@extends('partner.render')

@section('metaTitle')
    <title>Partner Register | Tizzy Group</title>
@endsection

@section('myCSS')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/alertify.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/themes/default.min.css"/>
    <style>
        .custom-input-group > .custom-search-btn:hover {
            background-color: #97bbdf !important;
        }
    </style>
    @endsection

@section('jsLinks')
    <script src="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/alertify.min.js"></script>
@endsection

@section('Body')
    <div class="auth-header text-center" data-aos="fade-up" data-aos-duration="500">
       
        <h2 class="auth-title">Ready to become a Partner?</h2>
        <p class="auth-subtitle">Join 2000+ partners already scaling with Tizzy.</p>
    </div>

    <form id="form" data-aos="fade-up" data-aos-duration="900" class="auth-form">
        @csrf
        
        <div class="mb-2">
            <label class="form-label custom-label">Name<span class="text-danger">*</span></label>
            <input type="text" name="name" id="name" class="form-control custom-input" placeholder="Name" value="{{ old('name') }}">
            <div class="text-danger small mt-1" id="error-name"></div>
        </div>
        
        <div class="mb-2">
            <label class="form-label custom-label">Email<span class="text-danger">*</span></label>
            <input type="email" name="email" id="email" class="form-control custom-input" placeholder="you@example.com" value="{{ old('email') }}">
            <div class="text-danger small mt-1" id="error-email"></div>
        </div>

        <div class="mb-2">
            <label class="form-label custom-label">Mobile No.<span class="text-danger">*</span></label>
            <div class="input-group custom-input-group">
                <select class="form-select custom-select" name="country_code" style="max-width: 80px;">
                    <option value="+91">+91</option>
                </select>
                <input type="text" name="mobile" id="mobile" class="form-control custom-input no-border-left" placeholder="" value="{{ old('mobile') }}">
            </div>
            <div class="text-danger small mt-1" id="error-mobile"></div>
        </div>
        
        <div class="mb-2">
            <label class="form-label custom-label">GSTIN<span class="text-danger">*</span></label>
            <div class="input-group custom-input-group">
                <input type="text" name="gstin" id="gstin" class="form-control custom-input" placeholder="" style="text-transform: uppercase;margin-right: 3px;" value="{{ old('gstin') }}">
                <button class="btn btn-outline-secondary custom-search-btn" type="button" id="search"> 
                    <span id="spinner2" class="spinner-border spinner-border-sm" role="status" style="display:none"></span>
                    Search</button>
            </div>
            <div class="text-danger small mt-1" id="error-gstin"></div>
        </div>

        <div class="mb-2" id="companyNameGroup" style="display: none;">
            <label class="form-label custom-label">Company Name<span class="text-danger">*</span></label>
            <input type="text" name="company_name" id="company_name" class="form-control custom-input" placeholder="Company Name" value="{{ old('company_name') }}" readonly>
            <div class="text-danger small mt-1" id="error-company_name"></div>
        </div>

        {{-- company address  --}}
        <div class="mb-2" id="companyAddressGroup" style="display: none;">
            <label class="form-label custom-label">Company Address<span class="text-danger">*</span></label>
            <input type="text" name="company_address" id="company_address" class="form-control custom-input" placeholder="Company Address" value="{{ old('company_address') }}" readonly>
            <div class="text-danger small mt-1" id="error-company_address"></div>
        </div>

        <div class="terms-text mb-2 mt-3">
            By clicking 'Register' below, you agree to our <a href="#">Terms of Services</a> and <a href="#">Privacy Policy</a>.
        </div>
        <input type="hidden" name="last_updated" id="last_updated">
        <input type="hidden" name="constitution" id="constitution">
        <input type="hidden" name="registration_date" id="registration_date">
        <input type="hidden" name="nature_of_business" id="nature_of_business">
        <input type="hidden" name="gst_status" id="gst_status">
        <input type="hidden" name="state" id="state">
        <input type="hidden" name="city" id="city">
        <input type="hidden" name="country" id="country" value="India">
        <input type="hidden" name="pincode" id="pincode">
        <input type="hidden" name="postcode" id="postcode">
        <input type="hidden" name="pan_no" id="pan_no">
        <input type="hidden" name="legal_name" id="legal_name">
        <input type="hidden" name="trade_name" id="trade_name">
        <input type="hidden" name="address" id="address">
        <button type="submit" id="submit" class="btn btn-primary btn-register d-flex align-items-center justify-content-center gap-2">
            <span id="spinner" class="spinner-border spinner-border-sm" role="status" style="display:none !important;"></span>
            <span id="btn-text">Register</span>
        </button>
    </form>

    <div class="text-center mt-4">
        <p class="auth-footer-text">
            Already a partner? <a href="{{ url('partner/login') }}" class="sign-in-link">Sign In</a>
        </p>
    </div>
    
@endsection

@section('myJS')
    <script>
        
        // Uppercase GSTIN input on typing
        $("#company_name , #company_address").on('input', function () {
          // user canot change company name also dont show cursor on company name and company address
          this.readOnly = true;
          this.style.cursor = "not-allowed";
        });
        $("#gstin").on('input', function () {
            // Convert to uppercase
            this.value = this.value.toUpperCase();

            // Allow only alphanumeric
            this.value = this.value.replace(/[^A-Z0-9]/g, '');

            // Limit to 15 characters
            if (this.value.length > 15) {
                this.value = this.value.substring(0, 15);
            }
        });
        $("#search").on("click", function() {

            var gstin = $("#gstin").val().trim();
            // check empty
            if (gstin === "") {
                alertify.error("Please enter GSTIN");
                return false;
            }
            $.ajax({
                url: "{{ url('search-gstin') }}",
                type: "POST",
                data: {
                    _token: "{{ csrf_token() }}",
                    gstin: gstin
                },
                beforeSend: function() {
                    $("#spinner2").attr("style", "display: inline-block !important");
                    $("#search").prop("disabled", true);
                },
                success: function(data) {
                    $("#spinner2").attr("style", "display: none !important");
                    $("#search").prop("disabled", false);

                    if (!data.error && data.company_name) {
                        
                        $("#company_name").val(data.company_name);
                        $("#company_address").val(data.company_address);
                        $("#address").val(data.company_address);
                        $("#last_updated").val(data.last_updated);
                        $("#constitution").val(data.constitution);
                        $("#registration_date").val(data.registration_date);
                        $("#nature_of_business").val(data.nature_of_business);
                        $("#gst_status").val(data.gst_status || "");
                        $("#state").val(data.state || "");
                        $("#city").val(data.city || "");
                        $("#country").val(data.country || "India");
                        $("#pincode").val(data.pincode || "");
                        $("#postcode").val(data.pincode || "");
                        $("#pan_no").val(data.pan_no || "");
                        $("#legal_name").val(data.legal_name || data.company_name || "");
                        $("#trade_name").val(data.trade_name || data.company_name || "");

                        $("#companyNameGroup").show();
                        $("#companyAddressGroup").show();
                        
                    } else if (data.message) {
                        alertify.error(data.message);
                    } else {
                        alertify.error("GSTIN data not found");
                    }

                },

                error: function(xhr) {
                    $("#spinner2").attr("style", "display: none !important");
                    $("#search").prop("disabled", false);
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        alertify.error(xhr.responseJSON.message);
                    } else {
                        alertify.error("Something went wrong. Please try again.");
                    }
                }

            });

        });

        $("#form").on("submit", function(e) {
            e.preventDefault();

            
            $.ajax({
                url: '{{ url("partner/register") }}',
                type: "POST",
                data: new FormData(this),
                contentType: false,
                cache: false,
                processData: false,
                dataType: "json",
                beforeSend: function(data) {
                    $("#spinner").attr("style", "display: inline-block !important");
                    $("#btn-text").text('Registering...');
                    $("#submit").prop("disabled", true);
                    $(".text-danger").html("");
                    $(".form-control").removeClass("border-danger");
                    $(".custom-input").removeClass("border-danger");
                },
                success: function(data) {
                    $("#spinner").attr("style", "display: none !important");
                    $("#btn-text").text('Register');
                    $("#submit").prop("disabled", false);
                    if (data.error) {
                        $.each(data, function(key, value) {
                            if (!['error', 'success', 'message', 'data', 'alert_error', 'redirect'].includes(key)) {
                                $("#error-" + key).html(value);
                                $("#" + key).addClass("border-danger");
                            }
                        });
                    }
                    if (data.alert_error) {
                        alertify.error(data.alert_error);
                    }
                    var companyName = $("#company_name").val().trim();
                    var companyAddress = $("#company_address").val().trim();

                    if (companyName === "" || companyAddress === "" || $("#gstin").val().trim() === "") {
                            
                        alertify.error("Please enter valid gstin and click search button");
                        return false;
                    }
                    if (data.success) {
                        $("#form .form-control").removeClass("border-danger");
                        $("#form .text-danger").html(" ");
                        alertify.success(data.message);
                        setTimeout(function() {
                            window.location.href = '{{ url("partner/verify-otp") }}';
                        }, 1000);
                    }
                },
                error: function(xhr) {
                    $("#spinner").attr("style", "display: none !important");
                    $("#btn-text").text('Register');
                    $("#submit").prop("disabled", false);
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        $.each(xhr.responseJSON.errors, function(key, value) {
                            $("#error-" + key).html(value[0]);
                            $("#" + key).addClass("border-danger");
                        });
                    } else if (xhr.responseJSON && xhr.responseJSON.error === true) {
                        $.each(xhr.responseJSON, function(key, value) {
                            if (!['error', 'success', 'message', 'data', 'alert_error', 'redirect'].includes(key)) {
                                $("#error-" + key).html(value);
                                $("#" + key).addClass("border-danger");
                            }
                        });
                        if (xhr.responseJSON.message) {
                            alertify.error(xhr.responseJSON.message);
                        }
                    } else if (xhr.responseJSON && xhr.responseJSON.alert_error) {
                        alertify.error(xhr.responseJSON.alert_error);
                    } else if (xhr.responseJSON && xhr.responseJSON.redirect) {
                        window.location.href = xhr.responseJSON.redirect;
                    } else {
                        alertify.error("An error occurred. Please try again.");
                    }
                }
            });
        });
    </script>
@endsection
