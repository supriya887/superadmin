@extends('superadmin.layouts.crm')

@section('content')
    @php
        $items = $leadSources ?? ($sources ?? collect());
        $totalSourcesCount = $totalSources ?? (method_exists($items, 'total') ? $items->total() : $items->count());
        $totalLeadsCount = $totalLeads ?? 788;
        $topSourceName = $topLeadSource ?? 'Website';
        $convRate = $conversionRate ?? '24.6%';
        $monthLeads = $newLeadsThisMonth ?? 126;
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
                        style="display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 8px; background: #f1f5f9; color: #1e293b; font-size: 13px; font-weight: 600; text-decoration: none;">
                        <i data-lucide="globe" style="width: 16px; height: 16px; color: #0284c7;"></i>
                        <span>Lead Sources</span>
                    </a>
                </li>
                <li>
                    <a href="javascript:void(0)"
                        style="display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 8px; color: #64748b; font-size: 13px; font-weight: 500; text-decoration: none;">
                        <i data-lucide="building-2" style="width: 16px; height: 16px; color: #94a3b8;"></i>
                        <span>Industry</span>
                    </a>
                </li>
                <li>
                    <a href="javascript:void(0)"
                        style="display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 8px; color: #64748b; font-size: 13px; font-weight: 500; text-decoration: none;">
                        <i data-lucide="check-square" style="width: 16px; height: 16px; color: #94a3b8;"></i>
                        <span>Lead Status</span>
                    </a>
                </li>
                <li>
                    <a href="javascript:void(0)"
                        style="display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 8px; color: #64748b; font-size: 13px; font-weight: 500; text-decoration: none;">
                        <i data-lucide="share-2" style="width: 16px; height: 16px; color: #94a3b8;"></i>
                        <span>Lead Bifurcation</span>
                    </a>
                </li>
                <li>
                    <a href="javascript:void(0)"
                        style="display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 8px; color: #64748b; font-size: 13px; font-weight: 500; text-decoration: none;">
                        <i data-lucide="flag" style="width: 16px; height: 16px; color: #94a3b8;"></i>
                        <span>Lead Priority</span>
                    </a>
                </li>
                <li>
                    <a href="javascript:void(0)"
                        style="display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 8px; color: #64748b; font-size: 13px; font-weight: 500; text-decoration: none;">
                        <i data-lucide="briefcase" style="width: 16px; height: 16px; color: #94a3b8;"></i>
                        <span>Designation</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Main Content Column -->
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
                    <h2 style="margin: 4px 0 0 0; font-size: 22px; font-weight: 700; color: #1e293b;">Lead Sources</h2>
                </div>
                <div>
                    <button type="button" onclick="openAddModal()"
                        style="background: #0056b3; color: #ffffff; border: none; padding: 10px 20px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                        <i data-lucide="plus" style="width: 15px; height: 15px;"></i>
                        <span>Add Leads Sources</span>
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

            <!-- 5 Stats / Summary Cards -->
            <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 14px; margin-bottom: 22px;">
                <!-- Total Sources -->
                <div class="summary-card"
                    style="background: #ffffff; border: 1px solid #edf2f7; border-radius: 12px; padding: 16px 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                    <div style="width: 36px; height: 36px; border-radius: 8px; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; margin-bottom: 12px;">
                        <i data-lucide="layers" style="width: 18px; height: 18px;"></i>
                    </div>
                    <small style="font-size: 11.5px; color: #94a3b8; font-weight: 500; display: block;">Total Sources</small>
                    <h3 style="margin: 4px 0 0 0; font-size: 22px; font-weight: 700; color: #1e293b;">{{ $totalSourcesCount }}</h3>
                </div>

                <!-- Total Leads -->
                <div class="summary-card"
                    style="background: #ffffff; border: 1px solid #edf2f7; border-radius: 12px; padding: 16px 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                    <div style="width: 36px; height: 36px; border-radius: 8px; background: #e0f7fa; color: #00acc1; display: flex; align-items: center; justify-content: center; margin-bottom: 12px;">
                        <i data-lucide="users" style="width: 18px; height: 18px;"></i>
                    </div>
                    <small style="font-size: 11.5px; color: #94a3b8; font-weight: 500; display: block;">Total Leads</small>
                    <h3 style="margin: 4px 0 0 0; font-size: 22px; font-weight: 700; color: #1e293b;">{{ $totalLeadsCount }}</h3>
                </div>

                <!-- Top Lead Source -->
                <div class="summary-card"
                    style="background: #ffffff; border: 1px solid #edf2f7; border-radius: 12px; padding: 16px 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                    <div style="width: 36px; height: 36px; border-radius: 8px; background: #dcfce7; color: #16a34a; display: flex; align-items: center; justify-content: center; margin-bottom: 12px;">
                        <i data-lucide="globe" style="width: 18px; height: 18px;"></i>
                    </div>
                    <small style="font-size: 11.5px; color: #94a3b8; font-weight: 500; display: block;">Top Lead Source</small>
                    <h3 style="margin: 4px 0 0 0; font-size: 18px; font-weight: 700; color: #1e293b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $topSourceName }}</h3>
                </div>

                <!-- Conversion Rate -->
                <div class="summary-card"
                    style="background: #ffffff; border: 1px solid #edf2f7; border-radius: 12px; padding: 16px 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                    <div style="width: 36px; height: 36px; border-radius: 8px; background: #f3e8ff; color: #9333ea; display: flex; align-items: center; justify-content: center; margin-bottom: 12px;">
                        <i data-lucide="trending-up" style="width: 18px; height: 18px;"></i>
                    </div>
                    <small style="font-size: 11.5px; color: #94a3b8; font-weight: 500; display: block;">Conversion Rate</small>
                    <h3 style="margin: 4px 0 0 0; font-size: 22px; font-weight: 700; color: #1e293b;">{{ $convRate }}</h3>
                </div>

                <!-- New Leads This Month -->
                <div class="summary-card"
                    style="background: #ffffff; border: 1px solid #edf2f7; border-radius: 12px; padding: 16px 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                    <div style="width: 36px; height: 36px; border-radius: 8px; background: #fef3c7; color: #d97706; display: flex; align-items: center; justify-content: center; margin-bottom: 12px;">
                        <i data-lucide="user-plus" style="width: 18px; height: 18px;"></i>
                    </div>
                    <small style="font-size: 11.5px; color: #94a3b8; font-weight: 500; display: block;">New Leads This Month</small>
                    <h3 style="margin: 4px 0 0 0; font-size: 22px; font-weight: 700; color: #1e293b;">{{ $monthLeads }}</h3>
                </div>
            </div>

            <!-- Table Card -->
            <div class="common-table-card"
                style="background: #ffffff; border: 1px solid #edf2f7; border-radius: 14px; padding: 22px 24px; box-shadow: 0 1px 4px rgba(0,0,0,0.02);">
                
                <!-- Table Top Filter Bar -->
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px;">
                    <p style="margin: 0; font-size: 13px; color: #475569;">
                        Showing <strong>{{ count($items) > 0 ? '1 - ' . count($items) : '0' }}</strong> from <strong>{{ $totalSourcesCount }}</strong> Sources
                    </p>

                    <form method="GET" action="{{ route('crm.lead-sources.index') }}" style="margin: 0;">
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
                                Sources
                            </th>
                            <th style="padding: 12px 18px; text-align: left; font-size: 12.5px; font-weight: 600; color: #334155;">
                                Leads
                            </th>
                            <th style="padding: 12px 18px; text-align: left; font-size: 12.5px; font-weight: 600; color: #334155;">
                                Created on
                            </th>
                            <th style="padding: 12px 18px; text-align: center; font-size: 12.5px; font-weight: 600; color: #334155; border-top-right-radius: 8px; border-bottom-right-radius: 8px;">
                                Action
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $source)
                            @php
                                $logoSrc = null;
                                if (!empty($source->logo)) {
                                    if (str_starts_with($source->logo, 'http://') || str_starts_with($source->logo, 'https://')) {
                                        $logoSrc = $source->logo;
                                    } elseif (file_exists(public_path('storage/' . ltrim($source->logo, '/')))) {
                                        $logoSrc = asset('storage/' . ltrim($source->logo, '/'));
                                    } elseif (file_exists(public_path('storage/lead-sources/' . basename($source->logo)))) {
                                        $logoSrc = asset('storage/lead-sources/' . basename($source->logo));
                                    } else {
                                        $logoSrc = asset('storage/' . ltrim($source->logo, '/'));
                                    }
                                }

                                $leadCountVal = $leadsCountMap[$source->name] ?? ($leadsCountMap[$source->id] ?? ($source->leads_count ?? 245));
                            @endphp
                            <tr style="background: #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,0.02); border: 1px solid #edf2f7;">
                                <!-- Id -->
                                <td style="padding: 14px 18px; border-top: 1px solid #edf2f7; border-bottom: 1px solid #edf2f7; border-left: 1px solid #edf2f7; border-top-left-radius: 8px; border-bottom-left-radius: 8px;">
                                    <span style="background: #f1f5f9; color: #475569; padding: 4px 10px; border-radius: 6px; font-size: 11.5px; font-weight: 600; letter-spacing: 0.5px;">
                                        LS{{ str_pad($source->id, 3, '0', STR_PAD_LEFT) }}
                                    </span>
                                </td>

                                <!-- Sources -->
                                <td style="padding: 14px 18px; border-top: 1px solid #edf2f7; border-bottom: 1px solid #edf2f7;">
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div style="width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #fff; border: 1px solid #e2e8f0; overflow: hidden; flex-shrink: 0;">
                                            @if ($logoSrc)
                                                <img src="{{ $logoSrc }}" alt="{{ $source->name }}"
                                                    style="width: 100%; height: 100%; object-fit: contain;">
                                            @else
                                                <i data-lucide="globe" style="width: 16px; height: 16px; color: #0284c7;"></i>
                                            @endif
                                        </div>
                                        <span style="font-weight: 500; font-size: 13.5px; color: #1e293b;">
                                            {{ $source->name }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Leads -->
                                <td style="padding: 14px 18px; border-top: 1px solid #edf2f7; border-bottom: 1px solid #edf2f7; font-size: 13px; color: #475569;">
                                    {{ $leadCountVal }}
                                </td>

                                <!-- Created on -->
                                <td style="padding: 14px 18px; border-top: 1px solid #edf2f7; border-bottom: 1px solid #edf2f7; font-size: 13px; color: #64748b;">
                                    {{ $source->created_at ? $source->created_at->format('d M Y') : '—' }}
                                </td>

                                <!-- Action -->
                                <td style="padding: 14px 18px; text-align: center; border-top: 1px solid #edf2f7; border-bottom: 1px solid #edf2f7; border-right: 1px solid #edf2f7; border-top-right-radius: 8px; border-bottom-right-radius: 8px;">
                                    <div style="display: inline-flex; align-items: center; gap: 10px;">
                                        <button type="button"
                                            onclick="openEditModal({{ $source->id }}, '{{ addslashes($source->name) }}', '{{ $source->status }}', '{{ $logoSrc ?? '' }}')"
                                            style="background: transparent; border: none; cursor: pointer; color: #64748b; padding: 4px; display: inline-flex; align-items: center;"
                                            title="Edit">
                                            <i data-lucide="square-pen" style="width: 16px; height: 16px;"></i>
                                        </button>

                                        <form method="POST" action="{{ route('crm.lead-sources.destroy', $source->id) }}"
                                            onsubmit="return confirm('Are you sure you want to delete this lead source?');"
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
                                <td colspan="5" style="text-align: center; padding: 36px; color: #94a3b8; font-size: 13px;">
                                    No lead sources found. Click "Add Leads Sources" to create one.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                @if (method_exists($items, 'links'))
                    <div style="margin-top: 18px;">
                        {{ $items->links() }}
                    </div>
                @endif

            </div>
        </div>

    </div>

    <!-- Modal: Add Lead Source (Exact design from screenshot) -->
    <div id="addModal"
        style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.45); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: #ffffff; border-radius: 16px; width: 100%; max-width: 390px; padding: 26px 28px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); position: relative;">
            
            <!-- Close Button -->
            <button type="button" onclick="closeAddModal()"
                style="position: absolute; top: 16px; right: 16px; border: none; background: transparent; cursor: pointer; font-size: 20px; color: #334155; line-height: 1; padding: 2px;">
                &times;
            </button>

            <!-- Title -->
            <h3 style="margin: 0 0 22px 0; font-size: 18px; font-weight: 700; color: #1e293b; text-align: center;">
                Add Lead Source
            </h3>

            <form method="POST" action="{{ route('crm.lead-sources.store') }}" enctype="multipart/form-data">
                @csrf

                <!-- Logo -->
                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                        Logo <span style="color: #ef4444;">*</span>
                    </label>
                    <div style="display: flex; align-items: center; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; background: #fff;">
                        <span id="addFileName"
                            style="flex: 1; padding: 9px 12px; font-size: 12.5px; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            Choose File
                        </span>
                        <label for="addLogoInput"
                            style="padding: 9px 16px; background: #fff; border-left: 1px solid #e2e8f0; font-size: 12.5px; font-weight: 500; color: #334155; cursor: pointer; margin: 0; white-space: nowrap;">
                            Choose File
                        </label>
                        <input type="file" id="addLogoInput" name="logo" accept="image/*" required style="display: none;"
                            onchange="document.getElementById('addFileName').innerText = this.files[0] ? this.files[0].name : 'Choose File'">
                    </div>
                </div>

                <!-- Source Name -->
                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                        Source Name <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="name" placeholder="Enter lead source name" required
                        style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 9px 14px; font-size: 13px; outline: none; box-sizing: border-box; color: #1e293b;">
                </div>

                <!-- Status -->
                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                        Status <span style="color: #ef4444;">*</span>
                    </label>
                    <select name="status" required
                        style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 9px 14px; font-size: 13px; outline: none; box-sizing: border-box; background: #fff; color: #1e293b;">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <!-- Save Button -->
                <div style="text-align: center;">
                    <button type="submit"
                        style="width: 100%; background: #0056b3; color: #ffffff; border: none; padding: 11px 24px; border-radius: 20px; font-size: 14px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 12px rgba(0, 86, 179, 0.2);">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Edit Lead Source -->
    <div id="editModal"
        style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.45); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: #ffffff; border-radius: 16px; width: 100%; max-width: 390px; padding: 26px 28px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); position: relative;">
            
            <!-- Close Button -->
            <button type="button" onclick="closeEditModal()"
                style="position: absolute; top: 16px; right: 16px; border: none; background: transparent; cursor: pointer; font-size: 20px; color: #334155; line-height: 1; padding: 2px;">
                &times;
            </button>

            <!-- Title -->
            <h3 style="margin: 0 0 22px 0; font-size: 18px; font-weight: 700; color: #1e293b; text-align: center;">
                Edit Lead Source
            </h3>

            <form id="editForm" method="POST" action="" enctype="multipart/form-data">
                @csrf

                <!-- Logo -->
                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                        Logo
                    </label>
                    <div style="display: flex; align-items: center; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; background: #fff;">
                        <span id="editFileName"
                            style="flex: 1; padding: 9px 12px; font-size: 12.5px; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            Choose File
                        </span>
                        <label for="editLogoInput"
                            style="padding: 9px 16px; background: #fff; border-left: 1px solid #e2e8f0; font-size: 12.5px; font-weight: 500; color: #334155; cursor: pointer; margin: 0; white-space: nowrap;">
                            Choose File
                        </label>
                        <input type="file" id="editLogoInput" name="logo" accept="image/*" style="display: none;"
                            onchange="document.getElementById('editFileName').innerText = this.files[0] ? this.files[0].name : 'Choose File'">
                    </div>
                </div>

                <!-- Source Name -->
                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                        Source Name <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" id="editNameInput" name="name" placeholder="Enter lead source name" required
                        style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 9px 14px; font-size: 13px; outline: none; box-sizing: border-box; color: #1e293b;">
                </div>

                <!-- Status -->
                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                        Status <span style="color: #ef4444;">*</span>
                    </label>
                    <select id="editStatusInput" name="status" required
                        style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 9px 14px; font-size: 13px; outline: none; box-sizing: border-box; background: #fff; color: #1e293b;">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <!-- Save Button -->
                <div style="text-align: center;">
                    <button type="submit"
                        style="width: 100%; background: #0056b3; color: #ffffff; border: none; padding: 11px 24px; border-radius: 20px; font-size: 14px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 12px rgba(0, 86, 179, 0.2);">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            const modal = document.getElementById('addModal');
            if (modal) {
                modal.style.display = 'flex';
            }
        }

        function closeAddModal() {
            const modal = document.getElementById('addModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        function openEditModal(id, name, status, logoUrl) {
            const modal = document.getElementById('editModal');
            const form = document.getElementById('editForm');
            const nameInput = document.getElementById('editNameInput');
            const statusInput = document.getElementById('editStatusInput');
            const fileName = document.getElementById('editFileName');

            if (modal && form) {
                form.action = "{{ url('crm/lead-sources') }}/" + id + "/update";
                if (nameInput) nameInput.value = name;
                if (statusInput) statusInput.value = status ? status.toLowerCase() : 'active';
                if (fileName) fileName.innerText = logoUrl ? 'Current logo selected' : 'Choose File';
                modal.style.display = 'flex';
            }
        }

        function closeEditModal() {
            const modal = document.getElementById('editModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        window.addEventListener('click', function(e) {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            if (e.target === addModal) {
                closeAddModal();
            }
            if (e.target === editModal) {
                closeEditModal();
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    </script>
@endsection
