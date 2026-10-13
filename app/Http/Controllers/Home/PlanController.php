<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PlanController extends Controller
{
    /**
     * Display a listing of active plans.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $plans = Plan::where('is_active', true)->get();
        return response()->json([
            'success' => true,
            'data' => $plans
        ]);
    }

    /**
     * Show a single plan details.
     */
    public function show($id)
    {
        $plan = Plan::where('is_active', true)->find($id);

        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plan not found.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Plan details fetched successfully.',
            'data' => $plan,
        ], 200);
    }

    /**
     * Calculate order totals based on plan, duration, and quantity.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function calculateOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|exists:plans,id',
            'duration' => 'required|numeric|min:0.01', 
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $plan = Plan::find($request->plan_id);
        
        // base_price = plan price
        // total = base_price × quantity × duration
        $basePrice = (float)$plan->price;
        $quantity = (int)$request->quantity;
        $duration = (float)$request->duration;
        
        $total = $basePrice * $quantity * $duration;

        return response()->json([
            'success' => true,
            'plan_name' => $plan->name,
            'price' => $basePrice,
            'quantity' => $quantity,
            'duration' => $duration,
            'subtotal' => number_format($total, 2, '.', ''),
            'total' => number_format($total, 2, '.', ''),
        ]);
    }
}
