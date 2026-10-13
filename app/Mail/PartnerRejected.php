<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Partner as PartnerRecord;
use App\Models\Partner\Partner as PartnerAccount;

class PartnerRejected extends Mailable
{
    use Queueable, SerializesModels;

    public PartnerRecord|PartnerAccount $partner;

    public string $rejectionReason;

    /**
     * @param  PartnerRecord|PartnerAccount  $partner
     */
    public function __construct(PartnerRecord|PartnerAccount $partner, string $rejectionReason = '')
    {
        $this->partner = $partner;
        $this->rejectionReason = trim($rejectionReason);
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Partner Account Rejected')
                    ->view('emails.partner_rejected');
    }
}
