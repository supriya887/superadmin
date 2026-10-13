<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketAssignment extends Model
{
    protected $connection = 'pgsql_support';
    protected $table = 'support_ticket_assignments';

    protected $fillable = [
        'ticket_id',
        'assigned_from',
        'assigned_to',
        'team_id',
        'changed_by',
        'changed_by_type',
        'note',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }
}
