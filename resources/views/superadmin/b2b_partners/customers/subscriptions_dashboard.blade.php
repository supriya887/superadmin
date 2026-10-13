@extends('superadmin.layouts.app')

@section('content')
<div class="container-fluid mt-2">
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Customer Analytics - {{ $partner->name }}</h4>
                    <a href="{{ route('superadmin.b2b_partners.list') }}" class="btn btn-sm btn-outline-secondary">Back</a>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-4">
                            <div class="border rounded p-2 h-100">
                                <div class="text-muted small">Total Customers</div>
                                <div class="fs-5 fw-semibold">{{ $totalCustomers }}</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded p-2 h-100">
                                <div class="text-muted small">Total Domains</div>
                                <div class="fs-5 fw-semibold">{{ $totalDomains }}</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded p-2 h-100">
                                <div class="text-muted small">Total Orders</div>
                                <div class="fs-5 fw-semibold">{{ $totalOrders }}</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded p-2 h-100">
                                <div class="text-muted small">Revenue Generated</div>
                                <div class="fs-5 fw-semibold">₹{{ number_format($revenueGenerated, 2) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header">
                    <h4 class="card-title mb-0">Customer vs Order Count</h4>
                </div>
                <div class="card-body">
                    <div id="partnerCustomerBarChart" style="min-height: 300px;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header">
            <h4 class="card-title mb-0">Partner Customers</h4>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-nowrap mb-0 align-middle w-100" id="partnerCustomersAnalyticsTable">
                    <thead class="table-light">
                        <tr>
                            <th>Customer Name</th>
                            <th>Email</th>
                            <th>Mobile</th>
                            <th class="text-center">Total Domains</th>
                            <th class="text-center">Total Orders</th>
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
@endsection

@push('scripts')
<script src="{{ asset('public/superadmin/plugins/echarts/echarts.min.js') }}"></script>
<script>
    (function () {
        var el = document.getElementById('partnerCustomerBarChart');
        if (!el || typeof echarts === 'undefined') return;
        var chart = echarts.init(el);
        var labels = @json($chartLabels);
        var values = @json($chartValues);
        chart.setOption({
            color: ['#0d6efd'],
            tooltip: { trigger: 'axis' },
            grid: { left: 40, right: 16, top: 16, bottom: 42 },
            xAxis: {
                type: 'category',
                data: labels,
                axisLabel: { interval: 0, rotate: labels.length > 6 ? 20 : 0 },
                axisTick: { alignWithLabel: true }
            },
            yAxis: { type: 'value', minInterval: 1 },
            series: [{
                name: 'Orders',
                type: 'bar',
                barMaxWidth: 32,
                data: values,
                itemStyle: { borderRadius: [6, 6, 0, 0] }
            }]
        });
        window.addEventListener('resize', function () { chart.resize(); });
    })();

    (function () {
        if (typeof $ === 'undefined' || !$.fn.DataTable) return;
        var tableUrl = @json(route('superadmin.b2b_partners.customers.list_table', ['partner_id' => $encryptedPartnerId]));
        $('#partnerCustomersAnalyticsTable').DataTable({
            processing: true,
            serverSide: true,
            pageLength: 10,
            ajax: {
                url: tableUrl,
                type: 'POST',
                data: function (d) {
                    d._token = $('meta[name="csrf-token"]').attr('content');
                }
            },
            columns: [
                { data: 'name', name: 'name' },
                { data: 'email', name: 'email' },
                { data: 'mobile', name: 'mobile' },
                { data: 'domain_count', name: 'domain_count', className: 'text-center' },
                { data: 'total_orders_count', name: 'total_orders_count', className: 'text-center' },
                { data: 'status', name: 'status', orderable: false, searchable: false },
                { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
            ]
        });
    })();
</script>
@endpush
