<?php

namespace App\Http\Controllers\SuperAdmin\Crm\Api;

use App\Http\Controllers\SuperAdmin\Crm\QuotationController as WebQuotationController;
use App\Models\Quotation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QuotationController extends WebQuotationController
{
    public function index(Request $request)
    {
        $data = parent::index($request)->getData();
        $quotations = $data['quotations'];

        return response()->json([
            'success' => true,
            'message' => 'Quotations fetched successfully.',
            'data' => [
                'quotations' => $quotations->items(),
                'pagination' => [
                    'current_page' => $quotations->currentPage(),
                    'last_page' => $quotations->lastPage(),
                    'per_page' => $quotations->perPage(),
                    'total' => $quotations->total(),
                ],
                'status_counts' => $data['statusCounts'],
            ],
        ]);
    }

    public function create(Request $request)
    {
        $data = parent::create($request)->getData();

        return response()->json([
            'success' => true,
            'message' => 'Send this body to POST /api/crm/quotations.',
            'data' => [
                'sample' => [
                    'deal_type' => 'new',
                    'lead_id' => null,
                    'salutation' => 'Mr',
                    'name' => '',
                    'company_name' => '',
                    'gstin' => '',
                    'email' => '',
                    'mobile_code' => '91',
                    'mobile' => '',
                    'discount' => 0,
                    'domain_type' => '',
                    'domain_name' => '',
                    'domain_rate' => null,
                    'sac' => '',
                    'domain_start' => '',
                    'domain_end' => '',
                    'plans' => [
                        [
                            'from_service_id' => null,
                            'provider_id' => null,
                            'plan_id' => null,
                            'quote_period' => 'Yearly',
                            'billing_start_date' => '',
                            'sku' => '',
                            'quantity' => 1,
                            'rate' => null,
                        ],
                    ],
                ],
                'lead' => $data['lead'],
                'services' => $data['services'],
                'current_services' => $data['currentServices'],
                'leads' => $data['leads'],
                'providers' => $data['providers'],
                'plans' => $data['providerPlans'],
                'options' => [
                    'deal_type' => Quotation::DEAL_TYPES,
                    'salutation' => ['Mr', 'Mrs', 'Ms'],
                    'domain_type' => ['existing', 'new'],
                    'quote_period' => ['Monthly', 'Quarterly', 'Half Yearly', 'Yearly', '3 Yearly'],
                ],
            ],
        ]);
    }

    public function store(Request $request)
    {
        parent::store($request);

        $quotation = Quotation::where('email', $request->email)->latest('id')->first();

        return response()->json([
            'success' => true,
            'message' => 'Quotation saved.',
            'data' => ['quotation' => $quotation],
        ], 201);
    }

    public function show(Quotation $quotation)
    {
        $data = parent::show($quotation)->getData();

        return response()->json([
            'success' => true,
            'message' => 'Quotation fetched successfully.',
            'data' => [
                'quotation' => $data['quotation'],
                'sheets' => $data['sheets'],
                'is_latest' => $data['isLatest'],
                'is_locked' => $data['isLocked'],
                'versions' => $data['siblingQuotes'],
            ],
        ]);
    }

    public function update(Request $request, Quotation $quotation)
    {
        $wasSent = (bool) $quotation->sent_at;
        $response = parent::update($request, $quotation);

        if ($error = $this->failed($response)) {
            return $error;
        }

        $quotation = $wasSent
            ? Quotation::where('email', $request->email)->latest('id')->first()
            : $quotation->refresh();

        return response()->json([
            'success' => true,
            'message' => $response->getSession()->get('success'),
            'data' => ['quotation' => $quotation],
        ]);
    }

    public function updateStatuses(Request $request, Quotation $quotation)
    {
        parent::updateStatuses($request, $quotation);

        return response()->json([
            'success' => true,
            'message' => 'Quotation status updated.',
            'data' => ['quotation' => $quotation->refresh()],
        ]);
    }

    public function activity(Quotation $quotation)
    {
        $data = parent::activity($quotation)->getData();

        return response()->json([
            'success' => true,
            'message' => 'Quotation activity fetched successfully.',
            'data' => [
                'quotation_status' => $data['quoteStatus'],
                'invoice_status' => $data['invoiceStatus'],
                'quotation_emails' => $data['quoteEmails'],
                'quotation_cc' => $data['quoteCc'],
                'invoice_emails' => $data['invoiceEmails'],
                'invoice_cc' => $data['invoiceCc'],
                'events' => $data['events'],
            ],
        ]);
    }

    public function send(Request $request)
    {
        $response = parent::send($request);

        if ($error = $this->failed($response)) {
            return $error;
        }

        return response()->json([
            'success' => true,
            'message' => $response->getSession()->get('success'),
        ]);
    }

    public function invoice(Quotation $quotation)
    {
        $response = parent::invoice($quotation);

        if ($error = $this->failed($response)) {
            return $error;
        }

        $data = $response->getData();

        return response()->json([
            'success' => true,
            'message' => 'Invoice fetched successfully.',
            'data' => [
                'quotation' => $data['quotation'],
                'sheets' => $data['sheets'],
                'invoice_no' => $data['invoiceNo'],
                'invoice_date' => $data['invoiceDate'],
                'tax_type' => $data['taxType'],
                'discount_amount' => $data['discountAmount'],
                'igst_total' => $data['igstTotal'],
                'cgst_total' => $data['cgstTotal'],
                'sgst_total' => $data['sgstTotal'],
                'grand_total' => $data['grandTotal'],
                'amount_in_words' => $data['amtWords'],
            ],
        ]);
    }

    public function downloadInvoice(Quotation $quotation)
    {
        $response = parent::downloadInvoice($quotation);

        return $this->failed($response) ?? $response;
    }

    public function sendInvoice(Request $request)
    {
        $response = parent::sendInvoice($request);

        if ($error = $this->failed($response)) {
            return $error;
        }

        return response()->json([
            'success' => true,
            'message' => $response->getSession()->get('success'),
        ]);
    }

    protected function failed($response)
    {
        $error = $response instanceof RedirectResponse ? $response->getSession()?->get('error') : null;

        return $error
            ? response()->json(['success' => false, 'message' => $error], 422)
            : null;
    }
}
