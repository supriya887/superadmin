@extends('superadmin.layouts.crm')

@section('content')
    <div class="page-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
        <div>
            <div class="breadcrumb welcome-text">
                <p>
                    <a href="{{ Route::has('crm.dashboard') ? route('crm.dashboard') : '#' }}">Dashboard</a>
                    <span>/</span>
                    <a href="javascript:void(0)">Manage</a>
                    <span>/</span>
                    <a href="{{ route('crm.lead-sources.index') }}">Lead Sources</a>
                    <span>/</span>
                    Edit Lead Source
                </p>
            </div>
            <h2>Edit Lead Source</h2>
        </div>
        <div class="back">
            <a href="{{ route('crm.lead-sources.index') }}"
                style="background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 16px; display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 500; color: #334155; text-decoration: none;">
                <i data-lucide="arrow-left" style="width: 14px; height: 14px;"></i>
                <span>Back</span>
            </a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger"
            style="padding: 12px 20px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 8px; margin-bottom: 16px; font-size: 13.5px;">
            <ul style="margin: 0; padding-left: 16px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div style="display: flex; justify-content: center;">
        <div style="background: #ffffff; border-radius: 16px; width: 100%; max-width: 440px; padding: 28px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #edf2f7;">
            <h3 style="margin: 0 0 20px 0; font-size: 18px; font-weight: 700; color: #1e293b; text-align: center;">
                Edit Lead Source
            </h3>

            <form method="POST" action="{{ route('crm.lead-sources.update', $leadSource->id) }}" enctype="multipart/form-data">
                @csrf

                <!-- Logo -->
                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                        Logo
                    </label>
                    <div style="display: flex; align-items: center; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; background: #fff;">
                        <span id="editFileName" style="flex: 1; padding: 9px 12px; font-size: 13px; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            {{ $leadSource->logo ? 'Current logo selected' : 'Choose File' }}
                        </span>
                        <label for="editLogoInput" style="padding: 9px 14px; background: #f8fafc; border-left: 1px solid #e2e8f0; font-size: 13px; font-weight: 500; color: #334155; cursor: pointer; margin: 0; white-space: nowrap;">
                            Choose File
                        </label>
                        <input type="file" id="editLogoInput" name="logo" accept="image/*" style="display: none;" onchange="document.getElementById('editFileName').innerText = this.files[0] ? this.files[0].name : 'Choose File'">
                    </div>
                </div>

                <!-- Source Name -->
                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                        Source Name <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="name" value="{{ old('name', $leadSource->name) }}" placeholder="Enter lead source name" required
                        style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 9px 12px; font-size: 13px; outline: none; box-sizing: border-box; color: #1e293b;">
                </div>

                <!-- Status -->
                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                        Status <span style="color: #ef4444;">*</span>
                    </label>
                    <select name="status" required
                        style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 9px 12px; font-size: 13px; outline: none; box-sizing: border-box; background: #fff; color: #1e293b;">
                        <option value="active" {{ old('status', $leadSource->status) === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ old('status', $leadSource->status) === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>

                <div style="text-align: center;">
                    <button type="submit"
                        style="width: 100%; background: #0056b3; color: #ffffff; border: none; padding: 11px 24px; border-radius: 20px; font-size: 14px; font-weight: 600; cursor: pointer;">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
