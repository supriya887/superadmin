@php
    // routeIs() OR legacy path urls (no route name). Supports * globs.
    function saHit(string $p): bool
    {
        if (request()->routeIs($p)) return true;
        $path = trim(request()->path(), '/');
        $n = trim($p, '/');
        if ($n === '') return false;
        return str_contains($n, '*') ? fnmatch($n, $path) : ($path === $n || str_ends_with($path, '/' . $n) || str_contains($path, $n . '/'));
    }
    function saAny(array $ps, callable $ok): bool { foreach ($ps as $p) { if ($ok((string) $p)) return true; } return false; }
    function saOpen(array $p): string { return saAny($p, fn ($x) => saHit($x)) ? 'show' : ''; }
    function saActive(array $p): string { return saAny($p, fn ($x) => saHit($x)) ? 'menuitem-active' : ''; }
    function saLink(array $p): string { return saAny($p, fn ($x) => saHit($x)) ? 'active' : ''; }
    $partnerApprovalsPending = \App\Models\Partner::query()
        ->where(function ($q) {
            $q->where('status', 'pending')->orWhere('status', 2)->orWhere('status', '2');
        })
        ->count();
    $partnersCount = 0;
    try {
        $partnersCount = (int) \App\Models\Partner::query()
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhere('status', '!=', 'inactive');
            })
            ->count();
    } catch (\Throwable) {
        $partnersCount = 0;
    }
    $ordersCount = 0;
    try {
        $ordersCount = (int) \App\Models\Order::query()->count();
    } catch (\Throwable) {
        $ordersCount = 0;
    }
    $creditRequestsPending = 0;
    foreach (['pgsql_billing', 'pgsql_auth', config('database.default')] as $creditRequestConnection) {
        if ($creditRequestConnection && \Illuminate\Support\Facades\Schema::connection($creditRequestConnection)->hasTable('credit_requests')) {
            $creditRequestsPending = \App\Models\CreditRequest::on($creditRequestConnection)
                ->where(function ($q) {
                    $q->whereRaw("LOWER(TRIM(COALESCE(status, 'pending'))) = 'pending'");
                })
                ->count();
            break;
        }
    }
    $licenseDecreasePending = 0;
    if (\Illuminate\Support\Facades\Schema::connection('pgsql_billing')->hasTable('license_decrease_requests')) {
        $licenseDecreasePending = \App\Models\LicenseDecreaseRequest::query()
            ->whereRaw("LOWER(TRIM(COALESCE(status, 'pending'))) = 'pending'")
            ->count();
    }
@endphp

<div class="sidenav-menu">

    <a href="{{ route('superadmin.dashboard') }}" class="logo">
        <span class="logo logo-light">
            <span class="logo-lg"><img src="{{ asset('home/images/logo_tagline.png') }}" alt="logo" /></span>
            <span class="logo-sm"><img src="{{ asset('home/images/logo_tagline.png') }}" alt="small logo" /></span>
        </span>
        <span class="logo logo-dark">
            <span class="logo-lg"><img src="{{ asset('home/images/logo_tagline.png') }}" alt="dark logo" /></span>
            <span class="logo-sm"><img src="{{ asset('home/images/logo_tagline.png') }}" alt="small logo" /></span>
        </span>
    </a>

    <button class="button-on-hover">
        <span class="btn-on-hover-icon"></span>
    </button>

    <button class="button-close-offcanvas">
        <i class="ti ti-menu-4 align-middle"></i>
    </button>

    <div class="scrollbar" data-simplebar="">

        

        <div id="sidenav-menu" class="mt-4">
            <ul class="side-nav">
                @if(permission(26, 'View'))
                {{-- <li class="side-nav-title mt-2">Navigation</li> --}}
                <li class="side-nav-item {{ saActive(['superadmin.dashboard']) }}">
                    <a href="{{ route('superadmin.dashboard') }}" class="side-nav-link {{ saLink(['superadmin.dashboard']) }}">
                        <span class="menu-icon"><i class="ti ti-layout-dashboard"></i></span>
                        <span class="menu-text">Dashboard</span>
                    </a>
                </li>
                @endif
                @php $currentSaPath = '/' . trim(request()->path(), '/'); @endphp
                @if(permission(10, 'View'))
                <li class="side-nav-item {{ (saActive(['superadmin.orders.*']) !== '' || str_contains($currentSaPath, 'superadmin/orders')) ? 'menuitem-active' : '' }}">
                    <a href="{{ route('superadmin.orders.index') }}" class="side-nav-link {{ (saLink(['superadmin.orders.*']) !== '' || str_contains($currentSaPath, 'superadmin/orders')) ? 'active' : '' }}">
                        <span class="menu-icon"><i class="ti ti-shopping-cart"></i></span>
                        <span class="menu-text">Orders</span>
                        @if(($ordersCount ?? 0) > 0)
                            <span class="badge bg-secondary ms-1">{{ $ordersCount }}</span>
                        @endif
                    </a>
                </li>
                @endif
                <li class="side-nav-item {{ saActive(['crm.deals']) }}">
                    <a href="{{ route('crm.deals') }}" class="side-nav-link {{ saLink(['crm.deals']) }}">
                        <span class="menu-icon"><i class="ti ti-briefcase"></i></span>
                        <span class="menu-text">CRM Deals</span>
                        @if(($crmDealsCount ?? 0) > 0)
                            <span class="badge bg-success ms-1">{{ $crmDealsCount }}</span>
                        @endif
                    </a>
                </li>
                @if(permission(7, 'View'))
                <li class="side-nav-item {{ saActive(['superadmin.partner_requests.*']) }}">
                    <a href="{{ route('superadmin.partner_requests.index') }}" class="side-nav-link {{ saLink(['superadmin.partner_requests.*']) }}">
                        <span class="menu-icon"><i class="ti ti-user-check"></i></span>
                        <span class="menu-text">Partner approvals</span>
                        @if($partnerApprovalsPending > 0)
                            <span class="badge bg-danger ms-1">{{ $partnerApprovalsPending }}</span>
                        @endif
                    </a>
                </li>
                @endif
                @if(permission(6, 'View'))
                <li class="side-nav-item {{ saActive(['superadmin.b2b_partners.*', 'superadmin.b2b_partners.customers.*']) }}">
                    <a href="{{ route('superadmin.b2b_partners.list') }}" class="side-nav-link {{ saLink(['superadmin.b2b_partners.*', 'superadmin.b2b_partners.customers.*']) }}">
                        <span class="menu-icon"><i class="ti ti-building-community"></i></span>
                        <span class="menu-text">Partners</span>
                        @if(($partnersCount ?? 0) > 0)
                            <span class="badge bg-secondary ms-1">{{ $partnersCount }}</span>
                        @endif
                    </a>
                </li>
                @endif

                {{-- Products --}}
                {{-- <li class="side-nav-item {{ saActive(['superadmin.products.*']) }}">
                    <a href="{{ route('superadmin.products.index') }}" class="side-nav-link {{ saLink(['superadmin.products.*']) }}">
                        <span class="menu-icon"><i class="ti ti-package"></i></span>
                        <span class="menu-text">Products</span>
                    </a>
                </li> --}}

                {{-- Plans (collapsible) --}}
                {{-- @php $plansOpen = saOpen(['superadmin.plans.*','superadmin.variants.*','superadmin.options.*']); @endphp
                <li class="side-nav-item {{ $plansOpen ? 'menuitem-active' : '' }}">
                    <a href="#sidebarPlans" data-bs-toggle="collapse"
                       aria-expanded="{{ $plansOpen ? 'true' : 'false' }}"
                       class="side-nav-link {{ $plansOpen ? '' : 'collapsed' }}">
                        <span class="menu-icon"><i class="ti ti-list-details"></i></span>
                        <span class="menu-text">Plans</span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse {{ $plansOpen }}" id="sidebarPlans">
                        <ul class="sub-menu">
                            <li class="side-nav-item {{ saActive(['superadmin.plans.*']) }}">
                                <a href="{{ route('superadmin.plans.index') }}" class="side-nav-link {{ saLink(['superadmin.plans.*']) }}">
                                    <span class="menu-text">All Plans</span>
                                </a>
                            </li>
                            <li class="side-nav-item {{ saActive(['superadmin.variants.*']) }}">
                                <a href="#" class="side-nav-link {{ saLink(['superadmin.variants.*']) }}">
                                    <span class="menu-text">Variants</span>
                                </a>
                            </li>
                            <li class="side-nav-item {{ saActive(['superadmin.options.*']) }}">
                                <a href="#" class="side-nav-link {{ saLink(['superadmin.options.*']) }}">
                                    <span class="menu-text">Options</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li> --}}
                @if(permission(8, 'View')) {{-- Module ID for B2B Partners --}}
                @php $b2bOpen = saOpen([
                    'superadmin.b2b.add_subscription',
                    'superadmin.b2b.add_subscription.store',
                    'superadmin.renewals.*',
                    'superadmin.domaintransferrequests.*',
                    // 'superadmin.partner_logs.*',
                ]); @endphp
                <li class="side-nav-item {{ $b2bOpen ? 'menuitem-active' : '' }}">
                    <a href="#sidebarB2b" data-bs-toggle="collapse"
                       aria-expanded="{{ $b2bOpen ? 'true' : 'false' }}"
                       class="side-nav-link {{ $b2bOpen ? '' : 'collapsed' }}">
                        <span class="menu-icon"><i class="ti ti-building-warehouse"></i></span>
                        <span class="menu-text">B2B</span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse {{ $b2bOpen }}" id="sidebarB2b">
                        <ul class="sub-menu">
                            @if(permission(8, 'View'))
                            <li class="side-nav-item {{ saActive(['superadmin.renewals.*']) }}">
                                <a href="{{ route('superadmin.renewals.index') }}" class="side-nav-link {{ saLink(['superadmin.renewals.*']) }}">
                                    <span class="menu-text">Renewals</span>
                                </a>
                            </li>
                            @endif
                           
                            {{-- <li class="side-nav-item {{ saActive(['superadmin.domaintransferrequests.*']) }}">
                                <a href="{{ route('superadmin.domaintransferrequests') }}" class="side-nav-link {{ saLink(['superadmin.domaintransferrequests.*']) }}">
                                    <span class="menu-text">Transfer Request</span>
                                </a>
                            </li> --}}
                        </ul>
                    </div>
                </li>
                @endif

                {{-- @if(permission(9, 'View') || permission(10, 'View'))  --}}
                {{-- Assuming '9' is the module ID for B2C Customers --}}
                {{-- <li class="side-nav-title mt-3">Commerce</li> --}}

                 @if(permission(9, 'View')) {{-- Assuming '9' is the module ID for B2C Customers --}}
                    @php $commerceOpen = saOpen(['superadmin.b2c_customers.*']); @endphp
                    <li class="side-nav-item {{ $commerceOpen ? 'menuitem-active' : '' }}">
                        <a href="#sidebarB2c" data-bs-toggle="collapse"
                        aria-expanded="{{ $commerceOpen ? 'true' : 'false' }}"
                        class="side-nav-link {{ $commerceOpen ? '' : 'collapsed' }}">
                            <span class="menu-icon"><i class="ti ti-users"></i></span>
                            <span class="menu-text">B2C</span>
                            <span class="menu-arrow"></span>
                        </a>
                        <div class="collapse {{ $commerceOpen }}" id="sidebarB2c">
                            <ul class="sub-menu">
                                <li class="side-nav-item {{ saActive(['superadmin.b2c_customers.*']) }}">
                                    <a href="{{ route('superadmin.b2c_customers.list') }}" class="side-nav-link {{ saLink(['superadmin.b2c_customers.*']) }}">
                                        <span class="menu-text">Customers</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    @endif
                {{-- @endif --}}
                @if(permission(28, 'View'))
                @php
                    $transactionsOpen = (
                        saOpen(['superadmin.b2b_billing.*', 'superadmin.partner_invoices.*', 'superadmin.credit_requests.*', 'superadmin.license_decrease_requests.*', 'superadmin.refunds.*', 'superadmin.promostions_coupens.*', 'superadmin.promotions_coupons.*']) !== ''
                        || str_contains($currentSaPath, 'superadmin/b2b-billing')
                        || str_contains($currentSaPath, 'superadmin/promotions-coupens')
                        || str_contains($currentSaPath, 'superadmin/partner_invoices')
                        || str_contains($currentSaPath, 'superadmin/credit-requests')
                        || str_contains($currentSaPath, 'superadmin/license-decrease-requests')
                        || str_contains($currentSaPath, 'superadmin/refunds')
                        || str_contains($currentSaPath, 'superadmin/purchase-requests')
                    ) ? 'show' : '';
                @endphp
                    <li class="side-nav-item {{ $transactionsOpen ? 'menuitem-active' : '' }}">
                        <a href="#sidebarTransactions" data-bs-toggle="collapse"
                        aria-expanded="{{ $transactionsOpen ? 'true' : 'false' }}"
                        class="side-nav-link {{ $transactionsOpen ? '' : 'collapsed' }}">
                            <span class="menu-icon"><i class="ti ti-credit-card"></i></span>
                            <span class="menu-text">Transactions</span>
                            <span class="menu-arrow"></span>
                        </a>
                        <div class="collapse {{ $transactionsOpen }}" id="sidebarTransactions">
                            <ul class="sub-menu">
                                @if(permission(28, 'View'))
                                <li class="side-nav-item {{ (saActive(['superadmin.purchase_requests.*']) !== '' || str_contains($currentSaPath, 'superadmin/purchase-requests')) ? 'menuitem-active' : '' }}">
                                    <a href="{{ route('superadmin.purchase_requests.index') }}" class="side-nav-link {{ (saLink(['superadmin.purchase_requests.*']) !== '' || str_contains($currentSaPath, 'superadmin/purchase-requests')) ? 'active' : '' }}">
                                        <span class="menu-text">Purchase Requests</span>
                                    </a>
                                </li>
                                @endif
                                @if(permission(11, 'View'))
                                <li class="side-nav-item {{ (saActive(['superadmin.b2b_billing.*']) !== '' || str_contains($currentSaPath, 'superadmin/b2b-billing')) ? 'menuitem-active' : '' }}">
                                    <a href="{{ route('superadmin.b2b_billing.index') }}" class="side-nav-link {{ (saLink(['superadmin.b2b_billing.*']) !== '' || str_contains($currentSaPath, 'superadmin/b2b-billing')) ? 'active' : '' }}">
                                        <span class="menu-text">Billing & invoices</span>
                                    </a>
                                </li>
                                @endif
                                @if(permission(12, 'View'))
                                <li class="side-nav-item {{ (saActive(['superadmin.promostions_coupens.*', 'superadmin.promotions_coupons.*']) !== '' || str_contains($currentSaPath, 'superadmin/promotions-coupens')) ? 'menuitem-active' : '' }}">
                                    <a href="{{ route('superadmin.promostions_coupens.index') }}" class="side-nav-link {{ ((saLink(['superadmin.promostions_coupens.*', 'superadmin.promotions_coupons.*']) !== '') || str_contains($currentSaPath, 'superadmin/promotions-coupens')) ? 'active' : '' }}">
                                        <span class="menu-text">Promocode</span>
                                    </a>
                                </li>
                                @endif
                                @if(permission(13, 'View'))
                                <li class="side-nav-item {{ (saActive(['superadmin.partner_invoices.*']) !== '' || str_contains($currentSaPath, 'superadmin/partner_invoices')) ? 'menuitem-active' : '' }}">
                                    <a href="{{ route('superadmin.partner_invoices.index') }}" class="side-nav-link {{ (saLink(['superadmin.partner_invoices.*']) !== '' || str_contains($currentSaPath, 'superadmin/partner_invoices')) ? 'active' : '' }}">
                                        <span class="menu-text">Invoice management</span>
                                    </a>
                                </li>
                                @endif
                                @if(permission(14, 'View'))
                                {{-- @if(permission(10, 'View')) --}}
                                <li class="side-nav-item {{ (saActive(['superadmin.credit_requests.*']) !== '' || str_contains($currentSaPath, 'superadmin/credit-requests')) ? 'menuitem-active' : '' }}">
                                    <a href="{{ route('superadmin.credit_requests.index') }}" class="side-nav-link {{ (saLink(['superadmin.credit_requests.*']) !== '' || str_contains($currentSaPath, 'superadmin/credit-requests')) ? 'active' : '' }}">
                                        <span class="menu-text">Credit requests</span>
                                        @if($creditRequestsPending > 0)
                                            <span class="badge bg-warning text-dark ms-1">{{ $creditRequestsPending }}</span>
                                        @endif
                                    </a>
                                </li>
                                @endif
                                @if(permission(15, 'View')) 
                                {{-- <li class="side-nav-item {{ (saActive(['superadmin.license_decrease_requests.*']) !== '' || str_contains($currentSaPath, 'superadmin/license-decrease-requests')) ? 'menuitem-active' : '' }}">
                                    <a href="{{ route('superadmin.license_decrease_requests.index') }}" class="side-nav-link {{ (saLink(['superadmin.license_decrease_requests.*']) !== '' || str_contains($currentSaPath, 'superadmin/license-decrease-requests')) ? 'active' : '' }}">
                                        <span class="menu-text">License decrease</span>
                                        @if(($licenseDecreasePending ?? 0) > 0)
                                            <span class="badge bg-warning text-dark ms-1">{{ $licenseDecreasePending }}</span>
                                        @endif
                                    </a>
                                </li> --}}
                                @endif
                                @if(permission(16, 'View'))
                                <li class="side-nav-item {{ (saActive(['superadmin.refunds.*']) !== '' || str_contains($currentSaPath, 'superadmin/refunds')) ? 'menuitem-active' : '' }}">
                                    <a href="{{ route('superadmin.refunds.index') }}" class="side-nav-link {{ (saLink(['superadmin.refunds.*']) !== '' || str_contains($currentSaPath, 'superadmin/refunds')) ? 'active' : '' }}">
                                        <span class="menu-text">Refunds</span>
                                    </a>
                                </li>
                                @endif
                            </ul>
                        </div>
                    </li>
                @endif
                {{-- <li class="side-nav-title mt-3">Operations</li>
                <li class="side-nav-item {{ saActive(['superadmin.provisioning.*']) }}">
                    <a href="{{ route('superadmin.provisioning.index') }}" class="side-nav-link {{ saLink(['superadmin.provisioning.*']) }}">
                    <a href="#" class="side-nav-link {{ saLink(['superadmin.provisioning.*']) }}">
                        <span class="menu-icon"><i class="ti ti-server"></i></span>
                        <span class="menu-text">Provisioning</span>
                    </a>
                </li>
                <li class="side-nav-item {{ saActive(['superadmin.notifications.*']) }}">
                    <a href="#" class="side-nav-link {{ saLink(['superadmin.notifications.*']) }}">
                        <span class="menu-icon"><i class="ti ti-bell"></i></span>
                        <span class="menu-text">Notifications</span>
                    </a>
                </li> --}}
                @if(permission(17, 'View') || permission(18, 'View') || permission(19, 'View') || permission(20, 'View'))
                    @php
                        $userManagementActive = (
                            saActive(['user-creation-list', 'add-user-creation']) !== '' ||
                            saActive(['authority-table', 'add-authority']) !== '' ||
                            saActive(['module-mst-table*', 'add-module-mst*']) !== '' ||
                            saActive(['superadmin.partner_logs.*']) !== '' ||
                            str_contains($currentSaPath, 'superadmin/partner-logs')
                        );
                    @endphp
                    <li class="side-nav-item {{ $userManagementActive ? 'menuitem-active' : '' }}">
                        <a href="#sidebarUserManagement" 
                           data-bs-toggle="collapse"
                           aria-expanded="{{ $userManagementActive ? 'true' : 'false' }}"
                           class="side-nav-link {{ $userManagementActive ? '' : 'collapsed' }}">
                            <span class="menu-icon"><i class="ti ti-users"></i></span>
                            <span class="menu-text">User Management</span>
                            <span class="menu-arrow"></span>
                        </a>
                        <div class="collapse {{ $userManagementActive ? 'show' : '' }}" id="sidebarUserManagement">
                            <ul class="sub-menu">
                                @if(permission(17, 'View'))
                                <li class="side-nav-item {{ saActive(['superadmin.user-creation-list', 'add-user-creation']) || str_contains($currentSaPath, 'superadmin/user-creation-list') ? 'menuitem-active' : '' }}">
                                    <a href="{{ url('superadmin/user-creation-list') }}" class="side-nav-link {{ saLink(['superadmin.user-creation-list']) }}">
                                        <span class="menu-text">Users</span>
                                    </a>
                                </li>
                                @endif
                                @if(permission(18, 'View'))
                                <li class="side-nav-item {{ saActive(['superadmin.authority-table', 'superadmin.add-authority']) }}">
                                    <a href="{{ url('superadmin/authority-table') }}" class="side-nav-link {{ saLink(['superadmin.authority-table']) }}">
                                        <span class="menu-text">Permissions</span>
                                    </a>
                                </li>
                                @endif
                                @if(permission(19, 'View'))
                                <li class="side-nav-item {{ saActive(['superadmin.module-mst-table*', 'superadmin.add-module-mst*']) }}">
                                    <a href="{{ url('superadmin/module-mst-table') }}" class="side-nav-link {{ saLink(['superadmin.module-mst-table*', 'superadmin.add-module-mst*']) }}">
                                        <span class="menu-text">Module</span>
                                    </a>
                                </li>
                                @endif
                                @if(permission(20, 'View'))
                                <li class="side-nav-item {{ saActive(['superadmin.partner_logs.*']) || str_contains($currentSaPath, 'superadmin/partner-logs') ? 'menuitem-active' : '' }}">
                                    <a href="{{ route('superadmin.partner_logs.index') }}" class="side-nav-link {{ saLink(['superadmin.partner_logs.*']) }}">
                                        <span class="menu-text">Partner Login Logs</span>
                                    </a>
                                </li>
                                @endif
                            </ul>
                        </div>
                    </li>
                @endif
                @if(permission(21, 'Add') || permission(22, 'View') || permission(23, 'View'))
                    @php
                        $path = '/' . trim(request()->path(), '/');
                        $isPlansPath = str_contains($path, '/superadmin/plans');
                        $isProvidersPath = str_contains($path, '/superadmin/providers');
                        $masterOpen = (saOpen(['superadmin.providers.*', 'superadmin.plans.*']) !== '' || $isProvidersPath || $isPlansPath) ? 'show' : '';
                        $addPlanActive = (saActive(['superadmin.plans.*']) !== '' || $isPlansPath) ? 'menuitem-active' : '';
                        $addPlanLinkActive = (saLink(['superadmin.plans.*']) !== '' || $isPlansPath) ? 'active' : '';
                        $providerActive = (saActive(['superadmin.providers.*']) !== '' || $isProvidersPath) ? 'menuitem-active' : '';
                        $providerLinkActive = (saLink(['superadmin.providers.*']) !== '' || $isProvidersPath) ? 'active' : '';
                    @endphp
                    <li class="side-nav-item {{ $masterOpen ? 'menuitem-active' : '' }}">
                        <a href="#sidebarMaster" data-bs-toggle="collapse"
                           aria-expanded="{{ $masterOpen ? 'true' : 'false' }}"
                           class="side-nav-link {{ $masterOpen ? '' : 'collapsed' }}">
                            <span class="menu-icon"><i class="ti ti-layout-grid"></i></span>
                            <span class="menu-text">Master</span>
                            <span class="menu-arrow"></span>
                        </a>
                        <div class="collapse {{ $masterOpen }}" id="sidebarMaster">
                            <ul class="sub-menu">
                                @if(permission(21, 'Add'))
                                <li class="side-nav-item {{ $addPlanActive }}">
                                    <a href="{{ route('superadmin.plans.add_selector') }}" class="side-nav-link {{ $addPlanLinkActive }}">
                                        <span class="menu-text">Add Plan</span>
                                    </a>
                                </li>
                                @endif
                                @if(permission(22, 'View'))
                                <li class="side-nav-item {{ $providerActive }}">
                                    <a href="{{ route('superadmin.providers.index') }}" class="side-nav-link {{ $providerLinkActive }}">
                                        <span class="menu-text">Provider</span>
                                    </a>
                                </li>
                                @endif
                                @if(permission(23, 'View'))
                                <li class="side-nav-item {{ saActive(['superadmin.apps.*']) }}">
                                    <a href="{{ route('superadmin.apps.index') }}" class="side-nav-link {{ saLink(['superadmin.apps.*']) }}">
                                        <span class="menu-icon"><i class="ti ti-apps"></i></span>
                                        <span class="menu-text">Popular Apps</span>
                                    </a>
                                </li>
                                @endif
                            </ul>
                        </div>
                    </li>
                @endif
                    
                {{-- @if(permission(5, 'View') || permission(6, 'View')) --}}
                {{-- <li class="side-nav-title mt-3">Masters</li> --}}
                   
                    {{-- @if(permission(6, 'View'))
                        <li class="side-nav-item {{ saActive(['superadmin.categories.*']) }}">
                            <a href="{{ route('superadmin.categories.index') }}" class="side-nav-link {{ saLink(['superadmin.categories.*']) }}">
                                <span class="menu-icon"><i class="ti ti-category-2"></i></span>
                                <span class="menu-text">Plan Categories</span>
                            </a>
                        </li>
                    @endif --}}
                {{-- @endif --}}

                {{-- <li class="side-nav-item {{ saActive(['superadmin.support.*']) }}">
                    <a href="{{ route('superadmin.support.index') }}" class="side-nav-link {{ saLink(['superadmin.support.*']) }}">
                        <span class="menu-icon"><i class="ti ti-headset"></i></span>
                        <span class="menu-text">Support</span>
                    </a>
                </li> --}}

                @if(permission(24, 'View') || permission(25, 'View'))
                    @php
                        $path = '/' . trim(request()->path(), '/');
                        $isTicketList = str_contains($path, '/superadmin/support/tickets')
                            && request()->query('filter') !== 'unassigned';
                        $isUnassigned = str_contains($path, '/superadmin/support/tickets')
                            && request()->query('filter') === 'unassigned';
                        $isTicketDetails = str_contains($path, '/superadmin/support/details');
                        $isNewTicket = str_contains($path, '/superadmin/support/create');
                        $isLiveChat = str_contains($path, '/superadmin/support/chats');
                        $isAgentReport = str_contains($path, '/superadmin/support/report');

                        $supportOpen = ($isTicketList || $isUnassigned || $isTicketDetails || $isNewTicket || $isLiveChat || $isAgentReport) ? 'show' : '';
                        $ticketListActive = $isTicketList ? 'menuitem-active' : '';
                        $ticketListLinkActive = $isTicketList ? 'active' : '';
                        $unassignedActive = $isUnassigned ? 'menuitem-active' : '';
                        $unassignedLinkActive = $isUnassigned ? 'active' : '';
                        $ticketDetailsActive = $isTicketDetails ? 'menuitem-active' : '';
                        $ticketDetailsLinkActive = $isTicketDetails ? 'active' : '';
                        $newTicketActive = $isNewTicket ? 'menuitem-active' : '';
                        $newTicketLinkActive = $isNewTicket ? 'active' : '';
                    @endphp
                    <li class="side-nav-item {{ $supportOpen ? 'menuitem-active' : '' }}">
                        <a href="#sidebarSupport" data-bs-toggle="collapse"
                           aria-expanded="{{ $supportOpen ? 'true' : 'false' }}"
                           class="side-nav-link {{ $supportOpen ? '' : 'collapsed' }}">
                            <span class="menu-icon"><i class="ti ti-headset"></i></span>
                            <span class="menu-text">Support Center</span>
                            <span class="menu-arrow"></span>
                        </a>
                        <div class="collapse {{ $supportOpen }}" id="sidebarSupport">
                            <ul class="sub-menu">
                                {{-- NOTE: Changed support links to use url() NOT route(), to fix RouteNotFoundException --}}
                                {{-- Ticket List link - checks correct URL for superadmin support/tickets --}}
                                @if(permission(24, 'View'))
                                <li class="side-nav-item {{ $ticketListActive }}">
                                    <a href="{{ url('/superadmin/support/tickets') }}" class="side-nav-link {{ $ticketListLinkActive }}">
                                        <span class="menu-text">Ticket List</span>
                                    </a>
                                </li>
                                @endif
                                {{-- @if(permission(29, 'View'))
                                <li class="side-nav-item {{ $unassignedActive }}">
                                    <a href="{{ url('/superadmin/support/tickets?filter=unassigned') }}" class="side-nav-link {{ $unassignedLinkActive }}">
                                        <span class="menu-text">Assign Ticket</span>
                                    </a>
                                </li>
                                @endif --}}
                                @if(permission(25, 'View'))
                                <li class="side-nav-item {{ $newTicketActive }}">
                                    <a href="{{ url('superadmin/support/create') }}" class="side-nav-link {{ $newTicketLinkActive }}">
                                        <span class="menu-text fw-bold">New Ticket</span>
                                    </a>
                                </li>
                                @endif
                                @if(permission(24, 'View'))
                                <li class="side-nav-item {{ $isLiveChat ? 'menuitem-active' : '' }}">
                                    <a href="{{ url('superadmin/support/chats') }}" class="side-nav-link {{ $isLiveChat ? 'active' : '' }}">
                                        <span class="menu-text">Live Chat</span>
                                    </a>
                                </li>
                                <li class="side-nav-item {{ $isAgentReport ? 'menuitem-active' : '' }}">
                                    <a href="{{ url('superadmin/support/report') }}" class="side-nav-link {{ $isAgentReport ? 'active' : '' }}">
                                        <span class="menu-text">Support User Report</span>
                                    </a>
                                </li>
                                @endif
                            </ul>
                        </div>
                    </li>
                    @if(permission(27, 'View'))
                    <li class="side-nav-item {{ saActive(['superadmin.settings.*']) }}">
                        <a href="{{ route('superadmin.settings.index') }}" class="side-nav-link {{ saLink(['superadmin.settings.*']) }}">
                            <span class="menu-icon"><i class="ti ti-settings"></i></span>
                            <span class="menu-text">Settings</span>
                        </a>
                    </li>
                @endif
                @endif
                @php
                    $crmOpen = (
                        saOpen([
                            'crm.dashboard',
                            'crm.leads.*',
                            'crm.follow_up.*',
                            'crm.quotations.*',
                            'crm.renewals.*',
                            'crm.manage.*',
                            'crm.reports.*',
                        ]) !== ''
                        || str_contains($currentSaPath, '/crm/')
                    );
            @endphp
            @if(permission(26, 'View'))
                <li class="side-nav-item {{ saActive(['crm.dashboard']) }}">
                    <a href="{{ route('crm.dashboard') }}" class="side-nav-link {{ saLink(['crm.dashboard']) }}">
                        <span class="menu-icon"><i class="ti ti-layout-dashboard"></i></span>
                        <span class="menu-text">Crm Dashboard</span>
                    </a>
                </li>
            @endif
            <li class="side-nav-item">
                <a href="{{ route('superadmin.logout') }}" class="side-nav-link">
                    <span class="menu-icon"><i class="ti ti-logout"></i></span>
                    <span class="menu-text">Logout</span>
                </a>
            </li>
        </ul>
    </div>
</li>
            {{-- @php
                $crmOpen = saOpen([
                    'crm.dashboard',
                    'crm.leads.*',
                    'crm.follow_up.*',
                    'crm.quotations.*',
                    'crm.renewals.*',
                    'crm.manage.*',
                    'crm.reports.*',
                ]) !== '' || str_contains($currentSaPath, '/crm/');
            @endphp --}}
            {{-- <li class="side-nav-item {{ $crmOpen ? 'menuitem-active' : '' }}">
                <a href="#sidebarCrm" data-bs-toggle="collapse"
                aria-expanded="{{ $crmOpen ? 'true' : 'false' }}"
                class="side-nav-link {{ $crmOpen ? '' : 'collapsed' }}">
                    <span class="menu-icon"><i class="ti ti-briefcase"></i></span>
                    <span class="menu-text">CRM</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse {{ $crmOpen ? 'show' : '' }}" id="sidebarCrm">
                    <ul class="sub-menu">
                        <li class="side-nav-item {{ saActive(['crm.dashboard']) }}">
                            <a href="{{ route('crm.dashboard') }}" class="side-nav-link {{ saLink(['crm.dashboard']) }}">
                                <span class="menu-icon"><i class="ti ti-layout-dashboard"></i></span>
                                <span class="menu-text">Dashboard</span>
                            </a>
                        </li>
                        <li class="side-nav-item {{ str_contains($currentSaPath, '/crm/leads') ? 'menuitem-active' : '' }}">
                            <a href="{{ url('/crm/leads') }}" class="side-nav-link {{ str_contains($currentSaPath, '/crm/leads') ? 'active' : '' }}">
                                <span class="menu-icon"><i class="ti ti-users"></i></span>
                                <span class="menu-text">Leads</span>
                            </a>
                        </li>
                        <li class="side-nav-item {{ str_contains($currentSaPath, '/crm/follow-up') ? 'menuitem-active' : '' }}">
                            <a href="{{ url('/crm/follow-up') }}" class="side-nav-link {{ str_contains($currentSaPath, '/crm/follow-up') ? 'active' : '' }}">
                                <span class="menu-icon"><i class="ti ti-phone-call"></i></span>
                                <span class="menu-text">Follow Up</span>
                            </a>
                        </li>
                        <li class="side-nav-item {{ str_contains($currentSaPath, '/crm/quotations') ? 'menuitem-active' : '' }}">
                            <a href="{{ url('/crm/quotations') }}" class="side-nav-link {{ str_contains($currentSaPath, '/crm/quotations') ? 'active' : '' }}">
                                <span class="menu-icon"><i class="ti ti-file-invoice"></i></span>
                                <span class="menu-text">Quotations</span>
                            </a>
                        </li>
                        <li class="side-nav-item {{ str_contains($currentSaPath, '/crm/renewals') ? 'menuitem-active' : '' }}">
                            <a href="{{ url('/crm/renewals') }}" class="side-nav-link {{ str_contains($currentSaPath, '/crm/renewals') ? 'active' : '' }}">
                                <span class="menu-icon"><i class="ti ti-refresh"></i></span>
                                <span class="menu-text">Renewals</span>
                            </a>
                        </li>
                        <li class="side-nav-item {{ str_contains($currentSaPath, '/crm/manage') ? 'menuitem-active' : '' }}">
                            <a href="{{ url('/crm/manage') }}" class="side-nav-link {{ str_contains($currentSaPath, '/crm/manage') ? 'active' : '' }}">
                                <span class="menu-icon"><i class="ti ti-settings"></i></span>
                                <span class="menu-text">Manage</span>
                                <span class="menu-arrow"></span>
                            </a>
                        </li>
                        <li class="side-nav-item {{ str_contains($currentSaPath, '/crm/reports') ? 'menuitem-active' : '' }}">
                            <a href="{{ url('/crm/reports') }}" class="side-nav-link {{ str_contains($currentSaPath, '/crm/reports') ? 'active' : '' }}">
                                <span class="menu-icon"><i class="ti ti-report-analytics"></i></span>
                                <span class="menu-text">Reports</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </li>


            <!-- Logout -->
            <li class="side-nav-item">
                <a href="{{ route('superadmin.logout') }}" class="side-nav-link">
                    <span class="menu-icon"><i class="ti ti-logout"></i></span>
                    <span class="menu-text">Logout</span>
                </a>
            </li>

            </ul> --}}
            
        </div>
    </div>
</div>
