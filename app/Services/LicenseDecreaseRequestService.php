<?php

namespace App\Services;

use App\Mail\PartnerLicenseDecreaseDecisionMail;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\LicenseDecreaseRequest;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Partner;
use App\Services\FirebasePushService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class LicenseDecreaseRequestService
{
    public static function tableReady(): bool
    {
        try {
            return Schema::connection('pgsql_billing')->hasTable('license_decrease_requests');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function currentLicensesForOrder(Order $order, ?int $planId = null): int
    {
        $query = OrderItem::query()
            ->where('order_id', (int) $order->id)
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereRaw("LOWER(TRIM(COALESCE(status, ''))) NOT IN ('delete', 'deleted', 'cancelled')");
            });
    
        // ✅ If planId is provided, get licenses for that specific plan
        if ($planId !== null && $planId > 0) {
            $item = (clone $query)->where('plan_id', $planId)->first();
            if ($item) {
                return max(1, (int) ($item->quantity ?? 1));
            }
            // Plan not found, fallback to order total
        }
    
        // ✅ For total across all plans, SUM the quantities
        $totalLicenses = (int) (clone $query)->sum('quantity');
        
        // If no order items, fallback to order table
        if ($totalLicenses <= 0) {
            $orderQty = (int) ($order->quantity ?? 0);
            $orderLicenses = (int) ($order->licenses ?? 0);
            return max(1, $orderQty, $orderLicenses);
        }
        
        return max(1, $totalLicenses);
    }

    /**
     * Create or refresh a pending decrease request.
     *
     * @return array{
     *   ok: bool,
     *   requires_approval: bool,
     *   message: string,
     *   request?: LicenseDecreaseRequest|null,
     *   current_licenses?: int,
     *   requested_licenses?: int
     * }
     */
    public function createOrUpdatePending(
        int $partnerId,
        int $orderId,
        int $requestedLicenses,
        ?int $cartId = null,
        ?int $planId = null,
        string $remarks = ''
    ): array {
        if (!self::tableReady()) {
            return [
                'ok' => false,
                'requires_approval' => true,
                'message' => 'License decrease requests table is not installed. Run migrations.',
            ];
        }
    
        $partnerId = (int) $partnerId;
        $orderId = (int) $orderId;
        $requestedLicenses = max(1, (int) $requestedLicenses);
    
        $order = Order::query()->whereKey($orderId)->where('partner_id', $partnerId)->first();
        if (!$order) {
            return ['ok' => false, 'requires_approval' => false, 'message' => 'Order not found for this partner.'];
        }
    
        // ✅ Get current licenses for the specific plan (or total)
        $current = self::currentLicensesForOrder($order, $planId);
        
        // ✅ If planId is provided, make sure the plan exists on this order
        if ($planId > 0) {
            $planExists = OrderItem::query()
                ->where('order_id', $orderId)
                ->where('plan_id', $planId)
                ->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhereRaw("LOWER(TRIM(COALESCE(status, ''))) NOT IN ('delete', 'deleted', 'cancelled')");
                })
                ->exists();
                
            if (!$planExists) {
                return [
                    'ok' => false,
                    'requires_approval' => false,
                    'message' => 'Plan not found on this order.',
                    'current_licenses' => $current,
                    'requested_licenses' => $requestedLicenses,
                ];
            }
        }
    
        if ($requestedLicenses >= $current) {
            return [
                'ok' => false,
                'requires_approval' => false,
                'message' => sprintf(
                    'No license decrease. Current licenses %s are %d; you sent requested_licenses=%d. Send a value less than %d.',
                    $planId > 0 ? 'for this plan' : 'total',
                    $current,
                    $requestedLicenses,
                    $current
                ),
                'current_licenses' => $current,
                'requested_licenses' => $requestedLicenses,
            ];
        }
    
        // Already approved for this (or lower) qty → allow proceed.
        $approved = LicenseDecreaseRequest::query()
            ->where('partner_id', $partnerId)
            ->where('order_id', $orderId)
            ->whereRaw("LOWER(TRIM(status)) = ?", [LicenseDecreaseRequest::STATUS_APPROVED])
            ->where('requested_licenses', '<=', $requestedLicenses)
            ->orderByDesc('id')
            ->first();
    
        if ($approved && (int) $approved->requested_licenses === $requestedLicenses) {
            return [
                'ok' => true,
                'requires_approval' => false,
                'message' => 'License decrease already approved.',
                'request' => $approved,
                'current_licenses' => $current,
                'requested_licenses' => $requestedLicenses,
            ];
        }
    
        $pending = LicenseDecreaseRequest::query()
            ->where('partner_id', $partnerId)
            ->where('order_id', $orderId)
            ->whereRaw("LOWER(TRIM(COALESCE(status, 'pending'))) = ?", [LicenseDecreaseRequest::STATUS_PENDING])
            ->orderByDesc('id')
            ->first();
    
        $payload = [
            'partner_id' => $partnerId,
            'order_id' => $orderId,
            'cart_id' => $cartId > 0 ? $cartId : null,
            'plan_id' => $planId > 0 ? $planId : null, // ✅ Store plan_id
            'current_licenses' => $current,
            'requested_licenses' => $requestedLicenses,
            'status' => LicenseDecreaseRequest::STATUS_PENDING,
            'remarks' => trim($remarks) !== '' ? trim($remarks) : 'Renewal license decrease request',
        ];
    
        if ($pending) {
            $pending->fill($payload);
            $pending->save();
            $row = $pending->fresh();
        } else {
            $row = LicenseDecreaseRequest::query()->create($payload);
        }
    
        return [
            'ok' => true,
            'requires_approval' => true,
            'message' => 'License decrease request submitted. Waiting for SuperAdmin approval.',
            'request' => $row,
            'current_licenses' => $current,
            'requested_licenses' => $requestedLicenses,
        ];
    }

    /**
     * Whether renew may proceed with this license count.
     */
    public function isDecreaseAllowed(
        int $partnerId,
        int $orderId,
        int $requestedLicenses,
        ?int $planId = null
    ): bool {
        $requestedLicenses = max(1, (int) $requestedLicenses);
        $order = Order::query()->whereKey($orderId)->where('partner_id', $partnerId)->first();
        if (!$order) {
            return false;
        }

        $current = self::currentLicensesForOrder($order, $planId);
        if ($requestedLicenses >= $current) {
            return true;
        }

        if (!self::tableReady()) {
            return false;
        }

        $q = LicenseDecreaseRequest::query()
            ->where('partner_id', $partnerId)
            ->where('order_id', $orderId)
            ->whereRaw("LOWER(TRIM(status)) = ?", [LicenseDecreaseRequest::STATUS_APPROVED])
            ->where('requested_licenses', $requestedLicenses);

        if ($planId !== null && $planId > 0) {
            $q->where(function ($inner) use ($planId) {
                $inner->where('plan_id', $planId)
                    ->orWhereNull('plan_id');
            });
        }

        return $q->exists();
    }

    /**
     * Guard renew: if decrease without SuperAdmin approval, block only.
     * Frontend must call POST /api/partner/license-decrease-request explicitly.
     *
     * Compares like-for-like:
     * - cart_id → each cart line vs same plan on the order (avoids false 1 vs order-total 3)
     * - plan_id → that plan only
     * - else → order total
     *
     * @return array{blocked: bool, payload?: array<string, mixed>}
     */
    public function guardRenewDecrease(
        int $partnerId,
        int $orderId,
        int $requestedLicenses,
        ?int $cartId = null,
        ?int $planId = null
    ): array {
        $requestedLicenses = max(1, (int) $requestedLicenses);
        $order = Order::query()->whereKey($orderId)->where('partner_id', $partnerId)->first();
        if (!$order) {
            return ['blocked' => false];
        }

        $planId = $planId !== null ? (int) $planId : 0;
        $cartId = $cartId !== null ? (int) $cartId : 0;
        $current = self::currentLicensesForOrder($order, $planId > 0 ? $planId : null);
        $decreasedPlanId = $planId > 0 ? $planId : null;

        // Cart-aware: only flag real per-plan decreases (multi-plan renew false positives).
        if ($cartId > 0) {
            $cartItems = CartItem::query()->where('cart_id', $cartId)->get();
            if ($cartItems->isNotEmpty()) {
                $anyDecrease = false;
                $worstCurrent = 0;
                $worstRequested = 0;
                $worstPlanId = null;

                foreach ($cartItems as $cartItem) {
                    $linePlanId = (int) ($cartItem->plan_id ?? 0);
                    $lineRequested = max(1, (int) ($cartItem->licenses ?? 0));
                    if ($lineRequested <= 0) {
                        continue;
                    }
                    $lineCurrent = self::currentLicensesForOrder(
                        $order,
                        $linePlanId > 0 ? $linePlanId : null
                    );
                    if ($lineRequested < $lineCurrent) {
                        $anyDecrease = true;
                        // Prefer the largest drop for the approval payload.
                        if (($lineCurrent - $lineRequested) >= ($worstCurrent - $worstRequested)) {
                            $worstCurrent = $lineCurrent;
                            $worstRequested = $lineRequested;
                            $worstPlanId = $linePlanId > 0 ? $linePlanId : null;
                        }
                    }
                }

                if (!$anyDecrease) {
                    return ['blocked' => false];
                }

                $current = max(1, $worstCurrent);
                $requestedLicenses = max(1, $worstRequested);
                $decreasedPlanId = $worstPlanId;
            }
        } else {
            if ($planId > 0 && $requestedLicenses >= $current) {
                return ['blocked' => false];
            }
            if ($planId <= 0 && $requestedLicenses >= $current) {
                return ['blocked' => false];
            }
        }

        if ($this->isDecreaseAllowed($partnerId, $orderId, $requestedLicenses, $decreasedPlanId)) {
            return ['blocked' => false];
        }

        $pendingId = null;
        if (self::tableReady()) {
            $pendingId = LicenseDecreaseRequest::query()
                ->where('partner_id', $partnerId)
                ->where('order_id', $orderId)
                ->whereRaw("LOWER(TRIM(COALESCE(status, 'pending'))) = ?", [LicenseDecreaseRequest::STATUS_PENDING])
                ->orderByDesc('id')
                ->value('id');
        }

        return [
            'blocked' => true,
            'payload' => [
                'success' => false,
                'error' => true,
                'code' => 'license_decrease_approval_required',
                'message' => 'License decrease requires SuperAdmin approval. Call POST /api/partner/license-decrease-request first.',
                'data' => [
                    'requires_approval' => true,
                    'submit_via' => 'POST /api/partner/license-decrease-request',
                    'request_id' => $pendingId ? (int) $pendingId : null,
                    'status' => $pendingId ? LicenseDecreaseRequest::STATUS_PENDING : null,
                    'order_id' => $orderId,
                    'cart_id' => $cartId > 0 ? $cartId : null,
                    'plan_id' => $decreasedPlanId,
                    'current_licenses' => $current,
                    'requested_licenses' => $requestedLicenses,
                ],
            ],
        ];
    }

    public function approve(LicenseDecreaseRequest $row, ?string $adminNote = null, ?int $adminUserId = null): LicenseDecreaseRequest
    {
        if (!$row->isPending()) {
            throw new \InvalidArgumentException('This request is no longer pending.');
        }

        $row->status = LicenseDecreaseRequest::STATUS_APPROVED;
        $row->admin_note = $adminNote !== null ? trim($adminNote) : $row->admin_note;
        $row->decided_by = $adminUserId;
        $row->decided_at = now();
        $row->save();

        // Keep renew cart at the approved (reduced) license count.
        $this->syncCartLicenses((int) ($row->cart_id ?? 0), (int) $row->requested_licenses);

        $this->notifyPartner($row, 'approved');

        return $row->fresh();
    }

    public function rejectOrRevert(
        LicenseDecreaseRequest $row,
        string $status = LicenseDecreaseRequest::STATUS_REJECTED,
        ?string $adminNote = null,
        ?int $adminUserId = null
    ): LicenseDecreaseRequest {
        if (!$row->isPending() && strtolower(trim((string) $row->status)) !== LicenseDecreaseRequest::STATUS_APPROVED) {
            throw new \InvalidArgumentException('This request cannot be reverted.');
        }

        $status = strtolower(trim($status));
        if (!in_array($status, [LicenseDecreaseRequest::STATUS_REJECTED, LicenseDecreaseRequest::STATUS_REVERTED], true)) {
            $status = LicenseDecreaseRequest::STATUS_REJECTED;
        }

        $row->status = $status;
        $row->admin_note = $adminNote !== null ? trim($adminNote) : $row->admin_note;
        $row->decided_by = $adminUserId;
        $row->decided_at = now();
        $row->save();

        // Restore original license count on renew cart.
        $this->syncCartLicenses((int) ($row->cart_id ?? 0), (int) $row->current_licenses);

        $this->notifyPartner($row, $status);

        return $row->fresh();
    }

    private function syncCartLicenses(int $cartId, int $licenses): void
    {
        if ($cartId <= 0 || $licenses < 1) {
            return;
        }

        try {
            $cart = Cart::query()->whereKey($cartId)->first();
            if (!$cart) {
                return;
            }

            CartItem::query()->where('cart_id', $cartId)->update(['licenses' => $licenses]);

            // Recalc cart totals from first line when possible.
            $item = CartItem::query()->where('cart_id', $cartId)->orderBy('id')->first();
            if ($item) {
                $unit = round((float) ($item->unit_price ?? 0), 2);
                $line = round(max(0, $unit * $licenses), 2);
                $item->licenses = $licenses;
                $item->grand_total = $line;
                $item->save();

                $gstPct = round((float) ($cart->gst_percentage ?? 18), 2);
                $discount = round((float) ($cart->discount_amount ?? 0), 2);
                $gst = round(max(0, (($line - $discount) * $gstPct) / 100), 2);
                $cart->gst_amount = $gst;
                $cart->grand_total = round(max(0, $line - $discount + $gst), 2);
                $cart->save();
            }
        } catch (\Throwable $e) {
            Log::warning('license_decrease_sync_cart_failed', [
                'cart_id' => $cartId,
                'licenses' => $licenses,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifyPartner(LicenseDecreaseRequest $row, string $decision): void
    {
        $partnerId = (int) ($row->partner_id ?? 0);
        if ($partnerId <= 0) {
            return;
        }

        $orderId = (int) ($row->order_id ?? 0);
        $requestId = (int) ($row->id ?? 0);
        $current = (int) ($row->current_licenses ?? 0);
        $requested = (int) ($row->requested_licenses ?? 0);
        $decision = strtolower(trim($decision));

        $title = match ($decision) {
            'approved' => 'License decrease approved',
            'reverted' => 'License decrease reverted',
            default => 'License decrease rejected',
        };

        $message = match ($decision) {
            'approved' => sprintf(
                'Your request to reduce licenses from %d to %d on order #%d was approved. You can proceed with renewal.',
                $current,
                $requested,
                $orderId
            ),
            'reverted' => sprintf(
                'Your approved license decrease on order #%d was reverted. Renewal will continue with %d licenses.',
                $orderId,
                $current
            ),
            default => sprintf(
                'Your request to reduce licenses from %d to %d on order #%d was rejected. Renewal will continue with %d licenses.',
                $current,
                $requested,
                $orderId,
                $current
            ),
        };

        $partner = Partner::query()->find($partnerId);
        if ($partner && filter_var((string) ($partner->email ?? ''), FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::to($partner->email)->send(new PartnerLicenseDecreaseDecisionMail(
                    (string) ($partner->name ?? 'Partner'),
                    $decision,
                    $requestId,
                    $orderId,
                    $current,
                    $requested,
                    $row->admin_note ? (string) $row->admin_note : null
                ));
            } catch (\Throwable $e) {
                Log::warning('license_decrease_decision_mail_failed', [
                    'request_id' => $requestId,
                    'partner_id' => $partnerId,
                    'decision' => $decision,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            Log::info('license_decrease_decision_mail_skipped', [
                'request_id' => $requestId,
                'partner_id' => $partnerId,
                'reason' => $partner ? 'invalid_email' : 'partner_not_found',
            ]);
        }

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
            Log::warning('license_decrease_notification_failed', [
                'partner_id' => $partnerId,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            app(FirebasePushService::class)->sendToPartner($partnerId, $title, $message, [
                'type' => 'license_decrease_request',
                'request_id' => (string) $requestId,
                'order_id' => (string) $orderId,
                'decision' => $decision,
            ]);
        } catch (\Throwable $e) {
            Log::warning('license_decrease_fcm_failed', [
                'partner_id' => $partnerId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForPartner(int $partnerId, ?string $status = null): array
    {
        if (!self::tableReady() || $partnerId <= 0) {
            return [];
        }

        $q = LicenseDecreaseRequest::query()->where('partner_id', $partnerId);
        if ($status !== null && strtolower(trim($status)) !== 'all') {
            $q->whereRaw('LOWER(TRIM(status)) = ?', [strtolower(trim($status))]);
        }

        return $q->orderByDesc('id')->get()->map(static function (LicenseDecreaseRequest $row) {
            return [
                'request_id' => (int) $row->id,
                'order_id' => (int) $row->order_id,
                'cart_id' => $row->cart_id ? (int) $row->cart_id : null,
                'plan_id' => $row->plan_id ? (int) $row->plan_id : null,
                'current_licenses' => (int) $row->current_licenses,
                'requested_licenses' => (int) $row->requested_licenses,
                'status' => (string) $row->status,
                'remarks' => (string) ($row->remarks ?? ''),
                'admin_note' => (string) ($row->admin_note ?? ''),
                'decided_at' => $row->decided_at ? (string) $row->decided_at : null,
                'created_at' => $row->created_at ? (string) $row->created_at : null,
            ];
        })->all();
    }
}
