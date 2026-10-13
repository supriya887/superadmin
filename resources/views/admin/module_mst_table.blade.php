@extends('superadmin.layouts.app')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-12">
        <br>
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0">{{ $title ?? 'Module Master' }}</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="{{ url('superadmin/dashboard') }}">Admin</a></li>
                    <li class="breadcrumb-item active">{{ $title ?? 'Module Master' }}</li>
                </ol>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Modules</h5>
                <a href="{{ url('superadmin/add-module-mst') }}" class="btn btn-primary btn-sm">
                    <i class="ti ti-plus me-1"></i> Add Module
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle" id="moduleMstTable">
                        <thead>
                            <tr>
                                <th style="width: 80px">Sr. No.</th>
                                <th>Module Name</th>
                                <th class="text-center" style="width: 160px">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($result as $index => $item)
                                @php
                                    $row = (object) $item;
                                    $rowId = (int) ($row->id ?? 0);
                                @endphp
                                <tr id="row-{{ $rowId }}">
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $row->module_name ?? '-' }}</td>
                                    <td class="text-center">
                                        <div class="d-inline-flex align-items-center justify-content-center gap-1">
                                            <a href="{{ url('superadmin/rolepermission-table/' . $rowId) }}"
                                               class="btn btn-primary btn-icon btn-sm rounded-circle"
                                               title="View Permissions"
                                               data-bs-toggle="tooltip">
                                                <i class="ti ti-folder fs-lg"></i>
                                            </a>
                                            <a href="{{ url('superadmin/add-module-mst/' . $rowId) }}"
                                               class="btn btn-light btn-icon btn-sm rounded-circle"
                                               title="Edit"
                                               data-bs-toggle="tooltip">
                                                <i class="ti ti-pencil fs-lg"></i>
                                            </a>
                                            <button type="button"
                                                    class="btn btn-light btn-icon btn-sm rounded-circle delete-module"
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
                                    <td colspan="3" class="text-center text-muted">No modules found.</td>
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
        if ($('#moduleMstTable').length) {
            $('#moduleMstTable').DataTable({
                order: [[0, 'asc']]
            });
        }

        $(document).on('click', '.delete-module', function () {
            var id = $(this).attr('id');
            var row = $('#row-' + id);
            var token = $('input[name="_token"]').val();

            alertify.confirm('Confirm Deletion', 'Are you sure you want to delete this module?',
                function () {
                    $.ajax({
                        url: "{{ url('superadmin/delete-module-mst') }}",
                        type: 'POST',
                        data: {
                            _token: token,
                            id: id
                        },
                        success: function () {
                            alertify.success('Module deleted successfully.');
                            row.fadeOut(300, function () {
                                $(this).remove();
                            });
                        },
                        error: function () {
                            alertify.error('Unable to delete module.');
                        }
                    });
                },
                function () {}
            ).set('labels', { ok: 'Yes', cancel: 'No' });
        });
    });
</script>
@endpush
