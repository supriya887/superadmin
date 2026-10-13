<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtherState extends Model
{
    protected $table = 'other_state';
    protected $guarded = ['id'];
    protected $fillable = [
        'country_id',
        'state',
        'status'
    ];
    public $timestamps = false;
}
