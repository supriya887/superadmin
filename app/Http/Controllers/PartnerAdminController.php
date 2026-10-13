<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Partner\Partner;
use App\Models\Customer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;



class PartnerAdminController extends Controller
{
    private function getPartnerIdFromSession()
    {
        return Session::get('partner_id') ?? Session::get('partnerId');
    }

    public function dashboard()
    {
        $partnerId = $this->getPartnerIdFromSession();
        if (!Session::has('userName') && $partnerId) {
            $partner = Partner::find($partnerId);
            if ($partner) {
                Session::put('userName', $partner->name);
                Session::put('userRole', 'Partner');
                // Keep both keys in sync for old/new flows.
                Session::put('partner_id', $partner->id);
                Session::put('partnerId', $partner->id);
            }
        }
        return view('partner.partneradmin.dashboard');
    }

    public function servicesIndex(Request $request)
    {
        // $type = $request->get('type', 'tizzy_mail');
        $data['mail'] = \App\Models\Plan::with('features')
            ->where('program_id', 3)
            ->where('is_active', 1)
            ->get()->toArray();
        $data['ms365'] = \App\Models\Plan::with('features')
            ->where('program_id', 2)
            ->where('is_active', 1)
            ->get()->toArray();
        $data['google'] = \App\Models\Plan::with('features')
            ->where('program_id', 4)
            ->where('is_active', 1)
            ->get()->toArray();

            // print_r($data['mail']);die();
            
        return view('partner.partneradmin.services.index', $data);
    }

    public function customersIndex(Request $request)
    {
        $partner_id = $this->getPartnerIdFromSession();
        $customers = Customer::where('partner_id', $partner_id)->latest()->get();
        // print_r($partner_id);die();

        if ($request->ajax()) {
            return response()->json([
                'customers' => $customers,
            ]);
        }

        return view('partner.partneradmin.customers.index', compact('customers'));
    }

    public function customersCreate()
    {
        return view('partner.partneradmin.customers.create');
    }

    public function partnersIndex()
    {
        return view('admin.partners.index');
    }

    public function gstIndex()
    {
        return view('admin.gst.index');
    }

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
            $errors = $validator->errors();
            return response()->json([
                'error'            => true,
                'name'             => $errors->first('name'),
                'email'            => $errors->first('email'),
                'mobile'           => $errors->first('mobile'),
                'company_name'     => $errors->first('company_name'),
                'company_address'  => $errors->first('company_address'),
                'gstin'            => $errors->first('gstin'),
                'country'          => $errors->first('country'),
                'state'            => $errors->first('state'),
                'city'             => $errors->first('city'),
                'zip_code'         => $errors->first('zip_code'),
                'pan_no'           => $errors->first('pan_no'),
            ]);
        }

        $partner_id = $this->getPartnerIdFromSession();
        if (!$partner_id) {
            return response()->json([
                'error'   => true,
                'message' => 'Session expired. Please login again.'
            ]);
        }

        Customer::create([
            'partner_id' => $partner_id,
            'gstin' => strtoupper($request->gstin),
            'name' => $request->name,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'company_name' => $request->company_name,
            'company_address' => $request->company_address,
            'country' => $request->country,
            'state' => $request->state,
            'city' => $request->city,
            'zip_code' => $request->zip_code,
            'pan_no' => $request->pan_no,
        ]);

        return response()->json([
            'success'  => 'Customer added successfully!',
            'redirect' => route('partner-admin.customers.index')
        ]);
    }

    public function searchPincode(Request $request)
    {
        $pincode = $request->zip_code;
        
        try {
            $response = Http::get("https://api.postalpincode.in/pincode/{$pincode}");
            
            if ($response->successful()) {
                $res = $response->json();
                
                if (isset($res[0]) && $res[0]['Status'] == 'Success') {
                    return response()->json([
                        'status' => true,
                        'data' => $res[0]['PostOffice'][0]
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Log error if needed
        }

        return response()->json([
            'status' => false,
            'message' => 'Invalid Pincode or Service Unavailable'
        ]);
    }

    public function customersEdit($id)
    {
        $customer = Customer::find($id);
        return response()->json($customer);
    }

    public function customersUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:customers,id',
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('customers')->ignore($request->id),
            ],
            'mobile' => 'required|digits:10',
            'status' => 'required|in:0,1',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            return response()->json([
                'error'            => true,
                'name'             => $errors->first('name'),
                'email'            => $errors->first('email'),
                'mobile'           => $errors->first('mobile'),
                'status'           => $errors->first('status'),
            ]);
        }

        $customer = Customer::find($request->id);
        $customer->update([
            'name' => $request->name,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'status' => $request->status,
        ]);

        return response()->json([
            'success' => 'Customer updated successfully.'
        ]);
    }

    public function customersDelete($id)
    {
        $customer = Customer::where('id', $id)
            ->where('status', 1)
            ->first();
    
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found.'
            ], 404);
        }
    
        $customer->update([
            'status' => 0,
        ]);
    
        return response()->json([
            'success' => true,
            'message' => 'Customer deleted successfully.'
        ]);
    }
}
