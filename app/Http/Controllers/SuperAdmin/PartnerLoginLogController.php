<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PartnerLoginLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PartnerLoginLogController extends Controller
{
    public function index(Request $request): View
    {
        return view('superadmin.partner_logs.index');
    }

    public function table(Request $request): JsonResponse
    {
        $draw = (int) $request->get('draw');
        $start = (int) $request->get('start', 0);
        $rowPerPage = (int) $request->get('length', 10);
        $orderArray = $request->get('order', []);
        $columnNameArray = $request->get('columns', []);

        $searchIdentifier = trim((string) $request->get('search_identifier', ''));
        $searchAction = trim((string) $request->get('search_action', ''));
        $searchPartner = trim((string) $request->get('search_partner', ''));
        $globalSearchValue = (string) ($request->get('search')['value'] ?? '');

        $sortableColumns = ['id', 'identifier', 'action', 'ip_address', 'created_at'];
        if (!empty($orderArray)) {
            $columnIndex = (int) $orderArray[0]['column'];
            $columnSortOrder = ($orderArray[0]['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
            $columnName = $columnNameArray[$columnIndex]['data'] ?? 'id';
            if (!in_array($columnName, $sortableColumns, true)) {
                $columnName = 'id';
            }
        } else {
            $columnSortOrder = 'desc';
            $columnName = 'id';
        }

        $baseQuery = PartnerLoginLog::query()->with('partner');
        $query = clone $baseQuery;

        if ($searchIdentifier !== '') {
            $query->where('identifier', 'like', '%' . $searchIdentifier . '%');
        }

        if ($searchAction !== '') {
            $query->where('action', $searchAction);
        }

        if ($searchPartner !== '') {
            $query->where(function ($q) use ($searchPartner) {
                $q->whereHas('partner', function ($partnerQuery) use ($searchPartner) {
                    $partnerQuery->where('name', 'like', '%' . $searchPartner . '%')
                        ->orWhere('company_name', 'like', '%' . $searchPartner . '%')
                        ->orWhere('email', 'like', '%' . $searchPartner . '%');
                });

                if (ctype_digit($searchPartner)) {
                    $q->orWhere('partner_id', (int) $searchPartner);
                }
            });
        }

        if ($globalSearchValue !== '') {
            $query->where(function ($q) use ($globalSearchValue) {
                $q->where('identifier', 'like', '%' . $globalSearchValue . '%')
                    ->orWhere('action', 'like', '%' . $globalSearchValue . '%')
                    ->orWhere('ip_address', 'like', '%' . $globalSearchValue . '%')
                    ->orWhere('payload', 'like', '%' . $globalSearchValue . '%')
                    ->orWhere('user_agent', 'like', '%' . $globalSearchValue . '%')
                    ->orWhereHas('partner', function ($partnerQuery) use ($globalSearchValue) {
                        $partnerQuery->where('name', 'like', '%' . $globalSearchValue . '%')
                            ->orWhere('company_name', 'like', '%' . $globalSearchValue . '%')
                            ->orWhere('email', 'like', '%' . $globalSearchValue . '%');
                    });
            });
        }

        $totalData = (clone $baseQuery)->count();
        $totalFiltered = (clone $query)->count();

        $logs = $query
            ->orderBy($columnName, $columnSortOrder)
            ->offset($start)
            ->limit($rowPerPage)
            ->get();

        $data = [];
        foreach ($logs as $log) {
            $partner = $log->partner;
            $partnerLabel = '—';
            if ($partner) {
                $partnerLabel = trim((string) ($partner->name ?? $partner->company_name ?? ''));
                if ($partnerLabel === '') {
                    $partnerLabel = (string) ($partner->email ?? 'Partner #' . $log->partner_id);
                }
            } elseif (!empty($log->partner_id)) {
                $partnerLabel = 'Partner #' . $log->partner_id;
            }

            $data[] = [
                'id' => $log->id,
                'partner' => $partnerLabel,
                'identifier' => $log->identifier ?? '—',
                'action' => $this->formatLoginActionBadge((string) ($log->action ?? '')),
                'ip_address' => $log->ip_address ?? '—',
                'user_agent' => $this->truncateText((string) ($log->user_agent ?? '—'), 80),
                'payload' => $this->truncateText((string) ($log->payload ?? '—'), 100),
                'created_at' => $log->created_at
                    ? $log->created_at->format('d M Y, h:i A')
                    : '—',
            ];
        }

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $totalData,
            'recordsFiltered' => $totalFiltered,
            'data' => $data,
        ]);
    }

    private function formatLoginActionBadge(string $action): string
    {
        $normalized = strtolower(trim($action));
        $label = $normalized !== '' ? str_replace('_', ' ', ucwords($normalized, '_')) : 'Unknown';

        $class = match ($normalized) {
            'login_success' => 'bg-success',
            'login_failed' => 'bg-danger',
            'otp_sent' => 'bg-info text-dark',
            'suspicious_activity' => 'bg-warning text-dark',
            default => 'bg-secondary',
        };

        return '<span class="badge ' . $class . '">' . e($label) . '</span>';
    }

    private function truncateText(string $value, int $length): string
    {
        $value = trim($value);
        if ($value === '') {
            return '—';
        }

        return strlen($value) > $length
            ? substr($value, 0, $length) . '...'
            : $value;
    }
}
