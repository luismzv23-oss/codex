<?php
namespace App\Libraries;

use RuntimeException;

/** Numeric rules for the document import form, independent of other purchase flows. */
class PurchaseImportAmounts
{
    public static function normalize(array $data): array
    {
        if (!is_array($data['items'] ?? [])) return $data;
        foreach ($data['items'] ?? [] as $index => $item) {
            if (!is_array($item)) continue;
            foreach (['unit_cost', 'tax_rate'] as $key) {
                if (isset($item[$key]) && preg_match('/^\d+(?:\.\d+)?$/D', (string) $item[$key])) {
                    $item[$key] = number_format(round((float) $item[$key], 2), 2, '.', '');
                }
            }
            // The form exposes calculated VAT; stale manual amounts must not override it.
            $item['tax_amount'] = '';
            $data['items'][$index] = $item;
        }
        return $data;
    }

    public static function validate(array $data): void
    {
        $net = $tax = 0;
        foreach ($data['items'] ?? [] as $index => $item) {
            $quantity = PurchaseInvoiceService::number($item['quantity'] ?? '', 6);
            if ($quantity <= 0 || floor($quantity) !== $quantity) {
                throw new RuntimeException('El concepto '.($index + 1).' requiere una cantidad entera mayor que cero.');
            }
            $base = round($quantity * PurchaseInvoiceService::number($item['unit_cost'] ?? '', 2), 2);
            $net += (int) round($base * 100);
            $tax += (int) round(round($base * PurchaseInvoiceService::number($item['tax_rate'] ?? '', 2, 100) / 100, 2) * 100);
        }
        foreach ($data['adjustments'] ?? [] as $adjustment) {
            if (($adjustment['kind'] ?? '') === 'discount') continue;
            $label = mb_strtolower(trim($adjustment['label'] ?? ''));
            $amount = (int) round(PurchaseInvoiceService::number($adjustment['amount'] ?? '', 2) * 100);
            if ($net > 0 && preg_match('/^(neto(?: gravado)?|subtotal)$/u', $label) && $amount === $net) {
                throw new RuntimeException('El ajuste de neto o subtotal duplica el importe de los conceptos. Revisa los ajustes antes de registrar.');
            }
            if ($tax > 0 && preg_match('/^(?:iva(?:\s+\d+(?:[.,]\d+)?\s*%)?|total iva)$/u', $label) && abs($amount - $tax) <= 1) {
                throw new RuntimeException('El ajuste de IVA duplica el IVA calculado de los conceptos. Conserva únicamente el redondeo que corresponda.');
            }
        }
    }
}
