<?php

namespace App\Helpers;

use App\Models\Provider;
use Illuminate\Support\Facades\Cache;

class ProviderHelper
{
    /**
     * Check if a provider is Microsoft by ID
     */
    public static function isMicrosoftProvider($providerId): bool
    {
        if (!$providerId) {
            return false;
        }

        // Check cache first
        $cacheKey = 'provider_is_microsoft_' . $providerId;
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $provider = Provider::find($providerId);
        if (!$provider) {
            Cache::put($cacheKey, false, 3600);
            return false;
        }

        $name = strtolower(trim($provider->name));
        $isMicrosoft = $name === 'microsoft' || strpos($name, 'microsoft') !== false;
        
        Cache::put($cacheKey, $isMicrosoft, 3600);
        return $isMicrosoft;
    }

    /**
     * Get all Microsoft provider IDs
     */
    public static function getMicrosoftProviderIds(): array
    {
        $cacheKey = 'microsoft_provider_ids';
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $ids = Provider::whereRaw('LOWER(name) LIKE ?', ['%microsoft%'])
            ->pluck('id')
            ->toArray();

        Cache::put($cacheKey, $ids, 3600);
        return $ids;
    }

    /**
     * Check if provider has variants
     */
    public static function providerHasVariants($providerId): bool
    {
        $provider = Provider::find($providerId);
        return $provider && ($provider->has_variant ?? 'no') === 'yes';
    }

    /**
     * Clear Microsoft provider cache
     */
    public static function clearMicrosoftCache(): void
    {
        Cache::forget('microsoft_provider_ids');
        foreach (self::getMicrosoftProviderIds() as $id) {
            Cache::forget('provider_is_microsoft_' . $id);
        }
    }
}