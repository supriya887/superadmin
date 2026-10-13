<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the customer (and optionally cc'd to partner) once a plan renewal
 * is confirmed in the database. Renders a Tizzy Group branded card with the
 * plan name, amount paid, and the new validity date.
 */
class PlanRenewedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $planName;
    public string $amountPaid;
    public string $validityDate;
    public string $customerName;
    public string $orderNo;

    public function __construct(
        string $planName,
        float|string $amountPaid,
        string $validityDate,
        string $customerName = 'Customer',
        string $orderNo = ''
    ) {
        $this->planName     = $planName !== '' ? $planName : 'Subscription';
        $this->amountPaid   = number_format((float) $amountPaid, 2, '.', ',');
        $this->validityDate = $validityDate;
        $this->customerName = $customerName !== '' ? $customerName : 'Customer';
        $this->orderNo      = $orderNo;
    }

    public function build()
    {
        return $this->subject('Your Tizzy Group plan has been renewed')
            ->view('emails.plan_renewed')
            ->with([
                'planName'     => $this->planName,
                'amountPaid'   => $this->amountPaid,
                'validityDate' => $this->validityDate,
                'customerName' => $this->customerName,
                'orderNo'      => $this->orderNo,
            ]);
    }
}
