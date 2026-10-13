<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class DomainController extends Controller
{
    /**
     * Check domain availability.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkDomain(Request $request)
    {
        $query = strtolower($request->query('domain'));

        if (!$query) {
            return response()->json([
                'status' => 'error',
                'message' => 'Domain keyword is required'
            ], 400);
        }

        // TLDs and their static prices
        $tldPrices = [
            'com' => 499,
            'in' => 199,
            'net' => 399,
            'org' => 699,
        ];

        $results = [];

        // Generate suggestions based on keyword
        foreach ($tldPrices as $tld => $price) {
            $domain = $query . '.' . $tld;
            
            // Available if no A records exist
            $isAvailable = !checkdnsrr($domain, 'A'); 

            if ($isAvailable) {
                $results[] = [
                    'domain' => $domain,
                    'price' => $price,
                    'available' => true,
                ];
            }
        }

        return response()->json($results);
    }

    /**
     * Assign domain to an order.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignDomain(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'domain' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Valid domain is required'
            ], 400);
        }

        $order = \App\Models\Order::find($id);
        
        if (!$order) {
            // If DB entry not found, at least store in session for later
            session(['assigned_domain_' . $id => $request->domain]);
            return response()->json([
                'status' => 'success',
                'message' => 'Domain assigned to session (DB record not found)'
            ]);
        }

        try {
            $order->update(['domain' => $request->domain]);
            return response()->json([
                'status' => 'success',
                'message' => 'Domain assigned to order successfully'
            ]);
        } catch (\Exception $e) {
            // Fallback to session on DB error
            session(['assigned_domain_' . $id => $request->domain]);
            return response()->json([
                'status' => 'success',
                'message' => 'Domain assigned to session (DB Error: ' . $e->getMessage() . ')'
            ]);
        }
    }

    /**
     * Fallback DNS check for domain availability.
     *
     * @param string $domain
     * @return bool
     */
    private function isDomainRegistered($domain)
    {
        // checkdnsrr returns true if any DNS records exist for the domain
        return checkdnsrr($domain, 'ANY');
    }
}
