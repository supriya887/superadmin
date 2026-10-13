<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the customer once a plan upgrade (or downgrade) is confirmed in
 * the database. Renders a Tizzy Group branded card with the new plan
 * name, amount paid, and the new validity date.
 *
 * If a generated invoice PDF is available for the order, it is attached
 * automatically via the optional $invoicePdf payload:
 *   ['name' => 'invoice-123.pdf', 'content' => '<binary>', 'mime' => 'application/pdf']
 */
class PlanUpgradedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $planName;
    public string $amountPaid;
    public string $validityDate;
    public string $customerName;
    public string $orderNo;
    public string $action; // 'upgrade' or 'downgrade'
    public ?array $invoicePdf;

    public function __construct(
        string $planName,
        float|string $amountPaid,
        string $validityDate,
        string $customerName = 'Customer',
        string $orderNo = '',
        string $action = 'upgrade',
        ?array $invoicePdf = null
    ) {
        $this->planName     = $planName !== '' ? $planName : 'Subscription';
        $this->amountPaid   = number_format((float) $amountPaid, 2, '.', ',');
        $this->validityDate = $validityDate;
        $this->customerName = $customerName !== '' ? $customerName : 'Customer';
        $this->orderNo      = $orderNo;
        $this->action       = strtolower($action) === 'downgrade' ? 'downgrade' : 'upgrade';
        $this->invoicePdf   = $invoicePdf;
    }

    public function build()
    {
        $subject = $this->action === 'downgrade'
            ? 'Your Tizzy Group plan has been downgraded'
            : 'Your Tizzy Group plan has been upgraded';

        $mail = $this->subject($subject)
            ->view('emails.plan_upgraded')
            ->with([
                'planName'     => $this->planName,
                'amountPaid'   => $this->amountPaid,
                'validityDate' => $this->validityDate,
                'customerName' => $this->customerName,
                'orderNo'      => $this->orderNo,
                'action'       => $this->action,
            ]);

        if (
            is_array($this->invoicePdf)
            && !empty($this->invoicePdf['content'])
            && !empty($this->invoicePdf['name'])
        ) {
            $mail->attachData(
                $this->invoicePdf['content'],
                $this->invoicePdf['name'],
                ['mime' => $this->invoicePdf['mime'] ?? 'application/pdf']
            );
        }

        return $mail;
    }
}
