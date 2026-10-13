<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\UserOtpMail;
use App\Models\EmailOtp;
use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Partner;



class AuthController extends Controller
{
    public $request;

    public $partner;
    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->partner = new Partner();
    }
    public function register_validation()
    {
        DB::beginTransaction();
        try {
            $email = strtolower(trim((string) $this->request->input('email', '')));
            $existingUser = $email !== '' ? User::where('email', $email)->first() : null;

            $rules = [
                'name'             => 'required|string|max:255',
                'email'            => 'required|string|email|max:255',
                'mobile'           => 'required|digits:10',
                'gstin'            => ['required', 'string', 'size:15', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/'],
                'company_name'     => 'required|string|max:255',
                'company_address'  => 'required|string|max:500',
                'terms_and_conditions' => 'required|accepted',
                'postcode'=>'string',
                'pincode'=>'string',
                'legal_name'=>'string',
                'trade_name'=>'string',
                'address'=>'string',
                'gst_status'=>'string',
                'last_updated'=>'date',
                'constitution'=>'string',
                'registration_date'=>'date',
                'nature_of_business'=>'string',
            ];

            // Verified user must continue getting the standard "email taken" behavior.
            if (!$existingUser) {
                $rules['email'] = 'unique:users,email';
            }

            $validator = Validator::make(
                $this->request->all(),
                $rules,
                [
                    'mobile.digits'            => 'Mobile number must be exactly 10 digits.',
                    'gstin.regex'              => 'Please enter a valid 15-character GSTIN.',
                    'terms_and_conditions.required' => 'You must accept the Terms & Conditions.',
                    'terms_and_conditions.accepted' => 'You must accept the Terms & Conditions.',
                ]
            );

            if ($validator->fails()) {
                $errors = $validator->errors();
                return response()->json([
                    'error'            => true,
                    'name'             => $errors->first('name'),
                    'email'            => $errors->first('email'),
                    'mobile'           => $errors->first('mobile'),
                    'company_name'     => $errors->first('company_name'),
                    'company_address'  => $errors->first('company_address'),
                    'gstin'            => $errors->first('gstin'),
                    'terms_and_conditions' => $errors->first('terms_and_conditions'),
                ]);
            }

            if ($existingUser && (bool) $existingUser->email_verified) {
                return response()->json([
                    'error' => true,
                    'email' => 'The email has already been taken.',
                ], 422);
            }

            if ($existingUser && !(bool) $existingUser->email_verified) {
                $existingUser->update([
                    'name'            => $this->request->name,
                    'mobile'          => $this->request->mobile,
                    'gstin'           => strtoupper((string) $this->request->gstin),
                    'company_name'    => $this->request->company_name,
                    'company_address' => $this->request->company_address,
                    'email_verified'  => false,
                    'status'          => '0',
                ]);
                $user = $existingUser;
            } else {
                $user = User::create([
                    'name'            => $this->request->name,
                    'email'           => $email,
                    'mobile'          => $this->request->mobile,
                    'gstin'           => strtoupper((string) $this->request->gstin),
                    'company_name'    => $this->request->company_name,
                    'company_address' => $this->request->company_address,
                    'email_verified'  => false,
                    'status'          => '0',
                    'password'        => null,
                    'postcode'        => $this->request->postcode,
                    'pincode'         => $this->request->pincode,
                    'legal_name'      => $this->request->legal_name,
                    'trade_name'      => $this->request->trade_name,
                    'address'         => $this->request->address,
                    'gst_status'      => $this->request->gst_status,
                    'last_updated'    => $this->request->last_updated,
                    'constitution'    => $this->request->constitution,
                    'registration_date' => $this->request->registration_date,
                    'nature_of_business' => $this->request->nature_of_business,
                ]);
            }

            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            EmailOtp::create([
                'user_id'    => $user->id,
                'otp'        => $otp,
                'type'       => 'verify_email',
                'expires_at' => now()->addMinutes(5),
                'verified'   => false,
            ]);

            session(['pending_verification_email' => $user->email]);

            try {
                Mail::to($user->email)->send(new UserOtpMail($otp, $user->name, 10, 'register'));
            } catch (\Exception $e) {
                // Keep registration resumable even when mail fails transiently.
            }
            $this->sendRegistrationOtpSms((string) ($user->mobile ?? ''), $otp, 5);

            DB::commit();

            if ($this->request->expectsJson()) {
                return $this->successResponse('Registration successful! Please verify your email with the OTP sent to ' . $user->email, $user, 201);
            }

            return response()->json([
                'success' => 'Registration successful! Please verify your email with the OTP sent to ' . $user->email,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Registration failed: ' . $e->getMessage(), 500);
        }
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'otp' => 'required|numeric|digits:6',
        ], [
            'otp.required' => 'Please enter the 6-digit OTP.',
            'otp.numeric'  => 'OTP must be a number.',
            'otp.digits'   => 'OTP must be exactly 6 digits.',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $email = $request->expectsJson() ? $request->email : session('pending_verification_email');

        if (!$email) {
            if ($request->expectsJson()) {
                return $this->errorResponse('Something went wrong. Please try again.', 400);
            }
            return response()->json([
                'error' => 'Something went wrong. Please try again.',
            ], 400);
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            if ($request->expectsJson()) {
                return $this->errorResponse('Something went wrong. Please try again.', 404);
            }
            return response()->json([
                'error' => 'Something went wrong. Please try again.',
            ], 404);
        }

        $otpRecord = EmailOtp::where('user_id', $user->id)
            ->where('otp', $request->otp)
            ->where('verified', false)
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$otpRecord) {
            if ($request->expectsJson()) {
                return $this->errorResponse('Something went wrong. Please try again.', 400);
            }
            return response()->json([
                'error' => 'Something went wrong. Please try again.',
            ], 400);
        }

        // Mark OTP as verified
        $otpRecord->verified = true;
        $otpRecord->save();

        // Update user as email verified
        $user->email_verified = true;
        $user->save();

        // Clean up session
        session()->forget('pending_verification_email');

        if ($request->expectsJson()) {
            return $this->successResponse('Email verified successfully.');
        }

        return response()->json([
            'success' => 'Email verified successfully!',
            'redirect' => session()->pull('url.intended', route('verification-success'))
        ]);
    }

    public function resendOtp(Request $request)
    {
        $email = $request->expectsJson() ? $request->email : session('pending_verification_email');

        if (!$email) {
            return $this->errorResponse('Session expired or email missing. Please register again.', 400);
        }

        $user = $this->partner->where('email', $email)->first();

        if (!$user) {
            return $this->errorResponse('User not found.', 404);
        }

        DB::beginTransaction();
        try {
            // Generate new 6-digit OTP
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            // Create new OTP record
            EmailOtp::create([
                'user_id'    => $user->id,
                'otp'        => $otp,
                'expires_at' => now()->addMinutes(5),
                'verified'   => false,
            ]);

            // Send the OTP email
            Mail::to($user->email)->send(new UserOtpMail($otp, $user->name, 10, 'register'));
            $this->sendRegistrationOtpSms((string) ($user->mobile ?? ''), $otp, 5);

            DB::commit();

            if ($request->expectsJson()) {
                return $this->successResponse('A new OTP has been sent to your email.');
            }

            return response()->json([
                'success' => 'A new OTP has been sent to your email.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to resend OTP: ' . $e->getMessage(), 500);
        }
    }

    public function loginPost(Request $request)
    {
        $rules = [
            'password' => 'required|string',
        ];

        if ($this->request->filled('mobile')) {
            $rules['mobile'] = 'required|digits:10';
        } else {
            $rules['email'] = 'required|string|email';
        }

        $validator = Validator::make($this->request->all(), $rules);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        } else {
            if (Auth::attempt(['email' => $this->request->email, 'password' => $this->request->password])) {
                $user = Auth::user();
                $this->request->session()->regenerate();
                
                if ($this->request->remember) {
                    \Illuminate\Support\Facades\Cookie::queue('userEmail', $this->request->email, 1209600);
                    \Illuminate\Support\Facades\Cookie::queue('userPassword', $this->request->password, 1209600);
                    \Illuminate\Support\Facades\Cookie::queue('remember', '1', 1209600);
                }

                // Create explicit session data for the user
                session(['partnerId' => $user->id]);
                session(['partnerName' => $user->name]);
                session(['partnerEmail' => $user->email]);
                // token 
                $token = $user->createToken('authToken')->plainTextToken;
                session(['token' => $token]);
                
                // Record Login History
                $this->recordLogin($this->request);

                if ($this->request->expectsJson()) {
                    return $this->successResponse('Login Successful.', [
                        'token' => $token,
                        'user' => $user
                    ]);
                }

                return response()->json([
                    "success" => "Login Successfully.",
                    "redirect" => session()->pull('url.intended', route('dashboard'))
                ]);
            } else {
                return $this->errorResponse('Please Enter Valid Email and Password.', 401);
            }
        }
    }



   
    public function verify_email()
    {
        return view('home.verify_email');
    }

    public function logout(Request $request)
    {
        if (Auth::check()) {
            $sessionId = session()->getId();
            
            // Update logout time for the current session's login record
            $loginHistory = LoginHistory::where('user_id', Auth::id())
                                        ->whereNull('logout_time')
                                        ->orderBy('id', 'desc')
                                        ->first();
                                        
            if ($loginHistory) {
                $loginHistory->update([
                    'logout_time' => now()
                ]);
            }
        }

        $this->request->session()->forget(['partnerId', 'partnerName', 'partnerEmail']);
        Auth::logout();
        $this->request->session()->invalidate();
        $this->request->session()->regenerateToken();

        return redirect('user-login');
    }

    private function recordLogin(Request $request)
    {
        if (Auth::check()) {
            LoginHistory::create([
                'user_id' => Auth::id(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'login_time' => now(),
            ]);
        }
    }

    private function sendRegistrationOtpSms(string $mobile, string $otp, int $expiryMinutes = 5): void
    {
        if ($this->normalizeIndianMobile($mobile) === null) {
            return;
        }

        $sent = app(\App\Services\SmsService::class)->sendOtpRegistration($mobile, $otp, $expiryMinutes);
        if (!$sent) {
            Log::warning('Registration OTP SMS sending failed.', ['number' => $mobile]);
        }

        if ($mobile !== '' && class_exists(\App\Services\WhatsAppService::class)) {
            try {
                $waSent = app(\App\Services\WhatsAppService::class)->sendRegistrationOtp($mobile, $otp, $expiryMinutes);
                if (!$waSent) {
                    Log::warning('Registration OTP WhatsApp sending failed.', ['number' => $mobile]);
                }
            } catch (\Throwable $e) {
                Log::warning('Registration OTP WhatsApp exception.', [
                    'number' => $mobile,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function getSmsGatewaySettings(): array
    {
        if (!Schema::hasTable('sms_settings')) {
            return [];
        }

        $row = DB::table('sms_settings')
            ->where('provider', 'jmd_sms')
            ->where('is_active', 1)
            ->orderByDesc('id')
            ->first();

        if (!$row) {
            return [];
        }

        return [
            'base_url' => (string) ($row->base_url ?? ''),
            'user' => (string) ($row->user ?? ''),
            'password' => (string) ($row->password ?? ''),
            'senderid' => (string) ($row->senderid ?? ''),
            'channel' => (string) ($row->channel ?? ''),
            'dcs' => (string) ($row->dcs ?? ''),
            'flashsms' => (string) ($row->flashsms ?? ''),
            'route' => (string) ($row->route ?? ''),
            'peid' => (string) ($row->peid ?? ''),
            'template_id' => (string) ($row->template_id ?? ''),
        ];
    }

    private function normalizeIndianMobile(string $mobile): ?string
    {
        $digits = preg_replace('/\D+/', '', $mobile);
        if (!is_string($digits) || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            return '91' . $digits;
        }

        if (strlen($digits) >= 11 && strlen($digits) <= 15) {
            return $digits;
        }

        return null;
    }

    // search-gstin
    public function searchGstin()
    {
        
        $validator = Validator::make($this->request->all(), [
            'gstin' => ['required', 'string', 'size:15', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/'],
        ], [
            'gstin.required' => 'GSTIN is required.',
            'gstin.size'     => 'GSTIN must be exactly 15 characters.',
            'gstin.regex'    => 'Please enter a valid GSTIN (e.g. 22AAAAA0000A1Z5).',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error'   => true,
                'message' => $validator->errors()->first('gstin'),
            ], 422);
        }

        $gstin = strtoupper($this->request->gstin);

        try {
            // Pair key + URL (live key on test-api → 403 "Use test API key for test environment")
            $creds = $this->sandboxGstCredentials();
            $apiKey = $creds['api_key'];
            $apiSecret = $creds['api_secret'];
            $baseUrl = $creds['base_url'];

            // 1. Authenticate to get Access Token
            $authResponse = Http::withHeaders([
                'x-api-key'    => $apiKey,
                'x-api-secret' => $apiSecret,
                'Content-Type' => 'application/json',
            ])->withoutVerifying()
              ->post("{$baseUrl}/authenticate");

            if (!$authResponse->successful()) {
                $authMsg = data_get($authResponse->json(), 'message')
                    ?? data_get($authResponse->json(), 'data.message')
                    ?? 'Authentication failed with GST API.';
                return response()->json([
                    'error'   => true,
                    'message' => $authMsg . ' (Status: ' . $authResponse->status() . ')',
                ], $authResponse->status());
            }

            $token = $authResponse->json()['access_token'] ?? null;

            if (!$token) {
                return response()->json([
                    'error'   => true,
                    'message' => 'Access token not found in authentication response.',
                ], 500);
            }

            // 2. Search GSTIN using the Token
            $response = Http::withHeaders([
                'Authorization' => $token,
                'x-api-key'     => $apiKey,
                'x-api-version' => '1.0',
                'Content-Type'  => 'application/json',
            ])->withoutVerifying()
              ->post("{$baseUrl}/gst/compliance/public/gstin/search", [
                  'gstin' => $gstin,
              ]);
          
            if ($response->successful()) {
                $apiData = $response->json();
               
                // Handle nested 'data' array structure from API
                $gstData = $apiData['data']['data'] ?? $apiData['data'] ?? [];
               
                if (!empty($gstData) && !isset($apiData['data']['error_cd'])) {
                    //last_updated DATE,constitution VARCHAR(100),registration_date DATE,nature_of_business JSON($gstData[''], $gstData[''])
                    $parseGstDate = function ($dateStr) {
                        if (empty($dateStr) || $dateStr === '1970-01-01') return null;
                        $timestamp = strtotime(str_replace('/', '-', $dateStr));
                        return $timestamp ? date('Y-m-d', $timestamp) : null;
                    };
                    
                    $last_updated = $parseGstDate($gstData['lstupdt'] ?? null);
                    $constitution = $gstData['ctb'] ?? null;
                    $registration_date = $parseGstDate($gstData['rgdt'] ?? null);
                    $nature_of_business = $gstData['nba'] ?? null;
                    // Prefer Trade Name, fall back to Legal Name
                    $companyName = !empty($gstData['tradeNam']) ? $gstData['tradeNam'] : ($gstData['lgnm'] ?? '');
                    $panNo = substr($gstin, 2, 10); // Extract PAN from GSTIN
                    $gstData[''] = $companyName;
                    // Parse and concatenate the primary address
                    $addrObj = $gstData['pradr']['addr'] ?? [];
                    $addrParts = [];
                    
                    if (!empty($addrObj['flno'])) $addrParts[] = trim($addrObj['flno']);
                    if (!empty($addrObj['bno'])) $addrParts[] = trim($addrObj['bno']);
                    if (!empty($addrObj['bnm'])) $addrParts[] = trim($addrObj['bnm']);
                    if (!empty($addrObj['st'])) $addrParts[] = trim($addrObj['st']);
                    if (!empty($addrObj['loc'])) $addrParts[] = trim($addrObj['loc']);
                    if (!empty($addrObj['locality'])) $addrParts[] = trim($addrObj['locality']);
                    if (!empty($addrObj['dst'])) $addrParts[] = trim($addrObj['dst']);
                    if (!empty($addrObj['stcd'])) $addrParts[] = trim($addrObj['stcd']);
                    if (!empty($addrObj['pncd'])) $addrParts[] = trim($addrObj['pncd']);
                    
                    // Remove empty elements and duplicates, then join with comma
                    $address = implode(', ', array_unique(array_filter($addrParts)));
                    // print_r($address);
                    // die;

                    return response()->json([
                        'company_name'    => $companyName,
                        'legal_name'      => $gstData['lgnm'] ?? $companyName,
                        'trade_name'      => $gstData['trdnm'] ?? $companyName,
                        'company_address' => $address,
                        'last_updated'=> $last_updated,
                        'constitution'=> $constitution,
                        'registration_date'=> $registration_date,
                        'nature_of_business'=> $nature_of_business,
                        'state'           => $addrObj['st'] ?? $addrObj['stcd'] ?? null,
                        'pincode'         => $addrObj['pncd'] ?? null,
                        'gst_status'      => $gstData['sts'] ?? null,
                        'city'=> $addrObj['dst'] ?? null,
                        'country'=> 'India',
                        'pan_no' => $panNo,
                    ]);
                }

                $message = $apiData['data']['message'] ?? 'GSTIN data not found in API response.';
                return response()->json([
                    'error'   => true,
                    'message' => $message,
                ], 404);
            }else{
                $errorData = $response->json();
                $errorMessage = $errorData['message'] ?? 'Failed to fetch data from GST API.';
            }

            
            // User-friendly message for Sandbox test environment
           

            return response()->json([
                'error'   => true,
                'message' => $errorMessage . ' (Status: ' . $response->status() . ')',
            ], $response->status());
            

        } catch (\Exception $e) {
            // print_r($e->getMessage());
            // die;
            return response()->json([
                'error'   => true,
                'message' => 'An error occurred while calling the GST API.',
                // 'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function searchGstinforapi()
    {
        // Validate GSTIN input
        $validator = Validator::make($this->request->all(), [
            'gstin' => ['required', 'string', 'size:15', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/'],
        ], [
            'gstin.required' => 'GSTIN is required.',
            'gstin.size'     => 'GSTIN must be exactly 15 characters.',
            'gstin.regex'    => 'Please enter a valid GSTIN (e.g. 22AAAAA0000A1Z5).',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first('gstin'),
            ], 422);
        }

        $gstin = strtoupper($this->request->gstin);
        // Remove debug code: print_r, die

        try {
            $creds = $this->sandboxGstCredentials();
            $apiKey = $creds['api_key'];
            $apiSecret = $creds['api_secret'];
            $baseUrl = $creds['base_url'];

            // 1. Authenticate to get Access Token
            $authResponse = Http::withHeaders([
                'x-api-key'    => $apiKey,
                'x-api-secret' => $apiSecret,
                'Content-Type' => 'application/json',
            ])->withoutVerifying()
              ->post("{$baseUrl}/authenticate");

            if (!$authResponse->successful()) {
                $authMsg = data_get($authResponse->json(), 'message')
                    ?? data_get($authResponse->json(), 'data.message')
                    ?? 'Authentication failed with GST API.';
                return response()->json([
                    'success' => false,
                    'message' => $authMsg . ' (Status: ' . $authResponse->status() . ')',
                ], $authResponse->status());
            }

            // Check if authentication response is valid JSON and has expected structure
            $authJson = null;
            try {
                $authJson = $authResponse->json();
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication response could not be parsed as JSON: ' . $e->getMessage(),
                ], 500);
            }

            $token = is_array($authJson) && isset($authJson['access_token']) ? $authJson['access_token'] : null;

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access token not found in authentication response.',
                ], 500);
            }

            // 2. Search GSTIN using the Token
            $response = Http::withHeaders([
                'Authorization' => $token,
                'x-api-key'     => $apiKey,
                'x-api-version' => '1.0',
                'Content-Type'  => 'application/json',
            ])->withoutVerifying()
              ->post("{$baseUrl}/gst/compliance/public/gstin/search", [
                  'gstin' => $gstin,
              ]);

            // Check if API response is valid JSON
            $apiData = null;
            try {
                $apiData = $response->json();
            } catch (\Exception $e) {
                // Most likely JSON Parse error, typically "Unexpected character: A"
                $errMsg = $e->getMessage();
                return response()->json([
                    'success' => false,
                    'message' => "Error parsing GST API response as JSON: " . $errMsg . 
                        '. This could indicate an invalid GSTIN or unexpected API output. Please verify the GSTIN and try again.',
                    'api_response_raw' => $response->body(),
                ], 500);
            }

            if ($response->successful()) {
                // Handle nested 'data' array structure from API
                $gstData = $apiData['data']['data'] ?? $apiData['data'] ?? [];

                if (!empty($gstData) && !isset($apiData['data']['error_cd'])) {
                    $parseGstDate = function ($dateStr) {
                        if (empty($dateStr) || $dateStr === '1970-01-01') return null;
                        $timestamp = strtotime(str_replace('/', '-', $dateStr));
                        return $timestamp ? date('Y-m-d', $timestamp) : null;
                    };
                    
                    $last_updated = $parseGstDate($gstData['lstupdt'] ?? null);
                    $constitution = $gstData['ctb'] ?? null;
                    $registration_date = $parseGstDate($gstData['rgdt'] ?? null);
                    $nature_of_business = $gstData['nba'] ?? null;
                    // Prefer Trade Name, fall back to Legal Name
                    $companyName = !empty($gstData['tradeNam']) ? $gstData['tradeNam'] : ($gstData['lgnm'] ?? '');
                    $panNo = substr($gstin, 2, 10); // Extract PAN from GSTIN

                    // Parse and concatenate the primary address
                    $addrObj = $gstData['pradr']['addr'] ?? [];
                    $addrParts = [];
                    
                    if (!empty($addrObj['flno'])) $addrParts[] = trim($addrObj['flno']);
                    if (!empty($addrObj['bno'])) $addrParts[] = trim($addrObj['bno']);
                    if (!empty($addrObj['bnm'])) $addrParts[] = trim($addrObj['bnm']);
                    if (!empty($addrObj['st'])) $addrParts[] = trim($addrObj['st']);
                    if (!empty($addrObj['loc'])) $addrParts[] = trim($addrObj['loc']);
                    if (!empty($addrObj['locality'])) $addrParts[] = trim($addrObj['locality']);
                    if (!empty($addrObj['dst'])) $addrParts[] = trim($addrObj['dst']);
                    if (!empty($addrObj['stcd'])) $addrParts[] = trim($addrObj['stcd']);
                    if (!empty($addrObj['pncd'])) $addrParts[] = trim($addrObj['pncd']);
                    
                    // Remove empty elements and duplicates, then join with comma
                    $address = implode(', ', array_unique(array_filter($addrParts)));

                    return response()->json([
                        'success'          => true,
                        'data' => [
                            'company_name'      => $companyName,
                            'legal_name'        => $gstData['lgnm'] ?? $companyName,
                            'trade_name'        => $gstData['trdnm'] ?? $companyName,
                            'company_address'   => $address,
                            'last_updated'      => $last_updated,
                            'constitution'      => $constitution,
                            'registration_date' => $registration_date,
                            'nature_of_business'=> $nature_of_business,
                            // GST addr: st = street, stcd = state, dst = district, city = city (often empty)
                            'state'             => trim((string) ($addrObj['stcd'] ?? '')) ?: null,
                            'pincode'           => $addrObj['pncd'] ?? null,
                            'gst_status'        => $gstData['sts'] ?? null,
                            'city'              => trim((string) ($addrObj['city'] ?? '')) ?: (trim((string) ($addrObj['dst'] ?? '')) ?: null),
                            'country'           => 'India',
                            'pan_no'            => $panNo,
                        ]
                    ]);
                }

                $message = $apiData['data']['message'] ?? 'GSTIN data not found in API response.';
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 404);
            } else {
                // Check if response body is valid JSON, else provide raw body for debugging
                $errorData = [];
                try {
                    $errorData = $response->json();
                    $errorMessage = $errorData['message'] ?? 'Failed to fetch data from GST API.';
                } catch (\Throwable $e) {
                    $errorMessage = 'Failed to fetch data from GST API. Also, could not parse error JSON: ' . $e->getMessage();
                    return response()->json([
                        'success' => false,
                        'message' => $errorMessage,
                        'api_response_raw' => $response->body(),
                    ], $response->status());
                }
            }

            // User-friendly message for Sandbox test environment
            if (
                isset($errorMessage)
                && $response->status() == 404
                && str_contains($errorMessage, 'Test environment: Request does not match')
            ) {
                $errorMessage = "Sandbox Test Mode: GSTIN not found in test data. Use live keys for real GSTINs.";
            }

            return response()->json([
                'success' => false,
                'message' => (isset($errorMessage) ? $errorMessage : 'Unknown error.') . ' (Status: ' . $response->status() . ')',
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while calling the GST API. ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sandbox.co.in GST credentials: key and base URL must match (test↔test, live↔live).
     *
     * @return array{api_key: string, api_secret: string, base_url: string, mode: string}
     */
    private function sandboxGstCredentials(): array
    {
        $mode = strtolower((string) env('SANDBOX_MODE', 'test'));
        $isLive = $mode === 'live';

        $apiKey = $isLive
            ? (string) env('SANDBOX_API_KEY_LIVE', env('SANDBOX_API_KEY', ''))
            : (string) env('SANDBOX_API_KEY_TEST', env('SANDBOX_API_KEY', ''));
        $apiSecret = $isLive
            ? (string) env('SANDBOX_API_SECRET_LIVE', env('SANDBOX_SECRET', ''))
            : (string) env('SANDBOX_API_SECRET_TEST', env('SANDBOX_SECRET', ''));
        $baseUrl = rtrim($isLive
            ? (string) env('SANDBOX_BASE_URL_LIVE', 'https://api.sandbox.co.in')
            : (string) env('SANDBOX_BASE_URL_TEST', 'https://test-api.sandbox.co.in'), '/');

        if (str_starts_with($apiKey, 'key_live')) {
            $baseUrl = rtrim((string) env('SANDBOX_BASE_URL_LIVE', 'https://api.sandbox.co.in'), '/');
        } elseif (str_starts_with($apiKey, 'key_test')) {
            $baseUrl = rtrim((string) env('SANDBOX_BASE_URL_TEST', 'https://test-api.sandbox.co.in'), '/');
        }

        return [
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'base_url' => $baseUrl,
            'mode' => $isLive ? 'live' : 'test',
        ];
    }
}
