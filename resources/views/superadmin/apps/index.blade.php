@extends('superadmin.layouts.app')

@section('content')
<div class="main-container container-fluid">

    <div class="breadcrumb-header justify-content-between">
        <div>
            <br>
            <h4 class="content-title mb-2">{{ $title }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('superadmin.dashboard') }}">Super Admin</a></li>
                    <li class="breadcrumb-item active">{{ $pageTitle }}</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex my-auto mb-2">
            <a href="{{ route('superadmin.apps.form') }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i> Add App/Service
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-custom table-centered table-select table-hover w-100 mb-0" id="appsTable">
                    <thead class="bg-light align-middle bg-opacity-25 thead-sm">
                        <tr>
                            <th>ID</th>
                            <th>Icon</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th class="text-center" style="width: 1%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($apps as $app)
                        <tr id="row-{{ $app->id }}">
                            <td>{{ $app->id }}</td>
                            <td>
                                @if($app->image)
                                    <img src="{{ $app->image_url }}" class="avatar-sm rounded shadow-sm" alt="{{ $app->name }}" width="32"
                                        onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name={{ urlencode($app->name) }}&size=32';">
                                @else
                                    <span class="avatar-sm rounded bg-light border d-flex align-items-center justify-content-center text-muted">
                                        <i class="ti ti-question-mark"></i>
                                    </span>
                                @endif
                            </td>
                            <td>{{ $app->name }}</td>
                            <td>
                                @if($app->status == 'active')
                                    <span class="badge bg-success mt-1">Active</span>
                                @else
                                    <span class="badge bg-danger mt-1">Inactive</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="{{ route('superadmin.apps.form', $app->id) }}" class="btn btn-light btn-icon btn-sm rounded-circle" data-bs-toggle="tooltip" title="Edit App">
                                        <i class="ti ti-edit fs-lg"></i>
                                    </a>
                                </div>
                                {{-- <button class="btn btn-sm btn-danger delete-app ms-1" data-id="{{ $app->id }}" data-bs-toggle="tooltip" title="Delete App">
                                    <i class="fas fa-trash"></i>
                                </button> --}}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<script>
    $(document).ready(function() {
        // Handle Delete App
        $('.delete-app').on('click', function () {
            var appId = $(this).data('id');

            alertify.confirm('Confirm Deletion', 'Are you sure you want to delete this app/service?', 
                function() { 
                    $.ajax({
                        url: '{{ route('superadmin.apps.delete') }}', 
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            id: appId
                        },
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                alertify.success(response.success);
                                $('#row-' + appId).fadeOut();
                            } else {
                                alertify.error('Error deleting app.');
                            }
                        },
                        error: function (xhr) {
                            alertify.error('An error occurred.');
                        }
                    });
                },
                function() { }
            ).set('labels', {ok:'Yes', cancel:'No'});
        });
    });
</script>
@endpush
