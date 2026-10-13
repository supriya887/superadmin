<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerSession extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_auth'; // Using the auth partner DB connection

    protected $table = 'partner_sessions';

    protected $fillable = [
        'partner_id',
        'partner_user_id',
        'token_id',
        'device_id',
        'device_name',
        'device_type',
        'platform',
        'location',
        'logged_in',
        'last_active_at',
        'logout',
        'ip_address',
        'user_agent',
    ];

    public $timestamps = false;

    protected $casts = [
        'partner_id' => 'integer',
        'partner_user_id' => 'integer',
        'token_id' => 'integer',
        'logged_in' => 'datetime',
        'last_active_at' => 'datetime',
        'logout' => 'datetime',
        'ip_address' => 'string',
        'user_agent' => 'string',
    ];

    /**
     * Get the partner that owns the session.
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function partnerUser(): BelongsTo
    {
        return $this->belongsTo(PartnerUser::class, 'partner_user_id');
    }
}