<?php

namespace App\Services;

use App\Models\Partner;
use Illuminate\Support\Facades\Schema;

/**
 * Partner discount system:
 * - market_value: discount offered to the customer
 * - distributor: discount received from the Microsoft distributor
 *
 * Each type stores either a percentage or a fixed amount (₹ per license).
 * Apply only when a mode is selected on the cart/order flow.
 */
class PartnerDiscountService
{
    public const MODE_NONE = 'none';
    public const MODE_MARKET_VALUE = 'market_value';
    public const MODE_DISTRIBUTOR = 'distributor';

    public const TYPE_PERCENT = 'percent';
    public const TYPE_AMOUNT = 'amount';

    public static function modes(): array
    {
        return [self::MODE_NONE, self::MODE_MARKET_VALUE, self::MODE_DISTRIBUTOR];
    }

    public static function normalizeMode(?string $mode): string
    {
        $mode = strtolower(trim((string) $mode));
        return in_array($mode, self::modes(), true) ? $mode : self::MODE_NONE;
    }

    /**
     * @return array{mode:string,type:string,percent:float,amount:float,label:string}
     */
    public function resolveConfig(?Partner $partner, ?string $mode): array
    {
        $mode = self::normalizeMode($mode);
        $empty = [
            'mode' => self::MODE_NONE,
            'type' => self::TYPE_PERCENT,
            'percent' => 0.0,
            'amount' => 0.0,
            'label' => 'None',
        ];

        if (!$partner || $mode === self::MODE_NONE) {
            return $empty;
        }

        if ($mode === self::MODE_MARKET_VALUE) {
            $type = strtolower(trim((string) ($partner->market_value_discount_type ?? self::TYPE_PERCENT)));
            if (!in_array($type, [self::TYPE_PERCENT, self::TYPE_AMOUNT], true)) {
                $type = self::TYPE_PERCENT;
            }

            $percent = (float) ($partner->partner_discount_percent ?? 0);
            if ($percent <= 0) {
                $percent = (float) ($partner->discount_percent ?? 0);
            }

            return [
                'mode' => self::MODE_MARKET_VALUE,
                'type' => $type,
                'percent' => max(0.0, min(100.0, $percent)),
                'amount' => max(0.0, (float) ($partner->market_value_discount_amount ?? 0)),
                'label' => 'Market Value Discount',
            ];
        }

        $type = strtolower(trim((string) ($partner->distributor_discount_type ?? self::TYPE_PERCENT)));
        if (!in_array($type, [self::TYPE_PERCENT, self::TYPE_AMOUNT], true)) {
            $type = self::TYPE_PERCENT;
        }

        $percent = (float) ($partner->partner_discount_percent ?? 0);
        if ($percent <= 0) {
            $percent = (float) ($partner->distributor_discount ?? 0);
        }

        return [
            'mode' => self::MODE_DISTRIBUTOR,
            'type' => $type,
            'percent' => max(0.0, min(100.0, $percent)),
            'amount' => max(0.0, (float) ($partner->distributor_discount_amount ?? 0)),
            'label' => 'Distributor Discount',
        ];
    }

    /**
     * Apply selected partner discount to a unit price (₹ per license / year).
     *
     * @return array{
     *   unit_price:float,
     *   base_unit_price:float,
     *   partner_discount_mode:string,
     *   partner_discount_type:string,
     *   partner_discount_percent:float,
     *   partner_discount_amount:float,
     *   partner_discount_per_unit:float,
     *   partner_discount_line_amount:float
     * }
     */
    public function applyToUnitPrice(float $baseUnitPrice, ?Partner $partner, ?string $mode, int $licenses = 1): array
    {
        $baseUnitPrice = max(0.0, round($baseUnitPrice, 2));
        $licenses = max(1, $licenses);
        $config = $this->resolveConfig($partner, $mode);

        $perUnit = 0.0;
        if ($config['mode'] !== self::MODE_NONE) {
            if ($config['type'] === self::TYPE_AMOUNT) {
                $perUnit = min($baseUnitPrice, round((float) $config['amount'], 2));
            } elseif ($config['percent'] > 0) {
                $perUnit = round(($baseUnitPrice * (float) $config['percent']) / 100, 2);
                $perUnit = min($baseUnitPrice, $perUnit);
            }
        }

        $unitPrice = max(0.0, round($baseUnitPrice - $perUnit, 2));
        $lineAmount = round($perUnit * $licenses, 2);

        return [
            'unit_price' => $unitPrice,
            'base_unit_price' => $baseUnitPrice,
            'partner_discount_mode' => $config['mode'],
            'partner_discount_type' => $config['type'],
            'partner_discount_percent' => $config['type'] === self::TYPE_PERCENT ? (float) $config['percent'] : 0.0,
            'partner_discount_amount' => $config['type'] === self::TYPE_AMOUNT ? (float) $config['amount'] : 0.0,
            'partner_discount_per_unit' => $perUnit,
            'partner_discount_line_amount' => $lineAmount,
            'partner_discount_label' => $config['label'],
        ];
    }

    /**
     * Merge partner discount into PartnerCartService::calculateLineAmounts() result.
     *
     * @param  array<string,mixed>  $amounts
     * @return array<string,mixed>
     */
    public function applyToLineAmounts(array $amounts, ?Partner $partner, ?string $mode, int $licenses = 1): array
    {
        $baseUnit = (float) ($amounts['unit_price'] ?? $amounts['base_unit_price'] ?? 0);
        $applied = $this->applyToUnitPrice($baseUnit, $partner, $mode, $licenses);

        $amounts['base_unit_price'] = $applied['base_unit_price'];
        $amounts['unit_price'] = $applied['unit_price'];
        $amounts['plan_discount_amount'] = round(
            (float) ($amounts['plan_discount_amount'] ?? 0) + $applied['partner_discount_line_amount'],
            2
        );
        $amounts['partner_discount_mode'] = $applied['partner_discount_mode'];
        $amounts['partner_discount_type'] = $applied['partner_discount_type'];
        $amounts['partner_discount_percent'] = $applied['partner_discount_percent'];
        $amounts['partner_discount_amount'] = $applied['partner_discount_amount'];
        $amounts['partner_discount_per_unit'] = $applied['partner_discount_per_unit'];
        $amounts['partner_discount_line_amount'] = $applied['partner_discount_line_amount'];
        $amounts['partner_discount_label'] = $applied['partner_discount_label'];

        // Keep line subtotal in sync when present.
        if (array_key_exists('subtotal', $amounts)) {
            $amounts['subtotal'] = round($applied['unit_price'] * max(1, $licenses), 2);
        }

        return $amounts;
    }

    /**
     * Snapshot of both configured discounts for API / UI.
     *
     * @return array<string,mixed>
     */
    public function partnerDiscountSummary(?Partner $partner): array
    {
        if (!$partner) {
            return [
                'market_value' => null,
                'distributor' => null,
            ];
        }

        $marketType = strtolower(trim((string) ($partner->market_value_discount_type ?? self::TYPE_PERCENT)));
        if (!in_array($marketType, [self::TYPE_PERCENT, self::TYPE_AMOUNT], true)) {
            $marketType = self::TYPE_PERCENT;
        }
        $distType = strtolower(trim((string) ($partner->distributor_discount_type ?? self::TYPE_PERCENT)));
        if (!in_array($distType, [self::TYPE_PERCENT, self::TYPE_AMOUNT], true)) {
            $distType = self::TYPE_PERCENT;
        }

        return [
            'market_value' => [
                'label' => 'Market Value Discount',
                'description' => 'Discount offered to the customer',
                'type' => $marketType,
                'percent' => round((float) ($partner->discount_percent ?? 0), 2),
                'amount' => round((float) ($partner->market_value_discount_amount ?? 0), 2),
            ],
            'distributor' => [
                'label' => 'Distributor Discount',
                'description' => 'Discount received from the Microsoft distributor',
                'type' => $distType,
                'percent' => round((float) ($partner->distributor_discount ?? 0), 2),
                'amount' => round((float) ($partner->distributor_discount_amount ?? 0), 2),
            ],
        ];
    }

    public function persistModeOnCart($cart, ?string $mode): void
    {
        if (!$cart) {
            return;
        }

        $mode = self::normalizeMode($mode);
        try {
            $connection = method_exists($cart, 'getConnectionName') ? $cart->getConnectionName() : 'pgsql_billing';
            $table = method_exists($cart, 'getTable') ? $cart->getTable() : 'carts';
            if (!Schema::connection($connection)->hasColumn($table, 'partner_discount_type')) {
                return;
            }
            $cart->partner_discount_type = $mode;
            $cart->save();
        } catch (\Throwable $e) {
            // Column may not exist yet on some environments.
        }
    }

    public function modeFromCart($cart): string
    {
        if (!$cart) {
            return self::MODE_NONE;
        }
        return self::normalizeMode($cart->partner_discount_type ?? self::MODE_NONE);
    }
}
