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
                    <a href="{{ route('crm.lead-bifurcation.index') }}">Lead Bifurcation</a>
                    <span>/</span>
                    Add Lead Bifurcation
                </p>
            </div>
            <h2>Add Lead Bifurcation</h2>
        </div>
        <div class="back">
            <a href="{{ route('crm.lead-bifurcation.index') }}"
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

    <div class="common-table-card" style="background: #fff; border-radius: 12px; padding: 28px; border: 1px solid #edf2f7; max-width: 600px;">
        <form method="POST" action="{{ route('crm.lead-bifurcation.store') }}">
            @csrf

            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 8px;">
                    Status Name <span style="color: #ef4444;">*</span>
                </label>
                <input type="text" name="name" value="{{ old('name') }}" placeholder="e.g. B2B, B2C" required
                    style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 14px; font-size: 13.5px; box-sizing: border-box; outline: none;">
            </div>

            <div style="margin-bottom: 24px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 8px;">
                    Description
                </label>
                <textarea name="description" placeholder="Enter description" rows="3"
                    style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 14px; font-size: 13.5px; box-sizing: border-box; outline: none;">{{ old('description') }}</textarea>
            </div>

            <button type="submit"
                style="background: #0056b3; color: #fff; border: none; padding: 11px 28px; border-radius: 8px; font-size: 13.5px; font-weight: 600; cursor: pointer;">
                Save Lead Bifurcation
            </button>
        </form>
    </div>
@endsection
