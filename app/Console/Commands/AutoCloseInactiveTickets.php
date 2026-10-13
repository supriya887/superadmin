<?php

namespace App\Console\Commands;

use App\Models\SupportActivity;
use App\Models\SupportTicket;
use App\Services\TicketMailService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Closes tickets where support is waiting on the partner and the partner
 * has not replied for `services.support.auto_close_days` days, then mails the partner.
 *
 * A ticket is "waiting on partner" when, with no partner reply since then:
 *  - support's last public reply is older than the cutoff (status not In Progress), or
 *  - status is Waiting for Customer and waiting_since is older than the cutoff, or
 *  - status is Resolved and resolved_at is older than the cutoff.
 */
class AutoCloseInactiveTickets extends Command
{
    protected $signature = 'support:auto-close-inactive {--dry-run : List matching tickets without closing them}';

    protected $description = 'Auto-close tickets with no partner reply for N days and notify the partner';

    /** Statuses that are never auto-closed (support owns the next step, or already closed). */
    private const SKIP_STATUSES = ['closed', 'in progress', 'pending', 'pending assignment'];

    public function handle(TicketMailService $mailService): int
    {
        $days = max(1, (int) config('services.support.auto_close_days', 5));
        $cutoff = now()->subDays($days);
        $schema = Schema::connection('pgsql_support');

        if (!$schema->hasColumn('support_tickets', 'last_agent_reply_at')) {
            $this->error('support_tickets.last_agent_reply_at is missing. Run database/scripts/support_ticket_workflow_schema.sql first.');

            return self::FAILURE;
        }
        $hasWaitingSince = $schema->hasColumn('support_tickets', 'waiting_since');
        $hasResolvedAt = $schema->hasColumn('support_tickets', 'resolved_at');
        $hasAutoCloseAt = $schema->hasColumn('support_tickets', 'auto_close_at');

        $skip = "'" . implode("','", self::SKIP_STATUSES) . "'";
        $partnerSilentSince = fn (string $column) => function ($q) use ($column) {
            $q->whereNull('last_customer_reply_at')
                ->orWhereColumn('last_customer_reply_at', '<', $column);
        };

        $tickets = SupportTicket::query()
            ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) NOT IN ({$skip})")
            ->where(function ($q) use ($cutoff, $hasWaitingSince, $hasResolvedAt, $partnerSilentSince) {
                $q->where(function ($a) use ($cutoff, $partnerSilentSince) {
                    $a->whereNotNull('last_agent_reply_at')
                        ->where('last_agent_reply_at', '<=', $cutoff)
                        ->where($partnerSilentSince('last_agent_reply_at'));
                });

                if ($hasWaitingSince) {
                    $q->orWhere(function ($w) use ($cutoff, $partnerSilentSince) {
                        $w->whereRaw("LOWER(TRIM(status)) = 'waiting for customer'")
                            ->whereNotNull('waiting_since')
                            ->where('waiting_since', '<=', $cutoff)
                            ->where($partnerSilentSince('waiting_since'));
                    });
                }

                if ($hasResolvedAt) {
                    $q->orWhere(function ($r) use ($cutoff, $partnerSilentSince) {
                        $r->whereRaw("LOWER(TRIM(status)) = 'resolved'")
                            ->whereNotNull('resolved_at')
                            ->where('resolved_at', '<=', $cutoff)
                            ->where($partnerSilentSince('resolved_at'));
                    });
                }
            })
            ->orderBy('id')
            ->limit(200)
            ->get();

        if ($this->option('dry-run')) {
            foreach ($tickets as $ticket) {
                $this->line(sprintf(
                    '#%s [%s] last agent reply: %s, last partner reply: %s',
                    $ticket->ticket_no ?? $ticket->id,
                    $ticket->status,
                    $ticket->last_agent_reply_at ?? '-',
                    $ticket->last_customer_reply_at ?? '-'
                ));
            }
            $this->info("Dry run: {$tickets->count()} ticket(s) would be auto-closed.");

            return self::SUCCESS;
        }

        $closed = 0;
        foreach ($tickets as $ticket) {
            try {
                if (!$this->closeTicket($ticket, $days, $hasAutoCloseAt)) {
                    continue;
                }
                $closed++;

                $mail = $mailService->sendTicketAutoClosedNotification($ticket->fresh(), $days);
                Log::info('TICKET_AUTO_CLOSED', [
                    'ticket_id' => $ticket->id,
                    'ticket_no' => $ticket->ticket_no,
                    'inactive_days' => $days,
                    'mail_sent' => $mail['mail_sent'] ?? false,
                    'mail_message' => $mail['message'] ?? null,
                ]);
            } catch (\Throwable $e) {
                Log::error('Ticket auto-close failed: ' . $e->getMessage(), ['ticket_id' => $ticket->id]);
            }
        }

        $this->info("Auto-closed {$closed} ticket(s) with no partner reply for {$days} day(s).");

        return self::SUCCESS;
    }

    /**
     * Close one ticket and log a partner-visible system activity.
     * Returns false if the ticket changed status meanwhile (e.g. partner just replied).
     */
    private function closeTicket(SupportTicket $ticket, int $days, bool $hasAutoCloseAt): bool
    {
        return DB::connection('pgsql_support')->transaction(function () use ($ticket, $days, $hasAutoCloseAt) {
            $fresh = SupportTicket::query()->whereKey($ticket->id)->lockForUpdate()->first();
            if (!$fresh || in_array(strtolower(trim((string) $fresh->status)), self::SKIP_STATUSES, true)) {
                return false;
            }

            $oldStatus = (string) $fresh->status;
            $now = Carbon::now();
            $updates = [
                'status' => 'Closed',
                'previous_status' => $oldStatus,
                'status_changed_at' => $now,
                'closed_at' => $now,
            ];
            if ($hasAutoCloseAt) {
                $updates['auto_close_at'] = $now;
            }
            $fresh->update($updates);

            SupportActivity::create([
                'ticket_id' => $fresh->id,
                'user_id' => null,
                'user_type' => 'system',
                'message' => "Ticket automatically closed: no response from partner in over {$days} day(s).",
                'type' => 'closed',
                'author_name' => 'System',
                'author_role' => 'system',
                'old_status' => $oldStatus,
                'new_status' => 'Closed',
                'is_internal' => false,
                'notify_customer' => true,
            ]);

            return true;
        });
    }
}
