<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Master;
class ProviderController extends Controller
{
    protected $master;
    
    public function __construct(Master $master)
    {
        $this->master = $master;
    }
    public function index()
    {
        $this->master->check_permission(1, 'View');
        $data['providers'] = Provider::where('status', 'active')->get();

        $data['title']     = 'Providers';
        $data['pageTitle'] = 'Provider List';

        return view('superadmin.providers.index', $data);
    }

    public function variants($id)
    {
        $this->master->check_permission(8, 'View');
        $data['provider'] = $provider = Provider::findOrFail($id);
        $data['variants'] = $provider->variants()->orderByRaw('COALESCE(parent_variant_id, id), id')->get();
        $data['rootVariants'] = $provider->variants()
            ->whereNull('parent_variant_id')
            ->orderBy('name')
            ->get();
        $data['variants_plans'] = Plan::query()
            ->where('provider_id', $id)
            ->whereIn('variant_id', $data['variants']->pluck('id'))
            ->get(['id', 'variant_id'])
            ->groupBy('variant_id')
            ->all();
        $data['providerId'] = $id;

        $data['title']     = $provider->name . ' Variants';
        $data['pageTitle'] = 'Variants Selection';

        return view('superadmin.providers.variants', $data);
    }

    public function plans($id)
    {
        $this->master->check_permission(7, 'View');
        $data['provider']  = Provider::findOrFail($id);
        $data['providerId'] = $id;
        $data['title']     = $data['provider']->name . ' Plans';
        $data['pageTitle'] = 'Plans Selection';

        return view('superadmin.providers.plans', $data);
    }


    public function form($id = null)
    {
        if($id != ''){
            $this->master->check_permission(1, 'Edit');
            $data['update']    = Provider::findOrFail($id);
        }else{
            $this->master->check_permission(1, 'Add');
            $data['update']    = null;
        }
        $data['title']     = $data['update'] ? 'Edit Provider' : 'Add Provider';
        $data['pageTitle'] = $data['title'];

        return view('superadmin.providers.form', $data);
    }

    public function save(Request $request)
    {
        $id        = $request->input('id');
        $isUpdate  = !empty($id);
        $slugUnique = $isUpdate
            ? 'nullable|string|max:255|unique:pgsql_product.providers,slug,' . $id . ',id'
            : 'nullable|string|max:255|unique:pgsql_product.providers,slug';

        $validator = Validator::make($request->all(), [
            'name'         => 'required|string|max:255',
            'slug'         => $slugUnique,
            'description'  => 'nullable|string',
            'logo'         => 'nullable|image|mimes:jpg,jpeg,png,svg,webp|max:2048',
            'type'         => 'required|in:email,cloud,saas',
            'website_url'  => 'nullable|url',
            'api_base_url' => 'nullable|url',
            'status2'       => 'required|in:active,inactive',
            'has_variant'  => 'required|in:yes,no',
            'transfer_type'  => 'required|in:code,link',
            'transfer_detail' => 'required_if:transfer_type,link|nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            $error = $validator->errors();
            return response()->json([
                'error'        => true,
                'name'         => $error->first('name'),
                'slug'         => $error->first('slug'),
                'type'         => $error->first('type'),
                'logo'         => $error->first('logo'),
                'website_url'  => $error->first('website_url'),
                'api_base_url' => $error->first('api_base_url'),
                'status2'       => $error->first('status2'),
                'has_variant'  => $error->first('has_variant'),
                'transfer_type'  => $error->first('transfer_type'),
                'transfer_detail' => $error->first('transfer_detail'),
            ]);
        }

        $data = [
            'name'         => $request->name,
            'slug'         => $request->slug ?: Str::slug($request->name),
            'description'  => $request->description,
            'type'         => $request->type,
            'website_url'  => $request->website_url,
            'api_base_url' => $request->api_base_url,
            'status'       => $request->status2,
            'has_variant'  => $request->has_variant,
            'transfer_type'  => $request->transfer_type,
            'transfer_detail' =>  $request->transfer_detail,
        ];


        if ($request->hasFile('logo')) {
            if ($isUpdate) {
                $existing = Provider::find($id);
                if ($existing && $existing->logo) {
                    Storage::disk('public')->delete($existing->logo);
                }
            }
            $data['logo'] = $request->file('logo')->store('providers/logos', 'public');
        }

        if ($isUpdate) {
            Provider::where('id', $id)->update($data);
            $json = [
                'success' => 'Provider updated successfully.',
            ];
        } else {
            Provider::create($data);
            $json = [
                'success' =>  'Provider added successfully.',
            ];
        }

        return response()->json($json);
    }

    public function delete(Request $request)
    {
        $provider = Provider::find($request->input('id'));

        if (!$provider) {
            return response()->json([
                'error'   => true,
                'message' => 'Provider not found.',
            ]);
        }

        
            // Storage::disk('public')->delete($provider->logo);
            $provider->update([
                'status' => 'deleted',
            ]);

        // $provider->delete();

        return response()->json(['success' => 'Provider deleted successfully.']);
    }

    public function variantsForm($id = null)
    {
        if($id != ''){
            $this->master->check_permission(8, 'Edit');
            $data['update']    = Provider::findOrFail($id);
        }else{
            $this->master->check_permission(8, 'Add');
            $data['update']    = null;
        }
        $data['provider'] = $provider = Provider::findOrFail($id);
        $data['variants'] = $provider->variants;
        $data['providerId'] = $id;

        $data['title']     = $provider->name . ' Variants';
        $data['pageTitle'] = 'Variants Selection';

        return view('superadmin.providers.variants.form', $data);
    }
}
