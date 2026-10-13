<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrmIndustry extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_crm';
    protected $table = 'crm_industries';

    protected $fillable = [
        'name',
        'description',
        'status',
    ];
}
