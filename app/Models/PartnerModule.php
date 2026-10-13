<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class PartnerModule extends Model
{
    protected $connection = 'pgsql_auth';
    protected $table = 'partner_modules';

    protected $fillable = [
        'group_name',
        'module_key',
        'module_name',
        'sort_order',
        'status',
        'allow_view',
        'allow_add',
        'allow_edit',
        'allow_delete',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'status' => 'integer',
        'allow_view' => 'boolean',
        'allow_add' => 'boolean',
        'allow_edit' => 'boolean',
        'allow_delete' => 'boolean',
    ];

    public function permissions(): HasMany
    {
        return $this->hasMany(PartnerPermission::class, 'module_id');
    }

    /** Whether partner_modules has allow_* columns (cached per request). */
    public static function hasAllowColumns(): bool
    {
        static $cached = null;
        if ($cached === null) {
            try {
                $cached = Schema::connection('pgsql_auth')->hasColumn('partner_modules', 'allow_view');
            } catch (\Throwable $e) {
                $cached = false;
            }
        }

        return $cached;
    }

    /**
     * Which permission checkboxes this module supports in the UI.
     * Dashboard defaults to View-only when columns are missing or unset.
     */
    public function allowFlags(): array
    {
        $isDashboard = strtolower(trim((string) $this->module_key)) === 'dashboard';

        if (self::hasAllowColumns()) {
            $view = $this->allow_view;
            $add = $this->allow_add;
            $edit = $this->allow_edit;
            $delete = $this->allow_delete;

            // Null columns → sensible defaults (dashboard = view only)
            if ($view === null && $add === null && $edit === null && $delete === null) {
                return $isDashboard
                    ? ['allow_view' => true, 'allow_add' => false, 'allow_edit' => false, 'allow_delete' => false]
                    : ['allow_view' => true, 'allow_add' => true, 'allow_edit' => true, 'allow_delete' => true];
            }

            return [
                'allow_view' => $view === null ? true : (bool) $view,
                'allow_add' => $add === null ? !$isDashboard : (bool) $add,
                'allow_edit' => $edit === null ? !$isDashboard : (bool) $edit,
                'allow_delete' => $delete === null ? !$isDashboard : (bool) $delete,
            ];
        }

        return $isDashboard
            ? ['allow_view' => true, 'allow_add' => false, 'allow_edit' => false, 'allow_delete' => false]
            : ['allow_view' => true, 'allow_add' => true, 'allow_edit' => true, 'allow_delete' => true];
    }
}
