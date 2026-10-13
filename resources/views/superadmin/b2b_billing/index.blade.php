@extends('superadmin.layouts.app')

@section('content')
<div class="row">
    <div class="col-12 mt-2">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0">B2B Billing (Partner Customers)</h4>
            <ol class="breadcrumb m-0">
                <li class="breadcrumb-item"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item active">Billing</li>
            </ol>
        </div>
    </div>

    <div class="col-12">
        <div class="card mb-2">
            <div class="card-header py-2">
                <h5 class="card-title mb-0">Search &amp; Filters</h5>
            </div>
            <div class="card-body p-2">
                <form id="b2bBillingSearchForm" action="#" method="get" onsubmit="return false;">
                    @csrf
                    <div class="row g-2 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Partner Name</label>
                            <input type="text" name="partner_name" id="b2b_filter_partner_name" class="form-control form-control-sm" value="" placeholder="Partner">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Domain Name</label>
                            <input type="text" name="domain_name" id="b2b_filter_domain_name" class="form-control form-control-sm" value="" placeholder="example.com">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Customer Name</label>
                            <input type="text" name="customer_name" id="b2b_filter_customer_name" class="form-control form-control-sm" value="" placeholder="Customer">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Mobile</label>
                            <input type="text" name="mobile" id="b2b_filter_mobile" class="form-control form-control-sm" value="" placeholder="10-digit">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Email</label>
                            <input type="email" name="email" id="b2b_filter_email" class="form-control form-control-sm" value="" placeholder="email@example.com">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">From Date</label>
                            <input type="date" name="from_date" id="b2b_filter_from_date" class="form-control form-control-sm" value="">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">To Date</label>
                            <input type="date" name="to_date" id="b2b_filter_to_date" class="form-control form-control-sm" value="">
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" id="b2bBillingSearchSubmit" class="btn btn-sm rounded-pill px-4" style="background:#1e58b4;color:#fff;">Search</button>
                            <button type="button" id="b2bBillingSearchReset" class="btn btn-sm rounded-pill px-3 d-block" style="background:#1e58b4;color:#fff;">Reset</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header py-2 d-flex align-items-center justify-content-between gap-2">
                <h5 class="card-title mb-0">Order History</h5>
                <form id="b2bBulkInvoiceForm" method="POST" action="{{ route('superadmin.b2b_billing.bulk_invoices') }}" target="_blank" class="m-0">
                    @csrf
                    <button type="button" id="b2bGenerateInvoicesBtn" class="btn btn-sm rounded-pill px-3" style="background:#1e58b4;color:#fff;">
                        Show Invoice PDF
                    </button>
                </form>
            </div>
            <div class="card-body p-2">
                <div class="table-responsive">
                    <table class="table table-custom table-centered table-select table-hover w-100 mb-0 text-nowrap" id="b2bBillingOrdersTable">
                        <thead class="bg-light align-middle bg-opacity-25 thead-sm">
                            <tr>
                                <th style="width:1%"><input type="checkbox" id="b2b_select_all"></th>
                                <th>Order ID</th>
                                <th>Customer Name</th>
                                <th>Domain</th>
                                <th>Plan Name</th>
                                <th>Order Type</th>
                                <th>Amount</th>
                                <th>Credit Used</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th class="text-center" style="width:20%">Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function () {
        var b2bBillingTable = $('#b2bBillingOrdersTable').DataTable({
            processing: true,
            serverSide: true,
            searching: true,
            ajax: {
                url: "{{ route('superadmin.b2b_billing.list_table') }}",
                type: "POST",
                data: function (d) {
                    d._token = $('meta[name="csrf-token"]').attr('content');
                    d.partner_name = $('#b2b_filter_partner_name').val();
                    d.domain_name = $('#b2b_filter_domain_name').val();
                    d.customer_name = $('#b2b_filter_customer_name').val();
                    d.mobile = $('#b2b_filter_mobile').val();
                    d.email = $('#b2b_filter_email').val();
                    d.from_date = $('#b2b_filter_from_date').val();
                    d.to_date = $('#b2b_filter_to_date').val();
                }
            },
            order: [[1, 'desc']],
            columns: [
                {
                    data: 'order_id',
                    name: 'order_id',
                    orderable: false,
                    searchable: false,
                    render: function (data) {
                        return '<input type="checkbox" class="b2b-order-select" value="' + data + '">';
                    }
                },
                {
                    data: 'id',
                    name: 'id',
                    render: function (data) {
                        return data || '-';
                    }
                },
                { data: 'customer_name', name: 'customer_name' },
                { data: 'domain_name', name: 'domain_name' },
                { data: 'plan_name', name: 'plan_name' },
                { data: 'order_type', name: 'order_type' },
                { data: 'amount', name: 'amount', orderable: false, searchable: false },
                { data: 'credit_used', name: 'credit_used', orderable: false, searchable: false },
                { data: 'created_at', name: 'created_at' },
                { data: 'status', name: 'status', orderable: false, searchable: false },
                { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-center' }
            ],
            columnDefs: [
                { targets: '_all', defaultContent: '-' }
            ]
        });

        var selectedOrderIds = new Set();

        function syncCheckedRows() {
            $('#b2bBillingOrdersTable .b2b-order-select').each(function () {
                var id = String($(this).val() || '');
                $(this).prop('checked', selectedOrderIds.has(id));
            });
        }

        $('#b2bBillingOrdersTable').on('change', '.b2b-order-select', function () {
            var id = String($(this).val() || '');
            if ($(this).is(':checked')) {
                selectedOrderIds.add(id);
            } else {
                selectedOrderIds.delete(id);
                $('#b2b_select_all').prop('checked', false);
            }
        });

        $('#b2b_select_all').on('change', function () {
            var checked = $(this).is(':checked');
            $('#b2bBillingOrdersTable .b2b-order-select').each(function () {
                var id = String($(this).val() || '');
                $(this).prop('checked', checked);
                if (checked) selectedOrderIds.add(id);
                else selectedOrderIds.delete(id);
            });
        });

        b2bBillingTable.on('draw', function () {
            syncCheckedRows();
        });

        $('#b2bBillingSearchForm').on('submit', function (e) {
            e.preventDefault();
            b2bBillingTable.ajax.reload();
        });

        $('#b2bBillingSearchReset').on('click', function () {
            $('#b2bBillingSearchForm')[0].reset();
            selectedOrderIds.clear();
            $('#b2b_select_all').prop('checked', false);
            b2bBillingTable.ajax.reload();
        });

        $('#b2bGenerateInvoicesBtn').on('click', function () {
            if (selectedOrderIds.size === 0) {
                alert('Please select at least one order.');
                return;
            }

            var $form = $('#b2bBulkInvoiceForm');
            $form.find('input[name="order_ids[]"]').remove();
            selectedOrderIds.forEach(function (id) {
                $('<input type="hidden" name="order_ids[]">').val(id).appendTo($form);
            });
            $form.trigger('submit');
        });
    });
</script>
@endpush
