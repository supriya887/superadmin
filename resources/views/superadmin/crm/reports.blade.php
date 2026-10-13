@extends('superadmin.layouts.crm')

@section('content')
@php
    $employees = [
        ['name' => 'Divya Sharma', 'role' => 'Sales Director', 'leads' => 248, 'calls' => 312, 'followups' => 186, 'opps' => 94, 'won' => 34, 'revenue' => '₹1.8L', 'conv' => '13.7%', 'color' => 'purple'],
        ['name' => 'Rahul Mehta', 'role' => 'Regional Manager', 'leads' => 312, 'calls' => 287, 'followups' => 215, 'opps' => 110, 'won' => 48, 'revenue' => '₹24.5L', 'conv' => '15.4%', 'color' => 'teal'],
        ['name' => 'Ananya Patel', 'role' => 'Account Manager', 'leads' => 189, 'calls' => 245, 'followups' => 167, 'opps' => 78, 'won' => 28, 'revenue' => '₹9.2L', 'conv' => '14.8%', 'color' => 'blue'],
        ['name' => 'Vikram Singh', 'role' => 'Business Executive', 'leads' => 176, 'calls' => 198, 'followups' => 142, 'opps' => 65, 'won' => 21, 'revenue' => '₹7.1L', 'conv' => '11.9%', 'color' => 'orange'],
        ['name' => 'Suresh Kumar', 'role' => 'Team Lead', 'leads' => 215, 'calls' => 254, 'followups' => 200, 'opps' => 88, 'won' => 32, 'revenue' => '₹12.6L', 'conv' => '14.9%', 'color' => 'green'],
        ['name' => 'Priya Nair', 'role' => 'Sales Executive', 'leads' => 154, 'calls' => 176, 'followups' => 128, 'opps' => 52, 'won' => 18, 'revenue' => '₹5.4L', 'conv' => '11.7%', 'color' => 'pink'],
    ];
    $months = [
        ['label' => 'Jan', 'h' => 42],
        ['label' => 'Feb', 'h' => 55],
        ['label' => 'Mar', 'h' => 48],
        ['label' => 'Apr', 'h' => 62],
        ['label' => 'May', 'h' => 70],
        ['label' => 'Jun', 'h' => 58],
        ['label' => 'Jul', 'h' => 76],
        ['label' => 'Aug', 'h' => 64],
        ['label' => 'Sep', 'h' => 88],
        ['label' => 'Oct', 'h' => 72],
        ['label' => 'Nov', 'h' => 80],
        ['label' => 'Dec', 'h' => 94],
    ];
    $categories = [
        ['icon' => 'trending-up', 'title' => 'Sales Performance', 'desc' => 'Track overall sales performance, revenue and conversions.', 'links' => ['Pipeline', 'Won deals', 'Lost deals'], 'tone' => 'teal'],
        ['icon' => 'users', 'title' => 'Lead Performance', 'desc' => 'Lead quality, conversion, sources and follow-through.', 'links' => ['Top sources', 'Conversion', 'Unassigned'], 'tone' => 'purple'],
        ['icon' => 'briefcase', 'title' => 'Deal / Opportunity', 'desc' => 'Pipeline health, deal stages and expected revenue.', 'links' => ['By stage', 'Forecast', 'Closing this month'], 'tone' => 'orange'],
        ['icon' => 'file-text', 'title' => 'Quotations', 'desc' => 'Quote conversion, discounts and value.', 'links' => ['Sent', 'Approved', 'Discounted'], 'tone' => 'yellow'],
        ['icon' => 'handshake', 'title' => 'Deal / Opportunity', 'desc' => 'Monitor pipeline value, expected and closed revenue.', 'links' => ['Pipeline', 'Expected', 'Highest value deal'], 'tone' => 'blue'],
        ['icon' => 'wallet', 'title' => 'Revenue & Payments', 'desc' => 'Track collections, pending invoices and billing.', 'links' => ['Billed', 'Pending', 'Overdue'], 'tone' => 'orange'],
        ['icon' => 'building-2', 'title' => 'Partners & Customers', 'desc' => 'Partner performance, customer health and accounts.', 'links' => ['Partners', 'Customers', 'Renewals'], 'tone' => 'teal'],
        ['icon' => 'activity', 'title' => 'Activities', 'desc' => 'Calls, meetings, follow-ups and team activity.', 'links' => ['Calls logged', 'Meetings', 'Follow-ups'], 'tone' => 'purple'],
        ['icon' => 'refresh-cw', 'title' => 'Renewals', 'desc' => 'Track upcoming, due and completed renewals.', 'links' => ['Upcoming', 'Renewed', 'Renewal value'], 'tone' => 'blue'],
        ['icon' => 'target', 'title' => 'Targets', 'desc' => 'Monitor team/individual targets vs achievement.', 'links' => ['Team targets', 'Achievement', '%'], 'tone' => 'yellow'],
    ];
    $insights = [
        ['label' => 'Top Lead Source', 'value' => 'LinkedIn', 'meta' => '28% of leads', 'tone' => 'blue'],
        ['label' => 'Top Employee', 'value' => 'Rahul Mehta', 'meta' => '48 won deals', 'tone' => 'teal'],
        ['label' => 'Highest Value Deal', 'value' => '₹9.2L', 'meta' => 'Technoova Pvt. — Pipeline', 'tone' => 'purple'],
        ['label' => 'Highest Value Deal', 'value' => '₹7.1L', 'meta' => 'Closed 27 Aug 2026', 'tone' => 'orange'],
        ['label' => 'Follow-up Tasks', 'value' => '42 overdue tasks', 'meta' => 'Need attention', 'tone' => 'yellow'],
    ];
    $forecast = [
        ['label' => 'Current Pipeline', 'value' => '₹1.85 Cr', 'pct' => 100, 'tone' => 'navy'],
        ['label' => 'Expected Revenue', 'value' => '₹94.2 L', 'pct' => 51, 'tone' => 'blue'],
        ['label' => 'Forecasted Revenue', 'value' => '₹52.7 L', 'pct' => 28, 'tone' => 'sky'],
        ['label' => 'Closing This Month', 'value' => '₹28.4 L', 'pct' => 15, 'tone' => 'teal'],
        ['label' => 'Closing This Quarter', 'value' => '₹67.3 L', 'pct' => 36, 'tone' => 'purple'],
    ];
@endphp

<div class="crm-reports-page">
    <div class="page-header">
        <div>
            <div class="breadcrumb welcome-text">
                <p><a href="{{ route('crm.dashboard') }}">Dashboard</a> <span>/</span></p>
            </div>
            <h2>Reports</h2>
        </div>
    </div>

    <div class="stats-grid">
        <div class="summary-card rpt-kpi tone-blue">
            <div class="card-icon"><i data-lucide="indian-rupee"></i></div>
            <p>Total Sales</p>
            <h3>₹7,248</h3>
        </div>
        <div class="summary-card rpt-kpi tone-teal">
            <div class="card-icon"><i data-lucide="trophy"></i></div>
            <p>Won Deals</p>
            <h3>186</h3>
        </div>
        <div class="summary-card rpt-kpi tone-green">
            <div class="card-icon"><i data-lucide="percent"></i></div>
            <p>Win Rate</p>
            <h3>28.6%</h3>
        </div>
        <div class="summary-card rpt-kpi tone-purple">
            <div class="card-icon"><i data-lucide="git-branch"></i></div>
            <p>Pipeline Value</p>
            <h3>₹1.85 Cr</h3>
        </div>
        <div class="summary-card rpt-kpi tone-orange">
            <div class="card-icon"><i data-lucide="target"></i></div>
            <p>Target Achievement</p>
            <h3>78%</h3>
        </div>
    </div>

    <section class="common-table-section">
        <div class="common-table-card rpt-card">
            <div class="common-table-header">
                <div>
                    <h3>Business Performance Overview</h3>
                    <p class="rpt-sub">Organization-wide sales and pipeline snapshot</p>
                </div>
                <div class="rpt-range">Last 12 Months</div>
            </div>
            <div class="rpt-overview-stats">
                <div class="tone-blue"><span>Total Revenue</span><strong>₹772.4 L</strong></div>
                <div class="tone-teal"><span>Won Deals</span><strong>186</strong></div>
                <div class="tone-orange"><span>Lost Deals</span><strong>312</strong></div>
                <div class="tone-purple"><span>Pipeline Value</span><strong>₹1.85 Cr</strong></div>
                <div class="tone-green"><span>Expected Revenue</span><strong>₹94.2 L</strong></div>
                <div class="tone-yellow"><span>Win Rate</span><strong>28.6%</strong></div>
            </div>
            <div class="rpt-bars">
                @foreach ($months as $m)
                    <div class="rpt-bar-col">
                        <div class="rpt-bar" style="--h: {{ $m['h'] }}%"></div>
                        <small>{{ $m['label'] }}</small>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="common-table-section">
        <div class="common-table-card rpt-card">
            <div class="common-table-header">
                <div>
                    <h3>Employee Performance</h3>
                    <p class="rpt-sub">Individual sales and conversion contribution</p>
                </div>
                <div class="search-box rpt-search">
                    <input type="text" placeholder="Search Employee" readonly>
                    <button type="button"><i data-lucide="search"></i></button>
                </div>
            </div>
            <div class="crm-lead-table-wrap">
                <table class="rpt-emp-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Leads</th>
                            <th>Calls</th>
                            <th>Follow-ups</th>
                            <th>Opportunities</th>
                            <th>Won Deals</th>
                            <th>Revenue</th>
                            <th>Conv %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($employees as $emp)
                            <tr>
                                <td>
                                    <div class="rpt-emp">
                                        <span class="avatar {{ $emp['color'] }}">{{ strtoupper(substr($emp['name'], 0, 1)) }}</span>
                                        <div>
                                            <h5>{{ $emp['name'] }}</h5>
                                            <p>{{ $emp['role'] }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $emp['leads'] }}</td>
                                <td>{{ $emp['calls'] }}</td>
                                <td>{{ $emp['followups'] }}</td>
                                <td>{{ $emp['opps'] }}</td>
                                <td>{{ $emp['won'] }}</td>
                                <td>{{ $emp['revenue'] }}</td>
                                <td class="rpt-conv">{{ $emp['conv'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <h3 class="rpt-section-title">Report Categories</h3>
    <div class="rpt-cats">
        @foreach ($categories as $cat)
            <div class="rpt-cat tone-{{ $cat['tone'] }}">
                <div class="rpt-cat-icon"><i data-lucide="{{ $cat['icon'] }}"></i></div>
                <h4>{{ $cat['title'] }}</h4>
                <p>{{ $cat['desc'] }}</p>
                <div class="rpt-cat-links">
                    @foreach ($cat['links'] as $link)
                        <a href="javascript:void(0)">{{ $link }}</a>
                    @endforeach
                </div>
                <a href="javascript:void(0)" class="rpt-cat-more">View Reports →</a>
            </div>
        @endforeach
    </div>

    <h3 class="rpt-section-title">Quick Insights</h3>
    <div class="rpt-insights">
        @foreach ($insights as $item)
            <div class="rpt-insight tone-{{ $item['tone'] }}">
                <span>{{ $item['label'] }}</span>
                <strong>{{ $item['value'] }}</strong>
                <p>{{ $item['meta'] }}</p>
            </div>
        @endforeach
    </div>

    <section class="common-table-section">
        <div class="common-table-card rpt-card">
            <div class="common-table-header">
                <div>
                    <h3>Sales Forecast</h3>
                    <p class="rpt-sub">Pipeline-based revenue projection</p>
                </div>
                <div class="rpt-range">Last 12 Months</div>
            </div>
            <div class="rpt-forecast">
                @foreach ($forecast as $row)
                    <div class="rpt-forecast-row tone-{{ $row['tone'] }}">
                        <div class="rpt-forecast-label">
                            <span>{{ $row['label'] }}</span>
                            <strong>{{ $row['value'] }}</strong>
                        </div>
                        <div class="rpt-forecast-track">
                            <span style="--w: {{ $row['pct'] }}%"></span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
</div>
@endsection
