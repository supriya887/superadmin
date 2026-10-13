<?php

namespace App\Http\Controllers\SuperAdmin\B2B\Concerns;

use App\Http\Controllers\SuperAdmin\BillingController;
use App\Models\AccountMaster;
use App\Models\AdminSetting;
use App\Models\InvoiceMst;
use App\Models\OrderPoDetail;
use App\Models\Partner;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Plan;
use App\Services\B2bSubscriptionRenewalService;
use App\Services\PartnerCreditService;
use App\Services\RenewalService;
use App\Services\InvoiceService;
use App\Services\SmsService;
use App\Services\SandboxEInvoiceService;
use App\Mail\PlanRenewedMail;
use App\Mail\PlanUpgradedMail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Support\OrderPrefixResolver;

trait PartnerCustomerSubscriptionTrait
{
    public function subscriptions($partner_id, $customer_id)
    {
        $data = [];
        $data['partner_id'] = Crypt::decrypt($partner_id);
        $data['customer_id'] = Crypt::decrypt($customer_id);
        $data['encryptedPartnerId'] = Crypt::encrypt($data['partner_id']);
        $data['partner'] = $this->partner->findOrFail($data['partner_id']);
        $data['customer'] = $this->customer->where('partner_id', $data['partner_id'])->findOrFail($data['customer_id']);
        $data['creditUsed'] = $this->partnerCreditUsed((int) $data['partner_id']);
        $data['creditAvailable'] = max(0, (float) ($data['partner']->credit_limit ?? 0) - $data['creditUsed']);

        $subscriptions = Order::query()
            ->where('user_id', $data['customer']->id)
            ->where('order_type', 'b2b')
            ->select('id', 'user_id', 'plan_id', 'price', 'quantity', 'duration', 'created_at', 'domain_name', 'status')
            ->orderByDesc('created_at')
            ->get();

        $plansById = Plan::query()
            ->whereIn('id', $subscriptions->pluck('plan_id')->unique()->filter()->values()->all() ?: [-1])
            ->get(['id', 'name', 'provider_id'])
            ->keyBy('id');
        $subscriptions->each(function ($sub) use ($plansById) {
            $plan = $plansById->get((int) $sub->plan_id);
            $sub->plan_title = $plan ? (string) ($plan->name ?? '') : null;
            $sub->provider_id = $plan?->provider_id;
        });

        $data['subscriptionsByDomain'] = $subscriptions->groupBy('domain_name');
        $data['subscriptionsTableUrl'] = route('superadmin.b2b_partners.customers.subscriptions_table', [
            'partner_id' => $partner_id,
            'customer_id' => $customer_id,
        ]);

        // print_r($data['subscriptionsByDomain']);
        // die();

        return view('superadmin.b2b_partners.customers.subscriptions', $data);
    }

    public function subscriptionsTable($partner_id, $customer_id, Request $request)
    {
        $partnerId = Crypt::decrypt($partner_id);
        $customerId = Crypt::decrypt($customer_id);

        $partner = $this->partner->findOrFail($partnerId);
        $customer = $this->customer->where('partner_id', $partnerId)->findOrFail($customerId);

        $draw = (int) ($request->input('draw') ?? 1);
        $start = max(0, (int) ($request->input('start') ?? 0));
        $length = max(1, (int) ($request->input('length') ?? 10));

        $columns = [
            0 => 'id',
            1 => 'domain_name',
            2 => 'plan_id',
            3 => 'order_category',
            4 => 'quantity',
            5 => 'total_amount',
            6 => 'status',
            7 => 'created_at',
        ];
        $orderColIdx = (int) data_get($request->input('order', []), '0.column', 7);
        $orderDir = strtolower((string) data_get($request->input('order', []), '0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $orderBy = $columns[$orderColIdx] ?? 'created_at';

        $baseQuery = Order::query()
            ->where('partner_id', $partnerId)
            ->where('user_id', $customerId)
            ->whereRaw('LOWER(COALESCE(order_type, \'\')) = ?', ['b2b'])
            ->whereRaw('LOWER(COALESCE(status, \'pending\')) != ?', ['draft']);

        $search = trim((string) data_get($request->input('search', []), 'value', ''));
        $query = clone $baseQuery;
        if ($search !== '') {
            $matchingPlanIds = Plan::query()
                ->where('name', 'like', '%' . $search . '%')
                ->pluck('id');

            $query->where(function ($q) use ($search, $matchingPlanIds) {
                $q->where('domain_name', 'like', '%' . $search . '%')
                    ->orWhere('status', 'like', '%' . $search . '%')
                    ->orWhere('order_category', 'like', '%' . $search . '%');
                if ($matchingPlanIds->isNotEmpty()) {
                    $q->orWhereIn('plan_id', $matchingPlanIds);
                }
            });
        }

        $filterOrderId = trim((string) $request->input('filter_order_id', ''));
        $filterDomain = trim((string) $request->input('filter_domain', ''));
        $filterPlan = trim((string) $request->input('filter_plan', ''));
        $filterStatus = strtolower(trim((string) $request->input('filter_status', '')));
        $filterCategory = strtolower(trim((string) $request->input('filter_category', '')));
        $filterFromDate = trim((string) $request->input('filter_from_date', ''));
        $filterToDate = trim((string) $request->input('filter_to_date', ''));

        if ($filterOrderId !== '') {
            $query->where(function ($q) use ($filterOrderId) {
                if (ctype_digit($filterOrderId)) {
                    $q->where('id', (int) $filterOrderId);
                }
                $q->orWhere('order_no', 'like', '%' . addcslashes($filterOrderId, '%_\\') . '%');
            });
        }
        if ($filterDomain !== '') {
            $query->where('domain_name', 'like', '%' . $filterDomain . '%');
        }
        if ($filterPlan !== '') {
            $filterPlanIds = Plan::query()
                ->where('name', 'like', '%' . $filterPlan . '%')
                ->pluck('id');
            if ($filterPlanIds->isNotEmpty()) {
                $query->whereIn('plan_id', $filterPlanIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }
        if ($filterStatus !== '') {
            $query->whereRaw('LOWER(COALESCE(status, \'pending\')) = ?', [$filterStatus]);
        }
        if ($filterCategory !== '') {
            $query->whereRaw('LOWER(COALESCE(order_category, \'new\')) = ?', [$filterCategory]);
        }
        if ($filterFromDate !== '') {
            $query->whereDate('created_at', '>=', $filterFromDate);
        }
        if ($filterToDate !== '') {
            $query->whereDate('created_at', '<=', $filterToDate);
        }

        $recordsTotal = (clone $baseQuery)->count();
        $recordsFiltered = (clone $query)->count();

        // Only the newest completed/active row per domain may renew — older
        // subscriptions on the same domain drop out of the renewal funnel
        // once a successor order exists (renew / upgrade / downgrade).
        $latestRenewableOrderIds = Order::query()
            ->where('partner_id', $partnerId)
            ->where('user_id', $customerId)
            ->whereRaw('LOWER(COALESCE(order_type, \'\')) = ?', ['b2b'])
            ->whereIn(DB::raw('LOWER(COALESCE(status, \'\'))'), ['completed', 'active'])
            ->selectRaw('MAX(id) as latest_id')
            ->groupBy(DB::raw('LOWER(TRIM(COALESCE(domain_name, \'\')))'))
            ->pluck('latest_id')
            ->all();
        $latestRenewableSet = array_fill_keys(array_map('intval', $latestRenewableOrderIds), true);

        // Only select columns that exist on pgsql `orders` (no `amount` column).
        $ordersSchema = Schema::connection('pgsql_billing');
        $selectCols = ['id', 'order_no', 'domain_name', 'plan_id', 'quantity', 'price', 'status', 'created_at'];
        foreach (['order_category', 'total_amount', 'subtotal'] as $optionalCol) {
            if ($ordersSchema->hasColumn('orders', $optionalCol)) {
                $selectCols[] = $optionalCol;
            }
        }
        if (!$ordersSchema->hasColumn('orders', $orderBy)) {
            $orderBy = 'created_at';
        }

        $rows = $query
            ->orderBy($orderBy, $orderDir)
            ->offset($start)
            ->limit($length)
            ->get($selectCols);

        $planNames = Plan::query()
            ->whereIn('id', $rows->pluck('plan_id')->filter()->unique()->values())
            ->pluck('name', 'id');

        $data = $rows->map(function ($row) use ($partner, $partner_id, $customer_id, $latestRenewableSet, $planNames) {
            $category = ucfirst((string) ($row->order_category ?? 'new'));
            $statusText = ucfirst((string) ($row->status ?? 'pending'));
            $statusBadge = '<span class="badge bg-light text-dark border">' . e($statusText) . '</span>';
            $amount = (float) ($row->total_amount
                ?? $row->subtotal
                ?? ((float) ($row->price ?? 0) * (int) ($row->quantity ?? 1)));

            $actions = '<div class="d-flex justify-content-end gap-1">';
            $actions .= '<a class="btn btn-light btn-icon btn-sm rounded-circle" title="View Order" href="' . route('superadmin.orders.details', (int) $row->id) . '"><i class="ti ti-eye fs-lg"></i></a>';
            $actions .= '<a class="btn btn-light btn-icon btn-sm rounded-circle" title="Billing" href="' . route('superadmin.b2b_partners.customers.billing', [
                'partner_id' => $partner_id,
                'customer_id' => $customer_id,
            ]) . '"><i class="ti ti-file-invoice fs-lg"></i></a>';

            $statusLower = strtolower((string) ($row->status ?? ''));
            $isEligibleStatus = in_array($statusLower, ['completed', 'active'], true);
            $isLatestForDomain = !empty($latestRenewableSet[(int) $row->id]);

            if ($isEligibleStatus && $isLatestForDomain) {
                $actions .= '<a class="btn btn-primary btn-sm" title="Renew" href="' . route('superadmin.b2b_partners.customers.update_plan', [
                    'partner_id' => Crypt::encrypt($partner->id),
                    'subscription_id' => Crypt::encrypt((int) $row->id),
                ]) . '">Renew</a>';
            }
            $actions .= '</div>';

            return [
                'id' => (string) ($row->order_no ?: '—'),
                'domain_name' => $row->domain_name ?: '-',
                'plan_name' => (string) ($planNames[(int) ($row->plan_id ?? 0)] ?? '-'),
                'order_category' => $category,
                'quantity' => (int) ($row->quantity ?? 1),
                'amount' => '₹' . number_format($amount, 2),
                'status' => $statusBadge,
                'created_at' => !empty($row->created_at) ? Carbon::parse($row->created_at)->format('d M Y, h:i A') : '-',
                'action' => $actions,
            ];
        })->values();

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function subscriptionsByPartner($partner_id)
    {
        $partnerId = Crypt::decrypt($partner_id);
        $partner = $this->partner->findOrFail($partnerId);

        $analytics = $this->renewalService->partnerCustomerAnalytics($partnerId);
        $encryptedPartnerId = Crypt::encrypt($partnerId);

        $rows = collect($analytics['customers'])->map(function ($customer) use ($encryptedPartnerId) {
            return (object) [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'mobile' => $customer->mobile,
                'domain_count' => (int) ($customer->domain_count ?? 0),
                'total_orders_count' => (int) ($customer->total_orders_count ?? 0),
                'status' => $customer->status,
                'view_url' => route('superadmin.b2b_partners.customers.subscriptions', [
                    'partner_id' => $encryptedPartnerId,
                    'customer_id' => Crypt::encrypt($customer->id),
                ]),
            ];
        });

        return view('superadmin.b2b_partners.customers.subscriptions_dashboard', [
            'title' => 'Partner Customer Analytics',
            'partner' => $partner,
            'encryptedPartnerId' => $encryptedPartnerId,
            'totalCustomers' => (int) ($analytics['total_customers'] ?? 0),
            'totalDomains' => (int) ($analytics['total_domains'] ?? 0),
            'totalOrders' => (int) ($analytics['total_orders'] ?? 0),
            'revenueGenerated' => (float) ($analytics['revenue_generated'] ?? 0),
            'chartLabels' => $analytics['chart_labels'] ?? [],
            'chartValues' => $analytics['chart_values'] ?? [],
            'customerRows' => $rows,
        ]);
    }

    public function billing($partner_id, $customer_id)
    {
        $data = [];
        $data['partner_id'] = Crypt::decrypt($partner_id);
        $data['customer_id'] = Crypt::decrypt($customer_id);
        $data['encryptedPartnerId'] = Crypt::encrypt($data['partner_id']);
        $data['partner'] = $this->partner->findOrFail($data['partner_id']);
        $data['customer'] = $this->customer->where('partner_id', $data['partner_id'])->findOrFail($data['customer_id']);

        // pgsql `orders` has no `plan_name` (hint suggested bank_name) — resolve via plans table.
        $ordersSchema = Schema::connection('pgsql_billing');
        $selectCols = ['id', 'order_no', 'plan_id', 'price', 'created_at', 'status'];
        foreach (['order_type', 'total_amount', 'subtotal', 'credit_used', 'plan_name'] as $optionalCol) {
            if ($ordersSchema->hasColumn('orders', $optionalCol)) {
                $selectCols[] = $optionalCol;
            }
        }

        $orders = Order::query()
            ->where('user_id', $data['customer_id'])
            ->where('partner_id', $data['partner_id'])
            ->whereRaw('LOWER(COALESCE(order_type, \'\')) = ?', ['b2b'])
            ->orderByDesc('id')
            ->get($selectCols);

        $planNames = Plan::query()
            ->whereIn('id', $orders->pluck('plan_id')->filter()->unique()->values())
            ->pluck('name', 'id');

        $data['orders'] = $orders->map(function ($order) use ($planNames) {
            $amount = (float) ($order->total_amount ?? $order->subtotal ?? $order->price ?? 0);
            $planName = trim((string) ($order->plan_name ?? ''));
            if ($planName === '') {
                $planName = (string) ($planNames[(int) ($order->plan_id ?? 0)] ?? '-');
            }

            return (object) [
                'id' => (int) $order->id,
                'order_no' => (string) ($order->order_no ?? ''),
                'plan_name' => $planName !== '' ? $planName : '-',
                'order_type' => (string) ($order->order_type ?? 'b2c'),
                'amount' => $amount,
                'credit_used' => (float) ($order->credit_used ?? 0),
                'created_at' => $order->created_at,
                'status' => (string) ($order->status ?? 'pending'),
            ];
        });

        return view('superadmin.b2b_partners.customers.billing', $data);
    }

    public function updatePlan($partner_id, $subscription_id)
    {
        $data = [];
        $data['partner_id'] = Crypt::decrypt($partner_id);
        $data['subscription_id'] = Crypt::decrypt($subscription_id);
        $data['encryptedPartnerId'] = Crypt::encrypt($data['partner_id']);

        // You would fetch the actual subscription and customer details here
        // For now, let's mock some data or fetch minimal for display
        $data['partner'] = $this->partner->findOrFail($data['partner_id']);
        // Assuming you need customer details related to this subscription as well
        // For now, we'll just pass the IDs.
        // In a real scenario, you'd likely eager load customer with subscription or fetch directly.

        $subscription = Order::query()
            ->where('id', $data['subscription_id'])
            ->where('order_type', 'b2b')
            ->firstOrFail();

        $currentPlan = Plan::query()->find($subscription->plan_id);
        if ($currentPlan) {
            $subscription->plan_title = (string) ($currentPlan->name ?? $currentPlan->title ?? '');
            $subscription->provider_id = $currentPlan->provider_id;
        }

        $unitPrice = (float) ($subscription->price ?? 0);
        if ($unitPrice <= 0 && $currentPlan) {
            $unitPrice = (float) ($currentPlan->price ?? 0);
        }
        $quantity = (int) ($subscription->quantity ?? 0);
        if ($quantity <= 0) {
            $quantity = 1;
        }

        // Push normalized values back onto the subscription so the Blade view's
        // existing $subscription->price / quantity / total_amount references work.
        $subscription->price = $unitPrice;
        $subscription->quantity = $quantity;

        $renewSubtotal = round($unitPrice * $quantity, 2);
        $storedDiscount = (float) ($subscription->discount_amount ?? 0);
        $renewTaxable = max(0, $renewSubtotal - $storedDiscount);
        $renewGst = round($renewTaxable * 0.18, 2);
        $renewTotal = round($renewTaxable + $renewGst, 2);

        // Override total_amount/tax_amount when missing so the order summary card
        // doesn't render ₹0.00 for plans that simply weren't priced on the row.
        if ((float) ($subscription->tax_amount ?? 0) <= 0) {
            $subscription->tax_amount = $renewGst;
        }
        if ((float) ($subscription->total_amount ?? 0) <= 0) {
            $subscription->total_amount = $renewTotal;
        }

        $data['subscription'] = $subscription;
        $data['customer'] = $this->customer->findOrFail($subscription->user_id);
        $data['creditUsed'] = $this->partnerCreditUsed((int) $data['partner_id']);
        $data['creditAvailable'] = max(0, (float) ($data['partner']->credit_limit ?? 0) - $data['creditUsed']);
        $data['estimatedProRata'] = $this->renewalService->calculateRemainingCredit($subscription);
        // As requested: derive wallet strictly from partners table.
        $walletBalance = Schema::hasColumn('partners', 'wallet_balance')
            ? (float) ($data['partner']->wallet_balance ?? 0)
            : 0.0;
        // Promote the live ledger balance over the partners.wallet_balance
        // snapshot so the "Available credits" hint and `hasPendingUpgrade`
        // gate stay in lock-step with the upgradeUsingCredit affordability
        // check (which prefers account_masters when available).
        if (Schema::hasTable('account_masters')) {
            $acc = AccountMaster::query()
                ->where('refer_id', (int) $data['partner_id'])
                ->where('type', 'Partner')
                ->where('name', PartnerCreditService::partnerWalletAccountName((int) $data['partner_id']))
                ->first();
            if ($acc) {
                $walletBalance = (float) ($acc->current_balance ?? 0);
            }
        }
        $data['walletBalance'] = $walletBalance;
        $data['maxUpgradePayable'] = $walletBalance + $data['creditAvailable'];

        // Show the "Complete your existing payment…" warning ONLY when the
        // partner has no funds to pay with anyway. With credits in hand the
        // pending upgrade is harmless because the new flow marks plan
        // changes as `completed` upfront — see upgradeUsingCredit().
        $hasStaleUpgrade = $this->renewalService->hasActiveUpgradeForCustomer((int) $subscription->user_id, (int) $subscription->id);
        $data['hasPendingUpgrade'] = $hasStaleUpgrade && $data['maxUpgradePayable'] <= 0;
        $data['renewQtyLocked'] = $quantity;
        $data['renewSubtotal'] = $renewSubtotal;
        $data['renewGst'] = $renewGst;
        $data['renewTotal'] = $renewTotal;
        $data['renewFundsAvailable'] = $walletBalance + $data['creditAvailable'];
        $data['renewCanProceed'] = $data['renewFundsAvailable'] + 0.01 >= $renewTotal;
        $data['planCustomerLimit'] = $currentPlan
            ? max(0, (int) ($currentPlan->customer_limit ?? 0))
            : 0;

        // Build upgrade & downgrade plan lists by sr_no tier.
        // Mirrors the partner API (`PartnerAuthController::upgrade`) so the admin
        // panel and the partner app stay consistent: a plan with a higher
        // numeric sr_no is an upgrade; lower is a downgrade. Plans without
        // sr_no are excluded.
        $upgradePlans = collect();
        $downgradePlans = collect();
        if ($currentPlan && is_numeric($currentPlan->sr_no) && (int) $currentPlan->sr_no > 0) {
            $srNo = (int) $currentPlan->sr_no;
            $providerId = $subscription->provider_id ?? $currentPlan->provider_id ?? null;

            $upgradeQuery = Plan::query()
                ->whereNotNull('sr_no')
                ->whereRaw('CAST(sr_no AS INTEGER) > ?', [$srNo])
                ->where('id', '!=', $currentPlan->id)
                ->where(function ($q) {
                    $q->where('status', 1)->orWhereNull('status');
                })
                ->orderByRaw('CAST(sr_no AS INTEGER) ASC');

            $downgradeQuery = Plan::query()
                ->whereNotNull('sr_no')
                ->whereRaw('CAST(sr_no AS INTEGER) < ?', [$srNo])
                ->whereRaw('CAST(sr_no AS INTEGER) > 0')
                ->where('id', '!=', $currentPlan->id)
                ->where(function ($q) {
                    $q->where('status', 1)->orWhereNull('status');
                })
                ->orderByRaw('CAST(sr_no AS INTEGER) DESC');

            if (!empty($providerId)) {
                $upgradeQuery->where('provider_id', $providerId);
                $downgradeQuery->where('provider_id', $providerId);
            }

            $upgradePlans = $upgradeQuery->get();
            $downgradePlans = $downgradeQuery->get();
        }
        $data['upgradePlans'] = $upgradePlans->values();
        $data['downgradePlans'] = $downgradePlans->values();
        $data['upgradeOnly'] = strtolower((string) request()->query('mode', '')) === 'upgrade';

        return view('superadmin.b2b_partners.customers.update_plan', $data);
    }

    public function upgradeUsingCredit($partner_id, $subscription_id, Request $request)
    {
        $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists(Plan::class, 'id')],
            'quantity' => 'required|integer|min:1',
        ]);

        $partnerId = Crypt::decrypt($partner_id);
        $orderId   = Crypt::decrypt($subscription_id);
        $partner   = $this->partner->findOrFail($partnerId);

        $baseOrder = Order::query()
            ->where('id', $orderId)
            ->where('order_type', 'b2b')
            ->firstOrFail();

        $newPlan     = Plan::query()->findOrFail((int) $request->input('plan_id'));
        $currentPlan = Plan::query()->find($baseOrder->plan_id);
        if (!$currentPlan) {
            return back()->with('error', 'Current plan not found.');
        }

        // Eligibility by sr_no tier (matches the plan listing query).
        $currentSr = is_numeric($currentPlan->sr_no) ? (int) $currentPlan->sr_no : 0;
        $newSr     = is_numeric($newPlan->sr_no) ? (int) $newPlan->sr_no : 0;
        // if ($currentSr <= 0 || $newSr <= 0 || $newSr <= $currentSr) {
        //     return back()->with('error', 'Selected plan is not eligible for upgrade.');
        // }

        $qty = max(1, (int) $request->input('quantity'));
        if ($reject = $this->rejectIfQuantityExceedsPlanLimit($newPlan, $qty)) {
            return $reject;
        }
        $summary   = $this->renewalService->upgradeOrderCalculation([
            'partner_id' => $partnerId,
            'subscription_id' => (int) $baseOrder->id,
            'plan_id' => (int) $newPlan->id,
            'licenses' => $qty,
        ]);
        $creditCheck = PartnerCreditService::validateB2bPurchaseAmount($partnerId, (float) $summary['payable_amount']);
        if (empty($creditCheck['ok'])) {
            return back()->with('error', (string) ($creditCheck['message'] ?? PartnerCreditService::CREDIT_LIMIT_REACHED_MESSAGE));
        }
        $remainingLimit = PartnerCreditService::remainingPurchaseAllowance($partnerId);
        $walletBalance = PartnerCreditService::partnerWalletSnapshot($partnerId);
        $availableCredits = $walletBalance + $remainingLimit;

        // Only block stacking when there is no money to pay with anyway.
        // If credits are available, the partner can finish a paid upgrade
        // straight away — the older pending upgrade is harmless because
        // the new flow marks orders as `completed` upfront.
        if (
            $availableCredits <= 0
            && $this->renewalService->hasActiveUpgradeForCustomer((int) $baseOrder->user_id, (int) $baseOrder->id)
        ) {
            return back()->with('error', 'Complete your existing payment before starting another upgrade.');
        }

        DB::beginTransaction();
        try {
            $newOrder = $this->buildPlanChangeOrder(
                $baseOrder,
                $newPlan,
                $qty,
                $summary,
                $partnerId,
                'upgrade'
            );
            $newOrder->save();

            if (Schema::hasColumn('orders', 'order_category')) {
                $baseOrder->order_category = 'upgraded';
                $baseOrder->updated_at = now();
                $baseOrder->save();
            }

            $this->upsertAutoPoForOrder((int) $newOrder->id, '', (float) $summary['payable_amount']);

            $this->createGstInvoiceForPlanChange($baseOrder, $newOrder, $newPlan, $qty, $summary, 'upgrade');

            PartnerCreditService::syncB2bOrderWalletLedger((int) $newOrder->id, $newOrder->getAttributes());

            DB::commit();

            $this->finalizeB2bCreditOrderAfterSave((int) $newOrder->id, 'upgrade');

            return redirect()
                ->route('superadmin.orders.details', ['id' => (int) $newOrder->id])
                ->with('success', 'Upgrade completed successfully. Order is now active.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Upgrade failed', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
            return back()->with('error', 'Failed to start upgrade: ' . $e->getMessage());
        }
    }

    public function downgradeUsingCredit($partner_id, $subscription_id, Request $request)
    {
        $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists(Plan::class, 'id')],
            'quantity' => 'required|integer|min:1',
        ]);

        $partnerId = Crypt::decrypt($partner_id);
        $orderId   = Crypt::decrypt($subscription_id);
        $partner   = $this->partner->findOrFail($partnerId);

        $baseOrder = Order::query()
            ->where('id', $orderId)
            ->where('order_type', 'b2b')
            ->firstOrFail();

        $newPlan     = Plan::query()->findOrFail((int) $request->input('plan_id'));
        $currentPlan = Plan::query()->find($baseOrder->plan_id);
        if (!$currentPlan) {
            return back()->with('error', 'Current plan not found.');
        }

        // Eligibility by sr_no tier (matches the plan listing query).
        $currentSr = is_numeric($currentPlan->sr_no) ? (int) $currentPlan->sr_no : 0;
        $newSr     = is_numeric($newPlan->sr_no) ? (int) $newPlan->sr_no : 0;
        if ($currentSr <= 0 || $newSr <= 0 || $newSr >= $currentSr) {
            return back()->with('error', 'Selected plan is not eligible for downgrade.');
        }

        $qty = max(1, (int) $request->input('quantity'));
        if ($reject = $this->rejectIfQuantityExceedsPlanLimit($newPlan, $qty)) {
            return $reject;
        }

        // DOWNGRADE pricing: do NOT apply pro-rata. Customer is moving to a
        // cheaper plan, so the leftover value of the old subscription would
        // otherwise wipe the entire bill (Subtotal − Pro-rata = 0). The new
        // plan is charged in full at its catalog price + 18% GST.
        $newPlanPrice = round((float) $newPlan->price * $qty, 2);
        $gstAmount    = $this->renewalService->calculateGst($newPlanPrice);
        $payable      = round($newPlanPrice + $gstAmount, 2);
        $summary = [
            'new_plan_price'      => $newPlanPrice,
            'pro_rata_adjustment' => 0.0,
            'gst_amount'          => $gstAmount,
            'payable_amount'      => $payable,
        ];

        $creditCheck = PartnerCreditService::validateB2bPurchaseAmount($partnerId, (float) $summary['payable_amount']);
        if (empty($creditCheck['ok'])) {
            return back()->with('error', (string) ($creditCheck['message'] ?? PartnerCreditService::CREDIT_LIMIT_REACHED_MESSAGE));
        }
        $remainingLimit = PartnerCreditService::remainingPurchaseAllowance($partnerId);
        $walletBalance = PartnerCreditService::partnerWalletSnapshot($partnerId);
        $availableCredits = $walletBalance + $remainingLimit;

        // Only block stacking when there is no money to pay with anyway.
        // Mirrors the upgrade path: with credits in hand, finish the change.
        if (
            $availableCredits <= 0
            && $this->renewalService->hasActiveUpgradeForCustomer((int) $baseOrder->user_id, (int) $baseOrder->id)
        ) {
            return back()->with('error', 'Complete your existing payment before starting another change.');
        }

        DB::beginTransaction();
        try {
            $newOrder = $this->buildPlanChangeOrder(
                $baseOrder,
                $newPlan,
                $qty,
                $summary,
                $partnerId,
                'downgrade'
            );
            $newOrder->save();

            $this->upsertAutoPoForOrder((int) $newOrder->id, '', (float) $summary['payable_amount']);

            $this->createGstInvoiceForPlanChange($baseOrder, $newOrder, $newPlan, $qty, $summary, 'downgrade');

            PartnerCreditService::syncB2bOrderWalletLedger((int) $newOrder->id, $newOrder->getAttributes());

            DB::commit();

            $this->finalizeB2bCreditOrderAfterSave((int) $newOrder->id, 'upgrade');

            return redirect()
                ->route('superadmin.orders.details', ['id' => (int) $newOrder->id])
                ->with('success', 'Downgrade completed successfully. Order is now active.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Downgrade failed', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
            return back()->with('error', 'Failed to start downgrade: ' . $e->getMessage());
        }
    }

    public function renewUsingCredit($partner_id, $subscription_id, Request $request)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $partnerId = Crypt::decrypt($partner_id);
        $orderId = Crypt::decrypt($subscription_id);
        $partner = $this->partner->findOrFail($partnerId);

        if (!Schema::hasColumn('orders', 'partner_id') || !Schema::hasColumn('orders', 'credit_used')) {
            return back()->with('error', 'Credit fields are missing in orders table. Please run ALTER TABLE for partner_id and credit_used.');
        }

        $baseOrderQuery = Order::query()->where('id', $orderId);
        if (Schema::hasColumn('orders', 'order_type')) {
            $baseOrderQuery->where('order_type', 'b2b');
        }
        $baseOrder = $baseOrderQuery->firstOrFail();

        $inProgress = $this->renewalService->findInProgressRenewalForSubscription(
            (int) $baseOrder->user_id,
            (int) $baseOrder->id
        );
        if ($inProgress) {
            return redirect()
                ->route('superadmin.orders.create.step', ['step' => 2, 'id' => (int) $inProgress->id])
                ->with(
                    'info',
                    'A renewal is already in progress for this subscription. Complete it in the order wizard.'
                );
        }

        if ($this->renewalService->hasActiveRenewalForCustomer((int) $baseOrder->user_id, (int) $baseOrder->id)) {
            return back()->with('error', 'An active renewal/upgrade already exists for this customer.');
        }

        $renewQty = max(1, (int) $request->input('quantity'));
        $plan = Plan::query()->find($baseOrder->plan_id);
        if ($plan && ($reject = $this->rejectIfQuantityExceedsPlanLimit($plan, $renewQty))) {
            return $reject;
        }
        $unitPrice = (float) ($baseOrder->price ?? 0);
        if ($unitPrice <= 0 && $plan) {
            $unitPrice = (float) ($plan->price ?? 0);
        }
        $subtotal = round($unitPrice * $renewQty, 2);
        $gstAmount = round($subtotal * 0.18, 2);
        $amount = round($subtotal + $gstAmount, 2);
        $creditCheck = PartnerCreditService::validateB2bPurchaseAmount($partnerId, $amount);
        if (empty($creditCheck['ok'])) {
            return back()->with('error', (string) ($creditCheck['message'] ?? PartnerCreditService::CREDIT_LIMIT_REACHED_MESSAGE));
        }

        DB::beginTransaction();
        try {
            $oldStart = !empty($baseOrder->subscription_start_date)
                ? Carbon::parse((string) $baseOrder->subscription_start_date)->startOfDay()
                : Carbon::parse((string) $baseOrder->created_at)->startOfDay();
            $oldEnd = !empty($baseOrder->subscription_end_date)
                ? Carbon::parse((string) $baseOrder->subscription_end_date)->startOfDay()
                : $this->renewalService->subscriptionEndDate($oldStart, $baseOrder->duration ?? '12 month')->startOfDay();
            $newStart = $oldEnd->copy()->addDay();
            $newEnd = $this->renewalService->subscriptionEndDate($newStart, $baseOrder->duration ?? '12 month')->startOfDay();

            $newOrder = $baseOrder->replicate();
            // replicate() copies order_no/sr_no; both are UNIQUE — allocate fresh values.
            $newOrder->order_no = null;
            if (Schema::hasColumn('orders', 'sr_no')) {
                $newOrder->sr_no = null;
            }
            $nextIds = $this->nextOrderIdentifiers();
            $renewPrefix = OrderPrefixResolver::resolve('renewal');
            $newOrder->order_no = $renewPrefix . str_pad((string) $nextIds['sr_no'], 5, '0', STR_PAD_LEFT);
            if (Schema::hasColumn('orders', 'sr_no')) {
                $newOrder->sr_no = $nextIds['sr_no'];
            }

            $newOrder->partner_id = $partnerId;
            $newOrder->price = $unitPrice;
            $newOrder->quantity = $renewQty;
            $newOrder->subtotal = $subtotal;
            $newOrder->tax_amount = $gstAmount;
            $newOrder->total_amount = $amount;
            $newOrder->credit_used = $amount;
            $newOrder->order_type = 'b2b';
            $newOrder->order_category = 'renewal';
            $newOrder->status = 'completed';
            if (Schema::hasColumn('orders', 'subscription_start_date')) {
                $newOrder->subscription_start_date = $newStart->toDateString();
            }
            if (Schema::hasColumn('orders', 'subscription_end_date')) {
                $newOrder->subscription_end_date = $newEnd->toDateString();
            }
            if (Schema::hasColumn('orders', 'parent_order_id')) {
                $newOrder->parent_order_id = (int) $baseOrder->id;
            }
            $newOrder->created_at = now();
            $newOrder->updated_at = now();
            $newOrder->save();

            app(B2bSubscriptionRenewalService::class)->copyOrderItemsForRenewal((int) $newOrder->id, $baseOrder, [
                'licenses' => $renewQty,
                'unit_price' => $unitPrice,
                'gst_percentage' => 18,
                'plan_name' => (string) (optional($plan)->name ?? $baseOrder->plan_name ?? ''),
                'subscription_start_date' => $newStart->toDateString(),
                'subscription_end_date' => $newEnd->toDateString(),
            ]);

            app(B2bSubscriptionRenewalService::class)->syncBaseOrderAfterRenewal($baseOrder);

            $this->upsertAutoPoForOrder((int) $newOrder->id, '', $amount);

            $cgst = round($gstAmount / 2, 2);
            $sgst = round($gstAmount / 2, 2);
            $this->invoiceService->createInvoice([
                'cust_id' => (int) $baseOrder->user_id,
                'status' => 'pending',
                'action' => 'renew',
                'net_amt' => $amount,
                'taxable_total' => $subtotal,
                'cgst_total' => $cgst,
                'sgst_total' => $sgst,
                'items' => [[
                    'description' => 'Plan Renewal',
                    'hsn_sac' => '',
                    'qty' => $renewQty,
                    'cnt_rate' => (float) $baseOrder->price,
                    'taxable_amt' => $subtotal,
                    'cgst' => $cgst,
                    'sgst' => $sgst,
                    'nettotal' => $amount,
                ]],
            ]);

            PartnerCreditService::syncB2bOrderWalletLedger((int) $newOrder->id, $newOrder->getAttributes());

            DB::commit();

            $this->finalizeB2bCreditOrderAfterSave((int) $newOrder->id, 'renewal');

            return redirect()
                ->route('superadmin.orders.details', ['id' => (int) $newOrder->id])
                ->with('success', 'Renewal completed successfully. Order is now active.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to process renewal: ' . $e->getMessage());
        }
    }
}
