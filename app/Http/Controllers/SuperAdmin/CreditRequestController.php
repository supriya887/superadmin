<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Mail\PartnerCreditRequestDecisionMail;
use App\Models\CreditRequest;
use App\Models\Notification;
use App\Models\Partner as PartnerRecord;
use App\Services\FirebasePushService;
use App\Services\PartnerCreditService;
use App\Services\PartnerDomainTransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class CreditRequestController extends Controller
{
    public function index(Request $request)
    {
        $connection = $this->creditRequestsConnection();
        if ($connection === null) {
            return view('superadmin.credit_requests.index', [
                'rows' => collect(),
                'status' => $request->get('status', 'pending'),
            ]);
        }

        $status = (string) $request->get('status', 'pending');

        $query = CreditRequest::on($connection)->newQuery();

        if ($status === 'pending') {
            $query->where(function ($q) {
                $q->whereRaw("LOWER(TRIM(COALESCE(status, 'pending'))) = 'pending'");
            });
        } elseif ($status !== 'all') {
            $query->whereRaw('LOWER(TRIM(status)) = ?', [strtolower($status)]);
        }

        $rows = $query
            ->orderByDesc('id')
            ->get();

        $partnerIds = $rows->pluck('partner_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $partnersById = $partnerIds->isEmpty()
            ? collect()
            : PartnerRecord::query()->whereIn('id', $partnerIds)->get()->keyBy('id');

        $rows->each(function ($row) use ($partnersById) {
            $row->setRelation('partner', $partnersById->get((int) $row->partner_id));
        });

        return view('superadmin.credit_requests.index', compact('rows', 'status'));
    }

    public function approve(Request $request, int $id)
    {
        $connection = $this->creditRequestsConnection();
        if ($connection === null) {
            return back()->with('error', 'Credit requests table is not installed.');
        }

        $request->validate([
            'approved_amount' => 'required|numeric|min:0.01',
            'admin_note' => 'nullable|string|max:500',
        ]);

        $creditRequest = CreditRequest::on($connection)->findOrFail($id);

        if (strtolower(trim((string) ($creditRequest->status ?? 'pending'))) !== 'pending') {
            return back()->with('warning', 'This request is no longer pending.');
        }

        $approvedAmount = round((float) $request->input('approved_amount'), 2);
        $adminNote = trim((string) $request->input('admin_note', ''));
        if ($adminNote === '') {
            $adminNote = 'Approved allocation increase: ₹' . number_format($approvedAmount, 2)
                . ' (requested ₹' . number_format((float) $creditRequest->requested_amount, 2) . ')';
        }

        try {
            DB::transaction(function () use ($creditRequest, $connection, $approvedAmount, $adminNote): void {
                $schema = Schema::connection($connection);
                $payload = [
                    'status' => 'approved',
                    'admin_note' => $adminNote,
                ];
                if ($schema->hasColumn('credit_requests', 'approved_amount')) {
                    $payload['approved_amount'] = $approvedAmount;
                }

                $creditRequest->update($payload);

                $partnerId = (int) $creditRequest->partner_id;
                if ($partnerId > 0) {
                    $note = 'Credit request #' . $creditRequest->id . ' approved — allocation increased by ₹'
                        . number_format($approvedAmount, 2);

                    PartnerCreditService::applyAdminCreditTopUpAndOptionalLimit(
                        $partnerId,
                        $approvedAmount,
                        null,
                        $note,
                        auth()->id() ? (int) auth()->id() : (session('userId') ? (int) session('userId') : null)
                    );
                }
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Could not approve credit request: ' . $e->getMessage());
        }

        try {
            app(PartnerDomainTransferService::class)->finalizePendingCartsAfterCreditApproval(
                (int) $creditRequest->partner_id,
                (int) $creditRequest->id
            );
        } catch (\Throwable) {
        }

        $creditRequest->refresh();
        $this->notifyPartnerOfDecision($creditRequest, 'approved', $approvedAmount);

        return back()->with(
            'success',
            'Credit request approved. ₹' . number_format($approvedAmount, 2) . ' added to partner credit allocation. Partner notified.'
        );
    }

    public function reject(Request $request, int $id)
    {
        $connection = $this->creditRequestsConnection();
        if ($connection === null) {
            return back()->with('error', 'Credit requests table is not installed.');
        }

        $creditRequest = CreditRequest::on($connection)->findOrFail($id);

        if (strtolower(trim((string) ($creditRequest->status ?? 'pending'))) !== 'pending') {
            return back()->with('warning', 'This request is no longer pending.');
        }

        $creditRequest->update(['status' => 'rejected']);

        $this->notifyPartnerOfDecision($creditRequest, 'rejected');

        return back()->with('success', 'Credit request rejected. Partner notified by email and in-app notification.');
    }

    /**
     * Email + in-app notification + FCM push so the decision shows in the partner app.
     */
    private function notifyPartnerOfDecision(CreditRequest $creditRequest, string $decision, ?float $approvedAmount = null): void
    {
        $partnerId = (int) ($creditRequest->partner_id ?? 0);
        if ($partnerId <= 0) {
            return;
        }

        $partner = PartnerRecord::find($partnerId);
        $requested = round((float) ($creditRequest->requested_amount ?? 0), 2);
        $amount = $approvedAmount !== null ? round($approvedAmount, 2) : $requested;
        $requestId = (int) $creditRequest->id;
        $approved = $decision === 'approved';

        $title = $approved
            ? 'Credit request approved'
            : 'Credit request rejected';
        $message = $approved
            ? ('Your credit request #' . $requestId . ' has been approved. ₹' . number_format($amount, 2)
                . ' has been added to your credit allocation'
                . ($approvedAmount !== null && abs($amount - $requested) >= 0.01
                    ? ' (you requested ₹' . number_format($requested, 2) . ')'
                    : '')
                . '.')
            : ('Your credit request #' . $requestId . ' for ₹' . number_format($requested, 2) . ' was rejected.');

        if ($partner && filter_var((string) $partner->email, FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::to($partner->email)->send(new PartnerCreditRequestDecisionMail(
                    (string) ($partner->name ?? 'Partner'),
                    $decision,
                    $amount,
                    $requestId
                ));
            } catch (\Throwable $e) {
                Log::warning('credit_request_decision_mail_failed', [
                    'credit_request_id' => $requestId,
                    'partner_id' => $partnerId,
                    'decision' => $decision,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            Log::info('credit_request_decision_mail_skipped', [
                'credit_request_id' => $requestId,
                'partner_id' => $partnerId,
                'reason' => $partner ? 'invalid_email' : 'partner_not_found',
            ]);
        }

        try {
            Notification::query()->create([
                'partner_id' => $partnerId,
                'title' => $title,
                'message' => $message,
                'is_read' => 0,
                'order_id' => null,
                'status' => 'active',
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('credit_request_in_app_notification_failed', [
                'credit_request_id' => $requestId,
                'partner_id' => $partnerId,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            app(FirebasePushService::class)->sendToPartner($partnerId, $title, $message, [
                'type' => 'credit_request_decision',
                'credit_request_id' => (string) $requestId,
                'decision' => $decision,
                'amount' => (string) $amount,
            ]);
        } catch (\Throwable $e) {
            Log::warning('credit_request_fcm_failed', [
                'credit_request_id' => $requestId,
                'partner_id' => $partnerId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function creditRequestsConnection(): ?string
    {
        foreach (array_unique(array_filter([
            (new CreditRequest())->getConnectionName(),
            'pgsql_billing',
            'pgsql_auth',
            config('database.default'),
        ])) as $connection) {
            if (Schema::connection($connection)->hasTable('credit_requests')) {
                return $connection;
            }
        }

        return null;
    }
}
