<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enquiry extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_auth';
    protected $table = 'enquiries';

    protected $fillable = [
        'partner_id',
        'message',
        'status',
    ];

    protected $attributes = [
        'status' => 'new',
    ];

    protected $casts = [
        'partner_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }
}
