@extends('superadmin.layouts.app')

@section('content')
<div class="main-container container-fluid mt-4">
    <div class="breadcrumb-header justify-content-between">
        <div>
            <h4 class="content-title mb-2">{{ $title }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('superadmin.dashboard') }}">Super Admin</a></li>
                    <li class="breadcrumb-item active">{{ $pageTitle }}</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="card mt-2">
        <div class="card-header">
            <h3 class="card-title mb-0">Select Provider</h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <label class="form-label">Provider <span class="text-danger">*</span></label>
                    <select class="form-control" id="provider_selector">
                        <option value="">-- Select Provider --</option>
                        @foreach($providers as $provider)
                            <option value="{{ $provider->id }}">{{ $provider->name }}</option>
                        @endforeach
                    </select>
                    <div class="text-danger small" id="provider-selector-error"></div>
                </div>
                <div class="col-lg-6 mb-3" id="variant_selector_wrap" style="display:none;">
                    <label class="form-label">Variant <span class="text-danger">*</span></label>
                    <select class="form-control" id="variant_selector">
                        <option value="">-- Select Variant --</option>
                    </select>
                </div>
            </div>
            <button type="button" class="btn btn-primary" id="go_to_add_plan">Continue</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function () {
        var variantsByProvider = @json($variantsByProvider ?? []);
        var hasVariantByProvider = @json($hasVariantByProvider ?? []);

        function renderVariants(providerId) {
            var variants = variantsByProvider[providerId] || [];
            var hasVariant = !!hasVariantByProvider[providerId];
            var $wrap = $('#variant_selector_wrap');
            var $variant = $('#variant_selector');

            $variant.empty();

            if (!hasVariant) {
                $wrap.hide();
                return;
            }

            $variant.append('<option value="">-- Select Variant --</option>');
            variants.forEach(function (item, index) {
                var selected = index === 0 ? ' selected' : '';
                $variant.append('<option value="' + item.id + '"' + selected + '>' + item.name + '</option>');
            });
            if (!variants.length) {
                $variant.append('<option value="0" selected>Default</option>');
            }
            $wrap.show();
        }

        function goToAddPlan() {
            var providerId = $('#provider_selector').val();
            $('#provider-selector-error').text('');
            if (!providerId) {
                $('#provider-selector-error').text('Please select a provider.');
                return;
            }

            var hasVariant = !!hasVariantByProvider[providerId];
            var variants = variantsByProvider[providerId] || [];
            var variantId = 0;
            if (hasVariant) {
                variantId = variants.length ? ($('#variant_selector').val() || variants[0].id) : 0;
            }
            window.location.href = "{{ url('superadmin/plans/add-plan') }}/" + providerId + "/" + variantId;
        }

        $('#go_to_add_plan').on('click', goToAddPlan);
        $('#provider_selector').on('change', function () {
            var providerId = $(this).val();
            renderVariants(providerId);
        });
        $('#variant_selector').on('change', function () {
            if ($('#provider_selector').val()) {
                goToAddPlan();
            }
        });
    });
</script>
@endpush
