<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Placed Successfully</title>
</head>
<body>
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#FFF;padding:30px 0;border-collapse:collapse;font-family:Inter,Arial,Helvetica,sans-serif;">
    <tr>
        <td align="center">
            <table width="600" cellpadding="0" cellspacing="0" border="0" style="background:#F5F8FC;border-collapse:collapse;font-family:Inter,Arial,Helvetica,sans-serif;">
                <tr>
                    <td align="center" style="padding:25px 30px;">
                        @include('emails.partials.logo', [
                            'logoWidth' => '200',
                            'logoStyle' => 'display:block;max-width:200px;width:200px;height:auto;border:0;',
                            'providerAlt' => 'Tizzy Group',
                        ])
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 24px;">
                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-radius:12px;">
                            <tr>
                                <td style="padding:35px 30px;border:1px solid #E7E9EB;border-radius:10px;background:#fff;">
                                    <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                        <tr>
                                            <td align="center" style="padding-bottom:20px;">
                                                @include('emails.partials.mail_icon', ['icon' => 'success-check', 'width' => 70, 'alt' => 'Success', 'style' => 'border:0;display:block;margin:0 auto;'])
                                            </td>
                                        </tr>
                                    </table>
                                    <h1 style="margin:0;color:#121A26;font-family:Inter,Arial,Helvetica,sans-serif;font-size:26px;font-weight:700;line-height:150%;letter-spacing:.2px;text-align:center;"> Order Placed Successfully </h1>
                                    <p style="margin:8px 0 15px;text-align:center;color:#5B6473;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;line-height:150%;"> Your order is in process. We will update you once it is activated. </p>
                                    <table align="center" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto 35px;">
                                        <tr>
                                            <td style="background:#FFF8EB;border:1px solid #f9bf594a;border-radius:20px;padding:6px 16px;font-family:Inter,Arial,Helvetica,sans-serif;font-size:13px;font-weight:600;color:#D89A1E;"> &bull; In Progress </td>
                                        </tr>
                                    </table>
                                    <p style="margin:0 0 16px;color:#4C4C5C;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;line-height:170%;"> Hello <strong style="color:#121A26;">{{ $partnerName ?? 'Partner' }}</strong>, </p>
                                    <p style="margin:0 0 35px;color:#4C4C5C;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;line-height:170%;"> Thank you. Your order has been placed successfully and is currently being processed. Your subscription will be activated shortly. Once activation is complete, you will receive a confirmation email with invoice and PO documents. </p>
                                    <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                        <tr>
                                            <td>
                                                <a href="https://partner.tizzygroup.com" style="display:block;background:linear-gradient(45deg,#2969FF,#193F99);color:#ffffff;text-decoration:none;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;font-weight:600;text-align:center;padding:18px 20px;border-radius:8px;"> Track Order </a>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:24px 0;">
                                    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #E7E9EB;border-radius:12px;background:#ffffff;">
                                        <tr>
                                            <td style="padding:32px;">
                                                <table width="100%" cellpadding="0" cellspacing="0">
                                                    <tr>
                                                        <td style="font-family:Inter,Arial,Helvetica,sans-serif;font-size:22px;font-weight:600;color:#121A26;">
                                                            @include('emails.partials.mail_icon', ['icon' => 'order', 'width' => 24, 'alt' => 'Order']) Order Overview
                                                        </td>
                                                        <td align="right">
                                                            <span style="background:#F4F5F7;padding:7px 14px;border-radius:4px;font-family:Inter,Arial,Helvetica,sans-serif;font-size:14px;font-weight:500;color:#2C3139;"> Order #{{ $orderNo }} </span>
                                                        </td>
                                                    </tr>
                                                </table>
                                                <hr style="border:none;border-top:1px solid #E7E9EB;margin:20px 0;">
                                                <table width="100%" cellpadding="8" cellspacing="0" border="0">
                                                    <tr>
                                                        <td width="40%" style="color:#384860;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> Customer </td>
                                                        <td align="right" style="color:#121A26;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;font-weight:500;"> {{ $customerName ?? '-' }} </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="color:#384860;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> Category </td>
                                                        <td align="right" style="color:#121A26;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;font-weight:500;"> {{ ucfirst($category ?? 'new') }} </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="color:#384860;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> Provider </td>
                                                        <td align="right" style="color:#121A26;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;font-weight:500;"> {{ $providerName ?? '-' }} </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="color:#384860;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> Domain </td>
                                                        <td align="right" style="color:#121A26;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> {{ $order->domain_name ?? '-' }} </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="color:#384860;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> Billing </td>
                                                        <td align="right" style="color:#121A26;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> {{ $billingCycle ?? 'Yearly' }} </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="color:#384860;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> Start Date </td>
                                                        <td align="right" style="color:#121A26;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> {{ $subscriptionStart ?? '-' }} </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:24px 24px 0;">
                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #E7E9EB;border-radius:12px;background:#ffffff;">
                            <tr>
                                <td style="padding:32px;">
                                    <table width="100%" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td style="font-family:Inter,Arial,Helvetica,sans-serif;font-size:22px;font-weight:600;color:#121A26;">
                                                @include('emails.partials.mail_icon', ['icon' => 'plan', 'width' => 24, 'alt' => 'Plan']) Plan Detail
                                            </td>
                                        </tr>
                                    </table>
                                    <hr style="border:none;border-top:1px solid #E7E9EB;margin:20px 0;">
                                    @if(!empty($lineItems) && count($lineItems) > 0)
                                        @foreach($lineItems as $item)
                                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #E7E9EB;border-radius:10px;margin-bottom:16px;">
                                                <tr>
                                                    <td style="padding:18px 16px;">
                                                        <table width="100%" cellpadding="0" cellspacing="0">
                                                            <tr>
                                                                <td valign="top" width="60%">
                                                                    <div style="font-family:Inter,Arial,sans-serif;font-weight:600;font-size:14px;color:#121A26;"> {{ $item->plan_name ?? '-' }} </div>
                                                                    <div style="margin-top:12px;">
                                                                        <span style="font-family:Inter,Arial,Helvetica,sans-serif;color:#4C4C5C;font-size:14px;font-weight:500;"> ₹ {{ number_format((float) ($item->unit_price ?? 0), 0) }} </span>
                                                                        <span style="color:#9BA6B7;font-size:12px;"> Per User / {{ $item->billing_cycle ?? ($billingCycle ?? 'Year') }} </span>
                                                                    </div>
                                                                </td>
                                                                <td align="right" valign="top">
                                                                    <div style="margin-bottom:8px;">
                                                                        @include('emails.partials.mail_icon', ['icon' => 'user', 'width' => 14, 'alt' => 'Users', 'style' => 'vertical-align:middle;margin-right:4px;border:0;'])
                                                                        <span style="color:#4C4C5C;font-size:12px;font-family:Inter,Arial,Helvetica,sans-serif;vertical-align:middle;"> {{ (int) ($item->quantity ?? 1) }} Users </span>
                                                                    </div>
                                                                    <div style="font-family:Inter,Arial,Helvetica,sans-serif;font-weight:600;color:#121A26;"> Rs. {{ number_format((float) ($item->subtotal ?? 0), 2) }} </div>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                            </table>
                                        @endforeach
                                    @else
                                        <p style="color:#9BA6B7;font-size:14px;font-family:Inter,Arial,Helvetica,sans-serif;">{{ $planName ?? 'Subscription' }}</p>
                                    @endif
                                    <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                        <tr>
                                            <td style="padding:32px 0 0;">
                                                <table width="100%" cellpadding="0" cellspacing="0">
                                                    <tr>
                                                        <td style="font-family:Inter,Arial,Helvetica,sans-serif;font-size:22px;font-weight:600;color:#121A26;">
                                                            @include('emails.partials.mail_icon', ['icon' => 'payment', 'width' => 24, 'alt' => 'Payment']) Payment Summary
                                                        </td>
                                                    </tr>
                                                </table>
                                                <hr style="border:none;border-top:1px solid #E7E9EB;margin:20px 0;">
                                                <table width="100%" cellpadding="8" cellspacing="0" border="0">
                                                    <tr>
                                                        <td width="60%" style="color:#384860;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> Plan Total </td>
                                                        <td align="right" style="color:#121A26;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;font-weight:500;"> ₹{{ number_format((float) ($subtotal ?? $amount ?? 0), 0) }} </td>
                                                    </tr>
                                                    @if(($discountAmount ?? 0) > 0)
                                                    <tr>
                                                        <td style="color:#384860;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> Discount </td>
                                                        <td align="right" style="color:#121A26;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> -₹{{ number_format((float) $discountAmount, 0) }} </td>
                                                    </tr>
                                                    @endif
                                                    <tr>
                                                        <td style="color:#384860;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> GST </td>
                                                        <td align="right" style="color:#121A26;font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;"> ₹{{ number_format((float) ($taxAmount ?? 0), 0) }} </td>
                                                    </tr>
                                                </table>
                                                <hr style="border:none;border-top:1px solid #E7E9EB;margin:20px 0;">
                                                <table width="100%" cellpadding="8" cellspacing="0" border="0">
                                                    <tr>
                                                        <td width="60%" style="font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;font-weight:600;color:#121A26;padding:0;"> Total </td>
                                                        <td align="right" style="font-family:Inter,Arial,Helvetica,sans-serif;font-size:16px;font-weight:600;color:#121A26;padding:0;"> ₹{{ number_format((float) ($amount ?? 0), 0) }} </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                @include('emails.partials.mail_help')
                @include('emails.partials.mail_footer')
            </table>
        </td>
    </tr>
</table>
</body>
</html>
