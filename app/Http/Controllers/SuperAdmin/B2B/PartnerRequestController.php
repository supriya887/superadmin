<?php

namespace App\Http\Controllers\SuperAdmin\B2B;

use App\Http\Controllers\Controller;
use App\Mail\PartnerApproved;
use App\Mail\PartnerRejected;
use App\Models\AccountMaster;
use App\Models\Partner;
use App\Services\CreditService;
use App\Services\PartnerCreditService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PartnerRequestController extends Controller
{
    public function index(Request $request)
    {
        $granularity = $request->query('trend', 'week') === 'month' ? 'month' : 'week';
        $activeTab = in_array($request->query('tab'), ['pending', 'approved', 'all'], true)
            ? $request->query('tab')
            : 'pending';

        $base = Partner::query()->where(function ($q) {
            $q->whereNull('status')->orWhere('status', '!=', 'inactive');
        })->where('email_verified', 1);

        $overallPartnerCount = Partner::query()->count();
        $pendingCount = (clone $base)->where(fn ($q) => $this->applyStatusFilter($q, 'pending'))->count();
        $approvedCount = (clone $base)->where(fn ($q) => $this->applyStatusFilter($q, 'approved'))->count();
        $rejectedCount = (clone $base)->where(fn ($q) => $this->applyStatusFilter($q, 'rejected'))->count();

        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();
        $approvedThisMonth = (clone $base)
            ->where(fn ($q) => $this->applyStatusFilter($q, 'approved'))
            ->whereBetween('updated_at', [$startOfMonth, $endOfMonth])
            ->count();

        $chartLabels = [];
        $chartValues = [];
        if ($granularity === 'month') {
            for ($i = 5; $i >= 0; $i--) {
                $m = Carbon::now()->subMonths($i)->startOfMonth();
                $label = $m->format('M Y');
                $chartLabels[] = $label;
                $chartValues[] = (clone $base)->whereBetween('created_at', [$m->copy()->startOfMonth(), $m->copy()->endOfMonth()])->count();
            }
        } else {
            for ($i = 7; $i >= 0; $i--) {
                $wStart = Carbon::now()->subWeeks($i)->startOfWeek();
                $wEnd = Carbon::now()->subWeeks($i)->endOfWeek();
                $chartLabels[] = $wStart->format('d M') . ' – ' . $wEnd->format('d M');
                $chartValues[] = (clone $base)->whereBetween('created_at', [$wStart, $wEnd])->count();
            }
        }

        return view('superadmin.partner_requests.index', [
            'title' => 'Partner Requests',
            'overallPartnerCount' => $overallPartnerCount,
            'pendingCount' => $pendingCount,
            'approvedCount' => $approvedCount,
            'rejectedCount' => $rejectedCount,
            'chartLabels' => $chartLabels,
            'chartValues' => $chartValues,
            'trendGranularity' => $granularity,
            'activeTab' => $activeTab,
            'approvedThisMonth' => $approvedThisMonth,
        ]);
    }

    public function listTable(Request $request)
    {
        $draw = (int) $request->input('draw', 1);
        $start = max((int) $request->input('start', 0), 0);
        $length = max((int) $request->input('length', 10), 1);
        $tab = in_array($request->input('tab'), ['pending', 'approved', 'all'], true)
            ? $request->input('tab')
            : 'all';

        $columns = [
            0 => 'created_at',
            1 => 'name',
            2 => 'company_name',
            3 => 'email',
            4 => 'mobile',
            5 => 'status',
        ];

        // Always default to DESC order for the partner list, regardless of the given order.
        // You can allow manual override if you want, but for now force DESC for the main listing.
        $orderColIdx = (int) data_get($request->input('order', []), '0.column', 0);
        $orderBy = $columns[$orderColIdx] ?? 'created_at';
        $orderDir = 'desc'; // Force descending order always

        $base = Partner::query()->where(function ($q) {
            $q->whereNull('status')->orWhere('status', '!=', 'inactive');
        })->where('email_verified', 1);

        $applyTabFilter = function ($query) use ($tab) {
            if ($tab === 'pending') {
                $this->applyStatusFilter($query, 'pending');
            } elseif ($tab === 'approved') {
                $this->applyStatusFilter($query, 'approved');
            }
        };

        $filtered = clone $base;
        $applyTabFilter($filtered);

        $search = trim((string) data_get($request->input('search', []), 'value', ''));
        if ($search !== '') {
            $filtered->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('first_name', 'like', '%' . $search . '%')
                    ->orWhere('company_name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('mobile', 'like', '%' . $search . '%');
            });
        }

        $recordsTotalQuery = clone $base;
        $applyTabFilter($recordsTotalQuery);
        $recordsTotal = $recordsTotalQuery->count();
        $recordsFiltered = (clone $filtered)->count();

        $rows = $filtered
            ->orderBy($orderBy, $orderDir)
            ->offset($start)
            ->limit($length)
            ->get();

        $data = $rows->map(function ($p) {
            $statusVal = strtolower(trim((string) $p->status));
            $label = 'Unknown';
            $cls = 'bg-secondary';
            if (in_array($statusVal, ['pending', '2'], true)) {
                $label = 'Pending';
                $cls = 'bg-warning text-dark';
            } elseif (in_array($statusVal, ['approved', '1'], true)) {
                $label = 'Approved';
                $cls = 'bg-success';
            } elseif (in_array($statusVal, ['rejected', '3'], true)) {
                $label = 'Rejected';
                $cls = 'bg-danger';
            }

            return [
                'created_at' => $p->created_at ? $p->created_at->format('d-m-Y H:i') : '-',
                'partner_name' => $p->first_name ?? $p->name ?? '-',
                'company_name' => $p->company_name ?: '—',
                'email' => $p->email ?: '—',
                'mobile' => $p->mobile ?: '—',
                'wallet_balance' => round((float) ($p->wallet_balance ?? 0), 2),
                'credit_limit' => round((float) ($p->credit_limit ?? 0), 2),
                'status' => '<span class="badge ' . $cls . '">' . e($label) . '</span>',
                'action' => '<a class="btn btn-sm btn-outline-primary" title="View" href="' . route('superadmin.partner_requests.show', $p->id) . '"><i class="ti ti-eye"></i></a>',
            ];
        })->values();

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function show(int $id)
    {
        $partner = Partner::findOrFail($id);
        $partnerApproved = $this->isApprovedStatus($partner->status);
        $partnerPending = $this->isPendingStatus($partner->status);
        $partnerRejected = $this->isRejectedStatus($partner->status);
        $creditTimeline = $partnerApproved ? PartnerCreditService::buildCreditTimeline($partner) : [];
        $manualCreditHistory = $partnerApproved
            ? PartnerCreditService::listPartnerCreditAllocationHistory((int) $partner->id)
            : collect();

        return view('superadmin.partner_requests.show', [
            'title' => 'Partner request #' . $id,
            'partner' => $partner,
            'creditTimeline' => $creditTimeline,
            'partnerApproved' => $partnerApproved,
            'partnerPending' => $partnerPending,
            'partnerRejected' => $partnerRejected,
            'manualCreditHistory' => $manualCreditHistory,
            'partnerWalletBalance' => 0.0,
        ]);
    }

    public function addManualCredit(Request $request, int $id)
    {
        if (!Schema::hasTable('partners')) {
            return back()->with('error', 'Partners table is not available.');
        }

        $partner = Partner::findOrFail($id);
        if (!$this->isApprovedStatus($partner->status)) {
            return back()->with('error', 'Adding wallet credit is only available for approved partners.');
        }

        $request->validate([
            'manual_credit_amount' => 'required|numeric|min:0.01',
            'manual_credit_note' => 'nullable|string|max:2000',
        ]);

        try {
            CreditService::addWalletTopUp(
                (int) $partner->id,
                (float) $request->input('manual_credit_amount'),
                (string) $request->input('manual_credit_note', ''),
                session('userId') ? (int) session('userId') : null
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['manual_credit_amount' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Could not add credit: ' . $e->getMessage());
        }

        return redirect()
            ->route('superadmin.partner_requests.show', $id)
            ->with('success', 'Wallet credit added and ledger entry recorded.');
    }

    public function updateCreditLimit(Request $request, int $id)
    {
        if (!Schema::hasTable('partners') || !Schema::hasColumn('partners', 'credit_limit')) {
            return back()->with('error', 'Credit limit is not configured on partners.');
        }

        $request->validate([
            'credit_limit' => 'required|numeric|min:0',
        ]);

        $partner = Partner::findOrFail($id);
        $old = (float) ($partner->credit_limit ?? 0);
        $new = (float) $request->input('credit_limit');
        $used = PartnerCreditService::summarizeUsedCredit((int) $partner->id);
        if ($new + 0.005 < $used) {
            return back()
                ->withInput()
                ->withErrors([
                    'credit_limit' => 'Credit limit cannot be less than credit already used (₹' . number_format($used, 2) . ').',
                ]);
        }

        DB::beginTransaction();
        try {
            $partner->credit_limit = $new;
            $partner->save();
            PartnerCreditService::recordCreditLimitLedgerDelta((int) $partner->id, $old, $new);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Could not update credit limit: ' . $e->getMessage());
        }

        return redirect()
            ->route('superadmin.partner_requests.show', $id)
            ->with('success', 'Credit limit updated and audit entry recorded.');
    }

    public function approve(Request $request, int $id)
    {
        $partner = Partner::findOrFail($id);
        if (!$this->isPendingStatus($partner->status)) {
            return redirect()->route('superadmin.partner_requests.show', $id)->with('success', 'Only pending partners can be approved.');
        }

        $request->validate([
            'partner_discount_type' => 'required|in:market_value,distributor',
            'partner_discount_percent' => 'nullable|numeric|min:0|max:100',
            'approval_wallet_credit' => 'nullable|numeric|min:0',
        ]);

        $discountType = $request->input('partner_discount_type') === 'distributor' ? 'distributor' : 'market_value';
        $discountPercent = $request->filled('partner_discount_percent')
            ? round((float) $request->input('partner_discount_percent'), 2)
            : null;
        // Single field: credit allocation only (no separate wallet top-up).
        $creditLimit = round((float) ($request->input('approval_wallet_credit') ?: 0), 2);
        $partnerSchema = Schema::connection((new Partner())->getConnectionName());

        DB::beginTransaction();
        try {
            if (empty($partner->password)) {
                $partner->password = Hash::make(Str::password(16));
            }
            $partner->status = $this->approvedStatusScalar($partner->status);
            if ($partnerSchema->hasColumn('partners', 'credit_limit')) {
                $partner->credit_limit = $creditLimit;
            }
            if ($partnerSchema->hasColumn('partners', 'wallet_balance')) {
                $partner->wallet_balance = 0;
            }
            // Separate fields: type → partner_discount_type, % → partner_discount_percent
            if ($partnerSchema->hasColumn('partners', 'partner_discount_type')) {
                $partner->partner_discount_type = $discountType;
            }
            if ($discountPercent !== null && $partnerSchema->hasColumn('partners', 'partner_discount_percent')) {
                $partner->partner_discount_percent = $discountPercent;
            }
            if ($partnerSchema->hasColumn('partners', 'approveby')) {
                $partner->approveby = session('userId');
            }
            if ($partnerSchema->hasColumn('partners', 'approve_datetime')) {
                $partner->approve_datetime = now();
            }
            if ($partnerSchema->hasColumn('partners', 'rejection_reason')) {
                $partner->rejection_reason = null;
            }
            $partner->save();

            if ($creditLimit > 0.005) {
                try {
                    PartnerCreditService::recordCreditLimitLedgerDelta(
                        (int) $partner->id,
                        0.0,
                        $creditLimit,
                        'Initial credit allocation on partner approval'
                    );
                } catch (\Throwable $e) {
                    Log::warning('partner_request_approve_credit_ledger_failed', [
                        'partner_id' => (int) $partner->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Approval failed: ' . $e->getMessage());
        }

        $partner->refresh();
        try {
            Mail::to($partner->email)->send(new PartnerApproved($partner));
        } catch (\Throwable $e) {
            return redirect()->route('superadmin.partner_requests.show', $id)->with(
                'success',
                'Partner approved, but the approval email could not be sent: ' . $e->getMessage()
            );
        }

        $successMsg = $creditLimit > 0.005
            ? 'Partner approved; credit allocation set and approval email sent.'
            : 'Partner approved; discount type saved and approval email sent.';

        return redirect()
            ->route('superadmin.partner_requests.show', $id)
            ->with('success', $successMsg);
    }

    public function reject(Request $request, int $id)
    {
        $request->validate([
            'rejection_reason' => 'nullable|string|max:2000',
        ]);

        $partner = Partner::findOrFail($id);
        if (!$this->isPendingStatus($partner->status)) {
            return back()->with('error', 'Only pending partners can be rejected.');
        }
        if ($this->isRejectedStatus($partner->status)) {
            return redirect()->route('superadmin.partner_requests.show', $id)->with('success', 'Partner is already rejected.');
        }

        DB::beginTransaction();
        try {
            $partner->status = $this->rejectedStatusScalar($partner->status);
            if (Schema::hasColumn('partners', 'rejection_reason')) {
                $partner->rejection_reason = $request->input('rejection_reason');
            }
            if (Schema::hasColumn('partners', 'approveby')) {
                $partner->approveby = session('userId');
            }
            if (Schema::hasColumn('partners', 'approve_datetime')) {
                $partner->approve_datetime = now();
            }
            $partner->save();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Reject failed: ' . $e->getMessage());
        }

        $partner->refresh();
        $reason = trim((string) $request->input('rejection_reason', ''));
        if ($reason === '' && Schema::hasColumn('partners', 'rejection_reason')) {
            $reason = trim((string) ($partner->rejection_reason ?? ''));
        }

        try {
            Mail::to($partner->email)->send(new PartnerRejected($partner, $reason));
        } catch (\Throwable $e) {
            return redirect()->route('superadmin.partner_requests.index')->with(
                'success',
                'Partner rejected, but the rejection email could not be sent: ' . $e->getMessage()
            );
        }

        return redirect()->route('superadmin.partner_requests.index')->with('success', 'Partner rejected; rejection email sent.');
    }

    /**
     * Match partner status stored as string labels or legacy numeric codes (pgsql-safe).
     */
    private function applyStatusFilter($query, string $status): void
    {
        $values = match ($status) {
            'pending' => ['pending', 2, '2'],
            'approved' => ['approved', 1, '1'],
            'rejected' => ['rejected', 3, '3'],
            default => [],
        };

        if ($values === []) {
            return;
        }

        $query->where(function ($q) use ($values) {
            foreach ($values as $value) {
                $q->orWhere('status', $value);
            }
        });
    }

    private function isApprovedStatus($status): bool
    {
        return $status === 'approved' || $status === 1 || $status === '1';
    }

    private function isPendingStatus($status): bool
    {
        return $status === 'pending' || $status === 2 || $status === '2';
    }

    private function isRejectedStatus($status): bool
    {
        return $status === 'rejected' || $status === 3 || $status === '3';
    }

    /**
     * Preserve numeric vs string status columns when possible.
     */
    private function approvedStatusScalar($current)
    {
        if (is_int($current) || (is_string($current) && ctype_digit($current))) {
            return 1;
        }
        return 'approved';
    }

    private function rejectedStatusScalar($current)
    {
        if (is_int($current) || (is_string($current) && ctype_digit($current))) {
            return 3;
        }
        return 'rejected';
    }

    private function ensurePartnerWalletKhata(int $partnerId): void
    {
        $partnerAccount = AccountMaster::findOrCreate(PartnerCreditService::partnerWalletAccountName($partnerId), 'Partner', $partnerId);
        $partnerAccount->refresh();

        if (!Schema::hasTable('ledgers')) {
            return;
        }

        $exists = DB::table('ledgers')
            ->where('account_master_id', $partnerAccount->id)
            ->where('description', 'Opening balance (system)')
            ->exists();

        if ($exists) {
            $partnerAccount->syncCurrentBalance();
            return;
        }

        $payload = [
            'account_master_id' => $partnerAccount->id,
            'order_id' => null,
            'transaction_type' => 'Credit',
            'amount' => 0,
            'description' => 'Opening balance (system)',
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('ledgers', 'type')) {
            $payload['type'] = 'credit';
        }
        if (Schema::hasColumn('ledgers', 'payment_mode')) {
            $payload['payment_mode'] = null;
        }
        if (Schema::hasColumn('ledgers', 'reference_no')) {
            $payload['reference_no'] = 'OB-' . $partnerId;
        }

        DB::table('ledgers')->insert($payload);
        $partnerAccount->syncCurrentBalance();
    }
}
