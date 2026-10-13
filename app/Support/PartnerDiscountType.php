<?php

namespace App\Support;

use App\Models\Partner;
use App\Models\Plan;

class PartnerDiscountType
{
    public static function label(?string $type): ?string
    {
        return match (strtolower(trim((string) $type))) {
            'market_value' => 'Market Value',
            'distributor' => 'Distri Value',
            default => null,
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            'market_value' => 'Market Value',
            'distributor' => 'Distri Value',
        ];
    }

    public static function normalize(?string $type): string
    {
        return strtolower(trim((string) $type)) === 'distributor' ? 'distributor' : 'market_value';
    }

    /**
     * @return array{type: string, label: string|null}
     */
    public static function resolveForPartner(null|int|string $partnerId): array
    {
        $type = 'market_value';
        $id = (int) $partnerId;
        if ($id > 0) {
            $partner = Partner::query()->find($id);
            if ($partner) {
                $type = self::normalize((string) ($partner->partner_discount_type ?? ''));
            }
        }

        return [
            'type' => $type,
            'label' => self::label($type),
        ];
    }

    /**
     * Resolve market/distributor unit prices from a plan row (handles Microsoft / legacy fills).
     *
     * @return array{
     *   price: float,
     *   price_type: string,
     *   price_type_label: string|null,
     *   market_price: float|null,
     *   distributor_price: float|null,
     *   actual_price: float|null
     * }
     */
    public static function fromPlan(Plan $plan, string $discountType): array
    {
        $attrs = $plan->getAttributes();
        $marketRaw = round((float) ($attrs['actual_price'] ?? $plan->actual_price ?? 0), 2);
        $distributorRaw = round((float) ($attrs['price'] ?? $plan->price ?? 0), 2);

        // Microsoft / software: distributor may be derived from actual + rebate.
        if ($distributorRaw <= 0) {
            try {
                $discounted = round((float) $plan->getDiscountedPrice(), 2);
            } catch (\Throwable $e) {
                $discounted = 0.0;
            }
            if ($discounted > 0) {
                $distributorRaw = $discounted;
            }
        }

        // Legacy Microsoft rows: market empty, only price filled → treat price as market too.
        if ($marketRaw <= 0 && $distributorRaw > 0) {
            $marketRaw = $distributorRaw;
        }

        // If market exists but distributor empty, keep distributor usable for fallback.
        if ($distributorRaw <= 0 && $marketRaw > 0) {
            $distributorRaw = $marketRaw;
        }

        return self::applyToPlanPrices($marketRaw, $distributorRaw, $discountType);
    }

    /**
     * Pick the unit price for a partner discount type from stored market/distributor values.
     *
     * @return array{
     *   price: float,
     *   price_type: string,
     *   price_type_label: string|null,
     *   market_price: float|null,
     *   distributor_price: float|null,
     *   actual_price: float|null
     * }
     */
    public static function applyToPlanPrices(
        float|int|string|null $storedMarket,
        float|int|string|null $storedDistributor,
        string $discountType
    ): array {
        $type = self::normalize($discountType);
        $marketRaw = round((float) (is_numeric($storedMarket) ? $storedMarket : 0), 2);
        $distributorRaw = round((float) (is_numeric($storedDistributor) ? $storedDistributor : 0), 2);

        // Prefer the assigned type, but never return 0 when the other column has a value.
        if ($type === 'distributor') {
            $applicable = $distributorRaw > 0 ? $distributorRaw : $marketRaw;
        } else {
            $applicable = $marketRaw > 0 ? $marketRaw : $distributorRaw;
        }
        $applicable = round(max(0.0, (float) $applicable), 2);

        // Always expose both prices when known (do not null market when equal to distributor).
        $marketPrice = $marketRaw > 0 ? $marketRaw : ($applicable > 0 ? $applicable : null);
        $distributorPrice = $distributorRaw > 0 ? $distributorRaw : ($applicable > 0 ? $applicable : null);

        return [
            'price' => $applicable,
            'price_type' => $type,
            'price_type_label' => self::label($type),
            'market_price' => $marketPrice,
            'distributor_price' => $distributorPrice,
            'actual_price' => $marketPrice,
        ];
    }
}
