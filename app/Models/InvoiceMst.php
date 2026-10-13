<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceMst extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_billing';

    protected $table = 'invoice_mst';

    protected $fillable = [
        'bill_no_full',
        'bill_date',
        'cust_id',
        'net_amt',
        'status',
        'taxable_total',
        'cgst_total',
        'sgst_total',
        'action_type',
        'order_id',
    ];

    protected $casts = [
        'bill_date' => 'date',
        'net_amt' => 'decimal:2',
        'taxable_total' => 'decimal:2',
        'cgst_total' => 'decimal:2',
        'sgst_total' => 'decimal:2',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceSub::class, 'invoice_mst_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cust_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
