<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KeyServer extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_billing';
    protected $table = 'key_server';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'key_server',
    ];

    protected $casts = [
        'id' => 'integer',
        'key_server' => 'string',
    ];
}