<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrmLeadPriority extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_crm';
    protected $table = 'crm_lead_priorities';

    protected $fillable = [
        'name',
        'description',
        'status',
    ];
}
