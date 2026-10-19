<?php

namespace App\Http\Controllers\SuperAdmin\Crm\Api;

use App\Http\Controllers\SuperAdmin\Crm\DashboardController as WebDashboardController;
use Illuminate\Http\Request;

class ReportController extends WebDashboardController
{
    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Reports fetched successfully.',
            'data' => parent::reports($request)->getData(),
        ]);
    }

    public function show(string $report)
    {
        $page = $this->reportPage($report);
        if (!$page) {
            return response()->json([
                'success' => false,
                'message' => 'Report not found.',
                'data' => null,
            ], 404);
        }

        $rows = collect($page['rows'])->values();

        return response()->json([
            'success' => true,
            'message' => 'Report fetched successfully.',
            'data' => [
                'report' => $report,
                'title' => $page['title'],
                'columns' => $page['columns'],
                'count' => $rows->count(),
                'rows' => $rows,
            ],
        ]);
    }
}
