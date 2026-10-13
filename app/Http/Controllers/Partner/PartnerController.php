<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Partner\Partner;
use App\Models\Partner\PartnerOtp;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use App\Mail\PartnerOtpMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Twilio\Rest\Client;


class PartnerController extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse|\Illuminate\Contracts\View\View|mixed
     */
    public function me(Request $request)
    {
        return $this->successResponse('Profile fetched.', $request->user());
    }
    public function showLogin() { 
        return view('partner.login'); 
    }
    
    public function showRegister() { 
        return view('partner.register'); 
    }

    public function showForgotPassword() {
        return view('partner.forgot-password');
    }

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:partners,email'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $partner = DB::table('partners')->where('email', $request->email)->first();
        $otp = rand(100000, 999999);

        $otpRecord = PartnerOtp::where('partner_id', $partner->id)->where('type', 'forgot_password')->first();
        if (!$otpRecord) {
            $otpRecord = new PartnerOtp();
            $otpRecord->partner_id = $partner->id;
            $otpRecord->type = 'forgot_password';
        }
        $otpRecord->otp = $otp;
        $otpRecord->expires_at = now()->addMinutes(5);
        $otpRecord->verified = 0;
        $otpRecord->save();

        session(['reset_email' => $partner->email]);

        try {
            Mail::to($request->email)->send(new PartnerOtpMail($otp));
            return response()->json([
                'success' => true,
                'message' => 'An OTP has been sent to your email to reset your password.',
                'redirect' => url('/partner/verify-reset-otp')
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to send OTP email: ' . $e->getMessage(), 500);
        }
    }

    public function showVerifyResetOtp(Request $request) {
        if (!session('reset_email')) {
            return redirect('/partner/forgot-password');
        }
        return view('partner.verify-reset-otp');
    }

    public function verifyResetOtp(Request $request) {
        $validator = Validator::make($request->all(), [
            'otp' => 'required|digits:6',
            'email' => 'nullable'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $email = session('reset_email');
        if (!$email) {
            return $this->errorResponse('Session expired or email missing. Please request OTP again.', 400);
        }

        $partner = Partner::where('email', $email)->first();
        if (!$partner) {
            return $this->errorResponse('Partner not found.', 404);
        }

        $otpRecord = PartnerOtp::where('partner_id', $partner->id)
            ->where('otp', $request->otp)
            ->first();

        if (!$otpRecord) {
            return response()->json(['error' => ['otp' => ['OTP not found in database.']]]);
        }

        if ($otpRecord->type !== 'forgot_password') {
            return response()->json(['error' => ['otp' => ['OTP found but type is mismatch (is ' . $otpRecord->type . ').']]]);
        }

        if ($otpRecord->expires_at <= now()) {
            return $this->errorResponse('OTP has expired.', 400);
        }

        $otpRecord->update(['verified' => 1]);

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully.',
            'redirect' => url('/partner/reset-password')
        ]);
    }

    public function showResetPassword(Request $request) {
        if (!session('reset_email')) {
            return redirect('/partner/login');
        }
        return view('partner.reset-password', ['email' => session('reset_email')]);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|min:8|confirmed',
            'email' => 'nullable'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $email = session('reset_email');
        if (!$email) {
            return $this->errorResponse('Session expired.', 400);
        }

        $partner = Partner::where('email', $email)->first();
        if (!$partner) {
            return $this->errorResponse('Partner not found.', 400);
        }

        $otpRecord = PartnerOtp::where('partner_id', $partner->id)
            ->where('type', 'forgot_password')
            ->where('verified', 1)
            ->first();

        if (!$otpRecord) {
            return $this->errorResponse('Please verify OTP first before resetting password.', 400);
        }

        $partner->update(['password' => Hash::make($request->password)]);
        $otpRecord->delete();

        return response()->json([
            'success' => true,
            'message' => 'Your password has been reset successfully!',
            'redirect' => url('/partner/login')
        ]);
    }
    
    public function showVerifyOtp(Request $request) { 
        if (!$request->session()->has('partner_email')) {
            return redirect('/partner/login');
        }
        return view('partner.verify-otp'); 
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            // 'password' => $request->expectsJson() ? 'required|min:8|confirmed' : 'nullable',
            'mobile' => 'required|string',
            'gstin' => ['required','string','size:15','regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/'],
            'company_name' => 'required|string',
            'company_address' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            DB::beginTransaction();
            $email = strtolower(trim((string) $request->email));
            $partner = Partner::where('email', $email)->first();

            if ($partner && (int) ($partner->email_verified ?? 0) === 1) {
                DB::rollBack();
                return response()->json([
                    'error' => true,
                    'email' => 'The email has already been taken.',
                ], 422);
            }

            $payload = [
                'name' => $request->name,
                'email' => $email,
                'mobile' => $request->mobile,
                'gstin' => strtoupper((string) $request->gstin),
                'pan_no' => $request->input('pan_no'),
                'company_name' => $request->company_name,
                'company_address' => $request->company_address ?? null,
                'legal_name' => $request->input('legal_name', $request->company_name),
                'trade_name' => $request->input('trade_name', $request->company_name),
                'address' => $request->input('address', $request->company_address),
                'country' => $request->input('country', 'India'),
                'state' => $request->input('state'),
                'city' => $request->input('city'),
                'postcode' => $request->input('postcode', $request->input('pincode')),
                'pincode' => $request->input('pincode'),
                'gst_status' => $request->input('gst_status'),
                'last_updated' => $request->input('last_updated'),
                'constitution' => $request->input('constitution'),
                'registration_date' => $request->input('registration_date'),
                'nature_of_business' => $request->input('nature_of_business'),
                'email_verified' => 0,
                'status' => 'pending',
            ];

            if ($partner) {
                $partner->update($payload);
            } else {
                $partner = Partner::create($payload);
            }

        // Regenerate verification OTP and refresh expiry for incomplete registrations.
        $this->createOrRefreshPartnerOtp($partner, 'verify_email', 10);
        
        DB::commit();

        $request->session()->put('partner_email', $partner->email);

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. OTP sent to your email.',
            'data' => $partner,
            'redirect' => url('/partner/verify-otp')
        ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Registration failed: ' . $e->getMessage(), 500);
        }
    }

    private function createOrRefreshPartnerOtp(Partner $partner, string $type, int $ttlMinutes): void
    {
        $otp = rand(100000, 999999);
        $otpRecord = PartnerOtp::where('partner_id', $partner->id)
            ->where('type', $type)
            ->latest('id')
            ->first();

        if ($otpRecord) {
            $otpRecord->update([
                'otp' => $otp,
                'expires_at' => now()->addMinutes($ttlMinutes),
                'verified' => 0,
            ]);
        } else {
            PartnerOtp::create([
                'partner_id' => $partner->id,
                'otp' => $otp,
                'type' => $type,
                'expires_at' => now()->addMinutes($ttlMinutes),
                'verified' => 0,
            ]);
        }

        try {
            Mail::to($partner->email)->send(new PartnerOtpMail($otp));
        } catch (\Exception $e) {
            // Keep flow successful even if mail fails.
        }
    }

    // Helper method to fetch GSTIN data via existing API logic
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
    
    public function sendOtp($requestOrEmail)
    {
        if ($requestOrEmail instanceof Request) {
            $validator = Validator::make($requestOrEmail->all(), ['email' => 'required|email']);
            if ($validator->fails()) {
                if ($requestOrEmail->expectsJson()) {
                    return $this->validationErrorResponse($validator);
                }
                if ($requestOrEmail->ajax()) {
                    return $this->errorResponse('Invalid email format.', 400);
                }
                return back()->with('error', 'Invalid email format.');
            }
            $email = $requestOrEmail->email;
            $isRequest = true;
            $request = $requestOrEmail;
        } else {
            $email = $requestOrEmail;
            $isRequest = false;
        }

        $partner = DB::table('partners')->where('email', $email)->first();
        if (!$partner) {
            if ($isRequest) {
                return back()->with('error', 'Partner not found.');
            }
            return false;
        }

        $otp = rand(100000, 999999);
        PartnerOtp::create([
            'partner_id' => $partner->id,
            'otp' => $otp,
            'expires_at' => now()->addMinutes(10),
        ]);

        try {
            Mail::to($partner->email)->send(new PartnerOtpMail($otp));
        } catch (\Exception $e) {
            // Mail error
        }

        if ($isRequest) {
            if ($request->expectsJson()) {
                return $this->successResponse('OTP resent successfully.');
            }
            return back()->with('success', 'OTP resent successfully.');
        }

        return true;
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'otp' => 'required|digits:6',
            'email' => 'nullable'
        ]);
        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }
        
        $email = $request->session()->get('partner_email');
        if (!$email) {
            return response()->json([
                'success' => false,
                'error' => true,
                'message' => 'Session expired.',
                'data' => ['redirect' => url('/partner/login')],
            ], 400);
        }

        $partner = Partner::where('email', $email)->first();
        if (!$partner) {
            return response()->json([
                'success' => false,
                'error' => true,
                'message' => 'Partner not found.',
                'data' => ['redirect' => url('/partner/login')],
            ], 400);
        }

        $otpRecord = PartnerOtp::where('partner_id', $partner->id)
            ->where('otp', $request->otp)
            ->where('verified', 0)
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (!$otpRecord) {
            return $this->errorResponse('Invalid or expired OTP.', 400);
        }

        $otpRecord->update(['verified' => 1]);
        $partner->update([
            'email_verified' => 1,
            'password' => Hash::make('Goyalinfotech@12345')
        ]);

        $request->session()->forget('partner_email');

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully.',
            'redirect' => url('/partner/login')
        ]);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'nullable'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $partner = Partner::where('email', $request->email)->first();

        $passwordOk = false;

        if ($partner) {
            // Normal case: password is stored as a hash.
            if (!empty($partner->password) && Hash::check($request->password, $partner->password)) {
                $passwordOk = true;
            }

            // Legacy fallback: in case some existing rows store plaintext password.
            // If plaintext matches, accept and migrate it to a hashed password.
            if (
                !$passwordOk &&
                !empty($partner->password) &&
                is_string($partner->password) &&
                $partner->password === $request->password
            ) {
                $partner->update([
                    'password' => Hash::make($request->password),
                ]);
                $passwordOk = true;
            }
        }

        if ($passwordOk) {
            $partner->tokens()->delete();
            $sessionToken = 'web:' . Str::random(60);
            $partner->update(['current_session_token' => $sessionToken]);

            Session::put('partnerId', $partner->id);
            Session::put('partner_id', $partner->id);
            Session::put('partnerName', $partner->name);
            Session::put('partnerRole', 'Partner');
            Session::put('partner_session_token', $sessionToken);

            return response()->json([
                'success' => true,
                'error' => false,
                'message' => 'Logged in successfully.',
                'redirect' => url('/partner-admin/dashboard')
            ]);
        }
       
        return $this->errorResponse('Invalid email or password.', 401);
    }

    public function dashboard()
    {
        return view('partner.partneradmin.dashboard');
    }

    public function sessionCheck()
    {
        return response()->json(['success' => true, 'valid' => true]);
    }

    // logout
    public function logout(Request $request) {
        $partnerId = (int) (Session::get('partnerId') ?? Session::get('partner_id'));
        if ($partnerId > 0) {
            Partner::where('id', $partnerId)->update(['current_session_token' => null]);
            $partner = Partner::find($partnerId);
            if ($partner) {
                $partner->tokens()->delete();
            }
        }

        $request->session()->flush();

        return redirect('/partner/login');
    }
}
