<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function attemptWebLogin(array $credentials): array
    {
        if (!Auth::attempt($credentials)) {
            return [
                'success' => false,
                'message' => 'Invalid email or password.',
                'data' => null,
            ];
        }

        request()->session()->regenerate();

        return [
            'success' => true,
            'message' => 'Login successful.',
            'data' => Auth::user(),
        ];
    }

    public function attemptApiLogin(array $credentials, string $deviceName = 'android-app'): array
    {
        $user = User::where('email', $credentials['email'] ?? '')->first();

        if (!$user || !Hash::check($credentials['password'] ?? '', $user->password)) {
            return [
                'success' => false,
                'message' => 'Invalid email or password.',
                'data' => null,
            ];
        }

        $token = $user->createToken($deviceName)->plainTextToken;

        return [
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ];
    }

    public function apiLogout($user): void
    {
        $user->currentAccessToken()?->delete();
    }
}

