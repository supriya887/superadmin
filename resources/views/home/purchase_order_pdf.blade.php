<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Purchase Order #{{ $order->id }}</title>
    <style>
        body { font-family: sans-serif; font-size: 13px; color: #111; }
        h2 { margin-bottom: 0; }
        .muted { color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background: #f6f6f6; text-align: left; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <h2>Purchase Order</h2>
    <div class="muted">PO #{{ $order->id }} | Date: {{ now()->format('d-m-Y') }}</div>

    <table>
        <tr><th colspan="2">Customer Details</th></tr>
        <tr><td>Name</td><td>{{ $prefill['name'] ?? '' }}</td></tr>
        <tr><td>Email</td><td>{{ $prefill['email'] ?? '' }}</td></tr>
        <tr><td>Mobile</td><td>{{ $prefill['mobile'] ?? '' }}</td></tr>
        <tr><td>GSTIN</td><td>{{ $prefill['gstin'] ?? '' }}</td></tr>
        <tr><td>Company</td><td>{{ $prefill['company_name'] ?? '' }}</td></tr>
        <tr><td>Address</td><td>{{ $prefill['address'] ?? '' }}</td></tr>
    </table>

    <table>
        <tr>
            <th>Plan</th>
            <th>Domain</th>
            <th>Order Category</th>
            <th class="right">Base</th>
            <th class="right">Pro-rata Credit</th>
            <th class="right">GST</th>
            <th class="right">Total</th>
        </tr>
        <tr>
            <td>{{ $order->plan_name }}</td>
            <td>{{ $order->domain_name ?: 'N/A' }}</td>
            <td>{{ ucfirst($order->order_category ?? 'new') }}</td>
            <td class="right">{{ number_format((float) ($proRata['base_subtotal'] ?? 0), 2) }}</td>
            <td class="right">{{ number_format((float) ($proRata['pro_rata_credit'] ?? 0), 2) }}</td>
            <td class="right">{{ number_format((float) ($proRata['gst_amount'] ?? 0), 2) }}</td>
            <td class="right">{{ number_format((float) ($proRata['total_amount'] ?? 0), 2) }}</td>
        </tr>
    </table>
</body>
</html>
