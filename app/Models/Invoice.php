<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Canonical invoice model on `invoice_mst`.
 */
class Invoice extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_billing';
    protected $table = 'invoice_mst';

    protected $fillable = [
        'order_id',
        'partner_id',
        'customer_id',
        'bill_no_full',
        'invoice_no',
        'tzg_invoice_no',
        'invoice_date',
        'bill_date',
        'due_date',
        'status',
        'net_amt',
        'amount',
        'subtotal',
        'taxable_total',
        'cgst_total',
        'sgst_total',
        'igst_total',
        'tax_type',
        'tax_amount',
        'total_amount',
        // Sandbox e-invoicing fields (NIC IRP / Sandbox API response).
        'irn',
        'signed_qr_code',
        'signed_invoice',
        'e_invoice_pdf_url',
        'acknowledgement_no',
        'acknowledgement_date',
        'is_cancelled',
    ];

    protected $casts = [
        'bill_date'    => 'date',
        'invoice_date' => 'date',
        'due_date'     => 'date',
        'net_amt'      => 'decimal:2',
        'amount'       => 'decimal:2',
        'subtotal'     => 'decimal:2',
        'taxable_total' => 'decimal:2',
        'cgst_total'   => 'decimal:2',
        'sgst_total'   => 'decimal:2',
        'igst_total'   => 'decimal:2',
        'tax_amount'   => 'decimal:2',
        'total_amount' => 'decimal:2',
        'is_cancelled' => 'boolean',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function paymentTransactionItems(): HasMany
    {
        return $this->hasMany(InvoicePaymentTransactionItem::class, 'invoice_id');
    }
}
