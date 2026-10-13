<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadContact extends Model
{
    protected $connection = 'pgsql_crm';

    protected $table = 'lead_contacts';

    protected $fillable = [
        'lead_id',
        'name',
        'designation',
        'email',
        'mobile',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }
}