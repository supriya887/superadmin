@extends('partner.render')

@section('metaTitle')
    <title>Partner Login | Tizzy Group</title>
@endsection

@section('myCSS')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/alertify.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/themes/default.min.css"/>
@endsection

@section('jsLinks')
    <script src="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/alertify.min.js"></script>
    @if(session('success'))
        <script>alertify.success("{{ session('success') }}");</script>
    @endif
    @if(session('error'))
        <script>alertify.error("{{ session('error') }}");</script>
    @endif
@endsection

@section('Body')
    <div class="mb-4">
        <h2 data-aos="fade-up" data-aos-duration="500">Great to see you here 👋</h2>
        <p data-aos="fade-up" data-aos-duration="700">Sign in to continue.</p>
    </div>

    <form id="form" data-aos="fade-up" data-aos-duration="900">
        @csrf
        <div class="mb-3">
            <label class="form-label text-muted custom-label">Email <span class="text-danger">*</span></label>
            <input type="email" name="email" id="email" class="form-control custom-input" placeholder="you@example.com" value="{{ old('email') }}">
            <div class="text-danger small mt-1" id="error-email"></div>
        </div>

        <div class="mb-3">
            <label class="form-label text-muted custom-label">Password <span class="text-danger">*</span></label>
            <input type="password" name="password" id="password" class="form-control custom-input" placeholder="••••••••">
            <div class="text-danger small mt-1" id="error-password"></div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="rememberMe">
                <label class="form-check-label text-muted custom-label" for="rememberMe" style="font-size: 0.85rem;">
                    Keep me signed in
                </label>
            </div>
            <a href="{{ url('partner/forgot-password') }}" class="text-decoration-none" style="font-size: 0.85rem; color: #1665b6; font-weight: 500;">Forgot Password?</a>
        </div>

        <button type="submit" id="submit" class="btn btn-primary d-flex align-items-center justify-content-center gap-2">
            <span id="spinner" class="spinner-border spinner-border-sm" role="status" style="display:none !important;"></span>
            <span id="btn-text">Sign In</span>
        </button>
    </form>

    <div class="text-center mt-4">
        <p class="text-muted" style="font-size: 0.85rem;">
            Want to be a partner? <a href="{{ url('partner/register') }}" class="text-decoration-none fw-bold" style="color: #1665b6;">Register Now</a>
        </p>
    </div>
@endsection

@section('myJS')
    <script>
        $("#form").on("submit", function(e) {
            e.preventDefault();
            $.ajax({
                url: '{{ url("partner/login") }}',
                type: "POST",
                data: new FormData(this),
                contentType: false,
                cache: false,
                processData: false,
                dataType: "json",
                beforeSend: function(data) {
                    $("#spinner").attr("style", "display: inline-block !important");
                    $("#btn-text").text('Signing In...');
                    $("#submit").prop("disabled", true);
                    $(".text-danger").html("");
                    $(".form-control").removeClass("border-danger");
                    $(".custom-input").removeClass("border-danger");
                },
                success: function(data) {
                    $("#spinner").attr("style", "display: none !important");
                    $("#btn-text").text('Sign In');
                    $("#submit").prop("disabled", false);
                    if (data.error && typeof data.error === "boolean" && !data.message) {
                        $.each(data, function(key, value) {
                            if (key !== 'error') {
                                $("#error-" + key).html(value);
                                $("#" + key).addClass("border-danger");
                            }
                        });
                    }
                    if (data.error && typeof data.error === "boolean" && data.message) {
                        alertify.error(data.message);
                    }
                    if (data.success && typeof data.success === "boolean" && data.success) {
                        $("#form .form-control").removeClass("border-danger");
                        $("#form .text-danger").html(" ");
                        alertify.success(data.message);
                        setTimeout(function() {
                            window.location.href = data.redirect || '{{ url("partner/dashboard") }}';
                        }, 1000);
                    }
                },
                error: function(xhr) {
                    $("#spinner").attr("style", "display: none !important");
                    $("#btn-text").text('Sign In');
                    $("#submit").prop("disabled", false);
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        alertify.error(xhr.responseJSON.message);
                    } else {
                        errorMessage(xhr.status);
                    }
                },
            });
        });
    </script>
@endsection
