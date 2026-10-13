<?php

namespace App\Services;

use App\Http\Controllers\SuperAdmin\BillingController;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderPoDetail;
use App\Models\PartnerAadhaarVerification;
use App\Support\AadhaarVerificationGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class PartnerAadhaarVerificationService
{
    /**
     * Cart checkout OTP verify — same gateway/conditions as BillingController::aadharVerifyOtp.
     *
     * @return array{ok: bool, message: string, mock: bool, ref_no: string, mode: string, aadhaar_verification_id: int|null}
     */
    public function verify(string $aadhaarNumber, string $otp, int $cartId): array
    {
        $contextKey = 'cart:' . $cartId;
        $record = $this->getLatestForCart($cartId);
        $refNo = (string) ($record['aadhaar_ref_no'] ?? '');
        if ($refNo === '') {
            $refNo = (string) Cache::get('aadhaar_ref_' . $cartId);
        }
        $aadhaarDigits = preg_replace('/\D/', '', $aadhaarNumber) ?? '';
        if ($record !== null && strlen($aadhaarDigits) !== 12) {
            $aadhaarNumber = $record['aadhaar_number'];
        }

        $gateway = app(AadhaarVerificationGateway::class);
        $verify = $gateway->verifyOtp($aadhaarNumber, $otp, $contextKey, $refNo);

        $verificationId = null;
        if (!empty($verify['ok'])) {
            $verificationId = $this->saveOtpForCart(
                $cartId,
                $aadhaarNumber,
                $otp,
                $refNo !== '' ? $refNo : (string) ($verify['ref_no'] ?? ''),
                (string) ($verify['mode'] ?? 'test')
            );
        }

        return [
            'ok' => (bool) $verify['ok'],
            'message' => (string) ($verify['message'] ?? ''),
            'mock' => ($verify['mode'] ?? '') === 'test',
            'ref_no' => $refNo,
            'mode' => (string) ($verify['mode'] ?? ''),
            'aadhaar_verification_id' => $verificationId,
        ];
    }

    /**
     * Cart Aadhaar init — gateway + DB row (customer_id, cart_id, aadhaar_number).
     *
     * @return array{ok: bool, message: string, masked: string, ref_no: string, mode: string, aadhaar_verification_id: int|null}
     */
    public function initForCart(string $aadhaarNumber, int $cartId, ?int $customerIdOverride = null): array
    {
        $aadhaar = preg_replace('/\D/', '', $aadhaarNumber) ?? '';
        if (strlen($aadhaar) !== 12) {
            return [
                'ok' => false,
                'message' => 'Aadhaar number must be 12 digits.',
                'masked' => '',
                'ref_no' => '',
                'mode' => '',
                'aadhaar_verification_id' => null,
            ];
        }

        // Save aadhaar_number to DB first (init step).
        $verificationId = $this->saveInitForCart(
            $cartId,
            $aadhaar,
            '',
            (string) config('services.aadhaar.mode', 'test'),
            $customerIdOverride
        );

        Cache::put('aadhaar_number_' . $cartId, $aadhaar, now()->addMinutes(30));

        $gateway = app(AadhaarVerificationGateway::class);
        $result = $gateway->init($aadhaarNumber, 'cart:' . $cartId);

        if (!empty($result['ref_no'])) {
            Cache::put('aadhaar_ref_' . $cartId, (string) $result['ref_no'], now()->addMinutes(30));
        }

        if ($verificationId && !empty($result['ref_no'])) {
            PartnerAadhaarVerification::query()->whereKey($verificationId)->update([
                'aadhaar_ref_no' => (string) $result['ref_no'],
                'verification_mode' => (string) ($result['mode'] ?? 'test'),
                'aadhaar_number' => $aadhaar,
                'aadhaar_masked' => 'XXXXXXXX' . substr($aadhaar, -4),
            ]);
        }

        $result['aadhaar_verification_id'] = $verificationId;

        if (empty($result['ok'])) {
            return $result;
        }

        if (!$verificationId) {
            $result['ok'] = false;
            $result['message'] = 'Aadhaar number could not be saved. Add customer to cart or send customer_id in the request.';
        }

        return $result;
    }

    /**
     * Latest Aadhaar row for cart (init / verify) — used on OTP verify + checkout.
     *
     * @return array{
     *   id: int,
     *   customer_id: int,
     *   partner_id: int|null,
     *   cart_id: int,
     *   aadhaar_number: string,
     *   aadhaar_ref_no: string,
     *   status: string
     * }|null
     */
    public function getLatestForCart(int $cartId): ?array
    {
        $row = PartnerAadhaarVerification::query()
            ->where('cart_id', $cartId)
            ->orderByDesc('id')
            ->first();

        if (!$row) {
            return null;
        }

        $aadhaarNumber = preg_replace('/\D/', '', (string) ($row->aadhaar_number ?? '')) ?? '';
        if (strlen($aadhaarNumber) !== 12) {
            return null;
        }

        $customerId = (int) ($row->customer_id ?? 0);
        if ($customerId <= 0) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'customer_id' => $customerId,
            'partner_id' => isset($row->partner_id) && (int) $row->partner_id > 0
                ? (int) $row->partner_id
                : null,
            'cart_id' => (int) ($row->cart_id ?? $cartId),
            'aadhaar_number' => $aadhaarNumber,
            'aadhaar_ref_no' => (string) ($row->aadhaar_ref_no ?? ''),
            'status' => (string) ($row->status ?? ''),
        ];
    }

    /**
     * On init: customer_id + aadhaar_number + cart_id (orders table मध्ये cart_id नाही).
     */
    public function saveInitForCart(int $cartId, string $aadhaarNumber, string $refNo, string $mode, ?int $customerIdOverride = null): ?int
    {
        $cart = Cart::query()->find($cartId);
        if (!$cart) {
            return null;
        }

        $customerId = (int) ($cart->customer_id ?? 0);
        if ($customerId <= 0 && $customerIdOverride !== null && $customerIdOverride > 0) {
            $customerId = $customerIdOverride;
        }
        if ($customerId <= 0) {
            Log::warning('partner_aadhaar_verifications: cart has no customer_id', ['cart_id' => $cartId]);

            return null;
        }

        $aadhaarNumber = preg_replace('/\D/', '', $aadhaarNumber) ?? '';
        if (strlen($aadhaarNumber) !== 12) {
            return null;
        }

        $masked = 'XXXXXXXX' . substr($aadhaarNumber, -4);
        $partnerId = (int) ($cart->partner_id ?? 0);

        $existing = PartnerAadhaarVerification::query()
            ->where('cart_id', $cartId)
            ->whereIn('status', [PartnerAadhaarVerification::STATUS_INIT, PartnerAadhaarVerification::STATUS_VERIFIED])
            ->orderByDesc('id')
            ->first();

        $payload = [
            'customer_id' => $customerId,
            'partner_id' => $partnerId > 0 ? $partnerId : null,
            'cart_id' => $cartId,
            'aadhaar_number' => $aadhaarNumber,
            'aadhaar_masked' => $masked,
            'aadhaar_ref_no' => $refNo,
            'verification_mode' => $mode,
            'status' => PartnerAadhaarVerification::STATUS_INIT,
        ];

        if ($existing) {
            $existing->update($payload);

            return (int) $existing->id;
        }

        return (int) PartnerAadhaarVerification::query()->create($payload)->id;
    }

    /**
     * After OTP verify: save otp + verified_at.
     */
    public function saveOtpForCart(int $cartId, string $aadhaarNumber, string $otp, string $refNo, string $mode): ?int
    {
        $aadhaarNumber = preg_replace('/\D/', '', $aadhaarNumber) ?? '';
        $otp = preg_replace('/\D/', '', $otp) ?? '';
        if (strlen($aadhaarNumber) !== 12 || strlen($otp) !== 6) {
            return null;
        }

        $row = PartnerAadhaarVerification::query()
            ->where('cart_id', $cartId)
            ->orderByDesc('id')
            ->first();

        $verificationId = $row ? (int) $row->id : $this->saveInitForCart($cartId, $aadhaarNumber, $refNo, $mode);
        if (!$verificationId) {
            return null;
        }

        $otpPayload = [
            'aadhaar_number' => $aadhaarNumber,
            'aadhaar_masked' => 'XXXXXXXX' . substr($aadhaarNumber, -4),
            'otp' => $otp,
            'verification_mode' => $mode,
            'status' => PartnerAadhaarVerification::STATUS_VERIFIED,
            'verified_at' => now(),
        ];
        if ($refNo !== '') {
            $otpPayload['aadhaar_ref_no'] = $refNo;
        }
        PartnerAadhaarVerification::query()->whereKey($verificationId)->update($otpPayload);

        return $verificationId;
    }

    /**
     * Superadmin legacy/manual order: store Aadhaar without OTP or external verification APIs.
     */
    public function saveLegacyManualForOrder(int $partnerId, int $customerId, int $orderId, string $aadhaarNumber): ?int
    {
        if ($orderId <= 0 || $customerId <= 0) {
            return null;
        }

        $aadhaarNumber = preg_replace('/\D/', '', $aadhaarNumber) ?? '';
        if ($aadhaarNumber === '') {
            return null;
        }

        $masked = strlen($aadhaarNumber) === 12
            ? 'XXXXXXXX' . substr($aadhaarNumber, -4)
            : (strlen($aadhaarNumber) > 4
                ? str_repeat('X', strlen($aadhaarNumber) - 4) . substr($aadhaarNumber, -4)
                : $aadhaarNumber);

        $now = now();
        $payload = [
            'customer_id' => $customerId,
            'partner_id' => $partnerId > 0 ? $partnerId : null,
            'order_id' => $orderId,
            'aadhaar_number' => $aadhaarNumber,
            'aadhaar_masked' => $masked,
            'verification_mode' => 'legacy_manual',
            'status' => PartnerAadhaarVerification::STATUS_ORDER_LINKED,
            'verified_at' => $now,
            'aadhaar_ref_no' => 'legacy-manual-order-' . $orderId,
        ];

        $existing = PartnerAadhaarVerification::query()
            ->where('order_id', $orderId)
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            $existing->update($payload);
            $verificationId = (int) $existing->id;
        } else {
            $verificationId = (int) PartnerAadhaarVerification::query()->create($payload)->id;
        }

        Order::query()->whereKey($orderId)->update([
            'aadhaar_verification_id' => $verificationId,
        ]);

        return $verificationId > 0 ? $verificationId : null;
    }

    /**
     * After orders created: cart_id + primary order_id on aadhaar table; orders.aadhaar_verification_id.
     *
     * @param  list<int>  $orderIds
     */
    public function linkOrdersAfterCheckout(int $verificationId, int $cartId, int $primaryOrderId, array $orderIds): void
    {
        if ($verificationId <= 0) {
            return;
        }

        PartnerAadhaarVerification::query()->whereKey($verificationId)->update([
            'cart_id' => $cartId,
            'order_id' => $primaryOrderId > 0 ? $primaryOrderId : null,
            'status' => PartnerAadhaarVerification::STATUS_ORDER_LINKED,
        ]);

        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        if ($orderIds === []) {
            return;
        }

        Order::query()
            ->whereIn('id', $orderIds)
            ->update(['aadhaar_verification_id' => $verificationId]);
    }

    /**
     * After orders exist: mark PO Aadhaar verified + finalize (mirrors BillingController::aadharVerifyOtp).
     */
    public function finalizeOrderAfterOtp(
        int $orderId,
        string $aadhaarNumber,
        string $refNo,
        ?int $aadhaarVerificationId = null,
        bool $skipCreditChecks = false
    ): void {
        if ($orderId <= 0) {
            return;
        }

        $aadhaarNumber = preg_replace('/\D/', '', $aadhaarNumber) ?? '';
        $masked = strlen($aadhaarNumber) === 12
            ? 'XXXXXXXX' . substr($aadhaarNumber, -4)
            : 'XXXXXXXXXXXX';

        app(BillingController::class)->completeOrderAfterAadhaarVerification(
            $orderId,
            $refNo,
            $masked,
            $aadhaarVerificationId,
            $skipCreditChecks
        );
    }

    public function initForOrder(Request $request, BillingController $billing): \Illuminate\Http\JsonResponse
    {
        return $billing->aadharVerifyInit($request);
    }

    public function verifyForOrder(Request $request, BillingController $billing): \Illuminate\Http\JsonResponse
    {
        return $billing->aadharVerifyOtp($request);
    }

    /**
     * Renewal Aadhaar init — no cart, no new order yet. Persist Aadhaar with the
     * source-subscription's customer; subscription↔verification map lives in cache
     * so OTP verify can find it back without polluting cart_id/order_id columns.
     *
     * @return array{ok: bool, message: string, masked: string, ref_no: string, mode: string, aadhaar_verification_id: int|null}
     */
    public function initForRenewal(int $subscriptionId, string $aadhaarNumber, int $partnerId, int $customerId): array
    {
        $aadhaar = preg_replace('/\D/', '', $aadhaarNumber) ?? '';
        if (strlen($aadhaar) !== 12) {
            return [
                'ok' => false,
                'message' => 'Aadhaar number must be 12 digits.',
                'masked' => '',
                'ref_no' => '',
                'mode' => '',
                'aadhaar_verification_id' => null,
            ];
        }

        $masked = 'XXXXXXXX' . substr($aadhaar, -4);

        // Reuse any existing pending row for this subscription (re-init scenario).
        $existing = PartnerAadhaarVerification::query()
            ->where('customer_id', $customerId)
            ->where('verification_mode', 'renewal_pending')
            ->whereNull('order_id')
            ->orderByDesc('id')
            ->first();

        $payload = [
            'customer_id' => $customerId,
            'partner_id' => $partnerId > 0 ? $partnerId : null,
            'cart_id' => null,
            'order_id' => null,
            'aadhaar_number' => $aadhaar,
            'aadhaar_masked' => $masked,
            'verification_mode' => 'renewal_pending',
            'status' => PartnerAadhaarVerification::STATUS_INIT,
        ];

        if ($existing) {
            $existing->update($payload);
            $verificationId = (int) $existing->id;
        } else {
            $verificationId = (int) PartnerAadhaarVerification::query()->create($payload)->id;
        }

        $gateway = app(\App\Support\AadhaarVerificationGateway::class);
        $result = $gateway->init($aadhaarNumber, 'renew:' . $subscriptionId);
        $refNo = (string) ($result['ref_no'] ?? '');
        $mode = (string) ($result['mode'] ?? 'test');

        if ($refNo !== '') {
            PartnerAadhaarVerification::query()->whereKey($verificationId)->update([
                'aadhaar_ref_no' => $refNo,
                'verification_mode' => 'renewal_pending',
            ]);
            Cache::put('aadhaar_ref_renew_' . $subscriptionId, $refNo, now()->addMinutes(30));
        }

        Cache::put('aadhaar_renew_' . $subscriptionId, [
            'verification_id' => $verificationId,
            'subscription_id' => $subscriptionId,
            'aadhaar_number' => $aadhaar,
            'aadhaar_ref_no' => $refNo,
            'customer_id' => $customerId,
            'partner_id' => $partnerId,
        ], now()->addMinutes(30));

        return [
            'ok' => (bool) ($result['ok'] ?? false),
            'message' => (string) ($result['message'] ?? 'Aadhaar init successful.'),
            'masked' => $masked,
            'ref_no' => $refNo,
            'mode' => $mode,
            'aadhaar_verification_id' => $verificationId > 0 ? $verificationId : null,
        ];
    }

    /**
     * @return array{verification_id:int, aadhaar_number:string, aadhaar_ref_no:string, customer_id:int, partner_id:int}|null
     */
    public function getLatestForRenewal(int $subscriptionId): ?array
    {
        $cached = Cache::get('aadhaar_renew_' . $subscriptionId);
        if (is_array($cached) && !empty($cached['verification_id'])) {
            return $this->normalizeRenewalCacheRow($cached);
        }

        // Fallback: cache may have expired but the DB row is still around.
        $customerId = 0;
        if ($subscriptionId > 0) {
            $customerId = (int) (Order::query()->whereKey($subscriptionId)->value('user_id') ?? 0);
        }

        $fallbackQuery = PartnerAadhaarVerification::query()
            ->where('verification_mode', 'renewal_pending')
            ->whereNull('order_id');
        if ($customerId > 0) {
            $fallbackQuery->where('customer_id', $customerId);
        }
        $row = $fallbackQuery->orderByDesc('id')->first();

        if (!$row) {
            return null;
        }

        return [
            'verification_id' => (int) $row->id,
            'aadhaar_number' => (string) ($row->aadhaar_number ?? ''),
            'aadhaar_ref_no' => (string) ($row->aadhaar_ref_no ?? ''),
            'customer_id' => (int) ($row->customer_id ?? 0),
            'partner_id' => (int) ($row->partner_id ?? 0),
        ];
    }

    /**
     * OTP verify for a renewal Aadhaar (no order yet).
     *
     * @return array{ok: bool, message: string, mock: bool, ref_no: string, mode: string, aadhaar_verification_id: int|null}
     */
    public function verifyForRenewal(int $subscriptionId, string $aadhaarNumber, string $otp): array
    {
        $contextKey = 'renew:' . $subscriptionId;
        $cached = $this->getLatestForRenewal($subscriptionId);
        $refNo = (string) ($cached['aadhaar_ref_no'] ?? '');
        if ($refNo === '') {
            $refNo = (string) Cache::get('aadhaar_ref_renew_' . $subscriptionId, '');
        }
        $verificationId = (int) ($cached['verification_id'] ?? 0);

        $aadhaarDigits = preg_replace('/\D/', '', $aadhaarNumber) ?? '';
        if (strlen($aadhaarDigits) !== 12 && !empty($cached['aadhaar_number'])) {
            $aadhaarNumber = (string) $cached['aadhaar_number'];
        }

        $gateway = app(\App\Support\AadhaarVerificationGateway::class);
        $verify = $gateway->verifyOtp($aadhaarNumber, $otp, $contextKey, $refNo);

        if (!empty($verify['ok']) && $verificationId > 0) {
            PartnerAadhaarVerification::query()->whereKey($verificationId)->update([
                'otp' => preg_replace('/\D/', '', (string) $otp) ?? '',
                'aadhaar_ref_no' => $refNo,
                'verification_mode' => 'renewal_verified',
                'status' => PartnerAadhaarVerification::STATUS_VERIFIED,
                'verified_at' => now(),
            ]);
        }

        return [
            'ok' => (bool) ($verify['ok'] ?? false),
            'message' => (string) ($verify['message'] ?? ''),
            'mock' => ($verify['mode'] ?? '') === 'test',
            'ref_no' => $refNo,
            'mode' => (string) ($verify['mode'] ?? ''),
            'aadhaar_verification_id' => $verificationId > 0 ? $verificationId : null,
        ];
    }

    /**
     * After the renewal order is created, link Aadhaar row ↔ new order_id.
     */
    public function linkRenewalAfterOrderCreated(int $verificationId, int $newOrderId): void
    {
        if ($verificationId <= 0 || $newOrderId <= 0) {
            return;
        }

        PartnerAadhaarVerification::query()->whereKey($verificationId)->update([
            'order_id' => $newOrderId,
            'verification_mode' => 'renewal_verified',
            'status' => PartnerAadhaarVerification::STATUS_ORDER_LINKED,
        ]);

        Order::query()->whereKey($newOrderId)->update([
            'aadhaar_verification_id' => $verificationId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{verification_id:int, aadhaar_number:string, aadhaar_ref_no:string, customer_id:int, partner_id:int}
     */
    private function normalizeRenewalCacheRow(array $row): array
    {
        return [
            'verification_id' => (int) ($row['verification_id'] ?? 0),
            'aadhaar_number' => (string) ($row['aadhaar_number'] ?? ''),
            'aadhaar_ref_no' => (string) ($row['aadhaar_ref_no'] ?? ''),
            'customer_id' => (int) ($row['customer_id'] ?? 0),
            'partner_id' => (int) ($row['partner_id'] ?? 0),
        ];
    }

    /**
     * Generated-order flow: save encrypted Aadhaar + send OTP.
     *
     * @return array{ok: bool, message: string, masked: string, ref_no: string, mode: string, aadhaar_verification_id: int|null}
     */
    public function initForGeneratedOrder(int $orderId, string $aadhaarNumber): array
    {
        $aadhaar = preg_replace('/\D/', '', $aadhaarNumber) ?? '';
        if (strlen($aadhaar) !== 12) {
            return [
                'ok' => false,
                'message' => 'Aadhaar number must be 12 digits.',
                'masked' => '',
                'ref_no' => '',
                'mode' => '',
                'aadhaar_verification_id' => null,
            ];
        }

        $order = Order::query()->find($orderId);
        if (!$order) {
            return [
                'ok' => false,
                'message' => 'Order not found.',
                'masked' => '',
                'ref_no' => '',
                'mode' => '',
                'aadhaar_verification_id' => null,
            ];
        }

        $customerId = (int) ($order->user_id ?? 0);
        if ($customerId <= 0) {
            return [
                'ok' => false,
                'message' => 'Order has no linked customer.',
                'masked' => '',
                'ref_no' => '',
                'mode' => '',
                'aadhaar_verification_id' => null,
            ];
        }

        $partnerId = (int) ($order->partner_id ?? 0);
        $masked = 'XXXXXXXX' . substr($aadhaar, -4);
        $encryptedAadhaar = Crypt::encryptString($aadhaar);

        $existing = PartnerAadhaarVerification::query()
            ->where('order_id', $orderId)
            ->whereIn('status', [PartnerAadhaarVerification::STATUS_INIT, PartnerAadhaarVerification::STATUS_VERIFIED])
            ->orderByDesc('id')
            ->first();

        $payload = [
            'customer_id' => $customerId,
            'partner_id' => $partnerId > 0 ? $partnerId : null,
            'order_id' => $orderId,
            'aadhaar_number' => $encryptedAadhaar,
            'aadhaar_masked' => $masked,
            'verification_mode' => (string) config('services.aadhaar.mode', 'test'),
            'status' => PartnerAadhaarVerification::STATUS_INIT,
        ];

        if ($existing) {
            $existing->update($payload);
            $verificationId = (int) $existing->id;
        } else {
            $verificationId = (int) PartnerAadhaarVerification::query()->create($payload)->id;
        }

        Cache::put('aadhaar_number_' . $orderId, $aadhaar, now()->addMinutes(30));

        $gateway = app(AadhaarVerificationGateway::class);
        $result = $gateway->init($aadhaar, 'order:' . $orderId);
        $refNo = (string) ($result['ref_no'] ?? '');
        $mode = (string) ($result['mode'] ?? 'test');

        $result['aadhaar_verification_id'] = $verificationId > 0 ? $verificationId : null;

        $existingRefNo = (string) Cache::get('aadhaar_ref_' . $orderId, '');

        // Some gateway responses indicate cooldown ("try after 45 seconds") without returning reference_id.
        // In that case, keep the active ref so verification can continue instead of reporting a hard failure.
        if (!empty($result['ok']) && $refNo === '' && $existingRefNo !== '') {
            $gatewayMessage = strtolower(trim((string) ($result['message'] ?? '')));
            if (
                str_contains($gatewayMessage, 'try after')
                || str_contains($gatewayMessage, 'already generated')
                || str_contains($gatewayMessage, 'already sent')
            ) {
                $refNo = $existingRefNo;
                $result['ref_no'] = $existingRefNo;
                $result['ok'] = true;
                if (empty($result['message'])) {
                    $result['message'] = 'OTP already sent. Please use the latest OTP.';
                }
            }
        }

        // OTP / provider error → do not advance PO journey or wipe ref
        if (empty($result['ok']) || $refNo === '') {
            if (empty($result['message'])) {
                $result['message'] = 'Failed to send OTP. Please try again.';
            }
            $result['ok'] = false;
            $result['ref_no'] = '';
            return $result;
        }

        Cache::put('aadhaar_ref_' . $orderId, $refNo, now()->addMinutes(30));
        PartnerAadhaarVerification::query()->whereKey($verificationId)->update([
            'aadhaar_ref_no' => $refNo,
            'verification_mode' => $mode,
        ]);

        OrderPoDetail::query()->updateOrCreate(
            ['order_id' => $orderId],
            [
                'aadhaar_masked' => $masked,
                'aadhaar_ref_no' => $refNo,
                'aadhar_verified' => 0,
                'journey_status' => 'po_pending',
                'status' => 'pending',
            ]
        );

        Order::query()->whereKey($orderId)->update([
            'aadhaar_verification_id' => $verificationId,
        ]);

        if ($verificationId <= 0) {
            $result['ok'] = false;
            $result['message'] = 'Aadhaar number could not be saved.';
        }

        return $result;
    }

    /**
     * Generated-order OTP verify — encrypted Aadhaar persisted in DB.
     *
     * @return array{ok: bool, message: string, mock: bool, ref_no: string, mode: string, aadhaar_verification_id: int|null, aadhaar_masked: string}
     */
    public function verifyOtpForGeneratedOrder(int $orderId, string $otp, ?string $aadhaarNumber = null): array
    {
        $verificationId = 0;
        // Active OTP session lives in cache. DB ref is only for audit — using it after
        // expiry causes endless "OTP expired" on the same dead reference_id.
        $refNo = (string) Cache::get('aadhaar_ref_' . $orderId, '');

        $row = PartnerAadhaarVerification::query()
            ->where('order_id', $orderId)
            ->orderByDesc('id')
            ->first();

        if ($row) {
            $verificationId = (int) $row->id;
        }

        $aadhaarDigits = preg_replace('/\D/', '', (string) $aadhaarNumber) ?? '';
        if (strlen($aadhaarDigits) !== 12 && $row) {
            $stored = (string) ($row->aadhaar_number ?? '');
            $plainDigits = preg_replace('/\D/', '', $stored) ?? '';
            if (strlen($plainDigits) === 12) {
                $aadhaarDigits = $plainDigits;
            } else {
                try {
                    $aadhaarDigits = preg_replace('/\D/', '', Crypt::decryptString($stored)) ?? '';
                } catch (\Throwable) {
                    $aadhaarDigits = '';
                }
            }
        }
        if (strlen($aadhaarDigits) !== 12) {
            $aadhaarDigits = preg_replace('/\D/', '', (string) Cache::get('aadhaar_number_' . $orderId, '')) ?? '';
        }
        if (strlen($aadhaarDigits) !== 12) {
            return [
                'ok' => false,
                'message' => 'Aadhaar number must be 12 digits. Send OTP init first.',
                'mock' => false,
                'ref_no' => $refNo,
                'mode' => '',
                'aadhaar_verification_id' => null,
                'aadhaar_masked' => '',
            ];
        }

        $gateway = app(AadhaarVerificationGateway::class);
        $otpDigits = preg_replace('/\D/', '', $otp) ?? '';
        if ($otpDigits === '' && $gateway->isTestMode()) {
            $otpDigits = preg_replace('/\D/', '', (string) (config('services.aadhaar.mock_otps')[0] ?? '123456')) ?? '';
        }
        if (strlen($otpDigits) !== 6) {
            return [
                'ok' => false,
                'message' => 'Please enter a valid 6-digit OTP.',
                'mock' => false,
                'ref_no' => $refNo,
                'mode' => '',
                'aadhaar_verification_id' => null,
                'aadhaar_masked' => '',
            ];
        }

        $verify = $gateway->verifyOtp($aadhaarDigits, $otpDigits, 'order:' . $orderId, $refNo);
        $masked = 'XXXXXXXX' . substr($aadhaarDigits, -4);

        if (empty($verify['ok'])) {
            $failMsg = (string) ($verify['message'] ?? '');
            if ($verificationId > 0 && stripos($failMsg, 'expired') !== false) {
                PartnerAadhaarVerification::query()->whereKey($verificationId)->update([
                    'aadhaar_ref_no' => null,
                ]);
                Cache::forget('aadhaar_ref_' . $orderId);
            }

            return [
                'ok' => false,
                'message' => $failMsg !== '' ? $failMsg : 'OTP verification failed.',
                'mock' => ($verify['mode'] ?? '') === 'test',
                'ref_no' => $refNo,
                'mode' => (string) ($verify['mode'] ?? ''),
                'aadhaar_verification_id' => $verificationId > 0 ? $verificationId : null,
                'aadhaar_masked' => $masked,
            ];
        }

        if ($verificationId > 0) {
            PartnerAadhaarVerification::query()->whereKey($verificationId)->update([
                'aadhaar_number' => Crypt::encryptString($aadhaarDigits),
                'aadhaar_masked' => $masked,
                'otp' => $otpDigits,
                'aadhaar_ref_no' => $refNo !== '' ? $refNo : (string) ($verify['ref_no'] ?? ''),
                'verification_mode' => (string) ($verify['mode'] ?? 'test'),
                'status' => PartnerAadhaarVerification::STATUS_ORDER_LINKED,
                'verified_at' => now(),
            ]);
        }

        return [
            'ok' => true,
            'message' => (string) ($verify['message'] ?? ''),
            'mock' => ($verify['mode'] ?? '') === 'test',
            'ref_no' => $refNo,
            'mode' => (string) ($verify['mode'] ?? ''),
            'aadhaar_verification_id' => $verificationId > 0 ? $verificationId : null,
            'aadhaar_masked' => $masked,
        ];
    }
}
