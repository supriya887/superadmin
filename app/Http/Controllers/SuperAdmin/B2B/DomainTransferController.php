<?php

namespace App\Http\Controllers\SuperAdmin\B2B;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Yajra\DataTables\Facades\DataTables;

use App\Models\Order;
use App\Models\Plan;
use App\Models\Provider;
use App\Models\Partner;

class DomainTransferController extends Controller
{
    /**
     * LIST PAGE
     */
    public function index()
    {
        $tableExists = Schema::hasTable('orders');

        $rows = collect();

        if ($tableExists) {
            $rows = Order::with([
                    'customer:id,name',
                    'partner:id,name',
                    'plan:id,name'
                ])
                ->where('transfer_domain', 'yes') // ONLY YES
                ->latest()
                ->get();
        }

        return view('superadmin.domain_transfer_orders.index', [
            'requests'    => $rows,
            'tableExists' => $tableExists,
            'typeOptions' => ['b2b', 'b2c'],
        ]);
    }

    /**
     * DATATABLE AJAX
     */
    public function listTable(Request $request)
    {
        $query = Order::with([
                'customer:id,name',
                'partner:id,name',
                'plan:id,name'
            ])
            ->where('transfer_domain', 'yes'); // ONLY YES RECORDS

        // FILTERS
        if ($request->order_type) {
            $query->where('order_type', $request->order_type);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->domain_name) {
            $query->where('domain_name', 'like', "%{$request->domain_name}%");
        }

        if ($request->order_category) {
            $query->where('order_category', $request->order_category);
        }

        return DataTables::of($query)

            ->addColumn('customer_name', fn($row) => optional($row->customer)->name ?? '-')
            ->addColumn('partner_name', fn($row) => optional($row->partner)->name ?? '-')
            ->addColumn('plan_name', fn($row) => optional($row->plan)->name ?? '-')

            ->editColumn('total_amount', fn($row) => '₹' . ($row->total_amount ?? 0))
            ->editColumn('credit_used', fn($row) => $row->credit_used ?? 0)

            ->editColumn('status', function ($row) {
                $status = strtolower($row->status ?? 'pending');

                $badge = match ($status) {
                    'approved' => 'success',
                    'rejected' => 'danger',
                    default => 'warning'
                };

                return '<span class="badge bg-'.$badge.'">'.ucfirst($status).'</span>';
            })

            ->editColumn('created_at', function ($row) {
                return $row->created_at
                    ? $row->created_at->format('d M Y h:i A')
                    : '-';
            })

            ->addColumn('action', function ($row) {

                if (($row->status ?? 'pending') === 'pending') {

                    return '
                    <div class="d-flex gap-1 justify-content-center">

                        <form method="POST" action="'.route('superadmin.domain_transfer_orders.update_status', $row->id).'">
                            '.csrf_field().'
                            <input type="hidden" name="status" value="approved">
                            <button class="btn btn-success btn-sm">Approve</button>
                        </form>

                        <form method="POST" action="'.route('superadmin.domain_transfer_orders.update_status', $row->id).'">
                            '.csrf_field().'
                            <input type="hidden" name="status" value="rejected">
                            <button class="btn btn-danger btn-sm">Reject</button>
                        </form>

                    </div>';
                }

                return '<span class="text-muted">No Action</span>';
            })

            ->rawColumns(['status', 'action'])
            ->make(true);
    }

    /**
     * STATUS UPDATE
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected'
        ]);

        Order::where('id', $id)->update([
            'status' => $request->status
        ]);

        return back()->with('success', 'Status updated successfully');
    }
}