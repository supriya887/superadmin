<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseDecreaseRequest extends Model
{
    protected $connection = 'pgsql_billing';

    protected $table = 'license_decrease_requests';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_REVERTED = 'reverted';

    protected $fillable = [
        'partner_id',
        'order_id',
        'cart_id',
        'plan_id',
        'current_licenses',
        'requested_licenses',
        'status',
        'remarks',
        'admin_note',
        'decided_by',
        'decided_at',
    ];

    protected $casts = [
        'partner_id' => 'integer',
        'order_id' => 'integer',
        'cart_id' => 'integer',
        'plan_id' => 'integer',
        'current_licenses' => 'integer',
        'requested_licenses' => 'integer',
        'decided_by' => 'integer',
        'decided_at' => 'datetime',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }

    public function isPending(): bool
    {
        return strtolower(trim((string) ($this->status ?? ''))) === self::STATUS_PENDING;
    }
}
