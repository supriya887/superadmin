<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model representing a single row in the `promocodes` table.
 * 
 * @property int $id
 * @property string $code
 * @property \Illuminate\Support\Carbon|null $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property float $discount_percent
 * @property bool $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Promocode extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_billing';
    protected $table = 'promocodes';

    // Fillable fields for mass assignment
    protected $fillable = [
        'code',
        'start_date',
        'end_date',
        'discount_percent',
        'status',
    ];

    // Cast fields to native types
    protected $casts = [
        'id'               => 'integer',
        'code'             => 'string',
        'start_date'       => 'date',
        'end_date'         => 'date',
        'discount_percent' => 'decimal:2',
        'status'           => 'boolean',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    /**
     * Scope a query to only include active promocodes.
     * Status may be boolean or integer (0/1) depending on DB cast.
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->where('status', true)
                ->orWhere('status', 1)
                ->orWhere('status', '1');
        });
    }

    /**
     * Active and within start/end date window (when dates are set).
     */
    public function scopeValidNow($query)
    {
        $today = now()->toDateString();

        return $query->active()
            ->where(function ($q) use ($today) {
                $q->whereNull('start_date')->orWhereDate('start_date', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
            });
    }
}
