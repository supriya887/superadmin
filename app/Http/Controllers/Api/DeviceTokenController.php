<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Services\FirebasePushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DeviceTokenController extends Controller
{
    /**
     * Save / refresh an FCM device token for a partner.
     *
     * POST /api/v1/partner/device-token
     * body: partner_id, fcm_token, platform?=android|ios|web, device_name?
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'partner_id' => 'required|integer|min:1',
            'fcm_token' => 'required|string|min:10',
            'platform' => 'nullable|string|in:android,ios,web',
            'device_name' => 'nullable|string|max:120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $partnerId = (int) $request->input('partner_id');
        $token = trim((string) $request->input('fcm_token'));
        $platform = strtolower(trim((string) $request->input('platform', 'android')));
        if (!in_array($platform, ['android', 'ios', 'web'], true)) {
            $platform = 'android';
        }

        try {
            $row = DeviceToken::query()->updateOrCreate(
                [
                    'partner_id' => $partnerId,
                    'fcm_token' => $token,
                ],
                [
                    'platform' => $platform,
                    'device_name' => $request->input('device_name'),
                    'is_active' => true,
                    'last_used_at' => now(),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Device token saved',
                'data' => [
                    'id' => $row->id,
                    'partner_id' => $row->partner_id,
                    'platform' => $row->platform,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Device token save failed', [
                'partner_id' => $partnerId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save device token',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Deactivate a token (e.g. on logout).
     *
     * POST /api/v1/partner/device-token/remove
     * body: partner_id, fcm_token
     */
    public function destroy(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'partner_id' => 'required|integer|min:1',
            'fcm_token' => 'required|string|min:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $updated = DeviceToken::query()
            ->where('partner_id', (int) $request->input('partner_id'))
            ->where('fcm_token', trim((string) $request->input('fcm_token')))
            ->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => $updated ? 'Device token removed' : 'Token not found',
            'data' => ['updated' => (int) $updated],
        ]);
    }

    /**
     * Dev/helper: send a test push to a partner.
     *
     * POST /api/v1/partner/device-token/test-push
     * body: partner_id, title?, body?
     */
    public function testPush(Request $request, FirebasePushService $push): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'partner_id' => 'required|integer|min:1',
            'title' => 'nullable|string|max:120',
            'body' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $partnerId = (int) $request->input('partner_id');
        $title = trim((string) $request->input('title', 'Tizzy test notification'));
        $body = trim((string) $request->input('body', 'Push notifications are working.'));

        $sent = $push->sendToPartner($partnerId, $title !== '' ? $title : 'Tizzy', $body !== '' ? $body : 'Test', [
            'type' => 'test_push',
        ]);

        return response()->json([
            'success' => $sent > 0,
            'message' => $sent > 0 ? 'Test push sent' : 'No tokens sent (check Firebase config / device tokens)',
            'data' => ['sent_count' => $sent],
        ], $sent > 0 ? 200 : 422);
    }
}
