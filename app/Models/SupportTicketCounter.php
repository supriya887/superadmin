<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicketCounter extends Model
{
    protected $connection = 'pgsql_support';
    protected $table = 'support_ticket_counters';

    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = 'year';
    protected $keyType = 'int';

    protected $fillable = [
        'year',
        'next_serial',
    ];
}
