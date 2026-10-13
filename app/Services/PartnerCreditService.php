<?php

namespace App\Services;

use App\Models\AccountMaster;
use App\Models\Ledger;
use App\Models\Order;
use App\Models\Partner as PartnerRecord;
use App\Models\Partner\Partner;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;


class PartnerCreditService
{
    public const AUDIT_TYPE = 'PartnerCreditLine';
    /**
     * Order statuses that consume credit allocation.
     * `paid` is intentionally excluded — once the invoice is paid, credit is restored.
     */
    private const SUCCESSFUL_ORDER_STATUSES = ['completed', 'success', 'active'];

    /** Invoice statuses that settle the order and release credit back to the partner. */
    private const PAID_INVOICE_STATUSES = ['paid', 'success', 'completed', 'succeeded'];

    /** Marks credit-line audit rows that should appear in allocation history (manual + approval). */
    public const CREDIT_LINE_LEDGER_PAYMENT_MODE = 'limit_update';

    /** Partner wallet (khata) credits added by admin after approval. */
    public const WALLET_TOPUP_PAYMENT_MODE = 'wallet_topup';

    /** Initial prepaid wallet credit captured at partner approval. */
    public const WALLET_INITIAL_APPROVAL_PAYMENT_MODE = 'wallet_initial_approval';

    /** Partner wallet debit when a B2B order is activated / completed. */
    public const WALLET_ORDER_DEBIT_PAYMENT_MODE = 'order_wallet_debit';

    /** Prorated cancellation credit note credited to partner wallet. */
    public const WALLET_CANCEL_CREDIT_NOTE_PAYMENT_MODE = 'order_cancel_credit_note';

    private static function billingConnection(): string
    {
        static $connection;

        return $connection ??= (new Order())->getConnectionName() ?: 'pgsql_billing';
    }

    private static function authConnection(): string
    {
        static $connection;

        return $connection ??= (new PartnerRecord())->getConnectionName() ?: 'pgsql_auth';
    }

    private static function billingSchema(): \Illuminate\Database\Schema\Builder
    {
        return Schema::connection(self::billingConnection());
    }

    private static function authSchema(): \Illuminate\Database\Schema\Builder
    {
        return Schema::connection(self::authConnection());
    }

    private static function billingDb(): \Illuminate\Database\Connection
    {
        return DB::connection(self::billingConnection());
    }

    private static function authDb(): \Illuminate\Database\Connection
    {
        return DB::connection(self::authConnection());
    }

    private static function ordersTableExists(): bool
    {
        return self::billingSchema()->hasTable('orders');
    }

    private static function ordersHasColumn(string $column): bool
    {
        return self::ordersTableExists() && self::billingSchema()->hasColumn('orders', $column);
    }

    private static function ledgersTableExists(): bool
    {
        return self::billingSchema()->hasTable('ledgers')
            && self::billingSchema()->hasTable('account_masters');
    }

    private static function ledgersHasColumn(string $column): bool
    {
        return self::billingSchema()->hasTable('ledgers')
            && self::billingSchema()->hasColumn('ledgers', $column);
    }

    private static function partnersHasColumn(string $column): bool
    {
        return self::authSchema()->hasTable('partners')
            && self::authSchema()->hasColumn('partners', $column);
    }

    private static function billingPartnersHasColumn(string $column): bool
    {
        return self::billingSchema()->hasTable('partners')
            && self::billingSchema()->hasColumn('partners', $column);
    }

    private static function applySuccessfulStatusFilter($query): void
    {
        if (!self::ordersHasColumn('status')) {
            return;
        }

        $query->whereRaw(
            "LOWER(TRIM(COALESCE(status, ''))) IN (?, ?, ?)",
            self::SUCCESSFUL_ORDER_STATUSES,
        );
    }

    /**
     * Orders whose invoice is already paid no longer consume credit allocation.
     */
    private static function excludeOrdersWithPaidInvoice($query): void
    {
        if (!self::billingSchema()->hasTable('invoice_mst')
            || !self::billingSchema()->hasColumn('invoice_mst', 'order_id')
            || !self::billingSchema()->hasColumn('invoice_mst', 'status')
        ) {
            return;
        }

        $paid = self::PAID_INVOICE_STATUSES;
        $placeholders = implode(', ', array_fill(0, count($paid), '?'));

        $query->whereNotExists(function ($q) use ($paid, $placeholders) {
            $q->selectRaw('1')
                ->from('invoice_mst')
                ->whereColumn('invoice_mst.order_id', 'orders.id')
                ->whereRaw(
                    "LOWER(TRIM(COALESCE(invoice_mst.status, ''))) IN ({$placeholders})",
                    $paid
                );
        });
    }

    private static function walletLedgerBalance(AccountMaster $account): float
    {
        $account->syncCurrentBalance();
        $account->refresh();

        return (float) ($account->current_balance ?? 0);
    }

    public static function partnerWalletSnapshot(int $partnerId): float
    {
        if ($partnerId <= 0 || !self::partnersHasColumn('wallet_balance')) {
            return 0.0;
        }

        return (float) self::authDb()->table('partners')->where('id', $partnerId)->value('wallet_balance');
    }

    private static function updatePartnerWalletSnapshot(int $partnerId, float $balance): void
    {
        if ($partnerId <= 0) {
            return;
        }

        $balance = max(0.0, round($balance, 2));
        $updates = [];
        if (self::partnersHasColumn('wallet_balance')) {
            $updates['wallet_balance'] = $balance;
        }
        if (self::partnersHasColumn('current_balance')) {
            $updates['current_balance'] = $balance;
        }
        if ($updates !== []) {
            PartnerRecord::query()->whereKey($partnerId)->update($updates);
        }

        // Some deployments also keep a billing-side partners table used by admin reports.
        $billingUpdates = [];
        if (self::billingPartnersHasColumn('wallet_balance')) {
            $billingUpdates['wallet_balance'] = $balance;
        }
        if (self::billingPartnersHasColumn('current_balance')) {
            $billingUpdates['current_balance'] = $balance;
        }
        if ($billingUpdates !== []) {
            self::billingDb()->table('partners')->where('id', $partnerId)->update($billingUpdates);
        }
    }

    private static function reconcileWalletAccountFromPartnerSnapshot(int $partnerId, AccountMaster $partnerAccount): void
    {
        if ($partnerId <= 0 || !self::partnersHasColumn('wallet_balance')) {
            return;
        }

        $snapshotBalance = self::partnerWalletSnapshot($partnerId);
        $ledgerBalance = self::walletLedgerBalance($partnerAccount);
        $delta = round($snapshotBalance - $ledgerBalance, 2);
        if (abs($delta) <= 0.005) {
            return;
        }

        $payload = [
            'account_master_id' => $partnerAccount->id,
            'order_id' => null,
            'transaction_type' => $delta > 0 ? 'Credit' : 'Debit',
            'amount' => abs($delta),
            'description' => 'Wallet opening balance sync from partner snapshot',
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (self::ledgersHasColumn('type')) {
            $payload['type'] = $delta > 0 ? 'credit' : 'debit';
        }
        if (self::ledgersHasColumn('payment_mode')) {
            $payload['payment_mode'] = 'wallet_snapshot_sync';
        }
        if (self::ledgersHasColumn('reference_no')) {
            $payload['reference_no'] = 'WS-' . $partnerId . '-' . uniqid('', true);
        }

        self::billingDb()->table('ledgers')->insert($payload);
        $partnerAccount->syncCurrentBalance();
        $partnerAccount->refresh();
    }

    public static function partnerWalletAccountName(int $partnerId): string
    {
        return 'Partner #' . $partnerId . ' Wallet';
    }

    /**
     * Mirror partners.wallet_balance (and optional current_balance) from the Partner wallet account.
     */
    public static function syncPartnerWalletSnapshotFromAccount(int $partnerId, AccountMaster $partnerAccount): void
    {
        if ($partnerId <= 0) {
            return;
        }

        self::updatePartnerWalletSnapshot($partnerId, self::walletLedgerBalance($partnerAccount));
    }

    /**
     * Debit the partner prepaid wallet for a B2B order (idempotent per order).
     */
    public static function insertPartnerWalletDebit(
        int $partnerId,
        float $amount,
        string $description,
        ?int $orderId = null,
        ?int $adminUserId = null,
        string $paymentMode = self::WALLET_ORDER_DEBIT_PAYMENT_MODE,
        string $referencePrefix = 'WD'
    ): void {
        $amount = round($amount, 2);
        if ($amount <= 0.005) {
            return;
        }

        $wallet = AccountMaster::findOrCreate(self::partnerWalletAccountName($partnerId), 'Partner', $partnerId);
        $wallet->refresh();

        $payload = [
            'account_master_id' => $wallet->id,
            'order_id' => ($orderId !== null && $orderId > 0) ? $orderId : null,
            'admin_user_id' => $adminUserId,
            'transaction_type' => 'Debit',
            'amount' => $amount,
            'description' => $description,
            'type' => 'debit',
            'payment_mode' => $paymentMode,
            'reference_no' => $referencePrefix . '-' . $partnerId . '-' . ($orderId ?? 'x') . '-' . uniqid('', true),
        ];
        Ledger::create($payload);
        $wallet->syncCurrentBalance();
        self::syncPartnerWalletSnapshotFromAccount($partnerId, $wallet);
    }

    /**
     * Whether this order already posted a wallet debit (prevents double deduction).
     */
    public static function hasOrderWalletDebit(int $orderId): bool
    {
        $orderId = (int) $orderId;
        if ($orderId <= 0 || !self::ledgersTableExists()) {
            return false;
        }

        $q = self::billingDb()->table('ledgers as l')
            ->join('account_masters as am', 'am.id', '=', 'l.account_master_id')
            ->where('l.order_id', $orderId)
            ->where('am.type', 'Partner')
            ->where('am.name', 'like', 'Partner #% Wallet');

        if (self::ledgersHasColumn('payment_mode')) {
            $q->where(function ($inner) {
                $inner->where('l.payment_mode', self::WALLET_ORDER_DEBIT_PAYMENT_MODE)
                    ->orWhere(function ($d) {
                        $d->where(function ($t) {
                            $t->where('l.transaction_type', 'Debit')
                                ->orWhere('l.type', 'debit');
                        })->where(function ($pm) {
                            $pm->whereNull('l.payment_mode')
                                ->orWhereNotIn('l.payment_mode', [
                                    self::WALLET_CANCEL_CREDIT_NOTE_PAYMENT_MODE,
                                    self::WALLET_TOPUP_PAYMENT_MODE,
                                    self::WALLET_INITIAL_APPROVAL_PAYMENT_MODE,
                                ]);
                        });
                    });
            });
        } else {
            $q->where(function ($t) {
                $t->where('l.transaction_type', 'Debit')
                    ->orWhere('l.type', 'debit');
            });
        }

        return $q->exists();
    }

    /**
     * Backfill wallet debits — retired under single credit-allocation model (no prepaid wallet spend).
     */
    public static function reconcilePartnerCompletedOrderWallets(int $partnerId): int
    {
        return 0;
    }

    /**
     * Credit the partner’s prepaid wallet (Partner account) and refresh snapshot columns.
     */
    public static function insertPartnerWalletCredit(
        int $partnerId,
        float $amount,
        string $description,
        ?int $adminUserId,
        string $paymentMode,
        string $referencePrefix = 'W',
        ?int $orderId = null
    ): void {
        $amount = round($amount, 2);
        if ($amount <= 0.005) {
            return;
        }

        $wallet = AccountMaster::findOrCreate(self::partnerWalletAccountName($partnerId), 'Partner', $partnerId);
        $wallet->refresh();

        $payload = [
            'account_master_id' => $wallet->id,
            'order_id' => ($orderId !== null && $orderId > 0) ? $orderId : null,
            'admin_user_id' => $adminUserId,
            'transaction_type' => 'Credit',
            'amount' => $amount,
            'description' => $description,
            'type' => 'credit',
            'payment_mode' => $paymentMode,
            'reference_no' => $referencePrefix . '-' . $partnerId . '-' . ($adminUserId ?? 'x') . '-' . uniqid('', true),
        ];
        Ledger::create($payload);
        $wallet->syncCurrentBalance();
        self::syncPartnerWalletSnapshotFromAccount($partnerId, $wallet);
    }

    /**
     * Whether a cancellation credit-note amount was already posted to the partner wallet for this order.
     */
    public static function hasCancellationCreditNoteWalletCredit(int $orderId): bool
    {
        $orderId = (int) $orderId;
        if ($orderId <= 0 || !self::ledgersTableExists()) {
            return false;
        }

        $q = self::billingDb()->table('ledgers')->where('order_id', $orderId);
        if (self::ledgersHasColumn('payment_mode')) {
            $q->where('payment_mode', self::WALLET_CANCEL_CREDIT_NOTE_PAYMENT_MODE);
        } else {
            $q->where('description', 'like', '%credit note%');
        }

        return $q->exists();
    }

    /**
     * Credit partner wallet from an order-cancellation credit note (idempotent per order).
     *
     * @return array{ok: bool, credited: bool, amount: float, message: string}
     */
    public static function creditWalletFromCancellationCreditNote(
        int $partnerId,
        int $orderId,
        float $amount,
        string $creditNoteNo = '',
        ?int $creditNoteId = null
    ): array {
        $partnerId = (int) $partnerId;
        $orderId = (int) $orderId;
        $amount = round((float) $amount, 2);

        if ($partnerId <= 0) {
            return ['ok' => false, 'credited' => false, 'amount' => 0.0, 'message' => 'Invalid partner id.'];
        }
        if ($amount <= 0.005) {
            return ['ok' => false, 'credited' => false, 'amount' => 0.0, 'message' => 'Nothing to credit.'];
        }
        if ($orderId > 0 && self::hasCancellationCreditNoteWalletCredit($orderId)) {
            return ['ok' => true, 'credited' => false, 'amount' => $amount, 'message' => 'Already credited to wallet.'];
        }

        $cnLabel = trim($creditNoteNo) !== ''
            ? trim($creditNoteNo)
            : ($creditNoteId ? ('CN#' . $creditNoteId) : 'Credit note');
        $desc = sprintf(
            'Order cancellation credit note %s credited to wallet (order #%d)',
            $cnLabel,
            $orderId > 0 ? $orderId : 0
        );

        try {
            self::billingDb()->transaction(function () use ($partnerId, $amount, $desc, $orderId, $creditNoteId) {
                self::insertPartnerWalletCredit(
                    $partnerId,
                    $amount,
                    $desc,
                    null,
                    self::WALLET_CANCEL_CREDIT_NOTE_PAYMENT_MODE,
                    $creditNoteId ? ('CN' . $creditNoteId) : 'CN',
                    $orderId > 0 ? $orderId : null
                );
            });
        } catch (\Throwable $e) {
            Log::error('credit_wallet_from_cancellation_credit_note_failed', [
                'partner_id' => $partnerId,
                'order_id' => $orderId,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'credited' => false, 'amount' => $amount, 'message' => $e->getMessage()];
        }

        return ['ok' => true, 'credited' => true, 'amount' => $amount, 'message' => 'Credited to partner wallet.'];
    }

    /**
     * @return Collection<int, object>
     */
    public static function listPartnerWalletCredits(int $partnerId): Collection
    {
        $wallet = AccountMaster::query()
            ->where('refer_id', $partnerId)
            ->where('type', 'Partner')
            ->where('name', self::partnerWalletAccountName($partnerId))
            ->first();

        if (!$wallet) {
            return collect();
        }

        $rows = Ledger::query()
            ->where('account_master_id', $wallet->id)
            ->where('transaction_type', 'Credit')
            ->whereIn('payment_mode', [
                self::WALLET_TOPUP_PAYMENT_MODE,
                self::WALLET_INITIAL_APPROVAL_PAYMENT_MODE,
                self::WALLET_CANCEL_CREDIT_NOTE_PAYMENT_MODE,
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'created_at', 'amount', 'description', 'admin_user_id', 'payment_mode', 'order_id']);

        $adminIds = $rows->pluck('admin_user_id')->filter()->unique()->values();
        $adminNames = collect();
        if ($adminIds->isNotEmpty()) {
            $adminNames = DB::connection('pgsql_admin')
                ->table('admin_user_creation')
                ->whereIn('id', $adminIds)
                ->pluck('user_name', 'id');
        }

        return $rows->map(function ($row) use ($adminNames) {
            $adminName = null;
            if ($row->admin_user_id) {
                $name = $adminNames->get($row->admin_user_id);
                $adminName = ($name && $name !== '') ? $name : ('Admin User #' . $row->admin_user_id);
            }

            return (object) [
                'id' => $row->id,
                'created_at' => $row->created_at,
                'amount' => $row->amount,
                'description' => $row->description,
                'admin_user_id' => $row->admin_user_id,
                'admin_name' => $adminName,
                'payment_mode' => $row->payment_mode ?? null,
                'order_id' => isset($row->order_id) ? (int) $row->order_id : null,
            ];
        });
    }

    public static function extractB2bOrderAmountFromPayload(array $payload): float
    {
        if (isset($payload['total_amount']) && is_numeric($payload['total_amount'])) {
            return max(0, round((float) $payload['total_amount'], 2));
        }

        if (isset($payload['credit_used']) && is_numeric($payload['credit_used'])) {
            return max(0, round((float) $payload['credit_used'], 2));
        }

        if (isset($payload['subtotal']) && is_numeric($payload['subtotal'])) {
            $subtotal = max(0, round((float) $payload['subtotal'], 2));
            $tax = 0.0;
            if (isset($payload['gst_amount']) && is_numeric($payload['gst_amount'])) {
                $tax = max(0, round((float) $payload['gst_amount'], 2));
            } elseif (isset($payload['tax_amount']) && is_numeric($payload['tax_amount'])) {
                $tax = max(0, round((float) $payload['tax_amount'], 2));
            }
            if ($subtotal > 0 || $tax > 0) {
                return round($subtotal + $tax, 2);
            }
        }

        return 0.0;
    }

    public static function cleanupB2bOrderWalletLedgersForOrder(int $orderId): void
    {
        if (!self::ledgersTableExists()) {
            return;
        }
        $affected = self::billingDb()->table('ledgers as l')
            ->join('account_masters as am', 'am.id', '=', 'l.account_master_id')
            ->where('l.order_id', $orderId)
            ->where('am.type', 'Partner')
            ->pluck('l.account_master_id')
            ->unique()
            ->values()
            ->all();

        if (!empty($affected)) {
            self::billingDb()->table('ledgers')
                ->whereIn('id', function ($q) use ($orderId) {
                    $q->from('ledgers as l')
                        ->select('l.id')
                        ->join('account_masters as am', 'am.id', '=', 'l.account_master_id')
                        ->where('l.order_id', $orderId)
                        ->where('am.type', 'Partner');
                })
                ->delete();
        }
    }

    /**
     * B2B order wallet: deduct up to available wallet; remainder is post-paid against partners.credit_limit.
     * Idempotent per order via ledger payment_mode=order_wallet_debit.
     */
    public static function syncB2bOrderWalletLedger(int $orderId, array $orderPayload): void
    {
        // Normalize missing payload from DB so activation calls are resilient.
        $dbOrder = null;
        if (empty($orderPayload['order_type']) || empty($orderPayload['status']) || empty($orderPayload['partner_id'])) {
            $dbOrder = Order::query()->whereKey($orderId)->first();
            if ($dbOrder) {
                if (empty($orderPayload['order_type'])) {
                    $orderPayload['order_type'] = (string) ($dbOrder->order_type ?? '');
                }
                if (empty($orderPayload['status'])) {
                    $orderPayload['status'] = (string) ($dbOrder->status ?? '');
                }
                if (empty($orderPayload['partner_id'])) {
                    $orderPayload['partner_id'] = (int) ($dbOrder->partner_id ?? 0);
                }
                foreach (['credit_used', 'total_amount', 'subtotal', 'gst_amount', 'tax_amount', 'price'] as $k) {
                    if (!isset($orderPayload[$k])) {
                        $orderPayload[$k] = $dbOrder->{$k} ?? null;
                    }
                }
            }
        }

        if (!empty($orderPayload['is_legacy']) || (int) ($orderPayload['is_legacy'] ?? 0) === 1) {
            return;
        }

        $orderType = strtolower(trim((string) ($orderPayload['order_type'] ?? '')));
        if ($orderType !== 'b2b') {
            return;
        }

        $status = strtolower(trim((string) ($orderPayload['status'] ?? '')));
        if (!in_array($status, ['completed', 'active'], true)) {
            // Do not delete existing wallet debits when called with pending/processing.
            return;
        }

        $partnerId = (int) ($orderPayload['partner_id'] ?? 0);
        if ($partnerId <= 0) {
            throw ValidationException::withMessages([
                'partner_id' => 'Partner is required for B2B wallet transactions.',
            ]);
        }

        // Already deducted for this order — keep snapshot aligned with ledger if present.
        if (self::hasOrderWalletDebit($orderId)) {
            if (self::ledgersTableExists()) {
                $wallet = AccountMaster::query()
                    ->where('refer_id', $partnerId)
                    ->where('type', 'Partner')
                    ->where('name', self::partnerWalletAccountName($partnerId))
                    ->first();
                if ($wallet) {
                    $wallet->syncCurrentBalance();
                    self::syncPartnerWalletSnapshotFromAccount($partnerId, $wallet);
                }
            }
            return;
        }

        $walletDebit = self::extractB2bOrderAmountFromPayload($orderPayload);
        $orderTotal = 0.0;
        foreach (['total_amount', 'subtotal', 'price'] as $amountKey) {
            if (isset($orderPayload[$amountKey]) && is_numeric($orderPayload[$amountKey])) {
                $orderTotal = max(0, round((float) $orderPayload[$amountKey], 2));
                break;
            }
        }
        if ($walletDebit <= 0 && $orderTotal <= 0) {
            $row = $dbOrder ?: Order::query()->whereKey($orderId)->first([
                'credit_used',
                'total_amount',
                'subtotal',
                'gst_amount',
                'tax_amount',
                'price',
            ]);
            if ($row) {
                $walletDebit = max(0.0, round((float) ($row->credit_used ?? 0), 2));
                $orderTotal = max(0.0, round((float) ($row->total_amount ?? 0), 2));
                if ($orderTotal <= 0) {
                    $orderTotal = max(
                        0.0,
                        round((float) ($row->subtotal ?? 0) + (float) ($row->gst_amount ?? $row->tax_amount ?? 0), 2)
                    );
                }
            }
        }
        if ($walletDebit <= 0 && $orderTotal <= 0) {
            return;
        }

        $limit = PHP_FLOAT_MAX;
        if (self::partnersHasColumn('credit_limit')) {
            $limit = (float) self::authDb()->table('partners')->where('id', $partnerId)->value('credit_limit');
        }
        $used = self::ordersHasColumn('partner_id') ? self::summarizeUsedCredit($partnerId, $orderId) : 0.0;

        // Single credit-allocation model: available = credit_limit − credit_used.
        // Do not debit wallet_balance on activate; invoice payment is real money later.
        $postRoom = max(0.0, round($limit - $used, 2));
        $coverageTotal = $orderTotal > 0 ? $orderTotal : $walletDebit;
        $skipHeadroomCheck = !empty($orderPayload['skip_credit_headroom_check']);
        if (!$skipHeadroomCheck && $limit < PHP_FLOAT_MAX && $coverageTotal > $postRoom + 0.01) {
            throw ValidationException::withMessages([
                'amount' => self::CREDIT_LIMIT_REACHED_MESSAGE,
            ]);
        }
    }

    /**
 * Refresh partner wallet balance from the ledger and return the current balance
 */
public static function refreshPartnerWalletBalance(int $partnerId): float
{
    if ($partnerId <= 0) {
        return 0.0;
    }
    
    try {
        // Reconcile any missing wallet debits first
        self::reconcilePartnerCompletedOrderWallets($partnerId);
        
        // Get or create the wallet account
        $wallet = AccountMaster::findOrCreate(
            self::partnerWalletAccountName($partnerId),
            'Partner',
            $partnerId
        );
        
        // Sync balance from ledger
        $wallet->syncCurrentBalance();
        
        // Push to partner record
        self::syncPartnerWalletSnapshotFromAccount($partnerId, $wallet);
        
        // Return the refreshed balance
        return self::partnerWalletSnapshot($partnerId);
        
    } catch (\Throwable $e) {
        Log::warning('refreshPartnerWalletBalance_failed', [
            'partner_id' => $partnerId,
            'error' => $e->getMessage(),
        ]);
        
        // Fallback to direct partner snapshot
        return self::partnerWalletSnapshot($partnerId);
    }
}
    public static function auditAccountName(int $partnerId): string
    {
        return 'Partner #' . $partnerId . ' Credit Line (Audit)';
    }

    public static function findOrCreateAuditAccount(int $partnerId): AccountMaster
    {
        return AccountMaster::findOrCreate(
            self::auditAccountName($partnerId),
            self::AUDIT_TYPE,
            $partnerId
        );
    }

    public static function summarizeUsedCredit(int $partnerId, ?int $excludeOrderId = null): float
    {
        if (!self::ordersHasColumn('partner_id')) {
            return 0.0;
        }

        $query = Order::query()->where('partner_id', $partnerId);
        if (self::ordersHasColumn('order_type')) {
            $query->where(function ($q) {
                $q->where('order_type', 'b2b')->orWhere('order_type', 'B2B');
            });
        }
        if ($excludeOrderId !== null) {
            $query->where('id', '!=', $excludeOrderId);
        }
        self::applySuccessfulStatusFilter($query);
        // Paid invoice → credit returns to partner available balance.
        self::excludeOrdersWithPaidInvoice($query);

        if (self::ordersHasColumn('credit_used')) {
            $query->where('credit_used', '>', 0);
            return max(0.0, round((float) $query->sum('credit_used'), 2));
        }
        if (self::ordersHasColumn('subtotal')) {
            $query->where('subtotal', '>', 0);
            return max(0.0, round((float) $query->sum('subtotal'), 2));
        }

        return 0.0;
    }

    /**
     * After an invoice is marked paid: release that order's credit back to the partner.
     * Available credit = credit_limit − summarizeUsedCredit (paid invoices excluded).
     *
     * @return array{ok:bool,released:bool,partner_id:int,order_id:int,amount:float,credit_used:float,remaining_credit_limit:float,message:string}
     */
    public static function releaseCreditForPaidInvoice(int $invoiceId, ?int $orderId = null): array
    {
        $empty = [
            'ok' => false,
            'released' => false,
            'partner_id' => 0,
            'order_id' => 0,
            'amount' => 0.0,
            'credit_used' => 0.0,
            'remaining_credit_limit' => 0.0,
            'message' => 'Nothing to release.',
        ];

        $invoice = null;
        if ($invoiceId > 0 && self::billingSchema()->hasTable('invoice_mst')) {
            $invoice = self::billingDb()->table('invoice_mst')->where('id', $invoiceId)->first();
        }

        if ($orderId === null || $orderId <= 0) {
            $orderId = (int) ($invoice->order_id ?? 0);
        }
        if ($orderId <= 0) {
            return array_merge($empty, ['message' => 'Invoice has no linked order.']);
        }

        $order = Order::query()->whereKey($orderId)->first();
        if (!$order) {
            return array_merge($empty, ['message' => 'Order not found.', 'order_id' => $orderId]);
        }

        $partnerId = (int) ($order->partner_id ?? $invoice->partner_id ?? 0);
        $amount = round((float) ($order->credit_used ?? 0), 2);
        if ($amount <= 0) {
            $amount = round((float) (
                $invoice->total_amount
                ?? $invoice->net_amt
                ?? $invoice->amount
                ?? $order->total_amount
                ?? 0
            ), 2);
        }

        // Order.status = paid → no longer counted in summarizeUsedCredit.
        if (self::ordersHasColumn('status')) {
            $current = strtolower(trim((string) ($order->status ?? '')));
            if (!in_array($current, ['cancelled', 'canceled', 'rejected', 'failed', 'void'], true)) {
                $order->status = 'paid';
                $order->save();
            }
        }

        $used = self::summarizeUsedCredit($partnerId);
        $remaining = self::remainingPurchaseAllowance($partnerId);

        Log::info('partner_credit_released_on_invoice_paid', [
            'partner_id' => $partnerId,
            'order_id' => $orderId,
            'invoice_id' => $invoiceId,
            'released_amount' => $amount,
            'credit_used_now' => $used,
            'remaining_credit_limit' => $remaining,
        ]);

        return [
            'ok' => true,
            'released' => $amount > 0,
            'partner_id' => $partnerId,
            'order_id' => $orderId,
            'amount' => $amount,
            'credit_used' => $used,
            'remaining_credit_limit' => $remaining,
            'message' => $amount > 0
                ? 'Credit restored to partner after invoice payment.'
                : 'Invoice paid; no credit_used on order to restore.',
        ];
    }

    public static function availableCredit(int $partnerId, ?int $excludeOrderId = null): float
    {
        $limit = 0.0;
        if (self::partnersHasColumn('credit_limit')) {
            $limit = (float) self::authDb()->table('partners')->where('id', $partnerId)->value('credit_limit');
        }
        $used = self::summarizeUsedCredit($partnerId, $excludeOrderId);

        return max(0.0, round($limit - $used, 2));
    }

    public const CREDIT_LIMIT_REACHED_MESSAGE = 'Credit used limit reached. You cannot purchase now.';

    /**
     * True when B2B credit_used has reached or exceeded partners.credit_limit.
     */
    public static function isCreditLimitFullyUsed(int $partnerId): bool
    {
        if ($partnerId <= 0 || !self::partnersHasColumn('credit_limit')) {
            return false;
        }

        $limit = (float) self::authDb()->table('partners')->where('id', $partnerId)->value('credit_limit');
        if ($limit <= 0.005) {
            return false;
        }

        return self::summarizeUsedCredit($partnerId) + 0.005 >= $limit;
    }

    /**
     * Remaining B2B spend allowed under partners.credit_limit (limit − credit_used).
     */
    public static function remainingPurchaseAllowance(int $partnerId): float
    {
        if (self::isCreditLimitFullyUsed($partnerId)) {
            return 0.0;
        }

        return self::availableCredit($partnerId);
    }

    /**
     * Wallet + post-paid headroom available for new B2B purchases (0 when limit fully used).
     *
     * @deprecated Use remainingPurchaseAllowance() for limit cap; this mirrors it.
     */
    public static function partnerPurchaseHeadroom(int $partnerId): float
    {
        return self::remainingPurchaseAllowance($partnerId);
    }

    /**
     * Validate a B2B order amount against credit_limit and wallet.
     *
     * @return array{ok:bool,message?:string,code?:string,credit_limit?:float,credit_used?:float,remaining_limit?:float,wallet_balance?:float}
     */
    public static function validateB2bPurchaseAmount(int $partnerId, float $orderAmount, bool $skipCheck = false, ?int $excludeOrderId = null): array
    {
        if ($skipCheck || $partnerId <= 0) {
            return ['ok' => true];
        }

        $orderAmount = round(max(0.0, $orderAmount), 2);
        if ($orderAmount <= 0.005) {
            return ['ok' => true];
        }

        if (!self::partnersHasColumn('credit_limit')) {
            return ['ok' => true];
        }

        $limit = (float) self::authDb()->table('partners')->where('id', $partnerId)->value('credit_limit');
        if ($limit <= 0.005) {
            return ['ok' => true];
        }

        $used = self::summarizeUsedCredit($partnerId, $excludeOrderId);
        if ($used + 0.005 >= $limit) {
            return [
                'ok' => false,
                'message' => self::CREDIT_LIMIT_REACHED_MESSAGE,
                'code' => 'credit_limit_reached',
                'credit_limit' => $limit,
                'credit_used' => $used,
                'remaining_limit' => 0.0,
            ];
        }

        $remaining = max(0.0, round($limit - $used, 2));

        if ($orderAmount > $remaining + 0.0001) {
            return [
                'ok' => false,
                'message' => $used + 0.005 >= $limit
                    ? self::CREDIT_LIMIT_REACHED_MESSAGE
                    : 'Order amount exceeds available credit allocation. Remaining: ₹' . number_format($remaining, 2) . '.',
                'code' => $used + 0.005 >= $limit ? 'credit_limit_reached' : 'credit_limit_exceeded',
                'credit_limit' => $limit,
                'credit_used' => $used,
                'remaining_limit' => $remaining,
            ];
        }

        return ['ok' => true];
    }

    /**
     * @throws ValidationException
     */
    public static function assertB2bPurchaseAmount(int $partnerId, float $orderAmount, bool $skipCheck = false, ?int $excludeOrderId = null): void
    {
        $result = self::validateB2bPurchaseAmount($partnerId, $orderAmount, $skipCheck, $excludeOrderId);
        if (!empty($result['ok'])) {
            return;
        }

        $field = ($result['code'] ?? '') === 'insufficient_credits' ? 'payable_amount' : 'credit';
        throw ValidationException::withMessages([
            $field => (string) ($result['message'] ?? self::CREDIT_LIMIT_REACHED_MESSAGE),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public static function assertPartnerCanPurchase(int $partnerId, bool $skipCheck = false): void
    {
        if ($skipCheck || $partnerId <= 0) {
            return;
        }

        if (self::isCreditLimitFullyUsed($partnerId)) {
            throw ValidationException::withMessages([
                'credit' => self::CREDIT_LIMIT_REACHED_MESSAGE,
            ]);
        }
    }

    /**
     * Ledger audit only (PartnerCreditLine account). Caller updates partners.credit_limit.
     */
    public static function recordCreditLimitLedgerDelta(int $partnerId, float $oldLimit, float $newLimit, ?string $description = null): void
    {
        $delta = round($newLimit - $oldLimit, 2);
        if (abs($delta) < 0.005) {
            return;
        }

        $account = self::findOrCreateAuditAccount($partnerId);
        $account->refresh();

        $isIncrease = $delta > 0;
        if ($description === null || trim($description) === '') {
            $description = ($oldLimit <= 0.005)
                ? 'Initial Credit Allocation'
                : 'Credit Limit Revised by Admin';
        } else {
            $description = trim($description);
        }

        Ledger::create([
            'account_master_id' => $account->id,
            'order_id' => null,
            'transaction_type' => $isIncrease ? 'Credit' : 'Debit',
            'amount' => abs($delta),
            'description' => $description,
            'type' => $isIncrease ? 'credit' : 'debit',
            'reference_no' => 'CL-' . $partnerId . '-' . uniqid('', true),
        ]);
        $account->syncCurrentBalance();
    }

    /**
     * One audit row on the Partner credit-line account (LimitUpdate, or Credit + payment_mode fallback).
     * Caller is responsible for updating partners.credit_limit when needed.
     */
    public static function insertCreditLineAuditLedger(
        int $partnerId,
        float $amount,
        string $description,
        ?int $adminUserId = null,
        string $referencePrefix = 'CL'
    ): void {
        if (!self::ledgersTableExists()) {
            return;
        }

        $amount = round($amount, 2);
        if ($amount <= 0) {
            return;
        }

        $account = self::findOrCreateAuditAccount($partnerId);
        $account->refresh();

        $ledgerTypeColumn = self::ledgersHasColumn('type');
        $payload = [
            'account_master_id' => $account->id,
            'order_id' => null,
            'transaction_type' => 'LimitUpdate',
            'amount' => $amount,
            'description' => $description,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if ($ledgerTypeColumn) {
            $payload['type'] = 'credit';
        }
        if (self::ledgersHasColumn('payment_mode')) {
            $payload['payment_mode'] = self::CREDIT_LINE_LEDGER_PAYMENT_MODE;
        }
        if (self::ledgersHasColumn('reference_no')) {
            $payload['reference_no'] = $referencePrefix . '-' . $partnerId . '-' . ($adminUserId ?? 'x') . '-' . uniqid('', true);
        }

        try {
            self::billingDb()->table('ledgers')->insert($payload);
        } catch (\Throwable $e) {
            $payload['transaction_type'] = 'Credit';
            if (self::ledgersHasColumn('payment_mode')) {
                $payload['payment_mode'] = self::CREDIT_LINE_LEDGER_PAYMENT_MODE;
            }
            self::billingDb()->table('ledgers')->insert($payload);
        }

        $account->syncCurrentBalance();
    }

    /**
     * @return list<array{at: Carbon, activity: string, amount: float, amount_label: string, available: float}>
     */
    public static function buildCreditTimeline(Partner|PartnerRecord $partner): array
    {
        $partnerId = (int) $partner->id;
        $events = [];

        $auditAccount = AccountMaster::query()
            ->where('refer_id', $partnerId)
            ->where('type', self::AUDIT_TYPE)
            ->first();

        if ($auditAccount) {
            $ledgers = self::billingDb()->table('ledgers')
                ->where('account_master_id', $auditAccount->id)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            foreach ($ledgers as $l) {
                $at = Carbon::parse($l->created_at);
                $events[] = [
                    'sort' => $at->getTimestamp() * 1000000 + (int) $l->id,
                    'at' => $at,
                    'kind' => 'ledger',
                    'ledger' => $l,
                ];
            }
        }

        if (self::ordersHasColumn('partner_id') && self::ordersHasColumn('credit_used')) {
            $q = Order::query()->where('partner_id', $partnerId);
            if (self::ordersHasColumn('order_type')) {
                $q->where(function ($qq) {
                    $qq->where('order_type', 'b2b')->orWhere('order_type', 'B2B');
                });
            }
            self::applySuccessfulStatusFilter($q);
            self::excludeOrdersWithPaidInvoice($q);
            $q->where('credit_used', '>', 0);
            foreach ($q->orderBy('created_at')->orderBy('id')->get() as $order) {
                $cu = (float) ($order->credit_used ?? 0);
                if ($cu <= 0) {
                    continue;
                }
                $at = Carbon::parse($order->created_at);
                $events[] = [
                    'sort' => $at->getTimestamp() * 1000000 + (int) $order->id,
                    'at' => $at,
                    'kind' => 'order',
                    'order' => $order,
                ];
            }
        }

        usort($events, static function ($a, $b) {
            return $a['sort'] <=> $b['sort'];
        });

        $hasAllocLedgers = $auditAccount && self::billingDb()->table('ledgers')->where('account_master_id', $auditAccount->id)->exists();
        $limit = $hasAllocLedgers ? 0.0 : (float) ($partner->credit_limit ?? 0);
        $used = 0.0;
        $rows = [];

        foreach ($events as $e) {
            if ($e['kind'] === 'ledger') {
                $l = $e['ledger'];
                $amt = (float) ($l->amount ?? 0);
                $tt = (string) ($l->transaction_type ?? '');
                $isCredit = strcasecmp($tt, 'Credit') === 0 || strcasecmp($tt, 'LimitUpdate') === 0;
                if ($isCredit) {
                    $limit += $amt;
                } else {
                    $limit -= $amt;
                }
                $signed = $isCredit ? $amt : -$amt;
                $avail = max(0.0, round($limit - $used, 2));
                $activity = (string) ($l->description ?? 'Credit limit change');
                $rows[] = [
                    'at' => $e['at'],
                    'activity' => $activity,
                    'amount' => $signed,
                    'amount_label' => ($signed >= 0 ? '+' : '') . '₹' . number_format(abs($signed), 2),
                    'available' => $avail,
                ];
            } else {
                /** @var \App\Models\Order $order */
                $order = $e['order'];
                $cu = (float) ($order->credit_used ?? 0);
                $used += $cu;
                $oid = self::ordersHasColumn('order_no') && !empty($order->order_no)
                    ? (string) $order->order_no
                    : ('#' . $order->id);
                $avail = max(0.0, round($limit - $used, 2));
                $rows[] = [
                    'at' => $e['at'],
                    'activity' => 'Credit Utilized — Order ' . $oid,
                    'amount' => -$cu,
                    'amount_label' => '−₹' . number_format($cu, 2),
                    'available' => $avail,
                ];
            }
        }

        return $rows;
    }

    public static function partnerApprovedForCreditManagement($status): bool
    {
        return $status === 'approved' || $status === 1 || $status === '1';
    }

    /**
     * @return array{credit_limit: float, wallet_balance: float, credit_used: float, available_post_paid: float, available_usable: float, total_orders: int, successful_orders: int, failed_or_cancelled_orders: int, success_rate_pct: float|null}
     */
    public static function getCreditManagementKpis(int $partnerId): array
    {
        $partnerRecord = PartnerRecord::query()->find($partnerId);
        // Legacy column only; purchase power is credit allocation alone (not wallet + limit).
        $wallet = self::partnerWalletSnapshot($partnerId);

        $limit = (float) ($partnerRecord?->credit_limit ?? 0);

        $used = self::summarizeUsedCredit($partnerId);
        // Available = credit allocation − outstanding (unpaid) credit_used.
        // Paying the invoice restores that amount to the partner's remaining credit.
        $postAvail = max(0.0, round($limit - $used, 2));
        $usable = $postAvail;

        $totalOrders = 0;
        $successful = 0;
        $failedOrCancelled = 0;
        if (self::ordersHasColumn('partner_id')) {
            $q = Order::query()->where('partner_id', $partnerId);
            if (self::ordersHasColumn('order_type')) {
                $q->where(function ($qq) {
                    $qq->where('order_type', 'b2b')->orWhere('order_type', 'B2B');
                });
            }
            $totalOrders = (int) (clone $q)->count();
            if (self::ordersHasColumn('status')) {
                $successful = (int) (clone $q)->whereRaw(
                    "LOWER(TRIM(COALESCE(status, ''))) IN (?, ?, ?, ?)",
                    ['completed', 'paid', 'success', 'active'],
                )->count();
                $failedOrCancelled = (int) (clone $q)->whereRaw(
                    "LOWER(TRIM(COALESCE(status, ''))) IN (?, ?, ?)",
                    ['cancelled', 'failed', 'fraud'],
                )->count();
            }
        }

        $denom = $successful + $failedOrCancelled;
        $successRate = $denom > 0 ? round(100.0 * $successful / $denom, 1) : null;

        return [
            'credit_limit' => $limit,
            'wallet_balance' => $wallet,
            'credit_used' => $used,
            'available_post_paid' => $postAvail,
            'available_usable' => $usable,
            'total_orders' => $totalOrders,
            'successful_orders' => $successful,
            'failed_or_cancelled_orders' => $failedOrCancelled,
            'success_rate_pct' => $successRate,
        ];
    }

    /**
     * Sum of B2B order credit_used per bucket for Chart.js (week | month | year).
     *
     * @return array{labels: list<string>, values: list<float>}
     */
    public static function getCreditUsageTrend(int $partnerId, string $granularity): array
    {
        $granularity = strtolower(trim($granularity));
        if (!in_array($granularity, ['week', 'month', 'year'], true)) {
            $granularity = 'week';
        }

        $labels = [];
        $keys = [];
        $now = Carbon::now();

        if ($granularity === 'week') {
            for ($i = 7; $i >= 0; $i--) {
                $start = $now->copy()->subWeeks($i)->startOfWeek();
                $keys[] = $start->format('Y-m-d');
                $labels[] = $start->format('d M');
            }
        } elseif ($granularity === 'month') {
            for ($i = 11; $i >= 0; $i--) {
                $start = $now->copy()->subMonths($i)->startOfMonth();
                $keys[] = $start->format('Y-m');
                $labels[] = $start->format('M Y');
            }
        } else {
            for ($i = 4; $i >= 0; $i--) {
                $start = $now->copy()->subYears($i)->startOfYear();
                $keys[] = $start->format('Y');
                $labels[] = $start->format('Y');
            }
        }

        $sums = array_fill(0, count($keys), 0.0);

        if (!self::ordersHasColumn('partner_id')) {
            return ['labels' => $labels, 'values' => $sums];
        }

        $from = $granularity === 'week'
            ? Carbon::parse($keys[0])->startOfDay()
            : ($granularity === 'month'
                ? Carbon::createFromFormat('Y-m', $keys[0])->startOfMonth()
                : Carbon::createFromFormat('Y', $keys[0])->startOfYear());

        $q = Order::query()
            ->where('partner_id', $partnerId)
            ->where('created_at', '>=', $from);
        if (self::ordersHasColumn('order_type')) {
            $q->where(function ($qq) {
                $qq->where('order_type', 'b2b')->orWhere('order_type', 'B2B');
            });
        }
        self::applySuccessfulStatusFilter($q);
        self::excludeOrdersWithPaidInvoice($q);
        if (self::ordersHasColumn('credit_used')) {
            $q->where('credit_used', '>', 0);
        }

        foreach ($q->orderBy('created_at')->get(['created_at', 'credit_used', 'subtotal']) as $order) {
            $at = Carbon::parse($order->created_at);
            $amt = self::ordersHasColumn('credit_used')
                ? (float) ($order->credit_used ?? 0)
                : (float) ($order->subtotal ?? 0);
            if ($amt <= 0) {
                continue;
            }
            if ($granularity === 'week') {
                $bucket = $at->copy()->startOfWeek()->format('Y-m-d');
            } elseif ($granularity === 'month') {
                $bucket = $at->format('Y-m');
            } else {
                $bucket = $at->format('Y');
            }
            $idx = array_search($bucket, $keys, true);
            if ($idx !== false) {
                $sums[$idx] = round($sums[$idx] + $amt, 2);
            }
        }

        return ['labels' => $labels, 'values' => $sums];
    }

    /**
     * @return Collection<int, object>
     */
    public static function listOrderCreditUsage(int $partnerId): Collection
    {
        if (!self::ordersHasColumn('partner_id') || !self::ordersHasColumn('credit_used')) {
            return collect();
        }

        $q = Order::query()
            ->where('partner_id', $partnerId)
            ->where('credit_used', '>', 0);

        if (self::ordersHasColumn('order_type')) {
            $q->where(function ($x) {
                $x->where('order_type', 'b2b')->orWhere('order_type', 'B2B');
            });
        }
        self::applySuccessfulStatusFilter($q);

        $select = ['id', 'user_id', 'domain_name', 'credit_used', 'created_at'];
        if (self::ordersHasColumn('status')) {
            $select[] = 'status';
        }
        if (self::ordersHasColumn('order_category')) {
            $select[] = 'order_category';
        }

        return $q->orderByDesc('created_at')->orderByDesc('id')->get($select);
    }

    /**
     * Credit-line timeline plus wallet recharge rows (sorted), for admin history table.
     *
     * @return list<array{at: Carbon, activity: string, amount_label: string, available: float}>
     */
    public static function buildMergedCreditAdminTimeline(Partner|PartnerRecord $partner): array
    {
        $base = self::buildCreditTimeline($partner);
        $walletCredits = self::listPartnerWalletCredits((int) $partner->id);

        $merged = [];
        foreach ($base as $i => $row) {
            $merged[] = [
                'sort' => $row['at']->getTimestamp() * 1000000 + $i,
                'at' => $row['at'],
                'activity' => $row['activity'],
                'amount_label' => $row['amount_label'],
                'available' => $row['available'],
                'is_wallet_only' => false,
            ];
        }

        foreach ($walletCredits as $w) {
            $at = Carbon::parse($w->created_at);
            $merged[] = [
                'sort' => $at->getTimestamp() * 1000000 + 500000 + (int) $w->id,
                'at' => $at,
                'activity' => 'Wallet recharge — ' . (string) ($w->description ?? ''),
                'amount_label' => '+₹' . number_format((float) $w->amount, 2),
                'available' => null,
                'is_wallet_only' => true,
            ];
        }

        usort($merged, static function ($a, $b) {
            return $a['sort'] <=> $b['sort'];
        });

        $lastAvail = 0.0;
        $out = [];
        foreach ($merged as $row) {
            if (!empty($row['is_wallet_only'])) {
                $out[] = [
                    'at' => $row['at'],
                    'activity' => $row['activity'],
                    'amount_label' => $row['amount_label'],
                    'available' => $lastAvail,
                ];
            } else {
                $lastAvail = (float) $row['available'];
                $out[] = [
                    'at' => $row['at'],
                    'activity' => $row['activity'],
                    'amount_label' => $row['amount_label'],
                    'available' => $lastAvail,
                ];
            }
        }

        return $out;
    }

    /**
     * Update partners.credit_limit with audit ledger (approved partners only).
     *
     * @throws \InvalidArgumentException
     * @throws ValidationException
     */
    public static function updatePartnerCreditLimit(int $partnerId, float $newLimit, string $note = ''): void
    {
        $newLimit = round($newLimit, 2);
        $note = trim($note);

        DB::connection(self::authConnection())->transaction(function () use ($partnerId, $newLimit, $note) {
            /** @var PartnerRecord $partner */
            $partner = PartnerRecord::query()->whereKey($partnerId)->lockForUpdate()->firstOrFail();
            if (!self::partnerApprovedForCreditManagement($partner->status)) {
                throw new \InvalidArgumentException('Partner is not approved for credit management.');
            }

            $old = (float) ($partner->credit_limit ?? 0);
            $used = self::summarizeUsedCredit($partnerId);
            if ($newLimit + 0.005 < $used) {
                throw ValidationException::withMessages([
                    'credit_limit' => 'Credit limit cannot be less than credit already used (₹' . number_format($used, 2) . ').',
                ]);
            }

            if (abs($newLimit - $old) < 0.01) {
                return;
            }

            $partner->credit_limit = $newLimit;
            if (self::partnersHasColumn('wallet_balance')) {
                $partner->wallet_balance = 0;
            }
            $partner->save();
            self::recordCreditLimitLedgerDelta($partnerId, $old, $newLimit, $note !== '' ? $note : null);
        });
    }

    /**
     * Wallet top-up is retired: amount increases credit allocation (credit_limit) only.
     *
     * @throws \InvalidArgumentException
     * @throws ValidationException
     */
    public static function applyAdminCreditTopUpAndOptionalLimit(
        int $partnerId,
        float $walletAmount,
        ?float $newCreditLimit,
        string $note,
        ?int $adminUserId
    ): void {
        $walletAmount = round($walletAmount, 2);
        $note = trim($note);
        if ($note === '') {
            $note = 'Admin credit allocation increase';
        }

        DB::connection(self::authConnection())->transaction(function () use ($partnerId, $walletAmount, $newCreditLimit, $note) {
            /** @var PartnerRecord $partner */
            $partner = PartnerRecord::query()->whereKey($partnerId)->lockForUpdate()->firstOrFail();
            if (!self::partnerApprovedForCreditManagement($partner->status)) {
                throw new \InvalidArgumentException('Partner is not approved for credit management.');
            }

            if (!self::partnersHasColumn('credit_limit')) {
                throw new \InvalidArgumentException('Credit allocation is not configured on partners.');
            }

            $old = round((float) ($partner->credit_limit ?? 0), 2);
            $used = self::summarizeUsedCredit($partnerId);
            $target = $old;

            if ($newCreditLimit !== null) {
                $target = round((float) $newCreditLimit, 2);
            } elseif ($walletAmount > 0.005) {
                $target = round($old + $walletAmount, 2);
            }

            if ($target + 0.005 < $used) {
                throw ValidationException::withMessages([
                    'topup_amount' => 'Credit allocation cannot be less than consumed amount (₹' . number_format($used, 2) . ').',
                ]);
            }

            if (abs($target - $old) < 0.01) {
                throw new \InvalidArgumentException('Enter an amount to increase credit allocation, or a new allocation total.');
            }

            $partner->credit_limit = $target;
            if (self::partnersHasColumn('wallet_balance')) {
                $partner->wallet_balance = 0;
            }
            $partner->save();
            self::recordCreditLimitLedgerDelta($partnerId, $old, $target, $note);
        });
    }

    /**
     * Credit allocation increase / decrease history (audit account), not wallet top-ups.
     *
     * @return Collection<int, object>
     */
    public static function listPartnerCreditAllocationHistory(int $partnerId): Collection
    {
        if ($partnerId <= 0 || !self::ledgersTableExists()) {
            return collect();
        }

        $account = AccountMaster::query()
            ->where('refer_id', $partnerId)
            ->where('type', self::AUDIT_TYPE)
            ->where('name', self::auditAccountName($partnerId))
            ->first();

        if (!$account) {
            return collect();
        }

        $rows = Ledger::query()
            ->where('account_master_id', $account->id)
            ->where(function ($q) {
                $q->where('transaction_type', 'Credit')
                    ->orWhere('transaction_type', 'LimitUpdate')
                    ->orWhere(function ($qq) {
                        $qq->where('transaction_type', 'Debit');
                    });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'created_at', 'amount', 'description', 'admin_user_id', 'transaction_type']);

        $adminIds = $rows->pluck('admin_user_id')->filter()->unique()->values();
        $adminNames = collect();
        if ($adminIds->isNotEmpty()) {
            $adminNames = DB::connection('pgsql_admin')
                ->table('admin_user_creation')
                ->whereIn('id', $adminIds)
                ->pluck('user_name', 'id');
        }

        return $rows->map(function ($row) use ($adminNames) {
            $adminName = null;
            if ($row->admin_user_id) {
                $name = $adminNames->get($row->admin_user_id);
                $adminName = ($name && $name !== '') ? $name : ('Admin User #' . $row->admin_user_id);
            }
            $isDecrease = strcasecmp((string) ($row->transaction_type ?? ''), 'Debit') === 0;

            return (object) [
                'id' => $row->id,
                'created_at' => $row->created_at,
                'amount' => $row->amount,
                'description' => $row->description,
                'admin_user_id' => $row->admin_user_id,
                'admin_name' => $adminName,
                'is_decrease' => $isDecrease,
            ];
        });
    }
}
