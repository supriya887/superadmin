<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Tax Invoice #{{ $invoice_number }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 6mm 5mm;
        }

        html,
        body {
            width: 100%;
        }

        body,
        table,
        td,
        th,
        div {
            font-family: DejaVu Sans, sans-serif;
            color: #000;
        }

        body {
            font-size: 10px;
            line-height: 1.35;
            margin: 0;
            padding: 0;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        td,
        th {
            vertical-align: top;
        }

        .page {
            width: 100%;
            padding: 2px 0 4px;
            box-sizing: border-box;
        }

        .layout {
            width: 100%;
            border-collapse: collapse;
        }

        .layout td,
        .layout th {
            border: 1px solid #000;
            padding: 2px 5px;
        }

        .no-border td,
        .no-border th {
            border: 0 !important;
        }

        .hdr-title {
            font-size: 30px;
            font-weight: bold;
            text-align: center;
        }

        .hdr-einv {
            font-size: 11px;
            font-weight: bold;
            text-align: right;
        }

        .irn-td {
            font-size: 12px;
            line-height: 1.4;
            padding: 0;
            margin: 0;
        }

        .irn-label {
            display: inline-block;
            width: 68px;
        }

        .irn-qr-td {
            text-align: center;
            width: 160px;
            padding: 0;
            margin: 0;
            vertical-align: top;
        }

        .irn-qr-td img {
            width: 155px;
            height: 155px;
           
            padding: 0;
            margin: 0;
            display: block;
            background: #fff;
        }

        .irn-qr-caption {
            font-size: 9px;
            margin: 0;
            padding: 0;
            line-height: 1.2;
        }

        .parties-left-td {
            width: 50%;
            padding: 0 !important;
            line-height: 1.28;
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

        .terms-text {
            font-size: 11.5px;
            line-height: 1.22;
        }

        .seller-inner td {
            border: 0;
            padding: 0 4px 0 0;
            vertical-align: top;
        }

        .logo-img {
            width: 120px;
            max-width: 120px;
            margin-top: 8px;
        }

        .company-name {
            font-weight: bold;
            font-size: 14.5px;
        }

        .section-label {
            font-size: 11.5px;
            color: #444;
        }

        .buyer-name {
            font-weight: bold;
            font-size: 12.5px;
        }

        .meta-lbl {
            font-size: 10px;
            color: #333;
        }

        .meta-val {
            font-weight: bold;
            font-size: 10px;
        }

        .terms-title {
            font-weight: bold;
            font-size: 12.5px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
            border-top: none;
        }

        .items-table th,
        .items-table td {
            border: 1px solid #000;
            padding: 2px 4px;
            vertical-align: top;
        }

        .items-table thead th {
            background: #f0f0f0;
            font-weight: bold;
            font-size: 10px;
            text-align: center;
            padding: 2px 3px;
        }

        .c-sl {
            width: 30px;
            text-align: center;
        }

        .c-subid {
            width: 80px;
            text-align: center;
        }

        .c-hsn {
            width: 72px;
            text-align: center;
        }

        .c-qty {
            width: 60px;
            text-align: center;
        }

        .c-rate {
            width: 60px;
            text-align: right;
        }

        .c-amt {
            width: 70px;
            text-align: right;
        }

        .item-desc {
            font-weight: bold;
            font-size: 10px;
        }

        .item-sub {
            font-size: 8.5px;
            color: #333;
            font-style: italic;
            line-height: 1.2;
        }

        tr.spacer-row td {
            height: {{ (int) ($invoice_spacer_row_height ?? 12) }}px;
            padding: 0 !important;
            margin: 0;
            line-height: 1px;
            font-size: 1px;
            border-top: none !important;
            border-bottom: none !important;
        }

        tr.spacer-row td.c-sl,
        tr.spacer-row td.c-amt {
            border-left: 1px solid #000 !important;
            border-right: 1px solid #000 !important;
        }

        .tax-label {
            text-align: right;
            font-weight: bold;
            font-style: italic;
        }

        /* Line items + CGST/SGST: no horizontal lines between rows (until spacer block) */
        .items-table tbody tr.item-line-row td,
        .items-table tbody tr.tax-row td {
            border-top: none !important;
            border-bottom: none !important;
        }

        .tax-row td {
            font-style: normal;
            font-weight: bold;
        }

        .total-row td {
            font-weight: bold;
            background: #f9f9f9;
        }

        .total-amt {
            font-size: 11px;
            text-align: right;
        }

        .footer-left {
            width: 58%;
            padding: 3px 6px;
            line-height: 1.28;
        }

        .footer-right {
            width: 42%;
            padding: 3px 6px;
            line-height: 1.28;
        }

        .amount-words {
            font-weight: bold;
            font-size: 10px;
            margin: 2px 0 3px;
        }

        .eoe {
            text-align: right;
            font-style: italic;
            font-size: 10px;
        }

        .decl-lbl {
            font-size: 12.5px;
            font-weight: bold;
        }

        .decl-txt {
            font-size: 11.5px;
            line-height: 1.22;
        }

        .bank-lbl {
            font-weight: bold;
            font-size: 12.5px;
            margin-bottom: 2px;
        }

        .bk-key {
            color: #555;
            width: 95px;
            display: inline-block;
        }

        .sign-block {
            text-align: right;
            margin-top: 6px;
            font-size: 11.5px;
        }

        .sign-for {
            font-weight: bold;
            margin-bottom: 4px;
        }

        .foot-note {
            text-align: center;
            font-size: 11.5px;
            color: #444;
            margin-top: 2px;
            padding-top: 2px;
            border-top: 1px solid #ccc;
        }
    </style>
</head>

<body>
    @php
        $logoPath = trim((string) ($company['logo_path'] ?? ''));
        if ($logoPath === '') {
            $logoPath = trim((string) config('services.invoice.logo_url', ''))
                ?: 'https://goyalinfotech.in/tizzy/public/home/images/logo.png';
        }

        $einvoice = $einvoice ?? [];
        $irn = trim((string) ($einvoice['irn'] ?? ''));
        $ackNo = trim((string) ($einvoice['ack_no'] ?? ''));
        $ackDate = trim((string) ($einvoice['ack_date'] ?? ''));
        if ($irn === '') {
            $irn = (string) config(
                'services.invoice.dummy_irn',
                '5ded162fb6dfc937d5d0e567f12eb5c2a19753fe02f60ea4a50-ac82ae0ab9f66'
            );
        }
        if ($ackNo === '') {
            $ackNo = (string) config('services.invoice.dummy_ack_no', '122632344735015');
        }
        if ($ackDate === '') {
            $ackDate = (string) config('services.invoice.dummy_ack_date', '29-Apr-26');
        }
        $qrSrc = (string) ($einvoice['qr_src'] ?? '');
        $signedQr = (string) ($einvoice['signed_qr_code'] ?? '');
        $qrPayload = $signedQr !== '' ? $signedQr : ($irn !== '' ? $irn : 'TIZZY-INVOICE-' . $invoice_number);
        $qrDisplaySrc =
            $qrSrc !== ''
                ? $qrSrc
                : 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&format=svg&data=' . rawurlencode($qrPayload);

        $gstTotal = (float) ($gst_total ?? 0);
        $taxType = strtolower(trim((string) ($tax_type ?? '')));
        $cgstTotal = (float) ($cgst_total ?? 0);
        $sgstTotal = (float) ($sgst_total ?? 0);
        $igstTotal = (float) ($igst_total ?? 0);
        $discountAmount = max(0, (float) ($coupon_discount ?? $discount_amount ?? 0));
        $remainingValue = max(0, (float) ($remaining_value ?? $pro_rata_adjustment ?? 0));
        // Avoid double-counting when controller still passes combined discount_amount.
        if ($remainingValue > 0 && abs($discountAmount - $remainingValue) < 0.01) {
            $discountAmount = 0.0;
        } elseif ($remainingValue > 0 && $discountAmount >= $remainingValue) {
            $discountAmount = max(0.0, round($discountAmount - $remainingValue, 2));
        }
        $promoCode = strtoupper(trim((string) ($promo_code ?? '')));
        $invoiceDateAlt = $invoice_date_alt ?? $invoice_date;

        $gstRate = (float) ($gst_rate ?? 0);
        $taxableTotal = (float) ($taxable_total ?? $subtotal ?? 0);
        if ($gstRate <= 0 && $taxableTotal > 0 && $gstTotal > 0) {
            $gstRate = round(($gstTotal / $taxableTotal) * 100, 2);
        }
        if ($gstRate <= 0) {
            $gstRate = 18;
        }
        $normalizeGstRate = static function (float $rate): float {
            $slabs = [0, 3, 5, 12, 18, 28];
            $closest = 18.0;
            $bestDiff = INF;
            foreach ($slabs as $slab) {
                $diff = abs($rate - $slab);
                if ($diff < $bestDiff) {
                    $bestDiff = $diff;
                    $closest = (float) $slab;
                }
            }
            // Keep original when it's clearly a different intentional rate.
            return $bestDiff <= 2 ? $closest : $rate;
        };
        $gstRate = $normalizeGstRate($gstRate);
        $taxPctLabel = $taxType === 'igst' ? $gstRate : round($gstRate / 2, 2);

        $totalQty = array_sum(
            array_map(static function ($it) {
                return (int) ($it['quantity'] ?? 1);
            }, $items),
        );

        $companyFy = trim((string) ($company['fy_label'] ?? ''));
        $companyTitle = trim($company['name'] ?: 'Company Name');
        $companyTitleFy = $companyFy !== '' ? $companyTitle . ' (FY ' . $companyFy . ')' : $companyTitle;

        $amtWords = '—';
        if (class_exists(\NumberFormatter::class)) {
            try {
                $fmt = new \NumberFormatter('en_IN', \NumberFormatter::SPELLOUT);
                $whole = (int) floor((float) $total);
                $paise = (int) round(((float) $total - $whole) * 100);
                $w = ucwords(strtolower((string) $fmt->format($whole)));
                $amtWords =
                    'INR ' .
                    $w .
                    ($paise > 0 ? ' and ' . ucwords(strtolower((string) $fmt->format($paise))) . ' paise' : '') .
                    ' Only';
            } catch (\Throwable $e) {
                $amtWords = 'INR ' . number_format((float) $total, 2) . ' Only';
            }
        }

        $termsPay = $terms_of_payment ?? 'Due as per agreed credit terms.';
        $termsDelRaw =
            $terms_of_delivery ??
            "Payment due date is net 15 days from the Invoice date.\nIn order to comply with GST requirements kindly make the payment on or before the mentioned due date.\nIn case of delay in payment 2% interest per month will be \"charged on total overdue amount. For General Terms \"\nKindly Refer - http://www.tizzycloud.com/terms.aspx";
        $serviceDetailsRaw = $service_details ?? '';

        $declaration =
            $declaration_text ??
            'TDS Not Applicable-"That the above Software has/have been sold without any Modification. Pursuant to notification(Income Tax) No.21/2012F. No.142/10/2012-SO (TPL) Dt. 13-06-2012 This is a resale of software without any notification and tax has been deducted u/s 194J"';

        $custGstin = trim((string) ($customer['gstin'] ?? ''));
        if ($custGstin === '' || $custGstin === '-') {
            $custGstin = '—';
        }
        $custState = trim((string) ($customer['state_name'] ?? ''));
        $custStateCode = trim((string) ($customer['state_code'] ?? ''));
        $coPan = trim((string) ($company['pan'] ?? ''));
        $coGstin = trim((string) ($company['gstin'] ?? ''));
        $coState = trim((string) ($company['state_name'] ?? ''));
        $coStateCode = trim((string) ($company['state_code'] ?? ''));
        $bankNameLine = trim((string) ($bank['bank_name'] ?? ''));
        $bankAcct = trim((string) ($bank['account_number'] ?? ''));
        $bankNameDisplay = $bankNameLine !== '' && $bankAcct !== '' ? $bankNameLine . ' A/C NO. ' . $bankAcct : ($bankNameLine ?: '—');

        $coAddressLines = $company['address_lines'] ?? [];
        if (!is_array($coAddressLines) || $coAddressLines === []) {
            $coAddressRaw = trim((string) ($company['address'] ?? ''));
            if ($coAddressRaw !== '') {
                $coAddressLines = preg_split('/\s*,\s*|\r\n|\n|\r/', $coAddressRaw);
                $coAddressLines = array_values(array_filter(array_map('trim', $coAddressLines)));
            }
        }
        if ($coAddressLines === []) {
            $coAddressLines = [
                'Office No. 511, Kalpataru Avenue,',
                'Akurli Road, Kandivali (East)',
                'Mumbai-400101, Maharastra , India',
            ];
        }
        if ($coPan === '') {
            $coPan = (string) config('services.invoice.company_pan', 'AAGCT7197L');
        }
        if ($coGstin === '') {
            $coGstin = (string) config('services.invoice.company_gstin', '27AAGCT7197L1ZI');
        }
        if ($coState === '') {
            $coState = (string) config('services.invoice.company_state', 'Maharashtra');
        }
        if ($coStateCode === '') {
            $coStateCode = (string) config('services.invoice.company_state_code', '27');
        }
        $coStateLine = 'State Name : ' . $coState . ($coStateCode !== '' ? ', Code : ' . $coStateCode : '');
        $buyerAddressLines = preg_split("/\r\n|\n|\r/", trim((string) ($customer['billing_address'] ?? '')));
        $buyerAddressLines = array_values(array_filter(array_map('trim', $buyerAddressLines)));

        // Full-page fill: keep footer near bottom on short invoice pages.
        $planCount = is_countable($items ?? null) ? count($items) : 0;
        $extraRows = ($discountAmount > 0 ? 1 : 0) + 1; // Remaining Value row always present
        if ($gstTotal > 0) {
            $extraRows += ($taxType === 'igst' ? 1 : 2);
        }
        $spacerRowCount = max(2, 30 - ($planCount * 4) - ($extraRows * 3));
        $spacerRowCount = min($spacerRowCount, 26);
        if (isset($invoice_spacer_rows) && (int) $invoice_spacer_rows >= 0) {
            $spacerRowCount = min(26, (int) $invoice_spacer_rows);
        }
    @endphp

    <div class="page">

        {{-- Header: Tax Invoice + e-Invoice (dompdf-safe table) --}}
        <table class="no-border" style="width:100%; margin:0 0 2px 0;">
            <tr>
                <td style="width:90px; border:0;"></td>
                <td class="hdr-title" style="border:0;">Tax Invoice</td>
                <td class="hdr-einv" style="width:90px; border:0;">e-Invoice</td>
            </tr>
        </table>

        {{-- IRN + QR (always shown, like reference HTML) --}}
        <table class="no-border" style="width:100%; margin:0 0 2px 0;">
            <tr>
                <td class="irn-td" style="border:0; vertical-align:top;">
                    <div><span class="irn-label">IRN</span> : {{ $irn }}</div>
                    <div><span class="irn-label">Ack No.</span> : {{ $ackNo }}</div>
                    <div><span class="irn-label">Ack Date</span> : {{ $ackDate }}</div>
                </td>
                <td class="irn-qr-td" style="border:0; vertical-align:top;">
                    <img src="{{ $qrDisplaySrc }}" alt="IRN QR">
                    
                </td>
            </tr>
        </table>

        {{-- Seller + buyer | Invoice meta (50/50) --}}
        <table class="layout" cellspacing="0" cellpadding="0">
            <tr>
                <td class="parties-left-td">
                    <table class="parties-left-inner" cellspacing="0" cellpadding="0" width="100%">
                        <tr>
                            <td>
                                <table class="seller-inner" cellspacing="0" cellpadding="0" width="100%">
                                    <tr>
                                        <td style="width:125px;">
                                            @if ($logoPath !== '')
                                                <img src="{{ $logoPath }}" alt="Logo" class="logo-img">
                                            @endif
                                        </td>
                                        <td>
                                            <div class="company-name">{{ $companyTitleFy }}</div>
                                            <div>{{ $company['address'] }}</div>
                                            <div class="pan">PAN: AAGCT7197L</div>
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
                                <div class="buyer-name">{{ $customer['name'] ?: '—' }}</div>
                                @foreach ($buyerAddressLines as $line)
                                    <div>{{ $line }}</div>
                                @endforeach
                                @if (empty($buyerAddressLines))
                                    <div>—</div>
                                @endif
                                @if (!empty($customer['phone']))
                                    <div>Phone: {{ $customer['phone'] }}</div>
                                @endif
                                <div>GSTIN/UIN : {{ $custGstin }}</div>
                                @if ($custState !== '')
                                    <div>State Name : {{ $custState }}@if ($custStateCode !== ''), Code : {{ $custStateCode }}@endif</div>
                                @endif
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="parties-right-td">
                    <table class="meta-inner" cellspacing="0" cellpadding="0" width="100%">
                        <tr class="meta-row-split">
                            <td class="meta-split-left">
                                <div class="meta-lbl">Invoice No.</div>
                                <div class="meta-val">{{ $invoice_number }}</div>
                            </td>
                            <td class="meta-split-right">
                                <div class="meta-lbl">Invoice Date</div>
                                <div class="meta-val">{{ date('d-m-Y', strtotime($invoiceDateAlt)) }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <div class="meta-val">Mode/Terms of Payment</div>
                                <div>{{ $termsPay }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <div class="meta-val">Terms of Delivery</div>
                                <div>{!! nl2br(e($termsDelRaw)) !!}</div>
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
                    <th>Description of<br>Services</th>
                    <th class="c-subid">Subscription ID</th>
                    <th class="c-hsn">HSN/SAC</th>
                    <th class="c-qty">Quantity</th>
                    <th class="c-rate">Rate</th>
                    <th class="c-amt">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $idx => $item)
                    @php
                        $qty = (int) ($item['quantity'] ?? 1);
                        $lineTotal = (float) ($item['total'] ?? 0);
                        $rate = $qty > 0 ? round($lineTotal / $qty, 2) : $lineTotal;
                        $hsn = trim((string) ($item['hsn_sac'] ?? ''));
                        if ($hsn === '') {
                            $hsn = '997331';
                        }

                        $domain = trim((string) ($item['domain'] ?? ''));
                        if ($domain === '' || $domain === '-') {
                            $domain = isset($order) ? trim((string) ($order->domain_name ?? '')) : '';
                            if ($domain === '-') {
                                $domain = '';
                            }
                        }

                        $extraLines = [];
                        foreach (preg_split("/\r\n|\n|\r/", (string) ($item['description'] ?? '')) as $line) {
                            $line = trim($line);
                            if ($line === '') {
                                continue;
                            }
                            if (stripos($line, 'Domain:') === 0) {
                                continue;
                            }
                            if (stripos($line, 'Enrollment') === 0) {
                                continue;
                            }
                            if (stripos($line, 'Billing Cycle') === 0) {
                                continue;
                            }
                            if ($domain !== '' && strcasecmp($line, $domain) === 0) {
                                continue;
                            }
                            $extraLines[] = $line;
                        }
                    @endphp
                    <tr class="item-line-row">
                        <td class="c-sl">{{ $idx + 1 }}</td>
                        <td>
                            <div class="item-desc">{{ $item['name'] ?? '—' }}</div>
                            {!! nl2br(e($serviceDetailsRaw)) !!}
                            <br>
                            
                       
                            @foreach ($extraLines as $line)
                                <div class="item-sub">{{ $line }}</div>
                            @endforeach
                        </td>
                        <td class="c-subid">{{ trim((string) ($item['subscription_id'] ?? '')) !== '' ? $item['subscription_id'] : '-' }}</td>
                        <td class="c-hsn">{{ $hsn }}</td>
                        <td class="c-qty">{{ $qty }} nos</td>
                        <td class="c-rate">{{ number_format($rate, 2) }}</td>
                        <td class="c-amt">{{ number_format($lineTotal, 2) }}</td>
                    </tr>
                @endforeach

                <tr class="tax-row">
                    <td class="c-sl"></td>
                    <td class="tax-label">Remaining Value</td>
                    <td class="c-subid"></td>
                    <td class="c-hsn"></td>
                    <td class="c-qty"></td>
                    <td class="c-rate"></td>
                    <td class="c-amt">
                        @if ((float) $remainingValue > 0)
                            - {{ number_format((float) $remainingValue, 2) }}
                        @else
                            {{ number_format(0, 2) }}
                        @endif
                    </td>
                </tr>

                @if ($discountAmount > 0)
                    <tr class="tax-row">
                        <td class="c-sl"></td>
                        <td class="tax-label">
                            Discount
                            @if ($promoCode !== '')
                                (Coupon: {{ $promoCode }})
                            @endif
                        </td>
                        <td class="c-subid"></td>
                        <td class="c-hsn"></td>
                        <td class="c-qty"></td>
                        <td class="c-rate"></td>
                        <td class="c-amt">- {{ number_format($discountAmount, 2) }}</td>
                    </tr>
                @endif

                @if ($gstTotal > 0)
                    @if ($taxType === 'igst')
                        <tr class="tax-row">
                            <td class="c-sl"></td>
                            <td class="tax-label">OUTPUT IGST</td>
                            <td class="c-subid"></td>
                            <td class="c-hsn"></td>
                            <td class="c-qty"></td>
                            <td class="c-rate">{{ number_format($taxPctLabel, 0) }} %</td>
                            <td class="c-amt">{{ number_format($igstTotal, 2) }}</td>
                        </tr>
                    @else
                        <tr class="tax-row">
                            <td class="c-sl"></td>
                            <td class="tax-label">OUTPUT CGST</td>
                            <td class="c-subid"></td>
                            <td class="c-hsn"></td>
                            <td class="c-qty"></td>
                            <td class="c-rate">{{ number_format($taxPctLabel, 0) }} %</td>
                            <td class="c-amt">{{ number_format($cgstTotal, 2) }}</td>
                        </tr>
                        <tr class="tax-row">
                            <td class="c-sl"></td>
                            <td class="tax-label">OUTPUT SGST</td>
                            <td class="c-subid"></td>
                            <td class="c-hsn"></td>
                            <td class="c-qty"></td>
                            <td class="c-rate">{{ number_format($taxPctLabel, 0) }} %</td>
                            <td class="c-amt">{{ number_format($sgstTotal, 2) }}</td>
                        </tr>
                    @endif
                @endif

                {{-- Small gap after GST; row count = f(plan count) --}}
                @for ($s = 0; $s < $spacerRowCount; $s++)
                    <tr class="spacer-row">
                        <td class="c-sl">&nbsp;</td>
                        <td>&nbsp;</td>
                        <td class="c-subid">&nbsp;</td>
                        <td class="c-hsn">&nbsp;</td>
                        <td class="c-qty">&nbsp;</td>
                        <td class="c-rate">&nbsp;</td>
                        <td class="c-amt">&nbsp;</td>
                    </tr>
                @endfor

                <tr class="total-row">
                    <td class="c-sl"></td>
                    <td style="text-align:right;">Total</td>
                    <td class="c-subid"></td>
                    <td class="c-hsn"></td>
                    <td class="c-qty">{{ $totalQty }} nos</td>
                    <td class="c-rate"></td>
                    <td class="c-amt total-amt">{{ $currency_symbol }} {{ number_format((float) $total, 2) }}</td>
                </tr>
            </tbody>
        </table>

        {{-- Footer --}}
        <table class="layout" cellspacing="0" cellpadding="0" style="border-top:none;">
            <tr>
                <td class="footer-left">
                    <div style="font-size:12.5px;color:#444;">Amount Chargeable (in words)</div>
                    <div class="amount-words">{{ $amtWords }}</div>
                    <div class="eoe">E. &amp; O.E</div>
                    <div class="decl-lbl" style="margin-top:3px;">Declaration</div>
                    <div class="decl-txt">{{ $declaration }}</div>
                </td>
                <td class="footer-right">
                    <div class="bank-lbl">Company's Bank Details</div>
                    <div><span class="bk-key">Bank Name</span> : ICICI BANK A/C NO. 026705002724</div>
                    <div><span class="bk-key">A/c No.</span> : 026705002724</div>
                    <div><span class="bk-key">Branch &amp; IFS Code</span> : ICIC0000267</div>
                    <div class="sign-block">
                        <div class="sign-for">for {{ $companyTitleFy }}</div>
                        <div style="font-weight:bold;">Authorised Signatory</div>
                    </div>
                </td>
            </tr>
        </table>

        <div class="foot-note">This is a Computer Generated Invoice</div>

    </div>
</body>

</html>
