<?php

namespace App\Http\Controllers\SuperAdmin\Crm;

use App\Http\Controllers\Controller;
use App\Models\CrmUser;
use App\Models\Lead;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FollowUpController extends Controller
{
    public function index(Request $request)
    {
        $today = Carbon::today();
        $query = Lead::whereNotNull('follow_up_date');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%");
            });
        }

        if ($request->filter === 'overdue') {
            $query->whereDate('follow_up_date', '<', $today);
        } elseif ($request->filter === 'today') {
            $query->whereDate('follow_up_date', $today);
        } elseif ($request->filter === 'upcoming') {
            $query->whereDate('follow_up_date', '>', $today);
        }

        $leads = $query->orderBy('follow_up_date', 'asc')->paginate(10)->withQueryString();

        return view('superadmin.crm.followups.index', compact('leads'));
    }

    public function show(Lead $lead)
    {
        $this->ensureTable();
        $followups = $this->entriesFor($lead);

        if ($followups->isEmpty() && $lead->follow_up_date) {
            $this->record($lead, [
                'follow_up_date' => $lead->follow_up_date,
                'follow_up_note' => $lead->follow_up_note,
                'lead_status' => $lead->lead_status,
                'assigned_to' => $lead->assigned_to,
            ]);
            $followups = $this->entriesFor($lead);
        }

        $assignees = $this->assignees();

        return view('superadmin.crm.followups.show', compact('lead', 'followups', 'assignees'));
    }

    public function store(Request $request, Lead $lead)
    {
        $data = $request->validate([
            'lead_status' => 'required|string|max:100',
            'assigned_to' => 'nullable|string|max:255',
            'follow_up_date' => 'nullable|date',
            'follow_up_note' => 'nullable|string',
        ], [
            'lead_status.required' => 'Lead Status is required.',
        ]);

        $this->record($lead, $data);
        $lead->update($data);

        return redirect()
            ->route('crm.followups.show', $lead)
            ->with('success', 'Follow-up added.');
    }

    public function record(Lead $lead, array $data): void
    {
        $this->ensureTable();

        DB::connection('pgsql_crm')->table('lead_followups')->insert([
            'lead_id' => $lead->id,
            'follow_up_date' => $data['follow_up_date'] ?? null,
            'note' => $data['follow_up_note'] ?? null,
            'lead_status' => $data['lead_status'] ?? $lead->lead_status,
            'assigned_to' => $data['assigned_to'] ?? $lead->assigned_to,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function entriesFor(Lead $lead)
    {
        return DB::connection('pgsql_crm')
            ->table('lead_followups')
            ->where('lead_id', $lead->id)
            ->orderByDesc('id')
            ->get();
    }

    protected function assignees()
    {
        try {
            return CrmUser::query()
                ->where('role', 'executive')
                ->orderBy('name')
                ->pluck('name')
                ->filter()
                ->unique()
                ->values();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    protected function ensureTable(): void
    {
        if (Schema::connection('pgsql_crm')->hasTable('lead_followups')) {
            return;
        }

        try {
            DB::connection('pgsql_crm')->statement("
                CREATE TABLE IF NOT EXISTS lead_followups (
                    id serial PRIMARY KEY,
                    lead_id bigint NOT NULL,
                    follow_up_date timestamp NULL,
                    note text NULL,
                    lead_status varchar(100) NULL,
                    assigned_to varchar(255) NULL,
                    created_at timestamp NULL,
                    updated_at timestamp NULL
                )
            ");
        } catch (\Throwable $e) {
            // table may already exist
        }
    }
}
