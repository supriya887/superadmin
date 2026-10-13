<?php

namespace App\Services;

use App\Models\SmsObject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Centralized SMS sender for Tizzy Group.
 *
 * One entry point — `send($mobile, $type, $variables)` — that:
 *   1. Resolves the DLT template_id from the $type (constants below).
 *   2. Loads the SMS body from the `sms_templates` table when available;
 *      otherwise falls back to the built-in template strings.
 *   3. Substitutes `{#var#}` placeholders (in order) with $variables.
 *   4. Pushes the SMS through the active `sms_settings` row (provider=jmd_sms).
 *   5. Logs every failure WITHOUT throwing, so callers never break their
 *      own DB transactions because of an SMS issue.
 *
 * Supported types (use the public constants):
 *   - SmsService::TYPE_OTP_REGISTRATION    → DLT 1707177650708988480
 *   - SmsService::TYPE_PAYMENT_CONFIRMATION → DLT 1707177650912852063
 *   - SmsService::TYPE_RENEW_PLAN          → DLT 1707177650891627520
 *   - SmsService::TYPE_UPGRADE_PLATFORM    → DLT 1707177650875511604
 *   - SmsService::TYPE_ORDER_PROCESSING    → DLT (to be registered)
 *   - SmsService::TYPE_ORDER_CONFIRMATION  → DLT (to be registered)
 */
class SmsService
{
    /* ---------- Type constants ---------- */
    public const TYPE_OTP_REGISTRATION    = 'OTP_REGISTRATION';
    public const TYPE_PAYMENT_CONFIRMATION = 'PAYMENT_CONFIRMATION';
    public const TYPE_RENEW_PLAN          = 'RENEW_PLAN';
    public const TYPE_UPGRADE_PLATFORM    = 'UPGRADE_PLATFORM';
    public const TYPE_ORDER_PROCESSING    = 'ORDER_PROCESSING';
    public const TYPE_ORDER_CONFIRMATION  = 'ORDER_CONFIRMATION';

    /** DLT template IDs (Tizzy Group registration). */
    public const DLT_TEMPLATE_RENEW   = '1707177650891627520';
    public const DLT_TEMPLATE_UPGRADE = '1707177650875511604';
    public const DLT_TEMPLATE_ORDER_PROCESSING = '1707177650912852063'; // Using payment confirmation DLT as fallback
    public const DLT_TEMPLATE_ORDER_CONFIRMATION = '1707177650912852063'; // Using payment confirmation DLT as fallback

    /* ---------- DLT template-id map (locked per Tizzy DLT registration) ---------- */
    private const DLT_TEMPLATE_IDS = [
        self::TYPE_OTP_REGISTRATION     => '1707177650708988480',
        self::TYPE_PAYMENT_CONFIRMATION => '1707177650912852063',
        self::TYPE_RENEW_PLAN           => self::DLT_TEMPLATE_RENEW,
        self::TYPE_UPGRADE_PLATFORM     => self::DLT_TEMPLATE_UPGRADE,
        self::TYPE_ORDER_PROCESSING     => self::DLT_TEMPLATE_ORDER_PROCESSING,
        self::TYPE_ORDER_CONFIRMATION   => self::DLT_TEMPLATE_ORDER_CONFIRMATION,
    ];

    /* ---------- Built-in fallback templates (used if sms_templates row missing) ---------- */
    private const FALLBACK_TEMPLATES = [
        self::TYPE_OTP_REGISTRATION =>
            'Dear Customer, your OTP to complete registration on Tizzy Group is {#var#}. It is valid for {#var#} minutes. Do not share this OTP with anyone. - Tizzy Group',

        self::TYPE_PAYMENT_CONFIRMATION =>
            'Dear Customer, we have received your payment of Rs {#var#} towards order {#var#} on Tizzy Group. Thank you. - Tizzy Group',

        self::TYPE_RENEW_PLAN =>
            'Dear Customer, your plan has been successfully renewed on Tizzy Group. Valid till {#var#}. Amount paid: Rs {#var#}. Thank you. - Tizzy Group',

        self::TYPE_UPGRADE_PLATFORM =>
            'Dear Customer, your plan has been successfully upgraded to {#var#} on Tizzy Group. Amount paid: Rs {#var#}. Thank you. - Tizzy Group',

        self::TYPE_ORDER_PROCESSING =>
            'Dear Customer, your order {#var#} is being processed. Amount: Rs {#var#}. We will notify you once completed. - Tizzy Group',

        self::TYPE_ORDER_CONFIRMATION =>
            'Dear Customer, your order {#var#} has been confirmed. Amount: Rs {#var#}. Thank you for choosing Tizzy Group.',
    ];

    /* ====================================================================
     * Public API
     * ================================================================== */

    /**
     * Send a templated SMS.
     *
     * @param  string $mobile     Indian mobile (any common format; auto-normalised to 91XXXXXXXXXX).
     * @param  string $type       One of the SmsService::TYPE_* constants.
     * @param  array<int|string,scalar|null> $variables  Values that will replace each {#var#} placeholder, IN ORDER.
     *                                                   Keys are ignored — only the order matters.
     * @return bool               true if the gateway returned success; false otherwise (also false on local issues).
     */
    public function send(string $mobile, string $type, array $variables = []): bool
    {
        $normalized = $this->normalizeIndianMobile($mobile);
        if ($normalized === null) {
            Log::warning('SmsService: invalid mobile number', ['mobile' => $mobile, 'type' => $type]);
            return false;
        }

        if (!isset(self::DLT_TEMPLATE_IDS[$type])) {
            Log::warning('SmsService: unknown SMS type', ['type' => $type]);
            return false;
        }

        $templateBody = $this->resolveTemplateBody($type);
        if ($templateBody === '') {
            Log::warning('SmsService: empty template body', ['type' => $type]);
            return false;
        }

        $smsText = $this->fillPlaceholders($templateBody, array_values($variables));

        // Per-type DLT template id wins over whatever sms_settings has.
        $templateId = self::DLT_TEMPLATE_IDS[$type];

        return $this->dispatchToGateway($normalized, $smsText, $templateId, $type);
    }

    /* -------- Convenience wrappers (one-liner per use case) -------- */

    public function sendOtpRegistration(string $mobile, string|int $otp, int $expiryMinutes = 10): bool
    {
        return $this->send($mobile, self::TYPE_OTP_REGISTRATION, [(string) $otp, (string) $expiryMinutes]);
    }

    public function sendPaymentConfirmation(string $mobile, float|string $amount, string $orderNo): bool
    {
        return $this->send($mobile, self::TYPE_PAYMENT_CONFIRMATION, [
            $this->money($amount),
            $orderNo,
        ]);
    }

    public function sendRenewPlan(string $mobile, string $expiryDate, float|string $amount): bool
    {
        return $this->send($mobile, self::TYPE_RENEW_PLAN, [
            $expiryDate,
            $this->money($amount),
        ]);
    }

    public function sendUpgradePlatform(string $mobile, string $newPlanName, float|string $amount): bool
    {
        return $this->send($mobile, self::TYPE_UPGRADE_PLATFORM, [
            (string) $newPlanName,
            $this->money($amount),
        ]);
    }

    /**
     * Send order processing SMS
     */
    public function sendOrderProcessing(string $mobile, string $orderNo, float|string $amount): bool
    {
        return $this->send($mobile, self::TYPE_ORDER_PROCESSING, [
            $orderNo,
            $this->money($amount),
        ]);
    }

    /**
     * Send order confirmation SMS
     */
    public function sendOrderConfirmation(string $mobile, string $orderNo, float|string $amount): bool
    {
        return $this->send($mobile, self::TYPE_ORDER_CONFIRMATION, [
            $orderNo,
            $this->money($amount),
        ]);
    }

    /**
     * Send "plan renewed" SMS to user.
     *
     * @param string $mobile User's mobile number
     * @param string $subscription_end_date Plan validity end date (format as needed)
     * @param float|string $amount Paid amount
     */
    public function sendPlanRenewed(string $mobile, string $subscription_end_date, float|string $amount): bool
    {
        $normalized = $this->normalizeIndianMobile($mobile);
        if ($normalized === null) {
            Log::warning('SmsService: invalid mobile number', [
                'mobile' => $mobile,
                'type' => self::TYPE_RENEW_PLAN
            ]);
            return false;
        }

        $msg = "Dear Customer, your plan has been successfully renewed on Tizzy Group. Valid till {$subscription_end_date}. Amount paid: Rs "
            . $this->money($amount) . '. Thank you. - Tizzy Group';

        return $this->dispatchToGateway(
            $normalized,
            $msg,
            self::DLT_TEMPLATE_RENEW,
            self::TYPE_RENEW_PLAN
        );
    }

    /* ====================================================================
     * Internals
     * ================================================================== */

    /**
     * Replace each `{#var#}` placeholder, in order, with the supplied values.
     * Extra placeholders without a matching value are left as-is so that
     * truncation issues are obvious during testing.
     *
     * @param array<int,scalar|null> $values
     */
    private function fillPlaceholders(string $template, array $values): string
    {
        $i = 0;
        return preg_replace_callback('/\{#var#\}/', function () use ($values, &$i) {
            $val = $values[$i] ?? null;
            $i++;
            return $val === null ? '{#var#}' : (string) $val;
        }, $template) ?? $template;
    }

    /**
     * Look up the SMS body from `sms_templates`. Falls back to built-in
     * constants if the table/row is missing. This makes the service usable
     * before the table is created and lets ops edit copy without a deploy.
     */
    private function resolveTemplateBody(string $type): string
    {
        try {
            if (Schema::hasTable('sms_templates')) {
                $cols = Schema::getColumnListing('sms_templates');
                $nameCol = in_array('name', $cols, true) ? 'name'
                    : (in_array('template_name', $cols, true) ? 'template_name'
                    : (in_array('code', $cols, true) ? 'code' : null));
                $bodyCol = in_array('body', $cols, true) ? 'body'
                    : (in_array('content', $cols, true) ? 'content'
                    : (in_array('template', $cols, true) ? 'template'
                    : (in_array('message', $cols, true) ? 'message' : null)));

                if ($nameCol !== null && $bodyCol !== null) {
                    $row = DB::table('sms_templates')->where($nameCol, $type)->first();
                    if ($row && !empty($row->{$bodyCol})) {
                        return $this->sanitizeSmsText((string) $row->{$bodyCol});
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('SmsService: sms_templates lookup failed; using fallback', [
                'type'  => $type,
                'error' => $e->getMessage(),
            ]);
        }

        return self::FALLBACK_TEMPLATES[$type] ?? '';
    }

    /**
     * Push the prepared text to the JMD SMS gateway using the saved
     * sms_settings row (same row OTP code reads from).
     */
    private function sanitizeSmsText(string $text): string
    {
        $text = trim($text);
        $text = (string) preg_replace('/\s*\/a[A-Za-z0-9]+\s*$/', '', $text);
        return trim($text);
    }

    private function dispatchToGateway(string $number, string $text, string $templateId, string $type): bool
    {
        $cfg = $this->getSmsGatewaySettings();
        if (empty($cfg['base_url'])) {
            Log::warning('SmsService: sms_settings not configured', ['type' => $type]);
            return false;
        }

        $text = $this->sanitizeSmsText($text);

        try {
            $response = Http::timeout(15)->get((string) $cfg['base_url'], [
                'user'        => $cfg['user'],
                'password'    => $cfg['password'],
                'senderid'    => $cfg['senderid'],
                'channel'     => $cfg['channel'],
                'DCS'         => $cfg['dcs'],
                'flashsms'    => '0',
                'number'      => $number,
                'text'        => $text,
                'route'       => $cfg['route'],
                'peid'        => $cfg['peid'],
                'template_id' => $templateId,
            ]);

            if (!$response->successful()) {
                Log::warning('SmsService: gateway HTTP error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                    'type'   => $type,
                    'number' => $number,
                ]);
                return false;
            }

            $payload = $response->json();
            if (is_array($payload) && ($payload['ErrorCode'] ?? null) !== null && (string) $payload['ErrorCode'] !== '000') {
                Log::warning('SmsService: gateway business error', [
                    'response' => $payload,
                    'type'     => $type,
                    'number'   => $number,
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('SmsService: dispatch exception', [
                'message' => $e->getMessage(),
                'type'    => $type,
                'number'  => $number,
            ]);
            return false;
        }
    }

    /**
     * @return array{base_url:string,user:string,password:string,senderid:string,channel:string,dcs:string,flashsms:string,route:string,peid:string}
     */
    private function getSmsGatewaySettings(): array
    {
        try {
            $row = SmsObject::query()
                ->where('provider', 'jmd_sms')
                ->orderByDesc('id')
                ->first();
        } catch (\Throwable $e) {
            Log::warning('SmsService: sms_settings fetch failed', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }

        if (!$row) {
            return [];
        }

        return [
            'base_url' => (string) ($row->base_url ?? ''),
            'user'     => (string) ($row->user ?? ''),
            'password' => (string) ($row->password ?? ''),
            'senderid' => (string) ($row->senderid ?? $row->sender_id ?? ''),
            'channel'  => (string) ($row->channel ?? ''),
            'dcs'      => (string) ($row->dcs ?? ''),
            'flashsms' => (string) ($row->flashsms ?? ''),
            'route'    => (string) ($row->route ?? ''),
            'peid'     => (string) ($row->peid ?? $row->PEId ?? ''),
        ];
    }

    /**
     * Normalise common Indian mobile inputs to the `91XXXXXXXXXX` format
     * that JMD's SendSMS endpoint expects.
     */
    private function normalizeIndianMobile(string $mobile): ?string
    {
        $digits = preg_replace('/\D+/', '', $mobile);
        if (!is_string($digits) || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }
        if (strlen($digits) === 10) {
            return '91' . $digits;
        }
        if (strlen($digits) >= 11 && strlen($digits) <= 15) {
            return $digits;
        }
        return null;
    }

    private function money(float|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}