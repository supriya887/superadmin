<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SuperAdmin\BillingController;
use App\Models\Order;
use App\Models\Plan;
use App\Services\B2bSubscriptionRenewalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SubscriptionRenewalController extends Controller
{
    public function __construct(private B2bSubscriptionRenewalService $renewalService) {}

    /**
     * Renew a B2B subscription: creates a new order (order_category=renewal) and invoice.
     */
    public function renew(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'partner_id' => ['required', 'integer', 'exists:pgsql_auth.partners,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'subscription_id' => ['nullable', 'integer', 'exists:orders,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'licenses' => ['nullable', 'integer', 'min:1'],
            'aadhaar_number' => ['nullable', 'digits:12'],
            'aadhar_number' => ['nullable', 'digits:12'],
            'subtotal' => ['nullable', 'numeric', 'min:0'],
            'gst_amount' => ['nullable', 'numeric', 'min:0'],
            'payable_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $subscriptionId = (int) ($validated['subscription_id'] ?? $validated['order_id'] ?? 0);
            $subscriptionId = $subscriptionId > 0 ? $subscriptionId : null;

            $resolvedId = $this->renewalService->resolveSubscriptionId(
                (int) $validated['partner_id'],
                $subscriptionId,
                isset($validated['customer_id']) ? (int) $validated['customer_id'] : null,
                null,
            );

            $qty = (int) ($validated['quantity'] ?? $validated['licenses'] ?? 0);
            if ($qty <= 0) {
                $base = Order::query()->find($resolvedId);
                $qty = max(1, (int) ($base->quantity ?? 1));
            }

            $decreaseGuard = app(\App\Services\LicenseDecreaseRequestService::class)->guardRenewDecrease(
                (int) $validated['partner_id'],
                $resolvedId,
                $qty
            );
            if (!empty($decreaseGuard['blocked'])) {
                return response()->json($decreaseGuard['payload'] ?? [
                    'success' => false,
                    'message' => 'License decrease requires SuperAdmin approval.',
                ], 422);
            }

            $result = $this->renewalService->renew([
                'partner_id' => (int) $validated['partner_id'],
                'subscription_id' => $resolvedId,
                'quantity' => $qty,
                'aadhaar_number' => (string) ($validated['aadhaar_number'] ?? $validated['aadhar_number'] ?? ''),
                'subtotal' => $validated['subtotal'] ?? null,
                'gst_amount' => $validated['gst_amount'] ?? null,
                'payable_amount' => $validated['payable_amount'] ?? null,
            ]);

            $order = $result['order'];
            $newOrderId = (int) ($order->id ?? 0);
            if ($newOrderId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Renewal order was not created. Please try again.',
                ], 500);
            }

            $plan = Plan::query()->find($order->plan_id);
            $summary = $result['summary'] ?? [];
            $creditsUsed = round((float) ($order->credit_used ?? $summary['payable_amount'] ?? 0), 2);
            $availableBefore = round((float) $result['available_credits'], 2);

            $data = [
                'order_id' => $newOrderId,
                'order_no' => (string) ($order->order_no ?? $order->id),
                'order_category' => (string) ($order->order_category ?? 'renewal'),
                'parent_order_id' => (int) ($order->parent_order_id ?? $resolvedId),
                'customer_id' => (int) ($order->user_id ?? 0),
                'plan_id' => (int) $order->plan_id,
                'plan_name' => (string) ($plan->name ?? $order->plan_name ?? ''),
                'domain_name' => (string) ($order->domain_name ?? ''),
                'licenses' => (int) ($order->quantity ?? $qty),
                'subscription_start_date' => $order->subscription_start_date ?? $summary['subscription_start_date'] ?? null,
                'subscription_end_date' => $order->subscription_end_date ?? $summary['subscription_end_date'] ?? null,
                'renewal_summary' => $summary,
                'credits_used' => $creditsUsed,
                'credit_used' => $creditsUsed,
                'credits_deducted' => $creditsUsed,
                'available_credits_before' => $availableBefore,
                'available_credits_after' => round(max(0, $availableBefore - $creditsUsed), 2),
            ];

            $billing = app(BillingController::class);
            try {
                $billing->ensureGeneratedPoForOrder($newOrderId);
                $billing->persistOrderPoPdfToStorage($newOrderId);
                if (method_exists($billing, 'persistOrderInvoicePdfToStorage')) {
                    $billing->persistOrderInvoicePdfToStorage($newOrderId);
                }
                $billing->markInvoiceStatusPendingForOrder($newOrderId);
                $data['invoice_status'] = 'pending';
            } catch (\Throwable $e) {
                Log::error('API renewal-subscription PO/invoice persist failed', [
                    'order_id' => $newOrderId,
                    'error' => $e->getMessage(),
                ]);
            }

            $links = $billing->getOrderDocumentLinks($newOrderId);
            if (!empty($links['po_link'])) {
                $data['po_link'] = $links['po_link'];
            }
            if (!empty($links['invoice_link'])) {
                $data['invoice_link'] = $links['invoice_link'];
            }
            if (!empty($links['po_number'])) {
                $data['po_number'] = $links['po_number'];
            }

            return response()->json([
                'success' => true,
                'message' => 'Renewal completed successfully. Plan renewed and new order created.',
                'data' => $data,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process renewal: ' . $e->getMessage(),
            ], 500);
        }
    }
}
