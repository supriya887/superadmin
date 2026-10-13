<?php

namespace App\Http\Controllers\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperAdminApiController extends BaseSuperAdminController
{
    public function dashboard(Request $request)
    {
        $this->authorizeModule(1, 'View');

        return response()->json([
            'success' => true,
            'error' => false,
            'message' => 'Super admin dashboard data fetched successfully',
            'data' => [
                'user' => $request->user(),
            ],
        ], 200);
    }

    public function users(Request $request)
    {
        $this->authorizeModule(1, 'View');

        $users = DB::table('admin_user_creation')
            ->select('id', 'user_name', 'role', 'status')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'error' => false,
            'message' => 'Users fetched successfully',
            'data' => $users,
        ], 200);
    }
}

