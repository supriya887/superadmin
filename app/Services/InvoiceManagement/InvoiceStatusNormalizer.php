<?php

namespace App\Services\InvoiceManagement;

/**
 * Maps raw `invoice_mst.status` values to dashboard tabs: success | pending | fail.
 */
final class InvoiceStatusNormalizer
{
    public static function tab(?string $status): string
    {
        $s = strtolower(trim((string) $status));

        if ($s === 'paid' || in_array($s, ['success', 'completed', 'succeeded', 'credited', 'issued', '1'], true)) {
            return 'success';
        }

        if ($s === 'failed' || in_array($s, ['cancelled', 'canceled', 'void', 'rejected'], true)) {
            return 'fail';
        }

        return 'pending';
    }

    /**
     * SQL fragment for counting "pending" invoices (not success/fail).
     */
    public static function isPendingSql(string $column = 'invoice_mst.status'): string
    {
        $success = "'paid'";
        $fail = "'failed'";

        return "({$column} IS NULL OR LOWER(TRIM({$column})) NOT IN ({$success}) AND LOWER(TRIM({$column})) NOT IN ({$fail}))";
    }
}
