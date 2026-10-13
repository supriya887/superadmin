<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmEmployeePermission extends Model
{
    protected $connection = 'pgsql_crm';

    protected $table = 'crm_employee_permissions';

    protected $fillable = [
        'role',
        'permissions',
    ];

    protected $casts = [
        'permissions' => 'array',
    ];
}