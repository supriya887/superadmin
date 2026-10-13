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
                                        <option value="{{ $p->id }}"
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
                                    </div>
                                    <div class="col-md-6 {{ $showPartnerFieldOnLoad ? '' : 'd-none' }}" id="order_partner_field_wrap">
                                        <label class="form-label">Partner</label>
                                        <select name="partner_id" id="order_partner_id" class="form-select" required>
                                            <option value="">Select Partner</option>
                                            @foreach ($partners as $partner)
                                                <option value="{{ $partner->id }}"
                                                    {{ (string) old('partner_id', $isEdit ? $order->partner_id ?? '' : '') === (string) $partner->id ? 'selected' : '' }}>
                                                    {{ $partner->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                                            <label class="form-label mb-0">Customer</label>
                                            <button type="button" class="btn btn-sm btn-outline-primary px-2 py-0"
                                                id="order_add_customer_btn" title="Add new customer">+</button>
                                        </div>
                                        <select name="user_id" id="order_user_id" class="form-select" required>
                                            <option value="">Select Customer</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Domain name</label>
                                        <input type="text" name="domain_name" id="domain_name" class="form-control"
                                            list="previous_domains_list"
                                            value="{{ old('domain_name', $isEdit ? $order->domain_name ?? '' : '') }}"
                                            placeholder="example.com" autocomplete="off" required>
                                        <small id="domain_check_error" class="text-danger d-none"></small>
                                        {{-- <small class="text-muted d-block mt-1">Previous Domains</small> --}}
                                        <datalist id="previous_domains_list"></datalist>
                                    </div>
                                    @if (!empty($hasQuantityColumn))
                                        <div class="col-md-3">
                                            <label class="form-label">User quantity</label>
                                            <input type="number" name="quantity" id="user_quantity"
                                                class="form-control" min="1" step="1"
                                                value="{{ old('quantity', $isEdit ? $order->quantity ?? 1 : 1) }}"
                                                placeholder="Number of users / seats">
                                            <small id="qty_error" class="text-danger d-none"></small>
                                        </div>
                                    @endif

                                    <div class="col-md-3">
                                        <label class="form-label">Status</label>
                                        <input type="hidden" name="status"
                                            value="{{ old('status', $isEdit ? $order->resolved_status ?? 'pending' : 'pending') }}">
                                        <input type="text" class="form-control bg-light"
                                            value="{{ ucfirst((string) old('status', $isEdit ? $order->resolved_status ?? 'pending' : 'pending')) }}"
                                            readonly>
                                    </div>
                                    @if (!empty($hasPromoCodeColumn))
                                    <div class="col-md-3">
                                        <label class="form-label">Promocode</label>
                                        <input type="text" name="promocode" id="promocode" class="form-control"
                                            value="{{ old('promocode', $isEdit ? $order->promo_code ?? '' : '') }}"
                                            placeholder="Enter promocode">
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
                                                    <input type="hidden" name="po_mode" id="po_mode" value="generated">
                                                    <input type="hidden" name="po_number" id="po_number"
                                                        value="{{ old('po_number', $isEdit ? $order->po_number ?? '' : '') }}">
                                                    <input type="hidden" name="po_date" id="po_date"
                                                        value="{{ old('po_date', $isEdit ? (!empty($order->po_date) ? \Illuminate\Support\Carbon::parse($order->po_date)->format('Y-m-d') : '') : '') }}">
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
                                    <span>Saving:</span>
                                    <strong class="text-danger" id="summary_discount">- ₹0.00</strong>
                                </div>
                                <div class="d-flex justify-content-between small mb-2">
                                    <span>Taxes:</span>
                                    <strong id="summary_taxes">₹0.00</strong>
                                </div>
                                <div class="d-flex justify-content-between small mb-2">
                                    <span>Taxable Amount:</span>
                                    <strong id="summary_taxable">₹0.00</strong>
                                </div>
                                <div class="d-flex justify-content-between small mb-3">
                                    <span>GST <span id="summary_gst_rate_label">0</span>%:</span>
                                    <strong id="summary_gst_value">₹0.00</strong>
                                </div>
                                <div class="d-flex justify-content-between small mb-2 d-none" id="summary_credit_wrap">
                                    <span>Credit Balance:</span>
                                    <strong id="summary_credit">₹0.00</strong>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="fw-semibold">Estimated Total:</span>
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
                <div class="mt-3 d-flex gap-2">
                    <button type="submit" id="submit-btn" class="btn rounded-pill px-4 py-2 {{ ($orderCompleted && $currentStep >= 2) || $currentStep === 3 ? 'd-none' : '' }}"
                        style="background:#1e58b4;color:#fff;">
                        {{ $currentStep === 1 ? 'Place Order' : ($currentStep === 2 ? 'Save PO Details' : 'Place Order') }}
                    </button>
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

@push('scripts')
    <script>
        $(function () {
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
                aadhaarInitApiUrl = @json(route('api.v1.orders.aadhaar.init')),
                aadhaarOtpVerifyApiUrl = @json(route('api.v1.orders.aadhaar.otp_verify')),
                custDomainsUrl = @json(route('superadmin.orders.customer_domains', ['id' => '__ID__'])),
                custMetaUrl = @json(route('superadmin.orders.customer_meta', ['id' => '__ID__'])),
                domainCheckUrl = @json(route('superadmin.orders.domain_check')),
                stepSaveUrlBase = @json(url('/superadmin/orders-add')),
                b2bCustUrl = @json(route('superadmin.b2b_partners.customers.add_open')),
                initialUserId = @json(old('user_id', $isEdit ? $order->user_id ?? null : null));

            var orderTypeIsB2b = () => ($('#order_type_select').val() + '').trim().toLowerCase() === 'b2b';
            function pId() { var v = $('#order_partner_id').val(); return v ? parseInt(v,10) : 0 }
            function providerId() { var v = $('#order_provider_id').val()||$('#order_provider_id_inline').val(); return v?parseInt(v,10):0 }
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
                    $sel.prop('disabled',1).find('option:first').text('Select Partner First').end().val('');
                    $sel.trigger('change.select2'); syncBal(); return;
                }
                $sel.prop('disabled',0).find('option:first').text('Select Customer');
                arr.forEach(c=> $sel.append($('<option>').val(c.id).text(c.name)));
                var iv = String(initialUserId||'');
                if (prev && $sel.find('[value="'+prev+'"]').length) $sel.val(prev);
                else if (!$sel.data('_bootstrapped') && iv && $sel.find('[value="'+iv+'"]').length) $sel.val(iv);
                else $sel.val('');
                $sel.data('_bootstrapped',1).trigger('change.select2'); syncBal();
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
                    dom = ($('#domain_name').val()||'').trim().toLowerCase(),
                    noteInvalid = false;
                var price = type=='b2b'?partnerPrice:retailPrice;
                var subtotal=price*qty, taxable=subtotal-(promoPercent?subtotal*promoPercent/100:disc);
                var gst=applyGst?(taxable*gstR/100):0, total=taxable+gst;
                // Set fields
                $('#amount').val(total.toFixed(2)); $('#gst_amount,#tax_amount').val(gst.toFixed(2));
                ['#subtotal_display','#summary_subtotal','#taxable_display','#summary_taxable',
                '#gst_display','#summary_taxes','#summary_gst_value','#total_display','#summary_total']
                    .forEach((s,i)=>{
                        var values = [subtotal, taxable, gst, total];
                        var idx = Math.floor(i / 2);
                        var n = Number(values[idx] ?? 0);
                        $(s).text('₹' + n.toFixed(2));
                    });
                $('#summary_total').text('₹' + Number(total || 0).toFixed(2));
                $('#summary_discount').text('- ₹'+((promoPercent?subtotal*promoPercent/100:disc)||0).toFixed(2));
                $('#summary_gst_rate_label').text(applyGst?gstR:0);
                $('#summary_credit').text('₹'+availCred.toFixed(2));
                // Validation
                var qtyErr = limit>0&&qty>limit,
                    // For B2B, block only when a positive credit balance exists and total exceeds it.
                    credErr = type=='b2b' && availCred>0 && total>availCred;
                var payErr = false;
                var domErr = (dom === '') ? true : ((window._domainStatus || '') === 'invalid');
                $('#qty_error').toggleClass('d-none',!qtyErr).text(qtyErr?'Qty limit '+limit:'');
                $('#credit_error').toggleClass('d-none',!credErr).text(credErr?'Exceeds credit':'');
                $('#payment_error').toggleClass('d-none',!payErr).text(payErr?'Fill payment':'');
                $('#domain_check_error').toggleClass('d-none',!domErr).text(domErr?'Domain invalid':'');
                $('#submit-btn').prop('disabled', qtyErr || credErr || noteInvalid);
            }
            function domainCheck() {
                var dn = ($('#domain_name').val()||'').trim().toLowerCase();
                window._domainStatus = 'checking'; if (!dn) {window._domainStatus='invalid';validateOrderForm();return;}
                clearTimeout(window._dctm);
                window._dctm = setTimeout(function(){
                    $.getJSON(domainCheckUrl,{domain_name:dn}, function(r){
                        window._domainStatus = r && r.success && r.available?'valid':'invalid';
                        validateOrderForm();
                    }).fail(()=>{window._domainStatus='invalid'; validateOrderForm()});
                },350);
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
            $('#order_type_select').on('change', function(){ syncOrderTypeUi(); renderCustomersSel(); syncBal(); validateOrderForm(); autoFillPoFromCustomerMeta(); });
            $('#order_plan_id').on('change', function(){ validateOrderForm(); });
            $('#order_partner_id').on('change',function(){ renderCustomersSel(); syncBal(); validateOrderForm(); autoFillPoFromCustomerMeta(); });
            $('#order_user_id').on('change',function(){ loadCustDomains($(this).val()); syncBal(); validateOrderForm(); autoFillPoFromCustomerMeta(); });
            $('#user_quantity,#discount_amount,#gst_rate,#bank_name,#cheque_ref_no,#upi_transaction_id,#order_note').on('input',validateOrderForm);
            $('#domain_name').on('keyup change',domainCheck);
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
                var l = mvbp[pid]||vbp[pid]||[];
                l.forEach(i=> $s.append($('<option>').val(i.id).text(i.name)));
                $('#order_variant_wrap').toggleClass('d-none', !l.length);
                rendSubVar(parseInt($s.val()||0));
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
                    validateOrderForm();
                });
            }
            if (!skipProvider) {
                $('#order_provider_id').on('change', function(){
                    selVid=selMainVid=selSubVid=0;$('#order_variant_id,#order_sub_variant_id').val('');
                    rendVar(providerId());
                    $('#order_provider_id_inline').val($(this).val());
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
                if (isEdit && selPid) { $('#order_provider_id_inline').val(selPid); rendVar(selPid);}
            }

            // PO/Aadhaar simplified
            $('#po_mode').change(validateOrderForm);
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
                if(!wizardOrderId)return alertify.error('Missing order id');
                if(!/^\d{12}$/.test(ad))return alertify.error('Enter valid 12-digit');
                var $btn=$(this).prop('disabled',1);
                $.post(aadhaarInitApiUrl,{order_id:wizardOrderId,aadhaar_number:ad}).done(r=>{
                    if(r.success) {aadhaarInit=1;$('#aadhaar_number_block').addClass('d-none');$('#aadhaar_otp_block').removeClass('d-none');alertify.success('OTP sent');}
                    else alertify.error(r.message||'Failed');
                }).always(()=> $btn.prop('disabled',0));
            });
            $('#aadhaar_verify_local_btn').click(function(){
                var otp=($('#aadhaar_otp_local').val()||'').trim();
                if(!wizardOrderId||!aadhaarInit)return alertify.error('Send OTP first');
                if(!/^\d{6}$/.test(otp))return alertify.error('Enter valid 6-digit OTP');
                var $btn=$(this).prop('disabled',1);
                $.post(aadhaarOtpVerifyApiUrl,{order_id:wizardOrderId,otp}).done(r=>{
                    if(r.success) {
                        $('#aadhar_verified_local').val('1');
                        alertify.success(r.message || 'Aadhaar verified');
                        var go = (r && r.redirect_url) ? r.redirect_url : null;
                        if (go) {
                            window.location.href = go;
                        }
                    }
                    else alertify.error(r.message||'OTP failed');
                }).always(()=> $btn.prop('disabled',0));
            });

            // Init
            if (!isEdit && !wizardOrderId) {
                $('#order_type_select').val('b2c');
            }
            syncOrderTypeUi();
            renderCustomersSel();
            syncBal();
            updateFormAction(currentStep, wizardOrderId);
            setStep(currentStep);
            autoFillPoFromCustomerMeta();
            if (currentStep === 2) {
                fetchAndFillGstin($('#po_manual_gstin').val() || '', true);
            }
            validateOrderForm();
            domainCheck();
        });
    </script>
@endpush
