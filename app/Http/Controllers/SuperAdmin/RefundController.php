<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\InvoiceMst;
use App\Models\InvoicePaymentTransaction;
use App\Models\InvoicePaymentTransactionItem;
use App\Models\Order;
use App\Models\Partner;
use App\Models\PaymentRefund;
use App\Services\RazorpayRefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class RefundController extends Controller
{
    public function index(Request $request)
    {
        $status = strtolower(trim((string) $request->get('status', 'all')));
        $q = trim((string) $request->get('q', ''));

        $rows = $this->buildCancelledPaidQueue()
            ->filter(fn (array $row) => $this->shouldShowRefundRow($row))
            ->values();

        if ($q !== '') {
            $needle = strtolower($q);
            $rows = $rows->filter(function (array $row) use ($needle, $q) {
                $hay = strtolower(implode(' ', array_filter([
                    (string) ($row['id'] ?? ''),
                    (string) ($row['order_id'] ?? ''),
                    (string) ($row['invoice_id'] ?? ''),
                    (string) ($row['invoice_no'] ?? ''),
                    (string) ($row['order_no'] ?? ''),
                    (string) ($row['partner_name'] ?? ''),
                    (string) ($row['domain_name'] ?? ''),
                    (string) ($row['razorpay_payment_id'] ?? ''),
                    (string) ($row['reason'] ?? ''),
                ])));

                if (str_contains($hay, $needle)) {
                    return true;
                }

                return ctype_digit($q) && (
                    (int) ($row['order_id'] ?? 0) === (int) $q
                    || (int) ($row['invoice_id'] ?? 0) === (int) $q
                    || (int) ($row['partner_id'] ?? 0) === (int) $q
                    || (int) ($row['refund_id'] ?? 0) === (int) $q
                );
            })->values();
        }

        $counts = [
            'all' => $rows->count(),
            'processing' => $rows->whereIn('bucket', ['processing', 'pending_refund'])->count(),
            'processed' => $rows->where('bucket', 'processed')->count(),
            'failed' => $rows->where('bucket', 'failed')->count(),
        ];

        if ($status === 'processed') {
            $rows = $rows->where('bucket', 'processed')->values();
        } elseif ($status === 'failed') {
            $rows = $rows->where('bucket', 'failed')->values();
        } elseif ($status === 'processing') {
            $rows = $rows->whereIn('bucket', ['processing', 'pending_refund'])->values();
        }

        return view('superadmin.refunds.index', [
            'rows' => $rows,
            'status' => $status,
            'q' => $q,
            'counts' => $counts,
            'tableMissing' => !$this->refundsTableReady(),
        ]);
    }

    public function retry(Request $request, int $id)
    {
        if (!$this->refundsTableReady()) {
            return back()->with('error', 'Refunds table is not installed.');
        }

        $request->validate([
            'reason' => 'nullable|string|min:5|max:500',
        ]);

        $failed = PaymentRefund::query()->findOrFail($id);
        if (strtolower(trim((string) $failed->status)) !== PaymentRefund::STATUS_FAILED) {
            return back()->with('warning', 'Only failed refunds can be retried.');
        }

        $reason = trim((string) $request->input('reason', ''));
        if ($reason === '') {
            $reason = trim((string) ($failed->reason ?? '')) ?: 'Retry refund from superadmin';
        }

        try {
            $payload = [
                'amount' => (float) $failed->amount,
                'reason' => $reason,
                'requested_by' => 'superadmin_retry',
                'on_cancellation' => true,
            ];

            if (!empty($failed->order_id)) {
                $payload['order_id'] = (int) $failed->order_id;
            } elseif (!empty($failed->invoice_payment_transaction_id)) {
                $payload['invoice_payment_transaction_id'] = (int) $failed->invoice_payment_transaction_id;
            } elseif (!empty($failed->razorpay_payment_id)) {
                $payload['razorpay_payment_id'] = (string) $failed->razorpay_payment_id;
            } else {
                return back()->with('error', 'Failed refund has no payment reference to retry.');
            }

            $refund = app(RazorpayRefundService::class)->refund($payload);

            return back()->with(
                'success',
                'Refund #' . $refund->id . ' processed (₹' . number_format((float) $refund->amount, 2) . ').'
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::warning('Superadmin refund retry failed', [
                'refund_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Retry failed: ' . $e->getMessage());
        }
    }

    public function store(Request $request)
    {
        if (!$this->refundsTableReady()) {
            return back()->with('error', 'Refunds table is not installed.');
        }

        $request->validate([
            'order_id' => 'nullable|integer|min:1',
            'invoice_payment_transaction_id' => 'nullable|integer|min:1',
            'razorpay_payment_id' => 'nullable|string|max:100',
            'amount' => 'nullable|numeric|min:1',
            'reason' => 'required|string|min:5|max:500',
        ]);

        if (!$request->filled('order_id')
            && !$request->filled('invoice_payment_transaction_id')
            && !$request->filled('razorpay_payment_id')) {
            return back()->with('error', 'Provide order id, invoice payment transaction id, or Razorpay payment id.');
        }

        try {
            $refund = app(RazorpayRefundService::class)->refund([
                'order_id' => $request->input('order_id'),
                'invoice_payment_transaction_id' => $request->input('invoice_payment_transaction_id'),
                'razorpay_payment_id' => $request->input('razorpay_payment_id'),
                'amount' => $request->input('amount'),
                'reason' => $request->input('reason'),
                'requested_by' => 'superadmin',
                'on_cancellation' => true,
            ]);

            return redirect()
                ->route('superadmin.refunds.index', ['status' => 'processed'])
                ->with('success', 'Refund #' . $refund->id . ' of ₹' . number_format((float) $refund->amount, 2) . ' processed.');
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::warning('Superadmin manual refund failed', ['error' => $e->getMessage()]);

            return back()->withInput()->with('error', 'Refund failed: ' . $e->getMessage());
        }
    }

    /**
     * One row per cancelled-after-payment invoice/order, merged with latest Razorpay refund attempt.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function buildCancelledPaidQueue(): Collection
    {
        $byKey = [];

        $cancelledOrders = Order::query()
            ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) IN ('rejected', 'cancelled', 'canceled', 'failed', 'void')")
            ->orderByDesc('id')
            ->limit(500)
            ->get([
                'id', 'order_no', 'partner_id', 'domain_name', 'status',
                'credit_used', 'razorpay_payment_id', 'total_amount', 'updated_at', 'created_at',
            ]);

        $orderIds = $cancelledOrders->pluck('id')->map(fn ($id) => (int) $id)->all();
        $invoicesByOrder = collect();
        if ($orderIds !== [] && $this->invoiceTableReady()) {
            $invoicesByOrder = InvoiceMst::query()
                ->whereIn('order_id', $orderIds)
                ->orderByDesc('id')
                ->get()
                ->groupBy(fn ($inv) => (int) $inv->order_id);
        }

        // Also pull cancelled invoices whose order may not yet be flagged (stale).
        $extraCancelledInvoices = collect();
        if ($this->invoiceTableReady()) {
            $extraCancelledInvoices = InvoiceMst::query()
                ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) IN ('cancelled', 'canceled', 'failed', 'rejected', 'void')")
                ->when($orderIds !== [], fn ($q) => $q->whereNotIn('order_id', $orderIds))
                ->orderByDesc('id')
                ->limit(300)
                ->get();
        }

        $extraOrderIds = $extraCancelledInvoices
            ->pluck('order_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $extraOrders = $extraOrderIds === []
            ? collect()
            : Order::query()->whereIn('id', $extraOrderIds)->get()->keyBy('id');

        $allOrderIds = array_values(array_unique(array_merge($orderIds, $extraOrderIds)));
        $paymentByOrder = $this->razorpayPaymentIdsByOrder($allOrderIds);
        $refundsByOrder = $this->latestRefundsByOrder($allOrderIds);
        $refundsByPayment = $this->latestRefundsByPayment(
            collect($paymentByOrder)->flatten()->filter()->unique()->values()->all()
        );

        $partnerIds = $cancelledOrders->pluck('partner_id')
            ->merge($extraOrders->pluck('partner_id'))
            ->merge($extraCancelledInvoices->pluck('partner_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $partnersById = $partnerIds === []
            ? collect()
            : Partner::query()->whereIn('id', $partnerIds)->get()->keyBy('id');

        foreach ($cancelledOrders as $order) {
            $orderId = (int) $order->id;
            $invoices = $invoicesByOrder->get($orderId, collect());
            if ($invoices->isEmpty()) {
                $this->pushQueueRow($byKey, $order, null, $partnersById, $paymentByOrder, $refundsByOrder, $refundsByPayment);
                continue;
            }
            foreach ($invoices as $invoice) {
                $this->pushQueueRow($byKey, $order, $invoice, $partnersById, $paymentByOrder, $refundsByOrder, $refundsByPayment);
            }
        }

        foreach ($extraCancelledInvoices as $invoice) {
            $order = $extraOrders->get((int) ($invoice->order_id ?? 0));
            $this->pushQueueRow($byKey, $order, $invoice, $partnersById, $paymentByOrder, $refundsByOrder, $refundsByPayment);
        }

        // Keep orphan Razorpay refund attempts only when they are real processed/actionable rows.
        if ($this->refundsTableReady()) {
            $orphanRefunds = PaymentRefund::query()->orderByDesc('id')->limit(200)->get();
            foreach ($orphanRefunds as $refund) {
                $oid = (int) ($refund->order_id ?? 0);
                $key = $oid > 0
                    ? 'order:' . $oid
                    : 'refund:' . $refund->id;
                if (isset($byKey[$key]) && $oid > 0) {
                    continue;
                }
                if ($oid > 0 && collect($byKey)->contains(fn ($r) => (int) ($r['order_id'] ?? 0) === $oid)) {
                    continue;
                }

                $st = strtolower(trim((string) ($refund->status ?? '')));
                $failure = (string) ($refund->failure_message ?? '');
                // Hide auth-noise orphans; uncaptured with order still appear via cancelled-invoice queue.
                if ($st === PaymentRefund::STATUS_FAILED && $this->isAuthNoiseRefundFailure($failure)) {
                    continue;
                }
                if ($st === PaymentRefund::STATUS_FAILED && $this->isUncapturedRefundFailure($failure)) {
                    // Prefer cancelled-invoice rows; skip duplicate orphan listing.
                    if ($oid > 0) {
                        continue;
                    }
                }

                $partner = $partnersById->get((int) ($refund->partner_id ?? 0))
                    ?: Partner::query()->find((int) ($refund->partner_id ?? 0));
                $bucket = match ($st) {
                    PaymentRefund::STATUS_PROCESSED => 'processed',
                    PaymentRefund::STATUS_FAILED => 'failed',
                    PaymentRefund::STATUS_PENDING, PaymentRefund::STATUS_PROCESSING => 'processing',
                    default => 'pending_refund',
                };

                $byKey['refund:' . $refund->id] = [
                    'id' => 'R' . $refund->id,
                    'refund_id' => (int) $refund->id,
                    'invoice_id' => null,
                    'invoice_no' => '—',
                    'order_id' => $oid > 0 ? $oid : null,
                    'order_no' => $oid > 0 ? ('#' . $oid) : '—',
                    'domain_name' => '',
                    'partner_id' => (int) ($refund->partner_id ?? 0) ?: null,
                    'partner_name' => $partner?->name ?? $partner?->company_name ?? '—',
                    'amount' => (float) $refund->amount,
                    'payment_type' => 'razorpay',
                    'razorpay_payment_id' => (string) ($refund->razorpay_payment_id ?? ''),
                    'razorpay_refund_id' => (string) ($refund->razorpay_refund_id ?? ''),
                    'bucket' => $bucket,
                    'status_label' => ucfirst($st ?: 'Unknown'),
                    'failure_message' => $failure,
                    'reason' => (string) ($refund->reason ?? ''),
                    'date' => $refund->created_at,
                    'can_retry' => $st === PaymentRefund::STATUS_FAILED,
                    'can_refund' => false,
                    'is_partial' => (bool) $refund->is_partial,
                ];
            }
        }

        return collect(array_values($byKey))
            ->sortByDesc(function (array $row) {
                $d = $row['date'] ?? null;

                return $d ? strtotime((string) $d) : 0;
            })
            ->values();
    }

    private function pushQueueRow(
        array &$byKey,
        mixed $order,
        mixed $invoice,
        $partnersById,
        array $paymentByOrder,
        array $refundsByOrder,
        array $refundsByPayment
    ): void {
        $orderId = (int) ($order->id ?? $invoice->order_id ?? 0);
        $invoiceId = (int) ($invoice->id ?? 0);
        $key = $invoiceId > 0 ? 'invoice:' . $invoiceId : ('order:' . $orderId);
        if ($key === 'order:0' || isset($byKey[$key])) {
            return;
        }

        $creditUsed = (float) ($order->credit_used ?? 0);
        $rzFromOrder = trim((string) ($order->razorpay_payment_id ?? ''));
        $rzFromTxn = $orderId > 0 ? trim((string) ($paymentByOrder[$orderId] ?? '')) : '';
        $razorpayPaymentId = $rzFromOrder !== '' ? $rzFromOrder : $rzFromTxn;
        $invoiceStatus = strtolower(trim((string) ($invoice->status ?? '')));
        $invoiceWasPaid = in_array($invoiceStatus, ['paid', 'success', 'completed', 'succeeded'], true);

        // Only Razorpay cancelled-after-payment (hide wallet-only).
        if ($razorpayPaymentId === '') {
            return;
        }

        $partnerId = (int) ($order->partner_id ?? $invoice->partner_id ?? 0);
        $partner = $partnersById->get($partnerId);

        $refund = null;
        if ($orderId > 0 && isset($refundsByOrder[$orderId])) {
            $refund = $refundsByOrder[$orderId];
        } elseif ($razorpayPaymentId !== '' && isset($refundsByPayment[$razorpayPaymentId])) {
            $refund = $refundsByPayment[$razorpayPaymentId];
        }

        $paymentType = $creditUsed > 0.009 ? 'mixed' : 'razorpay';

        $bucket = 'pending_refund';
        $statusLabel = 'Refund Soon';
        $failure = '';
        $canRetry = false;
        $canRefund = false;
        $refundId = null;
        $rzRefundId = '';
        $reason = 'Order cancelled after payment';
        $isPartial = false;

        if ($refund) {
            $refundId = (int) $refund->id;
            $rzRefundId = (string) ($refund->razorpay_refund_id ?? '');
            $reason = (string) ($refund->reason ?? $reason);
            $failure = (string) ($refund->failure_message ?? '');
            $isPartial = (bool) $refund->is_partial;
            $st = strtolower(trim((string) ($refund->status ?? '')));
            if ($st === PaymentRefund::STATUS_PROCESSED) {
                $bucket = 'processed';
                $statusLabel = 'Processed';
            } elseif ($st === PaymentRefund::STATUS_FAILED) {
                // Auth-noise only: hide. Uncaptured payments still list as Refund Soon + Retry.
                if ($this->isAuthNoiseRefundFailure($failure)) {
                    return;
                }
                if ($this->isUncapturedRefundFailure($failure)) {
                    $bucket = 'pending_refund';
                    $statusLabel = 'Refund Soon';
                    $failure = '';
                    $canRetry = true;
                    $canRefund = false;
                } else {
                    $bucket = 'failed';
                    $statusLabel = 'Failed';
                    $canRetry = true;
                }
            } elseif (in_array($st, [PaymentRefund::STATUS_PENDING, PaymentRefund::STATUS_PROCESSING], true)) {
                $bucket = 'processing';
                $statusLabel = ucfirst($st);
            } else {
                $bucket = 'pending_refund';
                $statusLabel = 'Refund Soon';
                $canRefund = true;
            }
        } else {
            $bucket = 'pending_refund';
            $statusLabel = 'Refund Soon';
            $canRefund = true;
        }

        $amount = (float) ($invoice->net_amt ?? 0);
        if ($amount <= 0) {
            $amount = (float) ($order->total_amount ?? 0);
        }
        if ($amount <= 0 && $creditUsed > 0) {
            $amount = $creditUsed;
        }

        $date = $invoice->updated_at ?? $invoice->created_at ?? $order->updated_at ?? $order->created_at ?? null;

        $byKey[$key] = [
            'id' => $invoiceId > 0 ? $invoiceId : ('O' . $orderId),
            'refund_id' => $refundId,
            'invoice_id' => $invoiceId > 0 ? $invoiceId : null,
            'invoice_no' => trim((string) ($invoice->bill_no_full ?? '')) ?: '—',
            'order_id' => $orderId > 0 ? $orderId : null,
            'order_no' => trim((string) ($order->order_no ?? '')) ?: ($orderId > 0 ? '#' . $orderId : '—'),
            'domain_name' => trim((string) ($order->domain_name ?? '')),
            'partner_id' => $partnerId > 0 ? $partnerId : null,
            'partner_name' => $partner?->name ?? $partner?->company_name ?? ($partnerId ? '#' . $partnerId : '—'),
            'amount' => $amount,
            'payment_type' => $paymentType,
            'razorpay_payment_id' => $razorpayPaymentId,
            'razorpay_refund_id' => $rzRefundId,
            'bucket' => $bucket,
            'status_label' => $statusLabel,
            'failure_message' => $failure,
            'reason' => $reason,
            'date' => $date,
            'can_retry' => $canRetry,
            'can_refund' => $canRefund,
            'is_partial' => $isPartial,
            'credit_used' => $creditUsed,
        ];
    }

    /**
     * @param  array<int, int>  $orderIds
     * @return array<int, string>
     */
    private function razorpayPaymentIdsByOrder(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        try {
            if (!Schema::connection('pgsql_billing')->hasTable('invoice_payment_transaction_items')) {
                return [];
            }

            $invoiceOrder = InvoiceMst::query()
                ->whereIn('order_id', $orderIds)
                ->pluck('order_id', 'id');

            $invoiceIds = $invoiceOrder->keys()->map(fn ($id) => (int) $id)->all();
            if ($invoiceIds === []) {
                return [];
            }

            $items = InvoicePaymentTransactionItem::query()
                ->whereIn('invoice_id', $invoiceIds)
                ->get(['invoice_id', 'invoice_payment_transaction_id']);

            $txnIds = $items->pluck('invoice_payment_transaction_id')->map(fn ($id) => (int) $id)->unique()->all();
            if ($txnIds === []) {
                return [];
            }

            $txns = InvoicePaymentTransaction::query()
                ->whereIn('id', $txnIds)
                ->whereRaw("LOWER(TRIM(COALESCE(gateway, ''))) = 'razorpay'")
                ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) IN ('success', 'paid', 'captured', 'completed')")
                ->get(['id', 'gateway_payment_id'])
                ->keyBy('id');

            $map = [];
            foreach ($items as $item) {
                $txn = $txns->get((int) $item->invoice_payment_transaction_id);
                if (!$txn) {
                    continue;
                }
                $payId = trim((string) ($txn->gateway_payment_id ?? ''));
                if ($payId === '') {
                    continue;
                }
                $oid = (int) ($invoiceOrder[(int) $item->invoice_id] ?? 0);
                if ($oid > 0 && !isset($map[$oid])) {
                    $map[$oid] = $payId;
                }
            }

            return $map;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param  array<int, int>  $orderIds
     * @return array<int, PaymentRefund>
     */
    private function latestRefundsByOrder(array $orderIds): array
    {
        if ($orderIds === [] || !$this->refundsTableReady()) {
            return [];
        }

        $map = [];
        PaymentRefund::query()
            ->whereIn('order_id', $orderIds)
            ->orderByDesc('id')
            ->get()
            ->each(function (PaymentRefund $r) use (&$map) {
                $oid = (int) $r->order_id;
                if ($oid > 0 && !isset($map[$oid])) {
                    $map[$oid] = $r;
                }
            });

        return $map;
    }

    /**
     * @param  array<int, string>  $paymentIds
     * @return array<string, PaymentRefund>
     */
    private function latestRefundsByPayment(array $paymentIds): array
    {
        $paymentIds = array_values(array_filter(array_map('strval', $paymentIds)));
        if ($paymentIds === [] || !$this->refundsTableReady()) {
            return [];
        }

        $map = [];
        PaymentRefund::query()
            ->whereIn('razorpay_payment_id', $paymentIds)
            ->orderByDesc('id')
            ->get()
            ->each(function (PaymentRefund $r) use (&$map) {
                $pid = trim((string) $r->razorpay_payment_id);
                if ($pid !== '' && !isset($map[$pid])) {
                    $map[$pid] = $r;
                }
            });

        return $map;
    }

    /**
     * Hide wallet-only and auth-noise rows. Uncaptured cancelled Razorpay invoices stay visible.
     *
     * @param  array<string, mixed>  $row
     */
    private function shouldShowRefundRow(array $row): bool
    {
        if (($row['bucket'] ?? '') === 'wallet') {
            return false;
        }

        if (trim((string) ($row['razorpay_payment_id'] ?? '')) === '' && ($row['bucket'] ?? '') !== 'processed') {
            return false;
        }

        if (($row['bucket'] ?? '') === 'failed' && $this->isAuthNoiseRefundFailure((string) ($row['failure_message'] ?? ''))) {
            return false;
        }

        return true;
    }

    private function isUncapturedRefundFailure(string $message): bool
    {
        $m = strtolower($message);

        return $m !== '' && (
            str_contains($m, 'captured for action')
            || str_contains($m, 'should be captured')
            || str_contains($m, 'payment status should be captured')
        );
    }

    private function isAuthNoiseRefundFailure(string $message): bool
    {
        return str_contains(strtolower($message), 'authentication failed');
    }

    private function isNonActionableRefundFailure(string $message): bool
    {
        return $this->isUncapturedRefundFailure($message) || $this->isAuthNoiseRefundFailure($message);
    }

    private function refundsTableReady(): bool
    {
        try {
            return Schema::connection('pgsql_billing')->hasTable('payment_refunds');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function invoiceTableReady(): bool
    {
        try {
            return Schema::connection('pgsql_billing')->hasTable('invoice_mst');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
