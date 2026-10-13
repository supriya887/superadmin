<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $connection = 'pgsql_billing';
    public $timestamps = false;

    protected $table = 'cart_items';

    protected $fillable = [
        'cart_id',
        'plan_id',
        'licenses',
        'unit_price',
        'grand_total',
        'subscription_start_date',
        'subscription_end_date',
        'added_at',
    ];

    protected $casts = [
        'licenses' => 'integer',
        'unit_price' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'subscription_start_date' => 'date',
        'subscription_end_date' => 'date',
        'added_at' => 'datetime',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }
}
