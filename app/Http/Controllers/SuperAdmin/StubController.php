<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;

/**
 * Placeholder controller for modules not yet implemented.
 * Replace individual methods with real controllers as modules are built.
 */
class StubController extends Controller
{
    // ── Catalog ─────────────────────────────────────────────
    public function products()    { return $this->stub('Products',     'superadmin.products.index'); }
    public function plans()       { return $this->stub('Plans',        'superadmin.plans.index'); }
    public function variants()    { return $this->stub('Variants',     'superadmin.variants.index'); }
    public function options()     { return $this->stub('Options',      'superadmin.options.index'); }

    // ── SKU Engine ───────────────────────────────────────────
    public function skuEngine()   { return $this->stub('SKU Engine',   'superadmin.sku.index'); }
    public function pricing()     { return $this->stub('Pricing',      'superadmin.pricing.index'); }

    // ── Commerce ─────────────────────────────────────────────
    public function orders()      { return $this->stub('Orders',       'superadmin.orders.index'); }
    public function subscriptions(){ return $this->stub('Subscriptions','superadmin.subscriptions.index'); }

    // ── Operations ───────────────────────────────────────────
    public function provisioning(){ return $this->stub('Provisioning', 'superadmin.provisioning.index'); }
    public function notifications(){ return $this->stub('Notifications','superadmin.notifications.index'); }

    // ────────────────────────────────────────────────────────
    private function stub(string $title, string $routeName)
    {
        return view('superadmin.stub', compact('title', 'routeName'));
    }
}
