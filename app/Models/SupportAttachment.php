<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportAttachment extends Model
{
    protected $connection = 'pgsql_support';
    protected $table = 'ticket_attachments';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ticket_id',
        'activity_id',
        'file_path',
        'filename',
        'file_size',
        'mime_type',
        'uploaded_by',
        'is_internal',
        'uploaded_by_type',
        'original_name',
        'disk',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'file_size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'is_internal' => 'boolean',
    ];

    /**
     * Get the support ticket associated with the attachment.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    /**
     * Get the support activity associated with the attachment.
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(SupportActivity::class, 'activity_id');
    }
}