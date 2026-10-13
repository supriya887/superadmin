@php
    use Carbon\Carbon;

    $fieldsDisabled = !empty($disabled);
    $multiPlan = !empty($multiPlan);
    $orderRef = $order ?? null;
    $subStart = old('subscription_start_date');
    $subEnd = old('subscription_end_date');
    if ($subStart === null && $orderRef) {
        foreach (['subscription_start_date', 'subscription_start'] as $col) {
            if (!empty($orderRef->{$col})) {
                try {
                    $subStart = Carbon::parse($orderRef->{$col})->format('Y-m-d');
                    break;
                } catch (\Throwable $e) {
                }
            }
        }
    }
    if ($subEnd === null && $orderRef) {
        foreach (['subscription_end_date', 'subscription_end'] as $col) {
            if (!empty($orderRef->{$col})) {
                try {
                    $subEnd = Carbon::parse($orderRef->{$col})->format('Y-m-d');
                    break;
                } catch (\Throwable $e) {
                }
            }
        }
    }
    if ($subStart === null) {
        $subStart = now()->format('Y-m-d');
    }
    if ($subEnd === null) {
        $subEnd = now()->addYear()->format('Y-m-d');
    }
@endphp

@if($multiPlan)
<div x-show="partnerId" x-cloak>
    <div class="mb-3">
        <label class="form-label" for="user_id">Customer <span class="text-danger">*</span></label>
        <select name="user_id" id="user_id" class="form-select b2b-customer-select" required>
            <option value="">— Select customer —</option>
        </select>
        <small id="error-user_id" class="text-danger"></small>
    </div>

    <div class="mb-3">
        <label class="form-label">Domain <span class="text-danger">*</span></label>
        <input type="text" name="domain_name" id="domain_name" class="form-control" 
               x-model="domain" 
               @keyup.debounce.500ms="checkDomain()" 
               placeholder="example.com" 
               list="b2b_previous_domains_list" 
               required>
        <div class="small mt-1" x-show="domainMsg" x-text="domainMsg" :class="domainOk ? 'text-success' : 'text-danger'"></div>
        <small id="error-domain_name" class="text-danger"></small>
        <datalist id="b2b_previous_domains_list"></datalist>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <label class="form-label" for="subscription_start_date">Subscription start date</label>
            <input type="date" name="subscription_start_date" id="subscription_start_date" class="form-control"
                value="{{ $subStart }}" required>
            <small id="error-subscription_start_date" class="text-danger"></small>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="subscription_end_date">Subscription end date</label>
            <input type="date" name="subscription_end_date" id="subscription_end_date" class="form-control"
                value="{{ $subEnd }}" required>
            <small id="error-subscription_end_date" class="text-danger"></small>
        </div>
        {{-- ✅ ADD STATUS DROPDOWN HERE --}}
        <div class="col-md-4">
            <label class="form-label" for="wizard_order_status">Order status</label>
            <select id="wizard_order_status" class="form-select" name="status">
                @if (!$wizardOrderIsActive && !$wizardOrderIsProcessing)
                    <option value="pending" @selected(($wizardOrderStatus ?? 'pending') === 'pending')>Pending</option>
                @endif
                @if (($wizardOrderStatus ?? '') === 'processing' || !$wizardOrderIsActive)
                    <option value="processing" @selected(($wizardOrderStatus ?? '') === 'processing')>Processing</option>
                @endif
                <option value="completed" @selected(($wizardOrderStatus ?? '') === 'completed')>Active</option>
                <option value="rejected" @selected(($wizardOrderStatus ?? '') === 'rejected')>Cancelled</option>
            </select>
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">Provider <span class="text-danger">*</span></label>
        <select class="form-select b2b-provider-select" id="provider_id" required>
            <option value="">— Select provider —</option>
            @foreach($providers as $p)
                <option value="{{ $p->id }}">{{ $p->name }}</option>
            @endforeach
        </select>
        <input type="hidden" name="provider_id" :value="providerId || ''">
        <small id="error-provider_id" class="text-danger"></small>
        <p class="small text-muted mb-0 mt-1">One provider per order. Add multiple plan rows below.</p>
    </div>
</div>

<div x-show="providerId" x-cloak>
    <div class="d-flex align-items-center justify-content-between mb-2">
        <label class="form-label mb-0 fw-semibold">Plans</label>
        <button type="button" class="btn btn-sm btn-outline-primary rounded-circle d-inline-flex align-items-center justify-content-center"
            style="width:2rem;height:2rem;" @click="addPlanLine()" :disabled="!providerId" title="Add plan">+</button>
    </div>

    <template x-for="(line, index) in planLines" :key="line.uid">
        <div class="border rounded-3 p-3 mb-3 position-relative bg-light-subtle">
            <button type="button" class="btn btn-sm btn-link text-muted position-absolute top-0 end-0 mt-1 me-1"
                @click="removePlanLine(index)" x-show="planLines.length > 1" title="Remove plan">&times;</button>

            <p class="small text-muted mb-2" x-show="line.plansLoading">Loading plans…</p>

            <div class="row g-3 align-items-end">
                <div class="col-md-6 col-lg-4" x-show="providerHasMainVariants">
                    <label class="form-label small text-muted mb-1">Main Variant <span class="text-danger">*</span></label>
                    <select class="form-select b2b-main-variant-select"
                        :id="'b2b_main_variant_' + line.uid"
                        :data-line-uid="line.uid">
                        <option value="">— Select main variant —</option>
                    </select>
                </div>
                <div class="col-md-6 col-lg-4" x-show="lineShowSubVariant(line)">
                    <label class="form-label small text-muted mb-1">Sub Variant <span class="text-danger">*</span></label>
                    <select class="form-select b2b-sub-variant-select"
                        :id="'b2b_sub_variant_' + line.uid"
                        :data-line-uid="line.uid">
                        <option value="">— Select sub variant —</option>
                    </select>
                </div>
                <div class="col-md-6" :class="providerHasMainVariants ? 'col-lg-4' : 'col-lg-5'">
                    <label class="form-label small text-muted mb-1">Plan <span class="text-danger">*</span></label>
                    <select class="form-select b2b-plan-select"
                        :id="'b2b_plan_select_' + line.uid"
                        :name="'plans[' + index + '][plan_id]'"
                        :data-line-uid="line.uid"
                        :disabled="!lineScopeReady(line)"
                        required>
                        <option value="">— Search plan —</option>
                    </select>
                    <p class="small text-warning mb-0 mt-1" x-show="lineScopeReady(line) && !line.plansLoading && !(line.plans || []).length">
                        No plans for this variant.
                    </p>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label small text-muted mb-1">Price</label>
                    <div class="fw-semibold">₹ <span x-text="line.unitPrice.toFixed(2)"></span></div>
                    <div class="small text-muted">per user/year</div>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label small text-muted mb-1">License</label>
                    <div class="input-group input-group-sm">
                        <button type="button" class="btn btn-outline-secondary" @click="changeLineQty(index, -1)">−</button>
                        <input type="number" class="form-control text-center" min="1" step="1" inputmode="numeric"
                            :name="'plans[' + index + '][quantity]'" x-model.number="line.qty"
                            @input="recalcLine(index)" @blur="normalizeLineQty(index)">
                        <button type="button" class="btn btn-outline-secondary" @click="changeLineQty(index, 1)">+</button>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <label class="form-label small text-muted mb-1">Total</label>
                    <div class="fw-bold fs-5">₹ <span x-text="line.lineTotal.toFixed(2)"></span></div>
                </div>
            </div>
        </div>
    </template>
</div>

<div class="form-check mb-2" x-show="partnerId" x-cloak>
    <input class="form-check-input" type="checkbox" name="apply_gst" value="1" id="applyGstSa" x-model="applyGst" @change="recalc()">
    <label class="form-check-label" for="applyGstSa">Apply GST (18% default)</label>
</div>
<input type="hidden" name="gst_rate" :value="gstRate">
<input type="hidden" name="amount" :value="total > 0 ? total.toFixed(2) : '0.00'">

@else
<div class="row">
    <div class="mb-3 col-md-6">
        <label class="form-label">Customer</label>
        <select name="user_id" id="user_id" class="form-select" x-model="userId" required @if($fieldsDisabled) disabled @endif>
            <option value="">— Select —</option>
            @foreach($customers as $c)
                <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->email }})</option>
            @endforeach
        </select>
        <small id="error-user_id" class="text-danger"></small>
    </div>
    <div class="mb-3 col-md-6">
        <label class="form-label">Provider</label>
        <select class="form-select" id="provider_id" x-model="providerId" @change="loadVariants()" required @if($fieldsDisabled) disabled @endif>
            <option value="">— Select —</option>
            <template x-for="p in providers" :key="p.id">
                <option :value="p.id" x-text="p.name"></option>
            </template>
        </select>
        <input type="hidden" name="provider_id" :value="providerId || ''">
        <small id="error-provider_id" class="text-danger"></small>
    </div>
</div>
<div class="row g-2 mb-3">
    <div class="col-md-6">
        <label class="form-label">Variant</label>
        <select class="form-select" id="variant_id" x-model="variantId" @change="loadPlans()" :disabled="!variants.length || {{ $fieldsDisabled ? 'true' : 'false' }}">
            <option value="">— Select —</option>
            <template x-for="v in variants" :key="v.id">
                <option :value="v.id" x-text="v.name || v.title || ('Variant #' + v.id)"></option>
            </template>
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Plan</label>
        <select name="plan_id" id="plan_id" class="form-select" x-model="planId" @change="onPlanChange()" required @if($fieldsDisabled) disabled @endif>
            <option value="">— Select —</option>
            <template x-for="pl in plans" :key="pl.id">
                <option :value="pl.id" x-text="pl.name || pl.title"></option>
            </template>
        </select>
        <small id="error-plan_id" class="text-danger"></small>
    </div>
</div>
<div class="mb-3">
    <label class="form-label">Domain</label>
    <input type="text" name="domain_name" id="domain_name" class="form-control" x-model="domain" @blur.debounce.400ms="checkDomain()" placeholder="example.com" required @if($fieldsDisabled) disabled @endif>
    <div class="small mt-1" x-show="domainMsg" x-text="domainMsg" :class="domainOk ? 'text-success' : 'text-danger'"></div>
    <small id="error-domain_name" class="text-danger"></small>
</div>
<div class="row g-2 mb-3">
    <div class="col-md-6">
        <label class="form-label" for="subscription_start_date">Subscription start date</label>
        <input type="date" name="subscription_start_date" id="subscription_start_date" class="form-control"
            value="{{ $subStart }}" required @if($fieldsDisabled) disabled @endif>
        <small id="error-subscription_start_date" class="text-danger"></small>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="subscription_end_date">Subscription end date</label>
        <input type="date" name="subscription_end_date" id="subscription_end_date" class="form-control"
            value="{{ $subEnd }}" required @if($fieldsDisabled) disabled @endif>
        <small id="error-subscription_end_date" class="text-danger"></small>
    </div>
</div>
<div class="mb-3">
    <label class="form-label">Quantity</label>
    <input type="number" name="quantity" id="quantity" class="form-control" min="1" x-model.number="qty" @input="recalc()" required @if($fieldsDisabled) disabled @endif>
    <small id="error-quantity" class="text-danger"></small>
</div>
<div class="form-check mb-2">
    <input class="form-check-input" type="checkbox" name="apply_gst" value="1" id="applyGstSa" x-model="applyGst" @change="recalc()" @if($fieldsDisabled) disabled @endif>
    <label class="form-check-label" for="applyGstSa">Apply GST (18% default)</label>
</div>
<input type="hidden" name="gst_rate" :value="gstRate">
<input type="hidden" name="amount" :value="total > 0 ? total.toFixed(2) : '0.00'">
@endif
