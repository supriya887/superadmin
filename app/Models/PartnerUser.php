<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerUser extends Model
{
    protected $connection = 'pgsql_auth';
    protected $table = 'partner_users';

    protected $fillable = [
        'partner_id',
        'name',
        'employee_id',
        'designation',
        'mobile',
        'email',
        'status',
        'last_login_at',
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(PartnerPermission::class, 'partner_user_id');
    }
}
