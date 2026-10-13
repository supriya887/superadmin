<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Plan;
use App\Models\Provider;
use App\Models\Variant;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Http\Controllers\Master;
use Illuminate\Support\Facades\DB;

class VariantController extends Controller
{
    protected $master;
    
    public function __construct() {
        $this->master = new Master();
    }
    
    public function variants_validation(Request $request)
    {
        $variantId  = $request->input('id');
        $providerId = $request->input('provider_id');
        // Treat blank / zero id as create (avoid PG casting "" to bigint)
        if ($variantId === '' || $variantId === null || $variantId === '0' || $variantId === 0) {
            $variantId = null;
            $request->merge(['id' => null]);
        }
        $isUpdate   = !empty($variantId);

        if ($request->input('status') === 'deleted') {
            $this->master->check_permission(8, 'Delete');
            $variant = Variant::find($variantId);
            if (!$variant) {
                return response()->json(['success' => false, 'message' => 'Variant not found.'], 404);
            }

            if (Plan::query()->where('variant_id', $variant->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This variant has plans and cannot be deleted.',
                ], 422);
            }

            $variant->delete();

            return response()->json([
                'success' => true,
                'message' => 'Variant deleted successfully.',
            ]);
        }

        $this->master->check_permission(8, $isUpdate ? 'Edit' : 'Add');

        if (!$request->filled('parent_variant_id')) {
            $request->merge(['parent_variant_id' => null]);
        }

        $validator = Validator::make($request->all(), [
            'provider_id'       => 'required|exists:pgsql_product.providers,id',
            'name'              => 'required|string|max:255',
            'parent_variant_id' => 'nullable|integer|exists:pgsql_product.variants,id',
            'v_status'          => 'required|in:active,inactive',
            'sr_no'             => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            $error = $validator->errors();
            return response()->json([
                'error'               => true,
                'provider_id'         => $error->first('provider_id'),
                'name'                => $error->first('name'),
                'parent_variant_id'   => $error->first('parent_variant_id'),
                'v_status'            => $error->first('v_status'),
                'sr_no'               => $error->first('sr_no'),
            ]);
        }

        $parentVariantId = $request->filled('parent_variant_id') ? (int) $request->input('parent_variant_id') : null;

        // --- Plan Migration Validation/Correction ---
        // If updating a variant and changing its provider_id (migrating to a different plan/provider), allow migration ONLY if:
        // - The parent_variant_id is either null or is within the new provider
        if ($isUpdate) {
            $variant = Variant::find($variantId);

            if (!$variant) {
                return response()->json(['success' => false, 'message' => 'Variant not found.']);
            }

            // If the plan/provider is being changed
            if ($providerId && $providerId != $variant->provider_id) {
                // If parent_variant_id is set, ensure it belongs to the new provider as well
                if ($parentVariantId) {
                    $validParent = Variant::where('id', $parentVariantId)
                        ->where('provider_id', $providerId)
                        ->exists();
                    if (!$validParent) {
                        return response()->json([
                            'error' => true,
                            'parent_variant_id' => 'Parent variant must belong to the target plan.'
                        ]);
                    }
                }
            } else {
                // when in same plan, still check that the parent is not self and is valid
                if ($parentVariantId && $parentVariantId === (int) $variantId) {
                    return response()->json([
                        'error' => true,
                        'parent_variant_id' => 'A variant cannot be a child of itself.',
                    ]);
                }
                if ($parentVariantId) {
                    $validParent = Variant::where('id', $parentVariantId)
                        ->where('provider_id', $providerId)
                        ->exists();
                    if (!$validParent) {
                        return response()->json([
                            'error' => true,
                            'parent_variant_id' => 'Please select a valid parent variant from this provider.',
                        ]);
                    }
                }
            }
        } else {
            // For new variants, enforce parent_variant_id is valid or null for that plan/provider
            if ($parentVariantId) {
                $validParent = Variant::where('id', $parentVariantId)
                    ->where('provider_id', $providerId)
                    ->exists();
                if (!$validParent) {
                    return response()->json([
                        'error' => true,
                        'parent_variant_id' => 'Please select a valid parent variant from this provider.',
                    ]);
                }
            }
        }

        if ($request->filled('sr_no')) {
            $sr_no = (int) $request->input('sr_no');
        } else {
            $query = Variant::query()->where('provider_id', $providerId);
            if ($parentVariantId) {
                $query->where('parent_variant_id', $parentVariantId);
            } else {
                $query->whereNull('parent_variant_id');
            }
            if ($isUpdate) {
                $query->where('id', '!=', $variantId);
            }
            $maxSr = $query->max('sr_no');
            $sr_no = is_null($maxSr) ? 1 : $maxSr + 1;
        }

        $data = [
            'provider_id'       => $request->provider_id,
            'parent_variant_id' => $parentVariantId,
            'name'              => $request->name,
            'status'            => $request->v_status,
            'sr_no'             => $sr_no,
        ];

        if ($isUpdate) {
            $success = Variant::where('id', $variantId)->update($data);
            if ($success) {
                $updatedVariant = Variant::find($variantId);
                // Provide latest updated_at as current time
                return response()->json([
                    'success' => true,
                    'message' => 'Variant updated successfully.',
                    'variant' => $updatedVariant,
                    'updated_at' => now()
                ]);
            } else {
                return response()->json(['success' => false, 'message' => 'Failed to update variant, please try again or contact admin.']);
            }
        }

        $variant = Variant::create($data);
        if ($variant) {
            // For new, also return created_at as now for refresh purpose
            return response()->json([
                'success' => true,
                'message' => 'Variant added successfully.',
                'variant' => $variant,
                'created_at' => now()
            ]);
        } else {
            return response()->json(['success' => false, 'message' => 'Failed to add variant, please try again or contact admin.']);
        }
    }

    public function edit($id)
    {
        $this->master->check_permission(8, 'Edit');
        $variant = Variant::findOrFail($id);
        return response()->json($variant);
    }
    
    /**
     * Update the order (sr_no) and parent_variant_id for variants after drag/reorder.
     * Also solves the problem of not updating refresh time by returning accurate time
     */
    public function updateOrder(Request $request)
    {
        $variants = $request->input('variants');
    
        if (!is_array($variants)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid data'
            ]);
        }
    
        $updatedIds = [];
        DB::beginTransaction();

        try {
            foreach ($variants as $item) {
                if (!isset($item['id'])) continue;

                // When changing plan/provider by drag, validate parent_variant_id's provider (if present)
                if (isset($item['parent_variant_id']) && $item['parent_variant_id'] !== null) {
                    $parent = Variant::find($item['parent_variant_id']);
                    $child = Variant::find($item['id']);
                    if ($parent && $child && $parent->provider_id != $child->provider_id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Cannot move variant to another provider\'s hierarchy.',
                        ]);
                    }
                }

                Variant::where('id', $item['id'])->update([
                    'sr_no' => (int) ($item['sr_no'] ?? 0),
                    'parent_variant_id' => array_key_exists('parent_variant_id', $item)
                        ? ($item['parent_variant_id'] !== null ? (int)$item['parent_variant_id'] : null)
                        : null,
                ]);
                $updatedIds[] = $item['id'];
            }

            DB::commit();

            // Always use "now" to indicate latest refresh time after drag/drop
            $updatedVariants = Variant::whereIn('id', $updatedIds)->get();

            return response()->json([
                'success' => true,
                'message' => 'Order updated successfully',
                'updated_variants' => $updatedVariants,
                'updated_at' => now(), // Use the current time for true refresh feedback
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
