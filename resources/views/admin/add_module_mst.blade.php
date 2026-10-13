@extends('superadmin.layouts.app')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-12">
        <br>
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0">{{ $title ?? 'Add Module Master' }}</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="{{ url('superadmin/dashboard') }}">Admin</a></li>
                    <li class="breadcrumb-item"><a href="{{ url('superadmin/module-mst-table') }}">Module Master</a></li>
                    <li class="breadcrumb-item active">{{ $title ?? 'Add Module' }}</li>
                </ol>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Module Details</h5>
            </div>
            <div class="card-body">
                <form id="form">
                    @csrf
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <label class="form-label">Module Name <span class="text-danger">*</span></label>
                            <input class="form-control" type="text" name="module_name" id="module_name"
                                   value="{{ $update->module_name ?? '' }}">
                            <div class="text-danger fs-xs" id="error-module_name"></div>
                        </div>

                        <div class="col-lg-12 text-center mt-4">
                            <input type="hidden" name="id" id="id" value="{{ $update->id ?? '' }}">
                            <a href="{{ url('superadmin/module-mst-table') }}"
                               class="btn btn-sm rounded-pill px-3 py-1 me-2"
                               style="background:#1e58b4;color:#fff;">
                                Back
                            </a>
                            <button type="submit"
                                    class="btn btn-sm rounded-pill px-3 py-1"
                                    style="background:#1e58b4;color:#fff;"
                                    id="submit">
                                <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true" id="spinner" style="display: none;"></span>
                                Save Module
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function () {
        $('#form').on('submit', function (e) {
            e.preventDefault();
            var formData = new FormData(this);
            $('#submit').attr('disabled', true);
            $('#spinner').show();
            $('#error-module_name').text('');
            $('#module_name').removeClass('is-invalid');

            $.ajax({
                url: "{{ url('superadmin/module-mst-validation') }}",
                type: "POST",
                data: formData,
                processData: false,
                contentType: false,
                success: function (data) {
                    $('#submit').attr('disabled', false);
                    $('#spinner').hide();
                    if (data.error) {
                        if (data.module_name) {
                            $('#error-module_name').text(data.module_name);
                            $('#module_name').addClass('is-invalid');
                        }
                    } else {
                        alertify.success(data.success || 'Module saved successfully.');
                        setTimeout(function () {
                            window.location.href = "{{ url('superadmin/module-mst-table') }}";
                        }, 800);
                    }
                },
                error: function (xhr) {
                    $('#submit').attr('disabled', false);
                    $('#spinner').hide();
                    var msg = (xhr.responseJSON && xhr.responseJSON.module_name)
                        ? xhr.responseJSON.module_name
                        : 'Unable to save module.';
                    $('#error-module_name').text(msg);
                    $('#module_name').addClass('is-invalid');
                }
            });
        });
    });
</script>
@endpush
