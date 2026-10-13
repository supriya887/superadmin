<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use App\Models\Customer;
use App\Models\GstDetail;
use App\Services\RenewalService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

use App\Http\Controllers\SuperAdmin\BillingController;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderController extends Controller
{
    private RenewalService $renewalService;

    public function __construct(RenewalService $renewalService)
    {
        $this->renewalService = $renewalService;
    }
    /**
     * Store a newly created order.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|integer',
            'plan_name' => 'required|string',
            'price' => 'required|numeric',
            'quantity' => 'required|integer|min:1',
            'duration' => 'required|string',
            'subtotal' => 'required|numeric',
            'order_category' => 'nullable|in:new,renewal,upgrade',
            'previous_plan_id' => 'nullable|integer',
            'parent_order_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            $error = $validator->errors();
            return response()->json([
                'error' => true,
                'plan_id' => $error->first('plan_id'),
                'plan_name' => $error->first('plan_name'),
                'price' => $error->first('price'),
                'quantity' => $error->first('quantity'),
                'duration' => $error->first('duration'),
                'subtotal' => $error->first('subtotal'),
            ]);
        }

        DB::beginTransaction();
        try {
            $orderData = [
                'user_id' => auth()->id(),
                'plan_id' => $request->plan_id,
                'previous_plan_id' => $request->input('previous_plan_id'),
                'parent_order_id' => $request->input('parent_order_id'),
                'order_category' => $request->input('order_category', 'new'),
                'plan_name' => $request->plan_name,
                'price' => $request->price,
                'quantity' => $request->quantity,
                'duration' => $request->duration,
                'subtotal' => $request->subtotal,
                'status' => 'pending',
            ];

            if ($request->has('order_id') && !empty($request->order_id)) {
                $order = Order::find($request->order_id);
                if ($order) {
                    $order->update($orderData);
                } else {
                    $order = Order::create($orderData);
                }
            } else {
                $order = Order::create($orderData);
            }

            // Sync `order_items` line for this single-plan home/website order so that
            // every order — cart-checkout, legacy/manual and home-flow — exposes the
            // same per-line schema downstream (invoices, get-order, admin views).
            if (Schema::hasTable('order_items') && (int) ($order->id ?? 0) > 0) {
                $itemCols = Schema::getColumnListing('order_items');
                $orderId = (int) $order->id;
                $unitPrice = round((float) $request->price, 2);
                $qty = max(1, (int) $request->quantity);
                $lineTotal = round((float) ($request->subtotal ?? $unitPrice * $qty), 2);

                $itemRow = ['order_id' => $orderId];
                if (in_array('plan_id', $itemCols, true)) {
                    $itemRow['plan_id'] = (int) $request->plan_id;
                }
                if (in_array('item_name', $itemCols, true)) {
                    $itemRow['item_name'] = (string) $request->plan_name;
                } elseif (in_array('name', $itemCols, true)) {
                    $itemRow['name'] = (string) $request->plan_name;
                }
                if (in_array('quantity', $itemCols, true)) {
                    $itemRow['quantity'] = $qty;
                }
                if (in_array('unit_price', $itemCols, true)) {
                    $itemRow['unit_price'] = $unitPrice;
                } elseif (in_array('price', $itemCols, true)) {
                    $itemRow['price'] = $unitPrice;
                }
                if (in_array('total_amount', $itemCols, true)) {
                    $itemRow['total_amount'] = $lineTotal;
                } elseif (in_array('amount', $itemCols, true)) {
                    $itemRow['amount'] = $lineTotal;
                }
                if (in_array('updated_at', $itemCols, true)) {
                    $itemRow['updated_at'] = now();
                }
                if (in_array('created_at', $itemCols, true)) {
                    $itemRow['created_at'] = now();
                }

                DB::table('order_items')->where('order_id', $orderId)->delete();
                DB::table('order_items')->insert($itemRow);
            }

            DB::commit();

            return response()->json([
                'success' => 'Plan selection saved successfully',
                'order_id' => $order->id,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    public function updateOrder(Request $request)
    {
        $validationRules = [
            'order_id' => 'required|integer',
            'tax_amount' => 'required|numeric',
            'total_amount' => 'required|numeric',
            'domain_name' => 'required|string',
        ];

        // Registration details if user is guest
        if (!auth()->check()) {
            $validationRules = array_merge($validationRules, [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'mobile' => 'required|string',
                'country' => 'required|string',
                'state' => 'required|string',
                'city' => 'required|string',
                'postcode' => 'required|string',
            ]);
        }
        // GST validation if checked
        if ($request->add_company_details) {
            $validationRules['gstin'] = ['required', 'string', 'size:15', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/'];
        }

        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            $error = $validator->errors();
            $json = ['error' => true];
            foreach ($validationRules as $key => $rule) {
                $json[$key] = $error->first($key);
            }
            return response()->json($json);
        }

        DB::beginTransaction();
        try {
            $order = Order::find($request->order_id);
            if (!$order) {
                DB::rollBack();
                return response()->json(['error' => true, 'order_id' => 'Order not found']);
            }

            $domainName = strtolower(trim((string) $request->domain_name));
            $previousStatus = strtolower(trim((string) ($order->status ?? '')));

            // Process GST first
            $gstId = null;
            if ($request->add_company_details && $request->gstin) {
                $gstin = strtoupper($request->gstin);
                $gstDetail = GstDetail::where('gstin', $gstin)->first();
                
                if (!$gstDetail) {
                    $apiData = $this->fetchGstinData($gstin);
                    if (isset($apiData['company_name'])) {
                        $parseGstDate = function ($dateStr) {
                            if (empty($dateStr) || $dateStr === '1970-01-01') return null;
                            $timestamp = strtotime(str_replace('/', '-', $dateStr));
                            return $timestamp ? date('Y-m-d', $timestamp) : null;
                        };

                        $gstDetail = GstDetail::create([
                            'gstin' => $gstin,
                            'legal_name' => $apiData['company_name'] ?? null,
                            'trade_name' => $apiData['company_name'] ?? null,
                            'address' => $apiData['company_address'] ?? null,
                            'last_updated' => $parseGstDate($apiData['last_updated'] ?? null),
                            'constitution' => $apiData['constitution'] ?? null,
                            'registration_date' => $parseGstDate($apiData['registration_date'] ?? null),
                            'nature_of_business' => $apiData['nature_of_business'] ?? null,
                            'state' => $apiData['state'] ?? null,
                            'pincode' => $apiData['pincode'] ?? null,
                            'gst_status' => $apiData['gst_status'] ?? null,
                        ]);
                    }
                }
                
                if ($gstDetail) {
                    $gstId = $gstDetail->id;
                }
            }

            $userId = auth()->id();
            if (!$userId) {
                $user = \App\Models\User::create([
                    'name' => $request->first_name . ' ' . $request->last_name,
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'email' => $request->email,
                    'password' => \Illuminate\Support\Facades\Hash::make(Str::random(8)),
                    'mobile' => $request->mobile,
                    'country' => $request->country,
                    'state' => $request->state,
                    'city' => $request->city,
                    'postcode' => $request->postcode,
                    'gstin' => $request->gstin ? strtoupper($request->gstin) : null,
                    // 'gst_id' => $gstId,
                    'status' => '1',
                    'email_verified' => true,
                ]);
                
                $userId = $user->id;
                auth()->login($user);
            } else {
                // Update existing user with GST details if provided
                $user = User::find(auth()->id());
                $updateData = [];
                if ($request->add_company_details) {
                    $updateData = [
                        'gstin' => $request->gstin ? strtoupper($request->gstin) : null,
                        // 'gst_id' => $gstId,
                        'company_name' => $request->company_name,
                        'company_address' => $request->company_address,
                    ];
                }
                // Also update profile fields if they are sent and were empty? 
                // For now, let's just focus on GST as requested.
                if (!empty($updateData) && $user) {
                    $user->update($updateData);
                }
            }

            $proRata = [
                'base_subtotal' => (float) ($request->subtotal ?? 0),
                'pro_rata_credit' => 0.0,
                'taxable_amount' => (float) ($request->subtotal ?? 0),
                'gst_amount' => (float) ($request->tax_amount ?? 0),
                'total_amount' => (float) ($request->total_amount ?? 0),
            ];
            if (in_array($request->input('order_category'), ['renewal', 'upgrade'], true) && $request->filled('parent_order_id')) {
                $parentOrder = Order::find((int) $request->input('parent_order_id'));
                $proRata = $this->renewalService->calculateProRataPricing(
                    $parentOrder,
                    (float) ($order->price ?? 0),
                    (int) ($order->quantity ?? 1)
                );
            }

            $order->update([
                'user_id' => $userId,
                'domain_name' => $domainName,
                'promo_code' => $request->promo_code,
                'discount_amount' => $request->discount_amount ?? 0,
                'tax_amount' => $proRata['gst_amount'],
                'subtotal' => $proRata['taxable_amount'],
                'total_amount' => $proRata['total_amount'],
                'gst_id' => $gstId,
                'order_category' => $request->input('order_category', $order->order_category ?? 'new'),
                'previous_plan_id' => $request->input('previous_plan_id', $order->previous_plan_id),
                'parent_order_id' => $request->input('parent_order_id', $order->parent_order_id),
                'status' => 'pending_payment',
            ]);

            // Refresh single-plan `order_items` line from the updated order so that
            // taxable-amount / discounts / pro-rata adjustments are reflected on the
            // line item (downstream invoices/get-order read from order_items).
            if (Schema::hasTable('order_items')) {
                $itemCols = Schema::getColumnListing('order_items');
                $orderId = (int) $order->id;
                $planId = (int) ($order->plan_id ?? 0);
                $planName = (string) ($order->plan_name ?? 'Plan');
                $qty = max(1, (int) ($order->quantity ?? 1));
                $unitPrice = round((float) ($order->price ?? 0), 2);
                $lineTotal = round((float) ($proRata['taxable_amount'] ?? $unitPrice * $qty), 2);

                $itemRow = ['order_id' => $orderId];
                if ($planId > 0 && in_array('plan_id', $itemCols, true)) {
                    $itemRow['plan_id'] = $planId;
                }
                if (in_array('item_name', $itemCols, true)) {
                    $itemRow['item_name'] = $planName;
                } elseif (in_array('name', $itemCols, true)) {
                    $itemRow['name'] = $planName;
                }
                if (in_array('quantity', $itemCols, true)) {
                    $itemRow['quantity'] = $qty;
                }
                if (in_array('unit_price', $itemCols, true)) {
                    $itemRow['unit_price'] = $unitPrice;
                } elseif (in_array('price', $itemCols, true)) {
                    $itemRow['price'] = $unitPrice;
                }
                if (in_array('total_amount', $itemCols, true)) {
                    $itemRow['total_amount'] = $lineTotal;
                } elseif (in_array('amount', $itemCols, true)) {
                    $itemRow['amount'] = $lineTotal;
                }
                if (in_array('discount_amount', $itemCols, true)) {
                    $itemRow['discount_amount'] = round((float) ($request->discount_amount ?? 0), 2);
                }
                if (in_array('tax_amount', $itemCols, true)) {
                    $itemRow['tax_amount'] = round((float) ($proRata['gst_amount'] ?? 0), 2);
                }
                if (in_array('updated_at', $itemCols, true)) {
                    $itemRow['updated_at'] = now();
                }

                $existingId = (int) DB::table('order_items')->where('order_id', $orderId)->value('id');
                if ($existingId > 0) {
                    DB::table('order_items')->where('id', $existingId)->update($itemRow);
                } else {
                    if (in_array('created_at', $itemCols, true)) {
                        $itemRow['created_at'] = now();
                    }
                    DB::table('order_items')->insert($itemRow);
                }
            }

            // must create session and save email 
            session()->put('email', $request->email);

            $user = User::find($userId);
            $skipKyc = (bool) ($user?->is_kyc_verified ?? false);
            $nextStep = $skipKyc ? 'purchase_order' : 'aadhaar_verification';

            DB::commit();

            if ($previousStatus !== 'pending_payment') {
                $orderId = (int) $order->id;
                $partnerId = (int) ($order->partner_id ?? 0);
                $orderNo = (string) ($order->order_no ?? $orderId);
                if ($partnerId > 0) {
                    try {
                        Notification::query()->create([
                            'partner_id' => $partnerId,
                            'order_id' => $orderId,
                            'title' => 'Order status updated',
                            'message' => 'Order #' . $orderNo . ' is pending payment.',
                            'is_read' => 0,
                            'status' => 'active',
                            'created_at' => now(),
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning('updateOrder: partner notification failed', [
                            'order_id' => $orderId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                $email = trim((string) ($user?->email ?? $request->email ?? ''));
                if ($email !== '') {
                    try {
                        Mail::send(
                            'emails.order_placed_client',
                            ['order' => (object) [
                                'id' => $orderId,
                                'order_no' => $orderNo,
                                'customer_name' => trim((string) ($user?->name ?? 'Customer')),
                                'plan_name' => (string) ($order->plan_name ?? ''),
                                'domain_name' => (string) ($order->domain_name ?? ''),
                                'total_amount' => (float) ($order->total_amount ?? 0),
                                'resolved_status' => 'pending_payment',
                            ]],
                            static function ($message) use ($email, $orderNo) {
                                $label = $orderNo !== '' ? $orderNo : '';
                                $message->to($email)->subject('Your order #' . $label . ' is saved');
                            }
                        );
                    } catch (\Throwable $e) {
                        Log::warning('updateOrder: customer email failed', [
                            'order_id' => $orderId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            return response()->json([
                'success' => 'Plan details saved successfully',
                'order_id' => Crypt::encryptString($order->id),
                'next_step' => $nextStep,
                'skip_kyc' => $skipKyc,
                'po_url' => route('purchase-order.show', ['order' => Crypt::encryptString($order->id)]),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified order.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOrder($id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        // Pull per-line items from `order_items` (cart / legacy / home-flow all write
        // a row here on save). Older databases may use `name`/`price`/`amount` instead
        // of `item_name`/`unit_price`/`total_amount`, so resolve both names.
        $items = [];
        if (Schema::hasTable('order_items')) {
            $itemCols = Schema::getColumnListing('order_items');
            $nameCol = in_array('item_name', $itemCols, true) ? 'item_name'
                : (in_array('name', $itemCols, true) ? 'name' : null);
            $unitCol = in_array('unit_price', $itemCols, true) ? 'unit_price'
                : (in_array('price', $itemCols, true) ? 'price' : null);
            $totalCol = in_array('total_amount', $itemCols, true) ? 'total_amount'
                : (in_array('amount', $itemCols, true) ? 'amount' : null);

            $rows = DB::table('order_items')->where('order_id', $order->id)->orderBy('id')->get();
            foreach ($rows as $r) {
                $items[] = [
                    'id' => (int) $r->id,
                    'plan_id' => isset($r->plan_id) ? (int) $r->plan_id : null,
                    'item_name' => $nameCol ? (string) ($r->{$nameCol} ?? '') : '',
                    'quantity' => isset($r->quantity) ? (int) $r->quantity : 1,
                    'unit_price' => $unitCol ? round((float) ($r->{$unitCol} ?? 0), 2) : 0.0,
                    'total_amount' => $totalCol ? round((float) ($r->{$totalCol} ?? 0), 2) : 0.0,
                ];
            }
        }

        // Backward-compat single-plan snapshot (existing consumers) + new `items[]`.
        return response()->json([
            'success' => true,
            'plan_name' => $order->plan_name,
            'price' => $order->price,
            'quantity' => $order->quantity,
            'duration' => $order->duration,
            'subtotal' => $order->subtotal,
            'domain_name' => $order->domain_name,
            'items' => $items,
        ]);
    }

    /**
     * Assign domain to an order.
     */
    public function assignDomain(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'domain_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $order = Order::find($id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        try {
            $order->update(['domain_name' => $request->domain_name]);
            return response()->json([
                'success' => true,
                'message' => $request->domain_name ? 'Domain assigned' : 'Domain removed',
                'domain_name' => $order->domain_name
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Update failed', 'error' => $e->getMessage()], 500);
        }
    }

    private function fetchGstinData($gstin)
    {
        $gstin = strtoupper($gstin);
        try {
            $apiKey = 'key_live_781df7faa60f4a949c14241823bc98bd';
            $apiSecret = 'secret_live_0f8e7bb482144a49b4bff36f1eaa9f89';
            $baseUrl = str_starts_with($apiKey, 'key_live') ? 'https://api.sandbox.co.in' : 'https://test-api.sandbox.co.in';
            $authResponse = \Illuminate\Support\Facades\Http::withHeaders([
                'x-api-key' => $apiKey,
                'x-api-secret' => $apiSecret,
                'Content-Type' => 'application/json',
            ])->withoutVerifying()->post("{$baseUrl}/authenticate");
            if (!$authResponse->successful()) {
                return [];
            }
            $token = $authResponse->json()['access_token'] ?? null;
            if (!$token) {
                return [];
            }
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => $token,
                'x-api-key' => $apiKey,
                'x-api-version' => '1.0',
                'Content-Type' => 'application/json',
            ])->withoutVerifying()->post("{$baseUrl}/gst/compliance/public/gstin/search", ['gstin' => $gstin]);
            if ($response->successful()) {
                $apiData = $response->json();
                $gstData = $apiData['data']['data'] ?? $apiData['data'] ?? [];
                if (!empty($gstData) && !isset($apiData['data']['error_cd'])) {
                    $companyName = $gstData['tradeNam'] ?? $gstData['lgnm'] ?? '';
                    $addrObj = $gstData['pradr']['addr'] ?? [];
                    $addrParts = [];
                    foreach (['flno','bno','bnm','st','loc','locality','dst','stcd','pncd'] as $key) {
                        if (!empty($addrObj[$key])) $addrParts[] = trim($addrObj[$key]);
                    }
                    $address = implode(', ', array_unique(array_filter($addrParts)));
                    return [
                        'company_name' => $companyName, 
                        'company_address' => $address,
                        'state' => $addrObj['st'] ?? $addrObj['stcd'] ?? null,
                        'pincode' => $addrObj['pncd'] ?? null,
                        'gst_status' => $gstData['sts'] ?? null,
                        'last_updated' => $gstData['lstupdt'] ?? null,
                        'constitution' => $gstData['ctb'] ?? null,
                        'registration_date' => $gstData['rgdt'] ?? null,
                        'nature_of_business' => $gstData['nba'] ?? null,
                    ];
                }
            }
        } catch (\Exception $e) {
            // log error if needed
        }
        return [];
    }

    public function verifyPayment(Request $request)
    {
        $setting = DB::table('pv_setting')->first();
        $secret = $setting->key_secret ?? '';

        $generated_signature = hash_hmac(
            "sha256",
            $request->razorpay_order_id . "|" . $request->razorpay_payment_id,
            $secret
        );

        if ($generated_signature == $request->razorpay_signature) {
            $order = Order::where('razorpay_order_id', $request->razorpay_order_id)->first();
            if ($order) {
                $previousStatus = strtolower(trim((string) ($order->status ?? '')));
                $order->update([
                    'status' => 'completed',
                    'razorpay_payment_id' => $request->razorpay_payment_id,
                    'razorpay_signature' => $request->razorpay_signature,
                    'payment_method' => $request->payment_method,
                ]);
                if ($previousStatus !== 'completed') {
                    $billing = app(BillingController::class);
                    try {
                        $billing->sendOrderPlacedEmailForOrder((int) $order->id);
                    } catch (\Throwable $e) {
                        Log::warning('verifyPayment: customer email failed', [
                            'order_id' => (int) $order->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                    if ((int) ($order->partner_id ?? 0) > 0) {
                        try {
                            $billing->notifyPartnerOrderPlacement((int) $order->id);
                        } catch (\Throwable $e) {
                            Log::warning('verifyPayment: partner notify failed', [
                                'order_id' => (int) $order->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'Payment successful',
                'redirect_url' => route('payment-success', Crypt::encryptString($order->id))
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => 'Payment verification failed'
        ]);
    }

    public function paymentSuccess($id)
    {
        try {
            $order_id = Crypt::decryptString($id);
            $order = Order::findOrFail($order_id);
            return view('home.payment_success', compact('order'));
        } catch (\Exception $e) {
            return redirect()->route('plan')->with('error', 'Invalid success session');
        }
    }

    public function purchaseOrder(string $encryptedOrderId)
    {
        $orderId = Crypt::decryptString($encryptedOrderId);
        $order = Order::findOrFail($orderId);
        $user = User::find($order->user_id);
        abort_if(!$user, 404);

        $prefill = $this->renewalService->buildPurchaseOrderPrefill($user);
        $proRata = $this->renewalService->calculateProRataPricing(
            $order->parent_order_id ? Order::find($order->parent_order_id) : null,
            (float) ($order->price ?? 0),
            (int) ($order->quantity ?? 1)
        );

        return view('home.purchase_order', [
            'title' => 'Purchase Order',
            'order' => $order,
            'prefill' => $prefill,
            'proRata' => $proRata,
        ]);
    }

    public function generatePurchaseOrderPdf(string $encryptedOrderId)
    {
        $orderId = Crypt::decryptString($encryptedOrderId);
        $order = Order::findOrFail($orderId);
        $user = User::find($order->user_id);
        abort_if(!$user, 404);

        $prefill = $this->renewalService->buildPurchaseOrderPrefill($user);
        $proRata = $this->renewalService->calculateProRataPricing(
            $order->parent_order_id ? Order::find($order->parent_order_id) : null,
            (float) ($order->price ?? 0),
            (int) ($order->quantity ?? 1)
        );
        $html = view('home.purchase_order_pdf', compact('order', 'prefill', 'proRata'))->render();

        if (app()->bound('dompdf.wrapper')) {
            return app('dompdf.wrapper')
                ->loadHtml($html)
                ->setPaper('a4', 'portrait')
                ->setOptions(['defaultFont' => 'sans-serif', 'isRemoteEnabled' => true])
                ->download('PO-' . $order->id . '.pdf');
        }

        return response($html, 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => 'attachment; filename="PO-' . $order->id . '.html"',
        ]);
    }
}
