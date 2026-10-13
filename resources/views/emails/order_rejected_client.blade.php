<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Cancelled</title>
</head>
<body style="margin:0;padding:20px;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827;">
    <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:12px;padding:28px;">
        <h2 style="margin:0 0 16px;color:#b91c1c;">Order Cancelled</h2>
        <p style="margin:0 0 12px;">Hello <strong>{{ (string) ($order->customer_name ?? 'Customer') }}</strong>,</p>
        <p style="margin:0 0 12px;line-height:1.6;">
            We regret to inform you that your order
            @if(!empty($order->order_no))
                <strong>#{{ $order->order_no }}</strong>
            @else
                <strong>#{{ $order->id ?? '' }}</strong>
            @endif
            has been cancelled.
        </p>
        @if(!empty($rejectionReason))
            <p style="margin:16px 0 0;padding:12px;background:#fef2f2;border-left:4px solid #b91c1c;line-height:1.6;">
                <strong>Reason:</strong><br>{{ $rejectionReason }}
            </p>
        @endif
        <p style="margin:16px 0 0;line-height:1.6;">
            A copy of the cancelled purchase order is attached for your records.
        </p>
        <p style="margin:24px 0 0;color:#6b7280;font-size:14px;">If you have questions, please contact our support team.</p>
        <p style="margin:8px 0 0;color:#6b7280;font-size:14px;">— {{ config('app.name') }}</p>
    </div>
</body>
</html>
