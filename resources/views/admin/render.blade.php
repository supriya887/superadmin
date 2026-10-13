<!DOCTYPE html>
<html>
@php
    DB::connection()->getPDO();
    // $setting = DB::table('admin_settings')->where('id', 1)->get();
    // print_r($setting);die;
@endphp

<head>
    <meta charset="UTF-8">
    <meta name='viewport' content='width=device-width, initial-scale=1.0, user-scalable=0'>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    @yield('metaTitle')
    @yield('cssLinks')

    <!--- Favicon --->
    {{-- <link rel="icon" href="{{ asset('images/' . $setting[0]->favicon) }}" type="image/x-icon" /> --}}

    <!-- Bootstrap css -->
    <link href="{{ asset('assets/plugins/bootstrap/css/bootstrap.css') }}" rel="stylesheet" id="style" />

    <!--- Style css --->
    <link href="{{ asset('assets/css/style.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/plugins.css') }}" rel="stylesheet">

    <!--- Icons css --->
    <link href="{{ asset('assets/css/icons.css') }}" rel="stylesheet">

    <!--- Animations css --->
    <link href="{{ asset('assets/css/animate.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/alertifyjs/build/css/alertify.min.css') }}" rel="stylesheet" type="text/css" />
    <!-- alertifyjs default themes  Css -->
    <link href="{{ asset('assets/alertifyjs/build/css/themes/default.min.css') }}" rel="stylesheet" type="text/css" />
    <!-- Sweetalert2 Css -->
    <link href="{{ asset('assets/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
    <!-- flatpicker -->
    <link href="{{ asset('assets/lib/flatpickr/flatpickr.min.css') }}" rel="stylesheet" type="text/css" />
    {{-- dropify --}}
    <link href="{{ asset('assets/plugins/dropify/css/dropify.min.css') }}" rel="stylesheet" type="text/css" />
    {{-- <link href="{{ asset('assets/plugins/fancyuploder/fancy_fileupload.css') }}" rel="stylesheet" type="text/css" /> --}}
    {{-- <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" /> --}}
    @yield('myCSS')

</head>
<style>
    .select2-container--default .select2-selection--multiple {
        border: 1px solid #ababb1;
    }

    .form-group {
        margin-bottom: 7px
    }

    .breadcrumb-item+.breadcrumb-item {
        padding-left: 0px;
    }

    .form-control:disabled,
    .form-control[readonly] {
        background-color: transparent;
    }

    .breadcrumb-item+.breadcrumb-item::before {
        padding-right: 2px;
    }

    .form-group label {
        font-size: 11px;
        margin-bottom: 0px;
    }

    .text-danger {
        font-size: 11px;
    }

    .form-control {
        font-size: 11px;
        border: 1px solid #67676b;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        font-size: 11px;
    }

    .select2-container--default .select2-selection--single {
        border: 1px solid #67676b;
    }

    .select2-container--default .select2-selection--single .select2-selection__placeholder {
        font-size: 12px;
    }

    @media and screen (max-width:1399px) {
        .form-group label {
            margin-bottom: 1px;
        }
    }
</style>
{{-- <body data-layout="horizontal"> --}}
<!-- Loader -->

<!-- /Loader -->

<body class="main-body app ltr horizontal">
    <!-- Begin page -->
    {{-- <div id="global-loader">
        <img src="{{ asset('assets/img/loaders/loader-4.svg') }}" class="loader-img" alt="Loader">
    </div> --}}
    <div class="page custom-index">
        <!-- main-header -->
        <div class="main-header side-header sticky nav nav-item">
            <div class="container-fluid main-container">
                <div class="main-header-left ">
                    <div class="app-sidebar__toggle mobile-toggle" data-bs-toggle="sidebar">
                        <a class="open-toggle" href="javascript:void(0);"><i class="header-icons"
                                data-eva="menu-outline"></i></a>
                        <a class="close-toggle" href="javascript:void(0);"><i class="header-icons"
                                data-eva="close-outline"></i></a>
                    </div>

                    <div class="responsive-logo">
                    </div>

                </div>
                <button class="navbar-toggler nav-link icon navresponsive-toggler vertical-icon ms-auto" type="button"
                    data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent-4"
                    aria-controls="navbarSupportedContent-4" aria-expanded="false" aria-label="Toggle navigation">
                    <i class="fe fe-more-vertical header-icons navbar-toggler-icon"></i>
                </button>
                <div
                    class="mb-0 navbar navbar-expand-lg navbar-nav-right responsive-navbar navbar-dark p-0  mg-lg-s-auto">
                    <div class="collapse navbar-collapse" id="navbarSupportedContent-4">
                        <div class="main-header-right">


                            <div class="nav nav-item  navbar-nav-right mg-lg-s-auto">
                                <div class="nav-item main-header-message ">
                                    <a class="new nav-link" href="{{ url('jobcard-search') }}"><i
                                            class="fe fe-search"></i></a>
                                </div>
                                <div class="dropdown  nav-item main-header-message ">
                                    <a class="new nav-link" href="javascript:void(0);"><i class="fe fe-moon"></i></a>
                                </div>
                                <div class="dropdown  nav-item main-header-message ">
                                    <a class="new nav-link" href="javascript:void(0);"><i class="fe fe-mail"></i></a>
                                    <div class="dropdown-menu">
                                        <div class="menu-header-content bg-primary-gradient text-start d-flex">
                                            <div class="">
                                                <h6 class="menu-header-title text-white mb-0">5 new Messages</h6>
                                            </div>
                                            <div class="my-auto mg-s-auto">
                                                <a class="badge bg-pill bg-warning float-end"
                                                    href="javascript:void(0);">Mark All Read</a>
                                            </div>
                                        </div>
                                        <div class="main-message-list chat-scroll">
                                            <a href="#" class="p-3 d-flex border-bottom">
                                                <div class="drop-img  cover-image  "
                                                    data-bs-image-src="{{ asset('assets/img/faces/3.jpg') }}">
                                                    <span class="avatar-status bg-teal"></span>
                                                </div>

                                                <div class="wd-90p">
                                                    <div class="d-flex">
                                                        <h5 class="mb-1 name">Paul Molive</h5>
                                                        <p class="time mb-0 text-end ms-auto float-end">10 min ago</p>
                                                    </div>
                                                    <p class="mb-0 desc">I'm sorry but i'm not sure how...</p>
                                                </div>
                                            </a>
                                            <a href="#" class="p-3 d-flex border-bottom">
                                                <div class="drop-img cover-image"
                                                    data-bs-image-src="{{ asset('assets/img/faces/2.jpg') }}">
                                                    <span class="avatar-status bg-teal"></span>
                                                </div>
                                                <div class="wd-90p">
                                                    <div class="d-flex">
                                                        <h5 class="mb-1 name">Sahar Dary</h5>
                                                        <p class="time mb-0 text-end ms-auto float-end">13 min ago</p>
                                                    </div>
                                                    <p class="mb-0 desc">All set ! Now, time to get to you now......
                                                    </p>
                                                </div>
                                            </a>
                                            <a href="#" class="p-3 d-flex border-bottom">
                                                <div class="drop-img cover-image"
                                                    data-bs-image-src="{{ asset('assets/img/faces/9.jpg') }}">
                                                    <span class="avatar-status bg-teal"></span>
                                                </div>
                                                <div class="wd-90p">
                                                    <div class="d-flex">
                                                        <h5 class="mb-1 name">Khadija Mehr</h5>
                                                        <p class="time mb-0 text-end ms-auto float-end">20 min ago</p>
                                                    </div>
                                                    <p class="mb-0 desc">Are you ready to pickup your Delivery...</p>
                                                </div>
                                            </a>
                                            <a href="#" class="p-3 d-flex border-bottom">
                                                <div class="drop-img cover-image"
                                                    data-bs-image-src="{{ asset('assets/img/faces/12.jpg') }}">
                                                    <span class="avatar-status bg-danger"></span>
                                                </div>
                                                <div class="wd-90p">
                                                    <div class="d-flex">
                                                        <h5 class="mb-1 name">Barney Cull</h5>
                                                        <p class="time mb-0 text-end ms-auto float-end">30 min ago</p>
                                                    </div>
                                                    <p class="mb-0 desc">Here are some products ...</p>
                                                </div>
                                            </a>
                                            <a href="#" class="p-3 d-flex border-bottom">
                                                <div class="drop-img cover-image"
                                                    data-bs-image-src="{{ asset('assets/img/faces/5.jpg') }}">
                                                    <span class="avatar-status bg-teal"></span>
                                                </div>
                                                <div class="wd-90p">
                                                    <div class="d-flex">
                                                        <h5 class="mb-1 name">Admin</h5>
                                                        {{-- <p class="time mb-0 text-end ms-auto float-end">35 min ago</p> --}}
                                                    </div>
                                                    <p class="mb-0 desc">I'm sorry but i'm not sure how...</p>
                                                </div>
                                            </a>
                                        </div>
                                        <div class="text-center dropdown-footer">
                                            <a href="#">VIEW ALL</a>
                                        </div>
                                    </div>
                                </div>
                                <div class="dropdown nav-item main-header-notification">
                                    <a class="new nav-link" href="javascript:void(0);"><i class="fe fe-bell"></i></a>
                                    <div class="dropdown-menu">
                                        <div class="menu-header-content bg-primary-gradient text-start d-flex">
                                            <div class="">
                                                <h6 class="menu-header-title text-white mb-0">7 new Notifications</h6>
                                            </div>
                                            <div class="my-auto ms-auto">
                                                <a class="badge bg-pill bg-warning float-end"
                                                    href="javascript:void(0);">Mark All Read</a>
                                            </div>
                                        </div>
                                        <div class="main-notification-list Notification-scroll">
                                            <a class="d-flex p-3 border-bottom" href="javascript:void(0);">
                                                <div class="notifyimg bg-success-transparent">
                                                    <i class="la la-shopping-basket text-success"></i>
                                                </div>
                                                <div class="ms-3">
                                                    <h5 class="notification-label mb-1">New Order Received</h5>
                                                    <div class="notification-subtext">1 hour ago</div>
                                                </div>
                                                <div class="ms-auto">
                                                    <i class="las la-angle-right text-end text-muted"></i>
                                                </div>
                                            </a>
                                            <a class="d-flex p-3 border-bottom" href="javascript:void(0);">
                                                <div class="notifyimg bg-danger-transparent">
                                                    <i class="la la-user-check text-danger"></i>
                                                </div>
                                                <div class="ms-3">
                                                    <h5 class="notification-label mb-1">22 verified registrations</h5>
                                                    <div class="notification-subtext">2 hour ago</div>
                                                </div>
                                                <div class="ms-auto">
                                                    <i class="las la-angle-right text-end text-muted"></i>
                                                </div>
                                            </a>
                                            <a class="d-flex p-3 border-bottom" href="javascript:void(0);">
                                                <div class="notifyimg bg-primary-transparent">
                                                    <i class="la la-check-circle text-primary"></i>
                                                </div>
                                                <div class="ms-3">
                                                    <h5 class="notification-label mb-1">Project has been approved</h5>
                                                    <div class="notification-subtext">4 hour ago</div>
                                                </div>
                                                <div class="ms-auto">
                                                    <i class="las la-angle-right text-end text-muted"></i>
                                                </div>
                                            </a>
                                            <a class="d-flex p-3 border-bottom" href="javascript:void(0);">
                                                <div class="notifyimg bg-pink-transparent">
                                                    <i class="la la-file-alt text-pink"></i>
                                                </div>
                                                <div class="ms-3">
                                                    <h5 class="notification-label mb-1">New files available</h5>
                                                    <div class="notification-subtext">10 hour ago</div>
                                                </div>
                                                <div class="ms-auto">
                                                    <i class="las la-angle-right text-end text-muted"></i>
                                                </div>
                                            </a>
                                            <a class="d-flex p-3 border-bottom" href="javascript:void(0);">
                                                <div class="notifyimg bg-warning-transparent">
                                                    <i class="la la-envelope-open text-warning"></i>
                                                </div>
                                                <div class="ms-3">
                                                    <h5 class="notification-label mb-1">New review received</h5>
                                                    <div class="notification-subtext">1 day ago</div>
                                                </div>
                                                <div class="ms-auto">
                                                    <i class="las la-angle-right text-end text-muted"></i>
                                                </div>
                                            </a>
                                            <a class="d-flex p-3" href="javascript:void(0);">
                                                <div class="notifyimg bg-purple-transparent">
                                                    <i class="la la-gem text-purple"></i>
                                                </div>
                                                <div class="ms-3">
                                                    <h5 class="notification-label mb-1">Updates Available</h5>
                                                    <div class="notification-subtext">2 days ago</div>
                                                </div>
                                                <div class="ms-auto">
                                                    <i class="las la-angle-right text-end text-muted"></i>
                                                </div>
                                            </a>
                                        </div>
                                        <div class="dropdown-footer">
                                            <a href="javascript:void(0);">VIEW ALL</a>
                                        </div>
                                    </div>
                                </div>
                                <div class="dropdown main-profile-menu nav nav-item nav-link">
                                    <a class="profile-user d-flex" href=""><img
                                            src="{{ url('assets/img/faces/6.jpg') }}" alt="user-img"
                                            class="rounded-circle mCS_img_loaded"><span></span></a>

                                    <div class="dropdown-menu">
                                        <div class="main-header-profile header-img">
                                            <div class="main-img-user"><img alt=""
                                                    src="{{ asset('assets/img/faces/6.jpg') }}"></div>
                                            <h6>{{ Session::get('userName') }}</h6><span></span>
                                        </div>
                                        <a class="dropdown-item" href="{{ url('change-password') }}"><i
                                                class="far fa-clock"></i>Change Password</a>
                                        <a class="dropdown-item" href="{{ url('logout') }}"><i
                                                class="fas fa-sign-out-alt"></i> Logout</a>
                                    </div>
                                </div>
                                {{-- <div class="dropdown main-header-message right-toggle">
                                    <a class="nav-link pe-0" data-bs-toggle="sidebar-right" data-bs-target=".sidebar-right">
                                        <i class="ion ion-md-menu tx-20 bg-transparent"></i>
                                    </a>
                                </div> --}}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /main-header -->
        @include('admin.horizontalbar')
        <div class="main-content app-content">
            <!-- container -->
            @yield('cardBody')
            <!-- Container closed -->
        </div>
        <!-- Footer opened -->
        <div class="main-footer ht-45">
            <div class="container-fluid pd-t-0-f ht-100p">
                <span> Copyright ©
                    <script>
                        document.write(new Date().getFullYear())
                    </script>. Design & Develop by test.
                </span>
            </div>
        </div>
        <!-- Footer closed -->
    </div>
</body>
<!-- END layout-wrapper -->




<!--- JQuery min js --->
<script src="{{ asset('assets/plugins/jquery/jquery.min.js') }}"></script>

<!--- Datepicker js --->
<script src="{{ asset('assets/plugins/jquery-ui/ui/widgets/datepicker.js') }}"></script>

<!--- flatpicker js --->
<script src="{{ asset('assets/lib/flatpickr/flatpickr.min.js') }}"></script>

<!--- Bootstrap Bundle js --->
<script src="{{ asset('assets/plugins/bootstrap/popper.min.js') }}"></script>
<script src="{{ asset('assets/plugins/bootstrap/js/bootstrap.min.js') }}"></script>

<!--- Ionicons js --->
<script src="{{ asset('assets/plugins/ionicons/ionicons.js') }}"></script>

{{-- datatable --}}

<!-- DATA TABLE JS-->
<script src="{{ asset('assets/plugins/datatable/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('assets/plugins/datatable/js/dataTables.bootstrap5.js') }}"></script>
<script src="{{ asset('assets/plugins/datatable/js/dataTables.buttons.min.js') }}"></script>
<script src="{{ asset('assets/plugins/datatable/js/buttons.bootstrap5.min.js') }}"></script>
<script src="{{ asset('assets/plugins/datatable/js/jszip.min.js') }}"></script>
<script src="{{ asset('assets/plugins/datatable/pdfmake/pdfmake.min.js') }}"></script>
<script src="{{ asset('assets/plugins/datatable/pdfmake/vfs_fonts.js') }}"></script>
<script src="{{ asset('assets/plugins/datatable/js/buttons.html5.min.js') }}"></script>
<script src="{{ asset('assets/plugins/datatable/js/buttons.print.min.js') }}"></script>
<script src="{{ asset('assets/plugins/datatable/js/buttons.colVis.min.js') }}"></script>
<!-- Responsive examples -->
<script src="{{ asset('assets/plugins/datatable/dataTables.responsive.min.js') }}"></script>
<script src="{{ asset('assets/plugins/datatable/responsive.bootstrap5.min.js') }}"></script>

<!-- pace js -->
<script src="{{ asset('assets/js/table-data.js') }}"></script>


<!--- Eva-icons js --->
<script src="{{ asset('assets/js/eva-icons.min.js') }}"></script>

<!--- Moment js --->
<script src="{{ asset('assets/plugins/moment/moment.js') }}"></script>
<!--- Perfect-scrollbar js --->
<script src="{{ asset('assets/plugins/perfect-scrollbar/perfect-scrollbar.min.js') }}"></script>
<script src="{{ asset('assets/plugins/perfect-scrollbar/p-scroll.js') }}"></script>

<!--- Sidebar js --->
<script src="{{ asset('assets/plugins/side-menu/sidemenu.js') }}"></script>
<!--- sticky js --->
<script src="{{ asset('assets/js/sticky.js') }}"></script>
<!--- Fileuploads js --->
{{-- <script src="{{ asset('assets/plugins/fileuploads/js/fileupload.js') }}"></script>
<script src="{{ asset('assets/plugins/fileuploads/js/file-upload.js') }}"></script> --}}

<!--- Fancy uploader js --->
{{-- <script src="{{ asset('assets/plugins/fancyuploder/jquery.ui.widget.js') }}"></script>
<script src="{{ asset('assets/plugins/fancyuploder/jquery.fileupload.js') }}"></script>
<script src="{{ asset('assets/plugins/fancyuploder/jquery.iframe-transport.js') }}"></script>
<script src="{{ asset('assets/plugins/fancyuploder/jquery.fancy-fileupload.js') }}"></script>
<script src="{{ asset('assets/plugins/fancyuploder/fancy-uploader.js') }}"></script> --}}

<!--- JQuery sparkline js --->
{{-- <script src="{{ asset('assets/plugins/jquery-sparkline/jquery.sparkline.min.js') }}"></script> --}}

<script src="{{ asset('assets/plugins/dropify/js/dropify.js') }}"></script>

<!--- Internal jquery.maskedinput js --->
{{-- <script src="{{ asset('assets/plugins/jquery.maskedinput/jquery.maskedinput.js') }}"></script> --}}

<!--- Internal Spectrum-colorpicker js --->
{{-- <script src="{{ asset('assets/plugins/spectrum-colorpicker/spectrum.js') }}"></script> --}}
<!--- Select2.min js --->
<script src="{{ asset('assets/plugins/select2/js/select2.min.js') }}"></script>
{{-- <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script> --}}
<!--- Internal jquery-simple-datetimepicker js --->
<script src="{{ asset('assets/plugins/amazeui-datetimepicker/js/amazeui.datetimepicker.min.js') }}"></script>
<!-- form-elements js -->
{{-- <script src="{{ asset('assets/js/form-elements.js') }}"></script> --}}
<!--- Right-sidebar js --->
<script src="{{ asset('assets/plugins/sidebar/sidebar.js') }}"></script>
<script src="{{ asset('assets/plugins/sidebar/sidebar-custom.js') }}"></script>
<!-- alertify js -->
<script src="{{ asset('assets/alertifyjs/build/alertify.min.js') }}"></script>
<!-- Sweet Alerts js -->
<script src="{{ asset('assets/sweetalert2/sweetalert2.min.js') }}"></script>
<!--swither-styles js-->
<script src="{{ asset('assets/js/index.js') }}"></script>
<!--themecolor js-->
<script src="{{ asset('assets/js/themecolor.js') }}"></script>

<script src="{{ asset('assets/js/swither-styles.js') }}"></script>

<script src="{{ asset('assets/js/custom.js') }}"></script>
@yield('jsLinks')

<script>
    $(document).ready(function() {
        $(document).on('select2:open', () => {
            window.setTimeout(function() {
                document.querySelector('.select2-container--open .select2-search__field')
                    .focus();
            }, 10);
        });

        $(document).on("input", function(event) {
            event.target.value = event.target.value.toLocaleUpperCase()
        });
    });

    function lowercase(id) {
        $('#' + id).keyup(function() {
            this.value = this.value.toLowerCase();
        });
    }

    function active(act) {
        if (act != '') {
            var len = $('.' + act).length;
            if (len == 1) {
                $("." + act).parents('li').addClass('mm-active');
                $("." + act).parents('ul').addClass('mm-show');
                $("." + act).closest('ul').addClass('mm-show');
                $("." + act).addClass('active');
                $(".mm-active .has-arrow").addClass('mm-active');
            }
        }

    }

    // function datepicker(id) {
    //     $('#' + id).datepicker({
    //         showOtherMonths: true,
    //         dateFormat: "dd-mm-yy",
    //         selectOtherMonths: true
    //     });
    //     // $('#'+id).datepicker({format: 'dd-mm-yy'});
    //     // $('#' + id).datepicker({
    //     //     showOtherMonths: true,
    //     //     dateFormat: "yyyy",
    //     //     selectOtherMonths: true
    //     // });

    //     $('#'+id).datetimepicker({
    // 	format: 'dd-mm-yyyy',
    // 	autoclose: true
    // });
    // }


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

    // function MYdatepicker(id) {
    //     $('#' + id).datepicker({
    //         showOtherMonths: true,
    //         dateFormat: "mm-yy",
    //         selectOtherMonths: true
    //     });
    // }

    function select2(id, title) {

        $('#' + id).select2({
            maximumInputLength: Infinity,
            placeholder: title,
            allowClear: true
        });
    }


    function select2NoSearch(id, title) {
        $('#' + id).select2({
            minimumResultsForSearch: Infinity,
            placeholder: title,
            allowClear: true
        });

    }

    // function withckformSubmit(url, redirect) {
    //     $("#form").on("submit", function(e) {
    //         e.preventDefault();
    //         for (instance in CKEDITOR.instances) {
    //             CKEDITOR.instances[instance].updateElement();
    //         }
    //         $.ajax({
    //             url: url,
    //             type: "POST",
    //             data: new FormData(this),
    //             contentType: false,
    //             cache: false,
    //             processData: false,
    //             dataType: "json",
    //             beforeSend: function(data) {
    //                 $("#spinner").show();
    //                 $("#submit").attr("disabled", true);
    //             },
    //             success: function(data) {
    //                 $("#spinner").hide();
    //                 $("#submit").attr("disabled", false);
    //                 if (data.error) {
    //                     $.each(data, function(key, value) {
    //                         if (value) {
    //                             $("#error-" + key).html(value);
    //                             $("#" + key).addClass("border-danger");
    //                         } else {
    //                             $("#error-" + key).html(" ");
    //                             $("#" + key).removeClass("border-danger");
    //                         }
    //                     });
    //                 }
    //                 if (data.success) {
    //                     $("#form .form-control").removeClass("border-danger");
    //                     $("#form .text-danger").html(" ");
    //                     alertify.success(data.success);
    //                     if (redirect != '') {
    //                         setTimeout(function() {
    //                             window.location.href = redirect;
    //                         }, 1000);
    //                     }

    //                 }
    //             },
    //             error: function(error) {
    //                 $("#spinner").hide();
    //                 $("#submit").attr("disabled", false);
    //                 errorMessage(error.status);
    //             }
    //         });
    //     });
    // }

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

    function dt(id) {
        $("#" + id).DataTable({
            dom: "lBfrtip",
            buttons: ['copy', 'excel', 'pdf'],
            language: {
                searchPlaceholder: 'Search...',
                scrollX: "100%",
                sSearch: '',
            }
        });

    }

    function dtscroll(id) {
        $("#" + id).DataTable({
            dom: "lBfrtip",
            buttons: ['copy', 'excel', 'pdf'],
            scrollX: true,
            language: {
                searchPlaceholder: 'Search...',
                scrollX: "100%",
                sSearch: '',
            }
        });

    }


    // function datatable(dt, url) {
    //     var token = $('input[name = _token]').val();
    //     $("#" + dt).DataTable({
    //         processing: true,
    //         serverSide: true,
    //         responsive: true,
    //         lengthMenu: [
    //             [10, 25, 50, 100],
    //             [10, 25, 50, 100]
    //         ],
    //         ajax: {
    //             url: url,
    //             type: "POST",
    //             dataType: "json",

    //             data: {
    //                 _token: token,
    //             }
    //         },
    //         dom: "lBfrtip",
    //         buttons: ["copy", "csv", "pdf"],
    //     });
    // }
    function datatable(dt, url, dtArr, col) {
        var token = $('input[name = _token]').val();
        $("#" + dt).DataTable({
            processing: true,
            serverSide: true,
            // order: [],
            scrollX: true,
            sScrollXInner: "110%",
            bScrollCollapse: true,
            responsive: true,
            lengthMenu: [
                [10, 25, 50, 100],
                [10, 25, 50, 100]
            ],
            ajax: url,
            columns: dtArr,
            dom: "lBfrtip",
            buttons: [{
                    extend: "excel",
                    exportOptions: {
                        columns: col
                    }
                },
                {
                    extend: "print",
                    exportOptions: {
                        columns: col
                    }
                },
                {
                    extend: "pdf",
                    exportOptions: {
                        columns: col
                    }
                },
            ]
        });
    }

    function errorMessage(id) {
        if (id == 403) {
            alertify.error('Your Session Expired ! Please Reload the Page.');
        } else if (id == 404) {
            alertify.error('Define ajax URL is not a valid controller name.');
        } else {
            alertify.error('Error !');
        }
    }

    function deleteRow(dlt, url, msg) {
        $(document).on("click", "." + dlt, function() {
            var id = $(this).attr("id");
            Swal.fire({
                title: "Are you sure?",
                text: "You won't be able to revert this!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Yes, delete it!"
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: url,
                        method: "POST",
                        data: {
                            id: id,
                            _token: '{{ csrf_token() }}',
                        },
                        success: function(data) {
                            alertify.success(msg);
                            setTimeout(function() {
                                window.location.reload();
                            }, 1000);
                        },
                        error: function(error) {
                            errorMessage(error.status);
                        }
                    });
                }
            });
        });
    }

    function deleteRowType(dlt, url, msg) {
        $(document).on("click", "." + dlt, function() {
            var id = $(this).attr("id");
            var vtype = $(this).attr("vtype");
            Swal.fire({
                title: "Are you sure?",
                text: "You won't be able to revert this!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Yes, delete it!"
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: url,
                        method: "POST",
                        data: {
                            vtype: vtype,
                            id: id,
                            _token: '{{ csrf_token() }}',
                        },
                        success: function(data) {
                            alertify.success(msg);
                            setTimeout(function() {
                                window.location.reload();
                            }, 1000);
                        },
                        error: function(error) {
                            errorMessage(error.status);
                        }
                    });
                }
            });
        });
    }

    function cancelRow(dlt, url, msg) {
        $(document).on("click", "." + dlt, function() {
            var id = $(this).attr("id");
            Swal.fire({
                title: "Are you sure?",
                text: "You won't be able to revert this!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Yes, cancel it!"
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: url,
                        method: "POST",
                        data: {
                            id: id,
                            _token: '{{ csrf_token() }}',
                        },
                        success: function(data) {
                            alertify.success(msg);
                            setTimeout(function() {
                                window.location.reload();
                            }, 1000);
                        },
                        error: function(error) {
                            errorMessage(error.status);
                        }
                    });
                }
            });
        });
    }

    function cancelRowWithMsg(dlt, url, msg) {
        $(document).on("click", "." + dlt, function() {
            var id = $(this).attr("id");
            Swal.fire({
                title: "Are you sure?",
                text: "You won't be able to revert this!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Yes, cancel it!"
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: url,
                        method: "POST",
                        data: {
                            id: id,
                            _token: '{{ csrf_token() }}',
                        },
                        success: function(data) {
                            console.log(data);
                            if (data.error) {
                                alertify.error(data.msg);
                            }
                            if (data.success) {
                                alertify.success(data.msg);
                            }
                            setTimeout(function() {
                                window.location.reload();
                            }, 1000);
                        },
                        error: function(data) {
                            errorMessage(error.status);
                        }
                    });
                }
            });
        });
    }

    function summernote(id, placeholder) {
        $('#' + id).summernote({
            placeholder: placeholder,
            height: 350,
        });
    }

    function deleteFile(dlt, url, msg) {
        $(document).on("click", "." + dlt, function() {
            var id = $(this).attr("id");
            Swal.fire({
                title: "Are you sure?",
                text: "You won't be able to revert this!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Yes, delete it!"
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: url,
                        method: "POST",
                        data: {
                            id: id,
                        },
                        success: function(data) {
                            alertify.success(msg);
                            $("#remove_file").val("");
                            // setTimeout(function() {
                            //     window.location.reload();
                            // }, 1000);
                        },
                        error: function(error) {
                            errorMessage(error.status);
                        }
                    });
                }
            });
        });
    }

    // function regenerate() {
    //     $.ajax({
    //         url: '',
    //         type: "POST",
    //         dataType: "json",
    //         success: function(data) {
    //             // $("#csrf").val(data.token);
    //             $("#csrf").val(data.token);
    //         }
    //     });
    // }

    function summernote(id, placeholder) {
        $('#' + id).summernote({
            placeholder: placeholder,
            height: 250,
        });
    }

    function isFloat(evt) {
        var charCode = (event.which) ? event.which : event.keyCode;
        if (charCode != 46 && charCode > 31 && (charCode < 48 || charCode > 57)) {
            // alert('Please enter only Number or float value');
            return false;
        } else {
            var parts = evt.srcElement.value.split('.');
            if (parts.length > 1 && charCode == 46) {
                return false;
            }
            return true;
        }
    }


    // function formSearchSubmit(url,id="form") {
    //     $("#" + id).on("submit", function(e) {
    //         e.preventDefault();
    //         $.ajax({
    //             url: url,
    //             type: "POST",
    //             data: new FormData(this),
    //             contentType: false,
    //             cache: false,
    //             processData: false,
    //             dataType: "json",
    //             beforeSend: function(data) {
    //                 $("#" + id + "#spinner").show();
    //                 $("#" + id + "#submit").attr("disabled", true);
    //             },
    //             success: function(data) {
    //                 $("#" + id + "#spinner").hide();
    //                 $("#" + id + "#submit").attr("disabled", false);
    //                 if (data.error) {
    //                     $.each(data, function(key, value) {
    //                         if (value) {
    //                             $("#error-" + key).html(value);
    //                             $("#" + key).addClass("border-danger");
    //                         } else {
    //                             $("#error-" + key).html(" ");
    //                             $("#" + key).removeClass("border-danger");
    //                         }
    //                     });
    //                 }
    //                 if (data.success) {

    //                     $("#" + id + ".form-control").removeClass("border-danger");
    //                     $("#" + id + ".text-danger").html(" ");
    //                     $('#datatable').DataTable().clear().destroy();
    //                     $('#filter').val(1);
    //                     search();

    //                 }
    //             },
    //             error: function(error) {
    //                 $("#" + id + "#spinner").hide();
    //                 $("#" + id + "#submit").attr("disabled", false);
    //                 errorMessage(error.status);
    //             }
    //         });
    //     });
    // }
    // setInterval(function() {
    //     regenerate();
    // }, 10000);
</script>

</html>

@yield('myJS')
