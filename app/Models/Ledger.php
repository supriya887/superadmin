<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\AdminUserCreation;
use App\Models\Order;

class Ledger extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_billing';

    protected $table = 'ledgers';

    protected $fillable = [
        'account_master_id',
        'order_id',
        'admin_user_id',
        'transaction_type',
        'amount',
        'payment_mode',
        'reference_no',
        'description',
        'type',
    ];

    protected $casts = [
        'id'                 => 'integer',
        'account_master_id'  => 'integer',
        'order_id'           => 'integer',
        'admin_user_id'      => 'integer',
        'transaction_type'   => 'string', // ENUM: 'Debit', 'Credit', 'LunDebit', 'LunCredit'
        'amount'             => 'decimal:2',
        'payment_mode'       => 'string',
        'reference_no'       => 'string',
        'description'        => 'string',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    public $timestamps = true;

    /**
     * Get the account master that owns the ledger entry.
     */
    public function accountMaster(): BelongsTo
    {
        return $this->belongsTo(AccountMaster::class, 'account_master_id');
    }

    /**
     * Optionally, define relation to user/admin user.
     */
    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUserCreation::class, 'admin_user_id');
    }

    /**
     * Optionally, define relation to order if needed.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
