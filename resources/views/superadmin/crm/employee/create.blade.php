@extends('superadmin.layouts.crm')

@section('content')
@php
$roles = $roles ?? [
'super_admin' => 'Super Admin',
'admin' => 'Admin (Admin)',
'manager' => 'Manager',
'executive' => 'Sales Executive',
];

$managers = $managers ?? ['Wilson (Admin)', 'Mittal Solanki', 'Janak Singh'];
@endphp

<div class="crm-emp-shell">
    <!-- Left Sub Navigation -->
    @include('superadmin.crm.partials.employee-navigation', ['active' => 'employee'])

    <!-- Main Content Area -->
    <div>
        <!-- Page Header -->
        <div class="page-header"
            style="margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <div class="breadcrumb welcome-text">
                    <p>
                        <a href="{{ Route::has('crm.dashboard') ? route('crm.dashboard') : '#' }}">Dashboard</a>
                        <span>/</span>
                        <a href="javascript:void(0)">Manage</a> <span>/</span>
                        <a href="javascript:void(0)">Employee Management</a> <span>/</span>
                        <a href="{{ Route::has('crm.employees') ? route('crm.employees') : '#' }}">Employee</a>
                    </p>
                </div>
                <h2>{{ isset($employee) ? 'Edit Employee' : 'Add Employee' }}</h2>
            </div>
            <div class="back">
                <a href="{{ Route::has('crm.employees') ? route('crm.employees') : '#' }}" class="back-btn"
                    style="background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 16px; display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 500; color: #334155; text-decoration: none;">
                    <i data-lucide="arrow-left" style="width: 14px; height: 14px;"></i>
                    <span>Back</span>
                </a>
            </div>
        </div>

        @if (session('error'))
        <div class="alert alert-danger" style="margin-bottom: 16px;">{{ session('error') }}</div>
        @endif

        <!-- Add Employee Card Form -->
        <div class="common-table-card"
            style="padding: 28px 32px; border-radius: 14px; background: #fff; border: 1px solid #edf2f7; box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);">
            <form method="POST"
                action="{{ isset($employee) ? route('crm.employee.update', $employee->id) : (Route::has('crm.employee.store') ? route('crm.employee.store') : '#') }}"
                enctype="multipart/form-data">
                @csrf

                <!-- Account Image Section -->
                <div style="margin-bottom: 24px;">
                    <label
                        style="display: block; font-size: 14px; font-weight: 700; color: #1e293b; margin-bottom: 12px;">Account
                        Image</label>
                    @php
                    $avatarUrl = isset($employee) ? ($employee->avatar_url ?? null) : null;
                    @endphp
                    <div style="display: flex; align-items: center; gap: 16px;">
                        <div style="position: relative; width: 84px; height: 84px; border-radius: 50%; background: #f1f5f9; border: 2px dashed #cbd5e1; display: flex; align-items: center; justify-content: center; overflow: hidden; cursor: pointer;"
                            onclick="document.getElementById('avatarUpload').click();"
                            title="Click to upload profile image">
                            <img id="avatarPreview" src="{{ $avatarUrl ?? '' }}"
                                alt="Profile Image"
                                style="{{ $avatarUrl ? 'display: block;' : 'display: none;' }} width: 100%; height: 100%; object-fit: cover;">
                            <div id="avatarPlaceholder" style="{{ $avatarUrl ? 'display: none;' : 'display: flex;' }} align-items: center; justify-content: center;">
                                <i data-lucide="user" style="width: 40px; height: 40px; color: #94a3b8;"></i>
                            </div>
                            <div style="position: absolute; inset: 0; background: rgba(0,0,0,0.35); display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.2s ease;"
                                onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0'">
                                <i data-lucide="camera" style="width: 22px; height: 22px; color: #ffffff;"></i>
                            </div>
                            <input type="file" id="avatarUpload" name="avatar" accept="image/*" style="display: none;">
                        </div>
                        <div>
                            <button type="button" onclick="document.getElementById('avatarUpload').click();"
                                style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 7px 14px; border-radius: 6px; font-size: 12.5px; font-weight: 600; color: #334155; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                                <i data-lucide="upload" style="width: 14px; height: 14px;"></i>
                                <span>Choose Image</span>
                            </button>
                            <p style="font-size: 11.5px; color: #94a3b8; margin: 4px 0 0 2px;">PNG, JPG, GIF or WEBP up to 5MB</p>
                            @error('avatar')
                            <small style="color: #ef4444; font-size: 12px; display: block; margin-top: 4px;">{{ $message }}</small>
                            @enderror
                            @error('profile_image')
                            <small style="color: #ef4444; font-size: 12px; display: block; margin-top: 4px;">{{ $message }}</small>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Employee Information Section -->
                <h4 style="font-size: 15px; font-weight: 700; color: #1e293b; margin: 0 0 18px 0;">Employee Information
                </h4>

                <!-- 2-Column Form Fields Grid -->
                <div class="crm-emp-fields">

                    <!-- Row 1: Name & Phone Number -->
                    <div class="form-group" style="margin: 0;">
                        <label
                            style="display: block; font-size: 13px; font-weight: 500; color: #475569; margin-bottom: 6px;">Name
                            <span style="color: #ef4444;">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $employee->name ?? '') }}" required
                            style="width: 100%; border: 1px solid #e2e8f0; border-radius: 6px; padding: 9px 14px; font-size: 13px; color: #1e293b; outline: none; box-sizing: border-box;">
                        @error('name')
                        <small style="color: #ef4444; font-size: 12px;">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="form-group" style="margin: 0;">
                        <label
                            style="display: block; font-size: 13px; font-weight: 500; color: #475569; margin-bottom: 6px;">Phone
                            Number <span style="color: #ef4444;">*</span></label>
                        <div
                            style="display: flex; align-items: center; border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; background: #fff;">
                            <span
                                style="padding: 9px 12px; font-size: 13px; color: #64748b; background: #f8fafc; border-right: 1px solid #e2e8f0; white-space: nowrap;">+91</span>
                            <input type="text" name="mobile" value="{{ old('mobile', $employee->mobile ?? '') }}"
                                required
                                style="width: 100%; border: none; padding: 9px 14px; font-size: 13px; color: #1e293b; outline: none; box-sizing: border-box;">
                        </div>
                        @error('mobile')
                        <small style="color: #ef4444; font-size: 12px;">{{ $message }}</small>
                        @enderror
                    </div>

                    <!-- Row 2: Email & UserID -->
                    <div class="form-group" style="margin: 0;">
                        <label
                            style="display: block; font-size: 13px; font-weight: 500; color: #475569; margin-bottom: 6px;">Email
                            <span style="color: #ef4444;">*</span></label>
                        <input type="email" name="email" value="{{ old('email', $employee->email ?? '') }}"
                            required
                            style="width: 100%; border: 1px solid #e2e8f0; border-radius: 6px; padding: 9px 14px; font-size: 13px; color: #1e293b; outline: none; box-sizing: border-box;">
                        @error('email')
                        <small style="color: #ef4444; font-size: 12px;">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="form-group" style="margin: 0;">
                        <label
                            style="display: block; font-size: 13px; font-weight: 500; color: #475569; margin-bottom: 6px;">UserID
                            <span style="color: #ef4444;">*</span></label>
                        <input type="text" name="user_id"
                            value="{{ old('user_id', isset($employee) ? 'EMP' . str_pad($employee->id, 4, '0', STR_PAD_LEFT) : '') }}"
                            style="width: 100%; border: 1px solid #e2e8f0; border-radius: 6px; padding: 9px 14px; font-size: 13px; color: #1e293b; outline: none; box-sizing: border-box;">
                        @error('user_id')
                        <small style="color: #ef4444; font-size: 12px;">{{ $message }}</small>
                        @enderror
                    </div>

                    <!-- Row 3: Password & Role -->
                    <div class="form-group" style="margin: 0;">
                        <label
                            style="display: block; font-size: 13px; font-weight: 500; color: #475569; margin-bottom: 6px;">
                            Password
                            @if (!isset($employee))
                                <span style="color: #ef4444;">*</span>
                            @endif
                        </label>
                    
                        <div style="position: relative;">
                            <input
                                type="password"
                                name="password"
                                id="password"
                                {{ isset($employee) ? '' : 'required' }}
                                placeholder="{{ isset($employee) ? 'Leave blank to keep current password' : '' }}"
                                style="width: 100%; border: 1px solid #e2e8f0; border-radius: 6px; padding: 9px 42px 9px 14px; font-size: 13px; color: #1e293b; outline: none; box-sizing: border-box;">
                    
                            <button
                                type="button"
                                id="togglePassword"
                                onclick="togglePasswordVisibility()"
                                style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); border: none; background: transparent; padding: 4px; cursor: pointer; color: #64748b; display: flex; align-items: center; justify-content: center;">
                                <i data-lucide="eye" id="passwordEye" style="width: 17px; height: 17px;"></i>
                            </button>
                        </div>
                    
                        @error('password')
                            <small style="color: #ef4444; font-size: 12px;">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="form-group" style="margin: 0;">
                        <label
                            style="display: block; font-size: 13px; font-weight: 500; color: #475569; margin-bottom: 6px;">Role
                            <span style="color: #ef4444;">*</span></label>
                        <select name="role" required
                            style="width: 100%; border: 1px solid #e2e8f0; border-radius: 6px; padding: 9px 14px; font-size: 13px; color: #334155; outline: none; background: #fff; box-sizing: border-box;">
                            <option value="">Select Role</option>
                            @foreach ($roles as $val => $label)
                            @php
                            $optVal = is_numeric($val) ? $label : $val;
                            $optLabel = $label;
                            @endphp
                            <option value="{{ $optVal }}"
                                {{ old('role', isset($employee) ? $employee->role : 'admin') === $optVal ? 'selected' : '' }}>
                                {{ $optLabel }}
                            </option>
                            @endforeach
                        </select>
                        @error('role')
                        <small style="color: #ef4444; font-size: 12px;">{{ $message }}</small>
                        @enderror
                    </div>

                    <!-- Row 4: Manager & Max Quot Discount -->
                    <div class="form-group" style="margin: 0;">
                        <label
                            style="display: block; font-size: 13px; font-weight: 500; color: #475569; margin-bottom: 6px;">Manager
                            <span style="color: #ef4444;">*</span></label>
                        <select name="manager"
                            style="width: 100%; border: 1px solid #e2e8f0; border-radius: 6px; padding: 9px 14px; font-size: 13px; color: #334155; outline: none; background: #fff; box-sizing: border-box;">
                            <option value="">Select Manager</option>
                            @foreach ($managers as $manager)
                            <option value="{{ $manager }}"
                                {{ old('manager', isset($employee) ? ($employee->manager ?? '') : 'Wilson (Admin)') === $manager ? 'selected' : '' }}>
                                {{ $manager }}
                            </option>
                            @endforeach
                        </select>
                        @error('manager')
                        <small style="color: #ef4444; font-size: 12px;">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="form-group" style="margin: 0;">
                        <label
                            style="display: block; font-size: 13px; font-weight: 500; color: #475569; margin-bottom: 6px;">Max
                            Quot Discount <span style="color: #ef4444;">*</span></label>
                        <input type="text" name="max_quot_discount"
                            value="{{ old('max_quot_discount', $employee->max_quot_discount ?? ($employee->max_discount ?? ($employee->discount ?? ''))) }}"
                            placeholder="e.g. 10%"
                            style="width: 100%; border: 1px solid #e2e8f0; border-radius: 6px; padding: 9px 14px; font-size: 13px; color: #1e293b; outline: none; box-sizing: border-box;">
                        @error('max_quot_discount')
                        <small style="color: #ef4444; font-size: 12px;">{{ $message }}</small>
                        @enderror
                    </div>

                    <!-- Row 5: Status & JD Lead Assign -->
                    <div class="form-group" style="margin: 0;">
                        <label
                            style="display: block; font-size: 13px; font-weight: 500; color: #475569; margin-bottom: 6px;">Status
                            <span style="color: #ef4444;">*</span>
                        </label>
                        <select name="status"
                            style="width: 100%; border: 1px solid #e2e8f0; border-radius: 6px; padding: 9px 14px; font-size: 13px; color: #334155; outline: none; background: #fff; box-sizing: border-box;">
                            <option value="active" {{ old('status', isset($employee) ? $employee->status : 'active') === 'active' ? 'selected' : '' }}>Active
                            </option>
                            <option value="inactive" {{ old('status', isset($employee) ? $employee->status : 'active') === 'inactive' ? 'selected' : '' }}>Inactive
                            </option>
                        </select>
                        @error('status')
                        <small style="color: #ef4444; font-size: 12px;">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="form-group" style="margin: 0;">
                        @php
                            $jdRaw = strtolower(trim((string) old('jd_lead_assign', $employee->jd_lead_assign ?? ($employee->lead_assign ?? ($employee->leads_assigned ?? 'no')))));
                            $jdYes = in_array($jdRaw, ['yes', '1', 'true', 'on'], true)
                                || (ctype_digit($jdRaw) && (int) $jdRaw > 0);
                        @endphp
                        <label
                            style="display: block; font-size: 13px; font-weight: 500; color: #475569; margin-bottom: 6px;">JD
                            Lead Assign
                        </label>
                        <select name="jd_lead_assign"
                            style="width: 100%; border: 1px solid #e2e8f0; border-radius: 6px; padding: 9px 14px; font-size: 13px; color: #334155; outline: none; background: #fff; box-sizing: border-box;">
                            <option value="yes" {{ $jdYes ? 'selected' : '' }}>Yes</option>
                            <option value="no" {{ !$jdYes ? 'selected' : '' }}>No</option>
                        </select>
                        @error('jd_lead_assign')
                        <small style="color: #ef4444; font-size: 12px;">{{ $message }}</small>
                        @enderror
                    </div>

                </div>

                <!-- Centered Save Button -->
                <div style="text-align: center; margin-top: 32px;">
                    <button type="submit" class="submit-btn"
                        style="background: #0056b3; color: #ffffff; border: none; padding: 10px 48px; border-radius: 20px; font-size: 14px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 12px rgba(0, 86, 179, 0.25);">
                        Save
                    </button>
                </div>

            </form>
        </div>
    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        const avatarUpload = document.getElementById('avatarUpload');
        const avatarPreview = document.getElementById('avatarPreview');
        const avatarPlaceholder = document.getElementById('avatarPlaceholder');

        if (avatarUpload && avatarPreview) {
            avatarUpload.addEventListener('change', function(e) {
                const file = e.target.files && e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(evt) {
                        avatarPreview.src = evt.target.result;
                        avatarPreview.style.display = 'block';
                        if (avatarPlaceholder) {
                            avatarPlaceholder.style.display = 'none';
                        }
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    });
    function togglePasswordVisibility() {
    const passwordInput = document.getElementById('password');
    const passwordEye = document.getElementById('passwordEye');

    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';

        passwordEye.setAttribute('data-lucide', 'eye-off');
    } else {
        passwordInput.type = 'password';

        passwordEye.setAttribute('data-lucide', 'eye');
    }

    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}
</script>
@endsection
