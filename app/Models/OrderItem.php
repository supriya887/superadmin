<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model representing a single row in the `order_items` table.
 * Each Order can have multiple OrderItems.
 * This model's properties align with the table structure seen in the provided schema.
 */
class OrderItem extends Model
{
    protected $connection = 'pgsql_billing';
    use HasFactory;

    protected $table = 'order_items';

    protected $fillable = [
        'order_id',
        'plan_id',
        'variant_id',
        'item_name',
        'description',
        'hsn_sac',
        'quantity',
        'unit_price',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'total_amount',
        'subscription_id',
        'subscription_start_date',
        'subscription_end_date',
        'remaining_days',
        'total_plan_days',
        'prorated_credit',
        'meta',
        'status',
        'previous_order_sub_id',
    ];

    protected $casts = [
        'id'                      => 'integer',
        'order_id'                => 'integer',
        'plan_id'                 => 'integer',
        'variant_id'              => 'integer',
        'item_name'               => 'string',
        'description'             => 'string',
        'hsn_sac'                 => 'string',
        'quantity'                => 'integer',
        'unit_price'              => 'decimal:2',
        'discount_amount'         => 'decimal:2',
        'tax_rate'                => 'decimal:2',
        'tax_amount'              => 'decimal:2',
        'total_amount'            => 'decimal:2',
        'subscription_id'         => 'string',
        'subscription_start_date' => 'date',
        'subscription_end_date'   => 'date',
        'remaining_days'          => 'integer',
        'total_plan_days'         => 'integer',
        'prorated_credit'         => 'decimal:2',
        'meta'                    => 'array',
        'previous_order_sub_id'   => 'integer',
        'created_at'              => 'datetime',
        'updated_at'              => 'datetime',
    ];

    public $timestamps = true;

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            if ($item->status === null || trim((string) $item->status) === '') {
                $item->status = 'active';
            }
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function withCreateDefaults(array $attributes): array
    {
        if (!array_key_exists('status', $attributes) || $attributes['status'] === null || $attributes['status'] === '') {
            $attributes['status'] = 'active';
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function withUpgradeDefaults(array $attributes, ?int $sourceOrderSubId = null): array
    {
        $attributes = self::withCreateDefaults($attributes);
        if ($sourceOrderSubId > 0) {
            $attributes['previous_order_sub_id'] = $sourceOrderSubId;
        }

        return $attributes;
    }

    /**
     * Get the order that owns this item.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Get the plan associated with this order item.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    /**
     * Get the variant associated with this order item.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class, 'variant_id');
    }

    /**
     * Status values that should not appear in order edit UI or active line totals.
     *
     * @return list<string>
     */
    public static function inactiveStatuses(): array
    {
        return ['delete', 'upgraded', 'downgraded', 'cancelled', 'inactive'];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActiveLine($query)
    {
        $inactive = self::inactiveStatuses();

        return $query->where(function ($q) use ($inactive) {
            $q->whereNull('status')
                ->orWhereNotIn('status', $inactive);
        });
    }
}
