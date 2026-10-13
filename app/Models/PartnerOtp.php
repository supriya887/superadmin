<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerOtp extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_auth'; // Adjust if you use MySQL, e.g., 'mysql'
    
    protected $table = 'partner_otps';

    protected $fillable = [
        'partner_id',
        'otp',
        'type',
        'expires_at',
        'verified',
        'login_field',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    public $timestamps = false;

    protected $casts = [
        'partner_id' => 'integer',
        'expires_at' => 'datetime',
        'verified' => 'boolean',
        'created_at' => 'datetime',
    ];

    /**
     * Get the partner that owns the OTP.
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }
}