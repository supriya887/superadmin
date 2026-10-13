<?php

namespace App\Mail;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\Provider;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class OrderProcessingMail extends Mailable
{
    use Queueable, SerializesModels;

    public Order $order;

    public Partner $partner;

    public string $category;

    public string $planName;

    public float $amount;

    public string $orderNo;

    public string $validityDate;

    public ?string $invoiceUrl;

    public string $partnerName;

    public string $customerName;

    public string $providerName;

    public string $billingCycle;

    public string $subscriptionStart;

    public string $subscriptionEnd;

    public float $subtotal;

    public float $discountAmount;

    public float $taxAmount;

    public int $licenses;

    /** @var Collection<int, object> */
    public Collection $lineItems;

    public function __construct(Order $order, Partner $partner, string $category = 'new')
    {
        $this->order = $order;
        $this->partner = $partner;
        $this->category = strtolower(trim($category !== '' ? $category : 'new'));

        $this->orderNo = (string) ($order->order_no ?? $order->id ?? '');
        $this->amount = round((float) (
            $order->total_amount
            ?? $order->subtotal
            ?? $order->credit_used
            ?? 0
        ), 2);
        $this->subtotal = round((float) ($order->subtotal ?? $this->amount), 2);
        $this->discountAmount = round((float) ($order->discount_amount ?? 0), 2);
        $this->taxAmount = round((float) ($order->tax_amount ?? $order->gst_amount ?? 0), 2);
        $this->licenses = max(1, (int) ($order->quantity ?? $order->licenses ?? 1));

        $this->partnerName = trim((string) ($partner->name ?? $partner->company_name ?? 'Partner'));
        $this->customerName = $this->resolveCustomerName($order);

        $plan = !empty($order->plan_id)
            ? Plan::query()->whereKey((int) $order->plan_id)->first()
            : null;

        $this->planName = trim((string) ($order->plan_name ?? ''));
        if ($this->planName === '' && $plan) {
            $this->planName = trim((string) ($plan->name ?? $plan->title ?? ''));
        }
        if ($this->planName === '') {
            $this->planName = 'Subscription';
        }

        $this->billingCycle = $this->formatBillingCycle($plan, $order);
        $this->providerName = $this->resolveProviderName($plan);

        $this->subscriptionStart = $this->formatDate($order->subscription_start_date ?? null);
        $this->subscriptionEnd = $this->formatDate($order->subscription_end_date ?? null);
        if ($this->subscriptionEnd === '-') {
            $this->subscriptionEnd = Carbon::now()->addYear()->format('d M, Y');
        }
        $this->validityDate = $this->subscriptionEnd;

        $poRow = \App\Models\OrderPoDetail::query()->where('order_id', (int) $order->id)->first();
        $this->invoiceUrl = (string) ($poRow->final_po_link ?? $poRow->po_document_url ?? null) ?: null;

        $this->lineItems = $this->buildLineItems((int) $order->id, $plan);
    }

    public function build()
    {
        $subject = match ($this->category) {
            'renewal' => "Order Placed Successfully - Renewal In Process ({$this->orderNo})",
            'upgrade', 'upgraded' => "Order Placed Successfully - Upgrade In Process ({$this->orderNo})",
            'downgrade' => "Order Placed Successfully - Downgrade In Process ({$this->orderNo})",
            default => "Order Placed Successfully - In Process ({$this->orderNo})",
        };

        return $this->subject($subject)
            ->view('emails.order_processing');
    }

    private function resolveCustomerName(Order $order): string
    {
        if (!empty($order->user_id)) {
            $customer = Customer::query()->whereKey((int) $order->user_id)->first();
            if ($customer) {
                $name = trim((string) ($customer->name ?? $customer->company_name ?? ''));
                if ($name !== '') {
                    return $name;
                }
            }
        }

        return $this->partnerName;
    }

    private function resolveProviderName(?Plan $plan): string
    {
        if (!$plan || (int) ($plan->provider_id ?? 0) <= 0) {
            return '-';
        }

        $provider = Provider::query()->whereKey((int) $plan->provider_id)->first();
        $name = trim((string) ($provider->name ?? ''));

        return $name !== '' ? $name : '-';
    }

    private function formatBillingCycle(?Plan $plan, Order $order): string
    {
        $cycle = strtolower(trim((string) ($plan->billing_cycle ?? $order->duration ?? '')));
        if ($cycle === '') {
            return 'Yearly';
        }

        return match ($cycle) {
            'monthly' => 'Monthly',
            'yearly' => 'Yearly',
            '12 month', '12 months' => '12 Months',
            default => ucfirst($cycle),
        };
    }

    private function formatDate(mixed $value): string
    {
        if (empty($value)) {
            return '-';
        }

        try {
            return Carbon::parse((string) $value)->format('d M, Y');
        } catch (\Throwable) {
            return '-';
        }
    }

    /**
     * @return Collection<int, object>
     */
    private function buildLineItems(int $orderId, ?Plan $fallbackPlan): Collection
    {
        $items = OrderItem::query()->where('order_id', $orderId)->get();
        if ($items->isEmpty()) {
            return collect([
                (object) [
                    'plan_name' => $this->planName,
                    'quantity' => $this->licenses,
                    'unit_price' => $this->licenses > 0 ? round($this->subtotal / $this->licenses, 2) : $this->subtotal,
                    'billing_cycle' => $this->billingCycle,
                    'subtotal' => $this->subtotal,
                    'subscription_start' => $this->subscriptionStart,
                    'subscription_end' => $this->subscriptionEnd,
                ],
            ]);
        }

        $planIds = $items->pluck('plan_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $plans = $planIds->isNotEmpty()
            ? Plan::query()->whereIn('id', $planIds->all())->get()->keyBy('id')
            : collect();

        return $items->map(function (OrderItem $item) use ($plans, $fallbackPlan) {
            $plan = $plans->get((int) ($item->plan_id ?? 0)) ?? $fallbackPlan;
            $planName = trim((string) ($item->item_name ?? $plan->name ?? $plan->title ?? '-'));
            if ($planName === '') {
                $planName = '-';
            }

            return (object) [
                'plan_name' => $planName,
                'quantity' => max(1, (int) ($item->quantity ?? 1)),
                'unit_price' => round((float) ($item->unit_price ?? 0), 2),
                'billing_cycle' => $this->formatBillingCycle($plan instanceof Plan ? $plan : null, $this->order),
                'subtotal' => round((float) ($item->total_amount ?? 0), 2),
                'subscription_start' => $this->formatDate($item->subscription_start_date ?? $this->order->subscription_start_date ?? null),
                'subscription_end' => $this->formatDate($item->subscription_end_date ?? $this->order->subscription_end_date ?? null),
            ];
        });
    }
}
