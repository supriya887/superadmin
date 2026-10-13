<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Plan;
use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RenewalService
{
    public const GST_RATE_PERCENT = 18.0;

    public function hasPendingPaymentForDomain(string $domain, ?int $excludeOrderId = null): bool
    {
        $normalized = strtolower(trim($domain));
        if ($normalized === '') {
            return false;
        }

        $query = Order::query()
            ->whereRaw('LOWER(domain_name) = ?', [$normalized])
            ->where('status', 'pending_payment');

        if ($excludeOrderId) {
            $query->where('id', '!=', $excludeOrderId);
        }

        return $query->exists();
    }

    public function hasActiveRenewalForCustomer(int $customerId, ?int $excludeOrderId = null): bool
    {
        if ($customerId <= 0) {
            return false;
        }

        $query = Order::query()
            ->where('user_id', $customerId)
            ->whereIn('status', ['pending', 'pending_payment', 'processing'])
            ->whereIn('order_category', ['renewal', 'upgrade']);

        if ($excludeOrderId) {
            $query->where('id', '!=', $excludeOrderId);
        }

        return $query->exists();
    }

    /**
     * Pending renewal/upgrade for the same subscription (wizard or API draft).
     */
    public function findInProgressRenewalForSubscription(int $customerId, int $baseOrderId): ?Order
    {
        $base = $baseOrderId > 0 ? Order::query()->find($baseOrderId) : null;
        $partnerId = $base ? (int) ($base->partner_id ?? 0) : 0;

        return $this->findPendingRenewalForCustomer($customerId, $partnerId, $baseOrderId);
    }

    /**
     * Any open B2B checkout for this customer (wizard renewal often saved as order_category=new).
     */
    public function findPendingRenewalForCustomer(int $customerId, int $partnerId, int $excludeOrderId): ?Order
    {
        if ($customerId <= 0) {
            return null;
        }

        $query = Order::query()
            ->where('user_id', $customerId)
            ->whereIn('status', ['pending', 'pending_payment', 'processing'])
            ->where('id', '!=', $excludeOrderId)
            ->orderByDesc('id');

        if ($partnerId > 0) {
            $query->where('partner_id', $partnerId);
        }

        $query->where(function ($q) {
            $q->whereNull('order_type')
                ->orWhereRaw("LOWER(TRIM(COALESCE(order_type, ''))) = 'b2b'");
        });

        $renewalOrUpgrade = (clone $query)
            ->whereIn('order_category', ['renewal', 'upgrade'])
            ->first();
        if ($renewalOrUpgrade) {
            return $renewalOrUpgrade;
        }

        return (clone $query)
            ->where(function ($q) {
                $q->whereNull('order_category')
                    ->orWhereIn('order_category', ['', 'new']);
            })
            ->first();
    }

    public function hasActiveUpgradeForCustomer(int $customerId, ?int $excludeOrderId = null): bool
    {
        if ($customerId <= 0) {
            return false;
        }

        $query = Order::query()
            ->where('user_id', $customerId)
            ->whereIn('status', ['pending', 'pending_payment', 'processing'])
            ->where('order_category', 'upgrade');

        if ($excludeOrderId) {
            $query->where('id', '!=', $excludeOrderId);
        }

        return $query->exists();
    }

    public function calculateGst(float $amount, float $ratePercent = self::GST_RATE_PERCENT): float
    {
        return round(max(0, $amount) * ($ratePercent / 100), 2);
    }

    public function calculateProRataPricing(?Order $previousOrder, float $newPlanUnitPrice, int $quantity = 1): array
    {
        $quantity = max(1, $quantity);
        $baseSubtotal = round(max(0, $newPlanUnitPrice) * $quantity, 2);
        $credit = $this->calculateRemainingCredit($previousOrder);
        $taxable = max(0, $baseSubtotal - $credit);
        $gst = $this->calculateGst($taxable);
        $total = round($taxable + $gst, 2);

        return [
            'base_subtotal' => $baseSubtotal,
            'pro_rata_credit' => $credit,
            'taxable_amount' => $taxable,
            'gst_amount' => $gst,
            'total_amount' => $total,
        ];
    }

    public function calculateRemainingCredit(?Order $previousOrder): float
    {
        if (!$previousOrder) {
            return 0.0;
        }

        $paid = (float) ($previousOrder->subtotal ?? 0);
        if ($paid <= 0) {
            $paid = (float) ($previousOrder->price ?? 0) * max(1, (int) ($previousOrder->quantity ?? 1));
        }
        if ($paid <= 0 || empty($previousOrder->created_at)) {
            return 0.0;
        }

        $start = Carbon::parse($previousOrder->created_at)->startOfDay();
        $end = $this->subscriptionEndDate($start, $previousOrder->duration)->startOfDay();
        $now = Carbon::now()->startOfDay();

        if ($now->greaterThanOrEqualTo($end)) {
            return 0.0;
        }

        $totalDays = max(1, $start->diffInDays($end));
        $remainingDays = $now->diffInDays($end);

        return round(($remainingDays / $totalDays) * $paid, 2);
    }

    public function filterUpgradePlans(Collection $plans, Plan $currentPlan): Collection
    {
        $currentPrice = (float) $currentPlan->price;
        $currentStorage = $this->parseStorageToNumeric($this->planStorageString($currentPlan));

        return $plans->filter(function ($plan) use ($currentPrice, $currentStorage) {
            $planPrice = (float) ($plan->price ?? 0);
            $planStorage = $this->parseStorageToNumeric($this->planStorageString($plan));

            return $planPrice > $currentPrice || $planStorage > $currentStorage;
        })->values();
    }

    public function filterUpgradePlansByPrice(Collection $plans, Plan $currentPlan): Collection
    {
        $currentPrice = (float) $currentPlan->price;

        return $plans->filter(function ($plan) use ($currentPrice) {
            return (float) $plan->price > $currentPrice;
        })->values();
    }

    /**
     * Mirror of filterUpgradePlans: keep candidates that are cheaper OR have
     * less storage than the current plan. Used to populate the Downgrade tab.
     */
    public function filterDowngradePlans(Collection $plans, Plan $currentPlan): Collection
    {
        $currentPrice = (float) $currentPlan->price;
        $currentStorage = $this->parseStorageToNumeric($this->planStorageString($currentPlan));

        return $plans->filter(function ($plan) use ($currentPrice, $currentStorage) {
            $planPrice = (float) ($plan->price ?? 0);
            $planStorage = $this->parseStorageToNumeric($this->planStorageString($plan));

            return $planPrice < $currentPrice || $planStorage < $currentStorage;
        })->values();
    }

    /**
     * Upgrade/downgrade cart pricing:
     * New plan value = new unit price × new licenses
     * Remaining credit = (current unit price × current licenses / billing cycle days) × remaining days
     *
     * @param  array<string, mixed>  $data
     * @return array<string, int|float|string>
     */
    public function upgradeOrderCalculation(array $data): array
    {
        $partnerId = (int) ($data['partner_id'] ?? 0);
        $newPlanLicenses = max(1, (int) ($data['new_plan_licenses'] ?? $data['licenses'] ?? $data['quantity'] ?? 1));
        $subscriptionId = app(B2bSubscriptionUpgradeService::class)->resolveSubscriptionId(
            $partnerId,
            (int) ($data['subscription_id'] ?? $data['order_id'] ?? 0) ?: null,
            isset($data['customer_id']) ? (int) $data['customer_id'] : null,
            $data['username'] ?? null,
        );

        $baseOrder = Order::query()
            ->where('id', $subscriptionId)
            ->where('partner_id', $partnerId)
            ->where('order_type', 'b2b')
            ->firstOrFail();

        $newPlan = Plan::query()->findOrFail((int) $data['plan_id']);
        $currentPlan = $baseOrder->plan_id ? Plan::query()->find((int) $baseOrder->plan_id) : null;

        $orderRow = $baseOrder;
        $orderItem = OrderItem::query()
            ->where('order_id', $subscriptionId)
            ->when(!empty($data['order_item_id']), fn ($q) => $q->whereKey((int) $data['order_item_id']))
            ->orderBy('id')
            ->first();

        [$start, $end] = [null, null];
        if (!empty($orderRow->subscription_start_date)) {
            $start = Carbon::parse((string) $orderRow->subscription_start_date)->startOfDay();
        } elseif (!empty($orderRow->created_at)) {
            $start = Carbon::parse((string) $orderRow->created_at)->startOfDay();
        }
        if (!empty($orderRow->subscription_end_date)) {
            $end = Carbon::parse((string) $orderRow->subscription_end_date)->startOfDay();
        }
        $now = Carbon::now()->startOfDay();
        if (!$start) {
            $start = $now->copy();
        }
        if (!$end) {
            $end = $this->subscriptionEndDate($start, $orderRow->duration ?? '12 month')->startOfDay();
        }

        $totalPlanDays = max(1, UpgradeProrationService::subscriptionTotalDays($start, $end));
        $remainingDays = UpgradeProrationService::subscriptionRemainingDays($start, $end, $now);

        $oldUnitPrice = round((float) ($currentPlan->price ?? 0), 2);
        if ($oldUnitPrice <= 0) {
            $oldUnitPrice = round((float) ($orderItem->unit_price ?? 0), 2);
        }
        if ($oldUnitPrice <= 0) {
            $oldUnitPrice = round((float) ($orderRow->subtotal ?? 0) / max(1, (int) ($orderRow->quantity ?? 1)), 2);
        }
        if ($oldUnitPrice <= 0 && (float) ($orderRow->price ?? 0) > 0) {
            $oldUnitPrice = round((float) $orderRow->price, 2);
        }

        $newUnitPrice = round((float) ($newPlan->price ?? 0), 2);
        $subscriptionQty = max(1, (int) ($data['current_licenses'] ?? $orderItem->quantity ?? $orderRow->quantity ?? 1));
        // Include activated same-plan license-add (addon) seats when override not passed.
        if (empty($data['current_licenses'])) {
            $planId = (int) ($orderItem->plan_id ?? $orderRow->plan_id ?? 0);
            if ($subscriptionId > 0 && $planId > 0) {
                $addonChildren = Order::query()
                    ->where('parent_order_id', $subscriptionId)
                    ->where('plan_id', $planId)
                    ->whereRaw("LOWER(TRIM(COALESCE(order_category, ''))) = 'addon'")
                    ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) IN ('completed','paid','success','active')")
                    ->get(['id', 'quantity']);
                foreach ($addonChildren as $child) {
                    $childItems = OrderItem::query()
                        ->where('order_id', (int) $child->id)
                        ->where('plan_id', $planId)
                        ->where(function ($q) {
                            $q->whereNull('status')
                                ->orWhereRaw("LOWER(TRIM(COALESCE(status, ''))) NOT IN ('delete', 'deleted')");
                        })
                        ->get(['quantity']);
                    if ($childItems->isNotEmpty()) {
                        foreach ($childItems as $childItem) {
                            $subscriptionQty += max(1, (int) ($childItem->quantity ?? 1));
                        }
                    } else {
                        $subscriptionQty += max(1, (int) ($child->quantity ?? 1));
                    }
                }
            }
        }

        $subtotal = round($newUnitPrice * $newPlanLicenses, 2);
        $currentPlanValue = round($oldUnitPrice * $subscriptionQty, 2);
        $currentDailyValue = round($currentPlanValue / $totalPlanDays, 4);
        $remainingCredit = $remainingDays > 0
            ? round($currentDailyValue * $remainingDays, 2)
            : 0.0;

        $taxableBeforeCoupon = max(0, round($subtotal - $remainingCredit, 2));

        $couponPercent = max(0, (float) ($data['coupon_percent'] ?? 0));
        $couponDiscount = $couponPercent > 0
            ? round($taxableBeforeCoupon * $couponPercent / 100, 2)
            : 0.0;

        $taxableAfterCoupon = max(0, round($taxableBeforeCoupon - $couponDiscount, 2));

        $applyGst = (int) ($orderRow->apply_gst ?? 1) === 1;
        $gstRate = $applyGst
            ? (float) ($data['gst_rate'] ?? $orderRow->gst_rate ?? self::GST_RATE_PERCENT)
            : 0.0;
        $gst = $taxableAfterCoupon > 0 && $applyGst
            ? $this->calculateGst($taxableAfterCoupon, $gstRate)
            : 0.0;
        $payable = round($taxableAfterCoupon + $gst, 2);

        $walletCredit = $remainingCredit > $subtotal
            ? round($remainingCredit - $subtotal, 2)
            : 0.0;

        return [
            'subscription_id' => $subscriptionId,
            'plan_id' => (int) $newPlan->id,
            'plan_name' => (string) ($newPlan->name ?? ''),
            'quantity' => $newPlanLicenses,
            'licenses' => $newPlanLicenses,
            'new_plan_licenses' => $newPlanLicenses,
            'current_upgrade_licenses' => $subscriptionQty,
            'subtotal' => $subtotal,
            'new_plan_cost' => $subtotal,
            'current_plan_value' => $currentPlanValue,
            'current_daily_value' => $currentDailyValue,
            'pro_rata_adjustment' => $remainingCredit,
            'remaining_credit' => $remainingCredit,
            'remaining_value' => $remainingCredit,
            'eligible_license_count' => $subscriptionQty,
            'wallet_credit' => $walletCredit,
            'taxable_amount' => $taxableBeforeCoupon,
            'taxable_amount_before_coupon' => $taxableBeforeCoupon,
            'coupon_discount' => $couponDiscount,
            'coupon_discount_amount' => $couponDiscount,
            'taxable_amount_after_coupon' => $taxableAfterCoupon,
            'final_payable' => $payable,
            'gst_amount' => $gst,
            'payable_amount' => $payable,
            'total_plan_days' => $totalPlanDays,
            'remaining_days' => $remainingDays,
            'subscription_start_date' => $start->toDateString(),
            'subscription_end_date' => $end->toDateString(),
            'prorated_credit' => $remainingCredit,
            'old_unit_price' => $oldUnitPrice,
            'new_unit_price' => $newUnitPrice,
            'subscription_quantity' => $subscriptionQty,
        ];
    }

    public function subscriptionEndDate(Carbon $startDate, mixed $duration): Carbon
    {
        $durationRaw = strtolower(trim((string) $duration));
        $count = $this->extractFirstInt((string) $duration);
        $count = max(1, $count);

        if (str_contains($durationRaw, 'month')) {
            return (clone $startDate)->addMonths($count)->subDay();
        }

        if (str_contains($durationRaw, 'year')) {
            return (clone $startDate)->addYears($count)->subDay();
        }

        if (is_numeric($duration)) {
            $months = (int) $duration;

            return $months <= 1
                ? (clone $startDate)->addYear()->subDay()
                : (clone $startDate)->addMonths($months)->subDay();
        }

        return (clone $startDate)->addYear()->subDay();
    }

    public function buildPurchaseOrderPrefillFromCustomer(int $customerId): array
    {
        $customer = Customer::query()->whereKey($customerId)->first();
        if (!$customer) {
            return [];
        }

        $companyDetails = null;
        $billingConnection = (new Customer())->getConnectionName();
        if (Schema::connection($billingConnection)->hasTable('company_details')) {
            $query = DB::connection($billingConnection)
                ->table('company_details')
                ->where('customer_id', $customerId);
            if (Schema::connection($billingConnection)->hasColumn('company_details', 'is_primary')) {
                $query->orderByDesc('is_primary');
            }
            $companyDetails = $query->latest('id')->first();
        }

        return [
            'name' => (string) ($customer->name ?? ''),
            'email' => (string) ($customer->email ?? ''),
            'mobile' => (string) ($customer->mobile ?? ''),
            'gstin' => (string) (($companyDetails->gstin ?? null) ?: ($customer->gstin ?? '')),
            'company_name' => (string) (($companyDetails->company_name ?? null) ?: ($customer->company_name ?? '')),
            'address' => (string) (($companyDetails->company_address ?? null) ?: ($customer->company_address ?? '')),
            'city' => (string) (($companyDetails->city ?? null) ?: ($customer->city ?? '')),
            'state' => (string) (($companyDetails->state ?? null) ?: ($customer->state ?? '')),
            'country' => (string) (($companyDetails->country ?? null) ?: ($customer->country ?? '')),
            'postcode' => (string) (
                ($companyDetails->zip_code ?? null)
                ?: ($customer->pincode ?? null)
                ?: ($customer->zip_code ?? null)
                ?: ($customer->postcode ?? '')
            ),
            'pan_no' => (string) (($companyDetails->pan_no ?? null) ?: ($customer->pan_no ?? '')),
        ];
    }

    public function buildPurchaseOrderPrefill(User $user): array
    {
        $companyDetails = null;
        if (Schema::hasTable('company_details')) {
            $query = DB::table('company_details')->where('user_id', $user->id);
            if (Schema::hasColumn('company_details', 'is_primary')) {
                $query->orderByDesc('is_primary');
            }
            $companyDetails = $query->latest('id')->first();
        }

        return [
            'name' => (string) ($user->name ?? ''),
            'email' => (string) ($user->email ?? ''),
            'mobile' => (string) ($user->mobile ?? ''),
            'gstin' => (string) (($companyDetails->gstin ?? null) ?: ($user->gstin ?? '')),
            'company_name' => (string) (($companyDetails->company_name ?? null) ?: ($user->company_name ?? '')),
            'address' => (string) (($companyDetails->company_address ?? null) ?: ($user->company_address ?? '')),
            'city' => (string) (($companyDetails->city ?? null) ?: ($user->city ?? '')),
            'state' => (string) (($companyDetails->state ?? null) ?: ($user->state ?? '')),
            'country' => (string) (($companyDetails->country ?? null) ?: ($user->country ?? '')),
            'postcode' => (string) (($companyDetails->zip_code ?? null) ?: ($user->postcode ?? '')),
            'pan_no' => (string) (($companyDetails->pan_no ?? null) ?: ($user->pan_no ?? '')),
        ];
    }

    public function pendingRenewalPartnerCounts(): Collection
    {
        if (!Schema::hasTable('customers')) {
            return collect();
        }

        $windowEnd = Carbon::now()->subMonths(11)->endOfDay();
        $windowStart = Carbon::now()->subMonths(12)->startOfDay();

        $query = DB::table('customers as c')
            ->selectRaw('c.partner_id, COUNT(*) as pending_count')
            ->whereBetween('c.created_at', [$windowStart, $windowEnd])
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('orders as o_base')
                    ->whereColumn('o_base.partner_id', 'c.partner_id')
                    ->whereColumn('o_base.user_id', 'c.id')
                    ->whereIn('o_base.status', ['completed', 'active', 'pending']);

                if (Schema::hasColumn('orders', 'order_type')) {
                    $q->where('o_base.order_type', 'b2b');
                }
                if (Schema::hasColumn('orders', 'order_category')) {
                    $q->where(function ($x) {
                        $x->whereNull('o_base.order_category')
                          ->orWhere('o_base.order_category', 'new');
                    });
                }
            })
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('orders as o')
                    ->whereColumn('o.partner_id', 'c.partner_id')
                    ->whereColumn('o.user_id', 'c.id')
                    ->whereIn('o.status', ['completed', 'active']);

                if (Schema::hasColumn('orders', 'order_type')) {
                    $q->where('o.order_type', 'b2b');
                }
                if (Schema::hasColumn('orders', 'order_category')) {
                    $q->whereIn('o.order_category', ['renewal', 'upgrade']);
                }
            })
            ->groupBy('c.partner_id');

        return $query->get()->keyBy('partner_id');
    }

    public function successfulRenewalPartnerCounts(): Collection
    {
        if (!Schema::hasTable('orders')) {
            return collect();
        }

        $query = DB::table('orders')
            ->selectRaw('partner_id, COUNT(DISTINCT user_id) as successful_count')
            ->whereIn('status', ['completed', 'active']);

        if (Schema::hasColumn('orders', 'order_type')) {
            $query->where('order_type', 'b2b');
        }
        if (Schema::hasColumn('orders', 'order_category')) {
            $query->whereIn('order_category', ['renewal', 'upgrade']);
        }

        return $query
            ->groupBy('partner_id')
            ->get()
            ->keyBy('partner_id');
    }

    public function renewalRevenue(): float
    {
        if (!Schema::hasTable('orders')) {
            return 0.0;
        }

        $query = DB::table('orders')
            ->whereIn('status', ['completed', 'active']);

        if (Schema::hasColumn('orders', 'order_type')) {
            $query->where('order_type', 'b2b');
        }
        if (Schema::hasColumn('orders', 'order_category')) {
            $query->whereIn('order_category', ['renewal', 'upgrade']);
        }

        return (float) $query->sum(DB::raw('COALESCE(total_amount, subtotal, (price * quantity), 0)'));
    }

    public function completedRenewalsCount(): int
    {
        if (!Schema::hasTable('orders')) {
            return 0;
        }

        $query = DB::table('orders')
            ->whereIn('status', ['completed', 'active']);

        if (Schema::hasColumn('orders', 'order_type')) {
            $query->where('order_type', 'b2b');
        }
        if (Schema::hasColumn('orders', 'order_category')) {
            $query->whereIn('order_category', ['renewal', 'upgrade']);
        }

        return (int) $query->count();
    }

    public function dueCustomersForPartner(int $partnerId): Collection
    {
        if (!Schema::hasTable('customers')) {
            return collect();
        }

        $windowEnd = Carbon::now()->subMonths(11)->endOfDay();
        $windowStart = Carbon::now()->subMonths(12)->startOfDay();

        // Fetching as stdClass objects to support property access (->id), not arrays
        $query = DB::table('customers as c')
            ->where('c.partner_id', $partnerId)
            ->whereBetween('c.created_at', [$windowStart, $windowEnd])
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('orders as o_base')
                    ->whereColumn('o_base.partner_id', 'c.partner_id')
                    ->whereColumn('o_base.user_id', 'c.id')
                    ->whereIn('o_base.status', ['completed']);

                if (Schema::hasColumn('orders', 'order_type')) {
                    $q->where('o_base.order_type', 'b2b');
                }
                if (Schema::hasColumn('orders', 'order_category')) {
                    $q->where(function ($x) {
                        $x->whereNull('o_base.order_category')
                          ->orWhere('o_base.order_category', 'new');
                    });
                }
            })
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('orders as o')
                    ->whereColumn('o.partner_id', 'c.partner_id')
                    ->whereColumn('o.user_id', 'c.id')
                    ->whereIn('o.status', ['completed', 'active']);

                if (Schema::hasColumn('orders', 'order_type')) {
                    $q->where('o.order_type', 'b2b');
                }
                if (Schema::hasColumn('orders', 'order_category')) {
                    $q->whereIn('o.order_category', ['renewal', 'upgrade']);
                }
            })
            ->orderBy('c.created_at');
            // print_r($query->get());
            // exit;
         
        return $query->get(); // get() returns Collection of stdClass (property access), not array
       
    }

    public function renewalsVsUpgradesSeries(string $mode = 'week'): array
    {
        $mode = strtolower($mode);
        if (!in_array($mode, ['week', 'month'], true)) {
            $mode = 'week';
        }

        $labels = [];
        $renewals = [];
        $upgrades = [];

        if ($mode === 'month') {
            for ($i = 29; $i >= 0; $i--) {
                $day = Carbon::now()->subDays($i);
                $start = $day->copy()->startOfDay();
                $end = $day->copy()->endOfDay();
                $labels[] = $day->format('d M');
                $renewals[] = $this->countCategoryBetween('renewal', $start, $end);
                $upgrades[] = $this->countCategoryBetween('upgrade', $start, $end);
            }
        } else {
            for ($i = 3; $i >= 0; $i--) {
                $start = Carbon::now()->subWeeks($i)->startOfWeek();
                $end = Carbon::now()->subWeeks($i)->endOfWeek();
                $labels[] = $start->format('d M') . ' - ' . $end->format('d M');
                $renewals[] = $this->countCategoryBetween('renewal', $start, $end);
                $upgrades[] = $this->countCategoryBetween('upgrade', $start, $end);
            }
        }

        return [$labels, $renewals, $upgrades];
    }

    public function partnerCustomerAnalytics(int $partnerId): array
    {
        if ($partnerId <= 0 || !Schema::hasTable('customers')) {
            return [
                'customers' => collect(),
                'total_customers' => 0,
                'total_domains' => 0,
                'total_orders' => 0,
                'revenue_generated' => 0.0,
                'chart_labels' => [],
                'chart_values' => [],
            ];
        }

        $hasOrders = Schema::hasTable('orders');
        $hasOrderType = $hasOrders && Schema::hasColumn('orders', 'order_type');
        $hasTotalAmount = $hasOrders && Schema::hasColumn('orders', 'total_amount');
        $hasSubtotal = $hasOrders && Schema::hasColumn('orders', 'subtotal');

        $customers = Customer::query()
            ->where('partner_id', $partnerId)
            ->withCount([
                'orders as total_orders_count' => function ($q) use ($partnerId, $hasOrderType) {
                    $q->where('partner_id', $partnerId);
                    if ($hasOrderType) {
                        $q->where('order_type', 'b2b');
                    }
                },
            ])
            ->addSelect([
                'domain_count' => DB::table('orders as o')
                    ->selectRaw("COUNT(DISTINCT NULLIF(TRIM(COALESCE(o.domain_name, '')), ''))")
                    ->whereColumn('o.user_id', 'customers.id')
                    ->where('o.partner_id', $partnerId)
                    ->when($hasOrderType, function ($q) {
                        $q->where('o.order_type', 'b2b');
                    }),
                'revenue_completed' => DB::table('orders as o')
                    ->selectRaw(
                        $hasTotalAmount
                            ? 'COALESCE(SUM(COALESCE(o.total_amount, 0)), 0)'
                            : ($hasSubtotal ? 'COALESCE(SUM(COALESCE(o.subtotal, 0)), 0)' : 'COALESCE(SUM(COALESCE(o.price, 0) * COALESCE(o.quantity, 1)), 0)')
                    )
                    ->whereColumn('o.user_id', 'customers.id')
                    ->where('o.partner_id', $partnerId)
                    ->whereIn('o.status', ['completed', 'active'])
                    ->when($hasOrderType, function ($q) {
                        $q->where('o.order_type', 'b2b');
                    }),
            ])
            ->orderBy('name')
            ->get();

        $totalCustomers = (int) $customers->count();
        $totalDomains = (int) $customers->sum(function ($c) {
            return (int) ($c->domain_count ?? 0);
        });
        $totalOrders = (int) $customers->sum(function ($c) {
            return (int) ($c->total_orders_count ?? 0);
        });
        $revenueGenerated = (float) $customers->sum(function ($c) {
            return (float) ($c->revenue_completed ?? 0);
        });

        $top = $customers
            ->sortByDesc(function ($c) {
                return (int) ($c->total_orders_count ?? 0);
            })
            ->take(10)
            ->values();

        return [
            'customers' => $customers,
            'total_customers' => $totalCustomers,
            'total_domains' => $totalDomains,
            'total_orders' => $totalOrders,
            'revenue_generated' => $revenueGenerated,
            'chart_labels' => $top->map(fn ($c) => (string) ($c->name ?? ('#' . $c->id)))->all(),
            'chart_values' => $top->map(fn ($c) => (int) ($c->total_orders_count ?? 0))->all(),
        ];
    }

    private function countCategoryBetween(string $category, Carbon $start, Carbon $end): int
    {
        if (!Schema::hasTable('orders')) {
            return 0;
        }

        $query = DB::table('orders')
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('status', ['completed', 'active']);

        if (Schema::hasColumn('orders', 'order_type')) {
            $query->where('order_type', 'b2b');
        }
        if (Schema::hasColumn('orders', 'order_category')) {
            $query->where('order_category', $category);
        }

        return (int) $query->count();
    }

    private function extractFirstInt(string $value): int
    {
        if (preg_match('/(\d+)/', $value, $matches)) {
            return (int) $matches[1];
        }

        return 1;
    }

    private function parseStorageToNumeric(string $storage): float
    {
        if (!preg_match('/([\d\.]+)/', $storage, $num)) {
            return 0.0;
        }

        $value = (float) $num[1];
        $unit = strtoupper((string) preg_replace('/[^a-zA-Z]/', '', $storage));

        if (str_contains($unit, 'TB')) {
            return $value * 1024;
        }
        if (str_contains($unit, 'GB')) {
            return $value;
        }
        if (str_contains($unit, 'MB')) {
            return $value / 1024;
        }

        return $value;
    }

    private function planStorageString(Plan $plan): string
    {
        $storageGb = data_get($plan, 'storage_gb');
        if ($storageGb !== null && $storageGb !== '') {
            return (string) $storageGb . ' GB';
        }

        return (string) ($plan->storage ?? '0');
    }
}
