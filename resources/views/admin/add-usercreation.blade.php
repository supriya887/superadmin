@extends('superadmin.layouts.app')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0">{{ $title }}</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="{{ url('superadmin/dashboard') }}">Admin</a></li>
                    <li class="breadcrumb-item"><a href="{{ url('superadmin/user-creation-list') }}">Users</a></li>
                    <li class="breadcrumb-item active">{{ $title }}</li>
                </ol>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">User Details</h5>
            </div>
            <div class="card-body">
                <form id="form">
                    @csrf
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input class="form-control" type="text" name="user_name" id="user_name" value="{{ $update->user_name ?? '' }}">
                            <div class="text-danger fs-xs" id="error-user_name"></div>
                        </div>

                        <div class="col-lg-6">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input class="form-control" type="password" name="password" id="password" value="{{ $update->password ?? '' }}">
                            <div class="text-danger fs-xs" id="error-password"></div>
                        </div>

                        <div class="col-lg-6">
                            <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <input class="form-control" type="password" name="confirm_password" id="confirm_password" value="{{ $update->password ?? '' }}">
                            <div class="text-danger fs-xs" id="error-confirm_password"></div>
                        </div>

                        <div class="col-lg-6">
                            <div class="d-flex justify-content-between align-items-center">
                            <label class="form-label align-items-center">
                                Role <span class="text-danger">*</span>
                            </label>
                             <a href="{{ url('superadmin/add-authority') }}" class="btn btn-xs btn-soft-primary p-0 px-1" title="Add New Role">
                                <i class="ti ti-plus"></i>
                             </a>
                            </div>
                            <select class="form-control" name="role" id="role">
                                <option value="">Select Role</option>
                                @foreach ($role as $roleRow)
                                    @php $roleItem = (object) $roleRow; @endphp
                                    <option value="{{ $roleItem->id }}" @if(isset($update) && (int) $update->role === (int) $roleItem->id) selected @endif>
                                        {{ $roleItem->role_name ?? ($roleItem->rolevalue ?? '-') }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="text-danger fs-xs" id="error-role"></div>
                        </div>

                        <div class="col-lg-6">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" id="status" class="form-control">
                                <option value="1" @if(isset($update) && $update->status == '1') selected @endif>Active</option>
                                <option value="0" @if(isset($update) && $update->status == '0') selected @endif>Inactive</option>
                            </select>
                            <div class="text-danger fs-xs" id="error-status"></div>
                        </div>

                        <div class="col-lg-12 text-end mt-4">
                            <input type="hidden" name="id" id="id" value="{{ $update->id ?? '' }}">
                            <a href="{{ url('superadmin/user-creation-list') }}" class="btn btn-sm rounded-pill px-3 py-1" style="background:#1e58b4;color:#fff;">Cancel</a>
                            <button type="submit" class="btn btn-sm rounded-pill px-3 py-1" style="background:#1e58b4;color:#fff;" id="submit">
                                <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true" id="spinner" style="display: none;"></span>
                                Save Changes
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
        $(document).ready(function() {
            select2NoSearch('role', 'Select Role');
            select2NoSearch('status', 'Select Status');

            $('#form').on('submit', function(e) {
                e.preventDefault();
                var formData = new FormData(this);
                $('#submit').attr('disabled', true);
                $('#spinner').show();

                $.ajax({
                    url: "{{ url('superadmin/user-validation') }}",
                    type: "POST",
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(data) {
                        $('#submit').attr('disabled', false);
                        $('#spinner').hide();
                        if (data.error) {
                            $.each(data, function(key, value) {
                                if (value) {
                                    $('#error-' + key).text(value);
                                    $('#' + key).addClass('is-invalid');
                                } else {
                                    $('#error-' + key).text('');
                                    $('#' + key).removeClass('is-invalid');
                                }
                            });
                        } else {
                            alertify.success(data.success);
                            setTimeout(function() {
                                window.location.href = "{{ url('superadmin/user-creation-list') }}";
                            }, 1000);
                        }
                    }
                });
            });
        });
    </script>
@endpush
