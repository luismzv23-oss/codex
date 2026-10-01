<?php

namespace App\Libraries;

/** Build a fiscal copy; never change the sale or the amounts collected. */
final class PaymentFiscalPolicy
{
    public static function forSale(array $sale, array $items): array
    {
        $payments = [];
        if (!empty($sale['id']) && !empty($sale['company_id']) && (float)($sale['payment_surcharge_amount'] ?? 0) > 0) {
            $payments = db_connect()->table('sale_payments p')
                ->select('p.surcharge_amount, p.status, m.show_on_receipt')
                ->join('sales s', 's.id = p.sale_id')
                ->join('company_payment_methods m', 'm.id = p.payment_method_id AND m.company_id = s.company_id', 'left')
                ->where('p.sale_id', $sale['id'])->where('s.company_id', $sale['company_id'])
                ->get()->getResultArray();
        }
        return self::apply($sale, $items, $payments);
    }

    public static function apply(array $sale, array $items, array $payments): array
    {
        $included = 0;
        foreach ($payments as $payment) {
            if ((int)($payment['show_on_receipt'] ?? 0) === 1 && ($payment['status'] ?? '') !== 'reversed') {
                $included += (int)round((float)($payment['surcharge_amount'] ?? 0) * 100);
            }
        }
        $maximum = (int)round((float)($sale['payment_surcharge_amount'] ?? 0) * 100);
        if ($included < 0 || $included > $maximum) {
            throw new \InvalidArgumentException('El recargo fiscal no coincide con los medios de pago de la venta.');
        }
        $sale['fiscal_payment_surcharge_amount'] = $included / 100;
        if ($included === 0) { return [$sale, $items]; }

        // Distribute a tax-inclusive addition using the existing net/tax proportions.
        // Cumulative rounding ensures that even mixed rates conserve every cent.
        $weights = array_map(static fn($item) => max(0, (int)round((float)($item['line_total'] ?? 0) * 100)), $items);
        $weightTotal = array_sum($weights);
        $netAdded = 0;
        $taxAdded = 0;
        if ($weightTotal > 0) {
            $cumulative = 0;
            $allocated = 0;
            foreach ($items as $index => &$item) {
                $cumulative += $weights[$index];
                $target = (int)round($included * $cumulative / $weightTotal);
                $addition = $target - $allocated;
                $allocated = $target;
                $tax = (float)($item['tax_total'] ?? $item['line_tax'] ?? 0);
                $net = (float)($item['subtotal'] ?? ((float)$item['line_total'] - $tax));
                $taxPart = $net + $tax > 0 ? (int)round($addition * $tax / ($net + $tax)) : 0;
                $netPart = $addition - $taxPart;
                $item['subtotal'] = round($net + $netPart / 100, 2);
                $item['tax_total'] = round($tax + $taxPart / 100, 2);
                $item['line_tax'] = $item['tax_total'];
                $item['line_total'] = round((float)$item['line_total'] + $addition / 100, 2);
                if ((float)($item['quantity'] ?? 0) > 0) {
                    $item['unit_price'] = $item['line_total'] / (float)$item['quantity'];
                }
                $netAdded += $netPart;
                $taxAdded += $taxPart;
            }
            unset($item);
        } else {
            $base = (float)($sale['subtotal'] ?? 0) + (float)($sale['tax_total'] ?? 0);
            if ($base <= 0) { throw new \InvalidArgumentException('No hay base fiscal para distribuir el recargo.'); }
            $taxAdded = (int)round($included * (float)($sale['tax_total'] ?? 0) / $base);
            $netAdded = $included - $taxAdded;
        }
        $sale['subtotal'] = round((float)($sale['subtotal'] ?? 0) + $netAdded / 100, 2);
        $sale['tax_total'] = round((float)($sale['tax_total'] ?? 0) + $taxAdded / 100, 2);
        return [$sale, $items];
    }
}
