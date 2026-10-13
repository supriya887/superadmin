<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="language" content="EN" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no,user-scalable=no">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="apple-mobile-web-app-title" content="My App">

    @yield('metaTitle')

    @yield('cssLinks')

    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset(' ') }}" />
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('home/images/favicon/favicon-180.png') }}">
    <link rel="icon" sizes="32x32" href="{{ asset('home/images/favicon/favicon-32.png') }}">
    <link rel="icon" sizes="16x16" href="{{ asset('home/images/favicon/favicon-16.png') }}">
    <link rel="stylesheet" href="{{ asset('home/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('home/css/swiper.min.css') }}">
    <link rel="stylesheet" href="{{ asset('home/css/animate.css') }}">
    <link rel="stylesheet" href="{{ asset('home/css/aos.css') }}">
    <link rel="stylesheet" href="{{ asset('home/css/default.css') }}">
    <link rel="stylesheet" href="{{ asset('home/css/tizzycloud.css') }}">

    @yield('myCSS')
    <style>
        .topbar-dropdown {
            cursor: pointer;
            margin-left: 10px;
            padding: 0;
        }

        .topbar-dropdown-link {
            padding: 0;
            background: transparent;
        }

        .topbar-avatar {
            width: 36px;
            height: 36px;
            background-color: #48dbfb;
            text-transform: uppercase;
        }

        .topbar-user-name {
            font-size: 14px;
        }

        .topbar-user-role {
            font-size: 11px;
        }

        .topbar-chevron {
            font-size: 13px;
        }

        .top-header {
            position: relative;
        }

        .topbar-dropdown .dropdown-menu {
            z-index: 9999;
            background-color: #1665b6;
            /* Tizzy Cloud Blue */
            border-radius: 8px;
            padding: 8px 0 !important;
            margin-top: 15px !important;
            min-width: 160px;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15) !important;
        }

        /* Override inherited .top-header-text li & a styles */
        .top-header .topbar-dropdown .dropdown-menu li {
            margin: 0 !important;
            padding: 0 !important;
            border: none !important;
            display: block !important;
        }

        .top-header .topbar-dropdown .dropdown-menu li a.dropdown-item {
            color: #ffffff !important;
            font-weight: 500 !important;
            font-size: 14px !important;
            padding: 10px 20px !important;
            margin: 0 !important;
            transition: all 0.2s ease-in-out !important;
            background-color: transparent !important;
            display: block !important;
            text-align: left !important;
            width: 100% !important;
            line-height: normal !important;
        }

        .top-header .topbar-dropdown .dropdown-menu li a.dropdown-item:hover,
        .top-header .topbar-dropdown .dropdown-menu li a.dropdown-item:focus {
            background-color: rgba(255, 255, 255, 0.15) !important;
            color: #ffffff !important;
        }
    </style>
</head>

<body>
    <div id="loader">
        <img src="{{ asset('home/images/logo.png') }}" class="logo">
        <div class="progress-container">
            <div class="progress-bar" id="progress"></div>
        </div>
        <p class="loading-text">Loading...</p>
    </div>
    <header class="main-header g_header">
        <div class="top-header">
            <div class="container container-1760">
                <div class="col-lg-12">
                    <ul class="top-header-text d-flex align-items-center">
                        <li><a href="{{ url('contact') }}">Quick Assist</a></li>
                        <li><a href="{{ url('partner') }}">Parther with us</a></li>
                        <li><a href="{{ url('contact') }}">Sales Enquiry</a></li>
                        <li class="helpline"><a href="tel:+919925992599"><img
                                    src="{{ asset('home/images/Global-Helpline.png') }}" alt=""> +91 9925 9925
                                99</a></li>
                        @if (session()->has('partnerId'))
                            <li class="topbar-dropdown">
                                <a href="{{ url('dashboard') }}"
                                    class="d-flex align-items-center gap-2 text-decoration-none topbar-dropdown-link">
                                    <div
                                        class="rounded-circle d-flex align-items-center justify-content-center fw-normal text-white fs-6 topbar-avatar">
                                        {{ substr(session('partnerName'), 0, 1) }}
                                    </div>
                                    <div class="d-none d-md-flex flex-column lh-sm text-start">
                                        <span class="fw-medium text-white topbar-user-name">{{ session('partnerName') }}</span>
                                        {{-- <span class="text-white-50 topbar-user-role">Founder</span> --}}
                                    </div>
                                    {{-- <i class="fa fa-chevron-down ms-1 text-white-50 topbar-chevron"></i> --}}
                                </a>
                                {{-- <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                    <li><a class="dropdown-item" href="#">My Account</a></li>
                                    <li><a class="dropdown-item" href="{{ url('logout') }}">Logout</a></li>
                                </ul> --}}
                            </li>
                        @else
                        
                            <li class="login"><img src="{{ asset('home/images/icons/person.png') }}" alt=""><a
                                    href="{{ url('user-login') }}">Log In</a> <span>/</span> <a
                                    href="{{ url('user-register') }}">Sign Up</a></li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
        <div class="offcanvas-menu">
            <div class="main-menu">
                <div class="container container-1760 d-flex align-items-center">
                    <div class="mobile-menu d-flex justify-content-between align-items-center">
                        <div class="logo-img">
                            <a href="{{ url('/') }}"><img src="{{ asset('home/images/logo.png') }}"
                                    class="main-logo" alt="" /></a>
                        </div>
                        <div class="mobile-offcanvas d-flex align-items-center gap-4">
                            {{-- Hamburger removed: sidebar is now toggled via the edge arrow button --}}
                            <!-- <div class="cart-btn position-relative"><a href="#"><img src="images/icons/cart.png" class="img-fluid" alt=""><span>01</span></a></div> -->
                            <a class="offcanvas-close" data-bs-toggle="offcanvas" href="#offcanvasExample"
                                role="button" aria-label="Open navigation menu" aria-controls="offcanvasExample"
                                aria-expanded="false"><span class="toggle-img" aria-hidden="true"></span></a>
                        </div>
                    </div>

                    <div class="offcanvas offcanvas-start menu-right" tabindex="-1" id="offcanvasExample"
                        aria-labelledby="offcanvasExampleLabel" role="dialog" aria-modal="true">
                        <h2 class="visually-hidden" id="offcanvasExampleLabel">Navigation menu</h2>
                        <div class="offcanvas-header">
                            <a class="navbar" href="{{ url('/') }}"><img
                                    src="{{ asset('home/images/logo.png') }}" class="img" alt=" "></a>
                            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"
                                aria-label="Close"><img src="{{ asset('home/images/icons/icon-x.svg') }}"
                                    alt=""></button>
                        </div>
                        <div class="offcanvas-body">
                            <div class="main-nav">
                                <nav class="navbar navbar-expand-lg" aria-label="Primary navigation">
                                    <div class="navbar-collapse" id="navbarSupportedContent">
                                        <ul class="navbar-nav ms-auto">
                                           
                                            <!-- desktop-menu -->
                                            <li class="nav-item"><a class="nav-link weight-SemiBold"
                                                    href="{{ url('tizzy-mail') }}">Tizzy Mail</a></li>
                                            <li class="nav-item"><a class="nav-link weight-SemiBold"
                                                    href="{{ url('cloud-microsoft-365') }}">Microsoft 365</a></li>
                                            <li class="nav-item"><a class="nav-link weight-SemiBold"
                                                    href="{{ url('google-workspace') }}">Google Workspace</a></li>
                                            <li class="nav-item"><a class="nav-link weight-SemiBold"
                                                    href="{{ url('migration') }}">Migration</a></li>
                                            <li class="nav-item dropdown">
                                                <a class="nav-link dropdown-toggle dropdown-item"
                                                    href="javascript:void(0)" id="mainDropdown3"
                                                    data-bs-toggle="dropdown">Managed Services</a>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="migration">Migration</a></li>
                                                    <li><a class="dropdown-item" href="#">Backup &amp;
                                                            Restore</a></li>
                                                </ul>
                                            </li>   
                                          <li class="nav-item cart-btn"><a class="nav-link" href="#"><img src="{{ asset('home/images/icons/cart.png') }}" class="img-fluid" alt=""><span>01</span></a></li> 
                                            <!-- desktop-menu -->
                                            <!-- mobile-menu -->
                                            <li class="nav-item dropdown Workspace-mobile">
                                            <a class="nav-link dropdown-toggle dropdown-item" href="javascript:void(0)" id="mainDropdown4" data-bs-toggle="dropdown">My Account</a>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="#">Webmail Login</a></li>
                                                <li><a class="dropdown-item" href="#">Customer Login</a></li>
                                                <li><a class="dropdown-item" href="#">Reseller Login</a></li>
                                            </ul>
                                            </li>
                                            <li class="nav-item dropdown Workspace-mobile">
                                            <a class="nav-link dropdown-toggle dropdown-item" href="javascript:void(0)" id="mainDropdown5" data-bs-toggle="dropdown">Hello, Govind </a>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="#">Profile</a></li>
                                                <li><a class="dropdown-item" href="#">Active Service List</a></li>
                                                <li><a class="dropdown-item" href="#">Logout</a></li>
                                            </ul>
                                            </li>
                                            <!-- mobile-menu -->
                                            <li class="nav-item Workspace-mobile"><a class="nav-link"
                                                    href="{{ url('about') }}">About Us</a></li>
                                            <li class="nav-item Workspace-mobile"><a class="nav-link"
                                                    href="{{ url('partner') }}">Partner with us</a></li>
                                            <li class="nav-item Workspace-mobile"><a class="nav-link"
                                                    href="{{ url('contact') }}">Contact Us</a></li>
                                            <!-- mobile-menu -->
                                        </ul>
                                        <!-- mobile-menu -->
                                        <div class="Workspace-right Workspace-mobile">
                                            <a href="{{ url('contact') }}" class="tp-btn-black btn-red-bg ">
                                                <span class="tp-btn-black-filter d-inline-flex align-items-center">
                                                    <span class="tp-btn-black-text">Get Start Now</span>
                                                    <span class="tp-btn-black-circle">
                                                        <svg width="10" height="10" viewbox="0 0 10 10"
                                                            fill="none" xmlns="http://www.w3.org/2000/svg">
                                                            <path d="M1 9L9 1M9 1H1M9 1V9" stroke="currentcolor"
                                                                stroke-width="1.5" stroke-linecap="round"
                                                                stroke-linejoin="round"></path>
                                                        </svg>
                                                    </span>
                                                </span>
                                            </a>
                                        </div>
                                        <!-- mobile-menu -->
                                    </div>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <div class="bottom-header">
        <div class="container container-1760">
            <div class="col-lg-12 d-flex align-items-center">
                <div class="Workspace-section d-flex justify-content-lg-evenly justify-content-between">
                    <div class="Workspace-box text-center" data-aos="zoom-in" data-aos-easing="linear"
                        data-aos-duration="800">
                        <a class="workspace-a" href="{{ url('tizzy-mail') }}">
                            <div class="Workspace-icon">
                                <img src="{{ asset('home/images/icons/tizzy-mail.png') }}" alt="">
                            </div>
                        </a>
                    </div>
                    <div class="Workspace-box text-center" data-aos="zoom-in" data-aos-easing="linear"
                        data-aos-duration="1200">
                        <a class="workspace-a" href="{{ url('cloud-microsoft-365') }}">
                            <div class="Workspace-icon">
                                <img src="{{ asset('home/images/icons/microsoft.png') }}" alt="">
                            </div>
                        </a>
                    </div>
                    <div class="Workspace-box text-center" data-aos="zoom-in" data-aos-easing="linear"
                        data-aos-duration="1000">
                        <a class="workspace-a" href="{{ url('google-workspace') }}">
                            <div class="Workspace-icon">
                                <img src="{{ asset('home/images/icons/Google_Workspace.png') }}" alt="">
                            </div>
                        </a>
                    </div>
                </div>
                <div class="Workspace-right Workspace-right-desktop d-flex justify-content-end">
                    <a href="{{ url('contact') }}" class="tp-btn-black btn-red-bg " data-aos="fade-right"
                        data-aos-easing="linear" data-aos-duration="600">
                        <span class="tp-btn-black-filter d-inline-flex align-items-center">
                            <span class="tp-btn-black-text">Get Start Now</span>
                            <span class="tp-btn-black-circle">
                                <svg width="10" height="10" viewbox="0 0 10 10" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M1 9L9 1M9 1H1M9 1V9" stroke="currentcolor" stroke-width="1.5"
                                        stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                            </span>
                        </span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <main id="main-content">
        @yield('Body')
    </main>

    <footer class="footer-section bg-white pt-50" role="contentinfo">
        <div class="container">
            <div class="row gy-4 align-items-start">
                <div class="col-lg-3 col-md-6 col-12">
                    <img src="{{ asset('home/images/logo.png') }}" alt="Tizzy Cloud" class="footer-logo">
                    <div class="footer-social d-flex gap-2" aria-label="Social media links">
                        <a href="#" class="social-icon" aria-label="Facebook"><img
                                src="{{ asset('home/images/icons/facebook.png') }}" alt=""></a>
                        <a href="#" class="social-icon" aria-label="Twitter"><img
                                src="{{ asset('home/images/icons/twitter.png') }}" alt=""></a>
                        <a href="#" class="social-icon" aria-label="Instagram"><img
                                src="{{ asset('home/images/icons/instagram.png') }}" alt=""></a>
                        <a href="#" class="social-icon" aria-label="LinkedIn"><img
                                src="{{ asset('home/images/icons/linkedin.png') }}" alt=""></a>
                        <a href="#" class="social-icon" aria-label="YouTube"><img
                                src="{{ asset('home/images/icons/youtube.png') }}" alt=""></a>
                    </div>
                </div>
                <div class="col-lg-6 col-md-6 col-12">
                    <div class="row">
                        <div class="col-lg-3 col-md-4 col-6">
                            <h6 class="footer-title">Tizzy Info</h6>
                            <ul class="footer-list">
                                <li><a href="{{ url('about') }}">About Tizzy</a></li>
                                <li><a href="{{ url('contact') }}">Contact</a></li>
                            </ul>
                        </div>
                        <div class="col-lg-3 col-md-4 col-6">
                            <h6 class="footer-title">Solutions</h6>
                            <ul class="footer-list">
                                <li><a href="{{ url('tizzy-mail') }}">Tizzy Mail</a></li>
                                <li><a href="{{ url('google-workspace') }}">Google Workspace</a></li>
                                <li><a href="{{ url('cloud-microsoft-365') }}">Microsoft 365</a></li>
                            </ul>
                        </div>
                        <div class="col-lg-6 col-md-4 col-6">
                            <h6 class="footer-title">Support</h6>
                            <ul class="footer-list">
                                <li><a href="{{ url('migration') }}">Migration</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3">
                    <p class="footer-desc">Tizzy Cloud is a team of enthusiastic techies specialized in Cloud Computing
                        services. Since inception we are focused on educating rather just selling services.</p>
                </div>

            </div>
        </div>
        <div class="footer-bottom mt-40 py-3">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <p class="mb-0 small-text">© 2026 Tizzy Cloud. All rights reserved.</p>
                    </div>
                    <div class="col-md-4 text-md-end" data-aos="zoom-in" data-aos-easing="linear"
                        data-aos-duration="600">
                        <img src="{{ asset('home/images/iso.png') }}" alt="Certifications" class="certifications">
                    </div>
                </div>
            </div>
        </div>
    </footer>
    <!-- inquiry Btn -->
    <button id="inquiryBtn" class="inquiry-sticky-btn" type="button" aria-label="Open inquiry form"
        aria-controls="inquiryPanel" aria-expanded="false"><span class="inquiry-icon" aria-hidden="true">✉</span> Inquiry Now</button>
    <div id="inquiryPanel" class="inquiry-panel inquiry-form">
        <div class="inquiry-header">
            <h4 class="div-text color-0355ac mb-0">Connect with us</h4>
            <button id="closeInquiry" class="close-btn" type="button" aria-label="Close inquiry form">&times;</button>
        </div>

        <div class="inquiry-body">
            <form class="row">
                <div class="col-md-12 form-group">
                    <input type="text" class="form-control" placeholder="Enter Name *">
                </div>

                <div class="col-md-12 form-group">
                    <input type="email" class="form-control" placeholder="Enter E-mail Id *">
                </div>

                <div class="col-12 form-group">
                    <input type="text" class="form-control" placeholder="Enter Phone Number *">
                </div>

                <div class="col-12 form-group">
                    <textarea class="form-control" placeholder="Message / Requirement *"></textarea>
                </div>

                <div class="col-12 mt-20 form-group mb-0">
                    <button class="tp-btn-black" type="submit">
                        <span class="tp-btn-black-filter d-inline-flex align-items-center">
                            <span class="tp-btn-black-text">Submit</span>
                            <span class="tp-btn-black-circle">
                                <svg width="10" height="10" viewbox="0 0 10 10" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M1 9L9 1M9 1H1M9 1V9" stroke="currentcolor" stroke-width="1.5"
                                        stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                            </span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <a href="javascript:void(0)" id="return-to-top" aria-label="Back to top"><img
            src="{{ asset('home/images/icons/page-up.png') }}" alt=""></a>


    <script src="{{ asset('home/js/jquery.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('home/js/swiper.min.js') }}"></script>
    <script src="{{ asset('home/js/aos.js') }}"></script>
    <script src="{{ asset('home/js/custom.js') }}"></script>
    @yield('jsLinks')

    @yield('myJS')

    <script>
        document.addEventListener("DOMContentLoaded", function() {

            const inquiryBtns = document.querySelectorAll(".inquiry-sticky-btn, .open-inquiry-btn");
            const inquiryPanels = document.querySelectorAll(".inquiry-panel");
            const closeBtns = document.querySelectorAll(".close-btn");

            // Open panel
            inquiryBtns.forEach(btn => {
                btn.addEventListener("click", function() {
                    inquiryPanels.forEach(panel => panel.classList.add("active"));
                    inquiryBtns.forEach(b => b.setAttribute("aria-expanded", "true"));
                });
            });

            // Close panel with close icon
            closeBtns.forEach(btn => {
                btn.addEventListener("click", function() {
                    this.closest(".inquiry-panel").classList.remove("active");
                    inquiryBtns.forEach(b => b.setAttribute("aria-expanded", "false"));
                });
            });

            // Optional: close when clicking outside
            document.addEventListener("click", function(e) {
                inquiryPanels.forEach(panel => {
                    if (
                        panel.classList.contains("active") &&
                        !panel.contains(e.target) &&
                        !e.target.classList.contains("inquiry-sticky-btn") &&
                        !e.target.classList.contains("open-inquiry-btn")
                    ) {
                        panel.classList.remove("active");
                        inquiryBtns.forEach(b => b.setAttribute("aria-expanded", "false"));
                    }
                });
            });

        });
    </script>
</body>

</html>
