@extends('superadmin.layouts.app')

@section('content')
<style>
   
</style>
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card mt-2 mt-3">
                <div class="card-header">
                    <h3 class="card-title">Customer Users</h3> {{-- Reverted title --}}
                    <div class="card-tools">
                        <a href="{{ url('superadmin/b2c-customers-add') }}" class="btn btn-primary btn-sm">Add New Customer</a> {{-- Reverted button text --}}
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
                                    <label for="search_gstin">GSTIN</label>
                                    <input class="form-control" type="text" name="search_gstin" id="search_gstin" value="">
                                </div>
                            </div>
                            <div class="col-lg-3 mt-2">
                                <div class="form-group">
                                    <label for="search_company_name">Company Name</label>
                                    <input class="form-control" type="text" name="search_company_name" id="search_company_name" value="">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-12 mt-2">
                                <div class="form-group text-center">
                                    @csrf
                                    <button class="btn btn-secondary w-md" type="submit" id="searchSubmit">
                                        <div class="spinner-border spinner-border-sm float-left mt-1" id="spinner" style="display:none" role="status">
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
            <div class="card mt-2 mb-2">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-custom table-centered table-select table-hover w-100 mb-0" id="customerUsersTable">
                            <thead class="bg-light align-middle bg-opacity-25 thead-sm">
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Mobile</th>
                                    <th>GSTIN</th>
                                    <th>Company Name</th>
                                    <th>Status</th>
                                    <th class="text-center" style="width:20%">Action</th>
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
@endsection

@push('scripts')
<script>
    // $(document).ready(function() {
    //     var dataTable = $('#customerUsersTable').DataTable({
    //         processing: true,
    //         serverSide: true,
    //         ajax: {
    //             url: "{{ url('superadmin/b2c-customers-list-table') }}",
    //             type: "POST",
    //             data: function (d) {
    //                 d._token = $('meta[name="csrf-token"]').attr('content');
    //                 d.search_name = $('#search_name').val();
    //                 d.search_email = $('#search_email').val();
    //                 d.search_mobile = $('#search_mobile').val();
    //                 d.search_gstin = $('#search_gstin').val();
    //                 d.search_company_name = $('#search_company_name').val();
    //             }
    //         },
    //         columns: [
    //             {data: 'id', name: 'id'},
    //             {data: 'name', name: 'name'},
    //             {data: 'email', name: 'email'},
    //             {data: 'mobile', name: 'mobile'},
    //             {data: 'gstin', name: 'gstin'},
    //             {data: 'company_name', name: 'company_name'},
    //             {data: 'status', name: 'status'},
    //             {data: 'action', name: 'action', orderable: false, searchable: false},
    //         ],
    //         dom: "lBfrtip",
    //         buttons: [
    //             { extend: "excel", exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] } },
    //             { extend: "print", exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] } },
    //             { extend: "pdf", exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] } },
    //         ],
    //     });

    //     $('#searchForm').on('submit', function(e) {
    //         e.preventDefault();
    //         $('#searchSubmit').attr('disabled', true);
    //         $('#spinner').show();
    //         dataTable.ajax.reload(function() {
    //             $('#searchSubmit').attr('disabled', false);
    //             $('#spinner').hide();
    //         });
    //     });

    //     $('#resetSearch').on('click', function() {
    //         $('#searchForm')[0].reset();
    //         dataTable.ajax.reload();
    //     });

    // });
</script>
@endpush