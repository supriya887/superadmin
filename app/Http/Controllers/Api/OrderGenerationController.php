<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SuperAdmin\BillingController;
use App\Mail\PlanRenewedMail;
use App\Mail\PlanUpgradedMail;
use App\Mail\OrderProcessingMail;
use App\Models\AdminSetting;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\NotificationPreference;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPoDetail;
use App\Models\Partner;
use App\Models\PartnerAadhaarVerification;
use App\Models\PersonalAccessToken;
use App\Models\Plan;
use App\Services\B2bSubscriptionRenewalService;
use App\Services\B2bSubscriptionUpgradeService;
use App\Services\PartnerAadhaarVerificationService;
use App\Services\PartnerCartService;
use App\Services\RenewalService;
use App\Services\SmsService;
use App\Services\UpgradeProrationService;
use App\Services\WhatsAppService;
use App\Models\InvoiceMst;
use App\Models\InvoiceSub;
use App\Models\OrderPoSub;
use App\Models\Enquiry;
use App\Models\Provider;
use App\Models\PurchaseRequest;
use App\Services\OrderCancellationPolicy;
use App\Support\AadhaarVerificationGateway;
use App\Support\GstSplit;
use App\Support\OrderDomainHelper;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderGenerationController extends Controller
{
    protected Order $orders;
    protected OrderItem $orderItems;
    protected OrderPoDetail $orderPoDetails;
    protected PartnerAadhaarVerification $partnerAadhaarVerifications;
    protected Plan $plans;
    protected AdminSetting $adminSettings;
    protected Cart $carts;
    protected Customer $customers;
    protected Partner $partners;
    protected NotificationPreference $notifications;
    protected WhatsAppService $whatsAppService;
    protected Provider $providers;
    // Order status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_SUSPENDED = 'suspended';

    public function __construct(WhatsAppService $whatsAppService)
    {
        $this->orders = new Order();
        $this->orderItems = new OrderItem();
        $this->orderPoDetails = new OrderPoDetail();
        $this->partnerAadhaarVerifications = new PartnerAadhaarVerification();
        // FIX: Explicitly set the connection for Plan
        $this->plans = new Plan();
        $this->plans->setConnection('pgsql_product');
        $this->adminSettings = new AdminSetting();
        $this->carts = new Cart();
        $this->customers = new Customer();
        $this->partners = new Partner();
        $this->notifications = new NotificationPreference();
        $this->whatsAppService = $whatsAppService;
        $this->providers = new Provider();
    }

    public function generateNewOrder(Request $request): JsonResponse
    {
        $this->normalizePoDocumentUpload($request);

        // Allow place flow without explicit partner_id (resolve from token/cart).
        $resolvedPartnerId = $this->resolvePartnerIdFromRequest($request) ?? 0;
        if ($resolvedPartnerId > 0 && !$request->filled('partner_id')) {
            $request->merge(['partner_id' => $resolvedPartnerId]);
        }
        if (!$request->filled('partner_id')) {
            $cartHint = (int) ($request->input('main_cart_id') ?? $request->input('cart_id') ?? 0);
            if ($cartHint > 0) {
                $fromCart = (int) ($this->carts->newQuery()->whereKey($cartHint)->value('partner_id') ?? 0);
                if ($fromCart > 0) {
                    $request->merge(['partner_id' => $fromCart]);
                }
            }
        }

        $validated = $request->validate(array_merge([
            'partner_id' => ['required', 'integer', Rule::exists(Partner::class, 'id')],
            'main_cart_id' => ['required', 'integer', Rule::exists(Cart::class, 'id')],
            'po_mode' => ['nullable', 'in:generated,uploaded'],
            'po_number' => ['nullable', 'string', 'max:100'],
            'po_date' => ['nullable', 'date'],
            // Final place from partner. Without this, PO/draft stays in cart only.
            'place_order' => ['nullable', 'boolean'],
            'finalize' => ['nullable', 'boolean'],
            'confirm_place' => ['nullable', 'boolean'],
        ], $this->poDocumentValidationRules(false)));

        $partnerId = (int) $validated['partner_id'];
        $poMode = app(\App\Services\OrderPoDocumentService::class)
            ->normalizeMode((string) ($validated['po_mode'] ?? 'generated'));
        // File present ⇒ treat as upload even if FE omitted/mis-sent po_mode.
        if ($request->hasFile('po_document')) {
            $poMode = \App\Services\OrderPoDocumentService::MODE_UPLOADED;
        }

        $cartQuery = Cart::query()
            ->with(['items.plan'])
            ->where('partner_id', $partnerId);

        if (!empty($validated['main_cart_id'])) {
            $cartQuery->where('id', (int) $validated['main_cart_id']);
        }

        $parentCart = $cartQuery->orderByDesc('id')->first();

        if (!$parentCart) {
            return response()->json([
                'success' => false,
                'message' => 'Cart not found for this partner.',
            ], 404);
        }

        $cartId = (int) ($parentCart->id ?? 0);
        $uploadError = $this->storeUploadedPoOnCartIfNeeded($request, $cartId, $poMode);
        if ($uploadError !== null) {
            return $uploadError;
        }
        $customerId = (int) ($parentCart->customer_id ?? 0);
        $requestDomain = OrderDomainHelper::normalizeStored($request->input('domain_name'));
        $domainName = app(PartnerCartService::class)->cartDomainNameString($parentCart);
        if ($domainName === '') {
            $domainName = $this->resolveDomainNameForOrder($parentCart, $cartId);
        }
        if ($requestDomain !== '') {
            $domainName = $requestDomain;
        }
        if ($domainName !== '' && OrderDomainHelper::normalizeStored($parentCart->domain_name ?? null) !== $domainName) {
            $parentCart->domain_name = $domainName;
            $parentCart->save();
        }
        $companyName = '';
        if ($customerId > 0) {
            $companyName = trim((string) ($this->customers->newQuery()->whereKey($customerId)->value('company_name') ?? ''));
        }

        // eSign removed — never gate on aadhaar_mandatory. Place Order is the only gate.
        $signRequiredFlag = 'no';
        $signRequired = false;

        $gstRate = (float) ($parentCart->gst_percentage ?? 18);
        $applyGst = (float) ($parentCart->gst_amount ?? 0) > 0 || $gstRate > 0;
        $cartGrandTotal = round((float) ($parentCart->grand_total ?? 0), 2);
        $cartGstAmount = round((float) ($parentCart->gst_amount ?? 0), 2);
        $cartDiscount = round((float) ($parentCart->discount_amount ?? 0), 2);
        $cartProRata = round((float) ($parentCart->pro_rata_adjustment ?? 0), 2);
        $couponName = !empty($parentCart->coupen) ? strtoupper(trim((string) $parentCart->coupen)) : '';
        $cartType = strtolower(trim((string) ($parentCart->type ?? 'standard')));
        $orderCategory = match ($cartType) {
            Cart::TYPE_UPGRADE => 'upgrade',
            'license_add' => 'addon',
            default => 'new',
        };

        $lineRows = [];
        foreach ($parentCart->items as $cartLine) {
            $plan = $cartLine->plan ?? Plan::query()->find((int) $cartLine->plan_id);
            if (!$plan) {
                continue;
            }
            $licenses = max(1, (int) $cartLine->licenses);
            $unitPrice = round((float) ($cartLine->unit_price ?? 0), 2);
            $lineGross = round((float) ($cartLine->grand_total ?? ($unitPrice * $licenses)), 2);
            $lineRows[] = [
                'cartLine' => $cartLine,
                'plan' => $plan,
                'licenses' => $licenses,
                'unitPrice' => $unitPrice,
                'lineGrand' => $lineGross,
            ];
        }

        if ($lineRows === []) {
            return response()->json([
                'success' => false,
                'message' => 'Cart has no valid plan lines.',
            ], 422);
        }
        $cartSubtotalPreview = round(array_sum(array_column($lineRows, 'lineGrand')), 2);

        // --- Get provider_id from plan table for the 1st plan (as asked) ---
        $firstPlan = null;
        if (!empty($lineRows)) {
            $firstPlan = $lineRows[0]['plan'] ?? null;
        }
        $providerIdFromPlan = null;
        if ($firstPlan && isset($firstPlan->provider_id)) {
            $providerIdFromPlan = (int) $firstPlan->provider_id;
        } else {
            // If provider_id is not available from eager load, fallback to DB fetch.
            $firstPlanId = $firstPlan->id ?? null;
            if ($firstPlanId) {
                $providerIdFromPlan = (int) (Plan::query()->where('id', $firstPlanId)->value('provider_id') ?? null);
            }
        }
        // If provider ID still not set, fallback to partnerId if required by system
        if (!$providerIdFromPlan) {
            $providerIdFromPlan = $partnerId;
        }
        // ---------------------------------------------------------------

        // Not a final Place Order → keep PO/draft in cart only. Never insert into orders / SuperAdmin.
        if (!$this->wantsFinalPlace($request)) {
            // Cancel any leftover draft rows so they cannot appear in SuperAdmin.
            $this->cancelLeftoverDraftOrdersForCart($partnerId, $cartId);

            $plansOut = [];
            foreach ($lineRows as $row) {
                $plansOut[] = [
                    'plan_id' => (int) ($row['plan']->id ?? 0),
                    'plan_name' => (string) ($row['plan']->name ?? 'Plan'),
                    'licenses' => max(1, (int) ($row['licenses'] ?? 1)),
                    'cart_item_id' => (int) ($row['cartLine']->id ?? 0),
                    'subscription_id' => (string) ($row['cartLine']->subscription_id ?? ''),
                    'plan_amount' => round((float) ($row['lineGrand'] ?? 0), 2),
                    'domain_name' => $domainName,
                    'domain' => $domainName,
                ];
            }

            return $this->cartPendingAadhaarResponse(
                $cartId,
                $companyName,
                'Draft PO saved in cart. No order created yet — call place_order=1 to place the order.',
                [
                    'sign_required' => $signRequiredFlag,
                    'provider_id' => $providerIdFromPlan ?? null,
                    'total_amount' => round((float) $cartGrandTotal, 2),
                    'plan_amount' => round((float) $cartSubtotalPreview, 2),
                    'gst' => round((float) $cartGstAmount, 2),
                    'coupon_name' => $couponName,
                    'coupon_discount' => round((float) $cartDiscount, 2),
                    'domain_name' => $domainName,
                    'domain' => $domainName,
                    'plans' => $plansOut,
                    'po_mode' => $poMode,
                    'place_order_required' => true,
                ],
                'new'
            );
        }

        // Open drafts for this cart (legacy cleanup / promote when placing for real).
        $existingDraftIds = $this->activeDraftOrderIdsForCart($partnerId, $cartId, $customerId, $domainName);
        $draftPlanIds = $this->planIdsForDraftOrders($existingDraftIds);

        // Plans already on a draft stay there; only NEW cart plans get a new order on re-proceed.
        $newLineRows = array_values(array_filter(
            $lineRows,
            static fn(array $row) => !in_array((int) ($row['plan']->id ?? 0), $draftPlanIds, true)
        ));
        $existingDraftId = (int) ($existingDraftIds[0] ?? 0);

        if ($existingDraftId > 0 && $newLineRows === []) {
            $draftOrder = $this->orders->newQuery()->whereKey($existingDraftId)->first();
            $draftPo = $this->orderPoDetails->newQuery()->where('order_id', $existingDraftId)->first();

            // Final place → promote legacy draft to processing (no aadhaar gate).
            foreach ($existingDraftIds as $promoteId) {
                $this->orders->newQuery()->whereKey($promoteId)->update([
                    'status' => self::STATUS_PROCESSING,
                ]);
                $this->orderPoDetails->newQuery()->where('order_id', $promoteId)->update([
                    'sign_required' => $signRequiredFlag,
                    'journey_status' => 'placed',
                ]);
                $this->forgetDraftCartLink($cartId, $promoteId);
                $this->sendProcessingOrderNotifications($promoteId);
            }
            if ($cartId > 0) {
                app(PartnerCartService::class)->clearCartPendingAadhaar($cartId);
                $this->carts->newQuery()->whereKey($cartId)->update(['status' => 'converted']);
            }
            $draftOrder = $this->orders->newQuery()->whereKey($existingDraftId)->first();
            $draftPo = $this->orderPoDetails->newQuery()->where('order_id', $existingDraftId)->first();

            return response()->json([
                'success' => true,
                'message' => 'Order generated successfully.',
                'data' => [
                    'order_id' => $existingDraftId,
                    'ordId' => (int) ($draftOrder->id ?? $existingDraftId),
                    'provider_id' => $providerIdFromPlan, // Use provider_id from plan
                    'order_ids' => $existingDraftIds,
                    'status' => self::STATUS_PROCESSING,
                    'sign_required' => $signRequiredFlag,
                    'aadhaar_pending' => false,
                    'draft_cart' => false,
                    'draft_order' => null,
                    'company_name' => $companyName,
                    'subscription_id' => (string) (OrderItem::query()->where('order_id', $existingDraftId)->orderBy('id')->value('subscription_id') ?? ''),
                    'total_amount' => round((float) ($draftOrder->total_amount ?? $cartGrandTotal), 2),
                    'plan_amount' => round((float) ($draftOrder->subtotal ?? $draftOrder->total_amount ?? $cartGrandTotal), 2),
                    'gst' => round((float) ($draftOrder->gst_amount ?? $cartGstAmount), 2),
                    'coupon_name' => $couponName,
                    'coupon_discount' => round((float) ($draftOrder->discount_amount ?? $cartDiscount), 2),
                    'po_link' => $draftPo?->po_document_url ?? null,
                    'po_number' => $draftPo?->po_number ?? null,
                    'cart_id' => $cartId,
                    'orders' => [[
                        'order_id' => $existingDraftId,
                        'order_no' => (string) ($draftOrder->order_no ?? ''),
                        'status' => self::STATUS_PROCESSING,
                        'domain_name' => (string) ($draftOrder->domain_name ?? $domainName),
                        'transfer_domain' => $draftOrder->transfer_domain ?? null,
                        'company_name' => $companyName,
                        'subscription_id' => (string) (OrderItem::query()->where('order_id', $existingDraftId)->orderBy('id')->value('subscription_id') ?? ''),
                        'plan_amount' => round((float) ($draftOrder->subtotal ?? $draftOrder->total_amount ?? $cartGrandTotal), 2),
                        'gst' => round((float) ($draftOrder->gst_amount ?? $cartGstAmount), 2),
                        'coupon_name' => $couponName,
                        'coupon_discount' => round((float) ($draftOrder->discount_amount ?? $cartDiscount), 2),
                        'po' => [
                            'po_number' => (string) ($draftPo?->po_number ?? ''),
                            'journey_status' => (string) ($draftPo?->journey_status ?? 'po_pending'),
                        ],
                    ]],
                ],
            ], 200);
        }

        // Re-proceed with extra plans while a draft is open → order ONLY the new plans.
        if ($existingDraftId > 0 && $newLineRows !== []) {
            $lineRows = $newLineRows;
        }

        if (!empty($validated['main_cart_id']) && strtolower((string) ($parentCart->status ?? '')) === 'converted') {
            return response()->json([
                'success' => true,
                'message' => 'Order already created for this cart.',
            ], 422);
        }

        $firstLine = $lineRows[0] ?? null;
        if (!$firstLine) {
            return response()->json([
                'success' => false,
                'message' => 'Cart has no valid plan lines.',
            ], 422);
        }

        $cartSubtotal = round(array_sum(array_column($lineRows, 'lineGrand')), 2);
        $lineGstShare = 0.0;
        $lineDiscountShare = 0.0;
        if ($existingDraftId > 0) {
            // New-plans-only order: price from those lines (not full cart totals).
            $cartGrandTotal = $cartSubtotal;
            if ($applyGst && $gstRate > 0) {
                $lineGstShare = round($cartSubtotal * $gstRate / 100, 2);
                $cartGrandTotal = round($cartSubtotal + $lineGstShare, 2);
            }
            $cartGstAmount = $lineGstShare;
            $cartDiscount = $lineDiscountShare;
        } elseif ($cartSubtotal <= 0) {
            $cartSubtotal = round(max(0, $cartGrandTotal - $cartGstAmount + $cartDiscount), 2);
        }

        $promoCode = !empty($parentCart->coupen)
            ? strtoupper(trim((string) $parentCart->coupen))
            : null;

        $cartServiceForDates = app(PartnerCartService::class);
        $subscriptionStartDate = Carbon::now()->startOfDay()->toDateString();
        $subscriptionEndDate = $cartServiceForDates
            ->computeSubscriptionEnd(Carbon::parse($subscriptionStartDate), $firstLine['plan'])
            ->toDateString();
        $licenseAddParentOrderId = 0;
        if ($orderCategory === 'addon') {
            $licenseAddParentOrderId = (int) ($parentCart->order_id ?? 0);
            if ($licenseAddParentOrderId > 0) {
                $parentSub = $this->orders->newQuery()->whereKey($licenseAddParentOrderId)->first();
                if ($parentSub) {
                    $subscriptionStartDate = Carbon::now()->startOfDay()->toDateString();
                    if (!empty($parentSub->subscription_end_date)) {
                        $subscriptionEndDate = Carbon::parse((string) $parentSub->subscription_end_date)
                            ->toDateString();
                    }
                }
            }
        }

        // Reaching here means eSign is done (or not required) → create REAL order only.
        $orderStatus = self::STATUS_PROCESSING;

        try {
            $orderId = (int) $this->orders->getConnection()->transaction(function () use (
                $partnerId,
                $customerId,
                $cartId,
                $firstLine,
                $domainName,
                $orderCategory,
                $cartSubtotal,
                $cartGrandTotal,
                $cartDiscount,
                $cartProRata,
                $applyGst,
                $gstRate,
                $cartGstAmount,
                $orderStatus,
                $promoCode,
                $subscriptionStartDate,
                $subscriptionEndDate,
                $parentCart,
                $lineRows,
                $licenseAddParentOrderId
            ) {
                $identifiers = $this->nextOrderIdentifiers();

                $planNames = [];
                $totalLicenses = 0;
                foreach ($lineRows as $row) {
                    $planNames[] = trim((string) ($row['plan']->name ?? 'Plan'));
                    $totalLicenses += max(1, (int) ($row['licenses'] ?? 1));
                }
                $planNames = array_values(array_filter($planNames));
                $durationLabel = $this->resolveBillingCycleLabel($firstLine['plan'] ?? null, null);

                $orderInsert = [
                    'partner_id' => $partnerId,
                    'user_id' => $customerId,
                    'plan_id' => (int) $firstLine['plan']->id,
                    'domain_name' => $domainName !== '' ? $domainName : null,
                    'order_type' => 'b2b',
                    'order_category' => $orderCategory,
                    'quantity' => max(1, $totalLicenses),
                    'duration' => $durationLabel !== '' ? $durationLabel : '12 Months',
                    'subtotal' => $cartSubtotal,
                    'total_amount' => $cartGrandTotal,
                    'price' => null,
                    'discount_amount' => $cartDiscount,
                    'apply_gst' => $applyGst ? 1 : 0,
                    'gst_rate' => $gstRate,
                    'gst_amount' => $cartGstAmount,
                    'tax_amount' => $cartGstAmount,
                    'credit_used' => $cartGrandTotal,
                    'status' => $orderStatus,
                    'order_no' => $identifiers['order_no'],
                    'sr_no' => $identifiers['sr_no'],
                ];
                $orderInsert['pro_rata_adjustment'] = $cartProRata;

                if ($planNames !== [] && Schema::connection('pgsql_billing')->hasColumn('orders', 'plan_name')) {
                    $orderInsert['plan_name'] = implode(', ', $planNames);
                }

                if ($promoCode !== null) {
                    $orderInsert['promo_code'] = $promoCode;
                }
                $orderInsert['subscription_start_date'] = $subscriptionStartDate;
                $orderInsert['subscription_end_date'] = $subscriptionEndDate;
                if (
                    $orderCategory === 'addon' && $licenseAddParentOrderId > 0
                    && Schema::connection('pgsql_billing')->hasColumn('orders', 'parent_order_id')
                ) {
                    $orderInsert['parent_order_id'] = $licenseAddParentOrderId;
                }
                $transferDomain = strtolower(trim((string) ($parentCart->transfer_domain ?? '')));
                if (in_array($transferDomain, ['yes', 'no'], true)) {
                    $orderInsert['transfer_domain'] = $transferDomain;
                }
                if ($cartId > 0 && Schema::connection('pgsql_billing')->hasColumn('orders', 'cart_id')) {
                    $orderInsert['cart_id'] = $cartId;
                }
                if ($cartId > 0 && Schema::connection('pgsql_billing')->hasColumn('orders', 'source_cart_id')) {
                    $orderInsert['source_cart_id'] = $cartId;
                }

                $order = $this->orders->newQuery()->create($orderInsert);
                $newOrderId = (int) $order->id;

                if ($newOrderId <= 0) {
                    throw new \RuntimeException('Order could not be created.');
                }

                foreach ($lineRows as $row) {
                    $item = [
                        'order_id' => $newOrderId,
                        'plan_id' => (int) $row['plan']->id,
                        'item_name' => (string) ($row['plan']->name ?? 'Plan'),
                        'quantity' => $row['licenses'],
                        'unit_price' => round((float) $row['unitPrice'], 2),
                        'discount_amount' => 0,
                        'tax_rate' => 0,
                        'tax_amount' => 0,
                        'total_amount' => round((float) $row['lineGrand'], 2),
                        'subscription_start_date' => $subscriptionStartDate,
                        'subscription_end_date' => $subscriptionEndDate,
                    ];

                    $orderItem = $this->orderItems->newQuery()->create($item);
                    $subscriptionPrefix = strtoupper(trim((string) (
                        $this->adminSettings->newQuery()->orderBy('id')->value('subscription_prefix') ?? 'SUB'
                    ))) ?: 'SUB';
                    $orderItem->subscription_id = $subscriptionPrefix . '-' . str_pad((string) $orderItem->id, 6, '0', STR_PAD_LEFT);
                    $orderItem->save();
                }

                $this->saveGeneratedOrderPoDetails($newOrderId, $partnerId, $cartGrandTotal, $customerId);

                return $newOrderId;
            });
        } catch (\Throwable $e) {
            Log::error('order_generation_new_order_failed', [
                'partner_id' => $partnerId,
                'cart_id' => $cartId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Order could not be created. ' . $e->getMessage(),
            ], 422);
        }

        $poLink = null;
        $poNumber = null;
        $poError = null;
        $order = null;
        $poRow = null;

        try {
            $poStored = $this->resolvePoForGeneratedOrder($orderId, $request, $poMode);
            $order = $this->orders->newQuery()->whereKey($orderId)->first();
            $poRow = $this->orderPoDetails->newQuery()->where('order_id', $orderId)->first();
            if (!empty($poStored['ok'])) {
                $poLink = $poStored['public_url'] ?? null;
                $poNumber = $poStored['po_number'] ?? null;
            } else {
                $poError = (string) ($poStored['error'] ?? 'PO link could not be created.');
                $poLink = $poRow?->po_document_url ?? null;
                $poNumber = $poRow?->po_number ?? null;
            }
        } catch (\Throwable $e) {
            $poError = $e->getMessage();
            Log::error('order_generation_new_order_po_failed', [
                'order_id' => $orderId,
                'error' => $poError,
            ]);
            $order = $this->orders->newQuery()->whereKey($orderId)->first();
            $poRow = $this->orderPoDetails->newQuery()->where('order_id', $orderId)->first();
            $poLink = $poRow?->po_document_url ?? null;
            $poNumber = $poRow?->po_number ?? null;
        }

        $resolvedDomain = $this->resolveDomainNameForOrder($order, $cartId) ?: (string) ($domainName ?? '');
        if ($orderId > 0 && $resolvedDomain !== '') {
            OrderDomainHelper::persistOnOrderIfMissing($orderId, $resolvedDomain);
        }

        // Draft (aadhaar pending): keep cart active so CartDetails still returns it + draft_cart true.
        // Convert only when order is ready for processing AND no other drafts remain for this cart.
        // Never delete cart_items until PO + place fully succeed (failed place must keep cart lines).
        $priorDraftIds = $existingDraftIds ?? [];
        $placeSucceeded = ($poError === null || $poError === '');
        if ($cartId > 0 && $orderStatus !== self::STATUS_DRAFT && $placeSucceeded) {
            app(PartnerCartService::class)->clearCartPendingAadhaar($cartId);
            $this->forgetDraftCartLink($cartId, $orderId);
            Cache::forget('draft_order_plan_ids_' . $orderId);
            $remainingDrafts = array_values(array_filter(
                $priorDraftIds,
                fn($id) => (int) $id !== (int) $orderId && $this->isDraftOrderStatus((int) $id)
            ));
            // Remove purchased plan lines from cart.
            $purchasedPlanIds = array_values(array_unique(array_filter(array_map(
                static fn($row) => (int) ($row['plan']->id ?? 0),
                $lineRows
            ))));
            if ($purchasedPlanIds !== []) {
                CartItem::query()
                    ->where('cart_id', $cartId)
                    ->whereIn('plan_id', $purchasedPlanIds)
                    ->delete();
            }
            $remainingCartPlans = CartItem::query()->where('cart_id', $cartId)->count();
            if ($remainingDrafts === [] || $remainingCartPlans <= 0) {
                CartItem::query()->where('cart_id', $cartId)->delete();
                $this->carts->newQuery()->whereKey($cartId)->update(['status' => 'converted']);
                // Successful purchase → wipe all draft links so getCartDetails shows empty / no draft.
                $this->forgetDraftCartLink($cartId, 0);
                $priorDraftIds = [];
            } else {
                $this->carts->newQuery()->whereKey($cartId)->update(['status' => 'active']);
                foreach ($remainingDrafts as $keepId) {
                    $this->rememberDraftCartLink((int) $keepId, $cartId);
                }
                $priorDraftIds = $remainingDrafts;
            }
        } elseif ($cartId > 0 && $orderStatus !== self::STATUS_DRAFT && !$placeSucceeded) {
            // PO failed — keep cart lines intact for retry; link order for rollback cleanup.
            $this->rememberDraftCartLink($orderId, $cartId);
            $this->carts->newQuery()->whereKey($cartId)->update(['status' => 'active']);
        } elseif ($cartId > 0 && $orderStatus === self::STATUS_DRAFT) {
            $draftPlanIdsForNew = array_values(array_unique(array_filter(array_map(
                static fn($row) => (int) ($row['plan']->id ?? 0),
                $lineRows
            ))));
            $this->rememberDraftCartLink($orderId, $cartId, $draftPlanIdsForNew);
            foreach ($priorDraftIds as $keepId) {
                if ((int) $keepId !== (int) $orderId) {
                    $this->rememberDraftCartLink((int) $keepId, $cartId);
                }
            }
            $this->carts->newQuery()->whereKey($cartId)->update(['status' => 'active']);
        }

        // Send processing notifications if order is in processing state (no sign required)
        $orderStatusNow = strtolower(trim((string) ($order->status ?? $orderStatus ?? '')));
        if ($order && $orderStatusNow === self::STATUS_PROCESSING) {
            $this->sendProcessingOrderNotifications($orderId);
        } elseif ($orderId > 0 && $orderStatus === self::STATUS_PROCESSING) {
            // Fresh read if in-memory status was stale after PO attach.
            $this->sendProcessingOrderNotifications($orderId);
        }

        $ordersOut = [];
        $plansOut = [];
        $orderItemSubs = OrderItem::query()
            ->where('order_id', $orderId)
            ->orderBy('id')
            ->get()
            ->values();
        $billingPayload = $this->resolveOrderBillingCyclePayload($order, $firstLine['plan'] ?? null);
        $billingCycleLabel = (string) ($billingPayload['billing_cycle'] ?? '12 Months');
        $subStartLabel = (string) ($billingPayload['subscription_start_date'] ?? '');
        $subEndLabel = (string) ($billingPayload['subscription_end_date'] ?? '');

        foreach ($lineRows as $idx => $row) {
            $planName = (string) ($row['plan']->name ?? 'Plan');
            $planId = (int) $row['plan']->id;
            $matchedItem = $orderItemSubs->get($idx);
            $lineSubId = $this->normalizeSubscriptionIdDisplay(
                trim((string) ($matchedItem->subscription_id ?? $row['cartLine']->subscription_id ?? ''))
            );
            if ($lineSubId === '' && $matchedItem) {
                $lineSubId = $this->ensureOrderItemSubscriptionId($matchedItem);
            }
            $lineBilling = $this->resolveOrderBillingCyclePayload($order, $row['plan'] ?? null);
            $lineBillingLabel = (string) ($lineBilling['billing_cycle'] ?? $billingCycleLabel);
            $lineStart = (string) ($lineBilling['subscription_start_date'] ?? $subStartLabel);
            $lineEnd = (string) ($lineBilling['subscription_end_date'] ?? $subEndLabel);

            $resolvedDomain = trim((string) ($order->domain_name ?? $domainName ?? ''));
            if ($resolvedDomain === '' && $cartId > 0) {
                $resolvedDomain = $this->resolveDomainNameForOrder($order, $cartId);
            }

            $ordersOut[] = [
                'order_id' => $orderId,
                'provider_id' => $providerIdFromPlan, // Use provider_id from plan
                'order_no' => (string) ($order->order_no ?? ''),
                'plan_id' => $planId,
                'plan_name' => $planName,
                'cart_item_id' => (int) $row['cartLine']->id,
                'licenses' => $row['licenses'],
                'status' => $orderStatus,
                'domain_name' => $resolvedDomain,
                'domain' => $resolvedDomain,
                'transfer_domain' => $order->transfer_domain,
                'company_name' => $companyName,
                'subscription_id' => $lineSubId,
                'subscription_no' => $lineSubId,
                'billing_cycle' => $lineBillingLabel,
                'duration' => $lineBillingLabel,
                'subscription_start_date' => $lineStart !== '' ? $lineStart : null,
                'subscription_end_date' => $lineEnd !== '' ? $lineEnd : null,
                'plan_amount' => round((float) $row['lineGrand'], 2),
                'gst' => round((float) ($row['cartLine']->gst_amount ?? 0), 2),
                'coupon_name' => $couponName,
                'coupon_discount' => round((float) $cartDiscount, 2),
                'po' => [
                    'po_number' => (string) ($poRow?->po_number ?? ''),
                    'journey_status' => (string) ($poRow?->journey_status ?? 'po_pending'),
                    'po_link' => $poLink,
                ],
            ];
            $plansOut[] = [
                'plan_id' => $planId,
                'plan_name' => $planName,
                'licenses' => $row['licenses'],
                'unit_price' => round((float) $row['unitPrice'], 2),
                'line_total' => round((float) $row['lineGrand'], 2),
                'plan_amount' => round((float) $row['lineGrand'], 2),
                'gst' => round((float) ($row['cartLine']->gst_amount ?? 0), 2),
                'coupon_name' => $couponName,
                'coupon_discount' => round((float) $cartDiscount, 2),
                'domain_name' => $resolvedDomain,
                'domain' => $resolvedDomain,
                'subscription_id' => $lineSubId,
                'subscription_no' => $lineSubId,
                'billing_cycle' => $lineBillingLabel,
                'duration' => $lineBillingLabel,
                'subscription_start_date' => $lineStart !== '' ? $lineStart : null,
                'subscription_end_date' => $lineEnd !== '' ? $lineEnd : null,
            ];
        }

        // Include prior open drafts in response so UI can show mixed draft / new orders.
        $allOrderIds = array_values(array_unique(array_merge(
            array_map('intval', $priorDraftIds),
            [(int) $orderId]
        )));
        $isDraft = $orderStatus === self::STATUS_DRAFT;
        $primaryDraftId = $isDraft ? $orderId : (int) ($priorDraftIds[0] ?? 0);
        $primaryDraftOrder = null;
        $primaryDraftPo = null;
        if ($primaryDraftId > 0) {
            $primaryDraftOrder = $primaryDraftId === $orderId
                ? $order
                : $this->orders->newQuery()->whereKey($primaryDraftId)->first();
            $primaryDraftPo = $primaryDraftId === $orderId
                ? $poRow
                : $this->orderPoDetails->newQuery()->where('order_id', $primaryDraftId)->first();
        }
        $hasAnyDraft = $isDraft || $primaryDraftId > 0;

        if (!$isDraft && $cartId > 0 && !$hasAnyDraft) {
            $this->forgetDraftCartLink($cartId, $orderId);
        }

        $categoryProbe = clone $order;
        $categoryProbe->transfer_domain = null;
        if (isset($categoryProbe->domain_transfer)) {
            $categoryProbe->domain_transfer = null;
        }
        $enrollmentCategory = $this->resolveEnrollmentType($categoryProbe, (string) ($orderCategory ?? ''));
        $transferFlag = strtolower(trim((string) (
            $order->transfer_domain
            ?? $order->domain_transfer
            ?? $parentCart->domain_transfer
            ?? $parentCart->transfer_domain
            ?? ''
        )));
        $enrollmentType = in_array($transferFlag, ['yes', '1', 'true'], true)
            ? 'Transfer Service'
            : 'New Service';

        $responseData = [
            'order_id' => $orderId,
            'ordId' => (int) ($order->id ?? $orderId),
            'order_no' => (string) ($order->order_no ?? $orderId),
            'orderNo' => (string) ($order->order_no ?? $orderId),
            'provider_id' => $providerIdFromPlan, // Use provider_id from plan at root
            'order_ids' => $allOrderIds,
            'status' => $orderStatus,
            'sign_required' => $signRequiredFlag,
            'aadhaar_pending' => $hasAnyDraft,
            'draft_cart' => $hasAnyDraft,
            'company_name' => $companyName,
            'subscription_id' => (string) ($ordersOut[0]['subscription_id'] ?? OrderItem::query()->where('order_id', $orderId)->orderBy('id')->value('subscription_id') ?? ''),
            'subscription_no' => (string) ($ordersOut[0]['subscription_id'] ?? ''),
            'subscriptionId' => (string) ($ordersOut[0]['subscription_id'] ?? ''),
            'billing_cycle' => $billingCycleLabel,
            'billingCycle' => $billingCycleLabel,
            'duration' => $billingCycleLabel,
            'subscription_start_date' => $subStartLabel !== '' ? $subStartLabel : null,
            'subscription_end_date' => $subEndLabel !== '' ? $subEndLabel : null,
            'start_date' => $subStartLabel !== '' ? $subStartLabel : null,
            'end_date' => $subEndLabel !== '' ? $subEndLabel : null,
            'total_amount' => round((float) ($order->total_amount ?? $cartGrandTotal), 2),
            'plan_amount' => round((float) ($order->subtotal ?? $order->total_amount ?? $cartGrandTotal), 2),
            'gst' => round((float) ($order->gst_amount ?? $cartGstAmount), 2),
            'coupon_name' => $couponName,
            'coupon_discount' => round((float) ($order->discount_amount ?? $cartDiscount), 2),
            'draft_order' => $hasAnyDraft && $primaryDraftOrder ? [
                'order_id' => $primaryDraftId,
                'provider_id' => $providerIdFromPlan,
                'order_no' => (string) ($primaryDraftOrder->order_no ?? ''),
                'status' => self::STATUS_DRAFT,
                'sign_required' => $signRequiredFlag,
                'aadhaar_pending' => true,
                'po_link' => $primaryDraftPo?->po_document_url ?? $poLink,
                'po_number' => $primaryDraftPo?->po_number ?? $poNumber,
                'cart_id' => $cartId,
                'company_name' => $companyName,
                'subscription_id' => (string) (OrderItem::query()->where('order_id', $primaryDraftId)->orderBy('id')->value('subscription_id') ?? ''),
                'total_amount' => round((float) ($primaryDraftOrder->total_amount ?? 0), 2),
                'plan_amount' => round((float) ($primaryDraftOrder->subtotal ?? $primaryDraftOrder->total_amount ?? 0), 2),
                'gst' => round((float) ($primaryDraftOrder->gst_amount ?? 0), 2),
                'coupon_name' => $couponName,
                'coupon_discount' => round((float) ($primaryDraftOrder->discount_amount ?? $cartDiscount), 2),
            ] : null,
            'po_link' => $poLink,
            'po_number' => $this->cleanPoNumberDisplay((string) ($poNumber ?? '')),
            'po_document_name' => $this->cleanPoNumberDisplay((string) ($poNumber ?? '')),
            'document_name' => $this->cleanPoNumberDisplay((string) ($poNumber ?? '')),
            'document_label' => $this->cleanPoNumberDisplay((string) ($poNumber ?? '')),
            'po_label' => $this->cleanPoNumberDisplay((string) ($poNumber ?? '')),
            'po_mode' => (string) ($poRow->po_mode ?? $poMode ?? 'generated'),
            'cart_id' => $cartId,
            'domain_name' => $this->resolveDomainNameForOrder($order, $cartId) ?: (string) ($domainName ?? ''),
            'domain' => $this->resolveDomainNameForOrder($order, $cartId) ?: (string) ($domainName ?? ''),
            'order_type' => (string) ($order->order_category ?? $orderCategory ?? 'new'),
            'order_category' => (string) ($order->order_category ?? $orderCategory ?? 'new'),
            'enrollment_category' => $enrollmentCategory,
            'enrollment_type' => $enrollmentType,
            'order_date' => $this->formatOrderDateFields($order->created_at ?? now())['order_date'],
            'order_date_iso' => $this->formatOrderDateFields($order->created_at ?? now())['order_date_iso'],
            'orderDate' => $this->formatOrderDateFields($order->created_at ?? now())['orderDate'],
            'plans' => $plansOut,
            'orders' => $ordersOut,
        ];
        // Prefer resolved domain once (also mirrors into plans if still blank).
        $rootDomain = (string) ($responseData['domain_name'] ?? '');
        $responseData['domain'] = $rootDomain;
        if ($rootDomain !== '') {
            foreach ($responseData['plans'] as $i => $_) {
                if (trim((string) ($responseData['plans'][$i]['domain_name'] ?? '')) === '') {
                    $responseData['plans'][$i]['domain_name'] = $rootDomain;
                    $responseData['plans'][$i]['domain'] = $rootDomain;
                }
            }
            foreach ($responseData['orders'] as $i => $_) {
                if (trim((string) ($responseData['orders'][$i]['domain_name'] ?? '')) === '') {
                    $responseData['orders'][$i]['domain_name'] = $rootDomain;
                    $responseData['orders'][$i]['domain'] = $rootDomain;
                }
            }
        }
        $responseData['subscription_no'] = (string) ($responseData['subscription_id'] ?? '');
        $responseData['subscriptionId'] = (string) ($responseData['subscription_id'] ?? '');
        $poClean = $this->cleanPoNumberDisplay((string) ($responseData['po_number'] ?? ''));
        if ($poClean !== '' || !empty($poLink)) {
            $responseData['documents'] = [[
                'type' => 'purchase_order',
                'title' => 'Purchase Order',
                'name' => $poClean,
                'label' => $poClean,
                'po_number' => $poClean,
                'link' => $poLink,
                'url' => $poLink,
            ]];
        }
        if ($poError !== null && $poError !== '') {
            $responseData['po_error'] = $poError;
        }

        $finalPlaceFailed = !$isDraft
            && $this->wantsFinalPlace($request)
            && $poError !== null
            && $poError !== '';

        return response()->json([
            'success' => !$finalPlaceFailed,
            'message' => $isDraft
                ? (
                    $priorDraftIds !== []
                    ? 'New draft order saved for added plans. Complete Aadhaar verification to place the order(s).'
                    : 'Draft order saved. Complete Aadhaar verification to place the order.'
                )
                : ($poError
                    ? 'Order created but PO link could not be generated.'
                    : (
                        ($poMode ?? '') === \App\Services\OrderPoDocumentService::MODE_UPLOADED
                        ? 'New order generated with uploaded PO.'
                        : (
                            $priorDraftIds !== []
                            ? 'New order generated for added plans. Prior draft still pending Aadhaar.'
                            : 'New order generated successfully.'
                        )
                    )),
            'data' => $responseData,
        ], $isDraft ? 200 : 201);
    }

    public function generateRenewOrder(Request $request): JsonResponse
    {
        $this->normalizePoDocumentUpload($request);

        $validated = $request->validate(array_merge([
            'partner_id' => ['required', 'integer', Rule::exists(Partner::class, 'id')],
            'order_id' => ['nullable', 'integer', Rule::exists(Order::class, 'id')],
            'subscription_id' => ['nullable', 'integer', Rule::exists(Order::class, 'id')],
            'customer_id' => ['nullable', 'integer', Rule::exists(Customer::class, 'id')],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'licenses' => ['nullable', 'integer', 'min:1'],
            'aadhaar_number' => ['nullable', 'digits:12'],
            'main_cart_id' => ['nullable', 'integer', Rule::exists(Cart::class, 'id')],
            'cart_id' => ['nullable', 'integer', Rule::exists(Cart::class, 'id')],
            'po_mode' => ['nullable', 'in:generated,uploaded'],
            'po_number' => ['nullable', 'string', 'max:100'],
            'po_date' => ['nullable', 'date'],
            'place_order' => ['nullable', 'boolean'],
            'finalize' => ['nullable', 'boolean'],
            'confirm_place' => ['nullable', 'boolean'],
        ], $this->poDocumentValidationRules(false)));

        $partnerId = (int) $validated['partner_id'];
        $poMode = app(\App\Services\OrderPoDocumentService::class)
            ->normalizeMode((string) ($validated['po_mode'] ?? 'generated'));
        if ($request->hasFile('po_document')) {
            $poMode = \App\Services\OrderPoDocumentService::MODE_UPLOADED;
        }
        // eSign removed — Place Order is the only gate.
        $signRequired = false;

        $subscriptionIdHint = (int) ($validated['subscription_id'] ?? $validated['order_id'] ?? 0);
        $cartId = (int) ($validated['main_cart_id'] ?? $validated['cart_id'] ?? 0);
        $uploadError = $this->storeUploadedPoOnCartIfNeeded($request, $cartId, $poMode);
        if ($uploadError !== null) {
            return $uploadError;
        }

        $companyName = '';
        $renewCustomerId = (int) ($validated['customer_id'] ?? 0);
        if ($renewCustomerId <= 0 && $cartId > 0) {
            $renewCustomerId = (int) ($this->carts->newQuery()->whereKey($cartId)->value('customer_id') ?? 0);
        }
        if ($renewCustomerId <= 0 && $subscriptionIdHint > 0) {
            $renewCustomerId = (int) ($this->orders->newQuery()->whereKey($subscriptionIdHint)->value('user_id') ?? 0);
        }
        if ($renewCustomerId > 0) {
            $companyName = trim((string) ($this->customers->newQuery()->whereKey($renewCustomerId)->value('company_name') ?? ''));
        }
        $renewCouponName = '';
        $renewCouponDiscount = 0.0;
        if ($cartId > 0) {
            $renewCart = $this->carts->newQuery()->whereKey($cartId)->first();
            if ($renewCart) {
                $renewCouponName = !empty($renewCart->coupen) ? strtoupper(trim((string) $renewCart->coupen)) : '';
                $renewCouponDiscount = round((float) ($renewCart->discount_amount ?? 0), 2);
            }
        }

        $orderStatus = self::STATUS_PROCESSING;

        // Reuse existing draft renew for this cart/parent when finally placing.
        $existingDraftId = 0;
        if ($cartId > 0) {
            $existingDraftId = (int) Cache::get('cart_draft_order_' . $cartId, 0);
        }
        if ($existingDraftId <= 0 && $cartId > 0 && Schema::connection('pgsql_billing')->hasColumn('orders', 'cart_id')) {
            $existingDraftId = (int) $this->orders->newQuery()
                ->where('partner_id', $partnerId)
                ->where('cart_id', $cartId)
                ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = 'draft'")
                ->orderByDesc('id')
                ->value('id');
        }
        if ($existingDraftId <= 0 && $subscriptionIdHint > 0) {
            $existingDraftId = (int) $this->orders->newQuery()
                ->where('partner_id', $partnerId)
                ->where('parent_order_id', $subscriptionIdHint)
                ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = 'draft'")
                ->where(function ($q) {
                    $q->whereIn('order_category', ['renewal', 'renew'])
                        ->orWhereRaw("LOWER(TRIM(COALESCE(order_category, ''))) IN ('renewal', 'renew')")
                        ->orWhere('order_no', 'like', 'RN%')
                        ->orWhere('order_no', 'like', 'rn%');
                })
                ->orderByDesc('id')
                ->value('id');
        }

        if ($existingDraftId > 0 && $this->wantsFinalPlace($request)) {
            $draftOrder = $this->orders->newQuery()->whereKey($existingDraftId)->first();
            $draftPo = $this->orderPoDetails->newQuery()->where('order_id', $existingDraftId)->first();

            $this->orders->newQuery()->whereKey($existingDraftId)->update(['status' => self::STATUS_PROCESSING]);
            if ($cartId > 0) {
                app(PartnerCartService::class)->clearCartPendingAadhaar($cartId);
                $this->carts->newQuery()->whereKey($cartId)->update(['status' => 'converted']);
                $this->forgetDraftCartLink($cartId, $existingDraftId);
            }
            $this->sendProcessingOrderNotifications($existingDraftId);
            $draftOrder = $this->orders->newQuery()->whereKey($existingDraftId)->first();
            $draftPo = $this->orderPoDetails->newQuery()->where('order_id', $existingDraftId)->first();
            if ($companyName === '' && (int) ($draftOrder->user_id ?? 0) > 0) {
                $companyName = trim((string) ($this->customers->newQuery()->whereKey((int) $draftOrder->user_id)->value('company_name') ?? ''));
            }

            return response()->json([
                'success' => true,
                'message' => 'Renewal order generated successfully.',
                'data' => [
                    'order_id' => $existingDraftId,
                    'ordId' => (int) ($draftOrder->id ?? $existingDraftId),
                    'order_no' => (string) ($draftOrder->order_no ?? $existingDraftId),
                    'status' => self::STATUS_PROCESSING,
                    'sign_required' => 'no',
                    'aadhaar_pending' => false,
                    'renewal_created' => true,
                    'company_name' => $companyName,
                    'subscription_id' => (string) (OrderItem::query()->where('order_id', $existingDraftId)->orderBy('id')->value('subscription_id') ?? ''),
                    'total_amount' => round((float) ($draftOrder->total_amount ?? 0), 2),
                    'plan_amount' => round((float) ($draftOrder->subtotal ?? $draftOrder->total_amount ?? 0), 2),
                    'gst' => round((float) ($draftOrder->gst_amount ?? 0), 2),
                    'coupon_name' => $renewCouponName,
                    'coupon_discount' => round((float) ($draftOrder->discount_amount ?? $renewCouponDiscount), 2),
                    'po_link' => $draftPo?->po_document_url ?? null,
                    'po_number' => $draftPo?->po_number ?? null,
                    'cart_id' => $cartId > 0 ? $cartId : null,
                    'parent_order_id' => (int) ($draftOrder->parent_order_id ?? $subscriptionIdHint),
                ],
            ], 200);
        }

        if (!empty($validated['main_cart_id'])) {
            $cartStatus = (string) $this->carts->newQuery()
                ->where('partner_id', $partnerId)
                ->whereKey((int) $validated['main_cart_id'])
                ->value('status');
            if (strtolower($cartStatus) === 'converted') {
                return response()->json([
                    'success' => false,
                    'message' => 'Order already created for this cart.',
                ], 422);
            }
        }

        // Until Place Order: no renewal row — temp PND + PO in cart only (same as new order).
        if (!$this->wantsFinalPlace($request)) {
            if ($cartId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'main_cart_id / cart_id is required. Add renew plans to cart, then generate PO.',
                    'data' => null,
                ], 422);
            }

            $uploadError = $this->storeUploadedPoOnCartIfNeeded($request, $cartId, $poMode);
            if ($uploadError !== null) {
                return $uploadError;
            }
            $this->cancelLeftoverDraftOrdersForCart($partnerId, $cartId);
            $this->ensureCartFlowType($cartId, 'renew', $subscriptionIdHint > 0 ? $subscriptionIdHint : null);

            $plansOut = [];
            $cartItems = CartItem::query()->where('cart_id', $cartId)->with('plan')->get();
            $renewDomain = $this->resolveDomainNameForOrder(null, $cartId);
            if ($renewDomain === '' && $subscriptionIdHint > 0) {
                $renewDomain = trim((string) (
                    $this->orders->newQuery()->whereKey($subscriptionIdHint)->value('domain_name') ?? ''
                ));
            }
            foreach ($cartItems as $cartLine) {
                $plan = $cartLine->plan;
                $plansOut[] = [
                    'plan_id' => (int) ($cartLine->plan_id ?? 0),
                    'plan_name' => (string) ($plan->name ?? $plan->title ?? 'Plan'),
                    'licenses' => max(1, (int) ($cartLine->licenses ?? 1)),
                    'cart_item_id' => (int) ($cartLine->id ?? 0),
                    'plan_amount' => round((float) ($cartLine->grand_total ?? 0), 2),
                    'domain_name' => $renewDomain,
                    'domain' => $renewDomain,
                ];
            }

            return $this->cartPendingAadhaarResponse(
                $cartId,
                $companyName,
                'Draft PO saved in cart. No renewal order created yet — call place_order=1 to place the order.',
                [
                    'renewal_created' => false,
                    'parent_order_id' => $subscriptionIdHint > 0 ? $subscriptionIdHint : null,
                    'coupon_name' => $renewCouponName,
                    'coupon_discount' => $renewCouponDiscount,
                    'po_mode' => $poMode,
                    'place_order_required' => true,
                    'order_type' => 'renew',
                    'domain_name' => $renewDomain,
                    'domain' => $renewDomain,
                    'plans' => $plansOut,
                    // Explicit: cart stays active with these lines until Place Order.
                    'cart_status' => 'active',
                    'cart_item_count' => count($plansOut),
                ],
                'renew'
            );
        }

        if ($cartId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'main_cart_id / cart_id is required to place the renewal order.',
                'data' => null,
            ], 422);
        }

        $this->ensureCartFlowType($cartId, 'renew', $subscriptionIdHint > 0 ? $subscriptionIdHint : null);

        $renewService = app(B2bSubscriptionRenewalService::class);
        $resolvedId = 0;

        try {
            $subscriptionId = $subscriptionIdHint;
            $resolvedId = $renewService->resolveSubscriptionId(
                $partnerId,
                $subscriptionId > 0 ? $subscriptionId : null,
                isset($validated['customer_id']) ? (int) $validated['customer_id'] : null,
                null,
            );

            $qty = (int) ($validated['quantity'] ?? $validated['licenses'] ?? 0);
            if ($qty <= 0 && $cartId > 0) {
                // Sum all renew cart lines — not only the first item (false decrease vs order total).
                $qty = (int) (\App\Models\CartItem::query()->where('cart_id', $cartId)->sum('licenses') ?? 0);
            }
            if ($qty <= 0) {
                $base = Order::query()->find($resolvedId);
                $qty = max(1, (int) ($base->quantity ?? 1));
            }

            $guardPlanId = isset($validated['plan_id']) ? (int) $validated['plan_id'] : null;
            if (($guardPlanId === null || $guardPlanId <= 0) && $cartId > 0) {
                $cartPlanIds = \App\Models\CartItem::query()
                    ->where('cart_id', $cartId)
                    ->whereNotNull('plan_id')
                    ->pluck('plan_id')
                    ->map(fn($id) => (int) $id)
                    ->filter(fn($id) => $id > 0)
                    ->unique()
                    ->values();
                if ($cartPlanIds->count() === 1) {
                    $guardPlanId = (int) $cartPlanIds->first();
                }
            }

            $decreaseGuard = app(\App\Services\LicenseDecreaseRequestService::class)->guardRenewDecrease(
                $partnerId,
                $resolvedId,
                $qty,
                $cartId > 0 ? $cartId : null,
                $guardPlanId
            );
            if (!empty($decreaseGuard['blocked'])) {
                return response()->json($decreaseGuard['payload'] ?? [
                    'success' => false,
                    'message' => 'License decrease requires SuperAdmin approval.',
                ], 422);
            }

            $result = $renewService->renew([
                'partner_id' => $partnerId,
                'subscription_id' => $resolvedId,
                'quantity' => $qty,
                'aadhaar_number' => '',
                'main_cart_id' => $validated['main_cart_id'] ?? $validated['cart_id'] ?? null,
                'cart_id' => $validated['cart_id'] ?? $validated['main_cart_id'] ?? null,
                'skip_credit_check' => true,
                'skip_invoice' => true,
                'force_pending' => true,
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
                'message' => 'Failed to generate renewal order: ' . $e->getMessage(),
            ], 500);
        }

        $order = $result['order'];
        $orderId = (int) ($order->id ?? 0);
        if ($orderId > 0) {
            $previousSubId = (int) ($order->parent_order_sub_id ?? 0);
            if ($previousSubId <= 0 && $resolvedId > 0) {
                $previousSubId = (int) OrderItem::query()
                    ->where('order_id', $resolvedId)
                    ->where('status', '!=', 'delete')
                    ->orderBy('id')
                    ->value('id');
            }

            if ($previousSubId > 0) {
                OrderItem::query()
                    ->where('order_id', $orderId)
                    ->where(function ($q) {
                        $q->whereNull('previous_order_sub_id')
                            ->orWhere('previous_order_sub_id', 0);
                    })
                    ->update(['previous_order_sub_id' => $previousSubId]);
            }
        }

        $poLink = null;
        $poNumber = null;
        $poError = null;

        if ($orderId > 0) {
            try {
                $this->orders->newQuery()->whereKey($orderId)->update(['status' => $orderStatus]);

                $poAmount = round((float) ($order->total_amount ?? $order->credit_used ?? 0), 2);
                $this->saveGeneratedOrderPoDetails(
                    $orderId,
                    $partnerId,
                    $poAmount,
                    (int) ($order->user_id ?? 0)
                );

                $poStored = $this->resolvePoForGeneratedOrder($orderId, $request, $poMode);
                $poRow = $this->orderPoDetails->newQuery()->where('order_id', $orderId)->first();
                if (!empty($poStored['ok'])) {
                    $poLink = $poStored['public_url'] ?? null;
                    $poNumber = $poStored['po_number'] ?? null;
                } else {
                    $poError = (string) ($poStored['error'] ?? 'PO link could not be created.');
                    $poLink = $poRow?->po_document_url ?? null;
                    $poNumber = $poRow?->po_number ?? null;
                }
            } catch (\Throwable $e) {
                $poError = $e->getMessage();
                Log::error('order_generation_renew_po_failed', [
                    'order_id' => $orderId,
                    'error' => $poError,
                ]);
            }
        }

        $orderRow = $orderId > 0 ? $this->orders->newQuery()->whereKey($orderId)->first() : null;

        if ($orderId > 0) {
            $renewDomain = $this->resolveDomainNameForOrder($orderRow ?? $order, $cartId);
            if ($renewDomain !== '') {
                OrderDomainHelper::persistOnOrderIfMissing($orderId, $renewDomain);
            }
        }

        if ($companyName === '' && $orderRow && (int) ($orderRow->user_id ?? 0) > 0) {
            $companyName = trim((string) ($this->customers->newQuery()->whereKey((int) $orderRow->user_id)->value('company_name') ?? ''));
        }

        if ($orderRow && $orderStatus === self::STATUS_PROCESSING) {
            $this->sendProcessingOrderNotifications($orderId);
        }

        // Draft PO: keep cart. Processing/pending: convert cart only after place succeeds.
        if ($cartId > 0 && $orderStatus === self::STATUS_DRAFT) {
            $this->rememberDraftCartLink($orderId, $cartId);
        } elseif ($cartId > 0 && in_array($orderStatus, [self::STATUS_PROCESSING, self::STATUS_PENDING], true)) {
            app(PartnerCartService::class)->clearSupersededConvertedCarts($partnerId, Cart::TYPE_RENEW, $cartId);
            app(PartnerCartService::class)->clearCartPendingAadhaar($cartId);
            $this->rememberDraftCartLink($orderId, $cartId);
            $cartUpdate = ['status' => 'converted'];
            if (Schema::connection('pgsql_billing')->hasColumn('carts', 'order_id')) {
                $cartUpdate['order_id'] = $orderId;
            }
            $this->carts->newQuery()->whereKey($cartId)->update($cartUpdate);
            if (Schema::connection('pgsql_billing')->hasColumn('orders', 'cart_id')) {
                $this->orders->newQuery()->whereKey($orderId)->update(['cart_id' => $cartId]);
            }
        }

        $renewData = [
            'order_id' => $orderId,
            'ordId' => (int) ($order->id ?? $orderId),
            'order_no' => (string) ($order->order_no ?? $orderId),
            'status' => $orderStatus,
            'sign_required' => 'no',
            'aadhaar_pending' => false,
            'place_order_required' => false,
            'renewal_created' => true,
            'company_name' => $companyName,
            'subscription_id' => (string) (OrderItem::query()->where('order_id', $orderId)->orderBy('id')->value('subscription_id') ?? ''),
            'total_amount' => round((float) ($order->total_amount ?? 0), 2),
            'plan_amount' => round((float) ($order->subtotal ?? $order->total_amount ?? 0), 2),
            'gst' => round((float) ($order->gst_amount ?? 0), 2),
            'coupon_name' => $renewCouponName,
            'coupon_discount' => round((float) ($order->discount_amount ?? $renewCouponDiscount), 2),
            'po_link' => $poLink,
            'po_number' => $poNumber,
            'po_mode' => $poMode,
            'cart_id' => $cartId > 0 ? $cartId : null,
            'parent_order_id' => (int) ($order->parent_order_id ?? $resolvedId),
            'order_type' => 'renew',
            'order_category' => 'renewal',
            'enrollment_type' => $this->resolveEnrollmentType($order, 'renew'),
            'renewal_summary' => $result['summary'] ?? [],
        ];
        if ($poError !== null && $poError !== '') {
            $renewData['po_error'] = $poError;
        }

        $renewFinalPlaceFailed = $this->wantsFinalPlace($request)
            && $orderStatus !== self::STATUS_DRAFT
            && $poError !== null
            && $poError !== '';

        return response()->json([
            'success' => !$renewFinalPlaceFailed,
            'message' => $poError
                ? 'Renewal order created but PO link could not be generated.'
                : ($poMode === \App\Services\OrderPoDocumentService::MODE_UPLOADED
                    ? 'Renewal order generated with uploaded PO.'
                    : 'Renewal order generated successfully.'),
            'data' => $renewData,
        ], 201);
    }

    public function generateUpgradeOrder(Request $request): JsonResponse
    {
        $this->normalizePoDocumentUpload($request);

        $validated = $request->validate(array_merge([
            'partner_id' => ['required', 'integer', Rule::exists(Partner::class, 'id')],
            'main_cart_id' => ['nullable', 'integer', Rule::exists(Cart::class, 'id')],
            'order_sub_id' => ['nullable', 'integer'],
            'order_category' => ['nullable', 'string'],
            'is_upgrade' => ['nullable', 'boolean'],
            'po_mode' => ['nullable', 'in:generated,uploaded'],
            'po_number' => ['nullable', 'string', 'max:100'],
            'po_date' => ['nullable', 'date'],
            'place_order' => ['nullable', 'boolean'],
            'finalize' => ['nullable', 'boolean'],
            'confirm_place' => ['nullable', 'boolean'],
        ], $this->poDocumentValidationRules(false)));

        $partnerId = (int) $validated['partner_id'];
        $poMode = app(\App\Services\OrderPoDocumentService::class)
            ->normalizeMode((string) ($validated['po_mode'] ?? 'generated'));
        if ($request->hasFile('po_document')) {
            $poMode = \App\Services\OrderPoDocumentService::MODE_UPLOADED;
        }
        $orderSubId = (int) ($validated['order_sub_id'] ?? $request->input('order_sub_id', 0));
        $cart = $this->resolvePlanChangeCart($partnerId, $validated, $request);

        if (!$cart || $cart->items->isEmpty()) {
            $isDowngradeHint = $this->requestIndicatesDowngrade($request, $validated, $cart);
            $label = $isDowngradeHint ? 'Downgrade' : 'Upgrade';

            return $this->fail("{$label} cart not found. Add plan to cart first.", 422);
        }

        $uploadError = $this->storeUploadedPoOnCartIfNeeded($request, (int) $cart->id, $poMode);
        if ($uploadError !== null) {
            return $uploadError;
        }

        // Recover source line from cart when frontend omits order_sub_id.
        $orderSubId = $this->resolveUpgradeSourceSubIdFromCart($cart, $orderSubId);

        $isDowngrade = strtolower(trim((string) ($cart->type ?? ''))) === Cart::TYPE_DOWNGRADE
            || $this->requestIndicatesDowngrade($request, $validated, $cart);
        $changeLabel = $isDowngrade ? 'Downgrade' : 'Upgrade';
        $cartId = (int) ($cart->id ?? 0);
        $upgradeMode = $isDowngrade ? 'full' : $this->resolveUpgradeModeFromCart($cart, $orderSubId);
        $enrollmentType = $isDowngrade
            ? 'Downgraded'
            : $this->cartEnrollmentType($cart);

        $partialLicenseError = $this->partialUpgradeSameLicenseError($cart, $orderSubId, $isDowngrade);
        if ($partialLicenseError !== null) {
            return $partialLicenseError;
        }

        // eSign removed — Place Order is the only gate.
        $signRequired = false;

        if (!$this->wantsFinalPlace($request)) {
            $companyName = '';
            $customerIdForName = (int) ($cart->customer_id ?? 0);
            if ($customerIdForName > 0) {
                $companyName = trim((string) ($this->customers->newQuery()->whereKey($customerIdForName)->value('company_name') ?? ''));
            }

            $uploadError = $this->storeUploadedPoOnCartIfNeeded($request, $cartId, $poMode);
            if ($uploadError !== null) {
                return $uploadError;
            }
            $this->cancelLeftoverDraftOrdersForCart($partnerId, $cartId);
            $this->ensureCartFlowType(
                $cartId,
                $isDowngrade ? 'downgrade' : 'upgrade',
                (int) ($cart->order_id ?? 0) ?: null,
                $orderSubId > 0 ? $orderSubId : null
            );

            // Recompute remaining_value / payable so PO generate never zeros the upgrade cart.
            $cart = $this->refreshPlanChangeCartPricing($cart, $orderSubId > 0 ? $orderSubId : null)
                ?? $cart->fresh(['items.plan'])
                ?? $cart;
            $upgradeMode = $isDowngrade ? 'full' : $this->resolveUpgradeModeFromCart($cart, $orderSubId);
            $enrollmentType = $isDowngrade
                ? 'Downgraded'
                : $this->cartEnrollmentType($cart);

            $plansOut = [];
            $upgradeDomain = $this->resolveDomainNameForOrder(null, $cartId);
            if ($upgradeDomain === '') {
                $parentOrderId = (int) ($cart->order_id ?? 0);
                if ($parentOrderId > 0) {
                    $upgradeDomain = trim((string) (
                        $this->orders->newQuery()->whereKey($parentOrderId)->value('domain_name') ?? ''
                    ));
                }
            }
            $remainingValue = round((float) ($cart->pro_rata_adjustment ?? 0), 2);
            $cartSubtotal = round((float) ($cart->subtotal ?? 0), 2);
            $cartGst = round((float) ($cart->gst_amount ?? 0), 2);
            $cartPayable = round((float) ($cart->grand_total ?? 0), 2);
            $appliedProRata = $remainingValue > 0 && $cartSubtotal > 0
                ? round(min($remainingValue, $cartSubtotal), 2)
                : $remainingValue;
            foreach ($cart->items as $cartLine) {
                $plan = $cartLine->plan ?? $this->plans->newQuery()->whereKey((int) ($cartLine->plan_id ?? 0))->first();
                $plansOut[] = [
                    'plan_id' => (int) ($cartLine->plan_id ?? 0),
                    'plan_name' => (string) ($plan->name ?? $plan->title ?? 'Plan'),
                    'licenses' => max(1, (int) ($cartLine->licenses ?? 1)),
                    'cart_item_id' => (int) ($cartLine->id ?? 0),
                    'plan_amount' => round((float) ($cartLine->grand_total ?? 0), 2),
                    'unit_price' => round((float) ($cartLine->unit_price ?? 0), 2),
                    'domain_name' => $upgradeDomain,
                    'domain' => $upgradeDomain,
                    'remaining_value' => $remainingValue,
                    'remaining_credit' => $remainingValue,
                    'pro_rata_adjustment' => $remainingValue,
                    'applied_pro_rata' => $appliedProRata,
                    'subtotal' => $cartSubtotal,
                    'gst_amount' => $cartGst,
                    'grand_total' => $cartPayable,
                    'final_payable' => $cartPayable,
                ];
            }

            return $this->cartPendingAadhaarResponse(
                $cartId,
                $companyName,
                "Draft PO saved in cart. No {$changeLabel} order created yet — call place_order=1 to place the order.",
                [
                    'order_category' => $isDowngrade ? 'downgrade' : ($upgradeMode === 'partial' ? 'partial_upgrade' : 'upgrade'),
                    'order_type' => $isDowngrade ? 'downgrade' : ($upgradeMode === 'partial' ? 'partial_upgrade' : 'upgrade'),
                    'enrollment_type' => $enrollmentType,
                    'upgrade_mode' => $upgradeMode,
                    'order_sub_id' => $orderSubId > 0 ? $orderSubId : null,
                    'parent_order_id' => (int) ($cart->order_id ?? 0) ?: null,
                    'po_mode' => $poMode,
                    'place_order_required' => true,
                    'domain_name' => $upgradeDomain,
                    'domain' => $upgradeDomain,
                    'remaining_value' => $remainingValue,
                    'remaining_credit' => $remainingValue,
                    'pro_rata_adjustment' => $remainingValue,
                    'applied_pro_rata' => $appliedProRata,
                    'subtotal' => $cartSubtotal,
                    'gst_amount' => $cartGst,
                    'total_amount' => $cartPayable,
                    'plan_amount' => $cartSubtotal > 0 ? $cartSubtotal : $cartPayable,
                    'plans' => $plansOut,
                    'cart_status' => 'active',
                    'cart_item_count' => count($plansOut),
                ],
                $isDowngrade ? 'downgrade' : 'upgrade'
            );
        }

        // Placing for real — drop any leftover draft rows so SuperAdmin only sees the new order.
        $this->cancelLeftoverDraftOrdersForCart($partnerId, $cartId);

        $cartItem = $cart->items->first();
        $planId = (int) ($cartItem->plan_id ?? 0);
        $licenses = max(1, (int) ($cartItem->licenses ?? 1));
        $customerId = (int) ($cart->customer_id ?? 0);

        if ($planId <= 0) {
            return $this->fail("{$changeLabel} plan not found in cart.", 422);
        }

        $upgradeService = app(B2bSubscriptionUpgradeService::class);

        try {
            $subscriptionId = (int) ($cart->order_id ?? 0);
            if ($subscriptionId <= 0 && $orderSubId > 0) {
                $subscriptionId = (int) OrderItem::query()->whereKey($orderSubId)->value('order_id');
            }
            if ($subscriptionId <= 0) {
                $subscriptionId = $upgradeService->resolveSubscriptionId(
                    $partnerId,
                    null,
                    $customerId > 0 ? $customerId : null,
                    null,
                );
            }

            $calcInput = [
                'partner_id' => $partnerId,
                'order_id' => $subscriptionId,
                'plan_id' => $planId,
                'licenses' => $licenses,
                'customer_id' => $customerId > 0 ? $customerId : null,
            ];
            if ($orderSubId > 0) {
                $calcInput['order_item_id'] = $orderSubId;
            }

            $calc = app(RenewalService::class)->upgradeOrderCalculation($calcInput);

            $cartPayable = round((float) ($cart->grand_total ?? 0), 2);
            $cartGst = round((float) ($cart->gst_amount ?? 0), 2);
            $cartProRata = round((float) ($cart->pro_rata_adjustment ?? 0), 2);
            $cartSubtotal = round((float) ($cart->subtotal ?? 0), 2);
            $cartTaxable = $cartPayable > 0 ? max(0, round($cartPayable - $cartGst, 2)) : (float) $calc['taxable_amount'];

            $serviceInput = [
                'partner_id' => $partnerId,
                'subscription_id' => $subscriptionId,
                'plan_id' => $planId,
                'quantity' => $licenses,
                'aadhaar_number' => '',
                'subtotal' => $cartSubtotal > 0 ? $cartSubtotal : $calc['subtotal'],
                'pro_rata_adjustment' => $cartProRata > 0 ? $cartProRata : $calc['pro_rata_adjustment'],
                'taxable_amount' => $cartTaxable,
                'gst_amount' => $cartGst > 0 ? $cartGst : $calc['gst_amount'],
                'payable_amount' => $cartPayable > 0 ? $cartPayable : $calc['payable_amount'],
                'skip_invoice' => true,
                'force_pending' => true,
            ];
            if ($orderSubId > 0) {
                $serviceInput['order_sub_id'] = $orderSubId;
            }

            $result = $isDowngrade
                ? $upgradeService->downgrade($serviceInput)
                : $upgradeService->upgrade($serviceInput);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: "{$changeLabel} validation failed.",
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => "Failed to generate {$changeLabel} order: " . $e->getMessage(),
            ], 500);
        }

        $order = $result['order'];
        $orderId = (int) ($order->id ?? 0);
        $previousSubId = 0;

        try {
            if ($orderId > 0) {
                $previousSubId = (int) ($order->parent_order_sub_id ?? 0);
                if ($previousSubId <= 0 && $orderSubId > 0) {
                    $previousSubId = $orderSubId;
                }
                if ($previousSubId <= 0 && $subscriptionId > 0) {
                    $previousSubId = (int) OrderItem::query()
                        ->where('order_id', $subscriptionId)
                        ->where('status', '!=', 'delete')
                        ->orderBy('id')
                        ->value('id');
                }

                // Persist link on the CH order itself (needed when parent_order_sub_id was missing).
                if ($previousSubId > 0 && (int) ($order->parent_order_sub_id ?? 0) <= 0) {
                    $this->orders->newQuery()->whereKey($orderId)->update([
                        'parent_order_sub_id' => $previousSubId,
                    ]);
                    $order->parent_order_sub_id = $previousSubId;
                }

                if ($upgradeMode === 'partial') {
                    $this->orders->newQuery()->whereKey($orderId)->update([
                        'order_category' => 'partial_upgrade',
                        'previous_plan_id' => (int) ($order->previous_plan_id ?? 0) > 0
                            ? (int) $order->previous_plan_id
                            : $planId,
                    ]);
                    $order->order_category = 'partial_upgrade';
                    if ((int) ($order->previous_plan_id ?? 0) <= 0) {
                        $order->previous_plan_id = $planId;
                    }
                }
            }
            $poLink = null;
            $poNumber = null;
            $poError = null;

            // Keep cart items until Place Order finishes successfully (convert after order + PO).
            $baseOrderId = (int) ($order->parent_order_id ?? $subscriptionId);
            if ($orderId > 0 && $baseOrderId > 0) {
                try {
                    $baseOrder = Order::query()->find($baseOrderId);
                    $newPlan = Plan::query()->find($planId);
                    if ($baseOrder && $newPlan) {
                        $this->persistUpgradeOrderItem(
                            $orderId,
                            $baseOrder,
                            $newPlan,
                            $licenses,
                            $result['summary'] ?? $calc,
                            $order,
                            $previousSubId > 0 ? $previousSubId : null
                        );
                    }
                } catch (\Throwable $e) {
                    Log::warning('order_generation_upgrade_item_failed', [
                        'order_id' => $orderId,
                        'base_order_id' => $baseOrderId,
                        'error' => $e->getMessage(),
                    ]);
                    throw new \RuntimeException(
                        'Failed to persist upgrade order item: ' . $e->getMessage(),
                        0,
                        $e
                    );
                }
            }

            // Always sync previous_order_sub_id after item seed (create path used to miss this).
            if ($orderId > 0 && $previousSubId > 0) {
                OrderItem::query()
                    ->where('order_id', $orderId)
                    ->where(function ($q) {
                        $q->whereNull('previous_order_sub_id')
                            ->orWhere('previous_order_sub_id', 0);
                    })
                    ->update(['previous_order_sub_id' => $previousSubId]);
            }

            if ($orderId > 0) {
                try {
                    $poAmount = round((float) ($order->total_amount ?? $order->credit_used ?? 0), 2);
                    $this->saveGeneratedOrderPoDetails(
                        $orderId,
                        $partnerId,
                        $poAmount,
                        (int) ($order->user_id ?? 0)
                    );

                    $poStored = $this->resolvePoForGeneratedOrder($orderId, $request, $poMode);
                    $poRow = $this->orderPoDetails->newQuery()->where('order_id', $orderId)->first();
                    if (!empty($poStored['ok'])) {
                        $poLink = $poStored['public_url'] ?? null;
                        $poNumber = $poStored['po_number'] ?? null;
                    } else {
                        $poError = (string) ($poStored['error'] ?? 'PO link could not be created.');
                        $poLink = $poRow?->po_document_url ?? null;
                        $poNumber = $poRow?->po_number ?? null;
                    }
                } catch (\Throwable $e) {
                    $poError = $e->getMessage();
                    Log::error('order_generation_upgrade_po_failed', [
                        'order_id' => $orderId,
                        'error' => $poError,
                    ]);
                }
            }

            if ($poError !== null && $poError !== '') {
                throw new \RuntimeException($poError);
            }

            // Finalize only after PO + items succeed — then move to processing.
            // Downgrade: always set a fresh subscription term (today → +duration). Do not keep parent dates.
            if ($orderId > 0) {
                $statusUpdate = ['status' => self::STATUS_PROCESSING];
                if ($isDowngrade) {
                    $downgradeStart = Carbon::now()->startOfDay();
                    $downgradeEnd = app(RenewalService::class)
                        ->subscriptionEndDate(
                            $downgradeStart,
                            (string) ($order->duration ?? '12 month')
                        )
                        ->startOfDay();
                    $statusUpdate['subscription_start_date'] = $downgradeStart->toDateString();
                    $statusUpdate['subscription_end_date'] = $downgradeEnd->toDateString();
                    $order->subscription_start_date = $statusUpdate['subscription_start_date'];
                    $order->subscription_end_date = $statusUpdate['subscription_end_date'];

                    OrderItem::query()
                        ->where('order_id', $orderId)
                        ->update([
                            'subscription_start_date' => $statusUpdate['subscription_start_date'],
                            'subscription_end_date' => $statusUpdate['subscription_end_date'],
                        ]);
                }
                $this->orders->newQuery()->whereKey($orderId)->update($statusUpdate);
            }

            $orderRow = $orderId > 0 ? $this->orders->newQuery()->whereKey($orderId)->first() : null;

            // Place succeeded — convert this upgrade/partial-upgrade cart so the email link cannot reuse it.
            if ($cartId > 0 && $orderId > 0) {
                try {
                    app(PartnerCartService::class)->clearSupersededConvertedCarts(
                        $partnerId,
                        $isDowngrade ? Cart::TYPE_DOWNGRADE : Cart::TYPE_UPGRADE,
                        $cartId
                    );
                    app(PartnerCartService::class)->clearCartPendingAadhaar($cartId);
                    $this->rememberDraftCartLink($orderId, $cartId);
                } catch (\Throwable $e) {
                    Log::warning('order_generation_upgrade_cart_cleanup_failed', [
                        'order_id' => $orderId,
                        'cart_id' => $cartId,
                        'error' => $e->getMessage(),
                    ]);
                }

                $cartIdsToConvert = array_values(array_unique(array_filter([
                    $cartId,
                    (int) ($validated['main_cart_id'] ?? $request->input('main_cart_id') ?? 0),
                    (int) ($request->input('cart_id') ?? 0),
                ])));
                Cart::query()
                    ->whereIn('id', $cartIdsToConvert)
                    ->where('partner_id', $partnerId)
                    ->update(['status' => Cart::STATUS_CONVERTED]);

                try {
                    if (Schema::connection('pgsql_billing')->hasColumn('carts', 'order_id')) {
                        Cart::query()->whereKey($cartId)->update(['order_id' => $orderId]);
                    }
                    if (Schema::connection('pgsql_billing')->hasColumn('orders', 'cart_id')) {
                        $this->orders->newQuery()->whereKey($orderId)->update(['cart_id' => $cartId]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('order_generation_upgrade_cart_link_failed', [
                        'order_id' => $orderId,
                        'cart_id' => $cartId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Send processing notifications for upgrade/downgrade orders
            if ($orderRow) {
                $this->sendProcessingOrderNotifications($orderId);
            }

            $plan = Plan::query()->find($order->plan_id);
            $baseOrderAfter = $baseOrderId > 0 ? Order::query()->find($baseOrderId) : null;

            $domainName = $this->resolveDomainNameForOrder($orderRow ?? $order, $cartId);
            if ($orderId > 0 && $domainName !== '') {
                OrderDomainHelper::persistOnOrderIfMissing($orderId, $domainName);
            }
            $billingPayload = $this->resolveOrderBillingCyclePayload(
                $orderRow ?? $order,
                $plan
            );
            $billingCycle = (string) ($billingPayload['billing_cycle'] ?? '12 Months');
            $subscriptionId = '';
            $orderItemRow = $orderId > 0
                ? OrderItem::query()->where('order_id', $orderId)->orderBy('id')->first()
                : null;
            if ($orderItemRow) {
                $subscriptionId = $this->normalizeSubscriptionIdDisplay(
                    trim((string) ($orderItemRow->subscription_id ?? ''))
                );
                if ($subscriptionId === '') {
                    $subscriptionId = $this->ensureOrderItemSubscriptionId($orderItemRow);
                }
            }
            $summary = $result['summary'] ?? $calc ?? [];
            $remainingValue = round((float) (
                $summary['remaining_value']
                ?? $summary['remaining_credit']
                ?? $summary['pro_rata_adjustment']
                ?? $order->pro_rata_adjustment
                ?? 0
            ), 2);
            $planAmount = round((float) (
                $summary['new_plan_cost']
                ?? $summary['subtotal']
                ?? $order->subtotal
                ?? 0
            ), 2);
            $gstAmount = round((float) ($summary['gst_amount'] ?? $order->gst_amount ?? 0), 2);
            $totalAmount = round((float) ($summary['payable_amount'] ?? $order->total_amount ?? 0), 2);
            $unitPrice = round((float) ($summary['new_unit_price'] ?? $plan->price ?? $order->price ?? 0), 2);
            $appliedProRata = round((float) (
                $summary['applied_pro_rata']
                ?? min($remainingValue, max(0, $planAmount))
            ), 2);

            $plansOut = [[
                'plan_id' => (int) ($order->plan_id ?? $planId),
                'plan_name' => (string) ($plan->name ?? $order->plan_name ?? ''),
                'licenses' => $licenses,
                'unit_price' => $unitPrice,
                'plan_amount' => $planAmount > 0 ? $planAmount : round($unitPrice * $licenses, 2),
                'line_total' => $planAmount > 0 ? $planAmount : round($unitPrice * $licenses, 2),
                'gst' => $gstAmount,
                'gst_amount' => $gstAmount,
                'domain_name' => $domainName,
                'domain' => $domainName,
                'subscription_id' => $subscriptionId,
                'subscription_no' => $subscriptionId,
                'billing_cycle' => $billingCycle,
                'duration' => $billingCycle,
                'subscription_start_date' => $billingPayload['subscription_start_date'] ?? null,
                'subscription_end_date' => $billingPayload['subscription_end_date'] ?? null,
                'start_date' => $billingPayload['start_date'] ?? null,
                'end_date' => $billingPayload['end_date'] ?? null,
                'remaining_value' => $remainingValue,
                'remaining_credit' => $remainingValue,
                'pro_rata_adjustment' => $remainingValue,
                'applied_pro_rata' => $appliedProRata,
                'grand_total' => $totalAmount,
                'final_payable' => $totalAmount,
                'status' => self::STATUS_PROCESSING,
                'order_id' => $orderId,
                'order_no' => (string) ($order->order_no ?? $orderId),
            ]];

            $upgradeData = [
                'order_id' => $orderId,
                'ordId' => (int) $orderId,
                'order_no' => (string) ($order->order_no ?? $orderId),
                'status' => self::STATUS_PROCESSING,
                'order_category' => $isDowngrade
                    ? 'downgrade'
                    : ($upgradeMode === 'partial' ? 'partial_upgrade' : 'upgrade'),
                'sign_required' => $signRequired ? 'yes' : 'no',
                'po_link' => $poLink,
                'po_number' => $poNumber,
                'po_mode' => $poMode,
                'plan_id' => (int) $order->plan_id,
                'plan_name' => (string) ($plan->name ?? $order->plan_name ?? ''),
                'upgraded_licenses' => $licenses,
                'remaining_licenses' => (int) ($baseOrderAfter->quantity ?? 0),
                'base_order_id' => $baseOrderId,
                'base_order_category' => (string) ($baseOrderAfter->order_category ?? ''),
                'domain_name' => $domainName,
                'domain' => $domainName,
                'subscription_id' => $subscriptionId,
                'subscription_no' => $subscriptionId,
                'billing_cycle' => $billingCycle,
                'billingCycle' => $billingCycle,
                'duration' => $billingCycle,
                'subscription_start_date' => $billingPayload['subscription_start_date'] ?? null,
                'subscription_end_date' => $billingPayload['subscription_end_date'] ?? null,
                'start_date' => $billingPayload['start_date'] ?? null,
                'end_date' => $billingPayload['end_date'] ?? null,
                'remaining_value' => $remainingValue,
                'remaining_credit' => $remainingValue,
                'pro_rata_adjustment' => $remainingValue,
                'applied_pro_rata' => $appliedProRata,
                'subtotal' => $planAmount,
                'gst_amount' => $gstAmount,
                'total_amount' => $totalAmount,
                'plan_amount' => $planAmount,
                'cart_id' => $cartId > 0 ? $cartId : null,
                'plans' => $plansOut,
                'orders' => $plansOut,
                'enrollment_type' => $enrollmentType,
                'upgrade_mode' => $upgradeMode,
                'order_sub_id' => $previousSubId > 0 ? $previousSubId : ($orderSubId > 0 ? $orderSubId : null),
                'summary' => $summary,
            ];
            return response()->json([
                'success' => true,
                'message' => "{$changeLabel} order generated successfully.",
                'data' => $upgradeData,
            ], 201);
        } catch (\Throwable $e) {
            if ($orderId > 0) {
                $this->rollbackFailedPlaceOrderAttempt(
                    $partnerId,
                    $cartId,
                    $orderId,
                    $subscriptionId > 0 ? $subscriptionId : null
                );
            }

            Log::error('order_generation_upgrade_finalize_failed', [
                'order_id' => $orderId,
                'cart_id' => $cartId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => "Failed to finalize {$changeLabel} order: " . $e->getMessage(),
                'errors' => ['order' => [$e->getMessage()]],
                'data' => [
                    'cart_id' => $cartId,
                    'main_cart_id' => $cartId,
                    'partner_id' => $partnerId,
                    'order_id' => $cartId,
                    'ordId' => $cartId,
                    'order_rolled_back' => $orderId > 0,
                    'order_removed' => $orderId > 0,
                    'is_temp_order' => true,
                    'real_order' => false,
                ],
            ], 422);
        }
    }

    /**
     * Send notifications for processing orders
     * This is called when order is in 'processing' state
     */
    public function sendProcessingOrderNotifications(int $orderId): void
    {
        $order = $this->orders->newQuery()->whereKey($orderId)->first();
        if (!$order) {
            Log::warning('Order not found for processing notification', ['order_id' => $orderId]);
            return;
        }

        $partnerId = (int) ($order->partner_id ?? 0);
        if ($partnerId <= 0) {
            Log::warning('order_processing_notify_skip_no_partner', ['order_id' => $orderId]);
            return;
        }

        $prefs = $this->notificationPreferences($partnerId);

        // Determine category based on order type
        $category = $this->determineOrderCategory($order);

        // Send SMS if enabled
        if (!empty($prefs['subscription_sms'])) {
            $this->sendProcessingSmsNotification($partnerId, $order, $category);
        }

        // Send WhatsApp if enabled (category template: new / upgrade / downgrade / renewal)
        if (!empty($prefs['subscription_whatsapp'])) {
            $this->sendProcessingWhatsAppNotification($partnerId, $order, $category);
        }

        // Order-placed / in-process email is transactional — always try to send
        // (do not block on subscription_email preference). Idempotent per order briefly.
        $mailLockKey = 'order_processing_mail_sent_' . $orderId;
        if (!Cache::has($mailLockKey)) {
            $this->sendProcessingEmailNotification($orderId, $order, $category);
            Cache::put($mailLockKey, 1, now()->addMinutes(30));
        }
    }

    protected function determineOrderCategory(Order $order): string
    {
        $category = strtolower(trim((string) ($order->order_category ?? 'new')));

        if ($category === 'upgrade') {
            return WhatsAppService::CATEGORY_UPGRADE;
        }
        if ($category === 'downgrade') {
            return WhatsAppService::CATEGORY_DOWNGRADE;
        }
        if ($category === 'renewal') {
            return WhatsAppService::CATEGORY_RENEWAL;
        }

        return WhatsAppService::CATEGORY_PROCESSING;
    }

    protected function sendProcessingSmsNotification(int $partnerId, object $order, string $category): void
    {
        $mobile = trim((string) ($this->partners->newQuery()->whereKey($partnerId)->value('mobile') ?? ''));
        if ($mobile === '') {
            return;
        }

        try {
            $amount = round((float) (
                $order->total_amount
                ?? $order->subtotal
                ?? $order->credit_used
                ?? 0
            ), 2);
            $orderNo = (string) ($order->order_no ?? $order->id ?? '');
            $sms = app(SmsService::class);

            // Send processing SMS
            $sms->sendOrderProcessing($mobile, $orderNo, $amount);
        } catch (\Throwable $e) {
            Log::warning('order_processing_sms_failed', [
                'partner_id' => $partnerId,
                'order_id' => (int) ($order->id ?? 0),
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function sendProcessingWhatsAppNotification(int $partnerId, object $order, string $category): void
    {
        $mobile = trim((string) ($this->partners->newQuery()->whereKey($partnerId)->value('mobile') ?? ''));
        if ($mobile === '') {
            return;
        }

        // New orders are notified after activation, when the invoice PDF exists.
        if (strtolower(trim($category)) === WhatsAppService::CATEGORY_NEW) {
            return;
        }

        try {
            $orderNo = (string) ($order->order_no ?? $order->id ?? '');
            $amount = round((float) (
                $order->total_amount
                ?? $order->subtotal
                ?? $order->credit_used
                ?? 0
            ), 2);

            // Get plan name
            $planName = (string) ($order->plan_name ?? '');
            if ($planName === '' && !empty($order->plan_id)) {
                $planName = (string) ($this->plans->newQuery()->whereKey((int) $order->plan_id)->value('name') ?? '');
            }

            // WhatsApp: text/template only — no invoice/PO attachment
            $context = [
                'amount' => $amount,
                'plan_name' => $planName,
                'details' => 'Your order is being processed',
                'transaction_id' => $orderNo,
                'status' => 'processing',
            ];
            if (in_array($category, [
                WhatsAppService::CATEGORY_RENEWAL,
                WhatsAppService::CATEGORY_UPGRADE,
                WhatsAppService::CATEGORY_DOWNGRADE,
            ], true) && !empty($order->subscription_end_date)) {
                try {
                    $context['end_date'] = Carbon::parse((string) $order->subscription_end_date)->format('d M, Y');
                } catch (\Throwable $e) {
                    $context['end_date'] = (string) $order->subscription_end_date;
                }
            }

            $this->whatsAppService->sendOrderNotification(
                $mobile,
                $orderNo,
                $category !== '' ? $category : WhatsAppService::CATEGORY_PROCESSING,
                $context
            );
        } catch (\Throwable $e) {
            Log::warning('order_processing_whatsapp_failed', [
                'partner_id' => $partnerId,
                'order_id' => (int) ($order->id ?? 0),
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function sendProcessingEmailNotification(int $orderId, object $order, string $category): void
    {
        $partnerId = (int) ($order->partner_id ?? 0);
        if ($partnerId <= 0) {
            return;
        }

        $partner = $this->partners->newQuery()->whereKey($partnerId)->first();
        if (!$partner) {
            Log::warning('order_processing_email_skip_no_partner_row', [
                'partner_id' => $partnerId,
                'order_id' => $orderId,
            ]);
            return;
        }

        $userEmail = trim((string) ($partner->email ?? ''));
        // Fallback: customer email on the order when partner email is missing.
        if ($userEmail === '' || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            $customerId = (int) ($order->user_id ?? 0);
            if ($customerId > 0) {
                $userEmail = trim((string) (
                    $this->customers->newQuery()->whereKey($customerId)->value('email') ?? ''
                ));
            }
        }
        if ($userEmail === '' || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            Log::warning('order_processing_email_skip_invalid_email', [
                'partner_id' => $partnerId,
                'order_id' => $orderId,
                'partner_email' => (string) ($partner->email ?? ''),
            ]);
            return;
        }

        try {
            // Pass order-category label for subject (new/renewal/upgrade), not WhatsApp channel key.
            $mailCategory = strtolower(trim((string) ($order->order_category ?? 'new')));
            if ($mailCategory === '' || $mailCategory === 'standard') {
                $mailCategory = 'new';
            }

            Mail::to($userEmail)->send(new OrderProcessingMail(
                $order instanceof Order ? $order : Order::query()->whereKey($orderId)->firstOrFail(),
                $partner,
                $mailCategory
            ));

            Log::info('order_processing_email_sent', [
                'partner_id' => $partnerId,
                'order_id' => $orderId,
                'email' => $userEmail,
                'category' => $mailCategory,
            ]);
        } catch (\Throwable $e) {
            Log::warning('order_processing_email_failed', [
                'partner_id' => $partnerId,
                'order_id' => $orderId,
                'category' => $category,
                'email' => $userEmail,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function resolvePlanChangeCart(int $partnerId, array $validated, Request $request): ?Cart
    {
        $cartQuery = Cart::query()
            ->with(['items.plan'])
            ->where('partner_id', $partnerId);

        if (!empty($validated['main_cart_id'])) {
            return $cartQuery->whereKey((int) $validated['main_cart_id'])->first();
        }

        $isDowngrade = $this->requestIndicatesDowngrade($request, $validated, null);
        $cartType = $isDowngrade ? Cart::TYPE_DOWNGRADE : Cart::TYPE_UPGRADE;

        // Prefer active cart with items so converted/empty carts are not picked after PO.
        $active = (clone $cartQuery)
            ->where('type', $cartType)
            ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = 'active'")
            ->orderByDesc('id')
            ->first();
        if ($active && $active->items->isNotEmpty()) {
            return $active;
        }

        return $cartQuery
            ->where('type', $cartType)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * partial = fewer licenses moved to a higher plan.
     * full = all current licenses moved to a higher plan.
     * same-plan extra seats are license add (not partial).
     */
    protected function resolveUpgradeModeFromCart(?Cart $cart, ?int $orderSubId = null): string
    {
        if (!$cart) {
            return 'full';
        }

        $cart->loadMissing(['items']);
        $itemType = strtolower(trim((string) ($cart->item_type ?? '')));
        if (str_contains($itemType, 'mode:partial') || str_contains($itemType, 'partial_upgrade')) {
            return 'partial';
        }

        $cartPlanId = (int) ($cart->items->first()->plan_id ?? 0);
        $cartQty = max(1, (int) ($cart->items->first()->licenses ?? 1));
        $sourcePlanId = $this->resolveUpgradeSourcePlanIdFromCart($cart, (int) ($orderSubId ?? 0));
        $sourceQty = 0;
        $sourceSubId = $this->resolveUpgradeSourceSubIdFromCart($cart, (int) ($orderSubId ?? 0));
        if ($sourceSubId > 0) {
            $sourceQty = max(1, (int) (OrderItem::query()->whereKey($sourceSubId)->value('quantity') ?? 0));
        }

        $parentOrderId = (int) ($cart->order_id ?? 0);
        if ($sourceQty > 0 && $cartQty < $sourceQty && $cartPlanId > 0 && $sourcePlanId > 0 && $cartPlanId !== $sourcePlanId) {
            return 'partial';
        }
        if ($cartPlanId > 0 && $sourcePlanId > 0 && $cartPlanId === $sourcePlanId) {
            return 'full';
        }
        if ($sourceQty > 0 && $cartQty < $sourceQty) {
            return 'partial';
        }
        if ($cartPlanId > 0 && $sourcePlanId > 0 && $cartPlanId !== $sourcePlanId) {
            return 'full';
        }

        return 'full';
    }

    /**
     * Source plan the upgrade cart is changing from (line, item_type, cache, parent).
     */
    protected function resolveUpgradeSourcePlanIdFromCart(?Cart $cart, int $orderSubIdHint = 0): int
    {
        if (!$cart) {
            return 0;
        }

        $itemType = trim((string) ($cart->item_type ?? ''));
        if (preg_match('/:plan:(\d+)/', $itemType, $m)) {
            $fromItemType = (int) $m[1];
            if ($fromItemType > 0) {
                return $fromItemType;
            }
        }

        $sourceSubId = $this->resolveUpgradeSourceSubIdFromCart($cart, $orderSubIdHint);
        if ($sourceSubId > 0) {
            $fromLine = (int) (OrderItem::query()->whereKey($sourceSubId)->value('plan_id') ?? 0);
            if ($fromLine > 0) {
                return $fromLine;
            }
        }

        $cartId = (int) ($cart->id ?? 0);
        if ($cartId > 0) {
            $cached = (int) Cache::get('upgrade_cart_source_plan:' . $cartId, 0);
            if ($cached > 0) {
                return $cached;
            }
        }

        $parentOrderId = (int) ($cart->order_id ?? 0);
        $cartPlanId = (int) ($cart->items->first()->plan_id ?? 0);
        if ($parentOrderId > 0 && $cartPlanId > 0) {
            $parentHasSamePlan = OrderItem::query()
                ->where('order_id', $parentOrderId)
                ->where('plan_id', $cartPlanId)
                ->where(function ($q) {
                    $q->whereNull('status')->orWhereRaw("LOWER(TRIM(COALESCE(status, ''))) != 'delete'");
                })
                ->exists();
            if ($parentHasSamePlan) {
                return $cartPlanId;
            }
        }

        if ($parentOrderId > 0) {
            return (int) (Order::query()->whereKey($parentOrderId)->value('plan_id') ?? 0);
        }

        return 0;
    }

    /**
     * Resolve subscription line id for upgrade/partial carts (DB, item_type, cache, parent order).
     */
    protected function resolveUpgradeSourceSubIdFromCart(?Cart $cart, int $orderSubIdHint = 0): int
    {
        if ($orderSubIdHint > 0) {
            return $orderSubIdHint;
        }
        if (!$cart) {
            return 0;
        }

        if (Schema::connection('pgsql_billing')->hasColumn('carts', 'order_sub_id')) {
            $stored = (int) ($cart->order_sub_id ?? 0);
            if ($stored > 0) {
                return $stored;
            }
        }

        $itemType = trim((string) ($cart->item_type ?? ''));
        if (preg_match('/^order_sub:(\d+)/', $itemType, $m)) {
            return (int) $m[1];
        }

        $cartId = (int) ($cart->id ?? 0);
        if ($cartId > 0) {
            $cached = (int) Cache::get('upgrade_cart_source_sub:' . $cartId, 0);
            if ($cached > 0) {
                return $cached;
            }
        }

        $parentOrderId = (int) ($cart->order_id ?? 0);
        $cartPlanId = (int) ($cart->items->first()->plan_id ?? 0);
        if ($parentOrderId <= 0 || $cartPlanId <= 0) {
            return 0;
        }

        $fromPlanId = $cartId > 0 ? (int) Cache::get('upgrade_cart_source_plan:' . $cartId, 0) : 0;
        if ($fromPlanId <= 0 && preg_match('/:plan:(\d+)/', $itemType, $pm)) {
            $fromPlanId = (int) $pm[1];
        }
        $lineQuery = OrderItem::query()
            ->where('order_id', $parentOrderId)
            ->where(function ($q) {
                $q->whereNull('status')->orWhereRaw("LOWER(TRIM(COALESCE(status, ''))) != 'delete'");
            });
        if ($fromPlanId > 0) {
            $lineQuery->where('plan_id', $fromPlanId);
        } elseif ($cartPlanId > 0) {
            // Source line is the CURRENT plan — never match the upgrade target plan_id.
            $lineQuery->where('plan_id', '!=', $cartPlanId);
        }

        $lineId = (int) ($lineQuery->orderBy('id')->value('id') ?? 0);

        return $lineId > 0 ? $lineId : 0;
    }

    /**
     * @return array{source_sub_id: int, source_plan_id: int, source_qty: int, cart_plan_id: int, cart_qty: int}
     */
    protected function resolvePartialUpgradeLicenseContext(?Cart $cart, int $orderSubId = 0): array
    {
        $cartPlanId = (int) ($cart?->items->first()->plan_id ?? 0);
        $cartQty = max(1, (int) ($cart?->items->first()->licenses ?? 1));
        $sourceSubId = $this->resolveUpgradeSourceSubIdFromCart($cart, $orderSubId);
        $sourcePlanId = 0;
        $sourceQty = 0;

        if ($sourceSubId > 0) {
            $sourceItem = OrderItem::query()->whereKey($sourceSubId)->first();
            if ($sourceItem) {
                $sourcePlanId = (int) ($sourceItem->plan_id ?? 0);
                $sourceQty = max(1, (int) ($sourceItem->quantity ?? 1));
            }
        }

        if ($sourcePlanId <= 0 || $sourceQty <= 0) {
            $parentOrderId = (int) ($cart->order_id ?? 0);
            if ($parentOrderId > 0) {
                $parentOrder = $this->orders->newQuery()->whereKey($parentOrderId)->first();
                if ($parentOrder) {
                    if ($sourcePlanId <= 0) {
                        $sourcePlanId = (int) ($parentOrder->plan_id ?? 0);
                    }
                    if ($sourceQty <= 0) {
                        $sourceQty = max(1, (int) ($parentOrder->quantity ?? 1));
                    }
                }

                if ($cartPlanId > 0) {
                    $line = OrderItem::query()
                        ->where('order_id', $parentOrderId)
                        ->where('plan_id', $cartPlanId)
                        ->where(function ($q) {
                            $q->whereNull('status')->orWhereRaw("LOWER(TRIM(COALESCE(status, ''))) != 'delete'");
                        })
                        ->orderBy('id')
                        ->first();
                    if ($line) {
                        $sourceSubId = (int) ($line->id ?? $sourceSubId);
                        $sourcePlanId = (int) ($line->plan_id ?? $sourcePlanId);
                        $sourceQty = max(1, (int) ($line->quantity ?? $sourceQty));
                    }
                }
            }
        }

        return [
            'source_sub_id' => $sourceSubId,
            'source_plan_id' => $sourcePlanId,
            'source_qty' => max(1, $sourceQty),
            'cart_plan_id' => $cartPlanId,
            'cart_qty' => $cartQty,
        ];
    }

    /**
     * Same-plan partial upgrade cannot generate PO / place unless licenses increase.
     */
    protected function partialUpgradeSameLicenseError(?Cart $cart, int $orderSubId, bool $isDowngrade): ?JsonResponse
    {
        if ($isDowngrade || !$cart || $cart->items->isEmpty()) {
            return null;
        }

        $ctx = $this->resolvePartialUpgradeLicenseContext($cart, $orderSubId);
        $sourcePlanId = (int) ($ctx['source_plan_id'] ?? 0);
        $cartPlanId = (int) ($ctx['cart_plan_id'] ?? 0);
        $sourceQty = (int) ($ctx['source_qty'] ?? 0);
        $cartQty = (int) ($ctx['cart_qty'] ?? 0);

        // Plan-change partial (subset qty) is allowed. Same-plan extra seats are license add.
        if ($sourcePlanId <= 0 || $cartPlanId <= 0 || $sourcePlanId !== $cartPlanId) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'License add is separate from upgrade and partial upgrade. Extra seats on the same plan must use license add.',
            'use_endpoint' => 'license-add-to-cart',
            'current_licenses' => $sourceQty,
            'requested_licenses' => $cartQty,
        ], 422);
    }

    /**
     * Recompute upgrade/downgrade cart remaining_value + payable and persist (never wipe items).
     * Does not touch standard/new carts.
     */
    protected function refreshPlanChangeCartPricing(?Cart $cart, ?int $orderSubId = null): ?Cart
    {
        if (!$cart) {
            return null;
        }

        $cartType = strtolower(trim((string) ($cart->type ?? '')));
        if (!in_array($cartType, [Cart::TYPE_UPGRADE, Cart::TYPE_DOWNGRADE], true)) {
            return $cart;
        }

        $cart->loadMissing(['items.plan']);
        $item = $cart->items->first();
        if (!$item) {
            return $cart;
        }

        $plan = $item->plan ?? Plan::query()->find((int) ($item->plan_id ?? 0));
        if (!$plan) {
            return $cart;
        }

        $parentOrderId = (int) ($cart->order_id ?? 0);
        $sourceOrder = $parentOrderId > 0 ? Order::query()->find($parentOrderId) : null;
        $sourceItem = null;
        if ($orderSubId && $orderSubId > 0) {
            $sourceItem = OrderItem::query()->whereKey($orderSubId)->first();
            if ($sourceItem && !$sourceOrder) {
                $sourceOrder = Order::query()->find((int) ($sourceItem->order_id ?? 0));
                if ($sourceOrder) {
                    $cart->order_id = (int) $sourceOrder->id;
                }
            }
        } elseif ($sourceOrder) {
            $sourceItem = OrderItem::query()
                ->where('order_id', (int) $sourceOrder->id)
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '!=', 'delete');
                })
                ->orderBy('id')
                ->first();
        }

        $licenses = max(1, (int) ($item->licenses ?? 1));
        $newUnitPrice = round((float) ($item->unit_price ?? $plan->price ?? 0), 2);
        $proration = app(UpgradeProrationService::class)->resolve(
            $sourceOrder,
            $sourceItem,
            $newUnitPrice,
            $licenses,
            (float) ($cart->gst_percentage ?? 18),
            0.0,
            (int) $plan->id
        );

        $cart->pro_rata_adjustment = round((float) ($proration['pro_rata_adjustment'] ?? 0), 2);
        $cart->discount_amount = round((float) ($proration['coupon_discount'] ?? 0), 2);
        $cart->gst_amount = round((float) ($proration['gst_amount'] ?? 0), 2);
        $cart->grand_total = round((float) ($proration['final_payable'] ?? 0), 2);
        if (Schema::connection('pgsql_billing')->hasColumn('carts', 'subtotal')) {
            $cart->subtotal = round((float) ($proration['new_plan_cost'] ?? 0), 2);
        }
        $cart->status = Cart::STATUS_ACTIVE;
        $cart->save();

        $item->unit_price = round((float) ($proration['new_unit_price'] ?? $newUnitPrice), 2);
        $item->licenses = $licenses;
        $item->grand_total = round(((float) $item->unit_price) * $licenses, 2);
        $item->save();

        return $cart->fresh(['items.plan']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buildPlansOutFromOrderId(
        int $orderId,
        string $domainName = '',
        string $billingCycle = '',
        string $subscriptionId = ''
    ): array {
        if ($orderId <= 0) {
            return [];
        }

        $order = $this->orders->newQuery()->whereKey($orderId)->first();
        if (!$order) {
            return [];
        }

        if ($domainName === '') {
            $domainName = $this->resolveDomainNameForOrder($order, (int) ($order->cart_id ?? 0));
        }

        $items = OrderItem::query()->where('order_id', $orderId)->orderBy('id')->get();
        if ($items->isEmpty()) {
            $plan = Plan::query()->find((int) ($order->plan_id ?? 0));
            $billingPayload = $this->resolveOrderBillingCyclePayload($order, $plan);
            $billingCycle = $billingCycle !== '' ? $billingCycle : (string) ($billingPayload['billing_cycle'] ?? '12 Months');
            $subId = $subscriptionId !== '' ? $subscriptionId : '';

            return [[
                'plan_id' => (int) ($order->plan_id ?? 0),
                'plan_name' => (string) ($order->plan_name ?? $plan->name ?? 'Plan'),
                'licenses' => max(1, (int) ($order->quantity ?? 1)),
                'unit_price' => round((float) ($order->price ?? 0), 2),
                'plan_amount' => round((float) ($order->subtotal ?? $order->total_amount ?? 0), 2),
                'line_total' => round((float) ($order->subtotal ?? $order->total_amount ?? 0), 2),
                'gst' => round((float) ($order->gst_amount ?? 0), 2),
                'gst_amount' => round((float) ($order->gst_amount ?? 0), 2),
                'domain_name' => $domainName,
                'domain' => $domainName,
                'subscription_id' => $subId,
                'subscription_no' => $subId,
                'billing_cycle' => $billingCycle,
                'duration' => $billingCycle,
                'subscription_start_date' => $billingPayload['subscription_start_date'] ?? null,
                'subscription_end_date' => $billingPayload['subscription_end_date'] ?? null,
                'remaining_value' => round((float) ($order->pro_rata_adjustment ?? 0), 2),
                'remaining_credit' => round((float) ($order->pro_rata_adjustment ?? 0), 2),
                'pro_rata_adjustment' => round((float) ($order->pro_rata_adjustment ?? 0), 2),
                'status' => (string) ($order->status ?? ''),
                'order_id' => $orderId,
                'order_no' => (string) ($order->order_no ?? $orderId),
            ]];
        }

        $plans = [];
        foreach ($items as $item) {
            $plan = Plan::query()->find((int) ($item->plan_id ?? $order->plan_id ?? 0));
            $billingPayload = $this->resolveOrderBillingCyclePayload($order, $plan);
            $lineBilling = $billingCycle !== '' ? $billingCycle : (string) ($billingPayload['billing_cycle'] ?? '12 Months');
            $subId = $this->normalizeSubscriptionIdDisplay(trim((string) ($item->subscription_id ?? '')));
            if ($subId === '') {
                $subId = $subscriptionId !== '' ? $subscriptionId : $this->ensureOrderItemSubscriptionId($item);
            }
            $plans[] = [
                'plan_id' => (int) ($item->plan_id ?? $order->plan_id ?? 0),
                'plan_name' => (string) ($item->item_name ?? $order->plan_name ?? $plan->name ?? 'Plan'),
                'licenses' => max(1, (int) ($item->quantity ?? $order->quantity ?? 1)),
                'unit_price' => round((float) ($item->unit_price ?? $order->price ?? 0), 2),
                'plan_amount' => round((float) ($item->total_amount ?? $order->subtotal ?? 0), 2),
                'line_total' => round((float) ($item->total_amount ?? $order->subtotal ?? 0), 2),
                'gst' => round((float) ($item->tax_amount ?? $order->gst_amount ?? 0), 2),
                'gst_amount' => round((float) ($item->tax_amount ?? $order->gst_amount ?? 0), 2),
                'domain_name' => $domainName,
                'domain' => $domainName,
                'subscription_id' => $subId,
                'subscription_no' => $subId,
                'billing_cycle' => $lineBilling,
                'duration' => $lineBilling,
                'subscription_start_date' => $billingPayload['subscription_start_date'] ?? null,
                'subscription_end_date' => $billingPayload['subscription_end_date'] ?? null,
                'remaining_value' => round((float) ($order->pro_rata_adjustment ?? 0), 2),
                'remaining_credit' => round((float) ($order->pro_rata_adjustment ?? 0), 2),
                'pro_rata_adjustment' => round((float) ($order->pro_rata_adjustment ?? 0), 2),
                'status' => (string) ($item->status ?? $order->status ?? ''),
                'order_id' => $orderId,
                'order_no' => (string) ($order->order_no ?? $orderId),
            ];
        }

        return $plans;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function requestIndicatesDowngrade(Request $request, array $validated, ?Cart $cart): bool
    {
        if ($cart !== null) {
            $cartType = strtolower(trim((string) ($cart->type ?? '')));
            if ($cartType === Cart::TYPE_DOWNGRADE) {
                return true;
            }
            if ($cartType === Cart::TYPE_UPGRADE) {
                return false;
            }
        }

        $category = strtolower(trim((string) (
            $validated['order_category']
            ?? $request->input('order_category', '')
        )));
        if ($category === 'downgrade') {
            return true;
        }
        if ($category === 'upgrade') {
            return false;
        }

        if (array_key_exists('is_upgrade', $validated)) {
            return !(bool) $validated['is_upgrade'];
        }
        if ($request->has('is_upgrade')) {
            return !$request->boolean('is_upgrade');
        }

        return false;
    }

    /**
     * @param  array<string, bool|int>  $prefs
     */
    protected function sendSmsNotification(int $partnerId, object $orderRow, string $category, array $prefs): void
    {
        if (empty($prefs['subscription_sms'])) {
            return;
        }

        if ($partnerId <= 0) {
            return;
        }

        $mobile = trim((string) ($this->partners->newQuery()->whereKey($partnerId)->value('mobile') ?? ''));
        if ($mobile === '') {
            return;
        }

        try {
            $amount = round((float) (
                $orderRow->total_amount
                ?? $orderRow->subtotal
                ?? $orderRow->credit_used
                ?? 0
            ), 2);
            $orderNo = (string) ($orderRow->order_no ?? $orderRow->id ?? '');
            $sms = app(SmsService::class);

            if ($category === 'renewal') {
                $endDate = (string) ($orderRow->subscription_end_date ?? '');
                $sms->sendRenewPlan($mobile, $endDate !== '' ? $endDate : 'N/A', $amount);
            } elseif (in_array($category, ['upgrade', 'downgrade'], true)) {
                $planName = '';
                if (!empty($orderRow->plan_id)) {
                    $planName = (string) ($this->plans->newQuery()->whereKey((int) $orderRow->plan_id)->value('name') ?? '');
                }
                $sms->sendUpgradePlatform($mobile, $planName !== '' ? $planName : ($category === 'downgrade' ? 'Downgraded plan' : 'Upgraded plan'), $amount);
            } else {
                $sms->sendPaymentConfirmation($mobile, $amount, $orderNo);
            }
        } catch (\Throwable $e) {
            Log::warning('order_generation_sms_failed', [
                'partner_id' => $partnerId,
                'order_id' => (int) ($orderRow->id ?? 0),
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function sendEmailNotification(int $orderId, object $orderRow, string $category): void
    {
        $partnerId = (int) ($orderRow->partner_id ?? 0);
        if ($partnerId <= 0) {
            return;
        }

        $prefs = $this->notificationPreferences($partnerId);
        if (empty($prefs['subscription_email'])) {
            return;
        }

        $partner = $this->partners->newQuery()->whereKey($partnerId)->first();
        if (!$partner) {
            return;
        }

        $userEmail = trim((string) ($partner->email ?? ''));
        if ($userEmail === '' || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            $planName = (string) ($orderRow->plan_name ?? '');
            if ($planName === '' && !empty($orderRow->plan_id)) {
                $planName = (string) ($this->plans->newQuery()->whereKey((int) $orderRow->plan_id)->value('name') ?? '');
            }
            if ($planName === '') {
                $planName = 'Subscription';
            }

            $amount = round((float) (
                $orderRow->total_amount
                ?? $orderRow->subtotal
                ?? $orderRow->credit_used
                ?? 0
            ), 2);

            $validityDate = Carbon::now()->addYear()->format('d M, Y');
            if (!empty($orderRow->subscription_end_date)) {
                $validityDate = Carbon::parse((string) $orderRow->subscription_end_date)->format('d M, Y');
            }

            $userName = trim((string) ($partner->name ?? $partner->company_name ?? 'Partner'));
            $orderNo = (string) ($orderRow->order_no ?? $orderId);

            if ($category === 'renewal') {
                Mail::to($userEmail)->send(new PlanRenewedMail(
                    $planName,
                    $amount,
                    $validityDate,
                    $userName,
                    $orderNo
                ));
            } elseif (in_array($category, ['upgrade', 'downgrade'], true)) {
                Mail::to($userEmail)->send(new PlanUpgradedMail(
                    $planName,
                    $amount,
                    $validityDate,
                    $userName,
                    $orderNo,
                    $category
                ));
            }
        } catch (\Throwable $e) {
            Log::warning('order_generation_email_failed', [
                'partner_id' => $partnerId,
                'order_id' => $orderId,
                'category' => $category,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @return array<string, bool|int> */
    protected function notificationPreferences(int $partnerId): array
    {
        $defaults = [
            'subscription_sms' => false,
            'subscription_whatsapp' => false,
            'subscription_email' => true,
        ];

        if ($partnerId <= 0) {
            return $defaults;
        }

        $row = $this->notifications->newQuery()->where('partner_id', $partnerId)->first();
        if (!$row) {
            return $defaults;
        }

        return [
            'subscription_sms' => (bool) ($row->subscription_sms ?? false),
            'subscription_whatsapp' => (bool) ($row->subscription_whatsapp ?? false),
            'subscription_email' => (bool) ($row->subscription_email ?? true),
        ];
    }

    protected function resolvePartnerAadhaarNumber(int $partnerId, Request $request): string
    {
        $verificationId = (int) $request->input('aadhaar_verification_id', 0);
        if ($verificationId > 0) {
            $digits = preg_replace(
                '/\D/',
                '',
                (string) $this->partnerAadhaarVerifications->newQuery()->whereKey($verificationId)->value('aadhaar_number')
            ) ?? '';
            if (strlen($digits) === 12) {
                return $digits;
            }
        }

        $row = $this->partnerAadhaarVerifications->newQuery()
            ->where('partner_id', $partnerId)
            ->whereIn('status', ['verified', 'order_linked'])
            ->whereNotNull('verified_at')
            ->orderByDesc('id')
            ->first();

        if (!$row) {
            return '';
        }

        $digits = preg_replace('/\D/', '', (string) ($row->aadhaar_number ?? '')) ?? '';

        return strlen($digits) === 12 ? $digits : '';
    }

    protected function saveGeneratedOrderPoDetails(
        int $orderId,
        int $partnerId,
        float $poAmount,
        ?int $customerId = null
    ): void {
        if ($orderId <= 0) {
            return;
        }

        if ($customerId === null || $customerId <= 0) {
            $customerId = (int) ($this->orders->newQuery()->whereKey($orderId)->value('user_id') ?? 0);
        }

        $signRequired = strtolower(trim((string) ($this->partners->newQuery()->whereKey($partnerId)->value('aadhaar_mandatory') ?? 'no'))) === 'yes';

        $poRow = [
            'order_id' => $orderId,
            'po_mode' => 'generated',
            'po_amount' => round($poAmount, 2),
            'po_date' => now()->toDateString(),
            'journey_status' => 'po_pending',
            'sign_required' => $signRequired ? 'yes' : 'no',
            'aadhar_verified' => 0,
        ];

        if ($customerId > 0) {
            $prefill = app(RenewalService::class)->buildPurchaseOrderPrefillFromCustomer($customerId);
            if ($prefill !== []) {
                $fieldMap = [
                    'manual_name' => 'name',
                    'manual_email' => 'email',
                    'manual_mobile' => 'mobile',
                    'manual_gstin' => 'gstin',
                    'manual_company_name' => 'company_name',
                    'manual_company_address' => 'address',
                    'manual_city' => 'city',
                    'manual_state' => 'state',
                    'manual_country' => 'country',
                    'manual_pincode' => 'postcode',
                ];
                foreach ($fieldMap as $column => $prefillKey) {
                    $value = trim((string) ($prefill[$prefillKey] ?? ''));
                    if ($value !== '') {
                        $poRow[$column] = $value;
                    }
                }
            }
        }

        $existingPo = $this->orderPoDetails->newQuery()->where('order_id', $orderId)->first();
        if ($existingPo && !empty($existingPo->po_number)) {
            $poRow['po_number'] = (string) $existingPo->po_number;
        } else {
            $poRow['po_number'] = $this->nextPoNumber();
        }

        if ($existingPo) {
            $this->orderPoDetails->newQuery()->where('order_id', $orderId)->update($poRow);
        } else {
            $this->orderPoDetails->newQuery()->create($poRow);
        }
    }

    /** @return array{ok: bool, public_url?: string, po_number?: string, error?: string, po_mode?: string} */
    protected function resolvePoForGeneratedOrder(int $orderId, Request $request, string $poMode): array
    {
        $this->normalizePoDocumentUpload($request);
        $poService = app(\App\Services\OrderPoDocumentService::class);

        // Fresh file on this request wins.
        if ($request->hasFile('po_document')) {
            return $poService->attachUploadedPo(
                $orderId,
                $request->file('po_document'),
                $request->input('po_number'),
                $request->input('po_date')
            );
        }

        // Place Order after cart draft: reuse the PO already saved on the cart
        // (do not regenerate a system PDF over an uploaded partner PO).
        $cartId = (int) ($request->input('main_cart_id') ?? $request->input('cart_id') ?? 0);
        if ($cartId <= 0 && $orderId > 0) {
            $cartId = (int) (Cache::get('draft_order_cart_' . $orderId, 0) ?: 0);
        }
        if ($cartId > 0) {
            $cartPoMeta = app(PartnerCartService::class)->getCartPoMeta($cartId);
            if (
                $cartPoMeta !== null
                && ($cartPoMeta['po_mode'] ?? '') === 'uploaded'
                && ($cartPoMeta['po_link'] ?? '') !== ''
            ) {
                return $poService->applyStoredUploadedPoToOrder(
                    $orderId,
                    (string) $cartPoMeta['po_link'],
                    (string) ($cartPoMeta['po_number'] ?? $request->input('po_number') ?? ''),
                    true
                );
            }
        }

        if ($poMode === \App\Services\OrderPoDocumentService::MODE_UPLOADED) {
            return ['ok' => false, 'error' => 'Please upload a PDF purchase order.'];
        }

        return $this->createOrderPo($orderId);
    }

    /**
     * Partner portal: upload external CRM/SAP PO for an existing order.
     * Does NOT skip eSign — if Aadhaar is mandatory and incomplete, order stays pending/draft.
     */
    public function uploadOrderPo(Request $request): JsonResponse
    {
        $this->normalizePoDocumentUpload($request);

        $validated = $request->validate(array_merge([
            'partner_id' => ['required', 'integer', Rule::exists(Partner::class, 'id')],
            'order_id' => ['required', 'integer', Rule::exists(Order::class, 'id')],
            'po_number' => ['nullable', 'string', 'max:100'],
            'po_date' => ['nullable', 'date'],
        ], $this->poDocumentValidationRules(true)));

        $partnerId = (int) $validated['partner_id'];
        $orderId = (int) $validated['order_id'];
        $order = $this->orders->newQuery()->whereKey($orderId)->first();
        if (!$order || (int) ($order->partner_id ?? 0) !== $partnerId) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found for this partner.',
            ], 404);
        }

        $signRequired = strtolower(trim((string) (
            $this->partners->newQuery()->whereKey($partnerId)->value('aadhaar_mandatory') ?? 'no'
        ))) === 'yes';

        $poRowBefore = $this->orderPoDetails->newQuery()->where('order_id', $orderId)->first();
        $aadhaarDone = (int) ($poRowBefore?->aadhar_verified ?? 0) === 1
            || $this->resolvePartnerAadhaarNumber($partnerId, $request) !== '';
        $esignStillPending = $signRequired && !$aadhaarDone;

        $result = app(\App\Services\OrderPoDocumentService::class)->attachUploadedPo(
            $orderId,
            $request->file('po_document'),
            $request->input('po_number'),
            $request->input('po_date'),
            null,
            !$esignStillPending
        );

        if (empty($result['ok'])) {
            return response()->json([
                'success' => false,
                'message' => (string) ($result['error'] ?? 'PO upload failed.'),
            ], 422);
        }

        $status = strtolower(trim((string) ($order->status ?? '')));
        $cartId = (int) ($order->source_cart_id ?? $order->cart_id ?? 0);

        // Only promote to processing when eSign is not required / already done.
        if (!$esignStillPending && in_array($status, ['draft', self::STATUS_DRAFT, self::STATUS_PENDING], true)) {
            $this->orders->newQuery()->whereKey($orderId)->update([
                'status' => self::STATUS_PROCESSING,
                'updated_at' => now(),
            ]);
            if ($cartId > 0) {
                app(PartnerCartService::class)->clearCartPendingAadhaar($cartId);
                $this->forgetDraftCartLink($cartId, $orderId);
            }
            $status = self::STATUS_PROCESSING;
        } elseif ($esignStillPending) {
            // Keep cart pending so listing matches generate-PO behaviour.
            if ($cartId > 0) {
                app(PartnerCartService::class)->markCartPendingAadhaar($cartId, 'new');
                $meta = app(PartnerCartService::class)->getCartPoMeta($cartId);
                if ($meta === null || ($meta['po_link'] ?? '') === '') {
                    app(PartnerCartService::class)->saveCartPoMeta(
                        $cartId,
                        (string) ($result['public_url'] ?? ''),
                        (string) ($result['po_number'] ?? ''),
                        'uploaded'
                    );
                }
            }
            if (in_array($status, ['draft', self::STATUS_DRAFT], true)) {
                // leave draft; do not move to processing
            } elseif ($status === self::STATUS_PROCESSING) {
                $this->orders->newQuery()->whereKey($orderId)->update([
                    'status' => self::STATUS_PENDING,
                    'updated_at' => now(),
                ]);
                $status = self::STATUS_PENDING;
            }
        }

        $poRow = $this->orderPoDetails->newQuery()->where('order_id', $orderId)->first();

        return response()->json([
            'success' => true,
            'message' => $esignStillPending
                ? 'PO uploaded. eSign still required before the order can move to processing.'
                : 'PO uploaded successfully.',
            'data' => [
                'order_id' => $orderId,
                'status' => $status,
                'po_mode' => 'uploaded',
                'po_number' => $result['po_number'] ?? ($poRow->po_number ?? null),
                'po_link' => $result['public_url'] ?? ($poRow->po_document_url ?? null),
                'final_po_link' => $poRow->final_po_link ?? ($result['public_url'] ?? null),
                'journey_status' => $poRow->journey_status ?? ($esignStillPending ? 'po_done' : 'placed'),
                'sign_required' => $signRequired ? 'yes' : 'no',
                'aadhaar_pending' => $esignStillPending,
            ],
        ]);
    }

    /** @return array{ok: bool, public_url?: string, po_number?: string, error?: string} */
    protected function createOrderPo(int $orderId): array
    {
        if ($orderId <= 0) {
            return ['ok' => false, 'error' => 'Invalid order id for PO generation.'];
        }

        if (app(\App\Services\OrderPoDocumentService::class)->isUploadedPo($orderId)) {
            $poRow = $this->orderPoDetails->newQuery()->where('order_id', $orderId)->first();

            return [
                'ok' => true,
                'public_url' => (string) ($poRow->po_document_url ?? $poRow->final_po_link ?? ''),
                'po_number' => (string) ($poRow->po_number ?? ''),
                'po_mode' => 'uploaded',
            ];
        }

        try {
            $order = $this->orders->newQuery()->whereKey($orderId)->first();
            if (!$order) {
                return ['ok' => false, 'error' => 'Order not found for PO generation.'];
            }

            $poRow = $this->orderPoDetails->newQuery()->where('order_id', $orderId)->first();
            $poNumber = trim((string) ($poRow?->po_number ?? ''));
            if ($poNumber === '') {
                $poNumber = $this->nextPoNumber();
            }

            $aadhaarVerified = (int) ($poRow?->aadhar_verified ?? 0) === 1;
            $signRequired = strtolower(trim((string) ($poRow?->sign_required ?? ''))) === 'yes';
            if (!$signRequired) {
                $partnerId = (int) ($order->partner_id ?? 0);
                if ($partnerId > 0) {
                    $signRequired = strtolower(trim((string) (
                        $this->partners->newQuery()->whereKey($partnerId)->value('aadhaar_mandatory') ?? 'no'
                    ))) === 'yes';
                }
            }

            $poCarbon = Carbon::parse($poRow?->po_date ?? $order->created_at ?? now());
            $total = round((float) ($order->total_amount ?? 0), 2);

            if ($poRow && trim((string) ($poRow->po_number ?? '')) === '') {
                $this->orderPoDetails->newQuery()->where('order_id', $orderId)->update([
                    'po_number' => $poNumber,
                    'updated_at' => now(),
                ]);
            }

            $billing = app(BillingController::class);
            $stored = $billing->saveOrderPoPdfDocument($orderId);
            if ($stored === null || empty($stored['public_url'])) {
                return ['ok' => false, 'error' => 'Could not generate PO PDF. Check dompdf/GD extensions and storage permissions.'];
            }

            $publicUrl = (string) $stored['public_url'];
            $poNumber = trim((string) ($stored['po_number'] ?? $poNumber));

            // PDF ready = PO step done. No Aadhaar needed → journey complete (placed).
            if ($aadhaarVerified || !$signRequired) {
                $journeyStatus = 'placed';
            } else {
                $journeyStatus = 'po_done';
            }

            $poUpdate = [
                'po_number' => $poNumber,
                'po_document_url' => $publicUrl,
                'journey_status' => $journeyStatus,
                'po_mode' => 'generated',
                'updated_at' => now(),
            ];
            if ($aadhaarVerified || !$signRequired) {
                $poUpdate['status'] = 'active';
                $poUpdate['final_po_link'] = $publicUrl;
                $poUpdate['sign_required'] = $signRequired ? 'yes' : 'no';
            } else {
                $poUpdate['status'] = 'processing';
            }

            if ($poRow) {
                $this->orderPoDetails->newQuery()->where('order_id', $orderId)->update($poUpdate);
            } else {
                $poUpdate['order_id'] = $orderId;
                $poUpdate['po_amount'] = $total;
                $poUpdate['po_date'] = $poCarbon->toDateString();
                $this->orderPoDetails->newQuery()->create($poUpdate);
            }

            $this->orders->newQuery()->whereKey($orderId)->update([
                'po_document_url' => $publicUrl,
            ]);

            // Stamp multi-plan lines onto order_po_details.po_items / items.
            try {
                $billing->syncPoDetailsFromOrder($orderId, $order);
            } catch (\Throwable $e) {
                Log::warning('createOrderPo: syncPoDetailsFromOrder failed', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }

            return [
                'ok' => true,
                'public_url' => $publicUrl,
                'po_number' => $poNumber,
                'po_mode' => 'generated',
            ];
        } catch (\Throwable $e) {
            Log::error('createOrderPo failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    protected function nextPoNumber(): string
    {
        $rows = $this->orderPoDetails->newQuery()
            ->whereNotNull('po_number')
            ->whereRaw("po_number <> ''")
            ->pluck('po_number');

        $max = 0;
        foreach ($rows as $po) {
            $val = strtoupper(trim((string) $po));
            if (preg_match('/^PON(\d{5,})$/', $val, $m)) {
                $n = (int) $m[1];
                if ($n > $max) {
                    $max = $n;
                }
            }
        }

        return 'PON' . str_pad((string) ($max + 1), 5, '0', STR_PAD_LEFT);
    }

    /** @return array{order_no: string, sr_no: int} */
    protected function nextOrderIdentifiers(): array
    {
        $latestOrder = $this->orders->newQuery()
            ->select('sr_no')
            ->orderByDesc('sr_no')
            ->lockForUpdate()
            ->first();

        $nextSrNo = $latestOrder ? ((int) $latestOrder->sr_no + 1) : 1;

        $orderPrefix = $this->readOrderPrefix();
        $orderNo = $orderPrefix . str_pad((string) $nextSrNo, 5, '0', STR_PAD_LEFT);

        while ($this->orders->newQuery()->where('order_no', $orderNo)->exists()) {
            $nextSrNo++;
            $orderNo = $orderPrefix . str_pad((string) $nextSrNo, 5, '0', STR_PAD_LEFT);
        }

        return [
            'order_no' => $orderNo,
            'sr_no' => $nextSrNo,
        ];
    }

    protected function readOrderPrefix(): string
    {
        $prefix = (string) $this->adminSettings->newQuery()->whereKey(1)->value('order_prefix');
        if (trim($prefix) !== '') {
            return strtoupper(trim($prefix));
        }

        $prefix = (string) $this->adminSettings->newQuery()->orderByDesc('id')->value('order_prefix');
        if (trim($prefix) !== '') {
            return strtoupper(trim($prefix));
        }

        return 'TNX';
    }

    public function orderAadhaarInit(Request $request): JsonResponse
    {
        $pendingRefError = $this->applyPendingEsignRefToRequest($request);
        if ($pendingRefError !== null) {
            return $pendingRefError;
        }

        // Cart-only flow (no order yet until eSign completes).
        $cartId = (int) ($request->input('main_cart_id') ?? $request->input('cart_id') ?? 0);
        $orderId = (int) $request->input('order_id', 0);

        if ($orderId <= 0 && $cartId > 0) {
            $validated = $request->validate([
                'main_cart_id' => ['nullable', 'integer', Rule::exists(Cart::class, 'id')],
                'cart_id' => ['nullable', 'integer', Rule::exists(Cart::class, 'id')],
                'aadhaar_number' => ['required', 'digits:12'],
                'customer_id' => ['nullable', 'integer', Rule::exists(Customer::class, 'id')],
                'partner_id' => ['nullable', 'integer', Rule::exists(Partner::class, 'id')],
            ]);

            $cart = $this->carts->newQuery()->whereKey($cartId)->first();
            if (!$cart) {
                return $this->fail('Cart not found.', 404);
            }

            $partnerId = (int) ($validated['partner_id'] ?? $cart->partner_id ?? 0);
            if ($partnerId > 0 && (int) ($cart->partner_id ?? 0) !== $partnerId) {
                return $this->fail('Cart does not belong to this partner.', 422);
            }

            $customerIdOverride = isset($validated['customer_id']) ? (int) $validated['customer_id'] : null;
            try {
                $init = app(PartnerAadhaarVerificationService::class)->initForCart(
                    (string) $validated['aadhaar_number'],
                    $cartId,
                    $customerIdOverride
                );

                if (empty($init['ok'])) {
                    return $this->fail((string) ($init['message'] ?? 'Aadhaar init failed.'), 422);
                }

                $pendingRef = app(PartnerCartService::class)->issueCartPendingEsignRef(
                    $cartId,
                    $partnerId
                );

                $payload = [
                    'order_id' => $pendingRef !== '' ? $pendingRef : null,
                    'cart_id' => $cartId,
                    'main_cart_id' => $cartId,
                    'ordId' => (int) ($orderId ?? 0),
                    'pending_ref_id' => $pendingRef !== '' ? $pendingRef : null,
                    'order_no' => $pendingRef !== '' ? $pendingRef : null,
                    'is_temp_order' => true,
                    'aadhaar_verification_id' => $init['aadhaar_verification_id'] ?? null,
                    'aadhaar_masked' => $init['masked'] ?? '',
                    'aadhaar_ref_no' => $init['ref_no'] ?? '',
                    'mode' => $init['mode'] ?? '',
                    'aadhaar_pending' => true,
                ];
                if (($init['mode'] ?? '') === 'test') {
                    $payload['test_otp'] = (string) (config('services.aadhaar.mock_otps')[0] ?? '123456');
                }

                return response()->json([
                    'success' => true,
                    'message' => (string) ($init['message'] ?? 'OTP sent successfully.'),
                    'data' => $payload,
                ]);
            } catch (\Throwable $e) {
                Log::error('order_aadhaar_init_cart_failed', [
                    'cart_id' => $cartId,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 500);
            }
        }

        $validated = $request->validate([
            'order_id' => ['required', 'integer', Rule::exists(Order::class, 'id')],
            'aadhaar_number' => ['required', 'digits:12'],
        ]);

        $aadhaarNumber = (string) $validated['aadhaar_number'];
        $orderId = (int) $validated['order_id'];

        try {
            $init = app(PartnerAadhaarVerificationService::class)->initForGeneratedOrder($orderId, $aadhaarNumber);

            if (empty($init['ok'])) {
                return $this->fail((string) ($init['message'] ?? 'Aadhaar init failed.'), 422);
            }

            $payload = [
                'order_id' => $orderId,
                'aadhaar_verification_id' => $init['aadhaar_verification_id'] ?? null,
                'aadhaar_masked' => $init['masked'] ?? '',
                'aadhaar_ref_no' => $init['ref_no'] ?? '',
                'mode' => $init['mode'] ?? '',
            ];
            if (($init['mode'] ?? '') === 'test') {
                $payload['test_otp'] = (string) (config('services.aadhaar.mock_otps')[0] ?? '123456');
            }

            return response()->json([
                'success' => true,
                'message' => (string) ($init['message'] ?? 'OTP sent successfully.'),
                'data' => $payload,
            ]);
        } catch (\Throwable $e) {
            Log::error('order_aadhaar_init_failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function orderAadhaarVerify(Request $request): JsonResponse
    {
        $pendingRefError = $this->applyPendingEsignRefToRequest($request);
        if ($pendingRefError !== null) {
            return $pendingRefError;
        }

        $gateway = app(AadhaarVerificationGateway::class);
        $cartId = (int) ($request->input('main_cart_id') ?? $request->input('cart_id') ?? 0);
        $orderId = (int) $request->input('order_id', 0);

        // Cart-only flow: verify OTP on cart, then create the real order.
        if ($orderId <= 0 && $cartId > 0) {
            return $this->verifyCartAadhaarAndCreateOrder($request, $cartId, $gateway);
        }

        $validated = $request->validate([
            'order_id' => ['required', 'integer', Rule::exists(Order::class, 'id')],
            'otp' => $gateway->isTestMode() ? ['nullable', 'string', 'max:10'] : ['required', 'string', 'max:10'],
            'aadhaar_number' => ['nullable', 'digits:12'],
        ]);

        $orderId = (int) $validated['order_id'];
        $aadhaarNumber = (string) ($validated['aadhaar_number'] ?? '');

        try {
            $verify = app(PartnerAadhaarVerificationService::class)->verifyOtpForGeneratedOrder(
                $orderId,
                (string) ($validated['otp'] ?? ''),
                $aadhaarNumber !== '' ? $aadhaarNumber : null
            );

            if (empty($verify['ok'])) {
                return response()->json([
                    'success' => false,
                    'message' => (string) ($verify['message'] ?? 'OTP verification failed.'),
                    'data' => null
                ], 422);
            }

            $refNo = (string) ($verify['ref_no'] ?? '');
            $masked = (string) ($verify['aadhaar_masked'] ?? '');
            $aadhaarVerificationId = isset($verify['aadhaar_verification_id']) ? (int) $verify['aadhaar_verification_id'] : null;

            $orderBefore = $this->orders->newQuery()->whereKey($orderId)->first();
            $category = strtolower(trim((string) ($orderBefore->order_category ?? '')));
            $orderNo = strtoupper(trim((string) ($orderBefore->order_no ?? '')));
            $isRenewal = in_array($category, ['renewal', 'renew', 'renewed'], true) || str_starts_with($orderNo, 'RN');
            // Renew after e-sign → Renewal Pending. New/upgrade → processing.
            $finalStatus = $isRenewal ? self::STATUS_PENDING : self::STATUS_PROCESSING;

            $orderUpdate = ['status' => $finalStatus];
            if ($aadhaarVerificationId > 0) {
                $orderUpdate['aadhaar_verification_id'] = $aadhaarVerificationId;
            }
            $this->orders->newQuery()->whereKey($orderId)->update($orderUpdate);

            $aadhaarPoUpdate = [
                'aadhaar_masked' => $masked,
                'aadhar_verified' => 1,
                'aadhar_verified_at' => now(),
            ];
            if ($refNo !== '') {
                $aadhaarPoUpdate['aadhaar_ref_no'] = $refNo;
            }
            $this->orderPoDetails->newQuery()->where('order_id', $orderId)->update($aadhaarPoUpdate);

            $stored = $this->createOrderPo($orderId);
            if (empty($stored['ok']) || empty($stored['public_url'])) {
                $poFailMsg = (string) ($stored['error'] ?? 'PO finalization failed.');
                Log::error('order_aadhaar_verify_finalize_failed', [
                    'order_id' => $orderId,
                    'error' => $poFailMsg,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Aadhaar verified but PO finalization failed: ' . $poFailMsg,
                    'data' => null
                ], 500);
            }

            $finalUrl = (string) $stored['public_url'];
            $poNumber = (string) ($stored['po_number'] ?? '');

            $finalPoUpdate = [
                'status' => $finalStatus,
                'updated_at' => now(),
            ];
            if ($finalUrl !== '') {
                $finalPoUpdate['po_document_url'] = $finalUrl;
                $finalPoUpdate['final_po_link'] = $finalUrl;
            }
            $this->orderPoDetails->newQuery()->where('order_id', $orderId)->update($finalPoUpdate);

            $this->orders->newQuery()->whereKey($orderId)->update([
                'po_document_url' => $finalUrl,
            ]);

            $order = $this->orders->newQuery()->whereKey($orderId)->first();
            $poRow = $this->orderPoDetails->newQuery()->where('order_id', $orderId)->first();

            // Processing notifications only for non-renew (renew stays Renewal Pending).
            if ($finalStatus === self::STATUS_PROCESSING) {
                $this->sendProcessingOrderNotifications($orderId);
            }

            // Aadhaar complete → convert linked cart (draft was keeping it active for CartDetails).
            $this->convertCartAfterAadhaarVerified($order);
            app(PartnerCartService::class)->clearCartPendingAadhaar(
                (int) Cache::get('draft_order_cart_' . $orderId, (int) ($order->cart_id ?? 0))
            );
            $companyName = '';
            $customerId = (int) ($order->user_id ?? 0);
            if ($customerId > 0) {
                $companyName = trim((string) ($this->customers->newQuery()->whereKey($customerId)->value('company_name') ?? ''));
            }

            $orderItems = $this->orderItems->newQuery()
                ->where('order_id', $orderId)
                ->orderBy('id')
                ->get();

            $firstPlanId = (int) ($orderItems->first()->plan_id ?? $order->plan_id ?? 0);
            $providerId = $firstPlanId > 0
                ? ((int) ($this->plans->newQuery()->whereKey($firstPlanId)->value('provider_id') ?? 0) ?: null)
                : null;

            $orders = [];
            if ($orderItems->isEmpty()) {
                $planId = (int) ($order->plan_id ?? 0);
                $planName = trim((string) ($order->plan_name ?? ''));

                if ($planId > 0) {
                    $planRow = $this->plans->newQuery()->whereKey($planId)->first();
                    if ($planRow) {
                        if ($planName === '') {
                            $planName = (string) ($planRow->name ?? '');
                        }
                    }
                }
                $orders[] = [
                    'order_id' => $orderId,
                    'order_no' => (string) ($order->order_no ?? $orderId),

                    'plan_id' => $planId,
                    'plan_name' => $planName !== '' ? $planName : 'Plan',
                    'licenses' => max(1, (int) ($order->quantity ?? 1)),
                    'plan_amount' => round((float) ($order->subtotal ?? $order->total_amount ?? 0), 2),
                    'status' => $finalStatus,
                    'company_name' => $companyName,
                    'domain_name' => (string) ($order->domain_name ?? ''),
                    'subscription_id' => (string) (OrderItem::query()->where('order_id', $orderId)->orderBy('id')->value('subscription_id') ?? ''),
                ];
            } else {
                foreach ($orderItems as $item) {
                    $planId = (int) ($item->plan_id ?? $order->plan_id ?? 0);
                    $planName = trim((string) ($item->item_name ?? $order->plan_name ?? ''));
                    $itemProviderId = $providerId;
                    if ($planId > 0) {
                        $planRow = $this->plans->newQuery()->whereKey($planId)->first();
                        if ($planRow) {
                            if ($planName === '') {
                                $planName = (string) ($planRow->name ?? '');
                            }
                            $itemProviderId = (int) ($planRow->provider_id ?? 0) ?: null;
                            if ($providerId === null) {
                                $providerId = $itemProviderId;
                            }
                        }
                    }
                    $orders[] = [
                        'order_id' => $orderId,
                        'order_no' => (string) ($order->order_no ?? $orderId),
                        'provider_id' => $itemProviderId,
                        'plan_id' => $planId,
                        'plan_name' => $planName !== '' ? $planName : 'Plan',
                        'licenses' => max(1, (int) ($item->quantity ?? $order->quantity ?? 1)),
                        'plan_amount' => round((float) ($item->total_amount ?? $order->subtotal ?? $order->total_amount ?? 0), 2),
                        'status' => $finalStatus,
                        'company_name' => $companyName,
                        'domain_name' => (string) ($order->domain_name ?? ''),
                        'subscription_id' => (string) ($item->subscription_id ?? ''),
                        'unit_price' => round((float) ($item->unit_price ?? 0), 2),
                    ];
                }
            }

            $poDetails = [
                'po_status' => (string) ($poRow?->status ?? $finalStatus),
                'po_number' => $this->cleanPoNumberDisplay(
                    (string) ($poNumber !== '' ? $poNumber : ($poRow?->po_number ?? ''))
                ),
                'final_po_link' => $finalUrl !== '' ? $finalUrl : ($poRow?->final_po_link ?? null),
                'po_link' => $finalUrl !== '' ? $finalUrl : ($poRow?->po_document_url ?? null),
            ];

            $subtotal = round((float) ($order->subtotal ?? $order->total_amount ?? 0), 2);
            $gstAmount = round((float) ($order->gst_amount ?? 0), 2);
            $gstRate = round((float) ($order->gst_rate ?? 0), 2);
            $couponDiscount = round((float) ($order->discount_amount ?? 0), 2);
            $totalAmount = round((float) ($order->total_amount ?? 0), 2);

            $rootSubscriptionId = '';
            foreach ($orders as $ordLine) {
                $cand = $this->normalizeSubscriptionIdDisplay(trim((string) ($ordLine['subscription_id'] ?? '')));
                if ($cand !== '') {
                    $rootSubscriptionId = $cand;
                    break;
                }
            }
            if ($rootSubscriptionId === '') {
                $firstItem = OrderItem::query()->where('order_id', $orderId)->orderBy('id')->first();
                if ($firstItem) {
                    $rootSubscriptionId = $this->ensureOrderItemSubscriptionId($firstItem);
                }
            }
            $billingPayload = $this->resolveOrderBillingCyclePayload($order);
            $uiDates = $this->formatOrderDateFields($order->created_at ?? null);
            $poNumberClean = (string) ($poDetails['po_number'] ?? '');

            return response()->json([
                'success' => true,
                'message' => (string) ($verify['message'] ?? 'Aadhaar verified successfully.'),
                'data' => [
                    'provider_id' => $providerId,
                    'order_id' => $orderId,
                    'ordId' => $orderId,
                    'order_no' => (string) ($order->order_no ?? $orderId),
                    'orderNo' => (string) ($order->order_no ?? $orderId),
                    'status' => $finalStatus,
                    'orders' => $orders,
                    'subscription_id' => $rootSubscriptionId,
                    'subscription_no' => $rootSubscriptionId,
                    'subscriptionId' => $rootSubscriptionId,
                    'billing_cycle' => $billingPayload['billing_cycle'] ?? null,
                    'billingCycle' => $billingPayload['billing_cycle'] ?? null,
                    'duration' => $billingPayload['billing_cycle'] ?? null,
                    'subscription_start_date' => $billingPayload['subscription_start_date'] ?? null,
                    'subscription_end_date' => $billingPayload['subscription_end_date'] ?? null,
                    'order_date' => $uiDates['order_date'],
                    'order_date_iso' => $uiDates['order_date_iso'],
                    'orderDate' => $uiDates['orderDate'],
                    'created_at' => $uiDates['created_at'],
                    'domain_name' => (string) ($order->domain_name ?? ''),
                    'domain' => (string) ($order->domain_name ?? ''),
                    'company_name' => $companyName,
                    'aadhaar_verification_id' => $verify['aadhaar_verification_id'] ?? null,
                    'aadhaar_masked' => $verify['aadhaar_masked'] ?? '',
                    'po_status' => $poDetails['po_status'],
                    'po_number' => $poNumberClean,
                    'po_date' => $uiDates['order_date'],
                    'po_document_name' => $poNumberClean,
                    'document_name' => $poNumberClean,
                    'document_label' => $poNumberClean,
                    'po_label' => $poNumberClean,
                    'documents' => $poNumberClean !== '' || !empty($poDetails['po_link']) ? [[
                        'type' => 'purchase_order',
                        'title' => 'Purchase Order',
                        'name' => $poNumberClean,
                        'label' => $poNumberClean,
                        'po_number' => $poNumberClean,
                        'link' => $poDetails['po_link'],
                        'url' => $poDetails['po_link'],
                    ]] : [],
                    'final_po_link' => $poDetails['final_po_link'],
                    'po_link' => $poDetails['po_link'],
                    'po_details' => $poDetails,
                    'gst' => $gstRate,
                    'discount_amount' => round((float) ($order->discount_amount ?? 0), 2),
                    'subtotal' => $subtotal,
                    'gst_amount' => $gstAmount,
                    'total_amount' => $totalAmount,
                ],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('order_aadhaar_verify_failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Aadhaar verification failed. Please try again.',
                'data' => null
            ], 500);
        }
    }

    /**
     * Cart eSign: verify OTP on cart, then create order (no order existed before eSign).
     */
    protected function verifyCartAadhaarAndCreateOrder(
        Request $request,
        int $cartId,
        AadhaarVerificationGateway $gateway
    ): JsonResponse {
        $validated = $request->validate([
            'main_cart_id' => ['nullable', 'integer', Rule::exists(Cart::class, 'id')],
            'cart_id' => ['nullable', 'integer', Rule::exists(Cart::class, 'id')],
            'partner_id' => ['nullable', 'integer', Rule::exists(Partner::class, 'id')],
            'otp' => $gateway->isTestMode() ? ['nullable', 'string', 'max:10'] : ['required', 'string', 'max:10'],
            'aadhaar_number' => ['nullable', 'digits:12'],
        ]);

        $cart = $this->carts->newQuery()->with(['items.plan', 'customer'])->whereKey($cartId)->first();
        if (!$cart) {
            return $this->fail('Cart not found.', 404);
        }

        $partnerId = (int) ($validated['partner_id'] ?? $cart->partner_id ?? 0);
        if ($partnerId <= 0) {
            return $this->fail('partner_id is required.', 422);
        }
        if ((int) ($cart->partner_id ?? 0) !== $partnerId) {
            return $this->fail('Cart does not belong to this partner.', 422);
        }

        $aadhaarService = app(PartnerAadhaarVerificationService::class);
        $aadhaarRecord = $aadhaarService->getLatestForCart($cartId);
        if ($aadhaarRecord === null) {
            return $this->fail(
                'No Aadhaar record for this cart. Call order-aadhaar-init with main_cart_id + aadhaar_number first.',
                422
            );
        }

        $aadhaarNumber = preg_replace(
            '/\D/',
            '',
            (string) ($validated['aadhaar_number'] ?? $aadhaarRecord['aadhaar_number'] ?? '')
        ) ?? '';
        if (strlen($aadhaarNumber) !== 12) {
            return $this->fail('Aadhaar number must be 12 digits. Call order-aadhaar-init first.', 422);
        }

        $otp = (string) ($validated['otp'] ?? '');
        if ($otp === '' && $gateway->isTestMode()) {
            $otp = (string) (config('services.aadhaar.mock_otps')[0] ?? '123456');
        }
        if (!$gateway->isTestMode() && $otp === '') {
            return $this->fail('OTP is required.', 422, ['otp' => ['OTP is required.']]);
        }

        $otpResult = $aadhaarService->verify($aadhaarNumber, $otp, $cartId);
        if (empty($otpResult['ok'])) {
            return $this->fail((string) ($otpResult['message'] ?? 'OTP verification failed.'), 422, [
                'otp' => [(string) ($otpResult['message'] ?? 'OTP verification failed.')],
            ]);
        }

        $aadhaarVerificationId = (int) ($otpResult['aadhaar_verification_id'] ?? $aadhaarRecord['id'] ?? 0);

        $cartService = app(PartnerCartService::class);
        $flow = $cartService->getCartPendingAadhaarFlow($cartId);
        $cartType = strtolower(trim((string) ($cart->type ?? 'standard')));
        if (in_array($cartType, ['renew', 'renewal'], true)) {
            $flow = 'renew';
        } elseif ($cartType === 'upgrade') {
            $flow = 'upgrade';
        } elseif ($cartType === 'downgrade') {
            $flow = 'downgrade';
        }

        // Create the real order now that eSign is done (route by cart flow).
        $payload = [
            'partner_id' => $partnerId,
            'main_cart_id' => $cartId,
            'cart_id' => $cartId,
            'aadhaar_number' => $aadhaarNumber,
            'aadhaar_verification_id' => $aadhaarVerificationId > 0 ? $aadhaarVerificationId : null,
            'place_order' => true,
        ];

        if (in_array($flow, ['renew', 'renewal'], true)) {
            $parentOrderId = (int) ($cart->order_id ?? 0);
            if ($parentOrderId > 0) {
                $payload['order_id'] = $parentOrderId;
                $payload['subscription_id'] = $parentOrderId;
            }
            if ((int) ($cart->customer_id ?? 0) > 0) {
                $payload['customer_id'] = (int) $cart->customer_id;
            }
            $createRequest = Request::create('/api/generate-renew-order', 'POST', $payload);
            $createRequest->headers->set('Accept', 'application/json');
            $createResponse = $this->generateRenewOrder($createRequest);
        } elseif (in_array($flow, ['upgrade', 'downgrade'], true)) {
            if ((int) ($cart->customer_id ?? 0) > 0) {
                $payload['customer_id'] = (int) $cart->customer_id;
            }
            $createRequest = Request::create('/api/generate-upgrade-order', 'POST', $payload);
            $createRequest->headers->set('Accept', 'application/json');
            $createResponse = $this->generateUpgradeOrder($createRequest);
        } else {
            $createRequest = Request::create('/api/generate-new-order', 'POST', $payload);
            $createRequest->headers->set('Accept', 'application/json');
            $createResponse = $this->generateNewOrder($createRequest);
        }
        $createBody = $createResponse->getData(true);
        if (empty($createBody['success'])) {
            return response()->json($createBody, $createResponse->getStatusCode() ?: 422);
        }

        $data = is_array($createBody['data'] ?? null) ? $createBody['data'] : [];
        $newOrderId = (int) ($data['order_id'] ?? 0);

        // Stamp Aadhaar verified on PO + refresh final PO PDF when order was created.
        if ($newOrderId > 0) {
            $this->orderPoDetails->newQuery()->where('order_id', $newOrderId)->update([
                'aadhaar_masked' => 'XXXXXXXX' . substr($aadhaarNumber, -4),
                'aadhar_verified' => 1,
                'aadhar_verified_at' => now(),
                'aadhaar_ref_no' => (string) ($otpResult['ref_no'] ?? ''),
            ]);

            if ($aadhaarVerificationId > 0) {
                $this->orders->newQuery()->whereKey($newOrderId)->update([
                    'aadhaar_verification_id' => $aadhaarVerificationId,
                ]);
                $aadhaarService->linkOrdersAfterCheckout(
                    $aadhaarVerificationId,
                    $cartId,
                    $newOrderId,
                    [$newOrderId]
                );
            }

            $cartPoMeta = $cartService->getCartPoMeta($cartId);
            if (($cartPoMeta['po_mode'] ?? '') === 'uploaded' && ($cartPoMeta['po_link'] ?? '') !== '') {
                $stored = app(\App\Services\OrderPoDocumentService::class)->applyStoredUploadedPoToOrder(
                    $newOrderId,
                    (string) $cartPoMeta['po_link'],
                    (string) ($cartPoMeta['po_number'] ?? ''),
                    true
                );
            } else {
                $stored = $this->createOrderPo($newOrderId);
            }

            if (!empty($stored['public_url'])) {
                $finalUrl = (string) $stored['public_url'];
                $this->orderPoDetails->newQuery()->where('order_id', $newOrderId)->update([
                    'po_document_url' => $finalUrl,
                    'final_po_link' => $finalUrl,
                    'aadhar_verified' => 1,
                    'journey_status' => 'placed',
                ]);
                $this->orders->newQuery()->whereKey($newOrderId)->update([
                    'po_document_url' => $finalUrl,
                ]);
                $data['po_link'] = $finalUrl;
                $data['final_po_link'] = $finalUrl;
                $data['po_number'] = (string) ($stored['po_number'] ?? ($data['po_number'] ?? ''));
                $data['po_mode'] = (string) ($stored['po_mode'] ?? ($cartPoMeta['po_mode'] ?? 'generated'));
            }
        }

        app(PartnerCartService::class)->clearCartPendingAadhaar($cartId);

        $data['aadhaar_pending'] = false;
        $data['draft_cart'] = false;
        $data['cart_id'] = $cartId;
        $data['aadhaar_verification_id'] = $aadhaarVerificationId > 0 ? $aadhaarVerificationId : null;
        $data['aadhaar_masked'] = 'XXXXXXXX' . substr($aadhaarNumber, -4);

        return response()->json([
            'success' => true,
            'message' => (string) ($otpResult['message'] ?? 'Aadhaar verified successfully. Order generated.'),
            'data' => $data,
        ], $newOrderId > 0 ? 201 : 200);
    }

    public function placeOrderWithoutAadhaar(Request $request): JsonResponse
    {
        // Normalize FE quirks: order_id may be cart_id for drafts (is_temp_order).
        $rawOrderId = $request->input('order_id', $request->input('ordId', data_get($request->all(), 'data.order_id')));
        $maybeCartFromOrderId = (int) ($rawOrderId ?? 0);

        foreach (['partner_id', 'cart_id', 'main_cart_id', 'pending_ref_id'] as $key) {
            $nested = data_get($request->all(), 'data.' . $key);
            if (!$request->filled($key) && $nested !== null && $nested !== '') {
                $request->merge([$key => $nested]);
            }
        }
        if (!$request->filled('cart_id') && $request->filled('main_cart_id')) {
            $request->merge(['cart_id' => $request->input('main_cart_id')]);
        }
        if (!$request->filled('main_cart_id') && $request->filled('cart_id')) {
            $request->merge(['main_cart_id' => $request->input('cart_id')]);
        }

        // Draft APIs return ordId = cart_id. If FE posts that back as order_id, treat as cart.
        if (
            $maybeCartFromOrderId > 0
            && !$request->filled('cart_id')
            && !$request->filled('main_cart_id')
            && $this->carts->newQuery()->whereKey($maybeCartFromOrderId)->exists()
        ) {
            $request->merge([
                'cart_id' => $maybeCartFromOrderId,
                'main_cart_id' => $maybeCartFromOrderId,
            ]);
        }

        $partnerId = $this->resolvePartnerIdFromRequest($request) ?? 0;
        $cartId = (int) ($request->input('main_cart_id') ?? $request->input('cart_id') ?? 0);

        // Resolve partner from cart / pending ref before requiring partner_id.
        if ($partnerId <= 0 && $cartId > 0) {
            $partnerId = (int) ($this->carts->newQuery()->whereKey($cartId)->value('partner_id') ?? 0);
        }
        if ($partnerId <= 0) {
            $ref = trim((string) (
                $request->input('pending_ref_id')
                ?? $request->input('temp_order_no')
                ?? $request->input('order_no')
                ?? ''
            ));
            // Ignore literal "0" order_no from FE.
            if ($ref === '0') {
                $ref = '';
            }
            if ($ref !== '' && app(PartnerCartService::class)->isPendingEsignRef($ref)) {
                $resolved = app(PartnerCartService::class)->resolvePendingEsignRef($ref);
                if ($resolved !== null) {
                    $cartId = $cartId > 0 ? $cartId : (int) ($resolved['cart_id'] ?? 0);
                    $partnerId = (int) ($resolved['partner_id'] ?? 0);
                }
            }
        }

        // order_id 0 = place pending cart for this partner (from token).
        if ($partnerId > 0 && $cartId <= 0) {
            $cartId = $this->resolvePendingCartIdForPartner($partnerId);
        }

        if ($partnerId > 0 && !$request->filled('partner_id')) {
            $request->merge(['partner_id' => $partnerId]);
        }
        if ($cartId > 0) {
            $request->merge([
                'main_cart_id' => $cartId,
                'cart_id' => $cartId,
            ]);
        }

        $validated = $request->validate([
            'partner_id' => ['nullable', 'integer', 'min:1'],
            'main_cart_id' => ['nullable', 'integer', 'min:1'],
            'cart_id' => ['nullable', 'integer', 'min:1'],
            'pending_ref_id' => ['nullable', 'string', 'max:64'],
            'temp_order_no' => ['nullable', 'string', 'max:64'],
            'order_no' => ['nullable', 'string', 'max:64'],
            'po_mode' => ['nullable', 'in:generated,uploaded'],
        ]);

        $partnerId = (int) ($validated['partner_id'] ?? $partnerId);
        $cartId = (int) ($validated['main_cart_id'] ?? $validated['cart_id'] ?? $cartId);

        if ($cartId <= 0) {
            $ref = trim((string) ($validated['pending_ref_id'] ?? $validated['temp_order_no'] ?? $validated['order_no'] ?? ''));
            if ($ref === '0') {
                $ref = '';
            }
            if ($ref !== '' && app(PartnerCartService::class)->isPendingEsignRef($ref)) {
                $resolved = app(PartnerCartService::class)->resolvePendingEsignRef($ref);
                if ($resolved !== null) {
                    $cartId = (int) ($resolved['cart_id'] ?? 0);
                    if ($partnerId <= 0 && (int) ($resolved['partner_id'] ?? 0) > 0) {
                        $partnerId = (int) $resolved['partner_id'];
                    }
                }
            }
        }

        if ($partnerId <= 0 && $cartId > 0) {
            $partnerId = (int) ($this->carts->newQuery()->whereKey($cartId)->value('partner_id') ?? 0);
        }

        if ($partnerId <= 0 && $cartId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'partner_id and cart_id are required to place the order.',
                'errors' => [
                    'partner_id' => ['Send partner_id (or Authorization: Bearer <token>). order_id:0 alone is not enough.'],
                    'cart_id' => ['Send cart_id / main_cart_id from generate-order or getCartDetails.'],
                ],
                'data' => [
                    'hint' => 'Example: {"partner_id":12,"main_cart_id":456,"place_order":true}',
                    'received' => [
                        'order_id' => $request->input('order_id'),
                        'has_bearer' => (bool) $request->bearerToken(),
                    ],
                ],
            ], 422);
        }

        if ($partnerId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'The partner id field is required.',
                'errors' => ['partner_id' => ['Send partner_id or Authorization Bearer token.']],
                'data' => null,
            ], 422);
        }

        if ($cartId <= 0) {
            $cartId = $this->resolvePendingCartIdForPartner($partnerId);
        }

        if ($cartId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cart not found. Pass cart_id / main_cart_id from the draft PO step.',
                'data' => [
                    'partner_id' => $partnerId,
                    'hint' => 'Call getCartDetails or use cart_id returned by generate-new-order.',
                ],
            ], 422);
        }

        $cart = $this->carts->newQuery()->whereKey($cartId)->where('partner_id', $partnerId)->first();
        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Cart not found.',
                'data' => null,
            ], 404);
        }

        // Parent subscription on renew/upgrade carts — restore on failed place.
        $restoreCartParentOrderId = 0;
        $cartLinkedOrderId = (int) ($cart->order_id ?? 0);
        if ($cartLinkedOrderId > 0 && $this->orders->newQuery()->whereKey($cartLinkedOrderId)->exists()) {
            $restoreCartParentOrderId = $cartLinkedOrderId;
        }

        $cartService = app(PartnerCartService::class);
        $requestDomain = OrderDomainHelper::normalizeStored($request->input('domain_name'));
        if ($requestDomain === '') {
            $requestDomain = $cartService->cartDomainNameString($cart);
        }
        if ($requestDomain === '') {
            $requestDomain = $this->resolveDomainNameForOrder($cart, $cartId);
        }
        // Ensure domain name is present; if missing, halt order processing.
        if ($requestDomain === '' && empty($cart->domain_name)) {
            return response()->json([
                'success' => false,
                'message' => 'Domain is required for order placement.',
                'errors' => ['domain_name' => ['Domain name cannot be empty.']],
                'data' => null,
            ], 422);
        }
        if ($requestDomain !== '') {
            $cart->domain_name = $requestDomain;
            $cart->save();
        }

        // Upgrade/downgrade plans live on cart_items only (carts has no plan_id column).
        $cartTypeLower = strtolower(trim((string) ($cart->type ?? '')));
        if (in_array($cartTypeLower, ['upgrade', 'downgrade'], true)) {
            $planIdFromItems = (int) (CartItem::query()
                ->where('cart_id', $cartId)
                ->whereNotNull('plan_id')
                ->orderBy('id')
                ->value('plan_id') ?? 0);
            if ($planIdFromItems <= 0) {
                $label = $cartTypeLower === 'downgrade' ? 'downgrade' : 'upgrade';

                return response()->json([
                    'success' => false,
                    'message' => "No {$label} plan found in cart.",
                    'errors' => ['plan_id' => ["No {$label} plan found in cart."]],
                    'data' => [
                        'cart_id' => $cartId,
                        'hint' => 'Add an upgrade/downgrade plan to cart before place-order-without-aadhaar.',
                    ],
                ], 422);
            }
        }

        $flow = $cartService->getCartPendingAadhaarFlow($cartId);
        $cartType = strtolower(trim((string) ($cart->type ?? 'standard')));
        if (in_array($cartType, ['renew', 'renewal'], true)) {
            $flow = 'renew';
        } elseif ($cartType === 'upgrade') {
            $flow = 'upgrade';
        } elseif ($cartType === 'downgrade') {
            $flow = 'downgrade';
        } elseif (!in_array($flow, ['renew', 'renewal', 'upgrade', 'downgrade'], true)) {
            $flow = 'new';
        }

        $merge = [
            'partner_id' => $partnerId,
            'main_cart_id' => $cartId,
            'cart_id' => $cartId,
            'place_order' => 1,
            'finalize' => 1,
            'confirm_place' => 1,
        ];
        $poMode = $validated['po_mode'] ?? $request->input('po_mode');
        if ($poMode !== null && $poMode !== '') {
            $merge['po_mode'] = $poMode;
        }
        if ($request->filled('po_number')) {
            $merge['po_number'] = $request->input('po_number');
        }
        if ($request->filled('po_date')) {
            $merge['po_date'] = $request->input('po_date');
        }
        if ($requestDomain !== '') {
            $merge['domain_name'] = $requestDomain;
        }

        if (in_array($flow, ['renew', 'renewal'], true)) {
            $parentOrderId = (int) ($cart->order_id ?? 0);
            if ($parentOrderId > 0) {
                $merge['order_id'] = $parentOrderId;
                $merge['subscription_id'] = $parentOrderId;
            }
            if ((int) ($cart->customer_id ?? 0) > 0) {
                $merge['customer_id'] = (int) $cart->customer_id;
            }
            $request->merge($merge);
            $createResponse = $this->generateRenewOrder($request);
        } elseif (in_array($flow, ['upgrade', 'downgrade'], true)) {
            if ((int) ($cart->customer_id ?? 0) > 0) {
                $merge['customer_id'] = (int) $cart->customer_id;
            }
            $orderSubId = 0;
            if (Schema::connection('pgsql_billing')->hasColumn('carts', 'order_sub_id')) {
                $orderSubId = (int) ($cart->order_sub_id ?? 0);
            }
            if ($orderSubId <= 0) {
                $itemType = trim((string) ($cart->item_type ?? ''));
                if (preg_match('/^order_sub:(\d+)/', $itemType, $m)) {
                    $orderSubId = (int) $m[1];
                }
            }
            if ($orderSubId > 0) {
                $merge['order_sub_id'] = $orderSubId;
            }
            // Draft APIs return order_id = cart id. Never pass that as subscription/parent order id.
            $parentOrderId = (int) ($cart->order_id ?? 0);
            $incomingOrderId = (int) ($request->input('order_id') ?? $request->input('ordId') ?? 0);
            if ($parentOrderId > 0) {
                $merge['order_id'] = $parentOrderId;
                $merge['subscription_id'] = $parentOrderId;
            } elseif (
                $incomingOrderId === $cartId
                || ($incomingOrderId > 0 && !$this->orders->newQuery()->whereKey($incomingOrderId)->exists())
            ) {
                $request->request->remove('order_id');
                $request->request->remove('ordId');
                $request->request->remove('subscription_id');
            }
            $merge['order_category'] = $flow;
            $request->merge($merge);
            $createResponse = $this->generateUpgradeOrder($request);
        } else {
            $request->merge($merge);
            $createResponse = $this->generateNewOrder($request);
        }

        $createBody = $createResponse->getData(true);
        $data = is_array($createBody['data'] ?? null) ? $createBody['data'] : [];
        $stillDraft = !empty($data['is_temp_order']) || !empty($data['place_order_required']);
        $newOrderId = (int) ($data['order_id'] ?? $data['ordId'] ?? 0);
        if ($stillDraft || ($newOrderId > 0 && $newOrderId === $cartId && !$this->orders->newQuery()->whereKey($newOrderId)->exists())) {
            $newOrderId = 0;
        }

        $placeFailed = empty($createBody['success'])
            || $newOrderId <= 0
            || !empty($createBody['errors'])
            || !empty($data['po_error']);
        if ($placeFailed) {
            $orderHint = $newOrderId > 0
                ? $newOrderId
                : (int) ($data['order_id'] ?? $data['ordId'] ?? 0);
            $this->rollbackFailedPlaceOrderAttempt(
                $partnerId,
                $cartId,
                $orderHint > 0 ? $orderHint : null,
                $restoreCartParentOrderId > 0 ? $restoreCartParentOrderId : null
            );

            return response()->json([
                'success' => false,
                'message' => (string) ($createBody['message'] ?? 'Order could not be placed. Pass partner_id and cart_id with place_order=1.'),
                'errors' => is_array($createBody['errors'] ?? null) ? $createBody['errors'] : null,
                'data' => array_merge($data, [
                    'cart_id' => $cartId,
                    'main_cart_id' => $cartId,
                    'partner_id' => $partnerId,
                    'order_id' => $cartId,
                    'ordId' => $cartId,
                    'order_rolled_back' => true,
                    'order_removed' => true,
                    'is_temp_order' => true,
                    'real_order' => false,
                ]),
            ], $createResponse->getStatusCode() ?: 422);
        }

        if ($newOrderId > 0) {
            $this->orderPoDetails->newQuery()->where('order_id', $newOrderId)->update([
                'journey_status' => 'placed',
                'sign_required' => 'no',
            ]);

            $cartPoMeta = $cartService->getCartPoMeta($cartId);
            if (($cartPoMeta['po_mode'] ?? '') === 'uploaded' && ($cartPoMeta['po_link'] ?? '') !== '') {
                $stored = app(\App\Services\OrderPoDocumentService::class)->applyStoredUploadedPoToOrder(
                    $newOrderId,
                    (string) $cartPoMeta['po_link'],
                    (string) ($cartPoMeta['po_number'] ?? ''),
                    true
                );
                if (!empty($stored['ok'])) {
                    $data['po_link'] = $stored['public_url'] ?? ($data['po_link'] ?? null);
                    $data['po_number'] = (string) ($stored['po_number'] ?? ($data['po_number'] ?? ''));
                }
            }
        }

        $cartService->clearCartPendingAadhaar($cartId);

        $orderRow = $newOrderId > 0 ? $this->orders->newQuery()->whereKey($newOrderId)->first() : null;
        if ($orderRow) {
            $this->convertCartAfterAadhaarVerified($orderRow);
        }
        $subscriptionId = $newOrderId > 0
            ? (string) (OrderItem::query()->where('order_id', $newOrderId)->orderBy('id')->value('subscription_id') ?? '')
            : '';

        // Link cart → order so order-details?ordId={cartId} still works after place.
        if ($cartId > 0 && $newOrderId > 0) {
            $this->rememberDraftCartLink($newOrderId, $cartId);
            if (Schema::connection('pgsql_billing')->hasColumn('carts', 'order_id')) {
                $this->carts->newQuery()->whereKey($cartId)->update(['order_id' => $newOrderId]);
            }
        }

        $data['aadhaar_pending'] = false;
        $data['place_order_required'] = false;
        $data['draft_cart'] = false;
        $data['cart_id'] = $cartId;
        $data['ordId'] = $newOrderId > 0 ? $newOrderId : (int) ($data['ordId'] ?? 0);
        $data['order_id'] = $newOrderId > 0 ? $newOrderId : (int) ($data['order_id'] ?? 0);
        $data['order_no'] = (string) (optional($orderRow)->order_no ?? $data['order_no'] ?? $newOrderId);
        $data['orderNo'] = $data['order_no'];
        $subscriptionId = $this->normalizeSubscriptionIdDisplay(
            $subscriptionId !== '' ? $subscriptionId : (string) ($data['subscription_id'] ?? '')
        );
        if ($subscriptionId === '' && $newOrderId > 0) {
            $firstItem = OrderItem::query()->where('order_id', $newOrderId)->orderBy('id')->first();
            if ($firstItem) {
                $subscriptionId = $this->ensureOrderItemSubscriptionId($firstItem);
            }
        }

        $data['subscription_id'] = $subscriptionId;
        $data['subscription_no'] = $subscriptionId;
        $data['subscriptionId'] = $subscriptionId;
        $billingPayload = $this->resolveOrderBillingCyclePayload(
            $orderRow,
            ($newOrderId > 0 && $orderRow)
                ? $this->plans->newQuery()->whereKey((int) ($orderRow->plan_id ?? 0))->first()
                : null
        );
        $billingCycle = (string) ($billingPayload['billing_cycle'] ?? '12 Months');
        $data['billing_cycle'] = $billingCycle;
        $data['billingCycle'] = $billingCycle;
        $data['duration'] = $billingCycle;
        $data['subscription_start_date'] = $billingPayload['subscription_start_date'] ?? null;
        $data['subscription_end_date'] = $billingPayload['subscription_end_date'] ?? null;
        $data['start_date'] = $billingPayload['start_date'] ?? null;
        $data['end_date'] = $billingPayload['end_date'] ?? null;

        $uiDates = $this->formatOrderDateFields(optional($orderRow)->created_at ?? now());
        $data['order_date'] = $uiDates['order_date'];
        $data['order_date_iso'] = $uiDates['order_date_iso'];
        $data['orderDate'] = $uiDates['orderDate'];
        $data['created_at'] = $uiDates['created_at'];

        $domainName = $this->resolveDomainNameForOrder($orderRow, $cartId);
        if ($newOrderId > 0 && $domainName !== '') {
            OrderDomainHelper::persistOnOrderIfMissing($newOrderId, $domainName);
        }
        $data['domain_name'] = $domainName;
        $data['domain'] = $domainName;
        $data['enrollment_type'] = $this->resolveEnrollmentType(
            $orderRow,
            (string) ($data['order_category'] ?? $data['order_type'] ?? $flow ?? '')
        );

        $poNumberClean = $this->cleanPoNumberDisplay((string) ($data['po_number'] ?? ''));
        $data['po_number'] = $poNumberClean;
        $data['po_document_name'] = $poNumberClean;
        $data['document_name'] = $poNumberClean;
        $data['document_label'] = $poNumberClean;
        $data['po_label'] = $poNumberClean;
        $data['po_date'] = $uiDates['order_date'];
        if ($poNumberClean !== '' || !empty($data['po_link'])) {
            $data['documents'] = [[
                'type' => 'purchase_order',
                'title' => 'Purchase Order',
                'name' => $poNumberClean,
                'label' => $poNumberClean,
                'po_number' => $poNumberClean,
                'link' => $data['po_link'] ?? null,
                'url' => $data['po_link'] ?? null,
            ]];
        }

        // Keep plans/orders subscription_id + domain in sync with root (order-details shape).
        // Upgrade/renew place responses may omit plans — build from order items when missing.
        if ((!is_array($data['plans'] ?? null) || $data['plans'] === []) && $newOrderId > 0) {
            $data['plans'] = $this->buildPlansOutFromOrderId($newOrderId, $domainName, $billingCycle, $subscriptionId);
            if ((!is_array($data['orders'] ?? null) || $data['orders'] === []) && $data['plans'] !== []) {
                $data['orders'] = $data['plans'];
            }
        }
        if (is_array($data['plans'] ?? null)) {
            foreach ($data['plans'] as $i => $planRow) {
                if (!is_array($planRow)) {
                    continue;
                }
                if (trim((string) ($planRow['subscription_id'] ?? '')) === '' && $subscriptionId !== '') {
                    $data['plans'][$i]['subscription_id'] = $subscriptionId;
                    $data['plans'][$i]['subscription_no'] = $subscriptionId;
                }
                if (empty($planRow['billing_cycle'])) {
                    $data['plans'][$i]['billing_cycle'] = $billingCycle;
                    $data['plans'][$i]['duration'] = $billingCycle;
                }
                $data['plans'][$i]['domain_name'] = $domainName;
                $data['plans'][$i]['domain'] = $domainName;

                $planid = $planRow['plan_id'] ?? null;
                if ($planid) {
                    $program_id = $this->plans->newQuery()->whereKey($planid)->value('provider_id');
                    $data['plans'][$i]['provider_id'] = $program_id ?: null;
                }
            }
        }

        if (is_array($data['orders'] ?? null)) {
            foreach ($data['orders'] as $i => $orderLine) {
                if (!is_array($orderLine)) {
                    continue;
                }

                $planid = $orderLine['plan_id'] ?? null;
                if ($planid) {
                    $program_id = $this->plans->newQuery()->whereKey($planid)->value('provider_id');
                    $data['orders'][$i]['provider_id'] = $program_id ?: null;
                }

                if (trim((string) ($orderLine['subscription_id'] ?? '')) === '' && $subscriptionId !== '') {
                    $data['orders'][$i]['subscription_id'] = $subscriptionId;
                    $data['orders'][$i]['subscription_no'] = $subscriptionId;
                }
                $data['orders'][$i]['order_date'] = $uiDates['order_date'];
                $data['orders'][$i]['domain_name'] = $domainName;
                $data['orders'][$i]['domain'] = $domainName;
                if (empty($orderLine['billing_cycle'])) {
                    $data['orders'][$i]['billing_cycle'] = $billingCycle;
                }
            }
        }

        $data['sign_required'] = 'no';
        $data['is_temp_order'] = false;
        $data['real_order'] = $newOrderId > 0;
        $data['status'] = (string) (optional($orderRow)->status ?? $data['status'] ?? self::STATUS_PROCESSING);

        // FE often keeps cart id in the URL — also echo it for redirect helpers.
        $data['redirect_ordId'] = $newOrderId > 0 ? $newOrderId : $cartId;

        // Safety net: ensure "Order Placed / In Process" mail goes out after place.
        if ($newOrderId > 0) {
            $placedStatus = strtolower(trim((string) (optional($orderRow)->status ?? '')));
            if ($placedStatus === '' || $placedStatus === self::STATUS_PROCESSING || $placedStatus === self::STATUS_PENDING) {
                try {
                    $this->sendProcessingOrderNotifications($newOrderId);
                } catch (\Throwable $e) {
                    Log::warning('place_order_processing_email_retry_failed', [
                        'order_id' => $newOrderId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => (string) ($createBody['message'] ?? 'Order placed successfully.'),
            'data' => $data,
        ], $newOrderId > 0 ? 201 : ($createResponse->getStatusCode() ?: 200));
    }

    /**
     * Expand draft order(s) into per-plan rows for proceed / PO details UI.
     *
     * @param  list<int>  $draftIds
     * @return list<array<string, mixed>>
     */
    protected function buildOrdersOutFromDraftIds(array $draftIds, string $domainName = ''): array
    {
        $out = [];
        foreach ($draftIds as $draftId) {
            $draftId = (int) $draftId;
            if ($draftId <= 0) {
                continue;
            }
            $draftOrder = $this->orders->newQuery()->whereKey($draftId)->first();
            $draftPo = $this->orderPoDetails->newQuery()->where('order_id', $draftId)->first();
            if (!$draftOrder) {
                continue;
            }

            $draftCompanyName = '';
            $draftCustomerId = (int) ($draftOrder->user_id ?? 0);
            if ($draftCustomerId > 0) {
                $draftCompanyName = trim((string) ($this->customers->newQuery()->whereKey($draftCustomerId)->value('company_name') ?? ''));
            }

            $items = $this->orderItems->newQuery()
                ->where('order_id', $draftId)
                ->orderBy('id')
                ->get();

            if ($items->isEmpty()) {
                $planId = (int) ($draftOrder->plan_id ?? 0);
                $planName = (string) ($draftOrder->plan_name ?? '');
                if ($planName === '' && $planId > 0) {
                    $planName = (string) ($this->plans->newQuery()->whereKey($planId)->value('name') ?? '');
                }
                $out[] = [
                    'order_id' => $draftId,
                    'order_no' => (string) ($draftOrder->order_no ?? ''),
                    'plan_id' => $planId,
                    'plan_name' => $planName,
                    'licenses' => max(1, (int) ($draftOrder->quantity ?? 1)),
                    'status' => self::STATUS_DRAFT,
                    'domain_name' => (string) ($draftOrder->domain_name ?? $domainName),
                    'transfer_domain' => $draftOrder->transfer_domain ?? null,
                    'company_name' => $draftCompanyName,
                    'po' => [
                        'po_number' => (string) ($draftPo?->po_number ?? ''),
                        'journey_status' => (string) ($draftPo?->journey_status ?? 'po_pending'),
                        'po_link' => $draftPo?->po_document_url ?? null,
                    ],
                ];
                continue;
            }

            foreach ($items as $item) {
                $planId = (int) ($item->plan_id ?? 0);
                $planName = trim((string) ($item->item_name ?? ''));
                if ($planName === '' && $planId > 0) {
                    $planName = (string) ($this->plans->newQuery()->whereKey($planId)->value('name') ?? '');
                }
                $out[] = [
                    'order_id' => $draftId,
                    'order_no' => (string) ($draftOrder->order_no ?? ''),
                    'plan_id' => $planId,
                    'plan_name' => $planName !== '' ? $planName : 'Plan',
                    'licenses' => max(1, (int) ($item->quantity ?? 1)),
                    'status' => self::STATUS_DRAFT,
                    'domain_name' => (string) ($draftOrder->domain_name ?? $domainName),
                    'transfer_domain' => $draftOrder->transfer_domain ?? null,
                    'company_name' => $draftCompanyName,
                    'unit_price' => round((float) ($item->unit_price ?? 0), 2),
                    'line_total' => round((float) ($item->total_amount ?? 0), 2),
                    'po' => [
                        'po_number' => (string) ($draftPo?->po_number ?? ''),
                        'journey_status' => (string) ($draftPo?->journey_status ?? 'po_pending'),
                        'po_link' => $draftPo?->po_document_url ?? null,
                    ],
                ];
            }
        }

        return $out;
    }

    protected function rememberDraftCartLink(int $orderId, int $cartId, array $planIds = []): void
    {
        if ($orderId <= 0 || $cartId <= 0) {
            return;
        }
        Cache::put('cart_draft_order_' . $cartId, $orderId, now()->addDays(7));
        Cache::put('draft_order_cart_' . $orderId, $cartId, now()->addDays(7));

        $list = Cache::get('cart_draft_orders_' . $cartId, []);
        if (!is_array($list)) {
            $list = [];
        }
        $list[] = $orderId;
        $list = array_values(array_unique(array_filter(array_map('intval', $list))));
        Cache::put('cart_draft_orders_' . $cartId, $list, now()->addDays(7));

        $planIds = array_values(array_unique(array_filter(array_map('intval', $planIds))));
        if ($planIds === []) {
            $planIds = OrderItem::query()
                ->where('order_id', $orderId)
                ->pluck('plan_id')
                ->map(fn($id) => (int) $id)
                ->filter(fn($id) => $id > 0)
                ->unique()
                ->values()
                ->all();
        }
        if ($planIds !== []) {
            Cache::put('draft_order_plan_ids_' . $orderId, $planIds, now()->addDays(7));
        }

        if (Schema::connection('pgsql_billing')->hasColumn('carts', 'order_id')) {
            $this->carts->newQuery()->whereKey($cartId)->update(['order_id' => $orderId]);
        }
        if (Schema::connection('pgsql_billing')->hasColumn('orders', 'cart_id')) {
            $this->orders->newQuery()->whereKey($orderId)->update(['cart_id' => $cartId]);
        }
    }

    protected function forgetDraftCartLink(int $cartId, int $orderId = 0): void
    {
        if ($orderId > 0) {
            Cache::forget('draft_order_cart_' . $orderId);
            Cache::forget('draft_order_plan_ids_' . $orderId);
        }

        if ($cartId <= 0) {
            return;
        }

        $list = Cache::get('cart_draft_orders_' . $cartId, []);
        if (!is_array($list)) {
            $list = [];
        }
        $primary = (int) Cache::get('cart_draft_order_' . $cartId, 0);
        if ($primary > 0) {
            $list[] = $primary;
        }
        $list = array_values(array_unique(array_filter(array_map('intval', $list))));

        if ($orderId > 0) {
            $list = array_values(array_filter($list, static fn($id) => (int) $id !== (int) $orderId));
        } else {
            foreach ($list as $oid) {
                Cache::forget('draft_order_cart_' . $oid);
                Cache::forget('draft_order_plan_ids_' . $oid);
            }
            $list = [];
        }

        if ($list === []) {
            Cache::forget('cart_draft_orders_' . $cartId);
            Cache::forget('cart_draft_order_' . $cartId);
            if (
                Schema::connection('pgsql_billing')->hasColumn('carts', 'order_id')
                && !$this->cartKeepsParentOrderLink($cartId)
            ) {
                $this->carts->newQuery()->whereKey($cartId)->update(['order_id' => null]);
            }
        } else {
            Cache::put('cart_draft_orders_' . $cartId, $list, now()->addDays(7));
            Cache::put('cart_draft_order_' . $cartId, (int) $list[0], now()->addDays(7));
            if (
                Schema::connection('pgsql_billing')->hasColumn('carts', 'order_id')
                && !$this->cartKeepsParentOrderLink($cartId)
            ) {
                $this->carts->newQuery()->whereKey($cartId)->update(['order_id' => (int) $list[0]]);
            }
        }
    }

    /**
     * Renew / upgrade / downgrade carts store the parent subscription on carts.order_id.
     */
    protected function cartKeepsParentOrderLink(int $cartId): bool
    {
        if ($cartId <= 0) {
            return false;
        }

        $type = strtolower(trim((string) (
            $this->carts->newQuery()->whereKey($cartId)->value('type') ?? ''
        )));

        return in_array($type, [
            Cart::TYPE_RENEW,
            Cart::TYPE_UPGRADE,
            Cart::TYPE_DOWNGRADE,
            'license_add',
        ], true);
    }

    protected function convertCartAfterAadhaarVerified($order): void
    {
        if (!$order) {
            return;
        }

        $orderId = (int) ($order->id ?? 0);
        $partnerId = (int) ($order->partner_id ?? 0);
        $customerId = (int) ($order->user_id ?? 0);
        $cartId = (int) Cache::get('draft_order_cart_' . $orderId, 0);

        if ($cartId <= 0 && Schema::connection('pgsql_billing')->hasColumn('orders', 'cart_id')) {
            $cartId = (int) ($order->cart_id ?? 0);
        }

        // Do NOT resolve via carts.order_id for renew/upgrade — that column is the parent subscription.
        if ($cartId <= 0 && Schema::connection('pgsql_billing')->hasColumn('carts', 'order_id')) {
            $candidateId = (int) ($this->carts->newQuery()
                ->where('partner_id', $partnerId)
                ->where('order_id', $orderId)
                ->orderByDesc('id')
                ->value('id') ?? 0);
            if ($candidateId > 0 && !$this->cartKeepsParentOrderLink($candidateId)) {
                $cartId = $candidateId;
            }
        }

        if ($cartId <= 0 && $partnerId > 0 && $customerId > 0) {
            $purchasedPlanIds = OrderItem::query()
                ->where('order_id', $orderId)
                ->pluck('plan_id')
                ->map(fn($id) => (int) $id)
                ->filter(fn($id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            $candidateCarts = $this->carts->newQuery()
                ->where('partner_id', $partnerId)
                ->where('customer_id', $customerId)
                ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = 'active'")
                ->whereNotIn('type', [Cart::TYPE_RENEW, Cart::TYPE_UPGRADE, Cart::TYPE_DOWNGRADE])
                ->orderByDesc('id')
                ->limit(10)
                ->get(['id']);

            foreach ($candidateCarts as $candidate) {
                $cid = (int) $candidate->id;
                // Never wipe a place-pending cart.
                if (app(PartnerCartService::class)->isCartPendingAadhaar($cid)) {
                    continue;
                }
                if ($purchasedPlanIds === []) {
                    $cartId = $cid;
                    break;
                }
                $cartPlans = CartItem::query()
                    ->where('cart_id', $cid)
                    ->pluck('plan_id')
                    ->map(fn($id) => (int) $id)
                    ->all();
                if (array_intersect($purchasedPlanIds, $cartPlans) !== []) {
                    $cartId = $cid;
                    break;
                }
            }
        }

        if ($cartId <= 0) {
            return;
        }

        // Place-pending renew/upgrade carts stay until Place Order.
        if (
            app(PartnerCartService::class)->isCartPendingAadhaar($cartId)
            || $this->cartKeepsParentOrderLink($cartId)
        ) {
            return;
        }

        $purchasedPlanIds = OrderItem::query()
            ->where('order_id', $orderId)
            ->pluck('plan_id')
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        // Drop purchased plans from cart so getCartDetails becomes empty for this order.
        if ($purchasedPlanIds !== []) {
            CartItem::query()
                ->where('cart_id', $cartId)
                ->whereIn('plan_id', $purchasedPlanIds)
                ->delete();
        }

        // Always drop this purchased order from draft links.
        $this->forgetDraftCartLink($cartId, $orderId);
        Cache::forget('draft_order_plan_ids_' . $orderId);

        $linked = [];
        $list = Cache::get('cart_draft_orders_' . $cartId, []);
        if (is_array($list)) {
            $linked = array_merge($linked, array_map('intval', $list));
        }
        $primary = (int) Cache::get('cart_draft_order_' . $cartId, 0);
        if ($primary > 0) {
            $linked[] = $primary;
        }
        $linked = array_values(array_unique(array_filter($linked)));

        $cartPlanIds = CartItem::query()
            ->where('cart_id', $cartId)
            ->pluck('plan_id')
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $survivors = [];
        foreach ($linked as $keepId) {
            $keepId = (int) $keepId;
            if ($keepId <= 0 || $keepId === $orderId) {
                continue;
            }
            if (!$this->isDraftOrderStatus($keepId)) {
                $this->forgetDraftCartLink($cartId, $keepId);
                continue;
            }
            $draftPlans = $this->planIdsForDraftOrders([$keepId]);
            if ($cartPlanIds === [] || array_intersect($draftPlans, $cartPlanIds) === []) {
                $this->orders->newQuery()->whereKey($keepId)->update(['status' => self::STATUS_CANCELLED]);
                $this->forgetDraftCartLink($cartId, $keepId);
                continue;
            }
            $survivors[] = $keepId;
        }

        // No remaining draft plans / items → convert cart (empty for getCartDetails).
        if ($survivors === [] || $cartPlanIds === []) {
            CartItem::query()->where('cart_id', $cartId)->delete();
            $this->carts->newQuery()->whereKey($cartId)->update(['status' => 'converted']);
            $this->forgetDraftCartLink($cartId, 0);
            if (Schema::connection('pgsql_billing')->hasColumn('carts', 'order_id')) {
                $this->carts->newQuery()->whereKey($cartId)->update(['order_id' => null]);
            }
        } else {
            $this->carts->newQuery()->whereKey($cartId)->update(['status' => 'active']);
            foreach ($survivors as $keepId) {
                $this->rememberDraftCartLink((int) $keepId, $cartId);
            }
        }
    }

    /**
     * @return list<int>
     */
    protected function activeDraftOrderIdsForCart(
        int $partnerId,
        int $cartId,
        int $customerId = 0,
        string $domainName = ''
    ): array {
        $ids = [];

        if ($cartId > 0) {
            $cachedList = Cache::get('cart_draft_orders_' . $cartId, []);
            if (is_array($cachedList)) {
                foreach ($cachedList as $id) {
                    $ids[] = (int) $id;
                }
            }
            $primary = (int) Cache::get('cart_draft_order_' . $cartId, 0);
            if ($primary > 0) {
                $ids[] = $primary;
            }
            if (Schema::connection('pgsql_billing')->hasColumn('orders', 'cart_id')) {
                $fromDb = $this->orders->newQuery()
                    ->where('partner_id', $partnerId)
                    ->where('cart_id', $cartId)
                    ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = 'draft'")
                    ->orderBy('id')
                    ->pluck('id')
                    ->map(fn($id) => (int) $id)
                    ->all();
                $ids = array_merge($ids, $fromDb);
            }
        }

        if ($ids === [] && $customerId > 0) {
            $q = $this->orders->newQuery()
                ->where('partner_id', $partnerId)
                ->where('user_id', $customerId)
                ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = 'draft'")
                ->where(function ($qq) {
                    $qq->whereNull('parent_order_id')->orWhere('parent_order_id', 0);
                })
                ->orderBy('id');
            if ($domainName !== '') {
                $q->where('domain_name', $domainName);
            }
            $ids = $q->pluck('id')->map(fn($id) => (int) $id)->all();
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        return array_values(array_filter(
            $ids,
            fn($id) => $this->isDraftOrderStatus((int) $id)
        ));
    }

    /**
     * @param  list<int>  $orderIds
     * @return list<int>
     */
    protected function planIdsForDraftOrders(array $orderIds): array
    {
        $planIds = [];
        foreach ($orderIds as $orderId) {
            $orderId = (int) $orderId;
            if ($orderId <= 0) {
                continue;
            }
            $cached = Cache::get('draft_order_plan_ids_' . $orderId);
            if (is_array($cached) && $cached !== []) {
                foreach ($cached as $pid) {
                    $planIds[] = (int) $pid;
                }
                continue;
            }
            $fromItems = OrderItem::query()
                ->where('order_id', $orderId)
                ->pluck('plan_id')
                ->map(fn($id) => (int) $id)
                ->filter(fn($id) => $id > 0)
                ->all();
            foreach ($fromItems as $pid) {
                $planIds[] = $pid;
            }
            if ($fromItems !== []) {
                Cache::put('draft_order_plan_ids_' . $orderId, array_values(array_unique($fromItems)), now()->addDays(7));
            }
        }

        return array_values(array_unique(array_filter($planIds)));
    }

    protected function isDraftOrderStatus(int $orderId): bool
    {
        if ($orderId <= 0) {
            return false;
        }
        $status = strtolower(trim((string) (
            $this->orders->newQuery()->whereKey($orderId)->value('status') ?? ''
        )));
        if ($status !== self::STATUS_DRAFT) {
            return false;
        }

        // Aadhaar verified ⇒ purchased; do not treat as open draft.
        $poVerified = (int) ($this->orderPoDetails->newQuery()
            ->where('order_id', $orderId)
            ->value('aadhar_verified') ?? 0);

        return $poVerified !== 1;
    }

    /**
     * Manually trigger processing notifications for an existing order
     */
    public function sendOrderProcessingNotification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', Rule::exists(Order::class, 'id')],
        ]);

        $orderId = (int) $validated['order_id'];
        $order = $this->orders->newQuery()->whereKey($orderId)->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.',
            ], 404);
        }

        if ($order->status !== self::STATUS_PROCESSING) {
            return response()->json([
                'success' => false,
                'message' => 'Order is not in processing state.',
            ], 422);
        }

        $this->sendProcessingOrderNotifications($orderId);

        return response()->json([
            'success' => true,
            'message' => 'Processing notifications sent successfully.',
        ]);
    }

    protected function fail(string $message, int $status = 422, ?array $errors = null): JsonResponse
    {
        $payload = ['success' => false, 'message' => $message];
        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    protected function persistUpgradeOrderItem(
        int $upgradeOrderId,
        Order $baseOrder,
        Plan $newPlan,
        int $quantity,
        array $summary,
        ?object $upgradeOrderRow = null,
        ?int $previousOrderSubId = null
    ): void {
        if ($upgradeOrderId <= 0) {
            return;
        }

        $existingItemId = (int) $this->orderItems->newQuery()->where('order_id', $upgradeOrderId)->value('id');
        if ($existingItemId > 0) {
            // Backfill link/meta when an earlier insert skipped them.
            $patch = [];
            if ($previousOrderSubId && $previousOrderSubId > 0) {
                $patch['previous_order_sub_id'] = $previousOrderSubId;
            }
            if ($patch !== []) {
                $this->orderItems->newQuery()->whereKey($existingItemId)->where(function ($q) {
                    $q->whereNull('previous_order_sub_id')
                        ->orWhere('previous_order_sub_id', 0);
                })->update($patch);
            }

            return;
        }

        $qty = max(1, $quantity);
        $newUnitPrice = round((float) ($summary['new_unit_price'] ?? $newPlan->price ?? 0), 2);
        $proratedCredit = round((float) ($summary['prorated_credit'] ?? $summary['pro_rata_adjustment'] ?? 0), 2);
        $taxable = round((float) ($summary['taxable_amount'] ?? max(0, (float) ($summary['subtotal'] ?? 0) - $proratedCredit)), 2);
        $gstAmount = round((float) ($summary['gst_amount'] ?? 0), 2);
        $payable = round((float) ($summary['payable_amount'] ?? ($taxable + $gstAmount)), 2);
        $gstRate = (float) ($baseOrder->gst_rate ?? RenewalService::GST_RATE_PERCENT);

        $category = strtolower(trim((string) ($upgradeOrderRow->order_category ?? '')));
        $isDowngradeItem = in_array($category, ['downgrade', 'downgraded'], true)
            || strtolower(trim((string) ($upgradeOrderRow->order_type ?? ''))) === 'downgrade';

        if ($isDowngradeItem) {
            $start = Carbon::now()->startOfDay();
            $end = app(RenewalService::class)
                ->subscriptionEndDate($start, (string) ($upgradeOrderRow->duration ?? $baseOrder->duration ?? '12 month'))
                ->startOfDay();
            $subStart = $start->toDateString();
            $subEnd = $end->toDateString();
        } else {
            $subStart = $upgradeOrderRow->subscription_start_date
                ?? $baseOrder->subscription_start_date
                ?? null;
            $subEnd = $upgradeOrderRow->subscription_end_date
                ?? $baseOrder->subscription_end_date
                ?? null;
        }

        $sourceSubId = (int) ($previousOrderSubId
            ?? $upgradeOrderRow->parent_order_sub_id
            ?? 0);
        $oldPlanId = (int) ($upgradeOrderRow->previous_plan_id ?? 0);
        if ($oldPlanId <= 0 && $sourceSubId > 0) {
            $oldPlanId = (int) OrderItem::query()->whereKey($sourceSubId)->value('plan_id');
        }
        if ($oldPlanId <= 0) {
            $oldPlanId = (int) ($baseOrder->plan_id ?? 0);
        }

        $orderItem = $this->orderItems->newQuery()->create([
            'order_id' => $upgradeOrderId,
            'plan_id' => (int) $newPlan->id,
            'item_name' => (string) ($newPlan->name ?? 'Plan'),
            'quantity' => $qty,
            'unit_price' => $newUnitPrice,
            'discount_amount' => $proratedCredit,
            'tax_rate' => $gstRate,
            'tax_amount' => $gstAmount,
            'total_amount' => round($newUnitPrice * $qty, 2),
            'subscription_start_date' => $subStart ? Carbon::parse((string) $subStart)->toDateString() : null,
            'subscription_end_date' => $subEnd ? Carbon::parse((string) $subEnd)->toDateString() : null,
            'remaining_days' => (int) ($summary['remaining_days'] ?? 0),
            'total_plan_days' => (int) ($summary['total_plan_days'] ?? 365),
            'prorated_credit' => $proratedCredit,
            'previous_order_sub_id' => $sourceSubId > 0 ? $sourceSubId : null,
            'meta' => [
                'upgrade_from_order_id' => (int) $baseOrder->id,
                'old_plan_id' => $oldPlanId,
                'previous_order_sub_id' => $sourceSubId > 0 ? $sourceSubId : null,
                'old_unit_price' => round((float) ($summary['old_unit_price'] ?? 0), 2),
                'new_plan_remaining_total' => round((float) ($summary['subtotal'] ?? 0), 2),
            ],
        ]);
        $subscriptionPrefix = strtoupper(trim((string) (
            $this->adminSettings->newQuery()->orderBy('id')->value('subscription_prefix') ?? 'SUB'
        ))) ?: 'SUB';
        $orderItem->subscription_id = $subscriptionPrefix . '-' . str_pad((string) $orderItem->id, 6, '0', STR_PAD_LEFT);
        $orderItem->save();
    }

    /**
     * True when partner is finally placing (not just generating a cart PO).
     * eSign/Aadhaar no longer places — only explicit place flags.
     */
    protected function wantsFinalPlace(Request $request): bool
    {
        return $request->boolean('place_order')
            || $request->boolean('finalize')
            || $request->boolean('confirm_place')
            || (string) $request->input('place_order') === '1'
            || (string) $request->input('finalize') === '1'
            || (string) $request->input('confirm_place') === '1'
            || strtolower(trim((string) $request->input('action', ''))) === 'place';
    }

    /**
     * Undo a failed place-order-without-aadhaar attempt: remove in-flight order rows
     * (orders / order_items / order_po_details) and restore the cart for retry.
     */
    protected function rollbackFailedPlaceOrderAttempt(
        int $partnerId,
        int $cartId,
        ?int $orderIdHint = null,
        ?int $restoreCartParentOrderId = null
    ): void {
        if ($partnerId <= 0 || $cartId <= 0) {
            return;
        }

        $candidateIds = [];
        if ($orderIdHint !== null && $orderIdHint > 0) {
            $candidateIds[] = $orderIdHint;
        }

        $cachedDraft = (int) Cache::get('cart_draft_order_' . $cartId, 0);
        if ($cachedDraft > 0) {
            $candidateIds[] = $cachedDraft;
        }

        $cachedList = Cache::get('cart_draft_orders_' . $cartId, []);
        if (is_array($cachedList)) {
            foreach ($cachedList as $id) {
                $candidateIds[] = (int) $id;
            }
        }

        if (
            Schema::connection('pgsql_billing')->hasColumn('orders', 'cart_id')
            || Schema::connection('pgsql_billing')->hasColumn('orders', 'source_cart_id')
        ) {
            $linkedQuery = $this->orders->newQuery()
                ->where('partner_id', $partnerId)
                ->whereIn('status', [
                    self::STATUS_PROCESSING,
                    self::STATUS_PENDING,
                    self::STATUS_DRAFT,
                ]);

            $linkedQuery->where(function ($q) use ($cartId) {
                if (Schema::connection('pgsql_billing')->hasColumn('orders', 'cart_id')) {
                    $q->orWhere('cart_id', $cartId);
                }
                if (Schema::connection('pgsql_billing')->hasColumn('orders', 'source_cart_id')) {
                    $q->orWhere('source_cart_id', $cartId);
                }
            });

            $candidateIds = array_merge($candidateIds, $linkedQuery->pluck('id')->all());
        }

        $candidateIds = array_values(array_unique(array_filter(array_map('intval', $candidateIds))));
        $deletedOrderIds = [];

        // Restore cart lines from order_items before we delete them.
        $remainingItems = (int) CartItem::query()->where('cart_id', $cartId)->count();
        if ($remainingItems <= 0 && $candidateIds !== []) {
            foreach (array_reverse($candidateIds) as $restoreFromOrderId) {
                if ($restoreCartParentOrderId > 0 && $restoreFromOrderId === $restoreCartParentOrderId) {
                    continue;
                }
                $orderItems = OrderItem::query()
                    ->where('order_id', (int) $restoreFromOrderId)
                    ->orderBy('id')
                    ->get();
                if ($orderItems->isEmpty()) {
                    continue;
                }
                foreach ($orderItems as $orderItemRow) {
                    $planId = (int) ($orderItemRow->plan_id ?? 0);
                    if ($planId <= 0) {
                        continue;
                    }
                    if (CartItem::query()->where('cart_id', $cartId)->where('plan_id', $planId)->exists()) {
                        continue;
                    }
                    CartItem::query()->create([
                        'cart_id' => $cartId,
                        'plan_id' => $planId,
                        'licenses' => max(1, (int) ($orderItemRow->quantity ?? 1)),
                        'unit_price' => round((float) ($orderItemRow->unit_price ?? 0), 2),
                        'grand_total' => round((float) ($orderItemRow->total_amount ?? 0), 2),
                        'subscription_start_date' => $orderItemRow->subscription_start_date ?? null,
                        'subscription_end_date' => $orderItemRow->subscription_end_date ?? null,
                        'added_at' => now(),
                    ]);
                }
                if (CartItem::query()->where('cart_id', $cartId)->exists()) {
                    app(PartnerCartService::class)->recalculateCartTotals($cartId);
                    break;
                }
            }
        }

        foreach ($candidateIds as $removeId) {
            if ($restoreCartParentOrderId > 0 && $removeId === $restoreCartParentOrderId) {
                continue;
            }

            $row = $this->orders->newQuery()->whereKey($removeId)->where('partner_id', $partnerId)->first();
            if (!$row) {
                continue;
            }
            $status = strtolower(trim((string) ($row->status ?? '')));
            if (!in_array($status, [self::STATUS_PROCESSING, self::STATUS_PENDING, self::STATUS_DRAFT], true)) {
                continue;
            }

            $this->orderPoDetails->newQuery()->where('order_id', $removeId)->delete();
            if (Schema::connection('pgsql_billing')->hasTable('order_po_subs')) {
                OrderPoSub::query()->where('order_id', $removeId)->delete();
            }
            OrderItem::query()->where('order_id', $removeId)->delete();
            $this->orders->newQuery()->whereKey($removeId)->delete();

            $this->forgetDraftCartLink($cartId, $removeId);
            Cache::forget('draft_order_plan_ids_' . $removeId);
            Cache::forget('draft_order_cart_' . $removeId);
            $deletedOrderIds[] = $removeId;
        }

        $this->forgetDraftCartLink($cartId, 0);

        $cartUpdate = ['status' => 'active'];
        if (
            $restoreCartParentOrderId !== null
            && $restoreCartParentOrderId > 0
            && Schema::connection('pgsql_billing')->hasColumn('carts', 'order_id')
        ) {
            $cartUpdate['order_id'] = $restoreCartParentOrderId;
        } elseif (Schema::connection('pgsql_billing')->hasColumn('carts', 'order_id')) {
            $parentOnCart = (int) ($this->carts->newQuery()->whereKey($cartId)->value('order_id') ?? 0);
            if ($parentOnCart > 0 && in_array($parentOnCart, $deletedOrderIds, true)) {
                $cartUpdate['order_id'] = $restoreCartParentOrderId > 0 ? $restoreCartParentOrderId : null;
            }
        }
        $this->carts->newQuery()->whereKey($cartId)->where('partner_id', $partnerId)->update($cartUpdate);

        Log::info('place_order_rollback', [
            'partner_id' => $partnerId,
            'cart_id' => $cartId,
            'deleted_order_ids' => $deletedOrderIds,
            'restore_parent_order_id' => $restoreCartParentOrderId,
        ]);
    }

    protected function cancelLeftoverDraftOrdersForCart(int $partnerId, int $cartId): void
    {
        if ($cartId <= 0) {
            return;
        }

        app(PartnerCartService::class)->cancelOpenDraftOrdersForCart($cartId, $partnerId);
        $this->forgetDraftCartLink($cartId, 0);
    }

    protected function markCartPendingAadhaar(int $cartId, string $flow = 'new'): void
    {
        if ($cartId <= 0) {
            return;
        }

        $parentOrderId = null;
        $orderSubId = null;
        $cart = $this->carts->newQuery()->whereKey($cartId)->first();
        if ($cart) {
            $type = strtolower(trim((string) ($cart->type ?? '')));
            if (
                in_array($type, [Cart::TYPE_RENEW, Cart::TYPE_UPGRADE, Cart::TYPE_DOWNGRADE, 'license_add'], true)
                || in_array(strtolower(trim($flow)), ['renew', 'renewal', 'upgrade', 'downgrade', 'license_add', 'addon'], true)
            ) {
                $parentOrderId = (int) ($cart->order_id ?? 0) ?: null;
                if (Schema::connection('pgsql_billing')->hasColumn('carts', 'order_sub_id')) {
                    $orderSubId = (int) ($cart->order_sub_id ?? 0) ?: null;
                }
            }
        }

        // Service cancels leftover draft orders + marks cart pending (PND temp only).
        app(PartnerCartService::class)->markCartPendingAadhaar($cartId, $flow);
        $this->ensureCartFlowType($cartId, $flow, $parentOrderId, $orderSubId);
    }

    /**
     * Persist cart.type (+ parent links) so Place routes renew/upgrade correctly.
     */
    protected function ensureCartFlowType(
        int $cartId,
        string $flow,
        ?int $parentOrderId = null,
        ?int $orderSubId = null
    ): void {
        if ($cartId <= 0) {
            return;
        }

        $flow = strtolower(trim($flow));
        $type = match ($flow) {
            'renew', 'renewal' => Cart::TYPE_RENEW,
            'upgrade' => Cart::TYPE_UPGRADE,
            'downgrade' => Cart::TYPE_DOWNGRADE,
            default => null,
        };

        $update = ['status' => 'active'];
        if ($type !== null) {
            $update['type'] = $type;
        }

        $cart = $this->carts->newQuery()->whereKey($cartId)->first();
        if (!$cart) {
            return;
        }

        if (
            $parentOrderId !== null && $parentOrderId > 0
            && Schema::connection('pgsql_billing')->hasColumn('carts', 'order_id')
        ) {
            // Keep parent subscription id on renew/upgrade carts (not a draft order id).
            $linkedStatus = '';
            $existingLinked = (int) ($cart->order_id ?? 0);
            if ($existingLinked > 0) {
                $linkedStatus = strtolower(trim((string) (
                    $this->orders->newQuery()->whereKey($existingLinked)->value('status') ?? ''
                )));
            }
            if ($existingLinked <= 0 || $linkedStatus === 'draft' || $existingLinked === $parentOrderId) {
                $update['order_id'] = $parentOrderId;
            } elseif (
                in_array($type, [Cart::TYPE_RENEW, Cart::TYPE_UPGRADE, Cart::TYPE_DOWNGRADE], true)
                && $linkedStatus !== 'draft'
            ) {
                // Prefer explicit parent when placing renew/upgrade.
                $update['order_id'] = $parentOrderId;
            }
        }

        if ($orderSubId !== null && $orderSubId > 0) {
            if (Schema::connection('pgsql_billing')->hasColumn('carts', 'order_sub_id')) {
                $update['order_sub_id'] = $orderSubId;
            }
            if (Schema::connection('pgsql_billing')->hasColumn('carts', 'item_type')) {
                $itemType = trim((string) ($cart->item_type ?? ''));
                $sourcePlanId = (int) (OrderItem::query()->whereKey($orderSubId)->value('plan_id') ?? 0);
                $encoded = 'order_sub:' . $orderSubId
                    . ($sourcePlanId > 0 ? (':plan:' . $sourcePlanId) : '');
                $cartPlanId = (int) ($cart->items()->value('plan_id') ?? 0);
                if (
                    str_contains(strtolower((string) ($cart->item_type ?? '')), 'mode:license_add')
                    || strtolower(trim((string) ($cart->type ?? ''))) === 'license_add'
                ) {
                    $encoded .= ':mode:license_add';
                } elseif ($sourcePlanId > 0 && $cartPlanId > 0 && $sourcePlanId === $cartPlanId) {
                    $encoded .= ':mode:license_add';
                }
                if ($itemType === '' || !str_starts_with($itemType, 'order_sub:') || !str_contains($itemType, ':plan:')) {
                    $update['item_type'] = $encoded;
                }
            }
        }

        $this->carts->newQuery()->whereKey($cartId)->update($update);
    }

    /**
     * @deprecated Use PartnerCartService::cancelOpenDraftOrdersForCart
     */
    protected function cancelOpenDraftOrdersForPendingCart(int $cartId): void
    {
        if ($cartId <= 0) {
            return;
        }

        $partnerId = (int) ($this->carts->newQuery()->whereKey($cartId)->value('partner_id') ?? 0);
        app(PartnerCartService::class)->cancelOpenDraftOrdersForCart($cartId, $partnerId);
        $this->forgetDraftCartLink($cartId, 0);
    }

    /**
     * Cart-only response after PO generate — no orders row until place_order=1.
     *
     * @param  array<string, mixed>  $extra
     */
    protected function cartPendingAadhaarResponse(
        int $cartId,
        string $companyName,
        string $message,
        array $extra = [],
        string $flow = 'new'
    ): JsonResponse {
        $this->markCartPendingAadhaar($cartId, $flow);

        $poLink = null;
        $poNumber = null;
        $poError = null;

        $pendingRef = null;
        if ($cartId > 0) {
            $poResult = $this->createCartPoWithoutOrder($cartId, true);
            $poLink = $poResult['po_link'] ?? null;
            $poNumber = $poResult['po_number'] ?? null;
            $poError = $poResult['error'] ?? null;

            $cartRow = $this->carts->newQuery()->whereKey($cartId)->first();
            $partnerIdForRef = (int) ($cartRow->partner_id ?? 0);
            if ($partnerIdForRef > 0) {
                $pendingRef = app(PartnerCartService::class)->issueCartPendingEsignRef($cartId, $partnerIdForRef);
            }
        }

        $cartTotals = $cartId > 0 ? $this->carts->newQuery()->whereKey($cartId)->first() : null;
        // Until Place Order: expose cart_id as ordId so FE can call place with that id.
        $tempOrdId = $cartId > 0 ? $cartId : 0;

        // Temp PND only — never a real orders-table row / TNX order_no.
        $responseData = array_merge([
            'order_id' => $tempOrdId,
            'order_ids' => [],
            'order_no' => $pendingRef,
            'ordId' => $tempOrdId,
            'pending_ref_id' => $pendingRef,
            'temp_order_no' => $pendingRef,
            'is_temp_order' => true,
            'real_order' => false,
            'status' => 'pending_place',
            'sign_required' => 'no',
            'aadhaar_pending' => false,
            'place_order_required' => true,
            'draft_cart' => $cartId > 0,
            'draft_order' => null,
            'company_name' => $companyName,
            'subscription_id' => '',
            'po_link' => $poLink,
            'po_number' => $poNumber,
            'cart_id' => $cartId > 0 ? $cartId : null,
            'subtotal' => round((float) ($cartTotals->subtotal ?? 0), 2),
            'gst_amount' => round((float) ($cartTotals->gst_amount ?? 0), 2),
            'discount_amount' => round((float) ($cartTotals->discount_amount ?? 0), 2),
            'total_amount' => round((float) ($cartTotals->grand_total ?? 0), 2),
            'plan_amount' => round((float) ($cartTotals->subtotal ?? $cartTotals->grand_total ?? 0), 2),
            'plans' => [],
            'orders' => [],
        ], $extra);

        // Force non-real-order flags even if $extra tries to override.
        $responseData['is_temp_order'] = true;
        $responseData['real_order'] = false;
        $responseData['draft_order'] = null;
        $responseData['orders'] = [];
        $responseData['order_ids'] = [];
        $responseData['status'] = 'pending_place';
        $responseData['sign_required'] = 'no';
        $responseData['aadhaar_pending'] = false;
        $responseData['place_order_required'] = true;
        $responseData['order_id'] = $tempOrdId;
        $responseData['ordId'] = $tempOrdId;
        if ($poLink) {
            $responseData['po_link'] = $poLink;
            $responseData['po_number'] = $poNumber;
        }
        $cartPoMeta = $cartId > 0 ? app(PartnerCartService::class)->getCartPoMeta($cartId) : null;
        if ($cartPoMeta !== null) {
            $responseData['po_mode'] = (string) ($cartPoMeta['po_mode'] ?? ($extra['po_mode'] ?? 'generated'));
        }
        if ($pendingRef) {
            $responseData['order_no'] = $pendingRef;
            $responseData['pending_ref_id'] = $pendingRef;
            $responseData['temp_order_no'] = $pendingRef;
        }

        if ($poError !== null && $poError !== '') {
            $responseData['po_error'] = $poError;
        }

        // Review Order / order-details: always expose enrollment type on draft cart responses.
        if (trim((string) ($responseData['enrollment_type'] ?? '')) === '') {
            $responseData['enrollment_type'] = $this->resolveEnrollmentType(
                $cartTotals,
                (string) ($extra['order_category'] ?? $extra['order_type'] ?? $flow ?? '')
            );
        }
        $responseData['order_type'] = (string) (
            $responseData['order_type']
            ?? $extra['order_category']
            ?? $extra['order_type']
            ?? ($cartTotals->type ?? $flow)
            ?? 'new'
        );

        // Always expose domain + remaining_value on root + each plan (cart → parent subscription fallback).
        $domainName = trim((string) ($responseData['domain_name'] ?? $responseData['domain'] ?? ''));
        if ($domainName === '' && $cartId > 0) {
            $domainName = $this->resolveDomainNameForOrder(null, $cartId);
            if ($domainName === '') {
                $parentId = (int) ($responseData['parent_order_id'] ?? 0);
                if ($parentId <= 0) {
                    $parentId = (int) ($this->carts->newQuery()->whereKey($cartId)->value('order_id') ?? 0);
                }
                if ($parentId > 0) {
                    $domainName = trim((string) (
                        $this->orders->newQuery()->whereKey($parentId)->value('domain_name') ?? ''
                    ));
                }
            }
        }
        $responseData['domain_name'] = $domainName;
        $responseData['domain'] = $domainName;

        $remainingValue = round((float) ($responseData['remaining_value'] ?? $responseData['pro_rata_adjustment'] ?? 0), 2);
        if ($remainingValue <= 0 && $cartTotals) {
            $remainingValue = round((float) ($cartTotals->pro_rata_adjustment ?? 0), 2);
        }
        $cartSubtotal = round((float) ($responseData['subtotal'] ?? $cartTotals->subtotal ?? 0), 2);
        $appliedProRata = round((float) ($responseData['applied_pro_rata'] ?? 0), 2);
        if ($appliedProRata <= 0 && $remainingValue > 0) {
            $appliedProRata = $cartSubtotal > 0 ? round(min($remainingValue, $cartSubtotal), 2) : $remainingValue;
        }
        $responseData['remaining_value'] = $remainingValue;
        $responseData['remaining_credit'] = $remainingValue;
        $responseData['pro_rata_adjustment'] = $remainingValue;
        $responseData['applied_pro_rata'] = $appliedProRata;

        if (!empty($responseData['plans']) && is_array($responseData['plans'])) {
            foreach ($responseData['plans'] as $i => $planRow) {
                if (!is_array($planRow)) {
                    continue;
                }
                if (trim((string) ($responseData['plans'][$i]['domain_name'] ?? '')) === '') {
                    $responseData['plans'][$i]['domain_name'] = $domainName;
                }
                if (trim((string) ($responseData['plans'][$i]['domain'] ?? '')) === '') {
                    $responseData['plans'][$i]['domain'] = $domainName;
                }
                if (round((float) ($responseData['plans'][$i]['remaining_value'] ?? 0), 2) <= 0) {
                    $responseData['plans'][$i]['remaining_value'] = $remainingValue;
                }
                if (round((float) ($responseData['plans'][$i]['remaining_credit'] ?? 0), 2) <= 0) {
                    $responseData['plans'][$i]['remaining_credit'] = $remainingValue;
                }
                if (round((float) ($responseData['plans'][$i]['pro_rata_adjustment'] ?? 0), 2) <= 0) {
                    $responseData['plans'][$i]['pro_rata_adjustment'] = $remainingValue;
                }
                if (round((float) ($responseData['plans'][$i]['applied_pro_rata'] ?? 0), 2) <= 0) {
                    $responseData['plans'][$i]['applied_pro_rata'] = $appliedProRata;
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => $message !== ''
                ? $message
                : 'Draft PO saved in cart. Call place_order=1 to place the order.',
            'data' => $responseData,
        ], 200);
    }

    /**
     * When proceed uses uploaded PO and eSign is still pending, keep the file on the cart.
     */
    protected function storeUploadedPoOnCartIfNeeded(Request $request, int $cartId, string $poMode): ?JsonResponse
    {
        if ($cartId <= 0 || $poMode !== \App\Services\OrderPoDocumentService::MODE_UPLOADED) {
            return null;
        }

        if (!$request->hasFile('po_document')) {
            $existing = app(PartnerCartService::class)->getCartPoMeta($cartId);
            if ($existing !== null && ($existing['po_link'] ?? '') !== '') {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Please upload a PDF purchase order, or choose Generate PO.',
            ], 422);
        }

        $stored = app(\App\Services\OrderPoDocumentService::class)->storeUploadedPoForCart(
            $cartId,
            $request->file('po_document'),
            $request->input('po_number')
        );

        if (empty($stored['ok'])) {
            return response()->json([
                'success' => false,
                'message' => (string) ($stored['error'] ?? 'PO upload failed.'),
            ], 422);
        }

        return null;
    }

    /**
     * @return array{ok: bool, po_link: ?string, po_number: ?string, error: ?string}
     */
    protected function createCartPoWithoutOrder(int $cartId, bool $forceRefresh = false): array
    {
        if ($cartId <= 0) {
            return ['ok' => false, 'po_link' => null, 'po_number' => null, 'error' => 'Invalid cart id.'];
        }

        $cartService = app(PartnerCartService::class);
        $cartAmount = round((float) ($this->carts->newQuery()->whereKey($cartId)->value('grand_total') ?? 0), 2);
        $cachedAmount = Cache::get('cart_po_amount_' . $cartId);
        $existing = $cartService->getCartPoMeta($cartId);
        $poMode = strtolower(trim((string) ($existing['po_mode'] ?? 'generated')));

        // Partner-uploaded PO must never be overwritten by a system-generated PDF,
        // even when forceRefresh=true (cartPendingAadhaarResponse always forces refresh).
        if (
            $existing !== null
            && ($existing['po_link'] ?? '') !== ''
            && $poMode === 'uploaded'
        ) {
            return [
                'ok' => true,
                'po_link' => (string) $existing['po_link'],
                'po_number' => (string) ($existing['po_number'] ?? ''),
                'error' => null,
            ];
        }

        // Keep generated PO when amount unchanged and not forced.
        $amountMatches = $cachedAmount !== null && (string) $cachedAmount === (string) $cartAmount;
        if (
            !$forceRefresh
            && $existing !== null
            && ($existing['po_link'] ?? '') !== ''
            && $amountMatches
        ) {
            return [
                'ok' => true,
                'po_link' => (string) $existing['po_link'],
                'po_number' => (string) ($existing['po_number'] ?? ''),
                'error' => null,
            ];
        }

        try {
            $stored = app(BillingController::class)->saveCartPoPdfDocument($cartId);
            if (empty($stored['public_url'])) {
                return [
                    'ok' => false,
                    'po_link' => null,
                    'po_number' => null,
                    'error' => 'PO link could not be created.',
                ];
            }

            $poLink = (string) $stored['public_url'];
            $poNumber = (string) ($stored['po_number'] ?? '');
            $cartService->saveCartPoMeta($cartId, $poLink, $poNumber, 'generated');

            return [
                'ok' => true,
                'po_link' => $poLink,
                'po_number' => $poNumber,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('createCartPoWithoutOrder failed', [
                'cart_id' => $cartId,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'po_link' => null,
                'po_number' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Map PND… reference from verify-aadhar URL onto cart fields for init/verify APIs.
     */
    protected function applyPendingEsignRefToRequest(Request $request): ?JsonResponse
    {
        $ref = $this->extractPendingEsignRefFromRequest($request);
        if ($ref === null) {
            return null;
        }

        $cartService = app(PartnerCartService::class);
        $resolved = $cartService->resolvePendingEsignRef($ref);
        if ($resolved === null || !$cartService->isCartPendingAadhaar((int) $resolved['cart_id'])) {
            $cartService->clearCartPendingEsignRefByRef($ref);

            return $this->fail('Verification reference expired or cart was removed.', 404);
        }

        $cartId = (int) $resolved['cart_id'];
        $cart = $this->carts->newQuery()->whereKey($cartId)->first();
        if (!$cart) {
            $cartService->clearCartPendingEsignRefByRef($ref);

            return $this->fail('Cart not found.', 404);
        }

        $request->merge([
            'main_cart_id' => $cartId,
            'cart_id' => $cartId,
            'pending_ref_id' => $ref,
            'temp_order_no' => $ref,
        ]);
        if (!$request->filled('partner_id') && (int) ($resolved['partner_id'] ?? 0) > 0) {
            $request->merge(['partner_id' => (int) $resolved['partner_id']]);
        }

        // Avoid treating PND… as a numeric order_id in the real-order branch.
        if (is_string($request->input('order_id')) && $cartService->isPendingEsignRef((string) $request->input('order_id'))) {
            $request->request->remove('order_id');
            $request->query->remove('order_id');
        }

        return null;
    }

    protected function extractPendingEsignRefFromRequest(Request $request): ?string
    {
        $cartService = app(PartnerCartService::class);
        $candidates = [
            $request->input('ordId'),
            $request->input('ord_id'),
            $request->input('pending_ref_id'),
        ];

        $orderNo = $request->input('order_no');
        if (is_string($orderNo) && $cartService->isPendingEsignRef($orderNo)) {
            $candidates[] = $orderNo;
        }

        $orderIdRaw = $request->input('order_id');
        if (is_string($orderIdRaw) && $cartService->isPendingEsignRef($orderIdRaw)) {
            $candidates[] = $orderIdRaw;
        }

        foreach ($candidates as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }
            $ref = strtoupper(trim($candidate));
            if ($cartService->isPendingEsignRef($ref)) {
                return $ref;
            }
        }

        return null;
    }

    /**
     * Accept browser/React-Native PDF File uploads under common field names, or as base64.
     * Normalizes everything onto request file key `po_document`.
     */
    protected function normalizePoDocumentUpload(Request $request): void
    {
        $fileKeys = [
            'po_document',
            'poDocument',
            'file',
            'pdf',
            'document',
            'po_file',
            'po_pdf',
            'upload',
            'po',
        ];

        foreach ($fileKeys as $key) {
            if (!$request->hasFile($key)) {
                continue;
            }
            $file = $request->file($key);
            if ($file instanceof UploadedFile && $file->isValid()) {
                if ($key !== 'po_document') {
                    $request->files->set('po_document', $file);
                }
                return;
            }
        }

        if ($request->hasFile('po_document')) {
            return;
        }

        $raw = $request->input('po_document_base64')
            ?? $request->input('pdf_base64')
            ?? $request->input('document_base64');

        if (!is_string($raw) || trim($raw) === '') {
            $maybe = $request->input('po_document');
            if (is_string($maybe) && trim($maybe) !== '') {
                $raw = $maybe;
            }
        }

        if (!is_string($raw) || trim($raw) === '') {
            return;
        }

        $filename = (string) (
            $request->input('po_document_name')
            ?? $request->input('file_name')
            ?? $request->input('filename')
            ?? 'Tizzy.pdf'
        );

        $uploaded = $this->uploadedPdfFromBase64($raw, $filename);
        if ($uploaded) {
            $request->files->set('po_document', $uploaded);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function poDocumentValidationRules(bool $required = false): array
    {
        return [
            'po_document' => [
                $required ? 'required' : 'nullable',
                'file',
                'max:10240',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (!$value instanceof UploadedFile) {
                        $fail('Please upload a PDF purchase order.');
                        return;
                    }

                    $ext = strtolower((string) $value->getClientOriginalExtension());
                    $mime = strtolower((string) ($value->getClientMimeType() ?: $value->getMimeType() ?: ''));
                    $name = strtolower((string) $value->getClientOriginalName());

                    $ok = $ext === 'pdf'
                        || str_ends_with($name, '.pdf')
                        || str_contains($mime, 'pdf')
                        || in_array($mime, ['application/octet-stream', 'binary/octet-stream', ''], true);

                    if (!$ok) {
                        $path = $value->getRealPath() ?: $value->getPathname();
                        $head = $path ? (string) @file_get_contents($path, false, null, 0, 5) : '';
                        $ok = $head === '%PDF-';
                    }

                    if (!$ok) {
                        $fail('Only PDF purchase orders are accepted (application/pdf).');
                    }
                },
            ],
        ];
    }

    protected function uploadedPdfFromBase64(string $payload, string $filename = 'Tizzy.pdf'): ?UploadedFile
    {
        $payload = trim($payload);
        if ($payload === '') {
            return null;
        }

        if (str_starts_with($payload, 'data:') && str_contains($payload, ',')) {
            $payload = substr($payload, strpos($payload, ',') + 1);
        }

        $binary = base64_decode($payload, true);
        if ($binary === false || $binary === '') {
            return null;
        }

        if (!str_ends_with(strtolower($filename), '.pdf')) {
            $filename .= '.pdf';
        }

        $tmp = tempnam(sys_get_temp_dir(), 'po_pdf_');
        if ($tmp === false) {
            return null;
        }
        file_put_contents($tmp, $binary);

        return new UploadedFile($tmp, $filename, 'application/pdf', null, true);
    }
    public function orderDetails(Request $request): JsonResponse
    {
        // FE page uses ?ordId=221 (cart id for drafts). Accept body + query.
        $ordFromQuery = $request->query('ordId', $request->query('order_id'));
        if (!$request->filled('order_id') && !$request->filled('ordId') && $ordFromQuery !== null && $ordFromQuery !== '') {
            $request->merge(['order_id' => $ordFromQuery, 'ordId' => $ordFromQuery]);
        }
        if (!$request->filled('order_id') && $request->filled('ordId')) {
            $request->merge(['order_id' => $request->input('ordId')]);
        }
        if (!$request->filled('ordId') && $request->filled('order_id')) {
            $request->merge(['ordId' => $request->input('order_id')]);
        }
        if (!$request->filled('cart_id') && $request->filled('main_cart_id')) {
            $request->merge(['cart_id' => $request->input('main_cart_id')]);
        }

        $partnerId = $this->resolvePartnerIdFromRequest($request) ?? 0;
        $orderId = (int) ($request->input('order_id') ?? $request->input('ordId') ?? $ordFromQuery ?? 0);
        $cartId = (int) ($request->input('cart_id') ?? $request->input('main_cart_id') ?? 0);

        // Resolve cart / partner before requiring partner_id.
        if ($orderId < 1 && $cartId <= 0) {
            $ref = trim((string) (
                $request->input('pending_ref_id')
                ?? $request->input('temp_order_no')
                ?? $request->input('order_no')
                ?? ''
            ));
            if ($ref !== '' && app(PartnerCartService::class)->isPendingEsignRef($ref)) {
                $resolved = app(PartnerCartService::class)->resolvePendingEsignRef($ref);
                if ($resolved !== null) {
                    $cartId = (int) ($resolved['cart_id'] ?? 0);
                    if ($partnerId <= 0) {
                        $partnerId = (int) ($resolved['partner_id'] ?? 0);
                    }
                }
            }
        }
        if ($partnerId <= 0 && $cartId > 0) {
            $partnerId = (int) ($this->carts->newQuery()->whereKey($cartId)->value('partner_id') ?? 0);
        }

        // ordId may be cart id (draft or after place). Prefer cart when no matching order row.
        // After place, cart is converted — resolve the real order via orders.cart_id.
        if ($orderId > 0) {
            $cartRow = $this->carts->newQuery()->whereKey($orderId)->first();
            $realOrder = $this->orders->newQuery()->whereKey($orderId)->first();
            if ($cartRow && !$realOrder) {
                $cartId = $orderId;
                if ($partnerId <= 0) {
                    $partnerId = (int) ($cartRow->partner_id ?? 0);
                }
                $placedOrderId = $this->resolvePlacedOrderIdForCart($cartId, $partnerId);
                if ($placedOrderId > 0) {
                    $orderId = $placedOrderId;
                    $cartId = 0;
                } else {
                    $orderId = 0; // force cart-details path (still draft)
                }
            } elseif ($cartRow && $realOrder) {
                // Same numeric id exists in both tables — prefer cart if place-pending.
                if (
                    app(PartnerCartService::class)->isCartPendingAadhaar($orderId)
                    || strtolower(trim((string) ($cartRow->status ?? ''))) === 'active'
                ) {
                    $hasItems = CartItem::query()->where('cart_id', $orderId)->exists();
                    if ($hasItems) {
                        $cartId = $orderId;
                        if ($partnerId <= 0) {
                            $partnerId = (int) ($cartRow->partner_id ?? 0);
                        }
                        if (strtolower(trim((string) ($cartRow->status ?? ''))) !== 'converted') {
                            $orderId = 0;
                        }
                    }
                }
            } elseif (!$cartRow && $realOrder && $partnerId <= 0) {
                $partnerId = (int) ($realOrder->partner_id ?? 0);
            } elseif ($partnerId <= 0 && $realOrder) {
                $partnerId = (int) ($realOrder->partner_id ?? 0);
            }
        }

        // Explicit cart_id after place → upgrade to real order id.
        if ($cartId > 0 && $orderId < 1) {
            $placedOrderId = $this->resolvePlacedOrderIdForCart($cartId, $partnerId);
            if ($placedOrderId > 0) {
                $orderId = $placedOrderId;
            }
        }

        if ($partnerId > 0 && $orderId < 1 && $cartId <= 0) {
            $cartId = $this->resolvePendingCartIdForPartner($partnerId);
        }

        if ($partnerId > 0) {
            $request->merge(['partner_id' => $partnerId]);
        }
        if ($cartId > 0) {
            $request->merge(['cart_id' => $cartId, 'main_cart_id' => $cartId]);
        }
        $request->merge(['order_id' => $orderId, 'ordId' => $orderId > 0 ? $orderId : $cartId]);

        $validated = $request->validate([
            'partner_id' => ['nullable', 'integer', 'min:1'],
            'order_id' => ['nullable', 'integer', 'min:0'],
            'cart_id' => ['nullable', 'integer', 'min:1'],
            'main_cart_id' => ['nullable', 'integer', 'min:1'],
            'pending_ref_id' => ['nullable', 'string', 'max:64'],
            'temp_order_no' => ['nullable', 'string', 'max:64'],
            'order_no' => ['nullable', 'string', 'max:64'],
        ]);

        $partnerId = (int) ($validated['partner_id'] ?? $partnerId);
        $orderId = (int) ($validated['order_id'] ?? $orderId);
        $cartId = (int) ($validated['cart_id'] ?? $validated['main_cart_id'] ?? $cartId);

        // Re-apply cart-as-ordId after validation (validated order_id may restore cart id).
        if ($orderId > 0 && $cartId <= 0) {
            $cartRow = $this->carts->newQuery()->whereKey($orderId)->first();
            if ($cartRow && !$this->orders->newQuery()->whereKey($orderId)->exists()) {
                $cartId = $orderId;
                if ($partnerId <= 0) {
                    $partnerId = (int) ($cartRow->partner_id ?? 0);
                }
                $orderId = 0;
            }
        }
        if ($orderId > 0 && $cartId === $orderId) {
            if (!$this->orders->newQuery()->whereKey($orderId)->exists()) {
                $orderId = 0;
            }
        }

        if ($partnerId <= 0 && $cartId > 0) {
            $partnerId = (int) ($this->carts->newQuery()->whereKey($cartId)->value('partner_id') ?? 0);
        }

        if ($partnerId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'The partner id field is required.',
                'errors' => ['partner_id' => ['Send partner_id, Bearer token, cart_id, or pending_ref_id.']],
                'data' => null,
            ], 422);
        }

        // Resolve cart from pending PND ref when order is not placed yet.
        if ($orderId < 1 && $cartId <= 0) {
            $ref = trim((string) ($validated['pending_ref_id'] ?? $validated['temp_order_no'] ?? $validated['order_no'] ?? ''));
            if ($ref === '') {
                $ref = trim((string) ($request->input('order_no') ?? ''));
            }
            if ($ref !== '' && app(PartnerCartService::class)->isPendingEsignRef($ref)) {
                $resolved = app(PartnerCartService::class)->resolvePendingEsignRef($ref);
                if ($resolved !== null) {
                    $cartId = (int) ($resolved['cart_id'] ?? 0);
                    if ((int) ($resolved['partner_id'] ?? 0) > 0) {
                        $partnerId = (int) $resolved['partner_id'];
                    }
                }
            } elseif ($ref !== '') {
                $byNo = $this->orders->newQuery()
                    ->where('partner_id', $partnerId)
                    ->where('order_no', $ref)
                    ->orderByDesc('id')
                    ->first();
                if ($byNo) {
                    $orderId = (int) $byNo->id;
                }
            }
        }

        if ($orderId < 1 && $cartId <= 0) {
            $cartId = $this->resolvePendingCartIdForPartner($partnerId);
        }

        // Place-pending / draft PO in cart — no real orders row yet.
        if ($orderId < 1) {
            if ($cartId > 0) {
                $placedOrderId = $this->resolvePlacedOrderIdForCart($cartId, $partnerId);
                if ($placedOrderId > 0) {
                    $orderId = $placedOrderId;
                } else {
                    return $this->cartPendingOrderDetails($partnerId, $cartId);
                }
            } else {
                return $this->cartPendingOrderDetails($partnerId, $cartId);
            }
        }

        $order = $this->orders->newQuery()->whereKey($orderId)->where('partner_id', $partnerId)->first();

        if (!$order) {
            // order_id was cart id, or partner mismatch on order — try cart without partner filter.
            $asCart = $this->carts->newQuery()->whereKey($orderId)->first()
                ?? ($cartId > 0 ? $this->carts->newQuery()->whereKey($cartId)->first() : null);
            if ($asCart) {
                return $this->cartPendingOrderDetails(
                    (int) ($asCart->partner_id ?? $partnerId),
                    (int) $asCart->id
                );
            }

            return response()->json([
                'success' => false,
                'message' => 'Order not found.',
                'data' => [
                    'order_id' => $orderId,
                    'cart_id' => $cartId,
                    'hint' => 'For drafts, ordId is cart_id. Regenerate PO if cart was cleared.',
                ],
            ], 404);
        }

        $orderItems = $this->orderItems->newQuery()
            ->where('order_id', $orderId)
            ->orderBy('id')
            ->get();
        $planIds = $orderItems->pluck('plan_id')
            ->push($order->plan_id)
            ->filter()
            ->map(fn($planId) => (int) $planId)
            ->unique()
            ->values();
        $plansById = $this->plans->newQuery()
            ->whereIn('id', $planIds)
            ->get()
            ->keyBy('id');

        $customer = !empty($order->user_id)
            ? $this->customers->newQuery()->whereKey((int) $order->user_id)->first()
            : null;
        $companyName = trim((string) ($customer->company_name ?? ''));

        $orderNo = trim((string) ($order->order_no ?? ''));
        if ($orderNo === '') {
            $orderNo = (string) $orderId;
        }

        $domainName = $this->resolveDomainNameForOrder(
            $order,
            (int) ($order->cart_id ?? $order->source_cart_id ?? $cartId ?: 0)
        );
        if ($domainName !== '') {
            OrderDomainHelper::persistOnOrderIfMissing((int) $order->id, $domainName);
        }

        $plans = [];
        if ($orderItems->isEmpty()) {
            $planId = (int) ($order->plan_id ?? 0);
            $plan = $plansById->get($planId);
            $billingPayload = $this->resolveOrderBillingCyclePayload($order, $plan);
            $startLabel = (string) ($billingPayload['subscription_start_date'] ?? '');
            $endLabel = (string) ($billingPayload['subscription_end_date'] ?? '');
            $billingCycle = (string) ($billingPayload['billing_cycle']
                ?? $this->resolveBillingCycleLabel($plan, $order));
            $plans[] = [
                'provider_id' => (int) ($plan->provider_id ?? 0) ?: null,
                'plan_id' => $planId ?: null,
                'plan_name' => (string) ($order->plan_name ?? $plan->name ?? $plan->title ?? 'Plan'),
                'licenses' => max(1, (int) ($order->quantity ?? 1)),
                'unit_price' => round((float) ($order->price ?? 0), 2),
                'plan_amount' => round((float) ($order->subtotal ?? $order->total_amount ?? 0), 2),
                'status' => (string) ($order->status ?? ''),
                'company_name' => $companyName,
                'domain_name' => $domainName,
                'domain' => $domainName,
                'order_id' => $orderId,
                'order_sub_id' => null,
                'order_no' => $orderNo,
                'subscription_id' => '',
                'subscription_no' => '',
                'billing_cycle' => $billingCycle,
                'duration' => $billingCycle,
                'subscription_start_date' => $startLabel !== '' ? $startLabel : null,
                'subscription_end_date' => $endLabel !== '' ? $endLabel : null,
                'start_date' => $startLabel !== '' ? $startLabel : null,
                'end_date' => $endLabel !== '' ? $endLabel : null,
            ];
        } else {
            foreach ($orderItems as $item) {
                $planId = (int) ($item->plan_id ?? $order->plan_id ?? 0);
                $plan = $plansById->get($planId);
                $subscriptionId = trim((string) ($item->subscription_id ?? ''));
                if ($subscriptionId === '') {
                    $subscriptionId = $this->ensureOrderItemSubscriptionId($item);
                }
                // Normalize "SUB 000175" / "SUB000175" → "SUB-000175" for UI.
                $subscriptionId = $this->normalizeSubscriptionIdDisplay($subscriptionId);
                $billingPayload = $this->resolveOrderBillingCyclePayload($order, $plan);
                $startLabel = (string) ($billingPayload['subscription_start_date'] ?? '');
                $endLabel = (string) ($billingPayload['subscription_end_date'] ?? '');
                $billingCycle = (string) ($billingPayload['billing_cycle']
                    ?: $this->resolveBillingCycleLabel($plan, $order));
                $plans[] = [
                    'provider_id' => (int) ($plan->provider_id ?? 0) ?: null,
                    'plan_id' => $planId ?: null,
                    'plan_name' => (string) ($item->item_name ?? $order->plan_name ?? $plan->name ?? $plan->title ?? 'Plan'),
                    'licenses' => max(1, (int) ($item->quantity ?? $order->quantity ?? 1)),
                    'unit_price' => round((float) ($item->unit_price ?? $order->price ?? 0), 2),
                    'plan_amount' => round((float) ($item->total_amount ?? 0), 2),
                    'status' => (string) ($item->status ?? $order->status ?? ''),
                    'company_name' => $companyName,
                    'domain_name' => $domainName,
                    'domain' => $domainName,
                    'order_id' => $orderId,
                    'order_sub_id' => $item->id,
                    'order_no' => $orderNo,
                    'subscription_id' => $subscriptionId,
                    'subscription_no' => $subscriptionId,
                    'billing_cycle' => $billingCycle,
                    'duration' => $billingCycle,
                    'subscription_start_date' => $startLabel !== '' ? $startLabel : null,
                    'subscription_end_date' => $endLabel !== '' ? $endLabel : null,
                    'start_date' => $startLabel !== '' ? $startLabel : null,
                    'end_date' => $endLabel !== '' ? $endLabel : null,
                ];
            }
        }

        $poRow = $this->orderPoDetails->newQuery()
            ->where('order_id', $orderId)
            ->first();
        $finalPoLink = $poRow?->final_po_link
            ?? $poRow?->po_document_url
            ?? $order->po_document_url
            ?? null;
        $poNumberClean = $this->cleanPoNumberDisplay((string) ($poRow?->po_number ?? ''));
        $poDetails = [
            'po_status' => (string) ($poRow?->status ?? $order->status ?? ''),
            'po_number' => $poNumberClean,
            'final_po_link' => $finalPoLink,
            'po_link' => $poRow?->po_document_url ?? $finalPoLink,
        ];

        $rootSubscriptionId = $this->normalizeSubscriptionIdDisplay(
            trim((string) ($plans[0]['subscription_id'] ?? ''))
        );
        $rootBillingCycle = (string) ($plans[0]['billing_cycle'] ?? $this->resolveBillingCycleLabel(null, $order));
        $rootStart = (string) ($plans[0]['subscription_start_date'] ?? '');
        $rootEnd = (string) ($plans[0]['subscription_end_date'] ?? '');

        // Category = upgrade/renew/new label; type = New Service vs Transfer Service only.
        $categoryProbe = clone $order;
        $categoryProbe->transfer_domain = null;
        if (property_exists($categoryProbe, 'domain_transfer') || isset($categoryProbe->domain_transfer)) {
            $categoryProbe->domain_transfer = null;
        }
        $enrollmentCategory = $this->resolveEnrollmentType($categoryProbe, (string) ($order->order_category ?? ''));
        $transferFlag = strtolower(trim((string) (
            $order->transfer_domain
            ?? $order->domain_transfer
            ?? ''
        )));
        $enrollmentType = in_array($transferFlag, ['yes', '1', 'true'], true)
            ? 'Transfer Service'
            : 'New Service';
        $uiDates = $this->formatOrderDateFields($order->created_at ?? null);
        $poDateRaw = $poRow?->po_date ?? $order->created_at ?? null;
        $poDateLabel = $this->formatDisplayDate(
            is_object($poDateRaw) && method_exists($poDateRaw, 'format')
                ? $poDateRaw->format('Y-m-d')
                : (string) ($poDateRaw ?? '')
        );
        if ($poDateLabel === '') {
            $poDateLabel = (string) ($uiDates['order_date'] ?? '');
        }

        // Mirror plans into orders[] — some FE screens bind Purchase Snapshot from orders[0].
        $ordersOut = [];
        foreach ($plans as $planRow) {
            $provider_id = $this->providers->newQuery()->whereKey($planRow['plan_id'])->value('id');
            $ordersOut[] = [
                'order_id' => $orderId,
                'ordId' => $orderId,
                'order_sub_id' => $planRow['order_sub_id'] ?? null,
                'order_no' => $orderNo,
                'provider_id' => $provider_id,
                'plan_id' => $planRow['plan_id'] ?? null,
                'plan_name' => $planRow['plan_name'] ?? 'Plan',
                'licenses' => $planRow['licenses'] ?? 1,
                'plan_amount' => $planRow['plan_amount'] ?? 0,
                'status' => $planRow['status'] ?? (string) ($order->status ?? ''),
                'company_name' => $companyName,
                'domain_name' => $domainName !== '' ? $domainName : ($planRow['domain_name'] ?? ''),
                'domain' => $domainName !== '' ? $domainName : ($planRow['domain_name'] ?? ''),
                'subscription_id' => (string) ($planRow['subscription_id'] ?? ''),
                'subscription_no' => (string) ($planRow['subscription_no'] ?? $planRow['subscription_id'] ?? ''),
                'billing_cycle' => (string) ($planRow['billing_cycle'] ?? $rootBillingCycle),
                'duration' => (string) ($planRow['duration'] ?? $rootBillingCycle),
                'subscription_start_date' => $planRow['subscription_start_date'] ?? null,
                'subscription_end_date' => $planRow['subscription_end_date'] ?? null,
                'order_date' => $uiDates['order_date'] ?? null,
            ];
        }

        $discountAmount = round((float) ($order->discount_amount ?? 0), 2);
        $subtotalAmount = round((float) ($order->subtotal ?? $order->total_amount ?? 0), 2);
        $gstAmount = round((float) ($order->gst_amount ?? 0), 2);
        $totalAmount = round((float) ($order->total_amount ?? 0), 2);
        $customerName = trim((string) ($customer->name ?? ''));
        $documentLabel = $poNumberClean;
        $invoice = InvoiceMst::query()->where('order_id', $orderId)->first();
        $gstBreakdown = $this->resolveOrderGstBreakdown($order, $customer, $poRow, $invoice, $gstAmount);
        $paymentBreakdown = GstSplit::paymentBreakdownPayload(
            $subtotalAmount,
            $gstBreakdown,
            $discountAmount,
            $totalAmount > 0 ? $totalAmount : null,
            ['coupon' => strtoupper(trim((string) ($order->promo_code ?? '')))]
        );

        return response()->json([
            'success' => true,
            'message' => 'Order details fetched successfully.',
            'data' => [
                'provider_id' => $plans[0]['provider_id'] ?? null,
                'order_id' => $orderId,
                'ordId' => $orderId,
                'id' => $orderId,
                'partner_id' => $partnerId,
                'order_no' => $orderNo,
                'orderNo' => $orderNo,
                'status' => (string) ($order->status ?? ''),
                'order_date' => $uiDates['order_date'] ?? null,
                'order_date_iso' => $uiDates['order_date_iso'] ?? null,
                'orderDate' => $uiDates['orderDate'] ?? null,
                'created_at' => $uiDates['created_at'] ?? null,
                'order_type' => (string) ($order->order_type ?? $order->order_category ?? ''),
                'enrollment_category' => $enrollmentCategory,
                'enrollment_type' => $enrollmentType,
                'domain_name' => $domainName,
                'domain' => $domainName,
                'subscription_id' => $rootSubscriptionId,
                'subscription_no' => $rootSubscriptionId,
                'subscriptionId' => $rootSubscriptionId,
                'billing_cycle' => $rootBillingCycle,
                'billingCycle' => $rootBillingCycle,
                'duration' => $rootBillingCycle,
                'subscription_start_date' => $rootStart !== '' ? $rootStart : null,
                'subscription_end_date' => $rootEnd !== '' ? $rootEnd : null,
                'start_date' => $rootStart !== '' ? $rootStart : null,
                'end_date' => $rootEnd !== '' ? $rootEnd : null,
                'customer' => [
                    'customer_id' => $customer->id ?? null,
                    'customer_no' => $customer->customer_no ?? null,
                    'name' => $customer->name ?? null,
                    'company_name' => $customer->company_name ?? null,
                    'email' => $customer->email ?? null,
                    'mobile' => $customer->mobile ?? null,
                ],
                'customer_name' => $customerName !== '' ? $customerName : null,
                'company_name' => $companyName !== '' ? $companyName : null,
                'plans' => $plans,
                'orders' => $ordersOut,
                'aadhaar_verification_id' => $order->aadhaar_verification_id ?? null,
                'aadhaar_masked' => (string) ($poRow?->aadhaar_masked ?? ''),
                'po_status' => $poDetails['po_status'],
                'po_number' => $poNumberClean,
                'po_date' => $poDateLabel !== '' ? $poDateLabel : null,
                'po_document_name' => $documentLabel,
                'document_name' => $documentLabel,
                'document_label' => $documentLabel,
                'po_label' => $documentLabel,
                'documents' => $poNumberClean !== '' || !empty($poDetails['po_link']) ? [[
                    'type' => 'purchase_order',
                    'title' => 'Purchase Order',
                    'name' => $documentLabel,
                    'label' => $documentLabel,
                    'po_number' => $poNumberClean,
                    'link' => $poDetails['po_link'] ?? $finalPoLink,
                    'url' => $poDetails['po_link'] ?? $finalPoLink,
                ]] : [],
                'final_po_link' => $poDetails['final_po_link'],
                'po_link' => $poDetails['po_link'],
                'po_details' => $poDetails,
                'gst' => round((float) ($order->gst_rate ?? 0), 2),
                'discount_amount' => round((float) ($order->discount_amount ?? 0), 2),
                'subtotal' => round((float) ($order->subtotal ?? $order->total_amount ?? 0), 2),
                'gst_amount' => round((float) ($order->gst_amount ?? 0), 2),
                'total_amount' => round((float) ($order->total_amount ?? 0), 2),
                'plan_amount' => round((float) ($order->subtotal ?? $order->total_amount ?? 0), 2),
                'cart_id' => (int) ($order->cart_id ?? $cartId ?: 0) ?: null,
                'place_order_required' => false,
                'is_temp_order' => false,
                'real_order' => true,
            ],
        ]);
    }

    /**
     * Human-readable billing cycle for order-details / order-complete UI.
     */
    protected function resolveBillingCycleLabel($plan = null, $order = null): string
    {
        $raw = trim((string) ($plan->billing_cycle ?? ''));
        if ($raw === '') {
            $raw = trim((string) ($order->duration ?? ''));
        }
        if ($raw === '') {
            return '12 Months';
        }

        $normalized = strtolower(preg_replace('/\s+/', ' ', $raw) ?? $raw);
        if (
            $normalized === 'yearly'
            || $normalized === 'annual'
            || $normalized === 'annually'
            || preg_match('/^\s*1\s*year/i', $normalized)
            || preg_match('/^\s*12\s*months?\s*$/i', $normalized)
        ) {
            return '12 Months';
        }
        if ($normalized === 'monthly' || preg_match('/^\s*1\s*months?\s*$/i', $normalized)) {
            return '1 Month';
        }
        if (preg_match('/^(\d+)\s*months?$/i', $normalized, $m)) {
            $n = (int) $m[1];

            return $n === 1 ? '1 Month' : ($n . ' Months');
        }

        // Keep plan/order text when already display-friendly.
        return $raw;
    }

    /**
     * Backfill SUB-###### when place created the item without a subscription_id.
     */
    protected function ensureOrderItemSubscriptionId($orderItem): string
    {
        $existing = trim((string) ($orderItem->subscription_id ?? ''));
        if ($existing !== '') {
            return $this->normalizeSubscriptionIdDisplay($existing);
        }

        $itemId = (int) ($orderItem->id ?? 0);
        if ($itemId <= 0) {
            return '';
        }

        $subscriptionPrefix = strtoupper(trim((string) (
            $this->adminSettings->newQuery()->orderBy('id')->value('subscription_prefix') ?? 'SUB'
        ))) ?: 'SUB';
        $subscriptionId = $subscriptionPrefix . '-' . str_pad((string) $itemId, 6, '0', STR_PAD_LEFT);

        try {
            $orderItem->subscription_id = $subscriptionId;
            $orderItem->save();
        } catch (\Throwable $e) {
            Log::warning('ensureOrderItemSubscriptionId_failed', [
                'order_item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
        }

        return $subscriptionId;
    }

    /**
     * Normalize subscription ids for UI (SUB-000175).
     */
    protected function normalizeSubscriptionIdDisplay(string $subscriptionId): string
    {
        $subscriptionId = trim($subscriptionId);
        if ($subscriptionId === '') {
            return '';
        }

        // "SUB 000175" / "SUB000175" / "sub-000175" → "SUB-000175"
        if (preg_match('/^([A-Za-z]+)\s*-?\s*(\d+)$/', $subscriptionId, $m)) {
            return strtoupper($m[1]) . '-' . str_pad($m[2], 6, '0', STR_PAD_LEFT);
        }

        return $subscriptionId;
    }

    /**
     * Billing cycle payload for placed orders: "10 Aug 2026 To 09 Aug 2027" + dates.
     *
     * @return array{
     *   billing_cycle: string,
     *   subscription_start_date: ?string,
     *   subscription_end_date: ?string,
     *   start_date: ?string,
     *   end_date: ?string,
     *   subscription_start_date_iso: ?string,
     *   subscription_end_date_iso: ?string
     * }
     */
    protected function resolveOrderBillingCyclePayload($order = null, $plan = null): array
    {
        $startRaw = null;
        $endRaw = null;

        if ($order) {
            $startRaw = $order->subscription_start_date ?? null;
            $endRaw = $order->subscription_end_date ?? null;
            if (is_object($startRaw) && method_exists($startRaw, 'format')) {
                $startRaw = $startRaw->format('Y-m-d');
            }
            if (is_object($endRaw) && method_exists($endRaw, 'format')) {
                $endRaw = $endRaw->format('Y-m-d');
            }
        }

        if (!$plan && $order && (int) ($order->plan_id ?? 0) > 0) {
            $plan = $this->plans->newQuery()->whereKey((int) $order->plan_id)->first();
        }

        // Always apply provider end rule when plan is known:
        // Google = same anniversary date; Tizzy/Microsoft = one day before.
        if ($plan) {
            $start = Carbon::now()->startOfDay();
            if ($startRaw !== null && $startRaw !== '') {
                try {
                    $startIso = $this->toIsoDateString((string) $startRaw);
                    $start = $startIso !== ''
                        ? Carbon::createFromFormat('Y-m-d', $startIso)->startOfDay()
                        : Carbon::now()->startOfDay();
                } catch (\Throwable $e) {
                    $start = Carbon::now()->startOfDay();
                }
            }
            $end = app(PartnerCartService::class)->computeSubscriptionEnd($start, $plan);
            $startRaw = $start->format('Y-m-d');
            $endRaw = $end->format('Y-m-d');
        } elseif ($startRaw === null || $startRaw === '' || $endRaw === null || $endRaw === '') {
            // No plan — keep whatever dates we have; cannot apply provider rule.
            if ($startRaw === null || $startRaw === '') {
                $startRaw = Carbon::now()->format('Y-m-d');
            }
            if ($endRaw === null || $endRaw === '') {
                try {
                    $startIso = $this->toIsoDateString((string) $startRaw);
                    $endRaw = $startIso !== ''
                        ? Carbon::createFromFormat('Y-m-d', $startIso)->addYear()->subDay()->format('Y-m-d')
                        : Carbon::now()->addYear()->subDay()->format('Y-m-d');
                } catch (\Throwable $e) {
                    $endRaw = Carbon::now()->addYear()->subDay()->format('Y-m-d');
                }
            }
        }

        $startIso = $this->toIsoDateString($startRaw !== null ? (string) $startRaw : null);
        $endIso = $this->toIsoDateString($endRaw !== null ? (string) $endRaw : null);
        $startLabel = $this->formatDisplayDate($startIso ?: ($startRaw !== null ? (string) $startRaw : null));
        $endLabel = $this->formatDisplayDate($endIso ?: ($endRaw !== null ? (string) $endRaw : null));
        $range = $this->formatBillingCycleDateRange($startLabel, $endLabel);
        if ($range === '') {
            $range = $this->resolveBillingCycleLabel($plan, $order);
        }

        return [
            'billing_cycle' => $range,
            'subscription_start_date' => $startLabel !== '' ? $startLabel : null,
            'subscription_end_date' => $endLabel !== '' ? $endLabel : null,
            'start_date' => $startLabel !== '' ? $startLabel : null,
            'end_date' => $endLabel !== '' ? $endLabel : null,
            'subscription_start_date_iso' => $startIso !== '' ? $startIso : null,
            'subscription_end_date_iso' => $endIso !== '' ? $endIso : null,
        ];
    }

    /**
     * Partner UI enrollment label (Review Order / order-details).
     * Upgrade → Upgraded, Downgrade → Downgraded, Renew → Renewed,
     * Transfer → Transfer Service, else New Service.
     *
     * @param  object|null  $order  Order or Cart row (uses order_category/type + transfer_domain).
     */
    protected function resolveEnrollmentType($order = null, string $hint = ''): string
    {
        // transfer_domain = yes → always 'Transfer', regardless of category.
        $transferDomain = strtolower(trim((string) ($order->transfer_domain ?? '')));
        if (in_array($transferDomain, ['yes', '1', 'true'], true)) {
            return 'Transfer';
        }

        // No transfer_domain match — fall through to category / hint based logic.
        $category = strtolower(trim($hint));
        if ($category === '' && $order) {
            $category = strtolower(trim((string) ($order->order_category ?? '')));
        }
        if ($category === '' && $order) {
            $category = strtolower(trim((string) ($order->type ?? $order->order_type ?? '')));
        }
        // Normalize common flow aliases from carts / place payloads.
        if (in_array($category, ['standard', 'new', 'new_service'], true)) {
            $category = 'new';
        }

        if (
            in_array($category, ['partial_upgrade', 'partial'], true)
            || $this->isPartialUpgradeContext($order, $category)
        ) {
            return 'Partial Upgrade';
        }
        if (in_array($category, ['upgrade', 'upgraded'], true)) {
            return 'Upgraded';
        }
        if (in_array($category, ['downgrade', 'downgraded', 'partial_downgrade'], true)) {
            return 'Downgraded';
        }
        if (in_array($category, ['renew', 'renewal', 'renewed'], true)) {
            return 'Renewed';
        }
        if (in_array($category, ['transfer', 'transfer_service'], true)) {
            return 'Transfer';
        }

        return 'New Service';
    }

    /**
     * Draft cart enrollment: same plan + license change = Partial Upgrade.
     */
    protected function cartEnrollmentType(?Cart $cart): string
    {
        if (!$cart) {
            return 'New Service';
        }

        $cart->loadMissing(['items']);
        $type = strtolower(trim((string) ($cart->type ?? '')));
        if ($type === 'downgrade') {
            return 'Downgraded';
        }
        if (in_array($type, ['renew', 'renewal'], true)) {
            return 'Renewed';
        }
        if (!in_array($type, ['upgrade', 'partial_upgrade'], true)) {
            return $this->resolveEnrollmentType($cart, $type);
        }

        if ($this->resolveUpgradeModeFromCart($cart) === 'partial') {
            return 'Partial Upgrade';
        }

        $cartPlanId = (int) ($cart->items->first()->plan_id ?? 0);
        $sourcePlanId = $this->resolveUpgradeSourcePlanIdFromCart($cart);
        if ($cartPlanId > 0 && $sourcePlanId > 0) {
            return $cartPlanId === $sourcePlanId ? 'Partial Upgrade' : 'Upgraded';
        }

        return 'Partial Upgrade';
    }

    /**
     * Same-plan license increase (or subset seats) = partial upgrade, not full plan change.
     */
    protected function isPartialUpgradeContext($order = null, string $category = ''): bool
    {
        if (!$order) {
            return false;
        }

        $category = strtolower(trim($category !== '' ? $category : (string) ($order->order_category ?? $order->type ?? '')));
        $cartType = strtolower(trim((string) ($order->type ?? '')));
        $orderNo = strtoupper(trim((string) ($order->order_no ?? '')));
        $isUpgradeFlow = in_array($category, ['upgrade', 'upgraded', 'partial_upgrade', 'partial'], true)
            || $cartType === 'upgrade'
            || str_starts_with($orderNo, 'CH');

        if (!$isUpgradeFlow) {
            return false;
        }

        if ($order instanceof Cart || (isset($order->items) && $cartType === 'upgrade')) {
            try {
                if ($this->resolveUpgradeModeFromCart($order instanceof Cart ? $order : null) === 'partial') {
                    return true;
                }
            } catch (\Throwable $e) {
                // fall through to plan-id check
            }
        }

        $prev = (int) ($order->previous_plan_id ?? 0);
        $curr = (int) ($order->plan_id ?? 0);
        if ($prev > 0 && $curr > 0 && $prev === $curr) {
            return true;
        }

        $parentSubId = (int) ($order->parent_order_sub_id ?? 0);
        if ($parentSubId > 0 && $curr > 0) {
            $sourcePlan = (int) (OrderItem::query()->whereKey($parentSubId)->value('plan_id') ?? 0);
            if ($sourcePlan > 0 && $sourcePlan === $curr) {
                return true;
            }
        }

        $parentId = (int) ($order->parent_order_id ?? 0);
        if ($parentId > 0 && $curr > 0) {
            $parentHasSamePlan = OrderItem::query()
                ->where('order_id', $parentId)
                ->where('plan_id', $curr)
                ->where(function ($q) {
                    $q->whereNull('status')->orWhereRaw("LOWER(TRIM(COALESCE(status, ''))) != 'delete'");
                })
                ->exists();
            if ($parentHasSamePlan) {
                return true;
            }
            $parentPlan = (int) (Order::query()->whereKey($parentId)->value('plan_id') ?? 0);
            if ($parentPlan > 0 && $parentPlan === $curr) {
                return true;
            }
        }

        return false;
    }

    /**
     * Domain for order-complete blue bar — order first, then linked cart.
     */
    protected function resolveDomainNameForOrder($order = null, int $cartId = 0): string
    {
        return OrderDomainHelper::resolveForOrder($order, $cartId);
    }

    /**
     * Normalize any common date string to Y-m-d (empty if unparseable).
     */
    protected function toIsoDateString(?string $raw): string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw)) {
                return substr($raw, 0, 10);
            }
            if (preg_match('/^\d{1,2}-\d{1,2}-\d{4}$/', $raw)) {
                return Carbon::createFromFormat('d-m-Y', $raw)->format('Y-m-d');
            }
            if (preg_match('/^\d{1,2}\s+[A-Za-z]{3,}\s+\d{4}$/', $raw)) {
                return Carbon::parse($raw)->format('Y-m-d');
            }

            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable $e) {
            return '';
        }
    }
    protected function draftDummySubscriptionId(int $cartId, int $cartItemId = 0): string
    {
        $subscriptionPrefix = strtoupper(trim((string) (
            $this->adminSettings->newQuery()->orderBy('id')->value('subscription_prefix') ?? 'SUB'
        ))) ?: 'SUB';
        $seed = $cartItemId > 0 ? $cartItemId : max(0, $cartId);

        return $subscriptionPrefix . '-TEMP-' . str_pad((string) $seed, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Billing cycle UI label as "10 Aug 2026 To 09 Aug 2027" (unambiguous).
     */
    protected function formatBillingCycleDateRange(?string $start, ?string $end): string
    {
        $startLabel = $this->formatDisplayDate($start);
        $endLabel = $this->formatDisplayDate($end);
        if ($startLabel === '' || $endLabel === '') {
            return '';
        }

        return $startLabel . ' To ' . $endLabel;
    }

    /**
     * Unambiguous display date: "10 Aug 2026".
     * Avoids d-m-Y / m-d-Y mix-ups that show "08 Oct" for 10 Aug.
     */
    protected function formatDisplayDate(?string $raw): string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }

        // Already in "10 Aug 2026" form.
        if (preg_match('/^\d{1,2}\s+[A-Za-z]{3,}\s+\d{4}$/', $raw)) {
            try {
                return Carbon::parse($raw)->format('d M Y');
            } catch (\Throwable $e) {
                return $raw;
            }
        }

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw)) {
                $dt = Carbon::createFromFormat('Y-m-d', substr($raw, 0, 10));
            } elseif (preg_match('/^\d{1,2}-\d{1,2}-\d{4}$/', $raw)) {
                // Prefer day-month-year (India) over US month-day-year.
                $dt = Carbon::createFromFormat('d-m-Y', $raw);
            } else {
                $dt = Carbon::parse($raw);
            }

            return $dt ? $dt->format('d M Y') : $raw;
        } catch (\Throwable $e) {
            return $raw;
        }
    }

    /**
     * Strip accidental "PON00157 - -" style suffixes from PO labels.
     */
    protected function cleanPoNumberDisplay(?string $poNumber): string
    {
        $po = trim((string) $poNumber);
        if ($po === '') {
            return '';
        }

        // Remove trailing " -" / " - -" / " --" junk from FE/legacy joins.
        $po = preg_replace('/(?:\s*-\s*)+$/', '', $po) ?? $po;

        return trim($po);
    }

    /**
     * @return array{order_date:?string,order_date_iso:?string,orderDate:?string,created_at:?string}
     */
    protected function formatOrderDateFields($createdAt): array
    {
        if ($createdAt === null || $createdAt === '') {
            return [
                'order_date' => null,
                'order_date_iso' => null,
                'orderDate' => null,
                'created_at' => null,
            ];
        }

        try {
            $dt = $createdAt instanceof Carbon
                ? $createdAt
                : Carbon::parse((string) $createdAt);
        } catch (\Throwable $e) {
            return [
                'order_date' => null,
                'order_date_iso' => null,
                'orderDate' => null,
                'created_at' => null,
            ];
        }

        return [
            'order_date' => $dt->format('d M Y'),
            'order_date_iso' => $dt->format('Y-m-d'),
            'orderDate' => $dt->format('d M Y'),
            'created_at' => $dt->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * After Place Order, FE may still call details with cart id (ordId=223).
     * Resolve the real orders.id linked to that cart.
     */
    protected function resolvePlacedOrderIdForCart(int $cartId, int $partnerId = 0): int
    {
        if ($cartId <= 0) {
            return 0;
        }

        $query = $this->orders->newQuery()->orderByDesc('id');
        if ($partnerId > 0) {
            $query->where('partner_id', $partnerId);
        }

        if (Schema::connection('pgsql_billing')->hasColumn('orders', 'cart_id')) {
            $id = (int) (clone $query)->where('cart_id', $cartId)->value('id');
            if ($id > 0) {
                return $id;
            }
        }

        // Cache link from draft/place flow.
        $cached = (int) Cache::get('cart_draft_order_' . $cartId, 0);
        if ($cached > 0) {
            $exists = $this->orders->newQuery()->whereKey($cached)->exists();
            if ($exists) {
                return $cached;
            }
        }

        $cartStatus = strtolower(trim((string) (
            $this->carts->newQuery()->whereKey($cartId)->value('status') ?? ''
        )));
        // Renew/upgrade store parent subscription on carts.order_id — never treat that as the placed order.
        if (
            $cartStatus === 'converted'
            && Schema::connection('pgsql_billing')->hasColumn('carts', 'order_id')
            && !$this->cartKeepsParentOrderLink($cartId)
        ) {
            $linked = (int) ($this->carts->newQuery()->whereKey($cartId)->value('order_id') ?? 0);
            if ($linked > 0 && $this->orders->newQuery()->whereKey($linked)->exists()) {
                return $linked;
            }
        }

        return 0;
    }

    /**
     * Partner id from Bearer token (pgsql_auth), cart, or request aliases.
     */
    protected function resolvePartnerIdFromRequest(Request $request): ?int
    {
        $user = $request->user();
        if ($user instanceof Partner) {
            return (int) $user->id;
        }

        $token = $request->bearerToken();
        if ($token === null || $token === '') {
            $auth = (string) $request->header('Authorization', '');
            if (preg_match('/^\s*Bearer\s+(\S+)/i', $auth, $m)) {
                $token = $m[1];
            }
        }
        if ($token === null || $token === '') {
            foreach (['token', 'access_token', 'api_token', 'auth_token'] as $key) {
                $raw = $request->input($key, $request->query($key));
                if (is_string($raw) && trim($raw) !== '') {
                    $token = trim($raw);
                    break;
                }
            }
        }
        if ($token) {
            $accessToken = PersonalAccessToken::findToken($token);
            if ($accessToken && $accessToken->tokenable instanceof Partner) {
                return (int) $accessToken->tokenable->id;
            }
            if ($accessToken && (int) ($accessToken->tokenable_id ?? 0) > 0) {
                $type = (string) ($accessToken->tokenable_type ?? '');
                if ($type === '' || str_contains($type, 'Partner')) {
                    return (int) $accessToken->tokenable_id;
                }
            }
        }

        foreach (['partner_id', 'partnerId', 'partnerID'] as $key) {
            $raw = $request->input($key, $request->query($key));
            if ($raw !== null && $raw !== '' && (int) $raw > 0) {
                return (int) $raw;
            }
        }

        $headerPartner = (int) $request->header('X-Partner-Id', 0);
        if ($headerPartner > 0) {
            return $headerPartner;
        }

        $nested = (int) data_get($request->all(), 'data.partner_id', 0);
        if ($nested > 0) {
            return $nested;
        }

        $cartId = (int) ($request->input('main_cart_id') ?? $request->input('cart_id') ?? $request->query('main_cart_id') ?? $request->query('cart_id') ?? 0);
        if ($cartId > 0) {
            $fromCart = (int) ($this->carts->newQuery()->whereKey($cartId)->value('partner_id') ?? 0);
            if ($fromCart > 0) {
                return $fromCart;
            }
        }

        return null;
    }

    /**
     * Latest place-pending cart for partner, else latest active cart with items.
     */
    protected function resolvePendingCartIdForPartner(int $partnerId): int
    {
        if ($partnerId <= 0) {
            return 0;
        }

        $cartService = app(PartnerCartService::class);
        $candidates = $this->carts->newQuery()
            ->where('partner_id', $partnerId)
            ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) IN ('active', 'pending', '')")
            ->orderByDesc('id')
            ->limit(20)
            ->pluck('id');

        foreach ($candidates as $id) {
            $id = (int) $id;
            if ($id > 0 && $cartService->isCartPendingAadhaar($id)) {
                return $id;
            }
        }

        foreach ($candidates as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            if (CartItem::query()->where('cart_id', $id)->exists()) {
                return $id;
            }
        }

        return (int) ($candidates->first() ?? 0);
    }

    /**
     * Order-details shape for place-pending carts (no orders row yet).
     */
    protected function cartPendingOrderDetails(int $partnerId, int $cartId): JsonResponse
    {
        if ($cartId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'No place-pending cart found for this partner. Generate PO first, or pass cart_id.',
                'data' => null,
            ], 404);
        }

        $cart = $this->carts->newQuery()
            ->with(['items.plan'])
            ->whereKey($cartId)
            ->where('partner_id', $partnerId)
            ->first();

        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Cart not found.',
                'data' => null,
            ], 404);
        }

        $cartService = app(PartnerCartService::class);
        $cartType = strtolower(trim((string) ($cart->type ?? '')));
        $isPlanChangeCart = in_array($cartType, [
            Cart::TYPE_UPGRADE,
            Cart::TYPE_DOWNGRADE,
            Cart::TYPE_RENEW,
            'license_add',
        ], true);
        // Never recalculate upgrade/renew totals here — that wiped remaining_value / payable.
        if (!$isPlanChangeCart) {
            $cart = $cartService->recalculateCartTotals($cartId) ?? $cart->fresh(['items.plan']);
        } else {
            $cart = $cart->fresh(['items.plan']) ?? $cart;
        }

        // Always rebuild generated PO from current cart totals (avoids stale amount/PDF).
        $poResult = $this->createCartPoWithoutOrder($cartId, true);
        $poLink = $poResult['po_link'] ?? null;
        $poNumber = (string) ($poResult['po_number'] ?? '');
        $pendingRef = $cartService->issueCartPendingEsignRef($cartId, $partnerId);

        $customerId = (int) ($cart->customer_id ?? 0);
        $customer = $customerId > 0
            ? $this->customers->newQuery()->whereKey($customerId)->first()
            : null;
        $companyName = trim((string) ($customer->company_name ?? ''));

        $plans = [];
        $plansSubtotal = 0.0;
        $domainName = $this->resolveDomainNameForOrder($cart, $cartId);
        $remainingValue = round((float) ($cart->pro_rata_adjustment ?? 0), 2);
        $cartSubtotal = round((float) ($cart->subtotal ?? 0), 2);
        $appliedProRata = $remainingValue > 0 && $cartSubtotal > 0
            ? round(min($remainingValue, $cartSubtotal), 2)
            : $remainingValue;
        $cartType = strtolower(trim((string) ($cart->type ?? 'new')));
        $enrollmentCategory = $this->cartEnrollmentType($cart);
        if ($enrollmentCategory === 'Partial Upgrade') {
            $cartType = 'partial_upgrade';
        }
        foreach ($cart->items as $item) {
            $plan = $item->plan ?? $this->plans->newQuery()->whereKey((int) ($item->plan_id ?? 0))->first();
            $lineAmount = round((float) ($item->grand_total ?? (($item->unit_price ?? 0) * max(1, (int) ($item->licenses ?? 1)))), 2);
            $plansSubtotal += $lineAmount;

            // Dummy subscription id until Place Order creates the real SUB-######.
            $dummySubId = trim((string) ($item->subscription_id ?? ''));
            if ($dummySubId === '') {
                $dummySubId = $this->draftDummySubscriptionId($cartId, (int) ($item->id ?? 0));
            } else {
                $dummySubId = $this->normalizeSubscriptionIdDisplay($dummySubId);
            }

            $startDate = null;
            $endDate = null;
            $startIso = null;
            $endIso = null;
            $billingCycle = '12 Months';
            if ($plan) {
                $start = !empty($item->subscription_start_date)
                    ? Carbon::parse($item->subscription_start_date)->startOfDay()
                    : Carbon::now()->startOfDay();
                // Google: same anniversary; Tizzy/Microsoft: one day before.
                $end = $cartService->computeSubscriptionEnd($start, $plan);
                $startIso = $start->format('Y-m-d');
                $endIso = $end->format('Y-m-d');
                $startDate = $this->formatDisplayDate($startIso);
                $endDate = $this->formatDisplayDate($endIso);
                $billingCycle = $this->formatBillingCycleDateRange($startDate, $endDate)
                    ?: $this->resolveBillingCycleLabel($plan, null);
            }

            $plans[] = [
                'provider_id' => (int) ($plan->provider_id ?? 0) ?: null,
                'order_id' => $cartId,
                'order_sub_id' => $item->id ?? null,
                'plan_id' => (int) ($item->plan_id ?? 0) ?: null,
                'plan_name' => (string) ($plan->name ?? $plan->title ?? 'Plan'),
                'licenses' => max(1, (int) ($item->licenses ?? 1)),
                'unit_price' => round((float) ($item->unit_price ?? 0), 2),
                'plan_amount' => $lineAmount,
                'status' => 'pending_place',
                'company_name' => $companyName,
                'domain_name' => $domainName,
                'domain' => $domainName,
                'remaining_value' => $remainingValue,
                'remaining_credit' => $remainingValue,
                'pro_rata_adjustment' => $remainingValue,
                'applied_pro_rata' => $appliedProRata,
                'subscription_id' => $dummySubId,
                'subscription_no' => $dummySubId,
                'billing_cycle' => $billingCycle,
                'duration' => $billingCycle,
                'subscription_start_date' => $startDate !== '' && $startDate !== null ? $startDate : null,
                'subscription_end_date' => $endDate !== '' && $endDate !== null ? $endDate : null,
                'subscription_start_date_iso' => $startIso,
                'subscription_end_date_iso' => $endIso,
                'start_date' => $startDate !== '' && $startDate !== null ? $startDate : null,
                'end_date' => $endDate !== '' && $endDate !== null ? $endDate : null,
            ];
        }

        $gstAmount = round((float) ($cart->gst_amount ?? 0), 2);
        $discountAmount = round((float) ($cart->discount_amount ?? 0), 2);
        $totalAmount = round((float) ($cart->grand_total ?? 0), 2);
        $subtotal = round((float) ($cart->subtotal ?? 0), 2);
        if ($subtotal <= 0) {
            $subtotal = round($plansSubtotal, 2);
        }
        if ($subtotal <= 0 && $totalAmount > 0) {
            $subtotal = round(max(0, $totalAmount - $gstAmount + $discountAmount), 2);
        }
        if ($totalAmount <= 0 && $subtotal > 0) {
            $totalAmount = round($subtotal - $discountAmount + $gstAmount, 2);
        }

        $gstBreakdown = $this->resolveOrderGstBreakdown($cart, $customer, null, null, $gstAmount);
        $paymentBreakdown = GstSplit::paymentBreakdownPayload(
            $subtotal,
            $gstBreakdown,
            $discountAmount,
            $totalAmount > 0 ? $totalAmount : null,
            ['coupon' => strtoupper(trim((string) ($cart->coupen ?? '')))]
        );

        $poDetails = [
            'po_status' => 'pending_place',
            'po_number' => $poNumber,
            'final_po_link' => $poLink,
            'po_link' => $poLink,
        ];

        $rootSubscriptionId = (string) ($plans[0]['subscription_id'] ?? '');
        if ($rootSubscriptionId === '') {
            $rootSubscriptionId = $this->draftDummySubscriptionId($cartId, 0);
        }
        $rootBillingCycle = (string) ($plans[0]['billing_cycle'] ?? '12 Months');
        $rootStart = (string) ($plans[0]['subscription_start_date'] ?? '');
        $rootEnd = (string) ($plans[0]['subscription_end_date'] ?? '');
        $uiDates = $this->formatOrderDateFields($cart->created_at ?? now());
        $transferFlag = strtolower(trim((string) (
            $cart->domain_transfer
            ?? $cart->transfer_domain
            ?? ''
        )));
        $enrollmentType = in_array($transferFlag, ['yes', '1', 'true'], true)
            ? 'Transfer Service'
            : 'New Service';

        return response()->json([
            'success' => true,
            'message' => 'Draft order details from cart (not placed yet).',
            'data' => [
                'provider_id' => $plans[0]['provider_id'] ?? null,
                // cart_id exposed as ordId until Place creates a real order.
                'order_id' => $cartId,
                'ordId' => $cartId,
                'partner_id' => $partnerId,
                'order_no' => $pendingRef,
                'orderNo' => $pendingRef,
                'pending_ref_id' => $pendingRef,
                'temp_order_no' => $pendingRef,
                'status' => 'pending_place',
                'order_date' => $uiDates['order_date'],
                'order_date_iso' => $uiDates['order_date_iso'],
                'orderDate' => $uiDates['orderDate'],
                'order_type' => $cartType,
                'domain_name' => $domainName,
                'domain' => $domainName,
                'enrollment_category' => $enrollmentCategory,
                'enrollment_type' => $enrollmentType,
                'subscription_id' => $rootSubscriptionId,
                'subscription_no' => $rootSubscriptionId,
                'subscriptionId' => $rootSubscriptionId,
                'billing_cycle' => $rootBillingCycle,
                'billingCycle' => $rootBillingCycle,
                'duration' => $rootBillingCycle,
                'subscription_start_date' => $rootStart !== '' ? $rootStart : null,
                'subscription_end_date' => $rootEnd !== '' ? $rootEnd : null,
                'start_date' => $rootStart !== '' ? $rootStart : null,
                'end_date' => $rootEnd !== '' ? $rootEnd : null,
                'customer' => [
                    'customer_id' => $customer->id ?? null,
                    'customer_no' => $customer->customer_no ?? null,
                    'name' => $customer->name ?? null,
                    'company_name' => $customer->company_name ?? null,
                    'email' => $customer->email ?? null,
                    'mobile' => $customer->mobile ?? null,
                ],
                'plans' => $plans,
                'aadhaar_verification_id' => null,
                'aadhaar_masked' => '',
                'po_status' => $poDetails['po_status'],
                'po_number' => $poDetails['po_number'],
                'final_po_link' => $poDetails['final_po_link'],
                'po_link' => $poDetails['po_link'],
                'po_details' => $poDetails,
                'gst' => round((float) ($gstBreakdown['gst_rate'] ?? $cart->gst_percentage ?? GstSplit::RATE), 2),
                'discount_amount' => $discountAmount,
                'subtotal' => $subtotal,
                'gst_amount' => round((float) ($gstBreakdown['gst_amount'] ?? $gstAmount), 2),
                'total_amount' => round((float) ($paymentBreakdown['total_amount'] ?? $totalAmount), 2),
                'plan_amount' => $subtotal > 0 ? $subtotal : round((float) ($paymentBreakdown['total_amount'] ?? $totalAmount), 2),
                'tax_type' => (string) ($gstBreakdown['tax_type'] ?? 'cgst_sgst'),
                'cgst' => (float) ($gstBreakdown['cgst'] ?? 0),
                'sgst' => (float) ($gstBreakdown['sgst'] ?? 0),
                'igst' => (float) ($gstBreakdown['igst'] ?? 0),
                'cgst_rate' => (float) ($gstBreakdown['cgst_rate'] ?? 0),
                'sgst_rate' => (float) ($gstBreakdown['sgst_rate'] ?? 0),
                'igst_rate' => (float) ($gstBreakdown['igst_rate'] ?? 0),
                'show_igst' => (bool) ($gstBreakdown['show_igst'] ?? false),
                'show_cgst_sgst' => (bool) ($gstBreakdown['show_cgst_sgst'] ?? true),
                'gst_lines' => $gstBreakdown['gst_lines'] ?? [],
                'payment_breakdown' => $paymentBreakdown,
                'remaining_value' => $remainingValue,
                'remaining_credit' => $remainingValue,
                'pro_rata_adjustment' => $remainingValue,
                'applied_pro_rata' => $appliedProRata,
                'cart_id' => $cartId,
                'place_order_required' => true,
                'is_temp_order' => true,
                'real_order' => false,
                'draft_cart' => true,
            ],
        ]);
    }

    /**
     * CGST/SGST vs IGST for order-details, transaction-details, and cart draft payloads.
     */
    protected function resolveOrderGstBreakdown(
        object $source,
        $customer = null,
        $poRow = null,
        $invoice = null,
        ?float $gstAmountOverride = null
    ): array {
        $firstNonEmpty = static function (...$candidates): string {
            foreach ($candidates as $candidate) {
                $value = trim((string) ($candidate ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }

            return '';
        };

        $orderId = (int) ($source->id ?? 0);
        $linkedOrderId = $source instanceof Order
            ? $orderId
            : (int) ($source->order_id ?? 0);
        $customerGstin = $firstNonEmpty(
            $source->po_manual_gstin ?? null,
            optional($poRow)->manual_gstin ?? null,
            optional($poRow)->po_manual_gstin ?? null,
            $source->customer_gstin ?? null,
            optional($customer)->gstin ?? null
        );

        $companySettings = $this->adminSettings->newQuery()->orderByDesc('id')->first();
        $companyGstin = trim((string) (
            optional($companySettings)->gstin
            ?? optional($companySettings)->gst_no
            ?? optional($companySettings)->company_gstin
            ?? ''
        ));

        $lineTaxType = '';
        if ($invoice) {
            try {
                $invoiceLines = InvoiceSub::query()
                    ->where('invoice_mst_id', (int) $invoice->id)
                    ->get(['tax_type', 'cgst', 'sgst', 'igst']);
                if ($invoiceLines->isNotEmpty()) {
                    $lineTaxType = (string) ($invoiceLines->first()->tax_type ?? '');
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $poTaxType = '';
        if ($linkedOrderId > 0) {
            try {
                $poLines = OrderPoSub::query()
                    ->where('order_id', $linkedOrderId)
                    ->get(['tax_type']);
                if ($poLines->isNotEmpty()) {
                    $poTaxType = (string) ($poLines->first()->tax_type ?? '');
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $gstAmount = $gstAmountOverride;
        if ($gstAmount === null) {
            $gstAmount = round((float) ($source->gst_amount ?? $source->tax_amount ?? 0), 2);
        }

        return GstSplit::resolveBreakdown([
            'gst_amount' => $gstAmount,
            'gst_rate' => (float) ($source->gst_rate ?? $source->gst_percentage ?? GstSplit::RATE),
            'customer_gstin' => $customerGstin,
            'company_gstin' => $companyGstin,
            'tax_type' => optional($invoice)->tax_type ?? $source->tax_type ?? null,
            'line_tax_type' => $lineTaxType,
            'po_tax_type' => $poTaxType,
            'cgst' => optional($invoice)->cgst_total ?? $source->cgst_total ?? $source->cgst_amount ?? 0,
            'sgst' => optional($invoice)->sgst_total ?? $source->sgst_total ?? $source->sgst_amount ?? 0,
            'igst' => optional($invoice)->igst_total ?? $source->igst_total ?? $source->igst_amount ?? 0,
        ]);
    }
}
