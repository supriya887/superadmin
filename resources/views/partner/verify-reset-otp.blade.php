@extends('partner.render')

@section('metaTitle')
    <title>Verify OTP | Tizzy Group</title>
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
    <h2 class="auth-heading" data-aos="fade-up" data-aos-duration="500">Verify Reset Password OTP</h2>
    <p class="auth-desc" data-aos="fade-up" data-aos-duration="700">We've emailed you a 6-digit verification code. <br> Please enter it below to reset your password.</p>
    
    @if(session('reset_email'))
        <span class="text-center fw-bold EmailID d-block mb-3" style="color: #1665b6;">{{ session('reset_email') }}</span>
    @endif

    <form id="form" data-aos="fade-up" data-aos-duration="900">
        @csrf
        <div class="mb-4">
            <label class="custom-label d-block text-center mb-3">Enter your 6-digit code <span class="text-danger">*</span></label>
            <div class="d-flex justify-content-center gap-2 otp-input-group">
                <input type="text" class="custom-input text-center otp-box" style="width: 45px; padding: 0;" maxlength="1" pattern="[0-9]*" inputmode="numeric">
                <input type="text" class="custom-input text-center otp-box" style="width: 45px; padding: 0;" maxlength="1" pattern="[0-9]*" inputmode="numeric">
                <input type="text" class="custom-input text-center otp-box" style="width: 45px; padding: 0;" maxlength="1" pattern="[0-9]*" inputmode="numeric">
                <input type="text" class="custom-input text-center otp-box" style="width: 45px; padding: 0;" maxlength="1" pattern="[0-9]*" inputmode="numeric">
                <input type="text" class="custom-input text-center otp-box" style="width: 45px; padding: 0;" maxlength="1" pattern="[0-9]*" inputmode="numeric">
                <input type="text" class="custom-input text-center otp-box" style="width: 45px; padding: 0;" maxlength="1" pattern="[0-9]*" inputmode="numeric">
            </div>
            <input type="hidden" name="otp" id="otp">
            <div class="text-danger small mt-2 text-center" id="error-otp"></div>
        </div>

        <button type="submit" id="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
            <span id="spinner" class="spinner-border spinner-border-sm" role="status" style="display:none !important;"></span>
            <span id="btn-text">Verify & Continue</span>
        </button>
    </form>

    <div class="back-to-login" data-aos="fade-up" data-aos-duration="1000">
        Back to <a href="{{ url('partner/login') }}">Sign in</a>
    </div>
@endsection

@section('myJS')
    <script>
        $(document).ready(function() {
            const inputs = $(".otp-box");
            
            inputs.on("input", function(e) {
                const target = e.target;
                const val = target.value;
                if (isNaN(val)) { target.value = ""; return; }
                if (val != "") {
                    const next = target.nextElementSibling;
                    if (next) next.focus();
                }
                updateHiddenOtp();
            });
            
            inputs.on("keydown", function(e) {
                const target = e.target;
                const key = e.key.toLowerCase();
                if (key == "backspace" || key == "delete") {
                    if (target.value == "") {
                        const prev = target.previousElementSibling;
                        if (prev) prev.focus();
                    } else { target.value = ""; }
                    updateHiddenOtp();
                }
            });

            inputs.on("paste", function(e) {
                const paste = (e.originalEvent.clipboardData || window.clipboardData).getData('text');
                const pasteArray = paste.replace(/[^0-9]/g, '').split('');
                inputs.each(function(i) {
                    if (pasteArray[i]) { $(this).val(pasteArray[i]); } else { $(this).val(""); }
                });
                inputs.eq(Math.min(pasteArray.length - 1, 5)).focus();
                updateHiddenOtp();
                e.preventDefault();
            });
            
            function updateHiddenOtp() {
                let otp = "";
                inputs.each(function() { otp += $(this).val(); });
                $("#otp").val(otp);
            }
        });

        $("#form").on("submit", function(e) {
            e.preventDefault();
            $.ajax({
                url: '{{ url("partner/verify-reset-otp") }}',
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
                    $("#btn-text").text('Verify & Continue');
                    $("#submit").prop("disabled", false);
                    if (data.error) {
                        $.each(data, function(key, value) {
                            if (key !== 'error') {
                                $("#error-" + key).html(value);
                            }
                        });
                    }
                    if (data.alert_error) { alertify.error(data.alert_error); }
                    if (data.success) {
                        alertify.success(data.message);
                        setTimeout(function() {
                            window.location.href = data.redirect;
                        }, 1000);
                    }
                },
                error: function(xhr) {
                    $("#spinner").attr("style", "display: none !important");
                    $("#btn-text").text('Verify & Continue');
                    $("#submit").prop("disabled", false);
                    if (xhr.status === 422 && xhr.responseJSON) {
                        let data = xhr.responseJSON;
                        $.each(data, function(key, value) {
                            if (key !== 'error' && key !== 'message' && key !== 'data') {
                                $("#error-" + key).html(value);
                            }
                        });
                        if (data.message && data.message !== 'Validation failed') {
                            alertify.error(data.message);
                        }
                    } else {
                        alertify.error("An error occurred. Please try again.");
                    }
                }
            });
        });
    </script>
@endsection
