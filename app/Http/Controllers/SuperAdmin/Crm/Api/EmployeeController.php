<?php

namespace App\Http\Controllers\SuperAdmin\Crm\Api;

use App\Http\Controllers\SuperAdmin\Crm\EmployeeController as WebEmployeeController;
use App\Models\CrmUser;
use Illuminate\Http\Request;

class EmployeeController extends WebEmployeeController
{
    public function index(Request $request)
    {
        $data = parent::index($request)->getData();
        $employees = $data['employees'];

        return response()->json([
            'success' => true,
            'message' => 'Employees fetched successfully.',
            'data' => [
                'employees' => $employees->items(),
                'pagination' => $this->pages($employees),
                'summary' => [
                    'total' => $data['totalEmployees'],
                    'active' => $data['activeEmployees'],
                    'leads_assigned' => $data['totalLeadsAssigned'],
                    'pending_tasks' => $data['pendingTasks'],
                ],
            ],
        ]);
    }

    public function show($id)
    {
        $data = parent::show($id)->getData();

        return response()->json([
            'success' => true,
            'message' => 'Employee fetched successfully.',
            'data' => [
                'employee' => $data['employee'],
                'leads' => $data['employeeLeads']->items(),
                'pagination' => $this->pages($data['employeeLeads']),
                'total_leads' => $data['totalLeads'],
                'total_calls' => $data['totalCalls'],
                'total_deals' => $data['totalDeals'],
            ],
        ]);
    }

    public function store(Request $request)
    {
        parent::store($request);

        return response()->json([
            'success' => true,
            'message' => 'Employee created successfully.',
            'data' => ['employee' => CrmUser::where('email', $request->email)->latest('id')->first()],
        ], 201);
    }

    public function update(Request $request, $id)
    {
        parent::update($request, $id);

        return response()->json([
            'success' => true,
            'message' => 'Employee updated successfully.',
            'data' => ['employee' => CrmUser::find($id)],
        ]);
    }

    public function destroy($id)
    {
        parent::destroy($id);

        return response()->json([
            'success' => true,
            'message' => 'Employee deleted successfully.',
        ]);
    }

    public function permissions(Request $request)
    {
        $data = parent::permissions($request)->getData();

        return response()->json([
            'success' => true,
            'message' => 'Employee permissions fetched successfully.',
            'data' => [
                'roles' => $data['roles'],
                'employees' => $data['employees'],
                'selected_role' => $data['selectedRole'],
                'selected_employee' => $data['selectedEmployee'],
                'has_employee_override' => $data['hasEmployeeOverride'],
                'modules' => $data['modules'],
                'permissions' => $data['permissions'],
            ],
        ]);
    }

    public function savePermissions(Request $request)
    {
        $response = parent::savePermissions($request);

        return response()->json([
            'success' => true,
            'message' => $response->getSession()->get('success') ?: 'Permissions saved.',
        ]);
    }

    public function attendance(Request $request)
    {
        $data = parent::attindex($request)->getData();
        $employees = $data['employees'];

        return response()->json([
            'success' => true,
            'message' => 'Attendance fetched successfully.',
            'data' => [
                'employees' => $employees->items(),
                'pagination' => $this->pages($employees),
                'roles' => $data['roles'],
                'employees_list' => $data['employeeOptions'],
            ],
        ]);
    }

    protected function pages($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
