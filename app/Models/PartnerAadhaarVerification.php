<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerAadhaarVerification extends Model
{
    public const STATUS_INIT = 'init';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_ORDER_LINKED = 'order_linked';

    protected $connection = 'pgsql_auth';

    protected $table = 'partner_aadhaar_verifications';

    protected $fillable = [
        'customer_id',
        'partner_id',
        'cart_id',
        'order_id',
        'aadhaar_number',
        'aadhaar_masked',
        'otp',
        'aadhaar_ref_no',
        'verification_mode',
        'status',
        'verified_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
