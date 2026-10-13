@extends('superadmin.layouts.crm')

@section('content')
@php
$searchQuery = request('search');
$statusQuery = request('status');
$roleQuery = request('role');
$fromQuery = request('from');
$toQuery = request('to');

$totalCount = isset($totalEmployees) ? $totalEmployees : ($employees ? $employees->total() : 0);
$activeCount = isset($activeEmployees) ? $activeEmployees : 0;
$leadsAssignedCount = isset($totalLeadsAssigned) ? $totalLeadsAssigned : 0;
$pendingTasksCount = isset($pendingTasks) ? $pendingTasks : 0;

$roleTitles = [
'super_admin' => 'Super Admin',
'admin' => 'Admin (Admin)',
'manager' => 'Manager',
'executive' => 'Sales Executive',
'marketing_manager' => 'Marketing Manager',
'product_designer' => 'Product Designer',
'customer_support' => 'Customer Support',
];

$avatarColors = ['purple', 'blue', 'teal', 'orange', 'green'];
@endphp
<div class="crm-emp-shell">
    <!-- Left Sub Navigation -->
    @include('superadmin.crm.partials.employee-navigation', ['active' => request('tab') === 'permissions' ? 'permissions' : 'employee'])

    @if (request('tab') === 'permissions')
        @php
            $selectedEmployeeId = request('employee_id', optional($employees->first())->id);
            $permissionGroups = [
                'Leads' => ['Leads', 'JD Leads', 'Exhibition Leads', 'Import Leads'],
                'Follow Up' => ['Follow Ups', 'Meetings', 'Tasks / Reminders'],
                'Quotations' => ['Quotations', 'Discount Approval', 'Create Quotations'],
                'Renewals' => ['Renewals', 'Renewal Reminders'],
                'Manage' => ['Employees', 'Team Assignment', 'Employee Permissions', 'Services & Providers', 'CRM Settings'],
            ];
        @endphp
        <div class="employee-permissions-page">
            <style>
                .employee-permissions-page { min-width: 0; }
                .employee-permissions-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
                .employee-permissions-heading h2 { margin: 0; color: #26364a; font-size: 23px; }
                .employee-permissions-heading p { margin: 0 0 6px; color: #7b8797; font-size: 12px; }
                .employee-permissions-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 14px; }
                .employee-permissions-toolbar label { color: #566477; font-size: 13px; font-weight: 600; }
                .employee-permissions-toolbar select { min-width: 220px; padding: 9px 12px; border: 1px solid #dce3eb; border-radius: 5px; background: #fff; color: #26364a; }
                .employee-permissions-table-wrap { overflow-x: auto; border: 1px solid #e7ebf0; border-radius: 6px; background: #fff; }
                .employee-permissions-table { width: 100%; min-width: 620px; border-collapse: collapse; }
                .employee-permissions-table th, .employee-permissions-table td { padding: 10px 14px; border-bottom: 1px solid #edf0f3; text-align: center; font-size: 12px; }
                .employee-permissions-table thead th { height: 42px; color: #536174; font-weight: 600; }
                .employee-permissions-table th:first-child, .employee-permissions-table td:first-child { width: 46%; text-align: left; }
                .employee-permissions-table .permission-group th { padding-top: 12px; padding-bottom: 7px; border-bottom: 0; }
                .employee-permissions-table .permission-group span { display: inline-block; padding: 4px 8px; border-radius: 4px; background: #eaf2fc; color: #315e99; font-size: 12px; font-weight: 700; }
                .employee-permissions-table .permission-module { color: #455266; font-weight: 500; }
                .employee-permissions-table input { width: 14px; height: 14px; accent-color: #0866b5; vertical-align: middle; }
                .employee-permissions-back { display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; border: 1px solid #dce3eb; border-radius: 5px; background: #fff; color: #455266; font-size: 12px; text-decoration: none; }
                @media (max-width: 680px) {
                    .employee-permissions-heading, .employee-permissions-toolbar { align-items: stretch; flex-direction: column; }
                    .employee-permissions-toolbar select { width: 100%; }
                }
            </style>

            <div class="employee-permissions-heading">
                <div>
                    <p><a href="{{ Route::has('crm.dashboard') ? route('crm.dashboard') : '#' }}">Dashboard</a> / Manage / Employee Management / Permissions</p>
                    <h2>Employee Permissions</h2>
                </div>
                <a href="{{ route('crm.employees') }}" class="employee-permissions-back">
                    <i data-lucide="arrow-left"></i> Back
                </a>
            </div>

            <div class="employee-permissions-toolbar">
                <label for="permissionEmployee">Employee</label>
                <form method="GET" action="{{ route('crm.employees') }}">
                    <input type="hidden" name="tab" value="permissions">
                    <select id="permissionEmployee" name="employee_id" onchange="this.form.submit()">
                        @foreach ($employees as $employeeOption)
                            <option value="{{ $employeeOption->id }}" {{ (string) $selectedEmployeeId === (string) $employeeOption->id ? 'selected' : '' }}>
                                {{ $employeeOption->name }}
                            </option>
                        @endforeach
                    </select>
                </form>
            </div>

            <div class="employee-permissions-table-wrap">
                <table class="employee-permissions-table">
                    <thead>
                        <tr>
                            <th scope="col">Module</th>
                            <th scope="col">View</th>
                            <th scope="col">Add</th>
                            <th scope="col">Edit</th>
                            <th scope="col">Delete</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($permissionGroups as $group => $modules)
                            <tr class="permission-group"><th colspan="5" scope="colgroup"><span>{{ $group }}</span></th></tr>
                            @foreach ($modules as $module)
                                <tr>
                                    <td class="permission-module"><label><input type="checkbox"> {{ $module }}</label></td>
                                    @foreach (['View', 'Add', 'Edit', 'Delete'] as $action)
                                        <td><input type="checkbox" checked aria-label="{{ $action }} {{ $module }}"></td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
    <div>
        <div class="page-header">
            <div class="">
                <div class="breadcrumb welcome-text">
                    <p>
                        <a href="{{ Route::has('crm.dashboard') ? route('crm.dashboard') : '#' }}">Dashboard</a>
                        <span>/</span>
                        <a href="javascript:void(0)">Manage</a>
                        <span>/</span>
                        <a href="javascript:void(0)">Employee Management</a>
                        <span>/</span>
                        Employee
                    </p>
                </div>
                <h2>Employee</h2>
            </div>
            <div class="crm-actions">
                <a href="{{ Route::has('crm.employee.create') ? route('crm.employee.create') : '#' }}"
                    class="crm-action-btn active">
                    <i data-lucide="user-round-plus"></i>
                    <span>Add Employee</span>
                </a>
            </div>
        </div>

        <div class="summary-section">
            <div class="common-table-header">
                <div class="summary-header">
                    <h3>Account Summary</h3>
                    <span>(As on {{ $summaryDate ?? now()->format('d M, Y') }})</span>
                </div>
                <form method="GET" action="{{ route('crm.employees') }}" class="date-range" id="dateFilterForm"
                    style="margin: 0;">
                    @if (request('search'))
                    <input type="hidden" name="search" value="{{ request('search') }}">
                    @endif
                    @if (request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                    @endif
                    @if (request('role'))
                    <input type="hidden" name="role" value="{{ request('role') }}">
                    @endif
                    <div class="date-range">
                        <div class="date-input">
                            <input type="date" name="from" value="{{ $fromQuery }}" onchange="this.form.submit()">
                            <i data-lucide="calendar"></i>
                        </div>
                        <div class="date-input">
                            <input type="date" name="to" value="{{ $toQuery }}" onchange="this.form.submit()">
                            <i data-lucide="calendar"></i>
                        </div>
                    </div>
                </form>
            </div>
            <div class="summary-grid stats-grid-4">
                <div class="summary-card blue">
                    <div class="card-icon">
                        <i data-lucide="user"></i>
                    </div>
                    <p>Total Employee</p>
                    <h3>{{ $totalCount }}</h3>
                </div>
                <div class="summary-card green">
                    <div class="card-icon">
                        <i data-lucide="user-check"></i>
                    </div>
                    <p>Active Employee</p>
                    <h3>{{ $activeCount }}</h3>
                </div>
                <div class="summary-card purple">
                    <div class="card-icon">
                        <i data-lucide="calendar-check"></i>
                    </div>
                    <p>Total Leads Assigned</p>
                    <h3>{{ $leadsAssignedCount }}</h3>
                </div>
                <div class="summary-card ">
                    <div class="card-icon orange">
                        <i data-lucide="list-todo"></i>
                    </div>
                    <p>Pending Tasks</p>
                    <h3>{{ $pendingTasksCount }}</h3>
                </div>
            </div>
        </div>

        @if (session('success'))
        <div class="alert alert-success"
            style="padding: 12px 20px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; border-radius: 8px; margin-bottom: 16px; font-size: 13.5px;">
            {{ session('success') }}
        </div>
        @endif

        @if (session('error'))
        <div class="alert alert-danger"
            style="padding: 12px 20px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 8px; margin-bottom: 16px; font-size: 13.5px;">
            {{ session('error') }}
        </div>
        @endif

        <section class="common-table-section">
            <div class="common-table-card">
                <div class="common-table-header crm-lead-header">
                    <div class="crm-lead-title">
                        <p>
                            @if ($employees && $employees->total() > 0)
                            Showing <strong>{{ $employees->firstItem() }} - {{ $employees->lastItem() }}</strong> from
                            <strong>{{ $employees->total() }}</strong> Employee
                            @else
                            Showing <strong>0</strong> Employee
                            @endif
                        </p>
                    </div>

                    <form method="GET" action="{{ route('crm.employees') }}" class="table-search">
                        @if (request('status'))
                        <input type="hidden" name="status" value="{{ request('status') }}">
                        @endif
                        @if (request('role'))
                        <input type="hidden" name="role" value="{{ request('role') }}">
                        @endif
                        @if (request('from'))
                        <input type="hidden" name="from" value="{{ request('from') }}">
                        @endif
                        @if (request('to'))
                        <input type="hidden" name="to" value="{{ request('to') }}">
                        @endif
                        <input type="text" id="employeeSearch" name="search" value="{{ $searchQuery }}"
                            placeholder="Search Employee...">
                        <i data-lucide="search"></i>
                    </form>
                </div>

                <form method="GET" action="{{ route('crm.employees') }}" id="filterForm">
                    @if (request('search'))
                    <input type="hidden" name="search" value="{{ request('search') }}">
                    @endif
                    @if (request('from'))
                    <input type="hidden" name="from" value="{{ request('from') }}">
                    @endif
                    @if (request('to'))
                    <input type="hidden" name="to" value="{{ request('to') }}">
                    @endif
                    <div class="table-filter-wrap{{ request('status') || request('role') ? ' active' : '' }}" id="filterPanel">
                        <div class="filter-grid">
                            <div class="filter-group">
                                <h6>Status :</h6>
                                <div class="filter-options">
                                    <select name="status" onchange="this.form.submit()">
                                        <option value="">All</option>
                                        <option value="active" {{ strtolower($statusQuery) === 'active' ? 'selected' : '' }}>
                                            Active</option>
                                        <option value="inactive"
                                            {{ strtolower($statusQuery) === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                    </select>
                                </div>
                            </div>

                            <div class="filter-group">
                                <h6>Role :</h6>
                                <div class="filter-options">
                                    <select name="role" onchange="this.form.submit()">
                                        <option value="">All Roles</option>
                                        @foreach ($roleTitles as $val => $label)
                                        <option value="{{ $val }}"
                                            {{ strtolower($roleQuery) === strtolower($val) ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="filter-group">
                                <h6>Actions :</h6>
                                <div>
                                    <a href="{{ route('crm.employees') }}" class="btn"
                                        style="padding: 6px 14px; font-size: 12px; background: #e2e8f0; border-radius: 6px; color: #475569; text-decoration: none; display: inline-block;">Clear
                                        Filters</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-filter">
                        <div class="border-line"></div>
                        <button class="filter-btn" id="toggleFilter" type="button">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="9" viewBox="0 0 12 9"
                                fill="none">
                                <path d="M0.75 0.75H10.75M2.75 4.25H8.75M4.25 7.75H7.25" stroke="white" stroke-width="1.5"
                                    stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <span>Filters</span>
                        </button>
                    </div>
                </form>

                <table>
                    <thead>
                        <tr class="table-head">
                            <td>Id</td>
                            <td>Customer Information</td>
                            <td>Email</td>
                            <td>Phone</td>
                            <td class="status-col">Status</td>
                            <td style="text-align: center;">Action</td>
                        </tr>
                    </thead>

                    <tbody class="table-body">
                        @forelse ($employees as $item)
                        @php
                        $id = 'EMP' . str_pad($item->id, 4, '0', STR_PAD_LEFT);
                        $name = $item->name ?? '—';
                        $rawRole = $item->role ?? 'executive';
                        $role = $roleTitles[strtolower($rawRole)] ?? ucwords(str_replace('_', ' ', $rawRole));
                        $email = $item->email ?? '—';
                        $phone = $item->mobile ?? '—';
                        $status = $item->status ?? 'active';
                        $initial = strtoupper(substr(trim($name), 0, 1)) ?: 'E';
                        $colorIndex = crc32($name) % count($avatarColors);
                        $avatarClass = $avatarColors[abs($colorIndex)];
                        $isActive = strtolower($status) === 'active';
                        @endphp
                        <tr class="table-row order-row">
                            <td class="order-id">
                                <span>{{ $id }}</span>
                            </td>

                            <td class="customer">
                                @if ($item->avatar_url)
                                <img src="{{ $item->avatar_url }}" alt="{{ $name }}" class="avatar"
                                    style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover; flex-shrink: 0; display: inline-block;">
                                @else
                                <div class="avatar {{ $avatarClass }}">
                                    {{ $initial }}
                                </div>
                                @endif
                                <div>
                                    <h5><a href="{{ route('crm.employee.view', $item->id) }}">{{ $name }}</a></h5>
                                    <p>
                                        {{ $role }}
                                        @if (strtolower((string) $rawRole) === 'executive')
                                        · <a href="{{ route('crm.leads', ['assigned_to' => $name]) }}">{{ (int) ($item->leads_count ?? 0) }} Leads</a>
                                        @endif
                                    </p>
                                </div>
                            </td>

                            <td class="contact-info">
                                <div>
                                    <h5>{{ $email }}</h5>
                                </div>
                            </td>

                            <td class="contact-info">
                                <div>
                                    <p>{{ $phone }}</p>
                                </div>
                            </td>

                            <td class="status-col">
                                <span class="badge {{ $isActive ? 'active' : 'inactive' }}">
                                    {{ ucfirst($status) }}
                                </span>
                            </td>

                            <td>
                                <div class="actions">
                                    <a href="{{ Route::has('crm.employee.edit') ? route('crm.employee.edit', $item->id) : '#' }}"
                                        class="action-btn" title="Edit Employee">
                                        <i data-lucide="square-pen"></i>
                                    </a>
                                    <a href="{{ route('crm.employee.view', ['id' => $item->id]) }}" class="action-btn"
                                        title="View Employee Details">
                                        <i data-lucide="eye"></i>
                                    </a>
                                    <a href="{{ route('crm.employee.permissions', ['employee_id' => $item->id]) }}" class="action-btn"
                                        title="Employee Permissions">
                                        <i data-lucide="shield-check"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 48px 16px; color: #64748b;">
                                <div
                                    style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px;">
                                    <i data-lucide="users" style="width: 36px; height: 36px; color: #94a3b8;"></i>
                                    <p style="margin: 0; font-size: 14px; font-weight: 500;">No employees found.</p>
                                    <a href="{{ route('crm.employee.create') }}"
                                        style="color: #0056b3; font-size: 13px; font-weight: 600; text-decoration: underline;">Add
                                        New Employee</a>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>

                @if ($employees && method_exists($employees, 'hasPages') && $employees->hasPages())
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
            </div>
        </section>
    </div>
    @endif
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });
</script>
@endsection
