@extends('admin.render')
@section('metaTitle')
    <meta content="{{ $title }}" name="description" />
    <title>{{ $title }}</title>
@endsection
@section('cssLinks')
@endsection
@section('myCSS')
@endsection
@section('pageTitle')
@endsection
@section('cardHeading')
@endsection
@section('cardBody')
    <div class="main-container container-fluid">
        <div class="breadcrumb-header justify-content-between">
            <div>
                <h4 class="content-title mb-2">{{ $title }}</h4>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ url('dashboard') }}">Admin</a></li>
                        <li class="breadcrumb-item active" aria-current="page"> {{ $title }}</li>
                    </ol>
                </nav>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <form id="form">

                    <div class="row d-flex justify-content-center">

                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="horizontal-firstname-input" class="form-label">Currunt
                                    Password<span class="link-danger">*</span></label>
                                <input type="password" name="password" id="password" class="form-control" vlaue="">
                                <div class="text-danger" id="error-password"></div>

                            </div>
                            <div class="form-group">
                                <label for="horizontal-email-input" class="form-label">New Password<span
                                        class="link-danger">*</span></label>
                                <input type="text" id="new_password" name="new_password" class="form-control"
                                    value="">
                                <div class="text-danger" id="error-new_password"></div>

                            </div>
                            <div class="form-group">
                                <label for="horizontal-password-input" class="form-label">Confirm
                                    Password<span class="link-danger">*</span></label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                                    vlaue="">
                                <div class="text-danger" id="error-confirm_password"></div>

                            </div>
                        </div>

                        @csrf

                        <div class="col-sm-12 text-center mt-4">
                            <button type="submit" class="btn btn-primary w-md" name="submit" id="submit">
                                <i class="bx bx-loader bx-spin font-size-16 align-middle me-2" style="display: none;"
                                    id="spinner"></i> Save
                            </button>
                            <a href="" class="btn btn-danger w-md">Back </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
@section('jsLinks')
@endsection
@section('myJS')
    <script>
        $("#form").on("submit", function(e) {
            // var _token = $('#csrf').val();
            e.preventDefault();
            $.ajax({
                url: "{{ route('changepassword-validation') }}",
                type: "post",
                data: new FormData(this),
                contentType: false,
                cache: false,
                processData: false,
                dataType: "json",
                // alert(data),
                beforeSend: function(data) {
                    // $("#spinner").show();
                    $("#submit").attr("disabled", true);
                },
                success: function(data) {
                    $("#spinner").hide();
                    $("#submit").attr("disabled", false);
                    if (data.error) {
                        $.each(data, function(key, value) {
                            if (value) {
                                $('#error-' + key).html(value);
                                $('#' + key).addClass("border-danger");
                            } else {
                                $('#error-' + key).html(" ");
                                $('#' + key).removeClass("border-danger");
                            }
                        });
                    }
                    if (data.success) {
                        $("#form .error").html(" ");
                        $('#form .form-control').removeClass("border-danger");
                        alertify.success(data.success);
                        window.location.href = '{{ url('logout') }}';
                    }
                },
                error: function(error) {
                    $("#spinner").hide();
                    $("#submit").attr("disabled", false);
                    errorMessage(error.status);
                }
            });
        });
    </script>
@endsection
