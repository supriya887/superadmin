@extends('superadmin.layouts.crm')

@section('content')
@php
    $quotation = $quotation ?? null;
    $isEdit = !empty($quotation);
    $planRows = old('plans');
    if (!$planRows) {
        if (($services ?? collect())->count() && !$isEdit) {
            $planRows = $services->map(function ($s) {
                return [
                    'provider_id' => $s->provider_id,
                    'plan_id' => $s->plan_id,
                    'quote_period' => $s->quote_period ?: 'Yearly',
                    'sku' => $s->sku,
                    'quantity' => $s->quantity ?: 1,
                    'rate' => $s->rate,
                ];
            })->values()->all();
        } elseif ($isEdit && is_array($quotation->plans) && count($quotation->plans)) {
            $planRows = $quotation->plans;
        } else {
            $planRows = [['provider_id' => '', 'plan_id' => '', 'quote_period' => 'Yearly', 'sku' => '', 'quantity' => 1, 'rate' => '']];
        }
    }
    $dealTypeValue = old('deal_type', $quotation->deal_type ?? 'new') ?: 'new';
    $currentServices = $currentServices ?? collect();
    $domainTypeValue = old('domain_type', $lead->domain_type ?? '');
    if ($isEdit && $domainTypeValue === '') {
        if ((float) $quotation->domain_rate > 0) {
            $domainTypeValue = 'new';
        } elseif ($quotation->domain_name) {
            $domainTypeValue = 'existing';
        }
    }
@endphp

<div class="page-header">
    <div>
        <div class="breadcrumb welcome-text">
            <p>
                <a href="{{ route('crm.dashboard') }}">Dashboard</a>
                <span>/</span>
                <a href="{{ route('crm.quotations') }}">Quotations</a>
            </p>
        </div>
        <h2>{{ $isEdit ? 'Edit Quotations' : 'Add Quotations' }}</h2>
    </div>
    <div class="back">
        <a href="{{ route('crm.quotations') }}" class="back-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 12H5" />
                <path d="M12 19l-7-7 7-7" />
            </svg>
            <span>Back</span>
        </a>
    </div>
</div>

<form method="POST" action="{{ $isEdit ? route('crm.quotations.update', $quotation) : route('crm.quotations.store') }}" id="quotationForm" novalidate>
    @csrf
    @if($errors->any())
        <div class="alert alert-danger">
            <ul style="margin:0;padding-left:18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <input type="hidden" name="lead_id" value="{{ old('lead_id', $lead->id ?? $quotation->lead_id ?? '') }}">

    <section class="common-form">
        <h3>Quotation Detail</h3>

        <div class="form-grid">
            @php
                $selectedLeadId = (string) old('lead_id', optional($lead)->id ?? optional($quotation)->lead_id ?? '');
                $leadLocked = $selectedLeadId !== '';
                $dialCodes = \App\Models\Lead::orderedDialCodes();
                $quoteCode = old('mobile_code', optional($quotation)->mobile_code ?? optional($lead)->mobile_code ?? '91');
                $quoteSalutation = old('salutation', optional($quotation)->salutation ?? optional($lead)->salutation ?? '');
                $selectedLeadLabel = 'Select Lead';
                foreach ($leads ?? [] as $l) {
                    if ((string) $l->id === $selectedLeadId) {
                        $selectedLeadLabel = trim(($l->company_name ?: '') . ' — ' . ($l->name ?: ''), ' —') ?: $selectedLeadLabel;
                        break;
                    }
                }
            @endphp

            <div class="form-group">
                <label>Lead</label>
                <div class="loc-select" data-lookup="lead">
                    <button type="button" class="loc-select-btn {{ $selectedLeadId ? '' : 'is-placeholder' }}" id="leadSelectBtn">{{ $selectedLeadLabel }}</button>
                    <div class="loc-select-panel">
                        <input type="text" class="loc-select-search" placeholder="Search lead" autocomplete="off">
                        <div class="loc-select-list" id="leadLookupList"></div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Company Name <span>*</span></label>
                <input type="text" name="company_name" value="{{ old('company_name', $quotation->company_name ?? $lead->company_name ?? '') }}" required {{ $leadLocked ? 'readonly' : '' }}>
            </div>

            <div class="form-group">
                <label>GSTIN <span>*</span></label>
                <input type="text" name="gstin" value="{{ old('gstin', $quotation->gstin ?? $lead->gstin ?? '') }}" maxlength="15" required {{ $leadLocked ? 'readonly' : '' }}>
            </div>

            <div class="form-group">
                <label>Name <span>*</span></label>
                <div class="phone-group name-title-row">
                    <select name="salutation" class="form-control phone-code-select title-select" required {{ $leadLocked ? 'disabled' : '' }}>
                        <option value="">Title</option>
                        @foreach(['Mr', 'Mrs', 'Ms'] as $title)
                            <option value="{{ $title }}" {{ $quoteSalutation === $title ? 'selected' : '' }}>{{ $title }}</option>
                        @endforeach
                    </select>
                    @if($leadLocked)
                        <input type="hidden" name="salutation" value="{{ $quoteSalutation }}">
                    @endif
                    <input type="text" name="name" value="{{ old('name', $quotation->name ?? $lead->name ?? '') }}" required {{ $leadLocked ? 'readonly' : '' }}>
                </div>
            </div>

            <div class="form-group">
                <label>Email <span>*</span></label>
                @if($leadLocked)
                    <input type="email" name="email" id="quotationEmail" value="{{ old('email', $quotation->email ?? $lead->email ?? '') }}" required readonly>
                @else
                    <div class="loc-select" data-lookup="email">
                        <input type="email" name="email" id="quotationEmail" class="loc-select-input" value="{{ old('email', $quotation->email ?? $lead->email ?? '') }}" required autocomplete="off" placeholder="Select or type email">
                        <div class="loc-select-panel">
                            <div class="loc-select-list" id="emailLookupList"></div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="form-group">
                <label>Mobile</label>
                <div class="phone-group">
                    <select name="mobile_code" class="form-control phone-code-select" {{ $leadLocked ? 'disabled' : '' }}>
                        @foreach($dialCodes as $code => $country)
                            <option value="{{ $code }}" {{ (string) $quoteCode === (string) $code ? 'selected' : '' }}>+{{ $code }} {{ $country }}</option>
                        @endforeach
                    </select>
                    @if($leadLocked)
                        <input type="hidden" name="mobile_code" value="{{ $quoteCode }}">
                    @endif
                    @if($leadLocked)
                        <input type="tel" name="mobile" id="quotationMobile" value="{{ old('mobile', $lead->mobile ?? '') }}" readonly>
                    @else
                        <div class="loc-select" data-lookup="mobile" style="flex:1;min-width:0;">
                            <input type="tel" name="mobile" id="quotationMobile" class="loc-select-input" value="{{ old('mobile', $lead->mobile ?? '') }}" autocomplete="off" inputmode="numeric" maxlength="15" placeholder="Select or type number">
                            <div class="loc-select-panel">
                                <div class="loc-select-list" id="mobileLookupList"></div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="form-group">
                <label>Discount (in %) <span>*</span></label>
                <input type="number" name="discount" min="0" max="100" step="0.01" value="{{ old('discount', $quotation->discount ?? '') }}" required>
            </div>

            <div class="form-group">
                <label>Order Type <span>*</span></label>
                <select name="deal_type" id="dealType" class="form-control" required>
                    @foreach (\App\Models\Quotation::DEAL_TYPES as $value => $label)
                        <option value="{{ $value }}" {{ $dealTypeValue === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

        </div>

        <div id="domainSection">
        <h3 class="section-title">Domain Detail</h3>

        <div class="form-grid">

            <div class="form-group">
                <label>Domain option</label>
                <select name="domain_type" id="domainType" class="form-control">
                    <option value="">No domain</option>
                    <option value="existing" {{ $domainTypeValue === 'existing' ? 'selected' : '' }}>Already have domain</option>
                    <option value="new" {{ $domainTypeValue === 'new' ? 'selected' : '' }}>Need new domain</option>
                </select>
            </div>

            <div class="form-group domain-name-wrap" style="display:none;">
                <label>Domain Name</label>
                <input type="text" name="domain_name" id="domainName" value="{{ old('domain_name', $quotation->domain_name ?? $lead->domain ?? '') }}">
            </div>

            <div class="form-group domain-new-wrap" style="display:none;">
                <label>SAC</label>
                <input type="text" name="sac" value="{{ old('sac', $quotation->sac ?? $lead->sac ?? '') }}">
            </div>

            <div class="form-group domain-new-wrap" style="display:none;">
                <label>Domain Start</label>
                <div class="date-input">
                    <input type="date" name="domain_start" value="{{ old('domain_start', optional($quotation)->domain_start?->format('Y-m-d') ?: optional($lead)->domain_start?->format('Y-m-d')) }}">
                </div>
            </div>

            <div class="form-group domain-new-wrap" style="display:none;">
                <label>Domain End</label>
                <div class="date-input">
                    <input type="date" name="domain_end" value="{{ old('domain_end', optional($quotation)->domain_end?->format('Y-m-d') ?: optional($lead)->domain_end?->format('Y-m-d')) }}">
                </div>
            </div>

            <div class="form-group domain-new-wrap" style="display:none;">
                <label>Rate</label>
                <input type="text" name="domain_rate" value="{{ old('domain_rate', $quotation->domain_rate ?? $lead->domain_rate ?? '') }}">
            </div>

            <div class="form-group domain-new-wrap" style="display:none;">
                <label>&nbsp;</label>
                <button type="button" class="add-plan-btn" id="checkDomainBtn">Check Availability</button>
                <div id="domainCheckStatus"></div>
            </div>

        </div>
        </div>

        <div class="section-heading">

            <h3>Plan Detail</h3>

            <button type="button" class="add-plan-btn" id="addPlanBtn">
                + Add Plan
            </button>

        </div>

        <div id="plansWrapper" class="stack-list">
            @foreach ($planRows as $i => $row)
                <div class="plan-box">

                    <div class="plan-header">

                        <strong class="plan-number">Sr. No. {{ $i + 1 }}</strong>

                        <button
                            type="button"
                            class="remove-plan"
                            aria-label="Remove Plan">

                            <i data-lucide="x"></i>

                        </button>

                    </div>


                    <div class="form-grid form-grid-3">

                        <div class="form-group addon-only" style="display:none;">
                            <label>Current Plan <span>*</span></label>
                            <select class="form-control current-service-select" name="plans[{{ $i }}][from_service_id]" data-selected="{{ $row['from_service_id'] ?? '' }}"></select>
                        </div>

                        <div class="form-group provider-group">
                            <label>Provider <span>*</span></label>
                            <select
                                class="form-control provider-select"
                                name="plans[{{ $i }}][provider_id]"
                                required>
                                <option value="">Select Provider</option>
                                @foreach ($providers ?? [] as $provider)
                                    <option value="{{ $provider->id }}" {{ (string) ($row['provider_id'] ?? '') === (string) $provider->id ? 'selected' : '' }}>
                                        {{ $provider->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>


                        <div class="form-group plan-group">

                            <label><span class="plan-label">Plan</span> <span>*</span></label>

                            <select
                                class="form-control plan-select"
                                name="plans[{{ $i }}][plan_id]"
                                data-selected="{{ $row['plan_id'] ?? '' }}"
                                required>

                                <option value="">
                                    Select Plan
                                </option>

                            </select>

                        </div>


                        <div class="form-group period-group">

                            <label>Billing Cycle <span>*</span></label>

                            <select
                                class="form-control quote-period-select"
                                name="plans[{{ $i }}][quote_period]"
                                required>

                                <option value="">
                                    Select Billing Cycle
                                </option>
                                @php $periodValue = (string) ($row['quote_period'] ?? ''); @endphp
                                <option value="Monthly" {{ strcasecmp($periodValue, 'Monthly') === 0 ? 'selected' : '' }}>Monthly</option>
                                <option value="Quarterly" {{ strcasecmp($periodValue, 'Quarterly') === 0 ? 'selected' : '' }}>Quarterly</option>
                                <option value="Half Yearly" {{ strcasecmp($periodValue, 'Half Yearly') === 0 ? 'selected' : '' }}>Half Yearly</option>
                                <option value="Yearly" {{ strcasecmp($periodValue, 'Yearly') === 0 || $periodValue === '' ? 'selected' : '' }}>Yearly</option>
                                <option value="3 Yearly" {{ strcasecmp($periodValue, '3 Yearly') === 0 ? 'selected' : '' }}>3 Yearly</option>

                            </select>

                        </div>

                        <div class="form-group">
                            <label>Start Date</label>
                            <input type="date" name="plans[{{ $i }}][billing_start_date]" class="start-input" value="{{ $row['billing_start_date'] ?? '' }}">
                            <div class="form-hint end-hint"></div>
                        </div>


                        <div class="form-group sku-group">
                            <label>SKU <span>*</span></label>
                            <input type="text" name="plans[{{ $i }}][sku]" class="sku-input" value="{{ $row['sku'] ?? '' }}" required>
                        </div>


                        <div class="form-group">
                            <label><span class="qty-label">Quantity</span> <span>*</span></label>
                            <input type="number" name="plans[{{ $i }}][quantity]" class="qty-input" min="1" value="{{ $row['quantity'] ?? 1 }}" required>
                        </div>


                        <div class="form-group">
                            <label><span class="rate-label">Rate</span> <span>*</span></label>
                            <input type="text" name="plans[{{ $i }}][rate]" class="rate-input" value="{{ $row['annual_rate'] ?? $row['rate'] ?? '' }}" required>
                        </div>

                    </div>

                    <div class="form-hint addon-info" style="display:none;max-width:620px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:10px 14px;margin-top:12px;"></div>

                </div>
            @endforeach
        </div>

        <div class="submit-wrap">
            <button type="submit" class="submit-btn">
                {{ $isEdit ? 'Update Quotation' : 'Save Quotation' }}
            </button>
        </div>

    </section>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const quotationForm = document.getElementById('quotationForm');

    function clearFieldError(el) {
        if (!el) return;
        el.classList.remove('is-invalid');
        const wrap = el.closest('.form-group') || el.parentElement;
        if (!wrap) return;
        wrap.querySelectorAll('.js-field-error[data-for="' + el.name + '"]').forEach(function (err) { err.remove(); });
    }

    function showFieldError(el, msg) {
        if (!el) return;
        clearFieldError(el);
        el.classList.add('is-invalid');
        const wrap = el.closest('.form-group') || el.parentElement;
        if (!wrap) return;
        const err = document.createElement('div');
        err.className = 'form-error js-field-error';
        err.setAttribute('data-for', el.name);
        err.textContent = msg;
        wrap.appendChild(err);
    }

    function goToField(el) {
        if (!el) return;
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(function () { el.focus(); }, 200);
    }

    function namedField(form, name) {
        const all = form.querySelectorAll('[name="' + name + '"]');
        for (let i = 0; i < all.length; i++) {
            if (all[i].type === 'hidden' && String(all[i].value || '').trim()) {
                return all[i];
            }
        }
        return all[0] || null;
    }

    const quotationLeads = @json(($leads ?? collect())->values());
    function goToLead(id) {
        if ({{ $isEdit ? 'true' : 'false' }}) {
            return;
        }
        var url = "{{ route('crm.quotations.create') }}";
        window.location = id ? (url + '?lead_id=' + encodeURIComponent(id)) : url;
    }
    function digitsOnly(value) {
        return String(value || '').replace(/\D+/g, '');
    }
    function currentLeadId() {
        const el = document.querySelector('input[name="lead_id"]');
        return el ? String(el.value || '') : '';
    }
    function applyMatchedLead(match) {
        if (!match || String(match.id) === currentLeadId()) {
            return;
        }
        goToLead(match.id);
    }
    function leadLabel(lead) {
        return [lead.company_name, lead.name].filter(Boolean).join(' — ') || ('Lead #' + lead.id);
    }
    function uniqueOptions(rows) {
        const seen = {};
        return rows.filter(function (row) {
            const key = String(row.value || '').toLowerCase();
            if (!key || seen[key]) {
                return false;
            }
            seen[key] = true;
            return true;
        });
    }
    function emailOptions() {
        const rows = [];
        quotationLeads.forEach(function (lead) {
            if (lead.email) {
                rows.push({ value: lead.email, meta: leadLabel(lead), leadId: lead.id });
            }
            (lead.contacts || []).forEach(function (contact) {
                if (contact.email) {
                    rows.push({ value: contact.email, meta: leadLabel(lead), leadId: lead.id });
                }
            });
        });
        return uniqueOptions(rows);
    }
    function mobileOptions() {
        const rows = [];
        quotationLeads.forEach(function (lead) {
            if (lead.mobile) {
                rows.push({ value: lead.mobile, meta: leadLabel(lead), leadId: lead.id });
            }
            (lead.contacts || []).forEach(function (contact) {
                if (contact.mobile) {
                    rows.push({ value: contact.mobile, meta: leadLabel(lead), leadId: lead.id });
                }
            });
        });
        return uniqueOptions(rows);
    }
    function fillLookupList(list, rows) {
        if (!list) {
            return;
        }
        list.innerHTML = '';
        if (!rows.length) {
            const empty = document.createElement('div');
            empty.className = 'empty';
            empty.textContent = 'No matches';
            list.appendChild(empty);
            return;
        }
        rows.forEach(function (row) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.dataset.value = row.value;
            if (row.leadId) {
                btn.dataset.leadId = row.leadId;
            }
            btn.innerHTML = '<span class="opt-main"></span>' + (row.meta ? '<span class="opt-meta"></span>' : '');
            btn.querySelector('.opt-main').textContent = row.value;
            if (row.meta) {
                btn.querySelector('.opt-meta').textContent = row.meta;
            }
            list.appendChild(btn);
        });
    }
    function filterLookupList(list, query, rows) {
        const q = String(query || '').trim().toLowerCase();
        const filtered = rows.filter(function (row) {
            return !q || String(row.value).toLowerCase().indexOf(q) !== -1 || String(row.meta || '').toLowerCase().indexOf(q) !== -1;
        });
        fillLookupList(list, filtered);
    }
    function closeLookups(except) {
        document.querySelectorAll('#quotationForm .loc-select.open').forEach(function (wrap) {
            if (wrap !== except) {
                wrap.classList.remove('open');
            }
        });
    }
    const leadRows = quotationLeads.map(function (lead) {
        return { value: leadLabel(lead), meta: [lead.email, lead.mobile].filter(Boolean).join(' · '), leadId: lead.id };
    });
    const emailRows = emailOptions();
    const mobileRows = mobileOptions();
    fillLookupList(document.getElementById('leadLookupList'), leadRows);
    fillLookupList(document.getElementById('emailLookupList'), emailRows);
    fillLookupList(document.getElementById('mobileLookupList'), mobileRows);

    document.querySelectorAll('#quotationForm .loc-select').forEach(function (wrap) {
        const kind = wrap.getAttribute('data-lookup');
        const btn = wrap.querySelector('.loc-select-btn');
        const input = wrap.querySelector('.loc-select-input');
        const search = wrap.querySelector('.loc-select-search');
        const list = wrap.querySelector('.loc-select-list');
        const rows = kind === 'lead' ? leadRows : (kind === 'email' ? emailRows : mobileRows);

        function openPanel() {
            closeLookups(wrap);
            wrap.classList.add('open');
            if (search) {
                search.value = '';
                filterLookupList(list, '', rows);
                search.focus();
            } else if (input) {
                filterLookupList(list, input.readOnly ? '' : input.value, rows);
            }
        }

        wrap.addEventListener('click', function (e) {
            e.stopPropagation();
        });
        if (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                if (wrap.classList.contains('open')) {
                    wrap.classList.remove('open');
                    return;
                }
                openPanel();
            });
        }
        if (input) {
            input.addEventListener('focus', openPanel);
            input.addEventListener('click', openPanel);
            input.addEventListener('input', function () {
                if (input.readOnly) {
                    return;
                }
                wrap.classList.add('open');
                filterLookupList(list, this.value, rows);
            });
            input.addEventListener('blur', function () {
                if (input.readOnly) {
                    return;
                }
                const match = rows.find(function (row) {
                    if (kind === 'mobile') {
                        return digitsOnly(row.value) === digitsOnly(input.value);
                    }
                    return String(row.value).toLowerCase() === String(input.value || '').trim().toLowerCase();
                });
                if (match) {
                    applyMatchedLead({ id: match.leadId });
                }
            });
        }
        if (search) {
            search.addEventListener('input', function () {
                filterLookupList(list, this.value, rows);
            });
        }
        list.addEventListener('mousedown', function (e) {
            e.preventDefault();
            const item = e.target.closest('button');
            if (!item) {
                return;
            }
            wrap.classList.remove('open');
            if (kind === 'lead') {
                goToLead(item.dataset.leadId);
                return;
            }
            if (input) {
                input.value = item.dataset.value || '';
            }
            applyMatchedLead({ id: item.dataset.leadId });
        });
    });
    document.addEventListener('click', function (e) {
        if (!e.target.closest('#quotationForm .loc-select')) {
            closeLookups();
        }
    });

    const domainType = document.getElementById('domainType');
    const domainNameWrap = document.querySelector('.domain-name-wrap');
    const domainNewWraps = document.querySelectorAll('.domain-new-wrap');
    const checkDomainBtn = document.getElementById('checkDomainBtn');
    const domainCheckStatus = document.getElementById('domainCheckStatus');

    function toggleDomainFields() {
        const type = domainType ? domainType.value : '';
        if (domainNameWrap) {
            domainNameWrap.style.display = type ? 'block' : 'none';
        }
        domainNewWraps.forEach(function (el) {
            el.style.display = type === 'new' ? 'block' : 'none';
        });
    }

    if (domainType) {
        domainType.addEventListener('change', toggleDomainFields);
        toggleDomainFields();
    }

    if (checkDomainBtn) {
        checkDomainBtn.addEventListener('click', function () {
            const domain = (document.getElementById('domainName') || {}).value || '';
            if (!domainCheckStatus) {
                return;
            }
            domainCheckStatus.textContent = 'Checking...';
            fetch("{{ route('crm.leads.check-domain') }}?domain=" + encodeURIComponent(domain.trim()))
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    domainCheckStatus.textContent = data.message || 'Could not check domain.';
                })
                .catch(function () {
                    domainCheckStatus.textContent = 'Could not check domain.';
                });
        });
    }

    const addPlanBtn = document.getElementById('addPlanBtn');
    const plansWrapper = document.getElementById('plansWrapper');
    const providerPlans = @json($providerPlans ?? []);
    let planCount = plansWrapper.querySelectorAll('.plan-box').length;

    const currentServices = @json($currentServices->values());
    const dealTypeSelect = document.getElementById('dealType');
    const domainSection = document.getElementById('domainSection');
    const cycleMonths = { 'Monthly': 1, 'Quarterly': 3, 'Half Yearly': 6, 'Yearly': 12, '3 Yearly': 36 };

    const dealRules = {
        license_addon: { plans: 'same', qty: 'extra', period: false, planLabel: 'Plan', qtyLabel: 'Licences to Add', rateLabel: 'Price per Licence / Year' },
        renewal: { plans: 'same', qty: 'any', period: true, planLabel: 'Plan', qtyLabel: 'Licences to Renew', rateLabel: 'Rate' },
        upgrade: { plans: 'higher', qty: 'all', period: false, planLabel: 'Upgrade To', qtyLabel: 'Licences', rateLabel: 'New Plan Price per Licence / Year' },
        partial_upgrade: { plans: 'higher', qty: 'part', period: false, planLabel: 'Upgrade To', qtyLabel: 'Licences to Upgrade', rateLabel: 'New Plan Price per Licence / Year' },
        downgrade: { plans: 'lower', qty: 'all', period: true, planLabel: 'Downgrade To', qtyLabel: 'Licences', rateLabel: 'Rate' }
    };

    function dealRule() {
        return dealTypeSelect ? (dealRules[dealTypeSelect.value] || null) : null;
    }

    function planYearlyRate(plan) {
        return plan ? (parseFloat(plan.actual_price || plan.rate) || 0) : 0;
    }

    function currentPlanRate(service) {
        return planYearlyRate((providerPlans[service.provider_id] || []).find(function (p) {
            return String(p.id) === String(service.plan_id);
        }));
    }

    function planAllowed(row, plan) {
        const rule = dealRule();
        const service = rule ? currentService(row) : null;
        if (!service) return true;
        if (rule.plans === 'same') return String(plan.id) === String(service.plan_id);
        const from = currentPlanRate(service);
        return rule.plans === 'higher' ? planYearlyRate(plan) > from : planYearlyRate(plan) < from;
    }

    function daysLeft(service, row) {
        if (!service.renewal_date) return 0;
        const start = row.querySelector('.start-input').value;
        const from = start ? new Date(start + 'T00:00:00') : new Date(new Date().setHours(0, 0, 0, 0));
        return Math.max(0, Math.round((new Date(service.renewal_date + 'T00:00:00') - from) / 86400000));
    }

    function ymd(date) {
        return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
    }

    function planEndDate(row) {
        const service = dealRule() ? currentService(row) : null;
        if (service && service.renewal_date && ['license_addon', 'upgrade', 'partial_upgrade'].includes(dealTypeSelect.value)) {
            return service.renewal_date;
        }
        const start = row.querySelector('.start-input').value;
        if (!start) return '';
        const end = new Date(start + 'T00:00:00');
        const day = end.getDate();
        end.setDate(1);
        end.setMonth(end.getMonth() + (cycleMonths[row.querySelector('.quote-period-select').value] || 12));
        end.setDate(Math.min(day, new Date(end.getFullYear(), end.getMonth() + 1, 0).getDate()));
        const provider = row.querySelector('.provider-select');
        if (!/google/i.test(provider.selectedIndex > 0 ? provider.options[provider.selectedIndex].text : '')) {
            end.setDate(end.getDate() - 1);
        }
        return ymd(end);
    }

    function updateEndDate(row) {
        const end = planEndDate(row);
        row.querySelector('.end-hint').textContent = end ? 'End Date: ' + end.split('-').reverse().join('-') : '';
    }

    function prorate(yearly, days) {
        return Math.round((days > 0 ? yearly / 365 * days : yearly) * 100) / 100;
    }

    function fillCurrentServices(row) {
        const select = row.querySelector('.current-service-select');
        if (!select) return;
        const selected = select.value || select.getAttribute('data-selected') || '';
        select.innerHTML = '<option value="">' + (currentServices.length ? 'Select Current Plan' : 'No current service on this lead') + '</option>';
        currentServices.forEach(function (s) {
            const option = document.createElement('option');
            option.value = s.id;
            option.textContent = servicePlanName(s) + ' (' + s.quantity + ' licences)';
            select.appendChild(option);
        });
        select.value = selected || (currentServices.length === 1 ? currentServices[0].id : '');
    }

    function currentService(row) {
        const select = row.querySelector('.current-service-select');
        const id = select ? String(select.value || '') : '';
        return currentServices.find(function (s) { return String(s.id) === id; }) || null;
    }

    function updateChangeInfo(row) {
        const info = row.querySelector('.addon-info');
        if (!info) return;
        const rule = dealRule();
        const service = rule ? currentService(row) : null;
        if (!service) {
            info.style.display = 'none';
            info.textContent = '';
            return;
        }
        const type = dealTypeSelect.value;
        const qty = parseInt((row.querySelector('.qty-input') || {}).value, 10) || 0;
        const rate = parseFloat((row.querySelector('.rate-input') || {}).value) || 0;
        const days = daysLeft(service, row);
        const till = service.renewal_date ? service.renewal_date.split('-').reverse().join('-') : '';
        const planSelect = row.querySelector('.plan-select');
        const newPlan = planSelect && planSelect.value ? planSelect.options[planSelect.selectedIndex].text : '-';
        const currentRate = currentPlanRate(service);
        const yearly = type === 'license_addon' ? rate : Math.max(0, rate - currentRate);
        const unit = prorate(yearly, days);
        const perYear = ' / licence / year';
        const prorated = days > 0 ? ' (prorated ' + days + ' days)' : ' (full year)';
        const rows = [
            ['Current Plan', servicePlanName(service) + ' · ' + service.quantity + ' licences'],
            ['Current Price', currentRate ? money(currentRate) + perYear : '-'],
            ['Renewal Date', till ? till + ' (' + days + ' days left)' : 'Not set, full year charged']
        ];

        if (type === 'license_addon') {
            rows.push(['Licences to Add', qty || 'Enter licences above']);
            rows.push(['Total After Add-on', (service.quantity + qty) + ' licences']);
            rows.push(['Price per Licence', money(unit) + prorated]);
            rows.push(['Amount', money(unit * qty)]);
        } else if (type === 'upgrade' || type === 'partial_upgrade') {
            rows.push(['Upgrade To', newPlan + ' · ' + money(rate) + perYear]);
            rows.push(['Licences', qty ? qty + ' upgrading' + (type === 'partial_upgrade' ? ', ' + (service.quantity - qty) + ' stay on current plan' : '') : 'Enter licences above']);
            rows.push(['Price Difference', money(yearly) + perYear]);
            rows.push(['Price per Licence', money(unit) + prorated]);
            rows.push(['Amount', money(unit * qty)]);
        } else if (type === 'renewal') {
            rows.push(['Renew', qty + ' licences' + (till ? ' from ' + till : '')]);
            rows.push(['Amount', money(rate * qty)]);
        } else {
            rows.push(['Downgrade To', newPlan + (till ? ', from renewal ' + till : ', from next renewal')]);
            rows.push(['Amount', money(rate * qty)]);
        }

        info.innerHTML = rows.map(function (r, i) {
            const last = i === rows.length - 1;
            return '<div style="display:flex;justify-content:space-between;gap:16px;padding:3px 0;' + (last ? 'border-top:1px solid #e5e7eb;margin-top:4px;padding-top:6px;font-size:15px;' : '') + '">'
                + '<span style="color:#6b7280;">' + r[0] + '</span><strong style="text-align:right;">' + esc(r[1]) + '</strong></div>';
        }).join('');
        info.style.display = 'block';
    }

    function servicePlanName(s) {
        const plan = s.plan_name || '';
        return s.provider_name && plan.indexOf(s.provider_name) !== 0 ? s.provider_name + ' - ' + plan : plan;
    }

    function money(n) {
        return '₹ ' + Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function esc(v) {
        const d = document.createElement('div');
        d.textContent = v;
        return d.innerHTML;
    }

    function applyRowRule(row, reset) {
        const rule = dealRule();
        const service = rule ? currentService(row) : null;
        const show = function (selector, on) {
            const el = row.querySelector(selector);
            if (el) el.style.display = on ? '' : 'none';
        };
        show('.addon-only', !!rule);
        show('.provider-group', !rule);
        show('.sku-group', !rule);
        show('.plan-group', !rule || rule.plans !== 'same' || !row.querySelector('.plan-select').value);
        show('.period-group', !rule || rule.period);
        const periodSelect = row.querySelector('.quote-period-select');
        if (rule && !rule.period && periodSelect && periodSelect.value !== 'Yearly') {
            periodSelect.value = 'Yearly';
            updatePlanRate(row);
        }
        const startInput = row.querySelector('.start-input');
        if (reset && service && rule.period && service.renewal_date) {
            const next = new Date(service.renewal_date + 'T00:00:00');
            next.setDate(next.getDate() + 1);
            startInput.value = ymd(next);
        } else if (reset && rule && !startInput.value) {
            startInput.value = ymd(new Date());
        }

        const qtyInput = row.querySelector('.qty-input');
        const askQty = !!rule && (rule.qty === 'extra' || rule.qty === 'part');
        qtyInput.readOnly = !!rule && rule.qty === 'all';
        qtyInput.removeAttribute('max');
        qtyInput.placeholder = askQty ? 'Enter licences' : '';
        if (service && (rule.qty === 'all' || (reset && rule.qty === 'any'))) {
            qtyInput.value = service.quantity;
        }
        if (reset) {
            if (askQty) qtyInput.value = '';
            if (!rule && !qtyInput.value) qtyInput.value = 1;
        }
        if (service && rule.qty === 'part') {
            qtyInput.max = Math.max(1, service.quantity - 1);
            if ((parseInt(qtyInput.value, 10) || 0) >= service.quantity) {
                qtyInput.value = '';
            }
        }

        row.querySelector('.plan-label').textContent = rule ? rule.planLabel : 'Plan';
        row.querySelector('.qty-label').textContent = rule ? rule.qtyLabel : 'Quantity';
        row.querySelector('.rate-label').textContent = rule ? rule.rateLabel : 'Rate';
        updateChangeInfo(row);
        updateEndDate(row);
    }

    function applyCurrentService(row, reset) {
        const rule = dealRule();
        const service = rule ? currentService(row) : null;
        const providerSelect = row.querySelector('.provider-select');
        const planSelect = row.querySelector('.plan-select');
        if (service) {
            providerSelect.value = service.provider_id || '';
        }
        if (providerSelect.value) {
            const keepPlan = service && rule.plans === 'same' ? service.plan_id : planSelect.value;
            loadPlans(providerSelect.value, planSelect, row.querySelector('.sku-input'), row.querySelector('.rate-input'), keepPlan);
        }
        applyRowRule(row, reset);
    }

    function applyDealType(reset) {
        if (domainSection) {
            domainSection.style.display = dealRule() ? 'none' : '';
        }
        plansWrapper.querySelectorAll('.plan-box').forEach(function (row) {
            applyCurrentService(row, reset);
        });
    }

    plansWrapper.addEventListener('input', function (e) {
        const row = e.target.closest('.plan-box');
        if (row && (e.target.classList.contains('qty-input') || e.target.classList.contains('rate-input') || e.target.classList.contains('start-input'))) {
            updateChangeInfo(row);
            updateEndDate(row);
        }
    });

    function refreshIcons() {
        if (window.lucide) {
            lucide.createIcons();
        }
    }

    function updatePlanNumbers() {
        const plans = plansWrapper.querySelectorAll('.plan-box');
        plans.forEach(function (plan, index) {
            plan.querySelector('.plan-number').textContent = 'Sr. No. ' + (index + 1);
        });
        planCount = plans.length;
    }

    function updateRemoveButtons() {
        const plans = plansWrapper.querySelectorAll('.plan-box');
        plans.forEach(function (plan) {
            const btn = plan.querySelector('.remove-plan');
            if (btn) {
                btn.style.display = plans.length > 1 ? 'flex' : 'none';
            }
        });
    }

    function loadPlans(providerId, planSelect, skuInput, rateInput, selectedPlanId) {
        planSelect.innerHTML = '<option value="">Select Plan</option>';
        skuInput.value = '';
        rateInput.value = '';

        if (!providerId || !providerPlans[providerId]) {
            return;
        }

        const row = planSelect.closest('.plan-box');
        providerPlans[providerId].forEach(function (plan) {
            if (row && !planAllowed(row, plan)) {
                return;
            }
            const option = document.createElement('option');
            option.value = plan.id;
            option.textContent = plan.name;
            option.dataset.sku = plan.sku || plan.slug || '';
            option.dataset.actualPrice = plan.actual_price || plan.rate || 0;
            planSelect.appendChild(option);
        });

        const rule = dealRule();
        if (rule && row && currentService(row) && planSelect.options.length === 1) {
            planSelect.options[0].textContent = { same: 'Current plan not available', higher: 'No higher plan available', lower: 'No lower plan available' }[rule.plans];
        }

        if (selectedPlanId) {
            planSelect.value = selectedPlanId;
            if (!planSelect.value && rule && planSelect.options.length > 1) {
                planSelect.selectedIndex = 1;
            }
        } else if (planSelect.options.length > 1) {
            planSelect.selectedIndex = 1;
        }

        if (planSelect.value) {
            planSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function updatePlanRate(row) {
        const planSelect = row.querySelector('.plan-select');
        const periodSelect = row.querySelector('.quote-period-select');
        const rateInput = row.querySelector('.rate-input');
        if (!planSelect || !periodSelect || !rateInput) {
            return;
        }
        const selectedOption = planSelect.options[planSelect.selectedIndex];
        if (!selectedOption || !selectedOption.value) {
            rateInput.value = '';
            return;
        }
        const actualPrice = parseFloat(selectedOption.dataset.actualPrice) || 0;
        let calculatedRate = 0;
        switch (periodSelect.value) {
            case 'Monthly': calculatedRate = actualPrice / 12; break;
            case 'Quarterly': calculatedRate = actualPrice / 4; break;
            case 'Half Yearly': calculatedRate = actualPrice / 2; break;
            case 'Yearly': calculatedRate = actualPrice; break;
            case '3 Yearly': calculatedRate = actualPrice * 3; break;
            default: calculatedRate = actualPrice;
        }
        rateInput.value = calculatedRate > 0 ? calculatedRate.toFixed(2) : '';
    }

    plansWrapper.addEventListener('change', function (e) {
        const row = e.target.closest('.plan-box');
        if (!row) return;
        if (e.target.classList.contains('current-service-select')) {
            applyCurrentService(row, true);
            return;
        }
        if (e.target.classList.contains('provider-select')) {
            loadPlans(e.target.value, row.querySelector('.plan-select'), row.querySelector('.sku-input'), row.querySelector('.rate-input'));
            var name = e.target.options[e.target.selectedIndex] ? e.target.options[e.target.selectedIndex].text : '';
            var same = false;
            plansWrapper.querySelectorAll('.provider-select').forEach(function (sel) {
                if (sel === e.target) return;
                if (sel.value && sel.value === e.target.value) same = true;
            });
            if (same && !/google/i.test(name)) {
                showFieldError(e.target, 'Same provider can only be added once.');
                e.target.value = '';
                loadPlans('', row.querySelector('.plan-select'), row.querySelector('.sku-input'), row.querySelector('.rate-input'));
                return;
            }
            return;
        }
        if (e.target.classList.contains('plan-select')) {
            const selected = e.target.options[e.target.selectedIndex];
            const skuInput = row.querySelector('.sku-input');
            if (skuInput) {
                skuInput.value = selected.dataset.sku || '';
            }
            updatePlanRate(row);
            var providerSel = row.querySelector('.provider-select');
            var planSel = row.querySelector('.plan-select');
            if (providerSel && planSel && providerSel.value && planSel.value) {
                var pair = providerSel.value + ':' + planSel.value;
                var dup = false;
                plansWrapper.querySelectorAll('.plan-box').forEach(function (box) {
                    if (box === row) return;
                    var a = box.querySelector('.provider-select');
                    var b = box.querySelector('.plan-select');
                    if (a && b && a.value && b.value && (a.value + ':' + b.value) === pair) dup = true;
                });
                if (dup) {
                    showFieldError(planSel, 'This plan is already added.');
                    planSel.value = '';
                    if (skuInput) skuInput.value = '';
                    var rateEl = row.querySelector('.rate-input');
                    if (rateEl) rateEl.value = '';
                }
            }
            updateChangeInfo(row);
            return;
        }
        if (e.target.classList.contains('quote-period-select')) {
            updatePlanRate(row);
            updateChangeInfo(row);
        }
    });

    plansWrapper.addEventListener('change', function (e) {
        const row = e.target.closest('.plan-box');
        if (row) updateEndDate(row);
    });

    addPlanBtn.addEventListener('click', function (e) {
        e.preventDefault();
        const index = planCount;
        planCount++;
        const plan = document.createElement('div');
        plan.className = 'plan-box';
        plan.innerHTML = `
            <div class="plan-header">

                <strong class="plan-number">Sr. No. ${planCount}</strong>

                <button
                    type="button"
                    class="remove-plan"
                    aria-label="Remove Plan">

                    <i data-lucide="x"></i>

                </button>

            </div>


            <div class="form-grid form-grid-3">

                <div class="form-group addon-only" style="display:none;">
                    <label>Current Plan <span>*</span></label>
                    <select class="form-control current-service-select" name="plans[${index}][from_service_id]"></select>
                </div>

                <div class="form-group provider-group">
                    <label>Provider <span>*</span></label>
                    <select
                        class="form-control provider-select"
                        name="plans[${index}][provider_id]"
                        required>
                        <option value="">Select Provider</option>
                        @foreach ($providers ?? [] as $provider)
                            <option value="{{ $provider->id }}">{{ $provider->name }}</option>
                        @endforeach
                    </select>
                </div>


                <div class="form-group plan-group">

                    <label><span class="plan-label">Plan</span> <span>*</span></label>

                    <select
                        class="form-control plan-select"
                        name="plans[${index}][plan_id]"
                        required>

                        <option value="">
                            Select Plan
                        </option>

                    </select>

                </div>


                <div class="form-group period-group">

                    <label>Billing Cycle <span>*</span></label>

                    <select
                        class="form-control quote-period-select"
                        name="plans[${index}][quote_period]"
                        required>

                        <option value="">
                            Select Billing Cycle
                        </option>
                        <option value="Monthly">Monthly</option>
                        <option value="Quarterly">Quarterly</option>
                        <option value="Half Yearly">Half Yearly</option>
                        <option value="Yearly" selected>Yearly</option>
                        <option value="3 Yearly">3 Yearly</option>

                    </select>

                </div>

                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" name="plans[${index}][billing_start_date]" class="start-input">
                    <div class="form-hint end-hint"></div>
                </div>


                <div class="form-group sku-group">
                    <label>SKU <span>*</span></label>
                    <input type="text" name="plans[${index}][sku]" class="sku-input" required>
                </div>


                <div class="form-group">
                    <label><span class="qty-label">Quantity</span> <span>*</span></label>
                    <input type="number" name="plans[${index}][quantity]" class="qty-input" min="1" value="1" required>
                </div>


                <div class="form-group">
                    <label><span class="rate-label">Rate</span> <span>*</span></label>
                    <input type="text" name="plans[${index}][rate]" class="rate-input" required>
                </div>

            </div>

            <div class="form-hint addon-info" style="display:none;max-width:620px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:10px 14px;margin-top:12px;"></div>
        `;
        plansWrapper.appendChild(plan);
        fillCurrentServices(plan);
        applyCurrentService(plan, true);
        updatePlanNumbers();
        updateRemoveButtons();
        refreshIcons();
    });

    plansWrapper.addEventListener('click', function (e) {
        if (!e.target.closest('.remove-plan')) {
            return;
        }
        const plan = e.target.closest('.plan-box');
        if (!plan) {
            return;
        }
        plan.remove();
        updatePlanNumbers();
        updateRemoveButtons();
    });

    plansWrapper.querySelectorAll('.plan-box').forEach(function (row) {
        const providerSelect = row.querySelector('.provider-select');
        const planSelect = row.querySelector('.plan-select');
        if (providerSelect && providerSelect.value) {
            loadPlans(
                providerSelect.value,
                planSelect,
                row.querySelector('.sku-input'),
                row.querySelector('.rate-input'),
                planSelect.getAttribute('data-selected')
            );
        }
    });

    plansWrapper.querySelectorAll('.plan-box').forEach(fillCurrentServices);
    if (dealTypeSelect) {
        dealTypeSelect.addEventListener('change', function () {
            applyDealType(true);
        });
    }
    applyDealType();

    updateRemoveButtons();
    refreshIcons();

    if (quotationForm) {
        quotationForm.addEventListener('input', function (e) {
            if (e.target && e.target.name) clearFieldError(e.target);
        });
        quotationForm.addEventListener('change', function (e) {
            if (e.target && e.target.name) clearFieldError(e.target);
        });
        quotationForm.addEventListener('submit', function (e) {
            quotationForm.querySelectorAll('.js-field-error').forEach(function (err) { err.remove(); });
            quotationForm.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });

            const checks = [
                { name: 'company_name', msg: 'Company Name is required.' },
                { name: 'gstin', msg: 'GSTIN is required.' },
                { name: 'salutation', msg: 'Title is required.' },
                { name: 'name', msg: 'Name is required.' },
                { name: 'email', msg: 'Email is required.' },
                { name: 'discount', msg: 'Discount is required.' }
            ];
            let firstInvalid = null;
            checks.forEach(function (item) {
                const el = namedField(quotationForm, item.name);
                if (!el || !String(el.value || '').trim()) {
                    const visible = quotationForm.querySelector('[name="' + item.name + '"]:not([type="hidden"])') || el;
                    showFieldError(visible, item.msg);
                    if (!firstInvalid && visible) firstInvalid = visible;
                }
            });

            const emailEl = namedField(quotationForm, 'email');
            if (emailEl && String(emailEl.value || '').trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(emailEl.value).trim())) {
                showFieldError(emailEl, 'Enter a valid email.');
                if (!firstInvalid) firstInvalid = emailEl;
            }

            const planChecks = [
                { sel: '.provider-select', msg: 'Provider is required.' },
                { sel: '.plan-select', msg: 'Plan is required.' },
                { sel: '.quote-period-select', msg: 'Billing Cycle is required.' },
                { sel: '.sku-input', msg: 'SKU is required.' },
                { sel: '[name$="[quantity]"]', msg: 'Quantity is required.' },
                { sel: '.rate-input', msg: 'Rate is required.' }
            ];
            quotationForm.querySelectorAll('.plan-box').forEach(function (box) {
                const serviceSelect = box.querySelector('.current-service-select');
                if (dealRule() && serviceSelect && !serviceSelect.value) {
                    showFieldError(serviceSelect, 'Select the current plan.');
                    if (!firstInvalid) firstInvalid = serviceSelect;
                }
                planChecks.forEach(function (item) {
                    const el = box.querySelector(item.sel);
                    if (!el || !String(el.value || '').trim()) {
                        showFieldError(el, item.msg);
                        if (!firstInvalid && el) firstInvalid = el;
                    }
                });
            });

            if (firstInvalid) {
                e.preventDefault();
                goToField(firstInvalid);
                return false;
            }
        });
    }
});
</script>
@endsection
