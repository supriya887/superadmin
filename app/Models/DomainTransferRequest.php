<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DomainTransferRequest extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_billing';
    protected $table = 'domain_transfer_requests';

    protected $fillable = [
        'domain_name',
        'transfer_code',
        'requested_by_type',
        'requested_by_id',
        'status',
    ];

    protected $casts = [
        'id' => 'integer',
        'domain_name' => 'string',
        'transfer_code' => 'string',
        'requested_by_type' => 'string',
        'requested_by_id' => 'integer',
        'status' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public $timestamps = true;

    /**
     * Scope a query to only include records for a specific status.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}