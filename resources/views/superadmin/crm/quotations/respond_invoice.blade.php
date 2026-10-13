<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        @if(!empty($needsConfirm))
            Confirm invoice {{ $action }}
        @else
            Invoice {{ ucfirst($status) }}
        @endif
    </title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; background: #f4f6f8; margin: 0; padding: 40px 16px; color: #222; }
        .box { max-width: 540px; margin: 0 auto; background: #fff; border: 1px solid #dbe3ec; border-radius: 10px; padding: 36px 32px; text-align: center; box-shadow: 0 4px 14px rgba(0,0,0,0.06); }
        .icon { width: 56px; height: 56px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 28px; margin-bottom: 16px; }
        .icon-success { background: #dcfce7; color: #16a34a; }
        .icon-danger { background: #fee2e2; color: #dc2626; }
        h1 { margin: 0 0 12px; font-size: 22px; color: {{ $status === 'approved' || $action === 'approve' ? '#16a34a' : '#dc2626' }}; }
        p { margin: 8px 0; line-height: 1.5; color: #475569; font-size: 15px; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 13px; font-weight: 600; margin: 10px 0; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .company-name { font-weight: bold; color: #1e293b; margin-top: 16px; }
        .footer-note { font-size: 13px; color: #94a3b8; margin-top: 24px; border-top: 1px solid #f1f5f9; padding-top: 16px; }
        .btn { display: inline-block; text-decoration: none; padding: 10px 22px; border-radius: 6px; font-weight: bold; margin: 8px 6px 0; }
        .btn-yes { background: {{ $action === 'approve' ? '#16a34a' : '#dc2626' }}; color: #fff; }
        .btn-no { background: #e2e8f0; color: #334155; }
    </style>
</head>
<body>
    <div class="box">
        @php $invoiceBy = $quotation->invoice_responded_email ?: $quotation->responded_email; @endphp
        @if(!empty($alreadyProcessed))
            <div class="icon {{ $status === 'approved' ? 'icon-success' : 'icon-danger' }}">
                {{ $status === 'approved' ? '✓' : '✕' }}
            </div>
            <h1>Invoice Already {{ ucfirst($status) }}</h1>
            <span class="badge {{ $status === 'approved' ? 'badge-success' : 'badge-danger' }}">{{ ucfirst($status) }}</span>
            <p>This Tax Invoice for quotation <strong>{{ $quotation->quotation_no }}</strong> has already been <strong>{{ $status }}</strong>. Approve and Reject are no longer available.</p>
            @if($invoiceBy)
                <p>Recorded response from <strong>{{ $invoiceBy }}</strong>.</p>
            @endif
        @elseif(!empty($needsConfirm))
            <h1>Are you sure?</h1>
            <p>Do you want to <strong>{{ $action }}</strong> this Tax Invoice for quotation <strong>{{ $quotation->quotation_no }}</strong>?</p>
            <p>
                <a class="btn btn-yes" href="{{ $applyUrl }}"
                   onclick="return confirm('Are you sure you want to {{ $action }} this invoice? This cannot be undone.');">
                    Yes, {{ $action }}
                </a>
                <a class="btn btn-no" href="javascript:void(0)" onclick="window.close();">Cancel</a>
            </p>
        @else
            <div class="icon {{ $action === 'approve' ? 'icon-success' : 'icon-danger' }}">
                {{ $action === 'approve' ? '✓' : '✕' }}
            </div>
            <h1>{{ $action === 'approve' ? 'Invoice Approved' : 'Invoice Rejected' }}</h1>
            <p>Thank you. Your response for Tax Invoice of quotation <strong>{{ $quotation->quotation_no }}</strong> has been recorded.</p>
            @if($invoiceBy)
                <p>{{ $action === 'approve' ? 'Approved' : 'Rejected' }} by <strong>{{ $invoiceBy }}</strong>.</p>
            @endif
        @endif
        <div class="company-name">{{ $quotation->company_name }}</div>
        <div class="footer-note">Tizzy Cloud Computing Private Limited</div>
    </div>
</body>
</html>
