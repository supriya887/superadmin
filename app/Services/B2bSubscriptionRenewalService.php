<?php

namespace App\Services;

use App\Models\AccountMaster;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPoDetail;
use App\Models\Partner;
use App\Models\User;
use App\Models\Plan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Support\GstSplit;
use App\Support\OrderPrefixResolver;

class B2bSubscriptionRenewalService
{
    public function __construct(
        private RenewalService $renewalService,
        private InvoiceService $invoiceService,
    ) {}

    /**
     * @param  array{
     *   partner_id:int,
     *   subscription_id:int,
     *   quantity?:int,
     *   aadhaar_number?:string|null,
     *   main_cart_id?:int|null,
     *   cart_id?:int|null,
     *   subtotal?:float|null,
     *   gst_amount?:float|null,
     *   payable_amount?:float|null
     * }  $input
     * @return array{order:Order, summary:array<string,mixed>, available_credits:float}
     */
    public function renew(array $input): array
    {
        $partnerId = (int) $input['partner_id'];
        $orderId = (int) $input['subscription_id'];
        
        // ✅ Get quantity from input or from order_items
        $qty = (int) ($input['quantity'] ?? 0);
        if ($qty <= 0) {
            $qty = $this->getQuantityFromOrderItems($orderId);
        }
        $qty = max(1, $qty);

        $partner = Partner::query()->findOrFail($partnerId);
        $baseOrder = $this->resolveBaseOrder($orderId, $partnerId);

        $decreaseGuard = app(LicenseDecreaseRequestService::class)->guardRenewDecrease(
            $partnerId,
            (int) $baseOrder->id,
            $qty,
            isset($input['cart_id']) ? (int) $input['cart_id'] : null,
            !empty($baseOrder->plan_id) ? (int) $baseOrder->plan_id : null
        );
        if (!empty($decreaseGuard['blocked'])) {
            $payload = $decreaseGuard['payload'] ?? [];
            throw ValidationException::withMessages([
                'quantity' => [(string) ($payload['message'] ?? 'License decrease requires SuperAdmin approval.')],
            ]);
        }

        $plan = Plan::query()->find($baseOrder->plan_id);
        if ($plan) {
            $limit = (int) ($plan->customer_limit ?? 0);
            if ($limit > 0 && $qty > $limit) {
                throw ValidationException::withMessages([
                    'quantity' => 'Licenses cannot exceed plan limit of ' . $limit . '.',
                ]);
            }
        }

        $summary = $this->calculateRenewalSummary($baseOrder, $qty);
        $summary = $this->mergeSummaryFromCart($summary, $partnerId, $input);
        $qty = max(1, (int) ($summary['licenses'] ?? $qty));

        if (empty($input['skip_credit_check'])) {
            PartnerCreditService::assertB2bPurchaseAmount(
                $partnerId,
                (float) $summary['payable_amount']
            );
        }

        $availableCredits = $this->availableCredits($partnerId, $partner);

        $aadhaar = isset($input['aadhaar_number']) ? (string) $input['aadhaar_number'] : '';

        $newOrder = DB::connection('pgsql_billing')->transaction(function () use ($baseOrder, $plan, $qty, $summary, $partnerId, $aadhaar, $input) {
            $order = $this->buildRenewalOrder($baseOrder, $plan, $qty, $summary, $partnerId);
            if (!empty($input['force_pending'])) {
                $order->status = 'pending';
            }
            $order->save();

            $this->seedRenewalOrderItems((int) $order->id, $baseOrder, $summary);

            if ($aadhaar !== '') {
                $this->upsertAutoPoForOrder((int) $order->id, $aadhaar, (float) $summary['payable_amount']);
            }

            if (empty($input['skip_invoice'])) {
                $this->createRenewalInvoice($order, $baseOrder, $plan, $qty, $summary);
            }
            $walletPayload = $order->getAttributes();
            if (!empty($input['skip_credit_check'])) {
                $walletPayload['skip_credit_headroom_check'] = true;
            }
            PartnerCreditService::syncB2bOrderWalletLedger((int) $order->id, $walletPayload);

            return $order->fresh();
        });

        $renewPrefix = OrderPrefixResolver::resolve('renewal');
        $newOrderId = (int) ($newOrder->id ?? 0);
        $srNo = (int) ($newOrder->sr_no ?? 0);
        if ($newOrderId > 0 && $srNo > 0) {
            $renewOrderNo = $renewPrefix . str_pad((string) $srNo, 5, '0', STR_PAD_LEFT);
            if ((string) ($newOrder->order_no ?? '') !== $renewOrderNo) {
                Order::on('pgsql_billing')->whereKey($newOrderId)->update(['order_no' => $renewOrderNo]);
                $newOrder->order_no = $renewOrderNo;
            }
        }

        $baseOrderId = (int) $baseOrder->id;
        $basePlanIds = OrderItem::on('pgsql_billing')
            ->where('order_id', $baseOrderId)
            ->where('status', '!=', 'delete')
            ->whereNotNull('plan_id')
            ->pluck('plan_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();
        if ($basePlanIds->isEmpty() && (int) ($baseOrder->plan_id ?? 0) > 0) {
            $basePlanIds = collect([(int) $baseOrder->plan_id]);
        }
        $renewalIds = Order::on('pgsql_billing')
            ->where('parent_order_id', $baseOrderId)
            ->whereIn('order_category', ['renewal', 'renew'])
            ->pluck('id');
        $renewedPlanIds = OrderItem::on('pgsql_billing')
            ->whereIn('order_id', $renewalIds)
            ->whereNotNull('plan_id')
            ->pluck('plan_id')
            ->map(fn ($id) => (int) $id)
            ->unique();
        if ($basePlanIds->isNotEmpty() && $basePlanIds->every(fn ($pid) => $renewedPlanIds->contains($pid))) {
            Order::on('pgsql_billing')->whereKey($baseOrderId)->update([
                'order_category' => 'renewed',
                'status' => 'renewed',
                'updated_at' => now(),
            ]);
        }

        if (empty($input['skip_invoice'])) {
            $this->triggerSandboxEInvoice((int) $newOrder->id);
            $this->notifyCustomerRenewalSms($newOrder, $summary);
        }

        if (
            empty($input['force_pending'])
            && in_array(strtolower(trim((string) ($newOrder->status ?? ''))), ['completed', 'paid', 'success', 'active'], true)
        ) {
            try {
                app(OrderCancellationCreditNoteService::class)->issueForPlanChangeOrder((int) $newOrder->id);
            } catch (\Throwable $e) {
                Log::warning('renewal_plan_change_credit_note_failed', [
                    'order_id' => (int) $newOrder->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'order' => $newOrder,
            'summary' => $summary,
            'available_credits' => $availableCredits,
        ];
    }
    private function getQuantityFromOrderItems(int $orderId): int
{
    $quantity = OrderItem::on('pgsql_billing')
        ->where('order_id', $orderId)
        ->where(function ($q) {
            $q->whereNull('status')
                ->orWhereRaw("LOWER(TRIM(COALESCE(status, ''))) NOT IN ('delete', 'deleted', 'cancelled')");
        })
        ->sum('quantity');
        
    if ($quantity <= 0) {
        $order = Order::on('pgsql_billing')->find($orderId);
        $quantity = (int) ($order->quantity ?? 1);
    }
    
    return max(1, $quantity);
}

    /**
     * Send renewal confirmation SMS to the customer's mobile (DLT template 1707177650891627520).
     */
    private function notifyCustomerRenewalSms(Order $newOrder, array $summary): void
    {
        try {
            $customerId = (int) ($newOrder->user_id ?? 0);
            if ($customerId <= 0) {
                return;
            }

            $customer = Customer::query()->find($customerId);
            if (!$customer) {
                return;
            }

            $mobile = trim((string) ($customer->mobile ?? $customer->phone ?? ''));
            if ($mobile === '') {
                Log::warning('Renewal SMS skipped: customer has no mobile.', [
                    'order_id' => (int) $newOrder->id,
                    'customer_id' => $customerId,
                ]);

                return;
            }

            $validityDate = !empty($newOrder->subscription_end_date)
                ? Carbon::parse((string) $newOrder->subscription_end_date)->format('d M, Y')
                : Carbon::now()->addYear()->format('d M, Y');
            $amount = (float) ($newOrder->total_amount ?? $summary['payable_amount'] ?? 0);

            $sent = app(SmsService::class)->sendRenewPlan($mobile, $validityDate, $amount);
            if (!$sent) {
                Log::warning('Renewal customer SMS gateway returned failure.', [
                    'order_id' => (int) $newOrder->id,
                    'customer_id' => $customerId,
                    'mobile' => $mobile,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Renewal customer SMS failed.', [
                'order_id' => (int) ($newOrder->id ?? 0),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function resolveSubscriptionId(int $partnerId, ?int $subscriptionId, ?int $customerId, ?string $username): int
    {
        if ($subscriptionId) {
            return (int) $this->resolveBaseOrder((int) $subscriptionId, $partnerId)->id;
        }

        $customerQuery = Customer::query()->where('partner_id', $partnerId);
        if ($customerId) {
            $customerQuery->where('id', $customerId);
        } elseif ($username !== null && $username !== '') {
            $customerQuery->where(function ($q) use ($username) {
                $q->where('email', $username)->orWhere('mobile', $username);
            });
        } else {
            throw ValidationException::withMessages([
                'subscription_id' => 'Provide order_id, subscription_id, or customer_id.',
            ]);
        }

        $customer = $customerQuery->first();
        if (!$customer) {
            throw ValidationException::withMessages(['customer_id' => 'Customer not found for this partner.']);
        }

        $order = Order::on('pgsql_billing')
            ->where('user_id', (int) $customer->id)
            ->where('partner_id', $partnerId)
            ->where('order_type', 'b2b')
            ->where(function ($q) {
                $q->whereNull('order_category')
                    ->orWhereNotIn('order_category', ['renewal', 'renewed', 'upgrade', 'upgraded']);
            })
            ->where('status', 'completed')
            ->orderByDesc('id')
            ->first();

        if (!$order) {
            throw ValidationException::withMessages(['customer_id' => 'No active subscription found for this customer.']);
        }

        return (int) $order->id;
    }

    /**
     * @return array<string, mixed>
     */
    /**
 * @return array<string, mixed>
 */
public function calculateRenewalSummary(Order $baseOrder, int $qty): array
{
    // ✅ Explicitly use pgsql_product connection
    $plan = Plan::on('pgsql_product')->find($baseOrder->plan_id);
    
    // ✅ Get the actual unit price from order_items
    $unitPrice = $this->getUnitPriceFromOrderItems($baseOrder, $plan);
    
    $subtotal = round($unitPrice * max(1, $qty), 2);
    $gstAmount = $this->renewalService->calculateGst($subtotal);
    $payable = round($subtotal + $gstAmount, 2);

    $oldEnd = !empty($baseOrder->subscription_end_date)
        ? Carbon::parse((string) $baseOrder->subscription_end_date)->startOfDay()
        : null;
    $durationMonths = (int) ($baseOrder->duration ?? 12);
    if (is_string($baseOrder->duration ?? null) && preg_match('/\d+/', (string) $baseOrder->duration, $m)) {
        $durationMonths = max(1, (int) $m[0]);
    }

    $newStart = $oldEnd ? $oldEnd->copy()->addDay() : Carbon::now()->startOfDay();
    $newEnd = $plan
        ? app(PartnerCartService::class)->computeSubscriptionEnd($newStart, $plan)->startOfDay()
        : $this->renewalService->subscriptionEndDate($newStart, $baseOrder->duration ?? '12 month')->startOfDay();

    return [
        'plan_id' => (int) $baseOrder->plan_id,
        'plan_name' => (string) (optional($plan)->name ?? $baseOrder->plan_name ?? ''),
        'licenses' => max(1, $qty),
        'unit_price' => round($unitPrice, 2),
        'subtotal' => $subtotal,
        'gst_percentage' => RenewalService::GST_RATE_PERCENT,
        'gst_amount' => $gstAmount,
        'payable_amount' => $payable,
        'renew_from' => $oldEnd?->toDateString(),
        'subscription_start_date' => $newStart->toDateString(),
        'subscription_end_date' => $newEnd->toDateString(),
        'duration_months' => $durationMonths,
    ];
}

/**
 * Get the unit price from order_items for the specific plan
 */
private function getUnitPriceFromOrderItems(Order $baseOrder, ?Plan $plan): float
{
    $planId = (int) ($baseOrder->plan_id ?? 0);
    
    // ✅ First try to get unit price from order_items
    $orderItem = OrderItem::on('pgsql_billing')
        ->where('order_id', (int) $baseOrder->id)
        ->where(function ($q) {
            $q->whereNull('status')
                ->orWhereRaw("LOWER(TRIM(COALESCE(status, ''))) NOT IN ('delete', 'deleted', 'cancelled')");
        });
    
    if ($planId > 0) {
        $orderItem->where('plan_id', $planId);
    }
    
    $item = $orderItem->orderBy('id')->first();
    
    if ($item && (float) ($item->unit_price ?? 0) > 0) {
        return (float) $item->unit_price;
    }
    
    if ($item && (float) ($item->total_amount ?? 0) > 0 && (int) ($item->quantity ?? 0) > 0) {
        return round((float) $item->total_amount / (int) $item->quantity, 2);
    }
    
    $unitPrice = (float) ($baseOrder->price ?? 0);
    if ($unitPrice <= 0 && $plan) {
        $unitPrice = (float) ($plan->price ?? 0);
    }
    
    // ✅ If still 0, try to get from any order_item for this order
    if ($unitPrice <= 0) {
        $anyItem = OrderItem::on('pgsql_billing')
            ->where('order_id', (int) $baseOrder->id)
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereRaw("LOWER(TRIM(COALESCE(status, ''))) NOT IN ('delete', 'deleted', 'cancelled')");
            })
            ->orderBy('id')
            ->first();
            
        if ($anyItem && (float) ($anyItem->unit_price ?? 0) > 0) {
            $unitPrice = (float) $anyItem->unit_price;
        }
    }
    
    return $unitPrice;
}

    private function resolveBaseOrder(int $orderId, int $partnerId): Order
    {
        $order = Order::on('pgsql_billing')->where('id', $orderId)->first();
        if (!$order) {
            throw ValidationException::withMessages(['subscription_id' => 'Subscription order not found.']);
        }

        if ((int) ($order->partner_id ?? 0) !== $partnerId) {
            throw ValidationException::withMessages([
                'subscription_id' => 'Subscription does not belong to this partner.',
            ]);
        }

        $type = strtolower(trim((string) ($order->order_type ?? '')));
        if ($type !== '' && $type !== 'b2b') {
            throw ValidationException::withMessages([
                'subscription_id' => 'Only B2B partner subscription orders can be renewed.',
            ]);
        }

        $category = strtolower(trim((string) ($order->order_category ?? '')));
        if ($category === 'renewed') {
            throw ValidationException::withMessages([
                'subscription_id' => 'This subscription has already been renewed.',
            ]);
        }
        if ($category === 'upgraded') {
            throw ValidationException::withMessages([
                'subscription_id' => 'This subscription has already been upgraded.',
            ]);
        }
        if ($category === 'renewal') {
            if (!empty($order->parent_order_id)) {
                $parent = Order::on('pgsql_billing')->find((int) $order->parent_order_id);
                if ($parent && (int) ($parent->partner_id ?? 0) === $partnerId) {
                    return $parent;
                }
            }

            throw ValidationException::withMessages([
                'subscription_id' => 'Use the original subscription order id, not a renewal order.',
            ]);
        }

        return $order;
    }

    /** @param array<string,mixed> $input */
    private function mergeSummaryFromCart(array $summary, int $partnerId, array $input): array
    {
        $cartId = (int) ($input['main_cart_id'] ?? $input['cart_id'] ?? 0);
        $cart = null;
        if ($cartId > 0) {
            $cart = Cart::query()->where('id', $cartId)->where('partner_id', $partnerId)->first();
        }
        if (!$cart) {
            $cart = Cart::query()
                ->where('partner_id', $partnerId)
                ->where('status', Cart::STATUS_ACTIVE)
                ->orderByDesc('id')
                ->first();
        }
        if (!$cart) {
            return $summary;
        }

        $gstAmount = round((float) ($cart->gst_amount ?? 0), 2);
        $grandTotal = round((float) ($cart->grand_total ?? 0), 2);
        if ($grandTotal <= 0) {
            return $summary;
        }

        $subtotal = $gstAmount > 0
            ? round(max(0, $grandTotal - $gstAmount), 2)
            : round((float) ($summary['subtotal'] ?? 0), 2);

        $summary['subtotal'] = $subtotal;
        $summary['gst_amount'] = $gstAmount;
        $summary['payable_amount'] = $grandTotal;
        $summary['gst_percentage'] = round(
            (float) ($cart->gst_percentage ?? $summary['gst_percentage'] ?? RenewalService::GST_RATE_PERCENT),
            2
        );
        $summary['coupon_discount'] = round((float) ($cart->discount_amount ?? 0), 2);
        $summary['discount_amount'] = $summary['coupon_discount'];
        $summary['pro_rata_adjustment'] = round((float) ($cart->pro_rata_adjustment ?? 0), 2);

        $cartItems = CartItem::query()->where('cart_id', (int) $cart->id)->get();
        if ($cartItems->isNotEmpty()) {
            $summary['cart_plan_ids'] = $cartItems->pluck('plan_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();
            $firstItem = $cartItems->first();
            if ($firstItem && (int) ($firstItem->licenses ?? 0) > 0) {
                $summary['licenses'] = max(1, (int) $firstItem->licenses);
            }
        }

        return $summary;
    }

    private function availableCredits(int $partnerId, Partner $partner): float
    {
        return PartnerCreditService::remainingPurchaseAllowance($partnerId);
    }

    private function partnerCreditUsed(int $partnerId): float
    {
        return (float) Order::on('pgsql_billing')
            ->where('partner_id', $partnerId)
            ->where('order_type', 'b2b')
            ->sum('credit_used');
    }

    /** @param array<string,mixed> $summary */
    private function buildRenewalOrder(Order $baseOrder, ?Plan $plan, int $qty, array $summary, int $partnerId): Order
    {
        $newOrder = $baseOrder->replicate();
        $newOrder->setConnection('pgsql_billing');
        $newOrder->order_no = null;
        $newOrder->sr_no = null;

        $next = $this->nextOrderIdentifiers('renewal');
        $newOrder->order_no = $next['order_no'];
        $newOrder->sr_no = $next['sr_no'];

        $unitPrice = (float) $summary['unit_price'];
        $subtotal = (float) $summary['subtotal'];
        $gstAmount = (float) $summary['gst_amount'];
        $amount = (float) $summary['payable_amount'];

        $newOrder->partner_id = $partnerId;
        $newOrder->price = $unitPrice;
        $newOrder->quantity = $qty;
        $newOrder->subtotal = $subtotal;
        $newOrder->tax_amount = $gstAmount;
        $newOrder->gst_amount = $gstAmount;
        $newOrder->gst_rate = RenewalService::GST_RATE_PERCENT;
        $newOrder->apply_gst = true;
        $newOrder->total_amount = $amount;
        $newOrder->credit_used = $amount;
        $newOrder->order_type = 'b2b';
        $newOrder->order_category = 'renewal';
        $newOrder->status = 'completed';
        $newOrder->payment_method = $newOrder->payment_method ?: 'wallet';
        $newOrder->payment_mode = $newOrder->payment_mode ?: 'wallet';
        $newOrder->parent_order_id = (int) $baseOrder->id;
        $newOrder->subscription_start_date = $summary['subscription_start_date'];
        $newOrder->subscription_end_date = $summary['subscription_end_date'];
        $newOrder->renewal_at = now()->toDateString();
        $newOrder->razorpay_order_id = null;
        $newOrder->razorpay_payment_id = null;
        $newOrder->razorpay_signature = null;
        $newOrder->discount_amount = (float) ($summary['coupon_discount'] ?? $summary['discount_amount'] ?? 0);
        $newOrder->pro_rata_adjustment = (float) ($summary['pro_rata_adjustment'] ?? 0);
        $newOrder->created_at = now();
        $newOrder->updated_at = now();

        return $newOrder;
    }

    /**
     * Copy source subscription lines onto the renewal order (same licence/qty per plan).
     *
     * @param  array<string, mixed>  $summary
   
 */
private function seedRenewalOrderItems(int $newOrderId, Order $baseOrder, array $summary): void
{
    if ($newOrderId <= 0) {
        return;
    }

    $sourceRows = OrderItem::on('pgsql_billing')
        ->where('order_id', (int) $baseOrder->id)
        ->where('status', '!=', 'delete')
        ->orderBy('id')
        ->get();

    $cartPlanIds = $summary['cart_plan_ids'] ?? [];
    if (!empty($cartPlanIds)) {
        $sourceRows = $sourceRows
            ->filter(fn ($row) => in_array((int) ($row->plan_id ?? 0), $cartPlanIds, true))
            ->values();
    }

    OrderItem::on('pgsql_billing')
        ->where('order_id', $newOrderId)
        ->where('status', '!=', 'delete')
        ->update(['status' => 'delete', 'updated_at' => now()]);

    $renewQty = max(1, (int) ($summary['licenses'] ?? $baseOrder->quantity ?? 1));

    if ($sourceRows->isEmpty()) {
        $planId = !empty($cartPlanIds) ? (int) $cartPlanIds[0] : (int) ($baseOrder->plan_id ?? 0);
        $this->insertRenewalOrderItemRow(
            $newOrderId,
            $planId,
            (string) ($baseOrder->plan_name ?? 'Plan'),
            $renewQty,
            (float) ($summary['unit_price'] ?? $baseOrder->price ?? 0),
            null
        );

        return;
    }

    foreach ($sourceRows as $row) {
        $qty = !empty($cartPlanIds) ? $renewQty : max(1, (int) ($row->quantity ?? 1));
        $planId = (int) ($row->plan_id ?? $baseOrder->plan_id ?? 0);
        $itemName = (string) ($row->item_name ?? '');
        
        // ✅ Explicitly use pgsql_product connection
        if ($itemName === '' && $planId > 0) {
            $plan = Plan::on('pgsql_product')->find($planId);
            $itemName = (string) ($plan->name ?? 'Plan');
        }
        if ($itemName === '') {
            $itemName = (string) ($baseOrder->plan_name ?? 'Plan');
        }

        $unitPrice = (float) ($row->unit_price ?? 0);
        if ($unitPrice <= 0) {
            $unitPrice = (float) ($summary['unit_price'] ?? $baseOrder->price ?? 0);
        }
        // ✅ Explicitly use pgsql_product connection
        if ($unitPrice <= 0 && $planId > 0) {
            $plan = Plan::on('pgsql_product')->find($planId);
            $unitPrice = (float) ($plan->price ?? 0);
        }

        $variantId = isset($row->variant_id) ? (int) $row->variant_id : null;
        $this->insertRenewalOrderItemRow($newOrderId, $planId, $itemName, $qty, $unitPrice, $variantId);
    }
}

    private function insertRenewalOrderItemRow(
        int $orderId,
        int $planId,
        string $itemName,
        int $qty,
        float $unitPrice,
        ?int $variantId
    ): void {
        $lineTotal = round($unitPrice * max(1, $qty), 2);

        OrderItem::on('pgsql_billing')->create([
            'order_id' => $orderId,
            'plan_id' => $planId > 0 ? $planId : null,
            'variant_id' => $variantId,
            'item_name' => $itemName,
            'quantity' => $qty,
            'unit_price' => round($unitPrice, 2),
            'total_amount' => $lineTotal,
            'status' => 'active',
        ]);
    }

    /** @return array{order_no:string,sr_no:int} */
    private function nextOrderIdentifiers(?string $orderCategory = 'new'): array
    {
        $latestOrder = Order::on('pgsql_billing')
            ->select('sr_no')
            ->orderByDesc('sr_no')
            ->lockForUpdate()
            ->first();

        $nextSrNo = $latestOrder ? ((int) $latestOrder->sr_no + 1) : 1;

        $prefix = OrderPrefixResolver::resolve($orderCategory);
        $orderNo = $prefix . str_pad((string) $nextSrNo, 5, '0', STR_PAD_LEFT);

        while (Order::on('pgsql_billing')->where('order_no', $orderNo)->exists()) {
            $nextSrNo++;
            $orderNo = $prefix . str_pad((string) $nextSrNo, 5, '0', STR_PAD_LEFT);
        }

        return ['order_no' => $orderNo, 'sr_no' => $nextSrNo];
    }

    private function upsertAutoPoForOrder(int $orderId, string $aadhaar, float $amount): void
    {
        OrderPoDetail::on('pgsql_billing')->updateOrCreate(
            ['order_id' => $orderId],
            [
                'po_mode' => 'generated',
                'journey_status' => 'placed',
                'aadhar_verified' => 1,
                'aadhar_verified_at' => now(),
                'aadhaar_masked' => 'XXXXXXXX' . substr($aadhaar, -4),
                'aadhaar_ref_no' => 'AADH-' . strtoupper(Str::random(8)),
                'po_amount' => $amount,
            ]
        );
    }

    /** @param array<string,mixed > $summary */
    private function createRenewalInvoice(Order $newOrder, Order $baseOrder, ?Plan $plan, int $qty, array $summary): void
    {
        $gst = (float) $summary['gst_amount'];
        $customerGstin = trim((string) ($baseOrder->po_manual_gstin ?? $baseOrder->customer_gstin ?? ''));
        if ($customerGstin === '') {
            $customerGstin = (string) (Customer::query()->whereKey((int) $baseOrder->user_id)->value('gstin') ?? '');
        }
        if ($customerGstin === '') {
            $customerGstin = (string) (User::query()->whereKey((int) $baseOrder->user_id)->value('gstin') ?? '');
        }
        $split = GstSplit::split($gst, $customerGstin);
        $taxType = $split['tax_type'];
        $cgst = $split['cgst'];
        $sgst = $split['sgst'];
        $igst = $split['igst'];
        $planName = (string) (optional($plan)->name ?? $baseOrder->plan_name ?? 'Subscription');
        $taxable = round((float) ($summary['taxable_amount'] ?? $summary['subtotal'] ?? 0), 2);
        $payable = round((float) ($summary['payable_amount'] ?? 0), 2);

        $this->invoiceService->createInvoice([
            'cust_id' => (int) $baseOrder->user_id,
            'order_id' => (int) ($newOrder->id ?? 0),
            'partner_id' => (int) ($newOrder->partner_id ?? $baseOrder->partner_id ?? 0),
            'status' => 'pending',
            'action' => 'renew',
            'tax_type' => $taxType,
            'net_amt' => $payable,
            'taxable_total' => $taxable,
            'cgst_total' => $cgst,
            'sgst_total' => $sgst,
            'igst_total' => $igst,
            'items' => [[
                'description' => 'Plan Renewal — ' . $planName,
                'hsn_sac' => '',
                'qty' => $qty,
                'cnt_rate' => (float) ($summary['unit_price'] ?? 0),
                'taxable_amt' => $taxable,
                'tax_type' => $taxType,
                'cgst' => $cgst,
                'sgst' => $sgst,
                'igst' => $igst,
                'nettotal' => $payable,
            ]],
        ]);
    }

    private function triggerSandboxEInvoice(int $orderId): void
    {
        try {
            if (!filter_var(env('SANDBOX_AUTO_GENERATE', true), FILTER_VALIDATE_BOOLEAN)) {
                return;
            }
            app(SandboxEInvoiceService::class)->generateForOrder($orderId);
        } catch (\Throwable $e) {
            Log::warning('Sandbox e-invoice generation failed (API renewal)', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
