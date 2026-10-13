@extends('partner.render')

@section('metaTitle')
    <title>Forgot Password | Tizzy Group</title>
@endsection

@section('myCSS')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/alertify.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/themes/default.min.css"/>
    <style>
        
    </style>
@endsection

@section('jsLinks')
    <script src="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/alertify.min.js"></script>
@endsection

@section('Body')
    <h2 class="auth-heading" data-aos="fade-up" data-aos-duration="500">Forgot Password?</h2>
    <p class="auth-desc" data-aos="fade-up" data-aos-duration="700">Enter your email and we'll send you a link to reset <br>your password.</p>

    <form id="form" data-aos="fade-up" data-aos-duration="900">
        @csrf
        <div class="mb-3">
            <label class="custom-label d-block text-start">Email <span class="text-danger">*</span></label>
            <input type="email" name="email" id="email" class="form-control custom-input" placeholder="you@example.com">
            <div class="text-danger small mt-1 text-start" id="error-email"></div>
        </div>

        <button type="submit" id="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
            <span id="spinner" class="spinner-border spinner-border-sm" role="status" style="display:none !important;"></span>
            <span id="btn-text">Send Request</span>
        </button>
    </form>

    <div class="back-to-login" data-aos="fade-up" data-aos-duration="1000">
        Back to <a href="{{ url('partner/login') }}">Sign in</a>
    </div>
@endsection

@section('myJS')
    <script>
        $("#form").on("submit", function(e) {
            e.preventDefault();
            $.ajax({
                url: '{{ url("partner/forgot-password") }}',
                type: "POST",
                data: new FormData(this),
                contentType: false,
                cache: false,
                processData: false,
                dataType: "json",
                beforeSend: function() {
                    $("#spinner").attr("style", "display: inline-block !important");
                    $("#btn-text").text('Sending...');
                    $("#submit").prop("disabled", true);
                    $(".text-danger").html("");
                    $(".form-control").removeClass("border-danger");
                    $(".custom-input").removeClass("border-danger");
                },
                success: function(data) {
                    $("#spinner").attr("style", "display: none !important");
                    $("#btn-text").text('Send Request');
                    $("#submit").prop("disabled", false);
                    
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
                        alertify.success(data.message);
                        setTimeout(function() {
                            window.location.href = data.redirect || '{{ url("partner/verify-reset-otp") }}';
                        }, 1000);
                        $("#email").val("");
                    }
                },
                error: function(xhr) {
                    $("#spinner").attr("style", "display: none !important");
                    $("#btn-text").text('Send Request');
                    $("#submit").prop("disabled", false);
                    if (xhr.status === 422 && xhr.responseJSON) {
                        let data = xhr.responseJSON;
                        $.each(data, function(key, value) {
                            if (key !== 'error' && key !== 'message' && key !== 'data') {
                                $("#error-" + key).html(value);
                                $("#" + key).addClass("border-danger");
                            }
                        });
                        if (data.message && data.message !== 'Validation failed') {
                            alertify.error(data.message);
                        }
                    } else if (xhr.status === 404) {
                        alertify.error("Page not found. Please try again.");
                    } else {
                        alertify.error("An error occurred. Please try again.");
                    }
                }
            });
        });
    </script>
@endsection
