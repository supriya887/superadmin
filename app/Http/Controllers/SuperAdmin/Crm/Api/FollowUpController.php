<?php

namespace App\Http\Controllers\SuperAdmin\Crm\Api;

use App\Http\Controllers\SuperAdmin\Crm\FollowUpController as WebFollowUpController;
use App\Models\Lead;
use Illuminate\Http\Request;

class FollowUpController extends WebFollowUpController
{
    public function index(Request $request)
    {
        $leads = parent::index($request)->getData()['leads'];

        return response()->json([
            'success' => true,
            'message' => 'Follow-ups fetched successfully.',
            'data' => [
                'followups' => $leads->items(),
                'pagination' => [
                    'current_page' => $leads->currentPage(),
                    'last_page' => $leads->lastPage(),
                    'per_page' => $leads->perPage(),
                    'total' => $leads->total(),
                ],
                'filters' => ['all', 'overdue', 'today', 'upcoming'],
            ],
        ]);
    }

    public function show(Lead $lead)
    {
        $data = parent::show($lead)->getData();

        return response()->json([
            'success' => true,
            'message' => 'Follow-up fetched successfully.',
            'data' => [
                'lead' => $data['lead'],
                'history' => $data['followups'],
                'assignees' => $data['assignees'],
                'statuses' => ['New', 'Contacted', 'Qualified', 'Negotiation', 'Quote Send', 'Won', 'Lost', 'Nurturing'],
            ],
        ]);
    }

    public function store(Request $request, Lead $lead)
    {
        parent::store($request, $lead);

        return response()->json([
            'success' => true,
            'message' => 'Follow-up added.',
            'data' => [
                'lead' => $lead->refresh(),
                'history' => $this->entriesFor($lead),
            ],
        ], 201);
    }
}
