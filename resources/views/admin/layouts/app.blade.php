<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin Panel') | Tizzy Group</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Font: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Animation Libraries -->
    <link rel="stylesheet" href="{{ asset('public/home/css/animate.css') }}">
    <link rel="stylesheet" href="{{ asset('public/home/css/aos.css') }}">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="{{ asset('public/partner/assets/css/partneradmin.css') }}">
    {{-- responsive css --}}
    <link rel="stylesheet" href="{{ asset('public/partner/assets/css/responsive.css') }}">
    {{-- alertify cdn --}}
    {{-- <link rel="stylesheet" href="{{ asset('public/home/css/alertify.min.css') }}"> --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/alertify.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/themes/default.min.css"/>
    @yield('css')
</head>
<body>

    <!-- Sidebar -->
    <div id="sidebar" data-aos="fade-right" data-aos-duration="800">
        <div class="sidebar-brand">
            <img src="{{ asset('public/home/images/logo.png') }}" alt="Tizzy Group" >
            <button class="collapse-btn"><i class="fas fa-chevron-left"></i></button>
        </div>

        <div class="sidebar-menu" data-aos="fade-right" data-aos-duration="800" data-aos-delay="100">
            <ul class="p-0">
                <li class="nav-item">
                    <a href="{{ url('partner-admin/dashboard') }}" class="nav-link">
                        <img src="{{ asset('public/home/images/partner/icon/dashboard.png') }}" class="dashboardicon" alt="Tizzy Group" > <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ url('partner-admin/customers') }}" class="nav-link {{ request()->is('*customers*') ? 'active' : '' }} dropdown-toggle">
                        <img src="{{ asset('public/home/images/partner/icon/customer.png') }}" class="dashboardicon" alt="Tizzy Group" > <span>Customers</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('partner-admin.b2b.new_plan') }}" class="nav-link {{ request()->routeIs('partner-admin.b2b.*') ? 'active' : '' }}">
                        <img src="{{ asset('public/home/images/partner/icon/subcription.png') }}" class="dashboardicon" alt=""> <span>B2B new order</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('partner-admin.services.index') }}" class="nav-link {{ request()->is('*services*') ? 'active' : '' }}">
                        <img src="{{ asset('public/home/images/partner/icon/service.png') }}" class="dashboardicon" alt="Tizzy Group" > <span>Services</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link dropdown-toggle">
                        <img src="{{ asset('public/home/images/partner/icon/finance.png') }}" class="dashboardicon" alt="Tizzy Group" > <span>Finance</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link dropdown-toggle">
                        <img src="{{ asset('public/home/images/partner/icon/subcription.png') }}" class="dashboardicon" alt="Tizzy Group" > <span>Subscriptions</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link dropdown-toggle">
                        <img src="{{ asset('public/home/images/partner/icon/reports.png') }}" class="dashboardicon" alt="Tizzy Group" > <span>Reports</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link dropdown-toggle">
                        <img src="{{ asset('public/home/images/partner/icon/support.png') }}" class="dashboardicon" alt="Tizzy Group" > <span>Support</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link dropdown-toggle">
                        <img src="{{ asset('public/home/images/partner/icon/setting.png') }}" class="dashboardicon" alt="Tizzy Group" > <span>Settings</span>
                    </a>
                </li>
                {{-- <li class="nav-item">
                    <a href="{{ url('partner/logout') }}" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
                    </a>
                </li> --}}
            </ul>
        </div>

        <div class="sidebar-footer">
            <div class="balance-label">Your Credit Balance</div>
            <div class="balance-amount">
                <span>₹ 36,458.00</span>
                <a href="#" class="add-funds-btn">Add funds</a>
            </div>
        </div>
    </div>

    <!-- Top Navbar -->
    <nav class="top-navbar">
        <div class="search-container">
            <i class="fas fa-search"></i>
            <input type="text" class="search-input" placeholder="Quick Search...">
        </div>

        <div class="top-nav-right">
            <div class="nav-icon">
                <i class="far fa-bell"></i>
                <span class="badge-count">5</span>
            </div>
            <div class="nav-icon">
                <i class="fas fa-shopping-bag"></i>
            </div>
            <div class="nav-icon">
                <i class="fas fa-cog"></i>
            </div>
            <div class="divider"></div>
            <div class="user-info-wrapper">
                <div class="user-info" id="userDropdownTrigger">
                    <div class="avatar-circle">{{ strtoupper(substr(session('partnerName'), 0, 1)) }}</div>
                    <div class="user-details">
                        <span class="username"> {{ session('partnerName') }} <i class="fas fa-chevron-down ms-1" style="font-size: 8px;"></i></span>
                        <span class="userrole">Admin</span>
                    </div>
                </div>
                <div class="user-dropdown-menu" id="userDropdownMenu">
                    <div class="px-3 py-2 mb-1 border-bottom d-md-none">
                        <div class="fw-bold text-dark">{{ session('partnerName') }}</div>
                        <div class="small text-muted">Admin</div>
                    </div>
                    <a href="#" class="dropdown-item">
                        <i class="far fa-user"></i> My Profile
                    </a>
                    <a href="#" class="dropdown-item">
                        <i class="fas fa-cog"></i> Account Settings
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ url('partner/logout') }}" class="dropdown-item text-danger">
                        <i class="fas fa-sign-out-alt text-danger"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-wrapper">
        @yield('content')
    </div>

    <!-- Scripts -->
    <script src="{{ asset('public/home/js/jquery.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('public/home/js/aos.js') }}"></script>
    {{-- alertify cdn --}}
    <script src="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/alertify.min.js"></script>
    <script>
        AOS.init({
            duration: 800,
            once: true,
            easing: 'ease-in-out'
        });

        // Sidebar Collapse Toggle
        $('.collapse-btn').on('click', function() {
            $('body').toggleClass('sidebar-collapsed');
            
            // Toggle Icon
            const icon = $(this).find('i');
            if ($('body').hasClass('sidebar-collapsed')) {
                icon.removeClass('fa-chevron-left').addClass('fa-chevron-right');
            } else {
                icon.removeClass('fa-chevron-right').addClass('fa-chevron-left');
            }
        });

        // User Dropdown Toggle
        $('#userDropdownTrigger').on('click', function(e) {
            e.stopPropagation();
            $('#userDropdownMenu').toggleClass('show');
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('.user-info-wrapper').length) {
                $('#userDropdownMenu').removeClass('show');
            }
        });

        @if(session('partnerId') || session('partner_id'))
        (function () {
            var sessionCheckUrl = @json(url('partner/session-check'));
            var loginUrl = @json(url('partner/login'));

            function enforceSingleDeviceSession() {
                $.ajax({
                    url: sessionCheckUrl,
                    type: 'GET',
                    dataType: 'json',
                    cache: false
                }).fail(function (xhr) {
                    if (xhr.status === 401) {
                        alertify.error('You have been logged out because your account was logged in from another device.');
                        window.location.href = loginUrl;
                    }
                });
            }

            enforceSingleDeviceSession();
            setInterval(enforceSingleDeviceSession, 15000);
        })();
        @endif

        //  formSubmit
        function formSubmit(url, redirect, id = "form") {
        $("#" + id).on("submit", function(e) {
            e.preventDefault();
            $.ajax({
                url: url,
                type: "POST",
                data: new FormData(this),
                contentType: false,
                cache: false,
                processData: false,
                dataType: "json",
                beforeSend: function(data) {
                    $("#spinner").show();
                    $("#submit").attr("disabled", true);
                },
                success: function(data) {
                    $("#spinner").hide();
                    $("#submit").attr("disabled", false);
                    if (data.error) {
                        $.each(data, function(key, value) {
                            if (value) {
                                $("#error-" + key).html(value);
                                $("#" + key).addClass("border-danger");
                            } else {
                                $("#error-" + key).html(" ");
                                $("#" + key).removeClass("border-danger");
                            }
                        });
                    }
                    if (data.success) {
                        $("#" + id + " .form-control").removeClass("border-danger");
                        $("#" + id + " .text-danger").html(" ");
                        alertify.success(data.success);
                        if (redirect != '') {
                            setTimeout(function() {
                                window.location.href = redirect;
                            }, 1000);
                        }
                    }
                },
                error: function(error) {
                    $("#spinner").hide();
                    $("#submit").attr("disabled", false);
                    errorMessage(error.status);
                }
            });
        });
    }
    </script>
    @yield('js')
</body>
</html>
