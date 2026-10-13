<!DOCTYPE html>
<html>
<head>
    <title>License Decrease Decision</title>
</head>
<body style="font-family: sans-serif; line-height: 1.6; background-color: #f4f6f8; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; border-radius: 10px;">
    <div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 16px; padding: 32px 28px;">
        <div style="text-align: left;">
            <img src="{{ rtrim(config('app.url'), '/') }}/home/images/logo_tagline.png" alt="{{ config('app.name') }}" style="height:44px; border-radius: 8px; margin-bottom: 5px;" />
            <h2 style="margin: 0 0 10px 0; font-size:22px; font-weight: 600; color: #222;">
                @if ($decision === 'approved')
                    License Decrease Approved
                @elseif ($decision === 'reverted')
                    License Decrease Reverted
                @else
                    License Decrease Rejected
                @endif
            </h2>
            <p style="font-size:12px; color: #222; margin: 0 0 15px 0;">
                Hello {{ $partnerName }},
            </p>
            <div style="background: #f6f7f9; border-radius: 12px; padding: 24px 18px 15px 18px; text-align: left; margin: 18px 0;">
                <div style="font-size: 15px; color: #232323;">
                    @if ($decision === 'approved')
                        Your request <strong>#{{ $requestId }}</strong> to reduce licenses on order
                        <strong>#{{ $orderId }}</strong> from <strong>{{ $currentLicenses }}</strong> to
                        <strong>{{ $requestedLicenses }}</strong> has been
                        <span style="color: #23a05b; font-weight: bold;">approved</span>.<br>
                        You can proceed with renewal at the reduced license count.
                    @elseif ($decision === 'reverted')
                        The approval for request <strong>#{{ $requestId }}</strong> on order
                        <strong>#{{ $orderId }}</strong> has been
                        <span style="color: #d23421; font-weight:bold;">reverted</span>.<br>
                        Renewal will continue with <strong>{{ $currentLicenses }}</strong> licenses.
                    @else
                        Your request <strong>#{{ $requestId }}</strong> to reduce licenses on order
                        <strong>#{{ $orderId }}</strong> from <strong>{{ $currentLicenses }}</strong> to
                        <strong>{{ $requestedLicenses }}</strong> was
                        <span style="color: #d23421; font-weight:bold;">rejected</span>.<br>
                        Renewal will continue with <strong>{{ $currentLicenses }}</strong> licenses.
                    @endif

                    @if (!empty($adminNote))
                        <br><br>
                        <strong>Admin note:</strong> {{ $adminNote }}
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
