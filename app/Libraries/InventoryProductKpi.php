<?php

namespace App\Libraries;

/**
 * Totalized KPI analysis for a single inventory product over a date period.
 * Read-only: never mutates stock.
 */
final class InventoryProductKpi
{
    public static function period(?string $from, ?string $to): array
    {
        $valid = static function ($value): ?string {
            $d = is_string($value) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
            return $d && $d->format('Y-m-d') === $value ? $value : null;
        };
        $to = $valid($to) ?? date('Y-m-d');
        $from = $valid($from) ?? date('Y-m-d', strtotime($to . ' -29 days'));
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    /** Flat [section, label, value, kind] list shared by the PDF and Excel exports; kind: qty|money|cost|pct|days|ratio|text. */
    public static function rows(array $kpi): array
    {
        $s = $kpi['stock']; $v = $kpi['valuation']; $f = $kpi['flow']; $i = $kpi['indicators'];

        return [
            ['Existencias', 'Stock total', $s['quantity'], 'qty'],
            ['Existencias', 'Reservado', $s['reserved'], 'qty'],
            ['Existencias', 'Disponible', $s['available'], 'qty'],
            ['Existencias', 'Depositos con stock', $s['warehouses_with_stock'], 'int'],
            ['Existencias', 'Stock minimo / maximo', number_format($s['min'], 2, ',', '.') . ' / ' . number_format($s['max'], 2, ',', '.'), 'text'],
            ['Valorizacion', 'Valor del stock', $v['value'], 'money'],
            ['Valorizacion', 'Costo promedio unitario', $v['average_cost'], 'cost'],
            ['Flujo del periodo', 'Stock inicial estimado', $f['opening'], 'qty'],
            ['Flujo del periodo', 'Entradas', $f['in'], 'qty'],
            ['Flujo del periodo', 'Salidas', $f['out'], 'qty'],
            ['Flujo del periodo', 'Transferencias internas', $f['transfer'], 'qty'],
            ['Flujo del periodo', 'Variacion neta', $f['net'], 'qty'],
            ['Flujo del periodo', 'Stock final del periodo', $f['closing'], 'qty'],
            ['Flujo del periodo', 'Cantidad de movimientos', $f['movements'], 'int'],
            ['Flujo del periodo', 'Costo de las salidas', $f['out_cost'], 'money'],
            ['Indicadores', 'Rotacion (salidas / stock promedio)', $i['rotation'], 'ratio'],
            ['Indicadores', 'Salida promedio diaria', $i['daily_out'], 'qty'],
            ['Indicadores', 'Dias de cobertura', $i['coverage_days'], 'days'],
            ['Indicadores', 'Dias desde el ultimo movimiento', $i['days_since_last_movement'], 'int'],
            ['Indicadores', '% reservado', $i['reserved_pct'], 'pct'],
            ['Indicadores', 'Estado vs minimo / maximo', $i['status'], 'text'],
        ];
    }

    public static function format($value, string $kind): string
    {
        if ($value === null) {
            return '-';
        }
        return match ($kind) {
            'text' => (string) $value,
            'int' => number_format((float) $value, 0, ',', '.'),
            'money' => '$ ' . number_format((float) $value, 2, ',', '.'),
            'cost' => '$ ' . number_format((float) $value, 4, ',', '.'),
            'pct' => number_format((float) $value, 2, ',', '.') . ' %',
            'days' => number_format((float) $value, 1, ',', '.') . ' dias',
            default => number_format((float) $value, 2, ',', '.'),
        };
    }

    public function build(string $companyId, array $product, string $from, string $to): array
    {
        [$from, $to] = self::period($from, $to);
        $db = db_connect();
        $productId = (string) $product['id'];
        $days = (int) ((new \DateTimeImmutable($from))->diff(new \DateTimeImmutable($to))->days) + 1;
        $periodEnd = date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00';

        $warehouses = array_column($db->table('inventory_warehouses')->select('id, name, code')
            ->where('company_id', $companyId)->orderBy('name', 'ASC')->get()->getResultArray(), null, 'id');

        $byWarehouse = [];
        $ensure = static function (?string $warehouseId) use (&$byWarehouse, $warehouses): ?string {
            if ($warehouseId === null || $warehouseId === '' || ! isset($warehouses[$warehouseId])) {
                return null;
            }
            $byWarehouse[$warehouseId] ??= [
                'warehouse_id' => $warehouseId, 'name' => $warehouses[$warehouseId]['name'], 'code' => $warehouses[$warehouseId]['code'],
                'quantity' => 0.0, 'reserved' => 0.0, 'available' => 0.0, 'in' => 0.0, 'out' => 0.0, 'value' => 0.0,
            ];
            return $warehouseId;
        };

        $stockRows = $db->table('inventory_stock_levels')->select('warehouse_id')->selectSum('quantity')->selectSum('reserved_quantity')
            ->where('company_id', $companyId)->where('product_id', $productId)->groupBy('warehouse_id')->get()->getResultArray();
        foreach ($stockRows as $row) {
            if ($w = $ensure((string) $row['warehouse_id'])) {
                $byWarehouse[$w]['quantity'] = (float) $row['quantity'];
                $byWarehouse[$w]['reserved'] = (float) $row['reserved_quantity'];
                $byWarehouse[$w]['available'] = (float) $row['quantity'] - (float) $row['reserved_quantity'];
            }
        }

        // Valuation from open cost layers; warehouses without layers fall back to the product cost price.
        $layerRows = $db->table('inventory_cost_layers')->select('warehouse_id, remaining_quantity, unit_cost')
            ->where('company_id', $companyId)->where('product_id', $productId)->where('remaining_quantity >', 0)->get()->getResultArray();
        $layerValue = [];
        foreach ($layerRows as $row) {
            $layerValue[$row['warehouse_id']] = ($layerValue[$row['warehouse_id']] ?? 0.0) + (float) $row['remaining_quantity'] * (float) $row['unit_cost'];
        }
        foreach ($byWarehouse as $w => $row) {
            $byWarehouse[$w]['value'] = $layerValue[$w] ?? max(0.0, $row['quantity']) * (float) ($product['cost_price'] ?? 0);
        }

        $flow = ['in' => 0.0, 'out' => 0.0, 'transfer' => 0.0, 'movements' => 0, 'out_cost' => 0.0];
        $netAfter = 0.0;
        $movements = $db->table('inventory_movements')
            ->select('movement_type, adjustment_mode, quantity, total_cost, source_warehouse_id, destination_warehouse_id, occurred_at')
            ->where('company_id', $companyId)->where('product_id', $productId)->where('occurred_at >=', $from . ' 00:00:00')
            ->get()->getResultArray();
        foreach ($movements as $m) {
            $qty = (float) $m['quantity'];
            $type = (string) $m['movement_type'];
            $increase = $type === 'ingreso' || ($type === 'ajuste' && $m['adjustment_mode'] === 'increase');
            $decrease = $type === 'egreso' || ($type === 'ajuste' && $m['adjustment_mode'] === 'decrease');
            if ((string) $m['occurred_at'] >= $periodEnd) {
                $netAfter += $increase ? $qty : ($decrease ? -$qty : 0.0);
                continue;
            }
            $flow['movements']++;
            if ($increase) {
                $flow['in'] += $qty;
                if ($w = $ensure($type === 'ingreso' ? $m['destination_warehouse_id'] : $m['source_warehouse_id'])) {
                    $byWarehouse[$w]['in'] += $qty;
                }
            } elseif ($decrease) {
                $flow['out'] += $qty;
                $flow['out_cost'] += (float) ($m['total_cost'] ?? 0);
                if ($w = $ensure($m['source_warehouse_id'])) {
                    $byWarehouse[$w]['out'] += $qty;
                }
            } elseif ($type === 'transferencia') {
                $flow['transfer'] += $qty;
                if ($w = $ensure($m['source_warehouse_id'])) {
                    $byWarehouse[$w]['out'] += $qty;
                }
                if ($w = $ensure($m['destination_warehouse_id'])) {
                    $byWarehouse[$w]['in'] += $qty;
                }
            }
        }
        $flow['net'] = $flow['in'] - $flow['out'];

        $totals = ['quantity' => 0.0, 'reserved' => 0.0, 'available' => 0.0, 'in' => 0.0, 'out' => 0.0, 'value' => 0.0];
        foreach ($byWarehouse as $row) {
            foreach ($totals as $key => $_) {
                $totals[$key] += $row[$key];
            }
        }

        $stock = $totals['quantity'];
        $closing = $stock - $netAfter;
        $opening = $closing - $flow['net'];
        $averageStock = ($opening + $closing) / 2;
        $dailyOut = $flow['out'] / $days;
        $min = (float) ($product['min_stock'] ?? 0);
        $max = (float) ($product['max_stock'] ?? 0);
        $lastMovement = $db->table('inventory_movements')->selectMax('occurred_at', 'last')
            ->where('company_id', $companyId)->where('product_id', $productId)->get()->getRowArray()['last'] ?? null;

        return [
            'period' => ['from' => $from, 'to' => $to, 'days' => $days],
            'stock' => [
                'quantity' => $stock,
                'reserved' => $totals['reserved'],
                'available' => $totals['available'],
                'warehouses_with_stock' => count(array_filter($byWarehouse, static fn(array $r): bool => $r['quantity'] > 0)),
                'min' => $min,
                'max' => $max,
            ],
            'valuation' => [
                'value' => $totals['value'],
                'average_cost' => $stock > 0 ? $totals['value'] / $stock : null,
            ],
            'flow' => $flow + ['opening' => $opening, 'closing' => $closing],
            'indicators' => [
                'rotation' => $averageStock > 0 ? $flow['out'] / $averageStock : null,
                'coverage_days' => $dailyOut > 0 ? max(0.0, $totals['available']) / $dailyOut : null,
                'daily_out' => $dailyOut,
                'days_since_last_movement' => $lastMovement ? (int) floor((time() - strtotime((string) $lastMovement)) / 86400) : null,
                'last_movement_at' => $lastMovement,
                'reserved_pct' => $stock > 0 ? $totals['reserved'] / $stock * 100 : null,
                'status' => match (true) {
                    $stock <= 0 => 'Sin stock',
                    $min > 0 && $stock <= $min => 'Bajo minimo',
                    $max > 0 && $stock > $max => 'Sobrestock',
                    default => 'Normal',
                },
            ],
            'warehouses' => array_values($byWarehouse),
            'totals' => $totals,
        ];
    }
}
