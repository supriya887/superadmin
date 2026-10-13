<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Provider extends Model
{
    protected $connection = 'pgsql_product';
    protected $table = 'providers';
    
    protected $fillable = [
        'name',
        'slug',
        'description',
        'logo',
        'type',
        'website_url',
        'api_base_url',
        'auth_type',
        'config_json',
        'status',
        'has_variant',
        'transfer_type',
        'transfer_detail',
    ];

    protected $casts = [
        'config_json' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($provider) {
            if (empty($provider->slug)) {
                $provider->slug = Str::slug($provider->name);
            }
        });
    }

    public function variants()
    {
        return $this->hasMany(Variant::class);
    }

    public function getLogoUrlAttribute(): ?string
    {
        $logo = trim((string) ($this->attributes['logo'] ?? ''));
        if ($logo === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $logo)) {
            return (string) preg_replace('#(/storage)/app/public/#', '$1/', $logo);
        }

        if (str_starts_with($logo, 'data:image')) {
            return $logo;
        }

        $relative = ltrim(str_replace('\\', '/', $logo), '/');
        if (str_starts_with($relative, 'storage/')) {
            $relative = ltrim(substr($relative, strlen('storage/')), '/');
        }
        $relative = (string) preg_replace('#^(app/public/|public/)+#', '', $relative);
        if ($relative === '') {
            return null;
        }

        return asset('storage/' . $relative);
    }
}