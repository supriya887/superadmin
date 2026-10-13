<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminSetting extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_admin';

    protected $table = 'admin_settings';

    protected $fillable = ['setting_name', 'setting_value'];
}
