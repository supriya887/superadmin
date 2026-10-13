<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\InvoiceMst;
use App\Models\Order;
use App\Models\Partner;
use App\Models\PaymentRefund;
use App\Services\InvoiceManagement\InvoiceStatusNormalizer;
use App\Services\OrderCancellationCreditNoteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class InvoiceManagementController extends Controller
{
    public function index(): View
    {
        $partners = Partner::query()
            ->select(['id', 'name', 'company_name', 'email', 'status'])
            ->orderBy('name')
            ->get();

        $pendingCounts = $this->invoiceCountsByPartner('pending');
        $paidCounts = $this->invoiceCountsByPartner('paid');
        $cancelledCounts = $this->invoiceCountsByPartner('cancelled');

        $partners->each(function ($partner) use ($pendingCounts, $paidCounts, $cancelledCounts) {
            $partner->pending_invoices_count = (int) ($pendingCounts[(int) $partner->id] ?? 0);
            $partner->paid_invoices_count = (int) ($paidCounts[(int) $partner->id] ?? 0);
            $partner->cancelled_invoices_count = (int) ($cancelledCounts[(int) $partner->id] ?? 0);
        });

        return view('superadmin.invoice_management.partners', [
            'partners' => $partners,
        ]);
    }

    public function partner(Request $request, int $id): View
    {
        $partner = Partner::query()->findOrFail($id);
        $filterTab = strtolower(trim((string) $request->query('tab', 'all')));
        if (!in_array($filterTab, ['all', 'success', 'pending', 'fail'], true)) {
            $filterTab = 'all';
        }

        $invoices = collect();
        if ($this->invoiceTableAvailable()) {
            $rows = InvoiceMst::query()
                ->where('partner_id', $id)
                ->orderByDesc('id')
                ->get();

            $orderIds = $rows->pluck('order_id')
                ->map(fn ($oid) => (int) $oid)
                ->filter(fn ($oid) => $oid > 0)
                ->unique()
                ->values()
                ->all();

            $ordersById = $orderIds === []
                ? collect()
                : Order::query()
                    ->whereIn('id', $orderIds)
                    ->get(['id', 'status', 'credit_used', 'razorpay_payment_id', 'order_no', 'domain_name'])
                    ->keyBy('id');

            $refundOrderIds = $this->orderIdsWithRefunds($orderIds);
            $paidInvoiceIds = $this->paidInvoiceIdsFromTransactions(
                $rows->pluck('id')->map(fn ($invId) => (int) $invId)->filter()->values()->all()
            );
            $billing = app(BillingController::class);
            $staleCancelIds = [];
            $stalePaidIds = [];

            $invoices = $rows->map(function ($invoice) use (
                $partner,
                $billing,
                $ordersById,
                $refundOrderIds,
                $paidInvoiceIds,
                &$staleCancelIds,
                &$stalePaidIds
            ) {
                $rawInvoiceStatus = strtolower(trim((string) ($invoice->status ?? 'pending')));
                $orderId = (int) ($invoice->order_id ?? 0);
                $order = $orderId > 0 ? $ordersById->get($orderId) : null;
                $orderStatus = strtolower(trim((string) ($order->status ?? '')));
                $orderCancelled = in_array($orderStatus, ['rejected', 'cancelled', 'canceled', 'failed', 'void'], true);
                $isCreditNote = OrderCancellationCreditNoteService::isCreditNoteRecord($invoice);
                $invoiceCancelled = !$isCreditNote && in_array($rawInvoiceStatus, ['cancelled', 'canceled', 'failed', 'rejected', 'void'], true);
                $hasSuccessfulPaymentTxn = isset($paidInvoiceIds[(int) ($invoice->id ?? 0)]);

                $wasPaid = in_array($rawInvoiceStatus, ['paid', 'success', 'completed', 'succeeded', 'credited'], true)
                    || $hasSuccessfulPaymentTxn
                    || trim((string) ($order->razorpay_payment_id ?? '')) !== ''
                    || $orderStatus === 'paid'
                    || ($orderId > 0 && isset($refundOrderIds[$orderId]));

                if (!$orderCancelled && !$invoiceCancelled && $wasPaid && !in_array($rawInvoiceStatus, ['paid', 'success', 'completed', 'succeeded', 'credited'], true)) {
                    $stalePaidIds[] = (int) $invoice->id;
                    $rawInvoiceStatus = 'paid';
                }

                // Order cancelled but tax invoice still pending/paid → treat as cancelled for admin UI.
                // Credit notes are the cancellation document and must stay visible as issued.
                if ($orderCancelled && !$invoiceCancelled && !$isCreditNote) {
                    $staleCancelIds[] = (int) $invoice->id;
                    $rawInvoiceStatus = 'cancelled';
                    $invoiceCancelled = true;
                }

                if ($isCreditNote && in_array($rawInvoiceStatus, ['cancelled', 'canceled', 'failed', 'rejected', 'void'], true)) {
                    $rawInvoiceStatus = 'credited';
                }

                $tab = InvoiceStatusNormalizer::tab($rawInvoiceStatus);
                $statusLabel = match ($tab) {
                    'success' => 'Paid',
                    'fail' => 'Cancelled',
                    default => 'Pending',
                };
                if ($isCreditNote) {
                    $tab = in_array($rawInvoiceStatus, ['credited', 'paid', 'success', 'completed', 'issued'], true)
                        ? 'success'
                        : ($tab === 'fail' ? 'success' : $tab);
                    $statusLabel = in_array($rawInvoiceStatus, ['credited', 'paid'], true) ? 'Credited' : 'Issued';
                }

                $refundStatus = null;
                if ($invoiceCancelled && $wasPaid) {
                    $refundStatus = 'Refund Soon';
                    $statusLabel = 'Cancelled';
                    $tab = 'fail';
                } elseif ($invoiceCancelled) {
                    $statusLabel = 'Cancelled';
                    $tab = 'fail';
                }

                $invoiceUrl = '';
                if ($orderId > 0 && method_exists($billing, 'getOrderDocumentLinks')) {
                    try {
                        $links = (array) $billing->getOrderDocumentLinks($orderId);
                        if ($isCreditNote) {
                            $invoiceUrl = trim((string) ($links['credit_note_link'] ?? ''));
                        }
                        if ($invoiceUrl === '') {
                            $invoiceUrl = trim((string) ($links['invoice_link'] ?? $links['e_invoice_link'] ?? ''));
                        }
                    } catch (\Throwable $e) {
                        $invoiceUrl = '';
                    }
                }

                if ($invoiceUrl === '' && $orderId > 0) {
                    try {
                        $invoiceUrl = route('superadmin.orders.invoice', ['id' => $orderId]);
                    } catch (\Throwable $e) {
                        $invoiceUrl = '';
                    }
                }

                return [
                    'id' => (int) ($invoice->id ?? 0),
                    'order_id' => $orderId > 0 ? $orderId : null,
                    'invoice_no' => trim((string) ($invoice->bill_no_full ?? $invoice->order_no ?? '')),
                    'document_type' => $isCreditNote ? 'Credit Note' : 'Invoice',
                    'is_credit_note' => $isCreditNote,
                    'company' => trim((string) ($partner->company_name ?? $partner->name ?? '')),
                    'amount' => (float) ($invoice->net_amt ?? 0),
                    'date' => !empty($invoice->created_at)
                        ? \Carbon\Carbon::parse($invoice->created_at)->format('d M Y')
                        : '',
                    'status' => $rawInvoiceStatus,
                    'status_label' => $statusLabel,
                    'refund_status' => $refundStatus,
                    'tab' => $tab,
                    'invoice_url' => $invoiceUrl,
                    'view_label' => $isCreditNote ? 'View credit note' : 'View',
                ];
            });

            if ($staleCancelIds !== []) {
                $this->healCancelledInvoiceStatuses($staleCancelIds);
            }
            if ($stalePaidIds !== []) {
                $this->healPaidInvoiceStatuses($stalePaidIds);
            }

            if ($filterTab !== 'all') {
                $invoices = $invoices->filter(fn ($row) => ($row['tab'] ?? '') === $filterTab)->values();
            } else {
                $invoices = $invoices->values();
            }
        }

        return view('superadmin.invoice_management.partner_dashboard', [
            'partner' => $partner,
            'invoices' => $invoices,
            'filter_tab' => $filterTab,
        ]);
    }

    public function initiatePayment(Request $request)
    {
        return back()->with('warning', 'Payment initiation is not configured on this environment.');
    }

    public function paymentWebhook(Request $request)
    {
        return response()->json(['success' => true]);
    }

    /**
     * @param  array<int, int>  $orderIds
     * @return array<int, true>
     */
    private function orderIdsWithRefunds(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        try {
            if (!Schema::connection('pgsql_billing')->hasTable('payment_refunds')) {
                return [];
            }

            return PaymentRefund::query()
                ->whereIn('order_id', $orderIds)
                ->pluck('order_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->mapWithKeys(fn ($id) => [$id => true])
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param  array<int, int>  $invoiceIds
     */
    private function healCancelledInvoiceStatuses(array $invoiceIds): void
    {
        $invoiceIds = array_values(array_unique(array_filter(array_map('intval', $invoiceIds))));
        if ($invoiceIds === []) {
            return;
        }

        try {
            $query = InvoiceMst::query()
                ->whereIn('id', $invoiceIds)
                ->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhereRaw("LOWER(TRIM(status)) NOT IN ('cancelled', 'canceled', 'failed', 'rejected', 'void')");
                })
                ->where(function ($q) {
                    $q->whereNull('bill_no_full')
                        ->orWhere('bill_no_full', 'not like', 'CN/%');
                });

            $conn = (new InvoiceMst())->getConnectionName();
            if (Schema::connection($conn)->hasColumn('invoice_mst', 'action_type')) {
                $query->where(function ($q) {
                    $q->whereNull('action_type')
                        ->orWhere('action_type', '!=', OrderCancellationCreditNoteService::CREDIT_NOTE_ACTION);
                });
            }

            $query->update([
                'status' => 'cancelled',
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('healCancelledInvoiceStatuses failed', [
                'invoice_ids' => $invoiceIds,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<int, int>  $invoiceIds
     * @return array<int, true>
     */
    private function paidInvoiceIdsFromTransactions(array $invoiceIds): array
    {
        $invoiceIds = array_values(array_unique(array_filter(array_map('intval', $invoiceIds))));
        if ($invoiceIds === [] || !Schema::hasTable('invoice_payment_transaction_items')) {
            return [];
        }

        try {
            $txnIds = \App\Models\InvoicePaymentTransactionItem::query()
                ->whereIn('invoice_id', $invoiceIds)
                ->pluck('invoice_payment_transaction_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->filter(fn ($id) => $id > 0)
                ->values()
                ->all();

            if ($txnIds === []) {
                return [];
            }

            $successTxnIds = \App\Models\InvoicePaymentTransaction::query()
                ->whereIn('id', $txnIds)
                ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) IN ('success', 'paid', 'captured', 'completed')")
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($successTxnIds === []) {
                return [];
            }

            $paid = [];
            foreach (\App\Models\InvoicePaymentTransactionItem::query()
                ->whereIn('invoice_payment_transaction_id', $successTxnIds)
                ->whereIn('invoice_id', $invoiceIds)
                ->pluck('invoice_id') as $id) {
                $paid[(int) $id] = true;
            }

            return $paid;
        } catch (\Throwable $e) {
            Log::warning('paidInvoiceIdsFromTransactions failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @param  array<int, int>  $invoiceIds
     */
    private function healPaidInvoiceStatuses(array $invoiceIds): void
    {
        $invoiceIds = array_values(array_unique(array_filter(array_map('intval', $invoiceIds))));
        if ($invoiceIds === []) {
            return;
        }

        try {
            InvoiceMst::query()
                ->whereIn('id', $invoiceIds)
                ->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhereRaw("LOWER(TRIM(status)) NOT IN ('paid', 'success', 'completed', 'succeeded', 'credited')");
                })
                ->update([
                    'status' => 'paid',
                    'updated_at' => now(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('healPaidInvoiceStatuses failed', [
                'invoice_ids' => $invoiceIds,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<int, int>
     */
    private function invoiceCountsByPartner(string $kind): array
    {
        if (!$this->invoiceTableAvailable()) {
            return [];
        }

        $connection = (new InvoiceMst())->getConnectionName() ?: config('database.default');
        $query = DB::connection($connection)
            ->table('invoice_mst')
            ->select('partner_id', DB::raw('COUNT(*) as aggregate'))
            ->whereNotNull('partner_id');

        if ($kind === 'paid') {
            $query->whereRaw("LOWER(TRIM(COALESCE(status, ''))) IN ('paid', 'success', 'completed', 'succeeded')");
        } elseif ($kind === 'cancelled') {
            $query->whereRaw("LOWER(TRIM(COALESCE(status, ''))) IN ('cancelled', 'canceled', 'failed', 'rejected', 'void')");
        } else {
            $query->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereRaw("LOWER(TRIM(status)) IN ('pending', 'unpaid')");
            });
        }

        return $query
            ->groupBy('partner_id')
            ->pluck('aggregate', 'partner_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    private function invoiceTableAvailable(): bool
    {
        $connection = (new InvoiceMst())->getConnectionName() ?: config('database.default');

        return Schema::connection($connection)->hasTable('invoice_mst');
    }
}
