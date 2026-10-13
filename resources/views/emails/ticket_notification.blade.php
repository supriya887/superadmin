<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Support Ticket Notification</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .header p {
            margin: 10px 0 0;
            opacity: 0.9;
        }
        .content {
            padding: 30px;
        }
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin: 0 5px;
        }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .badge-secondary { background: #e2e3e5; color: #383d41; }
        
        .status-update {
            background: #f0f4ff;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
        }
        .status-change {
            font-size: 20px;
            font-weight: bold;
        }
        .arrow {
            font-size: 24px;
            margin: 0 15px;
        }
        .ticket-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .detail-row {
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e9ecef;
        }
        .detail-label {
            font-weight: bold;
            width: 120px;
            display: inline-block;
            color: #495057;
        }
        .detail-value {
            color: #212529;
        }
        .message-box {
            background: #fff;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 25px;
            margin-top: 20px;
            font-weight: bold;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #6c757d;
            border-top: 1px solid #dee2e6;
        }
        .role-tag {
            display: inline-block;
            padding: 2px 8px;
            background: #667eea;
            color: white;
            border-radius: 12px;
            font-size: 10px;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                @if($eventType == 'created')
                    🎫 New Support Ticket
                @else
                    📝 Ticket Status Updated
                @endif
                <span class="role-tag">{{ $role }}</span>
            </h1>
            <p>Ticket #{{ $ticket->ticket_no }}</p>
        </div>
        
        <div class="content">
            <p>Hello <strong>{{ $name }}</strong>,</p>
            
            @if($eventType == 'created')
                <p>A new support ticket has been created in the system.</p>
            @else
                <p>A support ticket has been updated.</p>
                <div class="status-update">
                    <div class="status-change">
                        <span class="badge badge-secondary">{{ $oldStatus ?? 'Previous' }}</span>
                        <span class="arrow">→</span>
                        <span class="badge 
                            @if($newStatus == 'Resolved' || $newStatus == 'Closed') badge-success
                            @elseif($newStatus == 'In Progress') badge-warning
                            @elseif($newStatus == 'Pending') badge-info
                            @else badge-danger @endif">
                            {{ $newStatus ?? 'Updated' }}
                        </span>
                    </div>
                    <small>Status updated by: {{ $updatedBy ?? 'System' }}</small>
                </div>
            @endif
            
            <div class="ticket-details">
                <div class="detail-row">
                    <span class="detail-label">Ticket #:</span>
                    <span class="detail-value">{{ $ticket->ticket_no }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Subject:</span>
                    <span class="detail-value">{{ $ticket->subject }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Priority:</span>
                    <span class="detail-value">
                        <span class="badge 
                            @if($ticket->priority == 'Urgent' || $ticket->priority == 'High') badge-danger
                            @elseif($ticket->priority == 'Medium') badge-warning
                            @else badge-success @endif">
                            {{ $ticket->priority }}
                        </span>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value">
                        <span class="badge 
                            @if($ticket->status == 'Resolved' || $ticket->status == 'Closed') badge-success
                            @elseif($ticket->status == 'In Progress') badge-warning
                            @elseif($ticket->status == 'Pending') badge-info
                            @else badge-secondary @endif">
                            {{ $ticket->status }}
                        </span>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Requester:</span>
                    <span class="detail-value">{{ $ticket->requester }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value">{{ $ticket->email }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Created:</span>
                    <span class="detail-value">{{ $ticket->created_at->format('d M Y, h:i A') }}</span>
                </div>
                @if($ticket->tags)
                <div class="detail-row">
                    <span class="detail-label">Tags:</span>
                    <span class="detail-value">{{ $ticket->tags }}</span>
                </div>
                @endif
            </div>
            
            @if($description)
            <div class="message-box">
                <strong>📄 Description:</strong>
                <p style="margin-top: 10px;">{{ nl2br(e($description)) }}</p>
            </div>
            @endif
            
            <div style="text-align: center;">
                <a href="{{ $ticketUrl }}" class="button">View Ticket Details</a>
            </div>
        </div>
        
        <div class="footer">
            <p>This is an automated notification from TIZZY Support System.</p>
            <p>&copy; {{ date('Y') }} TIZZY Group. All rights reserved.</p>
            <p><small>Please do not reply to this email.</small></p>
        </div>
    </div>
</body>
</html>