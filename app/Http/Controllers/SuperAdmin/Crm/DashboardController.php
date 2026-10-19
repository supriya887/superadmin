<?php

namespace App\Http\Controllers\SuperAdmin\Crm;

use App\Http\Controllers\Controller;
use App\Models\CrmUser;
use App\Models\Lead;
use App\Models\Quotation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

    public function reports(Request $request)
    {
        $today = Carbon::today();
        $leads = Lead::query()->get(['id', 'assigned_to', 'lead_source', 'lead_status', 'follow_up_date', 'company_name', 'name']);
        $quotes = Quotation::query()->get([
            'id', 'lead_id', 'assigned_to', 'company_name', 'total_amount', 'discount',
            'quotation_status', 'invoice_status', 'sent_at', 'created_at',
            'valid_date', 'follow_up_date', 'responded_at', 'deal_type',
        ]);

        $won = $quotes->filter(fn ($quote) => $this->quoteWon($quote));
        $lost = $quotes->filter(fn ($quote) => strtolower(trim((string) $quote->quotation_status)) === 'rejected');
        $open = $quotes->reject(fn ($quote) => $this->quoteWon($quote) || strtolower(trim((string) $quote->quotation_status)) === 'rejected');
        $sent = $open->filter(fn ($quote) => $quote->sent_at);
        $wonAmount = (float) $won->sum('total_amount');
        $pipelineAmount = (float) $open->sum('total_amount');
        $expectedAmount = (float) $sent->sum('total_amount');
        $closed = $won->count() + $lost->count();
        $winRate = $closed > 0 ? round(($won->count() / $closed) * 100, 1) : 0;
        $target = ($wonAmount + $pipelineAmount) > 0
            ? round(($wonAmount / ($wonAmount + $pipelineAmount)) * 100)
            : 0;

        $followups = collect();
        if (Schema::connection('pgsql_crm')->hasTable('lead_followups')) {
            $followups = DB::connection('pgsql_crm')->table('lead_followups')
                ->select('lead_id', DB::raw('count(*) as total'))
                ->groupBy('lead_id')
                ->pluck('total', 'lead_id');
        }

        $colors = ['purple', 'teal', 'blue', 'orange', 'green', 'pink'];
        $search = strtolower(trim((string) $request->input('search', '')));
        $employees = CrmUser::query()->orderBy('name')->get(['id', 'name', 'role'])
            ->values()
            ->map(function ($member, $index) use ($leads, $quotes, $followups, $colors) {
                $keys = array_map('strtolower', Lead::assigneeKeys($member));
                $mine = $leads->filter(function ($lead) use ($keys) {
                    $assigned = strtolower(trim((string) $lead->assigned_to));

                    return $assigned !== '' && in_array($assigned, $keys, true);
                });
                $leadIds = $mine->pluck('id');
                $deals = $quotes->filter(function ($quote) use ($leadIds, $keys) {
                    if ($quote->lead_id && $leadIds->contains($quote->lead_id)) {
                        return true;
                    }

                    return in_array(strtolower(trim((string) $quote->assigned_to)), $keys, true);
                });
                $wonDeals = $deals->filter(fn ($quote) => $this->quoteWon($quote));
                $leadCount = $mine->count();
                $calls = $leadIds->sum(fn ($id) => (int) ($followups[$id] ?? $followups[(string) $id] ?? 0));

                return [
                    'name' => $member->name ?: 'Employee',
                    'role' => ucfirst(str_replace('_', ' ', (string) $member->role)),
                    'leads' => $leadCount,
                    'calls' => $calls,
                    'followups' => $mine->whereNotNull('follow_up_date')->count(),
                    'opps' => $deals->count(),
                    'won' => $wonDeals->count(),
                    'revenue' => $this->money($wonDeals->sum('total_amount')),
                    'conv' => $leadCount > 0 ? round(($wonDeals->count() / $leadCount) * 100, 1) . '%' : '0%',
                    'color' => $colors[$index % count($colors)],
                ];
            })
            ->when($search !== '', function ($rows) use ($search) {
                return $rows->filter(fn ($row) => str_contains(strtolower($row['name'] . ' ' . $row['role']), $search));
            })
            ->sortByDesc('won')
            ->values();

        $monthStart = Carbon::now()->startOfMonth()->subMonths(11);
        $monthTotals = [];
        for ($i = 0; $i < 12; $i++) {
            $point = $monthStart->copy()->addMonths($i);
            $monthTotals[$point->format('Y-m')] = ['label' => $point->format('M'), 'amount' => 0];
        }
        foreach ($won as $quote) {
            $when = $quote->responded_at ?: $quote->created_at;
            if (!$when) {
                continue;
            }
            $key = Carbon::parse($when)->format('Y-m');
            if (isset($monthTotals[$key])) {
                $monthTotals[$key]['amount'] += (float) $quote->total_amount;
            }
        }
        $peak = max(1, collect($monthTotals)->max('amount'));
        $months = collect($monthTotals)->map(fn ($row) => [
            'label' => $row['label'],
            'h' => round(($row['amount'] / $peak) * 100),
        ])->values();

        $sourceGroups = $leads->groupBy(fn ($lead) => trim((string) $lead->lead_source) ?: 'Unknown')
            ->map->count()
            ->sortDesc();
        $topSource = $sourceGroups->keys()->first() ?: '—';
        $topSourceCount = (int) $sourceGroups->first();
        $topSourceShare = $leads->count() > 0 ? round(($topSourceCount / $leads->count()) * 100) : 0;
        $topEmployee = $employees->sortByDesc('won')->first();
        $biggest = $quotes->sortByDesc(fn ($quote) => (float) $quote->total_amount)->first();
        $biggestWon = $won->sortByDesc(fn ($quote) => (float) $quote->total_amount)->first();
        $overdue = $leads->filter(fn ($lead) => $lead->follow_up_date && Carbon::parse($lead->follow_up_date)->lt($today))->count();
        $unassigned = $leads->filter(fn ($lead) => trim((string) $lead->assigned_to) === '')->count();
        $renewalsDue = $open->filter(fn ($quote) => ($quote->deal_type ?: 'new') === 'renewal')->count();
        $discounted = $quotes->filter(fn ($quote) => (float) $quote->discount > 0)->count();

        $thisMonth = $open->filter(function ($quote) use ($today) {
            $date = $quote->follow_up_date ?: $quote->valid_date;

            return $date && Carbon::parse($date)->isSameMonth($today);
        });
        $thisQuarter = $open->filter(function ($quote) use ($today) {
            $date = $quote->follow_up_date ?: $quote->valid_date;

            return $date && Carbon::parse($date)->isSameQuarter($today);
        });
        $forecastBase = max($pipelineAmount, 1);
        $forecast = [
            ['label' => 'Current Pipeline', 'value' => $this->money($pipelineAmount), 'pct' => $pipelineAmount > 0 ? 100 : 0, 'tone' => 'navy'],
            ['label' => 'Expected Revenue', 'value' => $this->money($expectedAmount), 'pct' => min(100, round(($expectedAmount / $forecastBase) * 100)), 'tone' => 'blue'],
            ['label' => 'Still Valid', 'value' => $this->money($open->filter(fn ($quote) => $quote->valid_date && Carbon::parse($quote->valid_date)->gte($today))->sum('total_amount')), 'pct' => min(100, round(($open->filter(fn ($quote) => $quote->valid_date && Carbon::parse($quote->valid_date)->gte($today))->sum('total_amount') / $forecastBase) * 100)), 'tone' => 'sky'],
            ['label' => 'Closing This Month', 'value' => $this->money($thisMonth->sum('total_amount')), 'pct' => min(100, round(($thisMonth->sum('total_amount') / $forecastBase) * 100)), 'tone' => 'teal'],
            ['label' => 'Closing This Quarter', 'value' => $this->money($thisQuarter->sum('total_amount')), 'pct' => min(100, round(($thisQuarter->sum('total_amount') / $forecastBase) * 100)), 'tone' => 'purple'],
        ];

        $categories = [
            ['icon' => 'trending-up', 'title' => 'Sales Performance', 'desc' => 'Won, lost and open quotation value.', 'tone' => 'teal', 'href' => route('crm.reports.show', 'sales'), 'links' => [
                ['text' => 'Pipeline (' . $open->count() . ')', 'href' => route('crm.reports.show', 'pipeline')],
                ['text' => 'Won deals (' . $won->count() . ')', 'href' => route('crm.reports.show', 'won')],
                ['text' => 'Lost deals (' . $lost->count() . ')', 'href' => route('crm.reports.show', 'lost')],
            ]],
            ['icon' => 'users', 'title' => 'Lead Performance', 'desc' => 'Lead volume, source and assignment.', 'tone' => 'purple', 'href' => route('crm.reports.show', 'leads'), 'links' => [
                ['text' => 'Top source (' . $topSourceCount . ')', 'href' => route('crm.reports.show', 'sources')],
                ['text' => 'Conversion (' . $winRate . '%)', 'href' => route('crm.reports.show', 'conversion')],
                ['text' => 'Unassigned (' . $unassigned . ')', 'href' => route('crm.reports.show', 'unassigned')],
            ]],
            ['icon' => 'briefcase', 'title' => 'Deal / Opportunity', 'desc' => 'Open quotations still in the pipeline.', 'tone' => 'orange', 'href' => route('crm.reports.show', 'deals'), 'links' => [
                ['text' => 'By stage (' . $open->count() . ')', 'href' => route('crm.reports.show', 'deals')],
                ['text' => 'This month (' . $thisMonth->count() . ')', 'href' => route('crm.reports.show', 'deals-month')],
                ['text' => 'This quarter (' . $thisQuarter->count() . ')', 'href' => route('crm.reports.show', 'deals-quarter')],
            ]],
            ['icon' => 'file-text', 'title' => 'Quotations', 'desc' => 'Shared, approved and discounted quotations.', 'tone' => 'yellow', 'href' => route('crm.reports.show', 'quotations'), 'links' => [
                ['text' => 'Sent (' . $sent->count() . ')', 'href' => route('crm.reports.show', 'sent')],
                ['text' => 'Approved (' . $won->count() . ')', 'href' => route('crm.reports.show', 'won')],
                ['text' => 'Discounted (' . $discounted . ')', 'href' => route('crm.reports.show', 'discounted')],
            ]],
            ['icon' => 'refresh-cw', 'title' => 'Renewals', 'desc' => 'Open renewal quotations still to close.', 'tone' => 'blue', 'href' => route('crm.reports.show', 'renewals'), 'links' => [
                ['text' => 'Renewal quotes (' . $renewalsDue . ')', 'href' => route('crm.reports.show', 'renewals')],
                ['text' => 'Overdue follow-ups (' . $overdue . ')', 'href' => route('crm.reports.show', 'overdue')],
                ['text' => 'Follow-ups', 'href' => route('crm.reports.show', 'scheduled')],
            ]],
            ['icon' => 'activity', 'title' => 'Activities', 'desc' => 'Follow-ups logged against leads.', 'tone' => 'purple', 'href' => route('crm.reports.show', 'activities'), 'links' => [
                ['text' => 'Logged (' . (int) $followups->sum() . ')', 'href' => route('crm.reports.show', 'activities')],
                ['text' => 'Scheduled (' . $leads->whereNotNull('follow_up_date')->count() . ')', 'href' => route('crm.reports.show', 'scheduled')],
                ['text' => 'Overdue (' . $overdue . ')', 'href' => route('crm.reports.show', 'overdue')],
            ]],
        ];

        $insights = [
            ['label' => 'Top Lead Source', 'value' => $topSource, 'meta' => $topSourceShare . '% of leads', 'tone' => 'blue'],
            ['label' => 'Top Employee', 'value' => $topEmployee['name'] ?? '—', 'meta' => ($topEmployee['won'] ?? 0) . ' won deals', 'tone' => 'teal'],
            ['label' => 'Highest Value Deal', 'value' => $biggest ? $this->money($biggest->total_amount) : '₹0', 'meta' => $biggest ? ($biggest->company_name ?: 'Quotation') : 'No quotations yet', 'tone' => 'purple'],
            ['label' => 'Highest Won Deal', 'value' => $biggestWon ? $this->money($biggestWon->total_amount) : '₹0', 'meta' => $biggestWon ? (($biggestWon->company_name ?: 'Quotation') . ($biggestWon->responded_at ? ' · ' . Carbon::parse($biggestWon->responded_at)->format('d M Y') : '')) : 'No won deals yet', 'tone' => 'orange'],
            ['label' => 'Follow-up Tasks', 'value' => $overdue . ' overdue', 'meta' => $overdue > 0 ? 'Need attention' : 'None overdue', 'tone' => 'yellow'],
        ];

        return view('superadmin.crm.reports', [
            'totalSales' => $this->money($wonAmount),
            'wonDeals' => $won->count(),
            'lostDeals' => $lost->count(),
            'winRate' => $winRate . '%',
            'pipelineValue' => $this->money($pipelineAmount),
            'expectedRevenue' => $this->money($expectedAmount),
            'targetAchievement' => $target . '%',
            'yearRevenue' => $this->money(collect($monthTotals)->sum('amount')),
            'months' => $months,
            'employees' => $employees,
            'categories' => $categories,
            'insights' => $insights,
            'forecast' => $forecast,
        ]);
    }

    public function reportShow(string $report)
    {
        $page = $this->reportPage($report);
        abort_unless($page, 404);

        return view('superadmin.crm.reports.show', $page);
    }

    protected function reportPage(string $report): ?array
    {
        $today = Carbon::today();
        $leads = Lead::query()->get(['id', 'name', 'company_name', 'email', 'lead_source', 'lead_status', 'assigned_to', 'follow_up_date']);
        $quotes = Quotation::query()->get([
            'id', 'quotation_no', 'lead_id', 'company_name', 'name', 'total_amount', 'discount',
            'quotation_status', 'invoice_status', 'sent_at', 'created_at', 'valid_date',
            'follow_up_date', 'deal_type',
        ]);
        $wonIds = $quotes->filter(fn ($quote) => $this->quoteWon($quote))->pluck('lead_id')->filter();

        $quoteRows = function ($items) {
            return $items->sortByDesc('id')->values()->map(function ($quote) {
                $status = $this->quoteWon($quote) ? 'Won' : (strtolower(trim((string) $quote->quotation_status)) === 'rejected' ? 'Lost' : ($quote->sent_at ? 'Sent' : 'Open'));

                return [
                    ['text' => $quote->quotation_no ?: ('#' . $quote->id), 'href' => route('crm.quotations.show', $quote->id)],
                    ['text' => $quote->company_name ?: ($quote->name ?: '—')],
                    ['text' => $status],
                    ['text' => $this->money($quote->total_amount)],
                    ['text' => optional($quote->created_at)->format('d M Y') ?: '—'],
                ];
            });
        };
        $leadRows = function ($items) {
            return $items->sortByDesc('id')->values()->map(function ($lead) {
                return [
                    ['text' => $lead->company_name ?: ($lead->name ?: 'Lead'), 'href' => route('crm.leads.show', $lead->id)],
                    ['text' => $lead->lead_source ?: '—'],
                    ['text' => $lead->lead_status ?: 'New'],
                    ['text' => $lead->assigned_to ?: 'Unassigned'],
                    ['text' => $lead->follow_up_date ? Carbon::parse($lead->follow_up_date)->format('d M Y') : '—'],
                ];
            });
        };

        $open = $quotes->reject(fn ($quote) => $this->quoteWon($quote) || strtolower(trim((string) $quote->quotation_status)) === 'rejected');
        $quoteColumns = ['Quotation', 'Customer', 'Status', 'Amount', 'Created'];
        $leadColumns = ['Customer', 'Source', 'Status', 'Assigned', 'Follow-up'];

        return match ($report) {
            'sales', 'quotations' => ['title' => $report === 'sales' ? 'Sales Performance' : 'Quotations', 'columns' => $quoteColumns, 'rows' => $quoteRows($quotes)],
            'pipeline', 'deals' => ['title' => 'Open Pipeline', 'columns' => $quoteColumns, 'rows' => $quoteRows($open)],
            'won' => ['title' => 'Won Deals', 'columns' => $quoteColumns, 'rows' => $quoteRows($quotes->filter(fn ($quote) => $this->quoteWon($quote)))],
            'lost' => ['title' => 'Lost Deals', 'columns' => $quoteColumns, 'rows' => $quoteRows($quotes->filter(fn ($quote) => strtolower(trim((string) $quote->quotation_status)) === 'rejected'))],
            'sent' => ['title' => 'Sent Quotations', 'columns' => $quoteColumns, 'rows' => $quoteRows($quotes->filter(fn ($quote) => $quote->sent_at && !$this->quoteWon($quote)))],
            'discounted' => ['title' => 'Discounted Quotations', 'columns' => $quoteColumns, 'rows' => $quoteRows($quotes->filter(fn ($quote) => (float) $quote->discount > 0))],
            'renewals' => ['title' => 'Renewal Quotations', 'columns' => $quoteColumns, 'rows' => $quoteRows($quotes->filter(fn ($quote) => ($quote->deal_type ?: 'new') === 'renewal'))],
            'deals-month' => ['title' => 'Closing This Month', 'columns' => $quoteColumns, 'rows' => $quoteRows($open->filter(function ($quote) use ($today) {
                $date = $quote->follow_up_date ?: $quote->valid_date;

                return $date && Carbon::parse($date)->isSameMonth($today);
            }))],
            'deals-quarter' => ['title' => 'Closing This Quarter', 'columns' => $quoteColumns, 'rows' => $quoteRows($open->filter(function ($quote) use ($today) {
                $date = $quote->follow_up_date ?: $quote->valid_date;

                return $date && Carbon::parse($date)->isSameQuarter($today);
            }))],
            'leads' => ['title' => 'Lead Performance', 'columns' => $leadColumns, 'rows' => $leadRows($leads)],
            'sources' => ['title' => 'Lead Sources', 'columns' => ['Source', 'Leads', 'Share'], 'rows' => $leads->groupBy(fn ($lead) => trim((string) $lead->lead_source) ?: 'Unknown')->map(function ($group, $source) use ($leads) {
                $share = $leads->count() > 0 ? round(($group->count() / $leads->count()) * 100) . '%' : '0%';

                return [
                    ['text' => $source],
                    ['text' => (string) $group->count()],
                    ['text' => $share],
                ];
            })->sortByDesc(fn ($row) => (int) $row[1]['text'])->values()],
            'conversion' => ['title' => 'Converted Leads', 'columns' => $leadColumns, 'rows' => $leadRows($leads->filter(fn ($lead) => $wonIds->contains($lead->id)))],
            'unassigned' => ['title' => 'Unassigned Leads', 'columns' => $leadColumns, 'rows' => $leadRows($leads->filter(fn ($lead) => trim((string) $lead->assigned_to) === ''))],
            'scheduled' => ['title' => 'Scheduled Follow-ups', 'columns' => $leadColumns, 'rows' => $leadRows($leads->filter(fn ($lead) => $lead->follow_up_date))],
            'overdue' => ['title' => 'Overdue Follow-ups', 'columns' => $leadColumns, 'rows' => $leadRows($leads->filter(fn ($lead) => $lead->follow_up_date && Carbon::parse($lead->follow_up_date)->lt($today)))],
            'activities' => $this->activityReport($leads),
            default => null,
        };
    }

    protected function activityReport($leads): array
    {
        $columns = ['Customer', 'Status', 'Note', 'Added'];
        if (!Schema::connection('pgsql_crm')->hasTable('lead_followups')) {
            return ['title' => 'Activities', 'columns' => $columns, 'rows' => collect()];
        }

        $byLead = $leads->keyBy('id');
        $rows = DB::connection('pgsql_crm')->table('lead_followups')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(function ($row) use ($byLead) {
                $lead = $byLead->get($row->lead_id);

                return [
                    ['text' => $lead ? ($lead->company_name ?: $lead->name ?: 'Lead') : ('Lead #' . $row->lead_id), 'href' => $lead ? route('crm.followups.show', $lead->id) : null],
                    ['text' => $row->lead_status ?: '—'],
                    ['text' => $row->note ?: '—'],
                    ['text' => $row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '—'],
                ];
            });

        return ['title' => 'Activities', 'columns' => $columns, 'rows' => $rows];
    }

    protected function quoteWon($quote): bool
    {
        return strtolower(trim((string) $quote->quotation_status)) === 'approved'
            && strtolower(trim((string) $quote->invoice_status)) === 'approved';
    }

    protected function money($amount): string
    {
        $amount = (float) $amount;
        $abs = abs($amount);
        if ($abs >= 10000000) {
            return '₹' . rtrim(rtrim(number_format($amount / 10000000, 2), '0'), '.') . ' Cr';
        }
        if ($abs >= 100000) {
            return '₹' . rtrim(rtrim(number_format($amount / 100000, 1), '0'), '.') . ' L';
        }

        return '₹' . number_format($amount, 0);
    }
}
