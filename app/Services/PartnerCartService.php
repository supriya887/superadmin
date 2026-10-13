<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\Promocode;
use App\Support\OrderDomainHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PartnerCartService
{
    /** Mirror App\Models\Cart constants — used here so partner checkout works if server Cart.php is outdated. */
    private const CART_TYPE_STANDARD = 'standard';

    private const CART_TYPE_UPGRADE = 'upgrade';

    private const CART_TYPE_DOMAIN_TRANSFER = 'domain_transfer';

    private const CART_STATUS_ACTIVE = 'active';

    private const CART_STATUS_CONVERTED = 'converted';

    /** @var list<string> */
    private const CART_LEGACY_STANDARD_TYPES = ['standard', 'new'];

    public function findOrCreateActiveCart(int $partnerId, string $type, ?int $providerId = null, ?string $coupon = null): Cart
    {
        // Reuse the latest active cart for this partner+type (even when empty) — never spawn a second parent row.
        $cart = Cart::query()
            ->ofType($type)
            ->where('partner_id', $partnerId)
            ->active()
            ->orderByDesc('id')
            ->first();

        if ($cart) {
            if ($providerId && empty($cart->provider_id)) {
                $cart->provider_id = $providerId;
            }
            if ($coupon !== null && $coupon !== '') {
                $cart->coupen = $coupon;
            }
            $cart->save();

            return $cart->fresh(['items.plan', 'customer']);
        }

        return Cart::create([
            'session_id' => (string) Str::uuid(),
            'partner_id' => $partnerId,
            'provider_id' => $providerId,
            'type' => $type === self::CART_TYPE_STANDARD ? self::CART_TYPE_STANDARD : $type,
            'status' => self::CART_STATUS_ACTIVE,
            'currency' => 'INR',
            'coupen' => $coupon,
            'gst_percentage' => 18,
            'discount_amount' => 0,
            'gst_amount' => 0,
            'grand_total' => 0,
        ]);
    }

    public function deleteActiveCartsByType(int $partnerId, string $type): void
    {
        $carts = Cart::query()
            ->active()
            ->where('partner_id', $partnerId)
            ->where('type', $type)
            ->orderByDesc('id')
            ->get(['id']);

        foreach ($carts as $cart) {
            $cartId = (int) ($cart->id ?? 0);
            // Keep place-pending renew/upgrade/new carts until Place Order.
            if ($cartId > 0 && $this->isCartPendingAadhaar($cartId)) {
                continue;
            }
            if ($cartId > 0) {
                Cart::query()->whereKey($cartId)->update(['status' => 'inactive']);
            }
        }
    }

    /**
     * Remove older converted carts for this partner+type so unique_active_partner_cart
     * (partner_id + status + type) does not block marking the current cart converted.
     */
    public function clearSupersededConvertedCarts(int $partnerId, string $type, int $keepCartId): void
    {
        if ($partnerId <= 0 || $keepCartId <= 0) {
            return;
        }

        $query = Cart::query()
            ->where('partner_id', $partnerId)
            ->where('status', self::CART_STATUS_CONVERTED)
            ->where('id', '!=', $keepCartId);

        $normalizedType = strtolower(trim($type));
        if (in_array($normalizedType, self::CART_LEGACY_STANDARD_TYPES, true)) {
            $query->whereIn('type', self::CART_LEGACY_STANDARD_TYPES);
        } else {
            $query->ofType($type);
        }

        $query->update(['status' => 'converted']);
    }

    /**
     * Google subscriptions end on the same calendar date next cycle; Tizzy/Microsoft end one day earlier.
     */
    public function computeSubscriptionEnd(Carbon $start, Plan $plan): Carbon
    {
        $billingCycle = strtolower((string) ($plan->billing_cycle ?? ''));
        $sameAnniversaryEnd = (int) ($plan->provider_id ?? 0) === OrderCancellationPolicy::PROVIDER_GOOGLE;

        if ($billingCycle === '' || $billingCycle === 'monthly') {
            $end = $start->copy()->addMonth();

            return $sameAnniversaryEnd ? $end : $end->subDay();
        }

        if (
            $billingCycle === 'yearly'
            || $billingCycle === '12 month'
            || $billingCycle === '12 months'
            || str_contains($billingCycle, 'year')
        ) {
            $end = $start->copy()->addYear();

            return $sameAnniversaryEnd ? $end : $end->subDay();
        }

        if (preg_match('/^(\d+)\s*month/', $billingCycle, $matches)) {
            $end = $start->copy()->addMonths(max(1, (int) $matches[1]));

            return $sameAnniversaryEnd ? $end : $end->subDay();
        }

        if (str_starts_with($billingCycle, 'days:')) {
            $days = (int) substr($billingCycle, 5);

            return $start->copy()->addDays($days > 0 ? $days : 30);
        }

        $end = $start->copy()->addYear();

        return $sameAnniversaryEnd ? $end : $end->subDay();
    }

    /**
     * Consistent billing-cycle fields for partner APIs (Google / Microsoft / Tizzy).
     *
     * @return array{
     *     billing_cycle: string,
     *     subscription_start_date: string,
     *     subscription_end_date: string,
     *     start_date: string,
     *     end_date: string,
     *     provider_id: int,
     *     billing_cycle_end_rule: string
     * }
     */
    public function billingCycleApiPayload(Plan $plan, ?Carbon $start = null): array
    {
        $start = ($start ?? now())->copy()->startOfDay();
        $end = $this->computeSubscriptionEnd($start, $plan);
        $providerId = (int) ($plan->provider_id ?? 0);

        return [
            'billing_cycle' => (string) ($plan->billing_cycle ?? ''),
            'subscription_start_date' => $start->format('d-m-Y'),
            'subscription_end_date' => $end->format('d-m-Y'),
            'start_date' => $start->format('d-m-Y'),
            'end_date' => $end->format('d-m-Y'),
            'provider_id' => $providerId,
            'billing_cycle_end_rule' => $providerId === OrderCancellationPolicy::PROVIDER_GOOGLE
                ? 'same_anniversary_date'
                : 'one_day_before_anniversary',
        ];
    }

    /**
     * Resolve cart/order line subscription dates using stored values or provider-aware defaults.
     *
     * @return array<string, string|int>
     */
    public function resolveLineSubscriptionDatesForApi(CartItem $item, Plan $plan): array
    {
        $start = !empty($item->subscription_start_date)
            ? Carbon::parse($item->subscription_start_date)->startOfDay()
            : now()->startOfDay();

        // Always apply provider rule (Google same day; Tizzy/Microsoft -1 day).
        $end = $this->computeSubscriptionEnd($start, $plan);

        $payload = $this->billingCycleApiPayload($plan, $start);
        $payload['subscription_end_date'] = $end->format('d-m-Y');
        $payload['end_date'] = $end->format('d-m-Y');

        return $payload;
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    public function resolveSubscriptionPeriod(Plan $plan, ?Carbon $start = null): array
    {
        $start = $start ?? now();
        $end = $this->computeSubscriptionEnd($start, $plan);

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Active promocode discount % from carts.coupen (matches orderStore promo logic).
     */
    public function resolveCouponDiscountPercent(?string $couponCode): float
    {
        $code = strtoupper(trim((string) $couponCode));
        if ($code === '') {
            return 0.0;
        }

        $promo = Promocode::query()
            ->whereRaw('UPPER(code) = ?', [$code])
            ->where('status', 1)
            ->first();

        return $promo ? (float) $promo->discount_percent : 0.0;
    }

    /**
     * @return array{
     *   unit_price: float,
     *   subtotal: float,
     *   plan_discount_amount: float,
     *   coupon_discount_amount: float,
     *   discount_amount: float,
     *   after_discount: float,
     *   gst_amount: float,
     *   grand_total: float
     * }
     */
    public function calculateLineAmounts(
        Plan $plan,
        int $licenses,
        float $gstPercentage = 18,
        float $couponDiscountPercent = 0
    ): array {
        $unitPrice = (float) ($plan->price ?? 0);
        $subtotal = $unitPrice * $licenses;
        $planDiscountPercent = (float) ($plan->discount ?? 0);
        $planDiscountAmount = $planDiscountPercent > 0 ? ($subtotal * $planDiscountPercent) / 100 : 0;
        $afterPlanDiscount = $subtotal - $planDiscountAmount;
        $couponDiscountAmount = $couponDiscountPercent > 0
            ? ($afterPlanDiscount * $couponDiscountPercent) / 100
            : 0;
        $afterDiscount = $afterPlanDiscount - $couponDiscountAmount;
        $gstAmount = 0;
        $grandTotal = $afterDiscount;
        $totalDiscount = $planDiscountAmount + $couponDiscountAmount;

        return [
            'unit_price' => round($unitPrice, 2),
            'subtotal' => round($subtotal, 2),
            'plan_discount_amount' => round($planDiscountAmount, 2),
            'coupon_discount_amount' => round($couponDiscountAmount, 2),
            'discount_amount' => round($totalDiscount, 2),
            'after_discount' => round($afterDiscount, 2),
            'gst_amount' => round($gstAmount, 2),
            'grand_total' => round($grandTotal, 2),
        ];
    }

    /**
     * Per-line amount before tax: licenses × unit_price (no GST, no cart-level coupon).
     */
    public function lineGrossTotal(float $unitPrice, int $licenses): float
    {
        return round(max(0, $unitPrice) * max(1, $licenses), 2);
    }

    public function recalculateCartTotals(int $cartId): ?Cart
    {
        $cart = Cart::with(['items.plan'])->find($cartId);
        if (!$cart) {
            return null;
        }

        // Upgrade / renew / downgrade totals come from proration — never wipe remaining_value.
        $cartType = strtolower(trim((string) ($cart->type ?? '')));
        if (in_array($cartType, [
            Cart::TYPE_UPGRADE,
            Cart::TYPE_DOWNGRADE,
            Cart::TYPE_RENEW,
        ], true)) {
            return $cart->fresh(['items.plan', 'customer']) ?? $cart;
        }

        $gstPercentage = (float) ($cart->gst_percentage ?? 18);
        $couponDiscountPercent = $this->resolveCouponDiscountPercent($cart->coupen ?? '');
        $sumGross = 0.0;
        $sumPlanDiscount = 0.0;

        foreach ($cart->items as $item) {
            $plan = $item->plan;
            if (!$plan) {
                continue;
            }

            $licenses = max(1, (int) $item->licenses);
            $amounts = $this->calculateLineAmounts($plan, $licenses, $gstPercentage, 0);
            $item->unit_price = $amounts['unit_price'];
            $item->grand_total = $this->lineGrossTotal((float) $item->unit_price, $licenses);
            $item->save();

            $sumGross += (float) $amounts['subtotal'];
            $sumPlanDiscount += (float) $amounts['plan_discount_amount'];
        }

        $afterPlanDiscount = max(0, round($sumGross - $sumPlanDiscount, 2));
        $couponDiscount = $couponDiscountPercent > 0
            ? round(($afterPlanDiscount * $couponDiscountPercent) / 100, 2)
            : 0.0;
        $cartDiscount = round($sumPlanDiscount + $couponDiscount, 2);
        $grossSubtotal = round($sumGross, 2);

        // Match partner checkout UI: GST on full subtotal, then subtract promo discount.
        $sumGst = round(($grossSubtotal * $gstPercentage) / 100, 2);

        $cart->discount_amount = $cartDiscount;
        $cart->gst_amount = $sumGst;
        $cart->grand_total = round($grossSubtotal - $cartDiscount + $sumGst, 2);
        if (Schema::connection($cart->getConnectionName() ?: config('database.default'))->hasColumn('carts', 'subtotal')) {
            $cart->subtotal = $grossSubtotal;
        }
        $cart->save();

        return $cart->fresh(['items.plan', 'customer']);
    }

    /**
     * Tax-inclusive checkout total (same amount debited from wallet/credit on order).
     *
     * @return array{
     *   subtotal: float,
     *   discount_amount: float,
     *   taxable_amount: float,
     *   gst_percentage: float,
     *   gst_amount: float,
     *   grand_total: float,
     *   deducted_amount: float,
     *   credits_deducted: float,
     *   credits_used: float,
     *   total_invoice_amount: float
     * }
     */
    /**
     * Map carts.coupen + carts.discount_amount onto orders columns (promo_code / discount_amount).
     *
     * @return array<string, mixed>
     */
    public function cartPromoFieldsForOrder(Cart $cart): array
    {
        $orderColumns = Schema::getColumnListing('orders');
        $fields = [];
        $code = strtoupper(trim((string) ($cart->coupen ?? '')));
        $discount = round((float) ($cart->discount_amount ?? 0), 2);

        if ($code !== '') {
            if (in_array('promo_code', $orderColumns, true)) {
                $fields['promo_code'] = $code;
            }
            if (in_array('coupen', $orderColumns, true)) {
                $fields['coupen'] = $code;
            }
            if (in_array('promocode', $orderColumns, true)) {
                $fields['promocode'] = $code;
            }
        }

        if (in_array('discount_amount', $orderColumns, true)) {
            $fields['discount_amount'] = $discount;
        }

        return $fields;
    }

    public function buildCartDeductionSummary(Cart $cart): array
    {
        $cart = $this->recalculateCartTotals((int) $cart->id) ?? $cart->fresh(['items.plan', 'customer']);

        $gstPercentage = round((float) ($cart->gst_percentage ?? 18), 2);
        $discountAmount = round((float) ($cart->discount_amount ?? 0), 2);
        $gstAmount = round((float) ($cart->gst_amount ?? 0), 2);
        $grandTotal = round((float) ($cart->grand_total ?? 0), 2);
        $grossSubtotal = round(max(0, $grandTotal - $gstAmount + $discountAmount), 2);

        if ($cart->items->isEmpty() && $cart->plan_id) {
            $plan = $cart->plan ?? Plan::find((int) $cart->plan_id);
            if ($plan) {
                $licenses = max(1, (int) ($cart->licenses ?? 1));
                $couponPercent = $this->resolveCouponDiscountPercent($cart->coupen ?? '');
                $amounts = $this->calculateLineAmounts($plan, $licenses, $gstPercentage, $couponPercent);
                $grossSubtotal = round((float) $amounts['subtotal'], 2);
                $discountAmount = round((float) $amounts['discount_amount'], 2);
                $gstAmount = round(($grossSubtotal * $gstPercentage) / 100, 2);
                $grandTotal = round($grossSubtotal - $discountAmount + $gstAmount, 2);
            }
        }

        if ($grossSubtotal <= 0) {
            $grossSubtotal = round(max(0, $grandTotal - $gstAmount + $discountAmount), 2);
        }
        $taxableAmount = round(max(0, $grossSubtotal - $discountAmount), 2);
        $subtotal = $grossSubtotal;

        return [
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'taxable_amount' => $taxableAmount,
            'gst_percentage' => $gstPercentage,
            'gst_amount' => $gstAmount,
            'grand_total' => $grandTotal,
            'total_invoice_amount' => $grandTotal,
            'deducted_amount' => $grandTotal,
            'credits_deducted' => $grandTotal,
            'credits_used' => $grandTotal,
        ];
    }

    public function assignCartCustomer(Cart $cart, ?int $customerId): void
    {
        if ($customerId === null || $customerId <= 0) {
            return;
        }

        $cart->customer_id = $customerId;
        $cart->save();
    }

    public function assignCartDomain(Cart $cart, mixed $domainName): void
    {
        $normalized = OrderDomainHelper::normalizeStored($domainName);
        if ($normalized === '') {
            return;
        }

        $cart->domain_name = $normalized;
        $cart->save();
    }

    public function assignCartTransferDomain(Cart $cart, ?string $transferDomain): void
    {
        if ($transferDomain === null || trim($transferDomain) === '') {
            return;
        }

        $normalized = strtolower(trim($transferDomain));
        if (in_array($normalized, ['1', 'true'], true)) {
            $normalized = 'yes';
        } elseif (in_array($normalized, ['0', 'false'], true)) {
            $normalized = 'no';
        }
        if (!in_array($normalized, ['yes', 'no'], true)) {
            return;
        }

        $cart->transfer_domain = $normalized;
        $cart->save();
    }

    /**
     * When cart_items count is zero, reset cart-level metadata fields to NULL.
     */
    public function clearCartRelatedFieldsWhenEmpty(Cart $cart): Cart
    {
        $itemCount = CartItem::query()->where('cart_id', $cart->id)->count();
        if ($itemCount > 0) {
            return $cart;
        }

        $cart->customer_id = null;
        $cart->provider_id = null;
        $cart->domain_name = null;
        $cart->transfer_domain = null;
        $cart->discount_amount = 0;
        $cart->coupen = null;

        $cart->save();

        return $cart->fresh(['items.plan', 'customer']);
    }

    /**
     * @return list<string>
     */
    public function cartDomainNameToArray(?Cart $cart): array
    {
        if (!$cart) {
            return [];
        }

        $normalized = OrderDomainHelper::normalizeStored($cart->domain_name ?? null);
        if ($normalized === '') {
            return [];
        }

        return $this->dedupeDomainSegments(array_values(array_filter(
            array_map('trim', explode(',', $normalized)),
            static fn (string $value): bool => $value !== '' && strcasecmp($value, 'Array') !== 0
        )));
    }

    public function cartDomainNameString(?Cart $cart): string
    {
        return implode(',', $this->dedupeDomainSegments($this->cartDomainNameToArray($cart)));
    }

    /**
     * @param  list<string>  $segments
     * @return list<string>
     */
    private function dedupeDomainSegments(array $segments): array
    {
        $seen = [];
        $unique = [];
        foreach ($segments as $segment) {
            $key = strtolower(trim((string) $segment));
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $key;
        }

        return $unique;
    }

    public function cartHasMultipleDomains(?Cart $cart): bool
    {
        return count($this->cartDomainNameToArray($cart)) > 1;
    }

    public function cartTransferDomain(?Cart $cart): ?string
    {
        if (!$cart) {
            return null;
        }

        return $cart->transfer_domain ?? null;
    }

    /**
     * @return array{wallet_used: float, payable_after_wallet: float}
     */
    public function walletApplication(float $grandTotal, float $walletBalance): array
    {
        if ($walletBalance <= 0) {
            return ['wallet_used' => 0.0, 'payable_after_wallet' => round($grandTotal, 2)];
        }

        if ($walletBalance >= $grandTotal) {
            return ['wallet_used' => round($grandTotal, 2), 'payable_after_wallet' => 0.0];
        }

        return [
            'wallet_used' => round($walletBalance, 2),
            'payable_after_wallet' => round($grandTotal - $walletBalance, 2),
        ];
    }

    public function upsertStandardLine(
        Cart $cart,
        Plan $plan,
        int $licenses,
        ?int $customerId = null,
        mixed $domainName = null,
        ?string $transferDomain = null
    ): CartItem {
        $period = $this->resolveSubscriptionPeriod($plan);
        $startDate = $period['start']->toDateString();
        $endDate = $period['end']->toDateString();
        $amounts = $this->calculateLineAmounts($plan, $licenses, (float) ($cart->gst_percentage ?? 18));

        $item = CartItem::query()->firstOrNew([
            'cart_id' => $cart->id,
            'plan_id' => $plan->id,
            'subscription_start_date' => $startDate,
        ]);

        if ($item->exists) {
            $item->licenses = (int) $item->licenses + $licenses;
        } else {
            $item->licenses = $licenses;
            $item->added_at = now();
        }

        $item->unit_price = $amounts['unit_price'];
        $item->subscription_end_date = $endDate;
        $item->grand_total = $this->lineGrossTotal((float) $item->unit_price, (int) $item->licenses);
        $item->save();

        $this->assignCartDomain($cart, $domainName);
        $this->assignCartTransferDomain($cart, $transferDomain);
        $this->assignCartCustomer($cart, $customerId);

        if (empty($cart->provider_id) && !empty($plan->provider_id)) {
            $cart->provider_id = (int) $plan->provider_id;
            $cart->save();
        }

        $this->recalculateCartTotals((int) $cart->id);

        return $item->fresh(['plan', 'cart.customer']);
    }

    public function createUpgradeLine(
        Cart $cart,
        Plan $plan,
        ?int $customerId = null,
        int $licenses = 1
    ): CartItem {
        $period = $this->resolveSubscriptionPeriod($plan);
        $amounts = $this->calculateLineAmounts($plan, $licenses, (float) ($cart->gst_percentage ?? 18));

        $item = CartItem::create([
            'cart_id' => $cart->id,
            'plan_id' => $plan->id,
            'licenses' => $licenses,
            'unit_price' => $amounts['unit_price'],
            'grand_total' => $this->lineGrossTotal((float) $amounts['unit_price'], $licenses),
            'subscription_start_date' => $period['start']->toDateString(),
            'subscription_end_date' => $period['end']->toDateString(),
            'added_at' => now(),
        ]);

        $this->assignCartCustomer($cart, $customerId);

        $cart->provider_id = (int) ($plan->provider_id ?? 0) ?: $cart->provider_id;
        $cart->save();

        $this->recalculateCartTotals((int) $cart->id);

        return $item->fresh(['plan', 'cart.customer']);
    }

    public function getCartByIdForPartner(int $cartId, int $partnerId, string $type = self::CART_TYPE_STANDARD): ?Cart
    {
        return Cart::query()
            ->with(['items.plan', 'customer'])
            ->where('id', $cartId)
            ->where('partner_id', $partnerId)
            ->ofType($type)
            ->first();
    }

    /**
     * Resolve parent cart: explicit cart_id, else reuse latest partner cart, else create once.
     */
    public function resolvePartnerCart(
        int $partnerId,
        string $type,
        ?int $cartId = null,
        ?int $providerId = null,
        ?string $coupon = null
    ): ?Cart {
        if ($cartId) {
            $cart = $this->getCartByIdForPartner($cartId, $partnerId, $type);
            if (!$cart) {
                return null;
            }

            $cart->status = self::CART_STATUS_ACTIVE;
            if ($providerId && empty($cart->provider_id)) {
                $cart->provider_id = $providerId;
            }
            if ($coupon !== null && $coupon !== '') {
                $cart->coupen = $coupon;
            }
            if ($cart->isDirty()) {
                $cart->save();
            }

            return $cart->fresh(['items.plan', 'customer']);
        }

        return $this->findOrCreateActiveCart($partnerId, $type, $providerId, $coupon);
    }

    /**
     * Parent cart (carts table) with all nested cart_items.
     *
     * @return array<string, mixed>
     */
    public function formatCartWithItemsResponse(Cart $cart, Partner $partner, ?CartItem $addedItem = null): array
    {
        $cart->loadMissing(['items.plan', 'customer']);

        // Parent carts.id (carts table has no cart_id column)
        $mainCartId = (int) $cart->id;

        $walletBalance = (float) ($partner->wallet_balance ?? 0);
        $grandTotal = (float) ($cart->grand_total ?? 0);
        $wallet = $this->walletApplication($grandTotal, $walletBalance);

        $payload = [
            // 'cart_id' => $mainCartId,
            'main_cart_id' => $mainCartId,
            'session_id' => $cart->session_id,
            'partner_id' => $cart->partner_id,
            'customer_id' => $cart->customer_id,
            'provider_id' => $cart->provider_id,
            'domain_name' => $this->cartDomainNameToArray($cart),
            'transfer_domain' => $cart->transfer_domain ?? null,
            'type' => $cart->type,
            'coupen' => $cart->coupen,
            'status' => $cart->status ?? self::CART_STATUS_ACTIVE,
            'currency' => $cart->currency ?? 'INR',
            'subtotal' => round(max(0, $grandTotal - (float) ($cart->gst_amount ?? 0) + (float) ($cart->discount_amount ?? 0)), 2),
            'discount_amount' => round((float) ($cart->discount_amount ?? 0), 2),
            'gst_percentage' => round((float) ($cart->gst_percentage ?? 18), 2),
            'gst_amount' => round((float) ($cart->gst_amount ?? 0), 2),
            'grand_total' => round($grandTotal, 2),
            'wallet_balance' => $walletBalance,
            'wallet_used' => $wallet['wallet_used'],
            'payable_after_wallet' => $wallet['payable_after_wallet'],
            'created_at' => optional($cart->created_at)->format('Y-m-d H:i:s'),
            'updated_at' => optional($cart->updated_at)->format('Y-m-d H:i:s'),
            'items' => $this->formatStandardCartDetails($cart, $walletBalance),
        ];

        if ($addedItem) {
            $addedItem->loadMissing(['plan', 'cart.customer']);
            $addedPlan = $addedItem->plan;
            if ($addedPlan) {
                $payload['added_item'] = array_merge(
                    $this->formatAddToCartResponse($addedItem, $addedPlan, $partner),
                    ['customer_limit' => $addedPlan->customer_limit ?? null]
                );
            }
        }

        return $payload;
    }

    /**
     * Build single line addToCart fields (used inside added_item).
     */
    public function formatAddToCartResponse(CartItem $item, Plan $plan, Partner $partner): array
    {
        $cart = $item->cart;
        $licenses = (int) $item->licenses;
        $amounts = $this->calculateLineAmounts($plan, $licenses, (float) ($cart->gst_percentage ?? 18), 0);
        $lineGross = $this->lineGrossTotal((float) $amounts['unit_price'], $licenses);
        $walletBalance = (float) ($partner->wallet_balance ?? 0);
        $cartGrand = round((float) ($cart->grand_total ?? 0), 2);
        $wallet = $this->walletApplication($cartGrand > 0 ? $cartGrand : $lineGross, $walletBalance);

        return [
            'item_id' => $item->id,
            'cart_id' => $item->id,
            'main_cart_id' => (int) $item->cart_id,
            'domain_name' => $this->cartDomainNameToArray($cart),
            'transfer_domain' => $cart->transfer_domain ?? null,
            'plan_name' => $plan->name,
            'provider_id' => (int) ($plan->provider_id ?? $cart->provider_id ?? 0),
            'licenses' => $licenses,
            'unit_price' => $amounts['unit_price'],
            'subscription_start_date' => optional($item->subscription_start_date)->toDateString(),
            'subscription_end_date' => optional($item->subscription_end_date)->toDateString(),
            'customer_limit' => $plan->customer_limit ?? null,
            'pricing' => [
                'subtotal' => $lineGross,
                'plan_discount_amount' => $amounts['plan_discount_amount'],
                'coupon_discount_amount' => 0,
                'discount_amount' => $amounts['plan_discount_amount'],
                'gst_amount' => 0,
                'wallet_balance' => $walletBalance,
                'wallet_used' => $wallet['wallet_used'],
                'grand_total' => $lineGross,
                'payable_after_wallet' => $wallet['payable_after_wallet'],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function formatStandardCartDetails(Cart $cart, float $walletBalance): array
    {

        $cart->loadMissing('customer');

        $couponPercent = $this->resolveCouponDiscountPercent($cart->coupen ?? '');

        return $cart->items->map(function (CartItem $item) use ($walletBalance, $cart, $couponPercent) {
            $plan = $item->plan;
            if (!$plan instanceof Plan) {
                return [];
            }
            $customer = $cart->customer;
            $licenses = (int) ($item->licenses ?? 1);
            $taxPercentage = (float) ($item->cart->gst_percentage ?? 18);
            $planPrice = (float) ($item->unit_price ?? ($plan->price ?? 0));
            $discount = (float) ($plan->discount ?? 0);
            $subtotal = $this->lineGrossTotal($planPrice, $licenses);
            $discountAmount = $discount > 0 ? round(($subtotal * $discount) / 100, 2) : 0.0;
            $cartDiscountAmount = (float) ($cart->discount_amount ?? 0);
            $taxAmount = 0.0;
            $totalAmount = (float) ($item->grand_total ?? $subtotal);

            $companyName = ($customer && !empty($customer->company_name)) ? $customer->company_name : null;

            $subscriptionDates = $this->resolveLineSubscriptionDatesForApi($item, $plan);

            $domainNameValue = $this->cartDomainNameToArray($cart);

            $planName = $plan->name ?? null;
            $planData = array_merge($plan->toArray(), $this->billingCycleApiPayload($plan));

            return array_merge([
                'item_id' => $item->id,
                'cart_id' => $item->id,
                'main_cart_id' => (int) $item->cart_id,
                'partner_id' => $item->cart->partner_id,
                'customer_id' => $cart->customer_id,
                'company_name' => $companyName,
                'plan_id' => $item->plan_id,
                'plan_name' => $planName,
                'domain_name' => $domainNameValue,
                'transfer_domain' => $cart->transfer_domain ?? null,
                'customer_limit' => $plan->customer_limit ?? null,
                'licenses' => $licenses,
                'price_per_unit' => $planPrice,
                'discount' => $discount,
                'coupon_discount_percent' => $couponPercent,
                'discount_amount' => round($discountAmount, 2),
                'cart_discount_amount' => round($cartDiscountAmount, 2),
                'tax_percentage' => $taxPercentage,
                'tax_amount' => round($taxAmount, 2),
                'subtotal' => round($subtotal, 2),
                'total_amount' => round($totalAmount, 2),
                'limited_offer' => 0,
                'is_active' => true,
                'status' => true,
                'created_at' => optional($item->added_at ?? $item->cart->created_at)->format('d-m-Y'),
                'updated_at' => optional($item->cart->updated_at)->format('Y-m-d'),
                'plan' => $planData,
                'wallet_balance' => $walletBalance,
            ], $subscriptionDates);
        })->filter()->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function formatUpgradeCartDetails(Cart $cart, float $walletBalance): array
    {
        $providerId = (int) ($cart->provider_id ?? 0);
        if ($providerId === 0) {
            $firstItem = $cart->items->first();
            if ($firstItem && $firstItem->plan) {
                $providerId = (int) ($firstItem->plan->provider_id ?? 0);
            }
        }

        $cart->loadMissing('customer');

        return $cart->items->map(function (CartItem $item) use ($walletBalance, $providerId, $cart) {
            $plan = $item->plan;
            if (!$plan instanceof Plan) {
                return [];
            }
            $customer = $cart->customer;

            $planPrice = (float) ($item->unit_price ?? ($plan->price ?? 0));
            $licenses = (int) ($item->licenses ?? 1);
            $discount = (float) ($plan->discount ?? 0);
            $taxPercentage = (float) ($item->cart->gst_percentage ?? 18);

            $subtotal = $planPrice * $licenses;
            $discountAmount = $discount > 0 ? ($subtotal * $discount) / 100 : 0;
            $afterDiscount = $subtotal - $discountAmount;
            $taxAmount = ($afterDiscount * $taxPercentage) / 100;
            $totalAmount = $afterDiscount + $taxAmount;

            $companyName = ($customer && !empty($customer->company_name)) ? $customer->company_name : null;
            $subscriptionDates = $this->resolveLineSubscriptionDatesForApi($item, $plan);

            return array_merge([
                'item_id' => $item->id,
                'cart_id' => $item->id,
                'main_cart_id' => (int) $item->cart_id,
                'provider_id' => $providerId,
                'type' => $item->cart->type,
                'plan_name' => $plan->name ?? null,
                'customer_id' => $cart->customer_id,
                'company_name' => $companyName,
                'licenses' => $licenses,
                'unit_price' => $planPrice,
                'subtotal' => round((float) ($cart->subtotal ?? $subtotal), 2),
                'discount_amount' => round((float) ($cart->discount_amount ?? $discountAmount), 2),
                'gst_amount' => round((float) ($cart->gst_amount ?? $taxAmount), 2),
                'grand_total' => round((float) ($cart->grand_total ?? $totalAmount), 2),
                'remaining_value' => round((float) ($cart->pro_rata_adjustment ?? 0), 2),
                'remaining_credit' => round((float) ($cart->pro_rata_adjustment ?? 0), 2),
                'pro_rata_adjustment' => round((float) ($cart->pro_rata_adjustment ?? 0), 2),
                'domain_name' => $this->cartDomainNameToArray($cart),
                'wallet_balance' => $walletBalance,
                'created_at' => optional($item->cart->created_at)->format('Y-m-d H:i:s'),
                'updated_at' => optional($item->cart->updated_at)->format('Y-m-d H:i:s'),
            ], $subscriptionDates);
        })->filter()->values()->all();
    }

    public function getActiveCartWithItems(int $partnerId, string $type): ?Cart
    {
        return Cart::query()
            ->with(['items.plan', 'customer'])
            ->ofType($type)
            ->where('partner_id', $partnerId)
            ->active()
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Latest partner cart that still has line items (standard → upgrade → any).
     */
    public function resolveCheckoutCartForPartner(int $partnerId): ?Cart
    {
        foreach ([self::CART_TYPE_STANDARD, self::CART_TYPE_UPGRADE, self::CART_TYPE_DOMAIN_TRANSFER] as $type) {
            $cart = $this->getActiveCartWithItems($partnerId, $type);
            if ($cart && $cart->items->isNotEmpty()) {
                return $cart;
            }
        }

        return Cart::query()
            ->with(['items.plan', 'customer'])
            ->where('partner_id', $partnerId)
            ->whereHas('items')
            ->orderByDesc('id')
            ->first();
    }

    public function orderCategoryFromCartType(Cart $cart): string
    {
        $type = strtolower(trim((string) ($cart->type ?? self::CART_TYPE_STANDARD)));
        $itemType = strtolower(trim((string) ($cart->item_type ?? '')));

        if ($type === self::CART_TYPE_LICENSE_ADD || $type === 'license_add' || $type === 'addon' || str_contains($itemType, 'mode:license_add')) {
            return 'addon';
        }

        return match ($type) {
            self::CART_TYPE_UPGRADE, 'upgrade' => 'upgrade',
            self::CART_TYPE_DOWNGRADE, 'downgrade' => 'downgrade',
            self::CART_TYPE_RENEW, 'renew', 'renewal' => 'renewal',
            self::CART_TYPE_DOMAIN_TRANSFER, 'domain_transfer' => 'new',
            default => 'new',
        };
    }

    /**
     * Resolve API cart_id to a cart_items row.
     */
    public function resolveLineByCartId(int $cartId): ?CartItem
    {
        return CartItem::with(['cart.partner', 'cart.customer', 'cart', 'plan'])->find($cartId);
    }

    /**
     * Delete cart line(s) and refresh the parent carts row (never delete parent for standard flow).
     *
     * @return array{deleted: bool, parent_deleted: bool, parent_cart_id: int|null, item_id: int|null, cart: ?Cart}
     */
    public function deleteCartItemOrParent(int $cartId): array
    {
        return DB::transaction(function () use ($cartId) {
            $outcome = [
                'deleted' => false,
                'parent_deleted' => false,
                'parent_cart_id' => null,
                'item_id' => null,
                'cart' => null,
            ];

            $item = CartItem::query()->find($cartId);

            if ($item) {
                $parentId = (int) $item->cart_id;
                $outcome['parent_cart_id'] = $parentId;
                $outcome['item_id'] = (int) $item->id;

                $item->delete();
                $outcome['deleted'] = true;

                $outcome['cart'] = $this->recalculateCartTotals($parentId);
                if ($outcome['cart']) {
                    $outcome['cart'] = $this->clearCartRelatedFieldsWhenEmpty($outcome['cart']);
                }

                return $outcome;
            }

            $parent = Cart::query()->find($cartId);
            if (!$parent) {
                return $outcome;
            }

            $outcome['parent_cart_id'] = (int) $parent->id;
            $outcome['deleted'] = true;

            CartItem::query()->where('cart_id', $parent->id)->delete();
            $outcome['cart'] = $this->recalculateCartTotals((int) $parent->id);
            if ($outcome['cart']) {
                $outcome['cart'] = $this->clearCartRelatedFieldsWhenEmpty($outcome['cart']);
            }

            return $outcome;
        });
    }

    public function migrateLegacyFlatCartsIfNeeded(int $partnerId, string $type): void
    {
        $legacyTypes = $type === self::CART_TYPE_STANDARD ? self::CART_LEGACY_STANDARD_TYPES : [$type];
        $flatRows = Cart::query()
            ->where('partner_id', $partnerId)
            ->whereIn('type', $legacyTypes)
            ->whereDoesntHave('items')
            ->whereNotNull('plan_id')
            ->orderBy('id')
            ->get();

        if ($flatRows->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($flatRows, $type) {
            $parent = Cart::create([
                'session_id' => (string) Str::uuid(),
                'partner_id' => $flatRows->first()->partner_id,
                'provider_id' => $flatRows->first()->provider_id,
                'type' => $type === self::CART_TYPE_STANDARD ? self::CART_TYPE_STANDARD : $type,
                'status' => self::CART_STATUS_ACTIVE,
                'currency' => 'INR',
                'coupen' => $flatRows->first()->coupen,
                'gst_percentage' => $flatRows->first()->gst_percentage ?? 18,
            ]);

            if (!empty($flatRows->first()->customer_id)) {
                $this->assignCartCustomer($parent, (int) $flatRows->first()->customer_id);
            }

            $firstRow = $flatRows->first();
            if ($firstRow && !empty($firstRow->domain_name)) {
                $parent->domain_name = $firstRow->domain_name;
                $parent->save();
            }

            foreach ($flatRows as $row) {
                $start = Carbon::parse($row->subscription_start_date ?? now());
                $plan = !empty($row->plan_id) ? Plan::query()->find((int) $row->plan_id) : null;
                $end = $plan instanceof Plan
                    ? $this->computeSubscriptionEnd($start, $plan)
                    : $start->copy()->addYear()->subDay();

                CartItem::create([
                    'cart_id' => $parent->id,
                    'plan_id' => $row->plan_id,
                    'licenses' => $row->licenses ?? 1,
                    'unit_price' => $row->unit_price ?? 0,
                    'grand_total' => $row->grand_total ?? 0,
                    'subscription_start_date' => $start->toDateString(),
                    'subscription_end_date' => $end->toDateString(),
                    'added_at' => $row->created_at ?? now(),
                ]);
                if (empty($parent->customer_id) && !empty($row->customer_id)) {
                    $this->assignCartCustomer($parent, (int) $row->customer_id);
                }
                $row->delete();
            }

            $this->recalculateCartTotals((int) $parent->id);
        });
    }

    public function cartPendingAadhaarCacheKey(int $cartId): string
    {
        return 'cart_pending_aadhaar_' . $cartId;
    }

    public function markCartPendingAadhaar(int $cartId, string $flow = 'new'): void
    {
        if ($cartId <= 0) {
            return;
        }

        $partnerId = (int) (Cart::query()->whereKey($cartId)->value('partner_id') ?? 0);
        $this->cancelOpenDraftOrdersForCart($cartId, $partnerId);

        Cache::put($this->cartPendingAadhaarCacheKey($cartId), [
            'cart_id' => $cartId,
            'flow' => strtolower(trim($flow)) ?: 'new',
            'marked_at' => now()->toIso8601String(),
        ], now()->addDays(7));

        Cart::query()->whereKey($cartId)->update(['status' => self::CART_STATUS_ACTIVE]);

        // Clear carts.order_id only when it points at a draft (not parent subscription for renew/upgrade).
        if (Schema::connection('pgsql_billing')->hasColumn('carts', 'order_id')) {
            $linkedOrderId = (int) (Cart::query()->whereKey($cartId)->value('order_id') ?? 0);
            if ($linkedOrderId > 0) {
                $linkedStatus = strtolower(trim((string) (
                    Order::query()->whereKey($linkedOrderId)->value('status') ?? ''
                )));
                if ($linkedStatus === 'draft') {
                    Cart::query()->whereKey($cartId)->update(['order_id' => null]);
                }
            }
        }
    }

    /**
     * Cancel leftover draft orders for this cart so eSign-pending stays cart-only (PND temp ref).
     * Does not clear pending-aadhaar / PND flags.
     */
    public function cancelOpenDraftOrdersForCart(int $cartId, int $partnerId = 0): void
    {
        if ($cartId <= 0) {
            return;
        }

        if ($partnerId <= 0) {
            $partnerId = (int) (Cart::query()->whereKey($cartId)->value('partner_id') ?? 0);
        }

        $draftIds = [];
        $cached = (int) Cache::get('cart_draft_order_' . $cartId, 0);
        if ($cached > 0) {
            $draftIds[] = $cached;
        }
        $cachedMany = Cache::get('cart_draft_orders_' . $cartId);
        if (is_array($cachedMany)) {
            foreach ($cachedMany as $oid) {
                $oid = (int) $oid;
                if ($oid > 0) {
                    $draftIds[] = $oid;
                }
            }
        }

        if (Schema::connection('pgsql_billing')->hasColumn('orders', 'cart_id')) {
            $query = Order::query()
                ->where('cart_id', $cartId)
                ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = 'draft'");
            if ($partnerId > 0) {
                $query->where('partner_id', $partnerId);
            }
            foreach ($query->pluck('id') as $oid) {
                $draftIds[] = (int) $oid;
            }
        }

        foreach (array_values(array_unique(array_filter($draftIds))) as $draftId) {
            $status = strtolower(trim((string) (Order::query()->whereKey($draftId)->value('status') ?? '')));
            if ($status === 'draft') {
                Order::query()->whereKey($draftId)->update([
                    'status' => 'cancelled',
                    'updated_at' => now(),
                ]);
            }
            Cache::forget('draft_order_cart_' . $draftId);
            Cache::forget('draft_order_plan_ids_' . $draftId);
        }

        Cache::forget('cart_draft_order_' . $cartId);
        Cache::forget('cart_draft_orders_' . $cartId);
    }

    /**
     * @return array{cart_id: int, flow: string, marked_at?: string}|null
     */
    public function getCartPendingAadhaarMeta(int $cartId): ?array
    {
        if ($cartId <= 0) {
            return null;
        }

        $data = Cache::get($this->cartPendingAadhaarCacheKey($cartId));
        if (!is_array($data) || empty($data['cart_id'])) {
            // Fallback: PND ref still mapped ⇒ treat as pending eSign.
            $ref = Cache::get('cart_pending_esign_ref_' . $cartId);
            if (!is_string($ref) || !$this->isPendingEsignRef($ref)) {
                return null;
            }

            return [
                'cart_id' => $cartId,
                'flow' => 'new',
                'marked_at' => null,
            ];
        }

        return [
            'cart_id' => (int) $data['cart_id'],
            'flow' => strtolower(trim((string) ($data['flow'] ?? 'new'))) ?: 'new',
            'marked_at' => isset($data['marked_at']) ? (string) $data['marked_at'] : null,
        ];
    }

    public function getCartPendingAadhaarFlow(int $cartId): string
    {
        $meta = $this->getCartPendingAadhaarMeta($cartId);

        return strtolower(trim((string) ($meta['flow'] ?? 'new'))) ?: 'new';
    }

    /** Pending eSign reference prefix — distinct from real order numbers (TNX/RN/etc.). */
    public const PENDING_ESIGN_REF_PREFIX = 'PND';

    public function clearCartPendingAadhaar(int $cartId): void
    {
        if ($cartId <= 0) {
            return;
        }

        Cache::forget($this->cartPendingAadhaarCacheKey($cartId));
        $this->clearCartPoMeta($cartId);
        $this->clearCartPendingEsignRef($cartId);
    }

    public function isPendingEsignRef(string $ref): bool
    {
        $ref = strtoupper(trim($ref));

        return $ref !== '' && (bool) preg_match('/^' . self::PENDING_ESIGN_REF_PREFIX . '\d{6,}$/', $ref);
    }

    public function issueCartPendingEsignRef(int $cartId, int $partnerId): string
    {
        if ($cartId <= 0) {
            return '';
        }

        $cartKey = 'cart_pending_esign_ref_' . $cartId;
        $existing = Cache::get($cartKey);
        if (is_string($existing) && $this->isPendingEsignRef($existing)) {
            Cache::put($this->pendingEsignRefCacheKey($existing), [
                'cart_id' => $cartId,
                'partner_id' => $partnerId,
                'issued_at' => now()->toIso8601String(),
            ], now()->addDays(7));

            return strtoupper($existing);
        }

        $ref = $this->nextPendingEsignRefCode();
        Cache::put($cartKey, $ref, now()->addDays(7));
        Cache::put($this->pendingEsignRefCacheKey($ref), [
            'cart_id' => $cartId,
            'partner_id' => $partnerId,
            'issued_at' => now()->toIso8601String(),
        ], now()->addDays(7));

        return $ref;
    }

    /**
     * @return array{cart_id: int, partner_id: int, issued_at?: string}|null
     */
    public function resolvePendingEsignRef(string $ref): ?array
    {
        if (!$this->isPendingEsignRef($ref)) {
            return null;
        }

        $data = Cache::get($this->pendingEsignRefCacheKey($ref));
        if (!is_array($data) || empty($data['cart_id'])) {
            return null;
        }

        return [
            'cart_id' => (int) $data['cart_id'],
            'partner_id' => (int) ($data['partner_id'] ?? 0),
            'issued_at' => isset($data['issued_at']) ? (string) $data['issued_at'] : null,
        ];
    }

    public function clearCartPendingEsignRef(int $cartId): void
    {
        if ($cartId <= 0) {
            return;
        }

        $ref = Cache::get('cart_pending_esign_ref_' . $cartId);
        if (is_string($ref) && $ref !== '') {
            Cache::forget($this->pendingEsignRefCacheKey($ref));
        }
        Cache::forget('cart_pending_esign_ref_' . $cartId);
    }

    public function clearCartPendingEsignRefByRef(string $ref): void
    {
        if (!$this->isPendingEsignRef($ref)) {
            return;
        }

        $resolved = $this->resolvePendingEsignRef($ref);
        Cache::forget($this->pendingEsignRefCacheKey($ref));
        if ($resolved !== null && (int) ($resolved['cart_id'] ?? 0) > 0) {
            Cache::forget('cart_pending_esign_ref_' . (int) $resolved['cart_id']);
        }
    }

    private function pendingEsignRefCacheKey(string $ref): string
    {
        return 'pending_esign_ref_' . strtoupper(trim($ref));
    }

    private function nextPendingEsignRefCode(): string
    {
        $seqKey = 'pending_esign_ref_seq';
        $seq = (int) Cache::get($seqKey, 0) + 1;
        Cache::put($seqKey, $seq, now()->addYears(1));

        return self::PENDING_ESIGN_REF_PREFIX . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }

    public function saveCartPoMeta(int $cartId, string $poLink, string $poNumber, string $poMode = 'generated'): void
    {
        if ($cartId <= 0) {
            return;
        }

        Cache::put('cart_po_link_' . $cartId, trim($poLink), now()->addDays(7));
        Cache::put('cart_po_number_' . $cartId, trim($poNumber), now()->addDays(7));
        $mode = strtolower(trim($poMode)) === 'uploaded' ? 'uploaded' : 'generated';
        Cache::put('cart_po_mode_' . $cartId, $mode, now()->addDays(7));
    }

    /**
     * @return array{po_link: string, po_number: string, po_mode: string}|null
     */
    public function getCartPoMeta(int $cartId): ?array
    {
        if ($cartId <= 0) {
            return null;
        }

        $poLink = trim((string) Cache::get('cart_po_link_' . $cartId, ''));
        $poNumber = trim((string) Cache::get('cart_po_number_' . $cartId, ''));
        $poMode = strtolower(trim((string) Cache::get('cart_po_mode_' . $cartId, 'generated')));
        if (!in_array($poMode, ['generated', 'uploaded'], true)) {
            $poMode = 'generated';
        }

        if ($poLink === '' && $poNumber === '') {
            return null;
        }

        return [
            'po_link' => $poLink,
            'po_number' => $poNumber,
            'po_mode' => $poMode,
        ];
    }

    public function clearCartPoMeta(int $cartId): void
    {
        if ($cartId <= 0) {
            return;
        }

        Cache::forget('cart_po_link_' . $cartId);
        Cache::forget('cart_po_number_' . $cartId);
        Cache::forget('cart_po_mode_' . $cartId);
    }

    public function isCartPendingAadhaar(int $cartId): bool
    {
        if ($cartId <= 0) {
            return false;
        }

        if (Cache::has($this->cartPendingAadhaarCacheKey($cartId))) {
            return true;
        }

        // Durable fallback when pending flag was lost but temp PND ref remains.
        $ref = Cache::get('cart_pending_esign_ref_' . $cartId);

        return is_string($ref) && $this->isPendingEsignRef($ref);
    }
}
