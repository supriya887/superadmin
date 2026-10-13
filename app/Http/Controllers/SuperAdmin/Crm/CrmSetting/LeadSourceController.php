<?php

namespace App\Http\Controllers\SuperAdmin\Crm\CrmSetting;

use App\Http\Controllers\Controller;
use App\Models\CrmLeadSource;
use Illuminate\Http\Request;

class LeadSourceController extends Controller
{
    public function index()
    {
        $sources = CrmLeadSource::orderBy('id', 'asc')->get();
        return view('superadmin.crm.crmsetting.lead-sources.index', [
            'sources' => $sources,
            'leadSources' => $sources,
            'pageTitle' => 'Lead Sources',
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'logo' => 'nullable|image|max:2048',
            'status' => 'nullable|string',
        ]);

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('lead-sources', 'public');
        }

        CrmLeadSource::create($data);
        return redirect()->route('crm.lead-sources.index')->with('success', 'Lead Source created successfully');
    }

    public function edit($id)
    {
        $source = CrmLeadSource::findOrFail($id);
        return view('superadmin.crm.crmsetting.lead-sources.edit', compact('source'));
    }

    public function update(Request $request, $id)
    {
        $source = CrmLeadSource::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'logo' => 'nullable|image|max:2048',
            'status' => 'nullable|string',
        ]);

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('lead-sources', 'public');
        }

        $source->update($data);
        return redirect()->route('crm.lead-sources.index')->with('success', 'Lead Source updated successfully');
    }

    public function destroy($id)
    {
        $source = CrmLeadSource::findOrFail($id);
        $source->delete();
        return redirect()->route('crm.lead-sources.index')->with('success', 'Lead Source deleted successfully');
    }
}
