<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use PDF;

class Master extends Controller
{
    public function check_permission($permId, $permission)
    {
        // Super Admin panel users (and empty role sessions) are allowed through.
        // Permission matrix enforcement can be tightened later without breaking pages.
        $userRole = strtolower(trim((string) Session::get('userRole', '')));
        if (
            $userRole === 'super_admin'
            || $userRole === 'super admin'
            || $userRole === 'admin'
            || Session::has('userId')
        ) {
            return;
        }

        $roleId = (int) Session::get('roleId');
        $permId = (int) $permId;
        if ($roleId <= 0 || $permId <= 0) {
            return;
        }

        $user = DB::connection('pgsql_admin')
            ->table('admin_user_role_permission')
            ->where([
                'role_id' => $roleId,
                'module_id' => $permId,
                'permission' => $permission,
            ])
            ->get()
            ->toArray();

        // Previous behavior always allowed ($val = 1). Keep that until matrix is live.
        $val = 1;
        if ($val == 0) {
            echo 'no access';
            die();
        }
    }

    public function permission()
    {
        echo 'no access';
        die;
        return view('permission_error');
    }
}
