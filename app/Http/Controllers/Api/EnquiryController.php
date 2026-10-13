<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Plan;
use App\Services\UpgradeProrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EnquiryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'partner_id' => ['required', 'integer', 'exists:pgsql_auth.partners,id'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => true,
                'message' => $validator->errors()->first(),
                'data' => null,
            ], 422);
        }

        $validated = $validator->validated();

        $enquiry = Enquiry::query()->create([
            'partner_id' => (int) $validated['partner_id'],
            'message' => trim((string) $validated['message']),
            'status' => 'new',
        ]);

        return response()->json([
            'success' => true,
            'error' => false,
            'message' => 'Your enquiry has been submitted successfully.',
            'data' => [
                'id' => (int) $enquiry->id,
                'partner_id' => (int) $enquiry->partner_id,
            ],
        ], 201);
    }

    public function upgrade(Request $request)
    {
        try {
            // Validate plan_id presence and existence in plans table
            $validated = $request->validate([
                'partner_id' => ['nullable', 'integer'],
                'order_id' => ['nullable', 'integer', 'exists:pgsql_billing.orders,id'],
                'plan_id' => 'required|integer|exists:pgsql_product.plans,id',
                'licenses' => 'nullable|integer|min:1',
                'new_plan_id' => 'nullable|integer|exists:pgsql_product.plans,id',
                'variant_id' => ['nullable', 'integer'],
            ]);

            $partnerId = (int) ($validated['partner_id']
                ?? $request->input('partner_id', $request->input('partnerId')));
            if ($partnerId <= 0 && !empty($validated['order_id'])) {
                $partnerId = (int) (Order::query()->whereKey((int) $validated['order_id'])->value('partner_id') ?? 0);
            }
            $partnerDiscount = \App\Support\PartnerDiscountType::resolveForPartner($partnerId);
            $partnerDiscountType = $partnerDiscount['type'];
            $partnerDiscountTypeLabel = $partnerDiscount['label'];

            // Load the current plan with its provider
            $currentPlan = Plan::with(['provider'])->find($validated['plan_id']);
           
            if (!$currentPlan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plan not found',
                ], 404);
            }

            // Defensive: Ensure sr_no is available and numeric
            $srNo = $currentPlan->sr_no;
            if (!is_numeric($srNo) || (int)$srNo <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid sr_no in plan',
                    'debug'   => $srNo,
                    'plan_id' => $currentPlan->id
                ], 400);
            }
            $srNo = (int) $srNo;

            $currentPriceMeta = \App\Support\PartnerDiscountType::applyToPlanPrices(
                $currentPlan->actual_price ?? null,
                $currentPlan->price ?? null,
                $partnerDiscountType
            );

            $currentPlanResult = [
                'name'           => $currentPlan->name ?? $currentPlan->title ?? '-',
                'variant_id'     => (int) ($currentPlan->variant_id ?? 0) ?: null,
                'remaining_days' => null,
                'amount'         => $this->formatPlanPriceValue($currentPriceMeta['price']),
                'price'          => $this->formatPlanPriceValue($currentPriceMeta['price']),
                'formatted_price'=> $this->formatPlanPrice($currentPriceMeta['price']),
                'price_type'     => $currentPriceMeta['price_type'],
                'price_type_label' => $currentPriceMeta['price_type_label'],
            ];
            $currentPlanAmount = (float) $currentPriceMeta['price'];

            $proration = null;
            if (!empty($validated['order_id'])) {
                $sourceOrder = Order::query()->find((int) $validated['order_id']);
                if ($sourceOrder) {
                    $sourceItem = OrderItem::query()
                        ->where('order_id', (int) $sourceOrder->id)
                        ->orderBy('id')
                        ->first();
                    $upgradeLicenses = !empty($validated['licenses'])
                        ? max(1, (int) $validated['licenses'])
                        : max(1, (int) ($sourceOrder->quantity ?? 1));
                    $newUnitPrice = 0.0;
                    if (!empty($validated['new_plan_id'])) {
                        $newPlan = Plan::query()->find((int) $validated['new_plan_id']);
                        if ($newPlan) {
                            $newPriceMeta = \App\Support\PartnerDiscountType::applyToPlanPrices(
                                $newPlan->actual_price ?? null,
                                $newPlan->price ?? null,
                                $partnerDiscountType
                            );
                            $newUnitPrice = (float) $newPriceMeta['price'];
                        }
                    }
                    $proration = app(UpgradeProrationService::class)->resolve(
                        $sourceOrder,
                        $sourceItem,
                        $newUnitPrice,
                        $upgradeLicenses
                    );
                    $currentPlanResult['remaining_days'] = (int) ($proration['remaining_days'] ?? 0);
                    $prorationUnit = round((float) ($proration['current_unit_price'] ?? 0), 2);
                    $currentAmount = $prorationUnit > 0
                        ? $prorationUnit
                        : (float) ($currentPriceMeta['price'] ?? 0);
                    $currentPlanResult['amount'] = $this->formatPlanPriceValue($currentAmount);
                    $currentPlanResult['price'] = $this->formatPlanPriceValue($currentAmount);
                    $currentPlanResult['formatted_price'] = $this->formatPlanPrice($currentAmount);
                    $currentPlanResult['current_licenses'] = (int) ($proration['current_licenses'] ?? 0);
                    $currentPlanResult['credit_licenses'] = (int) ($proration['credit_licenses'] ?? 0);
                    $currentPlanResult['pro_rata_adjustment'] = $this->formatPlanPriceValue($proration['pro_rata_adjustment'] ?? 0);
                    $currentPlanResult['remaining_value'] = $this->formatPlanPriceValue($proration['remaining_value'] ?? 0);
                    $currentPlanResult['applied_pro_rata'] = $this->formatPlanPriceValue($proration['applied_pro_rata'] ?? 0);
                    $currentPlanResult['daily_rate_per_license'] = $this->formatPlanPriceValue($proration['daily_rate_per_license'] ?? 0);
                }
            }

            $variantId = (int) ($validated['variant_id'] ?? $currentPlan->variant_id ?? 0);

            // Retrieve plans with amount greater than current plan amount (same provider), in DESC amount order.
            $upgradePlansQuery = Plan::query()
            ->where('provider_id', $currentPlan->provider_id)
            ->where('id', '!=', $currentPlan->id)
            ->where('status', 1) 
            ->whereRaw('COALESCE(price, 0) > ?', [$currentPlanAmount]);

            if ($variantId > 0) {
                $upgradePlansQuery->where('variant_id', $variantId);
            }

            $upgradePlans = $upgradePlansQuery
            ->orderByRaw('COALESCE(price, 0) ASC')
            ->get()
            ->map(function ($p) use ($partnerDiscountType) {
                $priceMeta = \App\Support\PartnerDiscountType::applyToPlanPrices(
                    $p->actual_price ?? null,
                    $p->price ?? null,
                    $partnerDiscountType
                );

                return [
                    'plan_id'  => $p->id,
                    'variant_id' => (int) ($p->variant_id ?? 0) ?: null,
                    'name'     => $p->name ?? $p->title ?? '-',
                    'price'    => $this->formatPlanPriceValue($priceMeta['price']),
                    'amount'   => $this->formatPlanPriceValue($priceMeta['price']),
                    'formatted_price' => $this->formatPlanPrice($priceMeta['price']),
                    'price_type' => $priceMeta['price_type'],
                    'price_type_label' => $priceMeta['price_type_label'],
                    'market_price' => $this->formatPlanPriceValue($priceMeta['market_price']),
                    'formatted_market_price' => $this->formatPlanPrice($priceMeta['market_price']),
                    'distributor_price' => $this->formatPlanPriceValue($priceMeta['distributor_price']),
                    'formatted_distributor_price' => $this->formatPlanPrice($priceMeta['distributor_price']),
                    'actual_price' => $this->formatPlanPriceValue($priceMeta['actual_price']),
                    'formatted_actual_price' => $this->formatPlanPrice($priceMeta['actual_price']),
                    'features' => $p->getFeaturesArrayAttribute(),
                ];
            })->values();

            return response()->json([
                'success' => true,
                'message' => 'Upgrade plan details fetched successfully',
                'data' => [
                    'plan_id'       => $currentPlan->id,
                    'variant_id'    => $variantId > 0 ? $variantId : null,
                    'current_plan'  => $currentPlanResult,
                    'proration'     => $proration,
                    'plans'         => $upgradePlans,
                    'upgrade_plans' => $upgradePlans,
                    'partner_discount_type' => $partnerDiscountType,
                    'partner_discount_type_label' => $partnerDiscountTypeLabel,
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors'  => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Numeric price with 2 decimals (e.g. "792.00") for API number fields.
     */
    private function formatPlanPriceValue(mixed $amount): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * Display price with currency symbol (e.g. "₹792.00").
     */
    private function formatPlanPrice(mixed $amount): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        return '₹' . number_format((float) $amount, 2);
    }
}
