@extends('superadmin.layouts.crm')

@section('content')

<div class="page-header">
    <div>
        <p class="welcome-text">Welcome,</p>
        <h2>{{ $userName }}</h2>
    </div>
</div>

<div class="summary-section">
    <div class="common-table-header">
        <div class="summary-header">
            <h3>Account Summary</h3>
        </div>
        <div class="date-range">
            <div class="date-input">
                <input
                    type="text"
                    placeholder="From"
                    onfocus="this.type='date'">
                <i data-lucide="calendar-days"></i>
            </div>
            <div class="date-input">
                <input
                    type="text"
                    placeholder="To"
                    onfocus="this.type='date'">
                <i data-lucide="calendar-days"></i>
            </div>
        </div>
    </div>
    <div class="summary-grid stats-grid">
        <div class="summary-card blue">
            <div class="card-icon">
                <i data-lucide="phone-call"></i>
            </div>
            <p>Total Leads</p>
            <h3>{{ number_format($totalLeads) }}</h3>
        </div>
        <div class="summary-card green">
            <div class="card-icon">
                <i data-lucide="clipboard-check"></i>
            </div>
            <p>Pending Follow-ups</p>
            <h3>{{ number_format($pendingFollowUps) }}</h3>
        </div>
        <div class="summary-card yellow">
            <div class="card-icon">
                <i data-lucide="clock-3"></i>
            </div>
            <p>Pending Quotations</p>
            <h3>{{ number_format($pendingQuotations) }}</h3>
        </div>
        <div class="summary-card purple">
            <div class="card-icon">
                <i data-lucide="calendar-clock"></i>
            </div>
            <p>Meetings Scheduled</p>
            <h3>{{ number_format($meetingsScheduled) }}</h3>
        </div>
        <div class="summary-card red">
            <div class="card-icon">
                <i data-lucide="circle-alert"></i>
            </div>
            <p>Overdue Tasks</p>
            <h3>{{ number_format($overdueTasks) }}</h3>
        </div>
    </div>
</div>

<div class="new-quick-actions">
    <a href="{{ route('crm.leads.create') }}" class="quick-btn">
        <i data-lucide="plus"></i>
        <span>Add Leads</span>
    </a>

    <a href="{{ route('crm.leads.uploads') }}" class="quick-btn">
        <i data-lucide="file-up"></i>
        <span>Upload Leads</span>
    </a>

    <a href="{{ route('crm.quotations') }}" class="quick-btn">
        <i data-lucide="file-plus-2"></i>
        <span>Add Quotation</span>
    </a>

    <a href="{{ route('crm.employee.create') }}" class="quick-btn">
        <i data-lucide="user-plus"></i>
        <span>Add Employee</span>
    </a>
</div>

<section class="common-table-section">
    <div class="common-table-card">
        <div class="common-table-header">
            <div>
                <h3>TEAM PERFORMANCE</h3>
            </div>
            <div class="sales-filter">
                <button>Today</button>
                <button class="active">Last 7 days</button>
                <button>Last Month</button>
            </div>
        </div>
        <div class="team-pre-table">
            <table>
                <tbody class="table-body">
                    @forelse($team as $member)
                        <tr class="team-pr-row table-row">
                            <td class="customer">
                                <div class="avatar purple">{{ strtoupper(substr($member->name ?? 'T', 0, 1)) }}</div>
                                <div>
                                    <h5>{{ $member->name ?? '-' }}</h5>
                                    <p>{{ $member->role ?? '-' }}</p>
                                </div>
                            </td>
                            <td class="team-num">
                                <small>LEADS</small>
                                <strong>{{ $member->leads ?? 0 }}</strong>
                            </td>
                            <td class="team-num">
                                <small>CALLING</small>
                                <strong>{{ $member->calling ?? 0 }}</strong>
                            </td>
                            <td class="team-num">
                                <small>DEALS</small>
                                <strong>{{ $member->deals ?? 0 }}</strong>
                            </td>
                            <td class="team-num">
                                <small>CONV</small>
                                <strong>{{ $member->conv ?? 0 }}</strong>
                            </td>
                        </tr>
                    @empty
                        <tr class="team-pr-row table-row">
                            <td colspan="5">No team performance data available.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="approval-activity-section">
    <div class="approval-column">
        <div class="common-table-header">
            <h3>Pending Approvals</h3>
            <a href="{{ route('crm.followups') }}" class="view-all">View All Tasks</a>
        </div>

        <div class="approval-list">
            <div class="approval-item">
                <div class="approval-icon blue">
                    <i data-lucide="percent"></i>
                </div>
                <span>Discount Approval</span>
                <strong>{{ $discountApprovals }}</strong>
            </div>
            <div class="approval-item">
                <div class="approval-icon orange">
                    <i data-lucide="phone-call"></i>
                </div>
                <span>Pending Quotations</span>
                <strong>{{ $quotationApprovals }}</strong>
            </div>
            <div class="approval-item">
                <div class="approval-icon purple">
                    <i data-lucide="user-round-plus"></i>
                </div>
                <span>New Lead Assignments</span>
                <strong>{{ $newLeadAssignments }}</strong>
            </div>
            <div class="approval-item">
                <div class="approval-icon teal">
                    <i data-lucide="calendar-days"></i>
                </div>
                <span>Lead Assignment Requests</span>
                <strong>{{ $leadAssignmentRequests }}</strong>
            </div>
        </div>
    </div>

    <div class="activity-column">
        <div class="common-table-header">
            <h3>Recent Activities</h3>
            <a href="{{ route('crm.leads') }}" class="view-all">View All</a>
        </div>

        <div class="activity-card">
            @forelse($recentActivities as $activity)
                <div class="activity-item">
                    <div class="activity-icon blue">
                        <i data-lucide="phone-call"></i>
                    </div>
                    <div class="activity-content">
                        <p>{{ $activity->message ?? '' }}</p>
                        <small>{{ $activity->time ?? '' }}</small>
                    </div>
                </div>
            @empty
                <div class="activity-item">
                    <div class="activity-icon blue">
                        <i data-lucide="phone-call"></i>
                    </div>
                    <div class="activity-content">
                        <p>No recent activity</p>
                        <small>-</small>
                    </div>
                </div>
            @endforelse
        </div>
    </div>
</section>

@endsection
