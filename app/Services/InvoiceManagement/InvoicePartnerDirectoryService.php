<?php

namespace App\Services\InvoiceManagement;

use App\Models\Invoice;
use App\Models\Partner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InvoicePartnerDirectoryService
{
    /**
     * Partners from `partners` (B2B directory). Pending = invoices not in success/fail buckets.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Partner&object{pending_invoices_count:int}>
     */
    public function listPartnersWithPendingCounts()
    {
        if (!Schema::hasTable('partners')) {
            return collect();
        }

        $pendingExpr = InvoiceStatusNormalizer::isPendingSql('i.status');

        return Partner::query()
            ->select(['partners.id', 'partners.name', 'partners.company_name', 'partners.email'])
            ->when(Schema::hasTable('invoice_mst'), function ($q) use ($pendingExpr) {
                $q->selectSub(function ($sub) use ($pendingExpr) {
                    $sub->from('invoice_mst as i')
                        ->selectRaw('COUNT(*)')
                        ->whereColumn('i.partner_id', 'partners.id')
                        ->whereRaw($pendingExpr);
                }, 'pending_invoices_count');
            }, function ($q) {
                $q->selectRaw('0 as pending_invoices_count');
            })
            ->orderBy('partners.company_name')
            ->orderBy('partners.name')
            ->get();
    }

    /**
     * @return array{partner: Partner, invoices: array<int, array<string, mixed>>}|null
     */
    public function getPartnerInvoiceDashboard(int $partnerId): ?array
    {
        $partner = Partner::query()->find($partnerId);
        if (!$partner) {
            return null;
        }

        if (!Schema::hasTable('invoice_mst')) {
            return [
                'partner'  => $partner,
                'invoices' => [],
            ];
        }

        $hasOrders = Schema::hasTable('orders');
        $hasCustomers = Schema::hasTable('customers');

        $q = Invoice::query()->where('invoice_mst.partner_id', $partnerId);

        if ($hasOrders) {
            $q->leftJoin('orders', 'orders.id', '=', 'invoice_mst.order_id');
        }
        if ($hasCustomers) {
            $q->leftJoin('customers', 'customers.id', '=', 'invoice_mst.customer_id');
            if ($hasOrders) {
                $q->leftJoin('customers as order_cust', 'order_cust.id', '=', 'orders.user_id');
            }
        }

        if ($hasCustomers && $hasOrders) {
            $companySelect = "COALESCE(NULLIF(TRIM(customers.company_name), ''), NULLIF(TRIM(customers.name), ''), NULLIF(TRIM(order_cust.company_name), ''), NULLIF(TRIM(order_cust.name), ''), '—')";
        } elseif ($hasCustomers) {
            $companySelect = "COALESCE(NULLIF(TRIM(customers.company_name), ''), NULLIF(TRIM(customers.name), ''), '—')";
        } else {
            $companySelect = "'—'";
        }

        $rows = $q->select([
            'invoice_mst.id',
            'invoice_mst.order_id',
            DB::raw("COALESCE(invoice_mst.invoice_no, invoice_mst.bill_no_full, '') as invoice_no"),
            DB::raw("COALESCE(invoice_mst.total_amount, invoice_mst.net_amt, invoice_mst.amount, 0) as total_amount"),
            DB::raw("COALESCE(invoice_mst.amount, invoice_mst.net_amt, 0) as amount"),
            DB::raw("COALESCE(invoice_mst.invoice_date, invoice_mst.bill_date) as invoice_date"),
            'invoice_mst.status',
            DB::raw($companySelect . ' as company_display'),
        ])
            ->orderByDesc('invoice_mst.id')
            ->get();

        $invoices = $rows->map(function ($row) {
            $status = (string) ($row->status ?? '');
            $tab = InvoiceStatusNormalizer::tab($status);
            $amount = (float) ($row->total_amount ?? $row->amount ?? 0);
            $date = $row->invoice_date
                ? (string) $row->invoice_date
                : '';
            $orderId = (int) ($row->order_id ?? 0);

            return [
                'id'          => (int) $row->id,
                'order_id'    => $orderId,
                'invoice_no'  => (string) ($row->invoice_no ?? ''),
                'company'     => (string) ($row->company_display ?? '—'),
                'amount'      => $amount,
                'date'        => $date,
                'status'      => $status,
                'tab'         => $tab,
                'invoice_url' => $orderId > 0
                    ? route('superadmin.orders.invoice', ['id' => $orderId])
                    : '',
            ];
        })->values()->all();

        return [
            'partner'  => $partner,
            'invoices' => $invoices,
        ];
    }
}
