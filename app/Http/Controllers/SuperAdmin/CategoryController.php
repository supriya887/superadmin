<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PlanCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Master;    
class CategoryController extends Controller
{
    protected $master;
    
    public function __construct() {
        $this->master = new Master();
    }
    public function index()
    {
        $this->master->check_permission(4, 'View');
        $data['categories'] = PlanCategory::orderBy('id', 'desc')->get();
        $data['title']     = 'Plan Categories';
        $data['pageTitle'] = 'Master Category List';
        
        return view('superadmin.categories.index', $data);
    }

    public function form($id = null)
    {
        if($id != ''){
            $this->master->check_permission(4, 'Edit');
            $data['update']    = PlanCategory::findOrFail($id);
        }else{
            $this->master->check_permission(4, 'Add');
            $data['update']    = null;
        }
        $data['title']     = $data['update'] ? 'Edit Category' : 'Add Category';
        $data['pageTitle'] = $data['title'];

        return view('superadmin.categories.form', $data);
    }

    public function save(Request $request)
    {
        $id        = $request->input('id');
        $isUpdate  = !empty($id);

        $validator = Validator::make($request->all(), [
            'name'   => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) use ($isUpdate, $id): void {
                    $query = PlanCategory::query()->where('name', (string) $value);
                    if ($isUpdate) {
                        $query->where('id', '!=', (int) $id);
                    }
                    if ($query->exists()) {
                        $fail('The category name has already been taken.');
                    }
                },
            ],
            'status' => 'required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            $error = $validator->errors();
            return response()->json([
                'error'  => true,
                'name'   => $error->first('name'),
                'status' => $error->first('status'),
            ]);
        }

        $data = [
            'name'   => $request->name,
            'status' => $request->status,
        ];

        if ($isUpdate) {
            PlanCategory::where('id', $id)->update($data);
            return response()->json(['success' => 'Category updated successfully.']);
        }

        $cat = PlanCategory::create($data);
        return response()->json(['success' => true, 'message' => 'Category added successfully.', 'data' => $cat]);
    }

    public function delete(Request $request)
    {
        $category = PlanCategory::find($request->input('id'));
        if (!$category) {
            return response()->json(['error' => true, 'message' => 'Category not found.']);
        }

        $category->delete();
        return response()->json(['success' => 'Category deleted successfully.']);
    }
}
