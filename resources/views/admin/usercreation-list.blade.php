@extends('superadmin.layouts.app')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-12 mt-4">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0">{{ $title }}</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="{{ url('dashboard') }}">Admin</a></li>
                    <li class="breadcrumb-item active">{{ $title }}</li>
                </ol>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Existing Users</h5>
                @if(permission(2, 'Add'))
                <a href="{{ url('superadmin/add-user-creation') }}" class="btn btn-sm rounded-pill px-3 py-1" style="background:#1e58b4;color:#fff;">
                    <i class="ti ti-plus me-1"></i> Add User
                </a>
                @endif
            </div>
            <div class="card-body p-2">
                <div class="table-responsive orders-table-scroll">
                    <table id="user-creation-table" class="table table-custom table-centered table-select table-hover w-100 mb-0">
                        <thead class="bg-light align-middle bg-opacity-25 thead-sm">
                            <tr>
                                <th>Sr. No.</th>
                                <th>User Name</th>
                                <th>Status</th>
                                <th class="text-center" style="width:20%">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $i = 1; @endphp
                            @foreach ($users as $row)
                                @php
                                    $uid = data_get($row, 'id');
                                    $uname = data_get($row, 'user_name');
                                    $ustatus = (int) data_get($row, 'status', 0);
                                @endphp
                                <tr>
                                    <td>{{ $i }}</td>
                                    <td>{{ $uname }}</td>
                                    <td>
                                        @if ($ustatus === 1)
                                            <span class="badge bg-success-subtle text-success">Active</span>
                                        @else
                                            <span class="badge bg-danger-subtle text-danger">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="text-center text-wrap">
                                        <div class="d-inline-flex align-items-center justify-content-center gap-1 flex-wrap">
                                            @if(permission(2, 'Edit'))
                                            <a href="{{ url('add-user-creation/' . $uid) }}"
                                               class="btn btn-light btn-icon btn-sm rounded-circle"
                                               data-bs-toggle="tooltip"
                                               title="Edit">
                                                <i class="ti ti-edit fs-lg"></i>
                                            </a>
                                            @endif
                                            @if(permission(2, 'Delete'))
                                            <button type="button"
                                                class="btn btn-light btn-icon btn-sm rounded-circle delete"
                                                data-id="{{ $uid }}"
                                                data-bs-toggle="tooltip"
                                                title="Delete">
                                                <i class="ti ti-trash fs-lg"></i>
                                            </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @php $i++; @endphp
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@csrf
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            $('#user-creation-table').DataTable({
                order: [[1, 'asc']],
                pageLength: 25,
                autoWidth: false,
                columnDefs: [
                    { orderable: false, targets: [0, 3] }
                ],
            });

            var tooltipList = [].slice.call(document.querySelectorAll('#datatable [data-bs-toggle="tooltip"]'));
            tooltipList.map(function (el) { return new bootstrap.Tooltip(el); });

            $(document).on('click', '.delete', function() {
                var id = $(this).data('id');
                var token = $('input[name="_token"]').val();
                
                alertify.confirm("Are you sure you want to delete this user?", function() {
                    $.ajax({
                        url: "{{ url('delete-usercreation') }}",
                        type: "POST",
                        data: {
                            id: id,
                            _token: token
                        },
                        success: function(data) {
                            alertify.success("User Deleted Successfully.");
                            setTimeout(function() {
                                window.location.reload();
                            }, 1000);
                        }
                    });
                });
            });
        });
    </script>
@endpush
