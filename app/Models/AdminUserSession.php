<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminUserSession extends Model
{
    protected $table = 'admin_user_session';
    protected $connection = 'pgsql_admin';
    protected $guarded = ['id'];
    protected $fillable = [
        'user_id',
        'logged_in',
        'logout',
        'ip_address'
    ];
    public $timestamps = false;
}
