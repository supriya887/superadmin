<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderPoDetail;
use App\Support\OrderDomainHelper;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Attach an externally created PO (CRM / SAP PDF) instead of generating system PO.
 * Uploaded PO does not skip partner eSign when Aadhaar is mandatory.
 */
class OrderPoDocumentService
{
    public const MODE_GENERATED = 'generated';
    public const MODE_UPLOADED = 'uploaded';

    /**
     * Store an uploaded PO against a cart (no orders row yet — eSign pending).
     *
     * @return array{ok: bool, public_url?: string, po_number?: string, po_mode?: string, error?: string}
     */
    public function storeUploadedPoForCart(
        int $cartId,
        UploadedFile $file,
        ?string $poNumber = null
    ): array {
        if ($cartId <= 0) {
            return ['ok' => false, 'error' => 'Invalid cart id.'];
        }

        if (!$file->isValid()) {
            return ['ok' => false, 'error' => 'Invalid PO file upload.'];
        }

        $parsed = $this->readUploadedPdfBinary($file);
        if (empty($parsed['ok'])) {
            return ['ok' => false, 'error' => (string) ($parsed['error'] ?? 'Invalid PO file.')];
        }

        $binary = (string) $parsed['binary'];
        $resolvedNumber = trim((string) ($poNumber ?? ''));
        if ($resolvedNumber === '') {
            $resolvedNumber = 'EXT-CART-' . $cartId . '-' . now()->format('Ymd');
        }

        $safePoNo = preg_replace('/[^A-Za-z0-9_\-]/', '', $resolvedNumber) ?: ('EXT-CART-' . $cartId);
        $filename = 'uploaded-' . $safePoNo . '-cart-' . $cartId . '-' . Str::lower(Str::random(6)) . '.pdf';
        $relativePath = 'order-po/' . $filename;

        if (!$this->writePublicStorageFile($relativePath, $binary)) {
            return ['ok' => false, 'error' => 'Could not store PO document on disk.'];
        }

        $publicUrl = $this->publicStorageUrl($relativePath);
        app(PartnerCartService::class)->saveCartPoMeta(
            $cartId,
            $publicUrl,
            $resolvedNumber,
            self::MODE_UPLOADED
        );

        return [
            'ok' => true,
            'public_url' => $publicUrl,
            'po_number' => $resolvedNumber,
            'po_mode' => self::MODE_UPLOADED,
        ];
    }

    /**
     * Apply a previously uploaded cart PO URL onto a real order after eSign.
     *
     * @return array{ok: bool, public_url?: string, po_number?: string, po_mode?: string, error?: string}
     */
    public function applyStoredUploadedPoToOrder(
        int $orderId,
        string $publicUrl,
        ?string $poNumber = null,
        bool $aadhaarCompleted = true
    ): array {
        if ($orderId <= 0 || trim($publicUrl) === '') {
            return ['ok' => false, 'error' => 'Invalid order or PO URL.'];
        }

        $order = Order::query()->whereKey($orderId)->first();
        if (!$order) {
            return ['ok' => false, 'error' => 'Order not found.'];
        }

        $resolvedNumber = trim((string) ($poNumber ?? ''));
        if ($resolvedNumber === '') {
            $resolvedNumber = 'EXT-' . $orderId . '-' . now()->format('Ymd');
        }

        return $this->persistUploadedPoRow(
            $order,
            $orderId,
            $publicUrl,
            $resolvedNumber,
            now()->toDateString(),
            round((float) ($order->total_amount ?? $order->amount ?? 0), 2),
            $aadhaarCompleted
        );
    }

    /**
     * @return array{
     *   ok: bool,
     *   public_url?: string,
     *   po_number?: string,
     *   po_mode?: string,
     *   journey_status?: string,
     *   error?: string
     * }
     */
    public function attachUploadedPo(
        int $orderId,
        UploadedFile $file,
        ?string $poNumber = null,
        ?string $poDate = null,
        ?float $poAmount = null,
        bool $aadhaarCompleted = false
    ): array {
        if ($orderId <= 0) {
            return ['ok' => false, 'error' => 'Invalid order id.'];
        }

        if (!$file->isValid()) {
            return ['ok' => false, 'error' => 'Invalid PO file upload.'];
        }

        $parsed = $this->readUploadedPdfBinary($file);
        if (empty($parsed['ok'])) {
            return ['ok' => false, 'error' => (string) ($parsed['error'] ?? 'Invalid PO file.')];
        }

        $order = Order::query()->whereKey($orderId)->first();
        if (!$order) {
            return ['ok' => false, 'error' => 'Order not found.'];
        }

        try {
            $binary = (string) $parsed['binary'];
            $safePoNo = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($poNumber ?: ('EXT-' . $orderId))) ?: ('EXT-' . $orderId);
            $filename = 'uploaded-' . $safePoNo . '-order-' . $orderId . '-' . Str::lower(Str::random(6)) . '.pdf';
            $relativePath = 'order-po/' . $filename;

            if (!$this->writePublicStorageFile($relativePath, $binary)) {
                Log::error('order_po_upload_write_failed', [
                    'order_id' => $orderId,
                    'relative' => $relativePath,
                    'storage_file' => $this->storagePublicPath($relativePath),
                    'public_file' => $this->webPublicStoragePath($relativePath),
                ]);

                return ['ok' => false, 'error' => 'Could not store PO document on disk.'];
            }

            $publicUrl = $this->publicStorageUrl($relativePath);
            $resolvedNumber = trim((string) ($poNumber ?? ''));
            if ($resolvedNumber === '') {
                $resolvedNumber = 'EXT-' . $orderId . '-' . now()->format('Ymd');
            }
            $resolvedDate = $poDate && trim((string) $poDate) !== ''
                ? date('Y-m-d', strtotime((string) $poDate))
                : now()->toDateString();
            $amount = $poAmount !== null && $poAmount > 0
                ? round($poAmount, 2)
                : round((float) ($order->total_amount ?? $order->amount ?? 0), 2);

            return $this->persistUploadedPoRow(
                $order,
                $orderId,
                $publicUrl,
                $resolvedNumber,
                $resolvedDate,
                $amount,
                $aadhaarCompleted
            );
        } catch (\Throwable $e) {
            Log::error('order_po_upload_failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, binary?: string, error?: string}
     */
    private function readUploadedPdfBinary(UploadedFile $file): array
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $mime = strtolower((string) ($file->getClientMimeType() ?: $file->getMimeType() ?: ''));
        $name = strtolower((string) $file->getClientOriginalName());
        $isPdf = $ext === 'pdf'
            || str_ends_with($name, '.pdf')
            || str_contains($mime, 'pdf')
            || in_array($mime, ['application/octet-stream', 'binary/octet-stream', ''], true);

        $path = $file->getRealPath() ?: $file->getPathname();
        if (!$isPdf) {
            $head = $path ? (string) @file_get_contents($path, false, null, 0, 5) : '';
            if ($head !== '%PDF-') {
                return ['ok' => false, 'error' => 'Only PDF purchase orders are accepted.'];
            }
        }

        $binary = $path ? (string) @file_get_contents($path) : '';
        if ($binary === '') {
            $binary = (string) $file->get();
        }
        if ($binary === '') {
            return ['ok' => false, 'error' => 'Uploaded PO PDF is empty.'];
        }

        return ['ok' => true, 'binary' => $binary];
    }

    /**
     * @return array{ok: bool, public_url?: string, po_number?: string, po_mode?: string, journey_status?: string, error?: string}
     */
    private function persistUploadedPoRow(
        Order $order,
        int $orderId,
        string $publicUrl,
        string $resolvedNumber,
        string $resolvedDate,
        float $amount,
        bool $aadhaarCompleted
    ): array {
        $poCols = Schema::connection((new OrderPoDetail())->getConnectionName())->getColumnListing('order_po_details');
        $payload = [
            'order_id' => $orderId,
            'updated_at' => now(),
        ];
        if (in_array('po_mode', $poCols, true)) {
            $payload['po_mode'] = self::MODE_UPLOADED;
        }
        if (in_array('po_number', $poCols, true)) {
            $payload['po_number'] = $resolvedNumber;
        }
        if (in_array('po_date', $poCols, true)) {
            $payload['po_date'] = $resolvedDate;
        }
        if (in_array('po_amount', $poCols, true)) {
            $payload['po_amount'] = $amount;
        }
        if (in_array('po_document_url', $poCols, true)) {
            $payload['po_document_url'] = $publicUrl;
        }
        if (in_array('final_po_link', $poCols, true)) {
            $payload['final_po_link'] = $publicUrl;
        }
        if (in_array('journey_status', $poCols, true)) {
            // PO uploaded; eSign still required → po_done. Otherwise placed.
            $payload['journey_status'] = $aadhaarCompleted ? 'placed' : 'po_done';
        }
        if (in_array('sign_required', $poCols, true)) {
            $payload['sign_required'] = $aadhaarCompleted ? 'no' : 'yes';
        }
        if (in_array('status', $poCols, true)) {
            $payload['status'] = $aadhaarCompleted ? 'active' : 'processing';
        }
        if (in_array('aadhar_verified', $poCols, true) && $aadhaarCompleted) {
            $payload['aadhar_verified'] = 1;
        }

        $existing = OrderPoDetail::query()->where('order_id', $orderId)->first();
        if ($existing) {
            OrderPoDetail::query()->where('order_id', $orderId)->update($payload);
        } else {
            if (in_array('created_at', $poCols, true)) {
                $payload['created_at'] = now();
            }
            OrderPoDetail::query()->create($payload);
        }

        $orderCols = Schema::connection((new Order())->getConnectionName())->getColumnListing('orders');
        $orderUpdate = ['updated_at' => now()];
        if (in_array('po_document_url', $orderCols, true)) {
            $orderUpdate['po_document_url'] = $publicUrl;
        }
        Order::query()->whereKey($orderId)->update($orderUpdate);

        $freshOrder = Order::query()->whereKey($orderId)->first() ?? $order;
        $resolvedDomain = OrderDomainHelper::resolveForOrder(
            $freshOrder,
            (int) ($freshOrder->cart_id ?? $freshOrder->source_cart_id ?? 0)
        );
        OrderDomainHelper::persistOnOrderIfMissing($orderId, $resolvedDomain);

        Log::info('order_po_upload_saved', [
            'order_id' => $orderId,
            'public_url' => $publicUrl,
            'aadhaar_completed' => $aadhaarCompleted,
        ]);

        return [
            'ok' => true,
            'public_url' => $publicUrl,
            'po_number' => $resolvedNumber,
            'po_mode' => self::MODE_UPLOADED,
            'journey_status' => $aadhaarCompleted ? 'placed' : 'po_done',
        ];
    }

    /**
     * Write under storage/app/public and mirror to public/storage (shared hosting may lack symlink).
     */
    private function writePublicStorageFile(string $relativePath, string $binary): bool
    {
        if ($binary === '') {
            return false;
        }

        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $storageFile = $this->storagePublicPath($relativePath);
        $publicFile = $this->webPublicStoragePath($relativePath);

        foreach ([dirname($storageFile), dirname($publicFile)] as $directory) {
            if (is_dir($directory)) {
                continue;
            }
            if (!@mkdir($directory, 0755, true) && !is_dir($directory)) {
                Log::error('order_po_upload: cannot create directory', [
                    'directory' => $directory,
                    'relative' => $relativePath,
                ]);
            }
        }

        $storageOk = @file_put_contents($storageFile, $binary) !== false;
        $publicOk = @file_put_contents($publicFile, $binary) !== false;

        if ($storageOk && !$publicOk && is_file($storageFile)) {
            $publicOk = @copy($storageFile, $publicFile);
        }

        if (!$storageOk) {
            try {
                Storage::disk('public')->makeDirectory(dirname($relativePath) === '.' ? '' : dirname($relativePath));
                $storageOk = (bool) Storage::disk('public')->put($relativePath, $binary);
                if ($storageOk && is_file($storageFile) && !is_file($publicFile)) {
                    $publicOk = @copy($storageFile, $publicFile);
                }
            } catch (\Throwable $e) {
                Log::warning('order_po_upload: Storage disk fallback failed', [
                    'path' => $relativePath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return (is_file($storageFile) && filesize($storageFile) > 0)
            || (is_file($publicFile) && filesize($publicFile) > 0);
    }

    private function storagePublicPath(string $relativePath): string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

        return storage_path('app/public/' . $relativePath);
    }

    private function webPublicStoragePath(string $relativePath): string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

        return public_path('storage/' . $relativePath);
    }

    private function publicStorageUrl(string $relativePath): string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $prefix = trim((string) env('PUBLIC_STORAGE_URL_PREFIX', 'storage'), '/');
        $base = rtrim((string) config('app.url'), '/');

        return $base . '/' . $prefix . '/' . $relativePath;
    }

    public function isUploadedPo(int $orderId): bool
    {
        if ($orderId <= 0) {
            return false;
        }

        $mode = strtolower(trim((string) (
            OrderPoDetail::query()->where('order_id', $orderId)->value('po_mode') ?? ''
        )));

        return $mode === self::MODE_UPLOADED;
    }

    /**
     * Resolve PO document for an order. For renew/upgrade/downgrade child orders,
     * falls back to the original/new parent order's uploaded PO when the child has none.
     *
     * @return array{
     *   po_link: ?string,
     *   po_number: string,
     *   po_mode: string,
     *   po_date: ?string,
     *   final_po_link: ?string,
     *   source_order_id: int,
     *   is_uploaded: bool
     * }
     */
    public function resolvePoForOrder(int $orderId, bool $fallbackToParent = true): array
    {
        $empty = [
            'po_link' => null,
            'po_number' => '',
            'po_mode' => self::MODE_GENERATED,
            'po_date' => null,
            'final_po_link' => null,
            'source_order_id' => $orderId > 0 ? $orderId : 0,
            'is_uploaded' => false,
        ];

        if ($orderId <= 0) {
            return $empty;
        }

        $visited = [];
        $currentId = $orderId;

        while ($currentId > 0 && !isset($visited[$currentId])) {
            $visited[$currentId] = true;

            $poRow = OrderPoDetail::query()->where('order_id', $currentId)->orderByDesc('id')->first();
            $order = Order::query()->whereKey($currentId)->first(['id', 'po_document_url', 'parent_order_id']);

            $link = trim((string) (
                ($poRow->po_document_url ?? null)
                ?: ($poRow->final_po_link ?? null)
                ?: ($order->po_document_url ?? null)
                ?: ''
            ));
            $finalLink = trim((string) ($poRow->final_po_link ?? $link));
            $mode = strtolower(trim((string) ($poRow->po_mode ?? '')));
            if ($mode === '') {
                $mode = $link !== '' ? self::MODE_GENERATED : self::MODE_GENERATED;
            }

            if ($link !== '') {
                return [
                    'po_link' => $link,
                    'po_number' => (string) ($poRow->po_number ?? ''),
                    'po_mode' => in_array($mode, [self::MODE_GENERATED, self::MODE_UPLOADED], true)
                        ? $mode
                        : self::MODE_GENERATED,
                    'po_date' => isset($poRow->po_date) ? (string) $poRow->po_date : null,
                    'final_po_link' => $finalLink !== '' ? $finalLink : $link,
                    'source_order_id' => $currentId,
                    'is_uploaded' => $mode === self::MODE_UPLOADED,
                ];
            }

            if (!$fallbackToParent) {
                break;
            }

            $parentId = (int) ($order->parent_order_id ?? 0);
            if ($parentId <= 0) {
                break;
            }
            $currentId = $parentId;
        }

        $empty['source_order_id'] = $orderId;

        return $empty;
    }

    /**
     * Copy an existing (usually parent uploaded) PO onto a renew/upgrade/downgrade order.
     *
     * @return array{ok: bool, public_url?: string, po_number?: string, po_mode?: string, error?: string}
     */
    public function copyPoFromOrder(int $fromOrderId, int $toOrderId): array
    {
        if ($fromOrderId <= 0 || $toOrderId <= 0 || $fromOrderId === $toOrderId) {
            return ['ok' => false, 'error' => 'Invalid order ids for PO copy.'];
        }

        $source = $this->resolvePoForOrder($fromOrderId, true);
        $link = trim((string) ($source['po_link'] ?? ''));
        if ($link === '') {
            return ['ok' => false, 'error' => 'Source order has no PO document.'];
        }

        $poCols = Schema::connection((new OrderPoDetail())->getConnectionName())->getColumnListing('order_po_details');
        $payload = [
            'order_id' => $toOrderId,
            'updated_at' => now(),
        ];
        if (in_array('po_mode', $poCols, true)) {
            $payload['po_mode'] = (string) ($source['po_mode'] ?: self::MODE_UPLOADED);
        }
        if (in_array('po_number', $poCols, true)) {
            $payload['po_number'] = (string) ($source['po_number'] ?: ('COPY-' . $toOrderId));
        }
        if (in_array('po_date', $poCols, true) && !empty($source['po_date'])) {
            $payload['po_date'] = $source['po_date'];
        }
        if (in_array('po_document_url', $poCols, true)) {
            $payload['po_document_url'] = $link;
        }
        if (in_array('final_po_link', $poCols, true)) {
            $payload['final_po_link'] = (string) ($source['final_po_link'] ?: $link);
        }
        if (in_array('journey_status', $poCols, true)) {
            $payload['journey_status'] = 'placed';
        }
        if (in_array('sign_required', $poCols, true) && ($source['is_uploaded'] ?? false)) {
            $payload['sign_required'] = 'no';
        }
        if (in_array('status', $poCols, true)) {
            $payload['status'] = 'active';
        }

        $existing = OrderPoDetail::query()->where('order_id', $toOrderId)->first();
        if ($existing) {
            OrderPoDetail::query()->where('order_id', $toOrderId)->update($payload);
        } else {
            if (in_array('created_at', $poCols, true)) {
                $payload['created_at'] = now();
            }
            OrderPoDetail::query()->create($payload);
        }

        $orderCols = Schema::connection((new Order())->getConnectionName())->getColumnListing('orders');
        if (in_array('po_document_url', $orderCols, true)) {
            Order::query()->whereKey($toOrderId)->update([
                'po_document_url' => $link,
                'updated_at' => now(),
            ]);
        }

        return [
            'ok' => true,
            'public_url' => $link,
            'po_number' => (string) ($payload['po_number'] ?? $source['po_number'] ?? ''),
            'po_mode' => (string) ($payload['po_mode'] ?? $source['po_mode'] ?? self::MODE_GENERATED),
        ];
    }

    public function normalizeMode(?string $mode): string
    {
        $mode = strtolower(trim((string) $mode));

        return in_array($mode, [self::MODE_GENERATED, self::MODE_UPLOADED], true)
            ? $mode
            : self::MODE_GENERATED;
    }
}
