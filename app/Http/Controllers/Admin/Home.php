<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Master;
use App\Mail\TestEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mail;
use App\Models\User;
use App\Mail\SendApprovalMail;

require_once app_path('helper.php');


class Home extends Master
{
    protected $request;
    protected $usersession;
    protected $usercreation;
    protected $country;
    protected $state;
    protected $userrole;
    protected $rolepermission;
    protected $userpermission;
    protected $master;
    protected $adminbranch;
    protected $modulemst;
    protected $adminsettings;
    protected $account_mst;
    protected $emailaccess;


    public function __construct(Request $request)
    {
        $this->request = $request;
        $admin = DB::connection('pgsql_admin');
        $this->usersession = $admin->table('admin_user_session');
        $this->usercreation = $admin->table('admin_user_creation');
        $this->country = DB::table('location_mst');
        $this->state = DB::table('other_state');
        $this->userrole = $admin->table('admin_user_role');
        $this->userpermission = $admin->table('admin_user_role_permission');
        $this->rolepermission = $admin->table('admin_user_module');
        $this->master = new Master();
        $this->adminbranch = $admin->table("admin_branch");
        $this->modulemst = $admin->table("admin_user_module_mst");
        $this->adminsettings = $admin->table("admin_settings");
        $this->emailaccess = $admin->table("admin_email_access");
    }
    // public function check_permission($val)
    // {
    //     // print_r($val);die;
    //     if ($val == 0) {
    //         // return Redirect::to('/');
    //         // exit();
    //         $this->permission();
    //         die();
    //     }
    //     return redirect('permission-error');
    //     // redirect()->route('check_permission', ['id' => $val]);
    // }
    // public function permission()
    // {
    //     echo 'no access';
    //     die;
    //     return view('permission_error');
    // }
    public function index()
    {
        session(['menu' => 'Dashboard']);
        // print_r(session()->all());die;
        $data = [
            'title' => 'Dashboard',
            'pageTitle' => 'Dashboard', 'li_1' => 'Admin', 'li_2' => 'Dashboard'
        ];
        return view('admin.index', $data);
    }

    public function logout()
    {
        $SessionId = Session::get('sessionId');
        $Session = ['logout' => date('Y-m-d H:i:s')];
        $this->usersession->where('id', $SessionId)->update($Session);

        Session::forget('userId');
        Session::forget('userName');
        Session::forget('roleId');
        Session::forget('passWord');
        Session::forget('sessionId');
        return redirect('login');
    }

    public function add_usercreation($id = '')
    {
        session(['menu' => 'Admin']);
        $title = 'Add User Creation';
        if ($id != '') {
            $title = 'Edit User Creation';
            $this->master->check_permission(2, 'Edit');
        }
        else {
            $this->master->check_permission(2, 'Add');
        }
        $data = ['title' => $title];
        $data['role'] = DB::connection('pgsql_admin')->table('admin_user_role')->orderBy('id')->get();
        $data['branch'] = DB::connection('pgsql_admin')->table('admin_branch')->orderBy('id')->get();
        if ($id != '') {
            $data['update'] = DB::connection('pgsql_admin')
                ->table('admin_user_creation')
                ->where('id', (int) $id)
                ->first();
        }
        return view('admin.add-usercreation', $data);
    }

    public function usercreation_list()
    {
        session(['menu' => 'Admin']);
        $this->master->check_permission(2, 'View');
        $data = [
            'title' => 'User Creation List',
            'users' => DB::connection('pgsql_admin')
                ->table('admin_user_creation')
                ->orderBy('id')
                ->get(),
        ];

        return view('superadmin.user-management.usercreation-list', $data);
    }

    public function user_validation()
    {
        $id = trim((string) ($this->request['id'] ?? ''));
        $ignoreId = ($id !== '' && $id !== '0') ? (int) $id : null;

        $validator = Validator::make(
            $this->request->all(),
            [
                'user_name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('pgsql_admin.admin_user_creation', 'user_name')->ignore($ignoreId, 'id'),
                ],
                'password' => ['required', 'string', 'max:255'],
                'confirm_password' => 'required|same:password',
                'status' => 'required',
                'role' => 'required',
            ]
        );

        if ($validator->fails()) {
            $error = $validator->errors();
            return response()->json([
                "error" => true,
                "user_name" => $error->first('user_name'),
                "password" => $error->first('password'),
                "confirm_password" => $error->first('confirm_password'),
                "status" => $error->first('status'),
                "role" => $error->first('role'),
            ]);
        }

        $array = [
            'user_name' => $this->request['user_name'],
            'password' => $this->request['confirm_password'],
            'status' => $this->request['status'],
            'role' => $this->request['role'],
        ];

        try {
            if ($id === '' || $id === '0') {
                $array['created_at'] = date('Y-m-d H:i:s');
                $array['updated_at'] = date('Y-m-d H:i:s');
                DB::connection('pgsql_admin')->table('admin_user_creation')->insert($array);
            } else {
                $array['updated_at'] = date('Y-m-d H:i:s');
                DB::connection('pgsql_admin')
                    ->table('admin_user_creation')
                    ->where('id', (int) $id)
                    ->update($array);
            }

            return response()->json([
                "success" => "User Saved Successfully."
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                "error" => true,
                "user_name" => "Unable to save user. " . $e->getMessage(),
            ], 500);
        }
    }

    public function delete_usercreation()
    {
        $id = (int) ($this->request['id'] ?? 0);
        if ($id <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid user id.',
            ], 422);
        }

        if (function_exists('permission') && !permission(1, 'Delete') && !permission(2, 'Delete')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete users.',
            ], 403);
        }

        DB::connection('pgsql_admin')->table('admin_user_creation')->where('id', $id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully.',
        ]);
    }
    public function authority_table()
    {
        $this->master->check_permission(3, 'View');
        session(['menu' => 'Admin']);
        $data = [
            'title' => 'Role',
            'result' => DB::connection('pgsql_admin')
                ->table('admin_user_role')
                ->orderBy('id')
                ->get(),
        ];

        return view('admin.authority_table', $data);
    }

    public function add_authority($id = "")
    {
        session(['menu' => 'Admin']);
        $title = 'Add Role';
        if ($id != '') {
            $title = 'Edit Role';
            $this->master->check_permission(3, 'Edit');
        }
        else {
            $this->master->check_permission(3, 'Add');
        }
        $data = [
            'title' => $title,
            'result' => DB::connection('pgsql_admin')->table('admin_user_module')->orderBy('id')->get(),
            'id' => $id,
        ];

        if ($id != '') {
            $data['update'] = DB::connection('pgsql_admin')
                ->table('admin_user_role')
                ->where('id', (int) $id)
                ->first();
        }

        return view('admin.add_authority', $data);
    }
    public function authority_validation()
    {
        $validator = Validator::make(
            $this->request->all(),
        [
            'role_name' => 'required',
        ]
        );
        $id = $this->request['id'];

        if ($validator->fails()) {
            $error = $validator->errors();
            $json = array(
                "error" => true,
                "role_name" => $error->first('role_name'),
            );
        }
        else {
            $roleName = $this->request['role_name'];

            // IMPORTANT:
            // When creating a new role, the hidden `id` field is usually empty string.
            // Inserting empty-string into an auto-increment PK can break the insert.
            $RoleArray = [
                'role_name' => $roleName,
                // This project uses `rolevalue` as the display value in `authority_table`.
                // Keep it in sync with `role_name` so add/update reflects immediately.
                'rolevalue' => $roleName,
            ];

            $requestId = trim((string) ($this->request['id'] ?? ''));
            if ($requestId === '' || $requestId === '0') {
                DB::connection('pgsql_admin')->table('admin_user_role')->insert($RoleArray);
                $id = (int) DB::connection('pgsql_admin')->getPdo()->lastInsertId();
                $crud = 1;
            }
            else {
                $id = (int) $requestId;
                DB::connection('pgsql_admin')->table('admin_user_role')->where('id', $id)->update($RoleArray);
                $crud = 2;
            }

            $array = $this->request->all();
            unset($array['role_name']);
            unset($array['rolevalue']);
            unset($array['id']);
            unset($array['_token']);
            DB::connection('pgsql_admin')->table('admin_user_role_permission')->where('role_id', $id)->delete();
            $perm = [];
            foreach ($array as $key => $val) {
                if (!is_array($val)) {
                    continue;
                }
                foreach ($val as $row) {
                    $perm[] = array(
                        "role_id" => $id,
                        "module_id" => (int) $key,
                        "permission" => $row
                    );
                }
            }
            if (!empty($perm)) {
                DB::connection('pgsql_admin')->table('admin_user_role_permission')->insert($perm);
            }
            $json = array(
                "success" => "Authority Saved Successfully."
            );
        }
        return response()->json($json);
    }
    public function delete_authority()
    {
        $id = (int) ($this->request['id'] ?? 0);
        if ($id <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid role id.',
            ], 422);
        }

        try {
            // Delete role permissions first, then the role itself.
            DB::connection('pgsql_admin')
                ->table('admin_user_role_permission')
                ->where('role_id', $id)
                ->delete();

            DB::connection('pgsql_admin')
                ->table('admin_user_role')
                ->where('id', $id)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Role deleted successfully.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to delete role. ' . $e->getMessage(),
            ], 500);
        }
    }

    //modile list

    public function add_rolepermission($mid, $id = '')
    {
        session(['menu' => 'Admin']);
        $this->master->check_permission(3, 'Add');
        $title = 'Add Role permission';
        $moduleId = (int) $mid;
        $rowId = trim((string) $id);

        if ($rowId !== '') {
            $title = 'Edit Role permission';
            $this->master->check_permission(3, 'Edit');
        }

        $data = [
            'title' => $title,
            'pageTitle' => $title,
            'li_1' => 'Admin',
            'li_2' => $title,
            'mid' => $moduleId,
            'id' => $rowId,
            'parent_module' => $moduleId > 0
                ? DB::connection('pgsql_admin')->table('admin_user_module_mst')->where('id', $moduleId)->first()
                : null,
            'module' => DB::connection('pgsql_admin')->table('admin_user_module_mst')->orderBy('id')->get(),
        ];

        if ($rowId !== '') {
            $data['update'] = DB::connection('pgsql_admin')
                ->table('admin_user_module')
                ->where('id', (int) $rowId)
                ->first();
        }

        return view('admin.add_rolepermission', $data);
    }

    public function rolepermission_table($id = '')
    {
        $this->master->check_permission(3, 'View');
        session(['menu' => 'Admin']);
        $moduleId = (int) $id;
        $parentModule = $moduleId > 0
            ? DB::connection('pgsql_admin')->table('admin_user_module_mst')->where('id', $moduleId)->first()
            : null;

        $data = [
            'title' => 'Role permission List',
            'pageTitle' => 'Role permission List',
            'li_1' => 'Admin',
            'li_2' => 'Role permission List',
            'id' => $moduleId,
            'parent_module' => $parentModule,
            'module' => DB::connection('pgsql_admin')->table('admin_user_module')
                ->where('mst_id', $moduleId)
                ->orderBy('id')
                ->get()
                ->toArray(),
        ];

        return view('admin.rolepermission_table', $data);
    }
    public function rolepermission_validation()
    {
        $validator = Validator::make(
            $this->request->all(),
            [
                'modules' => 'required',
                'permissions' => 'required',
                'user_status' => 'required',
                'mst_id' => 'required'
            ]
        );

        if ($validator->fails()) {
            $error = $validator->errors();
            return response()->json([
                "error" => true,
                "mst_id" => $error->first('mst_id'),
                "modules" => $error->first('modules'),
                "permissions" => $error->first('permissions'),
                "user_status" => $error->first('user_status')
            ]);
        }

        $id = trim((string) ($this->request['id'] ?? ''));
        $array = [
            'mst_id' => $this->request['mst_id'],
            'modules' => $this->request['modules'],
            'permissions' => $this->request['permissions'],
            'status' => $this->request['user_status'],
        ];

        try {
            if ($id !== '' && $id !== '0') {
                $array['updated_at'] = date('Y-m-d H:i:s');
                DB::connection('pgsql_admin')->table('admin_user_module')->where('id', (int) $id)->update($array);
            } else {
                $array['created_at'] = date('Y-m-d H:i:s');
                $array['updated_at'] = date('Y-m-d H:i:s');
                DB::connection('pgsql_admin')->table('admin_user_module')->insert($array);
            }

            return response()->json([
                "success" => "Role Permission Saved Successfully."
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                "error" => true,
                "modules" => "Unable to save role permission. " . $e->getMessage(),
            ], 500);
        }
    }

    public function delete_rolepermission()
    {
        $id = (int) ($this->request['id'] ?? 0);
        if ($id <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid role permission id.',
            ], 422);
        }

        DB::connection('pgsql_admin')->table('admin_user_module')->where('id', $id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role permission deleted successfully.',
        ]);
    }

    /* Start Admin branch */
    // public function admin_branch_table()
    // {
    //     session(['menu' => 'Admin']);
    //     $data = [
    //         "title" => "Admin branch",
    //         "pageTitle" => "Admin branch", "li_1" => "Admin", "li_2" => "Admin branch"
    //     ];
    //     // $data["result"] = $this->adminbranch->orderBy('id', 'ASC')->get()->toArray();
    //     $data['result'] = DB::table('admin_branch')->select('admin_branch.*', 'location_mst.location_name as countryname')->join('location_mst', 'admin_branch.country', '=', 'location_mst.location_id')->get()->toArray();
    //     // print_r($data['cname']);die;
    //     return view("admin.admin_branch_table", $data);
    // }
    public function get_state()
    {

        $country = $this->request['id'];
        $data = $this->state->orderBy('state', 'ASC')->get()->toArray();


        $html = '<option></option>';
        foreach ($data as $row) {
            $html .= '<option  data-id="' . $row->state . '" value="' . $row->id . '">' . $row->state . '</option>';
        }
        $json = ['html' => $html];
        return response()->json($json);
    }
    public function add_admin_branch($id = "")
    {
        session(['menu' => 'Admin']);
        $title = "Add Admin branch";
        if ($id != "") {
            $title = "Update Admin branch";
            $this->master->check_permission(25, 'Edit');
        }
        else {
            $this->master->check_permission(25, 'Add');
        }
        $data = [
            "title" => $title,
            "pageTitle" => $title, "li_1" => "Admin", "li_2" => $title
        ];
        $data['country'] = $this->master->other_country();
        $data['state'] = $this->master->state();
        if ($id != "") {
            $data["update"] = $this->adminbranch->where("id", $id)->first();
        }
        return view("admin.add_admin_branch", $data);
    }

    public function delete_admin_branch()
    {
        $id = $this->request["id"];
        if (permission(25, 'Delete')) {
            $this->usercreation->where('id', $id)->delete();
        }
        $this->adminbranch->where("id", $id)->delete();
    }

    public function admin_branch_validation()
    {
        if ($this->request['country'] == 1) {
            $stateReq = "nullable";
            $state_idReq = "required";
        }
        else {
            $stateReq = "required";
            $state_idReq = "nullable";
        }
        $validator = Validator::make(
            $this->request->all(),
        [
            "branch_name" => "required",
            "country" => "required",
            "state_name" => $stateReq,
            "state_id" => $state_idReq,
            "status" => "required",
        ], [],
        [
            "state_id" => "state"
        ]
        );
        if ($validator->fails()) {
            $error = $validator->errors();
            $json = array(
                "error" => true,
                "branch_name" => $error->first("branch_name"),
                "country" => $error->first("country"),
                "state_id" => $error->first("state_id"),
                "city" => $error->first("city"),
                "state_name" => $error->first("state_name"),
                "status" => $error->first("status"),
            );
        }
        else {

            $array = [
                "id" => $this->request["id"],
                "branch_name" => $this->request["branch_name"],
                "country" => $this->request["country"],
                "state_id" => $this->request["state_id"],
                "state_name" => $this->request["state_name"],
                "city" => $this->request["city"],
                "status" => $this->request["status"],
            ];
            // print_r($array);
            // die;
            if ($this->request["id"] == "") {
                $array["created_at"] = date("Y-m-d H:i:s");
                $this->adminbranch->insert($array);
            }
            else {
                $array["updated_at"] = date("Y-m-d H:i:s");
                $this->adminbranch->where("id", $this->request["id"])->update($array);
            }

            $json = array(
                "success" => "Admin branch Saved Successfully."
            );
        }
        return response()->json($json);
    }
    /* End Admin branch */

    /* Start Module mst */
    public function module_mst_table()
    {
        session(['menu' => 'Admin']);
        $data = [
            "title" => "Module Master",
            "pageTitle" => "Module Master", "li_1" => "Admin", "li_2" => "Module Master"
        ];
        $this->master->check_permission(4, 'View');
        $data["result"] = DB::connection('pgsql_admin')->table("admin_user_module_mst")->orderBy('id')->get()->toArray();
        return view("admin.module_mst_table", $data);
    }

    public function add_module_mst($id = "")
    {
        session(['menu' => 'Admin']);
        $title = "Add Module Master";
        if ($id != "") {
            $title = "Update Module Master";
            $this->master->check_permission(4, 'Edit');
        }
        else {
            $this->master->check_permission(4, 'Add');
        }

        $data = [
            "title" => $title,
            "pageTitle" => $title, "li_1" => "Admin", "li_2" => $title
        ];
        if ($id != "") {
            $data["update"] = DB::connection('pgsql_admin')->table("admin_user_module_mst")->where("id", $id)->first();
        }
        return view("admin.add_module_mst", $data);
    }

    public function delete_module_mst()
    {
        $id = (int) ($this->request["id"] ?? 0);
        if ($id <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid module id.',
            ], 422);
        }

        if (function_exists('permission') && !permission(4, 'Delete')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete modules.',
            ], 403);
        }

        DB::connection('pgsql_admin')->table("admin_user_module_mst")->where("id", $id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Module deleted successfully.',
        ]);
    }

    public function module_mst_validation()
    {
        $validator = Validator::make(
            $this->request->all(),
            [
                "module_name" => "required|string|max:255",
            ]
        );
        if ($validator->fails()) {
            $error = $validator->errors();
            return response()->json([
                "error" => true,
                "module_name" => $error->first("module_name"),
            ]);
        }

        $id = trim((string) ($this->request["id"] ?? ''));
        $moduleName = trim((string) $this->request["module_name"]);
        $now = date("Y-m-d H:i:s");

        // Never insert empty id — auto-increment handles it (empty id breaks PG/MySQL).
        $array = [
            "module_name" => $moduleName,
            "status" => 1,
        ];

        try {
            if ($id === "" || $id === "0") {
                $array["created_at"] = $now;
                $array["updated_at"] = $now;
                DB::connection('pgsql_admin')->table("admin_user_module_mst")->insert($array);
            } else {
                $array["updated_at"] = $now;
                DB::connection('pgsql_admin')->table("admin_user_module_mst")->where("id", (int) $id)->update($array);
            }

            return response()->json([
                "success" => "Module Master Saved Successfully."
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                "error" => true,
                "module_name" => "Unable to save module. " . $e->getMessage(),
            ], 500);
        }
    }
    /* End Module mst */


    /* Start Admin settings */

    public function add_admin_settings()
    {
        session(['menu' => 'Admin']);
        // print_r($id);die;
        $title = "Add Admin settings";
        $this->master->check_permission(24, 'View');
        $data = [
            "title" => $title,
            "pageTitle" => $title, "li_1" => "Admin", "li_2" => $title
        ];
        $data["update"] = $this->adminsettings->where("id", 1)->first();
        $data["access"] = $this->emailaccess->get()->toArray();
        $data['yearlist'] = $this->master->financialyear();
        return view("admin.settings", $data);
    }

    public function general_sett_validation()
    {
        $validator = Validator::make(
            $this->request->all(),
        [
            "address" => "required",
            "grid_rec" => "required",
            "credit_manag" => "required",
            "round_up" => "required",
            "credit_block" => "required",
        ]
        );
        if ($validator->fails()) {
            $error = $validator->errors();
            $json = array(
                "error" => true,
                "address" => $error->first("address"),
                "grid_rec" => $error->first("grid_rec"),
                "credit_manag" => $error->first("credit_manag"),
                "round_up" => $error->first("round_up"),
                "credit_block" => $error->first("credit_block"),
            );
        }
        else {

            $array = [

                "address" => $this->request["address"],
                "pincode" => $this->request["pincode"],
                "gst_no" => $this->request["gst_no"],
                "custom_agn_code" => $this->request["custom_agn_code"],
                "sms_start" => date('H:i', strtotime($this->request["sms_start"])),
                "grid_rec" => $this->request["grid_rec"],
                "contact" => $this->request["contact"],
                "email" => $this->request["email"],
                "credit_manag" => $this->request["credit_manag"],
                "air_export" => $this->request["air_export"],
                "sea_export" => $this->request["sea_export"],
                "do_pref" => $this->request["do_pref"],
                "exp_adv" => $this->request["exp_adv"],
                "hawb_prefix" => $this->request["hawb_prefix"],
                "soa_prefix" => $this->request["soa_prefix"],
                "payv_prefix" => $this->request["payv_prefix"],
                "cin_no" => $this->request["cin_no"],
                "iata_code" => $this->request["iata_code"],
                "sms_end" => date('H:i', strtotime($this->request["sms_end"])),
                "thumb_hard" => $this->request["thumb_hard"],
                "pan_no" => $this->request["pan_no"],
                "round_up" => $this->request["round_up"],
                "credit_block" => $this->request["credit_block"],
                "airimp_prifix" => $this->request["airimp_prifix"],
                "seaimp_prefix" => $this->request["seaimp_prefix"],
                "credit_prifix" => $this->request["credit_prifix"],
                "book_prefix" => $this->request["book_prefix"],
                "debit_prefix" => $this->request["debit_prefix"],
                "other_prefix" => $this->request["other_prefix"],
                "reciept_prefix" => $this->request["reciept_prefix"],
            ];

            // print_r($array);die;
            $this->adminsettings->where("id", 1)->update($array);

            $json = array(
                "success" => "Admin settings Saved Successfully."
            );
        }
        return response()->json($json);
    }
    public function salary_sett_validation()
    {
        $validator = Validator::make(
            $this->request->all(),
        [
            "emp_dw_hr" => "required",
            "esi" => "required",
            "pf" => "required",
            "selc_ins" => "required",
            "medic_exp" => "required",
            "home_loan" => "required",
            "conv_allow" => "required",
            "present_day" => "required"
        ]
        );
        if ($validator->fails()) {
            $error = $validator->errors();
            $json = array(
                "error" => true,
                "emp_dw_hr" => $error->first("emp_dw_hr"),
                "esi" => $error->first("esi"),
                "pf" => $error->first("pf"),
                "selc_ins" => $error->first("selc_ins"),
                "medic_exp" => $error->first("medic_exp"),
                "home_loan" => $error->first("home_loan"),
                "conv_allow" => $error->first("conv_allow"),
                "present_day" => $error->first("present_day")
            );
        }
        else {
            $array = [
                "id" => $this->request["id"],
                "emp_dw_hr" => $this->request["emp_dw_hr"],
                "esi" => $this->request["esi"],
                "pf" => $this->request["pf"],
                "selc_ins" => $this->request["selc_ins"],
                "medic_exp" => $this->request["medic_exp"],
                "mediclam_limit" => $this->request["mediclam_limit"],
                "esi_limit" => $this->request["esi_limit"],
                "tds" => $this->request["tds"],
                "home_loan" => $this->request["home_loan"],
                "conv_allow" => $this->request["conv_allow"],
                "present_day" => $this->request["present_day"],
                "emp_hr_asign" => $this->request["emp_hr_asign"],
                "esi_assign" => $this->request["esi_assign"],
                "pf_assign" => $this->request["pf_assign"],
                "tds" => $this->request["tds"],
            ];

            $array["updated_at"] = date("Y-m-d H:i:s");
            $this->adminsettings->where("id", 1)->update($array);


            $json = array(
                "success" => "Admin settings Saved Successfully."
            );
        }
        return response()->json($json);
    }
    public function apisett_validation()
    {
        $array = [
            "id" => $this->request["id"],
            "prom_url" => $this->request["prom_url"],
            "tranc_url" => $this->request["tranc_url"],
            "token_url" => $this->request["token_url"],
            "client_id" => $this->request["client_id"],
            "cancel_url" => $this->request["cancel_url"],
            "username" => $this->request["username"],
            "email_id" => $this->request["email_id"],
            "email_pass" => $this->request["email_pass"],
            "smpt_name" => $this->request["smpt_name"],
            "smpt_port" => $this->request["smpt_port"],
            "api_ssl" => $this->request["api_ssl"],
            "irn_url" => $this->request["irn_url"],
            "secret_key" => $this->request["secret_key"],
            "password" => $this->request["password"],
        ];
        $array["updated_at"] = date("Y-m-d H:i:s");
        $this->adminsettings->where("id", 1)->update($array);
        $json = array(
            "success" => "Admin settings Saved Successfully."
        );
        return response()->json($json);
    }
    public function logo_validation()
    {
        $logo = $this->master->upload_img($this->request->file('logo'), $this->request['logo_file'], 'images');
        $logotr = $this->master->upload_img($this->request->file('logotr'), $this->request['logotr_file'], 'images');
        $favicon = $this->master->upload_img($this->request->file('favicon'), $this->request['favicon_file'], 'images');
        $array = [
            "id" => $this->request["id"],
            "logo" => $logo,
            "logotr" => $logotr,
            "favicon" => $favicon
        ];

        if ($this->request["id"] == "") {
            $array["created_at"] = date("Y-m-d H:i:s");
            $this->adminsettings->insert($array);
        }
        else {
            $array["updated_at"] = date("Y-m-d H:i:s");
            $this->adminsettings->where("id", 1)->update($array);
        }

        $json = array(
            "success" => "Admin settings Saved Successfully."
        );

        return response()->json($json);
    }
    public function email_validation()
    {

        $array = [
            "id" => $this->request["id"],
            "outs_email" => $this->request["outs_email"],
            "emailday_one" => $this->request["emailday_one"],
            "open_jobcard" => $this->request["open_jobcard"],
            "emailday_two" => $this->request["emailday_two"],
        ];
        $this->adminsettings->where("id", 1)->update($array);


        $json = array(
            "success" => "Admin settings Saved Successfully."
        );

        return response()->json($json);
    }
    public function custom_validation()
    {
        $array = [
            "id" => $this->request["id"],
            "seaimp_sender" => $this->request["seaimp_sender"],
            "seaexp_sender" => $this->request["seaexp_sender"],
            "seaimp_rec" => $this->request["seaimp_rec"],
            "seaexp_rec" => $this->request["seaexp_rec"],
        ];
        $this->adminsettings->where("id", 1)->update($array);
        $json = array(
            "success" => "Admin settings Saved Successfully."
        );
        return response()->json($json);
    }
    public function signature_validation()
    {
        $array = [
            "id" => $this->request["id"],
            "email_sign" => $this->request["email_sign"],
        ];

        if ($this->request["id"] == "") {
            $array["created_at"] = date("Y-m-d H:i:s");
            $this->adminsettings->insert($array);
        }
        else {
            $array["updated_at"] = date("Y-m-d H:i:s");
            $this->adminsettings->where("id", 1)->update($array);
        }

        $json = array(
            "success" => "Admin settings Saved Successfully."
        );

        return response()->json($json);
    }
    public function financial_year_validation()
    {
        $array = [
            "id" => $this->request["id"],
            "fin_year" => $this->request["fin_year"],
        ];

        if ($this->request["id"] == "") {
            $array["created_at"] = date("Y-m-d H:i:s");
            $this->adminsettings->insert($array);
        }
        else {
            $array["updated_at"] = date("Y-m-d H:i:s");
            $this->adminsettings->where("id", 1)->update($array);
        }

        $json = array(
            "success" => "Admin settings Saved Successfully."
        );

        return response()->json($json);
    }

    public function sendmail()
    {
        // echo 'supriya';die();
        $mailData = [
            "name" => "Test NAME",
            "dob" => "12/12/2000"
        ];
        $email = Mail::to("test@gmail.com")->send(new TestEmail($mailData));
        echo "<pre>";
        print_r($email);
        echo "</pre>";

        dd("Mail Sent Successfully!");
    }

/* End Admin settings */

    public function send_sms($request){
        $validator = Validator::make($request->all(), [
                'email' => 'required|email'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json(['status' => 'Error', 'message' => 'User not found'], 404);
            }

            if ($user->email_verified != 1) {
                return response()->json(['status' => 'Error', 'message' => 'User email not verified'], 400);
            }
            
            date_default_timezone_set('Asia/Kolkata');
            // Generate password
            $plainPassword = Str::password(12, true, true, true, false);

            // Update user
            $user->update([
                'password' => Hash::make($plainPassword),
                'status' => 1,
                'approved_by' => 1,
                'approved_at' => date('Y-m-d H:i:s'),
            ]);

            Mail::to($user->email)->send(
                new SendApprovalMail($user, 'Your request has been approved successfully.',$plainPassword)
            );
            return response()->json(['status' => 'Success', 'message' => 'Successfully']);
    }

}
