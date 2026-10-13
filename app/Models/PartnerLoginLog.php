<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerLoginLog extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_auth'; // adjust if not using pgsql
    protected $table = 'partner_login_logs';

    protected $fillable = [
        'partner_id',
        'identifier',
        'action',
        'ip_address',
        'user_agent',
        'payload',
        'created_at',
        'device_name'
    ];

    public $timestamps = false;

    protected $casts = [
        'partner_id' => 'integer',
        'created_at' => 'datetime',
    ];
    
    /**
     * Get the partner that owns the login log.
     */
    public function partner()
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }
}