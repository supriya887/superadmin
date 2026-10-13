<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminUserRolePermission extends Model
{
    protected $table = 'admin_user_role_permission';
    protected $connection = 'pgsql_admin';
    protected $guarded = ['id'];
    protected $fillable = [
        'role_id',
        'module_id',
        'permission'
    ];
    public $timestamps = false;
}
