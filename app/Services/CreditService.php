<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class CreditService
{
    /**
     * Add prepaid funds to the partner wallet (Partner account_master) and sync partners.wallet_balance.
     *
     * @throws \InvalidArgumentException
     */
    public static function addWalletTopUp(int $partnerId, float $amount, ?string $note, ?int $adminUserId = null): void
    {
        if (!Schema::hasTable('partners')) {
            throw new \RuntimeException('Partners table is not available.');
        }
        if (!Schema::hasTable('ledgers') || !Schema::hasTable('account_masters')) {
            throw new \RuntimeException('Ledger tables are not available.');
        }

        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero.');
        }

        $note = trim((string) $note);
        if ($note === '') {
            $note = 'Wallet credit top-up';
        }

        PartnerCreditService::insertPartnerWalletCredit(
            $partnerId,
            $amount,
            $note,
            $adminUserId,
            PartnerCreditService::WALLET_TOPUP_PAYMENT_MODE,
            'WT'
        );
    }

    /**
     * @return Collection<int, object>
     */
    public static function listManualCreditAllocations(int $partnerId): Collection
    {
        return PartnerCreditService::listPartnerWalletCredits($partnerId);
    }
}
