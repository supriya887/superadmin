<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333333; margin: 0; padding: 20px; }
    .container { max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 6px; padding: 24px; background-color: #ffffff; }
    .header { font-size: 14px; color: #666666; margin-bottom: 20px; border-bottom: 1px solid #eeeeee; padding-bottom: 12px; }
    .ticket-id { font-weight: bold; color: #1a73e8; }
    .greeting { font-size: 15px; font-weight: bold; margin-bottom: 16px; }
    .message-box { background-color: #f8f9fa; border-left: 4px solid #1a73e8; padding: 14px 18px; margin: 20px 0; border-radius: 0 4px 4px 0; font-size: 14px; color: #222222; }
    .instruction { font-size: 13px; color: #5f6368; margin-top: 20px; font-style: italic; }
    .footer { font-size: 13px; color: #333333; margin-top: 24px; border-top: 1px solid #eeeeee; padding-top: 16px; }
    .disclaimer { font-size: 11px; color: #888888; margin-top: 24px; line-height: 1.4; }
  </style>
</head>
<body>

  <div class="container">
    <div class="greeting">Dear {{ $recipientName }},</div>

    <p style="margin: 0;">A new reply was added to your support ticket <span class="ticket-id">#{{ $ticketNo }}</span> ({{ $ticket->subject ?? 'Support request' }}):</p>

    <div class="message-box">
      {!! nl2br(e($replyMessage)) !!}
    </div>

    <p class="instruction">💡 You can reply directly to this email to respond to this ticket.</p>

    <div class="footer">
      Regards,<br>
      <strong>Team Tizzy</strong>
    </div>

    <div class="disclaimer">
      This email was sent regarding your support request with Tizzy Group. If you did not submit this request, please contact support immediately.
    </div>
  </div>
</body>
</html>
