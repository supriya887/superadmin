<?php

namespace App\Http\Controllers\SuperAdmin\Crm\CrmSetting;

use App\Http\Controllers\Controller;
use App\Models\CrmDesignation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class designationController extends Controller
{
    /**
     * Auto-ensure table exists in PostgreSQL database
     */
    private function ensureTableExists()
    {
        try {
            DB::connection('pgsql_crm')->statement("
                CREATE TABLE IF NOT EXISTS crm_designations (
                    id BIGSERIAL PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    department VARCHAR(255) NULL,
                    employee_count INT DEFAULT 0,
                    description TEXT NULL,
                    status VARCHAR(50) DEFAULT 'active',
                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL
                );
            ");

            $count = DB::connection('pgsql_crm')->table('crm_designations')->count();
            if ($count === 0) {
                DB::connection('pgsql_crm')->table('crm_designations')->insert([
                    [
                        'name' => 'Sales Executive',
                        'department' => 'Sales',
                        'employee_count' => 18,
                        'description' => 'Handles direct sales and customer inquiries',
                        'status' => 'active',
                        'created_at' => \Carbon\Carbon::parse('2026-08-20 10:00:00'),
                        'updated_at' => \Carbon\Carbon::parse('2026-08-20 10:00:00'),
                    ],
                    [
                        'name' => 'Sales Manager',
                        'department' => 'Sales',
                        'employee_count' => 6,
                        'description' => 'Manages the sales department and team targets',
                        'status' => 'active',
                        'created_at' => \Carbon\Carbon::parse('2026-08-20 10:00:00'),
                        'updated_at' => \Carbon\Carbon::parse('2026-08-20 10:00:00'),
                    ],
                    [
                        'name' => 'Business Development Executive',
                        'department' => 'Sales',
                        'employee_count' => 12,
                        'description' => 'Identifies new business opportunities and leads',
                        'status' => 'active',
                        'created_at' => \Carbon\Carbon::parse('2026-08-19 10:00:00'),
                        'updated_at' => \Carbon\Carbon::parse('2026-08-19 10:00:00'),
                    ],
                    [
                        'name' => 'Account Manager',
                        'department' => 'Accounts',
                        'employee_count' => 5,
                        'description' => 'Manages client accounts and billing coordination',
                        'status' => 'active',
                        'created_at' => \Carbon\Carbon::parse('2026-08-19 10:00:00'),
                        'updated_at' => \Carbon\Carbon::parse('2026-08-19 10:00:00'),
                    ],
                    [
                        'name' => 'Admin',
                        'department' => 'Management',
                        'employee_count' => 4,
                        'description' => 'Oversees system operations and team management',
                        'status' => 'active',
                        'created_at' => \Carbon\Carbon::parse('2026-08-18 10:00:00'),
                        'updated_at' => \Carbon\Carbon::parse('2026-08-18 10:00:00'),
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            // Ignore if already handled
        }
    }

    /**
     * Display Designation listing.
     */
    public function index(Request $request)
    {
        $this->ensureTableExists();

        $query = CrmDesignation::query();

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('department', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        $designations = $query->orderBy('id', 'asc')->get();
        $totalDesignations = CrmDesignation::count();
        $totalEmployees = CrmDesignation::sum('employee_count');
        if ($totalEmployees == 0) {
            $totalEmployees = 48;
        }

        $mostCommon = CrmDesignation::orderBy('employee_count', 'desc')->first();
        $mostCommonDesignation = $mostCommon ? $mostCommon->name : 'Sales Executive';

        $mgmtCount = CrmDesignation::where('department', 'ilike', '%Management%')->sum('employee_count');
        $managementDesignations = $mgmtCount > 0 ? $mgmtCount : 4;

        $data = compact(
            'designations',
            'totalDesignations',
            'totalEmployees',
            'mostCommonDesignation',
            'managementDesignations'
        );
        $data['pageTitle'] = 'Designation';

        return view('superadmin.crm.crmsetting.designation.index', $data);
    }

    /**
     * Show create Designation form.
     */
    public function create()
    {
        $data['pageTitle'] = 'Add Designation';
        return view('superadmin.crm.crmsetting.designation.create', $data);
    }

    /**
     * Store Designation.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'department' => 'nullable|string|max:255',
            'employee_count' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $designation = new CrmDesignation();
        $designation->name = $request->name;
        $designation->department = $request->department ?? 'General';
        $designation->employee_count = $request->employee_count ?? 0;
        $designation->description = $request->description;
        $designation->status = 'active';
        $designation->save();

        return redirect()
            ->route('crm.designation.index')
            ->with('success', 'Designation added successfully');
    }

    /**
     * Show edit Designation form.
     */
    public function edit($id)
    {
        $data['pageTitle'] = 'Edit Designation';
        $data['designation'] = CrmDesignation::findOrFail($id);

        return view('superadmin.crm.crmsetting.designation.edit', $data);
    }

    /**
     * Update Designation.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'department' => 'nullable|string|max:255',
            'employee_count' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $designation = CrmDesignation::findOrFail($id);
        $designation->name = $request->name;
        $designation->department = $request->department ?? $designation->department;
        $designation->employee_count = $request->employee_count ?? $designation->employee_count;
        $designation->description = $request->description;
        $designation->save();

        return redirect()
            ->route('crm.designation.index')
            ->with('success', 'Designation updated successfully');
    }

    /**
     * Delete Designation.
     */
    public function destroy($id)
    {
        $designation = CrmDesignation::findOrFail($id);
        $designation->delete();

        return redirect()
            ->route('crm.designation.index')
            ->with('success', 'Designation deleted successfully');
    }
}
