@extends('home.render')

@section('cssLinks')
    <!-- use cdn for alertify -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/alertify.min.css" />
    {{-- google font  --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
@endsection
@section('metaTitle')
    <title>Tizzy Cloud</title>
    <meta name="description" content="" />
    <meta name="keywords" content="" />
    <style>
        .domain-suggestions-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #ffffff;
            border: 1px solid #eee;
            z-index: 1000;
            max-height: 350px;
            overflow-y: auto;
            border-radius: 12px;
            margin-top: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            padding: 8px;
        }

        .suggestion-item {
            padding: 12px 16px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
            border-radius: 8px;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .suggestion-item:hover {
            background-color: #f8f9ff;
            border-color: #e0e4ff;
        }

        .suggestion-item.taken {
            cursor: not-allowed;
            opacity: 0.7;
            background-color: #f9f9f9;
        }

        .suggestion-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .suggestion-name {
            font-weight: 600;
            color: #333;
            font-size: 1rem;
        }

        .suggestion-item.available .suggestion-name {
            color: #2e7d32;
        }

        .suggestion-item.taken .suggestion-name {
            color: #666;
        }

        .status-badge {
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-available {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .status-taken {
            background-color: #f5f5f5;
            color: #757575;
        }

        .suggestion-right {
            display: flex;
            align-items: center;
            gap: 8px;
            text-align: right;
        }

        .current-price {
            color: #1a1a1a;
            font-weight: 700;
            font-size: 1rem;
        }

        .price-suffix {
            color: #777;
            font-weight: 400;
            font-size: 0.85rem;
        }

        .domain-input .spinner-border {
            color: #1665b6;
        }

        .search-icon-input {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
            font-size: 1.1rem;
        }

        .domain-btn {
            border-radius: 11px;
        }

        /* Ensure the containers don't clip the absolute dropdown */
        .cart-plan-box,
        .domain-section,
        .domain-box {
            overflow: visible !important;
        }

        .subtotal-section {
            border: 0px solid transparent !important;
            padding-right: 0px !important;
            padding-left: 0px !important;
        }

        .form-control:focus {
            background-color: transparent !important;
        }

        .disabled-section {
            pointer-events: none;
            opacity: 0.5;
            filter: grayscale(0.5);
        }

        .selected-domain-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8f9ff;
            border: 1px solid #e0e4ff;
            padding: 15px 25px;
            border-radius: 12px;
            margin-top: 20px;
        }

        .selected-domain-name {
            font-weight: 700;
            color: #1665b6;
            font-size: 1.2rem;
        }

        .delete-domain-btn {
            color: #dc3545;
            cursor: pointer;
            font-size: 1.3rem;
            transition: transform 0.2s;
        }

        .delete-domain-btn:hover {
            color: #a71d2a;
            transform: scale(1.1);
        }

        .alertify-notifier {
            color: #fff;
        }

        .cross {
            color: #fff;
            cursor: pointer;
            font-size: 0.9rem;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s ease;
            background: #1d56b0;
            font-style: normal;
        }
        .cross:hover {
            background: #ff4d4d;
            color: #fff;
            transform: rotate(90deg);
        }

        .is-invalid {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25) !important;
        }

        .is-invalid + .invalid-feedback {
            display: block;
        }
       .input-group{
        flex-wrap: nowrap !important;
       }
    </style>
@endsection

<!-- select plan section -->
@section('Body')
    <!-- checkout content -->
    <section class="checkout-section select-plan-section position-relative">
        <div class="container">
            <h2 class="your-cart-heading">Your Cart</h2>
            <div class="cart-progress">
                <div class="step-wrapper">
                    <div class="step">Select Plan</div>
                    <div class="step active">Checkout</div>
                    <div class="step">Payment</div>
                </div>
                <div class="back-btn">← Back</div>
            </div>
            <div class="row mt-40">
                <div class="col-lg-8 col-md-7 col-12">
                    <div class="cart-plan-box mb-30" id="domain-setup-box">
                        <h3 class="plan-box-title div-text mb-0"><img
                                src="{{ asset('public/home/images/icons/world-wide-web.png') }}" alt="world-wide-web"> Set
                            Up Business Email on Your Domain</h3>
                        <div class="domain-section">
                            <div id="domain-input-container">
                                <form id="domain-form" class="domain-box">
                                    <div class="domain-input position-relative w-100">
                                        <i class="fa fa-globe search-icon-input"></i>
                                        <input type="text" name="domain_name" id="domain_name"
                                            placeholder="Enter Domain Name (e.g. tizzycloud)" autocomplete="off"
                                            class="form-control" style="height: 55px; border-radius: 10px;">
                                        <div id="domain-suggestions" class="domain-suggestions-dropdown"
                                            style="display: none;"></div>
                                        <div id="domain-loader" class="spinner-border spinner-border-sm position-absolute"
                                            role="status" style="right: 15px; top: 18px; display: none;"></div>
                                    </div>
                                    <button type="submit" class="domain-btn">Add</button>
                                </form>
                            </div>
                            <div id="selected-domain-container" style="display: none;">
                                <div class="selected-domain-bar">
                                    <span class="selected-domain-name" id="display-selected-domain"></span>
                                    <i class="fa fa-trash-o delete-domain-btn" id="remove-domain" title="Delete Domain"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="account-card card-bg disabled-section" id="account-section">
                        {{-- <div class="plan-box-title">
                            <label class="p-content mb-0"><input type="radio" name="acctOption" value="have"> Already have an account?</label>  
                        </div> --}}
                        <div class="register-box">
                            {{--  <div class="account-toggle d-flex align-items-center mb-20">
                            <label class="p-content mb-0"><input type="radio" name="acctOption" value="register" checked> Register</label>
                            </div>    --}}
                            <form class="dashboard-form" id="checkout-form">
                                @csrf
                                <input type="hidden" name="order_id" id="form_order_id">
                                <input type="hidden" name="total_amount" id="form_total_amount">
                                <input type="hidden" name="tax_amount" id="form_tax_amount">
                                <input type="hidden" name="subtotal" id="form_subtotal">
                                <input type="hidden" name="discount_amount" id="form_discount_amount">
                                <input type="hidden" name="domain_name" id="form_domain_name">
                                <input type="hidden" name="promo_code" id="form_promo_code">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">First Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="first_name" id="first_name">
                                        <div class="text-danger" id="error-first_name"></div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="last_name" id="last_name">
                                        <div class="text-danger" id="error-last_name"></div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email address <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" name="email" id="email">
                                        <div class="text-danger" id="error-email"></div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Phone number <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="text" id="mobile" class="js-states form-control"
                                                placeholder="Phone Number" name="mobile">
                                        </div>
                                        <div class="text-danger" id="error-mobile"></div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Country <span class="text-danger">*</span></label>
                                        <select class="form-select form-control" name="country" id="country_select">
                                            <option value="101" selected>India</option>
                                        </select>
                                        <div class="text-danger" id="error-country"></div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">State <span class="text-danger">*</span></label>
                                        <select class="form-select form-control" name="state" id="state_select">
                                            <option value="">Select State</option>
                                        </select>
                                        <div class="text-danger" id="error-state"></div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">City <span class="text-danger">*</span></label>
                                        <select class="form-select form-control" name="city" id="city_select">
                                            <option value="">Select City</option>
                                        </select>
                                        <div class="text-danger" id="error-city"></div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Postcode <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="postcode" id="postcode">
                                        <div class="text-danger" id="error-postcode"></div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="companyDetails"
                                                name="add_company_details">
                                            <label class="form-check-label" for="companyDetails">Add company
                                                details</label>
                                        </div>
                                    </div>

                                    <div class="col-md-12 col-12" id="gstin_fields" style="display: none;">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">GSTIN <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="gstin" id="gstin" placeholder="Enter 15 digit GSTIN" style="text-transform: uppercase; height: 45px;">
                                                    <button class="btn btn-outline-primary" type="button" id="search-gstin-btn" style="border-radius: 0 10px 10px 0;">Search</button>
                                                </div>
                                                <div class="text-danger" id="error-gstin"></div>
                                            </div>
                                            <div class="col-md-6" id="company_name_group" style="display: none;">
                                                <label class="form-label">Company Name</label>
                                                <input type="text" class="form-control" name="company_name" id="company_name" readonly style="background-color: #f8f9fa; cursor: not-allowed;">
                                            </div>
                                            <div class="col-md-12" id="company_address_group" style="display: none;">
                                                <label class="form-label">Company Address</label>
                                                <textarea class="form-control" name="company_address" id="company_address" readonly rows="2" style="background-color: #f8f9fa; cursor: not-allowed;"></textarea>
                                            </div>
                                        </div>
                                        <input type="hidden" name="last_updated" id="last_updated">
                                        <input type="hidden" name="constitution" id="constitution">
                                        <input type="hidden" name="registration_date" id="registration_date">
                                        <input type="hidden" name="nature_of_business" id="nature_of_business">
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-5 col-12">
                    <div class="order-summary-box disabled-section" id="summary-section">
                        <h3 class="summary-title">Order Summary</h3>

                        <div class="plan-controls">
                            <div class="summary-item d-flex justify-content-between align-items-center"
                                id="summary-domain-row"
                                style="border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 10px; display: none;">
                                <span id="summary-domain-name" class="text-muted" style="font-size: 0.9rem;">No domain
                                    selected</span>
                                <i class="fa fa-times cross" id="remove-sidebar-domain"
                                    title="Remove Domain">x</i>
                            </div>

                            <div class="summary-item-header">
                                <span class="item-name" id="display-plan-name"></span>
                                <span class="item-price" id="display-mailboxes"></span>
                            </div>



                            <div class="summary-item">
                                <span class="item-description">
                                    <span class="color-000000" id="display-duration"></span>
                                </span>
                                <span class="item-price-value" id="plan-price-display">₹ 0.00</span>
                            </div>

                            <div class="summary-item" id="summary-discount-row" style="display: none; color: #2e7d32;">
                                <div>
                                    <span class="item-description" style="color: #2e7d32;">Discount (<span id="discount-percent-display">0</span>%)</span>
                                </div>
                                <span class="item-price-value" id="summary-discount-amount">₹ 0.00</span>
                            </div>

                            <div class="summary-item">
                                <div>
                                    <span class="item-description">Taxes (GST 18%)</span>
                                    <div class="tooltip-box">
                                        <img src="{{ asset('public/home/images/icons/question-mark.png') }}"
                                            alt="question mark">
                                        <span class="tooltip-text">Goods and Services Tax (GST) calculated at 18% of
                                            subtotal.</span>
                                    </div>
                                </div>
                                <span class="item-price-value" id="summary-gst">₹ 0.00</span>
                            </div>
                                {{-- <div class="subtotal-section mt-40">
                                <span class="subtotal-label">Subtotal</span>
                                <span class="subtotal-amount" id="summary-subtotal">₹ 499.00</span>
                                </div> --}}
                            <div class="subtotal-section pt-0"
                                style="border-top: 1px solid #eee; padding-top: 15px; margin-top: 10px;">
                                <span class="subtotal-label" style="font-weight: 700; font-size: 1.2rem;">Total</span>
                                <span class="subtotal-amount" id="summary-total"
                                    style="font-weight: 700; font-size: 1.2rem;">₹ 0.00</span>
                            </div>
                            {{-- <p class="gst-info text-center mt-3" style="font-size: 0.8rem; color: #888;">Prices are in INR</p> --}}

                            <div class="input-group coupon-code">
                                <input type="text" class="form-control" id="promo_code_input" placeholder="Have a coupon code?"
                                    aria-label="Recipient's username" aria-describedby="button-addon2">
                                <button class="apply-btn" id="apply-promo-btn" type="button">Apply</button>
                            </div>
                            <button type="submit" form="checkout-form" class="checkout-btn" id="secure-checkout-btn">
                                <span id="spinner" class="spinner-border spinner-border-sm me-2" role="status" style="display: none;"></span>
                                <span id="btn-text">Continue to Secure Checkout</span>
                            </button>

                            <div class="guarantee-badge">
                                <svg width="20" height="20" viewbox="0 0 24 24" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 1L3 6V12C3 18.6 12 24 12 24S21 18.6 21 12V6L12 1Z" fill="#32a953"
                                        stroke="#32a953" stroke-width="2" stroke-linejoin="round" />
                                    <path d="M10 14L8 12M10 14L16 8" stroke="white" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <span>30 days money back guarantee</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
@section('jsLinks')
    <!-- use cdn for alertify -->
    <script src="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/alertify.min.js"></script>
@endsection
@section('myJS')
    <script>
        $(document).ready(function() {
            var orderId = "{{ request()->route('order_id') }}";
            var basePlanPrice = 0;
            var selectedDomain = null;
            var discountPercent = 0;

            if (orderId) {
                $.ajax({
                    url: '{{ url('api/order') }}/' + orderId,
                    method: 'GET',
                    success: function(data) {
                        if (data.success) {
                            basePlanPrice = parseFloat(data.subtotal);
                            $('#display-plan-name').text(data.plan_name);
                            $('#display-mailboxes').text('- ' + data.quantity + (data.quantity > 1 ?
                                ' Mailboxes' : ' Mailbox'));
                            $('#display-duration').text(data.duration);
                            $('#plan-price-display').text('₹ ' + basePlanPrice.toFixed(2));
                            if (data.domain_name) {
                                selectedDomain = data.domain_name;
                            }
                            updateCheckoutUI();
                        }
                    },
                    error: function(xhr) {
                        console.error('Error fetching order details:', xhr.responseText);
                    }
                });
            }

            // --- Promo Code Logic ---
            $('#apply-promo-btn').on('click', function() {
                var code = $('#promo_code_input').val().trim();
                if (!code) {
                    alertify.error('Please enter a promo code');
                    return;
                }

                $.post('{{ url('api/apply-promo') }}', {
                    _token: '{{ csrf_token() }}',
                    code: code
                }, function(res) {
                    if (res.success) {
                        discountPercent = parseFloat(res.discount_percent);
                        alertify.success('Promo code applied! ' + discountPercent + '% discount.');
                        updateCheckoutUI();
                    } else {
                        alertify.error(res.message || 'Invalid promo code');
                    }
                }).fail(function(xhr) {
                    alertify.error(xhr.responseJSON ? xhr.responseJSON.message : 'Error applying promo');
                });
            });

            // --- Form Validation Logic ---
            var requiredFields = [
                'first_name', 'last_name', 'email', 'mobile', 
                'country_select', 'state_select', 'city_select', 'postcode'
            ];



            // --- UI Update Logic ---
            function updateCheckoutUI() {
                var subtotal = basePlanPrice || 0;
                var discountAmount = (subtotal * discountPercent) / 100;
                var discountedSubtotal = subtotal - discountAmount;
                var gst = discountedSubtotal * 0.18;
                var total = discountedSubtotal + gst;

                // Update Sidebar
                if (discountPercent > 0) {
                    $('#discount-percent-display').text(discountPercent);
                    $('#summary-discount-amount').text('₹ -' + discountAmount.toFixed(2));
                    $('#summary-discount-row').show();
                } else {
                    $('#summary-discount-row').hide();
                }

                $('#summary-gst').text('₹ ' + gst.toFixed(2));
                $('#summary-total').text('₹ ' + total.toFixed(2));

                // Update Hidden Form Fields
                $('#form_order_id').val(orderId);
                $('#form_total_amount').val(total.toFixed(2));
                $('#form_tax_amount').val(gst.toFixed(2));
                $('#form_subtotal').val(subtotal.toFixed(2));
                $('#form_discount_amount').val(discountAmount.toFixed(2));
                $('#form_domain_name').val(selectedDomain);
                $('#form_promo_code').val($('#promo_code_input').val());

                if (selectedDomain) {
                    $('#display-selected-domain').text(selectedDomain);
                    $('#summary-domain-name').text(selectedDomain).removeClass('text-muted');
                    $('#summary-domain-row').attr('style', 'border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 10px; display: flex !important;');
                    $('#selected-domain-container').show();
                    $('#domain-input-container').hide();
                    $('#account-section, #summary-section').removeClass('disabled-section');
                } else {
                    $('#summary-domain-name').text('No domain selected').addClass('text-muted');
                    $('#summary-domain-row').attr('style', 'display: none !important;');
                    $('#selected-domain-container').hide();
                    $('#domain-input-container').show();
                    $('#account-section, #summary-section').addClass('disabled-section');
                }
            }

            // Domain Assignment (Revised to use updateCheckoutUI)
            function updateDomainUI() {
                updateCheckoutUI();
            }

            // --- GST Logic ---
            $('#companyDetails').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#gstin_fields').slideDown();
                } else {
                    $('#gstin_fields').slideUp();
                    // Clear fields
                    $('#gstin').val('');
                    $('#company_name').val('');
                    $('#company_address').val('');
                    $('#company_name_group, #company_address_group').hide();
                }
            });

            $('#gstin').on('input', function() {
                this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').substring(0, 15);
            });

            $('#search-gstin-btn').on('click', function() {
                var gstin = $('#gstin').val().trim();
                if (gstin.length !== 15) {
                    alertify.error('Please enter a valid 15-digit GSTIN');
                    return;
                }

                $.ajax({
                    url: '{{ route('search-gstin') }}',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        gstin: gstin
                    },
                    beforeSend: function() {
                        $('#search-gstin-btn').prop('disabled', true).text('Searching...');
                    },
                    success: function(data) {
                        $('#search-gstin-btn').prop('disabled', false).text('Search');
                        if (data.error) {
                            alertify.error(data.message);
                        } else {
                            $('#company_name').val(data.company_name);
                            $('#company_address').val(data.company_address);
                            $('#last_updated').val(data.last_updated);
                            $('#constitution').val(data.constitution);
                            $('#registration_date').val(data.registration_date);
                            $('#nature_of_business').val(data.nature_of_business);
                            
                            $('#company_name_group, #company_address_group').show();
                            alertify.success('GST details fetched successfully');
                        }
                    },
                    error: function(xhr) {
                        $('#search-gstin-btn').prop('disabled', false).text('Search');
                        var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error fetching GST details';
                        alertify.error(msg);
                    }
                });
            });

            // --- Location Logic ---
            // ... (keep loadIndiaStates, loadIndiaCities, loadLoc as is)
            function loadIndiaStates() {
                $('#state_select').html('<option>Loading...</option>');
                $.get('{{ url('api/get-states') }}', function(res) {
                    if (res && res.data && res.data.states) {
                        let h = '<option value="">Select State</option>';
                        res.data.states.forEach(s => h += `<option value="${s.name}">${s.name}</option>`);
                        $('#state_select').html(h);
                    } else {
                        alertify.error('Unable to load states');
                    }
                }).fail(() => alertify.error('Unable to load states'));
            }

            function loadIndiaCities(state) {
                $('#city_select').html('<option>Loading...</option>');
                $.post('{{ url('api/get-cities') }}', {
                    _token: '{{ csrf_token() }}',
                    state: state
                }, function(res) {
                    if (res && res.data && res.data.length > 0) {
                        let h = '<option value="">Select City</option>';
                        res.data.forEach(c => h += `<option value="${c}">${c}</option>`);
                        $('#city_select').html(h);
                    } else {
                        $('#city_select').html('<option value="">No data found</option>');
                    }
                }).fail(() => alertify.error('Unable to load cities'));
            }

            $('#country_select').change(function() {
                $('#city_select').html('<option value="">Select City</option>');
                loadIndiaStates();
            });

            $('#state_select').change(function() {
                loadIndiaCities($(this).val());
            });

            // On page load for Location
            loadIndiaStates();

            $('.back-btn').on('click', function() {
                window.history.back();
            });

            // --- Domain Assignment Submit ---
            var domainRegex = /^[a-zA-Z0-9-]+\.[a-zA-Z]{2,}$/;
            $('#domain-form').on('submit', function(e) {
                e.preventDefault();
                var domain = $('#domain_name').val().trim().toLowerCase();

                if (!domain || !domainRegex.test(domain)) {
                    alertify.error('Please enter a valid domain name (e.g. example.com)');
                    return;
                }

                $.post('{{ url('api/order') }}/' + orderId + '/assign-domain', {
                    _token: '{{ csrf_token() }}',
                    domain_name: domain
                }, function(res) {
                    if (res.success) {
                        selectedDomain = domain;
                        updateCheckoutUI();
                        alertify.success('Domain added successfully');
                    } else {
                        alertify.error(res.message || 'Error occurred');
                    }
                }).fail(() => alertify.error('Connection failed'));
            });

            $('#remove-domain, #remove-sidebar-domain').on('click', function() {
                $.post('{{ url('api/order') }}/' + orderId + '/assign-domain', {
                    _token: '{{ csrf_token() }}',
                    domain_name: null
                }, function(res) {
                    if (res.success) {
                        selectedDomain = null;
                        $('#domain_name').val('');
                        updateCheckoutUI();
                        alertify.log('Domain removed');
                    }
                });
            });

            // --- Standardized Form Validation & Submission ---
            $("#checkout-form").on("submit", function(e) {
                e.preventDefault();
                
                if (!selectedDomain) {
                    alertify.error('Please add a domain first');
                    return;
                }

                var url = '{{ route('update-order') }}';
                var $btn = $("#secure-checkout-btn");
                var $spinner = $("#spinner");
                var $btnText = $("#btn-text");

                $.ajax({
                    url: url,
                    type: "POST",
                    data: new FormData(this),
                    contentType: false,
                    cache: false,
                    processData: false,
                    dataType: "json",
                    beforeSend: function() {
                        $spinner.show();
                        $btn.attr("disabled", true);
                        $btnText.text('Processing...');
                    },
                    success: function(data) {
                        $spinner.hide();
                        $btn.attr("disabled", false);
                        $btnText.text('Continue to Secure Checkout');

                        // Clear previous errors
                        $(".text-danger[id^='error-']").html(" ");
                        $(".form-control").removeClass("border-danger");

                        if (data.error) {
                            $.each(data, function(key, value) {
                                if (value) {
                                    $("#error-" + key).html(value);
                                    var fieldId = key;
                                    if (['country', 'state', 'city'].includes(key)) fieldId = key + '_select';
                                    $("#" + fieldId).addClass("border-danger");
                                }
                            });
                            alertify.error('Please check the highlighted fields');
                        }

                        if (data.success) {
                            alertify.success(data.success);
                            setTimeout(function() {
                                if (data.next_step === 'purchase_order' && data.po_url) {
                                    window.location.href = data.po_url;
                                    return;
                                }
                                window.location.href = '{{ url('payment') }}/' + data.order_id;
                            }, 1000);
                        }
                    },
                    error: function(error) {
                        $spinner.hide();
                        $btn.attr("disabled", false);
                        $btnText.text('Continue to Secure Checkout');
                        alertify.error('An error occurred. Please try again.');
                    }
                });
            });
        });
    </script>
@endsection
