<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    protected $connection = 'pgsql_auth';
    protected $table = 'users';
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'mobile',
        'country',
        'state',
        'city',
        'postcode',
        'gstin',
        'gst_id',
        'company_name',
        'company_address',
        'email_verified',
        'is_kyc_verified',
        'status',
        'password',
        'pan_no',
        'current_session_token',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'email_verified' => 'boolean',
        'is_kyc_verified' => 'boolean',
    ];

    public function emailOtps()
    {
        return $this->hasMany(EmailOtp::class);
    }

    public function gstDetail()
    {
        return $this->belongsTo(GstDetail::class, 'gst_id');
    }
}
