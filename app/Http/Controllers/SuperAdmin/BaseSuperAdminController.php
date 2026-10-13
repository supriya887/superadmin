<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Master;

require_once app_path('helper.php');

class BaseSuperAdminController extends Master
{
    protected function authorizeModule(int $moduleId, string $action): void
    {
        if (!permission($moduleId, $action)) {
            abort(403, 'Permission denied.');
        }
    }
}

