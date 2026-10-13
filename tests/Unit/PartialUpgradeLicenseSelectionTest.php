<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\PartnerAuthController;
use Illuminate\Http\Request;
use Tests\TestCase;

class PartialUpgradeLicenseSelectionTest extends TestCase
{
    public function test_partial_upgrade_keeps_selected_license_count_below_total(): void
    {
        $controller = new PartnerAuthController();
        $request = new Request();
        $request->merge(['licenses' => 2]);

        $method = new \ReflectionMethod(PartnerAuthController::class, 'resolveSelectedPartialUpgradeLicenses');
        $method->setAccessible(true);

        $selected = $method->invokeArgs($controller, [$request, null, 4, 1]);

        $this->assertSame(2, $selected);

        $request = new Request();
        $request->merge(['licenses' => 4]);
        $selected = $method->invokeArgs($controller, [$request, null, 4, 1]);

        $this->assertSame(3, $selected);
    }
}
