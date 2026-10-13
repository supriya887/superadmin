<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoginHistory extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_auth';
    protected $table = 'login_history';

    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'login_time',
        'logout_time',
    ];

    public $timestamps = true;

    // Define the relationship to user (optional, but good practice)
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
