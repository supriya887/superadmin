<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Master;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminSettingController extends Controller
{
    protected Master $master;

    public function __construct(Master $master)
    {
        $this->master = $master;
    }

    public function index(): View
    {
        if (method_exists($this->master, 'check_permission')) {
            $this->master->check_permission(11, 'View');
        }

        $settings = DB::table('admin_settings')->where('id', 1)->first();
        if (!$settings) {
            $settings = DB::table('admin_settings')->orderBy('id')->first();
        }

        return view('superadmin.settings.index', [
            'title' => 'Admin Settings',
            'pageTitle' => 'Admin Settings',
            'settings' => $settings,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        // Hamesha pahilya row var update hoil, karan ya table madhe ekach row asto.

        if (method_exists($this->master, 'check_permission')) {
            $this->master->check_permission(11, 'Edit');
        }

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'address' => ['nullable', 'string', 'max:1000'],
            'pincode' => ['nullable', 'string', 'max:20'],
            'transfer_code' => ['nullable', 'string', 'max:255'],
            'google_transfer_code' => ['nullable', 'string', 'max:255'],
            'microsoft_transfer_code' => ['nullable', 'string', 'max:255'],
            'invoice_prefix' => ['nullable', 'string', 'max:20'],
            'subscription_prefix' => ['nullable', 'string', 'max:20'],
            'upgrade_prefix' => ['nullable', 'string', 'max:20'],
            'renewal_prefix' => ['nullable', 'string', 'max:20'],
            'new_order_prefix' => ['nullable', 'string', 'max:20'],
        ]);

        $columns = Schema::getColumnListing('admin_settings');
        $payload = [];

        foreach (['title', 'pincode', 'transfer_code', 'google_transfer_code', 'microsoft_transfer_code'] as $field) {
            if (in_array($field, $columns, true)) {
                $payload[$field] = $validated[$field] ?? null;
            }
        }

        $address = trim((string) ($validated['address'] ?? ''));
        if (in_array('address', $columns, true)) {
            $payload['address'] = $address;
        }
        // Some DBs use legacy column name `addres`.
        if (in_array('addres', $columns, true)) {
            $payload['addres'] = $address;
        }

        $invoicePrefix = strtoupper(trim((string) ($validated['invoice_prefix'] ?? '')));
        $subscriptionPrefix = strtoupper(trim((string) ($validated['subscription_prefix'] ?? '')));
        $upgradePrefix = strtoupper(trim((string) ($validated['upgrade_prefix'] ?? '')));
        $renewalPrefix = strtoupper(trim((string) ($validated['renewal_prefix'] ?? '')));
        $newOrderPrefix = strtoupper(trim((string) ($validated['new_order_prefix'] ?? '')));

        if (in_array('invoice_prefix', $columns, true)) {
            $payload['invoice_prefix'] = $invoicePrefix;
        }
        if (in_array('subscription_prefix', $columns, true)) {
            $payload['subscription_prefix'] = $subscriptionPrefix;
        }
        if (in_array('upgrade_prefix', $columns, true)) {
            $payload['upgrade_prefix'] = $upgradePrefix;
        }
        if (in_array('renewal_prefix', $columns, true)) {
            $payload['renewal_prefix'] = $renewalPrefix;
        }
        if (in_array('new_order_prefix', $columns, true)) {
            $payload['new_order_prefix'] = $newOrderPrefix;
        }

        if (in_array('updated_at', $columns, true)) {
            $payload['updated_at'] = now();
        }

        // Always update first row only, jya madhe id lavna garjecha nahi, fakt first row update kara.
        // Pahilya row cha id find kara (minimum id).
        $firstRow = DB::table('admin_settings')->orderBy('id')->first();

        if ($request->hasFile('logo') && in_array('logo', $columns, true)) {
            if ($firstRow && !empty($firstRow->logo) && !str_contains((string) $firstRow->logo, 'http')) {
                Storage::disk('public')->delete((string) $firstRow->logo);
            }
            $payload['logo'] = $request->file('logo')->store('settings/logo', 'public');
        }

        if ($firstRow) {
            DB::table('admin_settings')->where('id', $firstRow->id)->update($payload);
        } else {
            if (in_array('created_at', $columns, true)) {
                $payload['created_at'] = now();
            }
            DB::table('admin_settings')->insert($payload);
        }

        return redirect()
            ->route('superadmin.settings.index')
            ->with('success', 'Admin settings saved successfully.');
    }
}
