@extends('superadmin.layouts.app')

@section('content')
    <div class="container-fluid mt-2">
        {{-- <div class="row g-2 mb-3">
            <div class="col-md-8">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted small">Pending renewals</div>
                                <div class="fs-3 fw-semibold">{{ (int) $totalPendingRenewals }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted small">Renewal + upgrade revenue</div>
                                <div class="fs-3 fw-semibold">₹{{ number_format((float) $renewalRevenue, 2) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted small">Conversion rate</div>
                                <div class="fs-3 fw-semibold">{{ number_format((float) $conversionRate, 2) }}%</div>
                                <div class="text-muted small mt-1">Converted: {{ (int) $renewalsCompleted }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Renewal Trends</h5>
                        <form method="get" class="d-flex align-items-center gap-2 mb-0">
                            <label class="small text-muted mb-0">View</label>
                            <select name="trend" class="form-select form-select-sm" style="width:auto;"
                                onchange="this.form.submit()">
                                <option value="week" {{ $trend === 'week' ? 'selected' : '' }}>Weekly</option>
                                <option value="month" {{ $trend === 'month' ? 'selected' : '' }}>Monthly</option>
                                <option value="year" {{ $trend === 'year' ? 'selected' : '' }}>Yearly</option>
                            </select>
                        </form>
                    </div>
                    <div class="card-body" style="height: 320px;">
                        <canvas id="renewalTrendsChart"></canvas>
                    </div>
                </div>
            </div>
        </div> --}}



        <div class="card border-0 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Renewals - Partner Overview</h4>
            </div>
            <div class="card-body border-bottom">
                <div class="row g-2">
                    <div class="col-md-3">
                        <input type="text" id="filterPartner" class="form-control form-control-sm" placeholder="Partner">
                    </div>
                    <div class="col-md-3">
                        <input type="text" id="filterCustomer" class="form-control form-control-sm" placeholder="Customer">
                    </div>
                    <div class="col-md-2">
                        <input type="text" id="filterDomain" class="form-control form-control-sm" placeholder="Domain">
                    </div>
                    <div class="col-md-2">
                        <input type="text" id="filterEmail" class="form-control form-control-sm" placeholder="Email">
                    </div>
                    <div class="col-md-2">
                        <input type="text" id="filterMobile" class="form-control form-control-sm" placeholder="Mobile no.">
                    </div>
                </div>
                <div class="row g-2 mt-1 align-items-end">
                    <div class="col-md-3">
                        <label for="expiringSoonDays" class="form-label small text-muted mb-0">Expires within (days)</label>
                        <input type="number" min="0" step="1" id="expiringSoonDays" class="form-control form-control-sm"
                            value="15" title="Include subscriptions ending on or before today + N days (plus expired)">
                    </div>
                    <div class="col-md-3">
                        <label for="filterDaysLeft" class="form-label small text-muted mb-0">Days left (exact)</label>
                        <input type="number" step="1" id="filterDaysLeft" class="form-control form-control-sm"
                            placeholder="e.g. 7 or -3 (expired)" title="Show only rows with this exact days-left; leave empty for all">
                    </div>
                    <div class="col-md-3">
                        <label for="filterStatus" class="form-label small text-muted mb-0">Status</label>
                        <select id="filterStatus" class="form-select form-select-sm">
                            <option value="">All</option>
                            <option value="Expiring Soon">Expiring Soon</option>
                            <option value="Expired">Expired</option>
                        </select>
                    </div>
                </div>
                <div class="mt-2">
                    <button type="button" id="clearRenewalFilters" class="btn btn-sm btn-light">Clear Filters</button>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive" style="overflow-x: auto;">
                    <table class="table table-hover mb-0 align-middle" id="renewalsIndexTable">
                        <thead class="table-light sticky-top" style="z-index: 2;">
                            <tr>
                                <th>Partner</th>
                                <th>Customer</th>
                                <th>Email</th>
                                <th>Mobile</th>
                                <th>Domain</th>
                                <th class="text-center">Sub. start</th>
                                <th class="text-center">Sub. end</th>
                                <th class="text-center">Renewal Date</th>
                                <th class="text-center">Days Left</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
       
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        (function() {
            var renewalsIndexTableUrl = @json($renewalsIndexTableUrl);
            if (typeof $ !== 'undefined' && $.fn.DataTable) {
                var table = $('#renewalsIndexTable').DataTable({
                    processing: true,
                    serverSide: true,
                    pageLength: 10,
                    ajax: {
                        url: renewalsIndexTableUrl,
                        type: 'GET',
                        data: function (d) {
                            d.filter_partner = $('#filterPartner').val();
                            d.filter_customer = $('#filterCustomer').val();
                            d.filter_domain = $('#filterDomain').val();
                            d.filter_email = $('#filterEmail').val();
                            d.filter_mobile = $('#filterMobile').val();
                            d.filter_status = $('#filterStatus').val();
                            d.expiring_soon_days = $('#expiringSoonDays').val();
                            d.filter_days_left = $('#filterDaysLeft').val();
                        }
                    },
                    columns: [
                        { data: 'partner_name', name: 'partner_name' },
                        { data: 'customer_name', name: 'customer_name' },
                        { data: 'email', name: 'email' },
                        { data: 'mobile', name: 'mobile' },
                        { data: 'domain', name: 'domain' },
                        { data: 'subscription_start_date', name: 'subscription_start_date', className: 'text-center' },
                        { data: 'subscription_end_date', name: 'subscription_end_date', className: 'text-center' },
                        { data: 'renewal_date_display', name: 'renewal_date', className: 'text-center' },
                        { data: 'days_left', name: 'days_left', className: 'text-center' },
                        { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                    ],
                    columnDefs: [{ targets: '_all', defaultContent: '-' }]
                });

                $('#filterPartner,#filterCustomer,#filterDomain,#filterEmail,#filterMobile,#filterStatus,#expiringSoonDays,#filterDaysLeft').on('keyup change', function () {
                    table.ajax.reload();
                });
                $('#clearRenewalFilters').on('click', function () {
                    $('#filterPartner,#filterCustomer,#filterDomain,#filterEmail,#filterMobile,#filterDaysLeft').val('');
                    $('#filterStatus').val('');
                    $('#expiringSoonDays').val('15');
                    table.ajax.reload();
                });
            }

            var el = document.getElementById('renewalTrendsChart');
            if (!el || typeof Chart === 'undefined') return;
            new Chart(el, {
                type: 'bar',
                data: {
                    labels: @json($trendLabels),
                    datasets: [{
                            type: 'line',
                            label: 'Due',
                            data: @json($trendRenewals),
                            borderColor: '#f59f00',
                            backgroundColor: 'rgba(245, 159, 0, 0.25)',
                            tension: 0.25,
                            yAxisID: 'y'
                        },
                        {
                            type: 'bar',
                            label: 'Done',
                            data: @json($trendUpgrades),
                            backgroundColor: 'rgba(13, 110, 253, 0.5)',
                            borderColor: '#0d6efd',
                            borderWidth: 1,
                            yAxisID: 'y'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        })();
    </script>
@endpush
