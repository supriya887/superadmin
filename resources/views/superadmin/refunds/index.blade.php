@extends('superadmin.layouts.app')
@section('content')
<div class="breadcrumb-header d-flex align-items-center">
    <div class="me-auto">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a href="{{ route('superadmin.dashboard') }}">Super Admin</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Refunds</li>
            </ol>
        </nav>
    </div>
</div>

<div class="col-12 mt-2">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <h4 class="mb-sm-0 font-main fw-bold">Refunds — Cancelled after payment</h4>
    </div>
</div>

<div class="container-fluid mt-3">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
    @endif

    @if(!empty($tableMissing))
        <div class="alert alert-warning mb-3">
            `payment_refunds` table is missing — Razorpay retry/process needs it. Cancelled paid invoices still list below.
        </div>
    @endif

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <h5 class="mb-3">Process Razorpay refund</h5>
            <form method="POST" action="{{ route('superadmin.refunds.store') }}" class="row g-3">
                @csrf
                <div class="col-md-3">
                    <label class="form-label">Order ID</label>
                    <input type="number" name="order_id" value="{{ old('order_id') }}" class="form-control" min="1" placeholder="e.g. 74">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Invoice payment txn ID</label>
                    <input type="number" name="invoice_payment_transaction_id" value="{{ old('invoice_payment_transaction_id') }}" class="form-control" min="1">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Razorpay payment ID</label>
                    <input type="text" name="razorpay_payment_id" value="{{ old('razorpay_payment_id') }}" class="form-control" placeholder="pay_xxx">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Amount (optional)</label>
                    <input type="number" step="0.01" min="1" name="amount" value="{{ old('amount') }}" class="form-control" placeholder="Full remaining if empty">
                </div>
                <div class="col-md-9">
                    <label class="form-label">Reason <span class="text-danger">*</span></label>
                    <input type="text" name="reason" value="{{ old('reason', 'Order cancelled after payment') }}" class="form-control" required minlength="5" maxlength="500">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Refund now</button>
                </div>
            </form>
            <p class="text-muted small mb-0 mt-2">
                Lists cancelled orders/invoices paid via <strong>Razorpay</strong>.
                Wallet-only and auth-noise rows stay hidden. Uncaptured payments still show as Refund Soon — use Retry.
            </p>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body pt-3 pb-0">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <ul class="nav nav-tabs mb-0" role="tablist">
                    <li class="nav-item">
                        <a href="{{ route('superadmin.refunds.index', ['status' => 'all', 'q' => $q]) }}" class="nav-link {{ $status === 'all' ? 'active' : '' }}">
                            All <span class="badge bg-secondary">{{ $counts['all'] ?? 0 }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('superadmin.refunds.index', ['status' => 'processing', 'q' => $q]) }}" class="nav-link {{ $status === 'processing' ? 'active' : '' }}">
                            Refund Soon <span class="badge bg-warning text-dark">{{ $counts['processing'] ?? 0 }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('superadmin.refunds.index', ['status' => 'processed', 'q' => $q]) }}" class="nav-link {{ $status === 'processed' ? 'active' : '' }}">
                            Done <span class="badge bg-success">{{ $counts['processed'] ?? 0 }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('superadmin.refunds.index', ['status' => 'failed', 'q' => $q]) }}" class="nav-link {{ $status === 'failed' ? 'active' : '' }}">
                            Failed <span class="badge bg-danger">{{ $counts['failed'] ?? 0 }}</span>
                        </a>
                    </li>
                </ul>
                <form method="GET" action="{{ route('superadmin.refunds.index') }}" class="d-flex gap-2">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <input type="search" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="Invoice / order / partner / pay_id" style="min-width: 240px;">
                    <button type="submit" class="btn btn-sm btn-outline-secondary">Search</button>
                </form>
            </div>
        </div>

        <div class="card-body p-0">
            @if ($rows->isEmpty())
                <div class="p-4 text-center text-muted">No cancelled-after-payment invoices found.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover table-nowrap align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice</th>
                                <th>Date</th>
                                <th>Partner</th>
                                <th>Order</th>
                                <th>Payment</th>
                                <th class="text-end">Amount</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                @php
                                    $bucket = $row['bucket'] ?? '';
                                    $date = $row['date'] ?? null;
                                    $dateLabel = $date ? \Carbon\Carbon::parse($date)->format('d M Y h:i A') : '—';
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-medium">{{ $row['invoice_no'] ?? '—' }}</div>
                                        @if(!empty($row['invoice_id']))
                                            <div class="small text-muted">#{{ $row['invoice_id'] }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $dateLabel }}</td>
                                    <td>{{ $row['partner_name'] ?? '—' }}</td>
                                    <td>
                                        @if(!empty($row['order_id']))
                                            <a href="{{ route('superadmin.orders.details', ['id' => $row['order_id']]) }}">
                                                {{ $row['order_no'] ?? ('#' . $row['order_id']) }}
                                            </a>
                                            @if(!empty($row['domain_name']))
                                                <div class="small text-muted">{{ $row['domain_name'] }}</div>
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>
                                        @if(($row['payment_type'] ?? '') === 'wallet')
                                            <span class="badge bg-secondary">Wallet</span>
                                            @if(($row['credit_used'] ?? 0) > 0)
                                                <div class="small text-muted">Credit ₹{{ number_format((float) $row['credit_used'], 2) }}</div>
                                            @endif
                                        @elseif(($row['payment_type'] ?? '') === 'mixed')
                                            <span class="badge bg-info text-dark">Razorpay + Wallet</span>
                                            <div class="small">{{ $row['razorpay_payment_id'] ?: '' }}</div>
                                        @else
                                            <span class="badge bg-primary">Razorpay</span>
                                            <div class="small">{{ $row['razorpay_payment_id'] ?: '—' }}</div>
                                        @endif
                                        @if(!empty($row['razorpay_refund_id']))
                                            <div class="small text-muted">{{ $row['razorpay_refund_id'] }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        ₹{{ number_format((float) ($row['amount'] ?? 0), 2) }}
                                        @if(!empty($row['is_partial']))
                                            <div class="small text-muted">Partial</div>
                                        @endif
                                    </td>
                                    <td>
                                        @if($bucket === 'processed')
                                            <span class="badge bg-success">Processed</span>
                                        @elseif($bucket === 'wallet')
                                            <span class="badge bg-secondary">Wallet / Credit</span>
                                        @elseif($bucket === 'failed')
                                            <span class="badge bg-danger">Failed</span>
                                        @elseif($bucket === 'processing')
                                            <span class="badge bg-warning text-dark">{{ $row['status_label'] ?? 'Processing' }}</span>
                                        @else
                                            <span class="badge bg-info text-dark">Refund Soon</span>
                                        @endif
                                        @if(!empty($row['failure_message']))
                                            <div class="small text-danger mt-1" style="max-width: 240px; white-space: normal;">{{ \Illuminate\Support\Str::limit($row['failure_message'], 140) }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if(!empty($row['can_retry']) && !empty($row['refund_id']))
                                            <form action="{{ route('superadmin.refunds.retry', $row['refund_id']) }}" method="POST" onsubmit="return confirm('Retry Razorpay refund?');">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-primary btn-sm">Retry</button>
                                            </form>
                                        @elseif(!empty($row['can_refund']) && (!empty($row['order_id']) || !empty($row['razorpay_payment_id'])))
                                            <form action="{{ route('superadmin.refunds.store') }}" method="POST" onsubmit="return confirm('Process Razorpay refund?');">
                                                @csrf
                                                @if(!empty($row['razorpay_payment_id']))
                                                    <input type="hidden" name="razorpay_payment_id" value="{{ $row['razorpay_payment_id'] }}">
                                                @endif
                                                @if(!empty($row['order_id']))
                                                    <input type="hidden" name="order_id" value="{{ $row['order_id'] }}">
                                                @endif
                                                <input type="hidden" name="reason" value="Order cancelled after payment">
                                                <button type="submit" class="btn btn-primary btn-sm">Refund</button>
                                            </form>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
