@extends('superadmin.layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8 col-xl-7 offset-lg-2 offset-xl-2 mt-2">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title mb-0">{{ isset($user) ? 'Edit Customer' : 'Add New Customer' }}</h3>
                </div>
                <form id="customerForm" method="POST">
                    @csrf
                    @if(isset($user))
                        <input type="hidden" name="id" value="{{ $user->id }}">
                    @endif
                    @php($restrictedEdit = isset($user))
                    @php($showGstinDerived = $restrictedEdit && !empty($user->gstin))
                    @php($statusVal = (string) (old('status2', $user->status ?? '1')))
                    <div class="card-body">
                        <div class="form-group mt-2">
                            <label for="name">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" id="name" placeholder="Enter Name" value="{{ old('name', $user->name ?? '') }}">
                            <div class="text-danger fs-xs" id="error-name"></div>
                        </div>
                        <div class="form-group mt-2">
                            <label for="email">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" id="email" placeholder="Enter Email" value="{{ old('email', $user->email ?? '') }}">
                            <div class="text-danger fs-xs" id="error-email"></div>
                        </div>
                        <div class="form-group mt-2">
                            <label for="mobile">Mobile <span class="text-danger">*</span></label>
                            <input type="text" name="mobile" class="form-control" id="mobile" placeholder="Enter Mobile" value="{{ old('mobile', $user->mobile ?? '') }}">
                            <div class="text-danger fs-xs" id="error-mobile"></div>
                        </div>

                        <div class="form-group mt-3">
                            <label for="status2">Customer status  <span class="text-danger">*</span></label>
                            {{-- @if(!$restrictedEdit)
                                <input type="hidden" name="status2" id="status2_submit" value="{{ $statusVal === '0' ? '0' : '1' }}">
                            @endif --}}
                            <select name="status2" id="status2" class="form-control">
                                <option value="1" {{ $statusVal === '1' || $statusVal === '' ? 'selected' : '' }}>Active</option>
                                <option value="0" {{ $statusVal === '0' ? 'selected' : '' }}>Inactive</option>
                            </select>
                            <div class="text-danger fs-xs" id="error-status2"></div>
                        </div>

                        <div class="form-group mt-3">
                            <label for="gstin">GSTIN <span class="text-danger">*</span></label>
                            <div class="d-flex align-items-stretch gap-2">
                                <input type="text" name="gstin" class="form-control text-uppercase @if($restrictedEdit) bg-light @endif" id="gstin" maxlength="15" autocomplete="off"
                                       placeholder="15-character GSTIN (e.g. 22AAAAA0000A1Z5)"
                                       value="{{ old('gstin', $user->gstin ?? '') }}" @if($restrictedEdit) readonly @endif>
                                {{-- <div class="d-flex align-items-center flex-shrink-0" id="gstin_spinner_wrap" style="min-width: 2rem;"> --}}
                                    <span class="spinner-border spinner-border-sm text-primary" id="gstin_spinner" role="presentation" style="display:none;" aria-hidden="true"></span>
                                {{-- </div> --}}
                            </div>
                            <small class="text-muted d-block mt-1">Details load automatically when you leave this field (valid format required).</small>
                            <div class="text-danger fs-xs mt-1" id="error-gstin"></div>
                        </div>

                        <div id="b2c_gstin_derived_fields" class="{{ $showGstinDerived ? '' : 'd-none' }}">
                            {{-- <hr class="my-3"> --}}
                            {{-- <p class="small text-muted mb-3 mb-md-2">Company &amp; address (filled from GSTIN; you can edit if needed)</p> --}}

                            <div class="form-group mt-2">
                                <label for="pan_no">PAN No. <span class="text-danger">*</span></label>
                                <input type="text" name="pan_no" class="form-control bg-light" id="pan_no" placeholder="PAN No." value="{{ old('pan_no', $user->pan_no ?? '') }}" readonly>
                                <div class="text-danger fs-xs" id="error-pan_no"></div>
                            </div>

                            <div class="form-group mt-2">
                                <label for="company_name">Company Name <span class="text-danger">*</span></label>
                                <input type="text" name="company_name" class="form-control bg-light" id="company_name" placeholder="Company Name" value="{{ old('company_name', $user->company_name ?? '') }}" readonly>
                                <div class="text-danger fs-xs" id="error-company_name"></div>
                            </div>
                            <div class="form-group mt-2">
                                <label for="company_address">Company Address <span class="text-danger">*</span></label>
                                <input type="text" name="company_address" class="form-control bg-light" id="company_address" placeholder="Company Address" value="{{ old('company_address', $user->company_address ?? '') }}" readonly>
                                <div class="text-danger fs-xs" id="error-company_address"></div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mt-2">
                                        <label for="country">Country <span class="text-danger">*</span></label>
                                        <input type="text" name="country" class="form-control bg-light" id="country" placeholder="Country" value="{{ old('country', $user->country ?? '') }}" readonly>
                                        <div class="text-danger fs-xs" id="error-country"></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mt-2">
                                        <label for="state">State <span class="text-danger">*</span></label>
                                        <input type="text" name="state" class="form-control bg-light" id="state" placeholder="State" value="{{ old('state', $user->state ?? '') }}" readonly>
                                        <div class="text-danger fs-xs" id="error-state"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mt-2">
                                        <label for="city">City <span class="text-danger">*</span></label>
                                        <input type="text" name="city" class="form-control bg-light" id="city" placeholder="City" value="{{ old('city', $user->city ?? '') }}" readonly>
                                        <div class="text-danger fs-xs" id="error-city"></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mt-2">
                                        <label for="postcode">Pincode <span class="text-danger">*</span></label>
                                        <input type="text" name="postcode" class="form-control bg-light" id="postcode" placeholder="Pincode" value="{{ old('postcode', $user->postcode ?? '') }}" readonly>
                                        <div class="text-danger fs-xs" id="error-postcode"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <span class="spinner-border spinner-border-sm me-1" id="spinner" style="display:none;"></span>
                            Submit
                        </button>
                        <a href="{{ url('superadmin/b2c-customers-list') }}" class="btn btn-secondary ms-2">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function () {
        var GSTIN_REGEX = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/;
        var restrictedEdit = @json($restrictedEdit);
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
        }

        function hideDerivedSection() {
            $('#b2c_gstin_derived_fields').addClass('d-none');
            if (!restrictedEdit) {
                clearDerivedValues();
            }
        }

        function showDerivedSection() {
            $('#b2c_gstin_derived_fields').removeClass('d-none');
        }

        function applyGstinResponse(response) {
            $('#company_name').val(response.company_name || '');
            $('#company_address').val(response.company_address || '');
            $('#country').val(response.country || '');
            $('#state').val(response.state || '');
            $('#city').val(response.city || '');
            $('#postcode').val(response.pincode || response.postcode || '');
            $('#pan_no').val(response.pan_no || '');
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
            }).done(function (response) {
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
            }).fail(function (xhr) {
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
            }).always(function () {
                showSpinner(false);
            });
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
            if (gstin === lastFetchedGstin && $('#b2c_gstin_derived_fields').is(':visible') && $('#company_name').val()) {
                return;
            }
            fetchGstinDetails(gstin);
        }

        if (!$('#gstin').prop('readonly')) {
            $('#gstin').on('blur', onGstinBlur);
        }

        $('#customerForm').on('submit', function (e) {
            e.preventDefault();
            $('#submitBtn').attr('disabled', true);
            $('#spinner').show();

            $('.text-danger.fs-xs').text('');
            $('.form-control').removeClass('is-invalid');

            $.ajax({
                url: "{{ url('superadmin/b2c-customers-validation') }}",
                type: 'POST',
                data: new FormData(this),
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (response) {
                    $('#submitBtn').attr('disabled', false);
                    $('#spinner').hide();

                    if (response.error) {
                        if (response.messages) {
                            $.each(response.messages, function (key, value) {
                                var msg = Array.isArray(value) ? value[0] : value;
                                $('#error-' + key).text(msg).show();
                                $('#' + key).addClass('is-invalid');
                            });
                        } else if (response.message) {
                            alertify.error(response.message);
                        }
                    } else if (response.success) {
                        alertify.success(response.success);
                        if (response.redirect) {
                            setTimeout(function () {
                                window.location.href = response.redirect;
                            }, 1000);
                        }
                    }
                },
                error: function (xhr) {
                    $('#submitBtn').attr('disabled', false);
                    $('#spinner').hide();
                    alertify.error('An error occurred: ' + xhr.statusText);
                }
            });
        });

        $('#customerForm input, #customerForm select').on('input change', function () {
            var name = $(this).attr('name');
            if (name) {
                $('#error-' + name).text('');
            }
            $(this).removeClass('is-invalid');
        });
    });
</script>
@endpush
