<?php

namespace App\Http\Controllers\SuperAdmin\Crm\CrmSetting;

use App\Http\Controllers\Controller;
use App\Models\CrmLeadStatus;
use Illuminate\Http\Request;

class LeadStatusController extends Controller
{
    /**
     * Display Lead Status list.
     */
    public function index()
    {
        $data['pageTitle'] = 'Lead Status List';

        $data['leadStatuses'] = CrmLeadStatus::latest('id')->get();

        return view('superadmin.crm.crmsetting.lead-status.index', $data);
    }

    /**
     * Show create Lead Status form.
     */
    public function create()
    {
        $data['pageTitle'] = 'Add Lead Status';

        return view('superadmin.crm.crmsetting.lead-status.create', $data);
    }

    /**
     * Store Lead Status.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $leadStatus = new CrmLeadStatus();

        $leadStatus->name = $request->name;
        $leadStatus->description = $request->description;
        $leadStatus->status = 'active';

        $leadStatus->save();

        return redirect()
            ->route('crm.lead-status.index')
            ->with('success', 'Lead Status added successfully');
    }

    /**
     * Show edit Lead Status form.
     */
    public function edit($id)
    {
        $data['pageTitle'] = 'Edit Lead Status';

        $data['leadStatus'] = CrmLeadStatus::findOrFail($id);

        return view('superadmin.crm.crmsetting.lead-status.edit', $data);
    }

    /**
     * Update Lead Status.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $leadStatus = CrmLeadStatus::findOrFail($id);

        $leadStatus->name = $request->name;
        $leadStatus->description = $request->description;

        $leadStatus->save();

        return redirect()
            ->route('crm.lead-status.index')
            ->with('success', 'Lead Status updated successfully');
    }

    /**
     * Delete Lead Status.
     */
    public function destroy($id)
    {
        $leadStatus = CrmLeadStatus::findOrFail($id);

        $leadStatus->delete();

        return redirect()
            ->route('crm.lead-status.index')
            ->with('success', 'Lead Status deleted successfully');
    }
}
