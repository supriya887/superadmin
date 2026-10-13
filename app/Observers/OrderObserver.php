<?php

namespace App\Observers;

use App\Models\AccountMaster;
use App\Models\Ledger;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderObserver
{
    public function created(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $this->rebuildEntries($order);
        });
    }

    public function updated(Order $order): void
    {
        $relevantDirty = [
            'total_amount', 'subtotal', 'price', 'payment_mode', 'partner_id',
            'user_id', 'order_type', 'cheque_ref_no', 'upi_transaction_id', 'order_note', 'bank_name'
        ];

        if (!$order->wasChanged($relevantDirty)) {
            return;
        }

        DB::transaction(function () use ($order) {
            $this->rebuildEntries($order);
        });
    }

    public function deleting(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $this->createReversalEntries($order);
        });
    }

    private function rebuildEntries(Order $order): void
    {
        Ledger::where('order_id', $order->id)->delete();
        $entries = $this->buildEntries($order, false);
        $this->persistEntries($entries, $order);
    }

    private function createReversalEntries(Order $order): void
    {
        $entries = $this->buildEntries($order, true);
        $this->persistEntries($entries, $order);
    }

    private function buildEntries(Order $order, bool $reverse = false): array
    {
        $amount = $this->resolveOrderAmount($order);
        if ($amount <= 0) {
            return [];
        }

        $referenceNo = (string) ($order->upi_transaction_id ?: $order->cheque_ref_no ?: '');
        $paymentMode = (string) ($order->payment_mode ?? '');
        $orderType = strtolower((string) ($order->order_type ?? 'b2c'));
        $sales = AccountMaster::findOrCreate('Sales Income', 'Sales Income', null);

        if ($orderType === 'b2b') {
            $partnerId = !empty($order->partner_id) ? (int) $order->partner_id : null;
            $partnerName = 'Partner #' . ($partnerId ?: 'NA');
            $partnerAccount = AccountMaster::findOrCreate($partnerName, 'Partner', $partnerId);

            return $reverse
                ? [
                    [$sales, 'Debit', $amount, 'Order reversal credit note'],
                    [$partnerAccount, 'Credit', $amount, 'Order reversal refund'],
                ]
                : [
                    [$partnerAccount, 'Debit', $amount, 'Order debit against partner wallet'],
                    [$sales, 'Credit', $amount, 'Order sales credit'],
                ];
        }

        $cashOrBank = $paymentMode === 'cash_hand' ? 'Cash' : 'Bank';
        $accountName = $cashOrBank === 'Cash'
            ? 'Cash in Hand'
            : ($paymentMode === 'upi' ? 'UPI Collection' : ('Bank - ' . (string) ($order->bank_name ?: 'Main')));
        $receiptAccount = AccountMaster::findOrCreate($accountName, $cashOrBank, null);

        return $reverse
            ? [
                [$sales, 'Debit', $amount, 'Order reversal credit note'],
                [$receiptAccount, 'Credit', $amount, 'Order reversal refund'],
            ]
            : [
                [$receiptAccount, 'Debit', $amount, 'Order receipt debit'],
                [$sales, 'Credit', $amount, 'Order sales credit'],
            ];
    }

    private function persistEntries(array $entries, Order $order): void
    {
        foreach ($entries as [$account, $type, $amount, $desc]) {
            Ledger::create([
                'account_master_id' => $account->id,
                'order_id' => $order->id,
                'transaction_type' => $type,
                'amount' => $amount,
                'payment_mode' => $order->payment_mode,
                'reference_no' => (string) ($order->upi_transaction_id ?: $order->cheque_ref_no ?: ''),
                'description' => trim($desc . (empty($order->order_note) ? '' : (' | ' . $order->order_note))),
            ]);
            $account->syncCurrentBalance();
        }
    }

    private function resolveOrderAmount(Order $order): float
    {
        if (isset($order->total_amount) && (float) $order->total_amount > 0) {
            return (float) $order->total_amount;
        }
        if (isset($order->subtotal) && (float) $order->subtotal > 0) {
            return (float) $order->subtotal;
        }
        if (isset($order->price) && (float) $order->price > 0) {
            return (float) $order->price;
        }
        return 0.0;
    }
}
