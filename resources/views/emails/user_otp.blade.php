<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verification</title>
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
                                    <h1 style="margin:0 0 30px;color:#121A26;font-family:Inter,Arial,Helvetica,sans-serif;font-size:26px;font-weight:700;line-height:150%;letter-spacing:.2px;text-align:center;"> Sign in to {{ $appName ?? 'Tizzy Group' }} </h1>
                                    <p style="margin:0 0 16px;color:#4C4C5C;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;line-height:150%;"> Hello <strong>{{ $name ?? 'there' }}</strong>, </p>
                                    <p style="margin:0 0 20px;color:#4C4C5C;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;line-height:150%;"> You requested to {{ $authContext ?? 'sign in' }} to {{ $appName ?? 'Tizzy Group' }}. This is your verification code: </p>
                                    <div style="font-family:Inter,Arial,Helvetica,sans-serif;margin:30px auto;max-width:420px;text-align:center;border:1px solid #BCDAF9;background:#FBFBFD;border-radius:6px;padding:14px;font-size:32px;font-weight:700;letter-spacing:15px;color:#111827;"> {{ $otp }} </div>
                                    <p style="margin:20px 0;color:#4C4C5C;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;line-height:150%;"> This code will only be valid for the next {{ $expiryMinutes ?? 10 }} minutes. </p>
                                    <p style="margin:0 0 24px;color:#4C4C5C;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;line-height:150%;"> If you didn't request to {{ $authContext ?? 'sign in' }} to {{ $appName ?? 'Tizzy Group' }}, you can safely ignore this email. Someone else might have typed your email address by mistake. </p>
                                    <p style="margin:0;color:#4C4C5C;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;line-height:150%;"> Regards, <br> {{ $appName ?? 'Tizzy Group' }} Team </p>
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
