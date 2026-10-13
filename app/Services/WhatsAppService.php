<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * JMD Infotech WhatsApp Business API client.
 *
 * Templates are sent via:
 *   POST {base}/whatsapp/send/template
 * with phoneNumberID, botTemplateID, sendToPhoneNumber,
 * and templateVariable-{Name}-{n} (from JMD variable_map).
 */
class WhatsAppService
{
    public const CATEGORY_NEW = 'new';
    public const CATEGORY_RENEWAL = 'renewal';
    public const CATEGORY_UPGRADE = 'upgrade';
    public const CATEGORY_DOWNGRADE = 'downgrade';
    public const CATEGORY_CANCEL = 'cancel';
    public const CATEGORY_PROCESSING = 'processing';

    /**
     * Route order / subscription WhatsApp by category.
     *
     * @param  array{
     *   amount?: float|string|null,
     *   end_date?: string|null,
     *   plan_name?: string|null,
     *   details?: string|null,
     *   transaction_id?: string|null,
     *   reason?: string|null,
     *   invoice_url?: string|null,
     *   status?: string|null
     * }  $context
     */
    public function sendOrderNotification(
        string $mobile,
        string $orderNo,
        string $category = self::CATEGORY_NEW,
        array $context = []
    ): bool {
        $category = strtolower(trim($category));
        if ($category === 'upgraded') {
            $category = self::CATEGORY_UPGRADE;
        }

        // Handle processing category
        if ($category === self::CATEGORY_PROCESSING) {
            return $this->sendOrderProcessing($mobile, $orderNo, $context);
        }

        $amount = $this->money($context['amount'] ?? null);
        $amount = $amount !== '' ? $amount : '0';
        $planName = trim((string) ($context['plan_name'] ?? ''));
        if ($planName === '') {
            $planName = match ($category) {
                self::CATEGORY_DOWNGRADE => 'Downgraded plan',
                self::CATEGORY_UPGRADE => 'Upgraded plan',
                self::CATEGORY_RENEWAL => 'Renewed plan',
                default => 'Subscription plan',
            };
        }
        $endDate = trim((string) ($context['end_date'] ?? ''));
        if ($endDate === '') {
            $endDate = 'N/A';
        }
        $details = trim((string) ($context['details'] ?? ''));
        if ($details === '') {
            $details = $orderNo !== '' ? ('order ' . $orderNo) : 'your order';
        }
        $txnId = trim((string) ($context['transaction_id'] ?? $orderNo));
        if ($txnId === '') {
            $txnId = '-';
        }
        $reason = trim((string) ($context['reason'] ?? 'Order cancelled'));
        if ($reason === '') {
            $reason = 'Order cancelled';
        }

        $invoiceUrl = trim((string) ($context['invoice_url'] ?? ''));

        $ok = match ($category) {
            self::CATEGORY_RENEWAL => $this->sendRenewPlan($mobile, $endDate, $amount),
            self::CATEGORY_UPGRADE => $this->sendUpgrade($mobile, $planName, $amount, $orderNo),
            self::CATEGORY_DOWNGRADE => $this->sendDowngrade($mobile, $planName, $amount, $orderNo),
            self::CATEGORY_CANCEL => $this->sendOrderCancelled($mobile, $orderNo, $reason),
            default => $this->sendOrderConfirmation(
                $mobile,
                $orderNo,
                $amount,
                $planName,
                $details,
                $txnId,
                $invoiceUrl
            ),
        };

        // Attachment goes with activation messages (new / renew / upgrade / downgrade).
        // Cancel has no invoice. New already attaches inside sendOrderConfirmation.
        if (
            $ok
            && $invoiceUrl !== ''
            && $category !== self::CATEGORY_CANCEL
            && $category !== self::CATEGORY_NEW
            && $category !== self::CATEGORY_PROCESSING
        ) {
            $this->sendDocument($mobile, $invoiceUrl, 'Order invoice ' . $orderNo);
        }

        return $ok;
    }

    /**
     * Send order processing notification via WhatsApp
     */
    public function sendOrderProcessing(string $mobile, string $orderNo, array $context = []): bool
    {
        $amount = $this->money($context['amount'] ?? null);
        $amount = $amount !== '' ? $amount : '0';
        $planName = trim((string) ($context['plan_name'] ?? ''));
        if ($planName === '') {
            $planName = 'Subscription';
        }
        $status = trim((string) ($context['status'] ?? 'processing'));
        
        // Try to use a dedicated processing template if available
        $templateName = trim((string) config('services.jmd_whatsapp.templates.processing', ''));
        $templateId = trim((string) config('services.jmd_whatsapp.templates.processing_id', ''));
        
        // If no dedicated template, use order confirmation with processing context
        if ($templateName === '' || $templateId === '') {
            return $this->sendOrderConfirmation(
                $mobile,
                $orderNo,
                $amount,
                $planName,
                "Your order is {$status}",
                $orderNo,
                $context['invoice_url'] ?? ''
            );
        }
        
        return $this->sendNamedTemplate(
            $mobile,
            $templateName,
            $templateId,
            [
                'order' => $orderNo,
                'plan' => $planName,
                'Amount' => $amount,
                'status' => $status,
            ]
        );
    }

    /**
     * New-order activation template + optional invoice attachment.
     */
    public function sendOrderConfirmation(
        string $mobile,
        string $orderNo,
        float|string $amount,
        string $planName,
        string $details,
        string $transactionId,
        string $invoiceUrl = ''
    ): bool {
        $templateName = trim((string) config('services.jmd_whatsapp.templates.new', ''));
        $templateId = trim((string) config('services.jmd_whatsapp.templates.new_id', ''));
        if ($templateName === '') {
            $templateName = (string) config('services.jmd_whatsapp.templates.payment', 'payment_confirmation');
        }
        if ($templateId === '') {
            $templateId = (string) config('services.jmd_whatsapp.templates.payment_id', '364275');
        }

        $isPlanUpdate = $templateName === 'plan_update';
        $ok = $this->sendNamedTemplate(
            $mobile,
            $templateName,
            $templateId,
            $isPlanUpdate
                ? [
                    'var1' => $planName,
                    'var2' => $this->money($amount) ?: '0',
                ]
                : [
                    'plan' => $planName,
                    'Amount' => $this->money($amount) ?: '0',
                    'order' => $orderNo,
                ],
            $isPlanUpdate ? $invoiceUrl : null
        );

        // Always try invoice PDF attachment after the template (message + file).
        if (trim($invoiceUrl) !== '') {
            $docOk = $this->sendDocument($mobile, $invoiceUrl, 'Invoice for order ' . $orderNo);
            if (!$docOk) {
                Log::warning('WhatsAppService: order confirmation message sent but invoice attachment failed', [
                    'order_no' => $orderNo,
                    'invoice_url' => $invoiceUrl,
                ]);
            }
        } else {
            Log::warning('WhatsAppService: order confirmation sent without invoice attachment (missing URL)', [
                'order_no' => $orderNo,
            ]);
        }

        return $ok;
    }

    /** partner_otp_v_two / partner_reg_otp — {{1}} otp, {{2}} minutes */
    public function sendRegistrationOtp(string $mobile, string|int $otp, int $expiryMinutes = 10): bool
    {
        $minutes = $expiryMinutes > 0 ? $expiryMinutes : 10;

        return $this->sendNamedTemplate(
            $mobile,
            (string) config('services.jmd_whatsapp.templates.otp', 'partner_otp_v_two'),
            (string) config('services.jmd_whatsapp.templates.otp_id', '408238'),
            [
                'otp' => (string) $otp,
                'minutes' => (string) $minutes,
            ]
        );
    }

    /** payment_confirmation — Amount, details, transactionID (+ optional invoice PDF) */
    public function sendPaymentConfirmation(
        string $mobile,
        float|string $amount,
        string $details,
        string $transactionId,
        string $invoiceUrl = ''
    ): bool {
        $ok = $this->sendNamedTemplate(
            $mobile,
            (string) config('services.jmd_whatsapp.templates.payment', 'payment_confirmation'),
            (string) config('services.jmd_whatsapp.templates.payment_id', '364275'),
            [
                'Amount' => $this->money($amount) ?: '0',
                'details' => $details,
                'transactionID' => $transactionId,
            ]
        );

        if ($ok && trim($invoiceUrl) !== '') {
            $this->sendDocument($mobile, $invoiceUrl, 'Payment invoice ' . $transactionId);
        }

        return $ok;
    }

    /** renew_plan_confirmation — Date, Amount */
    public function sendRenewPlan(string $mobile, string $expiryDate, float|string $amount): bool
    {
        return $this->sendNamedTemplate(
            $mobile,
            (string) config('services.jmd_whatsapp.templates.renew', 'renew_plan_confirmation'),
            (string) config('services.jmd_whatsapp.templates.renew_id', '364278'),
            [
                'Date' => $expiryDate,
                'Amount' => $this->money($amount) ?: '0',
            ]
        );
    }

    /** upgrade_plan_confirm_v_two — plan, Amount, order */
    public function sendUpgrade(string $mobile, string $planName, float|string $amount, string $orderNo): bool
    {
        return $this->sendNamedTemplate(
            $mobile,
            (string) config('services.jmd_whatsapp.templates.upgrade', 'upgrade_plan_confirm_v_two'),
            (string) config('services.jmd_whatsapp.templates.upgrade_id', '408234'),
            [
                'plan' => $planName,
                'Amount' => $this->money($amount) ?: '0',
                'order' => $orderNo,
            ]
        );
    }

    /** downgrade_plan_confirmation — plan, Amount, order */
    public function sendDowngrade(string $mobile, string $planName, float|string $amount, string $orderNo): bool
    {
        return $this->sendNamedTemplate(
            $mobile,
            (string) config('services.jmd_whatsapp.templates.downgrade', 'downgrade_plan_confirmation'),
            (string) config('services.jmd_whatsapp.templates.downgrade_id', '408235'),
            [
                'plan' => $planName,
                'Amount' => $this->money($amount) ?: '0',
                'order' => $orderNo,
            ]
        );
    }

    /** order_cancellation — order, reason */
    public function sendOrderCancelled(string $mobile, string $orderNo, string $reason = 'Order cancelled'): bool
    {
        return $this->sendNamedTemplate(
            $mobile,
            (string) config('services.jmd_whatsapp.templates.cancel', 'order_cancellation'),
            (string) config('services.jmd_whatsapp.templates.cancel_id', '408236'),
            [
                'order' => $orderNo,
                'reason' => $reason,
            ]
        );
    }

    /**
     * Fetch templates from JMD.
     *
     * @return array{ok:bool,status:?string,message:mixed,raw:?array}
     */
    public function listTemplates(): array
    {
        if (!$this->isConfigured()) {
            return [
                'ok' => false,
                'status' => '0',
                'message' => 'WhatsApp is not configured or disabled.',
                'raw' => null,
            ];
        }

        $url = rtrim((string) config('services.jmd_whatsapp.base_url'), '/') . '/whatsapp/template/list';
        $timeout = (int) config('services.jmd_whatsapp.timeout', 20);

        try {
            $response = Http::asForm()
                ->timeout($timeout)
                ->acceptJson()
                ->post($url, [
                    'apiToken' => (string) config('services.jmd_whatsapp.api_token'),
                    'phone_number_id' => (string) config('services.jmd_whatsapp.phone_number_id'),
                ]);

            $json = $response->json();
            if (!is_array($json)) {
                $json = ['status' => '0', 'message' => $response->body()];
            }

            $status = (string) ($json['status'] ?? '');
            $ok = $response->successful() && ($status === '1' || $status === 'true');

            if (!$ok) {
                Log::warning('WhatsAppService: template list failed', [
                    'http' => $response->status(),
                    'response' => $json,
                ]);
            }

            return [
                'ok' => $ok,
                'status' => $status !== '' ? $status : ($ok ? '1' : '0'),
                'message' => $json['message'] ?? ($ok ? [] : 'Failed to fetch templates.'),
                'raw' => $json,
            ];
        } catch (\Throwable $e) {
            Log::warning('WhatsAppService: template list exception', [
                'message' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'status' => '0',
                'message' => $e->getMessage(),
                'raw' => null,
            ];
        }
    }

    /**
     * Send template via JMD /whatsapp/send/template.
     *
     * @param  array<string, scalar>  $namedVars  e.g. ['Amount' => '100', 'details' => '...']
     */
    public function sendNamedTemplate(
        string $mobile,
        string $templateName,
        string $botTemplateId,
        array $namedVars = [],
        ?string $documentUrl = null
    ): bool {
        if (!$this->isConfigured()) {
            Log::warning('WhatsAppService: not configured or disabled', [
                'template' => $templateName,
            ]);
            return false;
        }

        if (trim($botTemplateId) === '') {
            Log::warning('WhatsAppService: missing botTemplateID', [
                'template' => $templateName,
            ]);
            return false;
        }

        $normalized = $this->normalizeIndianMobile($mobile);
        if ($normalized === null) {
            Log::warning('WhatsAppService: invalid mobile number', [
                'mobile' => $mobile,
                'template' => $templateName,
            ]);
            return false;
        }

        $payload = [
            'apiToken' => (string) config('services.jmd_whatsapp.api_token'),
            'phoneNumberID' => (string) config('services.jmd_whatsapp.phone_number_id'),
            'botTemplateID' => $botTemplateId,
            'sendToPhoneNumber' => $normalized,
        ];

        $i = 1;
        foreach ($namedVars as $name => $value) {
            $payload['templateVariable-' . $name . '-' . $i] = (string) $value;
            $i++;
        }

        if (trim((string) $documentUrl) !== '') {
            $payload['media_url'] = trim((string) $documentUrl);
            $payload['media_type'] = 'document';
            $payload['templateHeader-document-1'] = trim((string) $documentUrl);
        }

        Log::info('WhatsAppService: sending template', [
            'template' => $templateName,
            'botTemplateID' => $botTemplateId,
            'number' => $normalized,
            'variables' => $namedVars,
            'url' => rtrim((string) config('services.jmd_whatsapp.base_url'), '/') . '/whatsapp/send/template',
        ]);

        return $this->postTemplate($payload, $templateName, $normalized);
    }

    /**
     * Send a simple text message via WhatsApp (fallback)
     */
    public function sendTextMessage(string $mobile, string $message): bool
    {
        if (!$this->isConfigured()) {
            Log::warning('WhatsAppService: not configured or disabled');
            return false;
        }

        $normalized = $this->normalizeIndianMobile($mobile);
        if ($normalized === null) {
            Log::warning('WhatsAppService: invalid mobile number', ['mobile' => $mobile]);
            return false;
        }

        $url = rtrim((string) config('services.jmd_whatsapp.base_url'), '/') . '/whatsapp/send/text';
        $timeout = (int) config('services.jmd_whatsapp.timeout', 20);

        try {
            $response = Http::asForm()
                ->timeout($timeout)
                ->acceptJson()
                ->post($url, [
                    'apiToken' => (string) config('services.jmd_whatsapp.api_token'),
                    'phone_number_id' => (string) config('services.jmd_whatsapp.phone_number_id'),
                    'sendToPhoneNumber' => $normalized,
                    'message' => $message,
                ]);

            $json = $response->json();
            $status = is_array($json) ? (string) ($json['status'] ?? '') : '';
            $ok = $response->successful() && ($status === '1' || $status === 'true');

            if ($ok) {
                Log::info('WhatsAppService: text message sent', [
                    'number' => $normalized,
                ]);
            } else {
                Log::warning('WhatsAppService: text message failed', [
                    'number' => $normalized,
                    'response' => $json,
                ]);
            }

            return $ok;
        } catch (\Throwable $e) {
            Log::warning('WhatsAppService: text message exception', [
                'number' => $normalized,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function isConfigured(): bool
    {
        if (!(bool) config('services.jmd_whatsapp.enabled', true)) {
            return false;
        }

        $token = trim((string) config('services.jmd_whatsapp.api_token', ''));
        $phoneId = trim((string) config('services.jmd_whatsapp.phone_number_id', ''));
        $base = trim((string) config('services.jmd_whatsapp.base_url', ''));

        return $token !== '' && $phoneId !== '' && $base !== '';
    }

    /**
     * @param  array<string, scalar>  $payload
     */
    private function postTemplate(array $payload, string $label, string $number): bool
    {
        $url = rtrim((string) config('services.jmd_whatsapp.base_url'), '/') . '/whatsapp/send/template';
        $timeout = (int) config('services.jmd_whatsapp.timeout', 20);

        try {
            $response = Http::asForm()
                ->timeout($timeout)
                ->acceptJson()
                ->post($url, $payload);

            if (!$response->successful()) {
                Log::warning('WhatsAppService: HTTP error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'label' => $label,
                    'number' => $number,
                ]);
                return false;
            }

            $json = $response->json();
            $status = is_array($json) ? (string) ($json['status'] ?? '') : '';
            if ($status !== '1' && $status !== 'true') {
                Log::warning('WhatsAppService: API rejected send', [
                    'response' => $json,
                    'label' => $label,
                    'number' => $number,
                ]);
                return false;
            }

            Log::info('WhatsAppService: template sent successfully', [
                'label' => $label,
                'number' => $number,
                'response' => $json,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('WhatsAppService: dispatch exception', [
                'message' => $e->getMessage(),
                'label' => $label,
                'number' => $number,
            ]);
            return false;
        }
    }

    /**
     * Send invoice/document as WhatsApp media message.
     */
    public function sendDocument(string $mobile, string $mediaUrl, string $caption = ''): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $normalized = $this->normalizeIndianMobile($mobile);
        if ($normalized === null || trim($mediaUrl) === '') {
            return false;
        }

        $url = rtrim((string) config('services.jmd_whatsapp.base_url'), '/') . '/whatsapp/send/file';
        $timeout = (int) config('services.jmd_whatsapp.timeout', 20);

        try {
            $response = Http::asForm()
                ->timeout($timeout)
                ->acceptJson()
                ->post($url, [
                    'apiToken' => (string) config('services.jmd_whatsapp.api_token'),
                    'phone_number_id' => (string) config('services.jmd_whatsapp.phone_number_id'),
                    'phone_number' => $normalized,
                    'media_url' => $mediaUrl,
                    'media_type' => 'document',
                    'media_caption_text' => $caption,
                ]);

            $json = $response->json();
            $status = is_array($json) ? (string) ($json['status'] ?? '') : '';
            $ok = $response->successful() && ($status === '1' || $status === 'true');

            if ($ok) {
                Log::info('WhatsAppService: document sent successfully', [
                    'number' => $normalized,
                    'media_url' => $mediaUrl,
                ]);
            } else {
                Log::warning('WhatsAppService: document send failed', [
                    'number' => $normalized,
                    'media_url' => $mediaUrl,
                    'http' => $response->status(),
                    'response' => $json,
                ]);
            }

            return $ok;
        } catch (\Throwable $e) {
            Log::warning('WhatsAppService: document send exception', [
                'number' => $normalized,
                'media_url' => $mediaUrl,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function normalizeIndianMobile(string $mobile): ?string
    {
        $digits = preg_replace('/\D+/', '', $mobile) ?? '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '91') && strlen($digits) === 12) {
            return $digits;
        }

        if (strlen($digits) === 10) {
            return '91' . $digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '91' . substr($digits, 1);
        }

        if (strlen($digits) >= 10 && strlen($digits) <= 15) {
            return $digits;
        }

        return null;
    }

    private function money(float|string|null $amount): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }

        if (is_string($amount) && !is_numeric($amount)) {
            return trim($amount);
        }

        return number_format((float) $amount, 2, '.', '');
    }
}