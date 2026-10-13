<?php

namespace App\Services;

use App\Models\InvoiceMst;
use App\Models\InvoicePaymentTransaction;
use App\Models\InvoicePaymentTransactionItem;
use App\Models\Order;
use App\Models\PaymentRefund;
use App\Models\PvSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

/**
 * Razorpay refund policy + API caller.
 *
 * Policy (configurable via config/services.php → razorpay_refund):
 * - Only Razorpay online payments can be refunded
 * - Full or partial refunds allowed
 * - Refund only within N days of payment/order (default 30)
 * - Cannot refund more than paid minus already-processed refunds
 * - Reason required (min length)
 */
class RazorpayRefundService
{
    /**
     * Full remaining refund(s) when an order is cancelled after online payment.
     * Tries order.razorpay_payment_id and any successful invoice payment transactions for the order.
     * Soft-fails (logs) so cancellation is never blocked by refund API issues.
     *
     * @return list<array{ok: bool, message: string, refund_id?: int, payment_id?: string}>
     */
    public function refundOnOrderCancellation(int $orderId, string $reason = 'Order cancelled after payment'): array
    {
        $orderId = (int) $orderId;
        if ($orderId <= 0) {
            return [['ok' => false, 'message' => 'Invalid order id.']];
        }

        $reason = trim($reason);
        $minReason = (int) config('services.razorpay_refund.min_reason_length', 5);
        if (strlen($reason) < $minReason) {
            $reason = 'Order cancelled after payment — automatic refund';
        }

        $targets = $this->resolveCancellationRefundTargets($orderId);
        if ($targets === []) {
            return [['ok' => false, 'message' => 'No Razorpay payment found for this order (wallet-only or unpaid).']];
        }

        $results = [];
        $seenPayments = [];

        foreach ($targets as $target) {
            try {
                $context = $this->resolvePaymentContext(array_merge($target, [
                    'amount' => null,
                    'reason' => $reason,
                ]));
                $paymentId = (string) ($context['razorpay_payment_id'] ?? '');
                if ($paymentId === '' || isset($seenPayments[$paymentId])) {
                    continue;
                }
                $seenPayments[$paymentId] = true;

                if ((float) ($context['refundable_amount'] ?? 0) <= 0) {
                    $results[] = [
                        'ok' => true,
                        'message' => 'Already fully refunded.',
                        'payment_id' => $paymentId,
                    ];
                    continue;
                }

                $refund = $this->refund([
                    ...$target,
                    'amount' => null,
                    'reason' => $reason,
                    'requested_by' => 'system_order_cancel',
                    'on_cancellation' => true,
                ]);

                $results[] = [
                    'ok' => true,
                    'message' => 'Refund processed.',
                    'refund_id' => (int) $refund->id,
                    'payment_id' => $paymentId,
                    'amount' => (float) $refund->amount,
                ];
            } catch (\Throwable $e) {
                Log::warning('Auto refund on order cancel failed', [
                    'order_id' => $orderId,
                    'target' => $target,
                    'error' => $e->getMessage(),
                ]);
                $results[] = [
                    'ok' => false,
                    'message' => $e->getMessage(),
                    'payment_id' => $target['razorpay_payment_id'] ?? null,
                ];
            }
        }

        return $results !== [] ? $results : [['ok' => false, 'message' => 'No refundable Razorpay payment found.']];
    }

    /**
     * @return list<array{order_id?: int, invoice_payment_transaction_id?: int}>
     */
    private function resolveCancellationRefundTargets(int $orderId): array
    {
        $targets = [];

        $order = Order::query()->whereKey($orderId)->first();
        if ($order && trim((string) ($order->razorpay_payment_id ?? '')) !== '') {
            $targets[] = ['order_id' => $orderId];
        }

        $invoiceIds = InvoiceMst::query()
            ->where('order_id', $orderId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        if ($invoiceIds === []) {
            return $targets;
        }

        $txnIds = InvoicePaymentTransactionItem::query()
            ->whereIn('invoice_id', $invoiceIds)
            ->pluck('invoice_payment_transaction_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        foreach ($txnIds as $txnId) {
            $txn = InvoicePaymentTransaction::query()->whereKey($txnId)->first();
            if (!$txn) {
                continue;
            }
            $gateway = strtolower(trim((string) ($txn->gateway ?? '')));
            $status = strtolower(trim((string) ($txn->status ?? '')));
            $paymentId = trim((string) ($txn->gateway_payment_id ?? ''));
            if ($gateway !== 'razorpay' || $paymentId === '') {
                continue;
            }
            if (!in_array($status, ['success', 'paid', 'captured', 'completed'], true)) {
                continue;
            }
            $targets[] = ['invoice_payment_transaction_id' => $txnId];
        }

        return $targets;
    }

    /**
     * Evaluate whether a refund is allowed (does not call Razorpay).
     *
     * @return array{allowed: bool, message: string, context?: array<string, mixed>}
     */
    public function evaluatePolicy(array $input): array
    {
        try {
            $context = $this->resolvePaymentContext($input);
            $this->assertPolicy($context, $input);

            return [
                'allowed' => true,
                'message' => 'Refund is allowed under current policy.',
                'context' => [
                    'source_type' => $context['source_type'],
                    'source_id' => $context['source_id'],
                    'razorpay_payment_id' => $context['razorpay_payment_id'],
                    'paid_amount' => $context['paid_amount'],
                    'already_refunded' => $context['already_refunded'],
                    'refundable_amount' => $context['refundable_amount'],
                    'requested_amount' => $context['requested_amount'],
                    'is_partial' => $context['is_partial'],
                    'days_since_payment' => $context['days_since_payment'],
                    'max_days' => $this->maxDays(),
                ],
            ];
        } catch (InvalidArgumentException $e) {
            return [
                'allowed' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create a refund under policy and call Razorpay.
     *
     * @param  array{
     *   order_id?: int,
     *   invoice_payment_transaction_id?: int,
     *   razorpay_payment_id?: string,
     *   amount?: float|int|string|null,
     *   reason?: string,
     *   requested_by?: string
     * }  $input
     */
    public function refund(array $input): PaymentRefund
    {
        $context = $this->resolvePaymentContext($input);
        $this->assertPolicy($context, $input);

        $refund = PaymentRefund::query()->create([
            'source_type' => $context['source_type'],
            'source_id' => $context['source_id'],
            'partner_id' => $context['partner_id'],
            'order_id' => $context['order_id'],
            'invoice_payment_transaction_id' => $context['invoice_payment_transaction_id'],
            'razorpay_payment_id' => $context['razorpay_payment_id'],
            'amount' => $context['requested_amount'],
            'currency' => 'INR',
            'reason' => trim((string) ($input['reason'] ?? '')),
            'status' => PaymentRefund::STATUS_PROCESSING,
            'is_partial' => $context['is_partial'],
            'requested_by' => trim((string) ($input['requested_by'] ?? 'superadmin')),
            'meta' => [
                'policy' => [
                    'max_days' => $this->maxDays(),
                    'days_since_payment' => $context['days_since_payment'],
                    'paid_amount' => $context['paid_amount'],
                    'already_refunded' => $context['already_refunded'],
                    'refundable_amount' => $context['refundable_amount'],
                ],
            ],
        ]);

        try {
            $rz = $this->callRazorpayRefund(
                $context['razorpay_payment_id'],
                $context['requested_amount'],
                trim((string) ($input['reason'] ?? ''))
            );

            $refund->razorpay_refund_id = (string) ($rz['id'] ?? '');
            $refund->status = PaymentRefund::STATUS_PROCESSED;
            $refund->processed_at = now();
            $refund->meta = array_merge((array) $refund->meta, [
                'razorpay_response' => $rz,
            ]);
            $refund->save();

            $this->markSourceRefunded($context, $refund);

            return $refund->fresh();
        } catch (\Throwable $e) {
            $refund->status = PaymentRefund::STATUS_FAILED;
            $refund->failure_message = $e->getMessage();
            $refund->save();

            Log::warning('Razorpay refund failed', [
                'refund_id' => $refund->id,
                'payment_id' => $context['razorpay_payment_id'],
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function resolvePaymentContext(array $input): array
    {
        $orderId = (int) ($input['order_id'] ?? 0);
        $txnId = (int) ($input['invoice_payment_transaction_id'] ?? 0);
        $paymentId = trim((string) ($input['razorpay_payment_id'] ?? ''));

        if ($orderId > 0) {
            $order = Order::query()->whereKey($orderId)->first();
            if (!$order) {
                throw new InvalidArgumentException('Order not found.');
            }

            $rzPaymentId = trim((string) ($order->razorpay_payment_id ?? ''));
            $txnIdForOrder = 0;

            // Invoice-page payments store pay_id on invoice_payment_transactions, not always on orders.
            if ($rzPaymentId === '' && $paymentId !== '') {
                $rzPaymentId = $paymentId;
            }
            if ($rzPaymentId === '') {
                [$rzPaymentId, $txnIdForOrder] = $this->findRazorpayPaymentForOrder($orderId);
            }

            if ($rzPaymentId === '') {
                throw new InvalidArgumentException(
                    'No Razorpay payment found for this order. Only online Razorpay payments can be refunded (wallet/credit payments are restored on cancel).'
                );
            }

            // Prefer invoice-payment source when the pay_id lives on a txn.
            if ($txnIdForOrder <= 0 && $paymentId !== '') {
                $txn = InvoicePaymentTransaction::query()
                    ->where('gateway_payment_id', $rzPaymentId)
                    ->orderByDesc('id')
                    ->first();
                if ($txn) {
                    $txnIdForOrder = (int) $txn->id;
                }
            }

            if ($txnIdForOrder > 0) {
                return $this->resolvePaymentContext([
                    'invoice_payment_transaction_id' => $txnIdForOrder,
                    'amount' => $input['amount'] ?? null,
                    'reason' => $input['reason'] ?? null,
                    'on_cancellation' => $input['on_cancellation'] ?? null,
                ]);
            }

            $paidAmount = round((float) ($order->total_amount ?? 0), 2);
            if ($paidAmount <= 0) {
                throw new InvalidArgumentException('Order paid amount is invalid for refund.');
            }

            $already = $this->sumProcessedRefundsForPayment($rzPaymentId);
            $refundable = round(max(0, $paidAmount - $already), 2);
            $requested = $this->resolveRequestedAmount($input, $refundable);
            $paidAt = $order->updated_at ?? $order->created_at ?? now();

            return [
                'source_type' => PaymentRefund::SOURCE_ORDER,
                'source_id' => (int) $order->id,
                'partner_id' => (int) ($order->partner_id ?? 0) ?: null,
                'order_id' => (int) $order->id,
                'invoice_payment_transaction_id' => null,
                'razorpay_payment_id' => $rzPaymentId,
                'paid_amount' => $paidAmount,
                'already_refunded' => $already,
                'refundable_amount' => $refundable,
                'requested_amount' => $requested,
                'is_partial' => $requested < $refundable,
                'paid_at' => $paidAt,
                'days_since_payment' => now()->diffInDays($paidAt),
                'order' => $order,
            ];
        }

        if ($txnId > 0) {
            $txn = InvoicePaymentTransaction::query()->whereKey($txnId)->first();
            if (!$txn) {
                throw new InvalidArgumentException('Invoice payment transaction not found.');
            }

            $rzPaymentId = trim((string) ($txn->gateway_payment_id ?? ''));
            if ($rzPaymentId === '' || strtolower((string) ($txn->gateway ?? '')) !== 'razorpay') {
                throw new InvalidArgumentException('Transaction has no Razorpay payment id.');
            }

            if (strtolower((string) ($txn->status ?? '')) !== 'success'
                && strtolower((string) ($txn->status ?? '')) !== 'paid') {
                // allow success/paid; some rows may use "captured"
                $status = strtolower((string) ($txn->status ?? ''));
                if (!in_array($status, ['success', 'paid', 'captured', 'completed'], true)) {
                    throw new InvalidArgumentException('Only successful Razorpay payments can be refunded. Current status: ' . $status);
                }
            }

            $paidAmount = round((float) ($txn->total_amount ?? 0), 2);
            if ($paidAmount <= 0) {
                throw new InvalidArgumentException('Transaction amount is invalid for refund.');
            }

            $already = $this->sumProcessedRefundsForPayment($rzPaymentId);
            $refundable = round(max(0, $paidAmount - $already), 2);
            $requested = $this->resolveRequestedAmount($input, $refundable);
            $paidAt = $txn->updated_at ?? $txn->created_at ?? now();

            $linkedOrderId = (int) ($txn->order_id ?? 0);
            if ($linkedOrderId <= 0) {
                $invoiceIds = InvoicePaymentTransactionItem::query()
                    ->where('invoice_payment_transaction_id', (int) $txn->id)
                    ->pluck('invoice_id')
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->values()
                    ->all();
                if ($invoiceIds !== []) {
                    $linkedOrderId = (int) (InvoiceMst::query()
                        ->whereIn('id', $invoiceIds)
                        ->whereNotNull('order_id')
                        ->orderByDesc('id')
                        ->value('order_id') ?? 0);
                }
            }

            return [
                'source_type' => PaymentRefund::SOURCE_INVOICE_PAYMENT,
                'source_id' => (int) $txn->id,
                'partner_id' => (int) ($txn->partner_id ?? 0) ?: null,
                'order_id' => $linkedOrderId > 0 ? $linkedOrderId : null,
                'invoice_payment_transaction_id' => (int) $txn->id,
                'razorpay_payment_id' => $rzPaymentId,
                'paid_amount' => $paidAmount,
                'already_refunded' => $already,
                'refundable_amount' => $refundable,
                'requested_amount' => $requested,
                'is_partial' => $requested < $refundable,
                'paid_at' => $paidAt,
                'days_since_payment' => now()->diffInDays($paidAt),
                'transaction' => $txn,
            ];
        }

        if ($paymentId !== '') {
            // Lookup by payment id: prefer invoice txn, then order
            $txn = InvoicePaymentTransaction::query()
                ->where('gateway_payment_id', $paymentId)
                ->orderByDesc('id')
                ->first();
            if ($txn) {
                return $this->resolvePaymentContext([
                    'invoice_payment_transaction_id' => (int) $txn->id,
                    'amount' => $input['amount'] ?? null,
                    'reason' => $input['reason'] ?? null,
                ]);
            }

            $order = Order::query()
                ->where('razorpay_payment_id', $paymentId)
                ->orderByDesc('id')
                ->first();
            if ($order) {
                return $this->resolvePaymentContext([
                    'order_id' => (int) $order->id,
                    'amount' => $input['amount'] ?? null,
                    'reason' => $input['reason'] ?? null,
                ]);
            }

            throw new InvalidArgumentException('No order or invoice payment found for this Razorpay payment id.');
        }

        throw new InvalidArgumentException('Provide order_id, invoice_payment_transaction_id, or razorpay_payment_id.');
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $input
     */
    private function assertPolicy(array $context, array $input): void
    {
        if (!$this->enabled()) {
            throw new InvalidArgumentException('Razorpay refunds are disabled.');
        }

        $reason = trim((string) ($input['reason'] ?? ''));
        $minReason = (int) config('services.razorpay_refund.min_reason_length', 5);
        if (strlen($reason) < $minReason) {
            throw new InvalidArgumentException("Refund reason is required (min {$minReason} characters).");
        }

        if ((float) $context['refundable_amount'] <= 0) {
            throw new InvalidArgumentException('Nothing left to refund for this payment.');
        }

        if ((float) $context['requested_amount'] <= 0) {
            throw new InvalidArgumentException('Refund amount must be greater than zero.');
        }

        if ((float) $context['requested_amount'] > (float) $context['refundable_amount'] + 0.001) {
            throw new InvalidArgumentException(
                'Refund amount exceeds refundable balance (₹' . number_format((float) $context['refundable_amount'], 2) . ').'
            );
        }

        $allowPartial = (bool) config('services.razorpay_refund.allow_partial', true);
        if (!$allowPartial && !empty($context['is_partial'])) {
            throw new InvalidArgumentException('Partial refunds are not allowed. Refund the full remaining amount.');
        }

        // Cancellation refunds always run (admin cancelled a paid order).
        if (!empty($input['on_cancellation'])) {
            return;
        }

        $maxDays = $this->maxDays();
        if ($maxDays > 0 && (int) $context['days_since_payment'] > $maxDays) {
            throw new InvalidArgumentException(
                "Refund window expired. Refunds allowed within {$maxDays} days of payment (this payment is {$context['days_since_payment']} days old)."
            );
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function resolveRequestedAmount(array $input, float $refundable): float
    {
        if (!array_key_exists('amount', $input) || $input['amount'] === null || $input['amount'] === '') {
            return $refundable; // full remaining
        }

        $amount = round((float) $input['amount'], 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Refund amount must be greater than zero.');
        }

        return $amount;
    }

    private function sumProcessedRefundsForPayment(string $razorpayPaymentId): float
    {
        return round((float) PaymentRefund::query()
            ->where('razorpay_payment_id', $razorpayPaymentId)
            ->where('status', PaymentRefund::STATUS_PROCESSED)
            ->sum('amount'), 2);
    }

    /**
     * Resolve Razorpay pay_id (+ optional invoice payment txn) for an order paid via partner invoice checkout.
     *
     * @return array{0: string, 1: int}
     */
    private function findRazorpayPaymentForOrder(int $orderId): array
    {
        if ($orderId <= 0) {
            return ['', 0];
        }

        $invoiceIds = InvoiceMst::query()
            ->where('order_id', $orderId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        if ($invoiceIds === []) {
            return ['', 0];
        }

        $txnIds = InvoicePaymentTransactionItem::query()
            ->whereIn('invoice_id', $invoiceIds)
            ->pluck('invoice_payment_transaction_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        if ($txnIds === []) {
            return ['', 0];
        }

        $txn = InvoicePaymentTransaction::query()
            ->whereIn('id', $txnIds)
            ->whereRaw("LOWER(TRIM(COALESCE(gateway, ''))) = 'razorpay'")
            ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) IN ('success', 'paid', 'captured', 'completed')")
            ->whereNotNull('gateway_payment_id')
            ->where('gateway_payment_id', '!=', '')
            ->orderByDesc('id')
            ->first();

        if (!$txn) {
            return ['', 0];
        }

        return [trim((string) $txn->gateway_payment_id), (int) $txn->id];
    }

    /**
     * @return array<string, mixed>
     */
    private function callRazorpayRefund(string $paymentId, float $amountInr, string $reason): array
    {
        [$key, $secret] = $this->credentials();
        if ($key === '' || $secret === '') {
            throw new RuntimeException('Razorpay credentials are missing.');
        }

        $amountPaise = (int) round($amountInr * 100);
        if ($amountPaise < 100) {
            throw new InvalidArgumentException('Minimum refund amount is ₹1.00.');
        }

        $payment = $this->fetchRazorpayPayment($paymentId, $key, $secret);
        $status = strtolower(trim((string) ($payment['status'] ?? '')));

        if ($status === 'authorized') {
            $captureAmount = (int) ($payment['amount'] ?? $amountPaise);
            $this->captureRazorpayPayment($paymentId, $captureAmount, $key, $secret);
            $status = 'captured';
        }

        if (in_array($status, ['refunded'], true)) {
            throw new InvalidArgumentException(
                'This Razorpay payment is already fully refunded. Check Razorpay Dashboard → Payments → ' . $paymentId
            );
        }

        if ($status !== 'captured') {
            throw new RuntimeException(
                'Razorpay payment must be captured before refund. Current status: '
                . ($status !== '' ? $status : 'unknown')
                . '. Open Razorpay Dashboard → Payments → '
                . $paymentId
                . ' and Capture, then click Retry here.'
            );
        }

        $payload = [
            'amount' => $amountPaise,
            'speed' => (string) config('services.razorpay_refund.speed', 'normal'),
        ];
        if ($reason !== '') {
            $payload['notes'] = [
                'reason' => mb_substr($reason, 0, 250),
            ];
        }

        $response = Http::withBasicAuth($key, $secret)
            ->acceptJson()
            ->timeout(30)
            ->post('https://api.razorpay.com/v1/payments/' . rawurlencode($paymentId) . '/refund', $payload);

        if (!$response->successful()) {
            $message = (string) data_get($response->json(), 'error.description', $response->body());
            if (stripos($message, 'authentication') !== false) {
                $message .= ' (key used: ' . $key . '). Check pv_setting.key_secret matches this key in Razorpay Test Mode.';
            }
            if (stripos($message, 'captured') !== false) {
                $message .= ' Open Razorpay Dashboard → Payments → ' . $paymentId
                    . ' → Capture the payment, then Retry.';
            }
            throw new RuntimeException('Razorpay refund API error: ' . $message);
        }

        $json = $response->json();
        if (!is_array($json) || empty($json['id'])) {
            throw new RuntimeException('Unexpected Razorpay refund response.');
        }

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchRazorpayPayment(string $paymentId, string $key, string $secret): array
    {
        $response = Http::withBasicAuth($key, $secret)
            ->acceptJson()
            ->timeout(30)
            ->get('https://api.razorpay.com/v1/payments/' . rawurlencode($paymentId));

        if (!$response->successful()) {
            $message = (string) data_get($response->json(), 'error.description', $response->body());
            throw new RuntimeException('Unable to fetch Razorpay payment: ' . $message);
        }

        $json = $response->json();
        if (!is_array($json)) {
            throw new RuntimeException('Unexpected Razorpay payment response.');
        }

        return $json;
    }

    private function captureRazorpayPayment(string $paymentId, int $amountPaise, string $key, string $secret): void
    {
        if ($amountPaise < 100) {
            throw new InvalidArgumentException('Invalid capture amount.');
        }

        $response = Http::withBasicAuth($key, $secret)
            ->acceptJson()
            ->timeout(30)
            ->asJson()
            ->post('https://api.razorpay.com/v1/payments/' . rawurlencode($paymentId) . '/capture', [
                'amount' => $amountPaise,
                'currency' => 'INR',
            ]);

        if (!$response->successful()) {
            $message = (string) data_get($response->json(), 'error.description', $response->body());
            throw new RuntimeException('Razorpay capture failed before refund: ' . $message);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function markSourceRefunded(array $context, PaymentRefund $refund): void
    {
        try {
            if ($context['source_type'] === PaymentRefund::SOURCE_ORDER && !empty($context['order'])) {
                /** @var Order $order */
                $order = $context['order'];
                // Soft marker only — do not force-cancel subscription unless product asks later.
                $note = trim((string) ($order->order_note ?? ''));
                $tag = '[refunded ₹' . number_format((float) $refund->amount, 2) . ' via ' . ($refund->razorpay_refund_id ?: 'razorpay') . ']';
                if (!str_contains($note, $tag)) {
                    $order->order_note = trim($note . ' ' . $tag);
                    $order->save();
                }
            }

            if ($context['source_type'] === PaymentRefund::SOURCE_INVOICE_PAYMENT && !empty($context['transaction'])) {
                /** @var InvoicePaymentTransaction $txn */
                $txn = $context['transaction'];
                $meta = is_array($txn->meta) ? $txn->meta : [];
                $meta['refunds'] = $meta['refunds'] ?? [];
                $meta['refunds'][] = [
                    'refund_row_id' => $refund->id,
                    'razorpay_refund_id' => $refund->razorpay_refund_id,
                    'amount' => (float) $refund->amount,
                    'at' => now()->toDateTimeString(),
                ];
                if ((float) $context['requested_amount'] >= (float) $context['refundable_amount'] - 0.001) {
                    $meta['fully_refunded'] = true;
                }
                $txn->meta = $meta;
                $txn->save();
            }
        } catch (\Throwable $e) {
            Log::warning('Refund source marker failed', [
                'refund_id' => $refund->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @return array{0: string, 1: string} */
    private function credentials(): array
    {
        return PvSetting::razorpayCredentials();
    }

    private function enabled(): bool
    {
        return (bool) config('services.razorpay_refund.enabled', true);
    }

    private function maxDays(): int
    {
        return max(0, (int) config('services.razorpay_refund.max_days', 30));
    }
}
