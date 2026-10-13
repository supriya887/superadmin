<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333333; margin: 0; padding: 20px; }
    .container { max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 6px; padding: 24px; background-color: #ffffff; }
    .ticket-id { font-weight: bold; color: #1a73e8; }
    .greeting { font-size: 15px; font-weight: bold; margin-bottom: 16px; }
    .details { background-color: #f8f9fa; border-left: 4px solid #1a73e8; padding: 14px 18px; margin: 20px 0; border-radius: 0 4px 4px 0; font-size: 14px; color: #222222; }
    .details td { padding: 2px 12px 2px 0; vertical-align: top; }
    .details td.label { color: #5f6368; white-space: nowrap; }
    .status-closed { color: #9aa0a6; font-weight: bold; }
    .instruction { font-size: 13px; color: #5f6368; margin-top: 20px; }
    .footer { font-size: 13px; color: #333333; margin-top: 24px; border-top: 1px solid #eeeeee; padding-top: 16px; }
    .disclaimer { font-size: 11px; color: #888888; margin-top: 24px; line-height: 1.4; }
  </style>
</head>
<body>

  <div class="container">
    <div class="greeting">Dear {{ $recipientName }},</div>

    <p style="margin: 0;">
      This is a notification to let you know that we are changing the status of your ticket
      <span class="ticket-id">#{{ $ticketNo }}</span> to <strong>Closed</strong>
      as we have not received a response from you in over {{ $inactiveDays }} {{ $inactiveDays === 1 ? 'day' : 'days' }}.
    </p>

    <div class="details">
      <table cellpadding="0" cellspacing="0">
        <tr><td class="label">Subject:</td><td>{{ $ticket->subject ?? '-' }}</td></tr>
        <tr><td class="label">Service:</td><td>{{ $ticket->service ?: 'Support' }}</td></tr>
        <tr><td class="label">Priority:</td><td>{{ ucfirst((string) ($ticket->priority ?? 'Normal')) }}</td></tr>
        <tr><td class="label">Status:</td><td class="status-closed">Closed</td></tr>
      </table>
    </div>

    <p class="instruction">
      If you still need help, you can reopen this ticket from the Support section within {{ $reopenHours }} hours,
      or raise a new ticket at any time.
    </p>

    <div class="footer">
      Thanks &amp; Regards,<br>
      <strong>Team Tizzy</strong>
    </div>

    <div class="disclaimer">
      This email was sent regarding your support request with Tizzy Group. If you did not submit this request, please contact support immediately.
    </div>
  </div>
</body>
</html>
