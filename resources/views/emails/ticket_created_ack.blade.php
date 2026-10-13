<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Ticket Created</title>
</head>
<body style="margin:0;padding:0;background-color:#ffffff;font-family:Arial,Helvetica,sans-serif;color:#222222;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#ffffff;">
        <tr>
            <td align="left" style="padding:40px 24px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px;">
                    <tr>
                        <td style="font-size:15px;line-height:24px;color:#222222;">
                            <p style="margin:0 0 18px 0;">Dear {{ $recipientName }},</p>

                            <p style="margin:0 0 18px 0;">
                                We would like to acknowledge that we have received your request and a ticket has been created{{ !empty($ticketNo) ? ' (' . $ticketNo . ')' : '' }}.
                            </p>

                            <p style="margin:0 0 18px 0;">
                                A support representative will be reviewing your request and will send you a personal response in next 24 hours.
                            </p>

                            <p style="margin:0 0 28px 0;">
                                Thank you for your patience.
                            </p>

                            <p style="margin:0 0 4px 0;">Regards,</p>
                            <p style="margin:0 0 28px 0;">Team Tizzy</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="border-top:1px solid #e5e5e5;padding-top:20px;font-size:14px;line-height:22px;">
                            <p style="margin:0 0 10px 0;">
                                <span style="margin-right:4px;">📌</span>
                                <a href="{{ $helpCenterUrl }}" style="color:#1a73e8;text-decoration:underline;">Visit our Help Center</a>
                            </p>
                            <p style="margin:0 0 10px 0;">
                                <span style="margin-right:4px;">🌐</span>
                                <a href="{{ $websiteUrl }}" style="color:#1a73e8;text-decoration:underline;">Visit our Website</a>
                            </p>
                            <p style="margin:0;">
                                <span style="margin-right:4px;">📢</span>
                                <a href="{{ $twitterUrl }}" style="color:#1a73e8;text-decoration:underline;">Twitter</a>
                                <span style="color:#666666;"> | </span>
                                <a href="{{ $linkedinUrl }}" style="color:#1a73e8;text-decoration:underline;">LinkedIn</a>
                                <span style="color:#666666;"> | </span>
                                <a href="{{ $youtubeUrl }}" style="color:#1a73e8;text-decoration:underline;">YouTube</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
