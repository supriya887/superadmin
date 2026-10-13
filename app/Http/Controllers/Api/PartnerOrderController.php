<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SuperAdmin\BillingController;
use App\Models\Cart;
use App\Models\CreditRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Partner;
use App\Models\PartnerAadhaarVerification;
use App\Models\Plan;
use App\Services\B2bSubscriptionRenewalService;
use App\Services\PartnerAadhaarVerificationService;
use App\Services\PartnerCartOrderPlacementService;
use App\Services\PartnerCartService;
use App\Support\AadhaarVerificationGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PartnerOrderController extends Controller
{
    /**
     * Create order(s) from cart after Aadhaar OTP verification (mobile create-order).
     */
    public function orderStore(Request $request): JsonResponse
    {
        return $this->placeOrdersFromMainCart($request);
    }

    /**
     * Legacy: checkout latest active cart for partner (no OTP — pending Aadhaar).
     */
    public function addOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'partner_id' => ['required', 'integer', 'exists:pgsql_auth.partners,id'],
            'main_cart_id' => ['nullable', 'integer', 'exists:carts,id'],
        ]);

        $partnerId = (int) $validated['partner_id'];
        $cartService = app(PartnerCartService::class);

        if (!Schema::hasTable('carts') || !Schema::hasTable('cart_items')) {
            return $this->fail('Cart tables are not available.', 422);
        }

        $parentCart = null;
        if (!empty($validated['main_cart_id'])) {
            $parentCart = Cart::query()
                ->with(['items.plan', 'customer', 'partner'])
                ->where('id', (int) $validated['main_cart_id'])
                ->where('partner_id', $partnerId)
                ->first();
        } else {
            $parentCart = $cartService->resolveCheckoutCartForPartner($partnerId);
        }

        if (!$parentCart) {
            return $this->fail('No active cart found. Add items to cart first.', 422);
        }

        $partner = Partner::query()->findOrFail($partnerId);
        $signRequired = strtolower(trim((string) ($partner->aadhaar_mandatory ?? 'no'))) === 'yes';
        if ($signRequired) {
            $cartService->markCartPendingAadhaar((int) $parentCart->id, 'new');
            if (Schema::hasColumn('carts', 'status')) {
                $parentCart->status = 'active';
                $parentCart->save();
            }

            $poLink = null;
            $poNumber = null;
            $poError = null;
            try {
                $stored = app(BillingController::class)->saveCartPoPdfDocument((int) $parentCart->id);
                if (!empty($stored['public_url'])) {
                    $poLink = (string) $stored['public_url'];
                    $poNumber = (string) ($stored['po_number'] ?? '');
                    $cartService->saveCartPoMeta((int) $parentCart->id, $poLink, $poNumber);
                } else {
                    $poError = 'PO link could not be created.';
                }
            } catch (\Throwable $e) {
                $poError = $e->getMessage();
            }

            $pendingRef = $cartService->issueCartPendingEsignRef((int) $parentCart->id, $partnerId);

            $payload = [
                'order_id' => (int) $parentCart->id,
                'order_ids' => [],
                'order_no' => $pendingRef !== '' ? $pendingRef : null,
                'ordId' => (int) $parentCart->id,
                'pending_ref_id' => $pendingRef !== '' ? $pendingRef : null,
                'temp_order_no' => $pendingRef !== '' ? $pendingRef : null,
                'is_temp_order' => true,
                'real_order' => false,
                'status' => 'pending_place',
                'cart_id' => (int) $parentCart->id,
                'aadhaar_pending' => false,
                'place_order_required' => true,
                'draft_cart' => true,
                'draft_order' => null,
                'sign_required' => 'no',
                'po_link' => $poLink,
                'po_number' => $poNumber,
                'orders' => [],
                'cart' => $cartService->formatCartWithItemsResponse($parentCart, $partner),
            ];
            if ($poError !== null && $poError !== '') {
                $payload['po_error'] = $poError;
            }

            return response()->json([
                'success' => true,
                'message' => 'Draft PO saved in cart. Call place_order=1 to place the order.',
                'data' => $payload,
            ], 200);
        }

        $placement = app(PartnerCartOrderPlacementService::class);
        $result = $placement->checkoutFromCart($parentCart, ['finalize' => false]);

        if (empty($result['ok'])) {
            return response()->json([
                'success' => false,
                'message' => (string) ($result['message'] ?? 'Order could not be created.'),
                'code' => $result['code'] ?? null,
                'data' => array_merge($result['data'] ?? ['cart_id' => (int) $parentCart->id], [
                    'cart' => $cartService->formatCartWithItemsResponse(
                        $parentCart,
                        Partner::query()->findOrFail($partnerId)
                    ),
                ]),
            ], (int) ($result['http_status'] ?? 422));
        }

        return $this->checkoutSuccessJson($result, false, true);
    }

    /**
     * Aadhaar init — three branches:
     *   order_id + renew=1   → renewal flow (no new order yet; renew order is
     *                          created at OTP verify after OTP success)
     *   main_cart_id         → cart checkout flow (multi-plan from cart_items)
     *   order_id (no renew)  → existing-order flow (BillingController PO journey)
     *
     * NOTE: `order_id` is the source subscription's order_id when renewing —
     * the renew=1 flag disambiguates between "verify Aadhaar for this existing
     * order" and "verify Aadhaar to create a renewal of this order".
     */
    public function aadhaarInit(Request $request): JsonResponse
    {
        $isResendOtp = $this->requestFlagIsTrue($request, 'resend_otp', 'resend');
        if ($isResendOtp && !$request->filled('aadhaar_number') && $request->filled('main_cart_id')) {
            $cachedCartAadhaar = app(PartnerAadhaarVerificationService::class)
                ->getLatestForCart((int) $request->input('main_cart_id'));
            if (!empty($cachedCartAadhaar['aadhaar_number'])) {
                $request->merge(['aadhaar_number' => (string) $cachedCartAadhaar['aadhaar_number']]);
            }
        }

        if ($this->shouldUseRenewalAadhaarFlow($request)) {
            return $this->initRenewalAadhaar($request);
        }

        if ($request->filled('main_cart_id')) {
            $validator = Validator::make($request->all(), [
                'main_cart_id' => ['required', 'integer', 'exists:carts,id'],
                'aadhaar_number' => ['required', 'digits:12'],
                'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            ]);
            if ($validator->fails()) {
                return $this->fail((string) $validator->errors()->first(), 422, $validator->errors()->toArray());
            }
            $validated = $validator->validated();
            $customerIdOverride = isset($validated['customer_id']) ? (int) $validated['customer_id'] : null;
            $init = app(PartnerAadhaarVerificationService::class)->initForCart(
                (string) $validated['aadhaar_number'],
                (int) $validated['main_cart_id'],
                $customerIdOverride
            );
            if (!$init['ok']) {
                return response()->json(['success' => false, 'message' => $init['message']], 422);
            }
            if (empty($init['aadhaar_verification_id'])) {
                return $this->fail(
                    'Aadhaar number was not saved. Run create_partner_aadhaar_verifications.sql and ensure cart has customer_id (or send customer_id in body).',
                    422
                );
            }

            $payload = [
                'aadhaar_verification_id' => $init['aadhaar_verification_id'] ?? null,
                'aadhaar_masked' => $init['masked'],
                'aadhaar_ref_no' => $init['ref_no'],
                'mode' => $init['mode'],
            ];
            if (($init['mode'] ?? '') === 'test') {
                $payload['test_otp'] = (string) (config('services.aadhaar.mock_otps')[0] ?? '123456');
            }

            return response()->json([
                'success' => true,
                'message' => $init['message'] ?: 'Aadhaar init successful.',
                'data' => $payload,
            ]);
        }

        return app(PartnerAadhaarVerificationService::class)
            ->initForOrder($request, app(BillingController::class));
    }

    /**
     * Aadhaar OTP verify — three branches:
     *   order_id + renew=1   → OTP verify, then create the renewal order
     *   main_cart_id         → OTP verify, then create orders from cart_items
     *   order_id (no renew)  → BillingController (existing order is just marked placed)
     */
    public function aadhaarOtpVerify(Request $request): JsonResponse
    {
        if (!$request->filled('order_id') && $request->filled('subscription_id')) {
            $request->merge(['order_id' => $request->input('subscription_id')]);
        }

        if ($this->shouldUseRenewalAadhaarFlow($request)) {
            $response = $this->placeRenewalAfterOtp($request);
            if ($response->getStatusCode() !== 201) {
                return $response;
            }

            $body = $response->getData(true);
            $data = is_array($body['data'] ?? null) ? $body['data'] : [];
            $renewOrderId = (int) ($body['order_id'] ?? $data['order_id'] ?? 0);
            $creditsUsed = 0.0;
            if ($renewOrderId > 0 && Schema::hasTable('orders') && Schema::hasColumn('orders', 'credit_used')) {
                $creditsUsed = round((float) Order::query()->where('id', $renewOrderId)->value('credit_used'), 2);
            } else {
                $creditsUsed = round((float) (
                    $body['credits_used']
                    ?? $body['credit_used']
                    ?? $body['used_credit_amount']
                    ?? $data['credits_used']
                    ?? $data['credit_used']
                    ?? 0
                ), 2);
            }
            $poLink = $body['po_link'] ?? $data['po_link'] ?? null;

            $body['credits_used'] = $creditsUsed;
            $body['credit_used'] = $creditsUsed;
            $body['used_credit_amount'] = $creditsUsed;
            $data['credits_used'] = $creditsUsed;
            $data['credit_used'] = $creditsUsed;
            if ($poLink !== null && $poLink !== '') {
                $body['po_link'] = $poLink;
                $data['po_link'] = $poLink;
            }
            $body['data'] = $data;

            return response()->json($body, 201);
        }

        // Pending B2B order from create-order: complete that order (not a new cart checkout).
        $pendingOrderId = (int) $request->input('order_id', 0);
        if ($pendingOrderId > 0 && Schema::hasTable('orders') && Schema::hasColumn('orders', 'status')) {
            $pendingOrder = Order::query()->where('id', $pendingOrderId)->first();
            if ($pendingOrder && strtolower(trim((string) ($pendingOrder->status ?? ''))) === 'pending') {
                $response = app(PartnerAadhaarVerificationService::class)
                    ->verifyForOrder($request, app(BillingController::class));
                if ($response->isSuccessful()) {
                    $payload = $response->getData(true);
                    if (!empty($payload['success'])) {
                        $fixOrderId = (int) ($payload['data']['order_id'] ?? $pendingOrderId);
                        if ($fixOrderId > 0 && Schema::hasTable('orders')) {
                            $orderColumns = Schema::getColumnListing('orders');
                            $startCol = null;
                            foreach (['subscription_start_date', 'subscription_start'] as $c) {
                                if (in_array($c, $orderColumns, true)) {
                                    $startCol = $c;
                                    break;
                                }
                            }
                            $endCol = null;
                            foreach (['subscription_end_date', 'subscription_end'] as $c) {
                                if (in_array($c, $orderColumns, true)) {
                                    $endCol = $c;
                                    break;
                                }
                            }
                            if ($startCol !== null && $endCol !== null) {
                                $this->syncOrderSubscriptionEndDates([$fixOrderId], $startCol, $endCol);
                            }
                        }
                        if ($fixOrderId > 0) {
                            $this->syncInvoiceAmountsForOrders([$fixOrderId]);
                            $multiDomainPending = $this->applyPendingOrderStatusForMultiDomainCart(
                                $this->resolveCartForOrderMultiDomainCheck($fixOrderId),
                                [$fixOrderId]
                            );
                            if ($multiDomainPending) {
                                $payload['message'] = 'Order created successfully. Pending processing for multiple domains.';
                                if (!isset($payload['data']) || !is_array($payload['data'])) {
                                    $payload['data'] = ['order_id' => $fixOrderId];
                                }
                                $payload['data']['status'] = 'pending';
                                $this->attachOrderPoAndInvoiceLinks($fixOrderId, $payload['data']);
                                if (!empty($payload['data']['po_link'])) {
                                    $payload['po_link'] = $payload['data']['po_link'];
                                }
                                if (!empty($payload['data']['po_number'])) {
                                    $payload['po_number'] = $payload['data']['po_number'];
                                }
                            }
                            if (Schema::hasColumn('orders', 'credit_used')) {
                                $creditsUsed = round((float) Order::query()->where('id', $fixOrderId)->value('credit_used'), 2);
                                $payload['credit_used'] = $creditsUsed;
                                $payload['credits_used'] = $creditsUsed;
                                $payload['used_credit_amount'] = $creditsUsed;
                                if (isset($payload['data']) && is_array($payload['data'])) {
                                    $payload['data']['credit_used'] = $creditsUsed;
                                    $payload['data']['credits_used'] = $creditsUsed;
                                }
                            }
                            if ($multiDomainPending || Schema::hasColumn('orders', 'credit_used')) {
                                $response = response()->json($payload, $response->getStatusCode());
                            }
                        }
                    }
                }

                return $response;
            }
        }

        if ($request->filled('main_cart_id')) {
            return $this->placeOrdersFromMainCart($request);
        }

        if ($request->filled('order_id')) {
            $response = app(PartnerAadhaarVerificationService::class)
                ->verifyForOrder($request, app(BillingController::class));
            if ($response->isSuccessful()) {
                $payload = $response->getData(true);
                if (!empty($payload['success'])) {
                    $fixOrderId = (int) ($payload['data']['order_id'] ?? $request->input('order_id', 0));
                    if ($fixOrderId > 0 && Schema::hasTable('orders')) {
                        $orderColumns = Schema::getColumnListing('orders');
                        $startCol = null;
                        foreach (['subscription_start_date', 'subscription_start'] as $c) {
                            if (in_array($c, $orderColumns, true)) {
                                $startCol = $c;
                                break;
                            }
                        }
                        $endCol = null;
                        foreach (['subscription_end_date', 'subscription_end'] as $c) {
                            if (in_array($c, $orderColumns, true)) {
                                $endCol = $c;
                                break;
                            }
                        }
                        if ($startCol !== null && $endCol !== null) {
                            $this->syncOrderSubscriptionEndDates([$fixOrderId], $startCol, $endCol);
                        }
                        if ($fixOrderId > 0) {
                            $this->syncInvoiceAmountsForOrders([$fixOrderId]);
                            $multiDomainPending = $this->applyPendingOrderStatusForMultiDomainCart(
                                $this->resolveCartForOrderMultiDomainCheck($fixOrderId),
                                [$fixOrderId]
                            );
                            if ($multiDomainPending) {
                                $payload['message'] = 'Order created successfully. Pending processing for multiple domains.';
                                if (!isset($payload['data']) || !is_array($payload['data'])) {
                                    $payload['data'] = ['order_id' => $fixOrderId];
                                }
                                $payload['data']['status'] = 'pending';
                                $this->attachOrderPoAndInvoiceLinks($fixOrderId, $payload['data']);
                                if (!empty($payload['data']['po_link'])) {
                                    $payload['po_link'] = $payload['data']['po_link'];
                                }
                                if (!empty($payload['data']['po_number'])) {
                                    $payload['po_number'] = $payload['data']['po_number'];
                                }
                            }
                            if (Schema::hasColumn('orders', 'credit_used')) {
                                $creditsUsed = round((float) Order::query()->where('id', $fixOrderId)->value('credit_used'), 2);
                                $payload['credit_used'] = $creditsUsed;
                                $payload['credits_used'] = $creditsUsed;
                                $payload['used_credit_amount'] = $creditsUsed;
                                if (isset($payload['data']) && is_array($payload['data'])) {
                                    $payload['data']['credit_used'] = $creditsUsed;
                                    $payload['data']['credits_used'] = $creditsUsed;
                                }
                            }
                            if ($multiDomainPending || Schema::hasColumn('orders', 'credit_used')) {
                                $response = response()->json($payload, $response->getStatusCode());
                            }
                        }
                    }
                }
            }

            return $response;
        }

        return $this->fail('order_id or main_cart_id is required.', 422);
    }

    /**
     * Is this an Aadhaar request for the renewal flow?
     * Renewal needs `order_id` (the source subscription) + an explicit `renew=1`
     * flag — otherwise plain `order_id` would collide with the existing
     * BillingController PO-journey flow that also takes order_id.
     */ 
    private function isRenewalAadhaarRequest(Request $request): bool
    {
        if (!$request->filled('order_id')) {
            return false;
        }
        if ($request->filled('main_cart_id')) {
            return false;
        }

        return $this->requestFlagIsTrue(
            $request,
            'renew',
            'is_renewal',
            'renewal',
            'renew_order',
            'is_renew',
            'renew_subscription',
        );
    }

    /**
     * Renewal Aadhaar when renew=1 (or alias) is sent, or when OTP verify follows
     * orders-aadhaar-init renewal (pending row/cache exists for source order).
     */
    private function shouldUseRenewalAadhaarFlow(Request $request): bool
    {
        if (!$request->filled('order_id') || $request->filled('main_cart_id')) {
            return false;
        }

        if ($this->isRenewalAadhaarRequest($request)) {
            return true;
        }

        return $this->hasPendingRenewalAadhaarForRequest($request);
    }

    /**
     * After renewal init, mobile may omit renew=1 on OTP verify — detect pending state.
     */
    private function hasPendingRenewalAadhaarForRequest(Request $request): bool
    {
        $rawOrderId = (int) $request->input('order_id');
        if ($rawOrderId <= 0) {
            return false;
        }

        $aadhaarService = app(PartnerAadhaarVerificationService::class);
        if ($aadhaarService->getLatestForRenewal($rawOrderId) !== null) {
            return true;
        }

        $partnerId = (int) $request->input('partner_id', 0);
        if ($partnerId <= 0) {
            $partnerId = (int) (Order::query()->where('id', $rawOrderId)->value('partner_id') ?? 0);
        }
        if ($partnerId <= 0) {
            return false;
        }

        try {
            $sourceOrderId = app(B2bSubscriptionRenewalService::class)
                ->resolveSubscriptionId($partnerId, $rawOrderId, null, null);

            return $sourceOrderId !== $rawOrderId
                && $aadhaarService->getLatestForRenewal($sourceOrderId) !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Accept renew=1, renew=true, renew=yes, etc. (filter_var alone rejects "true").
     */
    private function requestFlagIsTrue(Request $request, string ...$keys): bool
    {
        foreach ($keys as $key) {
            if (!$request->has($key)) {
                continue;
            }
            $value = $request->input($key);
            if (is_bool($value)) {
                return $value;
            }
            if (is_int($value) || is_float($value)) {
                return (int) $value !== 0;
            }
            $normalized = strtolower(trim((string) $value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off', ''], true)) {
                continue;
            }
            $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($filtered !== null) {
                return $filtered;
            }
        }

        return false;
    }

    /**
     * Aadhaar init for a renewal — no new order created yet, just Aadhaar
     * persisted + OTP dispatched. The renewal is gated by OTP verify
     * (see placeRenewalAfterOtp).
     *
     * Source order's plan/items are pulled from `order_items` (multi-plan
     * orders) so the client knows exactly what subscription is being renewed.
     */
    private function initRenewalAadhaar(Request $request): JsonResponse
    {
        if (
            $this->requestFlagIsTrue($request, 'resend_otp', 'resend')
            && !$request->filled('aadhaar_number')
            && $request->filled('order_id')
        ) {
            $cachedRenewalAadhaar = app(PartnerAadhaarVerificationService::class)
                ->getLatestForRenewal((int) $request->input('order_id'));
            if (!empty($cachedRenewalAadhaar['aadhaar_number'])) {
                $request->merge(['aadhaar_number' => (string) $cachedRenewalAadhaar['aadhaar_number']]);
            }
        }

        $validator = Validator::make($request->all(), [
            'partner_id' => ['required', 'integer', 'exists:pgsql_auth.partners,id'],
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'aadhaar_number' => ['required', 'digits:12'],
        ]);
        if ($validator->fails()) {
            return $this->fail((string) $validator->errors()->first(), 422, $validator->errors()->toArray());
        }

        $validated = $validator->validated();
        $partnerId = (int) $validated['partner_id'];
        $rawOrderId = (int) $validated['order_id'];

        $renewService = app(B2bSubscriptionRenewalService::class);
        try {
            // Walks renewal → parent if a renewal-order id was sent by mistake.
            $resolvedOrderId = $renewService->resolveSubscriptionId($partnerId, $rawOrderId, null, null);
        } catch (ValidationException $e) {
            return $this->fail(
                collect($e->errors())->flatten()->first() ?: 'Order is not eligible for renewal.',
                422,
                $e->errors()
            );
        }

        $baseOrder = Order::query()->find($resolvedOrderId);
        if (!$baseOrder) {
            return $this->fail('Source order not found.', 404);
        }
        if ((int) ($baseOrder->partner_id ?? 0) !== $partnerId) {
            return $this->fail('Order does not belong to this partner.', 422);
        }

        $customerId = (int) ($baseOrder->user_id ?? 0);
        if ($customerId <= 0) {
            return $this->fail('Customer cannot be resolved for this order.', 422);
        }

        $init = app(PartnerAadhaarVerificationService::class)->initForRenewal(
            $resolvedOrderId,
            (string) $validated['aadhaar_number'],
            $partnerId,
            $customerId
        );

        if (!$init['ok']) {
            return response()->json([
                'success' => false,
                'message' => $init['message'] ?: 'Aadhaar init failed.',
            ], 422);
        }

        $payload = [
            'aadhaar_verification_id' => $init['aadhaar_verification_id'] ?? null,
            'aadhaar_masked' => $init['masked'],
            'aadhaar_ref_no' => $init['ref_no'],
            'mode' => $init['mode'],
            'order_id' => $resolvedOrderId,
            // Source order's plan lines pulled from order_items — confirms to
            // the client which subscription is queued for renewal.
            'source_order' => $this->fetchSourceOrderDetails($resolvedOrderId),
        ];
        if (($init['mode'] ?? '') === 'test') {
            $payload['test_otp'] = (string) (config('services.aadhaar.mock_otps')[0] ?? '123456');
        }

        return response()->json([
            'success' => true,
            'message' => $init['message']
                ?: 'Aadhaar init successful. Verify OTP to complete the renewal.',
            'data' => $payload,
        ]);
    }

    /**
     * OTP verify for a renewal Aadhaar. On OTP success → ONLY THEN call
     * B2bSubscriptionRenewalService::renew() to actually create the new order,
     * deduct wallet and generate the invoice. If OTP fails, nothing is created.
     *
     * Source `order_id` is read here; downstream the renew service still uses
     * `subscription_id` semantically (same value, just a different key name).
     */
    private function placeRenewalAfterOtp(Request $request): JsonResponse
    {
        $isTestMode = app(AadhaarVerificationGateway::class)->isTestMode();

        $rules = [
            'partner_id' => ['nullable', 'integer', 'exists:pgsql_auth.partners,id'],
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'licenses' => ['nullable', 'integer', 'min:1'],
        ];
        if ($isTestMode) {
            $rules['otp'] = ['nullable', 'string', 'max:10'];
            $rules['aadhaar_number'] = ['nullable', 'digits:12'];
        } else {
            $rules['otp'] = ['required', 'digits:6'];
            $rules['aadhaar_number'] = ['required', 'digits:12'];
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->fail((string) $validator->errors()->first(), 422, $validator->errors()->toArray());
        }

        $validated = $validator->validated();
        $rawOrderId = (int) $validated['order_id'];
        $partnerId = (int) ($validated['partner_id'] ?? 0);
        if ($partnerId <= 0) {
            $partnerId = (int) (Order::query()->where('id', $rawOrderId)->value('partner_id') ?? 0);
        }
        if ($partnerId <= 0) {
            return $this->fail('partner_id is required for renewal.', 422);
        }

        $renewService = app(B2bSubscriptionRenewalService::class);
        try {
            $sourceOrderId = $renewService->resolveSubscriptionId($partnerId, $rawOrderId, null, null);
        } catch (ValidationException $e) {
            return $this->fail(
                collect($e->errors())->flatten()->first() ?: 'Order is not eligible for renewal.',
                422,
                $e->errors()
            );
        }

        $aadhaarService = app(PartnerAadhaarVerificationService::class);
        $cached = $aadhaarService->getLatestForRenewal($sourceOrderId);
        if ($cached === null) {
            return $this->fail(
                'No Aadhaar record for this renewal. Call orders-aadhaar-init with order_id + renew=1 + aadhaar_number first.',
                422,
                null,
                ['order_id' => $sourceOrderId]
            );
        }

        $aadhaarNumber = preg_replace(
            '/\D/',
            '',
            (string) ($validated['aadhaar_number'] ?? $cached['aadhaar_number'] ?? '')
        ) ?? '';
        if (strlen($aadhaarNumber) !== 12) {
            return $this->fail('Aadhaar number must be 12 digits. Call orders-aadhaar-init first.', 422);
        }

        $otp = (string) ($validated['otp'] ?? '');
        if ($otp === '' && $isTestMode) {
            $otp = (string) (config('services.aadhaar.mock_otps')[0] ?? '123456');
        }
        if (!$isTestMode && $otp === '') {
            return $this->fail('OTP is required.', 422, ['otp' => ['OTP is required.']]);
        }

        // ---- 1. Verify OTP first. If this fails we bail out BEFORE renewal. ----
        $otpResult = $aadhaarService->verifyForRenewal($sourceOrderId, $aadhaarNumber, $otp);
        if (!$otpResult['ok']) {
            return $this->fail(
                (string) ($otpResult['message'] ?: 'OTP verification failed.'),
                422,
                ['otp' => [(string) ($otpResult['message'] ?: 'OTP verification failed.')]]
            );
        }

        // ---- 2. OTP good → create the renewal order.
        //
        // Quantity priority:
        //   1. explicit `quantity` / `licenses` in body
        //   2. sum of source order_items.quantity (multi-plan order)
        //   3. orders.quantity snapshot (fallback for legacy single-plan rows)
        // -----------------------------------------------------------------
        $qty = (int) ($validated['quantity'] ?? $validated['licenses'] ?? 0);
        if ($qty <= 0) {
            $qty = $this->resolveSourceOrderQuantity($sourceOrderId);
        }
        if ($qty <= 0) {
            $base = Order::query()->find($sourceOrderId);
            $qty = max(1, (int) ($base->quantity ?? 1));
        }

        try {
            $result = $renewService->renew([
                'partner_id' => $partnerId,
                'subscription_id' => $sourceOrderId, // service contract still uses subscription_id key
                'quantity' => $qty,
                'aadhaar_number' => $aadhaarNumber,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: 'Renewal validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process renewal after Aadhaar: ' . $e->getMessage(),
            ], 500);
        }

        $order = $result['order']->fresh() ?? $result['order'];
        $newOrderId = (int) ($order->id ?? 0);
        if ($newOrderId <= 0) {
            return $this->fail('Renewal order was not created. Please try again.', 500);
        }

        if (Schema::hasColumn('orders', 'order_category')) {
            $sourceQty = $this->resolveSourceOrderQuantity($sourceOrderId);
            if ($sourceQty <= 0) {
                $sourceQty = max(1, (int) (Order::query()->whereKey($sourceOrderId)->value('quantity') ?? 1));
            }

            // Partial renew: reduce source seats; only mark fully renewed when all seats move.
            if ($qty < $sourceQty) {
                $remainingQty = $sourceQty - $qty;
                $sourceUpdate = [
                    'quantity' => $remainingQty,
                    'updated_at' => now(),
                ];
                // Keep source live (completed/active) so leftover seats stay Expiring/Active.
                if (Schema::hasColumn('orders', 'status')) {
                    $currentStatus = strtolower(trim((string) (Order::query()->whereKey($sourceOrderId)->value('status') ?? '')));
                    if (in_array($currentStatus, ['renewed', 'upgraded', 'downgraded'], true)) {
                        $sourceUpdate['status'] = 'completed';
                    }
                }
                if (Schema::hasColumn('orders', 'order_category')) {
                    $cat = strtolower(trim((string) (Order::query()->whereKey($sourceOrderId)->value('order_category') ?? '')));
                    if (in_array($cat, ['renewed', 'upgraded', 'downgraded'], true)) {
                        $sourceUpdate['order_category'] = 'new';
                    }
                }
                Order::query()->where('id', $sourceOrderId)->update($sourceUpdate);

                if (Schema::hasTable('order_items') && Schema::hasColumn('order_items', 'quantity')) {
                    $liveItems = OrderItem::query()
                        ->where('order_id', $sourceOrderId)
                        ->where(function ($q) {
                            $q->whereNull('status')
                                ->orWhereRaw("LOWER(TRIM(COALESCE(status, ''))) NOT IN ('upgraded', 'downgraded', 'renewed', 'delete', 'deleted')");
                        })
                        ->orderBy('id')
                        ->get();
                    $toReduce = $qty;
                    foreach ($liveItems as $line) {
                        if ($toReduce <= 0) {
                            break;
                        }
                        $lineQty = max(1, (int) ($line->quantity ?? 1));
                        if ($lineQty <= $toReduce) {
                            OrderItem::query()->whereKey((int) $line->id)->update([
                                'status' => 'renewed',
                                'updated_at' => now(),
                            ]);
                            $toReduce -= $lineQty;
                        } else {
                            OrderItem::query()->whereKey((int) $line->id)->update([
                                'quantity' => $lineQty - $toReduce,
                                'updated_at' => now(),
                            ]);
                            $toReduce = 0;
                        }
                    }
                }
            } else {
                $sourceUpdate = [
                    'order_category' => 'renewed',
                    'updated_at' => now(),
                ];
                if (Schema::hasColumn('orders', 'status')) {
                    $sourceUpdate['status'] = 'renewed';
                }
                Order::query()->where('id', $sourceOrderId)->update($sourceUpdate);
            }

            Order::query()->where('id', $newOrderId)->update([
                'order_category' => 'renewal',
                'updated_at' => now(),
            ]);
        }

        $verificationId = (int) ($otpResult['aadhaar_verification_id'] ?? 0);

        if ($verificationId > 0) {
            $aadhaarService->linkRenewalAfterOrderCreated($verificationId, $newOrderId);
        }

        Cache::forget('aadhaar_renew_' . $sourceOrderId);
        Cache::forget('aadhaar_ref_renew_' . $sourceOrderId);

        $data = $this->buildRenewalSuccessData($order, $result, $sourceOrderId, $qty, $verificationId);
        $billing = app(BillingController::class);
        if (Schema::hasColumn('orders', 'status')) {
            Order::query()->where('id', $newOrderId)->update([
                'status' => 'completed',
                'updated_at' => now(),
            ]);
        }
        $this->syncInvoiceAmountsForOrders([$newOrderId]);
        $billing->markInvoiceStatusPendingForOrder($newOrderId);
        $data['invoice_status'] = 'pending';

        if (Schema::hasColumn('orders', 'credit_used')) {
            $orderCreditUsed = round((float) Order::query()->where('id', $newOrderId)->value('credit_used'), 2);
            $data['credits_used'] = $orderCreditUsed;
            $data['credit_used'] = $orderCreditUsed;
            $data['credits_deducted'] = $orderCreditUsed;
        }

        $renewOrderRow = Order::query()->where('id', $newOrderId)->first();
        if ($renewOrderRow) {
            try {
                $billing->sendPlanRenewedNotifications($newOrderId, $renewOrderRow, true);
            } catch (\Throwable $e) {
                Log::warning('Partner renewal checkout: partner renewal notifications failed', [
                    'order_id' => $newOrderId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Renewal completed successfully.',
            'order_id' => $newOrderId,
            'credits_used' => $data['credits_used'] ?? 0,
            'credit_used' => $data['credit_used'] ?? ($data['credits_used'] ?? 0),
            'used_credit_amount' => $data['credit_used'] ?? ($data['credits_used'] ?? 0),
            'po_link' => $data['po_link'] ?? null,
            'po_number' => $data['po_number'] ?? null,
            'invoice_link' => $data['invoice_link'] ?? null,
            'invoice_status' => 'pending',
            'data' => $data,
        ], 201);
    }

    /**
     * @param  array{order:Order, summary:array<string,mixed>, available_credits:float}  $renewResult
     * @return array<string, mixed>
     */
    private function buildRenewalSuccessData(
        Order $order,
        array $renewResult,
        int $sourceOrderId,
        int $qty,
        int $verificationId = 0,
    ): array {
        $newOrderId = (int) $order->id;
        $plan = Plan::query()->find($order->plan_id);
        $summary = $renewResult['summary'] ?? [];
        $creditsUsed = round((float) ($order->credit_used ?? $summary['payable_amount'] ?? 0), 2);
        $availableBefore = round((float) ($renewResult['available_credits'] ?? 0), 2);

        $data = [
            'order_id' => $newOrderId,
            'order_no' => (string) ($order->order_no ?? $order->id),
            'order_category' => (string) ($order->order_category ?? 'renewal'),
            'parent_order_id' => (int) ($order->parent_order_id ?? $sourceOrderId),
            'customer_id' => (int) ($order->user_id ?? 0),
            'plan_id' => (int) $order->plan_id,
            'plan_name' => (string) ($plan->name ?? $order->plan_name ?? ''),
            'domain_name' => (string) ($order->domain_name ?? ''),
            'licenses' => (int) ($order->quantity ?? $qty),
            'subscription_start_date' => $order->subscription_start_date
                ?? $summary['subscription_start_date']
                ?? null,
            'subscription_end_date' => $order->subscription_end_date
                ?? $summary['subscription_end_date']
                ?? null,
            'renewal_summary' => $summary,
            'credits_used' => $creditsUsed,
            'credit_used' => $creditsUsed,
            'credits_deducted' => $creditsUsed,
            'available_credits_before' => $availableBefore,
            'available_credits_after' => round(max(0, $availableBefore - $creditsUsed), 2),
            'aadhaar_verification_id' => $verificationId > 0 ? $verificationId : null,
            'source_order' => $this->fetchSourceOrderDetails($sourceOrderId),
            'new_order_items' => $this->fetchOrderItems($newOrderId),
        ];

        $this->attachOrderPoAndInvoiceLinks($newOrderId, $data);

        return $data;
    }

    /**
     * Generate PO/invoice files and merge public links into API payload.
     *
     * @param  array<string, mixed>  $data
     */
    private function attachOrderPoAndInvoiceLinks(int $orderId, array &$data): void
    {
        if ($orderId <= 0) {
            return;
        }

        $billing = app(BillingController::class);
        $cartService = app(PartnerCartService::class);
        $skipInvoice = false;
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'status')) {
            $status = strtolower(trim((string) Order::query()->where('id', $orderId)->value('status') ?? ''));
            $skipInvoice = $status === 'pending';
        }
        if (!$skipInvoice && Schema::hasTable('orders') && Schema::hasColumn('orders', 'domain_name')) {
            $domainName = trim((string) (Order::query()->where('id', $orderId)->value('domain_name') ?? ''));
            if ($domainName !== '') {
                $domainCart = new Cart();
                $domainCart->domain_name = $domainName;
                $skipInvoice = $cartService->cartHasMultipleDomains($domainCart);
            }
        }
        if (
            !$skipInvoice
            && Schema::hasTable('partner_aadhaar_verifications')
            && Schema::hasColumn('partner_aadhaar_verifications', 'cart_id')
        ) {
            $cartId = (int) PartnerAadhaarVerification::query()
                ->where('order_id', $orderId)
                ->orderByDesc('id')
                ->value('cart_id');
            if ($cartId > 0) {
                $linkedCart = Cart::query()->find($cartId);
                if ($linkedCart) {
                    $skipInvoice = $cartService->cartHasMultipleDomains($linkedCart);
                }
            }
        }

        try {
            $billing->ensureGeneratedPoForOrder($orderId);
            $billing->persistOrderPoPdfToStorage($orderId);
            if (!$skipInvoice) {
                if (method_exists($billing, 'persistOrderInvoicePdfToStorage')) {
                    $billing->persistOrderInvoicePdfToStorage($orderId);
                }
                $billing->markInvoiceStatusPendingForOrder($orderId);
                $data['invoice_status'] = 'pending';
            }
        } catch (\Throwable $e) {
            Log::error('Order PO/invoice persist failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }

        $links = $billing->getOrderDocumentLinks($orderId);
        if (!empty($links['po_link'])) {
            $data['po_link'] = $links['po_link'];
        }
        if (!$skipInvoice && !empty($links['invoice_link'])) {
            $data['invoice_link'] = $links['invoice_link'];
        }
        if (!empty($links['po_number'])) {
            $data['po_number'] = $links['po_number'];
        }
    }

    /**
     * Read source order + its `order_items` rows. Used in renewal init/verify
     * responses so the client can show "you're renewing this subscription".
     *
     * @return array<string, mixed>
     */
    private function fetchSourceOrderDetails(int $orderId): array
    {
        $order = Order::query()->find($orderId);
        if (!$order) {
            return [];
        }

        return [
            'order_id' => (int) $order->id,
            'order_no' => (string) ($order->order_no ?? $order->id),
            'domain_name' => (string) ($order->domain_name ?? ''),
            'customer_id' => (int) ($order->user_id ?? 0),
            'partner_id' => (int) ($order->partner_id ?? 0),
            'order_category' => (string) ($order->order_category ?? 'new'),
            'subscription_start_date' => $order->subscription_start_date,
            'subscription_end_date' => $order->subscription_end_date,
            'duration' => $order->duration,
            'total_amount' => round((float) ($order->total_amount ?? 0), 2),
            'gst_amount' => round((float) ($order->gst_amount ?? 0), 2),
            'items' => $this->fetchOrderItems($orderId),
        ];
    }

    /**
     * Per-line items for an order from the `order_items` table — multi-plan
     * orders carry one row per plan. Tolerates old schemas using `name`/
     * `price`/`amount` instead of `item_name`/`unit_price`/`total_amount`.
     *
     * @return list<array<string, mixed>>
     */
    private function fetchOrderItems(int $orderId): array
    {
        if ($orderId <= 0 || !Schema::hasTable('order_items')) {
            return [];
        }

        $itemCols = Schema::getColumnListing('order_items');
        $nameCol = in_array('item_name', $itemCols, true) ? 'item_name'
            : (in_array('name', $itemCols, true) ? 'name' : null);
        $unitCol = in_array('unit_price', $itemCols, true) ? 'unit_price'
            : (in_array('price', $itemCols, true) ? 'price' : null);
        $totalCol = in_array('total_amount', $itemCols, true) ? 'total_amount'
            : (in_array('amount', $itemCols, true) ? 'amount' : null);

        $rows = OrderItem::query()->where('order_id', $orderId)->orderBy('id')->get();
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => (int) $row->id,
                'plan_id' => isset($row->plan_id) ? (int) $row->plan_id : null,
                'variant_id' => isset($row->variant_id) ? (int) $row->variant_id : null,
                'item_name' => $nameCol ? (string) ($row->{$nameCol} ?? '') : '',
                'quantity' => isset($row->quantity) ? (int) $row->quantity : 1,
                'unit_price' => $unitCol ? round((float) ($row->{$unitCol} ?? 0), 2) : 0.0,
                'total_amount' => $totalCol ? round((float) ($row->{$totalCol} ?? 0), 2) : 0.0,
            ];
        }

        return $items;
    }

    /**
     * Sum quantity across all `order_items` rows of an order. Returns 0 if
     * the table has no rows for this order (caller will fall back to
     * orders.quantity snapshot).
     */
    private function resolveSourceOrderQuantity(int $orderId): int
    {
        if ($orderId <= 0 || !Schema::hasTable('order_items')) {
            return 0;
        }

        $cols = Schema::getColumnListing('order_items');
        if (!in_array('quantity', $cols, true)) {
            return 0;
        }

        $sum = (int) OrderItem::query()->where('order_id', $orderId)->sum('quantity');

        return max(0, $sum);
    }

    /**
     * OTP verify + insert orders from carts/cart_items (no order_id; uses main_cart_id).
     */
    private function placeOrdersFromMainCart(Request $request): JsonResponse
    {
        $isTestMode = app(AadhaarVerificationGateway::class)->isTestMode();

        $rules = [
            'main_cart_id' => ['required', 'integer', 'exists:carts,id'],
            'partner_id' => ['nullable', 'integer', 'exists:pgsql_auth.partners,id'],
        ];
        if ($isTestMode) {
            $rules['otp'] = ['nullable', 'string', 'max:10'];
            $rules['aadhaar_number'] = ['nullable', 'digits:12'];
        } else {
            $rules['otp'] = ['required', 'digits:6'];
            $rules['aadhaar_number'] = ['required', 'digits:12'];
        }
        if ($request->filled('requested_amount')) {
            $rules['requested_amount'] = ['required', 'numeric', 'min:0.01'];
            $rules['remarks'] = ['nullable', 'string', 'max:500'];
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $this->fail(
                (string) $validator->errors()->first(),
                422,
                $validator->errors()->toArray()
            );
        }

        $validated = $validator->validated();

        if (!Schema::hasTable('carts') || !Schema::hasTable('cart_items')) {
            return $this->fail('Cart tables are not available.', 422);
        }

        $mainCartId = (int) $validated['main_cart_id'];
        $aadhaarService = app(PartnerAadhaarVerificationService::class);

        $aadhaarRecord = $aadhaarService->getLatestForCart($mainCartId);
        if ($aadhaarRecord === null) {
            return $this->fail(
                'No Aadhaar record for this cart. Call orders-aadhaar-init with aadhaar_number first.',
                422,
                null,
                ['cart_id' => $mainCartId]
            );
        }

        $parentCart = Cart::query()
            ->with(['items.plan', 'customer', 'partner'])
            ->find($mainCartId);

        if (!$parentCart) {
            return $this->fail('Cart not found.', 404);
        }

        $recordCustomerId = (int) $aadhaarRecord['customer_id'];
        $cartCustomerId = (int) ($parentCart->customer_id ?? 0);
        if ($cartCustomerId > 0 && $cartCustomerId !== $recordCustomerId) {
            return $this->fail('Cart customer does not match Aadhaar record for this cart.', 422);
        }

        $aadhaarNumber = preg_replace('/\D/', '', (string) ($validated['aadhaar_number'] ?? $aadhaarRecord['aadhaar_number'])) ?? '';
        if (strlen($aadhaarNumber) !== 12) {
            return $this->fail('Aadhaar number must be 12 digits. Call orders-aadhaar-init first.', 422);
        }

        $otp = (string) ($validated['otp'] ?? '');
        if ($otp === '' && $isTestMode) {
            $otp = (string) (config('services.aadhaar.mock_otps')[0] ?? '123456');
        }
        if (!$isTestMode && $otp === '') {
            return $this->fail('OTP is required.', 422, ['otp' => ['OTP is required.']]);
        }

        $aadhaarRefNo = (string) ($aadhaarRecord['aadhaar_ref_no'] ?? '');
        if ($aadhaarRefNo === '') {
            $aadhaarRefNo = (string) Cache::get('aadhaar_ref_' . $mainCartId, '');
        }

        $otpResult = $aadhaarService->verify($aadhaarNumber, $otp, $mainCartId);

        if (!$otpResult['ok']) {
            return $this->fail((string) $otpResult['message'], 422, [
                'otp' => [(string) $otpResult['message']],
            ]);
        }

        $placement = app(PartnerCartOrderPlacementService::class);
        $planLines = $placement->buildPlanLinesFromCart($parentCart);
        if ($planLines === []) {
            return $this->fail('Cart has no valid plan lines.', 422, null, ['cart_id' => $mainCartId]);
        }

        $partnerId = (int) ($validated['partner_id'] ?? $parentCart->partner_id ?? 0);
        $recordPartnerId = (int) ($aadhaarRecord['partner_id'] ?? 0);
        if ($partnerId > 0 && (int) ($parentCart->partner_id ?? 0) !== $partnerId) {
            return $this->fail('Cart does not belong to this partner.', 422, [
                'partner_id' => ['Cart does not belong to this partner.'],
            ]);
        }
        if ($recordPartnerId > 0 && $partnerId > 0 && $recordPartnerId !== $partnerId) {
            return $this->fail('Partner does not match Aadhaar record for this cart.', 422);
        }

        $aadhaarVerificationId = (int) ($otpResult['aadhaar_verification_id'] ?? $aadhaarRecord['id']);

        if ($request->filled('requested_amount')) {
            if (!Schema::hasTable('credit_requests')) {
                return $this->fail('Credit requests are not available.', 422);
            }
            if ($partnerId <= 0) {
                return $this->fail('Partner is required for credit request.', 422);
            }
            if (CreditRequest::query()->where('partner_id', $partnerId)->where('status', 'pending')->exists()) {
                return $this->fail('You already have a pending credit request.', 422);
            }
            $requestedAmount = round((float) $request->input('requested_amount'), 2);
            $creditPayload = [
                'partner_id' => $partnerId,
                'order_id' => null,
                'requested_amount' => $requestedAmount,
                'status' => 'pending',
            ];
            if (Schema::hasColumn('credit_requests', 'cart_id')) {
                $creditPayload['cart_id'] = $mainCartId;
            }
            if (Schema::hasColumn('credit_requests', 'remarks') && $request->filled('remarks')) {
                $creditPayload['remarks'] = (string) $request->input('remarks');
            }
            $creditRequest = CreditRequest::query()->create($creditPayload);
            if (Schema::hasColumn('carts', 'credit_request_id')) {
                $parentCart->credit_request_id = (int) $creditRequest->id;
                if (Schema::hasColumn('carts', 'status')) {
                    $parentCart->status = Cart::STATUS_PENDING_CREDIT;
                }
                $parentCart->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Credit Request Submitted',
                'data' => [
                    'credit_request_id' => (int) $creditRequest->id,
                    'status' => 'pending',
                    'requested_amount' => $requestedAmount,
                    'cart_id' => $mainCartId,
                ],
            ], 201);
        }

        $cartService = app(PartnerCartService::class);
        foreach ($parentCart->items as $cartItem) {
            if (empty($cartItem->subscription_start_date)) {
                continue;
            }

            try {
                $start = \Carbon\Carbon::parse($cartItem->subscription_start_date);
                $plan = $cartItem->plan ?? Plan::query()->find((int) $cartItem->plan_id);
                $cartItem->subscription_end_date = $plan instanceof Plan
                    ? $cartService->computeSubscriptionEnd($start, $plan)
                    : $start->copy()->addYear()->subDay();
                $cartItem->save();
            } catch (\Throwable) {
            }
        }

        $result = $placement->checkoutFromCart($parentCart, [
            'aadhaar_number' => $aadhaarNumber,
            'aadhaar_ref_no' => (string) ($otpResult['ref_no'] ?: $aadhaarRefNo),
            'aadhaar_verification_id' => $aadhaarVerificationId,
            'finalize' => true,
        ]);

        if (empty($result['ok'])) {
            $code = (string) ($result['code'] ?? '');
            $data = array_merge($result['data'] ?? ['cart_id' => $mainCartId], [
                'plan_lines' => $planLines,
                'orders' => $result['orders'] ?? [],
            ]);

            return response()->json([
                'success' => false,
                'message' => (string) ($result['message'] ?? 'Order could not be created.'),
                'code' => $code !== '' ? $code : null,
                'errors' => $result['errors'] ?? null,
                'data' => $data,
            ], (int) ($result['http_status'] ?? 422));
        }

        app(PartnerCartService::class)->clearCartPendingAadhaar($mainCartId);

        $primaryOrderId = (int) ($result['primary_order_id'] ?? 0);
        $orderIds = array_values(array_unique(array_filter(array_map('intval', array_merge(
            [$primaryOrderId],
            (array) ($result['order_ids'] ?? []),
            array_map(
                static fn ($row) => (int) ($row['order_id'] ?? 0),
                (array) ($result['orders'] ?? [])
            )
        )))));

        if (Schema::hasTable('carts')) {
            $cartCouponClear = ['updated_at' => now()];
            if (Schema::hasColumn('carts', 'coupen')) {
                $cartCouponClear['coupen'] = null;
            }
            if (Schema::hasColumn('carts', 'coupon')) {
                $cartCouponClear['coupon'] = null;
            }
            if (count($cartCouponClear) > 1) {
                Cart::query()->where('id', $mainCartId)->update($cartCouponClear);
            }
        }

        if ($orderIds !== []) {
            $this->syncInvoiceAmountsForOrders($orderIds);
        }

        $multipleDomains = $this->applyPendingOrderStatusForMultiDomainCart($parentCart, $orderIds);

        if ($orderIds !== [] && Schema::hasColumn('orders', 'status') && !$multipleDomains) {
            Order::query()->whereIn('id', $orderIds)->update([
                'status' => 'completed',
                'updated_at' => now(),
            ]);
        }

        if ($orderIds !== [] && Schema::hasColumn('orders', 'transfer_domain') && Schema::hasColumn('carts', 'transfer_domain')) {
            $transferDomain = strtolower(trim((string) ($parentCart->transfer_domain ?? '')));
            Order::query()->whereIn('id', $orderIds)->update([
                'transfer_domain' => in_array($transferDomain, ['yes', 'no'], true)
                    ? $transferDomain
                    : null,
                'updated_at' => now(),
            ]);
        }

        if ($multipleDomains) {
            $result['message'] = 'Order created successfully. Pending processing for multiple domains.';
            foreach ($result['orders'] ?? [] as $idx => $orderRow) {
                if (is_array($orderRow)) {
                    $result['orders'][$idx]['status'] = 'pending';
                }
            }
        }

        if ($orderIds !== [] && Schema::hasTable('orders')) {
            $orderColumns = Schema::getColumnListing('orders');
            $startCol = null;
            foreach (['subscription_start_date', 'subscription_start'] as $c) {
                if (in_array($c, $orderColumns, true)) {
                    $startCol = $c;
                    break;
                }
            }
            $endCol = null;
            foreach (['subscription_end_date', 'subscription_end'] as $c) {
                if (in_array($c, $orderColumns, true)) {
                    $endCol = $c;
                    break;
                }
            }
            if ($startCol !== null && $endCol !== null) {
                $this->syncOrderSubscriptionEndDates($orderIds, $startCol, $endCol);
            }
        }

        if ($orderIds !== [] && !$multipleDomains) {
            $billing = app(BillingController::class);
            foreach ($orderIds as $oid) {
                if ($oid <= 0) {
                    continue;
                }
                try {
                    $billing->sendOrderPlacedEmailForOrder($oid, true);
                } catch (\Throwable $e) {
                    Log::warning('Partner cart checkout: partner placement email failed', [
                        'order_id' => $oid,
                        'error' => $e->getMessage(),
                    ]);
                }

                $orderRow = Order::query()->where('id', $oid)->first();
                if (!$orderRow) {
                    continue;
                }
                $partnerId = (int) ($orderRow->partner_id ?? 0);
                if ($partnerId <= 0 || !Schema::hasTable('partners')) {
                    continue;
                }
                $mobile = trim((string) (Partner::query()->where('id', $partnerId)->value('mobile') ?? ''));
                if ($mobile === '') {
                    continue;
                }
                try {
                    $amount = round((float) (
                        $orderRow->total_amount
                        ?? $orderRow->subtotal
                        ?? $orderRow->credit_used
                        ?? 0
                    ), 2);
                    $orderNo = (string) ($orderRow->order_no ?? $oid);
                    $category = strtolower(trim((string) ($orderRow->order_category ?? 'new')));
                    $sms = app(\App\Services\SmsService::class);
                    if ($category === 'upgrade') {
                        $planName = '';
                        if (!empty($orderRow->plan_id) && Schema::hasTable('plans')) {
                            $planName = (string) (Plan::query()->where('id', (int) $orderRow->plan_id)->value('name') ?? '');
                        }
                        $sms->sendUpgradePlatform($mobile, $planName !== '' ? $planName : 'Upgraded plan', $amount);
                    } else {
                        $sms->sendPaymentConfirmation($mobile, $amount, $orderNo);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Partner cart checkout: partner placement SMS failed', [
                        'order_id' => $oid,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $this->checkoutSuccessJson($result, (bool) ($otpResult['mock'] ?? false));
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function checkoutSuccessJson(array $result, bool $otpMock, bool $pendingAadhaar = false): JsonResponse
    {
        $orderId = (int) ($result['primary_order_id'] ?? 0);
        $creditsUsed = 0.0;
        if ($orderId > 0 && Schema::hasTable('orders') && Schema::hasColumn('orders', 'credit_used')) {
            $creditsUsed = round((float) Order::query()->where('id', $orderId)->value('credit_used'), 2);
        } else {
            $creditsUsed = round((float) collect($result['orders'] ?? [])
                ->sum(fn ($row) => (float) ($row['pricing']['credit_used'] ?? 0)), 2);
        }

        $orderStatusPending = false;
        if ($orderId > 0 && Schema::hasTable('orders') && Schema::hasColumn('orders', 'status')) {
            $orderStatusPending = strtolower(trim((string) Order::query()->where('id', $orderId)->value('status'))) === 'pending';
        }

        $message = $pendingAadhaar
            ? 'Order created successfully. Complete Aadhaar verification to place the order.'
            : (string) ($result['message'] ?? 'Order placed successfully.');

        $payload = [
            'success' => true,
            'message' => $message,
            'credit_used' => $creditsUsed,
            'credits_used' => $creditsUsed,
            'used_credit_amount' => $creditsUsed,
            'data' => [
                'order_id' => $orderId,
                'credits_used' => $creditsUsed,
                'credit_used' => $creditsUsed,
            ],
        ];

        if ($orderStatusPending) {
            $payload['data']['status'] = 'pending';
        }

        if ($orderId > 0 && !$pendingAadhaar) {
            $this->syncInvoiceAmountsForOrders([$orderId]);
            $this->attachOrderPoAndInvoiceLinks($orderId, $payload['data']);
            if (!empty($payload['data']['po_link'])) {
                $payload['po_link'] = $payload['data']['po_link'];
            }
            if (!empty($payload['data']['po_number'])) {
                $payload['po_number'] = $payload['data']['po_number'];
            }
            if (!empty($payload['data']['invoice_link'])) {
                $payload['invoice_link'] = $payload['data']['invoice_link'];
            }
        }

        return response()->json($payload, 201);
   
    }

    private function resolveCartForOrderMultiDomainCheck(int $orderId): ?Cart
    {
        if ($orderId <= 0) {
            return null;
        }

        if (Schema::hasTable('partner_aadhaar_verifications') && Schema::hasColumn('partner_aadhaar_verifications', 'cart_id')) {
            $cartId = (int) PartnerAadhaarVerification::query()
                ->where('order_id', $orderId)
                ->orderByDesc('id')
                ->value('cart_id');
            if ($cartId > 0) {
                $cart = Cart::query()->find($cartId);
                if ($cart) {
                    return $cart;
                }
            }
        }

        if (!Schema::hasColumn('orders', 'domain_name')) {
            return null;
        }

        $orderRow = Order::query()->where('id', $orderId)->first(['domain_name']);
        if (!$orderRow) {
            return null;
        }

        $cart = new Cart();
        $cart->domain_name = $orderRow->domain_name ?? '';

        return $cart;
    }

    /**
     * @param  list<int>  $orderIds
     */
    private function applyPendingOrderStatusForMultiDomainCart(?Cart $cart, array $orderIds): bool
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        if ($cart === null || $orderIds === [] || !Schema::hasColumn('orders', 'status')) {
            return false;
        }

        if (!app(PartnerCartService::class)->cartHasMultipleDomains($cart)) {
            return false;
        }

        Order::query()->whereIn('id', $orderIds)->update([
            'status' => 'pending',
            'updated_at' => now(),
        ]);

        return true;
    }

    /**
     * @param  list<int>  $orderIds
     */
    private function syncOrderSubscriptionEndDates(array $orderIds, string $startCol, string $endCol): void
    {
        $cartService = app(PartnerCartService::class);

        foreach (array_values(array_unique(array_filter(array_map('intval', $orderIds)))) as $orderId) {
            if ($orderId <= 0) {
                continue;
            }

            $row = Order::query()->where('id', $orderId)->first([$startCol, 'plan_id']);
            if (!$row || empty($row->{$startCol})) {
                continue;
            }

            try {
                $start = \Carbon\Carbon::parse((string) $row->{$startCol});
                $plan = !empty($row->plan_id) ? Plan::query()->find((int) $row->plan_id) : null;
                $endDate = $plan instanceof Plan
                    ? $cartService->computeSubscriptionEnd($start, $plan)->toDateString()
                    : $start->copy()->addYear()->subDay()->toDateString();

                Order::query()->where('id', $orderId)->update([
                    $endCol => $endDate,
                    'updated_at' => now(),
                ]);
            } catch (\Throwable) {
            }
        }
    }

    /**
     * @param  list<int>  $orderIds
     */
    private function syncInvoiceAmountsForOrders(array $orderIds): void
    {
        $billing = app(BillingController::class);
        foreach (array_values(array_unique(array_filter(array_map('intval', $orderIds)))) as $orderId) {
            if ($orderId <= 0) {
                continue;
            }
            try {
                $billing->syncInvoiceAmountsFromOrder($orderId);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Aadhaar flow: invoice amount sync failed', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param  array<string, list<string>>|null  $errors
     * @param  array<string, mixed>|null  $data
     */
    private function fail(string $message, int $status = 422, ?array $errors = null, ?array $data = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'data' => $data,
        ], $status);
    }
}
