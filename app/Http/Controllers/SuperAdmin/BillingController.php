<?php

namespace App\Http\Controllers\SuperAdmin;

use Carbon\Carbon;
use App\Http\Controllers\Controller;
use App\Models\AdminSetting;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\InvoiceMst;
use App\Models\InvoiceSub;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPoDetail;
use App\Models\OrderPoSub;
use App\Models\Partner;
use App\Models\PartnerAadhaarVerification;
use App\Models\Plan;
use App\Models\Promocode;
use App\Models\Provider;
use App\Models\PvSetting;
use App\Models\User;
use App\Models\Variant;
use App\Services\OrderCancellationCreditNoteService;
use App\Services\OrderCancellationPolicy;
use App\Services\PartnerAadhaarVerificationService;
use App\Services\PartnerCreditService;
use App\Services\InvoiceService;
use App\Services\RenewalService;
use App\Services\SandboxEInvoiceService;
use App\Services\SmsService;
use App\Support\OrderDomainHelper;
use App\Support\OrderPrefixResolver;
use App\Support\GstSplit;
use App\Mail\OrderPlacedClientMail;
use App\Mail\PlanRenewedMail;
use App\Mail\PlanUpgradedMail;
use App\Services\B2bSubscriptionUpgradeService;
use App\Services\UpgradeProrationService;
use App\Http\Controllers\Api\PartnerOrderController;
use App\Http\Controllers\SuperAdmin\B2B\B2bNewPlanController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class BillingController extends Controller
{
    protected Order $orders;
    protected OrderItem $orderItems;
    protected OrderPoDetail $orderPoDetails;
    protected OrderPoSub $orderPoSubs;
    protected Customer $customers;
    protected InvoiceMst $invoiceMst;
    protected InvoiceSub $invoiceSubs;
    protected Partner $partners;
    protected User $users;
    protected PartnerAadhaarVerification $partnerAadhaarVerifications;
    protected Plan $plans;
    protected Variant $variants;
    protected Provider $providers;
    protected AdminSetting $adminSettings;
    protected NotificationPreference $notifications;
    protected Promocode $promocodes;
    protected PvSetting $pvSettings;

    public function __construct()
    {
        $this->orders = new Order();
        $this->orderItems = new OrderItem();
        $this->orderPoDetails = new OrderPoDetail();
        $this->orderPoSubs = new OrderPoSub();
        $this->customers = new Customer();
        $this->invoiceMst = new InvoiceMst();
        $this->invoiceSubs = new InvoiceSub();
        $this->partners = new Partner();
        $this->users = new User();
        $this->partnerAadhaarVerifications = new PartnerAadhaarVerification();
        $this->plans = new Plan();
        $this->variants = new Variant();
        $this->providers = new Provider();
        $this->adminSettings = new AdminSetting();
        $this->notifications = new NotificationPreference();
        $this->promocodes = new Promocode();
        $this->pvSettings = new PvSetting();
    }

    public function index(Request $request)
    {
        return view('superadmin.orders.index', [
            'title' => 'Transactions - Orders',
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    /**
     * Server-side DataTables JSON for the orders listing (see B2C customers list-table pattern).
     */
    public function ordersListTable(Request $request)
    {
        $draw = (int) $request->input('draw', 1);
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length <= 0) {
            $length = 10;
        }
        if ($length > 500) {
            $length = 500;
        }

        $orderColumns = \Illuminate\Support\Facades\Schema::connection($this->orders->getConnectionName())
            ->getColumnListing('orders');
        $hasOrderCustomerId = in_array('customer_id', $orderColumns, true);
        $orderCustomerFk = $hasOrderCustomerId ? 'o.customer_id' : 'o.user_id';

        $query = $this->orders->newQuery()
            ->from('orders as o')
            ->leftJoin('customers as c', $orderCustomerFk, '=', 'c.id')
            ->leftJoin('order_po_details as opd', 'o.id', '=', 'opd.order_id');

        // Never list draft / pre-place orders — those stay in partner cart until Place Order.
        $query->whereRaw("LOWER(TRIM(COALESCE(o.status, ''))) != 'draft'");

        // =============================================
        // APPLY ALL FILTERS
        // =============================================

        $partnerId = (int) $request->input('partner_id', 0);
        if ($partnerId > 0) {
            $query->where(function ($q) use ($partnerId, $hasOrderCustomerId) {
                $q->where('o.partner_id', $partnerId)
                    ->orWhere('c.partner_id', $partnerId);

                // Some older rows may miss o.partner_id but still belong via order customer reference.
                if ($hasOrderCustomerId) {
                    $q->orWhereIn('o.customer_id', function ($qq) use ($partnerId) {
                        $qq->select('id')->from('customers')->where('partner_id', $partnerId);
                    });
                }
            });
        }

        $customerId = (int) $request->input('customer_id', 0);
        if ($customerId <= 0) {
            $customerId = (int) $request->input('filter_customer_id', 0);
        }
        if ($customerId > 0) {
            $query->where(function ($q) use ($customerId, $hasOrderCustomerId) {
                $q->where('o.user_id', $customerId);
                if ($hasOrderCustomerId) {
                    $q->orWhere('o.customer_id', $customerId);
                }
            });
        }

        $orderType = strtolower(trim((string) $request->input('order_type', '')));
        if ($orderType !== '' && in_array($orderType, ['b2b', 'b2c'], true)) {
            $query->whereRaw('LOWER(COALESCE(o.order_type, \'b2c\')) = ?', [$orderType]);
        }

        $totalData = (clone $query)->count();

        $status = $request->input('status');
        $filterStatus = strtolower(trim((string) $request->input('filter_status', '')));
        if ($filterStatus !== '') {
            $query->whereRaw('LOWER(COALESCE(o.status, \'pending\')) = ?', [$filterStatus]);
        } elseif ($status) {
            $query->where('o.status', $status);
        }

        $orderId = trim((string) $request->input('order_id', ''));
        $filterOrderId = trim((string) $request->input('filter_order_id', ''));
        if ($filterOrderId === '' && $orderId !== '') {
            $filterOrderId = $orderId;
        }
        if ($filterOrderId !== '') {
            $query->where(function ($q) use ($filterOrderId) {
                if (ctype_digit($filterOrderId)) {
                    $q->where('o.id', (int) $filterOrderId)
                        ->orWhere('o.order_no', 'like', '%' . $filterOrderId . '%');
                } else {
                    $q->where('o.order_no', 'like', '%' . addcslashes($filterOrderId, '%_\\') . '%');
                }
            });
        }

        $fromDate = trim((string) ($request->input('from_date') ?: $request->input('filter_from_date', '')));
        $toDate = trim((string) ($request->input('to_date') ?: $request->input('filter_to_date', '')));
        if ($fromDate) {
            $query->whereDate('o.created_at', '>=', Carbon::parse($fromDate));
        }
        if ($toDate) {
            $query->whereDate('o.created_at', '<=', Carbon::parse($toDate));
        }

        $customerName = trim((string) $request->input('customer_name', ''));
        if ($customerName !== '') {
            $like = '%' . addcslashes($customerName, '%_\\') . '%';
            $query->where(function ($q) use ($like) {
                $q->where('c.name', 'like', $like)
                    ->orWhere('c.company_name', 'like', $like);
            });
        }

        $partnerName = trim((string) $request->input('partner_name', ''));
        if ($partnerName !== '') {
            $like = '%' . addcslashes($partnerName, '%_\\') . '%';
            // partners live on pgsql_auth — resolve IDs first, then filter billing orders.
            $partnerIds = $this->partners->newQuery()
                ->where(function ($q) use ($like) {
                    $q->where('name', 'like', $like)
                        ->orWhere('company_name', 'like', $like);
                })
                ->pluck('id')
                ->map(static fn($id) => (int) $id)
                ->filter(static fn($id) => $id > 0)
                ->values()
                ->all();

            if ($partnerIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($q) use ($partnerIds) {
                    $q->whereIn('o.partner_id', $partnerIds)
                        ->orWhereIn('c.partner_id', $partnerIds);
                });
            }
        }

        $planName = trim((string) ($request->input('plan_name') ?: $request->input('filter_plan', '')));
        if ($planName !== '') {
            $planIds = Plan::where('name', 'like', '%' . $planName . '%')
                ->orWhere('title', 'like', '%' . $planName . '%')
                ->pluck('id')
                ->toArray();
            if (!empty($planIds)) {
                $query->whereIn('o.plan_id', $planIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $domainName = trim((string) ($request->input('domain_name') ?: $request->input('filter_domain', '')));
        if ($domainName !== '') {
            $query->where('o.domain_name', 'like', '%' . addcslashes($domainName, '%_\\') . '%');
        }

        $orderCategory = trim((string) ($request->input('order_category') ?: $request->input('filter_category', '')));
        if ($orderCategory !== '') {
            $query->whereRaw('LOWER(COALESCE(o.order_category, \'new\')) = ?', [strtolower($orderCategory)]);
        }

        $poStatus = $request->input('po_status');
        if ($poStatus) {
            if ($poStatus === 'po_pending') {
                $query->where(function ($q) {
                    $q->whereNull('opd.journey_status')
                        ->orWhere('opd.journey_status', '')
                        ->orWhere('opd.journey_status', 'po_pending');
                });
            } elseif ($poStatus === 'aadhar_pending') {
                $query->where('opd.journey_status', 'po_done');
            } elseif ($poStatus === 'otp_pending') {
                $query->where('opd.journey_status', 'aadhar_done');
            } elseif ($poStatus === 'placed') {
                $query->where('opd.journey_status', 'placed');
            }
        }

        // =============================================
        // GLOBAL SEARCH
        // =============================================
        $searchInput = $request->input('search');
        $globalSearch = is_array($searchInput) ? trim((string) ($searchInput['value'] ?? '')) : '';

        if ($globalSearch !== '') {
            $like = '%' . addcslashes($globalSearch, '%_\\') . '%';
            $query->where(function ($q) use ($like, $globalSearch) {
                $q->where('o.order_no', 'like', $like)
                    ->orWhere('o.id', 'like', $like)
                    ->orWhere('o.domain_name', 'like', $like)
                    ->orWhere('o.order_type', 'like', $like)
                    ->orWhere('c.name', 'like', $like)
                    ->orWhere('c.company_name', 'like', $like)
                    ->orWhere('c.mobile', 'like', $like)
                    ->orWhere('c.email', 'like', $like)
                    ->orWhere('opd.po_mode', 'like', $like)
                    ->orWhere('opd.journey_status', 'like', $like);

                if (is_numeric($globalSearch)) {
                    $q->orWhere('o.id', (int) $globalSearch);
                }
            });
        }

        // =============================================
        // COUNT FILTERED RECORDS
        // =============================================
        $recordsFiltered = (clone $query)->count();

        // =============================================
        // SORTING
        // =============================================
        $sortBy = strtolower(trim((string) $request->input('sort_by', '')));
        $sortColumn = 'o.id';
        $sortDirection = 'desc';

        if ($sortBy === 'oldest') {
            $sortColumn = 'o.id';
            $sortDirection = 'asc';
        } elseif ($sortBy === 'amount_desc') {
            $sortColumn = 'o.total_amount';
            $sortDirection = 'desc';
        } elseif ($sortBy === 'amount_asc') {
            $sortColumn = 'o.total_amount';
            $sortDirection = 'asc';
        } else {
            $order = $request->input('order');
            $columns = $request->input('columns');
            if (is_array($order) && isset($order[0]) && is_array($columns)) {
                $idx = (int) ($order[0]['column'] ?? -1);
                $dir = strtolower((string) ($order[0]['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
                $name = (string) ($columns[$idx]['name'] ?? $columns[$idx]['data'] ?? '');
                $map = [
                    'id' => 'o.id',
                    'customer_name' => 'c.name',
                    'domain_name' => 'o.domain_name',
                    'amount' => 'o.total_amount',
                    'credit_used' => 'o.credit_used',
                    'status' => 'o.status',
                    'created_at' => 'o.created_at',
                ];
                if (isset($map[$name])) {
                    $sortColumn = $map[$name];
                    $sortDirection = $dir;
                }
            }
        }

        // =============================================
        // FETCH ROWS
        // =============================================
        $rows = (clone $query)
            ->selectRaw("
            o.id,
            o.plan_id,
            o.partner_id,
            o.user_id,
            CASE WHEN LOWER(COALESCE(o.order_type, 'b2c')) = 'b2b'
                THEN COALESCE(NULLIF(c.name, ''), NULLIF(c.company_name, ''), '-')
                ELSE '-'
            END as customer_name,
            COALESCE(NULLIF(o.domain_name, ''), '-') as domain_name,
            COALESCE(o.order_type, 'b2c') as order_type,
            COALESCE(o.total_amount, 0) as amount,
            COALESCE(o.credit_used, 0) as credit_used,
            o.created_at,
            COALESCE(o.status, 'pending') as status,
            COALESCE(NULLIF(o.order_category, ''), 'new') as order_category,
            o.order_no,
            COALESCE(opd.po_mode, '-') as po_mode,
            COALESCE(opd.journey_status, '-') as po_journey_status
        ")
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('o.id', 'desc')
            ->offset($start)
            ->limit($length)
            ->get();

        // =============================================
        // GET RELATED DATA - FIXED: Always define these variables
        // =============================================
        $planIds = $rows->pluck('plan_id')->unique()->filter()->values()->all();
        $plansById = $this->plans->newQuery()
            ->whereIn('id', $planIds ?: [-1])
            ->get(['id', 'name', 'title'])
            ->keyBy('id');

        $orderIds = $rows->pluck('id')->unique()->filter()->values()->all();
        $partnerIds = $rows->pluck('partner_id')->unique()->filter()->values()->all();
        $partnersById = $this->partners->newQuery()
            ->whereIn('id', $partnerIds ?: [-1])
            ->get(['id', 'name', 'company_name'])
            ->keyBy('id');

        $activeOrderItemCounts = $this->orderItems->newQuery()
            ->selectRaw('order_id, COUNT(*) as aggregate')
            ->whereIn('order_id', $orderIds ?: [-1])
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereNotIn('status', ['delete', 'deleted']);
            })
            ->groupBy('order_id')
            ->pluck('aggregate', 'order_id');

        $creditNotesByOrderId = app(OrderCancellationCreditNoteService::class)
            ->resolveCreditNotesByOrderIds($orderIds ?: []);

        $successfulStatuses = ['completed', 'paid', 'success', 'active'];

        // =============================================
        // BUILD DATA ARRAY
        // =============================================
        $data = [];
        foreach ($rows as $order) {
            $status = strtolower((string) ($order->status ?? 'pending'));

            // Status badge
            $StatusLabel = match ($status) {
                'completed' => 'Active',
                'upgraded' => 'Upgraded',
                'rejected' => 'Cancelled',
                'processing' => 'Processing',
                default => ucwords(str_replace('_', ' ', $status)),
            };

            if ($status === 'upgraded') {
                $StatusLabel = '<span class="badge bg-secondary">' . e($StatusLabel) . '</span>';
            } elseif (in_array($status, $successfulStatuses, true)) {
                $StatusLabel = '<span class="badge bg-success">' . e($StatusLabel) . '</span>';
            } elseif (in_array($status, ['failed', 'cancelled', 'rejected'], true)) {
                $StatusLabel = '<span class="badge bg-danger">' . e($StatusLabel) . '</span>';
            } elseif ($status === 'processing') {
                $StatusLabel = '<span class="badge bg-info">' . e($StatusLabel) . '</span>';
            } else {
                $StatusLabel = '<span class="badge bg-warning text-dark">' . e($StatusLabel) . '</span>';
            }

            // Transaction status badge
            $txtstatus = strtolower(trim((string) ($order->order_category ?? 'new'))) ?: 'new';
            if ($txtstatus === 'renewed') {
                $txtstatus = 'renewal';
            } elseif ($txtstatus === 'upgraded') {
                $txtstatus = 'upgrade';
            }
            $txtStatusLabel = ucfirst($txtstatus);
            switch ($txtstatus) {
                case 'existing':
                    $categoryBadge = 'bg-info text-white';
                    break;
                case 'renewal':
                    $categoryBadge = 'bg-info text-white';
                    break;
                case 'upgrade':
                    $categoryBadge = 'bg-primary';
                    break;
                case 'downgrade':
                    $categoryBadge = 'bg-secondary';
                    break;
                default:
                    $categoryBadge = 'bg-light text-dark border';
                    break;
            }
            $txtStatusLabel = '<span class="badge ' . $categoryBadge . '">' . e($txtStatusLabel) . '</span>';

            $planRow = $plansById->get((int) ($order->plan_id ?? 0));
            $resolvedPlanName = '-';
            if ($planRow) {
                $resolvedPlanName = trim((string) ($planRow->name ?: $planRow->title ?: '')) ?: '-';
            }

            $partnerDisplayName = '-';
            if (strtolower((string) ($order->order_type ?? 'b2c')) === 'b2b' && (int) ($order->partner_id ?? 0) > 0) {
                $partnerRow = $partnersById->get((int) $order->partner_id);
                if ($partnerRow) {
                    $partnerDisplayName = trim((string) ($partnerRow->name ?: $partnerRow->company_name ?: '-')) ?: '-';
                }
            }

            $oid = (int) $order->id;

            // =============================================
            // ACTION BUTTONS - FIXED
            // =============================================
            $action = '<div class="action-btn-group d-flex align-items-center justify-content-center gap-1 flex-wrap">';

            // VIEW BUTTON
            $action .= '<a href="' . e(route('superadmin.orders.details', $oid)) . '" 
            class="btn btn-outline-dark btn-icon btn-sm rounded-circle" 
            data-bs-toggle="tooltip" 
            title="View">
            <i class="ti ti-eye"></i>
        </a>';

            // EDIT BUTTON
            $action .= '<a href="' . e(route('superadmin.orders.edit', $oid)) . '" 
            class="btn btn-outline-dark btn-icon btn-sm rounded-circle" 
            data-bs-toggle="tooltip" 
            title="Edit">
            <i class="ti ti-edit"></i>
        </a>';

            // Active/Cancel actions live on Order Details (not listing).

            $creditNote = $creditNotesByOrderId[$oid] ?? null;
            if (!empty($creditNote['credit_note_id']) || !empty($creditNote['credit_note_no'])) {
                $cnHref = !empty($creditNote['credit_note_link'])
                    ? (string) $creditNote['credit_note_link']
                    : route('superadmin.orders.details', $oid);
                $cnTarget = !empty($creditNote['credit_note_link']) ? ' target="_blank"' : '';
                $action .= '<a href="' . e($cnHref) . '"' . $cnTarget . '
                class="btn btn-outline-primary btn-icon btn-sm rounded-circle" 
                data-bs-toggle="tooltip" 
                title="Credit Note ' . e((string) ($creditNote['credit_note_no'] ?? '')) . '">
                <i class="ti ti-file-invoice"></i>
            </a>';
            }

            $action .= '</div>';

            $dateStr = '-';
            if (!empty($order->created_at)) {
                $dateStr = Carbon::parse($order->created_at)->format('d M Y');
            }

            $creditNoteLabel = '-';
            if ($creditNote) {
                $cnNo = e((string) ($creditNote['credit_note_no'] ?? 'CN'));
                $cnAmt = '₹' . number_format((float) ($creditNote['credit_note_amount'] ?? 0), 2);
                if (!empty($creditNote['credit_note_link'])) {
                    $creditNoteLabel = '<a href="' . e((string) $creditNote['credit_note_link']) . '" target="_blank">'
                        . $cnNo . '</a><div class="text-muted small">' . $cnAmt . '</div>';
                } else {
                    $creditNoteLabel = $cnNo . '<div class="text-muted small">' . $cnAmt . '</div>';
                }
            }

            $data[] = [
                'id' => $order->order_no,
                'DT_RowAttr' => [
                    'data-view-url' => route('superadmin.orders.details', $oid),
                    'class' => 'orders-row-clickable',
                ],
                'customer_name' => e((string) ($order->customer_name ?? '-')),
                'domain_name' => e((string) ($order->domain_name ?? '-')),
                'partner_name' => e($partnerDisplayName),
                'plan_name' => e($resolvedPlanName),
                'order_type' => strtoupper((string) ($order->order_type ?? 'b2c')),
                'po_mode' => $order->po_mode,
                'po_journey_status' => e(ucwords(str_replace('_', ' ', (string) ($order->po_journey_status ?? '-')))),
                'amount' => '₹' . number_format((float) ($order->amount ?? 0), 2),
                'credit_used' => '₹' . number_format((float) ($order->credit_used ?? 0), 2),
                'credit_note' => $creditNoteLabel,
                'credit_note_no' => $creditNote['credit_note_no'] ?? null,
                'credit_note_link' => $creditNote['credit_note_link'] ?? null,
                'status' => $StatusLabel,
                'transaction_status' => $txtStatusLabel,
                'created_at' => $dateStr,
                'action' => $action,
                'view_url' => route('superadmin.orders.details', $oid),
            ];
        }

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $totalData,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function create()
    {
        return redirect()->route('superadmin.orders.create.step', ['step' => 1]);
    }

    public function createStep(int $step = 1, ?int $id = null)
    {
        $step = max(1, min(3, (int) $step));
        session()->forget('_old_input');

        $order = null;
        if ($id !== null) {
            $orderRow = $this->orders->newQuery()->whereKey((int) $id)->first();
            abort_if(!$orderRow, 404);

            $customer = $this->customers->newQuery()->whereKey((int) $orderRow->user_id)->first();
            $poRow = $this->orderPoDetails->newQuery()->where('order_id', (int) $id)->first();
            $planForDisplay = $this->plans->newQuery()->whereKey((int) $orderRow->plan_id)->first();
            $partnerForDisplay = $this->partners->newQuery()->whereKey((int) $orderRow->partner_id)->first();

            $orderTypeValue = strtolower((string) ($orderRow->order_type ?? 'b2c'));
            $customerName = '-';
            $customerMobile = '-';
            $customerEmail = '-';
            $customerCompanyName = '-';
            $customerGstin = '';
            if ($orderTypeValue === 'b2b' && $customer) {
                $customerName = trim((string) ($customer->name ?: $customer->company_name ?: '-'));
                $customerMobile = trim((string) ($customer->mobile ?? '-'));
                $customerEmail = trim((string) ($customer->email ?? '-'));
                $customerCompanyName = trim((string) ($customer->company_name ?? $customer->name ?? '-'));
                $customerGstin = trim((string) ($customer->gstin ?? ''));
            }

            $planDisplayName = '-';
            if ($planForDisplay) {
                $planDisplayName = trim((string) ($planForDisplay->name ?: $planForDisplay->title ?: '-'));
            }

            $order = (object) array_merge($orderRow->toArray(), [
                'customer_name' => $customerName,
                'customer_mobile' => $customerMobile,
                'customer_email' => $customerEmail,
                'customer_company_name' => $customerCompanyName,
                'customer_gstin' => $customerGstin,
                'partner_name' => trim((string) ($partnerForDisplay->name ?? '-')),
                'resolved_plan_name' => $planDisplayName,
                'resolved_amount' => (float) ($orderRow->total_amount ?? 0),
                'resolved_credit_used' => (float) ($orderRow->credit_used ?? 0),
                'resolved_order_type' => (string) ($orderRow->order_type ?? 'b2c'),
                'resolved_status' => (string) ($orderRow->status ?? 'pending'),
                'po_mode' => (string) ($poRow->po_mode ?? '-'),
                'po_journey_status' => (string) ($poRow->journey_status ?? '-'),
                'po_number' => $poRow->po_number ?? null,
                'po_date' => $poRow->po_date ?? null,
                'po_amount' => $poRow->po_amount ?? null,
                'po_document_url' => $poRow->po_document_url ?? null,
                'po_manual_name' => $poRow->manual_name ?? null,
                'po_manual_email' => $poRow->manual_email ?? null,
                'po_manual_mobile' => $poRow->manual_mobile ?? null,
                'po_manual_gstin' => $poRow->manual_gstin ?? null,
                'po_manual_company_name' => $poRow->manual_company_name ?? null,
                'po_manual_company_address' => $poRow->manual_company_address ?? null,
                'po_manual_city' => $poRow->manual_city ?? null,
                'po_manual_state' => $poRow->manual_state ?? null,
                'po_manual_country' => $poRow->manual_country ?? null,
                'po_manual_pincode' => $poRow->manual_pincode ?? null,
                'aadhar_verified' => $poRow->aadhar_verified ?? null,
                'aadhar_verified_at' => $poRow->aadhar_verified_at ?? null,
                'aadhaar_ref_no' => $poRow->aadhaar_ref_no ?? null,
                'aadhaar_masked' => $poRow->aadhaar_masked ?? null,
            ]);
        }


        $providers = $this->providers->newQuery()->where('status', 'active')->orderBy('name')->get();
        $hasVariantByProvider = [];
        $variantsByProvider = [];
        $mainVariantsByProvider = [];
        $subVariantsByMain = [];
        foreach ($providers as $providerRow) {
            $providerId = (int) $providerRow->id;
            $hasVariantByProvider[$providerId] = (($providerRow->has_variant ?? 'no') === 'yes');
        }
        if ($providers->isNotEmpty()) {
            $providerIds = $providers->pluck('id')->map(static fn($providerId) => (int) $providerId)->all();
            $variantRows = $this->variants->newQuery()
                ->whereIn('provider_id', $providerIds)
                ->orderBy('name')
                ->get(['id', 'provider_id', 'name', 'parent_variant_id']);
            foreach ($variantRows as $variantRow) {
                $providerId = (int) $variantRow->provider_id;
                $parentId = (int) ($variantRow->parent_variant_id ?? 0);
                $variantsByProvider[$providerId][] = [
                    'id' => (int) $variantRow->id,
                    'name' => (string) $variantRow->name,
                    'parent_variant_id' => $parentId > 0 ? $parentId : null,
                ];
                if ($parentId > 0) {
                    $subVariantsByMain[$parentId][] = [
                        'id' => (int) $variantRow->id,
                        'name' => (string) $variantRow->name,
                    ];
                } else {
                    $mainVariantsByProvider[$providerId][] = [
                        'id' => (int) $variantRow->id,
                        'name' => (string) $variantRow->name,
                    ];
                }
            }
        }

        $selectedProviderId = null;
        $selectedVariantId = 0;
        $selectedMainVariantId = 0;
        $selectedSubVariantId = 0;
        $plans = collect();

        if ($order && (int) ($order->plan_id ?? 0) > 0) {
            $planRow = $this->plans->newQuery()->whereKey((int) $order->plan_id)->first();
            if ($planRow && (int) ($planRow->provider_id ?? 0) > 0) {
                $selectedProviderId = (int) $planRow->provider_id;
                $selectedVariantId = (int) ($planRow->variant_id ?? 0);
                if ($selectedVariantId > 0) {
                    $variantRow = $this->variants->newQuery()
                        ->whereKey($selectedVariantId)
                        ->first(['id', 'parent_variant_id']);
                    if ($variantRow) {
                        if ((int) ($variantRow->parent_variant_id ?? 0) > 0) {
                            $selectedMainVariantId = (int) $variantRow->parent_variant_id;
                            $selectedSubVariantId = (int) $variantRow->id;
                        } else {
                            $selectedMainVariantId = (int) $variantRow->id;
                        }
                    }
                }

                $providerRow = $this->providers->newQuery()->whereKey($selectedProviderId)->first();
                $planQuery = $this->plans->newQuery()->where('provider_id', $selectedProviderId);
                if ($providerRow && (($providerRow->has_variant ?? 'no') === 'yes') && $selectedVariantId > 0) {
                    $planQuery->where('variant_id', $selectedVariantId);
                }
                $plans = $planQuery
                    ->select(
                        'id',
                        DB::raw('COALESCE(name, title) as name'),
                        DB::raw($this->planB2bPriceSql() . ' as b2b_price'),
                        DB::raw('COALESCE(actual_price, price, 0) as b2c_price'),
                        DB::raw('COALESCE(customer_limit, 0) as customer_limit')
                    )
                    ->orderByDesc('id')
                    ->limit(500)
                    ->get();
            }
        }

        if ($plans->isEmpty()) {
            $plans = $this->plans->newQuery()
                ->select(
                    'id',
                    DB::raw('COALESCE(name, title) as name'),
                    DB::raw($this->planB2bPriceSql() . ' as b2b_price'),
                    DB::raw('COALESCE(actual_price, price, 0) as b2c_price'),
                    DB::raw('COALESCE(customer_limit, 0) as customer_limit')
                )
                ->orderByDesc('id')
                ->limit(500)
                ->get();
        }

        $skipProviderStep = $providers->isEmpty() || ($order !== null && $selectedProviderId === null);
        $orderCompleted = false;
        $showPoTab = false;
        $showAadhaarTab = false;
        $successfulStatuses = ['completed', 'paid', 'success', 'active'];

        if ($order) {
            $journey = strtolower((string) ($order->po_journey_status ?? ''));
            $status = strtolower((string) ($order->resolved_status ?? ''));
            $orderCompleted = $journey === 'placed' || in_array($status, $successfulStatuses, true);
            $orderType = strtolower((string) ($order->resolved_order_type ?? $order->order_type ?? 'b2c'));
            if ($orderType === 'b2b' && (int) ($order->user_id ?? 0) > 0) {
                $showPoTab = true;
                $showAadhaarTab = true;
            }
            if (!$showPoTab && $showAadhaarTab && $step === 2) {
                $step = 3;
            }
        }

        $orderSummaryPricing = null;
        if ($order) {
            $qty = max(1, (int) ($order->quantity ?? 1));
            $grossSubtotal = round((float) ($order->subtotal ?? 0), 2);
            if ($grossSubtotal <= 0) {
                $grossSubtotal = round((float) ($order->price ?? 0) * $qty, 2);
            }
            $discountAmount = round((float) ($order->discount_amount ?? 0), 2);
            $applyGst = (int) ($order->apply_gst ?? 1) === 1;
            $gstRate = GstSplit::RATE;
            $gstAmount = round((float) ($order->gst_amount ?? $order->tax_amount ?? 0), 2);
            if ($gstAmount <= 0 && $applyGst && $grossSubtotal > 0) {
                $gstAmount = round(($grossSubtotal - $discountAmount) * $gstRate / 100, 2);
            }
            $grandTotal = round((float) ($order->total_amount ?? $order->resolved_amount ?? 0), 2);
            if ($grandTotal <= 0) {
                $grandTotal = round($grossSubtotal - $discountAmount + $gstAmount, 2);
            }
            $customerGstin = trim((string) ($order->po_manual_gstin ?? $order->customer_gstin ?? ''));
            $split = GstSplit::split($gstAmount, $customerGstin);
            $orderSummaryPricing = [
                'subtotal' => $grossSubtotal,
                'discount_amount' => $discountAmount,
                'taxable_amount' => round(max(0, $grossSubtotal - $discountAmount), 2),
                'gst_amount' => $gstAmount,
                'grand_total' => $grandTotal,
                'apply_gst' => $applyGst,
                'gst_rate' => $gstRate,
                'tax_type' => $split['tax_type'],
                'cgst' => $split['cgst'],
                'sgst' => $split['sgst'],
                'igst' => $split['igst'],
            ];
        }

        $orderFormCustomers = $this->getOrderFormCustomers();

        $partners = $this->partners->newQuery()
            ->select('id', 'name', 'wallet_balance', 'status')
            ->where('status', 'approved')
            ->orderByDesc('id')
            ->get()
            ->map(static function ($partnerRow) {
                return (object) [
                    'id' => (int) $partnerRow->id,
                    'name' => (string) ($partnerRow->name ?: "Partner #{$partnerRow->id}"),
                    'wallet_balance' => (float) ($partnerRow->wallet_balance ?? 0),
                ];
            });


        $orderCanBeCancelled = true;
        $orderCancellationBlockReason = null;
        $orderCancellationDeadline = null;
        if ($order) {
            $policyContext = $this->buildOrderCancellationPolicyContext((int) $order->id, $selectedProviderId);
            $orderCanBeCancelled = $policyContext['can_cancel'];
            $orderCancellationBlockReason = $policyContext['block_reason'];
            $orderCancellationDeadline = $policyContext['deadline_label'];
        }

        return view('superadmin.orders.form', [
            'title' => $order ? 'Edit Order' : 'Add Order',
            'providers' => $providers,
            'hasVariantByProvider' => $hasVariantByProvider,
            'variantsByProvider' => $variantsByProvider,
            'mainVariantsByProvider' => $mainVariantsByProvider,
            'subVariantsByMain' => $subVariantsByMain,
            'orderSuccessfulStatuses' => $successfulStatuses,
            'order' => $order,
            'plans' => $plans,
            'skipProviderStep' => $skipProviderStep,
            'orderFormCustomers' => $orderFormCustomers,
            'customersPartnerColumn' => true,
            'partners' => $partners,
            'statusOptions' => ['pending', 'completed', 'paid', 'success', 'failed', 'cancelled'],
            'typeOptions' => ['b2b', 'b2c'],
            'hasDomainColumn' => true,
            'hasQuantityColumn' => true,
            'hasPromoCodeColumn' => true,
            'hasDiscountAmountColumn' => true,
            'hasPoTrackingColumns' => true,
            'selectedProviderId' => $selectedProviderId,
            'selectedVariantId' => $selectedVariantId,
            'selectedMainVariantId' => $selectedMainVariantId,
            'selectedSubVariantId' => $selectedSubVariantId,
            'currentStep' => $step,
            'wizardOrderId' => $order ? (int) $order->id : null,
            'orderCompleted' => $orderCompleted,
            'showPoTab' => $showPoTab,
            'showAadhaarTab' => $showAadhaarTab,
            'orderSummaryPricing' => $orderSummaryPricing,
            'hasTransferDomainColumn' => true,
            'orderCanBeCancelled' => $orderCanBeCancelled,
            'orderCancellationBlockReason' => $orderCancellationBlockReason,
            'orderCancellationDeadline' => $orderCancellationDeadline,
        ]);
    }

    public function orderPlansByProvider(Request $request)
    {
        $providerId = (int) $request->query('provider_id', 0);
        $variantId = (int) $request->query('variant_id', 0);

        $plans = $this->getPlansForProviderVariant($providerId, $variantId);
        $payload = $plans->map(function ($row) {
            return [
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?? ''),
                'b2b_price' => $this->resolvePlanB2bPriceFromRow($row),
                'b2c_price' => (float) ($row->b2c_price ?? $row->actual_price ?? $row->price ?? 0),
                'customer_limit' => (int) ($row->customer_limit ?? 0),
            ];
        })->values()->all();

        return response()->json(['plans' => $payload]);
    }

    public function customerDomains(int $id)
    {
        $domains = $this->orders->newQuery()
            ->where('user_id', $id)
            ->whereNotNull('domain_name')
            ->whereRaw("TRIM(domain_name) <> ''")
            ->orderByDesc('id')
            ->pluck('domain_name')
            ->map(static function ($d) {
                return trim((string) $d);
            })
            ->filter(static function ($d) {
                return $d !== '';
            })
            ->unique()
            ->values()
            ->all();

        return response()->json(['domains' => $domains]);
    }

    public function customerOrderMeta(Request $request, int $id)
    {
        $q = $this->orders->newQuery()->where('user_id', $id);
        $orderColumns = $this->orderColumns();

        $orderType = strtolower(trim((string) $request->query('order_type', '')));
        if ($orderType !== '' && in_array('order_type', $orderColumns, true)) {
            $q->whereRaw('LOWER(order_type) = ?', [$orderType]);
        }

        $partnerId = (int) $request->query('partner_id', 0);
        if ($partnerId > 0 && in_array('partner_id', $orderColumns, true)) {
            $q->where('partner_id', $partnerId);
        }

        $total = (int) $q->count();
        $profile = [
            'name' => null,
            'email' => null,
            'mobile' => null,
            'gstin' => null,
            'company_name' => null,
            'company_address' => null,
            'city' => null,
            'state' => null,
            'country' => null,
            'pincode' => null,
        ];
        $isB2b = $orderType === 'b2b';
        if ($isB2b) {
            $customerCols = $this->customerColumns();
            $row = $this->customers->newQuery()->where('id', $id)->first();
            if ($row) {
                $profile['name'] = in_array('name', $customerCols, true) ? ($row->name ?? null) : null;
                $profile['email'] = in_array('email', $customerCols, true) ? ($row->email ?? null) : null;
                $profile['mobile'] = in_array('mobile', $customerCols, true) ? ($row->mobile ?? null) : null;
                $profile['gstin'] = in_array('gstin', $customerCols, true) ? ($row->gstin ?? null) : null;
                $profile['company_name'] = in_array('company_name', $customerCols, true) ? ($row->company_name ?? null) : null;
                if (in_array('company_address', $customerCols, true)) {
                    $profile['company_address'] = $row->company_address ?? null;
                } elseif (in_array('address', $customerCols, true)) {
                    $profile['company_address'] = $row->address ?? null;
                }
                $profile['city'] = in_array('city', $customerCols, true) ? ($row->city ?? null) : null;
                $profile['state'] = in_array('state', $customerCols, true) ? ($row->state ?? null) : null;
                $profile['country'] = in_array('country', $customerCols, true) ? ($row->country ?? null) : null;
                if (in_array('zip_code', $customerCols, true)) {
                    $profile['pincode'] = $row->zip_code ?? null;
                }
            }
        } elseif (true) {
            $userCols = $this->userColumns();
            $row = $this->users->newQuery()->where('id', $id)->first();
            if ($row) {
                $profile['name'] = in_array('name', $userCols, true) ? ($row->name ?? null) : null;
                $profile['email'] = in_array('email', $userCols, true) ? ($row->email ?? null) : null;
                $profile['mobile'] = in_array('mobile', $userCols, true) ? ($row->mobile ?? null) : null;
                $profile['gstin'] = in_array('gstin', $userCols, true) ? ($row->gstin ?? null) : null;
                $profile['company_name'] = in_array('company_name', $userCols, true) ? ($row->company_name ?? null) : null;
                if (in_array('company_address', $userCols, true)) {
                    $profile['company_address'] = $row->company_address ?? null;
                } elseif (in_array('address', $userCols, true)) {
                    $profile['company_address'] = $row->address ?? null;
                }
                $profile['city'] = in_array('city', $userCols, true) ? ($row->city ?? null) : null;
                $profile['state'] = in_array('state', $userCols, true) ? ($row->state ?? null) : null;
                $profile['country'] = in_array('country', $userCols, true) ? ($row->country ?? null) : null;
                if (in_array('zip_code', $userCols, true)) {
                    $profile['pincode'] = $row->zip_code ?? null;
                }
            }
        }
        return response()->json([
            'total_orders' => $total,
            'is_first_order' => $total === 0,
            'profile' => $profile,
        ]);
    }

    public function checkDomainAvailability(Request $request)
    {
        $providerId = OrderDomainHelper::resolveProviderIdFromRequest($request);
        $domain = trim((string) $request->query('domain_name', ''));
        $transferYes = strtolower(trim((string) $request->query(
            'transfer_domain',
            $request->input('transfer_domain', '')
        ))) === 'yes';

        // Domain already on another provider's plan → reject for this provider.
        if ($providerId > 0 && $domain !== '') {
            $segments = OrderDomainHelper::parseSegments($domain, $providerId === 2 ? 2 : $providerId);
            if ($segments === []) {
                $segments = [strtolower(trim($domain))];
            }
            foreach ($segments as $segment) {
                if (OrderDomainHelper::isDomainUsedByOtherProvider($segment, $providerId)) {
                    return response()->json([
                        'success' => false,
                        'available' => false,
                        'message' => OrderDomainHelper::otherProviderConflictMessage(),
                        'used_provider_ids' => OrderDomainHelper::existingProviderIdsForDomain($segment),
                        'used_plans' => OrderDomainHelper::existingPlansForDomain($segment),
                        'suggested_domains' => [],
                    ], 422);
                }
                // Live / processing / pending / completed orders also block the domain.
                if (OrderDomainHelper::isDomainInUseOnActiveOrder($segment)) {
                    return response()->json([
                        'success' => false,
                        'available' => false,
                        'in_use' => true,
                        'message' => OrderDomainHelper::domainInUseMessage(),
                        'used_provider_ids' => OrderDomainHelper::existingProviderIdsForDomain($segment),
                        'used_plans' => OrderDomainHelper::existingPlansForDomain($segment),
                        'suggested_domains' => [],
                    ], 422);
                }
            }
        }

        // Superadmin only: provider 3 — no registrar verification.
        if ($providerId === 3) {
            return response()->json([
                'success' => true,
                'available' => true,
                'message' => $domain !== '' ? 'Domain accepted.' : 'Enter domain name.',
                'suggested_domains' => [],
            ]);
        }

        $microsoftMulti = $providerId === 2 && $transferYes;
        $microsoftSuggest = $providerId === 2 && !$transferYes;
        $parseProviderId = $microsoftMulti ? 2 : ($microsoftSuggest ? 2 : $providerId);

        if ($microsoftSuggest) {
            $keyword = strtolower(trim((string) $request->query('keyword', $domain)));
            $keyword = preg_replace('/\.onmicrosoft\.com$/i', '', $keyword) ?? $keyword;
            $keyword = preg_replace('/[^a-z0-9-]/', '', $keyword) ?? '';
            $suggested = [];
            if ($keyword !== '') {
                foreach (['', '1', '2'] as $suffix) {
                    $suggested[] = $keyword . $suffix . '.onmicrosoft.com';
                }
            }

            $domainLower = strtolower($domain);
            $isOnMicrosoft = $domainLower !== '' && (bool) preg_match('/\.onmicrosoft\.com$/i', $domainLower);
            $typedLooksComplete = str_contains($domainLower, '.');

            // Custom Microsoft domain (not *.onmicrosoft.com) — verify it is already registered.
            if ($typedLooksComplete && !$isOnMicrosoft) {
                $validationCustom = OrderDomainHelper::validateInput($domain, $parseProviderId);
                if (!$validationCustom['ok']) {
                    return response()->json([
                        'success' => false,
                        'available' => false,
                        'registered' => false,
                        'message' => $validationCustom['message'],
                        'suggested_domains' => $suggested,
                    ], 422);
                }

                try {
                    foreach ($validationCustom['segments'] as $segment) {
                        if ($this->isDomainAvailableForRegistration($segment)) {
                            return response()->json([
                                'success' => true,
                                'available' => false,
                                'registered' => false,
                                'message' => 'This domain is not registered yet. Please purchase/register the domain first before adding a plan.',
                                'suggested_domains' => $suggested,
                            ]);
                        }
                    }

                    return response()->json([
                        'success' => true,
                        'available' => true,
                        'registered' => true,
                        'message' => 'Domain is registered. You can add the plan.',
                        'suggested_domains' => $suggested,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('Microsoft custom domain registration check failed', [
                        'domain' => $domain,
                        'error' => $e->getMessage(),
                    ]);
                    $open = $this->isDomainOpenByDns($validationCustom['segments'][0] ?? $domain);

                    return response()->json([
                        'success' => true,
                        'available' => !$open,
                        'registered' => !$open,
                        'message' => $open
                            ? 'This domain is not registered yet. Please purchase/register the domain first before adding a plan.'
                            : 'Domain appears registered (verified via DNS). You can add the plan.',
                        'suggested_domains' => $suggested,
                    ]);
                }
            }

            // Keyword alone is not a full FQDN — only validate when a full domain was typed.
            if ($typedLooksComplete) {
                $validation = OrderDomainHelper::validateInput($domain, $parseProviderId);
                if (!$validation['ok'] && $suggested === []) {
                    return response()->json([
                        'success' => false,
                        'available' => false,
                        'message' => $validation['message'],
                        'suggested_domains' => [],
                    ], 422);
                }
            }

            if ($keyword === '' && $domain === '') {
                return response()->json([
                    'success' => false,
                    'available' => false,
                    'message' => 'Enter a domain keyword to see suggestions.',
                    'suggested_domains' => [],
                ], 422);
            }

            return response()->json([
                'success' => true,
                'available' => true,
                'registered' => true,
                'message' => $suggested !== []
                    ? 'Select a suggested Microsoft domain.'
                    : 'Domain format valid.',
                'suggested_domains' => $suggested,
            ]);
        }

        $validation = OrderDomainHelper::validateInput($domain, $parseProviderId);

        if ($microsoftMulti) {
            if (!$validation['ok']) {
                return response()->json([
                    'success' => false,
                    'available' => false,
                    'message' => $validation['message'],
                ], 422);
            }

            // Custom Microsoft domains (Transfer = Yes) must already be registered.
            try {
                foreach ($validation['segments'] as $segment) {
                    if (preg_match('/\.onmicrosoft\.com$/i', $segment)) {
                        continue;
                    }
                    if ($this->isDomainAvailableForRegistration($segment)) {
                        return response()->json([
                            'success' => true,
                            'available' => false,
                            'registered' => false,
                            'message' => 'This domain is not registered yet. Please purchase/register the domain first before adding a plan.',
                            'suggested_domains' => [],
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Microsoft multi-domain registration check failed', [
                    'domain' => $domain,
                    'error' => $e->getMessage(),
                ]);
                $segment = $validation['segments'][0] ?? $domain;
                if (!preg_match('/\.onmicrosoft\.com$/i', $segment) && $this->isDomainOpenByDns($segment)) {
                    return response()->json([
                        'success' => true,
                        'available' => false,
                        'registered' => false,
                        'message' => 'This domain is not registered yet. Please purchase/register the domain first before adding a plan.',
                        'suggested_domains' => [],
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'available' => true,
                'registered' => true,
                'message' => 'Domain is registered. You can add the plan.',
                'suggested_domains' => [],
            ]);
        }

        // Note: microsoftSuggest handled above; this block keeps original multi path if reordered.
        if (!$validation['ok']) {
            return response()->json([
                'success' => false,
                'available' => false,
                'message' => $validation['message'],
            ], 422);
        }

        if (count($validation['segments']) > 1) {
            return response()->json([
                'success' => false,
                'available' => false,
                'message' => 'Please enter only one domain name.',
            ], 422);
        }

        try {
            foreach ($validation['segments'] as $segment) {
                $openForRegistration = $this->isDomainAvailableForRegistration($segment);
                if ($openForRegistration) {
                    return response()->json([
                        'success' => true,
                        'available' => false,
                        'registered' => false,
                        'message' => 'This domain is not registered yet. Please purchase/register the domain first before adding a plan.',
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'available' => true,
                'registered' => true,
                'message' => 'Domain is registered. You can add the plan.',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Domain availability check unexpected error', [
                'domain' => $domain,
                'error'  => $e->getMessage(),
            ]);

            // Last resort: DNS-only decision so the wizard is not blocked by API outages.
            $segment = $validation['segments'][0] ?? $domain;
            $open = $this->isDomainOpenByDns($segment);

            return response()->json([
                'success' => true,
                'available' => !$open,
                'registered' => !$open,
                'message' => $open
                    ? 'This domain is not registered yet. Please purchase/register the domain first before adding a plan.'
                    : 'Domain appears registered (verified via DNS). You can add the plan.',
            ]);
        }
    }

    public function aadharVerifyInit(Request $request)
    {
        if ($this->shouldUsePartnerRenewalAadhaarFlow($request)) {
            return app(PartnerOrderController::class)->aadhaarInit($request);
        }

        $request->validate([
            'order_id' => ['required', 'integer'],
            'aadhaar_number' => ['required', 'digits:12'],
        ]);
        $orderId = (int) $request->input('order_id');
        $exists = $this->orders->newQuery()->where('id', $orderId)->exists();
        if (!$exists) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $aadhaar = preg_replace('/\D/', '', (string) $request->input('aadhaar_number')) ?? '';
        if (strlen($aadhaar) !== 12) {
            return response()->json(['success' => false, 'message' => 'Aadhaar number must be 12 digits.'], 422);
        }

        Cache::put('aadhaar_number_' . $orderId, $aadhaar, now()->addMinutes(30));

        $init = app(\App\Support\AadhaarVerificationGateway::class)->init($aadhaar, 'order:' . $orderId);
        $masked = (string) ($init['masked'] ?? ('XXXXXXXX' . substr($aadhaar, -4)));
        $refNo = (string) ($init['ref_no'] ?? '');

        // Provider / cooldown error → stop process, do not update order or PO journey
        if (empty($init['ok']) || $refNo === '') {
            return response()->json([
                'success' => false,
                'message' => (string) ($init['message'] ?? 'Failed to send OTP. Please wait and try again.'),
            ], 422);
        }

        Cache::put('aadhaar_ref_' . $orderId, $refNo, now()->addMinutes(30));

        $cols = $this->orderPoDetailColumns();
        $payload = [];
        if (in_array('journey_status', $cols, true)) $payload['journey_status'] = 'po_done';
        if (in_array('aadhaar_masked', $cols, true)) $payload['aadhaar_masked'] = $masked;
        if (in_array('aadhaar_ref_no', $cols, true)) $payload['aadhaar_ref_no'] = $refNo;
        if (in_array('aadhar_verified', $cols, true)) $payload['aadhar_verified'] = 0;
        if (in_array('updated_at', $cols, true)) $payload['updated_at'] = now();

        $row = $this->orderPoDetails->newQuery()->where('order_id', $orderId)->first();
        if ($row) {
            $this->orderPoDetails->newQuery()->where('order_id', $orderId)->update($payload);
        } else {
            $insert = array_merge(['order_id' => $orderId], $payload);
            if (in_array('created_at', $cols, true)) $insert['created_at'] = now();
            $this->orderPoDetails->newQuery()->insert($insert);
        }

        if (true) {
            $this->orders->newQuery()->where('id', $orderId)->update([
                'status' => 'pending',
                'updated_at' => now(),
            ]);
        }

        $payload = [
            'aadhaar_masked' => $masked,
            'aadhaar_ref_no' => $refNo,
            'mode' => (string) ($init['mode'] ?? ''),
        ];
        if (($init['mode'] ?? '') === 'test') {
            $payload['test_otp'] = (string) (config('services.aadhaar.mock_otps')[0] ?? '123456');
        }

        return response()->json([
            'success' => true,
            'message' => (string) ($init['message'] ?? 'OTP sent successfully.'),
            'data' => $payload,
        ]);
    }

    public function aadharVerifyOtp(Request $request)
    {
        if ($this->shouldUsePartnerRenewalAadhaarFlow($request)) {
            return app(PartnerOrderController::class)->aadhaarOtpVerify($request);
        }

        $gateway = app(\App\Support\AadhaarVerificationGateway::class);
        $isTestMode = $gateway->isTestMode();
        $rules = [
            'order_id' => ['required', 'integer'],
            'otp' => $isTestMode ? ['nullable', 'string', 'max:10'] : ['required', 'string', 'max:10'],
            'aadhaar_number' => ['nullable', 'digits:12'],
        ];
        $request->validate($rules);
        $orderId = (int) $request->input('order_id');
        $orderRow = $this->orders->newQuery()->where('id', $orderId)->first();
        if (!$orderRow) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $aadhaarNumber = preg_replace('/\D/', '', (string) $request->input('aadhaar_number')) ?? '';
        if (strlen($aadhaarNumber) !== 12) {
            $aadhaarNumber = preg_replace('/\D/', '', (string) Cache::get('aadhaar_number_' . $orderId, '')) ?? '';
        }
        if (strlen($aadhaarNumber) !== 12) {
            return response()->json(['success' => false, 'message' => 'Aadhaar number must be 12 digits. Send OTP first.'], 422);
        }

        $otp = preg_replace('/\D/', '', (string) $request->input('otp', '')) ?? '';
        if ($otp === '' && $isTestMode) {
            $otp = preg_replace('/\D/', '', (string) (config('services.aadhaar.mock_otps')[0] ?? '123456')) ?? '';
        }
        if (strlen($otp) !== 6) {
            return response()->json(['success' => false, 'message' => 'Please enter a valid 6-digit OTP.'], 422);
        }

        $poRow = $this->orderPoDetails->newQuery()->where('order_id', $orderId)->first();
        $refNo = (string) Cache::get('aadhaar_ref_' . $orderId, '');
        if ($refNo === '' && $poRow) {
            $refNo = (string) ($poRow->aadhaar_ref_no ?? '');
        }

        $verify = $gateway->verifyOtp($aadhaarNumber, $otp, 'order:' . $orderId, $refNo);
        if (empty($verify['ok'])) {
            return response()->json([
                'success' => false,
                'message' => (string) ($verify['message'] ?? 'OTP verification failed.'),
            ], 422);
        }

        $refNo = (string) (Cache::get('aadhaar_ref_' . $orderId) ?? $refNo);
        $masked = 'XXXXXXXX' . substr($aadhaarNumber, -4);

        $partnerId = (int) ($orderRow->partner_id ?? 0);
        if ($partnerId > 0 && strtolower(trim((string) ($orderRow->order_type ?? ''))) === 'b2b') {
            $orderAmount = (float) ($orderRow->total_amount ?? 0);
            if ($orderAmount <= 0) {
                $orderAmount = (float) ($orderRow->subtotal ?? 0) + (float) ($orderRow->gst_amount ?? $orderRow->tax_amount ?? 0);
            }
            $creditCheck = PartnerCreditService::validateB2bPurchaseAmount($partnerId, $orderAmount, false, $orderId);
            if (empty($creditCheck['ok'])) {
                return response()->json([
                    'success' => false,
                    'message' => (string) ($creditCheck['message'] ?? PartnerCreditService::CREDIT_LIMIT_REACHED_MESSAGE),
                    'code' => (string) ($creditCheck['code'] ?? 'credit_limit_exceeded'),
                ], 422);
            }
        }

        $skipCreditChecks = $request->boolean('skip_credit_check');
        try {
            $this->completeOrderAfterAadhaarVerification($orderId, $refNo, $masked, null, $skipCreditChecks);
        } catch (ValidationException $e) {
            if ($skipCreditChecks) {
                Log::warning('Aadhaar verify: wallet sync skipped after credit check bypass.', [
                    'order_id' => $orderId,
                    'error' => collect($e->errors())->flatten()->first(),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => collect($e->errors())->flatten()->first() ?: 'Credit deduction failed.',
                    'errors' => $e->errors(),
                ], 422);
            }
        }

        $creditUsed = round((float) $this->orders->newQuery()->where('id', $orderId)->value('credit_used'), 2);

        // Ensure `order_items` has a row for this order. Cart-flow and home-flow
        // (OrderController::saveOrder/updateOrder) already write here, but older
        // orders / direct admin flows may not — seed from `orders.*` as fallback.
        if (true) {
            $existingItemId = (int) $this->orderItems->newQuery()->where('order_id', $orderId)->value('id');
            if ($existingItemId <= 0) {
                $orderRow = $this->orders->newQuery()->where('id', $orderId)->first();
                if ($orderRow) {
                    $itemCols = $this->orderItemColumns();
                    $unitPrice = round((float) ($orderRow->price ?? 0), 2);
                    $qty = max(1, (int) ($orderRow->quantity ?? 1));
                    $lineTotal = round(
                        (float) ($orderRow->subtotal ?? $orderRow->total_amount ?? $unitPrice * $qty),
                        2
                    );

                    $itemRow = ['order_id' => $orderId];
                    if (
                        isset($orderRow->plan_id) && (int) $orderRow->plan_id > 0
                        && in_array('plan_id', $itemCols, true)
                    ) {
                        $itemRow['plan_id'] = (int) $orderRow->plan_id;
                    }
                    $planName = (string) ($orderRow->plan_name ?? 'Plan');
                    if (in_array('item_name', $itemCols, true)) {
                        $itemRow['item_name'] = $planName;
                    } elseif (in_array('name', $itemCols, true)) {
                        $itemRow['name'] = $planName;
                    }
                    if (in_array('quantity', $itemCols, true)) {
                        $itemRow['quantity'] = $qty;
                    }
                    if (in_array('unit_price', $itemCols, true)) {
                        $itemRow['unit_price'] = $unitPrice;
                    } elseif (in_array('price', $itemCols, true)) {
                        $itemRow['price'] = $unitPrice;
                    }
                    if (in_array('total_amount', $itemCols, true)) {
                        $itemRow['total_amount'] = $lineTotal;
                    } elseif (in_array('amount', $itemCols, true)) {
                        $itemRow['amount'] = $lineTotal;
                    }
                    if (in_array('created_at', $itemCols, true)) {
                        $itemRow['created_at'] = now();
                    }
                    if (in_array('updated_at', $itemCols, true)) {
                        $itemRow['updated_at'] = now();
                    }
                    if (in_array('status', $itemCols, true)) {
                        $itemRow['status'] = 'active';
                    }
                    $this->orderItems->newQuery()->insert($itemRow);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => (string) ($verify['message'] ?? 'Aadhaar verified. Order placed successfully.'),
            'redirect_url' => route('superadmin.orders.details', ['id' => $orderId]),
            'data' => [
                'order_id' => $orderId,
                'status' => 'completed',
                'credit_used' => $creditUsed,
                'credits_used' => $creditUsed,
                'mode' => (string) ($verify['mode'] ?? ''),
            ],
        ]);
    }

    /**
     * Invoice grand total (subtotal + GST) used for wallet / credit_used on placement.
     */
    private function resolveOrderGrandTotal(object $order): float
    {
        $total = (float) ($order->total_amount ?? 0);
        if ($total > 0) {
            return round($total, 2);
        }

        $gst = (float) ($order->gst_amount ?? $order->tax_amount ?? 0);
        $subtotal = (float) ($order->subtotal ?? 0);
        if ($subtotal > 0 || $gst > 0) {
            return round($subtotal + $gst, 2);
        }

        $qty = max(1, (int) ($order->quantity ?? 1));

        return round((float) ($order->price ?? 0) * $qty, 2);
    }

    public function store(Request $request)
    {
        return $this->saveStep($request, 1, null);
    }

    private function nextOrderIdentifiers(): array
    {
        $connection = $this->orders->getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'pgsql') {
            $connection->statement('SELECT pg_advisory_xact_lock(?)', [758291034]);
            $nextSrNo = ((int) $this->orders->newQuery()->max('sr_no')) + 1;
            if ($nextSrNo <= 0) {
                $nextSrNo = ((int) $this->orders->newQuery()->count()) + 1;
            }
        } else {
            $nextSrNo = ((int) $this->orders->newQuery()->lockForUpdate()->max('sr_no')) + 1;
            if ($nextSrNo <= 0) {
                $nextSrNo = ((int) $this->orders->newQuery()->lockForUpdate()->count()) + 1;
            }
        }

        $orderPrefix = $this->readOrderPrefix();
        $orderNo = $orderPrefix . str_pad((string) $nextSrNo, 5, '0', STR_PAD_LEFT);
        while ($this->orders->newQuery()->where('order_no', $orderNo)->exists()) {
            $nextSrNo++;
            $orderNo = $orderPrefix . str_pad((string) $nextSrNo, 5, '0', STR_PAD_LEFT);
        }

        return [
            'order_no' => $orderNo,
            'sr_no' => $nextSrNo,
        ];
    }

    private function readOrderPrefix(): string
    {
        $prefix = (string) $this->adminSettings->newQuery()->where('id', 1)->value('order_prefix');
        if (trim($prefix) !== '') {
            return strtoupper(trim($prefix));
        }

        $prefix = (string) $this->adminSettings->newQuery()->orderByDesc('id')->value('order_prefix');
        if (trim($prefix) !== '') {
            return strtoupper(trim($prefix));
        }

        return 'TNX';
    }

    public function edit(int $id)
    {
        return redirect()->route('superadmin.orders.create.step', ['step' => 1, 'id' => $id]);
    }

    private function ensurePendingInvoicesRowForOrder(int $orderId): void
    {
        try {
            app(SandboxEInvoiceService::class)->ensureInvoiceRowForOrder($orderId);
        } catch (\Throwable $e) {
            Log::warning('Pending invoices row ensure failed (order save continues)', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Partner B2B order complete — ensure invoices row exists and status is pending.
     */
    public function markInvoiceStatusPendingForOrder(int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }

        $this->ensurePendingInvoicesRowForOrder($orderId);

        $this->invoiceMst->newQuery()
            ->where('order_id', $orderId)
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereRaw("LOWER(TRIM(status)) NOT IN ('paid', 'success', 'completed', 'succeeded')");
            })
            ->update([
                'status' => 'pending',
                'updated_at' => now(),
            ]);
    }

    /**
     * When an order is cancelled, mark linked partner tax invoices as cancelled
     * so the invoice list shows Cancelled (and Refund Soon when paid).
     * Credit notes stay issued — they are the cancellation document.
     */
    public function markInvoicesCancelledForOrder(int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }

        try {
            $query = $this->invoiceMst->newQuery()->where('order_id', $orderId);
            $conn = $this->invoiceMst->getConnectionName();
            if (Schema::connection($conn)->hasColumn('invoice_mst', 'action_type')) {
                $query->where(function ($q) {
                    $q->whereNull('action_type')
                        ->orWhere('action_type', '!=', OrderCancellationCreditNoteService::CREDIT_NOTE_ACTION);
                });
            }
            $query->where(function ($q) {
                $q->whereNull('bill_no_full')
                    ->orWhere('bill_no_full', 'not like', 'CN/%');
            });
            $query->update([
                'status' => 'cancelled',
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('markInvoicesCancelledForOrder failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Fire-and-forget Sandbox e-invoice generation. Wrapped in try/catch so
     * an API outage NEVER blocks the order save. The toggle is read from
     * config('services.sandbox.auto_generate'), and the call is a no-op if
     * the `invoices` table does not exist (legacy/older deployments).
     *
     * The service itself reads invoice_mst + invoice_sub for line items
     * and persists the IRN/QR back into the `invoices` table.
     */
    private function maybeGenerateSandboxEInvoice(int $orderId): void
    {
        try {
            if (!config('services.sandbox.auto_generate', true)) {
                return;
            }
            app(SandboxEInvoiceService::class)->generateForOrder($orderId);
        } catch (\Throwable $e) {
            Log::warning('Sandbox e-invoice trigger failed (order save unaffected)', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Edit-path counterpart to maybeGenerateSandboxEInvoice. If a non-cancelled
     * IRN already exists for this order, the service first calls Sandbox cancel
     * with reason "Data Entry Mistake" (CnlRsn=2), then regenerates a fresh IRN
     * with the updated invoice payload. Cancel/regenerate failures NEVER block
     * the order save — they only log a warning.
     */
    private function maybeRegenerateSandboxEInvoice(int $orderId): void
    {
        try {
            if (!config('services.sandbox.auto_generate', true)) {
                return;
            }
            app(SandboxEInvoiceService::class)->regenerateForOrder($orderId);
        } catch (\Throwable $e) {
            Log::warning('Sandbox e-invoice regenerate failed (order save unaffected)', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * After an order is edited, push the updated amount & item summary back
     * into `order_po_details` so the generated PO and downstream e-invoice
     * payload stay in sync. All writes are schema-aware: only columns that
     * exist on `order_po_details` are touched.
     *
     * Updated columns (when present):
     *   - po_amount        ← orders.total_amount / amount / price / subtotal
     *   - po_items / items / line_items (JSON) ← serialized line summary
     *   - updated_at
     */
    public function syncPoDetailsFromOrder(int $orderId, object $order): void
    {
        try {
            $cols = $this->orderPoDetailColumns();
            if (!in_array('order_id', $cols, true)) {
                return;
            }

            // Resolve the canonical "amount" for this order — total_amount wins.
            $amount = 0.0;
            foreach (['total_amount', 'amount', 'price', 'subtotal'] as $candidate) {
                $val = $order->{$candidate} ?? null;
                if ($val !== null && $val !== '' && (float) $val > 0) {
                    $amount = (float) $val;
                    break;
                }
            }

            $update = [];
            if (in_array('po_amount', $cols, true) && $amount > 0) {
                $update['po_amount'] = $amount;
            }

            // Build a compact line-items JSON (pulled from invoice_sub when
            // available, else synthesized from the order). Persist into the
            // first JSON-style items column that exists.
            $itemsCol = null;
            $itemsSummary = [];
            foreach (['po_items', 'items', 'line_items'] as $candidate) {
                if (in_array($candidate, $cols, true)) {
                    $itemsCol = $candidate;
                    break;
                }
            }
            if ($itemsCol !== null) {
                $itemsSummary = $this->buildPoItemsSummary($orderId, $order);
                if (!empty($itemsSummary)) {
                    $update[$itemsCol] = json_encode($itemsSummary, JSON_UNESCAPED_UNICODE);
                }
            }

            $taxType = '';
            $cgstTotal = 0.0;
            $sgstTotal = 0.0;
            $igstTotal = 0.0;
            $mst = $this->invoiceMst->newQuery()->where('order_id', $orderId)->orderByDesc('id')->first();
            if ($mst) {
                $taxType = strtolower(trim((string) ($mst->tax_type ?? '')));
                $cgstTotal = round((float) ($mst->cgst_total ?? 0), 2);
                $sgstTotal = round((float) ($mst->sgst_total ?? 0), 2);
                $igstTotal = round((float) ($mst->igst_total ?? 0), 2);
            }
            if ($taxType === '') {
                $customerGstin = trim((string) ($order->po_manual_gstin ?? $order->customer_gstin ?? ''));
                $gst = round((float) ($order->gst_amount ?? $order->tax_amount ?? 0), 2);
                $split = GstSplit::split($gst, $customerGstin);
                $taxType = $split['tax_type'];
                $cgstTotal = $split['cgst'];
                $sgstTotal = $split['sgst'];
                $igstTotal = $split['igst'];
            }
            if (in_array('tax_type', $cols, true)) {
                $update['tax_type'] = $taxType;
            }
            if (in_array('cgst_total', $cols, true)) {
                $update['cgst_total'] = $cgstTotal;
            }
            if (in_array('sgst_total', $cols, true)) {
                $update['sgst_total'] = $sgstTotal;
            }
            if (in_array('igst_total', $cols, true)) {
                $update['igst_total'] = $igstTotal;
            }

            if (empty($update)) {
                return; // nothing relevant to update
            }
            if (in_array('updated_at', $cols, true)) {
                $update['updated_at'] = now();
            }

            $existing = $this->orderPoDetails->newQuery()->where('order_id', $orderId)->first();
            if ($existing) {
                $this->orderPoDetails->newQuery()->where('order_id', $orderId)->update($update);
            } else {
                $insert = array_merge(['order_id' => $orderId], $update);
                if (in_array('created_at', $cols, true)) {
                    $insert['created_at'] = now();
                }
                $this->orderPoDetails->newQuery()->insert($insert);
            }

            if (!empty($itemsSummary)) {
                $this->orderPoSubs->newQuery()->where('order_id', $orderId)->delete();
                $subCols = $this->orderPoSubColumns();
                foreach ($itemsSummary as $item) {
                    $sub = [];
                    if (in_array('order_id', $subCols, true)) {
                        $sub['order_id'] = $orderId;
                    }
                    if (in_array('description', $subCols, true)) {
                        $sub['description'] = (string) ($item['description'] ?? '');
                    }
                    if (in_array('hsn_sac', $subCols, true)) {
                        $sub['hsn_sac'] = (string) ($item['hsn'] ?? '');
                    }
                    if (in_array('qty', $subCols, true)) {
                        $sub['qty'] = (float) ($item['qty'] ?? 0);
                    }
                    if (in_array('cnt_rate', $subCols, true)) {
                        $sub['cnt_rate'] = (float) ($item['rate'] ?? 0);
                    }
                    if (in_array('taxable_amt', $subCols, true)) {
                        $sub['taxable_amt'] = (float) ($item['taxable'] ?? 0);
                    }
                    if (in_array('tax_type', $subCols, true)) {
                        $sub['tax_type'] = (string) ($item['tax_type'] ?? $taxType);
                    }
                    if (in_array('cgst', $subCols, true)) {
                        $sub['cgst'] = (float) ($item['cgst'] ?? 0);
                    }
                    if (in_array('sgst', $subCols, true)) {
                        $sub['sgst'] = (float) ($item['sgst'] ?? 0);
                    }
                    if (in_array('igst', $subCols, true)) {
                        $sub['igst'] = (float) ($item['igst'] ?? 0);
                    }
                    if (in_array('nettotal', $subCols, true)) {
                        $sub['nettotal'] = (float) ($item['total'] ?? 0);
                    }
                    if (in_array('created_at', $subCols, true)) {
                        $sub['created_at'] = now();
                    }
                    if (in_array('updated_at', $subCols, true)) {
                        $sub['updated_at'] = now();
                    }
                    if (!empty($sub)) {
                        $this->orderPoSubs->newQuery()->insert($sub);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('syncPoDetailsFromOrder failed', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Compact line-items summary used when stamping order_po_details.po_items.
     * Prefers invoice_sub (authoritative GST breakdown), else falls back to
     * a single line built from the order's plan/amount.
     *
     * @return array<int, array<string,mixed>>
     */
    private function buildPoItemsSummary(int $orderId, object $order): array
    {
        try {
            $mst = $this->latestTaxInvoiceMstForOrder($orderId);
            if ($mst) {
                $rows = $this->invoiceSubs->newQuery()->where('invoice_mst_id', (int) $mst->id)->get();
                $items = [];
                foreach ($rows as $r) {
                    $items[] = [
                        'description' => (string) ($r->invoice_no ?? $order->plan_name ?? 'Service'),
                        'hsn'         => (string) ($r->hsn_sac ?? ''),
                        'qty'         => (float) ($r->qty ?? 1),
                        'rate'        => (float) ($r->cnt_rate ?? 0),
                        'taxable'     => (float) ($r->taxable_amt ?? 0),
                        'tax_type'    => (string) ($r->tax_type ?? ($mst->tax_type ?? '')),
                        'cgst'        => (float) ($r->cgst ?? 0),
                        'sgst'        => (float) ($r->sgst ?? 0),
                        'igst'        => (float) ($r->igst ?? 0),
                        'total'       => (float) ($r->nettotal ?? 0),
                    ];
                }
                if (!empty($items)) {
                    return $items;
                }
            }
        } catch (\Throwable $e) {
            // Falls through to the single-line synthesis below.
        }

        $customerGstin = trim((string) ($order->po_manual_gstin ?? $order->customer_gstin ?? ''));
        $gst = (float) ($order->gst_amount ?? $order->tax_amount ?? 0);
        $split = GstSplit::split($gst, $customerGstin);

        return [[
            'description' => (string) ($order->plan_name ?? $order->domain_name ?? 'Service'),
            'hsn'         => (string) ($order->hsn_code ?? '997331'),
            'qty'         => (float) ($order->quantity ?? 1),
            'rate'        => (float) ($order->price ?? 0),
            'taxable'     => (float) ($order->subtotal ?? $order->price ?? 0),
            'tax_type'    => $split['tax_type'],
            'cgst'        => $split['cgst'],
            'sgst'        => $split['sgst'],
            'igst'        => $split['igst'],
            'total'       => (float) ($order->total_amount ?? 0),
        ]];
    }

    /**
     * Manual cancel endpoint (POST /superadmin/orders/{id}/einvoice-cancel).
     * Looks up the IRN from the `invoices` row linked to this order.
     */
    public function cancelEInvoice(Request $request, int $id)
    {
        $invoice = $this->invoiceMst->newQuery()
            ->where('order_id', $id)
            ->orderByDesc('id')
            ->first();

        if (!$invoice || empty($invoice->irn)) {
            return response()->json(['success' => false, 'message' => 'No IRN found for this order.'], 404);
        }

        $reason  = (string) $request->input('reason_code', '1'); // 1=Duplicate,2=Data entry,3=Order cancelled,4=Other
        $remarks = trim((string) $request->input('remarks', 'Cancelled via admin')) ?: 'Cancelled via admin';

        $result = app(SandboxEInvoiceService::class)->cancelIrn((string) $invoice->irn, $reason, $remarks);

        return response()->json([
            'success' => (bool) ($result['ok'] ?? false),
            'message' => ($result['ok'] ?? false) ? 'IRN cancelled.' : ('Cancel failed: ' . ($result['error'] ?? 'unknown')),
            'data'    => $result['raw'] ?? null,
        ], ($result['ok'] ?? false) ? 200 : 422);
    }

    public function update(Request $request, int $id)
    {
        return $this->saveStep($request, 1, $id);
    }

    public function saveStep(Request $request, int $step = 1, ?int $id = null)
    {
        $step = max(1, min(3, (int) $step));

        if ($step === 1) {
            // --- Step 1: linear transaction flow (Logistics-style) ---
            // Validation runs first; DB work uses explicit begin/commit/rollback.
            $validationJson = $this->validateOrderStepOneForWizard($request, $id);
            if ($validationJson !== null) {
                return $validationJson;
            }

            if ($id !== null && !$this->orders->newQuery()->where('id', $id)->exists()) {
                if ($this->wantsOrderWizardJson($request)) {
                    return response()->json(['error' => true, 'message' => 'Order not found.'], 404);
                }
                abort(404);
            }

            DB::beginTransaction();
            try {
                // 1) Build payload (includes duration = '12 month' when column exists)
                $payload = $this->buildOrderPayload($request, $id === null, $id !== null ? (int) $id : null);
                $orderColumns = $this->orderColumns();

                if ($id === null) {
                    // 2) Order number / serial (same as before)
                    if (in_array('order_no', $orderColumns, true) || in_array('sr_no', $orderColumns, true)) {
                        $next = $this->nextOrderIdentifiers();
                        if (in_array('order_no', $orderColumns, true)) {
                            $payload['order_no'] = $next['order_no'];
                        }
                        if (in_array('sr_no', $orderColumns, true)) {
                            $payload['sr_no'] = $next['sr_no'];
                        }
                    }

                    // 3) Insert order
                    $orderId = (int) $this->orders->newQuery()->insertGetId($payload);

                    $createdType = strtolower(trim((string) ($payload['order_type'] ?? 'b2c')));
                    if ($createdType === 'b2b' && !$this->deferPoSaveOnOrder($request)) {
                        $this->seedB2bOrderCustomerSnapshot($orderId);
                    }

                    // 4) Wallet / credit ledger (B2B)
                    $this->syncB2bOrderWalletLedger($orderId, $payload);

                    // 5) GST invoice master + lines (invoice_mst / invoice_sub)
                    $this->ensureGstInvoiceForOrder($orderId);

                    // 5b) Modern `invoices` row with status pending (e-invoice / invoice management), even if Sandbox IRN migration is pending
                    $this->ensurePendingInvoicesRowForOrder($orderId);

                    // 6) Sandbox e-invoice → persists IRN/QR/PDF URL on `invoices` (try/catch inside service)
                    $this->maybeGenerateSandboxEInvoice($orderId);
                } else {
                    // --- Update existing order (same behaviour as before) ---
                    $this->orders->newQuery()->where('id', $id)->update($payload);

                    $fresh = $this->orders->newQuery()->where('id', $id)->first();
                    if ($fresh) {
                        $this->syncB2bOrderWalletLedger((int) $id, (array) $fresh);
                        $this->syncInvoiceForUpdatedOrder((int) $id);
                        $this->ensurePendingInvoicesRowForOrder((int) $id);
                        if (!$this->deferPoSaveOnOrder($request)) {
                            $this->syncPoDetailsFromOrder((int) $id, (object) $fresh);
                        }
                        $this->maybeRegenerateSandboxEInvoice((int) $id);
                    }

                    $orderId = (int) $id;
                }

                DB::commit();
            } catch (ValidationException $e) {
                DB::rollBack();
                if ($this->wantsOrderWizardJson($request)) {
                    return response()->json([
                        'error' => true,
                        'message' => collect($e->errors())->flatten()->first() ?: $e->getMessage(),
                        'details' => $e->errors(),
                    ], 422);
                }
                throw $e;
            } catch (\Throwable $e) {
                DB::rollBack();
                if ($this->wantsOrderWizardJson($request)) {
                    Log::error('Order wizard step 1 save failed', [
                        'order_id' => $id,
                        'error' => $e->getMessage(),
                    ]);

                    return response()->json([
                        'error' => true,
                        'message' => 'Order save failed: ' . $e->getMessage(),
                    ], 500);
                }
                throw $e;
            }

            $savedOrder = $this->orders->newQuery()->where('id', $orderId)->first();
            $savedType = strtolower(trim((string) ($savedOrder->order_type ?? $request->input('order_type', 'b2c'))));
            if ($this->wantsOrderWizardJson($request)) {
                if ($savedType !== 'b2b') {
                    return response()->json([
                        'success' => true,
                        'message' => 'Order saved.',
                        'redirect_url' => route('superadmin.orders.details', ['id' => $orderId]),
                        'order_id' => $orderId,
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Order details saved. Continue with PO details.',
                    'redirect_url' => route('superadmin.orders.create.step', ['step' => 2, 'id' => $orderId]),
                    'order_id' => $orderId,
                ]);
            }

            if ($savedType !== 'b2b') {
                return redirect()
                    ->route('superadmin.orders.details', ['id' => $orderId])
                    ->with('success', 'Order saved successfully.');
            }

            return redirect()
                ->route('superadmin.orders.create.step', ['step' => 2, 'id' => $orderId])
                ->with('success', 'Order details saved. Continue with PO details.');
        }

        if ($id === null || !$this->orders->newQuery()->where('id', $id)->exists()) {
            if ($this->wantsOrderWizardJson($request)) {
                return response()->json(['error' => true, 'message' => 'Order not found.'], 404);
            }
            abort(404);
        }

        $existingOrder = $this->orders->newQuery()->where('id', (int) $id)->first();
        $existingType = strtolower(trim((string) ($existingOrder->order_type ?? 'b2c')));
        if ($existingType !== 'b2b') {
            if ($this->wantsOrderWizardJson($request)) {
                return response()->json([
                    'error' => true,
                    'message' => 'PO/Aadhaar flow is available only for B2B orders.',
                ], 422);
            }

            return redirect()
                ->route('superadmin.orders.details', ['id' => (int) $id])
                ->with('error', 'PO/Aadhaar flow is available only for B2B orders.');
        }

        if ($step === 3 && ($request->boolean('wizard_status_update') || (string) $request->input('wizard_status_update') === '1')) {
            $orderId = (int) $id;
            $status = strtolower(trim((string) $request->input('status', '')));
            if ($status === 'cancelled') {
                $status = 'rejected';
            }
            if (!in_array($status, ['pending', 'completed', 'rejected'], true)) {
                $message = 'Invalid order status selected.';
                if ($this->wantsOrderWizardJson($request)) {
                    return response()->json(['error' => true, 'message' => $message], 422);
                }

                return redirect()
                    ->route('superadmin.orders.details', ['id' => $orderId])
                    ->with('error', $message);
            }

            $rejectionReason = trim((string) $request->input('rejection_reason', $request->input('order_note', '')));
            if ($status === 'rejected' && $rejectionReason === '') {
                $message = 'Cancellation reason is required.';
                if ($this->wantsOrderWizardJson($request)) {
                    return response()->json(['error' => true, 'message' => $message], 422);
                }

                return redirect()
                    ->route('superadmin.orders.details', ['id' => $orderId])
                    ->with('error', $message);
            }

            if ($status === 'rejected') {
                $cancelBlockMessage = $this->orderCancellationBlockMessage($orderId);
                if ($cancelBlockMessage !== null) {
                    if ($this->wantsOrderWizardJson($request)) {
                        return response()->json(['error' => true, 'message' => $cancelBlockMessage], 422);
                    }

                    return redirect()
                        ->route('superadmin.orders.details', ['id' => $orderId])
                        ->with('error', $cancelBlockMessage);
                }
            }

            if ($status === 'completed') {
                if (!$request->boolean('wizard_status_update')) {
                    $blockMessage = $this->orderAadhaarBlocksActivation($orderId);
                    if ($blockMessage !== null) {
                        if ($this->wantsOrderWizardJson($request)) {
                            return response()->json(['error' => true, 'message' => $blockMessage], 422);
                        }

                        return redirect()
                            ->route('superadmin.orders.details', ['id' => $orderId])
                            ->with('error', $blockMessage);
                    }
                }
            }

            $orderBefore = $this->findOrder($orderId);
            $previousStatus = strtolower(trim((string) ($orderBefore->status ?? '')));
            $isPreviouslyActive = in_array($previousStatus, ['completed', 'paid', 'success', 'active'], true);
            if ($status === 'rejected' && !$isPreviouslyActive) {
                $message = 'Cancel is allowed only after the order is Active.';
                if ($this->wantsOrderWizardJson($request)) {
                    return response()->json(['error' => true, 'message' => $message], 422);
                }

                return redirect()
                    ->route('superadmin.orders.details', ['id' => $orderId])
                    ->with('error', $message);
            }
            if ($isPreviouslyActive && $status === 'pending') {
                $message = 'Active order cannot be moved back to pending.';
                if ($this->wantsOrderWizardJson($request)) {
                    return response()->json(['error' => true, 'message' => $message], 422);
                }

                return redirect()
                    ->route('superadmin.orders.details', ['id' => $orderId])
                    ->with('error', $message);
            }

            $partnerId = (int) ($orderBefore->partner_id ?? 0);
            $wasActive = $isPreviouslyActive;
            $forceWalletSync = $request->boolean('force_wallet_sync');
            if ($partnerId > 0 && strtolower(trim((string) ($orderBefore->order_type ?? 'b2b'))) === 'b2b') {
                if ($status === 'completed' && (!$wasActive || $forceWalletSync)) {
                    $orderRow = $this->orders->newQuery()->where('id', $orderId)->first();
                    if ($orderRow) {
                        $grandTotal = $this->resolveOrderGrandTotal($orderRow);
                        $currentCreditUsed = (float) ($orderRow->credit_used ?? 0);
                        $shouldSyncCreditUsed = $grandTotal > 0 && (
                            $currentCreditUsed <= 0
                            || $forceWalletSync
                            || abs($currentCreditUsed - $grandTotal) > 0.01
                        );
                        if ($shouldSyncCreditUsed) {
                            $this->orders->newQuery()->where('id', $orderId)->update([
                                'credit_used' => $grandTotal,
                                'updated_at' => now(),
                            ]);
                            $orderRow = $this->orders->newQuery()->where('id', $orderId)->first();
                        }
                        $walletPayload = (array) $orderRow;
                        $walletPayload['status'] = 'completed';
                        $walletError = $this->partnerCreditBlocksActivation($orderRow, $orderId);
                        if ($walletError !== null) {
                            if ($this->wantsOrderWizardJson($request)) {
                                return response()->json(['error' => true, 'message' => $walletError], 422);
                            }

                            return redirect()
                                ->route('superadmin.orders.details', ['id' => $orderId])
                                ->with('error', $walletError);
                        }
                        try {
                            $this->syncB2bOrderWalletLedger($orderId, $walletPayload);
                        } catch (ValidationException $e) {
                            $walletError = collect($e->errors())->flatten()->first() ?: 'Wallet deduction failed.';
                            if ($this->wantsOrderWizardJson($request)) {
                                return response()->json(['error' => true, 'message' => $walletError], 422);
                            }

                            return redirect()
                                ->route('superadmin.orders.details', ['id' => $orderId])
                                ->with('error', $walletError);
                        }
                    }
                } elseif ($status === 'rejected' && $wasActive) {
                    \App\Services\PartnerCreditService::cleanupB2bOrderWalletLedgersForOrder($orderId);
                    $wallet = \App\Models\AccountMaster::query()
                        ->where('refer_id', $partnerId)
                        ->where('type', 'Partner')
                        ->where('name', \App\Services\PartnerCreditService::partnerWalletAccountName($partnerId))
                        ->first();
                    if ($wallet) {
                        $wallet->syncCurrentBalance();
                        \App\Services\PartnerCreditService::syncPartnerWalletSnapshotFromAccount($partnerId, $wallet);
                    }
                }
            }

            // Persist status only after wallet sync succeeds for completed transitions.
            $patch = ['updated_at' => now()];
            if (true) {
                $patch['status'] = $status;
            }
            if ($status === 'rejected') {
                $patch['order_note'] = $rejectionReason;
            }
            // Downgrade becomes Active → start a fresh term from activation day (not parent dates).
            if ($status === 'completed' && !$wasActive) {
                $cat = strtolower(trim((string) ($orderBefore->order_category ?? '')));
                $otype = strtolower(trim((string) ($orderBefore->order_type ?? '')));
                if (in_array($cat, ['downgrade', 'downgraded'], true) || $otype === 'downgrade') {
                    $start = \Carbon\Carbon::now()->startOfDay();
                    $end = app(\App\Services\RenewalService::class)
                        ->subscriptionEndDate($start, (string) ($orderBefore->duration ?? '12 month'))
                        ->startOfDay();
                    $patch['subscription_start_date'] = $start->toDateString();
                    $patch['subscription_end_date'] = $end->toDateString();
                    \App\Models\OrderItem::query()
                        ->where('order_id', $orderId)
                        ->update([
                            'subscription_start_date' => $patch['subscription_start_date'],
                            'subscription_end_date' => $patch['subscription_end_date'],
                        ]);
                }
            }
            $this->orders->newQuery()->where('id', $orderId)->update($patch);

            $refundNote = '';
            $creditNoteNote = '';
            if ($status === 'completed') {
                try {
                    $this->finalizePartnerB2bOrderPostSave($orderId);
                    $this->markParentSubscriptionUpgradedIfNeeded($orderId);
                    if (!app(\App\Services\OrderPoDocumentService::class)->isUploadedPo($orderId)) {
                        $this->ensureGeneratedPoForOrder($orderId);
                        $this->persistOrderPoPdfToStorage($orderId);
                    }
                    $this->persistOrderInvoicePdfToStorage($orderId);
                    $this->markInvoiceStatusPendingForOrder($orderId);
                    $this->syncOrderPoJourneyOnOrdersTable($orderId, 'placed');
                } catch (\Throwable $e) {
                    Log::warning('Order status completed: PO/invoice persist failed', [
                        'order_id' => $orderId,
                        'error' => $e->getMessage(),
                    ]);
                }
                if ($status === 'completed' && !$wasActive && !$request->boolean('skip_partner_notify')) {
                    $this->deferOrderActivationNotifications($orderId);
                }
            } elseif ($status === 'rejected') {
                try {
                    // Rebuild stored PO PDF so /storage/order-po/*.pdf also reflects cancelled watermark.
                    $this->persistOrderPoPdfToStorage($orderId, false);
                } catch (\Throwable $e) {
                    Log::warning('Order rejection: PO PDF persist failed', [
                        'order_id' => $orderId,
                        'error' => $e->getMessage(),
                    ]);
                }
                $this->markInvoicesCancelledForOrder($orderId);
                $creditNoteIssue = $this->issueCancellationCreditNote($orderId, $rejectionReason !== '' ? $rejectionReason : 'Order cancelled');
                $creditNoteNote = (string) ($creditNoteIssue['message'] ?? '');
                $refundNote = $this->refundPaidOrderOnCancellation(
                    $orderId,
                    $rejectionReason !== '' ? $rejectionReason : 'Order cancelled after payment'
                );
                $this->sendOrderRejectedClientMail($orderId, $rejectionReason);
                $this->notifyPartnerOrderCancelled(
                    $orderId,
                    $rejectionReason !== '' ? $rejectionReason : 'Order cancelled',
                    $creditNoteIssue['credit_note'] ?? null
                );
            }

            $successMessage = match ($status) {
                'completed' => 'Order marked active. Invoice generated and partner notified as per preferences.',
                'rejected' => 'Order cancelled. Customer notified by email.' . ($creditNoteNote ?? '') . $refundNote,
                default => 'Order status set to pending.',
            };

            if ($this->wantsOrderWizardJson($request)) {
                return response()->json([
                    'success' => true,
                    'message' => $successMessage,
                    'order_id' => $orderId,
                    'status' => $status,
                    'redirect_url' => route('superadmin.orders.details', ['id' => $orderId]),
                ]);
            }

            return redirect()
                ->route('superadmin.orders.details', ['id' => $orderId])
                ->with('success', $successMessage);
        }

        if ($step === 2) {
            $this->normalizePoInputsForValidation($request);
            $this->mapB2bWizardPoFieldAliases($request);
            $request->merge(['po_journey_status' => 'po_done']);
            $this->upsertOrderPoDetails((int) $id, $request);
            $this->syncOrderPoJourneyOnOrdersTable((int) $id, 'po_done', $request);
            $freshOrder = $this->orders->newQuery()->where('id', (int) $id)->first();
            if ($freshOrder) {
                $this->syncPoDetailsFromOrder((int) $id, $freshOrder);
            }

            try {
                $this->completeOrderAfterAadhaarVerification((int) $id, '', null, null, false, true);
            } catch (ValidationException $e) {
                $message = collect($e->errors())->flatten()->first() ?: 'Order could not be completed.';
                if ($this->wantsOrderWizardJson($request)) {
                    return response()->json(['error' => true, 'message' => $message], 422);
                }

                return redirect()
                    ->route('superadmin.orders.create.step', ['step' => 2, 'id' => (int) $id])
                    ->with('error', $message);
            }

            $message = 'PO details saved. Order placed successfully.';
            if ($this->wantsOrderWizardJson($request)) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'redirect_url' => route('superadmin.orders.index'),
                    'order_id' => (int) $id,
                ]);
            }

            return redirect()
                ->route('superadmin.orders.index')
                ->with('success', $message);
        }

        if (!$this->isOrderPoSubmitted((int) $id)) {
            $message = 'Please save PO details before continuing to Aadhaar verification.';
            if ($this->wantsOrderWizardJson($request)) {
                return response()->json(['error' => true, 'message' => $message], 422);
            }

            return redirect()
                ->route('superadmin.orders.create.step', ['step' => 2, 'id' => (int) $id])
                ->with('error', $message);
        }

        $request->merge(['po_journey_status' => 'placed']);
        $this->upsertOrderPoDetails((int) $id, $request);
        $aadhaarVerified = false;
        if (true) {
            $poRow = $this->orderPoDetails->newQuery()->where('order_id', (int) $id)->first();
            $aadhaarVerified = (int) ($poRow->aadhar_verified ?? 0) === 1;
        }
        $orderCategory = strtolower(trim((string) ($this->orders->newQuery()->where('id', (int) $id)->value('order_category') ?? 'new')));
        $isExistingCategory = in_array($orderCategory, ['existing', 'renewal', 'renewed', 'upgrade', 'upgraded', 'downgrade'], true);

        if (!$aadhaarVerified) {
            $this->orders->newQuery()
                ->where('id', (int) $id)
                ->update([
                    'status' => $isExistingCategory ? 'completed' : 'pending',
                    'updated_at' => now(),
                ]);
            if ($isExistingCategory) {
                $this->markParentSubscriptionUpgradedIfNeeded((int) $id);
            }
        }

        if ($aadhaarVerified) {
            $this->sendOrderPlacedClientMail((int) $id);
        }

        $postSubmitAction = strtolower(trim((string) $request->input('post_submit_action', 'details')));
        if (!$aadhaarVerified) {
            if ($isExistingCategory) {
                $message = 'Aadhaar saved. Order marked as completed.';
                if ($this->wantsOrderWizardJson($request)) {
                    return response()->json([
                        'success' => true,
                        'message' => $message,
                        'redirect_url' => route('superadmin.orders.index'),
                        'order_id' => (int) $id,
                    ]);
                }

                return redirect()
                    ->route('superadmin.orders.index')
                    ->with('success', $message);
            }

            if ($postSubmitAction === 'list') {
                if ($this->wantsOrderWizardJson($request)) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Order saved as pending. Complete Aadhaar verification to mark completed.',
                        'redirect_url' => route('superadmin.orders.index'),
                        'order_id' => (int) $id,
                    ]);
                }

                return redirect()
                    ->route('superadmin.orders.index')
                    ->with('success', 'Order saved as pending. Complete Aadhaar verification to mark completed.');
            }
            if ($this->wantsOrderWizardJson($request)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Order saved as pending. Complete Aadhaar verification to mark completed.',
                    'redirect_url' => route('superadmin.orders.details', ['id' => (int) $id]),
                    'order_id' => (int) $id,
                ]);
            }

            return redirect()
                ->route('superadmin.orders.details', ['id' => (int) $id])
                ->with('success', 'Order saved as pending. Complete Aadhaar verification to mark completed.');
        }
        if ($postSubmitAction === 'list') {
            if ($this->wantsOrderWizardJson($request)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Order placed successfully.',
                    'redirect_url' => route('superadmin.orders.index'),
                    'order_id' => (int) $id,
                ]);
            }

            return redirect()
                ->route('superadmin.orders.index')
                ->with('success', 'Order placed successfully.');
        }
        if ($this->wantsOrderWizardJson($request)) {
            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully.',
                'redirect_url' => route('superadmin.orders.details', ['id' => (int) $id]),
                'order_id' => (int) $id,
            ]);
        }

        return redirect()
            ->route('superadmin.orders.details', ['id' => (int) $id])
            ->with('success', 'Order placed successfully.');
    }

    /**
     * Detect AJAX / JSON request from the order wizard (jQuery sets X-Requested-With).
     */
    private function wantsOrderWizardJson(Request $request): bool
    {
        return $request->ajax()
            || $request->wantsJson()
            || $request->boolean('ajax_submit');
    }

    /**
     * Step 1 validation + domain registration rule. Returns a JSON response when
     * validation fails and the client expects JSON; otherwise throws ValidationException.
     */
    private function validateOrderStepOneForWizard(Request $request, ?int $id): ?\Illuminate\Http\JsonResponse
    {
        $this->normalizePoInputsForValidation($request);
        $this->mergeComputedOrderAmount($request);
        $validator = $this->buildOrderRequestValidator($request, $id === null, $id);
        if ($validator->fails()) {
            if ($this->wantsOrderWizardJson($request)) {
                return response()->json([
                    'error' => true,
                    'details' => $validator->errors(),
                ], 422);
            }
            throw new ValidationException($validator);
        }

        try {
            $this->assertPlanQuantityWithinCustomerLimit($request);
        } catch (ValidationException $e) {
            if ($this->wantsOrderWizardJson($request)) {
                return response()->json([
                    'error' => true,
                    'message' => $e->getMessage() !== '' ? $e->getMessage() : collect($e->errors())->flatten()->first(),
                    'details' => $e->errors(),
                ], 422);
            }
            throw $e;
        }

        try {
            $this->assertB2bCreditLimitForWizard($request, $id);
        } catch (ValidationException $e) {
            if ($this->wantsOrderWizardJson($request)) {
                return response()->json([
                    'error' => true,
                    'message' => collect($e->errors())->flatten()->first() ?: $e->getMessage(),
                    'details' => $e->errors(),
                ], 422);
            }
            throw $e;
        }

        try {
            $this->assertDomainRegisteredForOrderSave($request);
        } catch (ValidationException $e) {
            if ($this->wantsOrderWizardJson($request)) {
                return response()->json([
                    'error' => true,
                    'message' => collect($e->errors())->flatten()->first() ?: $e->getMessage(),
                    'details' => $e->errors(),
                ], 422);
            }
            throw $e;
        }

        return null;
    }

    /**
     * Registrar domain must already be registered.
     * Provider 3 is exempt. Microsoft .onmicrosoft.com tenant domains are exempt.
     */
    private function assertDomainRegisteredForOrderSave(Request $request): void
    {
        $providerId = OrderDomainHelper::resolveProviderIdFromRequest($request);
        if ($providerId === 3) {
            return;
        }

        $domain = trim((string) $request->input('domain_name', ''));
        $validation = OrderDomainHelper::validateInput($domain, $providerId);
        if (!$validation['ok']) {
            throw ValidationException::withMessages([
                'domain_name' => (string) ($validation['message'] ?: 'Invalid domain name.'),
            ]);
        }

        $isMicrosoft = OrderDomainHelper::isMicrosoftProvider($providerId);
        $segments = $validation['segments'];

        // Microsoft Transfer=No uses *.onmicrosoft.com — no registrar check needed.
        if ($isMicrosoft) {
            $custom = array_values(array_filter(
                $segments,
                static fn(string $s): bool => !preg_match('/\.onmicrosoft\.com$/i', $s)
            ));
            if ($custom === []) {
                return;
            }
            $segments = $custom;
        } elseif (count($segments) > 1) {
            throw ValidationException::withMessages([
                'domain_name' => 'Please enter only one domain name.',
            ]);
        }

        foreach ($segments as $segment) {
            $openForRegistration = $this->isDomainAvailableForRegistration($segment);

            if ($openForRegistration) {
                throw ValidationException::withMessages([
                    'domain_name' => 'This domain is not registered yet. Please purchase/register the domain first before adding a plan.',
                ]);
            }
        }
    }

    /**
     * Enforce plans.customer_limit on license / quantity for wizard and API saves.
     */
    private function assertPlanQuantityWithinCustomerLimit(Request $request): void
    {
        $lines = [];
        foreach (['plans', 'items'] as $key) {
            $raw = $request->input($key, []);
            if (!is_array($raw) || $raw === []) {
                continue;
            }
            foreach ($raw as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $planId = (int) ($row['plan_id'] ?? 0);
                $qty = max(1, (int) ($row['quantity'] ?? $row['licenses'] ?? 1));
                if ($planId > 0) {
                    $lines[] = ['plan_id' => $planId, 'quantity' => $qty];
                }
            }
            if ($lines !== []) {
                break;
            }
        }

        if ($lines === []) {
            $planId = (int) $request->input('plan_id', 0);
            $qty = max(1, (int) $request->input('quantity', $request->input('licenses', 1)));
            if ($planId > 0) {
                $lines[] = ['plan_id' => $planId, 'quantity' => $qty];
            }
        }

        if ($lines === []) {
            return;
        }

        $errors = [];
        foreach ($lines as $line) {
            $plan = $this->plans->newQuery()->where('id', $line['plan_id'])->first();
            if (!$plan) {
                continue;
            }
            $limit = (int) ($plan->customer_limit ?? 0);
            if ($limit > 0 && (int) $line['quantity'] > $limit) {
                $planName = trim((string) ($plan->name ?? '')) ?: ('Plan #' . $line['plan_id']);
                $errors['quantity'][] = 'Licenses for "' . $planName . '" cannot exceed ' . $limit . ' (plan customer limit).';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * JSON error response when B2B credit limit blocks wizard save (steps 2/3/Aadhaar).
     */
    public function b2bCreditWizardJsonResponse(Request $request, int $orderId): ?JsonResponse
    {
        if ($orderId <= 0) {
            return null;
        }

        try {
            $this->assertB2bCreditLimitForWizard($request, $orderId);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => true,
                'message' => collect($e->errors())->flatten()->first() ?: $e->getMessage(),
                'details' => $e->errors(),
            ], 422);
        }

        return null;
    }

    /**
     * Block B2B wizard saves when partner credit limit cannot cover the order.
     */
    private function assertB2bCreditLimitForWizard(Request $request, ?int $orderId = null): void
    {
        if (strtolower(trim((string) $request->input('order_type', 'b2b'))) === 'b2c') {
            return;
        }

        $partnerId = (int) $request->input('partner_id', 0);
        if ($partnerId <= 0 && $orderId !== null && $orderId > 0) {
            $partnerId = (int) $this->orders->newQuery()->where('id', $orderId)->value('partner_id');
        }
        if ($partnerId <= 0) {
            throw ValidationException::withMessages([
                'partner_id' => 'Partner is required for B2B orders.',
            ]);
        }

        $amount = round((float) $request->input('total_amount', $request->input('amount', 0)), 2);
        if ($amount <= 0.005) {
            $amount = round(
                (float) $request->input('subtotal', 0)
                    + (float) $request->input('gst_amount', $request->input('tax_amount', 0)),
                2
            );
        }
        if ($amount <= 0.005 && $orderId !== null && $orderId > 0) {
            $orderRow = $this->orders->newQuery()->where('id', $orderId)->first();
            if ($orderRow) {
                $amount = $this->resolveOrderGrandTotal($orderRow);
            }
        }

        $check = PartnerCreditService::validateB2bPurchaseAmount(
            $partnerId,
            $amount,
            false,
            ($orderId !== null && $orderId > 0) ? $orderId : null
        );
        if (!empty($check['ok'])) {
            return;
        }

        throw ValidationException::withMessages([
            'credit' => (string) ($check['message'] ?? PartnerCreditService::CREDIT_LIMIT_REACHED_MESSAGE),
        ]);
    }

    private function normalizePoInputsForValidation(Request $request): void
    {
        $mode = app(\App\Services\OrderPoDocumentService::class)
            ->normalizeMode((string) $request->input('po_mode', 'generated'));
        $request->merge(['po_mode' => $mode]);
    }

    /**
     * B2B wizard and other flows may omit the amount input; derive it from plan/qty/GST
     * so step-1 validation passes and buildOrderPayload can persist total_amount.
     */
    private function mergeComputedOrderAmount(Request $request): void
    {
        if ($request->boolean('defer_po_save') && is_numeric($request->input('total_amount'))) {
            $amountColumn = $this->getAmountColumn();
            $total = round((float) $request->input('total_amount'), 2);
            if ($amountColumn !== null && !$request->has('amount')) {
                $request->merge(['amount' => $total]);
            }
            if ($amountColumn === 'total_amount' && !$request->has('total_amount')) {
                $request->merge(['total_amount' => $total]);
            }

            return;
        }

        if ($this->getAmountColumn() === null) {
            return;
        }

        $planId = (int) $request->input('plan_id', 0);
        $qty = (int) $request->input('quantity', $request->input('licenses', 0));
        $plans = $request->input('plans', []);
        if (is_array($plans)) {
            $base = 0.0;
            $orderType = strtolower(trim((string) $request->input('order_type', 'b2c')));
            foreach ($plans as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $linePlanId = (int) ($row['plan_id'] ?? 0);
                $lineQty = (int) ($row['quantity'] ?? $row['licenses'] ?? 0);
                if ($linePlanId <= 0 || $lineQty < 1) {
                    continue;
                }
                $base += $this->resolvePlanBaseAmount($linePlanId, $orderType, $lineQty);
            }
            if ($base > 0) {
                $grossSubtotal = round($base, 2);
                $discount = max(0, (float) $request->input('discount_amount', 0));
                $applyGst = (int) ($request->input('apply_gst', 0) ? 1 : 0) === 1;
                $gstRate = $applyGst ? max(0, (float) $request->input('gst_rate', 18)) : 0.0;
                $gst = $applyGst ? round($grossSubtotal * $gstRate / 100, 2) : 0.0;
                $request->merge([
                    'amount' => round($grossSubtotal - $discount + $gst, 2),
                    'gst_amount' => $gst,
                    'tax_amount' => $gst,
                    'subtotal' => $grossSubtotal,
                ]);

                return;
            }
            if (!empty($plans[0]) && is_array($plans[0])) {
                $planId = (int) ($plans[0]['plan_id'] ?? $planId);
                $qty = (int) ($plans[0]['quantity'] ?? $plans[0]['licenses'] ?? $qty);
            }
        }

        if ($planId <= 0) {
            return;
        }

        if ($qty < 1) {
            $qty = 1;
        }

        $orderType = strtolower(trim((string) $request->input('order_type', 'b2c')));
        $base = $this->resolvePlanBaseAmount($planId, $orderType, $qty);
        if ($base <= 0) {
            return;
        }

        $grossSubtotal = round($base, 2);
        $discount = max(0, (float) $request->input('discount_amount', 0));
        $applyGst = (int) ($request->input('apply_gst', 0) ? 1 : 0) === 1;
        $gstRate = $applyGst ? max(0, (float) $request->input('gst_rate', 18)) : 0.0;
        $gst = $applyGst ? round($grossSubtotal * $gstRate / 100, 2) : 0.0;
        $request->merge([
            'amount' => round($grossSubtotal - $discount + $gst, 2),
            'gst_amount' => $gst,
            'tax_amount' => $gst,
            'subtotal' => $grossSubtotal,
        ]);
    }

    private function finalizeOrderAmountFromBase(Request $request, float $base): float
    {
        $grossSubtotal = max(0, round($base, 2));
        $discount = max(0, (float) $request->input('discount_amount', 0));
        $applyGst = (int) ($request->input('apply_gst', 0) ? 1 : 0) === 1;
        $gstRate = $applyGst ? max(0, (float) $request->input('gst_rate', 18)) : 0.0;
        // Match partner cart: GST on gross subtotal, then subtract discount.
        $gst = $applyGst ? round($grossSubtotal * $gstRate / 100, 2) : 0.0;

        return round($grossSubtotal - $discount + $gst, 2);
    }

    /**
     * Map B2B wizard PO partial field names (po_name, po_email, …) to po_manual_* keys.
     */
    private function isOrderPoSubmitted(int $orderId): bool
    {
        if ($orderId <= 0) {
            return false;
        }

        $order = $this->orders->newQuery()->where('id', $orderId)->first();
        if (!$order) {
            return false;
        }

        if (true) {
            $cols = $this->orderPoDetailColumns();
            if (in_array('order_id', $cols, true) && in_array('journey_status', $cols, true)) {
                $poRow = $this->orderPoDetails->newQuery()->where('order_id', $orderId)->first();
                if ($poRow && trim((string) ($poRow->journey_status ?? '')) !== '') {
                    $journey = strtolower(trim((string) $poRow->journey_status));

                    return in_array($journey, ['po_done', 'aadhar_done', 'otp_done', 'placed'], true);
                }
            }
        }

        if (true) {
            $journey = strtolower(trim((string) ($order->po_journey_status ?? '')));

            return in_array($journey, ['po_done', 'aadhar_done', 'otp_done', 'placed'], true);
        }

        return false;
    }

    /**
     * Return an error message when Aadhaar OTP is required but not completed; null when activation is allowed.
     */
    private function orderAadhaarBlocksActivation(int $orderId): ?string
    {
        // Superadmin order wizard does not require Aadhaar OTP verification.
        return null;
    }

    /**
     * Block activation when this order would push used credit over partners.credit_limit.
     * Processing/pending orders do not consume allocation until they become Active.
     */
    private function partnerCreditBlocksActivation(?object $order, int $orderId = 0): ?string
    {
        if (!$order && $orderId > 0) {
            $order = $this->orders->newQuery()->where('id', $orderId)->first();
        }
        if (!$order) {
            return null;
        }
        if (strtolower(trim((string) ($order->order_type ?? 'b2b'))) !== 'b2b') {
            return null;
        }

        $partnerId = (int) ($order->partner_id ?? 0);
        if ($partnerId <= 0) {
            return null;
        }

        $amount = round((float) $this->resolveOrderGrandTotal($order), 2);
        if ($amount <= 0.005) {
            $amount = round((float) ($order->credit_used ?? 0), 2);
        }

        $check = PartnerCreditService::validateB2bPurchaseAmount(
            $partnerId,
            $amount,
            false,
            (int) ($order->id ?? $orderId)
        );
        if (!empty($check['ok'])) {
            return null;
        }

        $limit = round((float) ($check['credit_limit'] ?? 0), 2);
        $used = round((float) ($check['credit_used'] ?? 0), 2);
        $remaining = round((float) ($check['remaining_limit'] ?? 0), 2);
        $base = (string) ($check['message'] ?? PartnerCreditService::CREDIT_LIMIT_REACHED_MESSAGE);

        return $base
            . ' Credit limit ₹' . number_format($limit, 2)
            . ', used ₹' . number_format($used, 2)
            . ', remaining ₹' . number_format($remaining, 2)
            . ', this order ₹' . number_format($amount, 2) . '.';
    }

    /**
     * Stamp PO manual fields + journey on orders row (wizard step 2).
     */
    public function syncOrderPoJourneyOnOrdersTable(int $orderId, string $journeyStatus, ?Request $request = null): void
    {
        if ($orderId <= 0) {
            return;
        }

        $orderColumns = $this->orderColumns();
        $update = [];

        if (in_array('po_journey_status', $orderColumns, true)) {
            $update['po_journey_status'] = $journeyStatus;
        }

        if ($request !== null) {
            $fieldMap = [
                'po_manual_name' => 'po_manual_name',
                'po_manual_email' => 'po_manual_email',
                'po_manual_mobile' => 'po_manual_mobile',
                'po_manual_gstin' => 'po_manual_gstin',
                'po_manual_company_name' => 'po_manual_company_name',
                'po_manual_company_address' => 'po_manual_company_address',
                'po_manual_city' => 'po_manual_city',
                'po_manual_state' => 'po_manual_state',
                'po_manual_country' => 'po_manual_country',
                'po_manual_pincode' => 'po_manual_pincode',
            ];
            foreach ($fieldMap as $column => $inputKey) {
                if (in_array($column, $orderColumns, true) && $request->has($inputKey)) {
                    $val = $request->input($inputKey);
                    $update[$column] = is_string($val) ? (trim($val) !== '' ? trim($val) : null) : $val;
                }
            }
        }

        if ($update === []) {
            return;
        }

        if (in_array('updated_at', $orderColumns, true)) {
            $update['updated_at'] = now();
        }

        $this->orders->newQuery()->where('id', $orderId)->update($update);
    }

    /**
     * @param  array<string, string>  $prefill
     */
    public function syncWizardPoFromCustomerPrefill(int $orderId, array $prefill): void
    {
        if ($orderId <= 0 || $prefill === []) {
            return;
        }

        $request = Request::create('/', 'POST', [
            'po_mode' => 'generated',
            'po_journey_status' => 'po_done',
            'po_name' => $prefill['name'] ?? '',
            'po_email' => $prefill['email'] ?? '',
            'po_mobile' => $prefill['mobile'] ?? '',
            'po_gstin' => $prefill['gstin'] ?? '',
            'po_company_name' => $prefill['company'] ?? '',
            'po_company_address' => $prefill['address'] ?? '',
            'po_city' => $prefill['city'] ?? '',
            'po_state' => $prefill['state'] ?? '',
            'po_country' => $prefill['country'] ?? 'India',
            'po_pincode' => $prefill['pincode'] ?? '',
        ]);

        $this->normalizePoInputsForValidation($request);
        $this->mapB2bWizardPoFieldAliases($request);
        $this->upsertOrderPoDetails($orderId, $request);
        $this->syncOrderPoJourneyOnOrdersTable($orderId, 'po_done', $request);
    }

    private function mapB2bWizardPoFieldAliases(Request $request): void
    {
        $aliases = [
            'po_manual_name' => 'po_name',
            'po_manual_email' => 'po_email',
            'po_manual_mobile' => 'po_mobile',
            'po_manual_gstin' => 'po_gstin',
            'po_manual_company_name' => 'po_company_name',
            'po_manual_company_address' => 'po_company_address',
            'po_manual_city' => 'po_city',
            'po_manual_state' => 'po_state',
            'po_manual_country' => 'po_country',
            'po_manual_pincode' => 'po_pincode',
        ];

        foreach ($aliases as $target => $source) {
            if (!$request->filled($target) && $request->has($source)) {
                $request->merge([$target => $request->input($source)]);
            }
        }
    }

    private function syncB2bOrderWalletLedger(int $orderId, array $orderPayload): void
    {
        PartnerCreditService::syncB2bOrderWalletLedger($orderId, $orderPayload);
    }

    private function defaultInvoiceHsnSac(): string
    {
        return '997331';
    }

    private function resolvePlanRowForOrder(object $order): ?object
    {
        if (empty($order->plan_id)) {
            return null;
        }

        return $this->plans->newQuery()->where('id', (int) $order->plan_id)->first();
    }

    private function resolveHsnFromPlan(?object $plan): string
    {
        if (!$plan) {
            return $this->defaultInvoiceHsnSac();
        }
        $cols = $this->planColumns();
        foreach (['hsn_sac', 'hsn', 'sac', 'hsn_code'] as $c) {
            if (in_array($c, $cols, true) && isset($plan->{$c})) {
                $v = trim((string) $plan->{$c});
                if ($v !== '') {
                    return $v;
                }
            }
        }

        return $this->defaultInvoiceHsnSac();
    }

    private function buildRichInvoiceLineLabel(object $order, ?object $plan): string
    {
        $parts = [];
        $title = trim((string) ($order->resolved_plan_name ?? ($plan && isset($plan->name) ? (string) $plan->name : '')));
        if ($title === '') {
            $title = 'Service';
        }
        $parts[] = $title;

        $domain = trim((string) ($order->domain_name ?? ''));
        if ($domain !== '' && $domain !== '-') {
            $parts[] = 'Domain: ' . $domain;
        }

        $orderItem = $this->orderItems->newQuery()
            ->where('order_id', $order->id)
            ->orderBy('id')
            ->first();

        $startRaw = $orderItem->subscription_start_date ?? $order->subscription_start_date ?? null;
        $endRaw = $orderItem->subscription_end_date ?? $order->subscription_end_date ?? null;

        $parts[] = 'Billing Cycle: ' . date('d-m-Y', strtotime($startRaw)) . ' To ' . date('d-m-Y', strtotime($endRaw));


        // $qty = max(1, (int) ($order->quantity ?? 1));
        // $parts[] = 'Quantity / seats: ' . $qty;

        return implode("\n", $parts);
    }

    /**
     * Copy order header amounts for invoice_mst (orders table is source of truth).
     *
     * @return array{
     *   subtotal: float,
     *   tax_amount: float,
     *   total_amount: float,
     *   net_amt: float,
     *   amount: float,
     *   taxable_total: float,
     *   cgst_total: float,
     *   sgst_total: float,
     *   igst_total: float,
     *   discount_amount: float,
     *   gross_subtotal: float,
     *   promo_code: string,
     *   tax_type: string,
     *   status: string
     * }
     */
    private function invoiceAmountsFromOrder(object $order): array
    {
        $subtotal = round((float) ($order->subtotal ?? 0), 2);
        $discountAmount = max(0.0, round((float) ($order->discount_amount ?? 0), 2));
        $remaining = max(0.0, round((float) ($order->pro_rata_adjustment ?? 0), 2));
        if ($remaining <= 0 && $discountAmount > 0 && $this->orderPromoCode($order) === '') {
            $category = strtolower(trim((string) ($order->order_category ?? '')));
            if (
                in_array($category, ['upgrade', 'upgraded', 'partial_upgrade', 'downgrade', 'downgraded'], true)
                || (int) ($order->parent_order_id ?? 0) > 0
            ) {
                $remaining = $discountAmount;
            }
        }
        $coupon = $discountAmount;
        if ($remaining > 0 && abs($coupon - $remaining) < 0.01) {
            $coupon = 0.0;
        } elseif ($remaining > 0 && $coupon >= $remaining) {
            $coupon = max(0.0, round($coupon - $remaining, 2));
        }
        $taxAmount = round((float) ($order->gst_amount ?? $order->tax_amount ?? 0), 2);
        $totalAmount = round((float) ($order->total_amount ?? 0), 2);
        if ($totalAmount <= 0) {
            $totalAmount = round((float) ($order->resolved_amount ?? 0), 2);
        }

        $cgstTotal = $this->orderStoredAmount($order, ['cgst_total', 'cgst_amount', 'cgst']);
        $sgstTotal = $this->orderStoredAmount($order, ['sgst_total', 'sgst_amount', 'sgst']);
        $igstTotal = $this->orderStoredAmount($order, ['igst_total', 'igst_amount', 'igst']);

        $taxType = strtolower(trim((string) ($order->tax_type ?? '')));
        if (($cgstTotal <= 0 && $sgstTotal <= 0 && $igstTotal <= 0) && $taxAmount > 0) {
            $split = GstSplit::split(
                $taxAmount,
                (string) ($order->po_manual_gstin ?? $order->customer_gstin ?? '')
            );
            $cgstTotal = $split['cgst'];
            $sgstTotal = $split['sgst'];
            $igstTotal = $split['igst'];
            $taxType = $split['tax_type'];
        } elseif ($taxType === '' && ($cgstTotal > 0 || $sgstTotal > 0)) {
            $taxType = 'cgst_sgst';
        } elseif ($taxType === '' && $igstTotal > 0) {
            $taxType = 'igst';
        }

        if ($subtotal > 0) {
            $taxableTotal = max(0.0, round($subtotal - $remaining - $coupon, 2));
        } else {
            $taxableTotal = max(0.0, round($totalAmount - $taxAmount, 2));
        }
        $grossSubtotal = $subtotal > 0 ? $subtotal : max(0.0, round($taxableTotal + $remaining + $coupon, 2));

        $status = strtolower(trim((string) ($order->status ?? $order->resolved_status ?? '')));
        if (in_array($status, ['cancelled', 'failed', 'rejected', 'canceled', 'void'], true)) {
            $invoiceStatus = 'failed';
        } else {
            $invoiceStatus = 'pending';
        }

        return [
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'net_amt' => $totalAmount,
            'amount' => $totalAmount,
            'taxable_total' => $taxableTotal,
            'gross_subtotal' => $grossSubtotal,
            'discount_amount' => $discountAmount,
            'promo_code' => $this->orderPromoCode($order),
            'cgst_total' => $cgstTotal,
            'sgst_total' => $sgstTotal,
            'igst_total' => $igstTotal,
            'tax_type' => $taxType,
            'status' => $invoiceStatus,
        ];
    }

    private function orderPromoCode(object $order): string
    {
        foreach (['promo_code', 'promocode', 'coupon_code', 'coupen', 'coupon'] as $key) {
            if (!isset($order->{$key})) {
                continue;
            }
            $promo = strtoupper(trim((string) $order->{$key}));
            if ($promo !== '') {
                return $promo;
            }
        }

        return '';
    }

    private function orderStoredAmount(object $row, array $candidates): float
    {
        foreach ($candidates as $field) {
            if (isset($row->{$field}) && is_numeric($row->{$field})) {
                return round((float) $row->{$field}, 2);
            }
        }

        return 0.0;
    }

    /**
     * @param array<string, float|string> $amounts
     */
    private function mergeInvoiceMasterAmountColumns(array $target, array $amounts): array
    {
        if (isset($amounts['order_id'])) {
            $target['order_id'] = (int) $amounts['order_id'];
        }
        $target['net_amt'] = (float) ($amounts['net_amt'] ?? 0);
        $target['taxable_total'] = (float) ($amounts['taxable_total'] ?? 0);
        $target['cgst_total'] = (float) ($amounts['cgst_total'] ?? 0);
        $target['sgst_total'] = (float) ($amounts['sgst_total'] ?? 0);
        $target['igst_total'] = (float) ($amounts['igst_total'] ?? 0);
        if (!empty($amounts['tax_type'])) {
            $target['tax_type'] = (string) $amounts['tax_type'];
        }
        if (!empty($amounts['status'])) {
            $s = strtolower(trim((string) $amounts['status']));
            if ($s === 'paid') {
                $target['status'] = 'paid';
            } elseif ($s === 'failed' || in_array($s, ['cancelled', 'canceled', 'rejected', 'void'], true)) {
                $target['status'] = 'failed';
            } else {
                $target['status'] = 'pending';
            }
        }

        return $target;
    }

    /**
     * Build invoice_sub line(s) by copying order_items (no amount recalculation).
     *
     * @return array<int, array{invoice_no:string,hsn_sac:string,qty:float,cnt_rate:float,taxable_amt:float,tax_type:string,cgst:float,sgst:float,igst:float,nettotal:float}>
     */
    private function buildInvoiceSubItemsFromOrder(object $order): array
    {
        $plan = $this->resolvePlanRowForOrder($order);
        $planHsn = $this->resolveHsnFromPlan($plan);
        $taxType = strtolower(trim((string) ($order->tax_type ?? '')));

        if (true) {
            $itemColumns = $this->orderItemColumns();
            if (in_array('order_id', $itemColumns, true)) {
                $rows = $this->orderItems->newQuery()
                    ->where('order_id', (int) $order->id)
                    ->orderBy('id')
                    ->get();
                if ($rows->isNotEmpty()) {
                    $itemNameColumn = in_array('item_name', $itemColumns, true) ? 'item_name' : (in_array('name', $itemColumns, true) ? 'name' : null);
                    $descriptionColumn = in_array('description', $itemColumns, true) ? 'description' : null;
                    $qtyColumn = in_array('quantity', $itemColumns, true) ? 'quantity' : null;
                    $unitPriceColumn = in_array('unit_price', $itemColumns, true) ? 'unit_price' : (in_array('price', $itemColumns, true) ? 'price' : null);
                    $totalColumn = in_array('total_amount', $itemColumns, true) ? 'total_amount' : (in_array('amount', $itemColumns, true) ? 'amount' : null);
                    $hsnColumn = in_array('hsn_sac', $itemColumns, true)
                        ? 'hsn_sac'
                        : (in_array('hsn', $itemColumns, true) ? 'hsn' : (in_array('sac', $itemColumns, true) ? 'sac' : null));

                    $out = [];
                    foreach ($rows as $row) {
                        $q = max(1, (int) ($qtyColumn ? ($row->{$qtyColumn} ?? 1) : 1));
                        $unit = round((float) ($unitPriceColumn ? ($row->{$unitPriceColumn} ?? 0) : 0), 2);
                        $fromUnit = round($unit * $q, 2);
                        $fromStored = round((float) ($totalColumn ? ($row->{$totalColumn} ?? 0) : 0), 2);
                        $lineTotal = $fromUnit > 0 ? $fromUnit : $fromStored;

                        $lineTaxable = $this->orderStoredAmount($row, ['taxable_amt', 'taxable_amount', 'subtotal']);
                        if ($lineTaxable <= 0) {
                            $lineTaxable = $lineTotal;
                        }

                        $lineTaxType = strtolower(trim((string) ($row->tax_type ?? '')));
                        if ($lineTaxType === '') {
                            $lineTaxType = $taxType;
                        }

                        $name = (string) ($itemNameColumn ? ($row->{$itemNameColumn} ?? 'Order Item') : 'Order Item');
                        $desc = (string) ($descriptionColumn ? ($row->{$descriptionColumn} ?? '') : '');
                        $label = $name . ($desc !== '' ? "\n" . $desc : '');

                        $rowHsn = $hsnColumn ? trim((string) ($row->{$hsnColumn} ?? '')) : '';
                        if ($rowHsn === '') {
                            $rowHsn = $planHsn;
                        }

                        $out[] = [
                            'invoice_no' => $label,
                            'hsn_sac' => $rowHsn,
                            'qty' => (float) $q,
                            'cnt_rate' => $unit,
                            'taxable_amt' => $lineTaxable,
                            'tax_type' => $lineTaxType,
                            'cgst' => $this->orderStoredAmount($row, ['cgst', 'cgst_amount']),
                            'sgst' => $this->orderStoredAmount($row, ['sgst', 'sgst_amount']),
                            'igst' => $this->orderStoredAmount($row, ['igst', 'igst_amount']),
                            'nettotal' => $lineTotal > 0 ? $lineTotal : $lineTaxable,
                        ];
                    }

                    return $out;
                }
            }
        }

        $qty = max(1, (int) ($order->quantity ?? 1));
        $orderSubtotal = round((float) ($order->subtotal ?? 0), 2);
        $lineTotal = round((float) ($order->total_amount ?? $order->resolved_amount ?? 0), 2);
        $unit = round((float) ($order->price ?? $order->unit_price ?? 0), 2);

        return [[
            'invoice_no' => $this->buildRichInvoiceLineLabel($order, $plan),
            'hsn_sac' => $planHsn,
            'qty' => (float) $qty,
            'cnt_rate' => $unit,
            'taxable_amt' => $orderSubtotal > 0 ? $orderSubtotal : $lineTotal,
            'tax_type' => $taxType,
            'cgst' => $this->orderStoredAmount($order, ['cgst', 'cgst_amount', 'cgst_total']),
            'sgst' => $this->orderStoredAmount($order, ['sgst', 'sgst_amount', 'sgst_total']),
            'igst' => $this->orderStoredAmount($order, ['igst', 'igst_amount', 'igst_total']),
            'nettotal' => $lineTotal > 0 ? $lineTotal : $orderSubtotal,
        ]];
    }

    /**
     * Create invoice_mst + invoice_sub when an order is placed (and link order_id / order_no when columns exist).
     */
    private function ensureGstInvoiceForOrder(int $orderId): void
    {
        $order = $this->findOrder($orderId);
        if (!$order || empty($order->user_id)) {
            return;
        }

        if ($this->invoiceMst->newQuery()->where('order_id', $orderId)->exists()) {
            return;
        }

        $lines = $this->buildInvoiceSubItemsFromOrder($order);
        $amounts = $this->invoiceAmountsFromOrder($order);
        $taxType = (string) ($amounts['tax_type'] ?? '');

        $payloadItems = [];
        foreach ($lines as $ln) {
            $payloadItems[] = [
                'description' => (string) ($ln['invoice_no'] ?? ''),
                'hsn_sac' => (string) ($ln['hsn_sac'] ?? $this->defaultInvoiceHsnSac()),
                'qty' => (float) ($ln['qty'] ?? 1),
                'cnt_rate' => (float) ($ln['cnt_rate'] ?? 0),
                'taxable_amt' => (float) ($ln['taxable_amt'] ?? 0),
                'tax_type' => (string) ($ln['tax_type'] ?? $taxType),
                'cgst' => (float) ($ln['cgst'] ?? 0),
                'sgst' => (float) ($ln['sgst'] ?? 0),
                'igst' => (float) ($ln['igst'] ?? 0),
                'nettotal' => (float) ($ln['nettotal'] ?? 0),
            ];
        }

        $payload = [
            'cust_id' => (int) $order->user_id,
            'bill_date' => !empty($order->created_at)
                ? Carbon::parse($order->created_at)->toDateString()
                : now()->toDateString(),
            'net_amt' => $amounts['net_amt'],
            'taxable_total' => $amounts['taxable_total'],
            'tax_type' => $taxType,
            'cgst_total' => $amounts['cgst_total'],
            'sgst_total' => $amounts['sgst_total'],
            'igst_total' => $amounts['igst_total'],
            'status' => $amounts['status'],
            'action' => 'add_order',
            'items' => $payloadItems,
        ];
        $partnerId = (int) ($order->partner_id ?? 0);
        if ($partnerId <= 0 && !empty($order->user_id)) {
            $partnerId = (int) $this->customers->newQuery()->where('id', (int) $order->user_id)->value('partner_id');
        }
        if ($partnerId > 0) {
            $payload['partner_id'] = $partnerId;
        }

        $invoiceId = null;
        try {
            /** @var InvoiceService $invoiceService */
            $invoiceService = app(InvoiceService::class);
            $created = $invoiceService->createInvoice($payload);
            $invoiceId = (int) ($created['id'] ?? 0);
        } catch (\Throwable $e) {
            Log::warning('GST invoice create via InvoiceService failed; using fallback insert.', [
                'order_id' => $orderId,
                'message' => $e->getMessage(),
            ]);
            $invoiceId = $this->insertGstInvoiceFallback($orderId, $order, $lines);
        }

        if ($invoiceId <= 0) {
            return;
        }

        $link = ['order_id' => $orderId];
        $partnerId = (int) ($order->partner_id ?? 0);
        if ($partnerId <= 0 && !empty($order->user_id)) {
            $partnerId = (int) $this->customers->newQuery()->where('id', (int) $order->user_id)->value('partner_id');
        }
        if ($partnerId > 0) {
            $link['partner_id'] = $partnerId;
        }
        $link = $this->mergeInvoiceMasterAmountColumns($link, $amounts);
        $link['updated_at'] = now();
        $this->invoiceMst->newQuery()->where('id', $invoiceId)->update($link);
    }

    /**
     * Minimal insert when InvoiceService cannot run (missing FY, invoice_prefix, etc.).
     *
     * @param array<int, array{invoice_no:string,hsn_sac:string,qty:float,cnt_rate:float,taxable_amt:float,tax_type:string,cgst:float,sgst:float,igst:float,nettotal:float}> $lines
     */

    private function insertGstInvoiceFallback(int $orderId, object $order, array $lines): int
    {
        $amounts = $this->invoiceAmountsFromOrder($order);
        $taxType = (string) ($amounts['tax_type'] ?? '');

        $billNo = 'INV/' . preg_replace('/[^A-Z0-9\-_]/i', '', (string) ($order->order_no ?? ('ORD' . $orderId))) . '/' . date('His');
        $billDate = !empty($order->created_at)
            ? Carbon::parse($order->created_at)->toDateString()
            : now()->toDateString();

        $partnerId = (int) ($order->partner_id ?? 0);
        if ($partnerId <= 0 && !empty($order->user_id)) {
            $partnerId = (int) $this->customers->newQuery()->where('id', (int) $order->user_id)->value('partner_id');
        }

        $master = [
            'bill_no_full' => $billNo,
            'bill_date' => $billDate,
            'cust_id' => (int) $order->user_id,
            'action_type' => 'add_order',
            'order_id' => $orderId,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if ($partnerId > 0) {
            $master['partner_id'] = $partnerId;
        }
        $master = $this->mergeInvoiceMasterAmountColumns($master, array_merge($amounts, ['order_id' => $orderId]));

        $invoiceId = (int) $this->invoiceMst->newQuery()->insertGetId($master);

        app(InvoiceService::class)->ensureInvoiceMoneyColumnsNumeric();

        foreach ($lines as $ln) {
            $this->invoiceSubs->newQuery()->insert([
                'invoice_mst_id' => $invoiceId,
                'invoice_no' => (string) ($ln['invoice_no'] ?? ''),
                'hsn_sac' => (string) ($ln['hsn_sac'] ?? $this->defaultInvoiceHsnSac()),
                'qty' => (float) ($ln['qty'] ?? 1),
                'cnt_rate' => (float) ($ln['cnt_rate'] ?? 0),
                'taxable_amt' => (float) ($ln['taxable_amt'] ?? 0),
                'tax_type' => (string) ($ln['tax_type'] ?? $taxType),
                'cgst' => (float) ($ln['cgst'] ?? 0),
                'sgst' => (float) ($ln['sgst'] ?? 0),
                'igst' => (float) ($ln['igst'] ?? 0),
                'nettotal' => (float) ($ln['nettotal'] ?? 0),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $invoiceId;
    }

    /**
     * Public entry for API flows (e.g. Aadhaar OTP verify) — sync invoice_mst from orders.
     */
    public function syncInvoiceAmountsFromOrder(int $orderId): void
    {
        $this->syncInvoiceForUpdatedOrder($orderId);
    }

    private function syncInvoiceForUpdatedOrder(int $orderId): void
    {
        $order = $this->findOrder($orderId);
        if (!$order) {
            return;
        }

        $invoiceMstRow = $this->latestTaxInvoiceMstForOrder($orderId);
        $invoiceId = $invoiceMstRow ? (int) $invoiceMstRow->id : 0;

        if ($invoiceId <= 0) {
            $this->ensureGstInvoiceForOrder($orderId);
            $invoiceMstRow = $this->latestTaxInvoiceMstForOrder($orderId);
            $invoiceId = $invoiceMstRow ? (int) $invoiceMstRow->id : 0;
        }

        if (empty($invoiceId)) {
            return;
        }

        $lines = $this->buildInvoiceSubItemsFromOrder($order);
        $amounts = $this->invoiceAmountsFromOrder($order);
        $taxType = (string) ($amounts['tax_type'] ?? '');
        if (count($lines) === 1) {
            $lines[0]['taxable_amt'] = (float) ($amounts['taxable_total'] ?? 0);
            $lines[0]['nettotal'] = (float) ($amounts['net_amt'] ?? 0);
            $lines[0]['cgst'] = (float) ($amounts['cgst_total'] ?? 0);
            $lines[0]['sgst'] = (float) ($amounts['sgst_total'] ?? 0);
            $lines[0]['igst'] = (float) ($amounts['igst_total'] ?? 0);
            $lines[0]['tax_type'] = $taxType;
        }
        $billDate = !empty($order->created_at) ? Carbon::parse($order->created_at)->toDateString() : now()->toDateString();

        $mstUpdate = [
            'bill_date' => $billDate,
            'cust_id' => (int) ($order->user_id ?? 0),
        ];
        $mstUpdate = $this->mergeInvoiceMasterAmountColumns($mstUpdate, $amounts);
        $partnerId = (int) ($order->partner_id ?? 0);
        if ($partnerId <= 0 && !empty($order->user_id)) {
            $partnerId = (int) $this->customers->newQuery()->where('id', (int) $order->user_id)->value('partner_id');
        }
        if ($partnerId > 0) {
            $mstUpdate['partner_id'] = $partnerId;
        }
        $mstUpdate['updated_at'] = now();
        $this->invoiceMst->newQuery()->where('id', (int) $invoiceId)->update($mstUpdate);

        $this->invoiceSubs->newQuery()->where('invoice_mst_id', (int) $invoiceId)->delete();

        foreach ($lines as $ln) {
            $this->invoiceSubs->newQuery()->insert([
                'invoice_mst_id' => (int) $invoiceId,
                'invoice_no' => (string) ($ln['invoice_no'] ?? ''),
                'hsn_sac' => (string) ($ln['hsn_sac'] ?? $this->defaultInvoiceHsnSac()),
                'qty' => (float) ($ln['qty'] ?? 1),
                'cnt_rate' => (float) ($ln['cnt_rate'] ?? 0),
                'taxable_amt' => (float) ($ln['taxable_amt'] ?? 0),
                'tax_type' => (string) ($ln['tax_type'] ?? $taxType),
                'cgst' => (float) ($ln['cgst'] ?? 0),
                'sgst' => (float) ($ln['sgst'] ?? 0),
                'igst' => (float) ($ln['igst'] ?? 0),
                'nettotal' => (float) ($ln['nettotal'] ?? 0),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function destroy(int $id)
    {
        $this->orders->newQuery()->where('id', $id)->delete();

        return redirect()
            ->route('superadmin.orders.index')
            ->with('success', 'Order deleted successfully.');
    }
    public function orderAction(Request $request, int $id)
    {
        $order = $this->findOrder($id);
        abort_if(!$order, 404);

        $action = strtolower(trim((string) $request->input('action', '')));

        // Handle DELETE separately
        if ($action === 'delete') {
            return $this->destroy($id);
        }

        // Map actions to statuses
        $statusMap = [
            'active' => 'completed',
            'accept' => 'completed',
            'cancel' => 'cancelled',
            'fraud' => 'fraud',
        ];

        if (!isset($statusMap[$action])) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid order action selected.'
                ], 400);
            }
            return redirect()
                ->route('superadmin.orders.details', $id)
                ->with('error', 'Invalid order action selected.');
        }

        $newStatus = $statusMap[$action];
        $orderBefore = $this->findOrder($id);

        if ($newStatus === 'cancelled') {
            $currentStatus = strtolower(trim((string) ($orderBefore->status ?? '')));
            $isActiveNow = in_array($currentStatus, ['completed', 'paid', 'success', 'active'], true);
            // Superadmin: cancel only after Active. Partner panel may cancel within 6-day creation window.
            if (!$isActiveNow && !$request->boolean('partner_panel_cancel')) {
                $message = 'Cancel is allowed only after the order is Active.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $message,
                    ], 422);
                }

                return redirect()
                    ->route('superadmin.orders.details', $id)
                    ->with('error', $message);
            }

            $cancelBlockMessage = $this->orderCancellationBlockMessage($id);
            // Partner panel already enforced Google + 6-day creation window; skip admin active-window block for partner cancels.
            if ($cancelBlockMessage !== null && !$request->boolean('partner_panel_cancel')) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $cancelBlockMessage,
                    ], 422);
                }

                return redirect()
                    ->route('superadmin.orders.details', $id)
                    ->with('error', $cancelBlockMessage);
            }
        }

        // Handle activation
        if ($action === 'active') {
            // ✅ FIX: Allow both pending AND processing orders to be activated
            if (!in_array($orderBefore->status, ['pending', 'processing'])) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Only pending or processing orders can be activated.'
                    ], 400);
                }
                return redirect()
                    ->route('superadmin.orders.details', $id)
                    ->with('error', 'Only pending or processing orders can be activated.');
            }

            // Skip wallet debit only when this order was already charged (e.g. provisioning).
            // Credit-limit check still always runs — processing credit_used does not consume
            // allocation until the order is Active.
            $priorCreditUsed = round((float) ($orderBefore->credit_used ?? 0), 2);
            $skipWalletDebit = $priorCreditUsed > 0.01
                && strtolower(trim((string) ($orderBefore->status ?? ''))) === 'processing';

            $creditBlock = $this->partnerCreditBlocksActivation($orderBefore, $id);
            if ($creditBlock !== null) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $creditBlock,
                    ], 422);
                }

                return redirect()
                    ->route('superadmin.orders.details', $id)
                    ->with('error', $creditBlock);
            }

            try {
                // Update status to processing first (shows loader state)
                $this->orders->newQuery()
                    ->where('id', $id)
                    ->update([
                        'status' => 'processing',
                        'updated_at' => now(),
                    ]);

                // Sync wallet/credit for B2B orders.
                // PartnerCreditService only deducts when payload status is completed/active.
                $partnerId = (int) ($orderBefore->partner_id ?? 0);
                if ($partnerId > 0 && strtolower(trim((string) ($orderBefore->order_type ?? 'b2b'))) === 'b2b') {
                    try {
                        $orderRow = $this->orders->newQuery()->where('id', $id)->first() ?: $orderBefore;
                        $grandTotal = $this->resolveOrderGrandTotal($orderRow);
                        $currentCreditUsed = round((float) ($orderRow->credit_used ?? 0), 2);
                        if ($grandTotal > 0 && (
                            $currentCreditUsed <= 0.01
                            || abs($currentCreditUsed - $grandTotal) > 0.01
                        )) {
                            $this->orders->newQuery()->where('id', $id)->update([
                                'credit_used' => $grandTotal,
                                'updated_at' => now(),
                            ]);
                            $orderRow = $this->orders->newQuery()->where('id', $id)->first() ?: $orderRow;
                        }

                        $walletPayload = (array) $orderRow;
                        $walletPayload['status'] = 'completed';
                        $walletPayload['credit_used'] = round((float) ($orderRow->credit_used ?? $grandTotal), 2);

                        $creditBlock = $this->partnerCreditBlocksActivation($orderRow, $id);
                        if ($creditBlock !== null) {
                            throw ValidationException::withMessages([
                                'credit' => $creditBlock,
                            ]);
                        }

                        if (!$skipWalletDebit) {
                            $this->syncB2bOrderWalletLedger($id, $walletPayload);
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Order activation: wallet sync failed', [
                            'order_id' => $id,
                            'error' => $e->getMessage(),
                        ]);
                        $this->orders->newQuery()
                            ->where('id', $id)
                            ->update([
                                'status' => $orderBefore->status,
                                'credit_used' => $priorCreditUsed,
                                'updated_at' => now(),
                            ]);

                        $walletError = $e instanceof ValidationException
                            ? (collect($e->errors())->flatten()->first() ?: PartnerCreditService::CREDIT_LIMIT_REACHED_MESSAGE)
                            : ($e->getMessage() ?: PartnerCreditService::CREDIT_LIMIT_REACHED_MESSAGE);

                        if ($request->ajax() || $request->wantsJson()) {
                            return response()->json([
                                'success' => false,
                                'message' => $walletError,
                            ], 422);
                        }

                        return redirect()
                            ->route('superadmin.orders.details', $id)
                            ->with('error', $walletError);
                    }
                }

                // Finalize status first so the UI can respond quickly.
                $this->orders->newQuery()
                    ->where('id', $id)
                    ->update([
                        'status' => 'completed',
                        'updated_at' => now(),
                    ]);

                // Heavy PO/invoice work + notifications after HTTP response.
                $this->deferOrderActivationSideEffects($id);

                $message = 'Order #' . ($orderBefore->order_no ?? $id) . ' has been activated successfully.';

                if ($request->ajax() || $request->wantsJson()) {
                    // ✅ Return full order data for real-time UI update
                    $updatedOrder = $this->findOrder($id);
                    return response()->json([
                        'success' => true,
                        'message' => $message,
                        'order_id' => $id,
                        'status' => 'completed',
                        'order' => [
                            'id' => $updatedOrder->id,
                            'order_no' => $updatedOrder->order_no,
                            'status' => 'completed',
                            'status_label' => 'Active',
                            'status_badge' => '<span class="badge bg-success">Active</span>',
                            'customer_name' => $updatedOrder->customer_name ?? 'N/A',
                            'domain_name' => $updatedOrder->domain_name ?? '-',
                            'amount' => number_format((float) ($updatedOrder->total_amount ?? 0), 2),
                        ]
                    ]);
                }

                return redirect()
                    ->route('superadmin.orders.details', $id)
                    ->with('success', $message);
            } catch (\Throwable $e) {
                Log::error('Order activation failed', [
                    'order_id' => $id,
                    'error' => $e->getMessage()
                ]);

                // Revert status on failure
                $this->orders->newQuery()
                    ->where('id', $id)
                    ->update([
                        'status' => 'pending',
                        'updated_at' => now(),
                    ]);

                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Activation failed: ' . $e->getMessage()
                    ], 500);
                }

                return redirect()
                    ->route('superadmin.orders.details', $id)
                    ->with('error', 'Activation failed: ' . $e->getMessage());
            }
        }

        // Handle cancellation
        if ($action === 'cancel') {
            if (in_array(strtolower($orderBefore->status ?? ''), ['cancelled', 'rejected'], true)) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Order is already cancelled.'
                    ], 400);
                }
                return redirect()
                    ->route('superadmin.orders.details', $id)
                    ->with('error', 'Order is already cancelled.');
            }

            try {
                @ignore_user_abort(true);
                $cancelReason = trim((string) $request->input('rejection_reason', $request->input('order_note', '')));
                if ($cancelReason === '') {
                    $cancelReason = 'Order cancelled by admin.';
                }

                $this->orders->newQuery()
                    ->where('id', $id)
                    ->update([
                        'status' => 'cancelled',
                        'order_note' => $cancelReason,
                        'updated_at' => now(),
                    ]);

                // Issue CN before returning — the details page reloads immediately and can
                // abort afterResponse work, which previously left cancelled orders without a CN.
                $this->markInvoicesCancelledForOrder($id);
                $creditNoteIssue = $this->issueCancellationCreditNote($id, $cancelReason);
                $this->deferOrderCancellationSideEffects($id, $cancelReason, $creditNoteIssue);

                $message = 'Order #' . ($orderBefore->order_no ?? $id) . ' has been cancelled.';

                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => true,
                        'message' => $message,
                        'order_id' => $id,
                        'status' => 'cancelled',
                        'credit_note' => $creditNoteIssue['credit_note'] ?? null,
                    ]);
                }

                return redirect()
                    ->route('superadmin.orders.details', $id)
                    ->with('success', $message);
            } catch (\Throwable $e) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cancellation failed: ' . $e->getMessage()
                    ], 500);
                }
                return redirect()
                    ->route('superadmin.orders.details', $id)
                    ->with('error', 'Cancellation failed: ' . $e->getMessage());
            }
        }

        // Handle fraud
        if ($action === 'fraud') {
            try {
                $this->orders->newQuery()
                    ->where('id', $id)
                    ->update([
                        'status' => 'failed',
                        'order_note' => DB::raw("CONCAT(COALESCE(order_note, ''), ' Marked as fraud by admin on ', NOW())"),
                        'updated_at' => now(),
                    ]);

                Log::warning('Order marked as fraud', [
                    'order_id' => $id,
                    'admin_id' => auth()->id()
                ]);

                $message = 'Order #' . ($orderBefore->order_no ?? $id) . ' has been marked as fraudulent.';

                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => true,
                        'message' => $message,
                        'order_id' => $id,
                        'status' => 'failed'
                    ]);
                }

                return redirect()
                    ->route('superadmin.orders.details', $id)
                    ->with('success', $message);
            } catch (\Throwable $e) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Operation failed: ' . $e->getMessage()
                    ], 500);
                }
                return redirect()
                    ->route('superadmin.orders.details', $id)
                    ->with('error', 'Operation failed: ' . $e->getMessage());
            }
        }

        return redirect()
            ->route('superadmin.orders.details', $id)
            ->with('error', 'Invalid order action selected.');
    }

    public function show(int $id)
    {
        $data = $this->buildInvoiceData($id);
        abort_if(!$data, 404);

        $creditNote = app(OrderCancellationCreditNoteService::class)
            ->resolveCreditNotesByOrderIds([(int) $id])[(int) $id] ?? null;

        $order = $data['order'];
        $creditNoteSkipReason = null;
        if ($creditNote === null) {
            $orderStatus = strtolower(trim((string) ($order->resolved_status ?? $order->status ?? '')));
            if (in_array($orderStatus, ['cancelled', 'canceled', 'rejected'], true)) {
                try {
                    $calc = app(OrderCancellationCreditNoteService::class)->calculateProrataRefund($order);
                    $creditNoteSkipReason = app(OrderCancellationCreditNoteService::class)
                        ->zeroAmountSkipMessage($calc);
                    if (($calc['credit_note_amount'] ?? 0) > 0.005) {
                        $creditNoteSkipReason = 'Credit note was not created when this order was cancelled. You can generate it now.';
                    }
                } catch (\Throwable $e) {
                    $creditNoteSkipReason = 'Credit note was not created when this order was cancelled.';
                }
            }
        }
        $providerId = (int) ($order->provider_id ?? 0);
        if ($providerId <= 0 && !empty($data['items'][0]['provider_id'])) {
            $providerId = (int) $data['items'][0]['provider_id'];
        }
        $policyContext = $this->buildOrderCancellationPolicyContext((int) $id, $providerId > 0 ? $providerId : null);

        return view('superadmin.orders.details', [
            'title' => 'Order Details',
            'order' => $order,
            'invoiceItems' => $data['items'],
            'creditNote' => $creditNote,
            'creditNoteSkipReason' => $creditNoteSkipReason,
            'orderCanBeCancelled' => (bool) ($policyContext['can_cancel'] ?? true),
            'orderCancellationBlockReason' => $policyContext['block_reason'] ?? null,
            'orderCancellationDeadline' => $policyContext['deadline_label'] ?? null,
            'orderPricing' => [
                'subtotal' => round((float) ($data['gross_subtotal'] ?? $data['subtotal'] ?? 0), 2),
                'discount_amount' => round((float) ($data['coupon_discount'] ?? 0), 2),
                'pro_rata_adjustment' => round((float) ($data['pro_rata_adjustment'] ?? 0), 2),
                'remaining_value' => round((float) ($data['pro_rata_adjustment'] ?? 0), 2),
                'gst_amount' => round((float) ($data['gst_total'] ?? 0), 2),
                'taxable_amount' => round((float) ($data['taxable_total'] ?? 0), 2),
                'grand_total' => round((float) ($data['total'] ?? 0), 2),
                'promo_code' => (string) ($data['promo_code'] ?? ''),
            ],
        ]);
    }

    public function orders_details(int $id)
    {
        return $this->show($id);
    }

    /**
     * Create a missing cancellation credit note for an already-cancelled order.
     */
    public function generateCreditNote(int $id)
    {
        $order = $this->findOrder($id);
        abort_if(!$order, 404);

        $status = strtolower(trim((string) ($order->status ?? $order->resolved_status ?? '')));
        if (!in_array($status, ['cancelled', 'canceled', 'rejected'], true)) {
            return redirect()
                ->route('superadmin.orders.details', ['id' => $id])
                ->with('error', 'Credit notes can only be generated for cancelled orders.');
        }

        $issue = $this->issueCancellationCreditNote(
            $id,
            trim((string) ($order->order_note ?? '')) !== ''
                ? (string) $order->order_note
                : 'Backfill credit note for cancelled order'
        );

        $message = trim((string) ($issue['message'] ?? ''));
        if (!empty($issue['credit_note'])) {
            return redirect()
                ->route('superadmin.orders.details', ['id' => $id])
                ->with('success', $message !== '' ? $message : 'Credit note generated.');
        }

        return redirect()
            ->route('superadmin.orders.details', ['id' => $id])
            ->with('error', $message !== '' ? $message : 'Credit note could not be generated.');
    }

    /**
     * Rebuild credit-note PDF (and invoice_sub qty) from current order licenses.
     * Use this to fix older CNs that were saved with qty=1.
     */
    public function regenerateCreditNotePdf(int $id)
    {
        $order = $this->findOrder($id);
        abort_if(!$order, 404);

        $cn = $this->invoiceMst->newQuery()
            ->where('order_id', $id)
            ->where(function ($q) {
                $q->where('action_type', OrderCancellationCreditNoteService::CREDIT_NOTE_ACTION)
                    ->orWhere('bill_no_full', 'like', 'CN/%');
            })
            ->orderByDesc('id')
            ->first();

        if (!$cn) {
            return redirect()
                ->route('superadmin.orders.details', ['id' => $id])
                ->with('error', 'No credit note found for this order.');
        }

        $cnNo = trim((string) ($cn->bill_no_full ?? ''));
        if ($cnNo === '') {
            $cnNo = 'CN-' . (int) $cn->id;
        }

        $calc = [];
        try {
            $cancelAt = null;
            if (!empty($cn->bill_date)) {
                $cancelAt = Carbon::parse((string) $cn->bill_date);
            }
            $calc = app(OrderCancellationCreditNoteService::class)->calculateProrataRefund($order, $cancelAt);
        } catch (\Throwable $e) {
            Log::warning('regenerateCreditNotePdf: calc failed', [
                'order_id' => $id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $stored = $this->persistCreditNotePdfToStorage(
                $id,
                (int) $cn->id,
                $cnNo,
                $calc,
                true
            );
        } catch (\Throwable $e) {
            Log::error('regenerateCreditNotePdf failed', [
                'order_id' => $id,
                'credit_note_id' => (int) $cn->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('superadmin.orders.details', ['id' => $id])
                ->with('error', 'Credit note PDF could not be regenerated: ' . $e->getMessage());
        }

        if (empty($stored['public_url'])) {
            return redirect()
                ->route('superadmin.orders.details', ['id' => $id])
                ->with('error', 'Credit note PDF could not be regenerated. Check storage/logs for persistCreditNotePdfToStorage.');
        }

        $qty = app(OrderCancellationCreditNoteService::class)->resolveOrderLicenseQty($order);

        return redirect()
            ->route('superadmin.orders.details', ['id' => $id])
            ->with(
                'success',
                'Credit note PDF regenerated with quantity ' . $qty . '. Open View Credit Note (hard-refresh if cached).'
            );
    }

    public function invoice(int $id)
    {
        $invoiceData = $this->buildInvoiceData($id);
        abort_if(!$invoiceData, 404);

        try {
            $this->persistOrderInvoicePdfToStorage($id, true);
        } catch (\Throwable $e) {
            Log::warning('invoice view: stored PDF refresh failed', [
                'order_id' => $id,
                'error' => $e->getMessage(),
            ]);
        }

        $fileName = $this->safeDownloadFilename(
            'Tax Invoice-' . $invoiceData['invoice_number']
        );

        $binary = $this->renderInvoicePdfBinary($invoiceData);
        if ($binary !== null) {
            return $this->streamPdfBinary($binary, $fileName);
        }

        return view('superadmin.orders.invoice', $invoiceData);
    }

    public function downloadInvoice(int $id)
    {
        $invoiceData = $this->buildInvoiceData($id);
        abort_if(!$invoiceData, 404);

        $fileName = $this->safeDownloadFilename(
            'Tax Invoice-' . $invoiceData['invoice_number']
        );

        $binary = $this->renderInvoicePdfBinary($invoiceData);
        if ($binary !== null) {
            return $this->downloadPdfBinary($binary, $fileName);
        }

        return response()
            ->view('superadmin.orders.invoice', $invoiceData)
            ->header('Content-Type', 'text/html')
            ->header(
                'Content-Disposition',
                'attachment; filename="' . $fileName . '.html"'
            );
    }
    public function b2bBillingBulkInvoices(Request $request)
    {
        $ids = $request->input('order_ids', []);
        if (!is_array($ids) || empty($ids)) {
            return redirect()->back()->with('error', 'Please select at least one order.');
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_values(array_filter($ids, static fn($id) => $id > 0));
        if (empty($ids)) {
            return redirect()->back()->with('error', 'Invalid order selection.');
        }

        $invoicePayloads = [];
        foreach ($ids as $id) {
            $invoiceData = $this->buildInvoiceData($id);
            if (!$invoiceData) {
                continue;
            }
            $invoicePayloads[] = $invoiceData;
        }

        if (empty($invoicePayloads)) {
            return redirect()->back()->with('error', 'No invoices found for selected orders.');
        }

        $html = $this->buildBulkInvoiceHtml($invoicePayloads);
        $pdf = $this->resolvePdfWrapper();
        if (!$pdf) {
            return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        $html = $this->prepareInvoiceHtmlForPdf($html);
        $binary = $this->renderHtmlToPdfBinary($html);
        if ($binary === null) {
            return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        return $this->streamPdfBinary($binary, 'B2B-Invoices-' . date('Ymd-His'));
    }

    private function buildBulkInvoiceHtml(array $invoicePayloads): string
    {
        $styles = '';
        $pages = [];

        foreach ($invoicePayloads as $payload) {
            $rendered = view('superadmin.orders.invoice', $payload)->render();
            if ($styles === '' && preg_match('/<style[^>]*>(.*?)<\/style>/is', $rendered, $m)) {
                $styles = trim((string) ($m[1] ?? ''));
            }

            $body = $rendered;
            if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $rendered, $m)) {
                $body = (string) ($m[1] ?? '');
            }
            $pages[] = '<div class="bulk-invoice-page">' . $body . '</div>';
        }

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>B2B Invoices</title><style>'
            . $styles
            . '.bulk-invoice-page{page-break-after:always;}.bulk-invoice-page:last-child{page-break-after:auto;}'
            . '</style></head><body>'
            . implode("\n", $pages)
            . '</body></html>';
    }

    private function resolvePdfWrapper()
    {
        if (app()->bound('dompdf.wrapper')) {
            return app('dompdf.wrapper');
        }

        return null;
    }

    /**
     * Render HTML to PDF bytes (dompdf wrapper, or direct Dompdf class).
     */
    private function dompdfRenderOptions(): array
    {
        $defaults = [
            'defaultFont' => 'DejaVu Sans',
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'isPhpEnabled' => false,
            'dpi' => 96,
            'chroot' => realpath(base_path()) ?: base_path(),
            'font_dir' => storage_path('fonts'),
            'font_cache' => storage_path('fonts'),
            'temp_dir' => sys_get_temp_dir(),
        ];

        return array_merge($defaults, (array) config('dompdf.options', []));
    }

    private function renderHtmlToPdfBinary(string $html): ?string
    {
        $options = $this->dompdfRenderOptions();
        $pdf = $this->resolvePdfWrapper();
        if ($pdf) {
            try {
                return $pdf->loadHtml($html)->setPaper('a4', 'portrait')->setOptions($options)->output();
            } catch (\Throwable $e) {
                Log::warning('dompdf.wrapper render failed', ['error' => $e->getMessage()]);
            }
        }

        if (class_exists(\Dompdf\Dompdf::class)) {
            try {
                $dompdf = new \Dompdf\Dompdf($options);
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();

                return $dompdf->output();
            } catch (\Throwable $e) {
                Log::warning('Dompdf render failed', ['error' => $e->getMessage()]);
            }
        }

        return null;
    }

    /**
     * Build PO PDF bytes for storage/download using the shared layout pipeline.
     *
     * @return array{data: string, mime: string, po_number: string}|null
     */
    public function buildOrderPoPdfForStorage(int $orderId): ?array
    {
        $order = $this->orders->newQuery()->whereKey($orderId)->first();
        if (!$order) {
            return null;
        }

        $attachment = $this->buildGeneratedPoAttachment($orderId, $order);
        if (!$attachment || empty($attachment['data'])) {
            return null;
        }
        if (($attachment['mime'] ?? '') !== 'application/pdf') {
            Log::warning('buildOrderPoPdfForStorage: expected PDF', ['order_id' => $orderId]);

            return null;
        }

        $poRow = $this->orderPoDetails->newQuery()->where('order_id', $orderId)->first();
        $poNumber = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($poRow->po_number ?? 'PO-' . $orderId));
        if ($poNumber === '') {
            $poNumber = 'PO-' . $orderId;
        }

        return [
            'data' => (string) $attachment['data'],
            'mime' => 'application/pdf',
            'po_number' => $poNumber,
        ];
    }

    /**
     * Save generated PO PDF to public storage.
     *
     * @return array{relative_path: string, public_url: string, po_number: string}|null
     */
    public function saveOrderPoPdfDocument(int $orderId): ?array
    {
        $pdfBuild = $this->buildOrderPoPdfForStorage($orderId);
        if ($pdfBuild === null || ($pdfBuild['data'] ?? '') === '') {
            return null;
        }

        $poNumber = (string) ($pdfBuild['po_number'] ?? ('PO-' . $orderId));
        $relativePath = 'order-po/' . $poNumber . '-order-' . $orderId . '.pdf';
        if (!$this->writePublicStorageFile($relativePath, $pdfBuild['data'])) {
            return null;
        }

        return [
            'relative_path' => $relativePath,
            'public_url' => $this->publicStorageUrl($relativePath),
            'po_number' => $poNumber,
        ];
    }

    /**
     * Generate PO PDF from partner cart (no orders row). Used when eSign is pending.
     *
     * @return array{relative_path: string, public_url: string, po_number: string}|null
     */
    public function saveCartPoPdfDocument(int $cartId): ?array
    {
        if ($cartId <= 0) {
            return null;
        }

        $pdfBuild = $this->buildCartPoPdfForStorage($cartId);
        if ($pdfBuild === null || ($pdfBuild['data'] ?? '') === '') {
            return null;
        }

        $poNumber = (string) ($pdfBuild['po_number'] ?? ('PON' . str_pad((string) $cartId, 5, '0', STR_PAD_LEFT)));
        // Include amount fingerprint so amount changes get a fresh PDF (not a stale cached file).
        $cartAmount = (string) round((float) (Cart::query()->whereKey($cartId)->value('grand_total') ?? 0), 2);
        $relativePath = 'order-po/' . $poNumber . '-cart-' . $cartId . '-amt-' . str_replace('.', '_', $cartAmount) . '.pdf';

        if (!$this->writePublicStorageFile($relativePath, $pdfBuild['data'])) {
            return null;
        }

        $publicUrl = $this->publicStorageUrl($relativePath);
        Cache::put('cart_po_number_' . $cartId, $poNumber, now()->addDays(7));
        Cache::put('cart_po_link_' . $cartId, $publicUrl, now()->addDays(7));
        Cache::put('cart_po_amount_' . $cartId, $cartAmount, now()->addDays(7));

        return [
            'relative_path' => $relativePath,
            'public_url' => $publicUrl,
            'po_number' => $poNumber,
        ];
    }

    /**
     * @return array{data: string, mime: string, po_number: string}|null
     */
    public function buildCartPoPdfForStorage(int $cartId): ?array
    {
        $attachment = $this->buildCartPoAttachment($cartId);
        if (!$attachment || empty($attachment['data'])) {
            return null;
        }
        if (($attachment['mime'] ?? '') !== 'application/pdf') {
            return null;
        }

        $poNumber = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($attachment['po_number'] ?? ''));
        if ($poNumber === '') {
            $poNumber = $this->resolveCartPoNumber($cartId);
        }

        return [
            'data' => (string) $attachment['data'],
            'mime' => 'application/pdf',
            'po_number' => $poNumber,
        ];
    }

    /**
     * @return array{type: string, data: string, name: string, mime: string, po_number: string}|null
     */
    private function buildCartPoAttachment(int $cartId): ?array
    {
        $viewData = $this->buildCartPoViewData($cartId);
        if ($viewData === null) {
            return null;
        }

        $viewData['for_pdf'] = true;

        try {
            $html = view('superadmin.po_details_pdf', $viewData)->render();
        } catch (\Throwable $e) {
            Log::error('buildCartPoAttachment: view render failed', [
                'cart_id' => $cartId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($html === '') {
            return null;
        }

        $binary = $this->renderHtmlToPdfBinary($this->prepareInvoiceHtmlForPdf($html));
        if ($binary === null || $binary === '') {
            return null;
        }

        return [
            'type' => 'data',
            'data' => $binary,
            'name' => 'Generated-PO-Cart-' . $cartId . '.pdf',
            'mime' => 'application/pdf',
            'po_number' => (string) ($viewData['po_number'] ?? $this->resolveCartPoNumber($cartId)),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildCartPoViewData(int $cartId): ?array
    {
        $cart = Cart::query()->with(['items.plan', 'customer'])->find($cartId);
        if (!$cart || $cart->items->isEmpty()) {
            return null;
        }

        $customerId = (int) ($cart->customer_id ?? 0);
        $prefill = $customerId > 0
            ? app(RenewalService::class)->buildPurchaseOrderPrefillFromCustomer($customerId)
            : [];

        $items = [];
        $planNames = [];
        foreach ($cart->items as $cartLine) {
            $plan = $cartLine->plan;
            $qty = max(1, (int) ($cartLine->licenses ?? 1));
            $unitPrice = round((float) ($cartLine->unit_price ?? 0), 2);
            $lineTotal = round((float) ($cartLine->grand_total ?? ($unitPrice * $qty)), 2);
            $planName = trim((string) ($plan->name ?? 'Plan'));
            $planNames[] = $planName;
            $items[] = [
                'subscription_id' => '',
                'name' => $planName,
                'description' => '',
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'total' => $lineTotal,
                'hsn_sac' => $this->resolveHsnFromPlan($plan),
            ];
        }

        $domainName = trim((string) ($cart->domain_name ?? ''));
        $cartType = strtolower(trim((string) ($cart->type ?? Cart::TYPE_STANDARD)));
        $gstTotal = round((float) ($cart->gst_amount ?? 0), 2);
        $discountAmount = round((float) ($cart->discount_amount ?? 0), 2);
        $cartSubtotal = round((float) ($cart->subtotal ?? 0), 2);
        if ($cartSubtotal <= 0) {
            $cartSubtotal = round(array_sum(array_column($items, 'total')), 2);
        }
        $remainingValue = round((float) ($cart->pro_rata_adjustment ?? 0), 2);
        $isPlanChangeCart = in_array($cartType, [
            Cart::TYPE_UPGRADE,
            Cart::TYPE_DOWNGRADE,
            'upgrade',
            'downgrade',
        ], true);
        if ($remainingValue <= 0 && $isPlanChangeCart) {
            $remainingValue = $this->resolveCartUpgradeRemainingValue($cart);
        }
        if ($remainingValue > 0 && abs($discountAmount - $remainingValue) < 0.01) {
            $discountAmount = 0.0;
        }
        $total = round((float) ($cart->grand_total ?? 0), 2);
        if ($total <= 0) {
            $total = round(max(0, $cartSubtotal - $remainingValue - $discountAmount) + $gstTotal, 2);
        }
        $grossSubtotal = $cartSubtotal;
        $taxableTotal = max(0.0, round($cartSubtotal - $remainingValue - $discountAmount, 2));

        $customerGstin = trim((string) ($prefill['gstin'] ?? ''));
        $split = GstSplit::split($gstTotal, $customerGstin);
        $taxType = $split['tax_type'];
        $cgstTotal = $split['cgst'];
        $sgstTotal = $split['sgst'];
        $igstTotal = $split['igst'];

        $poNumber = $this->resolveCartPoNumber($cartId);
        $poCarbon = Carbon::now();
        $dueCarbon = $poCarbon->copy()->addDays(15);
        $dueDateLong = $dueCarbon->format('l, jS F Y');

        $buyerName = trim((string) ($prefill['company'] ?? $prefill['company_name'] ?? $prefill['name'] ?? ''));
        if ($buyerName === '') {
            $buyerName = trim((string) ($cart->customer->company_name ?? $cart->customer->name ?? '-'));
        }
        $buyerAddress = trim((string) ($prefill['address'] ?? $cart->customer->address ?? '-'));
        $buyerPhone = trim((string) ($prefill['mobile'] ?? $cart->customer->mobile ?? ''));
        $buyerGstin = trim((string) ($prefill['gstin'] ?? $cart->customer->gstin ?? '-'));
        $buyerState = trim((string) ($prefill['state'] ?? ''));
        $buyerStateCode = strlen($buyerGstin) >= 2 && ctype_digit(substr($buyerGstin, 0, 2))
            ? substr($buyerGstin, 0, 2)
            : '';

        $domainName = trim((string) ($cart->domain_name ?? ''));
        $cartTransfer = strtolower(trim((string) ($cart->transfer_domain ?? '')));
        if (in_array($cartTransfer, ['1', 'true'], true)) {
            $cartTransfer = 'yes';
        } elseif (in_array($cartTransfer, ['0', 'false'], true)) {
            $cartTransfer = 'no';
        }
        // Enrollment type from transfer_domain only (same as PO blade)
        $enrollmentType = $cartTransfer === 'yes' ? 'Transfer Service' : 'New Service';

        $firstLine = $cart->items->first();
        $startRaw = $firstLine?->subscription_start_date ?? now();
        $endRaw = $firstLine?->subscription_end_date ?? null;
        try {
            $startLabel = Carbon::parse($startRaw)->format('d-m-Y');
        } catch (\Throwable) {
            $startLabel = Carbon::now()->format('d-m-Y');
        }
        try {
            $endLabel = $endRaw
                ? Carbon::parse($endRaw)->format('d-m-Y')
                : Carbon::parse($startRaw)->addYear()->subDay()->format('d-m-Y');
        } catch (\Throwable) {
            $endLabel = Carbon::now()->addYear()->subDay()->format('d-m-Y');
        }

        $planTitle = $planNames !== [] ? implode(', ', array_unique($planNames)) : 'Service';
        $serviceDetails = implode("\n", array_values(array_filter([
            'Enrollment type: ' . $enrollmentType,
            $planTitle,
            $domainName !== '' ? 'Domain: ' . $domainName : '',
            'Billing Cycle: ' . $startLabel . ' To ' . $endLabel,
        ])));

        $partnerId = (int) ($cart->partner_id ?? 0);
        $aadhaarMandatoryYes = false;
        if ($partnerId > 0) {
            $aadhaarMandatoryYes = strtolower(trim((string) (
                $this->partners->newQuery()->whereKey($partnerId)->value('aadhaar_mandatory') ?? 'no'
            ))) === 'yes';
        }

        $termsOfDelivery = 'Due on or before ' . $dueDateLong . '. '
            . 'The payment for the services due in 15 days from the raised date of this purchase order. '
            . '2% interest charge per month will be levied if payment is not made by the due date. '
            . 'For general terms visit your service agreement or company website.';

        return [
            'title' => 'Invoice',
            'document_title' => 'Purchase Order',
            'po_number' => $poNumber,
            'order_no' => '',
            'order_status' => 'pending_aadhaar',
            'po_date' => $poCarbon->format('d-m-Y'),
            'po_date_alt' => $poCarbon->format('d-M-y'),
            'due_date' => $dueCarbon->format('jS F Y'),
            'due_date_long' => $dueDateLong,
            'invoice_number' => $poNumber,
            'invoice_date' => $poCarbon->format('d-m-Y'),
            'invoice_date_alt' => $poCarbon->format('d-M-y'),
            'currency_symbol' => "\xE2\x82\xB9",
            'company' => $this->resolveCompanyMeta(),
            'customer' => [
                'name' => $buyerName !== '' ? $buyerName : '-',
                'billing_address' => $buyerAddress !== '' ? $buyerAddress : '-',
                'phone' => $buyerPhone,
                'gstin' => $buyerGstin !== '' ? $buyerGstin : '-',
                'state_name' => $buyerState,
                'state_code' => $buyerStateCode,
            ],
            'items' => $items,
            'subtotal' => $taxableTotal,
            'gross_subtotal' => $grossSubtotal,
            'discount_amount' => $discountAmount,
            'pro_rata_adjustment' => $remainingValue,
            'remaining_value' => $remainingValue,
            'remaining_credit' => $remainingValue,
            'promo_code' => strtoupper(trim((string) ($cart->coupen ?? $cart->coupon ?? ''))),
            'total' => $total,
            'taxable_total' => $taxableTotal,
            'tax_type' => $taxType,
            'gst_rate' => GstSplit::RATE,
            'gst_total' => $gstTotal,
            'cgst_total' => $cgstTotal,
            'sgst_total' => $sgstTotal,
            'igst_total' => $igstTotal,
            'paid_amount' => 0.0,
            'balance' => $total,
            'bank' => $this->resolveBankMeta(),
            'einvoice' => [
                'irn' => '',
                'ack_no' => '',
                'ack_date' => '',
                'qr_src' => '',
                'signed_qr_code' => '',
                'is_cancelled' => false,
                'e_invoice_pdf_url' => '',
            ],
            'terms_of_payment' => 'Due within 15 days from PO date.',
            'terms_of_delivery' => $termsOfDelivery,
            'service_details' => $serviceDetails,
            'footer_note' => 'This is a Computer Generated Purchase Order',
            'aadhaar_signature_valid' => false,
            'aadhar_verified_at' => null,
            'aadhaar_masked' => null,
            'aadhaar_ref_no' => null,
            'aadhaar_mandatory_yes' => $aadhaarMandatoryYes,
            'show_aadhaar_signature_stamp' => false,
            'is_order_cancelled' => false,
            'transfer_domain' => $cartTransfer !== '' ? $cartTransfer : (string) ($cart->transfer_domain ?? ''),
            'enrollment_type' => $enrollmentType,
            'order' => (object) [
                'order_category' => $cartType,
                'transfer_domain' => $cartTransfer !== '' ? $cartTransfer : (string) ($cart->transfer_domain ?? ''),
                'parent_order_id' => (int) ($cart->order_id ?? 0),
                'pro_rata_adjustment' => $remainingValue,
            ],
        ];
    }

    /**
     * Remaining value for an upgrade/downgrade cart (parent unused days).
     */
    private function resolveCartUpgradeRemainingValue(Cart $cart): float
    {
        $stored = round((float) ($cart->pro_rata_adjustment ?? 0), 2);
        if ($stored > 0) {
            return $stored;
        }

        try {
            $cart->loadMissing(['items.plan']);
            $item = $cart->items->first();
            if (!$item) {
                return 0.0;
            }

            $parentOrderId = (int) ($cart->order_id ?? 0);
            $sourceOrder = $parentOrderId > 0 ? Order::query()->find($parentOrderId) : null;
            $sourceItem = null;
            $orderSubId = (int) ($item->order_sub_id ?? $item->order_item_id ?? 0);
            if ($orderSubId > 0) {
                $sourceItem = OrderItem::query()->whereKey($orderSubId)->first();
                if ($sourceItem && !$sourceOrder) {
                    $sourceOrder = Order::query()->find((int) ($sourceItem->order_id ?? 0));
                }
            } elseif ($sourceOrder) {
                $sourceItem = OrderItem::query()
                    ->where('order_id', (int) $sourceOrder->id)
                    ->where(function ($q) {
                        $q->whereNull('status')->orWhere('status', '!=', 'delete');
                    })
                    ->orderBy('id')
                    ->first();
            }

            $licenses = max(1, (int) ($item->licenses ?? 1));
            $newUnitPrice = round((float) ($item->unit_price ?? optional($item->plan)->price ?? 0), 2);
            $proration = app(UpgradeProrationService::class)->resolve(
                $sourceOrder,
                $sourceItem,
                $newUnitPrice,
                $licenses,
                (float) ($cart->gst_percentage ?? 18),
                0.0,
                (int) ($item->plan_id ?? 0)
            );

            $remaining = round((float) ($proration['remaining_value'] ?? $proration['pro_rata_adjustment'] ?? 0), 2);
            if ($remaining > 0) {
                $cart->pro_rata_adjustment = $remaining;
                $cart->save();
            }

            return max(0.0, $remaining);
        } catch (\Throwable $e) {
            Log::warning('cart_po_remaining_value_failed', [
                'cart_id' => (int) ($cart->id ?? 0),
                'error' => $e->getMessage(),
            ]);

            return 0.0;
        }
    }

    private function resolveCartPoNumber(int $cartId): string
    {
        $cached = Cache::get('cart_po_number_' . $cartId);
        if (is_string($cached) && trim($cached) !== '') {
            return trim($cached);
        }

        return $this->nextPoNumber();
    }

    private function poRowValue(?object $poRow, string ...$keys): string
    {
        if (!$poRow) {
            return '';
        }
        foreach ($keys as $key) {
            if (isset($poRow->{$key}) && (string) $poRow->{$key} !== '') {
                return (string) $poRow->{$key};
            }
        }

        return '';
    }

    /**
     * Absolute path under storage/app/public.
     */
    private function storagePublicPath(string $relativePath): string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

        return storage_path('app/public/' . $relativePath);
    }

    /**
     * Absolute path under public/storage (web-accessible when docroot is /public).
     */
    private function webPublicStoragePath(string $relativePath): string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

        return public_path('storage/' . $relativePath);
    }

    /**
     * First existing non-empty file for a public-storage relative path.
     */
    private function resolveExistingPublicStorageFile(string $relativePath): ?string
    {
        foreach ([$this->storagePublicPath($relativePath), $this->webPublicStoragePath($relativePath)] as $path) {
            if (is_file($path) && filesize($path) > 0) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Write under storage/app/public and mirror to public/storage (shared hosting may lack symlink).
     */
    private function writePublicStorageFile(string $relativePath, string $binary): bool
    {
        if ($binary === '') {
            Log::warning('writePublicStorageFile: empty binary', ['path' => $relativePath]);

            return false;
        }

        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $storageFile = $this->storagePublicPath($relativePath);
        $publicFile = $this->webPublicStoragePath($relativePath);

        foreach ([dirname($storageFile), dirname($publicFile)] as $directory) {
            if (is_dir($directory)) {
                continue;
            }
            if (!@mkdir($directory, 0755, true) && !is_dir($directory)) {
                Log::error('writePublicStorageFile: cannot create directory', [
                    'directory' => $directory,
                    'relative' => $relativePath,
                ]);
            }
        }

        $storageOk = @file_put_contents($storageFile, $binary) !== false;
        $publicOk = @file_put_contents($publicFile, $binary) !== false;

        if ($storageOk && !$publicOk && is_file($storageFile)) {
            $publicOk = @copy($storageFile, $publicFile);
        }

        if (!$storageOk) {
            try {
                Storage::disk('public')->makeDirectory(dirname($relativePath) === '.' ? '' : dirname($relativePath));
                $storageOk = (bool) Storage::disk('public')->put($relativePath, $binary);
                if ($storageOk && is_file($storageFile) && !is_file($publicFile)) {
                    $publicOk = @copy($storageFile, $publicFile);
                }
            } catch (\Throwable $e) {
                Log::warning('writePublicStorageFile: Storage disk fallback failed', [
                    'path' => $relativePath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $ok = (is_file($storageFile) && filesize($storageFile) > 0)
            || (is_file($publicFile) && filesize($publicFile) > 0);

        if (!$ok) {
            Log::error('writePublicStorageFile: file not saved', [
                'relative' => $relativePath,
                'storage_file' => $storageFile,
                'public_file' => $publicFile,
                'storage_ok' => $storageOk,
                'public_ok' => $publicOk,
                'php_error' => error_get_last(),
            ]);
        }

        return $ok;
    }

    /**
     * Web path segment for stored files (default: /storage/...).
     */
    private function publicStorageWebRelativePath(string $relativePath): string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $prefix = trim((string) env('PUBLIC_STORAGE_URL_PREFIX', 'storage'), '/');

        return $prefix . ($relativePath !== '' ? '/' . $relativePath : '');
    }

    /**
     * Full browser URL, e.g. https://example.com/storage/order-po/file.pdf
     */
    private function publicStorageUrl(string $relativePath): string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $webPath = $this->publicStorageWebRelativePath($relativePath);
        $base = rtrim((string) config('app.url'), '/');

        return $base . '/' . $webPath;
    }

    /**
     * Normalize stored file links to current PUBLIC_STORAGE_URL_PREFIX.
     */
    private function normalizePublicStorageHttpUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return $url;
        }

        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        if ($path === '') {
            return $url;
        }

        $relative = '';
        foreach (['/public/storage/', '/storage/'] as $marker) {
            $idx = strpos($path, $marker);
            if ($idx !== false) {
                $relative = ltrim(substr($path, $idx + strlen($marker)), '/');
                break;
            }
        }
        if ($relative !== '') {
            return $this->publicStorageUrl($relative);
        }

        return $url;
    }

    private function resolveStoredDocumentUrl(?string $storedUrl, string $relativePath): ?string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($relativePath !== '' && file_exists($this->webPublicStoragePath($relativePath))) {
            return $this->publicStorageUrl($relativePath);
        }
        if ($relativePath !== '' && file_exists($this->storagePublicPath($relativePath))) {
            return $this->publicStorageUrl($relativePath);
        }

        if ($storedUrl !== null && $storedUrl !== '') {
            if (str_starts_with($storedUrl, 'http')) {
                return $this->normalizePublicStorageHttpUrl($storedUrl);
            }

            return url(ltrim($storedUrl, '/'));
        }

        return null;
    }

    private function buildInvoiceData(int $id): ?array
    {
        $order = $this->findOrder($id);
        if (!$order) {
            return null;
        }

        $items = $this->alignInvoiceLineAmountsWithPo($order, $this->resolveInvoiceItems($order));

        $gstTotal = (float) ($order->gst_amount ?? 0);
        if ($gstTotal <= 0.0) {
            $gstTotal = (float) ($order->tax_amount ?? 0);
        }

        $discountAmount = max(0, round((float) ($order->discount_amount ?? 0), 2));
        $promoCode = $this->orderPromoCode($order);
        $orderSubtotal = round((float) ($order->subtotal ?? 0), 2);
        if ($orderSubtotal <= 0) {
            $orderSubtotal = round(array_sum(array_map(
                static fn($item) => (float) ($item['total'] ?? 0),
                $items
            )), 2);
        }

        // Remaining value / pro-rata from orders (+ items/cart fallback).
        $proRataAdjustment = $this->resolveInvoiceProRataAdjustment($order, $items, $gstTotal);
        $couponDiscount = $discountAmount;
        if ($proRataAdjustment > 0) {
            if (abs($couponDiscount - $proRataAdjustment) < 0.01) {
                $couponDiscount = 0.0;
            } else {
                $couponDiscount = max(0.0, round($couponDiscount - $proRataAdjustment, 2));
            }
        }
        $totalDeduction = round($proRataAdjustment + $couponDiscount, 2);

        $total = round((float) ($order->total_amount ?? 0), 2);
        if ($total <= 0) {
            $total = (float) ($order->resolved_amount ?? 0);
        }

        $invoiceMstRow = $this->latestTaxInvoiceMstForOrder($id);
        if ($invoiceMstRow) {
            if ($couponDiscount <= 0 && $proRataAdjustment <= 0 && isset($invoiceMstRow->discount_amount)) {
                $couponDiscount = max(0, round((float) $invoiceMstRow->discount_amount, 2));
                $totalDeduction = round($proRataAdjustment + $couponDiscount, 2);
            }
            if ($promoCode === '' && !empty($invoiceMstRow->promo_code)) {
                $promoCode = strtoupper(trim((string) $invoiceMstRow->promo_code));
            }
        }

        // When discount / pro-rata exists, orders table is source of truth.
        if ($totalDeduction > 0) {
            $taxableTotal = $orderSubtotal > 0
                ? max(0.0, round($orderSubtotal - $totalDeduction, 2))
                : max(0.0, round($total - $gstTotal, 2));
            $grossSubtotal = $orderSubtotal > 0 ? $orderSubtotal : round($taxableTotal + $totalDeduction, 2);
        } else {
            $linesSum = 0.0;
            foreach ($items as $item) {
                $linesSum += (float) ($item['total'] ?? 0);
            }
            $taxableTotal = $linesSum > 0
                ? $linesSum
                : ($orderSubtotal > 0
                    ? $orderSubtotal
                    : max(0.0, (float) ($order->resolved_amount ?? 0) - $gstTotal));
            $grossSubtotal = $taxableTotal;
        }
        $subtotal = $taxableTotal;

        if ($total <= 0 && $gstTotal > 0) {
            $total = round($taxableTotal + $gstTotal, 2);
        } elseif ($total <= 0 && $subtotal > 0) {
            $total = $subtotal;
        }

        $paidAmount = 0.0;
        $balance = $total - $paidAmount;
        $currencySymbol = "\xE2\x82\xB9";

        $invoiceDate = !empty($order->created_at)
            ? Carbon::parse($order->created_at)->format('d-m-Y')
            : Carbon::now()->format('d-m-Y');
        $invoiceDateAlt = !empty($order->created_at)
            ? Carbon::parse($order->created_at)->format('d-M-y')
            : Carbon::now()->format('d-M-y');

        $cgstTotal = $this->orderStoredAmount($order, ['cgst_total', 'cgst_amount', 'cgst']);
        $sgstTotal = $this->orderStoredAmount($order, ['sgst_total', 'sgst_amount', 'sgst']);
        $igstTotal = $this->orderStoredAmount($order, ['igst_total', 'igst_amount', 'igst']);
        $taxType = strtolower(trim((string) ($order->tax_type ?? '')));
        if ($cgstTotal <= 0 && $sgstTotal <= 0 && $igstTotal <= 0) {
            $customerGstin = trim((string) ($order->po_manual_gstin ?? $order->customer_gstin ?? ''));
            $split = GstSplit::split($gstTotal, $customerGstin);
            $taxType = $split['tax_type'];
            $cgstTotal = $split['cgst'];
            $sgstTotal = $split['sgst'];
            $igstTotal = $split['igst'];
        } elseif ($taxType === '' && ($cgstTotal > 0 || $sgstTotal > 0)) {
            $taxType = 'cgst_sgst';
        } elseif ($taxType === '' && $igstTotal > 0) {
            $taxType = 'igst';
        }

        $invoiceNumber = (string) ($order->order_no ?? $order->id);
        if ($invoiceMstRow && !empty($invoiceMstRow->bill_no_full)) {
            $invoiceNumber = (string) $invoiceMstRow->bill_no_full;
        }

        $plan = $this->resolvePlanRowForOrder($order);
        $enrollmentMeta = $this->resolveOrderEnrollmentMeta($order);
        $enrollmentType = (string) ($enrollmentMeta['enrollment_type'] ?? 'New Service');
        $labelLines = array_values(array_filter(array_map(
            'trim',
            explode("\n", $this->buildRichInvoiceLineLabel($order, $plan))
        )));
        $serviceDetails = implode("\n", array_merge(
            ['Enrollment type: ' . $enrollmentType],
            array_slice($labelLines, 1)
        ));

        return [
            'title' => 'Invoice',
            'order' => $order,
            'invoice_number' => $invoiceNumber,
            'invoice_date' => $invoiceDate,
            'invoice_date_alt' => $invoiceDateAlt,
            'currency_symbol' => $currencySymbol,
            'company' => $this->resolveCompanyMeta(),
            'customer' => $this->resolveCustomerMeta($order),
            'items' => $items,
            'subtotal' => $subtotal,
            'gross_subtotal' => $grossSubtotal,
            'discount_amount' => $couponDiscount,
            'pro_rata_adjustment' => $proRataAdjustment,
            'remaining_value' => $proRataAdjustment,
            'coupon_discount' => $couponDiscount,
            'promo_code' => $promoCode,
            'total' => $total,
            'taxable_total' => $taxableTotal,
            'tax_type' => $taxType,
            'gst_rate' => GstSplit::RATE,
            'gst_total' => $gstTotal,
            'cgst_total' => $cgstTotal,
            'sgst_total' => $sgstTotal,
            'igst_total' => $igstTotal,
            'paid_amount' => $paidAmount,
            'balance' => $balance,
            'bank' => $this->resolveBankMeta(),
            'einvoice' => $this->resolveEInvoiceMeta((int) $id),
            'terms_of_payment' => 'Due as per agreed credit terms.',
            'terms_of_delivery' => 'Payment shall be made within the agreed period from the invoice date. Interest @ 2% per month may be charged on overdue amounts. For general terms visit your service agreement or company website.',
            'service_details' => $serviceDetails,
            'transfer_domain' => (string) ($enrollmentMeta['transfer_domain'] ?? ''),
            'enrollment_type' => $enrollmentType,
        ];
    }

    /**
     * Pull the e-invoice artefacts (IRN / Ack / QR) from the `invoices` table
     * for this order. Returns empty strings when no row / IRN exists yet.
     * The `qr_src` field is rendered as a remote QR-code image URL using
     * Sandbox's signed_qr_code payload, so the existing invoice template can
     * just <img src="{{ $einvoice['qr_src'] }}"> without extra deps.
     *
     * @return array{irn:string,ack_no:string,ack_date:string,qr_src:string,signed_qr_code:string,is_cancelled:bool,e_invoice_pdf_url:string}
     */
    private function resolveEInvoiceMeta(int $orderId): array
    {
        $empty = [
            'irn' => '',
            'ack_no' => '',
            'ack_date' => '',
            'qr_src' => '',
            'signed_qr_code' => '',
            'is_cancelled' => false,
            'e_invoice_pdf_url' => '',
        ];
        $row = $this->latestTaxInvoiceMstForOrder($orderId);
        if (!$row || empty($row->irn ?? null)) {
            return $empty;
        }

        $signedQr = (string) ($row->signed_qr_code ?? '');
        $qrPayload = $signedQr !== '' ? $signedQr : (string) $row->irn;
        $qrSrc = '';
        if ($qrPayload !== '') {
            try {
                $qrResponse = Http::timeout(10)
                    ->withoutVerifying()
                    ->get('https://api.qrserver.com/v1/create-qr-code/', [
                        'size' => '160x160',
                        'format' => 'svg',
                        'data' => $qrPayload,
                    ]);

                if ($qrResponse->successful() && $qrResponse->body() !== '') {
                    $qrSrc = 'data:image/svg+xml;base64,' . base64_encode($qrResponse->body());
                }
            } catch (\Throwable $e) {
                Log::warning('Invoice QR image generation failed', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'irn'                => (string) $row->irn,
            'ack_no'             => (string) ($row->acknowledgement_no ?? ''),
            'ack_date'           => (string) ($row->acknowledgement_date ?? ''),
            'qr_src'             => $qrSrc,
            'signed_qr_code'     => $signedQr,
            'is_cancelled'       => (int) ($row->is_cancelled ?? 0) === 1,
            'e_invoice_pdf_url'  => (string) ($row->e_invoice_pdf_url ?? ''),
        ];
    }

    private function resolveInvoiceItems(object $order): array
    {
        if (true) {
            $itemColumns = $this->orderItemColumns();
            if (in_array('order_id', $itemColumns, true)) {
                $itemNameColumn = in_array('item_name', $itemColumns, true) ? 'item_name' : (in_array('name', $itemColumns, true) ? 'name' : null);
                $descriptionColumn = in_array('description', $itemColumns, true) ? 'description' : null;
                $qtyColumn = in_array('quantity', $itemColumns, true) ? 'quantity' : null;
                $unitPriceColumn = in_array('unit_price', $itemColumns, true) ? 'unit_price' : (in_array('price', $itemColumns, true) ? 'price' : null);
                $totalColumn = in_array('total_amount', $itemColumns, true) ? 'total_amount' : (in_array('amount', $itemColumns, true) ? 'amount' : null);
                $hsnColumn = in_array('hsn_sac', $itemColumns, true)
                    ? 'hsn_sac'
                    : (in_array('hsn', $itemColumns, true) ? 'hsn' : (in_array('sac', $itemColumns, true) ? 'sac' : null));

                $rows = $this->orderItems->newQuery()
                    ->where('order_id', (int) $order->id)
                    ->orderBy('id')
                    ->get();

                if ($rows->isNotEmpty()) {
                    $planForItems = $this->resolvePlanRowForOrder($order);
                    $fallbackHsn = $this->resolveHsnFromPlan($planForItems);

                    return $rows->map(static function ($row) use ($itemNameColumn, $descriptionColumn, $qtyColumn, $unitPriceColumn, $totalColumn, $hsnColumn, $fallbackHsn) {
                        $quantity = max(1, (int) ($qtyColumn ? ($row->{$qtyColumn} ?? 1) : 1));
                        $unitPrice = (float) ($unitPriceColumn ? ($row->{$unitPriceColumn} ?? 0) : 0);
                        // Line Amount must match PO: licenses × rate (before remaining value / GST).
                        // Upgrade items used to store payable in total_amount, which inflated the invoice.
                        $fromUnit = round($unitPrice * $quantity, 2);
                        $fromStored = $totalColumn ? (float) ($row->{$totalColumn} ?? 0) : 0.0;
                        $lineTotal = $fromUnit > 0 ? $fromUnit : $fromStored;
                        if ($lineTotal <= 0) {
                            $lineTotal = $fromStored > 0 ? $fromStored : $fromUnit;
                        }

                        $hsn = $hsnColumn ? trim((string) ($row->{$hsnColumn} ?? '')) : '';
                        if ($hsn === '') {
                            $hsn = $fallbackHsn;
                        }

                        return [
                            'subscription_id' => (string) ($row->subscription_id ?? ''),
                            'name' => (string) ($itemNameColumn ? ($row->{$itemNameColumn} ?? 'Order Item') : 'Order Item'),
                            'description' => (string) ($descriptionColumn ? ($row->{$descriptionColumn} ?? '') : ''),
                            'quantity' => $quantity,
                            'unit_price' => $unitPrice,
                            'total' => $lineTotal,
                            'hsn_sac' => $hsn,
                        ];
                    })->values()->all();
                }
            }
        }

        $qty = isset($order->quantity) ? max(1, (int) $order->quantity) : 1;
        $grossLine = round((float) ($order->subtotal ?? 0), 2);
        if ($grossLine <= 0) {
            $grossLine = round((float) ($order->price ?? 0) * $qty, 2);
        }
        if ($grossLine <= 0) {
            $grossLine = round((float) ($order->resolved_amount ?? 0), 2);
        }
        $unitPrice = round($grossLine / max($qty, 1), 2);
        $plan = $this->resolvePlanRowForOrder($order);

        return [[
            'subscription_id' => '',
            'name' => (string) ($order->resolved_plan_name ?? 'Plan'),
            'description' => (string) ($order->domain_name ?? ''),
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'total' => $grossLine,
            'hsn_sac' => $this->resolveHsnFromPlan($plan),
        ]];
    }

    /**
     * Invoice line Amount must match PO: licenses × rate (before remaining value / GST).
     * Upgrade order_items used to store payable in total_amount.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function alignInvoiceLineAmountsWithPo(object $order, array $items): array
    {
        if ($items === []) {
            return $items;
        }

        $payable = round((float) ($order->total_amount ?? $order->resolved_amount ?? 0), 2);
        $gross = round((float) ($order->subtotal ?? 0), 2);
        if ($gross <= 0) {
            $qty = max(1, (int) ($order->quantity ?? 1));
            $unit = round((float) ($order->price ?? $order->unit_price ?? 0), 2);
            if ($unit > 0) {
                $gross = round($unit * $qty, 2);
            }
        }

        $linesSum = round(array_sum(array_map(static fn($item) => (float) ($item['total'] ?? 0), $items)), 2);
        $looksLikePayable = $payable > 0.009 && $linesSum > 0.009 && abs($linesSum - $payable) < 0.05;
        $grossDiffers = $gross > 0.009 && abs($gross - $payable) >= 0.05;

        if (!$looksLikePayable || !$grossDiffers) {
            return $items;
        }

        if (count($items) === 1) {
            $qty = max(1, (int) ($items[0]['quantity'] ?? 1));
            $items[0]['total'] = $gross;
            $unit = round((float) ($items[0]['unit_price'] ?? 0), 2);
            if ($unit <= 0 || abs(round($unit * $qty, 2) - $payable) < 0.05) {
                $items[0]['unit_price'] = round($gross / $qty, 2);
            }

            return $items;
        }

        $scale = $linesSum > 0 ? ($gross / $linesSum) : 1.0;
        foreach ($items as $i => $item) {
            $items[$i]['total'] = round((float) ($item['total'] ?? 0) * $scale, 2);
        }

        return $items;
    }

    /**
     * Absolute filesystem path (or remote/data URL) for dompdf — logo from superadmin settings upload.
     */
    private function resolveCompanyLogoAbsolutePath(?string $storedLogo): ?string
    {
        $storedLogo = trim((string) $storedLogo);
        if ($storedLogo === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $storedLogo) || str_starts_with($storedLogo, 'data:image')) {
            return $storedLogo;
        }

        $relative = ltrim(str_replace('\\', '/', $storedLogo), '/');
        $relative = (string) preg_replace('#^(public/|storage/app/public/)+#', '', $relative);

        foreach (
            [
                storage_path('app/public/' . $relative),
                public_path('storage/' . $relative),
                public_path('storage/app/public/' . $relative),
                public_path($relative),
            ] as $candidate
        ) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Public logo URL for PO/invoice PDFs — APP_URL + public/home/images/logo_tagline.png
     */
    private function resolveCompanyLogoPublicUrl(): string
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $relative = 'public/home/images/logo_tagline.png';

        return $baseUrl . '/' . ltrim($relative, '/');
    }

    private function resolveCompanyMeta(): array
    {
        $settings = $this->adminSettings->newQuery()->orderByDesc('id')->first();

        $logoRel = 'public/home/images/logo_tagline.png';
        $logo = $settings && !empty($settings->logo) ? (string) $settings->logo : $logoRel;
        $logoPath = $this->resolveCompanyLogoPublicUrl();

        $storedLogoPath = $settings ? $this->resolveCompanyLogoAbsolutePath((string) ($settings->logo ?? '')) : null;
        if ($storedLogoPath !== null && is_file($storedLogoPath)) {
            $logoPath = str_replace('\\', '/', $storedLogoPath);
        } else {
            foreach (
                [
                    public_path('public/home/images/logo_tagline.png'),
                    public_path('home/images/logo_tagline.png'),
                ] as $candidate
            ) {
                if (is_file($candidate)) {
                    $logoPath = str_replace('\\', '/', $candidate);
                    break;
                }
            }
        }

        $aadhaarCheckRel = 'public/home/images/aadhaar-valid-check.png';
        $aadhaarCheckImg = rtrim((string) config('app.url'), '/') . '/' . ltrim($aadhaarCheckRel, '/');
        foreach (
            [
                public_path('public/home/images/aadhaar-valid-check.png'),
                public_path('home/images/aadhaar-valid-check.png'),
            ] as $candidate
        ) {
            if (is_file($candidate)) {
                $aadhaarCheckImg = $candidate;
                break;
            }
        }

        $companyGstin = $settings ? (string) ($settings->gstin ?? $settings->gst_no ?? $settings->company_gstin ?? '') : '';
        $companyStateCode = $settings ? (string) ($settings->state_code ?? '') : '';
        if ($companyStateCode === '' && strlen($companyGstin) >= 2 && ctype_digit(substr($companyGstin, 0, 2))) {
            $companyStateCode = substr($companyGstin, 0, 2);
        }

        return [
            'name' => $settings ? (string) (($settings->title ?? '') ?: config('app.name', 'Company')) : (string) config('app.name', 'Company'),
            'address' => $settings ? (string) ($settings->address ?? '') : '',
            'phone' => $settings ? (string) ($settings->phone ?? '') : '',
            'email' => $settings ? (string) ($settings->email ?? '') : '',
            'logo' => $logo,
            'logo_path' => $logoPath,
            'aadhaar_check_img' => $aadhaarCheckImg,
            'gstin' => $companyGstin,
            'pan' => $settings ? (string) ($settings->pan ?? $settings->pan_no ?? $settings->company_pan ?? '') : '',
            'state_name' => $settings ? (string) ($settings->state ?? $settings->state_name ?? '') : '',
            'state_code' => $companyStateCode,
            'fy_label' => $settings ? (string) ($settings->financial_year ?? $settings->fy_label ?? '') : '',
        ];
    }

    private function resolveCustomerMeta(object $order): array
    {
        $gstin = trim((string) ($order->po_manual_gstin ?? $order->customer_gstin ?? ''));
        $stateCode = strlen($gstin) >= 2 && ctype_digit(substr($gstin, 0, 2)) ? substr($gstin, 0, 2) : '';

        return [
            'name' => (string) ($order->customer_company_name ?? $order->customer_name ?? '-'),
            'billing_address' => (string) ($order->customer_billing_address ?? '-'),
            'phone' => (string) ($order->po_manual_mobile ?? $order->customer_mobile ?? ''),
            'gstin' => $gstin !== '' ? $gstin : '-',
            'state_name' => (string) ($order->po_manual_state ?? ''),
            'state_code' => $stateCode,
        ];
    }

    private function resolveBankMeta(): array
    {
        $settings = $this->adminSettings->newQuery()->orderByDesc('id')->first();

        return [
            'bank_name' => (string) ($settings->bank_name ?? ''),
            'account_number' => (string) ($settings->account_number ?? ''),
            'ifsc_code' => (string) ($settings->ifsc_code ?? ''),
            'account_holder_name' => (string) ($settings->account_holder_name ?? ''),
        ];
    }

    /**
     * @return array{can_cancel: bool, block_reason: ?string, deadline_label: ?string}
     */
    private function buildOrderCancellationPolicyContext(int $orderId, ?int $providerIdHint = null): array
    {
        $orderRow = $this->orders->newQuery()->whereKey($orderId)->first();
        if (!$orderRow) {
            return [
                'can_cancel' => true,
                'block_reason' => null,
                'deadline_label' => null,
            ];
        }

        $providerId = $providerIdHint > 0 ? $providerIdHint : $this->resolveProviderIdForOrder($orderRow);
        if ($providerId > 0) {
            $orderRow->setAttribute('provider_id', $providerId);
        }

        $policy = app(OrderCancellationPolicy::class);

        return [
            'can_cancel' => $policy->canCancel($orderRow, $providerId > 0 ? $providerId : null),
            'block_reason' => $policy->blockMessage($orderRow),
            'deadline_label' => $policy->cancellationDeadlineLabel(
                $orderRow,
                'd-m-Y',
                $providerId > 0 ? $providerId : null
            ),
        ];
    }

    private function orderCancellationBlockMessage(int $orderId, ?int $providerIdHint = null): ?string
    {
        $orderRow = $this->orders->newQuery()->whereKey($orderId)->first();
        if (!$orderRow) {
            return 'Order not found.';
        }

        $providerId = $providerIdHint > 0 ? $providerIdHint : $this->resolveProviderIdForOrder($orderRow);
        if ($providerId > 0) {
            $orderRow->setAttribute('provider_id', $providerId);
        }

        return app(OrderCancellationPolicy::class)->blockMessage($orderRow);
    }

    private function resolveProviderIdForOrder(object $orderRow): int
    {
        $explicitProviderId = (int) ($orderRow->provider_id ?? 0);
        if ($explicitProviderId > 0) {
            return $explicitProviderId;
        }

        $planId = (int) ($orderRow->plan_id ?? 0);
        if ($planId > 0) {
            $providerId = (int) ($this->plans->newQuery()->whereKey($planId)->value('provider_id') ?? 0);
            if ($providerId > 0) {
                return $providerId;
            }
        }

        $orderId = (int) ($orderRow->id ?? 0);
        if ($orderId <= 0) {
            return 0;
        }

        $itemPlanId = (int) $this->orderItems->newQuery()
            ->where('order_id', $orderId)
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereNotIn('status', ['delete', 'deleted']);
            })
            ->orderBy('id')
            ->value('plan_id');

        if ($itemPlanId <= 0) {
            return 0;
        }

        return (int) ($this->plans->newQuery()->whereKey($itemPlanId)->value('provider_id') ?? 0);
    }

    private function findOrder(int $id): ?object
    {
        $orderRow = $this->orders->newQuery()->whereKey($id)->first();
        if (!$orderRow) {
            return null;
        }

        $orderTypeValue = strtolower((string) ($orderRow->order_type ?? 'b2c'));
        $customer = $this->customers->newQuery()->whereKey((int) $orderRow->user_id)->first();
        $poRow = $this->orderPoDetails->newQuery()->where('order_id', $id)->first();
        $planForDisplay = (int) ($orderRow->plan_id ?? 0) > 0
            ? $this->plans->newQuery()->whereKey((int) $orderRow->plan_id)->first()
            : null;
        $partnerForDisplay = (int) ($orderRow->partner_id ?? 0) > 0
            ? $this->partners->newQuery()->whereKey((int) $orderRow->partner_id)->first()
            : null;
        $user = ($orderTypeValue !== 'b2b' && (int) ($orderRow->user_id ?? 0) > 0)
            ? $this->users->newQuery()->whereKey((int) $orderRow->user_id)->first()
            : null;

        $customerName = '-';
        $customerMobile = '-';
        $customerEmail = '-';
        $customerCompanyName = '-';
        $customerPan = '-';
        $customerGstin = '-';
        $customerBillingAddress = '-';

        if ($orderTypeValue === 'b2b' && $customer) {
            $customerName = trim((string) ($customer->name ?: $customer->company_name ?: '-'));
            $customerMobile = trim((string) ($customer->mobile ?? '-'));
            $customerEmail = trim((string) ($customer->email ?? '-'));
            $customerCompanyName = trim((string) ($customer->company_name ?? $customer->name ?? '-'));
            $customerPan = trim((string) ($customer->pan_no ?? '-')) ?: '-';
            $customerGstin = trim((string) ($customer->gstin ?? '-')) ?: '-';
            $addressParts = array_values(array_filter([
                trim((string) ($customer->company_address ?? $customer->address ?? '')),
                trim((string) ($customer->city ?? '')),
                trim((string) ($customer->state ?? '')),
                trim((string) ($customer->country ?? '')),
                trim((string) ($customer->pincode ?? $customer->postcode ?? $customer->zip_code ?? '')),
            ], static fn($part) => $part !== ''));
            $customerBillingAddress = $addressParts !== [] ? implode(', ', $addressParts) : '-';
        } elseif ($user) {
            $customerName = trim((string) ($user->name ?: $user->company_name ?: '-'));
            $customerMobile = trim((string) ($user->mobile ?? '-'));
            $customerEmail = trim((string) ($user->email ?? '-'));
            $customerCompanyName = trim((string) ($user->company_name ?? $user->name ?? '-'));
            $customerPan = trim((string) ($user->pan_no ?? '-')) ?: '-';
            $customerGstin = trim((string) ($user->gstin ?? '-')) ?: '-';
            $addressParts = array_values(array_filter([
                trim((string) ($user->company_address ?? '')),
                trim((string) ($user->city ?? '')),
                trim((string) ($user->state ?? '')),
                trim((string) ($user->country ?? '')),
                trim((string) ($user->postcode ?? '')),
            ], static fn($part) => $part !== ''));
            $customerBillingAddress = $addressParts !== [] ? implode(', ', $addressParts) : '-';
        }

        $planDisplayName = '-';
        if ($planForDisplay) {
            $planDisplayName = trim((string) ($planForDisplay->name ?: $planForDisplay->title ?: '-'));
        }

        $resolvedAmount = (float) ($orderRow->total_amount ?? 0);
        if ($resolvedAmount <= 0) {
            $resolvedAmount = (float) ($orderRow->subtotal ?? $orderRow->price ?? 0);
        }

        return (object) array_merge($orderRow->toArray(), [
            'customer_name' => $customerName,
            'customer_mobile' => $customerMobile,
            'customer_email' => $customerEmail,
            'customer_company_name' => $customerCompanyName,
            'customer_pan' => $customerPan,
            'customer_gstin' => $customerGstin,
            'customer_billing_address' => $customerBillingAddress,
            'partner_name' => trim((string) ($partnerForDisplay->name ?? '-')),
            'resolved_plan_name' => $planDisplayName,
            'resolved_amount' => $resolvedAmount,
            'resolved_credit_used' => (float) ($orderRow->credit_used ?? 0),
            'resolved_order_type' => (string) ($orderRow->order_type ?? 'b2c'),
            'resolved_status' => (string) ($orderRow->status ?? 'pending'),
            'po_mode' => (string) ($poRow->po_mode ?? '-'),
            'po_journey_status' => (string) ($poRow->journey_status ?? '-'),
            'po_number' => $poRow->po_number ?? null,
            'po_date' => $poRow->po_date ?? null,
            'po_amount' => $poRow->po_amount ?? null,
            'po_document_url' => $poRow->po_document_url ?? $orderRow->po_document_url ?? null,
            'po_manual_name' => $poRow->manual_name ?? null,
            'po_manual_email' => $poRow->manual_email ?? null,
            'po_manual_mobile' => $poRow->manual_mobile ?? null,
            'po_manual_gstin' => $poRow->manual_gstin ?? null,
            'po_manual_company_name' => $poRow->manual_company_name ?? null,
            'po_manual_company_address' => $poRow->manual_company_address ?? null,
            'po_manual_city' => $poRow->manual_city ?? null,
            'po_manual_state' => $poRow->manual_state ?? null,
            'po_manual_country' => $poRow->manual_country ?? null,
            'po_manual_pincode' => $poRow->manual_pincode ?? null,
            'aadhar_verified' => $poRow->aadhar_verified ?? null,
            'aadhar_verified_at' => $poRow->aadhar_verified_at ?? null,
            'aadhaar_ref_no' => $poRow->aadhaar_ref_no ?? null,
            'aadhaar_masked' => $poRow->aadhaar_masked ?? null,
        ]);
    }

    /**
     * Build the Validator instance for order step 1 (no validate() call yet).
     */
    private function buildOrderRequestValidator(Request $request, bool $isCreate, ?int $ignoreOrderId = null): \Illuminate\Contracts\Validation\Validator
    {
        $rules = [];
        $orderColumns = $this->orderColumns();

        if (in_array('user_id', $orderColumns, true)) {
            $rules['user_id'] = ['required', 'integer'];
        }
        if (in_array('partner_id', $orderColumns, true)) {
            $rules['partner_id'] = ['nullable', 'integer', 'required_if:order_type,b2b,B2B'];
        }
        if (in_array('plan_id', $orderColumns, true)) {
            $rules['plan_id'] = ['nullable', 'integer'];
        }
        if (in_array('order_no', $orderColumns, true)) {
            $rules['order_no'] = ['nullable', 'string', 'max:20'];
            if ($isCreate) {
                $rules['order_no'][] = 'unique:orders,order_no';
            } elseif ($ignoreOrderId !== null) {
                $rules['order_no'][] = Rule::unique('orders', 'order_no')->ignore($ignoreOrderId);
            }
        }
        if (in_array('sr_no', $orderColumns, true)) {
            $rules['sr_no'] = ['nullable', 'integer', 'min:1'];
        }
        if (in_array('domain_name', $orderColumns, true)) {
            $rules['domain_name'] = ['required', 'string', 'max:255'];
        }
        if (in_array('transfer_domain', $orderColumns, true)) {
            $rules['transfer_domain'] = ['nullable', 'in:yes,no'];
        }
        if (in_array('payment_mode', $orderColumns, true)) {
            $rules['payment_mode'] = ['nullable', 'in:cash_bank,cash_hand,upi'];
        }
        if (in_array('bank_name', $orderColumns, true)) {
            $rules['bank_name'] = ['nullable', 'string', 'max:255'];
        }
        if (in_array('cheque_ref_no', $orderColumns, true)) {
            $rules['cheque_ref_no'] = ['nullable', 'string', 'max:255'];
        }
        if (in_array('upi_transaction_id', $orderColumns, true)) {
            $rules['upi_transaction_id'] = ['nullable', 'string', 'max:255'];
        }
        if (in_array('order_note', $orderColumns, true)) {
            $rules['order_note'] = ['nullable', 'string'];
        }
        if (in_array('promo_code', $orderColumns, true)) {
            $rules['promocode'] = ['nullable', 'string', 'max:100'];
        }
        if (in_array('discount_amount', $orderColumns, true)) {
            $rules['discount_amount'] = ['nullable', 'numeric', 'min:0'];
        }
        if (in_array('apply_gst', $orderColumns, true)) {
            $rules['apply_gst'] = ['nullable', 'in:0,1'];
        }
        if (in_array('gst_rate', $orderColumns, true)) {
            $rules['gst_rate'] = ['nullable', 'numeric', 'min:0', 'max:100'];
        }
        if (in_array('gst_amount', $orderColumns, true)) {
            $rules['gst_amount'] = ['nullable', 'numeric', 'min:0'];
        }
        if (in_array('tax_amount', $orderColumns, true)) {
            $rules['tax_amount'] = ['nullable', 'numeric', 'min:0'];
        }
        $hasPoDetailsTable = true;
        if ($hasPoDetailsTable || in_array('po_mode', $orderColumns, true)) {
            $rules['po_mode'] = ['nullable', 'in:generated,uploaded'];
        }
        if ($hasPoDetailsTable || in_array('po_number', $orderColumns, true)) {
            $rules['po_number'] = ['nullable', 'string', 'max:100'];
        }
        if ($hasPoDetailsTable || in_array('po_date', $orderColumns, true)) {
            $rules['po_date'] = ['nullable', 'date'];
        }
        if ($hasPoDetailsTable || in_array('po_amount', $orderColumns, true)) {
            $rules['po_amount'] = ['nullable', 'numeric', 'min:0'];
        }
        if ($hasPoDetailsTable || in_array('po_document_url', $orderColumns, true)) {
            $rules['po_document_url'] = ['nullable', 'string', 'max:1000'];
        }
        $rules['po_document'] = ['nullable', 'file', 'mimes:pdf', 'max:5120'];
        if ($hasPoDetailsTable || in_array('po_manual_name', $orderColumns, true)) {
            $rules['po_manual_name'] = ['nullable', 'string', 'max:255'];
        }
        if ($hasPoDetailsTable || in_array('po_manual_email', $orderColumns, true)) {
            $rules['po_manual_email'] = ['nullable', 'email', 'max:255'];
        }
        if ($hasPoDetailsTable || in_array('po_manual_mobile', $orderColumns, true)) {
            $rules['po_manual_mobile'] = ['nullable', 'string', 'max:30'];
        }
        if ($hasPoDetailsTable || in_array('po_manual_gstin', $orderColumns, true)) {
            $rules['po_manual_gstin'] = ['nullable', 'string', 'max:20'];
        }
        if ($hasPoDetailsTable || in_array('po_manual_company_name', $orderColumns, true)) {
            $rules['po_manual_company_name'] = ['nullable', 'string', 'max:255'];
        }
        if ($hasPoDetailsTable || in_array('po_manual_company_address', $orderColumns, true)) {
            $rules['po_manual_company_address'] = ['nullable', 'string', 'max:2000'];
        }
        if ($hasPoDetailsTable || in_array('po_manual_city', $orderColumns, true)) {
            $rules['po_manual_city'] = ['nullable', 'string', 'max:255'];
        }
        if ($hasPoDetailsTable || in_array('po_manual_state', $orderColumns, true)) {
            $rules['po_manual_state'] = ['nullable', 'string', 'max:255'];
        }
        if ($hasPoDetailsTable || in_array('po_manual_country', $orderColumns, true)) {
            $rules['po_manual_country'] = ['nullable', 'string', 'max:255'];
        }
        if ($hasPoDetailsTable || in_array('po_manual_pincode', $orderColumns, true)) {
            $rules['po_manual_pincode'] = ['nullable', 'string', 'max:20'];
        }
        if ($hasPoDetailsTable || in_array('po_journey_status', $orderColumns, true)) {
            $rules['po_journey_status'] = ['nullable', 'in:po_pending,po_done,aadhar_done,otp_done,placed'];
        }
        if (in_array('order_type', $orderColumns, true)) {
            $rules['order_type'] = ['required', 'in:b2b,b2c,B2B,B2C'];
        }
        if (in_array('status', $orderColumns, true)) {
            $rules['status'] = ['nullable', 'string', 'max:50'];
        }
        if (in_array('credit_used', $orderColumns, true)) {
            $rules['credit_used'] = ['nullable', 'numeric', 'min:0'];
        }
        if (in_array('quantity', $orderColumns, true)) {
            $rules['quantity'] = ['nullable', 'integer', 'min:1'];
        }
        if (in_array('subscription_start_date', $orderColumns, true) || in_array('subscription_start', $orderColumns, true)) {
            $rules['subscription_start_date'] = ['nullable', 'date'];
            $rules['subscription_start'] = ['nullable', 'date'];
        }
        if (in_array('subscription_end_date', $orderColumns, true) || in_array('subscription_end', $orderColumns, true)) {
            $rules['subscription_end_date'] = ['nullable', 'date', 'after_or_equal:subscription_start_date'];
            $rules['subscription_end'] = ['nullable', 'date'];
        }

        $amountColumn = $this->getAmountColumn();
        if ($amountColumn !== null) {
            $rules['amount'] = ['required', 'numeric', 'min:0'];
        }

        return Validator::make($request->all(), $rules);
    }

    /**
     * Full server-side validation for order step 1 (normal form POST without AJAX).
     * Call {@see normalizePoInputsForValidation} before this when appropriate.
     */
    private function validateOrderRequest(Request $request, bool $isCreate = true, ?int $ignoreOrderId = null): void
    {
        $this->buildOrderRequestValidator($request, $isCreate, $ignoreOrderId)->validate();
    }

    private function isDomainAvailableForRegistration(string $domainName): bool
    {
        $domainName = strtolower(trim($domainName));
        if ($domainName === '' || !str_contains($domainName, '.')) {
            return false;
        }

        try {
            $cfg = $this->getDomaincheckConfigFromSettings();
            $baseUrl = rtrim((string) ($cfg['base_url'] ?? 'https://httpapi.com'), '/');
            $apiKey  = trim((string) ($cfg['api_key'] ?? ''));
            $authUserid = $this->resolveDomaincheckAuthUserId((string) ($cfg['auth_userid'] ?? ''));

            if ($apiKey !== '' && $authUserid > 0) {
                $parts = explode('.', $domainName, 2);
                $sld = $parts[0] ?? '';
                $tld = $parts[1] ?? '';
                if ($sld !== '' && $tld !== '') {
                    $url = $baseUrl . '/api/domains/available.json';
                    $query = [
                        'auth-userid' => $authUserid,
                        'api-key' => $apiKey,
                        'domain-name' => $sld,
                        'tlds' => $tld,
                    ];

                    Log::info('Calling Domain API', ['url' => $url, 'domain' => $domainName]);

                    $response = Http::timeout(15)
                        ->withHeaders([
                            'Accept' => 'application/json, text/plain, */*',
                            'User-Agent' => 'Mozilla/5.0 (compatible; TizzyBilling/1.0)',
                        ])
                        ->get($url, $query);

                    Log::info('Domain API Response', [
                        'domain' => $domainName,
                        'status_code' => $response->status(),
                        'response_json' => $response->json(),
                    ]);

                    if ($response->successful()) {
                        $result = $this->extractDomainAvailabilityFromResponse($response->json(), $domainName);
                        Log::info('Domain availability result', ['domain' => $domainName, 'available' => $result]);

                        return $result;
                    }

                    Log::warning('Domaincheck HTTP error — falling back to DNS', [
                        'status' => $response->status(),
                        'domain' => $domainName,
                        'body' => substr((string) $response->body(), 0, 300),
                    ]);
                }
            } else {
                Log::warning('Domaincheck not configured — falling back to DNS', [
                    'domain' => $domainName,
                    'has_api_key' => $apiKey !== '',
                    'auth_userid' => $authUserid,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Domaincheck exception — falling back to DNS', [
                'domain' => $domainName,
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback: treat as available-to-register only when no DNS records exist.
        return $this->isDomainOpenByDns($domainName);
    }

    /**
     * DNS heuristic: no A/MX/NS ⇒ likely unregistered / not resolvable.
     */
    private function isDomainOpenByDns(string $domainName): bool
    {
        $domainName = strtolower(trim($domainName));
        if ($domainName === '') {
            return false;
        }

        $hasDns = @checkdnsrr($domainName, 'A')
            || @checkdnsrr($domainName, 'AAAA')
            || @checkdnsrr($domainName, 'MX')
            || @checkdnsrr($domainName, 'NS');

        return !$hasDns;
    }

    private function getDomaincheckConfigFromSettings(): array
    {
        $out = [
            'base_url' => (string) config('services.domaincheck.base_url', 'https://domaincheck.httpapi.com'),
            'auth_userid' => (string) config('services.domaincheck.auth_userid', ''),
            'api_key' => (string) config('services.domaincheck.api_key', ''),
        ];

        $map = [
            'domaincheck_base_url' => 'base_url',
            'domaincheck_auth_userid' => 'auth_userid',
            'domaincheck_api_key' => 'api_key',
        ];
        foreach ($this->pvSettings->newQuery()->whereIn('key_id', array_keys($map))->pluck('key_secret', 'key_id') as $k => $v) {
            if ($v !== null && $v !== '') {
                $out[$map[$k]] = (string) $v;
            }
        }

        return $out;
    }

    /**
     * Normalise the Domaincheck auth-userid into the numeric Reseller ID the
     * API expects. Strips whitespace, surrounding quotes, and any non-digit
     * prefix/suffix; if that yields nothing usable, falls back to the .env
     * value (DOMAINCHECK_AUTH_USERID). Returns 0 when no digits can be found.
     */
    private function resolveDomaincheckAuthUserId(string $raw): int
    {
        $candidate = trim($raw, " \t\n\r\0\x0B\"'");
        $digits = preg_replace('/\D+/', '', $candidate);
        if ($digits !== '' && (int) $digits > 0) {
            return (int) $digits;
        }

        // pv_setting value was junk (email, blank, prefixed text). Fall back
        // to .env so a misconfigured DB row never breaks the registrar call.
        $envValue = (string) config('services.domaincheck.auth_userid', '');
        $envDigits = preg_replace('/\D+/', '', trim($envValue, " \t\n\r\0\x0B\"'"));
        return $envDigits !== '' ? (int) $envDigits : 0;
    }

    private function extractDomainAvailabilityFromResponse($payload, string $domainName): bool
    {
        if (!is_array($payload)) {
            return false;
        }

        // Direct check for the domain in the response
        if (isset($payload[$domainName]) && is_array($payload[$domainName])) {
            $domainData = $payload[$domainName];
            if (isset($domainData['status'])) {
                $status = strtolower(trim($domainData['status']));
                // If status is "available", domain is free to register
                if ($status === 'available') {
                    return true;  // Domain is AVAILABLE for registration
                }
                // Any other status (regthroughothers, unknown, etc.) means domain is registered
                return false;  // Domain is REGISTERED/TAKEN
            }
        }

        // Fallback: loop through response to find domain (backward compatibility)
        foreach ($payload as $key => $value) {
            if (is_string($key) && strcasecmp($key, $domainName) === 0 && is_array($value)) {
                if (isset($value['status'])) {
                    $status = strtolower(trim($value['status']));
                    return $status === 'available';
                }
            }
        }

        return false;
    }

    private function buildOrderPayload(Request $request, bool $isCreate = true, ?int $existingOrderId = null): array
    {
        $orderColumns = $this->orderColumns();
        $payload = [];

        $map = [
            'user_id' => 'user_id',
            'partner_id' => 'partner_id',
            'plan_id' => 'plan_id',
            'domain_name' => 'domain_name',
            'transfer_domain' => 'transfer_domain',
            'order_type' => 'order_type',
            'status' => 'status',
            'payment_mode' => 'payment_mode',
            'bank_name' => 'bank_name',
            'cheque_ref_no' => 'cheque_ref_no',
            'upi_transaction_id' => 'upi_transaction_id',
            'order_note' => 'order_note',
            'promo_code' => 'promocode',
            'discount_amount' => 'discount_amount',
            'apply_gst' => 'apply_gst',
            'gst_rate' => 'gst_rate',
            'gst_amount' => 'gst_amount',
            'tax_amount' => 'tax_amount',
            'po_mode' => 'po_mode',
            'po_number' => 'po_number',
            'po_date' => 'po_date',
            'po_amount' => 'po_amount',
            'po_document_url' => 'po_document_url',
            'po_manual_name' => 'po_manual_name',
            'po_manual_email' => 'po_manual_email',
            'po_manual_mobile' => 'po_manual_mobile',
            'po_manual_gstin' => 'po_manual_gstin',
            'po_manual_company_name' => 'po_manual_company_name',
            'po_manual_company_address' => 'po_manual_company_address',
            'po_journey_status' => 'po_journey_status',
        ];

        foreach ($map as $column => $inputKey) {
            if (in_array($column, $orderColumns, true) && $request->has($inputKey)) {
                $payload[$column] = $request->input($inputKey) !== '' ? $request->input($inputKey) : null;
            }
        }

        if (in_array('promo_code', $orderColumns, true) && empty($payload['promo_code'])) {
            $formPromo = strtoupper(trim((string) $request->input('promo_code', $request->input('promocode', ''))));
            if ($formPromo !== '') {
                $payload['promo_code'] = $formPromo;
            }
        }
        if (in_array('promo_code', $orderColumns, true) && empty($payload['promo_code'])) {
            $cartCoupon = strtoupper(trim((string) $request->input('coupen', '')));
            if ($cartCoupon !== '') {
                $payload['promo_code'] = $cartCoupon;
            }
        }

        // Status is system-controlled.
        // - B2C create: completed directly (no PO/Aadhaar journey)
        // - B2B create: pending until Aadhaar verification is completed
        // - Update: completed when Aadhaar already verified; otherwise unchanged
        if (in_array('status', $orderColumns, true)) {
            if ($isCreate) {
                $requestedType = strtolower(trim((string) $request->input('order_type', 'b2c')));
                $payload['status'] = $requestedType === 'b2c' ? 'completed' : 'pending';
            } else {
                $aadhaarVerified = false;
                if ($existingOrderId > 0) {
                    $orderRow = $this->orders->newQuery()->where('id', $existingOrderId)->first();
                    if ($orderRow) {
                        if (true) {
                            $aadhaarVerified = (int) ($orderRow->aadhar_verified ?? 0) === 1;
                        }
                        if (!$aadhaarVerified) {
                            $poCols = $this->orderPoDetailColumns();
                            $poRow = in_array('order_id', $poCols, true)
                                ? $this->orderPoDetails->newQuery()->where('order_id', $existingOrderId)->first()
                                : null;
                            if ($poRow && in_array('aadhar_verified', $poCols, true)) {
                                $aadhaarVerified = (int) ($poRow->aadhar_verified ?? 0) === 1;
                            }
                            if (!$aadhaarVerified && $poRow && in_array('journey_status', $poCols, true)) {
                                $aadhaarVerified = in_array(
                                    strtolower(trim((string) ($poRow->journey_status ?? ''))),
                                    ['aadhar_done', 'otp_done', 'placed'],
                                    true
                                );
                            }
                        }
                    }
                }
                if ($aadhaarVerified) {
                    $payload['status'] = 'completed';
                } else {
                    unset($payload['status']);
                }
            }
        }

        if (in_array('apply_gst', $orderColumns, true)) {
            $applyGstVal = (int) ($request->input('apply_gst', 0) ? 1 : 0);
            $payload['apply_gst'] = $applyGstVal;
            if ($applyGstVal !== 1) {
                if (in_array('gst_rate', $orderColumns, true)) $payload['gst_rate'] = null;
                if (in_array('gst_amount', $orderColumns, true)) $payload['gst_amount'] = 0;
                if (in_array('tax_amount', $orderColumns, true)) $payload['tax_amount'] = 0;
            }
        }

        if (in_array('quantity', $orderColumns, true)) {
            if ($request->has('quantity')) {
                $qty = $request->input('quantity');
                if ($qty === '' || $qty === null) {
                    if ($isCreate) {
                        $payload['quantity'] = 1;
                    }
                } else {
                    $payload['quantity'] = max(1, (int) $qty);
                }
            } elseif ($isCreate) {
                $payload['quantity'] = 1;
            }
        }

        // All catalogue plans are 12-month subscriptions — store literal label (no calculation).
        if (in_array('duration', $orderColumns, true)) {
            $payload['duration'] = '12 month';
        }

        $amountColumn = $this->getAmountColumn();
        if ($amountColumn !== null && $request->has('amount')) {
            $payload[$amountColumn] = $request->input('amount');
        }

        $this->applyPromoPricingOnPayload($payload, $request, $orderColumns, $amountColumn);

        $planIdForTotals = (int) ($payload['plan_id'] ?? $request->input('plan_id', 0));
        $qtyForTotals = max(1, (int) ($payload['quantity'] ?? $request->input('quantity', 1)));
        $orderTypeForTotals = strtolower(trim((string) ($payload['order_type'] ?? $request->input('order_type', 'b2c'))));
        $useFormTotals = $request->boolean('defer_po_save') && is_numeric($request->input('total_amount'));
        if ($useFormTotals) {
            $grossSubtotalForTotals = round((float) $request->input('subtotal', 0), 2);
            $discountForTotals = round((float) $request->input('discount_amount', 0), 2);
            $grandForTotals = round((float) $request->input('total_amount'), 2);
            $gstForTotals = round((float) $request->input('gst_amount', $request->input('tax_amount', 0)), 2);
            if (in_array('subtotal', $orderColumns, true)) {
                $payload['subtotal'] = $grossSubtotalForTotals;
            }
            if (in_array('discount_amount', $orderColumns, true)) {
                $payload['discount_amount'] = $discountForTotals;
            }
            if (in_array('gst_amount', $orderColumns, true)) {
                $payload['gst_amount'] = $gstForTotals;
            }
            if (in_array('tax_amount', $orderColumns, true)) {
                $payload['tax_amount'] = $gstForTotals;
            }
            if ($amountColumn !== null) {
                $payload[$amountColumn] = $grandForTotals;
            }
            if (in_array('total_amount', $orderColumns, true)) {
                $payload['total_amount'] = $grandForTotals;
            }
        } else {
            if ($request->boolean('defer_po_save') && is_numeric($request->input('subtotal'))) {
                $grossSubtotalForTotals = round((float) $request->input('subtotal'), 2);
            } else {
                $grossSubtotalForTotals = $this->resolvePlanBaseAmount($planIdForTotals, $orderTypeForTotals, $qtyForTotals);
                if ($grossSubtotalForTotals <= 0 && in_array('subtotal', $orderColumns, true) && isset($payload['subtotal'])) {
                    $grossSubtotalForTotals = (float) $payload['subtotal'];
                }
            }
            if ($grossSubtotalForTotals > 0) {
                $discountForTotals = max(0, (float) ($payload['discount_amount'] ?? $request->input('discount_amount', 0)));
                $discountForTotals = min($discountForTotals, $grossSubtotalForTotals);
                $applyGstForTotals = !isset($payload['apply_gst']) || (int) ($payload['apply_gst'] ?? 0) === 1;
                $gstRateForTotals = $applyGstForTotals
                    ? max(0, (float) ($payload['gst_rate'] ?? $request->input('gst_rate', 18)))
                    : 0.0;
                $taxableForTotals = max(0, round($grossSubtotalForTotals - $discountForTotals, 2));
                $gstForTotals = $applyGstForTotals
                    ? round($taxableForTotals * $gstRateForTotals / 100, 2)
                    : 0.0;
                $grandForTotals = round($taxableForTotals + $gstForTotals, 2);
                if (in_array('subtotal', $orderColumns, true)) {
                    $payload['subtotal'] = round($grossSubtotalForTotals, 2);
                }
                if (in_array('gst_amount', $orderColumns, true)) {
                    $payload['gst_amount'] = $gstForTotals;
                }
                if (in_array('tax_amount', $orderColumns, true)) {
                    $payload['tax_amount'] = $gstForTotals;
                }
                if ($amountColumn !== null) {
                    $payload[$amountColumn] = $grandForTotals;
                }
                if (in_array('total_amount', $orderColumns, true)) {
                    $payload['total_amount'] = $grandForTotals;
                }
            }
        }

        if ($isCreate && in_array('created_at', $orderColumns, true)) {
            $payload['created_at'] = now();
        }
        if (in_array('updated_at', $orderColumns, true)) {
            $payload['updated_at'] = now();
        }

        if ($isCreate && in_array('order_category', $orderColumns, true)) {
            $requestCategory = strtolower(trim((string) $request->input('order_category', '')));
            if (in_array($requestCategory, ['new', 'existing'], true)) {
                $payload['order_category'] = $requestCategory;
            } else {
                $existingB2bQuery = $this->orders->newQuery();
                $hasExisting = false;
                if (!empty($payload['user_id']) && in_array('user_id', $orderColumns, true)) {
                    $existingB2bQuery->where('user_id', (int) $payload['user_id']);
                    if (in_array('order_type', $orderColumns, true)) {
                        $existingB2bQuery->whereRaw("LOWER(COALESCE(order_type,'b2c')) = 'b2b'");
                    }
                    if (in_array('partner_id', $orderColumns, true) && !empty($payload['partner_id'])) {
                        $existingB2bQuery->where('partner_id', (int) $payload['partner_id']);
                    }
                    $hasExisting = (int) $existingB2bQuery->count() > 0;
                }
                $payload['order_category'] = $hasExisting ? 'renewal' : 'new';
            }
        }

        // B2C: partner/credit are hidden in the UI; ensure DB is cleared even when inputs are disabled.
        $orderTypeVal = strtolower(trim((string) ($payload['order_type'] ?? $request->input('order_type', ''))));
        if ($orderTypeVal === 'b2c') {
            if (in_array('partner_id', $orderColumns, true)) {
                $payload['partner_id'] = null;
            }
            if (in_array('credit_used', $orderColumns, true)) {
                $payload['credit_used'] = 0;
            }
        } elseif ($orderTypeVal === 'b2b') {
            if (in_array('credit_used', $orderColumns, true)) {
                $creditAmount = 0.0;
                if ($amountColumn !== null && isset($payload[$amountColumn]) && is_numeric($payload[$amountColumn])) {
                    $creditAmount = max(0, (float) $payload[$amountColumn]);
                }
                $payload['credit_used'] = $creditAmount;
            }
            // Ensure partner_id is stored on create/update for B2B orders.
            if (
                in_array('partner_id', $orderColumns, true) &&
                (empty($payload['partner_id']) || (int) $payload['partner_id'] <= 0) &&
                !empty($payload['user_id'])
            ) {
                $customerPartnerId = $this->customers->newQuery()
                    ->where('id', (int) $payload['user_id'])
                    ->value('partner_id');
                if (!empty($customerPartnerId)) {
                    $payload['partner_id'] = (int) $customerPartnerId;
                }
            }
        }

        $this->applySubscriptionDatesToOrderPayload($payload, $request, $orderColumns, $isCreate);

        if (
            $isCreate
            && $orderTypeVal === 'b2b'
            && !empty($payload['user_id'])
            && !$this->deferPoSaveOnOrder($request)
        ) {
            $this->applyCustomerSnapshotToOrderPayload($payload, (int) $payload['user_id'], $orderColumns);
            if (in_array('po_journey_status', $orderColumns, true) && empty($payload['po_journey_status'])) {
                $payload['po_journey_status'] = 'po_pending';
            }
        }

        if (!$isCreate) {
            unset($payload['order_category']);
        }

        return array_intersect_key($payload, array_flip($orderColumns));
    }

    /**
     * Super Admin order wizard: step 1 saves order only; PO is saved on step 2 submit.
     */
    private function deferPoSaveOnOrder(Request $request): bool
    {
        return $request->boolean('defer_po_save');
    }

    /**
     * Stamp B2B order + order_po_details from the linked customer (PO wizard step 2 prefill).
     */
    public function seedB2bOrderCustomerSnapshot(int $orderId, ?float $poAmount = null): void
    {
        if ($orderId <= 0) {
            return;
        }

        $order = $this->orders->newQuery()->where('id', $orderId)->first();
        if (!$order) {
            return;
        }

        $orderType = strtolower(trim((string) ($order->order_type ?? '')));
        if ($orderType !== 'b2b') {
            return;
        }

        $userId = (int) ($order->user_id ?? 0);
        if ($userId <= 0) {
            return;
        }

        $prefill = app(RenewalService::class)->buildPurchaseOrderPrefillFromCustomer($userId);
        if ($prefill === []) {
            return;
        }

        $orderColumns = $this->orderColumns();
        $orderUpdate = [];
        $orderFieldMap = [
            'po_manual_name' => 'name',
            'po_manual_email' => 'email',
            'po_manual_mobile' => 'mobile',
            'po_manual_gstin' => 'gstin',
            'po_manual_company_name' => 'company_name',
            'po_manual_company_address' => 'address',
            'po_manual_city' => 'city',
            'po_manual_state' => 'state',
            'po_manual_country' => 'country',
            'po_manual_pincode' => 'postcode',
        ];
        foreach ($orderFieldMap as $column => $prefillKey) {
            if (!in_array($column, $orderColumns, true)) {
                continue;
            }
            $existing = trim((string) ($order->{$column} ?? ''));
            $value = trim((string) ($prefill[$prefillKey] ?? ''));
            if ($value !== '' && $existing === '') {
                $orderUpdate[$column] = $value;
            }
        }
        if (in_array('po_journey_status', $orderColumns, true)) {
            $journey = strtolower(trim((string) ($order->po_journey_status ?? '')));
            if ($journey === '') {
                $orderUpdate['po_journey_status'] = 'po_pending';
            }
        }
        if (in_array('status', $orderColumns, true)) {
            $status = strtolower(trim((string) ($order->status ?? '')));
            if ($status === '') {
                $orderUpdate['status'] = 'pending';
            }
        }
        if ($orderUpdate !== []) {
            if (in_array('updated_at', $orderColumns, true)) {
                $orderUpdate['updated_at'] = now();
            }
            $this->orders->newQuery()->where('id', $orderId)->update($orderUpdate);
            $order = (object) array_merge((array) $order, $orderUpdate);
        }

        $cols = $this->orderPoDetailColumns();
        if (!in_array('order_id', $cols, true)) {
            return;
        }

        $existingPo = $this->orderPoDetails->newQuery()->where('order_id', $orderId)->first();
        $poRow = ['order_id' => $orderId];
        $poFieldMap = [
            'manual_name' => 'name',
            'manual_email' => 'email',
            'manual_mobile' => 'mobile',
            'manual_gstin' => 'gstin',
            'manual_company_name' => 'company_name',
            'manual_company_address' => 'address',
            'manual_city' => 'city',
            'manual_state' => 'state',
            'manual_country' => 'country',
            'manual_pincode' => 'postcode',
        ];
        foreach ($poFieldMap as $column => $prefillKey) {
            if (!in_array($column, $cols, true)) {
                continue;
            }
            $existing = $existingPo ? trim((string) ($existingPo->{$column} ?? '')) : '';
            $value = trim((string) ($prefill[$prefillKey] ?? ''));
            if ($value !== '' && $existing === '') {
                $poRow[$column] = $value;
            }
        }

        if ($poAmount === null) {
            foreach (['total_amount', 'amount', 'price', 'subtotal'] as $amountCol) {
                if (isset($order->{$amountCol}) && (float) $order->{$amountCol} > 0) {
                    $poAmount = (float) $order->{$amountCol};
                    break;
                }
            }
        }
        if ($poAmount !== null && $poAmount > 0 && in_array('po_amount', $cols, true)) {
            $existingAmount = $existingPo ? (float) ($existingPo->po_amount ?? 0) : 0.0;
            if ($existingAmount <= 0) {
                $poRow['po_amount'] = $poAmount;
            }
        }

        if (in_array('journey_status', $cols, true)) {
            $journey = $existingPo ? strtolower(trim((string) ($existingPo->journey_status ?? ''))) : '';
            if ($journey === '' || $journey === 'placed') {
                $poRow['journey_status'] = 'po_pending';
            }
        }
        if (in_array('aadhar_verified', $cols, true)) {
            $verified = $existingPo ? (int) ($existingPo->aadhar_verified ?? 0) : 0;
            if ($verified !== 1) {
                $poRow['aadhar_verified'] = 0;
            }
        }

        if (count($poRow) <= 1) {
            return;
        }

        if (in_array('updated_at', $cols, true)) {
            $poRow['updated_at'] = now();
        }

        if ($existingPo) {
            $update = $poRow;
            unset($update['order_id']);
            $this->orderPoDetails->newQuery()->where('order_id', $orderId)->update($update);
        } else {
            if (in_array('created_at', $cols, true)) {
                $poRow['created_at'] = now();
            }
            $this->orderPoDetails->newQuery()->insert($poRow);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $orderColumns
     */
    private function applyCustomerSnapshotToOrderPayload(array &$payload, int $customerId, array $orderColumns): void
    {
        $prefill = app(RenewalService::class)->buildPurchaseOrderPrefillFromCustomer($customerId);
        if ($prefill === []) {
            return;
        }

        $map = [
            'po_manual_name' => 'name',
            'po_manual_email' => 'email',
            'po_manual_mobile' => 'mobile',
            'po_manual_gstin' => 'gstin',
            'po_manual_company_name' => 'company_name',
            'po_manual_company_address' => 'address',
            'po_manual_city' => 'city',
            'po_manual_state' => 'state',
            'po_manual_country' => 'country',
            'po_manual_pincode' => 'postcode',
        ];

        foreach ($map as $column => $prefillKey) {
            if (!in_array($column, $orderColumns, true)) {
                continue;
            }
            if (!empty($payload[$column])) {
                continue;
            }
            $value = trim((string) ($prefill[$prefillKey] ?? ''));
            if ($value !== '') {
                $payload[$column] = $value;
            }
        }
    }

    /**
     * On create, set subscription start/end when those columns exist (from request or plan billing_cycle).
     */
    private function applySubscriptionDatesToOrderPayload(array &$payload, Request $request, array $orderColumns, bool $isCreate): void
    {
        $startCol = null;
        foreach (['subscription_start_date', 'subscription_start'] as $c) {
            if (in_array($c, $orderColumns, true)) {
                $startCol = $c;
                break;
            }
        }
        $endCol = null;
        foreach (['subscription_end_date', 'subscription_end'] as $c) {
            if (in_array($c, $orderColumns, true)) {
                $endCol = $c;
                break;
            }
        }
        if ($startCol === null && $endCol === null) {
            return;
        }

        $rqStart = $request->input('subscription_start_date', $request->input('subscription_start'));
        $rqEnd = $request->input('subscription_end_date', $request->input('subscription_end'));
        $orderCategory = strtolower(trim((string) $request->input('order_category', '')));
        $isExistingCategory = $orderCategory === 'existing';
        if (!empty($rqStart)) {
            try {
                $start = Carbon::parse((string) $rqStart);
                if ($startCol !== null) {
                    $payload[$startCol] = $start->toDateString();
                }
                if ($endCol !== null) {
                    if ($isExistingCategory) {
                        $payload[$endCol] = null;
                    } elseif ($isCreate) {
                        if (!empty($rqEnd)) {
                            $payload[$endCol] = Carbon::parse((string) $rqEnd)->toDateString();
                        } else {
                            $payload[$endCol] = $start->copy()->addYear()->subDay()->toDateString();
                        }
                    } elseif (!empty($rqEnd)) {
                        $payload[$endCol] = Carbon::parse((string) $rqEnd)->toDateString();
                    }
                }

                return;
            } catch (\Throwable $e) {
                // fall through to plan-based defaults
            }
        }

        if (!$isCreate) {
            return;
        }

        $planId = (int) ($payload['plan_id'] ?? $request->input('plan_id', 0));
        $start = Carbon::now()->startOfDay();
        $end = $start->copy();

        if ($planId > 0) {
            $plan = $this->plans->newQuery()->where('id', $planId)->first(['billing_cycle']);
            $cycle = strtolower((string) ($plan->billing_cycle ?? 'yearly'));
            if ($cycle === 'monthly') {
                $end = $start->copy()->addMonth();
            } else if ($cycle === '12 month' || $cycle === '12 months') {
                $end = $start->copy()->addYear()->subDay();
            } else {
                $end = $start->copy()->addYear()->subDay();
            }
        } else {
            $end = $start->copy()->addYear()->subDay();
        }

        if ($startCol !== null) {
            $payload[$startCol] = $start->toDateString();
        }
        if ($endCol !== null) {
            $payload[$endCol] = $end->toDateString();
        }
    }

    private function applyPromoPricingOnPayload(array &$payload, Request $request, array $orderColumns, ?string $amountColumn): void
    {
        $hasPromoCode = in_array('promo_code', $orderColumns, true);
        $hasDiscountAmount = in_array('discount_amount', $orderColumns, true);
        if (!$hasPromoCode && !$hasDiscountAmount) {
            return;
        }

        if ($request->boolean('defer_po_save') && is_numeric($request->input('total_amount'))) {
            $promoCode = strtoupper(trim((string) $request->input('promocode', $request->input('promo_code', ''))));
            if ($promoCode === '') {
                $promoCode = strtoupper(trim((string) $request->input('coupen', '')));
            }
            if ($hasPromoCode && $promoCode !== '') {
                $payload['promo_code'] = $promoCode;
            }
            if (in_array('coupen', $orderColumns, true) && $promoCode !== '') {
                $payload['coupen'] = $promoCode;
            }

            $subtotalForPromo = round((float) $request->input('subtotal', 0), 2);
            $discountAmount = round((float) $request->input('discount_amount', 0), 2);
            if ($promoCode !== '' && $subtotalForPromo > 0) {
                $promo = Promocode::query()
                    ->whereRaw('UPPER(TRIM(code)) = ?', [$promoCode])
                    ->validNow()
                    ->first();
                if ($promo) {
                    $discountPercent = max(0.0, min(100.0, (float) ($promo->discount_percent ?? 0)));
                    $computedDiscount = round(($subtotalForPromo * $discountPercent) / 100, 2);
                    $computedDiscount = min($computedDiscount, $subtotalForPromo);
                    if ($discountAmount <= 0 || abs($discountAmount - $computedDiscount) <= 0.05) {
                        $discountAmount = $computedDiscount;
                    }
                } elseif ($discountAmount <= 0) {
                    throw ValidationException::withMessages([
                        'promocode' => 'Invalid or expired promo code.',
                    ]);
                }
            }

            if ($hasDiscountAmount) {
                $payload['discount_amount'] = $discountAmount;
            }

            return;
        }

        $promoCode = strtoupper(trim((string) $request->input('promocode', $request->input('promo_code', ''))));
        if ($promoCode === '') {
            $promoCode = strtoupper(trim((string) $request->input('coupen', '')));
        }

        $explicitDiscount = max(0, (float) $request->input('discount_amount', 0));
        $useCartPricing = $request->boolean('use_cart_pricing')
            || ($promoCode !== '' && $explicitDiscount > 0);

        if ($useCartPricing) {
            if ($hasPromoCode && $promoCode !== '') {
                $payload['promo_code'] = $promoCode;
            }
            if (in_array('coupen', $orderColumns, true) && $promoCode !== '') {
                $payload['coupen'] = $promoCode;
            }
            if ($hasDiscountAmount) {
                $payload['discount_amount'] = round(min($explicitDiscount, max(0, (float) ($request->input('subtotal', 0)))), 2);
            }

            return;
        }

        $qty = max(1, (int) $request->input('quantity', 1));
        $planId = (int) $request->input('plan_id', 0);
        $orderType = strtolower(trim((string) $request->input('order_type', 'b2c')));
        $baseAmount = $this->resolvePlanBaseAmount($planId, $orderType, $qty);

        if ($baseAmount <= 0 && $amountColumn !== null && isset($payload[$amountColumn]) && is_numeric($payload[$amountColumn])) {
            $baseAmount = (float) $payload[$amountColumn];
        }

        $discountAmount = 0.0;
        if ($promoCode !== '') {
            $promo = Promocode::query()
                ->whereRaw('UPPER(TRIM(code)) = ?', [$promoCode])
                ->validNow()
                ->first();
            if (!$promo) {
                throw ValidationException::withMessages([
                    'promocode' => 'Invalid or expired promo code.',
                ]);
            }
            $discountPercent = max(0.0, min(100.0, (float) ($promo->discount_percent ?? 0)));
            $discountAmount = round(($baseAmount * $discountPercent) / 100, 2);
            $discountAmount = min($discountAmount, max(0, round($baseAmount, 2)));
        } else {
            $discountAmount = max(0, (float) $request->input('discount_amount', 0));
        }

        if ($hasPromoCode) {
            $payload['promo_code'] = $promoCode !== '' ? $promoCode : null;
        }
        if ($hasDiscountAmount) {
            $payload['discount_amount'] = $discountAmount;
        }
        if ($amountColumn !== null) {
            $request->merge(['discount_amount' => $discountAmount]);
            $payload[$amountColumn] = $this->finalizeOrderAmountFromBase($request, $baseAmount);
        }
    }

    private function resolvePlanBaseAmount(int $planId, string $orderType, int $qty): float
    {
        if ($planId <= 0) {
            return 0.0;
        }
        $plan = $this->plans->newQuery()->where('id', $planId)->first();
        if (!$plan) {
            return 0.0;
        }

        $unitPrice = 0.0;
        if ($orderType === 'b2b') {
            foreach (['price', 'b2b_price'] as $k) {
                if (isset($plan->{$k}) && is_numeric($plan->{$k}) && (float) $plan->{$k} > 0) {
                    $unitPrice = (float) $plan->{$k};
                    break;
                }
            }
            if ($unitPrice <= 0 && isset($plan->actual_price) && is_numeric($plan->actual_price) && (float) $plan->actual_price > 0) {
                $unitPrice = (float) $plan->actual_price;
            }
        } else {
            foreach (['b2c_price', 'actual_price', 'price', 'b2b_price'] as $k) {
                if (isset($plan->{$k}) && is_numeric($plan->{$k})) {
                    $unitPrice = (float) $plan->{$k};
                    break;
                }
            }
        }

        return round(max(0, $unitPrice) * max(1, $qty), 2);
    }

    private function getAmountColumn(): ?string
    {
        $orderColumns = $this->orderColumns();
        if (in_array('total_amount', $orderColumns, true)) {
            return 'total_amount';
        }
        if (in_array('subtotal', $orderColumns, true)) {
            return 'subtotal';
        }
        if (in_array('price', $orderColumns, true)) {
            return 'price';
        }

        return null;
    }

    private function getPlanOptions()
    {
        $columns = $this->planColumns();
        $nameCol = in_array('name', $columns, true) ? 'name' : (in_array('plan_name', $columns, true) ? 'plan_name' : (in_array('title', $columns, true) ? 'title' : null));
        if (!$nameCol) {
            return collect();
        }

        return $this->plans->newQuery()
            ->select(
                'id',
                DB::raw($nameCol . ' as name'),
                DB::raw($this->planB2bPriceSql() . ' as b2b_price'),
                DB::raw('COALESCE(actual_price, price, 0) as b2c_price'),
                DB::raw('COALESCE(customer_limit, 0) as customer_limit')
            )
            ->orderBy('id', 'desc')
            ->limit(500)
            ->get();
    }

    private function buildOrderFormProviderMeta(): array
    {
        $providers = collect();
        $hasVariantByProvider = [];
        $variantsByProvider = [];
        $mainVariantsByProvider = [];
        $subVariantsByMain = [];
        $providersQuery = $this->providers->newQuery();

        if (true) {
            $providersQuery->where('status', 'active');
        }
        $providers = $providersQuery->orderBy('name')->get();
        foreach ($providers as $p) {
            $pid = (int) $p->id;
            $hasVariantByProvider[$pid] = (($p->has_variant ?? 'no') === 'yes');
        }

        if ($providers->isNotEmpty()) {
            $ids = $providers->pluck('id')->map(static fn($id) => (int) $id)->all();
            $selectCols = ['id', 'provider_id', 'name'];
            if (true) {
                $selectCols[] = 'parent_variant_id';
            }
            $vrows = $this->variants->newQuery()
                ->whereIn('provider_id', $ids)
                ->orderBy('name')
                ->get($selectCols);
            foreach ($vrows as $v) {
                $pid = (int) $v->provider_id;
                $parentId = (int) ($v->parent_variant_id ?? 0);
                if (!isset($variantsByProvider[$pid])) {
                    $variantsByProvider[$pid] = [];
                }
                $variantsByProvider[$pid][] = [
                    'id' => (int) $v->id,
                    'name' => (string) $v->name,
                    'parent_variant_id' => $parentId > 0 ? $parentId : null,
                ];

                if ($parentId > 0) {
                    if (!isset($subVariantsByMain[$parentId])) {
                        $subVariantsByMain[$parentId] = [];
                    }
                    $subVariantsByMain[$parentId][] = [
                        'id' => (int) $v->id,
                        'name' => (string) $v->name,
                    ];
                } else {
                    if (!isset($mainVariantsByProvider[$pid])) {
                        $mainVariantsByProvider[$pid] = [];
                    }
                    $mainVariantsByProvider[$pid][] = [
                        'id' => (int) $v->id,
                        'name' => (string) $v->name,
                    ];
                }
            }
        }

        return compact('providers', 'hasVariantByProvider', 'variantsByProvider', 'mainVariantsByProvider', 'subVariantsByMain');
    }

    private function getPlansForProviderVariant(int $providerId, int $variantId = 0): \Illuminate\Support\Collection
    {
        if ($providerId <= 0) {
            return collect();
        }

        $provider = $this->providers->newQuery()->where('id', $providerId)->first();
        if (!$provider) {
            return collect();
        }

        $hasVariant = (($provider->has_variant ?? 'no') === 'yes');

        $query = $this->plans->newQuery()->where('provider_id', $providerId);
        //  print_r($query->get()->toArray()); die;
        if ($hasVariant) {
            if ($variantId > 0) {
                $query->where('variant_id', $variantId);
            }
        }

        $columns = $this->planColumns();
        $nameCol = in_array('name', $columns, true) ? 'name' : (in_array('plan_name', $columns, true) ? 'plan_name' : (in_array('title', $columns, true) ? 'title' : null));
        if (!$nameCol) {
            return collect();
        }
        // dd($columns);
        // dd($nameCol);

        return $query
            ->select(
                'id',
                DB::raw($nameCol . ' as name'),
                DB::raw($this->planB2bPriceSql() . ' as b2b_price'),
                DB::raw('COALESCE(actual_price, price, 0) as b2c_price'),
                DB::raw('COALESCE(customer_limit, 0) as customer_limit')
            )
            ->orderBy('id', 'desc')
            ->limit(500)
            ->get();
    }

    private function getCustomerOptions()
    {
        // 1. Grab the raw data simply
        $customers = $this->customers->newQuery()
            ->select('id', 'name', 'company_name', 'status')
            ->orderBy('id', 'desc')
            ->limit(500)
            ->get();

        // 2. Filter and Format using PHP logic
        return $customers->filter(function ($row) {
            // Basic status check (equivalent to your whereRaw)
            return in_array(strtolower(trim($row->status ?? '1')), ['1', 'active', 'approved']);
        })->map(function ($row) {
            return (object) [
                'id'   => $row->id,
                // Fallback logic: Name -> Company -> Default string
                'name' => $row->name ?: ($row->company_name ?: "Customer #{$row->id}"),
            ];
        });
    }

    /**
     * Customer choices for order form dropdown.
     * - B2B users come from `customers`
     * - B2C users come from `users`
     *
     * @return array<int, array{id: int, name: string, partner_id: int|null, source: string}>
     */
    /**
     * Dynamic customer list for order form (B2B partner-wise or B2C users).
     */
    public function customersSelect(Request $request)
    {
        $orderType = strtolower(trim((string) $request->input('order_type', 'b2b')));
        $partnerId = (int) $request->input('partner_id', 0);

        if ($orderType === 'b2b') {
            $customers = $this->fetchPartnerCustomersForOrderForm($partnerId);

            return response()->json(['customers' => $customers]);
        }

        $customers = array_values(array_filter(
            $this->getOrderFormCustomers(),
            static fn(array $row): bool => ($row['source'] ?? '') === 'b2c'
        ));

        return response()->json(['customers' => $customers]);
    }

    /**
     * Dynamic partner-wise customer list for the order form (AJAX GET).
     */
    public function partnerCustomers(int $partnerId)
    {
        return response()->json([
            'customers' => $this->fetchPartnerCustomersForOrderForm($partnerId),
        ]);
    }

    /**
     * @return list<array{id:int,name:string,credit_balance:float,partner_id?:int,source:string}>
     */
    private function fetchPartnerCustomersForOrderForm(int $partnerId): array
    {
        if ($partnerId <= 0) {
            return [];
        }

        $columns = $this->customerColumns();
        $nameExpression = "CONCAT('Customer #', id)";
        if (in_array('name', $columns, true) && in_array('company_name', $columns, true)) {
            $nameExpression = "COALESCE(NULLIF(name, ''), NULLIF(company_name, ''), CONCAT('Customer #', id))";
        } elseif (in_array('name', $columns, true)) {
            $nameExpression = "COALESCE(NULLIF(name, ''), CONCAT('Customer #', id))";
        } elseif (in_array('company_name', $columns, true)) {
            $nameExpression = "COALESCE(NULLIF(company_name, ''), CONCAT('Customer #', id))";
        }

        $query = $this->customers->newQuery()
            ->select('id', DB::raw($nameExpression . ' as name'))
            ->where('partner_id', $partnerId);
        foreach (['credit_limit', 'credit', 'wallet_balance', 'current_balance'] as $creditCol) {
            if (in_array($creditCol, $columns, true)) {
                $query->addSelect($creditCol);
            }
        }

        return $query->orderByDesc('id')->limit(1000)->get()->map(static function ($row) use ($partnerId) {
            $creditBalance = 0.0;
            foreach (['credit_limit', 'credit', 'wallet_balance', 'current_balance'] as $creditKey) {
                if (property_exists($row, $creditKey) && $row->{$creditKey} !== null && $row->{$creditKey} !== '') {
                    $creditBalance = (float) $row->{$creditKey};
                    break;
                }
            }

            return [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'partner_id' => $partnerId,
                'credit_balance' => $creditBalance,
                'source' => 'b2b',
            ];
        })->values()->all();
    }

    private function getOrderFormCustomers(): array
    {
        $all = [];

        if (true) {
            $columns = $this->customerColumns();
            $nameExpression = "CONCAT('Customer #', id)";
            if (in_array('name', $columns, true) && in_array('company_name', $columns, true)) {
                $nameExpression = "COALESCE(NULLIF(name, ''), NULLIF(company_name, ''), CONCAT('Customer #', id))";
            } elseif (in_array('name', $columns, true)) {
                $nameExpression = "COALESCE(NULLIF(name, ''), CONCAT('Customer #', id))";
            } elseif (in_array('company_name', $columns, true)) {
                $nameExpression = "COALESCE(NULLIF(company_name, ''), CONCAT('Customer #', id))";
            }

            $query = $this->customers->newQuery()->select('id', DB::raw($nameExpression . ' as name'));
            if (in_array('status', $columns, true)) {
                $query->whereRaw("LOWER(TRIM(CAST(status AS CHAR))) IN ('1','active','approved')");
            }
            if (in_array('partner_id', $columns, true)) {
                $query->addSelect('partner_id');
            }

            $b2b = $query->orderBy('id', 'desc')->limit(1000)->get()->map(static function ($row) {
                $partnerId = null;
                if (property_exists($row, 'partner_id') && $row->partner_id !== null && $row->partner_id !== '') {
                    $partnerId = (int) $row->partner_id;
                }
                $creditBalance = 0.0;
                foreach (['credit_limit', 'credit', 'wallet_balance', 'current_balance'] as $creditKey) {
                    if (property_exists($row, $creditKey) && $row->{$creditKey} !== null && $row->{$creditKey} !== '') {
                        $creditBalance = (float) $row->{$creditKey};
                        break;
                    }
                }

                return [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                    'partner_id' => $partnerId,
                    'credit_balance' => $creditBalance,
                    'source' => 'b2b',
                ];
            })->values()->all();

            $all = array_merge($all, $b2b);
        }

        if (true) {
            $columns = $this->userColumns();
            $nameExpression = "CONCAT('User #', id)";
            if (in_array('name', $columns, true) && in_array('company_name', $columns, true)) {
                $nameExpression = "COALESCE(NULLIF(name, ''), NULLIF(company_name, ''), CONCAT('User #', id))";
            } elseif (in_array('name', $columns, true)) {
                $nameExpression = "COALESCE(NULLIF(name, ''), CONCAT('User #', id))";
            } elseif (in_array('company_name', $columns, true)) {
                $nameExpression = "COALESCE(NULLIF(company_name, ''), CONCAT('User #', id))";
            }

            $userQuery = $this->users->newQuery()
                ->select('id', DB::raw($nameExpression . ' as name'));
            if (in_array('status', $columns, true)) {
                $userQuery->whereRaw("LOWER(TRIM(CAST(status AS CHAR))) IN ('1','active','approved')");
            }
            foreach (['credit_limit', 'credit', 'wallet_balance', 'current_balance'] as $creditCol) {
                if (in_array($creditCol, $columns, true)) {
                    $userQuery->addSelect($creditCol);
                }
            }

            $b2c = $userQuery
                ->orderBy('id', 'desc')
                ->limit(1000)
                ->get()
                ->map(static function ($row) {
                    $creditBalance = 0.0;
                    foreach (['credit_limit', 'credit', 'wallet_balance', 'current_balance'] as $creditKey) {
                        if (property_exists($row, $creditKey) && $row->{$creditKey} !== null && $row->{$creditKey} !== '') {
                            $creditBalance = (float) $row->{$creditKey};
                            break;
                        }
                    }

                    return [
                        'id' => (int) $row->id,
                        'name' => (string) $row->name,
                        'partner_id' => null,
                        'credit_balance' => $creditBalance,
                        'source' => 'b2c',
                    ];
                })
                ->values()
                ->all();

            $all = array_merge($all, $b2c);
        }

        return $all;
    }

    private function hasPoTrackingColumns(): bool
    {
        if (true) {
            return true;
        }
        $cols = $this->orderColumns();
        return in_array('po_mode', $cols, true) || in_array('po_journey_status', $cols, true);
    }

    private function upsertOrderPoDetails(int $orderId, Request $request): void
    {
        $cols = $this->orderPoDetailColumns();
        if (!in_array('order_id', $cols, true)) {
            return;
        }

        $payload = ['order_id' => $orderId];
        $map = [
            'po_mode' => 'po_mode',
            'po_number' => 'po_number',
            'po_date' => 'po_date',
            'po_amount' => 'po_amount',
            'po_document_url' => 'po_document_url',
            'manual_name' => 'po_manual_name',
            'manual_email' => 'po_manual_email',
            'manual_mobile' => 'po_manual_mobile',
            'manual_gstin' => 'po_manual_gstin',
            'manual_company_name' => 'po_manual_company_name',
            'manual_company_address' => 'po_manual_company_address',
            'manual_city' => 'po_manual_city',
            'manual_state' => 'po_manual_state',
            'manual_country' => 'po_manual_country',
            'manual_pincode' => 'po_manual_pincode',
            'journey_status' => 'po_journey_status',
        ];
        foreach ($map as $column => $inputKey) {
            if (in_array($column, $cols, true)) {
                $val = $request->input($inputKey);
                if (is_string($val)) {
                    $val = trim($val);
                }
                $payload[$column] = ($val === '' || $val === '-' || $val === 'null' ? null : $val);
            }
        }

        $mode = strtolower(trim((string) $request->input('po_mode', (string) ($payload['po_mode'] ?? ''))));
        if ($mode === 'generated') {
            if (in_array('po_number', $cols, true) && empty($payload['po_number'])) {
                $payload['po_number'] = $this->nextPoNumber();
            }
            if (in_array('po_date', $cols, true) && empty($payload['po_date'])) {
                $createdAt = true
                    ? $this->orders->newQuery()->where('id', $orderId)->value('created_at')
                    : null;
                $payload['po_date'] = !empty($createdAt)
                    ? Carbon::parse($createdAt)->toDateString()
                    : now()->toDateString();
            }
            if (in_array('po_amount', $cols, true) && (empty($payload['po_amount']) || (float) $payload['po_amount'] <= 0)) {
                $amount = 0.0;
                if (true) {
                    $orderRow = $this->orders->newQuery()->where('id', $orderId)->first();
                    if ($orderRow) {
                        foreach (['amount', 'total_amount', 'price', 'subtotal'] as $amountCol) {
                            if (isset($orderRow->{$amountCol}) && $orderRow->{$amountCol} !== null && $orderRow->{$amountCol} !== '') {
                                $amount = (float) $orderRow->{$amountCol};
                                break;
                            }
                        }
                    }
                }
                if ($amount <= 0) {
                    $amount = (float) $request->input('amount', 0);
                }
                if ($amount > 0) {
                    $payload['po_amount'] = $amount;
                }
            }
        }
        if (
            in_array('po_document_url', $cols, true) &&
            $request->hasFile('po_document') &&
            $request->file('po_document')->isValid()
        ) {
            $uploaded = app(\App\Services\OrderPoDocumentService::class)->attachUploadedPo(
                $orderId,
                $request->file('po_document'),
                $request->input('po_number'),
                $request->input('po_date'),
                is_numeric($request->input('po_amount')) ? (float) $request->input('po_amount') : null
            );
            if (!empty($uploaded['ok'])) {
                // attachUploadedPo already persisted the row; avoid double-write with stale payload.
                return;
            }
            Log::warning('attachUploadedPo failed; attempting mirrored fallback write', [
                'order_id' => $orderId,
                'error' => $uploaded['error'] ?? null,
            ]);
            $fallbackFile = $request->file('po_document');
            $binary = @file_get_contents($fallbackFile->getRealPath() ?: $fallbackFile->getPathname());
            if ($binary === false || $binary === '') {
                $binary = (string) $fallbackFile->get();
            }
            if ($binary !== '') {
                $safeName = 'uploaded-fallback-order-' . $orderId . '-' . now()->format('YmdHis') . '.pdf';
                $relative = 'order-po/' . $safeName;
                if ($this->writePublicStorageFile($relative, $binary)) {
                    $payload['po_document_url'] = $this->publicStorageUrl($relative);
                    if (in_array('po_mode', $cols, true)) {
                        $payload['po_mode'] = 'uploaded';
                    }
                    if (in_array('journey_status', $cols, true)) {
                        $payload['journey_status'] = 'placed';
                    }
                    if (in_array('sign_required', $cols, true)) {
                        $payload['sign_required'] = 'no';
                    }
                    if (in_array('final_po_link', $cols, true)) {
                        $payload['final_po_link'] = $payload['po_document_url'];
                    }
                    if (in_array('status', $cols, true)) {
                        $payload['status'] = 'active';
                    }
                }
            }
        } elseif ($mode === 'uploaded' && in_array('po_mode', $cols, true)) {
            $payload['po_mode'] = 'uploaded';
            if (in_array('sign_required', $cols, true)) {
                $payload['sign_required'] = 'no';
            }
        }
        if (in_array('updated_at', $cols, true)) {
            $payload['updated_at'] = now();
        }

        $existing = $this->orderPoDetails->newQuery()->where('order_id', $orderId)->first();
        if ($existing) {
            $this->orderPoDetails->newQuery()->where('order_id', $orderId)->update($payload);
            return;
        }
        if (in_array('created_at', $cols, true)) {
            $payload['created_at'] = now();
        }
        $this->orderPoDetails->newQuery()->insert($payload);
    }

    private function nextPoNumber(): string
    {
        $rows = $this->orderPoDetails->newQuery()
            ->whereNotNull('po_number')
            ->whereRaw("po_number <> ''")
            ->pluck('po_number');

        $max = 0;
        foreach ($rows as $po) {
            $val = strtoupper(trim((string) $po));
            if (preg_match('/^PON(\d{5,})$/', $val, $m)) {
                $n = (int) $m[1];
                if ($n > $max) {
                    $max = $n;
                }
            }
        }

        return 'PON' . str_pad((string) ($max + 1), 5, '0', STR_PAD_LEFT);
    }

    private function getPartnerOptions()
    {
        // 1. Fetch the data with a clean ternary for the name
        $partners = $this->partners->newQuery()
            ->select('id', 'name', 'wallet_balance', 'status')
            ->whereIn('status', ['approved']) // Simplified status check
            ->orderBy('id', 'desc')
            ->get();

        // 2. Map the results to the desired format
        return $partners->map(fn($partner) => (object) [
            'id'             => (int) $partner->id,
            'name'           => $partner->name ?: "Partner #{$partner->id}",
            'wallet_balance' => (float) ($partner->wallet_balance ?? 0),
        ]);
    }

    private function statusOptions(): array
    {
        return ['pending', 'completed', 'paid', 'success', 'failed', 'cancelled'];
    }

    /**
     * Order statuses treated as successfully closed for UI lock, listings, and metrics.
     * New flows use `completed`; `active` remains for legacy rows.
     *
     * @return list<string>
     */
    private function orderSuccessfulStatuses(): array
    {
        return ['completed', 'paid', 'success', 'active'];
    }

    private function typeOptions(): array
    {
        return ['b2b', 'b2c'];
    }

    public function b2bBillingIndex(Request $request)
    {
        $orders = collect();
        $ctx = $this->b2bBillingNewScopedQuery();
        if ($ctx === null) {
            return view('superadmin.b2b_billing.index', [
                'title' => 'B2B Billing',
                'orders' => collect(),
            ]);
        }

        $query = clone $ctx['query'];
        $this->applyB2bBillingRequestFilters($request, $query, $ctx);

        $dataQuery = clone $query;
        $this->applyB2bBillingSelect($dataQuery, $ctx);

        $orders = $dataQuery
            ->orderByDesc('o.id')
            ->limit(500)
            ->get();

        // ---- Dashboard metrics (computed from current filtered rows) ----
        $paidStatuses = $this->orderSuccessfulStatuses();
        $now = Carbon::now();
        $overdueCutoff = $now->copy()->subDays(30);

        $paidCount = 0;
        $paidAmount = 0.0;
        $pendingCount = 0;
        $pendingAmount = 0.0;
        $overdueCount = 0;
        $overdueAmount = 0.0;
        $unpaidCount = 0;
        $unpaidAmount = 0.0;

        // Last 6 months buckets for chart
        $salesBuckets = [];
        $labels = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = $now->copy()->startOfMonth()->subMonths($i);
            $key = $m->format('Y-m');
            $salesBuckets[$key] = ['label' => $m->format('M'), 'sales' => 0.0, 'orders' => 0];
            $labels[] = $m->format('M');
        }

        foreach ($orders as $row) {
            $status = strtolower((string) ($row->status ?? 'pending'));
            $amount = (float) ($row->amount ?? 0);
            $createdAt = $row->created_at ?? null;

            $isPaid = in_array($status, $paidStatuses, true);
            if ($isPaid) {
                $paidCount++;
                $paidAmount += $amount;
            } else {
                // Treat everything else as pending for dashboard purposes
                $pendingCount++;
                $pendingAmount += $amount;

                $created = null;
                try {
                    if (!empty($createdAt)) {
                        $created = Carbon::parse($createdAt);
                    }
                } catch (\Throwable $e) {
                    $created = null;
                }

                $isOverdue = $created ? $created->lt($overdueCutoff) : false;
                if ($isOverdue) {
                    $overdueCount++;
                    $overdueAmount += $amount;
                } else {
                    $unpaidCount++;
                    $unpaidAmount += $amount;
                }
            }

            // Chart buckets (use created_at month)
            if (!empty($createdAt)) {
                try {
                    $dt = Carbon::parse($createdAt);
                    $bucketKey = $dt->format('Y-m');
                    if (isset($salesBuckets[$bucketKey])) {
                        $salesBuckets[$bucketKey]['sales'] += $amount;
                        $salesBuckets[$bucketKey]['orders'] += 1;
                    }
                } catch (\Throwable $e) {
                    // ignore parse error for chart
                }
            }
        }

        $salesSeries = [];
        $orderCountSeries = [];
        foreach ($labels as $label) {
            // rebuild series in same order
            // labels are derived from buckets, so locate bucket by label is safe enough for display
        }
        foreach (array_keys($salesBuckets) as $key) {
            $salesSeries[] = (float) $salesBuckets[$key]['sales'];
            $orderCountSeries[] = (int) $salesBuckets[$key]['orders'];
        }

        // Month-over-month percent for the cards (approx via chart buckets last 2 months)
        $lastSales = count($salesSeries) >= 1 ? $salesSeries[count($salesSeries) - 1] : 0.0;
        $prevSales = count($salesSeries) >= 2 ? $salesSeries[count($salesSeries) - 2] : 0.0;
        $salesChangePct = ($prevSales > 0) ? (($lastSales - $prevSales) / $prevSales) * 100 : 0.0;

        $invoiceStats = [
            'unpaidCount' => $unpaidCount,
            'unpaidAmount' => $unpaidAmount,
            'unpaidChangePct' => $salesChangePct,
            'overdueCount' => $overdueCount,
            'overdueAmount' => $overdueAmount,
            'overdueChangePct' => $salesChangePct,
            'pendingCount' => $pendingCount,
            'pendingAmount' => $pendingAmount,
            'pendingChangePct' => $salesChangePct,
            'paidCount' => $paidCount,
            'paidAmount' => $paidAmount,
            'paidChangePct' => $salesChangePct,
        ];

        return view('superadmin.b2b_billing.index', [
            'title' => 'B2B Billing',
            'orders' => $orders,
            'invoiceStats' => $invoiceStats,
            'salesGraph' => [
                'labels' => $labels,
                'salesSeries' => $salesSeries,
                'orderCountSeries' => $orderCountSeries,
            ],
        ]);
    }

    public function b2bBillingListTable(Request $request)
    {
        $draw = (int) $request->input('draw', 1);
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        if ($length <= 0) {
            $length = 10;
        }
        if ($length > 500) {
            $length = 500;
        }
        $ctx = $this->b2bBillingNewScopedQuery();
        if ($ctx === null) {
            return response()->json([
                'draw' => $draw,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ]);
        }

        $totalData = (clone $ctx['query'])->count();

        $filteredQuery = clone $ctx['query'];
        $this->applyB2bBillingRequestFilters($request, $filteredQuery, $ctx);

        $searchInput = $request->input('search');
        $globalSearch = is_array($searchInput) ? trim((string) ($searchInput['value'] ?? '')) : '';
        if ($globalSearch !== '') {
            $this->applyB2bBillingGlobalSearch($filteredQuery, $globalSearch, $ctx);
        }

        $recordsFiltered = (clone $filteredQuery)->count();

        $orderArray = $request->input('order', []);
        $columnsInput = $request->input('columns', []);
        $columnName = 'o.id';
        $columnSortOrder = 'desc';
        if (!empty($orderArray) && is_array($columnsInput)) {
            $columnIndex = (int) ($orderArray[0]['column'] ?? 0);
            $columnSortOrder = strtolower((string) ($orderArray[0]['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
            $requested = $columnsInput[$columnIndex]['data'] ?? 'id';
            $columnName = $this->b2bBillingOrderColumn($requested, $ctx);
        }

        $dataQuery = clone $filteredQuery;
        $this->applyB2bBillingSelect($dataQuery, $ctx);
        $rows = $dataQuery
            ->orderBy($columnName, $columnSortOrder)
            ->offset($start)
            ->limit($length)
            ->get();

        $data = [];
        foreach ($rows as $order) {
            $status = strtolower((string) ($order->status ?? 'pending'));
            $badgeClass = 'badge bg-warning';
            if (in_array($status, $this->orderSuccessfulStatuses(), true)) {
                $badgeClass = 'badge bg-success';
            } elseif (in_array($status, ['failed', 'cancelled', 'rejected'], true)) {
                $badgeClass = 'badge bg-danger';
            }
            $statusLabel = match ($status) {
                'completed' => 'Active',
                'upgraded' => 'Upgraded',
                'rejected' => 'Cancelled',
                default => ucwords(str_replace('_', ' ', $status)),
            };
            if ($status === 'upgraded') {
                $badgeClass = 'badge bg-secondary';
            }
            $statusHtml = '<span class="badge ' . $badgeClass . ' px-2 py-1 text-capitalize">' . e($statusLabel) . '</span>';

            $oid = (int) $order->id;
            $action = '<div class="d-flex justify-content-center gap-1">'
                . '<a href="' . e(route('superadmin.orders.invoice', $oid)) . '" class="btn btn-light btn-icon btn-sm rounded-circle" title="View Invoice"><i class="ti ti-eye fs-lg"></i></a>'
                . '<a href="' . e(route('superadmin.orders.details', $oid)) . '" class="btn btn-light btn-icon btn-sm rounded-circle" title="View Details"><i class="ti ti-eye-search fs-lg"></i></a>'
                . '<a href="' . e(route('superadmin.orders.invoice.download', $oid)) . '" class="btn btn-light btn-icon btn-sm rounded-circle" title="Download Invoice"><i class="ti ti-download fs-lg"></i></a>'
                . '</div>';

            $dateStr = '-';
            if (!empty($order->created_at)) {
                try {
                    $dateStr = Carbon::parse($order->created_at)->format('d M Y');
                } catch (\Throwable $e) {
                    $dateStr = '-';
                }
            }

            $data[] = [
                'order_id' => $oid,
                'id' => (string) ($order->order_no ?? $oid),
                'customer_name' => $order->customer_name ?? '-',
                'domain_name' => $order->domain_name ?? '-',
                'plan_name' => $order->plan_name ?? '-',
                'order_type' => strtoupper((string) ($order->order_type ?? 'b2b')),
                'amount' => '₹' . number_format((float) ($order->amount ?? 0), 2),
                'credit_used' => '₹' . number_format((float) ($order->credit_used ?? 0), 2),
                'created_at' => $dateStr,
                'status' => $statusHtml,
                'action' => $action,
            ];
        }

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $totalData,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    private function b2bBillingNewScopedQuery(): ?array
    {
        $orderColumns = $this->orderColumns();
        $billingConnection = $this->orders->getConnectionName() ?: config('database.default');
        $hasPlansTable = \Illuminate\Support\Facades\Schema::connection($billingConnection)->hasTable('plans');
        $hasCustomersTable = \Illuminate\Support\Facades\Schema::connection($billingConnection)->hasTable('customers');
        $hasPartnersTable = \Illuminate\Support\Facades\Schema::connection($billingConnection)->hasTable('partners');
        $customersColumns = $hasCustomersTable ? $this->customerColumns() : [];
        $partnersColumns = $hasPartnersTable ? $this->partnerColumns() : [];

        $query = $this->orders->newQuery()->from('orders as o');

        $plansJoined = $hasPlansTable;
        if ($plansJoined) {
            $query->leftJoin('plans as p', 'o.plan_id', '=', 'p.id');
        }

        if (
            $hasCustomersTable &&
            in_array('id', $customersColumns, true)
        ) {
            $query->leftJoin('customers as c', 'o.user_id', '=', 'c.id');

            if ($hasPartnersTable && in_array('partner_id', $customersColumns, true) && in_array('id', $partnersColumns, true)) {
                $query->leftJoin('partners as pr', 'c.partner_id', '=', 'pr.id');
            }
        }

        if (in_array('order_type', $orderColumns, true)) {
            $query->whereRaw('LOWER(o.order_type) = ?', ['b2b']);
        }
        if (in_array('status', $orderColumns, true)) {
            // B2B Billing should list invoices only for completed orders.
            $query->whereRaw("LOWER(COALESCE(o.status, '')) = ?", ['completed']);
        }

        if ($hasCustomersTable && in_array('partner_id', $customersColumns, true) && $hasPartnersTable) {
            $query->whereNotNull('c.partner_id');
        }

        $amountExpression = '0';
        $amountColumn = null;
        if (in_array('total_amount', $orderColumns, true)) {
            $amountExpression = 'COALESCE(o.total_amount, 0)';
            $amountColumn = 'o.total_amount';
        } elseif (in_array('subtotal', $orderColumns, true)) {
            $amountExpression = 'COALESCE(o.subtotal, 0)';
            $amountColumn = 'o.subtotal';
        } elseif (in_array('price', $orderColumns, true)) {
            $amountExpression = 'COALESCE(o.price, 0)';
            $amountColumn = 'o.price';
        }

        $creditExpression = in_array('credit_used', $orderColumns, true) ? 'COALESCE(o.credit_used, 0)' : '0';
        $typeExpression = in_array('order_type', $orderColumns, true) ? "COALESCE(o.order_type, 'b2c')" : "'b2c'";
        $statusExpression = in_array('status', $orderColumns, true) ? "COALESCE(o.status, 'pending')" : "'pending'";
        $dateExpression = in_array('created_at', $orderColumns, true) ? 'o.created_at' : 'NULL';
        $orderNoExpression = in_array('order_no', $orderColumns, true)
            ? "COALESCE(NULLIF(o.order_no, ''), CAST(o.id AS CHAR))"
            : 'CAST(o.id AS CHAR)';

        $customerExpression = $hasCustomersTable
            ? "COALESCE(NULLIF(c.name, ''), NULLIF(c.company_name, ''), '-')"
            : "'-'";

        if (in_array('plan_name', $orderColumns, true)) {
            $planExpression = $hasPlansTable ? "COALESCE(NULLIF(o.plan_name, ''), p.name, '-')" : "COALESCE(NULLIF(o.plan_name, ''), '-')";
        } else {
            $planExpression = $hasPlansTable ? "COALESCE(p.name, '-')" : "'-'";
        }

        $domainExpression = in_array('domain_name', $orderColumns, true)
            ? "COALESCE(NULLIF(o.domain_name, ''), '-')"
            : "'-'";

        return [
            'query' => $query,
            'plansJoined' => $plansJoined,
            'orderColumns' => $orderColumns,
            'hasPlansTable' => $hasPlansTable,
            'hasCustomersTable' => $hasCustomersTable,
            'hasPartnersTable' => $hasPartnersTable,
            'customersColumns' => $customersColumns,
            'partnersColumns' => $partnersColumns,
            'amountExpression' => $amountExpression,
            'amountColumn' => $amountColumn,
            'creditExpression' => $creditExpression,
            'typeExpression' => $typeExpression,
            'statusExpression' => $statusExpression,
            'dateExpression' => $dateExpression,
            'orderNoExpression' => $orderNoExpression,
            'customerExpression' => $customerExpression,
            'planExpression' => $planExpression,
            'domainExpression' => $domainExpression,
        ];
    }

    private function applyB2bBillingSelect($query, array $ctx): void
    {
        $query->selectRaw("
            o.id,
            {$ctx['orderNoExpression']} as order_no,
            {$ctx['customerExpression']} as customer_name,
            {$ctx['domainExpression']} as domain_name,
            {$ctx['planExpression']} as plan_name,
            {$ctx['typeExpression']} as order_type,
            {$ctx['amountExpression']} as amount,
            {$ctx['creditExpression']} as credit_used,
            {$ctx['dateExpression']} as created_at,
            {$ctx['statusExpression']} as status
        ");
    }

    private function applyB2bBillingRequestFilters(Request $request, $query, array $ctx): void
    {
        $orderColumns = $ctx['orderColumns'];
        $hasPartnersTable = $ctx['hasPartnersTable'];
        $partnersColumns = $ctx['partnersColumns'];
        $hasCustomersTable = $ctx['hasCustomersTable'];
        $customersColumns = $ctx['customersColumns'];

        $partnerName = trim((string) $request->input('partner_name', $request->query('partner_name', '')));
        $domainName = trim((string) $request->input('domain_name', $request->query('domain_name', '')));
        $customerName = trim((string) $request->input('customer_name', $request->query('customer_name', '')));
        $mobile = trim((string) $request->input('mobile', $request->query('mobile', '')));
        $email = trim((string) $request->input('email', $request->query('email', '')));
        $fromDate = trim((string) $request->input('from_date', $request->query('from_date', '')));
        $toDate = trim((string) $request->input('to_date', $request->query('to_date', '')));

        if ($partnerName !== '' && $hasPartnersTable && in_array('name', $partnersColumns, true)) {
            $query->where('pr.name', 'like', '%' . $partnerName . '%');
        }

        if ($domainName !== '' && in_array('domain_name', $orderColumns, true)) {
            $query->where('o.domain_name', 'like', '%' . $domainName . '%');
        }

        if ($customerName !== '' && $hasCustomersTable) {
            $hasCustName = in_array('name', $customersColumns, true);
            $hasCompany = in_array('company_name', $customersColumns, true);
            if ($hasCustName || $hasCompany) {
                $query->where(function ($q) use ($customerName, $hasCustName, $hasCompany) {
                    if ($hasCustName) {
                        $q->where('c.name', 'like', '%' . $customerName . '%');
                    }
                    if ($hasCompany) {
                        if ($hasCustName) {
                            $q->orWhere('c.company_name', 'like', '%' . $customerName . '%');
                        } else {
                            $q->where('c.company_name', 'like', '%' . $customerName . '%');
                        }
                    }
                });
            }
        }

        if ($mobile !== '' && $hasCustomersTable && in_array('mobile', $customersColumns, true)) {
            $query->where('c.mobile', 'like', '%' . $mobile . '%');
        }

        if ($email !== '' && $hasCustomersTable && in_array('email', $customersColumns, true)) {
            $query->where('c.email', 'like', '%' . $email . '%');
        }

        if (in_array('created_at', $orderColumns, true)) {
            try {
                if ($fromDate !== '') {
                    $query->where('o.created_at', '>=', Carbon::parse($fromDate)->startOfDay());
                }
                if ($toDate !== '') {
                    $query->where('o.created_at', '<=', Carbon::parse($toDate)->endOfDay());
                }
            } catch (\Throwable $e) {
                // ignore invalid dates
            }
        }
    }

    private function applyB2bBillingGlobalSearch($query, string $globalSearch, array $ctx): void
    {
        $like = '%' . addcslashes($globalSearch, '%_\\') . '%';
        $orderColumns = $ctx['orderColumns'];
        $hasCustomersTable = $ctx['hasCustomersTable'];
        $customersColumns = $ctx['customersColumns'];
        $plansJoined = $ctx['plansJoined'] ?? false;

        $query->where(function ($q) use ($like, $globalSearch, $orderColumns, $hasCustomersTable, $customersColumns, $plansJoined) {
            $q->where('o.id', 'like', $like);
            if (in_array('order_no', $orderColumns, true)) {
                $q->orWhere('o.order_no', 'like', $like);
            }
            if (ctype_digit($globalSearch)) {
                $q->orWhere('o.id', (int) $globalSearch);
            }

            if ($hasCustomersTable) {
                if (in_array('name', $customersColumns, true)) {
                    $q->orWhere('c.name', 'like', $like);
                }
                if (in_array('company_name', $customersColumns, true)) {
                    $q->orWhere('c.company_name', 'like', $like);
                }
                if (in_array('mobile', $customersColumns, true)) {
                    $q->orWhere('c.mobile', 'like', $like);
                }
                if (in_array('email', $customersColumns, true)) {
                    $q->orWhere('c.email', 'like', $like);
                }
            }

            if (in_array('plan_name', $orderColumns, true)) {
                $q->orWhere('o.plan_name', 'like', $like);
            }
            if ($plansJoined) {
                $q->orWhere('p.name', 'like', $like);
            }

            if (in_array('order_type', $orderColumns, true)) {
                $q->orWhere('o.order_type', 'like', $like);
            }
            if (in_array('status', $orderColumns, true)) {
                $q->orWhere('o.status', 'like', $like);
            }
            if (in_array('domain_name', $orderColumns, true)) {
                $q->orWhere('o.domain_name', 'like', $like);
            }
        });
    }

    private function b2bBillingOrderColumn(string $requested, array $ctx): string
    {
        $orderColumns = $ctx['orderColumns'];
        $plansJoined = $ctx['plansJoined'] ?? false;

        $planSort = 'o.id';
        if (in_array('plan_name', $orderColumns, true)) {
            $planSort = 'o.plan_name';
        } elseif ($plansJoined) {
            $planSort = 'p.name';
        }

        $map = [
            'id' => 'o.id',
            'customer_name' => ($ctx['hasCustomersTable'] ?? false) ? 'c.name' : 'o.id',
            'domain_name' => in_array('domain_name', $ctx['orderColumns'], true) ? 'o.domain_name' : 'o.id',
            'plan_name' => $planSort,
            'order_type' => in_array('order_type', $orderColumns, true) ? 'o.order_type' : 'o.id',
            'amount' => $ctx['amountColumn'] ?? 'o.id',
            'credit_used' => in_array('credit_used', $orderColumns, true) ? 'o.credit_used' : 'o.id',
            'created_at' => in_array('created_at', $orderColumns, true) ? 'o.created_at' : 'o.id',
            'status' => in_array('status', $orderColumns, true) ? 'o.status' : 'o.id',
        ];

        return $map[$requested] ?? 'o.id';
    }

    private function sendOrderPlacedClientMail(int $orderId, bool $toPartner = false): bool
    {
        $email = '';

        try {
            $order = $this->findOrder($orderId);
            if (!$order) {
                Log::warning('Order placed mail skipped: order not found', [
                    'order_id' => $orderId,
                    'to_partner' => $toPartner,
                ]);

                return false;
            }

            if ((int) ($this->orders->newQuery()->where('id', $orderId)->value('is_legacy') ?? 0) === 1) {
                return false;
            }

            if ($toPartner) {
                $partnerId = (int) ($order->partner_id ?? 0);
                if ($partnerId <= 0) {
                    Log::warning('Partner order mail skipped: missing partner_id', ['order_id' => $orderId]);

                    return false;
                }

                $partner = $this->partners->newQuery()->whereKey($partnerId)->first();
                if (!$partner) {
                    Log::warning('Partner order mail skipped: partner not found', [
                        'order_id' => $orderId,
                        'partner_id' => $partnerId,
                    ]);

                    return false;
                }

                $email = trim((string) ($partner->email ?? ''));
                $order = (object) array_merge((array) $order, [
                    'customer_name' => (string) ($partner->name ?? $partner->company_name ?? 'Partner'),
                ]);
            } else {
                $email = trim((string) ($order->customer_email ?? ''));
            }

            if ($email === '' || $email === '-' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Log::warning('Order placed mail skipped: invalid recipient', [
                    'order_id' => $orderId,
                    'to_partner' => $toPartner,
                    'email' => $email,
                ]);

                return false;
            }

            $attachments = [];
            try {
                $poMode = strtolower(trim((string) ($order->po_mode ?? '')));

                if ($poMode === 'generated') {
                    // Rebuild generated PO for completed-order mail so the attachment
                    // matches the latest order amounts instead of an older cached PDF.
                    $poStored = $this->persistOrderPoPdfToStorage($orderId, false, true);
                    $poPath = !empty($poStored['relative_path'])
                        ? $this->resolveExistingPublicStorageFile((string) $poStored['relative_path'])
                        : null;
                    if ($poPath !== null) {
                        $attachments[] = [
                            'type' => 'path',
                            'path' => $poPath,
                            'name' => 'PO-Order-' . $orderId . '.pdf',
                            'mime' => 'application/pdf',
                        ];
                    } else {
                        $generatedPoAttachment = $this->buildGeneratedPoAttachment($orderId, $order);
                        if ($generatedPoAttachment !== null) {
                            $attachments[] = $generatedPoAttachment;
                        }
                    }
                } elseif (!empty($order->po_document_url)) {
                    $poFilePath = $this->resolvePublicStoragePathFromUrl((string) $order->po_document_url);
                    if ($poFilePath !== null && is_file($poFilePath)) {
                        $attachments[] = [
                            'type' => 'path',
                            'path' => $poFilePath,
                            'name' => 'PO-Order-' . $orderId . '.pdf',
                            'mime' => 'application/pdf',
                        ];
                    }
                }

                $invoiceStored = $this->persistOrderInvoicePdfToStorage($orderId);
                if (!empty($invoiceStored['relative_path'])) {
                    $invoicePath = $this->resolveExistingPublicStorageFile((string) $invoiceStored['relative_path']);
                    if ($invoicePath !== null) {
                        $attachments[] = [
                            'type' => 'path',
                            'path' => $invoicePath,
                            'name' => 'Tax-Invoice-Order-' . $orderId . '.pdf',
                            'mime' => 'application/pdf',
                        ];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Order placed mail attachments skipped', [
                    'order_id' => $orderId,
                    'to_partner' => $toPartner,
                    'error' => $e->getMessage(),
                ]);
            }

            Mail::to($email)->send(new OrderPlacedClientMail($order, $attachments));

            Log::info('Order placed mail sent', [
                'order_id' => $orderId,
                'to_partner' => $toPartner,
                'email' => $email,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Order placed mail failed', [
                'order_id' => $orderId,
                'to_partner' => $toPartner,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function sendOrderRejectedClientMail(int $orderId, string $rejectionReason): void
    {
        try {
            $order = $this->findOrder($orderId);
            if (!$order) {
                return;
            }

            $email = trim((string) ($order->customer_email ?? ''));
            if ($email === '') {
                return;
            }

            $attachments = [];
            $poMode = strtolower(trim((string) ($order->po_mode ?? '')));
            if ($poMode === 'generated') {
                $generatedPoAttachment = $this->buildGeneratedPoAttachment($orderId, $order);
                if ($generatedPoAttachment !== null) {
                    $attachments[] = $generatedPoAttachment;
                }
            } elseif (!empty($order->po_document_url)) {
                $poFilePath = $this->resolvePublicStoragePathFromUrl((string) $order->po_document_url);
                if ($poFilePath !== null && is_file($poFilePath)) {
                    $attachments[] = [
                        'type' => 'path',
                        'path' => $poFilePath,
                        'name' => 'PO-Order-' . $orderId . '.pdf',
                        'mime' => 'application/pdf',
                    ];
                }
            }

            Mail::send(
                'emails.order_rejected_client',
                ['order' => $order, 'rejectionReason' => $rejectionReason],
                static function ($message) use ($email, $order, $attachments) {
                    $orderNo = trim((string) ($order->order_no ?? ''));
                    $label = $orderNo !== '' ? $orderNo : (string) ($order->id ?? '');
                    $message->to($email)->subject('Your order #' . $label . ' has been cancelled');
                    foreach ($attachments as $attachment) {
                        $type = strtolower((string) ($attachment['type'] ?? ''));
                        if ($type === 'path' && !empty($attachment['path'])) {
                            $attachName = (string) ($attachment['name'] ?? basename((string) $attachment['path']));
                            $attachMime = (string) ($attachment['mime'] ?? 'application/octet-stream');
                            $message->attach((string) $attachment['path'], ['as' => $attachName, 'mime' => $attachMime]);
                        } elseif ($type === 'data' && array_key_exists('data', $attachment)) {
                            $attachData = (string) ($attachment['data'] ?? '');
                            if ($attachData !== '') {
                                $attachName = (string) ($attachment['name'] ?? 'attachment.bin');
                                $attachMime = (string) ($attachment['mime'] ?? 'application/octet-stream');
                                $message->attachData($attachData, $attachName, ['mime' => $attachMime]);
                            }
                        }
                    }
                }
            );
        } catch (\Throwable $e) {
            Log::warning('Order cancellation email failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build an attachment array for the customer's e-invoice PDF.
     *
     * Looks up `invoices.e_invoice_pdf_url` for this order; if found, downloads
     * the PDF via HTTP (8s timeout) and returns it as an inline `data` payload.
     * Returns null on any failure — the order success email must still go out.
     *
     * @return array{type:string,data:string,name:string,mime:string}|null
     */
    private function buildEInvoicePdfAttachment(int $orderId, object $order): ?array
    {
        try {
            $row = $this->invoiceMst->newQuery()->where('order_id', $orderId)->orderByDesc('id')->first();
            if (!$row) {
                return null;
            }

            $url = trim((string) ($row->e_invoice_pdf_url ?? ''));
            if ($url === '') {
                return null;
            }

            $response = \Illuminate\Support\Facades\Http::timeout(8)->get($url);
            if (!$response->successful()) {
                Log::warning('E-Invoice PDF download failed (skipping attachment)', [
                    'order_id' => $orderId,
                    'url'      => $url,
                    'status'   => $response->status(),
                ]);
                return null;
            }

            $body = $response->body();
            if ($body === '' || strlen($body) < 200) {
                return null; // not a real PDF body
            }

            $orderNo  = (string) ($order->order_no ?? $orderId);
            $fileName = 'E-Invoice-' . preg_replace('/[^A-Za-z0-9_\-]/', '', $orderNo) . '.pdf';

            return [
                'type' => 'data',
                'data' => $body,
                'name' => $fileName,
                'mime' => 'application/pdf',
            ];
        } catch (\Throwable $e) {
            Log::warning('buildEInvoicePdfAttachment exception', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function resolvePublicStoragePathFromUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $url;
        }
        $relative = '';
        foreach (['/public/storage/', '/storage/'] as $marker) {
            $idx = strpos($path, $marker);
            if ($idx !== false) {
                $relative = ltrim(substr($path, $idx + strlen($marker)), '/');
                break;
            }
        }
        if ($relative === '') {
            return null;
        }

        return storage_path('app/public/' . str_replace(['\\', '//'], '/', $relative));
    }

    /**
     * True only after Aadhaar OTP e-sign has completed — not on OTP init / ref_no alone.
     */
    private function isOrderAadhaarEsignCompleted(?object $order, ?object $poRow = null): bool
    {
        if ($poRow && (int) ($poRow->aadhar_verified ?? 0) === 1) {
            return true;
        }

        if ($order && (int) ($order->aadhar_verified ?? 0) === 1) {
            return true;
        }

        $verificationId = (int) ($order->aadhaar_verification_id ?? 0);
        if ($verificationId > 0) {
            $verifiedAt = $this->partnerAadhaarVerifications->newQuery()
                ->whereKey($verificationId)
                ->value('verified_at');

            if (!empty($verifiedAt)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{verified: bool, verified_at: mixed, masked: ?string, ref_no: ?string}
     */
    private function resolveOrderAadhaarEsignMeta(?object $order, ?object $poRow = null): array
    {
        $verified = $this->isOrderAadhaarEsignCompleted($order, $poRow);
        $verifiedAt = null;
        $masked = null;
        $refNo = null;

        if ($poRow) {
            $verifiedAt = $poRow->aadhar_verified_at ?? null;
            $masked = $poRow->aadhaar_masked ?? null;
            $refNo = $poRow->aadhaar_ref_no ?? null;
        }

        if ($order) {
            if (empty($verifiedAt)) {
                $verifiedAt = $order->aadhar_verified_at ?? null;
            }
            if (empty($masked)) {
                $masked = $order->aadhaar_masked ?? null;
            }
            if (empty($refNo)) {
                $refNo = $order->aadhaar_ref_no ?? null;
            }
        }

        $verificationId = (int) ($order->aadhaar_verification_id ?? 0);
        if ($verificationId > 0) {
            $verification = $this->partnerAadhaarVerifications->newQuery()
                ->whereKey($verificationId)
                ->first(['verified_at', 'created_at', 'aadhaar_masked', 'aadhaar_ref_no']);
            if ($verification) {
                if (empty($verifiedAt) && !empty($verification->verified_at)) {
                    $verifiedAt = $verification->verified_at;
                } elseif (empty($verifiedAt) && !empty($verification->created_at)) {
                    $verifiedAt = $verification->created_at;
                }
                if (empty($masked) && !empty($verification->aadhaar_masked)) {
                    $masked = $verification->aadhaar_masked;
                }
                if (empty($refNo) && !empty($verification->aadhaar_ref_no)) {
                    $refNo = $verification->aadhaar_ref_no;
                }
            }
        }

        return [
            'verified' => $verified,
            'verified_at' => $verifiedAt,
            'masked' => $masked !== null && trim((string) $masked) !== '' ? trim((string) $masked) : null,
            'ref_no' => $refNo !== null && trim((string) $refNo) !== '' ? trim((string) $refNo) : null,
        ];
    }

    /**
     * View data for generated PO PDF (same layout as tax invoice).
     *
     * @return array<string, mixed>|null
     */
    private function buildGeneratedPoViewData(int $orderId): ?array
    {
        $order = $this->findOrder($orderId);
        if (!$order) {
            return null;
        }

        $base = $this->buildInvoiceData($orderId);
        if (!$base) {
            return null;
        }

        $poRow = true
            ? $this->orderPoDetails->newQuery()->where('order_id', $orderId)->first()
            : null;

        $prefill = [];
        $customerId = (int) ($order->user_id ?? 0);
        if ($customerId > 0) {
            $prefill = app(RenewalService::class)->buildPurchaseOrderPrefillFromCustomer($customerId);
        }

        $poNumber = $this->poRowValue($poRow, 'po_number') ?: (string) ($order->po_number ?? ('PO-' . $orderId));
        $poDateRaw = $this->poRowValue($poRow, 'po_date') ?: (string) ($order->po_date ?? '');
        try {
            $poCarbon = $poDateRaw !== '' ? Carbon::parse($poDateRaw) : Carbon::now();
        } catch (\Throwable $e) {
            $poCarbon = Carbon::now();
        }

        $dueCarbon = $poCarbon->copy()->addDays(15);
        $dueDateLong = $dueCarbon->format('l, jS F Y');

        $buyerName = $this->poRowValue($poRow, 'po_manual_company_name', 'manual_company_name')
            ?: (string) ($order->po_manual_company_name ?? $order->customer_company_name ?? $prefill['company'] ?? '');
        if ($buyerName === '') {
            $buyerName = $this->poRowValue($poRow, 'po_manual_name', 'manual_name')
                ?: (string) ($order->po_manual_name ?? $order->customer_name ?? $prefill['name'] ?? '-');
        }

        $buyerAddress = $this->poRowValue($poRow, 'po_manual_company_address', 'manual_company_address')
            ?: (string) ($order->po_manual_company_address ?? $order->customer_billing_address ?? $prefill['address'] ?? '-');

        $buyerPhone = $this->poRowValue($poRow, 'po_manual_mobile', 'manual_mobile')
            ?: (string) ($order->po_manual_mobile ?? $order->customer_mobile ?? $prefill['mobile'] ?? '');

        $buyerGstin = $this->poRowValue($poRow, 'po_manual_gstin', 'manual_gstin')
            ?: (string) ($order->po_manual_gstin ?? $order->customer_gstin ?? $prefill['gstin'] ?? '-');

        $buyerState = $this->poRowValue($poRow, 'po_state', 'po_manual_state')
            ?: (string) ($prefill['state'] ?? $base['customer']['state_name'] ?? '');
        $buyerStateCode = strlen($buyerGstin) >= 2 && ctype_digit(substr($buyerGstin, 0, 2))
            ? substr($buyerGstin, 0, 2)
            : (string) ($base['customer']['state_code'] ?? '');

        $customer = $base['customer'];
        $customer['name'] = $buyerName;
        $customer['billing_address'] = $buyerAddress;
        $customer['phone'] = $buyerPhone;
        $customer['gstin'] = $buyerGstin !== '' ? $buyerGstin : '-';
        $customer['state_name'] = $buyerState;
        $customer['state_code'] = $buyerStateCode;

        $plan = $this->resolvePlanRowForOrder($order);
        $enrollmentMeta = $this->resolveOrderEnrollmentMeta($order);
        $transferDomain = (string) ($enrollmentMeta['transfer_domain'] ?? '');
        $enrollmentType = (string) ($enrollmentMeta['enrollment_type'] ?? 'New Service');
        $richLabel = $this->buildRichInvoiceLineLabel($order, $plan);
        $labelLines = array_values(array_filter(array_map('trim', explode("\n", $richLabel))));
        $items = $base['items'];
        $serviceDetails = implode("\n", array_merge(
            ['Enrollment type: ' . $enrollmentType],
            array_slice($labelLines, 1)
        ));

        $termsOfDelivery = 'Due on or before ' . $dueDateLong . '. '
            . 'The payment for the services due in 15 days from the raised date of this purchase order. '
            . '2% interest charge per month will be levied if payment is not made by the due date. '
            . 'For general terms visit your service agreement or company website.';

        $aadhaarEsign = $this->resolveOrderAadhaarEsignMeta($order, $poRow);
        $aadhaarVerified = $aadhaarEsign['verified'] === true;
        $aadharVerifiedAt = $aadhaarEsign['verified_at'];
        $aadhaarMasked = $aadhaarEsign['masked'];
        $aadhaarRefNo = $aadhaarEsign['ref_no'];

        $partnerAadhaarMandatory = 'no';
        $partnerId = (int) ($order->partner_id ?? 0);
        if ($partnerId > 0) {
            $partnerAadhaarMandatory = strtolower(trim((string) (
                $this->partners->newQuery()->whereKey($partnerId)->value('aadhaar_mandatory') ?? 'no'
            )));
        }
        $aadhaarMandatoryYes = $partnerAadhaarMandatory === 'yes';
        $showAadhaarSignatureStamp = $aadhaarMandatoryYes && $aadhaarVerified;

        $orderStatusNormalized = strtolower(trim((string) ($order->status ?? $order->resolved_status ?? '')));
        $isOrderCancelled = in_array($orderStatusNormalized, ['cancelled', 'canceled', 'rejected', 'failed', 'void'], true);

        return array_merge($base, [
            'document_title' => 'Purchase Order',
            'po_number' => $poNumber,
            'order_no' => (string) ($order->order_no ?? $orderId),
            'order_status' => $orderStatusNormalized,
            'po_date' => $poCarbon->format('d-m-Y'),
            'po_date_alt' => $poCarbon->format('d-M-y'),
            'due_date' => $dueCarbon->format('jS F Y'),
            'due_date_long' => $dueDateLong,
            'customer' => $customer,
            'items' => $items,
            'terms_of_payment' => 'Due within 15 days from PO date.',
            'terms_of_delivery' => $termsOfDelivery,
            'service_details' => $serviceDetails,
            'footer_note' => 'This is a Computer Generated Purchase Order',
            'aadhaar_signature_valid' => $aadhaarVerified,
            'aadhar_verified_at' => $aadharVerifiedAt,
            'aadhaar_masked' => $aadhaarMasked,
            'aadhaar_ref_no' => $aadhaarRefNo,
            'aadhaar_mandatory_yes' => $aadhaarMandatoryYes ? true : false,
            'show_aadhaar_signature_stamp' => $showAadhaarSignatureStamp ? true : false,
            'is_order_cancelled' => $isOrderCancelled,
            'transfer_domain' => $transferDomain,
            'enrollment_type' => $enrollmentType,
        ]);
    }

    private function buildGeneratedPoAttachment(int $orderId, object $order): ?array
    {
        $viewData = $this->buildGeneratedPoViewData($orderId);
        if ($viewData === null) {
            Log::warning('buildGeneratedPoAttachment: no view data', ['order_id' => $orderId]);

            return null;
        }

        $viewData['for_pdf'] = true;

        try {
            $html = view('superadmin.po_details_pdf', $viewData)->render();
        } catch (\Throwable $e) {
            Log::error('PO view render failed', ['order_id' => $orderId, 'error' => $e->getMessage()]);

            return null;
        }

        if ($html === '') {
            return null;
        }

        $binary = $this->renderHtmlToPdfBinary($this->prepareInvoiceHtmlForPdf($html));
        $fileName = 'Generated-PO-Order-' . $orderId . '.pdf';
        if ($binary !== null && $binary !== '') {
            return [
                'type' => 'data',
                'data' => $binary,
                'name' => $fileName,
                'mime' => 'application/pdf',
            ];
        }

        Log::warning('buildGeneratedPoAttachment: PDF render returned empty', ['order_id' => $orderId]);

        return null;
    }

    public function saveGstInvoice(Request $request, InvoiceService $invoiceService)
    {
        $validated = $request->validate([
            'cust_id' => ['required', 'integer'],
            'bill_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:pending,paid,failed'],
            'action' => ['nullable', 'in:add_order,renew,upgrade'],
            'net_amt' => ['required', 'numeric', 'min:0'],
            'taxable_total' => ['nullable', 'numeric', 'min:0'],
            'cgst_total' => ['nullable', 'numeric', 'min:0'],
            'sgst_total' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.invoice_no' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.hsn_sac' => ['nullable', 'string', 'max:50'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
            'items.*.cnt_rate' => ['required', 'numeric', 'min:0'],
            'items.*.taxable_amt' => ['nullable', 'numeric', 'min:0'],
            'items.*.cgst' => ['nullable', 'numeric', 'min:0'],
            'items.*.sgst' => ['nullable', 'numeric', 'min:0'],
            'items.*.nettotal' => ['required', 'numeric', 'min:0'],
        ]);

        $invoice = $invoiceService->createInvoice($validated);

        return response()->json([
            'success' => true,
            'message' => 'GST invoice saved successfully.',
            'data' => $invoice,
        ]);
    }
    /**
     * API endpoint for mobile app – fetch billing details for a partner.
     * URL (api.php): POST /b2b-billing-details/{partnerId}
     */
    public function b2bBillingDetails(Request $request, $partnerId)
    {
        // Verify orders table exists
        // Build query for the partner's orders
        $orders = $this->orders->newQuery()
            ->where('partner_id', $partnerId)
            ->orderByDesc('created_at')
            ->select('order_no', 'amount', 'status', 'created_at', 'domain_name', 'plan_name')
            ->get()
            ->map(function ($row) {
                return [
                    'order_no'    => $row->order_no,
                    'amount'      => $row->amount,
                    'status'      => $row->status,
                    'created_at'  => $row->created_at ? Carbon::parse($row->created_at)->format('Y-m-d H:i:s') : null,
                    'domain_name' => $row->domain_name,
                    'plan_name'   => $row->plan_name,
                ];
            });

        return response()->json([
            'partner_id' => (int) $partnerId,
            'orders'     => $orders,
        ]);
    }

    /**
     * Ensure generated PO row exists (po_number, po_date, amount) before PDF persist.
     */
    public function ensureGeneratedPoForOrder(int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }

        if (app(\App\Services\OrderPoDocumentService::class)->isUploadedPo($orderId)) {
            return;
        }

        $poPayload = [
            'po_mode' => 'generated',
            'po_journey_status' => 'placed',
        ];

        $order = $this->orders->newQuery()->where('id', $orderId)->first();
        $customerId = (int) ($order->user_id ?? 0);
        if ($customerId > 0) {
            $prefill = app(RenewalService::class)->buildPurchaseOrderPrefillFromCustomer($customerId);
            if ($prefill !== []) {
                $poPayload = array_merge($poPayload, [
                    'po_name' => (string) ($prefill['name'] ?? ''),
                    'po_email' => (string) ($prefill['email'] ?? ''),
                    'po_mobile' => (string) ($prefill['mobile'] ?? ''),
                    'po_gstin' => (string) ($prefill['gstin'] ?? ''),
                    'po_company_name' => (string) ($prefill['company'] ?? ''),
                    'po_company_address' => (string) ($prefill['address'] ?? ''),
                    'po_city' => (string) ($prefill['city'] ?? ''),
                    'po_state' => (string) ($prefill['state'] ?? ''),
                    'po_country' => (string) ($prefill['country'] ?? 'India'),
                    'po_pincode' => (string) ($prefill['pincode'] ?? ''),
                ]);
            }
        }

        $request = Request::create('/', 'POST', $poPayload);
        $this->normalizePoInputsForValidation($request);
        $this->mapB2bWizardPoFieldAliases($request);
        $this->upsertOrderPoDetails($orderId, $request);

        if ($order) {
            $this->syncPoDetailsFromOrder($orderId, $order);
        }

        $this->syncOrderPoJourneyOnOrdersTable($orderId, 'placed', $request);

        if ($customerId > 0 && method_exists($this, 'seedB2bOrderCustomerSnapshot')) {
            $orderStatus = strtolower(trim((string) ($order->status ?? '')));
            if ($orderStatus !== 'completed') {
                $this->seedB2bOrderCustomerSnapshot($orderId);
            }
        }
    }

    /**
     * Generate PO PDF, save to public storage, persist URL on order_po_details / orders.
     *
     * @return array{relative_path: string, public_url: string, po_number: string}|null
     */
    public function persistOrderPoPdfToStorage(int $orderId, bool $seedPoRow = true, bool $forceRebuild = false): ?array
    {
        try {
            if (app(\App\Services\OrderPoDocumentService::class)->isUploadedPo($orderId)) {
                $poRow = $this->orderPoDetails->newQuery()->where('order_id', $orderId)->first();
                $url = trim((string) ($poRow->po_document_url ?? $poRow->final_po_link ?? ''));
                if ($url === '') {
                    return null;
                }

                return [
                    'relative_path' => '',
                    'public_url' => $url,
                    'po_number' => (string) ($poRow->po_number ?? ''),
                ];
            }

            $order = $this->orders->newQuery()->where('id', $orderId)->first();
            if (!$order) {
                Log::warning('persistOrderPoPdfToStorage: order not found', ['order_id' => $orderId]);

                return null;
            }

            $existingPo = true
                ? $this->orderPoDetails->newQuery()->where('order_id', $orderId)->first()
                : null;
            $poAlreadySeeded = $existingPo
                && trim((string) ($existingPo->po_number ?? '')) !== '';

            if ($seedPoRow && !$poAlreadySeeded) {
                $this->ensureGeneratedPoForOrder($orderId);
            }

            $poRow = true
                ? $this->orderPoDetails->newQuery()->where('order_id', $orderId)->first()
                : null;
            $poNumber = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($poRow->po_number ?? 'PO-' . $orderId));
            if ($poNumber === '') {
                $poNumber = 'PO-' . $orderId;
            }

            $relativePath = 'order-po/' . $poNumber . '-order-' . $orderId . '.pdf';
            $existingPoFile = $this->resolveExistingPublicStorageFile($relativePath);
            if (!$forceRebuild && $existingPoFile !== null) {
                $publicUrl = $this->publicStorageUrl($relativePath);

                return [
                    'relative_path' => $relativePath,
                    'public_url' => $publicUrl,
                    'po_number' => (string) ($poRow->po_number ?? $poNumber),
                ];
            }

            $attachment = $this->buildGeneratedPoAttachment($orderId, $order);
            if (!$attachment || empty($attachment['data'])) {
                Log::warning('persistOrderPoPdfToStorage: empty PO attachment', ['order_id' => $orderId]);

                return null;
            }

            $isPdf = ($attachment['mime'] ?? '') === 'application/pdf';
            if (!$isPdf) {
                Log::warning('persistOrderPoPdfToStorage: PDF generation failed', ['order_id' => $orderId]);

                return null;
            }

            if (!$this->writePublicStorageFile($relativePath, $attachment['data'])) {
                Log::error('persistOrderPoPdfToStorage: file not written', [
                    'order_id' => $orderId,
                    'path' => $relativePath,
                    'storage_file' => $this->storagePublicPath($relativePath),
                    'public_file' => $this->webPublicStoragePath($relativePath),
                    'bytes' => strlen((string) $attachment['data']),
                ]);

                return null;
            }

            $publicUrl = $this->publicStorageUrl($relativePath);

            if (true) {
                $poCols = $this->orderPoDetailColumns();
                $poUpdate = ['updated_at' => now()];
                if (in_array('po_document_url', $poCols, true)) {
                    $poUpdate['po_document_url'] = $publicUrl;
                }
                if (in_array('journey_status', $poCols, true)) {
                    $existingJourney = strtolower(trim((string) ($poRow->journey_status ?? '')));
                    if ($this->isOrderAadhaarEsignCompleted($order, $poRow)) {
                        $poUpdate['journey_status'] = 'placed';
                    } elseif ($existingJourney === '') {
                        $poUpdate['journey_status'] = 'po_pending';
                    }
                }
                if (in_array('po_mode', $poCols, true)) {
                    $poUpdate['po_mode'] = 'generated';
                }
                if ($poRow) {
                    $this->orderPoDetails->newQuery()->where('order_id', $orderId)->update($poUpdate);
                } else {
                    $insert = array_merge(['order_id' => $orderId], $poUpdate);
                    if (in_array('created_at', $poCols, true)) {
                        $insert['created_at'] = now();
                    }
                    $this->orderPoDetails->newQuery()->insert($insert);
                }
            }

            if (true) {
                $this->orders->newQuery()->where('id', $orderId)->update([
                    'po_document_url' => $publicUrl,
                    'updated_at' => now(),
                ]);
            }

            return [
                'relative_path' => $relativePath,
                'public_url' => $publicUrl,
                'po_number' => (string) ($poRow->po_number ?? $poNumber),
            ];
        } catch (\Throwable $e) {
            Log::error('persistOrderPoPdfToStorage failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * PO + invoice + credit note public URLs for partner API success response.
     *
     * @return array{po_link: string|null, invoice_link: string|null, po_number: string|null, credit_note_link: string|null, credit_note_no: string|null}
     */
    public function getOrderDocumentLinks(int $orderId): array
    {
        $poLink = null;
        $invoiceLink = null;
        $poNumber = null;
        $creditNoteLink = null;
        $creditNoteNo = null;

        if (true) {
            $poRow = $this->orderPoDetails->newQuery()->where('order_id', $orderId)->first();
            if ($poRow) {
                $poNumber = (string) ($poRow->po_number ?? '');
                $poNumberSafe = preg_replace('/[^A-Za-z0-9_\-]/', '', $poNumber) ?: ('PO-' . $orderId);
                foreach (['pdf', 'html'] as $ext) {
                    $tryPath = 'order-po/' . $poNumberSafe . '-order-' . $orderId . '.' . $ext;
                    $resolved = $this->resolveStoredDocumentUrl(
                        (string) ($poRow->po_document_url ?? ''),
                        $tryPath
                    );
                    if ($resolved !== null) {
                        $poLink = $resolved;
                        break;
                    }
                }
            }
        }

        if ($poLink === null) {
            $matches = glob(public_path('storage/order-po/*-order-' . $orderId . '.pdf'))
                ?: glob(public_path('storage/order-po/*-order-' . $orderId . '.html'))
                ?: glob(storage_path('app/public/order-po/*-order-' . $orderId . '.pdf'))
                ?: [];
            if ($matches !== [] && is_file($matches[0])) {
                $poLink = $this->publicStorageUrl('order-po/' . basename($matches[0]));
            }
        }

        if ($poLink === null) {
            $rawPo = (string) ($this->orders->newQuery()->where('id', $orderId)->value('po_document_url') ?? '');
            $poLink = $this->resolveStoredDocumentUrl($rawPo, '');
        }

        if (true) {
            // Always refresh storage PDF so /storage/invoices/... matches /orders-invoice/{id}.
            $persisted = $this->persistOrderInvoicePdfToStorage($orderId, true);
            if (!empty($persisted['public_url'])) {
                $invoiceLink = (string) $persisted['public_url'];
            } else {
                $invoiceRow = $this->invoiceMst->newQuery()
                    ->where('order_id', $orderId)
                    ->where(function ($q) {
                        $q->whereNull('action_type')
                            ->orWhere('action_type', '!=', OrderCancellationCreditNoteService::CREDIT_NOTE_ACTION);
                    })
                    ->where(function ($q) {
                        $q->whereNull('bill_no_full')
                            ->orWhere('bill_no_full', 'not like', 'CN/%');
                    })
                    ->orderByDesc('id')
                    ->first();
                if ($invoiceRow) {
                    $billNo = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($invoiceRow->bill_no_full ?? ''));
                    if ($billNo !== '') {
                        $candidatePath = 'invoices/' . $billNo . '.pdf';
                        if (is_file($this->storagePublicPath($candidatePath))) {
                            $invoiceLink = $this->publicStorageUrl($candidatePath);
                        }
                    }
                }
            }
        }

        $creditNoteLink = Cache::get('order_credit_note_link_' . $orderId);
        $creditNoteNo = Cache::get('order_credit_note_no_' . $orderId);
        $cnRow = $this->invoiceMst->newQuery()
            ->where('order_id', $orderId)
            ->where(function ($q) {
                if (Schema::connection($this->invoiceMst->getConnectionName())->hasColumn('invoice_mst', 'action_type')) {
                    $q->where('action_type', OrderCancellationCreditNoteService::CREDIT_NOTE_ACTION);
                } else {
                    $q->where('bill_no_full', 'like', 'CN/%');
                }
            })
            ->orderByDesc('id')
            ->first();
        if ($cnRow) {
            $cnNo = trim((string) ($cnRow->bill_no_full ?? $creditNoteNo ?? ''));
            $persistedCn = $this->persistCreditNotePdfToStorage(
                $orderId,
                (int) $cnRow->id,
                $cnNo !== '' ? $cnNo : ('CN-' . $cnRow->id),
                [],
                true
            );
            if (!empty($persistedCn['public_url'])) {
                $creditNoteLink = (string) $persistedCn['public_url'];
                $creditNoteNo = (string) ($persistedCn['credit_note_no'] ?? $cnNo);
            }
        }
        if (!$creditNoteLink) {
            $cnMatches = glob(public_path('storage/credit-notes/*-order-' . $orderId . '.pdf'))
                ?: glob(storage_path('app/public/credit-notes/*-order-' . $orderId . '.pdf'))
                ?: [];
            if ($cnMatches !== [] && is_file($cnMatches[0])) {
                $creditNoteLink = $this->publicStorageUrl('credit-notes/' . basename($cnMatches[0]));
                if (!$creditNoteNo) {
                    $creditNoteNo = preg_replace('/-order-\d+\.pdf$/i', '', basename($cnMatches[0]));
                }
            }
        }

        return [
            'po_link' => $poLink,
            'invoice_link' => $invoiceLink,
            'po_number' => $poNumber !== '' ? $poNumber : null,
            'credit_note_link' => $creditNoteLink ?: null,
            'credit_note_no' => $creditNoteNo ? (string) $creditNoteNo : null,
        ];
    }

    /**
     * Mark PO journey placed + set orders.status completed after successful Aadhaar OTP.
     */
    public function completeOrderAfterAadhaarVerification(
        int $orderId,
        string $refNo = '',
        ?string $aadhaarMasked = null,
        ?int $aadhaarVerificationId = null,
        bool $skipCreditChecks = false,
        bool $skipAadhaarRecord = false
    ): void {
        if ($orderId <= 0) {
            return;
        }

        $orderRow = $this->orders->newQuery()->where('id', $orderId)->first();
        if (
            !$skipCreditChecks
            && $orderRow
            && strtolower(trim((string) ($orderRow->order_type ?? ''))) === 'b2b'
        ) {
            $partnerId = (int) ($orderRow->partner_id ?? 0);
            if ($partnerId > 0) {
                $amount = $this->resolveOrderGrandTotal($orderRow);
                $creditBlock = $this->partnerCreditBlocksActivation($orderRow, $orderId);
                if ($creditBlock !== null) {
                    throw ValidationException::withMessages([
                        'credit' => $creditBlock,
                    ]);
                }
            }
        }

        if ($aadhaarVerificationId > 0) {
            $this->orders->newQuery()->where('id', $orderId)->update([
                'aadhaar_verification_id' => $aadhaarVerificationId,
                'updated_at' => now(),
            ]);
        }

        if (true) {
            $cols = $this->orderPoDetailColumns();
            $payload = [];
            if (in_array('journey_status', $cols, true)) {
                $payload['journey_status'] = 'placed';
            }
            if (!$skipAadhaarRecord && $aadhaarMasked !== null && $aadhaarMasked !== '' && in_array('aadhaar_masked', $cols, true)) {
                $payload['aadhaar_masked'] = $aadhaarMasked;
            }
            if (!$skipAadhaarRecord && $refNo !== '' && in_array('aadhaar_ref_no', $cols, true)) {
                $payload['aadhaar_ref_no'] = $refNo;
            }
            if (!$skipAadhaarRecord && in_array('aadhar_verified', $cols, true)) {
                $payload['aadhar_verified'] = 1;
            }
            if (!$skipAadhaarRecord && in_array('aadhar_verified_at', $cols, true)) {
                $payload['aadhar_verified_at'] = now();
            }
            if (in_array('updated_at', $cols, true)) {
                $payload['updated_at'] = now();
            }
            if ($payload !== []) {
                $row = $this->orderPoDetails->newQuery()->where('order_id', $orderId)->first();
                if ($row) {
                    $this->orderPoDetails->newQuery()->where('order_id', $orderId)->update($payload);
                } else {
                    $insert = array_merge(['order_id' => $orderId], $payload);
                    if (in_array('created_at', $cols, true)) {
                        $insert['created_at'] = now();
                    }
                    $this->orderPoDetails->newQuery()->insert($insert);
                }
            }

            if (!$skipAadhaarRecord) {
                try {
                    $this->persistOrderPoPdfToStorage($orderId, false);
                } catch (\Throwable $e) {
                    Log::warning('completeOrderAfterAadhaarVerification: PO PDF regenerate failed', [
                        'order_id' => $orderId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->syncOrderPoJourneyOnOrdersTable($orderId, 'placed');

        $orderRow = $this->orders->newQuery()->where('id', $orderId)->first();
        if ($orderRow) {
            $creditPatch = ['updated_at' => now()];
            $patchOrder = false;
            if ((float) ($orderRow->total_amount ?? 0) <= 0) {
                $grandTotal = $this->resolveOrderGrandTotal($orderRow);
                if ($grandTotal > 0) {
                    $creditPatch['total_amount'] = $grandTotal;
                    $patchOrder = true;
                }
            }
            if ($patchOrder) {
                $this->orders->newQuery()->where('id', $orderId)->update($creditPatch);
                $orderRow = $this->orders->newQuery()->where('id', $orderId)->first();
            }
        }

        if ($orderRow) {
            $grandTotal = $this->resolveOrderGrandTotal($orderRow);
            $currentCreditUsed = round((float) ($orderRow->credit_used ?? 0), 2);
            if ($grandTotal > 0 && (
                $currentCreditUsed <= 0.01
                || abs($currentCreditUsed - $grandTotal) > 0.01
            )) {
                $this->orders->newQuery()->where('id', $orderId)->update([
                    'credit_used' => $grandTotal,
                    'updated_at' => now(),
                ]);
                $orderRow = $this->orders->newQuery()->where('id', $orderId)->first() ?: $orderRow;
            }

            $walletPayload = (array) $orderRow;
            // syncB2bOrderWalletLedger only deducts for completed/active — set before DB status flip.
            $walletPayload['status'] = 'completed';
            $walletPayload['credit_used'] = round((float) ($orderRow->credit_used ?? $grandTotal), 2);
            if ($skipCreditChecks) {
                $walletPayload['skip_credit_headroom_check'] = true;
            }
            $this->syncB2bOrderWalletLedger($orderId, $walletPayload);
        }

        $orderRowObj = $this->orders->newQuery()->where('id', $orderId)->first();
        $cartService = app(\App\Services\PartnerCartService::class);
        $multiDomain = false;
        if ($orderRowObj) {
            $domainName = trim((string) ($orderRowObj->domain_name ?? ''));
            if ($domainName !== '') {
                $domainCart = new \App\Models\Cart();
                $domainCart->domain_name = $domainName;
                $multiDomain = $cartService->cartHasMultipleDomains($domainCart);
            }
        }
        if (
            !$multiDomain
        ) {
            $cartId = (int) $this->partnerAadhaarVerifications->newQuery()
                ->where('order_id', $orderId)
                ->orderByDesc('id')
                ->value('cart_id');
            if ($cartId > 0) {
                $linkedCart = \App\Models\Cart::query()->find($cartId);
                if ($linkedCart) {
                    $multiDomain = $cartService->cartHasMultipleDomains($linkedCart);
                }
            }
        }

        $orderRow = (array) ($orderRowObj ?? []);
        if ($orderRow !== [] && !$multiDomain) {
            $this->finalizePartnerB2bOrderPostSave($orderId);
            try {
                $this->persistOrderInvoicePdfToStorage($orderId);
            } catch (\Throwable $e) {
                Log::warning('completeOrderAfterAadhaarVerification: invoice PDF failed', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }
            $this->markInvoiceStatusPendingForOrder($orderId);
        }

        if (true) {
            $this->orders->newQuery()->where('id', $orderId)->update([
                'status' => $multiDomain ? 'pending' : 'completed',
                'updated_at' => now(),
            ]);
        }

        $orderForNotify = $this->orders->newQuery()->where('id', $orderId)->first();
        if ($orderForNotify && !$multiDomain) {
            $category = strtolower(trim((string) ($orderForNotify->order_category ?? 'new')));

            if (in_array($category, ['upgrade', 'downgrade', 'renewal', 'renew'], true)) {
                $this->markParentSubscriptionUpgradedIfNeeded($orderId);
            }

            if ($category === 'renewal' || $category === 'renew') {
                try {
                    $this->sendPlanRenewedNotifications($orderId, $orderForNotify, $skipCreditChecks);
                } catch (\Throwable $e) {
                    Log::warning('completeOrderAfterAadhaarVerification: renewal notifications failed', [
                        'order_id' => $orderId,
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                try {
                    $this->sendOrderPlacedEmailForOrder($orderId, $skipCreditChecks);
                } catch (\Throwable $e) {
                    Log::warning('completeOrderAfterAadhaarVerification: placement email failed', [
                        'order_id' => $orderId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $mobile = '';
            if ($skipCreditChecks) {
                $partnerId = (int) ($orderForNotify->partner_id ?? 0);
                if ($partnerId > 0) {
                    $mobile = trim((string) ($this->partners->newQuery()->where('id', $partnerId)->value('mobile') ?? ''));
                }
            } elseif (!empty($orderForNotify->user_id)) {
                $mobile = trim((string) ($this->customers->newQuery()->where('id', (int) $orderForNotify->user_id)->value('mobile') ?? ''));
            }
            if ($mobile !== '' && $category !== 'renewal') {
                try {
                    $amount = $this->resolveOrderGrandTotal($orderForNotify);
                    $orderNo = (string) ($orderForNotify->order_no ?? $orderId);
                    $sms = app(SmsService::class);
                    if ($category === 'upgrade') {
                        $planName = '';
                        if (!empty($orderForNotify->plan_id)) {
                            $planName = (string) ($this->plans->newQuery()->where('id', (int) $orderForNotify->plan_id)->value('name') ?? '');
                        }
                        $sms->sendUpgradePlatform($mobile, $planName !== '' ? $planName : 'Upgraded plan', $amount);
                    } else {
                        $sms->sendPaymentConfirmation($mobile, $amount, $orderNo);
                    }
                } catch (\Throwable $e) {
                    Log::warning('completeOrderAfterAadhaarVerification: placement SMS failed', [
                        'order_id' => $orderId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Partner B2B checkout: GST ledger (invoice_mst/sub), Sandbox e-invoice, customer email.
     * Delegates to the same private pipeline used after super-admin order placement.
     */
    public function finalizePartnerB2bOrderPostSave(int $orderId): void
    {
        $this->ensureGstInvoiceForOrder($orderId);
        $this->ensurePendingInvoicesRowForOrder($orderId);
        $this->maybeGenerateSandboxEInvoice($orderId);
        $this->markInvoiceStatusPendingForOrder($orderId);

        $order = $this->orders->newQuery()->where('id', $orderId)->first();
        if (!$order) {
            return;
        }

        // Customer email/SMS sent from completeOrderAfterAadhaarVerification after status = completed.
    }

    /**
     * When an upgrade/downgrade/renewal order is completed, mark the original
     * subscription as replaced and issue unused-days credit note for that plan.
     */
    private function markParentSubscriptionUpgradedIfNeeded(int $orderId): void
    {
        $order = $this->orders->newQuery()->whereKey($orderId)->first();
        if (!$order) {
            return;
        }

        $category = strtolower(trim((string) ($order->order_category ?? '')));
        $orderNo = strtoupper(trim((string) ($order->order_no ?? '')));
        $isRenewal = in_array($category, ['renewal', 'renew'], true) || str_starts_with($orderNo, 'RN');
        $isPlanChange = in_array($category, ['upgrade', 'downgrade'], true)
            || str_starts_with($orderNo, 'CH')
            || $isRenewal;

        if (!$isPlanChange) {
            return;
        }

        if (!in_array(strtolower(trim((string) ($order->status ?? ''))), $this->orderSuccessfulStatuses(), true)) {
            return;
        }

        if (in_array($category, ['upgrade', 'downgrade'], true) || (str_starts_with($orderNo, 'CH') && !$isRenewal)) {
            // Ensure category is set for the upgrade service when only CH* prefix exists.
            if (!in_array($category, ['upgrade', 'downgrade'], true) && property_exists($order, 'order_category')) {
                $order->order_category = 'upgrade';
            }
            app(B2bSubscriptionUpgradeService::class)->markSourceSubscriptionUpgraded($order);
        }

        $this->issuePlanChangeCreditNote($orderId);
    }

    /**
     * Unused-days credit note for upgrade / downgrade / renewal activation.
     */
    private function issuePlanChangeCreditNote(int $orderId): void
    {
        try {
            $result = app(\App\Services\OrderCancellationCreditNoteService::class)
                ->issueForPlanChangeOrder($orderId);
            if (empty($result['ok'])) {
                Log::info('issuePlanChangeCreditNote skipped', [
                    'order_id' => $orderId,
                    'message' => (string) ($result['message'] ?? 'unknown'),
                    'remaining_days' => $result['remaining_days'] ?? null,
                ]);

                return;
            }

            Log::info('issuePlanChangeCreditNote issued', [
                'order_id' => $orderId,
                'credit_note_no' => $result['credit_note_no'] ?? null,
                'credit_note_amount' => $result['credit_note_amount'] ?? null,
                'wallet_credited' => $result['wallet_credited'] ?? false,
            ]);
        } catch (\Throwable $e) {
            Log::warning('issuePlanChangeCreditNote failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Public wrapper for partner/API flows — sends order placed email with PO + invoice attachments.
     */
    public function sendOrderPlacedEmailForOrder(int $orderId, bool $toPartner = false): bool
    {
        return $this->sendOrderPlacedClientMail($orderId, $toPartner);
    }

    /**
     * Lightweight partner activation email (no attachments) — reliable fallback.
     */
    public function sendPartnerActiveEmail(int $orderId, ?int $partnerIdOverride = null): bool
    {
        $orderRow = $this->orders->newQuery()->whereKey($orderId)->first();
        if (!$orderRow) {
            return false;
        }

        $partnerId = $partnerIdOverride ?? (int) ($orderRow->partner_id ?? 0);
        if ($partnerId <= 0) {
            return false;
        }

        $partner = $this->partners->newQuery()->whereKey($partnerId)->first();
        if (!$partner) {
            return false;
        }

        $email = trim((string) ($partner->email ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('Partner active email skipped: invalid partner email', [
                'order_id' => $orderId,
                'partner_id' => $partnerId,
                'email' => $email,
            ]);

            return false;
        }

        $order = $this->findOrder($orderId);
        if (!$order) {
            return false;
        }

        $order = (object) array_merge((array) $order, [
            'customer_name' => (string) ($partner->name ?? $partner->company_name ?? 'Partner'),
        ]);

        try {
            Mail::to($email)->send(new OrderPlacedClientMail($order, []));

            Log::info('Partner active email sent', [
                'order_id' => $orderId,
                'partner_id' => $partnerId,
                'email' => $email,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Partner active email failed', [
                'order_id' => $orderId,
                'partner_id' => $partnerId,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Partner SMS/email/WhatsApp when an order becomes active.
     */
    public function notifyPartnerOrderActivation(int $orderId, bool $sendEmail = true): void
    {
        $this->notifyPartnerOrderPlacement($orderId, $sendEmail);
    }

    /**
     * Send activation emails/notifications after the HTTP response (single partner email, faster activate action).
     */
    private function deferOrderActivationNotifications(int $orderId): void
    {
        dispatch(function () use ($orderId) {
            try {
                app(self::class)->runDeferredActivationNotifications($orderId);
            } catch (\Throwable $e) {
                Log::warning('Order activation: notifications failed', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }
        })->afterResponse();
    }

    /**
     * Public entry for afterResponse activation emails.
     */
    public function runDeferredActivationNotifications(int $orderId): void
    {
        $this->sendOrderPlacedClientMail($orderId, false);
        $this->notifyPartnerOrderActivation($orderId, true);
    }

    /**
     * PO/invoice finalize + activation emails after the HTTP response (keeps Active fast).
     */
    private function deferOrderActivationSideEffects(int $orderId): void
    {
        dispatch(function () use ($orderId) {
            try {
                app(self::class)->runDeferredActivationSideEffects($orderId);
            } catch (\Throwable $e) {
                Log::warning('Order activation: deferred side effects failed', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }
        })->afterResponse();
    }

    /**
     * Public entry for afterResponse activation PO/invoice/notifications.
     */
    public function runDeferredActivationSideEffects(int $orderId): void
    {
        try {
            $this->finalizePartnerB2bOrderPostSave($orderId);
            $this->markParentSubscriptionUpgradedIfNeeded($orderId);
            if (!app(\App\Services\OrderPoDocumentService::class)->isUploadedPo($orderId)) {
                $this->ensureGeneratedPoForOrder($orderId);
                $this->persistOrderPoPdfToStorage($orderId);
            }
            $this->persistOrderInvoicePdfToStorage($orderId);
            $this->markInvoiceStatusPendingForOrder($orderId);
            $this->syncOrderPoJourneyOnOrdersTable($orderId, 'placed');
        } catch (\Throwable $e) {
            Log::warning('Order activation: PO/invoice persist failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $this->runDeferredActivationNotifications($orderId);
        } catch (\Throwable $e) {
            Log::warning('Order activation: notifications failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Refund / cancel emails after HTTP response. Credit note is issued synchronously
     * during cancel so a page reload cannot abort it.
     *
     * @param  array{message?: string, credit_note?: ?array}|null  $creditNoteIssue
     */
    private function deferOrderCancellationSideEffects(
        int $orderId,
        string $reason = 'Order cancelled by admin.',
        ?array $creditNoteIssue = null
    ): void {
        dispatch(function () use ($orderId, $reason, $creditNoteIssue) {
            try {
                @ignore_user_abort(true);
                app(self::class)->runDeferredCancellationSideEffects($orderId, $reason, $creditNoteIssue);
            } catch (\Throwable $e) {
                Log::warning('Order cancellation: side effects failed', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }
        })->afterResponse();
    }

    /**
     * Public entry for afterResponse cancellation side effects.
     *
     * @param  array{message?: string, credit_note?: ?array}|null  $creditNoteIssue
     */
    public function runDeferredCancellationSideEffects(
        int $orderId,
        string $reason = 'Order cancelled by admin.',
        ?array $creditNoteIssue = null
    ): void {
        if ($creditNoteIssue === null) {
            $creditNoteIssue = $this->issueCancellationCreditNote($orderId, $reason);
        }
        $this->refundPaidOrderOnCancellation($orderId, $reason);
        $this->markInvoicesCancelledForOrder($orderId);
        $this->sendOrderRejectedClientMail($orderId, $reason);
        $this->notifyPartnerOrderCancelled(
            $orderId,
            $reason,
            $creditNoteIssue['credit_note'] ?? null
        );
    }

    /**
     * Partner WhatsApp when an order is cancelled / rejected.
     *
     * @param  array<string, mixed>|null  $creditNote
     */
    private function notifyPartnerOrderCancelled(int $orderId, string $reason = 'Order cancelled', ?array $creditNote = null): void
    {
        $order = $this->orders->newQuery()->where('id', $orderId)->first();
        if (!$order) {
            return;
        }

        $partnerId = (int) ($order->partner_id ?? 0);
        if ($partnerId <= 0) {
            return;
        }

        $prefs = NotificationPreference::forPartner($partnerId);
        if (!$prefs || !$prefs->isEnabled('subscription', 'whatsapp') || !class_exists(\App\Services\WhatsAppService::class)) {
            return;
        }

        $mobile = trim((string) ($this->partners->newQuery()->where('id', $partnerId)->value('mobile') ?? ''));
        if ($mobile === '') {
            return;
        }

        $orderNo = (string) ($order->order_no ?? $orderId);
        $waReason = $reason !== '' ? $reason : 'Order cancelled';
        if (is_array($creditNote) && !empty($creditNote['ok'])) {
            $cnNo = (string) ($creditNote['credit_note_no'] ?? '');
            $amount = round((float) ($creditNote['credit_note_amount'] ?? 0), 2);
            $link = trim((string) ($creditNote['credit_note_link'] ?? ''));
            $walletCredited = !empty($creditNote['wallet_credited']);
            $extra = '';
            if ($cnNo !== '') {
                $extra .= ' Credit note ' . $cnNo . ' for ₹' . number_format($amount, 2) . '.';
            } elseif ($amount > 0) {
                $extra .= ' Credit note for ₹' . number_format($amount, 2) . '.';
            }
            if ($walletCredited && $amount > 0) {
                $extra .= ' ₹' . number_format($amount, 2) . ' credited to wallet.';
            }
            if ($link !== '') {
                $extra .= ' Link: ' . $link;
            }
            $waReason = trim($waReason . $extra);
            if (strlen($waReason) > 500) {
                $waReason = substr($waReason, 0, 497) . '...';
            }
        }

        try {
            app(\App\Services\WhatsAppService::class)->sendOrderCancelled(
                $mobile,
                $orderNo,
                $waReason !== '' ? $waReason : 'Order cancelled'
            );
        } catch (\Throwable $e) {
            Log::warning('Order cancel: partner WhatsApp failed', [
                'order_id' => $orderId,
                'partner_id' => $partnerId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate Credit Note PDF (same layout as PO), save to public storage.
     *
     * @param  array<string, mixed>  $calc
     * @return array{relative_path: string, public_url: string, credit_note_no: string}|null
     */
    public function persistCreditNotePdfToStorage(
        int $orderId,
        int $creditNoteId,
        string $creditNoteNo,
        array $calc = [],
        bool $force = true
    ): ?array {
        try {
            if ($orderId <= 0 || $creditNoteId <= 0) {
                return null;
            }

            $safeNo = preg_replace('/[^A-Za-z0-9_\-]/', '', $creditNoteNo) ?: ('CN-' . $creditNoteId);
            $relativePath = 'credit-notes/' . $safeNo . '-order-' . $orderId . '.pdf';

            $existing = $this->resolveExistingPublicStorageFile($relativePath);
            if (!$force && $existing !== null) {
                $publicUrl = $this->publicStorageUrl($relativePath);
                Cache::put('order_credit_note_link_' . $orderId, $publicUrl, now()->addDays(365));
                Cache::put('order_credit_note_no_' . $orderId, $creditNoteNo, now()->addDays(365));

                return [
                    'relative_path' => $relativePath,
                    'public_url' => $publicUrl,
                    'credit_note_no' => $creditNoteNo,
                ];
            }

            if ($calc === [] || (
                (int) ($calc['remaining_days'] ?? 0) <= 0
                && (int) ($calc['total_plan_days'] ?? 0) <= 0
            )) {
                $order = $this->findOrder($orderId);
                if ($order) {
                    $cancelAt = null;
                    $creditNoteRow = $this->invoiceMst->newQuery()->whereKey($creditNoteId)->first();
                    if ($creditNoteRow && !empty($creditNoteRow->bill_date)) {
                        try {
                            $cancelAt = Carbon::parse((string) $creditNoteRow->bill_date);
                        } catch (\Throwable $e) {
                            $cancelAt = null;
                        }
                    }
                    $calc = array_merge(
                        $calc,
                        app(OrderCancellationCreditNoteService::class)->calculateProrataRefund($order, $cancelAt)
                    );
                }
            }

            $viewData = $this->buildCreditNoteViewData($orderId, $creditNoteId, $creditNoteNo, $calc);
            if ($viewData === null) {
                Log::warning('persistCreditNotePdfToStorage: no view data', [
                    'order_id' => $orderId,
                    'credit_note_id' => $creditNoteId,
                ]);

                return null;
            }

            // Keep invoice_sub qty in sync with order licenses (PDF was hardcoded to 1).
            // Never block PDF generation if the line update fails.
            try {
                $firstItem = is_array($viewData['items'][0] ?? null) ? $viewData['items'][0] : [];
                $pdfQty = max(1, (int) ($firstItem['quantity'] ?? $firstItem['qty'] ?? 1));
                $billingConn = $this->invoiceMst->getConnectionName() ?: 'pgsql_billing';
                if (Schema::connection($billingConn)->hasTable('invoice_sub')) {
                    $taxableForSub = round((float) ($viewData['taxable_total'] ?? 0), 2);
                    $unitRate = round($taxableForSub / $pdfQty, 2);
                    $subUpdate = ['qty' => $pdfQty, 'cnt_rate' => $unitRate];
                    if (Schema::connection($billingConn)->hasColumn('invoice_sub', 'updated_at')) {
                        $subUpdate['updated_at'] = now();
                    }
                    \App\Models\InvoiceSub::query()
                        ->where('invoice_mst_id', $creditNoteId)
                        ->update($subUpdate);
                }
            } catch (\Throwable $e) {
                Log::warning('persistCreditNotePdfToStorage: invoice_sub qty sync skipped', [
                    'order_id' => $orderId,
                    'credit_note_id' => $creditNoteId,
                    'error' => $e->getMessage(),
                ]);
            }

            $viewData['for_pdf'] = true;

            try {
                $html = view('superadmin.credit_note_pdf', $viewData)->render();
            } catch (\Throwable $e) {
                Log::error('Credit note view render failed', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }

            $binary = $this->renderHtmlToPdfBinary($this->prepareInvoiceHtmlForPdf($html));
            if ($binary === null || $binary === '') {
                Log::warning('persistCreditNotePdfToStorage: empty PDF', [
                    'order_id' => $orderId,
                    'credit_note_id' => $creditNoteId,
                    'html_len' => strlen($html),
                ]);

                return null;
            }

            if (!$this->writePublicStorageFile($relativePath, $binary)) {
                // Fallback filename used by older CNs if safeNo format differs.
                $altPath = 'credit-notes/CN' . preg_replace('/\D+/', '', $creditNoteNo) . '-order-' . $orderId . '.pdf';
                if ($altPath !== $relativePath && $this->writePublicStorageFile($altPath, $binary)) {
                    $relativePath = $altPath;
                } else {
                    Log::error('persistCreditNotePdfToStorage: file not written', [
                        'order_id' => $orderId,
                        'path' => $relativePath,
                    ]);

                    return null;
                }
            }

            $publicUrl = $this->publicStorageUrl($relativePath);
            Cache::put('order_credit_note_link_' . $orderId, $publicUrl, now()->addDays(365));
            Cache::put('order_credit_note_no_' . $orderId, $creditNoteNo, now()->addDays(365));
            Cache::put('credit_note_link_' . $creditNoteId, $publicUrl, now()->addDays(365));

            $mstCols = Schema::connection($this->invoiceMst->getConnectionName())->hasTable('invoice_mst')
                ? Schema::connection($this->invoiceMst->getConnectionName())->getColumnListing('invoice_mst')
                : [];
            if (in_array('document_url', $mstCols, true) || in_array('pdf_url', $mstCols, true)) {
                $col = in_array('document_url', $mstCols, true) ? 'document_url' : 'pdf_url';
                $this->invoiceMst->newQuery()->whereKey($creditNoteId)->update([
                    $col => $publicUrl,
                    'updated_at' => now(),
                ]);
            }

            return [
                'relative_path' => $relativePath,
                'public_url' => $publicUrl,
                'credit_note_no' => $creditNoteNo,
            ];
        } catch (\Throwable $e) {
            Log::error('persistCreditNotePdfToStorage failed', [
                'order_id' => $orderId,
                'credit_note_id' => $creditNoteId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Credit note PDF view data — same PO/tax-invoice layout with credit-note amounts.
     *
     * @param  array<string, mixed>  $calc
     * @return array<string, mixed>|null
     */
    private function buildCreditNoteViewData(
        int $orderId,
        int $creditNoteId,
        string $creditNoteNo,
        array $calc = []
    ): ?array {
        $base = $this->buildGeneratedPoViewData($orderId);
        if ($base === null) {
            // Soft fallback when PO/invoice base cannot be built (still emit CN PDF).
            $order = $this->findOrder($orderId);
            if (!$order) {
                return null;
            }
            $enrollmentMeta = $this->resolveOrderEnrollmentMeta($order);
            $base = [
                'order_no' => (string) ($order->order_no ?? $orderId),
                'order' => $order,
                'tax_type' => GstSplit::split(
                    0,
                    (string) ($order->po_manual_gstin ?? $order->customer_gstin ?? '')
                )['tax_type'],
                'company' => $this->resolveCompanyMeta(),
                'customer' => [
                    'name' => (string) ($order->customer_company_name ?? $order->customer_name ?? '-'),
                    'billing_address' => (string) ($order->customer_billing_address ?? '-'),
                    'phone' => (string) ($order->customer_mobile ?? ''),
                    'gstin' => (string) ($order->customer_gstin ?? '-'),
                    'state_name' => '',
                    'state_code' => '',
                ],
                'bank' => $this->resolveBankMeta(),
                'due_date' => Carbon::now()->addDays(15)->format('jS F Y'),
                'transfer_domain' => (string) ($enrollmentMeta['transfer_domain'] ?? ''),
                'enrollment_type' => (string) ($enrollmentMeta['enrollment_type'] ?? 'New Service'),
            ];
        }

        $creditNote = $this->invoiceMst->newQuery()->whereKey($creditNoteId)->first();
        if (!$creditNote) {
            return null;
        }
        $netAmt = round((float) ($creditNote->net_amt ?? $calc['credit_note_amount'] ?? 0), 2);
        $taxable = round((float) ($creditNote->taxable_total ?? 0), 2);
        $cgst = round((float) ($creditNote->cgst_total ?? 0), 2);
        $sgst = round((float) ($creditNote->sgst_total ?? 0), 2);
        $igst = round((float) ($creditNote->igst_total ?? 0), 2);
        $gstTotal = round($cgst + $sgst + $igst, 2);
        if ($taxable <= 0 && $netAmt > 0) {
            $taxable = round(max(0.0, $netAmt - $gstTotal), 2);
        }
        if ($netAmt <= 0 && !empty($calc['credit_note_amount'])) {
            $netAmt = round((float) $calc['credit_note_amount'], 2);
            if ($taxable <= 0) {
                $taxable = round(max(0.0, $netAmt - $gstTotal), 2);
            }
        }
        if ($netAmt <= 0) {
            $netAmt = round($taxable + $gstTotal, 2);
        }

        $remainingDays = (int) ($calc['remaining_days'] ?? 0);
        $totalPlanDays = (int) ($calc['total_plan_days'] ?? 0);
        $usedDays = (int) ($calc['used_days'] ?? 0);
        $orderNo = (string) ($base['order_no'] ?? $orderId);

        if ($totalPlanDays <= 0) {
            $order = $this->findOrder($orderId);
            if ($order) {
                $cancelAt = null;
                if ($creditNote && !empty($creditNote->bill_date)) {
                    try {
                        $cancelAt = Carbon::parse((string) $creditNote->bill_date);
                    } catch (\Throwable $e) {
                        $cancelAt = null;
                    }
                }
                $recalc = app(OrderCancellationCreditNoteService::class)->calculateProrataRefund($order, $cancelAt);
                $remainingDays = (int) ($recalc['remaining_days'] ?? $remainingDays);
                $totalPlanDays = (int) ($recalc['total_plan_days'] ?? $totalPlanDays);
                $usedDays = (int) ($recalc['used_days'] ?? $usedDays);
            }
        }

        $planDays = $totalPlanDays > 0 ? $totalPlanDays : 0;

        // Line item: short title once + unused-days detail once.
        // Do NOT also put the same text in service_details (layout would print it a third time).
        $itemName = 'Unused subscription days';
        $itemDesc = sprintf(
            '%d of %d unused (used %d). Against Order %s',
            $remainingDays,
            $planDays,
            $usedDays,
            $orderNo
        );

        // Use order license qty (e.g. 10), not hardcoded 1 — rate = taxable ÷ qty.
        $qty = 1;
        $orderForQty = $this->findOrder($orderId);
        if ($orderForQty) {
            $qty = app(OrderCancellationCreditNoteService::class)->resolveOrderLicenseQty($orderForQty);
        }
        $unitRate = $qty > 0 ? round($taxable / $qty, 2) : $taxable;

        $taxType = strtolower(trim((string) ($creditNote->tax_type ?? $base['tax_type'] ?? '')));
        if ($gstTotal <= 0 && $taxable > 0 && $netAmt > $taxable) {
            $gstTotal = round($netAmt - $taxable, 2);
        }
        if ($gstTotal > 0 && $cgst <= 0 && $sgst <= 0 && $igst <= 0) {
            $custGstin = (string) ($base['customer']['gstin'] ?? '');
            $coGstin = (string) ($base['company']['gstin'] ?? '');
            $split = GstSplit::split($gstTotal, $custGstin, $coGstin);
            $cgst = $split['cgst'];
            $sgst = $split['sgst'];
            $igst = $split['igst'];
            $taxType = $split['tax_type'];
        } elseif ($taxType === '') {
            $taxType = $igst > 0 ? 'igst' : 'cgst_sgst';
        }
        $items = [[
            'description' => $itemDesc,
            'name' => $itemName,
            'hsn' => '997331',
            'hsn_sac' => '997331',
            'quantity' => $qty,
            'qty' => $qty,
            'rate' => $unitRate,
            'unit_price' => $unitRate,
            'taxable' => $taxable,
            'total' => $taxable,
            'amount' => $taxable,
        ]];

        $billDate = !empty($creditNote->bill_date)
            ? Carbon::parse((string) $creditNote->bill_date)
            : Carbon::now();

        return array_merge($base, [
            'document_title' => 'Credit Note',
            'po_number' => $creditNoteNo !== '' ? $creditNoteNo : ('CN-' . $creditNoteId),
            'order_no' => $orderNo,
            'order_status' => 'credit_note',
            'is_order_cancelled' => false,
            'po_date' => $billDate->format('d-m-Y'),
            'po_date_alt' => $billDate->format('d-M-y'),
            'items' => $items,
            'subtotal' => $taxable,
            'taxable_total' => $taxable,
            'gross_subtotal' => $taxable,
            'discount_amount' => 0,
            'gst_total' => $gstTotal,
            'cgst_total' => $cgst,
            'sgst_total' => $sgst,
            'igst_total' => $igst,
            'tax_type' => $taxType,
            'gst_rate' => GstSplit::RATE,
            'total' => $netAmt,
            'total_amount' => $netAmt,
            'terms_of_payment' => 'Amount credited to partner wallet.',
            'terms_of_delivery' => sprintf(
                'Issued on cancellation of Order %s. Remaining days: %d of %d. Amount is credited to partner wallet balance.',
                $orderNo,
                $remainingDays,
                $planDays
            ),
            'service_details' => '',
            'footer_note' => 'This is a Computer Generated Credit Note',
            'show_aadhaar_signature_stamp' => false,
            'aadhaar_mandatory_yes' => false,
            'aadhaar_signature_valid' => false,
        ]);
    }

    /**
     * Prorated cancellation credit note for unused subscription days; amount is credited to partner wallet.
     *
     * @return array{message: string, credit_note: ?array}
     */
    private function issueCancellationCreditNote(int $orderId, string $reason = 'Order cancelled'): array
    {
        try {
            $result = app(OrderCancellationCreditNoteService::class)->issueForCancelledOrder($orderId, $reason);
            if (empty($result['ok'])) {
                $skipMessage = trim((string) ($result['message'] ?? 'Credit note was not generated.'));
                Log::warning('issueCancellationCreditNote skipped', [
                    'order_id' => $orderId,
                    'message' => $skipMessage,
                    'used_days' => $result['used_days'] ?? null,
                    'remaining_days' => $result['remaining_days'] ?? null,
                ]);

                return [
                    'message' => $skipMessage !== '' ? ' ' . $skipMessage : '',
                    'credit_note' => null,
                ];
            }

            $linkPart = '';
            $link = trim((string) ($result['credit_note_link'] ?? ''));
            if ($link !== '') {
                $linkPart = ' Link: ' . $link;
            }

            $walletPart = !empty($result['wallet_credited'])
                ? ' Amount credited to partner wallet.'
                : '';

            $message = sprintf(
                ' Credit note %s: ₹%s for %d unused day(s) of %d (used: %d day(s)).%s%s',
                (string) ($result['credit_note_no'] ?? ('#' . ($result['credit_note_id'] ?? ''))),
                number_format((float) ($result['credit_note_amount'] ?? 0), 2),
                (int) ($result['remaining_days'] ?? 0),
                (int) ($result['total_plan_days'] ?? 0),
                (int) ($result['used_days'] ?? 0),
                $walletPart,
                $linkPart
            );

            return ['message' => $message, 'credit_note' => $result];
        } catch (\Throwable $e) {
            Log::warning('issueCancellationCreditNote failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return [
                'message' => ' Credit note could not be issued: ' . $e->getMessage(),
                'credit_note' => null,
            ];
        }
    }

    /**
     * Auto-refund Razorpay payment(s) when a paid order is cancelled.
     * Returns a short admin-facing note (empty if nothing refundable).
     */
    private function refundPaidOrderOnCancellation(int $orderId, string $reason): string
    {
        try {
            $results = app(\App\Services\RazorpayRefundService::class)
                ->refundOnOrderCancellation($orderId, $reason);

            Log::info('Order cancel auto-refund', [
                'order_id' => $orderId,
                'results' => $results,
            ]);

            $refunded = 0.0;
            $anyOk = false;
            $anyFail = false;
            foreach ($results as $row) {
                if (!empty($row['ok']) && isset($row['amount'])) {
                    $anyOk = true;
                    $refunded += (float) $row['amount'];
                } elseif (!empty($row['ok'])) {
                    $anyOk = true;
                } else {
                    $anyFail = true;
                }
            }

            if ($refunded > 0) {
                return ' Razorpay refund of ₹' . number_format($refunded, 2) . ' initiated.';
            }
            if ($anyOk && !$anyFail) {
                return ' Payment already refunded or no refundable balance.';
            }
            if ($anyFail) {
                $msg = trim((string) ($results[0]['message'] ?? 'Refund failed'));
                if (stripos($msg, 'No Razorpay payment') !== false || stripos($msg, 'wallet-only') !== false) {
                    return '';
                }

                return ' Refund could not be completed automatically: ' . $msg;
            }
        } catch (\Throwable $e) {
            Log::warning('Order cancel auto-refund exception', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return ' Refund could not be completed automatically: ' . $e->getMessage();
        }

        return '';
    }

    /**
     * Partner SMS + email when wizard order is marked active (subscription prefs).
     */
    private function notifyPartnerOrderPlacement(int $orderId, bool $forceEmail = false): void
    {
        $order = $this->orders->newQuery()->where('id', $orderId)->first();
        if (!$order) {
            return;
        }

        $partnerId = (int) ($order->partner_id ?? 0);
        if ($partnerId <= 0) {
            return;
        }

        $prefs = true
            ? NotificationPreference::forPartner($partnerId)
            : null;

        $category = strtolower(trim((string) ($order->order_category ?? 'new')));
        $amount = $this->resolveOrderGrandTotal($order);
        $orderNo = (string) ($order->order_no ?? $orderId);

        if ($prefs && $prefs->isEnabled('subscription', 'sms')) {
            $mobile = trim((string) ($this->partners->newQuery()->where('id', $partnerId)->value('mobile') ?? ''));
            if ($mobile !== '') {
                try {
                    $sms = app(SmsService::class);
                    if ($category === 'renewal') {
                        $endDate = !empty($order->subscription_end_date)
                            ? Carbon::parse((string) $order->subscription_end_date)->format('d M, Y')
                            : 'N/A';
                        $sms->sendRenewPlan($mobile, $endDate, $amount);
                    } elseif (in_array($category, ['upgrade', 'upgraded'], true)) {
                        $planName = '';
                        if (!empty($order->plan_id)) {
                            $planName = (string) ($this->plans->newQuery()->where('id', (int) $order->plan_id)->value('name') ?? '');
                        }
                        $sms->sendUpgradePlatform($mobile, $planName !== '' ? $planName : 'Upgraded plan', $amount);
                    } else {
                        $sms->sendPaymentConfirmation($mobile, $amount, $orderNo);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Wizard order completed: partner SMS failed', [
                        'order_id' => $orderId,
                        'partner_id' => $partnerId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if ($prefs && $prefs->isEnabled('subscription', 'whatsapp') && class_exists(\App\Services\WhatsAppService::class)) {
            $mobile = trim((string) ($this->partners->newQuery()->where('id', $partnerId)->value('mobile') ?? ''));
            if ($mobile !== '') {
                try {
                    $notifyCategory = in_array($category, ['renewal', 'upgrade', 'upgraded', 'downgrade'], true)
                        ? $category
                        : 'new';
                    $waContext = ['amount' => $amount];
                    if ($category === 'renewal' && !empty($order->subscription_end_date)) {
                        $waContext['end_date'] = Carbon::parse((string) $order->subscription_end_date)->format('d M, Y');
                    }
                    if (in_array($notifyCategory, ['upgrade', 'upgraded', 'downgrade'], true)) {
                        $planName = '';
                        if (!empty($order->plan_id)) {
                            $planName = (string) ($this->plans->newQuery()->where('id', (int) $order->plan_id)->value('name') ?? '');
                        }
                        $waContext['plan_name'] = $planName !== ''
                            ? $planName
                            : ($notifyCategory === 'downgrade' ? 'Downgraded plan' : 'Upgraded plan');
                    }
                    if ($notifyCategory === 'new') {
                        $planName = trim((string) ($order->plan_name ?? ''));
                        if ($planName === '' && !empty($order->plan_id)) {
                            $planName = trim((string) ($this->plans->newQuery()->where('id', (int) $order->plan_id)->value('name') ?? ''));
                        }
                        if ($planName !== '') {
                            $waContext['plan_name'] = $planName;
                        }
                    }

                    $invoiceStored = $this->persistOrderInvoicePdfToStorage($orderId, false);
                    if (!empty($invoiceStored['public_url'])) {
                        $waContext['invoice_url'] = (string) $invoiceStored['public_url'];
                    }

                    // Send the activation template and attach the generated invoice.
                    Log::info('Wizard order completed: partner WhatsApp attempt', [
                        'order_id' => $orderId,
                        'partner_id' => $partnerId,
                        'order_no' => $orderNo,
                        'category' => $notifyCategory,
                        'mobile' => $mobile,
                        'context' => $waContext,
                    ]);
                    $waOk = app(\App\Services\WhatsAppService::class)->sendOrderNotification($mobile, $orderNo, $notifyCategory, $waContext);
                    Log::info('Wizard order completed: partner WhatsApp result', [
                        'order_id' => $orderId,
                        'partner_id' => $partnerId,
                        'success' => $waOk,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('Wizard order completed: partner WhatsApp failed', [
                        'order_id' => $orderId,
                        'partner_id' => $partnerId,
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                Log::info('Wizard order completed: partner WhatsApp skipped (no mobile)', [
                    'order_id' => $orderId,
                    'partner_id' => $partnerId,
                ]);
            }
        } else {
            Log::info('Wizard order completed: partner WhatsApp skipped (preference off or service missing)', [
                'order_id' => $orderId,
                'partner_id' => $partnerId,
                'subscription_whatsapp' => $prefs ? (bool) $prefs->isEnabled('subscription', 'whatsapp') : null,
                'service_exists' => class_exists(\App\Services\WhatsAppService::class),
            ]);
        }

        if ($forceEmail || ($prefs && $prefs->isEnabled('subscription', 'email'))) {
            try {
                if (!$forceEmail && $category === 'renewal') {
                    $partner = $this->partners->newQuery()->where('id', $partnerId)->first();
                    $userEmail = trim((string) ($partner->email ?? ''));
                    $userName = trim((string) ($partner->name ?? $partner->company_name ?? 'Partner'));
                    $planName = '';
                    if (!empty($order->plan_id)) {
                        $planName = (string) ($this->plans->newQuery()->where('id', (int) $order->plan_id)->value('name') ?? '');
                    }
                    if ($planName === '') {
                        $planName = 'Subscription';
                    }
                    $validityDate = '-';
                    if (!empty($order->subscription_end_date)) {
                        $validityDate = Carbon::parse((string) $order->subscription_end_date)->format('d M, Y');
                    }
                    if ($userEmail !== '' && filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
                        Mail::to($userEmail)->send(new PlanRenewedMail(
                            $planName,
                            $amount,
                            $validityDate,
                            $userName,
                            $orderNo
                        ));
                    }
                } else {
                    $this->sendOrderPlacedClientMail($orderId, true);
                }
            } catch (\Throwable $e) {
                Log::warning('Wizard order completed: partner email failed', [
                    'order_id' => $orderId,
                    'partner_id' => $partnerId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            Notification::query()->create([
                'partner_id' => $partnerId,
                'title' => 'Order activated',
                'message' => 'Order #' . $orderNo . ' is now active. Invoice has been sent to your email.',
                'is_read' => 0,
                'order_id' => $orderId,
                'status' => 'active',
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Wizard order completed: partner web notification failed', [
                'order_id' => $orderId,
                'partner_id' => $partnerId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Renewal SMS + PlanRenewedMail (same as PartnerCustomerController::notifyPlanRenewed).
     */
    public function sendPlanRenewedNotifications(int $orderId, object $orderRow, bool $toPartner = false): void
    {
        $userMobile = '';
        $userEmail = '';
        $userName = 'Customer';

        if ($toPartner) {
            $partnerId = (int) ($orderRow->partner_id ?? 0);
            if ($partnerId > 0) {
                $partner = $this->partners->newQuery()->where('id', $partnerId)->first();
                if ($partner) {
                    $userMobile = trim((string) ($partner->mobile ?? ''));
                    $userEmail = trim((string) ($partner->email ?? ''));
                    $userName = trim((string) ($partner->name ?? $partner->company_name ?? 'Partner'));
                }
            }
        } else {
            $customer = null;
            if (!empty($orderRow->user_id)) {
                $customer = $this->customers->newQuery()->where('id', (int) $orderRow->user_id)->first();
            }

            $userMobile = trim((string) ($customer->mobile ?? $customer->phone ?? ''));
            $userEmail = trim((string) ($customer->email ?? ''));
            $userName = trim((string) ($customer->name ?? $customer->company_name ?? 'Customer'));
        }

        $planName = (string) ($orderRow->plan_name ?? '');
        if ($planName === '' && !empty($orderRow->plan_id)) {
            $planName = (string) ($this->plans->newQuery()->where('id', (int) $orderRow->plan_id)->value('name') ?? '');
        }
        if ($planName === '') {
            $planName = 'Subscription';
        }

        $amount = $this->resolveOrderGrandTotal($orderRow);
        $validityDate = '-';
        if (!empty($orderRow->subscription_end_date)) {
            $validityDate = Carbon::parse((string) $orderRow->subscription_end_date)->format('d M, Y');
        }
        $orderNo = (string) ($orderRow->order_no ?? $orderId);

        if ($userMobile !== '') {
            $smsValidity = $validityDate !== '-' ? $validityDate : '-';
            app(SmsService::class)->sendRenewPlan($userMobile, $smsValidity, $amount);
        }

        if ($userEmail !== '' && filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            Mail::to($userEmail)->send(new PlanRenewedMail(
                $planName,
                $amount,
                $validityDate,
                $userName,
                $orderNo
            ));
        }
    }

    /**
     * Source-subscription renew=1 flow (Partner API); not for pending wizard orders.
     */
    private function shouldUsePartnerRenewalAadhaarFlow(Request $request): bool
    {
        if (!$request->filled('order_id') || $request->filled('main_cart_id')) {
            return false;
        }

        $orderId = (int) $request->input('order_id');
        if ($orderId > 0) {
            $status = strtolower(trim((string) ($this->orders->newQuery()->where('id', $orderId)->value('status') ?? '')));
            if ($status === 'pending') {
                return false;
            }
        }

        foreach (['renew', 'is_renew', 'renewal'] as $key) {
            $value = $request->input($key);
            if ($value === true || $value === 1 || $value === '1' || $value === 'true') {
                return true;
            }
        }

        return app(PartnerAadhaarVerificationService::class)->getLatestForRenewal($orderId) !== null;
    }

    /**
     * Latest tax invoice for an order — never a credit note (CN/…).
     */
    public function latestTaxInvoiceMstForOrder(int $orderId)
    {
        if ($orderId <= 0) {
            return null;
        }

        $query = $this->invoiceMst->newQuery()->where('order_id', $orderId);
        $conn = $this->invoiceMst->getConnectionName();
        if (Schema::connection($conn)->hasColumn('invoice_mst', 'action_type')) {
            $query->where(function ($q) {
                $q->whereNull('action_type')
                    ->orWhereRaw("LOWER(TRIM(COALESCE(action_type, ''))) <> ?", [
                        OrderCancellationCreditNoteService::CREDIT_NOTE_ACTION,
                    ]);
            });
        }
        $query->whereRaw("UPPER(TRIM(COALESCE(bill_no_full, ''))) NOT LIKE 'CN%'");

        return $query->orderByDesc('id')->first();
    }

    /**
     * Generate tax-invoice PDF (identical to /superadmin/orders-invoice/{id}) and save under
     * storage/.../invoices/{billNo}.pdf. Public URL path stays the same.
     *
     * @return array{relative_path: string, public_url: string, invoice_number: string}|null
     */
    public function persistOrderInvoicePdfToStorage(int $orderId, bool $force = true): ?array
    {
        $invoiceMstRow = $this->latestTaxInvoiceMstForOrder($orderId);

        // force=false: return the already stored PDF before the expensive buildInvoiceData() / render
        $quickNumber = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($invoiceMstRow->bill_no_full ?? ''));
        if (!$force && $quickNumber !== '' && !preg_match('/^CN/i', $quickNumber)
            && $this->resolveExistingPublicStorageFile('invoices/' . $quickNumber . '.pdf') !== null) {
            return [
                'relative_path' => 'invoices/' . $quickNumber . '.pdf',
                'public_url' => $this->publicStorageUrl('invoices/' . $quickNumber . '.pdf'),
                'invoice_number' => (string) $invoiceMstRow->bill_no_full,
            ];
        }

        $invoiceData = $this->buildInvoiceData($orderId);
        if (!$invoiceData) {
            return null;
        }

        $rawNumber = (string) (
            ($invoiceMstRow->bill_no_full ?? null)
            ?: ($invoiceData['invoice_number'] ?? null)
            ?: ('ORDER-' . $orderId)
        );
        if (preg_match('/^CN[\s\/\-]*/i', trim($rawNumber))) {
            $rawNumber = (string) (
                optional($this->latestTaxInvoiceMstForOrder($orderId))->bill_no_full
                ?: ('ORDER-' . $orderId)
            );
        }

        $invoiceNumber = preg_replace('/[^A-Za-z0-9_\-]/', '', $rawNumber);
        if ($invoiceNumber === '' || preg_match('/^CN/i', $invoiceNumber)) {
            $invoiceNumber = 'ORDER-' . $orderId;
        }

        $relativePath = 'invoices/' . $invoiceNumber . '.pdf';

        // Default force=true: overwrite so storage PDF matches live orders-invoice view.
        if (!$force) {
            $existingInvoiceFile = $this->resolveExistingPublicStorageFile($relativePath);
            if ($existingInvoiceFile !== null) {
                return [
                    'relative_path' => $relativePath,
                    'public_url' => $this->publicStorageUrl($relativePath),
                    'invoice_number' => (string) ($invoiceData['invoice_number'] ?? $invoiceNumber),
                ];
            }
        }

        $binary = $this->renderInvoicePdfBinary($invoiceData);
        if ($binary === null || $binary === '') {
            Log::warning('persistOrderInvoicePdfToStorage: PDF render failed', [
                'order_id' => $orderId,
            ]);
            return null;
        }

        if (!$this->writePublicStorageFile($relativePath, $binary)) {
            return null;
        }

        try {
            $this->syncInvoiceAmountsFromOrder($orderId);
        } catch (\Throwable $e) {
            Log::warning('persistOrderInvoicePdfToStorage: invoice amount sync failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }

        $publicUrl = $this->publicStorageUrl($relativePath);
        $publicUrl .= (str_contains($publicUrl, '?') ? '&' : '?') . 'v=' . time();

        return [
            'relative_path' => $relativePath,
            'public_url' => $publicUrl,
            'invoice_number' => (string) ($invoiceData['invoice_number'] ?? $invoiceNumber),
        ];
    }

    public function legacyManualOrderForm()
    {
        $providerMeta = $this->buildOrderFormProviderMeta();
        $partners = $this->getPartnerOptions();
        $partnerEncryptedById = [];
        foreach ($partners as $partner) {
            $pid = (int) ($partner->id ?? 0);
            if ($pid > 0) {
                $partnerEncryptedById[$pid] = Crypt::encrypt($pid);
            }
        }

        return view('superadmin.orders.legacy_manual', array_merge($providerMeta, [
            'partners' => $partners,
            'partnerEncryptedById' => $partnerEncryptedById,
            'orderFormCustomers' => $this->getOrderFormCustomers(),
            'customersPartnerColumn' => true,
            'partnerCustomerAddOpenUrl' => route('superadmin.b2b_partners.customers.add_open'),
            'partnerCustomerAddUrlTemplate' => route('superadmin.b2b_partners.customers.add', ['partner_id' => '__ENCRYPTED__']),
            'partnerAddUrl' => route('superadmin.b2b_partners.add'),
            'storeUrl' => route('superadmin.manual-create-order.store'),
            'ordersIndexUrl' => route('superadmin.orders.index'),
            'legacyPlansUrl' => route('superadmin.manual-create-order.plans'),
            'plansByProviderUrl' => route('superadmin.orders.plans_by_provider'),
            'planSaveUrlTemplate' => url('/superadmin/plans/save-plan/__PID__/0'),
        ]));
    }

    public function legacyManualPlanOptions(Request $request)
    {
        $providerId = (int) $request->query('provider_id', 0);

        return response()->json([
            'plans' => $this->buildLegacyManualPlanOptions($providerId),
            'has_variant' => $this->providerHasVariants($providerId),
        ]);
    }

    public function storeLegacyManualOrder(Request $request)
    {
        $validated = $request->validate([
            'partner_id' => ['required', 'integer', 'exists:pgsql_auth.partners,id'],
            'user_id' => ['required', 'integer', 'exists:customers,id'],
            'domain_name' => ['required', 'string', 'max:255'],
            'subscription_start_date' => ['required', 'date'],
            'subscription_end_date' => ['required', 'date', 'after_or_equal:subscription_start_date'],
            'provider_id' => ['required', 'integer', 'exists:providers,id'],
            'plans' => ['required', 'array', 'min:1'],
            'plans.*.plan_id' => ['required', 'integer', 'exists:plans,id'],
            'plans.*.quantity' => ['required', 'integer', 'min:1'],
            'plans.*.unit_price' => ['required', 'numeric', 'min:0'],
            'aadhaar_number' => ['nullable', 'string', 'max:20'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
        ]);
        $partnerId = (int) $validated['partner_id'];
        $customerId = (int) $validated['user_id'];
        $providerId = (int) $validated['provider_id'];
        $planLines = $this->normalizeLegacyPlanLines($request);

        if ($planLines === []) {
            return back()->withInput()->with('error', 'Add at least one plan with price and license quantity.');
        }

        $amount = 0.0;
        $totalQty = 0;
        foreach ($planLines as $line) {
            $amount += round((float) $line['unit_price'] * (int) $line['quantity'], 2);
            $totalQty += (int) $line['quantity'];
        }
        $amount = round($amount, 2);
        $planId = (int) $planLines[0]['plan_id'];

        if (true) {
            $customerPartnerId = (int) $this->customers->newQuery()->where('id', $customerId)->value('partner_id');
            if ($customerPartnerId > 0 && $customerPartnerId !== $partnerId) {
                return back()->withInput()->with('error', 'Customer does not belong to the selected partner.');
            }
        }

        foreach ($planLines as $line) {
            $planRow = true
                ? $this->plans->newQuery()->where('id', (int) $line['plan_id'])->first()
                : null;
            if (!$planRow) {
                return back()->withInput()->with('error', 'One of the selected plans was not found.');
            }
            if ((int) ($planRow->provider_id ?? 0) !== $providerId) {
                return back()->withInput()->with('error', 'All plans must belong to the selected provider.');
            }
            $limit = (int) ($planRow->customer_limit ?? 0);
            if ($limit > 0 && (int) $line['quantity'] > $limit) {
                $planName = trim((string) ($planRow->name ?? '')) ?: ('Plan #' . $line['plan_id']);

                return back()->withInput()->with(
                    'error',
                    'Licenses for "' . $planName . '" cannot exceed ' . $limit . ' (plan customer limit).'
                );
            }
        }

        $orderColumns = $this->orderColumns();
        $amountColumn = $this->getAmountColumn();

        DB::beginTransaction();
        try {
            $payload = [
                'user_id' => $customerId,
                'partner_id' => $partnerId,
                'order_type' => 'b2b',
                'domain_name' => trim((string) $validated['domain_name']),
                'quantity' => max(1, $totalQty),
                'status' => 'completed',
                'apply_gst' => 0,
                'discount_amount' => 0, // fixed
                'gst_amount' => 0,
                'tax_amount' => 0,
                'credit_used' => 0,
                'po_mode' => 'generated',
                'po_journey_status' => 'placed',
                'order_category' => 'new',
            ];
            if (in_array('is_legacy', $orderColumns, true)) {
                $payload['is_legacy'] = 1;
            }

            if ($planId > 0 && in_array('plan_id', $orderColumns, true)) {
                $payload['plan_id'] = $planId;
            }
            if ($amountColumn !== null && in_array($amountColumn, $orderColumns, true)) {
                $payload[$amountColumn] = $amount;
            }
            if (in_array('total_amount', $orderColumns, true)) {
                $payload['total_amount'] = $amount;
            }
            if (in_array('subtotal', $orderColumns, true)) {
                $payload['subtotal'] = $amount;
            }
            if (in_array('duration', $orderColumns, true)) {
                $payload['duration'] = '12 month';
            }
            if (in_array('subscription_start_date', $orderColumns, true)) {
                $payload['subscription_start_date'] = $validated['subscription_start_date'];
            }
            if (in_array('subscription_end_date', $orderColumns, true)) {
                $payload['subscription_end_date'] = $validated['subscription_end_date'];
            }
            $aadhaarDigits = preg_replace('/\D/', '', (string) ($validated['aadhaar_number'] ?? '')) ?? '';
            if ($aadhaarDigits !== '' && in_array('aadhaar_number', $orderColumns, true)) {
                $payload['aadhaar_number'] = $aadhaarDigits;
            }
            if (in_array('created_at', $orderColumns, true)) {
                $payload['created_at'] = now();
            }
            if (in_array('updated_at', $orderColumns, true)) {
                $payload['updated_at'] = now();
            }

            if (in_array('order_no', $orderColumns, true) || in_array('sr_no', $orderColumns, true)) {
                $next = $this->nextOrderIdentifiers();
                if (in_array('order_no', $orderColumns, true)) {
                    $payload['order_no'] = $next['order_no'];
                }
                if (in_array('sr_no', $orderColumns, true)) {
                    $payload['sr_no'] = $next['sr_no'];
                }
            }

            $orderId = (int) $this->orders->newQuery()->insertGetId(
                array_intersect_key($payload, array_flip($orderColumns))
            );

            $this->seedLegacyOrderItems($orderId, $planLines);
            $this->seedB2bOrderCustomerSnapshot($orderId, $amount);
            $this->syncB2bOrderWalletLedger($orderId, $payload);


            if ($aadhaarDigits !== '') {
                app(PartnerAadhaarVerificationService::class)->saveLegacyManualForOrder(
                    $partnerId,
                    $customerId,
                    $orderId,
                    $aadhaarDigits
                );
            }

            if ($aadhaarDigits !== '') {

                $poCols = $this->orderPoDetailColumns();
                $payload = [];

                if (in_array('aadhaar_masked', $poCols, true)) {
                    $payload['aadhaar_masked'] = strlen($aadhaarDigits) === 12
                        ? 'XXXXXXXX' . substr($aadhaarDigits, -4)
                        : $aadhaarDigits;
                }

                if (in_array('aadhar_verified', $poCols, true)) {
                    $payload['aadhar_verified'] = 1;
                }

                if (in_array('journey_status', $poCols, true)) {
                    $payload['journey_status'] = 'placed';
                }

                if (in_array('aadhar_verified_at', $poCols, true)) {
                    $payload['aadhar_verified_at'] = now();
                }

                if (in_array('updated_at', $poCols, true)) {
                    $payload['updated_at'] = now();
                }

                // Check existing row
                $exists = $this->orderPoDetails->newQuery()
                    ->where('order_id', $orderId)
                    ->exists();


                if ($exists) {

                    $this->orderPoDetails->newQuery()
                        ->where('order_id', $orderId)
                        ->update($payload);
                } else {

                    $insert = array_merge([
                        'order_id' => $orderId
                    ], $payload);

                    if (in_array('created_at', $poCols, true)) {
                        $insert['created_at'] = now();
                    }

                    $this->orderPoDetails->newQuery()->insert($insert);
                }
            }
            $this->ensureGeneratedPoForOrder($orderId);
            $this->persistOrderPoPdfToStorage($orderId);
            if (method_exists($this, 'markInvoiceStatusPendingForOrder')) {
                $this->markInvoiceStatusPendingForOrder($orderId);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withInput()->with('error', 'Legacy order could not be saved: ' . $e->getMessage());
        }

        return redirect()
            ->route('superadmin.orders.details', ['id' => $orderId])
            ->with('success', 'Legacy order saved. Purchase order generated. No wallet deduction or customer notifications were sent.');
    }

    /**
     * @return list<array{plan_id: int, quantity: int, unit_price: float}>
     */
    private function normalizeLegacyPlanLines(Request $request): array
    {
        $raw = $request->input('plans', []);
        if (!is_array($raw)) {
            return [];
        }

        $lines = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $planId = (int) ($row['plan_id'] ?? 0);
            $qty = (int) ($row['quantity'] ?? 0);
            $unitPrice = round((float) ($row['unit_price'] ?? 0), 2);
            if ($planId <= 0 || $qty < 1 || $unitPrice < 0) {
                continue;
            }
            $lines[] = [
                'plan_id' => $planId,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
            ];
        }

        return $lines;
    }

    /**
     * @param  list<array{plan_id: int, quantity: int, unit_price: float}>  $planLines
     */
    private function seedLegacyOrderItems(int $orderId, array $planLines): void
    {
        if ($orderId <= 0 || $planLines === []) {
            return;
        }

        $this->orderItems->newQuery()->where('order_id', $orderId)->delete();

        foreach ($planLines as $line) {
            $planId = (int) $line['plan_id'];
            $qty = (int) $line['quantity'];
            $unitPrice = (float) $line['unit_price'];
            $lineTotal = round($unitPrice * $qty, 2);

            $planName = 'Plan';
            if (true) {
                $planName = (string) ($this->plans->newQuery()->where('id', $planId)->value('name') ?? 'Plan');
            }

            $orderItem = $this->orderItems->newQuery()->create([
                'order_id' => $orderId,
                'plan_id' => $planId,
                'item_name' => $planName,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'total_amount' => $lineTotal,
            ]);
            $subscriptionPrefix = strtoupper(trim((string) (
                $this->adminSettings->newQuery()->orderBy('id')->value('subscription_prefix') ?? 'SUB'
            ))) ?: 'SUB';
            $orderItem->subscription_id = $subscriptionPrefix . '-' . str_pad((string) $orderItem->id, 6, '0', STR_PAD_LEFT);
            $orderItem->save();
        }
    }

    private function providerHasVariants(int $providerId): bool
    {
        if ($providerId <= 0) {
            return false;
        }

        $provider = $this->providers->newQuery()->where('id', $providerId)->first();

        return $provider && (($provider->has_variant ?? 'no') === 'yes');
    }

    /**
     * @return list<array{id: int, label: string, legacy_plan_name: string, variant_id: int|null}>
     */
    private function buildLegacyManualPlanOptions(int $providerId): array
    {
        if ($providerId <= 0) {
            return [];
        }

        $plans = $this->getPlansForProviderVariant($providerId, 0);
        $options = [];
        foreach ($plans as $plan) {
            $planId = (int) ($plan->id ?? 0);
            if ($planId <= 0) {
                continue;
            }
            $label = $this->legacyPlanDisplayLabel($planId, $plan);
            $options[] = [
                'id' => $planId,
                'label' => $label,
                'legacy_plan_name' => $label,
                'variant_id' => true
                    ? (int) ($this->plans->newQuery()->where('id', $planId)->value('variant_id') ?: 0) ?: null
                    : null,
            ];
        }

        return $options;
    }

    private function legacyPlanDisplayLabel(int $planId, ?object $planRow = null): string
    {
        if ($planId <= 0) {
            return '';
        }

        $plan = $planRow ?: $this->plans->newQuery()->where('id', $planId)->first();
        if (!$plan) {
            return '';
        }

        $nameCol = 'name';
        $planName = trim((string) ($plan->{$nameCol} ?? ''));
        $variantId = (int) ($plan->variant_id ?? 0);
        $variantPath = $this->resolveVariantPathLabel($variantId);

        if ($variantPath !== '' && $planName !== '') {
            return $variantPath . ' — ' . $planName;
        }

        return $variantPath !== '' ? $variantPath : $planName;
    }

    private function resolveVariantPathLabel(int $variantId): string
    {
        if ($variantId <= 0) {
            return '';
        }

        $variant = $this->variants->newQuery()->where('id', $variantId)->first();
        if (!$variant) {
            return '';
        }

        $parts = [];
        $parentId = (int) ($variant->parent_variant_id ?? 0);
        if ($parentId > 0) {
            $parentName = $this->variants->newQuery()->where('id', $parentId)->value('name');
            if ($parentName) {
                $parts[] = trim((string) $parentName);
            }
        }
        $parts[] = trim((string) ($variant->name ?? ''));

        return implode(' › ', array_filter($parts));
    }

    /** @return list<string> */
    private function orderColumns(): array
    {
        return [
            'id',
            'order_no',
            'sr_no',
            'user_id',
            'plan_id',
            'previous_plan_id',
            'parent_order_id',
            'parent_order_sub_id',
            'partner_id',
            'domain_name',
            'transfer_domain',
            'quantity',
            'price',
            'duration',
            'subscription_start_date',
            'subscription_end_date',
            'renewal_at',
            'promo_code',
            'discount_amount',
            'pro_rata_adjustment',
            'apply_gst',
            'gst_rate',
            'gst_amount',
            'tax_amount',
            'total_amount',
            'subtotal',
            'credit_used',
            'payment_mode',
            'bank_name',
            'cheque_ref_no',
            'upi_transaction_id',
            'order_note',
            'razorpay_order_id',
            'razorpay_payment_id',
            'razorpay_signature',
            'payment_method',
            'status',
            'is_legacy',
            'order_type',
            'order_category',
            'aadhaar_verification_id',
            'source_cart_id',
            'po_document_url',
            'created_at',
            'updated_at',
        ];
    }

    /** @return list<string> */
    private function customerColumns(): array
    {
        return [
            'id',
            'partner_id',
            'name',
            'email',
            'mobile',
            'company_name',
            'company_address',
            'gstin',
            'country',
            'state',
            'city',
            'zip_code',
            'postcode',
            'pincode',
            'status',
            'created_at',
            'updated_at',
        ];
    }

    /** @return list<string> */
    private function userColumns(): array
    {
        return [
            'id',
            'name',
            'email',
            'mobile',
            'company_name',
            'company_address',
            'gstin',
            'country',
            'state',
            'city',
            'postcode',
            'created_at',
            'updated_at',
        ];
    }

    /** @return list<string> */
    private function partnerColumns(): array
    {
        return [
            'id',
            'name',
            'email',
            'mobile',
            'aadhaar_mandatory',
            'wallet_balance',
            'status',
            'created_at',
            'updated_at',
        ];
    }

    /** @return list<string> */
    private function planColumns(): array
    {
        return [
            'id',
            'provider_id',
            'variant_id',
            'name',
            'title',
            'plan_name',
            'price',
            'actual_price',
            'billing_cycle',
            'customer_limit',
            'status',
            'is_active',
            'created_at',
            'updated_at',
        ];
    }

    private function planB2bPriceSql(): string
    {
        $columns = $this->planColumns();
        if (in_array('price', $columns, true) && in_array('actual_price', $columns, true)) {
            return 'COALESCE(NULLIF(price, 0), NULLIF(actual_price, 0), 0)';
        }
        if (in_array('price', $columns, true)) {
            return 'COALESCE(price, 0)';
        }
        if (in_array('actual_price', $columns, true)) {
            return 'COALESCE(actual_price, 0)';
        }

        return '0';
    }

    private function resolvePlanB2bPriceFromRow(object $row): float
    {
        foreach (['b2b_price', 'price'] as $key) {
            if (isset($row->{$key}) && is_numeric($row->{$key}) && (float) $row->{$key} > 0) {
                return (float) $row->{$key};
            }
        }
        if (isset($row->actual_price) && is_numeric($row->actual_price) && (float) $row->actual_price > 0) {
            return (float) $row->actual_price;
        }

        return 0.0;
    }

    /** @return list<string> */
    private function orderPoDetailColumns(): array
    {
        return [
            'id',
            'order_id',
            'po_mode',
            'po_number',
            'po_date',
            'po_amount',
            'po_document_url',
            'journey_status',
            'aadhar_verified',
            'aadhar_verified_at',
            'status',
            'final_po_link',
            'created_at',
            'updated_at',
        ];
    }

    /** @return list<string> */
    private function orderPoSubColumns(): array
    {
        return ['id', 'order_id', 'plan_id', 'item_name', 'quantity', 'unit_price', 'total_amount', 'created_at', 'updated_at'];
    }

    /** @return list<string> */
    private function orderItemColumns(): array
    {
        return [
            'id',
            'order_id',
            'plan_id',
            'item_name',
            'quantity',
            'unit_price',
            'discount_amount',
            'tax_rate',
            'tax_amount',
            'total_amount',
            'status',
            'previous_order_sub_id',
            'subscription_start_date',
            'subscription_end_date',
            'created_at',
            'updated_at',
        ];
    }

    /** @return list<string> */
    private function invoiceMstColumns(): array
    {
        return [
            'id',
            'bill_no_full',
            'bill_date',
            'cust_id',
            'net_amt',
            'status',
            'taxable_total',
            'cgst_total',
            'sgst_total',
            'igst_total',
            'tax_type',
            'action_type',
            'partner_id',
            'order_id',
            'created_at',
            'updated_at',
        ];
    }

    /** @return list<string> */
    private function invoiceSubColumns(): array
    {
        return [
            'id',
            'invoice_mst_id',
            'invoice_no',
            'hsn_sac',
            'qty',
            'cnt_rate',
            'taxable_amt',
            'tax_type',
            'cgst',
            'sgst',
            'igst',
            'nettotal',
            'created_at',
            'updated_at',
        ];
    }
    public function orderPoPdf(Request $request, int $orderId)
    {
        $download = $request->boolean('download');

        return $this->streamGeneratedPoPdfForOrder($orderId, null, $download);
    }

    public function po_details($pid)
    {
        $poDetail = $this->orderPoDetails->newQuery()->where('id', $pid)->first();
        if (!$poDetail) {
            abort(404, 'PO Detail not found.');
        }

        $orderId = (int) ($poDetail->order_id ?? 0);
        if ($orderId <= 0) {
            abort(404, 'Order not found for PO.');
        }

        return $this->streamGeneratedPoPdfForOrder($orderId, 'PO-' . $pid);
    }

    private function streamGeneratedPoPdfForOrder(int $orderId, ?string $fallbackPoNumber = null, bool $download = false)
    {
        if (!$this->orders->newQuery()->whereKey($orderId)->exists()) {
            abort(404, 'Order not found.');
        }

        $poNumber = $this->resolveStoredPoNumber($orderId, $fallbackPoNumber);
        $respondWithPdf = function (string $binary) use ($poNumber, $download) {
            return $download
                ? $this->downloadPdfBinary($binary, 'po_details_' . $poNumber)
                : $this->streamPdfBinary($binary, 'po_details_' . $poNumber);
        };

        $storedBinary = $this->readStoredOrderPoPdfBinary($orderId);

        try {
            $viewData = $this->buildGeneratedPoViewData($orderId);
            if ($viewData === null) {
                abort(404, 'Could not build PO data for this order.');
            }

            $viewData['for_pdf'] = true;
            $html = view('superadmin.po_details_pdf', $viewData)->render();
            $binary = $this->renderHtmlToPdfBinary($this->prepareInvoiceHtmlForPdf($html));
            if ($binary !== null && $binary !== '') {
                return $respondWithPdf($binary);
            }
        } catch (\Throwable $e) {
            Log::error('streamGeneratedPoPdfForOrder render failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }

        if ($storedBinary !== null && $storedBinary !== '') {
            return $respondWithPdf($storedBinary);
        }

        $redirectUrl = $this->resolveStoredOrderPoPdfUrl($orderId);
        if ($redirectUrl !== '') {
            return redirect()->away($redirectUrl);
        }

        abort(500, 'Could not generate PO PDF.');
    }

    private function resolveStoredPoNumber(int $orderId, ?string $fallbackPoNumber = null): string
    {
        $poRow = $this->orderPoDetails->newQuery()->where('order_id', $orderId)->first();
        $poNumber = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($poRow->po_number ?? ''));
        if ($poNumber === '') {
            $poNumber = $fallbackPoNumber ?: ('PO-' . $orderId);
        }

        return $poNumber;
    }

    /**
     * Enrollment label for invoices — derived only from orders.transfer_domain.
     *
     * @return array{transfer_domain: string, enrollment_type: string}
     */
    private function resolveOrderEnrollmentMeta(object $order): array
    {
        $transferDomain = strtolower(trim((string) ($order->transfer_domain ?? '')));
        if (in_array($transferDomain, ['1', 'true'], true)) {
            $transferDomain = 'yes';
        } elseif (in_array($transferDomain, ['0', 'false'], true)) {
            $transferDomain = 'no';
        }

        return [
            'transfer_domain' => $transferDomain,
            'enrollment_type' => $transferDomain === 'yes' ? 'Transfer Service' : 'New Service',
        ];
    }

    
    private function resolveInvoiceProRataAdjustment(object $order, array $items = [], float $gstTotal = 0.0): float
    {
        $orderId = (int) ($order->id ?? 0);
        if ($orderId <= 0) {
            return 0.0;
        }

        $category = strtolower(trim((string) ($order->order_category ?? '')));
        $isPlanChange = in_array($category, ['upgrade', 'upgraded', 'downgrade', 'downgraded'], true)
            || (int) ($order->parent_order_id ?? 0) > 0
            || (int) ($order->previous_plan_id ?? 0) > 0;

        $ordersConn = $this->orders->getConnectionName();

        // 1) Fresh read from orders table (source of truth for invoice).
        try {
            $orderSelect = ['id', 'discount_amount', 'subtotal', 'total_amount', 'gst_amount', 'tax_amount', 'order_category', 'parent_order_id', 'previous_plan_id', 'source_cart_id'];
            if (Schema::connection($ordersConn)->hasColumn('orders', 'pro_rata_adjustment')) {
                $orderSelect[] = 'pro_rata_adjustment';
            }
            $fresh = $this->orders->newQuery()->whereKey($orderId)->first($orderSelect);
            if ($fresh) {
                $fromOrderProRata = max(0.0, round((float) ($fresh->pro_rata_adjustment ?? 0), 2));
                if ($fromOrderProRata > 0) {
                    return $fromOrderProRata;
                }

                $freshCategory = strtolower(trim((string) ($fresh->order_category ?? $category)));
                $freshIsPlanChange = in_array($freshCategory, ['upgrade', 'upgraded', 'downgrade', 'downgraded'], true)
                    || (int) ($fresh->parent_order_id ?? 0) > 0
                    || (int) ($fresh->previous_plan_id ?? 0) > 0;
                if ($freshIsPlanChange) {
                    $fromOrderDiscount = max(0.0, round((float) ($fresh->discount_amount ?? 0), 2));
                    if ($fromOrderDiscount > 0) {
                        return $fromOrderDiscount;
                    }
                }

                // Keep fresh totals for derivation below.
                $order = $fresh;
                $isPlanChange = $freshIsPlanChange || $isPlanChange;
                if ($gstTotal <= 0) {
                    $gstTotal = (float) ($fresh->gst_amount ?? $fresh->tax_amount ?? 0);
                }
            }
        } catch (\Throwable $e) {
            // continue with in-memory order
        }

        $direct = max(0.0, round((float) ($order->pro_rata_adjustment ?? 0), 2));
        if ($direct > 0) {
            return $direct;
        }

        $itemsConn = $this->orderItems->getConnectionName();

        try {
            if (Schema::connection($itemsConn)->hasColumn('order_items', 'prorated_credit')) {
                $fromItems = round((float) $this->orderItems->newQuery()
                    ->where('order_id', $orderId)
                    ->sum('prorated_credit'), 2);
                if ($fromItems > 0) {
                    return $fromItems;
                }
            }
        } catch (\Throwable $e) {
            // continue
        }

        try {
            if ($isPlanChange && Schema::connection($itemsConn)->hasColumn('order_items', 'discount_amount')) {
                $fromItemDiscount = round((float) $this->orderItems->newQuery()
                    ->where('order_id', $orderId)
                    ->sum('discount_amount'), 2);
                if ($fromItemDiscount > 0) {
                    return $fromItemDiscount;
                }
            }
        } catch (\Throwable $e) {
            // continue
        }

        $cartId = (int) ($order->source_cart_id ?? $order->cart_id ?? 0);
        if ($cartId > 0) {
            try {
                $cartProRata = round((float) (Cart::query()->whereKey($cartId)->value('pro_rata_adjustment') ?? 0), 2);
                if ($cartProRata > 0) {
                    return $cartProRata;
                }
            } catch (\Throwable $e) {
                // continue
            }
        }

        if ($isPlanChange) {
            $fromDiscount = max(0.0, round((float) ($order->discount_amount ?? 0), 2));
            if ($fromDiscount > 0) {
                return $fromDiscount;
            }
        }

        // Derive: line gross − taxable (upgrade totals already include remaining credit).
        $linesGross = 0.0;
        foreach ($items as $item) {
            $linesGross += (float) ($item['total'] ?? 0);
        }
        if ($linesGross <= 0) {
            try {
                $linesGross = round((float) $this->orderItems->newQuery()
                    ->where('order_id', $orderId)
                    ->sum('total_amount'), 2);
            } catch (\Throwable $e) {
                $linesGross = 0.0;
            }
        }

        if ($linesGross > 0 && $isPlanChange) {
            $orderTotal = round((float) ($order->total_amount ?? 0), 2);
            if ($orderTotal <= 0) {
                $orderTotal = round((float) ($order->resolved_amount ?? 0), 2);
            }
            if ($gstTotal <= 0) {
                $gstTotal = (float) ($order->gst_amount ?? $order->tax_amount ?? 0);
            }
            $taxable = max(0.0, round($orderTotal - $gstTotal, 2));
            $derivedFromTotal = max(0.0, round($linesGross - $taxable, 2));

            $orderSubtotal = round((float) ($order->subtotal ?? 0), 2);
            $derivedFromSubtotal = $orderSubtotal > 0
                ? max(0.0, round($linesGross - $orderSubtotal, 2))
                : 0.0;

            $derived = max($derivedFromTotal, $derivedFromSubtotal);
            // Ignore tiny rounding noise.
            if ($derived >= 1.0) {
                return $derived;
            }
        }

        return 0.0;
    }

    private function resolveStoredOrderPoPdfUrl(int $orderId): string
    {
        $urls = [];
        $poRow = $this->orderPoDetails->newQuery()->where('order_id', $orderId)->first();
        if ($poRow && !empty($poRow->po_document_url)) {
            $urls[] = trim((string) $poRow->po_document_url);
        }
        $orderUrl = trim((string) ($this->orders->newQuery()->whereKey($orderId)->value('po_document_url') ?? ''));
        if ($orderUrl !== '') {
            $urls[] = $orderUrl;
        }

        foreach (array_values(array_unique(array_filter($urls))) as $url) {
            if (preg_match('/\.pdf(?:\?.*)?$/i', $url)) {
                return $url;
            }
        }

        return '';
    }

    private function readStoredOrderPoPdfBinary(int $orderId): ?string
    {
        $url = $this->resolveStoredOrderPoPdfUrl($orderId);
        if ($url === '') {
            return null;
        }

        return $this->readPdfBinaryFromPublicStorageUrl($url);
    }

    private function readPdfBinaryFromPublicStorageUrl(string $url): ?string
    {
        $paths = [];
        $storagePath = $this->resolvePublicStoragePathFromUrl($url);
        if ($storagePath) {
            $paths[] = $storagePath;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && $path !== '') {
            foreach (['/public/storage/', '/storage/'] as $marker) {
                $idx = strpos($path, $marker);
                if ($idx === false) {
                    continue;
                }
                $relative = ltrim(substr($path, $idx + strlen($marker)), '/');
                if ($relative !== '') {
                    $paths[] = public_path('storage/' . $relative);
                    $paths[] = $this->webPublicStoragePath($relative);
                }
                break;
            }
        }

        foreach (array_values(array_unique(array_filter($paths))) as $candidate) {
            if (!is_file($candidate) || filesize($candidate) <= 0) {
                continue;
            }
            $raw = @file_get_contents($candidate);
            if ($raw !== false && $raw !== '' && str_starts_with($raw, '%PDF')) {
                return $raw;
            }
        }

        return null;
    }

    private function renderInvoicePdfBinary(array $invoiceData): ?string
    {
        $html = view('superadmin.orders.invoice', $invoiceData)->render();

        return $this->renderHtmlToPdfBinary($this->prepareInvoiceHtmlForPdf($html));
    }

    /**
     * Embed local and remote images as data URIs for reliable PDF rendering.
     */
    private function prepareInvoiceHtmlForPdf(string $html): string
    {
        return (string) preg_replace_callback(
            '/<img\b[^>]*\bsrc=(["\'])([^"\']+)\1/i',
            function (array $matches): string {
                $src = $matches[2];
                if (str_starts_with($src, 'data:')) {
                    return $matches[0];
                }

                $dataUri = $this->pathOrUrlToDataUri($src);
                if (!$dataUri) {
                    return $matches[0];
                }

                return str_replace($src, $dataUri, $matches[0]);
            },
            $html
        );
    }

    private function pathOrUrlToDataUri(string $src): ?string
    {
        $src = trim($src);
        if ($src === '' || str_starts_with($src, 'data:')) {
            return $src !== '' ? $src : null;
        }

        $path = null;
        if (preg_match('#^https?://#i', $src)) {
            $path = $this->resolveCompanyLogoAbsolutePath($src);
            if (!$path || !is_file($path)) {
                try {
                    $response = Http::timeout(10)->withoutVerifying()->get($src);
                } catch (\Throwable $e) {
                    return null;
                }

                if (!$response->successful() || $response->body() === '') {
                    return null;
                }

                $mime = $response->header('Content-Type') ?: 'image/png';
                $mime = trim(explode(';', $mime)[0]);

                return 'data:' . $mime . ';base64,' . base64_encode($response->body());
            }
        } elseif (is_file($src)) {
            $path = $src;
        } else {
            $path = $this->resolveCompanyLogoAbsolutePath($src);
        }

        if (!$path || !is_file($path)) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
    }

    private function streamPdfBinary(string $binary, string $filename)
    {
        $safeName = $this->safeDownloadFilename($filename);
        if ($safeName === '') {
            $safeName = 'invoice';
        }

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $safeName . '.pdf"',
        ]);
    }

    private function downloadPdfBinary(string $binary, string $filename)
    {
        $safeName = $this->safeDownloadFilename($filename);
        if ($safeName === '') {
            $safeName = 'invoice';
        }

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $safeName . '.pdf"',
        ]);
    }

    private function safeDownloadFilename(string $filename): string
    {
        $filename = preg_replace('/[\/\\\\:*?"<>|]/', '-', $filename);
        $filename = trim((string) preg_replace('/\s+/', ' ', $filename));

        return $filename;
    }

    public function generateUpgradeOrder(Request $request)
    {
        $v = $request->validate([
            'partner_id' => ['required', 'integer', Rule::exists(Partner::class, 'id')],
            'customer_id' => ['required', 'integer', Rule::exists(Customer::class, 'id')],
            'order_id' => ['nullable', 'integer', Rule::exists(Order::class, 'id')],
            'order_sub_id' => ['nullable', 'integer'],
            'plan_id' => ['required', 'integer', Rule::exists(Plan::class, 'id')],
            'licenses' => ['required', 'integer', 'min:1'],
        ]);

        $partnerId = (int) $v['partner_id'];
        $customerId = (int) $v['customer_id'];
        $requestedOrderId = (int) ($v['order_id'] ?? 0);
        $planId = (int) $v['plan_id'];
        $licenses = max(1, (int) $v['licenses']);
        $orderSubId = (int) ($v['order_sub_id'] ?? 0);
        $upgrade = app(B2bSubscriptionUpgradeService::class);

        try {
            $baseOrder = null;
            if ($requestedOrderId > 0) {
                $baseOrder = Order::query()
                    ->whereKey($requestedOrderId)
                    ->where('partner_id', $partnerId)
                    ->where('user_id', $customerId)
                    ->whereRaw("LOWER(COALESCE(order_type, '')) = 'b2b'")
                    ->first();
                if (!$baseOrder) {
                    throw ValidationException::withMessages([
                        'order_id' => ['Selected subscription was not found for this partner/customer.'],
                    ]);
                }
            }

            $subId = $baseOrder
                ? (int) $baseOrder->id
                : (int) $upgrade->resolveSubscriptionId($partnerId, null, $customerId, null);
            $baseOrder = $baseOrder ?: Order::query()->find($subId);
            if (!$baseOrder) {
                throw ValidationException::withMessages([
                    'order_id' => ['Subscription not found.'],
                ]);
            }

            if (!in_array(strtolower(trim((string) ($baseOrder->status ?? 'pending'))), $this->orderSuccessfulStatuses(), true)) {
                throw ValidationException::withMessages([
                    'order_id' => ['Only active subscriptions can be upgraded.'],
                ]);
            }

            $activePlanLineCount = (int) $this->orderItems->newQuery()
                ->where('order_id', $subId)
                ->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhereRaw("LOWER(TRIM(COALESCE(status, ''))) NOT IN ('upgraded', 'downgraded', 'delete', 'deleted')");
                })
                ->count();
            if ($activePlanLineCount > 1 && $orderSubId <= 0) {
                throw ValidationException::withMessages([
                    'order_id' => ['Only single-plan subscriptions can be upgraded. Pass order_sub_id for multi-plan upgrades.'],
                ]);
            }

            $sourceItem = $orderSubId > 0
                ? $this->orderItems->newQuery()->whereKey($orderSubId)->where('order_id', $subId)->first()
                : null;
            $currentPlanId = $sourceItem
                ? (int) ($sourceItem->plan_id ?? 0)
                : (int) ($baseOrder->plan_id ?? 0);
            $currentPlan = $currentPlanId > 0 ? Plan::query()->find($currentPlanId) : null;
            $newPlan = Plan::query()->findOrFail($planId);
            // Same plan = license-only / partial upgrade — skip tier (sr_no) check.
            if ($currentPlan && (int) $newPlan->id !== $currentPlanId) {
                $currentSr = is_numeric($currentPlan->sr_no ?? null) ? (int) $currentPlan->sr_no : 0;
                $newSr = is_numeric($newPlan->sr_no ?? null) ? (int) $newPlan->sr_no : 0;
                if ($currentSr > 0 && $newSr > 0 && $newSr <= $currentSr) {
                    throw ValidationException::withMessages([
                        'plan_id' => ['Selected plan is not eligible for upgrade.'],
                    ]);
                }
            } elseif ($currentPlan && (int) $newPlan->id === $currentPlanId) {
                $currentLicenses = max(1, (int) ($sourceItem->quantity ?? $baseOrder->quantity ?? 1));
                if ((int) $licenses <= $currentLicenses) {
                    throw ValidationException::withMessages([
                        'licenses' => ['Partial upgrade requires more licenses than the current count (' . $currentLicenses . ').'],
                    ]);
                }
            }

            $calc = app(RenewalService::class)->upgradeOrderCalculation(array_filter([
                'partner_id' => $partnerId,
                'order_id' => $subId,
                'plan_id' => $planId,
                'licenses' => $licenses,
                'customer_id' => $customerId,
                'order_item_id' => $orderSubId > 0 ? $orderSubId : null,
            ]));

            $aadhaar = '';
            if (($vid = (int) $request->input('aadhaar_verification_id', 0)) > 0) {
                $aadhaar = preg_replace('/\D/', '', (string) $this->partnerAadhaarVerifications->newQuery()->whereKey($vid)->value('aadhaar_number')) ?? '';
            }
            if (strlen($aadhaar) !== 12) {
                $verified = $this->partnerAadhaarVerifications->newQuery()
                    ->where('partner_id', $partnerId)
                    ->whereIn('status', ['verified', 'order_linked'])
                    ->whereNotNull('verified_at')
                    ->orderByDesc('id')
                    ->value('aadhaar_number');
                $aadhaar = preg_replace('/\D/', '', (string) $verified) ?? '';
                $aadhaar = strlen($aadhaar) === 12 ? $aadhaar : '';
            }

            $result = $upgrade->upgrade([
                'partner_id' => $partnerId,
                'subscription_id' => $subId,
                'plan_id' => $planId,
                'quantity' => $licenses,
                'aadhaar_number' => $aadhaar,
                'subtotal' => $calc['subtotal'],
                'pro_rata_adjustment' => $calc['pro_rata_adjustment'],
                'taxable_amount' => $calc['taxable_amount'],
                'gst_amount' => $calc['gst_amount'],
                'payable_amount' => $calc['payable_amount'],
                'skip_credit_check' => true,
                'skip_invoice' => true,
                'force_pending' => true,
                'order_sub_id' => $orderSubId > 0 ? $orderSubId : null,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: 'Upgrade validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate upgrade order: ' . $e->getMessage(),
            ], 500);
        }

        $order = $result['order'];
        $orderId = (int) ($order->id ?? 0);
        $baseOrderId = (int) ($order->parent_order_id ?? $subId);
        $summary = $result['summary'] ?? $calc;
        $poLink = $poNumber = null;
        $poError = null;

        if ($orderId > 0 && $baseOrderId > 0) {
            try {
                $baseOrder = Order::query()->find($baseOrderId);
                $newPlan = Plan::query()->find($planId);
                if ($baseOrder && $newPlan && !(int) $this->orderItems->newQuery()->where('order_id', $orderId)->value('id')) {
                    $sourceSubId = (int) ($order->parent_order_sub_id ?? $orderSubId);
                    $sourcePlanId = (int) ($order->previous_plan_id ?? ($sourceItem->plan_id ?? $baseOrder->plan_id ?? 0));
                    $pr = round((float) ($summary['prorated_credit'] ?? $summary['pro_rata_adjustment'] ?? 0), 2);
                    $gst = round((float) ($summary['gst_amount'] ?? 0), 2);
                    $tax = round((float) ($summary['taxable_amount'] ?? 0), 2);
                    $subStart = $order->subscription_start_date ?? $baseOrder->subscription_start_date ?? null;
                    $subEnd = $order->subscription_end_date ?? $baseOrder->subscription_end_date ?? null;
                    $upgradeItem = [
                        'order_id' => $orderId,
                        'plan_id' => (int) $newPlan->id,
                        'item_name' => (string) ($newPlan->name ?? 'Plan'),
                        'quantity' => $licenses,
                        'unit_price' => round((float) ($summary['new_unit_price'] ?? $newPlan->price ?? 0), 2),
                        'discount_amount' => $pr,
                        'tax_rate' => (float) ($baseOrder->gst_rate ?? RenewalService::GST_RATE_PERCENT),
                        'tax_amount' => $gst,
                        'total_amount' => round((float) ($summary['payable_amount'] ?? $tax + $gst), 2),
                        'subscription_start_date' => $subStart ? Carbon::parse((string) $subStart)->toDateString() : null,
                        'subscription_end_date' => $subEnd ? Carbon::parse((string) $subEnd)->toDateString() : null,
                        'remaining_days' => (int) ($summary['remaining_days'] ?? 0),
                        'total_plan_days' => (int) ($summary['total_plan_days'] ?? 365),
                        'prorated_credit' => $pr,
                        'meta' => [
                            'upgrade_from_order_id' => $baseOrderId,
                            'old_plan_id' => $sourcePlanId,
                            'previous_order_sub_id' => $sourceSubId > 0 ? $sourceSubId : null,
                            'old_unit_price' => round((float) ($summary['old_unit_price'] ?? 0), 2),
                            'new_plan_remaining_total' => round((float) ($summary['subtotal'] ?? 0), 2),
                        ],
                    ];
                    $upgradeItem = OrderItem::withUpgradeDefaults($upgradeItem, $sourceSubId > 0 ? $sourceSubId : null);
                    $orderItem = $this->orderItems->newQuery()->create($upgradeItem);
                    $subscriptionPrefix = strtoupper(trim((string) (
                        $this->adminSettings->newQuery()->orderBy('id')->value('subscription_prefix') ?? 'SUB'
                    ))) ?: 'SUB';
                    $orderItem->subscription_id = $subscriptionPrefix . '-' . str_pad((string) $orderItem->id, 6, '0', STR_PAD_LEFT);
                    $orderItem->save();
                    if ($sourceSubId > 0 && Schema::hasColumn('orders', 'parent_order_sub_id')) {
                        $this->orders->newQuery()->whereKey($orderId)->update([
                            'parent_order_sub_id' => $sourceSubId,
                            'previous_plan_id' => $sourcePlanId > 0 ? $sourcePlanId : DB::raw('previous_plan_id'),
                            'updated_at' => now(),
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('billing_upgrade_item_failed', ['order_id' => $orderId, 'error' => $e->getMessage()]);
            }
        }

        if ($orderId > 0) {
            try {
                $this->orders->newQuery()->whereKey($orderId)->update(['status' => 'pending']);
                $this->ensureGeneratedPoForOrder($orderId);
                $stored = $this->persistOrderPoPdfToStorage($orderId, false);
                $poRow = $this->orderPoDetails->newQuery()->where('order_id', $orderId)->first();
                $poLink = $stored['public_url'] ?? $poRow?->po_document_url;
                $poNumber = $stored['po_number'] ?? $poRow?->po_number;
                if (!$poLink) {
                    $poError = 'PO link could not be created.';
                }
            } catch (\Throwable $e) {
                $poError = $e->getMessage();
                Log::error('billing_upgrade_po_failed', ['order_id' => $orderId, 'error' => $poError]);
            }
        }

        if ($orderId > 0 && ($orderRow = $this->orders->newQuery()->find($orderId))) {
            try {
                $prefs = NotificationPreference::forPartner($partnerId);
                $amount = $this->resolveOrderGrandTotal($orderRow);
                $orderNo = (string) ($orderRow->order_no ?? $orderId);
                $mobile = trim((string) ($this->partners->newQuery()->whereKey($partnerId)->value('mobile') ?? ''));
                if ($prefs->isEnabled('subscription', 'sms') && $mobile !== '') {
                    $planName = (string) ($this->plans->newQuery()->whereKey((int) $orderRow->plan_id)->value('name') ?? 'Upgraded plan');
                    app(SmsService::class)->sendUpgradePlatform($mobile, $planName, $amount);
                }
                if ($prefs->isEnabled('subscription', 'whatsapp') && $mobile !== '' && class_exists(\App\Services\WhatsAppService::class)) {
                    $planName = (string) ($this->plans->newQuery()->whereKey((int) $orderRow->plan_id)->value('name') ?? 'Upgraded plan');
                    $endDate = !empty($orderRow->subscription_end_date)
                        ? Carbon::parse((string) $orderRow->subscription_end_date)->format('d M, Y')
                        : 'N/A';
                    app(\App\Services\WhatsAppService::class)->sendOrderNotification(
                        $mobile,
                        $orderNo,
                        \App\Services\WhatsAppService::CATEGORY_UPGRADE,
                        [
                            'amount' => $amount,
                            'plan_name' => $planName,
                            'end_date' => $endDate,
                        ]
                    );
                }
                if ($prefs->isEnabled('subscription', 'email')) {
                    $partner = $this->partners->newQuery()->find($partnerId);
                    $email = trim((string) ($partner->email ?? ''));
                    if ($partner && $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $planName = (string) ($orderRow->plan_name ?: $this->plans->newQuery()->whereKey((int) $orderRow->plan_id)->value('name') ?? 'Subscription');
                        $validity = !empty($orderRow->subscription_end_date)
                            ? Carbon::parse((string) $orderRow->subscription_end_date)->format('d M, Y')
                            : Carbon::now()->addYear()->format('d M, Y');
                        Mail::to($email)->send(new PlanUpgradedMail(
                            $planName,
                            $amount,
                            $validity,
                            trim((string) ($partner->name ?? $partner->company_name ?? 'Partner')),
                            $orderNo,
                            'upgrade'
                        ));
                    }
                }
                // WhatsApp is also sent on generate (and again on activation if still pending).
            } catch (\Throwable $e) {
                Log::warning('billing_upgrade_notify_failed', ['order_id' => $orderId, 'error' => $e->getMessage()]);
            }
        }

        $plan = Plan::query()->find($order->plan_id);
        $baseOrderAfter = $baseOrderId > 0 ? Order::query()->find($baseOrderId) : null;
        $signRequired = strtolower(trim((string) ($this->partners->newQuery()->whereKey($partnerId)->value('aadhaar_mandatory') ?? 'no'))) === 'yes';
        $data = [
            'order_id' => $orderId,
            'order_no' => (string) ($order->order_no ?? $orderId),
            'status' => 'pending',
            'sign_required' => $signRequired ? 'yes' : 'no',
            'po_link' => $poLink,
            'po_number' => $poNumber,
            'plan_id' => (int) $order->plan_id,
            'plan_name' => (string) ($plan->name ?? $order->plan_name ?? ''),
            'upgraded_licenses' => $licenses,
            'remaining_licenses' => (int) ($baseOrderAfter->quantity ?? 0),
            'base_order_id' => $baseOrderId,
            'base_order_category' => (string) ($baseOrderAfter->order_category ?? ''),
            'summary' => $result['summary'] ?? [],
        ];
        if ($poError) {
            $data['po_error'] = $poError;
        }

        return response()->json([
            'success' => true,
            'message' => $poError
                ? 'Upgrade order created but PO link could not be generated.'
                : 'Upgrade order generated successfully.',
            'data' => $data,
        ], 201);
    }
}
