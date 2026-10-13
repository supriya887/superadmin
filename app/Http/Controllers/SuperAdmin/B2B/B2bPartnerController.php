<?php

namespace App\Http\Controllers\SuperAdmin\B2B;

use App\Http\Controllers\Controller;
use App\Models\AccountMaster;
use App\Models\Customer;
use App\Models\Ledger;
use App\Models\Order;
use App\Models\OrderPoDetail;
use App\Models\Partner;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Database\Query\JoinClause; 
use Illuminate\Support\Facades\Mail;
use App\Mail\PartnerApproved;
use App\Mail\PartnerRejected;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use App\Models\AdminSetting;
use Illuminate\Support\Str;
use App\Services\PartnerCreditService;
use Illuminate\Validation\ValidationException;
use App\Models\NotificationPreference;
use Illuminate\Http\Request;

class B2bPartnerController extends Controller
{
    protected $request;
    protected $partner;
    public function __construct(Partner $partner, Request $request)
    {
        $this->partner = $partner;
        $this->request = $request;
    }
    public function partner_list()
    {
        
        return view('superadmin.b2b_partners.index');
    }

    public function partner_list_table()
    {   
        $draw            = $this->request->get('draw');
        $start           = $this->request->get("start");
        $rowPerPage      = $this->request->get("length");
        $orderArray      = $this->request->get('order');
        $columnNameArray = $this->request->get('columns');

        // Custom column search parameters (from custom search fields)
        $search_name         = trim((string) $this->request->get('search_name', ''));
        $search_email        = trim((string) $this->request->get('search_email', ''));
        $search_mobile       = trim((string) $this->request->get('search_mobile', ''));
        $search_company_name = trim((string) $this->request->get('search_company_name', ''));
        $search_status       = trim((string) $this->request->get('search_status', ''));
        $searchPayload       = $this->request->get('search');
        $globalSearchValue   = trim(is_array($searchPayload) ? (string) ($searchPayload['value'] ?? '') : '');

        $hasTextSearch = $search_name !== ''
            || $search_email !== ''
            || $search_mobile !== ''
            || $search_company_name !== ''
            || $globalSearchValue !== '';

        if (!empty($orderArray)) {
            $columnIndex     = $orderArray[0]['column'];
            $columnSortOrder = $orderArray[0]['dir'] == 'asc' ? 'asc' : 'desc';
            $columnName      = $columnNameArray[$columnIndex]['data'] ?? ($columnNameArray[$columnIndex]['name'] ?? 'id');
            if (!in_array($columnName, ['id', 'name', 'email', 'mobile', 'company_name', 'status'], true)) {
                $columnName = 'id';
            }
        } else {
            $columnIndex     = 0;
            $columnSortOrder = 'desc';
            $columnName      = 'id';
        }

        // Base query for partners
        $normalizedStatus = $search_status;
        if ($normalizedStatus === 'approve') {
            $normalizedStatus = 'approved';
        }

        $baseQuery = Partner::query();
        if ($normalizedStatus !== '') {
            $baseQuery->where('status', $normalizedStatus);
        } elseif (!$hasTextSearch) {
            $baseQuery->where('status', '!=', 'inactive');
        }

        $query = clone $baseQuery;

        // Apply custom search on fields (from filter boxes)
        if ($search_name !== '') {
            $this->applyPartnerColumnSearch($query, 'name', $search_name);
        }
        if ($search_email !== '') {
            $this->applyPartnerColumnSearch($query, 'email', $search_email);
        }
        if ($search_mobile !== '') {
            $this->applyPartnerColumnSearch($query, 'mobile', $search_mobile);
        }
        if ($search_company_name !== '') {
            $this->applyPartnerColumnSearch($query, 'company_name', $search_company_name);
        }

        // Apply DataTables global search box, if used
        if ($globalSearchValue !== '') {
            $query->where(function ($q) use ($globalSearchValue) {
                $this->applyPartnerColumnSearch($q, 'name', $globalSearchValue);
                $q->orWhere(function ($inner) use ($globalSearchValue) {
                    $this->applyPartnerColumnSearch($inner, 'email', $globalSearchValue);
                });
                $q->orWhere(function ($inner) use ($globalSearchValue) {
                    $this->applyPartnerColumnSearch($inner, 'mobile', $globalSearchValue);
                });
                $q->orWhere(function ($inner) use ($globalSearchValue) {
                    $this->applyPartnerColumnSearch($inner, 'company_name', $globalSearchValue);
                });
            });
        }

        $totalData = (clone $baseQuery)->count();
        $totalFiltered = (clone $query)->count();

        $arrData = $query
        ->orderBy($columnName, $columnSortOrder)
        ->offset($start)
        ->limit($rowPerPage)
        ->get();

        // Shape the data for the DataTable frontend, including the "action" column, adjust as necessary
        $displayName = function_exists('format_display_name')
            ? fn (?string $value, string $fallback = '—') => format_display_name($value, $fallback)
            : fn (?string $value, string $fallback = '—') => trim((string) $value) !== '' ? trim((string) $value) : $fallback;

        $data = [];
        foreach ($arrData as $index => $partner) {

            if ($partner->status == 'approved') {
                $status = '<span class="badge bg-success">Approved</span>';
            } elseif ($partner->status == 'pending') {
                $status = '<span class="badge bg-warning text-dark">Pending</span>';
            } elseif ($partner->status == 'rejected') {
                $status = '<span class="badge bg-danger">Rejected</span>';
            } elseif (strtolower((string) $partner->status) === 'active') {
                $status = '<span class="badge bg-success">Active</span>';
            } elseif (strtolower((string) $partner->status) === 'inactive') {
                $status = '<span class="badge bg-secondary">Inactive</span>';
            } else {
                $status = '<span class="badge bg-secondary">' . e($displayName((string) $partner->status, 'Unknown')) . '</span>';
            }

            $discountType = strtolower(trim((string) ($partner->partner_discount_type ?? '')));
            $discountValue = $discountType === 'distributor'
                ? (float) ($partner->distributor_discount ?? 0)
                : (float) ($partner->discount_percent ?? 0);
            $discountLabel = \App\Support\PartnerDiscountType::label($discountType);
            $discount = $discountLabel
                ? '<div><span class="badge bg-info text-dark">' . e($discountLabel) . '</span>' . (
                    $discountValue > 0
                        ? ' <span class="ms-1 text-nowrap">' . number_format($discountValue, 2) . '%</span>'
                        : ''
                ) . '</div>'
                : '<span class="text-muted">—</span>';

            $action = '<div class="d-flex justify-content-center gap-1">
                                    <a class="btn btn-light btn-icon btn-sm rounded-circle" title="Edit" href="' . url('superadmin/b2b-partner-edit/' . $partner->id) . '"><i class="ti ti-edit fs-lg"></i></a>
                                    ';
                                    // <a class="btn btn-light btn-icon btn-sm rounded-circle delete" title="Delete" id="' . $partner->id . '" href="javascript:void(0);"><i class="ti ti-trash fs-lg"></i></a>
            $isApproved = $partner->status == 'approved' || $partner->status == 1 || $partner->status === '1';
            if ($isApproved) {
                $action .= '<a class="btn btn-light btn-icon btn-sm rounded-circle" title="View Partner credit Details" href="' . route('superadmin.b2b_partners.credit_history', ['id' => $partner->id]) . '"><i class="ti ti-eye fs-lg"></i></a>';
                $action .= '<a class="btn btn-light btn-icon btn-sm rounded-circle" title="Partner Customers" href="' . url('superadmin/b2b-partner-customers/' . Crypt::encrypt($partner->id)) . '"><i class="ti ti-users fs-lg"></i></a>';
            } else {
                // $action .= '<span class="btn btn-light btn-icon btn-sm rounded-circle disabled" title="Approve partner to add customers"><i class="ti ti-users fs-lg text-muted"></i></span>';
            }
            $action .= '</div>';
            $data[] = [
                'sr_no'         => (int) $start + $index + 1,
                'id'            => $partner->id,
                'name'          => $displayName($partner->name),
                'email'         => strtolower(trim((string) ($partner->email ?? ''))) ?: '—',
                'mobile'        => trim((string) ($partner->mobile ?? '')) ?: '—',
                'company_name'  => $displayName($partner->company_name),
                'discount'      => $discount,
                'status'        => $status,
                'action'        => $action,
            ];
        }

        $response = [
            'draw' => intval($draw),
            'recordsTotal' => $totalData,
            'recordsFiltered' => $totalFiltered,
            'data' => $data,
        ];

        return response()->json($response);
    }

    public function add_partner($id = null, $mode = null)
    {
        $partner = null;
        if ($id) {
            $partner = Partner::find($id);
        }
        $isApproveScreen = ($mode === 'approve');
        $readOnly = $isApproveScreen;
        $creditUsed = $partner ? $this->creditUsedForPartner($partner->id) : 0.0;
        $creditAvailable = $partner ? max(0, (float) ($partner->credit_limit ?? 0) - $creditUsed) : 0.0;

        return view('superadmin.b2b_partners.form', compact('partner', 'readOnly', 'isApproveScreen', 'creditUsed', 'creditAvailable'));
    }

    public function approve_partner($id)
    {
        return $this->add_partner($id, 'approve');
    }

    public function partner_validation(Request $request)
    {
        $partnerId = $request->input('id');

        if ($partnerId) {
            $rules = [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:pgsql_auth.partners,email,' . $partnerId,
                'mobile' => 'required|digits:10',
                'aadhaar_mandatory' => 'required|in:yes,no',
                'partner_discount_type' => 'required|in:market_value,distributor',
                'credit_limit' => 'required|numeric|min:0',
            ];
        } else {
            $rules = [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:pgsql_auth.partners,email',
                'mobile' => 'required|digits:10',
                'gstin' => 'required|string|max:15|unique:pgsql_auth.partners,gstin',
                'company_name' => 'required|string|max:255',
                'company_address' => 'required|string|max:500',
                'country' => 'required|string|max:255',
                'state' => 'required|string|max:255',
                'city' => 'nullable|string|max:255',
                'postcode' => 'required|string|max:10',
                'pan_no' => 'nullable|string|max:20',
                'legal_name' => 'nullable|string|max:255',
                'trade_name' => 'nullable|string|max:255',
                'address' => 'nullable|string|max:1000',
                'pincode' => 'nullable|string|max:20',
                'gst_status' => 'nullable|string|max:100',
                'last_updated' => 'nullable|date',
                'constitution' => 'nullable|string|max:255',
                'registration_date' => 'nullable|date',
                'nature_of_business' => 'nullable|string|max:2000',
                'credit_limit' => 'required|numeric|min:0',
                'status2' => 'nullable|in:0,1',
                'aadhaar_mandatory' => 'required|in:yes,no',
                'partner_discount_type' => 'required|in:market_value,distributor',
            ];
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'messages' => $validator->errors()->toArray()]);
        }

        DB::beginTransaction();
        try {
            if ($partnerId) {
                $partner = Partner::find($partnerId);
                if (!$partner) {
                    return response()->json(['error' => true, 'message' => 'Partner not found!'], 404);
                }

                $newCreditLimit = round((float) $request->input('credit_limit'), 2);
                $used = $this->creditUsedForPartner((int) $partner->id);
                if ($newCreditLimit + 0.005 < $used) {
                    return response()->json([
                        'error' => true,
                        'messages' => [
                            'credit_limit' => [
                                'Credit allocation cannot be less than consumed amount (₹' . number_format($used, 2) . ').',
                            ],
                        ],
                    ]);
                }

                $partner->name = format_display_name($request->name, '');
                $partner->email = strtolower(trim((string) $request->email));
                $partner->mobile = $request->mobile;
                $partner->aadhaar_mandatory = $request->aadhaar_mandatory === 'yes' ? 'yes' : 'no';
                $partnerSchema = \Illuminate\Support\Facades\Schema::connection($partner->getConnectionName());
                if ($partnerSchema->hasColumn('partners', 'partner_discount_type')) {
                    $partner->partner_discount_type = \App\Support\PartnerDiscountType::normalize($request->input('partner_discount_type'));
                }
                if ($partnerSchema->hasColumn('partners', 'credit_limit')) {
                    $oldLimit = round((float) ($partner->credit_limit ?? 0), 2);
                    $partner->credit_limit = $newCreditLimit;
                    $partner->save();
                    if (abs($newCreditLimit - $oldLimit) >= 0.01) {
                        try {
                            PartnerCreditService::recordCreditLimitLedgerDelta(
                                (int) $partner->id,
                                $oldLimit,
                                $newCreditLimit,
                                'Credit allocation updated from partner edit'
                            );
                        } catch (\Throwable $e) {
                            Log::warning('partner_edit_credit_limit_ledger_failed', [
                                'partner_id' => (int) $partner->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                } else {
                    $partner->save();
                }

                $this->syncPartnerOrderPoSignRequired((int) $partner->id, $partner->aadhaar_mandatory);

                $message = 'Partner updated successfully!';
            } else {
                // Partners added by super admin should be auto-approved.
                $partnerStatus = 'approved';

                $normalizeDate = function ($value) {
                    $value = trim((string) $value);
                    if ($value === '') {
                        return null;
                    }
                    try {
                        return \Carbon\Carbon::parse($value)->format('Y-m-d');
                    } catch (\Throwable $e) {
                        return null;
                    }
                };

                $natureOfBusiness = $request->nature_of_business;
                if (is_array($natureOfBusiness)) {
                    $natureOfBusiness = implode(', ', array_filter($natureOfBusiness));
                }

                $creditAllocation = round((float) $request->input('credit_limit'), 2);

                $createPayload = [
                    'name' => format_display_name($request->name, ''),
                    'email' => strtolower(trim((string) $request->email)),
                    'mobile' => $request->mobile,
                    'password' => Hash::make(Str::random(32)),
                    'gstin' => strtoupper((string) $request->gstin),
                    'pan_no' => strtoupper((string) $request->pan_no),
                    'company_name' => format_display_name($request->company_name, ''),
                    'company_address' => format_display_name($request->company_address, ''),
                    'country' => format_display_name($request->country, ''),
                    'state' => format_display_name($request->state, ''),
                    'city' => format_display_name($request->city, ''),
                    'postcode' => $request->postcode,
                    // Save GST-derived fields with sensible fallbacks.
                    'legal_name' => format_display_name($request->legal_name ?: $request->company_name, ''),
                    'trade_name' => format_display_name($request->trade_name ?: $request->company_name, ''),
                    'address' => format_display_name($request->address ?: $request->company_address, ''),
                    'pincode' => $request->pincode ?: $request->postcode,
                    'gst_status' => $request->gst_status,
                    'last_updated' => $normalizeDate($request->last_updated),
                    'constitution' => $request->constitution,
                    'registration_date' => $normalizeDate($request->registration_date),
                    'nature_of_business' => $natureOfBusiness,
                    'credit_limit' => $creditAllocation,
                    'wallet_balance' => 0,
                    'status' => $partnerStatus,
                    'aadhaar_mandatory' => $request->aadhaar_mandatory === 'yes' ? 'yes' : 'no',
                    'partner_discount_type' => \App\Support\PartnerDiscountType::normalize($request->input('partner_discount_type')),
                ];

                $createPayload['approveby'] = session('userId');
                $createPayload['approve_datetime'] = now();

                $partner = Partner::create($createPayload);

                try {
                    if ($creditAllocation > 0.005) {
                        PartnerCreditService::recordCreditLimitLedgerDelta(
                            (int) $partner->id,
                            0.0,
                            $creditAllocation,
                            'Initial credit allocation on partner create'
                        );
                    }
                } catch (\Throwable $e) {
                    Log::warning('partner_create_credit_limit_ledger_failed', [
                        'partner_id' => (int) $partner->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                $message = 'Partner created successfully!';
            }
            DB::commit();
            return response()->json(['success' => $message, 'redirect' => url('superadmin/b2b-partners-list')]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => true, 'message' => 'Failed to process partner: ' . $e->getMessage()], 500);
        }
    }

    public function walletStatement(Request $request, int $id)
    {
        $partner = Partner::findOrFail($id);
        $partnerAccount = AccountMaster::findOrCreate('Partner #' . ((int) $partner->id) . ' Wallet', 'Partner', (int) $partner->id);
        $partnerAccount->refresh();

        $fromDate = trim((string) $request->query('from_date', ''));
        $toDate = trim((string) $request->query('to_date', ''));

        // Perform a left join with orders table to get order_no if order_id exists
        $query = Ledger::query()
            ->where('account_master_id', $partnerAccount->id)
            ->leftJoin('orders', 'ledgers.order_id', '=', 'orders.id')
            ->select(
                'ledgers.*',
                'orders.order_no as order_no'
            )
            ->orderBy('ledgers.created_at')
            ->orderBy('ledgers.id');

        try {
            if ($fromDate !== '') {
                $query->where('ledgers.created_at', '>=', \Carbon\Carbon::parse($fromDate)->startOfDay());
            }
            if ($toDate !== '') {
                $query->where('ledgers.created_at', '<=', \Carbon\Carbon::parse($toDate)->endOfDay());
            }
        } catch (\Throwable $e) {
            // Ignore invalid date filter inputs.
        }

        $rows = $query->get();
        $runningBalance = 0.0;
        $statementRows = $rows->map(function ($row) use (&$runningBalance) {
            $type = strtolower((string) ($row->transaction_type ?? 'debit'));
            $amount = (float) ($row->amount ?? 0);
            if ($type === 'credit') {
                $runningBalance += $amount;
            } else {
                $runningBalance -= $amount;
            }
            return [
                'date' => $row->created_at,
                'type' => $type,
                'description' => (string) ($row->description ?? ''),
                'order_id' => $row->order_id,
                'order_no' => $row->order_no ?? null,
                'amount' => $amount,
                'closing_balance' => $runningBalance,
            ];
        });

        return view('superadmin.b2b_partners.wallet_statement', [
            'title' => 'Partner Wallet Statement',
            'partner' => $partner,
            'statementRows' => $statementRows,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'currentBalance' => (float) ($partnerAccount->current_balance ?? 0),
        ]);
    }

    public function update_status(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status2' => 'required|in:approved,rejected',
            'credit_limit' => 'required_if:status2,approved|nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'messages' => $validator->errors()->toArray()]);
        }

        DB::beginTransaction();
        try {
            $partner = Partner::find($id);
            if (!$partner) {
                return response()->json(['error' => true, 'message' => 'Partner not found!'], 404);
            }

            $newStatus = $request->input('status2');
            $partnerSchema = \Illuminate\Support\Facades\Schema::connection($partner->getConnectionName());

            $partner->status = $newStatus;

            if ($newStatus === 'approved' && $partnerSchema->hasColumn('partners', 'credit_limit')) {
                $newCreditLimit = round((float) $request->input('credit_limit', 0), 2);
                $used = $this->creditUsedForPartner((int) $partner->id);
                if ($newCreditLimit + 0.005 < $used) {
                    DB::rollBack();
                    return response()->json([
                        'error' => true,
                        'messages' => [
                            'credit_limit' => [
                                'Credit allocation cannot be less than consumed amount (₹' . number_format($used, 2) . ').',
                            ],
                        ],
                    ]);
                }

                $oldLimit = round((float) ($partner->credit_limit ?? 0), 2);
                $partner->credit_limit = $newCreditLimit;
                if ($partnerSchema->hasColumn('partners', 'wallet_balance')
                    && round((float) ($partner->wallet_balance ?? 0), 2) <= 0.005) {
                    $partner->wallet_balance = 0;
                }
                if ($partnerSchema->hasColumn('partners', 'approveby')) {
                    $partner->approveby = session('userId');
                }
                if ($partnerSchema->hasColumn('partners', 'approve_datetime')) {
                    $partner->approve_datetime = now();
                }
                $partner->save();

                if (abs($newCreditLimit - $oldLimit) >= 0.01) {
                    try {
                        PartnerCreditService::recordCreditLimitLedgerDelta(
                            (int) $partner->id,
                            $oldLimit,
                            $newCreditLimit,
                            'Credit allocation set on partner approval'
                        );
                    } catch (\Throwable $e) {
                        Log::warning('partner_approve_credit_limit_ledger_failed', [
                            'partner_id' => (int) $partner->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            } else {
                $partner->save();
            }

            // Send email based on status
            if ($newStatus === 'approved') {
                Log::info('Attempting to send Partner Approved email to: ' . $partner->email);
                Mail::to($partner->email)->send(new PartnerApproved($partner));
                $message = 'Partner approved successfully and email sent!';
            } elseif ($newStatus === 'rejected') {
                Log::info('Attempting to send Partner Rejected email to: ' . $partner->email);
                Mail::to($partner->email)->send(new PartnerRejected($partner, ''));
                $message = 'Partner rejected successfully and email sent!';
            } else {
                $message = 'Partner status updated successfully!';
            }

            DB::commit();
            return response()->json(['success' => $message, 'redirect' => url('superadmin/b2b-partners-list')]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => true, 'message' => 'Failed to update partner status: ' . $e->getMessage()], 500);
        }
    }

    public function delete_partner(Request $request)
    {
        $partnerId = $request->input('id');
        DB::beginTransaction();
        try {
            $partner = Partner::find($partnerId);
            if ($partner) {
                // Soft delete by changing status to inactive
                $partner->status = 'inactive';
                $partner->save();
                DB::commit();
                return response()->json(['success' => 'Partner soft-deleted successfully!']); 
            } else {
                return response()->json(['error' => 'Partner not found!'], 404);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => true, 'message' => 'Failed to delete partner: ' . $e->getMessage()], 500);
        }
    }

    public function getTransferCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'domain_name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'messages' => $validator->errors()->toArray()], 422);
        }

        try {
            $transferCodeSetting = AdminSetting::where('id', 1)->first();
            return response()->json([
                'success' => true,
                'transfer_code' => $transferCodeSetting->transfert_code,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching transfer code: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Failed to fetch transfer code.'], 500);
        }
    }

    public function creditHistory(int $id)
    {
        $partner = Partner::findOrFail($id);
        abort_unless(PartnerCreditService::partnerApprovedForCreditManagement($partner->status), 403);

        // Allocation model: no wallet reconcile on credit management page.
        $kpis = PartnerCreditService::getCreditManagementKpis($id);
        $creditAddHistory = PartnerCreditService::listPartnerCreditAllocationHistory($id);
        $trends = [
            'week' => PartnerCreditService::getCreditUsageTrend($id, 'week'),
            'month' => PartnerCreditService::getCreditUsageTrend($id, 'month'),
            'year' => PartnerCreditService::getCreditUsageTrend($id, 'year'),
        ];

        // Wallet add/deduct statement (ledger).
        // Credit history page previously showed only allocation changes; this adds the actual running ledger.
        $partnerAccount = AccountMaster::findOrCreate(
            'Partner #' . (int) $partner->id . ' Wallet',
            'Partner',
            (int) $partner->id
        );
        $partnerAccount->refresh();

        $rows = Ledger::query()
            ->where('account_master_id', $partnerAccount->id)
            ->leftJoin('orders', 'ledgers.order_id', '=', 'orders.id')
            ->select('ledgers.*', 'orders.order_no as order_no')
            ->orderBy('ledgers.created_at')
            ->orderBy('ledgers.id')
            ->limit(200)
            ->get();

        $runningBalance = 0.0;
        $statementRows = $rows->map(function ($row) use (&$runningBalance) {
            $type = strtolower((string) ($row->transaction_type ?? 'debit'));
            $amount = (float) ($row->amount ?? 0);

            if ($type === 'credit') {
                $runningBalance += $amount;
            } else {
                $runningBalance -= $amount;
            }

            return [
                'date' => $row->created_at,
                'type' => $type,
                'description' => (string) ($row->description ?? ''),
                'order_id' => $row->order_id,
                'order_no' => $row->order_no ?? null,
                'amount' => $amount,
                'closing_balance' => $runningBalance,
            ];
        });

        return view('superadmin.b2b_partners.credit_history', [
            'title' => 'Credit management — ' . ($partner->name ?? 'Partner #' . $id),
            'partner' => $partner,
            'kpis' => $kpis,
            'creditAddHistory' => $creditAddHistory,
            'trends' => $trends,
            'walletStatementRows' => $statementRows,
            'walletCurrentBalance' => (float) ($partnerAccount->current_balance ?? 0),
            'creditLimitReached' => PartnerCreditService::isCreditLimitFullyUsed($id),
        ]);
    }

    public function creditHistoryApply(Request $request, int $id)
    {
        $partner = Partner::findOrFail($id);
        $status = $partner->status;
        if (!($status === 'approved' || $status === 1 || $status === '1')) {
            abort(403);
        }

        $request->validate([
            'topup_amount' => 'required|numeric|min:0.01',
            'transaction_note' => 'required|string|max:2000',
        ]);

        $topup = round((float) $request->input('topup_amount'), 2);
        $adminId = session('userId') ? (int) session('userId') : null;
        $note = trim((string) $request->input('transaction_note'));

        try {
            PartnerCreditService::applyAdminCreditTopUpAndOptionalLimit(
                $id,
                $topup,
                null,
                $note,
                $adminId
            );
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['topup_amount' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Could not add credit: ' . $e->getMessage());
        }

        return redirect()
            ->route('superadmin.b2b_partners.credit_history', ['id' => $id])
            ->with('success', 'Credit allocation increased successfully.');
    }

    public function creditHistoryUpdateLimit(Request $request, int $id)
    {
        $partner = Partner::findOrFail($id);
        $status = $partner->status;
        if (!($status === 'approved' || $status === 1 || $status === '1')) {
            abort(403);
        }

        $request->validate([
            'credit_limit' => 'required|numeric|min:0',
            'limit_note' => 'required|string|max:2000',
        ]);

        $newLimit = round((float) $request->input('credit_limit'), 2);
        $note = trim((string) $request->input('limit_note'));

        try {
            PartnerCreditService::updatePartnerCreditLimit($id, $newLimit, $note);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['credit_limit' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Could not update credit allocation: ' . $e->getMessage());
        }

        return redirect()
            ->route('superadmin.b2b_partners.credit_history', ['id' => $id])
            ->with('success', 'Credit allocation updated successfully.');
    }

    public function creditHistoryUpdateDiscount(Request $request, int $id)
    {
        $partner = Partner::findOrFail($id);
        $status = $partner->status;
        if (!($status === 'approved' || $status === 1 || $status === '1')) {
            abort(403);
        }

        $request->validate([
            'partner_discount_type' => 'required|in:market_value,distributor',
        ]);

        $discountType = $request->input('partner_discount_type') === 'distributor' ? 'distributor' : 'market_value';
        $partnerSchema = \Illuminate\Support\Facades\Schema::connection($partner->getConnectionName());

        if (!$partnerSchema->hasColumn('partners', 'partner_discount_type')) {
            return back()->withInput()->with('error', 'Discount type is not configured on partners. Run the migration first.');
        }

        try {
            $partner->partner_discount_type = $discountType;
            $partner->save();
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Could not update discount type: ' . $e->getMessage());
        }

        return redirect()
            ->route('superadmin.b2b_partners.credit_history', ['id' => $id])
            ->with('success', 'Discount type updated successfully.');
    }

    private function creditUsedForPartner(int $partnerId): float
    {
        return (float) Order::query()
            ->where('partner_id', $partnerId)
            ->where(function ($q) {
                $q->where('order_type', 'b2b')->orWhere('order_type', 'B2B');
            })
            ->sum('credit_used');
    }

    private function creditDisplayHtml(Partner $partner): string
    {
        $limit = (float) ($partner->credit_limit ?? 0);
        $used = PartnerCreditService::summarizeUsedCredit((int) $partner->id);
        $available = max(0.0, round($limit - $used, 2));
        
        $html = '₹' . number_format($available, 2) . ' / ₹' . number_format($limit, 2);

        if ($limit > 0 && $available < 1000) {
            $html += ' <span class="badge bg-warning text-dark ms-1">Low</span>';
        }
        return $html;
    }

    public function notificationPreference($id)
    {
        $notificationPreference = NotificationPreference::firstOrCreate(
            ['partner_id' => $id],
            NotificationPreference::defaultsFor($id)
        );
    
        return view(
            'superadmin.b2b_partners.notification_preference',
            compact('notificationPreference')
        );
    }

    public function updateNotificationPreference(Request $request)
    {
        $request->validate([
            'partner_id' => 'required|integer',
            'column' => 'required|string',
            'value' => 'required|boolean',
        ]);
    
        $notificationPreference =
            NotificationPreference::forCustomer($request->partner_id);
    
        $notificationPreference->update([
            $request->column => $request->value
        ]);
    
        return response()->json([
            'success' => true,
            'message' => 'Notification preference updated successfully.'
        ]);
    }

    private function applyPartnerColumnSearch($query, string $column, string $value): void
    {
        $value = trim($value);
        if ($value === '') {
            return;
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
        $driver = $query->getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $query->where($column, 'ilike', '%' . $escaped . '%');
        } else {
            $query->where($column, 'like', '%' . $escaped . '%');
        }
    }

    private function syncPartnerOrderPoSignRequired(int $partnerId, string $aadhaarMandatory): void
    {
        if ($partnerId <= 0) {
            return;
        }

        $signRequired = strtolower(trim($aadhaarMandatory)) === 'yes' ? 'yes' : 'no';
        $customerIds = Customer::query()
            ->where('partner_id', $partnerId)
            ->pluck('id');

        $orderQuery = Order::query()->where('partner_id', $partnerId);
        if ($customerIds->isNotEmpty()) {
            $orderQuery->orWhereIn('user_id', $customerIds);
        }

        $orderIds = $orderQuery->pluck('id');
        if ($orderIds->isEmpty()) {
            return;
        }

        OrderPoDetail::query()
            ->whereIn('order_id', $orderIds)
            ->update([
                'sign_required' => $signRequired,
                'updated_at' => now(),
            ]);
    }

}
