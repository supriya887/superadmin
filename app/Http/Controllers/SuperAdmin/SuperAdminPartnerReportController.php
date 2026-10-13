<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperAdminPartnerReportController extends Controller
{
    public function index(Request $request)
    {
        $securityThreats = DB::table('partner_login_logs')
            ->where('action', 'suspicious_activity')
            ->orderByDesc('created_at')
            ->paginate(15, ['*'], 'threats_page');

        $authLogs = DB::table('partner_login_logs')
            ->leftJoin('partners', 'partner_login_logs.partner_id', '=', 'partners.id')
            ->select('partner_login_logs.*', 'partners.name as partner_name')
            ->where('partner_login_logs.action', '!=', 'suspicious_activity')
            ->orderByDesc('partner_login_logs.created_at')
            ->paginate(15, ['*'], 'auth_page');

        $otpHistory = DB::table('partner_otps')
            ->join('partners', 'partner_otps.partner_id', '=', 'partners.id')
            ->select(
                'partner_otps.*',
                'partners.name as partner_name',
                'partners.email as partner_email'
            )
            ->orderByDesc('partner_otps.created_at')
            ->paginate(15, ['*'], 'otp_page');

        return view('superadmin.reports.partner_logs', compact('securityThreats', 'authLogs', 'otpHistory'));
    }
}
