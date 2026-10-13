<?php

namespace App\Http\Controllers\SuperAdmin\Crm\CrmSetting;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\CrmLeadBifurcation;

class LeadBifurcationController extends Controller
{
    /**
     * Auto-ensure table exists in PostgreSQL database
     */
    private function ensureTableExists()
    {
        try {
            DB::connection('pgsql_crm')->statement("
                CREATE TABLE IF NOT EXISTS crm_lead_bifurcations (
                    id BIGSERIAL PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    description TEXT NULL,
                    status VARCHAR(50) DEFAULT 'active',
                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL
                );
            ");

            $count = DB::connection('pgsql_crm')->table('crm_lead_bifurcations')->count();
            if ($count === 0) {
                DB::connection('pgsql_crm')->table('crm_lead_bifurcations')->insert([
                    [
                        'name' => 'B2B',
                        'description' => 'Business / Corporate Leads',
                        'status' => 'active',
                        'created_at' => \Carbon\Carbon::parse('2026-08-20 10:00:00'),
                        'updated_at' => \Carbon\Carbon::parse('2026-08-20 10:00:00'),
                    ],
                    [
                        'name' => 'B2C',
                        'description' => 'Individual Customer Leads',
                        'status' => 'active',
                        'created_at' => \Carbon\Carbon::parse('2026-08-20 10:00:00'),
                        'updated_at' => \Carbon\Carbon::parse('2026-08-20 10:00:00'),
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            // Ignore if already handled
        }
    }

    public function index()
    {
        $this->ensureTableExists();

        $data['pageTitle'] = 'Lead Bifurcation';
        $data['bifurcations'] = CrmLeadBifurcation::orderBy('id', 'asc')->get();
        $data['totalCategories'] = CrmLeadBifurcation::count();
        return view('superadmin.crm.crmsetting.lead-bifurcation.index', $data);
    }

    public function create()
    {
        $data['pageTitle'] = 'Add Lead Bifurcation';
        return view('superadmin.crm.crmsetting.lead-bifurcation.create', $data);
    }

    public function store(Request $request)
    {
        $this->ensureTableExists();

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $bifurcation = new CrmLeadBifurcation();
        $bifurcation->name = $request->name;
        $bifurcation->description = $request->description;
        $bifurcation->status = 'active';
        $bifurcation->save();

        return redirect()->route('crm.lead-bifurcation.index')->with('success', 'Lead Bifurcation added successfully');
    }

    public function edit($id)
    {
        $this->ensureTableExists();

        $data['pageTitle'] = 'Edit Lead Bifurcation';
        $data['bifurcation'] = CrmLeadBifurcation::find($id);
        return view('superadmin.crm.crmsetting.lead-bifurcation.edit', $data);
    }

    public function update(Request $request, $id)
    {
        $this->ensureTableExists();

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $bifurcation = CrmLeadBifurcation::find($id);
        $bifurcation->name = $request->name;
        $bifurcation->description = $request->description;
        $bifurcation->save();

        return redirect()->route('crm.lead-bifurcation.index')->with('success', 'Lead Bifurcation updated successfully');
    }

    public function destroy($id)
    {
        $this->ensureTableExists();

        $bifurcation = CrmLeadBifurcation::find($id);
        $bifurcation->delete();

        return redirect()->route('crm.lead-bifurcation.index')->with('success', 'Lead Bifurcation deleted successfully');
    }
}
