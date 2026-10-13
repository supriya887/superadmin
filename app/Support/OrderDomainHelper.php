<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderDomainHelper
{
    public const DOMAIN_SEGMENT_PATTERN = '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i';

    /** Order statuses that free the domain for reuse. */
    public const DOMAIN_FREE_STATUSES = [
        'cancelled',
        'canceled',
        'rejected',
        'failed',
        'void',
        'draft',
    ];

    public static function isMicrosoftProvider(?int $providerId): bool
    {
        if ($providerId === null || $providerId <= 0) {
            return false;
        }
        if ($providerId === 2) {
            return true;
        }
        if (!Schema::hasTable('providers')) {
            return false;
        }
        $row = DB::table('providers')->where('id', $providerId)->first(['slug', 'name']);
        if (!$row) {
            return false;
        }
        $label = strtolower(trim(($row->slug ?? '') . ' ' . ($row->name ?? '')));

        return strpos($label, 'microsoft') !== false
            || strpos($label, 'office 365') !== false
            || strpos($label, 'm365') !== false;
    }

    public static function allowsMultiDomainSeparators(?int $providerId): bool
    {
        return self::isMicrosoftProvider($providerId);
    }

    /**
     * @return list<string>
     */
    public static function parseSegments(string $raw, ?int $providerId): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        if (!self::allowsMultiDomainSeparators($providerId)) {
            return [strtolower($raw)];
        }
        $parts = preg_split('/\s*[+,]\s*/', $raw) ?: [];

        return array_values(array_filter(array_map(
            static fn (string $part): string => strtolower(trim($part)),
            $parts
        ), static fn (string $part): bool => $part !== ''));
    }

    public static function isValidSegment(string $segment): bool
    {
        return (bool) preg_match(self::DOMAIN_SEGMENT_PATTERN, trim($segment));
    }

    /**
     * @return array{ok: bool, message: string, segments: list<string>}
     */
    public static function validateInput(string $raw, ?int $providerId): array
    {
        $segments = self::parseSegments($raw, $providerId);
        if ($segments === []) {
            return [
                'ok' => false,
                'message' => 'Domain name is required.',
                'segments' => [],
            ];
        }

        foreach ($segments as $segment) {
            if (!self::isValidSegment($segment)) {
                return [
                    'ok' => false,
                    'message' => 'Invalid domain format: ' . $segment,
                    'segments' => $segments,
                ];
            }
        }

        return [
            'ok' => true,
            'message' => '',
            'segments' => $segments,
        ];
    }

    public static function resolveProviderIdFromRequest(\Illuminate\Http\Request $request): int
    {
        $providerId = (int) $request->input('provider_id', $request->query('provider_id', 0));
        if ($providerId > 0) {
            return $providerId;
        }

        $planId = (int) $request->input('plan_id', $request->query('plan_id', 0));
        if ($planId > 0 && Schema::hasTable('plans') && Schema::hasColumn('plans', 'provider_id')) {
            return (int) (DB::table('plans')->where('id', $planId)->value('provider_id') ?? 0);
        }

        $plans = $request->input('plans', []);
        if (is_array($plans) && isset($plans[0]) && is_array($plans[0])) {
            $firstPlanId = (int) ($plans[0]['plan_id'] ?? 0);
            if ($firstPlanId > 0 && Schema::hasTable('plans') && Schema::hasColumn('plans', 'provider_id')) {
                return (int) (DB::table('plans')->where('id', $firstPlanId)->value('provider_id') ?? 0);
            }
        }

        return 0;
    }

    /**
     * True when domain is on any order that is still live (not cancelled / draft / failed).
     * Processing, pending, completed, active, etc. all count as "in use".
     */
    public static function isDomainInUseOnActiveOrder(string $domain, ?int $excludeOrderId = null): bool
    {
        return self::activeOrdersUsingDomain($domain, $excludeOrderId)->isNotEmpty();
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    public static function activeOrdersUsingDomain(string $domain, ?int $excludeOrderId = null)
    {
        $domain = strtolower(trim($domain));
        if ($domain === '' || !Schema::connection('pgsql_billing')->hasTable('orders')) {
            return collect();
        }

        $free = self::DOMAIN_FREE_STATUSES;
        $freeList = "'" . implode("','", array_map('addslashes', $free)) . "'";

        $query = DB::connection('pgsql_billing')
            ->table('orders')
            ->whereRaw('LOWER(TRIM(COALESCE(domain_name, \'\'))) = ?', [$domain])
            ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) NOT IN ({$freeList})");

        if ($excludeOrderId !== null && $excludeOrderId > 0) {
            $query->where('id', '!=', $excludeOrderId);
        }

        return $query
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'order_no', 'partner_id', 'plan_id', 'status', 'domain_name']);
    }

    public static function domainInUseMessage(): string
    {
        return 'This domain is already in use on an existing order (including processing/pending). Use another domain, or cancel the existing order first.';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function existingPlansForDomain(string $domain): array
    {
        $orders = self::activeOrdersUsingDomain($domain);
        if ($orders->isEmpty()) {
            return [];
        }

        $planIds = $orders->pluck('plan_id')->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        $plansById = [];
        if ($planIds !== [] && Schema::connection('pgsql_product')->hasTable('plans')) {
            $plansById = DB::connection('pgsql_product')
                ->table('plans')
                ->whereIn('id', $planIds)
                ->get(['id', 'name', 'provider_id'])
                ->keyBy('id')
                ->all();
        }

        $out = [];
        foreach ($orders as $order) {
            $planId = (int) ($order->plan_id ?? 0);
            $plan = $plansById[$planId] ?? null;
            $out[] = [
                'order_id' => (int) ($order->id ?? 0),
                'order_no' => (string) ($order->order_no ?? ''),
                'status' => (string) ($order->status ?? ''),
                'plan_id' => $planId > 0 ? $planId : null,
                'plan_name' => (string) ($plan->name ?? ''),
                'provider_id' => (int) ($plan->provider_id ?? 0) ?: null,
            ];
        }

        return $out;
    }

    /**
     * @return list<int>
     */
    public static function existingProviderIdsForDomain(string $domain): array
    {
        return collect(self::existingPlansForDomain($domain))
            ->pluck('provider_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public static function isDomainUsedByOtherProvider(string $domain, int $providerId): bool
    {
        if ($providerId <= 0) {
            return false;
        }
        $ids = self::existingProviderIdsForDomain($domain);
        if ($ids === []) {
            return false;
        }

        foreach ($ids as $id) {
            if ((int) $id !== $providerId) {
                return true;
            }
        }

        return false;
    }

    public static function otherProviderConflictMessage(): string
    {
        return 'This domain is already linked to another provider\'s plan. Choose a different domain.';
    }

    /**
     * Cart/order domain_name may be a string, CSV, JSON array, or PHP array.
     */
    public static function normalizeStored(mixed $raw): string
    {
        if (is_array($raw)) {
            $parts = array_values(array_filter(array_map(
                static fn ($value): string => trim((string) $value),
                $raw
            ), static fn (string $value): bool => $value !== '' && strcasecmp($value, 'Array') !== 0));

            return self::dedupeList(implode(',', $parts));
        }

        $stored = trim((string) ($raw ?? ''));
        if ($stored === '' || strcasecmp($stored, 'Array') === 0 || $stored === '-' || strcasecmp($stored, 'null') === 0) {
            return '';
        }

        if (
            (str_starts_with($stored, '[') && str_ends_with($stored, ']'))
            || (str_starts_with($stored, '{') && str_ends_with($stored, '}'))
        ) {
            $decoded = json_decode($stored, true);
            if (is_array($decoded)) {
                return self::normalizeStored($decoded);
            }
        }

        return self::dedupeList($stored);
    }

    /**
     * Remove duplicate domain segments from a CSV string (case-insensitive).
     */
    public static function dedupeList(string $csv): string
    {
        $csv = trim($csv);
        if ($csv === '') {
            return '';
        }

        $parts = preg_split('/\s*,\s*/', $csv) ?: [];
        $seen = [];
        $unique = [];
        foreach ($parts as $part) {
            $segment = strtolower(trim((string) $part));
            if ($segment === '' || isset($seen[$segment])) {
                continue;
            }
            $seen[$segment] = true;
            $unique[] = $segment;
        }

        return implode(',', $unique);
    }

    /**
     * Domain for order-details: order → cart → parent subscription.
     * Uploaded-PO place can leave orders.domain_name empty while cart/parent still have it.
     */
    public static function resolveForOrder(?object $order, int $cartId = 0): string
    {
        $candidates = [];
        if ($order) {
            $candidates[] = $order->domain_name ?? null;
            if ($cartId <= 0) {
                $cartId = (int) ($order->cart_id ?? $order->source_cart_id ?? 0);
            }
        }

        $conn = DB::connection('pgsql_billing');
        $hasCarts = Schema::connection('pgsql_billing')->hasTable('carts');

        if ($cartId <= 0 && $order && $hasCarts) {
            $orderId = (int) ($order->id ?? 0);
            if ($orderId > 0 && Schema::connection('pgsql_billing')->hasColumn('carts', 'order_id')) {
                $cartId = (int) ($conn->table('carts')->where('order_id', $orderId)->orderByDesc('id')->value('id') ?? 0);
            }
        }

        if ($cartId > 0 && $hasCarts) {
            $candidates[] = $conn->table('carts')->where('id', $cartId)->value('domain_name');
        }

        $parentId = 0;
        if ($order) {
            $parentId = (int) ($order->parent_order_id ?? 0);
            if ($parentId <= 0) {
                $maybeParent = (int) ($order->order_id ?? 0);
                $selfId = (int) ($order->id ?? 0);
                if ($maybeParent > 0 && $maybeParent !== $selfId) {
                    $parentId = $maybeParent;
                }
            }
        }
        if ($parentId > 0) {
            $candidates[] = $conn->table('orders')->where('id', $parentId)->value('domain_name');
        }

        // Renew/upgrade cart: carts.order_id is the parent subscription.
        if ($order && $hasCarts) {
            $linkedParent = (int) ($order->order_id ?? 0);
            $selfId = (int) ($order->id ?? 0);
            if ($linkedParent > 0 && $linkedParent !== $selfId && $linkedParent !== $parentId) {
                $candidates[] = $conn->table('orders')->where('id', $linkedParent)->value('domain_name');
            }
        }

        foreach ($candidates as $raw) {
            $normalized = self::normalizeStored($raw);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }

    public static function persistOnOrderIfMissing(int $orderId, string $domain): void
    {
        $domain = self::normalizeStored($domain);
        if ($orderId <= 0 || $domain === '') {
            return;
        }
        if (!Schema::connection('pgsql_billing')->hasTable('orders')) {
            return;
        }

        $current = self::normalizeStored(
            DB::connection('pgsql_billing')->table('orders')->where('id', $orderId)->value('domain_name')
        );
        if ($current !== '') {
            return;
        }

        DB::connection('pgsql_billing')->table('orders')->where('id', $orderId)->update([
            'domain_name' => $domain,
            'updated_at' => now(),
        ]);
    }
}
