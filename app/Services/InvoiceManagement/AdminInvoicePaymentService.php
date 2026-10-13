<?php

namespace App\Services\InvoiceManagement;

use App\Models\Invoice;
use App\Models\InvoicePaymentTransaction;
use App\Models\InvoicePaymentTransactionItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminInvoicePaymentService
{
    /**
     * Create parent transaction + line items. Gateway call is stubbed until keys exist.
     *
     * @param  array<int,int>  $invoiceIds
     * @return array{ok:bool,transaction_id?:int,error?:string,gateway_order_id?:string}
     */
    public function initiate(int $partnerId, array $invoiceIds): array
    {
        if (!Schema::hasTable('invoice_payment_transactions')) {
            return ['ok' => false, 'error' => 'payment_tables_missing_run_migrations'];
        }

        $invoiceIds = array_values(array_unique(array_map('intval', $invoiceIds)));
        $invoiceIds = array_values(array_filter($invoiceIds, static fn ($id) => $id > 0));
        if ($invoiceIds === []) {
            return ['ok' => false, 'error' => 'no_invoice_ids'];
        }

        $invoices = Invoice::query()
            ->whereIn('id', $invoiceIds)
            ->where('partner_id', $partnerId)
            ->get();

        if ($invoices->count() !== count($invoiceIds)) {
            return ['ok' => false, 'error' => 'invoice_partner_mismatch_or_missing'];
        }

        foreach ($invoices as $inv) {
            if (InvoiceStatusNormalizer::tab((string) ($inv->status ?? '')) !== 'pending') {
                return ['ok' => false, 'error' => 'only_pending_invoices_payable'];
            }
        }

        $total = 0.0;
        foreach ($invoices as $inv) {
            $total += (float) ($inv->total_amount ?? $inv->amount ?? 0);
        }

        return DB::transaction(function () use ($partnerId, $invoices, $total) {
            $parent = InvoicePaymentTransaction::query()->create([
                'partner_id'        => $partnerId,
                'status'            => 'pending',
                'total_amount'      => round($total, 2),
                'currency'          => 'INR',
                'gateway'           => 'razorpay',
                'gateway_order_id'  => 'ord_stub_' . bin2hex(random_bytes(8)),
                'gateway_payment_id'=> null,
                'meta'              => ['invoice_ids' => $invoices->pluck('id')->all()],
            ]);

            foreach ($invoices as $inv) {
                $line = (float) ($inv->total_amount ?? $inv->amount ?? 0);
                InvoicePaymentTransactionItem::query()->create([
                    'invoice_payment_transaction_id' => $parent->id,
                    'invoice_id'                     => $inv->id,
                    'amount'                         => round($line, 2),
                ]);
            }

            return [
                'ok'               => true,
                'transaction_id'   => (int) $parent->id,
                'gateway_order_id' => (string) $parent->gateway_order_id,
                'amount'           => round($total, 2),
                'message'          => 'Parent transaction created. Complete via payment gateway webhook (stub until Razorpay is wired).',
            ];
        });
    }

    /**
     * Mark parent + linked invoices paid and assign TZG-style numbers (stored in tzg_invoice_no).
     */
    public function completeByGatewayPaymentId(string $gatewayPaymentId): bool
    {
        if (!Schema::hasTable('invoice_payment_transactions')) {
            return false;
        }

        $parent = InvoicePaymentTransaction::query()
            ->where('gateway_order_id', $gatewayPaymentId)
            ->first();

        if (!$parent) {
            $parent = InvoicePaymentTransaction::query()
                ->where('gateway_payment_id', $gatewayPaymentId)
                ->first();
        }

        if (!$parent) {
            return false;
        }

        return $this->markParentSuccess($parent, $gatewayPaymentId);
    }

    public function markParentSuccess(InvoicePaymentTransaction $parent, ?string $gatewayPaymentId = null): bool
    {
        if ($parent->status === 'success') {
            return true;
        }

        return DB::transaction(function () use ($parent, $gatewayPaymentId) {
            $prefix = 'TZG';

            $parent->status = 'success';
            if ($gatewayPaymentId) {
                $parent->gateway_payment_id = $gatewayPaymentId;
            }
            $parent->save();

            $items = InvoicePaymentTransactionItem::query()
                ->where('invoice_payment_transaction_id', $parent->id)
                ->get();

            foreach ($items as $item) {
                $inv = Invoice::query()->find($item->invoice_id);
                if (!$inv || (int) $inv->partner_id !== (int) $parent->partner_id) {
                    continue;
                }

                $tzg = $prefix . '-' . str_pad((string) $inv->id, 5, '0', STR_PAD_LEFT);
                $update = ['status' => 'paid'];
                if (Schema::hasColumn('invoice_mst', 'tzg_invoice_no')) {
                    $update['tzg_invoice_no'] = $tzg;
                }
                $inv->update($update);

                // Restore partner credit allocation when invoice is settled.
                \App\Services\PartnerCreditService::releaseCreditForPaidInvoice(
                    (int) $inv->id,
                    (int) ($inv->order_id ?? 0)
                );
            }

            return true;
        });
    }
}
