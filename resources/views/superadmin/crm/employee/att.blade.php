@extends('superadmin.layouts.crm')

@section('content')

<div class="crm-emp-shell employee-login-shell">
    @include('superadmin.crm.partials.employee-navigation', ['active' => 'attendance'])

    <!-- ================= MAIN CONTENT ================= -->

    <main class="employee-login-main">

        <!-- Page Header -->
        <header class="page-header employee-login-header">
            <div>
                <div class="breadcrumb welcome-text">
                    <p>
                        <a href="{{ Route::has('crm.dashboard') ? route('crm.dashboard') : '#' }}">Dashboard</a>
                        <span>/</span><a href="javascript:void(0)">Manage</a>
                        <span>/</span><a href="{{ route('crm.employees') }}">Employee Management</a>
                        <span>/</span>
                        Attendance Overview
                    </p>
                </div>
                <h2>Login Overview</h2>
            </div>
            <div class="back">
                <a href="{{ route('crm.employees') }}" class="employee-login-back">
                    <i data-lucide="arrow-left"></i>
                    <span>Back</span>
                </a>
            </div>
        </header>

        <!-- ================= LOGIN TABLE ================= -->
        <section class="common-table-section">

            <div class="common-table-card">
                <div class="common-table-header login-table-header">

                    <div class="login-table-title">

                        <p>
                            @if ($employees->total() > 0)
                            Showing <strong>{{ $employees->firstItem() }} - {{ $employees->lastItem() }}</strong> from
                            <strong>{{ $employees->total() }}</strong> employees
                            @else
                            Showing <strong>0</strong> employees
                            @endif
                        </p>



                    </div>


                    <form method="GET" action="{{ route('crm.employee.att') }}" class="table-search">
                        @foreach (['role', 'employee_id', 'status', 'from', 'to'] as $filter)
                        @if ($filters[$filter] !== '')
                        <input type="hidden" name="{{ $filter }}" value="{{ $filters[$filter] }}">
                        @endif
                        @endforeach
                        <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Search employees">
                        <i data-lucide="search"></i>
                    </form>

                </div>
                <form method="GET" action="{{ route('crm.employee.att') }}" id="attendanceFilterForm">
                    @if ($filters['search'] !== '')
                    <input type="hidden" name="search" value="{{ $filters['search'] }}">
                    @endif
                    <div class="table-filter-wrap" id="filterPanel">
                        <div class="filter-grid">
                            <div class="filter-group">
                                <h6>Role:</h6>
                                <div class="filter-options">
                                    <select name="role">
                                        <option value="">All roles</option>
                                        @foreach ($roles as $role)
                                        <option value="{{ $role }}" @selected($filters['role']===$role)>{{ ucwords(str_replace('_', ' ', $role)) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="filter-group">
                                <h6>Employee:</h6>
                                <div class="filter-options">
                                    <select name="employee_id">
                                        <option value="">All employees</option>
                                        @foreach ($employeeOptions as $employeeOption)
                                        <option value="{{ $employeeOption->id }}" @selected((string) $filters['employee_id']===(string) $employeeOption->id)>{{ $employeeOption->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="filter-group">
                                <h6>Account Status:</h6>
                                <div class="filter-options">
                                    <select name="status">
                                        <option value="">All statuses</option>
                                        @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended'] as $value => $label)
                                        <option value="{{ $value }}" @selected($filters['status']===$value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="filter-group">
                                <h6>Login Date:</h6>
                                <div class="date-range">
                                    <div class="date-input">
                                        <input type="date" name="from" value="{{ $filters['from'] }}" aria-label="Login from date">
                                        <i data-lucide="calendar-days"></i>
                                    </div>
                                    <div class="date-input">
                                        <input type="date" name="to" value="{{ $filters['to'] }}" aria-label="Login to date">
                                        <i data-lucide="calendar-days"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="table-filter">
                    <div class="border-line"></div>
                    <button class="filter-btn" id="toggleFilter" type="button">
                        <i data-lucide="sliders-horizontal"></i><span>Filters</span>
                    </button>
                </div>



                {{-- Legacy sample filters removed; live CRM filters are above. --}}
                {{--
                <!-- Filter Panel -->
                <div class="table-filter-wrap" id="filterPanel">

                    <div class="filter-grid">

                        <!-- Date Type -->

                        <div class="filter-group">

                            <h6>
                                Date Type :
                            </h6>

                            <div class="filter-options">

                                <select>
                                    <option selected>Select</option>
                                    <option>Created Date</option>
                                    <option>Follow Up Date</option>
                                    <option>Updated Date</option>
                                </select>

                            </div>

                        </div>


                        <!-- Date Range -->

                        <div class="filter-group">

                            <h6>
                                Date Range :
                            </h6>

                            <div class="date-range">

                                <div class="date-input">

                                    <input
                                        type="text"
                                        placeholder="From"
                                        onfocus="this.type='date'">

                                    <i data-lucide="calendar-days"></i>

                                </div>

                                <div class="date-input">

                                    <input
                                        type="text"
                                        placeholder="To"
                                        onfocus="this.type='date'">

                                    <i data-lucide="calendar-days"></i>

                                </div>

                            </div>

                        </div>


                        <!-- Status -->

                        <div class="filter-group">

                            <h6>
                                Status :
                            </h6>

                            <div class="filter-options">

                                <select>

                                    <option selected>Select</option>
                                    <option>New</option>
                                    <option>Contacted</option>
                                    <option>Qualified</option>
                                    <option>Quotation Sent</option>
                                    <option>Negotiation</option>
                                    <option>Won</option>
                                    <option>Lost</option>
                                    <option>Nurturing</option>

                                </select>

                            </div>

                        </div>


                        <!-- Employee -->

                        <div class="filter-group">

                            <h6>
                                Employee :
                            </h6>

                            <div class="filter-options">

                                <select>

                                    <option selected>Select</option>
                                    <option>Pranali Jadhav</option>
                                    <option>Rohit Patil</option>
                                    <option>Nisha Singh</option>
                                    <option>Aakash Menon</option>
                                    <option>Sneha Patil</option>
                                    <option>Aniket Deshmukh</option>
                                    <option>Karan Choudhury</option>
                                    <option>Preeti Sinha</option>

                                </select>

                            </div>

                        </div>


                        <!-- Lead Bifurcation -->

                        <div class="filter-group">

                            <h6>
                                Lead Bifurcation :
                            </h6>

                            <div class="filter-options">

                                <select>

                                    <option selected>Select</option>
                                    <option>New</option>
                                    <option>Existing</option>
                                    <option>Follow Up</option>
                                    <option>Converted</option>
                                    <option>Closed</option>

                                </select>

                            </div>

                        </div>


                        <!-- Lead Priority -->

                        <div class="filter-group">

                            <h6>
                                Lead Priority :
                            </h6>

                            <div class="filter-options">

                                <select>

                                    <option selected>Select</option>
                                    <option>Hot</option>
                                    <option>Warm</option>
                                    <option>Cold</option>

                                </select>

                            </div>

                        </div>

                    </div>

                </div>
                <!-- Same Filter Button -->
                <div class="table-filter">
                    <div class="border-line"></div>
                    <button class="filter-btn" id="toggleFilter">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="9" viewBox="0 0 12 9" fill="none">
                            <path d="M0.75 0.75H10.75M2.75 4.25H8.75M4.25 7.75H7.25" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <span>Filters</span>
                    </button>
                </div>
                --}}


                <table class="login-table employee-login-table">
                    <thead>
                        <tr class="table-head">
                            <td>Employee</td>
                            <td>Role</td>
                            <td>Login Date</td>
                            <td>Login Time</td>
                            <td>IP Address</td>
                            <td class="status-col">Account Status</td>
                        </tr>
                    </thead>
                    <tbody class="table-body">
                        @forelse ($employees as $employee)
                        @php
                        $employeeName = $employee->name ?: 'Employee';
                        $employeeRole = $employee->role ?: 'user';
                        $employeeStatus = strtolower($employee->status ?? 'inactive');
                        $avatarInitial = strtoupper(substr(trim($employeeName), 0, 1)) ?: 'E';
                        $avatarColors = ['purple', 'blue', 'teal', 'orange', 'green'];
                        $avatarClass = $avatarColors[abs(crc32($employeeName)) % count($avatarColors)];
                        @endphp
                        <tr class="login-row table-row">
                            <td class="customer">
                                @if ($employee->avatar_url)
                                <img src="{{ $employee->avatar_url }}" alt="{{ $employeeName }}" class="avatar">
                                @else
                                <div class="avatar {{ $avatarClass }}">{{ $avatarInitial }}</div>
                                @endif
                                <div>
                                    <h5>{{ $employeeName }}</h5>
                                    {{-- <p>{{ $employee->email }}</p> --}}
                                </div>
                            </td>
                            <td>{{ ucwords(str_replace('_', ' ', $employeeRole)) }}</td>
                            <td class="contact-info">
                                @if ($employee->last_login_at)
                                {{ $employee->last_login_at->format('d M Y') }}
                                @else
                                <span class="employee-login-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($employee->last_login_at)
                                {{ $employee->last_login_at->format('h:i A') }}
                                <small class="employee-login-relative">{{ $employee->last_login_at->diffForHumans() }}</small>
                                @else
                                <span class="employee-login-muted">Never logged in</span>
                                @endif
                            </td>
                            <td class="contact-info">
                                {{ $employee->ip_address ?: '—' }}
                            </td>
                            <td class="status-col">
                                <span class="badge {{ $employeeStatus === 'active' ? 'active' : 'inactive' }}">{{ ucfirst($employeeStatus) }}</span>
                            </td>
                        </tr>
                        @empty
                        <tr class="table-row">
                            <td colspan="6" class="employee-login-empty">No CRM employees match these filters.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>

                @if ($employees->hasPages())
                <div class="table-pagination">
                    @if ($employees->onFirstPage())
                        <a href="#" class="page-btn"><i data-lucide="chevron-left"></i></a>
                    @else
                        <a href="{{ $employees->previousPageUrl() }}" class="page-btn"><i data-lucide="chevron-left"></i></a>
                    @endif

                    @php $lastPage = max($employees->lastPage(), 1); @endphp
                    @for ($page = 1; $page <= min($lastPage, 5); $page++)
                        <a href="{{ $employees->url($page) }}" class="page-btn {{ $page == $employees->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                    @endfor

                    @if ($employees->hasMorePages())
                        <a href="{{ $employees->nextPageUrl() }}" class="page-btn"><i data-lucide="chevron-right"></i></a>
                    @else
                        <a href="#" class="page-btn"><i data-lucide="chevron-right"></i></a>
                    @endif
                </div>
                @endif

                {{-- Legacy static samples are retained only as a comment; the table above uses CRM data.
                <!-- Employee Attendance Table -->
                <table class="login-table employee-login-table">

                    <thead>
                        <tr class="table-head">

                            <td>
                                Employee
                            </td>

                            <td>
                                Location &amp; IP
                            </td>

                            <td>
                                Date
                            </td>

                            <td>
                                Check In
                            </td>

                            <td>
                                Check Out
                            </td>

                            <td class="status-col">
                                Status
                            </td>

                        </tr>
                    </thead>


                    <tbody class="table-body">

                        <!-- ROW 1 -->
                        <tr class="login-row table-row">

                            <td class="customer">

                                <div class="avatar purple">
                                    W
                                </div>

                                <div>
                                    <h5>Wilson Thomas</h5>
                                    <p>Sales Executive</p>
                                </div>

                            </td>


                            <td class="contact-info">

                                <div class="attendance-location">

                                    <div class="device-icon">
                                        <i data-lucide="smartphone"></i>
                                    </div>

                                    <div>
                                        <h5>Mumbai, India</h5>
                                        <p>App · 223.181.59.2</p>
                                    </div>

                                </div>

                            </td>


                            <td>
                                25 Aug 2026
                            </td>


                            <td>
                                09:12 AM
                            </td>


                            <td>
                                06:18 PM
                            </td>


                            <td class="status-col">
                                <span class="badge active">
                                    Active
                                </span>
                            </td>

                        </tr>


                        <!-- ROW 2 -->
                        <tr class="login-row table-row">

                            <td class="customer">

                                <div class="avatar blue">
                                    X
                                </div>

                                <div>
                                    <h5>Xavier Rodriguez</h5>
                                    <p>Product Manager</p>
                                </div>

                            </td>


                            <td class="contact-info">

                                <div class="attendance-location">

                                    <div class="device-icon">
                                        <i data-lucide="laptop"></i>
                                    </div>

                                    <div>
                                        <h5>Barcelona, Spain</h5>
                                        <p>Web · 198.51.100.1</p>
                                    </div>

                                </div>

                            </td>


                            <td>
                                26 Aug 2026
                            </td>


                            <td>
                                10:00 AM
                            </td>


                            <td>
                                05:00 PM
                            </td>


                            <td class="status-col">
                                <span class="badge active">
                                    Active
                                </span>
                            </td>

                        </tr>


                        <!-- ROW 3 -->
                        <tr class="login-row table-row">

                            <td class="customer">

                                <div class="avatar teal">
                                    Y
                                </div>

                                <div>
                                    <h5>Yasmin Lee</h5>
                                    <p>Graphic Designer</p>
                                </div>

                            </td>


                            <td class="contact-info">

                                <div class="attendance-location">

                                    <div class="device-icon">
                                        <i data-lucide="smartphone"></i>
                                    </div>

                                    <div>
                                        <h5>Seoul, South Korea</h5>
                                        <p>App · 203.0.113.5</p>
                                    </div>

                                </div>

                            </td>


                            <td>
                                27 Aug 2026
                            </td>


                            <td>
                                11:15 AM
                            </td>


                            <td>
                                04:45 PM
                            </td>


                            <td class="status-col">
                                <span class="badge inactive">
                                    Inactive
                                </span>
                            </td>

                        </tr>


                        <!-- ROW 4 -->
                        <tr class="login-row table-row">

                            <td class="customer">

                                <div class="avatar purple">
                                    Z
                                </div>

                                <div>
                                    <h5>Zachary Smith</h5>
                                    <p>Software Engineer</p>
                                </div>

                            </td>


                            <td class="contact-info">

                                <div class="attendance-location">

                                    <div class="device-icon">
                                        <i data-lucide="laptop"></i>
                                    </div>

                                    <div>
                                        <h5>Toronto, Canada</h5>
                                        <p>Web · 172.16.254.1</p>
                                    </div>

                                </div>

                            </td>


                            <td>
                                28 Aug 2026
                            </td>


                            <td>
                                08:30 AM
                            </td>


                            <td>
                                05:30 PM
                            </td>


                            <td class="status-col">
                                <span class="badge active">
                                    Active
                                </span>
                            </td>

                        </tr>


                        <!-- ROW 5 -->
                        <tr class="login-row table-row">

                            <td class="customer">

                                <div class="avatar blue">
                                    A
                                </div>

                                <div>
                                    <h5>Alice Johnson</h5>
                                    <p>UX Researcher</p>
                                </div>

                            </td>


                            <td class="contact-info">

                                <div class="attendance-location">

                                    <div class="device-icon">
                                        <i data-lucide="smartphone"></i>
                                    </div>

                                    <div>
                                        <h5>Berlin, Germany</h5>
                                        <p>App · 192.0.2.33</p>
                                    </div>

                                </div>

                            </td>


                            <td>
                                29 Aug 2026
                            </td>


                            <td>
                                09:00 AM
                            </td>


                            <td>
                                06:00 PM
                            </td>


                            <td class="status-col">
                                <span class="badge inactive">
                                    Inactive
                                </span>
                            </td>

                        </tr>


                        <!-- ROW 6 -->
                        <tr class="login-row table-row">

                            <td class="customer">

                                <div class="avatar yellow">
                                    B
                                </div>

                                <div>
                                    <h5>Brian Chang</h5>
                                    <p>Data Analyst</p>
                                </div>

                            </td>


                            <td class="contact-info">

                                <div class="attendance-location">

                                    <div class="device-icon">
                                        <i data-lucide="laptop"></i>
                                    </div>

                                    <div>
                                        <h5>Singapore</h5>
                                        <p>Web · 203.0.113.25</p>
                                    </div>

                                </div>

                            </td>


                            <td>
                                30 Aug 2026
                            </td>


                            <td>
                                10:30 AM
                            </td>


                            <td>
                                07:00 PM
                            </td>


                            <td class="status-col">
                                <span class="badge active">
                                    Active
                                </span>
                            </td>

                        </tr>


                        <!-- ROW 7 -->
                        <tr class="login-row table-row">

                            <td class="customer">

                                <div class="avatar red">
                                    C
                                </div>

                                <div>
                                    <h5>Cynthia White</h5>
                                    <p>Marketing Specialist</p>
                                </div>

                            </td>


                            <td class="contact-info">

                                <div class="attendance-location">

                                    <div class="device-icon">
                                        <i data-lucide="smartphone"></i>
                                    </div>

                                    <div>
                                        <h5>Sydney, Australia</h5>
                                        <p>App · 203.0.113.45</p>
                                    </div>

                                </div>

                            </td>


                            <td>
                                31 Aug 2026
                            </td>


                            <td>
                                09:45 AM
                            </td>


                            <td>
                                05:15 PM
                            </td>


                            <td class="status-col">
                                <span class="badge inactive">
                                    Inactive
                                </span>
                            </td>

                        </tr>

                    </tbody>

                </table>


                <div class="table-pagination">

                    <a href="#" class="page-btn">
                        <i data-lucide="chevron-left"></i>
                    </a>

                    <a href="#" class="page-btn active">1</a>
                    <a href="#" class="page-btn">2</a>
                    <a href="#" class="page-btn">3</a>
                    <a href="#" class="page-btn">4</a>
                    <a href="#" class="page-btn">5</a>
                    <a href="#" class="page-btn">
                        <i data-lucide="chevron-right"></i>
                    </a>

                </div>
                --}}

            </div>

        </section>

    </main>

</div>






<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script>
    document.addEventListener('DOMContentLoaded', function() {


        /* ===============================
           LUCIDE
        =============================== */

        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }


        /* ===============================
           FILTER TOGGLE
        =============================== */

        const filterBtn =
            document.getElementById('toggleFilter');

        const filterPanel =
            document.getElementById('filterPanel');


        if (filterBtn && filterPanel) {

            filterBtn.addEventListener(
                'click',
                function() {

                    filterPanel.classList.toggle('active');

                }
            );

        }


        /* ===============================
           AVATAR UPLOAD
           (Aapke existing code ke liye)
        =============================== */

        const avatarUpload =
            document.getElementById('avatarUpload');

        const avatarPreview =
            document.getElementById('avatarPreview');

        const avatarPlaceholder =
            document.getElementById('avatarPlaceholder');


        if (avatarUpload && avatarPreview) {

            avatarUpload.addEventListener(
                'change',
                function(e) {

                    const file =
                        e.target.files &&
                        e.target.files[0];


                    if (file) {

                        const reader =
                            new FileReader();


                        reader.onload =
                            function(evt) {

                                avatarPreview.src =
                                    evt.target.result;

                                avatarPreview.style.display =
                                    'block';


                                if (avatarPlaceholder) {

                                    avatarPlaceholder.style.display =
                                        'none';

                                }

                            };


                        reader.readAsDataURL(file);

                    }

                }
            );

        }

    });
</script>

@endsection
