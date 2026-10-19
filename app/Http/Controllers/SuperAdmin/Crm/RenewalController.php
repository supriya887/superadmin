<?php

namespace App\Http\Controllers\SuperAdmin\Crm;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RenewalController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->orders();

        if (!$query) {
            $renewals = new LengthAwarePaginator([], 0, 10, 1, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);

            return view('superadmin.crm.renewals.index', compact('renewals'));
        }

        $today = Carbon::today();

        if ($request->filled('search')) {
            $search = '%' . trim((string) $request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('order_no', 'ilike', $search)
                    ->orWhere('company_name', 'ilike', $search)
                    ->orWhere('customer_name', 'ilike', $search)
                    ->orWhere('email', 'ilike', $search)
                    ->orWhere('plan_name', 'ilike', $search)
                    ->orWhere('domain_name', 'ilike', $search);
            });
        }

        if ($request->input('status') === 'expiring') {
            $query->whereDate('renewal_date', '<=', $today->copy()->addDays(30));
        } elseif ($request->input('status') === 'active') {
            $query->whereDate('renewal_date', '>', $today->copy()->addDays(30));
        }

        if ($request->filled('from')) {
            $query->whereDate('renewal_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('renewal_date', '<=', $request->to);
        }

        $page = $query->orderBy('renewal_date')->paginate(10)->withQueryString();
        $leads = Lead::whereIn('id', $page->getCollection()->pluck('lead_id')->filter()->unique())->get()->keyBy('id');
        $page->setCollection($page->getCollection()->map(function ($order) use ($leads, $today) {
            return $this->row($order, $leads->get($order->lead_id), $today);
        }));

        return view('superadmin.crm.renewals.index', ['renewals' => $page]);
    }

    public function show(int $id)
    {
        $order = $this->orders()?->where('id', $id)->first();
        abort_unless($order, 404);

        $lead = $order->lead_id ? Lead::find($order->lead_id) : null;
        $row = $this->row($order, $lead, Carbon::today());

        return view('superadmin.crm.renewals.show', [
            'row' => $row,
            'timeline' => $this->timeline($row),
        ]);
    }

    protected function orders()
    {
        if (!Schema::connection('pgsql_crm')->hasTable('crm_orders')) {
            return null;
        }
        if (!Schema::connection('pgsql_crm')->hasColumn('crm_orders', 'renewal_date')) {
            return null;
        }

        return DB::connection('pgsql_crm')->table('crm_orders')->whereNotNull('renewal_date');
    }

    protected function row(object $order, ?Lead $lead, Carbon $today): array
    {
        $plans = json_decode($order->plans ?? '[]', true);
        $plans = is_array($plans) ? $plans : [];
        $renewalDate = Carbon::parse($order->renewal_date)->startOfDay();
        $expiring = $renewalDate->lte($today->copy()->addDays(30));
        $quantity = max(0, (int) ($order->quantity ?: 0));
        $total = (float) ($order->total_amount ?: 0);

        return [
            'id' => (int) $order->id,
            'code' => $order->order_no,
            'lead_id' => $order->lead_id ? (int) $order->lead_id : null,
            'company_name' => $order->company_name,
            'name' => $order->customer_name ?: $lead?->name,
            'email' => $order->email ?: $lead?->email,
            'mobile' => $lead?->mobile,
            'mobile_code' => $lead?->mobile_code,
            'service' => $plans[0]['provider_name'] ?? 'CRM',
            'plan' => $order->plan_name ?: ($plans[0]['name'] ?? 'Plan'),
            'domain' => $order->domain_name,
            'quantity' => $quantity ?: null,
            'rate' => $quantity > 0 ? round($total / $quantity, 2) : $total,
            'period' => $order->period ?: null,
            'start_date' => $order->start_date ?? null,
            'created_at' => $order->created_at ?? null,
            'renewal_date' => $renewalDate->toDateString(),
            'status' => $expiring ? 'expiring' : 'active',
            'status_label' => $expiring ? 'Expiring' : 'Active',
        ];
    }

    protected function timeline(array $row): array
    {
        $events = [];

        if (!empty($row['created_at'])) {
            $events[] = [
                'title' => 'Order created',
                'text' => $row['code'] . ' was placed',
                'when' => Carbon::parse($row['created_at'])->format('d M Y'),
            ];
        }

        if (!empty($row['start_date'])) {
            $events[] = [
                'title' => 'Subscription started',
                'text' => $row['plan'],
                'when' => Carbon::parse($row['start_date'])->format('d M Y'),
            ];
        }

        $events[] = [
            'title' => 'Renewal scheduled',
            'text' => 'Renews on ' . Carbon::parse($row['renewal_date'])->format('d M Y'),
            'when' => Carbon::parse($row['renewal_date'])->format('d M Y'),
        ];

        if (!$row['lead_id'] || !Schema::connection('pgsql_crm')->hasTable('lead_followups')) {
            return $events;
        }

        DB::connection('pgsql_crm')
            ->table('lead_followups')
            ->where('lead_id', $row['lead_id'])
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->each(function ($note) use (&$events) {
                $events[] = [
                    'title' => 'Follow-up' . ($note->lead_status ? ' · ' . $note->lead_status : ''),
                    'text' => $note->note ?: 'Follow-up saved',
                    'when' => $note->created_at ? Carbon::parse($note->created_at)->format('d M Y') : '',
                ];
            });

        return $events;
    }
}
