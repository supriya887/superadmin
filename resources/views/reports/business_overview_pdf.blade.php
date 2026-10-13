<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Business Reports Overview</title>
    <style>
        @page { margin: 28px 30px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
        h1 { font-size: 18px; margin: 0; }
        h2 { font-size: 12px; margin: 18px 0 6px; color: #111827; }
        .muted { color: #6b7280; font-size: 9px; margin: 2px 0 0; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #0b5cad; color: #fff; font-weight: bold; padding: 6px; text-align: center; border: 1px solid #0b5cad; }
        td { padding: 5px 6px; border: 1px solid #e5e7eb; text-align: center; }
        td.l { text-align: left; font-weight: bold; }
        tr:nth-child(even) td { background: #f8fafc; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <h1>Business Reports Overview</h1>
    <p class="muted">{{ $partnerName ? $partnerName . '  |  ' : '' }}Period: {{ $period }}  |  Generated: {{ now()->format('d M Y, h:i A') }}</p>

    <h2>Summary Metrics</h2>
    <table>
        <tr><th>Metric</th><th>Value</th><th>Trend</th></tr>
        @foreach($data['summary_cards'] ?? [] as $card)
            <tr>
                <td class="l">{{ $card['label'] }}</td>
                <td>{{ str_replace('₹', 'Rs.', (string) $card['display']) }}</td>
                <td>{{ $card['trend'] }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Quick Insights</h2>
    <table>
        <tr><th>Insight</th><th>Value</th></tr>
        @foreach($data['quick_insights'] ?? [] as $insight)
            <tr><td class="l">{{ $insight['label'] }}</td><td>{{ $insight['value'] === '—' ? '-' : $insight['value'] }}</td></tr>
        @endforeach
    </table>

    <h2>Revenue Overview</h2>
    <table>
        <tr><th>Month</th><th>Revenue</th></tr>
        @foreach($data['revenue_overview']['x_axis']['values'] ?? [] as $i => $month)
            <tr><td class="l">{{ $month }}</td><td>{{ $inr($data['revenue_overview']['datasets']['revenue'][$i] ?? 0) }}</td></tr>
        @endforeach
    </table>

    <h2>Sales by Service</h2>
    <table>
        <tr><th>Service</th><th>Amount</th><th>Share</th></tr>
        @foreach($data['sales_by_service']['labels'] ?? [] as $key => $label)
            <tr>
                <td class="l">{{ $label }}</td>
                <td>{{ $inr($data['sales_by_service']['datasets'][$key] ?? 0) }}</td>
                <td>{{ $data['sales_by_service']['percentages'][$key] ?? 0 }}%</td>
            </tr>
        @endforeach
    </table>

    <h2>Monthly Business Growth</h2>
    <table>
        <tr><th>Month</th><th>Revenue</th><th>Orders</th><th>Renewals</th></tr>
        @foreach($data['monthly_business_growth']['x_axis']['values'] ?? [] as $i => $month)
            <tr>
                <td class="l">{{ $month }}</td>
                <td>{{ $inr($data['revenue_overview']['datasets']['revenue'][$i] ?? 0) }}</td>
                <td>{{ $data['monthly_business_growth']['datasets']['orders'][$i] ?? 0 }}</td>
                <td>{{ $data['monthly_business_growth']['datasets']['renewals'][$i] ?? 0 }}</td>
            </tr>
        @endforeach
    </table>

    @php $activeDays = array_values(array_filter($daily, fn ($d) => $d['sales'] > 0 || $d['orders'] > 0 || $d['renewals'] > 0)); @endphp
    <div class="page-break"></div>
    <h2 style="margin-top:0">{{ $dailyTitle }}</h2>
    <p class="muted">Monitor daily sales, orders, renewals, and business activity. Days without activity are not listed.</p>
    <table style="margin-top:6px">
        <tr><th>Date</th><th>Sales</th><th>Orders</th><th>Renewals</th></tr>
        @forelse($activeDays as $day)
            <tr>
                <td class="l">{{ $day['date'] }}</td>
                <td>{{ $inr($day['sales']) }}</td>
                <td>{{ $day['orders'] }}</td>
                <td>{{ $day['renewals'] }}</td>
            </tr>
        @empty
            <tr><td colspan="4">No sales, orders or renewals in this period.</td></tr>
        @endforelse
        @if($activeDays)
            <tr>
                <td class="l">Total</td>
                <td><b>{{ $inr(array_sum(array_column($daily, 'sales'))) }}</b></td>
                <td><b>{{ array_sum(array_column($daily, 'orders')) }}</b></td>
                <td><b>{{ array_sum(array_column($daily, 'renewals')) }}</b></td>
            </tr>
        @endif
    </table>
</body>
</html>
