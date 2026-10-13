@extends('superadmin.layouts.app')

@section('content')
@php
    $mid = (int) ($mid ?? 0);
    $update = isset($update) ? (object) $update : null;
    $parentName = optional($parent_module)->module_name ?? ('Module #' . $mid);

    $permissionsValue = '';
    if ($update && isset($update->permissions) && $update->permissions !== null && $update->permissions !== '') {
        $raw = $update->permissions;
        if (is_string($raw) && str_starts_with(trim($raw), '[')) {
            $decoded = json_decode($raw, true);
            $permissionsValue = is_array($decoded) ? implode(', ', $decoded) : (string) $raw;
        } elseif (is_array($raw)) {
            $permissionsValue = implode(', ', $raw);
        } else {
            $permissionsValue = (string) $raw;
        }
        // Do not force / keep "View Invoice" as a selectable preset.
        $permissionsValue = trim(str_ireplace(['View Invoice', 'view_invoice'], '', $permissionsValue));
        $permissionsValue = preg_replace('/\s*,\s*,+/', ', ', $permissionsValue);
        $permissionsValue = trim($permissionsValue, " ,");
    }
@endphp

<div class="row g-3 mb-4">
    <div class="col-12">
        <br>
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0">{{ $title ?? 'Add Role permission' }}</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="{{ url('superadmin/dashboard') }}">Admin</a></li>
                    <li class="breadcrumb-item"><a href="{{ url('superadmin/module-mst-table') }}">Module Master</a></li>
                    <li class="breadcrumb-item"><a href="{{ url('superadmin/rolepermission-table/' . $mid) }}">{{ $parentName }}</a></li>
                    <li class="breadcrumb-item active">{{ $title ?? 'Permission' }}</li>
                </ol>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Permission Details — {{ $parentName }}</h5>
            </div>
            <div class="card-body">
                <form id="rolePermissionForm" autocomplete="off">
                    @csrf
                    <input type="hidden" name="id" id="id" value="{{ $update->id ?? '' }}">
                    <input type="hidden" name="mst_id" id="mst_id" value="{{ $update->mst_id ?? $mid }}">

                    <div class="row g-3">
                        <div class="col-lg-6">
                            <label class="form-label" for="modules">Permission Name <span class="text-danger">*</span></label>
                            <input class="form-control" type="text" name="modules" id="modules"
                                   value="{{ $update->modules ?? '' }}"
                                   placeholder="Type here">
                            <div class="text-danger fs-xs" id="error-modules"></div>
                        </div>

                        <div class="col-lg-6">
                            <label class="form-label" for="status">Status <span class="text-danger">*</span></label>
                            <select name="user_status" id="user_status" class="form-control">
                                <option value="1" @selected((string) ($update->status ?? '1') === '1')>Active</option>
                                <option value="0" @selected((string) ($update->status ?? '') === '0')>Inactive</option>
                            </select>
                            <div class="text-danger fs-xs" id="error-user_status"></div>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="permissions">Permissions <span class="text-danger">*</span></label>
                            <input class="form-control"
                                   type="text"
                                   name="permissions"
                                   id="permissions"
                                   value="{{ $permissionsValue }}"
                                   placeholder="Type here (e.g. View, Add, Edit, Delete)">
                            <small class="text-muted">Enter permissions separated by comma. Do not use View Invoice.</small>
                            <div class="text-danger fs-xs" id="error-permissions"></div>
                            <div class="text-danger fs-xs" id="error-mst_id"></div>
                        </div>

                        <div class="col-12 text-center mt-4">
                            <a href="{{ url('superadmin/rolepermission-table/' . $mid) }}"
                               class="btn btn-sm rounded-pill px-3 py-1 me-2"
                               style="background:#1e58b4;color:#fff;">
                                Back
                            </a>
                            <button type="submit"
                                    class="btn btn-sm rounded-pill px-3 py-1"
                                    style="background:#1e58b4;color:#fff;"
                                    id="submitBtn">
                                <span class="spinner-border spinner-border-sm me-1" id="spinner" style="display:none;"></span>
                                Save Permission
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
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        // Keep native status dropdown visible (avoid select2 hiding it).
        if (window.jQuery && $('#status').hasClass('select2-hidden-accessible')) {
            $('#status').select2('destroy');
        }

        var form = document.getElementById('rolePermissionForm');
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var perms = (document.getElementById('permissions').value || '').trim();
            // Strip View Invoice if typed accidentally.
            perms = perms.replace(/view\s*invoice/ig, '').replace(/,+/g, ',').replace(/^,|,$/g, '').trim();
            document.getElementById('permissions').value = perms;

            if (!perms) {
                document.getElementById('error-permissions').textContent = 'Please type permissions here.';
                return;
            }

            var formData = new FormData(form);
            var btn = document.getElementById('submitBtn');
            var spinner = document.getElementById('spinner');
            btn.disabled = true;
            spinner.style.display = 'inline-block';

            ['modules', 'permissions', 'status', 'mst_id'].forEach(function (k) {
                var err = document.getElementById('error-' + k);
                if (err) err.textContent = '';
            });

            $.ajax({
                url: "{{ url('superadmin/rolepermission-validation') }}",
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (data) {
                    btn.disabled = false;
                    spinner.style.display = 'none';
                    if (data.error) {
                        Object.keys(data).forEach(function (key) {
                            if (key === 'error' || !data[key]) return;
                            var err = document.getElementById('error-' + key);
                            if (err) err.textContent = data[key];
                        });
                        return;
                    }
                    alertify.success(data.success || 'Saved successfully.');
                    setTimeout(function () {
                        window.location.href = "{{ url('superadmin/rolepermission-table/' . $mid) }}";
                    }, 800);
                },
                error: function (xhr) {
                    btn.disabled = false;
                    spinner.style.display = 'none';
                    var msg = (xhr.responseJSON && (xhr.responseJSON.modules || xhr.responseJSON.message))
                        ? (xhr.responseJSON.modules || xhr.responseJSON.message)
                        : 'Unable to save permission.';
                    alertify.error(msg);
                }
            });
        });
    });
})();
</script>
@endpush
