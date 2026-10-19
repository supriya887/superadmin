@extends('superadmin.layouts.crm')

@section('content')
@php
    $company = $row['company_name'] ?: $row['name'] ?: 'Customer';
    $initial = strtoupper(substr($company, 0, 1));
    $phone = trim(($row['mobile_code'] ? '+' . ltrim($row['mobile_code'], '+') . ' ' : '') . ($row['mobile'] ?: ''));
    $users = $row['quantity'];
    $price = $row['rate'] !== null ? '₹' . number_format($row['rate'], 0) : null;
    $period = $row['period'] ?: null;
@endphp

<div class="page-header">
    <div>
        <div class="breadcrumb welcome-text">
            <p><a href="{{ route('crm.dashboard') }}">Dashboard</a><span>/</span></p>
        </div>
        <h2>Plan detail</h2>
    </div>
    <div class="back">
        <a href="{{ route('crm.renewals') }}" class="back-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 12H5" />
                <path d="M12 19l-7-7 7-7" />
            </svg>
            <span>Back</span>
        </a>
    </div>
</div>

<div class="renewal-detail">
    <div class="renewal-main">
        <section class="common-table-card renewal-customer-card">
            <div class="customer">
                <div class="avatar">{{ $initial }}</div>
                <div>
                    <h5>{{ $company }}</h5>
                    <p>Customer Id : {{ $row['code'] }}@if($row['name']) &nbsp; {{ $row['name'] }}@endif</p>
                </div>
            </div>
            <div class="renewal-customer-contact">
                <div>
                    <span>Email</span>
                    <strong>{{ $row['email'] ?: '—' }}</strong>
                </div>
                <div>
                    <span>Contact No.</span>
                    <strong>{{ $phone !== '' ? $phone : '—' }}</strong>
                </div>
            </div>
        </section>

        <section class="common-table-card renewal-plan-card">
            <div class="renewal-plan-head">
                <h3><i data-lucide="file-text"></i> Subscription Details</h3>
                <span class="badge {{ $row['status'] === 'active' ? 'renewal-active' : 'expiring' }}">{{ $row['status_label'] }}</span>
            </div>

            <div class="renewal-plan-title">
                <div class="plan-icon renewal-plan-mark">{{ strtoupper(substr($row['service'] ?: 'P', 0, 1)) }}</div>
                <div>
                    <h4>{{ $row['plan'] }}</h4>
                    @if ($price)
                        <p>{{ $price }}@if($period) Per User / Per {{ $period }}@endif</p>
                    @endif
                </div>
                <span class="renewal-provider">Provider : {{ $row['service'] }}</span>
            </div>

            <div class="renewal-plan-rows">
                <div>
                    <span>License</span>
                    <strong>
                        {{ $users ? $users . ' ' . ($users === 1 ? 'User' : 'Users') : '—' }}
                        <a href="{{ route('crm.quotations.create', ['lead_id' => $row['lead_id'], 'deal_type' => 'license_addon']) }}" class="renewal-add">+ Add</a>
                    </strong>
                </div>
                <div>
                    <span>Domain</span>
                    <strong>{{ $row['domain'] ?: '—' }}</strong>
                </div>
                <div>
                    <span>Billing Cycle</span>
                    <strong>{{ $period ?: '—' }}</strong>
                </div>
                <div>
                    <span>Start Date</span>
                    <strong>{{ $row['start_date'] ? \Carbon\Carbon::parse($row['start_date'])->format('d M Y') : '—' }}</strong>
                </div>
                <div>
                    <span>Renewal Date</span>
                    <strong class="renewal-date {{ $row['status'] }}">{{ \Carbon\Carbon::parse($row['renewal_date'])->format('d M Y') }}</strong>
                </div>
            </div>

            <div class="renewal-actions">
                <a href="{{ route('crm.quotations.create', ['lead_id' => $row['lead_id'], 'deal_type' => 'upgrade']) }}" class="renewal-upgrade">Upgrade</a>
                <a href="{{ route('crm.quotations.create', ['lead_id' => $row['lead_id'], 'deal_type' => 'renewal']) }}" class="renewal-renew">Renew</a>
            </div>
        </section>
    </div>

    <aside class="common-table-card renewal-timeline">
        <div class="renewal-plan-head">
            <h3><i data-lucide="history"></i> Timeline</h3>
            @if ($row['lead_id'])
                <a href="{{ route('crm.leads.show', $row['lead_id']) }}">View All</a>
            @endif
        </div>
        <p class="renewal-timeline-label">Activity</p>
        <ol>
            @foreach ($timeline as $event)
                <li>
                    <strong>{{ $event['title'] }}</strong>
                    <p>{{ $event['text'] }}</p>
                    @if ($event['when'])
                        <small>{{ $event['when'] }}</small>
                    @endif
                </li>
            @endforeach
        </ol>
    </aside>
</div>
@endsection
