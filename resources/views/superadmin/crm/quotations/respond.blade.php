<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        @if(!empty($needsConfirm))
            Confirm quotation {{ $action }}
        @else
            Quotation {{ ucfirst($status) }}
        @endif
    </title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; background: #f4f6f8; margin: 0; padding: 40px 16px; color: #222; }
        .box { max-width: 520px; margin: 0 auto; background: #fff; border: 1px solid #dbe3ec; border-radius: 8px; padding: 32px; text-align: center; }
        h1 { margin: 0 0 12px; font-size: 22px; color: {{ $action === 'approve' ? '#16a34a' : '#dc2626' }}; }
        p { margin: 8px 0; line-height: 1.5; color: #475569; }
        .btn { display: inline-block; text-decoration: none; padding: 10px 22px; border-radius: 6px; font-weight: bold; margin: 8px 6px 0; }
        .btn-yes { background: {{ $action === 'approve' ? '#16a34a' : '#dc2626' }}; color: #fff; }
        .btn-no { background: #e2e8f0; color: #334155; }
    </style>
</head>
<body>
    <div class="box">
        @if(!empty($alreadyProcessed))
            <h1>Quotation Already {{ ucfirst($status) }}</h1>
            <p>This quotation <strong>{{ $quotation->quotation_no }}</strong> has already been <strong>{{ $status }}</strong>. Approve and Reject are no longer available.</p>
            @if($quotation->responded_email)
                <p>Recorded response from <strong>{{ $quotation->responded_email }}</strong>.</p>
            @endif
        @elseif(!empty($needsConfirm))
            <h1>Are you sure?</h1>
            <p>Do you want to <strong>{{ $action }}</strong> quotation <strong>{{ $quotation->quotation_no }}</strong>?</p>
            <p>{{ $quotation->company_name }}</p>
            <p>
                <a class="btn btn-yes" href="{{ $applyUrl }}"
                   onclick="return confirm('Are you sure you want to {{ $action }} this quotation? This cannot be undone.');">
                    Yes, {{ $action }}
                </a>
                <a class="btn btn-no" href="javascript:void(0)" onclick="window.close();">Cancel</a>
            </p>
        @else
            <h1>{{ $action === 'approve' ? 'Quotation Approved' : 'Quotation Rejected' }}</h1>
            <p>Thank you. Your response for quotation <strong>{{ $quotation->quotation_no }}</strong> has been recorded.</p>
            @if($quotation->responded_email)
                <p>{{ $action === 'approve' ? 'Approved' : 'Rejected' }} by <strong>{{ $quotation->responded_email }}</strong>.</p>
            @endif
        @endif
        <p>{{ $quotation->company_name }}</p>
    </div>
</body>
</html>
