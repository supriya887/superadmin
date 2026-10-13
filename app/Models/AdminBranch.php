<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminBranch extends Model
{
    use HasFactory;
    
    protected $connection = 'pgsql_admin';

    protected $table = 'admin_branch';

    protected $fillable = [
        'id',
        'branch_name',
        'country',
        'state_id',
        'state_name',
        'city',
        'status',
    ];
}
