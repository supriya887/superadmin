@extends('home.render')

@section('metaTitle')
    <title>Create Account | Tizzy Cloud</title>
@endsection

@section('myCSS')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .req-icon {
            font-size: 0.5rem;
            vertical-align: middle;
            margin-right: 5px;
        }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/alertify.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/themes/default.min.css" />
@endsection

@section('Body')
    <div class="auth-wrapper">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-11 col-xl-10">
                    <div class="auth-card d-flex flex-column flex-md-row">

                        <!-- Left Side: Register Form -->
                        <div class="col-md-6 col-lg-6 auth-left" data-aos="fade-right" data-aos-easing="linear"
                            data-aos-duration="900">
                            <h2 class="p-content mb-0" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="500">
                                Create Account</h2>
                            <p class="text-muted mb-4" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="900">
                                Sign up quickly and start managing your cloud services</p>

                            <div id="success-message" class="alert alert-success d-none fw-medium" data-aos="fade-up"></div>

                            <form id="form" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="700">
                                @csrf
                                <div class="mb-3">
                                    <div class="position-relative">
                                        <i class="fa fa-user input-icon"></i>
                                        <input type="text" name="name" id="name" class="form-control auth-input"
                                            placeholder="Name">
                                    </div>
                                    <div class="text-danger" id="error-name"></div>
                                </div>
                                <div class="mb-3">
                                    <div class="position-relative">
                                        <i class="fa fa-envelope input-icon"></i>
                                        <input type="text" name="email" id="email" class="form-control auth-input"
                                            placeholder="Email Address">
                                    </div>
                                    <div class="text-danger" id="error-email"></div>
                                </div>

                                {{-- <div class="mb-3">
                                <div class="position-relative">
                                    <i class="fa fa-lock input-icon"></i>
                                    <input type="password" name="password" class="form-control auth-input" id="password" placeholder="Password" >
                                    <i class="fa fa-eye toggle-password" id="togglePasswordBtn"></i>
                                </div>

                                <!-- Password Strength and Instructions -->
                                <div id="password-requirements" class="d-none">
                                    <div class="progress mt-2" style="height: 5px;">
                                        <div id="password-strength-bar" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <div class="mt-2" style="font-size: 0.8rem;">
                                        <span class="text-muted d-block mb-1">Password must contain:</span>
                                        <ul class="list-unstyled mb-0 ms-2">
                                            <li id="req-length" class="text-muted">
                                                <i class="fa fa-circle req-icon"></i> 
                                                At least 8 characters
                                            </li>
                                            <li id="req-upper" class="text-muted">
                                                <i class="fa fa-circle req-icon"></i> 
                                                Uppercase & lowercase letters
                                            </li>
                                            <li id="req-number" class="text-muted">
                                                <i class="fa fa-circle req-icon"></i> 
                                                A number
                                            </li>
                                            <li id="req-special" class="text-muted">
                                                <i class="fa fa-circle req-icon"></i> 
                                                A special character (@$!%*?&)
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="text-danger mt-1" id="error-password"></div>
                            </div> --}}
                                 {{-- mobile no  --}}
                                

                                <div class="mb-3">
                                    <div class="position-relative">
                                        <i class="fa fa-phone input-icon"></i>
                                        <input type="text" name="mobile" id="mobile"
                                            class="form-control auth-input" placeholder="Mobile Number">
                                    </div>
                                    <div class="text-danger" id="error-mobile"></div>
                                </div>
                                {{-- gstin --}}
                                <div class="mb-3">
                                    {{-- also add in input search --}}

                                    <div class="search-box">
                                        <div class="position-relative">
                                            <i class="fa fa-file input-icon"></i>
                                            <input type="text" name="gstin" id="gstin"
                                                class="form-control auth-input" placeholder="GSTIN">
                                            {{-- show spinner also    --}}
                                            <button id="search" type="button">Search
                                                <span id="spinner2" class="spinner-border spinner-border-sm" role="status" style="display:none !important;"></span>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="text-danger" id="error-gstin"></div>
                                </div>
                                {{-- compnay name   --}}
                                <div class="mb-3" id="CompanyName" style="display: none;">
                                    <div class="position-relative">
                                        <i class="fa fa-user input-icon"></i>
                                        <input type="text" name="company_name" id="company_name"
                                            class="form-control auth-input" placeholder="Company Name" readonly>
                                    </div>
                                    <div class="text-danger" id="error-company_name"></div>
                                </div>

                                {{-- company address  --}}
                                <div class="mb-3" id="CompanyAddress" style="display: none;">
                                    <div class="position-relative">
                                        <i class="fa fa-user input-icon"></i>
                                        <input type="text" name="company_address" id="company_address"
                                            class="form-control auth-input" placeholder="Company Address">
                                    </div>
                                    <div class="text-danger" id="error-company_address"></div>
                                </div>
                                <div class="mb-4">
                                    <div class="form-check">
                                        <input type="checkbox" name="terms_and_conditions" id="terms_and_conditions" class="form-check-input">
                                        <label class="form-check-label text-muted ms-1" for="termsCheck"
                                            style="font-size: 0.9rem;">
                                            I Agree to the <a href="#" class="text-decoration-none fw-medium"
                                                style="color:#1665b6;">Terms & Conditions</a>
                                        </label>
                                        <div class="text-danger" id="error-terms_and_conditions"></div>
                                    </div>
                                </div>

                                <button type="submit" id="submit"
                                    class="btn btn-register mb-4 text-white d-flex align-items-center justify-content-center gap-2">
                                    <span id="spinner" class="spinner-border spinner-border-sm" role="status"
                                        style="display:none !important;"></span>
                                    <span id="btn-text">Register</span>
                                </button>
                            </form>

                            <div class="text-center" data-aos="fade-up" data-aos-easing="linear"
                                data-aos-duration="800">
                                <p class="text-muted m-0" style="font-size: 1rem;">
                                    Already have an account? <a href="{{ url('user-login') }}"
                                        class="text-decoration-none fw-semibold" style="color:#1665b6;">Login</a>
                                </p>
                            </div>
                        </div>

                        <!-- Right Side: Illustration & Information -->
                        <div class="col-md-6 col-lg-6 auth-right" data-aos="fade-left" data-aos-easing="linear"
                            data-aos-duration="900">
                            <div class="auth-right-content" data-aos="fade-up" data-aos-easing="linear"
                                data-aos-duration="800">
                                <h3>Access your account securely</h3>

                                <ul>
                                    <li><i class="fa fa-check-circle"></i> Manage cloud Server</li>
                                    <li><i class="fa fa-check-circle"></i> Access Cloud Storage</li>
                                    <li><i class="fa fa-check-circle"></i> 247 Support</li>
                                </ul>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('jsLinks')
    <script src="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/alertify.min.js"></script>
@endsection

@section('myJS')
    <script>
        // Toggle Password Visibility
        // $('#togglePasswordBtn').on('click', function() {
        //     var passwordInput = $('#password');
        //     if (passwordInput.prop('type') === 'password') {
        //         passwordInput.prop('type', 'text');
        //         $(this).removeClass('fa-eye').addClass('fa-eye-slash');
        //     } else {
        //         passwordInput.prop('type', 'password');
        //         $(this).removeClass('fa-eye-slash').addClass('fa-eye');
        //     }
        // });

        // Password Strength Checker
        // $(document).ready(function() {
        //     $('#password').on('focus', function() {
        //         $('#password-requirements').removeClass('d-none');
        //     }).on('blur', function() {
        //         if ($(this).val() === '') {
        //             $('#password-requirements').addClass('d-none');
        //         }
        //     });

        //     $('#password').on('input', function() {
        //         var password = $(this).val();
        //         var strength = 0;

        //         // Requirements
        //         var hasLength = password.length >= 8;
        //         var hasUpperLower = /[a-z]/.test(password) && /[A-Z]/.test(password);
        //         var hasNumber = /[0-9]/.test(password);
        //         var hasSpecial = /[^A-Za-z0-9]/.test(password);

        //         // Update instruction text color
        //         updateRequirement('length', hasLength);
        //         updateRequirement('upper', hasUpperLower);
        //         updateRequirement('number', hasNumber);
        //         updateRequirement('special', hasSpecial);

        //         // Calculate strength
        //         if (hasLength) strength += 25;
        //         if (hasUpperLower) strength += 25;
        //         if (hasNumber) strength += 25;
        //         if (hasSpecial) strength += 25;

        //         // Update progress bar
        //         var progressBar = $('#password-strength-bar');
        //         progressBar.css('width', strength + '%');

        //         // Update color
        //         progressBar.removeClass('bg-danger bg-warning bg-info bg-success');
        //         if (password.length === 0) {
        //             // leave blank
        //         } else if (strength <= 25) {
        //             progressBar.addClass('bg-danger');
        //         } else if (strength <= 50) {
        //             progressBar.addClass('bg-warning');
        //         } else if (strength <= 75) {
        //             progressBar.addClass('bg-info');
        //         } else {
        //             progressBar.addClass('bg-success');
        //         }
        //     });

        //     function updateRequirement(req, isValid) {
        //         var element = $('#req-' + req);
        //         var icon = element.find('i');

        //         if (isValid) {
        //             element.removeClass('text-muted').addClass('text-success');
        //             icon.removeClass('fa-circle').addClass('fa-check-circle');
        //             icon.css('font-size', '0.8rem');
        //         } else {
        //             element.removeClass('text-success').addClass('text-muted');
        //             icon.removeClass('fa-check-circle').addClass('fa-circle');
        //             icon.css('font-size', '0.5rem');
        //         }
        //     }
        // });

        // onclick search search gstin  use ajax 
        $("#search").on("click", function() {

            var gstin = $("#gstin").val().trim();

            // check empty
            if (gstin === "") {
                alertify.error("Please enter GSTIN");
                return false;
            }

            // show fields
            

            $.ajax({
                url: "{{ route('search-gstin') }}",
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
                        

                        $("#CompanyName").show();
                        $("#CompanyAddress").show();
                        // show loader
                        
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
                url: '{{ route('register-validation') }}',
                type: "POST",
                data: new FormData(this),
                contentType: false,
                cache: false,
                processData: false,
                dataType: "json",
                beforeSend: function(data) {
                    $("#spinner").css('display', 'inline-block');
                    $("#btn-text").text('Registering...');
                    $("#submit").attr("disabled", true);
                    $(".text-danger").html("");
                    $(".form-control").removeClass("border-danger");
                },
                success: function(data) {
                    $("#spinner").css('display', 'none');
                    $("#btn-text").text('Register');
                    $("#submit").attr("disabled", false);
                    if (data.error) {
                        $.each(data, function(key, value) {
                            if (key !== 'error') {
                                $("#error-" + key).html(value);
                                $("#" + key).addClass("border-danger");
                            }
                        });
                    }
                    if (data.alert_error) {
                        alertify.error(data.alert_error);
                    }
                    if (data.success) {
                        $("#form .form-control").removeClass("border-danger");
                        $("#form .text-danger").html(" ");

                        // Show embedded alert message and clear the form
                        $("#success-message").html(data.success).removeClass("d-none");
                        $("#form")[0].reset();
                        alertify.success(data.success);
                        setTimeout(function() {
                            window.location.href = '{{ route('verify-email') }}';
                        }, 2000); // 2 second delay so they can read the success message
                    }
                },
                error: function(error) {
                    $("#spinner").css('display', 'none');
                    $("#btn-text").text('Register');
                    $("#submit").attr("disabled", false);
                    errorMessage(error.status);
                }
            });
        });
    </script>
@endsection
