<?php

namespace App\Services;

use App\Models\AdminSetting;
use App\Models\InvoiceMst;
use App\Models\InvoiceSub;
use App\Models\OtherFinancialYear;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class InvoiceService
{
    /**
     * Create master + child invoice rows in one transaction.
     *
     * @param array{
     *   cust_id:int,
     *   bill_date?:string|null,
     *   status?:string|null,
     *   action?:string|null,
     *   tax_type?:string|null,
     *   net_amt:float|int|string,
     *   taxable_total?:float|int|string|null,
     *   cgst_total?:float|int|string|null,
     *   sgst_total?:float|int|string|null,
     *   igst_total?:float|int|string|null,
     *   items:array<int,array{
     *     invoice_no?:string|null,
     *     description?:string|null,
     *     hsn_sac?:string|null,
     *     qty:float|int|string,
     *     cnt_rate:float|int|string,
     *     taxable_amt?:float|int|string|null,
     *     tax_type?:string|null,
     *     cgst?:float|int|string|null,
     *     sgst?:float|int|string|null,
     *     igst?:float|int|string|null,
     *     nettotal:float|int|string
     *   }>
     * } $payload
     */
    public function createInvoice(array $payload): array
    {
        $billing = InvoiceMst::query()->getConnection();

        return $billing->transaction(function () use ($payload) {
            $billDate = !empty($payload['bill_date']) ? Carbon::parse((string) $payload['bill_date']) : Carbon::now();
            $billNo = $this->generateInvoiceNumber($billDate);
            $rawStatus = strtolower(trim((string) ($payload['status'] ?? 'pending')));
            if ($rawStatus === 'paid') {
                $status = 'paid';
            } elseif ($rawStatus === 'failed' || in_array($rawStatus, ['cancelled', 'canceled', 'rejected', 'void'], true)) {
                $status = 'failed';
            } else {
                $status = 'pending';
            }
            $action = strtolower(trim((string) ($payload['action'] ?? 'add_order')));
            $taxType = strtolower(trim((string) ($payload['tax_type'] ?? '')));

            $master = [
                'bill_no_full' => $billNo,
                'bill_date' => $billDate->toDateString(),
                'cust_id' => (int) ($payload['cust_id'] ?? 0),
                'net_amt' => (float) ($payload['net_amt'] ?? 0),
                'status' => $status,
                'taxable_total' => (float) ($payload['taxable_total'] ?? 0),
                'cgst_total' => (float) ($payload['cgst_total'] ?? 0),
                'sgst_total' => (float) ($payload['sgst_total'] ?? 0),
                'igst_total' => (float) ($payload['igst_total'] ?? 0),
                'tax_type' => $taxType !== '' ? $taxType : null,
                'action_type' => $action,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (!empty($payload['partner_id'])) {
                $master['partner_id'] = (int) $payload['partner_id'];
            }

            $invoiceId = (int) InvoiceMst::query()->insertGetId($master);

            $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
            foreach ($items as $item) {
                $desc = (string) ($item['invoice_no'] ?? $item['description'] ?? '');
                if ($action === 'upgrade' && $desc === '') {
                    $desc = 'Plan Upgrade';
                } elseif ($action === 'renew' && $desc === '') {
                    $desc = 'Plan Renewal';
                } elseif ($action === 'add_order' && $desc === '') {
                    $desc = 'New Order';
                }

                $row = [
                    'invoice_mst_id' => $invoiceId,
                    'invoice_no' => $desc,
                    'hsn_sac' => (string) ($item['hsn_sac'] ?? ''),
                    'qty' => (float) ($item['qty'] ?? 0),
                    'cnt_rate' => (float) ($item['cnt_rate'] ?? 0),
                    'taxable_amt' => (float) ($item['taxable_amt'] ?? 0),
                    'tax_type' => strtolower(trim((string) ($item['tax_type'] ?? ($taxType !== '' ? $taxType : '')))),
                    'cgst' => (float) ($item['cgst'] ?? 0),
                    'sgst' => (float) ($item['sgst'] ?? 0),
                    'igst' => (float) ($item['igst'] ?? 0),
                    'nettotal' => (float) ($item['nettotal'] ?? 0),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                InvoiceSub::query()->insert($row);
            }

            return [
                'id' => $invoiceId,
                'bill_no_full' => $billNo,
            ];
        });
    }

    /**
     * Create a credit note (invoice_mst + invoice_sub) for order cancellation refunds.
     *
     * @param array<string, mixed> $payload
     */
    public function createCreditNote(array $payload): array
    {
        $payload['action'] = 'credit_note';
        unset($payload['reference_order_id']);

        $this->ensureInvoiceMoneyColumnsNumeric();

        $connName = (new InvoiceSub())->getConnectionName() ?: 'pgsql_billing';
        $igstType = $this->invoiceColumnNativeType($connName, 'invoice_sub', 'igst');
        if ($this->isIntegerSqlType($igstType)) {
            throw new \RuntimeException(
                'invoice_sub.igst is still integer. Run: ALTER TABLE invoice_sub ALTER COLUMN igst TYPE NUMERIC(14,2) USING igst::numeric;'
            );
        }

        $billing = InvoiceMst::query()->getConnection();

        return $billing->transaction(function () use ($payload, $billing) {
            $billDate = !empty($payload['bill_date']) ? Carbon::parse((string) $payload['bill_date']) : Carbon::now();
            $billNo = $this->generateCreditNoteNumber($billDate);
            $rawStatus = strtolower(trim((string) ($payload['status'] ?? 'paid')));
            $status = in_array($rawStatus, ['paid', 'success', 'completed', 'credited'], true) ? 'paid' : 'pending';
            $action = 'credit_note';
            $taxType = strtolower(trim((string) ($payload['tax_type'] ?? '')));

            $master = [
                'bill_no_full' => $billNo,
                'bill_date' => $billDate->toDateString(),
                'cust_id' => (int) ($payload['cust_id'] ?? 0),
                'net_amt' => (float) ($payload['net_amt'] ?? 0),
                'status' => $status,
                'taxable_total' => (float) ($payload['taxable_total'] ?? 0),
                'cgst_total' => (float) ($payload['cgst_total'] ?? 0),
                'sgst_total' => (float) ($payload['sgst_total'] ?? 0),
                'igst_total' => (float) ($payload['igst_total'] ?? 0),
                'tax_type' => $taxType !== '' ? $taxType : null,
                'action_type' => $action,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (!empty($payload['partner_id'])) {
                $master['partner_id'] = (int) $payload['partner_id'];
            }

            $connName = method_exists($billing, 'getName')
                ? (string) $billing->getName()
                : ((new InvoiceMst())->getConnectionName() ?: 'pgsql_billing');
            $billingSchema = Schema::connection($connName);
            if (!empty($payload['order_id']) && $billingSchema->hasColumn('invoice_mst', 'order_id')) {
                $master['order_id'] = (int) $payload['order_id'];
            }
            // Drop columns that may not exist on older schemas.
            foreach (array_keys($master) as $col) {
                if (!$billingSchema->hasColumn('invoice_mst', $col)) {
                    unset($master[$col]);
                }
            }

            $invoiceId = (int) InvoiceMst::query()->insertGetId($master);

            // Always force-link order_id when column exists (defensive).
            if ($invoiceId > 0 && !empty($payload['order_id']) && $billingSchema->hasColumn('invoice_mst', 'order_id')) {
                InvoiceMst::query()->whereKey($invoiceId)->update([
                    'order_id' => (int) $payload['order_id'],
                    'updated_at' => now(),
                ]);
            }

            $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
            foreach ($items as $item) {
                $desc = (string) ($item['description'] ?? $item['invoice_no'] ?? 'Order cancellation credit note');
                $line = [
                    'invoice_mst_id' => $invoiceId,
                    'invoice_no' => $desc,
                    'hsn_sac' => (string) ($item['hsn_sac'] ?? ''),
                    'qty' => $this->castInvoiceSubQty($item['qty'] ?? 1, $connName),
                    'cnt_rate' => round((float) ($item['cnt_rate'] ?? 0), 2),
                    'taxable_amt' => round((float) ($item['taxable_amt'] ?? 0), 2),
                    'tax_type' => strtolower(trim((string) ($item['tax_type'] ?? ($taxType !== '' ? $taxType : '')))),
                    'cgst' => round((float) ($item['cgst'] ?? 0), 2),
                    'sgst' => round((float) ($item['sgst'] ?? 0), 2),
                    'igst' => round((float) ($item['igst'] ?? 0), 2),
                    'nettotal' => round((float) ($item['nettotal'] ?? 0), 2),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                foreach (array_keys($line) as $col) {
                    if (!$billingSchema->hasColumn('invoice_sub', $col)) {
                        unset($line[$col]);
                    }
                }
                InvoiceSub::query()->insert($line);
            }

            return [
                'id' => $invoiceId,
                'bill_no_full' => $billNo,
            ];
        });
    }

    public function generateCreditNoteNumber(?Carbon $forDate = null): string
    {
        $forDate = $forDate ?: Carbon::now();
        $billing = InvoiceMst::query()->getConnection();

        return $billing->transaction(function () use ($forDate) {
            $prefix = $this->readCreditNotePrefix();
            [$fyId, $fyLabel] = $this->resolveCurrentFinancialYear($forDate);
            $serial = $this->nextCreditNoteSerialForFinancialYear($fyLabel);

            return $prefix . '/' . $fyLabel . '/' . str_pad((string) $serial, 4, '0', STR_PAD_LEFT);
        });
    }

    private function readCreditNotePrefix(): string
    {
        try {
            $conn = AdminSetting::query()->getConnectionName() ?: 'pgsql_admin';
            if (Schema::connection($conn)->hasColumn('admin_settings', 'credit_note_prefix')) {
                $prefix = (string) AdminSetting::query()->whereKey(1)->value('credit_note_prefix');
                if (trim($prefix) !== '') {
                    return strtoupper(trim($prefix));
                }
            }
        } catch (\Throwable $e) {
            // Column may not exist on older schemas — use default.
        }

        return 'CN';
    }

    private function nextCreditNoteSerialForFinancialYear(string $fyLabel): int
    {
        $like = 'CN/' . $fyLabel . '/%';
        InvoiceMst::query()
            ->where('bill_no_full', 'like', $like)
            ->lockForUpdate()
            ->orderByDesc('id')
            ->limit(1)
            ->get();

        $lastBill = (string) InvoiceMst::query()
            ->where('bill_no_full', 'like', $like)
            ->orderByDesc('id')
            ->value('bill_no_full');
        if ($lastBill === '') {
            return 1;
        }

        $parts = explode('/', $lastBill);
        $lastSerial = (int) end($parts);

        return $lastSerial > 0 ? $lastSerial + 1 : 1;
    }

    /**
     * Older billing DBs stored GST amounts as integer (Postgres int4). Credit notes
     * need paise (e.g. IGST 451.44) so those columns must be numeric.
     */
    public function ensureInvoiceMoneyColumnsNumeric(): void
    {
        Cache::forget('invoice_money_columns_numeric_v1');
        $cacheKey = 'invoice_money_columns_numeric_v2';
        if (Cache::get($cacheKey)) {
            return;
        }

        $connName = (new InvoiceSub())->getConnectionName() ?: 'pgsql_billing';
        $tables = [
            'invoice_sub' => ['cnt_rate', 'taxable_amt', 'cgst', 'sgst', 'igst', 'nettotal'],
            'invoice_mst' => ['net_amt', 'taxable_total', 'cgst_total', 'sgst_total', 'igst_total'],
        ];

        $allNumeric = true;
        try {
            $driver = Schema::connection($connName)->getConnection()->getDriverName();
            foreach ($tables as $table => $columns) {
                if (!Schema::connection($connName)->hasTable($table)) {
                    continue;
                }
                foreach ($columns as $column) {
                    if (!$this->promoteMoneyColumnToNumeric($connName, $driver, $table, $column)) {
                        $allNumeric = false;
                    }
                }
            }
            if ($allNumeric) {
                Cache::put($cacheKey, true, now()->addDays(30));
            }
        } catch (\Throwable $e) {
            Log::warning('ensureInvoiceMoneyColumnsNumeric failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return bool true when the column is numeric (or missing)
     */
    private function promoteMoneyColumnToNumeric(string $connName, string $driver, string $table, string $column): bool
    {
        if (!Schema::connection($connName)->hasColumn($table, $column)) {
            return true;
        }

        $type = $this->invoiceColumnNativeType($connName, $table, $column);
        if (!$this->isIntegerSqlType($type)) {
            return true;
        }

        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?: '';
        $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column) ?: '';
        if ($safeTable === '' || $safeColumn === '') {
            return false;
        }

        try {
            if ($driver === 'pgsql') {
                try {
                    DB::connection($connName)->unprepared(
                        "ALTER TABLE {$safeTable} ALTER COLUMN {$safeColumn} DROP DEFAULT"
                    );
                } catch (\Throwable) {
                    // Column may not have a default.
                }
                DB::connection($connName)->unprepared(
                    "ALTER TABLE {$safeTable} ALTER COLUMN {$safeColumn} TYPE NUMERIC(14,2) USING COALESCE({$safeColumn}::numeric, 0)"
                );
                try {
                    DB::connection($connName)->unprepared(
                        "ALTER TABLE {$safeTable} ALTER COLUMN {$safeColumn} SET DEFAULT 0"
                    );
                } catch (\Throwable) {
                    // Default is optional.
                }
            } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::connection($connName)->unprepared(
                    "ALTER TABLE `{$safeTable}` MODIFY `{$safeColumn}` DECIMAL(14,2) NOT NULL DEFAULT 0"
                );
            } else {
                return false;
            }

            $type = $this->invoiceColumnNativeType($connName, $table, $column);
            $ok = !$this->isIntegerSqlType($type);
            Log::info('invoice_money_column_promoted', [
                'table' => $table,
                'column' => $column,
                'now' => $type,
                'ok' => $ok,
            ]);

            return $ok;
        } catch (\Throwable $e) {
            Log::warning('invoice_money_column_promote_failed', [
                'table' => $table,
                'column' => $column,
                'from' => $type,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function invoiceColumnNativeType(string $connName, string $table, string $column): string
    {
        try {
            $row = DB::connection($connName)->selectOne(
                "SELECT udt_name, data_type
                 FROM information_schema.columns
                 WHERE table_schema = current_schema()
                   AND table_name = ?
                   AND column_name = ?
                 LIMIT 1",
                [$table, $column]
            );
            if ($row) {
                return strtolower(trim((string) ($row->udt_name ?? $row->data_type ?? '')));
            }
        } catch (\Throwable) {
            // Fall through to Laravel schema.
        }

        try {
            return strtolower((string) Schema::connection($connName)->getColumnType($table, $column));
        } catch (\Throwable) {
            return '';
        }
    }

    private function isIntegerSqlType(string $type): bool
    {
        $type = strtolower(trim($type));

        return in_array($type, [
            'integer',
            'int',
            'int2',
            'int4',
            'int8',
            'bigint',
            'smallint',
            'tinyint',
            'serial',
            'bigserial',
        ], true);
    }

    private function castInvoiceSubQty(mixed $qty, string $connName): int|float
    {
        $value = max(1, (float) $qty);
        $type = $this->invoiceColumnNativeType($connName, 'invoice_sub', 'qty');
        if ($this->isIntegerSqlType($type) || $type === '') {
            return max(1, (int) round($value));
        }

        return round($value, 2);
    }

    /**
     * Generate invoice no: {prefix}/{year}/{serial}
     * Example: TZG/26-27/0001
     */
    public function generateInvoiceNumber(?Carbon $forDate = null): string
    {
        $forDate = $forDate ?: Carbon::now();

        $billing = InvoiceMst::query()->getConnection();

        return $billing->transaction(function () use ($forDate) {
            $prefix = $this->readInvoicePrefix();
            [$fyId, $fyLabel] = $this->resolveCurrentFinancialYear($forDate);
            $serial = $this->nextSerialForFinancialYear($fyId, $fyLabel);

            return $prefix . '/' . $fyLabel . '/' . str_pad((string) $serial, 4, '0', STR_PAD_LEFT);
        });
    }

    private function resolveCurrentFinancialYear(Carbon $forDate): array
    {
        $row = OtherFinancialYear::query()
            ->whereDate('start_date', '<=', $forDate->toDateString())
            ->whereDate('end_date', '>=', $forDate->toDateString())
            ->orderByDesc('id')
            ->first();
        if ($row) {
            $label = (string) ($row->finyear ?? $row->financial_year ?? '');
            if ($label === '') {
                $label = $this->fyLabelFromDates((string) ($row->start_date ?? ''), (string) ($row->end_date ?? ''));
            }
            return [(int) ($row->id ?? 0), $label];
        }

        $fyStartYear = $forDate->month >= 4 ? (int) $forDate->year : (int) $forDate->year - 1;
        $start = Carbon::create($fyStartYear, 4, 1);
        $end = Carbon::create($fyStartYear + 1, 3, 31);

        return [0, $start->format('y') . '-' . $end->format('y')];
    }

    private function fyLabelFromDates(string $startDate, string $endDate): string
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        return $start->format('y') . '-' . $end->format('y');
    }

    private function readInvoicePrefix(): string
    {
        // admin_settings is a single-row config table in this schema:
        //   id, title, logo, invoice_prefix, order_prefix, address, ...
        // (NO key/value setting_name / setting_value columns).
        $prefix = (string) AdminSetting::query()->whereKey(1)->value('invoice_prefix');
        if (trim($prefix) !== '') {
            return strtoupper(trim($prefix));
        }

        $prefix = (string) AdminSetting::query()->orderByDesc('id')->value('invoice_prefix');
        if (trim($prefix) !== '') {
            return strtoupper(trim($prefix));
        }

        // Defensive fallback so an un-seeded admin_settings can never crash
        // the invoice generation; ops can later set a real prefix.
        return 'INV';
    }

    /**
     * Derive the next serial directly from `invoice_mst.bill_no_full` for
     * the current FY, avoiding the bogus admin_settings key/value lookup
     * that does not exist in this schema. Wrapped in lockForUpdate so two
     * concurrent invoice creations cannot pick the same serial.
     */
    private function nextSerialForFinancialYear(int $fyId, string $fyLabel): int
    {
        $like = '%/' . $fyLabel . '/%';

        // SELECT … FOR UPDATE on the matching rows so the next inserter
        // sees the row we are about to commit and bumps past it.
        InvoiceMst::query()
            ->where('bill_no_full', 'like', $like)
            ->lockForUpdate()
            ->orderByDesc('id')
            ->limit(1)
            ->get();

        $last = $this->lastSerialFromInvoices($fyLabel);
        return $last > 0 ? $last + 1 : 1;
    }

    /**
     * Helper: highest serial currently present in invoice_mst for the FY.
     */
    private function lastSerialFromInvoices(string $fyLabel): int
    {
        $like = '%/' . $fyLabel . '/%';
        $lastBill = (string) InvoiceMst::query()
            ->where('bill_no_full', 'like', $like)
            ->orderByDesc('id')
            ->value('bill_no_full');
        if ($lastBill === '') {
            return 0;
        }
        $parts = explode('/', $lastBill);
        $lastSerial = (int) end($parts);
        return $lastSerial;
    }
}

