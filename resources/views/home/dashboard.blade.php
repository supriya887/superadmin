@extends('home.render')

@section('metaTitle')
    <title>Dashboard - Tizzy Cloud</title>
@endsection

@section('myCSS')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('public/home/css/topbar.css') }}">
    <link rel="stylesheet" href="{{ asset('public/home/css/graph.css') }}">
    {{-- <style>
        /* Hide the global bottom header on the dashboard page only */
        .bottom-header {
            display: none !important;
        }
    </style> --}}
@endsection

@section('Body')
    <div class="position-relative dashboard-layout">
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        @include('home.sidebar')

        <div class="bg-light min-vh-100 dashboard-main-wrapper">
            <!-- Dashboard Topbar -->
            @include('home.dashboard_header')

            <section class="p-4 dashboard-main-content">
                <h2 class="mb-1 fw-normal text-dark fs-3" data-aos="fade-up" data-aos-easing="linear"
                    data-aos-duration="1200">Hello {{ Auth::user()->name }}!</h2>
                <p class="text-muted mb-4 fs-6" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1200">Here's
                    a quick overview of your dashboard</p>

                <div class="row g-4 mt-2">
                    <!-- Card 1 -->
                    <div class="col-xl-3 col-md-6" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1200">
                        <div class="card border-0 shadow-sm h-100 text-white"
                            style="background:#1665b6; border-radius:8px;">
                            <div class="card-body p-4 d-flex align-items-center gap-3">
                                <img src="{{ asset('public/home/images/admin/Groupicon.png') }}" width="60"
                                    alt="Active Services">
                                <div>
                                    <h6 class="mb-1 fw-medium text-white fs-6">Active Services</h6>
                                    <h2 class="mb-0 fw-normal lh-1 text-white">141</h2>
                                </div>
                            </div>
                            <a href="#"
                                class="card-footer border-0 card-footer-link d-flex align-items-center bg-light-blue fw-medium py-2 px-4 rounded-bottom">
                                View all <img src="{{ asset('public/home/images/admin/Rectangle47.png') }}" class="p-2">
                            </a>
                        </div>
                    </div>

                    <!-- Card 2 -->
                    <div class="col-xl-3 col-md-6" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1200">
                        <div class="card border-0 shadow-sm h-100" style="border-radius:8px;">
                            <div class="card-body p-4 d-flex align-items-center gap-3">
                                <img src="{{ asset('public/home/images/admin/Groupicon2.png') }}" width="60" alt="Open Ticket">
                                <div>
                                    <h6 class="mb-1 fw-medium text-dark fs-6">Open Ticket</h6>
                                    <h2 class="mb-0 fw-normal lh-1 text-orange">02</h2>
                                </div>
                            </div>
                            <a href="#"
                                class="card-footer border-0 card-footer-link d-flex align-items-center bg-light-orange fw-medium py-2 px-4 rounded-bottom">
                                View all <img src="{{ asset('public/home/images/admin/Rectangle47.png') }}" class="p-2">
                            </a>
                        </div>
                    </div>

                    <!-- Card 3 -->
                    <div class="col-xl-3 col-md-6" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1200">
                        <div class="card border-0 shadow-sm h-100" style="border-radius:8px;">
                            <div class="card-body p-4 d-flex align-items-center gap-3">
                                <img src="{{ asset('public/home/images/admin/Groupicon3.png') }}" width="60"
                                    alt="Overdue Invoice">
                                <div>
                                    <h6 class="mb-1 fw-medium text-dark fs-6">Overdue Invoice</h6>
                                    <h2 class="mb-0 fw-normal lh-1 text-purple">04</h2>
                                </div>
                            </div>
                            <a href="#"
                                class="card-footer border-0 card-footer-link d-flex align-items-center bg-light-purple fw-medium py-2 px-4 rounded-bottom">
                                View all <img src="{{ asset('public/home/images/admin/Rectangle47.png') }}" class="p-2">
                            </a>
                        </div>
                    </div>

                    <!-- Card 4 -->
                    <div class="col-xl-3 col-md-6" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1200">
                        <div class="card border-0 shadow-sm h-100" style="border-radius:8px;">
                            <div class="card-body p-4 d-flex align-items-center gap-3">
                                <img src="{{ asset('public/home/images/admin/Groupicon4.png') }}" width="60" alt="Support">
                                <div>
                                    <h6 class="mb-1 fw-medium text-dark fs-6">Support</h6>
                                    <h2 class="mb-0 fw-normal lh-1 text-green">04</h2>
                                </div>
                            </div>
                            <a href="#"
                                class="card-footer border-0 card-footer-link d-flex align-items-center bg-light-green fw-medium py-2 px-4 rounded-bottom">
                                View all <img src="{{ asset('public/home/images/admin/Rectangle47.png') }}" class="p-2">
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            <section class="px-4 pb-4">
                <div class="row">
                    <div class="col-md-8" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1200">
                        <div class="card border-0 shadow-sm" style="border-radius:8px;">
                            <div class="card-header bg-white border-bottom-0 pt-4 px-4 pb-0">
                                <h5 class="mb-3 fw-normal text-dark fs-5">Active Services</h5>
                            </div>

                            <div class="card-body p-0">
                                @for ($i = 0; $i < 4; $i++)
                                    <div
                                        class="d-flex flex-column flex-md-row align-items-md-center justify-content-between py-4 px-4 border-top">
                                        <div class="mb-2 mb-md-0">
                                            <span class="d-block text-dark lh-sm"><strong class="fw-normal fs-6">Google
                                                    Workspace:</strong> <span class="text-secondary fw-medium">Business
                                                    Starter</span></span>
                                            <span class="text-muted" style="font-size:13px;">Unitsafety.in-4 user</span>
                                        </div>
                                        <div class="mb-2 mb-md-0">
                                            <span class="text-danger fw-normal fs-6">Expired</span> <span
                                                class="text-secondary fw-normal fs-6">160 days ago</span>
                                        </div>
                                        <div class="mb-2 mb-md-0 d-flex align-items-center gap-2">
                                            <span class="btn badge-soft-success px-4 py-2 m-0 fw-medium shadow-none"
                                                style="border-radius: 6px; cursor: default; font-size: 13px; line-height: 1.5;">Active</span>
                                            <button class="btn btn-manage px-4 py-2 m-0 fw-medium shadow-none"
                                                style="border-radius: 6px; font-size: 13px; line-height: 1.5;">Manage
                                                Account</button>
                                        </div>
                                    </div>
                                @endfor
                            </div>
                            <div class="card-footer border-0 p-0 bg-light-blue rounded-bottom">
                                <a href="#"
                                    class="card-footer-link d-flex align-items-center p-3 text-decoration-none fw-medium"
                                    style="color:#1665b6;"> View all Services <img
                                        src="{{ asset('public/home/images/admin/Rectangle47.png') }}" class="p-2">
                                </a>
                            </div>

                        </div>
                    </div>

                    <div class="col-md-4" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1200">
                        <div class="card border-0 shadow-sm graph-card text-center" style="border-radius:8px;">
                            <div class="card-header bg-white border-bottom-0 pt-4 px-4 pb-0 text-start">
                                <h5 class="mb-0 fw-medium text-dark fs-5">Service Usage Overview</h5>
                            </div>
                            <div class=" p-0 pt-3">
                                <div class="card-body" id="usageChart" style="min-height: 250px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection

@section('jsLinks')
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
@endsection

@section('myJS')
    <script>
        $(document).ready(function() {
            var options = {
                series: [{
                    name: "Series 1",
                    data: [50, 85, 120, 85, 160, 245, 160, 90, 275, 250, 275, 300]
                }, {
                    name: "Series 2",
                    data: [25, 45, 70, 50, 70, 95, 80, 65, 110, 100, 175, 250]
                }],
                chart: {
                    type: 'area',
                    height: 330,
                    toolbar: {
                        show: false
                    },
                    fontFamily: 'inherit',
                    parentHeightOffset: 0
                },
                colors: ['#00b894', '#1665b6'],
                fill: {
                    type: ['gradient', 'gradient'],
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: [0.3, 0.6],
                        opacityTo: [0.05, 0.2],
                        stops: [0, 90, 100]
                    }
                },
                dataLabels: {
                    enabled: false
                },
                stroke: {
                    curve: 'smooth',
                    width: 2,
                    dashArray: 4
                },
                legend: {
                    show: false
                },
                grid: {
                    borderColor: '#f1f3fa',
                    strokeDashArray: 4,
                    padding: {
                        top: 10,
                        right: 10,
                        bottom: 0,
                        left: 10
                    }
                },
                xaxis: {
                    categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov',
                        'Dec'
                    ],
                    axisBorder: {
                        show: false
                    },
                    axisTicks: {
                        show: false
                    },
                    labels: {
                        style: {
                            colors: '#9e9e9e',
                            fontSize: '12px'
                        }
                    }
                },
                yaxis: {
                    labels: {
                        style: {
                            colors: '#9e9e9e',
                            fontSize: '12px'
                        }
                    }
                },
                markers: {
                    size: 0,
                    hover: {
                        size: 6
                    },
                    discrete: [{
                            seriesIndex: 0,
                            dataPointIndex: 0,
                            fillColor: '#1665b6',
                            strokeColor: '#1665b6',
                            size: 4
                        },
                        {
                            seriesIndex: 0,
                            dataPointIndex: 2,
                            fillColor: '#1665b6',
                            strokeColor: '#1665b6',
                            size: 4
                        },
                        {
                            seriesIndex: 0,
                            dataPointIndex: 3,
                            fillColor: '#00b894',
                            strokeColor: '#00b894',
                            size: 4
                        },
                        {
                            seriesIndex: 0,
                            dataPointIndex: 5,
                            fillColor: '#1665b6',
                            strokeColor: '#1665b6',
                            size: 4
                        },
                        {
                            seriesIndex: 0,
                            dataPointIndex: 7,
                            fillColor: '#1665b6',
                            strokeColor: '#1665b6',
                            size: 4
                        },
                        {
                            seriesIndex: 0,
                            dataPointIndex: 8,
                            fillColor: '#1665b6',
                            strokeColor: '#1665b6',
                            size: 4
                        },
                        {
                            seriesIndex: 0,
                            dataPointIndex: 9,
                            fillColor: '#1665b6',
                            strokeColor: '#1665b6',
                            size: 4
                        },
                        {
                            seriesIndex: 0,
                            dataPointIndex: 11,
                            fillColor: '#1665b6',
                            strokeColor: '#1665b6',
                            size: 4
                        },
                        {
                            seriesIndex: 1,
                            dataPointIndex: 0,
                            fillColor: '#1665b6',
                            strokeColor: '#1665b6',
                            size: 4
                        },
                        {
                            seriesIndex: 1,
                            dataPointIndex: 2,
                            fillColor: '#1665b6',
                            strokeColor: '#1665b6',
                            size: 4
                        },
                        {
                            seriesIndex: 1,
                            dataPointIndex: 3,
                            fillColor: '#1665b6',
                            strokeColor: '#1665b6',
                            size: 4
                        },
                        {
                            seriesIndex: 1,
                            dataPointIndex: 5,
                            fillColor: '#1665b6',
                            strokeColor: '#1665b6',
                            size: 4
                        },
                        {
                            seriesIndex: 1,
                            dataPointIndex: 7,
                            fillColor: '#1665b6',
                            strokeColor: '#1665b6',
                            size: 4
                        },
                        {
                            seriesIndex: 1,
                            dataPointIndex: 8,
                            fillColor: '#1665b6',
                            strokeColor: '#1665b6',
                            size: 4
                        },
                        {
                            seriesIndex: 1,
                            dataPointIndex: 9,
                            fillColor: '#1665b6',
                            strokeColor: '#1665b6',
                            size: 4
                        },
                        {
                            seriesIndex: 1,
                            dataPointIndex: 11,
                            fillColor: '#1665b6',
                            strokeColor: '#1665b6',
                            size: 4
                        }
                    ]
                }
            };

            var chart = new ApexCharts(document.querySelector("#usageChart"), options);
            chart.render();
            // ── Sidebar toggle: dual mode ──
            function isDesktop() { return $(window).width() >= 992; }

            $('#sidebarArrowToggle').on('click', function () {
                if (isDesktop()) {
                    // Desktop: collapse/expand in-flow (no overlay)
                    $('body').toggleClass('sidebar-collapsed');
                    $('#sidebar').toggleClass('collapsed');
                } else {
                    // Mobile: slide-in overlay
                    $('body').toggleClass('sidebar-open');
                    $('#sidebar').toggleClass('show');
                    $('#sidebarOverlay').toggleClass('show');
                }
            });

            // Close mobile sidebar when overlay is clicked
            $('#sidebarOverlay').on('click', function () {
                $('body').removeClass('sidebar-open');
                $('#sidebar').removeClass('show');
                $(this).removeClass('show');
            });

            // Clean up stale classes on resize
            $(window).on('resize', function () {
                if (isDesktop()) {
                    $('body').removeClass('sidebar-open');
                    $('#sidebar').removeClass('show');
                    $('#sidebarOverlay').removeClass('show');
                } else {
                    $('body').removeClass('sidebar-collapsed');
                    $('#sidebar').removeClass('collapsed');
                }
            });
        });
    </script>
@endsection
