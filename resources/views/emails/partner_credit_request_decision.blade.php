<!DOCTYPE html>
<html>
<head>
    <title>Credit Request Decision</title>
</head>
<body style="font-family: sans-serif; line-height: 1.6; background-color: #f4f6f8; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; border-radius: 10px;">
    <div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 16px; padding: 32px 28px;">
        <div style="text-align: left;">
            <img src="{{ rtrim(config('app.url'), '/') }}/home/images/logo_tagline.png" alt="{{ config('app.name') }}" style="height:44px; border-radius: 8px; margin-bottom: 5px;" />
            <h2 style="margin: 0 0 10px 0; font-size:22px; font-weight: 600; color: #222;">
                @if ($decision === 'approved')
                    Credit Request Approved
                @else
                    Credit Request Update
                @endif
            </h2>
            <p style="font-size:12px; color: #222; margin: 0 0 15px 0;">
                Hello {{ $partnerName }},
            </p>
            <div style="background: #f6f7f9; border-radius: 12px; padding: 24px 18px 15px 18px; text-align: left; margin: 18px 0;">
                <div style="font-size: 15px; color: #232323;">
                    @if ($decision === 'approved')
                        Your credit request <strong>#{{ $requestId }}</strong> for <strong>₹{{ number_format($amount, 2) }}</strong> has been <span style="color: #23a05b; font-weight: bold;">approved</span>.<br>
                        The amount has been added to your wallet balance.<br>
                        You can continue purchases from the partner portal.
                    @else
                        Your credit request <strong>#{{ $requestId }}</strong> for <strong>₹{{ number_format($amount, 2) }}</strong> was <span style="color: #d23421; font-weight:bold;">rejected</span>.<br>
                        Please contact support if you need assistance.
                    @endif
                </div>
            </div>
            <hr style="border: none; border-top: 1px solid #eee; margin: 24px 0 14px 0;">
            <p style="font-size: 12px; color: #888; margin: 0;">
                Regards,<br>
                Tizzy Group Team
            </p>
        </div>
    </div>
</body>
</html>
