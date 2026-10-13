@extends('superadmin.layouts.app')

@section('content')
<style>
    
</style>
    <div class="container-fluid mt-2">
        {{-- <div class="row g-3 mb-3">
            <div class="col-md-8">

                <div class="row g-2">
                    <div class="col-3">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body">
                                <div class="text-muted small">Total Partners</div>
                                <div class="fs-5 fw-semibold">{{ $overallPartnerCount }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body">
                                <div class="text-muted small">Active Partners</div>
                                <div class="fs-5 fw-semibold text-success">{{ $approvedCount }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-body">
                                <div class="text-muted small">Suspended / Rejected</div>
                                <div class="fs-5 fw-semibold text-danger">{{ $rejectedCount }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-xl-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <h4 class="card-title mb-0">Bar Gradient Chart</h4>
                        <form method="get" class="d-flex align-items-center gap-2 mb-0">
                            <input type="hidden" name="tab" value="{{ $activeTab }}">
                            <label class="mb-0 small text-muted">View</label>
                            <select class="form-select form-select-sm" style="width:auto" name="trend"
                                onchange="this.form.submit()">
                                <option value="week" {{ $trendGranularity === 'week' ? 'selected' : '' }}>Weekly</option>
                                <option value="month" {{ $trendGranularity === 'month' ? 'selected' : '' }}>Monthly
                                </option>
                            </select>
                        </form>
                    </div>
                    <div class="card-body">
                        <div id="chart-bar-gradient" style="min-height: 300px"></div>
                    </div>
                </div>
            </div>
        </div> --}}

        <div class="card shadow-sm border-0">
            {{-- <div class="card-body pt-2">
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a href="#pending-pane" data-bs-toggle="tab" aria-expanded="{{ $activeTab === 'pending' ? 'true' : 'false' }}"
                            class="nav-link {{ $activeTab === 'pending' ? 'active' : '' }}" aria-selected="{{ $activeTab === 'pending' ? 'true' : 'false' }}"
                            role="tab">Pending Requests</a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a href="#approved-pane" data-bs-toggle="tab" aria-expanded="{{ $activeTab === 'approved' ? 'true' : 'false' }}"
                            class="nav-link {{ $activeTab === 'approved' ? 'active' : '' }}" aria-selected="{{ $activeTab === 'approved' ? 'true' : 'false' }}"
                            role="tab" tabindex="-1">Approved Partners</a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a href="#all-pane" data-bs-toggle="tab" aria-expanded="{{ $activeTab === 'all' ? 'true' : 'false' }}"
                            class="nav-link {{ $activeTab === 'all' ? 'active' : '' }}" aria-selected="{{ $activeTab === 'all' ? 'true' : 'false' }}"
                            role="tab" tabindex="-1">All Partners</a>
                    </li>
                </ul>
            </div> --}}
            <div class="card-body p-0">
                <div class="tab-content">
                    <div class="tab-pane fade {{ $activeTab === 'pending' ? 'show active' : '' }}" id="pending-pane" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover table-nowrap mb-0 align-middle js-partner-requests-table" data-tab="pending">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Partner name</th>
                                        <th>Company</th>
                                        <th>Email</th>
                                        <th>Mobile</th>
                                        <th>Status</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade {{ $activeTab === 'approved' ? 'show active' : '' }}" id="approved-pane" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover table-nowrap mb-0 align-middle js-partner-requests-table" data-tab="approved">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Partner name</th>
                                        <th>Company</th>
                                        <th>Email</th>
                                        <th>Mobile</th>
                                        <th>Status</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade {{ $activeTab === 'all' ? 'show active' : '' }}" id="all-pane" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover table-nowrap mb-0 align-middle js-partner-requests-table" data-tab="all">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Partner name</th>
                                        <th>Company</th>
                                        <th>Email</th>
                                        <th>Mobile</th>
                                        <th>Status</th>
                                        <th class="text-end">Action</th>
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
    </div>
@endsection

@push('scripts')
    <!-- E Charts js -->
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.5.1/dist/echarts.min.js"></script>

    <!-- E Charts Demo js -->
    <script>
        (function() {
            var partnerListTableUrl = @json(route('superadmin.partner_requests.list_table'));
            if (typeof $ !== 'undefined' && $.fn.DataTable) {
                $('.js-partner-requests-table').each(function () {
                    var $table = $(this);
                    $table.DataTable({
                        processing: true,
                        serverSide: true,
                        pageLength: 10,
                        ajax: {
                            url: partnerListTableUrl,
                            type: 'POST',
                            data: function (d) {
                                d._token = $('meta[name="csrf-token"]').attr('content');
                                d.tab = $table.data('tab');
                            }
                        },
                        columns: [
                            { data: 'created_at', name: 'created_at' },
                            { data: 'partner_name', name: 'name' },
                            { data: 'company_name', name: 'company_name' },
                            { data: 'email', name: 'email' },
                            { data: 'mobile', name: 'mobile' },
                            { data: 'status', name: 'status', orderable: false, searchable: false },
                            { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                        ],
                        columnDefs: [{ targets: '_all', defaultContent: '-' }]
                    });
                });

                $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function () {
                    $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
                });
            }

            var el = document.getElementById('chart-bar-gradient');
            if (!el || typeof echarts === 'undefined') return;

            var labels = @json($chartLabels);
            var values = @json($chartValues);
            var chart = echarts.init(el);
            chart.setOption({
                tooltip: {
                    trigger: 'axis'
                },
                grid: {
                    left: 40,
                    right: 20,
                    top: 20,
                    bottom: 35
                },
                xAxis: {
                    type: 'category',
                    data: labels,
                    axisTick: {
                        alignWithLabel: true
                    }
                },
                yAxis: {
                    type: 'value',
                    minInterval: 1
                },
                series: [{
                    name: 'Registrations',
                    type: 'bar',
                    data: values,
                    barMaxWidth: 28,
                    itemStyle: {
                        borderRadius: [6, 6, 0, 0],
                        color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{
                                offset: 0,
                                color: '#14d39a'
                            },
                            {
                                offset: 1,
                                color: '#0d6efd'
                            }
                        ])
                    }
                }]
            });

            window.addEventListener('resize', function() {
                chart.resize();
            });
        })();
    </script>
@endpush
