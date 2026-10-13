@extends('superadmin.layouts.app')

@section('content')
<div class="row">
    <div class="col-12 mt-2">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0">{{ $partner->name ?? 'Partner' }} — Pending Invoices</h4>
            <ol class="breadcrumb m-0">
                <li class="breadcrumb-item"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('superadmin.partner_invoices.index') }}">Invoice Management</a></li>
                <li class="breadcrumb-item active">Partner</li>
            </ol>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 col-xl-10 mx-auto">
        <div class="card border shadow-sm">
            <div class="card-body">
                <p class="text-muted small mb-0">
                    Company: {{ $partner->company_name ?: '—' }} · Email: {{ $partner->email ?: '—' }}
                </p>
            </div>
            <div class="table-responsive border-top">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Invoice ID</th>
                            <th>Invoice No</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($invoices as $invoice)
                            <tr>
                                <td>{{ $invoice->id }}</td>
                                <td>{{ $invoice->bill_no_full ?? $invoice->order_no ?? '—' }}</td>
                                <td>₹{{ number_format((float) ($invoice->net_amt ?? 0), 2) }}</td>
                                <td><span class="badge bg-warning text-dark">{{ ucfirst((string) ($invoice->status ?? 'pending')) }}</span></td>
                                <td>{{ $invoice->created_at ? \Carbon\Carbon::parse($invoice->created_at)->format('d M Y') : '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No pending invoices for this partner.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
