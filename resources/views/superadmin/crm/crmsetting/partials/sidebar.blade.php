@php
    $employeesCount = $employeesCount ?? 0;
    $totalCount = $totalCount ?? 0;
    $commonDesignation = $commonDesignation ?? 'N/A';
    $mgmtDesignations = $mgmtDesignations ?? 0;
@endphp
<div style="display: grid; grid-template-columns: 220px minmax(0, 1fr); gap: 24px; align-items: start;">

    <!-- Left Sub Navigation -->
    <div class="common-table-card"
        style="padding: 16px; border-radius: 12px; background: #fff; border: 1px solid #edf2f7;">
        <p style="font-size: 11.5px; color: #94a3b8; margin: 0 0 14px 4px; font-weight: 500;">
            Configure lead workflow
        </p>
        <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 6px;">
            <li>
                <a href="{{ route('crm.lead-sources.index') }}"
                    style="display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 8px; color: #64748b; font-size: 13px; font-weight: 500; text-decoration: none;">
                    <i data-lucide="globe" style="width: 16px; height: 16px; color: #94a3b8;"></i>
                    <span>Lead Sources</span>
                </a>
            </li>
            <li>
                <a href="{{ route('crm.lead-status.index') }}"
                    style="display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 8px; color: #64748b; font-size: 13px; font-weight: 500; text-decoration: none;">
                    <i data-lucide="check-square" style="width: 16px; height: 16px; color: #94a3b8;"></i>
                    <span>Lead Status</span>
                </a>
            </li>
            <li>
                <a href="{{ route('crm.lead-bifurcation.index') }}"
                    style="display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 8px; color: #64748b; font-size: 13px; font-weight: 500; text-decoration: none;">
                    <i data-lucide="share-2" style="width: 16px; height: 16px; color: #94a3b8;"></i>
                    <span>Lead Bifurcation</span>
                </a>
            </li>
            <li>
                <a href="{{ route('crm.lead-priority.index') }}"
                    style="display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 8px; color: #64748b; font-size: 13px; font-weight: 500; text-decoration: none;">
                    <i data-lucide="flag" style="width: 16px; height: 16px; color: #94a3b8;"></i>
                    <span>Lead Priority</span>
                </a>
            </li>
            <li>
                <a href="{{ route('crm.designation.index') }}"
                    style="display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 8px; background: #f1f5f9; color: #1e293b; font-size: 13px; font-weight: 600; text-decoration: none;">
                    <i data-lucide="briefcase" style="width: 16px; height: 16px; color: #0284c7;"></i>
                    <span>Designation</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content Area -->
    <div>
        <!-- Header -->
        <div class="page-header"
            style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
            <div>
                <div class="breadcrumb welcome-text">
                    <p style="margin: 0; font-size: 13px; color: #64748b;">
                        <a href="{{ Route::has('crm.dashboard') ? route('crm.dashboard') : '#' }}" style="color: #64748b; text-decoration: none;">Dashboard</a>
                        <span>/</span>
                        <a href="javascript:void(0)" style="color: #64748b; text-decoration: none;">Manage</a>
                        <span>/</span>
                        CRM Setting
                        <span>/</span>
                    </p>
                </div>
                <h2 style="margin: 4px 0 0 0; font-size: 22px; font-weight: 700; color: #1e293b;">Designation</h2>
            </div>
            <div>
                <button type="button" onclick="openAddModal()"
                    style="background: #0056b3; color: #ffffff; border: none; padding: 10px 20px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                    <i data-lucide="plus" style="width: 15px; height: 15px;"></i>
                    <span>Add Designation</span>
                </button>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success"
                style="padding: 12px 18px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; border-radius: 8px; margin-bottom: 18px; font-size: 13px;">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger"
                style="padding: 12px 18px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 8px; margin-bottom: 18px; font-size: 13px;">
                <ul style="margin: 0; padding-left: 16px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- 4 Stats Cards matching mockup -->
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 22px;">
            <!-- Total Designations -->
            <div class="summary-card"
                style="background: #ffffff; border: 1px solid #edf2f7; border-radius: 12px; padding: 16px 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; margin-bottom: 12px;">
                    <i data-lucide="briefcase" style="width: 18px; height: 18px;"></i>
                </div>
                <small style="font-size: 11.5px; color: #94a3b8; font-weight: 500; display: block;">Total Designations</small>
                <h3 style="margin: 4px 0 0 0; font-size: 22px; font-weight: 700; color: #1e293b;">{{ $totalCount }}</h3>
            </div>

            <!-- Total Employees -->
            <div class="summary-card"
                style="background: #ffffff; border: 1px solid #edf2f7; border-radius: 12px; padding: 16px 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: #ede9fe; color: #7c3aed; display: flex; align-items: center; justify-content: center; margin-bottom: 12px;">
                    <i data-lucide="users" style="width: 18px; height: 18px;"></i>
                </div>
                <small style="font-size: 11.5px; color: #94a3b8; font-weight: 500; display: block;">Total Employees</small>
                <h3 style="margin: 4px 0 0 0; font-size: 22px; font-weight: 700; color: #1e293b;">{{ $employeesCount }}</h3>
            </div>

            <!-- Most Common Designation -->
            <div class="summary-card"
                style="background: #ffffff; border: 1px solid #edf2f7; border-radius: 12px; padding: 16px 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: #dcfce7; color: #16a34a; display: flex; align-items: center; justify-content: center; margin-bottom: 12px;">
                    <i data-lucide="trending-up" style="width: 18px; height: 18px;"></i>
                </div>
                <small style="font-size: 11.5px; color: #94a3b8; font-weight: 500; display: block;">Most Common Designation</small>
                <h3 style="margin: 4px 0 0 0; font-size: 19px; font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $commonDesignation }}</h3>
            </div>

            <!-- Management Designations -->
            <div class="summary-card"
                style="background: #ffffff; border: 1px solid #edf2f7; border-radius: 12px; padding: 16px 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: #ffedd5; color: #ea580c; display: flex; align-items: center; justify-content: center; margin-bottom: 12px;">
                    <i data-lucide="user-check" style="width: 18px; height: 18px;"></i>
                </div>
                <small style="font-size: 11.5px; color: #94a3b8; font-weight: 500; display: block;">Management Designations</small>
                <h3 style="margin: 4px 0 0 0; font-size: 22px; font-weight: 700; color: #1e293b;">{{ $mgmtDesignations }}</h3>
            </div>
        </div>

        <!-- Table Card Container -->
        <div class="common-table-card"
            style="background: #ffffff; border: 1px solid #edf2f7; border-radius: 12px; padding: 22px 24px;">

            <!-- Table Filter Bar -->
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px;">
                <p style="margin: 0; font-size: 13px; color: #475569;">
                    Showing <strong>{{ count($items) > 0 ? '1 - ' . count($items) : '0' }}</strong> from <strong>{{ $totalCount }}</strong> Sources
                </p>

                <form method="GET" action="{{ route('crm.designation.index') }}" style="margin: 0;">
                    <div style="position: relative; width: 230px;">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search Partners"
                            style="width: 100%; border: 1px solid #e2e8f0; border-radius: 20px; padding: 7px 36px 7px 16px; font-size: 12.5px; outline: none; box-sizing: border-box; color: #1e293b;">
                        <i data-lucide="search"
                            style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); width: 15px; height: 15px; color: #94a3b8; pointer-events: none;"></i>
                    </div>
                </form>
            </div>

            <!-- Table -->
            <table style="width: 100%; border-collapse: separate; border-spacing: 0 10px;">
                <thead>
                    <tr style="background: #eef5ff;">
                        <th style="padding: 12px 18px; text-align: left; font-size: 12.5px; font-weight: 600; color: #334155; border-top-left-radius: 8px; border-bottom-left-radius: 8px;">
                            Id
                        </th>
                        <th style="padding: 12px 18px; text-align: left; font-size: 12.5px; font-weight: 600; color: #334155;">
                            Designation
                        </th>
                        <th style="padding: 12px 18px; text-align: left; font-size: 12.5px; font-weight: 600; color: #334155;">
                            Department
                        </th>
                        <th style="padding: 12px 18px; text-align: center; font-size: 12.5px; font-weight: 600; color: #334155;">
                            Employee Count
                        </th>
                        <th style="padding: 12px 18px; text-align: left; font-size: 12.5px; font-weight: 600; color: #334155;">
                            Status
                        </th>
                        <th style="padding: 12px 18px; text-align: center; font-size: 12.5px; font-weight: 600; color: #334155; border-top-right-radius: 8px; border-bottom-right-radius: 8px;">
                            Action
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr style="background: #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,0.02); border: 1px solid #edf2f7;">
                            <!-- Id -->
                            <td style="padding: 14px 18px; border-top: 1px solid #edf2f7; border-bottom: 1px solid #edf2f7; border-left: 1px solid #edf2f7; border-top-left-radius: 8px; border-bottom-left-radius: 8px;">
                                <span style="background: #f1f5f9; color: #475569; padding: 4px 10px; border-radius: 6px; font-size: 11.5px; font-weight: 600; letter-spacing: 0.5px;">
                                    DES{{ str_pad($item->id, 3, '0', STR_PAD_LEFT) }}
                                </span>
                            </td>

                            <!-- Designation Name -->
                            <td style="padding: 14px 18px; border-top: 1px solid #edf2f7; border-bottom: 1px solid #edf2f7; font-weight: 500; font-size: 13.5px; color: #1e293b; white-space: nowrap;">
                                {{ $item->name }}
                            </td>

                            <!-- Department -->
                            <td style="padding: 14px 18px; border-top: 1px solid #edf2f7; border-bottom: 1px solid #edf2f7; font-size: 13px; color: #64748b;">
                                {{ $item->department ?? 'General' }}
                            </td>

                            <!-- Employee Count -->
                            <td style="padding: 14px 18px; text-align: center; border-top: 1px solid #edf2f7; border-bottom: 1px solid #edf2f7; font-size: 13px; color: #475569; font-weight: 500;">
                                {{ $item->employee_count ?? 0 }}
                            </td>

                            <!-- Status (Date Column) -->
                            <td style="padding: 14px 18px; border-top: 1px solid #edf2f7; border-bottom: 1px solid #edf2f7; font-size: 13px; color: #64748b; white-space: nowrap;">
                                {{ $item->created_at ? $item->created_at->format('d M Y') : '20 Aug 2026' }}
                            </td>

                            <!-- Action -->
                            <td style="padding: 14px 18px; text-align: center; border-top: 1px solid #edf2f7; border-bottom: 1px solid #edf2f7; border-right: 1px solid #edf2f7; border-top-right-radius: 8px; border-bottom-right-radius: 8px;">
                                <div style="display: inline-flex; align-items: center; gap: 10px;">
                                    <button type="button"
                                        onclick="openEditModal({{ $item->id }}, '{{ addslashes($item->name) }}', '{{ addslashes($item->department ?? 'General') }}', '{{ $item->employee_count ?? 0 }}', '{{ addslashes($item->description ?? '') }}')"
                                        style="background: transparent; border: none; cursor: pointer; color: #64748b; padding: 4px; display: inline-flex; align-items: center;"
                                        title="Edit">
                                        <i data-lucide="square-pen" style="width: 16px; height: 16px;"></i>
                                    </button>

                                    <form method="POST" action="{{ route('crm.designation.destroy', $item->id) }}"
                                        onsubmit="return confirm('Are you sure you want to delete this designation?');"
                                        style="margin: 0; display: inline-flex; align-items: center;">
                                        @csrf
                                        <button type="submit"
                                            style="background: transparent; border: none; cursor: pointer; color: #94a3b8; padding: 4px; display: inline-flex; align-items: center;"
                                            title="Delete">
                                            <i data-lucide="trash-2" style="width: 16px; height: 16px;"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 36px; color: #94a3b8; font-size: 13px;">
                                No designation found. Click "Add Designation" to create one.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

        </div>
    </div>

</div>