<?php

namespace App\Http\Controllers\SuperAdmin\Crm;

use App\Http\Controllers\Controller;
use App\Models\CrmUser;
use App\Models\Lead;
use App\Models\Quotation;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();

        $totalLeads = Lead::count();

        $pendingFollowUps = Lead::query()
            ->whereNotNull('follow_up_date')
            ->whereDate('follow_up_date', '>=', $today)
            ->count();

        $newLeadAssignments = Lead::query()
            ->whereNull('assigned_to')
            ->count();

        $pendingQuotations = Quotation::where('invoice_status', 'pending')->count();
        $meetingsScheduled = 0;
        $overdueTasks = Lead::query()
            ->whereNotNull('follow_up_date')
            ->whereDate('follow_up_date', '<', $today)
            ->count();
        $team = CrmUser::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'role'])
            ->map(function ($member) {
                $leads = Lead::query()->forEmployee($member);
                $leadIds = (clone $leads)->pluck('id');
                $deals = Quotation::query()
                    ->where(function ($query) use ($member, $leadIds) {
                        $query->whereIn('assigned_to', Lead::assigneeKeys($member));
                        if ($leadIds->isNotEmpty()) {
                            $query->orWhereIn('lead_id', $leadIds);
                        }
                    })
                    ->count();

                return (object) [
                    'name' => $member->name,
                    'role' => ucfirst(str_replace('_', ' ', $member->role ?? '')),
                    'leads' => $leadIds->count(),
                    'calling' => (clone $leads)->whereNotNull('follow_up_date')->count(),
                    'deals' => $deals,
                    'conv' => $leadIds->isNotEmpty() ? round(($deals / $leadIds->count()) * 100) : 0,
                ];
            });
        $discountApprovals = Quotation::where('discount_status', 'pending')->count();
        $quotationApprovals = $pendingQuotations;
        $leadAssignmentRequests = 0;
        $recentActivities = Lead::query()
            ->latest('updated_at')
            ->take(5)
            ->get(['name', 'company_name', 'created_at', 'updated_at'])
            ->map(function ($lead) {
                $timestamp = $lead->updated_at ?? $lead->created_at;
                $isNew = $lead->created_at && $lead->updated_at
                    && $lead->created_at->equalTo($lead->updated_at);

                return (object) [
                    'message' => ($isNew ? 'Lead created for ' : 'Lead updated for ')
                        . ($lead->company_name ?: $lead->name),
                    'time' => $timestamp ? $timestamp->diffForHumans() : '',
                ];
            });
        $userName = session('userName', 'Admin');

        return view('superadmin.crm.dashboard', compact(
            'userName',
            'totalLeads',
            'pendingFollowUps',
            'pendingQuotations',
            'meetingsScheduled',
            'overdueTasks',
            'team',
            'discountApprovals',
            'quotationApprovals',
            'newLeadAssignments',
            'leadAssignmentRequests',
            'recentActivities'
        ));
    }

    public function reports()
    {
        return view('superadmin.crm.reports');
    }
}
