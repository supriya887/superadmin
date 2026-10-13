<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest as PurchaseRequestModel;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use App\Services\PurchaseRequestService;
class PurchaseRequest extends Controller
{
    
    public function index()
    {
        return view('superadmin.purchase_requests.index');
    }

    public function approve(Request $request, int $id)
    {
        $purchaseRequest = PurchaseRequestModel::findOrFail($id);

        if ($purchaseRequest->status !== 'pending') {
            return back()->with('warning', 'This request is no longer pending.');
        }
        
        $adminNotes = trim((string) $request->input('admin_notes', ''));
        // print_r($purchaseRequest);
        // die;
        try {
            $service = app(PurchaseRequestService::class);
            $service->approvePurchaseRequest($id, $adminNotes);
        } catch (\Throwable $e) {
            Log::error('purchase_request_approve_failed', [
                'request_id' => $id,
                'error'      => $e->getMessage(),
            ]);
            return back()->with('error', 'Failed to approve request: ' . $e->getMessage());
        }

        return back()->with('success', 'Purchase request approved successfully. Email sent to partner with checkout link.');
    }

    public function reject(Request $request, int $id)
    {
        $purchaseRequest = PurchaseRequestModel::findOrFail($id);

        if ($purchaseRequest->status !== 'pending') {
            return back()->with('warning', 'This request is no longer pending.');
        }

        $adminNotes = trim((string) $request->input('admin_notes', ''));

        $purchaseRequest->update([
            'status'      => PurchaseRequestModel::STATUS_REJECTED,
            'admin_notes' => $adminNotes ?: null,
        ]);

        return back()->with('success', 'Purchase request rejected successfully.');
    }

    
    public function purchaserequestslisttable(Request $request)
    {
        $draw = (int) $request->input('draw', 1);
        $start = max((int) $request->input('start', 0), 0);
        $length = max((int) $request->input('length', 10), 1);
        $tab = in_array($request->input('tab'), ['pending', 'approved', 'all'], true)
            ? $request->input('tab')
            : 'all';

        $columns = [
            0 => 'created_at',
            1 => 'partner.name',
            2 => 'partner.company_name',
            3 => 'partner.email',
            4 => 'partner.mobile',
            5 => 'status',
        ];

        // Always default to DESC order for the table
        $orderColIdx = (int) data_get($request->input('order', []), '0.column', 0);
        $orderBy = $columns[$orderColIdx] ?? 'created_at';
        $orderDir = 'desc';

        $base = PurchaseRequestModel::query()
            ->with('partner')
            ->when($tab === 'pending', function ($query) {
                $query->where('status', 'pending');
            })
            ->when($tab === 'approved', function ($query) {
                $query->where('status', 'approved');
            });

        $filtered = clone $base;

        $search = trim((string) data_get($request->input('search', []), 'value', ''));
        if ($search !== '') {
            $filtered->where(function ($q) use ($search) {
                $q->whereHas('partner', function ($sp) use ($search) {
                    $sp->where('name', 'like', '%'.$search.'%')
                        ->orWhere('company_name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('mobile', 'like', '%'.$search.'%');
                })
                ->orWhere('status', 'like', '%'.$search.'%');
            });
        }

        $recordsTotal = $base->count();
        $recordsFiltered = $filtered->count();

        if (in_array($orderBy, ['partner.name', 'partner.company_name', 'partner.email', 'partner.mobile'])) {
            $partnerField = str_replace('partner.', '', $orderBy);
            $filtered = $filtered->join('partners', 'purchase_requests.partner_id', '=', 'partners.id')
                ->orderBy('partners.'.$partnerField, $orderDir)
                ->select('purchase_requests.*');
        } else {
            $filtered = $filtered->orderBy($orderBy, $orderDir);
        }

        $rows = $filtered
            ->offset($start)
            ->limit($length)
            ->get();

        $data = $rows->map(function ($r) {
            $partner = $r->partner;
            $statusVal = strtolower(trim((string) $r->status));
            $label = 'Unknown';
            $cls = 'bg-secondary';
            if (in_array($statusVal, ['pending', '2'], true)) {
                $label = 'Pending';
                $cls = 'bg-warning text-dark';
            } elseif (in_array($statusVal, ['approved', '1'], true)) {
                $label = 'Approved';
                $cls = 'bg-success';
            } elseif (in_array($statusVal, ['rejected', '3'], true)) {
                $label = 'Rejected';
                $cls = 'bg-danger';
            }

            return [
                'created_at' => $r->created_at ? $r->created_at->format('d-m-Y H:i') : '-',
                'partner' => [
                    'name' => $partner->name ?? '-',
                    'company_name' => $partner->company_name ?? '—',
                    'email' => $partner->email ?? '—',
                    'mobile' => $partner->mobile ?? '—',
                ],
                'status' => '<span class="badge ' . $cls . '">' . e($label) . '</span>',
                'action' => '<a href="' . url('superadmin/purchase-requests-show', $r->id) . '" class="btn btn-primary btn-sm">View</a>',
            ];
        })->values();

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function purchase_requests_show(int $id)
    {
        $purchaseRequest = PurchaseRequestModel::with(['customer', 'plan', 'cart', 'order', 'order.plan'])->findOrFail($id);
        return view('superadmin.purchase_requests.show', compact('purchaseRequest'));
    }
}
