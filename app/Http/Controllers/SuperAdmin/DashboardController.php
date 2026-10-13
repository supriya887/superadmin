<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Provider;
use App\Models\Partner;
use App\Models\Customer;
use App\Models\DomainTransferRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use App\Services\RenewalService;

class DashboardController extends Controller
{
    protected $renewalService;

    public function __construct(RenewalService $renewalService)
    {
        $this->renewalService = $renewalService;
    }

    /**
     * Get renewals data for dashboard table (same as renewals page)
     */
    private function getDashboardRenewalsData()
    {
        $today = Carbon::now()->startOfDay();
        $expiringSoonDays = 30;
        $expiringSoonThreshold = $today->copy()->addDays($expiringSoonDays)->endOfDay();

        $renewalsData = Order::with(['partner', 'customer', 'plan'])
            ->whereIn('orders.status', ['completed', 'active'])
            ->where(function ($query) {
                $query->whereNotNull('orders.subscription_end_date')
                      ->orWhereNotNull('orders.renewal_at');
            })
            ->where(function ($query) use ($today, $expiringSoonThreshold) {
                $query->whereRaw(
                    'DATE(COALESCE(subscription_end_date, renewal_at)) BETWEEN ? AND ?',
                    [$today->toDateString(), $expiringSoonThreshold->toDateString()]
                );
            })
            ->orderBy('subscription_end_date', 'asc')
            ->take(7)
            ->get();

        return $renewalsData
            ->map(function ($renewal) use ($today) {
                $renewalDate = $renewal->subscription_end_date
                    ? Carbon::parse($renewal->subscription_end_date)
                    : ($renewal->renewal_at ? Carbon::parse($renewal->renewal_at) : null);

                $daysLeft = $renewalDate
                    ? $today->diffInDays($renewalDate, false)
                    : null;

                $partnerName = optional($renewal->partner)->name ?? '-';
                $customer = $renewal->customer;
                $customerName = optional($customer)->name ?? optional($customer)->company_name ?? '-';
                $customerEmail = optional($customer)->email ?? '-';
                $customerMobile = optional($customer)->mobile ?? '-';
                $planName = optional($renewal->plan)->name ?? '-';

                return (object) [
                    'id' => $renewal->id,
                    'partner_name' => $partnerName,
                    'customer_name' => $customerName,
                    'customer_email' => $customerEmail,
                    'customer_mobile' => $customerMobile,
                    'domain_list' => $renewal->domain_name ?? '-',
                    'plan_name' => $planName,
                    'subscription_start_date' => $renewal->subscription_start_date,
                    'subscription_end_date' => $renewal->subscription_end_date,
                    'renewal_date_display' => $renewalDate?->format('d M Y'),
                    'days_left' => $daysLeft,
                    'renew_url' => route(
                        'superadmin.b2b_partners.customers.renew',
                        [
                            'partner_id' => Crypt::encrypt($renewal->partner_id),
                            'customer_id' => Crypt::encrypt($renewal->user_id),
                        ]
                    ),
                ];
            })
            ->filter(function ($renewal) {
                return $renewal->days_left !== null
                    && $renewal->days_left >= 0
                    && $renewal->days_left <= 30;
            })
            ->sortBy('days_left')
            ->values();
    }

    public function index()
    {
        // Uncomment to clear cache temporarily - REMOVE AFTER TESTING
        // Cache::forget('superadmin_dashboard');

        $data = Cache::remember('superadmin_dashboard', 300, function () {
            $paidStatusValues = ['completed', 'success', 'paid', 'active'];

            /*
            |--------------------------------------------------------------------------
            | Basic Stats
            |--------------------------------------------------------------------------
            */
            $totalProviders = Provider::count();
            $activeProviders = Provider::whereRaw('LOWER(status) = ?', ['active'])->count();
            $totalPartners = Partner::count();
            $activePartners = Partner::whereRaw('LOWER(status) = ?', ['active'])->count();
            $b2bCustomers = Customer::count();
            // Auth DB has no `users` table in this setup — keep B2C at 0 (original behavior).
            $totalCustomersB2C = 0;
            $totalCustomersB2B = $b2bCustomers;
            $totalCustomers = $b2bCustomers + $totalCustomersB2C;
            $newRequests = DomainTransferRequest::where('status', 'pending')->count();
            $newOrders = Order::whereDate('created_at', today())->count();
            $totalOrders = Order::count();

            /*
            |--------------------------------------------------------------------------
            | Orders & Revenue
            |--------------------------------------------------------------------------
            */
            $revenue = (float) Order::whereIn(DB::raw('LOWER(status)'), $paidStatusValues)->sum('total_amount');
            $monthlyRevenue = (float) Order::whereIn(DB::raw('LOWER(status)'), $paidStatusValues)
                ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('total_amount');
            $monthlyOrderCount = Order::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count();

            $peakOrderDay = (int) (Order::query()
                ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->selectRaw('COUNT(*) as c')
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderByDesc('c')
                ->value('c') ?? 0);

            /*
            |--------------------------------------------------------------------------
            | Monthly Charts Data
            |--------------------------------------------------------------------------
            */
            $monthlyData = [];
            $providerChart = ['labels' => [], 'tizzy_sales' => [], 'microsoft_sales' => [], 'google_sales' => []];
            $salesGraphLabels = [];
            $salesGraphRevenue = [];
            $salesGraphOrders = [];

            $plans = Plan::whereIn('provider_id', [1, 2, 3])->get(['id', 'provider_id']);
            $planProviderMap = [];
            foreach ($plans as $plan) {
                $planProviderMap[$plan->id] = $plan->provider_id;
            }

            for ($i = 5; $i >= 0; $i--) {
                $start = now()->copy()->startOfMonth()->subMonths($i);
                $end = $start->copy()->endOfMonth();

                $monthOrders = Order::whereBetween('created_at', [$start, $end]);
                $revenueMonth = (float) (clone $monthOrders)->whereIn(DB::raw('LOWER(status)'), $paidStatusValues)->sum('total_amount');
                $ordersCount = (clone $monthOrders)->count();

                $monthlyOrders = Order::whereBetween('created_at', [$start, $end])
                    ->whereIn(DB::raw('LOWER(status)'), $paidStatusValues)
                    ->whereIn('plan_id', array_keys($planProviderMap))
                    ->get(['plan_id', 'total_amount']);

                $providerSales = [
                    1 => 0.0,
                    2 => 0.0,
                    3 => 0.0,
                ];

                foreach ($monthlyOrders as $order) {
                    $provider_id = $planProviderMap[$order->plan_id] ?? null;
                    if ($provider_id && isset($providerSales[$provider_id])) {
                        $providerSales[$provider_id] += (float) $order->total_amount;
                    }
                }

                $tizzySales = (float) ($providerSales[1] ?? 0);
                $microsoftSales = (float) ($providerSales[2] ?? 0);
                $googleSales = (float) ($providerSales[3] ?? 0);

                $monthlyData[] = [
                    'month' => $start->format('M'),
                    'orders' => $ordersCount,
                    'revenue' => $revenueMonth,
                    'tizzy_sales' => $tizzySales,
                    'microsoft_sales' => $microsoftSales,
                    'google_sales' => $googleSales,
                ];

                $providerChart['labels'][] = $start->format('M');
                $providerChart['tizzy_sales'][] = round($tizzySales, 2);
                $providerChart['microsoft_sales'][] = round($microsoftSales, 2);
                $providerChart['google_sales'][] = round($googleSales, 2);
                $salesGraphLabels[] = $start->format('M');
                $salesGraphRevenue[] = round($revenueMonth, 2);
                $salesGraphOrders[] = $ordersCount;
            }

            $salesGraph = [
                'labels' => $salesGraphLabels,
                'salesSeries' => $salesGraphRevenue,
                'orderCountSeries' => $salesGraphOrders,
            ];

            /*
            |--------------------------------------------------------------------------
            | Recent Orders
            |--------------------------------------------------------------------------
            */
            $recentOrders = Order::with(['plan.provider', 'customer'])
                ->select(
                    'orders.id',
                    'orders.order_no',
                    'orders.user_id',
                    'orders.partner_id',
                    'orders.plan_id',
                    'orders.domain_name',
                    'orders.total_amount',
                    'orders.status',
                    'orders.created_at',
                    'orders.renewal_at',
                    'orders.order_type',
                    'orders.subscription_end_date'
                )
                ->with([
                    'plan' => function ($query) {
                        $query->select('id', 'provider_id', 'name');
                    },
                    'plan.provider' => function ($query) {
                        $query->select('id', 'name');
                    },
                    'customer:id,company_name,email,mobile',
                ])
                ->orderBy('orders.created_at', 'desc')
                ->take(10)
                ->get()
                ->map(function ($order) {
                    $providerName = $order->plan && $order->plan->provider ? $order->plan->provider->name : 'Tizzy';

                    if ($order->order_type === 'b2b') {
                        $customerName = $order->customer->company_name ?? ($order->domain_name ?? 'Guest');
                        $customerEmail = $order->customer->email ?? '';
                        $customerMobile = $order->customer->mobile ?? '';
                    } else {
                        $customerName = $order->domain_name ?? 'Guest';
                        $customerEmail = '';
                        $customerMobile = '';
                    }

                    $order->provider_name = $providerName;
                    $order->customer_name = $customerName;
                    $order->customer_email = $customerEmail;
                    $order->customer_mobile = $customerMobile;
                    return $order;
                });

            $txRecent = $recentOrders;

            /*
            |--------------------------------------------------------------------------
            | Upcoming Renewals
            |--------------------------------------------------------------------------
            */
            $txUpcoming = Order::with(['plan.provider', 'customer'])
                ->select(
                    'orders.id',
                    'orders.order_no',
                    'orders.user_id',
                    'orders.partner_id',
                    'orders.plan_id',
                    'orders.domain_name',
                    'orders.total_amount',
                    'orders.status',
                    'orders.created_at',
                    'orders.renewal_at',
                    'orders.order_type',
                    'orders.subscription_end_date'
                )
                ->with([
                    'plan' => function ($query) {
                        $query->select('id', 'provider_id', 'name');
                    },
                    'plan.provider' => function ($query) {
                        $query->select('id', 'name');
                    },
                    'customer:id,company_name,email,mobile',
                ])
                ->whereIn('orders.status', ['completed', 'active'])
                ->where(function ($query) {
                    $query->whereNotNull('orders.renewal_at')
                          ->orWhereNotNull('orders.subscription_end_date');
                })
                ->whereDate('orders.renewal_at', '>=', now())
                ->orderBy('orders.renewal_at', 'asc')
                ->take(10)
                ->get()
                ->map(function ($order) {
                    $providerName = $order->plan && $order->plan->provider ? $order->plan->provider->name : 'Tizzy';
                    if ($order->order_type === 'b2b') {
                        $customerName = $order->customer->company_name ?? ($order->domain_name ?? 'Guest');
                        $customerEmail = $order->customer->email ?? '';
                        $customerMobile = $order->customer->mobile ?? '';
                    } else {
                        $customerName = $order->domain_name ?? 'Guest';
                        $customerEmail = '';
                        $customerMobile = '';
                    }
                    $order->provider_name = $providerName;
                    $order->customer_name = $customerName;
                    $order->customer_email = $customerEmail;
                    $order->customer_mobile = $customerMobile;
                    return $order;
                });

            /*
            |--------------------------------------------------------------------------
            | Dashboard Renewals Data (Same as Renewals Page)
            |--------------------------------------------------------------------------
            */
            $dashboardRenewalsData = $this->getDashboardRenewalsData();

            /*
            |--------------------------------------------------------------------------
            | Invoice Summary
            |--------------------------------------------------------------------------
            */
            $orders = Order::latest('created_at')->take(100)->get();

            $invoiceStats = [
                'unpaidCount' => $orders->whereNotIn('status', $paidStatusValues)->count(),
                'unpaidAmount' => (float) $orders->whereNotIn('status', $paidStatusValues)->sum('total_amount'),
                'pendingCount' => $orders->where('status', 'pending')->count(),
                'pendingAmount' => (float) $orders->where('status', 'pending')->sum('total_amount'),
                'paidCount' => $orders->whereIn('status', $paidStatusValues)->count(),
                'paidAmount' => (float) $orders->whereIn('status', $paidStatusValues)->sum('total_amount'),
            ];

            /*
            |--------------------------------------------------------------------------
            | Subscription Stats
            |--------------------------------------------------------------------------
            */
            $activeSubscriptions = Order::whereIn('status', ['active', 'completed'])->count();
            $renewalsThisMonth = Order::whereBetween('renewal_at', [now()->startOfMonth(), now()->endOfMonth()])->count();

            /*
            |--------------------------------------------------------------------------
            | Partners Pending Approval
            |--------------------------------------------------------------------------
            */
            $partnersPendingApproval = Partner::where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->take(6)
                ->get();

            /*
            |--------------------------------------------------------------------------
            | Renewal Stats
            |--------------------------------------------------------------------------
            */
            $totalPendingRenewals = $this->renewalService->pendingRenewalPartnerCounts()->sum('pending_count');
            $renewalsCompleted = $this->renewalService->completedRenewalsCount();
            $renewalRevenue = $this->renewalService->renewalRevenue();
            $conversionRate = $totalPendingRenewals > 0 ? round(($renewalsCompleted / $totalPendingRenewals) * 100, 2) : 0;

            /*
            |--------------------------------------------------------------------------
            | Chart Data
            |--------------------------------------------------------------------------
            */
            $monthlyTrend = [];
            for ($i = 11; $i >= 0; $i--) {
                $start = now()->copy()->startOfMonth()->subMonths($i);
                $end = $start->copy()->endOfMonth();
                $monthRevenue = (float) Order::whereIn(DB::raw('LOWER(status)'), $paidStatusValues)
                    ->whereBetween('created_at', [$start, $end])
                    ->sum('total_amount');
                $monthlyTrend[] = round($monthRevenue / 1000, 2);
            }

            if (array_sum($monthlyTrend) == 0) {
                $monthlyTrend = [65, 70, 75, 80, 78, 85, 82, 88, 90, 85, 92, 95];
            }

            $chartData = [
                'total_income' => $monthlyTrend,
                'total_expenses' => array_map(fn ($val) => $val * 0.4, $monthlyTrend),
                'investments' => array_map(fn ($val) => $val * 0.25, $monthlyTrend),
                'savings' => array_map(fn ($val) => $val * 0.15, $monthlyTrend),
            ];

            return [
                'title' => 'Dashboard',
                'pageTitle' => 'Dashboard',
                'totalProviders' => $totalProviders,
                'activeProviders' => $activeProviders,
                'totalPartners' => $totalPartners,
                'activePartners' => $activePartners,
                'totalCustomers' => $totalCustomers,
                'totalCustomersB2B' => $totalCustomersB2B,
                'totalCustomersB2C' => $totalCustomersB2C,
                'newRequests' => $newRequests,
                'newOrders' => $newOrders,
                'totalOrders' => $totalOrders,
                'peakOrderDay' => $peakOrderDay,
                'revenue' => $revenue,
                'monthlyRevenue' => $monthlyRevenue,
                'monthlyOrderCount' => $monthlyOrderCount,
                'monthlyData' => $monthlyData,
                'recentOrders' => $recentOrders,
                'partnersPendingApproval' => $partnersPendingApproval,
                'invoiceStats' => $invoiceStats,
                'salesGraph' => $salesGraph,
                'providerChart' => $providerChart,
                'chartData' => $chartData,
                'txRecent' => $txRecent,
                'txUpcoming' => $txUpcoming,
                'dashboardRenewalsData' => $dashboardRenewalsData,
                'activeSubscriptions' => $activeSubscriptions,
                'renewalsThisMonth' => $renewalsThisMonth,
                'totalPendingRenewals' => $totalPendingRenewals,
                'renewalsCompleted' => $renewalsCompleted,
                'renewalRevenue' => $renewalRevenue,
                'conversionRate' => $conversionRate,
            ];
        });

        return view('superadmin.index', $data);
    }

    public function clearDashboardCache()
    {
        Cache::forget('superadmin_dashboard');
        return back()->with('success', 'Dashboard cache cleared successfully.');
    }
}
