<?php

namespace App\Http\Controllers\SuperAdmin\Crm\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SuperAdmin\Crm\CrmSetting\DesignationController;
use App\Http\Controllers\SuperAdmin\Crm\CrmSetting\LeadBifurcationController;
use App\Http\Controllers\SuperAdmin\Crm\CrmSetting\LeadPriorityController;
use App\Http\Controllers\SuperAdmin\Crm\CrmSetting\LeadSourceController;
use App\Http\Controllers\SuperAdmin\Crm\CrmSetting\LeadStatusController;
use App\Models\CrmDesignation;
use App\Models\CrmLeadBifurcation;
use App\Models\CrmLeadPriority;
use App\Models\CrmLeadSource;
use App\Models\CrmLeadStatus;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function sources()
    {
        return $this->list('Lead sources fetched successfully.', app(LeadSourceController::class)->index()->getData()['sources']);
    }

    public function storeSource(Request $request)
    {
        app(LeadSourceController::class)->store($request);

        return $this->saved('Lead source saved.', CrmLeadSource::latest('id')->first(), 201);
    }

    public function updateSource(Request $request, $id)
    {
        app(LeadSourceController::class)->update($request, $id);

        return $this->saved('Lead source updated.', CrmLeadSource::find($id));
    }

    public function deleteSource($id)
    {
        app(LeadSourceController::class)->destroy($id);

        return $this->saved('Lead source deleted.');
    }

    public function statuses()
    {
        return $this->list('Lead statuses fetched successfully.', app(LeadStatusController::class)->index()->getData()['leadStatuses']);
    }

    public function storeStatus(Request $request)
    {
        app(LeadStatusController::class)->store($request);

        return $this->saved('Lead status saved.', CrmLeadStatus::latest('id')->first(), 201);
    }

    public function updateStatus(Request $request, $id)
    {
        app(LeadStatusController::class)->update($request, $id);

        return $this->saved('Lead status updated.', CrmLeadStatus::find($id));
    }

    public function deleteStatus($id)
    {
        app(LeadStatusController::class)->destroy($id);

        return $this->saved('Lead status deleted.');
    }

    public function bifurcations()
    {
        return $this->list('Lead bifurcations fetched successfully.', app(LeadBifurcationController::class)->index()->getData()['bifurcations']);
    }

    public function storeBifurcation(Request $request)
    {
        app(LeadBifurcationController::class)->store($request);

        return $this->saved('Lead bifurcation saved.', CrmLeadBifurcation::latest('id')->first(), 201);
    }

    public function updateBifurcation(Request $request, $id)
    {
        app(LeadBifurcationController::class)->update($request, $id);

        return $this->saved('Lead bifurcation updated.', CrmLeadBifurcation::find($id));
    }

    public function deleteBifurcation($id)
    {
        app(LeadBifurcationController::class)->destroy($id);

        return $this->saved('Lead bifurcation deleted.');
    }

    public function priorities()
    {
        return $this->list('Lead priorities fetched successfully.', app(LeadPriorityController::class)->index()->getData()['priorities']);
    }

    public function storePriority(Request $request)
    {
        app(LeadPriorityController::class)->store($request);

        return $this->saved('Lead priority saved.', CrmLeadPriority::latest('id')->first(), 201);
    }

    public function updatePriority(Request $request, $id)
    {
        app(LeadPriorityController::class)->update($request, $id);

        return $this->saved('Lead priority updated.', CrmLeadPriority::find($id));
    }

    public function deletePriority($id)
    {
        app(LeadPriorityController::class)->destroy($id);

        return $this->saved('Lead priority deleted.');
    }

    public function designations(Request $request)
    {
        $data = app(DesignationController::class)->index($request)->getData();

        return response()->json([
            'success' => true,
            'message' => 'Designations fetched successfully.',
            'data' => [
                'designations' => $data['designations'],
                'summary' => [
                    'total' => $data['totalDesignations'],
                    'employees' => $data['totalEmployees'],
                    'most_common' => $data['mostCommonDesignation'],
                    'management' => $data['managementDesignations'],
                ],
            ],
        ]);
    }

    public function storeDesignation(Request $request)
    {
        app(DesignationController::class)->store($request);

        return $this->saved('Designation saved.', CrmDesignation::latest('id')->first(), 201);
    }

    public function updateDesignation(Request $request, $id)
    {
        app(DesignationController::class)->update($request, $id);

        return $this->saved('Designation updated.', CrmDesignation::find($id));
    }

    public function deleteDesignation($id)
    {
        app(DesignationController::class)->destroy($id);

        return $this->saved('Designation deleted.');
    }

    protected function list(string $message, $items)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => ['items' => $items],
        ]);
    }

    protected function saved(string $message, $item = null, int $status = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $item ? ['item' => $item] : null,
        ], $status);
    }
}
