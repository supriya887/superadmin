<?php

namespace App\Http\Controllers\SuperAdmin\Crm\CrmSetting;

use App\Http\Controllers\Controller;
use App\Models\CrmLeadPriority;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class LeadPrioirity extends Controller
{
    public function index()
    {
        $data['pageTitle'] = 'Lead Priority';
        return view('superadmin.crm.crmsetting.lead-priority.index', $data);
    }

    public function create()
    {
        $data['pageTitle'] = 'Add Lead Priority';
        return view('superadmin.crm.crmsetting.lead-priority.create', $data);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $priority = new CrmLeadPriority();
        $priority->name = $request->name;
        $priority->description = $request->description;
        $priority->status = 'active';
        $priority->save();

        return redirect()
            ->route('crm.lead-priority.index')
            ->with('success', 'Lead Priority added successfully');
    }

    public function edit($id)
    {
        $data['pageTitle'] = 'Edit Lead Priority';
        $data['priority'] = CrmLeadPriority::findOrFail($id);
        return view('superadmin.crm.crmsetting.lead-priority.edit', $data);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $priority = CrmLeadPriority::findOrFail($id);
        $priority->name = $request->name;
        $priority->description = $request->description;
        $priority->save();

        return redirect()
            ->route('crm.lead-priority.index')
            ->with('success', 'Lead Priority updated successfully');
    }

    public function destroy($id)
    {
        $priority = CrmLeadPriority::findOrFail($id);
        $priority->delete();
        return redirect()
            ->route('crm.lead-priority.index')
            ->with('success', 'Lead Priority deleted successfully');
    }
}
