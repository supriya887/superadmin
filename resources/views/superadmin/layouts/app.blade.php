<!doctype html>
<html lang="en" data-skin="mono" data-bs-theme="light" data-menu-color="light" data-topbar-color="dark" data-layout-width="fluid" dir="ltr" data-sidenav-size="default" class="">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>{{ $title ?? 'Super Admin' }} | TIZZY Group</title>

    <link rel="icon" sizes="32x32" href="{{ asset('home/images/favicon/favicon-32.png') }}">
    <link rel="icon" sizes="16x16" href="{{ asset('home/images/favicon/favicon-16.png') }}">
    <script src="{{ asset('superadmin/js/config.js') }}"></script>
    <link href="{{ asset('superadmin/css/vendors.min.css') }}" rel="stylesheet" type="text/css" />
    <link id="app-style" href="{{ asset('superadmin/css/app.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="https://fonts.googleapis.com/css2?family=Baloo+Bhai+2:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="{{ asset('superadmin/css/custom.css') }}" rel="stylesheet" type="text/css" />

    <!-- Alertify -->
    <link href="https://cdn.jsdelivr.net/npm/alertifyjs@1.14.0/build/css/alertify.min.css" rel="stylesheet" type="text/css" />
    <link href="https://cdn.jsdelivr.net/npm/alertifyjs@1.14.0/build/css/themes/default.min.css" rel="stylesheet" type="text/css" />
    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" type="text/css" />
    <!-- DataTables -->
    <!-- Datatables css -->
    <link href="{{ asset('superadmin/plugins/datatables/responsive.bootstrap5.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('superadmin/plugins/datatables/buttons.bootstrap5.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.6/dist/sweetalert2.min.css" rel="stylesheet">

    @stack('styles')
</head>

<body>
<div class="wrapper">

    <header class="app-topbar">
        <div class="container topbar-menu">
            <div class="d-flex align-items-center gap-2">

                <div class="logo-topbar">
                    <a href="{{ route('superadmin.dashboard') }}" class="logo-light">
                        <span class="logo-lg"><img src="{{ asset('home/images/logo_tagline.png') }}" alt="logo" /></span>
                        <span class="logo-sm"><img src="{{ asset('home/images/logo_tagline.png') }}" alt="small logo" /></span>
                    </a>
                    <a href="{{ route('superadmin.dashboard') }}" class="logo-dark">
                        <span class="logo-lg"><img src="{{ asset('home/images/logo_tagline.png') }}" alt="dark logo" /></span>
                        <span class="logo-sm"><img src="{{ asset('home/images/logo_tagline.png') }}" alt="small logo" /></span>
                    </a>
                </div>

                <button class="sidenav-toggle-button btn btn-primary btn-icon mt-2" type="button"
                    aria-label="Toggle sidebar navigation">
                    <i class="ti ti-menu-4" aria-hidden="true"></i>
                </button>
                <button class="topnav-toggle-button px-2" type="button" data-bs-toggle="collapse"
                    data-bs-target="#topnav-menu" aria-label="Toggle top navigation menu"
                    aria-controls="topnav-menu" aria-expanded="false">
                    <i class="ti ti-menu-4" aria-hidden="true"></i>
                </button>
                {{-- 
                <div id="search-box-rounded" class="app-search d-none d-xl-flex">
                    <input type="search" class="form-control rounded-pill topbar-search" name="search" placeholder="Quick Search..." />
                    <i class="ti ti-search app-search-icon text-muted"></i>
                </div>

                <div id="megamenu-columns" class="topbar-item d-none d-md-flex">
                    <div class="dropdown">
                        <button class="topbar-link btn fw-medium btn-link dropdown-toggle drop-arrow-none px-2"
                                data-bs-toggle="dropdown" type="button">
                            Mega Menu <i class="ti ti-chevron-down ms-1"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-xxl p-0">
                            <div class="h-100" style="max-height:380px" data-simplebar="">
                                <div class="row g-0">
                                    <div class="col-md-4">
                                        <div class="p-2">
                                            <h5 class="mb-1 fw-semibold fs-sm dropdown-header">Dashboard &amp; Analytics</h5>
                                            <ul class="list-unstyled megamenu-list">
                                                <li><a href="javascript:void(0);" class="dropdown-item"><i class="ti ti-chevron-right align-middle me-1 text-muted"></i>Sales Dashboard</a></li>
                                                <li><a href="javascript:void(0);" class="dropdown-item"><i class="ti ti-chevron-right align-middle me-1 text-muted"></i>Marketing Dashboard</a></li>
                                                <li><a href="javascript:void(0);" class="dropdown-item"><i class="ti ti-chevron-right align-middle me-1 text-muted"></i>Finance Overview</a></li>
                                                <li><a href="javascript:void(0);" class="dropdown-item"><i class="ti ti-chevron-right align-middle me-1 text-muted"></i>User Analytics</a></li>
                                                <li><a href="javascript:void(0);" class="dropdown-item"><i class="ti ti-chevron-right align-middle me-1 text-muted"></i>Traffic Insights</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-2">
                                            <h5 class="mb-1 fw-semibold fs-sm dropdown-header">Project Management</h5>
                                            <ul class="list-unstyled megamenu-list">
                                                <li><a href="javascript:void(0);" class="dropdown-item"><i class="ti ti-minus align-middle me-1 text-muted"></i>Kanban Workflow</a></li>
                                                <li><a href="javascript:void(0);" class="dropdown-item"><i class="ti ti-minus align-middle me-1 text-muted"></i>Project Timeline</a></li>
                                                <li><a href="javascript:void(0);" class="dropdown-item"><i class="ti ti-minus align-middle me-1 text-muted"></i>Task Management</a></li>
                                                <li><a href="javascript:void(0);" class="dropdown-item"><i class="ti ti-minus align-middle me-1 text-muted"></i>Team Members</a></li>
                                                <li><a href="javascript:void(0);" class="dropdown-item"><i class="ti ti-minus align-middle me-1 text-muted"></i>Assignments</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-2 bg-light bg-opacity-50">
                                            <h5 class="mb-1 fw-semibold fs-sm dropdown-header">User Management</h5>
                                            <ul class="list-unstyled megamenu-list">
                                                <li><a href="javascript:void(0);" class="dropdown-item"><i class="ti ti-chevron-right align-middle me-1 text-muted"></i>User Profiles</a></li>
                                                <li><a href="javascript:void(0);" class="dropdown-item"><i class="ti ti-chevron-right align-middle me-1 text-muted"></i>Access Control</a></li>
                                                <li><a href="javascript:void(0);" class="dropdown-item"><i class="ti ti-chevron-right align-middle me-1 text-muted"></i>Security Settings</a></li>
                                                <li><a href="javascript:void(0);" class="dropdown-item"><i class="ti ti-chevron-right align-middle me-1 text-muted"></i>User Groups</a></li>
                                                <li><a href="javascript:void(0);" class="dropdown-item"><i class="ti ti-chevron-right align-middle me-1 text-muted"></i>Authentication</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> --}}

            </div>

            <div class="d-flex align-items-center gap-2">

                {{-- <div id="theme-dropdown" class="topbar-item d-none d-sm-flex">
                    <div class="dropdown">
                        <button class="topbar-link" data-bs-toggle="dropdown" type="button">
                            <i class="ti ti-sun topbar-link-icon d-none"      id="theme-icon-light"></i>
                            <i class="ti ti-moon topbar-link-icon d-none"     id="theme-icon-dark"></i>
                            <i class="ti ti-sun-moon topbar-link-icon d-none" id="theme-icon-system"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end" data-thememode="dropdown">
                            <label class="dropdown-item cursor-pointer"><input class="form-check-input" type="radio" name="data-bs-theme" value="light" style="display:none" /><i class="ti ti-sun align-middle me-1 fs-16"></i><span class="align-middle">Light</span></label>
                            <label class="dropdown-item cursor-pointer"><input class="form-check-input" type="radio" name="data-bs-theme" value="dark" style="display:none" /><i class="ti ti-moon align-middle me-1 fs-16"></i><span class="align-middle">Dark</span></label>
                            <label class="dropdown-item cursor-pointer"><input class="form-check-input" type="radio" name="data-bs-theme" value="system" style="display:none" /><i class="ti ti-sun-moon align-middle me-1 fs-16"></i><span class="align-middle">System</span></label>
                        </div>
                    </div>
                </div>

                <div id="apps-dropdown-grid" class="topbar-item d-none d-xl-flex">
                    <div class="dropdown">
                        <button class="topbar-link dropdown-toggle drop-arrow-none" data-bs-toggle="dropdown" type="button" data-bs-auto-close="outside">
                            <i class="ti ti-apps topbar-link-icon"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-lg p-2 dropdown-menu-end">
                            <div class="row align-items-center g-1">
                                <div class="col-4"><a href="javascript:void(0);" class="dropdown-item border border-dashed rounded text-center py-2"><span class="avatar-sm d-block mx-auto mb-1"><span class="avatar-title text-bg-light rounded-circle"><img src="{{ asset('superadmin/images/logos/google.svg') }}" height="18" alt="" /></span></span><span class="align-middle fw-medium">Google</span></a></div>
                                <div class="col-4"><a href="javascript:void(0);" class="dropdown-item border border-dashed rounded text-center py-2"><span class="avatar-sm d-block mx-auto mb-1"><span class="avatar-title text-bg-light rounded-circle"><img src="{{ asset('superadmin/images/logos/figma.svg') }}" height="18" alt="" /></span></span><span class="align-middle fw-medium">Figma</span></a></div>
                                <div class="col-4"><a href="javascript:void(0);" class="dropdown-item border border-dashed rounded text-center py-2"><span class="avatar-sm d-block mx-auto mb-1"><span class="avatar-title text-bg-light rounded-circle"><img src="{{ asset('superadmin/images/logos/slack.svg') }}" height="18" alt="" /></span></span><span class="align-middle fw-medium">Slack</span></a></div>
                                <div class="col-4"><a href="javascript:void(0);" class="dropdown-item border border-dashed rounded text-center py-2"><span class="avatar-sm d-block mx-auto mb-1"><span class="avatar-title text-bg-light rounded-circle"><img src="{{ asset('superadmin/images/logos/dropbox.svg') }}" height="18" alt="" /></span></span><span class="align-middle fw-medium">Dropbox</span></a></div>
                                <div class="col-4 text-center"><a href="javascript:void(0);" class="btn btn-sm rounded-circle btn-icon btn-danger"><i class="ti ti-circle-dashed-plus fs-18"></i></a></div>
                                <div class="col-4"><a href="javascript:void(0);" class="dropdown-item border border-dashed rounded text-center py-2"><span class="avatar-sm d-block mx-auto mb-1"><span class="avatar-title bg-primary-subtle text-primary rounded-circle"><i class="ti ti-calendar fs-18"></i></span></span><span class="align-middle fw-medium">Calendar</span></a></div>
                                <div class="col-4"><a href="javascript:void(0);" class="dropdown-item border border-dashed rounded text-center py-2"><span class="avatar-sm d-block mx-auto mb-1"><span class="avatar-title bg-primary-subtle text-primary rounded-circle"><i class="ti ti-message-circle fs-18"></i></span></span><span class="align-middle fw-medium">Chat</span></a></div>
                                <div class="col-4"><a href="javascript:void(0);" class="dropdown-item border border-dashed rounded text-center py-2"><span class="avatar-sm d-block mx-auto mb-1"><span class="avatar-title bg-primary-subtle text-primary rounded-circle"><i class="ti ti-folder fs-18"></i></span></span><span class="align-middle fw-medium">Files</span></a></div>
                                <div class="col-4"><a href="javascript:void(0);" class="dropdown-item border border-dashed rounded text-center py-2"><span class="avatar-sm d-block mx-auto mb-1"><span class="avatar-title bg-primary-subtle text-primary rounded-circle"><i class="ti ti-users fs-18"></i></span></span><span class="align-middle fw-medium">Team</span></a></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="notification-dropdown-people" class="topbar-item">
                    <div class="dropdown">
                        <button class="topbar-link dropdown-toggle drop-arrow-none" data-bs-toggle="dropdown" type="button" data-bs-auto-close="outside">
                            <i class="ti ti-bell topbar-link-icon animate-ring"></i>
                            <span class="badge text-bg-danger badge-circle topbar-badge">5</span>
                        </button>
                        <div class="dropdown-menu p-0 dropdown-menu-end dropdown-menu-lg">
                            <div class="px-3 py-2 border-bottom">
                                <div class="row align-items-center">
                                    <div class="col"><h6 class="m-0 fs-md fw-semibold">Notifications</h6></div>
                                    <div class="col text-end"><a href="#!" class="badge badge-soft-success badge-label py-1">07 Notifications</a></div>
                                </div>
                            </div>
                            <div style="max-height:300px" data-simplebar="">
                                <div class="dropdown-item notification-item py-2 text-wrap" id="msg-1">
                                    <span class="d-flex align-items-center gap-3">
                                        <span class="flex-shrink-0 position-relative"><img src="{{ asset('superadmin/images/users/user-4.jpg') }}" class="avatar-md rounded-circle" alt="" /><span class="position-absolute rounded-pill bg-success notification-badge"><i class="ti ti-bell align-middle"></i></span></span>
                                        <span class="flex-grow-1 text-muted"><span class="fw-medium text-body">Emily Johnson</span> commented on a task in <span class="fw-medium text-body">Design Sprint</span><br /><span class="fs-xs">12 minutes ago</span></span>
                                        <button type="button" class="flex-shrink-0 text-muted btn btn-link p-0 position-absolute end-0 me-2 d-none noti-close-btn" data-dismissible="#msg-1"><i class="ti ti-square-rounded-x fs-xxl"></i></button>
                                    </span>
                                </div>
                                <div class="dropdown-item notification-item py-2 text-wrap" id="msg-2">
                                    <span class="d-flex align-items-center gap-3">
                                        <span class="flex-shrink-0 position-relative"><span class="avatar-md rounded-circle bg-light d-flex align-items-center justify-content-center"><i class="ti ti-database fs-4"></i></span><span class="position-absolute rounded-pill bg-danger notification-badge"><i class="ti ti-alert-circle align-middle"></i></span></span>
                                        <span class="flex-grow-1 text-muted"><span class="fw-medium text-body">Server #3</span> CPU usage exceeded 90%<br /><span class="fs-xs">Just now</span></span>
                                        <button type="button" class="flex-shrink-0 text-muted btn btn-link p-0 position-absolute end-0 me-2 d-none noti-close-btn" data-dismissible="#msg-2"><i class="ti ti-square-rounded-x fs-xxl"></i></button>
                                    </span>
                                </div>
                                <div class="dropdown-item notification-item py-2 text-wrap" id="msg-3">
                                    <span class="d-flex align-items-center gap-3">
                                        <span class="flex-shrink-0 position-relative"><span class="avatar-md rounded-circle bg-light d-flex align-items-center justify-content-center"><i class="ti ti-rocket fs-4"></i></span><span class="position-absolute rounded-pill bg-success notification-badge"><i class="ti ti-check align-middle"></i></span></span>
                                        <span class="flex-grow-1 text-muted"><span class="fw-medium text-body">Production Server</span> deployment completed successfully<br /><span class="fs-xs">30 minutes ago</span></span>
                                        <button type="button" class="flex-shrink-0 text-muted btn btn-link p-0 position-absolute end-0 me-2 d-none noti-close-btn" data-dismissible="#msg-3"><i class="ti ti-square-rounded-x fs-xxl"></i></button>
                                    </span>
                                </div>
                            </div>
                            <a href="javascript:void(0);" class="dropdown-item text-center text-reset text-decoration-underline link-offset-2 fw-bold notify-item border-top border-light py-2">Read All Messages</a>
                        </div>
                    </div>
                </div>

                <div id="fullscreen-toggler" class="topbar-item d-none d-md-flex">
                    <button class="topbar-link" type="button" data-toggle="fullscreen">
                        <i class="ti ti-maximize topbar-link-icon"></i>
                        <i class="ti ti-minimize topbar-link-icon d-none"></i>
                    </button>
                </div>

                <div id="monochrome-toggler" class="topbar-item d-none d-xl-flex">
                    <button id="monochrome-mode" class="topbar-link" type="button" data-toggle="monochrome">
                        <i class="ti ti-palette topbar-link-icon"></i>
                    </button>
                </div>

                <div class="topbar-item d-none d-sm-flex">
                    <button class="topbar-link btn-theme-setting"
                            data-bs-toggle="offcanvas" data-bs-target="#theme-settings-offcanvas" type="button">
                        <i class="ti ti-settings topbar-link-icon"></i>
                    </button>
                </div>

                <div id="language-selector-rounded" class="topbar-item">
                    <div class="dropdown">
                        <button class="topbar-link fw-bold" data-bs-toggle="dropdown" type="button">
                            <img src="{{ asset('superadmin/images/flags/us.svg') }}" alt="EN" class="rounded-circle me-2" height="18" id="selected-language-image" />
                            <span id="selected-language-code">EN</span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a href="javascript:void(0);" class="dropdown-item" data-translator-lang="en"><img src="{{ asset('superadmin/images/flags/us.svg') }}" class="me-1 rounded-circle" height="18" alt="" /><span class="align-middle">English</span></a>
                            <a href="javascript:void(0);" class="dropdown-item" data-translator-lang="de"><img src="{{ asset('superadmin/images/flags/de.svg') }}" class="me-1 rounded-circle" height="18" alt="" /><span class="align-middle">Deutsch</span></a>
                            <a href="javascript:void(0);" class="dropdown-item" data-translator-lang="it"><img src="{{ asset('superadmin/images/flags/it.svg') }}" class="me-1 rounded-circle" height="18" alt="" /><span class="align-middle">Italiano</span></a>
                            <a href="javascript:void(0);" class="dropdown-item" data-translator-lang="es"><img src="{{ asset('superadmin/images/flags/es.svg') }}" class="me-1 rounded-circle" height="18" alt="" /><span class="align-middle">Español</span></a>
                            <a href="javascript:void(0);" class="dropdown-item" data-translator-lang="hi"><img src="{{ asset('superadmin/images/flags/in.svg') }}" class="me-1 rounded-circle" height="18" alt="" /><span class="align-middle">हिन्दी</span></a>
                            {{-- <a href="javascript:void(0);" class="dropdown-item" data-translator-lang="zh"><img src="{{ asset('superadmin/images/flags/cn.svg') }}" class="me-1 rounded-circle" height="18" alt="" /><span class="align-middle">中文</span></a> --}}
                        </div>
                    </div>
                </div>

                

            </div>
        </div>
    </header>


    @include('superadmin.partials.sidebar')


    <main class="content-page fade-in" id="main-content">
        <div class="container-fluid">

            @yield('content')

        </div>

        <footer class="footer mt-2" role="contentinfo">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-12 text-center">
                        <script>document.write(new Date().getFullYear())</script>
                        &copy; TIZZY Group
                    </div>
                    {{-- <div class="col-md-6">
                        <div class="d-none d-md-flex justify-content-end gap-3">
                            <a href="javascript:void(0);" class="link-reset">About</a>
                            <a href="javascript:void(0);" class="link-reset">Support</a>
                            <a href="{{ route('superadmin.logout') }}" class="link-reset text-danger">Logout</a>
                        </div>
                    </div> --}}
                </div>
            </div>
        </footer>
    </main>



    {{-- Theme Customizer Removed To Fix Luxe Theme --}}

</div>{{-- End Wrapper --}}


<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script>
    // Keep AJAX POSTs in sync with the page CSRF meta (avoids 419 on DataTables / forms).
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
</script>
@if(session('userId'))
<script>
    // Support presence heartbeat: keeps "Active Now" while a visible panel tab is open.
    (function () {
        var url = @json(route('superadmin.support.agents.heartbeat'));
        var token = document.querySelector('meta[name="csrf-token"]');
        function beat() {
            if (document.hidden) return;
            fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            }).catch(function () {});
        }
        setInterval(beat, 120000);
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) beat();
        });
    })();
</script>
@endif
<script>window.SUPERADMIN_I18N_PATH = @json(asset('superadmin/assets/data/translations/'));</script>
<script src="{{ asset('superadmin/js/vendors.min.js') }}"></script>
<script src="{{ asset('superadmin/js/app.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/alertify.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- Datatables js -->
<script src="{{ asset('superadmin/plugins/datatables/dataTables.min.js') }}"></script>
<script src="{{ asset('superadmin/plugins/datatables/dataTables.bootstrap5.min.js') }}"></script>
<script src="{{ asset('superadmin/plugins/datatables/dataTables.responsive.min.js') }}"></script>
<script src="{{ asset('superadmin/plugins/datatables/responsive.bootstrap5.min.js') }}"></script>

<script src="{{ asset('superadmin/plugins/datatables/dataTables.buttons.min.js') }}"></script>
<script src="{{ asset('superadmin/plugins/datatables/buttons.bootstrap5.min.js') }}"></script>
<script src="{{ asset('superadmin/plugins/datatables/jszip.min.js') }}"></script>
<script src="{{ asset('superadmin/plugins/datatables/pdfmake.min.js') }}"></script>
<script src="{{ asset('superadmin/plugins/datatables/vfs_fonts.js') }}"></script>
<script src="{{ asset('superadmin/plugins/datatables/buttons.html5.min.js') }}"></script>
<script src="{{ asset('superadmin/plugins/datatables/buttons.print.min.js') }}"></script>

<!-- Page js -->
<script src="{{ asset('superadmin/js/pages/datatables-export-data.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.6/dist/sweetalert2.all.min.js"></script>
<script>
    (function () {
        if (typeof alertify !== 'undefined') {
            alertify.set('notifier', 'delay', 12);
            alertify.set('notifier', 'position', 'top-right');
            alertify.set('notifier', 'closeButton', true);
        }
        var successMsg = @json(session('success'));
        var errorMsg = @json(session('error'));
        var infoMsg = @json(session('info'));
        var warningMsg = @json(session('warning'));
        if (successMsg) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: successMsg,
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    showCloseButton: true,
                    timer: 12000,
                    timerProgressBar: true
                });
            } else {
                alertify.success(successMsg, 12);
            }
        }
        if (errorMsg) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: errorMsg,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#1e58b4'
                });
            } else {
                alertify.error(errorMsg, 30);
            }
        }
        if (infoMsg) {
            alertify.message(infoMsg, 12);
        }
        if (warningMsg) {
            if (typeof alertify.notify === 'function') {
                alertify.notify(warningMsg, 'warning', 12);
            } else {
                alertify.message(warningMsg, 12);
            }
        }
    })();
</script>

<script>
    function select2(id, title) { 
        $('#'+id).select2({ placeholder: title, allowClear: true });
     }
    function select2NoSearch(id, title) { $('#'+id).select2({ minimumResultsForSearch: Infinity, placeholder: title, allowClear: true }); }
    
    // Global deleteRowType function using SweetAlert2
    function deleteRowType(type, url, successMessage) {
        $(document).on('click', '.btn-soft-danger', function() {
            var id = $(this).attr('id');
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: url,
                        type: 'POST',
                        data: {
                            _token: $('meta[name="csrf-token"]').attr('content'),
                            id: id
                        },
                        success: function(response) {
                            if (response.success) {
                                Swal.fire(
                                    'Deleted!',
                                    successMessage,
                                    'success'
                                );
                                // Reload the appropriate DataTable
                                if ($('#customerUsersTable').length) {
                                    $('#customerUsersTable').DataTable().ajax.reload();
                                } else if ($('#partnerCustomersTable').length) {
                                    $('#partnerCustomersTable').DataTable().ajax.reload();
                                } else if ($('#datatable').length) {
                                    $('#datatable').DataTable().ajax.reload();
                                }
                                else {
                                    // Fallback if other tables exist
                                    $('.dataTable').DataTable().ajax.reload();
                                }
                            } else {
                                Swal.fire(
                                    'Error!',
                                    response.error || 'Something went wrong!',
                                    'error'
                                );
                            }
                        },
                        error: function(xhr) {
                            Swal.fire(
                                'Error!',
                                'Error deleting ' + type + ': ' + xhr.statusText,
                                'error'
                            );
                        }
                    });
                }
            });
        });
    }
    function datepicker(id) {
        flatpickr('#' + id, {
            // mode: "range",
            dateFormat: "d-m-Y"
        });
    }

    function futuredatedisable(id) {
        flatpickr('#' + id, {
            dateFormat: "d-m-Y",
            maxDate: "today"
        });
    }

    function MYdatepicker(id) {
        flatpickr('#' + id, {
            // mode: "range",
            dateFormat: "M-Y"
        });
    }

    function timepicker(id) {
        flatpickr('#' + id, {
            enableTime: true,
            noCalendar: true,
            dateFormat: "H:i",
            // defaultDate: "02:00"
        });
    }

    function Ydatepicker(id) {
        $('#' + id).datepicker({
            showOtherMonths: true,
            dateFormat: "yy",
            selectOtherMonths: true
        });
    }

    function timedatepicker(id) {
        flatpickr('#' + id, {
            enableTime: true,
            dateFormat: "d-m-Y H:i:s",
        });

    }
    
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

<script>
    // Global Bootstrap modal stacking fix for pages where parent containers
    // create stacking contexts (e.g. animated/faded wrappers).
    // Moving modals under <body> prevents "modal behind backdrop" behaviour.
    document.addEventListener('DOMContentLoaded', function () {
        function moveModals(root) {
            if (!root) return;
            var modalEls = root.matches && root.matches('.modal') ? [root] : (root.querySelectorAll ? root.querySelectorAll('.modal') : []);
            modalEls.forEach(function (modalEl) {
                try {
                    document.body.appendChild(modalEl);
                } catch (e) {
                    // ignore
                }
            });
        }

        // Move existing modals.
        moveModals(document);

        // Also handle dynamically injected modals.
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                m.addedNodes.forEach(function (node) {
                    if (node && node.nodeType === 1) {
                        moveModals(node);
                    }
                });
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });
    });
</script>

@stack('scripts')
</body>
</html>
