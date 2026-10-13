<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\CreditRequest;
use App\Models\Customer;
use App\Models\Partner as PartnerRecord;
use App\Models\Provider;
use App\Services\PartnerB2bNewPlanCheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;

class NewPlanFlowController extends Controller
{
    public function create()
    {
        $partnerId = (int) Session::get('partner_id');
        $partner = PartnerRecord::findOrFail($partnerId);

        $hasPendingCreditRequest = Schema::hasTable('credit_requests')
            && CreditRequest::query()
                ->where('partner_id', $partnerId)
                ->where('status', 'pending')
                ->exists();

        $customers = Customer::query()
            ->where('partner_id', $partnerId)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'mobile', 'company_name']);

        $providers = Provider::query()
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return view('partner.partneradmin.b2b.new_plan', [
            'partner' => $partner,
            'customers' => $customers,
            'providers' => $providers,
            'hasPendingCreditRequest' => $hasPendingCreditRequest,
            'walletBalance' => (float) ($partner->wallet_balance ?? 0),
        ]);
    }

    public function storeOrder(Request $request, PartnerB2bNewPlanCheckoutService $checkout)
    {
        $partnerId = (int) Session::get('partner_id');

        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'user_id' => 'required|exists:customers,id',
            'domain_name' => 'required|string|max:255',
            'quantity' => 'required|integer|min:1',
            'licenses' => 'nullable|integer|min:1',
            'provider_id' => 'nullable|integer|exists:providers,id',
            'apply_gst' => 'nullable|boolean',
            'gst_rate' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'promocode' => 'nullable|string',
            'order_note' => 'nullable|string',
            'status' => 'nullable|string',
            'aadhaar_number' => 'nullable|string|max:20',
            'po_name' => 'nullable|string|max:255',
            'po_email' => 'nullable|email|max:255',
            'po_mobile' => 'nullable|string|max:20',
            'po_gstin' => 'nullable|string|max:20',
            'po_company_name' => 'nullable|string|max:255',
            'po_company_address' => 'nullable|string',
            'po_city' => 'nullable|string|max:100',
            'po_state' => 'nullable|string|max:100',
            'po_country' => 'nullable|string|max:100',
            'po_pincode' => 'nullable|string|max:20',
        ]);

        $validated['apply_gst'] = $request->boolean('apply_gst', true);
        $validated['order_type'] = 'B2B';

        try {
            $result = $checkout->processCheckout($validated, $partnerId);
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors(),
                ], 422);
            }
            throw $e;
        }

        if (empty($result['ok'])) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Insufficient Credits',
                    'code' => $result['code'] ?? 'insufficient_credits',
                    'credit_request_id' => $result['credit_request_id'] ?? null,
                    'request_credits_url' => $result['request_credits_url'] ?? null,
                    'total_amount' => $result['total_amount'] ?? null,
                    'wallet_balance' => $result['wallet_balance'] ?? null,
                ], 422);
            }

            return back()
                ->withInput()
                ->with('error', ($result['message'] ?? 'Insufficient Credits') . ' You can request credits from your administrator.')
                ->with('request_credits_url', $result['request_credits_url'] ?? null);
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $result['message'] ?? 'Order created successfully',
                'order' => [
                    'order_id' => $result['order_id'],
                    'order_no' => $result['order_no'],
                    'total_amount' => $result['total_amount'],
                ],
            ], 201);
        }

        return redirect()
            ->route('partner-admin.dashboard')
            ->with('success', 'Order placed successfully.');
    }
}
