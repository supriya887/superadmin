@extends('superadmin.layouts.crm')

@section('content')
    @php
        $searchQuery = request('search');
        $avatarClasses = ['blue', 'purple', 'teal', 'yellow', 'red'];
    @endphp

    <div class="page-header">
        <div class="">
            <div class="breadcrumb welcome-text">
                <p><a href="{{ route('crm.dashboard') }}">Dashboard</a><span>/</span> CRM Deals</p>
            </div>
            <h2>CRM Deals</h2>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <section class="common-table-section crm-quo-list">
        <div class="common-table-card">
            <div class="common-table-header crm-quo-header">
                <div class="crm-quo-title">
                    <p>
                        Showing <strong>{{ $deals->firstItem() ?? 0 }} - {{ $deals->lastItem() ?? 0 }}</strong>
                        from
                        <strong>{{ $deals->total() }}</strong> approved deals
                    </p>
                </div>
                <form method="GET" action="{{ route('crm.deals') }}" class="table-search">
                    <input type="text" name="search" value="{{ $searchQuery }}"
                        placeholder="Search company, quote, employee" autocomplete="off">
                    <i data-lucide="search"></i>
                </form>
            </div>

            <table class="crm-quo-table">
                <thead>
                    <tr class="table-head">
                        <td class="order-id">Id</td>
                        <td class="order-id">Contact Information</td>
                        <td class="order-id">Plan</td>
                        <td class="status-col">Status</td>
                        <td class="status-col">Assigned To</td>
                    </tr>
                </thead>
                <tbody class="table-body">
                    @forelse($deals as $deal)
                        @php
                            $color = $avatarClasses[$deal->id % count($avatarClasses)];
                            $initial = strtoupper(substr($deal->company_name ?: 'D', 0, 1));
                            $employee = $deal->employeeName();
                            $employeeInitial = strtoupper(substr($employee, 0, 1));
                            $converted = (int) $deal->order_id > 0;
                            $plans = is_array($deal->plans) ? $deal->plans : [];
                            $extraPlans = max(0, count($plans) - 1);
                        @endphp
                        <tr class="crm-quo-row table-row">
                            <td class="order-id" data-label="Id">
                                <a href="{{ route('crm.quotations.show', $deal) }}" class="quotation-id">{{ $deal->quotation_no }}</a>
                            </td>
                            <td class="customer" data-label="Contact">
                                <div class="avatar {{ $color }}">{{ $initial }}</div>
                                <div>
                                    <h5>{{ $deal->company_name }}</h5>
                                    <p>
                                        {{ $deal->name }}
                                        @if ($deal->lead_id)
                                            · Lead #{{ $deal->lead_id }}
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
                                        {{ $deal->plan_name ?: '-' }}
                                        @if ($extraPlans > 0)
                                            <a href="{{ route('crm.quotations.show', $deal) }}">+{{ $extraPlans }} More</a>
                                        @endif
                                    </h5>
                                    <p class="plan-meta">
                                        <span>{{ $deal->dealTypeLabel() }}</span>
                                        <span>{{ $deal->quantity ?? 0 }} Licenses</span>
                                        <span>{{ $deal->quotation_period ?: '-' }}</span>
                                        <span>₹ {{ number_format((float) $deal->total_amount, 2) }}</span>
                                    </p>
                                </div>
                            </td>
                            <td class="status-col" data-label="Status">
                                @if ($converted)
                                    <span class="badge approved">{{ $deal->crm_order_no ?: ('#' . $deal->order_id) }}</span>
                                @else
                                    <span class="badge pending">Pending</span>
                                @endif
                            </td>
                            <td class="plan-action" data-label="Assigned To">
                                <div class="assigned-user">
                                    <div class="user-info">
                                        <div class="avatar-flex">
                                            <div class="mini-avatar primary">{{ $employeeInitial }}</div>
                                            <h5>{{ $employee }}</h5>
                                        </div>
                                        <small>Added {{ optional($deal->created_at)->format('d M, Y h:i A') }}</small>
                                    </div>
                                    <div class="actions">
                                        <a href="{{ route('crm.deals.view', $deal) }}" class="action-btn" title="View order">
                                            <i data-lucide="eye"></i>
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="crm-quo-row table-row">
                            <td class="order-id"></td>
                            <td class="customer" colspan="4">No CRM deals yet. Approve both quotation and invoice first.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="table-pagination">
                @if ($deals->onFirstPage())
                    <a href="#" class="page-btn"><i data-lucide="chevron-left"></i></a>
                @else
                    <a href="{{ $deals->previousPageUrl() }}" class="page-btn"><i data-lucide="chevron-left"></i></a>
                @endif

                @php $lastPage = max($deals->lastPage(), 1); @endphp
                @for ($page = 1; $page <= min($lastPage, 5); $page++)
                    <a href="{{ $deals->url($page) }}"
                        class="page-btn {{ $page == $deals->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                @endfor

                @if ($deals->hasMorePages())
                    <a href="{{ $deals->nextPageUrl() }}" class="page-btn"><i data-lucide="chevron-right"></i></a>
                @else
                    <a href="#" class="page-btn"><i data-lucide="chevron-right"></i></a>
                @endif
            </div>
        </div>
    </section>
@endsection
