<!-- Sidebar -->
<div class="dashboard-sidebar" id="sidebar">
    <div class="sidebar-inner py-3">
        <a href="#" class="sidebar-link active">
            <img src="{{ asset('public/home/images/dashboard/icon/home.png') }}" alt="Dashboard">
            Dashboard
        </a>

        <div class="mt-3">
            <a href="#" class="sidebar-link">
                <img src="{{ asset('public/home/images/dashboard/icon/domain.png') }}" alt="Services">
                Services
            </a>
            <a href="#emailCollapse" data-bs-toggle="collapse" class="sidebar-link">
                <img src="{{ asset('public/home/images/dashboard/icon/mail.png') }}" alt="Emails">
                Emails
                <i class="fa fa-chevron-down dropdown-icon ms-auto"></i>
            </a>
            <div class="collapse" id="emailCollapse">
                <a href="#" class="sidebar-link" style="padding-left: 62px; font-size: 14px;">Business Email</a>
                <a href="#" class="sidebar-link" style="padding-left: 62px; font-size: 14px;">Google Workspace</a>
                <a href="#" class="sidebar-link" style="padding-left: 62px; font-size: 14px;">Microsoft 365</a>
            </div>
            <a href="#" class="sidebar-link">
                <img src="{{ asset('public/home/images/dashboard/icon/bill.png') }}" alt="Billing">
                Billing
            </a>
            <a href="#" class="sidebar-link">
                <img src="{{ asset('public/home/images/dashboard/icon/online-support.png') }}" alt="Support">
                Support
            </a>

            <form id="logout-form" action="{{ route('user_logout') }}" method="POST" style="display: none;">
                @csrf
            </form>
            <a href="#" class="sidebar-link mt-4" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                <img src="{{ asset('public/home/images/dashboard/icon/logout.png') }}" alt="Logout">
                Logout
            </a>
        </div>
    </div>
</div>

<!-- Sidebar arrow toggle — fixed position, always visible outside sidebar -->
<button class="sidebar-arrow-toggle" id="sidebarArrowToggle" aria-label="Toggle sidebar">
    <i class="fa fa-chevron-left"></i>
</button>