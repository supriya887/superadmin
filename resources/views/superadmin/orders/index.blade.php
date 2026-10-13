@extends('superadmin.layouts.app')

@push('styles')
<style>
    .orders-table-scroll {
        width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
    }

    #orders-table {
        min-width: 1200px;
    }

    #orders-table th,
    #orders-table td {
        vertical-align: middle;
        padding: 8px 10px;
    }

    #orders-table td.text-center {
        text-align: center !important;
    }

    #orders-table tbody tr.orders-row-clickable {
        cursor: pointer;
    }

    #orders-table tbody tr.orders-row-clickable:hover {
        background-color: rgba(30, 88, 180, 0.06);
    }

    #orders-table tbody tr.orders-row-clickable td .action-btn-group,
    #orders-table tbody tr.orders-row-clickable td a {
        cursor: pointer;
    }
    
    .action-btn-group {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        flex-wrap: nowrap;
        min-width: 160px;
    }
    
    .action-btn-group .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.2rem;
        font-size: 0.7rem;
        border-radius: 50%;
        cursor: pointer;
        transition: all 0.2s ease;
        line-height: 1.3;
        white-space: nowrap;
        width: 30px;
        height: 30px;
        flex-shrink: 0;
        text-decoration: none;
        border: 1.5px solid #212529;
        background-color: transparent;
        color: #212529;
    }
    
    .action-btn-group .btn i.ti {
        font-size: 1.1rem;
        line-height: 1;
    }
    
    .action-btn-group .btn:hover {
        background-color: #212529;
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(33, 37, 41, 0.3);
    }
    
    .action-btn-group .btn-success {
        border-color: #28a745;
        color: #28a745;
    }
    
    .action-btn-group .btn-success:hover {
        background-color: #28a745;
        color: #fff;
    }
    
    .action-btn-group .btn-danger {
        border-color: #dc3545;
        color: #dc3545;
    }
    
    .action-btn-group .btn-danger:hover {
        background-color: #dc3545;
        color: #fff;
    }
    
    .action-btn-group .badge-disabled {
        cursor: default;
        opacity: 0.5;
    }
    
    .action-btn-group .badge-disabled:hover {
        background-color: transparent !important;
        color: inherit !important;
        transform: none !important;
        box-shadow: none !important;
    }
    
    .action-btn-group .btn-success.badge-disabled {
        border-color: #28a745;
        color: #28a745;
    }
    
    .action-btn-group .btn-secondary.badge-disabled {
        border-color: #6c757d;
        color: #6c757d;
    }
    
    .status-badge {
        padding: 0.25rem 0.6rem;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-block;
    }
    
    .status-badge.completed {
        background-color: #d4edda;
        color: #155724;
    }
    
    .status-badge.pending {
        background-color: #fff3cd;
        color: #856404;
    }
    
    .status-badge.active {
        background-color: #cce5ff;
        color: #004085;
    }
    
    .status-badge.cancelled {
        background-color: #f8d7da;
        color: #721c24;
    }
    
    .status-badge.failed {
        background-color: #f8d7da;
        color: #721c24;
    }
    
    .status-badge.rejected {
        background-color: #f8d7da;
        color: #721c24;
    }
    
    .status-dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 5px;
    }
    
    .status-dot.completed { background-color: #28a745; }
    .status-dot.pending { background-color: #ffc107; }
    .status-dot.active { background-color: #17a2b8; }
    .status-dot.cancelled { background-color: #dc3545; }
    .status-dot.failed { background-color: #dc3545; }
    .status-dot.rejected { background-color: #dc3545; }
    
    #actionConfirmModal .modal-content {
        border-radius: 12px;
        border: none;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        overflow: hidden;
    }
    
    #actionConfirmModal .modal-header {
        border-bottom: none;
        padding: 25px 25px 0 25px;
    }
    
    #actionConfirmModal .modal-body {
        padding: 20px 25px 25px 25px;
        text-align: center;
    }
    
    #actionConfirmModal .modal-footer {
        border-top: none;
        padding: 0 25px 25px 25px;
        justify-content: center;
        gap: 10px;
    }
    
    #actionConfirmModal .confirm-icon {
        width: 64px;
        height: 64px;
        margin: 0 auto 15px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 32px;
        line-height: 1;
    }
    
    #actionConfirmModal .confirm-icon i {
        font-size: 32px;
        line-height: 1;
    }
    
    .confirm-icon.warning {
        background-color: #fff3cd;
        color: #856404;
    }
    
    .confirm-icon.danger {
        background-color: #f8d7da;
        color: #721c24;
    }
    
    .confirm-icon.success {
        background-color: #d4edda;
        color: #155724;
    }
    
    .confirm-icon.info {
        background-color: #cce5ff;
        color: #004085;
    }
    
    #actionConfirmModal h4 {
        font-size: 20px;
        font-weight: 600;
        margin-bottom: 10px;
        color: #1a1a2e;
    }
    
    #actionConfirmModal p {
        color: #6c757d;
        font-size: 14px;
        margin-bottom: 5px;
        line-height: 1.6;
    }
    
    #actionConfirmModal .order-details {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 12px 16px;
        margin: 12px 0;
        font-size: 13px;
    }
    
    #actionConfirmModal .order-details strong {
        color: #1a1a2e;
    }
    
    #actionConfirmModal .btn {
        padding: 8px 24px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 13px;
        min-width: 100px;
    }
    
    #actionConfirmModal .btn-confirm {
        background-color: #1e58b4;
        color: #fff;
        border: none;
    }
    
    #actionConfirmModal .btn-confirm:hover {
        background-color: #164a8a;
    }
    
    #actionConfirmModal .btn-confirm.danger {
        background-color: #dc3545;
    }
    
    #actionConfirmModal .btn-confirm.danger:hover {
        background-color: #c82333;
    }
    
    #actionConfirmModal .btn-confirm.success {
        background-color: #28a745;
    }
    
    #actionConfirmModal .btn-confirm.success:hover {
        background-color: #218838;
    }
    
    #actionConfirmModal .btn-cancel {
        background-color: #e9ecef;
        color: #495057;
        border: none;
    }
    
    #actionConfirmModal .btn-cancel:hover {
        background-color: #dde0e3;
    }
    
    .toast-container {
        z-index: 9999;
    }
    
    @media (max-width: 768px) {
        .action-btn-group {
            min-width: 120px;
            gap: 3px;
        }
        
        .action-btn-group .btn {
            width: 26px;
            height: 26px;
        }
        
        .action-btn-group .btn i.ti {
            font-size: 0.9rem;
        }
        
        #orders-table {
            min-width: 900px;
        }
    }
    
    .action-btn-group .btn i.ti,
    .action-btn-group .btn i.ti-x,
    .action-btn-group .btn i.ti-toggle-left,
    .action-btn-group .btn i.ti-toggle-right,
    .action-btn-group .btn i.ti-eye,
    .action-btn-group .btn i.ti-edit {
        font-size: 1.1rem;
        line-height: 1;
        display: inline-block;
        visibility: visible !important;
        opacity: 1 !important;
    }
    
    .action-btn-group .btn-outline-danger {
        border-color: #dc3545 !important;
        color: #dc3545 !important;
    }
    
    .action-btn-group .btn-outline-danger:hover {
        background-color: #dc3545 !important;
        color: #fff !important;
    }
    
    .action-btn-group .btn-outline-danger i {
        color: inherit !important;
    }
    
    #orders-table td .btn-icon {
        width: 30px;
        height: 30px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    
    #orders-table td .btn-icon i {
        font-size: 1rem;
        line-height: 1;
    }
    
    .btn-outline-success {
        border-color: #28a745 !important;
        color: #28a745 !important;
    }
    
    .btn-outline-success:hover {
        background-color: #28a745 !important;
        color: #fff !important;
    }
    
    .btn-outline-secondary {
        border-color: #6c757d !important;
        color: #6c757d !important;
    }
    
    #actionConfirmModal .confirm-icon i {
        font-size: 36px !important;
        line-height: 1 !important;
        display: inline-block !important;
        visibility: visible !important;
        opacity: 1 !important;
    }
    
    #actionConfirmModal .confirm-icon i.ti-check {
        color: #28a745 !important;
    }
    
    #actionConfirmModal .confirm-icon i.ti-x {
        color: #dc3545 !important;
    }
    
    #actionConfirmModal .confirm-icon i.ti-alert-triangle {
        color: #856404 !important;
    }
    
    #actionConfirmModal .confirm-icon.success {
        background-color: #d4edda !important;
        color: #155724 !important;
    }
    
    #actionConfirmModal .confirm-icon.danger {
        background-color: #f8d7da !important;
        color: #721c24 !important;
    }
    
    #actionConfirmModal .confirm-icon.warning {
        background-color: #fff3cd !important;
        color: #856404 !important;
    }
    
    #actionConfirmModal .confirm-icon i {
        font-size: 32px !important;
        line-height: 1 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: 100% !important;
        height: 100% !important;
    }

    /* =============================================
       MODAL LOADER STYLES
    ============================================= */
    #actionConfirmModal .modal-loader {
        display: none;
        text-align: center;
        padding: 30px 0;
    }
    
    #actionConfirmModal .modal-loader.show {
        display: block;
    }
    
    #actionConfirmModal .modal-loader .spinner-border {
        width: 50px;
        height: 50px;
        margin-bottom: 15px;
        color: #1e58b4;
    }
    
    #actionConfirmModal .modal-loader .loader-text {
        color: #1a1a2e;
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 5px;
    }
    
    #actionConfirmModal .modal-loader .loader-subtext {
        color: #6c757d;
        font-size: 13px;
    }
    
    #actionConfirmModal .modal-content.processing .confirm-content {
        display: none !important;
    }
    
    #actionConfirmModal .modal-content.processing .modal-loader {
        display: block !important;
    }
    
    #actionConfirmModal .modal-content.processing .modal-footer {
        display: none !important;
    }
    
    #actionConfirmModal .modal-content.processing .btn-close {
        display: none !important;
    }
</style>
@endpush

@section('content')
<div class="row">
    <div class="col-12 mt-2">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0">Transactions - Orders</h4>
            <ol class="breadcrumb m-0">
                <li class="breadcrumb-item"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item active">Transactions</li>
            </ol>
        </div>
    </div>

    <!-- Search & Filters -->
    <div class="col-12">
        <div class="card mb-2">
            <div class="card-header py-2">
                <h5 class="card-title mb-0">Search &amp; Filters</h5>
            </div>
            <div class="card-body p-2">
                <form id="orders-search-form">
                    @csrf
                    <div class="row g-2 align-items-end">
                        <div class="col-6 col-md-6 col-lg-2">
                            <label class="form-label small mb-1">Order Type</label>
                            <select name="order_type" id="orders_filter_order_type" class="form-select form-select-sm">
                                <option value="">All</option>
                                @foreach($typeOptions as $t)
                                    <option value="{{ $t }}" {{ strtolower((string) request('order_type', '')) === strtolower($t) ? 'selected' : '' }}>
                                        {{ strtoupper($t) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-md-6 col-lg-2">
                            <label class="form-label small mb-1">Order ID</label>
                            <input type="text" name="order_id" id="orders_filter_order_id" class="form-control form-control-sm" value="{{ request('order_id', '') }}" placeholder="123">
                        </div>
                        <div class="col-6 col-md-6 col-lg-2">
                            <label class="form-label small mb-1">Customer Name</label>
                            <input type="text" name="customer_name" id="orders_filter_customer_name" class="form-control form-control-sm" value="{{ request('customer_name', '') }}" placeholder="Customer">
                        </div>
                        <div class="col-6 col-md-6 col-lg-2">
                            <label class="form-label small mb-1">Partner Name</label>
                            <input type="text" name="partner_name" id="orders_filter_partner_name" class="form-control form-control-sm" value="{{ request('partner_name', '') }}" placeholder="Partner">
                        </div>
                        <div class="col-6 col-md-6 col-lg-2">
                            <label class="form-label small mb-1">Plan Name</label>
                            <input type="text" name="plan_name" id="orders_filter_plan_name" class="form-control form-control-sm" value="{{ request('plan_name', '') }}" placeholder="Plan">
                        </div>
                        <div class="col-6 col-md-6 col-lg-2">
                            <label class="form-label small mb-1">Domain</label>
                            <input type="text" name="domain_name" id="orders_filter_domain_name" class="form-control form-control-sm" value="{{ request('domain_name', '') }}" placeholder="example.com">
                        </div>

                        <div class="col-6 col-md-6 col-lg-2">
                            <label class="form-label small mb-1">Status</label>
                            <select name="status" id="orders_filter_status" class="form-select form-select-sm">
                                <option value="">All</option>
                                <option value="completed" {{ strtolower((string) request('status', '')) === 'completed' ? 'selected' : '' }}>Active</option>
                                <option value="pending" {{ strtolower((string) request('status', '')) === 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="processing" {{ strtolower((string) request('status', '')) === 'processing' ? 'selected' : '' }}>Processing</option>
                                <option value="active" {{ strtolower((string) request('status', '')) === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="cancelled" {{ strtolower((string) request('status', '')) === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                <option value="rejected" {{ strtolower((string) request('status', '')) === 'rejected' ? 'selected' : '' }}>Rejected</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-6 col-lg-2">
                            <label class="form-label small mb-1">Transaction status</label>
                            <select name="order_category" id="orders_filter_order_category" class="form-select form-select-sm">
                                <option value="">All</option>
                                <option value="new" {{ strtolower((string) request('order_category', '')) === 'new' ? 'selected' : '' }}>New</option>
                                <option value="renewal" {{ strtolower((string) request('order_category', '')) === 'renewal' ? 'selected' : '' }}>Renewal</option>
                                <option value="upgrade" {{ strtolower((string) request('order_category', '')) === 'upgrade' ? 'selected' : '' }}>Upgrade</option>
                                <option value="downgrade" {{ strtolower((string) request('order_category', '')) === 'downgrade' ? 'selected' : '' }}>Downgrade</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-6 col-lg-2">
                            <label class="form-label small mb-1">PO Process</label>
                            <select name="po_status" id="orders_filter_po_status" class="form-select form-select-sm">
                                <option value="">All</option>
                                <option value="po_pending">PO Pending</option>
                                <option value="aadhar_pending">Aadhaar Pending</option>
                                <option value="otp_pending">OTP Pending</option>
                                <option value="placed">Placed</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-6 col-lg-2">
                            <label class="form-label small mb-1">From Date</label>
                            <input type="date" name="from_date" id="orders_filter_from_date" class="form-control form-control-sm" value="{{ request('from_date', '') }}">
                        </div>
                        <div class="col-6 col-md-6 col-lg-2">
                            <label class="form-label small mb-1">To Date</label>
                            <input type="date" name="to_date" id="orders_filter_to_date" class="form-control form-control-sm" value="{{ request('to_date', '') }}">
                        </div>
                        <div class="col-12 col-lg-4 d-flex flex-wrap gap-2 align-items-center pt-1 pt-lg-0">
                            <button type="submit" class="btn btn-sm rounded-pill px-4" style="background:#1e58b4;color:#fff;">Search</button>
                            <button type="button" id="orders-search-reset" class="btn btn-sm btn-secondary rounded-pill px-3">Reset</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="col-12">
        <div class="card mt-2">
            <div class="card-header py-2 d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0">Orders Listing</h5>
                <div>
                    <a href="{{ route('superadmin.orders.create.step', ['step' => 1]) }}" class="btn btn-sm rounded-pill px-3 py-1" style="background:#1e58b4;color:#fff;">
                        + Add Order
                    </a>
                </div>
            </div>
            <div class="card-body p-2">
                <div class="table-responsive">
                    <table id="orders-table" class="table table-custom table-centered table-hover w-100 mb-0">
                        <thead class="bg-light align-middle bg-opacity-25 thead-sm">
                            <tr>
                                <th style="min-width:80px;">Order ID</th>
                                <th style="min-width:100px;">Customer Name</th>
                                <th style="min-width:100px;">Domain</th>
                                <th style="min-width:100px;">Partner Name</th>
                                <th style="min-width:100px;">Plan Name</th>
                                <th style="min-width:60px;">Order Type</th>
                                <th style="min-width:80px;">PO Status</th>
                                <th style="min-width:80px;">Amount</th>
                                <th style="min-width:80px;">Credit Used</th>
                                <th style="min-width:100px;">Credit Note</th>
                                <th style="min-width:80px;">Status</th>
                                <th style="min-width:90px;">Transaction Status</th>
                                <th style="min-width:80px;">Date</th>
                                <th style="min-width:180px;text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- CONFIRMATION MODAL WITH LOADER -->
<!-- ============================================ -->
<div class="modal fade" id="actionConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-confirm">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Normal Content -->
                <div class="confirm-content">
                    <div class="confirm-icon" id="confirmIcon">
                        <i class="ti ti-alert-triangle" id="confirmIconTag" style="font-size: 36px; line-height: 1;"></i>
                    </div>
                    <h4 id="confirmTitle">Confirm Action</h4>
                    <p id="confirmMessage">Are you sure you want to perform this action?</p>
                    <div class="order-details" id="orderDetails" style="display:none;">
                        <strong>Order ID:</strong> <span id="confirmOrderId">-</span><br>
                        <strong>Customer:</strong> <span id="confirmCustomer">-</span>
                    </div>
                </div>
                
                <!-- Loader Content -->
                <div class="modal-loader" id="modalLoader">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="loader-text" id="loaderText">Processing your request...</div>
                    <div class="loader-subtext" id="loaderSubtext">Please wait while we update the order status</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-confirm" id="confirmActionBtn">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden form for order actions -->
<form id="orderActionForm" action="" method="post" style="display:none;">
    @csrf
    <input type="hidden" name="action" id="orderActionInput" value="">
</form>

@endsection

@push('scripts')
<script>
$(document).ready(function () {
    var ordersListTableUrl = @json(route('superadmin.orders.list_table'));
    var actionBaseUrl = @json(route('superadmin.orders.action', ['id' => 0]));

    if (typeof toastr === 'undefined') {
        window.toastr = {
            success: function(msg) { 
                if (typeof alertify !== 'undefined') alertify.success(msg); 
                else alert(msg);
            },
            error: function(msg) { 
                if (typeof alertify !== 'undefined') alertify.error(msg); 
                else alert(msg);
            },
            warning: function(msg) { 
                if (typeof alertify !== 'undefined') alertify.warning(msg); 
                else alert(msg);
            },
            info: function(msg) { 
                if (typeof alertify !== 'undefined') alertify.message(msg); 
                else alert(msg);
            }
        };
    }

    function ordersFilterIsB2c() {
        var v = ($('#orders_filter_order_type').val() || '').toString().trim().toLowerCase();
        return v === 'b2c';
    }

    function syncOrdersPartnerColumn() {
        if (!$.fn.DataTable.isDataTable('#orders-table')) {
            return;
        }
        var table = $('#orders-table').DataTable();
        table.column(3).visible(!ordersFilterIsB2c());
    }

    var ordersTable = $('#orders-table').DataTable({
        processing: true,
        serverSide: true,
        pageLength: 25,
        autoWidth: false,
        responsive: false,
        scrollX: true,
        stateSave: false,
        ajax: {
            url: ordersListTableUrl,
            type: 'GET',
            data: function (d) {
                d.order_type = $('#orders_filter_order_type').val();
                d.order_id = $('#orders_filter_order_id').val();
                d.customer_name = $('#orders_filter_customer_name').val();
                d.partner_name = $('#orders_filter_partner_name').val();
                d.plan_name = $('#orders_filter_plan_name').val();
                d.domain_name = $('#orders_filter_domain_name').val();
                d.status = $('#orders_filter_status').val();
                d.order_category = $('#orders_filter_order_category').val();
                d.po_status = $('#orders_filter_po_status').val();
                d.from_date = $('#orders_filter_from_date').val();
                d.to_date = $('#orders_filter_to_date').val();
            },
            error: function(xhr, error, code) {
                console.error('DataTables Ajax Error:', error, code);
                console.error('Response:', xhr.responseText);
                
                var errorMsg = 'Failed to load orders. Please try again.';
                if (xhr.status === 419) {
                    errorMsg = 'Session expired (CSRF). Please refresh the page and sign in again.';
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                
                $('#orders-table tbody').html(
                    '<tr><td colspan="14" class="text-center text-danger py-4">' + 
                    '<i class="ti ti-alert-circle"></i> ' + errorMsg + 
                    '</td></tr>'
                );
            }
        },
        order: [[0, 'desc']],
        columns: [
            { data: 'id', name: 'id' },
            { data: 'customer_name', name: 'customer_name' },
            { data: 'domain_name', name: 'domain_name' },
            { data: 'partner_name', name: 'partner_name' },
            { data: 'plan_name', name: 'plan_name' },
            { data: 'order_type', name: 'order_type' },
            { data: 'po_journey_status', name: 'po_journey_status' },
            { data: 'amount', name: 'amount', orderable: false, searchable: false },
            { data: 'credit_used', name: 'credit_used', orderable: false, searchable: false },
            { data: 'credit_note', name: 'credit_note', orderable: false, searchable: false },
            { data: 'status', name: 'status', orderable: false, searchable: false },
            { data: 'transaction_status', name: 'transaction_status', orderable: false, searchable: false },
            { data: 'created_at', name: 'created_at' },
            { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-center' }
        ],
        columnDefs: [
            { targets: '_all', defaultContent: '-' }
        ],
        dom: '<"d-flex flex-wrap justify-content-between align-items-center"lBf>rtip',
        buttons: [
            { 
                extend: 'excel', 
                exportOptions: { columns: ':visible' },
                className: 'btn-sm btn-light border'
            },
            { 
                extend: 'print', 
                exportOptions: { columns: ':visible' },
                className: 'btn-sm btn-light border'
            },
            { 
                extend: 'pdf', 
                exportOptions: { columns: ':visible' },
                className: 'btn-sm btn-light border'
            }
        ],
        initComplete: function () {
            syncOrdersPartnerColumn();
            initTooltips();
        },
        drawCallback: function() {
            syncOrdersPartnerColumn();
            initTooltips();
        },
        createdRow: function(row, data) {
            var viewUrl = (data && (data.view_url || (data.DT_RowAttr && data.DT_RowAttr['data-view-url']))) || '';
            if (viewUrl) {
                $(row).attr('data-view-url', viewUrl).addClass('orders-row-clickable');
            }
        }
    });

    $(document).on('click', '#orders-table tbody tr.orders-row-clickable', function(e) {
        // Keep action buttons / links working without also opening view.
        if ($(e.target).closest('a, button, .action-btn-group, input, select, label').length) {
            return;
        }
        var url = $(this).attr('data-view-url') || '';
        if (url) {
            window.location.href = url;
        }
    });

    $('#orders-search-form').on('submit', function (e) {
        e.preventDefault();
        syncOrdersPartnerColumn();
        ordersTable.ajax.reload();
    });

    $('#orders-search-reset').on('click', function () {
        $('#orders-search-form')[0].reset();
        syncOrdersPartnerColumn();
        ordersTable.ajax.reload();
    });

    $('#orders_filter_order_type').on('change', function () {
        syncOrdersPartnerColumn();
    });

    var pendingAction = null;
    var pendingOrderId = null;
    var pendingOrderData = null;
    var isProcessing = false;

    function openConfirmModal(action, orderId, orderData) {
        if (isProcessing) return;
        
        pendingAction = action;
        pendingOrderId = orderId;
        pendingOrderData = orderData || {};

        var modal = $('#actionConfirmModal');
        var iconContainer = $('#confirmIcon');
        var iconTag = $('#confirmIconTag');
        var title = $('#confirmTitle');
        var message = $('#confirmMessage');
        var details = $('#orderDetails');
        var confirmBtn = $('#confirmActionBtn');

        modal.find('.modal-content').removeClass('processing');
        modal.find('.confirm-content').show();
        $('#modalLoader').removeClass('show');
        confirmBtn.show();
        modal.find('.btn-cancel').show();
        modal.find('.btn-close').show();
        confirmBtn.prop('disabled', false);

        iconContainer.removeClass('warning danger success info');
        confirmBtn.removeClass('danger success');

        switch(action) {
            case 'active':
                iconContainer.addClass('success');
                iconTag.attr('class', 'ti ti-circle-check');
                title.text('Activate Order');
                message.html('Are you sure you want to activate this order?<br><small class="text-muted">This will mark the order as active and trigger provisioning.</small>');
                confirmBtn.text('Yes, Activate');
                confirmBtn.addClass('success');
                $('#loaderText').text('Activating order...');
                $('#loaderSubtext').text('Please wait while we process the activation');
                break;
                
            case 'cancel':
                iconContainer.addClass('danger');
                iconTag.attr('class', 'ti ti-circle-x');
                title.text('Cancel Order');
                message.html('Are you sure you want to cancel this order?<br><small class="text-muted">This action cannot be undone.</small>');
                confirmBtn.text('Yes, Cancel');
                confirmBtn.addClass('danger');
                $('#loaderText').text('Cancelling order...');
                $('#loaderSubtext').text('Please wait while we process the cancellation');
                break;
                
            default:
                iconContainer.addClass('warning');
                iconTag.attr('class', 'ti ti-alert-triangle');
                title.text('Confirm Action');
                message.text('Are you sure you want to perform this action?');
                confirmBtn.text('Confirm');
                $('#loaderText').text('Processing your request...');
                $('#loaderSubtext').text('Please wait');
        }
        
        if (orderId) {
            details.show();
            $('#confirmOrderId').text('#' + (orderData.order || orderId));
            $('#confirmCustomer').text(orderData.customer || 'N/A');
        } else {
            details.hide();
        }

        modal.modal('show');
    }

    function executeOrderAction(action, orderId) {
    if (isProcessing) return;
    isProcessing = true;
    
    var modal = $('#actionConfirmModal');
    var confirmBtn = $('#confirmActionBtn');
    var cancelBtn = modal.find('.btn-cancel');
    var closeBtn = modal.find('.btn-close');
    
    // Show loader inside modal
    modal.find('.modal-content').addClass('processing');
    $('#modalLoader').addClass('show');
    confirmBtn.hide();
    cancelBtn.hide();
    closeBtn.hide();
    
    var form = $('#orderActionForm');
    var url = actionBaseUrl.replace('/0', '/' + orderId);
    
    form.attr('action', url);
    $('#orderActionInput').val(action);
    
    $.ajax({
        url: url,
        type: 'POST',
        data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
            action: action
        },
        dataType: 'json',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        timeout: 60000
    })
    .done(function(response) {
        // Close modal after response
        modal.modal('hide');
        
        setTimeout(function() {
            modal.find('.modal-content').removeClass('processing');
            $('#modalLoader').removeClass('show');
            confirmBtn.show();
            cancelBtn.show();
            closeBtn.show();
            confirmBtn.prop('disabled', false);
            isProcessing = false;
        }, 300);
        
        if (response.success) {
            if (typeof toastr !== 'undefined') {
                toastr.success(response.message || 'Action completed successfully');
            }
            
            // ✅ IMPORTANT: Force a complete table reload with cache clearing
            ordersTable.ajax.reload(function() {
                // Callback after reload is complete
                syncOrdersPartnerColumn();
                initTooltips();
            }, false);
            
        } else {
            var errorMsg = response.message || 'Action failed';
            if (typeof toastr !== 'undefined') {
                toastr.error(errorMsg);
            }
            // Still reload to ensure consistency
            ordersTable.ajax.reload(null, false);
        }
    })
    .fail(function(xhr) {
        modal.modal('hide');
        
        setTimeout(function() {
            modal.find('.modal-content').removeClass('processing');
            $('#modalLoader').removeClass('show');
            confirmBtn.show();
            cancelBtn.show();
            closeBtn.show();
            confirmBtn.prop('disabled', false);
            isProcessing = false;
        }, 300);
        
        var msg = 'Action failed';
        if (xhr.responseJSON && xhr.responseJSON.message) {
            msg = xhr.responseJSON.message;
        } else if (xhr.status === 0) {
            msg = 'Network error. Please check your connection.';
        } else if (xhr.status === 500) {
            msg = 'Server error. Please try again later.';
        }
        
        if (typeof toastr !== 'undefined') {
            toastr.error(msg);
        }
        console.error('Action failed:', xhr.responseText);
        
        // Reload to reset state
        ordersTable.ajax.reload(null, false);
    });
}

    $('#confirmActionBtn').on('click', function() {
        if (pendingAction && pendingOrderId && !isProcessing) {
            executeOrderAction(pendingAction, pendingOrderId);
        }
    });

    $(document).on('click', '.js-order-action', function(e) {
        e.preventDefault();
        if (isProcessing) return;
        
        var action = $(this).data('action');
        var orderId = $(this).data('id');
        var customer = $(this).data('customer') || 'N/A';
        var order = $(this).data('order') || orderId;
        
        if (!action || !orderId) {
            console.error('Missing action or order ID');
            return;
        }

        openConfirmModal(action, orderId, { 
            customer: customer,
            order: order
        });
    });

    function initTooltips() {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            try {
                var existing = bootstrap.Tooltip.getInstance(el);
                if (existing) {
                    existing.dispose();
                }
                new bootstrap.Tooltip(el);
            } catch(e) {}
        });
    }
});
</script>
@endpush