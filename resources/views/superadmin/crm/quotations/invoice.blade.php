<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $docTitle ?? 'Tax Invoice' }} #{{ $invoiceNo }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 6mm 5mm;
        }
        * {
            box-sizing: border-box;
        }
        html, body {
            width: 100%;
            margin: 0;
            padding: 0;
        }
        body, table, td, th, div {
            font-family: DejaVu Sans, sans-serif;
            color: #000;
        }
        body {
            font-size: 10px;
            line-height: 1.35;
            background: #fff;
        }
        table {
            border-collapse: collapse;
            width: 100%;
        }
        td, th {
            vertical-align: top;
        }
        .page {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            padding: 6px 8px;
            box-sizing: border-box;
        }
        .layout {
            width: 100%;
            border-collapse: collapse;
        }
        .layout td, .layout th {
            border: 1px solid #000;
            padding: 3px 6px;
        }
        .no-border td, .no-border th {
            border: 0 !important;
        }
        .hdr-title {
            font-size: 26px;
            font-weight: bold;
            text-align: center;
            letter-spacing: 0.5px;
        }
        .hdr-einv {
            font-size: 11px;
            font-weight: bold;
            text-align: right;
        }
        .parties-left-td {
            width: 50%;
            padding: 0 !important;
            line-height: 1.3;
            border-right: 1px solid #000;
            vertical-align: top;
        }
        .parties-left-inner {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        .parties-left-inner td {
            border-left: 0 !important;
            border-right: 0 !important;
            border-top: 0 !important;
            border-bottom: 1px solid #000 !important;
            padding: 5px 8px;
            margin: 0;
            vertical-align: top;
        }
        .parties-left-inner tr:last-child td {
            border-bottom: none !important;
        }
        .parties-right-td {
            width: 50%;
            padding: 0 !important;
            margin: 0;
            vertical-align: top;
            border-left: none;
        }
        .meta-inner {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        .meta-inner td {
            border-left: 0 !important;
            border-right: 0 !important;
            border-top: 0 !important;
            border-bottom: 1px solid #000 !important;
            padding: 5px 8px;
            margin: 0;
            font-size: 10px;
            line-height: 1.35;
            vertical-align: top;
        }
        .meta-inner tr:last-child td {
            border-bottom: none !important;
        }
        .meta-inner td.meta-split-left {
            width: 50%;
            border-right: 1px solid #000 !important;
        }
        .meta-inner td.meta-split-right {
            width: 50%;
        }
        .meta-lbl {
            color: #555;
            font-size: 9.5px;
            margin-bottom: 2px;
        }
        .meta-val {
            font-weight: bold;
            font-size: 10.5px;
        }
        .seller-inner td {
            border: 0;
            padding: 0 4px 0 0;
            vertical-align: top;
        }
        .logo-img {
            width: 110px;
            max-width: 110px;
            height: auto;
            margin-top: 4px;
        }
        .company-name {
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 2px;
        }
        .section-label {
            font-weight: bold;
            font-size: 9.5px;
            text-transform: uppercase;
            color: #555;
            margin-bottom: 2px;
        }
        .buyer-name {
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 2px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
        }
        .items-table th, .items-table td {
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            padding: 4px 6px;
            font-size: 9.5px;
        }
        .items-table th {
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            background: #f5f5f5;
            text-align: left;
            font-weight: bold;
        }
        .items-table .c-sl { width: 32px; text-align: center; }
        .items-table .c-hsn { width: 70px; text-align: center; }
        .items-table .c-qty { width: 60px; text-align: center; }
        .items-table .c-rate { width: 75px; text-align: right; }
        .items-table .c-amt { width: 85px; text-align: right; }
        .items-table tbody tr.item-line-row td,
        .items-table tbody tr.tax-row td {
            border-top: none !important;
            border-bottom: none !important;
        }
        .tax-label {
            text-align: right;
            font-weight: bold;
        }
        tr.spacer-row td {
            border-top: none !important;
            border-bottom: none !important;
            height: 14px;
            padding: 0 6px;
        }
        .total-row td {
            font-weight: bold;
            background: #f9f9f9;
            border-top: 1px solid #000 !important;
            border-bottom: 1px solid #000 !important;
        }
        .total-amt {
            font-size: 11px;
            text-align: right;
        }
        .footer-left {
            width: 58%;
            padding: 4px 6px;
            line-height: 1.3;
        }
        .footer-right {
            width: 42%;
            padding: 4px 6px;
            line-height: 1.3;
        }
        .amount-words {
            font-weight: bold;
            font-size: 10px;
            margin: 2px 0 3px;
        }
        .eoe {
            text-align: right;
            font-style: italic;
            font-size: 9.5px;
        }
        .decl-lbl {
            font-size: 10px;
            font-weight: bold;
        }
        .decl-txt {
            font-size: 9px;
            line-height: 1.25;
            color: #333;
        }
        .bank-lbl {
            font-weight: bold;
            font-size: 10.5px;
            margin-bottom: 2px;
        }
        .bk-key {
            color: #555;
            width: 85px;
            display: inline-block;
        }
        .sign-block {
            text-align: right;
            margin-top: 12px;
            font-size: 10px;
        }
        .sign-for {
            font-weight: bold;
            margin-bottom: 16px;
        }
        .foot-note {
            text-align: center;
            font-size: 9.5px;
            color: #555;
            margin-top: 4px;
            padding-top: 3px;
            border-top: 1px solid #ddd;
        }
        .action-bar {
            width: 100%;
            max-width: 800px;
            margin: 10px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
            padding: 10px 14px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        .action-bar a, .action-bar button {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #334155;
        }
        .action-bar .btn-primary {
            background: #2563eb;
            color: #fff;
            border-color: #2563eb;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            .page {
                padding: 0;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>

@if(empty($forPdf))
<div class="action-bar no-print">
    <div>
        <a href="{{ route('crm.quotations') }}">
            &larr; Back to Quotations
        </a>
    </div>
    <div style="display:flex; gap:8px;">
        <button type="button" onclick="window.print()">
            Print
        </button>
        <a href="{{ route('crm.quotations.invoice.download', $quotation) }}" class="btn-primary">
            Download PDF
        </a>
    </div>
</div>
@endif

@php
    $buyerAddressLines = collect([
        $quotation->mailing_address,
        collect([$quotation->city, $quotation->state])->filter()->implode(', '),
        collect([$quotation->pincode, $quotation->country])->filter()->implode(' '),
    ])->filter()->values();

    $custGstin = trim((string) ($quotation->gstin ?? ''));
    if ($custGstin === '' || $custGstin === '-') {
        $custGstin = '—';
    }
    $custState = trim((string) ($quotation->state ?? ''));

    $totalQty = collect($sheets)->sum('qty');
    if (collect($sheets)->contains(fn ($s) => !empty($s['showDomain']))) {
        $totalQty += 1;
    }

    $planCount = count($sheets);
    $spacerRowCount = max(3, 14 - ($planCount * 2));
@endphp

<div class="page">

    {{-- Top header: Tax Invoice --}}
    <table class="no-border" style="width:100%; margin:0 0 4px 0;">
        <tr>
            <td style="width:90px; border:0;"></td>
            <td class="hdr-title" style="border:0;">{{ $docTitle ?? 'Tax Invoice' }}</td>
            <td class="hdr-einv" style="width:90px; border:0;">{{ ($docTitle ?? 'Tax Invoice') === 'Tax Invoice' ? 'e-Invoice' : '' }}</td>
        </tr>
    </table>

    {{-- Seller + Buyer & Invoice Meta --}}
    <table class="layout" cellspacing="0" cellpadding="0">
        <tr>
            <td class="parties-left-td">
                <table class="parties-left-inner" cellspacing="0" cellpadding="0" width="100%">
                    <tr>
                        <td>
                            <table class="seller-inner" cellspacing="0" cellpadding="0" width="100%">
                                <tr>
                                    <td style="width:115px;">
                                        @if(!empty($logoSrc))
                                            <img src="{{ $logoSrc }}" alt="Tizzy Cloud" class="logo-img">
                                        @endif
                                    </td>
                                    <td>
                                        <div class="company-name">Tizzy Cloud Computing Private Limited</div>
                                        <div>Office No. 511, Kalpataru Avenue, Akurli Road, Kandivali (East)</div>
                                        <div>Mumbai – 400101, Maharashtra, INDIA</div>
                                        <div>PAN: AAGCT7197L</div>
                                        <div>GSTIN/UIN: 27AAGCT7197L1ZI</div>
                                        <div>State Name : Maharashtra, Code : 27</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="section-label">Buyer (Bill to)</div>
                            <div class="buyer-name">{{ $quotation->company_name ?: $quotation->name }}</div>
                            @if($quotation->company_name && $quotation->name && $quotation->company_name !== $quotation->name)
                                <div>Attn: {{ $quotation->name }}</div>
                            @endif
                            @foreach ($buyerAddressLines as $line)
                                <div>{{ $line }}</div>
                            @endforeach
                            @if (!empty($quotation->email))
                                <div>Email: {{ $quotation->email }}</div>
                            @endif
                            <div>GSTIN/UIN : {{ $custGstin }}</div>
                            @if ($custState !== '')
                                <div>State Name : {{ $custState }}</div>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
            <td class="parties-right-td">
                <table class="meta-inner" cellspacing="0" cellpadding="0" width="100%">
                    <tr>
                        <td class="meta-split-left">
                            <div class="meta-lbl">Invoice No.</div>
                            <div class="meta-val">{{ $invoiceNo }}</div>
                        </td>
                        <td class="meta-split-right">
                            <div class="meta-lbl">Invoice Date</div>
                            <div class="meta-val">{{ $invoiceDate }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <div class="meta-lbl">Reference Quotation No.</div>
                            <div class="meta-val">{{ $quotation->quotation_no ?: ('#' . $quotation->id) }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <div class="meta-val">Mode/Terms of Payment</div>
                            <div>100% Advance / Net 15 days</div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <div class="meta-val">Terms of Delivery</div>
                            <div>Payment due date is net 15 days from the Invoice date.<br>Activation / Transfer / Renewal within 3-5 working days.</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Line items --}}
    <table class="items-table" cellspacing="0" cellpadding="0">
        <thead>
            <tr>
                <th class="c-sl">Sl<br>No.</th>
                <th>Description of Services</th>
                <th class="c-hsn">HSN/SAC</th>
                <th class="c-qty">Quantity</th>
                <th class="c-rate">Rate</th>
                <th class="c-amt">Amount</th>
            </tr>
        </thead>
        <tbody>
            @php $itemIndex = 1; @endphp
            @foreach ($sheets as $line)
                @php
                    $linePlan = $line['plan'];
                    $lineQty = (int) ($linePlan['quantity'] ?? $line['qty'] ?? 1);
                    $lineRate = (float) ($linePlan['rate'] ?? 0);
                    $lineSub = (float) ($line['sub'] ?? ($lineQty * $lineRate));
                    $sac = $quotation->sac ?: '997331';
                @endphp
                <tr class="item-line-row">
                    <td class="c-sl">{{ $itemIndex++ }}</td>
                    <td>
                        <strong>{{ $linePlan['name'] ?? ($quotation->service_name ?: 'Cloud Service') }}</strong>
                        @if(!empty($line['period']))
                            <span>({{ $line['period'] }})</span>
                        @endif
                        @if(!empty($linePlan['note']))
                            <div style="font-size: 8.5px; color: #555;">{{ $linePlan['note'] }}</div>
                        @endif
                        @if(!empty($linePlan['sku']))
                            <div style="font-size: 8.5px; color: #555;">SKU: {{ $linePlan['sku'] }}</div>
                        @endif
                    </td>
                    <td class="c-hsn">{{ $sac }}</td>
                    <td class="c-qty">{{ $lineQty }} nos</td>
                    <td class="c-rate">{{ number_format($lineRate, 2) }}</td>
                    <td class="c-amt">{{ number_format($lineSub, 2) }}</td>
                </tr>

                @if(!empty($line['showDomain']))
                    <tr class="item-line-row">
                        <td class="c-sl">{{ $itemIndex++ }}</td>
                        <td>
                            <strong>Domain - {{ $quotation->domain_name }}</strong>
                            @if($quotation->domain_start)
                                <span style="font-size: 8.5px; color: #555;">({{ $quotation->domain_start->format('d-m-Y') }} @if($quotation->domain_end) to {{ $quotation->domain_end->format('d-m-Y') }} @endif)</span>
                            @endif
                        </td>
                        <td class="c-hsn">{{ $sac }}</td>
                        <td class="c-qty">1 nos</td>
                        <td class="c-rate">{{ number_format((float) $quotation->domain_rate, 2) }}</td>
                        <td class="c-amt">{{ number_format((float) $quotation->domain_rate, 2) }}</td>
                    </tr>
                @endif
            @endforeach

            @if ($discountAmount > 0)
                <tr class="tax-row">
                    <td class="c-sl"></td>
                    <td class="tax-label">Discount {{ (float) $quotation->discount }}%</td>
                    <td class="c-hsn"></td>
                    <td class="c-qty"></td>
                    <td class="c-rate"></td>
                    <td class="c-amt">- {{ number_format($discountAmount, 2) }}</td>
                </tr>
            @endif

            @if ($taxType === 'igst')
                <tr class="tax-row">
                    <td class="c-sl"></td>
                    <td class="tax-label">OUTPUT IGST</td>
                    <td class="c-hsn"></td>
                    <td class="c-qty"></td>
                    <td class="c-rate">18 %</td>
                    <td class="c-amt">{{ number_format($igstTotal, 2) }}</td>
                </tr>
            @else
                <tr class="tax-row">
                    <td class="c-sl"></td>
                    <td class="tax-label">OUTPUT CGST</td>
                    <td class="c-hsn"></td>
                    <td class="c-qty"></td>
                    <td class="c-rate">9 %</td>
                    <td class="c-amt">{{ number_format($cgstTotal, 2) }}</td>
                </tr>
                <tr class="tax-row">
                    <td class="c-sl"></td>
                    <td class="tax-label">OUTPUT SGST</td>
                    <td class="c-hsn"></td>
                    <td class="c-qty"></td>
                    <td class="c-rate">9 %</td>
                    <td class="c-amt">{{ number_format($sgstTotal, 2) }}</td>
                </tr>
            @endif

            @for ($s = 0; $s < $spacerRowCount; $s++)
                <tr class="spacer-row">
                    <td class="c-sl">&nbsp;</td>
                    <td>&nbsp;</td>
                    <td class="c-hsn">&nbsp;</td>
                    <td class="c-qty">&nbsp;</td>
                    <td class="c-rate">&nbsp;</td>
                    <td class="c-amt">&nbsp;</td>
                </tr>
            @endfor

            <tr class="total-row">
                <td class="c-sl"></td>
                <td style="text-align:right;">Total</td>
                <td class="c-hsn"></td>
                <td class="c-qty">{{ $totalQty }} nos</td>
                <td class="c-rate"></td>
                <td class="c-amt total-amt">₹ {{ number_format($grandTotal, 2) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Footer --}}
    <table class="layout" cellspacing="0" cellpadding="0" style="border-top:none;">
        <tr>
            <td class="footer-left">
                <div style="font-size:10px; color:#444;">Amount Chargeable (in words)</div>
                <div class="amount-words">{{ $amtWords }}</div>
                <div class="eoe">E. &amp; O.E</div>
                <div class="decl-lbl" style="margin-top:4px;">Declaration</div>
                <div class="decl-txt">
                    TDS Not Applicable - "That the above Software has/have been sold without any Modification. Pursuant to notification(Income Tax) No.21/2012F. No.142/10/2012-SO (TPL) Dt. 13-06-2012 This is a resale of software without any notification and tax has been deducted u/s 194J"
                </div>
            </td>
            <td class="footer-right">
                <div class="bank-lbl">Company's Bank Details</div>
                <div><span class="bk-key">Bank Name</span> : ICICI BANK</div>
                <div><span class="bk-key">A/c No.</span> : 026705002724</div>
                <div><span class="bk-key">Branch &amp; IFSC</span> : Kandivali East &amp; ICIC0000267</div>
                <div class="sign-block">
                    <div class="sign-for">for Tizzy Cloud Computing Private Limited</div>
                    <div style="font-weight:bold;">Authorised Signatory</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="foot-note">This is a Computer Generated Invoice</div>

</div>

</body>
</html>
