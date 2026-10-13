<?php

namespace App\Mail;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TicketReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SupportTicket $ticket,
        public string $recipientName,
        public string $replyMessage,
        public string $repliedBy = 'Support Team',
        public ?string $replyToAddress = null
    ) {}

    public function build()
    {
        $ticketNo = (string) ($this->ticket->ticket_no ?? ('#' . $this->ticket->id));
        $mail = $this->subject("Re: [{$ticketNo}] " . (string) ($this->ticket->subject ?? 'Support ticket'))
            ->view('emails.ticket_reply')
            ->with([
                'recipientName' => $this->recipientName,
                'ticket' => $this->ticket,
                'ticketNo' => $ticketNo,
                'replyMessage' => $this->replyMessage,
                'repliedBy' => $this->repliedBy,
            ]);

        if ($this->replyToAddress) {
            $mail->replyTo($this->replyToAddress, 'Tizzy Support');
        }

        return $mail;
    }
}
