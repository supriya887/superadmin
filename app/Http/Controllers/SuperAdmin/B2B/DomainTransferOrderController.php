<?php

namespace App\Http\Controllers\SuperAdmin\B2B;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DomainTransferOrderController  extends Controller
{
    public function index()
    {
        $rows = collect();
        $tableExists = Schema::hasTable('domain_transfer_requests');

        if ($tableExists) {
            $columns = Schema::getColumnListing('domain_transfer_requests');

            $domainCol = in_array('domain', $columns, true) ? 'domain' : (in_array('domain_name', $columns, true) ? 'domain_name' : null);
            $codeCol = in_array('transfer_code', $columns, true) ? 'transfer_code' : (in_array('auth_code', $columns, true) ? 'auth_code' : null);
            $requestedByTypeCol = in_array('requested_by_type', $columns, true) ? 'requested_by_type' : null;
            $requestedByIdCol = in_array('requested_by_id', $columns, true) ? 'requested_by_id' : null;
            $statusCol = in_array('status', $columns, true) ? 'status' : null;
            $createdAtCol = in_array('created_at', $columns, true) ? 'created_at' : null;

            $query = DB::table('domain_transfer_requests');

            if ($createdAtCol) {
                $query->orderByDesc($createdAtCol);
            }

            $rows = $query->limit(200)->get()->map(function ($row) use (
                $domainCol,
                $codeCol,
                $requestedByTypeCol,
                $requestedByIdCol,
                $statusCol
            ) {
                $requestedByType = $requestedByTypeCol ? strtolower((string) data_get($row, $requestedByTypeCol, '')) : '';
                $requestedById = $requestedByIdCol ? data_get($row, $requestedByIdCol) : null;

                if (!in_array($requestedByType, ['partner', 'customer'], true)) {
                    $requestedByType = 'customer';
                }

                return (object) [
                    'id' => data_get($row, 'id'),
                    'domain' => $domainCol ? (string) data_get($row, $domainCol, '-') : '-',
                    'transfer_code' => $codeCol ? (string) data_get($row, $codeCol, '-') : '-',
                    'requested_by_type' => ucfirst($requestedByType),
                    'requested_by_id' => $requestedById,
                    'status' => strtolower((string) ($statusCol ? data_get($row, $statusCol, 'pending') : 'pending')),
                ];
            });
        }

        return view('superadmin.domain_transfer_orders.index', [
            'requests' => $rows,
            'tableExists' => $tableExists,
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
        ]);

        if (!Schema::hasTable('domain_transfer_requests')) {
            return redirect()->back()->with('error', 'Domain transfer requests table not found.');
        }

        DB::table('domain_transfer_requests')
            ->where('id', $id)
            ->update(['status' => $request->input('status')]);

        return redirect()->back()->with('success', 'Domain transfer request status updated.');
    }

    public function store(Request $request)
    {
        $request->validate([
            'domain_name' => 'required|string|max:255',
            'transfer_code' => 'required|string|max:255',
            'requested_by_type' => 'nullable|in:partner,customer',
            'requested_by_id' => 'nullable|integer',
        ]);

        if (!Schema::hasTable('domain_transfer_requests')) {
            return response()->json(['success' => false, 'message' => 'Domain transfer request table not found.'], 422);
        }

        $columns = Schema::getColumnListing('domain_transfer_requests');
        $domainCol = in_array('domain', $columns, true) ? 'domain' : (in_array('domain_name', $columns, true) ? 'domain_name' : null);
        $codeCol = in_array('transfer_code', $columns, true) ? 'transfer_code' : (in_array('auth_code', $columns, true) ? 'auth_code' : null);

        if (! $domainCol || ! $codeCol) {
            return response()->json(['success' => false, 'message' => 'Required columns are missing in domain transfer requests table.'], 422);
        }

        $requestedDomain = strtolower(trim((string) $request->input('domain_name')));
        $requestedCode = trim((string) $request->input('transfer_code'));

        $baseQuery = DB::table('domain_transfer_requests')
            ->whereRaw('LOWER(' . $domainCol . ') = ?', [$requestedDomain]);

        if (in_array('status', $columns, true)) {
            $baseQuery->whereIn('status', ['pending', 'approved']);
        }

        if ($baseQuery->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'A transfer request for this domain is already submitted.',
            ], 422);
        }

        // Do not allow the same transfer code for another domain request.
        $codeQuery = DB::table('domain_transfer_requests')->where($codeCol, $requestedCode);
        if (in_array('status', $columns, true)) {
            $codeQuery->whereIn('status', ['pending', 'approved']);
        }
        $existingByCode = $codeQuery->first();
        if ($existingByCode) {
            $existingDomain = strtolower(trim((string) data_get($existingByCode, $domainCol, '')));
            if ($existingDomain !== $requestedDomain) {
                return response()->json([
                    'success' => false,
                    'message' => 'This transfer code is already used for another domain request.',
                ], 422);
            }
            return response()->json([
                'success' => false,
                'message' => 'This transfer request is already submitted.',
            ], 422);
        }

        $payload = [
            $domainCol => $requestedDomain,
            $codeCol => $requestedCode,
        ];

        if (in_array('requested_by_type', $columns, true)) {
            $payload['requested_by_type'] = $request->input('requested_by_type', 'customer');
        }
        if (in_array('requested_by_id', $columns, true)) {
            $payload['requested_by_id'] = $request->input('requested_by_id');
        }
        if (in_array('status', $columns, true)) {
            $payload['status'] = 'pending';
        }
        if (in_array('created_at', $columns, true)) {
            $payload['created_at'] = now();
        }
        if (in_array('updated_at', $columns, true)) {
            $payload['updated_at'] = now();
        }

        DB::table('domain_transfer_requests')->insert($payload);

        return response()->json(['success' => true, 'message' => 'Transfer request sent successfully.']);
    }
}
