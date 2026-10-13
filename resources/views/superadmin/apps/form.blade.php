@extends('superadmin.layouts.app')

@section('content')
    <div class="main-container container-fluid">

        <div class="breadcrumb-header justify-content-between">
            <div>
                <br>
                <br>
                <h4 class="content-title mb-2">{{ $title }}</h4>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('superadmin.dashboard') }}">Super Admin</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('superadmin.apps.index') }}">Popular Apps/Services</a>
                        </li>
                        <li class="breadcrumb-item active">{{ $pageTitle }}</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex my-auto mb-2">
                <a href="{{ route('superadmin.apps.index') }}" class="btn btn-secondary me-2">
                    <i class="mdi mdi-arrow-left me-1"></i> Back to List
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <form id="appForm">
                    @csrf
                    <input type="hidden" name="id" value="{{ isset($update) ? $update->id : '' }}">

                    <div class="row ">
                        <div class="col-md-4 mb-3"></div>
                        <div class="col-md-4 mb-3">
                            <div class="form-group mb-3">
                                <label class="form-label">App Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" id="name"
                                    value="{{ isset($update) ? $update->name : '' }}" required>
                                <div class="text-danger small" id="error-name"></div>
                            </div>

                            <div class="form-group mb-3">
                                <label class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-control" name="status" id="status" required data-choices
                                    search-false>
                                    <option value="active"
                                        {{ isset($update) && $update->status == 'active' ? 'selected' : '' }}>Active
                                    </option>
                                    <option value="inactive"
                                        {{ isset($update) && $update->status == 'inactive' ? 'selected' : '' }}>Inactive
                                    </option>
                                </select>
                                <div class="text-danger small" id="error-status"></div>
                            </div>

                            <div class="form-group mb-3">
                                <label class="form-label">App Icon <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" name="image" id="image"
                                    {{ isset($update) ? '' : 'required' }}>
                                <div class="text-danger small" id="error-image"></div>
                                @if (isset($update) && $update->image)
                                    <div class="mt-2">
                                        <label class="d-block text-muted small">Current Icon:</label>
                                        <img src="{{ $update->image_url }}"
                                            class="avatar-sm rounded bg-light border shadow-sm" alt="{{ $update->name }}"
                                            width="64"
                                            onerror="this.style.display='none'">
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-4 mb-3"></div>
                    </div>
                    <div class="mt-4 text-center">
                        <button type="submit" class="btn btn-primary" id="saveAppBtn">
                            Save App/Service
                            <div class="spinner-border spinner-border-sm" role="status" style="display:none;"
                                id="appSpinner"></div>
                        </button>
                        <a href="{{ route('superadmin.apps.index') }}" class="btn btn-secondary ms-2">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            select2NoSearch('status', 'Select Status');
            // The data-choices attribute handles auto-init for Choices.js
            // If Select2 is needed, use: select2NoSearch('status', 'Select Status');
            $('#appForm').on('submit', function(e) {
                e.preventDefault();
                $('#saveAppBtn').attr('disabled', true);
                $('#appSpinner').show();
                $('#appForm .text-danger').html('');
                $('#appForm .form-control').removeClass('border-danger');

                $.ajax({
                    url: '{{ route('superadmin.apps.save') }}',
                    type: 'POST',
                    data: new FormData(this),
                    contentType: false,
                    cache: false,
                    processData: false,
                    dataType: 'json',
                    success: function(response) {
                        $('#saveAppBtn').attr('disabled', false);
                        $('#appSpinner').hide();

                        if (response.error) {
                            $.each(response, function(key, value) {
                                if (value && key != 'error') {
                                    $('#error-' + key).html(value);
                                    $('[name="' + key + '"]').addClass('border-danger');
                                }
                            });
                        } else if (response.success) {
                            alertify.success(response.success);
                            setTimeout(function() {
                                window.location.href =
                                    "{{ route('superadmin.apps.index') }}";
                            }, 1000);
                        }
                    },
                    error: function(xhr) {
                        $('#saveAppBtn').attr('disabled', false);
                        $('#appSpinner').hide();
                        alertify.error('An error occurred while saving.');
                    }
                });
            });
        });
    </script>
@endpush
