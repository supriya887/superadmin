<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tokenize extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_auth'; // Use the auth partner DB connection

    protected $table = 'tokenize';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'tokenize_type',
        'tokenable_id',
        'name',
        'token',
        'abilities',
        'last_used_at',
        'created_at',
        'expires_at',
        'updated_at',
    ];

    protected $casts = [
        'tokenable_id' => 'integer',
        'last_used_at' => 'datetime',
        'created_at' => 'datetime',
        'expires_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}