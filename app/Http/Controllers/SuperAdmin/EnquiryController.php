<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class EnquiryController extends Controller
{
    public function enquiry_table()
    {
        $data = [
            'title' => 'Enquiries',
            'pageTitle' => 'Enquiries',
            'li_1' => 'Support Center',
            'li_2' => 'Enquiries',
        ];

        $data['result'] = [];
        if (Schema::connection('pgsql_auth')->hasTable('enquiries')) {
            $data['result'] = Enquiry::query()
                ->with('partner')
                ->orderByDesc('id')
                ->get();
        }

        return view('superadmin.enquiries.index', $data);
    }

    public function add_enquiry($id = '')
    {
        $title = 'Add Enquiry';
        if ($id != '') {
            $title = 'Update Enquiry';
        }

        $data = [
            'title' => $title,
            'pageTitle' => $title,
            'li_1' => 'Support Center',
            'li_2' => $title,
        ];

        if ($id != '') {
            $data['update'] = Enquiry::query()->with('partner')->findOrFail($id);
        }

        $data['partners'] = Partner::query()
            ->select('id', 'name', 'email', 'mobile')
            ->orderBy('name')
            ->get();

        $data['statuses'] = ['new', 'in_progress', 'resolved', 'closed'];

        return view('superadmin.enquiries.form', $data);
    }

    public function delete_enquiry(Request $request)
    {
        $id = $request->input('id');
        Enquiry::query()->where('id', $id)->delete();
    }

    public function enquiry_validation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'partner_id' => 'required|integer|exists:pgsql_auth.partners,id',
            'message' => 'required|string|max:5000',
            'status' => 'required|string|in:new,in_progress,resolved,closed',
        ]);

        if ($validator->fails()) {
            $error = $validator->errors();

            return response()->json([
                'error' => true,
                'partner_id' => $error->first('partner_id'),
                'message' => $error->first('message'),
                'status' => $error->first('status'),
            ]);
        }

        $array = [
            'partner_id' => (int) $request->input('partner_id'),
            'message' => trim((string) $request->input('message')),
            'status' => $request->input('status'),
        ];

        if ($request->input('id') == '') {
            Enquiry::query()->create($array);
        } else {
            Enquiry::query()->where('id', $request->input('id'))->update($array);
        }

        return response()->json([
            'success' => 'Enquiry Saved Successfully.',
        ]);
    }
}
