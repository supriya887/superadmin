<?php
namespace App\Http\Controllers\Partner;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\PartnerAuthController as ApiPartnerAuthController;
use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\InvoiceMst;
use App\Models\Plan;
use App\Models\Variant;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class CustomerApi extends Controller
{
    public function customersStore(Request $request)
    {
        DB::beginTransaction();

        try {
            // Auth check first (needed for partner-wise uniqueness)
            $partner_id = (int) $request->partner_id;
            if ($partner_id <= 0) {
                DB::rollBack();

                return response()->json([
                    'error' => true,
                    'message' => 'Unauthorized. Please login again.'
                ], 401);
            }

            $validator = Validator::make(
                $request->all(),
                [
                    'name' => 'required|string|max:255',
                    'email' => [
                        'required',
                        'email',
                        'max:255',
                        Rule::unique('pgsql_billing.customers', 'email')
                            ->where(fn ($q) => $q->where('partner_id', $partner_id)),
                    ],
                    'mobile' => [
                        'required',
                        'digits:10',
                        Rule::unique('pgsql_billing.customers', 'mobile')
                            ->where(fn ($q) => $q->where('partner_id', $partner_id)),
                    ],
                    'company_name' => 'required|string|max:255',
                    'company_address' => 'required|string|max:500',
                    'gstin' => [
                        'required',
                        'string',
                        'size:15',
                        'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/',
                        Rule::unique('pgsql_billing.customers', 'gstin')
                            ->where(fn ($q) => $q->where('partner_id', $partner_id)),
                    ],
                    'country' => 'required|string',
                    'state' => 'required|string',
                    'city' => 'nullable|string',
                    'zip_code' => 'required|string',
                    'pan_no' => ['required','string','size:10','regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/'],
                ],
                [
                    'mobile.digits' => 'Mobile number must be exactly 10 digits.',
                    'mobile.unique' => 'Mobile number is already in use.',
                    'gstin.regex' => 'Please enter a valid GSTIN.',
                    'gstin.unique' => 'GSTIN is already in use for this partner.',
                    'pan_no.regex' => 'Please enter a valid PAN number.',
                    'email.unique' => 'Email address is already in use.',
                ]
            );

            // Validation fail response (same style as register)
            if ($validator->fails()) {
                $errors = $validator->errors();

                DB::rollBack();

                return response()->json([
                    'error' => true,
                    'name' => $errors->first('name'),
                    'email' => $errors->first('email'),
                    'mobile' => $errors->first('mobile'),
                    'company_name' => $errors->first('company_name'),
                    'company_address' => $errors->first('company_address'),
                    'gstin' => $errors->first('gstin'),
                    'country' => $errors->first('country'),
                    'state' => $errors->first('state'),
                    'city' => $errors->first('city'),
                    'zip_code' => $errors->first('zip_code'),
                    'pan_no' => $errors->first('pan_no'),
                ], 422);
            }

            // Generate customer_no
            // Try to generate a unique sequential customer_no per partner, fallback is global
            $lastCustomer = Customer::where('partner_id', $partner_id)
                ->orderByDesc('id')
                ->first();

            if ($lastCustomer && $lastCustomer->customer_no) {
                $lastNumber = intval(preg_replace('/\D/', '', $lastCustomer->customer_no)); // get only the numbers
                $customerNo = 'CUST' . ($lastNumber + 1);
            } else {
                // fallback if none set yet
                $startNumber = 10001;
                $customerNo = 'CUST' . $startNumber;
            }

            // If customer_no exists for this partner, increment till unique
            while (Customer::where('partner_id', $partner_id)->where('customer_no', $customerNo)->exists()) {
                $num = intval(preg_replace('/\D/', '', $customerNo)) + 1;
                $customerNo = 'CUST' . $num;
            }

            // Create customer
            $customer = Customer::create([
                'partner_id' => $partner_id,
                'customer_no' => $customerNo,
                'gstin' => strtoupper($request->gstin),
                'pan_no' => strtoupper($request->pan_no),
                'name' => $request->name,
                'email' => $request->email,
                'mobile' => $request->mobile,
                'company_name' => $request->company_name,
                'company_address' => $request->company_address,
                'country' => $request->country,
                'state' => $request->state,
                'city' => $request->city,
                'zip_code' => $request->zip_code,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Customer added successfully!',
                'data' => $customer
            ], 201);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'error' => true,
                'message' => 'Something went wrong',
                'debug' => $e->getMessage()
            ], 500);
        }
    }

    public function customersIndex(Request $request)
    {
        $partner_id = $request->partner_id;
        // status=active|inactive|closed (1|active, 0|inactive, anything else = closed)
        $status = strtolower(trim((string) $request->input('status', '')));
        $statusCol = DB::raw("LOWER(COALESCE(CAST(status AS TEXT), ''))");

        // search: name, email, mobile, company, customer no (case-insensitive)
        $search = trim((string) $request->input('search', ''));
        $like = '%' . addcslashes($search, '%_\\') . '%';

        $query = Customer::where('partner_id', $partner_id)
            ->when($status === 'active', fn ($q) => $q->whereIn($statusCol, ['1', 'active']))
            ->when($status === 'inactive', fn ($q) => $q->whereIn($statusCol, ['0', 'inactive']))
            ->when($status === 'closed', fn ($q) => $q->whereNotIn($statusCol, ['1', 'active', '0', 'inactive']))
            ->when($search !== '', fn ($q) => $q->where(function ($w) use ($like) {
                foreach (['name', 'email', 'mobile', 'company_name', 'customer_no'] as $col) {
                    $w->orWhereRaw("CAST({$col} AS TEXT) ILIKE ?", [$like]);
                }
            }))
            ->latest();

        // Paginate only when page_no / per_page is sent, so existing callers still get the full list
        if (!$request->filled('page_no') && !$request->filled('page') && !$request->filled('per_page')) {
            return response()->json([
                'success' => true,
                'message' => 'Customers fetched successfully',
                'data' => $query->get(),
            ]);
        }

        $perPage = $this->resolvePositiveInt($request->input('per_page'), 10, 1, 100);
        $page = $this->resolvePositiveInt($request->input('page_no', $request->input('page')), 1, 1, null);
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'message' => 'Customers fetched successfully',
            'data' => $paginator->items(),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    public function customersgetIndex(Request $request)
    {
        $partner_id = $request->query('partner_id', $request->input('partner_id'));

        if (!$partner_id) {
            return response()->json([
                'success' => false,
                'message' => 'partner_id is required'
            ], 422);
        }

        $perPage = $this->resolvePositiveInt(
            $request->query('per_page', $request->input('per_page')),
            10,
            1,
            100
        );
        // Accept page / page_no; treat missing / "undefined" / invalid as page 1
        $page = $this->resolvePositiveInt(
            $request->query('page_no', $request->input('page_no', $request->query('page', $request->input('page')))),
            1,
            1,
            null
        );

        $baseQuery = Customer::query()->where('partner_id', $partner_id);

        // Counts across all customers (not just current page)
        $allStatuses = (clone $baseQuery)->pluck('status');
        $activeCount = 0;
        $inactiveCount = 0;
        $closedCount = 0;
        foreach ($allStatuses as $rawStatus) {
            $status = strtolower((string) $rawStatus);
            if ($status == '1' || $status == 'active') {
                $activeCount++;
            } elseif ($status == '0' || $status == 'inactive') {
                $inactiveCount++;
            } else {
                $closedCount++;
            }
        }

        // status=active|inactive|closed filters the list only; counts above stay for all customers
        $statusFilter = strtolower(trim((string) $request->input('status', '')));
        $statusCol = DB::raw("LOWER(COALESCE(CAST(status AS TEXT), ''))");

        // search: name, email, mobile, company, customer no (case-insensitive)
        $search = trim((string) $request->input('search', ''));
        $like = '%' . addcslashes($search, '%_\\') . '%';

        $paginator = (clone $baseQuery)
            ->when($statusFilter === 'active', fn ($q) => $q->whereIn($statusCol, ['1', 'active']))
            ->when($statusFilter === 'inactive', fn ($q) => $q->whereIn($statusCol, ['0', 'inactive']))
            ->when($statusFilter === 'closed', fn ($q) => $q->whereNotIn($statusCol, ['1', 'active', '0', 'inactive']))
            ->when($search !== '', fn ($q) => $q->where(function ($w) use ($like) {
                foreach (['name', 'email', 'mobile', 'company_name', 'customer_no'] as $col) {
                    $w->orWhereRaw("CAST({$col} AS TEXT) ILIKE ?", [$like]);
                }
            }))
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page);

        $result = [];
        foreach ($paginator->items() as $customer) {
            $status = strtolower((string) $customer->status);

            if ($status == '1' || $status == 'active') {
                $statusText = 'active';
            } elseif ($status == '0' || $status == 'inactive') {
                $statusText = 'inactive';
            } else {
                $statusText = 'closed';
            }

            $planIds = \App\Models\Order::where('user_id', $customer->id)
                ->pluck('plan_id')
                ->filter()
                ->unique()
                ->toArray();

            $providerIds = [];
            if (!empty($planIds)) {
                $providerIds = \App\Models\Plan::whereIn('id', $planIds)
                    ->pluck('provider_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();
            }

            $result[] = [
                'id'      => $customer->id,
                'customer_no' => $customer->customer_no,
                'name'    => $customer->name ?? '-',
                'email'   => $customer->email ?? '-',
                'mobile'  => $customer->mobile ?? '-',
                'company' => $customer->company_name ?? '-',
                'status'  => $statusText,
                'created_date' => optional($customer->created_at)->format('d-m-Y'),
                'provider_ids' => $providerIds,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Customers fetched successfully',
            'data' => [
                'count' => [
                    [
                        "key" => "Active Customers",
                        "value" => $activeCount
                    ],
                    [
                        "key" => "Inactive Customers",
                        "value" => $inactiveCount
                    ],
                    [
                        "key" => "Closed Customers",
                        "value" => $closedCount
                    ]
                ],
                'customers' => $result,
                'pagination' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'page_no' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ]
        ]);
    }

    // public function customersEditPost(Request $request)
    // {
    //     $customer = Customer::where('id', $request->customer_id)->first();

    //     if (!$customer) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Customer not found',
    //             'data' => null
    //         ], 404);
    //     }

    //     // ✅ ALL TRANSACTIONS (including pending)
    //     $ordersRaw = Order::where('user_id', $customer->id)
    //         ->orderBy('id', 'desc')
    //         ->with('plan')  // Eager load the plan relationship
    //         ->get();

    //     $transactions = $ordersRaw->map(function ($order) {
    //         return [
    //             'order_id'      => $order->id,
    //             'order_no'      => $order->order_no,
    //             'order_name'    => $order->plan ? $order->plan->name : $order->order_no,  // Access plan_name via the plan relationship
    //             'domain_name'   => $order->domain_name,
    //             'status'        => $order->status,
    //             'price'         => (float) $order->price,
    //             'quantity'      => (int) $order->quantity,
    //             'total_amount'  => (float) $order->total_amount,
    //             'created_at'    => $order->created_at
    //                 ? $order->created_at->format('d-m-Y H:i:s')
    //                 : null,
    //         ];
    //     });

    //     // ✅ CURRENT PLANS (ONLY COMPLETED)
    //     $currentPlansRaw = Order::where('user_id', $customer->id)
    //         ->where('status', 'completed')
    //         ->orderBy('id', 'desc')
    //         ->with('plan')  // Eager load the plan relationship
    //         ->get();

    //     $currentPlans = $currentPlansRaw->map(function ($order) {
    //         return [
    //             'order_id'    => $order->id,
    //             'plan_name'   => $order->plan ? $order->plan->name : $order->order_no,  // Access plan_name via the plan relationship
    //             'domain_name' => $order->domain_name,
    //             'price'       => (float) $order->price,
    //             'start_date'  => $order->created_at
    //                 ? $order->created_at->format('d-m-Y')
    //                 : null,
    //         ];
    //     });

    //     // ✅ FINAL RESPONSE STRUCTURE
    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Customer details fetched successfully.',

    //         'data' => [
    //             'customer' => [
    //                 'id'                  => $customer->id,
    //                 'partner_id'          => $customer->partner_id,
    //                 'gstin'               => $customer->gstin,
    //                 'name'                => $customer->name,
    //                 'email'               => $customer->email,
    //                 'mobile'              => $customer->mobile,
    //                 'company_name'        => $customer->company_name,
    //                 'company_address'     => $customer->company_address,
    //                 'country'             => $customer->country,
    //                 'state'               => $customer->state,
    //                 'city'                => $customer->city,
    //                 'zip_code'            => $customer->zip_code,
    //                 'postcode'            => $customer->postcode,
    //                 'pincode'             => $customer->pincode,
    //                 'pan_no'              => $customer->pan_no,
    //                 'legal_name'          => $customer->legal_name,
    //                 'trade_name'          => $customer->trade_name,
    //                 'address'             => $customer->address,
    //                 'gst_status'          => $customer->gst_status,
    //                 'last_updated'        => $customer->last_updated,
    //                 'constitution'        => $customer->constitution,
    //                 'registration_date'   => $customer->registration_date,
    //                 'nature_of_business'  => $customer->nature_of_business,
    //                 'status'              => $customer->status,
    //                 'created_at'          => $customer->created_at,
    //                 'updated_at'          => $customer->updated_at,
    //             ],

    //             'transaction_history' => $transactions,
    //             'current_plans' => $currentPlans,

    //             'summary' => [
    //                 'total_orders' => $ordersRaw->count(),
    //                 'total_spent'  => $ordersRaw->sum('total_amount'),
    //                 'active_plans' => $currentPlansRaw->count(),
    //             ]
    //         ]
    //     ]);
    // }
    public function customersEditPost(Request $request)
    {
        $customerId = $request->input('customer_id');
        $partnerId = $request->input('partner_id');

        if (!$customerId) {
            return response()->json([
                'success' => false,
                'message' => 'customer_id is required in request body',
                'data' => null
            ], 422);
        }

        if (!$partnerId) {
            return response()->json([
                'success' => false,
                'message' => 'partner_id is required in request body',
                'data' => null
            ], 422);
        }

        $customer = Customer::where('id', $customerId)
            ->where('partner_id', $partnerId)
            ->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found for the provided partner',
                'data' => null
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | ALL TRANSACTIONS
        |--------------------------------------------------------------------------
        */
        // Exclude draft orders
        $ordersRaw = Order::where('user_id', $customer->id)
            ->where('status', '!=', 'draft')
            ->with(['plan.provider'])
            ->orderBy('id', 'desc')
            ->get();

        $transactions = $ordersRaw->map(function ($order) {
            $plan = $order->plan;
            $provider = optional(optional($plan)->provider);

            // Determine amount: prefer order->total_amount, else price * quantity, else price
            $amount = (float)$order->total_amount;

            return [
                'order_id'      => $order->id,
                'order_no'      => $order->order_no,

                'plan_id'       => $plan->id ?? null,
                'order_name'    => $plan->name ?? $order->order_no,

                'domain_name'   => $order->domain_name,
                'status'        => $order->status,

                'price'         => (float)$order->price,
                'quantity'      => (int)$order->quantity,
                'total_amount'  => $amount,

                'created_at'    => $order->created_at
                    ? $order->created_at->format('d-m-Y H:i:s')
                    : null,

                'provider_id'   => $provider->id ?? null,
                'provider_name' => $provider->name ?? null,
            ];
        });

        $orderIds = $ordersRaw->pluck('id')->map(fn ($id) => (int) $id)->all();
        $invoices = InvoiceMst::query()
            ->where(function ($query) use ($customer, $orderIds) {
                $query->where('cust_id', $customer->id);
                if ($orderIds !== []) {
                    $query->orWhereIn('order_id', $orderIds);
                }
            })
            ->orderByDesc('id')
            ->get()
            ->map(function ($invoice) {
                return [
                    'invoice_id' => (int) $invoice->id,
                    'invoice_no' => $invoice->bill_no_full,
                    'invoice_date' => $invoice->bill_date
                        ? $invoice->bill_date->format('d-m-Y')
                        : null,
                    'order_id' => $invoice->order_id ? (int) $invoice->order_id : null,
                    'amount' => round((float) ($invoice->net_amt ?? 0), 2),
                    'taxable_amount' => round((float) ($invoice->taxable_total ?? 0), 2),
                    'cgst' => round((float) ($invoice->cgst_total ?? 0), 2),
                    'sgst' => round((float) ($invoice->sgst_total ?? 0), 2),
                    'status' => $invoice->status,
                ];
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | CURRENT PLANS (FIXED: Show only active/not expired)
        |--------------------------------------------------------------------------
        */
        // Only show plans where end date is null or in future (and status completed), and don't show draft status
        $currentPlansRaw = Order::where('user_id', $customer->id)
            ->where('status', '!=', 'draft')
            ->where(function($q) {
                $q->whereNull('subscription_end_date')
                  ->orWhere('subscription_end_date', '>=', \Carbon\Carbon::now());
            })
            ->with(['plan.provider'])
            ->orderBy('id', 'desc')
            ->get();

        $currentPlans = $currentPlansRaw->map(function ($order) {
            $plan = $order->plan;
            $provider = optional(optional($plan)->provider);

            $startDate = $order->subscription_start_date ?? $order->created_at;
            $endDate   = $order->subscription_end_date;

            $start = $startDate ? \Carbon\Carbon::parse($startDate) : null;
            $end   = $endDate ? \Carbon\Carbon::parse($endDate) : null;
            $today = \Carbon\Carbon::now();

            $daysLeft = $end ? $today->diffInDays($end, false) : null;

            // Show only those orders as current where daysLeft is null (no end date) or non-negative (not expired)
            if (!is_null($daysLeft) && $daysLeft < 0) {
                return null; // filter out
            }

            // Status logic
            if (!$end) {
                $status = 'Active';
            } elseif ($daysLeft < 0) {
                $status = 'Expired';
            } elseif ($daysLeft <= 10) {
                $status = 'Critical';
            } elseif ($daysLeft <= 30) {
                $status = 'Expiring Soon';
            } else {
                $status = 'Active';
            }

            // Show correct amount
            if (!is_null($order->total_amount) && $order->total_amount !== '') {
                $amount = (float)$order->total_amount;
            } elseif (!is_null($order->price) && !is_null($order->quantity)) {
                $amount = (float)$order->price * (int)$order->quantity;
            } elseif (!is_null($order->price)) {
                $amount = (float)$order->price;
            } else {
                $amount = 0.0;
            }

            return [
                'order_id'        => $order->id,
                'plan_id'         => $plan->id ?? null,
                'plan_name'       => $plan->name ?? $order->order_no,

                'domain_name'     => $order->domain_name,
                'price'           => (float) $order->price,
                'total_amount'    => $amount,

                'license_count'   => (int) $order->quantity,

                'status'          => $status,
                'days_left'       => $daysLeft,
                'provider_id'     => $provider->id ?? null,
                'provider_name'   => $provider->name ?? null,
                'start_date'      => $start ? $start->format('d-m-Y') : null,
                'end_date'        => $end ? $end->format('d-m-Y') : null,
            ];
        })->filter()->values();

        return response()->json([
            'success' => true,
            'message' => 'Customer details fetched successfully',
            'data' => [
                'customer' => [
                    'id'                => $customer->id,
                    'partner_id'        => $customer->partner_id,
                    'customer_no'       => $customer->customer_no,
                    'gstin'             => $customer->gstin,
                    'name'              => $customer->name,
                    'email'             => $customer->email,
                    'mobile'            => $customer->mobile,
                    'company_name'      => $customer->company_name,
                    'company_address'   => $customer->company_address,
                    'country'           => $customer->country,
                    'state'             => $customer->state,
                    'city'              => $customer->city,
                    'zip_code'          => $customer->zip_code,
                    'postcode'          => $customer->postcode,
                    'pincode'           => $customer->pincode,
                    'pan_no'            => $customer->pan_no,
                    'legal_name'        => $customer->legal_name,
                    'trade_name'        => $customer->trade_name,
                    'address'           => $customer->address,
                    'gst_status'        => $customer->gst_status,
                    'last_updated'      => $customer->last_updated,
                    'constitution'      => $customer->constitution,
                    'registration_date' => $customer->registration_date,
                    'nature_of_business'=> $customer->nature_of_business,
                    'status'            => $customer->status,
                    'created_at'        => $customer->created_at,
                    'updated_at'        => $customer->updated_at,
                    'invoice'           => $invoices,
                ],

                'transaction_history' => $transactions,
                'current_plans'       => $currentPlans,
                'invoice'             => $invoices,

                'summary' => [
                    'total_orders' => $ordersRaw->count(),
                    'total_spent'  => $ordersRaw->sum('total_amount'),
                    'active_plans' => $currentPlans->count(),
                ]
            ]
        ]);
    }
    
    // public function customersEditGet(Request $request)
    // {
    //     $customerId = $request->query('customer_id');
    
    //     if (!$customerId) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'customer_id is required',
    //             'data' => null
    //         ], 422);
    //     }
    
    //     $customer = Customer::find($customerId);
    
    //     if (!$customer) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Customer not found',
    //             'data' => null
    //         ], 404);
    //     }
    
    //     // ALL TRANSACTIONS
    //     $ordersRaw = Order::where('user_id', $customer->id)
    //         ->orderBy('id', 'desc')
    //         ->with('plan')
    //         ->get();
    
    //     $transactions = $ordersRaw->map(function ($order) {
    //         return [
    //             'order_id'     => $order->id,
    //             'order_no'     => $order->order_no,
    //             'order_name'   => $order->plan->name ?? $order->order_no,
    //             'domain_name'  => $order->domain_name,
    //             'status'       => $order->status,
    //             'price'        => (float) $order->price,
    //             'quantity'     => (int) $order->quantity,
    //             'total_amount' => (float) $order->total_amount,
    //             'created_at'   => optional($order->created_at)->format('d-m-Y H:i:s'),
    //         ];
    //     });
    
    //     // CURRENT PLANS (ONLY COMPLETED)
    //     $currentPlansRaw = Order::where('user_id', $customer->id)
    //         ->where('status', 'completed')
    //         ->orderBy('id', 'desc')
    //         ->with('plan')
    //         ->get();
    
    //     $currentPlans = $currentPlansRaw->map(function ($order) {
    //         return [
    //             'order_id'    => $order->id,
    //             'plan_name'   => $order->plan->name ?? $order->order_no,
    //             'domain_name' => $order->domain_name,
    //             'price'       => (float) $order->price,
    //             'start_date'  => optional($order->created_at)->format('d-m-y'),
    //         ];
    //     });
    
    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Customer details fetched successfully',
    
    //         'data' => [
    //             'customer' => [
    //                 'id'                => $customer->id,
    //                 'partner_id'        => $customer->partner_id,
    //                 'gstin'             => $customer->gstin,
    //                 'name'              => $customer->name,
    //                 'email'             => $customer->email,
    //                 'mobile'            => $customer->mobile,
    //                 'company_name'      => $customer->company_name,
    //                 'company_address'   => $customer->company_address,
    //                 'country'           => $customer->country,
    //                 'state'             => $customer->state,
    //                 'city'              => $customer->city,
    //                 'zip_code'          => $customer->zip_code,
    //                 'postcode'          => $customer->postcode,
    //                 'pincode'           => $customer->pincode,
    //                 'pan_no'            => $customer->pan_no,
    //                 'legal_name'        => $customer->legal_name,
    //                 'trade_name'        => $customer->trade_name,
    //                 'address'           => $customer->address,
    //                 'gst_status'        => $customer->gst_status,
    //                 'last_updated'      => $customer->last_updated,
    //                 'constitution'      => $customer->constitution,
    //                 'registration_date' => $customer->registration_date,
    //                 'nature_of_business'=> $customer->nature_of_business,
    //                 'status'            => $customer->status,
    //                 'created_at'        => $customer->created_at,
    //                 'updated_at'        => $customer->updated_at,
    //             ],
    
    //             'transaction_history' => $transactions,
    //             'current_plans' => $currentPlans,
    
    //             'summary' => [
    //                 'total_orders' => $ordersRaw->count(),
    //                 'total_spent'   => $ordersRaw->sum('total_amount'),
    //                 'active_plans'  => $currentPlansRaw->count(),
    //             ]
    //         ]
    //     ]);
    // }
    public function customersEditGet(Request $request)
    {
        $customerId = $request->input('customer_id');
        $partnerId  = $request->input('partner_id');
    
        if (!$customerId) {
            return response()->json([
                'success' => false,
                'message' => 'customer_id is required',
                'data' => null
            ], 422);
        }
    
        if (!$partnerId) {
            return response()->json([
                'success' => false,
                'message' => 'partner_id is required',
                'data' => null
            ], 422);
        }
    
        $customer = Customer::where('id', $customerId)
            ->where('partner_id', $partnerId)
            ->first();
    
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found',
                'data' => null
            ], 404);
        }
    
        /*
        |--------------------------------------------------------------------------
        | SAFE PRICE FUNCTION
        |--------------------------------------------------------------------------
        */
        $getPrice = function ($item) {
            return (float) (
                $item->total_amount ??
                $item->subtotal ??
                $item->price ??
                0
            );
        };
    
        /*
        |--------------------------------------------------------------------------
        | TRANSACTIONS
        |--------------------------------------------------------------------------
        */
        // Exclude draft status orders
        $ordersRaw = Order::where('user_id', $customer->id)
            ->where('status', '!=', 'draft')
            ->with(['plan.provider'])
            ->orderBy('id', 'desc')
            ->get();
    
        $transactions = $ordersRaw->map(function ($order) use ($getPrice) {
            $plan = optional($order->plan);
            $provider = optional($plan->provider);
    
            return [
                'order_id'      => $order->id,
                'order_no'      => $order->order_no,
                'plan_id'       => $plan->id ?? null,
                'order_name'    => $plan->name ?? $order->order_no,
                'domain_name'   => $order->domain_name,
                'status'        => $order->status,
                'price'         => $getPrice($order),
                'quantity'      => (int) ($order->quantity ?? 0),
                'created_at'    => date('d-m-Y', strtotime($order->created_at)),
                'provider_id'   => $provider->id ?? null,
                'provider_name' => $provider->name ?? null,
            ];
        });

        /*
        |--------------------------------------------------------------------------
        | CURRENT PLANS (GROUPED BY DOMAIN) — Use order_items instead of orders for plans
        |--------------------------------------------------------------------------
        */
        $currentPlansRaw = Order::where('user_id', $customer->id)
            ->where('status', 'completed')
            ->orderBy('id', 'desc')
            ->get();

        $grouped = [];

        // Separate DB query to order_items for all current orders
        $orderIds = $currentPlansRaw->pluck('id')->toArray();
        $orderItems = OrderItem::whereIn('order_id', $orderIds)
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereRaw("LOWER(TRIM(COALESCE(status, ''))) NOT IN ('delete','deleted','cancelled','canceled','upgraded','downgraded','renewed')");
            })
            ->get();
        $orderItems = $this->keepLatestLivePlanItems($orderItems, $currentPlansRaw);

        // For related plans and providers
        $planIds = $orderItems->pluck('plan_id')->unique()->filter()->toArray();
        $plans = \App\Models\Plan::whereIn('id', $planIds)->with('provider')->get()->keyBy('id');

        foreach ($currentPlansRaw as $order) {
            $domain = $order->domain_name ?? 'unknown';

            if (!isset($grouped[$domain])) {
                $grouped[$domain] = [
                    'domain_name' => $domain,
                    'plans' => []
                ];
            }

            // For every order, get its relevant items
            $items = $orderItems->where('order_id', $order->id);

            foreach ($items as $item) {
                $plan = $plans[$item->plan_id] ?? null;
                $provider = optional($plan)->provider;

                $startDate = $item->subscription_start_date ?? $order->subscription_start_date ?? $order->created_at;
                $endDate   = $item->subscription_end_date   ?? $order->subscription_end_date;

                $start = $startDate ? \Carbon\Carbon::parse($startDate) : null;
                $end   = $endDate ? \Carbon\Carbon::parse($endDate) : null;
                $today = \Carbon\Carbon::now();
                $daysLeft = $end ? $today->diffInDays($end, false) : 0;

                // STATUS LOGIC
                if($order->status == 'completed'){
                    if ($daysLeft <= 30) {
                        $status = 'Expiring';
                    } else {
                        $status = 'Active';
                    }
                }elseif($order->status == 'pending'){
                    $status = 'Pending';
                }elseif($order->status == 'rejected'){
                    $status = 'Cancelled';
                }elseif($order->status == 'draft'){
                    // Explicitly skip plans for draft orders from "current_plans"
                    continue;
                }else{
                    $status = 'Pending';
                }
               
                $grouped[$domain]['plans'][] = [
                    'order_sub_id'  => $item->id ?? null,
                    'subscription_id' => (string) ($item->subscription_id ?? ''),
                    'subscription_no' => (string) ($item->subscription_id ?? ''),
                    'order_id'      => $order->id,
                    'plan_id'       => $item->plan_id ?? null,
                    'plan_name'     => $plan ? $plan->name : 'Plan',
                    'price'         => $getPrice($item),
                    'quantity'      => (int) ($item->quantity ?? 0),
                    'license_count' => (int) ($item->license_count ?? $item->quantity ?? 0),
                    'status'        => $status,
                    'days_left'     => $daysLeft,
                    'provider_id'   => $provider->id ?? null,
                    'provider_name' => $provider->name ?? null,
                    'start_date'    => $start ? $start->format('d-m-Y') : null,
                    'end_date'      => $end ? $end->format('d-m-Y') : null,
                ];
            }
        }

        $currentPlans = array_values(array_filter(
            $grouped,
            static fn ($row) => !empty($row['plans'])
        ));

        $customerOrderIds = $ordersRaw->pluck('id')->map(fn ($id) => (int) $id)->all();
        $invoices = InvoiceMst::query()
            ->where(function ($query) use ($customer, $customerOrderIds) {
                $query->where('cust_id', $customer->id);
                if ($customerOrderIds !== []) {
                    $query->orWhereIn('order_id', $customerOrderIds);
                }
            })
            ->orderByDesc('id')
            ->get()
            ->map(function ($invoice) {
                $order = Order::where('id', $invoice->order_id)->first();
                $orderitem = OrderItem::where('order_id', $invoice->order_id)->first();
                $plan = Plan::where('id', $orderitem->plan_id)->first();
                $domain_name = $order->domain_name;
                $plan_name = $plan->name;
                return [
                    'invoice_id' => (int) $invoice->id,
                    'invoice_no' => $invoice->bill_no_full,
                    'invoice_date' => $invoice->bill_date
                        ? $invoice->bill_date->format('d-m-Y')
                        : null,
                    'order_id' => $invoice->order_id,
                    'amount' => round((float) ($invoice->net_amt ?? 0), 2),
                    'taxable_amount' => round((float) ($invoice->taxable_total ?? 0), 2),
                    'cgst' => round((float) ($invoice->cgst_total ?? 0), 2),
                    'sgst' => round((float) ($invoice->sgst_total ?? 0), 2),
                    'status' => $invoice->status,
                    'domain_name' => $domain_name,
                    'plan_name' => $plan_name,
                    'invoice_pdf_url' => $invoice->invoice_pdf_url,
                ];
            })
            ->values();

        // Same renewal rows as POST /api/v1/partner/renewals — count for this customer only.
        $renewalCount = 0;
        try {
            $renewalPayload = app(ApiPartnerAuthController::class)
                ->renewalsindex(new Request([
                    'partner_id' => $partnerId,
                    'days' => $request->input('days', 30),
                ]))
                ->getData(true);
            $renewalOrderIds = collect($renewalPayload['data'] ?? [])
                ->pluck('order_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->values()
                ->all();
            if ($renewalOrderIds !== []) {
                $renewalCount = (int) Order::query()
                    ->where('user_id', $customer->id)
                    ->whereIn('id', $renewalOrderIds)
                    ->count();
            }
        } catch (\Throwable $e) {
            $renewalCount = 0;
        }

        if($customer->status == 1){
            $status = 'Active';
        }else{
            $status = 'Inactive';
        }
    
        // Compose response
        return response()->json([
            'success' => true,
            'message' => 'Customer details fetched successfully',
            'data' => [
                'customer' => [
                    'id' => $customer->id,
                    'partner_id' => $customer->partner_id,
                    'customer_no' => $customer->customer_no,
                    'gstin' => $customer->gstin,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'mobile' => $customer->mobile,
                    'company_name' => $customer->company_name,
                    'company_address' => $customer->company_address,
                    'country' => $customer->country,
                    'state' => $customer->state,
                    'city' => $customer->city,
                    'zip_code' => $customer->zip_code,
                    'postcode' => $customer->postcode,
                    'pincode' => $customer->pincode,
                    'pan_no' => $customer->pan_no,
                    'legal_name' => $customer->legal_name,
                    'trade_name' => $customer->trade_name,
                    'address' => $customer->address,
                    'gst_status' => $customer->gst_status,
                    'last_updated' => $customer->last_updated,
                    'constitution' => $customer->constitution,
                    'registration_date' => $customer->registration_date,
                    'nature_of_business' => $customer->nature_of_business,
                    'status' => $status,
                    'created_at' => date('d-m-Y', strtotime($customer->created_at)),
                    // 'updated_at' => $updatedAtFormatted,
                ],
                'transaction_history' => $transactions,
                'current_plans'       => $currentPlans,
                'invoice'             => $invoices,
                'summary' => [
                    'total_subscriptions' => collect($currentPlans)->sum(fn ($row) => count($row['plans'] ?? [])),
                    'total_orders' => $ordersRaw->count(),
                    'total_spent'   => $ordersRaw->sum('total_amount'),
                    'renewal_count' => $renewalCount,
                    'active_plans'  => collect($currentPlans)->sum(fn ($row) => count($row['plans'] ?? [])),
                ]
            ]
        ]);
    }
    public function customersUpdate(Request $request)
    {
        $customerIdPlain = (int) ($request->customer_id ?? 0);
        if ($customerIdPlain <= 0) {
            return response()->json([
                'error' => true,
                'message' => 'customer_id is required',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:pgsql_billing.customers,email,' . $customerIdPlain,
            'mobile' => 'required|digits:10|unique:pgsql_billing.customers,mobile,' . $customerIdPlain,
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            return response()->json([
                'error'  => true,
                'name'   => $errors->first('name'),
                'email'  => $errors->first('email'),
                'mobile' => $errors->first('mobile'),
            ]);
        }

        $customer = Customer::find($customerIdPlain);
        if (!$customer) {
            return response()->json([
                'error' => true,
                'message' => 'Customer not found',
            ], 404);
        }

        $customer->update([
            'name'   => $request->name,
            'email'  => $request->email,
            'mobile' => $request->mobile,
        ]);

        return response()->json([
            'success' => 'Customer updated successfully.'
        ]);
    }

    public function customerOrder(Request $request)
    {
        // 1. Get customer_id from query
        $customer_id = $request->customer_id;
    
        if (!$customer_id) {
            return response()->json([
                'success' => false,
                'message' => 'customer_id is required'
            ], 400);
        }
    
        // 2. Get customer
        $customer = Customer::find($customer_id);
    
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);
        }
    
        // 3. Get orders
        $ordersRaw = Order::where('user_id', $customer_id)
            ->orderBy('id', 'desc')
            ->get();
    
        // 4. Transform orders
        $orders = $ordersRaw->map(function ($order) {
            return [
                'order_id'      => $order->id,
                'order_no'      => $order->order_no,
                'order_name'    => $order->plan_name ?? $order->order_no,
                'domain_name'   => $order->domain_name,
                'status'        => $order->status,
                'price'         => (float) $order->price,
                'quantity'      => (int) $order->quantity,
                'total_amount'  => (float) $order->total_amount,
                'created_at'    => $order->created_at
                    ? $order->created_at->format('d-m-y H:i:s')
                    : null,
            ];
        });
    
        return response()->json([
            'success' => true,
            'customer' => [
                'id'              => $customer->id,
                'customer_no'     => $customer->customer_no,
                'name'            => $customer->name,
                'email'           => $customer->email,
                'mobile'          => $customer->mobile,
                'company_name'    => $customer->company_name,
                'company_address' => $customer->company_address,
                'gstin'           => $customer->gstin,
                'pan_no'          => $customer->pan_no,
            ],
            'orders' => $orders,
            'summary' => [
                'total_orders' => $ordersRaw->count(),
                'total_spent'  => $ordersRaw->sum('total_amount'),
                'last_order'   => optional($ordersRaw->first())->order_no,
            ]
        ]);
    }

    /**
     * Current subscriptions: one live line per domain + plan.
     * Same-plan license upgrades (3→4) keep the new line and hide the old one.
     * Different plans on the same domain (Silver leftover + Platinum) both stay.
     */
    private function keepLatestLivePlanItems($orderItems, $orders)
    {
        $ordersById = $orders->keyBy('id');
        $keepIds = [];

        $orderItems->groupBy(function ($item) use ($ordersById) {
            $order = $ordersById->get((int) ($item->order_id ?? 0));
            $domain = strtolower(trim((string) ($order->domain_name ?? '')));
            $planId = (int) ($item->plan_id ?? 0);

            return $domain . '#' . $planId;
        })->each(function ($rows) use (&$keepIds) {
            $latest = $rows->sortByDesc(function ($item) {
                return ((int) ($item->order_id ?? 0) * 1000000) + (int) ($item->id ?? 0);
            })->first();
            if ($latest) {
                $keepIds[(int) $latest->id] = true;
            }
        });

        return $orderItems->filter(fn ($item) => isset($keepIds[(int) ($item->id ?? 0)]))->values();
    }

    public function allCustomersDetails(Request $request)
    {
        $partnerId      = $request->input('partner_id');
        $customerId     = $request->input('customer_id');
        $planId         = $request->input('plan_id'); // Optional
        $variantId      = $request->input('variant_id'); // Optional
        $subVariantId   = $request->input('sub_variant_id'); // Optional
        $categoryId     = $request->input('category_id'); // Optional

        if (!$partnerId) {
            return response()->json([
                'success' => false,
                'message' => 'partner_id is required',
                'data' => null
            ], 422);
        }

        if (!$customerId) {
            return response()->json([
                'success' => false,
                'message' => 'customer_id is required',
                'data' => null
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Get Customer of Partner (customer-wise)
        |--------------------------------------------------------------------------
        */

        $customer = Customer::where('partner_id', $partnerId)
            ->where('id', (int) $customerId)
            ->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found for this partner',
                'data' => null
            ], 404);
        }

        $customers = collect([$customer]);

        /*
        |--------------------------------------------------------------------------
        | Customer IDs
        |--------------------------------------------------------------------------
        */

        $customerIds = [(int) $customer->id];

        /*
        |--------------------------------------------------------------------------
        | Get Completed Orders
        | Only completed orders are considered subscriptions
        |--------------------------------------------------------------------------
        */

        $ordersQuery = Order::whereIn('user_id', $customerIds)
            ->where('status', 'completed');

        $orders = $ordersQuery
            ->orderBy('id', 'desc')
            ->get();

        $orderIds = $orders->pluck('id')->toArray();

        /*
        |--------------------------------------------------------------------------
        | Get Live Order Items
        |--------------------------------------------------------------------------
        */

        $orderItems = collect();

        if (!empty($orderIds)) {

            $orderItems = OrderItem::whereIn('order_id', $orderIds)
                ->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhereRaw("
                            LOWER(TRIM(COALESCE(status, ''))) NOT IN (
                                'delete',
                                'deleted',
                                'cancelled',
                                'canceled',
                                'upgraded',
                                'downgraded',
                                'renewed'
                            )
                        ");
                })
                ->whereNotNull('plan_id')
                ->get();

            /*
            |--------------------------------------------------------------------------
            | Keep Latest Live Plan
            |--------------------------------------------------------------------------
            */

            $orderItems = $this->keepLatestLivePlanItems(
                $orderItems,
                $orders
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Optional Filtering - Plan, Variant, Sub Variant, Category
        |--------------------------------------------------------------------------
        */
        if ($planId || $variantId || $subVariantId || $categoryId) {
            $orderItems = $orderItems->filter(function($item) use ($planId, $variantId, $subVariantId, $categoryId) {
                // For category: Need to check ->plan->category_id so later after $plan loaded
                $match = true;
                if ($planId && (int)$item->plan_id !== (int)$planId) {
                    $match = false;
                }
                if ($variantId) {
                    // $item->variant_id may not exist, so use plan relation later
                    $planVariantId = isset($item->plan) ? $item->plan->variant_id : ($item->variant_id ?? null);
                    if ((int)$planVariantId !== (int)$variantId) {
                        $match = false;
                    }
                }
                if ($subVariantId) {
                    $planSubVariantId = isset($item->plan) ? $item->plan->sub_variant_id : ($item->sub_variant_id ?? null);
                    // NOTE: sub_variant is not always standard on all systems; if it doesn't exist, nothing will match
                    if ((int)$planSubVariantId !== (int)$subVariantId) {
                        $match = false;
                    }
                }
                if ($categoryId) {
                    // can't get category here, so we'll do category filter AFTER loading plan below
                }
                return $match;
            })->values();
        }

        /*
        |--------------------------------------------------------------------------
        | Get Plans + Providers
        |--------------------------------------------------------------------------
        */

        $planIds = $orderItems
            ->pluck('plan_id')
            ->unique()
            ->filter()
            ->values()
            ->toArray();

        $plans = collect();

        if (!empty($planIds)) {
            $plans = \App\Models\Plan::whereIn('id', $planIds)
                ->with('provider')
                ->get()
                ->keyBy('id');
        }

        $providerIds = $plans->pluck('provider_id')
            ->unique()
            ->filter()
            ->values()
            ->all();

        $variantsByProvider = collect();
        if ($providerIds !== []) {
            $variantsByProvider = Variant::query()
                ->whereIn('provider_id', $providerIds)
                ->get()
                ->groupBy('provider_id');
        }

        /*
        |--------------------------------------------------------------------------
        | Price Function
        |--------------------------------------------------------------------------
        */

        $getPrice = function ($item) {
            return (float) (
                $item->total_amount ??
                $item->subtotal ??
                $item->price ??
                0
            );
        };

        /*
        |--------------------------------------------------------------------------
        | Prepare Customer Subscription Response
        |--------------------------------------------------------------------------
        */

        $subscriptions = [];

        $customerOrders = $orders->where('user_id', $customer->id);
        $customerOrderIds = $customerOrders->pluck('id')->toArray();
        $customerItems = $orderItems->whereIn('order_id', $customerOrderIds);

        // For category filtering, need to use plan's category_id, so prepare here
        if ($categoryId) {
            $customerItems = $customerItems->filter(function ($item) use ($plans, $categoryId) {
                $planObj = $plans->get($item->plan_id);
                return $planObj && isset($planObj->category_id) && (int)$planObj->category_id === (int)$categoryId;
            })->values();
        }

        foreach ($customerItems as $item) {

            $order = $customerOrders->firstWhere('id', $item->order_id);

            if (!$order) {
                continue;
            }

            $plan = $plans->get($item->plan_id);

            if (!$plan) {
                continue;
            }

            $provider = $plan->provider;
            $providerVariants = $variantsByProvider->get((int) ($provider->id ?? 0), collect());
            $variantPayload = $this->buildSubscriptionVariantPayload($plan, $provider, $providerVariants);

            /*
            |--------------------------------------------------------------------------
            | Subscription Dates
            |--------------------------------------------------------------------------
            */

            $startDate =
                $item->subscription_start_date
                ?? $order->subscription_start_date
                ?? $order->created_at;

            $endDate =
                $item->subscription_end_date
                ?? $order->subscription_end_date;

            $start = $startDate
                ? \Carbon\Carbon::parse($startDate)
                : null;

            $end = $endDate
                ? \Carbon\Carbon::parse($endDate)
                : null;

            /*
            |--------------------------------------------------------------------------
            | Days Left
            |--------------------------------------------------------------------------
            */

            $daysLeft = $end
                ? \Carbon\Carbon::today()->diffInDays($end, false)
                : null;

            /*
            |--------------------------------------------------------------------------
            | Subscription Status
            |--------------------------------------------------------------------------
            */

            if ($daysLeft !== null && $daysLeft < 0) {
                $subscriptionStatus = 'Expired';
            } elseif ($daysLeft !== null && $daysLeft <= 30) {
                $subscriptionStatus = 'Expiring';
            } else {
                $subscriptionStatus = 'Active';
            }

            /*
            |--------------------------------------------------------------------------
            | Customer Subscription
            |--------------------------------------------------------------------------
            */

            $subscriptions[] = [
                'order_sub_id' => $item->id,
                'subscription_id' => (string) (
                    $item->subscription_id ?? ''
                ),
                'subscription_no' => (string) (
                    $item->subscription_id ?? ''
                ),
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'plan_id' => $item->plan_id,
                'plan_name' => $plan->name,
                'domain_name' => $order->domain_name,
                'price' => $getPrice($item),
                'quantity' => (int) (
                    $item->quantity ?? 0
                ),
                'license_count' => (int) (
                    $item->license_count
                    ?? $item->quantity
                    ?? 0
                ),
                'status' => $subscriptionStatus,
                'days_left' => $daysLeft,
                'provider_id' => $provider->id ?? null,
                'provider_name' => $provider->name ?? null,
                'provider' => $variantPayload['provider'],
                'start_date' => $start
                    ? $start->format('d-m-Y')
                    : null,
                'end_date' => $end
                    ? $end->format('d-m-Y')
                    : null,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Final Response (single customer)
        |--------------------------------------------------------------------------
        */

        $customerData = [

            'customer_id' => $customer->id,

            'customer_no' => $customer->customer_no,

            'partner_id' => $customer->partner_id,

            'customer_name' => $customer->name,

            'company_name' => $customer->company_name,

            'email' => $customer->email,

            'mobile' => $customer->mobile,

            'subscriptions' => $subscriptions,

            'total_subscriptions' => count($subscriptions),

            'active_subscriptions' => collect($subscriptions)
                ->where('status', 'Active')
                ->count(),

            'expiring_subscriptions' => collect($subscriptions)
                ->where('status', 'Expiring')
                ->count(),

            'expired_subscriptions' => collect($subscriptions)
                ->where('status', 'Expired')
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Customer subscriptions fetched successfully',
            'data' => [
                'partner_id' => (int) $partnerId,
                'total_customers' => count($customerData),
                'customer_id' => (int) $customer->id,
                'customers' => [$customerData]
            ]
        ]);
    }

    /**
     * @return array{
     *   provider: array<string, mixed>
     * }
     */
    private function buildSubscriptionVariantPayload($plan, $provider, $providerVariants): array
    {
        if (!$provider) {
            return [
                'provider' => [
                    'provider_id' => null,
                    'provider_name' => null,
                    'has_variant' => false,
                    'variant_id' => null,
                    'variant_name' => null,
                    'sub_variant_id' => null,
                    'sub_variant_name' => null,
                    'available_variant_keys' => [],
                    'available_sub_variant_keys' => [],
                    'variant' => [],
                    'sub_variant' => [],
                ],
            ];
        }

        $providerId = (int) ($provider->id ?? 0);
        $hasVariant = strtolower(trim((string) ($provider->has_variant ?? 'no'))) === 'yes';

        $resolvedVariantId = 0;
        $resolvedVariantName = null;
        $resolvedSubVariantId = 0;
        $resolvedSubVariantName = null;
        $variantFlags = [];
        $subVariantFlags = [];
        $availableVariantKeys = [];
        $availableSubVariantKeys = [];

        $planVariantId = (int) ($plan->variant_id ?? 0);
        $planVariant = $planVariantId > 0
            ? $providerVariants->firstWhere('id', $planVariantId)
            : null;

        if ($planVariant) {
            if (!empty($planVariant->parent_variant_id)) {
                $resolvedSubVariantId = (int) $planVariant->id;
                $resolvedSubVariantName = trim((string) ($planVariant->name ?? '')) ?: null;
                $parent = $providerVariants->firstWhere('id', (int) $planVariant->parent_variant_id);
                if ($parent) {
                    $resolvedVariantId = (int) $parent->id;
                    $resolvedVariantName = trim((string) ($parent->name ?? '')) ?: null;
                }
            } else {
                $resolvedVariantId = (int) $planVariant->id;
                $resolvedVariantName = trim((string) ($planVariant->name ?? '')) ?: null;
            }
        }

        if ($hasVariant && $providerVariants->isNotEmpty()) {
            foreach ($providerVariants as $variantRow) {
                if (!empty($variantRow->parent_variant_id)) {
                    continue;
                }
                $key = $this->variantSlugKey((string) ($variantRow->name ?? ''));
                if ($key === '') {
                    continue;
                }
                $availableVariantKeys[] = $key;
                if ($resolvedVariantId > 0 && (int) $variantRow->id === $resolvedVariantId) {
                    $variantFlags[$key] = true;
                }
            }

            foreach ($providerVariants as $variantRow) {
                if (empty($variantRow->parent_variant_id)) {
                    continue;
                }
                $key = $this->variantSlugKey((string) ($variantRow->name ?? ''));
                if ($key === '') {
                    continue;
                }
                $availableSubVariantKeys[] = $key;
                if ($resolvedSubVariantId > 0 && (int) $variantRow->id === $resolvedSubVariantId) {
                    $subVariantFlags[$key] = true;
                }
            }
        }

        return [
            'provider' => [
                'provider_id' => $providerId > 0 ? $providerId : null,
                'provider_name' => $provider->name ?? null,
                'has_variant' => $hasVariant,
                'variant_id' => $resolvedVariantId > 0 ? $resolvedVariantId : null,
                'variant_name' => $resolvedVariantName,
                'sub_variant_id' => $resolvedSubVariantId > 0 ? $resolvedSubVariantId : null,
                'sub_variant_name' => $resolvedSubVariantName,
                'available_variant_keys' => array_values(array_unique($availableVariantKeys)),
                'available_sub_variant_keys' => array_values(array_unique($availableSubVariantKeys)),
                'variant' => $variantFlags,
                'sub_variant' => $subVariantFlags,
            ],
        ];
    }

    private function variantSlugKey(string $name): string
    {
        return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $name) ?? '', '_'));
    }

    /**
     * Parse pagination ints; "undefined"/"null"/empty → default.
     */
    private function resolvePositiveInt(mixed $value, int $default, int $min = 1, ?int $max = null): int
    {
        if ($value === null || $value === '' || $value === false) {
            return $default;
        }

        if (is_string($value)) {
            $trimmed = strtolower(trim($value));
            if ($trimmed === '' || $trimmed === 'undefined' || $trimmed === 'null' || !is_numeric($trimmed)) {
                return $default;
            }
            $value = $trimmed;
        }

        if (!is_numeric($value)) {
            return $default;
        }

        $int = (int) $value;
        if ($int < $min) {
            return $default;
        }
        if ($max !== null && $int > $max) {
            return $max;
        }

        return $int;
    }
}
