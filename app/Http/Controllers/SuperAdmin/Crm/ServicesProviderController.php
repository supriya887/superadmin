<?php

namespace App\Http\Controllers\SuperAdmin\Crm;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Provider;
use Illuminate\Http\Request;

class ServicesProviderController extends Controller
{
    public function index()
    {
        $providers = Provider::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('superadmin.crm.providers.index', compact('providers'));
    }

    public function plans(Request $request, Provider $provider)
    {
        $plans = Plan::query()
            ->with(['category', 'variant.parent'])
            ->where('provider_id', $provider->id)
            ->orderBy('sr_no')
            ->orderBy('id')
            ->get();

        $isTizzy = str_contains(strtolower(($provider->name ?? '') . ' ' . ($provider->slug ?? '')), 'tizzy');

        if ($isTizzy) {
            $filterMenu = collect();
            $visibleCategories = collect(['all' => $plans]);

            return view('superadmin.crm.providers.plans', compact(
                'provider',
                'plans',
                'filterMenu',
                'visibleCategories'
            ));
        }

        $planGroups = $plans->groupBy(function ($plan) {
            return (int) ($plan->variant_id ?: 0);
        });

        $variantMenu = $planGroups->keys()->map(function ($variantId) use ($plans, $provider) {
            $variantId = (int) $variantId;
            $plan = $plans->first(fn ($row) => (int) ($row->variant_id ?: 0) === $variantId);
            $variant = optional($plan)->variant;
            $name = trim((string) optional($variant)->name);
            $parent = trim((string) optional(optional($variant)->parent)->name);
            if ($variantId === 0) {
                $name = (($provider->has_variant ?? 'no') === 'yes') ? 'Other' : $provider->name;
            } elseif ($parent !== '' && $name !== '') {
                $name = $parent . ' / ' . $name;
            } elseif ($name === '') {
                $name = $provider->name;
            }

            return [
                'id' => $variantId,
                'name' => $name,
            ];
        })->values();

        $selectedVariant = (int) $request->get('variant', $variantMenu->first()['id'] ?? 0);
        if (!$planGroups->has($selectedVariant)) {
            $selectedVariant = (int) ($variantMenu->first()['id'] ?? 0);
        }

        $filterMenu = $variantMenu->count() > 1
            ? $variantMenu->map(fn ($item) => [
                'key' => (string) $item['id'],
                'name' => $item['name'],
                'url' => route('crm.providers.plans', ['provider' => $provider, 'variant' => $item['id']]),
                'active' => (int) $selectedVariant === (int) $item['id'],
            ])
            : collect();

        $visiblePlans = $filterMenu->isEmpty()
            ? $plans
            : $plans->filter(fn ($plan) => (int) ($plan->variant_id ?: 0) === (int) $selectedVariant)->values();

        $visibleCategories = collect(['all' => $visiblePlans]);

        return view('superadmin.crm.providers.plans', compact(
            'provider',
            'plans',
            'filterMenu',
            'visibleCategories'
        ));
    }
}
