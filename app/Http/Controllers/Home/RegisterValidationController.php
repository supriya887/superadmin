<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Mail\UserOtpMail;   
use App\Models\EmailOtp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Api\PartnerAuthController;

class RegisterValidationController extends Controller
{
    protected $PartnerAuthController;
    
    public function __construct() {
        $this->PartnerAuthController = new PartnerAuthController();
    }

    
    public function register_validation(Request $request)
    {
        DB::beginTransaction();

        try {

            // Validation
            $validator = Validator::make(
                $request->all(),
                [
                    'name' => 'required|string|max:255',
                    'email' => 'required|string|email|max:255|unique:users,email',
                    'mobile' => 'required|digits:10',
                    'gstin' => [
                        'required',
                        'string',
                        'size:15',
                        'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/'
                    ],
                    'pan_no' => ['string','size:10','regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/'],
                    'company_name' => 'string|max:255',
                    'company_address' => 'string|max:500',
                    'country' => 'string',  
                    'state' => 'string',
                    'city' => 'string',
                    'zip_code'=>'string',
                    'terms_and_conditions' => 'accepted',
                ],
                [
                    'mobile.digits' => 'Mobile number must be exactly 10 digits.',
                    'gstin.regex' => 'Please enter a valid 15-character GSTIN.',
                    'terms_and_conditions.required' => 'You must accept the Terms & Conditions.',
                ]
            );

            if ($validator->fails()) {
                $errors = $validator->errors();
                return response()->json([
                    'error' => true,
                    'name' => $errors->first('name'),
                    'email' => $errors->first('email'),
                    'mobile' => $errors->first('mobile'),
                    'pan_no' => $errors->first('pan_no'),
                    'company_name' => $errors->first('company_name'),
                    'company_address' => $errors->first('company_address'),
                    'gstin' => $errors->first('gstin'),
                    'country' => $errors->first('country'),
                    'state' => $errors->first('state'),
                    'city' => $errors->first('city'),
                    'zip_code' => $errors->first('zip_code'),
                    'terms_and_conditions' => $errors->first('terms_and_conditions'),
                ], 422);
            }

            // Create user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'mobile' => $request->mobile,
                'gstin' => strtoupper($request->gstin),
                'pan_no' => strtoupper($request->pan_no),
                'company_name' => $request->company_name,
                'company_address' => $request->company_address,
                'country' => $request->country,
                'state' => $request->state,
                'city' => $request->city,
                'zip_code' => $request->zip_code,
                'terms_and_conditions' => true,
                'email_verified' => false,
                'status' => 0,
                'password' => null,
            ]);


            $this->PartnerAuthController->createAndSendOtp_for_user($user, 'verify_email', 10);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Registration successful. OTP sent to email.',
                'data' => $user
            ], 201);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        try {
            $validator = Validator::make(
                $request->all(),
                [
                    'email' => 'required|string|email',
                ]
            );
            if ($validator->fails()) {
                $errors = $validator->errors();
                return response()->json([
                    'error' => true,
                    'email' => $errors->first('email'),
                ], 422);
            }

            $user = User::where('email', $request->email)->first();
            if (!$user) {
                return response()->json([
                    'error' => true,
                    'message' => 'User not found.',
                ], 404);
            }

            DB::beginTransaction();

            $this->PartnerAuthController->createAndSendOtp_for_user($user, 10);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'OTP sent to email.',
                'data' => [
                    'email' => $user->email,
                ],
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function verifyLoginOtp(Request $request)
    {
        try {
            $validator = Validator::make(
                $request->all(),
                [
                    'email' => 'required|string|email',
                    'otp' => 'required|numeric|digits:6',
                ]
            );

            if ($validator->fails()) {
                $errors = $validator->errors();
                return response()->json([
                    'error' => true,
                    'email' => $errors->first('email'),
                    'otp' => $errors->first('otp'),
                ], 422);
            }

            $user = User::where('email', $request->email)->first();
            if (!$user) {
                return response()->json([
                    'error' => true,
                    'message' => 'User not found.',
                ], 404);
            }

            DB::beginTransaction();

            $otpRecord = EmailOtp::where('user_id', $user->id)
                ->where('otp', $request->otp)
                ->where('verified', false)
                ->where('expires_at', '>', now())
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$otpRecord) {
                DB::rollBack();
                return response()->json([
                    'error' => true,
                    'message' => 'Invalid or expired OTP. Please try again.',
                ], 400);
            }

            $otpRecord->update(['verified' => true]);

            Auth::login($user);

            // Only try to regenerate session if the request has a session (avoid "Session store not set on request")
            if ($request->hasSession()) {
                $request->session()->regenerate();

                // Session keys (used by web views / legacy flows)
                session([
                    'user_id' => $user->id,
                    'userName' => $user->name,
                    'userRole' => 'User',
                ]);
            }

            DB::commit();

            return response()
                ->json([
                    'success' => true,
                    'message' => 'Login Successful.',
                    'data' => [
                        'user' => $user,
                    ],
                ], 200)
                ->cookie('user_id', (string) $user->id, 60 * 24 * 30)
                ->cookie('user_email', (string) $user->email, 60 * 24 * 30);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}