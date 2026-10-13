<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Partner\Partner;
use App\Models\Customer;
use App\Models\PartnerLoginLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PartnerAdminController extends Controller
{
    /**
     * Retrieve services dynamically.
     */
    public function servicesIndex(Request $request)
    {
        try {
            // Note: Reusing the same data structure as the web controller, 
            // but fetching it in an API context.
            $data['mail'] = \App\Models\Plan::with('features')
                ->where('program_id', 3)
                ->where('is_active', 1)
                ->get();
                
            $data['ms365'] = \App\Models\Plan::with('features')
                ->where('program_id', 2)
                ->where('is_active', 1)
                ->get();
                
            $data['google'] = \App\Models\Plan::with('features')
                ->where('program_id', 4)
                ->where('is_active', 1)
                ->get();

            return response()->json([
                'success' => true,
                'error'   => false,
                'message' => 'Services retrieved successfully.',
                'data'    => $data,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => true,
                'message' => 'Failed to retrieve services.',
                'data'    => ['error_details' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * List all customers for the authenticated partner.
     */
    public function customersIndex(Request $request)
    {
        try {
            // Reusing logic: Authenticated user via Sanctum provides the partner_id
            // (Assumes the token belongs to the partner)
            $partner_id = $request->user()->id; 
            
            $customers = Customer::where('partner_id', $partner_id)
                                 ->where('status', '!=', 0) // Example condition based on web controller delete logic
                                 ->latest()
                                 ->get();

            return response()->json([
                'success' => true,
                'error'   => false,
                'message' => 'Customers retrieved successfully.',
                'data'    => $customers,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => true,
                'message' => 'Failed to retrieve customers.',
                'data'    => ['error_details' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Store a newly created customer in storage.
     */
    public function customersStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:customers,email',
            'mobile' => 'required|digits:10',
            'company_name' => 'required|string|max:255',
            'company_address' => 'required|string',
            'gstin' => ['required', 'string', 'size:15', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/'],
            'country' => 'required|string',
            'state' => 'required|string',
            'city' => 'required|string',
            'zip_code' => 'required|string',
            'pan_no' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error'   => true,
                'message' => 'Validation failed.',
                'data'    => $validator->errors(),
            ], 422);
        }

        try {
            // Get partner ID from Sanctum token instead of session
            $partner_id = $request->user()->id;

            $customer = Customer::create([
                'partner_id'      => $partner_id,
                'gstin'           => strtoupper($request->gstin),
                'name'            => $request->name,
                'email'           => $request->email,
                'mobile'          => $request->mobile,
                'company_name'    => $request->company_name,
                'company_address' => $request->company_address,
                'country'         => $request->country,
                'state'           => $request->state,
                'city'            => $request->city,
                'zip_code'        => $request->zip_code,
                'pan_no'          => $request->pan_no,
            ]);

            return response()->json([
                'success' => true,
                'error'   => false,
                'message' => 'Customer added successfully.',
                'data'    => $customer,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => true,
                'message' => 'Failed to add customer.',
                'data'    => ['error_details' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Reusing existing external API logic for Pincode
     */
    public function searchPincode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'zip_code' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error'   => true,
                'message' => 'Validation failed.',
                'data'    => $validator->errors(),
            ], 422);
        }

        $pincode = $request->zip_code;
        
        try {
            $response = Http::get("https://api.postalpincode.in/pincode/{$pincode}");
            
            if ($response->successful()) {
                $res = $response->json();
                
                if (isset($res[0]) && $res[0]['Status'] == 'Success') {
                    return response()->json([
                        'success' => true,
                        'error'   => false,
                        'message' => 'Pincode details retrieved successfully.',
                        'data'    => $res[0]['PostOffice'][0]
                    ], 200);
                }
            }

            return response()->json([
                'success' => false,
                'error'   => true,
                'message' => 'Invalid Pincode or Service Unavailable',
                'data'    => null,
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => true,
                'message' => 'Encountered an error while fetching pincode details.',
                'data'    => ['error_details' => $e->getMessage()],
            ], 500);
        }
    }
}
