@extends('superadmin.layouts.app')

@section('content')
<div class="main-container container-fluid mt-4">
    @php
        $isProviderLocked = ($lockProvider ?? false) || request()->query('source') === 'provider';
        $isMicrosoft = !empty($isMicrosoft) || (isset($provider) && (
            (int) ($provider->id ?? 0) === 2
            || str_contains(strtolower((string) ($provider->name ?? '')), 'microsoft')
            || str_contains(strtolower((string) ($provider->name ?? '')), 'office 365')
            || str_contains(strtolower((string) ($provider->name ?? '')), 'm365')
        ));
    @endphp

    <div class="breadcrumb-header justify-content-between">
        <div>
            <h4 class="content-title mb-2">{{ $title }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('superadmin.dashboard') }}">Super Admin</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('superadmin.providers.index') }}">Providers</a></li>
                    <li class="breadcrumb-item"><a href="{{ url('superadmin/providers-variants/' . $provider_id) }}">{{ $provider->name }} Variants</a></li>
                    <li class="breadcrumb-item"><a href="{{ url('superadmin/plans/' . $provider_id . '/' . $variant_id) }}">Plans</a></li>
                    <li class="breadcrumb-item active">{{ $pageTitle }}</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex my-auto">
            <a href="{{ url('superadmin/plans/' . $provider_id . '/' . $variant_id) }}" class="btn btn-secondary">
                <i class="mdi mdi-arrow-left me-1"></i> Back to Plans
            </a>
        </div>
    </div>

    <div class="card mt-2">
        <div class="card-header">
            <h3 class="card-title">{{ $pageTitle }}</h3>
        </div>
        <div class="card-body">
            <form id="planForm" novalidate>
                @csrf
                <input type="hidden" name="id" value="{{ isset($plan) ? $plan->id : '' }}">
                <input type="hidden" name="variant_id" value="{{ $variant_id ?? 0 }}">
                <input type="hidden" name="provider_id" value="{{ $provider_id }}">

                <div class="row">
                    {{-- Add SR. NO Column --}}
                    @php
                        if (isset($plan)) {
                            $sr_no_val = $plan->sr_no ?? '';
                        } elseif (isset($next_sr_no)) {
                            $sr_no_val = $next_sr_no;
                        } else {
                            $srQuery = \App\Models\Plan::where('provider_id', $provider_id);
                            if (!empty($variant?->id)) {
                                $srQuery->where('variant_id', $variant->id);
                            }
                            $sr_no_val = ((int) $srQuery->max('sr_no')) + 1;
                        }
                    @endphp

                    <div class="col-md-2 mb-3">
                        <label class="form-label">Sr. No @if(isset($plan))<span class="text-danger">*</span>@endif</label>
                        @if(isset($plan))
                            <input type="number" class="form-control" name="sr_no" id="sr_no" value="{{ $sr_no_val }}" min="1" step="1" required>
                            <small class="text-muted">Change order here, or drag plans on the list page.</small>
                        @else
                            <input type="text" class="form-control bg-light" id="sr_no_display" value="{{ $sr_no_val }}" readonly>
                            <input type="hidden" name="sr_no" id="sr_no" value="{{ $sr_no_val }}">
                            <small class="text-muted">Auto-assigned on save (next available number).</small>
                        @endif
                        <div class="text-danger small" id="error-sr_no"></div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Select Provider <span class="text-danger">*</span></label>
                        @if($isProviderLocked || isset($plan))
                            <input type="text" class="form-control" value="{{ $provider->name ?? '' }}" readonly>
                            <input type="hidden" name="provider_select" id="provider_select" value="{{ $provider_id }}">
                        @else
                            <select class="form-control" name="provider_select" id="provider_select">
                                @foreach($providers as $item)
                                    <option value="{{ $item->id }}" data-has-variant="{{ $item->has_variant }}" {{ (int)$provider_id === (int)$item->id ? 'selected' : '' }}>
                                        {{ $item->name }}
                                    </option>
                                @endforeach
                            </select>
                        @endif
                    </div>

                    @if($isMicrosoft)
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Subscription Type <span class="text-danger">*</span></label>
                        <select class="form-control" name="subscription_type" id="subscription_type" required>
                            <option value="">Select Type</option>
                            <option value="software" {{ (isset($plan) && $plan->subscription_type == 'software') ? 'selected' : '' }}>
                                Subscription Software
                            </option>
                            <option value="perpetual" {{ (isset($plan) && $plan->subscription_type == 'perpetual') ? 'selected' : '' }}>
                                Perpetual
                            </option>
                        </select>
                        <div class="text-danger small" id="error-subscription_type"></div>
                    </div>

                    @if($isProviderLocked && (($provider->has_variant ?? 'no') === 'yes'))
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Select Variant <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" value="{{ optional($variant)->name ?? 'Master' }}" readonly>
                            <input type="hidden" name="variant_select" id="variant_select" value="{{ $variant_id }}">
                        </div>
                    @else
                        <div class="col-md-6 mb-3" id="variant_select_wrap" style="display:none;">
                            <label class="form-label">Select Variant <span class="text-danger">*</span></label>
                            <select class="form-control" name="variant_select" id="variant_select">
                                <option value="0">-- Select Variant --</option>
                            </select>
                        </div>
                    @endif

                    <!-- Discount Type - Only visible when Subscription Software is selected -->
                    <div class="col-md-6 mb-3" id="discount_type_wrap" style="{{ (isset($plan) && $plan->subscription_type == 'software') ? '' : 'display:none;' }}">
                        <label class="form-label">Discount Type <span class="text-danger">*</span></label>
                        <select class="form-control" name="discount_type" id="discount_type">
                            <option value="">Select Discount Type</option>
                            <option value="rebated" {{ (isset($plan) && $plan->discount_type == 'rebated') ? 'selected' : '' }}>
                                Rebated (Discounted)
                            </option>
                            <option value="non_rebated" {{ (isset($plan) && $plan->discount_type == 'non_rebated') ? 'selected' : (!isset($plan) ? 'selected' : '') }}>
                                Non-Rebated (Full Price)
                            </option>
                        </select>
                        <small class="text-muted">Required for Subscription Software — choose Rebated if MSRP is discounted, otherwise Non-Rebated.</small>
                        <div class="text-danger small" id="error-discount_type"></div>
                    </div>
                    @endif

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Plan Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="name" value="{{ isset($plan) ? $plan->name : '' }}" required placeholder="Enter Plan Name">
                        <div class="text-danger small" id="error-name"></div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Slug <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="slug" id="slug" value="{{ isset($plan) ? $plan->slug : '' }}" required placeholder="plan-slug">
                        <div class="text-danger small" id="error-slug"></div>
                    </div>

                    {{-- Category column removed --}}

                    @php
                        $displayActualPrice = '';
                        $displayFinalPrice = '';
                        if (isset($plan)) {
                            $storedActual = (float) ($plan->actual_price ?? 0);
                            $storedPrice = (float) ($plan->price ?? 0);
                            if ($isMicrosoft && $storedActual <= 0 && $storedPrice > 0) {
                                $displayActualPrice = number_format($storedPrice, 2, '.', '');
                                $displayFinalPrice = number_format($storedPrice, 2, '.', '');
                            } else {
                                $displayActualPrice = $storedActual > 0 ? number_format($storedActual, 2, '.', '') : '';
                                $displayFinalPrice = $storedPrice > 0 ? number_format($storedPrice, 2, '.', '') : '';
                            }
                        }
                    @endphp
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Market Price<span class="text-danger">*</span></label>
                        <input type="number" step="any" class="form-control" name="actual_price" id="actual_price" value="{{ $displayActualPrice }}" required placeholder="0.00">
                        <div class="text-danger small" id="error-actual_price"></div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Distributor Price <span class="text-danger">*</span></label>
                        <input type="number" step="any" class="form-control {{ $isMicrosoft ? 'bg-light' : '' }}" name="price" id="price" value="{{ $displayFinalPrice }}" required placeholder="0.00" @if($isMicrosoft) readonly @endif>
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

                    <!-- MICROSOFT-SPECIFIC FIELDS -->
                    @if($isMicrosoft)
                    <div class="col-12">
                        <h5 class="border-bottom pb-2 mt-2 mb-3">Microsoft Plan Configuration</h5>
                    </div>

                    <!-- Discount Percentage - Only visible when Rebated is selected -->
                    <div class="col-md-6 mb-3" id="discount_percentage_wrap" style="{{ (isset($plan) && $plan->discount_type == 'rebated') ? '' : 'display:none;' }}">
                        <label class="form-label">Discount Percentage <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" step="0.01" class="form-control" name="discount_percentage" id="discount_percentage"
                                   value="{{ isset($plan) ? $plan->discount_percentage : '' }}" placeholder="e.g. 10" min="0" max="100">
                            <span class="input-group-text">%</span>
                        </div>
                        <small class="text-muted">Discount percentage for rebated pricing (e.g., 10% off)</small>
                        <div class="text-danger small" id="error-discount_percentage"></div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Microsoft Product ID</label>
                        <input type="text" class="form-control" name="microsoft_product_id" id="microsoft_product_id"
                               value="{{ isset($plan) ? $plan->microsoft_product_id : '' }}" placeholder="e.g., MS-PROD-001">
                        <div class="text-danger small" id="error-microsoft_product_id"></div>
                    </div>

                    <!-- Calculated Pricing Display -->
                    <div class="col-12 mb-3">
                        <div class="alert alert-info" id="pricingPreview" style="{{ (isset($plan) && $plan->subscription_type == 'software') ? '' : 'display:none;' }}">
                            <strong>Pricing Preview:</strong><br>
                            Actual Price: ₹<span id="preview_actual_price">{{ isset($plan) ? number_format($plan->actual_price, 2) : '0.00' }}</span><br>
                            Discount Type: <span id="preview_discount_type">{{ isset($plan) ? ucfirst(str_replace('_', ' ', $plan->discount_type)) : 'None' }}</span><br>
                            Discount: <span id="preview_discount_percentage">{{ isset($plan) ? $plan->discount_percentage : '0' }}</span>%<br>
                            <strong>Final Price: ₹<span id="preview_final_price">{{ isset($plan) ? number_format($plan->getDiscountedPrice(), 2) : '0.00' }}</span></strong>
                        </div>
                    </div>

                    <!-- Perpetual Info Message -->
                    <div class="col-12 mb-3" id="perpetual_info" style="{{ (isset($plan) && $plan->subscription_type == 'perpetual') ? '' : 'display:none;' }}">
                        <div class="alert alert-warning">
                            <i class="mdi mdi-information-outline"></i>
                            <strong>Perpetual License:</strong> One-time purchase with no recurring fees. No discounts apply.
                        </div>
                    </div>
                    @endif
                    <!-- END MICROSOFT-SPECIFIC FIELDS -->

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Storage <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="storage" id="storage" value="{{ isset($plan) ? $plan->storage : '' }}" required placeholder="e.g. 50 GB">
                        <div class="text-danger small" id="error-storage"></div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Limited Offer <span class="text-danger">*</span></label>
                        <select class="form-control" name="limited_offer" id="limited_offer" required>
                            <option value="0" {{ (isset($plan) && $plan->limited_offer == '0') ? 'selected' : '' }}>No</option>
                            <option value="1" {{ (isset($plan) && $plan->limited_offer == '1') ? 'selected' : '' }}>Yes</option>
                        </select>
                        <div class="text-danger small" id="error-limited_offer"></div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Status <span class="text-danger">*</span></label>
                        <select class="form-control" name="status2" id="status2" required>
                            <option value="1" {{ (isset($plan) && $plan->status == '1') ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ (isset($plan) && $plan->status == '0') ? 'selected' : '' }}>Inactive</option>
                        </select>
                        <div class="text-danger small" id="error-status2"></div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Users Limit <span class="text-muted">(User can add up to X customers)</span></label>
                        <input type="number" class="form-control" name="customer_limit" id="customer_limit" value="{{ isset($plan) ? $plan->customer_limit : '1' }}" required min="1">
                        <div class="text-danger small" id="error-customer_limit"></div>
                    </div>

                    <div class="col-md-12 mb-3">
                        <label class="form-label">Plan sub heading</label>
                        <textarea class="form-control" name="description" id="description" rows="3" placeholder="plan sub heading">{{ isset($plan) ? $plan->description : '' }}</textarea>
                        <div class="text-danger small" id="error-description"></div>
                    </div>

                    <div class="col-md-12 mb-3">
                        <label class="form-label">Plan Features <span class="text-muted">(One per line)</span></label>
                        <textarea class="form-control" name="features" id="features" rows="5" placeholder="Custom & secure business email&#10;150 participant video meetings + recording&#10;2 TB cloud storage per user">{{ isset($plan) ? str_replace('|', "\n", $plan->features) : '' }}</textarea>
                        <div class="text-muted small">Features will be automatically converted to bullet points.</div>
                        <div class="text-danger small" id="error-features"></div>
                    </div>

                    @if($isMicrosoft)
                    <div class="col-md-6 mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0">Popular Apps / Services</label>
                            <button type="button" class="btn btn-sm btn-outline-primary ms-1" data-bs-toggle="modal" data-bs-target="#addAppModal" title="Add New App" data-bs-toggle="tooltip">
                                <i class="mdi mdi-plus"></i>
                            </button>
                        </div>
                        <select class="form-control" id="choices-multiple-default" data-choices name="apps[]" multiple>
                            @foreach($apps as $app)
                                <option value="{{ $app->id }}" {{ (isset($selectedApps) && in_array($app->id, $selectedApps)) ? 'selected' : '' }}>{{ $app->name }}</option>
                            @endforeach
                        </select>
                        <div class="text-danger small" id="error-apps"></div>
                    </div>
                    @endif
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary" id="savePlanBtn">
                        Save Plan
                        <div class="spinner-border spinner-border-sm" role="status" style="display:none;" id="planSpinner"></div>
                    </button>
                    <a href="{{ url('superadmin/plans/' . $provider_id . '/' . $variant_id) }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modals -->

<div class="modal fade" id="addAppModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New App / Service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="quickAppForm">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">App Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="quick-app-name" required placeholder="e.g. Word, Excel">
                        <div class="text-danger small" id="quick-error-name"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">App Icon <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" name="image" id="quick-app-image" required>
                        <div class="text-danger small" id="quick-error-image"></div>
                    </div>
                    <input type="hidden" name="status" value="active">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveQuickAppBtn">
                        Save & Add
                        <div class="spinner-border spinner-border-sm" role="status" style="display:none;" id="quickAppSpinner"></div>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.2.96/css/materialdesignicons.min.css">
<script src="{{ asset('public/superadmin/plugins/select2/select2.min.js') }}"></script>
<script src="{{ asset('public/superadmin/js/pages/form-choice.js') }}"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/alertify.min.css"/>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/themes/default.min.css"/>
<script src="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/alertify.min.js"></script>

<script>
$(function() {
    const variantsByProvider = @json($variantsByProvider ?? []);
    const hasVariantByProvider = @json($hasVariantByProvider ?? []);
    const currentProviderId = "{{ (int) $provider_id }}";
    const currentVariantId = "{{ (int) $variant_id }}";
    const isMicrosoft = @json($isMicrosoft ?? false);

    function renderVariantSelect(providerId, selectedVariantId) {
        const variants = variantsByProvider[providerId] || [];
        const hasVariant = !!hasVariantByProvider[providerId];
        const $wrap = $('#variant_select_wrap');
        const $variant = $('#variant_select');
        $variant.empty();

        if (!hasVariant) {
            $wrap.hide();
            return;
        }

        if (variants.length) {
            variants.forEach(item => {
                $variant.append(
                    $('<option>').val(item.id).text(item.name).prop('selected', String(item.id) === String(selectedVariantId))
                );
            });
        } else {
            $variant.append($('<option>').val(0).text('Default').prop('selected', true));
        }

        $wrap.show();
    }

    renderVariantSelect(currentProviderId, currentVariantId);

    $('#quickAppForm').on('submit', function(e) {
        e.preventDefault();
        $('#saveQuickAppBtn').prop('disabled', true);
        $('#quickAppSpinner').show();
        $('#quickAppForm .text-danger').html('');

        $.ajax({
            url: '{{ route('superadmin.apps.save') }}',
            type: 'POST',
            data: new FormData(this),
            contentType: false,
            cache: false,
            processData: false,
            dataType: 'json',
            success(response) {
                $('#saveQuickAppBtn').prop('disabled', false);
                $('#quickAppSpinner').hide();
                if (response.error) {
                    if (response.name) $('#quick-error-name').html(response.name);
                    if (response.image) $('#quick-error-image').html(response.image);
                } else if (response.success) {
                    const app = response.app;
                    $('#choices-multiple-default').append(new Option(app.name, app.id, true, true)).trigger('change');
                    $('#addAppModal').modal('hide');
                    $('#quickAppForm')[0].reset();
                    alertify.success(response.success);
                }
            },
            error() {
                $('#saveQuickAppBtn').prop('disabled', false);
                $('#quickAppSpinner').hide();
                alertify.error('An error occurred.');
            }
        });
    });

    $('#name').on('blur', function() {
        let name = $(this).val();
        let currentSlug = $('#slug').val();
        let autoSlug = name.toLowerCase().replace(/ /g, '-').replace(/[^\w-]+/g, '');
        if (!currentSlug || currentSlug === autoSlug) {
            $('#slug').val(autoSlug);
        }
    });

    function clearMicrosoftFieldRequirements() {
        $('#discount_type, #discount_percentage').prop('required', false);
    }

    function syncMicrosoftPriceField() {
        if (!isMicrosoft) return;
        let actualPrice = parseFloat($('#actual_price').val()) || 0;
        let subscriptionType = $('#subscription_type').val();
        let discountType = $('#discount_type').val();
        let discountPercentage = parseFloat($('#discount_percentage').val()) || 0;
        let finalPrice = actualPrice;

        if (subscriptionType === 'software' && discountType === 'rebated' && discountPercentage > 0) {
            finalPrice = actualPrice * (1 - discountPercentage / 100);
        }
        $('#price').val(actualPrice > 0 ? finalPrice.toFixed(2) : '');
    }

    $('#planForm').on('submit', function(e) {
        e.preventDefault();
        clearMicrosoftFieldRequirements();

        if (isMicrosoft) {
            let subscriptionType = ($('#subscription_type').val() || '').trim();
            if (!subscriptionType) {
                alertify.error('Please select Subscription Type.');
                $('#subscription_type').focus();
                return;
            }
            if (subscriptionType === 'software') {
                if (!($('#discount_type').val() || '').trim()) {
                    alertify.error('Please select Rebated or Non-Rebated discount type.');
                    $('#discount_type').focus();
                    return;
                }
                if ($('#discount_type').val() === 'rebated') {
                    let pct = parseFloat($('#discount_percentage').val()) || 0;
                    if (!(pct > 0)) {
                        alertify.error('Enter discount percentage for rebated plans.');
                        $('#discount_percentage').focus();
                        return;
                    }
                }
            } else if (subscriptionType === 'perpetual') {
                $('#discount_type').val('');
                $('#discount_percentage').val('');
            }
            syncMicrosoftPriceField();
        }

        $('#savePlanBtn').prop('disabled', true);
        $('#planSpinner').show();
        $('#planForm .text-danger').html('');
        $('#planForm .form-control').removeClass('border-danger');

        $.ajax({
            url: '{{ url('superadmin/plans/save-plan/' . $provider_id . '/' . $variant_id) }}',
            type: 'POST',
            data: new FormData(this),
            contentType: false,
            cache: false,
            processData: false,
            dataType: 'json',
            success(response) {
                $('#savePlanBtn').prop('disabled', false);
                $('#planSpinner').hide();
                if (response.error) {
                    $.each(response, function(key, value) {
                        if (value && key !== 'error' && key !== 'message') {
                            $('#error-' + key).html(value);
                            $('[name="' + key + '"]').addClass('border-danger');
                        }
                    });
                    alertify.error(response.message || 'Please fix the highlighted fields.');
                } else if (response.success) {
                    alertify.success(response.message || 'Plan saved.');
                    setTimeout(function() {
                        window.location.href = "{{ url('superadmin/plans/' . $provider_id . '/' . $variant_id) }}";
                    }, 1000);
                }
            },
            error(xhr) {
                $('#savePlanBtn').prop('disabled', false);
                $('#planSpinner').hide();
                let msg = 'An error occurred while saving the plan.';
                if (xhr.responseJSON) {
                    if (xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    } else if (xhr.responseJSON.error) {
                        const firstFieldError = Object.keys(xhr.responseJSON).filter(k =>
                            k !== 'error' && k !== 'message' && xhr.responseJSON[k]
                        )[0];
                        if (firstFieldError) {
                            msg = xhr.responseJSON[firstFieldError];
                        }
                    }
                }
                alertify.error(msg);
            }
        });
    });

    @if(!isset($plan) && !$isProviderLocked)
    $('#provider_select').on('change', function() {
        const selectedProviderId = $(this).val();
        if (!selectedProviderId) return;
        const hasVariant = ($(this).find('option:selected').data('has-variant') + '') === 'yes';
        const variants = variantsByProvider[selectedProviderId] || [];
        const variantId = (hasVariant && variants.length) ? variants[0].id : 0;
        window.location.href = "{{ url('superadmin/plans/add-plan') }}/" + selectedProviderId + "/" + variantId;
    });
    @endif

    @if(!$isProviderLocked)
    $('#variant_select').on('change', function() {
        const selectedProviderId = $('#provider_select').val();
        const selectedVariantId = $(this).val() || 0;
        if (!selectedProviderId) return;
        window.location.href = "{{ url('superadmin/plans/add-plan') }}/" + selectedProviderId + "/" + selectedVariantId;
    });
    @endif

    if (isMicrosoft) {
        // Subscription Type change 
        $('#subscription_type').on('change', function() {
            const subscriptionType = $(this).val();

            if (subscriptionType === 'software') {
                $('#discount_type_wrap').show();
                $('#perpetual_info').hide();
                if (!($('#discount_type').val() || '').trim()) {
                    $('#discount_type').val('non_rebated');
                }
                $('#discount_type').trigger('change');
                syncMicrosoftPriceField();
            } else if (subscriptionType === 'perpetual') {
                $('#discount_type_wrap').hide();
                $('#discount_type').prop('required', false).val('');
                $('#discount_percentage_wrap').hide();
                $('#discount_percentage').prop('required', false).val('');
                $('#pricingPreview').hide();
                $('#perpetual_info').show();

                let actualPrice = parseFloat($('#actual_price').val()) || 0;
                $('#price').val(actualPrice > 0 ? actualPrice.toFixed(2) : '');
            } else {
                $('#discount_type_wrap').hide();
                $('#discount_type').prop('required', false);
                $('#discount_percentage_wrap').hide();
                $('#pricingPreview').hide();
                $('#perpetual_info').hide();
            }
        });

        // Discount Type change
        $('#discount_type').on('change', function() {
            const discountType = $(this).val();

            if (discountType === 'rebated') {
                $('#discount_percentage_wrap').show();
            } else if (discountType === 'non_rebated') {
                $('#discount_percentage_wrap').hide();
                $('#discount_percentage').val('');
                let actualPrice = parseFloat($('#actual_price').val()) || 0;
                $('#price').val(actualPrice.toFixed(2));
            } else {
                $('#discount_percentage_wrap').hide();
                $('#discount_percentage').prop('required', false);
            }
            updatePricingPreview();
        });

        // Pricing preview update events
        $('#actual_price, #discount_percentage, #discount_type, #subscription_type').on('change keyup', function() {
            updatePricingPreview();
        });

        function updatePricingPreview() {
            const subscriptionType = $('#subscription_type').val();

            if (subscriptionType !== 'software') {
                $('#pricingPreview').hide();
                return;
            }

            let actualPrice = parseFloat($('#actual_price').val()) || 0;
            let discountType = $('#discount_type').val();
            let discountPercentage = parseFloat($('#discount_percentage').val()) || 0;

            if (actualPrice > 0 && discountType) {
                $('#pricingPreview').show();
                $('#preview_actual_price').text(actualPrice.toFixed(2));
                let discountTypeLabel = discountType === 'rebated' ? 'Rebated' : 'Non-Rebated';
                $('#preview_discount_type').text(discountTypeLabel);

                let finalPrice = actualPrice;
                if (discountType === 'rebated' && discountPercentage > 0) {
                    finalPrice = actualPrice * (1 - discountPercentage / 100);
                    $('#preview_discount_percentage').text(discountPercentage);
                    $('#price').val(finalPrice.toFixed(2));
                } else {
                    $('#preview_discount_percentage').text('0');
                    $('#price').val(actualPrice.toFixed(2));
                }
                $('#preview_final_price').text(finalPrice.toFixed(2));
            } else {
                $('#pricingPreview').hide();
            }
        }

        $('#actual_price').on('change keyup', function() {
            let actualPrice = parseFloat($(this).val()) || 0;
            let subscriptionType = $('#subscription_type').val();
            let discountType = $('#discount_type').val();
            let discountPercentage = parseFloat($('#discount_percentage').val()) || 0;

            if (subscriptionType === 'perpetual') {
                $('#price').val(actualPrice.toFixed(2));
            } else if (subscriptionType === 'software' && discountType === 'rebated' && discountPercentage > 0) {
                let finalPrice = actualPrice * (1 - discountPercentage / 100);
                $('#price').val(finalPrice.toFixed(2));
            } else {
                $('#price').val(actualPrice.toFixed(2));
            }
            updatePricingPreview();
        });

        // Initial state if editing
        @if(isset($plan))
        setTimeout(function() {
            $('#subscription_type').trigger('change');
            if ('{{ $plan->subscription_type }}' === 'software') {
                setTimeout(function() {
                    $('#discount_type').trigger('change');
                    updatePricingPreview();
                }, 200);
            }
        }, 300);
        @else
        setTimeout(function() {
            if ($('#subscription_type').val() === 'software') {
                $('#subscription_type').trigger('change');
            }
        }, 100);
        @endif
    }
});
</script>
@endpush