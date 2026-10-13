<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminUserModuleMst extends Model
{
    protected $table = 'admin_user_module_mst';
    protected $guarded = ['id'];

    protected $fillable = [
        'module_name',
        'status',
        'created_at',
        'updated_at'
    ];
    
    public $timestamps = true;
}