@extends('superadmin.layouts.app')

@section('content')
    @php
        $isEdit = !empty($order);
        $successfulOrderStatuses = $orderSuccessfulStatuses ?? ['completed', 'paid', 'success', 'active'];
        $orderLockStatus = '';
        if ($isEdit && $order !== null) {
            $orderLockStatus = strtolower((string) ($order->resolved_status ?? $order->status ?? ''));
        }
        $poLocked = $isEdit && in_array($orderLockStatus, $successfulOrderStatuses, true);
        $poReadOnly = $poLocked ? 'readonly' : '';
        $poDisabled = $poLocked ? 'disabled' : '';
        $poModeValue = (string) old('po_mode', $isEdit ? $order->po_mode ?? '' : '');
        if (trim($poModeValue) === '-') {
            $poModeValue = '';
        }
        $currentStep = (int) ($currentStep ?? 1);
        $currentStep = $currentStep < 1 ? 1 : ($currentStep > 3 ? 3 : $currentStep);
        $wizardOrderId = $wizardOrderId ?? ($isEdit ? $order->id : null);
        $orderCompleted = !empty($orderCompleted);
        $hasStepSaveRoute = \Illuminate\Support\Facades\Route::has('superadmin.orders.save.step');
        $hasStepCreateRoute = \Illuminate\Support\Facades\Route::has('superadmin.orders.create.step');
        $selectedAmount = old(
            'amount',
            $isEdit ? $order->total_amount ?? ($order->subtotal ?? ($order->price ?? 0)) : 0,
        );
        $selectedDiscountAmount = old('discount_amount', $isEdit ? $order->discount_amount ?? 0 : 0);
        $selectedApplyGst = old(
            'apply_gst',
            $isEdit
                ? ((string) (($order->apply_gst ?? null) !== null
                    ? ((int) ($order->apply_gst ?? 0) === 1 ? '1' : '0')
                    : (((float) ($order->tax_amount ?? 0) > 0 ||
                        (float) ($order->gst_amount ?? 0) > 0 ||
                        !empty($order->gst_rate))
                        ? '1'
                        : '0')))
                : '0',
        );
        $selectedGstRate = old('gst_rate', $isEdit ? $order->gst_rate ?? 18 : 18);
        $skipProviderStep = !empty($skipProviderStep);
        $initialOrderType = strtolower((string) old('order_type', $isEdit ? $order->resolved_order_type ?? 'b2c' : 'b2c'));
        $showPartnerFieldOnLoad = $initialOrderType === 'b2b';
        $savedDomainName = old('domain_name', $isEdit ? $order->domain_name ?? '' : '');
        $savedTransferDomain = old(
            'transfer_domain',
            $isEdit && !empty($hasTransferDomainColumn) ? $order->transfer_domain ?? '' : '',
        );
        $savedDomainList = $savedDomainName !== ''
            ? array_values(array_filter(array_map(
                'trim',
                preg_split('/\s*[+,]\s*/', (string) $savedDomainName) ?: []
            )))
            : [];
        $savedMicrosoftDomains = (
            $isEdit
            && (int) ($selectedProviderId ?? 0) === 2
            && strtolower((string) $savedTransferDomain) !== 'yes'
        ) ? $savedDomainList : [];
    @endphp
    <div class="row">
        <div class="col-12 mt-2">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">{{ $isEdit ? 'Edit Order' : 'Add Order' }}</h4>
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="{{ route('superadmin.orders.index') }}">Transactions</a></li>
                    <li class="breadcrumb-item active">Orders</li>
                </ol>
            </div>
        </div>

        <div class="col-12">
            <div id="order-form-alert" class="d-none" aria-live="polite"></div>
        </div>

        @if (!$skipProviderStep)
            <div class="col-12" id="order-provider-step-card">
                <div class="card mb-2">
                    <div class="card-header py-2 mt-2">
                        <h5 class="card-title mb-0">1. Select provider &amp; plan scope</h5>
                    </div>
                    <div class="card-body ">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Provider <span class="text-danger">*</span></label>
                                <select class="form-select" id="order_provider_id"
                                    {{ $isEdit && !empty($selectedProviderId) ? 'disabled' : '' }}>
                                    <option value="">— Select provider —</option>
                                    @foreach ($providers as $p)
                                        @php
                                            $providerLabel = strtolower(trim(($p->slug ?? '') . ' ' . ($p->name ?? '')));
                                            $isMicrosoftProvider = strpos($providerLabel, 'microsoft') !== false
                                                || strpos($providerLabel, 'office 365') !== false
                                                || strpos($providerLabel, 'm365') !== false
                                                || (int) $p->id === 2;
                                        @endphp
                                        <option value="{{ $p->id }}"
                                            data-slug="{{ $p->slug ?? '' }}"
                                            data-is-microsoft="{{ $isMicrosoftProvider ? '1' : '0' }}"
                                            {{ (string) old('order_provider_id', $selectedProviderId ?? '') === (string) $p->id ? 'selected' : '' }}>
                                            {{ $p->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 d-none" id="order_variant_wrap">
                                <label class="form-label">Main Variant <span class="text-danger">*</span></label>
                                <select class="form-select" id="order_variant_id"
                                    {{ $isEdit && !empty($selectedProviderId) ? 'disabled' : '' }}>
                                    <option value="">— Select main variant —</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-none" id="order_sub_variant_wrap">
                                <label class="form-label">Sub Variant <span class="text-danger">*</span></label>
                                <select class="form-select" id="order_sub_variant_id"
                                    {{ $isEdit && !empty($selectedProviderId) ? 'disabled' : '' }}>
                                    <option value="">— Select sub variant —</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                @if (!$isEdit || empty($selectedProviderId))
                                    <button type="button" class="btn rounded-pill px-4 py-2" id="order_load_plans_btn"
                                        style="background:#1e58b4;color:#fff;">
                                        Load plans &amp; continue
                                    </button>
                                @endif
                            </div>
                        </div>
                        <p class="text-muted small mb-0 mt-2" id="order_provider_help">
                            Choose a provider. If the provider uses variants, pick a variant, then load plans to unlock the
                            order form.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <div class="col-12 mt-2">
            <form id="order-main-form"
                action="{{ route('superadmin.orders.save.step', ['step' => $currentStep, 'id' => $wizardOrderId]) }}"
                method="POST" enctype="multipart/form-data" class="{{ !$skipProviderStep && !$isEdit ? 'd-none' : '' }}">
                @csrf
                @if (!empty($wizardOrderId))
                    <input type="hidden" name="order_id" value="{{ (int) $wizardOrderId }}">
                @endif
                <input type="hidden" id="order_form_po_locked_flag" value="{{ $poLocked ? '1' : '0' }}"
                    autocomplete="off">
                <div class="row g-3 align-items-start">
                    <div class="col-lg-8">

                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="card-header ps-0">
                                    <h5 class="card-title mb-0">Create Order</h5>
                                </div>
                                <ul class="nav nav-tabs mb-3" id="orderStepTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <a href="{{ $hasStepCreateRoute
                                            ? route('superadmin.orders.create.step', ['step' => 1, 'id' => $wizardOrderId])
                                            : '#' }}"
                                            class="nav-link {{ $currentStep === 1 ? 'active' : '' }}" data-step="1" role="tab"> Order</a>
                                    </li>
                                    <li class="nav-item {{ empty($showPoTab) ? 'd-none' : '' }}" role="presentation" id="order_step_tab_po_item">
                                        <a href="{{ ($wizardOrderId && $hasStepCreateRoute) ? route('superadmin.orders.create.step', ['step' => 2, 'id' => $wizardOrderId]) : '#' }}"
                                            class="nav-link {{ $currentStep === 2 ? 'active' : '' }} {{ $wizardOrderId ? '' : 'disabled' }}"
                                            data-step="2" role="tab">PO Details</a>
                                    </li>
                                    <li class="nav-item {{ empty($showAadhaarTab) ? 'd-none' : '' }}" role="presentation" id="order_step_tab_aadhaar_item">
                                        <a href="{{ ($wizardOrderId && $hasStepCreateRoute) ? route('superadmin.orders.create.step', ['step' => 3, 'id' => $wizardOrderId]) : '#' }}"
                                            class="nav-link {{ $currentStep === 3 ? 'active' : '' }} {{ $wizardOrderId ? '' : 'disabled' }}"
                                            data-step="3" role="tab"> Aadhaar Details</a>
                                    </li>
                                </ul>
                                <div class="row g-2" id="order_form_fields_row">
                                    <div class="col-md-6">
                                        <label class="form-label">Plan <span class="text-danger">*</span></label>
                                        <select name="plan_id" id="order_plan_id" class="form-select" required>
                                            <option value="">Select Plan</option>
                                            @foreach ($plans as $plan)
                                                <option value="{{ $plan->id }}"
                                                    data-partner-price="{{ (float) ($plan->b2b_price ?? ($plan->price ?? 0)) }}"
                                                    data-retail-price="{{ (float) ($plan->b2c_price ?? ($plan->actual_price ?? ($plan->price ?? 0))) }}"
                                                    data-customer-limit="{{ (int) ($plan->customer_limit ?? 0) }}"
                                                    {{ (string) old('plan_id', $isEdit ? $order->plan_id ?? '' : '') === (string) $plan->id ? 'selected' : '' }}>
                                                    {{ $plan->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small id="error-plan_id" class="text-danger d-none server-field-error"></small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Order Type</label>
                                        <select name="order_type" id="order_type_select" class="form-select">
                                            @foreach ($typeOptions as $type)
                                                <option value="{{ $type }}"
                                                    {{ strtolower((string) old('order_type', $isEdit ? $order->resolved_order_type ?? 'b2c' : 'b2c')) === strtolower($type) ? 'selected' : '' }}>
                                                    {{ strtoupper($type) }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small id="error-order_type" class="text-danger d-none server-field-error"></small>
                                    </div>
                                    <div class="col-md-6 {{ $showPartnerFieldOnLoad ? '' : 'd-none' }}" id="order_partner_field_wrap">
                                        <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                                            <label class="form-label mb-0">Partner</label>
                                            <button type="button" class="btn btn-sm btn-outline-primary px-2 py-0 {{ $showPartnerFieldOnLoad ? '' : 'd-none' }}"
                                                id="order_add_partner_btn" title="Add Partner">+</button>
                                        </div>
                                        <select name="partner_id" id="order_partner_id" class="form-select" required>
                                            <option value="">Select Partner</option>
                                            @foreach ($partners as $partner)
                                                <option value="{{ $partner->id }}"
                                                    {{ (string) old('partner_id', $isEdit ? $order->partner_id ?? '' : '') === (string) $partner->id ? 'selected' : '' }}>
                                                    {{ $partner->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small id="error-partner_id" class="text-danger d-none server-field-error"></small>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                                            <label class="form-label mb-0">Customer</label>
                                            <button type="button" class="btn btn-sm btn-outline-primary px-2 py-0 {{ $showPartnerFieldOnLoad ? 'd-none' : '' }}"
                                                id="order_add_customer_btn" title="Add Customer">+</button>
                                        </div>
                                        <select name="user_id" id="order_user_id" class="form-select" required>
                                            <option value="">Select Customer</option>
                                        </select>
                                        <small id="error-user_id" class="text-danger d-none server-field-error"></small>
                                    </div>
                                    @if (!empty($hasTransferDomainColumn))
                                    <div class="col-md-3" id="transfer_domain_wrap">
                                        <label class="form-label">Transfer domain</label>
                                        <select name="transfer_domain" id="transfer_domain" class="form-select">
                                            <option value="">— Select —</option>
                                            <option value="yes" {{ $savedTransferDomain === 'yes' ? 'selected' : '' }}>Yes</option>
                                            <option value="no" {{ $savedTransferDomain === 'no' ? 'selected' : '' }}>No</option>
                                        </select>
                                    </div>
                                    @endif
                                    <div class="col-md-6" id="domain_field_wrap">
                                        <label class="form-label">Domain name</label>
                                        <div id="domain_single_wrap">
                                            <input type="text" name="domain_name" id="domain_name" class="form-control"
                                                list="previous_domains_list"
                                                value="{{ count($savedDomainList) <= 1 ? $savedDomainName : '' }}"
                                                placeholder="example.com" autocomplete="off" required>
                                        </div>
                                        <div id="domain_microsoft_wrap" class="d-none">
                                            <input type="text" id="domain_keyword" class="form-control mb-1"
                                                placeholder="Enter domain keyword (e.g. acme)" autocomplete="off"
                                                value="">
                                            <select id="domain_suggested_select" class="form-select">
                                                <option value="">— Select suggested domain —</option>
                                                @foreach ($savedMicrosoftDomains as $seg)
                                                    <option value="{{ $seg }}" {{ $savedDomainName === $seg ? 'selected' : '' }}>{{ $seg }}</option>
                                                @endforeach
                                            </select>
                                            <input type="hidden" name="domain_name" id="domain_name_ms"
                                                value="{{ $savedDomainName }}" disabled>
                                        </div>
                                        <div id="domain_multi_wrap" class="d-none">
                                            <input type="text" id="domain_keyword_multi" class="form-control mb-1"
                                                placeholder="Enter keyword to load suggestions (e.g. acme)"
                                                autocomplete="off" value="">
                                            <label class="form-label small text-muted mb-1 mt-1">Select domain(s)</label>
                                            <select id="domain_multi_select" class="form-select" multiple size="4">
                                                @foreach ($savedDomainList as $seg)
                                                    <option value="{{ $seg }}" selected>{{ $seg }}</option>
                                                @endforeach
                                            </select>
                                            <input type="hidden" name="domain_name" id="domain_name_multi"
                                                value="{{ $savedDomainName }}" disabled>
                                        </div>
                                        <small id="domain_name_hint" class="text-muted d-none"></small>
                                        <small id="error-domain_name" class="text-danger d-none server-field-error"></small>
                                        <small id="domain_check_error" class="text-danger d-none"></small>
                                        <datalist id="previous_domains_list"></datalist>
                                    </div>
                                    @if (!empty($hasQuantityColumn))
                                        <div class="col-md-3">
                                            <label class="form-label">User quantity</label>
                                            <input type="number" name="quantity" id="user_quantity"
                                                class="form-control" min="1" step="1"
                                                value="{{ old('quantity', $isEdit ? $order->quantity ?? 1 : 1) }}"
                                                placeholder="Number of users / seats">
                                            <small id="error-quantity" class="text-danger d-none server-field-error"></small>
                                            <small id="qty_limit_hint" class="text-muted d-none"></small>
                                            <small id="qty_error" class="text-danger d-none"></small>
                                        </div>
                                    @endif
                                    @php
                                    $wizardOrderStatus = strtolower(trim((string) ($isEdit ? ($order->resolved_status ?? $order->status ?? 'pending') : 'pending')));
                                    if ($wizardOrderStatus === '') {
                                        $wizardOrderStatus = 'pending';
                                    }
                                    $wizardOrderIsActive = in_array($wizardOrderStatus, ['completed', 'paid', 'success', 'active'], true);
                                    $wizardOrderIsCancelled = in_array($wizardOrderStatus, ['rejected', 'cancelled', 'canceled', 'failed'], true);
                                    $wizardOrderIsProcessing = $wizardOrderStatus === 'processing';
                                    $showCancelStatusOption = $wizardOrderIsCancelled
                                        || !empty($orderCanBeCancelled)
                                        || (!$wizardOrderIsActive && !$isEdit);
                                    $wizardStatusBadgeClass = $wizardOrderIsActive
                                        ? 'bg-success'
                                        : ($wizardOrderIsCancelled
                                            ? 'bg-danger'
                                            : ($wizardOrderIsProcessing ? 'bg-primary' : 'bg-warning text-dark'));
                                    $wizardStatusBadgeLabel = $wizardOrderIsActive
                                        ? 'Active'
                                        : ($wizardOrderIsCancelled
                                            ? 'Cancelled'
                                            : ($wizardOrderIsProcessing ? 'Processing' : ucfirst($wizardOrderStatus)));
                                    $wizardStatusSelectValue = $wizardOrderIsActive
                                        ? 'completed'
                                        : ($wizardOrderIsCancelled ? 'rejected' : $wizardOrderStatus);
                                @endphp
                                    @if ($isEdit)
                                        <input type="hidden" name="status_only_update" value="1">
                                        <input type="hidden" name="wizard_status_update" value="1">
                                    @endif
                                    <div class="col-12">
                                        <div class="order-status-panel border rounded-3 p-3 bg-light">
                                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                                <label class="form-label mb-0 fw-semibold" for="wizard_order_status">Order status</label>
                                                <span id="wizard_status_badge" class="badge rounded-pill {{ $wizardStatusBadgeClass }} px-3 py-2">
                                                    {{ $wizardStatusBadgeLabel }}
                                                </span>
                                            </div>

                                            <div class="row g-2 align-items-start">
                                                <div class="col-md-5">
                                                    <select id="wizard_order_status" class="form-select" name="status" @disabled($wizardOrderIsCancelled)>
                                                        @if (!$wizardOrderIsActive && !$wizardOrderIsProcessing && !$wizardOrderIsCancelled)
                                                            <option value="pending" @selected($wizardStatusSelectValue === 'pending')>Pending</option>
                                                        @endif
                                                        @if (($wizardOrderStatus === 'processing' || (!$wizardOrderIsActive && !$wizardOrderIsCancelled)) && !$isEdit)
                                                            <option value="processing" @selected($wizardStatusSelectValue === 'processing')>Processing</option>
                                                        @endif
                                                        @if ($isEdit && $wizardOrderIsProcessing)
                                                            <option value="processing" @selected(true)>Processing</option>
                                                        @endif
                                                        <option value="completed" @selected($wizardStatusSelectValue === 'completed')>Active</option>
                                                        @if ($showCancelStatusOption)
                                                            <option value="rejected" @selected($wizardStatusSelectValue === 'rejected')>Cancelled</option>
                                                        @endif
                                                    </select>
                                                    @if ($wizardOrderIsCancelled)
                                                        <input type="hidden" name="status" value="rejected">
                                                    @endif
                                                </div>
                                                <div class="col-md-7">
                                                    @if ($wizardOrderIsActive && !($orderCanBeCancelled ?? true) && !$wizardOrderIsCancelled)
                                                        <div class="alert alert-danger py-2 px-3 mb-0 small">
                                                            {{ $orderCancellationBlockReason ?? 'This order can no longer be canceled.' }}
                                                        </div>
                                                    @elseif ($wizardOrderIsActive && ($orderCanBeCancelled ?? false) && !empty($orderCancellationDeadline))
                                                        <div class="alert alert-secondary py-2 px-3 mb-0 small">
                                                            Cancel allowed until <strong>{{ $orderCancellationDeadline }}</strong>.
                                                        </div>
                                                    @elseif ($wizardOrderIsCancelled)
                                                        <div class="alert alert-danger py-2 px-3 mb-0 small">
                                                            This order is cancelled and cannot be reactivated from here.
                                                        </div>
                                                    @else
                                                        <div class="text-muted small pt-2">
                                                            Choose <strong>Active</strong> to activate, or <strong>Cancelled</strong> to cancel (when allowed).
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>

                                            <div id="wizard_cancel_reason_wrap" class="mt-3 {{ ($wizardStatusSelectValue === 'rejected' || old('rejection_reason')) ? '' : 'd-none' }}">
                                                <label class="form-label" for="rejection_reason">Cancellation reason <span class="text-danger">*</span></label>
                                                <textarea name="rejection_reason" id="rejection_reason" class="form-control" rows="2"
                                                    placeholder="Enter reason for cancellation">{{ old('rejection_reason', '') }}</textarea>
                                                <small id="error-rejection_reason" class="text-danger d-none server-field-error"></small>
                                            </div>
                                        </div>
                                    </div>
                                    @if (!empty($hasPromoCodeColumn))
                                    <div class="col-md-3">
                                        <label class="form-label">Promocode</label>
                                        <input type="text" name="promocode" id="promocode" class="form-control"
                                            value="{{ old('promocode', $isEdit ? $order->promo_code ?? '' : '') }}"
                                            placeholder="Enter promocode">
                                        <small id="error-promocode" class="text-danger d-none server-field-error"></small>
                                        </div>
                                    @endif

                                    @if (!empty($hasDiscountAmountColumn))
                                        <div class="col-md-3">
                                            <label class="form-label">Discount </label>
                                            <input type="number" step="0.01" min="0" name="discount_amount"
                                                id="discount_amount" class="form-control"
                                                value="{{ $selectedDiscountAmount }}"
                                                placeholder="Enter discount amount">
                                            {{-- <small class="text-muted">Enter how much discount to apply.</small> --}}
                                        </div>
                                    @endif
                                   
                                    @if (!empty($hasPoTrackingColumns))
                                        <div class="col-12" id="po_step_col">
                                            <div class="border rounded p-3 bg-light d-none" id="po_journey_section">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    {{-- <h6 class="mb-0">PO Details (First Order)</h6> --}}
                                                    {{-- <span class="badge bg-info text-dark"
                                                        id="po_first_order_badge">Checking...</span> --}}
                                                </div>
                                                {{-- <p class="small text-muted mb-2">Record purpose only. No admin approval
                                                    required.</p> --}}
                                                <div class="row g-2">
                                                    <input type="hidden" name="po_mode" id="po_mode" value="{{ $poModeValue !== '' ? $poModeValue : 'generated' }}">
                                                    <div class="col-12 mb-2">
                                                        <label class="form-label fw-semibold">PO Source</label>
                                                        <div class="d-flex flex-wrap gap-3">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="radio" name="po_mode_choice" id="po_mode_generated"
                                                                    value="generated" {{ ($poModeValue === '' || $poModeValue === 'generated') ? 'checked' : '' }} {{ $poDisabled }}>
                                                                <label class="form-check-label" for="po_mode_generated">Generate PO</label>
                                                            </div>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="radio" name="po_mode_choice" id="po_mode_uploaded"
                                                                    value="uploaded" {{ $poModeValue === 'uploaded' ? 'checked' : '' }} {{ $poDisabled }}>
                                                                <label class="form-check-label" for="po_mode_uploaded">Upload PO (CRM / SAP)</label>
                                                            </div>
                                                        </div>
                                                        <small class="text-muted">Companies using their own CRM/SAP PO can upload a PDF instead of our format and Aadhaar flow.</small>
                                                    </div>
                                                    <div class="col-12 {{ ($poModeValue === 'uploaded') ? '' : 'd-none' }}" id="po_upload_fields">
                                                        <div class="row g-2">
                                                            <div class="col-md-4">
                                                                <label class="form-label">External PO Number</label>
                                                                <input type="text" name="po_number" id="po_number" class="form-control form-control-sm"
                                                                    value="{{ old('po_number', $isEdit ? $order->po_number ?? '' : '') }}" {{ $poReadOnly }}
                                                                    placeholder="From CRM / SAP">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label">PO Date</label>
                                                                <input type="date" name="po_date" id="po_date" class="form-control form-control-sm"
                                                                    value="{{ old('po_date', $isEdit && !empty($order->po_date) ? \Illuminate\Support\Carbon::parse($order->po_date)->format('Y-m-d') : '') }}" {{ $poReadOnly }}>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label">Upload PO PDF</label>
                                                                <input type="file" name="po_document" id="po_document" class="form-control form-control-sm"
                                                                    accept="application/pdf,.pdf" {{ $poDisabled }}>
                                                                @if ($isEdit && !empty($order->po_document_url))
                                                                    <small class="d-block mt-1">
                                                                        Current:
                                                                        <a href="{{ $order->po_document_url }}" target="_blank" rel="noopener">View uploaded PO</a>
                                                                    </small>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <input type="hidden" name="po_amount" id="po_amount"
                                                        value="{{ old('po_amount', $isEdit ? $order->po_amount ?? '' : '') }}">
                                                    <div class="col-12" id="po_generated_fields">
                                                        <div class="row g-2">
                                                            <div class="col-md-6">
                                                                <label class="form-label">Name</label>
                                                                <input type="text" name="po_manual_name"
                                                                    id="po_manual_name"
                                                                    class="form-control form-control-sm"
                                                                    value="{{ old('po_manual_name', $isEdit ? $order->po_manual_name ?? '' : '') }}"
                                                                    {{ $poReadOnly }}>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Email</label>
                                                                <input type="email" name="po_manual_email"
                                                                    id="po_manual_email"
                                                                    class="form-control form-control-sm"
                                                                    value="{{ old('po_manual_email', $isEdit ? $order->po_manual_email ?? '' : '') }}"
                                                                    {{ $poReadOnly }}>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Mobile No.</label>
                                                                <input type="text" name="po_manual_mobile"
                                                                    id="po_manual_mobile"
                                                                    class="form-control form-control-sm"
                                                                    value="{{ old('po_manual_mobile', $isEdit ? $order->po_manual_mobile ?? '' : '') }}"
                                                                    {{ $poReadOnly }}>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">GSTIN</label>
                                                                <div class="input-group input-group-sm">
                                                                    <input type="text" name="po_manual_gstin"
                                                                        id="po_manual_gstin" class="form-control"
                                                                        value="{{ old('po_manual_gstin', $isEdit ? $order->po_manual_gstin ?? '' : '') }}"
                                                                        {{ $poReadOnly }}>
                                                                    @if (!$poLocked)
                                                                        <button type="button"
                                                                            class="btn btn-outline-secondary"
                                                                            id="po_gstin_search_btn">Search</button>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                            <div class="col-12 {{ $poLocked ? '' : 'd-none' }}" id="po_gstin_verified_fields">
                                                                <div class="row g-2">
                                                                    <div class="col-md-6">
                                                                        <label class="form-label">Company Name</label>
                                                                        <input type="text" id="po_gst_company_name"
                                                                            class="form-control form-control-sm"
                                                                            value="{{ old('po_gst_company_name', $isEdit ? $order->po_manual_company_name ?? '' : '') }}"
                                                                            readonly>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <label class="form-label">Company Address</label>
                                                                        <input type="text" id="po_gst_company_address"
                                                                            class="form-control form-control-sm"
                                                                            value="{{ old('po_gst_company_address', $isEdit ? $order->po_manual_company_address ?? '' : '') }}"
                                                                            readonly>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <label class="form-label">City</label>
                                                                        <input type="text" id="po_gst_city"
                                                                            class="form-control form-control-sm"
                                                                            value="{{ old('po_gst_city', $isEdit ? $order->po_manual_city ?? '' : '') }}"
                                                                            readonly>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <label class="form-label">State</label>
                                                                        <input type="text" id="po_gst_state"
                                                                            class="form-control form-control-sm"
                                                                            value="{{ old('po_gst_state', $isEdit ? $order->po_manual_state ?? '' : '') }}"
                                                                            readonly>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <label class="form-label">Country</label>
                                                                        <input type="text" id="po_gst_country"
                                                                            class="form-control form-control-sm"
                                                                            value="{{ old('po_gst_country', $isEdit ? $order->po_manual_country ?? '' : '') }}"
                                                                            readonly>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <label class="form-label">Pincode</label>
                                                                        <input type="text" id="po_gst_pincode"
                                                                            class="form-control form-control-sm"
                                                                            value="{{ old('po_gst_pincode', $isEdit ? $order->po_manual_pincode ?? '' : '') }}"
                                                                            readonly>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <input type="hidden" name="po_manual_company_name" id="po_manual_company_name"
                                                                value="{{ old('po_manual_company_name', $isEdit ? $order->po_manual_company_name ?? '' : '') }}">
                                                            <input type="hidden" name="po_manual_company_address" id="po_manual_company_address"
                                                                value="{{ old('po_manual_company_address', $isEdit ? $order->po_manual_company_address ?? '' : '') }}">
                                                            <input type="hidden" name="po_manual_city" id="po_manual_city"
                                                                value="{{ old('po_manual_city', $isEdit ? $order->po_manual_city ?? '' : '') }}">
                                                            <input type="hidden" name="po_manual_state" id="po_manual_state"
                                                                value="{{ old('po_manual_state', $isEdit ? $order->po_manual_state ?? '' : '') }}">
                                                            <input type="hidden" name="po_manual_country" id="po_manual_country"
                                                                value="{{ old('po_manual_country', $isEdit ? $order->po_manual_country ?? '' : '') }}">
                                                            <input type="hidden" name="po_manual_pincode" id="po_manual_pincode"
                                                                value="{{ old('po_manual_pincode', $isEdit ? $order->po_manual_pincode ?? '' : '') }}">
                                                        </div>
                                                    </div>
                                                    <input type="hidden" name="po_journey_status" id="po_journey_status"
                                                        value="{{ old('po_journey_status', $isEdit ? $order->po_journey_status ?? 'po_pending' : 'po_pending') }}">
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                   
                                    <div class="col-md-3">
                                        <label class="form-label">Apply GST?</label>
                                        <select id="apply_gst" name="apply_gst" class="form-select">
                                            <option value="0"
                                                {{ (string) $selectedApplyGst === '0' ? 'selected' : '' }}>No</option>
                                            <option value="1"
                                                {{ (string) $selectedApplyGst === '1' ? 'selected' : '' }}>Yes</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 d-none" id="gst_rate_wrap">
                                        <label class="form-label">GST Rate (%)</label>
                                        <input type="number" step="0.01" min="0" max="100"
                                            id="gst_rate" name="gst_rate" class="form-control"
                                            value="{{ $selectedGstRate }}" placeholder="Enter GST rate">
                                    </div>
                                    <div class="col-md-3 d-none" id="gst_amount_wrap">
                                        <label class="form-label">GST Amount</label>
                                        <input type="number" step="0.01" min="0" id="gst_amount"
                                            name="gst_amount" class="form-control" value="0.00" readonly>
                                        <input type="hidden" id="tax_amount" name="tax_amount" value="0.00">
                                    </div>
                                    <div class="col-md-4 d-none mb-2" id="payment_mode_wrap">
                                        <label class="form-label">Payment Mode</label>
                                        <select name="payment_mode" id="payment_mode" class="form-select">
                                            <option value="">Select payment mode</option>
                                            <option value="cash_bank"
                                                {{ old('payment_mode', $isEdit ? $order->payment_mode ?? '' : '') === 'cash_bank' ? 'selected' : '' }}>
                                                Cash in Bank</option>
                                            <option value="cash_hand"
                                                {{ old('payment_mode', $isEdit ? $order->payment_mode ?? '' : '') === 'cash_hand' ? 'selected' : '' }}>
                                                Cash in Hand</option>
                                            <option value="upi"
                                                {{ old('payment_mode', $isEdit ? $order->payment_mode ?? '' : '') === 'upi' ? 'selected' : '' }}>
                                                UPI</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 d-none mb-2" id="bank_details_container">
                                        <label class="form-label">Bank Name</label>
                                        <input type="text" name="bank_name" id="bank_name" class="form-control"
                                            placeholder="Bank Name"
                                            value="{{ old('bank_name', $isEdit ? $order->bank_name ?? '' : '') }}">
                                    </div>
                                    <div class="col-md-4 d-none mb-2" id="cheque_ref_no_container">
                                        <label class="form-label mt-2">Cheque/Ref No.</label>
                                        <input type="text" name="cheque_ref_no" id="cheque_ref_no"
                                            class="form-control" placeholder="Cheque/Ref No."
                                            value="{{ old('cheque_ref_no', $isEdit ? $order->cheque_ref_no ?? '' : '') }}">
                                    </div>
                                    <div class="col-md-4 d-none mb-2" id="upi_details_container">
                                        <label class="form-label">Transaction ID</label>
                                        <input type="text" name="upi_transaction_id" id="upi_transaction_id"
                                            class="form-control" placeholder="UPI Transaction ID"
                                            value="{{ old('upi_transaction_id', $isEdit ? $order->upi_transaction_id ?? '' : '') }}">
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label class="form-label">Amount</label>
                                        <input type="number" step="0.01" min="0" name="amount"
                                            id="amount" class="form-control" value="{{ $selectedAmount }}" required>
                                        <small id="error-amount" class="text-danger d-none server-field-error"></small>
                                        <small id="credit_error" class="text-danger d-none"></small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Order Note</label>
                                        <textarea name="order_note" id="order_note" class="form-control" rows="2" placeholder="Order note">{{ old('order_note', $isEdit ? $order->order_note ?? '' : '') }}</textarea>
                                        <small id="payment_error" class="text-muted d-none"></small>
                                    </div>
                                    <div class="col-12 " id="aadhaar_step_col" style="display: none;">
                                        <div class="border rounded p-3 bg-light">
                                            <h6 class="mb-2">Aadhaar Verification</h6>
                                            @if ($isEdit && $poLocked && !empty($order->aadhar_verified))
                                                <div class="row g-2">
                                                    <div class="col-md-4">
                                                        <label class="form-label">Verification Status</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            value="{{ !empty($order->aadhar_verified) ? 'Verified' : 'Pending' }}"
                                                            readonly>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">Journey Status</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            value="{{ !empty($order->po_journey_status) ? ucwords(str_replace('_', ' ', (string) $order->po_journey_status)) : '-' }}"
                                                            readonly>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">Aadhaar No.</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            value="{{ (string) ($order->aadhaar_masked ?? '-') }}" readonly>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Reference No.</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            value="{{ (string) ($order->aadhaar_ref_no ?? '-') }}" readonly>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Verified At</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            value="{{ !empty($order->aadhar_verified_at) ? \Carbon\Carbon::parse($order->aadhar_verified_at)->format('d-m-Y H:i') : '-' }}"
                                                            readonly>
                                                    </div>
                                                </div>
                                                <input type="hidden" id="aadhar_verified_local"
                                                    value="{{ !empty($order->aadhar_verified) ? '1' : '0' }}">
                                            @else
                                                <div class="row g-2" id="aadhaar_number_block">
                                                    <div class="col-md-6">
                                                        <label class="form-label">Aadhaar Number</label>
                                                        <input type="text" id="aadhaar_number_local" class="form-control form-control-sm" maxlength="12" placeholder="12 digit Aadhaar">
                                                    </div>
                                                    <div class="col-md-3 d-flex align-items-end">
                                                        <button type="button" class="btn btn-sm btn-primary w-100" id="aadhaar_send_otp_btn">Send OTP</button>
                                                    </div>
                                                </div>
                                                <div class="row g-2 d-none" id="aadhaar_otp_block">
                                                    <div class="col-md-4">
                                                        <label class="form-label">OTP</label>
                                                        <input type="text" id="aadhaar_otp_local" class="form-control form-control-sm" maxlength="6" placeholder="6 digit OTP">
                                                    </div>
                                                    <div class="col-md-3 d-flex align-items-end">
                                                        <button type="button" class="btn btn-sm btn-primary w-100" id="aadhaar_verify_local_btn">Verify Aadhaar</button>
                                                    </div>
                                                    <div class="col-md-3 d-flex align-items-end">
                                                        <button type="button" class="btn btn-sm btn-primary w-100" id="aadhaar_back_btn">Change Aadhaar</button>
                                                    </div>
                                                    <div class="col-md-4 d-flex align-items-end">
                                                        <a href="javascript:void(0);" class="d-none mt-2" id="aadhaar_resend_otp_btn">Resend OTP</a>
                                                    </div>
                                                </div>
                                                <div class="col-12 mt-2 px-0">
                                                    <input type="hidden" id="aadhar_verified_local" value="0">
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm sticky-top" style="top: 84px;">
                            <div class="card-header py-2" style="background:#0058b0;color:#fff;">
                                <h6 class="mb-0">Order Summary</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between small mb-2">
                                    <span>Subtotal:</span>
                                    <strong id="summary_subtotal">₹0.00</strong>
                                </div>
                                <div class="d-flex justify-content-between small mb-2">
                                    <span>Discount:</span>
                                    <strong class="text-danger" id="summary_discount">- ₹0.00</strong>
                                </div>
                                <div class="d-flex justify-content-between small mb-2">
                                    <span>Taxable amount:</span>
                                    <strong id="summary_taxable">₹0.00</strong>
                                </div>
                                <div class="d-flex justify-content-between small mb-2">
                                    <span>GST <span id="summary_gst_rate_label">0</span>%:</span>
                                    <strong id="summary_gst_value">₹0.00</strong>
                                </div>
                                <div class="d-flex justify-content-between small mb-2 d-none" id="summary_cgst_wrap">
                                    <span>CGST:</span>
                                    <strong id="summary_cgst">₹0.00</strong>
                                </div>
                                <div class="d-flex justify-content-between small mb-2 d-none" id="summary_sgst_wrap">
                                    <span>SGST:</span>
                                    <strong id="summary_sgst">₹0.00</strong>
                                </div>
                                <div class="d-flex justify-content-between small mb-2 d-none" id="summary_igst_wrap">
                                    <span>IGST:</span>
                                    <strong id="summary_igst">₹0.00</strong>
                                </div>
                                <div class="d-flex justify-content-between small mb-2 d-none" id="summary_credit_wrap">
                                    <span>Credit Balance:</span>
                                    <strong id="summary_credit">₹0.00</strong>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="fw-semibold">Grand total:</span>
                                    <span class="fw-bold text-primary" id="summary_total">₹0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger mt-3 mb-0">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <input type="hidden" id="partner_credit" value="0">
                <input type="hidden" id="partner_wallet_balance" value="0">
                <input type="hidden" id="customer_credit" value="0">
                <input type="hidden" name="post_submit_action" id="post_submit_action" value="details">
                <div class="mt-3 d-flex gap-2 align-items-center flex-wrap">
                    <button type="submit" id="submit-btn" class="btn rounded-pill px-4 py-2 {{ ($orderCompleted && $currentStep >= 2) || $currentStep === 3 ? 'd-none' : '' }}"
                        style="background:#1e58b4;color:#fff;">
                        {{ $isEdit ? 'Update Status' : ($currentStep === 1 ? 'Place Order' : ($currentStep === 2 ? 'Save PO Details' : 'Place Order')) }}
                    </button>
                    <div id="order-form-spinner" class="spinner-border spinner-border-sm text-primary d-none" role="status" aria-hidden="true"></div>
                    {{-- <a href="{{ route('superadmin.orders.index') }}" class="btn rounded-pill px-4 py-2"
                        style="background:#f3412c;color:#fff;">
                        Cancel
                    </a> --}}
                </div>
            </form>
        </div>
    </div>
    </div>
@endsection

@push('styles')
    <style>
        #order-form-alert.order-form-alert-visible {
            position: fixed;
            top: 72px;
            left: 50%;
            transform: translateX(-50%);
            width: min(680px, 94vw);
            z-index: 20000;
            pointer-events: auto;
        }
        #order-form-alert .order-form-alert-inner {
            font-size: 1rem;
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.18);
            border-width: 2px;
        }
        .order-status-panel {
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            border-color: #e2e8f0 !important;
        }
        .order-status-panel #wizard_status_badge {
            font-size: 0.8rem;
            letter-spacing: 0.02em;
        }
        .order-status-panel .form-select {
            min-height: 42px;
            font-weight: 600;
        }
        .order-status-panel .alert {
            border: 0;
        }
    </style>
@endpush

@push('scripts')
    <script>
        $(function () {
            function showOrderFormAlert(message, type) {
                type = String(type || 'error').toLowerCase();
                var bsMap = { error: 'danger', success: 'success', warning: 'warning', info: 'info' };
                var bsType = bsMap[type] || 'danger';
                var text = String(message || '').trim();
                if (!text) {
                    return;
                }
                var safeMsg = $('<div/>').text(text).html();
                var $box = $('#order-form-alert');
                if (!$box.length) {
                    window.alert(text);
                    return;
                }

                clearTimeout(window._orderFormAlertTimer);
                $box
                    .removeClass('d-none')
                    .addClass('order-form-alert-visible')
                    .html(
                        '<div class="alert alert-' + bsType + ' alert-dismissible show order-form-alert-inner mb-0" role="alert">' +
                        '<div class="fw-semibold mb-1">' +
                        (bsType === 'danger' ? 'Action blocked' : (bsType === 'success' ? 'Success' : 'Notice')) +
                        '</div>' +
                        '<div>' + safeMsg + '</div>' +
                        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
                        '</div>'
                    );

                window.scrollTo({ top: 0, behavior: 'smooth' });

                if (bsType !== 'success') {
                    return;
                }

                window._orderFormAlertTimer = setTimeout(function () {
                    $box.addClass('d-none').removeClass('order-form-alert-visible').empty();
                }, 10000);
            }

            function postOrderFormAlertFromXhr(xhr, fallback) {
                var j = xhr && xhr.responseJSON ? xhr.responseJSON : {};
                var msg = j.message || fallback || 'Request failed';
                if (xhr && xhr.status) {
                    msg = String(msg);
                }
                showOrderFormAlert(msg, 'error');
            }

            // Basic state from Blade
            var vbp = @json($variantsByProvider ?? []),
                mvbp = @json($mainVariantsByProvider ?? []),
                svbm = @json($subVariantsByMain ?? []),
                hvbp = @json($hasVariantByProvider ?? []),
                isEdit = @json($isEdit),
                selPid = @json($selectedProviderId ?? null),
                selVid = @json((int) ($selectedVariantId ?? 0)),
                selMainVid = @json((int) ($selectedMainVariantId ?? 0)),
                selSubVid = @json((int) ($selectedSubVariantId ?? 0)),
                skipProvider = @json($skipProviderStep),
                plansUrl = @json(route('superadmin.orders.plans_by_provider')),
                oldPlanId = @json((string) old('plan_id', $isEdit ? (string) ($order->plan_id ?? '') : '')),
                partnerList = @json($partners ?? []),
                custList = @json($orderFormCustomers ?? []),
                partnerCol = @json($customersPartnerColumn ?? false),
                currentStep = @json((int) ($currentStep ?? 1)),
                wizardOrderId = @json($wizardOrderId ?? null),
                showPoTab = @json(!empty($showPoTab)),
                showAadhaarTab = @json(!empty($showAadhaarTab)),
                gstSearchUrl = @json(route('search-gstin')),
                applyPromoApiUrl = @json(url('/api/apply-promo')),
                aadhaarInitApiUrl = @json(route('superadmin.orders.aadhaar_init')),
                aadhaarOtpVerifyApiUrl = @json(route('superadmin.orders.aadhaar_verify')),
                custDomainsUrl = @json(route('superadmin.orders.customer_domains', ['id' => '__ID__'])),
                custSelectUrl = @json(route('superadmin.orders.customers_select')),
                partnerCustomersUrl = @json(route('superadmin.orders.partner_customers', ['partner_id' => '__PID__'])),
                custMetaUrl = @json(route('superadmin.orders.customer_meta', ['id' => '__ID__'])),
                {{-- Use url() (request-root based) so subdir deployments work, AND keep zero commas inside @json — Blade's @json splits args with explode(',', $expr). --}}
                domainCheckUrl = @json(url('/superadmin/orders-check-domain')),
                stepSaveUrlBase = @json(url('/superadmin/orders-add')),
                b2bCustUrl = @json(route('superadmin.b2b_partners.customers.add_open')),
                partnerAddUrl = @json(route('superadmin.b2b_partners.add')),
                b2cCustUrl = @json(route('superadmin.b2c_customers.add')),
                initialUserId = @json(old('user_id', $isEdit ? $order->user_id ?? null : null)),
                orderSummaryPricing = @json($orderSummaryPricing ?? null),
                savedMicrosoftDomains = @json($savedMicrosoftDomains ?? []),
                savedDomainList = @json($savedDomainList ?? []);

            var orderTypeIsB2b = () => ($('#order_type_select').val() + '').trim().toLowerCase() === 'b2b';
            function pId() { var v = $('#order_partner_id').val(); return v ? parseInt(v,10) : 0 }
            function providerId() { var v = $('#order_provider_id').val()||$('#order_provider_id_inline').val(); return v?parseInt(v,10):0 }
            var domainSegmentRe = /^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i;
            function isMicrosoftProvider() {
                return providerId() === 2;
            }
            function isProvider3() {
                return providerId() === 3;
            }
            function isTransferDomainYes() {
                return ($('#transfer_domain').val() || '').trim().toLowerCase() === 'yes';
            }
            function allowsMultiDomain() {
                return isTransferDomainYes() && isMicrosoftProvider();
            }
            function skipDomainVerification() {
                return isProvider3();
            }
            function needsRegistrarDomainCheck() {
                if (skipDomainVerification()) return false;
                // Microsoft Transfer=No uses *.onmicrosoft.com suggestions — no WHOIS needed.
                if (isMicrosoftProvider() && !allowsMultiDomain()) return false;
                return true;
            }
            function parseDomainSegments(raw, multi) {
                if (!multi) return raw ? [raw] : [];
                return raw.split(/\s*[+,]\s*/).map(function (s) { return s.trim(); }).filter(Boolean);
            }
            function validateDomainSegments(raw, multi) {
                var segments = parseDomainSegments(raw, multi);
                if (!segments.length) return false;
                return segments.every(function (segment) { return domainSegmentRe.test(segment); });
            }
            function syncMultiDomainHidden() {
                var vals = $('#domain_multi_select').val() || [];
                if (!Array.isArray(vals)) {
                    vals = vals ? [vals] : [];
                }
                $('#domain_name_multi').val(vals.join(', '));
            }
            function populateMultiDomainSelect(domains, selected) {
                var $sel = $('#domain_multi_select');
                var keep = selected || $sel.val() || [];
                if (!Array.isArray(keep)) {
                    keep = keep ? [keep] : [];
                }
                $sel.empty();
                (domains || []).forEach(function (d) {
                    var $opt = $('<option>').val(d).text(d);
                    if (keep.indexOf(d) >= 0) {
                        $opt.prop('selected', true);
                    }
                    $sel.append($opt);
                });
                syncMultiDomainHidden();
            }
            function syncDomainFieldMode() {
                var multi = allowsMultiDomain();
                var msSuggest = isMicrosoftProvider() && !multi;
                $('#domain_single_wrap').toggleClass('d-none', multi || msSuggest);
                $('#domain_microsoft_wrap').toggleClass('d-none', !msSuggest);
                $('#domain_multi_wrap').toggleClass('d-none', !multi);
                var $text = $('#domain_name');
                var $hiddenMs = $('#domain_name_ms');
                var $hiddenMulti = $('#domain_name_multi');
                $text.prop('disabled', true).removeAttr('name');
                $hiddenMs.prop('disabled', true).removeAttr('name');
                $hiddenMulti.prop('disabled', true).removeAttr('name');
                if (multi) {
                    $hiddenMulti.prop('disabled', false).attr('name', 'domain_name');
                    syncMultiDomainHidden();
                } else if (msSuggest) {
                    $hiddenMs.prop('disabled', false).attr('name', 'domain_name');
                    var sel = ($('#domain_suggested_select').val() || '').trim();
                    if (sel) {
                        $hiddenMs.val(sel);
                    }
                } else {
                    $text.prop('disabled', false).attr('name', 'domain_name');
                    if (isProvider3()) {
                        $text.attr('placeholder', 'Enter domain (no verification)');
                    } else {
                        $text.attr('placeholder', 'example.com');
                    }
                }
                updateDomainHint();
            }
            function populateMicrosoftDomainSelect(domains, selected) {
                var $sel = $('#domain_suggested_select');
                var keep = selected || $sel.val() || $('#domain_name_ms').val() || '';
                $sel.find('option:not(:first)').remove();
                (domains || []).forEach(function (d) {
                    $sel.append($('<option>').val(d).text(d));
                });
                if (keep && $sel.find('option[value="' + keep.replace(/"/g, '\\"') + '"]').length) {
                    $sel.val(keep);
                }
                $('#domain_name_ms').val($sel.val() || '');
            }
            function updateDomainHint() {
                var $h = $('#domain_name_hint');
                if (!$h.length) return;
                if (skipDomainVerification()) {
                    $h.removeClass('d-none text-success text-danger').addClass('text-muted')
                        .text('Domain verification is not required for this provider.');
                } else if (allowsMultiDomain()) {
                    $h.removeClass('d-none text-success text-danger').addClass('text-muted')
                        .text('Transfer yes: select registered custom domain(s). Unregistered domains cannot be used.');
                } else if (isMicrosoftProvider()) {
                    $h.removeClass('d-none text-success text-danger').addClass('text-muted')
                        .text('Choose one of 3 suggested .onmicrosoft.com domains.');
                } else if (isTransferDomainYes()) {
                    $h.removeClass('d-none text-success text-danger').addClass('text-muted')
                        .text('Transfer: enter one registered domain — it will be verified.');
                } else {
                    $h.removeClass('d-none text-success text-danger').addClass('text-muted')
                        .text('Enter a registered domain. Unregistered domains cannot be used for a new plan.');
                }
            }
            function currentDomainRaw() {
                if (allowsMultiDomain()) {
                    return ($('#domain_name_multi').val() || '').trim().toLowerCase();
                }
                if (isMicrosoftProvider()) {
                    return ($('#domain_suggested_select').val() || $('#domain_name_ms').val() || '').trim().toLowerCase();
                }
                return ($('#domain_name').val() || '').trim().toLowerCase();
            }
            function resolveGstStateCode() {
                var gstin = ($('#po_manual_gstin').val() || '').trim().toUpperCase();
                if (gstin.length >= 2 && /^\d{2}/.test(gstin)) {
                    return gstin.substring(0, 2);
                }
                return '';
            }
            function filteredCustomers() {
                if (!custList.length) return [];
                if (orderTypeIsB2b()) {
                    var res = custList.filter(c => (c.source || '') === 'b2b');
                    if (!partnerCol) return res;
                    var pid = pId(); if (!pid) return [];
                    return res.filter(c => c.partner_id && parseInt(c.partner_id,10) === pid);
                }
                return custList.filter(c => (c.source || '') === 'b2c');
            }
            function syncOrderTypeUi() {
                var isB2b = orderTypeIsB2b();
                $('#order_partner_field_wrap').toggleClass('d-none', !isB2b);
                $('#order_partner_id').prop('required', isB2b).prop('disabled', !isB2b);
                $('#order_add_partner_btn').toggleClass('d-none', !isB2b);
                $('#order_add_customer_btn').toggleClass('d-none', isB2b);
                if (!isB2b) {
                    $('#order_partner_id').val('').trigger('change.select2');
                }

                $('#order_step_tab_po_item').toggleClass('d-none', !isB2b);
                $('#order_step_tab_aadhaar_item').toggleClass('d-none', !isB2b);
                $('#po_step_col,#aadhaar_step_col').toggle(isB2b && currentStep > 1);
                if (!isB2b) {
                    currentStep = 1;
                    setStep(1);
                }
            }
            function renderCustomersSel() {
                var $sel = $('#order_user_id');
                var prev = $sel.val(), arr = filteredCustomers();
                $sel.find('option:not(:first)').remove();
                if (orderTypeIsB2b() && partnerCol && !pId()) {
                    $sel.prop('disabled', 1).find('option:first').text('Select Partner First').end().val('');
                    $sel.trigger('change.select2'); syncBal(); return;
                }
                if (orderTypeIsB2b() && pId() && !arr.length) {
                    $sel.prop('disabled', 1).find('option:first').text('No customers for this partner').end().val('');
                    $sel.trigger('change.select2'); syncBal(); return;
                }
                $sel.prop('disabled', 0).find('option:first').text('Select Customer');
                arr.forEach(function (c) {
                    $sel.append($('<option>').val(c.id).text(c.name));
                });
                var iv = String(initialUserId || '');
                if (prev && $sel.find('[value="' + prev + '"]').length) {
                    $sel.val(prev);
                } else if (!$sel.data('_bootstrapped') && iv && $sel.find('[value="' + iv + '"]').length) {
                    $sel.val(iv);
                } else {
                    $sel.val('');
                }
                $sel.data('_bootstrapped', 1).trigger('change.select2');
                syncBal();
            }
            function mergeCustomers(rows) {
                if (!Array.isArray(rows)) {
                    return [];
                }
                var normalized = [];
                rows.forEach(function (row) {
                    if (!row) {
                        return;
                    }
                    var id = row.id != null ? row.id : (row.user_id != null ? row.user_id : row.value);
                    if (id == null || id === '') {
                        return;
                    }
                    var name = row.name || row.customer_name || row.full_name || row.text || row.email || ('Customer #' + id);
                    var item = {
                        id: id,
                        name: name,
                        source: (row.source || (orderTypeIsB2b() ? 'b2b' : 'b2c')),
                        partner_id: (row.partner_id != null ? row.partner_id : pId()),
                        credit_balance: (row.credit_balance != null ? row.credit_balance : (row.wallet_balance || 0))
                    };
                    normalized.push(item);
                    var idx = custList.findIndex(function (c) {
                        return String(c.id) === String(id);
                    });
                    if (idx >= 0) {
                        custList[idx] = $.extend({}, custList[idx], item);
                    } else {
                        custList.push(item);
                    }
                });
                return normalized;
            }
            function loadPartnerCustomersDynamic(keepId) {
                var $sel = $('#order_user_id');
                if (!orderTypeIsB2b()) {
                    renderCustomersSel();
                    return;
                }
                var pid = pId();
                if (!pid) {
                    renderCustomersSel();
                    return;
                }
                $sel.prop('disabled', 1).find('option:not(:first)').remove();
                $sel.find('option:first').text('Loading customers...');
                $sel.trigger('change.select2');

                var done = function () {
                    renderCustomersSel();
                    var pick = keepId || initialUserId || '';
                    if (pick && $sel.find('[value="' + pick + '"]').length) {
                        $sel.val(String(pick)).trigger('change.select2');
                    }
                    syncBal();
                    validateOrderForm();
                };

                if (custSelectUrl) {
                    $.ajax({
                        url: custSelectUrl,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            _token: $('meta[name="csrf-token"]').attr('content'),
                            partner_id: pid,
                            order_type: 'b2b',
                            provider_id: providerId() || ''
                        }
                    }).done(function (r) {
                        var rows = [];
                        if (Array.isArray(r)) {
                            rows = r;
                        } else if (r && Array.isArray(r.customers)) {
                            rows = r.customers;
                        } else if (r && Array.isArray(r.data)) {
                            rows = r.data;
                        } else if (r && Array.isArray(r.rows)) {
                            rows = r.rows;
                        } else if (r && Array.isArray(r.results)) {
                            rows = r.results;
                        }
                        custList = custList.filter(function (c) {
                            return !((c.source || '') === 'b2b' && parseInt(c.partner_id, 10) === pid);
                        });
                        mergeCustomers(rows);
                    }).always(done);
                    return;
                }

                $.getJSON(partnerCustomersUrl.replace('__PID__', pid), function (r) {
                    var rows = (r && r.customers) ? r.customers : [];
                    custList = custList.filter(function (c) {
                        return !((c.source || '') === 'b2b' && parseInt(c.partner_id, 10) === pid);
                    });
                    mergeCustomers(rows);
                }).always(done);
            }
            function syncBal() {
                var pid=pId(), uid=parseInt($('#order_user_id').val()||'0',10);
                var partner = partnerList.find(p=>parseInt(p.id,10)===pid)||{};
                var cust = custList.find(c=>parseInt(c.id,10)===uid)||{};
                var pb = parseFloat(partner.wallet_balance)||0, cb = parseFloat(cust.credit_balance)||0;
                $('#partner_credit,#partner_wallet_balance').val(pb.toFixed(2));
                $('#customer_credit').val(cb.toFixed(2));
                var ab = orderTypeIsB2b() ? pb : cb;
                $('#credit_balance_display').text('₹'+(ab.toFixed(2)));
            }
            function loadCustDomains(uid) {
                var $l = $('#previous_domains_list'); $l.empty();
                if (!uid) return;
                $.getJSON(custDomainsUrl.replace('__ID__',uid), d=>d.domains&&d.domains.forEach(v=>$l.append($('<option>').val(v).text(v))))
            }
            function setStep(s) {
                if (!orderTypeIsB2b()) s = 1;
                updateFormAction(s, wizardOrderId);
                $('#orderStepTabs .nav-link').removeClass('active').filter(`[data-step="${s}"]`).addClass('active');
                var $cols = $('#order_form_fields_row').children('div');
                $cols.hide();
                if (s==1) {
                    $cols.show();
                    $('#po_step_col,#aadhaar_step_col').hide();
                    $('#po_journey_section').addClass('d-none');
                }
                if (s==2) {
                    $('#po_step_col').show();
                    $('#po_journey_section').removeClass('d-none');
                }
                if (s==3) {
                    $('#aadhaar_step_col').show();
                    $('#po_journey_section').addClass('d-none');
                }
            }
            function updateFormAction(step, id) {
                var action = stepSaveUrlBase + '/' + String(step || 1);
                if (id) action += '/' + String(id);
                $('#order-main-form').attr('action', action);
            }
            function autoFillPoFromCustomerMeta() {
                if (!orderTypeIsB2b() || currentStep !== 2) return;
                var uid = parseInt($('#order_user_id').val() || '0', 10);
                if (!uid) return;
                $.getJSON(custMetaUrl.replace('__ID__', uid), {order_type:'b2b', partner_id:pId() || ''}, function (res) {
                    var p = (res && res.profile) ? res.profile : {};
                    if (p.name) $('#po_manual_name').val(p.name);
                    if (p.email) $('#po_manual_email').val(p.email);
                    if (p.mobile) $('#po_manual_mobile').val(p.mobile);
                    if (p.gstin) $('#po_manual_gstin').val(p.gstin);
                    if (p.company_name) $('#po_manual_company_name,#po_gst_company_name').val(p.company_name);
                    if (p.company_address) $('#po_manual_company_address,#po_gst_company_address').val(p.company_address);
                    if (p.city) $('#po_manual_city,#po_gst_city').val(p.city);
                    if (p.state) $('#po_manual_state,#po_gst_state').val(p.state);
                    if (p.country) $('#po_manual_country,#po_gst_country').val(p.country);
                    if (p.pincode) {
                        var pin = String(p.pincode).replace(/[^\d]/g, '').slice(0, 6);
                        $('#po_manual_pincode,#po_gst_pincode').val(pin);
                    }
                    $('#po_gstin_verified_fields').removeClass('d-none');
                });
            }
            function applyPlanQtyLimit() {
                var $opt = $('#order_plan_id option:selected');
                var limit = parseInt($opt.data('customer-limit'), 10) || 0;
                var $qty = $('#user_quantity');
                if (!$qty.length) {
                    return;
                }
                if (limit > 0) {
                    $qty.attr('max', String(limit));
                    var v = parseInt($qty.val(), 10);
                    if (!isNaN(v) && v > limit) {
                        $qty.val(limit);
                    }
                    $('#qty_limit_hint').removeClass('d-none').text('Max ' + limit + ' licenses for this plan.');
                } else {
                    $qty.removeAttr('max');
                    $('#qty_limit_hint').addClass('d-none').text('');
                }
            }
            var isEditOrderForm = !!(orderSummaryPricing && wizardOrderId);

            function paintOrderSummary(subtotal, discountAmt, taxable, gst, total, gstR, applyGst, cgst, sgst, igst) {
                var isMh = resolveGstStateCode() === '27';
                if (typeof cgst === 'undefined') {
                    cgst = isMh ? gst / 2 : 0;
                    sgst = isMh ? gst - cgst : 0;
                    igst = isMh ? 0 : gst;
                }
                $('#summary_subtotal').text('₹' + subtotal.toFixed(2));
                $('#summary_discount').text('- ₹' + (discountAmt || 0).toFixed(2));
                $('#summary_taxable').text('₹' + taxable.toFixed(2));
                $('#summary_gst_value').text('₹' + gst.toFixed(2));
                $('#summary_total').text('₹' + total.toFixed(2));
                $('#summary_gst_rate_label').text(applyGst ? gstR : 0);
                $('#summary_cgst_wrap,#summary_sgst_wrap').toggleClass('d-none', !isMh || !applyGst);
                $('#summary_igst_wrap').toggleClass('d-none', isMh || !applyGst);
                $('#summary_cgst').text('₹' + cgst.toFixed(2));
                $('#summary_sgst').text('₹' + sgst.toFixed(2));
                $('#summary_igst').text('₹' + igst.toFixed(2));
            }

            function applyStoredOrderSummary() {
                if (!orderSummaryPricing) {
                    return { subtotal: 0, discountAmt: 0, taxable: 0, gst: 0, total: 0 };
                }
                var p = orderSummaryPricing;
                var subtotal = parseFloat(p.subtotal) || 0;
                var discountAmt = parseFloat(p.discount_amount) || 0;
                var taxable = parseFloat(p.taxable_amount) || Math.max(0, subtotal - discountAmt);
                var gst = parseFloat(p.gst_amount) || 0;
                var total = parseFloat(p.grand_total) || 0;
                var gstR = parseFloat(p.gst_rate) || 0;
                var applyGst = !!p.apply_gst;
                paintOrderSummary(
                    subtotal,
                    discountAmt,
                    taxable,
                    gst,
                    total,
                    gstR,
                    applyGst,
                    parseFloat(p.cgst) || 0,
                    parseFloat(p.sgst) || 0,
                    parseFloat(p.igst) || 0
                );
                $('#amount').val(total.toFixed(2));
                $('#gst_amount,#tax_amount').val(gst.toFixed(2));
                return { subtotal: subtotal, discountAmt: discountAmt, taxable: taxable, gst: gst, total: total };
            }

            function validateOrderForm() {
                var $opt = $('#order_plan_id option:selected');
                var type = ($('#order_type_select').val()||'').trim().toLowerCase();
                var qtyFieldExists = $('#user_quantity').length > 0;
                var qtyRaw = parseInt($('#user_quantity').val(), 10);
                var qty = qtyFieldExists ? (isNaN(qtyRaw) ? 1 : Math.max(1, qtyRaw)) : 1,
                    partnerPrice = parseFloat($opt.data('partner-price'))||0,
                    retailPrice = parseFloat($opt.data('retail-price'))||0,
                    limit = parseInt($opt.data('customer-limit'),10)||0,
                    disc = parseFloat($('#discount_amount').val())||0,
                    gstR = parseFloat($('#gst_rate').val())||0,
                    applyGst = ($('#apply_gst').val()||'0')=='1',
                    promoPercent = window._promoPercent||0,
                    availCred = type=='b2b'?parseFloat($('#partner_wallet_balance').val())||0:parseFloat($('#customer_credit').val())||0,
                    mode = ($('#payment_mode').val()||''),
                    bankName = ($('#bank_name').val()||'').trim(),
                    upiTxn = ($('#upi_transaction_id').val()||'').trim(),
                    dom = currentDomainRaw(),
                    noteInvalid = false;
                var price = type=='b2b'?partnerPrice:retailPrice;
                var subtotal = price * qty;
                var discountAmt = promoPercent ? subtotal * promoPercent / 100 : disc;
                var taxable = Math.max(0, subtotal - discountAmt);
                var gst = applyGst ? taxable * gstR / 100 : 0;
                var total = subtotal - discountAmt + gst;
                if (isEditOrderForm) {
                    var stored = applyStoredOrderSummary();
                    subtotal = stored.subtotal;
                    discountAmt = stored.discountAmt;
                    taxable = stored.taxable;
                    gst = stored.gst;
                    total = stored.total;
                } else {
                    $('#amount').val(total.toFixed(2));
                    $('#gst_amount,#tax_amount').val(gst.toFixed(2));
                    paintOrderSummary(subtotal, discountAmt, taxable, gst, total, gstR, applyGst);
                }
                var isMh = resolveGstStateCode() === '27';
                $('#summary_credit').text('₹' + availCred.toFixed(2));
                // Validation
                var qtyErr = limit>0&&qty>limit,
                    // For B2B, block only when a positive credit balance exists and total exceeds it.
                    credErr = type=='b2b' && availCred>0 && total>availCred;
                var payErr = false;
                var domainStatus = (window._domainStatus || '');
                var domainChecking = !skipDomainVerification() && domainStatus === 'checking';
                var domErr = (dom === '')
                    ? true
                    : (!skipDomainVerification() && (domainStatus === 'invalid' || domainStatus === '' || domainChecking));
                $('#qty_error').toggleClass('d-none',!qtyErr).text(qtyErr ? ('Licenses cannot exceed plan limit of ' + limit + '.') : '');
                $('#credit_error').toggleClass('d-none',!credErr).text(credErr?'Exceeds credit':'');
                $('#payment_error').toggleClass('d-none',!payErr).text(payErr?'Fill payment':'');
                var domainMsg = '';
                if (dom === '') {
                    domainMsg = 'Domain name is required.';
                } else if (domainChecking) {
                    domainMsg = 'Checking domain availability…';
                } else if (domErr) {
                    domainMsg = window._domainCheckMessage || 'Domain is not valid for this order.';
                }
                $('#domain_check_error').toggleClass('d-none', !domErr).text(domainMsg);
                if (!domErr && !skipDomainVerification() && domainStatus === 'valid' && window._domainCheckMessage) {
                    $('#domain_name_hint').removeClass('d-none text-muted text-danger').addClass('text-success')
                        .text(window._domainCheckMessage);
                }
                $('#submit-btn').prop('disabled', qtyErr || credErr || noteInvalid || domErr || domainChecking);
            }
            function domainCheck() {
                var dn = currentDomainRaw();
                window._domainStatus = 'checking';
                window._domainCheckMessage = '';
                if (!dn) {
                    window._domainStatus = 'invalid';
                    window._domainCheckMessage = 'Domain name is required.';
                    validateOrderForm();
                    return;
                }
                if (skipDomainVerification()) {
                    window._domainStatus = 'valid';
                    window._domainCheckMessage = 'Domain accepted for this provider.';
                    validateOrderForm();
                    return;
                }
                var msSuggest = isMicrosoftProvider() && !allowsMultiDomain();
                var multi = allowsMultiDomain();
                if (msSuggest) {
                    dn = ($('#domain_keyword').val() || dn).trim().toLowerCase();
                } else if (multi) {
                    dn = ($('#domain_keyword_multi').val() || dn).trim().toLowerCase();
                }
                clearTimeout(window._dctm);
                window._dctm = setTimeout(function () {
                    $.getJSON(domainCheckUrl, {
                        domain_name: dn,
                        keyword: multi
                            ? ($('#domain_keyword_multi').val() || '').trim().toLowerCase()
                            : ($('#domain_keyword').val() || '').trim().toLowerCase(),
                        provider_id: providerId() || '',
                        plan_id: $('#order_plan_id').val() || '',
                        transfer_domain: ($('#transfer_domain').val() || '').trim().toLowerCase()
                    }, function (r) {
                        if (msSuggest) {
                            var suggestions = (r && r.suggested_domains) ? r.suggested_domains : [];
                            if (suggestions.length) {
                                populateMicrosoftDomainSelect(suggestions, $('#domain_suggested_select').val());
                            }
                            var selected = ($('#domain_suggested_select').val() || '').trim();
                            window._domainStatus = selected ? 'valid' : (suggestions.length ? 'invalid' : 'valid');
                            window._domainCheckMessage = selected
                                ? 'Microsoft domain selected.'
                                : (suggestions.length ? 'Select a suggested domain.' : ((r && r.message) || ''));
                        } else if (multi) {
                            var suggestions = (r && r.suggested_domains) ? r.suggested_domains : [];
                            var selected = $('#domain_multi_select').val() || [];
                            if (!Array.isArray(selected)) {
                                selected = selected ? [selected] : [];
                            }
                            if (suggestions.length) {
                                var merged = suggestions.slice();
                                selected.forEach(function (d) {
                                    if (merged.indexOf(d) < 0) {
                                        merged.push(d);
                                    }
                                });
                                populateMultiDomainSelect(merged, selected.length ? selected : suggestions);
                            }
                            var hasPick = ($('#domain_multi_select').val() || []).length > 0;
                            // Transfer=Yes Microsoft: server also verifies registration (available=true).
                            var regOk = !(r && r.success === true && r.available === false);
                            if (r && typeof r.registered !== 'undefined' && r.registered === false) {
                                regOk = false;
                            }
                            if (r && r.success && r.available === false) {
                                regOk = false;
                            }
                            window._domainStatus = (hasPick && regOk) ? 'valid' : 'invalid';
                            window._domainCheckMessage = !regOk
                                ? ((r && r.message) || 'Domain is not registered yet.')
                                : (hasPick ? ((r && r.message) || 'Domain(s) selected.') : 'Select at least one domain from the list.');
                        } else {
                            var ok = r && r.success && r.available;
                            window._domainStatus = ok ? 'valid' : 'invalid';
                            window._domainCheckMessage = (r && r.message)
                                ? r.message
                                : (ok ? 'Domain is registered.' : 'Domain is not valid for this order.');
                        }
                        validateOrderForm();
                    }).fail(function (xhr) {
                        var msg = 'Could not verify the domain. Try again.';
                        try {
                            if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                                msg = xhr.responseJSON.message;
                            }
                        } catch (e) {}
                        window._domainStatus = 'invalid';
                        window._domainCheckMessage = msg;
                        validateOrderForm();
                    });
                }, 350);
            }
            function promoCodeApply() {
                var code = ($('#promocode').val()||'').trim();
                if (!code) { window._promoPercent=0; return validateOrderForm(); }
                $.post(applyPromoApiUrl, {code:code,_token:$('meta[name="csrf-token"]').attr('content')}, function(r){
                    window._promoPercent = (r.discount_percent||0)*1;
                    validateOrderForm();
                }).fail(function(){window._promoPercent=0; validateOrderForm();});
            }
            // UI bindings
            if ($.fn.select2) {
                $('#order_partner_id,#order_user_id').select2({width:'100%',allowClear:true});
                $('#order_plan_id').select2({width:'100%',allowClear:true});
            }
            $('#order_add_partner_btn').on('click', function () { window.location.href = partnerAddUrl; });
            $('#order_add_customer_btn').on('click', function () { window.location.href = orderTypeIsB2b() ? b2bCustUrl : b2cCustUrl; });

            $('#order_type_select').on('change', function () {
                syncOrderTypeUi();
                if (orderTypeIsB2b() && pId()) {
                    loadPartnerCustomersDynamic(null);
                } else {
                    renderCustomersSel();
                }
                syncBal();
                validateOrderForm();
                autoFillPoFromCustomerMeta();
            });
            $('#order_plan_id').on('change', function(){ applyPlanQtyLimit(); domainCheck(); validateOrderForm(); });
            $('#order_partner_id').on('change', function () {
                initialUserId = null;
                loadPartnerCustomersDynamic(null);
                syncBal();
                validateOrderForm();
                autoFillPoFromCustomerMeta();
            });
            $('#order_user_id').on('change',function(){ loadCustDomains($(this).val()); syncBal(); validateOrderForm(); autoFillPoFromCustomerMeta(); });
            $('#user_quantity,#discount_amount,#gst_rate,#bank_name,#cheque_ref_no,#upi_transaction_id,#order_note').on('input',validateOrderForm);
            function stepUserQuantity(delta) {
                var $qty = $('#user_quantity');
                if (!$qty.length) return;
                var min = parseInt($qty.attr('min'), 10);
                if (isNaN(min)) min = 1;
                var maxAttr = parseInt($qty.attr('max'), 10);
                var current = parseInt($qty.val(), 10);
                if (isNaN(current)) current = min;
                var next = current + delta;
                if (next < min) next = min;
                if (!isNaN(maxAttr) && maxAttr > 0 && next > maxAttr) next = maxAttr;
                $qty.val(next);
                applyPlanQtyLimit();
                validateOrderForm();
            }
            $('#user_quantity_plus').on('click', function () { stepUserQuantity(1); });
            $('#user_quantity_minus').on('click', function () { stepUserQuantity(-1); });
            $('#domain_name,#domain_keyword,#domain_keyword_multi').on('keyup change', domainCheck);
            $('#transfer_domain').on('change', function () {
                syncDomainFieldMode();
                domainCheck();
            });
            $('#domain_suggested_select').on('change', function () {
                $('#domain_name_ms').val($(this).val() || '');
                domainCheck();
            });
            $('#domain_multi_select').on('change', function () {
                syncMultiDomainHidden();
                domainCheck();
            });
            $('#po_manual_gstin').on('input change', validateOrderForm);
            $('#promocode').on('blur change',promoCodeApply);
            $('#payment_mode').change(validateOrderForm);
            $('#apply_gst').change(function(){ $('#gst_rate_wrap,#gst_amount_wrap').toggleClass('d-none',this.value!='1'); validateOrderForm(); });

            // Variants/plans
            /** Plans API uses leaf variant when set (sub), else main, else 0. */
            function effectiveVariantId() {
                var sid = parseInt($('#order_sub_variant_id').val() || '0', 10);
                if (!isNaN(sid) && sid > 0) return sid;
                var mid = parseInt($('#order_variant_id').val() || '0', 10);
                return (!isNaN(mid) && mid > 0) ? mid : 0;
            }
            function requireVariantSelectionsForPlans() {
                var needMain = !$('#order_variant_wrap').hasClass('d-none');
                var needSub = !$('#order_sub_variant_wrap').hasClass('d-none');
                var mid = parseInt($('#order_variant_id').val() || '0', 10);
                var sid = parseInt($('#order_sub_variant_id').val() || '0', 10);
                if (needMain && (!mid || isNaN(mid))) {
                    alert('Select main variant');
                    return false;
                }
                if (needSub && (!sid || isNaN(sid))) {
                    alert('Select sub variant');
                    return false;
                }
                return true;
            }
            function rendSubVar(mid) {
                var $s = $('#order_sub_variant_id'); $s.find('option:not(:first)').remove();
                (svbm[''+mid]||[]).forEach(i=> $s.append($('<option>').val(i.id).text(i.name)));
                $('#order_sub_variant_wrap').toggleClass('d-none', !(mid && svbm['' + mid] && svbm['' + mid].length));
            }
            function rendVar(pid) {
                var $s = $('#order_variant_id'), $sub = $('#order_sub_variant_id');
                $s.find('option:not(:first)').remove(); $sub.find('option:not(:first)').remove();
                var pKey = '' + pid;
                var l = mvbp[pid] || mvbp[pKey] || vbp[pid] || vbp[pKey] || [];
                l.forEach(i=> $s.append($('<option>').val(i.id).text(i.name)));
                $('#order_variant_wrap').toggleClass('d-none', !l.length);
                rendSubVar(parseInt($s.val()||0));
            }
            /** Restore main/sub selects from plan (wizard steps 2–3 and edit). */
            function applySavedVariantSelections() {
                var mid = parseInt(selMainVid, 10) || 0;
                var sid = parseInt(selSubVid, 10) || 0;
                if (!mid || isNaN(mid)) return;
                var $mv = $('#order_variant_id');
                if (!$mv.find('option[value="' + mid + '"]').length) return;
                $mv.val(String(mid));
                rendSubVar(mid);
                if (sid && !isNaN(sid) && $('#order_sub_variant_id').find('option[value="' + sid + '"]').length) {
                    $('#order_sub_variant_id').val(String(sid));
                }
            }
            function fillPlans(pid, vid, keep) {
                var $p = $('#order_plan_id').prop('disabled',1);
                $.getJSON(plansUrl, {provider_id:pid,variant_id:vid||0}, function(r){
                    $p.find('option:not(:first)').remove();
                    (r.plans||[]).forEach(p=> $p.append($('<option>').val(p.id).attr({'data-partner-price':p.b2b_price||0,'data-retail-price':p.b2c_price||p.b2b_price||0,'data-customer-limit':p.customer_limit||0}).text(p.name||'Plan #'+p.id)));
                    if (keep&&$p.find('[value="'+keep+'"]').length) $p.val(keep);
                    $p.prop('disabled',0);
                    if ($.fn.select2 && $p.hasClass('select2-hidden-accessible')) {
                        $p.trigger('change.select2');
                    }
                    applyPlanQtyLimit();
                    validateOrderForm();
                });
            }
            if (!skipProvider) {
                $('#order_provider_id').on('change', function(){
                    selVid=selMainVid=selSubVid=0;$('#order_variant_id,#order_sub_variant_id').val('');
                    rendVar(providerId());
                    $('#order_provider_id_inline').val($(this).val());
                    syncDomainFieldMode();
                    domainCheck();
                });
                $('#order_variant_id').on('change', function () {
                    selSubVid = 0;
                    $('#order_sub_variant_id').val('');
                    rendSubVar($(this).val());
                    var pid = providerId();
                    if (!pid || $('#order-main-form').hasClass('d-none')) return;
                    if ($('#order_sub_variant_wrap').hasClass('d-none')) {
                        fillPlans(pid, effectiveVariantId(), oldPlanId);
                    }
                });
                $('#order_sub_variant_id').on('change', function () {
                    var pid = providerId();
                    if (!pid || $('#order-main-form').hasClass('d-none')) return;
                    var subVis = !$('#order_sub_variant_wrap').hasClass('d-none');
                    var sid = parseInt($(this).val() || '0', 10);
                    if (subVis && (!sid || isNaN(sid))) {
                        var mid = parseInt($('#order_variant_id').val() || '0', 10);
                        if (mid) fillPlans(pid, mid, oldPlanId);
                        return;
                    }
                    fillPlans(pid, effectiveVariantId(), oldPlanId);
                });
                $('#order_load_plans_btn').on('click',function(){
                    var pid=providerId();
                    if (!pid) return alert('Select provider');
                    if (!requireVariantSelectionsForPlans()) return;
                    fillPlans(pid, effectiveVariantId(), oldPlanId);
                    $('#order-main-form').removeClass('d-none');
                });
                if (!skipProvider && selPid) {
                    var pidStr = String(selPid);
                    if ($('#order_provider_id').length) {
                        $('#order_provider_id').val(pidStr);
                    }
                    if ($('#order_provider_id_inline').length) {
                        $('#order_provider_id_inline').val(pidStr);
                    }
                    rendVar(selPid);
                    applySavedVariantSelections();
                    if (!$('#order-main-form').hasClass('d-none') && providerId()) {
                        fillPlans(providerId(), effectiveVariantId(), oldPlanId);
                    }
                }
            }

            function clearWizardServerErrors() {
                var $f = $('#order-main-form');
                $f.find('.is-invalid').removeClass('is-invalid border-danger');
                $f.find('.server-field-error').text('').addClass('d-none');
            }

            function applyWizardValidationErrors($form, details) {
                if (!details) return;
                $.each(details, function (field, msgs) {
                    var msg = Array.isArray(msgs) ? (msgs[0] || '') : String(msgs || '');
                    var safeField = String(field).replace(/\./g, '_');
                    var $err = $('#error-' + safeField);
                    if ($err.length) {
                        $err.text(msg).removeClass('d-none');
                    }
                    var $inp = $form.find('[name="' + String(field).replace(/"/g, '\\"') + '"]');
                    if ($inp.length) {
                        $inp.addClass('is-invalid border-danger');
                    }
                });
            }

            $('#order-main-form').on('submit', function (e) {
                e.preventDefault();
                var $form = $(this);

                applyPlanQtyLimit();
                validateOrderForm();
                if (!$('#qty_error').hasClass('d-none')) {
                    return false;
                }

                if (orderTypeIsB2b() && !skipProvider && (currentStep === 2 || currentStep === 3)) {
                    if (!requireVariantSelectionsForPlans()) {
                        return false;
                    }
                }

                clearWizardServerErrors();

                if (orderTypeIsB2b() && !pId()) {
                    applyWizardValidationErrors($form, { partner_id: ['Please select a partner.'] });
                    showOrderFormAlert('Please select a partner.', 'error');
                    return false;
                }

                var selectedUserId = ($('#order_user_id').val() || '').trim();
                if (!selectedUserId) {
                    applyWizardValidationErrors($form, { user_id: ['Please select a customer.'] });
                    showOrderFormAlert('Please select a customer.', 'error');
                    return false;
                }

                var statusVal = String($('#wizard_order_status').val() || '').toLowerCase();
                if (statusVal === 'rejected' || statusVal === 'cancelled' || statusVal === 'canceled') {
                    var reason = String($('#rejection_reason').val() || '').trim();
                    if (!reason) {
                        $('#wizard_cancel_reason_wrap').removeClass('d-none');
                        applyWizardValidationErrors($form, { rejection_reason: ['Enter cancellation reason.'] });
                        showOrderFormAlert('Enter cancellation reason.', 'error');
                        return false;
                    }
                }

                // Disabled fields are omitted from FormData — re-enable before submit.
                $('#order_user_id').prop('disabled', false);
                if (orderTypeIsB2b()) {
                    $('#order_partner_id').prop('disabled', false);
                }

                var $btn = $('#submit-btn');
                var $spin = $('#order-form-spinner');
                var token = $('meta[name="csrf-token"]').attr('content');

                $.ajax({
                    url: $form.attr('action'),
                    type: 'POST',
                    data: new FormData(this),
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token || ''
                    },
                    beforeSend: function () {
                        $btn.prop('disabled', true);
                        $spin.removeClass('d-none');
                    },
                    complete: function () {
                        $spin.addClass('d-none');
                        $btn.prop('disabled', false);
                        validateOrderForm();
                    },
                    success: function (data) {
                        if (!data || typeof data !== 'object') {
                            showOrderFormAlert('Unexpected response', 'error');
                            return;
                        }
                        if (data.error) {
                            if (data.details) {
                                applyWizardValidationErrors($form, data.details);
                            }
                            showOrderFormAlert(data.message || 'Please fix the highlighted fields.', 'error');
                            return;
                        }
                        if (data.success) {
                            showOrderFormAlert(data.message || 'Saved', 'success');
                            if (data.redirect_url) {
                                setTimeout(function () {
                                    window.location.href = data.redirect_url;
                                }, 3500);
                            }
                            return;
                        }
                    },
                    error: function (xhr) {
                        var j = xhr.responseJSON;
                        if (xhr.status === 422 && j && j.details) {
                            applyWizardValidationErrors($form, j.details);
                            showOrderFormAlert(j.message || 'Validation failed', 'error');
                            return;
                        }
                        if (xhr.status === 422 && j && j.errors) {
                            applyWizardValidationErrors($form, j.errors);
                            showOrderFormAlert(j.message || 'Validation failed', 'error');
                            return;
                        }
                        postOrderFormAlertFromXhr(xhr, 'Request failed');
                    }
                });
                return false;
            });

            function syncWizardStatusUi() {
                var $sel = $('#wizard_order_status');
                if (!$sel.length) {
                    return;
                }
                var val = String($sel.val() || '').toLowerCase();
                var $badge = $('#wizard_status_badge');
                var $reasonWrap = $('#wizard_cancel_reason_wrap');
                var label = 'Pending';
                var badgeClass = 'bg-warning text-dark';

                if (val === 'completed' || val === 'active' || val === 'paid' || val === 'success') {
                    label = 'Active';
                    badgeClass = 'bg-success';
                } else if (val === 'rejected' || val === 'cancelled' || val === 'canceled' || val === 'failed') {
                    label = 'Cancelled';
                    badgeClass = 'bg-danger';
                } else if (val === 'processing') {
                    label = 'Processing';
                    badgeClass = 'bg-primary';
                } else if (val) {
                    label = val.charAt(0).toUpperCase() + val.slice(1);
                }

                if ($badge.length) {
                    $badge
                        .removeClass('bg-success bg-danger bg-primary bg-warning text-dark')
                        .addClass(badgeClass)
                        .text(label);
                }

                var showReason = (val === 'rejected' || val === 'cancelled' || val === 'canceled');
                $reasonWrap.toggleClass('d-none', !showReason);
                if (!showReason) {
                    $('#rejection_reason').val('');
                }
            }
            $(document).on('change', '#wizard_order_status', syncWizardStatusUi);
            syncWizardStatusUi();

            // PO/Aadhaar simplified
            $('#po_mode').change(validateOrderForm);

            function syncPoModeUi() {
                var mode = ($('input[name="po_mode_choice"]:checked').val() || 'generated').toString();
                $('#po_mode').val(mode);
                if (mode === 'uploaded') {
                    $('#po_upload_fields').removeClass('d-none');
                } else {
                    $('#po_upload_fields').addClass('d-none');
                }
                validateOrderForm();
            }
            $(document).on('change', 'input[name="po_mode_choice"]', syncPoModeUi);
            syncPoModeUi();
            function applyGstinResponseToPoFields(r) {
                if (!r || r.error) return false;
                var root = r.data || r.result || r.taxpayer || r.taxpayerInfo || r;
                var addr = root.pradr?.addr || root.address || {};

                var companyName = root.company_name || root.trade_name || root.legal_name || root.lgnm || root.tradeNam || '';
                var companyAddress = root.company_address
                    || root.address
                    || [addr.bnm, addr.st, addr.loc, addr.dst].filter(Boolean).join(', ')
                    || '';
                var city = root.city || addr.city || addr.dst || root.district || '';
                var state = root.state || addr.state || addr.stcd || root.stateCode || '';
                var country = root.country || addr.country || 'India';

                $('#po_manual_company_name,#po_gst_company_name').val(companyName);
                $('#po_manual_company_address,#po_gst_company_address').val(companyAddress);
                $('#po_manual_city,#po_gst_city').val(city);
                $('#po_manual_state,#po_gst_state').val(state);
                $('#po_manual_country,#po_gst_country').val(country);
                // Pincode is sourced strictly from customer zip_code profile field.
                $('#po_gstin_verified_fields').removeClass('d-none');
                return true;
            }
            function fetchAndFillGstin(gstin, silent) {
                var g = (gstin || '').trim().toUpperCase();
                if (!g || g.length < 15) return;
                $('#po_manual_gstin').val(g);
                $.post(gstSearchUrl, {_token:$('meta[name="csrf-token"]').attr('content'), gstin:g}, function(r){
                    if (applyGstinResponseToPoFields(r)) {
                        if (!silent) alertify.success('GSTIN details loaded');
                    } else if (!silent) {
                        alertify.error((r && r.message) ? r.message : 'Invalid GSTIN');
                    }
                }).fail(function(){
                    if (!silent) alertify.error('GSTIN search failed');
                });
            }
            $('#po_gstin_search_btn').click(function(){
                var gstin = ($('#po_manual_gstin').val()||'').trim().toUpperCase();
                if(!gstin)return;
                fetchAndFillGstin(gstin, false);
            });
            $('#po_manual_gstin').on('change blur', function () {
                fetchAndFillGstin($(this).val(), true);
            });
            // Aadhaar
            var aadhaarInit = false;
            $('#aadhaar_send_otp_btn,#aadhaar_resend_otp_btn').click(function(){
                var ad=($('#aadhaar_number_local').val()||'').trim();
                if(!wizardOrderId){ showOrderFormAlert('Missing order id', 'error'); return; }
                if(!/^\d{12}$/.test(ad)){ showOrderFormAlert('Enter valid 12-digit Aadhaar number', 'error'); return; }
                var $btn=$(this).prop('disabled',1);
                $.ajax({
                    url: aadhaarInitApiUrl,
                    method: 'POST',
                    data: { _token: $('meta[name="csrf-token"]').attr('content'), order_id: wizardOrderId, aadhaar_number: ad },
                    success: function (r) {
                        if (r && r.success) {
                            aadhaarInit = 1;
                            $('#aadhaar_number_block').addClass('d-none');
                            $('#aadhaar_otp_block').removeClass('d-none');
                            showOrderFormAlert(r.message || 'OTP sent', 'success');
                        } else {
                            showOrderFormAlert((r && r.message) ? r.message : 'Failed to send OTP', 'error');
                        }
                    },
                    error: function (xhr) {
                        postOrderFormAlertFromXhr(xhr, 'Failed to send OTP');
                    },
                    complete: function () {
                        $btn.prop('disabled', false);
                    }
                });
            });
            $('#aadhaar_verify_local_btn').click(function(){
                var otp=($('#aadhaar_otp_local').val()||'').trim();
                if(!wizardOrderId||!aadhaarInit){ showOrderFormAlert('Send OTP first', 'error'); return; }
                if(!/^\d{6}$/.test(otp)){ showOrderFormAlert('Enter valid 6-digit OTP', 'error'); return; }
                var $btn=$(this).prop('disabled',1);
                $.ajax({
                    url: aadhaarOtpVerifyApiUrl,
                    method: 'POST',
                    data: { _token: $('meta[name="csrf-token"]').attr('content'), order_id: wizardOrderId, otp: otp },
                    success: function (r) {
                        if (r && r.success) {
                            $('#aadhar_verified_local').val('1');
                            showOrderFormAlert(r.message || 'Aadhaar verified', 'success');
                            var go = (r && r.redirect_url) ? r.redirect_url : null;
                            if (go) {
                                setTimeout(function () {
                                    window.location.href = go;
                                }, 3500);
                            }
                        } else {
                            showOrderFormAlert((r && r.message) ? r.message : 'OTP verification failed', 'error');
                        }
                    },
                    error: function (xhr) {
                        postOrderFormAlertFromXhr(xhr, 'OTP verification failed');
                    },
                    complete: function () {
                        $btn.prop('disabled', false);
                    }
                });
            });

            // Init
            if (!isEdit && !wizardOrderId) {
                $('#order_type_select').val('b2c');
            }
            syncOrderTypeUi();
            if (orderTypeIsB2b() && pId()) {
                loadPartnerCustomersDynamic(initialUserId);
            } else {
                renderCustomersSel();
            }
            syncBal();
            updateFormAction(currentStep, wizardOrderId);
            setStep(currentStep);
            autoFillPoFromCustomerMeta();
            if (currentStep === 2) {
                fetchAndFillGstin($('#po_manual_gstin').val() || '', true);
            }
            syncDomainFieldMode();
            if (savedMicrosoftDomains && savedMicrosoftDomains.length) {
                populateMicrosoftDomainSelect(savedMicrosoftDomains, $('#domain_name_ms').val() || '');
            }
            if (savedDomainList && savedDomainList.length && allowsMultiDomain()) {
                populateMultiDomainSelect(savedDomainList, savedDomainList);
            }
            if (isMicrosoftProvider() && !allowsMultiDomain()) {
                var savedMs = ($('#domain_name_ms').val() || '').trim();
                if (savedMs) {
                    var kw = savedMs.replace(/\.onmicrosoft\.com$/i, '').replace(/[12]$/, '');
                    $('#domain_keyword').val(kw);
                }
            } else if (allowsMultiDomain() && savedDomainList && savedDomainList.length) {
                var first = savedDomainList[0] || '';
                var kwMulti = first.replace(/\.onmicrosoft\.com$/i, '').replace(/[12]$/, '');
                $('#domain_keyword_multi').val(kwMulti);
            }
            applyPlanQtyLimit();
            validateOrderForm();
            domainCheck();
        });
    </script>
@endpush
