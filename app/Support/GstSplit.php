<?php

namespace App\Support;

/**
 * GST 18% on PO, invoice, and credit note.
 * Same-state as company (Maharashtra 27 by default) → CGST 9% + SGST 9%.
 * Otherwise → IGST 18%.
 */
final class GstSplit
{
    public const RATE = 18.0;
    public const HALF_RATE = 9.0;
    public const COMPANY_STATE = '27';

    public static function stateCode(?string $gstin): string
    {
        $gstin = strtoupper(trim((string) preg_replace('/\s+/', '', (string) $gstin)));
        if (strlen($gstin) >= 2 && ctype_digit(substr($gstin, 0, 2))) {
            return substr($gstin, 0, 2);
        }

        return '';
    }

    public static function normalizeTaxType(?string $value): string
    {
        $value = strtolower(trim((string) $value));
        if (in_array($value, ['igst', 'i-gst', 'i_gst', 'interstate', 'inter_state', 'inter-state'], true)) {
            return 'igst';
        }
        if (in_array($value, ['cgst_sgst', 'cgst+sgst', 'cgst-sgst', 'cgst/sgst', 'intra', 'intrastate', 'intra_state'], true)) {
            return 'cgst_sgst';
        }

        return '';
    }

    /**
     * @return array{
     *   gst_rate: float,
     *   cgst_rate: float,
     *   sgst_rate: float,
     *   igst_rate: float,
     *   tax_type: string,
     *   cgst: float,
     *   sgst: float,
     *   igst: float,
     *   gst_total: float
     * }
     */
    public static function split(float $gstAmount, ?string $customerGstin, ?string $companyGstin = null): array
    {
        $gstAmount = round(max(0.0, $gstAmount), 2);
        $customerState = self::stateCode($customerGstin);
        $companyState = self::stateCode($companyGstin);
        if ($companyState === '') {
            $companyState = self::COMPANY_STATE;
        }

        $intraState = $customerState !== '' && $customerState === $companyState;
        if ($intraState) {
            $cgst = round($gstAmount / 2, 2);
            $sgst = round($gstAmount - $cgst, 2);

            return [
                'gst_rate' => self::RATE,
                'cgst_rate' => self::HALF_RATE,
                'sgst_rate' => self::HALF_RATE,
                'igst_rate' => 0.0,
                'tax_type' => 'cgst_sgst',
                'cgst' => $cgst,
                'sgst' => $sgst,
                'igst' => 0.0,
                'gst_total' => $gstAmount,
            ];
        }

        return [
            'gst_rate' => self::RATE,
            'cgst_rate' => 0.0,
            'sgst_rate' => 0.0,
            'igst_rate' => self::RATE,
            'tax_type' => 'igst',
            'cgst' => 0.0,
            'sgst' => 0.0,
            'igst' => $gstAmount,
            'gst_total' => $gstAmount,
        ];
    }

    /**
     * Resolve CGST/SGST vs IGST for API payloads (transaction details, order details, etc.).
     *
     * @param array{
     *   gst_amount?: float|int|string|null,
     *   gst_rate?: float|int|string|null,
     *   customer_gstin?: string|null,
     *   company_gstin?: string|null,
     *   tax_type?: string|null,
     *   line_tax_type?: string|null,
     *   po_tax_type?: string|null,
     *   cgst?: float|int|string|null,
     *   sgst?: float|int|string|null,
     *   igst?: float|int|string|null,
     * } $input
     *
     * @return array{
     *   tax_type: string,
     *   gst_rate: float,
     *   cgst_rate: float,
     *   sgst_rate: float,
     *   igst_rate: float,
     *   cgst: float,
     *   sgst: float,
     *   igst: float,
     *   gst_amount: float,
     *   show_igst: bool,
     *   showIgst: bool,
     *   show_cgst_sgst: bool,
     *   showCgstSgst: bool,
     *   gst_lines: array<int, array<string, mixed>>,
     *   gstLines: array<int, array<string, mixed>>
     * }
     */
    public static function resolveBreakdown(array $input): array
    {
        $gstRate = round((float) ($input['gst_rate'] ?? self::RATE), 2);
        if ($gstRate <= 0) {
            $gstRate = self::RATE;
        }

        $cgstAmount = round((float) ($input['cgst'] ?? 0), 2);
        $sgstAmount = round((float) ($input['sgst'] ?? 0), 2);
        $igstAmount = round((float) ($input['igst'] ?? 0), 2);
        $gstAmount = round((float) ($input['gst_amount'] ?? 0), 2);
        if ($gstAmount <= 0) {
            $gstAmount = round($cgstAmount + $sgstAmount + $igstAmount, 2);
        }

        $customerGstin = trim((string) ($input['customer_gstin'] ?? ''));
        $companyGstin = trim((string) ($input['company_gstin'] ?? ''));
        $split = self::split($gstAmount, $customerGstin !== '' ? $customerGstin : null, $companyGstin !== '' ? $companyGstin : null);

        $storedTaxType = self::normalizeTaxType($input['tax_type'] ?? '');
        $lineTaxType = self::normalizeTaxType($input['line_tax_type'] ?? '');
        $poTaxType = self::normalizeTaxType($input['po_tax_type'] ?? '');

        if (
            $storedTaxType === 'igst'
            || $lineTaxType === 'igst'
            || $poTaxType === 'igst'
            || $igstAmount > 0.009
        ) {
            $taxType = 'igst';
        } elseif ($customerGstin !== '') {
            $taxType = (string) ($split['tax_type'] ?? 'cgst_sgst');
        } elseif ($storedTaxType !== '') {
            $taxType = $storedTaxType;
        } elseif ($lineTaxType !== '') {
            $taxType = $lineTaxType;
        } elseif ($poTaxType !== '') {
            $taxType = $poTaxType;
        } else {
            $taxType = (string) ($split['tax_type'] ?? 'cgst_sgst');
        }

        if ($taxType === 'igst') {
            $igstAmount = $gstAmount > 0
                ? $gstAmount
                : round(max($igstAmount, $cgstAmount + $sgstAmount), 2);
            $cgstAmount = 0.0;
            $sgstAmount = 0.0;
            $cgstRate = 0.0;
            $sgstRate = 0.0;
            $igstRate = $gstRate > 0 ? $gstRate : self::RATE;
        } else {
            $taxType = 'cgst_sgst';
            if ($cgstAmount <= 0 && $sgstAmount <= 0) {
                $cgstAmount = (float) ($split['cgst'] ?? 0);
                $sgstAmount = (float) ($split['sgst'] ?? 0);
            }
            $igstAmount = 0.0;
            $cgstRate = self::HALF_RATE;
            $sgstRate = self::HALF_RATE;
            $igstRate = 0.0;
        }

        $gstLines = self::gstLines($taxType, $cgstRate, $sgstRate, $igstRate, $cgstAmount, $sgstAmount, $igstAmount);

        return [
            'tax_type' => $taxType,
            'taxType' => $taxType,
            'gst_rate' => $gstRate,
            'cgst_rate' => $cgstRate,
            'sgst_rate' => $sgstRate,
            'igst_rate' => $igstRate,
            'cgst' => $cgstAmount,
            'sgst' => $sgstAmount,
            'igst' => $igstAmount,
            'gst_amount' => $taxType === 'igst' ? $igstAmount : round($cgstAmount + $sgstAmount, 2),
            'show_igst' => $taxType === 'igst',
            'showIgst' => $taxType === 'igst',
            'show_cgst_sgst' => $taxType !== 'igst',
            'showCgstSgst' => $taxType !== 'igst',
            'gst_lines' => $gstLines,
            'gstLines' => $gstLines,
        ];
    }

    /**
     * Standard payment_breakdown block for partner APIs.
     *
     * @param array<string, mixed> $breakdown Result of resolveBreakdown()
     *
     * @return array<string, mixed>
     */
    public static function paymentBreakdownPayload(
        float $serviceAmount,
        array $breakdown,
        float $discountAmount = 0.0,
        ?float $totalAmount = null,
        array $extra = []
    ): array {
        $taxType = (string) ($breakdown['tax_type'] ?? 'cgst_sgst');
        $cgstAmount = (float) ($breakdown['cgst'] ?? 0);
        $sgstAmount = (float) ($breakdown['sgst'] ?? 0);
        $igstAmount = (float) ($breakdown['igst'] ?? 0);
        $cgstRate = (float) ($breakdown['cgst_rate'] ?? 0);
        $sgstRate = (float) ($breakdown['sgst_rate'] ?? 0);
        $igstRate = (float) ($breakdown['igst_rate'] ?? 0);

        if ($totalAmount === null) {
            $totalAmount = round($serviceAmount + $cgstAmount + $sgstAmount + $igstAmount - $discountAmount, 2);
        }

        return array_merge([
            'service_amount' => round($serviceAmount, 2),
            'service_amount_label' => '₹' . number_format($serviceAmount, 2),
            'cgst_rate' => $cgstRate,
            'cgst' => $cgstAmount,
            'cgst_label' => '₹' . number_format($cgstAmount, 2),
            'sgst_rate' => $sgstRate,
            'sgst' => $sgstAmount,
            'sgst_label' => '₹' . number_format($sgstAmount, 2),
            'igst_rate' => $igstRate,
            'igst' => $igstAmount,
            'igst_label' => '₹' . number_format($igstAmount, 2),
            'gst_rate' => $taxType === 'igst' ? $igstRate : ($cgstRate + $sgstRate),
            'gst_amount' => (float) ($breakdown['gst_amount'] ?? ($taxType === 'igst' ? $igstAmount : $cgstAmount + $sgstAmount)),
            'gst_amount_label' => '₹' . number_format(
                (float) ($breakdown['gst_amount'] ?? ($taxType === 'igst' ? $igstAmount : $cgstAmount + $sgstAmount)),
                2
            ),
            'tax_label' => $taxType === 'igst' ? 'IGST' : 'CGST + SGST',
            'tax_type' => $taxType,
            'taxType' => $taxType,
            'show_igst' => $taxType === 'igst',
            'showIgst' => $taxType === 'igst',
            'show_cgst_sgst' => $taxType !== 'igst',
            'showCgstSgst' => $taxType !== 'igst',
            'gst_lines' => $breakdown['gst_lines'] ?? self::gstLines($taxType, $cgstRate, $sgstRate, $igstRate, $cgstAmount, $sgstAmount, $igstAmount),
            'gstLines' => $breakdown['gstLines'] ?? ($breakdown['gst_lines'] ?? []),
            'discount' => round($discountAmount, 2),
            'discount_label' => '₹' . number_format($discountAmount, 2),
            'total_amount' => round($totalAmount, 2),
            'total_amount_label' => '₹' . number_format($totalAmount, 2),
        ], $extra);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function gstLines(
        string $taxType,
        float $cgstRate,
        float $sgstRate,
        float $igstRate,
        float $cgstAmount,
        float $sgstAmount,
        float $igstAmount
    ): array {
        if ($taxType === 'igst') {
            return [[
                'key' => 'igst',
                'label' => 'IGST',
                'rate' => $igstRate,
                'rate_label' => number_format($igstRate, 0) . '%',
                'amount' => $igstAmount,
                'amount_label' => '₹' . number_format($igstAmount, 2),
            ]];
        }

        return [
            [
                'key' => 'cgst',
                'label' => 'CGST',
                'rate' => $cgstRate,
                'rate_label' => number_format($cgstRate, 0) . '%',
                'amount' => $cgstAmount,
                'amount_label' => '₹' . number_format($cgstAmount, 2),
            ],
            [
                'key' => 'sgst',
                'label' => 'SGST',
                'rate' => $sgstRate,
                'rate_label' => number_format($sgstRate, 0) . '%',
                'amount' => $sgstAmount,
                'amount_label' => '₹' . number_format($sgstAmount, 2),
            ],
        ];
    }
}
