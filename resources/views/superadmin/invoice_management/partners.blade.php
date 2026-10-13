@extends('superadmin.layouts.app')

@section('content')
<div class="row">
    <div class="col-12 mt-2">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0">Invoice Management</h4>
            <ol class="breadcrumb m-0">
                <li class="breadcrumb-item"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item active">Partners</li>
            </ol>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 col-xl-10 mx-auto">
        <div class="card border shadow-sm">
            <div class="card-body">
                <p class="text-muted small mb-0">
                    Select a partner to open their invoice dashboard. Pending, paid, and cancelled invoices are listed.
                </p>
            </div>
            <div class="table-responsive border-top">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Partner</th>
                            <th>Company</th>
                            <th class="text-center">Pending</th>
                            <th class="text-center">Paid</th>
                            <th class="text-center">Cancelled</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($partners as $p)
                            @php
                                $pending = (int) ($p->pending_invoices_count ?? 0);
                                $paid = (int) ($p->paid_invoices_count ?? 0);
                                $cancelled = (int) ($p->cancelled_invoices_count ?? 0);
                            @endphp
                            <tr>
                                <td class="fw-medium">{{ $p->name ?: '—' }}</td>
                                <td>{{ $p->company_name ?: '—' }}</td>
                                <td class="text-center">
                                    <span class="badge rounded-pill bg-warning text-dark border">{{ $pending }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge rounded-pill bg-success border">{{ $paid }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge rounded-pill bg-danger border">{{ $cancelled }}</span>
                                </td>
                                <td class="text-end">
                                    @if ($pending > 0 || $paid > 0 || $cancelled > 0)
                                        <a href="{{ route('superadmin.partner_invoices.partner', ['id' => $p->id]) }}"
                                           class="btn btn-sm btn-primary">
                                            View invoices
                                        </a>
                                    @else
                                        <span class="text-muted small">No invoices</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No partners found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
