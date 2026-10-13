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
                    <li class="breadcrumb-item"><a href="{{ route('superadmin.categories.index') }}">Plan Categories</a></li>
                    <li class="breadcrumb-item active">{{ $pageTitle }}</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex my-auto mb-2">
            <a href="{{ route('superadmin.categories.index') }}" class="btn btn-secondary me-2">
                <i class="mdi mdi-arrow-left me-1"></i> Back to List
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form id="categoryForm">
                @csrf
                <input type="hidden" name="id" value="{{ isset($update) ? $update->id : '' }}">
                
                <div class="row">
                    <div class="col-md-4 mb-3"></div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="form-label">Category Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="name" value="{{ isset($update) ? $update->name : '' }}" required>
                            <div class="text-danger small" id="error-name"></div>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-control" name="status" id="status" required data-choices search-false>
                                <option value="active" {{ (isset($update) && $update->status == 'active') ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ (isset($update) && $update->status == 'inactive') ? 'selected' : '' }}>Inactive</option>
                            </select>
                            <div class="text-danger small" id="error-status"></div>
                        </div>
                    </div>
                    <div class="col-md-4 "></div>
                </div>

                <div class="mt-2 text-center">
                    <button type="submit" class="btn btn-primary" id="saveCategoryBtn">
                        Save Category
                        <div class="spinner-border spinner-border-sm" role="status" style="display:none;" id="categorySpinner"></div>
                    </button>
                    <a href="{{ route('superadmin.categories.index') }}" class="btn btn-secondary ms-2">Cancel</a>
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

        $('#categoryForm').on('submit', function (e) {
            e.preventDefault();
            $('#saveCategoryBtn').attr('disabled', true);
            $('#categorySpinner').show();
            $('#categoryForm .text-danger').html('');
            $('#categoryForm .form-control').removeClass('border-danger');

            $.ajax({
                url: '{{ route('superadmin.categories.save') }}',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function (response) {
                    $('#saveCategoryBtn').attr('disabled', false);
                    $('#categorySpinner').hide();
                    
                    if (response.error) {
                        $.each(response, function (key, value) {
                            if (value && key != 'error') {
                                $('#error-' + key).html(value);
                                $('[name="'+key+'"]').addClass('border-danger');
                            }
                        });
                    } else if (response.success) {
                        alertify.success(response.success);
                        setTimeout(function() {
                            window.location.href = "{{ route('superadmin.categories.index') }}";
                        }, 1000);
                    }
                },
                error: function (xhr) {
                    $('#saveCategoryBtn').attr('disabled', false);
                    $('#categorySpinner').hide();
                    alertify.error('An error occurred while saving.');
                }
            });
        });
    });
</script>
@endpush
