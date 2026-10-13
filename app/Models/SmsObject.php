<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsObject extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_auth'; // Adjust if needed.
    protected $table = 'sms_settings';
    protected $primaryKey = 'id';

    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = true; // since created_id and updated_id exist (timestamps)
    const CREATED_AT = 'created_id';
    const UPDATED_AT = 'updated_id';

    protected $fillable = [
        'provider',
        'base_url',
        'user',
        'password',
        'channel',
        'dlr',
        'flashsms',
        'route',
        'template_id',
        'api_key',
        'api_secret',
    ];

    protected $casts = [
        'id' => 'integer',
        'dlr' => 'string',
        'flashsms' => 'string',
        'route' => 'string',
        'template_id' => 'string',
        'api_key' => 'string',
        'api_secret' => 'string',
        'created_id' => 'datetime',
        'updated_id' => 'datetime',
    ];
}