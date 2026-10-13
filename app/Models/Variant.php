<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Variant extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_product';
    protected $table = 'variants';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'provider_id',
        'parent_variant_id',
        'name',
        'status',
        'sr_no',
    ];

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_variant_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_variant_id');
    }

    public function plans()
    {
        return $this->hasMany(Plan::class, 'variant_id');
    }
}
