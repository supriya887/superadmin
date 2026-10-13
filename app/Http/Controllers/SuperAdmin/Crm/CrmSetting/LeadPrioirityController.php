<?php

namespace App\Http\Controllers\SuperAdmin\Crm\CrmSetting;

use App\Http\Controllers\Controller;
use App\Models\CrmLeadPriority;
use Illuminate\Http\Request;

class LeadPriorityController extends Controller
{
    public function index()
    {
        $priorities = CrmLeadPriority::orderBy('id', 'asc')->get();

        $data['pageTitle'] = 'Lead Priority';
        $data['priorities'] = $priorities;
        $data['leadPriorities'] = $priorities;
        $data['totalPriorities'] = count($priorities);

        return view('superadmin.crm.crmsetting.lead-priority.index', $data);
    }

    public function create()
    {
        $data['pageTitle'] = 'Create Lead Priority';
        return view('superadmin.crm.crmsetting.lead-priority.create', $data);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $leadPriority = new CrmLeadPriority();
        $leadPriority->name = $request->name;
        $leadPriority->description = $request->description;
        $leadPriority->status = 'active';
        $leadPriority->save();

        return redirect()->route('crm.lead-priority.index')->with('success', 'Lead priority added successfully');
    }

    public function edit($id)
    {
        $data['pageTitle'] = 'Edit Lead Priority';
        $data['leadPriority'] = CrmLeadPriority::findOrFail($id);
        $data['priority'] = $data['leadPriority'];
        return view('superadmin.crm.crmsetting.lead-priority.edit', $data);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $leadPriority = CrmLeadPriority::findOrFail($id);
        $leadPriority->name = $request->name;
        $leadPriority->description = $request->description;
        $leadPriority->save();

        return redirect()->route('crm.lead-priority.index')->with('success', 'Lead priority updated successfully');
    }

    public function destroy($id)
    {
        $leadPriority = CrmLeadPriority::findOrFail($id);
        $leadPriority->delete();

        return redirect()->route('crm.lead-priority.index')->with('success', 'Lead priority deleted successfully');
    }
}
