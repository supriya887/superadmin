@extends('superadmin.layouts.app')

@section('content')
@php
    $totals = [
        ['label' => 'Assigned', 'value' => $rows->sum('assigned'), 'icon' => 'ti ti-ticket', 'cls' => 'primary'],
        ['label' => 'Pending', 'value' => $rows->sum('pending'), 'icon' => 'ti ti-clock', 'cls' => 'warning'],
        ['label' => 'Resolved', 'value' => $rows->sum('resolved'), 'icon' => 'ti ti-circle-check', 'cls' => 'success'],
        ['label' => 'Closed', 'value' => $rows->sum('closed'), 'icon' => 'ti ti-lock', 'cls' => 'danger'],
    ];
    $fmtTime = fn ($h) => $h === null ? '-' : ($h < 1 ? round($h * 60) . ' min' : ($h < 24 ? round($h, 1) . ' hrs' : round($h / 24, 1) . ' days'));
@endphp

<div class="container-fluid px-0">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Agent Report</h4>
            <ol class="breadcrumb m-0">
                <li class="breadcrumb-item"><a href="{{ route('superadmin.support.index') }}">Support</a></li>
                <li class="breadcrumb-item active">Agent Report</li>
            </ol>
        </div>
    </div>

    <div class="row g-2 mb-3">
        @foreach($totals as $t)
            <div class="col-6 col-lg-3">
                <div class="card mb-0">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="avatar-lg rounded-circle bg-{{ $t['cls'] }}-subtle text-{{ $t['cls'] }} d-flex align-items-center justify-content-center">
                            <i class="{{ $t['icon'] }} fs-xxl"></i>
                        </div>
                        <div>
                            <h5 class="fs-xl mb-0">{{ number_format($t['value']) }}</h5>
                            <p class="text-muted mb-0">{{ $t['label'] }}</p>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="mb-0 fw-semibold">Tickets Resolved by Agent</h5>
            <form method="GET" class="d-flex flex-wrap align-items-center gap-2">
                <label class="text-muted small mb-0">Created</label>
                <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm" style="width:auto">
                <span class="text-muted small">to</span>
                <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm" style="width:auto">
                <button class="btn btn-primary btn-sm"><i class="ti ti-filter"></i> Filter</button>
                @if($from || $to)
                    <a href="{{ route('superadmin.support.report') }}" class="btn btn-light btn-sm">Reset</a>
                @endif
            </form>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="agentReportTable" class="table table-hover align-middle mb-0 w-100">
                    <thead class="table-light">
                        <tr>
                            <th>Agent</th>
                            <th class="text-center">Assigned</th>
                            <th class="text-center">Pending</th>
                            <th class="text-center">Resolved</th>
                            <th class="text-center">Closed</th>
                            <th class="text-center">Total Done</th>
                            <th style="min-width:160px">Resolution Rate</th>
                            <th>Avg. Resolution Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $r)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="avatar-sm rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center fw-bold">
                                            {{ strtoupper(mb_substr($r->name, 0, 1)) }}
                                        </span>
                                        <span class="fw-semibold">{{ $r->name }}</span>
                                    </div>
                                </td>
                                <td class="text-center">{{ $r->assigned }}</td>
                                <td class="text-center"><span class="badge bg-warning-subtle text-warning">{{ $r->pending }}</span></td>
                                <td class="text-center"><span class="badge bg-success-subtle text-success">{{ $r->resolved }}</span></td>
                                <td class="text-center"><span class="badge bg-danger-subtle text-danger">{{ $r->closed }}</span></td>
                                <td class="text-center fw-bold">{{ $r->done }}</td>
                                <td data-order="{{ $r->rate }}">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height:6px">
                                            <div class="progress-bar bg-success" style="width: {{ $r->rate }}%"></div>
                                        </div>
                                        <small class="fw-semibold">{{ $r->rate }}%</small>
                                    </div>
                                </td>
                                <td data-order="{{ $r->avg_hours ?? 999999 }}">{{ $fmtTime($r->avg_hours) }}</td>
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
    $('#agentReportTable').DataTable({
        order: [[5, 'desc']],
        pageLength: 25,
        language: { emptyTable: 'No support agents found', search: '', searchPlaceholder: 'Search agent...' }
    });
});
</script>
@endpush
