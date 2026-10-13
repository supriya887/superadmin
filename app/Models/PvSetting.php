<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class PvSetting extends Model
{
    protected $connection = 'pgsql_billing';

    protected $table = 'pv_setting';

    public $timestamps = false;

    /** Non-Razorpay keys stored in the same table (see BillingController). */
    public const NON_RAZORPAY_KEY_IDS = [
        'domaincheck_base_url',
        'domaincheck_auth_userid',
        'domaincheck_api_key',
        'rezorpay_test_api_key',
        'rezorpay_test_secret_key',
        'razorpay_test_api_key',
        'razorpay_test_secret_key',
        'razorpay_live_api_key',
        'razorpay_live_secret_key',
    ];

    protected $fillable = [
        'key_id',
        'key_secret',
    ];

    /**
     * Resolve Razorpay API key + secret from pv_setting only.
     *
     * @return array{0: string, 1: string}
     */
    public static function razorpayCredentials(): array
    {
        $candidates = static::razorpayCredentialCandidates();
        if ($candidates === []) {
            throw new RuntimeException(
                'Razorpay credentials are missing or invalid in pv_setting. Add matching rezorpay_test_api_key + rezorpay_test_secret_key rows (or rzp_test_* / rzp_live_* with key_secret).'
            );
        }

        return $candidates[0];
    }

    /**
     * All unique Razorpay [key, secret] pairs found in pv_setting (named rows first, then rzp_* rows).
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function razorpayCredentialCandidates(): array
    {
        $candidates = [];
        $seen = [];

        $push = static function (string $key, string $secret) use (&$candidates, &$seen): void {
            $key = self::normalizeCredential($key);
            $secret = self::normalizeCredential($secret);
            if ($key === '' || $secret === '' || !str_starts_with($key, 'rzp_')) {
                return;
            }
            $fingerprint = $key . '|' . $secret;
            if (isset($seen[$fingerprint])) {
                return;
            }
            $seen[$fingerprint] = true;
            $candidates[] = [$key, $secret];
        };

        // Named rows: pair test/live correctly
        foreach (['live', 'test'] as $mode) {
            $apiKeyRow = static::query()
                ->whereIn('key_id', [
                    "razorpay_{$mode}_api_key",
                    "rezorpay_{$mode}_api_key",
                ])
                ->orderByDesc('id')
                ->first();

            $secretRow = static::query()
                ->whereIn('key_id', [
                    "razorpay_{$mode}_secret_key",
                    "rezorpay_{$mode}_secret_key",
                ])
                ->orderByDesc('id')
                ->first();

            $key = (string) ($apiKeyRow->key_secret ?? '');
            $secret = (string) ($secretRow->key_secret ?? '');
            if ($key === '' || $secret === '') {
                continue;
            }
            if ($mode === 'live' && !str_starts_with(self::normalizeCredential($key), 'rzp_live_')) {
                continue;
            }
            if ($mode === 'test' && !str_starts_with(self::normalizeCredential($key), 'rzp_test_')) {
                continue;
            }
            $push($key, $secret);
        }

        // Direct rzp_* rows (key_id = key, key_secret = secret)
        $directRows = static::query()
            ->where(function ($q) {
                $q->where('key_id', 'ilike', 'rzp_test_%')
                    ->orWhere('key_id', 'ilike', 'rzp_live_%');
            })
            ->orderByDesc('id')
            ->get(['key_id', 'key_secret']);

        foreach ($directRows as $row) {
            $push((string) $row->key_id, (string) ($row->key_secret ?? ''));
        }

        // Cross-pair: named API key + every direct-row secret / named secret (covers mismatched rows)
        $namedKeys = static::query()
            ->whereIn('key_id', [
                'razorpay_test_api_key', 'rezorpay_test_api_key',
                'razorpay_live_api_key', 'rezorpay_live_api_key',
            ])
            ->get(['key_secret']);
        $allSecrets = static::query()
            ->where(function ($q) {
                $q->whereIn('key_id', [
                    'razorpay_test_secret_key', 'rezorpay_test_secret_key',
                    'razorpay_live_secret_key', 'rezorpay_live_secret_key',
                ])->orWhere('key_id', 'ilike', 'rzp_test_%')
                    ->orWhere('key_id', 'ilike', 'rzp_live_%');
            })
            ->get(['key_id', 'key_secret']);

        foreach ($namedKeys as $keyRow) {
            $key = self::normalizeCredential((string) ($keyRow->key_secret ?? ''));
            if ($key === '' || !str_starts_with($key, 'rzp_')) {
                continue;
            }
            foreach ($allSecrets as $secretRow) {
                // Direct rzp row: secret is key_secret; named secret row: also key_secret
                $secret = self::normalizeCredential((string) ($secretRow->key_secret ?? ''));
                if ($secret === '' || str_starts_with($secret, 'rzp_')) {
                    // Skip if someone stored a key id in secret column incorrectly for named rows;
                    // but direct rows always have secret in key_secret.
                    if (str_starts_with($secret, 'rzp_')) {
                        continue;
                    }
                }
                if ($secret !== '') {
                    $push($key, $secret);
                }
            }
        }

        return $candidates;
    }

    private static function normalizeCredential(string $value): string
    {
        $value = trim($value);
        $value = trim($value, "\"'");

        return $value;
    }
}
