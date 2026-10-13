<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_product';
    protected $table = 'plans';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'sr_no',
        'provider_id',
        'category_id',
        'program_id',
        'variant_id',
        'plan_type',
        'title',
        'name',
        'slug',
        'storage',
        'price',
        'features',
        'customer_limit',
        'actual_price',
        'billing_cycle',
        'description',
        'limited_offer',
        'is_active',
        'status',
        'subscription_type',
        'discount_type',
        'discount_percentage',
        'microsoft_sku_id',
        'microsoft_product_id',
    ];

    public function category()
    {
        return $this->belongsTo(PlanCategory::class, 'category_id');
    }

    public function variant()
    {
        return $this->belongsTo(Variant::class, 'variant_id');
    }

    public function provider()
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    public function apps()
    {
        return $this->belongsToMany(AppService::class, 'plan_app', 'plan_id', 'app_id');
    }

    public function getFeaturesArrayAttribute()
    {
        return $this->features ? explode('|', $this->features) : [];
    }

    // Keep the old relationship if needed, or if it can coexist
    public function planFeatures()
    {
        return $this->hasMany(PlanFeature::class);
    }

    /**
     * Final selling price from actual price and Microsoft discount rules.
     */
    public function getDiscountedPrice(): float
    {
        $actualPrice = (float) ($this->actual_price ?? 0);

        if (($this->subscription_type ?? '') === 'perpetual') {
            return $actualPrice;
        }

        if (($this->subscription_type ?? '') === 'software'
            && ($this->discount_type ?? '') === 'rebated') {
            $discountPercent = (float) ($this->discount_percentage ?? 0);
            if ($discountPercent > 0) {
                return round($actualPrice * (1 - $discountPercent / 100), 2);
            }
        }

        return $actualPrice > 0 ? $actualPrice : (float) ($this->price ?? 0);
    }
}
