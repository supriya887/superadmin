<div class="common-table-card employee-panel-card">
    <p class="employee-panel-title">Manage employees &amp; assignments</p>

    <ul class="employee-panel-list">
        <li>
            <a href="{{ route('crm.employees') }}" class="employee-panel-link{{ $active === 'employee' ? ' active' : '' }}">
                <i data-lucide="user"></i>
                <span>Employee</span>
            </a>
        </li>
        <li>
            <a href="{{ route('crm.employee.permissions') }}" class="employee-panel-link{{ $active === 'permissions' ? ' active' : '' }}">
                <i data-lucide="shield-check"></i>
                <span>Employee Permissions</span>
            </a>
        </li>
        <li>
            <a href="{{ Route::has('crm.employee.att') ? route('crm.employee.att') : '#' }}" class="employee-panel-link{{ $active === 'attendance' ? ' active' : '' }}">
                <i data-lucide="bell"></i>
                <span>Attendance Overview</span>
            </a>
        </li>
    </ul>
</div>