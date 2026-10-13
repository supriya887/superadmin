@extends('superadmin.layouts.app')

@section('content')

<div class="container-fluid px-0">

    <!-- Page Title -->
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h4 class="mb-0">{{ !empty($isUnassigned) ? 'Assign Ticket' : 'Ticket List' }}</h4>
            <ol class="breadcrumb m-0">
                <li class="breadcrumb-item">
                    <a href="{{ route('superadmin.dashboard') }}">Support</a>
                </li>
                <li class="breadcrumb-item active">{{ !empty($isUnassigned) ? 'Unassigned Tickets' : 'All Tickets' }}</li>
            </ol>
        </div>
    </div>

    @if(!empty($assignmentToast['message']))
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="ti ti-bell"></i> {{ $assignmentToast['message'] }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Stats Row Start -->
    <div class="mb-4">
        <div class="row g-2">
            <div class="col">
                <div class="d-flex flex-wrap justify-content-between">
                    @foreach(($stats ?? []) as $stat)
                        @php
                            $trend = (float) ($stat['trend'] ?? 0);
                            $isLast = $loop->last;
                            $suffix = (string) ($stat['suffix'] ?? '');
                            $value = $stat['value'] ?? 0;
                            $displayValue = is_numeric($value) && $suffix === ''
                                ? number_format((float) $value)
                                : $value;
                        @endphp
                        <div class="card mb-2 {{ $isLast ? '' : 'me-2' }} flex-grow-1" style="min-width: 200px;">
                            <div class="card-body">
                                <div class="d-flex gap-3">
                                    <div class="avatar-lg rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center">
                                        <i class="{{ $stat['icon'] ?? 'ti ti-ticket' }} fs-xxl"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="mb-2 d-flex justify-content-between align-items-center">
                                            <h5 class="fs-xl mb-0">
                                                {{ $displayValue }}
                                                @if($suffix !== '')
                                                    <small class="fs-6">{{ $suffix }}</small>
                                                @endif
                                            </h5>
                                            <span>
                                                {{ $trend > 0 ? '+' : '' }}{{ $trend }}%
                                                @if($trend > 0)
                                                    <i class="ti ti-arrow-up text-success"></i>
                                                @elseif($trend < 0)
                                                    <i class="ti ti-arrow-down text-danger"></i>
                                                @endif
                                            </span>
                                        </div>
                                        <p class="text-muted mb-0">{{ $stat['label'] ?? '' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    <!-- Stats Row End -->

    <!-- Tickets Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-semibold">{{ !empty($isUnassigned) ? 'Unassigned Tickets' : 'All Tickets' }}</h5>
            @if(empty($isUnassigned))
            <a href="{{ route('superadmin.support.create') }}" class="btn btn-primary btn-sm">
                Create Ticket
            </a>
            @endif
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table id="ticketsTable" class="table table-hover align-middle mb-0 w-100">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Ticket No</th>
                            <th>Subject</th>
                            <th>Priority</th>
                            <th>Assigned To</th>
                            <th>Status</th>
                            <th>Created At</th>
                            <th width="150">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tickets as $ticket)
                            <tr>
                                <td>{{ $ticket->id }}</td>
                                <td>
                                    <span class="fw-semibold text-primary">
                                        #{{ $ticket->ticket_no }}
                                    </span>
                                </td>
                                <td>{{ $ticket->subject }}</td>
                                <td>
                                    @php
                                        $priorityClass = match(strtolower($ticket->priority)) {
                                            'high' => 'bg-danger',
                                            'medium' => 'bg-warning text-dark',
                                            'low' => 'bg-success',
                                            default => 'bg-secondary'
                                        };
                                    @endphp
                                    <span class="badge {{ $priorityClass }}">
                                        {{ ucfirst($ticket->priority) }}
                                    </span>
                                </td>
                                <td>
                                    @if($ticket->assigned_to)
                                        {{ $agentNames[$ticket->assigned_to] ?? 'Agent #' . $ticket->assigned_to }}
                                    @else
                                        <span class="text-muted">Unassigned</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $statusClass = match(strtolower(trim((string) $ticket->status))) {
                                            'open' => 'bg-success',
                                            'in progress', 'in_progress' => 'bg-warning text-dark',
                                            'waiting for customer', 'waiting_for_customer' => 'bg-info',
                                            'resolved' => 'bg-primary',
                                            'closed' => 'bg-danger',
                                            'reopened' => 'bg-warning text-dark',
                                            'pending' => 'bg-secondary',
                                            default => 'bg-secondary',
                                        };
                                    @endphp
                                    <span class="badge {{ $statusClass }}">
                                        {{ ucfirst($ticket->status) }}
                                    </span>
                                </td>
                                <td data-order="{{ $ticket->created_at?->timestamp ?? 0 }}">
                                    {{ $ticket->created_at?->format('d M Y, h:i A') ?? '-' }}
                                </td>
                                <td>
                                    <a href="{{ route('superadmin.support.details', $ticket->id) }}"
                                       class="btn btn-icon btn-light btn-sm">
                                        <i class="ti ti-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script>
$(function () {
    if (!$.fn.DataTable) return;
    $('#ticketsTable').DataTable({
        order: [[6, 'desc']],
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        columnDefs: [{ targets: 7, orderable: false, searchable: false }],
        language: {
            emptyTable: 'No {{ !empty($isUnassigned) ? 'unassigned ' : '' }}support tickets found',
            search: '',
            searchPlaceholder: 'Search tickets...'
        }
    });
});
</script>
@endpush