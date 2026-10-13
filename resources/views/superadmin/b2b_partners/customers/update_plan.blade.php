@extends('superadmin.layouts.app')

@section('content')
    @php
        $providerLogo = match ((int) ($subscription->provider_id ?? 0)) {
            1 => asset('home/images/tizzy.png'),
            2 => asset('home/images/icons/microsoft-icon.png'),
            3 => asset('home/images/icons/googl-logo.png'),
            default => asset('public/home/images/tizzy.png'),
        };
        $planTitle = $subscription->plan_title ?? ($subscription->plan_name ?? 'N/A');
        $domainName = $subscription->domain_name ?? 'N/A';
        $quantity = (int) ($subscription->quantity ?? 0);
        $unitPrice = (float) ($subscription->price ?? 0);
        $subtotal = (float) ($unitPrice * max(1, $quantity));
        $discount = (float) ($subscription->discount_amount ?? 0);
        $tax = (float) ($subscription->tax_amount ?? 0);
        $total = (float) ($subscription->total_amount ?? max(0, $subtotal - $discount + $tax));
        $durationYears = max(1, (int) ($subscription->duration ?? 1));
        $startDate = !empty($subscription->subscription_start_date)
            ? \Illuminate\Support\Carbon::parse($subscription->subscription_start_date)
            : \Illuminate\Support\Carbon::parse($subscription->created_at);
        $endDate = !empty($subscription->subscription_end_date)
            ? \Illuminate\Support\Carbon::parse($subscription->subscription_end_date)
            : (!empty($subscriptionEndDate ?? null)
                ? \Illuminate\Support\Carbon::parse($subscriptionEndDate)
                : (clone $startDate)->addYears($durationYears));
        $currentLicenses = max(1, $quantity);
        $remainingDays = max(0, (int) ($remainingDays ?? 0));
        $dailyRatePerLicense = (float) ($dailyRatePerLicense ?? ($unitPrice > 0 ? round($unitPrice / 365, 2) : 0));
        $estimatedProRataValue = (float) ($estimatedProRata ?? round(($unitPrice / 365) * $remainingDays * $currentLicenses, 2));
        $upgradePlansCollection = isset($upgradePlans) ? $upgradePlans : collect();
        $hasUpgradePlans = $upgradePlansCollection->isNotEmpty();
        $downgradePlansCollection = isset($downgradePlans) ? $downgradePlans : collect();
        $hasDowngradePlans = $downgradePlansCollection->isNotEmpty();
        $planCustomerLimit = max(0, (int) ($planCustomerLimit ?? 0));
        $upgradeOnly = !empty($upgradeOnly);
        $summarySubtotal = (float) $subtotal;
        $summaryProRata = 0.0;
        $summaryTaxable = max(0, $summarySubtotal - $summaryProRata);
        $summaryGst = round($summaryTaxable * 0.18, 2);
        $summaryPayable = round($summaryTaxable + $summaryGst, 2);
        if ($upgradeOnly && $hasUpgradePlans) {
            $firstUpgradePlan = $upgradePlansCollection->first();
            $upgradeQty = max(1, $quantity);
            $creditLicenses = min($upgradeQty, $currentLicenses);
            $creditForQty = round(($unitPrice / 365) * $remainingDays * $creditLicenses, 2);
            $summarySubtotal = round((float) ($firstUpgradePlan->price ?? 0) * $upgradeQty, 2);
            $summaryProRata = $creditForQty;
            $appliedProRata = min($creditForQty, $summarySubtotal);
            $summaryTaxable = max(0, $summarySubtotal - $appliedProRata);
            $summaryGst = round($summaryTaxable * 0.18, 2);
            $summaryPayable = round($summaryTaxable + $summaryGst, 2);
        }
        $upgradeApiUrl = url('/superadmin/upgrade') . '?' . http_build_query([
            'plan_id' => (int) ($subscription->plan_id ?? 0),
            'order_id' => (int) ($subscription->id ?? 0),
        ]);
    @endphp
    <style>
        .plan-slider {
            display: flex;
            gap: .75rem;
            direction: ltr;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            scroll-behavior: smooth;
            padding: .25rem .1rem .35rem;
        }

        .plan-slide {
            min-width: 240px;
            scroll-snap-align: start;
        }

        .plan-radio-card {
            position: relative;
            background: #fff;
            border-color: #dfe3e8 !important;
            transition: all .2s ease;
        }

        .upgrade-plan-radio,
        .downgrade-plan-radio {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .plan-radio-card:hover {
            border-color: #9ec5fe !important;
            box-shadow: 0 6px 18px rgba(13, 110, 253, .12);
        }

        .plan-radio-card.selected {
            border-color: #0d6efd !important;
            background: #eef5ff;
            box-shadow: 0 10px 24px rgba(13, 110, 253, .2);
        }

        .avatar-img {
            width: 40px;
            height: 40px;
            max-width: 100%;
            object-fit: cover;
        }
    </style>
    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="card-title mb-0">Update Plan - {{ $customer->name }}</h3>
            <a href="#" onclick="history.back();" class="btn btn-secondary btn-sm">
                <i class="ti ti-arrow-left me-1"></i> Back
            </a>
        </div>
        <div class="alert alert-light border d-flex justify-content-between align-items-center flex-wrap gap-2">
            {{-- <span><strong>Wallet:</strong> ₹{{ number_format((float) ($walletBalance ?? 0), 2) }}</span> --}}
            {{-- <span><strong>Credit Used:</strong> ₹{{ number_format((float) ($creditUsed ?? 0), 2) }} /
                ₹{{ number_format((float) ($partner->credit_limit ?? 0), 2) }}</span> --}}
            <span><strong>Available Credits:</strong>
                ₹{{ number_format((float) ($maxUpgradePayable ?? (($walletBalance ?? 0) + ($creditAvailable ?? 0))), 2) }}</span>
        </div>

        <div class="row">
            <div class="col-lg-8">
                @if (!$upgradeOnly)
                <div class="card mb-3">
                    <div class="card-body py-2">
                        <div class="btn-group" role="group" aria-label="Renewal mode">
                            <input type="radio" class="btn-check" name="renew_mode" id="modeRenew" autocomplete="off"
                                checked>
                            <label class="btn btn-outline-primary" for="modeRenew">Renew Current</label>

                            <input type="radio" class="btn-check" name="renew_mode" id="modeUpgrade"
                                autocomplete="off">
                            <label class="btn btn-outline-primary" for="modeUpgrade">Upgrade</label>
                        </div>
                    </div>
                </div>
                @endif
                <div class="card mb-3" @if($upgradeOnly) style="display:none;" @endif>
                    {{-- <div class="card-header">
                    <h5 class="card-title mb-0">UPDATE LICENSE / RENEWAL</h5>
                </div> --}}
                    <div class="card-body" id="renewSection">
                        <div class="d-flex align-items-center justify-content-between border rounded p-3 mb-3">
                            <div class="d-flex align-items-center">
                                <span class="avatar avatar-md me-1">
                                    <img src="{{ $providerLogo }}" alt="Provider Logo" class="avatar-img">
                                </span>
                                <div>
                                    <h6 class="mb-0">{{ $planTitle }}</h6>
                                    <small class="text-muted">{{ $domainName }}</small><br>
                                    <small class="text-muted">{{ $startDate->format('d M, Y') }} -
                                        {{ $endDate->format('d M, Y') }}</small>
                                </div>
                            </div>
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <small class="text-muted d-block">Price</small>
                                    <h6 class="mb-0">₹{{ number_format($unitPrice, 2) }}</h6>
                                    <small class="text-muted">per user/year</small>
                                </div>
                                <div class="me-3 text-center" style="width: 140px;">
                                    <small class="text-muted d-block">Licenses</small>
                                    <div class="input-group input-group-sm mt-1" style="max-width: 140px;">
                                        <button class="btn btn-outline-secondary" type="button"
                                            id="renewQtyMinus">-</button>
                                        <input type="number" min="1" step="1"
                                            @if($planCustomerLimit > 0) max="{{ $planCustomerLimit }}" @endif
                                            class="form-control text-center" id="renewQtyInput"
                                            value="{{ max(1, $planCustomerLimit > 0 ? min($quantity, $planCustomerLimit) : $quantity) }}">
                                        <button class="btn btn-outline-secondary" type="button"
                                            id="renewQtyPlus">+</button>
                                    </div>
                                    @if($planCustomerLimit > 0)
                                        <small class="text-muted d-block mt-1">Max {{ $planCustomerLimit }} licenses</small>
                                    @endif
                                </div>
                                <div>
                                    <small class="text-muted d-block">Total</small>
                                    <h6 class="mb-0" id="renewRowTotal">₹{{ number_format($subtotal, 2) }}</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mb-3" id="downgradeCard" style="display:none;">
                    <div class="card-body" id="downgradeSection">
                        <div class="border rounded p-3 mb-3 bg-light-subtle">
                            <h6 class="mb-2">Current Subscription</h6>
                            <p class="mb-1"><strong>Plan:</strong> {{ $planTitle }}</p>
                            <p class="mb-1"><strong>Domain:</strong> {{ $domainName }}</p>
                            <p class="mb-1"><strong>Current Unit Price:</strong> ₹{{ number_format($unitPrice, 2) }}</p>
                            <p class="mb-0"><strong>Current Licenses:</strong> {{ max(1, $quantity) }}</p>
                        </div>

                        <div class="mb-3">
                            <h6 class="mb-2">Choose Downgrade Plan</h6>
                            <div class="plan-slider" id="downgradePlanCards">
                                @forelse($downgradePlansCollection as $plan)
                                    @php
                                        $storageDisplay = $plan->storage_gb ?? ($plan->storage ?? '-');
                                    @endphp
                                    <div class="plan-slide">
                                        <label class="w-100 border rounded p-3 plan-radio-card" style="cursor:pointer;">
                                            <input type="radio" class="form-check-input me-2 downgrade-plan-radio"
                                                name="downgrade_plan_id" value="{{ $plan->id }}"
                                                data-name="{{ $plan->name }}" data-storage="{{ $storageDisplay }}"
                                                data-price="{{ (float) $plan->price }}"
                                                data-customer-limit="{{ max(0, (int) ($plan->customer_limit ?? 0)) }}">
                                            <span class="fw-semibold d-block">{{ $plan->name }}</span>
                                            <small class="text-muted d-block">Storage: {{ $storageDisplay }}</small>
                                            <small class="text-muted d-block">Unit:
                                                ₹{{ number_format((float) $plan->price, 2) }}</small>
                                        </label>
                                    </div>
                                @empty
                                    <div class="w-100">
                                        <div class="alert alert-info mb-0">No lower plan available for downgrade.</div>
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        <div class="row g-2 align-items-center">
                            <div class="col-md-6">
                                <label class="form-label mb-1">Selected Plan</label>
                                <div class="border rounded p-2 bg-white">
                                    <div class="fw-semibold" id="downgradeSelectedName">-</div>
                                    <small class="text-muted" id="downgradeSelectedStorage">Storage: -</small><br>
                                    <small class="text-muted" id="downgradeSelectedPrice">Unit Price: ₹0.00</small>
                                </div>
                            </div>
                                <div class="col-md-6">
                                    <label class="form-label mb-1">Licenses</label>
                                    <div class="input-group input-group-sm" style="max-width: 160px;">
                                        <button class="btn btn-outline-secondary" type="button"
                                            id="downgradeQtyMinus">-</button>
                                        <input type="number" min="1" step="1"
                                            class="form-control text-center" id="downgradeQtyInput"
                                            value="{{ max(1, $quantity) }}">
                                        <button class="btn btn-outline-secondary" type="button"
                                            id="downgradeQtyPlus">+</button>
                                    </div>
                                    <small class="text-muted d-block mt-1 d-none" id="downgradeQtyLimitHint"></small>
                                </div>
                        </div>
                    </div>
                </div>
                <div class="card mb-3">
                    {{-- <div class="card-header">
                        <h5 class="card-title mb-0">PLAN UPGRADE</h5>
                    </div> --}}
                    <div class="card-body" id="upgradeSection" @if($upgradeOnly) style="" @else style="display: none;" @endif>
                        @if (!empty($hasPendingUpgrade))
                            <div class="alert alert-warning mb-0">
                                Complete your existing payment before starting another upgrade.
                            </div>
                        @else
                            <div class="border rounded p-3 mb-3 bg-light-subtle">
                                <h6 class="mb-2">Current Subscription</h6>
                                <p class="mb-1"><strong>Plan:</strong> {{ $planTitle }}</p>
                                <p class="mb-1"><strong>Domain:</strong> {{ $domainName }}</p>
                                <p class="mb-1"><strong>Current Unit Price:</strong> ₹{{ number_format($unitPrice, 2) }}
                                </p>
                                <p class="mb-1"><strong>Current Licenses:</strong> {{ $currentLicenses }}</p>
                                <p class="mb-1"><strong>Remaining Days:</strong> <span id="remainingDaysText">{{ $remainingDays }}</span></p>
                                <p class="mb-1"><strong>Daily Rate (per license):</strong>
                                    <span id="dailyRateText">₹{{ number_format($dailyRatePerLicense, 2) }}</span>
                                </p>
                                <p class="mb-0"><strong>Remaining Value:</strong>
                                    <span id="remainingValueText">₹{{ number_format($estimatedProRataValue, 2) }}</span>
                                </p>
                                <small class="text-muted d-block mt-1" id="prorationFormulaText">
                                    ₹{{ number_format($unitPrice, 2) }} ÷ 365 × {{ $remainingDays }} days ×
                                    {{ $currentLicenses }} license(s)
                                </small>
                            </div>

                            <div class="mb-3">
                                <h6 class="mb-2">Choose Upgrade Plan</h6>
                                <div class="plan-slider" id="upgradePlanCards">
                                    @forelse($upgradePlansCollection as $plan)
                                        @php
                                            $storageDisplay = $plan->storage_gb ?? ($plan->storage ?? '-');
                                        @endphp
                                        <div class="plan-slide">
                                            <label class="w-100 border rounded p-3 plan-radio-card" style="cursor:pointer;">
                                                <input type="radio" class="form-check-input me-2 upgrade-plan-radio"
                                                    name="upgrade_plan_id" value="{{ $plan->id }}"
                                                    data-name="{{ $plan->name }}" data-storage="{{ $storageDisplay }}"
                                                    data-price="{{ (float) $plan->price }}"
                                                    data-customer-limit="{{ max(0, (int) ($plan->customer_limit ?? 0)) }}">
                                                <span class="fw-semibold d-block">{{ $plan->name }}</span>
                                                <small class="text-muted d-block">Storage: {{ $storageDisplay }}</small>
                                                <small class="text-muted d-block">Unit:
                                                    ₹{{ number_format((float) $plan->price, 2) }}</small>
                                                @if ((int) ($plan->customer_limit ?? 0) > 0)
                                                    <small class="text-muted d-block">Max licenses:
                                                        {{ (int) $plan->customer_limit }}</small>
                                                @endif
                                            </label>
                                        </div>
                                    @empty
                                        <div class="w-100">
                                            <div class="alert alert-info mb-0">No higher plan available for upgrade.</div>
                                        </div>
                                    @endforelse
                                </div>
                                <div class="alert alert-danger mt-2 mb-0 d-none" id="upgradePlansError"></div>
                            </div>

                            <div class="row g-2 align-items-center">
                                <div class="col-md-6">
                                    <label class="form-label mb-1">Selected Plan</label>
                                    <div class="border rounded p-2 bg-white">
                                        <div class="fw-semibold" id="upgradeSelectedName">-</div>
                                        <small class="text-muted" id="upgradeSelectedStorage">Storage: -</small><br>
                                        <small class="text-muted" id="upgradeSelectedPrice">Unit Price: ₹0.00</small><br>
                                        <small class="text-muted" id="upgradeSelectedLimit">Max licenses: -</small><br>
                                        <small class="fw-semibold" id="upgradeSelectedSubtotal">Subtotal: ₹0.00</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label mb-1">Licenses</label>
                                    <div class="input-group input-group-sm" style="max-width: 160px;">
                                        <button class="btn btn-outline-secondary" type="button"
                                            id="upgradeQtyMinus">-</button>
                                        <input type="number" min="1" step="1"
                                            class="form-control text-center" id="upgradeQtyInput"
                                            value="{{ max(1, $quantity) }}">
                                        <button class="btn btn-outline-secondary" type="button"
                                            id="upgradeQtyPlus">+</button>
                                    </div>
                                    <small class="text-muted d-block mt-1 d-none" id="upgradeQtyLimitHint"></small>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

            {{-- <div class="d-flex justify-content-end" id="topSubtotalWrap" style="display:none;">
                <h4>SUBTOTAL <span class="fw-bold" id="renewSubtotalText">₹{{ number_format($subtotal, 2) }}</span></h4>
            </div> --}}
        </div>
        <div class="col-lg-4">
            <div class="card" id="orderSummaryCard">
                <div class="card-header">
                    <h5 class="card-title mb-0">ORDER SUMMARY</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Subtotal
                            <span id="summarySubtotalText">₹{{ number_format($summarySubtotal, 2) }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Pro-rata Adjustment
                            <span id="summaryProRataText">- ₹{{ number_format($summaryProRata, 2) }}</span>
                        </li>
                        <small class="text-muted d-block px-3 pb-2" id="summaryProrationHint">
                            ₹{{ number_format($unitPrice, 2) }} ÷ 365 × {{ $remainingDays }} days × {{ $currentLicenses }} license(s)
                        </small>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Taxable Amount
                            <span id="summaryTaxableText">₹{{ number_format($summaryTaxable, 2) }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span id="summaryGstLabel">GST (18% on ₹{{ number_format($summaryTaxable, 2) }})</span>
                            <span id="summaryGstText">₹{{ number_format($summaryGst, 2) }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center fw-bold">
                            Payable Amount
                            <span id="summaryTotalText">₹{{ number_format($summaryPayable, 2) }}</span>
                        </li>
                    </ul>
                    <div class="small mt-2" id="creditLimitHint">
                        Available credits: ₹{{ number_format((float) ($renewFundsAvailable ?? 0), 2) }}
                    </div>
                    <div class="alert alert-warning mt-3 mb-0 d-none" id="renewPlanLimitAlert">
                        Licenses cannot exceed the plan limit.
                    </div>
                    <div class="alert alert-danger mt-3 mb-0 @if (!empty($renewCanProceed)) d-none @endif" id="renewInsufficientAlert">
                        Insufficient credits to complete this purchase
                    </div>
                    @if (!$upgradeOnly)
                    <form method="POST" id="renewForm"
                        action="{{ route('superadmin.b2b_partners.customers.renew_with_credit', ['partner_id' => $encryptedPartnerId, 'subscription_id' => Crypt::encrypt($subscription->id)]) }}">
                        @csrf
                        <input type="hidden" name="quantity" id="renewQtyHidden" value="{{ max(1, $quantity) }}">
                        <div class="mt-3 @if (empty($renewCanProceed)) d-none @endif" id="renewProceedBlock">
                            <button type="submit" class="btn btn-primary w-100" id="renewProceedBtn">Proceed</button>
                        </div>
                    </form>
                    @endif
                    @if ($hasUpgradePlans)
                        <form method="POST"
                            action="{{ route('superadmin.b2b_partners.customers.upgrade_with_credit', ['partner_id' => $encryptedPartnerId, 'subscription_id' => Crypt::encrypt($subscription->id)]) }}"
                            id="upgradeForm" class="mt-2" @if($upgradeOnly) style="" @else style="display:none;" @endif>
                            @csrf
                            <input type="hidden" name="plan_id" id="upgradePlanIdHidden">
                            <input type="hidden" name="quantity" id="upgradeQtyHidden"
                                value="{{ max(1, $quantity) }}">
                            <div id="upgradeProceedBlock">
                                <button type="submit" class="btn btn-primary w-100" id="upgradeProceedBtn"
                                    @if (!empty($hasPendingUpgrade)) disabled @endif>Proceed</button>
                            </div>
                        </form>
                    @endif
                    @if ($hasDowngradePlans)
                        <form method="POST"
                            action="{{ route('superadmin.b2b_partners.customers.downgrade_with_credit', ['partner_id' => $encryptedPartnerId, 'subscription_id' => Crypt::encrypt($subscription->id)]) }}"
                            id="downgradeForm" class="mt-2" style="display:none;">
                            @csrf
                            <input type="hidden" name="plan_id" id="downgradePlanIdHidden">
                            <input type="hidden" name="quantity" id="downgradeQtyHidden"
                                value="{{ max(1, $quantity) }}">
                            <div id="downgradeProceedBlock">
                                <button type="submit" class="btn btn-primary w-100" id="downgradeProceedBtn"
                                    @if (!empty($hasPendingUpgrade)) disabled @endif>Proceed</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
    </div>
    </div>

    <script>
        (function() {
            var unitPrice = {{ (float) $unitPrice }};
            var discount = {{ (float) ($discount ?? 0) }};
            var currentUnitPrice = {{ (float) $unitPrice }};
            var baseSubscriptionLicenses = {{ (int) $currentLicenses }};
            var remainingDays = {{ (int) $remainingDays }};
            var dailyRatePerLicense = {{ (float) $dailyRatePerLicense }};
            var estimatedProRata = {{ (float) $estimatedProRataValue }};
            var remainingDaysText = document.getElementById('remainingDaysText');
            var dailyRateText = document.getElementById('dailyRateText');
            var remainingValueText = document.getElementById('remainingValueText');
            var prorationFormulaText = document.getElementById('prorationFormulaText');
            var summaryProrationHint = document.getElementById('summaryProrationHint');
            var qtyInput = document.getElementById('renewQtyInput');
            var qtyHidden = document.getElementById('renewQtyHidden');
            var renewQtyMinus = document.getElementById('renewQtyMinus');
            var renewQtyPlus = document.getElementById('renewQtyPlus');
            var rowTotal = document.getElementById('renewRowTotal');
            var subtotalTop = document.getElementById('renewSubtotalText');
            var subtotalSummary = document.getElementById('summarySubtotalText');
            var totalSummary = document.getElementById('summaryTotalText');
            var proRataSummary = document.getElementById('summaryProRataText');
            var taxableSummary = document.getElementById('summaryTaxableText');
            var gstSummary = document.getElementById('summaryGstText');
            var gstLabel = document.getElementById('summaryGstLabel');
            var modeRenew = document.getElementById('modeRenew');
            var modeUpgrade = document.getElementById('modeUpgrade');
            var modeDowngrade = document.getElementById('modeDowngrade');
            var hasUpgradePlans = {{ $hasUpgradePlans ? 'true' : 'false' }};
            var hasDowngradePlans = {{ $hasDowngradePlans ? 'true' : 'false' }};
            var upgradeOnly = {{ $upgradeOnly ? 'true' : 'false' }};
            var upgradeApiUrl = @json($upgradeApiUrl);
            var renewSection = document.getElementById('renewSection');
            var upgradeSection = document.getElementById('upgradeSection');
            var downgradeCard = document.getElementById('downgradeCard');
            var topSubtotalWrap = document.getElementById('topSubtotalWrap');
            var orderSummaryCard = document.getElementById('orderSummaryCard');
            var renewSubmitBtn = document.getElementById('renewSubmitBtn');
            var upgradeProceedBtn = document.getElementById('upgradeProceedBtn');
            var upgradePlanIdHidden = document.getElementById('upgradePlanIdHidden');
            var upgradeQtyHidden = document.getElementById('upgradeQtyHidden');
            var upgradeQtyInput = document.getElementById('upgradeQtyInput');
            var upgradeQtyMinus = document.getElementById('upgradeQtyMinus');
            var upgradeQtyPlus = document.getElementById('upgradeQtyPlus');
            var upgradeName = document.getElementById('upgradeSelectedName');
            var upgradeStorage = document.getElementById('upgradeSelectedStorage');
            var upgradePrice = document.getElementById('upgradeSelectedPrice');
            var upgradeLimit = document.getElementById('upgradeSelectedLimit');
            var upgradeSubtotal = document.getElementById('upgradeSelectedSubtotal');
            var upgradePlanCards = document.getElementById('upgradePlanCards');
            var upgradePlansError = document.getElementById('upgradePlansError');
            // Downgrade
            var downgradeProceedBtn = document.getElementById('downgradeProceedBtn');
            var downgradePlanIdHidden = document.getElementById('downgradePlanIdHidden');
            var downgradeQtyHidden = document.getElementById('downgradeQtyHidden');
            var downgradeQtyInput = document.getElementById('downgradeQtyInput');
            var downgradeQtyMinus = document.getElementById('downgradeQtyMinus');
            var downgradeQtyPlus = document.getElementById('downgradeQtyPlus');
            var downgradeName = document.getElementById('downgradeSelectedName');
            var downgradeStorage = document.getElementById('downgradeSelectedStorage');
            var downgradePrice = document.getElementById('downgradeSelectedPrice');
            var downgradePlanRadios = document.querySelectorAll('.downgrade-plan-radio');
            var creditLimitHint = document.getElementById('creditLimitHint');
            var renewProceedBlock = document.getElementById('renewProceedBlock');
            var renewInsufficientAlert = document.getElementById('renewInsufficientAlert');
            var renewForm = document.getElementById('renewForm');
            var upgradePlanRadios = document.querySelectorAll('.upgrade-plan-radio');
            var currentPlanValue = unitPrice * Math.max(1, parseInt({{ max(1, $quantity) }}, 10));
            var maxUpgradePayable = {{ (float) ($maxUpgradePayable ?? 0) }};
            var renewFundsAvailable = {{ (float) ($renewFundsAvailable ?? 0) }};
            var renewPlanLimit = {{ (int) $planCustomerLimit }};
            var renewPlanLimitAlert = document.getElementById('renewPlanLimitAlert');
            var upgradeQtyLimitHint = document.getElementById('upgradeQtyLimitHint');
            var downgradeQtyLimitHint = document.getElementById('downgradeQtyLimitHint');

            function formatCurrency(val) {
                return '₹' + Number(val).toLocaleString('en-IN', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            function computeRemainingCredit() {
                if (remainingDays <= 0 || currentUnitPrice <= 0 || baseSubscriptionLicenses <= 0) {
                    return 0;
                }
                return Math.round((currentUnitPrice / 365) * remainingDays * baseSubscriptionLicenses * 100) / 100;
            }

            function creditLicensesForQty(upgradeQty) {
                return Math.min(Math.max(1, parseQty(upgradeQty)), baseSubscriptionLicenses);
            }

            function computeCreditForQty(upgradeQty) {
                var licenses = creditLicensesForQty(upgradeQty);
                if (remainingDays <= 0 || currentUnitPrice <= 0) {
                    return 0;
                }
                return Math.round((currentUnitPrice / 365) * remainingDays * licenses * 100) / 100;
            }

            function buildProrationFormula(licenseCount, amount) {
                return formatCurrency(currentUnitPrice) + ' ÷ 365 × ' + remainingDays +
                    ' days × ' + licenseCount + ' license(s) = ' + formatCurrency(amount);
            }

            function updateProrationDisplay(upgradeQty) {
                dailyRatePerLicense = currentUnitPrice > 0
                    ? Math.round((currentUnitPrice / 365) * 100) / 100
                    : 0;
                var licenses = upgradeQty != null
                    ? creditLicensesForQty(upgradeQty)
                    : baseSubscriptionLicenses;
                estimatedProRata = upgradeQty != null
                    ? computeCreditForQty(upgradeQty)
                    : computeRemainingCredit();
                var formula = buildProrationFormula(licenses, estimatedProRata);

                if (remainingDaysText) remainingDaysText.textContent = String(remainingDays);
                if (dailyRateText) dailyRateText.textContent = formatCurrency(dailyRatePerLicense);
                if (remainingValueText) remainingValueText.textContent = formatCurrency(estimatedProRata);
                if (prorationFormulaText) prorationFormulaText.textContent = formula;
            }

            function applyProrationFromApi(proration) {
                if (!proration) return;
                lastApiProration = proration;
                if (proration.remaining_days != null) {
                    remainingDays = Math.max(0, parseInt(proration.remaining_days, 10) || 0);
                }
                if (proration.current_unit_price != null) {
                    currentUnitPrice = parseFloat(proration.current_unit_price) || currentUnitPrice;
                }
                if (proration.daily_rate_per_license != null || proration.daily_rate != null) {
                    dailyRatePerLicense = parseFloat(proration.daily_rate_per_license ?? proration.daily_rate) ||
                        dailyRatePerLicense;
                }
                if (proration.current_licenses != null) {
                    baseSubscriptionLicenses = Math.max(1, parseInt(proration.current_licenses, 10) || baseSubscriptionLicenses);
                }
                updateProrationDisplay(upgradeQtyInput ? upgradeQtyInput.value : null);
            }

            function buildUpgradeApiUrl(licenses, newPlanId) {
                if (!upgradeApiUrl) return '';
                try {
                    var url = new URL(upgradeApiUrl, window.location.origin);
                    if (licenses != null) {
                        url.searchParams.set('licenses', String(licenses));
                    }
                    if (newPlanId) {
                        url.searchParams.set('new_plan_id', String(newPlanId));
                    }
                    return url.toString();
                } catch (e) {
                    return upgradeApiUrl;
                }
            }

            var lastApiProration = null;
            var upgradeProrationFetchTimer = null;

            function syncUpgradeSummaryFromApi(qty) {
                if (!lastApiProration) return;
                var selected = document.querySelector('.upgrade-plan-radio:checked');
                if (!selected) return;
                var unit = parseFloat(selected.dataset.price || '0');
                var newPlanPrice = unit * parseQty(qty);
                var displayProRata = parseFloat(
                    lastApiProration.pro_rata_adjustment ?? lastApiProration.remaining_value ?? 0
                ) || 0;
                var appliedProRata = parseFloat(
                    lastApiProration.applied_pro_rata ?? Math.min(displayProRata, newPlanPrice)
                ) || 0;
                var taxable = Math.max(0, newPlanPrice - appliedProRata);
                var gst = taxable * 0.18;
                var payable = taxable + gst;
                var creditLicenses = parseInt(lastApiProration.credit_licenses, 10);
                if (isNaN(creditLicenses) || creditLicenses < 1) {
                    creditLicenses = creditLicensesForQty(qty);
                }

                subtotalSummary.textContent = formatCurrency(newPlanPrice);
                proRataSummary.textContent = '- ' + formatCurrency(displayProRata);
                if (taxableSummary) taxableSummary.textContent = formatCurrency(taxable);
                if (gstLabel) gstLabel.textContent = 'GST (18% on ' + formatCurrency(taxable) + ')';
                gstSummary.textContent = formatCurrency(gst);
                totalSummary.textContent = formatCurrency(payable);
                if (summaryProrationHint) {
                    summaryProrationHint.textContent = buildProrationFormula(creditLicenses, displayProRata);
                }
            }

            function refreshUpgradeProrationFromApi() {
                if (!upgradeApiUrl) return;
                var selected = document.querySelector('.upgrade-plan-radio:checked');
                var qty = upgradeQtyInput ? parseQty(upgradeQtyInput.value) : baseSubscriptionLicenses;
                var planId = selected ? parseInt(selected.value, 10) : 0;
                var url = buildUpgradeApiUrl(qty, planId > 0 ? planId : null);
                if (!url) return;

                clearTimeout(upgradeProrationFetchTimer);
                upgradeProrationFetchTimer = setTimeout(function() {
                    fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        credentials: 'same-origin'
                    })
                    .then(function(response) {
                        return response.ok ? response.json() : null;
                    })
                    .then(function(payload) {
                        if (!payload || !payload.data) return;
                        var proration = payload.data.proration || payload.data.current_plan;
                        if (!proration) return;
                        applyProrationFromApi(proration);
                        if (modeUpgrade && modeUpgrade.checked) {
                            syncUpgradeSummaryFromApi(qty);
                        }
                    })
                    .catch(function() {});
                }, 200);
            }

            function parseQty(val) {
                var n = parseInt(String(val ?? '').trim(), 10);
                return (isNaN(n) || n < 1) ? 1 : n;
            }

            function clampQtyToPlanLimit(qty, planLimit) {
                qty = parseQty(qty);
                if (planLimit > 0 && qty > planLimit) {
                    qty = planLimit;
                }
                return qty;
            }

            function applyQtyInputMax(input, planLimit) {
                if (!input) return;
                if (planLimit > 0) {
                    input.setAttribute('max', String(planLimit));
                } else {
                    input.removeAttribute('max');
                }
            }

            function updateQtyLimitHint(hintEl, planLimit) {
                if (!hintEl) return;
                if (planLimit > 0) {
                    hintEl.textContent = 'Max ' + planLimit + ' licenses';
                    hintEl.classList.remove('d-none');
                } else {
                    hintEl.textContent = '';
                    hintEl.classList.add('d-none');
                }
            }

            function escapeHtml(value) {
                return String(value == null ? '' : value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function setUpgradePlansError(message) {
                if (!upgradePlansError) return;
                if (message) {
                    upgradePlansError.textContent = message;
                    upgradePlansError.classList.remove('d-none');
                } else {
                    upgradePlansError.textContent = '';
                    upgradePlansError.classList.add('d-none');
                }
            }

            function bindUpgradePlanRadios() {
                upgradePlanRadios = document.querySelectorAll('.upgrade-plan-radio');
                upgradePlanRadios.forEach(function(radio) {
                    radio.addEventListener('change', refreshUpgradeSummary);
                });
            }

            function renderUpgradePlans(plans) {
                if (!upgradePlanCards) return;
                if (!Array.isArray(plans) || plans.length === 0) {
                    hasUpgradePlans = false;
                    upgradePlanCards.innerHTML = '<div class="w-100"><div class="alert alert-info mb-0">No higher plan available for upgrade.</div></div>';
                    bindUpgradePlanRadios();
                    refreshUpgradeSummary();
                    refreshMode();
                    return;
                }

                hasUpgradePlans = true;
                upgradePlanCards.innerHTML = plans.map(function(plan, index) {
                    var planId = parseInt(plan.plan_id || plan.id || 0, 10) || 0;
                    var planName = escapeHtml(plan.name || plan.title || '-');
                    var price = Number(plan.price || 0);
                    var storage = escapeHtml(plan.storage_gb || plan.storage || '-');
                    var customerLimit = Math.max(0, parseInt(plan.customer_limit || 0, 10) || 0);
                    return '' +
                        '<div class="plan-slide">' +
                        '  <label class="w-100 border rounded p-3 plan-radio-card" style="cursor:pointer;">' +
                        '    <input type="radio" class="form-check-input me-2 upgrade-plan-radio" name="upgrade_plan_id" value="' + planId + '"' +
                        '      data-name="' + planName + '"' +
                        '      data-storage="' + storage + '"' +
                        '      data-price="' + price + '"' +
                        '      data-customer-limit="' + customerLimit + '"' + (index === 0 ? ' checked' : '') + '>' +
                        '    <span class="fw-semibold d-block">' + planName + '</span>' +
                        '    <small class="text-muted d-block">Storage: ' + storage + '</small>' +
                        '    <small class="text-muted d-block">Unit: ' + formatCurrency(price) + '</small>' +
                        (customerLimit > 0 ? '    <small class="text-muted d-block">Max licenses: ' + customerLimit + '</small>' : '') +
                        '  </label>' +
                        '</div>';
                }).join('');

                bindUpgradePlanRadios();
                var firstRadio = upgradePlanCards.querySelector('.upgrade-plan-radio');
                if (firstRadio) {
                    firstRadio.checked = true;
                }
                if (upgradeOnly || (modeUpgrade && modeUpgrade.checked)) {
                    refreshUpgradeSummary();
                    refreshMode();
                }
            }

            function loadUpgradePlans() {
                if (!upgradeApiUrl || !upgradePlanCards) return;
                if (!upgradeOnly && !(modeUpgrade && modeUpgrade.checked)) return;
                setUpgradePlansError('');
                fetch(upgradeApiUrl, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin'
                })
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('Failed to load upgrade plans.');
                    }
                    return response.json();
                })
                .then(function(payload) {
                    if (payload && payload.data) {
                        applyProrationFromApi(payload.data.proration || payload.data.current_plan);
                    }
                    var plans = payload && payload.data && Array.isArray(payload.data.upgrade_plans)
                        ? payload.data.upgrade_plans
                        : [];
                    renderUpgradePlans(plans);
                })
                .catch(function(error) {
                    setUpgradePlansError(error && error.message ? error.message : 'Failed to load upgrade plans.');
                });
            }

            function bindQtyInput(input, onUpdate, getPlanLimit) {
                if (!input || !onUpdate) return;
                input.addEventListener('input', onUpdate);
                input.addEventListener('change', onUpdate);
                input.addEventListener('blur', function() {
                    var limit = typeof getPlanLimit === 'function' ? getPlanLimit() : 0;
                    input.value = clampQtyToPlanLimit(input.value, limit);
                    onUpdate();
                });
            }

            function refreshTotals() {
                if (!qtyInput) return;
                var rawQty = parseQty(qtyInput.value);
                var qty = clampQtyToPlanLimit(qtyInput.value, renewPlanLimit);
                var overPlanLimit = renewPlanLimit > 0 && rawQty > renewPlanLimit;
                var subtotal = unitPrice * qty;
                var proRata = 0;
                var taxable = Math.max(0, subtotal - proRata);
                var gst = taxable * 0.18;
                var total = Math.max(0, taxable + gst);
                var canProceed = total <= (renewFundsAvailable + 0.01) && !overPlanLimit;
                qtyInput.value = qty;
                qtyHidden.value = qty;
                applyQtyInputMax(qtyInput, renewPlanLimit);
                if (renewPlanLimitAlert) {
                    renewPlanLimitAlert.classList.toggle('d-none', !overPlanLimit && qty <= renewPlanLimit);
                    if (renewPlanLimit > 0 && overPlanLimit) {
                        renewPlanLimitAlert.textContent = 'Licenses cannot exceed plan limit of ' + renewPlanLimit + '.';
                    }
                }
                rowTotal.textContent = formatCurrency(subtotal);
                if (subtotalTop) subtotalTop.textContent = formatCurrency(subtotal);
                subtotalSummary.textContent = formatCurrency(subtotal);
                proRataSummary.textContent = '- ' + formatCurrency(proRata);
                if (taxableSummary) taxableSummary.textContent = formatCurrency(taxable);
                if (gstLabel) gstLabel.textContent = 'GST (18% on ' + formatCurrency(taxable) + ')';
                gstSummary.textContent = formatCurrency(gst);
                totalSummary.textContent = formatCurrency(total);
                if (renewProceedBlock) renewProceedBlock.classList.toggle('d-none', !canProceed);
                if (renewInsufficientAlert) renewInsufficientAlert.classList.toggle('d-none', canProceed);
            }

            function resetOrderSummary() {
                subtotalSummary.textContent = formatCurrency(0);
                proRataSummary.textContent = '- ' + formatCurrency(0);
                if (taxableSummary) taxableSummary.textContent = formatCurrency(0);
                if (gstLabel) gstLabel.textContent = 'GST (18% on ' + formatCurrency(0) + ')';
                gstSummary.textContent = formatCurrency(0);
                totalSummary.textContent = formatCurrency(0);
                if (summaryProrationHint) {
                    summaryProrationHint.textContent = buildProrationFormula(
                        baseSubscriptionLicenses,
                        computeRemainingCredit()
                    );
                }
                updateProrationDisplay(baseSubscriptionLicenses);
            }

            function refreshPlanPickSummary(opts) {
                // Shared summary updater for the upgrade & downgrade panels.
                if (!opts.qtyInput || !opts.submitBtn) return;
                var selected = document.querySelector(opts.radioSelector + ':checked');
                var planLimit = selected ? parseInt(selected.dataset.customerLimit || '0', 10) : 0;
                if (isNaN(planLimit) || planLimit < 0) planLimit = 0;
                var rawQty = parseQty(opts.qtyInput.value);
                var qty = clampQtyToPlanLimit(opts.qtyInput.value, planLimit);
                var overPlanLimit = planLimit > 0 && rawQty > planLimit;
                opts.qtyInput.value = qty;
                applyQtyInputMax(opts.qtyInput, planLimit);
                updateQtyLimitHint(opts.qtyLimitHint, planLimit);
                if (opts.qtyHidden) opts.qtyHidden.value = qty;
                opts.radios.forEach(function(radio) {
                    var card = radio.closest('.plan-radio-card');
                    if (card) card.classList.toggle('selected', radio.checked);
                });

                if (!selected) {
                    opts.submitBtn.disabled = true;
                    if (opts.planIdHidden) opts.planIdHidden.value = '';
                    if (opts.nameEl) opts.nameEl.textContent = '-';
                    if (opts.storageEl) opts.storageEl.textContent = 'Storage: -';
                    if (opts.priceEl) opts.priceEl.textContent = 'Unit Price: ₹0.00';
                    if (opts.limitEl) opts.limitEl.textContent = 'Max licenses: -';
                    if (opts.subtotalEl) opts.subtotalEl.textContent = 'Subtotal: ₹0.00';
                    if (opts.direction === 'upgrade' || opts.direction === 'downgrade') {
                        resetOrderSummary();
                    }
                    return;
                }

                var unit = parseFloat(selected.dataset.price || '0');
                var newPlanPrice = unit * qty;
                var applyProRata = (opts.direction !== 'downgrade');
                var creditLicenses = creditLicensesForQty(qty);
                var creditForUpgrade = computeCreditForQty(qty);
                var appliedProRata = applyProRata ? Math.min(creditForUpgrade, newPlanPrice) : 0;
                var displayProRata = applyProRata ? creditForUpgrade : 0;
                var taxable = Math.max(0, newPlanPrice - appliedProRata);
                var gst = taxable * 0.18;
                var payable = taxable + gst;

                if (opts.direction === 'upgrade') {
                    updateProrationDisplay(qty);
                    if (summaryProrationHint) {
                        summaryProrationHint.textContent = buildProrationFormula(
                            creditLicenses,
                            creditForUpgrade
                        );
                    }
                    refreshUpgradeProrationFromApi();
                }

                if (opts.planIdHidden) opts.planIdHidden.value = selected.value;
                if (opts.nameEl) opts.nameEl.textContent = selected.dataset.name || '-';
                if (opts.storageEl) opts.storageEl.textContent = 'Storage: ' + (selected.dataset.storage || '-');
                if (opts.priceEl) opts.priceEl.textContent = 'Unit Price: ' + formatCurrency(unit);
                if (opts.limitEl) opts.limitEl.textContent = planLimit > 0 ? ('Max licenses: ' + planLimit) : 'Max licenses: Unlimited';
                if (opts.subtotalEl) opts.subtotalEl.textContent = 'Subtotal: ' + formatCurrency(newPlanPrice);

                subtotalSummary.textContent = formatCurrency(newPlanPrice);
                proRataSummary.textContent = '- ' + formatCurrency(displayProRata);
                if (taxableSummary) taxableSummary.textContent = formatCurrency(taxable);
                if (gstLabel) gstLabel.textContent = 'GST (18% on ' + formatCurrency(taxable) + ')';
                gstSummary.textContent = formatCurrency(gst);
                totalSummary.textContent = formatCurrency(payable);
                if (creditLimitHint) {
                    creditLimitHint.classList.toggle('text-success', payable <= maxUpgradePayable);
                    creditLimitHint.classList.toggle('text-danger', payable > maxUpgradePayable);
                }

                if ({{ !empty($hasPendingUpgrade) ? 'true' : 'false' }}) {
                    opts.submitBtn.disabled = true;
                } else {
                    opts.submitBtn.disabled = payable > maxUpgradePayable || overPlanLimit;
                }
                if (renewPlanLimitAlert && (modeUpgrade && modeUpgrade.checked || modeDowngrade && modeDowngrade.checked)) {
                    renewPlanLimitAlert.classList.toggle('d-none', !overPlanLimit);
                    if (overPlanLimit && planLimit > 0) {
                        renewPlanLimitAlert.textContent = 'Licenses cannot exceed plan limit of ' + planLimit + '.';
                    }
                }
            }

            function refreshUpgradeSummary() {
                refreshPlanPickSummary({
                    qtyInput: upgradeQtyInput,
                    qtyHidden: upgradeQtyHidden,
                    submitBtn: upgradeProceedBtn,
                    planIdHidden: upgradePlanIdHidden,
                    nameEl: upgradeName,
                    storageEl: upgradeStorage,
                    priceEl: upgradePrice,
                    limitEl: upgradeLimit,
                    subtotalEl: upgradeSubtotal,
                    radios: upgradePlanRadios,
                    radioSelector: '.upgrade-plan-radio',
                    direction: 'upgrade',
                    qtyLimitHint: upgradeQtyLimitHint,
                });
            }

            function refreshDowngradeSummary() {
                refreshPlanPickSummary({
                    qtyInput: downgradeQtyInput,
                    qtyHidden: downgradeQtyHidden,
                    submitBtn: downgradeProceedBtn,
                    planIdHidden: downgradePlanIdHidden,
                    nameEl: downgradeName,
                    storageEl: downgradeStorage,
                    priceEl: downgradePrice,
                    radios: downgradePlanRadios,
                    radioSelector: '.downgrade-plan-radio',
                    direction: 'downgrade',
                    qtyLimitHint: downgradeQtyLimitHint,
                });
            }

            function refreshMode() {
                var isRenew = !upgradeOnly && modeRenew && modeRenew.checked;
                var isUpgrade = upgradeOnly || (modeUpgrade && modeUpgrade.checked);
                var isDowngrade = modeDowngrade && modeDowngrade.checked;
                if (renewSection) renewSection.style.display = isRenew ? '' : 'none';
                if (upgradeSection) upgradeSection.style.display = isUpgrade ? '' : 'none';
                if (downgradeCard) downgradeCard.style.display = isDowngrade ? '' : 'none';
                if (topSubtotalWrap) topSubtotalWrap.style.display = isRenew ? 'none' : 'flex';
                if (orderSummaryCard) orderSummaryCard.style.display = '';

                var upgradeForm = document.getElementById('upgradeForm');
                if (upgradeForm) upgradeForm.style.display = isUpgrade ? '' : 'none';
                var downgradeForm = document.getElementById('downgradeForm');
                if (downgradeForm) downgradeForm.style.display = isDowngrade ? '' : 'none';
                if (renewProceedBlock) renewProceedBlock.style.display = isRenew ? '' : 'none';
                if (renewInsufficientAlert && !isRenew) renewInsufficientAlert.classList.add('d-none');

                if (renewPlanLimitAlert && isRenew) {
                    renewPlanLimitAlert.classList.add('d-none');
                }
                if (isRenew) {
                    refreshTotals();
                } else if (isUpgrade) {
                    refreshUpgradeSummary();
                } else if (isDowngrade) {
                    refreshDowngradeSummary();
                }
            }
            if (modeRenew) modeRenew.addEventListener('change', refreshMode);
            if (modeUpgrade) {
                modeUpgrade.addEventListener('change', function() {
                    if (modeUpgrade.checked) {
                        loadUpgradePlans();
                    }
                    refreshMode();
                });
            }
            if (modeDowngrade) modeDowngrade.addEventListener('change', refreshMode);

            var modeFromQuery = (new URLSearchParams(window.location.search).get('mode') || '').toLowerCase();
            if (!upgradeOnly) {
                if (modeFromQuery === 'upgrade' && hasUpgradePlans && modeUpgrade) {
                    modeUpgrade.checked = true;
                } else if (modeFromQuery === 'downgrade' && hasDowngradePlans && modeDowngrade) {
                    modeDowngrade.checked = true;
                } else if (modeFromQuery === 'renew' && modeRenew) {
                    modeRenew.checked = true;
                }
            }

            if (upgradePlanRadios.length) {
                upgradePlanRadios[0].checked = true;
            }
            if (downgradePlanRadios.length) {
                downgradePlanRadios[0].checked = true;
            }
            if (renewQtyMinus && renewQtyPlus && qtyInput) {
                renewQtyMinus.addEventListener('click', function() {
                    qtyInput.value = Math.max(1, parseQty(qtyInput.value) - 1);
                    refreshTotals();
                });
                renewQtyPlus.addEventListener('click', function() {
                    var next = parseQty(qtyInput.value) + 1;
                    if (renewPlanLimit > 0) next = Math.min(next, renewPlanLimit);
                    qtyInput.value = next;
                    refreshTotals();
                });
            }
            bindQtyInput(qtyInput, refreshTotals, function() { return renewPlanLimit; });
            if (upgradeQtyMinus && upgradeQtyPlus) {
                upgradeQtyMinus.addEventListener('click', function() {
                    upgradeQtyInput.value = Math.max(1, parseQty(upgradeQtyInput.value) - 1);
                    refreshUpgradeSummary();
                });
                upgradeQtyPlus.addEventListener('click', function() {
                    var selected = document.querySelector('.upgrade-plan-radio:checked');
                    var limit = selected ? parseInt(selected.dataset.customerLimit || '0', 10) : 0;
                    var next = parseQty(upgradeQtyInput.value) + 1;
                    if (limit > 0) next = Math.min(next, limit);
                    upgradeQtyInput.value = next;
                    refreshUpgradeSummary();
                });
            }
            bindQtyInput(upgradeQtyInput, refreshUpgradeSummary, function() {
                var selected = document.querySelector('.upgrade-plan-radio:checked');
                return selected ? parseInt(selected.dataset.customerLimit || '0', 10) : 0;
            });
            if (downgradeQtyMinus && downgradeQtyPlus) {
                downgradeQtyMinus.addEventListener('click', function() {
                    downgradeQtyInput.value = Math.max(1, parseQty(downgradeQtyInput.value) - 1);
                    refreshDowngradeSummary();
                });
                downgradeQtyPlus.addEventListener('click', function() {
                    var selected = document.querySelector('.downgrade-plan-radio:checked');
                    var limit = selected ? parseInt(selected.dataset.customerLimit || '0', 10) : 0;
                    var next = parseQty(downgradeQtyInput.value) + 1;
                    if (limit > 0) next = Math.min(next, limit);
                    downgradeQtyInput.value = next;
                    refreshDowngradeSummary();
                });
            }
            bindQtyInput(downgradeQtyInput, refreshDowngradeSummary, function() {
                var selected = document.querySelector('.downgrade-plan-radio:checked');
                return selected ? parseInt(selected.dataset.customerLimit || '0', 10) : 0;
            });
            bindUpgradePlanRadios();
            downgradePlanRadios.forEach(function(radio) {
                radio.addEventListener('change', refreshDowngradeSummary);
            });
            var isUpgradePage = upgradeOnly || (modeUpgrade && modeUpgrade.checked);
            if (isUpgradePage) {
                refreshMode();
                updateProrationDisplay(upgradeQtyInput ? upgradeQtyInput.value : null);
                loadUpgradePlans();
            } else {
                refreshTotals();
                refreshMode();
                updateProrationDisplay(null);
            }
        })();
    </script>
@endsection
