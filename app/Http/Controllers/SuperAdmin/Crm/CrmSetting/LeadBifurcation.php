<?php

namespace App\Http\Controllers\SuperAdmin\Crm\CrmSetting;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeadBifurcationController extends Controller
{
    public function index()
    {
        $data['pageTitle'] = 'Lead Bifurcation';
        return view('superadmin.crm.crmsetting.lead-bifurcation.index', $data);
    }

    public function create()
    {
        $data['pageTitle'] = 'Add Lead Bifurcation';
        return view('superadmin.crm.crmsetting.lead-bifurcation.create', $data);
    }

    public function store(Request $request)
    {
        $bifurcation = new CrmLeadBifurcation();
        $bifurcation->name = $request->name;
        $bifurcation->description = $request->description;
        $bifurcation->save();
        return redirect()->route('crm.lead-bifurcation.index')->with('success', 'Lead Bifurcation added successfully');
    }

    public function edit($id)
    {
        $data['pageTitle'] = 'Edit Lead Bifurcation';
        $data['bifurcation'] = CrmLeadBifurcation::find($id);
        return view('superadmin.crm.crmsetting.lead-bifurcation.edit', $data);
    }

    public function update(Request $request, $id)
    {
        $bifurcation = CrmLeadBifurcation::find($id);
        $bifurcation->name = $request->name;
        $bifurcation->description = $request->description;
        $bifurcation->save();
        return redirect()->route('crm.lead-bifurcation.index')->with('success', 'Lead Bifurcation updated successfully');
    }

    public function destroy($id)
    {
        $bifurcation = CrmLeadBifurcation::find($id);
        $bifurcation->delete();
        return redirect()->route('crm.lead-bifurcation.index')->with('success', 'Lead Bifurcation deleted successfully');
    }
}
