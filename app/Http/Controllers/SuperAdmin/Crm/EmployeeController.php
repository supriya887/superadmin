<?php

namespace App\Http\Controllers\SuperAdmin\Crm;

use App\Http\Controllers\Controller;
use App\Models\CrmUser;
use App\Models\CrmEmployeePermission;
use App\Models\Lead;
use App\Models\Quotation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    private const PERMISSION_ACTIONS = ['view', 'add', 'edit', 'delete'];

    private function permissionModules(): array
    {
        return [
            'leads' => ['label' => 'Leads', 'items' => [
                'leads' => 'Leads',
                'jd_leads' => 'JD Leads',
                'exhibition_leads' => 'Exhibition Leads',
                'import_leads' => 'Import Leads',
            ]],
            'follow_up' => ['label' => 'Follow Up', 'items' => [
                'follow_ups' => 'Follow Ups',
                'meetings' => 'Meetings',
                'tasks_reminders' => 'Tasks / Reminders',
            ]],
            'quotations' => ['label' => 'Quotations', 'items' => [
                'quotations' => 'Quotations',
                'discount_approval' => 'Discount Approval',
                'create_quotations' => 'Create Quotations',
                'update_status_of_invoice_and_quotation' => 'Update Status of Invoice and Quotation',
            ]],
            'renewals' => ['label' => 'Renewals', 'items' => [
                'renewals' => 'Renewals',
                'renewal_reminders' => 'Renewal Reminders',
            ]],
            'manage' => ['label' => 'Manage', 'items' => [
                'employees' => 'Employees',
                'team_assignment' => 'Team Assignment',
                'employee_permissions' => 'Employee Permissions',
                'services_providers' => 'Services & Providers',
                'crm_settings' => 'CRM Settings',
            ]],
        ];
    }

    private function permissionMatrix(array $values = []): array
    {
        $permissions = [];
        foreach ($this->permissionModules() as $moduleKey => $module) {
            foreach ($module['items'] as $itemKey => $itemLabel) {
                foreach (self::PERMISSION_ACTIONS as $action) {
                    $permissions[$moduleKey][$itemKey][$action] =
                        (bool) ($values[$moduleKey][$itemKey][$action] ?? false);
                }
            }
        }

        return $permissions;
    }

    public function permissions(Request $request)
    {
        $employees = CrmUser::query()->orderBy('name')->get([
            'id', 'name', 'email', 'role', 'avatar', 'profile_image',
        ]);
        $roles = $employees->pluck('role')->filter()->unique()->sort()->values();
        $selectedEmployee = $employees->firstWhere('id', (int) $request->query('employee_id'));
        $selectedRole = $selectedEmployee?->role ?? $request->query('role');

        if (!$roles->contains($selectedRole)) {
            $selectedEmployee = null;
            $selectedRole = $roles->first();
        }

        $modules = $this->permissionModules();
        $savedPermissions = [];
        if ($selectedRole) {
            $savedPermissions = CrmEmployeePermission::query()
                ->where('role', $selectedRole)
                ->first()?->permissions ?? [];
        }

        $rolePermissions = $this->permissionMatrix($savedPermissions);
        $employeeOverride = $selectedEmployee
            ? ($savedPermissions['_employee_overrides'][(string) $selectedEmployee->id] ?? null)
            : null;
        $hasEmployeeOverride = is_array($employeeOverride);
        $permissions = $hasEmployeeOverride
            ? $this->permissionMatrix(array_replace_recursive($rolePermissions, $employeeOverride))
            : $rolePermissions;

        return view('superadmin.crm.employee.permissions', compact(
            'roles',
            'employees',
            'selectedRole',
            'selectedEmployee',
            'hasEmployeeOverride',
            'modules',
            'permissions'
        ));
    }

    public function savePermissions(Request $request)
    {
        $validated = $request->validate([
            'role' => 'required_without:employee_id|nullable|string|max:100|exists:pgsql_crm.crm_users,role',
            'employee_id' => 'nullable|integer|exists:pgsql_crm.crm_users,id',
            'permissions' => 'nullable|array',
            'reset_employee_override' => 'nullable|in:1',
        ]);

        $employee = !empty($validated['employee_id'])
            ? CrmUser::findOrFail($validated['employee_id'])
            : null;
        $role = $employee?->role ?? $validated['role'];
        $permissionRecord = CrmEmployeePermission::query()->firstOrNew(['role' => $role]);
        $storedPermissions = $permissionRecord->permissions ?? [];

        if ($employee) {
            $employeeKey = (string) $employee->id;
            if ($request->boolean('reset_employee_override')) {
                unset($storedPermissions['_employee_overrides'][$employeeKey]);
                if (empty($storedPermissions['_employee_overrides'])) {
                    unset($storedPermissions['_employee_overrides']);
                }
                $message = 'Employee now uses role default permissions.';
            } else {
                $storedPermissions['_employee_overrides'][$employeeKey] =
                    $this->permissionMatrix($validated['permissions'] ?? []);
                $message = 'Employee permissions saved.';
            }
        } else {
            $storedPermissions = array_replace(
                $storedPermissions,
                $this->permissionMatrix($validated['permissions'] ?? [])
            );
            $message = 'Role permissions saved.';
        }

        $permissionRecord->permissions = $storedPermissions;
        $permissionRecord->save();

        $routeParameters = ['role' => $role];
        if ($employee) {
            $routeParameters['employee_id'] = $employee->id;
        }

        return redirect()->route('crm.employee.permissions', [
            ...$routeParameters,
        ])->with('success', $message);
    }

    /**
     * Display a listing of employees.
     */
    public function index(Request $request)
    {
        try {
            $query = CrmUser::query();

            if ($request->filled('search')) {
                $search = trim($request->search);
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%")
                        ->orWhere('mobile', 'ilike', "%{$search}%")
                        ->orWhere('role', 'ilike', "%{$search}%");
                });
            }

            if ($request->filled('status')) {
                $query->whereRaw('LOWER(status) = ?', [strtolower($request->status)]);
            }

            if ($request->filled('role')) {
                $query->whereRaw('LOWER(role) = ?', [strtolower($request->role)]);
            }

            if ($request->filled('from')) {
                $query->whereDate('created_at', '>=', $request->from);
            }

            if ($request->filled('to')) {
                $query->whereDate('created_at', '<=', $request->to);
            }

        $employees = $query->latest('id')->paginate(10)->withQueryString();

        $employees->getCollection()->transform(function ($emp) {
            try {
                $emp->leads_count = Lead::query()->forEmployee($emp)->count();
            } catch (\Throwable $e) {
                $emp->leads_count = 0;
            }
            return $emp;
        });

        $totalEmployees = CrmUser::count();
        $activeEmployees = CrmUser::where('status', 'active')->count();
        } catch (\Exception $e) {
            $employees = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10);
            $totalEmployees = 0;
            $activeEmployees = 0;
        }

        // Count assigned leads dynamically from leads table
        $totalLeadsAssigned = 0;
        try {
            $totalLeadsAssigned = DB::connection('pgsql_crm')->table('leads')->whereNotNull('assigned_to')->count();
        } catch (\Throwable $e) {
            $totalLeadsAssigned = 0;
        }

        // Count pending tasks / followups dynamically from leads
        $pendingTasks = 0;
        try {
            $pendingTasks = DB::connection('pgsql_crm')->table('leads')->whereIn('status', ['new', 'in_progress', 'pending'])->count();
        } catch (\Throwable $e) {
            $pendingTasks = 0;
        }

        $summaryDate = Carbon::now()->format('d M, Y');

        return view('superadmin.crm.employee.index', compact(
            'employees',
            'totalEmployees',
            'activeEmployees',
            'totalLeadsAssigned',
            'pendingTasks',
            'summaryDate'
        ));
    }

    /**
     * Show the form for creating a new employee.
     */
    public function create()
    {
        $roles = [
            'super_admin' => 'Super Admin',
            'admin' => 'Admin (Admin)',
            'manager' => 'Manager',
            'executive' => 'Sales Executive',
            'marketing_manager' => 'Marketing Manager',
            'product_designer' => 'Product Designer',
            'customer_support' => 'Customer Support',
        ];

        $managers = CrmUser::pluck('name')->toArray() ?: [
            'Wilson (Admin)',
            'Mittal Solanki',
            'Janak Singh'
        ];

        return view('superadmin.crm.employee.create', compact('roles', 'managers'));
    }

    /**
     * Store a newly created employee in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:pgsql_crm.crm_users,email',
            'mobile' => 'nullable|string|max:20',
            'role' => 'nullable|string|max:100',
            'status' => 'nullable|string',
            'password' => 'required|string|min:6',
            'manager' => 'nullable|string|max:255',
            'max_quot_discount' => 'nullable|string|max:100',
            'jd_lead_assign' => 'nullable|in:yes,no',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:5120',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:5120',
        ]);

        if ($request->hasFile('avatar') || $request->hasFile('profile_image')) {
            $avatarFile = $request->file('avatar') ?? $request->file('profile_image');
            if ($avatarFile && $avatarFile->isValid()) {
                $filename = 'emp_' . time() . '_' . uniqid() . '.' . $avatarFile->getClientOriginalExtension();
                $avatarFile->storeAs('crm/employees', $filename, 'public');
                try {
                    $destDir = public_path('storage/crm/employees');
                    if (!file_exists($destDir)) {
                        @mkdir($destDir, 0777, true);
                    }
                    if (!file_exists($destDir . '/' . $filename)) {
                        @copy($avatarFile->getRealPath(), $destDir . '/' . $filename);
                    }
                } catch (\Throwable $e) {
                    // Ignore
                }
                $validated['avatar'] = 'crm/employees/' . $filename;
                $validated['profile_image'] = 'crm/employees/' . $filename;
            }
        }

        $roleMap = [
            'super_admin' => 'super_admin',
            'super admin' => 'super_admin',
            'admin' => 'admin',
            'admin (admin)' => 'admin',
            'manager' => 'manager',
            'executive' => 'executive',
            'sales executive' => 'executive',
            'marketing_manager' => 'marketing_manager',
            'marketing manager' => 'marketing_manager',
            'product_designer' => 'product_designer',
            'product designer' => 'product_designer',
            'customer_support' => 'customer_support',
            'customer support' => 'customer_support',
        ];

        $rawRole = strtolower(trim($request->input('role', 'executive')));
        $validated['role'] = $roleMap[$rawRole] ?? $rawRole;

        $rawStatus = strtolower(trim($request->input('status', 'active')));
        $validated['status'] = in_array($rawStatus, ['active', 'inactive', 'suspended']) ? $rawStatus : 'active';
        $validated['jd_lead_assign'] = strtolower((string) ($validated['jd_lead_assign'] ?? 'no')) === 'yes' ? 'yes' : 'no';

        $validated['password'] = Hash::make($validated['password']);

        try {
            CrmUser::create($validated);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'crm_users_role_check')) {
                // Fallback to 'admin' or 'user' if PostgreSQL check constraint still restricts values
                $validated['role'] = in_array($validated['role'], ['super_admin', 'admin', 'manager']) ? 'admin' : 'user';
                CrmUser::create($validated);
            } else {
                throw $e;
            }
        }

        return redirect()->route('crm.employees')->with('success', 'Employee created successfully.');
    }

    /**
     * Display the specified employee.
     */
    public function show($id)
    {
        $employee = CrmUser::findOrFail($id);

        $employeeLeads = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10);
        $totalLeads = 0;
        $totalCalls = 0;
        $totalDeals = 0;

        try {
            $employeeLeads = Lead::query()->forEmployee($employee)->latest()->paginate(10);
            $totalLeads = $employeeLeads->total();
            $totalCalls = Lead::query()->forEmployee($employee)->whereNotNull('follow_up_date')->count();

            $leadIds = Lead::query()->forEmployee($employee)->pluck('id');
            $totalDeals = Quotation::query()
                ->where(function ($q) use ($employee, $leadIds) {
                    $q->whereIn('assigned_to', Lead::assigneeKeys($employee));
                    if ($leadIds->isNotEmpty()) {
                        $q->orWhereIn('lead_id', $leadIds);
                    }
                })
                ->count();
        } catch (\Throwable $e) {
            // ignore
        }

        return view('superadmin.crm.employee.view', compact(
            'employee',
            'employeeLeads',
            'totalLeads',
            'totalCalls',
            'totalDeals'
        ));
    }

    /**
     * Show the form for editing the specified employee.
     */
    public function edit($id = null)
    {
        $employee = null;

        if ($id && $id !== 'edit') {
            $cleanId = is_numeric($id) ? (int) $id : (int) preg_replace('/\D/', '', (string) $id);
            if ($cleanId > 0) {
                $employee = CrmUser::find($cleanId);
            }
        }

        if (!$employee) {
            $employee = CrmUser::first();
        }

        if (!$employee) {
            return redirect()->route('crm.employee.create');
        }

        $roles = [
            'super_admin' => 'Super Admin',
            'admin' => 'Admin (Admin)',
            'manager' => 'Manager',
            'executive' => 'Sales Executive',
            'marketing_manager' => 'Marketing Manager',
            'product_designer' => 'Product Designer',
            'customer_support' => 'Customer Support',
        ];

        $managers = CrmUser::pluck('name')->toArray() ?: [
            'Wilson (Admin)',
            'Mittal Solanki',
            'Janak Singh'
        ];

        return view('superadmin.crm.employee.create', compact('employee', 'roles', 'managers'));
    }

    /**
     * Update the specified employee in storage.
     */
    public function update(Request $request, $id)
    {
        $employee = CrmUser::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:pgsql_crm.crm_users,email,' . $id,
            'mobile' => 'nullable|string|max:20',
            'role' => 'nullable|string|max:100',
            'status' => 'nullable|string',
            'manager' => 'nullable|string|max:255',
            'max_quot_discount' => 'nullable|string|max:100',
            'jd_lead_assign' => 'nullable|in:yes,no',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:5120',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:5120',
        ]);

        if ($request->hasFile('avatar') || $request->hasFile('profile_image')) {
            $avatarFile = $request->file('avatar') ?? $request->file('profile_image');
            if ($avatarFile && $avatarFile->isValid()) {
                $filename = 'emp_' . time() . '_' . uniqid() . '.' . $avatarFile->getClientOriginalExtension();
                $avatarFile->storeAs('crm/employees', $filename, 'public');
                try {
                    $destDir = public_path('storage/crm/employees');
                    if (!file_exists($destDir)) {
                        @mkdir($destDir, 0777, true);
                    }
                    if (!file_exists($destDir . '/' . $filename)) {
                        @copy($avatarFile->getRealPath(), $destDir . '/' . $filename);
                    }
                } catch (\Throwable $e) {
                    // Ignore
                }
                $validated['avatar'] = 'crm/employees/' . $filename;
                $validated['profile_image'] = 'crm/employees/' . $filename;
            }
        }

        $roleMap = [
            'super_admin' => 'super_admin',
            'super admin' => 'super_admin',
            'admin' => 'admin',
            'admin (admin)' => 'admin',
            'manager' => 'manager',
            'executive' => 'executive',
            'sales executive' => 'executive',
            'marketing_manager' => 'marketing_manager',
            'marketing manager' => 'marketing_manager',
            'product_designer' => 'product_designer',
            'product designer' => 'product_designer',
            'customer_support' => 'customer_support',
            'customer support' => 'customer_support',
        ];

        $rawRole = strtolower(trim($request->input('role', $employee->role ?? 'executive')));
        $validated['role'] = $roleMap[$rawRole] ?? $rawRole;

        $rawStatus = strtolower(trim($request->input('status', $employee->status ?? 'active')));
        $validated['status'] = in_array($rawStatus, ['active', 'inactive', 'suspended']) ? $rawStatus : 'active';
        $validated['jd_lead_assign'] = strtolower((string) ($validated['jd_lead_assign'] ?? 'no')) === 'yes' ? 'yes' : 'no';

        if ($request->filled('password')) {
            $validated['password'] = Hash::make($request->password);
        }

        try {
            $employee->update($validated);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'crm_users_role_check')) {
                $validated['role'] = in_array($validated['role'], ['super_admin', 'admin', 'manager']) ? 'admin' : 'user';
                $employee->update($validated);
            } else {
                throw $e;
            }
        }

        return redirect()->route('crm.employees')->with('success', 'Employee updated successfully.');
    }

    /**
     * Remove the specified employee from storage.
     */
    public function destroy($id)
    {
        $employee = CrmUser::findOrFail($id);
        $employee->delete();

        return redirect()->route('crm.employees')->with('success', 'Employee deleted successfully.');
    }
    public function attindex(Request $request)
    {
        $query = CrmUser::query();

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('role', 'ilike', "%{$search}%")
                    ->orWhere('ip_address', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }
        if ($request->filled('employee_id')) {
            $query->whereKey($request->input('employee_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('from')) {
            $query->whereDate('last_login_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('last_login_at', '<=', $request->input('to'));
        }

        $employees = $query
            ->orderByRaw('last_login_at DESC NULLS LAST')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();
        $roles = CrmUser::query()->whereNotNull('role')->distinct()->orderBy('role')->pluck('role');
        $employeeOptions = CrmUser::query()->orderBy('name')->get(['id', 'name']);

        return view('superadmin.crm.employee.att', [
            'employees' => $employees,
            'roles' => $roles,
            'employeeOptions' => $employeeOptions,
            'filters' => [
                'search' => $request->input('search', ''),
                'role' => $request->input('role', ''),
                'employee_id' => $request->input('employee_id', ''),
                'status' => $request->input('status', ''),
                'from' => $request->input('from', ''),
                'to' => $request->input('to', ''),
            ],
            'totalEmployees' => CrmUser::count(),
            'activeEmployees' => CrmUser::where('status', 'active')->count(),
            'loggedInToday' => CrmUser::whereDate('last_login_at', now()->toDateString())->count(),
            'neverLoggedIn' => CrmUser::whereNull('last_login_at')->count(),
        ]);
    }
}
