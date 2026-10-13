<?php

namespace App\Models;

use App\Services\SupportAgentPresenceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SupportTicket extends Model
{
    protected $connection = 'pgsql_support';
    protected $table = 'support_tickets';

    public const ASSIGNMENT_AUTO = 'AUTO_ROUND_ROBIN';
    public const ASSIGNMENT_MANUAL = 'MANUAL_OVERRIDE';
    public const ASSIGNMENT_REOPEN = 'REOPEN_ROUTED';

    public const STATUS_PENDING_ASSIGNMENT = 'Pending Assignment';
    public const STATUS_ESCALATED = 'Escalated';

    protected $fillable = [
        'ticket_no',
        'subject',
        'status',
        'priority',
        'customer_id',
        'provider_id',
        'partner_id',
        'partner',
        'requester',
        'email',
        'cc_emails',
        'tags',
        'domain',
        'service',
        'order_id',
        'plan_id',
        'created_by_partner_user_id',
        'created_by_type',
        'assigned_to',
        'team_id',
        'assignment_type',
        'first_response_due_at',
        'first_response_at',
        'is_escalated',
        'previous_status',
        'status_changed_at',
        'assigned_at',
        'resolved_at',
        'closed_at',
        'reopened_at',
        'last_customer_reply_at',
        'last_agent_reply_at',
        'waiting_since',
        'auto_close_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'cc_emails' => 'array',
        'is_escalated' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'status_changed_at' => 'datetime',
        'assigned_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'reopened_at' => 'datetime',
        'last_customer_reply_at' => 'datetime',
        'last_agent_reply_at' => 'datetime',
        'waiting_since' => 'datetime',
        'auto_close_at' => 'datetime',
        'first_response_due_at' => 'datetime',
        'first_response_at' => 'datetime',
    ];

    public function activities(): HasMany
    {
        return $this->hasMany(SupportActivity::class, 'ticket_id')->orderBy('id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SupportAttachment::class, 'ticket_id');
    }

    /** Whether ownership columns exist on support_tickets. */
    public static function hasOwnershipColumns(): bool
    {
        static $cached = null;
        if ($cached === null) {
            try {
                $cached = Schema::connection('pgsql_support')
                    ->hasColumn('support_tickets', 'created_by_partner_user_id');
            } catch (\Throwable $e) {
                $cached = false;
            }
        }

        return (bool) $cached;
    }

    /**
     * Partner company tickets; if partnerUserId set, only that user's tickets.
     */
    public function scopeForPartnerActor($query, int $partnerId, ?int $partnerUserId = null)
    {
        $query->where('partner_id', $partnerId);

        if ($partnerUserId !== null && $partnerUserId > 0 && self::hasOwnershipColumns()) {
            $query->where('created_by_partner_user_id', $partnerUserId);
        }

        return $query;
    }

    public function assignmentLogs(): HasMany
    {
        return $this->hasMany(SupportTicketAssignment::class, 'ticket_id')->orderByDesc('id');
    }

    /** Auto-assign on create (skill round-robin → unassigned pool). */
    public function autoAssignOnCreate(): void
    {
        $teamId = $this->resolveTeamIdFromService();
        if ($teamId) {
            $this->team_id = $teamId;
            $this->save();
        }

        $agentId = $this->pickRoundRobinAgent($teamId);
        if ($agentId) {
            $this->applyAssignment($agentId, self::ASSIGNMENT_AUTO, null, 'system', 'Auto-assigned via skill/category round-robin.');
            return;
        }

        $this->forceFill([
            'assigned_to' => null,
            'assigned_at' => null,
            'assignment_type' => null,
            'first_response_due_at' => null,
            'status' => self::STATUS_PENDING_ASSIGNMENT,
            'status_changed_at' => now(),
        ])->save();

        SupportActivity::create([
            'ticket_id' => $this->id,
            'user_type' => 'system',
            'message' => 'No online agents available for this category. Ticket placed in Unassigned Pool (Pending Assignment).',
            'type' => 'system',
            'author_name' => 'System',
            'author_role' => 'system',
            'is_internal' => true,
        ]);

        Log::warning('TICKET_UNASSIGNED_POOL', [
            'ticket_id' => $this->id,
            'ticket_no' => $this->ticket_no,
            'team_id' => $teamId,
            'service' => $this->service,
            'alert' => 'high_priority_dashboard',
        ]);
    }

    /** Reopen routing: prefer original agent, else round-robin. */
    public function autoAssignOnReopen(): void
    {
        $teamId = $this->team_id ?: $this->resolveTeamIdFromService();
        $original = $this->assigned_to ? (int) $this->assigned_to : null;
        $online = $this->onlineAgentIds($teamId);

        if ($original && in_array($original, $online, true)) {
            $this->applyAssignment(
                $original,
                self::ASSIGNMENT_REOPEN,
                null,
                'system',
                'Reopened ticket routed to original agent.'
            );
            return;
        }

        $agentId = $this->pickRoundRobinAgent($teamId);
        if ($agentId) {
            $note = $original
                ? 'Reassigned on Reopen due to original agent unavailability.'
                : 'Reopened ticket assigned via round-robin.';
            $this->applyAssignment($agentId, self::ASSIGNMENT_REOPEN, null, 'system', $note);
            return;
        }

        $this->forceFill([
            'assigned_to' => null,
            'assigned_at' => null,
            'assignment_type' => null,
            'first_response_due_at' => null,
            'status' => self::STATUS_PENDING_ASSIGNMENT,
            'status_changed_at' => now(),
        ])->save();

        SupportActivity::create([
            'ticket_id' => $this->id,
            'user_type' => 'system',
            'message' => 'Reopened ticket placed in Unassigned Pool — no online agents available.',
            'type' => 'system',
            'author_name' => 'System',
            'author_role' => 'system',
            'is_internal' => true,
        ]);
    }

    /**
     * Manual override from Super Admin.
     *
     * @return array{old_name: string, new_name: string}
     */
    public function manualAssign(int $newAgentId, ?int $changedBy, string $adminName): array
    {
        $oldId = $this->assigned_to ? (int) $this->assigned_to : null;
        $oldName = $oldId ? $this->agentDisplayName($oldId) : 'Unassigned';
        $newName = $this->agentDisplayName($newAgentId);

        $this->applyAssignment(
            $newAgentId,
            self::ASSIGNMENT_MANUAL,
            $changedBy,
            'admin',
            "Ticket reassigned from {$oldName} to {$newName} by {$adminName}.",
            $adminName
        );

        return ['old_name' => $oldName, 'new_name' => $newName];
    }

    public function applyAssignment(
        int $agentId,
        string $type,
        ?int $changedBy,
        string $changedByType,
        string $note,
        ?string $actorName = null
    ): void {
        $from = $this->assigned_to ? (int) $this->assigned_to : null;
        $agentName = $this->agentDisplayName($agentId);
        $firstName = $this->agentFirstName($agentId);
        $now = now();

        $status = (string) $this->status;
        if (in_array($status, [self::STATUS_PENDING_ASSIGNMENT, 'Pending', ''], true)
            || strtolower($status) === 'pending'
        ) {
            $status = 'Open';
        }

        $this->forceFill([
            'assigned_to' => $agentId,
            'assigned_at' => $now,
            'assignment_type' => $type,
            'first_response_due_at' => $now->copy()->addMinutes(15),
            'first_response_at' => null,
            'is_escalated' => false,
            'status' => $status,
            'status_changed_at' => $now,
        ])->save();

        SupportTicketAssignment::create([
            'ticket_id' => $this->id,
            'assigned_from' => $from,
            'assigned_to' => $agentId,
            'team_id' => $this->team_id,
            'changed_by' => $changedBy,
            'changed_by_type' => $changedByType,
            'note' => $note,
        ]);

        SupportActivity::create([
            'ticket_id' => $this->id,
            'user_id' => $changedBy,
            'user_type' => $changedByType === 'admin' ? 'superadmin' : 'system',
            'message' => $note,
            'type' => 'assignment',
            'author_name' => $actorName ?: ($changedByType === 'admin' ? 'Admin' : 'System'),
            'author_role' => $changedByType === 'admin' ? 'admin' : 'system',
            'is_internal' => true,
        ]);

        // Partner-facing system bubble
        SupportActivity::create([
            'ticket_id' => $this->id,
            'user_type' => 'system',
            'message' => "Support Agent {$firstName} has been assigned to your ticket and will assist you shortly.",
            'type' => 'system',
            'author_name' => 'System',
            'author_role' => 'system',
            'is_internal' => false,
        ]);

        // Real-time stand-in: toast payload for assigned agent (next dashboard poll/load)
        Cache::put('ticket_assigned_toast_' . $agentId, [
            'event' => 'TICKET_ASSIGNED',
            'ticket_id' => $this->id,
            'ticket_no' => $this->ticket_no,
            'subject' => $this->subject,
            'message' => 'New Ticket #' . ($this->ticket_no ?? $this->id) . ' assigned to you: ' . ($this->subject ?? ''),
        ], now()->addMinutes(30));

        Log::info('TICKET_ASSIGNED', [
            'ticket_id' => $this->id,
            'assigned_to' => $agentId,
            'assignment_type' => $type,
            'agent_name' => $agentName,
        ]);
    }

    public function markFirstResponseIfNeeded(): void
    {
        if ($this->first_response_at) {
            return;
        }
        $this->forceFill([
            'first_response_at' => now(),
            'last_agent_reply_at' => now(),
        ])->save();
    }

    public function resolveTeamIdFromService(): ?int
    {
        $service = strtolower(trim((string) ($this->service ?? '')));
        if ($service === '') {
            return null;
        }

        try {
            $row = DB::connection('pgsql_support')
                ->table('support_teams')
                ->where('is_active', true)
                ->where(function ($q) use ($service) {
                    $q->whereRaw('LOWER(name) = ?', [$service])
                        ->orWhereRaw('LOWER(code) = ?', [$service])
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%' . $service . '%']);
                })
                ->orderBy('id')
                ->first();

            return $row ? (int) $row->id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Lowest open-ticket count among online agents (optionally filtered by team skills). */
    public function pickRoundRobinAgent(?int $teamId): ?int
    {
        $agentIds = $this->onlineAgentIds($teamId);
        if ($agentIds === []) {
            return null;
        }

        $openStatuses = [
            'Open', 'In Progress', 'Waiting for Customer', 'Reopened',
            self::STATUS_PENDING_ASSIGNMENT, self::STATUS_ESCALATED, 'Pending',
        ];

        $counts = SupportTicket::query()
            ->selectRaw('assigned_to, COUNT(*) as cnt')
            ->whereIn('assigned_to', $agentIds)
            ->whereIn('status', $openStatuses)
            ->groupBy('assigned_to')
            ->pluck('cnt', 'assigned_to');

        $best = null;
        $bestCount = PHP_INT_MAX;
        foreach ($agentIds as $id) {
            $c = (int) ($counts[$id] ?? 0);
            if ($c < $bestCount) {
                $bestCount = $c;
                $best = $id;
            }
        }

        return $best;
    }

    /**
     * Agents eligible for auto-assignment: support-role users whose manual status is Active
     * (online agents preferred, see SupportAgentPresenceService::assignableAgentIds).
     * If the team has skill rows, only those agents are kept.
     *
     * @return list<int>
     */
    public function onlineAgentIds(?int $teamId): array
    {
        $skilled = null;
        if ($teamId) {
            try {
                $ids = DB::connection('pgsql_support')
                    ->table('support_agent_skills')
                    ->where('team_id', $teamId)
                    ->pluck('agent_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
                $skilled = $ids !== [] ? $ids : null;
            } catch (\Throwable $e) {
                // skills table may not exist yet — use all active agents
            }
        }

        return app(SupportAgentPresenceService::class)->assignableAgentIds($skilled);
    }

    public function agentDisplayName(int $agentId): string
    {
        $user = AdminUserCreation::query()->find($agentId);
        if (!$user) {
            return 'Agent #' . $agentId;
        }

        return trim((string) ($user->user_name ?? '')) ?: ('Agent #' . $agentId);
    }

    public function agentFirstName(int $agentId): string
    {
        $full = $this->agentDisplayName($agentId);
        $parts = preg_split('/\s+/', $full) ?: [$full];

        return $parts[0] ?: $full;
    }

    public static function assignedAgentPayload(?int $agentId): array
    {
        if (!$agentId) {
            return [
                'assigned_agent_id' => null,
                'assigned_agent_name' => null,
                'assigned_agent_first_name' => null,
                'chat_enabled' => false,
                'assignment_status_label' => 'Pending Assignment',
            ];
        }

        $ticket = new self();
        $name = $ticket->agentDisplayName($agentId);
        $first = $ticket->agentFirstName($agentId);

        return [
            'assigned_agent_id' => $agentId,
            'assigned_agent_name' => $name,
            'assigned_agent_first_name' => $first,
            'chat_enabled' => true,
            'assignment_status_label' => 'Assigned to ' . $first,
        ];
    }
}
