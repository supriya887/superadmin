<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>{{ $document_title ?? 'Purchase Order' }} #{{ $po_number ?? '' }}</title>
    <style>
        @page { size: A4 portrait; margin: 10mm 8mm 8mm 8mm; }
        body, table, td, th, div { font-family: DejaVu Sans, sans-serif; color: #000; }
        body { font-size: 10px; line-height: 1.35; margin: 0; padding: 0; }
        table { border-collapse: collapse; width: 100%; }
        td, th { vertical-align: top; }
        .page { padding: 8px 6px 10px; position: relative; min-height: 760px; }
        .page.has-aadhaar-stamp { padding-bottom: 118px; }
        .doc-header { margin: 0 0 14px 0; padding-top: 6px; }
        .hdr-title {
            font-size: 13px;
            font-weight: bold;
            text-align: center;
            padding: 8px 0 12px 0;
            margin: 0 0 10px 0;
        }
        .doc-ref-strip { width: 100%; margin: 0 0 8px 0; border-collapse: separate; border-spacing: 0; }
        .doc-ref-strip td {
            border: 0 !important;
            font-size: 9.5px;
            line-height: 1.55;
            padding: 4px 12px 4px 0;
            vertical-align: top;
        }
        .doc-ref-strip td.doc-ref-cell-right { text-align: right; padding-right: 0; }
        .doc-ref-label { color: #444; }
        .doc-ref-value { font-weight: bold; }
        .po-meta-td { font-size: 9px; line-height: 1.4; padding: 0; margin: 0; }
        .po-meta-label { display: inline-block; width: 68px; }
        .po-meta-value { font-weight: bold; }
        .layout { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .layout td, .layout th { border: 1px solid #000; padding: 2px 5px; }
        .no-border td, .no-border th { border: 0 !important; }
        .parties-left-td { width: 50%; padding: 0 !important; line-height: 1.28; border-right: 1px solid #000; vertical-align: top; }
        .parties-left-inner { width: 100%; border-collapse: collapse; margin: 0; }
        .parties-left-inner td { border-left: 0 !important; border-right: 0 !important; border-top: 0 !important; border-bottom: 1px solid #000 !important; padding: 5px 8px; margin: 0; vertical-align: top; }
        .parties-left-inner tr:last-child td { border-bottom: none !important; }
        .parties-right-td { width: 50%; padding: 0 !important; margin: 0; vertical-align: top; border-left: none; }
        .meta-inner { width: 100%; border-collapse: collapse; margin: 0; }
        .meta-inner td { border-left: 0 !important; border-right: 0 !important; border-top: 0 !important; border-bottom: 1px solid #000 !important; padding: 5px 8px; margin: 0; font-size: 10px; line-height: 1.35; vertical-align: top; }
        .meta-inner tr:last-child td { border-bottom: none !important; }
        .meta-inner td.meta-split-left { width: 50%; border-right: 1px solid #000 !important; }
        .meta-inner td.meta-split-right { width: 50%; }
        .terms-text { font-size: 8.5px; line-height: 1.22; }
        .seller-inner td { border: 0; padding: 0 4px 0 0; vertical-align: top; }
        .logo-img {  width: 120px;
            max-width: 120px;
            margin-top: 8px; display: block; }
        .company-name { font-weight: bold; font-size: 10.5px; }
        .section-label { font-size: 10px; color: #444; }
        .buyer-name { font-weight: bold; font-size: 10.5px; }
        .meta-lbl { font-size: 10px; color: #333; }
        .meta-val { font-weight: bold; font-size: 10px; }
        .terms-title { font-weight: bold; font-size: 9.5px; }
        .order-details-title {
            font-weight: bold;
            font-size: 11px;
            letter-spacing: 0.2px;
            background: #e8e8e8;
            padding: 3px 5px;
            margin: 0 0 4px 0;
            border-left: 3px solid #222;
        }
        .items-table { width: 100%; border-collapse: collapse; border: 1px solid #000; border-top: none; }
        .items-table th, .items-table td { border: 1px solid #000; padding: 2px 4px; vertical-align: top; }
        .items-table thead th { background: #f0f0f0; font-weight: bold; font-size: 10px; text-align: center; padding: 2px 3px; }
        .c-sl { width: 30px; text-align: center; }
        .c-hsn { width: 72px; text-align: center; }
        .c-qty { width: 60px; text-align: center; }
        .c-rate { width: 70px; text-align: right; }
        .c-amt { width: 80px; text-align: right; }
        .item-desc { font-weight: bold; font-size: 10px; }
        .item-sub { font-size: 8.5px; color: #333; font-style: italic; line-height: 1.2; }
        tr.spacer-row td { height: {{ (int) ($po_spacer_row_height ?? 14) }}px; padding: 0; line-height: 1px; font-size: 1px; border-top: none !important; border-bottom: none !important; }
        tr.spacer-row td.c-sl, tr.spacer-row td.c-amt { border-left: 1px solid #000 !important; border-right: 1px solid #000 !important; }
        .tax-label { text-align: right; font-weight: bold; font-style: italic; }
        .items-table tbody tr.item-line-row td, .items-table tbody tr.tax-row td { border-top: none !important; border-bottom: none !important; }
        .tax-row td { font-style: normal; font-weight: bold; }
        .total-row td { font-weight: bold; background: #f9f9f9; }
        .total-amt { font-size: 11px; text-align: right; }
        .footer-left { width: 58%; padding: 3px 6px; line-height: 1.28; }
        .footer-right { width: 42%; padding: 3px 6px; line-height: 1.28; }
        .amount-words { font-weight: bold; font-size: 10px; margin: 2px 0 3px; }
        .eoe { text-align: right; font-style: italic; font-size: 10px; }
        .decl-lbl { font-size: 10px; font-weight: bold; }
        .decl-txt { font-size: 8px; line-height: 1.22; }
        .bank-lbl { font-weight: bold; font-size: 9.5px; margin-bottom: 2px; }
        .bk-key { color: #555; width: 95px; float: left; }
        .bk-val { display: block; overflow: hidden; }
        .sign-block { text-align: right; margin-top: 6px; font-size: 9px; }
        .sign-for { font-weight: bold; margin-bottom: 4px; }
        .auth-signature-css {
            margin-top: 8px;
            margin-bottom: 2px;
            font-family: DejaVu Sans, sans-serif;
            font-size: 14px;
            line-height: 1;
            color: #1f4f8f;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .auth-signature-line {
            border-top: 1px solid #333;
            margin-top: 2px;
            margin-bottom: 2px;
            width: 120px;
            height: 1px;
        }
        .foot-note { text-align: center; font-size: 8.5px; color: #444; margin-top: 2px; padding-top: 2px; border-top: 1px solid #ccc; }
        .po-cancelled-watermark-wrap {
            position: absolute;
            top: 330px;
            left: 0;
            width: 100%;
            z-index: 1000;
            border-collapse: collapse;
        }
        .po-cancelled-watermark-wrap td {
            border: 0 !important;
            text-align: center;
            padding: 0;
            vertical-align: middle;
        }
        .po-cancelled-watermark {
            width: 460px;
            height: 120px;
            display: block;
            margin: 0 auto;
            opacity: 0.9;
        }
        .po-cancelled-watermark-text {
            display: inline-block;
            font-size: 54px;
            font-weight: 700;
            letter-spacing: 3px;
            color: #b00020;
            opacity: 0.22;
            border: 4px solid #b00020;
            padding: 8px 22px;
            line-height: 1.05;
            text-transform: uppercase;
            white-space: nowrap;
            transform: rotate(-28deg);
            -webkit-transform: rotate(-28deg);
            transform-origin: center center;
        }
        .aadhaar-valid-stamp {
            position: absolute;
            right: 10px;
            bottom: 28px;
            width: 132px;
            border: 1px solid #42b426;
            padding: 4px 6px 6px;
            background: #fff;
            font-size: 6.5px;
            line-height: 1.32;
            color: #111;
            text-align: left;
            z-index: 10;
            box-sizing: border-box;
        }
        .aadhaar-valid-stamp .stamp-head {
            min-height: 54px;
            margin-bottom: 3px;
            text-align: center;
        }
        .aadhaar-valid-stamp .stamp-title {
            font-size: 9.5px;
            font-weight: 700;
            letter-spacing: 0.1px;
            padding-top: 0;
            text-align: center;
            line-height: 1.2;
        }
        .aadhaar-valid-stamp .stamp-tick-img {
            width: 36px;
            height: 24px;
            display: block;
            margin: 0 auto 3px;
        }
        .aadhaar-valid-stamp .stamp-body {
            font-size: 6.5px;
            line-height: 1.34;
            text-align: left;
            position: relative;
            z-index: 2;
        }
        .aadhaar-valid-stamp .stamp-body .stamp-line {
            display: block;
            margin: 0;
            padding: 0;
        }
        .aadhaar-verified-text {
            position: absolute;
            right: 18px;
            bottom: 24px;
            border: 1px solid #111;
            padding: 3px 8px;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            background: #fff;
            z-index: 10;
        }
        .for-pdf .po-meta-label,
        .for-pdf .bk-key { float: left; }
    </style>
</head>

<body class="{{ !empty($for_pdf) ? 'for-pdf' : '' }}"@empty($for_pdf) onload="setTimeout(function(){ try { window.focus(); window.print(); } catch(e){} }, 250)"@endempty>
    @php
        $logoSrc = '';
        // Prefer local PNG as embedded data URI; then fallback to absolute URL for browser.
        $logoCandidates = [
            public_path('home/images/logo_tagline.png'),
            public_path('public/home/images/logo_tagline.png'),
            public_path('home/images/logo.png'),
            public_path('public/home/images/logo.png'),
        ];
        foreach ($logoCandidates as $logoFile) {
            if (!is_file($logoFile)) {
                continue;
            }
            $candidate = (string) (po_pdf_file_to_data_uri($logoFile) ?? '');
            if ($candidate === '' && po_pdf_images_supported()) {
                $raw = @file_get_contents($logoFile);
                if ($raw !== false && $raw !== '') {
                    $candidate = 'data:image/png;base64,' . base64_encode($raw);
                }
            }
            $candidate = po_pdf_safe_dompdf_image_src($candidate);
            if ($candidate !== '') {
                $logoSrc = $candidate;
                break;
            }
        }
        if ($logoSrc === '') {
            $logoSrc = trim((string) po_pdf_safe_dompdf_image_src((string) po_pdf_logo_src()));
        }
        $orderStatusRaw = strtolower(trim((string) (
            $order_status
                ?? data_get($order ?? null, 'status', '')
                ?? data_get($order ?? null, 'resolved_status', '')
                ?? ''
        )));
        if ($orderStatusRaw === '' && isset($status)) {
            $orderStatusRaw = strtolower(trim((string) $status));
        }
        $showCancelledWatermark = (($is_order_cancelled ?? false) === true || !empty($is_order_cancelled))
            || in_array($orderStatusRaw, ['cancelled', 'canceled', 'rejected', 'failed', 'void'], true);
        $cancelledWatermarkSrc = '';
        try {
            $cancelledWatermarkSrc = trim((string) po_pdf_cancelled_watermark_img_src());
        } catch (\Throwable $e) {
            $cancelledWatermarkSrc = '';
        }
        $canUseImageWatermark = $cancelledWatermarkSrc !== '';
        $transferDomain = strtolower(trim((string) (
            $transfer_domain
                ?? data_get($order ?? null, 'transfer_domain', '')
        )));
        if (in_array($transferDomain, ['1', 'true'], true)) {
            $transferDomain = 'yes';
        } elseif (in_array($transferDomain, ['0', 'false'], true)) {
            $transferDomain = 'no';
        }

        // Enrollment type from transfer_domain only: yes → Transfer Service, else → New Service
        $enrollmentType = $transferDomain === 'yes' ? 'Transfer Service' : 'New Service';
        $gstTotal = (float) ($gst_total ?? 0);
        $taxType = strtolower(trim((string) ($tax_type ?? '')));
        $cgstTotal = (float) ($cgst_total ?? 0);
        $sgstTotal = (float) ($sgst_total ?? 0);
        $igstTotal = (float) ($igst_total ?? 0);
        $isCreditNoteDoc = strcasecmp(trim((string) ($document_title ?? '')), 'Credit Note') === 0
            || ($order_status ?? '') === 'credit_note'
            || !empty($is_credit_note)
            || str_starts_with(strtoupper(trim((string) ($po_number ?? ''))), 'CN');

        $discountAmount = max(0, (float) ($coupon_discount ?? $discount_amount ?? 0));
        $promoCode = strtoupper(trim((string) ($promo_code ?? '')));

        // ---- Remaining Value (shown on PO/invoice; not shown on Credit Note) ----
        $remainingValue = 0.0;
        if (!$isCreditNoteDoc) {
            $remainingValue = max(0.0, round((float) ($remaining_value ?? $pro_rata_adjustment ?? 0), 2));

            $orderCategory = strtolower(trim((string) (
                data_get($order ?? null, 'order_category', '')
                    ?: data_get($order ?? null, 'category', '')
                    ?: ''
            )));
            $isPlanChange = in_array($orderCategory, [
                'upgrade', 'upgraded', 'downgrade', 'downgraded', 'upgrade service', 'downgrade service',
            ], true)
                || (int) data_get($order ?? null, 'parent_order_id', 0) > 0
                || (int) data_get($order ?? null, 'previous_plan_id', 0) > 0
                || preg_match('/upgrade|downgrade/i', (string) ($enrollmentType ?? '')) === 1;

            if ($remainingValue <= 0 && isset($order)) {
                $remainingValue = max(0.0, round((float) (
                    data_get($order, 'pro_rata_adjustment', 0)
                        ?: data_get($order, 'remaining_value', 0)
                        ?: data_get($order, 'prorated_credit', 0)
                        ?: 0
                ), 2));
            }

            if ($remainingValue <= 0 && !empty($items) && is_iterable($items)) {
                $fromItems = 0.0;
                foreach ($items as $it) {
                    $fromItems += (float) (
                        data_get($it, 'prorated_credit', 0)
                            ?: data_get($it, 'pro_rata_adjustment', 0)
                            ?: data_get($it, 'remaining_value', 0)
                            ?: 0
                    );
                }
                $remainingValue = max(0.0, round($fromItems, 2));
            }

            if ($remainingValue <= 0 && $isPlanChange) {
                $orderDiscount = max(0.0, round((float) data_get($order ?? null, 'discount_amount', $discountAmount), 2));
                if ($orderDiscount > 0 && $promoCode === '') {
                    $remainingValue = $orderDiscount;
                }
            }

            if ($remainingValue <= 0 && $isPlanChange) {
                $grossSub = (float) ($gross_subtotal ?? 0);
                if ($grossSub <= 0 && !empty($items) && is_iterable($items)) {
                    foreach ($items as $it) {
                        $grossSub += (float) data_get($it, 'total', 0);
                    }
                    $grossSub = round($grossSub, 2);
                }
                $taxable = (float) ($taxable_total ?? $subtotal ?? 0);
                $couponOnly = max(0.0, (float) ($coupon_discount ?? 0));
                if ($promoCode === '' && abs($couponOnly - (float) ($discount_amount ?? 0)) < 0.01) {
                    $couponOnly = 0.0;
                }
                if ($grossSub > $taxable + 0.009) {
                    $derived = round($grossSub - $taxable - $couponOnly, 2);
                    if ($derived >= 0.01) {
                        $remainingValue = $derived;
                    }
                }
            }

            if ($remainingValue > 0 && abs($discountAmount - $remainingValue) < 0.01) {
                $discountAmount = 0.0;
            } elseif ($remainingValue > 0 && $discountAmount >= $remainingValue) {
                $discountAmount = max(0.0, round($discountAmount - $remainingValue, 2));
            }

            if ($remainingValue <= 0 && $isPlanChange && $discountAmount > 0 && $promoCode === '') {
                $remainingValue = $discountAmount;
                $discountAmount = 0.0;
            }

            $remainingValue = max(0.0, round((float) $remainingValue, 2));
        }

        $taxableTotal = (float) ($taxable_total ?? $subtotal ?? 0);
        $currencySymbol = '₹' ?? "\xE2\x82\xB9";
        $poDateAlt = $po_date_alt ?? $po_date ?? '—';

        $gstRate = (float) ($gst_rate ?? 0);
        if ($gstRate <= 0 && $taxableTotal > 0 && $gstTotal > 0) {
            $gstRate = round(($gstTotal / $taxableTotal) * 100, 2);
        }
        if ($gstRate <= 0) {
            $gstRate = 18;
        }
        $taxPctLabel = $taxType === 'igst' ? $gstRate : round($gstRate / 2, 2);

        $totalQty = array_sum(array_map(static fn ($it) => (int) ($it['quantity'] ?? 1), $items ?? []));

        $companyFy = trim((string) ($company['fy_label'] ?? ''));
        $companyTitle = trim($company['name'] ?? 'Company Name');
        $companyTitleFy = $companyFy !== '' ? $companyTitle . ' (FY ' . $companyFy . ')' : $companyTitle;

        $amtWords = '—';
        if (class_exists(\NumberFormatter::class)) {
            try {
                $fmt = new \NumberFormatter('en_IN', \NumberFormatter::SPELLOUT);
                $whole = (int) floor((float) ($total ?? 0));
                $paise = (int) round(((float) ($total ?? 0) - $whole) * 100);
                $w = ucwords(strtolower((string) $fmt->format($whole)));
                $amtWords = 'INR ' . $w . ($paise > 0 ? ' and ' . ucwords(strtolower((string) $fmt->format($paise))) . ' paise' : '') . ' Only';
            } catch (\Throwable $e) {
                $amtWords = 'INR ' . number_format((float) ($total ?? 0), 2) . ' Only';
            }
        } else {
            $amtWords = 'INR ' . number_format((float) ($total ?? 0), 2) . ' Only';
        }

        $termsPay = $terms_of_payment ?? 'Due within 15 days from PO date.';
        $termsDelRaw = $terms_of_delivery ?? 'Payment shall be made within the agreed period from the purchase order date.';
        $serviceDetailsRaw = $service_details ?? '';
        $serviceDetailsLines = [];
        foreach (preg_split("/\r\n|\n|\r/", (string) $serviceDetailsRaw) as $rawLine) {
            $line = trim((string) $rawLine);
            if ($line === '' || preg_match('/^Enrollment type:/i', $line)) {
                continue;
            }
            $serviceDetailsLines[] = $line;
        }
        $declaration = $declaration_text ?? 'TDS Not Applicable-"That the above Software has/have been sold without any Modification. Pursuant to notification(Income Tax) No.21/2012F. No.142/10/2012-SO (TPL) Dt. 13-06-2012 This is a resale of software without any notification and tax has been deducted u/s 194J"';

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
        $coStateLine = 'State Name : ' . $coState . ($coStateCode !== '' ? ', Code : ' . $coStateCode : '');
        $buyerAddressLines = preg_split("/\r\n|\n|\r/", trim((string) ($customer['billing_address'] ?? '')));
        $buyerAddressLines = array_values(array_filter(array_map('trim', $buyerAddressLines)));
        if ($buyerAddressLines === [] && trim((string) ($customer['billing_address'] ?? '')) !== '') {
            $buyerAddressLines = [trim((string) $customer['billing_address'])];
        }

        $planCount = is_countable($items ?? null) ? count($items) : 0;
        $extraRows = $isCreditNoteDoc ? 0 : 1; // Remaining Value row is not shown on Credit Note
        if ($discountAmount > 0) {
            $extraRows++;
        }
        if ($gstTotal > 0) {
            $extraRows += ($taxType === 'igst' ? 1 : 2);
        }
        $spacerRowCount = max(2, 22 - ($planCount * 4) - ($extraRows * 2));
        $spacerRowCount = min($spacerRowCount, 20);
        if (isset($po_spacer_rows) && (int) $po_spacer_rows >= 0) {
            $spacerRowCount = min(20, (int) $po_spacer_rows);
        }

        $aadhaarStampDate = '';
        if (!empty($aadhar_verified_at)) {
            try {
                $aadhaarStampDate = \Carbon\Carbon::parse((string) $aadhar_verified_at)->format('Y.m.d H:i:s');
            } catch (\Throwable $e) {
                $aadhaarStampDate = (string) $aadhar_verified_at;
            }
        }
        if ($aadhaarStampDate === '' && ($aadhaar_signature_valid ?? false)) {
            $aadhaarStampDate = now()->format('Y.m.d H:i:s');
        }
        $aadhaarStampTickSrc = '';
        try {
            $aadhaarStampTickSrc = trim((string) aadhaar_signature_stamp_tick_img_src());
        } catch (\Throwable $e) {
            $aadhaarStampTickSrc = '';
        }
        $aadhaarStampUidSuffix = aadhaar_signature_stamp_uid_suffix((string) ($aadhaar_masked ?? ''));
        $renderAadhaarSignatureStamp = ($show_aadhaar_signature_stamp ?? false) === true;
    @endphp

    <div class="page{{ $renderAadhaarSignatureStamp ? ' has-aadhaar-stamp' : '' }}">
        @if ($showCancelledWatermark)
            <table class="po-cancelled-watermark-wrap no-border" cellspacing="0" cellpadding="0">
                <tr>
                    <td>
                        @if ($canUseImageWatermark)
                            <img src="{{ $cancelledWatermarkSrc }}" class="po-cancelled-watermark" width="460" height="120" alt="">
                        @else
                            <span class="po-cancelled-watermark-text">Cancelled</span>
                        @endif
                    </td>
                </tr>
            </table>
        @endif

        <div class="doc-header">
            <div class="hdr-title">{{ $document_title ?? 'Purchase Order' }}</div>
            <table class="doc-ref-strip no-border" cellspacing="0" cellpadding="0">
                <tr>
                    <td style="width:34%;">
                        <span class="doc-ref-label">Order Ref.</span>
                        <span class="doc-ref-value">&nbsp;: {{ $order_no ?? '—' }}</span>
                    </td>
                    <td style="width:33%;">
                        <span class="doc-ref-label">Due Date</span>
                        <span class="doc-ref-value">&nbsp;: {{ $due_date ?? '—' }}</span>
                    </td>
                    <td class="doc-ref-cell-right" style="width:33%;">
                        <span class="doc-ref-label">Enrollment Type</span>
                        <span class="doc-ref-value">&nbsp;: {{ $enrollmentType }}</span>
                    </td>
                </tr>
            </table>
        </div>

        <table class="layout" cellspacing="0" cellpadding="0">
            <tr>
                <td class="parties-left-td">
                    <table class="parties-left-inner" cellspacing="0" cellpadding="0" width="100%">
                        <tr>
                            <td>
                                <table class="seller-inner" cellspacing="0" cellpadding="0" width="100%">
                                    <tr>
                                        <td style="width:148px;">
                                     
                                            @if($logoSrc)
                                            <img src="{{ $logoSrc }}" class="logo-img" alt="Company Logo">
                                          @endif
                                        </td>
                                        <td>
                                            <div class="company-name">{{ $companyTitleFy }}</div>
                                            
                                                <div>{!! nl2br(e($company['address'])) !!}</div>
                                           
                                            @if ($coAddressLines === [])
                                                <div>—</div>
                                            @endif
                                            @if ($coPan !== '')
                                                <div class="pan">PAN: {{ $coPan }}</div>
                                            @endif
                                            @if ($coGstin !== '')
                                                <div>GSTIN/UIN: {{ $coGstin }}</div>
                                            @endif
                                           
                                            <div>State Name : Maharashtra, Code : 27</div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="section-label">Buyer (Bill to)</div>
                                <div class="buyer-name">{{ $customer['name'] ?? '—' }}</div>
                                @foreach ($buyerAddressLines as $line)
                                    <div>{{ $line }}</div>
                                @endforeach
                                @if ($buyerAddressLines === [])
                                    <div>—</div>
                                @endif
                                @if (!empty($customer['phone']))
                                    <div>Phone: {{ $customer['phone'] }}</div>
                                @endif
                                <div>GSTIN/UIN : {{ $custGstin }}</div>
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="parties-right-td">
                    <table class="meta-inner" cellspacing="0" cellpadding="0" width="100%">
                        <tr class="meta-row-split">
                            <td class="meta-split-left">
                                <div class="meta-lbl">{{ $isCreditNoteDoc ? 'CN No.' : 'PO No.' }}</div>
                                <div class="meta-val">{{ $po_number ?? '—' }}</div>
                            </td>
                            <td class="meta-split-right">
                                <div class="meta-lbl">{{ $isCreditNoteDoc ? 'CN Date' : 'Po Date' }}</div>
                                <div class="meta-val">{{ date('d-m-Y', strtotime($poDateAlt)) }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <div class="meta-lbl">Mode/Terms of Payment</div>
                                <div class="meta-val">{{ $termsPay }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <div class="terms-title">Terms of Delivery</div>
                                <div class="terms-text">{!! nl2br(e($termsDelRaw)) !!}</div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

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
                @foreach ($items ?? [] as $idx => $item)
                    @php
                        $qty = (int) ($item['quantity'] ?? 1);
                        $lineTotal = (float) ($item['total'] ?? 0);
                        $rate = $qty > 0 ? round($lineTotal / $qty, 2) : $lineTotal;
                        $hsn = trim((string) ($item['hsn_sac'] ?? ''));
                        if ($hsn === '') {
                            $hsn = '997331';
                        }
                        $itemName = trim((string) ($item['name'] ?? ''));
                        // Avoid printing the same wording thrice (name + description + duplicate lines).
                        $descLines = [];
                        foreach (preg_split("/\r\n|\n|\r/", (string) ($item['description'] ?? '')) as $rawLine) {
                            $line = trim((string) $rawLine);
                            if ($line === '' || strcasecmp($line, $itemName) === 0) {
                                continue;
                            }
                            if (!in_array($line, $descLines, true)) {
                                $descLines[] = $line;
                            }
                        }
                    @endphp
                    <tr class="item-line-row">
                        <td class="c-sl">{{ $idx + 1 }}</td>
                        <td>
                            <div class="item-desc">{{ $itemName !== '' ? $itemName : '—' }}</div>
                            <div class="terms-text">Enrollment type: {{ $enrollmentType }}</div>
                            @foreach ($serviceDetailsLines as $detailLine)
                                <div class="terms-text">{{ $detailLine }}</div>
                            @endforeach
                            @foreach ($descLines as $descLine)
                                <div class="item-sub">{{ $descLine }}</div>
                            @endforeach
                        </td>
                        <td class="c-hsn">{{ $hsn }}</td>
                        <td class="c-qty">{{ $qty }} nos</td>
                        <td class="c-rate">{{ number_format($rate, 2) }}</td>
                        <td class="c-amt">{{ number_format($lineTotal, 2) }}</td>
                    </tr>
                @endforeach

                @php
                    $isCreditNote = !empty($isCreditNoteDoc)
                        || strcasecmp(trim((string) ($document_title ?? '')), 'Credit Note') === 0
                        || ($order_status ?? '') === 'credit_note'
                        || !empty($is_credit_note)
                        || str_starts_with(strtoupper(trim((string) ($po_number ?? ''))), 'CN');
                @endphp
                @if (!$isCreditNote)
                    <tr class="tax-row">
                        <td class="c-sl"></td>
                        <td class="tax-label">Remaining Value</td>
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
                @endif

                @if ($discountAmount > 0)
                    <tr class="tax-row">
                        <td class="c-sl"></td>
                        <td class="tax-label">
                            Discount
                            @if ($promoCode !== '')
                                (Coupon: {{ $promoCode }})
                            @endif
                        </td>
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
                            <td class="c-hsn"></td>
                            <td class="c-qty"></td>
                            <td class="c-rate">{{ number_format($taxPctLabel, 0) }} %</td>
                            <td class="c-amt">{{ number_format($igstTotal, 2) }}</td>
                        </tr>
                    @else
                        <tr class="tax-row">
                            <td class="c-sl"></td>
                            <td class="tax-label">OUTPUT CGST</td>
                            <td class="c-hsn"></td>
                            <td class="c-qty"></td>
                            <td class="c-rate">{{ number_format($taxPctLabel, 0) }} %</td>
                            <td class="c-amt">{{ number_format($cgstTotal, 2) }}</td>
                        </tr>
                        <tr class="tax-row">
                            <td class="c-sl"></td>
                            <td class="tax-label">OUTPUT SGST</td>
                            <td class="c-hsn"></td>
                            <td class="c-qty"></td>
                            <td class="c-rate">{{ number_format($taxPctLabel, 0) }} %</td>
                            <td class="c-amt">{{ number_format($sgstTotal, 2) }}</td>
                        </tr>
                    @endif
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
                    <td class="c-amt total-amt">{{ $currencySymbol }} {{ number_format((float) ($total ?? 0), 2) }}</td>
                </tr>
            </tbody>
        </table>

        <table class="layout" cellspacing="0" cellpadding="0" style="border-top:none;">
            <tr>
                <td class="footer-left">
                    <div style="font-size:10px;color:#444;">Amount Chargeable (in words)</div>
                    <div class="amount-words">{{ $amtWords }}</div>
                    <div class="eoe">E. &amp; O.E</div>
                    <div class="decl-lbl">Declaration</div>
                    <div class="decl-txt">{{ $declaration }}</div>
                </td>
                <td class="footer-right">
                    <div class="bank-lbl">Company's Bank Details</div>
                    <div><span class="bk-key">Bank Name</span><span class="bk-val"> : ICICI BANK A/C NO. 026705002724</span><br><span class="bk-key">A/c No.</span><span class="bk-val"> : 026705002724</span>
                        <br><span class="bk-key">Branch &amp; IFS Code</span><span class="bk-val"> : ICIC0000267</span></div>
                </td>
            </tr>
        </table>

        @if ($renderAadhaarSignatureStamp && $aadhaarStampTickSrc !== '')
        <div class="aadhaar-valid-stamp" aria-label="Aadhaar signature valid">
            <div class="stamp-head">
                <img src="{{ $aadhaarStampTickSrc }}" class="stamp-tick-img" alt="">
                <div class="stamp-title">AADHAAR SIGNATURE<br>VALID</div>
            </div>
            <div class="stamp-body">
                <span class="stamp-line">Aadhaar: XXXX XXXX {{ $aadhaarStampUidSuffix }}</span>
                <span class="stamp-line">Verified: {{ $aadhaarStampDate }}</span>
            </div>
        </div>
    @endif

        <div class="foot-note">{{ $footer_note ?? 'This is a Computer Generated Purchase Order' }}</div>
    </div>
    @empty($for_pdf)
    <script>
        (function () {
            if (typeof window === 'undefined') {
                return;
            }
            var triggerPrint = function () {
                try {
                    window.focus();
                    window.print();
                } catch (e) {}
            };
            if (document.readyState === 'complete') {
                setTimeout(triggerPrint, 250);
            } else {
                window.addEventListener('load', function () {
                    setTimeout(triggerPrint, 250);
                });
                document.addEventListener('DOMContentLoaded', function () {
                    setTimeout(triggerPrint, 350);
                });
            }
        })();
    </script>
    @endempty
</body>

</html>
