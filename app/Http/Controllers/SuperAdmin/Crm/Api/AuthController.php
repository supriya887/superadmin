<?php

namespace App\Http\Controllers\SuperAdmin\Crm\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmPersonalAccessToken;
use App\Models\CrmUser;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $email = strtolower(trim($request->email));

        $user = CrmUser::whereRaw('LOWER(email) = ?', [$email])->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        if (!$user->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is ' . $user->status . '.',
            ], 403);
        }

        $user->update([
            'last_login_at' => now(),
            'ip_address' => $request->ip(),
        ]);

        // Create CRM token in pgsql_crm.
        $token = $this->createCrmToken($user);

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'mobile' => $user->mobile,
                    'role' => $user->role,
                    'status' => $user->status,
                ],
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    /**
     * Create CRM Sanctum token using pgsql_crm.
     */
    private function createCrmToken(CrmUser $user): NewAccessToken
    {
        $plainTextToken = Str::random(40);

        $accessToken = new CrmPersonalAccessToken();

        $accessToken->forceFill([
            'tokenable_type' => CrmUser::class,
            'tokenable_id' => $user->getKey(),
            'name' => 'crm-auth-token',
            'token' => hash('sha256', $plainTextToken),
            'abilities' => ['*'],
        ]);

        $accessToken->save();

        return new NewAccessToken(
            $accessToken,
            $accessToken->getKey() . '|' . $plainTextToken
        );
    }

    public function logout(Request $request)
    {
        try {
            $accessToken = CrmPersonalAccessToken::findFromRequest($request);

            if ($accessToken) {
                $accessToken->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully.',
                'data' => null,
            ], 200);

        } catch (\Exception $e) {
            Log::error('CRM Logout Error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong: ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    public function forgotPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:pgsql_crm.crm_users,email',
            ], [
                'email.exists' => 'No account found with this email address.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'data' => null,
                ], 422);
            }

            $user = CrmUser::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No account found with this email address.',
                    'data' => null,
                ], 404);
            }

            if (!$user->isActive()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account is ' . $user->status . '. Please contact administrator.',
                    'data' => null,
                ], 403);
            }

            $status = Password::broker('crm_users')->sendResetLink([
                'email' => $request->email,
            ]);

            if ($status === Password::RESET_LINK_SENT) {
                return response()->json([
                    'success' => true,
                    'message' => 'Password reset link sent to your email.',
                    'data' => null,
                ], 200);
            }

            Log::warning('CRM Forgot Password Failed', [
                'email' => $request->email,
                'status' => $status,
            ]);

            return response()->json([
                'success' => false,
                'message' => __($status),
                'data' => null,
            ], 400);

        } catch (\Exception $e) {
            Log::error('CRM Forgot Password Error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong: ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:pgsql_crm.crm_users,email',
                'token' => 'required|string',
                'password' => 'required|string|min:8|confirmed',
            ], [
                'email.exists' => 'No account found with this email address.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                    'data' => null,
                ], 422);
            }

            $user = CrmUser::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No account found with this email address.',
                    'data' => null,
                ], 404);
            }

            $status = Password::broker('crm_users')->reset(
                [
                    'email' => $request->email,
                    'password' => $request->password,
                    'password_confirmation' => $request->password_confirmation,
                    'token' => $request->token,
                ],
                function ($user, $password) {
                    $user->forceFill([
                        'password' => Hash::make($password),
                        'remember_token' => Str::random(60),
                    ])->save();

                    event(new PasswordReset($user));
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                return response()->json([
                    'success' => true,
                    'message' => 'Password reset successfully. You can now login with your new password.',
                    'data' => null,
                ], 200);
            }

            Log::warning('CRM Password Reset Failed', [
                'email' => $request->email,
                'status' => $status,
            ]);

            return response()->json([
                'success' => false,
                'message' => __($status),
                'data' => null,
            ], 400);

        } catch (\Exception $e) {
            Log::error('CRM Reset Password Error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong: ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    public function profile(Request $request)
    {
        try {
            $user = $request->user('crm');

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                    'data' => null,
                ], 401);
            }

            return response()->json([
                'success' => true,
                'message' => 'Profile retrieved successfully.',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'mobile' => $user->mobile,
                    'role' => $user->role,
                    'status' => $user->status,
                    'last_login_at' => $user->last_login_at,
                    'created_at' => $user->created_at,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('CRM Profile Error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong: ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }
}