<?php

namespace App\Services;

use App\Mail\PurchaseRequestApprovedMail;
use App\Models\Cart;
use App\Models\Notification;
use App\Models\Plan;
use App\Models\PurchaseRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use App\Models\OrderItem;

class PurchaseRequestService
{
    /**
     * Link existing partner cart and submit purchase request to SuperAdmin.
     */
    public function createPurchaseRequest(array $data): PurchaseRequest
    {
        $partnerId = (int) $data['partner_id'];
        $cartId = (int) ($data['main_cart_id'] ?? $data['cart_id'] ?? 0);
        if ($cartId <= 0) {
            throw new \InvalidArgumentException('main_cart_id or cart_id is required.');
        }

        $cart = Cart::query()
            ->with(['items.plan'])
            ->where('id', $cartId)
            ->where('partner_id', $partnerId)
            ->first();

        if (!$cart) {
            throw new \InvalidArgumentException('Cart not found for this partner.');
        }

        $firstItem = $cart->items->first();
        $planId = (int) ($data['plan_id'] ?? $firstItem?->plan_id ?? 0);
        $customerId = !empty($data['customer_id'])
            ? (int) $data['customer_id']
            : ((int) ($cart->customer_id ?? 0) ?: null);
        $orderId = !empty($data['order_id'])
            ? (int) $data['order_id']
            : ((int) ($cart->order_id ?? 0) ?: null);
        $orderSubId = !empty($data['order_sub_id']) ? (int) $data['order_sub_id'] : $this->resolveCartOrderSubId($cart);
        $licenses = max(1, (int) ($data['licenses'] ?? $data['license_count'] ?? $firstItem?->licenses ?? 1));
        $requestType = strtolower(trim((string) ($data['request_type'] ?? $data['action'] ?? $cart->type ?? Cart::TYPE_UPGRADE)));
        $purchaseRequestType = $requestType;
        if (str_contains(strtolower((string) ($cart->item_type ?? '')), 'mode:partial')) {
            $purchaseRequestType = 'partial-upgrade';
        }
        $remarks = trim((string) ($data['remarks'] ?? $data['message'] ?? ''));

        $plan = $planId > 0 ? Plan::find($planId) : $firstItem?->plan;
        $providerId = (int) ($plan->provider_id ?? $cart->provider_id ?? 0);

        // Provider-wise request validation rules:
        // 1. Provider 2 (Microsoft 365): Request not allowed (Direct cart checkout)
        if ($providerId === 2) {
            throw new \InvalidArgumentException('Microsoft 365 plans do not require a request. Direct cart checkout is available.');
        }

        $cart->status = 'pending_approval';
        if ($customerId) {
            $cart->customer_id = $customerId;
        }
        if ($orderId) {
            $cart->order_id = $orderId;
        }
        if ($requestType !== '') {
            $cart->type = $requestType;
        }
        $cart->save();

        $requestRecord = PurchaseRequest::create([
            'partner_id'   => $partnerId,
            'customer_id'  => $customerId,
            'order_id'     => $orderId,
            'order_sub_id' => $orderSubId,
            'plan_id'      => $planId > 0 ? $planId : null,
            'cart_id'      => $cartId,
            'request_type' => $purchaseRequestType,
            'licenses'     => $licenses,
            'status'       => PurchaseRequest::STATUS_PENDING,
            'remarks'      => $remarks,
        ]);

        return $requestRecord;
    }

    /**
     * Approve purchase request by SuperAdmin and send checkout link email to partner.
     */
    public function approvePurchaseRequest(int $requestId, ?string $adminNotes = null): array
    {
        $purchaseRequest = PurchaseRequest::with(['partner', 'plan.provider', 'cart.items', 'customer'])->find($requestId);
        
        if (!$purchaseRequest) {
            throw new \InvalidArgumentException('Purchase request not found.');
        }

        if ($purchaseRequest->status === PurchaseRequest::STATUS_APPROVED) {
            throw new \LogicException('Purchase request has already been approved.');
        }

        // 1. Update purchase request status
        $purchaseRequest->status = PurchaseRequest::STATUS_APPROVED;
        $purchaseRequest->approved_at = now();
        $purchaseRequest->admin_notes = $adminNotes;
        $purchaseRequest->save();

        // 2. Activate temporary cart for partner checkout
        $cart = $purchaseRequest->cart;
        if ($cart) {
            $cart->status = Cart::STATUS_ACTIVE;
            $cart->save();
        }

        // 3. Generate checkout link for partner email
        // $partnerBaseUrl = 'http://localhost:3000';
        $partnerBaseUrl = 'https://partner.tizzygroup.com/';
        $cartId = $cart ? (int) $cart->id : 0;
        $reqType = strtolower(trim((string) ($purchaseRequest->request_type ?? 'upgrade')));

        $orderId = $purchaseRequest->order_id ? (int) $purchaseRequest->order_id : null;
        if (!$orderId && $purchaseRequest->order_sub_id) {
            $orderId = (int) (OrderItem::query()
                ->whereKey((int) $purchaseRequest->order_sub_id)
                ->value('order_id') ?? 0) ?: null;
        }

        $customerId = $purchaseRequest->customer_id ? (int) $purchaseRequest->customer_id : null;
        $orderSubId = $purchaseRequest->order_sub_id ? (int) $purchaseRequest->order_sub_id : null;
        if (!$orderSubId) {
            $orderSubId = $this->resolveCartOrderSubId($cart) ?: null;
        }
        $licenses = (int) $purchaseRequest->licenses;
        $planId = (int) ($purchaseRequest->plan_id ?? 0);
        if ($planId <= 0 && $cart) {
            $planId = (int) (optional($cart->items->first())->plan_id ?? $cart->plan_id ?? 0);
        }
       
        if ($reqType == 'license_add') {
            $params = [
                'type'         => 'add-license',
                'customer_id'  => $customerId,
                'order_id'     => $orderId,
                'order_sub_id' => $orderSubId,
                'licenses'     => $licenses,
            ];
        } elseif ($reqType == 'upgrade') {
            $params = [
                'type'         => 'upgrade',
                'order_id'     => $orderId,
                'order_sub_id' => $orderSubId,
                'licenses'     => $licenses,
                'customer_id'  => $customerId,
                'main_cart_id' => $cartId > 0 ? $cartId : null,
            ];
        } elseif ($reqType == 'partial-upgrade') {
            $params = [
                'type'         => 'partial-upgrade',
                'order_id'     => $orderId,
                'order_sub_id' => $orderSubId,
                'licenses'     => $licenses,
                'customer_id'  => $customerId,
                'main_cart_id' => $cartId > 0 ? $cartId : null,
            ];
        } elseif ($reqType === 'downgrade') {
            $params = [
                'type'         => 'downgrade',
                'customer_id'  => $customerId,
                'order_id'     => $orderId,
                'order_sub_id' => $orderSubId,
            ];
        } elseif ($reqType === 'renew') {
            $params = [
                'type'         => 'renew-plan',
                'order_id'     => $orderId,
                'order_sub_id' => $orderSubId,
                'planId'       => $planId > 0 ? $planId : null,
                'licenses'     => $licenses,
            ];
        } else {
            die('Invalid request type'. $reqType);die();
        }

        $queryParams = http_build_query(array_filter($params, fn ($v) => $v !== null && $v !== ''));
        $checkoutLink = rtrim($partnerBaseUrl, '/') . '/order-summary?' . $queryParams;

        // 4. Send email notification to partner
        $partnerEmail = optional($purchaseRequest->partner)->email;
        if (!empty($partnerEmail)) {
            Mail::to($partnerEmail)->send(new PurchaseRequestApprovedMail($purchaseRequest, $checkoutLink));
        }

        $this->savePurchaseRequestApprovedNotification($purchaseRequest, $cart);
        
        return [
            'request_id'    => (int) $purchaseRequest->id,
            'main_cart_id'  => $cartId,
            'status'        => PurchaseRequest::STATUS_APPROVED,
            'partner_email' => $partnerEmail,
            'checkout_link' => $checkoutLink,
        ];
    }

    private function savePurchaseRequestApprovedNotification(PurchaseRequest $purchaseRequest, ?Cart $cart = null): void
    {
        $partnerId = (int) ($purchaseRequest->partner_id ?? 0);
        if ($partnerId <= 0) {
            return;
        }

        if (!$cart) {
            $cartId = (int) ($purchaseRequest->cart_id ?? 0);
            $cart = $cartId > 0 ? Cart::query()->find($cartId) : null;
        }

        $requestType = strtolower(trim((string) ($purchaseRequest->request_type ?? 'upgrade')));
        $cartType = strtolower(trim((string) (optional($cart)->type ?? '')));
        $notificationType = $cartType !== '' ? $cartType : $requestType;
        $orderId = (int) ($purchaseRequest->order_id ?? 0) ?: (int) (optional($cart)->order_id ?? 0);
        $orderSubId = (int) ($purchaseRequest->order_sub_id ?? 0) ?: $this->resolveCartOrderSubId($cart);
        $mainCartId = (int) ($purchaseRequest->cart_id ?? 0) ?: (int) (optional($cart)->id ?? 0);

        $typeLabel = str_replace('_', ' ', $notificationType);
        $title = 'Purchase request approved';
        $message = 'Your ' . $typeLabel . ' request #' . (int) $purchaseRequest->id
            . ' has been approved. Please proceed to checkout.';

        try {
            Notification::query()->create([
                'partner_id' => $partnerId,
                'title' => $title,
                'message' => $message,
                'is_read' => 0,
                'order_id' => $orderId > 0 ? $orderId : null,
                'order_sub_id' => $orderSubId > 0 ? $orderSubId : null,
                'main_cart_id' => $mainCartId > 0 ? $mainCartId : null,
                'type' => $notificationType !== '' ? $notificationType : null,
                'status' => 'active',
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('purchase_request_in_app_notification_failed', [
                'request_id' => (int) $purchaseRequest->id,
                'partner_id' => $partnerId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveCartOrderSubId(?Cart $cart): int
    {
        if (!$cart) {
            return 0;
        }

        if (Schema::connection('pgsql_billing')->hasColumn('carts', 'order_sub_id')) {
            $stored = (int) ($cart->order_sub_id ?? 0);
            if ($stored > 0) {
                return $stored;
            }
        }

        $itemType = trim((string) ($cart->item_type ?? ''));
        if ($itemType !== '' && preg_match('/order_sub:(\d+)/', $itemType, $m)) {
            return (int) $m[1];
        }

        $orderId = (int) ($cart->order_id ?? 0);
        if ($orderId > 0) {
            return (int) (OrderItem::query()
                ->where('order_id', $orderId)
                ->orderBy('id')
                ->value('id') ?? 0);
        }

        return 0;
    }

    /**
     * Provider ID Wise Request Condition Check:
     * - Provider ID 1 (Tizzy Mail): Both License Add/Minus AND Plan Request (Upgrade/Downgrade) require request.
     * - Provider ID 3 (Google Workspace): Plan Request (Upgrade/Downgrade) requires request. License Add/Minus allowed directly.
     * - Provider ID 2 (Microsoft 365): Direct cart checkout for all actions (no request required).
     *
     * @param  int  $providerId  (1 = Tizzy Mail, 2 = Microsoft 365, 3 = Google Workspace)
     * @param  string  $actionType  ('upgrade', 'downgrade', 'license_add', 'license_minus')
     */
    public static function checkRequestRequirementByProvider(int $providerId, string $actionType): array
    {
        $action = strtolower(trim($actionType));

        // Provider 1: Tizzy Mail (License Add/Minus: YES, Request: YES)
        if ($providerId === 1) {
            return [
                'provider_id'      => 1,
                'provider_name'    => 'Tizzy Mail',
                'requires_request' => true,
                'change_via'       => 'request',
                'license_add_minus_via_request' => true,
                'plan_change_via_request'       => true,
            ];
        }

        // Provider 3: Google Workspace (License Add/Minus: NO (cart), Request: YES)
        if ($providerId === 3) {
            $isPlanChange = in_array($action, ['upgrade', 'downgrade', 'request'], true);

            return [
                'provider_id'      => 3,
                'provider_name'    => 'Google Workspace',
                'requires_request' => $isPlanChange,
                'change_via'       => $isPlanChange ? 'request' : 'cart',
                'license_add_minus_via_request' => false,
                'plan_change_via_request'       => true,
            ];
        }

        // Provider 2: Microsoft 365 (License Add/Minus: NO (cart), Request: NO (cart))
        return [
            'provider_id'      => 2,
            'provider_name'    => 'Microsoft 365',
            'requires_request' => false,
            'change_via'       => 'cart',
            'license_add_minus_via_request' => false,
            'plan_change_via_request'       => false,
        ];
    }
}
