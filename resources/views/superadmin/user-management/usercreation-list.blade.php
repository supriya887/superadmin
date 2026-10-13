@extends('superadmin.layouts.app')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-12">
        <br>
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0">{{ $title }}</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="{{ url('superadmin/dashboard') }}">Admin</a></li>
                    <li class="breadcrumb-item active">{{ $title }}</li>
                </ol>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Users</h5>
                <a href="{{ url('superadmin/add-user-creation') }}" class="btn btn-primary btn-sm">
                    <i class="ti ti-plus me-1"></i> Add User
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle" id="userTable">
                        <thead>
                            <tr>
                                <th style="width: 80px">Sr. No.</th>
                                <th>User Name</th>
                                <th>Status</th>
                                <th>Role</th>
                                <th class="text-center" style="width: 140px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($users as $index => $row)
                                <tr id="row-{{ $row->id }}">
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $row->user_name }}</td>
                                    <td>
                                        @if ((int) $row->status === 1)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-danger">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $role = DB::connection('pgsql_admin')
                                                ->table('admin_user_role')
                                                ->where('id', $row->role)
                                                ->first();
                                        @endphp 
                                        {{ $role->role_name }}
                                    </td>
                                    <td class="text-center">
                                        <div class="d-inline-flex align-items-center justify-content-center gap-1">
                                            <a href="{{ url('superadmin/add-user-creation/' . $row->id) }}"
                                               class="btn btn-light btn-icon btn-sm rounded-circle"
                                               title="Edit User"
                                               data-bs-toggle="tooltip">
                                                <i class="ti ti-pencil fs-lg"></i>
                                            </a>
                                            <button type="button"
                                                    class="btn btn-light btn-icon btn-sm rounded-circle delete"
                                                    id="{{ $row->id }}"
                                                    title="Delete User"
                                                    data-bs-toggle="tooltip">
                                                <i class="ti ti-trash fs-lg"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted">No users found.</td>
                                </tr>
                            @endforelse
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
    $(document).ready(function () {
        if ($('#userTable').length) {
            $('#userTable').DataTable({
                order: [[0, 'asc']]
            });
        }

        $(document).on('click', '.delete', function () {
            var id = $(this).attr('id');
            var row = $('#row-' + id);
            var token = $('input[name="_token"]').val();

            alertify.confirm('Confirm Deletion', 'Are you sure you want to delete this user?',
                function () {
                    $.ajax({
                        url: "{{ url('superadmin/delete-usercreation') }}",
                        type: 'POST',
                        data: {
                            _token: token,
                            id: id
                        },
                        success: function () {
                            alertify.success('User deleted successfully.');
                            row.fadeOut(300, function () {
                                $(this).remove();
                            });
                        },
                        error: function () {
                            alertify.error('Unable to delete user.');
                        }
                    });
                },
                function () {}
            ).set('labels', { ok: 'Yes', cancel: 'No' });
        });
    });
</script>
@endpush
