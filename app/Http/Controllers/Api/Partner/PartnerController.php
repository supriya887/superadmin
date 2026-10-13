<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\PartnerService;

class PartnerController extends Controller
{
    protected $partnerService;

    public function __construct(PartnerService $partnerService)
    {
        $this->partnerService = $partnerService;
    }

    public function me(Request $request)
    {
        return $this->successResponse('Profile fetched.', $request->user());
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $partner = $this->partnerService->authenticate($request->email, $request->password);

        if ($partner) {
            $token = $partner->createToken($request->device_name ?: 'android-app')->plainTextToken;
            return $this->successResponse('Login successful.', [
                'token' => $token,
                'partner' => $partner
            ]);
        }

        return $this->errorResponse('Invalid email or password.', 401);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:pgsql_auth.partners,email',
            'password' => 'required|min:8|confirmed',
            'mobile' => 'required|string',
            'gstin' => ['required','string','size:15','regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/'],
            'company_name' => 'required|string',
            'company_address' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            $partner = $this->partnerService->register($request->all());
            return $this->successResponse('Registration successful. OTP sent to your email.', $partner, 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Registration failed: ' . $e->getMessage(), 500);
        }
    }

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:pgsql_auth.partners,email'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            $partner = $this->partnerService->forgotPassword($request->email);
            return $this->successResponse('An OTP has been sent to your email to reset your password.', ['email' => $partner->email]);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to send OTP email: ' . $e->getMessage(), 500);
        }
    }

    public function verifyResetOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            $partner = $this->partnerService->verifyResetOtp($request->email, $request->otp);
            return $this->successResponse('OTP verified successfully.', ['email' => $partner->email]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            $this->partnerService->resetPassword($request->email, $request->password);
            return $this->successResponse('Your password has been reset successfully!');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            $this->partnerService->sendOtp($request->email);
            return $this->successResponse('OTP sent successfully.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|digits:6'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        try {
            $this->partnerService->verifyOtp($request->email, $request->otp);
            return $this->successResponse('Email verified successfully.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return $this->successResponse('Logged out successfully.');
    }
}
