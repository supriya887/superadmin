<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AccountMaster extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_billing';

    protected $fillable = [
        'name',
        'type',
        'refer_id',
        'current_balance',
    ];

    public function ledgers()
    {
        return $this->hasMany(Ledger::class);
    }

    public function syncCurrentBalance(): void
    {
        $balance = (float) $this->ledgers()
            ->selectRaw("COALESCE(SUM(CASE WHEN transaction_type IN ('Credit', 'LimitUpdate') THEN amount ELSE -amount END), 0) as bal")
            ->value('bal');

        $this->current_balance = $balance;
        $this->saveQuietly();
    }

    public static function findOrCreate(string $name, string $type, ?int $referId = null): self
    {
        return static::firstOrCreate(
            ['name' => $name, 'type' => $type, 'refer_id' => $referId],
            ['current_balance' => 0]
        );
    }
}
