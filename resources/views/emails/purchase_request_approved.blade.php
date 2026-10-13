<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Request Approved</title>
</head>
<body style="font-family: sans-serif; line-height: 1.6; background-color: #f4f6f8; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 16px; padding: 32px 28px;">
        <div style="text-align: left;">
            <h2 style="margin: 0 0 10px 0; font-size:22px; font-weight: 600; color: #222;">
                Plan Upgrade Request Approved
            </h2>
            <p style="font-size:14px; color: #222; margin: 0 0 15px 0;">
                Hello {{ optional($purchaseRequest->partner)->name ?? 'Partner' }},
            </p>
            <p style="font-size: 14px; color: #444;">
                Your plan upgrade request <strong>#{{ $purchaseRequest->id }}</strong> has been <span style="color: #23a05b; font-weight: bold;">APPROVED</span> by SuperAdmin.
            </p>

            <div style="background: #f6f7f9; border-radius: 12px; padding: 20px; text-align: left; margin: 18px 0;">
                <p style="margin: 4px 0; font-size: 13px;"><strong>Request Type:</strong> {{ ucfirst($purchaseRequest->request_type ?? 'Upgrade') }}</p>
                <p style="margin: 4px 0; font-size: 13px;"><strong>Plan:</strong> {{ optional($purchaseRequest->plan)->name ?? '-' }}</p>
                <p style="margin: 4px 0; font-size: 13px;"><strong>Licenses:</strong> {{ $purchaseRequest->licenses }}</p>
                @if(!empty($purchaseRequest->admin_notes))
                    <p style="margin: 4px 0; font-size: 13px;"><strong>Admin Note:</strong> {{ $purchaseRequest->admin_notes }}</p>
                @endif
            </div>

            <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin: 28px auto;">
                <tr>
                    <td align="center" bgcolor="#0066cc" style="border-radius: 8px;">
                        <a href="{{ $checkoutLink }}" target="_blank" rel="noopener noreferrer"
                           style="background-color: #0066cc; color: #ffffff; padding: 12px 28px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block; font-size: 14px; line-height: 20px;">
                            View Cart &rarr;
                        </a>
                    </td>
                </tr>
            </table>

            <p style="font-size: 12px; color: #777;">
                If the button above does not work, copy and paste this link into your browser:<br>
                <a href="{{ $checkoutLink }}" target="_blank" rel="noopener noreferrer" style="color: #0066cc; word-break: break-all;">{{ $checkoutLink }}</a>
            </p>

            <hr style="border: none; border-top: 1px solid #eee; margin: 24px 0 14px 0;">
            <p style="font-size: 12px; color: #888; margin: 0;">
                Regards,<br>
                Tizzy Group Team
            </p>
        </div>
    </div>
</body>
</html>
