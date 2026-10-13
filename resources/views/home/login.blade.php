@extends('home.render')

@section('metaTitle')
    <title>Login | Client Access Portal</title>
@endsection
@section('myCSS')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/alertify.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/themes/default.min.css"/>
@endsection

@section('jsLinks')
    <script src="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/alertify.min.js"></script>
@endsection

@section('Body')
    <div class="auth-wrapper">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-11 col-xl-10">
                    <div class="auth-card d-flex flex-column flex-md-row">
                        <!-- Left Side: Login Form -->
                        <div class="col-md-6 col-lg-6 auth-left" data-aos="fade-right" data-aos-easing="linear"
                            data-aos-duration="900">
                            <h2 class="p-content mb-1" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="500">
                                Client Access Portal</h2>
                            <p class="text-muted mb-4" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="900">
                                Sign in to your account</p>
                            <div data-aos="fade-up" data-aos-easing="linear" data-aos-duration="700">
                                <form id="form">
                                    @csrf
                                    <div class="mb-3">
                                        <div class="position-relative">
                                            <i class="fa fa-envelope input-icon"></i>
                                            <input type="text" name="email" id="email" class="form-control auth-input" placeholder="Enter your email" >
                                        </div>
                                        <div class="text-danger" id="error-email"></div>
                                    </div>

                                    <div class="mb-4">
                                        <div class="position-relative">
                                            <i class="fa fa-lock input-icon"></i>
                                            <input type="password" name="password" id="password" class="form-control auth-input" placeholder="Enter your password">
                                            <i class="fa fa-eye-slash toggle-password" id="togglePasswordBtn"></i>
                                        </div>
                                        <div class="text-danger" id="error-password"></div>
                                    </div>

                                    <div class="form-check mb-4">
                                        <input class="form-check-input mt-1" type="checkbox" name="remember" id="rememberCheck">
                                        <label class="form-check-label text-muted ms-1" for="rememberCheck"
                                            style="font-size: 0.9rem; font-weight: 500;">
                                            Remember Me
                                        </label>
                                    </div>
                                    <button type="submit" id="submit" class="btn btn-register mb-4 text-white d-flex align-items-center justify-content-center gap-2">
                                        <span id="spinner" class="spinner-border spinner-border-sm" role="status" style="display:none !important;"></span>
                                        <span id="btn-text">Login</span>
                                    </button>
                                </form>
                            </div>

                            <div class="text-center sync-card-height" data-aos="fade-up" data-aos-easing="linear"
                                data-aos-duration="800">
                                <p class="text-muted m-0" style="font-size: 0.95rem; font-weight: 500;">
                                    Don't you have an account? <a href="{{ url('user-register') }}"
                                        class="text-decoration-none fw-semibold" style="color:#1665b6;">Create an
                                        Account</a>
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

@section('myJS')
    <script>
        // Toggle Password Visibility
        document.getElementById('togglePasswordBtn').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                this.classList.remove('fa-eye-slash');
                this.classList.add('fa-eye');
            } else {
                passwordInput.type = 'password';
                this.classList.remove('fa-eye');
                this.classList.add('fa-eye-slash');
            }
        });

        $("#form").on("submit", function(e) {
            e.preventDefault();
            $.ajax({
                url: '{{ url("user-login-validation") }}',
                type: "POST",
                data: new FormData(this),
                contentType: false,
                cache: false,
                processData: false,
                dataType: "json",
                beforeSend: function(data) {
                    $("#spinner").css('display', 'inline-block');
                    $("#btn-text").text('Logging in...');
                    $("#submit").attr("disabled", true);
                    $(".text-danger").html("");
                    $(".form-control").removeClass("border-danger");
                },
                success: function(data) {
                    $("#spinner").css('display', 'none');
                    $("#btn-text").text('Login');
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
                        alertify.success(data.success);
                        setTimeout(function() {
                            window.location.href = data.redirect || '{{ route("dashboard") }}';
                        }, 1000);
                    }
                },
                error: function(error) {
                    $("#spinner").css('display', 'none');
                    $("#btn-text").text('Login');
                    $("#submit").attr("disabled", false);
                    errorMessage(error.status);
                }
            });
        });

    </script>
@endsection
