<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrmLeadBifurcation extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_crm';
    protected $table = 'crm_lead_bifurcations';

    protected $fillable = [
        'name',
        'description',
        'status',
    ];
}
