<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SuperAdmin\BillingController;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Provider;
use App\Models\Enquiry;
use App\Services\OrderCancellationPolicy;
use App\Services\B2bSubscriptionUpgradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SubscriptionUpgradeController extends Controller
{
    public function __construct(private B2bSubscriptionUpgradeService $upgradeService) {}

    /**
     * Upgrade a B2B customer subscription (mirrors superadmin upgradeUsingCredit).
     */
    public function upgrade(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'partner_id' => ['required', 'integer', 'exists:pgsql_auth.partners,id'],
            'subscription_id' => ['nullable', 'integer', 'exists:orders,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'username' => ['nullable', 'string', 'max:255'],
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'licenses' => ['nullable', 'integer', 'min:1'],
            'aadhaar_number' => ['required', 'digits:12'],
            'aadhar_number' => ['nullable', 'digits:12'],
            'otp' => ['required', 'digits:6'],
            'subtotal' => ['nullable', 'numeric', 'min:0'],
            'pro_rata_adjustment' => ['nullable', 'numeric', 'min:0'],
            'taxable_amount' => ['nullable', 'numeric', 'min:0'],
            'gst_amount' => ['nullable', 'numeric', 'min:0'],
            'payable_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $subscriptionId = (int) ($validated['subscription_id'] ?? $validated['order_id'] ?? 0);
            $subscriptionId = $subscriptionId > 0 ? $subscriptionId : null;

            $plan = Plan::query()->find((int) $validated['plan_id']);
            $providerId = (int) ($plan->provider_id ?? 0);
            $providerObj = $providerId > 0 ? Provider::query()->find($providerId) : null;
            $providerName = $providerObj->name ?? '';

            $isTizzyOrGoogle = $providerId === OrderCancellationPolicy::PROVIDER_TIZZY
                || $providerId === OrderCancellationPolicy::PROVIDER_GOOGLE
                || str_contains(strtolower($providerName), 'tizzy')
                || str_contains(strtolower($providerName), 'google');

            if ($isTizzyOrGoogle) {
                $pName = ($providerId === OrderCancellationPolicy::PROVIDER_GOOGLE || str_contains(strtolower($providerName), 'google')) ? 'Google' : 'Tizzy';
                $lines = [
                    $pName . ' upgrade request',
                    'Subscription ID: ' . ($subscriptionId ?? 0),
                    'Requested plan ID: ' . (int) $validated['plan_id'],
                    'Quantity: ' . (int) ($validated['quantity'] ?? $validated['licenses'] ?? 1),
                    'Customer ID: ' . (int) ($validated['customer_id'] ?? 0),
                ];
                $enquiry = Enquiry::query()->create([
                    'partner_id' => (int) $validated['partner_id'],
                    'message' => implode("\n", $lines),
                    'status' => 'new',
                ]);

                return response()->json([
                    'success' => true,
                    'requires_request' => true,
                    'change_via' => 'request',
                    'action' => 'upgrade',
                    'provider_id' => $providerId,
                    'request_id' => (int) $enquiry->id,
                    'message' => "{$pName} upgrade request has been submitted. Direct upgrade order generation is only available for Microsoft.",
                ]);
            }

            $result = $this->upgradeService->upgrade([
                'partner_id' => (int) $validated['partner_id'],
                'subscription_id' => $this->upgradeService->resolveSubscriptionId(
                    (int) $validated['partner_id'],
                    $subscriptionId,
                    isset($validated['customer_id']) ? (int) $validated['customer_id'] : null,
                    $validated['username'] ?? null,
                ),
                'plan_id' => (int) $validated['plan_id'],
                'quantity' => (int) ($validated['quantity'] ?? $validated['licenses'] ?? 1),
                'aadhaar_number' => (string) ($validated['aadhaar_number'] ?? $validated['aadhar_number']),
                'subtotal' => $validated['subtotal'] ?? null,
                'pro_rata_adjustment' => $validated['pro_rata_adjustment'] ?? null,
                'taxable_amount' => $validated['taxable_amount'] ?? null,
                'gst_amount' => $validated['gst_amount'] ?? null,
                'payable_amount' => $validated['payable_amount'] ?? null,
            ]);

            $order = $result['order'];
            $plan = Plan::query()->find($order->plan_id);
            $orderId = (int) ($order->id ?? 0);
            $creditUsed = round((float) ($order->credit_used ?? $result['summary']['payable_amount'] ?? 0), 2);
            if ($orderId > 0) {
                Order::query()->where('id', $orderId)->update([
                    'status' => 'completed',
                    'updated_at' => now(),
                ]);
            }

            $data = [
                'order_id' => $orderId,
                'order_no' => (string) ($order->order_no ?? $order->id),
                'customer_id' => (int) ($order->user_id ?? 0),
                'previous_subscription_id' => $subscriptionId,
                'plan_id' => (int) $order->plan_id,
                'plan_name' => (string) ($plan->name ?? $order->plan_name ?? ''),
                'licenses' => (int) ($order->quantity ?? 1),
                'subscription_start_date' => $order->subscription_start_date ?? null,
                'subscription_end_date' => $order->subscription_end_date ?? null,
                'summary' => $result['summary'],
                'available_credits_before' => round((float) $result['available_credits'], 2),
                'credits_deducted' => $creditUsed,
                'credits_used' => $creditUsed,
                'credit_used' => $creditUsed,
            ];

            $billing = app(BillingController::class);
            try {
                $billing->ensureGeneratedPoForOrder($orderId);
                $billing->persistOrderPoPdfToStorage($orderId);
                if (method_exists($billing, 'persistOrderInvoicePdfToStorage')) {
                    $billing->persistOrderInvoicePdfToStorage($orderId);
                }
                $billing->markInvoiceStatusPendingForOrder($orderId);
                $data['invoice_status'] = 'pending';
                $links = $billing->getOrderDocumentLinks($orderId);
                if (!empty($links['po_link'])) {
                    $data['po_link'] = $links['po_link'];
                }
                if (!empty($links['invoice_link'])) {
                    $data['invoice_link'] = $links['invoice_link'];
                }
                if (!empty($links['po_number'])) {
                    $data['po_number'] = $links['po_number'];
                }
            } catch (\Throwable $e) {
                Log::error('API upgrade PO/invoice persist failed', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Upgrade completed successfully. New order and invoice generated.',
                'data' => $data,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process upgrade: ' . $e->getMessage(),
            ], 500);
        }
    }
}
