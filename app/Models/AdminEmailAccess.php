<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminEmailAccess extends Model
{
    protected $table = 'admin_email_access';
    protected $connection = 'pgsql_admin';
    protected $guarded = ['id'];
    protected $fillable = [
        'access_name'
    ];
    public $timestamps = false;
}
