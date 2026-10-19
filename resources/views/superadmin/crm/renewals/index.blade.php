@extends('superadmin.layouts.crm')

@section('content')
@php
    $searchQuery = request('search');
    $status = request('status');
    $dateFrom = request('from');
    $dateTo = request('to');
    $filtersOpen = request()->has('filter_open') || $status || $dateFrom || $dateTo;
@endphp

<div class="page-header">
    <div>
        <div class="breadcrumb welcome-text">
            <p><a href="{{ route('crm.dashboard') }}">Dashboard</a><span>/</span></p>
        </div>
        <h2>Renewals</h2>
    </div>
</div>

<section class="common-table-section crm-quo-list">
    <div class="common-table-card">
        <div class="common-table-header crm-quo-header">
            <div class="crm-quo-title">
                <p>
                    Showing <strong>{{ $renewals->firstItem() ?? 0 }} - {{ $renewals->lastItem() ?? 0 }}</strong>
                    from
                    <strong>{{ $renewals->total() }}</strong> Customers
                </p>
            </div>
            <form method="GET" action="{{ route('crm.renewals') }}" class="table-search">
                @if ($status)
                    <input type="hidden" name="status" value="{{ $status }}">
                @endif
                @if ($dateFrom)
                    <input type="hidden" name="from" value="{{ $dateFrom }}">
                @endif
                @if ($dateTo)
                    <input type="hidden" name="to" value="{{ $dateTo }}">
                @endif
                <input type="text" name="search" value="{{ $searchQuery }}" placeholder="Search Customers" autocomplete="off">
                <i data-lucide="search"></i>
            </form>
        </div>

        <form method="GET" action="{{ route('crm.renewals') }}" class="table-filter-wrap" id="filterPanel">
            @if ($searchQuery)
                <input type="hidden" name="search" value="{{ $searchQuery }}">
            @endif
            <div class="filter-grid">
                <div class="filter-group">
                    <h6>Status :</h6>
                    <div class="filter-options">
                        <select name="status" onchange="this.form.submit()">
                            <option value="">All</option>
                            <option value="expiring" {{ $status === 'expiring' ? 'selected' : '' }}>Expiring</option>
                            <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Active</option>
                        </select>
                    </div>
                </div>
                <div class="filter-group">
                    <h6>Renewal Date :</h6>
                    <div class="date-range">
                        <div class="date-input">
                            <input type="date" name="from" value="{{ $dateFrom }}" onchange="this.form.submit()">
                            <i data-lucide="calendar"></i>
                        </div>
                        <div class="date-input">
                            <input type="date" name="to" value="{{ $dateTo }}" onchange="this.form.submit()">
                            <i data-lucide="calendar"></i>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <div class="table-filter">
            <div class="border-line"></div>
            <button class="filter-btn{{ $filtersOpen ? ' is-open' : '' }}" id="toggleFilter" type="button">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="9" viewBox="0 0 12 9" fill="none">
                    <path d="M0.75 0.75H10.75M2.75 4.25H8.75M4.25 7.75H7.25" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <span>Filters</span>
            </button>
            <a href="{{ route('crm.renewals', array_filter(['search' => $searchQuery])) }}" class="filter-btn" id="resetFilters">
                <i data-lucide="rotate-ccw" width="15" height="15"></i>
                <span>Reset</span>
            </a>
        </div>

        <table class="crm-quo-table crm-renewal-table">
            <thead>
                <tr class="table-head">
                    <td>Id</td>
                    <td>Customer Information</td>
                    <td>Contact Information</td>
                    <td>Plan</td>
                    <td>Status</td>
                </tr>
            </thead>
            <tbody class="table-body">
                @forelse ($renewals as $row)
                    @php
                        $company = $row['company_name'] ?: $row['name'] ?: 'Customer';
                        $initial = strtoupper(substr($company, 0, 1));
                        $phone = trim(($row['mobile_code'] ? '+' . ltrim($row['mobile_code'], '+') . ' ' : '') . ($row['mobile'] ?: ''));
                    @endphp
                    <tr class="crm-quo-row crm-renewal-row table-row" data-href="{{ route('crm.renewals.show', $row['id']) }}" style="cursor:pointer;">
                        <td class="order-id" data-label="Id">
                            <span class="quotation-id">{{ $row['code'] }}</span>
                        </td>
                        <td class="customer" data-label="Customer Information">
                            <div class="avatar">{{ $initial }}</div>
                            <div>
                                <h5>{{ $company }}</h5>
                                <p>{{ $row['domain'] ?: ($row['name'] ?: '—') }}</p>
                            </div>
                        </td>
                        <td class="customer" data-label="Contact Information">
                            <div>
                                <h5>{{ $row['email'] ?: '—' }}</h5>
                                <p>{{ $phone !== '' ? $phone : '—' }}</p>
                            </div>
                        </td>
                        <td class="plan-info" data-label="Plan">
                            <div class="plan-icon renewal-plan-mark">{{ strtoupper(substr($row['service'] ?: 'P', 0, 1)) }}</div>
                            <div>
                                <h5>{{ $row['plan'] }}</h5>
                                <p class="plan-meta">
                                    @if ($row['quantity'])
                                        <span>{{ $row['quantity'] }} Licenses</span>
                                    @endif
                                    @if ($row['period'])
                                        <span>{{ $row['period'] }}</span>
                                    @endif
                                    @if ($row['rate'] !== null)
                                        <span>₹ {{ number_format($row['rate'], 2) }}</span>
                                    @endif
                                </p>
                            </div>
                        </td>
                        <td class="renewal-status" data-label="Status">
                            <div>
                                <span class="badge {{ $row['status'] === 'active' ? 'renewal-active' : 'expiring' }}">{{ $row['status_label'] }}</span>
                                <small class="{{ $row['status'] === 'active' ? 'renewal-active' : 'expiring' }}">{{ \Carbon\Carbon::parse($row['renewal_date'])->format('d M Y') }}</small>
                            </div>
                            <a href="{{ route('crm.renewals.show', $row['id']) }}" class="action-btn" title="View plan">
                                <i data-lucide="chevron-right"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr class="crm-quo-row crm-renewal-row crm-renewal-empty table-row">
                        <td colspan="5">No CRM orders with a renewal date.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="table-pagination">
            @if ($renewals->onFirstPage())
                <a href="#" class="page-btn"><i data-lucide="chevron-left"></i></a>
            @else
                <a href="{{ $renewals->previousPageUrl() }}" class="page-btn"><i data-lucide="chevron-left"></i></a>
            @endif

            @for ($page = 1; $page <= min(max($renewals->lastPage(), 1), 5); $page++)
                <a href="{{ $renewals->appends(request()->query())->url($page) }}" class="page-btn {{ $page == $renewals->currentPage() ? 'active' : '' }}">{{ $page }}</a>
            @endfor

            @if ($renewals->hasMorePages())
                <a href="{{ $renewals->nextPageUrl() }}" class="page-btn"><i data-lucide="chevron-right"></i></a>
            @else
                <a href="#" class="page-btn"><i data-lucide="chevron-right"></i></a>
            @endif
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('tr.table-row[data-href]').forEach(function (row) {
        row.addEventListener('click', function (e) {
            if (e.target.closest('a, button, input, label, select')) {
                return;
            }
            window.location.href = row.getAttribute('data-href');
        });
    });
});
</script>
@endsection
