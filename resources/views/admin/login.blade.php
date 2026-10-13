<!doctype html>
<html lang="en">
@php
    DB::connection()->getPDO();
    // $setting = DB::table('admin_settings')->where('id', 1)->get();
@endphp

<head>
    <meta charset="utf-8" />
    <title>Admin Login - Secure Portal</title>
    <meta name='viewport' content='width=device-width, initial-scale=1.0, user-scalable=0'>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <!-- Google Fonts: Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap Css -->
    <link href="{{ asset('assets/plugins/bootstrap/css/bootstrap.css') }}" id="style" rel="stylesheet" />

    <!-- alertifyjs Css -->
    <link href="{{ asset('assets/alertifyjs/build/css/alertify.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/alertifyjs/build/css/themes/default.min.css') }}" rel="stylesheet" type="text/css" />

    <!-- select 2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <style>
        :root {
            --primary-color: #6366f1;
            --primary-hover: #4f46e5;
            --bg-color: #0f172a;
            --surface-color: rgba(30, 41, 59, 0.7);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border-color: rgba(255, 255, 255, 0.1);
            --input-bg: rgba(15, 23, 42, 0.6);
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-color);
            background-image:
                radial-gradient(at 0% 0%, hsla(253, 16%, 7%, 1) 0, transparent 50%),
                radial-gradient(at 50% 0%, hsla(225, 39%, 30%, 0.5) 0, transparent 50%),
                radial-gradient(at 100% 0%, hsla(339, 49%, 30%, 0.5) 0, transparent 50%);
            background-size: cover;
            background-attachment: fixed;
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            overflow: hidden;
            position: relative;
        }

        /* Animated background blobs */
        .shape-blob {
            position: absolute;
            filter: blur(80px);
            z-index: -1;
            opacity: 0.6;
            animation: move 10s infinite alternate cubic-bezier(0.42, 0, 0.58, 1);
        }

        .shape-blob.one {
            top: -10%;
            left: -10%;
            width: 500px;
            height: 500px;
            background: #6366f1;
            border-radius: 50%;
        }

        .shape-blob.two {
            bottom: -20%;
            right: -10%;
            width: 600px;
            height: 600px;
            background: #ec4899;
            border-radius: 50%;
            animation-delay: 2s;
        }

        @keyframes move {
            0% {
                transform: translate(0, 0) scale(1);
            }

            100% {
                transform: translate(30px, -50px) scale(1.1);
            }
        }

        .auth-wrapper {
            width: 100%;
            max-width: 1000px;
            margin: 20px;
            z-index: 10;
        }

        .auth-card {
            background: var(--surface-color);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            overflow: hidden;
            display: flex;
            min-height: 600px;
        }

        .auth-sidebar {
            flex: 1;
            padding: 4rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(236, 72, 153, 0.05) 100%);
            border-right: 1px solid var(--border-color);
            position: relative;
        }

        .auth-sidebar h1 {
            font-size: 2.8rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            background: linear-gradient(to right, #818cf8, #f472b6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1.2;
        }

        .auth-sidebar p {
            font-size: 1.1rem;
            color: var(--text-muted);
            line-height: 1.8;
            font-weight: 300;
        }

        .auth-form-area {
            flex: 1;
            padding: 4rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .auth-header {
            margin-bottom: 2.5rem;
        }

        .auth-header h3 {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #fff;
        }

        .auth-header p {
            color: var(--text-muted);
            font-weight: 300;
            font-size: 1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.6rem;
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--text-muted);
            transition: all 0.3s;
        }

        /* Inputs & Select2 Overrides */
        .form-control,
        .select2-container--default .select2-selection--single {
            background: var(--input-bg) !important;
            border: 1px solid var(--border-color) !important;
            border-radius: 12px !important;
            padding: 0.8rem 1.2rem;
            color: var(--text-main) !important;
            font-size: 1rem;
            height: auto !important;
            transition: all 0.3s ease;
            box-shadow: none !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: var(--text-main) !important;
            line-height: 1.5 !important;
            padding-left: 0 !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 100% !important;
            right: 15px !important;
        }

        .select2-dropdown {
            background-color: #1e293b !important;
            border: 1px solid var(--border-color) !important;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
        }

        .select2-search--dropdown .select2-search__field {
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            color: #fff;
            border-radius: 6px;
        }

        .select2-container--default .select2-results__option {
            color: var(--text-muted);
            padding: 10px 15px;
        }

        .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable {
            background-color: var(--primary-color) !important;
            color: #fff;
        }

        .select2-container--default .select2-results__option--selected {
            background-color: rgba(255, 255, 255, 0.1) !important;
        }

        .form-control:focus,
        .select2-container--default.select2-container--open .select2-selection--single {
            border-color: var(--primary-color) !important;
            background: rgba(15, 23, 42, 0.8) !important;
            outline: none;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15) !important;
        }

        /* Button override */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, #818cf8 100%);
            border: none;
            border-radius: 12px;
            padding: 1rem;
            font-size: 1.1rem;
            font-weight: 600;
            width: 100%;
            margin-top: 1rem;
            transition: all 0.3s ease;
            color: #fff;
            letter-spacing: 0.5px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -10px rgba(99, 102, 241, 0.6);
            background: linear-gradient(135deg, var(--primary-hover) 0%, #818cf8 100%);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .form-check {
            display: flex;
            align-items: center;
            margin-top: 1rem;
        }

        .form-check-input {
            width: 18px;
            height: 18px;
            background-color: var(--input-bg);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            cursor: pointer;
            margin-top: 0;
            margin-right: 10px;
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .form-check-label {
            color: var(--text-muted);
            font-size: 0.95rem;
            cursor: pointer;
            user-select: none;
            margin-bottom: 0;
            padding-top: 1px;
        }

        .error {
            font-size: 0.85rem;
            margin-top: 0.5rem;
            color: #ef4444;
            display: block;
            min-height: 1.2em;
        }

        .border-danger {
            border-color: #ef4444 !important;
        }

        /* Spinner Loader */
        .bx-spin {
            animation: spin 2s linear infinite;
            display: inline-block;
        }

        @keyframes spin {
            100% {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 768px) {
            .auth-card {
                flex-direction: column;
                min-height: auto;
            }

            .auth-sidebar {
                padding: 3rem 2rem;
                border-right: none;
                border-bottom: 1px solid var(--border-color);
                text-align: center;
            }

            .auth-sidebar h1 {
                font-size: 2.2rem;
            }

            .auth-form-area {
                padding: 3rem 2rem;
            }
        }
    </style>
</head>

<body>
    <!-- Background Animated Shapes -->
    <div class="shape-blob one"></div>
    <div class="shape-blob two"></div>

    <div class="auth-wrapper d-flex justify-content-center align-items-center">
        <div class="auth-card">

            <!-- Sidebar Area -->
            <div class="auth-sidebar d-none d-md-flex">
                <div>
                    <h1>Premium<br>Admin Portal</h1>
                    <p>Experience seamless management and optimized workflow. Please authenticate your access to enter
                        the secure environment.</p>
                </div>
            </div>

            <!-- Form Area -->
            <div class="auth-form-area">
                <div class="auth-header">
                    <h3>Welcome Back</h3>
                    <p>Sign in to your account</p>
                </div>

                <form id="form">
                    @csrf

                    <div class="form-group">
                        <label for="username">Email Address</label>
                        <input class="form-control" name="username" placeholder="Enter your email" id="username"
                            type="text" value="{{ Cookie::get('adminUsername') }}">
                        <div class="error" id="error-username"></div>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input class="form-control" name="password" placeholder="Enter your password" id="password"
                            type="password" value="{{ Cookie::get('adminPassword') }}">
                        <div class="error" id="error-password"></div>
                    </div>

                    <div class="form-group">
                        <label for="branch">Select Branch</label>
                        <select name="branch" id="branch" class="form-control w-100">
                            <option value="">Choose a branch...</option>
                            @if (!empty($branchlist))
                                @foreach ($branchlist as $branchRow)
                                    <option value="{{ $branchRow->id }}"
                                        @if (Cookie::get('branch') == $branchRow->id) selected @endif>
                                        {{ $branchRow->branch_name }}
                                    </option>
                                @endforeach
                            @endif
                        </select>
                        <div class="error" id="error-branch"></div>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remember-check" name="remember"
                            value="1" @if (Cookie::get('remember') == '1') checked @endif>
                        <label class="form-check-label" for="remember-check">
                            Remember my device
                        </label>
                    </div>

                    <button name="submit" type="submit" id="submit"
                        class="btn btn-primary d-flex justify-content-center align-items-center gap-2">
                        <svg id="spinner" style="display: none; height: 20px; width: 20px;" class="bx-spin"
                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span>Secure Login</span>
                    </button>

                    <div class="mt-4 text-center">
                        <p class="text-muted" style="font-size: 0.85rem; margin-top: 1.5rem;">
                            &copy;
                            <script>
                                document.write(new Date().getFullYear())
                            </script> Crafted with Precision.
                        </p>
                    </div>
                </form>
            </div>

        </div>
    </div>

    <!-- Core Scripts - Retained Original Functionality -->
    <script src="{{ asset('assets/plugins/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/bootstrap/js/bootstrap.min.js') }}"></script>

    <!-- Alertify JS & Select2 -->
    <script src="{{ asset('assets/alertifyjs/build/alertify.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script type="text/javascript">
        $(document).ready(function() {
            // Initialize custom matched Select2
            $('#branch').select2({
                minimumResultsForSearch: Infinity,
                width: '100%',
                dropdownParent: $('.auth-card')
            });

            // Form submission logic mapping seamlessly backwards 
            $("#form").on("submit", function(e) {
                e.preventDefault();
                $.ajax({
                    url: "{{ route('admin.login') }}",
                    type: "POST",
                    data: new FormData(this),
                    contentType: false,
                    cache: false,
                    processData: false,
                    dataType: "json",
                    beforeSend: function() {
                        $("#spinner").show();
                        $("#submit").attr("disabled", true).css("opacity", "0.7");
                    },
                    success: function(data) {
                        $("#spinner").hide();
                        $("#submit").attr("disabled", false).css("opacity", "1");

                        // Clear previous errors safely
                        $(".error").html("");
                        $(".form-control").removeClass("border-danger");
                        $('.select2-selection').removeClass("border-danger");

                        if (data.error) {
                            $.each(data, function(key, value) {
                                if (value) {
                                    $("#error-" + key).html(value);
                                    $("#" + key).addClass("border-danger");
                                    // Make sure custom dropdown reacts to errors properly
                                    if (key === 'branch') {
                                        $('#branch').next('.select2-container').find(
                                            '.select2-selection').addClass(
                                            "border-danger");
                                    }
                                }
                            });
                        }
                        if (data.alert_error) {
                            alertify.error(data.alert_error);
                        }
                        if (data.success) {
                            alertify.success(data.success);
                            setTimeout(function() {
                                window.location.href = "{{ route('dashboard') }}";
                            }, 1000);
                        }
                    },
                    error: function() {
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
