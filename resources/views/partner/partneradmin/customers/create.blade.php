@extends('admin.layouts.app')

@section('title', 'Add New Customer')

@section('content')
@php
    $readOnly = $readOnly ?? false;
    $partnerStatusVal = $partnerStatusVal ?? '1';
@endphp
<div class="page-header" data-aos="fade-up" data-aos-duration="600">
    <h1 class="page-title text-start">Add New Customer</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="#">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="#">Customers</a></li>
            <li class="breadcrumb-item active" aria-current="page">New Customer</li>
        </ol>
    </nav>
</div>

<div class="content-card" data-aos="fade-up" data-aos-duration="800" data-aos-delay="100">
   

    <form id="form">
        @csrf
        <input type="hidden" name="zip_code" id="zip_code" value="{{ old('zip_code') }}">
        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Name <span>*</span></label>
                <input type="text" class="form-control" name="name" id="name" value="{{ old('name') }}">
                <div class="text-danger" id="error-name"></div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email <span>*</span></label>
                <input type="email" class="form-control" name="email" id="email" value="{{ old('email') }}">
                <div class="text-danger" id="error-email"></div>
            </div>
        </div>

        <!-- Row 3 -->
        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Mobile <span>*</span></label>
                <input type="text" class="form-control" name="mobile" id="mobile" value="{{ old('mobile') }}">
                <div class="text-danger" id="error-mobile"></div>
            </div>

            @if(! $readOnly)
                <div class="col-md-6">
                    <label for="status2_display" class="form-label">Customer status (Active / Inactive) <span>*</span></label>
                    <select name="status2" id="status2_display" class="form-control">
                        <option value="1" {{ $partnerStatusVal === '1' || $partnerStatusVal === '' ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ $partnerStatusVal === '0' ? 'selected' : '' }}>Inactive</option>
                    </select>
                    <div class="text-danger fs-xs" id="error-status2"></div>
                </div>
            @endif
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">GSTIN <span>*</span></label>
                <input type="text" class="form-control" name="gstin" id="gstin" value="{{ old('gstin') }}" placeholder="AA00001234W548" maxlength="15">
                <div class="text-danger" id="error-gstin"></div>
                <small class="text-muted">Company details load when you leave this field (valid 15-character GSTIN).</small>
            </div>
        </div>

        <div id="company-section" style="display: none;">
            <hr class="my-4">
            <p class="text-muted mb-3">Company & address (from GSTIN lookup)</p>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-normal">PAN No. <span>*</span></label>
                    <input type="text" name="pan_no" class="form-control fw-normal" id="pan_no" placeholder="PAN No." value="{{ old('pan_no') }}" >
                    <div class="text-danger" id="error-pan_no"></div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Company Name <span>*</span></label>
                    <input type="text" class="form-control bg-light" name="company_name" id="company_name" value="{{ old('company_name') }}" readonly>
                    <div class="text-danger" id="error-company_name"></div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Company Address <span>*</span></label>
                    <textarea class="form-control bg-light" name="company_address" id="company_address" rows="2">{{ old('company_address') }}</textarea>
                    <div class="text-danger" id="error-company_address"></div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Country <span>*</span></label>
                    <input type="text" class="form-control bg-light" name="country" id="country" placeholder="Country" value="{{ old('country') }}">
                    <div class="text-danger fs-xs" id="error-country"></div>
                </div>

                <div class="col-md-6">
                    <label for="state" class="form-label">State <span>*</span></label>
                    <input type="text" name="state" class="form-control bg-light" id="state" placeholder="State" value="{{ old('state') }}">
                    <div class="text-danger fs-xs" id="error-state"></div>
                </div>
                
                <div class="col-md-6">
                    <label for="city" class="form-label">City <span>*</span></label>
                    <input type="text" name="city" class="form-control bg-light" id="city" placeholder="City" value="{{ old('city') }}">
                    <div class="text-danger fs-xs" id="error-city"></div>
                </div>

                <div class="col-md-6">
                    <label for="postcode" class="form-label">Pincode <span>*</span></label>
                    <input type="text" name="postcode" class="form-control bg-light" id="postcode" placeholder="Pincode" value="{{ old('postcode') }}">
                    <div class="text-danger fs-xs" id="error-postcode"></div>
                </div>
            </div>
        </div>
        <!-- Submit Button -->
        <div class="submit-btn-container d-flex gap-3 justify-content-center">
            {{-- show loader  --}}
            <button type="submit" id="submit" class="btn btn-submit">
                <span id="spinner" class="spinner-border spinner-border-sm" style="display: none;"></span>    
                Save
            </button>
            <a href="{{ url('partner/customers-create') }}" class="btn btn-submit">Cancel</a>
        </div>
    </form>
</div>
@endsection
@section('js')
<script>
    $(document).ready(function() {
        // Standardized Form Submission
        formSubmit("{{ route('partner.partneradmin.customers.store') }}", "{{ route('partner.partneradmin.customers.index') }}");

        function clearCompanyFields() {
            $('#company_name').val('');
            $('#company_address').val('');
            $('#country').val('');
            $('#state').val('');
            $('#city').val('');
            $('#postcode').val('');
            $('#zip_code').val('');
            $('#pan_no').val('');
        }

        // GSTIN Search and Auto-fill on Focus Out
        $('#gstin').on('focusout', function() {
            var gstin = $(this).val().trim().toUpperCase();
            $(this).val(gstin);

            if (gstin.length === 15) {
                // Auto-extract PAN (Chars 3 to 12)
                var pan = gstin.substring(2, 12);
                $('#pan_no').val(pan).prop('readonly', true);

                $.ajax({
                    url: "{{ route('search-gstin') }}",
                    type: "POST",
                    data: {
                        gstin: gstin,
                        _token: "{{ csrf_token() }}"
                    },
                    beforeSend: function() {
                        $('#gstin').addClass('loading');
                    },
                    success: function(response) {
                        $('#gstin').removeClass('loading');

                        var payload = response.data || response;

                        if (payload.company_name) {
                            $('#company_name').val(payload.company_name || '').prop('readonly', true);
                            $('#company_address').val(payload.company_address || '').prop('readonly', true);

                            // Dynamic location + PAN auto-fill from GST response/fallbacks
                            $('#pan_no').val((payload.pan_no || pan || '').toUpperCase());
                            $('#country').val(payload.country || 'India');
                            $('#state').val(payload.state || '');
                            $('#city').val(payload.city || payload.district || payload.location || '');
                            var pin = payload.pincode || payload.zip_code || '';
                            $('#postcode').val(pin);
                            $('#zip_code').val(pin);

                            alertify.success('GSTIN details fetched successfully!');
                        } else if (response.error) {
                            clearCompanyFields();
                            alertify.error(response.message);
                        } else {
                            clearCompanyFields();
                            alertify.error('No GSTIN details found');
                        }
                    },
                    error: function(xhr) {
                        $('#gstin').removeClass('loading');
                        clearCompanyFields();
                        var errorMsg = xhr.responseJSON ? xhr.responseJSON.message : 'Error fetching GSTIN details';
                        alertify.error(errorMsg);
                    }
                });
            } else if (gstin.length === 0) {
                clearCompanyFields();
            }
        });

        // GSTIN Dynamic UI
        $('#gstin').on('input', function() {
            if ($(this).val().trim() !== "") {
                $('#company-section').slideDown();
            } else {
                clearCompanyFields();
                $('#company-section').slideUp();
            }
        });

        $('#postcode').on('input', function() {
            $('#zip_code').val($(this).val().trim());
        });

        // Form Validation for Company Details
        $('#form').on('submit', function(e) {
            if ($('#gstin').val().trim() !== "") {
                var name = $('#company_name').val().trim();
                var pan = $('#pan_no').val().trim();
                var addr = $('#company_address').val().trim();
                if (name === "" || pan === "" || addr === "") {
                    alertify.error("Please fill all company details");
                    e.preventDefault();
                    return false;
                }
            }
        });
    });
</script>
@endsection
