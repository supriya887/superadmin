@extends('admin.layouts.app')

@section('title', 'B2B — New plan')

@push('styles')
<style>
    .b2b-flow { --b2b-blue:#0b5ed7; --b2b-deep:#062c66; --b2b-glow:rgba(11,94,215,.12); }
    .b2b-hero {
        background: linear-gradient(120deg, var(--b2b-deep) 0%, #0d6efd 45%, #6ea8fe 100%);
        color: #fff; border-radius: 1rem; padding: 1.75rem; box-shadow: 0 18px 40px var(--b2b-glow);
    }
    .b2b-tabs .nav-link { color: #0b3b82; font-weight: 600; }
    .b2b-tabs .nav-link.active { background: #fff; color: var(--b2b-blue); border-radius: .5rem .5rem 0 0; }
    .b2b-card { border: none; border-radius: 1rem; box-shadow: 0 8px 28px rgba(13,110,253,.08); }
    .b2b-summary { position: sticky; top: 1rem; }
</style>
@endpush

@section('content')
<div class="b2b-flow" x-data="b2bNewPlan({
    customers: @json($customers),
    providers: @json($providers),
    partnerId: {{ (int) $partner->id }},
    walletBalance: {{ number_format($walletBalance, 2, '.', '') }},
    hasPendingCreditRequest: @json($hasPendingCreditRequest),
    domainCheckUrl: @json(route('partner-admin.b2b.domain_check')),
    csrf: @json(csrf_token()),
})">
    <div class="b2b-hero mb-4">
        <h1 class="h4 mb-1">B2B — Add subscription order</h1>
        <p class="mb-0 opacity-75">Cinematic checkout · wallet balance <strong>₹<span x-text="Number(wallet).toLocaleString('en-IN',{minimumFractionDigits:2})"></span></strong></p>
    </div>

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card b2b-card">
                <div class="card-body">
                    <ul class="nav nav-tabs b2b-tabs mb-3" role="tablist">
                        <li class="nav-item"><button type="button" class="nav-link" :class="tab===1 && 'active'" @click="tab=1">Order</button></li>
                        <li class="nav-item"><button type="button" class="nav-link" :class="tab===2 && 'active'" @click="tab=2">PO details</button></li>
                        <li class="nav-item"><button type="button" class="nav-link" :class="tab===3 && 'active'" @click="tab=3">Aadhaar details</button></li>
                    </ul>

                    <form method="post" action="{{ route('partner-admin.b2b.new_plan.store') }}" @submit.prevent="submitForm">
                        @csrf
                        <input type="hidden" name="order_type" value="B2B">

                        <div x-show="tab===1" x-cloak>
                            <div class="mb-3">
                                <label class="form-label">Customer</label>
                                <select name="user_id" class="form-select" x-model="userId" required>
                                    <option value="">— Select —</option>
                                    @foreach($customers as $c)
                                        <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->email }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Provider</label>
                                <select class="form-select" x-model="providerId" @change="loadVariants()" required>
                                    <option value="">— Select —</option>
                                    <template x-for="p in providers" :key="p.id">
                                        <option :value="p.id" x-text="p.name"></option>
                                    </template>
                                </select>
                                <input type="hidden" name="provider_id" :value="providerId || ''">
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Variant</label>
                                    <select class="form-select" x-model="variantId" @change="loadPlans()" :disabled="!variants.length">
                                        <option value="">— Select —</option>
                                        <template x-for="v in variants" :key="v.id">
                                            <option :value="v.id" x-text="v.name || v.title || ('Variant #' + v.id)"></option>
                                        </template>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Plan</label>
                                    <select name="plan_id" class="form-select" x-model="planId" @change="onPlanChange()" required>
                                        <option value="">— Select —</option>
                                        <template x-for="pl in plans" :key="pl.id">
                                            <option :value="pl.id" x-text="pl.name || pl.title"></option>
                                        </template>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Domain</label>
                                <input type="text" name="domain_name" class="form-control" x-model="domain" @blur.debounce.400ms="checkDomain()" placeholder="example.com" required>
                                <div class="small mt-1" x-show="domainMsg" x-text="domainMsg" :class="domainOk ? 'text-success' : 'text-danger'"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Quantity</label>
                                <input type="number" name="quantity" class="form-control" min="1" x-model.number="qty" @input="recalc()" required>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="apply_gst" value="1" id="applyGst" x-model="applyGst" @change="recalc()">
                                <label class="form-check-label" for="applyGst">Apply GST (18% default)</label>
                            </div>
                            <input type="hidden" name="gst_rate" :value="gstRate">
                        </div>

                        <div x-show="tab===2" x-cloak>
                            <div class="row g-2">
                                <div class="col-md-6 mb-2"><label class="form-label">PO name</label><input class="form-control" name="po_name" x-model="po.name"></div>
                                <div class="col-md-6 mb-2"><label class="form-label">PO email</label><input class="form-control" name="po_email" type="email" x-model="po.email"></div>
                                <div class="col-md-6 mb-2"><label class="form-label">PO mobile</label><input class="form-control" name="po_mobile" x-model="po.mobile"></div>
                                <div class="col-md-6 mb-2"><label class="form-label">GSTIN</label><input class="form-control" name="po_gstin" x-model="po.gstin"></div>
                                <div class="col-12 mb-2"><label class="form-label">Company</label><input class="form-control" name="po_company_name" x-model="po.company"></div>
                                <div class="col-12 mb-2"><label class="form-label">Address</label><textarea class="form-control" name="po_company_address" rows="2" x-model="po.address"></textarea></div>
                                <div class="col-md-4 mb-2"><label class="form-label">City</label><input class="form-control" name="po_city" x-model="po.city"></div>
                                <div class="col-md-4 mb-2"><label class="form-label">State</label><input class="form-control" name="po_state" x-model="po.state"></div>
                                <div class="col-md-4 mb-2"><label class="form-label">Pincode</label><input class="form-control" name="po_pincode" x-model="po.pincode"></div>
                                <div class="col-md-6 mb-2"><label class="form-label">Country</label><input class="form-control" name="po_country" x-model="po.country"></div>
                            </div>
                        </div>

                        <div x-show="tab===3" x-cloak>
                            <label class="form-label">Aadhaar number</label>
                            <input class="form-control mb-2" name="aadhaar_number" maxlength="14" x-model="aadhaar" placeholder="12-digit Aadhaar">
                            <p class="small text-muted">Collected for PO / compliance. OTP flows can be completed separately via admin tools if required.</p>
                        </div>

                        <input type="hidden" name="status" value="completed">

                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <button type="button" class="btn btn-outline-secondary" @click="tab = Math.max(1, tab-1)" x-show="tab>1">Back</button>
                            <div>
                                <button type="button" class="btn btn-primary" @click="tab = Math.min(3, tab+1)" x-show="tab<3">Next</button>
                                <button type="submit" class="btn btn-success" x-show="tab===3"
                                    :disabled="hasPendingCreditRequest || submitting || !canSubmit">
                                    Continue purchase
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card b2b-card b2b-summary">
                <div class="card-body">
                    <h5 class="card-title text-primary">Order summary</h5>
                    <hr>
                    <div class="d-flex justify-content-between"><span>Subtotal</span><span>₹<span x-text="subtotal.toFixed(2)"></span></span></div>
                    <div class="d-flex justify-content-between"><span>GST</span><span>₹<span x-text="gst.toFixed(2)"></span></span></div>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold fs-5"><span>Total</span><span>₹<span x-text="total.toFixed(2)"></span></span></div>
                    <p class="small text-muted mt-3 mb-0" x-show="hasPendingCreditRequest">You have a pending credit request. Continue purchase unlocks after approval.</p>
                    <p class="small text-danger mt-2 mb-0" x-show="total > wallet && !hasPendingCreditRequest">Insufficient wallet for this total — submit will create a credit request.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
<script>
function b2bNewPlan(cfg) {
    return {
        tab: 1,
        userId: '',
        customers: cfg.customers,
        providers: cfg.providers,
        partnerId: cfg.partnerId,
        wallet: parseFloat(cfg.walletBalance) || 0,
        hasPendingCreditRequest: cfg.hasPendingCreditRequest,
        domainCheckUrl: cfg.domainCheckUrl,
        csrf: cfg.csrf,
        providerId: '',
        variantId: '',
        planId: '',
        variants: [],
        plans: [],
        selectedPlan: null,
        domain: '',
        domainMsg: '',
        domainOk: false,
        qty: 1,
        applyGst: true,
        gstRate: 18,
        unitPrice: 0,
        subtotal: 0,
        gst: 0,
        total: 0,
        aadhaar: '',
        submitting: false,
        po: { name:'', email:'', mobile:'', gstin:'', company:'', address:'', city:'', state:'', pincode:'', country:'India' },
        async loadVariants() {
            this.variantId = ''; this.planId = ''; this.plans = []; this.variants = []; this.selectedPlan = null; this.unitPrice = 0; this.recalc();
            if (!this.providerId) return;
            const res = await fetch('/api/v1/partner/providers-variants', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                body: JSON.stringify({ provider_id: Number(this.providerId) })
            });
            const j = await res.json();
            const block = j.data || {};
            const raw = block.variants || [];
            const list = [];
            for (const v of raw) {
                list.push(v);
                (v.children || []).forEach(c => list.push({ ...c, name: (v.name + ' › ' + c.name) }));
            }
            this.variants = list;
        },
        async loadPlans() {
            this.planId = ''; this.plans = []; this.selectedPlan = null; this.unitPrice = 0; this.recalc();
            if (!this.providerId || !this.variantId) return;
            const res = await fetch('/api/v1/partner/providers-plans', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                body: JSON.stringify({ provider_id: Number(this.providerId), variant_id: Number(this.variantId) })
            });
            const j = await res.json();
            const block = j.data || {};
            this.plans = Array.isArray(block.plans) ? block.plans : [];
        },
        onPlanChange() {
            const p = this.plans.find(pl => String(pl.id) === String(this.planId));
            this.selectedPlan = p || null;
            this.unitPrice = p ? parseFloat(p.price) || 0 : 0;
            this.recalc();
        },
        recalc() {
            this.subtotal = +(this.unitPrice * (this.qty || 1)).toFixed(2);
            this.gst = this.applyGst ? +(this.subtotal * (this.gstRate/100)).toFixed(2) : 0;
            this.total = +(this.subtotal + this.gst).toFixed(2);
        },
        needsDomainApi() {
            const pr = this.providers.find(p => String(p.id) === String(this.providerId));
            if (!pr) return false;
            const s = (pr.slug || pr.name || '').toLowerCase();
            return s.includes('microsoft') || s.includes('google') || s.includes('365') || s.includes('workspace');
        },
        async checkDomain() {
            this.domainMsg = '';
            const d = (this.domain || '').trim().toLowerCase();
            if (!d) return;
            if (this.needsDomainApi()) {
                try {
                    const u = this.domainCheckUrl + '?domain_name=' + encodeURIComponent(d);
                    const res = await fetch(u, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf } });
                    const j = await res.json();
                    const ok = !!(j.success && (j.available || j.registered));
                    this.domainOk = ok;
                    this.domainMsg = ok ? 'Domain valid (registered).' : (j.message || 'Domain invalid or not registered.');
                } catch (e) {
                    this.domainOk = false;
                    this.domainMsg = 'Could not verify domain.';
                }
            } else {
                const valid = /^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i.test(d);
                this.domainOk = valid;
                this.domainMsg = valid ? 'Domain format valid.' : 'Invalid domain format.';
            }
        },
        get canSubmit() {
            if (!this.userId || !this.planId || !this.domain) return false;
            if (this.needsDomainApi() && !this.domainOk) return false;
            if (!this.needsDomainApi() && !this.domainOk && this.domain) return false;
            return true;
        },
        async submitForm(e) {
            const form = e.target;
            this.submitting = true;
            try {
                const fd = new FormData(form);
                const res = await fetch(form.action, { method: 'POST', body: fd, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const j = await res.json().catch(() => ({}));
                if (res.ok && j.success) {
                    window.location = "{{ route('partner-admin.dashboard') }}";
                    return;
                }
                if (j.message) alert(j.message + (j.request_credits_url ? '\nRequest credits: ' + j.request_credits_url : ''));
                else alert('Could not place order.');
            } finally {
                this.submitting = false;
            }
        }
    };
}
</script>
@endsection
