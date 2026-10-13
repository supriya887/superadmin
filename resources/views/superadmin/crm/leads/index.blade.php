@extends('superadmin.layouts.crm')

@section('content')
    <div class="crm-leads-page crm-leads-list">

        @php
            $source = request('source');
            $avatarColors = ['purple', 'blue', 'teal', 'red', 'yellow'];
            $statusQuery = request('status');
            $searchQuery = request('search');
        @endphp

        <div class="page-header">
            <div class="">
                <div class="breadcrumb welcome-text">
                    <p><a href="{{ route('crm.dashboard') }}">Dashboard</a><span>/</span> </p>
                </div>
                <h2>Leads </h2>
            </div>
            <div class="crm-actions">

                <a href="{{ route('crm.leads.uploads') }}" class="crm-action-btn">
                    <i data-lucide="upload"></i>
                    <span>Upload Leads</span>
                </a>

                <a href="{{ route('crm.leads.create') }}" class="crm-action-btn active">
                    <i data-lucide="user-round-plus"></i>
                    <span>Add New Leads</span>
                </a>

            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success"
                style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                <div>{{ session('success') }}</div>
                @if (session('import_errors') && count(session('import_errors')) > 0)
                    <button type="button"
                        style="background:#fff; border:1px solid #dc2626; color:#dc2626; border-radius:6px; padding:5px 14px; font-size:12px; font-weight:600; cursor:pointer;"
                        onclick="document.getElementById('importErrorModal').classList.add('active')">
                        View {{ count(session('import_errors')) }} Skipped Records
                    </button>
                @endif
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger"
                style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                <div>{{ session('error') }}</div>
                @if (session('import_errors') && count(session('import_errors')) > 0)
                    <button type="button"
                        style="background:#fff; border:1px solid #dc2626; color:#dc2626; border-radius:6px; padding:5px 14px; font-size:12px; font-weight:600; cursor:pointer;"
                        onclick="document.getElementById('importErrorModal').classList.add('active')">
                        View {{ count(session('import_errors')) }} Skipped Records
                    </button>
                @endif
            </div>
        @endif

        <section class="dashboard-stats">
            <div class="stats-grid stats-grid-5">

                <div class="summary-card">
                    <div class="card-icon blue">
                        <i data-lucide="users"></i>
                    </div>
                    <div class="stat-content">
                        <h4>Total Leads</h4>
                        <h2>{{ number_format($leadStats['total'] ?? 0) }}</h2>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="card-icon green">
                        <i data-lucide="user-round-plus"></i>
                    </div>
                    <div class="stat-content">
                        <h4>New Leads</h4>
                        <h2>{{ number_format($leadStats['new'] ?? 0) }}</h2>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="card-icon orange">
                        <i data-lucide="user-round-check"></i>
                    </div>
                    <div class="stat-content">
                        <h4>Qualified Leads</h4>
                        <h2>{{ number_format($leadStats['qualified'] ?? 0) }}</h2>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="card-icon purple">
                        <i data-lucide="trophy"></i>
                    </div>
                    <div class="stat-content">
                        <h4>Won Leads</h4>
                        <h2>{{ number_format($leadStats['won'] ?? 0) }}</h2>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="card-icon red">
                        <i data-lucide="user-round-minus"></i>
                    </div>
                    <div class="stat-content">
                        <h4>Lost Leads</h4>
                        <h2>{{ number_format($leadStats['lost'] ?? 0) }}</h2>
                    </div>
                </div>

            </div>
        </section>

        <section class="common-table-section">
            <div class="crm-lead-tabs">

                <a href="{{ route('crm.leads', array_filter(['search' => $searchQuery, 'status' => $statusQuery])) }}"
                    class="{{ !$source ? 'active' : '' }}">All Leads</a>
                <a href="{{ route('crm.leads', array_filter(['source' => 'JustDial', 'search' => $searchQuery, 'status' => $statusQuery])) }}"
                    class="{{ in_array((string) $source, ['JustDial', 'JD', 'Just Dial'], true) ? 'active' : '' }}">JD</a>
                <a href="{{ route('crm.leads', array_filter(['source' => 'Exhibition', 'search' => $searchQuery, 'status' => $statusQuery])) }}"
                    class="{{ strcasecmp((string) $source, 'Exhibition') === 0 ? 'active' : '' }}">Exhibition</a>

            </div>
            <div class="common-table-card">
                <div class="common-table-header crm-lead-header">

                    <div class="crm-lead-title">

                        <p>
                            Showing <strong>{{ $leads->firstItem() ?? 0 }} - {{ $leads->lastItem() ?? 0 }}</strong> from
                            <strong>{{ $leads->total() }}</strong> Customers
                        </p>

                        <span class="header-divider"></span>

                        <label class="items-selected">
                            <input type="checkbox" id="selectAll">
                            <div><span id="selectedLeadCount">0</span>/{{ $leads->count() }} Items Selected</div>
                        </label>

                    </div>


                    <form method="GET" action="{{ route('crm.leads') }}" class="table-search">
                        @if ($source)
                            <input type="hidden" name="source" value="{{ $source }}">
                        @endif
                        @if ($statusQuery)
                            <input type="hidden" name="status" value="{{ $statusQuery }}">
                        @endif
                        @if (request('date_type'))
                            <input type="hidden" name="date_type" value="{{ request('date_type') }}">
                        @endif
                        @if (request('from'))
                            <input type="hidden" name="from" value="{{ request('from') }}">
                        @endif
                        @if (request('to'))
                            <input type="hidden" name="to" value="{{ request('to') }}">
                        @endif
                        @if (request('assigned_to'))
                            <input type="hidden" name="assigned_to" value="{{ request('assigned_to') }}">
                        @endif
                        @if (request('lead_category'))
                            <input type="hidden" name="lead_category" value="{{ request('lead_category') }}">
                        @endif
                        <input type="text" id="partnerSearch" name="search" value="{{ $searchQuery }}"
                            placeholder="Search Leads" autocomplete="off">

                        <i data-lucide="search"></i>
                    </form>

                </div>
                @php
                    $employees = $employees ?? collect();
                    $dateType = request('date_type');
                    $dateFrom = request('from');
                    $dateTo = request('to');
                    $assignedQuery = request('assigned_to');
                    $categoryQuery = request('lead_category');
                    $filtersOpen =
                        request()->has('filter_open') ||
                        $statusQuery ||
                        $dateType ||
                        $dateFrom ||
                        $dateTo ||
                        $assignedQuery ||
                        $categoryQuery;
                @endphp
                <form method="GET" action="{{ route('crm.leads') }}"
                    class="table-filter-wrap{{ $filtersOpen ? ' active' : '' }}" id="filterPanel">
                    @if ($source)
                        <input type="hidden" name="source" value="{{ $source }}">
                    @endif
                    @if ($searchQuery)
                        <input type="hidden" name="search" value="{{ $searchQuery }}">
                    @endif

                    <div class="filter-grid">

                        <div class="filter-group">
                            <h6>
                                Date Type :
                            </h6>
                            <div class="filter-options">
                                <select name="date_type" onchange="this.form.submit()">
                                    <option value="">Select</option>
                                    <option value="created" {{ $dateType === 'created' ? 'selected' : '' }}>Created Date
                                    </option>
                                    <option value="follow_up" {{ $dateType === 'follow_up' ? 'selected' : '' }}>Follow Up
                                        Date</option>
                                    <option value="updated" {{ $dateType === 'updated' ? 'selected' : '' }}>Updated Date
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="filter-group">
                            <h6>
                                Date Range :
                            </h6>
                            <div class="date-range">
                                <div class="date-input">
                                    <input type="date" name="from" value="{{ $dateFrom }}" placeholder="From"
                                        onchange="this.form.submit()">
                                    <i data-lucide="calendar"></i>
                                </div>
                                <div class="date-input">
                                    <input type="date" name="to" value="{{ $dateTo }}" placeholder="To"
                                        onchange="this.form.submit()">
                                    <i data-lucide="calendar"></i>
                                </div>
                            </div>
                        </div>

                        <div class="filter-group">
                            <h6>
                                Status :
                            </h6>
                            <div class="filter-options">
                                <select name="status" onchange="this.form.submit()">
                                    <option value="" {{ !$statusQuery ? 'selected' : '' }}>Select</option>
                                    @foreach (($leadStatuses ?? ['New', 'Contacted', 'Qualified', 'Negotiation', 'Quote Send', 'Won', 'Lost', 'Nurturing']) as $st)
                                        <option value="{{ $st }}" {{ $statusQuery === $st ? 'selected' : '' }}>
                                            {{ $st }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="filter-group">
                            <h6>
                                Employee :
                            </h6>
                            <div class="filter-options">
                                <select name="assigned_to" onchange="this.form.submit()">
                                    <option value="">Select</option>
                                    @foreach ($employees as $employee)
                                        @php $empName = is_object($employee) ? $employee->name : $employee; @endphp
                                        <option value="{{ $empName }}"
                                            {{ $assignedQuery == $empName ? 'selected' : '' }}>{{ $empName }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="filter-group">
                            <h6>
                                Lead Priority :
                            </h6>
                            <div class="filter-options">
                                <select name="lead_category" onchange="this.form.submit()">
                                    <option value="">Select</option>
                                    @foreach (($leadPriorities ?? $leadCategories ?? []) as $cat)
                                        <option value="{{ $cat }}" {{ $categoryQuery === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>



                    </div>

                </form>
                <div class="table-filter">
                    <div class="border-line"></div>
                    <button class="filter-btn{{ $filtersOpen ? ' is-open' : '' }}" id="toggleFilter" type="button">
                        @if ($filtersOpen)
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12"
                                fill="none">
                                <path d="M9 3L3 9M3 3L9 9" stroke="white" stroke-width="1.5" stroke-linecap="round"
                                    stroke-linejoin="round" />
                            </svg>
                            <span>Close</span>
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="9" viewBox="0 0 12 9"
                                fill="none">
                                <path d="M0.75 0.75H10.75M2.75 4.25H8.75M4.25 7.75H7.25" stroke="white" stroke-width="1.5"
                                    stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <span>Filters</span>
                        @endif
                    </button>
                    <a href="{{ route('crm.leads', array_filter(['source' => $source, 'filter_open' => 1])) }}"
                        class="filter-btn" id="resetFilters" role="button"
                        style="{{ $filtersOpen ? 'display: inline-flex;' : 'display: none;' }}">
                        <i data-lucide="rotate-ccw" width="15" height="15"></i>
                        <span>Reset</span>
                    </a>
                </div>

                <table class="crm-lead-table">

                    <thead>

                        <tr class="table-head">

                            <td class="select-col">
                                <input type="checkbox" id="selectAllTable">
                            </td>

                            <td>
                                Customer Name
                            </td>

                            <td>
                                Contact Information
                            </td>

                            <td class="status-col">
                                Status
                            </td>

                            <td>
                                Follow Up Date
                            </td>

                        </tr>

                    </thead>


                    <tbody class="table-body">

                        @forelse($leads as $lead)
                            @php
                                $statusLabel = $lead->lead_status ?: 'New';
                                $status = strtolower($statusLabel);
                                if (in_array($status, ['quotsent', 'quotation sent', 'quote send'], true)) {
                                    $statusLabel = 'Quote Send';
                                    $status = 'quote send';
                                }
                                $badgeClass = match ($status) {
                                    'new' => 'new',
                                    'contacted' => 'contacted',
                                    'qualified' => 'qualified',
                                    'negotiation', 'in process' => 'negotiation',
                                    'quotation sent', 'quote sent', 'quote send', 'quote approved' => 'quotation-sent',
                                    'won', 'deal done' => 'won',
                                    'lost', 'not interested' => 'lost',
                                    'nurturing' => 'nurturing',
                                    default => 'new',
                                };
                                $color = $avatarColors[$lead->id % count($avatarColors)];
                                $initial = strtoupper(substr($lead->company_name ?: $lead->name ?: 'L', 0, 1));
                                $assigned = $lead->assigned_to ?: 'Unassigned';
                                $assignedInitial = strtoupper(substr($assigned, 0, 1));
                            @endphp

                            <tr class="crm-lead-row table-row" data-href="{{ route('crm.leads.show', $lead->id) }}">

                                <td class="select-col" data-label="">
                                    <input type="checkbox" name="selected_leads[]" value="{{ $lead->id }}">
                                </td>

                                <td class="customer" data-label="Customer">

                                    <div class="avatar {{ $color }}">
                                        {{ $initial }}
                                    </div>

                                    <div>
                                        <h5>{{ $lead->company_name }}</h5>
                                        <p>
                                            {{ trim(($lead->salutation ? $lead->salutation . '. ' : '') . $lead->name) }}
                                            @if ($lead->designation)
                                                - {{ $lead->designation }}
                                            @endif
                                        </p>
                                    </div>

                                </td>

                                <td class="contact-info" data-label="Contact">

                                    <div>
                                        <h5>{{ $lead->email }}</h5>
                                        @php
                                            $cleanMobile = $lead->mobile;
                                            if ($cleanMobile) {
                                                $cleanMobile = trim((string) $cleanMobile);
                                                if (
                                                    stripos($cleanMobile, 'e') !== false ||
                                                    strpos($cleanMobile, '.') !== false
                                                ) {
                                                    if (is_numeric($cleanMobile)) {
                                                        $cleanMobile = sprintf('%.0f', (float) $cleanMobile);
                                                    } else {
                                                        $cleanMobile = preg_replace('/[^0-9]/', '', $cleanMobile);
                                                    }
                                                }
                                            }
                                        @endphp
                                        <p>{{ $cleanMobile ? '+' . ($lead->mobile_code ?: '91') . ' ' . $cleanMobile : '' }}
                                        </p>
                                    </div>

                                </td>

                                <td class="status-col" data-label="Status">
                                    <span class="badge {{ $badgeClass }}">
                                        {{ $statusLabel }}
                                    </span>
                                </td>

                                <td class="plan-action" data-label="Assigned To">
                                    <div class="assigned-user">

                                        <div class="user-info">
                                            <div class="avatar-flex">
                                                <div class="mini-avatar primary">
                                                    {{ $assignedInitial }}
                                                </div>
                                                <h5>{{ $assigned }}</h5>
                                            </div>

                                            <small>
                                                @if ($lead->follow_up_date)
                                                    Follow up on&nbsp;
                                                    <strong>{{ \Carbon\Carbon::parse($lead->follow_up_date)->format('d M, Y g:i A') }}</strong>
                                                @else
                                                    Added
                                                    {{ \Carbon\Carbon::parse($lead->created_at)->format('d M, Y h:i A') }}
                                                @endif
                                            </small>
                                        </div>
                                        <div class="actions">
                                            <a href="{{ route('crm.leads.show', $lead->id) }}" class="action-btn">
                                                <i data-lucide="chevron-right"></i>
                                            </a>
                                        </div>
                                    </div>
                                </td>

                            </tr>
                        @empty
                            <tr class="crm-lead-row table-row">
                                <td class="select-col"></td>
                                <td class="customer" colspan="4">No leads found.</td>
                            </tr>
                        @endforelse

                    </tbody>

                </table>


                <div class="table-pagination">

                    @if ($leads->onFirstPage())
                        <a href="#" class="page-btn">
                            <i data-lucide="chevron-left"></i>
                        </a>
                    @else
                        <a href="{{ $leads->previousPageUrl() }}" class="page-btn">
                            <i data-lucide="chevron-left"></i>
                        </a>
                    @endif

                    @php
                        $lastPage = max($leads->lastPage(), 1);
                    @endphp
                    @for ($page = 1; $page <= min($lastPage, 5); $page++)
                        <a href="{{ $leads->url($page) }}"
                            class="page-btn {{ $page == $leads->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                    @endfor

                    @if ($leads->hasMorePages())
                        <a href="{{ $leads->nextPageUrl() }}" class="page-btn">
                            <i data-lucide="chevron-right"></i>
                        </a>
                    @else
                        <a href="#" class="page-btn">
                            <i data-lucide="chevron-right"></i>
                        </a>
                    @endif

                </div>

            </div>

        </section>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var headerSelect = document.getElementById('selectAll');
                var tableSelect = document.getElementById('selectAllTable');
                var countEl = document.getElementById('selectedLeadCount');
                var rowBoxes = [];

                function selectedCount() {
                    return rowBoxes.filter(function(box) {
                        return box.checked;
                    }).length;
                }

                function refreshCount() {
                    if (countEl) {
                        countEl.textContent = selectedCount();
                    }
                    var allChecked = rowBoxes.length > 0 && selectedCount() === rowBoxes.length;
                    if (headerSelect) {
                        headerSelect.checked = allChecked;
                        headerSelect.indeterminate = selectedCount() > 0 && !allChecked;
                    }
                    if (tableSelect) {
                        tableSelect.checked = allChecked;
                        tableSelect.indeterminate = selectedCount() > 0 && !allChecked;
                    }
                }

                function setAll(checked) {
                    rowBoxes.forEach(function(box) {
                        box.checked = checked;
                    });
                    refreshCount();
                }

                function bindHeaderEvents() {
                    headerSelect = document.getElementById('selectAll');
                    tableSelect = document.getElementById('selectAllTable');
                    countEl = document.getElementById('selectedLeadCount');

                    if (headerSelect) {
                        headerSelect.onchange = function() {
                            setAll(this.checked);
                        };
                    }
                    if (tableSelect) {
                        tableSelect.onchange = function() {
                            setAll(this.checked);
                        };
                    }
                }

                function bindTableInteractions() {
                    rowBoxes = Array.prototype.slice.call(
                        document.querySelectorAll('.crm-lead-table tbody input[type="checkbox"]')
                    );
                    rowBoxes.forEach(function(box) {
                        box.onchange = refreshCount;
                    });
                    refreshCount();

                    document.querySelectorAll('.crm-lead-row[data-href]').forEach(function(row) {
                        row.onclick = function(e) {
                            if (e.target.closest('input, a, button, label')) {
                                return;
                            }
                            var href = row.getAttribute('data-href');
                            if (href) {
                                window.location.href = href;
                            }
                        };
                    });
                }

                bindHeaderEvents();
                bindTableInteractions();

                // Live search on keyup / input
                var searchInput = document.getElementById('partnerSearch');
                var searchForm = document.querySelector('form.table-search');
                var searchTimer = null;
                var searchAbortCtrl = null;

                function doLiveSearch() {
                    if (!searchForm) return;

                    if (searchAbortCtrl) {
                        searchAbortCtrl.abort();
                    }
                    searchAbortCtrl = new AbortController();

                    var formData = new FormData(searchForm);
                    var params = new URLSearchParams(formData);
                    var url = searchForm.action + '?' + params.toString();

                    var tbody = document.querySelector('.crm-lead-table tbody');
                    if (tbody) {
                        tbody.style.opacity = '0.55';
                        tbody.style.transition = 'opacity 0.2s';
                    }

                    fetch(url, {
                            signal: searchAbortCtrl.signal,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(function(res) {
                            return res.text();
                        })
                        .then(function(html) {
                            var parser = new DOMParser();
                            var doc = parser.parseFromString(html, 'text/html');

                            // Update lead count title
                            var newTitle = doc.querySelector('.crm-lead-title');
                            var curTitle = document.querySelector('.crm-lead-title');
                            if (newTitle && curTitle) {
                                curTitle.innerHTML = newTitle.innerHTML;
                            }

                            // Update table body
                            var newTbody = doc.querySelector('.crm-lead-table tbody');
                            if (newTbody && tbody) {
                                tbody.innerHTML = newTbody.innerHTML;
                                tbody.style.opacity = '1';
                            }

                            // Update pagination
                            var newPagination = doc.querySelector('.table-pagination');
                            var curPagination = document.querySelector('.table-pagination');
                            if (newPagination && curPagination) {
                                curPagination.innerHTML = newPagination.innerHTML;
                            } else if (!newPagination && curPagination) {
                                curPagination.innerHTML = '';
                            }

                            // Update tabs
                            var newTabs = doc.querySelector('.crm-lead-tabs');
                            var curTabs = document.querySelector('.crm-lead-tabs');
                            if (newTabs && curTabs) {
                                curTabs.innerHTML = newTabs.innerHTML;
                            }

                            // Sync hidden search input in filterPanel
                            var filterPanelSearch = document.querySelector('#filterPanel input[name="search"]');
                            if (filterPanelSearch && searchInput) {
                                filterPanelSearch.value = searchInput.value;
                            }

                            // Update browser URL
                            window.history.replaceState({}, '', url);

                            // Recreate lucide icons
                            if (window.lucide) {
                                lucide.createIcons();
                            }

                            // Rebind table header and rows
                            bindHeaderEvents();
                            bindTableInteractions();
                        })
                        .catch(function(err) {
                            if (err.name !== 'AbortError' && tbody) {
                                tbody.style.opacity = '1';
                            }
                        });
                }

                if (searchInput && searchForm) {
                    searchInput.addEventListener('input', function() {
                        clearTimeout(searchTimer);
                        searchTimer = setTimeout(doLiveSearch, 250);
                    });

                    searchInput.addEventListener('keyup', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            clearTimeout(searchTimer);
                            doLiveSearch();
                        }
                    });

                    searchForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        clearTimeout(searchTimer);
                        doLiveSearch();
                    });
                }

                var resetFiltersBtn = document.getElementById('resetFilters');
                if (resetFiltersBtn) {
                    resetFiltersBtn.addEventListener('click', function() {
                        var form = document.getElementById('filterPanel');
                        if (form) {
                            form.querySelectorAll('select').forEach(function(s) {
                                s.selectedIndex = 0;
                            });
                            form.querySelectorAll('input[type="date"], input[type="text"]').forEach(function(
                                i) {
                                i.value = '';
                            });
                        }
                    });
                }
            });
        </script>

        @if (session('import_errors') && count(session('import_errors')) > 0)
            <div class="credit-modal" id="importErrorModal">
                <div class="credit-modal-box" style="max-width: 780px; width: 92%;">
                    <button class="credit-close" type="button"
                        onclick="document.getElementById('importErrorModal').classList.remove('active')">&times;</button>
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                        <div
                            style="width: 38px; height: 38px; border-radius: 50%; background: #fee2e2; display: flex; align-items: center; justify-content: center; color: #dc2626; flex-shrink: 0;">
                            <i data-lucide="alert-circle" style="width: 22px; height: 22px;"></i>
                        </div>
                        <div>
                            <h2 style="margin: 0; font-size: 18px; color: #1e293b;">Skipped Records
                                ({{ count(session('import_errors')) }})</h2>
                            <p style="margin: 2px 0 0; font-size: 13px; color: #64748b;">The following rows could not be
                                imported during Excel leads import:</p>
                        </div>
                    </div>
                    <div
                        style="max-height: 420px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px; margin: 16px 0;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
                            <thead>
                                <tr
                                    style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #64748b; font-weight: 600;">
                                    <th style="padding: 10px 14px; width: 85px;">Row #</th>
                                    <th style="padding: 10px 14px; width: 220px;">Lead / Company</th>
                                    <th style="padding: 10px 14px;">Reason for Skipping</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (session('import_errors') as $err)
                                    @php
                                        $rowNum = is_array($err) ? $err['row'] ?? '—' : '—';
                                        $leadName = is_array($err) ? $err['lead'] ?? '—' : '—';
                                        $reason = is_array($err) ? $err['reason'] ?? $err : $err;
                                    @endphp
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td
                                            style="padding: 9px 14px; font-weight: bold; color: #475569; white-space: nowrap;">
                                            Row {{ $rowNum }}</td>
                                        <td style="padding: 9px 14px; color: #1e293b; font-weight: 500;">
                                            {{ $leadName }}</td>
                                        <td style="padding: 9px 14px; color: #dc2626;">{{ $reason }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div style="text-align: right;">
                        <button type="button" class="submit-btn" style="padding: 8px 22px; width: auto;"
                            onclick="document.getElementById('importErrorModal').classList.remove('active')">Close</button>
                    </div>
                </div>
            </div>
        @endif

    </div>
@endsection
