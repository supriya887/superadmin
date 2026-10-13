@extends('superadmin.layouts.app')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-12">
        <br>
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0">{{ $title ?? 'Role' }}</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="{{ url('superadmin/dashboard') }}">Admin</a></li>
                    <li class="breadcrumb-item active">{{ $title ?? 'Role' }}</li>
                </ol>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Roles</h5>
                <a href="{{ url('superadmin/add-authority') }}" class="btn btn-primary btn-sm">
                    <i class="ti ti-plus me-1"></i> Add Role
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle" id="authorityTable">
                        <thead>
                            <tr>
                                <th style="width: 80px">Sr. No.</th>
                                <th>Role Name</th>
                                <th class="text-center" style="width: 140px">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($result as $index => $item)
                                @php $row = (object) $item; @endphp
                                <tr id="row-{{ $row->id }}">
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $row->role_name ?? ($row->rolevalue ?? '-') }}</td>
                                    <td class="text-center">
                                        <div class="d-inline-flex align-items-center justify-content-center gap-1">
                                            <a href="{{ url('superadmin/add-authority/' . $row->id) }}"
                                               class="btn btn-light btn-icon btn-sm rounded-circle"
                                               title="Edit"
                                               data-bs-toggle="tooltip">
                                                <i class="ti ti-pencil fs-lg"></i>
                                            </a>
                                            <button type="button"
                                                    class="btn btn-light btn-icon btn-sm rounded-circle delete-authority"
                                                    data-id="{{ $row->id }}"
                                                    title="Delete"
                                                    data-bs-toggle="tooltip">
                                                <i class="ti ti-trash fs-lg"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted">No roles found.</td>
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
        if ($('#authorityTable').length) {
            $('#authorityTable').DataTable({
                order: [[0, 'asc']]
            });
        }

        $(document).on('click', '.delete-authority', function () {
            var id = $(this).data('id');
            var row = $('#row-' + id);
            var token = $('input[name="_token"]').val();

            alertify.confirm('Confirm Deletion', 'Are you sure you want to delete this role?',
                function () {
                    $.ajax({
                        url: "{{ url('superadmin/delete-authority') }}",
                        type: 'POST',
                        data: {
                            _token: token,
                            id: id
                        },
                        success: function (response) {
                            if (response && response.success === false) {
                                alertify.error(response.message || 'Unable to delete role.');
                                return;
                            }
                            alertify.success((response && response.message) ? response.message : 'Role deleted successfully.');
                            row.fadeOut(300, function () {
                                $(this).remove();
                            });
                        },
                        error: function (xhr) {
                            var msg = (xhr.responseJSON && xhr.responseJSON.message)
                                ? xhr.responseJSON.message
                                : 'Unable to delete role.';
                            alertify.error(msg);
                        }
                    });
                },
                function () {}
            ).set('labels', { ok: 'Yes', cancel: 'No' });
        });
    });
</script>
@endpush
