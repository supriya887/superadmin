<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\SupportActivity;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Inbound email → ticket reply (shows in SuperAdmin conversation).
 *
 * Configure Mailgun / SendGrid Inbound Parse / Postmark Inbound to POST:
 *   POST /api/v1/support/inbound-email?token=YOUR_SUPPORT_INBOUND_TOKEN
 *
 * Accepted fields (any provider):
 *   recipient|To|to, from|sender|From, subject|Subject,
 *   stripped-text|body-plain|text|TextBody|body|html|HtmlBody
 */
class SupportInboundMailController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $expected = trim((string) config('services.support.inbound_token', ''));
            if ($expected !== '') {
                $got = (string) ($request->query('token') ?: $request->header('X-Support-Inbound-Token') ?: '');
                if (!hash_equals($expected, $got)) {
                    return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
                }
            }

            $to = $this->first($request, ['recipient', 'To', 'to', 'envelope']);
            $from = $this->first($request, ['from', 'sender', 'From', 'FromFull']);
            $subject = $this->first($request, ['subject', 'Subject']);
            $body = $this->extractBody($request);

            Log::info('Inbound ticket mail received', [
                'to' => $to,
                'from' => $from,
                'subject' => $subject,
                'body_len' => strlen($body),
            ]);

            if ($body === '') {
                return response()->json(['success' => false, 'message' => 'Empty body.'], 422);
            }

            $ticket = $this->resolveTicket($to, $subject);
            if (!$ticket) {
                Log::warning('Inbound ticket mail: ticket not found', compact('to', 'subject'));

                return response()->json(['success' => false, 'message' => 'Ticket not found.'], 404);
            }

            $fromEmail = $this->extractEmail($from);
            $author = $fromEmail !== '' ? $fromEmail : 'Partner';
            if (!empty($ticket->partner_id)) {
                $partner = Partner::query()->whereKey((int) $ticket->partner_id)->first();
                if ($partner) {
                    $author = trim((string) ($partner->name ?? $partner->company_name ?? $author)) ?: $author;
                }
            }

            $payload = [
                'ticket_id' => $ticket->id,
                'user_id' => (int) ($ticket->partner_id ?? 0) ?: null,
                'user_type' => 'partner',
                'message' => $body,
                'type' => 'reply',
            ];
            if (Schema::connection('pgsql_support')->hasColumn('support_activities', 'is_internal')) {
                $payload['is_internal'] = false;
            }
            if (Schema::connection('pgsql_support')->hasColumn('support_activities', 'author_name')) {
                $payload['author_name'] = $author;
            }
            if (Schema::connection('pgsql_support')->hasColumn('support_activities', 'author_role')) {
                $payload['author_role'] = 'partner';
            }

            $activity = SupportActivity::create($payload);

            $updates = [];
            if (Schema::connection('pgsql_support')->hasColumn('support_tickets', 'last_customer_reply_at')) {
                $updates['last_customer_reply_at'] = now();
            }
            $status = strtolower(str_replace('_', ' ', trim((string) ($ticket->status ?? ''))));
            if (in_array($status, ['waiting for customer', 'resolved'], true)) {
                $updates['status'] = 'In Progress';
                $updates['previous_status'] = $ticket->status;
                $updates['status_changed_at'] = now();
                
            }
            if ($updates !== []) {
                $ticket->update($updates);
            }

            return response()->json([
                'success' => true,
                'message' => 'Inbound reply saved.',
                'data' => [
                    'ticket_id' => (int) $ticket->id,
                    'activity_id' => (int) $activity->id,
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Inbound ticket mail failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process inbound mail.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function first(Request $request, array $keys): string
    {
        foreach ($keys as $key) {
            $val = $request->input($key);
            if (is_string($val) && trim($val) !== '') {
                return trim($val);
            }
        }

        return '';
    }

    private function extractBody(Request $request): string
    {
        $raw = $this->first($request, [
            'stripped-text',
            'body-plain',
            'text',
            'TextBody',
            'plain',
            'body',
            'HtmlBody',
            'html',
            'body-html',
        ]);

        // Strip HTML if needed
        if ($raw !== '' && str_contains($raw, '<')) {
            $raw = trim(html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        // Keep only new reply (drop quoted thread)
        $raw = preg_split('/\r?\nOn .+ wrote:\r?\n/i', $raw)[0] ?? $raw;
        $raw = preg_split('/\r?\n-{2,}\s*Original Message\s*-{2,}/i', $raw)[0] ?? $raw;
        $raw = preg_split('/\r?\nFrom:\s.+\r?\nSent:/i', $raw)[0] ?? $raw;

        return trim($raw);
    }

    private function resolveTicket(string $to, string $subject): ?SupportTicket
    {
        // support+17@domain.com
        if (preg_match('/\+([0-9]+)@/i', $to, $m)) {
            $ticket = SupportTicket::query()->find((int) $m[1]);
            if ($ticket) {
                return $ticket;
            }
        }

        // Subject: Re: [SUP-26-0017] ...
        if (preg_match('/\[([A-Za-z0-9\-]+)\]/', $subject, $m)) {
            $ticketNo = strtoupper(trim($m[1]));
            $ticket = SupportTicket::query()->whereRaw('UPPER(TRIM(ticket_no)) = ?', [$ticketNo])->first();
            if ($ticket) {
                return $ticket;
            }
        }

        // Subject contains SUP-26-0017 without brackets
        if (preg_match('/\b(SUP[-\s]?\d+[-\s]?\d+)\b/i', $subject, $m)) {
            $ticketNo = strtoupper(preg_replace('/\s+/', '', $m[1]));
            $ticket = SupportTicket::query()->whereRaw('UPPER(REPLACE(ticket_no, \' \', \'\')) = ?', [$ticketNo])->first();
            if ($ticket) {
                return $ticket;
            }
        }

        // Subject: ticket #17 / ticket_id 17
        if (preg_match('/\b(?:ticket(?:\s*(?:id|#|no\.?))?\s*[:=]?\s*)(\d+)\b/i', $subject, $m)) {
            return SupportTicket::query()->find((int) $m[1]);
        }

        return null;
    }

    private function extractEmail(string $from): string
    {
        if (preg_match('/<([^>]+)>/', $from, $m)) {
            return strtolower(trim($m[1]));
        }

        return strtolower(trim($from));
    }
}
