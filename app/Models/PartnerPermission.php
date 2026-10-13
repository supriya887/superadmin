<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerPermission extends Model
{
    protected $connection = 'pgsql_auth';
    protected $table = 'partner_permissions';

    protected $fillable = [
        'partner_id',
        'partner_user_id',
        'module_id',
        'module_key',
        'module_name',
        'group_name',
        'can_view',
        'can_add',
        'can_edit',
        'can_delete',
    ];

    protected $casts = [
        'can_view' => 'boolean',
        'can_add' => 'boolean',
        'can_edit' => 'boolean',
        'can_delete' => 'boolean',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function partnerUser(): BelongsTo
    {
        return $this->belongsTo(PartnerUser::class, 'partner_user_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(PartnerModule::class, 'module_id');
    }
}
