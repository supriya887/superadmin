<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PartnerCreditRequestDecisionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $partnerName,
        public string $decision,
        public float $amount,
        public int $requestId
    ) {
    }

    public function build(): self
    {
        $subject = $this->decision === 'approved'
            ? 'Your credit request was approved'
            : 'Update on your credit request';

        return $this->subject($subject)->view('emails.partner_credit_request_decision');
    }
}
