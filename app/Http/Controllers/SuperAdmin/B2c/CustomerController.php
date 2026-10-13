<?php

namespace App\Http\Controllers\SuperAdmin\B2c;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Database\Query\JoinClause; 

class CustomerController extends Controller
{
    protected $request;
    protected $user;
    public function __construct(User $user, Request $request)
    {
        $this->user = $user;
        $this->request = $request;
    }
    public function customer_list(Request $request)
    {
        
        return view('superadmin.b2c_customers.index');
    }

    public function customer_list_table()
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
        $search_gstin        = $this->request->get('search_gstin');
        $search_company_name = $this->request->get('search_company_name');
        $globalSearchValue   = $this->request->get('search')['value'] ?? '';

        if (!empty($orderArray)) {
            $columnIndex     = $orderArray[0]['column'];
            $columnSortOrder = $orderArray[0]['dir'];
            $columnName      = $columnNameArray[$columnIndex]['data'];
            if (!in_array($columnName, ['id','name','email','mobile','gstin','company_name','status'])) {
                $columnName = 'id'; // Fallback to id if not a listed column
            }
        } else {
            $columnIndex     = 0;
            $columnSortOrder = "asc";
            $columnName      = 'id';
        }

        // Base query for users
        $query = User::where('status', '!=', 0);

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
        if (!empty($search_gstin)) {
            $query->where('gstin', 'like', '%' . $search_gstin . '%');
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
                  ->orWhere('gstin', 'like', '%' . $globalSearchValue . '%')
                  ->orWhere('company_name', 'like', '%' . $globalSearchValue . '%')
                  ->orWhere('company_address', 'like', '%' . $globalSearchValue . '%')
                  ->orWhere('country', 'like', '%' . $globalSearchValue . '%')
                  ->orWhere('state', 'like', '%' . $globalSearchValue . '%')
                  ->orWhere('city', 'like', '%' . $globalSearchValue . '%')
                  ->orWhere('postcode', 'like', '%' . $globalSearchValue . '%');
            });
        }

        $totalData = User::where('status', '!=', 0)->count();
        $totalFiltered = $query->count();

        $arrData = $query->orderBy($columnName, $columnSortOrder)
            ->offset($start)
            ->limit($rowPerPage)
            ->get();

        // Shape the data for the DataTable frontend, including the "action" column, adjust as necessary
        $data = [];
        foreach ($arrData as $user) {
            $data[] = [
                'id'            => $user->id,
                'name'          => $user->name,
                'email'         => $user->email,
                'mobile'        => $user->mobile,
                'gstin'         => $user->gstin,
                'company_name'  => $user->company_name,
                'status'        => $user->status == 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>',
                'action'        => '<div class="d-flex justify-content-center gap-1">
                                    <a class="btn btn-light btn-icon btn-sm rounded-circle" title="Edit" href="' . url('superadmin/b2c-customers-edit/' . $user->id) . '"><i class="ti ti-edit fs-lg"></i></a>
                                    </div>',
                                ];
                                // <a class="btn btn-light btn-icon btn-sm rounded-circle delete" title="Delete" id="' . $user->id . '" href="javascript:void(0);"><i class="ti ti-trash fs-lg"></i></a>
        }

        $response = [
            'draw' => intval($draw),
            'recordsTotal' => $totalData,
            'recordsFiltered' => $totalFiltered,
            'data' => $data,
        ];

        return response()->json($response);
    }

    public function add_customer($id = null)
    {
        $user = null;
        if ($id) {
            $user = User::find($id);
        }
        return view('superadmin.b2c_customers.form', compact('user'));
    }

    public function customer_validation(Request $request)
    {
        $userId = $request->input('id');

        if ($userId) {
            $rules = [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email,' . $userId,
                'mobile' => 'required|digits:10',
            ];
        } else {
            $rules = [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email',
                'mobile' => 'required|digits:10',
                'gstin' => 'required|string|max:15',
                'pan_no' => 'nullable|string|max:20',
                'company_name' => 'required|string|max:255',
                'company_address' => 'required|string|max:500',
                'country' => 'required|string|max:255',
                'state' => 'required|string|max:255',
                'city' => 'required|string|max:255',
                'postcode' => 'required|string|max:10',
                'status2' => 'required|in:0,1',
            ];
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'messages' => $validator->errors()->toArray()]);
        }

        DB::beginTransaction();
        try {
            if ($userId) {
                $user = User::find($userId);
                if (!$user) {
                    return response()->json(['error' => true, 'message' => 'User not found!'], 404);
                }
                $user->name = $request->name;
                $user->email = $request->email;
                $user->mobile = $request->mobile;
                $user->save();
                $message = 'User updated successfully!';
            } else {
                User::create([
                    'name'     => $request->name,
                    'email'    => $request->email,
                    'mobile'   => $request->mobile,
                    'gstin'    => $request->gstin,
                    'pan_no'   => $request->pan_no,
                    'company_name' => $request->company_name,
                    'company_address' => $request->company_address,
                    'country'  => $request->country,
                    'state'    => $request->state,
                    'city'     => $request->city,
                    'postcode' => $request->postcode,
                    'status'   => $request->status2,
                ]);
                $message = 'User created successfully!';
            }
            DB::commit();
            return response()->json(['success' => $message, 'redirect' => url('superadmin/b2c-customers-list')]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => true, 'message' => 'Failed to process user: ' . $e->getMessage()], 500);
        }
    }

    public function delete_customer(Request $request)
    {
        $userId = $request->input('id');
        DB::beginTransaction();
        try {
            $user = User::find($userId);
            if ($user) {
                
                User::where('id', $userId)->delete();
        
                DB::commit();
                return response()->json(['success' => 'User soft-deleted successfully!']); 
            } else {
                return response()->json(['error' => 'User not found!'], 404);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to delete user: ' . $e->getMessage()], 500);
        }
    }
}
