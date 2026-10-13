<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePaymentTransactionItem extends Model
{
    protected $connection = 'pgsql_billing';
    protected $table = 'invoice_payment_transaction_items';

    protected $fillable = [
        'invoice_payment_transaction_id',
        'invoice_id',
        'amount',
    ];

    protected $casts = [
        'id'                           => 'integer',
        'invoice_payment_transaction_id'=> 'integer',
        'invoice_id'                   => 'integer',
        'amount'                       => 'decimal:2',
        'created_at'                   => 'datetime',
        'updated_at'                   => 'datetime',
    ];

    public $timestamps = true;

    /**
     * Get the transaction that owns the item.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(InvoicePaymentTransaction::class, 'invoice_payment_transaction_id');
    }

    /**
     * Get the invoice that this item refers to.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
}
