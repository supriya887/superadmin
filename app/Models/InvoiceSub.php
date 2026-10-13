<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceSub extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_billing';
    protected $table = 'invoice_sub';

    protected $fillable = [
        'cnt_rate',
        'taxable_amt',
        'tax_type',
        'cgst',
        'sgst',
        'qty',
        'nettotal',
    ];

    protected $casts = [
        'cnt_rate'    => 'decimal:2',
        'taxable_amt' => 'decimal:2',
        'cgst'        => 'decimal:2',
        'sgst'        => 'decimal:2',
        'qty'         => 'integer',
        'nettotal'    => 'decimal:2',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    public $timestamps = true;
}
