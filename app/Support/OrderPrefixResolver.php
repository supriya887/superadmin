<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderPrefixResolver
{
    private const SETTINGS_CONNECTION = 'pgsql_admin';

    /**
     * Resolve order number prefix from admin_settings by order type.
     *
     * @param  string|null  $orderCategory  new|renewal|renew|upgrade
     */
    public static function resolve(?string $orderCategory = 'new'): string
    {
        $category = strtolower(trim((string) $orderCategory));
        if ($category === '') {
            $category = 'new';
        }

        $settings = self::loadSettings();
        $field = match (true) {
            in_array($category, ['renewal', 'renew'], true) => 'renewal_prefix',
            $category === 'upgrade' => 'upgrade_prefix',
            in_array($category, ['new', 'new_order'], true) => 'new_order_prefix',
            default => null,
        };

        if ($field !== null) {
            $prefix = self::readSettingField($settings, $field);
            if ($prefix !== '') {
                return $prefix;
            }
        }

        $fallback = self::readSettingField($settings, 'order_prefix');

        return $fallback !== '' ? $fallback : 'TNX';
    }

    /** @return object|null */
    private static function loadSettings(): ?object
    {
        if (!Schema::connection(self::SETTINGS_CONNECTION)->hasTable('admin_settings')) {
            return null;
        }

        $row = DB::connection(self::SETTINGS_CONNECTION)
            ->table('admin_settings')
            ->where('id', 1)
            ->first();
        if ($row) {
            return $row;
        }

        return DB::connection(self::SETTINGS_CONNECTION)
            ->table('admin_settings')
            ->orderBy('id')
            ->first();
    }

    /** @param  object|null  $settings */
    private static function readSettingField(?object $settings, string $field): string
    {
        if (
            !$settings
            || !Schema::connection(self::SETTINGS_CONNECTION)->hasColumn('admin_settings', $field)
        ) {
            return '';
        }

        return strtoupper(trim((string) ($settings->{$field} ?? '')));
    }
}
