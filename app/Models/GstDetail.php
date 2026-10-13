<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GstDetail extends Model
{
    protected $connection = 'pgsql_billing';
    use HasFactory;

    protected $table = 'gst_details';

    protected $fillable = [
        'gstin',
        'legal_name',
        'trade_name',
        'address',
        'state',
        'pincode',
        'gst_status',
        'last_updated',
        'constitution',
        'registration_date',
        'nature_of_business',
    ];

    protected $casts = [
        'nature_of_business' => 'array',
        'last_updated' => 'date',
        'registration_date' => 'date',
    ];
}
