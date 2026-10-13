<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $connection = 'pgsql_billing';

    use HasFactory;

    protected $table = 'orders';

    protected $fillable = [
        'order_no',
        'sr_no',
        'user_id',
        'plan_id',
        'previous_plan_id',
        'parent_order_id',
        'parent_order_sub_id',
        'payment_mode',
        'bank_name',
        'cheque_ref_no',
        'upi_transaction_id',
        'order_note',
        'domain_name',
        'price',
        'quantity',
        'duration',
        'subscription_start_date',
        'subscription_end_date',
        'renewal_at',
        'promo_code',
        'discount_amount',
        'pro_rata_adjustment',
        'apply_gst',
        'gst_rate',
        'gst_amount',
        'tax_amount',
        'total_amount',
        'subtotal',
        'razorpay_order_id',
        'razorpay_payment_id',
        'razorpay_signature',
        'payment_method',
        'status',
        'is_legacy',
        'order_type',
        'order_category',
        'credit_used',
        'partner_id',
        'aadhaar_verification_id',
        'cart_id',
        'source_cart_id',
        'transfer_domain',
        'po_document_url',
        // NOTE: Aadhaar journey columns (aadhaar_verified, aadhaar_masked,
        // aadhaar_ref_no, aadhaar_journey_status, etc.) were dropped from
        // `orders` — they live on `order_po_details` (PO journey) and
        // `partner_aadhaar_verifications` (raw value + OTP audit).
        // NOTE: Sandbox e-invoicing fields (irn, signed_qr_code, ack_no, etc.)
        // are stored on the `invoices` table — see App\Models\Invoice.
    ];

    protected $casts = [
        'subscription_start_date' => 'date',
        'subscription_end_date' => 'date',
        'renewal_at' => 'date',
        'apply_gst' => 'boolean',
        'is_legacy' => 'boolean',
        'price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'pro_rata_adjustment' => 'decimal:2',
        'gst_rate' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'credit_used' => 'decimal:2',
        'parent_order_sub_id' => 'integer',
    ];

    public const PAYMENT_LINK_WINDOW_HOURS = 24;

    /** @deprecated Kept for API field compatibility. */
    public const PAYMENT_LINK_MAX_ATTEMPTS = 3;

    private const PAYMENT_LINK_TIMEZONE = 'Asia/Kolkata';

    private const PAYMENT_RETRY_MARKER = '<!--PAYMENT_RETRY:';

    /** Order lifecycle statuses that mean the payment link is settled (not merely placed). */
    private const PAYMENT_LINK_PAID_STATUSES = ['paid', 'success'];
    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'user_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function invoice()
    {
        return $this->hasOne(InvoiceMst::class, 'order_id');
    }

    public function parentOrder()
    {
        return $this->belongsTo(Order::class, 'parent_order_id');
    }

    public function childOrders()
    {
        return $this->hasMany(Order::class, 'parent_order_id');
    }
    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    public function creditRequests()
    {
        return $this->hasMany(CreditRequest::class, 'order_id');
    }

    /**
     * Per-plan line items for this order (multi-plan orders).
     * Cart checkout, legacy/manual orders and home-flow all write here so
     * a single order can carry many plan rows with their own quantity/price.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    /**
     * Canonical Aadhaar verification record for this order — raw Aadhaar
     * value, masking, OTP audit and journey status all live here.
     */
    public function aadhaarVerification()
    {
        return $this->belongsTo(PartnerAadhaarVerification::class, 'aadhaar_verification_id');
    }

    /**
     * @return array{
     *     started_at: ?string,
     *     expires_at: ?string,
     *     attempts: int,
     *     last_failed_at: ?string,
     *     cooldown_expires_at: ?string,
     *     expired_at: ?string
     * }
     */
    public function paymentRetryMeta(): array
    {
        $note = (string) ($this->order_note ?? '');
        if (preg_match('/' . preg_quote(self::PAYMENT_RETRY_MARKER, '/') . '(.+?)-->/s', $note, $matches)) {
            $decoded = json_decode(trim((string) ($matches[1] ?? '')), true);
            if (is_array($decoded)) {
                return [
                    'started_at' => $decoded['started_at'] ?? null,
                    'expires_at' => $decoded['expires_at'] ?? null,
                    'attempts' => max(0, (int) ($decoded['attempts'] ?? 0)),
                    'last_failed_at' => $decoded['last_failed_at'] ?? null,
                    'cooldown_expires_at' => $decoded['cooldown_expires_at'] ?? null,
                    'expired_at' => $decoded['expired_at'] ?? null,
                ];
            }
        }

        return [
            'started_at' => null,
            'expires_at' => null,
            'attempts' => 0,
            'last_failed_at' => null,
            'cooldown_expires_at' => null,
            'expired_at' => null,
        ];
    }

    /**
     * Human-readable order note for UI (strips internal <!--PAYMENT_RETRY:...--> payloads,
     * including truncated markers that never got a closing -->).
     */
    public function displayOrderNote(): string
    {
        $note = (string) ($this->order_note ?? '');
        $note = preg_replace('/' . preg_quote(self::PAYMENT_RETRY_MARKER, '/') . '.*?-->/s', '', $note) ?? $note;
        $note = preg_replace('/' . preg_quote(self::PAYMENT_RETRY_MARKER, '/') . '.*$/s', '', $note) ?? $note;
        $note = preg_replace('/<!--\s*PAYMENT_RETRY:.*?-->/is', '', $note) ?? $note;

        return trim($note);
    }

    /**
     * Cancellation / rejection reason for admin UI (never exposes payment-retry JSON).
     */
    public function displayCancellationReason(): string
    {
        return $this->displayOrderNote();
    }

    protected function writePaymentRetryMeta(array $meta): void
    {
        $note = (string) ($this->order_note ?? '');
        $note = preg_replace('/' . preg_quote(self::PAYMENT_RETRY_MARKER, '/') . '.+?-->/s', '', $note) ?? $note;
        $note = rtrim($note);
        $payload = self::PAYMENT_RETRY_MARKER . json_encode([
            'started_at' => $meta['started_at'] ?? null,
            'expires_at' => $meta['expires_at'] ?? null,
            'attempts' => max(0, (int) ($meta['attempts'] ?? 0)),
            'last_failed_at' => $meta['last_failed_at'] ?? null,
            'cooldown_expires_at' => $meta['cooldown_expires_at'] ?? null,
            'expired_at' => $meta['expired_at'] ?? null,
        ]) . '-->';
        $this->order_note = $note !== '' ? ($note . "\n" . $payload) : $payload;
    }

    private function paymentLinkNow(): Carbon
    {
        return Carbon::now(self::PAYMENT_LINK_TIMEZONE);
    }

    private function parsePaymentLinkTime(string $value): Carbon
    {
        return Carbon::parse($value)->timezone(self::PAYMENT_LINK_TIMEZONE);
    }

    public function ensurePaymentLinkWindow(): self
    {
        $meta = $this->paymentRetryMeta();

        if (!empty($meta['started_at']) && empty($meta['expires_at'])) {
            try {
                $started = $this->parsePaymentLinkTime((string) $meta['started_at']);
                $meta['expires_at'] = $started->copy()->addHours(self::PAYMENT_LINK_WINDOW_HOURS)->toIso8601String();
                $this->writePaymentRetryMeta($meta);
                $this->save();

                return $this->fresh() ?? $this;
            } catch (\Throwable) {
                // Fall through and start a fresh window.
            }
        }

        if (!empty($meta['started_at'])) {
            return $this;
        }

        $started = $this->paymentLinkNow();
        $this->writePaymentRetryMeta([
            'started_at' => $started->toIso8601String(),
            'expires_at' => $started->copy()->addHours(self::PAYMENT_LINK_WINDOW_HOURS)->toIso8601String(),
            'attempts' => 0,
            'expired_at' => null,
        ]);
        $this->save();

        return $this->fresh() ?? $this;
    }

    /**
     * True when online payment has been captured (Razorpay id or explicit paid status).
     * B2B orders are often marked completed before invoice payment — that is not paid here.
     */
    public function isPaymentLinkPaid(): bool
    {
        if (trim((string) ($this->razorpay_payment_id ?? '')) !== '') {
            return true;
        }

        return in_array(
            strtolower(trim((string) ($this->status ?? ''))),
            self::PAYMENT_LINK_PAID_STATUSES,
            true
        );
    }

    public function isPaymentLinkWindowExpired(): bool
    {
        if ($this->isPaymentLinkPaid()) {
            return false;
        }

        $meta = $this->paymentRetryMeta();
        if (empty($meta['expires_at'])) {
            return false;
        }

        try {
            return $this->paymentLinkNow()->gte($this->parsePaymentLinkTime((string) $meta['expires_at']));
        } catch (\Throwable) {
            return false;
        }
    }

    public function isPaymentLinkAttemptsExhausted(): bool
    {
        return !$this->isPaymentLinkPaid()
            && $this->paymentLinkFailedAttempts() >= self::PAYMENT_LINK_MAX_ATTEMPTS;
    }

    public function isPaymentLinkInAttemptCooldown(): bool
    {
        if (!$this->isPaymentLinkAttemptsExhausted()) {
            return false;
        }

        $cooldownEnd = $this->paymentLinkCooldownExpiresAt();

        return $cooldownEnd instanceof Carbon && $this->paymentLinkNow()->lt($cooldownEnd);
    }

    public function isPaymentLinkCooldownFinished(): bool
    {
        if (!$this->isPaymentLinkAttemptsExhausted()) {
            return false;
        }

        $cooldownEnd = $this->paymentLinkCooldownExpiresAt();

        return !$cooldownEnd instanceof Carbon || $this->paymentLinkNow()->gte($cooldownEnd);
    }

    /** Blocked from paying online (24h link over, or 3 failures during cooldown). */
    public function isPaymentLinkExpired(): bool
    {
        if ($this->isPaymentLinkPaid()) {
            return false;
        }

        $this->maybeResetPaymentLinkAfterCooldown();

        return $this->isPaymentLinkWindowExpired() || $this->isPaymentLinkInAttemptCooldown();
    }

    /**
     * Re-open payment when:
     * - 3 failed attempts cooldown has finished, OR
     * - the 24h payment window has ended (and not inside attempt cooldown).
     * Always starts a fresh 24h window with attempts reset.
     */
    public function maybeResetPaymentLinkAfterCooldown(): self
    {
        if ($this->isPaymentLinkPaid()) {
            return $this;
        }

        // Still locked after 3 failures — wait out remaining_hrs.
        if ($this->isPaymentLinkInAttemptCooldown()) {
            return $this;
        }

        $attemptsExhausted = $this->isPaymentLinkAttemptsExhausted();
        $windowExpired = $this->isPaymentLinkWindowExpired();

        // Nothing to renew: window still open and attempts not exhausted.
        if (!$windowExpired && !$attemptsExhausted) {
            return $this;
        }

        $meta = $this->paymentRetryMeta();
        $started = $this->paymentLinkNow();
        $meta['attempts'] = 0;
        $meta['cooldown_expires_at'] = null;
        $meta['last_failed_at'] = null;
        $meta['expired_at'] = null;
        $meta['started_at'] = $started->toIso8601String();
        $meta['expires_at'] = $started->copy()->addHours(self::PAYMENT_LINK_WINDOW_HOURS)->toIso8601String();
        $this->writePaymentRetryMeta($meta);
        $this->save();

        return $this->fresh() ?? $this;
    }

    public function forceResetPaymentLinkAttempts(): self
    {
        $meta = $this->paymentRetryMeta();
        $started = $this->paymentLinkNow();
        $meta['attempts'] = 0;
        $meta['cooldown_expires_at'] = null;
        $meta['last_failed_at'] = null;
        $meta['expired_at'] = null;
        $meta['started_at'] = $started->toIso8601String();
        $meta['expires_at'] = $started->copy()->addHours(self::PAYMENT_LINK_WINDOW_HOURS)->toIso8601String();
        $this->writePaymentRetryMeta($meta);
        $this->save();

        return $this->fresh() ?? $this;
    }

    private function paymentLinkCooldownExpiresAt(): ?Carbon
    {
        $meta = $this->paymentRetryMeta();

        if (!empty($meta['cooldown_expires_at'])) {
            try {
                return $this->parsePaymentLinkTime((string) $meta['cooldown_expires_at']);
            } catch (\Throwable) {
                return null;
            }
        }

        if ($this->paymentLinkFailedAttempts() >= self::PAYMENT_LINK_MAX_ATTEMPTS && !empty($meta['last_failed_at'])) {
            try {
                return $this->parsePaymentLinkTime((string) $meta['last_failed_at'])
                    ->addHours(self::PAYMENT_LINK_WINDOW_HOURS);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    public function paymentLinkBlockReason(): ?string
    {
        if ($this->isPaymentLinkPaid() || !$this->isPaymentLinkExpired()) {
            return null;
        }

        if ($this->isPaymentLinkWindowExpired() && !$this->isPaymentLinkInAttemptCooldown()) {
            return 'This payment link has expired. The 24-hour payment window has ended (IST).';
        }

        if ($this->isPaymentLinkInAttemptCooldown()) {
            $remainingHrs = $this->paymentLinkRemainingHoursForApi();
            $expiresLabel = $this->paymentLinkExpiresAtLabel();

            return sprintf(
                'Maximum payment attempts reached. You can retry in %d hour(s) (IST)%s.',
                $remainingHrs,
                $expiresLabel ? ' — until ' . $expiresLabel : ''
            );
        }

        return 'This payment link is no longer available for payment.';
    }

    public function paymentLinkFailedAttempts(): int
    {
        $meta = $this->paymentRetryMeta();

        return max(0, (int) ($meta['attempts'] ?? 0));
    }

    /** Remaining tries before the link is blocked for attempts (informational). */
    public function paymentLinkRemainingAttemptsLegacy(): int
    {
        if ($this->isPaymentLinkWindowExpired()) {
            return 0;
        }

        if ($this->isPaymentLinkInAttemptCooldown()) {
            return 0;
        }

        return max(0, self::PAYMENT_LINK_MAX_ATTEMPTS - $this->paymentLinkFailedAttempts());
    }

    public function paymentLinkExpiresAtLabel(): ?string
    {
        if ($this->isPaymentLinkInAttemptCooldown()) {
            $cooldownEnd = $this->paymentLinkCooldownExpiresAt();
            if ($cooldownEnd instanceof Carbon) {
                return $cooldownEnd->format('d-m-Y, h:i A') . ' IST';
            }
        }

        $meta = $this->paymentRetryMeta();
        if (empty($meta['expires_at'])) {
            return null;
        }

        try {
            return $this->parsePaymentLinkTime((string) $meta['expires_at'])
                ->format('d-m-Y, h:i A') . ' IST';
        } catch (\Throwable) {
            return null;
        }
    }

    public function paymentLinkRemainingHours(): float
    {
        if ($this->isPaymentLinkPaid()) {
            return 0.0;
        }

        $meta = $this->paymentRetryMeta();
        if (empty($meta['expires_at'])) {
            return (float) self::PAYMENT_LINK_WINDOW_HOURS;
        }

        try {
            $expires = $this->parsePaymentLinkTime((string) $meta['expires_at']);
            $now = $this->paymentLinkNow();
            if ($now->gte($expires)) {
                return 0.0;
            }

            return max(0.0, round($now->floatDiffInRealHours($expires), 2));
        } catch (\Throwable) {
            return (float) self::PAYMENT_LINK_WINDOW_HOURS;
        }
    }

    /**
     * Whole hours until retry unlocks — only after all 3 attempts (IST, no decimals).
     */
    public function paymentLinkRemainingHoursForApi(): int
    {
        if ($this->isPaymentLinkPaid() || !$this->isPaymentLinkInAttemptCooldown()) {
            return 0;
        }

        $cooldownEnd = $this->paymentLinkCooldownExpiresAt();
        if (!$cooldownEnd instanceof Carbon) {
            return 0;
        }

        $now = $this->paymentLinkNow();
        if ($now->gte($cooldownEnd)) {
            return 0;
        }

        return max(0, (int) round($now->floatDiffInRealHours($cooldownEnd), 0));
    }

    /**
     * @return array{
     *     allowed: bool,
     *     message?: string,
     *     failed_payment_attempts: int,
     *     remaining_attempts: int,
     *     remaining_hrs: int,
     *     expires_at: ?string
     * }
     */
    public function paymentLinkGate(): array
    {
        $this->ensurePaymentLinkWindow();

        $failedAttempts = $this->paymentLinkFailedAttempts();
        $expiresLabel = $this->paymentLinkExpiresAtLabel();

        if ($this->isPaymentLinkPaid()) {
            return [
                'allowed' => false,
                'message' => 'This order is already paid.',
                'failed_payment_attempts' => $failedAttempts,
                'remaining_attempts' => $this->paymentLinkRemainingAttemptsLegacy(),
                'remaining_hrs' => 0,
                'expires_at' => $expiresLabel,
            ];
        }

        $partnerId = (int) ($this->partner_id ?? 0);

        $this->markPaymentLinkExpiredIfTimedOut();
        $this->maybeResetPaymentLinkAfterCooldown();
        $failedAttempts = $this->paymentLinkFailedAttempts();
        $expiresLabel = $this->paymentLinkExpiresAtLabel();

        if ($this->isPaymentLinkWindowExpired()) {
            return [
                'allowed' => false,
                'message' => $this->paymentLinkBlockReason() ?? 'This payment link has expired.',
                'failed_payment_attempts' => $failedAttempts,
                'remaining_attempts' => $this->paymentLinkRemainingAttemptsLegacy(),
                'remaining_hrs' => 0,
                'expires_at' => $expiresLabel,
            ];
        }



        if ($this->isPaymentLinkInAttemptCooldown() || $this->isPaymentLinkAttemptsExhausted()) {
            $remainingHrs = $this->paymentLinkRemainingHoursForApi();

            return [
                'allowed' => false,
                'message' => $this->paymentLinkBlockReason()
                    ?? 'Maximum payment attempts reached for this payment link.',
                'failed_payment_attempts' => $failedAttempts,
                'remaining_attempts' => 0,
                'remaining_hrs' => $remainingHrs,
                'expires_at' => $this->paymentLinkExpiresAtLabel(),
            ];
        }

        $remainingAttempts = $this->paymentLinkRemainingAttemptsLegacy();

        return [
            'allowed' => true,
            'message' => sprintf(
                'You have %d payment attempt(s) remaining. Link expires %s (IST).',
                $remainingAttempts,
                $expiresLabel ?? 'soon'
            ),
            'failed_payment_attempts' => $failedAttempts,
            'remaining_attempts' => $remainingAttempts,
            'remaining_hrs' => 0,
            'expires_at' => $expiresLabel,
        ];
    }

    /**
     * Standard payment-retry payload for partner/B2B APIs.
     *
     * @return array{
     *     payment_link_expired: bool,
     *     payment_link_allowed: bool,
     *     failed_payment_attempts: int,
     *     payment_link_expires_at: ?string,
     *     payment_link_message: ?string
     * }
     */
    public function paymentLinkRetryForApi(): array
    {
        $this->ensurePaymentLinkWindow();
        $this->markPaymentLinkExpiredIfTimedOut();
        $this->maybeResetPaymentLinkAfterCooldown();

        $partnerId = (int) ($this->partner_id ?? 0);



        $failedAttempts = $this->paymentLinkFailedAttempts();
        $expiresLabel = $this->paymentLinkExpiresAtLabel();
        $windowExpired = $this->isPaymentLinkWindowExpired();
        $inCooldown = $this->isPaymentLinkInAttemptCooldown();
        $attemptsExhausted = $this->isPaymentLinkAttemptsExhausted();
        $remainingHrs = $this->paymentLinkRemainingHoursForApi();
        $remainingAttempts = $this->paymentLinkRemainingAttemptsLegacy();
        $blocked = !$this->isPaymentLinkPaid() && ($windowExpired || $inCooldown || $attemptsExhausted);

        if ($this->isPaymentLinkPaid()) {
            return [
                'payment_link_expired' => false,
                'payment_link_allowed' => false,
                'failed_payment_attempts' => $failedAttempts,
                'remaining_attempts' => $remainingAttempts,
                'remaining_hrs' => 0,
                'payment_link_expires_at' => $expiresLabel,
                'payment_link_message' => 'This order is already paid.',
            ];
        }

        if ($blocked) {
            return [
                'payment_link_expired' => true,
                'payment_link_allowed' => false,
                'failed_payment_attempts' => $failedAttempts,
                'remaining_attempts' => 0,
                'remaining_hrs' => $remainingHrs,
                'payment_link_expires_at' => $expiresLabel,
                'payment_link_message' => $this->paymentLinkBlockReason(),
            ];
        }

        return [
            'payment_link_expired' => false,
            'payment_link_allowed' => true,
            'failed_payment_attempts' => $failedAttempts,
            'remaining_attempts' => $remainingAttempts,
            'remaining_hrs' => 0,
            'payment_link_expires_at' => $expiresLabel,
            'payment_link_message' => sprintf(
                'You have %d payment attempt(s) remaining. Link expires %s (IST).',
                $remainingAttempts,
                $expiresLabel ?? 'soon'
            ),
        ];
    }

    public function recordPaymentLinkFailure(?string $reason = null): self
    {
        $this->ensurePaymentLinkWindow();

        if ($this->isPaymentLinkPaid() || $this->isPaymentLinkWindowExpired()) {
            return $this;
        }

        $meta = $this->paymentRetryMeta();
        if ((int) ($meta['attempts'] ?? 0) >= self::PAYMENT_LINK_MAX_ATTEMPTS) {
            return $this;
        }

        $now = $this->paymentLinkNow();
        $meta['attempts'] = (int) ($meta['attempts'] ?? 0) + 1;
        $meta['last_failed_at'] = $now->toIso8601String();

        if ($meta['attempts'] >= self::PAYMENT_LINK_MAX_ATTEMPTS) {
            $meta['cooldown_expires_at'] = $now->copy()
                ->addHours(self::PAYMENT_LINK_WINDOW_HOURS)
                ->toIso8601String();
            $meta['expired_at'] = null;
        }

        $note = (string) ($this->order_note ?? '');
        $note = preg_replace('/' . preg_quote(self::PAYMENT_RETRY_MARKER, '/') . '.+?-->/s', '', $note) ?? $note;
        $note = rtrim($note);
        if ($reason !== null && trim($reason) !== '') {
            $entry = 'Payment failed (' . $now->format('d-m-Y H:i') . ' IST): ' . trim($reason);
            $note = $note !== '' ? ($note . "\n" . $entry) : $entry;
        }

        $this->order_note = $note;
        $this->writePaymentRetryMeta($meta);
        $this->save();

        return $this->fresh() ?? $this;
    }

    public function markPaymentLinkExpiredIfTimedOut(): self
    {
        if ($this->isPaymentLinkPaid()) {
            return $this;
        }

        // Unlock first if attempt-cooldown finished (may open a fresh window).
        $this->maybeResetPaymentLinkAfterCooldown();

        if (!$this->isPaymentLinkWindowExpired()) {
            return $this;
        }

        $meta = $this->paymentRetryMeta();
        if (empty($meta['expires_at'])) {
            return $this;
        }

        try {
            if ($this->paymentLinkNow()->gte($this->parsePaymentLinkTime((string) $meta['expires_at']))) {
                $meta['expired_at'] = $this->paymentLinkNow()->toIso8601String();
                $this->writePaymentRetryMeta($meta);
                $this->save();
            }
        } catch (\Throwable) {
            return $this;
        }

        return $this->fresh() ?? $this;
    }
}
