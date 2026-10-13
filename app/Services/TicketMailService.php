<?php

namespace App\Services;

use App\Mail\TicketCreatedMail;
use App\Models\Partner;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TicketMailService
{
    /**
     * After ticket create:
     * - To  = partner email
     * - CC  = ticket cc_emails[]
     *
     * @return array{
     *   mail_sent: bool,
     *   message: string,
     *   to: ?string,
     *   cc: list<string>,
     *   error: ?string
     * }
     */
    public function sendTicketCreatedNotifications(SupportTicket $ticket): array
    {
        $partnerId = (int) ($ticket->partner_id ?? 0);
        if ($partnerId <= 0) {
            $result = [
                'mail_sent' => false,
                'message' => 'Mail not sent: missing partner_id.',
                'to' => null,
                'cc' => [],
                'error' => 'missing_partner_id',
            ];
            Log::warning('Ticket created mail skipped: missing partner_id', [
                'ticket_id' => $ticket->id,
            ]);

            return $result;
        }

        $partner = Partner::query()->whereKey($partnerId)->first();
        $partnerEmail = strtolower(trim((string) ($partner->email ?? '')));
        if ($partnerEmail === '' || !filter_var($partnerEmail, FILTER_VALIDATE_EMAIL)) {
            $result = [
                'mail_sent' => false,
                'message' => 'Mail not sent: partner email missing or invalid.',
                'to' => $partnerEmail !== '' ? $partnerEmail : null,
                'cc' => [],
                'error' => 'invalid_partner_email',
            ];
            Log::warning('Ticket created mail skipped: partner email missing/invalid', [
                'ticket_id' => $ticket->id,
                'partner_id' => $partnerId,
            ]);

            return $result;
        }

        $ccEmails = $this->normalizeCcEmails($ticket, $partnerEmail);
        $recipientName = trim((string) ($partner->name ?? $partner->company_name ?? ''));
        if ($recipientName === '') {
            $recipientName = 'Partner';
        }

        try {
            $mailer = Mail::to($partnerEmail);
            if ($ccEmails !== []) {
                $mailer->cc($ccEmails);
            }
            $mailer->send(new TicketCreatedMail($ticket, $recipientName));

            return [
                'mail_sent' => true,
                'message' => 'Mail sent successfully to partner'
                    . ($ccEmails !== [] ? ' (with CC).' : '.'),
                'to' => $partnerEmail,
                'cc' => $ccEmails,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('Ticket created mail failed: ' . $e->getMessage(), [
                'ticket_id' => $ticket->id,
                'to' => $partnerEmail,
                'cc' => $ccEmails,
            ]);

            return [
                'mail_sent' => false,
                'message' => 'Mail send failed: ' . $e->getMessage(),
                'to' => $partnerEmail,
                'cc' => $ccEmails,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Notify partner of a public support reply (To partner, CC cc_emails).
     * Sets Reply-To so inbound email can map back to the ticket.
     */
    public function sendTicketReplyNotification(
        SupportTicket $ticket,
        string $replyMessage,
        string $repliedBy = 'Support Team'
    ): array {
        $resolved = $this->resolvePartnerMail($ticket);
        if ($resolved['error'] !== null) {
            return $resolved;
        }

        $partnerEmail = $resolved['to'];
        $ccEmails = $resolved['cc'];
        $recipientName = $resolved['name'];
        $replyTo = $this->ticketReplyToAddress($ticket);

        try {
            $mailer = Mail::to($partnerEmail);
            if ($ccEmails !== []) {
                $mailer->cc($ccEmails);
            }
            $mailer->send(new \App\Mail\TicketReplyMail(
                $ticket,
                $recipientName,
                $replyMessage,
                $repliedBy,
                $replyTo
            ));

            return [
                'mail_sent' => true,
                'message' => 'Reply mail sent to partner' . ($ccEmails !== [] ? ' (with CC).' : '.'),
                'to' => $partnerEmail,
                'cc' => $ccEmails,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('Ticket reply mail failed: ' . $e->getMessage(), [
                'ticket_id' => $ticket->id,
                'to' => $partnerEmail,
            ]);

            return [
                'mail_sent' => false,
                'message' => 'Mail send failed: ' . $e->getMessage(),
                'to' => $partnerEmail,
                'cc' => $ccEmails,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Notify partner (To) + cc_emails (CC) that the ticket was auto-closed for no response.
     */
    public function sendTicketAutoClosedNotification(SupportTicket $ticket, int $inactiveDays): array
    {
        $resolved = $this->resolvePartnerMail($ticket);
        if ($resolved['error'] !== null) {
            return $resolved;
        }

        $partnerEmail = $resolved['to'];
        $ccEmails = $resolved['cc'];

        try {
            $mailer = Mail::to($partnerEmail);
            if ($ccEmails !== []) {
                $mailer->cc($ccEmails);
            }
            $mailer->send(new \App\Mail\TicketAutoClosedMail($ticket, $resolved['name'], $inactiveDays));

            return [
                'mail_sent' => true,
                'message' => 'Auto-close mail sent to partner' . ($ccEmails !== [] ? ' (with CC).' : '.'),
                'to' => $partnerEmail,
                'cc' => $ccEmails,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('Ticket auto-close mail failed: ' . $e->getMessage(), [
                'ticket_id' => $ticket->id,
                'to' => $partnerEmail,
            ]);

            return [
                'mail_sent' => false,
                'message' => 'Mail send failed: ' . $e->getMessage(),
                'to' => $partnerEmail,
                'cc' => $ccEmails,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Reply-To like: support+15@yourdomain.com (ticket id for inbound parse).
     */
    public function ticketReplyToAddress(SupportTicket $ticket): ?string
    {
        $base = trim((string) (config('services.support.reply_to') ?: config('mail.from.address', '')));
        if (!filter_var($base, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        if (str_contains($base, '+')) {
            return $base;
        }
        [$local, $domain] = explode('@', $base, 2);

        return $local . '+' . (int) $ticket->id . '@' . $domain;
    }

    /**
     * @return array{mail_sent: bool, message: string, to: ?string, cc: list<string>, error: ?string, name?: string}
     */
    private function resolvePartnerMail(SupportTicket $ticket): array
    {
        $partnerId = (int) ($ticket->partner_id ?? 0);
        if ($partnerId <= 0) {
            return [
                'mail_sent' => false,
                'message' => 'Mail not sent: missing partner_id.',
                'to' => null,
                'cc' => [],
                'error' => 'missing_partner_id',
            ];
        }

        $partner = Partner::query()->whereKey($partnerId)->first();
        $partnerEmail = strtolower(trim((string) ($partner->email ?? '')));
        if ($partnerEmail === '' || !filter_var($partnerEmail, FILTER_VALIDATE_EMAIL)) {
            return [
                'mail_sent' => false,
                'message' => 'Mail not sent: partner email missing or invalid.',
                'to' => $partnerEmail !== '' ? $partnerEmail : null,
                'cc' => [],
                'error' => 'invalid_partner_email',
            ];
        }

        $name = trim((string) ($partner->name ?? $partner->company_name ?? ''));
        if ($name === '') {
            $name = 'Partner';
        }

        return [
            'mail_sent' => false,
            'message' => '',
            'to' => $partnerEmail,
            'cc' => $this->normalizeCcEmails($ticket, $partnerEmail),
            'error' => null,
            'name' => $name,
        ];
    }

    /**
     * @return list<string>
     */
    private function normalizeCcEmails(SupportTicket $ticket, string $partnerEmail): array
    {
        $cc = $ticket->cc_emails;
        if (is_string($cc) && $cc !== '') {
            $decoded = json_decode($cc, true);
            $cc = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($cc)) {
            return [];
        }

        $emails = [];
        foreach ($cc as $email) {
            if (!is_string($email)) {
                continue;
            }
            $email = strtolower(trim($email));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            if ($email === $partnerEmail) {
                continue;
            }
            $emails[] = $email;
        }

        return array_values(array_unique($emails));
    }

    /**
     * Notify partner (To) + cc_emails (CC) that ticket status changed.
     */
    public function sendTicketStatusUpdateNotification(
        SupportTicket $ticket,
        string $oldStatus,
        string $newStatus,
        string $updatedBy = 'Support'
    ): array {
        $resolved = $this->resolvePartnerMail($ticket);
        if ($resolved['error'] !== null) {
            return $resolved;
        }

        $partnerEmail = $resolved['to'];
        $ccEmails = $resolved['cc'];
        $recipientName = $resolved['name'];
        $ticketNo = $ticket->ticket_no ?? ('#' . $ticket->id);
        $subject = "Ticket {$ticketNo} status updated: {$oldStatus} → {$newStatus}";
        $body = "Hello {$recipientName},\n\n"
            . "Ticket {$ticketNo} status was updated by {$updatedBy}.\n"
            . "Subject: " . ($ticket->subject ?? '-') . "\n"
            . "Previous status: {$oldStatus}\n"
            . "New status: {$newStatus}\n\n"
            . "Thank you,\nTizzy Support";

        try {
            Mail::raw($body, function ($message) use ($partnerEmail, $ccEmails, $subject) {
                $message->to($partnerEmail)->subject($subject);
                if ($ccEmails !== []) {
                    $message->cc($ccEmails);
                }
            });

            return [
                'mail_sent' => true,
                'message' => 'Status update mail sent to partner'
                    . ($ccEmails !== [] ? ' (with CC).' : '.'),
                'to' => $partnerEmail,
                'cc' => $ccEmails,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('Ticket status update mail failed: ' . $e->getMessage(), [
                'ticket_id' => $ticket->id,
                'to' => $partnerEmail,
                'cc' => $ccEmails,
            ]);

            return [
                'mail_sent' => false,
                'message' => 'Mail send failed: ' . $e->getMessage(),
                'to' => $partnerEmail,
                'cc' => $ccEmails,
                'error' => $e->getMessage(),
            ];
        }
    }
}
