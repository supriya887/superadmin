<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\LicenseDecreaseRequest;
use App\Models\Order;
use App\Models\Partner as PartnerRecord;
use App\Services\LicenseDecreaseRequestService;
use Illuminate\Http\Request;

class LicenseDecreaseRequestController extends Controller
{
    public function index(Request $request)
    {
        if (!LicenseDecreaseRequestService::tableReady()) {
            return view('superadmin.license_decrease_requests.index', [
                'rows' => collect(),
                'status' => $request->get('status', 'pending'),
                'table_missing' => true,
            ]);
        }

        $status = (string) $request->get('status', 'pending');
        $query = LicenseDecreaseRequest::query();

        if ($status === 'pending') {
            $query->whereRaw("LOWER(TRIM(COALESCE(status, 'pending'))) = 'pending'");
        } elseif ($status !== 'all') {
            $query->whereRaw('LOWER(TRIM(status)) = ?', [strtolower($status)]);
        }

        $rows = $query->orderByDesc('id')->get();

        $partnerIds = $rows->pluck('partner_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $partnersById = $partnerIds->isEmpty()
            ? collect()
            : PartnerRecord::query()->whereIn('id', $partnerIds)->get()->keyBy('id');

        $orderIds = $rows->pluck('order_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $ordersById = $orderIds->isEmpty()
            ? collect()
            : Order::query()->whereIn('id', $orderIds)->get(['id', 'order_no', 'domain_name', 'plan_id'])->keyBy('id');

        $rows->each(function ($row) use ($partnersById, $ordersById) {
            $row->setRelation('partner', $partnersById->get((int) $row->partner_id));
            $row->setRelation('order', $ordersById->get((int) $row->order_id));
        });

        return view('superadmin.license_decrease_requests.index', [
            'rows' => $rows,
            'status' => $status,
            'table_missing' => false,
        ]);
    }

    public function approve(Request $request, int $id)
    {
        if (!LicenseDecreaseRequestService::tableReady()) {
            return back()->with('error', 'License decrease requests table is not installed.');
        }

        $request->validate(['admin_note' => 'nullable|string|max:500']);

        $row = LicenseDecreaseRequest::query()->findOrFail($id);
        try {
            app(LicenseDecreaseRequestService::class)->approve(
                $row,
                $request->input('admin_note'),
                auth()->id()
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('warning', $e->getMessage());
        }

        return back()->with('success', 'License decrease request approved. Partner notified by email and in-app notification.');
    }

    public function reject(Request $request, int $id)
    {
        return $this->decide($request, $id, LicenseDecreaseRequest::STATUS_REJECTED, 'License decrease request rejected. Partner notified by email and in-app notification.');
    }

    public function revert(Request $request, int $id)
    {
        return $this->decide($request, $id, LicenseDecreaseRequest::STATUS_REVERTED, 'License decrease request reverted. Partner notified by email and in-app notification.');
    }

    private function decide(Request $request, int $id, string $status, string $successMessage)
    {
        if (!LicenseDecreaseRequestService::tableReady()) {
            return back()->with('error', 'License decrease requests table is not installed.');
        }

        $request->validate(['admin_note' => 'nullable|string|max:500']);
        $row = LicenseDecreaseRequest::query()->findOrFail($id);

        try {
            app(LicenseDecreaseRequestService::class)->rejectOrRevert(
                $row,
                $status,
                $request->input('admin_note'),
                auth()->id()
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('warning', $e->getMessage());
        }

        return back()->with('success', $successMessage);
    }
}
