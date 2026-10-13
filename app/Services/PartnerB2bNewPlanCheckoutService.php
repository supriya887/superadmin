<?php

namespace App\Services;

use App\Http\Controllers\SuperAdmin\BillingController;
use App\Models\CreditRequest;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Partner as PartnerRecord;
use App\Models\Plan;
use App\Models\Promocode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use App\Support\OrderPrefixResolver;

class PartnerB2bNewPlanCheckoutService
{
    public function __construct(
        private readonly BillingController $billingController
    ) {
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function processCheckout(array $input, int $actingPartnerId): array
    {
        $plan = Plan::query()->findOrFail((int) ($input['plan_id'] ?? 0));
        $customer = Customer::query()->findOrFail((int) ($input['user_id'] ?? 0));

        if ((int) $customer->partner_id !== $actingPartnerId) {
            throw ValidationException::withMessages([
                'user_id' => 'Customer does not belong to this partner.',
            ]);
        }

        $licenses = (int) ($input['licenses'] ?? $input['quantity'] ?? 1);
        if ($licenses < 1) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be at least 1.']);
        }

        if (!empty($plan->customer_limit) && $licenses > (int) $plan->customer_limit) {
            throw ValidationException::withMessages([
                'quantity' => 'Requested quantity exceeds plan customer limit.',
            ]);
        }

        $baseAmount = (float) $plan->price;
        $subtotal = $baseAmount * $licenses;
        $discountAmount = max(0.0, (float) ($input['discount_amount'] ?? 0));
        $afterDiscount = max(0.0, $subtotal - $discountAmount);

        if (!empty($input['promocode'])) {
            $promo = Promocode::whereRaw('UPPER(code)=?', [strtoupper(trim((string) $input['promocode']))])
                ->where('status', 1)
                ->first();
            if (!$promo) {
                throw ValidationException::withMessages(['promocode' => 'Invalid promo code.']);
            }
            $promoAmount = ($afterDiscount * (float) $promo->discount_percent) / 100;
            $afterDiscount = max(0.0, $afterDiscount - $promoAmount);
        }

        $applyGst = (bool) ($input['apply_gst'] ?? false);
        $gstRate = (float) ($input['gst_rate'] ?? 18);
        $gstAmount = $applyGst ? round($afterDiscount * $gstRate / 100, 2) : 0.0;
        $totalAmount = round($afterDiscount + $gstAmount, 2);

        $partnerRead = PartnerRecord::query()->whereKey($actingPartnerId)->firstOrFail();
        if (PartnerCreditService::isCreditLimitFullyUsed($actingPartnerId)) {
            return [
                'ok' => false,
                'code' => 'credit_limit_reached',
                'message' => PartnerCreditService::CREDIT_LIMIT_REACHED_MESSAGE,
                'total_amount' => $totalAmount,
                'wallet_balance' => (float) ($partnerRead->wallet_balance ?? 0),
            ];
        }
        if ($totalAmount > (float) ($partnerRead->credit_limit ?? 0) + 0.0001) {
            throw ValidationException::withMessages([
                'total_amount' => 'Order amount exceeds total credit limit',
            ]);
        }

        $wallet = (float) ($partnerRead->wallet_balance ?? 0);

        if ($wallet + 0.001 < $totalAmount) {
            $shortfall = round(max(0.0, $totalAmount - $wallet), 2);
            if (Schema::hasTable('credit_requests')) {
                $creditRequest = CreditRequest::create([
                    'partner_id' => $actingPartnerId,
                    'order_id' => null,
                    'requested_amount' => $shortfall > 0 ? $shortfall : $totalAmount,
                    'status' => 'pending',
                ]);
            } else {
                $creditRequest = null;
            }

            return [
                'ok' => false,
                'code' => 'insufficient_credits',
                'message' => 'Insufficient Credits',
                'credit_request_id' => $creditRequest?->id,
                'request_credits_url' => route('partner-admin.b2b.new_plan'),
                'total_amount' => $totalAmount,
                'wallet_balance' => $wallet,
            ];
        }

        $order = null;

        DB::transaction(function () use (
            $input,
            $actingPartnerId,
            $plan,
            $customer,
            $licenses,
            $subtotal,
            $discountAmount,
            $gstRate,
            $gstAmount,
            $totalAmount,
            $applyGst,
            $baseAmount,
            &$order
        ): void {
            $partnerLocked = PartnerRecord::query()->whereKey($actingPartnerId)->lockForUpdate()->firstOrFail();

            $walletNow = (float) ($partnerLocked->wallet_balance ?? 0);
            if ($walletNow + 0.001 < $totalAmount) {
                throw ValidationException::withMessages([
                    'wallet' => 'Insufficient Credits',
                ]);
            }

            $ids = $this->nextPartnerOrderIdentifiers();
            $srNo = $ids['sr_no'];
            $orderNo = $ids['order_no'];

            $duration = (string) ($plan->billing_cycle ?? '12 month');
            if ($duration === '') {
                $duration = '12 month';
            }

            $orderPayload = [
                'sr_no' => $srNo,
                'plan_id' => $plan->id,
                'partner_id' => $actingPartnerId,
                'user_id' => $customer->id,
                'domain_name' => (string) $input['domain_name'],
                'order_type' => 'b2b',
                'quantity' => $licenses,
                'price' => $baseAmount,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'gst_rate' => $gstRate,
                'gst_amount' => $gstAmount,
                'tax_amount' => $gstAmount,
                'total_amount' => $totalAmount,
                'credit_used' => $totalAmount,
                'status' => 'pending',
                'order_note' => $input['order_note'] ?? null,
            ];

            if (Schema::hasColumn('orders', 'order_no')) {
                $orderPayload['order_no'] = $orderNo;
            }
            if (Schema::hasColumn('orders', 'provider_id')) {
                $orderPayload['provider_id'] = $input['provider_id'] ?? $plan->provider_id;
            }
            if (Schema::hasColumn('orders', 'apply_gst')) {
                $orderPayload['apply_gst'] = $applyGst;
            }
            if (Schema::hasColumn('orders', 'payment_method')) {
                $orderPayload['payment_method'] = 'wallet';
            }
            if (Schema::hasColumn('orders', 'payment_mode')) {
                $orderPayload['payment_mode'] = 'wallet';
            }
            if (Schema::hasColumn('orders', 'order_category')) {
                $orderPayload['order_category'] = 'new';
            }
            if (Schema::hasColumn('orders', 'duration')) {
                $orderPayload['duration'] = $duration;
            }
            if (Schema::hasColumn('orders', 'po_journey_status')) {
                $orderPayload['po_journey_status'] = 'po_pending';
            }

            $this->applySubscriptionDatesToOrderPayload($orderPayload, $input);

            $this->applyCustomerSnapshotToOrderPayload($orderPayload, (int) $customer->id);

            $order = Order::create($orderPayload);

            $partnerLocked->decrement('wallet_balance', $totalAmount);

            $this->syncOrderPoDetails((int) $order->id, $customer, $input, $totalAmount);
        });

        if (!$order instanceof Order) {
            throw new \RuntimeException('Order checkout failed.');
        }

        $this->billingController->finalizePartnerB2bOrderPostSave((int) $order->id);

        try {
            $mobile = (string) ($customer->mobile ?? '');
            if ($mobile !== '') {
                app(SmsService::class)->sendPaymentConfirmation(
                    $mobile,
                    $totalAmount,
                    (string) ($order->order_no ?? $order->id)
                );
            }
        } catch (\Throwable) {
        }

        return [
            'ok' => true,
            'message' => 'Order created successfully',
            'order_id' => (int) $order->id,
            'order_no' => (string) $order->order_no,
            'total_amount' => $totalAmount,
        ];
    }

    /**
     * Same rules as {@see BillingController::nextOrderIdentifiers()}: sr_no + prefixed order_no, unique.
     *
     * @return array{order_no: string, sr_no: int}
     */
    private function nextPartnerOrderIdentifiers(): array
    {
        $nextSrNo = ((int) DB::table('orders')->lockForUpdate()->max('sr_no')) + 1;
        if ($nextSrNo <= 0) {
            $nextSrNo = ((int) DB::table('orders')->lockForUpdate()->count()) + 1;
        }

        $orderPrefix = OrderPrefixResolver::resolve('new');
        $orderNo = $orderPrefix . str_pad((string) $nextSrNo, 5, '0', STR_PAD_LEFT);
        while (DB::table('orders')->where('order_no', $orderNo)->exists()) {
            $nextSrNo++;
            $orderNo = $orderPrefix . str_pad((string) $nextSrNo, 5, '0', STR_PAD_LEFT);
        }

        return [
            'order_no' => $orderNo,
            'sr_no' => $nextSrNo,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $orderPayload
     */
    private function applySubscriptionDatesToOrderPayload(array &$orderPayload, array $input): void
    {
        $orderColumns = Schema::getColumnListing('orders');
        $startCol = null;
        foreach (['subscription_start_date', 'subscription_start'] as $c) {
            if (in_array($c, $orderColumns, true)) {
                $startCol = $c;
                break;
            }
        }
        $endCol = null;
        foreach (['subscription_end_date', 'subscription_end'] as $c) {
            if (in_array($c, $orderColumns, true)) {
                $endCol = $c;
                break;
            }
        }
        if ($startCol === null && $endCol === null) {
            return;
        }

        $startVal = trim((string) ($input['subscription_start_date'] ?? $input['subscription_start'] ?? ''));
        if ($startVal === '') {
            return;
        }

        try {
            $start = \Carbon\Carbon::parse($startVal);
            if ($startCol !== null) {
                $orderPayload[$startCol] = $start->toDateString();
            }
            if ($endCol !== null) {
                $orderPayload[$endCol] = $start->copy()->addYear()->subDay()->toDateString();
            }
        } catch (\Throwable) {
        }
    }

    /**
     * @param  array<string, mixed>  $orderPayload
     */
    private function applyCustomerSnapshotToOrderPayload(array &$orderPayload, int $customerId): void
    {
        $prefill = app(\App\Services\RenewalService::class)->buildPurchaseOrderPrefillFromCustomer($customerId);
        if ($prefill === []) {
            return;
        }

        $map = [
            'po_manual_name' => 'name',
            'po_manual_email' => 'email',
            'po_manual_mobile' => 'mobile',
            'po_manual_gstin' => 'gstin',
            'po_manual_company_name' => 'company_name',
            'po_manual_company_address' => 'address',
            'po_manual_city' => 'city',
            'po_manual_state' => 'state',
            'po_manual_country' => 'country',
            'po_manual_pincode' => 'postcode',
        ];

        foreach ($map as $column => $prefillKey) {
            if (!Schema::hasColumn('orders', $column)) {
                continue;
            }
            $value = trim((string) ($prefill[$prefillKey] ?? ''));
            if ($value !== '') {
                $orderPayload[$column] = $value;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function syncOrderPoDetails(int $orderId, Customer $customer, array $input, float $totalAmount): void
    {
        if (!Schema::hasTable('order_po_details')) {
            return;
        }

        $this->billingController->seedB2bOrderCustomerSnapshot($orderId, $totalAmount);
    }
}
