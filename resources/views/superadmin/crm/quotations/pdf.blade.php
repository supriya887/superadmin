<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Quotation {{ $quotation->quotation_no }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 24px; font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #444; }
        table { border-collapse: collapse; }
        .header { width: 100%; margin-bottom: 12px; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
        .header td { vertical-align: top; }
        .qno span { color: #555; font-size: 12px; }
        .qno strong { display: block; font-size: 13px; margin-top: 2px; }
        .qno small { display: block; margin-top: 3px; font-size: 11px; color: #555; }
        .company { text-align: center; font-size: 15px; font-weight: 700; color: #222; }
        .logo { text-align: right; }
        .logo img { width: 140px; height: auto; }
        .to { margin-top: 16px; }
        .to p { margin: 0 0 4px; }
        .to strong, .to span { display: block; }
        .subject { text-align: center; margin: 20px 0 14px; }
        .subject strong { font-size: 13px; }
        .content p { margin: 0 0 12px; line-height: 1.55; }
        .commercial { font-size: 13px; font-weight: 700; margin: 18px 0 8px; }
        .commercial strong { font-weight: 500; }
        .qtable { width: 100%; }
        .qtable th { background: #E6F6FB; padding: 8px 8px; text-align: left; font-weight: 600; font-size: 11px; }
        .qtable th.center, .qtable td.center { text-align: center; }
        .qtable th.right, .qtable td.right { text-align: right; }
        .qtable td { padding: 8px; border-bottom: 1px solid #e5eef2; font-size: 11px; }
        .summary td { font-weight: 600; }
        .total td { font-weight: 700; background: #f8fbfd; }
        .terms { margin-top: 16px; font-size: 11px; line-height: 1.55; }
        .sign { margin-top: 18px; }
        .sign span, .sign strong { display: block; }
        .sign strong { margin: 4px 0; }
        .footer { margin-top: 22px; padding-top: 10px; border-top: 1px solid #ddd; font-size: 11px; }
        .footer small { display: block; color: #777; }
        .footer strong { display: block; margin: 4px 0; }
        .contacts { margin-top: 6px; }
    </style>
</head>
<body>
@php
    $pageQuotation = $quotation;
    $pageSheets = $sheets;
    $validDate = optional($pageQuotation->valid_date)->format('d F Y') ?: optional($pageQuotation->created_at)->format('d F Y');
    $createdDate = optional($pageQuotation->created_at)->format('d F Y');
    $cityLine = collect([
        collect([$pageQuotation->city, $pageQuotation->state])->filter()->implode(', '),
        $pageQuotation->pincode,
        $pageQuotation->country,
    ])->filter()->implode(' ');
    $subjectName = $pageQuotation->service_name ?: ($pageQuotation->plan_name ?: 'Services');
    $totalQty = collect($pageSheets)->sum('qty');
    if (collect($pageSheets)->contains(fn ($s) => !empty($s['showDomain']))) {
        $totalQty += 1;
    }
    $totalSub = collect($pageSheets)->sum('sub');
    $totalDiscount = collect($pageSheets)->sum('discountAmt');
    $totalGst = collect($pageSheets)->sum('gstAmt');
    $totalGrand = collect($pageSheets)->sum('grand');
    $logoSrc = $logoSrc ?? '';
    $periodMeta = $pageSheets[0]['periodMeta'] ?? ['period' => ($pageQuotation->quotation_period ?: 'Yearly'), 'unit' => 'Per Annual', 'total' => 'For Year'];
@endphp

<table class="header" width="100%">
    <tr>
        <td width="28%" class="qno">
            <span>Quotation No :</span>
            <strong>{{ $pageQuotation->quotation_no }}</strong>
            <small>{{ $createdDate }}</small>
        </td>
        <td width="44%" class="company">Tizzy Cloud Computing Private Limited</td>
        <td width="28%" class="logo">
            @if($logoSrc)
                <img src="{{ $logoSrc }}" alt="Tizzy Cloud Group">
            @endif
        </td>
    </tr>
</table>

<div class="to">
    <p>To,</p>
    <strong>{{ $pageQuotation->name }},</strong>
    <span>{{ $pageQuotation->company_name }}</span>
    @if($pageQuotation->mailing_address)
        <span>{{ $pageQuotation->mailing_address }}</span>
    @endif
    @if($cityLine)
        <span>{{ $cityLine }}</span>
    @endif
</div>

<div class="subject">
    Subject : <strong>Quotation for {{ $subjectName }}</strong>
</div>

<div class="content">
    <p>Dear {{ trim(($pageQuotation->salutation ? $pageQuotation->salutation . '. ' : '') . $pageQuotation->name) }},</p>
    <p>
        Tizzy Cloud is a team of enthusiastic techies specialized in Cloud Computing Services.
        Since inception we are focused on educating rather than just selling services.
        Gaining trust of our elite clients, we have a customer retain ratio of almost 94%.
    </p>
    <p>
        We are a Cloud Business Email Hosting Company. We provide Cloud Email Service named as
        ‘Tizzy Mail’. We have 13000+ Email users on our robust infra hosted on Amazon AWS INDIA DC.
        We are serving 500+ mid-sized and corporate companies along with dozens of IT companies
        empowering them to provide our premium Tizzy Mail services to their clients.
        Automated backup is done on S3 Bucket of AWS for 30 days. Customized paid backup service
        to store data up to 1 year is also provided with an additional cost. Zero IP blacklisting
        issue for Tizzy Mail Users. Plans starting as low as 10 GB to Corporate Plan of 1 TB Cloud
        Storage.
    </p>
    <p>
        We are official partners of Amazon AWS, Microsoft &amp; Google. We provide Microsoft 365
        &amp; Google Workspace Licenses with its technical assistance.
    </p>
</div>

<div class="commercial">
    Commercial Proposal for requirement : <strong>{{ $subjectName }}</strong>
</div>

<table class="qtable">
    <thead>
        <tr>
            <th>SKU</th>
            <th>Description</th>
            <th>Price Per Unit </th>
            <th class="center">Licenses</th>
            <th class="right">Total Price </th>
        </tr>
    </thead>
    <tbody>
        @foreach ($pageSheets as $line)
            @php
                $linePlan = $line['plan'];
                $lineQty = (int) ($linePlan['quantity'] ?? $line['qty'] ?? 0);
                $lineRate = (float) ($linePlan['rate'] ?? 0);
            @endphp
            <tr>
                <td>{{ $linePlan['sku'] ?? '-' }}</td>
                <td>{{ $linePlan['name'] ?? '-' }}@if(!empty($line['period'])) ({{ $line['period'] }})@endif
                    @if(!empty($linePlan['note']))
                        — {{ $linePlan['note'] }}
                    @endif
                </td>
                <td>₹ {{ number_format($lineRate, 2) }}</td>
                <td class="center">{{ $lineQty }}</td>
                <td class="right">₹ {{ number_format($lineQty * $lineRate, 2) }}</td>
            </tr>
            @if(!empty($line['showDomain']))
                <tr>
                    <td>{{ $pageQuotation->sac ?: '-' }}</td>
                    <td>Domain - {{ $pageQuotation->domain_name }}@if($pageQuotation->domain_start) ({{ $pageQuotation->domain_start->format('d-m-Y') }}@if($pageQuotation->domain_end) to {{ $pageQuotation->domain_end->format('d-m-Y') }}@endif)@endif</td>
                    <td>₹ {{ number_format((float) $pageQuotation->domain_rate, 2) }}</td>
                    <td class="center">1</td>
                    <td class="right">₹ {{ number_format((float) $pageQuotation->domain_rate, 2) }}</td>
                </tr>
            @endif
        @endforeach
        <tr class="summary">
            <td></td>
            <td>Sub Total</td>
            <td></td>
            <td class="center">{{ $totalQty }}</td>
            <td class="right">₹ {{ number_format($totalSub, 2) }}</td>
        </tr>
        <tr>
            <td></td>
            <td colspan="2">Discount {{ (float) $pageQuotation->discount }}%</td>
            <td></td>
            <td class="right">₹ {{ number_format($totalDiscount, 2) }}</td>
        </tr>
        <tr>
            <td></td>
            <td colspan="2">GST (18%)</td>
            <td></td>
            <td class="right">₹ {{ number_format($totalGst, 2) }}</td>
        </tr>
        <tr class="total">
            <td></td>
            <td colspan="2"><strong>Total</strong></td>
            <td></td>
            <td class="right"><strong>₹ {{ number_format($totalGrand, 2) }}</strong></td>
        </tr>
    </tbody>
</table>

<div class="terms">
    <p>
        This quotation is valid till {{ $validDate }}. All Orders and payments will be in favour
        of ‘Tizzy Cloud Computing Private Limited’. Price quoted is exclusive of 18% GST.
        Payment term is 100% Advance. Activation/Transfer/Renewal within 3-5 working days.
        License term is {{ $periodMeta['period'] }} commitment &amp; payment.
    </p>
    <p>It would be our pleasure serving you.</p>
</div>

<div class="sign">
    <span>Regards,</span>
    <strong>Shradha Kadam</strong>
    <span>Hr Admin – Cloud Services</span>
    <span>+91 9081929293</span>
</div>

<div class="footer">
    <small>Corporate Office Address :</small>
    <strong>Tizzy Cloud Computing Private Limited</strong>
    <span>
        Office No. 511, Kalpataru Avenue, Akurli Road,
        Kandivali (East), Mumbai – 400101, Maharashtra. INDIA
    </span>
    <div class="contacts">
        +91 9925 9925 99 &nbsp;|&nbsp; www.tizzycloud.com &nbsp;|&nbsp; sales@tizzycloud.com
    </div>
</div>
</body>
</html>
