@extends('superadmin.layouts.app')

@section('content')
@php
    $restrictedEdit = isset($partner) && ! $readOnly;
    $showGstinDerived = isset($partner) && !empty($partner->gstin);
    $partnerStatusVal = (string) (old('status2', (isset($partner) && ($partner->status ?? '') === 'inactive') ? '0' : '1'));
    $aadhaarMandatoryVal = (string) old('aadhaar_mandatory', $partner->aadhaar_mandatory ?? 'no');
    $discountTypeVal = strtolower(trim((string) old('partner_discount_type', $partner->partner_discount_type ?? '')));

    $partnerFieldValue = function (string $field, string $fallback = '') use ($partner) {
        $old = old($field);
        if ($old !== null) {
            if ($field === 'email') {
                return strtolower(trim((string) $old));
            }
            if (in_array($field, ['name', 'company_name', 'company_address', 'city', 'state', 'country'], true)) {
                return format_display_name((string) $old, $fallback);
            }
            return (string) $old;
        }
        if (!isset($partner)) {
            return $fallback;
        }
        $raw = (string) ($partner->{$field} ?? '');
        if ($field === 'email') {
            return strtolower(trim($raw)) ?: $fallback;
        }
        if (in_array($field, ['name', 'company_name', 'company_address', 'city', 'state', 'country'], true)) {
            return format_display_name($raw, $fallback);
        }
        return $raw !== '' ? $raw : $fallback;
    };
@endphp
<div class="container-fluid">
    <div class="row">
        <div class="col-md-8 offset-md-2 mt-2">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">
                        @if(!empty($isApproveScreen))
                            Approve Partner
                        @elseif(isset($partner))
                            Edit Partner
                        @else
                            Add New Partner
                        @endif
                    </h3>
                </div>
                <form id="partnerForm" method="POST" action="{{ $readOnly ? route('superadmin.b2b_partners.update_status', ['id' => $partner->id]) : '#' }}">
                    @csrf
                    @if(isset($partner))
                        <input type="hidden" name="id" value="{{ $partner->id }}">
                    @endif
                    <div class="card-body">
                        <div class="form-group mt-2">
                            <label for="name">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @if($readOnly) bg-light @endif" id="name" placeholder="Enter Name" value="{{ $partnerFieldValue('name') }}" @if($readOnly) readonly @endif>
                            <div class="text-danger fs-xs" id="error-name"></div>
                        </div>
                        <div class="form-group mt-2">
                            <label for="email">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control @if($readOnly) bg-light @endif" id="email" placeholder="Enter Email" value="{{ $partnerFieldValue('email') }}" @if($readOnly) readonly @endif>
                            <div class="text-danger fs-xs" id="error-email"></div>
                        </div>
                        <div class="form-group mt-2">
                            <label for="mobile">Mobile <span class="text-danger">*</span></label>
                            <input type="text" name="mobile" class="form-control @if($readOnly) bg-light @endif" id="mobile" placeholder="Enter Mobile" value="{{ old('mobile', $partner->mobile ?? '') }}" @if($readOnly) readonly @endif>
                            <div class="text-danger fs-xs" id="error-mobile"></div>
                        </div>
                        @if(! $readOnly)
                            <div class="form-group mt-2">
                                <label for="partner_discount_type">Discount Type <span class="text-danger">*</span></label>
                                <select name="partner_discount_type" id="partner_discount_type" class="form-control" required>
                                    <option value="" disabled {{ $discountTypeVal === '' ? 'selected' : '' }}>Select discount type</option>
                                    @foreach(\App\Support\PartnerDiscountType::options() as $value => $label)
                                        <option value="{{ $value }}" {{ $discountTypeVal === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <div class="text-danger fs-xs" id="error-partner_discount_type"></div>
                            </div>
                        @elseif(isset($partner))
                            <div class="form-group mt-2">
                                <label for="partner_discount_type_display">Discount Type</label>
                                <input type="text" class="form-control bg-light" id="partner_discount_type_display" readonly
                                       value="{{ \App\Support\PartnerDiscountType::label($discountTypeVal) ?? '—' }}">
                            </div>
                        @endif
                        @php
                            $creditLimitVal = old('credit_limit', $partner->credit_limit ?? '');
                            $creditAllocationEditable = empty($readOnly) || !empty($isApproveScreen);
                        @endphp
                        <div class="form-group mt-2">
                            <label for="credit_limit">Credit Allocation (₹) <span class="text-danger">*</span></label>
                            <input type="number"
                                   step="0.01"
                                   min="0"
                                   name="credit_limit"
                                   id="credit_limit"
                                   class="form-control @if(!$creditAllocationEditable) bg-light @endif"
                                   placeholder="e.g. 100000"
                                   value="{{ $creditLimitVal }}"
                                   @if(!$creditAllocationEditable) readonly @endif>
                            <div class="text-danger fs-xs" id="error-credit_limit"></div>
                            <small class="text-muted d-block mt-1">
                                Consumable purchase quota. Activated orders reduce available balance; paying invoices does not restore it.
                            </small>
                            @if(isset($partner))
                                <small class="text-muted d-block">
                                    Consumed: ₹{{ number_format((float) ($creditUsed ?? 0), 2) }}
                                    · Available: ₹{{ number_format((float) ($creditAvailable ?? 0), 2) }}
                                </small>
                            @endif
                        </div>
                        @if(! $readOnly)
                            <input type="hidden" name="status2" id="status2_submit" value="{{ $partnerStatusVal === '0' ? '0' : '1' }}">
                            @if(!isset($partner))
                                <div class="form-group mt-3">
                                    <label for="status2_display">Partner status (Active / Inactive) <span class="text-danger">*</span></label>
                                    <select id="status2_display" class="form-control bg-light" tabindex="-1" disabled>
                                        <option value="1" {{ $partnerStatusVal === '1' || $partnerStatusVal === '' ? 'selected' : '' }}>Active</option>
                                        <option value="0" {{ $partnerStatusVal === '0' ? 'selected' : '' }}>Inactive</option>
                                    </select>
                                    <div class="text-danger fs-xs" id="error-status2"></div>
                                </div>
                            @endif
                        @endif

                        {{-- GSTIN: blur lookup; company block hidden on add until success (same pattern as B2C) --}}
                        <div class="form-group mt-3">
                            <label for="gstin">GSTIN <span class="text-danger">*</span></label>
                            <div class="d-flex align-items-stretch gap-2">
                                <input type="text" name="gstin" class="form-control text-uppercase @if($readOnly || $restrictedEdit) bg-light @endif" id="gstin" maxlength="15" autocomplete="off"
                                       placeholder="15-character GSTIN (e.g. 22AAAAA0000A1Z5)"
                                       value="{{ old('gstin', $partner->gstin ?? '') }}" @if($readOnly || $restrictedEdit) readonly @endif>
                                <span class="spinner-border spinner-border-sm text-primary align-self-center" id="gstin_spinner" style="display:none;" role="presentation" aria-hidden="true"></span>
                            </div>
                            <small class="text-muted d-block mt-1">Company details load when you leave this field (valid 15-character GSTIN).</small>
                            <div class="text-danger fs-xs mt-1" id="error-gstin"></div>
                        </div>

                        <div id="b2b_gstin_derived_fields" class="{{ $showGstinDerived ? '' : 'd-none' }}">
                            {{-- <hr class="my-3"> --}}
                            {{-- <p class="small text-muted mb-2">Company &amp; registration (from GSTIN lookup)</p> --}}
                            <div class="form-group mt-2">
                                <label for="pan_no">PAN No. <span class="text-danger">*</span></label>
                                <input type="text" name="pan_no" class="form-control bg-light text-uppercase" id="pan_no" placeholder="PAN No." value="{{ strtoupper((string) old('pan_no', $partner->pan_no ?? '')) }}" readonly>
                                <div class="text-danger fs-xs" id="error-pan_no"></div>
                            </div>

                            <div class="form-group mt-2">
                                <label for="aadhaar_mandatory">Aadhaar Mandatory <span class="text-danger">*</span></label>
                                <select name="aadhaar_mandatory" id="aadhaar_mandatory" class="form-control @if($readOnly) bg-light @endif" @if($readOnly) disabled @endif>
                                    <option value="yes" {{ $aadhaarMandatoryVal === 'yes' ? 'selected' : '' }}>Yes</option>
                                    <option value="no" {{ $aadhaarMandatoryVal !== 'yes' ? 'selected' : '' }}>No</option>
                                </select>
                                <div class="text-danger fs-xs" id="error-aadhaar_mandatory"></div>
                            </div>

                            <div class="form-group mt-2">
                                <label for="company_name">Company Name <span class="text-danger">*</span></label>
                                <input type="text" name="company_name" class="form-control bg-light" id="company_name" placeholder="Company Name" value="{{ $partnerFieldValue('company_name') }}" readonly>
                                <div class="text-danger fs-xs" id="error-company_name"></div>
                            </div>
                            <div class="form-group mt-2">
                                <label for="company_address">Company Address <span class="text-danger">*</span></label>
                                <input type="text" name="company_address" class="form-control bg-light" id="company_address" placeholder="Company Address" value="{{ $partnerFieldValue('company_address') }}" readonly>
                                <div class="text-danger fs-xs" id="error-company_address"></div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mt-2">
                                        <label for="country">Country <span class="text-danger">*</span></label>
                                        <input type="text" name="country" class="form-control bg-light" id="country" placeholder="Country" value="{{ $partnerFieldValue('country') }}" readonly>
                                        <div class="text-danger fs-xs" id="error-country"></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mt-2">
                                        <label for="state">State <span class="text-danger">*</span></label>
                                        <input type="text" name="state" class="form-control bg-light" id="state" placeholder="State" value="{{ $partnerFieldValue('state') }}" readonly>
                                        <div class="text-danger fs-xs" id="error-state"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mt-2">
                                        <label for="city">City <span class="text-danger">*</span></label>
                                        <input type="text" name="city" class="form-control bg-light" id="city" placeholder="City" value="{{ $partnerFieldValue('city') }}" readonly>
                                        <div class="text-danger fs-xs" id="error-city"></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mt-2">
                                        <label for="postcode">Pincode <span class="text-danger">*</span></label>
                                        <input type="text" name="postcode" class="form-control bg-light" id="postcode" placeholder="Pincode" value="{{ old('postcode', $partner->postcode ?? '') }}" readonly>
                                        <div class="text-danger fs-xs" id="error-postcode"></div>
                                    </div>
                                </div>
                            </div>

                            {{-- Additional GST-derived fields saved in partners table (add flow) --}}
                            <input type="hidden" name="legal_name" id="legal_name" value="{{ old('legal_name', $partner->legal_name ?? '') }}">
                            <input type="hidden" name="trade_name" id="trade_name" value="{{ old('trade_name', $partner->trade_name ?? '') }}">
                            <input type="hidden" name="address" id="address" value="{{ old('address', $partner->address ?? '') }}">
                            <input type="hidden" name="pincode" id="pincode" value="{{ old('pincode', $partner->pincode ?? '') }}">
                            <input type="hidden" name="gst_status" id="gst_status" value="{{ old('gst_status', $partner->gst_status ?? '') }}">
                            <input type="hidden" name="last_updated" id="last_updated" value="{{ old('last_updated', $partner->last_updated ?? '') }}">
                            <input type="hidden" name="constitution" id="constitution" value="{{ old('constitution', $partner->constitution ?? '') }}">
                            <input type="hidden" name="registration_date" id="registration_date" value="{{ old('registration_date', $partner->registration_date ?? '') }}">
                            <input type="hidden" name="nature_of_business" id="nature_of_business" value="{{ old('nature_of_business', $partner->nature_of_business ?? '') }}">
                        </div>

                        @if($readOnly)
                            <div class="form-group mt-2">
                                <label for="status">Update Status</label>
                                <select name="status2" id="status2" class="form-control" required>
                                    <option value="approved" {{ old('status', $partner->status ?? '') == 'approved' ? 'selected' : '' }}>Approve</option>
                                    <option value="rejected" {{ old('status', $partner->status ?? '') == 'rejected' ? 'selected' : '' }}>Reject</option>
                                </select>
                                <div class="text-danger fs-xs" id="error-status2"></div>
                            </div>
                        @endif
                    </div>
                    <div class="card-footer">
                        @if($readOnly)
                            <button type="submit" class="btn btn-sm btn-secondary ml-2" id="approveRejectBtn">
                                <span class="spinner-border spinner-border-sm me-1" id="spinner" style="display:none;"></span>
                                Update Status
                            </button>
                        @else
                            <button type="submit" class="btn btn-sm btn-secondary ml-2" id="submitBtn">
                                <span class="spinner-border spinner-border-sm me-1" id="spinner" style="display:none;"></span>
                                Submit
                            </button>
                        @endif
                        <a href="{{ url('superadmin/b2b-partners-list') }}" class="btn btn-sm btn-secondary ml-2">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        var GSTIN_REGEX = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/;
        var readOnly = @json(!empty($readOnly));
        var restrictedEdit = @json(!empty($restrictedEdit));
        var lastFetchedGstin = null;

        function normalizeGstin(v) {
            return $.trim(v || '').toUpperCase();
        }

        function validateGstinFormat(gstin) {
            if (!gstin || gstin.length !== 15) {
                return 'GSTIN must be exactly 15 characters.';
            }
            if (!GSTIN_REGEX.test(gstin)) {
                return 'Please enter a valid GSTIN (e.g. 22AAAAA0000A1Z5).';
            }
            return null;
        }

        function setGstinError(msg) {
            if (msg) {
                $('#error-gstin').text(msg).show();
                $('#gstin').addClass('is-invalid');
            } else {
                $('#error-gstin').text('').hide();
                $('#gstin').removeClass('is-invalid');
            }
        }

        function showSpinner(show) {
            $('#gstin_spinner').toggle(!!show);
        }

        function clearDerivedValues() {
            $('#pan_no').val('');
            $('#company_name').val('');
            $('#company_address').val('');
            $('#country').val('');
            $('#state').val('');
            $('#city').val('');
            $('#postcode').val('');
            $('#legal_name').val('');
            $('#trade_name').val('');
            $('#address').val('');
            $('#pincode').val('');
            $('#gst_status').val('');
            $('#last_updated').val('');
            $('#constitution').val('');
            $('#registration_date').val('');
            $('#nature_of_business').val('');
        }

        function hideDerivedSection() {
            $('#b2b_gstin_derived_fields').addClass('d-none');
            if (!restrictedEdit) {
                clearDerivedValues();
            }
        }

        function showDerivedSection() {
            $('#b2b_gstin_derived_fields').removeClass('d-none');
        }

        function formatDisplayName(value) {
            var text = String(value == null ? '' : value).trim();
            if (text === '') {
                return '';
            }
            return text.toLowerCase().replace(/(^|\s)\S/g, function (match) {
                return match.toUpperCase();
            });
        }

        function applyGstinResponse(response) {
            $('#company_name').val(formatDisplayName(response.company_name || ''));
            $('#company_address').val(formatDisplayName(response.company_address || ''));
            $('#country').val(formatDisplayName(response.country || ''));
            $('#state').val(formatDisplayName(response.state || ''));
            $('#city').val(formatDisplayName(response.city || ''));
            $('#postcode').val(response.pincode || response.postcode || '');
            $('#pan_no').val(String(response.pan_no || '').toUpperCase());
            $('#legal_name').val(formatDisplayName(response.legal_name || ''));
            $('#trade_name').val(formatDisplayName(response.trade_name || ''));
            $('#address').val(formatDisplayName(response.company_address || ''));
            $('#pincode').val(response.pincode || response.postcode || '');
            $('#gst_status').val(response.gst_status || '');
            $('#last_updated').val(response.last_updated || '');
            $('#constitution').val(response.constitution || '');
            $('#registration_date').val(response.registration_date || '');
            var nob = response.nature_of_business || '';
            if (Array.isArray(nob)) {
                nob = nob.join(', ');
            }
            $('#nature_of_business').val(nob);
        }

        function fetchGstinDetails(gstin) {
            showSpinner(true);
            setGstinError('');
            $.ajax({
                url: "{{ route('search-gstin') }}",
                type: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    gstin: gstin
                }
            }).done(function(response) {
                if (response && response.error) {
                    alertify.error(response.message || 'GSTIN lookup failed.');
                    setGstinError(response.message || 'Invalid or unknown GSTIN.');
                    if (!restrictedEdit) {
                        hideDerivedSection();
                    }
                    lastFetchedGstin = null;
                    return;
                }
                applyGstinResponse(response);
                showDerivedSection();
                lastFetchedGstin = gstin;
            }).fail(function(xhr) {
                var msg = 'Could not fetch GSTIN details.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                alertify.error(msg);
                setGstinError(msg);
                if (!restrictedEdit) {
                    hideDerivedSection();
                }
                lastFetchedGstin = null;
            }).always(function() {
                showSpinner(false);
            });
        }

        if (restrictedEdit && $('#gstin').val()) {
            lastFetchedGstin = normalizeGstin($('#gstin').val());
        }

        function onGstinBlur() {
            if (readOnly || $('#gstin').prop('readonly')) {
                return;
            }
            var raw = $('#gstin').val();
            var gstin = normalizeGstin(raw);
            if (gstin !== raw) {
                $('#gstin').val(gstin);
            }
            if (!gstin) {
                setGstinError('');
                if (!restrictedEdit) {
                    hideDerivedSection();
                }
                lastFetchedGstin = null;
                return;
            }
            var fmtErr = validateGstinFormat(gstin);
            if (fmtErr) {
                setGstinError(fmtErr);
                alertify.error(fmtErr);
                if (!restrictedEdit) {
                    hideDerivedSection();
                }
                lastFetchedGstin = null;
                return;
            }
            setGstinError('');
            if (gstin === lastFetchedGstin && $('#b2b_gstin_derived_fields').is(':visible') && $('#company_name').val()) {
                return;
            }
            fetchGstinDetails(gstin);
        }

        if (!readOnly && !$('#gstin').prop('readonly')) {
            $('#gstin').on('blur', onGstinBlur);
        }

        $('#name').on('blur', function () {
            if (readOnly) {
                return;
            }
            var formatted = formatDisplayName($(this).val());
            if (formatted) {
                $(this).val(formatted);
            }
        });

        $('#email').on('blur', function () {
            if (readOnly) {
                return;
            }
            $(this).val(String($(this).val() || '').trim().toLowerCase());
        });

        $('#partnerForm').on('submit', function(e) {
            e.preventDefault();
            var submitButton = $(this).find('#submitBtn, #approveRejectBtn');
            submitButton.attr('disabled', true);
            submitButton.find('#spinner').show();

            $('.text-danger.fs-xs').text('').hide();
            $('.form-control').removeClass('is-invalid');

            var postUrl = readOnly
                ? $(this).attr('action')
                : "{{ url('superadmin/b2b-partner-validation') }}";

            $.ajax({
                url: postUrl,
                type: 'POST',
                data: new FormData(this),
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    submitButton.attr('disabled', false);
                    submitButton.find('#spinner').hide();

                    if (response.error) {
                        if (response.messages) {
                            $.each(response.messages, function(key, value) {
                                var msg = Array.isArray(value) ? value[0] : value;
                                $('#error-' + key).text(msg).show();
                                var $field = $('#' + key);
                                if ($field.length) {
                                    $field.addClass('is-invalid');
                                }
                            });
                        } else if (response.message) {
                            alertify.error(response.message);
                        }
                    } else if (response.success) {
                        alertify.success(response.success);
                        if (response.redirect) {
                            setTimeout(function() {
                                window.location.href = response.redirect;
                            }, 1000);
                        }
                    }
                },
                error: function(xhr) {
                    submitButton.attr('disabled', false);
                    submitButton.find('#spinner').hide();
                    alertify.error('An error occurred: ' + xhr.statusText);
                }
            });
        });

        $('#partnerForm input, #partnerForm select').on('input change', function() {
            var name = $(this).attr('name');
            if (name) {
                $('#error-' + name).text('').hide();
            }
            $(this).removeClass('is-invalid');
        });
    });
</script>
@endpush
