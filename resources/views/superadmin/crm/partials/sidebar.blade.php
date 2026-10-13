<aside class="sidebar">

    <h5 class="menu-title">MENU</h5>

    <ul class="menu">

        <!-- Dashboard -->
        <li class="dashboard {{ request()->routeIs('crm.dashboard') ? 'active' : '' }}">
            <a href="{{ route('crm.dashboard') }}">
                <div class="left">
                    <div class="icon">
                        <i data-lucide="layout-dashboard"></i>
                    </div>
                    <span>Dashboard</span>
                </div>
            </a>
        </li>


        <!-- Leads -->
        <li class="dashboard {{ request()->routeIs('crm.leads*') ? 'active' : '' }}">
            <a href="{{ route('crm.leads') }}">
                <div class="left">
                    <div class="icon">
                        <i data-lucide="user-round-plus"></i>
                    </div>
                    <span>Leads</span>
                </div>
            </a>
        </li>


        <!-- Follow Up -->
        <li class="dashboard {{ request()->routeIs('crm.followups*') ? 'active' : '' }}">
            <a href="{{ route('crm.followups') }}">
                <div class="left">
                    <div class="icon">
                        <i data-lucide="phone-call"></i>
                    </div>
                    <span>Follow Up</span>
                </div>
            </a>
        </li>


        <!-- Quotations -->
        <li class="dashboard {{ request()->routeIs('crm.quotations*') ? 'active' : '' }}">
            <a href="{{ route('crm.quotations') }}">
                <div class="left">
                    <div class="icon">
                        <i data-lucide="file-text"></i>
                    </div>
                    <span>Quotations</span>
                </div>
            </a>
        </li>

        <!-- CRM Deals -->
        <li class="dashboard {{ request()->routeIs('crm.deals*') ? 'active' : '' }}">
            <a href="{{ route('crm.deals') }}">
                <div class="left">
                    <div class="icon">
                        <i data-lucide="handshake"></i>
                    </div>
                    <span>CRM Deals</span>
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
        <li class="has-sub {{ request()->routeIs('crm.employees', 'crm.employee.*', 'crm.lead-sources.*', 'crm.lead-status.*', 'crm.lead-priority.*', 'crm.lead-bifurcation.*', 'crm.designation.*', 'crm.providers', 'crm.providers.*', 'crm.crm-settings') ? 'active' : '' }}">
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

                <li>
                    <a href="{{ route('crm.employees') }}">
                        <span>Employee Management</span>
                    </a>

                </li>

                <li>
                    <a href="{{ route('crm.lead-sources.index') }}">
                        <span>CRM Settings</span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('crm.providers') }}">
                        <span>Services &amp; Providers</span>
                    </a>
                </li>

            </ul>
        </li>


        <!-- Reports -->
        <li class="dashboard {{ request()->routeIs('crm.reports') ? 'active' : '' }}">
            <a href="{{ route('crm.reports') }}">
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
