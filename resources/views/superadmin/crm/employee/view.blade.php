@extends('superadmin.layouts.crm')

@section('content')
@php
$emp = $employee ?? null;
$empName = $emp ? $emp->name : 'Employee';
$rawEmpRole = $emp ? ($emp->role ?? 'executive') : 'executive';
$roleTitles = [
'super_admin' => 'Super Admin',
'admin' => 'Admin (Admin)',
'manager' => 'Manager',
'executive' => 'Sales Executive',
'marketing_manager' => 'Marketing Manager',
'product_designer' => 'Product Designer',
'customer_support' => 'Customer Support',
];
$empRole = $roleTitles[strtolower($rawEmpRole)] ?? ucwords(str_replace('_', ' ', $rawEmpRole));
$empEmail = $emp ? ($emp->email ?? '—') : '—';
$empPhone = $emp ? ($emp->mobile ?? '—') : '—';
$empStatus = $emp ? ($emp->status ?? 'active') : 'active';
$empId = $emp ? $emp->id : 1;
$empAvatar = $emp ? ($emp->avatar_url ?? null) : null;
$initial = strtoupper(substr(trim($empName), 0, 1)) ?: 'E';
$isActive = strtolower($empStatus) === 'active';

$leadsCount = $totalLeads ?? 0;
$callsCount = $totalCalls ?? 0;
$dealsCount = $totalDeals ?? 0;
$conversionRate = $leadsCount > 0 ? round(($dealsCount / $leadsCount) * 100, 1) . '%' : '0%';
@endphp

<div class="crm-emp-shell">

    <!-- Left Sub Navigation -->
    @include('superadmin.crm.partials.employee-navigation', ['active' => 'employee'])

    <!-- Main Content -->
    <div>
        <!-- Page Header -->
        <div class="page-header"
            style="margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <div class="breadcrumb welcome-text">
                    <p>
                        <a href="{{ Route::has('crm.dashboard') ? route('crm.dashboard') : '#' }}">Dashboard</a>
                        <span>/</span>
                        <a href="javascript:void(0)">Manage</a>
                        <span>/</span>
                        <a href="javascript:void(0)">Employee Management</a>
                        <span>/</span>
                        <a href="{{ route('crm.employees') }}">Employee</a>
                    </p>
                </div>
                <h2>Employee Detail</h2>
            </div>
            <div class="back">
                <a href="{{ Route::has('crm.employees') ? route('crm.employees') : '#' }}" class="back-btn"
                    style="background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 16px; display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 500; color: #334155; text-decoration: none;">
                    <i data-lucide="arrow-left" style="width: 14px; height: 14px;"></i>
                    <span>Back</span>
                </a>
            </div>
        </div>

        <!-- 2-Column Section (Details + Right Sidebar Widgets) -->
        <div class="crm-emp-main-grid">

            <!-- Left Details Column -->
            <div>
                <!-- Top Profile Card -->
                <div class="common-table-card"
                    style="padding: 16px 20px; border-radius: 12px; background: #fff; border: 1px solid #edf2f7; display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px;">
                    <div style="display: flex; align-items: center; gap: 14px;">
                        @if ($empAvatar)
                        <img src="{{ $empAvatar }}" alt="{{ $empName }}"
                            style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover; flex-shrink: 0; display: inline-block;">
                        @else
                        <div class="avatar"
                            style="width: 48px; height: 48px; border-radius: 50%; background: #1e3a8a; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 700; flex-shrink: 0;">
                            {{ $initial }}
                        </div>
                        @endif
                        <div>
                            <h4 style="margin: 0; font-size: 16px; font-weight: 700; color: #1e293b;">
                                {{ $empName }}
                            </h4>
                            <p style="margin: 3px 0 0; font-size: 13px; color: #64748b;">{{ $empRole }}</p>
                        </div>
                    </div>
                    <span class="badge {{ $isActive ? 'active' : 'inactive' }}"
                        style="background: {{ $isActive ? '#10b981' : '#ef4444' }}; color: #fff; padding: 4px 14px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                        {{ ucfirst($empStatus) }}
                    </span>
                </div>

                <!-- 4 Stats Cards -->
                <div class="crm-emp-stats">
                    <div class="summary-card"
                        style="padding: 14px 16px; border-radius: 10px; background: #fff; border: 1px solid #edf2f7; display: flex; align-items: center; gap: 12px;">
                        <div
                            style="width: 38px; height: 38px; border-radius: 8px; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i data-lucide="layers" style="width: 18px; height: 18px;"></i>
                        </div>
                        <div>
                            <small style="display: block; font-size: 11.5px; color: #94a3b8; font-weight: 500;">Total Leads</small>
                            <h3 style="margin: 0; font-size: 20px; font-weight: 700; color: #1e293b;">{{ $leadsCount }}</h3>
                        </div>
                    </div>

                    <div class="summary-card"
                        style="padding: 14px 16px; border-radius: 10px; background: #fff; border: 1px solid #edf2f7; display: flex; align-items: center; gap: 12px;">
                        <div
                            style="width: 38px; height: 38px; border-radius: 8px; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i data-lucide="phone-call" style="width: 18px; height: 18px;"></i>
                        </div>
                        <div>
                            <small style="display: block; font-size: 11.5px; color: #94a3b8; font-weight: 500;">Total Calls</small>
                            <h3 style="margin: 0; font-size: 20px; font-weight: 700; color: #1e293b;">{{ $callsCount }}</h3>
                        </div>
                    </div>

                    <div class="summary-card"
                        style="padding: 14px 16px; border-radius: 10px; background: #fff; border: 1px solid #edf2f7; display: flex; align-items: center; gap: 12px;">
                        <div
                            style="width: 38px; height: 38px; border-radius: 8px; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i data-lucide="file-check" style="width: 18px; height: 18px;"></i>
                        </div>
                        <div>
                            <small style="display: block; font-size: 11.5px; color: #94a3b8; font-weight: 500;">Total Deals</small>
                            <h3 style="margin: 0; font-size: 20px; font-weight: 700; color: #1e293b;">{{ $dealsCount }}</h3>
                        </div>
                    </div>

                    <div class="summary-card"
                        style="padding: 14px 16px; border-radius: 10px; background: #fff; border: 1px solid #edf2f7; display: flex; align-items: center; gap: 12px;">
                        <div
                            style="width: 38px; height: 38px; border-radius: 8px; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i data-lucide="trending-up" style="width: 18px; height: 18px;"></i>
                        </div>
                        <div>
                            <small style="display: block; font-size: 11.5px; color: #94a3b8; font-weight: 500;">Conversion Rate</small>
                            <h3 style="margin: 0; font-size: 20px; font-weight: 700; color: #1e293b;">{{ $conversionRate }}</h3>
                        </div>
                    </div>
                </div>

                <!-- Account Overview Card -->
                <div class="common-table-card"
                    style="padding: 22px 24px; border-radius: 12px; background: #fff; border: 1px solid #edf2f7;">
                    <div
                        style="display: flex; align-items: center; gap: 8px; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9;">
                        <i data-lucide="user" style="width: 18px; height: 18px; color: #64748b;"></i>
                        <h4 style="margin: 0; font-size: 15px; font-weight: 700; color: #1e293b;">Account Overview</h4>
                    </div>

                    <div class="crm-emp-overview">
                        <!-- Left Field List -->
                        <div style="display: flex; flex-direction: column; gap: 15px;">
                            <div style="display: flex; justify-content: space-between; font-size: 13px;">
                                <span style="color: #94a3b8;">Name</span>
                                <span style="color: #1e293b; font-weight: 600;">{{ $empName }}</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 13px;">
                                <span style="color: #94a3b8;">Designation</span>
                                <span style="color: #1e293b; font-weight: 500;">{{ $empRole }}</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 13px;">
                                <span style="color: #94a3b8;">Contact No.</span>
                                <span style="color: #1e293b; font-weight: 500;">{{ $empPhone }}</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 13px;">
                                <span style="color: #94a3b8;">Email</span>
                                <span style="color: #1e293b; font-weight: 500;">{{ $empEmail }}</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 13px;">
                                <span style="color: #94a3b8;">Password</span>
                                <span style="color: #64748b; font-weight: 500;">••••••••••••</span>
                            </div>
                        </div>

                        <!-- Right Field List -->
                        <div style="display: flex; flex-direction: column; gap: 15px;">
                            <div style="display: flex; justify-content: space-between; font-size: 13px;">
                                <span style="color: #94a3b8;">Account Owner</span>
                                <span style="color: #1e293b; font-weight: 600;">{{ $emp ? ($emp->manager ?? 'Admin') : 'Admin' }}</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 13px;">
                                <span style="color: #94a3b8;">Created on</span>
                                <span style="color: #1e293b; font-weight: 500;">{{ $emp && $emp->created_at ? $emp->created_at->format('d M Y') : '—' }}</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 13px;">
                                <span style="color: #94a3b8;">Status</span>
                                <span style="color: #1e293b; font-weight: 500;">{{ ucfirst($empStatus) }}</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 13px;">
                                <span style="color: #94a3b8;">Last Activity</span>
                                <span style="color: #1e293b; font-weight: 500;">{{ $emp && $emp->last_login_at ? $emp->last_login_at->diffForHumans() : 'Recently Active' }}</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 13px;">
                                <span style="color: #94a3b8;">Employee ID</span>
                                <span style="color: #1e293b; font-weight: 600;">EMP{{ str_pad($empId, 4, '0', STR_PAD_LEFT) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons Row -->
                    <div
                        style="display: flex; align-items: center; gap: 12px; padding-top: 16px; border-top: 1px solid #f1f5f9;">
                        <a href="{{ Route::has('crm.employee.edit') ? route('crm.employee.edit', $empId) : '#' }}"
                            style="background: #0056b3; color: #fff; padding: 8px 16px; border-radius: 6px; font-size: 12.5px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; text-decoration: none;">
                            <i data-lucide="square-pen" style="width: 14px; height: 14px;"></i>
                            <span>Edit Employee</span>
                        </a>

                        <a href="{{ route('crm.employees') }}"
                            style="background: #f1f5f9; color: #475569; padding: 8px 16px; border-radius: 6px; font-size: 12.5px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; text-decoration: none;">
                            <i data-lucide="arrow-left" style="width: 14px; height: 14px;"></i>
                            <span>Back to List</span>
                        </a>
                    </div>
                </div>

            </div>

            <!-- Right Sidebar Column (Quick Actions & Recent Activities) -->
            <div>
                <!-- Quick Action Card -->
                <div class="common-table-card"
                    style="padding: 18px 20px; border-radius: 12px; background: #fff; border: 1px solid #edf2f7; margin-bottom: 18px;">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px;">
                        <i data-lucide="sparkles" style="width: 16px; height: 16px; color: #94a3b8;"></i>
                        <h4 style="margin: 0; font-size: 14px; font-weight: 700; color: #1e293b;">Quick Action</h4>
                    </div>
                    <ul class="quick-links"
                        style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 14px;">
                        <li style="display: flex; align-items: center; gap: 10px;">
                            <i data-lucide="users" style="width: 16px; height: 16px; color: #64748b;"></i>
                            <a href="{{ route('crm.leads', ['assigned_to' => $empName]) }}"
                                style="color: #475569; font-size: 13px; font-weight: 500; text-decoration: none;">View
                                Leads</a>
                        </li>
                        <li style="display: flex; align-items: center; gap: 10px;">
                            <i data-lucide="phone-call" style="width: 16px; height: 16px; color: #64748b;"></i>
                            <a href="{{ Route::has('crm.followups') ? route('crm.followups') : '#' }}"
                                style="color: #475569; font-size: 13px; font-weight: 500; text-decoration: none;">View
                                Follow-up</a>
                        </li>
                        <li style="display: flex; align-items: center; gap: 10px;">
                            <i data-lucide="file-text" style="width: 16px; height: 16px; color: #64748b;"></i>
                            <a href="{{ Route::has('crm.quotations') ? route('crm.quotations') : '#' }}"
                                style="color: #475569; font-size: 13px; font-weight: 500; text-decoration: none;">View
                                Quotations</a>
                        </li>
                    </ul>
                </div>

                <!-- Recent Activities Card -->
                <div class="common-table-card"
                    style="padding: 18px 20px; border-radius: 12px; background: #fff; border: 1px solid #edf2f7;">
                    <div
                        style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i data-lucide="clock" style="width: 16px; height: 16px; color: #64748b;"></i>
                            <h4 style="margin: 0; font-size: 14px; font-weight: 700; color: #1e293b;">Recent Activities</h4>
                        </div>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 16px;">
                        <div style="display: flex; align-items: flex-start; gap: 10px;">
                            <span
                                style="width: 8px; height: 8px; border-radius: 50%; background: #0056b3; margin-top: 5px; flex-shrink: 0;"></span>
                            <div>
                                <p style="margin: 0; font-size: 12.5px; color: #1e293b; font-weight: 500;">
                                    Employee Account Active
                                </p>
                                <small style="font-size: 11px; color: #94a3b8;">
                                    {{ $emp && $emp->created_at ? $emp->created_at->diffForHumans() : 'Active' }}
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });
</script>
@endsection
