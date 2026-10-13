@extends('superadmin.layouts.app')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0">{{ $title }}</h4>
            <ol class="breadcrumb m-0">
                <li class="breadcrumb-item"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item active">{{ $title }}</li>
            </ol>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-body text-center py-5">
                <div class="avatar-lg rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-4"
                     style="width:80px;height:80px;">
                    <i class="ti ti-clock fs-36 text-primary"></i>
                </div>
                <h4 class="fw-semibold mb-2">{{ $title }} — Coming Soon</h4>
                <p class="text-muted mb-4">
                    This module is under development.<br>
                    It will be available in the next sprint.
                </p>
                <a href="{{ route('superadmin.dashboard') }}" class="btn btn-primary">
                    <i class="ti ti-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
