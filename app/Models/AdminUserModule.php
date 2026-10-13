<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminUserModule extends Model
{
    protected $table = 'admin_user_module';
    protected $connection = 'pgsql_admin';
    protected $guarded = ['id'];
    protected $fillable = [
        'mst_id',
        'modules',
        'permissions',
        'status'
    ];
}
