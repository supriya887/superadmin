<?php

namespace App\Http\Controllers\SuperAdmin\Crm\Api;

use App\Http\Controllers\SuperAdmin\Crm\RenewalController as WebRenewalController;
use Illuminate\Http\Request;

class RenewalController extends WebRenewalController
{
    public function index(Request $request)
    {
        $renewals = parent::index($request)->getData()['renewals'];

        return response()->json([
            'success' => true,
            'message' => 'Renewals fetched successfully.',
            'data' => [
                'renewals' => $renewals->items(),
                'pagination' => [
                    'current_page' => $renewals->currentPage(),
                    'last_page' => $renewals->lastPage(),
                    'per_page' => $renewals->perPage(),
                    'total' => $renewals->total(),
                ],
                'filters' => ['all', 'expiring', 'active'],
            ],
        ]);
    }

    public function show(int $id)
    {
        $data = parent::show($id)->getData();

        return response()->json([
            'success' => true,
            'message' => 'Renewal fetched successfully.',
            'data' => [
                'renewal' => $data['row'],
                'timeline' => $data['timeline'],
            ],
        ]);
    }
}
