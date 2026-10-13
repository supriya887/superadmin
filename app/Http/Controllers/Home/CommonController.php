<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use App\Models\Promocode;

class CommonController extends Controller
{
    /**
     * Fetch all states of India from a free REST API.
     */
    public function getStates()
    {
        $response = Http::post('https://countriesnow.space/api/v0.1/countries/states', [
            'country' => 'India'
        ]);

        return response()->json($response->json());
    }

    /**
     * Fetch cities of a specific state in India.
     */
    public function getCities(Request $request)
    {
        $response = Http::post('https://countriesnow.space/api/v0.1/countries/state/cities', [
            'country' => 'India',
            'state' => $request->state
        ]);

        return response()->json($response->json());
    }

    /**
     * Apply promo code and return discount percentage.
     */
    public function applyPromo(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Promo code is required'], 422);
        }

        $promo = Promocode::query()
            ->whereRaw('UPPER(TRIM(code)) = ?', [strtoupper(trim((string) $request->code))])
            ->validNow()
            ->first();

        if (!$promo) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired promo code'], 404);
        }

        $discountPercent = max(0.0, min(100.0, (float) ($promo->discount_percent ?? 0)));

        return response()->json([
            'success' => true,
            'discount_percent' => $discountPercent,
            'code' => (string) $promo->code,
        ]);
    }
}
