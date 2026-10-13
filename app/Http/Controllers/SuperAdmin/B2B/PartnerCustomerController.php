<?php

namespace App\Http\Controllers\SuperAdmin\B2B;

use App\Http\Controllers\Controller;
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

class PartnerCustomerController extends Controller
{
    protected $request;
    protected $customer;
    protected $partner;
    protected $renewalService;
    protected $invoiceService;

    public function __construct(Customer $customer, Request $request, Partner $partner, RenewalService $renewalService, InvoiceService $invoiceService)
    {
        $this->customer = $customer;
        $this->request = $request;
        $this->partner = $partner;
        $this->renewalService = $renewalService;
        $this->invoiceService = $invoiceService;
    }

    public function index($partner_id)
    {
        $partner_id = Crypt::decrypt($partner_id); // Decrypt partner_id
        $partner = $this->partner->findOrFail($partner_id);
        $encryptedPartnerId = Crypt::encrypt($partner->id);
        return view('superadmin.b2b_partners.customers.index', compact('partner', 'encryptedPartnerId'));
    }

    public function b2b_partner_customers_list_table($partner_id)
    {
        $partner_id = Crypt::decrypt($partner_id); // Decrypt partner_id
        $draw            = $this->request->get('draw');
        $start           = $this->request->get("start");
        $rowPerPage      = $this->request->get("length");
        $orderArray      = $this->request->get('order');
        $columnNameArray = $this->request->get('columns');
        $filterName      = trim((string) $this->request->get('filter_name', ''));
        $filterDomain    = trim((string) $this->request->get('filter_domain', ''));
        $filterFromDate  = trim((string) $this->request->get('filter_from_date', ''));
        $filterToDate    = trim((string) $this->request->get('filter_to_date', ''));
        $filterMobile    = trim((string) $this->request->get('filter_mobile', ''));
        $filterGstin     = trim((string) $this->request->get('filter_gstin', ''));
        $filterEmail     = trim((string) $this->request->get('filter_email', ''));
        $filterStatus    = trim((string) $this->request->get('filter_status', ''));

        $globalSearchValue = $this->request->get('search')['value'] ?? '';

        if (!empty($orderArray)) {
            $columnIndex     = $orderArray[0]['column'];
            $columnSortOrder = $orderArray[0]['dir'];
            $columnName      = $columnNameArray[$columnIndex]['data'] ?? 'customer_no';
            if (!in_array($columnName, ['customer_no', 'name', 'email', 'mobile', 'domain_name', 'domain_count', 'total_orders_count', 'company_name', 'gstin', 'status', 'created_at'], true)) {
                $columnName = 'customer_no';
            }
        } else {
            $columnIndex     = 1;
            $columnSortOrder = 'asc';
            $columnName      = 'customer_no';
        }

        $baseQuery = $this->customer->where('partner_id', $partner_id);
        $query = clone $baseQuery;

        if ($filterName !== '') {
            $query->where('name', 'like', '%' . $filterName . '%');
        }
        if ($filterDomain !== '') {
            $query->whereExists(function ($q) use ($partner_id, $filterDomain) {
                $q->select(DB::raw(1))
                    ->from('orders')
                    ->whereColumn('orders.user_id', 'customers.id')
                    ->where('orders.partner_id', $partner_id)
                    ->where('orders.domain_name', 'like', '%' . $filterDomain . '%')
                    ->whereIn('orders.status', ['completed', 'active', 'pending']);
                if (Schema::hasColumn('orders', 'order_type')) {
                    $q->where('orders.order_type', 'b2b');
                }
            });
        }
        if ($filterFromDate !== '') {
            $query->whereDate('created_at', '>=', $filterFromDate);
        }
        if ($filterToDate !== '') {
            $query->whereDate('created_at', '<=', $filterToDate);
        }
        if ($filterMobile !== '') {
            $query->where('mobile', 'like', '%' . $filterMobile . '%');
        }
        if ($filterGstin !== '') {
            $query->where('gstin', 'like', '%' . $filterGstin . '%');
        }
        if ($filterEmail !== '') {
            $query->where('email', 'like', '%' . $filterEmail . '%');
        }
        if ($filterStatus !== '' && in_array($filterStatus, ['0', '1'], true)) {
            $query->where('status', (int) $filterStatus);
        }

        if (!empty($globalSearchValue)) {
            $query->where(function ($q) use ($globalSearchValue) {
                $q->where('name', 'like', '%' . $globalSearchValue . '%')
                  ->orWhere('customer_no', 'like', '%' . $globalSearchValue . '%')
                  ->orWhere('email', 'like', '%' . $globalSearchValue . '%')
                  ->orWhere('mobile', 'like', '%' . $globalSearchValue . '%')
                  ->orWhere('company_name', 'like', '%' . $globalSearchValue . '%')
                  ->orWhere('gstin', 'like', '%' . $globalSearchValue . '%');
            });
        }

        $totalData = $baseQuery->count();
        $totalFiltered = $query->count();

        $arrData = $query->orderBy($columnName, $columnSortOrder)
            ->offset($start)
            ->limit($rowPerPage)
            ->get();

        $data = [];
        foreach ($arrData as $index => $customer) {
            $ordersQuery = Order::query()
                ->where('partner_id', $partner_id)
                ->where('user_id', $customer->id)
                ->when(Schema::hasColumn('orders', 'order_type'), function ($q) {
                    $q->where('order_type', 'b2b');
                })
                ->whereIn('status', ['completed', 'active', 'pending']);

            $latestDomain = (clone $ordersQuery)
                ->orderByDesc('id')
                ->value('domain_name');
            $totalOrdersCount = (clone $ordersQuery)->count();
            $domainCount = (clone $ordersQuery)
                ->whereNotNull('domain_name')
                ->where('domain_name', '!=', '')
                ->distinct('domain_name')
                ->count('domain_name');

            $status = ($customer->status == 1) 
                ? '<span class="badge bg-success">Active</span>' 
                : '<span class="badge bg-danger">Inactive</span>';
            $action = '<div class="d-flex justify-content-center gap-1">
                            <a class="btn btn-light btn-icon btn-sm rounded-circle" title="Edit" href="' . route('superadmin.b2b_partners.customers.edit', [
                                    'partner_id' => Crypt::encrypt($partner_id), 
                                    'id' => Crypt::encrypt($customer->id)
                                ]) . '"><i class="ti ti-edit fs-lg"></i></a>
                            <a class="btn btn-light btn-icon btn-sm rounded-circle" title="Renewal Center" href="' . route('superadmin.renewals.partner', [
                                    'partner_id' => Crypt::encrypt($partner_id)
                                ]) . '"><i class="ti ti-refresh fs-lg"></i></a>
                            <a class="btn btn-light btn-icon btn-sm rounded-circle" title="View Customer Details" href="' . route('superadmin.b2b_partners.customers.subscriptions', [
                                    'partner_id' => Crypt::encrypt($partner_id),
                                    'customer_id' => Crypt::encrypt($customer->id)
                                ]) . '"><i class="ti ti-eye fs-lg"></i></a>
                            <a class="btn btn-light btn-icon btn-sm rounded-circle" title="View Billing" href="' . route('superadmin.b2b_partners.customers.billing', [
                                    'partner_id' => Crypt::encrypt($partner_id), 
                                    'customer_id' => Crypt::encrypt($customer->id)
                                ]) . '"><i class="ti ti-eye-search fs-lg"></i></a>
                                </div>';

            $customerNo = trim((string) ($customer->customer_no ?? ''));
            if ($customerNo === '') {
                $customerNo = 'CUST' . str_pad((string) $customer->id, 5, '0', STR_PAD_LEFT);
            }

            $data[] = [
                'sr_no'        => (int) $start + $index + 1,
                'customer_no'  => $customerNo,
                'name'         => format_display_name($customer->name),
                'email'        => strtolower(trim((string) ($customer->email ?? ''))) ?: '—',
                'mobile'       => trim((string) ($customer->mobile ?? '')) ?: '—',
                'domain_name'  => $latestDomain ?: '—',
                'domain_count' => (int) $domainCount,
                'total_orders_count' => (int) $totalOrdersCount,
                'company_name' => format_display_name($customer->company_name),
                'gstin'        => $customer->gstin ?: '—',
                'status'       => $status,
                'action'       => $action,
            ];
        }

        $response = [
            'draw' => intval($draw),
            'recordsTotal' => $totalData,
            'recordsFiltered' => $totalFiltered,
            'data' => $data,
        ];

        return response()->json($response);

    }

    public function startRenewal($partner_id, $customer_id)
    {
        $partnerId = Crypt::decrypt($partner_id);
        $customerId = Crypt::decrypt($customer_id);

        $partner = $this->partner->findOrFail($partnerId);
        $customer = $this->customer->where('partner_id', $partnerId)->findOrFail($customerId);

        $latestOrder = Order::query()
            ->where('partner_id', $partnerId)
            ->where('user_id', $customer->id)
            ->where('order_type', 'b2b')
            ->whereIn('status', ['completed', 'active', 'pending'])
            ->orderByDesc('created_at')
            ->first();

        if (!$latestOrder) {
            return redirect()
                ->route('superadmin.b2b_partners.customers.subscriptions', [
                    'partner_id' => Crypt::encrypt($partner->id),
                    'customer_id' => Crypt::encrypt($customer->id),
                ])
                ->with('error', 'No existing subscription found to renew.');
        }

        return redirect()->route('superadmin.b2b_partners.customers.update_plan', [
            'partner_id' => Crypt::encrypt($partner->id),
            'subscription_id' => Crypt::encrypt($latestOrder->id),
        ]);
    }

    /**
     * Add customer without a partner in the URL: user picks an approved partner from a select.
     */
    public function b2b_partner_customers_add_open()
    {
        $partnersList = $this->partner->query()
            ->where('status', 'approved')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('superadmin.b2b_partners.customers.form', [
            'partnerLocked' => false,
            'partner' => null,
            'partnersList' => $partnersList,
            'customer' => null,
            'encryptedPartnerId' => null,
            'encryptedCustomerId' => null,
        ]);
    }

    public function b2b_partner_customers_add($partner_id, $id = null)
    {
        $data = [];
        $partner_id = Crypt::decrypt($partner_id);
        $data['partner'] = $this->partner->findOrFail($partner_id);
        $data['partnerLocked'] = true;

        if (($data['partner']->status ?? '') !== 'approved' && $id === null) {
            return redirect()
                ->route('superadmin.b2b_partners.customers.list', ['partner_id' => Crypt::encrypt($partner_id)])
                ->with('error', 'Only approved partners can add new customers.');
        }

        $data['customer'] = null;
        $data['partnersList'] = collect();

        if ($id !== null) {
            $id = Crypt::decrypt($id);
            $data['customer'] = $this->customer->where('partner_id', $partner_id)->findOrFail($id);
            $data['encryptedCustomerId'] = Crypt::encrypt($id);
        }
        $data['encryptedPartnerId'] = Crypt::encrypt($partner_id);

        return view('superadmin.b2b_partners.customers.form', $data);
    }

    public function b2b_partner_customers_validation($partner_id, Request $request)
    {
        $partner_id = Crypt::decrypt($partner_id);
        $partner = $this->partner->findOrFail($partner_id);
        $encryptedCustomerId = $request->input('id');
        $customerIdPlain = null;

        if ($encryptedCustomerId) {
            try {
                $customerIdPlain = Crypt::decrypt($encryptedCustomerId);
            } catch (\Throwable $e) {
                return response()->json(['error' => true, 'message' => 'Invalid customer reference.'], 422);
            }
        }

        if (($partner->status ?? '') !== 'approved' && !$customerIdPlain) {
            return response()->json([
                'error' => true,
                'message' => 'Only approved partners can add new customers.'
            ], 422);
        }

        if ($customerIdPlain) {
            $rules = [
                'name' => 'required|string|max:255',
                'email' => [
                    'required',
                    'string',
                    'email',
                    'max:255',
                    Rule::unique('pgsql_billing.customers', 'email')
                        ->where(fn ($q) => $q->where('partner_id', $partner_id))
                        ->ignore($customerIdPlain),
                ],
                'mobile' => 'required|digits:10',
            ];
        } else {
            $rules = [
                'name' => 'required|string|max:255',
                'email' => [
                    'required',
                    'string',
                    'email',
                    'max:255',
                    Rule::unique('pgsql_billing.customers', 'email')
                        ->where(fn ($q) => $q->where('partner_id', $partner_id)),
                ],
                'mobile' => 'required|digits:10',
                'company_name' => 'required|string|max:255',
                'gstin' => ['required', 'string', 'size:15', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/'],
                'company_address' => 'nullable|string|max:255',
                'country' => 'nullable|string|max:255',
                'state' => 'nullable|string|max:255',
                'city' => 'nullable|string|max:255',
                'zip_code' => 'nullable|string|max:255',
                'pan_no' => 'nullable|string|max:255',
                'legal_name' => 'nullable|string|max:255',
                'trade_name' => 'nullable|string|max:255',
                'address' => 'nullable|string|max:1000',
                'pincode' => 'nullable|string|max:20',
                'gst_status' => 'nullable|string|max:100',
                'last_updated' => 'nullable|date',
                'constitution' => 'nullable|string|max:255',
                'registration_date' => 'nullable|date',
                'nature_of_business' => 'nullable|string|max:2000',
                'status2' => 'required|in:0,1',
            ];
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'messages' => $validator->errors()->toArray()]);
        }

        DB::beginTransaction();
        try {
            if ($customerIdPlain) {
                $customer = $this->customer->where('partner_id', $partner_id)->findOrFail($customerIdPlain);
                $customer->update([
                    'name' => $request->name,
                    'email' => $request->email,
                    'mobile' => $request->mobile,
                ]);
                $message = 'Partner Customer updated successfully!';
            } else {
                $data = $request->only([
                    'name', 'email', 'mobile', 'company_name', 'gstin', 'company_address',
                    'country', 'state', 'city', 'zip_code', 'pan_no',
                    'legal_name', 'trade_name', 'address', 'pincode', 'gst_status',
                    'last_updated', 'constitution', 'registration_date', 'nature_of_business',
                ]);
                $data['gstin'] = strtoupper((string) ($data['gstin'] ?? ''));
                $data['partner_id'] = $partner_id;
                $data['status'] = $request->input('status2');
                $data['customer_no'] = Customer::getNextCustomerNo($partner_id);
                $this->customer->create($data);
                $message = 'Partner Customer created successfully!';
            }
            DB::commit();

            return response()->json([
                'success' => $message,
                'redirect' => route('superadmin.b2b_partners.customers.list', ['partner_id' => Crypt::encrypt($partner_id)]),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => true, 'message' => 'Failed to process Partner Customer: ' . $e->getMessage()], 500);
        }
    }

    public function b2b_partner_customers_delete($partner_id, Request $request)
    {
        $customerId = Crypt::decrypt($request->input('id'));
        $partner_id = Crypt::decrypt($partner_id);
        DB::beginTransaction();
        try {
            $customer = $this->customer->where('partner_id', $partner_id)->find($customerId);
            if ($customer) {
                $customer->delete(); // This will perform a soft delete if `SoftDeletes` trait is used in the Customer model
                DB::commit();
                return response()->json(['success' => 'Partner Customer deleted successfully!']); 
            } else {
                return response()->json(['error' => 'Partner Customer not found!'], 404);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => true, 'message' => 'Failed to delete Partner Customer: ' . $e->getMessage()], 500);
        }
    }

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

        // pgsql `orders` has no `amount` column — selecting it causes DataTables Ajax 500.
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
            if (trim((string) $row->domain_name) !== '') {
                $actions .= '<a class="btn btn-light btn-icon btn-sm rounded-circle" title="Domain History" target="_blank" href="'
                    . e(route('superadmin.b2b_partners.domain_history', ['partner_id' => $partner_id, 'domain' => $row->domain_name]))
                    . '"><i class="ti ti-history fs-lg"></i></a>';
            }

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

    public function domainHistoryPage($partner_id, Request $request)
    {
        $partner = $this->partner->findOrFail(Crypt::decrypt($partner_id));
        $domain = trim((string) $request->query('domain', ''));
        abort_if($domain === '', 404);

        // Same data as the partner API /api/v1/partner/domain-history
        $request->merge(['partner_id' => (int) $partner->id, 'domain_names' => [$domain]]);
        $res = app(\App\Http\Controllers\Api\PartnerAuthController::class)->domainHistory($request)->getData(true);
        $history = $res['data'][0]['history'] ?? [];

        return view('superadmin.b2b_partners.customers.domain_history', compact('partner', 'domain', 'history'));
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

    /**
     * Fire SMS + Email after a successful plan renewal.
     *
     * Resolves the customer's mobile / email / name from the `customers`
     * table using `user_id` on the order. Both channels are wrapped in
     * their own try/catch so a failure on one never affects the other,
     * and neither ever throws back to the caller.
     */
    /**
     * Block checkout when quantity exceeds plans.customer_limit (if limit > 0).
     */
    private function rejectIfQuantityExceedsPlanLimit(Plan $plan, int $quantity): ?\Illuminate\Http\RedirectResponse
    {
        $limit = (int) ($plan->customer_limit ?? 0);
        if ($limit > 0 && $quantity > $limit) {
            return back()->with('error', 'Licenses cannot exceed plan limit of ' . $limit . '.');
        }

        return null;
    }

    private function notifyPlanRenewed(Order $newOrder, Order $baseOrder, Carbon $newEnd): void
    {
        try {
            $customer = null;
            if (!empty($baseOrder->user_id)) {
                $customer = Customer::query()->find($baseOrder->user_id);
            }

            $userMobile = (string) ($customer->mobile ?? $customer->phone ?? '');
            $userEmail  = (string) ($customer->email ?? '');
            $userName   = (string) ($customer->name ?? $customer->company_name ?? 'Customer');

            $planName = (string) (
                $newOrder->plan_name
                    ?? optional(\App\Models\Plan::find($newOrder->plan_id))->name
                    ?? 'Subscription'
            );
            $amount       = (float) ($newOrder->total_amount ?? 0);
            $validityDate = $newEnd->format('d M, Y');
            $orderNo      = (string) ($newOrder->order_no ?? $newOrder->id);

            // ---- SMS ----
            try {
                if ($userMobile !== '') {
                    app(SmsService::class)->sendRenewPlan($userMobile, $validityDate, $amount);
                }
            } catch (\Throwable $e) {
                Log::warning('Plan renewal SMS failed', [
                    'order_id' => $newOrder->id,
                    'mobile'   => $userMobile,
                    'error'    => $e->getMessage(),
                ]);
            }

            // ---- Email ----
            try {
                if ($userEmail !== '' && filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
                    Mail::to($userEmail)->send(new PlanRenewedMail(
                        $planName,
                        $amount,
                        $validityDate,
                        $userName,
                        $orderNo
                    ));
                }
            } catch (\Throwable $e) {
                Log::warning('Plan renewal email failed', [
                    'order_id' => $newOrder->id,
                    'email'    => $userEmail,
                    'error'    => $e->getMessage(),
                ]);
            }
        } catch (\Throwable $e) {
            // Catch-all — should never happen, but never let notifications
            // bubble up and surprise the renewal flow.
            Log::warning('Plan renewal notification dispatcher failed', [
                'order_id' => $newOrder->id ?? null,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /* ====================================================================
     * Plan-change helpers (shared by upgrade + downgrade flows)
     * ================================================================== */

    /**
     * Build the new Order row for an upgrade/downgrade by replicating the
     * base order, then resetting all UNIQUE/identity columns so a fresh
     * order_no/sr_no is allocated and the insert can never collide with
     * the parent (which is the bug behind the duplicate-key error).
     */
    private function buildPlanChangeOrder(
        Order $baseOrder,
        Plan $newPlan,
        int $qty,
        array $summary,
        int $partnerId,
        string $category
    ): Order {
        $newOrder = $baseOrder->replicate();

        // CRITICAL: replicate() copies non-PK columns including order_no/sr_no.
        // Both have UNIQUE indexes, so we must clear them and let the
        // identifier allocator assign fresh ones.
        $newOrder->order_no = null;
        if (Schema::hasColumn('orders', 'sr_no')) {
            $newOrder->sr_no = null;
        }

        $next = $this->nextOrderIdentifiers();
        $newOrder->order_no = $next['order_no'];
        if (Schema::hasColumn('orders', 'sr_no')) {
            $newOrder->sr_no = $next['sr_no'];
        }

        $newPlanSubtotal = (float) ($summary['new_plan_price'] ?? $summary['subtotal'] ?? $summary['new_plan_cost'] ?? 0);
        $proRataCredit = (float) ($summary['pro_rata_adjustment'] ?? $summary['prorated_credit'] ?? $summary['remaining_credit'] ?? 0);

        $newOrder->partner_id        = $partnerId;
        $newOrder->plan_id           = $newPlan->id;
        $newOrder->previous_plan_id  = $baseOrder->plan_id;
        $newOrder->parent_order_id   = $baseOrder->id;
        $newOrder->order_type        = 'b2b';
        $newOrder->order_category    = $category;
        $newOrder->price             = (float) $newPlan->price;
        $newOrder->quantity          = $qty;
        $newOrder->subtotal          = $newPlanSubtotal;
        $newOrder->tax_amount        = (float) ($summary['gst_amount'] ?? 0);
        $newOrder->total_amount      = (float) ($summary['payable_amount'] ?? 0);
        $newOrder->credit_used       = (float) ($summary['payable_amount'] ?? 0);
        if ($proRataCredit > 0 && Schema::hasColumn('orders', 'discount_amount')) {
            $newOrder->discount_amount = $proRataCredit;
        }
        $newOrder->status            = 'completed';
        $newOrder->payment_method    = $newOrder->payment_method ?: 'wallet';
        if (Schema::hasColumn('orders', 'payment_mode') && empty($newOrder->payment_mode)) {
            $newOrder->payment_mode = 'wallet';
        }

        // All Tizzy plans are 12 month subscriptions — never persist NULL.
        if (Schema::hasColumn('orders', 'duration')) {
            $newOrder->duration = '12 month';
        }

        // Subscription window: start tomorrow if base still running, else today.
        $today = Carbon::now()->startOfDay();
        $oldEnd = !empty($baseOrder->subscription_end_date)
            ? Carbon::parse((string) $baseOrder->subscription_end_date)->startOfDay()
            : $today;
        $newStart = $oldEnd->greaterThan($today) ? $oldEnd->copy()->addDay() : $today;
        $newEnd   = $newStart->copy()->addYear()->subDay();
        if (Schema::hasColumn('orders', 'subscription_start_date')) {
            $newOrder->subscription_start_date = $newStart->toDateString();
        }
        if (Schema::hasColumn('orders', 'subscription_end_date')) {
            $newOrder->subscription_end_date = $newEnd->toDateString();
        }

        if (Schema::hasColumn('orders', 'plan_name')) {
            $newOrder->plan_name = $newPlan->name;
        }

        // Wipe payment-gateway columns inherited from the base order so we
        // never recycle an old razorpay id and break the new wallet ledger.
        foreach (['razorpay_order_id', 'razorpay_payment_id', 'razorpay_signature'] as $rzCol) {
            if (Schema::hasColumn('orders', $rzCol)) {
                $newOrder->{$rzCol} = null;
            }
        }

        $newOrder->created_at = now();
        $newOrder->updated_at = now();

        return $newOrder;
    }

    /**
     * Mirror of BillingController::nextOrderIdentifiers() (kept private there).
     * Allocates the next unique order_no using the admin_settings prefix and
     * MAX(sr_no)+1 inside a SELECT … FOR UPDATE so concurrent upgrades cannot
     * generate the same number.
     */
    private function nextOrderIdentifiers(): array
    {
        $connection = (new Order())->getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'pgsql') {
            $connection->statement('SELECT pg_advisory_xact_lock(?)', [758291034]);
            $nextSrNo = ((int) Order::query()->max('sr_no')) + 1;
            if ($nextSrNo <= 0) {
                $nextSrNo = ((int) Order::query()->count()) + 1;
            }
        } else {
            $nextSrNo = ((int) Order::query()->lockForUpdate()->max('sr_no')) + 1;
            if ($nextSrNo <= 0) {
                $nextSrNo = ((int) Order::query()->lockForUpdate()->count()) + 1;
            }
        }

        $orderPrefix = $this->readOrderPrefix();
        $orderNo = $orderPrefix . str_pad((string) $nextSrNo, 5, '0', STR_PAD_LEFT);
        while (Order::query()->where('order_no', $orderNo)->exists()) {
            $nextSrNo++;
            $orderNo = $orderPrefix . str_pad((string) $nextSrNo, 5, '0', STR_PAD_LEFT);
        }

        return [
            'order_no' => $orderNo,
            'sr_no'    => $nextSrNo,
        ];
    }

    private function readOrderPrefix(): string
    {
        $prefix = (string) AdminSetting::query()->whereKey(1)->value('order_prefix');
        if (trim($prefix) !== '') {
            return strtoupper(trim($prefix));
        }
        $prefix = (string) AdminSetting::query()->orderByDesc('id')->value('order_prefix');
        if (trim($prefix) !== '') {
            return strtoupper(trim($prefix));
        }
        return 'TNX';
    }

    /**
     * Auto-generate the order_po_details row (po_mode=generated, journey_status=placed).
     */
    private function upsertAutoPoForOrder(int $orderId, string $aadhaar, float $amount): void
    {
        if (!Schema::hasTable('order_po_details')) {
            return;
        }
        $cols    = Schema::getColumnListing('order_po_details');
        $payload = ['order_id' => $orderId];
        if (in_array('po_mode', $cols, true))           $payload['po_mode']           = 'generated';
        if (in_array('journey_status', $cols, true))    $payload['journey_status']    = 'placed';
        if ($aadhaar !== '' && strlen($aadhaar) === 12) {
            if (in_array('aadhar_verified', $cols, true))    $payload['aadhar_verified']    = 1;
            if (in_array('aadhar_verified_at', $cols, true)) $payload['aadhar_verified_at'] = now();
            if (in_array('aadhaar_masked', $cols, true))     $payload['aadhaar_masked']     = 'XXXXXXXX' . substr($aadhaar, -4);
            if (in_array('aadhaar_ref_no', $cols, true))     $payload['aadhaar_ref_no']     = 'AADH-' . strtoupper(Str::random(8));
        }
        if (in_array('total_amount', $cols, true))      $payload['total_amount']      = $amount;
        if (in_array('items_summary', $cols, true))     $payload['items_summary']     = '[]';
        if (in_array('created_at', $cols, true))        $payload['created_at']        = now();
        if (in_array('updated_at', $cols, true))        $payload['updated_at']        = now();
        OrderPoDetail::query()->updateOrCreate(
            ['order_id' => $orderId],
            array_diff_key($payload, ['order_id' => true])
        );
    }

    /**
     * Post-save pipeline aligned with BillingController::completeOrderAfterAadhaarVerification
     * and SuperAdmin orders.save — GST invoice, Sandbox e-invoice, PDF, SMS + email.
     */
    private function finalizeB2bCreditOrderAfterSave(int $orderId, string $orderCategory): void
    {
        if ($orderId <= 0) {
            return;
        }

        $billing = app(BillingController::class);

        try {
            $billing->finalizePartnerB2bOrderPostSave($orderId);
        } catch (\Throwable $e) {
            Log::warning('Credit order finalize failed', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
        }

        try {
            $billing->persistOrderInvoicePdfToStorage($orderId);
        } catch (\Throwable $e) {
            Log::warning('Credit order invoice PDF failed', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
        }

        try {
            $billing->markInvoiceStatusPendingForOrder($orderId);
        } catch (\Throwable $e) {
            Log::warning('Credit order invoice status update failed', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
        }

        Order::query()->where('id', $orderId)->update([
            'status'     => 'completed',
            'updated_at' => now(),
        ]);

        $orderForNotify = Order::query()->where('id', $orderId)->first();
        if (!$orderForNotify) {
            return;
        }

        $category = strtolower(trim($orderCategory));

        if ($category === 'renewal') {
            try {
                $billing->sendPlanRenewedNotifications($orderId, $orderForNotify, false);
            } catch (\Throwable $e) {
                Log::warning('Credit renewal notifications failed', [
                    'order_id' => $orderId,
                    'error'    => $e->getMessage(),
                ]);
            }

            return;
        }

        try {
            $billing->sendOrderPlacedEmailForOrder($orderId, false);
        } catch (\Throwable $e) {
            Log::warning('Credit order placement email failed', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
        }

        $mobile = '';
        if (!empty($orderForNotify->user_id)) {
            $mobile = trim((string) (Customer::query()->whereKey((int) $orderForNotify->user_id)->value('mobile') ?? ''));
        }

        if ($mobile === '') {
            return;
        }

        try {
            $amount  = (float) ($orderForNotify->total_amount ?? 0);
            $orderNo = (string) ($orderForNotify->order_no ?? $orderId);
            $sms     = app(SmsService::class);

            if (in_array($category, ['upgrade', 'downgrade'], true)) {
                $planName = (string) ($orderForNotify->plan_name ?? '');
                if ($planName === '' && !empty($orderForNotify->plan_id)) {
                    $planName = (string) (Plan::query()->where('id', (int) $orderForNotify->plan_id)->value('name') ?? '');
                }
                $sms->sendUpgradePlatform($mobile, $planName !== '' ? $planName : 'Upgraded plan', $amount);
            } else {
                $sms->sendPaymentConfirmation($mobile, $amount, $orderNo);
            }
        } catch (\Throwable $e) {
            Log::warning('Credit order placement SMS failed', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create the GST `invoice_mst` + `invoice_sub` rows for a plan change
     * via InvoiceService (which understands action='upgrade' / 'renew').
     * Side-effect: also creates the modern `invoices` row if missing —
     * but the SandboxEInvoiceService will create one when needed too,
     * so a missing invoices row is tolerated.
     */
    private function createGstInvoiceForPlanChange(
        Order $baseOrder,
        Order $newOrder,
        Plan $newPlan,
        int $qty,
        array $summary,
        string $action
    ): void {
        $subtotal  = (float) ($summary['taxable_amount'] ?? $summary['new_plan_price'] ?? $summary['subtotal'] ?? $summary['new_plan_cost'] ?? 0);
        $gstAmount = (float) ($summary['gst_amount'] ?? 0);
        $cgst      = round($gstAmount / 2, 2);
        $sgst      = round($gstAmount / 2, 2);
        $amount    = (float) ($summary['payable_amount'] ?? 0);

        $description = $action === 'downgrade'
            ? 'Plan Downgrade — ' . (string) ($newPlan->name ?? 'New Plan')
            : 'Plan Upgrade — ' . (string) ($newPlan->name ?? 'New Plan');

        $this->invoiceService->createInvoice([
            'cust_id'       => (int) $baseOrder->user_id,
            'status'        => 'pending',
            'action'        => $action === 'downgrade' ? 'upgrade' : 'upgrade', // InvoiceService only knows renew/upgrade/add_order; treat downgrade as upgrade
            'net_amt'       => $amount,
            'taxable_total' => $subtotal,
            'cgst_total'    => $cgst,
            'sgst_total'    => $sgst,
            'items'         => [[
                'description' => $description,
                'hsn_sac'     => '',
                'qty'         => $qty,
                'cnt_rate'    => (float) $newPlan->price,
                'taxable_amt' => $subtotal,
                'cgst'        => $cgst,
                'sgst'        => $sgst,
                'nettotal'    => $amount,
            ]],
        ]);
    }

    /**
     * Best-effort: ask the Sandbox e-invoice service to issue an IRN for
     * the new order. Always swallows exceptions — Sandbox failures must
     * never block an otherwise-completed plan change.
     */
    private function triggerSandboxEInvoice(int $orderId): void
    {
        try {
            $auto = filter_var(env('SANDBOX_AUTO_GENERATE', true), FILTER_VALIDATE_BOOLEAN);
            if (!$auto) {
                return;
            }
            app(SandboxEInvoiceService::class)->generateForOrder($orderId);
        } catch (\Throwable $e) {
            Log::warning('Sandbox e-invoice generation failed (plan change)', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * SMS (UPGRADE_PLATFORM, DLT 1707177650875511604) + Email with the
     * latest invoice PDF attached. Never bubbles up — same contract as
     * notifyPlanRenewed().
     */
    private function notifyPlanUpgraded(Order $newOrder, Order $baseOrder, Plan $newPlan, string $action = 'upgrade'): void
    {
        try {
            $customer = null;
            if (!empty($baseOrder->user_id)) {
                $customer = Customer::query()->find($baseOrder->user_id);
            }

            $userMobile = (string) ($customer->mobile ?? $customer->phone ?? '');
            $userEmail  = (string) ($customer->email ?? '');
            $userName   = (string) ($customer->name ?? $customer->company_name ?? 'Customer');

            $planName     = (string) ($newPlan->name ?? $newOrder->plan_name ?? 'Subscription');
            $amount       = (float) ($newOrder->total_amount ?? 0);
            $orderNo      = (string) ($newOrder->order_no ?? $newOrder->id);
            $validityDate = !empty($newOrder->subscription_end_date)
                ? Carbon::parse((string) $newOrder->subscription_end_date)->format('d M, Y')
                : Carbon::now()->addYear()->format('d M, Y');

            // ---- SMS ----
            try {
                if ($userMobile !== '') {
                    app(SmsService::class)->sendUpgradePlatform($userMobile, $planName, $amount);
                }
            } catch (\Throwable $e) {
                Log::warning('Plan upgrade SMS failed', [
                    'order_id' => $newOrder->id,
                    'mobile'   => $userMobile,
                    'error'    => $e->getMessage(),
                ]);
            }

            // ---- Email (with invoice PDF if available) ----
            try {
                if ($userEmail !== '' && filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
                    $pdf = $this->buildInvoicePdfAttachmentForOrder((int) $newOrder->id);
                    $mail = new PlanUpgradedMail(
                        $planName,
                        $amount,
                        $validityDate,
                        $userName,
                        $orderNo,
                        $action,
                        $pdf // null if no PDF yet — the mailable handles that
                    );
                    Mail::to($userEmail)->send($mail);
                }
            } catch (\Throwable $e) {
                Log::warning('Plan upgrade email failed', [
                    'order_id' => $newOrder->id,
                    'email'    => $userEmail,
                    'error'    => $e->getMessage(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Plan upgrade notification dispatcher failed', [
                'order_id' => $newOrder->id ?? null,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Look up the e-invoice PDF for the given order via the `invoices`
     * table (single source of truth post-refactor) and download its bytes
     * so they can be attached to the upgrade confirmation email.
     *
     * @return array{name:string,content:string,mime:string}|null
     */
    private function buildInvoicePdfAttachmentForOrder(int $orderId): ?array
    {
        try {
            $row = InvoiceMst::query()
                ->where('order_id', $orderId)
                ->orderByDesc('id')
                ->first();
            if (!$row || empty($row->e_invoice_pdf_url)) {
                return null;
            }
            $resp = Http::timeout(15)->get((string) $row->e_invoice_pdf_url);
            if (!$resp->successful()) {
                Log::warning('Upgrade invoice PDF download failed', [
                    'order_id' => $orderId,
                    'status'   => $resp->status(),
                ]);
                return null;
            }
            return [
                'name'    => 'invoice-' . $orderId . '.pdf',
                'content' => $resp->body(),
                'mime'    => 'application/pdf',
            ];
        } catch (\Throwable $e) {
            Log::warning('Upgrade invoice PDF attachment build failed', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * JSON for upgrade/downgrade modal: current subscription, prorated remaining value, alternate plans.
     */
    public function subscriptionPlanModal(Request $request, $partner_id, $subscription_id)
    {
        try {
            $partnerId = Crypt::decrypt($partner_id);
            $orderId = Crypt::decrypt($subscription_id);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Invalid request.'], 422);
        }

        $direction = $request->query('direction', 'upgrade');
        if (!in_array($direction, ['upgrade', 'downgrade'], true)) {
            $direction = 'upgrade';
        }

        $order = Order::query()
            ->where('id', $orderId)
            ->where('order_type', 'b2b')
            ->first([
                'id',
                'user_id',
                'plan_id',
                'price',
                'quantity',
                'duration',
                'created_at',
                'domain_name',
                'subtotal',
                'status',
            ]);
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        $plan = Plan::query()->find($order->plan_id);
        $order->plan_title = $plan ? (string) ($plan->name ?? '') : null;
        $order->provider_id = $plan?->provider_id;
        $order->billing_cycle = $plan?->billing_cycle;

        $customer = $this->customer->where('partner_id', $partnerId)->where('id', $order->user_id)->first();
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Customer not found for this partner.'], 403);
        }

        $start = !empty($order->subscription_start_date)
            ? Carbon::parse((string) $order->subscription_start_date)->startOfDay()
            : Carbon::parse($order->created_at)->startOfDay();
        $end = !empty($order->subscription_end_date)
            ? Carbon::parse((string) $order->subscription_end_date)->startOfDay()
            : $this->renewalService->subscriptionEndDate($start, $order->duration ?? '12 month')->startOfDay();
        $now = Carbon::now()->startOfDay();

        $totalDays = max(1, (int) $start->diffInDays($end));
        if ($now->greaterThanOrEqualTo($end)) {
            $remainingDays = 0;
            $remainingValue = 0.0;
        } else {
            $remainingDays = (int) $now->diffInDays($end);
            $termValue = (float) ($order->subtotal ?? 0) > 0
                ? (float) $order->subtotal
                : ((float) $order->price * (int) $order->quantity);
            $remainingValue = round(($remainingDays / $totalDays) * $termValue, 2);
        }

        $currentPrice = (float) $order->price;
        $plansQuery = Plan::query()
            ->where('provider_id', $order->provider_id)
            ->where('id', '!=', $order->plan_id)
            ->where('status', 1);

        if ($direction === 'upgrade') {
            $plansQuery->where('price', '>=', $currentPrice);
        } else {
            $plansQuery->where('price', '<', $currentPrice);
        }

        $plans = $plansQuery->orderBy('price')->get(['id', 'name', 'storage', 'price', 'billing_cycle']);

        if ($direction === 'upgrade') {
            $currentPlan = Plan::query()->find($order->plan_id);
            if ($currentPlan) {
                $plans = $this->renewalService->filterUpgradePlans($plans, $currentPlan);
            }
        }

        if ($plans->isEmpty() && $direction !== 'upgrade') {
            $plans = Plan::query()
                ->where('provider_id', $order->provider_id)
                ->where('id', '!=', $order->plan_id)
                ->where('status', 1)
                ->orderBy('price')
                ->get(['id', 'name', 'storage', 'price', 'billing_cycle']);
        }

        $plansPayload = $plans->map(function (Plan $plan) {
            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'storage' => $plan->storage,
                'price' => (float) $plan->price,
                'price_label' => $this->planPriceLabel($plan),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'domain_name' => $order->domain_name ?? '',
            'direction' => $direction,
            'current_plan' => [
                'id' => (int) $order->plan_id,
                'title' => $order->plan_title,
                'price_label' => $this->planPriceLabelFromRow($order->price, $order->billing_cycle ?? 'yearly'),
                'provider_id' => (int) $order->provider_id,
            ],
            'remaining_days' => $remainingDays,
            'remaining_value' => $remainingValue,
            'remaining_value_formatted' => number_format($remainingValue, 2),
            'plans' => $plansPayload,
        ]);
    }

    private function planPriceLabel(Plan $plan): string
    {
        return $this->planPriceLabelFromRow((float) $plan->price, $plan->billing_cycle ?? 'yearly');
    }

    private function planPriceLabelFromRow(float $price, string $billingCycle): string
    {
        $suffix = $billingCycle === 'monthly' ? 'per user/month' : 'per user/year';

        return '₹' . number_format($price, 2) . ' ' . $suffix;
    }

    private function partnerCreditUsed(int $partnerId): float
    {
        if (!Schema::hasTable('orders') || !Schema::hasColumn('orders', 'partner_id')) {
            return 0.0;
        }
        $q = Order::query()->where('partner_id', $partnerId);
        if (Schema::hasColumn('orders', 'order_type')) {
            $q->where('order_type', 'b2b');
        }
        if (Schema::hasColumn('orders', 'credit_used')) {
            return (float) $q->sum('credit_used');
        }
        return (float) $q->sum('subtotal');
    }

    public function renewalsIndex(Request $request)
    {
        $trend = strtolower((string) $request->query('trend', 'week'));
        if (!in_array($trend, ['week', 'month'], true)) {
            $trend = 'week';
        }

        $partners = $this->partner->query()
            ->where('status', 'approved')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
        $pendingMap = $this->renewalService->pendingRenewalPartnerCounts();
        $successfulMap = $this->renewalService->successfulRenewalPartnerCounts();

        $rows = $partners->map(function ($partner) use ($pendingMap, $successfulMap) {
            return (object) [
                'id' => $partner->id,
                'name' => $partner->name,
                'email' => $partner->email,
                'pending_renewals' => (int) ($pendingMap->get($partner->id)->pending_count ?? 0),
                'successful_renewals' => (int) ($successfulMap->get($partner->id)->successful_count ?? 0),
            ];
        })->filter(function ($row) {
            return (int) ($row->pending_renewals ?? 0) > 0;
        })->values();

        $totalPendingRenewals = (int) $rows->sum('pending_renewals');
        $renewalsCompleted = $this->renewalService->completedRenewalsCount();
        $renewalRevenue = $this->renewalService->renewalRevenue();
        $conversionRate = $totalPendingRenewals > 0
            ? round(($renewalsCompleted / $totalPendingRenewals) * 100, 2)
            : 0.0;

        [$trendLabels, $trendRenewals, $trendUpgrades] = $this->renewalService->renewalsVsUpgradesSeries($trend);

        return view('superadmin.renewals.index', [
            'title' => 'Renewal Center',
            'partners' => $rows,
            'renewalsIndexTableUrl' => route('superadmin.renewals.index.table'),
            'trend' => $trend,
            'totalPendingRenewals' => $totalPendingRenewals,
            'renewalRevenue' => $renewalRevenue,
            'renewalsCompleted' => $renewalsCompleted,
            'conversionRate' => $conversionRate,
            'trendLabels' => $trendLabels,
            'trendRenewals' => $trendRenewals,
            'trendUpgrades' => $trendUpgrades,
        ]);
    }

    public function renewalsIndexTable(Request $request)
    {
        $today = Carbon::now()->startOfDay();

        // Configurable upcoming window in days (e.g. show renewals expiring in next X days as "soon")
        $expiringSoonDays = max(0, (int) $request->get('expiring_soon_days', 15));
        $filterDaysLeftRaw = $request->get('filter_days_left', '');
        $filterDaysLeftExact = ($filterDaysLeftRaw !== '' && $filterDaysLeftRaw !== null && is_numeric($filterDaysLeftRaw))
            ? (int) $filterDaysLeftRaw
            : null;
        if ($filterDaysLeftExact !== null && $filterDaysLeftExact > $expiringSoonDays) {
            $expiringSoonDays = $filterDaysLeftExact;
        }
        $expiringSoonThreshold = $today->copy()->addDays($expiringSoonDays)->endOfDay();

        $partners = $this->partner->query()
            ->where('status', 'approved')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'mobile']);

        $rows = collect();

        foreach ($partners as $partner) {
            $partnerId = (int) $partner->id;
            if ($partnerId <= 0) {
                continue;
            }

            $ordersQuery = Order::query()
                ->with(['customer', 'plan'])
                ->where('partner_id', $partnerId)
                ->whereIn('status', ['completed', 'active']);

            if (Schema::connection('pgsql_billing')->hasColumn('orders', 'order_type')) {
                $ordersQuery->where('order_type', 'b2b');
            }
            if (Schema::connection('pgsql_billing')->hasColumn('orders', 'order_category')) {
                $ordersQuery->where(function ($q) {
                    $q->whereNull('order_category')
                        ->orWhere('order_category', 'new')
                        ->orWhereNotIn('order_category', ['renewal', 'renewed']);
                });
            }

            $orders = $ordersQuery->orderByDesc('id')->get();

            foreach ($orders as $order) {
                $customer = $order->customer;
                if (!$customer) {
                    continue;
                }

                $startDate = !empty($order->subscription_start_date)
                    ? Carbon::parse($order->subscription_start_date)->startOfDay()
                    : ($order->created_at ? Carbon::parse($order->created_at)->startOfDay() : null);
                $endDate = !empty($order->subscription_end_date)
                    ? Carbon::parse($order->subscription_end_date)->startOfDay()
                    : ($startDate
                        ? $this->renewalService->subscriptionEndDate($startDate, $order->duration ?? '12 month')->startOfDay()
                        : null);
                if (!$endDate) {
                    continue;
                }

                $daysLeft = (int) $today->diffInDays($endDate, false);
                if ($endDate->lt($today)) {
                    $expiryStatus = 'Expired';
                } elseif ($endDate->lte($expiringSoonThreshold)) {
                    $expiryStatus = 'Expiring Soon';
                } else {
                    continue;
                }

                $domains = Order::query()
                    ->where('partner_id', $partnerId)
                    ->where('user_id', (int) $customer->id)
                    ->whereIn('status', ['completed', 'active', 'pending'])
                    ->when(Schema::connection('pgsql_billing')->hasColumn('orders', 'order_type'), fn ($q) => $q->where('order_type', 'b2b'))
                    ->whereNotNull('domain_name')
                    ->where('domain_name', '!=', '')
                    ->distinct()
                    ->orderBy('domain_name')
                    ->pluck('domain_name');

                $plan = $order->plan;
                $rows->push([
                    'partner_id' => $partnerId,
                    'partner_name' => (string) ($partner->name ?? ''),
                    'customer_name' => (string) ($customer->name ?? ''),
                    'email' => (string) ($customer->email ?: ($partner->email ?? '')),
                    'mobile' => (string) ($customer->mobile ?: ($partner->mobile ?? '')),
                    'domain' => $domains->isNotEmpty() ? $domains->implode(', ') : '—',
                    'plan_name' => $plan ? $plan->name : ($order->plan_name ?? '—'),
                    'subscription_start_date' => $startDate ? $startDate->format('Y-m-d') : '—',
                    'subscription_end_date' => $endDate->format('Y-m-d'),
                    'renewal_date_display' => $endDate->format('d M Y'),
                    'days_left' => $daysLeft,
                    'expiry_status' => $expiryStatus,
                    'view_url' => route('superadmin.renewals.partner', ['partner_id' => Crypt::encrypt($partnerId)]),
                    'renew_url' => route('superadmin.b2b_partners.customers.renew', [
                        'partner_id' => Crypt::encrypt($partnerId),
                        'customer_id' => Crypt::encrypt((int) $customer->id),
                    ]),
                ]);
            }
        }

        // Filters
        $filterPartner  = trim((string) $request->get('filter_partner', ''));
        $filterCustomer = trim((string) $request->get('filter_customer', ''));
        $filterDomain   = trim((string) $request->get('filter_domain', ''));
        $filterEmail    = trim((string) $request->get('filter_email', ''));
        $filterMobile   = trim((string) $request->get('filter_mobile', ''));
        $filterStatus   = trim((string) $request->get('filter_status', ''));

        $draw         = (int) $request->get('draw', 1);
        $start        = (int) $request->get('start', 0);
        $length       = (int) $request->get('length', 10);
        $globalSearch = strtolower(trim((string) data_get($request->get('search', []), 'value', '')));
        $order        = (array) $request->get('order', []);
        $columns      = (array) $request->get('columns', []);

        $totalRecords = $rows->count();
        $filtered = $rows->filter(function ($item) use (
            $filterPartner,
            $filterCustomer,
            $filterDomain,
            $filterEmail,
            $filterMobile,
            $filterStatus,
            $filterDaysLeftExact,
            $globalSearch
        ) {
            if ($filterPartner !== '' && stripos((string) $item['partner_name'], $filterPartner) === false) return false;
            if ($filterCustomer !== '' && stripos((string) $item['customer_name'], $filterCustomer) === false) return false;
            if ($filterDomain !== '' && stripos((string) $item['domain'], $filterDomain) === false) return false;
            if ($filterEmail !== '' && stripos((string) $item['email'], $filterEmail) === false) return false;
            if ($filterMobile !== '' && stripos((string) $item['mobile'], $filterMobile) === false) return false;
            if ($filterStatus !== '' && stripos((string) $item['expiry_status'], $filterStatus) === false) return false;
            if ($filterDaysLeftExact !== null) {
                $left = $item['days_left'];
                if ($left === null || (int) $left !== $filterDaysLeftExact) {
                    return false;
                }
            }

            if ($globalSearch !== '') {
                $blob = strtolower(implode(' ', [
                    (string) $item['partner_name'],
                    (string) $item['customer_name'],
                    (string) $item['plan_name'] ?? '',
                    (string) $item['email'],
                    (string) $item['mobile'],
                    (string) $item['domain'],
                    (string) $item['subscription_start_date'],
                    (string) $item['subscription_end_date'],
                    (string) $item['renewal_date_display'],
                    (string) $item['expiry_status'],
                    (string) $item['days_left'],
                ]));
                if (!str_contains($blob, $globalSearch)) return false;
            }
            return true;
        })->values();

        $recordsFiltered = $filtered->count();

        $sortable = [
            'partner_name', 'customer_name', 'plan_name', 'email', 'mobile', 'domain',
            'subscription_start_date', 'subscription_end_date', 'renewal_date_display', 'days_left', 'expiry_status',
        ];
        $orderIndex  = (int) data_get($order, '0.column', 7);
        $orderDir    = strtolower((string) data_get($order, '0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $orderColumn = (string) data_get($columns, $orderIndex . '.data', 'subscription_end_date');
        if (!in_array($orderColumn, $sortable, true)) {
            $orderColumn = 'subscription_end_date';
        }

        $sorted = $filtered->sortBy($orderColumn, SORT_NATURAL, $orderDir === 'desc')->values();
        $paged  = $length > 0 ? $sorted->slice($start, $length)->values() : $sorted;

        $data = $paged->map(function ($row) {
            return [
                'partner_name'             => $row['partner_name'],
                'customer_name'            => $row['customer_name'],
                'email'                    => $row['email'],
                'mobile'                   => $row['mobile'],
                'domain'                   => $row['domain'],
                'plan_name'                => $row['plan_name'],
                'subscription_start_date'  => $row['subscription_start_date'],
                'subscription_end_date'    => $row['subscription_end_date'],
                'renewal_date_display'     => $row['renewal_date_display'],
                'expiry_status'            => $row['expiry_status'],
                'days_left'                => $row['days_left'],
                'action' => '<div class="d-flex justify-content-end gap-1">'
                    // . '<a href="' . e($row['view_url']) . '" class="btn btn-light btn-icon btn-sm rounded-circle"><i class="ti ti-eye fs-lg"></i></a>'
                    . '<a href="' . e($row['renew_url']) . '" class="btn btn-light btn-icon btn-sm rounded-circle"><i class="ti ti-refresh fs-lg"></i></a>'
                    . '</div>',
            ];
        })->all();

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    public function renewalsByPartner($partner_id)
    {
        $partnerId = Crypt::decrypt($partner_id);
        $partner = $this->partner->findOrFail($partnerId);

        return view('superadmin.renewals.partner', [
            'title' => 'Renewal Center - Partner',
            'partner' => $partner,
            'encryptedPartnerId' => Crypt::encrypt($partner->id),
            'renewalsTableUrl' => route('superadmin.renewals.partner.table', ['partner_id' => $partner_id]),
        ]);
    }

    public function renewalsByPartnerTable(Request $request, $partner_id)
    {
        $partnerId = Crypt::decrypt($partner_id);
        $today = Carbon::now()->startOfDay();

        $rows = collect();
        $dueList = $this->renewalService->dueCustomersForPartner($partnerId);
        foreach ($dueList as $row) {
            $customer = $this->customer->find($row->id);
            if (!$customer) {
                continue;
            }

            $latest = $this->latestB2bOrder($partnerId, (int) $customer->id);
            $domainQuery = Order::query()
                ->where('partner_id', $partnerId)
                ->where('user_id', (int) $customer->id)
                ->whereIn('status', ['completed', 'active', 'pending']);
            if (Schema::hasColumn('orders', 'order_type')) {
                $domainQuery->where('order_type', 'b2b');
            }
            $domains = $domainQuery
                ->whereNotNull('domain_name')
                ->where('domain_name', '!=', '')
                ->distinct()
                ->orderBy('domain_name')
                ->pluck('domain_name')
                ->values();

            // Show only rows backed by order data.
            // If partner/customer has no order rows, skip from renewal table.
            if (!$latest && $domains->isEmpty()) {
                continue;
            }

            $domainList = $domains->implode(', ');
            $renewalDue = Carbon::parse($customer->created_at)->addYear()->endOfDay();

            $rows->push([
                'customer_name' => (string) ($customer->name ?? ''),
                'email' => (string) ($customer->email ?? ''),
                'domain' => $domainList !== '' ? $domainList : '—',
                'domain_count' => (int) $domains->count(),
                'plan' => (string) (($latest->plan_name ?? null) ?: (isset($latest->plan_id) ? ('Plan #' . $latest->plan_id) : '—')),
                'renewal_date' => $renewalDue->format('Y-m-d'),
                'renewal_date_display' => $renewalDue->format('d M Y'),
                'days_left' => (int) $today->diffInDays($renewalDue, false),
                'renew_url' => route('superadmin.b2b_partners.customers.renew', [
                    'partner_id' => Crypt::encrypt($partnerId),
                    'customer_id' => Crypt::encrypt($customer->id),
                ]),
            ]);
        }

        $filterName = trim((string) $request->get('filter_customer_name', ''));
        $filterDomain = trim((string) $request->get('filter_domain', ''));
        $filterDaysLeft = trim((string) $request->get('filter_days_left', ''));
        $filterRenewalDate = trim((string) $request->get('filter_renewal_date', ''));

        $draw = (int) $request->get('draw', 1);
        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 10);
        $globalSearch = strtolower(trim((string) data_get($request->get('search', []), 'value', '')));
        $order = (array) $request->get('order', []);
        $columns = (array) $request->get('columns', []);

        $totalRecords = $rows->count();

        $filtered = $rows->filter(function ($item) use ($filterName, $filterDomain, $filterDaysLeft, $filterRenewalDate, $globalSearch) {
            if ($filterName !== '' && stripos((string) $item['customer_name'], $filterName) === false) {
                return false;
            }
            if ($filterDomain !== '' && stripos((string) $item['domain'], $filterDomain) === false) {
                return false;
            }
            if ($filterDaysLeft !== '' && is_numeric($filterDaysLeft) && (int) $item['days_left'] !== (int) $filterDaysLeft) {
                return false;
            }
            if ($filterRenewalDate !== '' && (string) $item['renewal_date'] !== $filterRenewalDate) {
                return false;
            }
            if ($globalSearch !== '') {
                $blob = strtolower(implode(' ', [
                    (string) $item['customer_name'],
                    (string) $item['email'],
                    (string) $item['domain'],
                    (string) $item['plan'],
                    (string) $item['renewal_date_display'],
                    (string) $item['days_left'],
                ]));
                if (!str_contains($blob, $globalSearch)) {
                    return false;
                }
            }
            return true;
        })->values();

        $recordsFiltered = $filtered->count();

        $sortable = ['customer_name', 'email', 'domain', 'domain_count', 'plan', 'renewal_date', 'days_left'];
        $orderIndex = (int) data_get($order, '0.column', 0);
        $orderDir = strtolower((string) data_get($order, '0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $orderColumn = (string) data_get($columns, $orderIndex . '.data', 'renewal_date');
        if (!in_array($orderColumn, $sortable, true)) {
            $orderColumn = 'renewal_date';
        }

        $sorted = $filtered->sortBy($orderColumn, SORT_NATURAL, $orderDir === 'desc')->values();
        $paged = $length > 0 ? $sorted->slice($start, $length)->values() : $sorted;

        $data = $paged->map(function ($row) {
            return [
                'customer_name' => $row['customer_name'],
                'email' => $row['email'],
                'domain' => $row['domain'],
                'domain_count' => $row['domain_count'],
                'plan' => $row['plan'],
                'renewal_date_display' => $row['renewal_date_display'],
                'days_left' => $row['days_left'],
                'action' => '<a href="' . e($row['renew_url']) . '" class="btn btn-sm btn-primary">Renew</a>',
            ];
        })->all();

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    private function latestB2bOrder(int $partnerId, int $customerId): ?Order
    {
        if (!Schema::hasTable('orders')) {
            return null;
        }

        $query = Order::query()
            ->where('partner_id', $partnerId)
            ->where('user_id', $customerId)
            ->whereIn('status', ['completed', 'active', 'pending']);

        if (Schema::hasColumn('orders', 'order_type')) {
            $query->where('order_type', 'b2b');
        }

        // Renewal due should be based on the base subscription period.
        // If order_category exists, prefer only "new" rows as the renewal anchor.
        if (Schema::hasColumn('orders', 'order_category')) {
            $query->where(function ($q) {
                $q->whereNull('order_category')
                  ->orWhere('order_category', 'new');
            });
        }

        return $query->orderByDesc('created_at')->first();
    }

    private function buildRenewalTrends($partners, string $trend): array
    {
        $labels = [];
        $dueCounts = [];
        $doneCounts = [];

        $windows = [];
        if ($trend === 'year') {
            for ($i = 5; $i >= 0; $i--) {
                $start = Carbon::now()->subYears($i)->startOfYear();
                $end = Carbon::now()->subYears($i)->endOfYear();
                $windows[] = [$start, $end, $start->format('Y')];
            }
        } elseif ($trend === 'month') {
            for ($i = 5; $i >= 0; $i--) {
                $start = Carbon::now()->subMonths($i)->startOfMonth();
                $end = Carbon::now()->subMonths($i)->endOfMonth();
                $windows[] = [$start, $end, $start->format('M Y')];
            }
        } else {
            for ($i = 7; $i >= 0; $i--) {
                $start = Carbon::now()->subWeeks($i)->startOfWeek();
                $end = Carbon::now()->subWeeks($i)->endOfWeek();
                $windows[] = [$start, $end, $start->format('d M') . ' - ' . $end->format('d M')];
            }
        }

        foreach ($windows as [$start, $end, $label]) {
            $due = 0;
            foreach ($partners as $partner) {
                $customers = $this->customer->where('partner_id', $partner->id)->get(['id']);
                foreach ($customers as $customer) {
                    $latest = $this->latestB2bOrder((int) $partner->id, (int) $customer->id);
                    if (!$latest || empty($latest->created_at)) {
                        continue;
                    }

                    $orderStart = Carbon::parse($latest->created_at)->startOfDay();
                    $renewalDue = $this->renewalService->subscriptionEndDate($orderStart, $latest->duration)->endOfDay();
                    if ($renewalDue->between($start, $end)) {
                        $due++;
                    }
                }
            }

            $done = 0;
            $done = (int) Order::query()
                ->whereIn('order_category', ['renewal', 'upgrade'])
                ->whereBetween('created_at', [$start, $end])
                ->whereIn('status', ['completed', 'active'])
                ->count();

            $labels[] = $label;
            $dueCounts[] = $due;
            $doneCounts[] = $done;
        }

        return [$labels, $dueCounts, $doneCounts];
    }
}