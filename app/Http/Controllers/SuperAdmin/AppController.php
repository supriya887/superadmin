<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Http\Controllers\Master;
class AppController extends Controller
{
    protected $master;
    
    public function __construct() {
        $this->master = new Master();
    }
    public function index()
    {
        $this->master->check_permission(5, 'View');
        $apps = AppService::orderBy('id', 'desc')->get();
        foreach ($apps as $app) {
            $app->ensurePublicMirror();
        }
        $data['apps'] = $apps;
        $data['title']     = 'Popular Apps & Services';
        $data['pageTitle'] = 'Master Apps List';

        return view('superadmin.apps.index', $data);
    }

    public function form($id = null)
    {
        if ($id != '') {
            $this->master->check_permission(5, 'Edit');
            $data['update'] = AppService::findOrFail($id);
            $data['update']->ensurePublicMirror();
        } else {
            $this->master->check_permission(5, 'Add');
            $data['update'] = null;
        }
        $data['title']     = $data['update'] ? 'Edit App/Service' : 'Add App/Service';
        $data['pageTitle'] = $data['title'];

        return view('superadmin.apps.form', $data);
    }

    public function save(Request $request)
    {
        $id        = $request->input('id');
        $isUpdate  = !empty($id);

        // Form historically used status2; accept either.
        if (!$request->filled('status') && $request->filled('status2')) {
            $request->merge(['status' => $request->input('status2')]);
        }

        $validator = Validator::make($request->all(), [
            'name'   => 'required|string|max:255|unique:apps,name,' . ($id ?? 'NULL'),
            'image'  => $isUpdate ? 'nullable|image|mimes:jpg,jpeg,png,svg,webp|max:2048' : 'required|image|mimes:jpg,jpeg,png,svg,webp|max:2048',
            'status' => 'required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            $error = $validator->errors();
            return response()->json([
                'error'  => true,
                'name'   => $error->first('name'),
                'image'  => $error->first('image'),
                'status' => $error->first('status'),
                'status2' => $error->first('status'),
            ]);
        }

        $data = [
            'name'   => $request->name,
            'status' => $request->status,
        ];

        if ($request->hasFile('image')) {
            if ($isUpdate) {
                $existing = AppService::find($id);
                if ($existing && $existing->image) {
                    $oldRel = ltrim(preg_replace('#^(storage/app/public/|public/storage/|storage/)+#', '', (string) $existing->image), '/');
                    Storage::disk('public')->delete($oldRel);
                    $publicOld = public_path('storage/' . $oldRel);
                    if (is_file($publicOld)) {
                        @unlink($publicOld);
                    }
                }
            }

            $stored = $request->file('image')->store('apps/icons', 'public');
            $data['image'] = $stored;

            // Mirror for hosts without a working storage symlink.
            $src = storage_path('app/public/' . ltrim(str_replace('\\', '/', $stored), '/'));
            $dst = public_path('storage/' . ltrim(str_replace('\\', '/', $stored), '/'));
            if (is_file($src)) {
                $dir = dirname($dst);
                if (!is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }
                @copy($src, $dst);
            }
        }

        if ($isUpdate) {
            AppService::where('id', $id)->update($data);
            return response()->json(['success' => 'App/Service updated successfully.']);
        }

        $app = AppService::create($data);
        return response()->json([
            'success' => 'App/Service added successfully.',
            'app'     => $app,
        ]);
    }

    public function delete(Request $request)
    {
        $app = AppService::find($request->input('id'));
        if (!$app) {
            return response()->json(['error' => true, 'message' => 'App not found.']);
        }

        if ($app->image) {
            Storage::disk('public')->delete($app->image);
        }

        $app->delete();
        return response()->json(['success' => 'App/Service deleted successfully.']);
    }
}
