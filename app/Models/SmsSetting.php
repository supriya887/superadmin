<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsSetting extends Model
{
    protected $table = 'sms_settings';

    protected $fillable = [
        'provider',
        'base_url',
        'user',
        'password',
        'senderid',
        'channel',
        'dcs',
        'flashsms',
        'route',
        'peid',
        'template_id',
        'is_active',
    ];
}
