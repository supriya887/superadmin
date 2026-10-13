@extends('superadmin.layouts.app')

@section('content')
    <style>
        .dt-length {
            padding: 14px 0;
        }
        .action-btn-group {
            display: flex;
            flex-wrap: nowrap;
            gap: 5px;
            justify-content: center;
            align-items: center;
        }
        .action-btn-group .circle-btn,
        .action-btn-group .btn-notification-pref {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #f6f8fb;
            box-shadow: none;
            border: none;
            padding: 0;
            transition: background 0.15s;
        }
        .action-btn-group .circle-btn i,
        .action-btn-group .btn-notification-pref i {
            font-size: 20px;
            color: #394150;
        }
        .action-btn-group .circle-btn:hover,
        .action-btn-group .circle-btn:focus,
        .action-btn-group .btn-notification-pref:hover,
        .action-btn-group .btn-notification-pref:focus {
            background: #eef1f6;
        }
    </style>
    <div class="container-fluid mt-2">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">B2B Partners</h3>
                        <div class="card-tools d-flex flex-wrap gap-2">
                            <a href="{{ url('superadmin/b2b-partner-add') }}" class="btn btn-primary btn-sm">Add New Partner</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="searchForm">
                            <div class="row">
                                <div class="col-lg-3 mt-2">
                                    <div class="form-group">
                                        <label for="search_name">Name</label>
                                        <input class="form-control" type="text" name="search_name" id="search_name" value="">
                                    </div>
                                </div>
                                <div class="col-lg-3 mt-2">
                                    <div class="form-group">
                                        <label for="search_email">Email</label>
                                        <input class="form-control" type="text" name="search_email" id="search_email" value="">
                                    </div>
                                </div>
                                <div class="col-lg-3 mt-2">
                                    <div class="form-group">
                                        <label for="search_mobile">Mobile</label>
                                        <input class="form-control" type="text" name="search_mobile" id="search_mobile" value="">
                                    </div>
                                </div>
                                <div class="col-lg-3 mt-2">
                                    <div class="form-group">
                                        <label for="search_company_name">Company Name</label>
                                        <input class="form-control" type="text" name="search_company_name" id="search_company_name" value="">
                                    </div>
                                </div>
                                <div class="col-lg-3 mt-2">
                                    <div class="form-group">
                                        <label for="search_status">Status</label>
                                        <select class="form-control" name="search_status" id="search_status">
                                            <option value="">All</option>
                                            <option value="pending">Pending</option>
                                            <option value="approved">Approved</option>
                                            <option value="rejected">Rejected</option>
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-lg-12 mt-2">
                                    <div class="form-group text-center">
                                        @csrf
                                        <button class="btn btn-secondary w-md" type="submit" id="searchSubmit">
                                            <div class="spinner-border spinner-border-sm float-left mt-1" id="spinner"
                                                style="display:none" role="status">
                                                <i class="fa fa-spinner fa-spin" style="font-size:16px"></i>
                                            </div><i class="fa fa-search"></i> Search
                                        </button>
                                        <button class="btn btn-secondary w-md" type="button" id="resetSearch">
                                            <i class="fa fa-undo"></i> Reset
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-custom table-centered table-select table-hover w-100 mb-0" id="customerUsersTable">
                                <thead class="bg-light align-middle bg-opacity-25 thead-sm">
                                    <tr>
                                        <th>Sr. No.</th>
                                        <th>Name</th>
                                        <th>Discount</th>
                                        <th>Email</th>
                                        <th>Mobile</th>
                                        <th>Company Name</th>
                                        <th>Status</th>
                                        <th class="text-center" style="width: 1%">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="partnerCartModal" tabindex="-1" aria-labelledby="partnerCartModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="partnerCartModalLabel">Partner Cart</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="partnerCartBody">
                    <div class="text-center text-muted py-4">Loading cart…</div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function formatDisplayName(value) {
            var text = String(value == null ? '' : value).trim();
            if (text === '' || text === '—') {
                return '—';
            }
            return text.toLowerCase().replace(/(^|\s)\S/g, function (match) {
                return match.toUpperCase();
            });
        }

        $(document).ready(function() {
            var dataTable = $('#customerUsersTable').DataTable({
                processing: true,
                serverSide: true,
                order: [[0, 'desc']],
                ajax: {
                    url: "{{ url('superadmin/b2b-partners-list-table') }}",
                    type: "POST",
                    data: function(d) {
                        d._token = $('meta[name="csrf-token"]').attr('content');
                        d.search_name = $.trim($('#search_name').val() || '');
                        d.search_email = $.trim($('#search_email').val() || '');
                        d.search_mobile = $.trim($('#search_mobile').val() || '');
                        d.search_company_name = $.trim($('#search_company_name').val() || '');
                        d.search_status = $('#search_status').val() || '';
                    },
                    error: function(xhr) {
                        console.error('Partners list load failed', xhr.status, xhr.responseText);
                        if (typeof alertify !== 'undefined') {
                            alertify.error('Could not load partners list. Please refresh or contact support.');
                        }
                    }
                },
                columns: [
                    {
                        data: 'id',
                        name: 'id',
                        render: function (data, type, row, meta) {
                            if (type === 'sort' || type === 'type') {
                                return data;
                            }
                            if (row.sr_no != null && row.sr_no !== '') {
                                return row.sr_no;
                            }
                            return meta.settings._iDisplayStart + meta.row + 1;
                        }
                    },
                    {
                        data: 'name',
                        name: 'name',
                        render: function (data, type) {
                            if (type === 'display' || type === 'export' || type === 'filter') {
                                return formatDisplayName(data);
                            }
                            return data;
                        }
                    },
                    {
                        data: 'discount',
                        name: 'discount',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'email',
                        name: 'email'
                    },
                    {
                        data: 'mobile',
                        name: 'mobile'
                    },
                    {
                        data: 'company_name',
                        name: 'company_name',
                        render: function (data, type) {
                            if (type === 'display' || type === 'export' || type === 'filter') {
                                return formatDisplayName(data);
                            }
                            return data;
                        }
                    },
                    {
                        data: 'status',
                        name: 'status',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row) {
                            var actionHtml = data ? data : '';
                            var notificationUrl = "{{ route('superadmin.b2b_partners.notification_preference', ':customer_id') }}";
                            notificationUrl = notificationUrl.replace(':customer_id', row.id);
                            var buttons = $('<div class="action-btn-group"></div>');
                            if (actionHtml) {
                                buttons.append($(actionHtml));
                            }
                            buttons.append(
                                $('<a>')
                                    .attr('href', notificationUrl)
                                    .addClass('btn btn-light btn-icon btn-sm rounded-circle')
                                    .attr('title', 'Notification Preference')
                                    .html('<i class="ti ti-bell text-dark"></i>')
                            );
                            return buttons.prop('outerHTML');
                        }
                    }
                ],
                dom: "lBfrtip",
                buttons: [
                    {
                        extend: "excel",
                        className: 'btn btn-sm btn-light border',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6],
                            format: {
                                body: function (data) {
                                    return $('<div>').html(data).text().trim() || data;
                                }
                            }
                        }
                    },
                    {
                        extend: "print",
                        className: 'btn btn-sm btn-light border',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6],
                            format: {
                                body: function (data) {
                                    return $('<div>').html(data).text().trim() || data;
                                }
                            }
                        }
                    },
                    {
                        extend: "pdf",
                        className: 'btn btn-sm btn-light border',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6],
                            format: {
                                body: function (data) {
                                    return $('<div>').html(data).text().trim() || data;
                                }
                            }
                        }
                    },
                ],
            });

            $('#searchForm').on('submit', function(e) {
                e.preventDefault();
                $('#searchSubmit').attr('disabled', true);
                $('#spinner').show();
                dataTable.page('first').ajax.reload(function() {
                    $('#searchSubmit').attr('disabled', false);
                    $('#spinner').hide();
                }, true);
            });

            $('#resetSearch').on('click', function() {
                $('#searchForm')[0].reset();
                dataTable.page('first').ajax.reload(null, true);
            });

            deleteRowType("partner", "{{ url('superadmin/b2b-partner-delete') }}", "Partner Deleted Successfully.");

            var partnerCartUrl = @json(url('superadmin/b2b-partner-cart'));

            function escHtml(s) {
                return $('<div>').text(s == null ? '' : String(s)).html();
            }

            function fmtMoney(n) {
                var v = parseFloat(n);
                if (isNaN(v)) return '—';
                return '₹' + v.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function renderPartnerCart(res) {
                if (!res || !res.success) {
                    return '<div class="alert alert-danger mb-0">' + escHtml((res && res.message) || 'Could not load cart.') + '</div>';
                }
                if (!res.cart) {
                    return '<div class="alert alert-info mb-0">' + escHtml(res.message || 'No items in cart for this partner.') + '</div>';
                }

                var cart = res.cart;
                var customer = res.customer || {};
                var items = cart.items || [];
                var domain = '';
                if (items.length && items[0].domain_name) {
                    domain = items[0].domain_name;
                }

                var html = '<div class="row g-3 mb-3">';
                html += '<div class="col-md-4"><div class="border rounded p-2"><div class="text-muted small">Cart ID</div><strong>' + escHtml(cart.main_cart_id) + '</strong></div></div>';
                html += '<div class="col-md-4"><div class="border rounded p-2"><div class="text-muted small">Type / Status</div><strong>' + escHtml(cart.type) + '</strong> <span class="badge bg-secondary">' + escHtml(cart.status) + '</span></div></div>';
                html += '<div class="col-md-4"><div class="border rounded p-2"><div class="text-muted small">Coupon</div><strong>' + escHtml(cart.coupon || '—') + '</strong></div></div>';
                html += '<div class="col-md-6"><div class="border rounded p-2"><div class="text-muted small">Customer</div><strong>' + escHtml(customer.company_name || customer.name || '—') + '</strong></div></div>';
                html += '<div class="col-md-6"><div class="border rounded p-2"><div class="text-muted small">Domain</div><strong>' + escHtml(domain || '—') + '</strong></div></div>';
                html += '</div>';

                if (!items.length) {
                    html += '<div class="alert alert-info mb-0">Cart has no plan lines.</div>';
                    return html;
                }

                html += '<div class="table-responsive"><table class="table table-sm table-bordered align-middle mb-3">';
                html += '<thead class="table-light"><tr><th>Plan</th><th>Licenses</th><th>Unit price</th><th>Subtotal</th><th>GST</th><th>Line total</th><th>Subscription</th></tr></thead><tbody>';
                items.forEach(function (row) {
                    html += '<tr>';
                    html += '<td>' + escHtml(row.plan_name || (row.plan && row.plan.name) || '—') + '</td>';
                    html += '<td>' + escHtml(row.licenses) + '</td>';
                    html += '<td>' + fmtMoney(row.price_per_unit) + '</td>';
                    html += '<td>' + fmtMoney(row.subtotal) + '</td>';
                    html += '<td>' + fmtMoney(row.tax_amount) + '</td>';
                    html += '<td>' + fmtMoney(row.total_amount) + '</td>';
                    html += '<td class="small">' + escHtml(row.subscription_start_date || row.created_at || '—');
                    if (row.end_date || row.subscription_end_date) {
                        html += ' → ' + escHtml(row.end_date || row.subscription_end_date);
                    }
                    html += '</td></tr>';
                });
                html += '</tbody></table></div>';

                html += '<div class="row justify-content-end"><div class="col-md-5">';
                html += '<table class="table table-sm mb-0">';
                html += '<tr><td>Discount</td><td class="text-end">' + fmtMoney(cart.discount_amount) + '</td></tr>';
                html += '<tr><td>GST (' + escHtml(cart.gst_percentage) + '%)</td><td class="text-end">' + fmtMoney(cart.gst_amount) + '</td></tr>';
                html += '<tr><th>Grand total</th><th class="text-end">' + fmtMoney(cart.grand_total) + '</th></tr>';
                html += '<tr><td>Wallet balance</td><td class="text-end">' + fmtMoney(cart.wallet_balance) + '</td></tr>';
                html += '<tr><td>Wallet used</td><td class="text-end">' + fmtMoney(cart.wallet_used) + '</td></tr>';
                html += '<tr><td>Payable after wallet</td><td class="text-end">' + fmtMoney(cart.payable_after_wallet) + '</td></tr>';
                html += '</table></div></div>';

                return html;
            }

            $(document).on('click', '.btn-partner-cart', function () {
                var partnerId = $(this).data('partner-id');
                var partnerName = $(this).data('partner-name') || 'Partner';
                $('#partnerCartModalLabel').text('Cart — ' + partnerName);
                $('#partnerCartBody').html('<div class="text-center text-muted py-4">Loading cart…</div>');
                bootstrap.Modal.getOrCreateInstance(document.getElementById('partnerCartModal')).show();
                $.get(partnerCartUrl + '/' + partnerId)
                    .done(function (res) {
                        $('#partnerCartBody').html(renderPartnerCart(res));
                    })
                    .fail(function (xhr) {
                        var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Could not load cart.';
                        $('#partnerCartBody').html('<div class="alert alert-danger mb-0">' + escHtml(msg) + '</div>');
                    });
            });
        });
    </script>
@endpush
