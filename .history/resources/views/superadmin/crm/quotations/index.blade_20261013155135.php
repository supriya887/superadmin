@extends('superadmin.layouts.crm')

@section('content')
    @php
        $tab = $tab ?? 'all';
        $status = $status ?? null;
        $searchQuery = request('search');
        $avatarClasses = ['blue', 'purple', 'teal', 'yellow', 'red'];
        $isEmail = $tab === 'email';
        $isDiscount = $tab === 'discount';
        $headerClass = $isEmail ? 'es-quo-header' : 'crm-quo-header';
        $titleClass = $isEmail ? 'es-quo-title' : 'crm-quo-title';
        $tableClass = $isEmail ? 'es-quo-table' : 'crm-quo-table';
        $rowClass = $isEmail ? 'es-quo-row' : 'crm-quo-row';
        $dateType = request('date_type');
        $dateFrom = request('from');
        $dateTo = request('to');
        $filtersOpen =
            request()->has('filter_open') || ($status && $status !== 'all') || $dateType || $dateFrom || $dateTo;
    @endphp

    <div class="page-header">
        <div class="">
            <div class="breadcrumb welcome-text">
                <p><a href="{{ route('crm.dashboard') }}">Dashboard</a><span>/</span> </p>
            </div>
            <h2>Quotations </h2>
        </div>
        <div class="crm-actions">
            <a href="{{ route('crm.quotations.create') }}" class="crm-action-btn active">
                <i data-lucide="file-plus-corner"></i>
                <span>Add Quotations</span>
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <section class="common-table-section crm-quo-list{{ $isEmail ? ' crm-quo-email' : '' }}">

        <div class="crm-lead-tabs">
            <a href="{{ route('crm.quotations', array_filter(['search' => $searchQuery])) }}"
                class="{{ $tab === 'all' ? 'active' : '' }}">All </a>
            <a href="{{ route('crm.quotations', array_filter(['tab' => 'discount', 'search' => $searchQuery])) }}"
                class="{{ $isDiscount ? 'active' : '' }}">Discount Status</a>
            <a href="{{ route('crm.quotations', array_filter(['tab' => 'email', 'search' => $searchQuery])) }}"
                class="{{ $isEmail ? 'active' : '' }}">Customer Status</a>
        </div>

        <div class="common-table-card">

            <div class="common-table-header {{ $headerClass }}">
                <div class="{{ $titleClass }}">
                    <p>
                        Showing <strong>{{ $quotations->firstItem() ?? 0 }} - {{ $quotations->lastItem() ?? 0 }}</strong>
                        from
                        <strong>{{ $quotations->total() }}</strong> Customers
                    </p>
                </div>

                <form method="GET" action="{{ route('crm.quotations') }}" class="table-search">
                    @if ($tab !== 'all')
                        <input type="hidden" name="tab" value="{{ $tab }}">
                    @endif
                    @if ($status)
                        <input type="hidden" name="status" value="{{ $status }}">
                    @endif
                    @if ($dateType)
                        <input type="hidden" name="date_type" value="{{ $dateType }}">
                    @endif
                    @if ($dateFrom)
                        <input type="hidden" name="from" value="{{ $dateFrom }}">
                    @endif
                    @if ($dateTo)
                        <input type="hidden" name="to" value="{{ $dateTo }}">
                    @endif
                    <input type="text" id="partnerSearch" name="search" value="{{ $searchQuery }}"
                        placeholder="Search by Quotation" autocomplete="off">
                    <i data-lucide="search"></i>
                </form>
            </div>

            <form method="GET" action="{{ route('crm.quotations') }}"
                class="table-filter-wrap{{ $filtersOpen ? ' active' : '' }}" id="filterPanel">
                @if ($tab !== 'all')
                    <input type="hidden" name="tab" value="{{ $tab }}">
                @endif
                @if ($searchQuery)
                    <input type="hidden" name="search" value="{{ $searchQuery }}">
                @endif
                <div class="filter-grid">

                    <div class="filter-group">
                        <h6>Date Type :</h6>
                        <div class="filter-options">
                            <select name="date_type" onchange="this.form.submit()">
                                <option value="">Select</option>
                                <option value="created" {{ $dateType === 'created' ? 'selected' : '' }}>Created Date
                                </option>
                                <option value="sent" {{ $dateType === 'sent' ? 'selected' : '' }}>Shared Date</option>
                                <option value="follow_up" {{ $dateType === 'follow_up' ? 'selected' : '' }}>Follow Up Date
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="filter-group">
                        <h6>Date Range :</h6>
                        <div class="date-range">
                            <div class="date-input">
                                <input type="date" name="from" value="{{ $dateFrom }}"
                                    onchange="this.form.submit()">
                                <i data-lucide="calendar"></i>
                            </div>
                            <div class="date-input">
                                <input type="date" name="to" value="{{ $dateTo }}"
                                    onchange="this.form.submit()">
                                <i data-lucide="calendar"></i>
                            </div>
                        </div>
                    </div>

                    <div class="filter-group">
                        <h6>Status :</h6>
                        <div class="filter-options">
                            <select name="status" onchange="this.form.submit()">
                                <option value="" {{ !$status || $status === 'all' ? 'selected' : '' }}>Select
                                </option>
                                <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>Approved</option>
                                <option value="rejected" {{ $status === 'rejected' ? 'selected' : '' }}>Rejected</option>
                                <option value="expired" {{ $status === 'expired' ? 'selected' : '' }}>Expired</option>
                                <option value="accepted" {{ $status === 'accepted' ? 'selected' : '' }}>Accepted</option>
                            </select>
                        </div>
                    </div>

                    <div class="filter-group filter-action-group">
                        <h6>&nbsp;</h6>
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
                <a href="{{ route('crm.quotations', array_filter(['tab' => $tab !== 'all' ? $tab : null, 'filter_open' => 1])) }}"
                    class="filter-btn" id="resetFilters" role="button">
                    <i data-lucide="rotate-ccw" width="15" height="15"></i>
                    <span>Reset</span>
                </a>
            </div>

            @if ($isDiscount)
                <div class="partner-tabs-wrap">
                    <div class="partner-tabs">
                        <button type="button" class="partner-tab {{ $status === 'pending' ? 'active' : '' }}"
                            onclick="window.location.href='{{ route('crm.quotations', array_filter(['tab' => 'discount', 'status' => 'pending', 'search' => $searchQuery])) }}'">
                            Pending Requests (<span class="tab-count">{{ $statusCounts['pending'] ?? 0 }}</span>)
                        </button>
                        <button type="button" class="partner-tab {{ $status === 'approved' ? 'active' : '' }}"
                            onclick="window.location.href='{{ route('crm.quotations', array_filter(['tab' => 'discount', 'status' => 'approved', 'search' => $searchQuery])) }}'">
                            Approved Requests (<span class="tab-count">{{ $statusCounts['approved'] ?? 0 }}</span>)
                        </button>
                        <button type="button" class="partner-tab {{ $status === 'all' ? 'active' : '' }}"
                            onclick="window.location.href='{{ route('crm.quotations', array_filter(['tab' => 'discount', 'status' => 'all', 'search' => $searchQuery])) }}'">
                            All Requests (<span class="tab-count">{{ $statusCounts['all'] ?? 0 }}</span>)
                        </button>
                    </div>
                </div>
            @endif

            <table class="{{ $tableClass }}">
                <thead>
                    <tr class="table-head">
                        <td class="order-id">Id</td>
                        <td class="order-id">Contact Information</td>
                        <td class="order-id">Plan</td>
                        <td class="status-col">Status</td>
                        @if ($isEmail)
                            <td class="status-col">Invoice</td>
                        @endif
                        <td class="status-col">Assigned To</td>
                    </tr>
                </thead>
                <tbody class="table-body">
                    @forelse($quotations as $quotation)
                        @php
                            $color = $avatarClasses[$quotation->id % count($avatarClasses)];
                            $initial = strtoupper(substr($quotation->company_name ?: 'Q', 0, 1));
                            $assigned = $quotation->assigned_to ?: 'Unassigned';
                            $assignedInitial = strtoupper(substr($assigned, 0, 1));
                            $badge = strtolower($quotation->discount_status ?? 'pending');
                            $rawCustomer = strtolower((string) ($quotation->quotation_status ?? ''));
                            $rawInvoice = strtolower((string) ($quotation->invoice_status ?? ''));
                            if (
                                !in_array($rawCustomer, ['approved', 'rejected', 'shared'], true) &&
                                in_array($rawInvoice, ['approved', 'rejected'], true)
                            ) {
                                $rawCustomer = $rawInvoice;
                                $rawInvoice = 'not_sent';
                            } elseif (
                                !in_array($rawCustomer, ['approved', 'rejected'], true) &&
                                ($quotation->sent_at || $rawCustomer === 'shared')
                            ) {
                                $rawCustomer = 'shared';
                            }

                            if ($rawCustomer === 'approved') {
                                $customerBadge = 'approved';
                                $customerStatusLabel = 'Approved';
                            } elseif ($rawCustomer === 'rejected') {
                                $customerBadge = 'rejected';
                                $customerStatusLabel = 'Rejected';
                            } elseif ($rawCustomer === 'shared') {
                                $customerBadge = 'pending';
                                $customerStatusLabel = 'Shared';
                            } else {
                                $customerBadge = 'pending';
                                $customerStatusLabel = 'Not shared';
                            }

                            if ($rawCustomer === 'shared') {
                                $invoiceBadge = '';
                                $invoiceLabel = '—';
                            } elseif ($rawCustomer !== 'approved') {
                                $invoiceBadge = 'not-sent';
                                $invoiceLabel = '—';
                            } elseif ($rawInvoice === 'approved') {
                                $invoiceBadge = 'approved';
                                $invoiceLabel = 'Approved';
                            } elseif ($rawInvoice === 'rejected') {
                                $invoiceBadge = 'rejected';
                                $invoiceLabel = 'Rejected';
                            } elseif ($rawInvoice === 'sent') {
                                $invoiceBadge = 'pending';
                                $invoiceLabel = 'Sent';
                            } else {
                                $invoiceBadge = 'not-sent';
                                $invoiceLabel = 'Not sent';
                            }

                            $rowStatus = $isDiscount ? $badge : $customerBadge;
                            $rowStatusLabel = $isDiscount
                                ? ucfirst($quotation->discount_status ?? 'Pending')
                                : $customerStatusLabel;
                            $plans = is_array($quotation->plans) ? $quotation->plans : [];
                            $extraPlans = max(0, count($plans) - 1);
                            $quoteEmails = collect($quotation->sent_emails ?? [])
                                ->map(fn ($email) => strtolower(trim((string) $email)))
                                ->filter()
                                ->unique()
                                ->values();
                            $invoiceEmails = collect($quotation->invoice_sent_emails ?? [])
                                ->map(fn ($email) => strtolower(trim((string) $email)))
                                ->filter()
                                ->unique()
                                ->values();
                        @endphp
                        <tr class="{{ $rowClass }} table-row"
                            data-href="{{ $isEmail ? route('crm.quotations.activity', $quotation) : route('crm.quotations.show', $quotation) }}" style="cursor:pointer;">
                            <td class="order-id" data-label="Id">
                                <span class="quotation-id">{{ $quotation->quotation_no }}</span>
                                {{-- @if (($quotation->version_total ?? 1) > 1)
                                @if ($quotation->is_latest_version)
                                    <span class="badge approved">Latest</span>
                                @else
                                    <span class="badge expired">Old</span>
                                @endif
                            @endif --}}
                            </td>
                            <td class="customer" data-label="Contact">
                                <div class="avatar {{ $color }}">{{ $initial }}</div>
                                <div>
                                    <h5>{{ $quotation->company_name }}</h5>
                                    <p>
                                        {{ $quotation->name }}
                                        @if ($quotation->designation)
                                            - {{ $quotation->designation }}
                                        @endif
                                    </p>
                                </div>
                            </td>
                            <td class="plan-info" data-label="Plan">
                                <div class="plan-icon">
                                    <img src="https://img.icons8.com/color/48/microsoft.png" alt="Plan">
                                </div>
                                <div>
                                    <h5>
                                        {{ $quotation->plan_name ?: '-' }}
                                        @if ($extraPlans > 0)
                                            <a href="{{ route('crm.quotations.show', $quotation) }}">+{{ $extraPlans }}
                                                More</a>
                                        @endif
                                    </h5>
                                    <p class="plan-meta">
                                        <span>{{ $quotation->quantity ?? 0 }} Licenses</span>
                                        <span>{{ $quotation->quotation_period ?: '-' }}</span>
                                        <span>₹ {{ number_format((float) $quotation->total_amount, 2) }}</span>
                                        <span>{{ (float) $quotation->discount }}%</span>
                                    </p>
                                </div>
                            </td>
                            <td class="status-col" data-label="Status">
                                <span class="badge {{ $rowStatus }}">
                                    {{ $rowStatusLabel }}
                                </span>
                              
                            </td>
                            @if ($isEmail)
                                <td class="status-col" data-label="Invoice">
                                    @if ($invoiceBadge !== '')
                                        <span class="badge {{ $invoiceBadge }}">{{ $invoiceLabel }}</span>
                                    @else
                                        {{ $invoiceLabel }}
                                    @endif
                                   
                                </td>
                            @endif
                            <td class="plan-action" data-label="Assigned To">
                                <div class="assigned-user">
                                    <div class="user-info">
                                        <div class="avatar-flex">
                                            <div class="mini-avatar primary">{{ $assignedInitial }}</div>
                                            <h5>{{ $assigned }}</h5>
                                        </div>
                                        <small>
                                            @if ($quotation->sent_at)
                                                Shared {{ $quotation->sent_at->format('d M, Y h:i A') }}
                                            @elseif($quotation->follow_up_date)
                                                Follow up on&nbsp;
                                                <strong>{{ $quotation->follow_up_date->format('d M, Y h:i A') }}</strong>
                                            @else
                                                Added {{ optional($quotation->created_at)->format('d M, Y h:i A') }}
                                            @endif
                                        </small>
                                    </div>
                                    <div class="actions">
                                        <button class="action-btn" type="button" title="Quotation actions"
                                            data-approved="{{ $rawCustomer === 'approved' ? '1' : '0' }}"
                                            data-locked="{{ $quotation->isLocked() ? '1' : '0' }}"
                                            data-view="{{ route('crm.quotations.show', $quotation) }}"
                                            data-history="{{ route('crm.quotations.activity', $quotation) }}"
                                            data-edit="{{ route('crm.quotations.edit', $quotation) }}"
                                            data-download="{{ route('crm.quotations.download', $quotation) }}"
                                            data-invoice-download="{{ route('crm.quotations.invoice.download', $quotation) }}"
                                            data-id="{{ $quotation->id }}" data-email="{{ $quotation->email }}"
                                            onclick="openQuotationMenu(this, event)">
                                            <i data-lucide="chevron-right"></i>
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="{{ $rowClass }} table-row">
                            <td class="order-id"></td>
                            <td class="customer" colspan="{{ $isEmail ? 5 : 4 }}">No quotations yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="table-pagination">
                @if ($quotations->onFirstPage())
                    <a href="#" class="page-btn"><i data-lucide="chevron-left"></i></a>
                @else
                    <a href="{{ $quotations->previousPageUrl() }}" class="page-btn"><i
                            data-lucide="chevron-left"></i></a>
                @endif

                @php $lastPage = max($quotations->lastPage(), 1); @endphp
                @for ($page = 1; $page <= min($lastPage, 5); $page++)
                    <a href="{{ $quotations->url($page) }}"
                        class="page-btn {{ $page == $quotations->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                @endfor

                @if ($quotations->hasMorePages())
                    <a href="{{ $quotations->nextPageUrl() }}" class="page-btn"><i data-lucide="chevron-right"></i></a>
                @else
                    <a href="#" class="page-btn"><i data-lucide="chevron-right"></i></a>
                @endif
            </div>

        </div>
    </section>
    <script>
        function openQuotationMenu(btn, event) {
            var approved = btn.getAttribute('data-approved') === '1';
            var locked = btn.getAttribute('data-locked') === '1';
            document.getElementById('quotationPopupView').href = btn.getAttribute('data-view');
            var historyLink = document.getElementById('quotationPopupHistory');
            if (historyLink) {
                historyLink.href = btn.getAttribute('data-history');
            }
            var edit = document.getElementById('quotationPopupEdit');
            edit.href = btn.getAttribute('data-edit');
            edit.hidden = locked;
            document.getElementById('quotationPopupDownload').href = btn.getAttribute('data-download');
            var invoice = document.getElementById('quotationPopupInvoice');
            invoice.hidden = !approved;
            if (approved) {
                document.getElementById('quotationPopupInvoiceDownload').href = btn.getAttribute('data-invoice-download');
            }
            document.getElementById('sendQuotationId').value = btn.getAttribute('data-id');
            document.getElementById('sendQuotationEmails').value = btn.getAttribute('data-email');
            document.getElementById('sendQuotationPrimaryEmail').value = (btn.getAttribute('data-email') || '')
                .toLowerCase();
            var discount = document.querySelector('.quotation-popup .discount-btn');
            if (discount) {
                discount.hidden = locked;
            }
            toggleQuotationPopup(btn, event);
        }

        function bindRowClicks() {
            document.querySelectorAll('tr.table-row[data-href]').forEach(function(row) {
                row.onclick = function(e) {
                    if (e.target.closest('a, button, input, label')) {
                        return;
                    }
                    var href = row.getAttribute('data-href');
                    if (href) {
                        window.location.href = href;
                    }
                };
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            bindRowClicks();

            // Live search on keyup / input (same condition as Leads)
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

                var tbody = document.querySelector('tbody.table-body');
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

                        // Update count title
                        var newTitle = doc.querySelector('.crm-quo-title, .es-quo-title');
                        var curTitle = document.querySelector('.crm-quo-title, .es-quo-title');
                        if (newTitle && curTitle) {
                            curTitle.innerHTML = newTitle.innerHTML;
                        }

                        // Update table body
                        var newTbody = doc.querySelector('tbody.table-body');
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

                        // Update tabs if present
                        var newTabs = doc.querySelector('.crm-lead-tabs');
                        var curTabs = document.querySelector('.crm-lead-tabs');
                        if (newTabs && curTabs) {
                            curTabs.innerHTML = newTabs.innerHTML;
                        }

                        var newPartnerTabs = doc.querySelector('.partner-tabs');
                        var curPartnerTabs = document.querySelector('.partner-tabs');
                        if (newPartnerTabs && curPartnerTabs) {
                            curPartnerTabs.innerHTML = newPartnerTabs.innerHTML;
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

                        // Rebind row click events
                        bindRowClicks();
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
@endsection
