@extends('superadmin.layouts.app')

@section('content')
    <div class="container-fluid mt-2">
        <div class="row">
            <div class="col-md-8 offset-md-2 mt-2">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            @if(isset($customer))
                                Edit Partner Customer
                            @else
                                Add New Partner Customer
                            @endif
                        </h3>
                    </div>
                    <div class="card-body">
                        @php($restrictedEdit = isset($customer))
                        @php($partnerLocked = !empty($partnerLocked))
                        @php($showGstinDerived = $restrictedEdit && !empty($customer->gstin))
                        @php($customerStatusVal = (string) (old('status2', $customer->status ?? '1')))
                        <form id="partnerCustomerForm"
                              method="POST"
                              novalidate
                              @if($partnerLocked)
                                  action="{{ route('superadmin.b2b_partners.customers.validation', ['partner_id' => $encryptedPartnerId]) }}"
                              @else
                                  action=""
                              @endif
                              data-validation-base="{{ url('/superadmin/b2b-partner-customers-validation') }}">
                            @csrf
                            @if(isset($customer))
                                <input type="hidden" name="id" value="{{ $encryptedCustomerId }}">
                            @endif

                            @if($partnerLocked && $partner)
                                <div class="row">
                                    <div class="col-lg-6 mb-3">
                                        <div class="form-group">
                                            <label for="partner_name_display">Partner name</label>
                                            <input type="text" id="partner_name_display" class="form-control bg-light" value="{{ $partner->name }}" readonly tabindex="-1">
                                        </div>
                                    </div>
                                </div>
                            @elseif(!$partnerLocked && !isset($customer))
                                <div class="row">
                                    <div class="col-lg-6 mb-3">
                                        <div class="form-group">
                                            <label for="partner_id_select">Partner <span class="text-danger">*</span></label>
                                            <select id="partner_id_select" class="form-control" required>
                                                <option value="">— Select partner —</option>
                                                @foreach(($partnersList ?? collect()) as $p)
                                                    <option value="{{ Crypt::encrypt($p->id) }}">{{ $p->name }}</option>
                                                @endforeach
                                            </select>
                                            <div class="text-muted fs-xs mt-1">Choose which approved partner this customer belongs to.</div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div class="row">
                                <div class="col-lg-6 mb-3">
                                    <div class="form-group">
                                        <label for="name">Name <span class="text-danger">*</span></label>
                                        <input type="text" name="name" id="name" class="form-control"
                                            value="{{ old('name', $customer->name ?? '') }}" required>
                                        <div class="text-danger fs-xs" id="error-name"></div>
                                    </div>
                                </div>
                            
                                <div class="col-lg-6 mb-3">
                                    <div class="form-group">
                                        <label for="email">Email <span class="text-danger">*</span></label>
                                        <input type="email" name="email" id="email" class="form-control"
                                            value="{{ old('email', $customer->email ?? '') }}" required>
                                        <div class="text-danger fs-xs" id="error-email"></div>
                                    </div>
                                </div>
                            </div>

                            {{-- <div class="row">
                                <div class="col-lg-6 mb-3">
                                    <div class="form-group">
                                        <label for="customer_no">
                                            Customer No
                                        </label>
                            
                                        <input type="text"
                                               name="customer_no"
                                               id="customer_no"
                                               class="form-control bg-light"
                                               value="{{ isset($customer) ? $customer->customer_no : \App\Models\Customer::getNextCustomerNo($partner->id ?? null) }}"
                                               readonly>
                            
                                        <div class="text-danger fs-xs" id="error-customer_no"></div>
                                    </div>
                                </div>
                            </div> --}}
                            
                            <div class="row">
                                <div class="col-lg-6 mb-3">
                                    <div class="form-group">
                                        <label for="mobile">Mobile <span class="text-danger">*</span></label>
                                        <input type="text" name="mobile" id="mobile" class="form-control"
                                            value="{{ old('mobile', $customer->mobile ?? '') }}" required>
                                        <div class="text-danger fs-xs" id="error-mobile"></div>
                                    </div>
                                </div>
                                <div class="col-lg-6 mb-3">
                                    <div class="form-group">
                                        <label for="status2">Customer status (Active / Inactive) <span class="text-danger">*</span></label>
                                        <select id="status2" name="status2" class="form-control">
                                            <option value="1" {{ $customerStatusVal === '1' || $customerStatusVal === '' ? 'selected' : '' }}>Active</option>
                                            <option value="0" {{ $customerStatusVal === '0' ? 'selected' : '' }}>Inactive</option>
                                        </select>
                                        <div class="text-danger fs-xs" id="error-status2"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-lg-6 mb-3">
                                    <div class="form-group">
                                        <label for="gstin">GSTIN <span class="text-danger">*</span></label>
                                        <div class="d-flex align-items-stretch gap-2">
                                            <input type="text" name="gstin" id="gstin" maxlength="15" autocomplete="off" class="form-control text-uppercase @if($restrictedEdit) bg-light @endif" placeholder="15-character GSTIN (e.g. 22AAAAA0000A1Z5)" value="{{ old('gstin', $customer->gstin ?? '') }}" required @if($restrictedEdit) readonly @endif>
                                            <span class="spinner-border spinner-border-sm text-primary align-self-center" id="gstin_spinner" style="display:none;" role="presentation" aria-hidden="true"></span>
                                        </div>
                                        <small class="text-muted d-block mt-1">Company details load when you leave this field (valid 15-character GSTIN).</small>
                                        <div class="text-danger fs-xs mt-1" id="error-gstin"></div>
                                    </div>
                                </div>
                            </div>
                              

                            <div id="partner_customer_gstin_derived_fields" class="{{ $showGstinDerived ? '' : 'd-none' }}">
                                <hr class="my-2">
                                <p class="small text-muted mb-2">Company &amp; address (from GSTIN lookup)</p>
                                <div class="row">
                                    <div class="col-lg-6 mb-3">
                                        <div class="form-group">
                                            <label for="pan_no">PAN No. <span class="text-danger">*</span></label>
                                            <input type="text" name="pan_no" id="pan_no" class="form-control bg-light" value="{{ old('pan_no', $customer->pan_no ?? '') }}" readonly>
                                            <div class="text-danger fs-xs" id="error-pan_no"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-lg-6 mb-3">
                                        <div class="form-group">
                                            <label for="company_name">Company Name <span class="text-danger">*</span></label>
                                            {{-- no HTML required: section is hidden until GSTIN lookup; server validates --}}
                                            <input type="text" name="company_name" id="company_name" class="form-control bg-light" value="{{ old('company_name', $customer->company_name ?? '') }}" readonly>
                                            <div class="text-danger fs-xs" id="error-company_name"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 mb-3">
                                        <div class="form-group">
                                            <label for="company_address">Company Address <span class="text-danger">*</span></label>
                                            <textarea name="company_address" id="company_address" class="form-control bg-light" rows="2" readonly>{{ old('company_address', $customer->company_address ?? '') }}</textarea>
                                            <div class="text-danger fs-xs" id="error-company_address"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-6 mb-3">
                                        <div class="form-group">
                                            <label for="country">Country <span class="text-danger">*</span></label>
                                            <input type="text" name="country" id="country" class="form-control bg-light" value="{{ old('country', $customer->country ?? '') }}" readonly>
                                            <div class="text-danger fs-xs" id="error-country"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 mb-3">
                                        <div class="form-group">
                                            <label for="state">State <span class="text-danger">*</span></label>
                                            <input type="text" name="state" id="state" class="form-control bg-light" value="{{ old('state', $customer->state ?? '') }}" readonly>
                                            <div class="text-danger fs-xs" id="error-state"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-6 mb-3">
                                        <div class="form-group">
                                            <label for="city">City <span class="text-danger">*</span></label>
                                            <input type="text" name="city" id="city" class="form-control bg-light" value="{{ old('city', $customer->city ?? '') }}" readonly>
                                            <div class="text-danger fs-xs" id="error-city"></div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 mb-3">
                                        <div class="form-group">
                                            <label for="zip_code">Pincode <span class="text-danger">*</span></label>
                                            <input type="text" name="zip_code" id="zip_code" class="form-control bg-light" value="{{ old('zip_code', $customer->zip_code ?? '') }}" readonly>
                                            <div class="text-danger fs-xs" id="error-zip_code"></div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Hidden GST derived fields (same as partner flow) --}}
                                <input type="hidden" name="legal_name" id="legal_name" value="{{ old('legal_name', $customer->legal_name ?? '') }}">
                                <input type="hidden" name="trade_name" id="trade_name" value="{{ old('trade_name', $customer->trade_name ?? '') }}">
                                <input type="hidden" name="address" id="address" value="{{ old('address', $customer->address ?? '') }}">
                                <input type="hidden" name="pincode" id="pincode" value="{{ old('pincode', $customer->pincode ?? '') }}">
                                <input type="hidden" name="gst_status" id="gst_status" value="{{ old('gst_status', $customer->gst_status ?? '') }}">
                                <input type="hidden" name="last_updated" id="last_updated" value="{{ old('last_updated', $customer->last_updated ?? '') }}">
                                <input type="hidden" name="constitution" id="constitution" value="{{ old('constitution', $customer->constitution ?? '') }}">
                                <input type="hidden" name="registration_date" id="registration_date" value="{{ old('registration_date', $customer->registration_date ?? '') }}">
                                <input type="hidden" name="nature_of_business" id="nature_of_business" value="{{ old('nature_of_business', $customer->nature_of_business ?? '') }}">
                            </div>

                            <div class="form-group text-center mt-4">
                                <button type="submit" class="btn btn-primary " id="submitBtn" @if(!$partnerLocked && !isset($customer)) disabled @endif>
                                    <div class="spinner-border spinner-border-sm float-left mt-1" id="spinner" style="display:none" role="presentation" aria-hidden="true"></div>
                                    {{ isset($customer) ? 'Update Customer' : 'Add Customer' }}
                                </button>
                                @if($partnerLocked && $encryptedPartnerId)
                                    <a href="{{ route('superadmin.b2b_partners.customers.list', ['partner_id' => $encryptedPartnerId]) }}" class="btn btn-secondary  ms-2">Cancel</a>
                                @else
                                    <a href="{{ route('superadmin.b2b_partners.list') }}" class="btn btn-secondary  ms-2">Cancel</a>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            @if(!$partnerLocked && !isset($customer))
            var validationBase = $('#partnerCustomerForm').data('validation-base');
            $('#partner_id_select').on('change', function () {
                var enc = $(this).val();
                if (enc) {
                    $('#partnerCustomerForm').attr('action', validationBase.replace(/\/$/, '') + '/' + encodeURIComponent(enc));
                    $('#submitBtn').prop('disabled', false);
                } else {
                    $('#partnerCustomerForm').attr('action', '');
                    $('#submitBtn').prop('disabled', true);
                }
            });
            @endif

            // Customer No "Auto Generate" note always present on 'Add' (no customer_no yet)
            @if(!isset($customer) || !is_numeric($customer->customer_no))
                $('#customer_no_auto_note').show();
            @endif

            var GSTIN_REGEX = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/;
            var restrictedEdit = @json($restrictedEdit);
            var lastFetchedGstin = null;

            function normalizeGstin(v) {
                return $.trim(v || '').toUpperCase();
            }

            if (restrictedEdit && $('#gstin').val()) {
                lastFetchedGstin = normalizeGstin($('#gstin').val());
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
                $('#zip_code').val('');
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
                $('#partner_customer_gstin_derived_fields').addClass('d-none');
                if (!restrictedEdit) {
                    clearDerivedValues();
                }
            }

            function showDerivedSection() {
                $('#partner_customer_gstin_derived_fields').removeClass('d-none');
            }

            function setDerivedEditable(editable) {
                var $fields = $('#company_name, #company_address, #country, #state, #city, #zip_code, #pan_no');
                $fields.prop('readonly', !editable);
                $fields.toggleClass('bg-light', !editable);
            }

            function applyGstinResponse(response) {
                $('#company_name').val(response.company_name || '');
                $('#company_address').val(response.company_address || '');
                $('#country').val(response.country || 'India');
                $('#state').val(response.state || '');
                $('#city').val(response.city || '');
                $('#zip_code').val(response.pincode || response.postcode || response.zip_code || '');
                $('#pan_no').val(response.pan_no || (normalizeGstin($('#gstin').val()).substring(2, 12)));
                $('#legal_name').val(response.legal_name || '');
                $('#trade_name').val(response.trade_name || '');
                $('#address').val(response.company_address || '');
                $('#pincode').val(response.pincode || response.postcode || response.zip_code || '');
                $('#gst_status').val(response.gst_status || '');
                $('#last_updated').val(response.last_updated || '');
                $('#constitution').val(response.constitution || '');
                $('#registration_date').val(response.registration_date || '');
                var nob = response.nature_of_business || '';
                if (Array.isArray(nob)) {
                    nob = nob.join(', ');
                }
                $('#nature_of_business').val(nob);
                setDerivedEditable(false);
            }

            var gstinFetchPromise = null;

            function fetchGstinDetails(gstin) {
                showSpinner(true);
                setGstinError('');
                gstinFetchPromise = $.ajax({
                    url: "{{ route('search-gstin') }}",
                    type: "POST",
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        gstin: gstin
                    }
                }).done(function(response) {
                    if (response && response.error) {
                        alertify.error(response.message || 'GSTIN lookup failed.');
                        setGstinError(response.message || 'Invalid or unknown GSTIN.');
                        lastFetchedGstin = null;
                        // Unlock so user can enter company details manually
                        showDerivedSection();
                        $('#pan_no').val(gstin.substring(2, 12));
                        $('#country').val($('#country').val() || 'India');
                        setDerivedEditable(true);
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
                    lastFetchedGstin = null;
                    showDerivedSection();
                    $('#pan_no').val(gstin.substring(2, 12));
                    $('#country').val($('#country').val() || 'India');
                    setDerivedEditable(true);
                }).always(function() {
                    showSpinner(false);
                    gstinFetchPromise = null;
                });
                return gstinFetchPromise;
            }

            function onGstinBlur() {
                if ($('#gstin').prop('readonly')) {
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
                if (gstin === lastFetchedGstin && $('#partner_customer_gstin_derived_fields').is(':visible') && $('#company_name').val()) {
                    return;
                }
                fetchGstinDetails(gstin);
            }

            if (!$('#gstin').prop('readonly')) {
                $('#gstin').on('blur', onGstinBlur);
            }

            function submitPartnerCustomerForm() {
                $('.text-danger.fs-xs').text('').hide();
                $('.form-control').removeClass('is-invalid');
                $('#submitBtn').attr('disabled', true);
                $('#spinner').show();

                $.ajax({
                    url: $('#partnerCustomerForm').attr('action'),
                    type: 'POST',
                    data: $('#partnerCustomerForm').serialize(),
                    success: function(response) {
                        $('#submitBtn').attr('disabled', false);
                        $('#spinner').hide();
                        if (response.error) {
                            if (response.messages) {
                                showDerivedSection();
                                for (let key in response.messages) {
                                    var msg = Array.isArray(response.messages[key]) ? response.messages[key][0] : response.messages[key];
                                    $('#error-' + key).text(msg).show();
                                    var $f = $('#' + key);
                                    if ($f.length) {
                                        $f.addClass('is-invalid');
                                    }
                                }
                            }
                            alertify.error(response.message || 'Validation Failed!');
                        } else {
                            alertify.success(response.success);
                            window.location.href = response.redirect;
                        }
                    },
                    error: function(xhr) {
                        $('#submitBtn').attr('disabled', false);
                        $('#spinner').hide();
                        if (xhr.responseJSON && xhr.responseJSON.messages) {
                            showDerivedSection();
                            for (let key in xhr.responseJSON.messages) {
                                var arr = xhr.responseJSON.messages[key];
                                var emsg = Array.isArray(arr) ? arr[0] : arr;
                                $('#error-' + key).text(emsg).show();
                                var $ef = $('#' + key);
                                if ($ef.length) {
                                    $ef.addClass('is-invalid');
                                }
                            }
                        }
                        alertify.error('Error: ' + (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : (xhr.statusText || 'Request failed')));
                    }
                });
            }

            $('#partnerCustomerForm').on('submit', function(e) {
                e.preventDefault();
                if (!$(this).attr('action')) {
                    alertify.error('Please select a partner.');
                    return;
                }

                if (!restrictedEdit) {
                    var gstin = normalizeGstin($('#gstin').val());
                    var fmtErr = validateGstinFormat(gstin);
                    if (fmtErr) {
                        setGstinError(fmtErr);
                        alertify.error(fmtErr);
                        return;
                    }

                    // If lookup still running, wait; if company empty, retry lookup once then allow manual fill
                    var proceedOrLookup = function () {
                        if ($('#company_name').val()) {
                            submitPartnerCustomerForm();
                            return;
                        }
                        alertify.message('Fetching GSTIN details…');
                        fetchGstinDetails(gstin).always(function () {
                            if ($('#company_name').val()) {
                                submitPartnerCustomerForm();
                                return;
                            }
                            showDerivedSection();
                            setDerivedEditable(true);
                            if (!$('#pan_no').val()) {
                                $('#pan_no').val(gstin.substring(2, 12));
                            }
                            if (!$('#country').val()) {
                                $('#country').val('India');
                            }
                            setGstinError('Enter company name and address (GSTIN lookup did not return them).');
                            alertify.error('Fill company details, then click Add Customer again.');
                            $('#company_name').focus();
                        });
                    };

                    if (gstinFetchPromise) {
                        gstinFetchPromise.always(proceedOrLookup);
                        return;
                    }
                    if (!$('#company_name').val()) {
                        proceedOrLookup();
                        return;
                    }
                }

                submitPartnerCustomerForm();
            });

            $('#partnerCustomerForm input, #partnerCustomerForm select, #partnerCustomerForm textarea').on('input change', function() {
                var name = $(this).attr('name');
                if (name) {
                    $('#error-' + name).text('').hide();
                }
                $(this).removeClass('is-invalid');
            });
        });
    </script>
@endpush
