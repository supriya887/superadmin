<?php

namespace App\Mail;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TicketAutoClosedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SupportTicket $ticket,
        public string $recipientName,
        public int $inactiveDays,
        public int $reopenHours = 72
    ) {}

    public function build()
    {
        $ticketNo = (string) ($this->ticket->ticket_no ?? ('#' . $this->ticket->id));

        return $this->subject("[{$ticketNo}] Ticket closed due to no response: " . (string) ($this->ticket->subject ?? 'Support ticket'))
            ->view('emails.ticket_auto_closed')
            ->with([
                'recipientName' => $this->recipientName,
                'ticket' => $this->ticket,
                'ticketNo' => $ticketNo,
                'inactiveDays' => $this->inactiveDays,
                'reopenHours' => $this->reopenHours,
            ]);
    }
}
