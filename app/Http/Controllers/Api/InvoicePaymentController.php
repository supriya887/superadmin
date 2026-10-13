<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SuperAdmin\BillingController;
use App\Models\Customer;
use App\Models\InvoiceMst;
use App\Models\InvoicePaymentTransaction;
use App\Models\InvoicePaymentTransactionItem;
use App\Models\InvoiceSub;
use App\Models\Order;
use App\Models\NotificationPreference;
use App\Models\Partner;
use App\Models\PvSetting;
use App\Services\InvoiceManagement\InvoiceStatusNormalizer;
use App\Services\PartnerCreditService;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class InvoicePaymentController extends Controller
{
    public $invoiceMst;
    public $invoiceSub;
    public $pvSetting;
    public $customer;
    public $order;
    public $partner;

    public function __construct()
    {
        $this->invoiceMst = new InvoiceMst();
        $this->invoiceSub = new InvoiceSub();
        $this->pvSetting = new PvSetting();
        $this->customer = new Customer();
        $this->order = new Order();
        $this->partner = new Partner();
    }

    public function getPaymentDetails(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'invoice_id' => ['required', 'array', 'min:1'],
                'invoice_id.*' => ['integer', 'min:1'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ], 422);
            }

            $invoiceIds = array_unique(array_map('intval', $request->input('invoice_id', [])));

            $invoices = $this->invoiceMst->newQuery()->whereIn('id', $invoiceIds)->get();

            if ($invoices->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice(s) not found',
                ], 404);
            }

            // Check if any invoice is already paid
            foreach ($invoices as $invoice) {
                if (strtolower((string) ($invoice->status ?? '')) === 'paid') {
                    return response()->json([
                        'success' => false,
                        'message' => 'One or more invoices are already paid',
                    ], 400);
                }
            }

            // Close leftover pending checkouts. Real Razorpay tries count; just opening Pay does not.
            $this->recordAbandonedInvoicePaymentAttempts($invoices);

            $paymentRetryBlock = $this->assertInvoicePaymentLinkAllowed($invoices);
            if ($paymentRetryBlock !== null) {
                return $paymentRetryBlock;
            }

            [$key, $secret] = PvSetting::razorpayCredentials();

            if (!$key || !$secret) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment gateway credentials are missing.'
                ], 500);
            }

            $totalAmount = round($invoices->sum(fn($invoice) => $this->resolveInvoicePayableAmount($invoice)), 2);
            $razorpayAmount = (int) round($totalAmount * 100);

            if ($razorpayAmount < 100) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice payable amount must be at least ₹1.00 to proceed with online payment.',
                    'details' => [
                        'total_amount' => $totalAmount,
                        'invoice_ids' => $invoiceIds,
                    ],
                ], 422);
            }

            // Pick customer info from first invoice (if available, or null fallback)
            $firstInvoice = $invoices->first();
            $customer = null;
            if (!empty($firstInvoice->cust_id ?? null)) {
                $customer = $this->customer->newQuery()->where('id', $firstInvoice->cust_id)->first();
            }
            $customerName = (string) ($customer->name ?? '');
            $customerEmail = (string) ($customer->email ?? '');
            $customerMobile = (string) ($customer->mobile ?? '');

            // Generate a group receipt number by joining invoice numbers/ids, max length 40 for Razorpay
            $invoiceNumbers = [];
            foreach ($invoices as $invoice) {
                if (!empty($invoice->bill_no_full)) {
                    $invoiceNumbers[] = (string) $invoice->bill_no_full;
                } else {
                    $invoiceNumbers[] = (string) $invoice->id;
                }
            }
            // Compose a compact, safe receipt id (max 40 chars)
            $prefix = "order_multi_";
            $maxReceiptLength = 40;
            $baseReceipt = $prefix . implode("_", $invoiceNumbers);

            // If too long, fallback to a hash of all IDs, still unique for this combo
            if (strlen($baseReceipt) > $maxReceiptLength) {
                // Attempt to fit as many invoice numbers as possible, or use hash
                $joinedIds = implode("_", $invoiceIds);
                $shortReceipt = $prefix . substr($joinedIds, 0, $maxReceiptLength - strlen($prefix));
                if (strlen($shortReceipt) <= $maxReceiptLength) {
                    $receiptId = $shortReceipt;
                } else {
                    // As a last resort, use a short hash
                    $receiptId = $prefix . substr(md5($joinedIds), 0, $maxReceiptLength - strlen($prefix));
                }
            } else {
                $receiptId = $baseReceipt;
            }

            // Try every credential pair from pv_setting until Razorpay accepts one
            $candidates = method_exists(PvSetting::class, 'razorpayCredentialCandidates')
                ? PvSetting::razorpayCredentialCandidates()
                : [PvSetting::razorpayCredentials()];
            if ($candidates === []) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment gateway credentials are missing in pv_setting.',
                ], 500);
            }

            $response = null;
            $key = '';
            $secret = '';
            $lastAuthFailure = null;

            foreach ($candidates as [$tryKey, $trySecret]) {
                $response = \Illuminate\Support\Facades\Http::withBasicAuth($tryKey, $trySecret)
                    ->post('https://api.razorpay.com/v1/orders', [
                        'amount' => $razorpayAmount,
                        'currency' => 'INR',
                        'receipt' => $receiptId,
                    ]);

                if ($response->successful()) {
                    $key = $tryKey;
                    $secret = $trySecret;
                    break;
                }

                $body = $response->json();
                $message = strtolower((string) data_get($body, 'error.description', ''));
                $isAuthFail = $response->status() === 401
                    || str_contains($message, 'authentication failed')
                    || str_contains($message, 'authentication');

                Log::warning('invoice_payment_razorpay_order_failed', [
                    'invoice_ids' => $invoiceIds,
                    'razorpay_key_prefix' => substr($tryKey, 0, 16),
                    'http_status' => $response->status(),
                    'response' => $body ?: $response->body(),
                ]);

                if ($isAuthFail) {
                    $lastAuthFailure = $response;
                    continue;
                }

                // Non-auth error (amount/receipt/etc.) — stop, don't try other keys
                $key = $tryKey;
                $secret = $trySecret;
                break;
            }

            // Razorpay auth failure — credentials in pv_setting do not match Dashboard API Keys
            if (!$response || $response->failed()) {
                $body = $response ? $response->json() : null;
                $rawMessage = trim((string) data_get($body, 'error.description', ''));
                $lower = strtolower($rawMessage);

                if (
                    $rawMessage === ''
                    || str_contains($lower, 'authentication')
                    || ($response && (int) $response->status() === 401)
                    || $lastAuthFailure !== null
                ) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Razorpay Key Id and Secret do not match. Open Razorpay Dashboard → Account & Settings → API Keys, copy a fresh Key Id + Key Secret pair into pv_setting (id 1 key_id/key_secret and rezorpay_test_* rows must be the same pair).',
                        'error_code' => 'razorpay_auth_failed',
                    ], 422);
                }

                return response()->json([
                    'success' => false,
                    'message' => $rawMessage !== '' ? $rawMessage : 'Unable to create Razorpay order.',
                ], 422);
            }

            $razorpayData = $response->json();
            $orderId = $razorpayData['id'] ?? null;

            if ($orderId) {
                $this->ensurePendingInvoicePaymentTransaction($invoices, (string) $orderId, $totalAmount);
            }

            $paymentRetry = $this->aggregatePaymentRetryForInvoices($invoices);

            $data = array_merge([
                'invoice_id' => $invoiceIds,
                'invoice_numbers' => $invoiceNumbers,
                'order_id' => $orderId,
                'amount' => $razorpayData['amount'] ?? $razorpayAmount,
                'currency' => 'INR',
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'customer_mobile' => $customerMobile,
                'razorpay_key' => $key,
                'payment_method' => 'upi,card,netbanking,wallet',
                'status' => 'pending',
                'expired' => (bool) ($paymentRetry['payment_link_expired'] ?? false),
                'payment_link_expired' => (bool) ($paymentRetry['payment_link_expired'] ?? false),
                'payment_link_allowed' => (bool) ($paymentRetry['payment_link_allowed'] ?? true),
                'failed_payment_attempts' => (int) ($paymentRetry['failed_payment_attempts'] ?? 0),
                'remaining_attempts' => (int) ($paymentRetry['remaining_attempts'] ?? 0),
                'remaining_hrs' => (int) ($paymentRetry['remaining_hrs'] ?? 0),
                'payment_link_expires_at' => $paymentRetry['payment_link_expires_at'] ?? null,
                'payment_link_message' => $paymentRetry['payment_link_message'] ?? null,
                'payment_retry' => $paymentRetry,
            ]);

            return response()->json([
                'success' => true,
                'data' => $data,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch invoice payment details: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function respondInvoicePaymentFailure(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'invoice_id' => ['required', 'array', 'min:1'],
            'invoice_id.*' => ['integer', 'min:1'],
            'razorpay_order_id' => ['nullable', 'string'],
            'error' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $invoiceIds = array_values(array_unique(array_filter(
            array_map('intval', (array) $request->input('invoice_id', [])),
            fn($id) => $id > 0
        )));

        if ($invoiceIds === []) {
            return response()->json([
                'success' => false,
                'payment_status' => 'failed',
                'message' => 'Missing or invalid invoice ID(s)',
            ], 400);
        }

        $razorpayOrderId = trim((string) $request->input('razorpay_order_id', ''));
        $errorMessage = trim((string) (
            $request->input('error')
            ?? $request->input('message')
            ?? $request->input('failure_reason')
            ?? 'Payment failed'
        ));
        if ($errorMessage === '') {
            $errorMessage = 'Payment failed';
        }

        try {
            $invoices = $this->invoiceMst->newQuery()->whereIn('id', $invoiceIds)->get();
            if ($invoices->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'payment_status' => 'failed',
                    'message' => 'Invoice(s) not found',
                ], 404);
            }

            // Mark matching / pending gateway txns failed and count partner attempt once.
            $this->markInvoicePaymentTransactionsFailed($invoices, $razorpayOrderId, $errorMessage);
            $paymentRetry = $this->recordInvoicePaymentFailures($invoices, $errorMessage);
        } catch (\Throwable $e) {
            Log::error('invoice_payment_failure_record_error', [
                'invoice_id' => $invoiceIds,
                'razorpay_order_id' => $razorpayOrderId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'payment_status' => 'failed',
                'message' => 'Unable to record payment failure',
            ], 500);
        }

        Log::info('invoice_payment_failure_recorded', [
            'invoice_id' => $invoiceIds,
            'razorpay_order_id' => $razorpayOrderId,
            'failed_payment_attempts' => (int) ($paymentRetry['failed_payment_attempts'] ?? 0),
            'remaining_attempts' => (int) ($paymentRetry['remaining_attempts'] ?? 0),
        ]);

        return response()->json([
            'success' => true,
            'status' => false,
            'payment_status' => 'failed',
            'expired' => (bool) ($paymentRetry['payment_link_expired'] ?? false),
            'message' => $errorMessage,
            'failed_payment_attempts' => (int) ($paymentRetry['failed_payment_attempts'] ?? 0),
            'remaining_attempts' => (int) ($paymentRetry['remaining_attempts'] ?? 0),
            'remaining_hrs' => (int) ($paymentRetry['remaining_hrs'] ?? 0),
            'payment_link_expires_at' => $paymentRetry['payment_link_expires_at'] ?? null,
            'payment_link_message' => $paymentRetry['payment_link_message'] ?? null,
            'data' => [
                'invoice_id' => $invoiceIds,
                'razorpay_order_id' => $razorpayOrderId !== '' ? $razorpayOrderId : null,
                'payment_retry' => $paymentRetry,
            ],
        ], 200);
    }

    /**
     * Existing partner API: record failure via payment_status=failed (no extra endpoint).
     */
    public function verifyPayment(Request $request): JsonResponse
    {
        $this->normalizeInvoicePaymentVerifyPayload($request);

        if ($this->shouldRecordInvoicePaymentFailure($request)) {
            return $this->respondInvoicePaymentFailure($request);
        }

        if (
            !$request->filled('razorpay_payment_id')
            || !$request->filled('razorpay_signature')
        ) {
            $orderId = trim((string) $request->input('razorpay_order_id', ''));

            $invoiceIds = array_values(array_filter(
                array_map('intval', (array) $request->input('invoice_id', [])),
                fn($id) => $id > 0
            ));
            $invoices = $invoiceIds === []
                ? collect()
                : $this->invoiceMst->newQuery()->whereIn('id', $invoiceIds)->get();

            // Actively record the failure/abandonment so attempts decrement.
            $paymentRetry = $this->recordInvoicePaymentFailures($invoices, 'Payment abandoned or incomplete.');

            return response()->json([
                'success' => true,
                'payment_status' => 'cancelled',
                'message' => 'Payment was not completed.',
                'failed_payment_attempts' => (int) ($paymentRetry['failed_payment_attempts'] ?? 0),
                'remaining_attempts' => (int) ($paymentRetry['remaining_attempts'] ?? Order::PAYMENT_LINK_MAX_ATTEMPTS),
                'remaining_hrs' => (int) ($paymentRetry['remaining_hrs'] ?? 0),
                'payment_link_expires_at' => $paymentRetry['payment_link_expires_at'] ?? null,
                'payment_link_message' => $paymentRetry['payment_link_message'] ?? null,
                'data' => [
                    'invoice_id' => $invoiceIds,
                    'razorpay_order_id' => $orderId !== '' ? $orderId : null,
                    'payment_retry' => $paymentRetry,
                ],
            ], 200);
        }

        $request->validate([
            'razorpay_order_id' => 'required',
            'razorpay_payment_id' => 'required',
            'razorpay_signature' => 'required',
            'invoice_id' => 'nullable|array|min:1', // Accept invoice_id as array
            'invoice_id.*' => 'integer|min:1',
        ]);

        [, $secret] = PvSetting::razorpayCredentials();

        if (!$secret) {
            return response()->json([
                'success' => false,
                'message' => 'Payment gateway secret is missing.'
            ], 500);
        }

        $razorpayOrderId = trim((string) $request->razorpay_order_id);
        $razorpayPaymentId = trim((string) $request->razorpay_payment_id);
        $razorpaySignature = trim((string) $request->razorpay_signature);
        $invoiceIds = (array) $request->input('invoice_id', []);
        $invoiceIds = array_filter(array_map('intval', $invoiceIds), function ($id) {
            return $id > 0;
        });

        if (empty($invoiceIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Missing or invalid invoice ID(s)'
            ], 400);
        }

        $generatedSignature = hash_hmac(
            'sha256',
            $razorpayOrderId . '|' . $razorpayPaymentId,
            $secret
        );

        if (!hash_equals($generatedSignature, $razorpaySignature)) {
            $invoices = $this->invoiceMst->newQuery()->whereIn('id', $invoiceIds)->get();
            $paymentRetry = $this->recordInvoicePaymentFailures($invoices, 'Invalid payment signature');

            return response()->json([
                'success' => false,
                'message' => 'Invalid signature',
                'expired' => (bool) ($paymentRetry['payment_link_expired'] ?? false),
                'failed_payment_attempts' => (int) ($paymentRetry['failed_payment_attempts'] ?? 0),
                'remaining_attempts' => (int) ($paymentRetry['remaining_attempts'] ?? 0),
                'remaining_hrs' => (int) ($paymentRetry['remaining_hrs'] ?? 0),
                'payment_link_expires_at' => $paymentRetry['payment_link_expires_at'] ?? null,
                'payment_link_message' => $paymentRetry['payment_link_message'] ?? null,
                'payment_retry' => $paymentRetry,
            ], 400);
        }

        $notifyOrderIds = [];
        $primaryInvoiceId = 0;
        $primaryOrderId = 0;
        $transactionId = 0;
        $alreadyProcessed = false;

        try {
            DB::transaction(function () use (
                $razorpayOrderId,
                $razorpayPaymentId,
                $invoiceIds,
                &$notifyOrderIds,
                &$primaryInvoiceId,
                &$primaryOrderId,
                &$transactionId,
                &$alreadyProcessed
            ) {
                // Find parent transaction by gateway_order_id, or create if not found & valid invoice(s)
                $parent = InvoicePaymentTransaction::query()
                    ->where('gateway_order_id', $razorpayOrderId)
                    ->first();

                if (!$parent && count($invoiceIds) > 0) {
                    $validInvoices = $this->invoiceMst->newQuery()->whereIn('id', $invoiceIds)->get();
                    $validInvoiceIds = $validInvoices->pluck('id')->map(fn($id) => (int) $id)->all();

                    if (empty($validInvoiceIds)) {
                        throw new \RuntimeException('invoice_not_found');
                    }

                    // If all invoice statuses are already successful, treat as already processed and return
                    $allSuccess = true;
                    foreach ($validInvoices as $invoice) {
                        if (InvoiceStatusNormalizer::tab((string) ($invoice->status ?? '')) !== 'success') {
                            $allSuccess = false;
                            break;
                        }
                    }

                    if ($allSuccess) {
                        $alreadyProcessed = true;
                        $primaryInvoiceId = (int) ($validInvoices->first()->id);
                        $primaryOrderId = (int) ($validInvoices->first()->order_id ?? 0);
                        return;
                    }

                    // Calculate total amount for all invoices
                    $totalAmount = round($validInvoices->sum(fn($invoice) => $this->resolveInvoicePayableAmount($invoice)), 2);

                    // For parent transaction, use partner_id of first valid invoice
                    $parent = InvoicePaymentTransaction::query()->create([
                        'partner_id' => (int) ($validInvoices->first()->partner_id ?? 0),
                        'status' => 'pending',
                        'total_amount' => $totalAmount,
                        'currency' => 'INR',
                        'gateway' => 'razorpay',
                        'gateway_order_id' => $razorpayOrderId,
                        'gateway_payment_id' => null,
                        'meta' => ['invoice_ids' => $validInvoiceIds, 'source' => 'verify_payment'],
                    ]);

                    foreach ($validInvoices as $invoice) {
                        $lineAmount = $this->resolveInvoicePayableAmount($invoice);
                        InvoicePaymentTransactionItem::query()->create([
                            'invoice_payment_transaction_id' => $parent->id,
                            'invoice_id' => (int) $invoice->id,
                            'amount' => $lineAmount,
                        ]);
                    }
                }

                if (!$parent) {
                    throw new \RuntimeException('transaction_not_found');
                }

                $transactionId = (int) $parent->id;

                if ($parent->status === 'success') {
                    $alreadyProcessed = true;
                    return;
                }

                // Ensure all provided invoice_ids are linked in the transaction's items
                foreach ($invoiceIds as $id) {
                    $invoice = $this->invoiceMst->newQuery()->where('id', $id)->first();
                    if (!$invoice) {
                        continue;
                    }

                    // Set first invoice as primary if not already
                    if ($primaryInvoiceId <= 0) {
                        $primaryInvoiceId = (int) $invoice->id;
                        $primaryOrderId = (int) ($invoice->order_id ?? 0);
                    }

                    $lineAmount = $this->resolveInvoicePayableAmount($invoice);

                    $existingItem = InvoicePaymentTransactionItem::query()
                        ->where('invoice_payment_transaction_id', $parent->id)
                        ->where('invoice_id', $invoice->id)
                        ->first();
                    if (!$existingItem) {
                        InvoicePaymentTransactionItem::query()->create([
                            'invoice_payment_transaction_id' => $parent->id,
                            'invoice_id' => (int) $invoice->id,
                            'amount' => $lineAmount,
                        ]);
                    }
                }

                $parent->gateway_order_id = $razorpayOrderId;
                $parent->gateway_payment_id = $razorpayPaymentId;
                $parent->status = 'success';
                $parent->save();

                $items = InvoicePaymentTransactionItem::query()
                    ->where('invoice_payment_transaction_id', $parent->id)
                    ->get();

                $prefix = 'TZG';
                foreach ($items as $item) {
                    $inv = $this->invoiceMst->newQuery()->where('id', $item->invoice_id)->first();
                    if (!$inv) {
                        continue;
                    }

                    $invoicePartnerId = (int) ($inv->partner_id ?? 0);
                    $parentPartnerId = (int) $parent->partner_id;
                    if (
                        $parentPartnerId > 0
                        && $invoicePartnerId > 0
                        && $invoicePartnerId !== $parentPartnerId
                    ) {
                        continue;
                    }

                    $tzg = $prefix . '-' . str_pad((string) $inv->id, 5, '0', STR_PAD_LEFT);
                    $update = [
                        'status' => 'paid',
                        'updated_at' => now(),
                    ];
                    if (Schema::hasColumn('invoice_mst', 'tzg_invoice_no')) {
                        $update['tzg_invoice_no'] = $tzg;
                    }
                    $this->invoiceMst->newQuery()->where('id', $inv->id)->update($update);
                }

                foreach ($invoiceIds as $invoiceId) {
                    $inv = $this->invoiceMst->newQuery()->where('id', $invoiceId)->first();
                    if (!$inv || strtolower(trim((string) ($inv->status ?? ''))) === 'paid') {
                        continue;
                    }

                    $tzg = 'TZG-' . str_pad((string) $inv->id, 5, '0', STR_PAD_LEFT);
                    $update = [
                        'status' => 'paid',
                        'updated_at' => now(),
                    ];
                    if (Schema::hasColumn('invoice_mst', 'tzg_invoice_no')) {
                        $update['tzg_invoice_no'] = $tzg;
                    }
                    $this->invoiceMst->newQuery()->where('id', $invoiceId)->update($update);
                }

                foreach ($items as $item) {
                    $invoice = $this->invoiceMst->newQuery()->where('id', $item->invoice_id)->first();
                    if (!$invoice) {
                        continue;
                    }

                    if ($primaryInvoiceId <= 0) {
                        $primaryInvoiceId = (int) $invoice->id;
                    }

                    $orderId = (int) ($invoice->order_id ?? 0);
                    if ($orderId <= 0) {
                        continue;
                    }

                    $order = Order::query()->find($orderId);
                    if (!$order) {
                        continue;
                    }

                    if ($primaryOrderId <= 0) {
                        $primaryOrderId = $orderId;
                    }

                    $previousStatus = strtolower(trim((string) ($order->status ?? '')));
                    $wasPaid = in_array($previousStatus, ['paid', 'completed', 'success'], true);

                    if (!$wasPaid) {
                        $order->status = 'paid';
                        $order->razorpay_payment_id = $razorpayPaymentId;
                        $order->razorpay_order_id = $razorpayOrderId;
                        $order->save();
                    }

                    // Paying the invoice restores credit allocation to the partner.
                    PartnerCreditService::releaseCreditForPaidInvoice(
                        (int) $invoice->id,
                        $orderId
                    );

                    // Always queue partner email/SMS/WhatsApp after a successful invoice payment.
                    $notifyOrderIds[] = $orderId;
                }

                $meta = is_array($parent->meta) ? $parent->meta : [];
                $meta['payment_activity'] = [
                    'invoice_id' => $primaryInvoiceId,
                    'order_id' => $primaryOrderId,
                    'transaction_id' => (int) $parent->id,
                    'razorpay_payment_id' => $razorpayPaymentId,
                    'razorpay_order_id' => $razorpayOrderId,
                    'timestamp' => now()->toDateTimeString(),
                ];
                $parent->meta = $meta;
                $parent->save();
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'invoice_not_found') {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found',
                ], 404);
            }

            if ($e->getMessage() === 'transaction_not_found') {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found',
                ], 404);
            }

            throw $e;
        } catch (\Throwable $e) {
            Log::error('invoice_payment_verify_failed', [
                'razorpay_order_id' => $razorpayOrderId,
                'razorpay_payment_id' => $razorpayPaymentId,
                'invoice_id' => json_encode($invoiceIds),
                'error' => $e->getMessage() . ' - ' . $e->getFile() . ' - ' . $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to verify payment - ' . $e->getMessage() . ' - ' . $e->getFile() . ' - ' . $e->getLine(),
            ], 500);
        }

        Log::info('invoice_payment_verified', [
            'invoice_id' => $primaryInvoiceId,
            'order_id' => $primaryOrderId,
            'transaction_id' => $transactionId,
            'razorpay_payment_id' => $razorpayPaymentId,
            'timestamp' => now()->toDateTimeString(),
        ]);

        if (!$alreadyProcessed) {
            $billing = app(BillingController::class);
            $notifyOrderIds = array_values(array_unique(array_filter($notifyOrderIds)));

            // Fallback: still email partner for the primary paid order.
            if ($notifyOrderIds === [] && $primaryOrderId > 0) {
                $notifyOrderIds[] = $primaryOrderId;
            }

            foreach ($notifyOrderIds as $orderId) {
                try {
                    $billing->persistOrderInvoicePdfToStorage($orderId);

                    $orderRow = $this->order->newQuery()->where('id', $orderId)->first();
                    if (!$orderRow) {
                        Log::warning('invoice_payment: partner email skipped — order not found', [
                            'order_id' => $orderId,
                        ]);
                        continue;
                    }

                    $partnerId = (int) ($orderRow->partner_id ?? 0);
                    $category = strtolower(trim((string) ($orderRow->order_category ?? 'new')));

                    // Separate invoice-payment success email (not order-placed mail).
                    $emailSent = false;
                    try {
                        $emailSent = (bool) $billing->sendInvoicePaymentEmailForOrder(
                            $orderId,
                            $razorpayPaymentId
                        );
                    } catch (\Throwable $e) {
                        Log::warning('invoice_payment: partner email failed', [
                            'order_id' => $orderId,
                            'partner_id' => $partnerId,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    Log::info('invoice_payment: partner email result', [
                        'order_id' => $orderId,
                        'partner_id' => $partnerId,
                        'category' => $category,
                        'email_sent' => $emailSent,
                    ]);

                    if (in_array($category, ['renewal', 'renew'], true)) {
                        $billing->sendPlanRenewedNotifications($orderId, $orderRow, true);
                        continue;
                    }

                    if ($partnerId <= 0) {
                        continue;
                    }

                    $mobile = trim((string) ($this->partner->newQuery()->where('id', $partnerId)->value('mobile') ?? ''));
                    if ($mobile === '') {
                        continue;
                    }

                    $amount = (float) ($orderRow->total_amount ?? $orderRow->subtotal ?? $orderRow->price ?? 0);
                    $orderNo = (string) ($orderRow->order_no ?? $orderId);
                    $details = 'invoice payment for order ' . $orderNo;

                    app(SmsService::class)->sendPaymentConfirmation($mobile, $amount, $orderNo);

                    $prefs = NotificationPreference::forPartner($partnerId);
                    if ($prefs && $prefs->isEnabled('subscription', 'whatsapp') && class_exists(WhatsAppService::class)) {
                        try {
                            $invoiceUrl = '';
                            $docs = $billing->getOrderDocumentLinks($orderId);
                            $invoiceUrl = trim((string) ($docs['invoice_link'] ?? ''));
                            if ($invoiceUrl === '') {
                                $stored = $billing->persistOrderInvoicePdfToStorage($orderId);
                                $invoiceUrl = trim((string) ($stored['public_url'] ?? ''));
                            }

                            $waOk = app(WhatsAppService::class)->sendPaymentConfirmation(
                                $mobile,
                                $amount,
                                $details,
                                $orderNo,
                                $invoiceUrl
                            );
                            Log::info('invoice_payment: partner WhatsApp payment_confirmation result', [
                                'order_id' => $orderId,
                                'partner_id' => $partnerId,
                                'success' => $waOk,
                                'has_invoice_attachment' => $invoiceUrl !== '',
                            ]);
                        } catch (\Throwable $e) {
                            Log::warning('invoice_payment: partner WhatsApp failed', [
                                'order_id' => $orderId,
                                'partner_id' => $partnerId,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('invoice_payment_post_notify_failed', [
                        'order_id' => $orderId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'status' => true,
            'payment_status' => 'paid',
            'message' => $alreadyProcessed ? 'Payment already verified' : 'Payment successful',
            'data' => [
                'invoice_id' => $primaryInvoiceId,
                'order_id' => $primaryOrderId,
                'transaction_id' => $transactionId,
                'razorpay_payment_id' => $razorpayPaymentId,
            ],
        ]);
    }

    private function resolveInvoicePayableAmount(object $invoice): float
    {
        $net = round((float) ($invoice->net_amt ?? 0), 2);
        if ($net > 0) {
            return $net;
        }

        $taxable = round((float) ($invoice->taxable_total ?? 0), 2);
        $tax = round((float) ($invoice->cgst_total ?? 0), 2)
            + round((float) ($invoice->sgst_total ?? 0), 2)
            + round((float) ($invoice->igst_total ?? 0), 2);
        $fromTaxParts = round($taxable + $tax, 2);
        if ($fromTaxParts > 0) {
            return $fromTaxParts;
        }

        foreach (['total_amount', 'amount'] as $field) {
            $value = round((float) ($invoice->{$field} ?? 0), 2);
            if ($value > 0) {
                return $value;
            }
        }

        $invoiceId = (int) ($invoice->id ?? 0);
        if ($invoiceId > 0) {
            $lineTotal = round((float) $this->invoiceSub->newQuery()
                ->where('invoice_mst_id', $invoiceId)
                ->sum('nettotal'), 2);
            if ($lineTotal > 0) {
                return $lineTotal;
            }
        }

        $orderId = (int) ($invoice->order_id ?? 0);
        if ($orderId > 0) {
            $order = $this->order->newQuery()->whereKey($orderId)->first();
            if ($order) {
                foreach (['total_amount', 'subtotal', 'price'] as $field) {
                    $value = round((float) ($order->{$field} ?? 0), 2);
                    if ($value > 0) {
                        return $value;
                    }
                }
            }
        }

        return 0.0;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>|array<int, object>  $invoices
     */
    private function ordersForInvoices($invoices): \Illuminate\Support\Collection
    {
        $orderIds = collect($invoices)
            ->pluck('order_id')
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($orderIds === []) {
            return collect();
        }

        return Order::query()->whereIn('id', $orderIds)->get()->keyBy('id');
    }

    /**
     * Orders linked to unpaid invoices that still need an online payment link.
     *
     * @param  \Illuminate\Support\Collection<int, object>|array<int, object>  $invoices
     */
    private function payableOrdersForInvoices($invoices): \Illuminate\Support\Collection
    {
        $orders = $this->ordersForInvoices($invoices);

        return $orders->filter(function (Order $order) use ($invoices) {
            if ($order->isPaymentLinkPaid()) {
                return false;
            }

            $orderId = (int) $order->id;
            foreach ($invoices as $invoice) {
                if ((int) ($invoice->order_id ?? 0) !== $orderId) {
                    continue;
                }
                if (strtolower(trim((string) ($invoice->status ?? ''))) !== 'paid') {
                    return true;
                }
            }

            return false;
        })->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>|array<int, object>  $invoices
     */
    private function assertInvoicePaymentLinkAllowed($invoices): ?JsonResponse
    {
        $orders = $this->payableOrdersForInvoices($invoices);
        if ($orders->isEmpty()) {
            return null;
        }

        foreach ($orders as $order) {
            $retry = $order->paymentLinkRetryForApi();
            if ($retry['payment_link_expired']) {
                return response()->json([
                    'success' => false,
                    'expired' => true,
                    'message' => $retry['payment_link_message'] ?? 'Payment link has expired.',
                    'failed_payment_attempts' => (int) ($retry['failed_payment_attempts'] ?? 0),
                    'remaining_attempts' => (int) ($retry['remaining_attempts'] ?? 0),
                    'remaining_hrs' => (int) ($retry['remaining_hrs'] ?? 0),
                    'payment_link_expires_at' => $retry['payment_link_expires_at'],
                    'payment_retry' => $retry,
                ], 410);
            }
        }

        return null;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>|array<int, object>  $invoices
     * @return array{
     *     payment_link_expired: bool,
     *     payment_link_allowed: bool,
     *     failed_payment_attempts: int,
     *     payment_link_expires_at: ?string,
     *     payment_link_message: ?string
     * }
     */
    private function aggregatePaymentRetryForInvoices($invoices): array
    {
        $orders = $this->ordersForInvoices($invoices);

        if ($orders->isEmpty()) {
            return [
                'payment_link_expired' => false,
                'payment_link_allowed' => true,
                'failed_payment_attempts' => 0,
                'remaining_attempts' => Order::PAYMENT_LINK_MAX_ATTEMPTS,
                'remaining_hrs' => 0,
                'payment_link_expires_at' => null,
                'payment_link_message' => null,
            ];
        }

        $retries = $orders->map(fn(Order $order) => $order->paymentLinkRetryForApi())->values();
        $expired = $retries->contains(fn(array $retry) => (bool) ($retry['payment_link_expired'] ?? false));

        return [
            'payment_link_expired' => $expired,
            'payment_link_allowed' => !$expired && $retries->contains(fn(array $retry) => (bool) ($retry['payment_link_allowed'] ?? false)),
            'failed_payment_attempts' => (int) $retries->max('failed_payment_attempts'),
            'remaining_attempts' => $expired
                ? (int) ($retries->min('remaining_attempts') ?? Order::PAYMENT_LINK_MAX_ATTEMPTS)
                : (int) $retries->min('remaining_attempts'),
            'remaining_hrs' => (int) $retries->min('remaining_hrs'),
            'payment_link_expires_at' => $retries->pluck('payment_link_expires_at')->filter()->sort()->first(),
            'payment_link_message' => $expired
                ? ($retries->firstWhere('payment_link_expired', true)['payment_link_message'] ?? null)
                : ($retries->first()['payment_link_message'] ?? null),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>|array<int, object>  $invoices
     * @return array{
     *     payment_link_expired: bool,
     *     payment_link_allowed: bool,
     *     failed_payment_attempts: int,
     *     payment_link_expires_at: ?string,
     *     payment_link_message: ?string
     * }
     */
    private function recordInvoicePaymentFailures($invoices, string $reason): array
    {
        $orders = $this->payableOrdersForInvoices($invoices);

        if ($orders->isEmpty()) {
            Log::warning('invoice_payment_failure_no_payable_orders', [
                'invoice_ids' => collect($invoices)->pluck('id')->values()->all(),
                'reason' => $reason,
            ]);
        }

        $partnerId = 0;
        foreach ($orders as $order) {
            if ($order->partner_id > 0) {
                $partnerId = (int) $order->partner_id;
                break;
            }
        }

        if ($partnerId > 0) {
            $allPartnerOrders = Order::where('partner_id', $partnerId)
                ->whereNull('razorpay_payment_id')
                ->get();

            foreach ($allPartnerOrders as $o) {
                if (!$o->isPaymentLinkPaid()) {
                    $o->recordPaymentLinkFailure($reason);
                }
            }
        } else {
            foreach ($orders as $order) {
                $order->recordPaymentLinkFailure($reason);
            }
        }

        $invoiceIds = collect($invoices)->pluck('id')->map(fn($id) => (int) $id)->filter()->values()->all();
        $freshInvoices = $invoiceIds === []
            ? collect($invoices)
            : $this->invoiceMst->newQuery()->whereIn('id', $invoiceIds)->get();

        return $this->aggregatePaymentRetryForInvoices($freshInvoices);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>|array<int, object>  $invoices
     * @param  \Illuminate\Support\Collection<int, Order>|null  $orders
     */
    private function resolvePartnerIdForInvoices($invoices, $orders = null): int
    {
        if ($orders) {
            foreach ($orders as $order) {
                $partnerId = (int) ($order->partner_id ?? 0);
                if ($partnerId > 0) {
                    return $partnerId;
                }
            }
        }

        foreach ($invoices as $invoice) {
            $partnerId = (int) ($invoice->partner_id ?? 0);
            if ($partnerId > 0) {
                return $partnerId;
            }
        }

        $orderIds = collect($invoices)
            ->pluck('order_id')
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($orderIds === []) {
            return 0;
        }

        return (int) (Order::query()->whereIn('id', $orderIds)->whereNotNull('partner_id')->value('partner_id') ?? 0);
    }

    /**
     * Close leftover pending Razorpay orders when the partner reopens Pay.
     * Reopening must not consume a partner payment attempt — only real failures do.
     *
     * @param  \Illuminate\Support\Collection<int, object>|array<int, object>  $invoices
     */
    private function recordAbandonedInvoicePaymentAttempts($invoices, string $reason = 'Payment not completed'): bool
    {
        $invoiceIds = collect($invoices)->pluck('id')->map(fn($id) => (int) $id)->filter()->values()->all();
        if ($invoiceIds === []) {
            return false;
        }

        $pendingTxnIds = InvoicePaymentTransactionItem::query()
            ->whereIn('invoice_id', $invoiceIds)
            ->pluck('invoice_payment_transaction_id')
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($pendingTxnIds === []) {
            return false;
        }

        $pendingTxns = InvoicePaymentTransaction::query()
            ->whereIn('id', $pendingTxnIds)
            ->where('status', 'pending')
            ->get();

        if ($pendingTxns->isEmpty()) {
            return false;
        }

        return true;
    }

    /**
     * Mark pending (or matching gateway) payment txns as failed and stamp attempt_counted.
     *
     * @param  \Illuminate\Support\Collection<int, object>|array<int, object>  $invoices
     */
    private function markInvoicePaymentTransactionsFailed($invoices, string $razorpayOrderId, string $reason): void
    {
        $invoiceIds = collect($invoices)->pluck('id')->map(fn($id) => (int) $id)->filter()->values()->all();
        $txnIds = [];

        if ($razorpayOrderId !== '') {
            $byGateway = InvoicePaymentTransaction::query()
                ->where('gateway_order_id', $razorpayOrderId)
                ->value('id');
            if ($byGateway) {
                $txnIds[] = (int) $byGateway;
            }
        }

        if ($invoiceIds !== []) {
            $linked = InvoicePaymentTransactionItem::query()
                ->whereIn('invoice_id', $invoiceIds)
                ->pluck('invoice_payment_transaction_id')
                ->map(fn($id) => (int) $id)
                ->all();
            $txnIds = array_merge($txnIds, $linked);
        }

        $txnIds = array_values(array_unique(array_filter($txnIds)));
        if ($txnIds === []) {
            return;
        }

        $txns = InvoicePaymentTransaction::query()
            ->whereIn('id', $txnIds)
            ->whereIn('status', ['pending', 'failed'])
            ->get();

        foreach ($txns as $txn) {
            if ((string) ($txn->status ?? '') === 'success') {
                continue;
            }

            $meta = is_array($txn->meta) ? $txn->meta : [];
            $txn->status = 'failed';
            $meta['payment_failure'] = [
                'error' => $reason,
                'razorpay_order_id' => $razorpayOrderId !== '' ? $razorpayOrderId : (string) ($txn->gateway_order_id ?? ''),
                'invoice_ids' => $invoiceIds,
                'timestamp' => now()->toDateTimeString(),
            ];
            // Stamp so reopen abandoned path does not count this session twice.
            $meta['attempt_counted'] = true;
            $txn->meta = $meta;
            $txn->save();
        }
    }

    private function normalizeInvoicePaymentVerifyPayload(Request $request): void
    {
        $invoiceId = $request->input('invoice_id', $request->input('invoice_ids'));
        if ($invoiceId !== null && !is_array($invoiceId)) {
            $request->merge(['invoice_id' => [(int) $invoiceId]]);
        } elseif (is_array($invoiceId)) {
            $request->merge([
                'invoice_id' => array_values(array_filter(array_map('intval', $invoiceId), fn($id) => $id > 0)),
            ]);
        }

        if (!$request->filled('razorpay_order_id')) {
            $orderId = $request->input('order_id')
                ?? $request->input('razorpayOrderId')
                ?? data_get($request->input('error'), 'metadata.order_id');
            if (is_string($orderId) && trim($orderId) !== '') {
                $request->merge(['razorpay_order_id' => trim($orderId)]);
            }
        }

        $error = $request->input('error');
        if (is_array($error)) {
            $desc = trim((string) ($error['description'] ?? $error['reason'] ?? $error['code'] ?? ''));
            if ($desc !== '') {
                $request->merge([
                    'error' => $desc,
                    'error_code' => (string) ($error['code'] ?? $request->input('error_code', '')),
                ]);
            }
        }
    }

    private function shouldRecordInvoicePaymentFailure(Request $request): bool
    {
        if (
            $request->boolean('record_failure')
            || $request->boolean('payment_failed')
            || $request->boolean('failed')
            || $request->filled('error')
            || $request->filled('error_code')
        ) {
            return true;
        }

        // Successful verify always has payment id + signature.
        if ($request->filled('razorpay_payment_id') && $request->filled('razorpay_signature')) {
            return false;
        }

        $status = strtolower(trim((string) (
            $request->input('payment_status')
            ?? $request->input('status')
            ?? $request->input('razorpay_status')
            ?? ''
        )));
        if (in_array($status, [
            'failed',
            'failure',
            'fail',
            'error',
            'cancelled',
            'canceled',
            'dismissed',
            'modal_closed',
            'user_cancelled',
            'user_canceled',
            'aborted',
        ], true)) {
            return true;
        }

        $reasonCode = strtolower(trim((string) (
            $request->input('error_code')
            ?? $request->input('code')
            ?? data_get($request->input('error'), 'code', '')
            ?? ''
        )));
        if ($reasonCode !== '' && (str_contains($reasonCode, 'fail') || str_contains($reasonCode, 'cancel'))) {
            return true;
        }

        return false;
    }

    private function ensurePendingInvoicePaymentTransaction($invoices, string $gatewayOrderId, float $totalAmount): void
    {
        if ($gatewayOrderId === '') {
            return;
        }

        $existing = InvoicePaymentTransaction::query()
            ->where('gateway_order_id', $gatewayOrderId)
            ->first();

        if ($existing) {
            return;
        }

        $invoiceIds = collect($invoices)->pluck('id')->map(fn($id) => (int) $id)->filter()->values()->all();
        if ($invoiceIds === []) {
            return;
        }

        $partnerId = 0;
        $firstInvoice = collect($invoices)->first();
        if ($firstInvoice) {
            $partnerId = (int) ($firstInvoice->partner_id ?? 0);
            if ($partnerId <= 0 && !empty($firstInvoice->order_id)) {
                $partnerId = (int) (Order::query()->whereKey((int) $firstInvoice->order_id)->value('partner_id') ?? 0);
            }
        }

        $parent = InvoicePaymentTransaction::query()->create([
            'partner_id' => $partnerId,
            'status' => 'pending',
            'total_amount' => round($totalAmount, 2),
            'currency' => 'INR',
            'gateway' => 'razorpay',
            'gateway_order_id' => $gatewayOrderId,
            'gateway_payment_id' => null,
            'meta' => ['invoice_ids' => $invoiceIds, 'source' => 'payment_details'],
        ]);

        foreach ($invoices as $invoice) {
            InvoicePaymentTransactionItem::query()->create([
                'invoice_payment_transaction_id' => $parent->id,
                'invoice_id' => (int) $invoice->id,
                'amount' => $this->resolveInvoicePayableAmount($invoice),
            ]);
        }
    }
}
