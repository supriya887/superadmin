@extends('superadmin.layouts.app')

@section('content')

<div class="container-fluid mt-2">

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            {{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="tab-content">
                <div class="tab-pane fade show active" id="all-pane" role="tabpanel">
                    <div class="table-responsive">
                        <table  class="table table-hover table-nowrap align-middle js-purchase-requests-table" data-tab="all">
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
                            <tbody>
                               
                           
                            </tbody>
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
            if (typeof $ !== 'undefined' && $.fn.DataTable) {

                $('.js-purchase-requests-table').each(function () {
                    var $table = $(this);
                    $table.DataTable({
                        processing: true,
                        serverSide: true,
                        pageLength: 10,
                        ajax: {
                            url: '{{ url('superadmin/purchase-requests-list-table') }}',
                            type: 'POST',
                            data: function (d) {
                                d._token = $('meta[name="csrf-token"]').attr('content');
                                d.tab = $table.data('tab');
                            }
                        },
                        columns: [
                            { data: 'created_at', name: 'created_at' },
                            { data: 'partner.name', name: 'partner.name' },
                            { data: 'partner.company_name', name: 'partner.company_name' },
                            { data: 'partner.email', name: 'partner.email' },
                            { data: 'partner.mobile', name: 'partner.mobile' },
                            { data: 'status', name: 'status' },
                            { data: 'action', name: 'action', className: 'text-end' }
                        ],
                        columnDefs: [{ targets: '_all', defaultContent: '-' }]
                    });
                });
            }
        })();
   
    </script>
@endpush
