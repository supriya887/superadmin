<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Partner;

/**
 * Per-partner notification channel preferences.
 *
 * One row per partner; each row holds 12 boolean toggles arranged as a
 * 4 × 3 matrix of (category × channel):
 *
 *   categories: subscription | security | service | offers
 *   channels:   sms          | whatsapp | email
 *
 * Schema → notification_preferences (see migration / raw SQL provided).
 */
class NotificationPreference extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_auth';
    protected $table = 'notification_preferences';

    public const CATEGORIES = ['subscription', 'security', 'service', 'offers'];
    public const CHANNELS = ['sms', 'whatsapp', 'email'];

    protected $fillable = [
        'partner_id',

        'subscription_sms',
        'subscription_whatsapp',
        'subscription_email',

        'security_sms',
        'security_whatsapp',
        'security_email',

        'service_sms',
        'service_whatsapp',
        'service_email',

        'offers_sms',
        'offers_whatsapp',
        'offers_email',
    ];

    protected $casts = [
        'partner_id' => 'integer',

        'subscription_sms' => 'boolean',
        'subscription_whatsapp' => 'boolean',
        'subscription_email' => 'boolean',

        'security_sms' => 'boolean',
        'security_whatsapp' => 'boolean',
        'security_email' => 'boolean',

        'service_sms' => 'boolean',
        'service_whatsapp' => 'boolean',
        'service_email' => 'boolean',

        'offers_sms' => 'boolean',
        'offers_whatsapp' => 'boolean',
        'offers_email' => 'boolean',
    ];

    protected $attributes = [
        'subscription_sms' => 0,
        'subscription_whatsapp' => 0,
        'subscription_email' => 0,

        'security_sms' => 0,
        'security_whatsapp' => 0,
        'security_email' => 0,

        'service_sms' => 0,
        'service_whatsapp' => 0,
        'service_email' => 0,

        'offers_sms' => 0,
        'offers_whatsapp' => 0,
        'offers_email' => 0,
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    /**
     * Default OFF row used when a customer doesn't have a preferences row yet.
     *
     * @return array<string, mixed>
     */
    public static function defaultsFor(int $partnerId): array
    {
        $row = ['partner_id' => $partnerId];
        foreach (self::CATEGORIES as $category) {
            foreach (self::CHANNELS as $channel) {
                $row[$category . '_' . $channel] = 0;
            }
        }

        return $row;
    }

    /**
     * Lookup row for a partner; lazily creates a defaults-OFF row if missing.
     */
    public static function forPartner(int $partnerId): self
    {
        return self::firstOrCreate(
            ['partner_id' => $partnerId],
            self::defaultsFor($partnerId)
        );
    }

    /**
     * Is the given (category, channel) toggle ON for this row?
     *
     * Returns false for unknown category/channel pairs instead of throwing —
     * callers can safely query arbitrary combinations.
     */
    public function isEnabled(string $category, string $channel): bool
    {
        if (!in_array($category, self::CATEGORIES, true)) {
            return false;
        }
        if (!in_array($channel, self::CHANNELS, true)) {
            return false;
        }

        return (bool) $this->getAttribute($category . '_' . $channel);
    }

    /**
     * Toggle matrix in a UI-friendly nested shape:
     * [ category => [ channel => bool ] ].
     *
     * @return array<string, array<string, bool>>
     */
    public function matrix(): array
    {
        $matrix = [];
        foreach (self::CATEGORIES as $category) {
            foreach (self::CHANNELS as $channel) {
                $matrix[$category][$channel] = (bool) $this->getAttribute($category . '_' . $channel);
            }
        }

        return $matrix;
    }
}
