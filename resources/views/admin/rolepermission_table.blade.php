@extends('superadmin.layouts.app')

@section('content')
@php
    $moduleId = (int) ($id ?? 0);
    $parentName = $parent_module->module_name ?? ('Module #' . $moduleId);
@endphp
<div class="row g-3 mb-4">
    <div class="col-12">
        <br>
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0">{{ $parentName }} — Permissions</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="{{ url('superadmin/dashboard') }}">Admin</a></li>
                    <li class="breadcrumb-item"><a href="{{ url('superadmin/module-mst-table') }}">Module Master</a></li>
                    <li class="breadcrumb-item active">{{ $parentName }}</li>
                </ol>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">{{ $parentName }}</h5>
                <div class="d-flex gap-2">
                    <a href="{{ url('superadmin/module-mst-table') }}" class="btn btn-sm rounded-pill px-3 py-1" style="background:#1e58b4;color:#fff;">
                        <i class="ti ti-arrow-left me-1"></i> Back
                    </a>
                    <a href="{{ url('superadmin/add-rolepermission/' . $moduleId) }}" class="btn btn-primary btn-sm">
                        <i class="ti ti-plus me-1"></i> Add Permission
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle" id="rolePermissionTable">
                        <thead>
                            <tr>
                                <th style="width: 80px">Sr. No.</th>
                                <th>Permission Name</th>
                                <th>Permissions</th>
                                <th>Status</th>
                                <th class="text-center" style="width: 140px">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($module as $index => $item)
                                @php
                                    $row = (object) $item;
                                    $rowId = (int) ($row->id ?? 0);
                                    $status = (int) ($row->status ?? 0);
                                    $perms = $row->permissions ?? '';
                                    if (is_string($perms) && str_starts_with(trim($perms), '[')) {
                                        $decoded = json_decode($perms, true);
                                        if (is_array($decoded)) {
                                            $perms = implode(', ', $decoded);
                                        }
                                    } elseif (is_array($perms)) {
                                        $perms = implode(', ', $perms);
                                    }
                                @endphp
                                <tr id="row-{{ $rowId }}">
                                    <td>{{ $row->id }}</td>
                                    <td>{{ $row->modules ?? '-' }}</td>
                                    <td>{{ $perms !== '' ? $perms : '-' }}</td>
                                    <td>
                                        @if ($status === 1)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-danger">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <div class="d-inline-flex align-items-center justify-content-center gap-1">
                                            <a href="{{ url('superadmin/add-rolepermission/' . $moduleId . '/' . $rowId) }}"
                                               class="btn btn-light btn-icon btn-sm rounded-circle"
                                               title="Edit"
                                               data-bs-toggle="tooltip">
                                                <i class="ti ti-pencil fs-lg"></i>
                                            </a>
                                            <button type="button"
                                                    class="btn btn-light btn-icon btn-sm rounded-circle delete-rolepermission"
                                                    id="{{ $rowId }}"
                                                    title="Delete"
                                                    data-bs-toggle="tooltip">
                                                <i class="ti ti-trash fs-lg"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted">
                                        No role permissions found.
                                        <a href="{{ url('superadmin/add-rolepermission/' . $moduleId) }}" class="ms-1">Add Permission</a>
                                    </td>
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
        if ($('#rolePermissionTable').length) {
            $('#rolePermissionTable').DataTable({
                order: [[0, 'asc']]
            });
        }

        $(document).on('click', '.delete-rolepermission', function () {
            var id = $(this).attr('id');
            var row = $('#row-' + id);
            var token = $('input[name="_token"]').val();

            alertify.confirm('Confirm Deletion', 'Are you sure you want to delete this role permission?',
                function () {
                    $.ajax({
                        url: "{{ url('superadmin/delete-rolepermission') }}",
                        type: 'POST',
                        data: {
                            _token: token,
                            id: id
                        },
                        success: function () {
                            alertify.success('Role permission deleted successfully.');
                            row.fadeOut(300, function () {
                                $(this).remove();
                            });
                        },
                        error: function () {
                            alertify.error('Unable to delete role permission.');
                        }
                    });
                },
                function () {}
            ).set('labels', { ok: 'Yes', cancel: 'No' });
        });
    });
</script>
@endpush
