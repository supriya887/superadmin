<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentRefund;
use App\Services\RazorpayRefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class RefundController extends Controller
{
    public function __construct(private RazorpayRefundService $refunds)
    {
    }

    /**
     * Check if a refund is allowed under policy (no Razorpay call).
     *
     * POST /api/v1/partner/refund-policy-check
     * or SuperAdmin equivalent
     */
    public function checkPolicy(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'nullable|integer|min:1',
            'invoice_payment_transaction_id' => 'nullable|integer|min:1',
            'razorpay_payment_id' => 'nullable|string|max:100',
            'amount' => 'nullable|numeric|min:1',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        if (!$request->filled('order_id')
            && !$request->filled('invoice_payment_transaction_id')
            && !$request->filled('razorpay_payment_id')) {
            return response()->json([
                'success' => false,
                'message' => 'Provide order_id, invoice_payment_transaction_id, or razorpay_payment_id.',
            ], 422);
        }

        $result = $this->refunds->evaluatePolicy($request->only([
            'order_id',
            'invoice_payment_transaction_id',
            'razorpay_payment_id',
            'amount',
            'reason',
        ]));

        return response()->json([
            'success' => (bool) $result['allowed'],
            'message' => $result['message'],
            'data' => $result['context'] ?? null,
            'policy' => [
                'enabled' => (bool) config('services.razorpay_refund.enabled', true),
                'max_days' => (int) config('services.razorpay_refund.max_days', 30),
                'allow_partial' => (bool) config('services.razorpay_refund.allow_partial', true),
                'min_reason_length' => (int) config('services.razorpay_refund.min_reason_length', 5),
            ],
        ], $result['allowed'] ? 200 : 422);
    }

    /**
     * Process a Razorpay refund under policy.
     *
     * POST /api/v1/superadmin/refunds  (or partner if you later allow)
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'nullable|integer|min:1',
            'invoice_payment_transaction_id' => 'nullable|integer|min:1',
            'razorpay_payment_id' => 'nullable|string|max:100',
            'amount' => 'nullable|numeric|min:1',
            'reason' => 'required|string|min:5|max:500',
            'requested_by' => 'nullable|string|max:120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        if (!$request->filled('order_id')
            && !$request->filled('invoice_payment_transaction_id')
            && !$request->filled('razorpay_payment_id')) {
            return response()->json([
                'success' => false,
                'message' => 'Provide order_id, invoice_payment_transaction_id, or razorpay_payment_id.',
            ], 422);
        }

        try {
            $refund = $this->refunds->refund([
                'order_id' => $request->input('order_id'),
                'invoice_payment_transaction_id' => $request->input('invoice_payment_transaction_id'),
                'razorpay_payment_id' => $request->input('razorpay_payment_id'),
                'amount' => $request->input('amount'),
                'reason' => $request->input('reason'),
                'requested_by' => $request->input('requested_by', 'api'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Refund processed successfully.',
                'data' => [
                    'id' => $refund->id,
                    'status' => $refund->status,
                    'amount' => (float) $refund->amount,
                    'is_partial' => (bool) $refund->is_partial,
                    'razorpay_payment_id' => $refund->razorpay_payment_id,
                    'razorpay_refund_id' => $refund->razorpay_refund_id,
                    'order_id' => $refund->order_id,
                    'invoice_payment_transaction_id' => $refund->invoice_payment_transaction_id,
                    'processed_at' => optional($refund->processed_at)->toDateTimeString(),
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::warning('Refund API failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Refund failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List refunds for an order / payment / partner.
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'nullable|integer|min:1',
            'partner_id' => 'nullable|integer|min:1',
            'razorpay_payment_id' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $query = PaymentRefund::query()->orderByDesc('id');

        if ($request->filled('order_id')) {
            $query->where('order_id', (int) $request->input('order_id'));
        }
        if ($request->filled('partner_id')) {
            $query->where('partner_id', (int) $request->input('partner_id'));
        }
        if ($request->filled('razorpay_payment_id')) {
            $query->where('razorpay_payment_id', trim((string) $request->input('razorpay_payment_id')));
        }

        $rows = $query->limit(100)->get()->map(static function (PaymentRefund $r) {
            return [
                'id' => $r->id,
                'status' => $r->status,
                'amount' => (float) $r->amount,
                'is_partial' => (bool) $r->is_partial,
                'reason' => $r->reason,
                'razorpay_payment_id' => $r->razorpay_payment_id,
                'razorpay_refund_id' => $r->razorpay_refund_id,
                'order_id' => $r->order_id,
                'partner_id' => $r->partner_id,
                'invoice_payment_transaction_id' => $r->invoice_payment_transaction_id,
                'failure_message' => $r->failure_message,
                'processed_at' => optional($r->processed_at)->toDateTimeString(),
                'created_at' => optional($r->created_at)->toDateTimeString(),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Refunds fetched successfully.',
            'data' => $rows,
        ]);
    }
}
