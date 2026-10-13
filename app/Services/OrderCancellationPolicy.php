<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Plan;
use Carbon\Carbon;

class OrderCancellationPolicy
{
    public const PROVIDER_TIZZY = 1;

    public const PROVIDER_MICROSOFT = 2;

    public const PROVIDER_GOOGLE = 3;

    public const CANCELLABLE_DAYS = 6;

    private const ACTIVE_STATUSES = ['completed', 'paid', 'success', 'active'];

    public function blockMessageForOrder(int $orderId, ?int $providerIdHint = null): ?string
    {
        $order = Order::query()->whereKey($orderId)->first();
        if (!$order) {
            return 'Order not found.';
        }

        if ($providerIdHint !== null && $providerIdHint > 0) {
            $order->setAttribute('provider_id', $providerIdHint);
        }

        return $this->blockMessage($order);
    }

    public function canCancel(object $order, ?int $providerIdHint = null): bool
    {
        if ($providerIdHint !== null && $providerIdHint > 0) {
            $order->provider_id = $providerIdHint;
        }

        return $this->blockMessage($order) === null;
    }

    public function cancellationDeadline(object $order, ?int $providerIdHint = null): ?Carbon
    {
        if ($providerIdHint !== null && $providerIdHint > 0) {
            $order->provider_id = $providerIdHint;
        }

        if (!$this->isActiveStatus($order)) {
            return null;
        }

        $referenceDate = $this->resolveReferenceDate($order);
        if ($referenceDate === null) {
            return null;
        }

        foreach ($this->resolveProviderIds($order) as $providerId) {
            if ($providerId === self::PROVIDER_GOOGLE) {
                return null;
            }

            if (in_array($providerId, [self::PROVIDER_TIZZY, self::PROVIDER_MICROSOFT], true)) {
                return $referenceDate->copy()->addDays(self::CANCELLABLE_DAYS);
            }
        }

        return null;
    }

    public function cancellationDeadlineLabel(object $order, string $format = 'd-m-Y', ?int $providerIdHint = null): ?string
    {
        $deadline = $this->cancellationDeadline($order, $providerIdHint);

        return $deadline ? $deadline->format($format) : null;
    }

    public function blockMessage(object $order): ?string
    {
        if (!$this->isActiveStatus($order)) {
            return null;
        }

        $providerIds = $this->resolveProviderIds($order);
        if ($providerIds === []) {
            return null;
        }

        foreach ($providerIds as $providerId) {
            $message = $this->blockMessageForProvider($providerId, $order);
            if ($message !== null) {
                return $message;
            }
        }

        return null;
    }

    private function blockMessageForProvider(int $providerId, object $order): ?string
    {
        if ($providerId === self::PROVIDER_GOOGLE) {
            return 'Google subscriptions cannot be canceled once activated.';
        }

        if (!in_array($providerId, [self::PROVIDER_TIZZY, self::PROVIDER_MICROSOFT], true)) {
            return null;
        }

        $referenceDate = $this->resolveReferenceDate($order);
        if ($referenceDate === null) {
            return 'Tizzy and Microsoft subscriptions can only be canceled within 6 days of the subscription start date.';
        }

        $deadlineDay = $referenceDate->copy()->addDays(self::CANCELLABLE_DAYS);
        $deadline = $deadlineDay->copy()->endOfDay();
        if (Carbon::now()->gt($deadline)) {
            return 'Tizzy and Microsoft subscriptions can only be canceled within 6 days of the subscription start date (last date: '
                . $deadlineDay->format('d-m-Y') . ').';
        }

        return null;
    }

    private function resolveReferenceDate(object $order): ?Carbon
    {
        $orderStart = $this->parseDate($order->subscription_start_date ?? null);
        if ($orderStart !== null) {
            return $orderStart;
        }

        $orderId = (int) ($order->id ?? 0);
        if ($orderId > 0) {
            $itemStarts = OrderItem::query()
                ->where('order_id', $orderId)
                ->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhereNotIn('status', ['delete', 'deleted']);
                })
                ->pluck('subscription_start_date')
                ->filter(static fn ($value) => $value !== null && trim((string) $value) !== '')
                ->map(fn ($value) => $this->parseDate($value))
                ->filter()
                ->values();

            if ($itemStarts->isNotEmpty()) {
                return $itemStarts->sortBy(static fn (Carbon $date) => $date->timestamp)->first();
            }
        }

        return $this->parseDate($order->created_at ?? null);
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function isActiveStatus(object $order): bool
    {
        $status = strtolower(trim((string) ($order->status ?? '')));

        return in_array($status, self::ACTIVE_STATUSES, true);
    }

    /**
     * Partner Panel action flags.
     *
     * Cancel:
     * - pending/processing: anytime (including Google)
     * - active: within 6 days of subscription start for Tizzy/Microsoft only (never Google)
     *
     * Edit:
     * - pending/processing: anytime for non-Google
     * - active: within 6 days of subscription start for Tizzy/Microsoft only (never Google)
     *
     * @return array{
     *   can_edit: bool,
     *   can_cancel: bool,
     *   show_edit: bool,
     *   show_cancel: bool,
     *   is_google_order: bool,
     *   is_tizzy_or_microsoft: bool,
     *   action_window_days: int,
     *   action_deadline: ?string,
     *   action_deadline_label: ?string,
     *   days_remaining: ?int,
     *   edit_blocked_reason: ?string,
     *   cancel_blocked_reason: ?string
     * }
     */
    public function partnerPanelActionFlags(object $order, ?int $providerIdHint = null): array
    {
        if ($providerIdHint !== null && $providerIdHint > 0) {
            $order->provider_id = $providerIdHint;
        }

        $providerIds = $this->resolveProviderIds($order);
        $isGoogle = in_array(self::PROVIDER_GOOGLE, $providerIds, true);
        $isTizzyOrMicrosoft = (bool) array_intersect(
            $providerIds,
            [self::PROVIDER_TIZZY, self::PROVIDER_MICROSOFT]
        );
        $status = strtolower(trim((string) ($order->status ?? '')));
        $isTerminal = in_array($status, ['cancelled', 'canceled', 'rejected', 'failed', 'void', 'draft'], true);
        $isPreActive = in_array($status, ['pending', 'processing'], true);
        $isActive = $this->isActiveStatus($order);

        // Same reference as admin cancel policy: subscription start (fallback created_at).
        $referenceDate = $this->resolveReferenceDate($order);
        $deadline = $referenceDate?->copy()->addDays(self::CANCELLABLE_DAYS)->endOfDay();
        $withinWindow = $referenceDate !== null && $deadline !== null && Carbon::now()->lte($deadline);
        $daysRemaining = null;
        if ($referenceDate !== null && $deadline !== null && Carbon::now()->lte($deadline)) {
            $daysRemaining = max(0, (int) Carbon::now()->startOfDay()->diffInDays($deadline->copy()->startOfDay(), false));
        }

        $editReason = null;
        $cancelReason = null;

        if ($isTerminal) {
            $editReason = 'This order can no longer be edited.';
            $cancelReason = 'This order can no longer be canceled.';
        } else {
            // --- Cancel ---
            if ($isPreActive) {
                // Processing/pending: cancel anytime (including Google).
                $cancelReason = null;
            } elseif ($isActive) {
                if ($isGoogle) {
                    $cancelReason = 'Google orders cannot be canceled after activation.';
                } elseif (!$isTizzyOrMicrosoft) {
                    $cancelReason = 'Cancel after activation is allowed only for Tizzy and Microsoft orders.';
                } elseif ($referenceDate === null) {
                    $cancelReason = 'Subscription start date is missing.';
                } elseif (!$withinWindow) {
                    $cancelReason = 'After activation, Tizzy and Microsoft orders can be canceled only within '
                        . self::CANCELLABLE_DAYS
                        . ' days of the subscription start date'
                        . ($deadline ? ' (last date: ' . $deadline->format('d-m-Y') . ')' : '')
                        . '.';
                }
            } else {
                $cancelReason = 'This order cannot be canceled in its current status.';
            }

            // --- Edit ---
            if ($isGoogle) {
                $editReason = 'Google orders cannot be edited from the partner panel.';
            } elseif ($isPreActive) {
                $editReason = null;
            } elseif ($isActive) {
                if (!$isTizzyOrMicrosoft) {
                    $editReason = 'Edit after activation is allowed only for Tizzy and Microsoft orders.';
                } elseif ($referenceDate === null) {
                    $editReason = 'Subscription start date is missing.';
                } elseif (!$withinWindow) {
                    $editReason = 'After activation, edit is allowed only within '
                        . self::CANCELLABLE_DAYS
                        . ' days of the subscription start date'
                        . ($deadline ? ' (last date: ' . $deadline->format('d-m-Y') . ')' : '')
                        . '.';
                }
            } else {
                $editReason = 'This order cannot be edited in its current status.';
            }
        }

        $canEdit = $editReason === null;
        $canCancel = $cancelReason === null;

        return [
            'can_edit' => $canEdit,
            'can_cancel' => $canCancel,
            'show_edit' => $canEdit,
            'show_cancel' => $canCancel,
            'is_google_order' => $isGoogle,
            'is_tizzy_or_microsoft' => $isTizzyOrMicrosoft,
            'action_window_days' => self::CANCELLABLE_DAYS,
            'action_deadline' => $isActive ? $deadline?->toDateString() : null,
            'action_deadline_label' => $isActive ? $deadline?->format('d-m-Y') : null,
            'days_remaining' => $isActive ? $daysRemaining : null,
            'edit_blocked_reason' => $editReason,
            'cancel_blocked_reason' => $cancelReason,
        ];
    }

    public function canPartnerEdit(object $order, ?int $providerIdHint = null): bool
    {
        return (bool) ($this->partnerPanelActionFlags($order, $providerIdHint)['can_edit'] ?? false);
    }

    public function canPartnerCancel(object $order, ?int $providerIdHint = null): bool
    {
        return (bool) ($this->partnerPanelActionFlags($order, $providerIdHint)['can_cancel'] ?? false);
    }

    /**
     * @return int[]
     */
    private function resolveProviderIds(object $order): array
    {
        $explicitProviderId = (int) ($order->provider_id ?? 0);
        if ($explicitProviderId > 0) {
            return [$explicitProviderId];
        }

        $planIds = [];
        $orderPlanId = (int) ($order->plan_id ?? 0);
        if ($orderPlanId > 0) {
            $planIds[] = $orderPlanId;
        }

        $orderId = (int) ($order->id ?? 0);
        if ($orderId > 0) {
            $itemPlanIds = OrderItem::query()
                ->where('order_id', $orderId)
                ->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhereNotIn('status', ['delete', 'deleted']);
                })
                ->pluck('plan_id')
                ->map(static fn ($planId) => (int) $planId)
                ->filter(static fn ($planId) => $planId > 0)
                ->all();
            $planIds = array_merge($planIds, $itemPlanIds);
        }

        $planIds = array_values(array_unique(array_filter($planIds, static fn ($planId) => $planId > 0)));
        if ($planIds === []) {
            return [];
        }

        return Plan::query()
            ->whereIn('id', $planIds)
            ->pluck('provider_id')
            ->map(static fn ($providerId) => (int) $providerId)
            ->filter(static fn ($providerId) => $providerId > 0)
            ->unique()
            ->values()
            ->all();
    }
}
