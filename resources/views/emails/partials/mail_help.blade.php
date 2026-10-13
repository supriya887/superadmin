<tr>
    <td style="padding:24px 24px 0;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #E7E9EB;border-radius:10px;background:#ffffff;">
            <tr>
                <td style="padding:16px 32px;">
                    <h3 style="margin:0 0 15px;color:#121A26;font-family:Inter,Arial,Helvetica,sans-serif;font-size:20px;font-weight:600;">
                        @include('emails.partials.mail_icon', ['icon' => 'question', 'width' => 24, 'alt' => 'Help']) Need Help?
                    </h3>
                    <p style="margin:0;color:#4C4C5C;font-family:Inter,Arial,Helvetica,sans-serif;font-size:15px;line-height:150%;">
                        @include('emails.partials.mail_icon', ['icon' => 'email', 'width' => 20, 'alt' => 'Email', 'style' => 'vertical-align:middle;margin-right:6px;border:0;'])
                        <a href="mailto:support@tizzygroup.com" style="color:#4C4C5C;text-decoration:none;">support@tizzygroup.com</a>
                        &nbsp;&nbsp;&nbsp;&nbsp;
                        @include('emails.partials.mail_icon', ['icon' => 'phone', 'width' => 20, 'alt' => 'Phone', 'style' => 'vertical-align:middle;margin-right:6px;border:0;'])
                        +91 XXXXXXXXXX
                    </p>
                </td>
            </tr>
        </table>
    </td>
</tr>
