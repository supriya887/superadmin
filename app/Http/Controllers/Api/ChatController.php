<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Models\Partner;
use App\Models\PartnerUser;
use App\Services\SupportChatService;

class ChatController extends Controller
{
    /**
     * @return int|null|false null = owner (all), int = filter, false = invalid
     */
    private function resolvePartnerUserIdForChat(Request $request, int $partnerId)
    {
        if (!$request->filled('partner_user_id')) {
            return null;
        }

        $partnerUserId = (int) $request->partner_user_id;
        if ($partnerUserId <= 0) {
            return false;
        }

        $valid = PartnerUser::where('partner_id', $partnerId)
            ->where('id', $partnerUserId)
            ->exists();

        return $valid ? $partnerUserId : false;
    }

    /**
     * Get or create active chat session and fetch messages.
     */
    public function getConversation(Request $request): JsonResponse
    {
        $request->validate([
            'partner_id' => ['required', 'integer', 'exists:pgsql_auth.partners,id'],
            'partner_user_id' => ['nullable', 'integer'],
        ]);

        $partnerId = (int) $request->partner_id;
        $partnerUserId = $this->resolvePartnerUserIdForChat($request, $partnerId);
        
        if ($partnerUserId === false) {
            return response()->json([
                'success' => false,
                'message' => 'Partner user not found.',
            ], 422);
        }

        // Find existing active chat or create one
        $chatQuery = DB::connection('pgsql_support')->table('chats')
            ->where('partner_id', $partnerId)
            ->where('status', 'open');

        if ($partnerUserId) {
            $chatQuery->where('partner_user_id', $partnerUserId);
        } else {
            $chatQuery->whereNull('partner_user_id');
        }

        $chat = $chatQuery->first();

        if (!$chat) {
            $chatId = DB::connection('pgsql_support')->table('chats')->insertGetId([
                'partner_id' => $partnerId,
                'partner_user_id' => $partnerUserId,
                'status' => 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $chat = DB::connection('pgsql_support')->table('chats')->where('id', $chatId)->first();
        }

        $chatService = app(SupportChatService::class);
        $messages = DB::connection('pgsql_support')->table('chat_messages')
            ->where('chat_id', $chat->id)
            ->where('is_internal', false)
            ->orderBy('id', 'asc') // Oldest first (Ascending)
            ->get()
            ->map(function ($msg) use ($partnerId, $partnerUserId, $chatService) {
                // Everything not sent by the partner (agent, legacy admin, system auto-reply) is from support.
                $fromSupport = $msg->sender_type !== 'partner';
                $author = 'Support Team';
                if ($msg->sender_type === 'partner') {
                    if ($partnerUserId) {
                        $u = PartnerUser::find($partnerUserId);
                        $author = $u ? $u->name : 'User';
                    } else {
                        $p = Partner::find($partnerId);
                        $author = $p ? ($p->name ?: $p->company_name) : 'Partner';
                    }
                }

                $createdAt = \Carbon\Carbon::parse($msg->created_at);

                return [
                    'id' => (int) $msg->id,
                    'message' => $msg->message,
                    'attachments' => $files = $chatService->attachmentsFor($msg->attachments ?? null),
                    'has_attachments' => $files !== [],
                    'author' => $author,
                    'author_role' => $fromSupport ? 'support' : 'partner',
                    'is_staff' => $fromSupport,
                    'is_system' => $msg->sender_type === 'system',
                    'created_at' => $createdAt->format('Y-m-d H:i:s'),
                    'time' => $createdAt->format('h:i A'),
                    'created_on' => $createdAt->isToday() ? 'Today · ' . $createdAt->format('h:i A') : $createdAt->format('d M, Y h:i A'),
                ];
            });

        // Every file in this chat (newest first), e.g. for a "Files / Media" tab.
        $attachments = $messages->flatMap(fn ($m) => collect($m['attachments'])->map(fn ($a) => $a + [
            'message_id' => $m['id'],
            'author_role' => $m['author_role'],
            'created_on' => $m['created_on'],
        ]))->reverse()->values();

        return response()->json([
            'success' => true,
            'message' => 'Chat fetched successfully.',
            'data' => [
                'chat_id' => (int) $chat->id,
                'status' => $chat->status,
                'total_messages' => $messages->count(),
                'total_attachments' => $attachments->count(),
                'conversation' => $messages,
                'attachments' => $attachments,
            ],
        ], 200);
    }

    /**
     * Send message to the active chat session.
     */
    public function sendMessage(Request $request): JsonResponse
    {
        // Single file under "attachments" -> array
        if ($request->hasFile('attachments') && !is_array($request->file('attachments'))) {
            $request->files->set('attachments', [$request->file('attachments')]);
        }

        // Non-file "attachments" (JSON body, file path, base64): ignore empty values, reject the rest clearly.
        if (!$request->hasFile('attachments') && $request->has('attachments')) {
            if (array_filter((array) $request->input('attachments')) !== []) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attachments must be uploaded as files using multipart/form-data (attachments[]).',
                ], 422);
            }
            $request->request->remove('attachments');
        }

        $request->validate([
            'partner_id' => ['required', 'integer'],
            'partner_user_id' => ['nullable', 'integer'],
            'chat_id' => ['required', 'integer'],
            'message' => ['nullable', 'string', 'max:5000', 'required_without:attachments'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:5120', 'extensions:jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,txt,zip'],
        ]);

        $partnerId = (int) $request->partner_id;
        $chatId = (int) $request->chat_id;
        $partnerUserId = $this->resolvePartnerUserIdForChat($request, $partnerId);

        $chat = DB::connection('pgsql_support')->table('chats')
            ->where('id', $chatId)
            ->where('partner_id', $partnerId)
            ->first();

        if (!$chat) {
            return response()->json(['success' => false, 'message' => 'Chat not found.'], 404);
        }
        
        if ($chat->status !== 'open') {
            return response()->json(['success' => false, 'message' => 'This chat session is closed.'], 422);
        }

        $files = [];
        foreach ((array) $request->file('attachments', []) as $file) {
            if ($file && $file->isValid()) {
                $files[] = [
                    'path' => $file->store('support-chats/' . $chatId, 'public'),
                    'name' => $file->getClientOriginalName(),
                    'mime' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ];
            }
        }

        $chatService = app(SupportChatService::class);
        $messageId = $chatService->addMessage($chatId, 'partner', $partnerId, $request->message, false, $files);
        try {
            $chatService->onPartnerMessage($chat);
        } catch (\Throwable $e) {
            \Log::warning('Chat routing failed: ' . $e->getMessage(), ['chat_id' => $chatId]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Message sent successfully.',
            'data' => [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'message' => trim((string) $request->message) ?: null,
                'attachments' => $chatService->attachmentsFor($files),
            ],
        ], 200);
    }
}
