<?php

namespace App\Http\Controllers\SuperAdmin\Crm\Api;

use App\Http\Controllers\SuperAdmin\Crm\ServicesProviderController as WebProviderController;
use App\Models\Provider;
use Illuminate\Http\Request;

class ProviderController extends WebProviderController
{
    public function index()
    {
        $providers = parent::index()->getData()['providers'];

        return response()->json([
            'success' => true,
            'message' => 'Providers fetched successfully.',
            'data' => ['providers' => $providers],
        ]);
    }

    public function plans(Request $request, Provider $provider)
    {
        $data = parent::plans($request, $provider)->getData();
        $plans = collect($data['visibleCategories'])->flatten(1)->values();

        return response()->json([
            'success' => true,
            'message' => 'Provider plans fetched successfully.',
            'data' => [
                'provider' => $data['provider'],
                'plans' => $plans,
                'filters' => $data['filterMenu'],
            ],
        ]);
    }
}
