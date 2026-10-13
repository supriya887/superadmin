<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Partner;
use App\Models\PartnerUser;
use App\Models\SupportTicket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;


class GlobalSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $partner = $request->user();
        if (!$partner instanceof Partner) {
            return response()->json(['success' => false, 'message' => 'Unauthorized partner.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2|max:100',
            'limit' => 'nullable|integer|min:1|max:20',
            'partner_user_id' => 'nullable|integer|min:1',
        ], [
            'query.required' => 'Please enter something to search.',
            'query.min' => 'Search term must be at least 2 characters.',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $partnerId = (int) $partner->id;
        $partnerUserId = (int) $request->input('partner_user_id') ?: null;
        if ($partnerUserId && !PartnerUser::where('partner_id', $partnerId)->whereKey($partnerUserId)->exists()) {
            return response()->json(['success' => false, 'message' => 'Invalid partner user.'], 403);
        }

        $term = trim((string) $request->input('query'));
        $limit = (int) $request->input('limit', 5);
        $like = '%' . addcslashes($term, '%_\\') . '%';

        $results = [
            'tickets' => $this->run('tickets', fn () => SupportTicket::query()
                ->forPartnerActor($partnerId, $partnerUserId)
                ->where(fn (Builder $q) => $this->match($q, ['ticket_no', 'subject', 'status'], $like, $term))
                ->latest()->limit($limit)
                ->get(['id', 'ticket_no', 'subject', 'status', 'priority', 'created_at'])
                ->map(fn ($t) => [
                    'id' => (int) $t->id,
                    'title' => '#' . $t->ticket_no,
                    'subtitle' => (string) $t->subject,
                    'status' => $t->status,
                    'priority' => $t->priority,
                    'created_at' => $t->created_at?->format('Y-m-d H:i:s'),
                ])),

            'customers' => $this->run('customers', fn () => Customer::query()
                ->where('partner_id', $partnerId)
                ->where(fn (Builder $q) => $this->match($q, ['name', 'company_name', 'email', 'mobile', 'customer_no'], $like, $term))
                ->latest()->limit($limit)
                ->get(['id', 'customer_no', 'name', 'company_name', 'email', 'mobile', 'status'])
                ->map(fn ($c) => [
                    'id' => (int) $c->id,
                    'customer_id' => (int) $c->id,
                    'title' => (string) ($c->company_name ?: $c->name),
                    'subtitle' => trim(implode(' · ', array_filter([$c->name, $c->email, $c->mobile]))),
                    'customer_no' => $c->customer_no,
                    'email' => $c->email,
                    'mobile' => $c->mobile,
                    'status' => $c->status,
                ])),

            'users' => $partnerUserId ? [] : $this->run('users', fn () => PartnerUser::query()
                ->where('partner_id', $partnerId)
                ->where(fn (Builder $q) => $this->match($q, ['name', 'email', 'mobile'], $like, $term))
                ->latest()->limit($limit)
                ->get(['id', 'name', 'email', 'mobile', 'status'])
                ->map(fn ($u) => [
                    'id' => (int) $u->id,
                    'title' => (string) $u->name,
                    'subtitle' => trim(implode(' · ', array_filter([$u->email, $u->mobile]))),
                    'email' => $u->email,
                    'mobile' => $u->mobile,
                    'status' => $u->status,
                ])),

            'orders' => $this->run('orders', fn () => Order::query()
                ->where('partner_id', $partnerId)
                ->where(fn (Builder $q) => $this->match($q, ['order_no', 'domain_name'], $like, $term))
                ->latest()->limit($limit)
                ->get(['id', 'order_no', 'user_id', 'domain_name', 'status', 'total_amount', 'created_at'])
                ->map(fn ($o) => [
                    'id' => (int) $o->id,
                    'customer_id' => $o->user_id ? (int) $o->user_id : null,
                    'title' => (string) $o->order_no,
                    'subtitle' => (string) ($o->domain_name ?? ''),
                    'status' => $o->status,
                    'total_amount' => $o->total_amount !== null ? (float) $o->total_amount : null,
                    'created_at' => $o->created_at?->format('Y-m-d H:i:s'),
                ])),
        ];

        $labels = ['tickets' => 'Tickets', 'customers' => 'Customers', 'users' => 'Users', 'orders' => 'Orders'];
        $groups = [];
        foreach ($results as $type => $items) {
            $groups[] = [
                'type' => $type,
                'label' => $labels[$type],
                'count' => count($items),
                'items' => array_map(fn ($item) => ['type' => $type] + $item, $items),
            ];
        }
        $total = array_sum(array_column($groups, 'count'));

        return response()->json([
            'success' => true,
            'message' => $total ? 'Search results fetched successfully.' : 'No results found.',
            'data' => [
                'query' => $term,
                'total' => $total,
                'results' => $groups,
            ],
        ]);
    }

    /** Case-insensitive contains on each column (cast to text so numeric columns are safe) + exact id for numeric terms. */
    private function match(Builder $q, array $columns, string $like, string $term): void
    {
        foreach ($columns as $column) {
            $q->orWhereRaw("CAST({$column} AS TEXT) ILIKE ?", [$like]);
        }
        if (ctype_digit($term) && strlen($term) <= 18) {
            $q->orWhere('id', (int) $term);
        }
    }

    /** One failing module (e.g. a DB connection down) must not break the whole search. */
    private function run(string $module, callable $query): array
    {
        try {
            return $query()->values()->all();
        } catch (Throwable $e) {
            Log::warning("Global search [{$module}] failed: " . $e->getMessage());

            return [];
        }
    }
}
