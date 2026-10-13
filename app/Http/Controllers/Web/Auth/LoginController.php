<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(private AuthService $authService)
    {
    }

    public function show(): View
    {
        return view('web.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $result = $this->authService->attemptWebLogin($validated);

        if (!$result['success']) {
            return back()->withInput()->withErrors(['email' => $result['message']]);
        }

        return redirect()->route('web.auth.dashboard')->with('success', $result['message']);
    }

    public function dashboard(): View
    {
        return view('web.auth.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('web.auth.login')->with('success', 'Logged out successfully.');
    }
}

