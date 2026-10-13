<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    protected $connection = 'pgsql_billing';
    use HasFactory;

    public const TYPE_STANDARD = 'standard';

    public const TYPE_UPGRADE = 'upgrade';

    public const TYPE_LICENSE_ADD = 'license_add';

    public const TYPE_DOWNGRADE = 'downgrade';

    public const TYPE_RENEW = 'renew';

    public const TYPE_DOMAIN_TRANSFER = 'domain_transfer';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PENDING_CREDIT = 'pending_credit';

    public const STATUS_IN_CART = 'in_cart';

    public const STATUS_CONVERTED = 'converted';

    /** @var list<string> */
    public const LEGACY_STANDARD_TYPES = ['standard', 'new'];

    protected $table = 'carts';

    protected $fillable = [
        'session_id',
        'partner_id',
        'provider_id',
        'customer_id',
        'plan_id',
        'domain_name',
        'transfer_domain',
        'licenses',
        'unit_price',
        'type',
        'coupen',
        'coupon',
        'status',
        'currency',
        'discount_amount',
        'pro_rata_adjustment',
        'subtotal',
        'gst_percentage',
        'gst_amount',
        'grand_total',
        'subscription_start_date',
        'subscription_end_date',
        'wallet_used',
        'payable_after_wallet',
        'customer_limit',
        'item_type',
        'credit_request_id',
        'order_id',
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
        'pro_rata_adjustment' => 'decimal:2',
        'gst_percentage' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'subscription_start_date' => 'datetime',
        'subscription_end_date' => 'datetime',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class, 'cart_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeOfType($query, string $type)
    {
        if ($type === self::TYPE_STANDARD) {
            return $query->whereIn('type', self::LEGACY_STANDARD_TYPES);
        }

        return $query->where('type', $type);
    }
}
