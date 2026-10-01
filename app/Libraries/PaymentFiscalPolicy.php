<?php

namespace App\Libraries;

/** Build a fiscal copy; never change the sale or the amounts collected. */
final class PaymentFiscalPolicy
{
    public static function forReceipt(array $sale, array $items, array $payments, array $fiscal = []): array
    {
        // Match the sales screen: distribute the saved amount collected, not the
        // separately authorized fiscal amount. Never recalculate the charge itself.
        $targetTotal = (int)round((float)$sale['total'] * 100);
        $weights = array_map(static fn($item) => max(0, (int)round((float)($item['line_total'] ?? 0) * 100)), $items);
        $weightTotal = array_sum($weights);
        if ($weightTotal <= 0) { return [$sale, $items]; }
        $cumulative = 0;
        $allocated = 0;
        $netTotal = 0;
        foreach ($items as $index => &$item) {
            $cumulative += $weights[$index];
            $target = (int)round($targetTotal * $cumulative / $weightTotal);
            $gross = $target - $allocated;
            $allocated = $target;
            $rate = (float)($item['tax_rate'] ?? 0);
            $net = (int)round($gross / (1 + $rate / 100));
            $item['subtotal'] = $net / 100.0;
            $item['tax_total'] = ($gross - $net) / 100.0;
            $item['line_tax'] = $item['tax_total'];
            $item['line_total'] = $gross / 100.0;
            if ((float)($item['quantity'] ?? 0) > 0) {
                $item['unit_price'] = $item['line_total'] / (float)$item['quantity'];
            }
            // Discounts are already included in the final line price.
            $item['discount_rate'] = 0;
            $netTotal += $net;
        }
        unset($item);
        $sale['subtotal'] = $netTotal / 100.0;
        $sale['tax_total'] = ($targetTotal - $netTotal) / 100.0;
        $sale['total'] = $targetTotal / 100.0;
        $sale['global_discount_total'] = 0;
        $sale['item_discount_total'] = 0;
        return [$sale, $items];
    }

    public static function forSale(array $sale, array $items): array
    {
        // ARCA and the customer receipt share the exact same calculation.
        // Payment visibility cannot change an invoice's fiscal amounts.
        if (!$items) {
            $base = (float)($sale['subtotal'] ?? 0) + (float)($sale['tax_total'] ?? 0);
            if ($base > 0) {
                $sale['subtotal'] = round((float)$sale['total'] * (float)($sale['subtotal'] ?? 0) / $base, 2);
                $sale['tax_total'] = round((float)$sale['total'] - $sale['subtotal'], 2);
            }
        }
        return self::forReceipt($sale, $items, []);
    }

    public static function apply(array $sale, array $items, array $payments): array
    {
        return self::forSale($sale, $items);
    }
}
