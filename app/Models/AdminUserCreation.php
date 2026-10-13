<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminUserCreation extends Model
{
    public const SUPPORT_STATUS_ACTIVE = 'active';
    public const SUPPORT_STATUS_INACTIVE = 'inactive';
    public const SUPPORT_STATUS_AWAY = 'away';

    public const SUPPORT_STATUSES = [
        self::SUPPORT_STATUS_ACTIVE,
        self::SUPPORT_STATUS_INACTIVE,
        self::SUPPORT_STATUS_AWAY,
    ];

    protected $table = 'admin_user_creation';
    protected $connection = 'pgsql_admin';
    protected $guarded = ['id'];
    protected $fillable = [
        'branch_id',
        'user_name',
        'password',
        'country',
        'state',
        'city',
        'status',
        'role',
        'support_status',
        'last_active_at',
        'last_logout_at',
    ];

    protected $casts = [
        'last_active_at' => 'datetime',
        'last_logout_at' => 'datetime',
    ];

    /**
     * Support agents only (role stored as int or string, so compare as text).
     */
    public function scopeSupportAgents($query)
    {
        $roleId = (string) config('services.support.agent_role_id', 3);

        return $query->where('status', 1)
            ->whereRaw('TRIM(CAST(role AS TEXT)) = ?', [$roleId]);
    }
}
