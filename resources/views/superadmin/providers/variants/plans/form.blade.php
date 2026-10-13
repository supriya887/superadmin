@extends('superadmin.index')

@section('content')
<div class="main-container container-fluid">

    <div class="breadcrumb-header justify-content-between">
        <div>
            <h4 class="content-title mb-2">{{ $title }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('superadmin.dashboard') }}">Super Admin</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('superadmin.providers.index') }}">Providers</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('superadmin.providers.variants', $variant->provider_id) }}">{{ $provider->name }} Variants</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('superadmin.providers.variants.plans', $variant->id) }}">Plans</a></li>
                    <li class="breadcrumb-item active">{{ $pageTitle }}</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex my-auto">
            <a href="{{ route('superadmin.providers.variants.plans', $variant->id) }}" class="btn btn-secondary">
                <i class="mdi mdi-arrow-left me-1"></i> Back to Plans
            </a>
        </div>
    </div>

    <div class="card mt-2">
        <div class="card-header">
            <h3 class="card-title">{{ $pageTitle }}</h3>
        </div>
        <div class="card-body">
            <form id="planForm">
                @csrf
                <input type="hidden" name="id" value="{{ isset($plan) ? $plan->id : '' }}">
                <input type="hidden" name="variant_id" value="{{ $variant->id }}">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Plan Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="name" value="{{ isset($plan) ? $plan->name : '' }}" required>
                        <div class="text-danger small" id="error-name"></div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Price <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control" name="price" id="price" value="{{ isset($plan) ? $plan->price : '' }}" required>
                        <div class="text-danger small" id="error-price"></div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Billing Cycle <span class="text-danger">*</span></label>
                        <select class="form-control" name="billing_cycle" id="billing_cycle" required>
                            <option value="monthly" {{ (isset($plan) && $plan->billing_cycle == 'monthly') ? 'selected' : '' }}>Monthly</option>
                            <option value="yearly" {{ (isset($plan) && $plan->billing_cycle == 'yearly') ? 'selected' : '' }}>Yearly</option>
                        </select>
                        <div class="text-danger small" id="error-billing_cycle"></div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Status <span class="text-danger">*</span></label>
                        <select class="form-control" name="status" id="status" required>
                            <option value="active" {{ (isset($plan) && $plan->status == 'active') ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ (isset($plan) && $plan->status == 'inactive') ? 'selected' : '' }}>Inactive</option>
                        </select>
                        <div class="text-danger small" id="error-status"></div>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary" id="savePlanBtn">
                        Save Plan
                        <div class="spinner-border spinner-border-sm" role="status" style="display:none;" id="planSpinner"></div>
                    </button>
                    <a href="{{ route('superadmin.providers.variants.plans', $variant->id) }}" class="btn btn-secondary ms-2">Cancel</a>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        $('#planForm').on('submit', function (e) {
            e.preventDefault();
            $('#savePlanBtn').attr('disabled', true);
            $('#planSpinner').show();
            $('#planForm .text-danger').html('');
            $('#planForm .form-control').removeClass('border-danger');

            $.ajax({
                url: '{{ route('superadmin.providers.variants.plans.save') }}',
                type: 'POST',
                data: new FormData(this),
                contentType: false,
                cache: false,
                processData: false,
                dataType: 'json',
                success: function (response) {
                    $('#savePlanBtn').attr('disabled', false);
                    $('#planSpinner').hide();
                    
                    if (response.error) {
                        $.each(response, function (key, value) {
                            if (value && key != 'error') {
                                $('#error-' + key).html(value);
                                $('[name="'+key+'"]').addClass('border-danger');
                            }
                        });
                    } else if (response.success) {
                        alertify.success(response.message);
                        setTimeout(function() {
                            window.location.href = "{{ route('superadmin.providers.variants.plans', $variant->id) }}";
                        }, 1000);
                    }
                },
                error: function (xhr) {
                    $('#savePlanBtn').attr('disabled', false);
                    $('#planSpinner').hide();
                    alertify.error('An error occurred while saving the plan.');
                }
            });
        });
    });
</script>
@endpush
