@extends('superadmin.layouts.app')

@section('content')

<div class="row">
    <div class="col-12 mt-2">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0">{{ $partner->company_name ?: $partner->name }} — Invoices</h4>
            <ol class="breadcrumb m-0">
                <li class="breadcrumb-item"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('superadmin.partner_invoices.index') }}">Invoice management</a></li>
                <li class="breadcrumb-item active">Partner</li>
            </ol>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 mb-2">
        <div class="card">
            <div class="card-body py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <span class="text-muted small d-block">Partner</span>
                    <span class="fw-semibold">{{ $partner->name }}</span>
                    @if($partner->email)
                        <span class="text-muted small ms-1">· {{ $partner->email }}</span>
                    @endif
                </div>
                <a href="{{ route('superadmin.partner_invoices.index') }}" class="btn btn-sm btn-outline-primary">All partners</a>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header py-2 d-flex flex-wrap gap-2 align-items-center justify-content-between">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="fw-semibold me-2">Status:</span>
                    @php $filterTab = $filter_tab ?? 'all'; @endphp
                    <a href="{{ route('superadmin.partner_invoices.partner', ['id' => $partner->id]) }}"
                       class="btn btn-sm {{ $filterTab === 'all' ? 'btn-primary' : 'btn-light' }}">All</a>
                    <a href="{{ route('superadmin.partner_invoices.partner', ['id' => $partner->id, 'tab' => 'success']) }}"
                       class="btn btn-sm {{ $filterTab === 'success' ? 'btn-success' : 'btn-light' }}">Paid</a>
                    <a href="{{ route('superadmin.partner_invoices.partner', ['id' => $partner->id, 'tab' => 'pending']) }}"
                       class="btn btn-sm {{ $filterTab === 'pending' ? 'btn-warning' : 'btn-light' }}">Pending</a>
                    <a href="{{ route('superadmin.partner_invoices.partner', ['id' => $partner->id, 'tab' => 'fail']) }}"
                       class="btn btn-sm {{ $filterTab === 'fail' ? 'btn-danger' : 'btn-light' }}">Cancelled</a>
                </div>
                <a href="{{ route('superadmin.refunds.index') }}" class="btn btn-sm btn-outline-secondary">Refunds</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Type</th>
                                <th>Invoice no.</th>
                                <th>Company</th>
                                <th class="text-end">Amount (₹)</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Refund</th>
                                <th>View</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($invoices as $inv)
                                @php
                                    $tabKey = $inv['tab'] ?? 'pending';
                                    $badgeClass = match ($tabKey) {
                                        'success' => 'bg-success',
                                        'fail' => 'bg-danger',
                                        default => 'bg-warning text-dark',
                                    };
                                    $statusLabel = $inv['status_label'] ?? match ($tabKey) {
                                        'success' => 'Paid',
                                        'fail' => 'Cancelled',
                                        default => 'Pending',
                                    };
                                    $url = $inv['invoice_url'] ?? '';
                                    $canOpen = $url !== '';
                                    $isCreditNote = !empty($inv['is_credit_note']);
                                    $viewLabel = $inv['view_label'] ?? ($isCreditNote ? 'View credit note' : 'View');
                                @endphp
                                <tr class="{{ $canOpen ? 'im-invoice-row' : '' }}"
                                    @if($canOpen) data-invoice-url="{{ e($url) }}" role="button" tabindex="0" @endif>
                                    <td>
                                        @if($isCreditNote)
                                            <span class="badge bg-primary">Credit Note</span>
                                        @else
                                            <span class="badge bg-light text-dark border">Invoice</span>
                                        @endif
                                    </td>
                                    <td class="fw-medium">{{ $inv['invoice_no'] ?: '—' }}</td>
                                    <td>{{ $inv['company'] ?? '—' }}</td>
                                    <td class="text-end">{{ number_format((float) ($inv['amount'] ?? 0), 2) }}</td>
                                    <td class="text-muted small">{{ $inv['date'] ?: '—' }}</td>
                                    <td>
                                        <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                                    </td>
                                    <td>
                                        @if(!empty($inv['refund_status']))
                                            <span class="badge bg-info text-dark">{{ $inv['refund_status'] }}</span>
                                            @if(!empty($inv['order_id']))
                                                <a href="{{ route('superadmin.refunds.index', ['q' => $inv['order_id']]) }}"
                                                   class="small ms-1"
                                                   onclick="event.stopPropagation();">Manage</a>
                                            @endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if(!empty($inv['invoice_url']))
                                            <a href="{{ $inv['invoice_url'] }}" target="_blank" rel="noopener" class="btn btn-sm {{ $isCreditNote ? 'btn-outline-primary' : 'btn-primary' }}" onclick="event.stopPropagation();">{{ $viewLabel }}</a>
                                        @else
                                            <span class="text-muted small">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No invoices in this filter.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer py-2 text-muted small">
                @if($filterTab !== 'all')
                    Filter: <strong>{{ ucfirst($filterTab) }}</strong> —
                    <a href="{{ route('superadmin.partner_invoices.partner', ['id' => $partner->id]) }}">Clear filter</a>
                @else
                    Row click opens tax invoice in a new tab (when a linked order exists).
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Invoice opens in a new tab (same tax invoice route as BillingController) --}}
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        function openInvoice(url) {
            if (!url) return;
            window.open(url, '_blank', 'noopener,noreferrer');
        }

        document.querySelectorAll('tr.im-invoice-row').forEach(function (row) {
            row.addEventListener('click', function () {
                openInvoice(row.getAttribute('data-invoice-url'));
            });
            row.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    openInvoice(row.getAttribute('data-invoice-url'));
                }
            });
        });
    });
</script>
<style>
    tr.im-invoice-row { cursor: pointer; }
    tr.im-invoice-row:hover { filter: brightness(0.98); }
</style>
@endpush
