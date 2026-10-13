<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Plan {{ $action === 'downgrade' ? 'Downgraded' : 'Upgraded' }} Successfully</title>
</head>
<body style="font-family: -apple-system, 'Segoe UI', Arial, sans-serif; line-height: 1.6; background-color: #f4f6f8; color: #333; margin: 0; padding: 24px;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; padding: 32px 28px; box-shadow: 0 6px 24px rgba(15,23,42,.06);">

        <div style="text-align: left; margin-bottom: 8px;">
            @include('emails.partials.logo', [
                'logoWidth' => '160',
                'logoStyle' => 'display:block; max-width:160px; width:160px; height:auto; border:0; border-radius:8px;',
            ])
        </div>

        <div style="background: linear-gradient(135deg, #0b56b6, #1a73e8); color: #fff; border-radius: 12px; padding: 22px 24px; margin: 18px 0 22px 0;">
            <div style="font-size: 13px; letter-spacing: .04em; text-transform: uppercase; opacity: .9;">
                Plan {{ $action === 'downgrade' ? 'Downgraded' : 'Upgraded' }}
            </div>
            <h2 style="margin: 4px 0 0 0; font-size: 22px; font-weight: 600;">Thank you, {{ $customerName }}!</h2>
            <p style="margin: 8px 0 0 0; font-size: 14px; opacity: .95;">
                Your subscription on <strong>Tizzy Group</strong> has been
                {{ $action === 'downgrade' ? 'downgraded' : 'upgraded' }} successfully.
            </p>
        </div>

        <p style="font-size: 14px; color: #334155; margin: 0 0 18px 0;">
            Here are the details of your new plan:
        </p>

        <table cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse: collapse; margin-bottom: 22px;">
            <tr>
                <td style="padding: 12px 14px; border: 1px solid #eef1f7; border-bottom: 0; background: #f9fbfd; width: 40%; font-size: 13px; color: #6b7280;">New Plan</td>
                <td style="padding: 12px 14px; border: 1px solid #eef1f7; border-bottom: 0; font-size: 14px; font-weight: 600; color: #0f172a;">{{ $planName }}</td>
            </tr>
            @if(!empty($orderNo))
            <tr>
                <td style="padding: 12px 14px; border: 1px solid #eef1f7; border-bottom: 0; background: #f9fbfd; font-size: 13px; color: #6b7280;">Order No.</td>
                <td style="padding: 12px 14px; border: 1px solid #eef1f7; border-bottom: 0; font-size: 14px; color: #0f172a;">{{ $orderNo }}</td>
            </tr>
            @endif
            <tr>
                <td style="padding: 12px 14px; border: 1px solid #eef1f7; border-bottom: 0; background: #f9fbfd; font-size: 13px; color: #6b7280;">Amount</td>
                <td style="padding: 12px 14px; border: 1px solid #eef1f7; border-bottom: 0; font-size: 14px; font-weight: 600; color: #0b56b6;">&#8377; {{ $amountPaid }}</td>
            </tr>
            <tr>
                <td style="padding: 12px 14px; border: 1px solid #eef1f7; background: #f9fbfd; font-size: 13px; color: #6b7280;">Valid Till</td>
                <td style="padding: 12px 14px; border: 1px solid #eef1f7; font-size: 14px; font-weight: 600; color: #0f172a;">{{ $validityDate }}</td>
            </tr>
        </table>

        <p style="font-size: 13px; color: #475569; margin: 0 0 18px 0; line-height: 1.7;">
            You can continue using your services without interruption. The signed GST e-invoice
            for this {{ $action === 'downgrade' ? 'downgrade' : 'upgrade' }} is attached to this email
            for your records.
        </p>

        <hr style="border: none; border-top: 1px solid #eef1f7; margin: 26px 0 16px 0;">

        <p style="font-size: 12px; color: #94a3b8; margin: 0;">
            This is an automated confirmation from <strong>Tizzy Group</strong>. Please do not reply
            to this email if it was unexpected.
        </p>
        <p style="font-size: 12px; color: #94a3b8; margin: 8px 0 0 0;">
            Regards,<br>
            <strong>Tizzy Group</strong> Team
        </p>
    </div>
</body>
</html>
