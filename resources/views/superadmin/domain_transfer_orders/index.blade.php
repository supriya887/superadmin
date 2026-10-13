@extends('superadmin.layouts.app')

{{-- Removed extra unnecessary custom styles as per instruction --}}
@push('styles')
<style>
    .orders-table-scroll {
        width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
    }
    #transfer-orders-table {
        width: 100% !important;
        min-width: 1600px;
    }
    #transfer-orders-table th,
    #transfer-orders-table td {
        white-space: nowrap;
        vertical-align: middle !important;
    }
    #transfer-orders-table thead th {
        background: #f8f9fa;
        font-weight: 600;
    }
</style>
@endpush

@section('content')
<div class="row">

    <!-- PAGE TITLE -->
    <div class="col-12 mt-2">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0">Transactions - Domain Transfer Orders</h4>
            <ol class="breadcrumb m-0">
                <li class="breadcrumb-item"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item active">Domain Transfer Orders</li>
            </ol>
        </div>
    </div>

    <!-- FILTERS -->
    <div class="col-12">
        <div class="card mb-2">
            <div class="card-header py-2">
                <h5 class="card-title mb-0">Search &amp; Filters</h5>
            </div>
            <div class="card-body p-2">
                <form id="transfer-orders-search-form">
                    @csrf
                    <div class="row g-2 align-items-end">
                        <div class="col-6 col-md-6 col-lg-2">
                            <label class="form-label small mb-1">Order Type</label>
                            <select name="order_type" id="transfer_orders_filter_order_type" class="form-select form-select-sm">
                                <option value="">All</option>
                                @foreach($typeOptions ?? [] as $t)
                                    <option value="{{ $t }}" {{ strtolower((string) request('order_type', '')) === strtolower($t) ? 'selected' : '' }}>
                                        {{ strtoupper($t) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-md-6 col-lg-2">
                            <label class="form-label small mb-1">Order ID</label>
                            <input type="text" name="order_id" id="transfer_orders_filter_order_id" class="form-control form-control-sm" 
                                   value="{{ request('order_id', '') }}" placeholder="123">
                        </div>
                        <div class="col-6 col-md-6 col-lg-2">
                            <label class="form-label small mb-1">Customer Name</label>
                            <input type="text" name="customer_name" id="transfer_orders_filter_customer_name" class="form-control form-control-sm" 
                                   value="{{ request('customer_name', '') }}" placeholder="Customer">
                        </div>
                        <div class="col-6 col-md-6 col-lg-2">
                            <label class="form-label small mb-1">Partner Name</label>
                            <input type="text" name="partner_name" id="transfer_orders_filter_partner_name" class="form-control form-control-sm" 
                                   value="{{ request('partner_name', '') }}" placeholder="Partner">
                        </div>
                        <div class="col-6 col-md-6 col-lg-2">
                            <label class="form-label small mb-1">Plan Name</label>
                            <input type="text" name="plan_name" id="transfer_orders_filter_plan_name" class="form-control form-control-sm" 
                                   value="{{ request('plan_name', '') }}" placeholder="Plan">
                        </div>
                        <div class="col-6 col-md-6 col-lg-2">
                            <label class="form-label small mb-1">Domain</label>
                            <input type="text" name="domain_name" id="transfer_orders_filter_domain_name" class="form-control form-control-sm" 
                                   value="{{ request('domain_name', '') }}" placeholder="example.com">
                        </div>
                        <div class="col-6 col-md-6 col-lg-2">
                            <label class="form-label small mb-1">Status</label>
                            <select name="status" id="transfer_orders_filter_status" class="form-select form-select-sm">
                                <option value="">All</option>
                                <option value="approved" {{ strtolower((string) request('status', '')) === 'approved' ? 'selected' : '' }}>Approved</option>
                                <option value="rejected" {{ strtolower((string) request('status', '')) === 'rejected' ? 'selected' : '' }}>Rejected</option>
                                <option value="pending" {{ strtolower((string) request('status', '')) === 'pending' ? 'selected' : '' }}>Pending</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-6 col-lg-2">
                            <label class="form-label small mb-1">Transaction status</label>
                            <select name="order_category" id="transfer_orders_filter_order_category" class="form-select form-select-sm">
                                <option value="">All</option>
                                <option value="new" {{ strtolower((string) request('order_category', '')) === 'new' ? 'selected' : '' }}>New</option>
                                <option value="renewal" {{ strtolower((string) request('order_category', '')) === 'renewal' ? 'selected' : '' }}>Renewal</option>
                                <option value="upgrade" {{ strtolower((string) request('order_category', '')) === 'upgrade' ? 'selected' : '' }}>Upgrade</option>
                                <option value="downgrade" {{ strtolower((string) request('order_category', '')) === 'downgrade' ? 'selected' : '' }}>Downgrade</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-6 col-lg-2">
                            <label class="form-label small mb-1">PO Process</label>
                            <select name="po_status" id="transfer_orders_filter_po_status" class="form-select form-select-sm">
                                <option value="">All</option>
                                <option value="po_pending">PO Pending</option>
                                <option value="aadhar_pending">Aadhaar Pending</option>
                                <option value="otp_pending">OTP Pending</option>
                                <option value="placed">Placed</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-6 col-lg-2">
                            <label class="form-label small mb-1">From Date</label>
                            <input type="date" name="from_date" id="transfer_orders_filter_from_date" class="form-control form-control-sm" 
                                   value="{{ request('from_date', '') }}">
                        </div>
                        <div class="col-6 col-md-6 col-lg-2">
                            <label class="form-label small mb-1">To Date</label>
                            <input type="date" name="to_date" id="transfer_orders_filter_to_date" class="form-control form-control-sm" 
                                   value="{{ request('to_date', '') }}">
                        </div>
                        <div class="col-12 col-lg-4 d-flex flex-wrap gap-2 align-items-center pt-1 pt-lg-0">
                            <button type="submit" class="btn btn-sm rounded-pill px-4" style="background:#1e58b4;color:#fff;">Search</button>
                            <button type="button" id="transfer-orders-search-reset" class="btn btn-sm btn-primary rounded-pill px-3" style="color:#fff;">Reset</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- TABLE -->
    <div class="col-12">
        <div class="card mt-2">
            <div class="card-header py-2 d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0">Domain Transfer Orders Listing</h5>
            </div>

            <div class="card-body p-2">
                @if(!$tableExists)
                    <div class="alert alert-warning mb-0">
                        domain_transfer_requests table not found.
                    </div>
                @else
                <div class="orders-table-scroll">
                    <table id="transfer-orders-table" class="table table-custom table-centered table-select table-hover w-100 mb-0">
                        <thead class="bg-light align-middle bg-opacity-25 thead-sm">
                            <tr>
                                <th>Order ID</th>
                                <th>Customer Name</th>
                                <th>Domain</th>
                                <th>Partner Name</th>
                                <th>Plan Name</th>
                                <th>Order Type</th>
                                <th>PO Status</th>
                                <th>Amount</th>
                                <th>Credit Used</th>
                                <th>Status</th>
                                <th>Transaction Status</th>
                                <th>Transfer Domain Used</th>
                                <th>Date</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($requests as $request)
                                @php
                                    $status = strtolower($request->status ?? 'pending');
                                    $badgeClass = match ($status) {
                                        'approved' => 'success',
                                        'rejected' => 'danger',
                                        // Use empty string for warning so we can handle custom
                                        'pending' => '',
                                        default => 'warning',
                                    };
                                    $transferDomainUsedText = (isset($request->transfer_domain_used) && $request->transfer_domain_used == 1) ? 'Yes' : 'No';
                                @endphp
                                <tr>
                                    <td>{{ $request->order_no ?? '-' }}</td>
                                    <td>{{ optional($request->customer)->name ?? '-' }}</td>
                                    <td>{{ $request->domain_name ?? '-' }}</td>
                                    <td>{{ optional($request->partner)->name ?? '-' }}</td>
                                    <td>{{ optional($request->plan)->name ?? '-' }}</td>
                                    <td>{{ $request->order_type ?? '-' }}</td>
                                    <td>{{ $request->order_category ?? '-' }}</td>
                                    <td>₹{{ $request->total_amount ?? 0 }}</td>
                                    <td>{{ $request->credit_used ?? 0 }}</td>
                                    <td>
                                        @if($status === 'pending')
                                            <span class="badge bg-warning" style="color:#856404 !important; background-color:#ffeeba !important; font-size:12px !important; font-weight:500; border:1px solid #ffeeba;">
                                                Pending
                                            </span>
                                        @else
                                            <span class="badge bg-{{ $badgeClass }}">
                                                {{ ucfirst($status) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td>{{ $request->payment_status ?? '-' }}</td>
                                    <td>{{ $transferDomainUsedText }}</td>
                                    <td>
                                        {{ $request->created_at
                                            ? $request->created_at->format('d M Y h:i A')
                                            : '-' }}
                                    </td>
                                    <td class="text-center">
                                        <a href="{{ route('superadmin.orders.details', $request->id) }}"
                                            class="btn btn-link btn-sm"
                                            title="View">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 20 20">
                                                <circle cx="10" cy="10" r="9" stroke="#D3D6EA" stroke-width="2" fill="#F7F9FC"/>
                                                <ellipse cx="10" cy="10" rx="3.5" ry="3" fill="#fff" stroke="#222" stroke-width="1.3"/>
                                                <circle cx="10" cy="10" r="1.1" fill="#222"/>
                                            </svg>
                                        </a>
                                        <a href="{{ route('superadmin.orders.edit', $request->id) }}"
                                            class="btn btn-link btn-sm"
                                            title="Edit">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 20 20">
                                                <circle cx="10" cy="10" r="9" stroke="#D3D6EA" stroke-width="2" fill="#F7F9FC"/>
                                                <path d="M7.5 13.25h-1a.75.75 0 0 1-.75-.75v-1l5.33-5.33a1.06 1.06 0 0 1 1.5 0l.67.66c.42.42.42 1.08 0 1.5L7.5 13.25Zm4.17-5.33 1.08 1.08" stroke="#222" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="14" class="text-center">
                                        No transfer orders found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Minimal DataTable initialization only, no extra functionality or CSS
    $(document).ready(function () {
        $('#transfer-orders-table').DataTable();
        $('#transfer-orders-search-reset').on('click', function () {
            $('#transfer-orders-search-form')[0].reset();
            $('#transfer-orders-search-form').submit();
        });
    });
</script>
@endpush