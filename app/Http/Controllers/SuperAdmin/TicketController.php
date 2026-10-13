<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AdminUserCreation;
use App\Models\SupportActivity;
use App\Models\SupportAttachment;
use App\Models\SupportTicket;
use App\Services\SupportAgentPresenceService;
use App\Services\SupportTicketIdService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;

class TicketController extends Controller
{
    public function __construct(
        private SupportTicketIdService $ticketIdService,
        private SupportAgentPresenceService $agentPresence
    ) {}

    /**
     * Dashboard listing with ticket summary stats (open, closed, pending tickets and more)
     */
    public function index(Request $request): View
    {
        $filter = strtolower(trim((string) $request->query('filter', '')));
        $isUnassigned = $filter === 'unassigned';
        $sessionUserId = session('userId') ? (int) session('userId') : 0;
        $isAgent = (int) session('roleId', 0) === (int) config('services.support.agent_role_id', 3);

        $ticketsQuery = SupportTicket::query()->orderByDesc('created_at')->orderByDesc('id');
        if ($isUnassigned) {
            // Assign Ticket menu: only tickets waiting for assignment
            $ticketsQuery->where(function ($q) {
                $q->whereNull('assigned_to')
                    ->orWhere('assigned_to', 0)
                    ->orWhere('status', SupportTicket::STATUS_PENDING_ASSIGNMENT);
            });
        } elseif ($isAgent && $sessionUserId > 0) {
            // Ticket List: support agents see only their own tickets, everyone else sees all
            $ticketsQuery->where('assigned_to', $sessionUserId);
        }
        $tickets = $ticketsQuery->get();
        $agentNames = AdminUserCreation::query()
            ->whereIn('id', $tickets->pluck('assigned_to')->filter()->unique()->values())
            ->pluck('user_name', 'id');

        // Detect statuses dynamically: show open, closed, pending tickets in cards/stats
        // Consider all distinct statuses for summary cards, but always show "Open", "Closed", and "Pending" if present

        // Get all statuses (dedupe/lowercase for lookup)
        $allStatuses = SupportTicket::select('status')->distinct()->pluck('status')->toArray();
        $statusesForCards = [];

        // Always show these if present, then add others
        $main = ['open', 'closed', 'pending'];
        foreach ($main as $s) {
            foreach ($allStatuses as $status) {
                if (strtolower($status) === $s && !in_array($status, $statusesForCards, true)) {
                    $statusesForCards[] = $status;
                }
            }
        }

        // Add any additional statuses not already included
        foreach ($allStatuses as $status) {
            if (!in_array($status, $statusesForCards, true)) {
                $statusesForCards[] = $status;
            }
        }

        // Stats calculation timeframes
        $now = now();
        $lastWeek = $now->copy()->subWeek();

        // Count for each status NOW
        $statusCounts = SupportTicket::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Count for each status up to last week (trend base)
        $statusCountsLastWeek = SupportTicket::selectRaw('status, COUNT(*) as count')
            ->where('created_at', '<', $lastWeek)
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Average Response Time (hours)
        $averageResponseTime = 0;
        $resolvedTicketsWithReplies = SupportTicket::where('status', 'resolved')
            ->with(['activities' => function ($q) {
                $q->where('type', 'reply')->orderBy('created_at', 'asc');
            }])->get();

        $responseTimes = [];
        foreach ($resolvedTicketsWithReplies as $ticket) {
            if ($ticket->activities->count() > 0) {
                $firstReply = $ticket->activities->first();
                $created = $ticket->created_at;
                $firstReplyAt = $firstReply->created_at;
                $diffHours = $created->diffInMinutes($firstReplyAt) / 60;
                $responseTimes[] = $diffHours;
            }
        }
        if (!empty($responseTimes)) {
            $averageResponseTime = round(array_sum($responseTimes) / count($responseTimes), 1);
        }

        $countByStatus = static function (string $needle) use ($statusCounts): int {
            $total = 0;
            foreach ($statusCounts as $status => $count) {
                if (strtolower(trim((string) $status)) === $needle) {
                    $total += (int) $count;
                }
            }
            return $total;
        };

        $lastWeekByStatus = static function (string $needle) use ($statusCountsLastWeek): int {
            $total = 0;
            foreach ($statusCountsLastWeek as $status => $count) {
                if (strtolower(trim((string) $status)) === $needle) {
                    $total += (int) $count;
                }
            }
            return $total;
        };

        $trendOf = static function (int $current, int $previous): float {
            if ($previous <= 0) {
                return $current > 0 ? 100.0 : 0.0;
            }
            return round((($current - $previous) / $previous) * 100, 1);
        };

        $openCount = $countByStatus('open');
        $resolvedCount = $countByStatus('resolved') + $countByStatus('closed');
        $pendingCount = $countByStatus('pending') + $countByStatus('in_progress');
        $escalatedCount = $countByStatus('escalated');

        $stats = [
            [
                'label' => 'Open Tickets',
                'value' => $openCount,
                'trend' => $trendOf($openCount, $lastWeekByStatus('open')),
                'icon' => 'ti ti-ticket',
            ],
            [
                'label' => 'Resolved Tickets',
                'value' => $resolvedCount,
                'trend' => $trendOf(
                    $resolvedCount,
                    $lastWeekByStatus('resolved') + $lastWeekByStatus('closed')
                ),
                'icon' => 'ti ti-circle-check',
            ],
            [
                'label' => 'Pending Tickets',
                'value' => $pendingCount,
                'trend' => $trendOf(
                    $pendingCount,
                    $lastWeekByStatus('pending') + $lastWeekByStatus('in_progress')
                ),
                'icon' => 'ti ti-hourglass',
            ],
            [
                'label' => 'Escalated Tickets',
                'value' => $escalatedCount,
                'trend' => $trendOf($escalatedCount, $lastWeekByStatus('escalated')),
                'icon' => 'ti ti-alert-circle',
            ],
            [
                'label' => 'Average Response Time',
                'value' => $averageResponseTime,
                'suffix' => 'hrs',
                'trend' => 0,
                'icon' => 'ti ti-clock',
            ],
        ];

        $assignmentToast = null;
        if ($sessionUserId > 0) {
            $assignmentToast = Cache::pull('ticket_assigned_toast_' . $sessionUserId);
        }

        return view('superadmin.support.index', compact('tickets', 'stats', 'assignmentToast', 'isUnassigned', 'agentNames'));
    }

    public function create(): View
    {
        $isEdit = false;
        $ticket = null;
        $partners = $this->getPartnerOptions();
        $workflowStatuses = $this->workflowStatuses();
        $currentStatus = 'Open';

        if ($partners->isEmpty()) {
            $partners = collect([
                (object) ['id' => 1, 'name' => 'Test Partner', 'email' => 'test@example.com']
            ]);
        }

        return view('superadmin.support.create', compact(
            'isEdit',
            'ticket',
            'partners',
            'workflowStatuses',
            'currentStatus'
        ));
    }

    public function details(int $id): View
    {
        $with = [
            'activities' => fn ($q) => $q->orderBy('id'),
        ];
        $hasAttachments = Schema::connection('pgsql_support')->hasTable('ticket_attachments');
        if ($hasAttachments) {
            $with[] = 'attachments';
        }

        $ticket = SupportTicket::with($with)->findOrFail($id);
        if (!$hasAttachments) {
            $ticket->setRelation('attachments', collect());
        }

        // Normalize legacy statuses for display/flow
        $currentStatus = $this->normalizeStatus((string) ($ticket->status ?? 'Open'));
        $allowedNextStatuses = $this->allowedNextStatuses($currentStatus);

        $partner = null;
        $partnerName = 'User';
        if (!empty($ticket->partner_id) && Schema::connection('pgsql_auth')->hasTable('partners')) {
            try {
                $partnerQuery = DB::connection('pgsql_auth')->table('partners')->where('id', $ticket->partner_id);
                $partnerCols = ['id', 'name', 'email'];
                if (Schema::connection('pgsql_auth')->hasColumn('partners', 'company_name')) {
                    $partnerCols[] = 'company_name';
                }
                $partner = $partnerQuery->select($partnerCols)->first();
                $partnerName = $partner ? ($partner->name ?: ($partner->company_name ?: 'User')) : 'User';
            } catch (\Throwable $e) {
                \Log::warning('Ticket details partner lookup failed: ' . $e->getMessage());
            }
        }

        // Inject Welcome Message into activities so it appears in Superadmin conversation
        if (isset($ticket->activities) && $ticket->activities->count() > 0) {
            $welcomeMessageText = "{$partnerName},\n\n"
                . "Thank you for contacting our support team. A support ticket has now been opened for your request. You will be notified when a response is made by email. The details of your ticket are shown below.\n\n"
                . "Ticket ID: #{$ticket->ticket_no}\n"
                . "Subject: {$ticket->subject}\n"
                . "Status: {$ticket->status}\n\n"
                . "Thanks & Regards,\n"
                . "Support Team";

            $welcomeMsg = new SupportActivity([
                'id' => 0,
                'ticket_id' => $ticket->id,
                'user_id' => null,
                'user_type' => 'system',
                'type' => 'reply',
                'message' => $welcomeMessageText,
                'author_name' => 'System',
                'author_role' => 'system',
                'is_internal' => false,
            ]);
            
            // Set timestamps manually to avoid mass assignment issues on dummy models
            $welcomeMsg->created_at = $ticket->created_at;
            $welcomeMsg->updated_at = $ticket->created_at;

            // Insert after the first activity (which is usually the ticket creation description)
            $ticket->activities->splice(1, 0, [$welcomeMsg]);
        }

        $customer = null;
        if (!empty($ticket->customer_id) && Schema::connection('pgsql_billing')->hasTable('customers')) {
            try {
                $customerCols = ['id', 'name', 'email'];
                if (Schema::connection('pgsql_billing')->hasColumn('customers', 'company_name')) {
                    $customerCols[] = 'company_name';
                }
                $customer = DB::connection('pgsql_billing')
                    ->table('customers')
                    ->select($customerCols)
                    ->where('id', $ticket->customer_id)
                    ->first();
            } catch (\Throwable $e) {
                \Log::warning('Ticket details customer lookup failed: ' . $e->getMessage());
            }
        }

        $workflowStatuses = $this->workflowStatuses();

        // Assign dropdown: support agents only (admin_user_creation.role = support role)
        $agents = $this->agentPresence->agents();

        // Presence for agents + current assignee + staff authors shown in the thread
        $assignedAgentId = (int) ($ticket->assigned_to ?? 0);
        $staffAuthorIds = ($ticket->activities ?? collect())
            ->filter(fn ($a) => in_array(strtolower((string) ($a->user_type ?? '')), ['superadmin', 'admin', 'agent'], true))
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $agentPresence = $this->agentPresence->presenceMap(
            array_merge($assignedAgentId > 0 ? [$assignedAgentId] : [], $staffAuthorIds)
        );

        $assignedAgentName = null;
        if ($assignedAgentId > 0) {
            $assignedAgentName = $agentPresence[$assignedAgentId]['name'] ?? ('Agent #' . $assignedAgentId);
        }

        $assignmentToast = null;
        $sessionUserId = session('userId') ? (int) session('userId') : 0;
        if ($sessionUserId > 0) {
            $toastKey = 'ticket_assigned_toast_' . $sessionUserId;
            $assignmentToast = Cache::pull($toastKey);
        }

        $canManageAllAgents = strtolower(trim((string) session('userRole', ''))) === 'super_admin';
        $presenceColumnsReady = $this->agentPresence->hasPresenceColumns();

        return view('superadmin.support.details', compact(
            'ticket',
            'partner',
            'customer',
            'currentStatus',
            'allowedNextStatuses',
            'workflowStatuses',
            'agents',
            'assignedAgentName',
            'assignmentToast',
            'agentPresence',
            'sessionUserId',
            'canManageAllAgents',
            'presenceColumnsReady'
        ));
    }

    public function assignAgent(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $request->validate([
            'assigned_to' => ['required', 'integer'],
        ]);

        $ticket = SupportTicket::findOrFail($id);
        $newAgentId = (int) $request->input('assigned_to');
        // Manual assignment is limited to support agents (Inactive agents are still allowed as an admin override).
        $agent = AdminUserCreation::query()->supportAgents()->whereKey($newAgentId)->first();
        if (!$agent) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Selected agent is not available.'], 422);
            }
            return redirect()
                ->route('superadmin.support.details', $ticket->id)
                ->with('error', 'Selected agent is not available.');
        }

        $adminId = session('userId') ? (int) session('userId') : null;
        $adminName = (string) (session('userName') ?: 'Super Admin');

        try {
            $names = $ticket->manualAssign($newAgentId, $adminId, $adminName);
        } catch (\Throwable $e) {
            \Log::error('Manual assign failed: ' . $e->getMessage(), ['ticket_id' => $ticket->id]);
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Assignment failed: ' . $e->getMessage()], 500);
            }
            return redirect()
                ->route('superadmin.support.details', $ticket->id)
                ->with('error', 'Assignment failed: ' . $e->getMessage());
        }

        $msg = "Ticket reassigned from {$names['old_name']} to {$names['new_name']} by {$adminName}.";
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'assigned_to' => $newAgentId,
                'assigned_agent_name' => $names['new_name'],
            ]);
        }

        return redirect()
            ->route('superadmin.support.details', $ticket->id)
            ->with('success', $msg);
    }

    public function save(Request $request, $id = null)
    {
        $isUpdate = !empty($id);

        if ($request->filled('status') && !$request->filled('tickit_status')) {
            $request->merge(['tickit_status' => $request->input('status')]);
        }

        $rules = [
            'requester'     => 'required|string|max:255',
            'email'         => 'required|email|max:255',
            'subject'       => 'required|string|max:255',
            'description'   => $isUpdate ? 'nullable|string|max:5000' : 'required|string|max:5000',
            'priority'      => 'required|string',
            'tickit_status' => 'required|string',
            'tags'          => 'nullable|string',
        ];

        if (!$isUpdate) {
            $rules['partner_id'] = 'required|integer|exists:pgsql_auth.partners,id';
            $rules['attachments'] = 'nullable|array|max:5';
            $rules['attachments.*'] = 'file|max:5120|mimes:jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,txt,zip';
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'messages' => $validator->errors()->toArray(),
            ]);
        }

        $data = $validator->validated();

        try {
            if ($isUpdate) {
                $ticket = SupportTicket::findOrFail($id);
                $oldStatus = $ticket->status;

                $ticket->update([
                    'requester' => $data['requester'],
                    'email'     => $data['email'],
                    'subject'   => $data['subject'],
                    'priority'  => $data['priority'],
                    'status'    => $this->normalizeStatus($data['tickit_status']),
                    'tags'      => $data['tags'] ?? null,
                ]);

                $createdActivity = $ticket->activities()->where('type', 'created')->first();
                if ($createdActivity && !empty($data['description'])) {
                    $createdActivity->update(['message' => $data['description']]);
                }

                $normalizedNew = $this->normalizeStatus($data['tickit_status']);
                $normalizedOld = $this->normalizeStatus((string) $oldStatus);

                SupportActivity::create([
                    'ticket_id' => $ticket->id,
                    'user_id'   => auth()->id(),
                    'user_type' => 'superadmin',
                    'message'   => 'Ticket details updated by admin. Status changed from ' . $normalizedOld . ' to ' . $normalizedNew,
                    'type'      => 'system',
                ]);

                if ($normalizedOld !== $normalizedNew) {
                    try {
                        $updatedBy = auth()->user()->name ?? 'Super Admin';
                        (new \App\Services\TicketMailService())->sendTicketStatusUpdateNotification(
                            $ticket,
                            $normalizedOld,
                            $normalizedNew,
                            $updatedBy
                        );
                    } catch (\Exception $e) {
                        \Log::error('Status update notification error: ' . $e->getMessage());
                    }
                }

                return response()->json([
                    'success' => 'Ticket updated successfully.',
                    'redirect' => route('superadmin.support.details', $ticket->id),
                ]);
            }

            $ticket = DB::transaction(function () use ($data, $request) {
                $ticket = SupportTicket::create([
                    'ticket_no'  => $this->ticketIdService->nextTicketNo(),
                    'subject'    => $data['subject'],
                    'status'     => $this->normalizeStatus($data['tickit_status']),
                    'priority'   => $data['priority'],
                    'requester'  => $data['requester'],
                    'email'      => $data['email'],
                    'tags'       => $data['tags'] ?? null,
                    'partner_id' => (int) $data['partner_id'],
                ]);

                $activity = SupportActivity::create([
                    'ticket_id' => $ticket->id,
                    'user_id'   => auth()->id(),
                    'user_type' => 'superadmin',
                    'message'   => $data['description'],
                    'type'      => 'created',
                ]);

                if ($request->hasFile('attachments') && Schema::connection('pgsql_support')->hasTable('ticket_attachments')) {
                    foreach ($request->file('attachments') as $file) {
                        if (!$file->isValid()) {
                            continue;
                        }
                        $storedPath = $file->store('support-tickets/' . $ticket->id, 'public');
                        SupportAttachment::create([
                            'ticket_id'   => $ticket->id,
                            'activity_id' => $activity->id,
                            'file_path'   => $storedPath,
                            'filename'    => $file->getClientOriginalName(),
                            'mime_type'   => $file->getClientMimeType(),
                            'file_size'   => $file->getSize(),
                            'uploaded_by' => auth()->id(),
                        ]);
                    }
                }

                return $ticket;
            });

            try {
                $ticket->autoAssignOnCreate();
            } catch (\Throwable $e) {
                \Log::error('Admin ticket auto-assign failed: ' . $e->getMessage(), ['ticket_id' => $ticket->id]);
            }

            try {
                (new \App\Services\TicketMailService())->sendTicketCreatedNotifications($ticket);
            } catch (\Exception $e) {
                \Log::error('Ticket notification error: ' . $e->getMessage());
            }

            return response()->json([
                'success' => 'Ticket created successfully.',
                'redirect' => route('superadmin.support.details', $ticket->id),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Failed to save ticket: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function addActivity(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $ticket = SupportTicket::findOrFail($id);
        $currentStatus = $this->normalizeStatus((string) ($ticket->status ?? 'Open'));

        $request->validate([
            'message' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'string'],
            'is_internal' => ['nullable', 'boolean'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:5120', 'extensions:jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,txt,zip'],
        ]);

        $message = trim((string) $request->input('message', ''));
        $requestedStatus = trim((string) $request->input('status', ''));
        $isInternal = $request->boolean('is_internal');
        $hasFiles = $request->hasFile('attachments');

        if ($message === '' && $requestedStatus === '' && !$hasFiles) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Please enter a reply, change status, or attach a file.'], 422);
            }
            return redirect()
                ->route('superadmin.support.details', $ticket->id)
                ->with('error', 'Please enter a reply, change status, or attach a file.');
        }

        // Separate concerns: reply vs status change
        $replyCreated = false;
        $statusChanged = false;
        $oldStatus = $currentStatus;
        $newStatus = $currentStatus;
        $createdActivity = null;

        try {
            DB::connection('pgsql_support')->transaction(function () use (
                $request,
                $ticket,
                $message,
                $requestedStatus,
                $isInternal,
                $hasFiles,
                $currentStatus,
                &$replyCreated,
                &$statusChanged,
                &$newStatus,
                &$createdActivity
            ) {
                $activityId = null;

                // 1) Public / internal reply (does not force status by itself)
                if ($message !== '' || $hasFiles) {
                    // Super Admin panel uses session login, so auth() is usually empty.
                    $staffUserId = auth()->id() ?? (session('userId') ? (int) session('userId') : null);
                    $activityPayload = [
                        'ticket_id' => $ticket->id,
                        'user_id' => $staffUserId,
                        'user_type' => 'superadmin',
                        'message' => $message !== '' ? $message : ($isInternal ? 'Internal note (attachments)' : 'Reply (attachments)'),
                        'type' => $isInternal ? 'internal_note' : 'reply',
                    ];

                    if (Schema::connection('pgsql_support')->hasColumn('support_activities', 'is_internal')) {
                        $activityPayload['is_internal'] = $isInternal;
                    }
                    if (Schema::connection('pgsql_support')->hasColumn('support_activities', 'author_name')) {
                        $activityPayload['author_name'] = auth()->user()->name ?? (session('userName') ?: 'Super Admin');
                    }
                    if (Schema::connection('pgsql_support')->hasColumn('support_activities', 'author_role')) {
                        $activityPayload['author_role'] = 'admin';
                    }
                    if (Schema::connection('pgsql_support')->hasColumn('support_activities', 'notify_customer')) {
                        $activityPayload['notify_customer'] = !$isInternal;
                    }

                    $activity = SupportActivity::create($activityPayload);
                    $activityId = (int) $activity->id;
                    $createdActivity = $activity;
                    $replyCreated = true;

                    if ($hasFiles && Schema::connection('pgsql_support')->hasTable('ticket_attachments')) {
                        foreach ($request->file('attachments') as $file) {
                            if (!$file->isValid()) {
                                continue;
                            }
                            $storedPath = $file->store('support-tickets/' . $ticket->id, 'public');
                            $attachPayload = [
                                'ticket_id' => $ticket->id,
                                'activity_id' => $activityId,
                                'file_path' => $storedPath,
                                'filename' => $file->getClientOriginalName(),
                                'mime_type' => $file->getClientMimeType(),
                                'file_size' => $file->getSize(),
                                'uploaded_by' => auth()->id(),
                            ];
                            if (Schema::connection('pgsql_support')->hasColumn('ticket_attachments', 'is_internal')) {
                                $attachPayload['is_internal'] = $isInternal;
                            }
                            if (Schema::connection('pgsql_support')->hasColumn('ticket_attachments', 'uploaded_by_type')) {
                                $attachPayload['uploaded_by_type'] = 'admin';
                            }
                            SupportAttachment::create($attachPayload);
                        }
                    }

                    // Auto flow: public admin reply while In Progress → Waiting for Customer
                    if (
                        !$isInternal
                        && $message !== ''
                        && $requestedStatus === ''
                        && $currentStatus === 'In Progress'
                    ) {
                        $this->applyStatusChange($ticket, $currentStatus, 'Waiting for Customer', false);
                        $statusChanged = true;
                        $newStatus = 'Waiting for Customer';
                    }

                    if (
                        Schema::connection('pgsql_support')->hasColumn('support_tickets', 'last_agent_reply_at')
                        && !$isInternal
                    ) {
                        $ticket->last_agent_reply_at = now();
                        $ticket->save();
                    }

                    if (!$isInternal && ($message !== '' || $hasFiles)) {
                        $ticket->markFirstResponseIfNeeded();
                    }
                }

                // 2) Explicit status change (separate from reply content)
                if ($requestedStatus !== '') {
                    $target = $this->normalizeStatus($requestedStatus);
                    $fromForStatus = $statusChanged ? $newStatus : $currentStatus;
                    if (!$this->canTransition($fromForStatus, $target)) {
                        throw new \InvalidArgumentException(
                            "Invalid status change: {$fromForStatus} → {$target}"
                        );
                    }
                    $this->applyStatusChange($ticket, $fromForStatus, $target, true);
                    $statusChanged = true;
                    $newStatus = $target;
                }
            });
        } catch (\InvalidArgumentException $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()
                ->route('superadmin.support.details', $ticket->id)
                ->with('error', $e->getMessage());
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Log::error('Ticket addActivity failed: ' . $e->getMessage(), [
                'ticket_id' => $ticket->id,
            ]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to save activity: ' . $e->getMessage()], 500);
            }
            return redirect()
                ->route('superadmin.support.details', $ticket->id)
                ->with('error', 'Failed to save activity: ' . $e->getMessage());
        }

        $flash = [];
        if ($replyCreated && $statusChanged) {
            $flash['success'] = "Reply saved. Status updated: {$oldStatus} → {$newStatus}.";
        } elseif ($replyCreated) {
            $flash['success'] = $isInternal ? 'Internal note added.' : 'Reply added successfully.';
        } elseif ($statusChanged) {
            $flash['success'] = "Status updated: {$oldStatus} → {$newStatus}.";
        }

        if ($replyCreated && !$isInternal && $createdActivity) {
            try {
                (new \App\Services\TicketMailService())->sendTicketReplyNotification(
                    $ticket->fresh(),
                    (string) $createdActivity->message,
                    auth()->user()->name ?? 'Support Team'
                );
            } catch (\Throwable $e) {
                \Log::error('Ticket reply notify failed: ' . $e->getMessage());
            }
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $flash['success'] ?? 'Saved.',
                'status' => $newStatus,
                'message_row' => $createdActivity ? $this->mapConversationRow($createdActivity) : null,
            ]);
        }

        return redirect()
            ->route('superadmin.support.details', $ticket->id)
            ->with($flash);
    }

    public function conversation(Request $request, int $id): JsonResponse
    {
        $afterId = (int) $request->query('after_id', 0);

        $rows = SupportActivity::query()
            ->where('ticket_id', $id)
            ->whereIn('type', ['created', 'reply', 'comment', 'internal_note'])
            ->when($afterId > 0, fn ($q) => $q->where('id', '>', $afterId))
            ->orderBy('id')
            ->get()
            ->map(fn (SupportActivity $a) => $this->mapConversationRow($a))
            ->values();

        return response()->json(['success' => true, 'messages' => $rows]);
    }

    private function mapConversationRow(SupportActivity $a): array
    {
        $userType = strtolower((string) ($a->user_type ?? ''));
        $isStaff = in_array($userType, ['superadmin', 'admin', 'agent'], true);
        $isInternal = !empty($a->is_internal) || ($a->type ?? '') === 'internal_note';
        $author = (string) ($a->author_name ?: ($isStaff ? 'Support Team' : ($userType === 'system' ? 'System' : 'Partner')));

        $attachments = [];
        if (Schema::connection('pgsql_support')->hasTable('ticket_attachments')) {
            $attachments = SupportAttachment::query()
                ->where('activity_id', $a->id)
                ->get()
                ->map(fn (SupportAttachment $f) => [
                    'id' => (int) $f->id,
                    'filename' => (string) ($f->filename ?? 'File'),
                    'url' => route('superadmin.support.attachment.download', $f->id),
                ])
                ->values()
                ->all();
        }

        return [
            'id' => (int) $a->id,
            'message' => (string) ($a->message ?? ''),
            'author' => $author,
            'author_id' => $isStaff && !empty($a->user_id) ? (int) $a->user_id : null,
            'role' => $isStaff ? 'staff' : ($userType === 'system' ? 'system' : 'partner'),
            'is_staff' => $isStaff,
            'is_internal' => $isInternal,
            'time' => $a->created_at ? $a->created_at->format('d M, h:i A') : '',
            'attachments' => $attachments,
        ];
    }

    public function edit(int $id): View
    {
        $isEdit = true;
        $ticket = SupportTicket::with('activities')->findOrFail($id);
        $partners = $this->getPartnerOptions();
        $workflowStatuses = $this->workflowStatuses();
        $currentStatus = $this->normalizeStatus((string) ($ticket->status ?? 'Open'));

        $partnerName = null;
        if (!empty($ticket->partner_id) && Schema::connection('pgsql_auth')->hasTable('partners')) {
            $partnerName = DB::connection('pgsql_auth')
                ->table('partners')
                ->where('id', $ticket->partner_id)
                ->value('name');
        }

        return view('superadmin.support.create', compact(
            'isEdit',
            'ticket',
            'partners',
            'workflowStatuses',
            'currentStatus',
            'partnerName'
        ));
    }

    public function close(int $id): RedirectResponse
    {
        $ticket = SupportTicket::findOrFail($id);
        $oldStatus = $this->normalizeStatus((string) ($ticket->status ?? 'Open'));

        // Flow: prefer Resolved → Closed; allow Closed from Reopened/In Progress only via resolve first
        if ($oldStatus === 'Closed') {
            return redirect()
                ->route('superadmin.support.details', $ticket->id)
                ->with('error', 'Ticket is already closed.');
        }

        if ($oldStatus !== 'Resolved' && !$this->canTransition($oldStatus, 'Closed')) {
            // Force path: move to Resolved then Closed is not automatic — require Resolved first
            if (!$this->canTransition($oldStatus, 'Resolved')) {
                return redirect()
                    ->route('superadmin.support.details', $ticket->id)
                    ->with('error', "Cannot close from status \"{$oldStatus}\". Move to Resolved first.");
            }
            $this->applyStatusChange($ticket, $oldStatus, 'Resolved', false);
            $oldStatus = 'Resolved';
            $ticket->refresh();
        }

        $this->applyStatusChange($ticket, $oldStatus, 'Closed', true);

        return redirect()
            ->route('superadmin.support.details', $ticket->id)
            ->with('success', 'Ticket closed successfully.');
    }

    /**
     * Canonical workflow statuses.
     *
     * @return list<string>
     */
    private function workflowStatuses(): array
    {
        return [
            'Open',
            'In Progress',
            'Waiting for Customer',
            'Resolved',
            'Closed',
            'Reopened',
            'Pending Assignment',
            'Escalated',
        ];
    }

    private function normalizeStatus(string $status): string
    {
        $s = strtolower(trim($status));
        $map = [
            'open' => 'Open',
            'pending' => 'Open',
            'pending assignment' => 'Pending Assignment',
            'pending_assignment' => 'Pending Assignment',
            'in_progress' => 'In Progress',
            'in progress' => 'In Progress',
            'waiting for customer' => 'Waiting for Customer',
            'waiting_for_customer' => 'Waiting for Customer',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
            'reopened' => 'Reopened',
            'escalated' => 'Escalated',
        ];

        return $map[$s] ?? (in_array($status, $this->workflowStatuses(), true) ? $status : 'Open');
    }

    /**
     * @return list<string>
     */
    private function allowedNextStatuses(string $from): array
    {
        $from = $this->normalizeStatus($from);
        $map = [
            'Open' => ['In Progress', 'Closed'],
            'In Progress' => ['Waiting for Customer', 'Resolved', 'Closed'],
            'Waiting for Customer' => ['In Progress', 'Resolved'],
            'Resolved' => ['Closed', 'Reopened'],
            'Closed' => ['Reopened'],
            'Reopened' => ['In Progress', 'Waiting for Customer', 'Resolved'],
            'Pending Assignment' => ['Open', 'In Progress', 'Closed'],
            'Escalated' => ['In Progress', 'Waiting for Customer', 'Resolved', 'Closed'],
        ];

        return $map[$from] ?? [];
    }

    private function canTransition(string $from, string $to): bool
    {
        $from = $this->normalizeStatus($from);
        $to = $this->normalizeStatus($to);
        if ($from === $to) {
            return false;
        }

        return in_array($to, $this->allowedNextStatuses($from), true);
    }

    private function applyStatusChange(
        SupportTicket $ticket,
        string $from,
        string $to,
        bool $notifyCustomer = false
    ): void {
        $from = $this->normalizeStatus($from);
        $to = $this->normalizeStatus($to);
        if ($from === $to) {
            return;
        }

        $payload = [
            'status' => $to,
        ];
        if (Schema::connection('pgsql_support')->hasColumn('support_tickets', 'previous_status')) {
            $payload['previous_status'] = $from;
        }
        if (Schema::connection('pgsql_support')->hasColumn('support_tickets', 'status_changed_at')) {
            $payload['status_changed_at'] = now();
        }
        if ($to === 'Resolved' && Schema::connection('pgsql_support')->hasColumn('support_tickets', 'resolved_at')) {
            $payload['resolved_at'] = now();
        }
        if ($to === 'Closed' && Schema::connection('pgsql_support')->hasColumn('support_tickets', 'closed_at')) {
            $payload['closed_at'] = now();
        }
        if ($to === 'Reopened' && Schema::connection('pgsql_support')->hasColumn('support_tickets', 'reopened_at')) {
            $payload['reopened_at'] = now();
        }
        if ($to === 'Waiting for Customer' && Schema::connection('pgsql_support')->hasColumn('support_tickets', 'waiting_since')) {
            $payload['waiting_since'] = now();
        }

        $ticket->update($payload);

        $activityPayload = [
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'user_type' => 'superadmin',
            'message' => "Status changed from {$from} to {$to}",
            'type' => $to === 'Closed' ? 'closed' : ($to === 'Resolved' ? 'resolved' : ($to === 'Reopened' ? 'reopened' : 'status_change')),
        ];
        if (Schema::connection('pgsql_support')->hasColumn('support_activities', 'old_status')) {
            $activityPayload['old_status'] = $from;
        }
        if (Schema::connection('pgsql_support')->hasColumn('support_activities', 'new_status')) {
            $activityPayload['new_status'] = $to;
        }
        if (Schema::connection('pgsql_support')->hasColumn('support_activities', 'notify_customer')) {
            $activityPayload['notify_customer'] = $notifyCustomer;
        }
        if (Schema::connection('pgsql_support')->hasColumn('support_activities', 'author_name')) {
            $activityPayload['author_name'] = auth()->user()->name ?? 'Super Admin';
        }
        if (Schema::connection('pgsql_support')->hasColumn('support_activities', 'author_role')) {
            $activityPayload['author_role'] = 'admin';
        }

        SupportActivity::create($activityPayload);

        if ($notifyCustomer) {
            try {
                (new \App\Services\TicketMailService())->sendTicketStatusUpdateNotification(
                    $ticket->fresh(),
                    $from,
                    $to,
                    auth()->user()->name ?? 'Super Admin'
                );
            } catch (\Throwable $e) {
                \Log::error('Ticket status notify failed: ' . $e->getMessage());
            }
        }
    }

    private function getPartnerOptions()
    {
        // Check if partners table exists
        if (! Schema::hasTable('partners')) {
            return collect([]);
        }

        // Get partners with approved status
        $partners = DB::table('partners')
            ->select('id', 'name', 'email')
            ->where('status', 'approved')
            ->orderBy('name')
            ->get();

        // If no partners found, return empty collection
        if ($partners->isEmpty()) {
            return collect([]);
        }

        return $partners;
    }
  
    public function downloadAttachment($id)
    {
        try {
            $attachment = SupportAttachment::findOrFail($id);

            if (!Storage::disk('public')->exists($attachment->file_path)) {
                return redirect()->back()->with('error', 'File not found!');
            }

            return Storage::disk('public')->download($attachment->file_path, $attachment->filename);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error downloading file: ' . $e->getMessage());
        }
    }

    /**
     * Delete attachment
     */
    public function deleteAttachment($id)
    {
        try {
            $attachment = SupportAttachment::findOrFail($id);

            if (Storage::disk('public')->exists($attachment->file_path)) {
                Storage::disk('public')->delete($attachment->file_path);
            }

            $attachment->delete();

            return response()->json(['success' => true, 'message' => 'Attachment deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}