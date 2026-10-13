<?php

namespace App\Http\Controllers\SuperAdmin\B2B;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SuperAdmin\BillingController;
use App\Models\CreditRequest;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPoDetail;
use App\Models\Partner as PartnerRecord;
use App\Models\PartnerAadhaarVerification;
use App\Models\Plan;
use App\Models\Provider;
use App\Models\Variant;
use App\Support\GstSplit;
use App\Services\OrderCancellationPolicy;
use App\Services\PartnerB2bNewPlanCheckoutService;
use App\Services\RenewalService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class B2bNewPlanController extends Controller
{
    public function createOrder()
    {
        return redirect()->route('superadmin.orders.create.step', ['step' => 1]);
    }

    public function createStep(int $step = 1, ?string $id = null)
    {
        $num = max(1, min(3, (int) $step));
        $wizardOrderId = ($id !== null && $id !== '') ? (int) $id : null;

        if ($num > 1 && $wizardOrderId === null) {
            return redirect()->route('superadmin.orders.create.step', ['step' => 1]);
        }
        
        $order = null;
        if ($wizardOrderId !== null) {
            $order = Order::query()->whereKey($wizardOrderId)->first();
            abort_if(!$order, 404);
        }

        $partners = $this->getPartnerOptions();
        $partner = null;
        $customers = collect();
        $hasPendingCreditRequest = false;
        $walletBalance = 0.0;

        if ($order && (int) ($order->partner_id ?? 0) > 0) {
            $partner = PartnerRecord::find((int) $order->partner_id);
            if ($partner) {
                $customers = $this->customersForPartner((int) $partner->id);
                $hasPendingCreditRequest = $this->partnerHasPendingCredit((int) $partner->id);
                $walletBalance = (float) ($partner->wallet_balance ?? 0);
            }
            if (!$partners->contains(fn ($row) => (int) ($row->id ?? 0) === (int) $order->partner_id) && $partner) {
                $partners->prepend((object) [
                    'id' => (int) $partner->id,
                    'name' => $partner->name ?: "Partner #{$partner->id}",
                    'wallet_balance' => (float) ($partner->wallet_balance ?? 0),
                ]);
            }
        }

        if ($order && (int) ($order->user_id ?? 0) > 0) {
            $selectedCustomerId = (int) $order->user_id;
            if (!$customers->contains(fn ($row) => (int) ($row->id ?? 0) === $selectedCustomerId)) {
                $selectedCustomer = Customer::query()->whereKey($selectedCustomerId)->first();
                if ($selectedCustomer) {
                    $customers->prepend($selectedCustomer);
                }
            }
        }

        $providers = Provider::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'has_variant']);

        $variantMeta = $this->buildProviderVariantMeta();
        $aadhaarStepEnabled = $this->isPoSubmitted($order);
        $wizardEditState = $order ? $this->buildWizardEditState($order) : null;
        $isWizardEdit = $wizardEditState !== null;
        $wizardPoPrefill = null;
        if ($order && $num >= 2) {
            $base = $this->buildWizardPoPrefill($order) ?? [];
            $poRow = OrderPoDetail::query()->where('order_id', (int) $order->id)->first();
            if ($poRow) {
                    $poMap = [
                        'name' => 'manual_name',
                        'email' => 'manual_email',
                        'mobile' => 'manual_mobile',
                        'gstin' => 'manual_gstin',
                        'company' => 'manual_company_name',
                        'address' => 'manual_company_address',
                        'city' => 'manual_city',
                        'state' => 'manual_state',
                        'pincode' => 'manual_pincode',
                        'country' => 'manual_country',
                    ];
                    foreach ($poMap as $key => $col) {
                        if (isset($poRow->{$col}) && trim((string) $poRow->{$col}) !== '') {
                            $base[$key] = trim((string) $poRow->{$col});
                        }
                    }
            }
            $wizardPoPrefill = $base !== [] ? $base : null;
        }
        $wizardOrderSummary = ($order && $num >= 2) ? $this->buildWizardOrderSummary($order) : null;
        $wizardPricing = $order ? $this->buildWizardPricing($order) : null;
        $wizardAadhaarMeta = $order ? $this->buildWizardAadhaarMeta($order) : null;
        $wizardOrderStatus = 'pending';
        $wizardOrderIsActive = false;
        $wizardOrderIsProcessing = false;  // ✅ Add this
        $wizardRejectionReason = '';
        if ($order) {
            $rawStatus = strtolower(trim((string) ($order->status ?? 'pending')));
            if ($rawStatus === 'cancelled') {
                $rawStatus = 'rejected';
            } elseif (in_array($rawStatus, ['active', 'paid', 'success'], true)) {
                $rawStatus = 'completed';
            }
            if (in_array($rawStatus, ['pending', 'completed', 'rejected', 'processing'], true)) {  // ✅ Add 'processing'
            $wizardOrderStatus = $rawStatus;
            }
            $wizardOrderIsActive = $wizardOrderStatus === 'completed';
            $wizardOrderIsProcessing = $wizardOrderStatus === 'processing';  // ✅ Set this
            

        }
        if ($wizardOrderStatus === 'rejected') {
            $wizardRejectionReason = trim((string) ($order->order_note ?? ''));
        }

        if ($num === 3 && $wizardOrderId !== null) {
            return redirect()
                ->route('superadmin.orders.create.step', ['step' => 2, 'id' => $wizardOrderId]);
        }

        $savedDomainName = $order ? trim((string) ($order->domain_name ?? '')) : '';
        if ($savedDomainName === '' && $wizardEditState !== null) {
            $savedDomainName = trim((string) ($wizardEditState['domain_name'] ?? ''));
        }
        $savedTransferDomain = '';
        if ($order) {
            $savedTransferDomain = strtolower(trim((string) ($order->transfer_domain ?? '')));
        }
        $savedDomainList = [];
        if ($savedDomainName !== '') {
            $decoded = json_decode($savedDomainName, true);
            if (is_array($decoded)) {
                $savedDomainList = array_values(array_filter(array_map(
                    static fn ($seg) => trim((string) $seg),
                    $decoded
                )));
            } else {
                $savedDomainList = array_values(array_filter(array_map(
                    'trim',
                    preg_split('/\s*[+,]\s*/', $savedDomainName) ?: []
                )));
            }
        }

        $formExtras = app(\App\Http\Controllers\SuperAdmin\SuperAdminOrderController::class)
            ->formExtras($order, $wizardOrderId, $num);

        $orderItemsForView = $formExtras['orderItems'] ?? [];
        if ($wizardEditState !== null && !empty($wizardEditState['plan_lines'])) {
            $fromEditState = $this->planLinesToOrderItems($wizardEditState['plan_lines']);
            if (count($fromEditState) >= count($orderItemsForView)) {
                $orderItemsForView = $fromEditState;
            }
        }
        $formExtras['orderItems'] = $orderItemsForView;
        $savedProviderId = 0;
        if ($order && !empty($order->provider_id)) {
            $savedProviderId = (int) $order->provider_id;
        }
        if ($savedProviderId <= 0 && $orderItemsForView !== []) {
            $savedProviderId = (int) ($orderItemsForView[0]['provider_id'] ?? 0);
        }
        if ($savedProviderId <= 0 && $wizardEditState !== null) {
            $savedProviderId = (int) ($wizardEditState['provider_id'] ?? 0);
        }

        $selectedCustomerLabel = '';
        if ($order && !empty($order->user_id)) {
            $uid = (int) $order->user_id;
            $selectedCustomer = $customers->firstWhere('id', $uid);
            if (!$selectedCustomer) {
                $selectedCustomer = Customer::query()->whereKey($uid)->first();
            }
            if ($selectedCustomer) {
                $name = trim((string) ($selectedCustomer->name ?? ''));
                $company = trim((string) ($selectedCustomer->company_name ?? ''));
                $selectedCustomerLabel = $company !== '' && $name !== ''
                    ? ($name . ' — ' . $company)
                    : ($name !== '' ? $name : ($company !== '' ? $company : ('Customer #' . $uid)));
            } else {
                $selectedCustomerLabel = 'Customer #' . $uid;
            }
        }

        $savedApplyGst = true;
        if ($order) {
            $savedApplyGst = (int) ($order->apply_gst ?? 1) === 1;
        }

        $orderCanBeCancelled = true;
        $orderCancellationBlockReason = null;
        $orderCancellationDeadline = null;
        if ($order) {
            if ($savedProviderId > 0) {
                $order->setAttribute('provider_id', $savedProviderId);
            }
            $cancellationPolicy = app(OrderCancellationPolicy::class);
            $orderCanBeCancelled = $cancellationPolicy->canCancel($order, $savedProviderId > 0 ? $savedProviderId : null);
            $orderCancellationBlockReason = $cancellationPolicy->blockMessage($order);
            $orderCancellationDeadline = $cancellationPolicy->cancellationDeadlineLabel(
                $order,
                'd-m-Y',
                $savedProviderId > 0 ? $savedProviderId : null
            );
        }

        return view('superadmin.b2b.new_plan', array_merge([
            'wizardMode' => true,
            'num' => $num,
            'id' => $wizardOrderId ? (string) $wizardOrderId : '',
            'wizardOrderId' => $wizardOrderId,
            'order' => $order,
            'partner' => $partner,
            'partners' => $partners,
            'customers' => $customers,
            'providers' => $providers,
            'mainVariantsByProvider' => $variantMeta['mainVariantsByProvider'],
            'subVariantsByMain' => $variantMeta['subVariantsByMain'],
            'hasPendingCreditRequest' => $hasPendingCreditRequest,
            'walletBalance' => $walletBalance,
            'aadhaarStepEnabled' => $aadhaarStepEnabled,
            'wizardEditState' => $wizardEditState,
            'isWizardEdit' => $isWizardEdit,
            'wizardPoPrefill' => $wizardPoPrefill,
            'wizardOrderSummary' => $wizardOrderSummary,
            'wizardPricing' => $wizardPricing,
            'wizardAadhaarMeta' => $wizardAadhaarMeta,
            'wizardOrderStatus' => $wizardOrderStatus,
            'wizardOrderIsActive' => $wizardOrderIsActive,
            'wizardOrderIsProcessing' => $wizardOrderIsProcessing,  // ✅ Pass to view
            'orderCanBeCancelled' => $orderCanBeCancelled,
            'orderCancellationBlockReason' => $orderCancellationBlockReason,
            'orderCancellationDeadline' => $orderCancellationDeadline,

            'wizardUserId' => $order ? (int) $order->user_id : null,
            'wizardRejectionReason' => $wizardRejectionReason,
            // Tab 1 order fields are view-only once an order id exists — except subscription dates.
            'orderStatusOnly' => $wizardOrderId !== null,
            // PO remains editable during create (pending/processing) until order is locked.
            'poReadonly' => $wizardOrderId !== null && (
                $wizardOrderIsActive
                || in_array($wizardOrderStatus, ['rejected', 'cancelled', 'canceled', 'failed'], true)
            ),
            'successRedirectUrl' => route('superadmin.orders.index'),
            'storeUrl' => route('superadmin.orders.save'),
            'hasTransferDomainColumn' => true,
            'savedDomainName' => $savedDomainName,
            'savedTransferDomain' => $savedTransferDomain,
            'savedDomainList' => $savedDomainList,
            'savedPromoCode' => $order
                ? trim((string) ($order->promocode ?? $order->promo_code ?? ''))
                : '',
            'savedProviderId' => $savedProviderId,
            'selectedCustomerLabel' => $selectedCustomerLabel,
            'savedApplyGst' => $savedApplyGst,
        ], $formExtras));
    }

    public function create(int $partner_id)
    {
        $partner = PartnerRecord::findOrFail($partner_id);

        $hasPendingCreditRequest = Schema::hasTable('credit_requests')
            && CreditRequest::query()
                ->where('partner_id', $partner_id)
                ->where('status', 'pending')
                ->exists();

        $customers = Customer::query()
            ->where('partner_id', $partner_id)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'mobile', 'company_name']);

        $providers = Provider::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $variantMeta = $this->buildProviderVariantMeta();

        $formExtras = app(\App\Http\Controllers\SuperAdmin\SuperAdminOrderController::class)
            ->formExtras(null, null, 1);

        return view('superadmin.b2b.new_plan', array_merge([
            'wizardMode' => false,
            'num' => 1,
            'id' => '',
            'wizardOrderId' => null,
            'order' => null,
            'partner' => $partner,
            'partners' => collect(),
            'customers' => $customers,
            'providers' => $providers,
            'mainVariantsByProvider' => $variantMeta['mainVariantsByProvider'],
            'subVariantsByMain' => $variantMeta['subVariantsByMain'],
            'hasPendingCreditRequest' => $hasPendingCreditRequest,
            'walletBalance' => (float) ($partner->wallet_balance ?? 0),
            'successRedirectUrl' => route('superadmin.b2b_partners.customers.list', ['partner_id' => $partner_id]),
            'storeUrl' => route('superadmin.orders.save'),
        ], $formExtras));
    }

    public function saveStep(Request $request, int $step = 1, ?int $id = null)
    {
        if ($step === 1 && $id === null) {
            return $this->saveWizardStepOne($request);
        }

        if ($step === 1 && $id !== null) {
            return $this->saveWizardStepOneEdit($request, (int) $id);
        }

        return app(BillingController::class)->saveStep($request, $step, $id);
    }

    public function store(Request $request, PartnerB2bNewPlanCheckoutService $checkout, int $partner_id)
    {
        return $this->processStore($request, $checkout, $partner_id);
    }

    public function partnerContext(int $partner_id)
    {
        $partner = PartnerRecord::findOrFail($partner_id);

        return response()->json([
            'success' => true,
            'partner' => [
                'id' => (int) $partner->id,
                'name' => (string) ($partner->company_name ?? $partner->name ?? ''),
                'wallet_balance' => (float) ($partner->wallet_balance ?? 0),
            ],
            'customers' => $this->customersForPartner($partner_id),
            'has_pending_credit_request' => $this->partnerHasPendingCredit($partner_id),
        ]);
    }

    public function providerCustomers(int $partner_id, int $provider_id)
    {
        PartnerRecord::findOrFail($partner_id);
        Provider::query()->where('status', 'active')->findOrFail($provider_id);

        return response()->json([
            'success' => true,
            'customers' => $this->customersForPartnerProvider($partner_id, $provider_id),
        ]);
    }

    private function saveWizardStepOne(Request $request)
    {
        $partnerId = (int) $request->input('partner_id', 0);
        if ($partnerId <= 0) {
            return response()->json([
                'error' => true,
                'message' => 'Please select a partner.',
                'details' => ['partner_id' => 'Partner is required.'],
            ], 422);
        }

        $planLines = $this->normalizePlanLines($request);
        if ($planLines === []) {
            return response()->json([
                'error' => true,
                'message' => 'Add at least one plan.',
                'details' => ['plans' => 'Select at least one plan.'],
            ], 422);
        }

        $checkout = app(PartnerB2bNewPlanCheckoutService::class);
        $orderIds = [];

        foreach ($planLines as $index => $line) {
            $lineRequest = clone $request;
            $lineRequest->merge([
                'plan_id' => $line['plan_id'],
                'quantity' => $line['quantity'],
                'licenses' => $line['quantity'],
            ]);

            $response = $this->processStore($lineRequest, $checkout, $partnerId, true, false);
            if ($response->getStatusCode() !== 200 && $response->getStatusCode() !== 201) {
                $payload = json_decode($response->getContent(), true) ?: [];
                $details = $payload['details'] ?? [];
                if (is_array($details)) {
                    $prefixed = [];
                    foreach ($details as $key => $msg) {
                        $prefixed['plans.' . $index . '.' . $key] = $msg;
                    }
                    $details = $prefixed;
                }

                return response()->json([
                    'error' => true,
                    'message' => $payload['message'] ?? 'Could not create order for plan line ' . ($index + 1) . '.',
                    'details' => $details,
                ], $response->getStatusCode());
            }

            $payload = json_decode($response->getContent(), true) ?: [];
            if (!empty($payload['order_id'])) {
                $orderIds[] = (int) $payload['order_id'];
            }
        }

        $firstOrderId = $orderIds[0] ?? 0;
        if ($firstOrderId <= 0) {
            return response()->json([
                'error' => true,
                'message' => 'Orders could not be created.',
            ], 500);
        }

        $message = count($orderIds) > 1
            ? count($orderIds) . ' orders created. Continue with PO details for the first order.'
            : 'Order created. Continue with PO details.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'redirect_url' => route('superadmin.orders.create.step', ['step' => 2, 'id' => $firstOrderId]),
            'order_id' => $firstOrderId,
            'order_ids' => $orderIds,
        ]);
    }

    /**
     * @return list<array{plan_id: int, quantity: int}>
     */
    private function normalizePlanLines(Request $request): array
    {
        $raw = $request->input('plans', []);
        if (!is_array($raw)) {
            $raw = [];
        }

        $lines = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $planId = (int) ($row['plan_id'] ?? 0);
            $qty = (int) ($row['quantity'] ?? $row['licenses'] ?? 0);
            if ($planId <= 0 || $qty < 1) {
                continue;
            }
            $lines[] = ['plan_id' => $planId, 'quantity' => $qty];
        }

        if ($lines !== []) {
            return $lines;
        }

        $singlePlan = (int) $request->input('plan_id', 0);
        $singleQty = (int) $request->input('quantity', $request->input('licenses', 0));
        if ($singlePlan > 0 && $singleQty >= 1) {
            return [['plan_id' => $singlePlan, 'quantity' => $singleQty]];
        }

        return [];
    }

    private function processStore(
        Request $request,
        PartnerB2bNewPlanCheckoutService $checkout,
        int $partner_id,
        bool $wizardJson = false,
        bool $wizardRedirect = true
    ) {
        PartnerRecord::findOrFail($partner_id);

        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'user_id' => 'required|exists:customers,id',
            'domain_name' => 'required|string|max:255',
            'quantity' => 'required|integer|min:1',
            'licenses' => 'nullable|integer|min:1',
            'provider_id' => 'nullable|integer|exists:providers,id',
            'apply_gst' => 'nullable|boolean',
            'gst_rate' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'promocode' => 'nullable|string',
            'order_note' => 'nullable|string',
            'status' => 'nullable|string',
            'aadhaar_number' => 'nullable|string|max:20',
            'po_name' => 'nullable|string|max:255',
            'po_email' => 'nullable|email|max:255',
            'po_mobile' => 'nullable|string|max:20',
            'po_gstin' => 'nullable|string|max:20',
            'po_company_name' => 'nullable|string|max:255',
            'po_company_address' => 'nullable|string',
            'po_city' => 'nullable|string|max:100',
            'po_state' => 'nullable|string|max:100',
            'po_country' => 'nullable|string|max:100',
            'po_pincode' => 'nullable|string|max:20',
            'subscription_start_date' => 'nullable|date',
            'subscription_end_date' => 'nullable|date|after_or_equal:subscription_start_date',
        ]);

        $customer = Customer::findOrFail((int) $validated['user_id']);
        if ((int) $customer->partner_id !== $partner_id) {
            if ($request->wantsJson() || $request->ajax() || $wizardJson) {
                return response()->json([
                    'error' => true,
                    'message' => 'Customer does not belong to this partner.',
                    'details' => ['user_id' => 'Customer does not belong to this partner.'],
                ], 422);
            }

            return back()->withInput()->with('error', 'Customer does not belong to this partner.');
        }

        $validated['apply_gst'] = $request->boolean('apply_gst', true);
        $validated['order_type'] = 'B2B';

        try {
            $result = $checkout->processCheckout($validated, $partner_id);
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->wantsJson() || $request->ajax() || $wizardJson) {
                return response()->json([
                    'error' => true,
                    'message' => 'Validation failed',
                    'details' => $e->errors(),
                ], 422);
            }
            throw $e;
        }

        if (empty($result['ok'])) {
            if ($request->wantsJson() || $request->ajax() || $wizardJson) {
                return response()->json([
                    'error' => true,
                    'message' => $result['message'] ?? 'Insufficient Credits',
                    'code' => $result['code'] ?? 'insufficient_credits',
                    'credit_request_id' => $result['credit_request_id'] ?? null,
                    'request_credits_url' => $result['request_credits_url'] ?? null,
                    'total_amount' => $result['total_amount'] ?? null,
                    'wallet_balance' => $result['wallet_balance'] ?? null,
                ], 422);
            }

            return back()
                ->withInput()
                ->with('error', ($result['message'] ?? 'Insufficient Credits') . ' A credit request was recorded where applicable.');
        }

        $orderId = (int) ($result['order_id'] ?? 0);

        if ($request->wantsJson() || $request->ajax() || $wizardJson) {
            $payload = [
                'success' => true,
                'message' => $result['message'] ?? 'Order created successfully',
                'order_id' => $orderId,
            ];
            if ($wizardRedirect) {
                $payload['redirect_url'] = route('superadmin.orders.create.step', ['step' => 2, 'id' => $orderId]);
            }

            return response()->json($payload);
        }

        return redirect()
            ->route('superadmin.orders.details', ['id' => $orderId])
            ->with('success', 'Order placed successfully.');
    }

    /**
     * @return \Illuminate\Support\Collection<int, object{id: int, name: string, wallet_balance: float}>
     */
    private function customersForPartner(int $partnerId)
    {
        return Customer::query()
            ->where('partner_id', $partnerId)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'mobile', 'company_name']);
    }

    /**
     * Customers for a partner scoped to a provider (via past orders). Falls back to all partner customers if none yet.
     */
    private function customersForPartnerProvider(int $partnerId, int $providerId)
    {
        $planIds = Plan::query()->where('provider_id', $providerId)->pluck('id')->all();

        $customerIds = Order::query()
            ->where('partner_id', $partnerId)
            ->whereNotNull('user_id')
            ->where('user_id', '>', 0)
            ->where(function ($q) use ($providerId, $planIds) {
                $q->where('provider_id', $providerId);
                if ($planIds !== []) {
                    $q->orWhereIn('plan_id', $planIds);
                }
            })
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        $query = Customer::query()
            ->where('partner_id', $partnerId)
            ->orderBy('name');

        if ($customerIds !== []) {
            $query->whereIn('id', $customerIds);
        }

        return $query->get(['id', 'name', 'email', 'mobile', 'company_name']);
    }

    private function partnerHasPendingCredit(int $partnerId): bool
    {
        return Schema::hasTable('credit_requests')
            && CreditRequest::query()
                ->where('partner_id', $partnerId)
                ->where('status', 'pending')
                ->exists();
    }

    private function orderPoJourneyStatus(?object $order): string
    {
        if (!$order) {
            return 'po_pending';
        }

        $orderId = (int) ($order->id ?? 0);
        if ($orderId > 0) {
            $poRow = OrderPoDetail::query()->where('order_id', $orderId)->first();
            if ($poRow && trim((string) ($poRow->journey_status ?? '')) !== '') {
                return strtolower(trim((string) $poRow->journey_status));
            }
        }

        return 'po_pending';
    }

    private function isPoSubmitted(?object $order): bool
    {
        return in_array($this->orderPoJourneyStatus($order), ['po_done', 'aadhar_done', 'otp_done', 'placed'], true);
    }

    /**
     * @return array{verified: bool, masked: string, ref_no: string, verified_at: mixed, journey_status: string}
     */
    private function buildWizardAadhaarMeta(object $order): array
    {
        $orderId = (int) ($order->id ?? 0);
        $verified = false;
        $masked = '';
        $refNo = '';
        $verifiedAt = null;

        if ($orderId > 0) {
            $po = OrderPoDetail::query()->where('order_id', $orderId)->first();
            if ($po) {
                $verified = (int) ($po->aadhar_verified ?? 0) === 1;
                $masked = (string) ($po->aadhaar_masked ?? '');
                $refNo = (string) ($po->aadhaar_ref_no ?? '');
                $verifiedAt = $po->aadhar_verified_at ?? null;
            }
        }

        $journey = $this->orderPoJourneyStatus($order);
        if (!$verified && in_array($journey, ['aadhar_done', 'otp_done', 'placed'], true)) {
            $verified = true;
        }
        if (!$verified) {
            $verified = strtolower(trim((string) ($order->status ?? ''))) === 'completed';
        }

        $digits = preg_replace('/\D/', '', $masked) ?? '';
        if (strlen($digits) >= 5) {
            $masked = 'xxxxx' . substr($digits, -5);
        } elseif (strlen($digits) >= 4) {
            $masked = 'xxxx' . substr($digits, -4);
        }

        $rawNumber = '';
        if ($orderId > 0) {
            $stored = PartnerAadhaarVerification::query()
                ->where('order_id', $orderId)
                ->orderByDesc('id')
                ->value('aadhaar_number');
            $rawNumber = preg_replace('/\D/', '', (string) ($stored ?? '')) ?? '';
        }

        return [
            'verified' => $verified,
            'masked' => $masked,
            'ref_no' => $refNo,
            'verified_at' => $verifiedAt,
            'journey_status' => $this->orderPoJourneyStatus($order),
            'raw_number' => $rawNumber,
        ];
    }

    /**
     * Existing orders: store Aadhaar on file without OTP / external verification.
     */
    public function saveAadhaar(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => ['required', 'integer', 'min:1'],
            'aadhaar_number' => ['required', 'string', 'max:20'],
            'order_category' => ['nullable', 'string', 'max:32'],
        ]);

        if (!Schema::hasTable('orders')) {
            return response()->json(['success' => false, 'message' => 'Orders table not available.'], 422);
        }

        $orderId = (int) $request->input('order_id');
        $order = DB::table('orders')->where('id', $orderId)->first();
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $requestCategory = strtolower(trim((string) $request->input('order_category', '')));
        if (!$this->isExistingOrderForAadhaarSave($order, $requestCategory)) {
            return response()->json([
                'success' => false,
                'message' => 'Select order category Existing on step 1, save the order, then save Aadhaar here.',
            ], 422);
        }

        $aadhaar = preg_replace('/\D/', '', (string) $request->input('aadhaar_number', '')) ?? '';
        if (strlen($aadhaar) !== 12) {
            return response()->json([
                'success' => false,
                'message' => 'Aadhaar number must be 12 digits.',
            ], 422);
        }

        $masked = 'XXXXXXXX' . substr($aadhaar, -4);
        $now = now();

        $orderCols = Schema::getColumnListing('orders');
        $orderUpdate = [];
        if (in_array('aadhaar_number', $orderCols, true)) {
            $orderUpdate['aadhaar_number'] = $aadhaar;
        }
        if (in_array('aadhaar_masked', $orderCols, true)) {
            $orderUpdate['aadhaar_masked'] = $masked;
        }
        if (in_array('order_category', $orderCols, true)) {
            $dbCat = strtolower(trim((string) ($order->order_category ?? 'new')));
            if ($requestCategory === 'existing' || !in_array($dbCat, ['renewal', 'renewed', 'upgrade', 'upgraded', 'downgrade', 'existing'], true)) {
                $orderUpdate['order_category'] = 'renewal';
            }
        }
        if (in_array('aadhar_verified', $orderCols, true)) {
            $orderUpdate['aadhar_verified'] = 0;
        }
        if (in_array('status', $orderCols, true)) {
            $orderUpdate['status'] = 'completed';
        }
        if (in_array('updated_at', $orderCols, true)) {
            $orderUpdate['updated_at'] = $now;
        }
        if ($orderUpdate !== []) {
            DB::table('orders')->where('id', $orderId)->update($orderUpdate);
        }

        OrderPoDetail::query()->updateOrCreate(
            ['order_id' => $orderId],
            [
                'aadhaar_masked' => $masked,
                'aadhar_verified' => 0,
                'journey_status' => 'placed',
            ]
        );

        if (Schema::hasTable('orders')) {
            app(BillingController::class)->syncOrderPoJourneyOnOrdersTable($orderId, 'placed');
        }

        $partnerId = (int) ($order->partner_id ?? 0);
        $customerId = (int) ($order->user_id ?? 0);
        if ($partnerId > 0 && $customerId > 0 && Schema::hasTable('partner_aadhaar_verifications')) {
            $this->upsertUnverifiedAadhaarRecord($partnerId, $customerId, $orderId, $aadhaar, $masked);
        }

        $message = 'Aadhaar saved. Order marked as completed.';
        session()->flash('success', $message);

        return response()->json([
            'success' => true,
            'message' => $message,
            'redirect_url' => route('superadmin.orders.index'),
            'data' => [
                'masked' => $masked,
                'verified' => false,
            ],
        ]);
    }

    private function isExistingOrderForAadhaarSave(object $order, ?string $requestCategory = null): bool
    {
        $req = strtolower(trim((string) ($requestCategory ?? '')));
        if ($req === 'existing' || in_array($req, ['renewal', 'renewed', 'upgrade', 'upgraded', 'downgrade'], true)) {
            return true;
        }

        $cat = strtolower(trim((string) ($order->order_category ?? 'new')));

        return in_array($cat, ['existing', 'renewal', 'renewed', 'upgrade', 'upgraded', 'downgrade'], true);
    }

    private function upsertUnverifiedAadhaarRecord(
        int $partnerId,
        int $customerId,
        int $orderId,
        string $aadhaar,
        string $masked
    ): void {
        $cols = Schema::getColumnListing('partner_aadhaar_verifications');
        $now = now();
        $payload = array_intersect_key([
            'customer_id' => $customerId,
            'partner_id' => $partnerId > 0 ? $partnerId : null,
            'order_id' => $orderId,
            'aadhaar_number' => $aadhaar,
            'aadhaar_masked' => $masked,
            'verification_mode' => 'legacy_manual',
            'status' => 'init',
            'verified_at' => null,
            'aadhaar_ref_no' => 'exist-' . $orderId,
            'updated_at' => $now,
        ], array_flip($cols));

        $existingId = (int) DB::table('partner_aadhaar_verifications')
            ->where('order_id', $orderId)
            ->orderByDesc('id')
            ->value('id');

        if ($existingId > 0) {
            DB::table('partner_aadhaar_verifications')->where('id', $existingId)->update($payload);
        } else {
            if (in_array('created_at', $cols, true)) {
                $payload['created_at'] = $now;
            }
            DB::table('partner_aadhaar_verifications')->insert($payload);
        }
    }

    /**
     * PO / customer display for wizard step 2 — primary source is customers (+ company_details).
     *
     * @return array<string, string>|null
     */
    private function buildWizardPoPrefill(object $order): ?array
    {
        $userId = (int) ($order->user_id ?? 0);
        if ($userId <= 0) {
            return null;
        }

        $customerPrefill = app(RenewalService::class)->buildPurchaseOrderPrefillFromCustomer($userId);
        if ($customerPrefill === []) {
            return null;
        }

        $prefill = [
            'customer_id' => (string) $userId,
            'name' => trim((string) ($customerPrefill['name'] ?? '')),
            'email' => trim((string) ($customerPrefill['email'] ?? '')),
            'mobile' => trim((string) ($customerPrefill['mobile'] ?? '')),
            'gstin' => trim((string) ($customerPrefill['gstin'] ?? '')),
            'company' => trim((string) ($customerPrefill['company_name'] ?? '')),
            'address' => trim((string) ($customerPrefill['address'] ?? '')),
            'city' => trim((string) ($customerPrefill['city'] ?? '')),
            'state' => trim((string) ($customerPrefill['state'] ?? '')),
            'pincode' => trim((string) ($customerPrefill['postcode'] ?? '')),
            'country' => trim((string) ($customerPrefill['country'] ?? '')),
            'pan_no' => trim((string) ($customerPrefill['pan_no'] ?? '')),
        ];

        if ($prefill['country'] === '') {
            $prefill['country'] = 'India';
        }

        return $prefill;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildWizardOrderSummary(object $order): ?array
    {
        $orderId = (int) ($order->id ?? 0);
        if ($orderId <= 0) {
            return null;
        }

        $partnerName = '';
        if (!empty($order->partner_id) && Schema::hasTable('partners')) {
            $partnerName = (string) (DB::table('partners')->where('id', (int) $order->partner_id)->value('name') ?? '');
        }

        $planLines = [];
        foreach ($this->relatedB2bWizardOrders($order) as $row) {
            foreach ($this->resolvePlanLineFromOrder($row) as $line) {
                $planName = '';
                if (!empty($line['plan_id']) && Schema::hasTable('plans')) {
                    $planName = (string) (DB::table('plans')->where('id', (int) $line['plan_id'])->value('name') ?? '');
                }
                $planLines[] = [
                    'order_id' => (int) $row->id,
                    'plan_name' => $planName,
                    'quantity' => (int) ($line['quantity'] ?? 1),
                    'unit_price' => (float) ($line['unit_price'] ?? 0),
                ];
            }
        }

        $status = strtolower(trim((string) ($order->status ?? 'pending')));

        $pricing = $this->buildWizardPricing($order);
        $poDetail = OrderPoDetail::query()->where('order_id', $orderId)->first();
        $poStatus = strtolower(trim((string) ($poDetail->status ?? '')));
        if ($poStatus === 'pending') {
            $po_journey_status = 'po draft';
        } elseif ($poStatus === 'active') {
            $po_journey_status = 'po done';
        } else {
            $po_journey_status = 'po pending';
        }

        return [
            'order_id' => $orderId,
            'order_no' => trim((string) ($order->order_no ?? '')),
            'partner_id' => (int) ($order->partner_id ?? 0),
            'partner_name' => $partnerName,
            'user_id' => (int) ($order->user_id ?? 0),
            'domain_name' => trim((string) ($order->domain_name ?? '')),
            'status' => $status,
            'total_amount' => (float) ($pricing['total'] ?? 0),
            'plan_lines' => $planLines,
            'po_journey_status' => $po_journey_status,
            'po_link' => $poDetail->po_document_url ?? null,
        ];
    }

    /**
     * @return array{subtotal: float, gst: float, total: float, apply_gst: bool, gst_rate: float}
     */
    private function buildWizardPricing(object $order): array
    {
        $subtotal = 0.0;
        $gst = 0.0;
        $total = 0.0;
        $applyGst = (int) ($order->apply_gst ?? 1) === 1;
        $gstRate = isset($order->gst_rate) ? (float) $order->gst_rate : GstSplit::RATE;
        if ($gstRate <= 0) {
            $gstRate = GstSplit::RATE;
        }

        foreach ($this->relatedB2bWizardOrders($order) as $row) {
            if (isset($row->subtotal)) {
                $subtotal += (float) $row->subtotal;
            } elseif (isset($row->price)) {
                $qty = (int) ($row->quantity ?? 0) > 0 ? (int) $row->quantity : 1;
                $subtotal += (float) $row->price * $qty;
            }

            foreach (['gst_amount', 'tax_amount'] as $gstCol) {
                if (isset($row->{$gstCol})) {
                    $gst += (float) $row->{$gstCol};
                    break;
                }
            }

            foreach (['total_amount', 'amount'] as $totalCol) {
                if (isset($row->{$totalCol}) && (float) $row->{$totalCol} > 0) {
                    $total += (float) $row->{$totalCol};
                    break;
                }
            }
        }

        if ($subtotal <= 0 && $total > 0) {
            $subtotal = max(0.0, $total - $gst);
        }
        if ($total <= 0) {
            $total = $subtotal + $gst;
        }
        $discountAmount = 0.0;
        foreach ($this->relatedB2bWizardOrders($order) as $row) {
            $discountAmount += (float) ($row->discount_amount ?? 0);
        }

        $grossSubtotal = round($subtotal, 2);
        if ($grossSubtotal <= 0 && $total > 0) {
            $grossSubtotal = round(max(0, $total - $gst + $discountAmount), 2);
        }

        $taxable = round(max(0, $grossSubtotal - $discountAmount), 2);
        $gstBroken = $gst > ($taxable + 0.05)
            || ($total > 0 && $gst > ($total + 0.05))
            || ($total > 0 && abs(($taxable + $gst) - $total) > 1.0);

        // Prefer orders.total_amount (listing) when tax columns are inconsistent.
        if ($total > 0 && $gstBroken) {
            if ($applyGst) {
                $taxable = round($total * 100 / (100 + $gstRate), 2);
                $gst = round($total - $taxable, 2);
                if ($grossSubtotal > 0) {
                    $impliedDiscount = round($grossSubtotal - $taxable, 2);
                    $discountAmount = $impliedDiscount >= 0 ? $impliedDiscount : 0.0;
                    if ($impliedDiscount < 0) {
                        $grossSubtotal = $taxable;
                    }
                } else {
                    $grossSubtotal = $taxable;
                    $discountAmount = 0.0;
                }
            } else {
                $gst = 0.0;
                $taxable = $total;
                if ($grossSubtotal <= 0) {
                    $grossSubtotal = $total;
                }
                $discountAmount = max(0.0, round($grossSubtotal - $taxable, 2));
            }
        } else {
            if ($gst <= 0 && $applyGst && $taxable > 0) {
                $gst = round($taxable * $gstRate / 100, 2);
            }
            if ($total <= 0) {
                $total = round($taxable + $gst, 2);
            }
        }

        $customerGstin = trim((string) ($order->po_manual_gstin ?? $order->customer_gstin ?? ''));
        $poRow = OrderPoDetail::query()->where('order_id', (int) ($order->id ?? 0))->first();
        if ($customerGstin === '' && $poRow) {
            $customerGstin = trim((string) ($poRow->manual_gstin ?? ''));
        }
        $split = GstSplit::split($gst, $customerGstin);

        return [
            'subtotal' => $grossSubtotal,
            'discount_amount' => round($discountAmount, 2),
            'taxable_amount' => round(max(0, $grossSubtotal - $discountAmount), 2),
            'gst' => round($gst, 2),
            'total' => round($total, 2),
            'apply_gst' => $applyGst,
            'gst_rate' => $gstRate,
            'tax_type' => $split['tax_type'],
            'cgst' => $split['cgst'],
            'sgst' => $split['sgst'],
            'igst' => $split['igst'],
        ];
    }

    /**
     * @param  array<string, string>  $prefill
     */
    private function wizardPoPrefillIsComplete(array $prefill): bool
    {
        return trim((string) ($prefill['name'] ?? '')) !== ''
            && trim((string) ($prefill['email'] ?? '')) !== '';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildWizardEditState(object $order): ?array
    {
        $planLines = [];
        foreach ($this->relatedB2bWizardOrders($order) as $row) {
            foreach ($this->resolvePlanLineFromOrder($row) as $line) {
                $planLines[] = $line;
            }
        }

        if ($planLines === []) {
            return null;
        }

        $providerId = (int) ($planLines[0]['provider_id'] ?? 0);
        $applyGst = (int) ($order->apply_gst ?? 1) === 1;
        $gstRate = isset($order->gst_rate) ? (float) $order->gst_rate : GstSplit::RATE;
        if ($gstRate <= 0) {
            $gstRate = GstSplit::RATE;
        }

        $subStart = '';
        $subEnd = '';
        foreach (['subscription_start_date', 'subscription_start'] as $col) {
            if (!empty($order->{$col})) {
                try {
                    $subStart = Carbon::parse($order->{$col})->format('Y-m-d');
                    break;
                } catch (\Throwable $e) {
                }
            }
        }
        foreach (['subscription_end_date', 'subscription_end'] as $col) {
            if (!empty($order->{$col})) {
                try {
                    $subEnd = Carbon::parse($order->{$col})->format('Y-m-d');
                    break;
                } catch (\Throwable $e) {
                }
            }
        }

        return [
            'order_id' => (int) $order->id,
            'partner_id' => (int) ($order->partner_id ?? 0),
            'user_id' => (int) ($order->user_id ?? 0),
            'domain_name' => trim((string) ($order->domain_name ?? '')),
            'transfer_domain' => strtolower(trim((string) ($order->transfer_domain ?? ''))),
            'provider_id' => $providerId,
            'apply_gst' => $applyGst,
            'gst_rate' => $gstRate,
            'subscription_start_date' => $subStart,
            'subscription_end_date' => $subEnd,
            'plan_lines' => $planLines,
        ];
    }

    /**
     * @return list<object>
     */
    private function relatedB2bWizardOrders(object $anchor): array
    {
        $anchorId = (int) ($anchor->id ?? 0);
        $partnerId = (int) ($anchor->partner_id ?? 0);
        $userId = (int) ($anchor->user_id ?? 0);
        $domain = trim((string) ($anchor->domain_name ?? ''));

        if ($anchorId <= 0) {
            return [];
        }

        $rows = [$anchor];
        $seen = [$anchorId => true];

        if ($partnerId > 0 && $userId > 0 && $domain !== '' && !empty($anchor->created_at)) {
            $from = Carbon::parse((string) $anchor->created_at)->subMinutes(5);
            $to = Carbon::parse((string) $anchor->created_at)->addMinutes(5);

            $query = Order::query()
                ->where('partner_id', $partnerId)
                ->where('user_id', $userId)
                ->where('domain_name', $domain)
                ->whereBetween('created_at', [$from, $to])
                ->whereRaw('LOWER(order_type) = ?', ['b2b']);

            foreach ($query->orderBy('id')->get() as $related) {
                $rid = (int) $related->id;
                if ($rid > 0 && empty($seen[$rid])) {
                    $seen[$rid] = true;
                    $rows[] = $related;
                }
            }
        }

        usort($rows, static fn ($a, $b) => (int) $a->id <=> (int) $b->id);

        return $rows;
    }

    /**
     * Expand an order into one or more plan lines.
     *
     * When `order_items` has rows for this order → one line per item (multi-plan
     * orders from cart / legacy-manual / home-flow). When it doesn't → fall back
     * to a single line built from the `orders.plan_id` snapshot.
     *
     * @return list<array<string, mixed>>
     */
    private function resolvePlanLineFromOrder(object $order): array
    {
        $orderId = (int) ($order->id ?? 0);

        if ($orderId > 0) {
            $itemRows = OrderItem::query()
                ->where('order_id', $orderId)
                ->orderBy('id')
                ->get();

            $lines = [];
            foreach ($itemRows as $itemRow) {
                $itemPlanId = (int) ($itemRow->plan_id ?? 0);
                if ($itemPlanId <= 0) {
                    continue;
                }

                $planRow = Plan::query()->whereKey($itemPlanId)->first();
                if (!$planRow) {
                    continue;
                }

                $unitPrice = (float) ($itemRow->unit_price ?? 0);
                if ($unitPrice <= 0) {
                    $unitPrice = (float) ($planRow->price ?? 0);
                }

                $itemQty = max(1, (int) ($itemRow->quantity ?? 1));
                $lines[] = $this->planLineFromPlanRow($order, $planRow, $unitPrice, $itemQty);
            }

            if ($lines !== []) {
                return $lines;
            }
        }

        $planId = (int) ($order->plan_id ?? 0);
        if ($planId <= 0) {
            return [];
        }

        $planRow = Plan::query()->whereKey($planId)->first();
        if (!$planRow) {
            return [];
        }

        $qty = max(1, (int) ($order->quantity ?? $order->licenses ?? $order->user_quantity ?? 1));
        $unitPrice = (float) ($planRow->price ?? 0);
        if ((float) ($order->price ?? 0) > 0) {
            $unitPrice = (float) $order->price;
        }

        return [$this->planLineFromPlanRow($order, $planRow, $unitPrice, $qty)];
    }

    /**
     * Build a single wizard plan-line array from a plans row + qty/price.
     *
     * @return array<string, mixed>
     */
    private function planLineFromPlanRow(object $order, object $planRow, float $unitPrice, int $qty): array
    {
        $providerId = (int) ($planRow->provider_id ?? 0);
        $variantId = (int) ($planRow->variant_id ?? 0);
        $mainVariantId = 0;
        $subVariantId = 0;

        if ($variantId > 0) {
            $vRow = Variant::query()->whereKey($variantId)->first(['id', 'parent_variant_id']);
            if ($vRow) {
                if (!empty($vRow->parent_variant_id)) {
                    $mainVariantId = (int) $vRow->parent_variant_id;
                    $subVariantId = (int) $vRow->id;
                } else {
                    $mainVariantId = (int) $vRow->id;
                }
            }
        }

        $itemName = (string) ($planRow->name ?? $planRow->plan_name ?? 'Plan');

        return [
            'order_id' => (int) ($order->id ?? 0),
            'plan_id' => (int) $planRow->id,
            'provider_id' => $providerId,
            'main_variant_id' => $mainVariantId,
            'sub_variant_id' => $subVariantId,
            'variant_id' => $subVariantId > 0 ? $subVariantId : $mainVariantId,
            'item_name' => $itemName,
            'quantity' => max(1, $qty),
            'unit_price' => $unitPrice,
        ];
    }

    /**
     * Map wizard plan_lines to the orderItems shape expected by new_plan.blade.php.
     *
     * @param  list<array<string, mixed>>  $planLines
     * @return list<array<string, mixed>>
     */
    private function planLinesToOrderItems(array $planLines): array
    {
        $items = [];
        foreach ($planLines as $line) {
            $planId = (int) ($line['plan_id'] ?? 0);
            if ($planId <= 0) {
                continue;
            }

            $itemName = trim((string) ($line['item_name'] ?? ''));
            $description = (string) ($line['description'] ?? '');
            $hsnSac = (string) ($line['hsn_sac'] ?? '');
            $providerId = (int) ($line['provider_id'] ?? 0);
            $mainVariantId = (int) ($line['main_variant_id'] ?? 0);
            $subVariantId = (int) ($line['sub_variant_id'] ?? 0);
            $variantId = (int) ($line['variant_id'] ?? 0);

            if ($providerId <= 0 || $variantId <= 0 || $itemName === '' || $description === '' || $hsnSac === '') {
                $plan = Plan::query()->whereKey($planId)->first();
                if ($plan) {
                    if ($itemName === '') {
                        $itemName = (string) ($plan->name ?? $plan->title ?? 'Plan');
                    }
                    if ($description === '') {
                        $description = (string) ($plan->description ?? '');
                    }
                    if ($hsnSac === '') {
                        $hsnSac = (string) ($plan->hsn_sac ?? $plan->hsn ?? '');
                    }
                    if ($providerId <= 0) {
                        $providerId = (int) ($plan->provider_id ?? 0);
                    }
                    if ($variantId <= 0) {
                        $vid = (int) ($plan->variant_id ?? 0);
                        if ($vid > 0) {
                            $v = Variant::query()->whereKey($vid)->first();
                            if ($v && !empty($v->parent_variant_id)) {
                                $mainVariantId = (int) $v->parent_variant_id;
                                $subVariantId = $vid;
                            } else {
                                $mainVariantId = $vid;
                            }
                            $variantId = $vid;
                        }
                    }
                }
            }

            $unitPrice = round((float) ($line['unit_price'] ?? 0), 2);
            $items[] = [
                'plan_id' => $planId,
                'provider_id' => $providerId,
                'main_variant_id' => $mainVariantId,
                'sub_variant_id' => $subVariantId,
                'variant_id' => $variantId > 0 ? $variantId : ($subVariantId > 0 ? $subVariantId : $mainVariantId),
                'item_name' => $itemName !== '' ? $itemName : 'Plan',
                'description' => $description,
                'hsn_sac' => $hsnSac,
                'quantity' => max(1, (int) ($line['quantity'] ?? 1)),
                'unit_price' => $unitPrice,
            ];
        }

        return $items;
    }

    private function saveWizardStepOneEdit(Request $request, int $orderId)
    {
        $planLines = $this->normalizePlanLines($request);
        if ($planLines === []) {
            return response()->json([
                'error' => true,
                'message' => 'Add at least one plan.',
                'details' => ['plans' => 'Select at least one plan.'],
            ], 422);
        }

        $billing = app(BillingController::class);
        $primaryLine = $planLines[0];
        $lineRequest = clone $request;
        $lineRequest->merge([
            'plan_id' => $primaryLine['plan_id'],
            'quantity' => $primaryLine['quantity'],
            'licenses' => $primaryLine['quantity'],
        ]);

        return $billing->saveStep($lineRequest, 1, $orderId);
    }

    /**
     * Main/sub variant options per provider (same structure as BillingController order form).
     *
     * @return array{mainVariantsByProvider: array<int|string, list<array{id: int, name: string}>>, subVariantsByMain: array<int|string, list<array{id: int, name: string}>>}
     */
    private function buildProviderVariantMeta(): array
    {
        $mainVariantsByProvider = [];
        $subVariantsByMain = [];

        $providerIds = Provider::query()
            ->where('status', 'active')
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        if ($providerIds === []) {
            return compact('mainVariantsByProvider', 'subVariantsByMain');
        }

        $vrows = Variant::query()
            ->whereIn('provider_id', $providerIds)
            ->orderBy('name')
            ->get(['id', 'provider_id', 'name', 'parent_variant_id']);

        foreach ($vrows as $v) {
            $pid = (int) $v->provider_id;
            $parentId = (int) ($v->parent_variant_id ?? 0);

            if ($parentId > 0) {
                if (!isset($subVariantsByMain[$parentId])) {
                    $subVariantsByMain[$parentId] = [];
                }
                $subVariantsByMain[$parentId][] = [
                    'id' => (int) $v->id,
                    'name' => (string) $v->name,
                ];
            } else {
                if (!isset($mainVariantsByProvider[$pid])) {
                    $mainVariantsByProvider[$pid] = [];
                }
                $mainVariantsByProvider[$pid][] = [
                    'id' => (int) $v->id,
                    'name' => (string) $v->name,
                ];
            }
        }

        return compact('mainVariantsByProvider', 'subVariantsByMain');
    }

    private function getPartnerOptions()
    {
        return PartnerRecord::query()
            ->select('id', 'name', 'wallet_balance', 'status')
            ->whereIn('status', ['approved'])
            ->orderByDesc('id')
            ->get()
            ->map(fn ($partner) => (object) [
                'id' => (int) $partner->id,
                'name' => $partner->name ?: "Partner #{$partner->id}",
                'wallet_balance' => (float) ($partner->wallet_balance ?? 0),
            ]);
    }
}
