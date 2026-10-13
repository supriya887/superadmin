<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditRequest extends Model
{
    protected $connection = 'pgsql_billing';

    protected $table = 'credit_requests';

    protected $fillable = [
        'partner_id',
        'order_id',
        'cart_id',
        'requested_amount',
        'approved_amount',
        'status',
        'remarks',
        'admin_note',
    ];

    protected $casts = [
        'requested_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'cart_id' => 'integer',
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
}