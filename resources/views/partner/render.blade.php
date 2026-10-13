<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @yield('metaTitle')
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('public/home/images/favicon/favicon.png') }}" />
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('public/home/images/favicon/favicon-180.png') }}">
    <link rel="stylesheet" href="{{ asset('public/home/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('public/home/css/animate.css') }}">
    <link rel="stylesheet" href="{{ asset('public/home/css/aos.css') }}">
    <!-- Custom CSS for Partner Portal -->
    <link rel="stylesheet" href="{{ asset('public/partner/assets/css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('public/partner/assets/css/responsive.css') }}">
    @yield('myCSS')
</head>
<body>

    <div class="partner-wrapper">
        <!-- Left Auth Panel -->
        <div class="partner-left">
            <div class="logo-container">
                <!-- Replace with actual path if needed -->
                <img src="{{ asset('public/home/images/logo.png') }}" alt="Tizzy Group">
            </div>
            
            <div class="auth-content">
                @yield('Body')
            </div>

            {{-- <div class="copyright">
                © {{ date('Y') }} Tizzy Group. All rights reserved.
            </div> --}}
            
            <!-- Adjust path as needed for the character image -->
            {{-- <img src="{{ asset('public/home/images/character-peeking.png') }}" alt="" class="peeking-character"> --}}
        </div>

        <!-- Right Image Panel -->
        <div class="partner-right"></div>
    </div>

    <script src="{{ asset('public/home/js/jquery.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('public/home/js/aos.js') }}"></script>
    @yield('jsLinks')

    
    @yield('myJS')

    <script>
        AOS.init();

        function errorMessage(id) {
            if (id == 403) {
                alertify.error('Your Session Expired ! Please Reload the Page.');
            } else if (id == 404) {
                alertify.error('Define ajax URL is not a valid controller name.');
            } else {
                alertify.error('Error !');
            }
        }
    </script>
</body>
</html>
