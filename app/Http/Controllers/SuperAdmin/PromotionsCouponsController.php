<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Promocode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PromotionsCouponsController extends Controller
{
    private const BILLING_CONNECTION = 'pgsql_billing';

    public function index()
    {
        $coupons = Promocode::on(self::BILLING_CONNECTION)->latest()->get();
        $today = now()->startOfDay();
        $redemptionCounts = $this->redemptionCountsByCode();

        $totalCoupons = $coupons->count();
        $activeCoupons = $coupons->where('status', true)->count();
        $expiredCoupons = $coupons->filter(function ($coupon) use ($today) {
            return $coupon->end_date && $coupon->end_date->lt($today);
        })->count();
        $upcomingCoupons = $coupons->filter(function ($coupon) use ($today) {
            return $coupon->start_date && $coupon->start_date->gt($today);
        })->count();
        $totalRedemptions = array_sum($redemptionCounts);

        return view('superadmin.promostions_coupens.index', compact(
            'coupons',
            'totalCoupons',
            'activeCoupons',
            'expiredCoupons',
            'upcomingCoupons',
            'totalRedemptions',
            'redemptionCounts'
        ));
    }

    public function promotion_Dashboard()
    {
        $coupons = Promocode::on(self::BILLING_CONNECTION)->get();
        $redemptionCounts = $this->redemptionCountsByCode();

        return view('superadmin.promotion_dashboard.index', [
            'pageTitle' => 'Promotion Dashboard',
            'totalCoupons' => $coupons->count(),
            'activeCoupons' => $coupons->where('status', true)->count(),
            'expiredCoupons' => $coupons->filter(fn ($c) => $c->end_date && $c->end_date->lt(now()->startOfDay()))->count(),
            'upcomingCoupons' => $coupons->filter(fn ($c) => $c->start_date && $c->start_date->gt(now()->startOfDay()))->count(),
            'totalRedemptions' => array_sum($redemptionCounts),
        ]);
    }

    public function list()
    {
        $coupons = Promocode::on(self::BILLING_CONNECTION)->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $coupons,
        ]);
    }

    public function save(Request $request)
    {
        $id = $request->input('id');

        if ($request->has('discount_percent') && $request->input('discount_percent') !== '') {
            $request->merge([
                'discount_percent' => (int) round((float) $request->input('discount_percent')),
            ]);
        }

        $validator = Validator::make($request->all(), [
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique(Promocode::class, 'code')->ignore($id),
            ],
            'discount_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'promo_status' => ['nullable', 'in:0,1'],
        ], [
            'code.required' => 'Coupon code is required',
            'code.unique' => 'This coupon code already exists',
            'code.regex' => 'Code can only contain letters, numbers, underscores and hyphens',
            'code.max' => 'Code cannot exceed 50 characters',
            'discount_percent.required' => 'Discount percentage is required',
            'discount_percent.integer' => 'Discount must be a whole number',
            'discount_percent.min' => 'Discount must be at least 1%',
            'discount_percent.max' => 'Discount cannot exceed 100%',
            'promo_status.in' => 'Invalid status value',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $code = strtoupper(trim((string) $request->code));

        try {
            $data = [
                'code' => $code,
                'discount_percent' => (int) $request->discount_percent,
                'status' => $request->filled('promo_status') ? (int) $request->promo_status : 1,
            ];

            if ($id && !empty($id)) {
                $coupon = Promocode::on(self::BILLING_CONNECTION)->find($id);
                if (!$coupon) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Coupon not found.',
                    ], 404);
                }
                $coupon->update($data);
                $message = 'Coupon updated successfully';
            } else {
                // PG sequences drift after manual/imported rows → duplicate promocodes_pkey (e.g. id=2).
                $this->syncPromocodesIdSequence();
                try {
                    $coupon = Promocode::on(self::BILLING_CONNECTION)->create($data);
                } catch (\Illuminate\Database\QueryException $e) {
                    if ($this->isPromocodePkConflict($e)) {
                        $this->syncPromocodesIdSequence();
                        $coupon = Promocode::on(self::BILLING_CONNECTION)->create($data);
                    } else {
                        throw $e;
                    }
                }
                $message = 'Coupon created successfully';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $coupon,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save coupon: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Align promocodes.id sequence with MAX(id) so inserts do not reuse existing PKs.
     */
    private function syncPromocodesIdSequence(): void
    {
        $connection = self::BILLING_CONNECTION;
        if (DB::connection($connection)->getDriverName() !== 'pgsql') {
            return;
        }
        if (!Schema::connection($connection)->hasTable('promocodes')) {
            return;
        }

        DB::connection($connection)->statement(
            "SELECT setval(
                pg_get_serial_sequence('promocodes', 'id'),
                COALESCE((SELECT MAX(id) FROM promocodes), 0)
            )"
        );
    }

    private function isPromocodePkConflict(\Illuminate\Database\QueryException $e): bool
    {
        $msg = $e->getMessage();

        return str_contains($msg, 'promocodes_pkey')
            || (str_contains($msg, '23505') && str_contains($msg, 'promocodes'));
    }

    public function edit($id)
    {
        try {
            $coupon = Promocode::on(self::BILLING_CONNECTION)->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => array_merge($coupon->toArray(), [
                    'discount_percent' => (int) round((float) $coupon->discount_percent),
                    'status' => (int) (bool) $coupon->status,
                ]),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon not found',
            ], 404);
        }
    }

    public function destroy($id)
    {
        try {
            $coupon = Promocode::on(self::BILLING_CONNECTION)->findOrFail($id);
            $coupon->delete();

            return response()->json([
                'success' => true,
                'message' => 'Coupon deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete coupon: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function changeStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'in:0,1'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status value',
            ], 422);
        }

        try {
            $coupon = Promocode::on(self::BILLING_CONNECTION)->findOrFail($id);
            $newStatus = (int) $request->status;

            $coupon->update(['status' => $newStatus]);

            $statusText = $newStatus === 1 ? 'activated' : 'deactivated';

            return response()->json([
                'success' => true,
                'message' => "Coupon {$statusText} successfully",
                'data' => $coupon,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to change status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @return array<string, int>
     */
    private function redemptionCountsByCode(): array
    {
        $connection = self::BILLING_CONNECTION;

        if (!Schema::connection($connection)->hasTable('orders')
            || !Schema::connection($connection)->hasColumn('orders', 'promo_code')) {
            return [];
        }

        return DB::connection($connection)
            ->table('orders')
            ->whereNotNull('promo_code')
            ->where('promo_code', '!=', '')
            ->select('promo_code', DB::raw('COUNT(*) as used_count'))
            ->groupBy('promo_code')
            ->pluck('used_count', 'promo_code')
            ->map(fn ($count) => (int) $count)
            ->all();
    }
}
