@extends('superadmin.layouts.crm')

@section('content')
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
            <h3>{{ $totalSales }}</h3>
        </div>
        <div class="summary-card rpt-kpi tone-teal">
            <div class="card-icon"><i data-lucide="trophy"></i></div>
            <p>Won Deals</p>
            <h3>{{ $wonDeals }}</h3>
        </div>
        <div class="summary-card rpt-kpi tone-green">
            <div class="card-icon"><i data-lucide="percent"></i></div>
            <p>Win Rate</p>
            <h3>{{ $winRate }}</h3>
        </div>
        <div class="summary-card rpt-kpi tone-purple">
            <div class="card-icon"><i data-lucide="git-branch"></i></div>
            <p>Pipeline Value</p>
            <h3>{{ $pipelineValue }}</h3>
        </div>
        <div class="summary-card rpt-kpi tone-orange">
            <div class="card-icon"><i data-lucide="target"></i></div>
            <p>Target Achievement</p>
            <h3>{{ $targetAchievement }}</h3>
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
                <div class="tone-blue"><span>Total Revenue</span><strong>{{ $yearRevenue }}</strong></div>
                <div class="tone-teal"><span>Won Deals</span><strong>{{ $wonDeals }}</strong></div>
                <div class="tone-orange"><span>Lost Deals</span><strong>{{ $lostDeals }}</strong></div>
                <div class="tone-purple"><span>Pipeline Value</span><strong>{{ $pipelineValue }}</strong></div>
                <div class="tone-green"><span>Expected Revenue</span><strong>{{ $expectedRevenue }}</strong></div>
                <div class="tone-yellow"><span>Win Rate</span><strong>{{ $winRate }}</strong></div>
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
                <form method="GET" action="{{ route('crm.reports') }}" class="search-box rpt-search">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search Employee">
                    <button type="submit"><i data-lucide="search"></i></button>
                </form>
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
                        @forelse ($employees as $emp)
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
                        @empty
                            <tr>
                                <td colspan="8">No employees found.</td>
                            </tr>
                        @endforelse
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
                        <a href="{{ $link['href'] }}">{{ $link['text'] }}</a>
                    @endforeach
                </div>
                <a href="{{ $cat['href'] }}" class="rpt-cat-more">View Reports →</a>
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
