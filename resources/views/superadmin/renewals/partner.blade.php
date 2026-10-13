@extends('superadmin.layouts.app')

@section('content')
<div class="container-fluid mt-2">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h4 class="mb-0">Renewal Center - {{ $partner->name }}</h4>
        <a href="{{ route('superadmin.renewals.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body border-bottom">
            <div class="row g-2">
                <div class="col-md-3">
                    <input type="text" id="filterCustomerName" class="form-control form-control-sm" placeholder="Customer name">
                </div>
                <div class="col-md-3">
                    <input type="text" id="filterDomain" class="form-control form-control-sm" placeholder="Domain">
                </div>
                <div class="col-md-2">
                    <input type="number" id="filterDaysLeft" class="form-control form-control-sm" placeholder="Days left">
                </div>
                <div class="col-md-2">
                    <input type="date" id="filterRenewalDate" class="form-control form-control-sm">
                </div>
                <div class="col-md-2">
                    <button type="button" id="clearFilters" class="btn btn-sm btn-light w-100">Clear</button>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle" id="renewalsPartnerTable">
                    <thead class="table-light">
                        <tr>
                            <th>Customer</th>
                            <th>Email</th>
                            <th class="text-center">Domains</th>
                            <th>Domain List</th>
                            <th>Plan</th>
                            <th class="text-center">Renewal Due</th>
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
</div>
@endsection

@push('scripts')
<script>
    (function () {
        if (typeof $ === 'undefined' || !$.fn.DataTable) return;
        var table = $('#renewalsPartnerTable').DataTable({
            processing: true,
            serverSide: true,
            pageLength: 10,
            ajax: {
                url: @json($renewalsTableUrl),
                data: function (d) {
                    d.filter_customer_name = $('#filterCustomerName').val();
                    d.filter_domain = $('#filterDomain').val();
                    d.filter_days_left = $('#filterDaysLeft').val();
                    d.filter_renewal_date = $('#filterRenewalDate').val();
                }
            },
            columns: [
                { data: 'customer_name', name: 'customer_name' },
                { data: 'email', name: 'email' },
                { data: 'domain_count', name: 'domain_count', className: 'text-center' },
                { data: 'domain', name: 'domain' },
                { data: 'plan', name: 'plan' },
                { data: 'renewal_date_display', name: 'renewal_date', className: 'text-center' },
                { data: 'days_left', name: 'days_left', className: 'text-center' },
                { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
            ]
        });

        $('#filterCustomerName,#filterDomain,#filterDaysLeft,#filterRenewalDate').on('keyup change', function () {
            table.ajax.reload();
        });
        $('#clearFilters').on('click', function () {
            $('#filterCustomerName,#filterDomain,#filterDaysLeft,#filterRenewalDate').val('');
            table.ajax.reload();
        });
    })();
</script>
@endpush
