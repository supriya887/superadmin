<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partner Account Approved</title>
</head>
<body>
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#FFF;padding:30px 0;border-collapse:collapse;">
    <tr>
        <td align="center">
            <table width="600" cellpadding="0" cellspacing="0" border="0" style="background:#F5F8FC;border-collapse:collapse;">
                <tr>
                    <td align="center" style="padding:25px 30px;">
                        @include('emails.partials.logo', [
                            'logoWidth' => '200',
                            'logoStyle' => 'display:block;max-width:200px;width:200px;height:auto;border:0;',
                            'providerAlt' => 'Tizzy Group',
                        ])
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 24px;">
                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #E7E9EB;border-radius:12px;background:#ffffff;">
                            <tr>
                                <td style="padding:35px 30px;">
                                    <h1 style="margin:0;color:#121A26;font-family:Inter,Arial,Helvetica,sans-serif;font-size:26px;font-weight:700;line-height:150%;letter-spacing:.2px;text-align:center;"> Partner Account Approved </h1>
                                    <p style="margin:8px 0 15px;text-align:center;color:#5B6473;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;line-height:150%;"> Welcome to Tizzy Group! </p>
                                    <table align="center" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto 35px;">
                                        <tr>
                                            <td style="background:#E8FBF3;border:1px solid #B9F2D6;border-radius:20px;padding:6px 16px;font-family:Inter,Arial,Helvetica,sans-serif;font-size:13px;font-weight:600;color:#0FA968;"> &bull; Active </td>
                                        </tr>
                                    </table>
                                    <p style="margin:0 0 16px;color:#4C4C5C;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;line-height:170%;"> Hello <strong style="color:#121A26;">{{ $partner->name }}</strong>, </p>
                                    <p style="margin:0 0 28px;color:#4C4C5C;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;line-height:170%;"> Congratulations! Your Partner Account has been successfully approved and is now active. <br> You can now access your Partner Dashboard to manage customers, subscriptions, orders, invoices, and support requests. </p>
                                    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:18px;">
                                        <tr>
                                            <td style="padding:0 0 16px;border-bottom:1px solid #E7E9EB;">
                                                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                                    <tr>
                                                        <td style="font-family:Inter,Arial,Helvetica,sans-serif;font-weight:600;color:#121A26;">
                                                            @include('emails.partials.mail_icon', ['icon' => 'account', 'width' => 24, 'alt' => 'Account'])
                                                            <span style="font-size:18px;vertical-align:middle;">Account Details</span>
                                                        </td>
                                                        <td align="right">
                                                            <span style="background:#E8FBF3;color:#0FA968;font-family:Inter,Arial,Helvetica,sans-serif;font-size:12px;font-weight:600;padding:5px 12px;border-radius:4px;"> Active </span>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                    <table width="100%" cellpadding="8" cellspacing="0" border="0">
                                        <tr>
                                            <td width="40%" style="color:#384860;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> Name </td>
                                            <td align="right" style="color:#121A26;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> {{ $partner->name }} </td>
                                        </tr>
                                        <tr>
                                            <td style="color:#384860;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> Email </td>
                                            <td align="right" style="color:#121A26;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> {{ $partner->email }} </td>
                                        </tr>
                                        <tr>
                                            <td style="color:#384860;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> Type </td>
                                            <td align="right" style="color:#121A26;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> Partner </td>
                                        </tr>
                                        <tr>
                                            <td style="color:#384860;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> Portal </td>
                                            <td align="right" style="color:#121A26;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> partner.tizzygroup.com </td>
                                        </tr>
                                    </table>
                                    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:35px 0;">
                                        <tr>
                                            <td>
                                                <a href="https://partner.tizzygroup.com" style="display:block;background:linear-gradient(45deg,#2969FF,#193F99);color:#ffffff;text-decoration:none;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;font-weight:600;text-align:center;padding:18px 20px;border-radius:8px;"> Access Partner Portal </a>
                                            </td>
                                        </tr>
                                    </table>
                                    <p style="margin:0;color:#4C4C5C;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;line-height:150%;"> Regards, <br> Tizzy Group Team </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                @include('emails.partials.mail_help')
                @include('emails.partials.mail_footer')
            </table>
        </td>
    </tr>
</table>
</body>
</html>
