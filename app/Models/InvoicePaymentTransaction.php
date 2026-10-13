<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoicePaymentTransaction extends Model
{
    protected $connection = 'pgsql_billing';
    protected $table = 'invoice_payment_transactions';

    protected $fillable = [
        'partner_id',
        'status',
        'total_amount',
        'currency',
        'gateway',
        'gateway_order_id',
        'gateway_payment_id',
        'meta',
    ];

    protected $casts = [
        'id'                  => 'integer',
        'partner_id'          => 'integer',
        'status'              => 'string',
        'total_amount'        => 'decimal:2',
        'currency'            => 'string',
        'gateway'             => 'string',
        'gateway_order_id'    => 'string',
        'gateway_payment_id'  => 'string',
        'meta'                => 'array',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
    ];

    public $timestamps = true;

    /**
     * Get the partner associated with the transaction.
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    /**
     * Get the items for this transaction.
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoicePaymentTransactionItem::class, 'invoice_payment_transaction_id');
    }
}
