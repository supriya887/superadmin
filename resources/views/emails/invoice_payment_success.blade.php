@php
    $iconBase = 'https://superadmin.tizzygroup.com/superadmin/icons/';
    $customerName = trim((string) ($order->customer_name ?? 'Partner'));
    if ($customerName === '') {
        $customerName = 'Partner';
    }

    $statusLabel = 'Active';
    $orderNo = trim((string) ($order->order_no ?? ''));
    $orderNoDisplay = $orderNo !== '' ? $orderNo : '-';

    $invoiceNo = trim((string) ($order->invoice_no ?? $order->bill_no_full ?? $order->tzg_invoice_no ?? ''));
    if ($invoiceNo === '') {
        $invoiceNo = '-';
    }

    $paymentId = trim((string) ($order->razorpay_payment_id ?? $order->gateway_payment_id ?? ''));
    if ($paymentId === '') {
        $paymentId = '-';
    }

    $emailOrderId = (int) ($order->order_id ?? $order->id ?? 0);
    $emailOrderItems = collect();
    $plansTable = collect();
    if ($emailOrderId > 0) {
        $emailOrderItems = \App\Models\OrderItem::query()
            ->where('order_id', $emailOrderId)
            ->get();
    }
    $planIds = $emailOrderItems
        ->pluck('plan_id')
        ->filter()
        ->map(fn ($id) => (int) $id)
        ->unique()
        ->values();
    if ($planIds->isNotEmpty()) {
        $plansTable = \App\Models\Plan::query()
            ->whereIn('id', $planIds->all())
            ->get()
            ->keyBy('id');
    }

    $providerName = trim((string) ($order->resolved_plan_name ?? '-'));
    $planRow = null;
    $categoryName = trim((string) ($order->order_category ?? ''));
    $billingCycle = trim((string) ($order->duration ?? ''));

    if ((int) ($order->plan_id ?? 0) > 0) {
        $planRow = \App\Models\Plan::query()->whereKey((int) $order->plan_id)->first();
    } elseif ($plansTable->isNotEmpty()) {
        $planRow = $plansTable->first();
    }

    if ($planRow) {
        if ($billingCycle === '') {
            $billingCycle = trim((string) ($planRow->billing_cycle ?? ''));
        }
        if ((int) ($planRow->provider_id ?? 0) > 0) {
            $providerRow = \App\Models\Provider::query()->whereKey((int) $planRow->provider_id)->first();
            $providerName = trim((string) ($providerRow->name ?? $providerName));
        }
        if ($categoryName === '' && (int) ($planRow->category_id ?? 0) > 0 && class_exists(\App\Models\PlanCategory::class)) {
            $categoryRow = \App\Models\PlanCategory::query()->whereKey((int) $planRow->category_id)->first();
            $categoryName = trim((string) ($categoryRow->name ?? $categoryRow->title ?? ''));
        }
    }

    if ((int) ($order->provider_id ?? 0) > 0 && ($providerName === '' || $providerName === '-')) {
        $providerRow = \App\Models\Provider::query()->whereKey((int) $order->provider_id)->first();
        $providerName = trim((string) ($providerRow->name ?? '-'));
    }
    if ($providerName === '') {
        $providerName = '-';
    }
    if ($categoryName === '') {
        $categoryName = '-';
    } else {
        $categoryName = ucfirst($categoryName);
    }
    if ($billingCycle === '') {
        $billingCycle = '-';
    } else {
        $billingCycle = ucfirst($billingCycle);
    }

    $domainName = trim((string) ($order->domain_name ?? '-'));
    if ($domainName === '') {
        $domainName = '-';
    }

    $startDateRaw = $order->subscription_start_date ?? null;
    $startDate = '-';
    if (!empty($startDateRaw)) {
        try {
            $startDate = \Illuminate\Support\Carbon::parse((string) $startDateRaw)->format('d M Y');
        } catch (\Throwable $e) {
            $startDate = (string) $startDateRaw;
        }
    }

    $planTotal = (float) $emailOrderItems->sum(fn ($item) => (float) ($item->total_amount ?? 0));
    if ($planTotal <= 0) {
        $planTotal = (float) ($order->subtotal ?? $order->price ?? 0);
    }
    $gstAmount = (float) ($order->gst_amount ?? $order->tax_amount ?? 0);
    $discountAmount = (float) ($order->discount_amount ?? 0);
    $totalPaid = (float) ($order->invoice_paid_amount ?? $order->resolved_amount ?? $order->total_amount ?? 0);
    if ($totalPaid <= 0) {
        $totalPaid = max(0, $planTotal + $gstAmount - $discountAmount);
    }

    $orderUrl = trim((string) ($orderUrl ?? $order->order_url ?? ''));
    if ($orderUrl === '') {
        $orderUrl = '#';
    }

    $formatMoney = static function ($amount): string {
        return '₹' . number_format((float) $amount, 0);
    };
    $formatMoneyDecimal = static function ($amount): string {
        return 'Rs. ' . number_format((float) $amount, 2);
    };

    $cycleUnitLabel = static function (?string $cycle): string {
        $cycle = strtolower(trim((string) $cycle));
        if (str_contains($cycle, 'month')) {
            return 'Per User / Per Month';
        }
        if (str_contains($cycle, 'quarter')) {
            return 'Per User / Per Quarter';
        }
        if (str_contains($cycle, 'year') || $cycle === 'annual' || $cycle === 'annually') {
            return 'Per User / Per Year';
        }

        return 'Per User';
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Payment Successful</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f7fb; font-family:'Baloo Bhai', sans-serif;">
    <table width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="#f4f7fb">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="background:#F5F8FC;border-collapse:collapse;font-family: Inter, Arial, Helvetica, sans-serif;">
                    <tbody>
                        <tr>
                            <td align="center" style="padding:25px 30px;">
                                @include('emails.partials.logo', [
                                    'logoWidth' => '200',
                                    'logoStyle' => 'display:block; max-width:200px; width:200px; height:auto; border:0;',
                                ])
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:0 24px;">
                                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-radius:12px;">
                                    <tbody>
                                        <tr>
                                            <td style="padding:35px 30px;margin-bottom: 15px;border: 1px solid #E7E9EB;border-radius: 10px;background: #fff;">
                                                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                                    <tbody>
                                                        <tr>
                                                            <td align="center" style="padding-bottom:20px;">
                                                                <table cellpadding="0" cellspacing="0" border="0" align="center" role="presentation">
                                                                    <tr>
                                                                        <td align="center" valign="middle" width="70" height="70" style="width:70px;height:70px;border-radius:50%;background:#E8FBF3;border:2px solid #0FA968;font-family:Arial,Helvetica,sans-serif;font-size:36px;line-height:70px;color:#0FA968;font-weight:700;">
                                                                            &#10003;
                                                                        </td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                                <h1 style="margin:0px;color:#121A26;font-family:Inter,Arial,Helvetica,sans-serif;font-size:26px;font-weight:700;line-height:150%;letter-spacing:.2px;text-align:center;"> Invoice Payment Successfully </h1>
                                                <p style="margin:8px 0 15px;text-align:center;color:#5B6473;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;line-height:150%;"> Thank you for choosing Tizzy Group </p>
                                                <table align="center" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto 35px;">
                                                    <tr>
                                                        <td style="background:#E8FBF3;border:1px solid #B9F2D6;border-radius:20px;padding:6px 16px;font-family:Inter,Arial,Helvetica,sans-serif;font-size:13px;font-weight:600;color:#0FA968;"> ● {{ $statusLabel }} </td>
                                                    </tr>
                                                </table>
                                                <p style="margin:0 0 16px;color:#4C4C5C;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;line-height:170%;"> Hello <strong style="color:#121A26;">{{ $customerName }}</strong>, </p>
                                                <p style="margin:0 0 35px;color:#4C4C5C;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;line-height:170%;"> Your invoice payment has been completed successfully. Your subscription is now active. Please find the invoice details below. </p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 24px 0;">
                                                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #E7E9EB;border-radius:12px;background:#ffffff;">
                                                    <tbody>
                                                        <tr>
                                                            <td style="padding: 32px;">
                                                                <table width="100%" cellpadding="0" cellspacing="0">
                                                                    <tbody>
                                                                        <tr>
                                                                            <td style="font-family:Inter,Arial,Helvetica,sans-serif;font-size:22px;font-weight:600;color:#121A26;">
                                                                                Invoice Payment Details
                                                                            </td>
                                                                            <td align="right">
                                                                                <span style="background:#F4F5F7;font-size: 14px;font-style: normal;font-weight: 500;line-height: normal;padding:7px 14px;border-radius:4px;font-family:Inter,Arial,Helvetica,sans-serif;color: #2C3139;"> Order #{{ $orderNoDisplay }} </span>
                                                                            </td>
                                                                        </tr>
                                                                    </tbody>
                                                                </table>
                                                                <hr style="border:none;border-top:1px solid #E7E9EB;margin:20px 0;">
                                                                <table width="100%" cellpadding="8" cellspacing="0" border="0">
                                                                    <tbody>
                                                                        <tr>
                                                                            <td width="40%" style="color:#384860;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> Partner </td>
                                                                            <td align="right" style="color:#121A26;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;font-weight: 500;"> {{ $customerName }} </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td style="color:#384860;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> Invoice No. </td>
                                                                            <td align="right" style="color:#121A26;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;font-weight: 500;"> {{ $invoiceNo }} </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td style="color:#384860;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> Payment ID </td>
                                                                            <td align="right" style="color:#121A26;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;font-weight: 500;"> {{ $paymentId }} </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td style="color:#384860;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> Payment Status </td>
                                                                            <td align="right" style="color:#0FA968;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;font-weight: 600;"> Paid </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td style="color:#384860;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> Category </td>
                                                                            <td align="right" style="color:#121A26;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;font-weight: 500;"> {{ $categoryName }} </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td style="color:#384860;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> Provider </td>
                                                                            <td align="right" style="color:#121A26;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;font-weight: 500;"> {{ $providerName }} </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td style="color:#384860;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> Domain </td>
                                                                            <td align="right" style="color:#121A26;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> {{ $domainName }} </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td style="color:#384860;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> Billing </td>
                                                                            <td align="right" style="color:#121A26;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> {{ $billingCycle }} </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td style="color:#384860;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> Start Date </td>
                                                                            <td align="right" style="color:#121A26;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> {{ $startDate }} </td>
                                                                        </tr>
                                                                    </tbody>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:24px 24px 0;">
                                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #E7E9EB;border-radius:12px;background:#ffffff;">
                                    <tbody>
                                        <tr>
                                            <td style="padding: 32px;">
                                                <table width="100%" cellpadding="0" cellspacing="0">
                                                    <tbody>
                                                        <tr>
                                                            <td style="font-family:Inter,Arial,Helvetica,sans-serif;font-size:22px;font-weight:600;color:#121A26;">
                                                                Plan Detail
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                                <hr style="border:none;border-top:1px solid #E7E9EB;margin:20px 0;">

                                                @if ($emailOrderItems->isNotEmpty())
                                                    @foreach ($emailOrderItems as $item)
                                                        @php
                                                            $plan = $plansTable->get((int) ($item->plan_id ?? 0));
                                                            $planName = trim((string) ($item->item_name ?? $plan->name ?? $plan->title ?? '-'));
                                                            if ($planName === '') {
                                                                $planName = '-';
                                                            }
                                                            $qty = (int) ($item->quantity ?? 1);
                                                            $unitPrice = (float) ($item->unit_price ?? $plan->price ?? 0);
                                                            $itemCycle = trim((string) ($plan->billing_cycle ?? $order->duration ?? $billingCycle));
                                                            $subtotal = (float) ($item->total_amount ?? ($unitPrice * max($qty, 1)));
                                                            $subscriptionNo = trim((string) ($item->subscription_id ?? $item->subscription_no ?? ''));
                                                            if ($subscriptionNo === '' && (int) ($item->id ?? 0) > 0) {
                                                                $subscriptionNo = 'SUB-' . str_pad((string) $item->id, 5, '0', STR_PAD_LEFT);
                                                            }
                                                            if ($subscriptionNo === '') {
                                                                $subscriptionNo = '-';
                                                            }
                                                        @endphp
                                                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #E7E9EB;border-radius:10px;margin-bottom:16px;">
                                                            <tbody>
                                                                <tr>
                                                                    <td style="padding:18px 16px;">
                                                                        <table width="100%" cellpadding="0" cellspacing="0">
                                                                            <tbody>
                                                                                <tr>
                                                                                    <td valign="top" width="60%">
                                                                                        <div style="font-family:Inter,Arial,sans-serif;font-weight:600;font-size: 14px;color:#121A26;"> {{ $planName }} </div>
                                                                                        <div style="margin-top:12px;">
                                                                                            <span style="font-family: Inter, Arial, Helvetica, sans-serif;color: #4C4C5C;text-align: center;font-size: 14px;font-style: normal;font-weight: 500;line-height: normal;"> ₹ {{ number_format($unitPrice, 0) }} </span>
                                                                                            <span style="color: #9BA6B7;text-align: center;font-size: 12px;font-style: normal;font-weight: 400;line-height: normal;"> {{ $cycleUnitLabel($itemCycle) }} </span>
                                                                                        </div>
                                                                                    </td>
                                                                                    <td align="right" valign="top">
                                                                                        <div style="margin-bottom:8px;color: #4C4C5C;font-size: 12px;font-family: Inter, Arial, Helvetica, sans-serif;">
                                                                                            {{ $subscriptionNo }} &nbsp;&nbsp; {{ $qty }} {{ $qty === 1 ? 'User' : 'Users' }}
                                                                                        </div>
                                                                                        <div style="font-family:Inter,Arial,Helvetica,sans-serif;font-weight:600;color:#121A26;"> {{ $formatMoneyDecimal($subtotal) }} </div>
                                                                                    </td>
                                                                                </tr>
                                                                            </tbody>
                                                                        </table>
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    @endforeach
                                                @else
                                                    <p style="margin:0 0 16px;color:#9BA6B7;font-family:Inter,Arial,Helvetica,sans-serif;font-size:14px;">No plan details found in this order.</p>
                                                @endif

                                                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                                    <tbody>
                                                        <tr>
                                                            <td style="padding: 32px 0px 0 0;">
                                                                <table width="100%" cellpadding="0" cellspacing="0">
                                                                    <tbody>
                                                                        <tr>
                                                                            <td style="font-family:Inter,Arial,Helvetica,sans-serif;font-size:22px;font-weight:600;color:#121A26;">
                                                                                Payment Summary
                                                                            </td>
                                                                        </tr>
                                                                    </tbody>
                                                                </table>
                                                                <hr style="border:none;border-top:1px solid #E7E9EB;margin:20px 0;">
                                                                <table width="100%" cellpadding="8" cellspacing="0" border="0">
                                                                    <tbody>
                                                                        <tr>
                                                                            <td width="60%" style="color:#384860;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> Plan Total </td>
                                                                            <td align="right" style="color:#121A26;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;font-weight: 500;"> {{ $formatMoney($planTotal) }} </td>
                                                                        </tr>
                                                                        @if($discountAmount > 0)
                                                                        <tr>
                                                                            <td style="color:#384860;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> Discount </td>
                                                                            <td align="right" style="color:#0FA968;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;font-weight: 500;"> - {{ $formatMoney($discountAmount) }} </td>
                                                                        </tr>
                                                                        @endif
                                                                        @if($gstAmount > 0)
                                                                        <tr>
                                                                            <td style="color:#384860;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> GST </td>
                                                                            <td align="right" style="color:#121A26;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> {{ $formatMoney($gstAmount) }} </td>
                                                                        </tr>
                                                                        @endif
                                                                    </tbody>
                                                                </table>
                                                                <hr style="border:none;border-top:1px solid #E7E9EB;margin:20px 0;">
                                                                <table width="100%" cellpadding="8" cellspacing="0" border="0">
                                                                    <tbody>
                                                                        <tr>
                                                                            <td width="60%" style="font-family:Inter,Arial,Helvetica,sans-serif;font-size: 16px;font-weight: 600;color:#121A26;padding: 0;"> Total Paid </td>
                                                                            <td align="right" style="font-family:Inter,Arial,Helvetica,sans-serif;font-size: 16px;font-weight: 600;color:#121A26;padding: 0;"> {{ $formatMoney($totalPaid) }} </td>
                                                                        </tr>
                                                                    </tbody>
                                                                </table>
                                                                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top: 32px;">
                                                                    <tbody>
                                                                        <tr>
                                                                            <td>
                                                                                <a href="{{ $orderUrl }}" style="display:block;background:linear-gradient(45deg,#2969FF,#193F99);color:#ffffff;text-decoration:none;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;font-weight:600;text-align:center;padding:18px 20px;border-radius:8px;"> View Order </a>
                                                                            </td>
                                                                        </tr>
                                                                    </tbody>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:24px 24px 0;">
                                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#eff6ff; border:1px solid #bfdbfe; border-radius:10px;">
                                    <tr>
                                        <td style="padding:16px 24px; color:#1e40af; font-family:Inter,Arial,Helvetica,sans-serif; font-size:15px; line-height:26px;">
                                            Tax invoice PDF is attached with this email for your reference.
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:24px 24px 0;">
                                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #E7E9EB;border-radius:10px;background:#ffffff;">
                                    <tbody>
                                        <tr>
                                            <td style="padding:16px 32px;">
                                                <h3 style="margin:0 0 15px;color:#121A26;font-family:Inter,Arial,Helvetica,sans-serif;font-size:20px;font-weight:600;">
                                                    Need Help?
                                                </h3>
                                                <p style="margin:0;color:#4C4C5C;font-family:Inter,Arial,Helvetica,sans-serif;font-size:15px;line-height:150%;">
                                                    support@tizzygroup.com
                                                </p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td align="center" style="padding:24px;">
                                <h2 style="margin:0 0 16px;color:#121A26;font-family:Inter,Arial,Helvetica,sans-serif;font-size:20px;font-weight:600;line-height:150%;"> Thank you for choosing Tizzy Group </h2>
                                <p style="margin:8px 0;color:#202B3C;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> © {{ date('Y') }} Tizzy Group. All Rights Reserved. </p>
                                <p style="margin:8px 0;color:#202B3C;font-family:Inter,Arial,Helvetica,sans-serif;font-size:13px;"> This is an automated email notification. Please do not reply directly to this email. </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
