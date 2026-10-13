<?php

namespace App\Mail;

use App\Models\PurchaseRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PurchaseRequestApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PurchaseRequest $purchaseRequest,
        public string $checkoutLink
    ) {
    }

    public function build(): self
    {
        return $this->subject('Your Plan Upgrade Request Has Been Approved')
            ->view('emails.purchase_request_approved');
    }
}
