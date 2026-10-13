<!doctype html>
<html lang="en" data-skin="luxe" data-bs-theme="light" data-menu-color="light" data-topbar-color="dark" data-layout-width="fluid" dir="ltr" data-sidenav-size="on-hover-active" data-sidenav-user="true">
<head>
    <meta charset="utf-8" />
    <title>Super Admin — Sign In</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <!-- App favicon -->
    <link rel="icon" sizes="32x32" href="{{ asset('home/images/favicon/favicon-32.png') }}">
    <link rel="icon" sizes="16x16" href="{{ asset('home/images/favicon/favicon-16.png') }}">

    <!-- Theme Config Js -->
    <script src="{{ asset('superadmin/js/config.js') }}"></script>

    <!-- Vendor css -->
    <link href="{{ asset('superadmin/css/vendors.min.css') }}" rel="stylesheet" type="text/css" />

    <!-- App css -->
    <link id="app-style" href="{{ asset('superadmin/css/app.min.css') }}" rel="stylesheet" type="text/css" />

    <!-- Alertify (load in head so styles apply before paint) -->
    <link href="https://cdn.jsdelivr.net/npm/alertifyjs@1.14.0/build/css/alertify.min.css" rel="stylesheet" type="text/css" />
    <link href="https://cdn.jsdelivr.net/npm/alertifyjs@1.14.0/build/css/themes/default.min.css" rel="stylesheet" type="text/css" />
</head>

<body>
    <div class="position-absolute top-0 end-0">
        <img src="{{ asset('superadmin/images/auth-card-bg.svg') }}" class="auth-card-bg-img" alt="auth-card-bg" />
    </div>
    <div class="position-absolute bottom-0 start-0" style="transform: rotate(180deg)">
        <img src="{{ asset('superadmin/images/auth-card-bg.svg') }}" class="auth-card-bg-img" alt="auth-card-bg" />
    </div>

    <div class="auth-box d-flex align-items-center">
        <div class="container-xxl">
            <div class="row align-items-center justify-content-center">
                <div class="col-xl-10">
                    <div class="card">
                        <div class="row justify-content-between g-0">

                            {{-- Left: Login Form --}}
                            <div class="col-lg-6">
                                <div class="card-body">
                                    <div class="auth-brand text-center mb-4 position-relative">
                                        <a href="{{ route('superadmin.login') }}" class="logo-dark">
                                            <img src="{{ url('home/images/logo.png') }}" alt="dark logo" />
                                        </a>
                                        <a href="{{ route('superadmin.login') }}" class="logo-light">
                                            <img src="{{ url('home/images/logo.png') }}" alt="logo" />
                                        </a>
                                        <h4 class="fw-bold text-dark mt-3">Super Admin Panel</h4>
                                        <p class="text-muted w-lg-75 mx-auto">Enter your credentials to access the Super Admin area.</p>
                                    </div>

                                    @if(session('error'))
                                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                            {{ session('error') }}
                                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                        </div>
                                    @endif
                                    @if(session('success'))
                                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                                            {{ session('success') }}
                                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                        </div>
                                    @endif

                                    <form id="form" autocomplete="off">
                                        @csrf

                                        <div class="mb-3">
                                            <label for="username" class="form-label">
                                                Username <span class="text-danger">*</span>
                                            </label>
                                            <div class="app-search">
                                                <input type="text"
                                                       class="form-control"
                                                       id="username"
                                                       name="username"
                                                       placeholder="Enter username" />
                                                <i class="ti ti-user app-search-icon text-muted"></i>
                                            </div>
                                            <div class="error" id="error-username"></div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="password" class="form-label">
                                                Password <span class="text-danger">*</span>
                                            </label>
                                            <div class="app-search">
                                                <input type="password"
                                                       class="form-control"
                                                       id="password"
                                                       name="password"
                                                       placeholder="••••••••" />
                                                <i class="ti ti-lock-password app-search-icon text-muted"></i>
                                            </div>
                                            <div class="error" id="error-password"></div>
                                        </div>

                                        <div class="d-grid mt-4">
                                            <button name="submit" type="submit" id="submit"
                                                class="btn btn-primary fw-semibold py-2 d-flex justify-content-center align-items-center gap-2">
                                                <svg id="spinner" style="display:none; height:20px; width:20px;" class="bx-spin"
                                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                </svg>
                                                <span>Sign In</span>
                                            </button>
                                        </div>
                                    </form>

                                    <p class="text-center text-muted mt-4 mb-0">
                                        &copy; <script>document.write(new Date().getFullYear())</script> Admin Panel
                                    </p>
                                </div>
                            </div>

                            {{-- Right: Decorative Image --}}
                            <div class="col-lg-6 d-none d-lg-block">
                                <div class="h-100 position-relative card-side-img rounded-end overflow-hidden"
                                     style="background-image: url({{ asset('superadmin/images/auth.jpg') }})">
                                    <div class="p-4 card-img-overlay rounded-end auth-overlay d-flex align-items-end justify-content-center"></div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>window.SUPERADMIN_I18N_PATH = @json(asset('superadmin/assets/data/translations/'));</script>
    <script src="{{ asset('superadmin/js/vendors.min.js') }}"></script>

    <script src="{{ asset('superadmin/js/app.js') }}"></script>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/alertifyjs@1.14.0/build/alertify.min.js"></script>

    <style>
        .bx-spin { animation: spin 2s linear infinite; display: inline-block; }
        @keyframes spin { 100% { transform: rotate(360deg); } }
        .error { font-size: 0.85rem; margin-top: 0.4rem; color: #ef4444; display: block; min-height: 1.2em; }
        .border-danger { border-color: #ef4444 !important; }
    </style>

    <script type="text/javascript">
        $(document).ready(function () {
            $("#form").on("submit", function (e) {
                e.preventDefault();
                $.ajax({
                    url: "{{ route('superadmin.login.post') }}",
                    type: "POST",
                    data: new FormData(this),
                    contentType: false,
                    cache: false,
                    processData: false,
                    dataType: "json",
                    beforeSend: function () {
                        $("#spinner").show();
                        $("#submit").attr("disabled", true).css("opacity", "0.7");
                    },
                    success: function (data) {
                        $("#spinner").hide();
                        $("#submit").attr("disabled", false).css("opacity", "1");

                        $(".error").html("");
                        $(".form-control").removeClass("border-danger");

                        if (data.error) {
                            $.each(data, function (key, value) {
                                if (value && key !== 'error') {
                                    $("#error-" + key).html(value);
                                    $("#" + key).addClass("border-danger");
                                }
                            });
                            if (data.message) {
                                alertify.error(data.message);
                            }
                        }

                        if (data.success) {
                            alertify.success(data.message || 'Login successfully.');
                            setTimeout(function () {
                                window.location.href = data.redirect;
                            }, 1000);
                        }
                    },
                    error: function () {
                        $("#spinner").hide();
                        $("#submit").attr("disabled", false).css("opacity", "1");
                        alertify.error("A network error occurred.");
                    }
                });
            });
        });
    </script>
</body>
</html>
