@extends('partner.render')

@section('metaTitle')
    <title>Reset Password | Tizzy Group</title>
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
    <h2 class="auth-heading" data-aos="fade-up" data-aos-duration="500">Reset Password</h2>

    <form id="form" data-aos="fade-up" data-aos-duration="900" class="mt-4">
        @csrf
       <div class="mb-3">
            <label class="form-label custom-label">Email <span class="text-danger">*</span></label>
            <input type="text" name="email" value="{{ $email }}" readonly class="form-control custom-input">
            <div class="text-danger small mt-1 text-start" id="error-email"></div>
        </div>
        

        <div class="mb-3">
            <label class="form-label custom-label">New Password <span class="text-danger">*</span></label>
            <input type="password" name="password" id="password" class="form-control custom-input" placeholder="Enter new password">
            <div class="text-danger small mt-1 text-start" id="error-password"></div>
        </div>

        <div class="mb-4">
            <label class="form-label custom-label">Confirm New Password <span class="text-danger">*</span></label>
            <input type="password" name="password_confirmation" id="password_confirmation" class="form-control custom-input" placeholder="Confirm new password">
            <div class="text-danger small mt-1 text-start" id="error-password_confirmation"></div>
        </div>

        <button type="submit" id="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
            <span id="spinner" class="spinner-border spinner-border-sm" role="status" style="display:none !important;"></span>
            <span id="btn-text">Reset Password</span>
        </button>
    </form>
@endsection

@section('myJS')
    <script>
        $("#form").on("submit", function(e) {
            e.preventDefault();
            $.ajax({
                url: '{{ url("partner/reset-password") }}',
                type: "POST",
                data: new FormData(this),
                contentType: false,
                cache: false,
                processData: false,
                dataType: "json",
                beforeSend: function() {
                    $("#spinner").attr("style", "display: inline-block !important");
                    $("#btn-text").text('Resetting...');
                    $("#submit").prop("disabled", true);
                    $(".text-danger").html("");
                    $(".form-control").removeClass("border-danger");
                    $(".custom-input").removeClass("border-danger");
                },
                success: function(data) {
                    $("#spinner").attr("style", "display: none !important");
                    $("#btn-text").text('Reset Password');
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
                            window.location.href = data.redirect || '{{ url("partner/login") }}';
                        }, 1000);
                    }
                },
                error: function(xhr) {
                    $("#spinner").attr("style", "display: none !important");
                    $("#btn-text").text('Reset Password');
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
                    } else {
                        alertify.error("An error occurred. Please try again.");
                    }
                }
            });
        });
    </script>
@endsection
