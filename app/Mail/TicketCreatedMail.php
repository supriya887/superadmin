<?php

namespace App\Mail;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TicketCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public SupportTicket $ticket;
    public string $recipientName;

    public function __construct(SupportTicket $ticket, string $recipientName = 'Customer')
    {
        $this->ticket = $ticket;
        $this->recipientName = $recipientName !== '' ? $recipientName : 'Customer';
    }

    public function build()
    {
        $ticketNo = (string) ($this->ticket->ticket_no ?? '');
        $subject = $ticketNo !== ''
            ? 'Support ticket created — ' . $ticketNo
            : 'Support ticket created';

        return $this->subject($subject)
            ->view('emails.ticket_created_ack')
            ->with([
                'recipientName' => $this->recipientName,
                'ticket' => $this->ticket,
                'ticketNo' => $ticketNo,
                'helpCenterUrl' => 'https://partner.tizzygroup.com',
                'websiteUrl' => 'https://tizzygroup.com',
                'twitterUrl' => 'https://twitter.com',
                'linkedinUrl' => 'https://www.linkedin.com',
                'youtubeUrl' => 'https://www.youtube.com',
            ]);
    }
}
