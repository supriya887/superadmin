<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_auth';
    protected $table = 'notifications';

    protected $fillable = [
        'partner_id',
        'order_id',
        'main_cart_id',
        'title',
        'message',
        'is_read',
        'status',
        'created_at',
        'order_sub_id',
        'type',
    ];

    public $timestamps = false;

    protected $attributes = [
        'status' => 'active',
        'is_read' => false,
    ];

    protected $casts = [
        'partner_id' => 'integer',
        'order_id'   => 'integer',
        'main_cart_id' => 'integer',
        'is_read'    => 'boolean',
        'created_at' => 'datetime',
        'order_sub_id' => 'integer',
        'type' => 'string',
    ];

    /**
     * Get the partner that owns the notification.
     */
    public function partner()
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }
}