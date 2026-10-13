<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrmLeadSource extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_crm';

    protected $table = 'crm_lead_sources';

    protected $fillable = [
        'name',
        'logo',
        'status',
    ];

    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo) {
            return null;
        }

        if (str_starts_with($this->logo, 'http://') || str_starts_with($this->logo, 'https://')) {
            return $this->logo;
        }

        return asset('storage/' . ltrim($this->logo, '/'));
    }
}
