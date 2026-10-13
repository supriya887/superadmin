@extends('superadmin.layouts.app')

@section('content')
<style>
    .ldr-page .nav-tabs-scroll {
        display: flex;
        flex-wrap: nowrap;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        gap: .25rem;
        scrollbar-width: thin;
    }
    .ldr-page .nav-tabs-scroll .nav-link {
        white-space: nowrap;
    }
    .ldr-page .ldr-actions {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap;
        gap: .35rem;
        justify-content: flex-end;
        align-items: center;
    }
    .ldr-page .ldr-actions form {
        display: inline-flex !important;
        margin: 0;
        flex: 0 0 auto;
    }
    .ldr-page .table td,
    .ldr-page .table th {
        vertical-align: middle;
    }
    .ldr-page .ldr-card .ldr-actions {
        justify-content: flex-start;
        margin-top: .75rem;
        flex-wrap: wrap;
    }
    @media (max-width: 767.98px) {
        .ldr-page .page-title-box h4 {
            font-size: 1.15rem;
        }
    }
</style>

<div class="ldr-page">
    <div class="breadcrumb-header d-flex align-items-center">
        <div class="me-auto">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="{{ route('superadmin.dashboard') }}">Super Admin</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">License Decrease</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row mt-2">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h4 class="mb-0 font-main fw-bold">License Decrease Requests</h4>
            </div>
        </div>
    </div>

    <div class="row mt-2">
        <div class="col-12">
            @if(!empty($table_missing))
                <div class="alert alert-warning">
                    Table <code>license_decrease_requests</code> is missing. Run migrations on the billing database.
                </div>
            @endif

            <div class="card shadow-sm border-0">
                <div class="card-body pt-3 pb-0">
                    <ul class="nav nav-tabs nav-tabs-scroll mb-3 border-bottom-0" role="tablist">
                        <li class="nav-item">
                            <a href="{{ route('superadmin.license_decrease_requests.index', ['status' => 'pending']) }}" class="nav-link {{ ($status ?? 'pending') === 'pending' ? 'active' : '' }}">Pending</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('superadmin.license_decrease_requests.index', ['status' => 'approved']) }}" class="nav-link {{ ($status ?? '') === 'approved' ? 'active' : '' }}">Approved</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('superadmin.license_decrease_requests.index', ['status' => 'rejected']) }}" class="nav-link {{ ($status ?? '') === 'rejected' ? 'active' : '' }}">Rejected</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('superadmin.license_decrease_requests.index', ['status' => 'reverted']) }}" class="nav-link {{ ($status ?? '') === 'reverted' ? 'active' : '' }}">Reverted</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('superadmin.license_decrease_requests.index', ['status' => 'all']) }}" class="nav-link {{ ($status ?? '') === 'all' ? 'active' : '' }}">All</a>
                        </li>
                    </ul>
                </div>

                <div class="card-body p-0">
                    @if ($rows->isEmpty())
                        <div class="p-4 text-center text-muted">No license decrease requests found.</div>
                    @else
                        {{-- Mobile / tablet cards --}}
                        <div class="d-lg-none p-3">
                            <div class="row g-3">
                                @foreach ($rows as $row)
                                    @php $st = strtolower(trim((string) $row->status)); @endphp
                                    <div class="col-12 col-md-6">
                                        <div class="border rounded-3 p-3 h-100 bg-light-subtle ldr-card">
                                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                                <div>
                                                    <div class="fw-semibold">#{{ $row->id }} · {{ $row->partner?->name ?? ('Partner #' . $row->partner_id) }}</div>
                                                    <div class="small text-muted text-break">{{ $row->partner?->email ?? '' }}</div>
                                                </div>
                                                @if($st === 'approved')
                                                    <span class="badge bg-success">Approved</span>
                                                @elseif($st === 'rejected')
                                                    <span class="badge bg-danger">Rejected</span>
                                                @elseif($st === 'reverted')
                                                    <span class="badge bg-secondary">Reverted</span>
                                                @else
                                                    <span class="badge bg-warning text-dark">Pending</span>
                                                @endif
                                            </div>
                                            <div class="small mb-1"><span class="text-muted">Date:</span> {{ $row->created_at?->format('d M Y h:i A') }}</div>
                                            <div class="small mb-1"><span class="text-muted">Order:</span> {{ $row->order?->order_no ?? ('#' . $row->order_id) }}</div>
                                            <div class="small mb-1"><span class="text-muted">Domain:</span> {{ $row->order?->domain_name ?? '—' }}</div>
                                            <div class="small mb-1">
                                                <span class="text-muted">Licenses:</span>
                                                <strong>{{ (int) $row->current_licenses }}</strong>
                                                <span class="mx-1">→</span>
                                                <strong class="text-primary">{{ (int) $row->requested_licenses }}</strong>
                                            </div>
                                           

                                            @if(($status ?? 'pending') === 'pending' && $st === 'pending')
                                                <div class="ldr-actions d-flex flex-row align-items-center gap-1">
                                                    <form action="{{ route('superadmin.license_decrease_requests.approve', $row->id) }}" method="POST" class="d-inline-flex m-0" onsubmit="return confirm('Are you sure you want to approve request #{{ $row->id }}?');">
                                                        @csrf
                                                        <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                                    </form>
                                                    <form action="{{ route('superadmin.license_decrease_requests.reject', $row->id) }}" method="POST" class="d-inline-flex m-0" onsubmit="return confirm('Are you sure you want to reject request #{{ $row->id }} and restore original licenses?');">
                                                        @csrf
                                                        <button type="submit" class="btn btn-outline-danger btn-sm">Reject</button>
                                                    </form>
                                                    <form action="{{ route('superadmin.license_decrease_requests.revert', $row->id) }}" method="POST" class="d-inline-flex m-0" onsubmit="return confirm('Are you sure you want to revert request #{{ $row->id }} and restore original licenses?');">
                                                        @csrf
                                                        <button type="submit" class="btn btn-outline-secondary btn-sm">Revert</button>
                                                    </form>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Desktop table --}}
                        <div class="d-none d-lg-block table-responsive">
                            <table class="table table-hover align-middle mb-0 w-100">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:56px;">ID</th>
                                        <th style="min-width:140px;">Date</th>
                                        <th style="min-width:180px;">Partner</th>
                                        <th>Order</th>
                                        <th class="d-none d-xl-table-cell">Domain</th>
                                        <th>Licenses</th>
                                        <th>Status</th>
                                        @if(($status ?? 'pending') === 'pending')
                                            <th class="text-end" style="min-width:220px;">Action</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $row)
                                        @php $st = strtolower(trim((string) $row->status)); @endphp
                                        <tr>
                                            <td>{{ $row->id }}</td>
                                            <td class="text-nowrap">{{ $row->created_at?->format('d M Y h:i A') }}</td>
                                            <td>
                                                <div class="fw-medium">{{ $row->partner?->name ?? ('#' . $row->partner_id) }}</div>
                                                <div class="small text-muted text-break">{{ $row->partner?->email ?? '' }}</div>
                                            </td>
                                            <td class="text-nowrap">{{ $row->order?->order_no ?? ('#' . $row->order_id) }}</td>
                                            <td class="d-none d-xl-table-cell text-break">{{ $row->order?->domain_name ?? '—' }}</td>
                                            <td class="text-nowrap">
                                                <strong>{{ (int) $row->current_licenses }}</strong>
                                                <span class="mx-1 text-muted">→</span>
                                                <strong class="text-primary">{{ (int) $row->requested_licenses }}</strong>
                                            </td>
                                           
                                            <td>
                                                @if($st === 'approved')
                                                    <span class="badge bg-success">Approved</span>
                                                @elseif($st === 'rejected')
                                                    <span class="badge bg-danger">Rejected</span>
                                                @elseif($st === 'reverted')
                                                    <span class="badge bg-secondary">Reverted</span>
                                                @else
                                                    <span class="badge bg-warning text-dark">Pending</span>
                                                @endif
                                            </td>
                                            @if(($status ?? 'pending') === 'pending')
                                                <td class="text-end">
                                                    @if($st === 'pending')
                                                        <div class="ldr-actions d-flex flex-row align-items-center justify-content-end gap-1">
                                                            <form action="{{ route('superadmin.license_decrease_requests.approve', $row->id) }}" method="POST" class="d-inline-flex m-0" onsubmit="return confirm('Are you sure you want to approve request #{{ $row->id }}?');">
                                                                @csrf
                                                                <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                                            </form>
                                                            <form action="{{ route('superadmin.license_decrease_requests.reject', $row->id) }}" method="POST" class="d-inline-flex m-0" onsubmit="return confirm('Are you sure you want to reject request #{{ $row->id }} and restore original licenses?');">
                                                                @csrf
                                                                <button type="submit" class="btn btn-outline-danger btn-sm">Reject</button>
                                                            </form>
                                                            <form action="{{ route('superadmin.license_decrease_requests.revert', $row->id) }}" method="POST" class="d-inline-flex m-0" onsubmit="return confirm('Are you sure you want to revert request #{{ $row->id }} and restore original licenses?');">
                                                                @csrf
                                                                <button type="submit" class="btn btn-outline-secondary btn-sm">Revert</button>
                                                            </form>
                                                        </div>
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
