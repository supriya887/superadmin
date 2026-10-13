<?php

namespace App\Services;

use App\Models\AdminUserCreation;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Support agent availability.
 *
 * Two independent signals:
 *  - support_status (manual toggle): active | inactive | away
 *  - last_active_at / last_logout_at (automatic): "Active Now" vs "Last active X ago"
 *
 * Only agents with support_status = active are eligible for AUTO_ROUND_ROBIN.
 */
class SupportAgentPresenceService
{
    /** Minimum seconds between two last_active_at writes for the same user. */
    private const TOUCH_THROTTLE_SECONDS = 60;

    private static ?bool $hasColumns = null;

    /**
     * Presence columns are added by a manual ALTER; stay backward compatible until then.
     */
    public function hasPresenceColumns(): bool
    {
        if (self::$hasColumns !== null) {
            return self::$hasColumns;
        }

        try {
            self::$hasColumns = Schema::connection('pgsql_admin')->hasColumns('admin_user_creation', [
                'support_status',
                'last_active_at',
                'last_logout_at',
            ]);
        } catch (\Throwable $e) {
            self::$hasColumns = false;
        }

        return self::$hasColumns;
    }

    public function onlineWindowMinutes(): int
    {
        return max(1, (int) config('services.support.online_window_minutes', 5));
    }

    /**
     * All active support agents (admin_user_creation.role = support role).
     *
     * @return Collection<int, AdminUserCreation>
     */
    public function agents(): Collection
    {
        return AdminUserCreation::query()
            ->supportAgents()
            ->orderBy('user_name')
            ->get();
    }

    public function isSupportAgent(int $userId): bool
    {
        return $userId > 0 && AdminUserCreation::query()->supportAgents()->whereKey($userId)->exists();
    }

    /**
     * Presence payload used by Blade and JSON endpoints.
     *
     * @return array{
     *     id: int, name: string, support_status: string, is_active: bool,
     *     is_online: bool, state: string, label: string, last_active_at: ?string
     * }
     */
    public function presenceFor(AdminUserCreation $user): array
    {
        $status = $this->normalizeStatus($user->support_status ?? null);
        $lastActive = $user->last_active_at ? Carbon::parse($user->last_active_at) : null;
        $lastLogout = $user->last_logout_at ? Carbon::parse($user->last_logout_at) : null;

        $isOnline = $lastActive !== null
            && $lastActive->greaterThanOrEqualTo(now()->subMinutes($this->onlineWindowMinutes()))
            && ($lastLogout === null || $lastLogout->lessThan($lastActive));

        $lastSeenLabel = $lastActive
            ? 'Last active ' . $lastActive->diffForHumans()
            : 'Never active';

        // Manual status wins over automatic online detection.
        if ($status === AdminUserCreation::SUPPORT_STATUS_INACTIVE) {
            $state = 'inactive';
            $label = 'Inactive';
        } elseif ($status === AdminUserCreation::SUPPORT_STATUS_AWAY) {
            $state = 'away';
            $label = $isOnline ? 'Away' : 'Away · ' . $lastSeenLabel;
        } elseif ($isOnline) {
            $state = 'online';
            $label = 'Active Now';
        } else {
            $state = 'offline';
            $label = $lastSeenLabel;
        }

        return [
            'id' => (int) $user->id,
            'name' => trim((string) ($user->user_name ?? '')) ?: ('Agent #' . $user->id),
            'support_status' => $status,
            'is_active' => $status === AdminUserCreation::SUPPORT_STATUS_ACTIVE,
            'is_online' => $isOnline,
            'state' => $state,
            'label' => $label,
            'last_active_at' => $lastActive?->toIso8601String(),
        ];
    }

    /**
     * Presence keyed by user id: all support agents plus any extra ids
     * (e.g. current assignee or staff authors in the conversation).
     *
     * @param  list<int>  $extraIds
     * @return array<int, array<string, mixed>>
     */
    public function presenceMap(array $extraIds = []): array
    {
        $users = $this->agents()->keyBy('id');

        $missing = array_values(array_diff(
            array_filter(array_map('intval', $extraIds)),
            $users->keys()->map(fn ($id) => (int) $id)->all()
        ));
        if ($missing !== []) {
            AdminUserCreation::query()->whereIn('id', $missing)->get()
                ->each(fn (AdminUserCreation $u) => $users->put($u->id, $u));
        }

        return $users->mapWithKeys(fn (AdminUserCreation $u) => [(int) $u->id => $this->presenceFor($u)])->all();
    }

    /**
     * Agent ids eligible for auto-assignment.
     * Prefers agents that are toggled Active AND online; falls back to toggled Active
     * (offline) so tickets still route. Inactive / Away agents are never returned.
     *
     * @param  list<int>|null  $restrictTo  optional candidate ids (e.g. team skill agents)
     * @return list<int>
     */
    public function assignableAgentIds(?array $restrictTo = null): array
    {
        $agents = $this->agents();

        if ($restrictTo !== null) {
            $agents = $agents->filter(fn (AdminUserCreation $u) => in_array((int) $u->id, $restrictTo, true));
        }

        if ($this->hasPresenceColumns()) {
            $agents = $agents->filter(
                fn (AdminUserCreation $u) => $this->normalizeStatus($u->support_status) === AdminUserCreation::SUPPORT_STATUS_ACTIVE
            );
        }

        $activeIds = $agents->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        if ($activeIds === [] || !$this->hasPresenceColumns()) {
            return $activeIds;
        }

        $onlineIds = $agents
            ->filter(fn (AdminUserCreation $u) => $this->presenceFor($u)['is_online'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return $onlineIds !== [] ? $onlineIds : $activeIds;
    }

    /**
     * Agents that are toggled Active AND online right now (no offline fallback).
     *
     * @return list<int>
     */
    public function onlineAgentIds(): array
    {
        if (!$this->hasPresenceColumns()) {
            return $this->assignableAgentIds();
        }

        return $this->agents()
            ->filter(function (AdminUserCreation $u) {
                $p = $this->presenceFor($u);
                return $p['is_active'] && $p['is_online'];
            })
            ->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    /**
     * Record activity for the logged-in admin user (throttled).
     */
    public function touch(int $userId, bool $force = false): void
    {
        if ($userId <= 0 || !$this->hasPresenceColumns()) {
            return;
        }

        if (!$force && !Cache::add('support_agent_touch_' . $userId, 1, self::TOUCH_THROTTLE_SECONDS)) {
            return;
        }

        try {
            $this->table()->where('id', $userId)->update(['last_active_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('Support agent touch failed: ' . $e->getMessage(), ['user_id' => $userId]);
        }
    }

    public function markLoggedOut(int $userId): void
    {
        if ($userId <= 0 || !$this->hasPresenceColumns()) {
            return;
        }

        Cache::forget('support_agent_touch_' . $userId);

        try {
            $this->table()->where('id', $userId)->update(['last_logout_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('Support agent logout mark failed: ' . $e->getMessage(), ['user_id' => $userId]);
        }
    }

    /**
     * Manually set active / inactive / away.
     *
     * @throws \InvalidArgumentException
     */
    public function setStatus(int $userId, string $status): AdminUserCreation
    {
        if (!$this->hasPresenceColumns()) {
            throw new \RuntimeException('Agent status columns are missing. Run the admin_user_creation ALTER first.');
        }

        $status = strtolower(trim($status));
        if (!in_array($status, AdminUserCreation::SUPPORT_STATUSES, true)) {
            throw new \InvalidArgumentException('Invalid status. Allowed: ' . implode(', ', AdminUserCreation::SUPPORT_STATUSES) . '.');
        }

        $user = AdminUserCreation::query()->supportAgents()->whereKey($userId)->first();
        if (!$user) {
            throw new \InvalidArgumentException('Support agent not found.');
        }

        $this->table()->where('id', $userId)->update(['support_status' => $status]);

        return $user->refresh();
    }

    /** Raw table access: admin_user_creation may not have updated_at. */
    private function table()
    {
        return DB::connection('pgsql_admin')->table('admin_user_creation');
    }

    private function normalizeStatus(?string $status): string
    {
        $status = strtolower(trim((string) $status));

        return in_array($status, AdminUserCreation::SUPPORT_STATUSES, true)
            ? $status
            : AdminUserCreation::SUPPORT_STATUS_ACTIVE;
    }
}
