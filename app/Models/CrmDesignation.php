<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrmDesignation extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_crm';
    protected $table = 'crm_designations';

    protected $fillable = [
        'name',
        'department',
        'employee_count',
        'description',
        'status',
    ];
}
