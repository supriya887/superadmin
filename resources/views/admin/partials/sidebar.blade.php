<nav id="sidebar">
    <div class="sidebar-header">
        <h4 class="mb-0">Tizzy Admin</h4>
    </div>

    <ul class="list-unstyled components">
        <li class="{{ Request::is('partner-admin/dashboard') ? 'active' : '' }}">
            <a href="{{ url('partner-admin/dashboard') }}"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
        </li>
        <li class="{{ Request::is('partner-admin/customers*') ? 'active' : '' }}">
            <a href="{{ url('partner-admin/customers') }}"><i class="fas fa-users me-2"></i> Customers</a>
        </li>
        <li>
            <a href="#"><i class="fas fa-concierge-bell me-2"></i> Services</a>
        </li>
        <li>
            <a href="#"><i class="fas fa-coins me-2"></i> Finance</a>
        </li>
        <li>
            <a href="#"><i class="fas fa-calendar-check me-2"></i> Subscriptions</a>
        </li>
        <li>
            <a href="#"><i class="fas fa-chart-line me-2"></i> Reports</a>
        </li>
        <li>
            <a href="#"><i class="fas fa-headset me-2"></i> Support</a>
        </li>
        <li>
            <a href="#"><i class="fas fa-cog me-2"></i> Settings</a>
        </li>
    </ul>
</nav>
