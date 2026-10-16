@php
$pageQuotation = $pageQuotation ?? $quotation;
$pageSheets = $pageSheets ?? $sheets;
$isOpen = $isOpen ?? true;
$showArrow = $showArrow ?? false;
$pageLatest = $pageLatest ?? false;
$logo = $logo ?? asset('superadmin/crm/images/logo.png');
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
$periodMeta = $pageSheets[0]['periodMeta'] ?? ['period' => ($pageQuotation->quotation_period ?: 'Yearly'), 'unit' => 'Per Annual', 'total' => 'For Year'];
@endphp

@php
$quoteNow = strtolower((string) ($pageQuotation->quotation_status ?? ''));
$invoiceNow = strtolower((string) ($pageQuotation->invoice_status ?? ''));
$wasShared = (bool) $pageQuotation->sent_at || $quoteNow === 'shared';
if (in_array($quoteNow, ['approved', 'rejected'], true)) {
$quotationStatus = $quoteNow;
} elseif (in_array($invoiceNow, ['approved', 'rejected'], true) && $quoteNow !== 'shared') {
$quotationStatus = $invoiceNow;
$invoiceNow = 'not_sent';
} elseif ($wasShared) {
$quotationStatus = 'shared';
} else {
$quotationStatus = 'not_shared';
}
$invoiceStatus = $quotationStatus === 'approved'
? (in_array($invoiceNow, ['not_sent', 'sent', 'approved', 'rejected'], true) ? $invoiceNow : 'not_sent')
: 'not_sent';
$quoteLabels = [
'not_shared' => 'Not shared',
'shared' => 'Shared, approval pending',
'approved' => 'Approved',
'rejected' => 'Rejected',
];
$invoiceLabels = [
'not_sent' => 'Not sent',
'sent' => 'Sent, approval pending',
'approved' => 'Approved',
'rejected' => 'Rejected',
];
$waPhone = preg_replace('/\D/', '', (string) optional($pageQuotation->lead)->mobile);
$waCode = preg_replace('/\D/', '', (string) (optional($pageQuotation->lead)->mobile_code ?: '91'));
$waText = rawurlencode('Tax invoice for quotation ' . ($pageQuotation->quotation_no ?: ('#' . $pageQuotation->id)));
$waLink = $waPhone !== '' ? ('https://wa.me/' . $waCode . $waPhone . '?text=' . $waText) : ('https://wa.me/?text=' . $waText);
@endphp

<div>

    <div class="quotation-top-meta">
        <div class="qmeta-field">
            <span>Customer status</span>
            <strong>{{ $quoteLabels[$quotationStatus] ?? ucfirst($quotationStatus) }}</strong>
        </div>
        <div class="qmeta-field">
            <span>Invoice</span>
            <strong>{{ $quotationStatus === 'approved' ? ($invoiceLabels[$invoiceStatus] ?? ucfirst($invoiceStatus)) : '—' }}</strong>
        </div>
        <div class="qmeta-field">
            <a href="{{ route('crm.quotations.activity', $pageQuotation) }}" class="quotation-activity-link">Send &amp; approval history</a>
        </div>
    </div>

    <div class="quotation-header quotation-toggle">

        <div class="quotation-number">
            <span>Quotation No :</span>
            <strong>{{ $pageQuotation->quotation_no }}</strong>
            <small>{{ $createdDate }}</small>


        </div>

        <div class="quotation-company">
            Tizzy Cloud Computing Private Limited

        </div>

        <div class="quotation-logo">
            <img src="{{ $logo }}" alt="Tizzy Cloud Group">
            @if($showArrow)
            <div class="quotation-arrow">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 9l6 6 6-6" />
                </svg>
            </div>
            @endif
        </div>

    </div>

    <div class="quotation-body">

        <div class="quotation-to">
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

        <div class="quotation-subject">
            Subject :
            <strong>Quotation for {{ $subjectName }}</strong>
        </div>

        <div class="quotation-content">
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

        <div class="commercial-title">
            Commercial Proposal for requirement :
            <strong>{{ $subjectName }}</strong>
        </div>

        <div class="quotation-table-wrap">
            <table class="quotation-table">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Description</th>
                        <th>Price Per Unit </th>
                        <th>Licenses</th>
                        <th>Total Price </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pageSheets as $line)
                    @php
                    $linePlan = $line['plan'];
                    $lineQty = (int) ($linePlan['quantity'] ?? 0);
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
                        <td>{{ $lineQty }}</td>
                        <td>₹ {{ number_format($lineQty * $lineRate, 2) }}</td>
                    </tr>
                    @if(!empty($line['showDomain']))
                    <tr>
                        <td>{{ $pageQuotation->sac ?: '-' }}</td>
                        <td>Domain - {{ $pageQuotation->domain_name }}@if($pageQuotation->domain_start) ({{ $pageQuotation->domain_start->format('d-m-Y') }}@if($pageQuotation->domain_end) to {{ $pageQuotation->domain_end->format('d-m-Y') }}@endif)@endif</td>
                        <td>₹ {{ number_format((float) $pageQuotation->domain_rate, 2) }}</td>
                        <td>1</td>
                        <td>₹ {{ number_format((float) $pageQuotation->domain_rate, 2) }}</td>
                    </tr>
                    @endif
                    @endforeach
                    <tr class="summary-row">
                        <td></td>
                        <td>Sub Total</td>
                        <td></td>
                        <td>{{ $totalQty }}</td>
                        <td>₹ {{ number_format($totalSub, 2) }}</td>
                    </tr>
                    <tr>
                        <td></td>
                        <td colspan="2">Discount {{ (float) $pageQuotation->discount }}%</td>
                        <td></td>
                        <td>₹ {{ number_format($totalDiscount, 2) }}</td>
                    </tr>
                    <tr>
                        <td></td>
                        <td colspan="2">GST (18%)</td>
                        <td></td>
                        <td>₹ {{ number_format($totalGst, 2) }}</td>
                    </tr>
                    <tr class="total-row">
                        <td></td>
                        <td colspan="2"><strong>Total</strong></td>
                        <td></td>
                        <td><strong>₹ {{ number_format($totalGrand, 2) }}</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="quotation-terms">
            <p>
                This quotation is valid till {{ $validDate }}. All Orders and payments will be in favour
                of ‘Tizzy Cloud Computing Private Limited’. Price quoted is exclusive of 18% GST.
                Payment term is 100% Advance. Activation/Transfer/Renewal within 3-5 working days.
                License term is {{ $periodMeta['period'] }} commitment &amp; payment.
            </p>
            <p>It would be our pleasure serving you.</p>
        </div>

        <div class="quotation-signature">
            <span>Regards,</span>
            <strong>Shradha Kadam</strong>
            <span>Hr Admin – Cloud Services</span>
            <span>+91 9081929293</span>
        </div>

        <div class="quotation-footer">
            <small>Corporate Office Address :</small>
            <strong>Tizzy Cloud Computing Private Limited</strong>
            <span>
                Office No. 511, Kalpataru Avenue, Akurli Road,
                Kandivali (East), Mumbai – 400101, Maharashtra. INDIA
            </span>
            <div class="footer-contact">
                <span><i data-lucide="phone"></i> +91 9925 9925 99</span>
                <span><i data-lucide="globe"></i> www.tizzycloud.com</span>
                <span><i data-lucide="mail"></i> sales@tizzycloud.com</span>
            </div>
        </div>

        <div class="quotation-actions">
            @if(empty($isLocked))
            <button type="button" data-modal-open="sendDiscountModal">
                <i data-lucide="corner-up-right"></i>
                Send For More Discount
            </button>
            @endif
            <button type="button" onclick="window.location.href='{{ route('crm.quotations.download', $pageQuotation) }}'">
                <i data-lucide="download"></i>
                Download
            </button>
            @if(empty($isLocked))
            <button type="button" onclick="window.location.href='{{ route('crm.quotations.edit', $pageQuotation) }}'">
                <i data-lucide="pencil"></i>
                Edit
            </button>
            @endif
            @if($pageLatest && empty($isLocked))
            <button type="button" data-modal-open="sendQuotationModal" onclick="var qid=document.getElementById('sendQuotationId'); if(qid){qid.value='{{ $pageQuotation->id }}';} var e=document.getElementById('sendQuotationEmails'); if(e){e.value='{{ implode("\n", array_values(array_unique(array_filter(array_merge([$pageQuotation->email], $pageQuotation->sent_emails ?? []))))) }}';} var pe=document.getElementById('sendQuotationPrimaryEmail'); if(pe){pe.value='{{ strtolower($pageQuotation->email) }}';}">
                <i data-lucide="mail"></i>
                Send
            </button>
            @endif
            <button type="button" onclick="window.location.href='{{ route('crm.quotations.activity', $pageQuotation) }}'">
                <i data-lucide="history"></i>
                History
            </button>
        </div>

    </div>
</div>
