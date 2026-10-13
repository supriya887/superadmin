<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProviderController extends Controller
{
    public function index()
    {
        $providers = Provider::latest()->get();

        return $this->successResponse('Providers fetched successfully.', $providers);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'         => 'required|string|max:255',
            'slug'         => 'nullable|string|max:255|unique:providers,slug',
            'description'  => 'nullable|string',
            'logo'         => 'nullable|image|mimes:jpg,jpeg,png,svg,webp|max:2048',
            'type'         => 'required|in:email,cloud,saas',
            'website_url'  => 'nullable|url',
            'api_base_url' => 'nullable|url',
            'auth_type'    => 'nullable|string|max:100',
            'config_json'  => 'nullable|json',
            'status'       => 'required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $data               = $validator->validated();
        $data['slug']       = $data['slug'] ?? Str::slug($data['name']);
        $data['config_json'] = isset($data['config_json'])
            ? json_decode($data['config_json'], true)
            : null;

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('providers/logos', 'public');
        }

        $provider = Provider::create($data);

        return $this->successResponse('Provider created successfully.', $provider, 201);
    }

    public function update(Request $request, Provider $provider)
    {
        $validator = Validator::make($request->all(), [
            'name'         => 'required|string|max:255',
            'slug'         => 'nullable|string|max:255|unique:providers,slug,' . $provider->id,
            'description'  => 'nullable|string',
            'logo'         => 'nullable|image|mimes:jpg,jpeg,png,svg,webp|max:2048',
            'type'         => 'required|in:email,cloud,saas',
            'website_url'  => 'nullable|url',
            'api_base_url' => 'nullable|url',
            'auth_type'    => 'nullable|string|max:100',
            'config_json'  => 'nullable|json',
            'status'       => 'required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $data               = $validator->validated();
        $data['slug']       = $data['slug'] ?? Str::slug($data['name']);
        $data['config_json'] = isset($data['config_json'])
            ? json_decode($data['config_json'], true)
            : null;

        if ($request->hasFile('logo')) {
            if ($provider->logo) {
                Storage::disk('public')->delete($provider->logo);
            }
            $data['logo'] = $request->file('logo')->store('providers/logos', 'public');
        }

        $provider->update($data);

        return $this->successResponse('Provider updated successfully.', $provider->fresh());
    }

    public function destroy(Provider $provider)
    {
        if ($provider->logo) {
            Storage::disk('public')->delete($provider->logo);
        }

        $provider->delete();

        return $this->successResponse('Provider deleted successfully.');
    }
    
}
