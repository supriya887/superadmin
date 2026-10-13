<?php

namespace App\Http\Controllers\SuperAdmin\Crm\Api;

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

        // Total Leads
        $totalLeads = Lead::count();

        // Pending Follow Ups
        $pendingFollowUps = Lead::query()
            ->whereNotNull('follow_up_date')
            ->whereDate('follow_up_date', '>=', $today)
            ->count();

        // New / Unassigned Leads
        $newLeadAssignments = Lead::query()
            ->whereNull('assigned_to')
            ->count();

        // Pending Quotations
        $pendingQuotations = Quotation::where(
            'invoice_status',
            'pending'
        )->count();

        // Meetings
        $meetingsScheduled = 0;

        // Overdue Tasks
        $overdueTasks = Lead::query()
            ->whereNotNull('follow_up_date')
            ->whereDate('follow_up_date', '<', $today)
            ->count();

        // Team Performance
        $team = CrmUser::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'role'])
            ->map(function ($member) {

                $leads = Lead::query()
                    ->forEmployee($member);

                $leadIds = (clone $leads)->pluck('id');

                $deals = Quotation::query()
                    ->where(function ($query) use ($member, $leadIds) {

                        $query->whereIn(
                            'assigned_to',
                            Lead::assigneeKeys($member)
                        );

                        if ($leadIds->isNotEmpty()) {
                            $query->orWhereIn(
                                'lead_id',
                                $leadIds
                            );
                        }
                    })
                    ->count();

                return [
                    'name' => $member->name,

                    'role' => ucfirst(
                        str_replace(
                            '_',
                            ' ',
                            $member->role ?? ''
                        )
                    ),

                    'leads' => $leadIds->count(),

                    'calling' => (clone $leads)
                        ->whereNotNull('follow_up_date')
                        ->count(),

                    'deals' => $deals,

                    'conversion' => $leadIds->isNotEmpty()
                        ? round(
                            ($deals / $leadIds->count()) * 100
                        )
                        : 0,
                ];
            })
            ->values();

        // Discount Approvals
        $discountApprovals = Quotation::where(
            'discount_status',
            'pending'
        )->count();

        // Quotation Approvals
        $quotationApprovals = $pendingQuotations;

        // Lead Assignment Requests
        $leadAssignmentRequests = 0;

        // Recent Activities
        $recentActivities = Lead::query()
            ->latest('updated_at')
            ->take(5)
            ->get([
                'name',
                'company_name',
                'created_at',
                'updated_at'
            ])
            ->map(function ($lead) {

                $timestamp = $lead->updated_at ?? $lead->created_at;

                $isNew = $lead->created_at
                    && $lead->updated_at
                    && $lead->created_at->equalTo(
                        $lead->updated_at
                    );

                return [
                    'message' => (
                        $isNew
                            ? 'Lead created for '
                            : 'Lead updated for '
                    ) . (
                        $lead->company_name
                            ?: $lead->name
                    ),

                    'time' => $timestamp
                        ? $timestamp->diffForHumans()
                        : '',
                ];
            })
            ->values();

        // Logged-in CRM User
        $user = auth('crm')->user();

        return response()->json([
            'success' => true,
            'message' => 'CRM dashboard data fetched successfully.',

            'data' => [
                'user' => [
                    'id' => $user?->id,
                    'name' => $user?->name ?? 'Admin',
                    'role' => $user?->role,
                ],

                'summary' => [
                    'total_leads' => $totalLeads,
                    'pending_follow_ups' => $pendingFollowUps,
                    'new_lead_assignments' => $newLeadAssignments,
                    'pending_quotations' => $pendingQuotations,
                    'meetings_scheduled' => $meetingsScheduled,
                    'overdue_tasks' => $overdueTasks,
                    'discount_approvals' => $discountApprovals,
                    'quotation_approvals' => $quotationApprovals,
                    'lead_assignment_requests' => $leadAssignmentRequests,
                ],

                'team' => $team,

                'recent_activities' => $recentActivities,
            ],
        ]);
    }
}