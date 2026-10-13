<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use App\Models\Partner\Partner;


class LoginController extends Controller
{


    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'nullable'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => true,
                'message' => $validator->errors()->first()
            ]);
        }

        $isPartnerUser = false;
        $partner = Partner::where('email', $request->email)->first();

        if (!$partner) {
            // Check PartnerUser if main partner not found
            $partner = \App\Models\PartnerUser::where('email', $request->email)->first();
            if ($partner) {
                $isPartnerUser = true;
            } else {
                return response()->json([
                    'success' => false,
                    'error' => true,
                    'message' => 'User not found'
                ]);
            }
        }

        $passwordOk = false;

        // Hashed password check
        if (!empty($partner->password) && Hash::check($request->password, $partner->password)) {
            $passwordOk = true;
        }

        // Plain password fallback
        if (
            !$passwordOk &&
            !empty($partner->password) &&
            $partner->password === $request->password
        ) {
            $partner->update([
                'password' => Hash::make($request->password),
            ]);
            $passwordOk = true;
        }

        if (!$passwordOk) {
            return response()->json([
                'success' => false,
                'error' => true,
                'message' => 'Invalid email or password'
            ], 401);
        }

        $partner->tokens()->delete();
        $tokenResult = $partner->createToken($request->device_name ?? 'phone');
        $partner->update(['current_session_token' => (string) $tokenResult->accessToken->id]);
        $token = $tokenResult->plainTextToken;

        return response()->json([
            'success' => true,
            'error' => false,
            'message' => 'Logged in successfully',
            'token' => $token,
            'user' => [
                'id' => $isPartnerUser ? $partner->partner_id : $partner->id,
                'name' => $partner->name,
                'email' => $partner->email,
                'partner_user_id' => $isPartnerUser ? $partner->id : null,
            ]
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'success' => true,
            'error' => false,
            'message' => 'User fetched successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'error' => false,
            'message' => 'Logged out successfully.',
            'data' => null
        ]);
    }
}

