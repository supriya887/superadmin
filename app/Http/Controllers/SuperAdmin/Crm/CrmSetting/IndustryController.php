<?php

namespace App\Http\Controllers\SuperAdmin\Crm\CrmSetting;

use App\Http\Controllers\Controller;
use App\Models\CrmIndustry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class IndustryController extends Controller
{
    public function index()
    {
        $data['pageTitle'] = 'Industry List';
        $data['industries'] = CrmIndustry::get();
        return view('superadmin.crm.crmsetting.industry.index', $data);
    }
    public function create()
    {
        $data['pageTitle'] = 'Add Industry';
        return view('superadmin.crm.crmsetting.industry.create', $data);
    }
    public function store(Request $request)
    {
        $industry = new CrmIndustry();
        $industry->name = $request->name;
        $industry->description = $request->description;
        $industry->save();
        return redirect()->route('crm.industries.index')->with('success', 'Industry added successfully');
    }
    public function edit($id)
    {
        $data['pageTitle'] = 'Edit Industry';
        $data['industry'] = CrmIndustry::find($id);
        return view('superadmin.crm.crmsetting.industry.edit', $data);
    }
    public function update(Request $request, $id)
    {
        $industry = CrmIndustry::find($id);
        $industry->name = $request->name;
        $industry->description = $request->description;
        $industry->save();
        return redirect()->route('crm.industries.index')->with('success', 'Industry updated successfully');
    }
    public function destroy($id)
    {
        $industry = CrmIndustry::find($id);
        $industry->delete();
        return redirect()->route('crm.industries.index')->with('success', 'Industry deleted successfully');
    }
}
