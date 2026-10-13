<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PartnerLicenseDecreaseDecisionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $partnerName,
        public string $decision,
        public int $requestId,
        public int $orderId,
        public int $currentLicenses,
        public int $requestedLicenses,
        public ?string $adminNote = null
    ) {
    }

    public function build(): self
    {
        $subject = match ($this->decision) {
            'approved' => 'License decrease request approved',
            'reverted' => 'License decrease approval reverted',
            default => 'License decrease request rejected',
        };

        return $this->subject($subject)->view('emails.partner_license_decrease_decision');
    }
}
