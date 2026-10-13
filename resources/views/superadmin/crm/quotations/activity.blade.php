@extends('superadmin.layouts.crm')

@section('content')
@php
    $quoteLabels = [
        'not_shared' => 'Not shared',
        'shared' => 'Shared, approval pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];
    $invoiceLabels = [
        'not_sent' => 'Not sent',
        'sent' => 'Sent, approval pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];
@endphp

<div class="page-header">
    <div>
        <div class="breadcrumb welcome-text">
            <p>
                <a href="{{ route('crm.dashboard') }}">Dashboard</a>
                <span>/</span>
                <a href="{{ route('crm.quotations') }}">Quotations</a>
                <span>/</span>
                <a href="{{ route('crm.quotations.show', $quotation) }}">{{ $quotation->quotation_no }}</a>
            </p>
        </div>
        <h2>Send &amp; approval history</h2>
    </div>
    <div class="back">
        <a href="{{ route('crm.quotations.show', $quotation) }}" class="back-btn">
            <span>Back</span>
        </a>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="quo-activity-page">
    <div class="stats-grid">
        <div class="summary-card rpt-kpi tone-teal">
            <p>Quotation sent to</p>
            <h3>{{ max($quoteEmails->count(), $quotation->sent_at ? 1 : 0) }}</h3>
            <small>
                {{ $quoteEmails->isNotEmpty() ? $quoteEmails->implode(', ') : ($quotation->email ?: 'Not sent yet') }}
                @if(!empty($quoteCc) && $quoteCc->isNotEmpty())
                    <br>CC: {{ $quoteCc->implode(', ') }}
                @endif
            </small>
        </div>
        <div class="summary-card rpt-kpi tone-green">
            <p>Quotation first approval</p>
            <h3>{{ $quoteStatus === 'approved' ? 'Approved' : ($quoteStatus === 'rejected' ? 'Rejected' : 'Pending') }}</h3>
            <small>
                @if(in_array($quoteStatus, ['approved', 'rejected'], true))
                    {{ $quotation->responded_email ?: '—' }}
                    @if($quotation->responded_at)
                        · {{ $quotation->responded_at->format('d M Y, h:i A') }}
                    @endif
                @else
                    Waiting for customer
                @endif
            </small>
        </div>
        <div class="summary-card rpt-kpi tone-purple">
            <p>Invoice sent to</p>
            <h3>{{ max($invoiceEmails->count(), $quotation->invoice_sent_at ? 1 : 0) }}</h3>
            <small>
                {{ $invoiceEmails->isNotEmpty() ? $invoiceEmails->implode(', ') : 'Not sent yet' }}
                @if(!empty($invoiceCc) && $invoiceCc->isNotEmpty())
                    <br>CC: {{ $invoiceCc->implode(', ') }}
                @endif
            </small>
        </div>
        <div class="summary-card rpt-kpi tone-orange">
            <p>Invoice approved by</p>
            <h3>{{ $invoiceStatus === 'approved' ? 'Approved' : ($invoiceStatus === 'rejected' ? 'Rejected' : 'Pending') }}</h3>
            <small>
                @if(in_array($invoiceStatus, ['approved', 'rejected'], true))
                    {{ $quotation->invoice_responded_email ?: ($quotation->responded_email ?: '—') }}
                    @if($quotation->invoice_responded_at)
                        · {{ $quotation->invoice_responded_at->format('d M Y, h:i A') }}
                    @endif
                @else
                    Waiting for invoice response
                @endif
            </small>
        </div>
    </div>

    <section class="common-table-section">
        <div class="common-table-card rpt-card">
            <div class="common-table-header">
                <div>
                    <h3>Timeline</h3>
                    <p class="rpt-sub">Who received the mail and who responded, in order</p>
                </div>
            </div>
            <ol class="quo-activity-timeline">
                @foreach ($events as $event)
                    <li class="tone-{{ $event['tone'] }}">
                        <h4>{{ $event['title'] }}</h4>
                        <p>{{ $event['text'] }}</p>
                        @if(!empty($event['when']))
                            <small>{{ $event['when'] }}</small>
                        @endif
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="common-table-section">
        <div class="common-table-card rpt-card">
            <div class="common-table-header">
                <div>
                    <h3>Update status</h3>
                    <p class="rpt-sub">Manual override if needed</p>
                </div>
            </div>
            <form method="POST" action="{{ route('crm.quotations.status', $quotation) }}" class="quotation-top-meta">
                @csrf
                <div class="qmeta-field">
                    <span>Quotation</span>
                    <select name="quotation_status" class="quotation-status-select is-{{ $quoteStatus }}" onchange="this.form.submit()">
                        @foreach($quoteLabels as $value => $label)
                            <option value="{{ $value }}" @selected($quoteStatus === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="qmeta-field">
                    <span>Invoice</span>
                    @if($quoteStatus === 'approved')
                        <select name="invoice_status" class="quotation-status-select is-{{ $invoiceStatus }}" onchange="this.form.submit()">
                            @foreach($invoiceLabels as $value => $label)
                                <option value="{{ $value }}" @selected($invoiceStatus === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    @else
                        <strong>—</strong>
                    @endif
                </div>
            </form>
        </div>
    </section>
</div>
@endsection
