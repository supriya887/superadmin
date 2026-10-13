<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>New Support Ticket</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #4e73df;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            background-color: #f8f9fc;
            padding: 20px;
            border: 1px solid #e3e6f0;
            border-top: none;
            border-radius: 0 0 5px 5px;
        }
        .ticket-details {
            background-color: white;
            padding: 15px;
            margin: 15px 0;
            border-left: 4px solid #4e73df;
        }
        .detail-row {
            margin-bottom: 10px;
        }
        .detail-label {
            font-weight: bold;
            width: 120px;
            display: inline-block;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-pending { background-color: #f6c23e; color: #fff; }
        .status-progress { background-color: #36b9cc; color: #fff; }
        .status-resolved { background-color: #1cc88a; color: #fff; }
        .status-closed { background-color: #e74a3b; color: #fff; }
        .priority-high { background-color: #e74a3b; color: #fff; }
        .priority-medium { background-color: #f6c23e; color: #fff; }
        .priority-low { background-color: #1cc88a; color: #fff; }
        .button {
            display: inline-block;
            background-color: #4e73df;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 15px;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 12px;
            color: #858796;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>New Support Ticket</h2>
        </div>
        <div class="content">
            <p>Hello <strong>{{ $name }}</strong>,</p>
            <p>A new support ticket has been created in the system.</p>
            
            <div class="ticket-details">
                <div class="detail-row">
                    <span class="detail-label">Ticket #:</span>
                    <span>{{ $ticket->ticket_no }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Subject:</span>
                    <span>{{ $ticket->subject }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Priority:</span>
                    <span class="status-badge priority-{{ strtolower($ticket->priority) }}">
                        {{ $ticket->priority }}
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status:</span>
                    <span class="status-badge status-{{ strtolower(str_replace(' ', '', $ticket->status)) }}">
                        {{ $ticket->status }}
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Requester:</span>
                    <span>{{ $ticket->requester }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Email:</span>
                    <span>{{ $ticket->email }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Created:</span>
                    <span>{{ $ticket->created_at->format('d M Y, h:i A') }}</span>
                </div>
                @if($ticket->tags)
                <div class="detail-row">
                    <span class="detail-label">Tags:</span>
                    <span>{{ $ticket->tags }}</span>
                </div>
                @endif
            </div>
            
            <div class="detail-row">
                <strong>Description:</strong><br>
                <p style="margin-top: 5px;">{{ nl2br(e($description)) }}</p>
            </div>
            
            <a href="{{ $ticketUrl }}" class="button">View Ticket Details</a>
            
            <div class="footer">
                <p>This is an automated notification. Please do not reply to this email.</p>
                <p>&copy; {{ date('Y') }} TIZZY Group. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>