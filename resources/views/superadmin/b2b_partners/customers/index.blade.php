@extends('superadmin.layouts.app')

@section('content')
    <style>
        #partnerCustomersTable_wrapper .dataTables_length,
        #partnerCustomersTable_wrapper .dataTables_filter {
            padding: 0.75rem 0 0.25rem;
        }

        #partnerCustomersTable_wrapper .dt-buttons {
            padding: 0.75rem 0 0.25rem;
        }

        #partnerCustomersTable_wrapper .dt-buttons .btn {
            font-size: 0.8125rem;
            padding: 0.35rem 0.85rem;
            border-radius: 999px;
            margin-right: 0.35rem;
        }

        .partner-customers-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            background: #f8fafc;
            border: 1px solid #e9edf3;
            border-radius: 0.75rem;
            margin-bottom: 1rem;
        }

        .partner-customers-toolbar .toolbar-left {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.65rem;
        }

        .partner-customers-toolbar .toolbar-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.75rem;
        }

        .partner-customers-toolbar .toolbar-right {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.75rem;
        }

        .partner-name-strip {
            display: flex;
            align-items: center;
            gap: 0.875rem;
            width: 100%;
            margin: 0 0 1rem;
            padding: 0.9rem 1.15rem;
            background: linear-gradient(135deg, #1e58b4 0%, #2563eb 100%);
            border-radius: 0.75rem;
            color: #fff;
            box-shadow: 0 4px 14px rgba(30, 88, 180, 0.2);
        }

        .partner-name-strip-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.16);
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .partner-name-strip-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            opacity: 0.88;
            font-weight: 600;
            line-height: 1.2;
        }

        .partner-name-strip-value {
            font-size: 1.35rem;
            font-weight: 700;
            line-height: 1.2;
            letter-spacing: 0.01em;
        }

        .partner-name-strip-meta {
            margin-left: auto;
            font-size: 0.8125rem;
            opacity: 0.92;
            text-align: right;
        }

        #partnerCustomersTable thead th {
            white-space: nowrap;
            font-size: 0.8125rem;
        }

        #partnerCustomersTable td {
            vertical-align: middle;
            font-size: 0.875rem;
        }

        .partner-customers-filter-label {
            font-size: 0.75rem;
            color: #64748b;
            margin-bottom: 0.25rem;
        }
    </style>

    <div class="container-fluid mt-2">
        <div class="row">
            <div class="col-12">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <div>
                        <h4 class="mb-1">Partner Customers</h4>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-0 small">
                                <li class="breadcrumb-item"><a href="{{ route('superadmin.b2b_partners.list') }}">B2B Partners</a></li>
                                <li class="breadcrumb-item active" aria-current="page">{{ format_display_name($partner->name, 'Partner') }}</li>
                            </ol>
                        </nav>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('superadmin.b2b_partners.list') }}" class="btn btn-sm btn-light rounded-pill px-3">Back to Partners</a>
                        @if (($partner->status ?? '') === 'approved')
                            <a href="{{ route('superadmin.b2b_partners.customers.add', ['partner_id' => $encryptedPartnerId]) }}"
                                class="btn btn-sm btn-primary rounded-pill px-3">+ Add Customer</a>
                        @else
                            <button type="button" class="btn btn-sm btn-secondary rounded-pill px-3" disabled
                                title="Partner must be approved to add customers">
                                + Add Customer
                            </button>
                        @endif
                    </div>
                </div>

                @if (($partner->status ?? '') !== 'approved')
                    <div class="alert alert-warning mb-3">
                        This partner is currently <strong>{{ ucfirst((string) $partner->status) }}</strong>.
                        Approve the partner first to add new customers.
                    </div>
                @endif

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">Search Filters</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3 col-lg-2">
                                <div class="partner-customers-filter-label">Customer Name</div>
                                <input type="text" id="filterName" class="form-control form-control-sm" placeholder="Search name">
                            </div>
                            <div class="col-md-3 col-lg-2">
                                <div class="partner-customers-filter-label">Domain</div>
                                <input type="text" id="filterDomain" class="form-control form-control-sm" placeholder="example.com">
                            </div>
                            <div class="col-md-3 col-lg-2">
                                <div class="partner-customers-filter-label">Email</div>
                                <input type="text" id="filterEmail" class="form-control form-control-sm" placeholder="email@domain.com">
                            </div>
                            <div class="col-md-3 col-lg-2">
                                <div class="partner-customers-filter-label">Mobile</div>
                                <input type="text" id="filterMobile" class="form-control form-control-sm" placeholder="Mobile number">
                            </div>
                            <div class="col-md-3 col-lg-2">
                                <div class="partner-customers-filter-label">GSTIN</div>
                                <input type="text" id="filterGstin" class="form-control form-control-sm" placeholder="GSTIN">
                            </div>
                            <div class="col-md-3 col-lg-2">
                                <div class="partner-customers-filter-label">Status</div>
                                <select id="filterStatus" class="form-select form-select-sm">
                                    <option value="">All Status</option>
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-lg-2">
                                <div class="partner-customers-filter-label">From Date</div>
                                <input type="date" id="filterFromDate" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-3 col-lg-2">
                                <div class="partner-customers-filter-label">To Date</div>
                                <input type="date" id="filterToDate" class="form-control form-control-sm">
                            </div>
                            
                        </div>
                        <div class="row justify-content-center d-flex">
                            <div class="col-md-2 d-flex gap-2">
                                <button type="button" id="applyFilters" class="btn btn-sm btn-primary w-100">Apply</button>
                                <button type="button" id="clearFilters" class="btn btn-sm btn-light w-100">Clear</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">Customer List</h5>
                    </div>
                    <div class="card-body pt-0">
                        <div id="partnerCustomersToolbarHost" class="partner-customers-toolbar">
                            <div class="toolbar-left">
                                <div class="toolbar-row">
                                    <div id="partnerCustomersLengthHost"></div>
                                    <div id="partnerCustomersButtonsHost"></div>
                                </div>
                            </div>
                            <div class="toolbar-right">
                                <div id="partnerCustomersFilterHost"></div>
                            </div>
                        </div>

                        <div class="partner-name-strip">
                            <div class="partner-name-strip-icon">
                                <i class="ti ti-building-store"></i>
                            </div>
                            <div>
                                <div class="partner-name-strip-label">Partner</div>
                                <div class="partner-name-strip-value">{{ format_display_name($partner->name) }}</div>
                            </div>
                            @if (!empty($partner->company_name))
                                <div class="partner-name-strip-meta d-none d-md-block">
                                    {{ format_display_name($partner->company_name) }}
                                </div>
                            @endif
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover table-centered w-100 mb-0" id="partnerCustomersTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Sr. No.</th>
                                        <th>Customer ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Mobile</th>
                                        <th>Company Name</th>
                                        <th>GSTIN</th>
                                        <th>Status</th>
                                        <th class="text-center" style="width: 1%">Action</th>
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
        $(document).ready(function() {
            var dataTable = $('#partnerCustomersTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ url('/superadmin/b2b-partner-customers-list-table/' . $encryptedPartnerId) }}",
                    type: "POST",
                    data: function(d) {
                        d._token = $('meta[name="csrf-token"]').attr('content');
                        d.filter_name = $('#filterName').val();
                        d.filter_domain = $('#filterDomain').val();
                        d.filter_from_date = $('#filterFromDate').val();
                        d.filter_to_date = $('#filterToDate').val();
                        d.filter_mobile = $('#filterMobile').val();
                        d.filter_gstin = $('#filterGstin').val();
                        d.filter_email = $('#filterEmail').val();
                        d.filter_status = $('#filterStatus').val();
                    }
                },
                columns: [{
                        data: 'sr_no',
                        name: 'sr_no',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'customer_no',
                        name: 'customer_no',
                        defaultContent: '—'
                    },
                    {
                        data: 'name',
                        name: 'name'
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
                        name: 'company_name'
                    },
                    {
                        data: 'gstin',
                        name: 'gstin'
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
                        className: 'text-center'
                    }
                ],
                order: [[1, 'asc']],
                dom: "lBfrtip",
                buttons: [{
                        extend: "excel",
                        className: 'btn btn-sm btn-light border',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6, 7]
                        }
                    },
                    {
                        extend: "print",
                        className: 'btn btn-sm btn-light border',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6, 7]
                        }
                    },
                    {
                        extend: "pdf",
                        className: 'btn btn-sm btn-light border',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6, 7]
                        }
                    }
                ],
                initComplete: function() {
                    var $container = $(this.api().table().container());
                    $container.find('.dataTables_length').appendTo('#partnerCustomersLengthHost');
                    $container.find('.dt-buttons').appendTo('#partnerCustomersButtonsHost');
                    $container.find('.dataTables_filter').appendTo('#partnerCustomersFilterHost');
                },
                drawCallback: function() {
                    $('#partnerCustomersToolbarHost .dataTables_length, #partnerCustomersToolbarHost .dt-buttons, #partnerCustomersToolbarHost .dataTables_filter')
                        .show();
                }
            });

            $('#applyFilters').on('click', function() {
                dataTable.ajax.reload();
            });

            $('#clearFilters').on('click', function() {
                $('#filterName,#filterDomain,#filterFromDate,#filterToDate,#filterMobile,#filterGstin,#filterEmail').val('');
                $('#filterStatus').val('');
                dataTable.ajax.reload();
            });

            deleteRowType("customer",
                "{{ route('superadmin.b2b_partners.customers.delete', ['partner_id' => $encryptedPartnerId]) }}",
                "Customer Deleted Successfully.");
        });
    </script>
@endpush
