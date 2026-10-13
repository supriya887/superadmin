<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation {{ $quotation->quotation_no }}</title>
</head>
<body style="margin:0;padding:0;background:#eef2f6;font-family:Arial,Helvetica,sans-serif;color:#1e293b;">
@php
    $customerName = trim(($quotation->salutation ? $quotation->salutation . '. ' : '') . ($quotation->name ?: 'Customer'));
    $quoteStatus = strtolower((string) ($quotation->quotation_status ?? ''));
    $invoiceStatus = strtolower((string) ($quotation->invoice_status ?? ''));
    $isApproved = $quoteStatus === 'approved' || ($invoiceStatus === 'approved' && $quoteStatus !== 'shared');
    $isRejected = $quoteStatus === 'rejected';
    $validTill = optional($quotation->valid_date)->format('d F Y') ?: optional($quotation->created_at)->format('d F Y');
    $logo = asset('home/images/logo_tagline.png');
@endphp
<table width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f6;padding:28px 12px;">
    <tr>
        <td align="center">
            <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #dbe3ec;">
                <tr>
                    <td style="background:#075db5;padding:22px 28px;">
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td>
                                    <img src="{{ $logo }}" alt="Tizzy Cloud" width="150" style="display:block;border:0;max-width:150px;height:auto;">
                                </td>
                                <td align="right" style="color:#dbeafe;font-size:12px;letter-spacing:0.4px;">QUOTATION</td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px 28px 8px;">
                        <p style="margin:0 0 6px;font-size:13px;color:#075db5;font-weight:bold;">Tizzy Cloud Computing Private Limited</p>
                        <h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;color:#0f172a;">Quotation {{ $quotation->quotation_no }}</h1>
                        <p style="margin:0 0 18px;font-size:15px;line-height:1.6;color:#334155;">
                            Dear {{ $customerName }},<br>
                            Please find attached the quotation PDF for <strong style="color:#0f172a;">{{ $quotation->company_name }}</strong>.
                            The attached file matches the quotation view.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 28px 8px;">
                        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;">
                            <tr>
                                <td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;width:40%;color:#64748b;font-size:13px;">Company</td>
                                <td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;font-size:13px;font-weight:bold;color:#0f172a;">{{ $quotation->company_name }}</td>
                            </tr>
                            <tr>
                                <td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:13px;">Quotation No</td>
                                <td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;font-size:13px;font-weight:bold;">{{ $quotation->quotation_no }}</td>
                            </tr>
                            <tr>
                                <td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:13px;">Total Amount</td>
                                <td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;font-size:16px;font-weight:bold;color:#16a34a;">₹ {{ number_format((float) $quotation->total_amount, 2) }}</td>
                            </tr>
                            <tr>
                                <td style="padding:14px 16px;color:#64748b;font-size:13px;">Valid till</td>
                                <td style="padding:14px 16px;font-size:13px;font-weight:bold;">{{ $validTill }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 28px 8px;" align="center">
                        @if($isApproved)
                            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0fdf4;border:1px solid #86efac;border-radius:8px;">
                                <tr>
                                    <td style="padding:16px;text-align:center;color:#15803d;font-size:14px;">
                                        <strong>✓ Quotation Approved</strong>
                                        @if($quotation->responded_email)
                                            <div style="font-size:12px;color:#166534;margin-top:6px;">Approved by {{ $quotation->responded_email }}</div>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        @elseif($isRejected)
                            <table width="100%" cellpadding="0" cellspacing="0" style="background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;">
                                <tr>
                                    <td style="padding:16px;text-align:center;color:#b91c1c;font-size:14px;">
                                        <strong>✕ Quotation Rejected</strong>
                                        @if($quotation->responded_email)
                                            <div style="font-size:12px;color:#991b1b;margin-top:6px;">Rejected by {{ $quotation->responded_email }}</div>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        @else
                            <p style="margin:0 0 16px;font-size:14px;color:#475569;line-height:1.5;">
                                Please approve or reject this quotation. You can do this only once.
                            </p>
                            <table cellpadding="0" cellspacing="0" align="center">
                                <tr>
                                    <td style="padding-right:10px;">
                                        <a href="{{ $approveUrl }}" style="display:inline-block;background:#16a34a;color:#ffffff;text-decoration:none;padding:12px 26px;border-radius:6px;font-size:14px;font-weight:bold;">Approve</a>
                                    </td>
                                    <td>
                                        <a href="{{ $rejectUrl }}" style="display:inline-block;background:#dc2626;color:#ffffff;text-decoration:none;padding:12px 26px;border-radius:6px;font-size:14px;font-weight:bold;">Reject</a>
                                    </td>
                                </tr>
                            </table>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:24px 28px 28px;">
                        <p style="margin:0 0 10px;font-size:13px;color:#64748b;line-height:1.6;">
                            Questions? Reply to this email or contact us at
                            <a href="mailto:sales@tizzycloud.com" style="color:#075db5;text-decoration:none;">sales@tizzycloud.com</a>
                            / +91 9925 9925 99.
                        </p>
                        <p style="margin:0;padding-top:14px;border-top:1px solid #e2e8f0;font-size:11px;color:#94a3b8;line-height:1.6;">
                            Tizzy Cloud Computing Private Limited<br>
                            Office No. 511, Kalpataru Avenue, Kandivali (East), Mumbai – 400101<br>
                            <a href="https://www.tizzycloud.com" style="color:#075db5;text-decoration:none;">www.tizzycloud.com</a>
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
