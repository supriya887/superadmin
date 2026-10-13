<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadService extends Model
{
    protected $connection = 'pgsql_crm';

    protected $table = 'lead_services';

    protected $fillable = [
        'lead_id',
        'provider_id',
        'plan_id',
        'quote_period',
        'sku',
        'quantity',
        'rate',
        'yearly_rate',
        'renewal_date',
        'is_info',
        'domain',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'rate' => 'decimal:2',
        'renewal_date' => 'date',
        'is_info' => 'boolean',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function provider()
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function isCompetitorInfo(): bool
    {
        if (array_key_exists('is_info', $this->attributes) && $this->attributes['is_info'] !== null) {
            return (bool) $this->is_info;
        }

        return $this->renewal_date && empty($this->quote_period);
    }
}