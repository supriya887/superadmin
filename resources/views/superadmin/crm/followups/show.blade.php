@extends('superadmin.layouts.crm')

@section('content')
@php
    $company = $lead->company_name ?: $lead->name ?: 'Lead';
    $initial = strtoupper(substr($company, 0, 1));
@endphp

<div class="page-header">
    <div>
        <div class="breadcrumb welcome-text">
            <p>
                <a href="{{ route('crm.dashboard') }}">Dashboard</a>
                <span>/</span>
                <a href="{{ route('crm.followups') }}">Follow-Ups</a>
            </p>
        </div>
        <h2>Follow-Up Detail</h2>
    </div>
    <div class="back">
        <a href="{{ route('crm.followups') }}" class="back-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 12H5" />
                <path d="M12 19l-7-7 7-7" />
            </svg>
            <span>Back</span>
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="followup-detail">
    <section class="common-table-section">
        <div class="common-table-card p-16">
            <div class="card-header">
                <h3>{{ $company }}</h3>
                <a href="{{ route('crm.leads.show', $lead) }}" class="add-credit-btn">View Lead</a>
            </div>
            <div class="followup-lead-meta">
                <p>{{ $lead->name }}@if($lead->designation) — {{ $lead->designation }}@endif</p>
                <p>{{ $lead->email }} @if($lead->mobile)· {{ $lead->mobile }}@endif</p>
                <p>
                    Status: <strong>{{ $lead->lead_status ?: 'New' }}</strong>
                    · Assigned: <strong>{{ $lead->assigned_to ?: 'Unassigned' }}</strong>
                </p>
                @if($lead->follow_up_date)
                    <p>Next follow-up: <strong>{{ $lead->follow_up_date->format('d M Y, g:i A') }}</strong></p>
                @endif
            </div>

            <div class="card-header" style="margin-top:18px;">
                <h3>Follow-Up History ({{ $followups->count() }})</h3>
            </div>

            <div class="followup-log">
                @forelse($followups as $row)
                    <div class="followup-log-item">
                        <div class="followup-log-top">
                            <strong>
                                @if($row->follow_up_date)
                                    {{ \Carbon\Carbon::parse($row->follow_up_date)->format('d M Y, g:i A') }}
                                @else
                                    No date
                                @endif
                            </strong>
                            <span class="badge {{ strtolower(str_replace(' ', '-', $row->lead_status ?: 'new')) }}">
                                {{ $row->lead_status ?: 'New' }}
                            </span>
                        </div>
                        <p>{{ $row->assigned_to ?: 'Unassigned' }}</p>
                        @if($row->note)
                            <p class="followup-log-note">{{ $row->note }}</p>
                        @endif
                        @if($row->created_at)
                            <small>Added {{ \Carbon\Carbon::parse($row->created_at)->format('d M Y, g:i A') }}</small>
                        @endif
                    </div>
                @empty
                    <p class="lead-empty-note">No follow-ups yet. Add one on the right.</p>
                @endforelse
            </div>
        </div>
    </section>

    <section class="common-table-section">
        <div class="common-table-card p-16">
            <div class="card-header">
                <h3>Add Follow-Up</h3>
            </div>
            <form method="POST" action="{{ route('crm.followups.store', $lead) }}" class="followup-form" novalidate>
                @csrf
                <div class="form-group">
                    <label>Lead Status <span>*</span></label>
                    <select name="lead_status" required>
                        <option value="">Select Status</option>
                        @foreach (['New', 'Contacted', 'Qualified', 'Negotiation', 'Quote Send', 'Won', 'Lost', 'Nurturing'] as $st)
                            <option value="{{ $st }}" {{ in_array((string) $lead->lead_status, $st === 'Quote Send' ? ['Quote Send', 'Quote Sent', 'Quotation Sent'] : [$st], true) ? 'selected' : '' }}>{{ $st }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Assigned To</label>
                    <select name="assigned_to">
                        <option value="">Select Employee</option>
                        @foreach(($assignees ?? collect()) as $assignee)
                            <option value="{{ $assignee }}" {{ (string) $lead->assigned_to === (string) $assignee ? 'selected' : '' }}>{{ $assignee }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Follow Up Date & Time</label>
                    <div class="date-input">
                        <input type="datetime-local" name="follow_up_date">
                    </div>
                </div>
                <div class="form-group">
                    <label>Note</label>
                    <textarea name="follow_up_note" placeholder="What was discussed / next step"></textarea>
                </div>
                <div class="submit-wrap">
                    <button type="submit" class="submit-btn">Save Follow-Up</button>
                </div>
            </form>
        </div>
    </section>
</div>
@endsection
