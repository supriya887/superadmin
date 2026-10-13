<?php

namespace App\Services;

use App\Support\GstSplit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Sandbox.co.in GST E-Invoicing client.
 *
 * Persistence model (changed):
 *   - All e-invoice artefacts (irn, signed_qr_code, signed_invoice,
 *     acknowledgement_no, acknowledgement_date, e_invoice_pdf_url,
 *     is_cancelled) are stored on the `invoices` table — NOT on `orders`.
 *   - Invoice line/tax breakdown is read from `invoice_mst` + `invoice_sub`
 *     (the GST invoice ledger created by App\Services\InvoiceService).
 *
 * Public API:
 *   - generateForOrder(int $orderId): array     — fire after invoice exists
 *   - generateForInvoice(int $invoiceId): array — direct, when you have it
 *   - cancelIrn(string $irn, string $reason='1', string $remarks=''): array
 *   - authenticate(bool $forceFresh = false): ?string
 *
 * Failure-handling contract: NONE of the public methods throw. They return
 * `['ok' => false, 'error' => '...']` and log the underlying exception so
 * the caller's order-save / invoice-save flow is never interrupted.
 */
class SandboxEInvoiceService
{
    private string $baseUrl;
    private ?string $apiKey;
    private ?string $apiSecret;
    private string $apiVersion;
    private string $gstApiVersion;
    private int $timeout;

    private const CACHE_KEY = 'sandbox_einvoice_token';

    public function __construct()
    {
        $this->baseUrl       = rtrim((string) config('services.sandbox.base_url', 'https://api.sandbox.co.in'), '/');
        $this->apiKey        = config('services.sandbox.api_key');
        $this->apiSecret     = config('services.sandbox.api_secret');
        $this->apiVersion    = (string) config('services.sandbox.api_version', '1.0');
        $this->gstApiVersion = (string) config('services.sandbox.gst_api_version', 'GST_EINV_1.03');
        $this->timeout       = (int) config('services.sandbox.timeout', 20);
    }

    /* ====================================================================
     * Public API
     * ================================================================== */

    /**
     * Generate IRN/QR for an order. Resolves the matching `invoices` row
     * (creating a thin one if necessary), builds the payload from
     * invoice_mst + invoice_sub, calls Sandbox, persists the result on the
     * `invoices` row.
     *
     * @return array{ok:bool,irn?:string,acknowledgement_no?:string,acknowledgement_date?:string,signed_qr_code?:string,error?:string}
     */
    public function generateForOrder(int $orderId): array
    {
        try {
            $invoiceId = $this->resolveOrCreateInvoiceRow($orderId);
            if ($invoiceId === null) {
                return ['ok' => false, 'error' => 'invoices_table_unavailable'];
            }
            return $this->generateForInvoice($invoiceId);
        } catch (Throwable $e) {
            Log::error('Sandbox e-invoice generateForOrder failed', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Regenerate IRN/QR for an order whose details have been edited.
     *
     * Workflow:
     *   1. Look up the latest `invoices` row for this order.
     *   2. If a non-cancelled IRN exists, call Sandbox cancel first (best-effort).
     *   3. Wipe stale e-invoice fields on the row so generateForInvoice() proceeds.
     *   4. Call generateForOrder() to mint a fresh IRN with the new details.
     *
     * Cancel failures are logged but do NOT block regeneration — the new IRN
     * is what matters; an orphaned IRN can be cancelled manually if needed.
     *
     * @return array{ok:bool,cancelled?:bool,cancel_error?:string,irn?:string,error?:string,...}
     */
    public function regenerateForOrder(int $orderId, string $cancelReason = '2', string $cancelRemarks = 'Order edited — regenerating IRN'): array
    {
        try {
            if (!Schema::hasTable('invoice_mst')) {
                return ['ok' => false, 'error' => 'invoice_mst_table_missing'];
            }

            $cancelled    = false;
            $cancelError  = null;
            $invoiceRow   = DB::table('invoice_mst')->where('order_id', $orderId)->orderByDesc('id')->first();

            if ($invoiceRow && !empty($invoiceRow->irn) && empty($invoiceRow->is_cancelled)) {
                $cancelResult = $this->cancelIrn((string) $invoiceRow->irn, $cancelReason, $cancelRemarks);
                $cancelled    = (bool) ($cancelResult['ok'] ?? false);
                if (!$cancelled) {
                    $cancelError = $cancelResult['error'] ?? 'cancel_failed';
                    Log::warning('Sandbox cancel failed during regenerate (continuing)', [
                        'order_id' => $orderId,
                        'irn'      => $invoiceRow->irn,
                        'error'    => $cancelError,
                    ]);
                }
            }

            // Wipe stale fields on the row so generateForInvoice() will not
            // short-circuit on the existing IRN. We keep the row itself so
            // related joins / FKs remain stable.
            if ($invoiceRow) {
                try {
                    $cols   = Schema::getColumnListing('invoice_mst');
                    $update = ['updated_at' => now()];
                    foreach ([
                        'irn', 'signed_qr_code', 'signed_invoice', 'e_invoice_pdf_url',
                        'acknowledgement_no', 'acknowledgement_date',
                    ] as $c) {
                        if (in_array($c, $cols, true)) {
                            $update[$c] = null;
                        }
                    }
                    if (in_array('is_cancelled', $cols, true)) {
                        $update['is_cancelled'] = 0;
                    }
                    if (in_array('status', $cols, true)) {
                        $update['status'] = 'pending';
                    }
                    DB::table('invoice_mst')->where('id', $invoiceRow->id)->update($update);
                } catch (Throwable $e) {
                    Log::warning('Failed to clear stale e-invoice fields before regenerate', [
                        'order_id' => $orderId,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }

            $result = $this->generateForOrder($orderId);

            return array_merge($result, [
                'cancelled'    => $cancelled,
                'cancel_error' => $cancelError,
            ]);
        } catch (Throwable $e) {
            Log::error('Sandbox e-invoice regenerateForOrder failed', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Generate IRN/QR for an existing `invoices` row id and persist back.
     *
     * @return array{ok:bool,irn?:string,acknowledgement_no?:string,acknowledgement_date?:string,signed_qr_code?:string,signed_invoice?:string,raw?:array,error?:string}
     */
    public function generateForInvoice(int $invoiceId): array
    {
        try {
            if (!Schema::hasTable('invoice_mst')) {
                return ['ok' => false, 'error' => 'invoice_mst_table_missing'];
            }

            $invoice = DB::table('invoice_mst')->where('id', $invoiceId)->first();
            if (!$invoice) {
                return ['ok' => false, 'error' => 'invoice_not_found'];
            }

            // Already generated and not cancelled? Skip silently — idempotent.
            if (!empty($invoice->irn) && empty($invoice->is_cancelled)) {
                return [
                    'ok'                   => true,
                    'irn'                  => (string) $invoice->irn,
                    'acknowledgement_no'   => (string) ($invoice->acknowledgement_no ?? ''),
                    'acknowledgement_date' => (string) ($invoice->acknowledgement_date ?? ''),
                    'signed_qr_code'       => (string) ($invoice->signed_qr_code ?? ''),
                ];
            }

            $orderId = (int) ($invoice->order_id ?? 0);
            if ($orderId <= 0) {
                return ['ok' => false, 'error' => 'invoice_missing_order_id'];
            }

            $payload = $this->buildEInvoicePayload($orderId, (int) $invoiceId);
            $result  = $this->generateIrn($payload);

            if (!($result['ok'] ?? false)) {
                return $result;
            }

            $this->persistEInvoiceResponse((int) $invoiceId, $result);

            return $result;
        } catch (Throwable $e) {
            Log::error('Sandbox e-invoice generateForInvoice failed', [
                'invoice_id' => $invoiceId,
                'error'      => $e->getMessage(),
            ]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Low-level: send a prepared payload to the Sandbox generate endpoint.
     *
     * @param  array<string,mixed> $payload
     * @return array{ok:bool,irn?:string,acknowledgement_no?:string,acknowledgement_date?:string,signed_qr_code?:string,signed_invoice?:string,raw?:array,error?:string}
     */
    public function generateIrn(array $payload): array
    {
        try {
            $token = $this->authenticate();
            if ($token === null) {
                return ['ok' => false, 'error' => 'sandbox_auth_failed'];
            }

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                    'Authorization' => $token,
                    'x-api-key'     => (string) $this->apiKey,
                    'x-api-version' => $this->gstApiVersion,
                ])
                ->post($this->baseUrl . '/gst/compliance/e-invoice/generate', $payload);

            if (!$response->successful()) {
                Log::warning('Sandbox e-invoice generate failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return ['ok' => false, 'error' => 'generate_http_' . $response->status(), 'raw' => $response->json() ?? []];
            }

            $body = $response->json();
            $data = data_get($body, 'data.0', data_get($body, 'data', []));

            $irn      = (string) (data_get($data, 'Irn') ?? data_get($data, 'irn') ?? '');
            $ackNo    = (string) (data_get($data, 'AckNo') ?? data_get($data, 'ack_no') ?? '');
            $ackDate  = (string) (data_get($data, 'AckDt') ?? data_get($data, 'ack_date') ?? '');
            $signedQr = (string) (data_get($data, 'SignedQRCode') ?? data_get($data, 'signed_qr_code') ?? '');
            $signedIn = (string) (data_get($data, 'SignedInvoice') ?? data_get($data, 'signed_invoice') ?? '');
            $pdfUrl   = (string) (data_get($data, 'EinvoicePdf') ?? data_get($data, 'e_invoice_pdf_url') ?? '');

            if ($irn === '') {
                Log::warning('Sandbox e-invoice generate returned no IRN', ['body' => $body]);
                return ['ok' => false, 'error' => 'irn_missing_in_response', 'raw' => $body ?? []];
            }

            return [
                'ok'                   => true,
                'irn'                  => $irn,
                'acknowledgement_no'   => $ackNo,
                'acknowledgement_date' => $ackDate,
                'signed_qr_code'       => $signedQr,
                'signed_invoice'       => $signedIn,
                'e_invoice_pdf_url'    => $pdfUrl,
                'raw'                  => $body ?? [],
            ];
        } catch (Throwable $e) {
            Log::error('Sandbox e-invoice generateIrn exception', ['error' => $e->getMessage()]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Cancel an issued IRN. Marks the matching `invoices` row as cancelled.
     *
     * @return array{ok:bool,raw?:array,error?:string}
     */
    public function cancelIrn(string $irn, string $reasonCode = '1', string $remarks = 'Data Entry Mistake'): array
    {
        try {
            if (trim($irn) === '') {
                return ['ok' => false, 'error' => 'irn_empty'];
            }

            $token = $this->authenticate();
            if ($token === null) {
                return ['ok' => false, 'error' => 'sandbox_auth_failed'];
            }

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                    'Authorization' => $token,
                    'x-api-key'     => (string) $this->apiKey,
                    'x-api-version' => $this->gstApiVersion,
                ])
                ->post($this->baseUrl . '/gst/compliance/e-invoice/cancel', [
                    'Irn'    => $irn,
                    'CnlRsn' => $reasonCode,
                    'CnlRem' => $remarks,
                ]);

            if (!$response->successful()) {
                Log::warning('Sandbox e-invoice cancel failed', [
                    'irn'    => $irn,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return ['ok' => false, 'error' => 'cancel_http_' . $response->status(), 'raw' => $response->json() ?? []];
            }

            // Mark the corresponding invoices row cancelled (idempotent).
            try {
                if (Schema::hasTable('invoice_mst') && Schema::hasColumn('invoice_mst', 'is_cancelled')) {
                    DB::table('invoice_mst')->where('irn', $irn)->update([
                        'is_cancelled' => 1,
                        'updated_at'   => now(),
                    ]);
                }
            } catch (Throwable $e) {
                Log::warning('Failed to flag invoices.is_cancelled', ['irn' => $irn, 'error' => $e->getMessage()]);
            }

            return ['ok' => true, 'raw' => $response->json() ?? []];
        } catch (Throwable $e) {
            Log::error('Sandbox e-invoice cancelIrn exception', ['irn' => $irn, 'error' => $e->getMessage()]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /* ====================================================================
     * Auth
     * ================================================================== */

    /**
     * Returns a bearer access_token, fetching + caching when needed.
     */
    public function authenticate(bool $forceFresh = false): ?string
    {
        if (!$forceFresh) {
            $cached = Cache::get(self::CACHE_KEY);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        if (empty($this->apiKey) || empty($this->apiSecret)) {
            Log::warning('Sandbox credentials missing — set SANDBOX_API_KEY / SANDBOX_SECRET in .env');
            return null;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Accept'        => 'application/json',
                    'x-api-key'     => $this->apiKey,
                    'x-api-secret'  => $this->apiSecret,
                    'x-api-version' => $this->apiVersion,
                ])
                ->post($this->baseUrl . '/authenticate');

            if (!$response->successful()) {
                Log::warning('Sandbox authenticate failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            $token = (string) (data_get($response->json(), 'access_token') ?? '');
            if ($token === '') {
                Log::warning('Sandbox authenticate: access_token missing', ['body' => $response->json()]);
                return null;
            }

            // Sandbox tokens last ~6h. Cache for 5h 30m to refresh ahead of expiry.
            Cache::put(self::CACHE_KEY, $token, now()->addMinutes(330));

            return $token;
        } catch (Throwable $e) {
            Log::error('Sandbox authenticate exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /* ====================================================================
     * Helpers
     * ================================================================== */

    /**
     * Find an `invoices` row for this order, or insert a thin one if missing.
     * Returns the invoices.id, or null if the table doesn't exist.
     */
    private function resolveOrCreateInvoiceRow(int $orderId): ?int
    {
        if (!Schema::hasTable('invoice_mst')) {
            return null;
        }

        $cols = Schema::getColumnListing('invoice_mst');

        $existing = DB::table('invoice_mst')->where('order_id', $orderId)->orderByDesc('id')->first();
        if ($existing) {
            if (in_array('status', $cols, true)) {
                $cur = strtolower(trim((string) ($existing->status ?? '')));
                if ($cur === '') {
                    DB::table('invoice_mst')->where('id', (int) $existing->id)->update([
                        'status'     => 'pending',
                        'updated_at' => now(),
                    ]);
                }
            }

            return (int) $existing->id;
        }

        // Build a minimal row from the order + invoice_mst (if available).
        $order = DB::table('orders')->where('id', $orderId)->first();
        if (!$order) {
            return null;
        }

        $mst = null;
        if (Schema::hasTable('invoice_mst')) {
            $mstCols = Schema::getColumnListing('invoice_mst');
            if (in_array('order_id', $mstCols, true)) {
                $mst = DB::table('invoice_mst')->where('order_id', $orderId)->orderByDesc('id')->first();
            }
            if (!$mst && in_array('order_no', $mstCols, true) && !empty($order->order_no)) {
                $mst = DB::table('invoice_mst')->where('order_no', (string) $order->order_no)->orderByDesc('id')->first();
            }
        }

        $payload = ['order_id' => $orderId];

        // Map common header columns when they exist on the table.
        $maybe = [
            'partner_id'   => $order->partner_id ?? null,
            'customer_id'  => $order->user_id ?? null,
            'invoice_no'   => (string) ($mst?->bill_no_full ?? $order->order_no ?? ''),
            'invoice_date' => $mst?->bill_date ?? ($order->created_at ? \Carbon\Carbon::parse($order->created_at)->toDateString() : now()->toDateString()),
            'amount'       => (float) ($mst?->net_amt ?? $order->total_amount ?? 0),
            'subtotal'     => (float) ($mst?->taxable_total ?? $order->subtotal ?? 0),
            'tax_amount'   => (float) (($mst ? ((float) ($mst->cgst_total ?? 0) + (float) ($mst->sgst_total ?? 0) + (float) ($mst->igst_total ?? 0)) : 0.0) ?: (float) ($order->tax_amount ?? 0)),
            'total_amount' => (float) ($mst?->net_amt ?? $order->total_amount ?? 0),
            'status'       => 'pending',
            'is_cancelled' => 0,
        ];
        foreach ($maybe as $col => $val) {
            if (in_array($col, $cols, true) && $val !== null) {
                $payload[$col] = $val;
            }
        }
        if (in_array('created_at', $cols, true)) $payload['created_at'] = now();
        if (in_array('updated_at', $cols, true)) $payload['updated_at'] = now();

        try {
            return (int) DB::table('invoice_mst')->insertGetId($payload);
        } catch (Throwable $e) {
            Log::warning('Failed to create invoices row for e-invoice', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Ensure a modern `invoices` row exists for the order with status pending (GST / order placement).
     * Does not call the Sandbox API. Safe when `irn` column is not migrated yet.
     */
    public function ensureInvoiceRowForOrder(int $orderId): ?int
    {
        try {
            return $this->resolveOrCreateInvoiceRow($orderId);
        } catch (Throwable $e) {
            Log::warning('ensureInvoiceRowForOrder failed', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Build the e-invoice payload from invoice_mst (header) + invoice_sub
     * (line items) and the linked order/buyer/seller meta. Schema follows
     * NIC's GST_EINV_1.03 shape that Sandbox proxies.
     *
     * @return array<string,mixed>
     */
    public function buildEInvoicePayload(int $orderId, int $invoiceId): array
    {
        $order   = DB::table('orders')->where('id', $orderId)->first();
        $invoice = DB::table('invoice_mst')->where('id', $invoiceId)->first();

        $settings = Schema::hasTable('admin_settings')
            ? DB::table('admin_settings')->orderByDesc('id')->first()
            : null;

        $sellerGstin = strtoupper(trim((string) ($settings->gstin ?? $settings->gst_no ?? $settings->company_gstin ?? '')));
        $sellerState = (string) ($settings->state_code ?? '');
        if ($sellerState === '' && strlen($sellerGstin) >= 2 && ctype_digit(substr($sellerGstin, 0, 2))) {
            $sellerState = substr($sellerGstin, 0, 2);
        }

        $buyer = $this->resolveBuyer($order);

        // Line items: prefer invoice_sub joined to invoice_mst (by order_id
        // or order_no), falling back to a single line synthesized from order.
        $lines = $this->resolveInvoiceLines($order);

        $totals = ['ass' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0, 'total' => 0.0];
        foreach ($lines as $ln) {
            $totals['ass']   += (float) $ln['AssAmt'];
            $totals['cgst']  += (float) $ln['CgstAmt'];
            $totals['sgst']  += (float) $ln['SgstAmt'];
            $totals['igst']  += (float) $ln['IgstAmt'];
            $totals['total'] += (float) $ln['TotItemVal'];
        }

        // If buyer is in a different state than seller, convert to IGST.
        $isInterState = $sellerState !== '' && $buyer['state_code'] !== '' && $sellerState !== $buyer['state_code'];
        if ($isInterState) {
            // Sum CGST+SGST → IGST per line, zero CGST/SGST.
            foreach ($lines as &$ln) {
                $combined = (float) $ln['CgstAmt'] + (float) $ln['SgstAmt'];
                if ($combined > 0 && (float) $ln['IgstAmt'] === 0.0) {
                    $ln['IgstAmt'] = round($combined, 2);
                    $ln['CgstAmt'] = 0.0;
                    $ln['SgstAmt'] = 0.0;
                }
            }
            unset($ln);
            $totals['igst'] = round($totals['cgst'] + $totals['sgst'] + $totals['igst'], 2);
            $totals['cgst'] = 0.0;
            $totals['sgst'] = 0.0;
        }

        $docNo = (string) ($invoice->invoice_no ?? $order->order_no ?? $orderId);
        $docDate = !empty($invoice->invoice_date)
            ? \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y')
            : (!empty($order->created_at) ? \Carbon\Carbon::parse($order->created_at)->format('d/m/Y') : now()->format('d/m/Y'));

        return [
            'Version'    => '1.1',
            'TranDtls'   => [
                'TaxSch'      => 'GST',
                'SupTyp'      => 'B2B',
                'RegRev'      => 'N',
                'IgstOnIntra' => 'N',
            ],
            'DocDtls'    => [
                'Typ' => 'INV',
                'No'  => $docNo,
                'Dt'  => $docDate,
            ],
            'SellerDtls' => [
                'Gstin' => $sellerGstin ?: 'URP',
                'LglNm' => (string) (($settings->title ?? '') ?: config('app.name', 'Company')),
                'Addr1' => (string) ($settings->address ?? ''),
                'Loc'   => (string) ($settings->city ?? $settings->state ?? ''),
                'Pin'   => (int) preg_replace('/\D/', '', (string) ($settings->pincode ?? '000000')) ?: 0,
                'Stcd'  => $sellerState ?: '00',
                'Ph'    => (string) ($settings->phone ?? ''),
                'Em'    => (string) ($settings->email ?? ''),
            ],
            'BuyerDtls'  => [
                'Gstin' => $buyer['gstin'] ?: 'URP',
                'LglNm' => $buyer['name'],
                'Pos'   => $buyer['state_code'] ?: ($sellerState ?: '00'),
                'Addr1' => $buyer['address'],
                'Loc'   => $buyer['location'],
                'Pin'   => $buyer['pin'],
                'Stcd'  => $buyer['state_code'] ?: ($sellerState ?: '00'),
                'Ph'    => $buyer['phone'],
                'Em'    => $buyer['email'],
            ],
            'ItemList'   => array_values($lines),
            'ValDtls'    => [
                'AssVal'    => round($totals['ass'], 2),
                'CgstVal'   => round($totals['cgst'], 2),
                'SgstVal'   => round($totals['sgst'], 2),
                'IgstVal'   => round($totals['igst'], 2),
                'CesVal'    => 0,
                'StCesVal'  => 0,
                'Discount'  => (float) ($order->discount_amount ?? 0),
                'OthChrg'   => 0,
                'RndOffAmt' => 0,
                'TotInvVal' => round($totals['total'] > 0 ? $totals['total'] : ($totals['ass'] + $totals['cgst'] + $totals['sgst'] + $totals['igst']), 2),
            ],
        ];
    }

    /**
     * Resolve invoice line items (NIC ItemList shape) preferring data from
     * invoice_sub. Falls back to a single line built from the order itself.
     *
     * @return array<int, array<string,mixed>>
     */
    private function resolveInvoiceLines(object $order): array
    {
        if (Schema::hasTable('invoice_mst') && Schema::hasTable('invoice_sub')) {
            $mstCols = Schema::getColumnListing('invoice_mst');
            $subCols = Schema::getColumnListing('invoice_sub');

            $mst = null;
            if (in_array('order_id', $mstCols, true)) {
                $mst = DB::table('invoice_mst')->where('order_id', $order->id)->orderByDesc('id')->first();
            }
            if (!$mst && in_array('order_no', $mstCols, true) && !empty($order->order_no)) {
                $mst = DB::table('invoice_mst')->where('order_no', (string) $order->order_no)->orderByDesc('id')->first();
            }

            if ($mst) {
                $fkCol = in_array('invoice_mst_id', $subCols, true) ? 'invoice_mst_id'
                    : (in_array('other_invoice_mst_id', $subCols, true) ? 'other_invoice_mst_id' : null);

                if ($fkCol !== null) {
                    $rows = DB::table('invoice_sub')->where($fkCol, (int) $mst->id)->get();
                    $lines = [];
                    $sl = 1;
                    foreach ($rows as $row) {
                        $qty       = max(1.0, (float) ($row->qty ?? 1));
                        $unitRate  = (float) ($row->cnt_rate ?? 0);
                        $assAmt    = (float) ($row->taxable_amt ?? ($unitRate * $qty));
                        $cgst      = (float) ($row->cgst ?? 0);
                        $sgst      = (float) ($row->sgst ?? 0);
                        $igst      = (float) ($row->igst ?? 0);
                        $netTotal  = (float) ($row->nettotal ?? ($assAmt + $cgst + $sgst + $igst));
                        $hsn       = (string) ($row->hsn_sac ?? '997331');
                        $gstRate   = $assAmt > 0 ? round((($cgst + $sgst + $igst) / $assAmt) * 100, 2) : GstSplit::RATE;

                        $lines[] = [
                            'SlNo'                 => (string) $sl++,
                            'PrdDesc'              => substr((string) ($row->invoice_no ?? $order->plan_name ?? 'Service'), 0, 100),
                            'IsServc'              => 'Y',
                            'HsnCd'                => $hsn,
                            'Qty'                  => $qty,
                            'Unit'                 => 'OTH',
                            'UnitPrice'            => round($unitRate > 0 ? $unitRate : ($qty > 0 ? $assAmt / $qty : 0), 2),
                            'TotAmt'               => round($unitRate * $qty, 2),
                            'Discount'             => 0,
                            'AssAmt'               => round($assAmt, 2),
                            'GstRt'                => $gstRate > 0 ? $gstRate : GstSplit::RATE,
                            'IgstAmt'              => round($igst, 2),
                            'CgstAmt'              => round($cgst, 2),
                            'SgstAmt'              => round($sgst, 2),
                            'CesRt'                => 0,
                            'CesAmt'               => 0,
                            'CesNonAdvlAmt'        => 0,
                            'StateCesRt'           => 0,
                            'StateCesAmt'          => 0,
                            'StateCesNonAdvlAmt'   => 0,
                            'OthChrg'              => 0,
                            'TotItemVal'           => round($netTotal, 2),
                        ];
                    }
                    if (!empty($lines)) {
                        return $lines;
                    }
                }
            }
        }

        // Fallback: single synthesized line from the order.
        $hsn      = (string) ($order->hsn_code ?? $order->hsn_sac ?? '997331');
        $qty      = max(1.0, (float) ($order->quantity ?? 1));
        $subtotal = (float) ($order->subtotal ?? $order->price ?? 0);
        $unitRate = $qty > 0 ? round($subtotal / $qty, 2) : $subtotal;
        $gstRate  = GstSplit::RATE;
        $gstAmt   = (float) ($order->gst_amount ?? $order->tax_amount ?? round($subtotal * ($gstRate / 100), 2));
        $split    = GstSplit::split($gstAmt, (string) ($order->po_manual_gstin ?? $order->customer_gstin ?? ''));
        $total    = (float) ($order->total_amount ?? ($subtotal + $gstAmt));

        return [[
            'SlNo'                 => '1',
            'PrdDesc'              => substr((string) ($order->plan_name ?? $order->domain_name ?? 'Service'), 0, 100),
            'IsServc'              => 'Y',
            'HsnCd'                => $hsn,
            'Qty'                  => $qty,
            'Unit'                 => 'OTH',
            'UnitPrice'            => $unitRate,
            'TotAmt'               => round($unitRate * $qty, 2),
            'Discount'             => 0,
            'AssAmt'               => $subtotal,
            'GstRt'                => $gstRate,
            'IgstAmt'              => $split['igst'],
            'CgstAmt'              => $split['cgst'],
            'SgstAmt'              => $split['sgst'],
            'CesRt'                => 0,
            'CesAmt'               => 0,
            'CesNonAdvlAmt'        => 0,
            'StateCesRt'           => 0,
            'StateCesAmt'          => 0,
            'StateCesNonAdvlAmt'   => 0,
            'OthChrg'              => 0,
            'TotItemVal'           => $total,
        ]];
    }

    /**
     * Resolve buyer details from joined customer / partner / PO records.
     *
     * @return array{name:string,gstin:string,state_code:string,address:string,location:string,pin:int,phone:string,email:string}
     */
    private function resolveBuyer(object $order): array
    {
        $row = null;
        if (!empty($order->user_id) && Schema::hasTable('customers')) {
            $row = DB::table('customers')->where('id', $order->user_id)->first();
        }
        if (!$row && !empty($order->partner_id) && Schema::hasTable('partners')) {
            $row = DB::table('partners')->where('id', $order->partner_id)->first();
        }

        $po = null;
        if (Schema::hasTable('order_po_details')) {
            $po = DB::table('order_po_details')->where('order_id', $order->id)->first();
        }

        $name      = (string) ($po->manual_company_name ?? $row->company_name ?? $row->name ?? 'Unregistered Customer');
        $gstin     = strtoupper(trim((string) ($po->manual_gstin ?? $row->gstin ?? $row->gst_no ?? '')));
        $stateCode = '';
        if (strlen($gstin) >= 2 && ctype_digit(substr($gstin, 0, 2))) {
            $stateCode = substr($gstin, 0, 2);
        } else {
            $stateCode = (string) ($po->manual_state_code ?? $row->state_code ?? '');
        }

        $address  = (string) ($po->manual_company_address ?? $row->billing_address ?? $row->address ?? 'NA');
        $location = (string) ($po->manual_city ?? $row->city ?? 'NA');
        $pinRaw   = (string) ($po->manual_pincode ?? $row->pincode ?? $row->pin ?? '');
        $pin      = (int) preg_replace('/\D/', '', $pinRaw);
        $phone    = (string) ($po->manual_mobile ?? $row->mobile ?? $row->phone ?? '');
        $email    = (string) ($po->manual_email ?? $row->email ?? '');

        return [
            'name'       => substr($name, 0, 100),
            'gstin'      => $gstin,
            'state_code' => $stateCode,
            'address'    => substr($address, 0, 100) ?: 'NA',
            'location'   => substr($location, 0, 50) ?: 'NA',
            'pin'        => $pin > 0 ? $pin : 0,
            'phone'      => $phone,
            'email'      => $email,
        ];
    }

    /**
     * Persist the IRN/QR back onto the `invoices` row, only writing columns
     * that actually exist on the table.
     *
     * @param  array<string,mixed> $result
     */
    private function persistEInvoiceResponse(int $invoiceId, array $result): void
    {
        try {
            if (!Schema::hasTable('invoice_mst')) {
                return;
            }

            $columns = Schema::getColumnListing('invoice_mst');
            $update  = ['updated_at' => now()];

            foreach ([
                'irn'                  => $result['irn']                  ?? null,
                'signed_qr_code'       => $result['signed_qr_code']       ?? null,
                'signed_invoice'       => $result['signed_invoice']       ?? null,
                'e_invoice_pdf_url'    => $result['e_invoice_pdf_url']    ?? null,
                'acknowledgement_no'   => $result['acknowledgement_no']   ?? null,
                'acknowledgement_date' => $result['acknowledgement_date'] ?? null,
            ] as $col => $val) {
                if ($val !== null && in_array($col, $columns, true)) {
                    $update[$col] = $val;
                }
            }

            if (in_array('is_cancelled', $columns, true)) {
                $update['is_cancelled'] = 0;
            }

            DB::table('invoice_mst')->where('id', $invoiceId)->update($update);
        } catch (Throwable $e) {
            Log::error('Sandbox persistEInvoiceResponse failed', [
                'invoice_id' => $invoiceId,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
