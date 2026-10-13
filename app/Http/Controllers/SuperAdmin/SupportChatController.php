<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PartnerUser;
use App\Services\SupportAgentPresenceService;
use App\Services\SupportChatService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Support agent live chat dashboard (threads, conversation, reply / internal note, transfer, close).
 */
class SupportChatController extends Controller
{
    public function __construct(private SupportChatService $chat, private SupportAgentPresenceService $presence) {}

    public function index()
    {
        return view('superadmin.support.chats', [
            'teams' => $this->chat->teams(),
            'canned' => SupportChatService::CANNED,
        ]);
    }

    /** GET ?status=open|closed&chat_id= : thread list + selected conversation (polled by the dashboard). */
    public function feed(Request $request): JsonResponse
    {
        if (Cache::add('chat_distribute_tick', 1, 15)) {
            $this->chat->distributeQueue();
        }

        $chats = $this->chat->db()->table('chats')
            ->where('status', $request->query('status') === 'closed' ? 'closed' : 'open')
            ->when(!$this->isSuperAdmin(), fn ($q) => $q->where(fn ($w) => $w->where('assigned_agent_id', $this->me())->orWhereNull('assigned_agent_id')))
            ->orderByDesc('updated_at')->limit(200)->get();

        $selected = null;
        if ($chat = $this->accessible((int) $request->query('chat_id'))) {
            $row = $this->rows(collect([$chat]))[0];
            $selected = ['chat' => $row, 'messages' => $this->messages($chat, $row)];
        }

        return response()->json([
            'success' => true,
            'me' => $this->me(),
            'threads' => $this->rows($chats),
            'selected' => $selected,
            'agents' => array_values(array_filter($this->presence->presenceMap(), fn ($a) => $a['is_active'])),
        ]);
    }

    public function send(Request $request, int $id): JsonResponse
    {
        $request->validate(['message' => 'required|string|max:5000', 'is_internal' => 'nullable|boolean']);

        $chat = $this->accessible($id);
        if (!$chat || $chat->status !== 'open') {
            return response()->json(['success' => false, 'message' => 'Chat not found or closed.'], 404);
        }

        if (empty($chat->assigned_agent_id)) {
            $this->chat->assign($id, $this->me());
        }
        $this->chat->addMessage($id, 'agent', $this->me(), $request->message, $request->boolean('is_internal'));

        return response()->json(['success' => true, 'message' => 'Message sent.']);
    }

    public function transfer(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'agent_id' => 'nullable|integer|required_without:team_id',
            'team_id' => 'nullable|integer|required_without:agent_id',
            'note' => 'required|string|min:5|max:1000',
        ]);

        $chat = $this->accessible($id);
        if (!$chat || $chat->status !== 'open') {
            return response()->json(['success' => false, 'message' => 'Chat not found or closed.'], 404);
        }

        $agentId = !empty($data['agent_id']) ? (int) $data['agent_id'] : null;
        $teamId = !empty($data['team_id']) ? (int) $data['team_id'] : null;
        $team = $teamId ? $this->chat->teams()->firstWhere('id', $teamId) : null;

        if (($agentId && !$this->presence->isSupportAgent($agentId)) || ($teamId && !$team)) {
            return response()->json(['success' => false, 'message' => 'Invalid agent or department.'], 422);
        }

        $label = $agentId
            ? ($this->presence->presenceMap([$agentId])[$agentId]['name'] ?? 'Agent #' . $agentId)
            : $team->name . ' department';
        $newAgent = $this->chat->transfer($chat, $this->me(), $agentId, $teamId, $data['note'], $label);

        return response()->json([
            'success' => true,
            'message' => $newAgent ? 'Chat transferred to ' . $label . '.' : 'No online agent in ' . $label . '. Chat moved to queue.',
        ]);
    }

    public function close(int $id): JsonResponse
    {
        $chat = $this->accessible($id);
        if (!$chat || $chat->status !== 'open') {
            return response()->json(['success' => false, 'message' => 'Chat not found or already closed.'], 404);
        }

        $this->chat->addMessage($id, 'system', null, 'This chat has been closed by support.');
        $this->chat->db()->table('chats')->where('id', $id)->update(['status' => 'closed', 'updated_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Chat closed.']);
    }

    private function rows(Collection $chats): array
    {
        if ($chats->isEmpty()) {
            return [];
        }

        $agents = $this->presence->presenceMap($chats->pluck('assigned_agent_id')->filter()->all());
        $partners = Partner::whereIn('id', $chats->pluck('partner_id')->unique())->get(['id', 'name', 'company_name'])->keyBy('id');
        $users = PartnerUser::whereIn('id', $chats->pluck('partner_user_id')->filter()->unique())->get(['id', 'name'])->keyBy('id');
        $last = $this->chat->db()->table('chat_messages')->whereIn('chat_id', $chats->pluck('id'))->where('is_internal', false)
            ->selectRaw('DISTINCT ON (chat_id) chat_id, id, sender_type, message')->orderBy('chat_id')->orderByDesc('id')->get()->keyBy('chat_id');

        return $chats->map(function ($c) use ($agents, $partners, $users, $last) {
            $p = $partners->get($c->partner_id);
            return [
                'id' => (int) $c->id,
                'partner_id' => (int) $c->partner_id,
                'partner_name' => $p ? ($p->company_name ?: $p->name) : 'Partner #' . $c->partner_id,
                'partner_user_id' => $c->partner_user_id ? (int) $c->partner_user_id : null,
                'user_name' => $c->partner_user_id ? ($users->get($c->partner_user_id)->name ?? 'User #' . $c->partner_user_id) : null,
                'status' => (string) $c->status,
                'assigned_agent_id' => $c->assigned_agent_id ? (int) $c->assigned_agent_id : null,
                'agent_name' => $agents[(int) $c->assigned_agent_id]['name'] ?? null,
                'last_message' => Str::limit((string) ($last->get($c->id)->message ?? ($last->has($c->id) ? 'Attachment' : '')), 60),
                'last_message_id' => (int) ($last->get($c->id)->id ?? 0),
                'last_sender' => (string) ($last->get($c->id)->sender_type ?? ''),
                'created_at' => Carbon::parse($c->created_at)->format('d M Y, h:i A'),
                'updated_at' => Carbon::parse($c->updated_at)->format('d M Y, h:i A'),
            ];
        })->values()->all();
    }

    private function messages(object $chat, array $row): array
    {
        $msgs = $this->chat->db()->table('chat_messages')->where('chat_id', $chat->id)->orderBy('id')->get();
        $agents = $this->presence->presenceMap($msgs->whereIn('sender_type', ['agent', 'admin'])->pluck('sender_id')->filter()->all());

        return $msgs->map(function ($m) use ($agents, $row) {
            $type = in_array($m->sender_type, ['agent', 'admin'], true) ? 'agent' : $m->sender_type;
            return [
                'id' => (int) $m->id,
                'type' => $type,
                'author' => match ($type) {
                    'partner' => $row['user_name'] ?: $row['partner_name'],
                    'agent' => $agents[(int) $m->sender_id]['name'] ?? 'Support',
                    default => 'System',
                },
                'message' => (string) ($m->message ?? ''),
                'attachments' => $this->chat->attachmentsFor($m->attachments ?? null),
                'is_internal' => (bool) ($m->is_internal ?? false),
                'time' => Carbon::parse($m->created_at)->format('d M, h:i A'),
            ];
        })->values()->all();
    }

    /** Super admin sees every chat; an agent sees chats assigned to them plus the unassigned queue. */
    private function accessible(int $id): ?object
    {
        $chat = $id > 0 ? $this->chat->db()->table('chats')->where('id', $id)->first() : null;
        if (!$chat) {
            return null;
        }

        return $this->isSuperAdmin() || empty($chat->assigned_agent_id) || (int) $chat->assigned_agent_id === $this->me() ? $chat : null;
    }

    private function me(): int
    {
        return (int) session('userId', 0);
    }

    private function isSuperAdmin(): bool
    {
        return strtolower(trim((string) session('userRole', ''))) === 'super_admin';
    }
}
