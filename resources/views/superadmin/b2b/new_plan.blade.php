@extends('superadmin.layouts.app')

@php
    use Illuminate\Support\Facades\Crypt;

    $wizardMode = !empty($wizardMode);
    $num = (int) ($num ?? ($activeTab ?? 1));
    $num = max(1, min(3, $num));
    $wizardOrderId = $wizardOrderId ?? (isset($id) && $id !== '' ? (int) $id : null);
    $partnerResolved = !empty($partner);
    $partnerId = $partnerResolved ? (int) $partner->id : (int) ($order->partner_id ?? 0);
    $orderFormUrls = $orderFormUrls ?? [];

    $orderItems = $orderItems ?? [];
    $wizardEditState = $wizardEditState ?? null;
    $mainVariantsByProvider = $mainVariantsByProvider ?? [];
    $subVariantsByMain = $subVariantsByMain ?? [];
    $isEdit = !empty($isEdit) || $wizardOrderId > 0;
    $orderStatusOnly = !empty($orderStatusOnly) || $isEdit;
    $poReadonly = !empty($poReadonly);
    $orderCategory = strtolower(trim((string) ($orderCategory ?? ($order->order_category ?? 'new'))));
    $isExistingOrderCategoryUi = in_array($orderCategory, ['existing', 'renewal', 'renewed', 'upgrade', 'upgraded', 'downgrade'], true);
    $aadhaarTestMode = app(\App\Support\AadhaarVerificationGateway::class)->isTestMode();
    $aadhaarMockOtp = (string) (config('services.aadhaar.mock_otps')[0] ?? '123456');
    $poGenerated = !empty($poGenerated);
    $poDocumentUrl = $poDocumentUrl ?? '';
    $wizardPricing = $wizardPricing ?? null;
    $savedPromoCode = $savedPromoCode ?? '';
    $savedProviderId = (int) ($savedProviderId ?? 0);
    if ($savedProviderId <= 0 && !empty($orderItems[0]['provider_id'])) {
        $savedProviderId = (int) $orderItems[0]['provider_id'];
    }
    if ($savedProviderId <= 0 && !empty($wizardEditState['provider_id'])) {
        $savedProviderId = (int) $wizardEditState['provider_id'];
    }
    $selectedCustomerLabel = $selectedCustomerLabel ?? '';
    $savedApplyGst = $savedApplyGst ?? true;
    $orderCategoryLabels = [
        'new' => 'New',
        'renewal' => 'Renewal',
        'upgrade' => 'Upgrade',
        'downgrade' => 'Downgrade',
    ];

    $partnerEncryptedById = [];
    if (!empty($partners)) {
        foreach ($partners as $p) {
            $pid = (int) ($p->id ?? 0);
            if ($pid > 0) {
                $partnerEncryptedById[$pid] = Crypt::encrypt($pid);
            }
        }
    }
    if ($partnerId > 0 && !isset($partnerEncryptedById[$partnerId])) {
        $partnerEncryptedById[$partnerId] = Crypt::encrypt($partnerId);
    }

    $providerLogos = [
        'default' => asset('home/images/tizzy.png'),
        1 => asset('home/images/tizzy.png'),
        2 => asset('home/images/icons/microsoft-icon.png'),
        3 => asset('home/images/icons/googl-logo.png'),
    ];

    // Provider variant flag for JS (DB column: has_variant enum yes/no)
    $providersHasVariant = [];
    $providerTransferFlags = [];
    $savedProviderSupportsTransfer = false;
    if (!empty($providers)) {
        foreach ($providers as $pr) {
            $pid = (int) $pr->id;
            $providersHasVariant[$pid] = strtolower((string) ($pr->has_variant ?? 'no')) === 'yes' ? 1 : 0;
            $providerLabel = strtolower(trim(($pr->slug ?? '') . ' ' . ($pr->name ?? '')));
            $isMicrosoftProvider = strpos($providerLabel, 'microsoft') !== false
                || strpos($providerLabel, 'office 365') !== false
                || strpos($providerLabel, 'm365') !== false
                || $pid === 2;
            $isGoogleProvider = strpos($providerLabel, 'google') !== false
                || strpos($providerLabel, 'g suite') !== false
                || strpos($providerLabel, 'workspace') !== false
                || $pid === 3;
            $providerTransferFlags[$pid] = [
                'google' => $isGoogleProvider,
                'microsoft' => $isMicrosoftProvider,
            ];
            if ($savedProviderId === $pid && ($isGoogleProvider || $isMicrosoftProvider)) {
                $savedProviderSupportsTransfer = true;
            }
        }
    }
    $showTransferDomainInitially = !$isExistingOrderCategoryUi && $savedProviderSupportsTransfer;
    $hasMultiDomainList = count($savedDomainList ?? []) > 1;
    $orderStepTabUrl = static function (int $step, ?int $orderId = null): string {
        $params = ['step' => $step];
        if ($orderId !== null && $orderId > 0) {
            $params['id'] = $orderId;
        }
        return route('superadmin.orders.create.step', $params);
    };
    // Prefer listing `total_amount`, then reconcile GST when stored tax columns are wrong.
    $summarySubtotal = isset($order) ? round((float) ($order->subtotal ?? 0), 2) : 0.0;
    $summaryDiscount = isset($order) ? round((float) ($order->discount_amount ?? 0), 2) : 0.0;
    $summaryGst = isset($order) ? round((float) ($order->gst_amount ?? $order->tax_amount ?? 0), 2) : 0.0;
    $summaryTotal = isset($order) ? round((float) ($order->total_amount ?? 0), 2) : 0.0;
    $summaryGstRate = isset($order) ? round((float) ($order->gst_rate ?? 18), 2) : 18.0;
    $summaryApplyGst = !isset($order) || (int) ($order->apply_gst ?? 1) === 1;
    if ($summaryGstRate <= 0) {
        $summaryGstRate = 18.0;
    }
    $summaryTaxable = max(0.0, round($summarySubtotal - $summaryDiscount, 2));
    if ($summarySubtotal <= 0 && $summaryTotal > 0) {
        $summarySubtotal = max(0.0, round($summaryTotal - $summaryGst, 2));
        $summaryTaxable = max(0.0, round($summarySubtotal - $summaryDiscount, 2));
    }
    $gstLooksBroken = $summaryGst > ($summaryTaxable + 0.05)
        || ($summaryTotal > 0 && $summaryGst > ($summaryTotal + 0.05))
        || ($summaryTotal > 0 && abs(($summaryTaxable + $summaryGst) - $summaryTotal) > 1.0);
    if ($summaryTotal > 0 && $gstLooksBroken) {
        if ($summaryApplyGst) {
            $summaryTaxable = round($summaryTotal * 100 / (100 + $summaryGstRate), 2);
            $summaryGst = round($summaryTotal - $summaryTaxable, 2);
            if ($summarySubtotal > 0) {
                $impliedDiscount = round($summarySubtotal - $summaryTaxable, 2);
                $summaryDiscount = $impliedDiscount >= 0 ? $impliedDiscount : 0.0;
                if ($impliedDiscount < 0) {
                    $summarySubtotal = $summaryTaxable;
                }
            } else {
                $summarySubtotal = $summaryTaxable;
                $summaryDiscount = 0.0;
            }
        } else {
            $summaryGst = 0.0;
            $summaryTaxable = $summaryTotal;
            if ($summarySubtotal <= 0) {
                $summarySubtotal = $summaryTotal;
            }
            $summaryDiscount = max(0.0, round($summarySubtotal - $summaryTaxable, 2));
        }
    } elseif ($summaryTotal <= 0 && $summaryTaxable > 0) {
        if ($summaryApplyGst && $summaryGst <= 0) {
            $summaryGst = round($summaryTaxable * $summaryGstRate / 100, 2);
        }
        $summaryTotal = round($summaryTaxable + $summaryGst, 2);
    }

    // ✅ Status variables for the dropdown
    $wizardOrderStatus = strtolower(trim((string) ($wizardOrderStatus ?? 'pending')));
    if ($wizardOrderStatus === '') {
        $wizardOrderStatus = 'pending';
    }
    $wizardOrderIsActive = $wizardOrderIsActive
        ?? in_array($wizardOrderStatus, ['completed', 'paid', 'success', 'active'], true);
    $wizardOrderIsProcessing = $wizardOrderIsProcessing ?? ($wizardOrderStatus === 'processing');
    $wizardRejectionReason = $wizardRejectionReason ?? '';
    $subscriptionStartValue = '';
    $subscriptionEndValue = '';
    if (isset($order)) {
        if (!empty($order->subscription_start_date)) {
            $subscriptionStartValue = substr((string) $order->subscription_start_date, 0, 10);
        } elseif (!empty($wizardEditState['subscription_start_date'] ?? '')) {
            $subscriptionStartValue = (string) $wizardEditState['subscription_start_date'];
        }
        if (!empty($order->subscription_end_date)) {
            $subscriptionEndValue = substr((string) $order->subscription_end_date, 0, 10);
        } elseif (!empty($wizardEditState['subscription_end_date'] ?? '')) {
            $subscriptionEndValue = (string) $wizardEditState['subscription_end_date'];
        }
    }
@endphp

@push('styles')
    <!-- Bootstrap CSS3/4/5 -->
    <style>
        .sa-order-form .select2-container {
            width: 100% !important;
        }

        .sa-order-tabs-nav .nav-link {
            cursor: pointer;
            font-weight: 600;
        }

        .sa-order-tabs-nav .nav-link.active {
            border-bottom: 3px solid #2563eb !important;
            color: #2563eb !important;
            background-color: #fff !important;
        }

        .sa-order-panel {
            display: none;
        }

        .sa-order-panel.active {
            display: block;
        }

        .sticky-summary {
            position: -webkit-sticky;
            position: sticky;
            top: 20px;
            z-index: 10;
        }

        .order-status-panel {
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            border-color: #e2e8f0 !important;
        }

        .order-status-panel .badge {
            font-size: 0.8rem;
            letter-spacing: 0.02em;
        }

        .order-status-panel .btn {
            min-height: 36px;
            font-weight: 600;
        }

        .custom-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 1.25rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            margin-bottom: 1rem;
        }

        .summary-card {
            border: 1px solid #bfdbfe;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .order-item-card {
            padding: 0.65rem 0.85rem !important;
            margin-bottom: 0.65rem !important;
            background: #fff;
        }

        .order-item-card-inner {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .order-item-row-top {
            display: flex;
            align-items: flex-start;
            gap: 0.65rem;
        }

        .order-item-icon-wrap {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: #eef4fc;
            border: 1px solid #dbeafe;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 1px 2px rgba(37, 99, 235, 0.06);
        }

        .order-item-icon-wrap .item-provider-logo {
            width: 22px;
            height: 22px;
            object-fit: contain;
        }

        .order-item-fields-top {
            flex: 1;
            min-width: 0;
        }

        .order-item-fields-row {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 0.5rem 0.75rem;
        }

        .order-item-field {
            flex: 1 1 120px;
            min-width: 100px;
        }

        .order-item-field .form-control,
        .order-item-field .form-select {
            font-size: 0.8125rem;
            padding: 0.25rem 0.5rem;
            min-height: 30px;
        }

        .order-item-field label,
        .order-item-field small {
            font-size: 0.7rem;
            margin-bottom: 0.15rem;
            line-height: 1.2;
        }

        .order-item-variant-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem 0.75rem;
            flex: 2 1 200px;
            align-items: flex-end;
        }

        .order-item-variant-row .order-item-field {
            flex: 1 1 130px;
            min-width: 100px;
        }

        .order-item-remove {
            flex-shrink: 0;
            line-height: 1;
            font-size: 1.25rem;
            margin-top: 0.1rem;
        }

        .order-item-row-bottom {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 0.65rem 1.25rem;
            padding-left: calc(38px + 0.65rem);
        }

        .order-item-price-col,
        .order-item-qty-col,
        .order-item-total-col {
            flex: 0 0 auto;
        }

        .order-item-price-col .item-price-readonly {
            font-size: 0.9rem;
            font-weight: 600;
            color: #1e293b;
            line-height: 1.2;
        }

        .order-item-price-col .item-price-hint {
            font-size: 0.65rem;
            color: #94a3b8;
        }

        .order-item-price-col .item-price-edit {
            max-width: 110px;
            font-size: 0.8125rem;
            padding: 0.2rem 0.45rem;
            min-height: 28px;
        }

        .order-item-qty-col .input-group {
            max-width: 94px;
        }

        .order-item-qty-col .input-group .btn {
            padding: 0.15rem 0.45rem;
            font-size: 0.8rem;
            background: #e8f2ff;
            border-color: #bfdbfe;
            color: #1d4ed8;
        }

        .order-item-qty-col .input-group .form-control {
            font-size: 0.8125rem;
            padding: 0.2rem 0.35rem;
            min-height: 28px;
        }

        .order-item-total-col .item-total-label {
            font-size: 0.7rem;
            color: #64748b;
            margin-bottom: 0.15rem;
            display: block;
        }

        .order-item-total-box {
            display: inline-flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            min-width: 100px;
            padding: 0.3rem 0.6rem;
            background: #e8f2ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
        }

        .order-item-total-box .item-row-total {
            font-size: 0.9rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
            white-space: nowrap;
        }

        .domain-status-available {
            color: #16a34a;
            font-weight: 500;
        }

        .domain-status-registered {
            color: #dc2626;
            font-weight: 500;
        }

        .domain-status-checking {
            color: #6b7280;
            font-weight: 500;
        }

        .domain-status-invalid {
            color: #dc2626;
            font-weight: 500;
        }

        @media (max-width: 991.98px) {
            .order-item-row-bottom {
                padding-left: 0;
            }
        }

        @media (max-width: 575.98px) {
            .order-item-fields-row {
                flex-direction: column;
                align-items: stretch;
            }

            .order-item-row-bottom {
                flex-direction: column;
                align-items: stretch;
                gap: 0.5rem;
            }

            .order-item-total-box {
                width: 100%;
            }
        }

        .Plus-Icon {
            padding: 1px 6px;
            background: #236dc9;
            color: #fff;
            border-radius: 9px;
            text-decoration: none;
            line-height: 1.4;
            font-weight: 600;
            border: none;
        }

        .Plus-Icon:hover {
            color: #fff;
            opacity: 0.9;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid sa-order-form pb-5" id="sa_order_root">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-4">
            <h4 class=" text-dark mb-0">
                {{ $orderStatusOnly ? 'Order' : ($isEdit ? 'Edit Order' : 'Create Order') }}
            </h4>
            <a href="{{ route('superadmin.orders.index') }}" class="btn btn-link text-primary p-0">← Back to orders</a>
        </div>

        <form id="sa_order_form" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="order_id" id="order_id" value="{{ $wizardOrderId ?: '' }}">
            <input type="hidden" name="apply_gst" id="apply_gst_hidden" value="{{ $savedApplyGst ? 1 : 0 }}">
            <input type="hidden" name="subtotal" id="subtotal_hidden"
                value="{{ number_format($summarySubtotal, 2, '.', '') }}">
            <input type="hidden" name="gst_amount" id="gst_amount_hidden"
                value="{{ number_format($summaryGst, 2, '.', '') }}">
            <input type="hidden" name="tax_amount" id="tax_amount_hidden"
                value="{{ number_format($summaryGst, 2, '.', '') }}">
            <input type="hidden" name="total_amount" id="total_amount_hidden"
                value="{{ number_format($summaryTotal, 2, '.', '') }}">
            <input type="hidden" name="gst_rate" id="gst_rate_hidden"
                value="{{ number_format($summaryGstRate, 2, '.', '') }}">

            <div class="row">
                <!-- Form Panels (Tabs) -->
                <div class="col-lg-8 card-body pt-3 pb-0">
                    <ul class="nav nav-tabs sa-order-tabs-nav border-bottom" role="tablist">
                        <li class="nav-item">
                            <a href="{{ $orderStepTabUrl(1, $wizardOrderId) }}"
                                class="nav-link border-0 bg-transparent sa-order-tab {{ $num === 1 ? 'active' : '' }}"
                                data-tab="1">Order details</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ $wizardOrderId ? $orderStepTabUrl(2, $wizardOrderId) : 'javascript:void(0);' }}"
                                class="nav-link border-0 bg-transparent sa-order-tab {{ $num === 2 ? 'active' : '' }} {{ !$wizardOrderId ? 'disabled opacity-50' : '' }}"
                                data-tab="2" @if(!$wizardOrderId) aria-disabled="true" tabindex="-1" @endif>PO details</a>
                        </li>
                        <li class="nav-item d-none" id="aadhaar_tab_nav">
                            <a href="{{ $wizardOrderId ? $orderStepTabUrl(3, $wizardOrderId) : 'javascript:void(0);' }}"
                                class="nav-link border-0 bg-transparent sa-order-tab {{ $num === 3 ? 'active' : '' }} {{ !$wizardOrderId ? 'disabled opacity-50' : '' }}"
                                data-tab="3" @if(!$wizardOrderId) aria-disabled="true" tabindex="-1" @endif>Aadhaar</a>
                        </li>
                    </ul>

                    {{-- Tab 1: Order Details --}}
                    <div class="sa-order-panel custom-card {{ $num === 1 ? 'active' : '' }}" data-panel="1">
                        <div class="row">
                            <!-- Order Type (col-md-4) -->
                            <div class="col-md-4 mb-3">
                                <label class="form-label mb-1">Order type</label>
                                <select name="order_type" id="order_type" class="form-control text-sm" readonly disabled>
                                    <option value="b2b" selected>B2B</option>
                                </select>
                                <input type="hidden" name="order_type" value="b2b">
                            </div>

                            <!-- Order Category (col-md-4) -->
                            <div class="col-md-4 mb-3">
                                <label class="form-label mb-1">Order category <span class="text-danger">*</span></label>
                                <select name="order_category" id="order_category" class="form-control text-sm">
                                    <option value="new" @selected(!$isExistingOrderCategoryUi)>New</option>
                                    <option value="existing" @selected($isExistingOrderCategoryUi)>Existing</option>
                                </select>
                            </div>

                            <!-- Provider (col-md-4) -->
                            <div class="col-md-4 mb-3">
                                <label class="form-label mb-1">Provider <span class="text-danger">*</span></label>
                                <select name="provider_id" id="provider_id" class="form-control text-sm">
                                    <option value="">— Select —</option>
                                    @foreach ($providers as $pr)
                                        @php
                                            $prFlags = $providerTransferFlags[(int) $pr->id] ?? ['google' => false, 'microsoft' => false];
                                        @endphp
                                        <option value="{{ $pr->id }}"
                                            data-has-variant="{{ $providersHasVariant[$pr->id] ?? 0 ? 1 : 0 }}"
                                            data-is-google="{{ !empty($prFlags['google']) ? 1 : 0 }}"
                                            data-is-microsoft="{{ !empty($prFlags['microsoft']) ? 1 : 0 }}"
                                            @if ($savedProviderId === (int) $pr->id) selected @endif>
                                            {{ $pr->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Partner (col-md-6) -->
                            @if ($wizardMode)
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                                        <label class="form-label mb-0">Partner <span class="text-danger">*</span></label>
                                        <a href="{{ route('superadmin.b2b_partners.add') }}" class="Plus-Icon"
                                            title="Add Partner">+</a>
                                    </div>
                                    <select name="partner_id" id="partner_id" class="form-control text-sm">
                                        <option value="">— Select partner —</option>
                                        @foreach ($partners as $p)
                                            <option value="{{ $p->id }}"
                                                @if ($partnerId === (int) $p->id) selected @endif>
                                                {{ $p->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @else
                                <input type="hidden" name="partner_id" value="{{ $partnerId }}">
                            @endif

                            <!-- Customer (col-md-6) -->
                            <div class="{{ $wizardMode ? 'col-md-6' : 'col-md-12' }} mb-3">
                                <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                                    <label class="form-label mb-0">Customer <span class="text-danger">*</span></label>
                                    <a href="{{ route('superadmin.b2b_partners.customers.add_open') }}" class="Plus-Icon"
                                        id="wizard_add_customer_btn" title="Add Customer">+</a>
                                </div>
                                <select name="user_id" id="user_id" class="form-control"
                                    @if ($orderStatusOnly || ($wizardMode && $customers->isEmpty() && empty($wizardUserId))) disabled @endif>
                                    @if (!empty($wizardUserId) && $customers->isEmpty())
                                        <option value="{{ $wizardUserId }}" selected>
                                            {{ $selectedCustomerLabel !== '' ? $selectedCustomerLabel : ('Customer #' . $wizardUserId) }}
                                        </option>
                                    @elseif ($customers->isNotEmpty())
                                        <option value="">— Select customer —</option>
                                        @foreach ($customers as $c)
                                            <option value="{{ $c->id }}" @selected((int) $wizardUserId === (int) $c->id)>
                                                {{ $c->name }}{{ !empty($c->company_name) ? ' — ' . $c->company_name : '' }}
                                            </option>
                                        @endforeach
                                        @if (!empty($wizardUserId) && !$customers->contains(fn ($row) => (int) ($row->id ?? 0) === (int) $wizardUserId))
                                            <option value="{{ $wizardUserId }}" selected>
                                                {{ $selectedCustomerLabel !== '' ? $selectedCustomerLabel : ('Customer #' . $wizardUserId) }}
                                            </option>
                                        @endif
                                    @elseif (!empty($wizardUserId) && $selectedCustomerLabel !== '')
                                        <option value="{{ $wizardUserId }}" selected>{{ $selectedCustomerLabel }}</option>
                                    @elseif ($wizardMode)
                                        <option value="">— Select partner first —</option>
                                    @else
                                        <option value="">— Select customer —</option>
                                    @endif
                                </select>
                            </div>
                        </div>

                       <div class="row">
                            <!-- Transfer Domain (col-md-6) - ONLY for NEW orders -->
                            <div class="col-md-6 mb-3" id="transfer_domain_wrap" @if(!$showTransferDomainInitially) style="display: none;" @endif>
                                <label class="form-label mb-1">Transfer domain?</label>
                                <select name="transfer_domain" id="transfer_domain" class="form-control text-sm">
                                    <option value="">— Select —</option>
                                    <option value="yes" @if(($savedTransferDomain ?? '') === 'yes') selected @endif>Yes</option>
                                    <option value="no" @if(($savedTransferDomain ?? '') === 'no') selected @endif>No</option>
                                </select>
                            </div>

                            <!-- Transfer Code Display - ONLY for NEW orders -->
                            <div class="col-md-6 mb-3 d-none" id="transfer_code_wrap">
                                <label class="form-label mb-1">Transfer code</label>
                                <input type="text" id="transfer_code_display" class="form-control bg-light text-sm" readonly>
                            </div>

                            <!-- Domain Name -->
                            <div class="mb-3 col-md-12" id="domain_name_wrap">
                                <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                                    <label class="form-label mb-0">Domain name <span class="text-danger">*</span></label>
                                    <button type="button" class="Plus-Icon d-none" id="btn_add_domain_popup"
                                        title="Add domain">+</button>
                                </div>
                                <div id="domain_single_wrap" @if($hasMultiDomainList) class="d-none" @endif>
                                    <input type="text" name="{{ $hasMultiDomainList ? '' : 'domain_name' }}" id="domain_name"
                                        class="form-control text-sm"
                                        value="{{ $hasMultiDomainList ? '' : ($savedDomainName ?? '') }}"
                                        placeholder="example.com or keyword (Microsoft)"
                                        @if($hasMultiDomainList) disabled @endif>
                                </div>
                                <div id="ms_domain_pick" class="d-none mt-2">
                                    <label class="text-muted mb-1">Select one domain (Microsoft, max 3)</label>
                                    <select id="ms_domain_select" class="form-control text-sm"></select>
                                </div>
                                <div id="domain_multi_wrap" @if(!$hasMultiDomainList) class="d-none" @endif>
                                    <select id="domain_multi_select" class="form-control text-sm">
                                        <option value="">— Select domain —</option>
                                        @foreach ($savedDomainList ?? [] as $i => $seg)
                                            <option value="{{ $seg }}" @if($i === 0) selected @endif>{{ $seg }}</option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted d-block mt-1">Select one domain from the list.</small>
                                    <input type="hidden" id="domain_name_multi"
                                        name="{{ $hasMultiDomainList ? 'domain_name' : '' }}"
                                        value="{{ $hasMultiDomainList ? (($savedDomainList[0] ?? null) ?: ($savedDomainName ?? '')) : '' }}"
                                        @if(!$hasMultiDomainList) disabled @endif>
                                </div>
                                <p id="domain_check_msg" class="mt-1 mb-0"></p>
                            </div>

                            <!-- Apply GST Toggle -->
                            <div class="col-md-6 mb-3 d-flex align-items-end" id="apply_gst_wrap">
                                <div class="form-check pb-2">
                                    <input type="checkbox" id="apply_gst_toggle" @checked($savedApplyGst)
                                        class="form-check-input">
                                    <label class="form-check-label small" for="apply_gst_toggle">Apply GST (18%)</label>
                                </div>
                            </div>
                        </div>

                        <!-- Plan Items Section -->
                        <div class="d-flex align-items-center justify-content-between mt-4 mb-2 border-top pt-3">
                            <h6 class="text-dark mb-0">Plan items</h6>
                            <button type="button" id="btn_add_item" class="btn btn-sm btn-primary">+ Add plan</button>
                        </div>

                        <div id="order_items_wrap" class="mt-2"></div>

                        <div class="row" id="promo_discount_wrap" @if($isExistingOrderCategoryUi) style="display:none;" @endif>
                            <div class="col-md-6 mb-3">
                                <label class="form-label mb-1">Promo code</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" name="promo_code" id="promo_code" class="form-control text-sm"
                                        value="{{ $savedPromoCode ?? '' }}" placeholder="Enter promo code">
                                    <button type="button" class="btn btn-outline-primary" id="btn_apply_promo">Apply</button>
                                </div>
                                <small id="promo_code_hint" class="text-muted d-block mt-1"></small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label mb-1">Discount (₹)</label>
                                <input type="number" name="discount_amount" id="discount_amount" step="0.01"
                                    min="0"
                                    value="{{ number_format($summaryDiscount, 2, '.', '') }}"
                                    class="form-control text-sm"
                                    placeholder="Auto-filled from promo %">
                                <small class="text-muted d-block mt-1">Filled from promo % after you add plan items.</small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label mb-1">Subscription start date <span
                                        class="text-danger">*</span></label>
                                <input type="date" name="subscription_start_date" id="subscription_start_date"
                                    class="form-control text-sm"
                                    value="{{ $subscriptionStartValue }}"
                                    required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label mb-1">Subscription end date <span
                                        class="text-danger">*</span></label>
                                <input type="date" name="subscription_end_date" id="subscription_end_date"
                                    class="form-control text-sm"
                                    value="{{ $subscriptionEndValue }}"
                                    required>
                            </div>
                            @if ($wizardOrderId)
                                @php
                                    $wizardOrderStatusNorm = strtolower(trim((string) $wizardOrderStatus));
                                    $wizardOrderIsCancelled = in_array($wizardOrderStatusNorm, ['rejected', 'cancelled', 'canceled', 'failed'], true);
                                    $wizardOrderIsActiveNorm = $wizardOrderIsActive
                                        || in_array($wizardOrderStatusNorm, ['completed', 'paid', 'success', 'active'], true);
                                    // Cancel only after Active — pending/processing show Active only.
                                    $showActivateBtn = !$wizardOrderIsActiveNorm && !$wizardOrderIsCancelled;
                                    $showCancelBtn = $wizardOrderIsActiveNorm
                                        && !$wizardOrderIsCancelled
                                        && !empty($orderCanBeCancelled);
                                    $statusBadgeClass = $wizardOrderIsActiveNorm
                                        ? 'bg-success'
                                        : ($wizardOrderIsCancelled
                                            ? 'bg-danger'
                                            : ($wizardOrderIsProcessing ? 'bg-info text-dark' : 'bg-secondary'));
                                    $statusBadgeLabel = $wizardOrderIsActiveNorm
                                        ? 'Active'
                                        : ($wizardOrderIsCancelled
                                            ? 'Cancelled'
                                            : ($wizardOrderIsProcessing ? 'Processing' : 'Pending'));
                                @endphp
                                <div class="col-12 mb-3">
                                    <div class="order-status-panel border rounded-3 p-3">
                                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                            <label class="form-label mb-0 fw-semibold">Order status</label>
                                            <span class="badge rounded-pill {{ $statusBadgeClass }} px-3 py-2">
                                                {{ $statusBadgeLabel }}
                                            </span>
                                        </div>

                                        <div class="d-flex flex-wrap gap-2 align-items-center">
                                            @if ($showActivateBtn)
                                                <button type="button" id="btn_status_active"
                                                    class="btn btn-success btn-sm rounded-pill px-3">
                                                    <i class="ti ti-circle-check me-1"></i> Set Active
                                                </button>
                                            @endif
                                            @if ($showCancelBtn)
                                                <button type="button" id="btn_status_cancel"
                                                    class="btn btn-outline-danger btn-sm rounded-pill px-3">
                                                    <i class="ti ti-circle-x me-1"></i> Cancel Order
                                                </button>
                                            @endif
                                            @if (!$showActivateBtn && !$showCancelBtn && !$wizardOrderIsCancelled)
                                                <span class="text-muted small">No status actions available right now.</span>
                                            @endif
                                        </div>

                                        <input type="hidden" id="wizard_order_status" value="{{ $wizardOrderStatus }}">

                                        @if ($wizardOrderIsActiveNorm && !($orderCanBeCancelled ?? true) && !$wizardOrderIsCancelled)
                                            <div class="alert alert-danger py-2 px-3 mb-0 mt-2 small">
                                                {{ $orderCancellationBlockReason ?? 'This order can no longer be canceled.' }}
                                            </div>
                                        @elseif ($wizardOrderIsActiveNorm && ($orderCanBeCancelled ?? false) && !empty($orderCancellationDeadline))
                                            <div class="alert alert-secondary py-2 px-3 mb-0 mt-2 small">
                                                Cancel allowed until <strong>{{ $orderCancellationDeadline }}</strong>.
                                            </div>
                                        @elseif ($wizardOrderIsCancelled)
                                            <div class="alert alert-danger py-2 px-3 mb-0 mt-2 small">
                                                This order is cancelled.
                                            </div>
                                        @elseif ($showActivateBtn)
                                            <div class="text-muted small mt-2">
                                                Current status is <strong>{{ $statusBadgeLabel }}</strong>. Click <strong>Set Active</strong> to activate this order.
                                                @if ($wizardOrderIsProcessing)
                                                    Cancel becomes available after the order is Active.
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                        @if ($wizardOrderId)
                            <div id="wizard_reject_reason_wrap"
                                class="mb-3 {{ $wizardOrderIsCancelled ? '' : 'd-none' }}">
                                <label class="form-label mb-1" for="wizard_rejection_reason">Cancellation reason <span
                                        class="text-danger">*</span></label>
                                <textarea id="wizard_rejection_reason" class="form-control text-sm" rows="3"
                                    placeholder="Enter reason for cancellation"
                                    {{ $wizardOrderIsCancelled ? 'readonly' : '' }}>{{ $wizardRejectionReason }}</textarea>
                            </div>
                        @endif

                        <div class="mt-4 d-flex justify-content-end border-top pt-3">
                            @if ($orderStatusOnly)
                                <span class="text-muted text-sm align-self-center me-auto">
                                    Order details are view-only except subscription dates.
                                    @if (!empty($showActivateBtn) && !empty($showCancelBtn))
                                        Use <strong>Set Active</strong> or <strong>Cancel Order</strong> to update status.
                                    @elseif (!empty($showActivateBtn))
                                        Use <strong>Set Active</strong> to update status.
                                    @elseif (!empty($showCancelBtn))
                                        Use <strong>Cancel Order</strong> to update status.
                                    @endif
                                </span>
                                <button type="button" id="btn_save_subscription_dates" class="btn btn-primary px-4 text-sm">
                                    Update subscription dates
                                </button>
                            @else
                                <button type="submit" id="btn_save_order" class="btn btn-primary px-4 text-sm">
                                    {{ $isEdit ? 'Update order' : 'Save order' }}
                                </button>
                            @endif
                        </div>
                    </div>

                    {{-- Tab 2: PO details --}}
                    <div class="sa-order-panel custom-card {{ $num === 2 ? 'active' : '' }}" data-panel="2">
                        @include('superadmin.b2b.partials.new_plan_po_fields', [
                            'poReadonly' => $poReadonly,
                            'wizardPoPrefill' => $wizardPoPrefill ?? null,
                            'wizardOrderSummary' => $wizardOrderSummary ?? null,
                        ])
                        @if ($wizardOrderId && ($poGenerated || $poDocumentUrl))
                            <div class="mt-4 border-top pt-3 d-flex align-items-center gap-2">
                                <span class="text-sm mr-2">PO document</span>
                                <a href="{{ route('superadmin.orders.po_pdf', ['id' => $wizardOrderId, 'download' => 1]) }}"
                                    target="_blank" rel="noopener"
                                    class="btn btn-sm btn-light border d-inline-flex align-items-center">
                                    <i class="ti ti-download mr-1"></i> Download PDF
                                </a>
                            </div>
                        @endif
                        @if (!$poReadonly)
                            <div class="mt-3 border-top pt-3 d-flex justify-content-end">
                                <button type="button" id="btn_save_po" class="btn btn-primary px-4 text-sm">Submit</button>
                            </div>
                        @endif
                    </div>

                    {{-- Tab 3: Aadhaar (disabled in superadmin — not required) --}}
                    <div class="sa-order-panel custom-card d-none {{ $num === 3 ? 'active' : '' }}" data-panel="3">
                        @php
                            $isExistingOrder = $isExistingOrderCategoryUi ?? false;
                        @endphp
                        <div id="aadhaar_existing_wrap" class="{{ $isExistingOrder ? '' : 'd-none' }}">
                            {{-- Existing orders: save Aadhaar without OTP verification --}}
                        <div class="row">
                                <div class="col-md-12">
                                    <div class="border rounded p-3 bg-light">
                                        <h6 class="mb-2">Aadhaar Information</h6>
                                        <div class="row g-2">
                                            <div class="col-md-12">
                                                <label class="form-label">Aadhaar Number</label>
                                                <input type="text" id="aadhaar_number_save" name="aadhaar_number"
                                                    class="form-control form-control-sm" maxlength="12"
                                                    placeholder="Enter 12 digit Aadhaar number"
                                                    value="{{ $wizardAadhaarMeta['raw_number'] ?? '' }}"
                                                    inputmode="numeric" autocomplete="off">
                                                <small class="text-muted">For existing orders only — saved to database without OTP verification.</small>
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <button type="button" id="btn_save_aadhaar"
                                                class="btn btn-primary btn-sm">Save Aadhaar</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="aadhaar_new_wrap" class="{{ $isExistingOrder ? 'd-none' : '' }}">
                            @include('superadmin.b2b.partials.new_plan_aadhaar_fields', [
                                'wizardAadhaarMeta' => $wizardAadhaarMeta ?? null,
                            ])
                        </div>

                            @if ($wizardOrderId && ($poGenerated || $poDocumentUrl))
                                <div class="mt-4 border-top pt-3 d-flex align-items-center gap-2">
                                    <span class="text-sm mr-2">PO document</span>
                                    <a href="{{ route('superadmin.orders.po_pdf', ['id' => $wizardOrderId, 'download' => 1]) }}"
                                        target="_blank" rel="noopener"
                                        class="btn btn-sm btn-light border d-inline-flex align-items-center">
                                        <i class="ti ti-download mr-1"></i> Download PDF
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                <!-- Order Summary Card (Sticky) -->
                <div class="col-lg-4">
                    <div class="sticky-summary custom-card summary-card">
                        <h5 class=" text-primary mb-3">Order summary</h5>
                        <div class="d-flex flex-column gap-2 text-sm">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="">Subtotal</span>
                                <span class="">₹<span id="sum_subtotal">{{ number_format($summarySubtotal, 2, '.', '') }}</span></span>
                            </div>
                            <div class="d-flex justify-content-between text-danger mb-2" id="row_discount"
                                @if ($summaryDiscount <= 0) style="display:none;" @endif>
                                <span>Discount</span>
                                <span>− ₹<span id="sum_discount">{{ number_format($summaryDiscount, 2, '.', '') }}</span></span>
                            </div>
                            <div class="d-flex justify-content-between text-muted mb-2">
                                <span>Taxable</span>
                                <span>₹<span id="sum_taxable">{{ number_format($summaryTaxable, 2, '.', '') }}</span></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="">GST</span>
                                <span class="">₹<span id="sum_gst">{{ number_format($summaryGst, 2, '.', '') }}</span></span>
                            </div>
                            <hr class="my-2 border-secondary">
                            <div class="d-flex justify-content-between  h5 text-dark pt-1">
                                <span>Grand total</span>
                                <span>₹<span id="sum_total">{{ number_format($summaryTotal, 2, '.', '') }}</span></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="modal fade" id="domain_add_modal" tabindex="-1" aria-labelledby="domain_add_modal_label"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title text-sm" id="domain_add_modal_label">Add domain</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label mb-1 text-sm" for="domain_modal_input">Domain</label>
                    <input type="text" id="domain_modal_input" class="form-control text-sm"
                        placeholder="example.onmicrosoft.com" autocomplete="off">
                    <small id="domain_modal_error" class="text-danger d-none"></small>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" id="btn_domain_modal_save">Add</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function($) {
            var itemIdx = 0;
            var CFG = {
                urls: @json($orderFormUrls ?? (object) []),
                mainVariantsByProvider: @json($mainVariantsByProvider ?? (object) []),
                subVariantsByMain: @json($subVariantsByMain ?? (object) []),
                orderItems: @json($orderItems ?? []),
                editState: @json($wizardEditState ?? null),
                wizardOrderId: @json($wizardOrderId),
                partnerId: @json($partnerId),
                selectedProviderId: @json($savedProviderId),
                selectedUserId: @json(isset($order) && $order ? (int) ($order->user_id ?? 0) : 0),
                selectedUserLabel: @json($selectedCustomerLabel ?? ''),
                savedApplyGst: @json($savedApplyGst ?? true),
                savedDomain: @json($savedDomainName ?? ''),
                savedDomainList: @json($savedDomainList ?? []),
                savedPromoCode: @json($savedPromoCode ?? ''),
                activeTab: parseInt(@json($num ?? 1), 10) || 1,
                wizardPricing: @json($wizardPricing ?? null),
                applyPromoUrl: @json(url('/api/apply-promo')),
                aadhaarTestMode: @json($aadhaarTestMode ?? false),
                aadhaarMockOtp: @json($aadhaarMockOtp ?? '123456'),
                providersHasVariant: @json($providersHasVariant ?? (object) []),
                providerLogos: @json($providerLogos ?? (object) []),
                showTransferDomainInitially: @json(!empty($showTransferDomainInitially)),
                checkDomainApi: @json(url('/superadmin/check-domain')),
                // ✅ Status variables for JavaScript
                wizardOrderStatus: @json($wizardOrderStatus ?? 'pending'),
                wizardOrderIsProcessing: @json($wizardOrderIsProcessing ?? false),
                wizardOrderIsActive: @json($wizardOrderIsActive ?? false),
                orderStatusOnly: @json(!empty($orderStatusOnly)),
                orderCanBeCancelled: @json(!empty($orderCanBeCancelled))
            };
            var GST_RATE = 18;
            var isEditOrder = !!CFG.wizardOrderId;
            window._promoPercent = 0;
            window._pricingDirty = false;
            window._updatingDiscountFromPromo = false;
            window._promoApplyInFlight = false;
            window._lastPromoAlertCode = '';

            function setPromoHint(msg, isError) {
                var $hint = $('#promo_code_hint');
                if (!$hint.length) {
                    return;
                }
                $hint.text(msg || '');
                $hint.toggleClass('text-danger', !!isError);
                $hint.toggleClass('text-success', !isError && !!msg);
                $hint.toggleClass('text-muted', !msg);
            }

            function resolvePlanSubtotal(preferred) {
                var subtotal = typeof preferred === 'number' ? preferred : 0;
                if (subtotal > 0) {
                    return +(subtotal.toFixed(2));
                }
                var fromRows = 0;
                $('#order_items_wrap .item-row').each(function() {
                    fromRows += (parseFloat($(this).find('.item-price').val()) || 0) *
                        (parseInt($(this).find('.item-qty').val(), 10) || 1);
                });
                if (fromRows > 0) {
                    return +(fromRows.toFixed(2));
                }
                var fromHidden = parseFloat($('#subtotal_hidden').val()) || 0;
                if (fromHidden > 0) {
                    return +(fromHidden.toFixed(2));
                }
                var fromSummary = parseFloat(($('#sum_subtotal').text() || '').replace(/[^\d.]/g, '')) || 0;
                if (fromSummary > 0) {
                    return +(fromSummary.toFixed(2));
                }
                if (CFG.wizardPricing && parseFloat(CFG.wizardPricing.subtotal) > 0) {
                    return +(parseFloat(CFG.wizardPricing.subtotal).toFixed(2));
                }
                return 0;
            }

            function fillDiscountFromPromo(preferredSubtotal) {
                var promoPercent = window._promoPercent || 0;
                if (!(promoPercent > 0)) {
                    return 0;
                }
                var subtotal = resolvePlanSubtotal(preferredSubtotal);
                var discountAmt = Math.min(subtotal, +((subtotal * promoPercent) / 100).toFixed(2));
                var $disc = $('#discount_amount');
                window._updatingDiscountFromPromo = true;
                if ($disc.length) {
                    $disc.val(discountAmt.toFixed(2));
                    $disc[0].value = discountAmt.toFixed(2);
                }
                window._updatingDiscountFromPromo = false;
                if (subtotal <= 0) {
                    setPromoHint('Promo ' + promoPercent + '% saved. Add plan items (with price) to calculate ₹ discount.', false);
                } else {
                    setPromoHint('Promo ' + promoPercent + '% applied (− ₹' + discountAmt.toFixed(2) + ').', false);
                }
                return discountAmt;
            }

            function promoCodeApply(opts) {
                opts = opts || {};
                if (isExistingOrderCategory()) {
                    return;
                }
                var code = ($('#promo_code').val() || '').trim();
                window._pricingDirty = true;
                if (!code) {
                    window._promoPercent = 0;
                    window._lastPromoAlertCode = '';
                    setPromoHint('', false);
                    manageOrderItems('recalc');
                    return;
                }
                if (window._promoApplyInFlight) {
                    return;
                }
                window._promoApplyInFlight = true;
                setPromoHint('Checking promo…', false);
                $.ajax({
                    url: CFG.applyPromoUrl || '/api/apply-promo',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        code: code,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).done(function(r) {
                    if (r && r.success) {
                        window._promoPercent = parseFloat(r.discount_percent) || 0;
                        var discountAmt = fillDiscountFromPromo();
                        manageOrderItems('recalc');
                        fillDiscountFromPromo();
                        var alertKey = code.toUpperCase() + ':' + window._promoPercent;
                        if (
                            !opts.silent &&
                            typeof alertify !== 'undefined' &&
                            window._promoPercent > 0 &&
                            window._lastPromoAlertCode !== alertKey
                        ) {
                            window._lastPromoAlertCode = alertKey;
                            alertify.success('Promo applied: ' + window._promoPercent + '% off');
                        }
                        if (discountAmt <= 0 && window._promoPercent > 0 && typeof alertify !== 'undefined' && !opts.silent) {
                            alertify.message('Add plan price/qty so Discount (₹) can calculate.');
                        }
                    } else {
                        window._promoPercent = 0;
                        window._lastPromoAlertCode = '';
                        setPromoHint((r && r.message) ? r.message : 'Invalid promo code', true);
                        if (!opts.silent && typeof alertify !== 'undefined') {
                            alertify.error((r && r.message) ? r.message : 'Invalid promo code');
                        }
                        manageOrderItems('recalc');
                    }
                }).fail(function(xhr) {
                    window._promoPercent = 0;
                    window._lastPromoAlertCode = '';
                    var msg = (xhr.responseJSON && xhr.responseJSON.message)
                        ? xhr.responseJSON.message
                        : 'Invalid promo code';
                    setPromoHint(msg, true);
                    if (!opts.silent && typeof alertify !== 'undefined') {
                        alertify.error(msg);
                    }
                    manageOrderItems('recalc');
                }).always(function() {
                    window._promoApplyInFlight = false;
                });
            }

            function paintOrderSummary(subtotal, discountAmt, taxable, gst, total) {
                $('#sum_subtotal').text(subtotal.toFixed(2));
                if (isExistingOrderCategory()) {
                    discountAmt = 0;
                }
                if (discountAmt > 0 && !isExistingOrderCategory()) {
                    $('#row_discount').show();
                    $('#sum_discount').text(discountAmt.toFixed(2));
                } else {
                    $('#row_discount').hide();
                }
                $('#sum_taxable').text(taxable.toFixed(2));
                $('#sum_gst').text(gst.toFixed(2));
                $('#sum_total').text(total.toFixed(2));
                syncOrderSummaryHiddenFields();
            }

            function applyStoredOrderSummary() {
                if (!isEditOrder || window._pricingDirty) {
                    return false;
                }
                var subtotal = parseFloat($('#subtotal_hidden').val()) || 0;
                var discountAmt = parseFloat($('#discount_amount').val()) || 0;
                var gst = parseFloat($('#gst_amount_hidden').val()) || 0;
                var total = parseFloat($('#total_amount_hidden').val()) || 0;
                var gstRate = parseFloat($('#gst_rate_hidden').val()) || GST_RATE;
                var applyGst = $('#apply_gst_toggle').is(':checked');
                if (!(gstRate > 0)) {
                    gstRate = GST_RATE;
                }
                var taxable = Math.max(0, +(subtotal - discountAmt).toFixed(2));
                if (taxable <= 0 && total > 0) {
                    taxable = Math.max(0, +(total - gst).toFixed(2));
                }
                var gstBroken = gst > (taxable + 0.05)
                    || (total > 0 && gst > (total + 0.05))
                    || (total > 0 && Math.abs((taxable + gst) - total) > 1);
                if (total > 0 && gstBroken) {
                    if (applyGst) {
                        taxable = +(total * 100 / (100 + gstRate)).toFixed(2);
                        gst = +(total - taxable).toFixed(2);
                        if (subtotal > 0) {
                            var impliedDisc = +(subtotal - taxable).toFixed(2);
                            discountAmt = impliedDisc >= 0 ? impliedDisc : 0;
                            if (impliedDisc < 0) {
                                subtotal = taxable;
                            }
                        } else {
                            subtotal = taxable;
                            discountAmt = 0;
                        }
                    } else {
                        gst = 0;
                        taxable = total;
                        if (!(subtotal > 0)) {
                            subtotal = total;
                        }
                        discountAmt = Math.max(0, +(subtotal - taxable).toFixed(2));
                    }
                }
                paintOrderSummary(subtotal, discountAmt, taxable, gst, total);
                return true;
            }

            function planUnitPrice(plan) {
                if (!plan) {
                    return 0;
                }
                var keys = ['b2b_price', 'price', 'actual_price', 'b2c_price'];
                for (var i = 0; i < keys.length; i++) {
                    var val = parseFloat(plan[keys[i]]);
                    if (!isNaN(val) && val > 0) {
                        return val;
                    }
                }
                return 0;
            }

            function selectedPlanUnitPrice($opt) {
                if (!$opt || !$opt.length || !$opt.val()) {
                    return 0;
                }
                var keys = ['price', 'b2c-price', 'actual-price'];
                for (var i = 0; i < keys.length; i++) {
                    var val = parseFloat($opt.attr('data-' + keys[i]));
                    if (!isNaN(val) && val > 0) {
                        return val;
                    }
                }
                return 0;
            }

            function isExistingOrderCategory() {
                return ($('#order_category').val() || '').toLowerCase() === 'existing';
            }

            function addOneYearMinusOneDay(startStr) {
                if (!startStr) {
                    return '';
                }
                var parts = startStr.split('-');
                if (parts.length !== 3) {
                    return '';
                }
                var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
                if (isNaN(d.getTime())) {
                    return '';
                }
                d.setFullYear(d.getFullYear() + 1);
                d.setDate(d.getDate() - 1);
                return d.getFullYear() + '-' +
                    String(d.getMonth() + 1).padStart(2, '0') + '-' +
                    String(d.getDate()).padStart(2, '0');
            }

            function applyOrderCategoryUi() {
                var isExisting = isExistingOrderCategory();
                $('#promo_discount_wrap').toggle(!isExisting);
                $('#row_discount').toggle(!isExisting && (parseFloat($('#sum_discount').text()) || 0) > 0);

                if (isExisting) {
                    $('#promo_code').val('');
                    $('#discount_amount').val('0');
                    window._promoPercent = 0;
                    setPromoHint('', false);
                } else {
                    var startVal = ($('#subscription_start_date').val() || '').trim();
                    if (startVal && !($('#subscription_end_date').val() || '').trim()) {
                        $('#subscription_end_date').val(addOneYearMinusOneDay(startVal));
                    }
                }

                syncAllItemPriceEditModes();
                manageOrderItems('sync_provider_ui');
                manageOrderItems('recalc');
            }

            function parseIntPrice(val) {
                var n = parseInt(String(val == null ? '' : val).replace(/\D/g, ''), 10);
                return isNaN(n) || n < 0 ? 0 : n;
            }

            function formatRupeePrice(amount) {
                return 'Rs.' + parseIntPrice(amount);
            }

            /**
             * syncPriceEditMode - Controls whether price is editable or read-only
             * - For "Existing" orders: Price is editable (shows input field)
             * - For "New" orders: Price is read-only (shows display only)
             */
            function syncPriceEditMode($row, price) {
                if (!$row || !$row.length) {
                    return;
                }
                var isExisting = isExistingOrderCategory();
                var needsManual = isExisting;
                $row.find('.item-price-readonly').toggleClass('d-none', needsManual);
                $row.find('.item-price-edit').toggleClass('d-none', !needsManual);
                if (needsManual) {
                    $row.find('.item-price-hint').text('Editable for existing orders');
                } else {
                    $row.find('.item-price-hint').text('per user/year');
                }
            }

            function syncAllItemPriceEditModes() {
                $('#order_items_wrap .item-row').each(function() {
                    var price = parseFloat($(this).find('.item-price').val()) || 0;
                    syncPriceEditMode($(this), price);
                });
            }

            function customerOptionLabel(c) {
                var label = (c.name || c.customer_name || c.text || ('Customer #' + c.id)).trim();
                var company = (c.company_name || '').trim();
                if (company && label.indexOf(company) < 0) {
                    label += ' — ' + company;
                }
                return label;
            }

            function renderCustomerOptions(customers, keepUserId) {
                var $sel = $('#user_id');
                $sel.empty();
                if (!customers || !customers.length) {
                    $sel.append($('<option>').val('').text('— No customers for this partner —'));
                    $sel.prop('disabled', true);
                    return;
                }
                $sel.prop('disabled', false);
                $sel.append($('<option>').val('').text('— Select customer —'));
                customers.forEach(function(c) {
                    if (!c || c.id == null || c.id === '') {
                        return;
                    }
                    $sel.append($('<option>').val(String(c.id)).text(customerOptionLabel(c)));
                });
                var pick = keepUserId || CFG.selectedUserId || '';
                if (pick && $sel.find('option[value="' + pick + '"]').length) {
                    $sel.val(String(pick));
                }
            }

            function loadPartnerCustomers(keepUserId) {
                var $partner = $('#partner_id');
                if (!$partner.length) {
                    return;
                }
                var pid = parseInt($partner.val() || String(CFG.partnerId || '0'), 10) || 0;
                var $sel = $('#user_id');
                if (!pid) {
                    $sel.empty().append($('<option>').val('').text('— Select partner first —'));
                    $sel.prop('disabled', true);
                    return;
                }
                $sel.prop('disabled', true).empty().append($('<option>').val('').text('Loading customers...'));
                var done = function(rows) {
                    renderCustomerOptions(rows, keepUserId);
                };
                var partnerCustomersUrl = (CFG.urls && CFG.urls.partnerCustomers)
                    ? String(CFG.urls.partnerCustomers).replace('__PID__', pid)
                    : '';
                if (partnerCustomersUrl) {
                    $.getJSON(partnerCustomersUrl).done(function(res) {
                        done((res && res.customers) ? res.customers : []);
                    }).fail(function() {
                        done([]);
                    });
                    return;
                }
                var postUrl = (CFG.urls && CFG.urls.customers) ? CFG.urls.customers : '';
                if (postUrl) {
                    $.ajax({
                        url: postUrl,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            _token: $('meta[name="csrf-token"]').attr('content'),
                            partner_id: pid,
                            order_type: 'b2b'
                        }
                    }).done(function(res) {
                        var rows = [];
                        if (Array.isArray(res)) {
                            rows = res;
                        } else if (res && Array.isArray(res.customers)) {
                            rows = res.customers;
                        }
                        done(rows);
                    }).fail(function() {
                        done([]);
                    });
                    return;
                }
                done([]);
            }

            function syncOrderSummaryHiddenFields() {
                $('#subtotal_hidden').val($('#sum_subtotal').text());
                $('#gst_amount_hidden').val($('#sum_gst').text());
                $('#tax_amount_hidden').val($('#sum_gst').text());
                $('#total_amount_hidden').val($('#sum_total').text());
            }

            function wizardStepSaveUrl(step, orderId) {
                var base = (CFG.urls && CFG.urls.poSaveBase) ? String(CFG.urls.poSaveBase) : '/superadmin/orders-add';
                base = base.replace(/\/+$/, '');
                var url = base + '/' + String(step || 1);
                if (orderId) {
                    url += '/' + String(orderId);
                }
                return url;
            }

            function applyGstinResponseToPoFields(r) {
                if (!r || r.error) {
                    return false;
                }
                var root = r.data || r.result || r.taxpayer || r.taxpayerInfo || r;
                var addr = root.pradr && root.pradr.addr ? root.pradr.addr : (root.address || {});
                var companyName = root.company_name || root.trade_name || root.legal_name || root.lgnm || root.tradeNam || '';
                var companyAddress = root.company_address
                    || root.address
                    || [addr.bnm, addr.st, addr.loc, addr.dst].filter(Boolean).join(', ')
                    || '';
                var city = root.city || addr.city || addr.dst || root.district || '';
                var state = root.state || addr.state || addr.stcd || root.stateCode || '';
                var country = root.country || addr.country || 'India';
                var pan = root.pan || root.pan_no || root.panNo || '';

                if (companyName) {
                    $('#po_company_name').val(companyName);
                }
                if (companyAddress) {
                    $('#po_company_address').val(companyAddress);
                }
                if (city) {
                    $('#po_city').val(city);
                }
                if (state) {
                    $('#po_state').val(state);
                }
                if (country) {
                    $('#po_country').val(country);
                }
                if (pan) {
                    $('#po_pan_no').val(pan);
                }
                return true;
            }

            function fetchAndFillGstin(gstin, silent) {
                var g = (gstin || '').trim().toUpperCase();
                if (!g || g.length < 15) {
                    if (!silent && typeof alertify !== 'undefined') {
                        alertify.error('Enter a valid 15-character GSTIN');
                    }
                    return;
                }
                $('#po_gstin').val(g);
                var gstUrl = (CFG.urls && CFG.urls.gstinSearch) ? CFG.urls.gstinSearch : '/api/v1/search-gstin';
                $.ajax({
                    url: gstUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        gstin: g
                    },
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).done(function(r) {
                    if (applyGstinResponseToPoFields(r)) {
                        if (!silent && typeof alertify !== 'undefined') {
                            alertify.success('GSTIN details loaded');
                        }
                    } else if (!silent && typeof alertify !== 'undefined') {
                        alertify.error((r && r.message) ? r.message : 'Invalid GSTIN');
                    }
                }).fail(function() {
                    if (!silent && typeof alertify !== 'undefined') {
                        alertify.error('GSTIN search failed');
                    }
                });
            }

            function autoFillPoFromCustomerMeta() {
                if (CFG.activeTab !== 2) {
                    return;
                }
                var uid = parseInt($('#user_id').val() || String(CFG.selectedUserId || '0'), 10) || 0;
                if (!uid) {
                    return;
                }
                var metaUrl = (CFG.urls && CFG.urls.customerMeta)
                    ? String(CFG.urls.customerMeta).replace('__ID__', uid)
                    : '';
                if (!metaUrl) {
                    return;
                }
                $.getJSON(metaUrl, {
                    order_type: 'b2b',
                    partner_id: parseInt($('#partner_id').val() || String(CFG.partnerId || '0'), 10) || ''
                }).done(function(res) {
                    var p = (res && res.profile) ? res.profile : {};
                    $('#po_customer_id').val(uid);
                    if (p.name && !($('#po_name').val() || '').trim()) {
                        $('#po_name').val(p.name);
                    }
                    if (p.email && !($('#po_email').val() || '').trim()) {
                        $('#po_email').val(p.email);
                    }
                    if (p.mobile && !($('#po_mobile').val() || '').trim()) {
                        $('#po_mobile').val(p.mobile);
                    }
                    if (p.gstin && !($('#po_gstin').val() || '').trim()) {
                        $('#po_gstin').val(p.gstin);
                    }
                    if (p.company_name && !($('#po_company_name').val() || '').trim()) {
                        $('#po_company_name').val(p.company_name);
                    }
                    if (p.company_address && !($('#po_company_address').val() || '').trim()) {
                        $('#po_company_address').val(p.company_address);
                    }
                    if (p.city && !($('#po_city').val() || '').trim()) {
                        $('#po_city').val(p.city);
                    }
                    if (p.state && !($('#po_state').val() || '').trim()) {
                        $('#po_state').val(p.state);
                    }
                    if (p.country && !($('#po_country').val() || '').trim()) {
                        $('#po_country').val(p.country);
                    }
                    if (p.pincode && !($('#po_pincode').val() || '').trim()) {
                        $('#po_pincode').val(String(p.pincode).replace(/[^\d]/g, '').slice(0, 6));
                    }
                });
            }

            function collectPoFormData() {
                var fd = new FormData();
                fd.append('_token', $('meta[name="csrf-token"]').attr('content'));
                var oid = parseInt(CFG.wizardOrderId || $('#order_id').val() || '0', 10);
                if (oid > 0) {
                    fd.append('order_id', String(oid));
                }
                var mode = ($('input[name="po_mode_choice"]:checked').val() || $('#po_mode').val() || 'generated').toString();
                $('#po_mode').val(mode);
                fd.append('po_mode', mode);
                if (mode === 'uploaded') {
                    fd.append('po_number', ($('#po_number_upload').val() || '').toString());
                    fd.append('po_date', ($('#po_date_upload').val() || '').toString());
                    var fileInput = document.getElementById('po_document');
                    if (fileInput && fileInput.files && fileInput.files[0]) {
                        fd.append('po_document', fileInput.files[0]);
                    }
                }
                [
                    'po_name', 'po_email', 'po_mobile', 'po_gstin', 'po_pan_no',
                    'po_company_name', 'po_company_address', 'po_city', 'po_state',
                    'po_pincode', 'po_country'
                ].forEach(function(name) {
                    var $el = $('[name="' + name + '"]');
                    if ($el.length) {
                        fd.append(name, ($el.val() || '').toString());
                    }
                });
                return fd;
            }

            function savePoDetails() {
                var oid = parseInt(CFG.wizardOrderId || $('#order_id').val() || '0', 10);
                if (!oid) {
                    if (typeof alertify !== 'undefined') {
                        alertify.error('Save order details first (step 1).');
                    }
                    return;
                }
                var saveUrl = wizardStepSaveUrl(2, oid);
                var $btn = $('#btn_save_po').prop('disabled', true);
                $.ajax({
                    url: saveUrl,
                    type: 'POST',
                    data: collectPoFormData(),
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).done(function(data) {
                    if (data && data.error) {
                        if (typeof alertify !== 'undefined') {
                            alertify.error(data.message || 'PO save failed');
                        }
                        return;
                    }
                    if (typeof alertify !== 'undefined') {
                        alertify.success((data && data.message) ? data.message : 'PO details saved.');
                    }
                    if (data && data.redirect_url) {
                        window.location.href = data.redirect_url;
                    }
                }).fail(function(xhr) {
                    var msg = 'PO save failed';
                    if (xhr.responseJSON) {
                        if (xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        } else if (xhr.responseJSON.details) {
                            var detailMsg = Object.values(xhr.responseJSON.details).flat()[0];
                            if (detailMsg) {
                                msg = detailMsg;
                            }
                        }
                    }
                    if (typeof alertify !== 'undefined') {
                        alertify.error(msg);
                    }
                }).always(function() {
                    $btn.prop('disabled', false);
                });
            }

            var aadhaarInitSent = false;

            function xhrErrorMessage(xhr, fallback) {
                var msg = fallback || 'Request failed';
                if (xhr && xhr.responseJSON) {
                    if (xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    } else if (xhr.responseJSON.details) {
                        var detailMsg = Object.values(xhr.responseJSON.details).flat()[0];
                        if (detailMsg) {
                            msg = detailMsg;
                        }
                    }
                }
                return msg;
            }

            function updateAadhaarOtpHint(r) {
                var $hint = $('#aadhaar_otp_hint');
                if (!$hint.length) {
                    return;
                }
                var mode = (r && r.data && r.data.mode) ? String(r.data.mode) : '';
                var testOtp = (r && r.data && r.data.test_otp)
                    ? String(r.data.test_otp)
                    : (CFG.aadhaarMockOtp || '123456');
                if (mode === 'test' || CFG.aadhaarTestMode) {
                    $hint.html(
                        'Test mode: no SMS is sent. Enter OTP <strong>' + testOtp + '</strong> (or any 6 digits) to verify.'
                    ).removeClass('text-muted').addClass('text-warning');
                    return;
                }
                $hint.text(
                    'OTP sent to the mobile number registered with UIDAI for this Aadhaar. Check SMS and enter the 6-digit code below.'
                ).removeClass('text-warning').addClass('text-muted');
            }

            function sendAadhaarOtp() {
                var oid = parseInt(CFG.wizardOrderId || $('#order_id').val() || '0', 10);
                var ad = ($('#aadhaar_number_local').val() || '').replace(/\D/g, '');
                if (!oid) {
                    if (typeof alertify !== 'undefined') {
                        alertify.error('Save order details first (step 1).');
                    }
                    return;
                }
                if (!/^\d{12}$/.test(ad)) {
                    if (typeof alertify !== 'undefined') {
                        alertify.error('Enter valid 12-digit Aadhaar number');
                    }
                    return;
                }
                var initUrl = (CFG.urls && CFG.urls.aadhaarInit)
                    ? CFG.urls.aadhaarInit
                    : '/superadmin/orders-aadhaar-init';
                var $btn = $('#aadhaar_send_otp_btn, #aadhaar_resend_otp_btn').prop('disabled', true);
                $.ajax({
                    url: initUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        order_id: oid,
                        aadhaar_number: ad
                    },
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).done(function(r) {
                    if (r && r.success) {
                        aadhaarInitSent = true;
                        $('#aadhaar_number_block').addClass('d-none');
                        $('#aadhaar_otp_block').removeClass('d-none');
                        $('#aadhaar_resend_otp_btn').removeClass('d-none');
                        updateAadhaarOtpHint(r);
                        if (typeof alertify !== 'undefined') {
                            alertify.success(r.message || 'OTP sent');
                        }
                    } else if (typeof alertify !== 'undefined') {
                        alertify.error((r && r.message) ? r.message : 'Failed to send OTP');
                    }
                }).fail(function(xhr) {
                    if (typeof alertify !== 'undefined') {
                        alertify.error(xhrErrorMessage(xhr, 'Failed to send OTP'));
                    }
                }).always(function() {
                    $btn.prop('disabled', false);
                });
            }

            function verifyAadhaarOtp() {
                var oid = parseInt(CFG.wizardOrderId || $('#order_id').val() || '0', 10);
                var otp = ($('#aadhaar_otp_local').val() || '').replace(/\D/g, '');
                if (!oid || !aadhaarInitSent) {
                    if (typeof alertify !== 'undefined') {
                        alertify.error('Send OTP first');
                    }
                    return;
                }
                if (!/^\d{6}$/.test(otp)) {
                    if (typeof alertify !== 'undefined') {
                        alertify.error('Enter valid 6-digit OTP');
                    }
                    return;
                }
                var verifyUrl = (CFG.urls && CFG.urls.aadhaarVerify)
                    ? CFG.urls.aadhaarVerify
                    : '/superadmin/orders-aadhaar-otp-verify';
                var $btn = $('#aadhaar_verify_local_btn').prop('disabled', true);
                $.ajax({
                    url: verifyUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        order_id: oid,
                        otp: otp
                    },
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).done(function(r) {
                    if (r && r.success) {
                        $('#aadhar_verified_local').val('1');
                        if (typeof alertify !== 'undefined') {
                            alertify.success(r.message || 'Aadhaar verified');
                        }
                        if (r.redirect_url) {
                            setTimeout(function() {
                                window.location.href = r.redirect_url;
                            }, 1500);
                        }
                    } else if (typeof alertify !== 'undefined') {
                        alertify.error((r && r.message) ? r.message : 'OTP verification failed');
                    }
                }).fail(function(xhr) {
                    if (typeof alertify !== 'undefined') {
                        alertify.error(xhrErrorMessage(xhr, 'OTP verification failed'));
                    }
                }).always(function() {
                    $btn.prop('disabled', false);
                });
            }

            function resetAadhaarOtpUi() {
                aadhaarInitSent = false;
                $('#aadhaar_otp_local').val('');
                $('#aadhaar_otp_block').addClass('d-none');
                $('#aadhaar_number_block').removeClass('d-none');
                $('#aadhaar_resend_otp_btn').addClass('d-none');
            }

            function saveExistingAadhaar() {
                var oid = parseInt(CFG.wizardOrderId || $('#order_id').val() || '0', 10);
                var ad = ($('#aadhaar_number_save').val() || '').replace(/\D/g, '');
                if (!oid) {
                    if (typeof alertify !== 'undefined') {
                        alertify.error('Save order details first (step 1).');
                    }
                    return;
                }
                if (!/^\d{12}$/.test(ad)) {
                    if (typeof alertify !== 'undefined') {
                        alertify.error('Enter valid 12-digit Aadhaar number');
                    }
                    return;
                }
                var saveUrl = (CFG.urls && CFG.urls.aadhaarSave)
                    ? CFG.urls.aadhaarSave
                    : '/superadmin/orders-save-aadhaar';
                var $btn = $('#btn_save_aadhaar').prop('disabled', true);
                $.ajax({
                    url: saveUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        order_id: oid,
                        aadhaar_number: ad,
                        order_category: ($('#order_category').val() || '').trim()
                    },
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).done(function(r) {
                    if (r && r.success) {
                        if (typeof alertify !== 'undefined') {
                            alertify.success(r.message || 'Aadhaar saved');
                        }
                        if (r.redirect_url) {
                            setTimeout(function() {
                                window.location.href = r.redirect_url;
                            }, 1500);
                        }
                    } else if (typeof alertify !== 'undefined') {
                        alertify.error((r && r.message) ? r.message : 'Failed to save Aadhaar');
                    }
                }).fail(function(xhr) {
                    if (typeof alertify !== 'undefined') {
                        alertify.error(xhrErrorMessage(xhr, 'Failed to save Aadhaar'));
                    }
                }).always(function() {
                    $btn.prop('disabled', false);
                });
            }

            function parseDomainSegments(raw) {
                return (raw || '').split(/\s*[+,]\s*/).map(function(s) {
                    return s.trim();
                }).filter(Boolean);
            }

            function savedDomainSegments() {
                var list = Array.isArray(CFG.savedDomainList) ? CFG.savedDomainList.filter(Boolean) : [];
                if (list.length) {
                    return list;
                }
                var saved = (CFG.savedDomain || '').trim();
                if (!saved) {
                    return [];
                }
                if (saved.charAt(0) === '[') {
                    try {
                        var parsed = JSON.parse(saved);
                        if (Array.isArray(parsed)) {
                            return parsed.map(function(s) {
                                return String(s).trim();
                            }).filter(Boolean);
                        }
                    } catch (e) {}
                }
                return parseDomainSegments(saved);
            }

            function syncDomainUi() {
                var segments = savedDomainSegments();
                $('#domain_multi_select option').each(function() {
                    var v = ($(this).val() || '').trim();
                    if (v && segments.indexOf(v) < 0) {
                        segments.push(v);
                    }
                });
                var multi = segments.length > 1;
                $('#domain_single_wrap').toggleClass('d-none', multi);
                $('#domain_multi_wrap').toggleClass('d-none', !multi);
                $('#btn_add_domain_popup').toggleClass('d-none', !multi);
                var $text = $('#domain_name');
                var $hiddenMulti = $('#domain_name_multi');
                $text.prop('disabled', true).removeAttr('name');
                $hiddenMulti.prop('disabled', true).removeAttr('name');
                if (multi) {
                    $hiddenMulti.prop('disabled', false).attr('name', 'domain_name');
                    var $sel = $('#domain_multi_select');
                    var optionCount = $sel.find('option').filter(function() {
                        return ($(this).val() || '').trim() !== '';
                    }).length;
                    if (!optionCount && segments.length) {
                        $sel.empty().append($('<option>').val('').text('\u2014 Select domain \u2014'));
                        segments.forEach(function(d) {
                            $sel.append($('<option>').val(d).text(d));
                        });
                    }
                    var selected = ($sel.val() || segments[0] || '').trim();
                    if (selected) {
                        $sel.val(selected);
                    }
                    $hiddenMulti.val(selected);
                } else {
                    $text.prop('disabled', false).attr('name', 'domain_name');
                    if (segments.length === 1) {
                        $text.val(segments[0]);
                    }
                    $hiddenMulti.val('').prop('disabled', true);
                }
            }

            // =============================================
            // DOMAIN VALIDATION — require registered domains
            // (Microsoft *.onmicrosoft.com tenant domains are accepted as-is)
            // =============================================
            function checkDomainRegistration(domain, providerId) {
                return new Promise(function(resolve) {
                    // Tizzy (provider 1) - no registrar validation needed
                    if (providerId === 1) {
                        resolve({
                            valid: true,
                            message: 'Domain accepted for Tizzy',
                            status: 'skip',
                            isRegistered: false
                        });
                        return;
                    }

                    if (!domain || domain.trim() === '') {
                        resolve({
                            valid: false,
                            message: 'Domain name is required',
                            status: 'invalid',
                            isRegistered: false
                        });
                        return;
                    }

                    domain = domain.trim().toLowerCase();

                    // Microsoft tenant domains do not need WHOIS/DNS registration checks.
                    if (providerId === 2 && /\.onmicrosoft\.com$/i.test(domain)) {
                        resolve({
                            valid: true,
                            message: '✓ Microsoft tenant domain accepted.',
                            status: 'available',
                            isRegistered: true
                        });
                        return;
                    }

                    var $msg = $('#domain_check_msg');
                    $msg.html('<span class="domain-status-checking">Checking domain registration...</span>');
                    $msg.data('domain-status', 'checking');

                    $.ajax({
                        url: CFG.checkDomainApi || '/superadmin/check-domain',
                        type: 'GET',
                        dataType: 'json',
                        data: {
                            domain_name: domain,
                            provider_id: providerId,
                            transfer_domain: ($('#transfer_domain').val() || '').trim().toLowerCase()
                        }
                    }).done(function(response) {
                        if (!response) {
                            resolve({
                                valid: false,
                                message: 'Domain validation failed',
                                status: 'error',
                                isRegistered: false
                            });
                            return;
                        }

                        var message = response.message || '';
                        // BillingController: available=true means domain is registered / OK to use.
                        var ok = !!(response.success && response.available);
                        var isRegistered = response.registered === true || (response.registered == null && ok);

                        if (ok) {
                            resolve({
                                valid: true,
                                message: message || ('✓ Domain "' + domain + '" is registered.'),
                                status: 'available',
                                isRegistered: true
                            });
                            return;
                        }

                        resolve({
                            valid: false,
                            message: message || ('✗ Domain "' + domain + '" is not registered yet.'),
                            status: isRegistered === false ? 'invalid' : 'invalid',
                            isRegistered: false
                        });
                    }).fail(function(xhr) {
                        var msg = 'Failed to check domain';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        resolve({
                            valid: false,
                            message: '✗ ' + msg,
                            status: 'error',
                            isRegistered: false
                        });
                    });
                });
            }

            var domainCheckTimeout = null;

            function validateDomainWithDebounce() {
                if (domainCheckTimeout) {
                    clearTimeout(domainCheckTimeout);
                }

                var providerId = parseInt($('#provider_id').val() || '0', 10) || 0;
                var domain = '';

                if (!$('#domain_multi_wrap').hasClass('d-none')) {
                    domain = $('#domain_name_multi').val() || '';
                } else {
                    domain = $('#domain_name').val() || '';
                }

                // Check registration for Microsoft (2) and Google (3). Tizzy (1) is skipped.
                if (domain && (providerId === 2 || providerId === 3)) {
                    domainCheckTimeout = setTimeout(function() {
                        checkDomainRegistration(domain, providerId).then(function(result) {
                            var $msg = $('#domain_check_msg');
                            if (result.status === 'available' && result.valid) {
                                $msg.html('<span class="domain-status-available">' + result.message +
                                '</span>');
                                $msg.data('domain-valid', 'true');
                                $msg.data('domain-status', 'available');
                                $msg.data('domain-registered', 'true');
                            } else if (result.status === 'skip') {
                                $msg.html('<span class="domain-status-available">' + result.message +
                                '</span>');
                                $msg.data('domain-valid', 'true');
                                $msg.data('domain-status', 'skip');
                                $msg.data('domain-registered', 'true');
                            } else {
                                $msg.html('<span class="domain-status-invalid">' + result.message +
                                '</span>');
                                $msg.data('domain-valid', 'false');
                                $msg.data('domain-status', result.status || 'invalid');
                                $msg.data('domain-registered', 'false');
                            }
                        });
                    }, 500);
                } else if (providerId === 1) {
                    var $msg = $('#domain_check_msg');
                    $msg.html('<span class="domain-status-available">✓ Domain accepted for Tizzy</span>');
                    $msg.data('domain-valid', 'true');
                    $msg.data('domain-status', 'skip');
                    $msg.data('domain-registered', 'false');
                } else {
                    $('#domain_check_msg').html('');
                    $('#domain_check_msg').data('domain-valid', 'true');
                    $('#domain_check_msg').data('domain-status', '');
                    $('#domain_check_msg').data('domain-registered', '');
                }
            }

            // =============================================
            // END DOMAIN VALIDATION
            // =============================================

            function manageOrderItems(action, data) {
                data = data || {};
                var pid = parseInt($('#provider_id').val() || String(CFG.selectedProviderId || '0'), 10) || 0;
                var $wrap = $('#order_items_wrap');

                if (action === 'provider_id') {
                    return pid;
                }

                if (action === 'saved_items') {
                    var fromOrder = (CFG.orderItems && CFG.orderItems.length) ? CFG.orderItems : [];
                    var fromEdit = (CFG.editState && CFG.editState.plan_lines && CFG.editState.plan_lines.length)
                        ? CFG.editState.plan_lines : [];
                    if (fromOrder.length >= fromEdit.length && fromOrder.length) {
                        return fromOrder;
                    }
                    return fromEdit.length ? fromEdit : [];
                }

                if (action === 'has_variant') {
                    var key = String(pid);
                    if (CFG.providersHasVariant && (CFG.providersHasVariant[key] || CFG.providersHasVariant[pid])) {
                        return true;
                    }
                    var $opt = $('#provider_id option[value="' + key + '"]');
                    return $opt.length && String($opt.data('hasVariant')) === '1';
                }

                if (action === 'row_html') {
                    var rowData = data.row || {};
                    var idx = data.idx;
                    var hasVariant = data.hasVariant;
                    var logo = (CFG.providerLogos && (CFG.providerLogos[String(pid)] || CFG.providerLogos[pid]))
                        ? (CFG.providerLogos[String(pid)] || CFG.providerLogos[pid])
                        : (CFG.providerLogos.default || '');
                    var qty = rowData.quantity || 1;
                    var price = parseFloat(rowData.unit_price || 0) || 0;
                    var variantBlock = hasVariant ?
                        '<div class="item-variant-fields order-item-variant-row variant-main-wrap">' +
                        '<div class="order-item-field"><small class="text-muted d-block">Variant</small>' +
                        '<select class="item-main-variant form-control form-control-sm"><option value="">- Select -</option></select></div>' +
                        '<div class="item-sub-wrap order-item-field d-none"><small class="text-muted d-block">Sub variant</small>' +
                        '<select class="item-sub-variant form-control form-control-sm"><option value="">- Select -</option></select></div></div>' :
                        '';
                    return '<div class="item-row order-item-card border rounded" data-idx="' + idx + '"' +
                        ' data-saved-plan-id="' + (rowData.plan_id || '') + '" data-saved-qty="' + qty + '"' +
                        ' data-saved-unit-price="' + price + '">' +
                        '<div class="order-item-card-inner"><div class="order-item-row-top">' +
                        '<div class="order-item-icon-wrap"><img src="' + logo +
                        '" alt="Provider" class="item-provider-logo"></div>' +
                        '<div class="order-item-fields-top"><div class="order-item-fields-row">' + variantBlock +
                        '<div class="order-item-field item-plan-wrap"><small class="text-muted d-block">Plan</small>' +
                        '<select name="items[' + idx +
                        '][plan_id]" class="item-plan form-select form-select-sm">' +
                        '<option value="">- Select plan -</option></select></div></div></div>' +
                        '<button type="button" class="btn btn-sm btn-link text-danger p-0 btn-remove-item order-item-remove" title="Remove line">&times;</button>' +
                        '</div><div class="order-item-row-bottom"><div class="d-flex">' +
                        '<div class="order-item-price-col me-2"><small class="text-muted d-block">Price</small>' +
                        '<div class="item-price-readonly"><span class="item-price-display">' + formatRupeePrice(
                            price) + '</span>' +
                        '<span class="item-price-hint d-block">per user/year</span></div>' +
                        '<input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="12" autocomplete="off" class="item-price-edit form-control form-control-sm d-none" value="' + (price > 0 ?
                            parseIntPrice(price) : '') + '">' +
                        '<input type="hidden" name="items[' + idx + '][unit_price]" class="item-price" value="' +
                        parseIntPrice(price) + '"></div>' +
                        '<div class="order-item-qty-col"><small class="text-muted d-block">Licenses</small>' +
                        '<div class="input-group input-group-sm"><button class="btn item-qty-minus" type="button" aria-label="Decrease licenses">-</button>' +
                        '<input type="number" name="items[' + idx +
                        '][quantity]" class="item-qty form-control text-center" min="1" step="1" value="' +
                        qty + '">' +
                        '<button class="btn item-qty-plus" type="button" aria-label="Increase licenses">+</button></div>' +
                        '<small class="text-muted d-block item-qty-max-hint d-none" style="font-size:10px;"></small></div></div>' +
                        '<div class="order-item-total-col"><span class="item-total-label">Plan Total</span>' +
                        '<div class="order-item-total-box"><span class="item-row-total">' + formatRupeePrice(
                            parseIntPrice(price) * qty) + '</span></div></div>' +
                        '</div></div>' +
                        '<input type="hidden" name="items[' + idx + '][variant_id]" class="item-variant-id" value="' +
                        (rowData.variant_id || '') + '">' +
                        '<input type="hidden" name="items[' + idx + '][item_name]" class="item-name" value="' + (
                            rowData.item_name || '') + '">' +
                        '<input type="hidden" name="items[' + idx + '][description]" class="item-desc" value="' + (
                            rowData.description || '') + '">' +
                        '</div>';
                }

                if (action === 'fill_variants') {
                    var $row = data.$row;
                    if (!$row || !$row.length) {
                        return;
                    }
                    var $main = $row.find('.item-main-variant');
                    if (!$main.length) {
                        return;
                    }
                    var list = CFG.mainVariantsByProvider[String(pid)] || CFG.mainVariantsByProvider[pid] || [];
                    $main.empty().append('<option value="">- Select -</option>');
                    list.forEach(function(v) {
                        $main.append('<option value="' + v.id + '">' + v.name + '</option>');
                    });
                    if (data.row && data.row.main_variant_id) {
                        $main.val(String(data.row.main_variant_id));
                        var subs = CFG.subVariantsByMain[data.row.main_variant_id] || CFG.subVariantsByMain[
                            String(data.row.main_variant_id)] || [];
                        if (subs.length) {
                            var $sub = $row.find('.item-sub-variant').empty().append(
                            '<option value="">- Select -</option>');
                            $row.find('.item-sub-wrap').removeClass('d-none');
                            subs.forEach(function(s) {
                                $sub.append('<option value="' + s.id + '">' + s.name + '</option>');
                            });
                            if (data.row.sub_variant_id) {
                                $sub.val(String(data.row.sub_variant_id));
                            }
                        }
                    }
                    return;
                }

                if (action === 'update_row') {
                    var $row = data.$row;
                    if (!$row || !$row.length) {
                        return;
                    }
                    var logo = (CFG.providerLogos && (CFG.providerLogos[String(pid)] || CFG.providerLogos[pid]))
                        ? (CFG.providerLogos[String(pid)] || CFG.providerLogos[pid])
                        : (CFG.providerLogos.default || '');
                    $row.find('.item-provider-logo').attr('src', logo);
                    var $opt = $row.find('.item-plan option:selected');
                    var planName = ($opt.attr('data-name') || $opt.text() || '').trim();
                    $row.find('.item-name').val(planName);
                    var price = 0;
                    if ($opt.length && $opt.val()) {
                        price = selectedPlanUnitPrice($opt);
                    } else {
                        var savedPrice = parseFloat($row.attr('data-saved-unit-price') || '0') || 0;
                        if (savedPrice > 0) {
                            price = savedPrice;
                        }
                    }
                    $row.find('.item-price').val(String(parseIntPrice(price)));
                    $row.find('.item-price-display').text(formatRupeePrice(price));
                    $row.find('.item-price-edit').val(price > 0 ? String(parseIntPrice(price)) : '');
                    syncPriceEditMode($row, price);
                    var qty = parseInt($row.find('.item-qty').val(), 10) || 1;
                    var limit = parseInt($opt.attr('data-customer-limit') || '0', 10) || 0;
                    if (limit > 0 && qty > limit) {
                        qty = limit;
                        $row.find('.item-qty').val(qty);
                    }
                    var $hint = $row.find('.item-qty-max-hint');
                    if (limit > 0) {
                        $hint.removeClass('d-none').text('Max ' + limit);
                    } else {
                        $hint.addClass('d-none').text('');
                    }
                    $row.find('.item-row-total').text(formatRupeePrice(parseIntPrice(price) * qty));
                    if ($opt.val()) {
                        $row.attr('data-saved-plan-id', $opt.val());
                    }
                    $row.attr('data-saved-qty', qty);
                    $row.attr('data-saved-unit-price', price > 0 ? String(parseIntPrice(price)) : '0');
                    return;
                }

                if (action === 'load_plans') {
                    var $row = data.$row;
                    if (!$row || !$row.length || !pid) {
                        return;
                    }
                    var hasVariant = manageOrderItems('has_variant');
                    var vid = 0;
                    if ($row.find('.item-sub-variant').val()) {
                        vid = parseInt($row.find('.item-sub-variant').val(), 10) || 0;
                    } else if ($row.find('.item-main-variant').val()) {
                        vid = parseInt($row.find('.item-main-variant').val(), 10) || 0;
                    }
                    var keepPlanId = parseInt(data.keepPlanId || $row.attr('data-saved-plan-id') || $row.find(
                        '.item-plan').val() || '0', 10) || 0;
                    if (hasVariant && vid <= 0 && !keepPlanId) {
                        $row.find('.item-plan').empty().append(
                        '<option value="">- Select variant first -</option>');
                        return;
                    }
                    $row.find('.item-variant-id').val(vid || '');
                    var $plan = $row.find('.item-plan');
                    var reqId = Date.now() + Math.random();
                    $row.data('plansReqId', reqId);
                    $plan.empty().append('<option value="">Loading plans...</option>');
                    var url = (CFG.urls && CFG.urls.plansByProvider) ? CFG.urls.plansByProvider : '';
                    if (!url) {
                        return;
                    }
                    $.getJSON(url, { provider_id: pid, variant_id: vid || 0 }).done(function(res) {
                        if ($row.data('plansReqId') !== reqId) {
                            return;
                        }
                        var list = (res && res.plans) ? res.plans : [];
                        $plan.empty().append('<option value="">- Select plan -</option>');
                        list.forEach(function(p) {
                            var unitPrice = planUnitPrice(p);
                            $plan.append(
                                $('<option></option>').val(String(p.id))
                                .attr('data-price', unitPrice)
                                .attr('data-b2c-price', p.b2c_price || p.actual_price || p.price ||
                                    0)
                                .attr('data-actual-price', p.actual_price || 0)
                                .attr('data-name', p.name)
                                .attr('data-customer-limit', p.customer_limit || 0)
                                .text(p.name)
                            );
                        });
                        if (keepPlanId) {
                            if (!$plan.find('option[value="' + keepPlanId + '"]').length) {
                                var label = ($row.find('.item-name').val() || ('Plan #' + keepPlanId))
                                    .trim();
                                var savedPrice = parseFloat($row.attr('data-saved-unit-price') ||
                                '0') || 0;
                                $plan.append(
                                    $('<option></option>').val(String(keepPlanId))
                                    .attr('data-price', savedPrice)
                                    .attr('data-name', label)
                                    .text(label)
                                );
                            }
                            $plan.val(String(keepPlanId));
                        }
                        manageOrderItems('update_row', { $row: $row });
                        manageOrderItems('recalc');
                    }).fail(function() {
                        if ($row.data('plansReqId') !== reqId) {
                            return;
                        }
                        $plan.empty().append('<option value="">- Select plan -</option>');
                        if (keepPlanId) {
                            var label = ($row.find('.item-name').val() || ('Plan #' + keepPlanId))
                                .trim();
                            $plan.append($('<option></option>').val(String(keepPlanId)).text(label));
                            $plan.val(String(keepPlanId));
                        }
                        manageOrderItems('update_row', { $row: $row });
                    });
                    return;
                }

                if (action === 'load_all_plans') {
                    $wrap.find('.item-row').each(function() {
                        var $row = $(this);
                        manageOrderItems('load_plans', {
                            $row: $row,
                            keepPlanId: parseInt($row.attr('data-saved-plan-id') || '0', 10) || 0
                        });
                    });
                    return;
                }

                if (action === 'add_row') {
                    if (pid === 3 && $wrap.find('.item-row').length >= 1) {
                        if (typeof alertify !== 'undefined') {
                            alertify.warning('Google provider allows only one plan.');
                        }
                        return;
                    }
                    var rowData = data.row || {};
                    var hasVariant = manageOrderItems('has_variant');
                    $wrap.append(manageOrderItems('row_html', { idx: itemIdx, row: rowData,
                        hasVariant: hasVariant }));
                    var $row = $wrap.find('.item-row').last();
                    if (hasVariant) {
                        manageOrderItems('fill_variants', { $row: $row, row: rowData });
                    }
                    if (rowData.item_name) {
                        $row.find('.item-name').val(rowData.item_name);
                    }
                    itemIdx++;
                    if (pid) {
                        manageOrderItems('load_plans', { $row: $row, keepPlanId: rowData.plan_id || 0 });
                    }
                    manageOrderItems('sync_provider_ui');
                    manageOrderItems('recalc');
                    return;
                }

                if (action === 'hydrate_rows') {
                    $wrap.empty();
                    itemIdx = 0;
                    var items = manageOrderItems('saved_items');
                    if (!items.length) {
                        manageOrderItems('add_row', { row: {} });
                        return;
                    }
                    if (pid === 3 && items.length > 1) {
                        items = [items[0]];
                    }
                    items.forEach(function(row) {
                        manageOrderItems('add_row', { row: row });
                    });
                    return;
                }

                if (action === 'provider_change') {
                    var rows = [];
                    $wrap.find('.item-row').each(function() {
                        var $r = $(this);
                        rows.push({
                            plan_id: $r.find('.item-plan').val() || $r.attr('data-saved-plan-id') ||
                                '',
                            quantity: $r.find('.item-qty').val() || $r.attr('data-saved-qty') ||
                                1,
                            unit_price: $r.find('.item-price').val() || $r.attr(
                                'data-saved-unit-price') || '',
                            variant_id: $r.find('.item-variant-id').val(),
                            item_name: $r.find('.item-name').val(),
                            description: $r.find('.item-desc').val(),
                            main_variant_id: $r.find('.item-main-variant').val() || '',
                            sub_variant_id: $r.find('.item-sub-variant').val() || ''
                        });
                    });
                    $wrap.empty();
                    itemIdx = 0;
                    if (pid === 3 && rows.length > 1) {
                        rows = [rows[0]];
                    }
                    if (!rows.length) {
                        manageOrderItems('add_row', { row: {} });
                    } else {
                        rows.forEach(function(row) {
                            manageOrderItems('add_row', { row: row });
                        });
                    }
                    manageOrderItems('sync_provider_ui');
                    return;
                }

                if (action === 'sync_provider_ui') {
                    var isNew = ($('#order_category').val() || '').toLowerCase() === 'new';
                    if (pid === 1) {
                        $('#btn_add_item').removeClass('d-none');
                        $wrap.find('.btn-remove-item').removeClass('d-none');
                        $('#transfer_domain_wrap').hide();
                    } else if (pid === 2) {
                        $('#btn_add_item').removeClass('d-none');
                        $wrap.find('.btn-remove-item').removeClass('d-none');
                        if (isNew) {
                            $('#transfer_domain_wrap').show();
                        } else {
                            $('#transfer_domain_wrap').hide();
                        }
                    } else if (pid === 3) {
                        $('#btn_add_item').addClass('d-none');
                        $wrap.find('.btn-remove-item').addClass('d-none');
                        if ($wrap.find('.item-row').length > 1) {
                            $wrap.find('.item-row').slice(1).remove();
                        }
                        if (isNew) {
                            $('#transfer_domain_wrap').show();
                        } else {
                            $('#transfer_domain_wrap').hide();
                        }
                    } else {
                        $('#btn_add_item').removeClass('d-none');
                        $wrap.find('.btn-remove-item').removeClass('d-none');
                        $('#transfer_domain_wrap').hide();
                    }
                    var hasVariant = manageOrderItems('has_variant');
                    $wrap.find('.item-row').each(function() {
                        if (hasVariant) {
                            $(this).find('.variant-main-wrap').show();
                        } else {
                            $(this).find('.variant-main-wrap').hide();
                            $(this).find('.item-sub-wrap').hide();
                            $(this).find('.item-variant-id').val('');
                        }
                    });
                    return;
                }

                if (action === 'recalc') {
                    var rowSubtotal = 0;
                    $wrap.find('.item-row').each(function() {
                        rowSubtotal += (parseFloat($(this).find('.item-price').val()) || 0) *
                            (parseInt($(this).find('.item-qty').val(), 10) || 1);
                    });
                    var probeSubtotal = rowSubtotal > 0 ? +(rowSubtotal.toFixed(2)) : resolvePlanSubtotal(0);
                    if (isEditOrder && !window._pricingDirty) {
                        var storedSubtotal = parseFloat($('#subtotal_hidden').val()) || 0;
                        if (Math.abs(probeSubtotal - storedSubtotal) > 0.009) {
                            window._pricingDirty = true;
                        }
                    }
                    if (!window._pricingDirty && applyStoredOrderSummary()) {
                        return;
                    }
                    var subtotal = probeSubtotal;
                    var applyGst = $('#apply_gst_toggle').is(':checked');
                    $('#apply_gst_hidden').val(applyGst ? 1 : 0);
                    var manualDisc = parseFloat($('#discount_amount').val()) || 0;
                    var promoPercent = isExistingOrderCategory() ? 0 : (window._promoPercent || 0);
                    var discountAmt = isExistingOrderCategory()
                        ? 0
                        : (promoPercent > 0 ? fillDiscountFromPromo(subtotal) : manualDisc);
                    discountAmt = Math.min(Math.max(0, discountAmt), subtotal > 0 ? subtotal : discountAmt);
                    var taxableBase = Math.min(discountAmt, subtotal);
                    var taxable = Math.max(0, +(subtotal - taxableBase).toFixed(2));
                    var gst = applyGst ? +(taxable * GST_RATE / 100).toFixed(2) : 0;
                    var total = +(taxable + gst).toFixed(2);
                    paintOrderSummary(subtotal, taxableBase, taxable, gst, total);
                    return;
                }

                if (action === 'init') {
                    var initialPid = parseInt(CFG.selectedProviderId || '0', 10) || 0;
                    if (!initialPid) {
                        var saved = manageOrderItems('saved_items');
                        if (saved.length && saved[0].provider_id) {
                            initialPid = parseInt(saved[0].provider_id, 10) || 0;
                        }
                    }
                    if (initialPid > 0) {
                        $('#provider_id').val(String(initialPid));
                    }
                    if (typeof CFG.savedApplyGst !== 'undefined') {
                        $('#apply_gst_toggle').prop('checked', !!CFG.savedApplyGst);
                        $('#apply_gst_hidden').val(CFG.savedApplyGst ? 1 : 0);
                    }
                    if (CFG.savedPromoCode) {
                        $('#promo_code').val(CFG.savedPromoCode);
                    }
                    manageOrderItems('hydrate_rows');
                    if ($('#provider_id').val()) {
                        manageOrderItems('load_all_plans');
                    }
                    manageOrderItems('sync_provider_ui');
                    if (($('#promo_code').val() || '').trim()) {
                        promoCodeApply({ silent: true });
                    } else {
                        manageOrderItems('recalc');
                    }
                    return;
                }
            }

            // Event Handlers
            $('#btn_add_item').on('click', function() {
                manageOrderItems('add_row', { row: {} });
            });

            $('#provider_id').on('change', function() {
                manageOrderItems('provider_change');
                syncDomainUi();
                // Clear domain validation message
                $('#domain_check_msg').html('');
                $('#domain_check_msg').data('domain-valid', 'true');
                $('#domain_check_msg').data('domain-status', '');
                $('#domain_check_msg').data('domain-registered', '');
                // Trigger validation if domain exists
                var domain = $('#domain_name').val() || '';
                if (domain) {
                    validateDomainWithDebounce();
                }
            });

            $('#partner_id').on('change', function() {
                CFG.selectedUserId = null;
                CFG.partnerId = parseInt($(this).val() || '0', 10) || 0;
                loadPartnerCustomers(null);
            });

            $('#user_id').on('change', function() {
                CFG.selectedUserId = parseInt($(this).val() || '0', 10) || null;
                autoFillPoFromCustomerMeta();
            });

            $(document).on('click', '#btn_save_po', function(e) {
                e.preventDefault();
                savePoDetails();
            });

            function syncNewPlanPoModeUi() {
                var mode = ($('input[name="po_mode_choice"]:checked').val() || 'generated').toString();
                $('#po_mode').val(mode);
                if (mode === 'uploaded') {
                    $('#po_upload_fields').removeClass('d-none');
                } else {
                    $('#po_upload_fields').addClass('d-none');
                }
            }
            $(document).on('change', 'input[name="po_mode_choice"]', syncNewPlanPoModeUi);
            syncNewPlanPoModeUi();

            $(document).on('click', '#po_gstin_search_btn', function(e) {
                e.preventDefault();
                fetchAndFillGstin($('#po_gstin').val(), false);
            });

            $(document).on('blur', '#po_gstin', function() {
                var gstin = ($(this).val() || '').trim();
                if (gstin.length >= 15) {
                    fetchAndFillGstin(gstin, true);
                }
            });

            $(document).on('click', '#aadhaar_send_otp_btn, #aadhaar_resend_otp_btn', function(e) {
                e.preventDefault();
                sendAadhaarOtp();
            });

            $(document).on('click', '#aadhaar_verify_local_btn', function(e) {
                e.preventDefault();
                verifyAadhaarOtp();
            });

            $(document).on('click', '#aadhaar_back_btn', function(e) {
                e.preventDefault();
                resetAadhaarOtpUi();
            });

            $(document).on('click', '#btn_save_aadhaar', function(e) {
                e.preventDefault();
                saveExistingAadhaar();
            });

            $('#domain_multi_select').on('change', function() {
                $('#domain_name_multi').val(($(this).val() || '').trim());
                validateDomainWithDebounce();
            });

            $(document).on('click', '.btn-remove-item', function() {
                if (manageOrderItems('provider_id') === 3) {
                    return;
                }
                if ($('#order_items_wrap .item-row').length > 1) {
                    $(this).closest('.item-row').remove();
                    manageOrderItems('recalc');
                }
            });

            function applyItemQty($row, nextQty) {
                var qty = Math.max(1, parseInt(nextQty, 10) || 1);
                var limit = parseInt($row.find('.item-plan option:selected').attr('data-customer-limit') || '0',
                10) || 0;
                if (limit > 0 && qty > limit) {
                    qty = limit;
                    if (typeof alertify !== 'undefined') {
                        alertify.warning('Maximum ' + limit + ' licenses allowed for this plan.');
                    }
                }
                $row.find('.item-qty').val(qty);
                manageOrderItems('update_row', { $row: $row });
                manageOrderItems('recalc');
            }

            $(document).on('click', '.item-qty-plus', function() {
                var $row = $(this).closest('.item-row');
                var qty = parseInt($row.find('.item-qty').val(), 10) || 1;
                applyItemQty($row, qty + 1);
            });

            $(document).on('click', '.item-qty-minus', function() {
                var $row = $(this).closest('.item-row');
                var qty = parseInt($row.find('.item-qty').val(), 10) || 1;
                applyItemQty($row, qty - 1);
            });

            $(document).on('change input', '.item-qty', function() {
                applyItemQty($(this).closest('.item-row'), $(this).val());
            });

            $(document).on('change', '.item-main-variant', function() {
                var $row = $(this).closest('.item-row');
                var mid = $(this).val();
                var subs = CFG.subVariantsByMain[mid] || CFG.subVariantsByMain[String(mid)] || [];
                var $subWrap = $row.find('.item-sub-wrap');
                var $sub = $row.find('.item-sub-variant').empty().append('<option value="">- Select -</option>');
                if (subs.length) {
                    $subWrap.removeClass('d-none');
                    subs.forEach(function(s) {
                        $sub.append('<option value="' + s.id + '">' + s.name + '</option>');
                    });
                } else {
                    $subWrap.addClass('d-none');
                }
                manageOrderItems('load_plans', { $row: $row });
            });

            $(document).on('change', '.item-sub-variant', function() {
                manageOrderItems('load_plans', { $row: $(this).closest('.item-row') });
            });

            $(document).on('change', '.item-plan', function() {
                var $row = $(this).closest('.item-row');
                $row.attr('data-saved-unit-price', '0');
                manageOrderItems('update_row', { $row: $row });
                manageOrderItems('recalc');
            });

            $(document).on('keydown', '.item-price-edit', function(e) {
                if (e.key === '.' || e.key === ',' || e.key === 'e' || e.key === 'E' || e.key === '-' || e.key ===
                    '+') {
                    e.preventDefault();
                }
            });

            $(document).on('input', '.item-price-edit', function() {
                var $input = $(this);
                var digits = String($input.val() || '').replace(/\D/g, '');
                if ($input.val() !== digits) {
                    $input.val(digits);
                }
            });

            $(document).on('keyup input change', '.item-price-edit', function() {
                var $row = $(this).closest('.item-row');
                var price = parseIntPrice($(this).val());
                var currentVal = $(this).val();
                if (currentVal !== '' && !/^\d*$/.test(currentVal)) {
                    var digits = currentVal.replace(/\D/g, '');
                    $(this).val(digits);
                    price = parseIntPrice(digits);
                }
                $row.find('.item-price').val(String(price));
                $row.find('.item-price-display').text(formatRupeePrice(price));
                $row.attr('data-saved-unit-price', price > 0 ? String(price) : '0');
                var qty = parseInt($row.find('.item-qty').val(), 10) || 1;
                $row.find('.item-row-total').text(formatRupeePrice(price * qty));
                manageOrderItems('recalc');
            });

            $(document).on('change input', '#discount_amount, #apply_gst_toggle', function() {
                if (window._updatingDiscountFromPromo) {
                    return;
                }
                window._pricingDirty = true;
                if ($(this).is('#discount_amount')) {
                    window._promoPercent = 0;
                    window._lastPromoAlertCode = '';
                    setPromoHint('', false);
                }
                manageOrderItems('recalc');
            });

            $(document).on('mousedown', '#btn_apply_promo', function(e) {
                e.preventDefault();
            });
            $(document).on('click', '#btn_apply_promo', function(e) {
                e.preventDefault();
                promoCodeApply();
            });

            $(document).on('keydown', '#promo_code', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    promoCodeApply();
                }
            });

            // Trigger domain validation on input
            $(document).on('input', '#domain_name', function() {
                validateDomainWithDebounce();
            });

            $('#order_category').on('change', function() {
                applyOrderCategoryUi();
            });

            $('#subscription_start_date').on('change', function() {
                if (CFG.orderStatusOnly || isExistingOrderCategory()) {
                    return;
                }
                var startVal = ($(this).val() || '').trim();
                if (startVal) {
                    $('#subscription_end_date').val(addOneYearMinusOneDay(startVal));
                }
            });

            $('#wizard_order_status').on('change', function() {
                var st = ($(this).val() || '').toLowerCase();
                $('#wizard_reject_reason_wrap').toggleClass('d-none', st !== 'rejected');
            });

            function submitStatusOnly(status) {
                var oid = parseInt(CFG.wizardOrderId || $('#order_id').val() || '0', 10);
                if (!oid) {
                    return;
                }
                var action = status === 'completed' ? 'active' : (status === 'rejected' ? 'cancel' : '');
                if (!action) {
                    return;
                }
                if (action === 'cancel') {
                    var rejectionReason = ($('#wizard_rejection_reason').val() || '').trim();
                    if (!rejectionReason) {
                        $('#wizard_reject_reason_wrap').removeClass('d-none');
                        if (typeof alertify !== 'undefined') {
                            alertify.error('Enter cancellation reason.');
                        }
                        $('#wizard_rejection_reason').focus();
                        return;
                    }
                }
                var actionUrl = (CFG.urls && CFG.urls.orderAction)
                    ? String(CFG.urls.orderAction).replace('__ID__', String(oid))
                    : ('/superadmin/orders-action/' + oid);
                var $btns = $('#btn_status_active, #btn_status_cancel').prop('disabled', true);
                $.ajax({
                    url: actionUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val() || '',
                        action: action,
                        rejection_reason: ($('#wizard_rejection_reason').val() || '').trim()
                    },
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).done(function(data) {
                    if (data && data.success === false) {
                        if (typeof alertify !== 'undefined') {
                            alertify.error(data.message || 'Status update failed');
                        }
                        return;
                    }
                    if (typeof alertify !== 'undefined') {
                        alertify.success((data && data.message) ? data.message : 'Status updated.');
                    }
                    window.location.reload();
                }).fail(function(xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Status update failed';
                    if (typeof alertify !== 'undefined') {
                        alertify.error(msg);
                    }
                }).always(function() {
                    $btns.prop('disabled', false);
                });
            }

            function confirmStatusAction(actionLabel, onYes) {
                var msg = 'Are you sure you want to ' + actionLabel + ' this order?';
                if (typeof alertify !== 'undefined' && typeof alertify.confirm === 'function') {
                    alertify.confirm('Confirm', msg, function() { onYes(); }, function() {});
                    return;
                }
                if (window.confirm(msg)) {
                    onYes();
                }
            }

            $('#btn_status_active').on('click', function() {
                confirmStatusAction('activate', function() {
                    submitStatusOnly('completed');
                });
            });
            $('#btn_status_cancel').on('click', function() {
                var $wrap = $('#wizard_reject_reason_wrap');
                if ($wrap.hasClass('d-none')) {
                    $wrap.removeClass('d-none');
                    $('#wizard_rejection_reason').focus();
                    if (typeof alertify !== 'undefined') {
                        alertify.message('Enter cancellation reason, then click Cancel again.');
                    }
                    return;
                }
                confirmStatusAction('cancel', function() {
                    submitStatusOnly('rejected');
                });
            });

            function ensureSelectedCustomerVisible() {
                var pick = parseInt(CFG.selectedUserId || '0', 10) || 0;
                var label = String(CFG.selectedUserLabel || '').trim();
                var $sel = $('#user_id');
                if (!$sel.length || !pick) {
                    return;
                }
                if (!$sel.find('option[value="' + pick + '"]').length) {
                    $sel.empty().append(
                        $('<option>').val(String(pick)).text(label || ('Customer #' + pick)).prop('selected', true)
                    );
                } else {
                    $sel.val(String(pick));
                }
                if (CFG.orderStatusOnly) {
                    $sel.prop('disabled', true);
                }
            }

            function lockOrderDetailsForStatusOnly() {
                if (!CFG.orderStatusOnly) {
                    return;
                }
                ensureSelectedCustomerVisible();
                var $panel = $('.sa-order-panel[data-panel="1"]');
                $panel.find('input, select, textarea, button')
                    .not('#btn_status_active, #btn_status_cancel, #wizard_rejection_reason, #subscription_start_date, #subscription_end_date, #btn_save_subscription_dates')
                    .each(function() {
                        var $el = $(this);
                        if ($el.is('button') || $el.is('select') || $el.attr('type') === 'checkbox' || $el.attr('type') === 'radio' || $el.attr('type') === 'file') {
                            $el.prop('disabled', true);
                        } else {
                            $el.prop('readonly', true);
                        }
                    });
                $('#subscription_start_date, #subscription_end_date')
                    .prop('readonly', false)
                    .prop('disabled', false);
                $('#btn_save_subscription_dates').prop('disabled', false);
                $('#btn_add_item, .btn-remove-item, .Plus-Icon, #btn_add_domain_popup').addClass('d-none').prop('disabled', true);
                $panel.find('a.Plus-Icon').addClass('d-none');
                // Keep the selected customer option visible even while disabled.
                ensureSelectedCustomerVisible();
            }

            $('#btn_save_subscription_dates').on('click', function() {
                var oid = parseInt(CFG.wizardOrderId || $('#order_id').val() || '0', 10);
                if (!oid) {
                    return;
                }
                var startVal = ($('#subscription_start_date').val() || '').trim();
                var endVal = ($('#subscription_end_date').val() || '').trim();
                if (!startVal) {
                    if (typeof alertify !== 'undefined') {
                        alertify.error('Subscription start date is required.');
                    }
                    $('#subscription_start_date').focus();
                    return;
                }
                if (!isExistingOrderCategory() && !endVal) {
                    if (typeof alertify !== 'undefined') {
                        alertify.error('Subscription end date is required.');
                    }
                    $('#subscription_end_date').focus();
                    return;
                }
                if (startVal && endVal && endVal < startVal) {
                    if (typeof alertify !== 'undefined') {
                        alertify.error('End date must be on or after the start date.');
                    }
                    $('#subscription_end_date').focus();
                    return;
                }
                var saveUrl = (CFG.urls && CFG.urls.save) ? CFG.urls.save : '';
                if (!saveUrl) {
                    return;
                }
                var $btn = $(this).prop('disabled', true);
                $.ajax({
                    url: saveUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val() || '',
                        order_id: oid,
                        subscription_dates_update: '1',
                        subscription_start_date: startVal,
                        subscription_end_date: endVal
                    },
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).done(function(data) {
                    if (data && data.error) {
                        if (typeof alertify !== 'undefined') {
                            alertify.error(data.message || 'Could not update dates.');
                        }
                        return;
                    }
                    if (typeof alertify !== 'undefined') {
                        alertify.success((data && data.message) ? data.message : 'Subscription dates updated.');
                    }
                }).fail(function(xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.message)
                        ? xhr.responseJSON.message
                        : 'Could not update dates.';
                    if (typeof alertify !== 'undefined') {
                        alertify.error(msg);
                    }
                }).always(function() {
                    $btn.prop('disabled', false);
                });
            });

            // Form submission with domain validation
            $('#sa_order_form').on('submit', function(e) {
                e.preventDefault();

                if (!$('#domain_multi_wrap').hasClass('d-none') && !$('#domain_name_multi').val()) {
                    if (typeof alertify !== 'undefined') {
                        alertify.error('Please select a domain from the list.');
                    }
                    return;
                }

                var providerId = parseInt($('#provider_id').val() || '0', 10) || 0;
                var domain = '';
                if (!$('#domain_multi_wrap').hasClass('d-none')) {
                    domain = ($('#domain_name_multi').val() || '').trim();
                } else {
                    domain = ($('#domain_name').val() || '').trim();
                }

                // Microsoft + Google: domain must be present and registered.
                if (providerId === 2 || providerId === 3) {
                    if (!domain) {
                        if (typeof alertify !== 'undefined') {
                            alertify.error('Domain name is required.');
                        }
                        $('#domain_name').focus();
                        return;
                    }

                    var domainStatus = $('#domain_check_msg').data('domain-status');
                    var isRegistered = $('#domain_check_msg').data('domain-registered');
                    var msgHtml = ($('#domain_check_msg').html() || '').toString();

                    if (domainStatus === 'invalid' || domainStatus === 'error' || isRegistered === 'false') {
                        if (typeof alertify !== 'undefined') {
                            alertify.error('This domain is not registered yet. Please use a registered domain.');
                        }
                        $('#domain_name').focus();
                        return;
                    }

                    if (!msgHtml || domainStatus === 'checking' || msgHtml.indexOf('Checking domain') !== -1) {
                        if (typeof alertify !== 'undefined') {
                            alertify.message('Please wait while we check domain registration...');
                        }
                        validateDomainWithDebounce();
                        setTimeout(function() {
                            var newStatus = $('#domain_check_msg').data('domain-status');
                            var newRegistered = $('#domain_check_msg').data('domain-registered');
                            if (newStatus === 'available' && newRegistered === 'true') {
                                submitOrderForm();
                            } else if (typeof alertify !== 'undefined') {
                                alertify.error('This domain is not registered yet. Please use a registered domain.');
                            }
                        }, 1500);
                        return;
                    }

                    if (domainStatus !== 'available' && domainStatus !== 'skip') {
                        if (typeof alertify !== 'undefined') {
                            alertify.error('Please verify the domain before saving.');
                        }
                        return;
                    }
                }

                submitOrderForm();
            });

            function submitOrderForm() {
                manageOrderItems('recalc');
                syncOrderSummaryHiddenFields();
                var fd = new FormData($('#sa_order_form')[0]);
                fd.set('defer_po_save', '1');
                fd.set('send_notifications', '0');
                var oid = parseInt(CFG.wizardOrderId || $('#order_id').val() || '0', 10);
                if (oid > 0 && $('#wizard_order_status').length) {
                    var status = ($('#wizard_order_status').val() || '').toLowerCase();
                    var rejectionReason = ($('#wizard_rejection_reason').val() || '').trim();

                    // ✅ Use CFG values for status checks
                    if (status === 'pending' && CFG.wizardOrderIsActive) {
                        if (typeof alertify !== 'undefined') {
                            alertify.error('Active order cannot be moved back to pending.');
                        }
                        return;
                    }
                    if (status === 'pending' && CFG.wizardOrderIsProcessing) {
                        if (typeof alertify !== 'undefined') {
                            alertify.error('Processing order cannot be moved back to pending.');
                        }
                        return;
                    }
                    if (status === 'rejected' && !rejectionReason) {
                        if (typeof alertify !== 'undefined') {
                            alertify.error('Enter cancellation reason.');
                        }
                        $('#wizard_rejection_reason').focus();
                        return;
                    }
                    fd.set('status', status);
                    if (status === 'rejected') {
                        fd.set('rejection_reason', rejectionReason);
                    }
                }
                if (isExistingOrderCategory()) {
                    fd.set('promocode', '');
                    fd.set('promo_code', '');
                    fd.set('discount_amount', '0');
                } else {
                    var promoVal = ($('#promo_code').val() || '').trim();
                    if (promoVal) {
                        fd.set('promocode', promoVal);
                        fd.set('promo_code', promoVal);
                    }
                }
                var saveUrl = (CFG.urls && CFG.urls.save) ? CFG.urls.save : '';
                if (!saveUrl) {
                    return;
                }
                var $btn = $('#btn_save_order').prop('disabled', true);
                $.ajax({
                    url: saveUrl,
                    type: 'POST',
                    data: fd,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).done(function(data) {
                    if (data.error) {
                        if (typeof alertify !== 'undefined') {
                            alertify.error(data.message || 'Save failed');
                        }
                        return;
                    }
                    if (typeof alertify !== 'undefined') {
                        alertify.success(data.message || 'Order saved.');
                    }
                    if (data.redirect_url) {
                        window.location.href = data.redirect_url;
                    }
                }).fail(function(xhr) {
                    var msg = 'Save failed';
                    if (xhr.responseJSON) {
                        if (xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        } else if (xhr.responseJSON.details) {
                            var detailMsg = Object.values(xhr.responseJSON.details).flat()[0];
                            if (detailMsg) {
                                msg = detailMsg;
                            }
                        }
                    }
                    if (typeof alertify !== 'undefined') {
                        alertify.error(msg);
                    }
                }).always(function() {
                    $btn.prop('disabled', false);
                });
            }

            $(function() {
                manageOrderItems('init');
                applyOrderCategoryUi();
                syncDomainUi();
                syncAllItemPriceEditModes();
                if (CFG.orderStatusOnly) {
                    // Keep server-rendered selected customer; do not reload/clear the dropdown.
                    lockOrderDetailsForStatusOnly();
                } else if ($('#partner_id').length) {
                    loadPartnerCustomers(CFG.selectedUserId || null);
                }
                if (CFG.activeTab === 2) {
                    autoFillPoFromCustomerMeta();
                }
            });
        })(jQuery);
    </script>
@endpush