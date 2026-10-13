<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountDeletionRequest;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AccountDeletionController extends Controller
{
    /**
     * Partner requests account deletion (does NOT delete the account).
     * POST /api/v1/partner/account-deletion-request
     */
    public function requestDeletion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'partner_id' => ['required', 'integer', 'exists:pgsql_auth.partners,id'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $partnerId = (int) $validated['partner_id'];
        $partner = Partner::query()->whereKey($partnerId)->first();
        if (!$partner) {
            return response()->json([
                'success' => false,
                'message' => 'Partner not found.',
                'data' => null,
            ], 404);
        }

        $open = AccountDeletionRequest::query()
            ->where('partner_id', $partnerId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->orderByDesc('id')
            ->first();

        if ($open) {
            return response()->json([
                'success' => true,
                'message' => 'An account deletion request is already pending. Our team will process it; the account is not deleted automatically.',
                'data' => $this->mapRequest($open),
            ], 200);
        }

        $row = AccountDeletionRequest::create([
            'partner_id' => $partnerId,
            'reason' => isset($validated['reason']) ? trim((string) $validated['reason']) : null,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        Log::info('ACCOUNT_DELETION_REQUEST', [
            'request_id' => $row->id,
            'partner_id' => $partnerId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Account deletion request submitted successfully. Your account is not deleted yet. Our team will review and process your request. You cannot delete the account yourself from the app.',
            'data' => $this->mapRequest($row),
        ], 200);
    }

    /**
     * Check latest deletion request status.
     * POST /api/v1/partner/account-deletion-status
     */
    public function status(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'partner_id' => ['required', 'integer', 'exists:pgsql_auth.partners,id'],
        ]);

        $partnerId = (int) $validated['partner_id'];
        $row = AccountDeletionRequest::query()
            ->where('partner_id', $partnerId)
            ->orderByDesc('id')
            ->first();

        if (!$row) {
            return response()->json([
                'success' => true,
                'message' => 'No account deletion request found.',
                'data' => null,
            ], 200);
        }

        return response()->json([
            'success' => true,
            'message' => 'Account deletion request status fetched.',
            'data' => $this->mapRequest($row),
        ], 200);
    }

    private function mapRequest(AccountDeletionRequest $row): array
    {
        return [
            'request_id' => (int) $row->id,
            'partner_id' => (int) $row->partner_id,
            'reason' => (string) ($row->reason ?? ''),
            'status' => (string) $row->status,
            'requested_at' => $row->requested_at
                ? $row->requested_at->format('Y-m-d H:i:s')
                : null,
            'processed_at' => $row->processed_at
                ? $row->processed_at->format('Y-m-d H:i:s')
                : null,
            'account_deleted' => false,
            'note' => 'This is a request only. Account is not deleted by this API.',
        ];
    }
}
