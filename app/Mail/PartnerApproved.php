<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Partner as PartnerRecord;
use App\Models\Partner\Partner as PartnerAccount;

class PartnerApproved extends Mailable
{
    use Queueable, SerializesModels;

    public PartnerRecord|PartnerAccount $partner;

    /**
     * @param  PartnerRecord|PartnerAccount  $partner
     */
    public function __construct(PartnerRecord|PartnerAccount $partner)
    {
        $this->partner = $partner;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Partner Account Approved!')
                    ->view('emails.partner_approved');
    }
}
