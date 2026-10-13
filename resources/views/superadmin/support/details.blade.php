@extends('superadmin.layouts.app')

@push('styles')
<link href="{{ asset('superadmin/css/support-details.css') }}?v={{ filemtime(public_path('superadmin/css/support-details.css')) }}" rel="stylesheet" type="text/css" />
@endpush
@php
    $displayStatus = $currentStatus ?? ($ticket->status ?? 'Open');
    $priorityLabel = ucfirst((string) ($ticket->priority ?? 'Normal'));

    $companyName = $customer?->company_name
        ?: $customer?->name
        ?: $partner?->company_name
        ?: $partner?->name
        ?: ($ticket->requester ?? '-');

    $ticketDescription = '';
    if (!empty($ticket->description) && trim((string) $ticket->description) !== '') {
        $ticketDescription = $ticket->description;
    } elseif (isset($ticket->activities)) {
        $createdActivity = $ticket->activities->firstWhere('type', 'created');
        if ($createdActivity && !empty($createdActivity->message)) {
            $ticketDescription = $createdActivity->message;
        }
    }

    $tagsArr = [];
    if (is_array($ticket->tags ?? null)) {
        $tagsArr = array_values(array_filter(array_map('trim', $ticket->tags)));
    } elseif (is_string($ticket->tags ?? null) && $ticket->tags !== '') {
        $tagsArr = array_values(array_filter(array_map('trim', explode(',', $ticket->tags))));
    }

    $ccList = [];
    if (is_array($ticket->cc_emails ?? null)) {
        $ccList = array_values(array_filter($ticket->cc_emails));
    } elseif (is_string($ticket->cc_emails ?? null) && $ticket->cc_emails !== '') {
        $decoded = json_decode($ticket->cc_emails, true);
        $ccList = is_array($decoded) ? array_values(array_filter($decoded)) : [];
    }

    $allActivities = $ticket->activities ?? collect();
    $conversation = $allActivities->filter(function ($a) {
        $type = strtolower((string) ($a->type ?? ''));
        return in_array($type, ['created', 'reply', 'internal_note', 'comment', 'reopened', 'system'], true);
    })->values();
    $statusActivities = $allActivities->filter(function ($a) {
        $type = strtolower((string) ($a->type ?? ''));
        return in_array($type, ['status_change', 'closed', 'resolved', 'reopened', 'assignment'], true);
    })->values();

    $filesByActivity = ($ticket->attachments ?? collect())->groupBy(fn ($f) => (int) ($f->activity_id ?? 0));

    $statusBadgeClass = match (strtolower($displayStatus)) {
        'open' => 'st-badge-open',
        'in progress' => 'st-badge-progress',
        'waiting for customer' => 'st-badge-waiting',
        'resolved' => 'st-badge-resolved',
        'closed' => 'st-badge-closed',
        'reopened' => 'st-badge-reopened',
        'escalated' => 'st-badge-priority-high',
        'pending assignment' => 'st-badge-pending',
        default => 'st-badge-pending',
    };

    $priorityBadgeClass = match (strtolower($priorityLabel)) {
        'urgent', 'high' => 'st-badge-priority-high',
        'medium' => 'st-badge-priority-med',
        'low', 'normal' => 'st-badge-priority-low',
        default => 'st-badge-priority-med',
    };

    // Agent presence (Active Now / Last active / Inactive)
    $agentPresence = $agentPresence ?? [];
    $assignedAgentId = (int) ($ticket->assigned_to ?? 0);
    $assignedPresence = $assignedAgentId > 0 ? ($agentPresence[$assignedAgentId] ?? null) : null;
    $canManageAllAgents = (bool) ($canManageAllAgents ?? false);
    $sessionUserId = (int) ($sessionUserId ?? 0);
    $presenceColumnsReady = (bool) ($presenceColumnsReady ?? false);

    $reopenCount = $allActivities->where('type', 'reopened')->count();
    $closedAt = $ticket->closed_at ?? $ticket->resolved_at ?? $ticket->status_changed_at ?? $ticket->updated_at;
    $canReopen = in_array($displayStatus, ['Closed', 'Resolved'], true)
        && $reopenCount < 2
        && $closedAt
        && now()->lessThanOrEqualTo(\Carbon\Carbon::parse($closedAt)->addHours(72));
@endphp

@section('content')
<div class="container-fluid px-0 st-details">

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('superadmin.support.index') }}">Tickets</a></li>
                    <li class="breadcrumb-item active">Ticket Details</li>
            </ol>
            </nav>
            <a href="{{ route('superadmin.support.index') }}" class="text-decoration-none small text-muted">
                <i class="ti ti-arrow-left"></i> Back to Tickets
            </a>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if($displayStatus !== 'Closed')
                <form method="POST" action="{{ route('superadmin.support.close', $ticket->id) }}" class="d-inline"
                      onsubmit="return confirm('Close this ticket?')">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-sm btn-outline-danger">Close</button>
                </form>
            @endif
            @if($canReopen)
                <form method="POST" action="{{ route('superadmin.support.activity', $ticket->id) }}" class="d-inline"
                      onsubmit="return confirm('Reopen this ticket?')">
                    @csrf
                    <input type="hidden" name="status" value="Reopened">
                    <button type="submit" class="btn btn-sm btn-outline-warning">Reopen</button>
                </form>
            @endif
            <a href="{{ route('superadmin.support.edit', $ticket->id) }}" class="btn btn-sm btn-primary">
                <i class="ti ti-edit"></i> Edit
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="ti ti-check-circle"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="ti ti-alert-circle"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(!empty($assignmentToast['message']))
        <div class="alert alert-info alert-dismissible fade show" role="alert" id="stAssignToast">
            <i class="ti ti-bell"></i> {{ $assignmentToast['message'] }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">
        {{-- Main --}}
        <div class="col-lg-8">
            <div class="st-card mb-4">
                <div class="st-card-head d-flex flex-wrap align-items-start justify-content-between gap-3">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="st-ticket-no">#{{ $ticket->ticket_no ?? $ticket->id }}</span>
                        <h4 class="st-subject mb-0">{{ $ticket->subject ?? 'No Subject' }}</h4>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <span class="st-pill {{ $priorityBadgeClass }}">{{ $priorityLabel }}</span>
                        <span class="st-pill {{ $statusBadgeClass }}">{{ $displayStatus }}</span>
                    </div>
                    </div>

                <div class="st-info-grid">
                    <div class="st-info-item">
                        <span class="st-info-icon st-icon-purple"><i class="ti ti-building"></i></span>
                        <div>
                            <div class="st-info-label">Company Name</div>
                            <div class="st-info-value">{{ $companyName }}</div>
                        </div>
                                </div>
                    <div class="st-info-item">
                        <span class="st-info-icon st-icon-blue"><i class="ti ti-world"></i></span>
                                <div>
                            <div class="st-info-label">Domain</div>
                            <div class="st-info-value">{{ $ticket->domain ?: '-' }}</div>
                                </div>
                    </div>
                    <div class="st-info-item">
                        <span class="st-info-icon st-icon-indigo"><i class="ti ti-apps"></i></span>
                        <div>
                            <div class="st-info-label">Service</div>
                            <div class="st-info-value">{{ $ticket->service ?: '-' }}</div>
                            </div>
                        </div>
                        
                    <div class="st-info-item">
                        <span class="st-info-icon st-icon-red"><i class="ti ti-flag"></i></span>
                        <div>
                            <div class="st-info-label">Priority</div>
                            <div class="st-info-value">
                                <span class="st-pill {{ $priorityBadgeClass }} st-pill-sm">{{ $priorityLabel }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="st-info-item">
                        <span class="st-info-icon st-icon-amber"><i class="ti ti-clock"></i></span>
                        <div>
                            <div class="st-info-label">Status</div>
                            <div class="st-info-value">
                                <span class="st-pill {{ $statusBadgeClass }} st-pill-sm">{{ $displayStatus }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="st-info-item">
                        <span class="st-info-icon st-icon-teal"><i class="ti ti-calendar"></i></span>
                        <div>
                            <div class="st-info-label">Created On</div>
                            <div class="st-info-value">
                                {{ $ticket->created_at ? $ticket->created_at->format('d M, Y h:i A') : '-' }}
                            </div>
                        </div>
                    </div>

                    <div class="st-info-item">
                        <span class="st-info-icon st-icon-cyan"><i class="ti ti-mail"></i></span>
                        <div>
                            <div class="st-info-label">Emails in Cc</div>
                            <div class="st-info-value">
                                @if(count($ccList))
                                    {{ implode(', ', $ccList) }}
                            @else
                                    —
                            @endif
                            </div>
                        </div>
                    </div>


                    <div class="st-info-item st-info-span-3">
                        <span class="st-info-icon st-icon-sky"><i class="ti ti-tags"></i></span>
                        <div>
                            <div class="st-info-label">Tags</div>
                            <div class="st-info-value d-flex flex-wrap gap-1">
                                @forelse($tagsArr as $tag)
                                    <span class="st-tag">{{ $tag }}</span>
                                @empty
                                    <span class="text-muted">No tags</span>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                @if(($ticket->attachments ?? collect())->count() > 0)
                    <div class="px-4 pb-3">
                        <div class="st-info-label mb-2"><i class="ti ti-paperclip"></i> Attachments</div>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($ticket->attachments as $attachment)
                                            <a href="{{ route('superadmin.support.attachment.download', $attachment->id) }}" 
                                   class="st-attach-chip">
                                    <i class="ti ti-file"></i>
                                    {{ $attachment->filename ?? $attachment->original_name ?? 'File' }}
                                    <small>({{ round(($attachment->file_size ?? 0) / 1024, 1) }} KB)</small>
                                </a>
                            @endforeach
                        </div>
                    </div>
                    @endif
            </div>

            {{-- Activity --}}
            <div class="st-card mb-4">
                <div class="st-section-title">
                    <i class="ti ti-history"></i> Activity
                </div>
                <div class="st-activity">
                    @forelse($statusActivities as $activity)
                        @php
                            $eventType = $activity->type ?? 'status_change';
                            $activityTitle = match ($eventType) {
                                'status_change' => 'Status Changed',
                                'closed' => 'Ticket Closed',
                                'resolved' => 'Ticket Resolved',
                                'reopened' => 'Ticket Reopened',
                                default => 'Status Changed',
                            };
                            $author = $activity->author_name
                                ?? (match (strtolower((string) ($activity->user_type ?? ''))) {
                                    'superadmin', 'admin', 'agent' => 'Support Team',
                                    'partner' => ($partner?->name ?? 'Partner'),
                                    default => ucfirst((string) ($activity->user_type ?? 'System')),
                                });
                            $initial = strtoupper(mb_substr($author, 0, 1, 'UTF-8'));
                            $when = $activity->created_at
                                ? \Carbon\Carbon::parse($activity->created_at)->format('d M, Y h:i A')
                                : '-';
                            $isInternal = !empty($activity->is_internal) || $eventType === 'internal_note';
                            $dotClass = match ($eventType) {
                                'created' => 'st-dot-green',
                                'reply' => 'st-dot-blue',
                                'status_change', 'resolved' => 'st-dot-amber',
                                'closed' => 'st-dot-red',
                                'internal_note' => 'st-dot-purple',
                                default => 'st-dot-gray',
                            };
                        @endphp
                        <div class="st-activity-item">
                            <div class="st-activity-rail">
                                <span class="st-dot {{ $dotClass }}">{{ $initial }}</span>
                            </div>
                            <div class="st-activity-body">
                                <div class="d-flex justify-content-between gap-2 flex-wrap">
                                    <strong>
                                        {{ $activityTitle }}
                                        @if($isInternal)
                                            <span class="st-pill st-badge-internal st-pill-sm">Internal</span>
                                        @endif
                                    </strong>
                                    <small class="text-muted">{{ $when }}</small>
                                </div>
                                @if(!empty($activity->message))
                                    <p class="mb-1 text-muted small">{{ $activity->message }}</p>
                        @endif
                                <small class="text-primary">By {{ $author }}</small>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted mb-0 px-1">No status changes yet.</p>
                    @endforelse
                </div>
            </div>

            {{-- Conversation --}}
            @php $lastConvId = $conversation->isNotEmpty() ? (int) $conversation->last()->id : 0; @endphp
            <div class="st-card" id="stConversation"
                 data-ticket="{{ $ticket->id }}"
                 data-poll="{{ route('superadmin.support.conversation', $ticket->id) }}"
                 data-last="{{ $lastConvId }}">
                <div class="st-section-title d-flex justify-content-between align-items-center">
                    <span><i class="ti ti-messages"></i> Conversation Thread</span>
                    <span class="st-msg-count"><span id="stMsgCount">{{ $conversation->count() }}</span> Messages</span>
                    </div>

                <div class="st-thread" id="stThread">
                    @forelse($conversation as $msg)
                        @php
                            // partner = customer (right), staff = support (left), system = automated (centre)
                            $userType = strtolower((string) ($msg->user_type ?? ''));
                            $msgRole = in_array($userType, ['superadmin', 'admin', 'agent'], true)
                                ? 'staff'
                                : ($userType === 'system' ? 'system' : 'partner');
                            $isInternalMsg = !empty($msg->is_internal) || ($msg->type ?? '') === 'internal_note';
                            $msgAuthor = $msg->author_name ?: match ($msgRole) {
                                'staff' => 'Support Team',
                                'system' => 'System',
                                default => $partner?->name ?? $ticket->requester ?? 'Partner',
                            };
                            $msgWhen = $msg->created_at
                                ? \Carbon\Carbon::parse($msg->created_at)->format('d M, h:i A')
                                : '';
                            $roleLabel = $isInternalMsg ? 'Internal' : ['staff' => 'Support', 'system' => 'System', 'partner' => 'Partner'][$msgRole];
                            $roleIcon = ['staff' => 'ti-headset', 'system' => 'ti-robot', 'partner' => 'ti-user'][$msgRole];
                            $msgAuthorId = $msgRole === 'staff' ? (int) ($msg->user_id ?? 0) : 0;
                            $msgPresence = $msgAuthorId > 0 ? ($agentPresence[$msgAuthorId] ?? null) : null;
                        @endphp
                        <div class="st-msg st-msg-{{ $msgRole }} {{ $isInternalMsg ? 'st-msg-internal' : '' }}" data-id="{{ $msg->id }}">
                            <div class="st-avatar-wrap" @if($msgPresence) data-presence-id="{{ $msgAuthorId }}" @endif>
                                <div class="st-avatar st-avatar-{{ $msgRole }}"><i class="ti {{ $roleIcon }}"></i></div>
                                @if($msgPresence)
                                    <span class="st-presence-dot st-presence-sm st-presence-{{ $msgPresence['state'] }}" title="{{ $msgPresence['label'] }}"></span>
                                @endif
                            </div>
                            <div class="st-msg-content">
                                <div class="st-msg-meta">
                                    <strong>{{ $msgAuthor }}</strong>
                                    <span class="st-role st-role-{{ $isInternalMsg ? 'internal' : $msgRole }}">{{ $roleLabel }}</span>
                                    <small>{{ $msgWhen }}</small>
                                </div>
                                @if(trim((string) ($msg->message ?? '')) !== '')
                                    <div class="st-bubble">{!! nl2br(e($msg->message)) !!}</div>
                                @endif
                                @php $msgFiles = $filesByActivity->get((int) $msg->id, collect()); @endphp
                                @if($msgFiles->count())
                                    <div class="st-msg-files">
                                        @foreach($msgFiles as $file)
                                            <a class="st-attach-chip" href="{{ route('superadmin.support.attachment.download', $file->id) }}">
                                                <i class="ti ti-paperclip"></i> {{ $file->filename ?? 'File' }}
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-muted px-2 st-empty">No conversation messages yet.</p>
                    @endforelse
                </div>

                <form method="POST" action="{{ route('superadmin.support.activity', $ticket->id) }}"
                      enctype="multipart/form-data" class="st-composer" id="stComposer">
                    @csrf
                    <label class="st-composer-attach" title="Attach files">
                        <i class="ti ti-paperclip"></i>
                        <input type="file" name="attachments[]" id="stFiles" multiple hidden
                               accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip">
                    </label>
                    <span class="st-file-hint" id="stFileHint"></span>
                    <input type="text" name="message" class="form-control st-composer-input"
                           placeholder="Type a message..." autocomplete="off">
                    <button type="submit" class="st-composer-send" title="Send">
                        <i class="ti ti-send"></i>
                    </button>
                </form>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="col-lg-4">
            <div class="st-card mb-4">
                <div class="st-section-title">
                    <i class="ti ti-user-check"></i> Assign / Reassign Agent
                                            </div>
                <div class="p-3 pt-0">
                    <form method="POST" action="{{ route('superadmin.support.assign', $ticket->id) }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label fw-semibold">Current Agent</label>
                            @if($assignedPresence)
                                <div class="st-agent-row" data-presence-id="{{ $assignedAgentId }}">
                                    <span class="st-presence-dot st-presence-{{ $assignedPresence['state'] }}"
                                          title="{{ $assignedPresence['label'] }}"></span>
                                    <div class="min-w-0">
                                        <div class="fw-semibold d-flex align-items-center gap-2 flex-wrap">
                                            {{ $assignedAgentName }}
                                            <span class="st-pill st-pill-sm st-badge-inactive js-inactive-badge {{ $assignedPresence['state'] === 'inactive' ? '' : 'd-none' }}">Inactive</span>
                                        </div>
                                        <small class="text-muted js-presence-label">{{ $assignedPresence['label'] }}</small>
                                    </div>
                                </div>
                            @else
                                <div class="fw-semibold">{{ $assignedAgentName ?: 'Unassigned' }}</div>
                            @endif
                            {{-- @if(!empty($ticket->assignment_type))
                                <small class="text-muted">{{ $ticket->assignment_type }}</small>
                            @endif --}}
                        </div>
                        @if(!empty($ticket->first_response_due_at) && empty($ticket->first_response_at))
                            <div class="mb-2 small {{ !empty($ticket->is_escalated) ? 'text-danger' : 'text-muted' }}">
                                First response due:
                                {{ \Carbon\Carbon::parse($ticket->first_response_due_at)->format('d M Y, g:i A') }}
                                @if(!empty($ticket->is_escalated))
                                    <span class="badge bg-danger">Escalated</span>
                                @endif
                            </div>
                        @endif
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Assign To</label>
                            <select name="assigned_to" class="form-select" required>
                                <option value="" disabled @selected(empty($ticket->assigned_to))>— Select agent —</option>
                                @foreach(($agents ?? []) as $agent)
                                    @php $p = $agentPresence[(int) $agent->id] ?? null; @endphp
                                    {{-- Native <option> can't hold a dot, so presence is shown as text (kept in sync by JS) --}}
                                    <option value="{{ $agent->id }}"
                                            data-presence-option="{{ $agent->id }}"
                                            data-name="{{ $agent->user_name }}"
                                            @selected($assignedAgentId === (int) $agent->id)>
                                        {{ $agent->user_name }}{{ $p ? ' — ' . $p['label'] : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @if(($agents ?? collect())->isEmpty())
                                <small class="text-danger">No support agents found (admin_user_creation.role = {{ config('services.support.agent_role_id', 3) }}).</small>
                            @endif
                        </div>
                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="ti ti-user-plus"></i> Assign Agent
                        </button>
                    </form>
                </div>
            </div>

            {{-- Agent Availability: presence + manual Active/Inactive toggle --}}
            <div class="st-card mb-4" id="stAgentPanel"
                 data-presence-url="{{ route('superadmin.support.agents.presence') }}"
                 data-status-url="{{ url('superadmin/support/agents/__ID__/status') }}"
                 data-can-manage-all="{{ $canManageAllAgents ? '1' : '0' }}"
                 data-current-user="{{ $sessionUserId }}"
                 data-columns-ready="{{ $presenceColumnsReady ? '1' : '0' }}">
                <div class="st-section-title d-flex justify-content-between align-items-center">
                    <span><i class="ti ti-users"></i> Users Availability</span>
                    
                </div>
                <div class="p-3 pt-0">
                    @unless($presenceColumnsReady)
                        <div class="alert alert-warning small py-2 mb-2">
                            Availability columns are missing on <code>admin_user_creation</code>. Run the ALTER query to enable the toggle.
                        </div>
                    @endunless
                    <ul class="st-agent-list list-unstyled mb-0">
                        @forelse(($agents ?? []) as $agent)
                            @php
                                $p = $agentPresence[(int) $agent->id] ?? null;
                                $canToggle = $presenceColumnsReady && ($canManageAllAgents || $sessionUserId === (int) $agent->id);
                            @endphp
                            @continue(!$p)
                            <li class="st-agent-item" data-presence-id="{{ $agent->id }}">
                                <span class="st-presence-dot st-presence-{{ $p['state'] }}" title="{{ $p['label'] }}"></span>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-semibold text-truncate d-flex align-items-center gap-2">
                                        {{ $p['name'] }}
                                        @if($sessionUserId === (int) $agent->id)
                                            <span class="st-role st-role-staff">You</span>
                                        @endif
                                        <span class="st-pill st-pill-sm st-badge-inactive js-inactive-badge {{ $p['state'] === 'inactive' ? '' : 'd-none' }}">Inactive</span>
                                    </div>
                                    <small class="text-muted js-presence-label">{{ $p['label'] }}</small>
                    </div>
                                {{-- <div class="form-check form-switch m-0" title="{{ $canToggle ? 'Set Active / Inactive' : 'Only a super admin or the agent can change this' }}">
                                    <input class="form-check-input js-agent-toggle" type="checkbox" role="switch"
                                           data-agent-id="{{ $agent->id }}"
                                           aria-label="Toggle {{ $p['name'] }} active"
                                           @checked($p['is_active'])
                                           @disabled(!$canToggle)>
                                </div> --}}
                            </li>
                        @empty
                            <li class="text-muted small">No support agents found.</li>
                        @endforelse
                    </ul>
            </div>
        </div>

            <div class="st-card mb-4">
                <div class="st-section-title">
                    <i class="ti ti-refresh"></i> Update Status
                </div>
                <div class="p-3 pt-0">
                    <form method="POST" action="{{ route('superadmin.support.activity', $ticket->id) }}" id="statusForm">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Current Status</label>
                            <div>
                                <span class="st-pill {{ $statusBadgeClass }}">{{ $displayStatus }}</span>
                        </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Change To</label>
                            <select name="status" class="form-select" required>
                                <option value="" disabled selected>— Select status —</option>
                                @foreach(($allowedNextStatuses ?? []) as $s)
                                    <option value="{{ $s }}" @selected(old('status') === $s)>{{ $s }}</option>
                                @endforeach
                            </select>
                            @if(empty($allowedNextStatuses))
                                <small class="text-muted">No further status transitions available.</small>
                            @endif
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100" @disabled(empty($allowedNextStatuses))>
                            <i class="ti ti-check"></i> Update Status
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="st-card">
                <div class="st-section-title">
                    <i class="ti ti-info-circle"></i> Quick Info
                </div>
                <div class="p-3 pt-0">
                    <table class="table table-sm st-quick-table mb-0">
                        <tr>
                            <td>Ticket ID</td>
                            <td class="text-end fw-semibold">{{ $ticket->id }}</td>
                        </tr>
                        <tr>
                            <td>Ticket No</td>
                            <td class="text-end fw-semibold">{{ $ticket->ticket_no ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td>Created</td>
                            <td class="text-end">{{ $ticket->created_at ? $ticket->created_at->format('d M Y') : '-' }}</td>
                        </tr>
                        <tr>
                            <td>Total Activities</td>
                            <td class="text-end">{{ $allActivities->count() }}</td>
                        </tr>
                        <tr>
                            <td>Attachments</td>
                            <td class="text-end">{{ ($ticket->attachments ?? collect())->count() }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var box = document.getElementById('stConversation');
    if (!box) return;
    var thread = document.getElementById('stThread');
    var form = document.getElementById('stComposer');
    var countEl = document.getElementById('stMsgCount');
    var fileInput = document.getElementById('stFiles');
    var fileHint = document.getElementById('stFileHint');
    var lastId = parseInt(box.dataset.last || '0', 10);
    var pollUrl = box.dataset.poll;
    var sending = false;

    function esc(s) {
        return String(s || '').replace(/[&<>"']/g, function (c) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]);
        });
    }

    function filesHtml(files) {
        if (!files || !files.length) return '';
        return '<div class="st-msg-files">' + files.map(function (f) {
            return '<a class="st-attach-chip" href="' + esc(f.url) + '" target="_blank" rel="noopener">'
                + '<i class="ti ti-paperclip"></i> ' + esc(f.filename || 'File') + '</a>';
        }).join('') + '</div>';
    }

    var ROLE = {
        staff:   { label: 'Support', icon: 'ti-headset', name: 'Support Team' },
        partner: { label: 'Partner', icon: 'ti-user',    name: 'Partner' },
        system:  { label: 'System',  icon: 'ti-robot',   name: 'System' }
    };

    function render(m) {
        var role = ROLE[m.role] ? m.role : (m.is_staff ? 'staff' : 'partner');
        var r = ROLE[role];
        var name = m.author || r.name;
        var body = m.message
            ? '<div class="st-bubble">' + esc(m.message).replace(/\n/g, '<br>') + '</div>'
            : '';
        // Staff messages get a presence dot; window.stPresence fills its state on the next refresh.
        var authorId = role === 'staff' && m.author_id ? parseInt(m.author_id, 10) : 0;
        var avatar = '<div class="st-avatar-wrap"' + (authorId ? ' data-presence-id="' + authorId + '"' : '') + '>'
            + '<div class="st-avatar st-avatar-' + role + '"><i class="ti ' + r.icon + '"></i></div>'
            + (authorId ? '<span class="st-presence-dot st-presence-sm st-presence-offline"></span>' : '')
            + '</div>';
        if (authorId && window.stPresence) window.stPresence.refreshSoon();
        return '<div class="st-msg st-msg-' + role + (m.is_internal ? ' st-msg-internal' : '') + '" data-id="' + m.id + '">'
            + avatar
            + '<div class="st-msg-content"><div class="st-msg-meta">'
            + '<strong>' + esc(name) + '</strong>'
            + '<span class="st-role st-role-' + (m.is_internal ? 'internal' : role) + '">' + (m.is_internal ? 'Internal' : r.label) + '</span>'
            + '<small>' + esc(m.time || '') + '</small></div>'
            + body + filesHtml(m.attachments) + '</div></div>';
    }

    function append(list) {
        if (!list || !list.length) return;
        var empty = thread.querySelector('.st-empty');
        if (empty) empty.remove();
        list.forEach(function (m) {
            if (thread.querySelector('[data-id="' + m.id + '"]')) return;
            thread.insertAdjacentHTML('beforeend', render(m));
            lastId = Math.max(lastId, parseInt(m.id, 10));
        });
        box.dataset.last = String(lastId);
        countEl.textContent = thread.querySelectorAll('.st-msg').length;
        thread.scrollTop = thread.scrollHeight;
    }

    function poll() {
        fetch(pollUrl + '?after_id=' + lastId, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (res) { if (res.success) append(res.messages || []); })
        .catch(function () {});
    }

    fileInput.addEventListener('change', function () {
        var n = this.files ? this.files.length : 0;
        fileHint.textContent = n ? (n + ' file' + (n > 1 ? 's' : '')) : '';
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (sending) return;
        var input = form.querySelector('[name="message"]');
        var hasText = String(input.value || '').trim() !== '';
        var hasFiles = fileInput.files && fileInput.files.length > 0;
        if (!hasText && !hasFiles) return;
        sending = true;
        form.querySelector('.st-composer-send').disabled = true;
        fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (res) {
            if (!res.j.success) {
                var msg = res.j.message || 'Failed';
                if (res.j.errors) {
                    var first = Object.values(res.j.errors)[0];
                    msg = Array.isArray(first) ? first[0] : msg;
                }
                throw new Error(msg);
            }
            input.value = '';
            fileInput.value = '';
            fileHint.textContent = '';
            if (res.j.message_row) append([res.j.message_row]);
            else poll();
        })
        .catch(function (err) {
            if (typeof alertify !== 'undefined') alertify.error(err.message || 'Send failed');
            else alert(err.message || 'Send failed');
        })
        .finally(function () {
            sending = false;
            form.querySelector('.st-composer-send').disabled = false;
            input.focus();
        });
    });

    setInterval(poll, 5000);
})();

// Agent presence: refreshes dots/labels every 30s and handles the Active/Inactive toggle.
(function () {
    var panel = document.getElementById('stAgentPanel');
    if (!panel) return;

    var presenceUrl = panel.dataset.presenceUrl;
    var statusUrl = panel.dataset.statusUrl;
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';
    var POLL_MS = 30000;
    var STATES = ['online', 'offline', 'away', 'inactive'];
    var soonTimer = null;

    function notify(type, msg) {
        if (typeof alertify !== 'undefined') alertify[type](msg);
        else if (type === 'error') alert(msg);
    }

    // Ids of every user shown on the page (assignee, agent list, thread authors).
    function visibleIds() {
        var ids = {};
        document.querySelectorAll('[data-presence-id]').forEach(function (el) {
            ids[el.dataset.presenceId] = 1;
        });
        return Object.keys(ids).join(',');
    }

    function apply(agent) {
        var id = String(agent.id);
        document.querySelectorAll('[data-presence-id="' + id + '"]').forEach(function (el) {
            var dot = el.querySelector('.st-presence-dot');
            if (dot) {
                STATES.forEach(function (s) { dot.classList.remove('st-presence-' + s); });
                dot.classList.add('st-presence-' + agent.state);
                dot.title = agent.label;
            }
            var label = el.querySelector('.js-presence-label');
            if (label) label.textContent = agent.label;
            var badge = el.querySelector('.js-inactive-badge');
            if (badge) badge.classList.toggle('d-none', agent.state !== 'inactive');
            var toggle = el.querySelector('.js-agent-toggle');
            if (toggle && !toggle.dataset.busy) toggle.checked = !!agent.is_active;
        });
        document.querySelectorAll('[data-presence-option="' + id + '"]').forEach(function (opt) {
            opt.textContent = (opt.dataset.name || agent.name) + ' — ' + agent.label;
        });
    }

    function refresh() {
        if (document.hidden) return;
        fetch(presenceUrl + '?ids=' + encodeURIComponent(visibleIds()), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
        .then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(function (res) {
            if (res.success) (res.agents || []).forEach(apply);
        })
        .catch(function () { /* transient failure: next poll retries */ });
    }

    function refreshSoon() {
        clearTimeout(soonTimer);
        soonTimer = setTimeout(refresh, 800);
    }

    panel.addEventListener('change', function (e) {
        var toggle = e.target;
        if (!toggle.classList.contains('js-agent-toggle')) return;

        var body = new FormData();
        body.append('status', toggle.checked ? 'active' : 'inactive');
        toggle.dataset.busy = '1';
        toggle.disabled = true;

        fetch(statusUrl.replace('__ID__', toggle.dataset.agentId), {
            method: 'POST',
            body: body,
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (res) {
            if (!res.ok || !res.j.success) throw new Error(res.j.message || 'Status update failed');
            apply(res.j.agent);
            notify('success', res.j.message);
        })
        .catch(function (err) {
            toggle.checked = !toggle.checked; // roll back the optimistic switch
            notify('error', err.message || 'Status update failed');
        })
        .finally(function () {
            delete toggle.dataset.busy;
            toggle.disabled = false;
        });
    });

    window.stPresence = { refresh: refresh, refreshSoon: refreshSoon };
    setInterval(refresh, POLL_MS);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) refresh();
    });
})();
</script>
@endpush

