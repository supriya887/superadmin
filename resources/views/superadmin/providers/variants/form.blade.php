@extends('superadmin.layouts.app')

@section('content')
<div class="main-container container-fluid">

    <div class="breadcrumb-header justify-content-between">
        <div>
            <h4 class="content-title mb-2">{{ $title }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('superadmin.dashboard') }}">Super Admin</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('superadmin.providers.index') }}">Providers</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('superadmin.providers.variants', $provider->id) }}">Variants for {{ $provider->name }}</a></li>
                    <li class="breadcrumb-item active">{{ $pageTitle }}</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex my-auto">
            <a href="{{ route('superadmin.providers.variants', $provider->id) }}" class="btn btn-primary">
                <i class="mdi mdi-arrow-left me-1"></i> Back to Variants
            </a>
        </div>
    </div>

    <div class="card mt-2">
        <div class="card-body">
            <form id="variantForm">
                @csrf
                <input type="hidden" name="id" id="variantId" value="{{ $variant->id ?? '' }}">
                <input type="hidden" name="provider_id" value="{{ $provider->id }}">

                <div class="row">

                    {{-- Variant Name --}}
                    <div class="col-lg-6 mb-3">
                        <div class="form-group">
                            <label>Variant Name <span class="text-danger">*</span></label>
                            <input class="form-control" type="text" name="name" id="name"
                                value="{{ $variant->name ?? '' }}"
                                placeholder="e.g. Tizzy Basic">
                            <div class="text-danger" id="error-name"></div>
                        </div>
                    </div>


                    {{-- Description --}}

                    {{-- Status --}}
                    <div class="col-lg-6 mb-3">
                        <div class="form-group">
                            <label>Status <span class="text-danger">*</span></label>
                            <select class="form-control" name="status" id="status">
                                <option value="active"
                                    {{ !isset($variant) || $variant->status == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive"
                                    {{ isset($variant) && $variant->status == 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                            <div class="text-danger" id="error-status"></div>
                        </div>
                    </div>

                    {{-- Submit --}}
                    <div class="col-lg-12">
                        <div class="form-group text-center">
                            <button class="btn btn-success w-md" type="submit" id="submitVariant">
                                <div class="spinner-border spinner-border-sm float-left mt-1"
                                     id="spinner" style="display:none" role="status">
                                    <span class="sr-only">Loading...</span>
                                </div>
                                {{ isset($variant) ? 'Update Variant' : 'Add Variant' }}
                            </button>
                            <a href="{{ route('superadmin.providers.variants', $provider->id) }}" class="btn btn-primary w-md">Cancel</a>
                        </div>
                    </div>

                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script type="text/javascript">
    $(document).ready(function () {
        select2NoSearch('status', 'Status');
        formsubmit('variantForm', '{{ url('superadmin/variants-validation') }}', '{{ url('superadmin/providers/variants/', $provider->id) }}')
    });
</script>
@endpush
