<?php

namespace App\Http\Controllers\SuperAdmin\Crm;

use App\Http\Controllers\Controller;
use App\Models\CrmUser;
use App\Models\Lead;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChatController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureTables();
        $chatId = (int) $request->query('chat');

        return view('superadmin.crm.chat.index', [
            'chats' => $this->chatList(),
            'users' => CrmUser::query()->orderBy('name')->get(['id', 'name', 'role']),
            'active' => $chatId > 0 ? $this->chatDetail($chatId) : null,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|integer',
        ]);

        $id = $this->openChat((int) $request->input('employee_id'), 0);

        return redirect()->route('crm.chat', ['chat' => $id]);
    }

    public function message(Request $request, int $id)
    {
        $request->validate(['message' => 'required|string|max:5000']);
        if (!$this->canSee($id)) {
            abort(404);
        }
        $this->addMessage($id, trim($request->input('message')));

        return redirect()->route('crm.chat', ['chat' => $id]);
    }

    public function chatList(): array
    {
        $this->ensureTables();
        $actor = $this->actor();
        $chats = $this->visibleQuery($actor)->orderByDesc('updated_at')->limit(100)->get();
        $leadIds = $chats->pluck('lead_id')->filter()->unique()->values();
        $leads = $leadIds->isEmpty()
            ? collect()
            : Lead::query()->whereIn('id', $leadIds)->get(['id', 'name', 'company_name'])->keyBy('id');
        $previews = $this->latestMessages($chats->pluck('id'));

        return $chats->map(function ($chat) use ($actor, $leads, $previews) {
            $preview = $previews->get($chat->id);

            $title = $this->title($chat, $actor, $leads);

            return [
                'id' => (int) $chat->id,
                'title' => $title,
                'kind' => $chat->lead_id ? 'Lead' : 'Direct',
                'employee_id' => $this->otherEmployeeId($chat, $actor),
                'initials' => $this->initials($title),
                'color' => $this->avatarColor($title),
                'preview' => $preview ? Str::limit($preview->message, 80) : 'No messages yet',
                'time' => $preview ? $this->whenLabel($preview->created_at) : '',
            ];
        })->all();
    }

    public function chatDetail(int $id): ?array
    {
        $this->ensureTables();
        $actor = $this->actor();
        $chat = $this->visibleQuery($actor)->where('crm_chats.id', $id)->first();
        if (!$chat) {
            return null;
        }

        $leads = $chat->lead_id
            ? Lead::query()->where('id', $chat->lead_id)->get(['id', 'name', 'company_name'])->keyBy('id')
            : collect();
        $messages = $this->db()->table('crm_chat_messages')
            ->where('chat_id', $id)
            ->orderByDesc('id')
            ->limit(300)
            ->get()
            ->reverse()
            ->values()
            ->map(function ($row) use ($actor) {
                $at = Carbon::parse($row->created_at);

                return [
                    'id' => (int) $row->id,
                    'sender_type' => $row->sender_type,
                    'sender_id' => (int) $row->sender_id,
                    'name' => $row->sender_name,
                    'message' => $row->message,
                    'time' => $at->format('d M, h:i A'),
                    'day' => $at->isToday() ? 'Today' : ($at->isYesterday() ? 'Yesterday' : $at->format('d M Y')),
                    'clock' => $at->format('h:i A'),
                    'mine' => $row->sender_type === $actor['type'] && (int) $row->sender_id === (int) $actor['id'],
                ];
            })
            ->all();

        $title = $this->title($chat, $actor, $leads);

        return [
            'id' => (int) $chat->id,
            'lead_id' => $chat->lead_id ? (int) $chat->lead_id : null,
            'employee_id' => $this->otherEmployeeId($chat, $actor),
            'title' => $title,
            'initials' => $this->initials($title),
            'color' => $this->avatarColor($title),
            'kind' => $chat->lead_id ? 'Lead' : 'Direct',
            'messages' => $messages,
        ];
    }

    public function openChat(int $employeeId, int $leadId): int
    {
        $this->ensureTables();
        $actor = $this->actor();

        if ($leadId > 0) {
            if (!Lead::query()->where('id', $leadId)->exists()) {
                throw ValidationException::withMessages(['lead_id' => 'Lead not found.']);
            }

            return $this->leadChat($leadId);
        }

        $employee = $employeeId > 0 ? CrmUser::query()->find($employeeId) : null;
        if (!$employee) {
            throw ValidationException::withMessages(['employee_id' => 'Employee not found.']);
        }
        if ($actor['type'] === 'employee' && (int) $actor['id'] === (int) $employee->id) {
            throw ValidationException::withMessages(['employee_id' => 'Choose another employee.']);
        }

        return $this->directChat($actor, [
            'type' => 'employee',
            'id' => (int) $employee->id,
            'name' => $employee->name ?: 'Employee',
        ]);
    }

    public function canSee(int $id): bool
    {
        $this->ensureTables();

        return $this->visibleQuery($this->actor())->where('crm_chats.id', $id)->exists();
    }

    public function addMessage(int $id, string $message): void
    {
        $actor = $this->actor();
        $now = now();
        $this->db()->table('crm_chat_messages')->insert([
            'chat_id' => $id,
            'sender_type' => $actor['type'],
            'sender_id' => $actor['id'],
            'sender_name' => $actor['name'],
            'message' => $message,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->db()->table('crm_chats')->where('id', $id)->update(['updated_at' => $now]);
    }

    protected function otherEmployeeId(object $chat, array $actor): ?int
    {
        if ($chat->lead_id) {
            return null;
        }

        foreach (['a', 'b'] as $side) {
            if ($chat->{'user_' . $side . '_type'} !== 'employee') {
                continue;
            }
            $id = (int) $chat->{'user_' . $side . '_id'};
            if ($actor['type'] === 'employee' && (int) $actor['id'] === $id) {
                continue;
            }

            return $id;
        }

        return null;
    }

    protected function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= strtoupper(substr($part, 0, 1));
        }

        return $letters !== '' ? $letters : 'C';
    }

    protected function avatarColor(string $name): string
    {
        $colors = ['#e0b03a', '#e15b64', '#7b61c9', '#3d9aa8', '#5c7cfa', '#d36b9a', '#de8a3c', '#6f9b45'];

        return $colors[abs(crc32($name)) % count($colors)];
    }

    protected function whenLabel($value): string
    {
        $at = Carbon::parse($value);
        if ($at->isToday()) {
            return $at->format('h:i A');
        }
        if ($at->isYesterday()) {
            return 'Yesterday';
        }
        if ($at->greaterThan(now()->subDays(6))) {
            return $at->format('l');
        }

        return $at->format('d M');
    }

    protected function actor(): array
    {
        $employee = auth('crm')->user();
        if ($employee) {
            return [
                'type' => 'employee',
                'id' => (int) $employee->id,
                'name' => $employee->name ?: 'Employee',
            ];
        }

        return [
            'type' => 'admin',
            'id' => (int) session('userId'),
            'name' => (string) session('userName', 'Admin'),
        ];
    }

    protected function leadChat(int $leadId): int
    {
        $existing = $this->db()->table('crm_chats')->where('lead_id', $leadId)->value('id');
        if ($existing) {
            return (int) $existing;
        }

        try {
            return (int) $this->db()->table('crm_chats')->insertGetId([
                'lead_id' => $leadId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $again = $this->db()->table('crm_chats')->where('lead_id', $leadId)->value('id');
            if ($again) {
                return (int) $again;
            }
            throw $e;
        }
    }

    protected function directChat(array $actor, array $other): int
    {
        [$a, $b] = strcmp($actor['type'] . ':' . $actor['id'], $other['type'] . ':' . $other['id']) <= 0
            ? [$actor, $other]
            : [$other, $actor];

        $existing = $this->db()->table('crm_chats')
            ->whereNull('lead_id')
            ->where('user_a_type', $a['type'])->where('user_a_id', $a['id'])
            ->where('user_b_type', $b['type'])->where('user_b_id', $b['id'])
            ->value('id');
        if ($existing) {
            return (int) $existing;
        }

        try {
            return (int) $this->db()->table('crm_chats')->insertGetId([
                'user_a_type' => $a['type'],
                'user_a_id' => $a['id'],
                'user_a_name' => $a['name'],
                'user_b_type' => $b['type'],
                'user_b_id' => $b['id'],
                'user_b_name' => $b['name'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $again = $this->db()->table('crm_chats')
                ->whereNull('lead_id')
                ->where('user_a_type', $a['type'])->where('user_a_id', $a['id'])
                ->where('user_b_type', $b['type'])->where('user_b_id', $b['id'])
                ->value('id');
            if ($again) {
                return (int) $again;
            }
            throw $e;
        }
    }

    protected function visibleQuery(array $actor)
    {
        return $this->db()->table('crm_chats')->where(function ($query) use ($actor) {
            $query->whereNotNull('lead_id')
                ->orWhere(function ($query) use ($actor) {
                    $query->where('user_a_type', $actor['type'])->where('user_a_id', $actor['id']);
                })
                ->orWhere(function ($query) use ($actor) {
                    $query->where('user_b_type', $actor['type'])->where('user_b_id', $actor['id']);
                });
        });
    }

    protected function title(object $chat, array $actor, $leads): string
    {
        if ($chat->lead_id) {
            $lead = $leads->get($chat->lead_id);

            return $lead ? ($lead->company_name ?: ($lead->name ?: 'Lead')) : ('Lead #' . $chat->lead_id);
        }

        $mineIsA = $chat->user_a_type === $actor['type'] && (int) $chat->user_a_id === (int) $actor['id'];

        return $mineIsA ? ($chat->user_b_name ?: 'Chat') : ($chat->user_a_name ?: 'Chat');
    }

    protected function latestMessages($chatIds)
    {
        if ($chatIds->isEmpty()) {
            return collect();
        }

        $ids = $this->db()->table('crm_chat_messages')
            ->select('chat_id', DB::raw('max(id) as id'))
            ->whereIn('chat_id', $chatIds)
            ->groupBy('chat_id')
            ->pluck('id');

        return $ids->isEmpty()
            ? collect()
            : $this->db()->table('crm_chat_messages')->whereIn('id', $ids)->get()->keyBy('chat_id');
    }

    protected function ensureTables(): void
    {
        if (!Schema::connection('pgsql_crm')->hasTable('crm_chats')) {
            Schema::connection('pgsql_crm')->create('crm_chats', function ($table) {
                $table->id();
                $table->unsignedBigInteger('lead_id')->nullable()->unique();
                $table->string('user_a_type', 20)->nullable();
                $table->unsignedBigInteger('user_a_id')->nullable();
                $table->string('user_a_name')->nullable();
                $table->string('user_b_type', 20)->nullable();
                $table->unsignedBigInteger('user_b_id')->nullable();
                $table->string('user_b_name')->nullable();
                $table->timestamps();
                $table->unique(['user_a_type', 'user_a_id', 'user_b_type', 'user_b_id'], 'crm_chats_direct_unique');
            });
        }

        if (!Schema::connection('pgsql_crm')->hasTable('crm_chat_messages')) {
            Schema::connection('pgsql_crm')->create('crm_chat_messages', function ($table) {
                $table->id();
                $table->unsignedBigInteger('chat_id')->index();
                $table->string('sender_type', 20);
                $table->unsignedBigInteger('sender_id')->default(0);
                $table->string('sender_name');
                $table->text('message');
                $table->timestamps();
            });
        }
    }

    protected function db()
    {
        return DB::connection('pgsql_crm');
    }
}
