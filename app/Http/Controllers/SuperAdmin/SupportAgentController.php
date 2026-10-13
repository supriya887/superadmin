<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AdminUserCreation;
use App\Services\SupportAgentPresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * JSON endpoints for support agent presence and the manual Active/Inactive/Away toggle.
 */
class SupportAgentController extends Controller
{
    public function __construct(private SupportAgentPresenceService $presence) {}

    /**
     * GET /superadmin/support/agents/presence?ids=4,7
     * All support agents + any extra ids (e.g. conversation authors).
     */
    public function presence(Request $request): JsonResponse
    {
        $extraIds = array_filter(array_map('intval', explode(',', (string) $request->query('ids', ''))));

        return response()->json([
            'success' => true,
            'online_window_minutes' => $this->presence->onlineWindowMinutes(),
            'can_manage_all' => $this->isSuperAdmin(),
            'current_user_id' => (int) session('userId', 0),
            'agents' => array_values($this->presence->presenceMap($extraIds)),
        ]);
    }

    /**
     * POST /superadmin/support/agents/heartbeat
     * Keeps the logged-in user "Active Now" while any panel tab is open.
     */
    public function heartbeat(): JsonResponse
    {
        $userId = (int) session('userId', 0);
        if ($userId <= 0) {
            return response()->json(['success' => false, 'message' => 'Session expired.'], 401);
        }

        $this->presence->touch($userId, true);

        return response()->json(['success' => true]);
    }

    /**
     * POST /superadmin/support/agents/{id}/status  body: status=active|inactive|away
     * Super admin can change anyone; an agent can change only their own status.
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', 'in:' . implode(',', AdminUserCreation::SUPPORT_STATUSES)],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $sessionUserId = (int) session('userId', 0);
        if (!$this->isSuperAdmin() && $sessionUserId !== $id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only change your own availability.',
            ], 403);
        }

        try {
            $user = $this->presence->setStatus($id, (string) $request->input('status'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Support agent status update failed: ' . $e->getMessage(), ['agent_id' => $id]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        $payload = $this->presence->presenceFor($user);

        return response()->json([
            'success' => true,
            'message' => $payload['name'] . ' is now ' . ucfirst($payload['support_status']) . '.',
            'agent' => $payload,
        ]);
    }

    public function report(Request $request): View
    {
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date']);
        $from = $request->query('from');
        $to = $request->query('to');
        $agentId = (int) session('roleId', 0) === (int) config('services.support.agent_role_id', 3)
            ? (int) session('userId', 0)
            : 0;

        $schema = Schema::connection('pgsql_support');
        $doneAtCols = array_filter(['resolved_at', 'closed_at'], fn ($c) => $schema->hasColumn('support_tickets', $c));
        $doneAt = 'COALESCE(' . implode(', ', [...$doneAtCols, 'updated_at']) . ')';
        $done = "LOWER(TRIM(status)) IN ('resolved', 'closed')";

        $stats = DB::connection('pgsql_support')->table('support_tickets')
            ->selectRaw("assigned_to,
                COUNT(*) AS assigned,
                COUNT(*) FILTER (WHERE LOWER(TRIM(status)) = 'resolved') AS resolved,
                COUNT(*) FILTER (WHERE LOWER(TRIM(status)) = 'closed') AS closed,
                COUNT(*) FILTER (WHERE NOT ($done)) AS pending,
                AVG(EXTRACT(EPOCH FROM ($doneAt - created_at)) / 3600) FILTER (WHERE $done) AS avg_hours")
            ->where('assigned_to', '>', 0)
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->when($agentId, fn ($q) => $q->where('assigned_to', $agentId))
            ->groupBy('assigned_to')
            ->get()
            ->keyBy('assigned_to');

        $names = AdminUserCreation::query()
            ->when(
                $agentId,
                fn ($q) => $q->whereKey($agentId),
                fn ($q) => $q->whereIn('id', $stats->keys())->orWhere(fn ($w) => $w->supportAgents())
            )
            ->pluck('user_name', 'id');

        $rows = $names->map(function ($name, $id) use ($stats) {
            $s = $stats[$id] ?? null;
            $assigned = (int) ($s->assigned ?? 0);
            $doneCount = (int) ($s->resolved ?? 0) + (int) ($s->closed ?? 0);

            return (object) [
                'id' => (int) $id,
                'name' => trim((string) $name) ?: 'Agent #' . $id,
                'assigned' => $assigned,
                'pending' => (int) ($s->pending ?? 0),
                'resolved' => (int) ($s->resolved ?? 0),
                'closed' => (int) ($s->closed ?? 0),
                'done' => $doneCount,
                'rate' => $assigned ? round($doneCount * 100 / $assigned) : 0,
                'avg_hours' => $s && $s->avg_hours !== null ? (float) $s->avg_hours : null,
            ];
        })->sortByDesc('done')->values();

        return view('superadmin.support.report', compact('rows', 'from', 'to'));
    }

    private function isSuperAdmin(): bool
    {
        return strtolower(trim((string) session('userRole', ''))) === 'super_admin';
    }
}
