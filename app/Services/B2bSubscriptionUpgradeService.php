<?php

namespace App\Services;

use App\Models\AccountMaster;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPoDetail;
use App\Models\Partner;
use App\Models\Plan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Support\GstSplit;
use App\Support\OrderPrefixResolver;

class B2bSubscriptionUpgradeService
{
    public function __construct(
        private RenewalService $renewalService,
        private InvoiceService $invoiceService,
    ) {}

    /**
     * @param  array{
     *   partner_id:int,
     *   subscription_id:int,
     *   plan_id:int,
     *   quantity:int,
     *   aadhaar_number:string,
     *   payable_amount?:float|null,
     *   subtotal?:float|null,
     *   pro_rata_adjustment?:float|null,
     *   taxable_amount?:float|null,
     *   gst_amount?:float|null,
     *   skip_credit_check?:bool,
     *   skip_invoice?:bool,
     *   force_pending?:bool,
     *   order_sub_id?:int,
     *   order_item_id?:int
     * }  $input
     * @return array{order:Order, summary:array<string,float>, available_credits:float}
     */
    public function upgrade(array $input): array
    {
        $partnerId = (int) $input['partner_id'];
    $orderId = (int) $input['subscription_id'];
    $planId = (int) $input['plan_id'];
    $qty = max(1, (int) $input['quantity']);
    $aadhaar = (string) $input['aadhaar_number'];

    $partner = Partner::query()->findOrFail($partnerId);
    $baseOrder = Order::query()
        ->where('id', $orderId)
        ->where('order_type', 'b2b')
        ->firstOrFail();

    if ((int) ($baseOrder->partner_id ?? 0) !== $partnerId) {
        throw ValidationException::withMessages([
            'subscription_id' => 'Subscription does not belong to this partner.',
        ]);
    }

    $newPlan = Plan::query()->findOrFail($planId);

        $sourceItem = $this->resolveSourceOrderItem($orderId, $input);
        $currentPlanId = $sourceItem
            ? (int) ($sourceItem->plan_id ?? 0)
            : (int) ($baseOrder->plan_id ?? 0);
        $currentPlan = $currentPlanId > 0 ? Plan::query()->find($currentPlanId) : null;
        if (!$currentPlan) {
            throw ValidationException::withMessages(['subscription_id' => 'Current plan not found.']);
        }   

        // Same plan handled below. Eligibility must match add-to-cart (price primary, sr_no secondary).
        // Strict sr_no-only rejected valid carts where target price is higher but sr_no is equal/lower/missing.
        if ((int) $newPlan->id !== $currentPlanId && !$this->isEligibleUpgradeTarget($currentPlan, $newPlan)) {
            throw ValidationException::withMessages([
                'plan_id' => 'Selected plan is not eligible for upgrade.',
            ]);
        }

        $limit = (int) ($newPlan->customer_limit ?? 0);
        if ($limit > 0 && $qty > $limit) {
            throw ValidationException::withMessages([
                'quantity' => 'Licenses cannot exceed plan limit of ' . $limit . '.',
            ]);
        }

        $baseQty = max(1, (int) ($baseOrder->quantity ?? 1));
        if ($sourceItem) {
            $baseQty = max(1, (int) ($sourceItem->quantity ?? 1));
        }

        if ((int) $newPlan->id === $currentPlanId) {
            throw ValidationException::withMessages([
                'plan_id' => 'License add is separate from upgrade. Extra seats on the same plan must use license add.',
            ]);
        }

        $calcInput = [
            'partner_id' => $partnerId,
            'subscription_id' => $orderId,
            'plan_id' => $planId,
            'licenses' => $qty,
        ];
        if ($sourceItem) {
            $calcInput['order_item_id'] = (int) $sourceItem->id;
            $calcInput['current_upgrade_licenses'] = $qty;
        }

        $summary = $this->renewalService->upgradeOrderCalculation($calcInput);
        $summary = $this->mergeSummaryFromInput($summary, $input);

        $skipWalletCheck = !empty($input['skip_credit_check']) || !empty($input['force_pending']);
        if (!$skipWalletCheck) {
            PartnerCreditService::assertB2bPurchaseAmount(
                $partnerId,
                (float) $summary['payable_amount'],
            );
        }
        $availableCredits = $this->availableCredits($partnerId, $partner);

        if (
            !$skipWalletCheck
            && $availableCredits <= 0
            && $this->renewalService->hasActiveUpgradeForCustomer((int) $baseOrder->user_id, (int) $baseOrder->id)
        ) {
            throw ValidationException::withMessages([
                'subscription_id' => 'Complete your existing payment before starting another upgrade.',
            ]);
        }

        $newOrder = Order::query()->getConnection()->transaction(function () use ($baseOrder, $newPlan, $qty, $summary, $partnerId, $aadhaar, $input, $baseQty, $skipWalletCheck, $sourceItem) {
            $order = $this->buildPlanChangeOrder($baseOrder, $newPlan, $qty, $summary, $partnerId, $sourceItem, 'upgrade');
            if (!empty($input['force_pending'])) {
                $order->status = 'pending';
            }
            $order->save();

            if ($sourceItem) {
                if (empty($input['force_pending'])) {
                    $this->applyPartialOrFullSourceLineChange($baseOrder, $sourceItem, $baseQty, $qty, 'upgrade');
                }
            } else {
                $remainingQty = $baseQty - $qty;
                if ($remainingQty > 0) {
                    $baseOrder->quantity = $remainingQty;
                    $baseOrder->updated_at = now();
                    $baseOrder->save();

                    OrderItem::query()
                        ->where('order_id', (int) $baseOrder->id)
                        ->update(['quantity' => $remainingQty, 'updated_at' => now()]);
                } elseif (empty($input['force_pending'])) {
                    $this->markOrderAsUpgraded($baseOrder);
                }
            }

            if (empty($input['force_pending']) && $aadhaar !== '') {
                $this->upsertAutoPoForOrder((int) $order->id, $aadhaar, (float) $summary['payable_amount']);
            }
            if (empty($input['skip_invoice'])) {
                $this->createGstInvoice($order, $baseOrder, $newPlan, $qty, $summary);
            }
            if (!$skipWalletCheck) {
                $walletPayload = $order->getAttributes();
                PartnerCreditService::syncB2bOrderWalletLedger((int) $order->id, $walletPayload);
            }

            return $order->fresh();
        });

        if (empty($input['skip_invoice'])) {
            $this->triggerSandboxEInvoice((int) $newOrder->id);
        }

        // Immediate-complete path (not force_pending): issue unused-days CN for source plan.
        if (
            empty($input['force_pending'])
            && in_array(strtolower(trim((string) ($newOrder->status ?? ''))), ['completed', 'paid', 'success', 'active'], true)
        ) {
            try {
                app(OrderCancellationCreditNoteService::class)->issueForPlanChangeOrder((int) $newOrder->id);
            } catch (\Throwable $e) {
                Log::warning('upgrade_plan_change_credit_note_failed', [
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

    /**
     * @param  array{
     *   partner_id:int,
     *   subscription_id:int,
     *   plan_id:int,
     *   quantity:int,
     *   aadhaar_number:string,
     *   payable_amount?:float|null,
     *   subtotal?:float|null,
     *   pro_rata_adjustment?:float|null,
     *   taxable_amount?:float|null,
     *   gst_amount?:float|null,
     *   skip_credit_check?:bool,
     *   skip_invoice?:bool,
     *   force_pending?:bool,
     *   order_sub_id?:int,
     *   order_item_id?:int
     * }  $input
     * @return array{order:Order, summary:array<string,float>, available_credits:float}
     */
    public function downgrade(array $input): array
    {
        $partnerId = (int) $input['partner_id'];
        $orderId = (int) $input['subscription_id'];
        $planId = (int) $input['plan_id'];
        $qty = max(1, (int) $input['quantity']);
        $aadhaar = (string) $input['aadhaar_number'];

        $partner = Partner::query()->findOrFail($partnerId);
        $baseOrder = Order::query()
            ->where('id', $orderId)
            ->where('order_type', 'b2b')
            ->firstOrFail();

        if ((int) ($baseOrder->partner_id ?? 0) !== $partnerId) {
            throw ValidationException::withMessages([
                'subscription_id' => 'Subscription does not belong to this partner.',
            ]);
        }

        $newPlan = Plan::query()->findOrFail($planId);
        $sourceItem = $this->resolveSourceOrderItem($orderId, $input);
        $currentPlanId = $sourceItem
            ? (int) ($sourceItem->plan_id ?? 0)
            : (int) ($baseOrder->plan_id ?? 0);
        $currentPlan = $currentPlanId > 0 ? Plan::query()->find($currentPlanId) : null;
        if (!$currentPlan) {
            throw ValidationException::withMessages(['subscription_id' => 'Current plan not found.']);
        }

        if (!$this->isEligibleDowngradeTarget($currentPlan, $newPlan)) {
            throw ValidationException::withMessages([
                'plan_id' => 'Selected plan is not eligible for downgrade.',
            ]);
        }

        $limit = (int) ($newPlan->customer_limit ?? 0);
        if ($limit > 0 && $qty > $limit) {
            throw ValidationException::withMessages([
                'quantity' => 'Licenses cannot exceed plan limit of ' . $limit . '.',
            ]);
        }

        $baseQty = max(1, (int) ($baseOrder->quantity ?? 1));
        if ($sourceItem) {
            $baseQty = max(1, (int) ($sourceItem->quantity ?? 1));
        }
        $qty = $baseQty;

        $calcInput = [
            'partner_id' => $partnerId,
            'subscription_id' => $orderId,
            'plan_id' => $planId,
            'licenses' => $qty,
        ];
        if ($sourceItem) {
            $calcInput['order_item_id'] = (int) $sourceItem->id;
            $calcInput['current_upgrade_licenses'] = $qty;
        }

        $summary = $this->renewalService->upgradeOrderCalculation($calcInput);
        $summary = $this->mergeSummaryFromInput($summary, $input);

        $skipWalletCheck = !empty($input['skip_credit_check']) || !empty($input['force_pending']);
        if (!$skipWalletCheck) {
            PartnerCreditService::assertB2bPurchaseAmount(
                $partnerId,
                (float) $summary['payable_amount'],
            );
        }
        $availableCredits = $this->availableCredits($partnerId, $partner);

        if (
            !$skipWalletCheck
            && $availableCredits <= 0
            && $this->renewalService->hasActiveUpgradeForCustomer((int) $baseOrder->user_id, (int) $baseOrder->id)
        ) {
            throw ValidationException::withMessages([
                'subscription_id' => 'Complete your existing payment before starting another downgrade.',
            ]);
        }

        $newOrder = Order::query()->getConnection()->transaction(function () use ($baseOrder, $newPlan, $qty, $summary, $partnerId, $aadhaar, $input, $baseQty, $skipWalletCheck, $sourceItem) {
            $order = $this->buildPlanChangeOrder($baseOrder, $newPlan, $qty, $summary, $partnerId, $sourceItem, 'downgrade');
            if (!empty($input['force_pending'])) {
                $order->status = 'pending';
            }
            $order->save();

            if ($sourceItem) {
                if (empty($input['force_pending'])) {
                    $this->applyPartialOrFullSourceLineChange($baseOrder, $sourceItem, $baseQty, $qty, 'downgrade');
                }
            } else {
                $remainingQty = $baseQty - $qty;
                if ($remainingQty > 0) {
                    $baseOrder->quantity = $remainingQty;
                    $baseOrder->updated_at = now();
                    $baseOrder->save();

                    OrderItem::query()
                        ->where('order_id', (int) $baseOrder->id)
                        ->update(['quantity' => $remainingQty, 'updated_at' => now()]);
                } elseif (empty($input['force_pending'])) {
                    $this->markOrderAsDowngraded($baseOrder);
                }
            }

            if (empty($input['force_pending']) && $aadhaar !== '') {
                $this->upsertAutoPoForOrder((int) $order->id, $aadhaar, (float) $summary['payable_amount']);
            }
            if (empty($input['skip_invoice'])) {
                $this->createGstInvoice($order, $baseOrder, $newPlan, $qty, $summary);
            }
            if (!$skipWalletCheck) {
                $walletPayload = $order->getAttributes();
                PartnerCreditService::syncB2bOrderWalletLedger((int) $order->id, $walletPayload);
            }

            return $order->fresh();
        });

        if (empty($input['skip_invoice'])) {
            $this->triggerSandboxEInvoice((int) $newOrder->id);
        }

        if (
            empty($input['force_pending'])
            && in_array(strtolower(trim((string) ($newOrder->status ?? ''))), ['completed', 'paid', 'success', 'active'], true)
        ) {
            try {
                app(OrderCancellationCreditNoteService::class)->issueForPlanChangeOrder((int) $newOrder->id);
            } catch (\Throwable $e) {
                Log::warning('downgrade_plan_change_credit_note_failed', [
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

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, float>
     */
    private function buildDowngradeSummary(Plan $newPlan, int $qty, array $input): array
    {
        if (isset($input['payable_amount']) && is_numeric($input['payable_amount']) && (float) $input['payable_amount'] > 0) {
            return [
                'subtotal' => round((float) ($input['subtotal'] ?? 0), 2),
                'new_plan_cost' => round((float) ($input['subtotal'] ?? 0), 2),
                'pro_rata_adjustment' => round((float) ($input['pro_rata_adjustment'] ?? 0), 2),
                'taxable_amount' => round((float) ($input['taxable_amount'] ?? 0), 2),
                'gst_amount' => round((float) ($input['gst_amount'] ?? 0), 2),
                'payable_amount' => round((float) $input['payable_amount'], 2),
                'final_payable' => round((float) $input['payable_amount'], 2),
            ];
        }

        $newPlanPrice = round((float) $newPlan->price * $qty, 2);
        $gstAmount = $this->renewalService->calculateGst($newPlanPrice);
        $payable = round($newPlanPrice + $gstAmount, 2);

        return [
            'subtotal' => $newPlanPrice,
            'new_plan_cost' => $newPlanPrice,
            'new_plan_price' => $newPlanPrice,
            'pro_rata_adjustment' => 0.0,
            'taxable_amount' => $newPlanPrice,
            'gst_amount' => $gstAmount,
            'payable_amount' => $payable,
            'final_payable' => $payable,
        ];
    }

    /**
     * Partial change (qty < line qty): keep leftover seats on the source line.
     * Full change (qty >= line qty): mark the source line upgraded/downgraded.
     */
    private function applyPartialOrFullSourceLineChange(
        Order $baseOrder,
        OrderItem $sourceItem,
        int $baseQty,
        int $changeQty,
        string $changeType
    ): void {
        $changeType = strtolower(trim($changeType)) === 'downgrade' ? 'downgrade' : 'upgrade';
        $remainingQty = $baseQty - $changeQty;

        if ($remainingQty > 0) {
            OrderItem::query()->whereKey((int) $sourceItem->id)->update([
                'quantity' => $remainingQty,
                'updated_at' => now(),
            ]);

            $orderQty = max(1, (int) ($baseOrder->quantity ?? $baseQty));
            $newOrderQty = max(1, $orderQty - $changeQty);
            $baseOrder->quantity = $newOrderQty;
            $baseOrder->updated_at = now();
            $baseOrder->save();

            return;
        }

        if ($changeType === 'downgrade') {
            $this->markOrderItemAsDowngraded($sourceItem);
        } else {
            $this->markOrderItemAsUpgraded($sourceItem);
        }

        $hasActiveItems = OrderItem::query()
            ->where('order_id', (int) $baseOrder->id)
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereRaw("LOWER(TRIM(COALESCE(status, ''))) NOT IN ('upgraded', 'downgraded', 'delete', 'deleted')");
            })
            ->exists();

        if (!$hasActiveItems) {
            if ($changeType === 'downgrade') {
                $this->markOrderAsDowngraded($baseOrder);
            } else {
                $this->markOrderAsUpgraded($baseOrder);
            }
        }
    }

    private function markOrderAsDowngraded(Order $order): void
    {
        $order->status = 'downgraded';
        // Don't use Schema::hasColumn. Only set if property exists
        if (property_exists($order, 'order_category')) {
            $order->order_category = 'downgraded';
        }
        $order->updated_at = now();
        $order->save();
    }

    private function markOrderItemAsDowngraded(OrderItem $item): void
    {
        if ((int) ($item->id ?? 0) <= 0) {
            return;
        }

        OrderItem::query()->whereKey((int) $item->id)->update([
            'status' => 'downgraded',
            'updated_at' => now(),
        ]);
    }

    /**
     * When an upgrade/downgrade order is completed, mark the original subscription line.
     */
    public function markSourceSubscriptionUpgraded(Order $upgradeOrder): void
    {
        $category = strtolower(trim((string) ($upgradeOrder->order_category ?? '')));
        if (!in_array($category, ['upgrade', 'downgrade'], true)) {
            return;
        }

        $isDowngrade = $category === 'downgrade';

        $parentId = (int) ($upgradeOrder->parent_order_id ?? 0);
        if ($parentId <= 0) {
            return;
        }

        $parent = Order::query()->find($parentId);
        if (!$parent) {
            return;
        }

        $subId = (int) ($upgradeOrder->parent_order_sub_id ?? 0);
        if ($subId <= 0) {
            $childItem = OrderItem::query()
                ->where('order_id', (int) $upgradeOrder->id)
                ->orderBy('id')
                ->first();
            if ($childItem) {
                $subId = (int) ($childItem->previous_order_sub_id ?? 0);
                if ($subId <= 0) {
                    $meta = is_array($childItem->meta ?? null) ? $childItem->meta : [];
                    $subId = (int) ($meta['previous_order_sub_id'] ?? $meta['order_sub_id'] ?? 0);
                }
            }
        }
        if ($subId <= 0) {
            $previousPlanId = (int) ($upgradeOrder->previous_plan_id ?? 0);
            if ($previousPlanId > 0) {
                $subId = (int) OrderItem::query()
                    ->where('order_id', $parentId)
                    ->where('plan_id', $previousPlanId)
                    ->where(function ($q) {
                        $q->whereNull('status')
                            ->orWhereRaw("LOWER(TRIM(COALESCE(status, ''))) NOT IN ('upgraded', 'downgraded', 'delete')");
                    })
                    ->orderBy('id')
                    ->value('id');
            }
            if ($subId <= 0) {
                $activeLineIds = OrderItem::query()
                    ->where('order_id', $parentId)
                    ->where(function ($q) {
                        $q->whereNull('status')
                            ->orWhereRaw("LOWER(TRIM(COALESCE(status, ''))) NOT IN ('upgraded', 'downgraded', 'delete', 'deleted')");
                    })
                    ->orderBy('id')
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
                if (count($activeLineIds) === 1) {
                    $subId = $activeLineIds[0];
                }
            }
        }

        if ($subId > 0) {
            $sourceItem = OrderItem::query()->whereKey($subId)->where('order_id', $parentId)->first();
            $changeQty = max(1, (int) ($upgradeOrder->quantity ?? 1));
            $childLineQty = (int) OrderItem::query()
                ->where('order_id', (int) $upgradeOrder->id)
                ->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhereRaw("LOWER(TRIM(COALESCE(status, ''))) NOT IN ('delete', 'deleted')");
                })
                ->sum('quantity');
            if ($childLineQty > 0) {
                $changeQty = max(1, $childLineQty);
            }

            // Partial upgrade/downgrade (moved seats < line qty): reduce leftover, keep Active.
            // Full line move: mark upgraded/downgraded. Same process as multi-plan 1-of-N.
            if ($sourceItem) {
                $baseQty = max(1, (int) ($sourceItem->quantity ?? 1));
                $this->applyPartialOrFullSourceLineChange(
                    $parent,
                    $sourceItem,
                    $baseQty,
                    $changeQty,
                    $isDowngrade ? 'downgrade' : 'upgrade'
                );

                return;
            }

            if ($isDowngrade) {
                $this->markOrderItemAsDowngradedById($subId);
            } else {
                $this->markOrderItemAsUpgradedById($subId);
            }
        }

        $hasActiveItems = OrderItem::query()
            ->where('order_id', $parentId)
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereRaw("LOWER(TRIM(COALESCE(status, ''))) NOT IN ('upgraded', 'downgraded', 'delete')");
            })
            ->exists();

        if (!$hasActiveItems) {
            if ($isDowngrade) {
                $this->markOrderAsDowngraded($parent);
            } else {
                $this->markOrderAsUpgraded($parent);
            }
        }
    }

    private function markOrderItemAsDowngradedById(int $itemId): void
    {
        if ($itemId <= 0) {
            return;
        }

        OrderItem::query()->whereKey($itemId)->update([
            'status' => 'downgraded',
            'updated_at' => now(),
        ]);
    }

    private function markOrderAsUpgraded(Order $order): void
    {
        $order->status = 'upgraded';
        // Don't use Schema::hasColumn. Only set if property exists
        if (property_exists($order, 'order_category')) {
            $order->order_category = 'upgraded';
        }
        $order->updated_at = now();
        $order->save();
    }

    private function markOrderItemAsUpgraded(OrderItem $item): void
    {
        $this->markOrderItemAsUpgradedById((int) $item->id);
    }

    private function markOrderItemAsUpgradedById(int $itemId): void
    {
        if ($itemId <= 0) {
            return;
        }

        OrderItem::query()->whereKey($itemId)->update([
            'status' => 'upgraded',
            'updated_at' => now(),
        ]);
    }

    private function orderItemHasStatusColumn(): bool
    {
        return true;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function resolveSourceOrderItem(int $orderId, array $input): ?OrderItem
    {
        $sourceItemId = (int) ($input['order_sub_id'] ?? $input['order_item_id'] ?? 0);
        if ($sourceItemId <= 0) {
            return null;
        }

        return OrderItem::query()
            ->whereKey($sourceItemId)
            ->where('order_id', $orderId)
            ->first();
    }

    /**
     * Upgrade target: higher price and/or higher sr_no (same rules as PartnerAuthController::isHigherPlan).
     */
    private function isEligibleUpgradeTarget(Plan $currentPlan, Plan $newPlan): bool
    {
        if ((int) ($newPlan->id ?? 0) === (int) ($currentPlan->id ?? 0)) {
            return false;
        }

        $currentSr = is_numeric($currentPlan->sr_no ?? null) ? (int) $currentPlan->sr_no : 0;
        $newSr = is_numeric($newPlan->sr_no ?? null) ? (int) $newPlan->sr_no : 0;
        $currentPrice = $this->planComparablePrice($currentPlan);
        $newPrice = $this->planComparablePrice($newPlan);

        $isHigherBySr = $currentSr > 0 && $newSr > 0 && $newSr > $currentSr;
        $isLowerBySr = $currentSr > 0 && $newSr > 0 && $newSr < $currentSr;
        $isHigherByPrice = $currentPrice > 0 && $newPrice > 0 && $newPrice > $currentPrice;
        $isLowerByPrice = $currentPrice > 0 && $newPrice > 0 && $newPrice < $currentPrice;

        if ($isHigherByPrice || $isHigherBySr) {
            return true;
        }

        // Equal price: allow higher sr_no only.
        if ($currentPrice > 0 && $newPrice > 0 && abs($newPrice - $currentPrice) < 0.01 && $newSr > $currentSr) {
            return true;
        }

        // Block clear downgrades; otherwise allow ambiguous catalog data (common for Microsoft).
        if ($isLowerBySr || $isLowerByPrice) {
            return false;
        }

        return true;
    }

    /**
     * Downgrade target must be same provider and not a clear upgrade.
     * Matches /downgrade list rules: lower sr_no and/or lower price (handles missing sr_no).
     */
    private function isEligibleDowngradeTarget(Plan $currentPlan, Plan $newPlan): bool
    {
        // Same plan is license add, not a downgrade.
        if ((int) ($newPlan->id ?? 0) === (int) ($currentPlan->id ?? 0)) {
            return false;
        }
        if ((int) ($newPlan->provider_id ?? 0) !== (int) ($currentPlan->provider_id ?? 0)) {
            return false;
        }

        $currentSr = is_numeric($currentPlan->sr_no ?? null) ? (int) $currentPlan->sr_no : 0;
        $newSr = is_numeric($newPlan->sr_no ?? null) ? (int) $newPlan->sr_no : 0;
        $currentPrice = $this->planComparablePrice($currentPlan);
        $newPrice = $this->planComparablePrice($newPlan);

        $isLowerBySr = $currentSr > 0 && $newSr > 0 && $newSr < $currentSr;
        $isHigherBySr = $currentSr > 0 && $newSr > 0 && $newSr > $currentSr;
        $isLowerByPrice = $currentPrice > 0 && $newPrice > 0 && $newPrice < $currentPrice;
        $isHigherByPrice = $currentPrice > 0 && $newPrice > 0 && $newPrice > $currentPrice;

        if ($isLowerBySr || $isLowerByPrice) {
            return true;
        }

        // Block only clear upgrades (higher tier / higher price).
        if ($isHigherBySr || $isHigherByPrice) {
            return false;
        }

        // Ambiguous sr_no/price data (common for Microsoft catalog): allow same-provider change.
        return true;
    }

    private function planComparablePrice(Plan $plan): float
    {
        $raw = $plan->price ?? $plan->actual_price ?? 0;
        if (is_string($raw)) {
            $raw = str_replace([',', '₹', ' '], '', $raw);
        }

        $price = round((float) $raw, 2);
        if ($price > 0) {
            return $price;
        }

        $actualRaw = $plan->actual_price ?? 0;
        if (is_string($actualRaw)) {
            $actualRaw = str_replace([',', '₹', ' '], '', $actualRaw);
        }

        return round((float) $actualRaw, 2);
    }

    public function resolveSubscriptionId(int $partnerId, ?int $subscriptionId, ?int $customerId, ?string $username): int
    {
        if ($subscriptionId) {
            return $subscriptionId;
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
                'subscription_id' => 'Provide subscription_id, customer_id, or username.',
            ]);
        }

        $customer = $customerQuery->first();
        if (!$customer) {
            throw ValidationException::withMessages(['customer_id' => 'Customer not found for this partner.']);
        }

        // Don't use Schema::hasColumn; assume 'status' exists or do not filter
        $order = Order::query()
            ->where('user_id', (int) $customer->id)
            ->where('partner_id', $partnerId)
            ->where('order_type', 'b2b')
            //->when(Schema::hasColumn('orders', 'status'), fn ($q) => $q->where('status', 'completed'))
            ->where('status', 'completed')
            ->orderByDesc('id')
            ->first();

        if (!$order) {
            throw ValidationException::withMessages(['customer_id' => 'No active subscription found for this customer.']);
        }

        return (int) $order->id;
    }

    private function availableCredits(int $partnerId, Partner $partner): float
    {
        return PartnerCreditService::remainingPurchaseAllowance($partnerId);
    }

    /**
     * @param  array<string,mixed>  $summary
     * @param  array<string,mixed>  $input
     * @return array<string,mixed>
     */
    private function mergeSummaryFromInput(array $summary, array $input): array
    {
        if (isset($input['subtotal']) && is_numeric($input['subtotal'])) {
            $summary['subtotal'] = round((float) $input['subtotal'], 2);
        }
        if (isset($input['pro_rata_adjustment']) && is_numeric($input['pro_rata_adjustment'])) {
            $credit = round((float) $input['pro_rata_adjustment'], 2);
            $summary['pro_rata_adjustment'] = $credit;
            $summary['remaining_credit'] = $credit;
            $summary['prorated_credit'] = $credit;
        }
        if (isset($input['taxable_amount']) && is_numeric($input['taxable_amount'])) {
            $taxable = round((float) $input['taxable_amount'], 2);
            $summary['taxable_amount'] = $taxable;
            $summary['taxable_amount_after_coupon'] = $taxable;
        }
        if (isset($input['gst_amount']) && is_numeric($input['gst_amount'])) {
            $summary['gst_amount'] = round((float) $input['gst_amount'], 2);
        }
        if (isset($input['payable_amount']) && is_numeric($input['payable_amount'])) {
            $payable = round((float) $input['payable_amount'], 2);
            $summary['payable_amount'] = $payable;
            $summary['final_payable'] = $payable;
        }

        return $summary;
    }

    /** @param array<string,float> $summary */
    private function buildPlanChangeOrder(
        Order $baseOrder,
        Plan $newPlan,
        int $qty,
        array $summary,
        int $partnerId,
        ?OrderItem $sourceItem = null,
        string $category = 'upgrade'
    ): Order {
        $category = strtolower(trim($category)) === 'downgrade' ? 'downgrade' : 'upgrade';
        $newOrder = $baseOrder->replicate();
        $newOrder->order_no = null;

        // Remove Schema::hasColumn, optimistically assume columns exist or use property_exists()
        if (property_exists($newOrder, 'sr_no')) {
            $newOrder->sr_no = null;
        }

        $next = $this->nextOrderIdentifiers($category);
        $newOrder->order_no = $next['order_no'];
        if (property_exists($newOrder, 'sr_no')) {
            $newOrder->sr_no = $next['sr_no'];
        }

        $newOrder->partner_id = $partnerId;
        $newOrder->plan_id = $newPlan->id;
        $newOrder->previous_plan_id = $sourceItem
            ? (int) ($sourceItem->plan_id ?? 0)
            : (int) ($baseOrder->plan_id ?? 0);
        $newOrder->parent_order_id = $baseOrder->id;

        // Always set when we know the source line (don't use property_exists on Eloquent).
        if ($sourceItem) {
            $newOrder->parent_order_sub_id = (int) $sourceItem->id;
        }

        $newOrder->order_type = 'b2b';
        $newOrder->order_category = $category;
        $newOrder->price = (float) $newPlan->price;
        $newOrder->quantity = $qty;
        $newOrder->subtotal = (float) $summary['subtotal'];
        $newOrder->tax_amount = (float) $summary['gst_amount'];
        if (property_exists($newOrder, 'gst_amount')) {
            $newOrder->gst_amount = (float) $summary['gst_amount'];
        }
        $newOrder->total_amount = (float) $summary['payable_amount'];
        $newOrder->credit_used = (float) $summary['payable_amount'];
        if (property_exists($newOrder, 'gst_rate')) {
            $newOrder->gst_rate = (float) ($summary['gst_rate'] ?? RenewalService::GST_RATE_PERCENT);
        }
        if (property_exists($newOrder, 'apply_gst')) {
            $newOrder->apply_gst = 1;
        }
        $newOrder->status = 'completed';
        $newOrder->payment_method = $newOrder->payment_method ?: 'wallet';
        if (property_exists($newOrder, 'payment_mode') && empty($newOrder->payment_mode)) {
            $newOrder->payment_mode = 'wallet';
        }
        if (property_exists($newOrder, 'duration')) {
            $newOrder->duration = '12 month';
        }

        if (property_exists($newOrder, 'plan_name')) {
            $newOrder->plan_name = $newPlan->name;
        }

        // Upgrade keeps parent term. Downgrade always starts a fresh term from today.
        // Do NOT use property_exists() — Eloquent stores attrs in $attributes, so that
        // check skips the assignment and replicate() parent dates remain (the bug).
        if ($category === 'downgrade') {
            $start = Carbon::now()->startOfDay();
            $end = $this->renewalService
                ->subscriptionEndDate($start, $newOrder->duration ?? $baseOrder->duration ?? '12 month')
                ->startOfDay();
        } else {
            $start = !empty($baseOrder->subscription_start_date)
                ? Carbon::parse((string) $baseOrder->subscription_start_date)->startOfDay()
                : Carbon::parse($baseOrder->created_at)->startOfDay();
            $end = !empty($baseOrder->subscription_end_date)
                ? Carbon::parse((string) $baseOrder->subscription_end_date)->startOfDay()
                : $this->renewalService->subscriptionEndDate($start, $baseOrder->duration ?? '12 month')->startOfDay();
        }

        $newOrder->subscription_start_date = $start->toDateString();
        $newOrder->subscription_end_date = $end->toDateString();

        $proRata = (float) ($summary['pro_rata_adjustment'] ?? $summary['prorated_credit'] ?? 0);
        if (property_exists($newOrder, 'discount_amount')) {
            $newOrder->discount_amount = $proRata;
        }
        if (property_exists($newOrder, 'pro_rata_adjustment')) {
            $newOrder->pro_rata_adjustment = $proRata;
        }

        foreach (['razorpay_order_id', 'razorpay_payment_id', 'razorpay_signature'] as $col) {
            if (property_exists($newOrder, $col)) {
                $newOrder->{$col} = null;
            }
        }

        $newOrder->created_at = now();
        $newOrder->updated_at = now();

        return $newOrder;
    }

    /** @return array{order_no:string,sr_no:int} */
    private function nextOrderIdentifiers(?string $orderCategory = 'new'): array
    {
        $latestOrder = Order::query()
            ->select('sr_no')
            ->orderByDesc('sr_no')
            ->lockForUpdate()
            ->first();

        $nextSrNo = $latestOrder ? ((int) $latestOrder->sr_no + 1) : 1;

        $prefix = OrderPrefixResolver::resolve($orderCategory);

        $orderNo = $prefix . str_pad((string) $nextSrNo, 5, '0', STR_PAD_LEFT);
        while (Order::query()->where('order_no', $orderNo)->exists()) {
            $nextSrNo++;
            $orderNo = $prefix . str_pad((string) $nextSrNo, 5, '0', STR_PAD_LEFT);
        }

        return ['order_no' => $orderNo, 'sr_no' => $nextSrNo];
    }

    private function upsertAutoPoForOrder(int $orderId, string $aadhaar, float $amount): void
    {
        // Still need column listing for the actual DB table, can't avoid this for dynamic upsert in upsertAutoPoForOrder
        $cols = \Illuminate\Support\Facades\Schema::connection('pgsql_billing')->getColumnListing('order_po_details');
        $payload = ['order_id' => $orderId];
        if (in_array('po_mode', $cols, true)) {
            $payload['po_mode'] = 'generated';
        }
        if (in_array('journey_status', $cols, true)) {
            $payload['journey_status'] = 'placed';
        }
        if (in_array('aadhar_verified', $cols, true)) {
            $payload['aadhar_verified'] = 1;
        }
        if (in_array('aadhar_verified_at', $cols, true)) {
            $payload['aadhar_verified_at'] = now();
        }
        if (in_array('aadhaar_masked', $cols, true)) {
            $payload['aadhaar_masked'] = 'XXXXXXXX' . substr($aadhaar, -4);
        }
        if (in_array('aadhaar_ref_no', $cols, true)) {
            $payload['aadhaar_ref_no'] = 'AADH-' . strtoupper(Str::random(8));
        }
        if (in_array('total_amount', $cols, true)) {
            $payload['total_amount'] = $amount;
        }
        if (in_array('created_at', $cols, true)) {
            $payload['created_at'] = now();
        }
        if (in_array('updated_at', $cols, true)) {
            $payload['updated_at'] = now();
        }

        OrderPoDetail::query()->updateOrInsert(['order_id' => $orderId], $payload);
    }

    /** @param array<string,float> $summary */
    private function createGstInvoice(Order $newOrder, Order $baseOrder, Plan $newPlan, int $qty, array $summary): void
    {
        $gst = round((float) ($summary['gst_amount'] ?? 0), 2);
        $customerGstin = trim((string) ($baseOrder->po_manual_gstin ?? $baseOrder->customer_gstin ?? ''));
        if ($customerGstin === '') {
            $customerGstin = (string) (Customer::query()->whereKey((int) $baseOrder->user_id)->value('gstin') ?? '');
        }
        $split = GstSplit::split($gst, $customerGstin);
        $gross = round((float) ($summary['subtotal'] ?? 0), 2);
        $remaining = round((float) ($summary['pro_rata_adjustment'] ?? $summary['prorated_credit'] ?? 0), 2);
        $taxable = round((float) ($summary['taxable_amount'] ?? max(0, $gross - $remaining)), 2);
        $payable = round((float) ($summary['payable_amount'] ?? 0), 2);
        $unitPrice = round((float) ($summary['new_unit_price'] ?? $newPlan->price ?? 0), 2);

        $this->invoiceService->createInvoice([
            'cust_id' => (int) $baseOrder->user_id,
            'order_id' => (int) ($newOrder->id ?? 0),
            'partner_id' => (int) ($newOrder->partner_id ?? $baseOrder->partner_id ?? 0),
            'status' => 'pending',
            'action' => 'upgrade',
            'tax_type' => $split['tax_type'],
            'net_amt' => $payable,
            'taxable_total' => $taxable,
            'cgst_total' => $split['cgst'],
            'sgst_total' => $split['sgst'],
            'igst_total' => $split['igst'],
            'items' => [[
                'description' => 'Plan Upgrade — ' . (string) ($newPlan->name ?? 'New Plan'),
                'hsn_sac' => '',
                'qty' => $qty,
                'cnt_rate' => $unitPrice,
                'taxable_amt' => $taxable,
                'tax_type' => $split['tax_type'],
                'cgst' => $split['cgst'],
                'sgst' => $split['sgst'],
                'igst' => $split['igst'],
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
            Log::warning('Sandbox e-invoice generation failed (API upgrade)', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
