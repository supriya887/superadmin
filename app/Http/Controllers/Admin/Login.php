<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cookie;

class Login extends Controller
{
    protected $request;
    protected $admin;
    protected $usersession;
    protected $adminbranch;
    protected $finyear;
    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->admin = DB::table('admin_user_creation');
        $this->usersession = DB::table('admin_user_session');
        $this->adminbranch = DB::table('admin_branch');
        $this->finyear = DB::table('other_financial_year');
    }

    public function login()
    {
        // echo 'dd';
        $data['branchlist'] = $this->adminbranch->get()->toArray();
        return view('admin.login',$data);
    }

    public function login_validation(Request $request)
    {
        $validator = Validator::make(
            $request->All(),
            [
                'username' => 'required',
                'password' => 'required',
                'branch' => 'required'
            ]
        );
        if ($validator->fails()) {
            $error = $validator->errors();
            $json = array(
                "error" => true,
                "username" => $error->first('username'),
                "password" => $error->first('password'),
                "branch" => $error->first('branch'),
            );
        } else {
            // $users = new users;
            $array = [
                'user_name' => $request->username,
                'password' => $request->password,
                'status' => 1
            ];

            // $SessionNumber = rand(1231,7879);

            // $check = $this->admin->where($array)->first();

            $user = $this->admin->where($array)->first();
            $branch = "";
            if (!empty($user)) {
                if($user->branch_id!=''){
                    $branchIds = explode(',',$user->branch_id);
                    $branch = in_array($request->branch, $branchIds);

                    if (in_array(1, $branchIds)) {
                        $hoStatus = 1;
                    }else {
                        $hoStatus = 0;
                        
                    }
                } 
                if ($branch !="") {
                    
                // else{

                // }
                if ($request->remember) {
                    Cookie::queue('adminUsername', $request->username, 1209600);
                    Cookie::queue('adminPassword', $request->password, 1209600);
                    Cookie::queue('branch', $request->branch, 1209600);
                    Cookie::queue('remember', '1', 1209600);
                }
                $Session = [
                    'user_id' => $user->id,
                    'logged_in' => date('Y-m-d H:i:s'),
                    'ip_address' => $request->getClientIp(),
                ];
                $this->usersession->insert($Session);
                $InserId =  DB::getPdo()->lastInsertId();
                $finyear = DB::table('other_financial_year')->select('id')->where('status',1)->first();
                // $Session = ['session_id' => $InserId,];
                // $this->usersession->where('id', $InserId)->update($Session);

                session(['userId' => $user->id]);
                session(['userName' => $user->user_name]);
                session(['userRole' => 'Admin']);
                // session(['passWord' => $user->password]);
                session(['roleId' => $user->role]);
                session(['sessionId' => $InserId]);
                session(['branchId' => $request->branch]);
                session(['companyId' => 1]);
                session(['hostatus' => $hoStatus]);
                session(['finyearId' => $finyear->id]);
                $json = array(
                    "success" => "Login Successfully."
                );
            }else {
                $json = array(
                    'success' => false,
                    'error' => true,
                    'message' => 'You Dont Have Permission To This Branch.',
                    'data' => null
                );
            }

            } else {
                $json = array(
                    'success' => false,
                    'error' => true,
                    'message' => 'Please Enter Valid User ID and Password and Branch.',
                    'data' => null
                );
            }
        }
        return response()->json($json);
    }
    public function change_password()
    {
        $title = 'Change Password';
        $data = [
            'title' =>  $title,
            'pageTitle' => $title, 'li_1' => 'Admin', 'li_2' => $title
        ];
        return view('admin.change_password', $data);
    }
    public function changepassword_validation()
    {
        // $id = Session::get('loginId');
        // print_r(Session::All());die;

        $data = Session::All();
        $users = $this->admin->where('id', $data['userId'])->first();
        $password = $this->request->password;
        $validator = Validator::make(
            $this->request->all(),
            [
                'password' =>  'required',
                'new_password' => [
                    'required', Rule::unique('admin_user_creation', 'password'),
                ],
                'confirm_password' => 'required|same:new_password',
            ]
        );


        if ($validator->fails()) {
            $error = $validator->errors();

            $json = array(
                "error" => true,
                "password" =>  $error->first('password'),
                "new_password" =>  $error->first('new_password'),
                "confirm_password" =>  $error->first('confirm_password')
            );

            if ($this->request->password != $users->password || $this->request->password != null) {
                // echo 'hii';die;
                $json['password'] = 'Currunt  Password Does Not Match';
            }
        } else {
            $array =  array(
                "password" => $this->request->new_password,
            );
            $this->admin->where('id', $data['userId'])->update($array);
            $json = array(
                "success" => "Password Change Successfully."
            );
        }
        return response()->json($json);
    }
}
