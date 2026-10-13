<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRefund extends Model
{
    protected $connection = 'pgsql_billing';
    protected $table = 'payment_refunds';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_FAILED = 'failed';

    public const SOURCE_ORDER = 'order';
    public const SOURCE_INVOICE_PAYMENT = 'invoice_payment';

    protected $fillable = [
        'source_type',
        'source_id',
        'partner_id',
        'order_id',
        'invoice_payment_transaction_id',
        'razorpay_payment_id',
        'razorpay_refund_id',
        'amount',
        'currency',
        'reason',
        'status',
        'is_partial',
        'requested_by',
        'meta',
        'failure_message',
        'processed_at',
    ];

    protected $casts = [
        'partner_id' => 'integer',
        'order_id' => 'integer',
        'invoice_payment_transaction_id' => 'integer',
        'source_id' => 'integer',
        'amount' => 'decimal:2',
        'is_partial' => 'boolean',
        'meta' => 'array',
        'processed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function invoicePaymentTransaction(): BelongsTo
    {
        return $this->belongsTo(InvoicePaymentTransaction::class, 'invoice_payment_transaction_id');
    }
}
