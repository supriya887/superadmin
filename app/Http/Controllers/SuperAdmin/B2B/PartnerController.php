<?php

namespace App\Http\Controllers\SuperAdmin\B2B;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Database\Query\JoinClause; 

class PartnerController extends Controller
{
    protected $request;
    protected $partner;
    public function __construct(Partner $partner, Request $request)
    {
        $this->partner = $partner;
        $this->request = $request;
    }
    public function partner_list()
    {
        
        return view('superadmin.b2b_partners.index');
    }

    public function partner_list_table()
    {   
        $draw            = $this->request->get('draw');
        $start           = $this->request->get("start");
        $rowPerPage      = $this->request->get("length");
        $orderArray      = $this->request->get('order');
        $columnNameArray = $this->request->get('columns');

        // Custom column search parameters (from custom search fields)
        $search_name         = $this->request->get('search_name');
        $search_email        = $this->request->get('search_email');
        $search_mobile       = $this->request->get('search_mobile');
        $search_company_name = $this->request->get('search_company_name');
        $globalSearchValue   = $this->request->get('search')['value'] ?? '';

        if (!empty($orderArray)) {
            $columnIndex     = $orderArray[0]['column'];
            $columnSortOrder = $orderArray[0]['dir'];
            $columnName      = $columnNameArray[$columnIndex]['data'];
            if (!in_array($columnName, ['id','name','email','mobile','company_name','status'])) {
                $columnName = 'id'; // Fallback to id if not a listed column
            }
        } else {
            $columnIndex     = 0;
            $columnSortOrder = "asc";
            $columnName      = 'id';
        }

        // Base query for partners
        $query = Partner::where('status', '!=', 0);

        // Apply custom search on fields (from filter boxes)
        if (!empty($search_name)) {
            $query->where('name', 'like', '%' . $search_name . '%');
        }
        if (!empty($search_email)) {
            $query->where('email', 'like', '%' . $search_email . '%');
        }
        if (!empty($search_mobile)) {
            $query->where('mobile', 'like', '%' . $search_mobile . '%');
        }
        if (!empty($search_company_name)) {
            $query->where('company_name', 'like', '%' . $search_company_name . '%');
        }

        // Apply global search if available
        if (!empty($globalSearchValue)) {
            $query->where(function ($q) use ($globalSearchValue) {
                $q->where('name', 'like', '%' . $globalSearchValue . '%')
                  ->orWhere('email', 'like', '%' . $globalSearchValue . '%')
                  ->orWhere('mobile', 'like', '%' . $globalSearchValue . '%')
                  ->orWhere('company_name', 'like', '%' . $globalSearchValue . '%');
            });
        }

        $totalData = Partner::where('status', '!=', 0)->count();
        $totalFiltered = $query->count();

        $arrData = $query->orderBy($columnName, $columnSortOrder)
            ->offset($start)
            ->limit($rowPerPage)
            ->get();

        // Shape the data for the DataTable frontend, including the "action" column, adjust as necessary
        $data = [];
        foreach ($arrData as $partner) {
            $data[] = [
                'id'            => $partner->id,
                'name'          => $partner->name,
                'email'         => $partner->email,
                'mobile'        => $partner->mobile,
                'company_name'  => $partner->company_name,
                'status'        => $partner->status == 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>',
                'action'        => '<div class="d-flex justify-content-center gap-1">
                                    <a class="btn btn-light btn-icon btn-sm rounded-circle" title="Edit" href="' . url('superadmin/b2b-partner-edit/' . $partner->id) . '"><i class="ti ti-edit fs-lg"></i></a>
                                    <a class="btn btn-light btn-icon btn-sm rounded-circle delete" title="Delete" id="' . $partner->id . '" href="javascript:void(0);"><i class="ti ti-trash fs-lg"></i></a>
                                    </div>',
            ];
        }

        $response = [
            'draw' => intval($draw),
            'recordsTotal' => $totalData,
            'recordsFiltered' => $totalFiltered,
            'data' => $data,
        ];

        return response()->json($response);
    }

    public function add_partner($id = null)
    {
        $partner = null;
        if ($id) {
            $partner = Partner::find($id);
        }
        return view('superadmin.b2b_partners.form', compact('partner'));
    }

    public function partner_validation(Request $request)
    {
        $partnerId = $request->input('id');

        $rules = [
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255' . ($partnerId ? '|unique:pgsql_auth.partners,email,' . $partnerId : '|unique:pgsql_auth.partners,email'),
            'mobile'   => 'required|digits:10',
            'company_name'    => 'nullable|string|max:255',
            'status'   => 'required|in:0,1',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'messages' => $validator->errors()->toArray()]);
        }

        DB::beginTransaction();
        try {
            if ($partnerId) {
                $partner = Partner::find($partnerId);
                if (!$partner) {
                    return response()->json(['error' => true, 'message' => 'Partner not found!'], 404);
                }
                $partner->name     = $request->name;
                $partner->email    = $request->email;
                $partner->mobile   = $request->mobile;
                $partner->company_name = $request->company_name;
                $partner->status   = $request->status;
                $partner->save();
                $message = 'Partner updated successfully!';
            } else {
                Partner::create([
                    'name'     => $request->name,
                    'email'    => $request->email,
                    'mobile'   => $request->mobile,
                    'company_name' => $request->company_name,
                    'status'   => $request->status,
                ]);
                $message = 'Partner created successfully!';
            }
            DB::commit();
            return response()->json(['success' => $message, 'redirect' => url('superadmin/b2b-partners-list')]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => true, 'message' => 'Failed to process partner: ' . $e->getMessage()], 500);
        }
    }

    public function delete_partner(Request $request)
    {
        $partnerId = $request->input('id');
        DB::beginTransaction();
        try {
            $partner = Partner::find($partnerId);
            if ($partner) {
                // Soft delete by changing status to inactive (0)
                $partner->status = 0;
                $partner->save();
                DB::commit();
                return response()->json(['success' => 'Partner soft-deleted successfully!']); 
            } else {
                return response()->json(['error' => 'Partner not found!'], 404);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to delete partner: ' . $e->getMessage()], 500);
        }
    }
}
