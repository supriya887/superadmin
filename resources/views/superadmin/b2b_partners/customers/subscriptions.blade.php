@extends('superadmin.layouts.app')

@section('content')
    <div class="container-fluid mt-2">
        <div class="row">
            <div class="col-12">
                <div class="card mb-2">
                    <div class="card-header py-2">
                        <h3 class="card-title">Customer Details for {{ $customer->name }}</h3>
                        <div class="card-tools">
                            <a href="{{ route('superadmin.b2b_partners.customers.list', ['partner_id' => $encryptedPartnerId]) }}"
                                class="btn btn-sm btn-primary rounded-pill mb-1 px-4">Back to Customers</a>
                        </div>
                    </div>
                    <div class="card-body py-2">
                        <div class="row">
                            <div class="col-md-3 mb-2"><strong>Customer ID:</strong> {{ $customer->id }}</div>
                            <div class="col-md-3 mb-2"><strong>Name:</strong> {{ $customer->name ?: '—' }}</div>
                            <div class="col-md-3 mb-2"><strong>Email:</strong> {{ $customer->email ?: '—' }}</div>
                            <div class="col-md-3 mb-2"><strong>Mobile:</strong> {{ $customer->mobile ?: '—' }}</div>
                            <div class="col-md-3 mb-2"><strong>Company:</strong> {{ $customer->company_name ?: '—' }}</div>
                            <div class="col-md-3 mb-2"><strong>GSTIN:</strong> {{ $customer->gstin ?: '—' }}</div>
                            <div class="col-md-3 mb-2"><strong>PAN:</strong> {{ $customer->pan_no ?: '—' }}</div>
                            <div class="col-md-3 mb-2"><strong>Legal Name:</strong> {{ $customer->legal_name ?: '—' }}</div>
                            <div class="col-md-3 mb-2"><strong>Trade Name:</strong> {{ $customer->trade_name ?: '—' }}</div>
                            <div class="col-md-3 mb-2"><strong>City:</strong> {{ $customer->city ?: '—' }}</div>
                            <div class="col-md-3 mb-2"><strong>State:</strong> {{ $customer->state ?: '—' }}</div>
                            <div class="col-md-3 mb-2"><strong>Country:</strong> {{ $customer->country ?: '—' }}</div>
                            <div class="col-md-3 mb-2"><strong>Pincode:</strong> {{ $customer->pincode ?: $customer->zip_code ?: '—' }}</div>
                            <div class="col-md-3 mb-2"><strong>Postcode:</strong> {{ $customer->postcode ?: '—' }}</div>
                            <div class="col-md-3 mb-2"><strong>GST Status:</strong> {{ $customer->gst_status ?: '—' }}</div>
                            <div class="col-md-3 mb-2"><strong>Constitution:</strong> {{ $customer->constitution ?: '—' }}</div>
                            <div class="col-md-3 mb-2"><strong>Registration:</strong> {{ $customer->registration_date ?: '—' }}</div>
                            <div class="col-md-3 mb-2"><strong>Last Updated:</strong> {{ $customer->last_updated ?: '—' }}</div>
                            <div class="col-md-3 mb-2"><strong>Status:</strong>
                                @if ($customer->status == 1)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-danger">Inactive</span>
                                @endif
                            </div>
                            <div class="col-md-12 mb-2"><strong>Address:</strong> {{ $customer->address ?: $customer->company_address ?: '—' }}</div>
                            <div class="col-md-12 mb-0"><strong>Credit:</strong> ₹{{ number_format($creditUsed ?? 0, 2) }} / ₹{{ number_format((float) ($partner->credit_limit ?? 0), 2) }}
                                @if(($creditAvailable ?? 0) < 1000 && (float) ($partner->credit_limit ?? 0) > 0)
                                    <span class="badge bg-warning text-dark ms-2">Low Credit</span>
                                @endif
                            </div>
                            <div class="col-md-12 mb-2"><strong>Nature of Business:</strong> {{ $customer->nature_of_business ?: '—' }}</div>

                        </div>
                    </div>
                </div>
            </div>  
        </div>

        <div class="card mb-2">
            <div class="card-header py-2">
                <h5 class="card-title mb-0">Orders</h5>
            </div>
            <div class="card-body pb-2">
                <div class="row g-2 mb-3">
                    <div class="col-md-2">
                        <input type="text" id="filterOrderId" class="form-control form-control-sm" placeholder="Order ID">
                    </div>
                    <div class="col-md-2">
                        <input type="text" id="filterDomain" class="form-control form-control-sm" placeholder="Domain">
                    </div>
                    <div class="col-md-2">
                        <input type="text" id="filterPlan" class="form-control form-control-sm" placeholder="Plan">
                    </div>
                    <div class="col-md-2">
                        <select id="filterStatus" class="form-select form-select-sm">
                            <option value="">All Status</option>
                            <option value="completed">Completed</option>
                            <option value="active">Active</option>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select id="filterCategory" class="form-select form-select-sm">
                            <option value="">All Categories</option>
                            <option value="new">New</option>
                            <option value="renewal">Renewal</option>
                            <option value="upgrade">Upgrade</option>
                            <option value="downgrade">Downgrade</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select id="filterSort" class="form-select form-select-sm">
                            <option value="created_at_desc">Newest First</option>
                            <option value="created_at_asc">Oldest First</option>
                            <option value="id_desc">Order ID (High → Low)</option>
                            <option value="id_asc">Order ID (Low → High)</option>
                            <option value="total_amount_desc">Amount (High → Low)</option>
                            <option value="total_amount_asc">Amount (Low → High)</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" id="filterFromDate" class="form-control form-control-sm" placeholder="From Date">
                    </div>
                    <div class="col-md-2">
                        <input type="date" id="filterToDate" class="form-control form-control-sm" placeholder="To Date">
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="button" id="applyOrderFilters" class="btn btn-sm btn-primary w-100">Apply</button>
                        <button type="button" id="clearOrderFilters" class="btn btn-sm btn-light w-100">Clear</button>
                    </div>
                </div>
            </div>
            <div class="card-body p-0 pt-0">
                <div class="table-responsive">
                    <table id="customerSubscriptionsTable" class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Order ID</th>
                                <th>Domain</th>
                                <th>Plan</th>
                                <th>Category</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
<!-- Transfer Code Modal -->
<div class="modal fade" id="transferCodeModal" tabindex="-1" aria-labelledby="transferCodeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-2">
                <h5 class="modal-title fw-bold" id="transferCodeModalLabel">Transfer Your Google Workspace Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-0">
                <div class="row g-0 border">
                    <div class="col-md-6 p-4 border-end">
                        <h6 class="fw-bold mb-2">Copy Identifier</h6>
                        <p class="mb-2 text-muted">Use this identifier to initiate the transfer from your existing admin panel.</p>
                        <div class="input-group mb-4">
                            <input type="text" class="form-control" id="transferCodeDisplay" readonly>
                            <button class="btn btn-light copy-transfer-code" title="Copy to clipboard" type="button">
                                <i class="ti ti-copy"></i>
                            </button>
                        </div>

                        <h6 class="fw-bold mb-2">Enter your domain name</h6>
                        <input type="text" class="form-control mb-4" id="transferRequestDomain" placeholder="example.com">

                        <button id="sendTransferRequestBtn" type="button" class="btn btn-sm btn-primary rounded-pill mb-1 px-4 me-2" style="">
                            Transfer My Account
                        </button>
                    </div>
                    <div class="col-md-6 p-4">
                        <h3 class="fw-bold mb-3">How does it work?</h3>
                        <p class="mb-3 text-muted">We move all your email accounts from the old provider to us by keeping the data intact.</p>
                        <p class="mb-3 text-muted">The admin account details to manage your Google account remain the same.</p>
                        <p class="mb-4 text-muted"><strong>Note:</strong> Your existing tenure with the other provider, if any does not get moved to us.</p>
                        <h4 class="fw-bold mb-2">Have more doubts?</h4>
                        <a href="#" class="fw-bold text-decoration-none" style="color:#f3412c;">Contact our support team</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

{{-- Upgrade/downgrade: JSON from route('superadmin.b2b_partners.customers.subscription_plan_modal'); orders must have order_type = 'b2b'. --}}
<!-- Upgrade/Downgrade Plan Modal -->
<div class="modal fade" id="planModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content p-3 border-0 shadow" style="border-radius: 12px;">
            <div class="modal-header border-0 pb-0 justify-content-end">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4">
                <h6 class="fw-bold text-dark mb-1">UPGRADE/DOWNGRADE PLAN</h6>
                <p class="text-muted small mb-4">For : <span id="modalPlanDomainName" class="fw-bold"></span></p>

                <div class="row g-4">
                    <div class="col-md-6 border-end">
                        <div class="position-relative border rounded p-3 mb-4 bg-white shadow-sm">
                            <span class="badge bg-primary position-absolute top-0 start-0 translate-middle-y ms-3"></span>
                            <div class="d-flex align-items-center mt-2">
                                <div id="currentPlanAvatar" class="bg-light rounded-circle border p-2 me-3 d-flex align-items-center justify-content-center" style="width:40px; height:40px; overflow:hidden;">
                                    <span class="text-success fw-bold">Y</span>
                                </div>
                                <div>
                                    <div class="fw-bold small" id="currentPlanTitle"></div>
                                    <div class="text-muted small" id="currentPlanPrice"></div>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-6">
                                <label class="text-muted d-block small">Remaining Days</label>
                                <span class="fw-bold fs-5" id="remainingDays"></span>
                            </div>
                            <div class="col-6">
                                <label class="text-muted d-block small">Remaining Value</label>
                                <span class="fw-bold fs-5" id="remainingValue"></span>
                            </div>
                        </div>

                        <div class="alert alert-light border-0 p-0">
                            <small class="text-muted">
                                <strong>Note :</strong> The above remaining value will be adjusted in your updated plan pricing, while billing for the remaining days.
                            </small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="position-relative border rounded overflow-hidden shadow-sm">
                            <span class="badge bg-info position-absolute top-0 start-0 translate-middle-y ms-3 z-index-1" style="">Select New Plan</span>
                            
                            <div class="p-3 bg-white border-bottom mt-2">
                                <div class="input-group input-group-sm border rounded">
                                    <input type="text" id="planSearch" class="form-control border-0" placeholder="Search Plan">
                                    <span class="input-group-text bg-white border-0"><i class="bi bi-search"></i></span>
                                </div>
                            </div>

                            <div class="plan-list overflow-auto" id="planContainer" style="max-height: 250px;">
                                <!-- Plan items will be loaded here via AJAX -->
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-5 mb-3">
                    <button id="confirmBtn" class="btn btn-primary px-5 rounded-pill fw-bold" disabled>
                        Confirm & Proceed to Payment
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    var planModalProviderIcons = {
        1: @json(asset('public/home/images/tizzy.png')),
        2: @json(asset('public/home/images/microsoft.png')),
        3: @json(asset('public/home/images/google.png'))
    };
    var subscriptionsTableUrl = @json($subscriptionsTableUrl);
    var planModalDataUrl = @json(route('superadmin.b2b_partners.customers.subscription_plan_modal', ['partner_id' => '__PARTNER__', 'subscription_id' => '__SUB__']));
    var transferRequestStoreUrl = @json(url('superadmin.domain_transfer_requests.store'));

    $(document).ready(function() {
        if (typeof $.fn.DataTable !== 'undefined') {
            var sortMap = {
                created_at_desc: [[7, 'desc']],
                created_at_asc: [[7, 'asc']],
                id_desc: [[0, 'desc']],
                id_asc: [[0, 'asc']],
                total_amount_desc: [[5, 'desc']],
                total_amount_asc: [[5, 'asc']],
            };

            var ordersTable = $('#customerSubscriptionsTable').DataTable({
                processing: true,
                serverSide: true,
                pageLength: 10,
                ajax: {
                    url: subscriptionsTableUrl,
                    type: 'POST',
                    data: function (d) {
                        d._token = $('meta[name="csrf-token"]').attr('content');
                        d.filter_order_id = $('#filterOrderId').val();
                        d.filter_domain = $('#filterDomain').val();
                        d.filter_plan = $('#filterPlan').val();
                        d.filter_status = $('#filterStatus').val();
                        d.filter_category = $('#filterCategory').val();
                        d.filter_from_date = $('#filterFromDate').val();
                        d.filter_to_date = $('#filterToDate').val();
                    }
                },
                order: [[7, 'desc']],
                columns: [
                    { data: 'id', name: 'id' },
                    { data: 'domain_name', name: 'domain_name' },
                    { data: 'plan_name', name: 'plan_name', orderable: false },
                    { data: 'order_category', name: 'order_category' },
                    { data: 'quantity', name: 'quantity', className: 'text-center' },
                    { data: 'amount', name: 'total_amount', className: 'text-end' },
                    { data: 'status', name: 'status', orderable: false, searchable: false },
                    { data: 'created_at', name: 'created_at' },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                ],
                columnDefs: [{ targets: '_all', defaultContent: '-' }]
            });

            $('#applyOrderFilters').on('click', function () {
                var sortKey = $('#filterSort').val() || 'created_at_desc';
                ordersTable.order(sortMap[sortKey] || [[7, 'desc']]).draw();
            });

            $('#clearOrderFilters').on('click', function () {
                $('#filterOrderId,#filterDomain,#filterPlan,#filterFromDate,#filterToDate').val('');
                $('#filterStatus,#filterCategory').val('');
                $('#filterSort').val('created_at_desc');
                ordersTable.order([[7, 'desc']]).draw();
            });

            $('#filterSort').on('change', function () {
                var sortKey = $(this).val() || 'created_at_desc';
                ordersTable.order(sortMap[sortKey] || [[7, 'desc']]).draw();
            });
        }

        $('.transfer-code-btn').on('click', function() {
            var domainName = $(this).data('domain-name');
            $('#transferRequestDomain').val(domainName || '');
            $('#transferCodeDisplay').val('Loading...');
            $('#transferCodeModal').modal('show');

            $.ajax({
                url: '{{ route('superadmin.get_transfer_code') }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    domain_name: domainName
                },
                success: function(response) {
                    if (response.success) {
                        $('#transferCodeDisplay').val(response.transfer_code);
                    } else {
                        $('#transferCodeDisplay').val(response.message || 'Error fetching code.');
                    }
                },
                error: function(xhr) {
                    console.error("Error fetching transfer code:", xhr);
                    $('#transferCodeDisplay').val('Error: Could not retrieve code.');
                }
            });
        });

        // Copy to clipboard functionality
        $('.copy-transfer-code').on('click', function() {
            var transferCode = $('#transferCodeDisplay').val();
            navigator.clipboard.writeText(transferCode).then(function() {
                // Optional: Show a tooltip or alert that the text has been copied
                alertify.success('Transfer Code copied to clipboard!');
            }).catch(function(err) {
                console.error('Could not copy text: ', err);
                alertify.error('Could not copy text: ' + err);
            });
        });

        $('#sendTransferRequestBtn').on('click', function() {
            var domainName = ($('#transferRequestDomain').val() || '').trim();
            var transferCode = ($('#transferCodeDisplay').val() || '').trim();
            if (!domainName) {
                alertify.error('Please enter your domain name.');
                $('#transferRequestDomain').focus();
                return;
            }
            if (!transferCode || transferCode.toLowerCase().indexOf('error') === 0 || transferCode.toLowerCase() === 'loading...') {
                alertify.error('Transfer code is not ready yet. Please try again.');
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true).text('Sending Request...');
            $.ajax({
                url: transferRequestStoreUrl,
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    domain_name: domainName,
                    transfer_code: transferCode,
                    requested_by_type: 'customer',
                    requested_by_id: '{{ $customer->id }}'
                },
                success: function(res) {
                    if (res.success) {
                        alertify.success(res.message || 'Request sent successfully.');
                        $('#transferCodeModal').modal('hide');
                    } else {
                        alertify.error(res.message || 'Unable to send request.');
                    }
                },
                error: function(xhr) {
                    var msg = (xhr.responseJSON && (xhr.responseJSON.message || (xhr.responseJSON.errors && Object.values(xhr.responseJSON.errors)[0][0])))
                        ? (xhr.responseJSON.message || Object.values(xhr.responseJSON.errors)[0][0])
                        : 'Unable to send request.';
                    alertify.error(msg);
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Transfer My Account');
                }
            });
        });

        // Handle Renew button click
        $('.renew-btn').on('click', function() {
            var partnerId = $(this).data('partner-id');
            var subscriptionId = $(this).data('subscription-id');
            var url = '{{ route('superadmin.b2b_partners.customers.update_plan', ['partner_id' => '__partnerId__', 'subscription_id' => '__subscriptionId__']) }}';
            url = url.replace('__partnerId__', partnerId);
            url = url.replace('__subscriptionId__', subscriptionId);
            window.location.href = url;
        });

        // Handle Upgrade/Downgrade — load from API then open modal
        $('.upgrade-downgrade-btn').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var partnerId = $btn.data('partner-id');
            var subscriptionId = $btn.data('subscription-id');
            var direction = $btn.data('direction') || 'upgrade';
            var url = planModalDataUrl.replace('__PARTNER__', encodeURIComponent(partnerId)).replace('__SUB__', encodeURIComponent(subscriptionId)) + '?direction=' + encodeURIComponent(direction);

            $('#planContainer').html('<p class="text-muted small p-3 mb-0">Loading plans…</p>');
            $('#confirmBtn').prop('disabled', true);
            $('#planSearch').val('');
            $('#planModal').modal('show');

            $.getJSON(url)
                .done(function(res) {
                    if (!res.success) {
                        $('#planContainer').html('<p class="text-danger small p-3 mb-0">' + (res.message || 'Unable to load.') + '</p>');
                        return;
                    }
                    $('#modalPlanDomainName').text(res.domain_name || '—');
                    $('#currentPlanTitle').text(res.current_plan.title || '');
                    $('#currentPlanPrice').text(res.current_plan.price_label || '');
                    $('#remainingDays').text(res.remaining_days);
                    $('#remainingValue').text('₹' + res.remaining_value_formatted);

                    var pid = parseInt(res.current_plan.provider_id, 10);
                    var iconSrc = planModalProviderIcons[pid];
                    var $av = $('#currentPlanAvatar');
                    $av.empty();
                    if (iconSrc) {
                        $av.append($('<img>', { src: iconSrc, alt: '', class: 'avatar-img rounded-circle', css: { width: '32px', height: '32px', objectFit: 'cover' } }));
                    } else {
                        $av.append($('<span>', { class: 'text-success fw-bold', text: 'Y' }));
                    }

                    $('#planContainer').empty();
                    if (!res.plans || !res.plans.length) {
                        $('#planContainer').html('<p class="text-muted small p-3 mb-0">No alternate plans available.</p>');
                        return;
                    }
                    $.each(res.plans, function(i, plan) {
                        var $row = $('<div>', { class: 'plan-item p-3 border-bottom d-flex align-items-center', role: 'button', 'data-plan-id': plan.id });
                        var $iconWrap = $('<div>', { class: 'border rounded-circle p-2 me-3 bg-light d-flex align-items-center justify-content-center', css: { width: '40px', height: '40px', overflow: 'hidden' } });
                        if (planModalProviderIcons[pid]) {
                            $iconWrap.append($('<img>', { src: planModalProviderIcons[pid], alt: '', css: { width: '24px', height: '24px', objectFit: 'contain' } }));
                        } else {
                            $iconWrap.append($('<span>', { class: 'text-success fw-bold small', text: 'Y' }));
                        }
                        var $txt = $('<div>');
                        $txt.append($('<div>', { class: 'fw-bold small', text: plan.name }));
                        $txt.append($('<div>', { class: 'text-muted small', text: plan.price_label }));
                        $row.append($iconWrap, $txt);
                        $('#planContainer').append($row);
                    });

                    $('#planContainer').off('click', '.plan-item').on('click', '.plan-item', function() {
                        $('#planContainer .plan-item').removeClass('active-plan');
                        $(this).addClass('active-plan');
                        $('#confirmBtn').prop('disabled', false);
                    });
                })
                .fail(function(xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Could not load plan data.';
                    $('#planContainer').html('<p class="text-danger small p-3 mb-0">' + msg + '</p>');
                });
        });

        // Search Functionality
        $('#planSearch').on('keyup', function() {
            const value = $(this).val().toLowerCase();
            $('.plan-item').each(function() {
                const text = $(this).text().toLowerCase();
                $(this).toggle(text.includes(value));
            });
        });

        // Confirm Click
        $('#confirmBtn').on('click', function() {
            const selected = $('#planContainer .active-plan .fw-bold').text();
            alertify.success('You have selected: ' + selected + '. Redirecting to payment...');
            // TODO: Implement actual redirection or further processing
            $('#planModal').modal('hide');
        });
    });
</script>
@endpush
