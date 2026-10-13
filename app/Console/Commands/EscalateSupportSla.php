<?php

namespace App\Console\Commands;

use App\Models\AdminUserCreation;
use App\Models\SupportActivity;
use App\Models\SupportTicket;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EscalateSupportSla extends Command
{
    protected $signature = 'support:escalate-sla';

    protected $description = 'Escalate tickets that missed first-response SLA (15 min)';

    public function handle(): int
    {
        $tickets = SupportTicket::query()
            ->whereNull('first_response_at')
            ->whereNotNull('first_response_due_at')
            ->where('first_response_due_at', '<', now())
            ->where(function ($q) {
                $q->where('is_escalated', false)->orWhereNull('is_escalated');
            })
            ->whereNotIn('status', ['Closed', 'Resolved'])
            ->limit(100)
            ->get();

        $count = 0;
        foreach ($tickets as $ticket) {
            $old = (string) $ticket->status;
            $ticket->update([
                'is_escalated' => true,
                'previous_status' => $old,
                'status' => 'Escalated',
                'status_changed_at' => now(),
            ]);

            SupportActivity::create([
                'ticket_id' => $ticket->id,
                'user_id' => null,
                'user_type' => 'system',
                'message' => 'SLA breached: first response not sent within 15 minutes. Ticket escalated.',
                'type' => 'status_change',
                'author_name' => 'System',
                'author_role' => 'system',
                'old_status' => $old,
                'new_status' => 'Escalated',
                'is_internal' => true,
            ]);

            $this->alertLeads($ticket);
            $count++;
        }

        $this->info("Escalated {$count} ticket(s).");

        return self::SUCCESS;
    }

    private function alertLeads(SupportTicket $ticket): void
    {
        $subject = 'SLA Breach: Ticket #' . ($ticket->ticket_no ?? $ticket->id);
        $body = $subject . ' — "' . ($ticket->subject ?? '') . '" exceeded first-response SLA and is Escalated.';

        Log::warning('TICKET_SLA_BREACH', [
            'ticket_id' => $ticket->id,
            'ticket_no' => $ticket->ticket_no,
            'assigned_to' => $ticket->assigned_to,
            'due_at' => (string) $ticket->first_response_due_at,
        ]);

        $leads = AdminUserCreation::query()
            ->where('status', 1)
            ->whereIn('role', [1, 2])
            ->limit(20)
            ->get();

        foreach ($leads as $lead) {
            $email = trim((string) ($lead->email ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            try {
                Mail::raw($body, function ($message) use ($email, $subject) {
                    $message->to($email)->subject($subject);
                });
            } catch (\Throwable $e) {
                Log::error('SLA breach mail failed: ' . $e->getMessage());
            }
        }
    }
}
