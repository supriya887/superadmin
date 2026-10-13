<aside class="sidebar">

    <h5 class="menu-title">MENU</h5>

    <ul class="menu">

        <!-- Dashboard -->
        <li class="dashboard">
            <a href="index.php">
                <div class="left">
                    <div class="icon">
                        <i data-lucide="layout-dashboard"></i>
                    </div>
                    <span>Dashboard</span>
                </div>
            </a>
        </li>


        <!-- Leads -->
        <li class="dashboard">
            <a href="leads.php">
                <div class="left">
                    <div class="icon">
                        <i data-lucide="user-round-plus"></i>
                    </div>
                    <span>Leads</span>
                </div>
            </a>
        </li>


        <!-- Follow Up -->
        <li class="dashboard">
            <a href="follow-up.php">
                <div class="left">
                    <div class="icon">
                        <i data-lucide="phone-call"></i>
                    </div>
                    <span>Follow Up</span>
                </div>
            </a>
        </li>


        <!-- Quotations -->
        <li class="dashboard">
            <a href="quotations.php">
                <div class="left">
                    <div class="icon">
                        <i data-lucide="file-text"></i>
                    </div>
                    <span>Quotations</span>
                </div>
            </a>
        </li>


        <!-- Renewals -->
        <li class="dashboard">
            <a href="renewals.php">
                <div class="left">
                    <div class="icon">
                        <i data-lucide="refresh-cw"></i>
                    </div>
                    <span>Renewals</span>
                </div>
            </a>
        </li>


        <!-- CONTROLS & SETTINGS -->
        <li class="menu-heading">
            <h5 class="menu-title">CONTROLS &amp; SETTINGS</h5>
        </li>


        <!-- Manage -->
        <li class="has-sub">
            <a href="javascript:void(0)">
                <div class="left">
                    <div class="icon">
                        <i data-lucide="settings"></i>
                    </div>
                    <span>Manage</span>
                </div>

                <div class="arrow-icon">
                    <i data-lucide="chevron-down" class="arrow"></i>
                </div>
            </a>

            <ul class="sub-menu">
                <li class="employee-management-item">

                    <a href="employee-management.php" class="employee-management-link">
                        <span>Employee Management</span>
                    </a>

                    <!-- RIGHT SIDE PANEL -->
                    <div class="employee-panel">

                        <p class="panel-title">
                            Manage employees &amp; assignments
                        </p>

                        <ul class="employee-options">

                            <li>
                                <a href="employee.php">
                                    <i data-lucide="circle-user-round"></i>
                                    <span>Employee</span>
                                </a>
                                <div class="common-table-card employee-panel-card">

                                    <p class="employee-panel-title">
                                        Manage employees &amp; assignments
                                    </p>

                                    <ul class="employee-panel-list">

                                        <li>
                                            <a
                                                href="{{ Route::has('crm.employee') ? route('crm.employee') : '#' }}"
                                                class="employee-panel-link">
                                                <i data-lucide="user"></i>
                                                <span>Employee</span>
                                            </a>
                                            <div class="common-table-card employee-panel-card">

                                                <p class="employee-panel-title">
                                                    Manage employees &amp; assignments
                                                </p>

                                                <ul class="employee-panel-list">

                                                    <li>
                                                        <a
                                                            href="{{ Route::has('crm.employee') ? route('crm.employee') : '#' }}"
                                                            class="employee-panel-link">
                                                            <i data-lucide="user"></i>
                                                            <span>Employee</span>
                                                        </a>
                                                    </li>

                                                    <li>
                                                        <a
                                                            href="javascript:void(0)"
                                                            class="employee-panel-link">
                                                            <i data-lucide="shield-check"></i>
                                                            <span>Employee Permissions</span>
                                                        </a>
                                                    </li>

                                                    <li>
                                                        <a
                                                            href="javascript:void(0)"
                                                            class="employee-panel-link active">
                                                            <i data-lucide="bell"></i>
                                                            <span>Attendance Overview</span>
                                                        </a>
                                                    </li>

                                                </ul>

                                            </div>
                                        </li>

                                        <li>
                                            <a
                                                href="javascript:void(0)"
                                                class="employee-panel-link">
                                                <i data-lucide="shield-check"></i>
                                                <span>Employee Permissions</span>
                                            </a>
                                        </li>

                                        <li>
                                            <a
                                                href="javascript:void(0)"
                                                class="employee-panel-link active">
                                                <i data-lucide="bell"></i>
                                                <span>Attendance Overview</span>
                                            </a>
                                        </li>

                                    </ul>

                                </div>
                            </li>

                            <li>
                                <a href="employee-permissions.php">
                                    <i data-lucide="user-round-cog"></i>
                                    <span>Employee Permissions</span>
                                </a>
                            </li>

                            <li>
                                <a href="attendance-overview.php">
                                    <i data-lucide="bell"></i>
                                    <span>Attendance Overview</span>
                                </a>
                            </li>

                        </ul>

                    </div>

                </li>

                <li>
                    <a href="crm-settings.php">
                        <span>CRM Settings</span>
                    </a>
                </li>

                <li>
                    <a href="services-providers.php">
                        <span>Services &amp; Providers</span>
                    </a>
                </li>

                <li>
                    <a href="security.php">
                        <span>Security</span>
                    </a>
                </li>

            </ul>
        </li>


        <!-- Reports -->
        <li class="dashboard">
            <a href="reports.php">
                <div class="left">
                    <div class="icon">
                        <i data-lucide="file-bar-chart"></i>
                    </div>
                    <span>Reports</span>
                </div>
            </a>
        </li>

    </ul>
    <div class="crm-chat">
        <a href="crm-chat.php">
            <div class="icon">
                <i data-lucide="messages-square"></i>
            </div>
            <span>CRM Chat</span>
        </a>
    </div>
</aside>
