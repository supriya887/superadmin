@extends('home.render')

@section('metaTitle')
    <title>Verify Email | Tizzy Cloud</title>
@endsection

@section('myCSS')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/alertify.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/themes/default.min.css" />
    <style>
        .otp-input {
            letter-spacing: 15px;
            font-size: 1.5rem;
            text-align: center;
            font-weight: bold;
        }
    </style>
@endsection

@section('Body')
    <div class="auth-wrapper">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-11 col-xl-10">
                    <div class="auth-card d-flex flex-column flex-md-row overflow-hidden">

                        <!-- Left Side: Verify Form -->
                        <div class="auth-left w-100 w-md-50" data-aos="fade-right" data-aos-easing="linear"
                            data-aos-duration="900" style="background: #fff; z-index: 1;">
                            <h2 class="p-content mb-0" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="500">
                                Verify Email</h2>
                            <p class="text-muted mb-4" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="900">
                                Please enter the 6-digit OTP sent to your email address.</p>

                            <div id="success-message" class="alert alert-success d-none fw-medium" data-aos="fade-up"></div>

                            <form id="otp-form" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="700">
                                @csrf
                                <div class="mb-4">
                                    <div class="position-relative">
                                        <i class="fa fa-key input-icon"></i>
                                        <input type="text" name="otp" id="otp" class="form-control auth-input otp-input"
                                            placeholder="XXXXXX" maxlength="6">
                                    </div>
                                    <div class="text-danger" id="error-otp"></div>
                                </div>

                                <button type="submit" id="submit"
                                    class="btn btn-register mb-4 text-white d-flex align-items-center justify-content-center gap-2">
                                    <span id="spinner" class="spinner-border spinner-border-sm" role="status"
                                        style="display:none !important;"></span>
                                    <span id="btn-text">Verify OTP</span>
                                </button>
                            </form>

                            <div class="text-center sync-card-height2" data-aos="fade-up" data-aos-easing="linear"
                                data-aos-duration="800">
                                <p class="text-muted m-0" style="font-size: 1rem;">
                                    Didn't receive the code? <a href="javascript:void(0)" id="resend-link"
                                        class="text-decoration-none fw-semibold" style="color:#1665b6;">Resend</a>
                                </p>
                            </div>
                        </div>

                        <!-- Right Side: Illustration & Information -->
                        <div class="auth-right w-100 w-md-50" data-aos="fade-left" data-aos-easing="linear"
                            data-aos-duration="900" style="min-height: 400px; z-index: 1;">
                            <div class="auth-right-content" data-aos="fade-up" data-aos-easing="linear"
                                data-aos-duration="800">
                                {{-- <h3>Nearly there!</h3> --}}
                                {{-- <p>Verifying your email ensures that your account is secure and that we can reach you with important updates.</p> --}}
                                <ul>
                                    <li><i class="fa fa-check-circle"></i> Secure your account</li>
                                    <li><i class="fa fa-check-circle"></i> Receive order notifications</li>
                                    <li><i class="fa fa-check-circle"></i> Complete your profile</li>
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
        $("#otp-form").on("submit", function(e) {
            e.preventDefault();
            $.ajax({
                url: '{{ route('verify-otp') }}',
                type: "POST",
                data: $(this).serialize(),
                dataType: "json",
                beforeSend: function() {
                    $("#spinner").css('display', 'inline-block');
                    $("#btn-text").text('Verifying...');
                    $("#submit").attr("disabled", true);
                    $(".text-danger").html("");
                    $(".form-control").removeClass("border-danger");
                },
                success: function(data) {
                    $("#spinner").css('display', 'none');
                    $("#btn-text").text('Verify OTP');
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
                        $("#otp").removeClass("border-danger");
                        $("#error-otp").html(" ");

                        // show success message in div
                        alertify.success(data.success);
                        
                       
                        
                        setTimeout(function() {
                            window.location.href = data.redirect;
                        }, 5000);
                    }
                },
                error: function(error) {
                    $("#spinner").css('display', 'none');
                    $("#btn-text").text('Verify OTP');
                    $("#submit").attr("disabled", false);
                    alertify.error("Something went wrong");
                }
            });
        });

        $("#resend-link").on("click", function(e) {
            e.preventDefault();
            let link = $(this);
            $.ajax({
                url: '{{ route('resend-otp') }}',
                type: "POST",
                data: {
                    _token: '{{ csrf_token() }}'
                },
                dataType: "json",
                beforeSend: function() {
                    link.text('Resending...').css('pointer-events', 'none');
                },
                success: function(data) {
                    link.text('Resend').css('pointer-events', 'auto');
                    if (data.success) {
                        alertify.success(data.success);
                    }
                    if (data.alert_error) {
                        alertify.error(data.alert_error);
                    }
                },
                error: function() {
                    link.text('Resend').css('pointer-events', 'auto');
                    alertify.error("Something went wrong");
                }
            });
        });
    </script>
@endsection
