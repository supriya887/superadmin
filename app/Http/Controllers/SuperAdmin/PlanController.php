<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Plan;
use App\Models\Variant;
use App\Models\Provider;
use App\Models\AppService;
use App\Models\PlanCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Http\Controllers\Master;
use Illuminate\Support\Facades\Log;

class PlanController extends Controller
{
    protected $master;
    
    public function __construct() {
        $this->master = new Master();
    }
    
    public function index($provider_id, $variant_id)
    {
        $this->master->check_permission(7, 'View');
        $data['provider'] = Provider::findOrFail($provider_id);
        $providerHasVariant = (($data['provider']->has_variant ?? 'no') === 'yes');

        if (!$providerHasVariant || $variant_id == 0) {
            $data['variant']   = null;
            $data['plans']     = Plan::where('provider_id', $provider_id)->orderBy('sr_no', 'asc')->get();
            $data['title']     = 'All Plans - ' . $data['provider']->name;
            $data['pageTitle'] = 'Master Plans List';
            $last_sr_no = Plan::where('provider_id', $provider_id)->orderBy('sr_no', 'desc')->value('sr_no');
            $data['next_sr_no'] = $last_sr_no ? $last_sr_no + 1 : 1;
        } else {
            $data['variant']   = Variant::findOrFail($variant_id);
            $data['plans']     = Plan::where('provider_id', $provider_id)
                ->where('variant_id', $variant_id)
                ->orderBy('sr_no', 'asc')
                ->get();
            $data['title']     = 'Manage Plans - ' . $data['variant']->name;
            $data['pageTitle'] = 'Plans';
            $last_sr_no = Plan::where('provider_id', $provider_id)
                ->where('variant_id', $variant_id)
                ->orderBy('sr_no', 'desc')
                ->value('sr_no');
            $data['next_sr_no'] = $last_sr_no ? $last_sr_no + 1 : 1;
        }

        $data['provider_id'] = $provider_id;
        $data['variant_id']  = $variant_id;

        return view('superadmin.plans.index', $data);
    }

    public function providerSelector(Request $request)
    {
        $this->master->check_permission(7, 'Add');

        $providers = Provider::where('status', 'active')->orderBy('name')->get();
        if ($providers->isEmpty()) {
            return redirect()->route('superadmin.providers.index');
        }

        $hasVariantByProvider = $providers->mapWithKeys(function ($provider) {
            return [(int) $provider->id => (($provider->has_variant ?? 'no') === 'yes')];
        })->toArray();
        
        $variantsByProvider = Variant::whereIn('provider_id', $providers->pluck('id')->all())
            ->orderBy('name')
            ->get(['id', 'provider_id', 'name'])
            ->groupBy('provider_id')
            ->map(function ($items) {
                return $items->map(function ($item) {
                    return ['id' => (int) $item->id, 'name' => (string) $item->name];
                })->values()->all();
            })
            ->toArray();
            
        $selectedProviderId = (int) $request->query('provider_id', (int) $providers->first()->id);
        $firstVariantId = (!empty($hasVariantByProvider[$selectedProviderId]) && isset($variantsByProvider[$selectedProviderId][0]['id']))
            ? (int) $variantsByProvider[$selectedProviderId][0]['id']
            : 0;
            
        return redirect(url('superadmin/plans/add-plan/' . $selectedProviderId . '/' . $firstVariantId));
    }

    public function create(Request $request, $provider_id, $variant_id)
    {
        $this->master->check_permission(7, 'Add');
        $data['provider'] = Provider::findOrFail($provider_id);
        $providerHasVariant = (($data['provider']->has_variant ?? 'no') === 'yes');
    
        if (!$providerHasVariant) {
            $variant = null;
        } elseif ($variant_id == 0) {
            $variant = Variant::where('provider_id', $provider_id)->first();
            if (!$variant) {
                $variant = Variant::create([
                    'provider_id' => $provider_id,
                    'name' => 'Master',
                    'slug' => Str::slug($data['provider']->name . '-master'),
                    'status' => 'active'
                ]);
            }
        } else {
            $variant = Variant::findOrFail($variant_id);
        }
    
        $data['variant']    = $variant;
        $data['apps']       = AppService::where('status', 'active')->get();
        $data['categories'] = PlanCategory::where('status', 'active')->get();
        $data['providers']  = Provider::where('status', 'active')->orderBy('name')->get();
    
        $data['hasVariantByProvider'] = $data['providers']->mapWithKeys(function ($provider) {
            return [(int) $provider->id => (($provider->has_variant ?? 'no') === 'yes')];
        })->toArray();
        
        $data['variantsByProvider'] = Variant::whereIn('provider_id', $data['providers']->pluck('id')->all())
            ->orderBy('name')
            ->get(['id', 'provider_id', 'name'])
            ->groupBy('provider_id')
            ->map(function ($items) {
                return $items->map(function ($item) {
                    return ['id' => (int) $item->id, 'name' => (string) $item->name];
                })->values()->all();
            })
            ->toArray();
    
        $data['title']      = 'Add Plan to ' . ($variant_id == 0 ? $data['provider']->name : optional($variant)->name);
        $data['pageTitle']  = 'Add Plan';
        $data['provider_id'] = $provider_id;
        $data['variant_id']  = $variant_id;
        $data['lockProvider'] = ($request->query('source') === 'provider');
    
        if ($providerHasVariant && $variant) {
            $last_sr_no = Plan::where('provider_id', $provider_id)
                ->where('variant_id', $variant->id)
                ->orderBy('sr_no', 'desc')
                ->value('sr_no');
            $data['next_sr_no'] = $last_sr_no ? $last_sr_no + 1 : 1;
        } else {
            $last_sr_no = Plan::where('provider_id', $provider_id)
                ->orderBy('sr_no', 'desc')
                ->value('sr_no');
            $data['next_sr_no'] = $last_sr_no ? $last_sr_no + 1 : 1;
        }
    
        // Check if Microsoft provider
        $data['isMicrosoft'] = $this->isMicrosoftProvider($data['provider']);
    
        return view('superadmin.plans.form', $data);
    }

    public function edit(Request $request, $provider_id, $variant_id, $plan_id)
    {
        $this->master->check_permission(7, 'Edit');
        $plan = Plan::findOrFail($plan_id);
        $discountedPrice = $this->calculateDiscountedPrice($plan);

        $provider = Provider::findOrFail($provider_id);
        $providerHasVariant = (($provider->has_variant ?? 'no') === 'yes');

        // Dynamically determine the variant
        if (!$providerHasVariant) {
            $variant = null;
        } elseif ($variant_id == 0) {
            $variant = Variant::where('provider_id', $provider_id)->first();
        } else {
            $variant = Variant::findOrFail($variant_id);
        }

        $apps = AppService::where('status', 'active')->get();
        $categories = PlanCategory::where('status', 'active')->get();
        $providers = Provider::where('status', 'active')->orderBy('name')->get();

        $hasVariantByProvider = $providers->mapWithKeys(function ($item) {
            return [(int) $item->id => (($item->has_variant ?? 'no') === 'yes')];
        })->toArray();

        $variantsByProvider = Variant::whereIn('provider_id', $providers->pluck('id')->all())
            ->orderBy('name')
            ->get(['id', 'provider_id', 'name'])
            ->groupBy('provider_id')
            ->map(function ($items) {
                return $items->map(function ($item) {
                    return ['id' => (int) $item->id, 'name' => (string) $item->name];
                })->values()->all();
            })
            ->toArray();

        $selectedApps = $plan->apps->pluck('id')->toArray();

        if ($providerHasVariant && $variant) {
            $max_sr_no = Plan::where('provider_id', $provider_id)
                ->where('variant_id', $variant->id)
                ->max('sr_no');
        } else {
            $max_sr_no = Plan::where('provider_id', $provider_id)
                ->max('sr_no');
        }

        $edit_sr_no_range = range(1, max($max_sr_no, $plan->sr_no ?? 1));

        $isMicrosoft = $this->isMicrosoftProvider($provider);

        $data = [
            'plan'                => $plan,
            'provider'            => $provider,
            'variant'             => $variant,
            'apps'                => $apps,
            'categories'          => $categories,
            'providers'           => $providers,
            'hasVariantByProvider'=> $hasVariantByProvider,
            'variantsByProvider'  => $variantsByProvider,
            'selectedApps'        => $selectedApps,
            'title'               => 'Edit Plan - ' . $plan->name,
            'pageTitle'           => 'Edit Plan',
            'provider_id'         => $provider_id,
            'variant_id'          => $variant_id,
            'plan_id'             => $plan_id,
            'lockProvider'        => ($request->query('source') === 'provider'),
            'edit_sr_no_range'    => $edit_sr_no_range,
            'current_sr_no'       => $plan->sr_no,
            'isMicrosoft'         => $isMicrosoft,
        ];

        return view('superadmin.plans.form', $data);
    }

    public function updateOrder(Request $request)
    {
        try {
            $request->validate([
                'plans' => 'required|array'
            ]);
    
            foreach ($request->plans as $index => $planId) {
                Plan::where('id', $planId)
                    ->update([
                        'sr_no' => $index + 1
                    ]);
            }
    
            return response()->json([
                'success' => true,
                'message' => 'Plan order updated successfully.'
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function save(Request $request, $provider_id, $variant_id)
    {
        try {
            $planId = $request->input('id');
            $isUpdate = !empty($planId);
            $provider = Provider::findOrFail($provider_id);
            $providerHasVariant = (($provider->has_variant ?? 'no') === 'yes');
            $legacyManual = $request->boolean('legacy_manual') && !$isUpdate;
            $resolvedVariantId = (int) $variant_id;

            if ($legacyManual) {
                // ... legacy manual logic ...
            }

            if ($legacyManual && $providerHasVariant && $resolvedVariantId > 0) {
                $dbVariantId = $resolvedVariantId;
            } elseif (!$providerHasVariant) {
                $dbVariantId = null;
            } elseif ($variant_id == 0) {
                $dbVariant = Variant::where('provider_id', $provider_id)->first();
                $dbVariantId = $dbVariant ? $dbVariant->id : null;
            } else {
                $dbVariantId = $variant_id;
            }

            $validator = Validator::make($request->all(), [
                'category_id'  => [
                    'nullable',
                    'integer',
                    function (string $attribute, mixed $value, \Closure $fail): void {
                        if ($value === null || $value === '') {
                            return;
                        }
                        if (! PlanCategory::query()->whereKey((int) $value)->exists()) {
                            $fail('The selected category is invalid.');
                        }
                    },
                ],
                'name'         => 'required|string|max:255',
                'slug'         => [
                    'required',
                    'string',
                    'max:255',
                    function (string $attribute, mixed $value, \Closure $fail) use ($isUpdate, $planId): void {
                        $query = Plan::query()->where('slug', (string) $value);
                        if ($isUpdate) {
                            $query->where('id', '!=', (int) $planId);
                        }
                        if ($query->exists()) {
                            $fail('The slug has already been taken.');
                        }
                    },
                ],
                'price'        => 'required|numeric|min:0',
                'actual_price' => 'required|numeric|min:0',
                'storage'      => 'required|string|max:255',
                'billing_cycle'=> 'required|in:monthly,yearly',
                'limited_offer'=> 'required|in:1,0',
                'customer_limit'=> 'required|numeric|min:1',
                'status2'       => 'required|in:1,0',
                'description'   => 'nullable|string',
                'sr_no'         => $isUpdate ? 'required|integer|min:1' : 'nullable|integer|min:1',
            ]);

            $validator->after(function ($v) use ($request, $provider_id, $providerHasVariant, $dbVariantId, $isUpdate, $planId): void {
                if (!$isUpdate) {
                    return;
                }

                $srNo = max(1, (int) $request->input('sr_no', 1));
                $duplicateSrQuery = Plan::query()
                    ->where('provider_id', $provider_id)
                    ->where('sr_no', $srNo);

                if ($providerHasVariant && $dbVariantId) {
                    $duplicateSrQuery->where('variant_id', $dbVariantId);
                } else {
                    $duplicateSrQuery->where(function ($q) {
                        $q->whereNull('variant_id')->orWhere('variant_id', 0);
                    });
                }

                if ($isUpdate) {
                    $duplicateSrQuery->where('id', '!=', (int) $planId);
                }

                if ($duplicateSrQuery->exists()) {
                    $v->errors()->add('sr_no', 'Sr. No is already used for this provider/variant. Choose another number.');
                }
            });

            $isMicrosoft = $this->isMicrosoftProvider($provider);
            $planColumns = Schema::connection('pgsql_product')->getColumnListing('plans');
            $hasMicrosoftColumns = in_array('subscription_type', $planColumns, true);

            if ($isMicrosoft && $hasMicrosoftColumns) {
                $validator->addRules([
                    'subscription_type' => 'required|in:software,perpetual',
                    'discount_type' => 'nullable|in:rebated,non_rebated',
                    'discount_percentage' => 'nullable|numeric|min:0|max:100',
                    'microsoft_product_id' => 'nullable|string|max:255',
                ]);
                $validator->after(function ($v) use ($request) {
                    if (($request->input('subscription_type') ?? '') === 'software') {
                        if (!in_array($request->input('discount_type'), ['rebated', 'non_rebated'], true)) {
                            $v->errors()->add('discount_type', 'Select rebated or non-rebated discount type.');
                        }
                        if ($request->input('discount_type') === 'rebated') {
                            $pct = $request->input('discount_percentage');
                            if ($pct === null || $pct === '' || !is_numeric($pct) || (float) $pct <= 0) {
                                $v->errors()->add('discount_percentage', 'Enter a discount percentage greater than 0 for rebated plans.');
                            }
                        }
                    }
                });
            }

            if ($validator->fails()) {
                return response()->json(array_merge(
                    ['error' => true, 'message' => $validator->errors()->first()],
                    collect($validator->errors()->messages())->mapWithKeys(function ($messages, $key) {
                        return [$key => $messages[0] ?? ''];
                    })->all()
                ), 422);
            }

            $featuresRaw = $request->input('features', '');
            $featuresArray = array_filter(array_map('trim', explode("\n", str_replace("\r", "", $featuresRaw))));
            $featuresString = implode('|', $featuresArray);

            if ($isUpdate) {
                $plan = Plan::findOrFail($planId);
                $srNo = max(1, (int) $request->input('sr_no', $plan->sr_no ?? 1));
            } else {
                $srNo = $this->resolveNextSrNo($provider_id, $providerHasVariant, $dbVariantId);
            }

            $data = [
                'variant_id'    => $dbVariantId,
                'provider_id'   => $provider_id,
                'category_id'   => $request->category_id,
                'name'          => $request->name,
                'slug'          => $request->slug,
                'price'         => $request->price,
                'actual_price'  => $request->actual_price,
                'storage'       => $request->storage,
                'billing_cycle' => $request->billing_cycle,
                'limited_offer' => $request->limited_offer,
                'customer_limit'=> $request->customer_limit,
                'description'   => $request->description,
                'features'      => $featuresString,
                'status'        => $request->status2,
                'sr_no'         => $srNo,
            ];

            if ($isMicrosoft && $hasMicrosoftColumns) {
                $actualPrice = round((float) $request->actual_price, 2);
                $subscriptionType = strtolower(trim((string) $request->input('subscription_type', '')));
                $data['subscription_type'] = $subscriptionType;
                $data['microsoft_product_id'] = $request->microsoft_product_id;

                if ($subscriptionType === 'perpetual') {
                    $data['discount_type'] = null;
                    $data['discount_percentage'] = 0;
                    $data['price'] = $actualPrice;
                } else {
                    $discountType = strtolower(trim((string) $request->input('discount_type', '')));
                    $data['discount_type'] = $discountType;
                    if ($discountType === 'rebated') {
                        $discountPercent = max(0.0, min(100.0, (float) $request->input('discount_percentage', 0)));
                        $data['discount_percentage'] = $discountPercent;
                        $data['price'] = round($actualPrice * (1 - $discountPercent / 100), 2);
                    } else {
                        $data['discount_percentage'] = 0;
                        $data['price'] = $actualPrice;
                    }
                }
            }

            $data = $this->filterPlanDataForExistingColumns($data, $planColumns);

            if (in_array('form_url', $planColumns, true)) {
                $data['form_url'] = '/superadmin/plans/add-plan/' . $provider_id . '/0';
            }

            if ($isUpdate) {
                $plan->update($data);
                if (Schema::connection('pgsql_product')->hasTable('plan_app')) {
                    $plan->apps()->sync($request->input('apps', []));
                }

                return response()->json(['success' => true, 'message' => 'Plan updated successfully.']);
            }

            $plan = Plan::create($data);
            if (Schema::connection('pgsql_product')->hasTable('plan_app')) {
                $plan->apps()->sync($request->input('apps', []));
            }

            $payload = ['success' => true, 'message' => 'Plan added successfully.'];
            if ($legacyManual) {
                $payload['plan_id'] = (int) $plan->id;
            }

            return response()->json($payload);
        } catch (\Throwable $e) {
            Log::error('PlanController::save failed', [
                'provider_id' => $provider_id,
                'variant_id' => $variant_id,
                'plan_id' => $request->input('id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => true,
                'message' => 'Plan save failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @param  list<string>  $planColumns
     * @return array<string, mixed>
     */
    private function filterPlanDataForExistingColumns(array $data, array $planColumns): array
    {
        return array_intersect_key($data, array_flip($planColumns));
    }

    private function isMicrosoftProvider(object $provider): bool
    {
        $name = strtolower(trim((string) ($provider->name ?? '')));

        return (int) ($provider->id ?? 0) === 2
            || $name === 'microsoft'
            || str_contains($name, 'microsoft')
            || str_contains($name, 'office 365')
            || str_contains($name, 'm365');
    }

    private function resolveNextSrNo(int $providerId, bool $providerHasVariant, ?int $variantId): int
    {
        $query = Plan::query()->where('provider_id', $providerId);

        if ($providerHasVariant && $variantId) {
            $query->where('variant_id', $variantId);
        } else {
            $query->where(function ($q) {
                $q->whereNull('variant_id')->orWhere('variant_id', 0);
            });
        }

        return max(1, ((int) $query->max('sr_no')) + 1);
    }

    private function calculateDiscountedPrice($plan): float
    {
        $actualPrice = (float) ($plan->actual_price ?? 0);
        
        if (isset($plan->subscription_type) && $plan->subscription_type === 'perpetual') {
            return $actualPrice;
        }
        
        if (isset($plan->subscription_type) && $plan->subscription_type === 'software') {
            if (isset($plan->discount_type) && $plan->discount_type === 'rebated') {
                $discountPercent = (float) ($plan->discount_percentage ?? 0);
                if ($discountPercent > 0) {
                    return round($actualPrice * (1 - $discountPercent / 100), 2);
                }
            }
        }
        
        return $actualPrice;
    }
    private function findOrCreateLegacyVariant(int $providerId, string $name, ?int $parentVariantId): int
    {
        $name = trim($name);
        $query = DB::table('variants')
            ->where('provider_id', $providerId)
            ->where('name', $name);
        if ($parentVariantId !== null && $parentVariantId > 0) {
            $query->where('parent_variant_id', $parentVariantId);
        } else {
            $query->where(function ($q) {
                $q->whereNull('parent_variant_id')->orWhere('parent_variant_id', 0);
            });
        }

        $existingId = (int) $query->value('id');
        if ($existingId > 0) {
            return $existingId;
        }

        $cols = Schema::getColumnListing('variants');
        $row = [
            'provider_id' => $providerId,
            'name' => $name,
            'status' => 'active',
        ];
        if ($parentVariantId !== null && $parentVariantId > 0 && in_array('parent_variant_id', $cols, true)) {
            $row['parent_variant_id'] = $parentVariantId;
        }
        if (in_array('slug', $cols, true)) {
            $slugBase = Str::slug($name . '-' . $providerId);
            $slug = $slugBase;
            $n = 0;
            while (DB::table('variants')->where('slug', $slug)->exists()) {
                $n++;
                $slug = $slugBase . '-' . $n;
            }
            $row['slug'] = $slug;
        }
        if (in_array('created_at', $cols, true)) {
            $row['created_at'] = now();
        }
        if (in_array('updated_at', $cols, true)) {
            $row['updated_at'] = now();
        }

        return (int) DB::table('variants')->insertGetId(array_intersect_key($row, array_flip($cols)));
    }

    public function delete(Request $request, $provider_id, $variant_id)
    {
        $plan = Plan::find($request->input('id'));
        if($plan){
            $plan->delete();
            return response()->json(['success' => true, 'message' => 'Plan deleted successfully.']);
        }
        return response()->json(['error' => true, 'message' => 'Plan not found.']);
    }
}