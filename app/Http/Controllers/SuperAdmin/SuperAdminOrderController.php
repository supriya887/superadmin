<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Partner\CustomerApi;
use App\Http\Controllers\SuperAdmin\B2B\B2bNewPlanController;
use App\Models\AdminSetting;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Partner;
use App\Models\OrderItem;
use App\Models\OrderPoDetail;
use App\Models\Plan;
use App\Models\Promocode;
use App\Models\Variant;
use App\Services\SmsService;
use App\Services\FirebasePushService;
use App\Services\B2bSubscriptionUpgradeService;
use App\Services\PartnerCreditService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SuperAdminOrderController extends Controller
{
    public function create()
    {
        return redirect()->route('superadmin.orders.create.step', ['step' => 1]);
    }

    /**
     * Create / edit order form (3-tab UI).
     */
    public function form(int $step = 1, ?string $id = null)
    {
        return app(B2bNewPlanController::class)->createStep($step, $id);
    }

    /**
     * Shared view data for order form (routes, line items, PO document).
     *
     * @return array<string, mixed>
     */
    public function formExtras(?object $order, ?int $orderId, int $activeTab = 1): array
    {
        $poDocumentUrl = '';
        $aadhaarVerificationId = '';
        $poGenerated = false;

        if ($orderId > 0) {
            $po = OrderPoDetail::query()->where('order_id', $orderId)->first();
            if ($po) {
                $poDocumentUrl = trim((string) ($po->po_document_url ?? ''));
                $journey = strtolower(trim((string) ($po->journey_status ?? '')));
                $poGenerated = in_array($journey, ['po_done', 'aadhar_done', 'otp_done', 'placed'], true)
                    || $poDocumentUrl !== '';
                $aadhaarVerificationId = trim((string) ($po->aadhaar_ref_no ?? $po->aadhaar_verification_id ?? ''));
            }
        }

        return [
            'activeTab' => max(1, min(3, $activeTab)),
            'orderItems' => $orderId > 0 ? $this->loadOrderItems($orderId) : [],
            'orderFormUrls' => [
                'save' => route('superadmin.orders.save'),
                'customers' => route('superadmin.orders.customers_select'),
                'partnerCustomers' => route('superadmin.orders.partner_customers', ['partner_id' => '__PID__']),
                'partnerContext' => route('superadmin.orders.partner_context', ['partner_id' => '__PID__']),
                'customersApi' => url('/api/v1/partner/customers'),
                'domainCheck' => url('/api/v1/partner/orders-check-domain'),
                'plansByProvider' => route('superadmin.orders.plans_by_provider'),
                'variants' => url('/api/v1/partner/providers-variants'),
                'transferCode' => route('superadmin.orders.transfer_code'),
                'aadhaarInit' => route('superadmin.orders.aadhaar_init'),
                'aadhaarVerify' => route('superadmin.orders.aadhaar_verify'),
                'aadhaarSave' => route('superadmin.orders.save.aadhaar'),
                'gstinSearch' => url('/api/v1/search-gstin'),
                'poSaveBase' => url('/superadmin/orders-add'),
                'customerMeta' => url('/superadmin/orders-customer-meta/__ID__'),
                'orderAction' => $orderId > 0
                    ? route('superadmin.orders.action', ['id' => $orderId])
                    : url('/superadmin/orders-action/__ID__'),
            ],
            'poDocumentUrl' => $poDocumentUrl,
            'poGenerated' => $poGenerated,
            'aadhaarVerificationId' => $aadhaarVerificationId,
            'isEdit' => $orderId > 0,
            'orderCategory' => $order ? strtolower((string) ($order->order_category ?? 'new')) : 'new',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function loadOrderItems(int $orderId): array
    {
        $rows = OrderItem::query()->where('order_id', $orderId)->orderBy('id')->get();
        $items = [];
        foreach ($rows as $r) {
            $planId = (int) ($r->plan_id ?? 0);
            $providerId = 0;
            $mainVariantId = 0;
            $subVariantId = 0;
            $plan = null;
            if ($planId > 0) {
                $plan = Plan::query()->whereKey($planId)->first();
                if ($plan) {
                    $providerId = (int) ($plan->provider_id ?? 0);
                    $vid = (int) ($r->variant_id ?? $plan->variant_id ?? 0);
                    if ($vid > 0) {
                        $v = Variant::query()->whereKey($vid)->first();
                        if ($v && !empty($v->parent_variant_id)) {
                            $mainVariantId = (int) $v->parent_variant_id;
                            $subVariantId = $vid;
                        } else {
                            $mainVariantId = $vid;
                        }
                    }
                }
            }

            $itemName = (string) ($r->item_name ?? $r->name ?? '');
            if ($itemName === '' && $plan) {
                $itemName = (string) ($plan->name ?? $plan->title ?? 'Plan');
            }
            $unitPrice = round((float) ($r->unit_price ?? $r->price ?? 0), 2);
            if ($unitPrice <= 0 && $plan) {
                $unitPrice = round((float) ($plan->price ?? 0), 2);
            }
            $items[] = [
                'plan_id' => $planId,
                'provider_id' => $providerId,
                'main_variant_id' => $mainVariantId,
                'sub_variant_id' => $subVariantId,
                'variant_id' => (int) ($r->variant_id ?? $plan->variant_id ?? 0),
                'item_name' => $itemName,
                'description' => (string) ($r->description ?? ''),
                'hsn_sac' => (string) ($r->hsn_sac ?? ''),
                'quantity' => max(1, (int) ($r->quantity ?? 1)),
                'unit_price' => $unitPrice,
            ];
        }

        if ($items === [] && $orderId > 0) {
            $order = Order::query()->whereKey($orderId)->first();
            $planId = (int) ($order->plan_id ?? 0);
            if ($order && $planId > 0) {
                $plan = Plan::query()->whereKey($planId)->first();
                if ($plan) {
                    $providerId = (int) ($plan->provider_id ?? 0);
                    $mainVariantId = 0;
                    $subVariantId = 0;
                    $vid = (int) ($plan->variant_id ?? 0);
                    if ($vid > 0) {
                        $v = Variant::query()->whereKey($vid)->first();
                        if ($v && !empty($v->parent_variant_id)) {
                            $mainVariantId = (int) $v->parent_variant_id;
                            $subVariantId = $vid;
                        } else {
                            $mainVariantId = $vid;
                        }
                    }
                    $qty = max(1, (int) ($order->quantity ?? $order->licenses ?? $order->user_quantity ?? 1));
                    $unitPrice = round((float) ($order->price ?? 0), 2);
                    if ($unitPrice <= 0) {
                        $unitPrice = round((float) ($plan->price ?? 0), 2);
                    }
                    $items[] = [
                        'plan_id' => $planId,
                        'provider_id' => $providerId,
                        'main_variant_id' => $mainVariantId,
                        'sub_variant_id' => $subVariantId,
                        'variant_id' => $vid,
                        'item_name' => (string) ($plan->name ?? $plan->title ?? 'Plan'),
                        'description' => (string) ($plan->description ?? ''),
                        'hsn_sac' => (string) ($plan->hsn_sac ?? $plan->hsn ?? ''),
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                    ];
                }
            }
        }

        return $items;
    }

    /**
     * Select2-compatible customer list for a partner.
     */
    public function customersSelect(Request $request): JsonResponse
    {
        $partnerId = (int) $request->input('partner_id', $request->query('partner_id', 0));
        if ($partnerId <= 0) {
            return response()->json([]);
        }

        $search = trim((string) $request->input('searchTerm', $request->input('q', '')));
        $query = Customer::query()->where('partner_id', $partnerId)->orderBy('name');
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('mobile', 'like', '%' . $search . '%')
                    ->orWhere('company_name', 'like', '%' . $search . '%');
            });
        }

        $results = $query->limit(50)->get(['id', 'name', 'email', 'mobile', 'company_name'])
            ->map(fn ($c) => [
                'id' => (int) $c->id,
                'text' => trim(($c->name ?: 'Customer') . ' — ' . ($c->company_name)),
            ])
            ->values()
            ->all();

        return response()->json($results);
    }

    /**
     * Partner customers (CustomerApi-compatible JSON).
     */
    public function customersByPartner(Request $request, CustomerApi $customerApi): JsonResponse
    {
        $request->merge(['partner_id' => $request->query('partner_id', $request->input('partner_id'))]);

        return $customerApi->customersgetIndex($request);
    }

    /**
     * Provider transfer code (maps provider_id → admin_settings type 1/2/3).
     */
    public function transferCode(Request $request): JsonResponse
    {
        $providerId = (int) $request->query('provider_id', 0);
        $type = match ($providerId) {
            1 => 1,
            2 => 2,
            3 => 3,
            default => 0,
        };

        if ($type === 0) {
            return response()->json(['success' => false, 'message' => 'Invalid provider'], 422);
        }

        $proxy = Request::create('/', 'POST', ['provider_type' => $type]);

        return app(\App\Http\Controllers\Api\PartnerAuthController::class)->postTransferCode($proxy);
    }

    /**
     * Unified create/update for order master + order_items + PO + notifications.
     */
    public function saveOrder(Request $request, BillingController $billing): JsonResponse
    {
        $request->headers->set('Accept', 'application/json');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $orderIdInput = trim((string) $request->input('order_id', ''));
        $isCreate = $orderIdInput === '';
        $existingOrderId = $isCreate ? 0 : (int) $orderIdInput;

        // Existing orders are status-only: Active / Cancel, plus subscription dates.
        if ($existingOrderId > 0) {
            if ($this->isSubscriptionDatesUpdateRequest($request)) {
                return $this->updateOrderSubscriptionDatesOnly($request, $existingOrderId);
            }

            if (
                $request->boolean('status_only_update')
                || (string) $request->input('status_only_update') === '1'
                || $request->boolean('wizard_status_update')
                || (string) $request->input('wizard_status_update') === '1'
            ) {
                return $this->updateOrderStatusOnly($request, $billing, $existingOrderId);
            }

            return response()->json([
                'error' => true,
                'message' => 'Order details cannot be edited. Only Active or Cancel status, or subscription dates, can be changed.',
            ], 403);
        }

        $items = $this->normalizeLineItems($request);
        if ($items === []) {
            return response()->json([
                'error' => true,
                'message' => 'Add at least one plan line.',
                'details' => ['items' => 'Select at least one plan.'],
            ], 422);
        }

        $limitError = $this->validatePlanCustomerLimits($items);
        if ($limitError !== null) {
            return response()->json([
                'error' => true,
                'message' => $limitError,
                'details' => ['quantity' => [$limitError]],
            ], 422);
        }

        $isExistingCategory = $this->isExistingOrderCategoryRequest($request);

        $subRules = [
            'subscription_start_date' => ['required', 'date'],
        ];
        if (!$isExistingCategory) {
            $subRules['subscription_end_date'] = ['required', 'date', 'after_or_equal:subscription_start_date'];
        }
        $subValidator = Validator::make($request->all(), $subRules);
        if ($subValidator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $subValidator->errors()->first(),
                'details' => $subValidator->errors()->toArray(),
            ], 422);
        }
        $subStart = Carbon::parse((string) $request->input('subscription_start_date'))->toDateString();
        $subEnd = $isExistingCategory
            ? null
            : Carbon::parse((string) $request->input('subscription_end_date'))->toDateString();

        $providerIds = array_unique(array_column($items, 'provider_id'));
        if (count($providerIds) > 1) {
            return response()->json([
                'error' => true,
                'message' => 'Only one provider is allowed per order.',
                'details' => ['provider_id' => 'Use a single provider for all lines.'],
            ], 422);
        }

        $providerId = (int) ($request->input('provider_id', $items[0]['provider_id'] ?? 0));
        if ($providerId === 3 && count($items) > 1) {
            return response()->json([
                'error' => true,
                'message' => 'Google provider allows only one plan per order.',
                'details' => ['items' => 'Select a single plan only.'],
            ], 422);
        }

        $totals = $this->resolveTotalsFromRequest($request, $items);
        if ($isExistingCategory) {
            $totals['discount_amount'] = 0.0;
            $taxable = max(0, round($totals['subtotal'], 2));
            $gst = $totals['apply_gst'] ? round($taxable * $totals['gst_rate'] / 100, 2) : 0.0;
            $totals['taxable_amount'] = $taxable;
            $totals['gst_amount'] = $gst;
            $totals['tax_amount'] = $gst;
            $totals['total_amount'] = round($taxable + $gst, 2);
        }
        $first = $items[0];

        $orderType = strtolower(trim((string) $request->input('order_type', 'b2b')));
        if ($orderType !== 'b2c') {
            $partnerId = (int) $request->input('partner_id', 0);
            $existingIdForCredit = $orderIdInput !== '' ? (int) $orderIdInput : 0;
            if ($partnerId <= 0 && $existingIdForCredit > 0) {
                $partnerId = (int) Order::query()->whereKey($existingIdForCredit)->value('partner_id');
            }
            if ($partnerId <= 0) {
                return response()->json([
                    'error' => true,
                    'message' => 'Partner is required for B2B orders.',
                    'details' => ['partner_id' => ['Please select a partner.']],
                ], 422);
            }
            $creditCheck = PartnerCreditService::validateB2bPurchaseAmount(
                $partnerId,
                (float) $totals['total_amount'],
                false,
                $existingIdForCredit > 0 ? $existingIdForCredit : null
            );
            if (empty($creditCheck['ok'])) {
                return response()->json([
                    'error' => true,
                    'message' => (string) ($creditCheck['message'] ?? PartnerCreditService::CREDIT_LIMIT_REACHED_MESSAGE),
                    'code' => (string) ($creditCheck['code'] ?? 'credit_limit_exceeded'),
                ], 422);
            }
        }

        $promoCode = $isExistingCategory
            ? ''
            : strtoupper(trim((string) $request->input('promo_code', $request->input('promocode', ''))));

        $existingId = $isCreate ? null : (int) $orderIdInput;
        if ($existingId !== null && !Order::query()->whereKey($existingId)->exists()) {
            return response()->json(['error' => true, 'message' => 'Order not found.'], 404);
        }

        $mergePayload = [
            'defer_po_save' => 1,
            'plan_id' => $first['plan_id'],
            'provider_id' => $first['provider_id'],
            'quantity' => $totals['quantity'],
            'order_type' => strtolower(trim((string) $request->input('order_type', 'b2b'))) === 'b2c' ? 'b2c' : 'b2b',
            'promocode' => $promoCode,
            'promo_code' => $promoCode,
            'subscription_start_date' => $subStart,
            'subscription_end_date' => $subEnd,
            'subtotal' => $totals['subtotal'],
            'discount_amount' => $totals['discount_amount'],
            'apply_gst' => $totals['apply_gst'] ? 1 : 0,
            'gst_rate' => $totals['gst_rate'],
            'gst_amount' => $totals['gst_amount'],
            'tax_amount' => $totals['tax_amount'],
            'total_amount' => $totals['total_amount'],
            'amount' => $totals['total_amount'],
            'plans' => array_map(static fn ($row) => [
                'plan_id' => $row['plan_id'],
                'quantity' => $row['quantity'],
                'unit_price' => $row['unit_price'],
            ], $items),
            'order_category' => $isExistingCategory ? 'existing' : 'new',
        ];
        $request->merge($mergePayload);

        $statusBeforeSave = $existingId !== null
            ? strtolower(trim((string) (Order::query()->whereKey($existingId)->value('status') ?? 'pending')))
            : null;

        try {
            $response = $billing->saveStep($request, 1, $existingId);
            $payload = json_decode($response->getContent(), true) ?: [];

            if ($response->getStatusCode() >= 400 || !empty($payload['error'])) {
                if (empty($payload['message']) && !empty($payload['details']) && is_array($payload['details'])) {
                    $firstDetail = collect($payload['details'])->flatten()->first();
                    if (is_string($firstDetail) && $firstDetail !== '') {
                        $payload['message'] = $firstDetail;
                    }
                }

                return response()->json(
                    $payload ?: ['error' => true, 'message' => 'Order save failed.'],
                    $response->getStatusCode() >= 400 ? $response->getStatusCode() : 422
                );
            }

            $orderId = (int) ($payload['order_id'] ?? $existingId ?? 0);
            if ($orderId <= 0) {
                return response()->json(['error' => true, 'message' => 'Order could not be saved.'], 500);
            }

            $orderUpdate = [
                'updated_at' => now(),
                'plan_id' => $first['plan_id'],
                'quantity' => $totals['quantity'],
                'promo_code' => $promoCode !== '' ? $promoCode : null,
                'subscription_start_date' => $subStart,
                'subscription_end_date' => $subEnd,
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount_amount'],
                'gst_amount' => $totals['gst_amount'],
                'tax_amount' => $totals['tax_amount'],
                'total_amount' => $totals['total_amount'],
                'apply_gst' => $totals['apply_gst'] ? 1 : 0,
                'gst_rate' => $totals['gst_rate'],
                'price' => $first['unit_price'],
            ];

            $cat = strtolower(trim((string) $request->input('order_category', 'new')));
            if (!in_array($cat, ['new', 'existing'], true)) {
                $cat = 'new';
            }
            $orderUpdate['order_category'] = $cat;

            Order::query()->whereKey($orderId)->update($orderUpdate);

            $this->syncOrderItems($orderId, $items);

            if (strtolower(trim((string) $request->input('order_type', 'b2b'))) === 'b2b') {
                $billing->finalizePartnerB2bOrderPostSave($orderId);
            }

            $statusWarning = null;
            if ($orderId > 0 && $request->filled('status')) {
                try {
                    $reqStatus = strtolower(trim((string) $request->input('status')));
                    if ($reqStatus === 'cancelled') {
                        $reqStatus = 'rejected';
                    } elseif (in_array($reqStatus, ['active', 'paid', 'success'], true)) {
                        $reqStatus = 'completed';
                    }
                    $statusRequestPayload = array_merge($request->all(), [
                        'wizard_status_update' => '1',
                        // Activation emails are sent once after save using pre-save status.
                        'skip_partner_notify' => '1',
                        'status' => $reqStatus,
                        'rejection_reason' => trim((string) $request->input('rejection_reason', '')),
                    ]);
                    $statusRequest = $request->duplicate(null, $statusRequestPayload);
                    $statusRequest->headers->set('Accept', 'application/json');
                    $statusRequest->headers->set('X-Requested-With', 'XMLHttpRequest');
                    $statusResponse = $billing->saveStep($statusRequest, 3, $orderId);
                    $statusPayload = json_decode($statusResponse->getContent(), true) ?: [];
                    if ($statusResponse->getStatusCode() >= 400 || !empty($statusPayload['error'])) {
                        $statusWarning = trim((string) ($statusPayload['message'] ?? 'Order status was not updated.'));
                        Log::warning('SuperAdminOrderController::saveOrder status update skipped', [
                            'order_id' => $orderId,
                            'status' => $request->input('status'),
                            'message' => $statusWarning,
                        ]);
                    }
                } catch (\Throwable $e) {
                    $statusWarning = $e->getMessage();
                    Log::warning('SuperAdminOrderController::saveOrder status update failed', [
                        'order_id' => $orderId,
                        'status' => $request->input('status'),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $savedOrder = Order::query()->whereKey($orderId)->first();
            if ($savedOrder) {
                $savedCategory = strtolower(trim((string) ($savedOrder->order_category ?? '')));
                $orderStatus = strtolower(trim((string) ($savedOrder->status ?? '')));
                $orderNo = strtoupper(trim((string) ($savedOrder->order_no ?? '')));
                if (
                    in_array($savedCategory, ['upgrade', 'downgrade'], true)
                    && in_array($orderStatus, ['completed', 'paid', 'success', 'active'], true)
                ) {
                    app(B2bSubscriptionUpgradeService::class)->markSourceSubscriptionUpgraded($savedOrder);
                }
                if (
                    in_array($orderStatus, ['completed', 'paid', 'success', 'active'], true)
                    && (
                        in_array($savedCategory, ['upgrade', 'downgrade', 'renewal', 'renew'], true)
                        || str_starts_with($orderNo, 'CH')
                        || str_starts_with($orderNo, 'RN')
                    )
                ) {
                    try {
                        app(\App\Services\OrderCancellationCreditNoteService::class)
                            ->issueForPlanChangeOrder($orderId);
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('plan_change_credit_note_on_save_failed', [
                            'order_id' => $orderId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            if ($savedOrder && !$isExistingCategory) {
                $this->sendActivationNotificationsIfNeeded(
                    $billing,
                    $orderId,
                    $savedOrder,
                    $statusBeforeSave,
                    $request
                );
            }

            $crud = $isCreate ? 1 : 2;
            $baseMessage = $crud === 1 ? 'Order created successfully.' : 'Order updated successfully.';
            if ($statusWarning !== null && $statusWarning !== '') {
                $baseMessage .= ' ' . $statusWarning;
            }

            return response()->json([
                'success' => true,
                'message' => $baseMessage,
                'order_id' => $orderId,
                'crud' => $crud,
                'po_generated' => $this->poAlreadyGenerated($orderId),
                'status_warning' => $statusWarning,
                'redirect_url' => route('superadmin.orders.create.step', [
                    'step' => $crud === 1 ? 2 : 1,
                    'id' => $orderId,
                ]),
            ]);
        } catch (\Throwable $e) {
            Log::error('SuperAdminOrderController::saveOrder', [
                'order_id' => $existingId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => true,
                'message' => 'Order save failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Backward-compatible wizard step POST (step 2/3 → BillingController).
     */
    public function saveOrderStep(Request $request, int $step, ?int $id = null, ?BillingController $billing = null): JsonResponse
    {
        $request->headers->set('Accept', 'application/json');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $billing = $billing ?? app(BillingController::class);

        if ($id !== null && (int) $id > 0) {
            $request->merge(['order_id' => (string) (int) $id]);
        }

        // Existing wizard orders: status-only for details; PO/Aadhaar allowed while still pending/processing.
        if ($id !== null && (int) $id > 0) {
            if ($this->isSubscriptionDatesUpdateRequest($request)) {
                return $this->updateOrderSubscriptionDatesOnly($request, (int) $id);
            }

            $isStatusOnly = $request->boolean('status_only_update')
                || (string) $request->input('status_only_update') === '1'
                || $request->boolean('wizard_status_update')
                || (string) $request->input('wizard_status_update') === '1';

            if ($isStatusOnly) {
                return $this->updateOrderStatusOnly($request, $billing, (int) $id);
            }

            $orderStatus = strtolower(trim((string) (Order::query()->whereKey((int) $id)->value('status') ?? '')));
            $lockedStatuses = ['completed', 'paid', 'success', 'active', 'rejected', 'cancelled', 'canceled', 'failed'];
            if (in_array($orderStatus, $lockedStatuses, true) || $step === 1) {
                return response()->json([
                    'error' => true,
                    'message' => 'Order details cannot be edited. Only Active or Cancel status, or subscription dates, can be changed.',
                ], 403);
            }

            $creditBlock = $billing->b2bCreditWizardJsonResponse($request, (int) $id);
            if ($creditBlock !== null) {
                return $creditBlock;
            }
        }

        if ($step === 1) {
            return $this->saveOrder($request, $billing);
        }

        $response = $billing->saveStep($request, $step, $id);

        return response()->json(
            json_decode($response->getContent(), true) ?: ['error' => true, 'message' => 'Save failed.'],
            $response->getStatusCode()
        );
    }

    private function isSubscriptionDatesUpdateRequest(Request $request): bool
    {
        return $request->boolean('subscription_dates_update')
            || (string) $request->input('subscription_dates_update') === '1';
    }

    /**
     * Superadmin can edit subscription start/end dates on an existing order.
     */
    private function updateOrderSubscriptionDatesOnly(Request $request, int $orderId): JsonResponse
    {
        $order = Order::query()->whereKey($orderId)->first();
        if (!$order) {
            return response()->json(['error' => true, 'message' => 'Order not found.'], 404);
        }

        $isExistingCategory = strtolower(trim((string) ($order->order_category ?? ''))) === 'existing';
        $rules = [
            'subscription_start_date' => ['required', 'date'],
        ];
        if ($isExistingCategory) {
            $rules['subscription_end_date'] = ['nullable', 'date', 'after_or_equal:subscription_start_date'];
        } else {
            $rules['subscription_end_date'] = ['required', 'date', 'after_or_equal:subscription_start_date'];
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
                'details' => $validator->errors()->toArray(),
            ], 422);
        }

        $subStart = Carbon::parse((string) $request->input('subscription_start_date'))->toDateString();
        $endRaw = trim((string) $request->input('subscription_end_date', ''));
        $subEnd = ($isExistingCategory && $endRaw === '')
            ? null
            : Carbon::parse($endRaw)->toDateString();

        try {
            $order->subscription_start_date = $subStart;
            $order->subscription_end_date = $subEnd;
            $order->save();

            OrderItem::query()
                ->where('order_id', $orderId)
                ->update([
                    'subscription_start_date' => $subStart,
                    'subscription_end_date' => $subEnd,
                    'updated_at' => now(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('SuperAdminOrderController::updateOrderSubscriptionDatesOnly failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => true,
                'message' => 'Subscription dates could not be updated.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Subscription dates updated.',
            'order_id' => $orderId,
            'subscription_start_date' => $subStart,
            'subscription_end_date' => $subEnd,
        ]);
    }

    /**
     * Status-only update for existing orders (Active / Cancel).
     */
    private function updateOrderStatusOnly(Request $request, BillingController $billing, int $orderId): JsonResponse
    {
        if ($orderId <= 0 || !Order::query()->whereKey($orderId)->exists()) {
            return response()->json(['error' => true, 'message' => 'Order not found.'], 404);
        }

        $statusBeforeSave = strtolower(trim((string) (Order::query()->whereKey($orderId)->value('status') ?? 'pending')));
        $reqStatus = strtolower(trim((string) $request->input('status', '')));
        if ($reqStatus === 'cancelled') {
            $reqStatus = 'rejected';
        } elseif (in_array($reqStatus, ['active', 'paid', 'success'], true)) {
            $reqStatus = 'completed';
        }

        if (!in_array($reqStatus, ['completed', 'rejected'], true)) {
            return response()->json([
                'error' => true,
                'message' => 'Only Active or Cancel status can be set.',
            ], 422);
        }

        $isActiveBefore = in_array($statusBeforeSave, ['completed', 'paid', 'success', 'active'], true);
        if ($reqStatus === 'rejected' && !$isActiveBefore) {
            return response()->json([
                'error' => true,
                'message' => 'Cancel is allowed only after the order is Active.',
            ], 422);
        }

        if ($reqStatus === 'rejected' && trim((string) $request->input('rejection_reason', '')) === '') {
            return response()->json([
                'error' => true,
                'message' => 'Enter cancellation reason.',
            ], 422);
        }

        try {
            $statusRequestPayload = array_merge($request->all(), [
                'order_id' => (string) $orderId,
                'wizard_status_update' => '1',
                'status_only_update' => '1',
                'skip_partner_notify' => '1',
                'status' => $reqStatus,
                'rejection_reason' => trim((string) $request->input('rejection_reason', '')),
            ]);
            $statusRequest = $request->duplicate(null, $statusRequestPayload);
            $statusRequest->headers->set('Accept', 'application/json');
            $statusRequest->headers->set('X-Requested-With', 'XMLHttpRequest');
            $statusResponse = $billing->saveStep($statusRequest, 3, $orderId);
            $statusPayload = json_decode($statusResponse->getContent(), true) ?: [];
            if ($statusResponse->getStatusCode() >= 400 || !empty($statusPayload['error'])) {
                return response()->json(
                    $statusPayload ?: ['error' => true, 'message' => 'Order status was not updated.'],
                    $statusResponse->getStatusCode() >= 400 ? $statusResponse->getStatusCode() : 422
                );
            }

            $savedOrder = Order::query()->whereKey($orderId)->first();
            if ($savedOrder) {
                $savedCategory = strtolower(trim((string) ($savedOrder->order_category ?? '')));
                $orderStatus = strtolower(trim((string) ($savedOrder->status ?? '')));
                $orderNo = strtoupper(trim((string) ($savedOrder->order_no ?? '')));
                if (
                    in_array($savedCategory, ['upgrade', 'downgrade'], true)
                    && in_array($orderStatus, ['completed', 'paid', 'success', 'active'], true)
                ) {
                    app(B2bSubscriptionUpgradeService::class)->markSourceSubscriptionUpgraded($savedOrder);
                }
                if (
                    in_array($orderStatus, ['completed', 'paid', 'success', 'active'], true)
                    && (
                        in_array($savedCategory, ['upgrade', 'downgrade', 'renewal', 'renew'], true)
                        || str_starts_with($orderNo, 'CH')
                        || str_starts_with($orderNo, 'RN')
                    )
                ) {
                    try {
                        app(\App\Services\OrderCancellationCreditNoteService::class)
                            ->issueForPlanChangeOrder($orderId);
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('plan_change_credit_note_on_status_failed', [
                            'order_id' => $orderId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                if (!$this->isExistingOrderCategoryRequest($request)) {
                    $this->sendActivationNotificationsIfNeeded(
                        $billing,
                        $orderId,
                        $savedOrder,
                        $statusBeforeSave,
                        $request
                    );
                }
            }

            $label = $reqStatus === 'rejected' ? 'cancelled' : 'activated';

            return response()->json([
                'success' => true,
                'message' => (string) ($statusPayload['message'] ?? ('Order ' . $label . ' successfully.')),
                'order_id' => $orderId,
                'redirect_url' => route('superadmin.orders.create.step', [
                    'step' => 1,
                    'id' => $orderId,
                ]),
            ]);
        } catch (\Throwable $e) {
            Log::warning('SuperAdminOrderController::updateOrderStatusOnly failed', [
                'order_id' => $orderId,
                'status' => $reqStatus,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => true,
                'message' => 'Order status update failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeLineItems(Request $request): array
    {
        $raw = $request->input('items', $request->input('plans', []));
        if (!is_array($raw)) {
            $raw = [];
        }

        $lines = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $planId = (int) ($row['plan_id'] ?? 0);
            $qty = max(1, (int) ($row['quantity'] ?? $row['licenses'] ?? 1));
            $unitPrice = (float) ($row['unit_price'] ?? 0);
            $variantId = (int) ($row['variant_id'] ?? $row['sub_variant_id'] ?? $row['main_variant_id'] ?? 0);
            if ($planId <= 0) {
                continue;
            }

            $providerId = (int) ($row['provider_id'] ?? $request->input('provider_id', 0));
            $plan = Plan::query()->whereKey($planId)->first();
            if ($providerId <= 0 && $plan) {
                $providerId = (int) ($plan->provider_id ?? 0);
            }

            $lines[] = [
                'plan_id' => $planId,
                'provider_id' => $providerId,
                'variant_id' => $variantId,
                'quantity' => $qty,
                'unit_price' => $unitPrice > 0 ? $unitPrice : (float) ($plan->price ?? 0),
                'item_name' => (string) ($row['item_name'] ?? $plan->name ?? $plan->title ?? 'Plan'),
                'description' => (string) ($row['description'] ?? $plan->description ?? ''),
                'hsn_sac' => (string) ($row['hsn_sac'] ?? $plan->hsn_sac ?? $plan->hsn ?? ''),
            ];
        }

        if ($lines !== []) {
            return $lines;
        }

        $singlePlan = (int) $request->input('plan_id', 0);
        $singleQty = max(1, (int) $request->input('quantity', 1));
        if ($singlePlan > 0) {
            return $this->normalizeLineItems(
                $request->merge(['items' => [['plan_id' => $singlePlan, 'quantity' => $singleQty]]])
            );
        }

        return [];
    }

    /**
     * Use order-summary values from the form when submitted; avoid server-side recalculation.
     *
     * @param  list<array<string, mixed>>  $items
     * @return array{subtotal: float, discount_amount: float, taxable_amount: float, gst_amount: float, tax_amount: float, total_amount: float, quantity: int, apply_gst: bool, gst_rate: float}
     */
    private function resolveTotalsFromRequest(Request $request, array $items): array
    {
        $qty = 0;
        $computedSubtotal = 0.0;
        foreach ($items as $line) {
            $computedSubtotal += round((float) $line['unit_price'] * (int) $line['quantity'], 2);
            $qty += (int) $line['quantity'];
        }
        $computedSubtotal = round($computedSubtotal, 2);

        if ($request->has('total_amount') && is_numeric($request->input('total_amount'))) {
            $subtotal = $request->has('subtotal') && is_numeric($request->input('subtotal'))
                ? round((float) $request->input('subtotal'), 2)
                : $computedSubtotal;
            $discount = round((float) $request->input('discount_amount', 0), 2);
            $applyGst = $request->boolean('apply_gst', true);
            $gstRate = max(0, (float) $request->input('gst_rate', 18));
            $gst = round((float) $request->input('gst_amount', $request->input('tax_amount', 0)), 2);
            $total = round((float) $request->input('total_amount'), 2);
            $taxable = max(0, round($subtotal - $discount, 2));

            return [
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'taxable_amount' => $taxable,
                'gst_amount' => $gst,
                'tax_amount' => $gst,
                'total_amount' => $total,
                'quantity' => max(1, $qty),
                'apply_gst' => $applyGst,
                'gst_rate' => $gstRate,
            ];
        }

        return $this->calculateTotals($request, $items);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{subtotal: float, discount_amount: float, taxable_amount: float, gst_amount: float, tax_amount: float, total_amount: float, quantity: int, apply_gst: bool, gst_rate: float}
     */
    private function calculateTotals(Request $request, array $items): array
    {
        $subtotal = 0.0;
        $qty = 0;
        foreach ($items as $line) {
            $subtotal += round((float) $line['unit_price'] * (int) $line['quantity'], 2);
            $qty += (int) $line['quantity'];
        }
        $subtotal = round($subtotal, 2);

        $discount = $this->resolveOrderDiscount($request, $subtotal);
        $applyGst = $request->boolean('apply_gst', true);
        $gstRate = max(0, (float) $request->input('gst_rate', 18));
        $taxable = max(0, round($subtotal - $discount, 2));
        $gst = $applyGst ? round($taxable * $gstRate / 100, 2) : 0.0;
        $total = round($taxable + $gst, 2);

        return [
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'taxable_amount' => $taxable,
            'gst_amount' => $gst,
            'tax_amount' => $gst,
            'total_amount' => $total,
            'quantity' => max(1, $qty),
            'apply_gst' => $applyGst,
            'gst_rate' => $gstRate,
        ];
    }

    private function isExistingOrderCategoryRequest(Request $request): bool
    {
        return strtolower(trim((string) $request->input('order_category', ''))) === 'existing';
    }

    private function resolveOrderDiscount(Request $request, float $subtotal): float
    {
        if ($this->isExistingOrderCategoryRequest($request)) {
            return 0.0;
        }

        $subtotal = max(0, round($subtotal, 2));
        if ($subtotal <= 0) {
            return 0.0;
        }

        $promoCode = strtoupper(trim((string) $request->input('promo_code', $request->input('promocode', ''))));
        if ($promoCode !== '') {
            $promo = Promocode::query()
                ->whereRaw('UPPER(code) = ?', [$promoCode])
                ->active()
                ->first();
            if ($promo) {
                $discountPercent = max(0.0, min(100.0, (float) ($promo->discount_percent ?? 0)));

                return min(round($subtotal * $discountPercent / 100, 2), $subtotal);
            }
        }

        $manualDiscount = max(0, (float) $request->input('discount_amount', 0));

        return min(round($manualDiscount, 2), $subtotal);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function syncOrderItems(int $orderId, array $items): void
    {
        if ($orderId <= 0) {
            return;
        }

        OrderItem::query()->where('order_id', $orderId)->delete();

        foreach ($items as $line) {
            $variantId = (int) ($line['variant_id'] ?? 0);
            $lineTotal = round((float) $line['unit_price'] * (int) $line['quantity'], 2);

            $orderItem = OrderItem::query()->create([
                'order_id' => $orderId,
                'plan_id' => $line['plan_id'],
                'variant_id' => $variantId > 0 ? $variantId : null,
                'item_name' => $line['item_name'] ?? 'Plan',
                'description' => $line['description'] ?? null,
                'hsn_sac' => $line['hsn_sac'] ?? null,
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'total_amount' => $lineTotal,
            ]);
            $subscriptionPrefix = strtoupper(trim((string) (
                AdminSetting::query()->orderBy('id')->value('subscription_prefix') ?? 'SUB'
            ))) ?: 'SUB';
            $orderItem->subscription_id = $subscriptionPrefix . '-' . str_pad((string) $orderItem->id, 6, '0', STR_PAD_LEFT);
            $orderItem->save();
        }
    }

    private function poAlreadyGenerated(int $orderId): bool
    {
        if ($orderId <= 0) {
            return false;
        }

        $row = OrderPoDetail::query()->where('order_id', $orderId)->first();
        if (!$row) {
            return false;
        }

        $journey = strtolower(trim((string) ($row->journey_status ?? '')));
        if (in_array($journey, ['po_done', 'aadhar_done', 'otp_done', 'placed'], true)) {
            return true;
        }

        return trim((string) ($row->po_document_url ?? '')) !== '';
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function validatePlanCustomerLimits(array $items): ?string
    {
        foreach ($items as $line) {
            $planId = (int) ($line['plan_id'] ?? 0);
            $qty = (int) ($line['quantity'] ?? 1);
            if ($planId <= 0) {
                continue;
            }
            $plan = Plan::query()->whereKey($planId)->first();
            if (!$plan) {
                continue;
            }
            $limit = (int) ($plan->customer_limit ?? 0);
            if ($limit > 0 && $qty > $limit) {
                $planName = trim((string) ($plan->name ?? $plan->title ?? '')) ?: ('Plan #' . $planId);

                return 'Licenses for "' . $planName . '" cannot exceed ' . $limit . ' (plan customer limit).';
            }
        }

        return null;
    }

    private function sendActivationNotificationsIfNeeded(
        BillingController $billing,
        int $orderId,
        Order $orderRow,
        ?string $statusBeforeSave,
        Request $request
    ): void {
        $freshOrder = Order::query()->whereKey($orderId)->first() ?? $orderRow;
        $partnerId = (int) ($freshOrder->partner_id ?? 0);
        if ($partnerId <= 0) {
            $partnerId = (int) $request->input('partner_id', 0);
            if ($partnerId > 0) {
                Order::query()->whereKey($orderId)->update([
                    'partner_id' => $partnerId,
                    'updated_at' => now(),
                ]);
                $freshOrder = Order::query()->whereKey($orderId)->first() ?? $freshOrder;
            }
        }

        $statusAfterSave = strtolower(trim((string) ($freshOrder->status ?? 'pending')));
        $wasActiveBefore = in_array((string) $statusBeforeSave, ['completed', 'paid', 'success', 'active'], true);
        $isActiveNow = in_array($statusAfterSave, ['completed', 'paid', 'success', 'active'], true);

        Log::info('SuperAdmin order activation mail check', [
            'order_id' => $orderId,
            'partner_id' => (int) ($freshOrder->partner_id ?? 0),
            'status_before' => $statusBeforeSave,
            'status_after' => $statusAfterSave,
            'was_active_before' => $wasActiveBefore,
            'is_active_now' => $isActiveNow,
        ]);

        if (!$isActiveNow || $wasActiveBefore) {
            return;
        }

        try {
            $billing->sendOrderPlacedEmailForOrder($orderId, false);
        } catch (\Throwable $e) {
            Log::warning('SuperAdmin order active: customer email failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }

        if (strtolower(trim((string) ($freshOrder->order_type ?? 'b2b'))) !== 'b2b') {
            return;
        }

        $partnerId = (int) ($freshOrder->partner_id ?? 0);
        if ($partnerId <= 0) {
            Log::warning('SuperAdmin order active: partner email skipped, missing partner_id', [
                'order_id' => $orderId,
            ]);

            return;
        }

        $partnerEmailSent = $billing->sendOrderPlacedEmailForOrder($orderId, true);
        if (!$partnerEmailSent) {
            try {
                $partnerEmailSent = $billing->sendPartnerActiveEmail($orderId, $partnerId);
            } catch (\Throwable $e) {
                Log::warning('SuperAdmin order active: partner fallback email failed', [
                    'order_id' => $orderId,
                    'partner_id' => $partnerId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (!$partnerEmailSent) {
            Log::warning('SuperAdmin order active: partner email not sent', [
                'order_id' => $orderId,
                'partner_id' => $partnerId,
            ]);
        }

        try {
            $billing->notifyPartnerOrderActivation($orderId, false);
        } catch (\Throwable $e) {
            Log::warning('SuperAdmin order active: partner SMS/WhatsApp failed', [
                'order_id' => $orderId,
                'partner_id' => $partnerId,
                'error' => $e->getMessage(),
            ]);
        }

        // Partner web panel / app notification list
        try {
            $orderNo = (string) ($freshOrder->order_no ?? $orderId);
            Notification::query()->create([
                'partner_id' => $partnerId,
                'title' => 'Order activated',
                'message' => 'Order #' . $orderNo . ' is now active.',
                'is_read' => 0,
                'order_id' => $orderId,
                'status' => 'active',
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('SuperAdmin order active: partner panel notification failed', [
                'order_id' => $orderId,
                'partner_id' => $partnerId,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $orderNo = (string) ($freshOrder->order_no ?? $orderId);
            $sent = app(FirebasePushService::class)->sendToPartner(
                $partnerId,
                'Order activated',
                'Order #' . $orderNo . ' is now active.',
                [
                    'type' => 'order_active',
                    'order_id' => (string) $orderId,
                    'order_no' => $orderNo,
                ]
            );
            Log::info('SuperAdmin order active: partner push', [
                'order_id' => $orderId,
                'partner_id' => $partnerId,
                'sent_count' => $sent,
            ]);
        } catch (\Throwable $e) {
            Log::warning('SuperAdmin order active: partner push failed', [
                'order_id' => $orderId,
                'partner_id' => $partnerId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendPlacementSms(int $orderId): void
    {
        $order = Order::query()->whereKey($orderId)->first();
        if (!$order) {
            return;
        }

        $mobile = trim((string) (Customer::query()->whereKey((int) ($order->user_id ?? 0))->value('mobile') ?? ''));
        if ($mobile === '') {
            return;
        }

        try {
            $amount = (float) ($order->total_amount ?? $order->subtotal ?? 0);
            $orderNo = (string) ($order->order_no ?? $orderId);
            app(SmsService::class)->sendPaymentConfirmation($mobile, $amount, $orderNo);
        } catch (\Throwable $e) {
            Log::warning('Order placement SMS failed', ['order_id' => $orderId, 'error' => $e->getMessage()]);
        }
    }
}
