@extends('superadmin.layouts.app')

@section('content')
    <style>
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
        .Plus-Icon:hover { color: #fff; opacity: 0.9; }
        .legacy-plan-row { position: relative; }
        .legacy-plan-row .btn-remove-row {
            position: absolute;
            top: 0.35rem;
            right: 0.5rem;
            line-height: 1;
            padding: 0 0.35rem;
        }
    </style>
    <div class="row">
        <div class="col-12 mt-2">
            <div class="page-title-box d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h4 class="mb-0">Legacy / Manual Order</h4>
                <div class="d-flex gap-2">
                    <a href="{{ $ordersIndexUrl }}" class="btn btn-sm btn-outline-secondary">Back to orders</a>
                    <a href="{{ route('superadmin.orders.create.step', ['step' => 1]) }}" class="btn btn-sm btn-outline-primary">Standard Add Order</a>
                </div>
            </div>
        </div>

        @if (session('error'))
            <div class="col-12"><div class="alert alert-danger">{{ session('error') }}</div></div>
        @endif
        @if (session('success'))
            <div class="col-12"><div class="alert alert-success">{{ session('success') }}</div></div>
        @endif

        <div class="col-12">
            <div class="card">
                <div class="card-header py-2">
                    <h5 class="card-title mb-0">Manual entry</h5>
                    <p class="small text-muted mb-0 mt-1">No domain check, no Aadhaar OTP, no wallet debit, no customer emails.</p>
                </div>
                <div class="card-body">
                    <form method="post" action="{{ $storeUrl }}" id="legacy_order_form">
                        @csrf
                        <input type="hidden" name="is_legacy" value="1">
                        <input type="hidden" name="amount" id="legacy_amount_hidden" value="0">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                                    <label class="form-label mb-0" for="legacy_partner_id">Partner <span class="text-danger">*</span></label>
                                    <a href="{{ $partnerAddUrl }}" class="Plus-Icon" title="Add Partner">+</a>
                                </div>
                                <select name="partner_id" id="legacy_partner_id" class="form-select" required>
                                    <option value="">— Select partner —</option>
                                    @foreach($partners as $partner)
                                        <option value="{{ $partner->id }}" {{ old('partner_id') == $partner->id ? 'selected' : '' }}>{{ $partner->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                                    <label class="form-label mb-0" for="legacy_user_id">Customer <span class="text-danger">*</span></label>
                                    <a href="{{ $partnerCustomerAddOpenUrl }}" class="Plus-Icon" id="legacy_add_customer_btn" title="Add Customer">+</a>
                                </div>
                                <select name="user_id" id="legacy_user_id" class="form-select" required disabled>
                                    <option value="">Select partner first</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="legacy_transfer_domain">Transfer domain</label>
                                <select name="transfer_domain" id="legacy_transfer_domain" class="form-select">
                                    <option value="1" {{ old('transfer_domain') == '1' ? 'selected' : '' }}>Yes</option>
                                    <option value="0" {{ old('transfer_domain') == '0' ? 'selected' : '' }}>No</option>
                                </select>
                            </div>
                       
                            <div class="col-md-6">
                                <label class="form-label" for="legacy_domain_name">Domain name <span class="text-danger">*</span></label>
                                <input type="text" name="domain_name" id="legacy_domain_name" class="form-control"
                                    value="{{ old('domain_name') }}" placeholder="example.com" required autocomplete="off">
                            </div>


                            <div class="col-md-6">
                                <label class="form-label" for="legacy_order_category">Status <span class="text-danger">*</span></label>
                                <select name="order_category" id="legacy_order_category" class="form-select" required>
                                    <option value="">— Order Category —</option>
                                    <option value="new" {{ old('order_category') == 'new' ? 'selected' : '' }}>New</option>
                                    <option value="renewal" {{ old('order_category') == 'renewal' ? 'selected' : '' }}>Renewal</option>
                                    <option value="upgrade" {{ old('order_category') == 'upgrade' ? 'selected' : '' }}>Upgrade</option>
                                    <option value="downgrade" {{ old('order_category') == 'downgrade' ? 'selected' : '' }}>Downgrade</option>
                                </select>
                            </div>
                       
                            <div class="col-md-3">
                                <label class="form-label" for="subscription_start_date">Subscription start</label>
                                <input type="date" name="subscription_start_date" id="subscription_start_date" class="form-control"
                                    value="{{ old('subscription_start_date', \Carbon\Carbon::now()->format('Y-m-d')) }}" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="subscription_end_date">Subscription end</label>
                                <input type="date" name="subscription_end_date" id="subscription_end_date" class="form-control"
                                    value="{{ old('subscription_end_date', \Carbon\Carbon::now()->addYear()->format('Y-m-d')) }}" readonly required>
                            </div>
                            <script>
                                document.addEventListener('DOMContentLoaded', function () {
                                    const startInput = document.getElementById('subscription_start_date');
                                    const endInput = document.getElementById('subscription_end_date');
                                    function setEndDate() {
                                        if (startInput.value) {
                                            const startDate = new Date(startInput.value);
                                            let endDate = new Date(startDate);
                                            endDate.setFullYear(endDate.getFullYear() + 1);
                                            let month = String(endDate.getMonth() + 1).padStart(2, '0');
                                            let day = String(endDate.getDate()).padStart(2, '0');
                                            let formatted = endDate.getFullYear() + '-' + month + '-' + day;
                                            endInput.value = formatted;
                                        }
                                    }
                                    startInput.addEventListener('change', setEndDate);
                                    setEndDate();
                                });
                            </script>
            

                            <div class="col-md-6">
                                <label class="form-label" for="legacy_provider_id">Provider <span class="text-danger">*</span></label>
                                <select name="provider_id" id="legacy_provider_id" class="form-select" required>
                                    <option value="">— Select provider —</option>
                                    @foreach($providers as $p)
                                        <option value="{{ $p->id }}" {{ old('provider_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">One provider per order. Add multiple plan rows below.</small>
                            </div>

                            <!-- Coupon Add Start -->
                            <div class="col-md-6">
                                <label class="form-label" for="legacy_coupon_code">Coupon Code</label>
                                <div class="input-group">
                                    <input type="text" name="coupon_code" id="legacy_coupon_code" class="form-control" 
                                           value="{{ old('coupon_code') }}" placeholder="Enter coupon code" autocomplete="off">
                                    <button type="button" class="btn btn-outline-primary" id="legacy_apply_coupon_btn">Apply</button>
                                </div>
                                <small id="legacy_coupon_message" class="text-success small"></small>
                                <small id="legacy_coupon_error" class="text-danger small"></small>
                                <input type="hidden" name="coupon_discount" id="legacy_coupon_discount" value="0">
                            </div>
                            <!-- Coupon Add End -->

                            <div class="col-12 d-none" id="legacy_plans_wrap">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <label class="form-label mb-0 fw-semibold">Plans</label>
                                    <button type="button" class="Plus-Icon" id="legacy_add_plan_row_btn" title="Add plan row">+</button>
                                </div>
                                <div id="legacy_plan_rows"></div>
                                <div class="d-flex justify-content-end align-items-center pt-1">
                                    <span class="fw-bold text-end me-2">
                                        Subtotal
                                    </span>
                                    <span class="fw-bold fs-6 text-end">
                                        ₹ <span id="legacy_subtotal">0.00</span>
                                    </span>
                                </div>
                                <div class="d-flex justify-content-end align-items-center pt-1">
                                    <label for="legacy_gst_checkbox" class="fw-bold text-end me-2 mb-0">
                                        Apply GST?
                                    </label>
                                    <input type="checkbox" id="legacy_gst_checkbox" class="form-check-input mx-2" checked>
                                    <span class="text-muted">(Yes/No)</span>
                                </div>
                                <!-- GST CALCULATION BY GST CHECK LOGIC -->
                                <div class="d-flex justify-content-end align-items-center pt-1" id="legacy_gst_row" style="display:none;">
                                    <span class="fw-bold text-end me-2">
                                        GST (<span id="legacy_gst_rate">18</span>%)
                                    </span>
                                    <span class="fw-bold fs-6 text-end">
                                        ₹ <span id="legacy_gst_amount">0.00</span>
                                    </span>
                                </div>

                                <!-- Coupon Discount Row -->
                                <div class="d-flex justify-content-end align-items-center pt-1" id="legacy_coupon_discount_row" style="display:none;">
                                    <span class="fw-bold text-end me-2">
                                        Coupon Discount
                                    </span>
                                    <span class="fw-bold fs-6 text-end text-success">
                                        − ₹ <span id="legacy_coupon_discount_amount">0.00</span>
                                    </span>
                                </div>
                                <!-- Coupon Discount Row End -->

                                <div class="d-flex justify-content-end align-items-center pt-1">
                                    <span class="fw-bold text-end me-2">
                                        Grand total <span id="legacy_gt_gst_hint">(incl. GST)</span>
                                    </span>
                                    <span class="fw-bold fs-5 text-success text-end">₹ <span id="legacy_grand_total">0.00</span></span>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label" for="aadhaar_number">Aadhaar number</label>
                                <input type="text" name="aadhaar_number" id="aadhaar_number" class="form-control" maxlength="14"
                                    value="{{ old('aadhaar_number') }}" placeholder="12 digits (stored only)">
                            </div>
                        </div>

                        <div class="mt-4 d-flex justify-content-end gap-2">
                            <a href="{{ $ordersIndexUrl }}" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary px-4" id="legacy_save_btn">Save &amp; generate PO</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="legacyAddPlanModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title">Add New Plan / Variant</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="legacy_quick_plan_alert" class="alert alert-danger d-none py-2 small mb-3"></div>
                    <div class="mb-3">
                        <label class="form-label">Provider</label>
                        <select id="modal_provider_id" class="form-select" disabled>
                            @foreach($providers as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Plan / Variant name <span class="text-danger">*</span></label>
                        <input type="text" id="modal_variant_name" class="form-control">
                    </div>
                    <div class="mb-0 d-none" id="modal_sub_variant_wrap">
                        <label class="form-label">Sub-variant details</label>
                        <input type="text" id="modal_sub_variant_name" class="form-control">
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="legacy_quick_plan_save_btn">Save &amp; Apply</button>
                </div>
            </div>
        </div>
    </div>
    </div>
@endsection

@push('scripts')
<script>
$(function () {
    var custList = @json($orderFormCustomers ?? []);
    var partnerCol = @json($customersPartnerColumn ?? false);
    var customerAddOpen = @json($partnerCustomerAddOpenUrl ?? '');
    var customerAddTpl = @json($partnerCustomerAddUrlTemplate ?? '');
    var partnerEnc = @json($partnerEncryptedById ?? []);
    var hasVariantByProvider = @json($hasVariantByProvider ?? []);
    var mainVariantsByProvider = @json($mainVariantsByProvider ?? []);
    var subVariantsByMain = @json($subVariantsByMain ?? []);
    var plansByProviderUrl = @json($plansByProviderUrl ?? '');
    var planSaveUrlTpl = @json($planSaveUrlTemplate ?? '');
    var csrfToken = @json(csrf_token());
    var oldPlans = @json(old('plans', []));

    var $providerSel = $('#legacy_provider_id');
    var $rowsWrap = $('#legacy_plan_rows');
    var $plansSection = $('#legacy_plans_wrap');
    var addPlanModal = null;
    var rowSeq = 0;
    var activeRowUid = null;

    // Coupon Variables
    var couponAmt = 0;
    var couponType = '';
    var couponApplied = false;

    function providerId() {
        var v = $providerSel.val();
        return v ? parseInt(v, 10) : 0;
    }

    function providerHasVariants(pid) {
        return !!(typeof hasVariantByProvider[pid] !== 'undefined' ? hasVariantByProvider[pid] : hasVariantByProvider[String(pid)]);
    }

    function renderCustomers() {
        var pid = parseInt($('#legacy_partner_id').val(), 10) || 0;
        var $sel = $('#legacy_user_id');
        var prev = $sel.val();
        $sel.find('option:not(:first)').remove();
        if (!pid) {
            $sel.prop('disabled', true).find('option:first').text('Select partner first');
            $('#legacy_add_customer_btn').attr('href', customerAddOpen);
            return;
        }
        var enc = partnerEnc[pid] || partnerEnc[String(pid)] || '';
        $('#legacy_add_customer_btn').attr('href', enc ? customerAddTpl.replace('__ENCRYPTED__', encodeURIComponent(enc)) : customerAddOpen);
        custList.filter(function (c) {
            return (c.source || '') === 'b2b' && (!partnerCol || parseInt(c.partner_id, 10) === pid);
        }).forEach(function (c) {
            $sel.append($('<option>').val(c.id).text(c.name));
        });
        $sel.prop('disabled', false).find('option:first').text('— Select customer —');
        if (prev) $sel.val(prev);
    }
    function recalcGrandTotal() {
        var subtotal = 0;
        $rowsWrap.find('.legacy-plan-row').each(function () {
            var price = parseFloat($(this).find('.legacy-line-price').val()) || 0;
            var qty = parseInt($(this).find('.legacy-line-qty').val(), 10) || 1;
            var rowTotal = price * qty;
            subtotal += rowTotal;
            $(this).find('.legacy-line-total').text(rowTotal.toFixed(2));
        });

        $('#legacy_subtotal').text(subtotal.toFixed(2));
        var gstPercent = 18;
        var applyGst = $('#legacy_gst_checkbox').is(':checked');
        var gstAmount = 0;
        var grandTotal = subtotal;

        if (applyGst) {
            gstAmount = (subtotal * gstPercent) / 100;
            grandTotal = subtotal + gstAmount;
            $('#legacy_gst_row').show();
            $('#legacy_gt_gst_hint').text('(incl. GST)');
        } else {
            $('#legacy_gst_row').hide();
            $('#legacy_gt_gst_hint').text('');
        }
        $('#legacy_gst_amount').text(gstAmount.toFixed(2));

        var couponDiscount = couponAmt || 0;
        if (couponType == 'percentage') {
            couponDiscount = ((grandTotal) * couponAmt / 100);
        }
        if (couponDiscount > 0 && couponApplied) {
            $('#legacy_coupon_discount_row').show();
            $('#legacy_coupon_discount_amount').text(couponDiscount.toFixed(2));
        } else {
            $('#legacy_coupon_discount_row').hide();
            $('#legacy_coupon_discount_amount').text('0.00');
        }
        $('#legacy_coupon_discount').val(couponDiscount.toFixed(2));

        var finalTotal = grandTotal;
        if (couponDiscount > 0 && couponApplied) {
            finalTotal = Math.max(0, grandTotal - couponDiscount);
        }
        $('#legacy_grand_total').text(finalTotal.toFixed(2));
        $('#legacy_amount_hidden').val(finalTotal.toFixed(2));
    }
    function recalcRow($row) {
        var price = parseFloat($row.find('.legacy-line-price').val()) || 0;
        var qty = parseInt($row.find('.legacy-line-qty').val(), 10) || 1;
        if (qty < 1) qty = 1;
        $row.find('.legacy-line-qty').val(qty);
        $row.find('.legacy-line-total').text((price * qty).toFixed(2));
        recalcGrandTotal();
    }

    function variantIdForRow($row) {
        var pid = providerId();
        if (!providerHasVariants(pid)) return 0;
        var sub = parseInt($row.find('.legacy-sub-variant').val(), 10) || 0;
        if (sub > 0) return sub;
        return parseInt($row.find('.legacy-main-variant').val(), 10) || 0;
    }

    function fillSubVariants($row) {
        var $sub = $row.find('.legacy-sub-variant');
        var mainId = parseInt($row.find('.legacy-main-variant').val(), 10) || 0;
        var subs = subVariantsByMain[mainId] || subVariantsByMain[String(mainId)] || [];
        var prev = $sub.val();
        $sub.find('option:not(:first)').remove();
        subs.forEach(function (s) {
            $sub.append($('<option>').val(s.id).text(s.name));
        });
        $row.find('.legacy-sub-variant-wrap').toggleClass('d-none', subs.length === 0);
        if (prev && $sub.find('[value="' + prev + '"]').length) $sub.val(prev);
    }

    function loadPlansForRow($row, selectPlanId) {
        var pid = providerId();
        var vid = variantIdForRow($row);
        var $plan = $row.find('.legacy-line-plan');
        if (!pid) return;
        $plan.prop('disabled', true).find('option:first').text('Loading…');
        $.getJSON(plansByProviderUrl, { provider_id: pid, variant_id: vid })
            .done(function (res) {
                var prev = $plan.val();
                $plan.find('option:not(:first)').remove();
                (res.plans || []).forEach(function (p) {
                    $plan.append($('<option>').val(p.id).text(p.name));
                });
                $plan.prop('disabled', false).find('option:first').text('— Search plan —');
                var pick = selectPlanId || prev;
                if (pick && $plan.find('[value="' + pick + '"]').length) $plan.val(String(pick));
            })
            .fail(function () {
                $plan.find('option:first').text('Could not load');
                $plan.prop('disabled', false);
            });
    }

    function buildPlanRow(index, data) {
        rowSeq++;
        var uid = 'r' + rowSeq;
        var pid = providerId();
        var hasVar = providerHasVariants(pid);
        var mains = mainVariantsByProvider[pid] || mainVariantsByProvider[String(pid)] || [];

        var html = '<div class="legacy-plan-row border rounded-3 p-3 mb-3 bg-light-subtle" data-uid="' + uid + '">';
        if (index > 0) {
            html += '<button type="button" class="btn btn-sm btn-link text-muted btn-remove-row" title="Remove">&times;</button>';
        }
        html += '<div class="row g-3 align-items-end">';
        if (hasVar) {
            html += '<div class="col-md-6 col-lg-4 legacy-main-variant-wrap"><label class="form-label small text-muted mb-1">Main Variant <span class="text-danger">*</span></label>';
            html += '<select class="form-select legacy-main-variant"><option value="">Search main variant…</option>';
            if (mains && mains.length) {
                mains.forEach(function (m) { html += '<option value="' + m.id + '">' + $('<div/>').text(m.name).html() + '</option>'; });
            }
            html += '</select></div>';
            html += '<div class="col-md-6 col-lg-4 legacy-sub-variant-wrap d-none"><label class="form-label small text-muted mb-1">Sub Variant</label>';
            html += '<select class="form-select legacy-sub-variant"><option value="">— Sub variant —</option></select></div>';
        }
        var planCol = hasVar ? 'col-md-6 col-lg-4' : 'col-md-6 col-lg-5';
        html += '<div class="' + planCol + '"><div class="d-flex justify-content-between align-items-center mb-1">';
        html += '<label class="form-label small text-muted mb-0">Plan <span class="text-danger">*</span></label>';
        html += '<button type="button" class="Plus-Icon legacy-row-add-plan" title="Add plan">+</button></div>';
        html += '<select class="form-select legacy-line-plan" name="plans[' + index + '][plan_id]" required disabled><option value="">Select provider first</option></select></div>';
        html += '<div class="col-6 col-md-3 col-lg-2"><label class="form-label small text-muted mb-1">Price (₹)</label>';
        html += '<input type="number" class="form-control legacy-line-price" name="plans[' + index + '][unit_price]" step="0.01" min="0" value="0" required></div>';
        html += '<div class="col-6 col-md-3 col-lg-2"><label class="form-label small text-muted mb-1">License</label>';
        html += '<div class="input-group input-group-sm"><button type="button" class="btn btn-outline-secondary legacy-qty-minus">−</button>';
        html += '<input type="number" class="form-control text-center legacy-line-qty" name="plans[' + index + '][quantity]" min="1" value="1" required>';
        html += '<button type="button" class="btn btn-outline-secondary legacy-qty-plus">+</button></div></div>';
        html += '<div class="col-md-6 col-lg-3"><label class="form-label small text-muted mb-1">Total</label>';
        html += '<div class="fw-bold fs-5">₹ <span class="legacy-line-total">0.00</span></div></div>';
        html += '</div></div>';

        var $row = $(html);
        $rowsWrap.append($row);

        if (data) {
            if (data.main_variant_id) $row.find('.legacy-main-variant').val(String(data.main_variant_id));
            fillSubVariants($row);
            if (data.sub_variant_id) $row.find('.legacy-sub-variant').val(String(data.sub_variant_id));
            if (typeof data.unit_price !== 'undefined') $row.find('.legacy-line-price').val(data.unit_price);
            if (data.quantity) $row.find('.legacy-line-qty').val(data.quantity);
            loadPlansForRow($row, data.plan_id || null);
        } else if (pid) {
            if (hasVar && mains.length === 1) {
                $row.find('.legacy-main-variant').val(String(mains[0].id));
                fillSubVariants($row);
            }
            loadPlansForRow($row);
        }

        recalcRow($row);
        reindexPlanRows();
        return $row;
    }

    function reindexPlanRows() {
        $rowsWrap.find('.legacy-plan-row').each(function (i) {
            $(this).find('.legacy-line-plan').attr('name', 'plans[' + i + '][plan_id]');
            $(this).find('.legacy-line-price').attr('name', 'plans[' + i + '][unit_price]');
            $(this).find('.legacy-line-qty').attr('name', 'plans[' + i + '][quantity]');
        });
    }

    function resetPlanRows() {
        $rowsWrap.empty();
        rowSeq = 0;
        if (providerId()) {
            buildPlanRow(0);
            $plansSection.removeClass('d-none');
        } else {
            $plansSection.addClass('d-none');
        }
        recalcGrandTotal();
    }

    $providerSel.on('change', function () {
        $('#modal_provider_id').val($(this).val() || '');
        var pid = providerId();
        $('#modal_sub_variant_wrap').toggleClass('d-none', !providerHasVariants(pid));
        resetPlanRows();
    });

    $('#legacy_add_plan_row_btn').on('click', function () {
        if (!providerId()) return;
        buildPlanRow($rowsWrap.find('.legacy-plan-row').length);
    });

    $rowsWrap.on('click', '.btn-remove-row', function () {
        $(this).closest('.legacy-plan-row').remove();
        if (!$rowsWrap.find('.legacy-plan-row').length) buildPlanRow(0);
        reindexPlanRows();
        recalcGrandTotal();
    });

    $rowsWrap.on('change', '.legacy-main-variant', function () {
        var $row = $(this).closest('.legacy-plan-row');
        fillSubVariants($row);
        loadPlansForRow($row);
    });
    $rowsWrap.on('change', '.legacy-sub-variant', function () {
        loadPlansForRow($(this).closest('.legacy-plan-row'));
    });
    $rowsWrap.on('input change', '.legacy-line-price, .legacy-line-qty', function () {
        recalcRow($(this).closest('.legacy-plan-row'));
    });
    $rowsWrap.on('click', '.legacy-qty-minus', function () {
        var $q = $(this).closest('.legacy-plan-row').find('.legacy-line-qty');
        $q.val(Math.max(1, (parseInt($q.val(), 10) || 1) - 1)).trigger('change');
    });
    $rowsWrap.on('click', '.legacy-qty-plus', function () {
        var $q = $(this).closest('.legacy-plan-row').find('.legacy-line-qty');
        $q.val((parseInt($q.val(), 10) || 1) + 1).trigger('change');
    });

    $rowsWrap.on('click', '.legacy-row-add-plan', function () {
        activeRowUid = $(this).closest('.legacy-plan-row').data('uid');
        $('#legacy_quick_plan_alert').addClass('d-none');
        $('#modal_variant_name, #modal_sub_variant_name').val('');
        if (!addPlanModal) addPlanModal = new bootstrap.Modal(document.getElementById('legacyAddPlanModal'));
        addPlanModal.show();
    });

    function firstPlanSaveError(res) {
        if (!res) return 'Could not save plan.';
        var keys = ['name', 'slug', 'price', 'message'];
        for (var i = 0; i < keys.length; i++) {
            if (res[keys[i]]) return res[keys[i]];
        }
        return 'Could not save plan.';
    }

    $('#legacy_quick_plan_save_btn').on('click', function () {
        var $btn = $(this).prop('disabled', true);
        var $alert = $('#legacy_quick_plan_alert');
        var pid = providerId();
        if (!pid) {
            $alert.removeClass('d-none').text('Select provider on the form first.');
            $btn.prop('disabled', false);
            return;
        }
        $.post(planSaveUrlTpl.replace('__PID__', pid), {
            _token: csrfToken,
            legacy_manual: 1,
            variant_name: $('#modal_variant_name').val(),
            sub_variant_name: $('#modal_sub_variant_name').val()
        }).done(function (res) {
            if (res.error) {
                $alert.removeClass('d-none').text(firstPlanSaveError(res));
                $btn.prop('disabled', false);
                return;
            }
            if (!res.success) {
                $alert.removeClass('d-none').text(res.message || 'Save failed.');
                $btn.prop('disabled', false);
                return;
            }
            var newId = parseInt(res.plan_id || 0, 10);
            var $row = activeRowUid ? $rowsWrap.find('[data-uid="' + activeRowUid + '"]') : $rowsWrap.find('.legacy-plan-row').first();
            loadPlansForRow($row, newId);
            if (addPlanModal) addPlanModal.hide();
            $btn.prop('disabled', false);
        }).fail(function (xhr) {
            $alert.removeClass('d-none').text(firstPlanSaveError(xhr.responseJSON));
            $btn.prop('disabled', false);
        });
    });

    // Coupon: Apply logic using Promocode.php logic (first 1-50 lines: code, discount_percent, status)
// Coupon Apply
// Coupon Apply
$('#legacy_apply_coupon_btn').on('click', function () {

let code = $('#legacy_coupon_code').val().trim();

$('#legacy_coupon_message').text('');
$('#legacy_coupon_error').text('');

couponApplied = false;
couponAmt = 0;
couponType = '';

if (!code) {
    $('#legacy_coupon_error').text('Please enter coupon code.');
    recalcGrandTotal();
    return;
}

$.ajax({
    url: '{{ route("superadmin.promotions_coupons.list") }}',
    type: 'GET',
    dataType: 'json',

    success: function (res) {

        console.log('Coupon API Response:', res);

        let coupons = [];

        // Handle response structure
        if (Array.isArray(res)) {
            coupons = res;
        } else if (res.data && Array.isArray(res.data)) {
            coupons = res.data;
        } else if (res.coupons && Array.isArray(res.coupons)) {
            coupons = res.coupons;
        }

        if (!coupons.length) {
            $('#legacy_coupon_error')
                .text('No coupon data found.');
            return;
        }

        // Find matching coupon
        let couponData = coupons.find(item => {

            let dbCode = (item.code || '')
                .toString()
                .trim()
                .toLowerCase();

            return dbCode === code.toLowerCase();
        });

        if (!couponData) {
            $('#legacy_coupon_error')
                .text('Invalid coupon code.');
            recalcGrandTotal();
            return;
        }

        // Active status check
        let status = String(couponData.status).toLowerCase();

        if (
            status === '1' ||
            status === 'active'
        ) {

            couponApplied = true;

            couponAmt = parseFloat(
                couponData.discount_percent ??
                couponData.discount ??
                0
            );

            couponType = 'percentage';

            $('#legacy_coupon_message')
                .text(
                    'Coupon applied successfully (' +
                    couponAmt +
                    '% discount)'
                );

            $('#legacy_coupon_error').text('');

        } else {

            $('#legacy_coupon_error')
                .text('Coupon expired or inactive.');
        }

        recalcGrandTotal();
    },

    error: function (xhr) {

        console.log(xhr.responseText);

        $('#legacy_coupon_error')
            .text('Coupon API error.');

        recalcGrandTotal();
    }
});
});
    $('#legacy_coupon_code').on('input', function () {
        couponApplied = false;
        couponAmt = 0;
        couponType = '';
        $('#legacy_coupon_message').text('');
        $('#legacy_coupon_error').text('');
        $('#legacy_coupon_discount_row').hide();
        recalcGrandTotal();
    });

    $('#legacy_partner_id').on('change', renderCustomers);
    renderCustomers();

    if ($providerSel.val()) {
        $('#modal_provider_id').val($providerSel.val());
        if (oldPlans && oldPlans.length) {
            $plansSection.removeClass('d-none');
            oldPlans.forEach(function (line, i) { buildPlanRow(i, line); });
        } else {
            resetPlanRows();
        }
    }
});
</script>
@endpush
