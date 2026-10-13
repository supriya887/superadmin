@extends('partner.render')

@section('metaTitle')
    <title>Verify OTP | Tizzy Group</title>
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
    @if(session('success'))
        <script>alertify.success("{{ session('success') }}");</script>
    @endif
    @if(session('error'))
        <script>alertify.error("{{ session('error') }}");</script>
    @endif
@endsection

@section('Body')
    <h2 data-aos="fade-up" data-aos-duration="500">Verify Your Email </h2>
    <p data-aos="fade-up" data-aos-duration="700">We've emailed you a 6-digit verification code. <br> Please enter it below to confirm your email.</p>
    
    @if(session('partner_email'))
        <span class="text-center fw-bold EmailID" style="color: #1665b6;">{{ session('partner_email') }}</span>
    @endif

    <form id="form" data-aos="fade-up" data-aos-duration="900">
        @csrf
        <div class="mb-4">
            <label class="form-label text-muted d-block text-center mb-3">Enter your 6-digit code <span class="text-danger">*</span></label>
            <div class="d-flex justify-content-center gap-3 otp-input-group">
                <input type="text" class="form-control text-center otp-box" maxlength="1" pattern="[0-9]*" inputmode="numeric">
                <input type="text" class="form-control text-center otp-box" maxlength="1" pattern="[0-9]*" inputmode="numeric">
                <input type="text" class="form-control text-center otp-box" maxlength="1" pattern="[0-9]*" inputmode="numeric">
                <input type="text" class="form-control text-center otp-box" maxlength="1" pattern="[0-9]*" inputmode="numeric">
                <input type="text" class="form-control text-center otp-box" maxlength="1" pattern="[0-9]*" inputmode="numeric">
                <input type="text" class="form-control text-center otp-box" maxlength="1" pattern="[0-9]*" inputmode="numeric">
            </div>
            <input type="hidden" name="otp" id="otp">
            <div class="text-danger small mt-1" id="error-otp"></div>
        </div>

        <button type="submit" id="submit" class="btn btn-primary d-flex align-items-center justify-content-center gap-2">
            <span id="spinner" class="spinner-border spinner-border-sm" role="status" style="display:none !important;"></span>
            <span id="btn-text">Confirm</span>
        </button>
    </form>

    <form action="{{ url('partner/send-otp') }}" method="POST" data-aos="fade-up" data-aos-duration="1000">
        @csrf
        <input type="hidden" name="email" value="{{ session('partner_email') }}">
        <div class="text-center mt-3">
            <button type="submit" class="btn btn-link p-0 m-0 align-baseline text-decoration-none" style="font-size: 0.85rem; color: #1665b6; font-weight: 500;">
                Didn't receive a code? Resend
            </button>
        </div>
    </form>

    <div class="text-center mt-4">
        <p class="text-muted" style="font-size: 0.85rem;">
            Back to <a href="{{ url('partner/login') }}" class="text-decoration-none fw-bold" style="color: #1665b6;">Login</a>
        </p>
    </div>
@endsection

@section('myJS')
    <script>
        $(document).ready(function() {
            const inputs = $(".otp-box");
            
            inputs.on("input", function(e) {
                const target = e.target;
                const val = target.value;
                
                if (isNaN(val)) {
                    target.value = "";
                    return;
                }
                
                if (val != "") {
                    const next = target.nextElementSibling;
                    if (next) {
                        next.focus();
                    }
                }
                updateHiddenOtp();
            });
            
            inputs.on("keydown", function(e) {
                const target = e.target;
                const key = e.key.toLowerCase();
                
                if (key == "backspace" || key == "delete") {
                    if (target.value == "") {
                        const prev = target.previousElementSibling;
                        if (prev) {
                            prev.focus();
                        }
                    } else {
                        target.value = "";
                    }
                    updateHiddenOtp();
                }
            });

            inputs.on("paste", function(e) {
                const paste = (e.originalEvent.clipboardData || window.clipboardData).getData('text');
                const pasteArray = paste.replace(/[^0-9]/g, '').split('');
                inputs.each(function(i) {
                    if (pasteArray[i]) {
                        $(this).val(pasteArray[i]);
                    } else {
                        $(this).val("");
                    }
                });
                inputs.eq(Math.min(pasteArray.length - 1, 5)).focus();
                updateHiddenOtp();
                e.preventDefault();
            });
            
            function updateHiddenOtp() {
                let otp = "";
                inputs.each(function() {
                    otp += $(this).val();
                });
                $("#otp").val(otp);
            }
        });

        $("#form").on("submit", function(e) {
            e.preventDefault();
            $.ajax({
                url: '{{ url("partner/verify-otp") }}',
                type: "POST",
                data: new FormData(this),
                contentType: false,
                cache: false,
                processData: false,
                dataType: "json",
                beforeSend: function(data) {
                    $("#spinner").attr("style", "display: inline-block !important");
                    $("#btn-text").text('Verifying...');
                    $("#submit").prop("disabled", true);
                    $(".text-danger").html("");
                    $(".otp-box").removeClass("border-danger");
                },
                success: function(data) {
                    $("#spinner").attr("style", "display: none !important");
                    $("#btn-text").text('Confirm');
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
                        $("#form .form-control").removeClass("border-danger");
                        $("#form .text-danger").html(" ");
                        alertify.success(data.message);
                        setTimeout(function() {
                            window.location.href =  '{{ url("partner/verify-successfully") }}';
                        }, 1000);
                    }
                },
                error: function(xhr) {
                    $("#spinner").attr("style", "display: none !important");
                    $("#btn-text").text('Confirm');
                    $("#submit").prop("disabled", false);
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        $.each(xhr.responseJSON.errors, function(key, value) {
                            $("#error-" + key).html(value[0]);
                            $("#" + key).addClass("border-danger");
                        });
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
