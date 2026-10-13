<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CompanyCategory;
use App\Models\Reseller;
use App\Models\Plan;
// session 
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Crypt;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

class HomeController extends Controller
{
    protected $plan;

    public function __construct()
    {
        $this->plan = new Plan();
    }
    public function index()
    {
        $data['plans'] = $this->plan->where(['program_id' => 3, 'is_active' => 1])->get();
        return view('home.index', $data);
    }

    public function contact()
    {
        return view('home.contact');
    }

    public function partner()
    {
        return view('home.partner');
    }

    public function tizzy_mail()
    {
        $data['plans'] = $this->plan->where(['program_id' => 1, 'is_active' => 1])->get();;
        // print_r($data['plans']);
        // die;
        return view('home.tizzy_mail', $data);
    }

    public function microsoft_365()
    {
        return view('home.microsoft_365');
    }

    public function google_workspace()
    {
        $data['plans'] = $this->plan->where(['program_id' => 4, 'is_active' => 1])->get()->toArray();
        return view('home.google_workspace', $data);
    }
    // cloud-microsoft-365 
    public function cloud_microsoft_365()
    {
        $data['PlanswithTeams'] = $this->plan->where(['program_id' => 2, 'plan_type' => 'with_teams', 'is_active' => 1])->get()->toArray();
        $data['PlanswithoutTeams'] = $this->plan->where(['program_id' => 2, 'plan_type' => 'without_teams', 'is_active' => 1])->get()->toArray();
        $data['with_copilot_WithoutTeams'] = $this->plan->where(['program_id' => 2, 'plan_type' => 'with_copilot_WithoutTeams', 'is_active' => 1])->get()->toArray();
        $data['with_copilot_WithTeams'] = $this->plan->where(['program_id' => 2, 'plan_type' => 'with_copilot_WithTeams', 'is_active' => 1])->get()->toArray();
        return view('home.cloud-microsoft-365', $data);
    }
    // migration 
    public function migration()
    {
        return view('home.migration');
    }

    public function about()
    {
        return view('home.about');
    }

    public function dashboard()
    {
        return view('home.dashboard');
    }

    public function user_login()
    {
        return view('home.login'); // Setup to point to your new login file
    }

    public function user_register()
    {
        return view('home.register');
    }

    // plan 
    public function Plan()
    {
        return view('home.plan');
    }


    // address details
    public function kycRegistration()
    {
        $data['categories'] = CompanyCategory::all();

        return view('home.reseller_kyc_registration', $data);
    }

    // payment 
    public function getUpiIntentLink($order_id)
    {
        $order = Order::findOrFail($order_id);

        $upi = "upi://pay?pa=yourvpa@bank&pn=TizzyCloud&am={$order->total_amount}&cu=INR&tn=Order{$order->id}";

        return redirect()->away($upi);
    }

    public function paymentOptions(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id'
        ]);

        $order = Order::findOrFail($request->order_id);
        $order = $order->markPaymentLinkExpiredIfTimedOut();
        $gate = $order->paymentLinkGate();
        if (!$gate['allowed']) {
            return response()->json([
                'success' => false,
                'expired' => true,
                'message' => $gate['message'] ?? 'Payment link has expired.',
                'failed_payment_attempts' => $gate['failed_payment_attempts'] ?? 0,
                'remaining_attempts' => $gate['remaining_attempts'] ?? 0,
                'remaining_hrs' => (float) ($gate['remaining_hrs'] ?? 0),
            ], 410);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment options fetched successfully.',
            'data' => [
                'order_id' => $order->id,
                'amount' => (int) ($order->total_amount * 100),
                'currency' => 'INR',
                'options' => ['upi', 'card', 'netbanking', 'wallet'],
                'upi_variants' => [
                    ['key' => 'verify_collect', 'label' => 'UPI Verify and Request'],
                    ['key' => 'qr_scan', 'label' => 'UPI QR Scan'],
                    ['key' => 'intent_redirect', 'label' => 'UPI Intent Redirect']
                ]
            ]
        ]);
    }

    public function createOrder(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'payment_method' => 'nullable|in:upi,card,netbanking,wallet',
            'upi_variant' => 'nullable|in:verify_collect,qr_scan,intent_redirect'
        ]);

        $order = Order::findOrFail($request->order_id);
        $order = $order->markPaymentLinkExpiredIfTimedOut();
        $gate = $order->paymentLinkGate();
        if (!$gate['allowed']) {
            return response()->json([
                'success' => false,
                'expired' => true,
                'message' => $gate['message'] ?? 'Payment link has expired.',
                'failed_payment_attempts' => $gate['failed_payment_attempts'] ?? 0,
                'remaining_attempts' => $gate['remaining_attempts'] ?? 0,
                'remaining_hrs' => (float) ($gate['remaining_hrs'] ?? 0),
            ], 410);
        }

        $amount = (int) ($order->total_amount * 100);

        if ($amount < 100) {
            return response()->json([
                'success' => false,
                'message' => 'Minimum order amount is ₹1'
            ], 400);
        }

        // Get Razorpay keys from DB (your existing setup)
        $setting = DB::table('pv_setting')->first();
        $key = $setting->key_id ?? '';
        $secret = $setting->key_secret ?? '';

        if (!$key || !$secret) {
            return response()->json([
                'success' => false,
                'message' => 'Payment gateway credentials are missing.'
            ], 500);
        }

        $response = Http::withBasicAuth($key, $secret)
            ->post('https://api.razorpay.com/v1/orders', [
                'amount' => $amount,
                'currency' => 'INR',
                'receipt' => 'order_' . $order->id
            ]);

        if ($response->failed()) {
            return response()->json([
                'success' => false,
                'error' => $response->body()
            ], 500);
        }

        $data = $response->json();

        // Save Razorpay order ID
        $order->razorpay_order_id = $data['id'];
        $order->save();

        return response()->json([
            'success' => true,
            'key' => $key,
            'order_id' => $data['id'],
            'amount' => $data['amount'],
            'payment_method' => $request->payment_method,
            'upi_variant' => $request->upi_variant
        ]);
    }
    public function verifyPayment(Request $request)
    {
        $request->validate([
            'razorpay_order_id' => 'required',
            'razorpay_payment_id' => 'required',
            'razorpay_signature' => 'required'
        ]);

        $setting = DB::table('pv_setting')->first();
        $secret = trim((string) ($setting->key_secret ?? ''));

        if (!$secret) {
            return response()->json([
                'success' => false,
                'message' => 'Payment gateway secret is missing.'
            ], 500);
        }

        $razorpayOrderId = trim((string) $request->razorpay_order_id);
        $razorpayPaymentId = trim((string) $request->razorpay_payment_id);
        $razorpaySignature = trim((string) $request->razorpay_signature);

        // Razorpay signature format: HMAC_SHA256(order_id|payment_id, key_secret)
        $generated_signature = hash_hmac(
            'sha256',
            $razorpayOrderId . "|" . $razorpayPaymentId,
            $secret
        );

        if (!hash_equals($generated_signature, $razorpaySignature)) {
            $order = Order::where('razorpay_order_id', $razorpayOrderId)->first();
            if ($order) {
                $order->recordPaymentLinkFailure('Invalid payment signature');
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid signature'
            ], 400);
        }

        $order = Order::where('razorpay_order_id', $razorpayOrderId)->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        $order->status = 'paid';
        $order->razorpay_payment_id = $razorpayPaymentId;
        $order->save();

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Payment successful'
        ]);
    }
    public function payment($id)
    {
        try {
            $order_id = Crypt::decryptString($id);
            $order = Order::find($order_id);
            if (!$order) {
                return redirect()->route('plan')->with('error', 'Order not found');
            }

            $order = $order->markPaymentLinkExpiredIfTimedOut();
            $gate = $order->paymentLinkGate();
            if (!$gate['allowed']) {
                $data['order'] = $order;
                $data['paymentExpired'] = true;
                $data['expiredMessage'] = $gate['message'] ?? 'This payment link has expired.';
                $data['paymentRetry'] = $gate;

                return view('home.payment2', $data);
            }

            // Fetch Razorpay settings
            $data['setting'] = $setting = DB::table('pv_setting')->first();
            $data['razorpay_key'] = $razorpay_key = $setting->key_id ?? '';
            $razorpay_secret = $setting->key_secret ?? '';

            // Create Razorpay Order if not already created
            if ($order && !$order->razorpay_order_id && $razorpay_key && $razorpay_secret) {
                try {
                    $response = Http::withBasicAuth($razorpay_key, $razorpay_secret)
                        ->post('https://api.razorpay.com/v1/orders', [
                            'amount' => (int)($order->total_amount * 100),
                            'currency' => 'INR',
                            'receipt' => 'receipt_' . $order->id,
                        ]);

                    if ($response->successful()) {
                        $order->update([
                            'razorpay_order_id' => $response->json()['id']
                        ]);
                    }
                } catch (\Exception $e) {
                    // Log error or handle gracefully
                }
            }

            $data['order'] = $order->fresh();
            $data['paymentRetry'] = $gate;

            return view('home.payment2', $data);
        } catch (\Exception $e) {
            echo  'Invalid payment session';
        }
    }

    public function recordPaymentFailure(Request $request)
    {
        $request->validate([
            'order_id' => 'required|integer|min:1',
            'error' => 'nullable|string|max:500',
        ]);

        $order = Order::query()->whereKey((int) $request->order_id)->first();
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        $order = $order->recordPaymentLinkFailure((string) $request->input('error', 'Payment failed'));

        $partnerGate = null;

        $gate = $partnerGate ?? $order->paymentLinkGate();

        return response()->json([
            'success' => true,
            'expired' => $partnerGate
                ? (bool) ($partnerGate['payment_link_expired'] ?? false)
                : !$gate['allowed'],
            'message' => $partnerGate['payment_link_message'] ?? ($gate['message'] ?? null),
            'failed_payment_attempts' => (int) ($partnerGate['failed_payment_attempts'] ?? $gate['failed_payment_attempts'] ?? 0),
            'remaining_attempts' => (int) ($partnerGate['remaining_attempts'] ?? $gate['remaining_attempts'] ?? 0),
            'remaining_hrs' => (int) ($partnerGate['remaining_hrs'] ?? $gate['remaining_hrs'] ?? 0),
            'expires_at' => $partnerGate['payment_link_expires_at'] ?? ($gate['expires_at'] ?? null),
            'payment_retry' => $partnerGate ?? $gate,
        ]);
    }
    public function generateQr(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id'
        ]);

        $order = Order::find($request->order_id);
        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        $order = $order->markPaymentLinkExpiredIfTimedOut();
        $gate = $order->paymentLinkGate();
        if (!$gate['allowed']) {
            return response()->json([
                'error' => $gate['message'] ?? 'Payment link has expired.',
                'expired' => true,
            ], 410);
        }

        // ✅ FIX: Use DB instead of config
        $setting = DB::table('pv_setting')->first();
        $key = $setting->key_id ?? '';
        $secret = $setting->key_secret ?? '';

        try {
            $response = Http::withBasicAuth($key, $secret)
                ->post('https://api.razorpay.com/v1/payments/qr_codes', [
                    "type" => "upi_qr",
                    "name" => "Tizzy Payment",
                    "usage" => "single_use",
                    "fixed_amount" => true,
                    "payment_amount" => (int) ($order->total_amount * 100),
                    "description" => "Order #" . $order->id
                ]);

            // 👇 DEBUG (VERY IMPORTANT)
            if ($response->failed()) {
                return response()->json([
                    'error' => $response->body() // 👈 shows actual Razorpay error
                ], 500);
            }

            $qrData = $response->json();

            return response()->json([
                'qr_image' => data_get($qrData, 'image_url'),
                'qr_id' => data_get($qrData, 'id'),
                'order_id' => $order->id,
                'payment_url' => data_get($qrData, 'payment_url'),
                'upi_uri' => data_get($qrData, 'upi_uri')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function checkPaymentStatus(Request $request, $qr_id)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id'
        ]);

        $order = Order::findOrFail($request->order_id);
        $order = $order->markPaymentLinkExpiredIfTimedOut();
        $gate = $order->paymentLinkGate();
        if (!$gate['allowed']) {
            return response()->json([
                'status' => 'expired',
                'message' => $gate['message'] ?? 'Payment link has expired.',
            ], 410);
        }

        $setting = DB::table('pv_setting')->first();
        $key = $setting->key_id ?? '';
        $secret = $setting->key_secret ?? '';

        if (!$key || !$secret) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payment gateway credentials are missing.'
            ], 500);
        }

        try {
            $response = Http::withBasicAuth($key, $secret)
                ->get("https://api.razorpay.com/v1/payments/qr_codes/{$qr_id}/payments");
            $paidPayment = null;

            if ($response->successful()) {
                $payments = data_get($response->json(), 'items', []);
                $paidPayment = collect($payments)->first(function ($payment) {
                    $status = data_get($payment, 'status');
                    return in_array($status, ['captured', 'authorized'], true);
                });
            }

            // Fallback for cases (especially test mode) where /qr_codes/{id}/payments
            // may return "not found" even when QR exists.
            if (!$paidPayment) {
                $qrResponse = Http::withBasicAuth($key, $secret)
                    ->get("https://api.razorpay.com/v1/payments/qr_codes/{$qr_id}");

                if ($qrResponse->successful()) {
                    $qrData = $qrResponse->json();
                    $receivedAmount = (int) data_get($qrData, 'payments_amount_received', 0);
                    $expectedAmount = (int) ($order->total_amount * 100);
                    $qrStatus = data_get($qrData, 'status');

                    if ($receivedAmount >= $expectedAmount || $qrStatus === 'closed') {
                        $order->status = 'paid';
                        $order->save();

                        $redirectUrl = Route::has('payment-success')
                            ? route('payment-success', ['id' => $order->id])
                            : url('/');

                        return response()->json([
                            'status' => 'paid',
                            'redirect_url' => $redirectUrl
                        ]);
                    }
                }
            }

            if ($paidPayment) {
                $order->status = 'paid';
                $order->razorpay_payment_id = data_get($paidPayment, 'id');
                $order->save();
            } else {
                return response()->json([
                    'status' => 'pending',
                    'message' => 'Payment is still pending.'
                ]);
            }

            $redirectUrl = Route::has('payment-success')
                ? route('payment-success', ['id' => $order->id])
                : url('/');

            return response()->json([
                'status' => 'paid',
                'redirect_url' => $redirectUrl
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'pending',
                'message' => 'Unable to verify payment right now.'
            ]);
        }
    }

    public function verifyUpiAndSendRequest(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'upi_id' => ['required', 'string', 'max:100']
        ]);

        $order = Order::find($request->order_id);
        $upiId = trim(strtolower($request->upi_id));

        if (!str_contains($upiId, '@')) {
            return response()->json([
                'error' => 'Please enter a valid UPI ID (example: name@bank).'
            ], 422);
        }

        $setting = DB::table('pv_setting')->first();
        $key = $setting->key_id ?? '';
        $secret = $setting->key_secret ?? '';

        if (!$key || !$secret) {
            return response()->json([
                'error' => 'Payment gateway configuration missing.'
            ], 500);
        }

        try {
            // Step 1: Verify VPA (UPI ID)
            $verifyResponse = Http::withBasicAuth($key, $secret)
                ->post('https://api.razorpay.com/v1/payments/validate/vpa', [
                    'vpa' => $upiId
                ]);

            $verifyJson = $verifyResponse->json();
            $isVpaValid = (bool) data_get($verifyJson, 'success', false);

            if ($verifyResponse->failed() || !$isVpaValid) {
                $razorpayError = data_get($verifyJson, 'error.description')
                    ?? data_get($verifyJson, 'error')
                    ?? data_get($verifyJson, 'message')
                    ?? 'Invalid UPI ID. Please check and try again.';
                return response()->json([
                    'error' => $razorpayError
                ], 422);
            }

            // Step 2: Send collect request directly via Razorpay API (no popup checkout)
            $collectPayload = [
                'amount' => (int) ($order->total_amount * 100),
                'currency' => 'INR',
                'order_id' => $order->razorpay_order_id,
                'email' => auth()->user()->email ?? 'customer@tizzy.cloud',
                'contact' => auth()->user()->mobile ?? '9999999999',
                'method' => 'upi',
                'vpa' => $upiId,
                'description' => 'Order #' . $order->id . ' payment request'
            ];

            $collectResponse = Http::withBasicAuth($key, $secret)
                ->post('https://api.razorpay.com/v1/payments/create/upi', $collectPayload);

            if ($collectResponse->failed()) {
                return response()->json([
                    'error' => 'UPI verified, but collect request failed: ' . $collectResponse->body()
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'UPI verified and collect request sent successfully.',
                'data' => $collectResponse->json()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
    //  public function payment(){
    //     return view('home.payment');
    //  }
    // checkout 
    public function checkout($order_id)
    {
        // if (!auth()->check()) {
        //     session(['url.intended' => url()->current()]);
        //     return redirect()->route('user_login');
        // }
        return view('home.checkout', compact('order_id'));
    }
    // select-plan 
    public function select_plan($id)
    {
        $data['plan'] = $this->plan->find($id);
        return view('home.select-plan', $data);
    }
    public function createOrderB2B(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'user_id' => 'required|exists:customers,id',
            'payment_method' => 'nullable|in:upi,card,netbanking,wallet',
            'upi_variant' => 'nullable|in:verify_collect,qr_scan,intent_redirect'
        ]);

        $order = Order::where('id', $request->order_id)
            ->where('user_id', $request->user_id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found for this user'
            ], 404);
        }

        $amount = (int) ($order->total_amount * 100);

        $setting = DB::table('pv_setting')->first();

        if (!$setting || !$setting->key_id || !$setting->key_secret) {
            return response()->json([
                'success' => false,
                'message' => 'Razorpay keys missing'
            ], 500);
        }

        try {
            $response = Http::withBasicAuth($setting->key_id, $setting->key_secret)
                ->post('https://api.razorpay.com/v1/orders', [
                    'amount' => $amount,
                    'currency' => 'INR',
                    'receipt' => 'b2b_order_' . $order->id
                ]);

            if ($response->failed()) {
                return response()->json([
                    'success' => false,
                    'error' => $response->body()
                ], 500);
            }

            $data = $response->json();

            $order->razorpay_order_id = $data['id'];
            $order->status = 'created';
            $order->save();

            return response()->json([
                'success' => true,
                'key' => $setting->key_id,
                'razorpay_order_id' => $data['id'],
                'amount' => $data['amount'],
                'currency' => 'INR',
                'payment_method' => $request->payment_method,
                'upi_variant' => $request->upi_variant
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function verifyPaymentB2B(Request $request)
    {
        $request->validate([
            'razorpay_order_id' => 'required',
            'razorpay_payment_id' => 'required',
            'razorpay_signature' => 'required',
            'user_id' => 'required'
        ]);

        $setting = DB::table('pv_setting')->first();

        $generated_signature = hash_hmac(
            'sha256',
            $request->razorpay_order_id . "|" . $request->razorpay_payment_id,
            $setting->key_secret
        );

        // ✅ DEBUG RESPONSE (temporary)
        if ($generated_signature !== $request->razorpay_signature) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid signature',
                'generated_signature' => $generated_signature,
                'received_signature' => $request->razorpay_signature
            ]);
        }

        $order = Order::where('razorpay_order_id', $request->razorpay_order_id)
            ->where('user_id', $request->user_id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        // ✅ SAVE PAYMENT DETAILS
        $order->status = 'paid';
        $order->razorpay_payment_id = $request->razorpay_payment_id;
        $order->payment_method = $request->method ?? 'unknown'; // optional
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Payment successful'
        ]);
    }
    public function paymentOptionsB2B(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'user_id' => 'required'
        ]);

        $order = Order::where('id', $request->order_id)
            ->where('user_id', $request->user_id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid order'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $order->id,
                'amount' => (int) ($order->total_amount * 100),
                'currency' => 'INR',
                'options' => ['upi', 'card', 'netbanking', 'wallet'],
                'upi_variants' => [
                    ['key' => 'verify_collect', 'label' => 'UPI Verify & Collect'],
                    ['key' => 'qr_scan', 'label' => 'QR Scan'],
                    ['key' => 'intent_redirect', 'label' => 'Intent Redirect']
                ]
            ]
        ]);
    }
}
