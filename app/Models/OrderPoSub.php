<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model representing a single row in the `order_po_sub` table.
 * @property int $id
 * @property int $order_id
 * @property string|null $description
 * @property string|null $hsn_sac
 * @property float|null $qty
 * @property float|null $cnt_rate
 * @property float|null $taxable_amt
 * @property string|null $tax_type
 * @property float|null $cgst
 * @property float|null $sgst
 * @property float|null $igst
 * @property float|null $nettotal
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class OrderPoSub extends Model
{
    protected $connection = 'pgsql_billing';
    use HasFactory;

    protected $table = 'order_po_sub';

    protected $fillable = [
        'order_id',
        'description',
        'hsn_sac',
        'qty',
        'cnt_rate',
        'taxable_amt',
        'tax_type',
        'cgst',
        'sgst',
        'igst',
        'nettotal',
    ];

    protected $casts = [
        'id'           => 'integer',
        'order_id'     => 'integer',
        'description'  => 'string',
        'hsn_sac'      => 'string',
        'qty'          => 'decimal:2',
        'cnt_rate'     => 'decimal:2',
        'taxable_amt'  => 'decimal:2',
        'tax_type'     => 'string',
        'cgst'         => 'decimal:2',
        'sgst'         => 'decimal:2',
        'igst'         => 'decimal:2',
        'nettotal'     => 'decimal:2',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    public $timestamps = true;

    /**
     * Get the order associated with this PO sub item.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
