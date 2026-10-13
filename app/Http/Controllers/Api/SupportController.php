<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Partner;
use App\Models\PartnerUser;
use App\Models\Plan;
use App\Models\Provider;
use App\Models\SupportActivity;
use App\Models\SupportAttachment;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;
class SupportController extends Controller
{
    
    public function detailsForSupport(Request $request): JsonResponse
    {
        try {
            $customerId = (int) ($request->input('customer_id') ?? 0);
            if ($customerId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Valid customer_id is required.',
                    'data' => null,
                ], 422);
            }

            $partnerId = (int) ($request->input('partner_id') ?? 0);

            $customerQuery = Customer::query()->where('id', $customerId);
            if ($partnerId > 0) {
                $customerQuery->where('partner_id', $partnerId);
            }
            $customer = $customerQuery->first();

            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found.',
                    'data' => null,
                ], 404);
            }

            $tree = $this->buildCascadingTree((int) $customer->id);

            return response()->json([
                'success' => true,
                'message' => empty($tree['providers'])
                    ? 'No providers / plans / domains found for this customer.'
                    : 'details fetched successfully.',
                'data' => [
                    'customer' => [
                        'customer_id' => (int) $customer->id,
                        'name' => (string) ($customer->name ?? ''),
                        'company_name' => (string) ($customer->company_name ?? ''),
                        'email' => (string) ($customer->email ?? ''),
                        'mobile' => (string) ($customer->mobile ?? ''),
                        'partner_id' => (int) ($customer->partner_id ?? 0),
                    ],
                    'providers' => $tree['providers'],
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch details.',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Build providers → plans → domains from customer's live orders.
     *
     * @return array{providers: list<array<string, mixed>>}
     */
    private function buildCascadingTree(int $customerId): array
    {
        $orders = Order::query()
            ->where('user_id', $customerId)
            ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) NOT IN ('draft', 'cancelled', 'canceled', 'failed', 'rejected')")
            ->orderByDesc('id')
            ->get(['id', 'order_no', 'plan_id', 'domain_name', 'status', 'quantity', 'partner_id']);

        if ($orders->isEmpty()) {
            return ['providers' => []];
        }

        $orderIds = $orders->pluck('id')->map(fn ($id) => (int) $id)->all();
        $itemsByOrder = OrderItem::query()
            ->whereIn('order_id', $orderIds)
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereRaw("LOWER(TRIM(COALESCE(status, ''))) NOT IN ('delete', 'deleted')");
            })
            ->get(['id', 'order_id', 'plan_id', 'quantity', 'status'])
            ->groupBy(fn ($row) => (int) $row->order_id);

        // Resolve plan ids from order header + order_items
        $planIds = [];
        foreach ($orders as $order) {
            $oid = (int) $order->id;
            $headerPlanId = (int) ($order->plan_id ?? 0);
            if ($headerPlanId > 0) {
                $planIds[$headerPlanId] = true;
            }
            foreach ($itemsByOrder->get($oid, collect()) as $item) {
                $pid = (int) ($item->plan_id ?? 0);
                if ($pid > 0) {
                    $planIds[$pid] = true;
                }
            }
        }
        $planIdList = array_keys($planIds);

        $plansById = $planIdList !== []
            ? Plan::query()->whereIn('id', $planIdList)->get()->keyBy('id')
            : collect();

        $providerIds = $plansById
            ->pluck('provider_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $providersById = $providerIds !== []
            ? Provider::query()->whereIn('id', $providerIds)->get()->keyBy('id')
            : collect();

        // Nested buckets: provider_id → plan_id → domain key → domain payload
        $tree = [];

        foreach ($orders as $order) {
            $oid = (int) $order->id;
            $domain = trim((string) ($order->domain_name ?? ''));
            if ($domain === '') {
                continue;
            }

            $linePlanIds = [];
            $headerPlanId = (int) ($order->plan_id ?? 0);
            if ($headerPlanId > 0) {
                $linePlanIds[] = $headerPlanId;
            }
            foreach ($itemsByOrder->get($oid, collect()) as $item) {
                $pid = (int) ($item->plan_id ?? 0);
                if ($pid > 0) {
                    $linePlanIds[] = $pid;
                }
            }
            $linePlanIds = array_values(array_unique($linePlanIds));
            if ($linePlanIds === []) {
                continue;
            }

            foreach ($linePlanIds as $planId) {
                $plan = $plansById->get($planId);
                if (!$plan) {
                    continue;
                }
                $providerId = (int) ($plan->provider_id ?? 0);
                if ($providerId <= 0 || !$providersById->has($providerId)) {
                    continue;
                }
                $provider = $providersById->get($providerId);

                if (!isset($tree[$providerId])) {
                    $tree[$providerId] = [
                        'provider_id' => $providerId,
                        'provider_name' => (string) ($provider->name ?? ''),
                        'plans' => [],
                    ];
                }

                if (!isset($tree[$providerId]['plans'][$planId])) {
                    $tree[$providerId]['plans'][$planId] = [
                        'plan_id' => $planId,
                        'plan_name' => (string) ($plan->name ?? $plan->title ?? ''),
                        'domains' => [],
                    ];
                }

                $domainKey = strtolower($domain);
                if (!isset($tree[$providerId]['plans'][$planId]['domains'][$domainKey])) {
                    $tree[$providerId]['plans'][$planId]['domains'][$domainKey] = [
                        'domain' => $domain,
                        'domain_name' => $domain,
                        'order_id' => $oid,
                        'order_no' => (string) ($order->order_no ?? ''),
                        'order_status' => (string) ($order->status ?? ''),
                    ];
                }
            }
        }

        $providers = [];
        foreach ($tree as $providerNode) {
            $plans = [];
            foreach ($providerNode['plans'] as $planNode) {
                $plans[] = [
                    'plan_id' => $planNode['plan_id'],
                    'plan_name' => $planNode['plan_name'],
                    'domains' => array_values($planNode['domains']),
                ];
            }
            usort($plans, fn ($a, $b) => strcasecmp($a['plan_name'], $b['plan_name']));
            $providers[] = [
                'provider_id' => $providerNode['provider_id'],
                'provider_name' => $providerNode['provider_name'],
                'plans' => $plans,
            ];
        }
        usort($providers, fn ($a, $b) => strcasecmp($a['provider_name'], $b['provider_name']));

        return ['providers' => $providers];
    }
      public function generateTicketPreview(Request $request): JsonResponse
    {
        $request->validate([
            'partner_id' => ['required', 'integer', 'exists:pgsql_auth.partners,id'],
            'partner_user_id' => ['nullable', 'integer'],
            'subject' => ['required', 'string'],
            'service_name' => ['required', 'string'],
            'description' => ['required', 'string'],
            'error_details' => ['nullable', 'string'],
        ]);

        $partnerId = (int) $request->partner_id;
        $partnerUserId = $this->resolvePartnerUserIdForTickets($request, $partnerId);
        
        $name = 'Partner';
        if ($partnerUserId) {
            $user = \App\Models\PartnerUser::find($partnerUserId);
            $name = $user ? $user->name : 'Partner User';
        } else {
            $partner = \App\Models\Partner::find($partnerId);
            $name = $partner ? ($partner->name ?: $partner->company_name) : 'Partner';
        }

        $subject = trim($request->subject);
        $serviceName = trim($request->service_name);
        $description = trim($request->description);
        $errorDetails = $request->filled('error_details') ? trim($request->error_details) : 'N/A';

        $preview = "Subject: {$subject}\n\n"
            . "Hello Support Team,\n\n"
            . "I am experiencing an issue with my {$serviceName}.\n\n"
            . "- Service Name: {$serviceName}\n"
            . "- Problem Description: {$description}\n"
            . "- Error Details: {$errorDetails}\n\n"
            . "Please look into this issue and help resolve it as soon as possible.\n\n"
            . "Thanks & Regards,\n"
            . "{$name}";

        return response()->json([
            'success' => true,
            'message' => 'Ticket preview generated successfully.',
            'data' => [
                'preview_text' => $preview
            ],
        ], 200);
    }

    public function getTicketConversation(Request $request): JsonResponse
    {
        $request->validate([
            'partner_id' => ['required', 'integer', 'exists:pgsql_auth.partners,id'],
            'ticket_id' => ['required', 'integer'],
            'partner_user_id' => ['nullable', 'integer'],
        ]);

        $partnerId = (int) $request->partner_id;
        $ticketId = (int) $request->ticket_id;
        $partnerUserId = $this->resolvePartnerUserIdForTickets($request, $partnerId);
        if ($partnerUserId === false) {
            return response()->json([
                'success' => false,
                'message' => 'Partner user not found for this partner.',
                'data' => null,
            ], 422);
        }

        $ticket = SupportTicket::query()
            ->forPartnerActor($partnerId, $partnerUserId)
            ->where('id', $ticketId)
            ->first();

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found.',
                'data' => null,
            ], 404);
        }

        $partner = Partner::find($partnerId);

        $activitiesQuery = SupportActivity::query()
            ->where('ticket_id', $ticket->id)
            ->whereIn('type', ['created', 'reply', 'comment', 'reopened', 'system', 'assignment'])
            ->orderBy('id');

        // Hide internal notes from partner conversation
        if (\Illuminate\Support\Facades\Schema::connection('pgsql_support')->hasColumn('support_activities', 'is_internal')) {
            $activitiesQuery->where(function ($q) {
                $q->whereNull('is_internal')->orWhere('is_internal', false);
            });
        }

        $activities = $activitiesQuery->get();

        $hasAttachments = \Illuminate\Support\Facades\Schema::connection('pgsql_support')->hasTable('ticket_attachments');
        $attachmentsByActivity = collect();
        if ($hasAttachments && $activities->isNotEmpty()) {
            $attachmentsByActivity = SupportAttachment::query()
                ->where('ticket_id', $ticket->id)
                ->whereIn('activity_id', $activities->pluck('id'))
                ->get()
                ->groupBy('activity_id');
        }

        $conversation = $activities
            ->filter(function (SupportActivity $a) {
                $type = strtolower((string) ($a->type ?? ''));
                $message = trim((string) ($a->message ?? ''));

                if ($type === 'assignment') return false;
                // Removed: if ($type === 'created') return false; so the initial description is shown!
                if ($type === 'system' && (stripos($message, 'has been assigned to your ticket') !== false || stripos($message, 'will assist you shortly') !== false)) {
                    return false;
                }
                if (!empty($a->is_internal)) return false;
                
                return true;
            })
            ->map(function (SupportActivity $a) use ($partner, $attachmentsByActivity) {
                $userType = strtolower((string) ($a->user_type ?? ''));
                $isSystem = in_array(strtolower((string) ($a->type ?? '')), ['system'], true) || $userType === 'system';
                $isStaff = in_array($userType, ['superadmin', 'admin', 'agent'], true);

                $author = (string) ($a->author_name ?? '');
                if ($author === '') {
                    if ($isSystem) $author = 'System';
                    elseif ($isStaff) $author = 'Support Team';
                    elseif ($userType === 'partner') $author = (string) ($partner?->name ?? $partner?->company_name ?? 'Partner');
                    else $author = $userType !== '' ? ucfirst($userType) : 'System';
                }

                $files = ($attachmentsByActivity->get($a->id) ?? collect())->map(function (SupportAttachment $file) {
                    $path = ltrim(str_replace('\\', '/', (string) ($file->file_path ?? '')), '/');
                    $path = preg_replace('#^(storage/app/public/|public/|storage/)#', '', $path);
                    return [
                        'id' => (int) $file->id,
                        'filename' => (string) ($file->filename ?? ''),
                        'mime_type' => (string) ($file->mime_type ?? ''),
                        'file_size' => (int) ($file->file_size ?? 0),
                        'url' => $path !== '' ? rtrim((string) config('app.url'), '/') . '/storage/' . $path : null,
                    ];
                })->values();

                return [
                    'id' => (int) $a->id,
                    'type' => (string) ($a->type ?? ''),
                    'message' => (string) ($a->message ?? ''),
                    'author' => $author,
                    'author_role' => $isSystem ? 'system' : ($isStaff ? 'support' : ($userType === 'partner' ? 'partner' : 'system')),
                    'is_staff' => $isStaff,
                    'is_system' => $isSystem,
                    'created_at' => $a->created_at ? $a->created_at->format('Y-m-d H:i:s') : null,
                    'time' => $a->created_at ? $a->created_at->format('h:i A') : '',
                    'created_on' => $a->created_at ? ($a->created_at->isToday() ? 'Today · ' . $a->created_at->format('h:i A') : $a->created_at->format('d M, Y h:i A')) : '',
                    'attachments' => $files,
                ];
            })->values();

        $userName = trim((string) ($partner->name ?? $partner->company_name ?? 'User'));
        $welcomeMessageText = "{$userName},\n\n"
            . "Thank you for contacting our support team. A support ticket has now been opened for your request. You will be notified when a response is made by email. The details of your ticket are shown below.\n\n"
            . "Ticket ID: #{$ticket->ticket_no}\n"
            . "Subject: {$ticket->subject}\n"
            . "Status: {$ticket->status}\n\n"
            . "Thanks & Regards,\n"
            . "Support Team";

        $welcomeMessage = [
            'id' => 0,
            'type' => 'system',
            'message' => $welcomeMessageText,
            'author' => 'System',
            'author_role' => 'system',
            'is_staff' => true,
            'is_system' => true,
            'created_at' => $ticket->created_at ? $ticket->created_at->format('Y-m-d H:i:s') : null,
            'time' => $ticket->created_at ? $ticket->created_at->format('h:i A') : '',
            'created_on' => $ticket->created_at ? ($ticket->created_at->isToday() ? 'Today · ' . $ticket->created_at->format('h:i A') : $ticket->created_at->format('d M, Y h:i A')) : '',
            'attachments' => [],
        ];

        // Insert the automated welcome message right after the user's initial description
        // Insert the automated welcome message right after the user's initial description
        // Since activities are ordered by ID ASC, the user's initial description is at index 0.
        $conversation->splice(1, 0, [$welcomeMessage]);

        // The user requested the conversation to be in descending order (newest first).
        $conversation = $conversation->reverse()->values();

        $assignMeta = SupportTicket::assignedAgentPayload($ticket->assigned_to ? (int) $ticket->assigned_to : null);

        return response()->json([
            'success' => true,
            'message' => 'Conversation fetched successfully.',
            'data' => [
                'ticket_id' => (int) $ticket->id,
                'ticket_no' => (string) ($ticket->ticket_no ?? ''),
                'subject' => (string) ($ticket->subject ?? ''),
                'status' => (string) ($ticket->status ?? ''),
                'total_messages' => $conversation->count(),
                'conversation' => $conversation,
            ] + $assignMeta,
        ], 200);
    }

    /**
     * Partner reply on ticket conversation.
     * POST /api/v1/partner/send-message
     * Body: partner_id, ticket_id, message, attachments[] (optional)
     */
    public function reply_message(Request $request): JsonResponse
    {
        try {
            if ($request->hasFile('attachments') && !is_array($request->file('attachments'))) {
                $request->files->set('attachments', [$request->file('attachments')]);
            }
            if (!$request->hasFile('attachments')) {
                $request->request->remove('attachments');
                $request->files->remove('attachments');
            }

            $rules = [
                'partner_id' => ['required', 'integer', 'exists:pgsql_auth.partners,id'],
                'ticket_id' => ['required', 'integer'],
                'partner_user_id' => ['nullable'],
                'message' => ['nullable', 'string'],
                'email' => ['nullable', 'email'],
                'name' => ['nullable', 'string'],
            ];
            if ($request->hasFile('attachments')) {
                $rules['attachments'] = ['array', 'max:5'];
                $rules['attachments.*'] = ['file', 'max:5120', 'extensions:jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,txt,zip'];
            }

            $request->validate($rules);

            $partnerId = (int) $request->partner_id;
            $ticketId = (int) $request->ticket_id;
            $partnerUserId = $this->resolvePartnerUserIdForTickets($request, $partnerId);
            if ($partnerUserId === false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Partner user not found for this partner.',
                    'data' => null,
                ], 422);
            }
            $message = trim((string) $request->input('message', ''));
            $hasFiles = $request->hasFile('attachments');

            if ($message === '' && !$hasFiles) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please enter a message or attach a file.',
                    'data' => null,
                ], 422);
            }   

            if ($message === '' && $hasFiles) {
                $message = 'Attachment';
            }

            $ticket = SupportTicket::query()
                ->forPartnerActor($partnerId, $partnerUserId)
                ->where('id', $ticketId)
                ->first();

            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket not found.',
                    'data' => null,
                ], 404);
            }

            $status = strtolower(trim((string) ($ticket->status ?? '')));
            if (in_array($status, ['closed'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot reply to a closed ticket. Please reopen it first.',
                    'data' => null,
                ], 422);
            }

            $partner = Partner::find($partnerId);
            $authorName = trim((string) ($partner?->name ?? $partner?->company_name ?? 'Partner'));
            if ($authorName === '') {
                $authorName = 'Partner';
            }

            $activity = null;
            $savedAttachments = [];

            DB::connection('pgsql_support')->transaction(function () use (
                $request,
                $ticket,
                $partnerId,
                $message,
                $authorName,
                $status,
                &$activity,
                &$savedAttachments
            ) {
                $payload = [
                    'ticket_id' => $ticket->id,
                    'user_id' => $partnerId,
                    'user_type' => 'partner',
                    'message' => $message,
                    'type' => 'reply',
                    'is_internal' => false,
                    'author_name' => $authorName,
                    'author_role' => 'partner',
                    'notify_customer' => false,
                ];
               
                $activity = SupportActivity::create($payload);

                if ($request->hasFile('attachments') && Schema::connection('pgsql_support')->hasTable('ticket_attachments')) {
                    foreach ($request->file('attachments') as $file) {
                        if (!$file || !$file->isValid()) {
                            continue;
                        }
                        $storedPath = $file->store('support-tickets/' . $ticket->id, 'public');
                        $attachPayload = [
                            'ticket_id' => $ticket->id,
                            'activity_id' => $activity->id,
                            'file_path' => $storedPath,
                            'filename' => $file->getClientOriginalName(),
                            'mime_type' => $file->getClientMimeType(),
                            'file_size' => $file->getSize(),
                            'uploaded_by' => $partnerId,
                        ];
                        if (Schema::connection('pgsql_support')->hasColumn('ticket_attachments', 'uploaded_by_type')) {
                            $attachPayload['uploaded_by_type'] = 'partner';
                        }
                        if (Schema::connection('pgsql_support')->hasColumn('ticket_attachments', 'is_internal')) {
                            $attachPayload['is_internal'] = false;
                        }
                        $attachment = SupportAttachment::create($attachPayload);

                        $path = ltrim(str_replace('\\', '/', (string) $storedPath), '/');
                        $savedAttachments[] = [
                            'id' => (int) $attachment->id,
                            'filename' => (string) $attachment->filename,
                            'mime_type' => (string) ($attachment->mime_type ?? ''),
                            'file_size' => (int) ($attachment->file_size ?? 0),
                            'url' => rtrim((string) config('app.url'), '/') . '/storage/' . $path,
                        ];
                    }
                }

                $ticketUpdates = [];
                $ticketUpdates['last_customer_reply_at'] = now();

                $normalized = strtolower(str_replace('_', ' ', $status));
                if (in_array($normalized, ['waiting for customer', 'resolved'], true)) {
                    $ticketUpdates['status'] = 'In Progress';
                    $ticketUpdates['previous_status'] = $ticket->status;
                    $ticketUpdates['status_changed_at'] = now();
                }

                if ($ticketUpdates !== []) {
                    $ticket->update($ticketUpdates);
                }
            });

            $ticket->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully.',
                'data' => [
                    'ticket_id' => (int) $ticket->id,
                    'ticket_no' => (string) ($ticket->ticket_no ?? ''),
                    'status' => (string) ($ticket->status ?? ''),
                    'activity' => [
                        'id' => (int) $activity->id,
                        'type' => 'reply',
                        'message' => $message,
                        'author' => $authorName,
                        'author_role' => 'partner',
                        'is_staff' => false,
                        'created_at' => $activity->created_at
                            ? $activity->created_at->format('Y-m-d H:i:s')
                            : now()->format('Y-m-d H:i:s'),
                        'attachments' => $savedAttachments,
                    ],
                ],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send message.',
                'error' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    public function reopenTicket(Request $request): JsonResponse
    {
        $request->validate([
            'partner_id' => ['required', 'integer'],
            'ticket_id' => ['required', 'integer'],
            'partner_user_id' => ['nullable', 'integer'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $partnerId = (int) $request->partner_id;
        $partnerUserId = $this->resolvePartnerUserIdForTickets($request, $partnerId);
        if ($partnerUserId === false) {
            return response()->json([
                'success' => false,
                'message' => 'Partner user not found for this partner.',
                'data' => null,
            ], 422);
        }

        $ticket = SupportTicket::query()
            ->forPartnerActor($partnerId, $partnerUserId)
            ->where('id', (int) $request->ticket_id)
            ->first();

        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Ticket not found.', 'data' => null], 404);
        }

        $status = strtolower(trim((string) $ticket->status));
        if (!in_array($status, ['closed', 'resolved'], true)) {
            return response()->json(['success' => false, 'message' => 'Only closed or resolved tickets can be reopened.', 'data' => null], 422);
        }

        $closedAt = $ticket->closed_at ?? $ticket->resolved_at ?? $ticket->status_changed_at ?? $ticket->updated_at;
        if (!$closedAt || now()->greaterThan(\Carbon\Carbon::parse($closedAt)->addHours(72))) {
            return response()->json(['success' => false, 'message' => 'Reopen window (72 hours) expired. Please create a new ticket.', 'data' => null], 422);
        }

        $reopenCount = SupportActivity::where('ticket_id', $ticket->id)->where('type', 'reopened')->count();
        if ($reopenCount >= 2) {
            return response()->json(['success' => false, 'message' => 'Ticket already reopened 2 times. Please create a new ticket.', 'data' => null], 422);
        }

        $partner = Partner::find($partnerId);
        $name = trim((string) ($partner->name ?? $partner->company_name ?? 'Partner')) ?: 'Partner';
        $reason = trim((string) $request->reason);
        $oldStatus = $ticket->status;

        $ticket->update([
            'previous_status' => $oldStatus,
            'status' => 'Reopened',
            'reopened_at' => now(),
            'status_changed_at' => now(),
        ]);

        SupportActivity::create([
            'ticket_id' => $ticket->id,
            'user_id' => $partnerId,
            'user_type' => 'partner',
            'message' => 'Ticket reopened by Partner. Reason: ' . $reason,
            'type' => 'reopened',
            'author_name' => $name,
            'author_role' => 'partner',
            'old_status' => $oldStatus,
            'new_status' => 'Reopened',
            'is_internal' => false,
        ]);

        try {
            $ticket->refresh();
            $ticket->autoAssignOnReopen();
            $ticket->refresh();
        } catch (\Throwable $e) {
            \Log::error('Reopen auto-assign failed: ' . $e->getMessage(), ['ticket_id' => $ticket->id]);
        }

        $assignMeta = SupportTicket::assignedAgentPayload(
            $ticket->assigned_to ? (int) $ticket->assigned_to : null
        );

        return response()->json([
            'success' => true,
            'message' => 'Ticket reopened successfully.',
            'data' => [
                'ticket_id' => (int) $ticket->id,
                'ticket_no' => (string) ($ticket->ticket_no ?? ''),
                'status' => (string) $ticket->status,
                'reopen_count' => $reopenCount + 1,
                'admin_notify' => 'Ticket #' . ($ticket->ticket_no ?? $ticket->id)
                    . ' has been reopened by ' . $name . '. Reason: \'' . $reason . '\'.',
            ] + $assignMeta,
        ]);
    }

    public function closeTicket(Request $request): JsonResponse
    {
        $request->validate([
            'partner_id' => ['required', 'integer'],
            'ticket_id' => ['required', 'integer'],
            'partner_user_id' => ['nullable', 'integer'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $partnerId = (int) $request->partner_id;
        $partnerUserId = $this->resolvePartnerUserIdForTickets($request, $partnerId);
        if ($partnerUserId === false) {
            return response()->json([
                'success' => false,
                'message' => 'Partner user not found for this partner.',
                'data' => null,
            ], 422);
        }

        $ticket = SupportTicket::query()
            ->forPartnerActor($partnerId, $partnerUserId)
            ->where('id', (int) $request->ticket_id)
            ->first();

        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Ticket not found.', 'data' => null], 404);
        }

        $status = strtolower(trim((string) $ticket->status));
        if (in_array($status, ['closed', 'resolved'], true)) {
            return response()->json(['success' => false, 'message' => 'Ticket is already closed or resolved.', 'data' => null], 422);
        }

        $partner = Partner::find($partnerId);
        $name = trim((string) ($partner->name ?? $partner->company_name ?? 'Partner')) ?: 'Partner';
        $reason = trim((string) $request->reason);
        $oldStatus = $ticket->status;

        $ticket->update([
            'previous_status' => $oldStatus,
            'status' => 'Closed',
            'closed_at' => now(),
            'status_changed_at' => now(),
        ]);

        SupportActivity::create([
            'ticket_id' => $ticket->id,
            'user_id' => $partnerId,
            'user_type' => 'partner',
            'message' => 'Ticket closed by Partner.' . ($reason ? ' Reason: ' . $reason : ''),
            'type' => 'closed',
            'author_name' => $name,
            'author_role' => 'partner',
            'old_status' => $oldStatus,
            'new_status' => 'Closed',
            'is_internal' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ticket closed successfully.',
            'data' => [
                'ticket_id' => (int) $ticket->id,
                'ticket_no' => (string) ($ticket->ticket_no ?? ''),
                'status' => 'Closed'
            ],
        ], 200);
    }

    /**
     * @return int|null|false null = owner (all), int = filter, false = invalid
     */
    private function resolvePartnerUserIdForTickets(Request $request, int $partnerId)
    {
        if (!$request->filled('partner_user_id')) {
            return null;
        }

        $partnerUserId = (int) $request->partner_user_id;
        if ($partnerUserId <= 0) {
            return null;
        }

        $exists = PartnerUser::query()
            ->where('partner_id', $partnerId)
            ->whereKey($partnerUserId)
            ->exists();

        return $exists ? $partnerUserId : false;
    }
}
