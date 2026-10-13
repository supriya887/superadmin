<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailOtp extends Model
{
    protected $connection = 'pgsql_auth';
    protected $table = 'email_otp';
    protected $fillable = [
        'user_id',
        'otp',
        'type',
        'expires_at',
        'verified',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified'   => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
