<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Plan;
use Carbon\Carbon;

class UpgradeProrationService
{
    /**
     * Total subscription term days from start → end (e.g. 364 or 365).
     */
    public static function subscriptionTotalDays(Carbon $start, Carbon $end): int
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->startOfDay();

        if ($end->lessThanOrEqualTo($start)) {
            return 0;
        }

        return max(1, (int) $start->diffInDays($end));
    }

    /**
     * Days left in the subscription term.
     * totalDays = start → end (364 or 365 by dates),
     * usedDays = start → today (0 if today ≤ start),
     * remaining = totalDays − usedDays.
     */
    public static function subscriptionRemainingDays(Carbon $start, Carbon $end, ?Carbon $now = null): int
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->startOfDay();
        $now = ($now ?? Carbon::now())->copy()->startOfDay();

        $totalDays = self::subscriptionTotalDays($start, $end);
        if ($totalDays <= 0) {
            return 0;
        }

        if ($now->greaterThanOrEqualTo($end)) {
            return 0;
        }

        // Before / on start date → full term remaining
        if ($now->lessThanOrEqualTo($start)) {
            return $totalDays;
        }

        $usedDays = max(0, (int) $start->diffInDays($now));

        return max(0, min($totalDays, $totalDays - $usedDays));
    }

   
    public function resolve(
        ?Order $sourceOrder,
        ?OrderItem $sourceItem,
        float $newUnitPrice,
        int $upgradeLicenses,
        float $gstRate = 18.0,
        float $couponPercent = 0.0,
        ?int $newPlanId = null,
        ?int $currentLicensesOverride = null
    ): array {
        $upgradeLicenses = max(1, $upgradeLicenses);

        if ($newPlanId > 0 && $sourceOrder) {
            try {
                $calc = app(RenewalService::class)->upgradeOrderCalculation(array_filter([
                    'partner_id' => (int) $sourceOrder->partner_id,
                    'order_id' => (int) $sourceOrder->id,
                    'plan_id' => $newPlanId,
                    'licenses' => $upgradeLicenses,
                    'order_item_id' => $sourceItem ? (int) $sourceItem->id : null,
                    'coupon_percent' => $couponPercent,
                    'gst_rate' => $gstRate,
                    'current_upgrade_licenses' => $upgradeLicenses,
                    'current_licenses' => $currentLicensesOverride,
                ], static fn ($v) => $v !== null && $v !== ''));

                return $this->normalizePlanChangePricing(
                    $calc,
                    $upgradeLicenses,
                    $newUnitPrice,
                    $gstRate,
                    $couponPercent,
                    $currentLicensesOverride
                );
            } catch (\Throwable $e) {
                // Fall back to line-based proration when RenewalService is unavailable.
            }
        }

        return $this->resolveFromSourceLine(
            $sourceOrder,
            $sourceItem,
            $newUnitPrice,
            $upgradeLicenses,
            $gstRate,
            $couponPercent,
            $currentLicensesOverride
        );
    }

    /**
     * Extra seats on the same plan: charge remaining term only, with no remaining-credit offset.
     * new_plan_cost = (unit_price / total_plan_days) × remaining_days × extra_licenses
     */
    public function resolveLicenseAdd(
        ?Order $sourceOrder,
        ?OrderItem $sourceItem,
        float $unitPrice,
        int $extraLicenses,
        float $gstRate = 18.0,
        float $couponPercent = 0.0
    ): array {
        $extraLicenses = max(1, $extraLicenses);
        $base = $this->resolveFromSourceLine(
            $sourceOrder,
            $sourceItem,
            $unitPrice,
            $extraLicenses,
            $gstRate,
            $couponPercent
        );

        $remainingDays = max(0, (int) ($base['remaining_days'] ?? 0));
        $totalPlanDays = max(1, (int) ($base['total_plan_days'] ?? 365));
        $unitPrice = round(max(0, $unitPrice), 2);
        $newPlanCost = $remainingDays > 0
            ? round(($unitPrice / $totalPlanDays) * $remainingDays * $extraLicenses, 2)
            : round($unitPrice * $extraLicenses, 2);
        $couponDiscount = $couponPercent > 0 ? round($newPlanCost * $couponPercent / 100, 2) : 0.0;
        $taxableAfter = max(0, round($newPlanCost - $couponDiscount, 2));
        $gstAmount = $taxableAfter > 0
            ? round(app(RenewalService::class)->calculateGst($taxableAfter, $gstRate), 2)
            : 0.0;
        $payable = round($taxableAfter + $gstAmount, 2);

        return array_merge($base, [
            'applied_pro_rata' => 0.0,
            'remaining_value' => 0.0,
            'remaining_credit' => 0.0,
            'pro_rata_adjustment' => 0.0,
            'credit_licenses' => 0,
            'eligible_license_count' => 0,
            'new_unit_price' => $unitPrice,
            'new_plan_cost' => $newPlanCost,
            'new_plan_price' => $newPlanCost,
            'subtotal' => $newPlanCost,
            'taxable_amount' => $taxableAfter,
            'coupon_discount' => $couponDiscount,
            'gst_amount' => $gstAmount,
            'final_payable' => $payable,
            'payable_amount' => $payable,
            'upgrade_licenses' => $extraLicenses,
            'added_licenses' => $extraLicenses,
        ]);
    }

    /**
     * Ensure GST/payable always use: taxable = subtotal - min(remaining_value, subtotal).
     */
    private function normalizePlanChangePricing(
        array $calc,
        int $upgradeLicenses,
        float $newUnitPrice,
        float $gstRate,
        float $couponPercent,
        ?int $currentLicensesOverride = null
    ): array {
        $currentLicenses = $currentLicensesOverride !== null && $currentLicensesOverride > 0
            ? max(1, $currentLicensesOverride)
            : max(1, (int) ($calc['subscription_quantity'] ?? $calc['current_licenses'] ?? 1));
        $creditLicenses = min($upgradeLicenses, $currentLicenses);
        $currentUnitPrice = round((float) ($calc['old_unit_price'] ?? $calc['current_unit_price'] ?? 0), 2);
        $remainingDays = (int) ($calc['remaining_days'] ?? 0);
        // Use actual term days from start→end (364 or 365), not hardcoded 365.
        $totalPlanDays = max(1, (int) ($calc['total_plan_days'] ?? 365));

        // Always recompute remaining from licenses being upgraded so update-cart stays in sync.
        // Includes license-add seats when currentLicensesOverride / merged qty is passed.
        $remainingValue = ($currentUnitPrice > 0 && $remainingDays > 0)
            ? round(($currentUnitPrice / $totalPlanDays) * $remainingDays * $creditLicenses, 2)
            : round((float) ($calc['remaining_value'] ?? $calc['remaining_credit'] ?? $calc['pro_rata_adjustment'] ?? 0), 2);

        $newPlanCost = round((float) ($calc['new_plan_cost'] ?? $calc['new_plan_price'] ?? ($newUnitPrice * $upgradeLicenses)), 2);
        // Prefer explicit cost from licenses × unit when caller passes new unit price.
        if ($newUnitPrice > 0) {
            $newPlanCost = round($newUnitPrice * $upgradeLicenses, 2);
        }
        $appliedProRata = round(min($remainingValue, $newPlanCost), 2);
        $taxable = max(0, round($newPlanCost - $appliedProRata, 2));
        $couponDiscount = $couponPercent > 0 ? round($taxable * $couponPercent / 100, 2) : 0.0;
        $taxableAfter = max(0, round($taxable - $couponDiscount, 2));
        $gstAmount = $taxableAfter > 0
            ? round(app(RenewalService::class)->calculateGst($taxableAfter, $gstRate), 2)
            : 0.0;
        $payable = round($taxableAfter + $gstAmount, 2);

        return array_merge($calc, [
            'applied_pro_rata' => $appliedProRata,
            'new_unit_price' => round(max(0, $newUnitPrice), 2),
            'new_plan_cost' => $newPlanCost,
            'new_plan_price' => $newPlanCost,
            'subtotal' => $newPlanCost,
            'remaining_value' => $remainingValue,
            'remaining_credit' => $remainingValue,
            'pro_rata_adjustment' => $remainingValue,
            'taxable_amount' => $taxableAfter,
            'coupon_discount' => $couponDiscount,
            'gst_amount' => $gstAmount,
            'final_payable' => $payable,
            'payable_amount' => $payable,
            'upgrade_licenses' => $upgradeLicenses,
            'credit_licenses' => $creditLicenses,
            'eligible_license_count' => $creditLicenses,
            'current_licenses' => $currentLicenses,
            'current_unit_price' => $currentUnitPrice,
            'daily_rate_per_license' => $currentUnitPrice > 0 ? round($currentUnitPrice / $totalPlanDays, 2) : 0.0,
            'total_plan_days' => $totalPlanDays,
            'remaining_days' => $remainingDays,
        ]);
    }

    private function resolveFromSourceLine(
        ?Order $sourceOrder,
        ?OrderItem $sourceItem,
        float $newUnitPrice,
        int $upgradeLicenses,
        float $gstRate,
        float $couponPercent,
        ?int $currentLicensesOverride = null
    ): array {
        if (!$sourceOrder && $sourceItem && (int) ($sourceItem->order_id ?? 0) > 0) {
            $sourceOrder = Order::query()->find((int) $sourceItem->order_id);
        }

        $currentUnitPrice = 0.0;
        $currentLicenses = 1;
        $remainingDays = 0;
        $totalPlanDays = 365;

        if ($sourceOrder) {
            if ($sourceItem) {
                $currentLicenses = max(1, (int) ($sourceItem->quantity ?? 1));
                $currentUnitPrice = (float) ($sourceItem->unit_price ?? 0);
                if ($currentUnitPrice <= 0) {
                    $itemQty = max(1, (int) ($sourceItem->quantity ?? 1));
                    $itemTotal = (float) ($sourceItem->total_amount ?? 0);
                    if ($itemTotal > 0) {
                        $currentUnitPrice = round($itemTotal / $itemQty, 2);
                    }
                }
            } else {
                $currentLicenses = max(1, (int) ($sourceOrder->quantity ?? 1));
                $currentUnitPrice = (float) ($sourceOrder->price ?? 0);
            }

            // Include activated same-plan license-add seats (addon) in remaining credit.
            if ($currentLicensesOverride !== null && $currentLicensesOverride > 0) {
                $currentLicenses = max(1, $currentLicensesOverride);
            } else {
                $parentId = (int) ($sourceOrder->id ?? 0);
                $planId = (int) ($sourceItem->plan_id ?? $sourceOrder->plan_id ?? 0);
                if ($parentId > 0 && $planId > 0) {
                    $addonChildren = Order::query()
                        ->where('parent_order_id', $parentId)
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
                                $currentLicenses += max(1, (int) ($childItem->quantity ?? 1));
                            }
                        } else {
                            $currentLicenses += max(1, (int) ($child->quantity ?? 1));
                        }
                    }
                }
            }

            if ($currentUnitPrice <= 0) {
                $sourcePlanId = $sourceItem
                    ? (int) ($sourceItem->plan_id ?? 0)
                    : (int) ($sourceOrder->plan_id ?? 0);
                if ($sourcePlanId > 0) {
                    $sourcePlan = Plan::query()->find($sourcePlanId);
                    if ($sourcePlan) {
                        $partnerType = \App\Support\PartnerDiscountType::normalize(
                            (string) (
                                \App\Models\Partner::query()
                                    ->whereKey((int) ($sourceOrder->partner_id ?? 0))
                                    ->value('partner_discount_type') ?? ''
                            )
                        );
                        $currentUnitPrice = (float) \App\Support\PartnerDiscountType::fromPlan(
                            $sourcePlan,
                            $partnerType
                        )['price'];
                    }
                    if ($currentUnitPrice <= 0) {
                        $currentUnitPrice = (float) (Plan::query()->whereKey($sourcePlanId)->value('price') ?? 0);
                    }
                    if ($currentUnitPrice <= 0) {
                        $currentUnitPrice = (float) (Plan::query()->whereKey($sourcePlanId)->value('actual_price') ?? 0);
                    }
                }
            }

            $start = !empty($sourceOrder->subscription_start_date)
                ? Carbon::parse((string) $sourceOrder->subscription_start_date)->startOfDay()
                : Carbon::parse((string) $sourceOrder->created_at)->startOfDay();
            $end = !empty($sourceOrder->subscription_end_date)
                ? Carbon::parse((string) $sourceOrder->subscription_end_date)->startOfDay()
                : app(RenewalService::class)->subscriptionEndDate($start, $sourceOrder->duration ?? '12 month')->startOfDay();
            $now = Carbon::now()->startOfDay();
            // totalDays from start→end (364/365), remaining = total − used
            $totalPlanDays = max(1, self::subscriptionTotalDays($start, $end));
            $remainingDays = self::subscriptionRemainingDays($start, $end, $now);
        } elseif ($currentLicensesOverride !== null && $currentLicensesOverride > 0) {
            $currentLicenses = max(1, $currentLicensesOverride);
        }

        $creditLicenses = min($upgradeLicenses, $currentLicenses);
        $remainingValue = ($currentUnitPrice > 0 && $remainingDays > 0 && $totalPlanDays > 0)
            ? round(($currentUnitPrice / $totalPlanDays) * $remainingDays * $creditLicenses, 2)
            : 0.0;
        $newPlanCost = round(max(0, $newUnitPrice) * $upgradeLicenses, 2);
        $appliedProRata = min($remainingValue, $newPlanCost);
        $taxable = max(0, round($newPlanCost - $appliedProRata, 2));
        $couponDiscount = $couponPercent > 0 ? round($taxable * $couponPercent / 100, 2) : 0.0;
        $taxableAfter = max(0, round($taxable - $couponDiscount, 2));
        $gstAmount = $taxableAfter > 0
            ? round(app(RenewalService::class)->calculateGst($taxableAfter, $gstRate), 2)
            : 0.0;
        $payable = round($taxableAfter + $gstAmount, 2);

        $prorationFormula = ($currentUnitPrice > 0 && $remainingDays > 0 && $creditLicenses > 0)
            ? sprintf(
                '₹%s ÷ %d × %d days × %d license(s) = ₹%s',
                number_format($currentUnitPrice, 2),
                $totalPlanDays,
                $remainingDays,
                $creditLicenses,
                number_format($remainingValue, 2)
            )
            : '';

        return [
            'current_unit_price' => round($currentUnitPrice, 2),
            'current_licenses' => $currentLicenses,
            'credit_licenses' => $creditLicenses,
            'upgrade_licenses' => $upgradeLicenses,
            'total_plan_days' => $totalPlanDays,
            'remaining_days' => $remainingDays,
            'daily_rate_per_license' => $currentUnitPrice > 0 ? round($currentUnitPrice / $totalPlanDays, 2) : 0.0,
            'remaining_value' => $remainingValue,
            'remaining_credit' => $remainingValue,
            'pro_rata_adjustment' => $remainingValue,
            'applied_pro_rata' => $appliedProRata,
            'new_unit_price' => round(max(0, $newUnitPrice), 2),
            'new_plan_cost' => $newPlanCost,
            'new_plan_price' => $newPlanCost,
            'subtotal' => $newPlanCost,
            'taxable_amount' => $taxableAfter,
            'coupon_discount' => $couponDiscount,
            'gst_amount' => $gstAmount,
            'final_payable' => $payable,
            'payable_amount' => $payable,
            'proration_formula' => $prorationFormula,
        ];
    }
}
