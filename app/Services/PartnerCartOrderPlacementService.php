<?php

namespace App\Services;

use App\Http\Controllers\SuperAdmin\BillingController;
use App\Models\AdminSetting;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\OrderItem;
use App\Models\Partner;
use App\Models\Plan;
use App\Support\OrderDomainHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PartnerCartOrderPlacementService
{
    /** Mirror App\Models\Cart — local constants for servers with outdated Cart.php. */
    private const CART_TYPE_STANDARD = 'standard';

    private const CART_STATUS_CONVERTED = 'converted';

    /**
     * Build plan lines from carts + cart_items (same shape as orders-add multiple plans).
     *
     * @return list<array{
     *   cart_item_id: int,
     *   plan_id: int,
     *   quantity: int,
     *   unit_price: float,
     *   line_grand_total: float,
     *   subscription_start_date: string|null,
     *   subscription_end_date: string|null
     * }>
     */
    public function buildPlanLinesFromCart(Cart $cart): array
    {
        $cart = $cart->relationLoaded('items') ? $cart : $cart->load(['items.plan']);
        $lines = [];

        foreach ($cart->items as $item) {
            $planId = (int) ($item->plan_id ?? 0);
            $qty = max(1, (int) ($item->licenses ?? 0));
            if ($planId <= 0 || $qty < 1) {
                continue;
            }

            $lines[] = [
                'cart_item_id' => (int) $item->id,
                'plan_id' => $planId,
                'quantity' => $qty,
                'unit_price' => round((float) ($item->unit_price ?? 0), 2),
                'line_grand_total' => round((float) ($item->grand_total ?? 0), 2),
                'subscription_start_date' => $item->subscription_start_date
                    ? $item->subscription_start_date->format('Y-m-d')
                    : null,
                'subscription_end_date' => $item->subscription_end_date
                    ? $item->subscription_end_date->format('Y-m-d')
                    : null,
            ];
        }

        return $lines;
    }

    /**
     * Checkout all cart_items into orders (Superadmin saveStep step-1 alignment).
     *
     * @param  array{aadhaar_number?: string, aadhaar_ref_no?: string, aadhaar_verification_id?: int, finalize?: bool, skip_credit_checks?: bool, retain_cart?: bool, force_pending?: bool}  $options
     * @return array<string, mixed>
     */
    public function checkoutFromCart(Cart $parentCart, array $options = []): array
    {
        $cartService = app(PartnerCartService::class);
        $aadhaarService = app(PartnerAadhaarVerificationService::class);
        $finalize = !empty($options['finalize']);
        $aadhaarNumber = (string) ($options['aadhaar_number'] ?? '');
        $aadhaarRefNo = (string) ($options['aadhaar_ref_no'] ?? '');
        $aadhaarVerificationId = (int) ($options['aadhaar_verification_id'] ?? 0);
        $skipCreditChecks = !empty($options['skip_credit_checks']);
        $retainCart = !empty($options['retain_cart']);
        $forcePending = !empty($options['force_pending']);

        $cartService->recalculateCartTotals((int) $parentCart->id);
        $parentCart = $parentCart->fresh(['items.plan', 'customer', 'partner']);
        $planLinesFromCart = $this->buildPlanLinesFromCart($parentCart);

        if ($planLinesFromCart === []) {
            return [
                'ok' => false,
                'http_status' => 422,
                'message' => 'Cart has no valid plan lines.',
                'data' => ['cart_id' => (int) $parentCart->id],
            ];
        }

        /** @var Collection<int, CartItem> $cartLines */
        $cartLines = $parentCart->items->values();
        if ($cartLines->isEmpty()) {
            return [
                'ok' => false,
                'http_status' => 422,
                'message' => 'Cart has no items to checkout.',
                'data' => ['cart_id' => (int) $parentCart->id],
            ];
        }

        $partnerId = (int) ($parentCart->partner_id ?? 0);
        $customerId = (int) ($parentCart->customer_id ?? 0);
        if ($partnerId <= 0 || $customerId <= 0) {
            return [
                'ok' => false,
                'http_status' => 422,
                'message' => 'Cart must have partner and customer before checkout.',
                'data' => ['cart_id' => (int) $parentCart->id],
            ];
        }

        $customer = Customer::query()->find($customerId);
        if (!$customer || (int) ($customer->partner_id ?? 0) !== $partnerId) {
            return [
                'ok' => false,
                'http_status' => 422,
                'message' => 'Cart customer does not belong to this partner.',
                'errors' => ['partner_id' => ['Cart customer does not belong to this partner.']],
            ];
        }

        $domainName = $cartService->cartDomainNameString($parentCart);
        if ($domainName === '') {
            return [
                'ok' => false,
                'http_status' => 422,
                'message' => 'Cart has no domain. Update cart with domain_name first.',
                'data' => ['cart_id' => (int) $parentCart->id],
            ];
        }

        $providerId = (int) ($parentCart->provider_id ?? 0);
        if ($providerId <= 0) {
            $firstPlan = $cartLines->first()?->plan;
            $providerId = (int) ($firstPlan->provider_id ?? 0);
        }

        $domainValidation = OrderDomainHelper::validateInput($domainName, $providerId > 0 ? $providerId : null);
        if (!$domainValidation['ok']) {
            return [
                'ok' => false,
                'http_status' => 422,
                'message' => $domainValidation['message'],
                'errors' => ['domain_name' => [$domainValidation['message']]],
            ];
        }

        $partner = Partner::query()->findOrFail($partnerId);
        $dbOrderType = 'b2b';
        $orderCategory = $cartService->orderCategoryFromCartType($parentCart);
        $useCredit = true;
        $gstRate = (float) ($parentCart->gst_percentage ?? 18);
        $applyGst = (float) ($parentCart->gst_amount ?? 0) > 0
            || (float) ($parentCart->gst_percentage ?? 0) > 0;
        $cartGrandTotal = round((float) ($parentCart->grand_total ?? 0), 2);
        $cartGstAmount = round((float) ($parentCart->gst_amount ?? 0), 2);
        $cartDiscount = round((float) ($parentCart->discount_amount ?? 0), 2);

        if ($cartGrandTotal <= 0) {
            $cartGrandTotal = 0.0;
            foreach ($cartLines as $line) {
                if (!$line->plan) {
                    continue;
                }
                $amt = $cartService->calculateLineAmounts(
                    $line->plan,
                    (int) $line->licenses,
                    $gstRate,
                    $cartService->resolveCouponDiscountPercent($parentCart->coupen ?? '')
                );
                $cartGrandTotal += (float) ($line->grand_total ?? $amt['grand_total'] ?? 0);
            }
            $cartGrandTotal = round($cartGrandTotal, 2);
        }

        if (!$skipCreditChecks) {
            $creditCheck = PartnerCreditService::validateB2bPurchaseAmount($partnerId, $cartGrandTotal);
            if (empty($creditCheck['ok'])) {
                return [
                    'ok' => false,
                    'http_status' => 422,
                    'message' => (string) ($creditCheck['message'] ?? PartnerCreditService::CREDIT_LIMIT_REACHED_MESSAGE),
                    'code' => (string) ($creditCheck['code'] ?? 'credit_limit_exceeded'),
                    'data' => array_merge(
                        ['cart_id' => (int) $parentCart->id, 'total_amount' => $cartGrandTotal],
                        array_intersect_key($creditCheck, array_flip([
                            'credit_limit', 'credit_used', 'remaining_limit', 'wallet_balance',
                        ]))
                    ),
                ];
            }
        }

        $billing = app(BillingController::class);
        $prefill = app(RenewalService::class)->buildPurchaseOrderPrefillFromCustomer($customerId);
        $orderColumns = Schema::getColumnListing('orders');
        $ordersOut = [];
        $lineIndex = 0;
        $lineRows = [];
        $plansPayload = [];

        foreach ($cartLines as $cartLine) {
            $plan = $cartLine->plan ?? Plan::query()->find((int) $cartLine->plan_id);
            if (!$plan) {
                continue;
            }

            $licenses = max(1, (int) $cartLine->licenses);
            if (!empty($plan->customer_limit) && $licenses > (int) $plan->customer_limit) {
                return [
                    'ok' => false,
                    'http_status' => 422,
                    'message' => 'Requested quantity exceeds plan customer limit for plan #' . $plan->id,
                    'errors' => ['plans.' . $lineIndex . '.quantity' => ['Exceeds plan customer limit.']],
                ];
            }

            $lineAmounts = $cartService->calculateLineAmounts($plan, $licenses, $gstRate, 0);
            $unitPrice = round((float) ($cartLine->unit_price ?? $lineAmounts['unit_price'] ?? 0), 2);
            $lineGross = round(
                (float) ($cartLine->grand_total ?? $cartService->lineGrossTotal($unitPrice, $licenses)),
                2
            );
            $lineRows[] = [
                'cartLine' => $cartLine,
                'plan' => $plan,
                'licenses' => $licenses,
                'unitPrice' => $unitPrice,
                'lineGrand' => $lineGross,
                'lineGst' => 0.0,
                'lineSubtotal' => $lineGross,
                'lineDiscount' => round((float) ($lineAmounts['plan_discount_amount'] ?? 0), 2),
            ];
            $plansPayload[] = [
                'plan_id' => (int) $plan->id,
                'quantity' => $licenses,
            ];
            $lineIndex++;
        }

        if ($lineRows === []) {
            return [
                'ok' => false,
                'http_status' => 422,
                'message' => 'No valid plan lines in cart.',
            ];
        }

        $firstLine = $lineRows[0];
        $cartSubtotal = round((float) ($parentCart->subtotal ?? 0), 2);
        if ($cartSubtotal <= 0) {
            $cartSubtotal = round(array_sum(array_column($lineRows, 'lineGrand')), 2);
        }

        $step1Payload = [
            'partner_id' => $partnerId,
            'user_id' => $customerId,
            'plan_id' => (int) $firstLine['plan']->id,
            'plans' => $plansPayload,
            'domain_name' => $domainName,
            'order_type' => $dbOrderType,
            'quantity' => $firstLine['licenses'],
            'amount' => $cartGrandTotal,
            'discount_amount' => $cartDiscount,
            'apply_gst' => $applyGst ? 1 : 0,
            'gst_rate' => $gstRate,
            'gst_amount' => $cartGstAmount,
            'tax_amount' => $cartGstAmount,
            'po_mode' => 'generated',
            'po_journey_status' => $finalize ? 'po_done' : 'po_pending',
            'ajax_submit' => 1,
            'use_cart_pricing' => 1,
        ];

        if (!empty($parentCart->coupen)) {
            $step1Payload['promocode'] = strtoupper(trim((string) $parentCart->coupen));
            $step1Payload['coupen'] = $step1Payload['promocode'];
        }

        $firstCartLine = $firstLine['cartLine'];
        if (!empty($firstCartLine->subscription_start_date)) {
            $startDate = $firstCartLine->subscription_start_date->format('Y-m-d');
            $step1Payload['subscription_start_date'] = $startDate;
            if (!empty($firstCartLine->subscription_end_date)) {
                $step1Payload['subscription_end_date'] = $firstCartLine->subscription_end_date->format('Y-m-d');
            } else {
                $cartService = app(PartnerCartService::class);
                $step1Payload['subscription_end_date'] = $cartService
                    ->computeSubscriptionEnd(\Carbon\Carbon::parse($startDate), $firstLine['plan'])
                    ->format('Y-m-d');
            }
        }

        $step1Request = Request::create('/api/v1/partner/create-order', 'POST', $step1Payload);
        $step1Request->headers->set('Accept', 'application/json');
        $step1Request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $step1Response = $billing->saveStep($step1Request, 1, null);
        $step1Data = $step1Response->getData(true);

        if (!is_array($step1Data) || !empty($step1Data['error'])) {
            return [
                'ok' => false,
                'http_status' => $step1Response->getStatusCode() >= 400 ? $step1Response->getStatusCode() : 422,
                'message' => (string) ($step1Data['message'] ?? 'Order could not be created.'),
                'errors' => $step1Data['details'] ?? $step1Data['errors'] ?? null,
                'orders' => $ordersOut,
                'data' => ['cart_id' => (int) $parentCart->id],
            ];
        }

        $orderId = (int) ($step1Data['order_id'] ?? 0);
        if ($orderId <= 0) {
            return [
                'ok' => false,
                'http_status' => 500,
                'message' => 'Order save failed.',
                'orders' => $ordersOut,
            ];
        }

        $primaryOrderId = $orderId;
        $orderIds = [$orderId];
        $orderStatus = $forcePending ? 'pending' : ($finalize ? 'completed' : 'pending');

        if (Schema::connection('pgsql_billing')->hasTable('order_items')) {
            OrderItem::query()->where('order_id', $orderId)->delete();
            foreach ($lineRows as $row) {
                $plan = $row['plan'];
                $unitPrice = round((float) ($row['unitPrice'] ?? 0), 2);
                $lineTotal = round((float) ($row['lineGrand'] ?? 0), 2);
                $orderItem = OrderItem::query()->create([
                    'order_id' => $orderId,
                    'plan_id' => (int) $plan->id,
                    'item_name' => (string) ($plan->name ?? 'Plan'),
                    'quantity' => (int) $row['licenses'],
                    'unit_price' => $unitPrice,
                    'total_amount' => $lineTotal,
                    'tax_amount' => 0,
                    'status' => 'active',
                ]);
                $subscriptionPrefix = strtoupper(trim((string) (
                    AdminSetting::query()->orderBy('id')->value('subscription_prefix') ?? 'SUB'
                ))) ?: 'SUB';
                $orderItem->subscription_id = $subscriptionPrefix . '-' . str_pad((string) $orderItem->id, 6, '0', STR_PAD_LEFT);
                $orderItem->save();
            }
        }

        $orderPatch = [];
        foreach ([
            'subtotal' => $cartSubtotal,
            'total_amount' => $cartGrandTotal,
            'gst_amount' => $cartGstAmount,
            'tax_amount' => $cartGstAmount,
            'gst_rate' => $gstRate,
            'apply_gst' => $applyGst ? 1 : 0,
            'discount_amount' => $cartDiscount,
            'credit_used' => $useCredit ? $cartGrandTotal : 0,
            'status' => $orderStatus,
        ] as $col => $val) {
            if (in_array($col, $orderColumns, true)) {
                $orderPatch[$col] = $val;
            }
        }
        $orderPatch = array_merge($orderPatch, $cartService->cartPromoFieldsForOrder($parentCart));
        if (in_array('order_category', $orderColumns, true)) {
            $orderPatch['order_category'] = $orderCategory;
        }
        if (in_array('parent_order_id', $orderColumns, true)) {
            $pId = (int) ($parentCart->order_id ?? 0);
            if ($pId <= 0 && in_array($orderCategory, ['addon', 'license_add', 'upgrade', 'downgrade', 'renewal'], true)) {
                $pId = (int) (Order::query()
                    ->where('partner_id', $partnerId)
                    ->where('domain_name', $domainName)
                    ->where(function ($q) {
                        $q->whereNull('parent_order_id')->orWhere('parent_order_id', 0);
                    })
                    ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) NOT IN ('draft', 'cancelled', 'canceled', 'failed', 'rejected')")
                    ->orderByDesc('id')
                    ->value('id') ?? 0);
            }
            if ($pId > 0) {
                $orderPatch['parent_order_id'] = $pId;
            }
        }
        if ($orderCategory === 'license_add' || $orderCategory === 'addon') {
            if (in_array('quantity', $orderColumns, true)) {
                $orderPatch['quantity'] = (int) $firstLine['licenses'];
            }
            if (in_array('licenses', $orderColumns, true)) {
                $orderPatch['licenses'] = (int) $firstLine['licenses'];
            }
        }
        if (in_array('updated_at', $orderColumns, true)) {
            $orderPatch['updated_at'] = now();
        }
        if ($orderPatch !== []) {
            DB::table('orders')->where('id', $orderId)->update($orderPatch);
        }

        PartnerCreditService::cleanupB2bOrderWalletLedgersForOrder($orderId);

        if ($prefill !== []) {
            $billing->syncWizardPoFromCustomerPrefill($orderId, $prefill);
        }

        if ($finalize) {
            $aadhaarService->finalizeOrderAfterOtp(
                $orderId,
                $aadhaarNumber,
                $aadhaarRefNo,
                $aadhaarVerificationId > 0 ? $aadhaarVerificationId : null,
                $skipCreditChecks
            );
        }

        $order = DB::table('orders')->where('id', $orderId)->first();
        $poRow = Schema::hasTable('order_po_details')
            ? DB::table('order_po_details')->where('order_id', $orderId)->first()
            : null;

        foreach ($lineRows as $row) {
            $ordersOut[] = [
                'order_id' => $orderId,
                'order_no' => (string) ($order->order_no ?? ''),
                'plan_id' => (int) $row['plan']->id,
                'cart_item_id' => (int) $row['cartLine']->id,
                'licenses' => $row['licenses'],
                'status' => $orderStatus,
                'domain_name' => (string) ($order->domain_name ?? $domainName),
                'transfer_domain' => Schema::hasColumn('carts', 'transfer_domain')
                    ? ($parentCart->transfer_domain ?? null)
                    : null,
                'pricing' => [
                    'subtotal' => $row['lineGrand'],
                    'discount_amount' => $row['lineDiscount'],
                    'gst_rate' => $gstRate,
                    'gst_amount' => 0,
                    'total_amount' => $row['lineGrand'],
                    'credit_used' => $useCredit ? $row['lineGrand'] : 0.0,
                ],
                'po' => [
                    'po_number' => (string) ($poRow->po_number ?? ''),
                    'po_date' => $poRow->po_date ?? null,
                    'journey_status' => (string) ($poRow->journey_status ?? ($finalize ? 'placed' : 'po_pending')),
                ],
                'invoice' => null,
            ];
            if (!$retainCart) {
                $row['cartLine']->delete();
            }
        }

        if ($ordersOut === []) {
            return [
                'ok' => false,
                'http_status' => 422,
                'message' => 'No valid plan lines in cart.',
            ];
        }

        if (!$retainCart) {
            $cartService->recalculateCartTotals((int) $parentCart->id);
            $parentCart = $cartService->clearCartRelatedFieldsWhenEmpty(
                $parentCart->fresh(['items.plan', 'customer', 'partner'])
            );

            if (Schema::hasColumn('carts', 'status') && $finalize) {
                $cartType = (string) ($parentCart->type ?? self::CART_TYPE_STANDARD);
                $cartService->clearSupersededConvertedCarts($partnerId, $cartType, (int) $parentCart->id);
                $parentCart->status = self::CART_STATUS_CONVERTED;
                $parentCart->save();
            }
        } else {
            $parentCart = $parentCart->fresh(['items.plan', 'customer', 'partner']);
        }
        $partner->refresh();

        if ($finalize && $aadhaarVerificationId > 0) {
            $aadhaarService->linkOrdersAfterCheckout(
                $aadhaarVerificationId,
                (int) $parentCart->id,
                $primaryOrderId,
                $orderIds
            );
        }

        return [
            'ok' => true,
            'http_status' => 201,
            'message' => $finalize
                ? 'Order placed successfully.'
                : 'Order created successfully. Complete Aadhaar verification to place the order.',
            'primary_order_id' => $primaryOrderId,
            'order_ids' => $orderIds,
            'plan_lines' => $planLinesFromCart,
            'orders' => $ordersOut,
            'partner_id' => $partnerId,
            'customer_id' => $customerId,
            'domain_name' => $domainName,
            'order_category' => $orderCategory,
            'cart_discount' => $cartDiscount,
            'gst_rate' => $gstRate,
            'use_credit' => $useCredit,
            'parent_cart' => $parentCart,
            'partner' => $partner,
        ];
    }
}
