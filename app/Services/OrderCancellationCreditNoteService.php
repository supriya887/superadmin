<?php

namespace App\Services;

use App\Models\InvoiceMst;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use App\Support\GstSplit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class OrderCancellationCreditNoteService
{
    public const CREDIT_NOTE_ACTION = 'credit_note';

    /** invoice_mst.status once amount has been posted to partner wallet */
    public const STATUS_CREDITED = 'credited';

    /**
     * Issue prorated credit note for a cancelled order and credit partner wallet.
     *
     * @return array{
     *   ok: bool,
     *   message: string,
     *   credit_note_id?: int,
     *   credit_note_no?: string,
     *   credit_note_amount?: float,
     *   used_amount?: float,
     *   used_days?: int,
     *   remaining_days?: int,
     *   total_plan_days?: int,
     *   credit_note_link?: ?string,
     *   wallet_withdrawable?: bool,
     *   wallet_credited?: bool,
     *   credit_note_status?: string
     * }
     */
    public function issueForCancelledOrder(int $orderId, string $reason = 'Order cancelled'): array
    {
        $orderId = (int) $orderId;
        if ($orderId <= 0) {
            return ['ok' => false, 'message' => 'Invalid order id.'];
        }

        $order = Order::query()->whereKey($orderId)->first();
        if (!$order) {
            return ['ok' => false, 'message' => 'Order not found.'];
        }

        if ($this->existingCreditNoteId($orderId) > 0) {
            $existing = $this->loadCreditNotesForOrderIds([$orderId])[$orderId] ?? null;
            if ($existing !== null) {
                $wallet = $this->ensureWalletCreditForExisting($order, $existing);
                $merged = array_merge($existing, $wallet);

                return array_merge([
                    'ok' => true,
                    'message' => !empty($wallet['wallet_credited'])
                        ? 'Credit note already issued; amount credited to partner wallet.'
                        : 'Credit note already issued for this order.',
                ], $merged);
            }

            return ['ok' => false, 'message' => 'Credit note already issued for this order.'];
        }

        $calc = $this->calculateProrataRefund($order);
        if (($calc['credit_note_amount'] ?? 0) <= 0.005) {
            Log::info('credit_note_skip_zero_amount', [
                'order_id' => $orderId,
                'calc' => $calc,
            ]);

            return [
                'ok' => false,
                'message' => $this->zeroAmountSkipMessage($calc),
                'used_days' => (int) ($calc['used_days'] ?? 0),
                'remaining_days' => (int) ($calc['remaining_days'] ?? 0),
                'total_plan_days' => (int) ($calc['total_plan_days'] ?? 0),
                'order_amount' => round((float) ($calc['order_amount'] ?? 0), 2),
            ];
        }

        $partnerId = (int) ($order->partner_id ?? 0);
        $reason = trim($reason) !== '' ? trim($reason) : 'Order cancelled';

        try {
            $issued = DB::connection((new Order())->getConnectionName() ?: 'pgsql_billing')
                ->transaction(function () use ($order, $orderId, $calc, $partnerId) {
                    $amounts = $this->splitCreditNoteTax((float) $calc['credit_note_amount'], $order);
                    $qty = $this->resolveOrderLicenseQty($order);
                    $unitRate = $qty > 0
                        ? round((float) $amounts['taxable_total'] / $qty, 2)
                        : (float) $amounts['taxable_total'];
                    $creditNote = app(InvoiceService::class)->createCreditNote([
                        'cust_id' => (int) ($order->user_id ?? 0),
                        'partner_id' => $partnerId > 0 ? $partnerId : null,
                        'order_id' => $orderId,
                        'net_amt' => $amounts['net_amt'],
                        'taxable_total' => $amounts['taxable_total'],
                        'cgst_total' => $amounts['cgst_total'],
                        'sgst_total' => $amounts['sgst_total'],
                        'igst_total' => $amounts['igst_total'],
                        'tax_type' => $amounts['tax_type'],
                        // Will flip to credited after wallet post succeeds.
                        'status' => 'pending',
                        'items' => [[
                            'description' => sprintf(
                                'Unused subscription days — %d of %d unused (used %d). Against Order %s',
                                (int) ($calc['remaining_days'] ?? 0),
                                (int) ($calc['total_plan_days'] ?? 0),
                                (int) ($calc['used_days'] ?? 0),
                                (string) ($order->order_no ?? $orderId)
                            ),
                            'hsn_sac' => '997331',
                            'qty' => $qty,
                            'cnt_rate' => $unitRate,
                            'taxable_amt' => $amounts['taxable_total'],
                            'tax_type' => $amounts['tax_type'],
                            'cgst' => $amounts['cgst_total'],
                            'sgst' => $amounts['sgst_total'],
                            'igst' => $amounts['igst_total'],
                            'nettotal' => $amounts['net_amt'],
                        ]],
                    ]);

                    $creditNoteId = (int) ($creditNote['id'] ?? 0);
                    $billingConn = (new InvoiceMst())->getConnectionName() ?: 'pgsql_billing';
                    if ($creditNoteId > 0 && Schema::connection($billingConn)->hasColumn('invoice_mst', 'order_id')) {
                        InvoiceMst::query()->whereKey($creditNoteId)->update([
                            'order_id' => $orderId,
                            'updated_at' => now(),
                        ]);
                    }

                    if (Schema::connection((new Order())->getConnectionName())->hasColumn('orders', 'credit_used')) {
                        Order::query()->whereKey($orderId)->update([
                            'credit_used' => round((float) ($calc['used_amount'] ?? 0), 2),
                            'updated_at' => now(),
                        ]);
                    }

                    return [
                        'credit_note_id' => $creditNoteId,
                        'credit_note_no' => (string) ($creditNote['bill_no_full'] ?? ''),
                        'credit_note_amount' => round((float) $calc['credit_note_amount'], 2),
                        'used_amount' => round((float) ($calc['used_amount'] ?? 0), 2),
                        'used_days' => (int) ($calc['used_days'] ?? 0),
                        'remaining_days' => (int) ($calc['remaining_days'] ?? 0),
                        'total_plan_days' => (int) ($calc['total_plan_days'] ?? 0),
                        'wallet_withdrawable' => false,
                        'credit_note_status' => 'pending',
                    ];
                });

            // PDF after commit so generation failure never rolls back the credit note row.
            $creditNoteLink = null;
            $creditNoteId = (int) ($issued['credit_note_id'] ?? 0);
            if ($creditNoteId > 0) {
                try {
                    $stored = app(\App\Http\Controllers\SuperAdmin\BillingController::class)
                        ->persistCreditNotePdfToStorage(
                            $orderId,
                            $creditNoteId,
                            (string) ($issued['credit_note_no'] ?? ''),
                            $calc
                        );
                    $creditNoteLink = $stored['public_url'] ?? null;
                } catch (\Throwable $e) {
                    Log::warning('credit_note_pdf_persist_failed', [
                        'order_id' => $orderId,
                        'credit_note_id' => $creditNoteId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $walletCredited = false;
            if ($partnerId > 0 && $creditNoteId > 0) {
                $walletResult = PartnerCreditService::creditWalletFromCancellationCreditNote(
                    $partnerId,
                    $orderId,
                    (float) ($issued['credit_note_amount'] ?? 0),
                    (string) ($issued['credit_note_no'] ?? ''),
                    $creditNoteId
                );
                $walletCredited = !empty($walletResult['ok']) && (
                    !empty($walletResult['credited']) || str_contains((string) ($walletResult['message'] ?? ''), 'Already credited')
                );
                if (!empty($walletResult['ok'])) {
                    $this->markCreditNoteCredited($creditNoteId);
                    $issued['credit_note_status'] = self::STATUS_CREDITED;
                    $issued['wallet_withdrawable'] = false;
                } else {
                    Log::warning('credit_note_wallet_credit_failed', [
                        'order_id' => $orderId,
                        'credit_note_id' => $creditNoteId,
                        'message' => $walletResult['message'] ?? 'unknown',
                    ]);
                }
            }

            $result = array_merge([
                'ok' => true,
                'message' => $walletCredited
                    ? 'Credit note issued and amount credited to partner wallet.'
                    : 'Credit note issued.',
                'credit_note_link' => $creditNoteLink,
                'wallet_credited' => $walletCredited,
            ], $issued);

            $this->notifyPartnerCreditNoteIssued($order, $result, $reason);

            return $result;
        } catch (\Throwable $e) {
            Log::error('order_cancellation_credit_note_failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'ok' => false,
                'message' => 'Credit note could not be issued: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Issue prorated credit note for unused days of the SOURCE plan when a
     * upgrade / downgrade / renewal (CH* / RN*) order is activated.
     *
     * CN is stored against the change order (idempotent per CH/RN). Amount is
     * based on the replaced plan line's share of the parent order payment.
     * Any pro_rata_adjustment already applied on the change order is deducted
     * so the partner is not credited twice.
     *
     * @return array<string, mixed>
     */
    public function issueForPlanChangeOrder(int $changeOrderId, string $reason = ''): array
    {
        $changeOrderId = (int) $changeOrderId;
        if ($changeOrderId <= 0) {
            return ['ok' => false, 'message' => 'Invalid change order id.'];
        }

        $changeOrder = Order::query()->whereKey($changeOrderId)->first();
        if (!$changeOrder) {
            return ['ok' => false, 'message' => 'Change order not found.'];
        }

        $category = strtolower(trim((string) ($changeOrder->order_category ?? '')));
        $orderNo = strtoupper(trim((string) ($changeOrder->order_no ?? '')));
        $isRenewal = in_array($category, ['renewal', 'renew', 'renewed'], true) || str_starts_with($orderNo, 'RN');
        $isDowngrade = in_array($category, ['downgrade', 'downgraded'], true);
        $isUpgrade = in_array($category, ['upgrade', 'upgraded'], true) || str_starts_with($orderNo, 'CH');
        if (!$isRenewal && !$isDowngrade && !$isUpgrade) {
            return ['ok' => false, 'message' => 'Not an upgrade/downgrade/renewal order.'];
        }
        if ($isRenewal) {
            $changeLabel = 'renewal';
        } elseif ($isDowngrade) {
            $changeLabel = 'downgrade';
        } else {
            $changeLabel = 'upgrade';
        }

        $status = strtolower(trim((string) ($changeOrder->status ?? '')));
        if (!in_array($status, ['completed', 'paid', 'success', 'active'], true)) {
            return ['ok' => false, 'message' => 'Change order is not activated yet.'];
        }

        if ($this->existingCreditNoteId($changeOrderId) > 0) {
            $existing = $this->loadCreditNotesForOrderIds([$changeOrderId])[$changeOrderId] ?? null;
            if ($existing !== null) {
                $wallet = $this->ensureWalletCreditForExisting($changeOrder, $existing);

                return array_merge([
                    'ok' => true,
                    'message' => 'Credit note already issued for this plan change.',
                ], $existing, $wallet);
            }

            return ['ok' => true, 'message' => 'Credit note already issued for this plan change.'];
        }

        $parentId = (int) ($changeOrder->parent_order_id ?? 0);
        $parent = $parentId > 0 ? Order::query()->whereKey($parentId)->first() : null;
        if (!$parent) {
            return ['ok' => false, 'message' => 'Parent subscription order not found for plan change credit note.'];
        }

        $sourceItem = $this->resolvePlanChangeSourceItem($changeOrder, $parent);
        $calc = $this->calculateProrataRefundForPlanLine($parent, $sourceItem, $changeOrder);

        // Avoid double-credit when upgrade already applied remaining value as discount.
        $alreadyApplied = round((float) (
            $changeOrder->pro_rata_adjustment
            ?? $changeOrder->prorated_credit
            ?? 0
        ), 2);
        if ($alreadyApplied > 0.005) {
            $calc['credit_note_amount'] = round(max(0.0, (float) $calc['credit_note_amount'] - $alreadyApplied), 2);
            $calc['used_amount'] = round(max(0.0, (float) $calc['order_amount'] - (float) $calc['credit_note_amount']), 2);
            $calc['pro_rata_already_applied'] = $alreadyApplied;
        }

        if (($calc['credit_note_amount'] ?? 0) <= 0.005) {
            Log::info('plan_change_credit_note_skip_zero', [
                'change_order_id' => $changeOrderId,
                'parent_order_id' => $parentId,
                'calc' => $calc,
            ]);

            return [
                'ok' => false,
                'message' => 'No refundable unused days for this plan change'
                    . ($alreadyApplied > 0.005 ? ' (already applied as pro-rata discount).' : '.'),
                'used_days' => (int) ($calc['used_days'] ?? 0),
                'remaining_days' => (int) ($calc['remaining_days'] ?? 0),
                'total_plan_days' => (int) ($calc['total_plan_days'] ?? 0),
            ];
        }

        $partnerId = (int) ($changeOrder->partner_id ?? $parent->partner_id ?? 0);
        $planName = trim((string) ($calc['plan_name'] ?? ''));
        $parentNo = (string) ($parent->order_no ?? $parentId);
        $changeNo = (string) ($changeOrder->order_no ?? $changeOrderId);
        $reason = trim($reason) !== ''
            ? trim($reason)
            : sprintf('Plan %s (%s) — unused days credit', $changeLabel, $changeNo);

        try {
            $issued = DB::connection((new Order())->getConnectionName() ?: 'pgsql_billing')
                ->transaction(function () use ($changeOrder, $changeOrderId, $calc, $partnerId, $planName, $parentNo, $changeNo, $changeLabel) {
                    $amounts = $this->splitCreditNoteTax((float) $calc['credit_note_amount'], $changeOrder);
                    $qty = max(
                        1,
                        (int) ($calc['quantity'] ?? 0) ?: $this->resolveOrderLicenseQty($changeOrder)
                    );
                    $unitRate = $qty > 0
                        ? round((float) $amounts['taxable_total'] / $qty, 2)
                        : (float) $amounts['taxable_total'];
                    $creditNote = app(InvoiceService::class)->createCreditNote([
                        'cust_id' => (int) ($changeOrder->user_id ?? 0),
                        'partner_id' => $partnerId > 0 ? $partnerId : null,
                        'order_id' => $changeOrderId,
                        'net_amt' => $amounts['net_amt'],
                        'taxable_total' => $amounts['taxable_total'],
                        'cgst_total' => $amounts['cgst_total'],
                        'sgst_total' => $amounts['sgst_total'],
                        'igst_total' => $amounts['igst_total'],
                        'tax_type' => $amounts['tax_type'],
                        'status' => 'pending',
                        'items' => [[
                            'description' => sprintf(
                                'Unused days on %s — %d of %d unused (used %d). Source order %s → %s %s',
                                $planName !== '' ? $planName : 'plan',
                                (int) ($calc['remaining_days'] ?? 0),
                                (int) ($calc['total_plan_days'] ?? 0),
                                (int) ($calc['used_days'] ?? 0),
                                $parentNo,
                                $changeLabel,
                                $changeNo
                            ),
                            'hsn_sac' => '997331',
                            'qty' => $qty,
                            'cnt_rate' => $unitRate,
                            'taxable_amt' => $amounts['taxable_total'],
                            'tax_type' => $amounts['tax_type'],
                            'cgst' => $amounts['cgst_total'],
                            'sgst' => $amounts['sgst_total'],
                            'igst' => $amounts['igst_total'],
                            'nettotal' => $amounts['net_amt'],
                        ]],
                    ]);

                    $creditNoteId = (int) ($creditNote['id'] ?? 0);
                    $billingConn = (new InvoiceMst())->getConnectionName() ?: 'pgsql_billing';
                    if ($creditNoteId > 0 && Schema::connection($billingConn)->hasColumn('invoice_mst', 'order_id')) {
                        InvoiceMst::query()->whereKey($creditNoteId)->update([
                            'order_id' => $changeOrderId,
                            'updated_at' => now(),
                        ]);
                    }

                    return [
                        'credit_note_id' => $creditNoteId,
                        'credit_note_no' => (string) ($creditNote['bill_no_full'] ?? ''),
                        'credit_note_amount' => round((float) $calc['credit_note_amount'], 2),
                        'used_amount' => round((float) ($calc['used_amount'] ?? 0), 2),
                        'used_days' => (int) ($calc['used_days'] ?? 0),
                        'remaining_days' => (int) ($calc['remaining_days'] ?? 0),
                        'total_plan_days' => (int) ($calc['total_plan_days'] ?? 0),
                        'wallet_withdrawable' => false,
                        'credit_note_status' => 'pending',
                        'change_type' => $changeLabel,
                        'source_order_id' => (int) ($calc['source_order_id'] ?? 0),
                        'source_order_no' => $parentNo,
                        'plan_name' => $planName,
                    ];
                });

            $creditNoteLink = null;
            $creditNoteId = (int) ($issued['credit_note_id'] ?? 0);
            if ($creditNoteId > 0) {
                try {
                    $stored = app(\App\Http\Controllers\SuperAdmin\BillingController::class)
                        ->persistCreditNotePdfToStorage(
                            $changeOrderId,
                            $creditNoteId,
                            (string) ($issued['credit_note_no'] ?? ''),
                            $calc
                        );
                    $creditNoteLink = $stored['public_url'] ?? null;
                } catch (\Throwable $e) {
                    Log::warning('plan_change_credit_note_pdf_failed', [
                        'change_order_id' => $changeOrderId,
                        'credit_note_id' => $creditNoteId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $walletCredited = false;
            if ($partnerId > 0 && $creditNoteId > 0) {
                $walletResult = PartnerCreditService::creditWalletFromCancellationCreditNote(
                    $partnerId,
                    $changeOrderId,
                    (float) ($issued['credit_note_amount'] ?? 0),
                    (string) ($issued['credit_note_no'] ?? ''),
                    $creditNoteId
                );
                $walletCredited = !empty($walletResult['ok']) && (
                    !empty($walletResult['credited'])
                    || str_contains((string) ($walletResult['message'] ?? ''), 'Already credited')
                );
                if (!empty($walletResult['ok'])) {
                    $this->markCreditNoteCredited($creditNoteId);
                    $issued['credit_note_status'] = self::STATUS_CREDITED;
                } else {
                    Log::warning('plan_change_credit_note_wallet_failed', [
                        'change_order_id' => $changeOrderId,
                        'credit_note_id' => $creditNoteId,
                        'message' => $walletResult['message'] ?? 'unknown',
                    ]);
                }
            }

            $result = array_merge([
                'ok' => true,
                'message' => $walletCredited
                    ? 'Plan-change credit note issued and credited to partner wallet.'
                    : 'Plan-change credit note issued.',
                'credit_note_link' => $creditNoteLink,
                'wallet_credited' => $walletCredited,
            ], $issued);

            $this->notifyPartnerCreditNoteIssued(
                $changeOrder,
                $result,
                $reason,
                sprintf('Plan %s — credit note generated', $changeLabel)
            );

            return $result;
        } catch (\Throwable $e) {
            Log::error('plan_change_credit_note_failed', [
                'change_order_id' => $changeOrderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'ok' => false,
                'message' => 'Plan-change credit note could not be issued: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Unused-days value for the replaced plan line on the parent subscription.
     *
     * @return array<string, mixed>
     */
    public function calculateProrataRefundForPlanLine(
        object $sourceOrder,
        ?OrderItem $sourceItem = null,
        ?object $changeOrder = null,
        ?Carbon $asOf = null
    ): array {
        $asOf = ($asOf ?? Carbon::now())->copy()->startOfDay();

        $startRaw = $sourceItem->subscription_start_date
            ?? $sourceOrder->subscription_start_date
            ?? null;
        $endRaw = $sourceItem->subscription_end_date
            ?? $sourceOrder->subscription_end_date
            ?? null;

        $start = !empty($startRaw)
            ? Carbon::parse((string) $startRaw)->startOfDay()
            : Carbon::parse((string) ($sourceOrder->created_at ?? now()))->startOfDay();
        $end = !empty($endRaw)
            ? Carbon::parse((string) $endRaw)->startOfDay()
            : app(RenewalService::class)->subscriptionEndDate($start, $sourceOrder->duration ?? '12 month')->startOfDay();
        if ($end->lessThanOrEqualTo($start)) {
            $end = app(RenewalService::class)->subscriptionEndDate($start, $sourceOrder->duration ?? '12 month')->startOfDay();
        }

        $totalPlanDays = max(1, (int) $start->diffInDays($end));
        $remainingDays = UpgradeProrationService::subscriptionRemainingDays($start, $end, $asOf);
        $remainingDays = max(0, min($remainingDays, $totalPlanDays));
        $usedDays = max(0, $totalPlanDays - $remainingDays);

        $orderAmount = $this->resolveRefundableOrderAmount($sourceOrder);
        $lineAmount = $orderAmount;

        if ($sourceItem) {
            $itemTotal = round((float) (
                $sourceItem->total
                ?? $sourceItem->net_amount
                ?? $sourceItem->amount
                ?? 0
            ), 2);
            if ($itemTotal > 0.005) {
                $lineAmount = $itemTotal;
            } else {
                $allItems = OrderItem::query()
                    ->where('order_id', (int) $sourceOrder->id)
                    ->where(function ($q) {
                        $q->whereNull('status')
                            ->orWhereRaw("LOWER(TRIM(COALESCE(status, ''))) NOT IN ('delete', 'deleted')");
                    })
                    ->get(['id', 'quantity', 'plan_id']);
                $totalQty = max(1, (int) $allItems->sum(fn ($r) => max(1, (int) ($r->quantity ?? 1))));
                $lineQty = max(1, (int) ($sourceItem->quantity ?? 1));
                $lineAmount = round($orderAmount * ($lineQty / $totalQty), 2);
            }

            // Partial seat move: scale by change-order quantity vs source line qty.
            if ($changeOrder) {
                $changeQty = max(1, (int) ($changeOrder->quantity ?? 1));
                $sourceQty = max(1, (int) ($sourceItem->quantity ?? 1));
                if ($changeQty < $sourceQty) {
                    $lineAmount = round($lineAmount * ($changeQty / $sourceQty), 2);
                }
            }
        }

        $creditNoteAmount = $remainingDays > 0
            ? round($lineAmount * $remainingDays / $totalPlanDays, 2)
            : 0.0;
        $usedAmount = round(max(0.0, $lineAmount - $creditNoteAmount), 2);

        $planName = '';
        if ($sourceItem && (int) ($sourceItem->plan_id ?? 0) > 0) {
            $planName = (string) (optional($sourceItem->plan)->name
                ?? optional(\App\Models\Plan::query()->find((int) $sourceItem->plan_id))->name
                ?? '');
        }
        if ($planName === '' && (int) ($sourceOrder->plan_id ?? 0) > 0) {
            $planName = (string) (optional(\App\Models\Plan::query()->find((int) $sourceOrder->plan_id))->name ?? '');
        }

        return [
            'order_amount' => round($lineAmount, 2),
            'used_days' => $usedDays,
            'remaining_days' => $remainingDays,
            'total_plan_days' => $totalPlanDays,
            'used_amount' => $usedAmount,
            'credit_note_amount' => $creditNoteAmount,
            'subscription_start' => $start->toDateString(),
            'subscription_end' => $end->toDateString(),
            'source_order_id' => (int) ($sourceOrder->id ?? 0),
            'source_order_sub_id' => $sourceItem ? (int) $sourceItem->id : null,
            'plan_name' => $planName,
        ];
    }

    private function resolvePlanChangeSourceItem(Order $changeOrder, Order $parent): ?OrderItem
    {
        $subId = (int) ($changeOrder->parent_order_sub_id ?? 0);
        $childItem = OrderItem::query()
            ->where('order_id', (int) $changeOrder->id)
            ->orderBy('id')
            ->first();

        if ($subId <= 0 && $childItem) {
            $subId = (int) ($childItem->previous_order_sub_id ?? 0);
            if ($subId <= 0) {
                $meta = is_array($childItem->meta ?? null) ? $childItem->meta : [];
                $subId = (int) ($meta['previous_order_sub_id'] ?? $meta['order_sub_id'] ?? 0);
            }
        }

        if ($subId > 0) {
            $item = OrderItem::query()->with('plan:id,name')->whereKey($subId)->first();
            if ($item && (int) ($item->order_id ?? 0) === (int) $parent->id) {
                return $item;
            }
        }

        $oldPlanId = (int) ($changeOrder->previous_plan_id ?? 0);
        if ($oldPlanId <= 0 && $childItem) {
            $meta = is_array($childItem->meta ?? null) ? $childItem->meta : [];
            $oldPlanId = (int) ($meta['old_plan_id'] ?? 0);
        }
        if ($oldPlanId <= 0 && $this->isRenewalLike($changeOrder)) {
            $oldPlanId = (int) ($childItem->plan_id ?? $changeOrder->plan_id ?? 0);
        }

        if ($oldPlanId > 0) {
            $item = OrderItem::query()
                ->with('plan:id,name')
                ->where('order_id', (int) $parent->id)
                ->where('plan_id', $oldPlanId)
                ->orderByDesc('id')
                ->first();
            if ($item) {
                return $item;
            }
        }

        $items = OrderItem::query()
            ->with('plan:id,name')
            ->where('order_id', (int) $parent->id)
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereRaw("LOWER(TRIM(COALESCE(status, ''))) NOT IN ('delete', 'deleted')");
            })
            ->orderBy('id')
            ->get();

        return $items->count() === 1 ? $items->first() : $items->first();
    }

    private function isRenewalLike(object $order): bool
    {
        $category = strtolower(trim((string) ($order->order_category ?? '')));
        $orderNo = strtoupper(trim((string) ($order->order_no ?? '')));

        return in_array($category, ['renewal', 'renew', 'renewed'], true) || str_starts_with($orderNo, 'RN');
    }

    /**
     * List all cancellation credit notes for a partner (with wallet credit status).
     *
     * @return list<array<string, mixed>>
     */
    public function listForPartner(int $partnerId): array
    {
        $partnerId = (int) $partnerId;
        if ($partnerId <= 0) {
            return [];
        }

        $billingConn = (new InvoiceMst())->getConnectionName() ?: 'pgsql_billing';
        if (!Schema::connection($billingConn)->hasTable('invoice_mst')) {
            return [];
        }

        $query = InvoiceMst::query();
        if (Schema::connection($billingConn)->hasColumn('invoice_mst', 'partner_id')) {
            $query->where('partner_id', $partnerId);
        } else {
            $orderIds = Order::query()
                ->where('partner_id', $partnerId)
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->all();
            if ($orderIds === []) {
                return [];
            }
            $query->whereIn('order_id', $orderIds);
        }

        if (Schema::connection($billingConn)->hasColumn('invoice_mst', 'action_type')) {
            $query->where(function ($q) {
                $q->where('action_type', self::CREDIT_NOTE_ACTION)
                    ->orWhere('bill_no_full', 'like', 'CN/%');
            });
        } else {
            $query->where('bill_no_full', 'like', 'CN/%');
        }

        $rows = $query->orderByDesc('id')->get([
            'id',
            'order_id',
            'partner_id',
            'bill_no_full',
            'bill_date',
            'net_amt',
            'status',
            'created_at',
            'updated_at',
        ]);

        if ($rows->isEmpty()) {
            return [];
        }

        $orderIds = $rows->pluck('order_id')->map(static fn ($id) => (int) $id)->filter(static fn ($id) => $id > 0)->unique()->values()->all();
        $orders = $orderIds !== []
            ? Order::query()->whereIn('id', $orderIds)->get(['id', 'order_no', 'partner_id', 'status', 'total_amount'])->keyBy('id')
            : collect();

        $out = [];
        foreach ($rows as $row) {
            $oid = (int) ($row->order_id ?? 0);
            $order = $oid > 0 ? ($orders->get($oid)) : null;
            if ($order && (int) ($order->partner_id ?? 0) !== $partnerId) {
                continue;
            }
            if (!$order && Schema::connection($billingConn)->hasColumn('invoice_mst', 'partner_id')
                && (int) ($row->partner_id ?? 0) !== $partnerId) {
                continue;
            }

            $cnId = (int) $row->id;
            $amount = round((float) ($row->net_amt ?? 0), 2);
            $status = strtolower(trim((string) ($row->status ?? 'pending')));
            $walletCredited = $oid > 0 && PartnerCreditService::hasCancellationCreditNoteWalletCredit($oid);
            $status = $this->normalizeDisplayedCreditNoteStatus($cnId, $status, $walletCredited);

            $link = Cache::get('order_credit_note_link_' . $oid)
                ?: Cache::get('credit_note_link_' . $cnId);
            if (!$link && $oid > 0) {
                $safeNo = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($row->bill_no_full ?? '')) ?: ('CN-' . $cnId);
                $rel = 'credit-notes/' . $safeNo . '-order-' . $oid . '.pdf';
                if (is_file(public_path('storage/' . $rel))) {
                    $link = asset('storage/' . $rel);
                } elseif (is_file(storage_path('app/public/' . $rel))) {
                    $link = asset('storage/' . $rel);
                } else {
                    $matches = glob(public_path('storage/credit-notes/*-order-' . $oid . '.pdf'))
                        ?: glob(storage_path('app/public/credit-notes/*-order-' . $oid . '.pdf'))
                        ?: [];
                    if ($matches !== [] && is_file($matches[0])) {
                        $link = asset('storage/credit-notes/' . basename($matches[0]));
                    }
                }
            }

            // Backfill wallet credit for older pending credit notes.
            if (!$walletCredited && $amount > 0.005 && $partnerId > 0) {
                $walletResult = PartnerCreditService::creditWalletFromCancellationCreditNote(
                    $partnerId,
                    $oid,
                    $amount,
                    (string) ($row->bill_no_full ?? ''),
                    $cnId
                );
                if (!empty($walletResult['ok'])) {
                    $walletCredited = true;
                    $this->markCreditNoteCredited($cnId);
                    $status = self::STATUS_CREDITED;
                    if (!empty($walletResult['credited']) && $order) {
                        $this->notifyPartnerCreditNoteIssued($order, [
                            'credit_note_no' => (string) ($row->bill_no_full ?? ''),
                            'credit_note_amount' => $amount,
                            'credit_note_link' => $link,
                            'wallet_credited' => true,
                        ], 'Credit note credited to wallet');
                    }
                }
            } elseif ($walletCredited && !in_array($status, ['paid', 'credited', 'withdrawn', 'used'], true)) {
                $this->markCreditNoteCredited($cnId);
                $status = self::STATUS_CREDITED;
            }

            $walletStatus = $walletCredited ? 'credited_to_wallet' : 'pending_wallet_credit';

            $out[] = [
                'credit_note_id' => $cnId,
                'credit_note_no' => (string) ($row->bill_no_full ?? ''),
                'credit_note_amount' => $amount,
                'credit_note_link' => $link ? (string) $link : null,
                'credit_note_status' => $status !== '' ? $status : 'pending',
                'wallet_status' => $walletStatus,
                'wallet_credited' => $walletCredited,
                'wallet_withdrawable' => false,
                'order_id' => $oid > 0 ? $oid : null,
                'order_no' => $order ? (string) ($order->order_no ?? '') : null,
                'order_status' => $order ? (string) ($order->status ?? '') : null,
                'bill_date' => $row->bill_date ? (string) $row->bill_date : null,
                'created_at' => $row->created_at ? (string) $row->created_at : null,
            ];
        }

        return $out;
    }

    /**
     * @return array{
     *   order_amount: float,
     *   used_days: int,
     *   remaining_days: int,
     *   total_plan_days: int,
     *   used_amount: float,
     *   credit_note_amount: float,
     *   subscription_start: ?string,
     *   subscription_end: ?string
     * }
     */
    public function calculateProrataRefund(object $order, ?Carbon $cancelAt = null): array
    {
        $cancelAt = ($cancelAt ?? $this->resolveCancelAt($order))->copy()->startOfDay();
        [$start, $end] = $this->resolveSubscriptionWindow($order);

        $totalPlanDays = max(1, (int) round($start->diffInDays($end, true)));
        $remainingDays = UpgradeProrationService::subscriptionRemainingDays($start, $end, $cancelAt);
        $remainingDays = max(0, min($remainingDays, $totalPlanDays));
        $usedDays = max(0, $totalPlanDays - $remainingDays);

        $orderAmount = $this->resolveRefundableOrderAmount($order);
        $creditNoteAmount = $remainingDays > 0
            ? round($orderAmount * $remainingDays / $totalPlanDays, 2)
            : 0.0;
        $usedAmount = round(max(0.0, $orderAmount - $creditNoteAmount), 2);

        return [
            'order_amount' => round($orderAmount, 2),
            'used_days' => $usedDays,
            'remaining_days' => $remainingDays,
            'total_plan_days' => $totalPlanDays,
            'used_amount' => $usedAmount,
            'credit_note_amount' => $creditNoteAmount,
            'subscription_start' => $start->toDateString(),
            'subscription_end' => $end->toDateString(),
        ];
    }

    private function existingCreditNoteId(int $orderId): int
    {
        if (!Schema::connection((new InvoiceMst())->getConnectionName())->hasTable('invoice_mst')) {
            return 0;
        }

        $query = InvoiceMst::query()->where('order_id', $orderId);
        if (Schema::connection((new InvoiceMst())->getConnectionName())->hasColumn('invoice_mst', 'action_type')) {
            $query->where('action_type', self::CREDIT_NOTE_ACTION);
        } else {
            $query->where('bill_no_full', 'like', 'CN/%');
        }

        return (int) $query->orderByDesc('id')->value('id');
    }

    /**
     * @param  array<string, mixed>  $existing
     * @return array{wallet_credited: bool, credit_note_status?: string, wallet_withdrawable?: bool}
     */
    private function ensureWalletCreditForExisting(object $order, array $existing): array
    {
        $partnerId = (int) ($order->partner_id ?? 0);
        $orderId = (int) ($order->id ?? 0);
        $amount = round((float) ($existing['credit_note_amount'] ?? 0), 2);
        $cnId = (int) ($existing['credit_note_id'] ?? 0);
        $status = strtolower(trim((string) ($existing['credit_note_status'] ?? 'pending')));

        if ($partnerId <= 0 || $amount <= 0.005) {
            return [
                'wallet_credited' => in_array($status, ['paid', 'credited', 'withdrawn', 'used'], true),
                'wallet_withdrawable' => false,
            ];
        }

        if (PartnerCreditService::hasCancellationCreditNoteWalletCredit($orderId)) {
            if ($cnId > 0 && !in_array($status, ['paid', 'credited'], true)) {
                $this->markCreditNoteCredited($cnId);
            }

            return [
                'wallet_credited' => true,
                'credit_note_status' => self::STATUS_CREDITED,
                'wallet_withdrawable' => false,
            ];
        }

        $walletResult = PartnerCreditService::creditWalletFromCancellationCreditNote(
            $partnerId,
            $orderId,
            $amount,
            (string) ($existing['credit_note_no'] ?? ''),
            $cnId > 0 ? $cnId : null
        );

        if (!empty($walletResult['ok'])) {
            if ($cnId > 0) {
                $this->markCreditNoteCredited($cnId);
            }
            if (!empty($walletResult['credited'])) {
                $this->notifyPartnerCreditNoteIssued($order, array_merge($existing, [
                    'wallet_credited' => true,
                    'credit_note_status' => self::STATUS_CREDITED,
                ]), 'Credit note wallet credit');
            }

            return [
                'wallet_credited' => true,
                'credit_note_status' => self::STATUS_CREDITED,
                'wallet_withdrawable' => false,
            ];
        }

        return [
            'wallet_credited' => false,
            'wallet_withdrawable' => false,
        ];
    }

    private function markCreditNoteCredited(int $creditNoteId): void
    {
        if ($creditNoteId <= 0) {
            return;
        }

        try {
            InvoiceMst::query()->whereKey($creditNoteId)->update([
                'status' => self::STATUS_CREDITED,
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('mark_credit_note_credited_failed', [
                'credit_note_id' => $creditNoteId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public static function isCreditNoteRecord(?object $row): bool
    {
        if ($row === null) {
            return false;
        }

        $action = strtolower(trim((string) ($row->action_type ?? '')));
        if ($action === self::CREDIT_NOTE_ACTION) {
            return true;
        }

        $billNo = strtoupper(trim((string) ($row->bill_no_full ?? $row->invoice_no ?? '')));

        return str_starts_with($billNo, 'CN/');
    }

    /**
     * In-app + FCM (+ optional WhatsApp text) when CN is issued / wallet credited.
     *
     * @param  array<string, mixed>  $result
     */
    private function notifyPartnerCreditNoteIssued(
        object $order,
        array $result,
        string $reason = 'Order cancelled',
        string $title = 'Order cancelled — credit note generated'
    ): void {
        $partnerId = (int) ($order->partner_id ?? 0);
        if ($partnerId <= 0) {
            return;
        }

        $orderId = (int) ($order->id ?? 0);
        $orderNo = (string) ($order->order_no ?? $orderId);
        $cnNo = (string) ($result['credit_note_no'] ?? '');
        $amount = round((float) ($result['credit_note_amount'] ?? 0), 2);
        $link = trim((string) ($result['credit_note_link'] ?? ''));
        $walletCredited = !empty($result['wallet_credited']);
        $title = trim($title) !== '' ? trim($title) : 'Order cancelled — credit note generated';

        $messageParts = [
            'Order #' . $orderNo . '.',
            $reason !== '' ? rtrim($reason, '.') . '.' : '',
            $cnNo !== '' ? ('Credit note ' . $cnNo . ' generated for ₹' . number_format($amount, 2) . '.') : ('Credit note generated for ₹' . number_format($amount, 2) . '.'),
        ];
        if ($walletCredited) {
            $messageParts[] = '₹' . number_format($amount, 2) . ' has been credited to your wallet.';
        }
        if ($link !== '') {
            $messageParts[] = 'Download: ' . $link;
        }
        $message = implode(' ', array_filter($messageParts));

        try {
            Notification::query()->create([
                'partner_id' => $partnerId,
                'title' => $title,
                'message' => $message,
                'is_read' => 0,
                'order_id' => $orderId > 0 ? $orderId : null,
                'status' => 'active',
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('credit_note_in_app_notification_failed', [
                'order_id' => $orderId,
                'partner_id' => $partnerId,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            app(FirebasePushService::class)->sendToPartner(
                $partnerId,
                $title,
                $message,
                [
                    'type' => 'order_cancel_credit_note',
                    'order_id' => (string) $orderId,
                    'order_no' => $orderNo,
                    'credit_note_no' => $cnNo,
                    'credit_note_amount' => (string) $amount,
                    'credit_note_link' => $link,
                    'wallet_credited' => $walletCredited ? '1' : '0',
                    'link' => $link,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('credit_note_fcm_failed', [
                'order_id' => $orderId,
                'partner_id' => $partnerId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveSubscriptionWindow(object $order): array
    {
        $startRaw = $order->subscription_start_date ?? null;
        $endRaw = $order->subscription_end_date ?? null;

        if (($startRaw === null || $endRaw === null) && !empty($order->id)) {
            $item = OrderItem::query()
                ->where('order_id', (int) $order->id)
                ->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhereNotIn('status', ['delete', 'deleted']);
                })
                ->orderBy('id')
                ->first(['subscription_start_date', 'subscription_end_date']);

            if ($item) {
                $startRaw = $startRaw ?? $item->subscription_start_date;
                $endRaw = $endRaw ?? $item->subscription_end_date;
            }
        }

        $start = !empty($startRaw)
            ? Carbon::parse((string) $startRaw)->startOfDay()
            : Carbon::parse((string) ($order->created_at ?? now()))->startOfDay();

        $end = !empty($endRaw)
            ? Carbon::parse((string) $endRaw)->startOfDay()
            : app(RenewalService::class)->subscriptionEndDate($start, $order->duration ?? '12 month')->startOfDay();

        if ($end->lessThanOrEqualTo($start)) {
            $end = app(RenewalService::class)->subscriptionEndDate($start, $order->duration ?? '12 month')->startOfDay();
        }

        return [$start, $end];
    }

    /**
     * License / seat quantity for credit-note line items (never collapse to 1 when order has more).
     */
    public function resolveOrderLicenseQty(object $order): int
    {
        $qty = max(0, (int) ($order->quantity ?? 0));
        if ($qty <= 0) {
            $orderId = (int) ($order->id ?? 0);
            if ($orderId > 0) {
                $qty = (int) OrderItem::query()
                    ->where('order_id', $orderId)
                    ->sum('quantity');
            }
        }

        return max(1, $qty);
    }

    private function resolveCancelAt(object $order): Carbon
    {
        $status = strtolower(trim((string) ($order->status ?? '')));
        if (in_array($status, ['cancelled', 'canceled', 'rejected'], true) && !empty($order->updated_at)) {
            try {
                return Carbon::parse((string) $order->updated_at)->startOfDay();
            } catch (\Throwable) {
                // Fall through to now.
            }
        }

        return Carbon::now()->startOfDay();
    }

    /**
     * @param  array<string, mixed>  $calc
     */
    public function zeroAmountSkipMessage(array $calc): string
    {
        $orderAmount = round((float) ($calc['order_amount'] ?? 0), 2);
        $remainingDays = (int) ($calc['remaining_days'] ?? 0);
        $usedDays = (int) ($calc['used_days'] ?? 0);
        $totalDays = (int) ($calc['total_plan_days'] ?? 0);
        $end = trim((string) ($calc['subscription_end'] ?? ''));

        if ($orderAmount <= 0.005) {
            return 'Credit note was not generated because the order amount is ₹0.00.';
        }

        if ($remainingDays <= 0) {
            return sprintf(
                'Credit note was not generated because there are no unused subscription days (used %d of %d%s).',
                $usedDays,
                max(1, $totalDays),
                $end !== '' ? ', ended ' . $end : ''
            );
        }

        return sprintf(
            'No refundable balance for remaining subscription days (unused %d of %d, order amount ₹%s).',
            $remainingDays,
            max(1, $totalDays),
            number_format($orderAmount, 2)
        );
    }

    private function resolveRefundableOrderAmount(object $order): float
    {
        $total = round((float) ($order->total_amount ?? $order->amount ?? 0), 2);
        if ($total > 0) {
            return $total;
        }

        $gst = round((float) ($order->gst_amount ?? $order->tax_amount ?? 0), 2);
        $subtotal = round((float) ($order->subtotal ?? 0), 2);
        if ($subtotal > 0 || $gst > 0) {
            return round($subtotal + $gst, 2);
        }

        $orderId = (int) ($order->id ?? 0);
        if ($orderId > 0) {
            $itemTotal = 0.0;
            try {
                $itemConn = (new OrderItem())->getConnectionName() ?: 'pgsql_billing';
                foreach (['total_amount', 'amount', 'net_amt', 'line_total'] as $col) {
                    if (!Schema::connection($itemConn)->hasColumn('order_items', $col)) {
                        continue;
                    }
                    $sum = round((float) OrderItem::query()->where('order_id', $orderId)->sum($col), 2);
                    if ($sum > 0) {
                        $itemTotal = $sum;
                        break;
                    }
                }
            } catch (\Throwable) {
                $itemTotal = 0.0;
            }
            if ($itemTotal > 0) {
                return $itemTotal;
            }

            try {
                $invoiceAmount = (float) InvoiceMst::query()
                    ->where('order_id', $orderId)
                    ->where(function ($q) {
                        $q->whereNull('action_type')
                            ->orWhere('action_type', '!=', self::CREDIT_NOTE_ACTION);
                    })
                    ->where(function ($q) {
                        $q->whereNull('bill_no_full')
                            ->orWhere('bill_no_full', 'not like', 'CN/%');
                    })
                    ->orderByDesc('id')
                    ->value('net_amt');
                if ($invoiceAmount > 0) {
                    return round($invoiceAmount, 2);
                }
            } catch (\Throwable) {
                // invoice_mst may be missing on older schemas.
            }
        }

        $creditUsed = round((float) ($order->credit_used ?? 0), 2);
        if ($creditUsed > 0) {
            return $creditUsed;
        }

        $qty = $this->resolveOrderLicenseQty($order);

        return round((float) ($order->price ?? 0) * $qty, 2);
    }

    /**
     * @return array{
     *   net_amt: float,
     *   taxable_total: float,
     *   cgst_total: float,
     *   sgst_total: float,
     *   igst_total: float,
     *   tax_type: string
     * }
     */
    private function splitCreditNoteTax(float $creditNoteAmount, object $order): array
    {
        $creditNoteAmount = round(max(0.0, $creditNoteAmount), 2);
        $orderTotal = round((float) ($order->total_amount ?? 0), 2);
        $orderTax = round((float) ($order->gst_amount ?? $order->tax_amount ?? 0), 2);
        $orderTaxable = round(max(0.0, $orderTotal - $orderTax), 2);

        if ($orderTotal > 0 && $orderTax > 0) {
            $ratio = $creditNoteAmount / $orderTotal;
            $taxable = round($orderTaxable * $ratio, 2);
            $tax = round($orderTax * $ratio, 2);
            $net = round($taxable + $tax, 2);
            if (abs($net - $creditNoteAmount) > 0.02) {
                $net = $creditNoteAmount;
                $tax = round($creditNoteAmount - $taxable, 2);
            }

            $cgst = round((float) ($order->cgst_total ?? $order->cgst_amount ?? 0) * $ratio, 2);
            $sgst = round((float) ($order->sgst_total ?? $order->sgst_amount ?? 0) * $ratio, 2);
            $igst = round((float) ($order->igst_total ?? $order->igst_amount ?? 0) * $ratio, 2);
            $gstin = (string) ($order->po_manual_gstin ?? $order->customer_gstin ?? '');
            if ($cgst <= 0 && $sgst <= 0 && $igst <= 0 && $tax > 0) {
                $split = GstSplit::split($tax, $gstin);
                $cgst = $split['cgst'];
                $sgst = $split['sgst'];
                $igst = $split['igst'];
            }

            $taxType = strtolower(trim((string) ($order->tax_type ?? '')));
            if ($taxType === '') {
                $taxType = GstSplit::split($tax, $gstin)['tax_type'];
            }

            return [
                'net_amt' => $net,
                'taxable_total' => $taxable,
                'cgst_total' => $cgst,
                'sgst_total' => $sgst,
                'igst_total' => $igst,
                'tax_type' => $taxType,
            ];
        }

        $gstRate = GstSplit::RATE;
        $taxable = round($creditNoteAmount / (1 + ($gstRate / 100)), 2);
        $tax = round(max(0.0, $creditNoteAmount - $taxable), 2);
        $split = GstSplit::split($tax, (string) ($order->po_manual_gstin ?? $order->customer_gstin ?? ''));

        return [
            'net_amt' => $creditNoteAmount,
            'taxable_total' => $taxable,
            'cgst_total' => $split['cgst'],
            'sgst_total' => $split['sgst'],
            'igst_total' => $split['igst'],
            'tax_type' => $split['tax_type'],
        ];
    }

    /**
     * Resolve credit notes for order list/detail APIs.
     * If a cancelled order has no credit note yet, attempt to create one (backfill).
     *
     * @param  list<int>  $orderIds
     * @return array<int, array{
     *   credit_note_id: int,
     *   credit_note_no: string,
     *   credit_note_amount: float,
     *   credit_note_link: ?string,
     *   credit_note_status: string,
     *   wallet_withdrawable: bool,
     *   wallet_credited: bool
     * }>
     */
    public function resolveCreditNotesByOrderIds(array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds), static fn ($id) => $id > 0)));
        if ($orderIds === []) {
            return [];
        }

        $billingConn = (new InvoiceMst())->getConnectionName() ?: 'pgsql_billing';
        if (!Schema::connection($billingConn)->hasTable('invoice_mst')) {
            return [];
        }

        $out = $this->loadCreditNotesForOrderIds($orderIds);

        // Backfill missing credit notes for already-cancelled orders.
        $missing = array_values(array_diff($orderIds, array_keys($out)));
        if ($missing !== []) {
            $cancelledOrders = Order::query()
                ->whereIn('id', $missing)
                ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) IN ('cancelled', 'canceled', 'rejected')")
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->all();

            foreach ($cancelledOrders as $oid) {
                $issued = $this->issueForCancelledOrder($oid, 'Backfill credit note for cancelled order');
                if (!empty($issued['ok']) && !empty($issued['credit_note_id'])) {
                    $out[$oid] = [
                        'credit_note_id' => (int) $issued['credit_note_id'],
                        'credit_note_no' => (string) ($issued['credit_note_no'] ?? ''),
                        'credit_note_amount' => round((float) ($issued['credit_note_amount'] ?? 0), 2),
                        'credit_note_link' => $issued['credit_note_link'] ?? null,
                        'credit_note_status' => (string) ($issued['credit_note_status'] ?? self::STATUS_CREDITED),
                        'wallet_withdrawable' => false,
                        'wallet_credited' => (bool) ($issued['wallet_credited'] ?? false),
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * Credit notes must stay visible after order cancel. Older rows were wrongly
     * stamped status=cancelled with the tax invoice — restore those.
     */
    public function normalizeDisplayedCreditNoteStatus(int $creditNoteId, string $status, bool $walletCredited): string
    {
        $status = strtolower(trim($status));
        if (in_array($status, ['cancelled', 'canceled', 'failed', 'rejected', 'void'], true)) {
            $restored = $walletCredited ? self::STATUS_CREDITED : 'issued';
            if ($creditNoteId > 0) {
                try {
                    InvoiceMst::query()->whereKey($creditNoteId)->update([
                        'status' => $restored,
                        'updated_at' => now(),
                    ]);
                } catch (\Throwable) {
                    // Display restore is enough if the write fails.
                }
            }

            return $restored;
        }

        if ($walletCredited) {
            return self::STATUS_CREDITED;
        }

        return $status !== '' ? $status : 'pending';
    }

    /**
     * @param  list<int>  $orderIds
     * @return array<int, array<string, mixed>>
     */
    private function loadCreditNotesForOrderIds(array $orderIds): array
    {
        $billingConn = (new InvoiceMst())->getConnectionName() ?: 'pgsql_billing';
        $query = InvoiceMst::query()->whereIn('order_id', $orderIds);
        if (Schema::connection($billingConn)->hasColumn('invoice_mst', 'action_type')) {
            $query->where(function ($q) {
                $q->where('action_type', self::CREDIT_NOTE_ACTION)
                    ->orWhere('bill_no_full', 'like', 'CN/%');
            });
        } else {
            $query->where('bill_no_full', 'like', 'CN/%');
        }

        $rows = $query->orderByDesc('id')->get(['id', 'order_id', 'bill_no_full', 'net_amt', 'status']);
        $out = [];
        foreach ($rows as $row) {
            $oid = (int) ($row->order_id ?? 0);
            if ($oid <= 0 || isset($out[$oid])) {
                continue;
            }

            $cnId = (int) $row->id;
            $link = Cache::get('order_credit_note_link_' . $oid)
                ?: Cache::get('credit_note_link_' . $cnId);
            if (!$link) {
                $safeNo = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($row->bill_no_full ?? '')) ?: ('CN-' . $cnId);
                $rel = 'credit-notes/' . $safeNo . '-order-' . $oid . '.pdf';
                if (is_file(public_path('storage/' . $rel))) {
                    $link = asset('storage/' . $rel);
                } elseif (is_file(storage_path('app/public/' . $rel))) {
                    $link = asset('storage/' . $rel);
                } else {
                    $matches = glob(public_path('storage/credit-notes/*-order-' . $oid . '.pdf'))
                        ?: glob(storage_path('app/public/credit-notes/*-order-' . $oid . '.pdf'))
                        ?: [];
                    if ($matches !== [] && is_file($matches[0])) {
                        $link = asset('storage/credit-notes/' . basename($matches[0]));
                    }
                }
            }

            $status = strtolower(trim((string) ($row->status ?? 'pending')));
            $walletCredited = PartnerCreditService::hasCancellationCreditNoteWalletCredit($oid)
                || in_array($status, ['paid', 'credited', 'withdrawn', 'used'], true);
            $status = $this->normalizeDisplayedCreditNoteStatus($cnId, $status, $walletCredited);

            $out[$oid] = [
                'credit_note_id' => $cnId,
                'credit_note_no' => (string) ($row->bill_no_full ?? ''),
                'credit_note_amount' => round((float) ($row->net_amt ?? 0), 2),
                'credit_note_link' => $link ? (string) $link : null,
                'credit_note_status' => $walletCredited ? self::STATUS_CREDITED : ($status !== '' ? $status : 'pending'),
                'wallet_withdrawable' => false,
                'wallet_credited' => $walletCredited,
            ];
        }

        return $out;
    }
}
