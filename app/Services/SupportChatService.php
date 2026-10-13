<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Live chat routing: offline auto-reply, FIFO queue (status = open, no agent) and round-robin
 * distribution capped at services.support.chat_max_active open chats per agent.
 */
class SupportChatService
{
    public const CANNED = [
        'greeting' => 'Hello! Thank you for contacting Tizzy Support. How can I help you today?',
        'transfer' => 'I am transferring your chat to the right specialist. Please stay connected.',
        'resolution' => 'Glad we could resolve this for you. Is there anything else I can help with?',
        'wait' => 'Thanks for your patience. I am checking this and will update you shortly.',
    ];

    public function __construct(private SupportAgentPresenceService $presence) {}

    public function db()
    {
        return DB::connection('pgsql_support');
    }

    public function addMessage(int $chatId, string $type, ?int $senderId, ?string $message, bool $internal = false, array $attachments = []): int
    {
        $message = trim((string) $message);

        $id = $this->db()->table('chat_messages')->insertGetId([
            'chat_id' => $chatId,
            'sender_type' => $type,
            'sender_id' => $senderId ?? 0, // chat_messages.sender_id is NOT NULL; 0 = system
            'message' => $message === '' ? null : $message,
            'is_internal' => $internal,
            'created_at' => now(),
            'updated_at' => now(),
        ] + ($attachments ? ['attachments' => json_encode($attachments)] : []));
        $this->db()->table('chats')->where('id', $chatId)->update(['updated_at' => now()]);

        return $id;
    }

    /** Stored attachment JSON -> [{name, mime, size, url}] */
    public function attachmentsFor($stored): array
    {
        $files = is_string($stored) ? json_decode($stored, true) : $stored;

        return collect(is_array($files) ? $files : [])->map(fn ($f) => [
            'name' => (string) ($f['name'] ?? ''),
            'mime' => (string) ($f['mime'] ?? ''),
            'size' => (int) ($f['size'] ?? 0),
            'url' => rtrim((string) config('app.url'), '/') . '/storage/' . ltrim((string) ($f['path'] ?? ''), '/'),
        ])->values()->all();
    }

    /** Partner wrote: route the chat, or tell them nobody is online (once per 2 hours). */
    public function onPartnerMessage(object $chat): void
    {
        if (!empty($chat->assigned_agent_id)) {
            return;
        }

        if ($this->presence->onlineAgentIds() === []) {
            $key = 'chat_offline_reply_' . $chat->id;
            if (!Cache::has($key)) {
                $this->addMessage((int) $chat->id, 'system', null,
                    'All our support agents are currently offline. Our working hours are '
                    . config('services.support.chat_hours') . '. Your chat is in the queue and an agent will reply as soon as possible.');
                Cache::put($key, 1, now()->addHours(2));
            }
            return;
        }

        $this->distributeQueue();
    }

    /** Assign queued chats (oldest first) to the least-loaded online agent under capacity. */
    public function distributeQueue(): int
    {
        $online = $this->presence->onlineAgentIds();
        if ($online === []) {
            return 0;
        }

        $max = $this->maxActive();
        $load = $this->loadFor($online);

        $queue = $this->db()->table('chats as c')
            ->where('c.status', 'open')->whereNull('c.assigned_agent_id')
            ->whereExists(fn ($q) => $q->from('chat_messages as m')->whereColumn('m.chat_id', 'c.id')->where('m.sender_type', 'partner'))
            ->orderBy('c.created_at')->limit(100)->get(['c.id', 'c.team_id']);

        $assigned = 0;
        foreach ($queue as $chat) {
            $pool = $chat->team_id ? array_values(array_intersect($online, $this->teamAgentIds((int) $chat->team_id))) : $online;
            $agentId = $this->leastLoaded($pool, $load, $max);
            if ($agentId === null) {
                continue;
            }
            $this->assign((int) $chat->id, $agentId);
            $load[$agentId] = ($load[$agentId] ?? 0) + 1;
            $assigned++;
        }

        return $assigned;
    }

    public function assign(int $chatId, ?int $agentId, ?int $teamId = null): void
    {
        $data = ['assigned_agent_id' => $agentId, 'assigned_at' => $agentId ? now() : null, 'updated_at' => now()];
        if ($teamId) {
            $data['team_id'] = $teamId;
        }
        $this->db()->table('chats')->where('id', $chatId)->update($data);
    }

    /** Agent or department transfer, always logged as an internal note. Returns the new agent id (null = queued). */
    public function transfer(object $chat, int $byUserId, ?int $agentId, ?int $teamId, string $note, string $targetLabel): ?int
    {
        if (!$agentId && $teamId) {
            $online = array_values(array_intersect($this->presence->onlineAgentIds(), $this->teamAgentIds($teamId)));
            $agentId = $this->leastLoaded($online, $this->loadFor($online), $this->maxActive());
        }

        $this->assign((int) $chat->id, $agentId, $teamId);
        $this->addMessage((int) $chat->id, 'agent', $byUserId, 'Transferred to ' . $targetLabel . ': ' . $note, true);

        return $agentId;
    }

    public function teams()
    {
        try {
            return $this->db()->table('support_teams')->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        } catch (\Throwable $e) {
            return collect();
        }
    }

    private function maxActive(): int
    {
        return max(1, (int) config('services.support.chat_max_active', 5));
    }

    private function teamAgentIds(int $teamId): array
    {
        return $this->db()->table('support_agent_skills')->where('team_id', $teamId)
            ->pluck('agent_id')->map(fn ($id) => (int) $id)->all();
    }

    private function loadFor(array $agentIds): array
    {
        return $agentIds === [] ? [] : $this->db()->table('chats')->where('status', 'open')->whereIn('assigned_agent_id', $agentIds)
            ->groupBy('assigned_agent_id')->selectRaw('assigned_agent_id, COUNT(*) AS total')
            ->pluck('total', 'assigned_agent_id')->map(fn ($n) => (int) $n)->all();
    }

    private function leastLoaded(array $pool, array $load, int $max): ?int
    {
        $free = array_filter($pool, fn ($id) => ($load[$id] ?? 0) < $max);
        if ($free === []) {
            return null;
        }
        usort($free, fn ($a, $b) => ($load[$a] ?? 0) <=> ($load[$b] ?? 0));

        return (int) $free[0];
    }
}
