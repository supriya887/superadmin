<?php

namespace App\Http\Controllers\SuperAdmin\Crm;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadService;
use App\Models\Plan;
use App\Models\Provider;
use App\Models\Quotation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QuotationController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'all');
        $status = $request->get('status');

        $query = Quotation::query()->latest();

        if ($request->filled('from') || $request->filled('to')) {
            $dateColumn = match ($request->get('date_type')) {
                'follow_up' => 'follow_up_date',
                'sent' => 'sent_at',
                default => 'created_at',
            };
            if ($request->filled('from')) {
                $query->whereDate($dateColumn, '>=', $request->from);
            }
            if ($request->filled('to')) {
                $query->whereDate($dateColumn, '<=', $request->to);
            }
        }

        if ($request->filled('search')) {

        $search = trim((string) $request->search);
    
        $query->where(function ($q) use ($search) {
    
            $q->where('company_name', 'ilike', "%{$search}%")
                ->orWhere('name', 'ilike', "%{$search}%")
                ->orWhere('quotation_no', 'ilike', "%{$search}%")
                ->orWhere('email', 'ilike', "%{$search}%")
    
                ->orWhereHas('lead', function ($lead) use ($search) {
    
                    $lead->where('name', 'ilike', "%{$search}%")
                        ->orWhere('company_name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%")
                        ->orWhere('mobile', 'ilike', "%{$search}%")
                        ->orWhere('gstin', 'ilike', "%{$search}%");
    
                    if (ctype_digit($search)) {
                        $lead->orWhere('id', (int) $search);
                    }
                });
    
            if (ctype_digit($search)) {
                $q->orWhere('lead_id', (int) $search);
            }
        });
    }

        if ($tab === 'email') {
            $query->whereNotNull('sent_at');
        }

        if ($tab === 'discount' && !$status) {
            $status = 'pending';
        }

        if ($status && $status !== 'all') {
            if ($tab === 'discount') {
                $query->where('discount_status', $status);
            } else {
                $query->where('invoice_status', $status);
            }
        }

        $quotations = $query->paginate(10)->withQueryString();
        Quotation::attachVersions($quotations->getCollection());

        $statusCounts = [
            'pending' => Quotation::where('discount_status', 'pending')->count(),
            'approved' => Quotation::where('discount_status', 'approved')->count(),
            'rejected' => Quotation::where('discount_status', 'rejected')->count(),
            'all' => Quotation::count(),
        ];

        if ($tab === 'email') {
            $statusCounts = [
                'pending' => Quotation::where('invoice_status', 'pending')->count(),
                'approved' => Quotation::where('invoice_status', 'approved')->count(),
                'rejected' => Quotation::where('invoice_status', 'rejected')->count(),
                'all' => Quotation::count(),
            ];
        }

        return view('superadmin.crm.quotations.index', compact('quotations', 'tab', 'status', 'statusCounts'));
    }

    public function deals()
    {
        $this->ensureQuotationColumn('order_id', 'bigint NULL');
        $this->ensureQuotationColumn('crm_order_no', 'varchar(50) NULL');
        $this->ensureCrmOrdersTable();

        $query = Quotation::query()
            ->with('lead')
            ->crmDeals()
            ->latest();

        if (request()->filled('search')) {
            $search = trim((string) request('search'));
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'ilike', "%{$search}%")
                    ->orWhere('name', 'ilike', "%{$search}%")
                    ->orWhere('quotation_no', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('crm_order_no', 'ilike', "%{$search}%")
                    ->orWhere('assigned_to', 'ilike', "%{$search}%");
            });
        }

        $deals = $query->paginate(10)->withQueryString();

        return view('superadmin.crm.quotations.deals', compact('deals'));
    }

    public function showDeal(Quotation $quotation)
    {
        abort_unless($quotation->isCrmDeal(), 404);
        $quotation->loadMissing('lead');
        $this->ensureDealPoColumns();

        $converted = (int) $quotation->order_id > 0;
        $order = null;
        if ($converted) {
            $order = DB::connection('pgsql_crm')->table('crm_orders')->where('id', $quotation->order_id)->first();
        }
        if (!$order) {
            $order = (object) [
                'id' => 0,
                'order_no' => $quotation->crm_order_no ?: 'Preview',
                'status' => 'pending',
                'plan_name' => $quotation->plan_name,
                'domain_name' => $quotation->domain_name ?: optional($quotation->lead)->domain,
                'total_amount' => $quotation->total_amount,
                'employee' => $quotation->employeeName(),
                'customer_name' => $quotation->name,
                'company_name' => $quotation->company_name,
                'email' => $quotation->email,
                'quantity' => $quotation->quantity,
                'period' => $quotation->quotation_period,
                'plans' => $quotation->plans,
                'created_at' => $quotation->created_at,
                'updated_at' => $quotation->updated_at,
            ];
            $converted = false;
        }

        $po = $this->resolveDealPo($quotation, $converted ? $order : null);
        $order->po_mode = $po['po_mode'];
        $order->po_number = $po['po_number'];
        $order->po_date = $po['po_date'];
        $order->po_amount = $po['po_amount'];
        $order->po_document_url = $po['po_document_url'];
        $order->po_journey_status = $po['po_journey_status'];

        return view('superadmin.crm.quotations.deal_order', [
            'title' => $converted ? 'Order Details' : 'Order preview',
            'order' => $order,
            'quotation' => $quotation,
            'items' => $this->dealOrderItems($order),
            'converted' => $converted,
        ]);
    }

    public function showDealOrder(int $id)
    {
        $this->ensureCrmOrdersTable();
        $row = DB::connection('pgsql_crm')->table('crm_orders')->where('id', $id)->first();
        abort_if(!$row, 404);
        $quotation = Quotation::query()->findOrFail($row->quotation_id);

        return redirect()->route('crm.deals.view', $quotation);
    }

    public function convertToOrder(Request $request, Quotation $quotation)
    {
        if (!$quotation->isCrmDeal()) {
            return back()->with('error', 'Quotation and invoice must both be approved.');
        }

        $request->validate([
            'start_date' => 'nullable|date',
            'billing_cycle' => 'nullable|in:Monthly,Quarterly,Half Yearly,Yearly,3 Yearly',
        ]);

        try {
            $order = $this->convertDealIfNeeded($quotation, $request->input('start_date'), $request->input('billing_cycle'));
        } catch (\Throwable $e) {
            Log::error('CRM deal convert failed', [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Could not create CRM order: ' . $e->getMessage());
        }

        return redirect()
            ->route('crm.deals.view', $quotation)
            ->with('success', 'CRM order ' . $order['order_no'] . ' created.');
    }

    public function saveDealPo(Request $request, Quotation $quotation)
    {
        if (!$quotation->isCrmDeal()) {
            return back()->with('error', 'Quotation and invoice must both be approved.');
        }

        $this->ensureDealPoColumns();
        $mode = $request->input('po_mode') === 'uploaded' ? 'uploaded' : 'generated';

        $request->validate([
            'po_number' => 'nullable|string|max:80',
            'po_date' => 'nullable|date',
            'po_document' => $mode === 'uploaded' ? 'required|file|mimes:pdf|max:5120' : 'nullable|file|mimes:pdf|max:5120',
        ]);

        $row = DB::connection('pgsql_crm')->table('crm_orders')->where('quotation_id', $quotation->id)->first();
        if (!$row && (int) $quotation->order_id > 0) {
            $row = DB::connection('pgsql_crm')->table('crm_orders')->where('id', (int) $quotation->order_id)->first();
        }

        $poNumber = trim((string) $request->input('po_number'));
        $poDate = $request->filled('po_date') ? $request->input('po_date') : now()->toDateString();
        $poAmount = round((float) ($row->total_amount ?? $quotation->total_amount ?? 0), 2);
        $relativePath = null;

        if ($mode === 'uploaded') {
            $file = $request->file('po_document');
            $relativePath = 'crm_pos/q' . $quotation->id . '_upload.pdf';
            Storage::disk('public')->put($relativePath, (string) file_get_contents($file->getRealPath()));
            if ($poNumber === '') {
                $poNumber = 'EXT-' . ($row->order_no ?? $quotation->quotation_no ?: $quotation->id);
            }
        } else {
            if ($poNumber === '') {
                $poNumber = 'PO-' . ($row->order_no ?? $quotation->quotation_no ?: $quotation->id);
            }
            $relativePath = $this->storeGeneratedDealPoPdf($quotation, $poNumber);
        }

        $poData = [
            'po_mode' => $mode,
            'po_number' => $poNumber,
            'po_date' => $poDate,
            'po_amount' => $poAmount,
            'po_document_url' => $relativePath,
            'po_journey_status' => 'po_done',
        ];

        $quotation->update($poData);

        if ($row) {
            DB::connection('pgsql_crm')->table('crm_orders')->where('id', $row->id)->update($poData + [
                'updated_at' => now(),
            ]);
        }

        return redirect()
            ->route('crm.deals.view', $quotation)
            ->with('success', $mode === 'uploaded' ? 'PO uploaded.' : 'PO generated.');
    }

    public function dealPoPdf(Quotation $quotation)
    {
        $this->ensureDealPoColumns();
        $row = DB::connection('pgsql_crm')->table('crm_orders')->where('quotation_id', $quotation->id)->first();
        $po = $this->resolveDealPo($quotation, $row);

        abort_if(empty($po['po_mode']) && empty($po['po_document_url']) && empty($po['po_number']), 404, 'PO document not found.');

        $filename = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($po['po_number'] ?: ('PO-' . ($row->order_no ?? $quotation->quotation_no ?: $quotation->id))));
        $storedPath = (string) ($po['po_document_url'] ?? '');
        if ($storedPath !== '' && Storage::disk('public')->exists($storedPath)) {
            $binary = Storage::disk('public')->get($storedPath);
            if ($binary !== '') {
                return response($binary, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . $filename . '.pdf"',
                ]);
            }
        }

        if (($po['po_mode'] ?? '') === 'generated') {
            $poNumber = (string) ($po['po_number'] ?: ('PO-' . ($row->order_no ?? $quotation->id)));
            $binary = $this->renderDealPoPdf($quotation, $poNumber);
            if ($binary) {
                return response($binary, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . $filename . '.pdf"',
                ]);
            }
        }

        abort(404, 'PO document not found.');
    }

    protected function dealOrderItems(object $order): array
    {
        $rawPlans = is_string($order->plans ?? null) ? json_decode($order->plans, true) : ($order->plans ?? []);
        $rawPlans = is_array($rawPlans) ? $rawPlans : [];
        $items = [];
        foreach ($rawPlans as $plan) {
            $qty = max(1, (int) ($plan['quantity'] ?? 1));
            $unit = (float) ($plan['rate'] ?? $plan['unit_price'] ?? 0);
            $items[] = [
                'name' => $plan['plan_name'] ?? $plan['name'] ?? ($order->plan_name ?: 'Plan'),
                'description' => ($plan['quote_period'] ?? $order->period ?? '')
                    . (!empty($plan['next_renewal']) ? ' · Renews ' . Carbon::parse($plan['next_renewal'])->format('d-m-Y') : ''),
                'quantity' => $qty,
                'unit_price' => $unit,
                'total' => round($unit * $qty, 2),
            ];
        }
        if ($items === []) {
            $items[] = [
                'name' => $order->plan_name ?: 'CRM plan',
                'description' => $order->period ?: '',
                'quantity' => max(1, (int) ($order->quantity ?: 1)),
                'unit_price' => (float) $order->total_amount,
                'total' => (float) $order->total_amount,
            ];
        }

        return $items;
    }

    protected function createCrmOrderFromDeal(Quotation $quotation, Carbon $start, ?string $billingCycle = null): array
    {
        $plans = is_array($quotation->plans) ? array_values($quotation->plans) : [];
        if ($billingCycle) {
            $plans = array_map(fn ($plan) => array_merge($plan, ['quote_period' => $billingCycle]), $plans);
        }
        $plans = $this->orderRenewalDates($quotation, $plans, $start);
        $renewals = array_filter(array_column($plans, 'next_renewal'));
        $this->ensureCrmOrderColumn('start_date', 'date NULL');
        $this->ensureCrmOrderColumn('renewal_date', 'date NULL');
        $orderNo = $this->nextCrmOrderNo();

        $id = (int) DB::connection('pgsql_crm')->table('crm_orders')->insertGetId([
            'quotation_id' => $quotation->id,
            'lead_id' => $quotation->lead_id,
            'order_no' => $orderNo,
            'company_name' => $quotation->company_name,
            'customer_name' => $quotation->name,
            'email' => $quotation->email,
            'employee' => $quotation->employeeName(),
            'domain_name' => $quotation->domain_name ?: optional($quotation->lead)->domain,
            'plan_name' => $quotation->plan_name,
            'quantity' => max(1, (int) ($quotation->quantity ?: 1)),
            'total_amount' => round((float) $quotation->total_amount, 2),
            'period' => $billingCycle ?: $quotation->quotation_period,
            'start_date' => $start->toDateString(),
            'renewal_date' => $renewals ? min($renewals) : null,
            'status' => 'pending',
            'plans' => json_encode($plans),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['id' => $id, 'order_no' => $orderNo];
    }

    protected function orderRenewalDates(Quotation $quotation, array $plans, Carbon $start): array
    {
        $type = $quotation->deal_type ?: 'new';

        foreach ($plans as $i => $plan) {
            $period = $plan['quote_period'] ?? null;
            $current = $type !== 'new' && !empty($plan['renewal_date']) ? Carbon::parse($plan['renewal_date']) : null;

            if (!$current) {
                $renewal = $this->nextRenewal($start, $period, $plan['provider_id'] ?? null);
            } elseif (in_array($type, ['renewal', 'downgrade'], true)) {
                $renewal = $current->addMonthsNoOverflow($this->periodMonths($period));
            } else {
                $renewal = $current;
            }

            $plans[$i]['start_date'] = $start->toDateString();
            $plans[$i]['next_renewal'] = $renewal->toDateString();
        }

        return $plans;
    }

    protected function periodMonths(?string $period): int
    {
        return ['Monthly' => 1, 'Quarterly' => 3, 'Half Yearly' => 6, 'Yearly' => 12, '3 Yearly' => 36][$period ?: 'Yearly'] ?? 12;
    }

    protected function nextRenewal(Carbon $start, ?string $period, $providerId): Carbon
    {
        $renewal = $start->copy()->addMonthsNoOverflow($this->periodMonths($period));
        $isGoogle = stripos((string) optional(Provider::find($providerId))->name, 'google') !== false;

        return $isGoogle ? $renewal : $renewal->subDay();
    }

    protected function convertDealIfNeeded(Quotation $quotation, ?string $startDate = null, ?string $billingCycle = null): array
    {
        $this->ensureQuotationColumn('order_id', 'bigint NULL');
        $this->ensureQuotationColumn('crm_order_no', 'varchar(50) NULL');
        $this->ensureCrmOrdersTable();
        $this->ensureCrmOrderPoColumns();
        $quotation->refresh();

        if ((int) $quotation->order_id > 0) {
            $existing = DB::connection('pgsql_crm')->table('crm_orders')->where('id', (int) $quotation->order_id)->first();
            if ($existing) {
                $this->copyDealPoToOrder($quotation, (int) $existing->id);

                return ['id' => (int) $existing->id, 'order_no' => (string) $existing->order_no];
            }
        }

        $byQuote = DB::connection('pgsql_crm')->table('crm_orders')->where('quotation_id', $quotation->id)->first();
        if ($byQuote) {
            $this->copyDealPoToOrder($quotation, (int) $byQuote->id);
            $quotation->update([
                'order_id' => $byQuote->id,
                'crm_order_no' => $byQuote->order_no,
            ]);

            return ['id' => (int) $byQuote->id, 'order_no' => (string) $byQuote->order_no];
        }

        $order = $this->createCrmOrderFromDeal($quotation, Carbon::parse($startDate ?: now())->startOfDay(), $billingCycle);
        $this->copyDealPoToOrder($quotation, (int) $order['id']);
        $quotation->update([
            'order_id' => $order['id'],
            'crm_order_no' => $order['order_no'],
        ]);

        return $order;
    }

    protected function renderDealPoPdf(Quotation $quotation, string $poNumber): ?string
    {
        $data = $this->invoiceViewData($quotation, true);
        $data['invoiceNo'] = $poNumber;
        $data['docTitle'] = 'Purchase Order';
        $html = view('superadmin.crm.quotations.invoice', $data)->render();

        return $this->htmlToPdf($html);
    }

    protected function storeGeneratedDealPoPdf(Quotation $quotation, string $poNumber): ?string
    {
        $binary = $this->renderDealPoPdf($quotation, $poNumber);
        if (!$binary) {
            return null;
        }
        $relativePath = 'crm_pos/q' . $quotation->id . '.pdf';
        Storage::disk('public')->put($relativePath, $binary);

        return $relativePath;
    }

    protected function resolveDealPo(Quotation $quotation, ?object $order = null): array
    {
        $fromOrder = [
            'po_mode' => $order->po_mode ?? null,
            'po_number' => $order->po_number ?? null,
            'po_date' => $order->po_date ?? null,
            'po_amount' => $order->po_amount ?? null,
            'po_document_url' => $order->po_document_url ?? null,
            'po_journey_status' => $order->po_journey_status ?? null,
        ];
        $fromQuote = [
            'po_mode' => $quotation->po_mode ?? null,
            'po_number' => $quotation->po_number ?? null,
            'po_date' => $quotation->po_date ?? null,
            'po_amount' => $quotation->po_amount ?? null,
            'po_document_url' => $quotation->po_document_url ?? null,
            'po_journey_status' => $quotation->po_journey_status ?? null,
        ];

        $hasOrderPo = !empty($fromOrder['po_mode']) || !empty($fromOrder['po_number']) || !empty($fromOrder['po_document_url']);

        return $hasOrderPo ? $fromOrder : $fromQuote;
    }

    protected function copyDealPoToOrder(Quotation $quotation, int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }
        $po = $this->resolveDealPo($quotation);
        if (empty($po['po_mode']) && empty($po['po_number']) && empty($po['po_document_url'])) {
            return;
        }
        $this->ensureCrmOrderPoColumns();
        DB::connection('pgsql_crm')->table('crm_orders')->where('id', $orderId)->update([
            'po_mode' => $po['po_mode'],
            'po_number' => $po['po_number'],
            'po_date' => $po['po_date'],
            'po_amount' => $po['po_amount'],
            'po_document_url' => $po['po_document_url'],
            'po_journey_status' => $po['po_journey_status'] ?: 'po_done',
            'updated_at' => now(),
        ]);
    }

    protected function ensureDealPoColumns(): void
    {
        $this->ensureQuotationColumn('po_mode', 'varchar(20) NULL');
        $this->ensureQuotationColumn('po_number', 'varchar(80) NULL');
        $this->ensureQuotationColumn('po_date', 'date NULL');
        $this->ensureQuotationColumn('po_amount', 'numeric(14,2) NULL');
        $this->ensureQuotationColumn('po_document_url', 'text NULL');
        $this->ensureQuotationColumn('po_journey_status', 'varchar(40) NULL');
        $this->ensureCrmOrderPoColumns();
    }

    protected function nextCrmOrderNo(): string
    {
        $next = ((int) DB::connection('pgsql_crm')->table('crm_orders')->count()) + 1;
        $orderNo = 'CRM' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
        while (DB::connection('pgsql_crm')->table('crm_orders')->where('order_no', $orderNo)->exists()) {
            $next++;
            $orderNo = 'CRM' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
        }

        return $orderNo;
    }

    protected function ensureCrmOrdersTable(): void
    {
        try {
            if (Schema::connection('pgsql_crm')->hasTable('crm_orders')) {
                return;
            }
            Schema::connection('pgsql_crm')->create('crm_orders', function ($table) {
                $table->id();
                $table->unsignedBigInteger('quotation_id')->unique();
                $table->unsignedBigInteger('lead_id')->nullable();
                $table->string('order_no')->unique();
                $table->string('company_name')->nullable();
                $table->string('customer_name')->nullable();
                $table->string('email')->nullable();
                $table->string('employee')->nullable();
                $table->string('domain_name')->nullable();
                $table->string('plan_name')->nullable();
                $table->unsignedInteger('quantity')->nullable();
                $table->decimal('total_amount', 14, 2)->default(0);
                $table->string('period')->nullable();
                $table->string('status')->default('pending');
                $table->json('plans')->nullable();
                $table->string('po_mode')->nullable();
                $table->string('po_number')->nullable();
                $table->date('po_date')->nullable();
                $table->decimal('po_amount', 14, 2)->nullable();
                $table->text('po_document_url')->nullable();
                $table->string('po_journey_status')->nullable();
                $table->timestamps();
            });
        } catch (\Throwable $e) {
            // table may already exist
        }
    }

    protected function ensureCrmOrderPoColumns(): void
    {
        $this->ensureCrmOrdersTable();
        $this->ensureCrmOrderColumn('po_mode', 'varchar(20) NULL');
        $this->ensureCrmOrderColumn('po_number', 'varchar(80) NULL');
        $this->ensureCrmOrderColumn('po_date', 'date NULL');
        $this->ensureCrmOrderColumn('po_amount', 'numeric(14,2) NULL');
        $this->ensureCrmOrderColumn('po_document_url', 'text NULL');
        $this->ensureCrmOrderColumn('po_journey_status', 'varchar(40) NULL');
    }

    protected function ensureCrmOrderColumn(string $column, string $type): void
    {
        try {
            if (!Schema::connection('pgsql_crm')->hasColumn('crm_orders', $column)) {
                DB::connection('pgsql_crm')->statement(
                    'ALTER TABLE crm_orders ADD COLUMN IF NOT EXISTS ' . $column . ' ' . $type
                );
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function create(Request $request)
    {
        $leads = Lead::with(['contacts:id,lead_id,name,email,mobile'])
            ->orderBy('company_name')
            ->get([
            'id', 'company_name', 'name', 'salutation', 'email', 'gstin', 'designation', 'mobile', 'mobile_code',
            'domain', 'domain_type', 'domain_rate', 'sac', 'domain_start', 'domain_end',
        ]);

        $lead = null;
        $services = collect();

        if ($request->filled('lead_id') || $request->filled('email') || $request->filled('mobile')) {
            $lead = $this->resolveQuotationLead($request);
            $services = $lead
                ? $lead->services->reject(fn ($s) => $s->isCompetitorInfo())->values()
                : collect();
        }

        $providers = Provider::orderBy('name')->get(['id', 'name']);

        $plans = Plan::query()
            ->whereIn('provider_id', $providers->pluck('id'))
            ->orderBy('name')
            ->get(['id', 'provider_id', 'name', 'title', 'slug', 'actual_price', 'price']);

        $providerPlans = $plans
            ->groupBy('provider_id')
            ->map(function ($group) {
                return $group->map(function ($plan) {
                    $rate = $plan->actual_price > 0 ? $plan->actual_price : ($plan->price > 0 ? $plan->price : 0);
                    return [
                        'id' => $plan->id,
                        'name' => $plan->name ?: $plan->title,
                        'sku' => $plan->slug,
                        'slug' => $plan->slug,
                        'actual_price' => (float) $plan->actual_price,
                        'rate' => (float) $rate,
                    ];
                })->values();
            })
            ->toArray();

        $quotation = null;
        $currentServices = $this->currentServicesFor($lead);

        return view('superadmin.crm.quotations.create', compact('leads', 'lead', 'services', 'providers', 'providerPlans', 'quotation', 'currentServices'));
    }

    protected function currentServicesFor(?Lead $lead)
    {
        if (!$lead) {
            return collect();
        }

        return $lead->services()->with(['provider', 'plan'])->get()
            ->filter(fn ($s) => $s->plan_id)
            ->map(function ($s) {
                $provider = $s->getRelation('provider');
                $plan = $s->getRelation('plan');

                return [
                    'id' => $s->id,
                    'provider_id' => $s->provider_id,
                    'plan_id' => $s->plan_id,
                    'provider_name' => optional($provider)->name ?: ($s->getAttributes()['provider'] ?? ''),
                    'plan_name' => optional($plan)->name ?: (optional($plan)->title ?: ($s->getAttributes()['plan'] ?? '')),
                    'quantity' => (int) $s->quantity,
                    'renewal_date' => optional($s->renewal_date)->toDateString(),
                ];
            })
            ->values();
    }

    protected function resolveQuotationLead(Request $request): ?Lead
    {
        $with = ['services.plan', 'contacts'];

        if ($request->filled('lead_id')) {
            return Lead::with($with)->find($request->lead_id);
        }

        $email = strtolower(trim((string) $request->input('email', '')));
        if ($email !== '') {
            $lead = Lead::with($with)->whereRaw('LOWER(email) = ?', [$email])->first();
            if ($lead) {
                return $lead;
            }

            return Lead::with($with)
                ->whereHas('contacts', function ($q) use ($email) {
                    $q->whereRaw('LOWER(email) = ?', [$email]);
                })
                ->first();
        }

        $mobile = preg_replace('/\D+/', '', (string) $request->input('mobile', ''));
        if ($mobile !== '') {
            $lead = Lead::with($with)
                ->whereRaw("regexp_replace(COALESCE(mobile, ''), '[^0-9]', '', 'g') = ?", [$mobile])
                ->first();
            if ($lead) {
                return $lead;
            }

            return Lead::with($with)
                ->whereHas('contacts', function ($q) use ($mobile) {
                    $q->whereRaw("regexp_replace(COALESCE(mobile, ''), '[^0-9]', '', 'g') = ?", [$mobile]);
                })
                ->first();
        }

        return null;
    }

    public function edit(Quotation $quotation)
    {
        if ($quotation->isLocked()) {
            return redirect()
                ->route('crm.quotations.show', $quotation)
                ->with('error', 'This quotation cannot be edited because it is already approved.');
        }
        $leads = Lead::with(['contacts:id,lead_id,name,email,mobile'])
            ->orderBy('company_name')
            ->get([
            'id', 'company_name', 'name', 'salutation', 'email', 'gstin', 'designation', 'mobile', 'mobile_code',
            'domain', 'domain_type', 'domain_rate', 'sac', 'domain_start', 'domain_end',
        ]);
        $lead = $quotation->lead_id ? Lead::with('services.plan')->find($quotation->lead_id) : null;
        $services = $lead
            ? $lead->services->reject(fn ($s) => $s->isCompetitorInfo())->values()
            : collect();
        $providers = Provider::orderBy('name')->get(['id', 'name']);
        $plans = Plan::query()
            ->whereIn('provider_id', $providers->pluck('id'))
            ->orderBy('name')
            ->get(['id', 'provider_id', 'name', 'title', 'slug', 'actual_price', 'price']);
        $providerPlans = $plans
            ->groupBy('provider_id')
            ->map(function ($group) {
                return $group->map(function ($plan) {
                    $rate = $plan->actual_price > 0 ? $plan->actual_price : ($plan->price > 0 ? $plan->price : 0);
                    return [
                        'id' => $plan->id,
                        'name' => $plan->name ?: $plan->title,
                        'sku' => $plan->slug,
                        'slug' => $plan->slug,
                        'actual_price' => (float) $plan->actual_price,
                        'rate' => (float) $rate,
                    ];
                })->values();
            })
            ->toArray();
        $currentServices = $this->currentServicesFor($lead);

        return view('superadmin.crm.quotations.create', compact('leads', 'lead', 'services', 'providers', 'providerPlans', 'quotation', 'currentServices'));
    }

    public function store(Request $request)
    {
        $request->validate($this->quotationRules());

        $this->checkPlans($request->input('plans', []));

        $lead = $request->filled('lead_id') ? Lead::find($request->lead_id) : null;
        $payload = $this->quotationPayload($request, $lead);
        $plans = $payload['plans'];

        $quotation = Quotation::create($payload + [
            'quotation_no' => 'TMP',
            'valid_date' => Carbon::now()->addDays(15)->toDateString(),
            'discount_status' => 'pending',
            'invoice_status' => 'pending',
        ]);

        $this->applyQuotationNumber($quotation, $plans);

        if ($lead) {
            $lead->update(['lead_status' => 'Quote Send']);
            $this->syncLeadPlansFromQuotation($lead, $plans, $payload['deal_type']);
        }

        return redirect()
            ->route('crm.quotations')
            ->with('success', 'Quotation saved.');
    }

    protected function quotationRules(): array
    {
        return [
            'deal_type' => 'nullable|in:' . implode(',', array_keys(Quotation::DEAL_TYPES)),
            'company_name' => 'required|string|max:255',
            'gstin' => 'required|string|max:15',
            'name' => 'required|string|max:255',
            'salutation' => 'required|in:Mr,Mrs,Ms',
            'mobile' => 'nullable|regex:/^[0-9]{6,15}$/',
            'mobile_code' => 'nullable|string|max:8',
            'email' => 'required|email',
            'discount' => 'required|numeric|min:0|max:100',
            'domain_type' => 'nullable|in:existing,new',
            'domain_name' => 'nullable|string|max:255',
            'sac' => 'nullable|string|max:50',
            'domain_start' => 'nullable|date',
            'domain_end' => 'nullable|date',
            'domain_rate' => 'nullable|numeric|min:0',
            'plans' => 'required|array|min:1',
            'plans.*.provider_id' => 'required',
            'plans.*.plan_id' => 'required',
            'plans.*.quote_period' => 'required|string',
            'plans.*.sku' => 'required|string',
            'plans.*.quantity' => 'required|integer|min:1',
            'plans.*.rate' => 'required|numeric|min:0',
            'plans.*.from_service_id' => 'nullable|integer',
            'plans.*.billing_start_date' => 'nullable|date',
            'lead_id' => 'nullable|integer',
        ];
    }

    public function update(Request $request, Quotation $quotation)
    {
        $request->validate($this->quotationRules());

        $this->checkPlans($request->input('plans', []));

        $lead = $request->filled('lead_id') ? Lead::find($request->lead_id) : $quotation->lead;
        $payload = $this->quotationPayload($request, $lead);
        $plans = $payload['plans'];

        if ($quotation->isLocked()) {
            return redirect()
                ->route('crm.quotations.show', $quotation)
                ->with('error', 'This quotation cannot be edited because it is already approved.');
        }

        if ($quotation->sent_at) {
            $sourceNo = $quotation->quotation_no;
            $payload['quotation_no'] = 'TMP-' . uniqid();
            $payload['valid_date'] = Carbon::now()->addDays(15)->toDateString();
            $payload['discount_status'] = 'pending';
            $payload['invoice_status'] = 'pending';
            $payload['sent_at'] = null;
            $quotation = Quotation::create($payload);
            $this->applyQuotationNumber($quotation, $plans, $sourceNo);
            $this->syncLeadPlansFromQuotation($lead, $plans, $payload['deal_type']);

            return redirect()
                ->route('crm.quotations.show', $quotation)
                ->with('success', 'New quotation saved. Previous shared quotation is kept.');
        }

        $quotation->update($payload);
        $this->applyQuotationNumber($quotation, $plans);
        $this->syncLeadPlansFromQuotation($lead, $plans, $payload['deal_type']);

        return redirect()
            ->route('crm.quotations.show', $quotation)
            ->with('success', 'Quotation updated.');
    }

    public function show(Quotation $quotation)
    {
        $quotation->loadMissing('lead.services');
        $sheets = $this->buildSheets($quotation);
        $domainType = optional($quotation->lead)->domain_type ?? '';
        $siblingQuotes = $quotation->siblingQuery()->orderBy('id')->get();
        Quotation::attachVersions($siblingQuotes);
        $sentSiblings = $siblingQuotes->filter(fn ($row) => $row->sent_at)->values();
        $isLatest = (int) $siblingQuotes->max('id') === (int) $quotation->id;
        $isLocked = $quotation->isLocked();

        $displayQuotes = $sentSiblings->isNotEmpty()
            ? $sentSiblings->sortByDesc('id')->values()
            : collect([$quotation]);

        if (!$displayQuotes->contains(fn ($row) => (int) $row->id === (int) $quotation->id)) {
            $displayQuotes->push($quotation);
            $displayQuotes = $displayQuotes->sortByDesc('id')->values();
        }

        $quotePages = $displayQuotes->map(function ($row) {
            $row->loadMissing('lead.services');
            return [
                'quotation' => $row,
                'sheets' => $this->buildSheets($row),
            ];
        })->values();

        return view('superadmin.crm.quotations.show', compact(
            'quotation',
            'sheets',
            'domainType',
            'isLatest',
            'isLocked',
            'siblingQuotes',
            'sentSiblings',
            'quotePages'
        ));
    }

    public function activity(Quotation $quotation)
    {
        $quotation->loadMissing('lead');
        $quoteEmails = collect($quotation->sent_emails ?? [])
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values();
        $quoteCc = collect($quotation->sent_cc_emails ?? [])
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values();
        $invoiceEmails = collect($quotation->invoice_sent_emails ?? [])
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values();
        $invoiceCc = collect($quotation->invoice_sent_cc_emails ?? [])
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values();

        $quoteStatus = strtolower((string) ($quotation->quotation_status ?? ''));
        $invoiceStatus = strtolower((string) ($quotation->invoice_status ?? ''));

        $events = [];
        $events[] = [
            'tone' => 'blue',
            'title' => 'Quotation created',
            'when' => optional($quotation->created_at)->format('d M Y, h:i A'),
            'text' => 'Created for ' . ($quotation->email ?: '—'),
        ];

        if ($quotation->sent_at || $quoteEmails->isNotEmpty()) {
            $list = $quoteEmails->isNotEmpty() ? $quoteEmails : collect([$quotation->email])->filter();
            $text = 'Sent to ' . $list->count() . ' address' . ($list->count() === 1 ? '' : 'es') . ': ' . $list->implode(', ');
            if ($quoteCc->isNotEmpty()) {
                $text .= '. CC (' . $quoteCc->count() . '): ' . $quoteCc->implode(', ');
            }
            $events[] = [
                'tone' => 'teal',
                'title' => 'Quotation sent',
                'when' => optional($quotation->sent_at)->format('d M Y, h:i A'),
                'text' => $text,
            ];
        }

        if (in_array($quoteStatus, ['approved', 'rejected'], true)) {
            $who = $quotation->responded_email ?: '—';
            $events[] = [
                'tone' => $quoteStatus === 'approved' ? 'green' : 'orange',
                'title' => 'Quotation ' . $quoteStatus . ' (first response)',
                'when' => optional($quotation->responded_at)->format('d M Y, h:i A'),
                'text' => ucfirst($quoteStatus) . ' by ' . $who,
            ];
        }

        if ($quotation->invoice_sent_at || $invoiceEmails->isNotEmpty() || $invoiceStatus === 'sent') {
            $list = $invoiceEmails->isNotEmpty()
                ? $invoiceEmails
                : collect([$quotation->responded_email ?: $quotation->email])->filter();
            $text = 'Sent to ' . $list->count() . ' address' . ($list->count() === 1 ? '' : 'es') . ': ' . $list->implode(', ');
            if ($invoiceCc->isNotEmpty()) {
                $text .= '. CC (' . $invoiceCc->count() . '): ' . $invoiceCc->implode(', ');
            }
            $events[] = [
                'tone' => 'purple',
                'title' => 'Invoice sent',
                'when' => optional($quotation->invoice_sent_at)->format('d M Y, h:i A'),
                'text' => $text,
            ];
        }

        if (in_array($invoiceStatus, ['approved', 'rejected'], true)) {
            $who = $quotation->invoice_responded_email ?: ($quotation->responded_email ?: '—');
            $events[] = [
                'tone' => $invoiceStatus === 'approved' ? 'green' : 'orange',
                'title' => 'Invoice ' . $invoiceStatus,
                'when' => optional($quotation->invoice_responded_at)->format('d M Y, h:i A'),
                'text' => ucfirst($invoiceStatus) . ' by ' . $who,
            ];
        }

        return view('superadmin.crm.quotations.activity', compact(
            'quotation',
            'quoteEmails',
            'quoteCc',
            'invoiceEmails',
            'invoiceCc',
            'quoteStatus',
            'invoiceStatus',
            'events'
        ));
    }

    public function updateStatuses(Request $request, Quotation $quotation)
    {
        $data = $request->validate([
            'quotation_status' => 'required|in:not_shared,shared,approved,rejected',
            'invoice_status' => 'nullable|in:not_sent,sent,approved,rejected',
        ]);

        $this->ensureQuotationColumn('quotation_status', "varchar(20) NULL DEFAULT 'pending'");
        $this->ensureQuotationColumn('invoice_responded_email', 'varchar(255) NULL');

        $quoteNow = strtolower((string) ($quotation->quotation_status ?? ''));
        $invoiceNow = strtolower((string) ($quotation->invoice_status ?? ''));
        $alreadyApproved = $quoteNow === 'approved'
            || (!in_array($quoteNow, ['approved', 'rejected', 'shared'], true) && $invoiceNow === 'approved');

        $invoiceStatus = $data['invoice_status'] ?? 'not_sent';
        if ($data['quotation_status'] !== 'approved') {
            $invoiceStatus = 'not_sent';
        } elseif (!$alreadyApproved) {
            $invoiceStatus = 'not_sent';
        }

        $payload = [
            'quotation_status' => $data['quotation_status'],
            'invoice_status' => $invoiceStatus,
        ];

        $quoteDecided = in_array($data['quotation_status'], ['approved', 'rejected'], true)
            && !in_array($quoteNow, ['approved', 'rejected'], true);
        if ($quoteDecided && trim((string) $quotation->responded_email) === '') {
            $this->ensureQuotationColumn('responded_email', 'varchar(255) NULL');
            $this->ensureQuotationColumn('responded_at', 'timestamp NULL');
            $payload['responded_email'] = $quotation->email;
            $payload['responded_at'] = now();
        }

        $invoiceDecided = in_array($invoiceStatus, ['approved', 'rejected'], true)
            && !in_array($invoiceNow, ['approved', 'rejected'], true);
        if ($invoiceDecided && trim((string) ($quotation->invoice_responded_email ?? '')) === '') {
            $this->ensureQuotationColumn('invoice_responded_at', 'timestamp NULL');
            $payload['invoice_responded_email'] = $quotation->email;
            $payload['invoice_responded_at'] = now();
        }

        $quotation->update($payload);

        if ($quotation->lead_id && in_array($data['quotation_status'], ['approved', 'rejected'], true)) {
            Lead::where('id', $quotation->lead_id)->update([
                'lead_status' => $data['quotation_status'] === 'approved' ? 'Quote Send' : 'Lost',
            ]);
        }

        return back()->with('success', 'Quotation status updated.');
    }

    public function download(Quotation $quotation)
    {
        $quotation->load('lead');
        $quotation->loadMissing('lead.services');
        $sheets = $this->buildSheets($quotation);
        $html = $this->quotationPdfHtml($quotation, $sheets);
        $binary = $this->htmlToPdf($html);
        $name = preg_replace('/[^A-Za-z0-9\-]+/', '-', $quotation->quotation_no ?: 'quotation-' . $quotation->id) . '.pdf';

        if ($binary) {
            return response($binary, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $name . '"',
            ]);
        }

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . str_replace('.pdf', '.html', $name) . '"',
        ]);
    }

    public function invoice(Quotation $quotation)
    {
        if (!$this->quotationIsApproved($quotation)) {
            return redirect()
                ->route('crm.quotations.show', $quotation)
                ->with('error', 'Invoice is available after the quotation is approved.');
        }

        return view('superadmin.crm.quotations.invoice', $this->invoiceViewData($quotation));
    }

    public function downloadInvoice(Quotation $quotation)
    {
        if (!$this->quotationIsApproved($quotation)) {
            return redirect()
                ->route('crm.quotations.show', $quotation)
                ->with('error', 'Invoice is available after the quotation is approved.');
        }

        $data = $this->invoiceViewData($quotation, true);
        $html = view('superadmin.crm.quotations.invoice', $data)->render();
        $binary = $this->htmlToPdf($html);
        $name = 'invoice-' . preg_replace('/[^A-Za-z0-9\-]+/', '-', $quotation->quotation_no ?: (string) $quotation->id) . '.pdf';

        if ($binary) {
            return response($binary, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $name . '"',
            ]);
        }

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . str_replace('.pdf', '.html', $name) . '"',
        ]);
    }

    public function sendInvoice(Request $request)
    {
        $request->validate([
            'quotation_id' => 'required|integer',
            'emails' => 'required|string',
            'cc' => 'nullable|string',
        ]);

        $quotation = Quotation::find($request->quotation_id);
        if (!$quotation) {
            return back()->with('error', 'Quotation not found.');
        }
        if (!$this->quotationIsApproved($quotation)) {
            return back()->with('error', 'Invoice can be sent after the quotation is approved.');
        }

        $emails = $this->parseMailAddresses($request->emails);
        $ccEmails = $this->parseMailAddresses($request->cc)
            ->reject(fn ($email) => $emails->contains($email))
            ->values();
        if ($emails->isEmpty()) {
            return back()->with('error', 'Enter at least one valid email address.');
        }

        $viewData = $this->invoiceViewData($quotation, true);
        $html = view('superadmin.crm.quotations.invoice', $viewData)->render();
        $pdfBinary = $this->htmlToPdf($html);
        $pdfName = 'invoice-' . preg_replace('/[^A-Za-z0-9\-]+/', '-', $quotation->quotation_no ?: (string) $quotation->id) . '.pdf';
        $invoiceNo = $viewData['invoiceNo'];
        $mailData = $viewData['data'];

        config([
            'mail.mailers.smtp.stream.ssl.verify_peer' => false,
            'mail.mailers.smtp.stream.ssl.verify_peer_name' => false,
        ]);

        $failed = [];
        $lastError = null;
        $sentOk = [];
        $ccAttached = false;
        foreach ($emails as $email) {
            try {
                $approveUrl = URL::signedRoute('crm.quotations.invoice.respond', [
                    'quotation' => $quotation->id,
                    'action' => 'approve',
                    'email' => $email,
                ]);
                $rejectUrl = URL::signedRoute('crm.quotations.invoice.respond', [
                    'quotation' => $quotation->id,
                    'action' => 'reject',
                    'email' => $email,
                ]);
                $attachCc = !$ccAttached && $ccEmails->isNotEmpty();
                Mail::send('superadmin.crm.quotations.invoice_mail', [
                    'quotation' => $quotation,
                    'invoiceNo' => $invoiceNo,
                    'data' => $mailData,
                    'approveUrl' => $approveUrl,
                    'rejectUrl' => $rejectUrl,
                ], function ($message) use ($email, $invoiceNo, $pdfBinary, $pdfName, $attachCc, $ccEmails) {
                    $message->to($email)->subject('Tax Invoice ' . $invoiceNo);
                    if ($attachCc) {
                        $message->cc($ccEmails->all());
                    }
                    if ($pdfBinary) {
                        $message->attachData($pdfBinary, $pdfName, ['mime' => 'application/pdf']);
                    }
                });
                $sentOk[] = $email;
                if ($attachCc) {
                    $ccAttached = true;
                }
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                $failed[] = $email;
                Log::error('Invoice email failed', [
                    'quotation_id' => $quotation->id,
                    'email' => $email,
                    'error' => $lastError,
                ]);
            }
        }

        if ($sentOk !== []) {
            $this->ensureQuotationColumn('invoice_sent_at', 'timestamp NULL');
            $this->ensureQuotationColumn('invoice_sent_emails', 'jsonb NULL');
            $this->ensureQuotationColumn('invoice_sent_cc_emails', 'jsonb NULL');
            $payload = [
                'invoice_status' => 'sent',
                'invoice_sent_at' => now(),
                'invoice_sent_emails' => $this->mergeAddressList($quotation->invoice_sent_emails, $sentOk),
            ];
            if ($ccAttached) {
                $payload['invoice_sent_cc_emails'] = $this->mergeAddressList($quotation->invoice_sent_cc_emails, $ccEmails->all());
            }
            $quotation->update($payload);
        }

        if (count($failed) === $emails->count()) {
            return back()->with('error', 'Could not send invoice email. ' . ($lastError ?: 'Check mail settings.'));
        }

        $sent = count($sentOk);

        $msg = 'Invoice emailed to ' . $sent . ' address' . ($sent === 1 ? '' : 'es');
        if ($ccAttached) {
            $msg .= ', CC ' . $ccEmails->count();
        }

        return back()->with('success', $msg . '.');
    }

    protected function quotationIsApproved(Quotation $quotation): bool
    {
        $quote = strtolower((string) ($quotation->quotation_status ?? ''));
        $invoice = strtolower((string) ($quotation->invoice_status ?? ''));

        return $quote === 'approved'
            || (!in_array($quote, ['rejected', 'shared'], true) && $invoice === 'approved');
    }

    protected function invoiceViewData(Quotation $quotation, bool $forPdf = false): array
    {
        $quotation->loadMissing('lead.services');
        $sheets = $this->buildSheets($quotation);
        $discountAmount = round((float) collect($sheets)->sum('discountAmt'), 2);
        $gstTotal = round((float) collect($sheets)->sum('gstAmt'), 2);
        $grandTotal = round((float) collect($sheets)->sum('grand'), 2);
        $state = strtolower(trim((string) $quotation->state));
        $taxType = ($state === '' || str_contains($state, 'maharashtra') || $state === 'mh') ? 'cgst' : 'igst';
        $logoPath = public_path('superadmin/crm/images/logo.png');
        $logoSrc = is_file($logoPath)
            ? 'data:image/png;base64,' . base64_encode((string) file_get_contents($logoPath))
            : '';
        $words = trim((string) numberTowords($grandTotal));
        $amtWords = 'INR ' . ucwords($words !== '' ? $words : 'Zero') . ' Only';

        return [
            'quotation' => $quotation,
            'sheets' => $sheets,
            'invoiceNo' => 'INV-' . ($quotation->quotation_no ?: $quotation->id),
            'invoiceDate' => now()->format('d-m-Y'),
            'forPdf' => $forPdf,
            'logoSrc' => $logoSrc,
            'discountAmount' => $discountAmount,
            'taxType' => $taxType,
            'igstTotal' => $taxType === 'igst' ? $gstTotal : 0,
            'cgstTotal' => $taxType === 'igst' ? 0 : round($gstTotal / 2, 2),
            'sgstTotal' => $taxType === 'igst' ? 0 : round($gstTotal / 2, 2),
            'grandTotal' => $grandTotal,
            'amtWords' => $amtWords,
            'data' => [
                'invoiceDate' => now()->format('d-m-Y'),
                'grandTotal' => $grandTotal,
            ],
        ];
    }

    public function send(Request $request)
    {
        Log::info('Quotation mail requested', [
            'quotation_id' => $request->quotation_id,
            'emails_raw' => $request->emails,
        ]);

        $request->validate([
            'quotation_id' => 'required|integer',
            'emails' => 'required|string',
            'cc' => 'nullable|string',
        ]);

        $quotation = Quotation::find($request->quotation_id);
        if (!$quotation) {
            Log::error('Quotation mail aborted: quotation not found', ['quotation_id' => $request->quotation_id]);
            return back()->with('error', 'Quotation not found.');
        }

        if ($quotation->isLocked()) {
            return back()->with('error', 'This quotation cannot be sent because a version is already approved.');
        }

        $latest = $quotation->siblingQuery()->orderByDesc('id')->first();
        if ($latest && (int) $latest->id !== (int) $quotation->id) {
            $quotation = $latest;
        }

        if ($quotation->isLocked()) {
            return back()->with('error', 'This quotation cannot be sent because a version is already approved.');
        }

        $quotation->refresh();
        $quotation->load('lead');
        $sheets = $this->buildSheets($quotation);
        $pdfHtml = $this->quotationPdfHtml($quotation, $sheets);
        $pdfBinary = $this->htmlToPdf($pdfHtml);
        $pdfName = preg_replace('/[^A-Za-z0-9\-]+/', '-', $quotation->quotation_no ?: 'quotation-' . $quotation->id) . '.pdf';

        $emails = $this->parseMailAddresses($request->emails);
        $ccEmails = $this->parseMailAddresses($request->cc)
            ->reject(fn ($email) => $emails->contains($email))
            ->values();

        if ($emails->isEmpty()) {
            Log::warning('Quotation mail aborted: no valid emails', [
                'quotation_id' => $quotation->id,
                'emails_raw' => $request->emails,
            ]);
            return back()->with('error', 'Enter at least one valid email address, one per line.');
        }

        config([
            'mail.mailers.smtp.stream.ssl.verify_peer' => false,
            'mail.mailers.smtp.stream.ssl.verify_peer_name' => false,
        ]);

        $failed = [];
        $lastError = null;
        $sentOk = [];
        $ccAttached = false;
        foreach ($emails as $email) {
            try {
                $approveUrl = URL::signedRoute('crm.quotations.respond', [
                    'quotation' => $quotation->id,
                    'action' => 'approve',
                    'email' => $email,
                ]);
                $rejectUrl = URL::signedRoute('crm.quotations.respond', [
                    'quotation' => $quotation->id,
                    'action' => 'reject',
                    'email' => $email,
                ]);
                $attachCc = !$ccAttached && $ccEmails->isNotEmpty();
                Mail::send('superadmin.crm.quotations.mail', [
                    'quotation' => $quotation,
                    'sheets' => $sheets,
                    'approveUrl' => $approveUrl,
                    'rejectUrl' => $rejectUrl,
                ], function ($message) use ($email, $quotation, $pdfBinary, $pdfName, $attachCc, $ccEmails) {
                    $message->to($email)->subject('Quotation ' . ($quotation->quotation_no ?: '#' . $quotation->id));
                    if ($attachCc) {
                        $message->cc($ccEmails->all());
                    }
                    if ($pdfBinary) {
                        $message->attachData($pdfBinary, $pdfName, ['mime' => 'application/pdf']);
                    }
                });
                $sentOk[] = $email;
                if ($attachCc) {
                    $ccAttached = true;
                }
                Log::info('Quotation mail sent', [
                    'quotation_id' => $quotation->id,
                    'email' => $email,
                ]);
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                Log::error('Quotation email failed', [
                    'quotation_id' => $quotation->id,
                    'email' => $email,
                    'error' => $lastError,
                ]);
                $failed[] = $email;
            }
        }

        $this->ensureQuotationColumn('quotation_status', "varchar(20) NULL DEFAULT 'pending'");
        $quotation->update(['quotation_status' => 'shared']);

        try {
            $this->ensureQuotationColumn('sent_at', 'timestamp NULL');
            $this->ensureQuotationColumn('sent_emails', 'jsonb NULL');
            $this->ensureQuotationColumn('sent_cc_emails', 'jsonb NULL');
            $this->ensureQuotationColumn('responded_email', 'varchar(255) NULL');
            $this->ensureQuotationColumn('responded_at', 'timestamp NULL');
            $payload = [
                'sent_at' => now(),
                'sent_emails' => $this->mergeAddressList($quotation->sent_emails, $sentOk),
            ];
            if ($ccAttached) {
                $payload['sent_cc_emails'] = $this->mergeAddressList($quotation->sent_cc_emails, $ccEmails->all());
            }
            $quotation->update($payload);
        } catch (\Throwable $e) {
            Log::warning('Could not save quotation sent_at', [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage(),
            ]);
        }

        if (count($failed) === $emails->count()) {
            return back()->with('error', 'Could not send quotation email. ' . ($lastError ?: 'Check mail settings and laravel.log.'));
        }

        $sent = $emails->count() - count($failed);
        $msg = 'Quotation emailed to ' . $sent . ' address' . ($sent === 1 ? '' : 'es');
        if ($ccAttached) {
            $msg .= ', CC ' . $ccEmails->count();
        }

        return back()->with('success', $msg . '.');
    }

    public function respond(Request $request, Quotation $quotation, string $action)
    {
        return $this->showEmailResponsePage($request, $quotation, $action, 'quotation', false);
    }

    public function respondApply(Request $request, Quotation $quotation, string $action)
    {
        return $this->showEmailResponsePage($request, $quotation, $action, 'quotation', true);
    }

    public function respondInvoice(Request $request, Quotation $quotation, string $action)
    {
        return $this->showEmailResponsePage($request, $quotation, $action, 'invoice', false);
    }

    public function respondInvoiceApply(Request $request, Quotation $quotation, string $action)
    {
        return $this->showEmailResponsePage($request, $quotation, $action, 'invoice', true);
    }

    protected function showEmailResponsePage(
        Request $request,
        Quotation $quotation,
        string $action,
        string $kind,
        bool $apply
    ) {
        $email = strtolower(trim((string) $request->query('email', '')));
        $current = $this->currentEmailDecision($quotation, $kind);
        $alreadyProcessed = in_array($current, ['approved', 'rejected'], true);
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $needsConfirm = false;
        $applyUrl = null;

        if ($alreadyProcessed) {
            $status = $current;
            $action = $current === 'approved' ? 'approve' : 'reject';
        } elseif ($apply) {
            $this->applyEmailDecision($quotation, $kind, $status, $action, $email);
            $quotation->refresh();
        } else {
            $needsConfirm = true;
            $route = $kind === 'invoice'
                ? 'crm.quotations.invoice.respond.apply'
                : 'crm.quotations.respond.apply';
            $applyUrl = URL::signedRoute($route, [
                'quotation' => $quotation->id,
                'action' => $action,
                'email' => $email,
            ]);
        }

        $view = $kind === 'invoice'
            ? 'superadmin.crm.quotations.respond_invoice'
            : 'superadmin.crm.quotations.respond';

        return view($view, compact(
            'quotation',
            'action',
            'status',
            'alreadyProcessed',
            'needsConfirm',
            'applyUrl',
            'kind'
        ));
    }

    protected function currentEmailDecision(Quotation $quotation, string $kind): string
    {
        if ($kind === 'invoice') {
            return strtolower((string) ($quotation->invoice_status ?? ''));
        }

        $current = strtolower((string) ($quotation->quotation_status ?? ''));
        if (!in_array($current, ['approved', 'rejected'], true)) {
            $legacy = strtolower((string) ($quotation->invoice_status ?? ''));
            if (in_array($legacy, ['approved', 'rejected'], true) && !in_array($current, ['shared'], true)) {
                return $legacy;
            }
        }

        return $current;
    }

    protected function applyEmailDecision(
        Quotation $quotation,
        string $kind,
        string $status,
        string $action,
        string $email
    ): void {
        if ($kind === 'invoice') {
            $this->ensureQuotationColumn('invoice_responded_email', 'varchar(255) NULL');
            $this->ensureQuotationColumn('invoice_responded_at', 'timestamp NULL');
            $quotation->update([
                'invoice_status' => $status,
                'invoice_responded_email' => $email !== '' ? $email : null,
                'invoice_responded_at' => now(),
            ]);

            return;
        }

        $this->ensureQuotationColumn('responded_email', 'varchar(255) NULL');
        $this->ensureQuotationColumn('responded_at', 'timestamp NULL');
        $this->ensureQuotationColumn('quotation_status', "varchar(20) NULL DEFAULT 'pending'");
        $payload = [
            'quotation_status' => $status,
        ];
        if ($status === 'approved') {
            $payload['invoice_status'] = 'not_sent';
        }
        if (Schema::connection('pgsql_crm')->hasColumn('quotations', 'responded_email')) {
            $payload['responded_email'] = $email !== '' ? $email : null;
        }
        if (Schema::connection('pgsql_crm')->hasColumn('quotations', 'responded_at')) {
            $payload['responded_at'] = now();
        }
        $quotation->update($payload);

        if ($quotation->lead_id) {
            Lead::where('id', $quotation->lead_id)->update([
                'lead_status' => $action === 'approve' ? 'Quote Send' : 'Lost',
            ]);
        }
    }

    protected function syncLeadPlansFromQuotation(?Lead $lead, array $plans, string $dealType = 'new'): void
    {
        if (!$lead || $dealType !== 'new') {
            return;
        }

        $quoteServices = LeadService::query()
            ->where('lead_id', $lead->id)
            ->get()
            ->reject(fn ($row) => $row->isCompetitorInfo())
            ->values();

        $keepIds = [];
        foreach (array_values($plans) as $plan) {
            $planId = (int) ($plan['plan_id'] ?? 0);
            $providerId = (int) ($plan['provider_id'] ?? 0);
            if (!$planId || !$providerId) {
                continue;
            }

            $existing = $quoteServices->first(function ($row) use ($planId, $keepIds) {
                return (int) $row->plan_id === $planId && !in_array((int) $row->id, $keepIds, true);
            });

            $payload = [
                'provider_id' => $providerId,
                'plan_id' => $planId,
                'quote_period' => $plan['quote_period'] ?? null,
                'sku' => $plan['sku'] ?? null,
                'quantity' => $plan['quantity'] ?? 1,
                'rate' => $plan['rate'] ?? 0,
                'is_info' => false,
            ];

            if ($existing) {
                $existing->update($payload);
                $keepIds[] = (int) $existing->id;
                continue;
            }

            $created = LeadService::create(array_merge($payload, [
                'lead_id' => $lead->id,
            ]));
            $keepIds[] = (int) $created->id;
        }

        foreach ($quoteServices as $row) {
            if (!in_array((int) $row->id, $keepIds, true)) {
                $row->delete();
            }
        }
    }

    protected function applyQuotationNumber(Quotation $quotation, array $plans, ?string $sourceNo = null): void
    {
        $siblings = $quotation->siblingQuery()->orderBy('id')->get();
        if ($siblings->isEmpty()) {
            $siblings = collect([$quotation]);
        }

        $first = $siblings->first();
        $name = $this->baseQuotationName($sourceNo ?: $first->quotation_no, $first);
        $index = $siblings->search(fn ($row) => (int) $row->id === (int) $quotation->id);
        if ($index === false) {
            $index = max($siblings->count() - 1, 0);
        }

        $number = $name;
        if ($index > 0) {
            $versionLetters = range('A', 'Z');
            $number .= '-' . ($versionLetters[$index - 1] ?? 'Z');
        }

        $quotation->update(['quotation_no' => $number]);
        $quotation->quotation_no = $number;
    }

    protected function baseQuotationName(?string $quotationNo, Quotation $fallback): string
    {
        $no = strtoupper((string) $quotationNo);
        if (preg_match('/^(TCCPLQ\d+A)/', $no, $match)) {
            return $match[1];
        }

        return 'TCCPLQ' . str_pad((string) $fallback->id, 5, '0', STR_PAD_LEFT) . 'A';
    }

    protected function quotationPayload(Request $request, ?Lead $lead): array
    {
        $dealType = $request->input('deal_type') ?: 'new';
        $isChange = $dealType !== 'new';
        $plans = array_values($request->plans);
        if ($isChange) {
            $plans = $this->changePlans($plans, $lead, $dealType);
        }

        $planTotal = 0;
        foreach ($plans as $i => $plan) {
            $planModel = Plan::find($plan['plan_id']);
            $provider = Provider::find($plan['provider_id']);
            $plans[$i]['name'] = optional($planModel)->name ?: (optional($planModel)->title ?? '');
            $plans[$i]['provider_name'] = optional($provider)->name ?? '';
            $plans[$i]['billing_end_date'] = in_array($dealType, ['license_addon', 'upgrade', 'partial_upgrade'], true) && !empty($plans[$i]['renewal_date'])
                ? $plans[$i]['renewal_date']
                : (!empty($plan['billing_start_date']) ? $this->nextRenewal(Carbon::parse($plan['billing_start_date']), $plan['quote_period'] ?? null, $plan['provider_id'] ?? null)->toDateString() : null);
            $planTotal += ((float) $plan['quantity']) * ((float) $plans[$i]['rate']);
        }

        $domainRate = !$isChange && $request->input('domain_type') === 'new' ? (float) $request->domain_rate : 0;
        $subTotal = $planTotal + $domainRate;
        $discount = (float) $request->discount;
        $total = $subTotal * (1 - ($discount / 100));
        $first = $plans[0];
        $firstProvider = Provider::find($first['provider_id']);

        $this->ensureQuotationColumn('salutation', 'varchar(10) NULL');
        $this->ensureQuotationColumn('mobile_code', 'varchar(8) NULL');
        $this->ensureQuotationColumn('deal_type', "varchar(30) NULL DEFAULT 'new'");

        return [
            'deal_type' => $dealType,
            'lead_id' => $lead?->id,
            'company_name' => $request->company_name,
            'gstin' => $request->gstin,
            'name' => $request->name,
            'salutation' => $request->salutation,
            'email' => $request->email,
            'mobile_code' => $request->mobile_code ?: ($lead?->mobile_code),
            'designation' => $lead?->designation,
            'mailing_address' => $lead?->mailing_address,
            'city' => $lead?->city,
            'state' => $lead?->state,
            'pincode' => $lead?->pincode,
            'country' => $lead?->country,
            'provider_id' => $firstProvider?->id,
            'service_name' => $firstProvider?->name,
            'discount' => $discount,
            'quotation_period' => $first['quote_period'] ?? 'Yearly',
            'domain_name' => $isChange ? null : $request->domain_name,
            'sac' => $isChange ? null : $request->sac,
            'domain_start' => $isChange ? null : $request->domain_start,
            'domain_end' => $isChange ? null : $request->domain_end,
            'domain_rate' => $domainRate ?: null,
            'plans' => $plans,
            'plan_name' => $first['name'],
            'quantity' => $first['quantity'],
            'total_amount' => round($total, 2),
            'assigned_to' => $lead?->assigned_to,
            'follow_up_date' => $lead?->follow_up_date,
        ];
    }

    protected function changePlans(array $plans, ?Lead $lead, string $dealType): array
    {
        if (!$lead) {
            throw ValidationException::withMessages(['lead_id' => 'Select a lead to create a ' . Quotation::DEAL_TYPES[$dealType] . ' quotation.']);
        }

        $current = $lead->services()->get()->keyBy('id');

        foreach ($plans as $i => $plan) {
            $fail = fn (string $field, string $message) => throw ValidationException::withMessages(["plans.$i.$field" => $message]);

            $service = $current->get((int) ($plan['from_service_id'] ?? 0));
            if (!$service) {
                $fail('from_service_id', 'Select the current plan.');
            }

            $fromPlan = Plan::find($service->plan_id);
            $fromName = optional($fromPlan)->name ?: (optional($fromPlan)->title ?: 'current plan');
            $fromRate = $this->planYearlyRate($fromPlan);
            $toRate = $this->planYearlyRate(Plan::find($plan['plan_id']));
            $fromQty = (int) $service->quantity;
            $qty = (int) $plan['quantity'];
            $samePlan = (int) $service->plan_id === (int) $plan['plan_id'];
            $sameProvider = (int) $service->provider_id === (int) $plan['provider_id'];

            if (in_array($dealType, ['license_addon', 'renewal'], true) && !$samePlan) {
                $fail('plan_id', 'Plan must be the same as the current plan.');
            }
            if (in_array($dealType, ['upgrade', 'partial_upgrade'], true) && !($sameProvider && $toRate > $fromRate)) {
                $fail('plan_id', 'Select a higher plan than ' . $fromName . '.');
            }
            if ($dealType === 'downgrade' && !($sameProvider && $toRate < $fromRate)) {
                $fail('plan_id', 'Select a lower plan than ' . $fromName . '.');
            }
            if (in_array($dealType, ['upgrade', 'downgrade'], true) && $qty !== $fromQty) {
                $fail('quantity', 'Licences must be the same as current (' . $fromQty . ').');
            }
            if ($dealType === 'partial_upgrade' && $qty >= $fromQty) {
                $fail('quantity', 'Partial upgrade must move fewer than the current ' . $fromQty . ' licences.');
            }

            $renewal = $service->renewal_date;
            $from = !empty($plan['billing_start_date']) ? Carbon::parse($plan['billing_start_date'])->startOfDay() : Carbon::today();
            $days = $renewal && $renewal->copy()->startOfDay()->gt($from)
                ? (int) $from->diffInDays($renewal->copy()->startOfDay())
                : 0;
            $till = $renewal ? $renewal->format('d-m-Y') : null;
            $proratedFor = $days > 0 ? ', prorated ' . $days . ' days till ' . $till : '';
            $enteredRate = round((float) $plan['rate'], 2);

            $row = [
                'from_plan_id' => $service->plan_id,
                'from_plan_name' => $fromName,
                'from_quantity' => $fromQty,
                'renewal_date' => optional($renewal)->toDateString(),
                'remaining_days' => $days,
            ];

            if ($dealType === 'license_addon') {
                $row += [
                    'quote_period' => 'Yearly',
                    'annual_rate' => $enteredRate,
                    'rate' => $this->prorate($enteredRate, $days),
                    'note' => 'Add-on ' . $qty . ' licences (current ' . $fromQty . ' → total ' . ($fromQty + $qty) . ')' . $proratedFor,
                ];
            } elseif (in_array($dealType, ['upgrade', 'partial_upgrade'], true)) {
                $row += [
                    'quote_period' => 'Yearly',
                    'annual_rate' => $enteredRate,
                    'current_rate' => $fromRate,
                    'rate' => $this->prorate(max(0, $enteredRate - $fromRate), $days),
                    'note' => $dealType === 'upgrade'
                        ? 'Upgrade from ' . $fromName . ' (' . $qty . ' licences), price difference' . $proratedFor
                        : 'Partial upgrade of ' . $qty . ' of ' . $fromQty . ' licences from ' . $fromName . ' (' . ($fromQty - $qty) . ' stay), price difference' . $proratedFor,
                ];
            } elseif ($dealType === 'renewal') {
                $row['note'] = 'Renewal of ' . $qty . ' licences' . ($till ? ' from ' . $till : '');
            } else {
                $row['note'] = 'Downgrade from ' . $fromName . ' (' . $fromQty . ' licences)' . ($till ? ', effective from renewal ' . $till : '');
            }

            $plans[$i] = array_merge($plan, $row);
        }

        return $plans;
    }

    protected function planYearlyRate(?Plan $plan): float
    {
        if (!$plan) {
            return 0.0;
        }

        return (float) ($plan->actual_price > 0 ? $plan->actual_price : ($plan->price > 0 ? $plan->price : 0));
    }

    protected function prorate(float $yearlyRate, int $days): float
    {
        return $days > 0 ? round($yearlyRate / 365 * $days, 2) : round($yearlyRate, 2);
    }

    protected function quotationPlans(Quotation $quotation): array
    {
        $plans = $quotation->plans ?? [];
        if (is_string($plans)) {
            $decoded = json_decode($plans, true);
            $plans = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($plans)) {
            $plans = [];
        }
        $plans = array_values($plans);

        if (!empty($plans)) {
            return $plans;
        }

        $lead = $quotation->lead;
        if ($lead) {
            $lead->loadMissing('services.plan', 'services.provider');
            $fromLead = $lead->services
                ->reject(fn ($s) => method_exists($s, 'isCompetitorInfo') && $s->isCompetitorInfo())
                ->values()
                ->map(function ($s) {
                    $planRel = $s->relationLoaded('plan') ? $s->getRelation('plan') : $s->plan()->first();
                    $providerRel = $s->relationLoaded('provider') ? $s->getRelation('provider') : $s->provider()->first();
                    return [
                        'provider_id' => $s->provider_id,
                        'plan_id' => $s->plan_id,
                        'name' => optional($planRel)->name ?: (optional($planRel)->title ?: (string) ($s->getAttributes()['plan'] ?? '')),
                        'provider_name' => optional($providerRel)->name ?: (string) ($s->getAttributes()['provider'] ?? ''),
                        'sku' => $s->sku,
                        'quantity' => $s->quantity ?: 1,
                        'rate' => $s->rate,
                        'quote_period' => $s->quote_period ?: 'Yearly',
                    ];
                })
                ->all();
            if (!empty($fromLead)) {
                return $fromLead;
            }
        }

        if ($quotation->plan_name) {
            return [[
                'name' => $quotation->plan_name,
                'sku' => '',
                'quantity' => $quotation->quantity,
                'rate' => 0,
                'quote_period' => $quotation->quotation_period ?: 'Yearly',
            ]];
        }

        return [];
    }

    protected function buildSheets(Quotation $quotation): array
    {
        $plans = $this->quotationPlans($quotation);

        $domainType = optional($quotation->lead)->domain_type ?? '';
        $chargeDomain = $quotation->domain_name
            && $domainType !== 'existing'
            && ($domainType === 'new' || (float) $quotation->domain_rate > 0);

        $letters = range('A', 'Z');
        $sheets = [];

        $leadPeriods = [];
        $lead = $quotation->lead;
        if ($lead) {
            $lead->loadMissing('services');
            foreach ($lead->services as $service) {
                if (method_exists($service, 'isCompetitorInfo') && $service->isCompetitorInfo()) {
                    continue;
                }
                $pid = (int) $service->plan_id;
                if ($pid && !empty($service->quote_period)) {
                    $leadPeriods[$pid] = $service->quote_period;
                }
            }
        }

        foreach ($plans as $i => $plan) {
            $qty = (float) ($plan['quantity'] ?? 0);
            $rate = (float) ($plan['rate'] ?? 0);
            $sub = $qty * $rate;
            $showDomain = $i === 0 && $chargeDomain;
            if ($showDomain) {
                $sub += (float) $quotation->domain_rate;
            }
            $discountAmt = $sub * ((float) $quotation->discount / 100);
            $afterDiscount = $sub - $discountAmt;
            $gstAmt = $afterDiscount * 0.18;
            $planId = (int) ($plan['plan_id'] ?? 0);
            $period = trim((string) ($plan['quote_period'] ?? ''));
            if ($planId && !empty($leadPeriods[$planId])) {
                $period = $leadPeriods[$planId];
            }
            if ($period === '') {
                $period = (string) ($quotation->quotation_period ?: 'Yearly');
            }
            $periodMeta = $this->quotePeriodLabels($period);

            $sheets[] = [
                'letter' => $letters[$i] ?? (string) ($i + 1),
                'plan' => $plan,
                'qty' => $qty,
                'sub' => $sub,
                'discountAmt' => $discountAmt,
                'gstAmt' => $gstAmt,
                'grand' => $afterDiscount + $gstAmt,
                'showDomain' => $showDomain,
                'period' => $period,
                'periodMeta' => $periodMeta,
            ];
        }

        return $sheets;
    }

    protected function quotePeriodLabels(?string $period): array
    {
        $period = trim((string) $period);
        if ($period === '') {
            $period = 'Yearly';
        }

        $map = [
            'Monthly' => ['unit' => 'Per Month', 'total' => 'For Month'],
            'Quarterly' => ['unit' => 'Per Quarter', 'total' => 'For Quarter'],
            'Half Yearly' => ['unit' => 'Per Half Year', 'total' => 'For Half Year'],
            'Yearly' => ['unit' => 'Per Annual', 'total' => 'For Year'],
            '3 Yearly' => ['unit' => 'Per 3 Years', 'total' => 'For 3 Years'],
        ];
        $labels = $map[$period] ?? ['unit' => $period, 'total' => $period];
        $labels['period'] = $period;

        return $labels;
    }

    protected function quotationPdfHtml(Quotation $quotation, array $sheets): string
    {
        $logoPath = public_path('superadmin/crm/images/logo.png');
        $logoSrc = '';
        if (is_file($logoPath)) {
            $logoSrc = 'data:image/png;base64,' . base64_encode((string) file_get_contents($logoPath));
        }

        return view('superadmin.crm.quotations.pdf', compact('quotation', 'sheets', 'logoSrc'))->render();
    }

    protected function htmlToPdf(string $html): ?string
    {
        $options = [
            'defaultFont' => 'DejaVu Sans',
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'isPhpEnabled' => false,
        ];

        if (app()->bound('dompdf.wrapper')) {
            try {
                return app('dompdf.wrapper')->loadHtml($html)->setPaper('a4', 'portrait')->setOptions($options)->output();
            } catch (\Throwable $e) {
                Log::warning('Quotation PDF wrapper failed', ['error' => $e->getMessage()]);
            }
        }

        if (class_exists(\Dompdf\Dompdf::class)) {
            try {
                $dompdf = new \Dompdf\Dompdf($options);
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();

                return $dompdf->output();
            } catch (\Throwable $e) {
                Log::warning('Quotation PDF failed', ['error' => $e->getMessage()]);
            }
        }

        return null;
    }

    protected function parseMailAddresses(?string $raw): \Illuminate\Support\Collection
    {
        return collect(preg_split('/[\s,;]+/', (string) $raw))
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();
    }

    protected function mergeAddressList($previous, array $added): array
    {
        if (!is_array($previous)) {
            $previous = [];
        }

        return collect($previous)
            ->merge($added)
            ->map(fn ($item) => strtolower(trim((string) $item)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function ensureQuotationColumn(string $column, string $type): void
    {
        try {
            if (!Schema::connection('pgsql_crm')->hasColumn('quotations', $column)) {
                DB::connection('pgsql_crm')->statement(
                    'ALTER TABLE quotations ADD COLUMN IF NOT EXISTS ' . $column . ' ' . $type
                );
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    protected function checkPlans($plans)
    {
        $pairs = [];
        $otherProviders = [];
        foreach ($plans as $row) {
            $id = (int) ($row['provider_id'] ?? 0);
            $planId = (int) ($row['plan_id'] ?? 0);
            if (!$id) {
                continue;
            }
            $pair = $id . ':' . $planId;
            if (in_array($pair, $pairs, true)) {
                throw ValidationException::withMessages(['plans' => 'This plan is already added.']);
            }
            $pairs[] = $pair;
            $name = optional(Provider::find($id))->name;
            if ($name && stripos($name, 'google') !== false) {
                continue;
            }
            if (in_array($id, $otherProviders, true)) {
                throw ValidationException::withMessages(['plans' => 'Same provider can only be added once.']);
            }
            $otherProviders[] = $id;
        }
    }
}
