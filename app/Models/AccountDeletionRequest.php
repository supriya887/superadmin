<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountDeletionRequest extends Model
{
    protected $connection = 'pgsql_auth';
    protected $table = 'account_deletion_requests';

    protected $fillable = [
        'partner_id',
        'reason',
        'status',
        'requested_at',
        'processed_at',
        'processed_by',
        'admin_note',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }
}
