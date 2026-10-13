<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocationMst extends Model
{
    protected $table = 'location_mst';
    protected $primaryKey = 'location_id';
    public $incrementing = false;
    protected $guarded = ['location_id'];
    protected $fillable = [
        'location_name',
        'location_type',
        'parent_id',
        'branch_no',
        'state_code'
    ];
    public $timestamps = false;
}
