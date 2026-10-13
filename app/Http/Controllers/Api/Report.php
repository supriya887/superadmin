<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\PartnerAuthController;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceMst;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\Provider;
use App\Models\SupportTicket;
use App\Support\SimpleXlsx;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class Report extends Controller
{
    /** @var list<string> */
    private array $paidStatuses = ['completed', 'success', 'paid', 'active', 'delivered'];

    private int $reportProviderId = 0;

    private int $reportCustomerId = 0;

    public function reportGeneration(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Report generated successfully.',
            'data' => [],
        ]);
    }

    
    public function reportList(Request $request): JsonResponse
    {
        $partnerId = (int) $request->query('partner_id', $request->input('partner_id', 0));
        if ($partnerId <= 0) {
            return response()->json([
                'status' => false,
                'message' => 'Partner ID is required.',
            ], 422);
        }

        $partner = Partner::query()->find($partnerId);
        if (!$partner) {
            return response()->json([
                'status' => false,
                'message' => 'Partner not found.',
            ], 404);
        }

        $providerId = (int) $request->query('provider_id', $request->input('provider_id', 0));
        if ($providerId > 0 && !Provider::query()->whereKey($providerId)->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Provider not found.',
            ], 404);
        }
        $this->reportProviderId = $providerId;

        $customerId = (int) ($request->input('customer_id') ?? $request->query('customer_id') ?? $request->input('customerId') ?? $request->input('user_id') ?? 0);
        if ($customerId > 0 && !Customer::query()->whereKey($customerId)->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Customer not found.',
            ], 404);
        }
        $this->reportCustomerId = $customerId;

        $dateRange = $this->resolveDateRange($request);
        if ($dateRange['error'] !== null) {
            return response()->json([
                'status' => false,
                'message' => $dateRange['error'],
            ], 422);
        }

        /** @var Carbon|null $rangeStart */
        $rangeStart = $dateRange['start'];
        /** @var Carbon|null $rangeEnd */
        $rangeEnd = $dateRange['end'];
        $dateFilterApplied = $dateRange['applied'];

        $paidStatuses = $this->paidStatuses;
        $now = now();

        $ordersBase = Order::query()
            ->where('partner_id', $partnerId)
            ->whereRaw("LOWER(COALESCE(order_type, 'b2c')) = 'b2b'")
            ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) != 'draft'");
        $this->applyProviderFilter($ordersBase);
        $this->applyCustomerFilter($ordersBase);
        $this->applyDateRange($ordersBase, $rangeStart, $rangeEnd);

        $paidOrdersBase = (clone $ordersBase)->whereIn(DB::raw('LOWER(status)'), $paidStatuses);

        // Previous period for trend comparison (same length before selected range).
        [$compareStart, $compareEnd] = $this->resolveComparisonPeriod($rangeStart, $rangeEnd, $now);

        $totalRevenue = round((float) (clone $paidOrdersBase)->sum('total_amount'), 2);

        // Trend: compare selected range vs equal previous window; if no filter, this month vs last month.
        if ($dateFilterApplied && $rangeStart && $rangeEnd) {
            $revenueCurrent = $totalRevenue;
            $revenuePrevious = round((float) $this->scopedPaidOrdersQuery($partnerId, $paidStatuses, $compareStart, $compareEnd)
                ->sum('total_amount'), 2);
        } else {
            $monthStart = $now->copy()->startOfMonth();
            $lastMonthStart = $now->copy()->subMonth()->startOfMonth();
            $lastMonthEnd = $now->copy()->subMonth()->endOfMonth();
            $revenueCurrent = round((float) $this->scopedPaidOrdersQuery($partnerId, $paidStatuses, $monthStart, $now)
                ->sum('total_amount'), 2);
            $revenuePrevious = round((float) $this->scopedPaidOrdersQuery($partnerId, $paidStatuses, $lastMonthStart, $lastMonthEnd)
                ->sum('total_amount'), 2);
        }
        $revenueGrowth = $this->growthPercent($revenueCurrent, $revenuePrevious);

        if ($dateFilterApplied && $rangeStart && $rangeEnd) {
            $activeCustomersQuery = Order::query()
                ->where('partner_id', $partnerId)
                ->whereRaw("LOWER(COALESCE(order_type, 'b2c')) = 'b2b'")
                ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) != 'draft'")
                ->whereBetween('created_at', [$rangeStart, $rangeEnd]);
            $this->applyProviderFilter($activeCustomersQuery);
            $this->applyCustomerFilter($activeCustomersQuery);
            $activeCustomers = (int) $activeCustomersQuery->distinct('user_id')->count('user_id');
        } else {
            if ($this->reportProviderId > 0 || $this->reportCustomerId > 0) {
                $activeCustomersQuery = Order::query()
                    ->where('partner_id', $partnerId)
                    ->whereRaw("LOWER(COALESCE(order_type, 'b2c')) = 'b2b'")
                    ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) != 'draft'");
                $this->applyProviderFilter($activeCustomersQuery);
                $this->applyCustomerFilter($activeCustomersQuery);
                $activeCustomers = (int) $activeCustomersQuery->distinct('user_id')->count('user_id');
            } else {
                $activeCustomers = (int) Customer::query()
                    ->where('partner_id', $partnerId)
                    ->where('status', 1)
                    ->count();
            }
        }

        if ($dateFilterApplied && $rangeStart && $rangeEnd) {
            $periodDays = max(1, $rangeStart->diffInDays($rangeEnd) + 1);
            $prevEnd = $rangeStart->copy()->subDay()->endOfDay();
            $prevStart = $prevEnd->copy()->subDays($periodDays - 1)->startOfDay();
            $newCustomersCurrent = (int) Customer::query()->where('partner_id', $partnerId)
                ->when($this->reportCustomerId > 0, fn ($q) => $q->where('id', $this->reportCustomerId))
                ->whereBetween('created_at', [$rangeStart, $rangeEnd])->count();
            $newCustomersPrevious = (int) Customer::query()->where('partner_id', $partnerId)
                ->when($this->reportCustomerId > 0, fn ($q) => $q->where('id', $this->reportCustomerId))
                ->whereBetween('created_at', [$prevStart, $prevEnd])->count();
        } else {
            $monthStart = $now->copy()->startOfMonth();
            $lastMonthStart = $now->copy()->subMonth()->startOfMonth();
            $lastMonthEnd = $now->copy()->subMonth()->endOfMonth();
            $newCustomersCurrent = (int) Customer::query()->where('partner_id', $partnerId)
                ->when($this->reportCustomerId > 0, fn ($q) => $q->where('id', $this->reportCustomerId))
                ->where('created_at', '>=', $monthStart)->count();
            $newCustomersPrevious = (int) Customer::query()->where('partner_id', $partnerId)
                ->when($this->reportCustomerId > 0, fn ($q) => $q->where('id', $this->reportCustomerId))
                ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count();
        }
        $customerDelta = $newCustomersCurrent - $newCustomersPrevious;

        $subscriptionsBase = Order::query()
            ->where('partner_id', $partnerId)
            ->whereRaw("LOWER(COALESCE(order_type, 'b2c')) = 'b2b'")
            ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) != 'draft'");
        $this->applyProviderFilter($subscriptionsBase);
        $this->applyCustomerFilter($subscriptionsBase);
        $this->applyDateRange($subscriptionsBase, $rangeStart, $rangeEnd);

        $totalSubscriptions = (int) (clone $subscriptionsBase)
            ->whereNotIn(DB::raw('LOWER(status)'), ['cancelled', 'canceled', 'rejected', 'failed'])
            ->whereNotIn(DB::raw("LOWER(COALESCE(order_category, ''))"), ['renewed', 'upgraded'])
            ->count();
        $activeSubscriptions = (int) (clone $subscriptionsBase)
            ->whereIn(DB::raw('LOWER(status)'), $paidStatuses)
            ->whereNotIn(DB::raw("LOWER(COALESCE(order_category, ''))"), ['renewed', 'upgraded'])
            ->where(function ($q) use ($now, $rangeEnd) {
                $ref = $rangeEnd ?? $now;
                $q->whereNull('subscription_end_date')
                    ->orWhere('subscription_end_date', '>=', $ref->toDateString());
            })
            ->count();
        $activeSubscriptionRate = $totalSubscriptions > 0
            ? round(($activeSubscriptions / $totalSubscriptions) * 100, 0)
            : 0;

        $ordersCurrent = (int) (clone $ordersBase)->count();
        if ($dateFilterApplied && $rangeStart && $rangeEnd) {
            $periodDays = max(1, $rangeStart->diffInDays($rangeEnd) + 1);
            $prevEnd = $rangeStart->copy()->subDay()->endOfDay();
            $prevStart = $prevEnd->copy()->subDays($periodDays - 1)->startOfDay();
            $ordersPreviousQuery = Order::query()
                ->where('partner_id', $partnerId)
                ->whereRaw("LOWER(COALESCE(order_type, 'b2c')) = 'b2b'")
                ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) != 'draft'")
                ->whereBetween('created_at', [$prevStart, $prevEnd]);
            $this->applyProviderFilter($ordersPreviousQuery);
            $this->applyCustomerFilter($ordersPreviousQuery);
            $ordersPrevious = (int) $ordersPreviousQuery->count();
        } else {
            $monthStart = $now->copy()->startOfMonth();
            $lastMonthStart = $now->copy()->subMonth()->startOfMonth();
            $lastMonthEnd = $now->copy()->subMonth()->endOfMonth();
            $ordersCurrentQuery = Order::query()
                ->where('partner_id', $partnerId)
                ->whereRaw("LOWER(COALESCE(order_type, 'b2c')) = 'b2b'")
                ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) != 'draft'")
                ->where('created_at', '>=', $monthStart);
            $this->applyProviderFilter($ordersCurrentQuery);
            $this->applyCustomerFilter($ordersCurrentQuery);
            $ordersCurrent = (int) $ordersCurrentQuery->count();
            $ordersPreviousQuery = Order::query()
                ->where('partner_id', $partnerId)
                ->whereRaw("LOWER(COALESCE(order_type, 'b2c')) = 'b2b'")
                ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) != 'draft'")
                ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd]);
            $this->applyProviderFilter($ordersPreviousQuery);
            $this->applyCustomerFilter($ordersPreviousQuery);
            $ordersPrevious = (int) $ordersPreviousQuery->count();
        }
        $monthlyOrdersGrowth = $this->growthPercent((float) $ordersCurrent, (float) $ordersPrevious);

        $renewalsCompleted = (int) (clone $paidOrdersBase)
            ->whereIn(DB::raw("LOWER(COALESCE(order_category, ''))"), ['renewal', 'renew', 'renewed'])
            ->count();
        $renewalsAttempted = (int) (clone $ordersBase)
            ->whereIn(DB::raw("LOWER(COALESCE(order_category, ''))"), ['renewal', 'renew', 'renewed'])
            ->count();
        $renewalSuccessRate = $renewalsAttempted > 0
            ? round(($renewalsCompleted / $renewalsAttempted) * 100, 0)
            : 0;

        [$pendingInvoiceAmount, $pendingInvoiceCount] = $this->resolvePendingInvoices($partnerId, $rangeStart, $rangeEnd);

        $summaryCards = [
            'total_revenue' => [
                'key' => 'total_revenue',
                'label' => 'Total Revenue',
                'value' => $totalRevenue,
                'display' => '₹' . number_format($totalRevenue, 2),
                'trend' => ($revenueGrowth >= 0 ? '+' : '') . $revenueGrowth . '%',
                'trend_type' => $revenueGrowth >= 0 ? 'up' : 'down',
            ],
            'active_customers' => [
                'key' => 'active_customers',
                'label' => 'Active Customers',
                'value' => $activeCustomers,
                'display' => (string) $activeCustomers,
                'trend' => ($customerDelta >= 0 ? '+' : '') . $customerDelta,
                'trend_type' => $customerDelta >= 0 ? 'up' : 'down',
            ],
            'active_subscriptions' => [
                'key' => 'active_subscriptions',
                'label' => 'Active Subscriptions',
                'value' => $activeSubscriptions,
                'display' => (string) $activeSubscriptions,
                'trend' => $activeSubscriptionRate . '% Active',
                'trend_type' => 'neutral',
            ],
            'monthly_orders' => [
                'key' => 'monthly_orders',
                'label' => 'Monthly Orders',
                'value' => $ordersCurrent,
                'display' => (string) $ordersCurrent,
                'trend' => ($monthlyOrdersGrowth >= 0 ? '+' : '') . $monthlyOrdersGrowth . '%',
                'trend_type' => $monthlyOrdersGrowth >= 0 ? 'up' : 'down',
            ],
            'renewals_completed' => [
                'key' => 'renewals_completed',
                'label' => 'Renewals Completed',
                'value' => $renewalsCompleted,
                'display' => (string) $renewalsCompleted,
                'trend' => $renewalSuccessRate . '% Success Rate',
                'trend_type' => 'neutral',
            ],
            'pending_invoices' => [
                'key' => 'pending_invoices',
                'label' => 'Pending Invoices',
                'value' => $pendingInvoiceAmount,
                'display' => '₹' . $this->formatIndianNumber($pendingInvoiceAmount),
                'trend' => $pendingInvoiceCount . ' Pending',
                'trend_type' => 'neutral',
            ],
        ];
   

        $chartRange = $this->resolveChartRange($partnerId, $rangeStart, $rangeEnd, $dateFilterApplied);
        $buckets = $this->buildMonthlyBuckets(
            $chartRange['start'],
            $chartRange['end'],
            $partnerId,
            $paidStatuses
        );

        $revenueLabels = array_column($buckets, 'label');
        $revenueSeries = array_column($buckets, 'revenue');

        $revenueOverview = [
            'title' => 'Revenue Overview',
            'subtitle' => 'Monthly revenue performance across the selected period.',
            'start_date' => $this->formatReportDate($rangeStart),
            'end_date' => $this->formatReportDate($rangeEnd),
            'datasets' => [
                'revenue' => $revenueSeries,
            ],
            'x_axis' => [
                'label' => 'Month',
                'values' => $revenueLabels,
            ],
            'y_axis' => [
                'label' => 'Revenue (₹)',
                'datasets' => [
                    [
                        'label' => 'Revenue',
                        'key' => 'revenue',
                        'data' => $revenueSeries,
                    ],
                ],
            ],
        ];

        $serviceSales = $this->resolveSalesByService($partnerId, $paidStatuses, $rangeStart, $rangeEnd);
        $serviceTotal = max(0.01, array_sum($serviceSales));
        $salesByService = [
            'title' => 'Sales by Service',
            'subtitle' => 'Order distribution across services.',
            'start_date' => $this->formatReportDate($rangeStart),
            'end_date' => $this->formatReportDate($rangeEnd),
            'datasets' => [
                'google_workspace' => round($serviceSales['google_workspace'], 2),
                'microsoft_365' => round($serviceSales['microsoft_365'], 2),
                'tizzy_mail' => round($serviceSales['tizzy_mail'], 2),
            ],
            'percentages' => [
                'google_workspace' => round(($serviceSales['google_workspace'] / $serviceTotal) * 100, 0),
                'microsoft_365' => round(($serviceSales['microsoft_365'] / $serviceTotal) * 100, 0),
                'tizzy_mail' => round(($serviceSales['tizzy_mail'] / $serviceTotal) * 100, 0),
            ],
            'labels' => [
                'google_workspace' => 'Google Workspace',
                'microsoft_365' => 'Microsoft 365',
                'tizzy_mail' => 'Tizzy Mail',
            ],
        ];

        $growthLabels = $revenueLabels;
        $growthRevenue = array_map(static fn ($v) => round(((float) $v) / 1000, 2), $revenueSeries);
        $growthOrders = array_column($buckets, 'orders');
        $growthRenewals = array_column($buckets, 'renewals');

        $monthlyBusinessGrowth = [
            'title' => 'Monthly Business Growth',
            'subtitle' => 'Compare revenue, orders, and renewals each month.',
            'start_date' => $this->formatReportDate($rangeStart),
            'end_date' => $this->formatReportDate($rangeEnd),
            'datasets' => [
                'revenue' => $growthRevenue,
                'orders' => $growthOrders,
                'renewals' => $growthRenewals,
            ],
            'x_axis' => [
                'label' => 'Month',
                'values' => $growthLabels,
            ],
            'y_axis' => [
                'label' => 'Count / Revenue (₹k)',
                'datasets' => [
                    [
                        'label' => 'Revenue (₹k)',
                        'key' => 'revenue',
                        'data' => $growthRevenue,
                    ],
                    [
                        'label' => 'Orders',
                        'key' => 'orders',
                        'data' => $growthOrders,
                    ],
                    [
                        'label' => 'Renewals',
                        'key' => 'renewals',
                        'data' => $growthRenewals,
                    ],
                ],
            ],
        ];

        $quickInsights = $this->buildQuickInsights(
            $partnerId,
            $revenueLabels,
            $revenueSeries,
            $serviceSales,
            $paidStatuses,
            $rangeStart,
            $rangeEnd
        );

        $recentReports = $this->resolveRecentReports($partnerId, $rangeStart, $rangeEnd);

        return response()->json([
            'status' => true,
            'partner_id' => $partnerId,
            'provider_id' => $this->reportProviderId > 0 ? $this->reportProviderId : null,
            'customer_id' => $this->reportCustomerId > 0 ? $this->reportCustomerId : null,
            'start_date' => $this->formatReportDate($rangeStart),
            'end_date' => $this->formatReportDate($rangeEnd),
            'date_filter_applied' => $dateFilterApplied,
            'catalog' => $this->getReportCatalogObject(),
            'catalog_groups' => $this->getReportCatalogGroups(),
            'summary_cards' => $summaryCards,
            'revenue_overview' => $revenueOverview,
            'sales_by_service' => $salesByService,
            'monthly_business_growth' => $monthlyBusinessGrowth,
            'quick_insights' => $quickInsights,
            'recent_reports' => $recentReports,
            'datasets' => $monthlyBusinessGrowth['datasets'],
            'x_axis' => $monthlyBusinessGrowth['x_axis'],
            'y_axis' => $monthlyBusinessGrowth['y_axis'],
        ]);
    }

    /**
     * POST /api/v1/partner/download-report-list
     * Body: partner_id, type=pdf|excel, fromDate, toDate (dd-mm-yyyy, optional), provider_id / customer_id (optional).
     * Builds the Business Reports Overview (+ Daily Performance) from reportList() and saves it on the public disk.
     */
    public function downloadReportList(Request $request): JsonResponse
    {
        $type = strtolower(trim((string) $request->input('type', '')));
        if (!in_array($type, ['pdf', 'excel'], true)) {
            return response()->json(['status' => false, 'message' => 'type must be pdf or excel.'], 422);
        }

        $overview = $this->reportList($request);
        $data = $overview->getData(true);
        if (empty($data['status'])) {
            return $overview;
        }

        $partnerId = (int) $data['partner_id'];
        $range = $this->resolveDateRange($request);
        $end = $range['end'] ?? now()->endOfDay();
        $start = $range['start'] ?? $end->copy()->startOfYear();
        if ($start->diffInDays($end) > 366) {
            $start = $end->copy()->subDays(366)->startOfDay();
        }
        $daily = $this->dailyPerformance($partnerId, $start, $end);
        $dailyTitle = 'DAILY PERFORMANCE (' . ($start->year === $end->year ? $start->year : $start->format('d M Y') . ' - ' . $end->format('d M Y')) . ')';
        $period = $data['date_filter_applied'] ? $data['start_date'] . ' to ' . $data['end_date'] : 'All time';
        $partnerName = (string) (Partner::query()->whereKey($partnerId)->value('company_name') ?: '');
        $inr = fn ($v) => 'Rs.' . $this->formatIndianNumber((float) $v);

        $path = 'reports/partner_' . $partnerId . '/business-report-' . now()->format('Ymd-His') . ($type === 'pdf' ? '.pdf' : '.xlsx');

        try {
            $content = $type === 'pdf'
                ? app('dompdf.wrapper')
                    ->loadView('reports.business_overview_pdf', compact('data', 'daily', 'dailyTitle', 'period', 'partnerName', 'inr'))
                    ->setPaper('a4', 'portrait')
                    ->output()
                : SimpleXlsx::build($this->reportExcelSheets($data, $daily, $dailyTitle, $period, $partnerName));
            Storage::disk('public')->put($path, $content);
        } catch (\Throwable $e) {
            Log::error('Report download failed: ' . $e->getMessage(), ['partner_id' => $partnerId, 'type' => $type]);

            return response()->json(['status' => false, 'message' => 'Could not generate the report file.', 'error' => $e->getMessage()], 500);
        }

        return response()->json([
            'status' => true,
            'message' => 'Report generated successfully.',
            'data' => [
                'type' => $type,
                'file_name' => basename($path),
                'file_path' => $path,
                'file_url' => rtrim((string) config('app.url'), '/') . '/storage/' . $path,
                'file_size' => strlen($content),
                'period' => $period,
            ],
        ]);
    }

    /** Sales (paid), orders and renewals per day for the report's partner/provider/customer filters. */
    private function dailyPerformance(int $partnerId, Carbon $start, Carbon $end): array
    {
        $paid = "LOWER(status) IN ('" . implode("','", $this->paidStatuses) . "')";
        $query = Order::query()
            ->selectRaw("DATE(created_at) AS day,
                COALESCE(SUM(total_amount) FILTER (WHERE {$paid}), 0) AS sales,
                COUNT(*) AS orders,
                COUNT(*) FILTER (WHERE {$paid} AND LOWER(COALESCE(order_category, '')) IN ('renewal', 'renew', 'renewed')) AS renewals")
            ->where('partner_id', $partnerId)
            ->whereRaw("LOWER(COALESCE(order_type, 'b2c')) = 'b2b'")
            ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) != 'draft'")
            ->whereBetween('created_at', [$start, $end]);
        $this->applyProviderFilter($query);
        $this->applyCustomerFilter($query);
        $rows = $query->groupByRaw('DATE(created_at)')->toBase()->get()
            ->keyBy(fn ($r) => Carbon::parse($r->day)->toDateString());

        $days = [];
        for ($d = $start->copy()->startOfDay(); $d->lte($end); $d->addDay()) {
            $r = $rows[$d->toDateString()] ?? null;
            $days[] = [
                'label' => $d->format('d M'),
                'date' => $d->format('d-m-Y'),
                'sales' => round((float) ($r->sales ?? 0), 2),
                'orders' => (int) ($r->orders ?? 0),
                'renewals' => (int) ($r->renewals ?? 0),
            ];
        }

        return $days;
    }

    /** Overview sheet (same sections as the PDF) + Daily Performance sheet (dates across, like the app). */
    private function reportExcelSheets(array $data, array $daily, string $dailyTitle, string $period, string $partnerName): array
    {
        $num = fn ($v) => is_numeric($v) ? $v + 0 : (string) $v;
        $months = $data['revenue_overview']['x_axis']['values'] ?? [];
        $revenue = $data['revenue_overview']['datasets']['revenue'] ?? [];
        $growth = $data['monthly_business_growth']['datasets'] ?? [];
        $sales = $data['sales_by_service'] ?? [];

        $rows = [
            [['Business Reports Overview', 1]],
            [[trim($partnerName . '  |  Period: ' . $period, ' |'), 2]],
            [],
            [['Summary Metrics', 6]],
            [['Metric', 3], ['Value', 3], ['Trend', 3]],
        ];
        foreach ($data['summary_cards'] ?? [] as $card) {
            $isMoney = str_contains((string) ($card['display'] ?? ''), '₹');
            $rows[] = [[$card['label'], 4], [$num($card['value']), $isMoney ? 7 : 5], [(string) $card['trend'], 5]];
        }

        $rows[] = [];
        $rows[] = [['Quick Insights', 6]];
        $rows[] = [['Insight', 3], ['Value', 3]];
        foreach ($data['quick_insights'] ?? [] as $insight) {
            $rows[] = [[$insight['label'], 4], [$num($insight['value']), 5]];
        }

        $rows[] = [];
        $rows[] = [['Sales by Service', 6]];
        $rows[] = [['Service', 3], ['Amount (₹)', 3], ['Share', 3]];
        foreach ($sales['labels'] ?? [] as $key => $label) {
            $rows[] = [[$label, 4], [(float) ($sales['datasets'][$key] ?? 0), 7], [($sales['percentages'][$key] ?? 0) . '%', 5]];
        }

        $rows[] = [];
        $rows[] = [['Monthly Business Growth', 6]];
        $rows[] = [['Month', 3], ['Revenue (₹)', 3], ['Orders', 3], ['Renewals', 3]];
        foreach ($months as $i => $month) {
            $rows[] = [[$month, 4], [(float) ($revenue[$i] ?? 0), 7], [(int) ($growth['orders'][$i] ?? 0), 5], [(int) ($growth['renewals'][$i] ?? 0), 5]];
        }

        $header = [['', 3]];
        $salesRow = [['Sales (₹)', 4]];
        $ordersRow = [['Orders', 4]];
        $renewalsRow = [['Renewals', 4]];
        foreach ($daily as $day) {
            $header[] = [$day['label'], 3];
            $salesRow[] = [$day['sales'], 7];
            $ordersRow[] = [$day['orders'], 5];
            $renewalsRow[] = [$day['renewals'], 5];
        }

        return [
            'Overview' => ['cols' => [34, 22, 22, 14], 'rows' => $rows],
            'Daily Performance' => [
                'cols' => array_merge([16], array_fill(0, count($daily), 12)),
                'rows' => [
                    [[$dailyTitle, 1]],
                    [['Monitor daily sales, orders, renewals, and business activity.', 2]],
                    [],
                    $header,
                    $salesRow,
                    $ordersRow,
                    $renewalsRow,
                ],
            ],
        ];
    }

    public function reportBySlug(Request $request): JsonResponse
    {
        $partnerId = (int) $request->query('partner_id', $request->input('partner_id', 0));
        if ($partnerId <= 0) {
            return response()->json(['status' => false, 'message' => 'Partner ID is required.'], 422);
        }

        $slug = strtolower(trim((string) $request->input('slug', '')));
        if ($slug === 'monthly-transactions') {
            $slug = 'transactions';
        }
        if ($slug === '') {
            return response()->json(['status' => false, 'message' => 'Report slug is required.'], 422);
        }

        if (!Partner::query()->whereKey($partnerId)->exists()) {
            return response()->json(['status' => false, 'message' => 'Partner not found.'], 404);
        }

        $providerId = (int) $request->query('provider_id', $request->input('provider_id', 0));
        if ($providerId > 0 && !Provider::query()->whereKey($providerId)->exists()) {
            return response()->json(['status' => false, 'message' => 'Provider not found.'], 404);
        }
        $this->reportProviderId = $providerId;

        $customerId = (int) ($request->input('customer_id') ?? $request->query('customer_id') ?? $request->input('customerId') ?? $request->input('user_id') ?? 0);
        if ($customerId > 0 && !Customer::query()->whereKey($customerId)->exists()) {
            return response()->json(['status' => false, 'message' => 'Customer not found.'], 404);
        }
        $this->reportCustomerId = $customerId;

        $catalog = $this->getReportCatalogDefinitions();
        if (!isset($catalog[$slug])) {
            return response()->json(['status' => false, 'message' => 'Report not found.'], 404);
        }

        $dateRange = $this->resolveDateRange($request);
        if ($dateRange['error'] !== null) {
            return response()->json(['status' => false, 'message' => $dateRange['error']], 422);
        }

        $yearRaw = $request->input('year');
        $yearProvided = $yearRaw !== null && $yearRaw !== '' && is_numeric($yearRaw);
        $yearFilterApplied = $yearProvided && !$dateRange['applied'];
        $year = $yearProvided ? (int) $yearRaw : (int) now()->format('Y');

        [$rangeStart, $rangeEnd] = $this->resolveSlugDateRange(
            $slug,
            $dateRange['start'],
            $dateRange['end'],
            $dateRange['applied'],
            $year,
            $yearFilterApplied
        );

        $reportMeta = array_merge(['slug' => $slug], $catalog[$slug]);

        $cardType = strtolower(trim((string) (
            $request->input('type')
            ?? $request->input('Type')
            ?? ''
        )));
        if ($slug === 'daily-performance' && $cardType !== '' && !in_array($cardType, ['sales', 'orders', 'renewals'], true)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid type. Use sales, orders, or renewals.',
            ], 422);
        }
        if ($slug !== 'daily-performance') {
            $cardType = '';
        }

        $payload = $this->buildSlugReportPayload($partnerId, $slug, $rangeStart, $rangeEnd, $year, $cardType);
        $records = $slug === 'new-customers'
            ? $this->buildNewCustomerRecords($partnerId, $rangeStart, $rangeEnd)
            : $this->buildCatalogOrderCards($partnerId, $slug, $rangeStart, $rangeEnd, $cardType);

        return response()->json([
            'status' => true,
            'partner_id' => $partnerId,
            'provider_id' => $this->reportProviderId > 0 ? $this->reportProviderId : null,
            'customer_id' => $this->reportCustomerId > 0 ? $this->reportCustomerId : null,
            'slug' => $slug,
            'type' => $cardType !== '' ? $cardType : null,
            'year' => $yearFilterApplied || $dateRange['applied'] ? $year : '',
            'start_date' => $this->formatReportDate($rangeStart),
            'end_date' => $this->formatReportDate($rangeEnd),
            'date_filter_applied' => $dateRange['applied'],
            'year_filter_applied' => $yearFilterApplied,
            'report' => $reportMeta,
            'catalog' => $this->getReportCatalogObject(),
            'description' => $payload['description'] ?? $reportMeta['subtitle'],
            'graph' => $payload['graph'],
            'table' => $payload['table'],
            'records' => $records,
            'data' => $records,
            'count' => count($records),
            'datasets' => $payload['graph']['datasets'] ?? [],
            'x_axis' => $payload['graph']['x_axis'] ?? ['label' => '', 'values' => []],
            'y_axis' => $payload['graph']['y_axis'] ?? ['label' => '', 'datasets' => []],
        ]);
    }

    /**
     * @return array{start: ?Carbon, end: ?Carbon, applied: bool, error: ?string}
     */
    private function resolveDateRange(Request $request): array
    {
        $startRaw = trim((string) ($request->input('fromDate') ?? ''));
        $endRaw = trim((string) ($request->input('toDate') ?? ''));

        if ($startRaw === '' && $endRaw === '') {
            return [
                'start' => null,
                'end' => null,
                'applied' => false,
                'error' => null,
            ];
        }

        $start = $startRaw !== '' ? $this->parseReportDate($startRaw)?->startOfDay() : null;
        $end = $endRaw !== '' ? $this->parseReportDate($endRaw)?->endOfDay() : null;

        if ($startRaw !== '' && $start === null) {
            return ['start' => null, 'end' => null, 'applied' => false, 'error' => 'Invalid start_date. Use dd-mm-yyyy format.'];
        }
        if ($endRaw !== '' && $end === null) {
            return ['start' => null, 'end' => null, 'applied' => false, 'error' => 'Invalid end_date. Use dd-mm-yyyy format.'];
        }

        if ($start === null && $end !== null) {
            $start = $end->copy()->startOfDay();
        }
        if ($end === null && $start !== null) {
            $end = now()->endOfDay();
        }

        if ($start && $end && $start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return [
            'start' => $start,
            'end' => $end,
            'applied' => true,
            'error' => null,
        ];
    }

    private function parseReportDate(string $raw): ?Carbon
    {
        foreach (['d-m-Y', 'd/m/Y', 'Y-m-d', 'd-M-y', 'd-M-Y'] as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $raw);
                if ($parsed !== false) {
                    return $parsed;
                }
            } catch (\Throwable $e) {
                // try next format
            }
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function formatReportDate(?Carbon $date): string
    {
        return $date ? $date->format('d-m-Y') : '';
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function resolveComparisonPeriod(?Carbon $rangeStart, ?Carbon $rangeEnd, Carbon $now): array
    {
        if (!$rangeStart || !$rangeEnd) {
            return [null, null];
        }

        $days = max(1, $rangeStart->diffInDays($rangeEnd) + 1);
        $compareEnd = $rangeStart->copy()->subDay()->endOfDay();
        $compareStart = $compareEnd->copy()->subDays($days - 1)->startOfDay();

        return [$compareStart, $compareEnd];
    }

    private function applyDateRange(Builder $query, ?Carbon $start, ?Carbon $end, string $column = 'created_at'): void
    {
        if ($start && $end) {
            $query->whereBetween($column, [$start, $end]);

            return;
        }
        if ($start) {
            $query->where($column, '>=', $start);

            return;
        }
        if ($end) {
            $query->where($column, '<=', $end);
        }
    }

    private function applyProviderFilter(Builder $query, string $planColumn = 'plan_id', string $providerColumn = 'provider_id'): void
    {
        if ($this->reportProviderId <= 0) {
            return;
        }

        if (Schema::connection('pgsql_billing')->hasColumn('orders', 'provider_id')) {
            $query->where($providerColumn, $this->reportProviderId);

            return;
        }

        $planIds = Plan::query()
            ->where('provider_id', $this->reportProviderId)
            ->pluck('id')
            ->all();
        if ($planIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn($planColumn, $planIds);
    }

    private function applyCustomerFilter(Builder $query, string $customerColumn = 'user_id'): void
    {
        if ($this->reportCustomerId <= 0) {
            return;
        }

        $query->where($customerColumn, $this->reportCustomerId);
    }

    /**
     * @param  list<string>  $paidStatuses
     */
    private function scopedPaidOrdersQuery(int $partnerId, array $paidStatuses, ?Carbon $start, ?Carbon $end): Builder
    {
        $query = Order::query()
            ->where('partner_id', $partnerId)
            ->whereRaw("LOWER(COALESCE(order_type, 'b2c')) = 'b2b'")
            ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) != 'draft'")
            ->whereIn(DB::raw('LOWER(status)'), $paidStatuses);
        $this->applyProviderFilter($query);
        $this->applyCustomerFilter($query);
        $this->applyDateRange($query, $start, $end);

        return $query;
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    private function resolveChartRange(int $partnerId, ?Carbon $rangeStart, ?Carbon $rangeEnd, bool $dateFilterApplied): array
    {
        if ($dateFilterApplied && $rangeStart && $rangeEnd) {
            return [
                'start' => $rangeStart->copy()->startOfMonth(),
                'end' => $rangeEnd->copy()->endOfMonth(),
            ];
        }

        $firstOrderQuery = Order::query()
            ->where('partner_id', $partnerId)
            ->whereRaw("LOWER(COALESCE(order_type, 'b2c')) = 'b2b'")
            ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) != 'draft'");
        $this->applyProviderFilter($firstOrderQuery);
        $this->applyCustomerFilter($firstOrderQuery);
        $firstOrderAt = $firstOrderQuery->min('created_at');

        $start = $firstOrderAt
            ? Carbon::parse((string) $firstOrderAt)->startOfMonth()
            : now()->copy()->subMonths(11)->startOfMonth();

        return [
            'start' => $start,
            'end' => now()->endOfMonth(),
        ];
    }

    /**
     * @param  list<string>  $paidStatuses
     * @return list<array{label: string, revenue: float, orders: int, renewals: int}>
     */
    private function buildMonthlyBuckets(Carbon $start, Carbon $end, int $partnerId, array $paidStatuses): array
    {
        $buckets = [];
        $cursor = $start->copy()->startOfMonth();
        $limit = $end->copy()->endOfMonth();

        while ($cursor->lte($limit)) {
            $bucketStart = $cursor->copy()->startOfMonth();
            $bucketEnd = $cursor->copy()->endOfMonth();
            if ($bucketEnd->gt($limit)) {
                $bucketEnd = $limit->copy();
            }

            $paidQuery = Order::query()
                ->where('partner_id', $partnerId)
                ->whereRaw("LOWER(COALESCE(order_type, 'b2c')) = 'b2b'")
                ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) != 'draft'")
                ->whereIn(DB::raw('LOWER(status)'), $paidStatuses)
                ->whereBetween('created_at', [$bucketStart, $bucketEnd]);
            $this->applyProviderFilter($paidQuery);
            $this->applyCustomerFilter($paidQuery);

            $ordersQuery = Order::query()
                ->where('partner_id', $partnerId)
                ->whereRaw("LOWER(COALESCE(order_type, 'b2c')) = 'b2b'")
                ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) != 'draft'")
                ->whereBetween('created_at', [$bucketStart, $bucketEnd]);
            $this->applyProviderFilter($ordersQuery);
            $this->applyCustomerFilter($ordersQuery);

            $buckets[] = [
                'label' => $cursor->format('M'),
                'revenue' => round((float) (clone $paidQuery)->sum('total_amount'), 2),
                'orders' => (int) (clone $ordersQuery)->count(),
                'renewals' => (int) (clone $paidQuery)
                    ->whereIn(DB::raw("LOWER(COALESCE(order_category, ''))"), ['renewal', 'renew', 'renewed'])
                    ->count(),
            ];

            $cursor->addMonth();
        }

        return $buckets;
    }

    /**
     * @return array{0: float, 1: int}
     */
    private function resolvePendingInvoices(int $partnerId, ?Carbon $start, ?Carbon $end): array
    {
        $partnerOrderQuery = Order::query()
            ->where('partner_id', $partnerId)
            ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) != 'draft'");
        $this->applyProviderFilter($partnerOrderQuery);
        $this->applyCustomerFilter($partnerOrderQuery);
        $partnerOrderIds = $partnerOrderQuery->pluck('id');

        $invoices = Invoice::query()
            ->where(function ($q) use ($partnerId, $partnerOrderIds) {
                if ($this->reportCustomerId > 0) {
                    $q->where('customer_id', $this->reportCustomerId);
                    if ($partnerOrderIds->isNotEmpty()) {
                        $q->orWhereIn('order_id', $partnerOrderIds);
                    }
                } else {
                    $q->where('partner_id', $partnerId);
                    if ($partnerOrderIds->isNotEmpty()) {
                        $q->orWhereIn('order_id', $partnerOrderIds);
                    }
                }
            })
            ->where(function ($q) {
                $q->whereNull('action_type')
                    ->orWhereRaw("LOWER(TRIM(COALESCE(action_type, ''))) <> 'credit_note'");
            })
            ->whereRaw("UPPER(TRIM(COALESCE(bill_no_full, ''))) NOT LIKE 'CN/%'");

        if ($start && $end) {
            $invoices->where(function ($q) use ($start, $end) {
                $q->whereBetween('bill_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhereBetween('created_at', [$start, $end]);
            });
        }

        $rows = $invoices->get(['net_amt', 'status']);

        $pendingAmount = 0.0;
        $pendingCount = 0;
        foreach ($rows as $inv) {
            $status = strtolower(trim((string) ($inv->status ?? '')));
            if (in_array($status, ['paid', 'completed', 'success', 'cancelled', 'canceled'], true)) {
                continue;
            }
            $pendingAmount += (float) ($inv->net_amt ?? 0);
            $pendingCount++;
        }

        return [round($pendingAmount, 2), $pendingCount];
    }

    /**
     * @param  list<string>  $paidStatuses
     * @return array{google_workspace: float, microsoft_365: float, tizzy_mail: float}
     */
    private function resolveSalesByService(int $partnerId, array $paidStatuses, ?Carbon $start, ?Carbon $end): array
    {
        $totals = [
            'google_workspace' => 0.0,
            'microsoft_365' => 0.0,
            'tizzy_mail' => 0.0,
        ];

        $ordersQuery = Order::query()
            ->where('partner_id', $partnerId)
            ->whereRaw("LOWER(COALESCE(order_type, 'b2c')) = 'b2b'")
            ->whereIn(DB::raw('LOWER(status)'), $paidStatuses);
        $this->applyProviderFilter($ordersQuery);
        $this->applyCustomerFilter($ordersQuery);
        $this->applyDateRange($ordersQuery, $start, $end);

        $orders = $ordersQuery->get(['id', 'plan_id', 'total_amount', 'subtotal', 'credit_used']);
        if ($orders->isEmpty()) {
            return $totals;
        }

        $items = OrderItem::query()
            ->whereIn('order_id', $orders->pluck('id')->all())
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereRaw("LOWER(TRIM(COALESCE(status, ''))) NOT IN ('delete', 'deleted')");
            })
            ->get(['order_id', 'plan_id', 'total_amount', 'unit_price', 'quantity']);

        $planIds = $items->pluck('plan_id')->filter()->unique()->values();
        if ($planIds->isEmpty()) {
            $planIds = $orders->pluck('plan_id')->filter()->unique()->values();
        }

        $plansById = $planIds->isEmpty()
            ? collect()
            : Plan::query()->whereIn('id', $planIds)->get(['id', 'provider_id'])->keyBy('id');

        $providerNames = Provider::query()->pluck('name', 'id');

        if ($items->isNotEmpty()) {
            foreach ($items as $item) {
                $amount = round((float) ($item->total_amount ?? 0), 2);
                if ($amount <= 0) {
                    $amount = round((float) ($item->unit_price ?? 0) * max(1, (int) ($item->quantity ?? 1)), 2);
                }
                $providerId = (int) ($plansById->get($item->plan_id)->provider_id ?? 0);
                $this->addServiceAmount($totals, $providerId, $amount, $providerNames);
            }
        } else {
            foreach ($orders as $order) {
                $amount = round((float) ($order->total_amount ?? 0), 2);
                if ($amount <= 0) {
                    $amount = round((float) ($order->credit_used ?? $order->subtotal ?? 0), 2);
                }
                $providerId = (int) ($plansById->get($order->plan_id)->provider_id ?? 0);
                $this->addServiceAmount($totals, $providerId, $amount, $providerNames);
            }
        }

        return $totals;
    }

    /**
     * @param  array{google_workspace: float, microsoft_365: float, tizzy_mail: float}  $totals
     */
    private function addServiceAmount(array &$totals, int $providerId, float $amount, $providerNames): void
    {
        if ($providerId === 1) {
            $totals['tizzy_mail'] += $amount;

            return;
        }
        if ($providerId === 2) {
            $totals['microsoft_365'] += $amount;

            return;
        }
        if ($providerId === 3) {
            $totals['google_workspace'] += $amount;

            return;
        }

        $name = strtolower((string) ($providerNames->get($providerId) ?? ''));
        if (str_contains($name, 'google')) {
            $totals['google_workspace'] += $amount;
        } elseif (str_contains($name, 'microsoft')) {
            $totals['microsoft_365'] += $amount;
        } elseif (str_contains($name, 'tizzy')) {
            $totals['tizzy_mail'] += $amount;
        }
    }

    /**
     * @param  list<string>  $labels
     * @param  list<float>  $revenueSeries
     * @param  array{google_workspace: float, microsoft_365: float, tizzy_mail: float}  $serviceSales
     * @param  list<string>  $paidStatuses
     * @return list<array{key: string, label: string, value: string|int}>
     */
    private function buildQuickInsights(
        int $partnerId,
        array $labels,
        array $revenueSeries,
        array $serviceSales,
        array $paidStatuses,
        ?Carbon $start,
        ?Carbon $end
    ): array {
        $highestMonth = '—';
        if ($revenueSeries !== []) {
            $maxIdx = (int) array_keys($revenueSeries, max($revenueSeries))[0];
            $highestMonth = $labels[$maxIdx] ?? '—';
            if ($highestMonth !== '—') {
                try {
                    $highestMonth = Carbon::createFromFormat('M', $highestMonth)->format('F');
                } catch (\Throwable $e) {
                    // keep short label
                }
            }
        }

        $bestServiceKey = array_search(max($serviceSales), $serviceSales, true);
        $serviceLabels = [
            'google_workspace' => 'Google Workspace',
            'microsoft_365' => 'Microsoft 365',
            'tizzy_mail' => 'Tizzy Mail',
        ];
        $bestSellingService = $serviceLabels[$bestServiceKey] ?? '—';

        $renewalQuery = Order::query()
            ->select('plan_id', DB::raw('COUNT(*) as renewal_count'))
            ->where('partner_id', $partnerId)
            ->whereRaw("LOWER(COALESCE(order_type, 'b2c')) = 'b2b'")
            ->whereIn(DB::raw('LOWER(status)'), $paidStatuses)
            ->whereIn(DB::raw("LOWER(COALESCE(order_category, ''))"), ['renewal', 'renew', 'renewed'])
            ->whereNotNull('plan_id');
        $this->applyProviderFilter($renewalQuery);
        $this->applyCustomerFilter($renewalQuery);
        $this->applyDateRange($renewalQuery, $start, $end);

        $mostRenewedPlanRow = $renewalQuery
            ->groupBy('plan_id')
            ->orderByDesc('renewal_count')
            ->first();

        $mostRenewedPlanName = '—';
        if ($mostRenewedPlanRow && (int) ($mostRenewedPlanRow->plan_id ?? 0) > 0) {
            $plan = Plan::query()->find((int) $mostRenewedPlanRow->plan_id);
            $mostRenewedPlanName = $plan
                ? (string) ($plan->title ?: $plan->name ?: ('Plan #' . $plan->id))
                : ('Plan #' . (int) $mostRenewedPlanRow->plan_id);
        }

        $topCustomerQuery = Customer::query()
            ->select('customers.id', 'customers.company_name', 'customers.name', DB::raw('COALESCE(SUM(orders.total_amount), 0) as revenue'))
            ->join('orders', 'orders.user_id', '=', 'customers.id')
            ->where('customers.partner_id', $partnerId)
            ->whereRaw("LOWER(COALESCE(orders.order_type, 'b2c')) = 'b2b'")
            ->whereIn(DB::raw('LOWER(orders.status)'), $paidStatuses);
        $this->applyProviderFilter($topCustomerQuery, 'orders.plan_id', 'orders.provider_id');
        $this->applyCustomerFilter($topCustomerQuery, 'customers.id');
        $this->applyDateRange($topCustomerQuery, $start, $end, 'orders.created_at');

        $topCustomer = $topCustomerQuery
            ->groupBy('customers.id', 'customers.company_name', 'customers.name')
            ->orderByDesc('revenue')
            ->first();

        $topCustomerName = $topCustomer
            ? (string) ($topCustomer->company_name ?: $topCustomer->name ?: ('Customer #' . $topCustomer->id))
            : '—';

        $pendingRenewalsQuery = Order::query()
            ->where('partner_id', $partnerId)
            ->whereRaw("LOWER(COALESCE(order_type, 'b2c')) = 'b2b'")
            ->whereIn(DB::raw('LOWER(status)'), $paidStatuses)
            ->whereNotIn(DB::raw("LOWER(COALESCE(order_category, ''))"), ['renewed', 'upgraded'])
            ->whereNotNull('subscription_end_date')
            ->whereBetween('subscription_end_date', [now()->toDateString(), now()->addDays(30)->toDateString()]);
        $this->applyProviderFilter($pendingRenewalsQuery);
        $this->applyCustomerFilter($pendingRenewalsQuery);
        $this->applyDateRange($pendingRenewalsQuery, $start, $end, 'created_at');
        $pendingRenewals = (int) $pendingRenewalsQuery->count();

        $openSupportTickets = 0;
        try {
            if (Schema::connection('pgsql_support')->hasTable('support_tickets')) {
                $openSupportTicketsQuery = SupportTicket::query()
                    ->where('partner_id', $partnerId)
                    ->when($this->reportCustomerId > 0, fn ($q) => $q->where('customer_id', $this->reportCustomerId))
                    ->whereIn('status', ['Pending', 'In Progress', 'Escalated']);
                $openSupportTickets = (int) $openSupportTicketsQuery->count();
            }
        } catch (\Throwable $e) {
            $openSupportTickets = 0;
        }

        return [
            'highest_revenue_month' => [
                'key' => 'highest_revenue_month',
                'label' => 'Highest Revenue Month',
                'value' => $highestMonth,
            ],
            'best_selling_service' => [
                'key' => 'best_selling_service',
                'label' => 'Best Selling Service',
                'value' => $bestSellingService,
            ],
            'most_renewed_plan' => [
                'key' => 'most_renewed_plan',
                'label' => 'Most Renewed Plan',
                'value' => $mostRenewedPlanName,
            ],
            'top_customer' => [
                'key' => 'top_customer',
                'label' => 'Top Customer',
                'value' => $topCustomerName,
            ],
            'pending_renewals' => [
                'key' => 'pending_renewals',
                'label' => 'Pending Renewals',
                'value' => $pendingRenewals,
            ],
            'open_support_tickets' => [
                'key' => 'open_support_tickets',
                'label' => 'Open Support Tickets',
                'value' => $openSupportTickets,
            ],
        ];
   
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolveRecentReports(int $partnerId, ?Carbon $start, ?Carbon $end): array
    {
        $connections = ['pgsql_billing', config('database.default')];
        foreach (array_unique(array_filter($connections)) as $connection) {
            try {
                if (!Schema::connection($connection)->hasTable('business_reports')) {
                    continue;
                }

                $query = DB::connection($connection)
                    ->table('business_reports')
                    ->where('partner_id', $partnerId)
                    ->orderByDesc('created_at');

                if ($start && $end) {
                    $query->whereBetween('created_at', [$start, $end]);
                }

                return $query
                    ->limit(10)
                    ->get()
                    ->map(static function ($row) {
                        return [
                            'id' => (int) ($row->id ?? 0),
                            'report_name' => (string) ($row->report_name ?? $row->title ?? ''),
                            'category' => (string) ($row->category ?? ''),
                            'generated_by' => (string) ($row->generated_by ?? 'Admin'),
                            'generated_date' => !empty($row->created_at)
                                ? Carbon::parse((string) $row->created_at)->format('d M Y')
                                : null,
                            'format' => strtoupper((string) ($row->format ?? 'pdf')),
                            'status' => (string) ($row->status ?? 'Generated'),
                            'download_url' => (string) ($row->download_url ?? $row->file_url ?? ''),
                        ];
                    })
                    ->values()
                    ->all();
            } catch (\Throwable $e) {
                continue;
            }
        }

        return [];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function getReportCatalogDefinitions(): array
    {
        return [
            'daily-performance' => [
                'title' => 'Daily Performance',
                'subtitle' => 'Monitor daily sales, orders, renewals, and business activity.',
                'category' => 'General Reports',
                'group' => 'general',
                'group_label' => 'General Reports',
            ],
            'new-customers' => [
                'title' => 'New Customers',
                'subtitle' => 'Recently registered customers.',
                'category' => 'Customers',
                'group' => 'customers',
                'group_label' => 'Customers',
            ],
            'customer-subscriptions' => [
                'title' => 'Subscriptions',
                'subtitle' => 'Customer subscription overview by service.',
                'category' => 'Customers',
                'group' => 'customers',
                'group_label' => 'Customers',
            ],
            'plan-renewals' => [
                'title' => 'Plan Renewals',
                'subtitle' => 'Upcoming and completed renewals.',
                'category' => 'Customers',
                'group' => 'customers',
                'group_label' => 'Customers',
            ],
            'annual-revenue' => [
                'title' => 'Annual Revenue',
                'subtitle' => 'Yearly earnings summary.',
                'category' => 'Billing & Income',
                'group' => 'billing',
                'group_label' => 'Billing & Income',
            ],
            'transactions' => [
                'title' => 'Transactions',
                'subtitle' => 'Payment and transaction history.',
                'category' => 'Billing & Income',
                'group' => 'billing',
                'group_label' => 'Billing & Income',
            ],
            'invoices' => [
                'title' => 'Invoices',
                'subtitle' => 'Paid, pending, and overdue invoices.',
                'category' => 'Billing & Income',
                'group' => 'billing',
                'group_label' => 'Billing & Income',
            ],
            'income-forecast' => [
                'title' => 'Income Forecast',
                'subtitle' => 'Projected business revenue.',
                'category' => 'Billing & Income',
                'group' => 'billing',
                'group_label' => 'Billing & Income',
            ],
            'open-tickets' => [
                'title' => 'Open Tickets',
                'subtitle' => 'Active support requests.',
                'category' => 'Support',
                'group' => 'support',
                'group_label' => 'Support',
            ],
            'resolved-tickets' => [
                'title' => 'Resolved Tickets',
                'subtitle' => 'Successfully closed tickets.',
                'category' => 'Support',
                'group' => 'support',
                'group_label' => 'Support',
            ],
        ];
    }

    /**
     * Catalog keyed by slug (JSON object).
     *
     * @return array<string, array<string, string>>
     */
    private function getReportCatalogObject(): array
    {
        $object = [];
        foreach ($this->getReportCatalogDefinitions() as $slug => $meta) {
            $object[$slug] = array_merge(['slug' => $slug], $meta);
        }

        return $object;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getReportCatalogGroups(): array
    {
        $groups = [];
        foreach ($this->getReportCatalogDefinitions() as $slug => $meta) {
            $groupKey = (string) ($meta['group'] ?? 'general');
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'group' => $groupKey,
                    'group_label' => (string) ($meta['group_label'] ?? ucfirst($groupKey)),
                    'items' => [],
                ];
            }
            $groups[$groupKey]['items'][] = array_merge(['slug' => $slug], $meta);
        }

        return array_values($groups);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveSlugDateRange(
        string $slug,
        ?Carbon $start,
        ?Carbon $end,
        bool $dateFilterApplied,
        int $year,
        bool $yearFilterApplied = false
    ): array {
        if ($dateFilterApplied && $start && $end) {
            return [$start, $end];
        }

        if ($yearFilterApplied) {
            return [
                Carbon::create($year, 1, 1)->startOfDay(),
                Carbon::create($year, 12, 31)->endOfDay(),
            ];
        }

        $now = now();

        return match ($slug) {
            'daily-performance' => [
                $now->copy()->subDays(29)->startOfDay(),
                $now->copy()->endOfDay(),
            ],
            default => [
                Carbon::create($year, 1, 1)->startOfDay(),
                Carbon::create($year, 12, 31)->endOfDay(),
            ],
        };
    }

    /**
     * @return array{description: string, graph: array<string, mixed>, table: array<string, mixed>}
     */
    private function buildSlugReportPayload(
        int $partnerId,
        string $slug,
        Carbon $rangeStart,
        Carbon $rangeEnd,
        int $year,
        string $type = ''
    ): array {
        return match ($slug) {
            'daily-performance' => $this->buildDailyPerformanceReport($partnerId, $rangeStart, $rangeEnd, $type),
            'new-customers' => $this->buildNewCustomersReport($partnerId, $rangeStart, $rangeEnd),
            'customer-subscriptions' => $this->buildCustomerSubscriptionsReport($partnerId, $rangeStart, $rangeEnd, $year),
            'plan-renewals' => $this->buildPlanRenewalsReport($partnerId, $rangeStart, $rangeEnd),
            'annual-revenue' => $this->buildAnnualRevenueReport($partnerId, $rangeStart, $rangeEnd, $year),
            'transactions' => $this->buildMonthlyTransactionsReport($partnerId, $rangeStart, $rangeEnd, $year),
            'invoices' => $this->buildInvoicesReport($partnerId, $rangeStart, $rangeEnd),
            'income-forecast' => $this->buildIncomeForecastReport($partnerId, $rangeStart, $rangeEnd, $year),
            'open-tickets' => $this->buildTicketsReport($partnerId, $rangeStart, $rangeEnd, 'open'),
            'resolved-tickets' => $this->buildTicketsReport($partnerId, $rangeStart, $rangeEnd, 'resolved'),
            default => [
                'description' => '',
                'graph' => ['datasets' => [], 'x_axis' => ['label' => '', 'values' => []], 'y_axis' => ['label' => '', 'datasets' => []]],
                'table' => $this->buildMatrixTable([], []),
            ],
        };
    }

    /**
     * Customer rows for the new-customers report table.
     *
     * @return list<array<string, mixed>>
     */
    private function buildNewCustomerRecords(int $partnerId, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        $customers = Customer::query()
            ->where('partner_id', $partnerId)
            ->when($this->reportCustomerId > 0, fn ($q) => $q->where('id', $this->reportCustomerId))
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->orderByDesc('id')
            ->limit(200)
            ->get([
                'id',
                'customer_no',
                'name',
                'company_name',
                'email',
                'mobile',
                'status',
                'created_at',
            ]);

        $records = [];
        foreach ($customers as $customer) {
            $createdAt = $customer->created_at
                ? Carbon::parse((string) $customer->created_at)
                : null;
            $statusRaw = strtolower(trim((string) ($customer->status ?? '')));
            $statusLabel = $statusRaw === '1' || $statusRaw === 'active'
                ? 'Active'
                : ($statusRaw === '0' || $statusRaw === 'inactive' ? 'Inactive' : ($statusRaw !== '' ? ucwords(str_replace('_', ' ', $statusRaw)) : '-'));

            $records[] = [
                'customer_id' => (int) $customer->id,
                'customer_no' => (string) ($customer->customer_no ?? ''),
                'name' => trim((string) ($customer->name ?? '')) ?: '-',
                'company_name' => trim((string) ($customer->company_name ?? '')) ?: '-',
                'email' => trim((string) ($customer->email ?? '')) ?: '-',
                'mobile' => trim((string) ($customer->mobile ?? '')) ?: '-',
                'status' => $statusLabel,
                'registered' => $this->formatReportDate($createdAt),
            ];
        }

        return $records;
    }

    /**
     * Order cards matching partner report list UI (date, order no, company, contact, plan, domain, licenses, enrollment, status).
     *
     * @return list<array<string, mixed>>
     */
    private function buildCatalogOrderCards(int $partnerId, string $slug, Carbon $rangeStart, Carbon $rangeEnd, string $type = ''): array
    {
        if (in_array($slug, ['open-tickets', 'resolved-tickets', 'new-customers'], true)) {
            return [];
        }

        $query = Order::query()
            ->with(['customer:id,name,company_name', 'items'])
            ->where('partner_id', $partnerId)
            ->where('status', 'completed')
            ->whereIn('order_category',['new','renewed','upgrade','downgrade','addon','renewal','partial_upgrade'])
            ->whereRaw("LOWER(COALESCE(order_type, 'b2c')) = 'b2b'")
            ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) != 'draft'");
        $this->applyProviderFilter($query);
        $this->applyCustomerFilter($query);
        $this->applyDateRange($query, $rangeStart, $rangeEnd);

        if ($slug === 'daily-performance') {
            if ($type === 'renewals') {
                $query->where(function ($q) {
                    $q->whereIn(DB::raw("LOWER(COALESCE(order_category, ''))"), ['renewal', 'renew', 'renewed'])
                        ->orWhere('order_no', 'ilike', 'RN%');
                });
            } elseif ($type === 'sales') {
                $query->whereIn(DB::raw('LOWER(status)'), $this->paidStatuses);
            }
        } elseif ($slug === 'plan-renewals') {
            $query->where(function ($q) {
                $q->whereIn(DB::raw("LOWER(COALESCE(order_category, ''))"), ['renewal', 'renew', 'renewed'])
                    ->orWhere('order_no', 'ilike', 'RN%');
            });
        } elseif (in_array($slug, ['transactions', 'annual-revenue'], true)) {
            $query->whereIn(DB::raw('LOWER(status)'), $this->paidStatuses);
        }

        $orders = $query->orderByDesc('id')->limit(200)->get();
        $planIds = $orders->pluck('plan_id')
            ->merge($orders->flatMap(fn ($o) => collect($o->items)->pluck('plan_id')))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $plansById = $planIds === []
            ? collect()
            : Plan::query()->whereIn('id', $planIds)->get(['id', 'name', 'title'])->keyBy('id');
        $billNoByOrderId = collect();
        if ($slug === 'transactions' && $orders->isNotEmpty()) {
            $billNoByOrderId = InvoiceMst::query()
                ->whereIn('order_id', $orders->pluck('id')->all())
                ->whereRaw("UPPER(TRIM(COALESCE(bill_no_full, ''))) NOT LIKE 'CN/%'")
                ->orderByDesc('id')
                ->get(['order_id', 'bill_no_full'])
                ->unique('order_id')
                ->pluck('bill_no_full', 'order_id');
        }
        $cards = [];

        foreach ($orders as $order) {
            $customer = $order->customer;
            $companyName = trim((string) ($customer->company_name ?? '')) ?: '-';
            $contactName = trim((string) ($customer->name ?? '')) ?: '-';
            $avatar = strtoupper(substr($companyName !== '-' ? $companyName : ($contactName !== '-' ? $contactName : 'C'), 0, 1));
            $domainName = trim((string) ($order->domain_name ?? ''));
            $transferFlag = strtolower(trim((string) ($order->transfer_domain ?? '')));
            $enrollmentType = in_array($transferFlag, ['yes', '1', 'true'], true)
                ? 'Transfer Service'
                : 'New Service';
            
            $displayDate = $order->subscription_end_date
                ? Carbon::parse((string) $order->subscription_end_date)->format('d-m-Y')
                : $this->formatReportDate($order->created_at ? Carbon::parse((string) $order->created_at) : null);

            $lines = collect($order->items ?? []);
            if ($lines->isEmpty()) {
                $lines = collect([null]);
            }
            if($order->status === 'completed') {
                $statusLabel = 'Active';
            } else {
                $statusLabel = '-';
            }

            foreach ($lines as $item) {
                $planId = (int) (is_object($item) ? ($item->plan_id ?? 0) : 0) ?: (int) ($order->plan_id ?? 0);
                $plan = $planId > 0 ? $plansById->get($planId) : null;
                $planName = trim((string) ($plan->name ?? $plan->title ?? '')) ?: '-';
                $licenses = max(1, (int) (is_object($item) ? ($item->quantity ?? 0) : 0) ?: (int) ($order->quantity ?? 1));

                $card = [
                    'date' => $displayDate,
                    'order_id' => (int) $order->id,
                    'order_no' => (string) ($order->order_no ?? ''),
                    'customer_id' => (int) ($order->user_id ?? 0) ?: null,
                    'company_name' => $companyName,
                    'plan_name' => $planName,
                    'domain_name' => $domainName !== '' ? $domainName : '-',
                    'licenses' => $licenses,
                    'enrollment_type' => $enrollmentType,
                    'status' => $statusLabel,
                    "price" => round((float) $order->total_amount, 2),
                    'order_status' => strtolower(trim((string) ($order->status ?? ''))) ?: null,
                ];

                if ($slug === 'transactions') {
                    $card['enrollment_category'] = match (strtolower(trim((string) ($order->order_category ?? '')))) {
                        'upgrade', 'upgraded' => 'upgraded plan',
                        'partial_upgrade' => 'partial upgrade',
                        'addon', 'license_add' => 'license add',
                        'new' => 'new plan',
                        'downgrade', 'downgraded' => 'downgraded plan',
                        'renewed', 'renewal' => 'renewed plan',
                        default => (string) ($order->order_category ?? ''),
                    };
                    $card['bill_no_full'] = trim((string) ($billNoByOrderId->get($order->id) ?? '')) ?: null;
                    $card['status'] = strtolower(trim((string) ($order->status ?? ''))) ?: null;
                }

                $cards[] = $card;
            }
        }

        return $cards;
    }

    private function formatReportCardStatus(string $status): string
    {
        $normalized = strtolower(trim($status));
        if (in_array($normalized, $this->paidStatuses, true)) {
            return 'Active';
        }
        if ($normalized === '') {
            return '-';
        }

        return ucwords(str_replace('_', ' ', $normalized));
    }

    /**
     * @return array{description: string, graph: array<string, mixed>, table: array<string, mixed>}
     */
    private function buildDailyPerformanceReport(int $partnerId, Carbon $start, Carbon $end, string $type = ''): array
    {
        $labels = [];
        $sales = [];
        $orders = [];
        $renewals = [];

        $cursor = $start->copy()->startOfDay();
        while ($cursor->lte($end)) {
            $dayStart = $cursor->copy()->startOfDay();
            $dayEnd = $cursor->copy()->endOfDay();
            $labels[] = $cursor->format('d M');

            $paidQuery = $this->scopedPaidOrdersQuery($partnerId, $this->paidStatuses, $dayStart, $dayEnd);
            $sales[] = round((float) (clone $paidQuery)->sum('total_amount'), 2);
            $ordersQuery = Order::query()
                ->where('partner_id', $partnerId)
                ->whereRaw("LOWER(COALESCE(order_type, 'b2c')) = 'b2b'")
                ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) != 'draft'")
                ->whereBetween('created_at', [$dayStart, $dayEnd]);
            $this->applyProviderFilter($ordersQuery);
            $this->applyCustomerFilter($ordersQuery);
            $orders[] = (int) $ordersQuery->count();
            $renewals[] = (int) (clone $paidQuery)
                ->whereIn(DB::raw("LOWER(COALESCE(order_category, ''))"), ['renewal', 'renew', 'renewed'])
                ->count();

            $cursor->addDay();
        }

        $datasets = [
            'sales' => $sales,
            'orders' => $orders,
            'renewals' => $renewals,
        ];
        $rowLabels = [
            'sales' => 'Sales (₹)',
            'orders' => 'Orders',
            'renewals' => 'Renewals',
        ];
        if (in_array($type, ['sales', 'orders', 'renewals'], true)) {
            $datasets = [$type => $datasets[$type]];
            $rowLabels = [$type => $rowLabels[$type]];
        }

        $yLabel = $type === 'sales' ? 'Sales (₹)' : ($type === 'orders' || $type === 'renewals' ? ucfirst($type) : 'Count / Amount');

        $graphTable = $this->buildMatrixGraphTable(
            $labels,
            $datasets,
            $rowLabels,
            'Day',
            $yLabel
        );

        $description = 'Monitor daily sales, orders, renewals, and business activity.';
        if ($type === 'sales') {
            $description = 'Daily sales performance.';
        } elseif ($type === 'orders') {
            $description = 'Daily orders.';
        } elseif ($type === 'renewals') {
            $description = 'Daily renewals.';
        }

        return array_merge([
            'description' => $description,
        ], $graphTable);
    }

    /**
     * @return array{description: string, graph: array<string, mixed>, table: array<string, mixed>}
     */
    private function buildAnnualRevenueReport(int $partnerId, Carbon $start, Carbon $end, int $year): array
    {
        $labels = [];
        $revenue = [];
        $cursor = $start->copy()->startOfMonth();
        while ($cursor->lte($end)) {
            $bucketStart = $cursor->copy()->startOfMonth();
            $bucketEnd = $cursor->copy()->endOfMonth();
            $labels[] = $cursor->format('M');
            $revenue[] = round((float) $this->scopedPaidOrdersQuery($partnerId, $this->paidStatuses, $bucketStart, $bucketEnd)
                ->sum('total_amount'), 2);
            $cursor->addMonth();
        }

        $graphTable = $this->buildMatrixGraphTable(
            $labels,
            ['revenue' => $revenue],
            ['revenue' => 'Revenue (₹)'],
            'Month',
            'Revenue (₹)'
        );

        return array_merge([
            'description' => 'Yearly earnings summary for ' . $year . '.',
        ], $graphTable);
    }

    /**
     * @return array{description: string, graph: array<string, mixed>, table: array<string, mixed>}
     */
    private function buildNewCustomersReport(int $partnerId, Carbon $start, Carbon $end): array
    {
        $labels = [];
        $counts = [];
        $cursor = $start->copy()->startOfMonth();
        while ($cursor->lte($end)) {
            $bucketStart = $cursor->copy()->startOfMonth();
            $bucketEnd = $cursor->copy()->endOfMonth();
            $labels[] = $cursor->format('M');
            $counts[] = (int) Customer::query()
                ->where('partner_id', $partnerId)
                ->when($this->reportCustomerId > 0, fn ($q) => $q->where('id', $this->reportCustomerId))
                ->whereBetween('created_at', [$bucketStart, $bucketEnd])
                ->count();
            $cursor->addMonth();
        }

        $graphTable = $this->buildMatrixGraphTable(
            $labels,
            ['new_customers' => $counts],
            ['new_customers' => 'New Customers'],
            'Month',
            'Customers'
        );

        return array_merge(['description' => 'Recently registered customers.'], $graphTable);
    }

    /**
     * @return array{description: string, graph: array<string, mixed>, table: array<string, mixed>}
     */
    private function buildCustomerSubscriptionsReport(int $partnerId, Carbon $start, Carbon $end, int $year): array
    {
        $breakdown = $this->buildServiceMonthlyBreakdown($partnerId, $start, $end, 'count');

        return array_merge([
            'description' => 'Customer subscription overview by service for year ' . $year . '.',
        ], $this->buildServiceSalesGraphTable($breakdown, 'Month', 'Subscriptions', [
            'tizzy_mail' => 'Tizzy Mail',
            'microsoft_365' => 'Microsoft Solutions',
            'google_workspace' => 'Google Workspace',
            'total' => 'Total',
        ]));
    }

    /**
     * @return array{description: string, graph: array<string, mixed>, table: array<string, mixed>}
     */
    private function buildPlanRenewalsReport(int $partnerId, Carbon $start, Carbon $end): array
    {
        $labels = [];
        $completed = [];
        $upcoming = [];
        $cursor = $start->copy()->startOfMonth();
        $now = now();

        while ($cursor->lte($end)) {
            $bucketStart = $cursor->copy()->startOfMonth();
            $bucketEnd = $cursor->copy()->endOfMonth();
            $labels[] = $cursor->format('M');

            $completed[] = (int) $this->scopedPaidOrdersQuery($partnerId, $this->paidStatuses, $bucketStart, $bucketEnd)
                ->whereIn(DB::raw("LOWER(COALESCE(order_category, ''))"), ['renewal', 'renew', 'renewed'])
                ->count();

            $upcomingQuery = Order::query()
                ->where('partner_id', $partnerId)
                ->whereRaw("LOWER(COALESCE(order_type, 'b2c')) = 'b2b'")
                ->whereIn(DB::raw('LOWER(status)'), $this->paidStatuses)
                ->whereNotIn(DB::raw("LOWER(COALESCE(order_category, ''))"), ['renewed', 'upgraded'])
                ->whereNotNull('subscription_end_date')
                ->whereBetween('subscription_end_date', [$bucketStart->toDateString(), $bucketEnd->toDateString()])
                ->where('subscription_end_date', '>=', $now->toDateString());
            $this->applyProviderFilter($upcomingQuery);
            $this->applyCustomerFilter($upcomingQuery);
            $upcoming[] = (int) $upcomingQuery->count();

            $cursor->addMonth();
        }

        $graphTable = $this->buildMatrixGraphTable(
            $labels,
            ['completed' => $completed, 'upcoming' => $upcoming],
            ['completed' => 'Completed', 'upcoming' => 'Upcoming'],
            'Month',
            'Renewals'
        );

        return array_merge(['description' => 'Upcoming and completed renewals.'], $graphTable);
    }

    /**
     * @return array{description: string, graph: array<string, mixed>, table: array<string, mixed>}
     */
    private function buildMonthlyTransactionsReport(int $partnerId, Carbon $start, Carbon $end, int $year): array
    {
        $labels = [];
        $amounts = [];
        $counts = [];
        $cursor = $start->copy()->startOfMonth();
        while ($cursor->lte($end)) {
            $bucketStart = $cursor->copy()->startOfMonth();
            $bucketEnd = $cursor->copy()->endOfMonth();
            $labels[] = $cursor->format('M');
            $paidQuery = $this->scopedPaidOrdersQuery($partnerId, $this->paidStatuses, $bucketStart, $bucketEnd);
            $amounts[] = round((float) (clone $paidQuery)->sum('total_amount'), 2);
            $counts[] = (int) (clone $paidQuery)->count();
            $cursor->addMonth();
        }

        $graphTable = $this->buildMatrixGraphTable(
            $labels,
            ['amount' => $amounts, 'transactions' => $counts],
            ['amount' => 'Amount (₹)', 'transactions' => 'Transactions'],
            'Month',
            'Amount / Count'
        );

        return array_merge(['description' => 'Payment and transaction history.'], $graphTable);
    }

    private function buildInvoicesReport(int $partnerId, Carbon $start, Carbon $end): array
    {
        $partnerOrderQuery = Order::query()
            ->where('partner_id', $partnerId);
        $this->applyProviderFilter($partnerOrderQuery);
        $this->applyCustomerFilter($partnerOrderQuery);
        $partnerOrderIds = $partnerOrderQuery->pluck('id');

        $labels = [];
        $paidSeries = [];
        $pendingSeries = [];
        $overdueSeries = [];
        $today = now()->toDateString();

        $cursor = $start->copy()->startOfMonth();
        while ($cursor->lte($end)) {
            $bucketStart = $cursor->copy()->startOfMonth();
            $bucketEnd = $cursor->copy()->endOfMonth();
            $labels[] = $cursor->format('M');

            $invoices = Invoice::query()
                ->where(function ($q) use ($partnerId, $partnerOrderIds) {
                    if ($this->reportCustomerId > 0) {
                        $q->where('customer_id', $this->reportCustomerId);
                        if ($partnerOrderIds->isNotEmpty()) {
                            $q->orWhereIn('order_id', $partnerOrderIds);
                        }
                    } else {
                        $q->where('partner_id', $partnerId);
                        if ($partnerOrderIds->isNotEmpty()) {
                            $q->orWhereIn('order_id', $partnerOrderIds);
                        }
                    }
                })
                ->where(function ($q) {
                    $q->whereNull('action_type')
                        ->orWhereRaw("LOWER(TRIM(COALESCE(action_type, ''))) <> 'credit_note'");
                })
                ->whereRaw("UPPER(TRIM(COALESCE(bill_no_full, ''))) NOT LIKE 'CN/%'")
                ->where(function ($q) use ($bucketStart, $bucketEnd) {
                    $q->whereBetween('created_at', [$bucketStart, $bucketEnd])
                        ->orWhereBetween('bill_date', [$bucketStart->toDateString(), $bucketEnd->toDateString()]);
                })
                ->get(['net_amt', 'status', 'due_date']);

            $paid = 0.0;
            $pending = 0.0;
            $overdue = 0.0;
            foreach ($invoices as $inv) {
                $amount = (float) ($inv->net_amt ?? 0);
                $status = strtolower(trim((string) ($inv->status ?? '')));
                if (in_array($status, ['paid', 'completed', 'success'], true)) {
                    $paid += $amount;
                } elseif ($inv->due_date && (string) $inv->due_date < $today) {
                    $overdue += $amount;
                } else {
                    $pending += $amount;
                }
            }

            $paidSeries[] = round($paid, 2);
            $pendingSeries[] = round($pending, 2);
            $overdueSeries[] = round($overdue, 2);
            $cursor->addMonth();
        }

        $graphTable = $this->buildMatrixGraphTable(
            $labels,
            ['paid' => $paidSeries, 'pending' => $pendingSeries, 'overdue' => $overdueSeries],
            ['paid' => 'Paid', 'pending' => 'Pending', 'overdue' => 'Overdue'],
            'Month',
            'Amount (₹)'
        );

        return array_merge(['description' => 'Paid, pending, and overdue invoices.'], $graphTable);
    }

    /**
     * @return array{description: string, graph: array<string, mixed>, table: array<string, mixed>}
     */
    private function buildIncomeForecastReport(int $partnerId, Carbon $start, Carbon $end, int $year): array
    {
        $historical = $this->buildAnnualRevenueReport($partnerId, $start, $end, $year);
        $revenue = $historical['graph']['datasets']['revenue'] ?? [];
        $labels = $historical['graph']['x_axis']['values'] ?? [];
        $avg = $revenue !== [] ? array_sum($revenue) / count($revenue) : 0.0;

        $forecastLabels = $labels;
        $forecast = [];
        foreach ($revenue as $idx => $val) {
            $growth = $idx > 0 && ($revenue[$idx - 1] ?? 0) > 0
                ? ($val / $revenue[$idx - 1])
                : 1.0;
            $forecast[] = round(max(0, $val > 0 ? $val * min(1.15, max(0.85, $growth)) : $avg), 2);
        }

        $graphTable = $this->buildMatrixGraphTable(
            $forecastLabels,
            ['actual' => $revenue, 'forecast' => $forecast],
            ['actual' => 'Actual Revenue', 'forecast' => 'Forecast Revenue'],
            'Month',
            'Revenue (₹)'
        );

        return array_merge(['description' => 'Projected business revenue based on recent trends.'], $graphTable);
    }

    /**
     * @return array{description: string, graph: array<string, mixed>, table: array<string, mixed>}
     */
    private function buildTicketsReport(int $partnerId, Carbon $start, Carbon $end, string $mode): array
    {
        $openStatuses = ['Pending', 'In Progress', 'Escalated'];
        $resolvedStatuses = ['Resolved', 'Closed'];

        try {
            if (!Schema::connection('pgsql_support')->hasTable('support_tickets')) {
                return [
                    'description' => $mode === 'open' ? 'Active support requests.' : 'Successfully closed tickets.',
                    'graph' => ['datasets' => [], 'x_axis' => ['label' => 'Month', 'values' => []], 'y_axis' => ['label' => 'Tickets', 'datasets' => []]],
                    'table' => $this->buildMatrixTable([], []),
                ];
            }
        } catch (\Throwable $e) {
            return [
                'description' => $mode === 'open' ? 'Active support requests.' : 'Successfully closed tickets.',
                'graph' => ['datasets' => [], 'x_axis' => ['label' => 'Month', 'values' => []], 'y_axis' => ['label' => 'Tickets', 'datasets' => []]],
                'table' => $this->buildMatrixTable([], []),
            ];
        }

        $statuses = $mode === 'open' ? $openStatuses : $resolvedStatuses;
        $labels = [];
        $counts = [];
        $cursor = $start->copy()->startOfMonth();
        while ($cursor->lte($end)) {
            $bucketStart = $cursor->copy()->startOfMonth();
            $bucketEnd = $cursor->copy()->endOfMonth();
            $labels[] = $cursor->format('M');
            $counts[] = (int) SupportTicket::query()
                ->where('partner_id', $partnerId)
                ->when($this->reportCustomerId > 0, fn ($q) => $q->where('customer_id', $this->reportCustomerId))
                ->whereIn('status', $statuses)
                ->whereBetween('created_at', [$bucketStart, $bucketEnd])
                ->count();
            $cursor->addMonth();
        }

        $key = $mode === 'open' ? 'open_tickets' : 'resolved_tickets';
        $graphTable = $this->buildMatrixGraphTable(
            $labels,
            [$key => $counts],
            [$key => $mode === 'open' ? 'Open Tickets' : 'Resolved Tickets'],
            'Month',
            'Tickets'
        );

        return array_merge([
            'description' => $mode === 'open' ? 'Active support requests.' : 'Successfully closed tickets.',
        ], $graphTable);
    }

    /**
     * @return array{labels: list<string>, tizzy_mail: list<float|int>, microsoft_365: list<float|int>, google_workspace: list<float|int>, total: list<float|int>}
     */
    private function buildServiceMonthlyBreakdown(
        int $partnerId,
        Carbon $start,
        Carbon $end,
        string $metric = 'amount'
    ): array {
        $labels = [];
        $tizzy = [];
        $microsoft = [];
        $google = [];
        $total = [];

        $cursor = $start->copy()->startOfMonth();
        while ($cursor->lte($end)) {
            $bucketStart = $cursor->copy()->startOfMonth();
            $bucketEnd = $cursor->copy()->endOfMonth();
            $labels[] = $cursor->format('M');

            if ($metric === 'count') {
                $sales = $this->resolveSalesByService($partnerId, $this->paidStatuses, $bucketStart, $bucketEnd);
                $orderCount = (int) $this->scopedPaidOrdersQuery($partnerId, $this->paidStatuses, $bucketStart, $bucketEnd)->count();
                $sum = max(0.01, array_sum($sales));
                $tizzy[] = (int) round($orderCount * ($sales['tizzy_mail'] / $sum));
                $microsoft[] = (int) round($orderCount * ($sales['microsoft_365'] / $sum));
                $google[] = (int) round($orderCount * ($sales['google_workspace'] / $sum));
                $total[] = $orderCount;
            } else {
                $sales = $this->resolveSalesByService($partnerId, $this->paidStatuses, $bucketStart, $bucketEnd);
                $tizzy[] = round($sales['tizzy_mail'], 2);
                $microsoft[] = round($sales['microsoft_365'], 2);
                $google[] = round($sales['google_workspace'], 2);
                $total[] = round(array_sum($sales), 2);
            }

            $cursor->addMonth();
        }

        return [
            'labels' => $labels,
            'tizzy_mail' => $tizzy,
            'microsoft_365' => $microsoft,
            'google_workspace' => $google,
            'total' => $total,
        ];
    }

    /**
     * @param  array{labels: list<string>, tizzy_mail: list<float|int>, microsoft_365: list<float|int>, google_workspace: list<float|int>, total: list<float|int>}  $breakdown
     * @param  array<string, string>|null  $rowLabels
     * @return array{graph: array<string, mixed>, table: array<string, mixed>}
     */
    private function buildServiceSalesGraphTable(
        array $breakdown,
        string $xLabel,
        string $yLabel,
        ?array $rowLabels = null
    ): array {
        $rowLabels ??= [
            'tizzy_mail' => 'Tizzy Mail',
            'microsoft_365' => 'Microsoft Solutions',
            'google_workspace' => 'Google Workspace',
            'total' => 'Total Sales',
        ];

        $datasets = [
            'tizzy_mail' => $breakdown['tizzy_mail'],
            'microsoft_365' => $breakdown['microsoft_365'],
            'google_workspace' => $breakdown['google_workspace'],
            'total_sales' => $breakdown['total'],
        ];

        $yDatasets = [];
        foreach ($datasets as $key => $data) {
            $yDatasets[] = [
                'label' => $rowLabels[str_replace('_sales', '', $key)] ?? ucfirst(str_replace('_', ' ', $key)),
                'key' => $key,
                'data' => $data,
            ];
        }

        $tableRows = [];
        foreach (['tizzy_mail', 'microsoft_365', 'google_workspace'] as $key) {
            $tableRows[] = [
                'label' => $rowLabels[$key] ?? $key,
                'key' => $key,
                'values' => $breakdown[$key],
            ];
        }

        return [
            'graph' => [
                'datasets' => $datasets,
                'x_axis' => ['label' => $xLabel, 'values' => $breakdown['labels']],
                'y_axis' => ['label' => $yLabel, 'datasets' => $yDatasets],
            ],
            'table' => $this->buildMatrixTable($breakdown['labels'], $tableRows),
        ];
    }
    
    private function buildMatrixTable(array $columns, array $rows): array
    {
        return [
            'type' => 'matrix',
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, list<float|int>>  $datasets
     * @param  array<string, string>  $rowLabels
     * @return array{graph: array<string, mixed>, table: array<string, mixed>}
     */
    private function buildMatrixGraphTable(
        array $labels,
        array $datasets,
        array $rowLabels,
        string $xLabel,
        string $yLabel
    ): array {
        $yDatasets = [];
        $tableRows = [];
        foreach ($datasets as $key => $values) {
            $yDatasets[] = [
                'label' => $rowLabels[$key] ?? ucfirst($key),
                'key' => $key,
                'data' => $values,
            ];
            $tableRows[] = [
                'label' => $rowLabels[$key] ?? ucfirst($key),
                'key' => $key,
                'values' => $values,
            ];
        }

        return [
            'graph' => [
                'datasets' => $datasets,
                'x_axis' => ['label' => $xLabel, 'values' => $labels],
                'y_axis' => ['label' => $yLabel, 'datasets' => $yDatasets],
            ],
            'table' => $this->buildMatrixTable($labels, $tableRows),
        ];
    }

    private function growthPercent(float $current, float $previous): float
    {
        if ($previous > 0) {
            return round((($current - $previous) / $previous) * 100, 0);
        }

        return $current > 0 ? 100.0 : 0.0;
    }

    private function formatIndianNumber(float $amount): string
    {
        $num = number_format($amount, 2, '.', '');
        $parts = explode('.', $num);
        $lastThree = substr($parts[0], -3);
        $rest = substr($parts[0], 0, -3);
        if ($rest !== '') {
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $formatted = $rest . ',' . $lastThree;
        } else {
            $formatted = $lastThree;
        }

        return $formatted . (isset($parts[1]) && $parts[1] !== '00' ? '.' . $parts[1] : '');
    }

    // renew update manual
    /**
     * Check whether an order appears in POST /renewals list (same filters + plan-line rules).
     * Body: order_id (required), partner_id? (optional), days? (default 30)
     */
    public function renewalCheck(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|integer',
            'partner_id' => 'nullable|integer',
            'days' => 'nullable|integer|min:0',
        ]);

        $orderId = (int) $validated['order_id'];
        $order = Order::query()->find($orderId);
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.',
                'data' => null,
            ], 404);
        }

        $partnerId = (int) ($validated['partner_id'] ?? $order->partner_id ?? 0);
        if ($partnerId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'partner_id is required.',
                'data' => null,
            ], 422);
        }

        if ((int) ($order->partner_id ?? 0) !== $partnerId) {
            return response()->json([
                'success' => false,
                'message' => 'Order does not belong to this partner.',
                'data' => null,
            ], 403);
        }

        $days = array_key_exists('days', $validated) ? (int) $validated['days'] : 30;

        $renewalPayload = app(PartnerAuthController::class)
            ->renewalsindex(new Request([
                'partner_id' => $partnerId,
                'request_id' => $orderId,
                'days' => $days,
            ]))
            ->getData(true);

        $renewalRow = collect($renewalPayload['data'] ?? [])
            ->first(fn ($row) => (int) ($row['order_id'] ?? 0) === $orderId);

        $inList = $renewalRow !== null;

        return response()->json([
            'success' => true,
            'message' => $inList
                ? 'Order is in the renewal list.'
                : 'Order is not in the renewal list.',
            'data' => [
                'order_id' => $orderId,
                'order_no' => (string) ($order->order_no ?? ''),
                'partner_id' => $partnerId,
                'days' => $days,
                'in_renewal_list' => $inList,
                'renewal' => $renewalRow,
                'criteria' => $this->buildRenewalCriteriaSnapshot($order, $days),
            ],
        ]);
    }

    /**
     * Order-level renewal gates (same as renewalsindex query before plan-line filter).
     *
     * @return array<string, mixed>
     */
    private function buildRenewalCriteriaSnapshot(Order $order, int $days): array
    {
        $today = Carbon::now()->startOfDay();
        $threshold = $today->copy()->addDays($days)->endOfDay();
        $status = strtolower(trim((string) ($order->status ?? '')));
        $category = strtolower(trim((string) ($order->order_category ?? '')));
        $endDate = $order->subscription_end_date
            ? Carbon::parse($order->subscription_end_date)
            : null;

        $checks = [
            'status_completed_or_active' => in_array($status, ['completed', 'active'], true),
            'subscription_end_date_set' => $endDate !== null,
            'subscription_end_within_days' => $endDate !== null && $endDate->lte($threshold),
            'order_category_allowed' => !in_array($category, ['renewed', 'upgraded', 'downgraded'], true),
        ];

        if (Schema::hasColumn('orders', 'order_type')) {
            $checks['order_type_b2b'] = strtolower(trim((string) ($order->order_type ?? ''))) === 'b2b';
        }

        $daysLeft = $endDate ? (int) $today->diffInDays($endDate->copy()->startOfDay(), false) : null;

        return [
            'checks' => $checks,
            'all_basic_checks_pass' => !in_array(false, $checks, true),
            'order_status' => $order->status,
            'order_category' => $order->order_category,
            'order_type' => $order->order_type ?? null,
            'subscription_end_date' => $endDate?->format('Y-m-d'),
            'renewal_threshold' => $threshold->format('Y-m-d'),
            'days_left' => $daysLeft,
            'note' => 'If basic checks pass but in_renewal_list is false, order_items may be fully upgraded/renewed (same plan-line filter as POST /renewals).',
        ];
    }

    public function sendPurchaseRequest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'partner_id'    => ['required', 'integer', 'exists:pgsql_auth.partners,id'],
            'main_cart_id'  => ['required_without:cart_id', 'nullable', 'integer'],
            'cart_id'       => ['required_without:main_cart_id', 'nullable', 'integer'],
            'plan_id'       => ['nullable', 'integer', 'exists:pgsql_product.plans,id'],
            'order_id'      => ['nullable', 'integer'],
            'order_sub_id'  => ['nullable', 'integer'],
            'customer_id'   => ['nullable', 'integer'],
            'licenses'      => ['nullable', 'integer', 'min:1'],
            'license_count' => ['nullable', 'integer', 'min:1'],
            'request_type'  => ['nullable', 'string'],
            'action'        => ['nullable', 'string'],
            'message'       => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $service = app(\App\Services\PurchaseRequestService::class);
            $purchaseRequest = $service->createPurchaseRequest($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Your request has been submitted to SuperAdmin successfully. Our team will review your request and contact you shortly.',
                'data' => [
                    'request_id'   => (int) $purchaseRequest->id,
                    'partner_id'   => (int) $purchaseRequest->partner_id,
                    'plan_id'      => (int) ($purchaseRequest->plan_id ?? 0),
                    'main_cart_id' => (int) ($purchaseRequest->cart_id ?? 0),
                    'licenses'     => (int) $purchaseRequest->licenses,
                    'request_type' => $purchaseRequest->request_type,
                    'status'       => $purchaseRequest->status,
                ],
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating purchase request.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve purchase request by SuperAdmin and send checkout link email to partner.
     */
    public function approvePurchaseRequest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'request_id'  => ['required', 'integer', 'exists:pgsql_billing.purchase_requests,id'],
            'admin_notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $service = app(\App\Services\PurchaseRequestService::class);
            $result = $service->approvePurchaseRequest(
                (int) $request->input('request_id'),
                $request->input('admin_notes')
            );

            return response()->json([
                'success' => true,
                'message' => 'Purchase request approved successfully and checkout email sent to partner.',
                'data'    => $result,
            ]);
        } catch (\InvalidArgumentException|\LogicException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while approving purchase request.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
