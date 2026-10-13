<?php

namespace App\Http\Controllers\SuperAdmin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

// Import Eloquent models
use App\Models\AdminUserCreation;
use App\Models\AdminUserSession;
use App\Models\AdminUserRole;
use App\Models\OtherFinancialYear;
use App\Services\SupportAgentPresenceService;

class LoginController extends Controller
{
    public function show()
    {
        if (session('userId')) {
            return redirect()->route('superadmin.dashboard');
        }

        return view('superadmin.auth.login');
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            $error = $validator->errors();
            return response()->json([
                'success' => false,
                'error'   => true,
                'message' => $error->first(),
                'data'    => null,
            ]);
        }

        $user = AdminUserCreation::where('user_name', $request->username)
            ->where('password', $request->password)
            ->where('status', 1)
            ->first();

        if (empty($user)) {
            return response()->json([
                'success' => false,
                'error'   => true,
                'message' => 'Please enter a valid username and password.',
                'data'    => null,
            ]);
        }

        $roleId = 0;
        $roleName = null;
        $roleRaw = $user->role;

        // Numeric role id on admin_user_creation.role
        if ($roleRaw !== null && $roleRaw !== '' && is_numeric($roleRaw)) {
            $roleId = (int) $roleRaw;
            $role = AdminUserRole::query()->whereKey($roleId)->first();
            if ($role) {
                $roleName = trim((string) ($role->role_name ?? $role->rolevalue ?? ''));
            } else {
                $roleId = 0;
            }
        } elseif (is_string($roleRaw) && trim($roleRaw) !== '') {
            // Legacy: role stored as name / rolevalue
            $roleKey = trim($roleRaw);
            $role = AdminUserRole::query()
                ->whereRaw('LOWER(TRIM(role_name)) = ?', [strtolower($roleKey)])
                ->orWhereRaw('LOWER(TRIM(COALESCE(rolevalue, \'\'))) = ?', [strtolower($roleKey)])
                ->first();
            if ($role) {
                $roleId = (int) $role->id;
                $roleName = trim((string) ($role->role_name ?? $role->rolevalue ?? $roleKey));
            }
        }

        // Legacy SUPERADMIN / admin users with blank role → map to super_admin role
        if ($roleId <= 0) {
            $role = AdminUserRole::query()
                ->where(function ($q) {
                    $q->whereRaw('LOWER(TRIM(role_name)) = ?', ['super_admin'])
                        ->orWhereRaw('LOWER(TRIM(COALESCE(rolevalue, \'\'))) = ?', ['super_admin']);
                })
                ->orderBy('id')
                ->first();
            if ($role) {
                $roleId = (int) $role->id;
                $roleName = trim((string) ($role->role_name ?? 'super_admin'));
            }
        }

        if ($roleId <= 0) {
            return response()->json([
                'success' => false,
                'error'   => true,
                'message' => 'No role assigned to this user. Please assign a role and try again.',
                'data'    => null,
            ]);
        }

        if ($roleName === '') {
            $roleName = 'admin';
        }

        $session = new AdminUserSession();
        $session->user_id    = $user->id;
        $session->logged_in  = date('Y-m-d H:i:s');
        $session->ip_address = $request->getClientIp();
        $session->save();
        $sessionId = $session->id;

        $finyear = OtherFinancialYear::select('id')
            ->where('status', 1)
            ->first();

        session([
            'userId'    => $user->id,
            'userName'  => $user->user_name,
            'userRole'  => $roleName,
            'roleId'    => $roleId,
            'sessionId' => $sessionId,
            'finyearId' => $finyear->id ?? null,
        ]);

        app(SupportAgentPresenceService::class)->touch((int) $user->id, true);

        return response()->json([
            'success'  => true,
            'error'    => false,
            'message'  => 'Login successfully.',
            'redirect' => route('superadmin.dashboard'),
        ]);
    }

    public function logout()
    {
        // Close the session row and mark the agent offline before clearing the session.
        $userId = (int) session('userId', 0);
        $sessionId = (int) session('sessionId', 0);
        if ($sessionId > 0) {
            AdminUserSession::whereKey($sessionId)->update(['logout' => date('Y-m-d H:i:s')]);
        }
        app(SupportAgentPresenceService::class)->markLoggedOut($userId);

        Session::flush();

        return redirect()->route('superadmin.login')->with('success', 'Logged out successfully.');
    }
}
