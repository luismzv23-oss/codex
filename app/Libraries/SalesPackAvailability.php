<?php

namespace App\Libraries;

/** Selling kits consumes their configured components, never a second stock balance. */
class SalesPackAvailability
{
    public function catalog(string $companyId, array $products): array
    {
        if ($products === []) {
            return [];
        }
        $db = db_connect();
        $components = $db->table('inventory_kit_items k')
            ->select('k.product_id, k.component_product_id, k.quantity, c.name')
            ->join('inventory_products p', 'p.id = k.product_id')
            ->join('inventory_products c', 'c.id = k.component_product_id')
            ->where('p.company_id', $companyId)->where('c.company_id', $companyId)
            ->whereIn('k.product_id', array_column($products, 'id'))->get()->getResultArray();
        $stockIds = array_values(array_unique(array_merge(array_column($products, 'id'), array_column($components, 'component_product_id'))));
        $stocks = $db->table('inventory_stock_levels s')
            ->select('s.product_id, s.warehouse_id, SUM(s.quantity) AS stock, SUM(s.reserved_quantity) AS reserved', false)
            ->join('inventory_warehouses w', 'w.id = s.warehouse_id')
            ->where('s.company_id', $companyId)->where('w.company_id', $companyId)->where('w.active', 1)
            ->whereIn('s.product_id', $stockIds)
            ->groupBy('s.product_id, s.warehouse_id')->get()->getResultArray();
        return $this->calculate($products, $components, $stocks);
    }

    public function calculate(array $products, array $components, array $stocks): array
    {
        $balances = $recipes = $warehouses = [];
        foreach ($stocks as $row) {
            $balances[$row['product_id']][$row['warehouse_id']] = [
                'stock' => (float) $row['stock'], 'reserved' => (float) $row['reserved'],
                'available' => (float) $row['stock'] - (float) $row['reserved'],
            ];
            $warehouses[$row['warehouse_id']] = true;
        }
        foreach ($components as $row) {
            if ((float) $row['quantity'] > 0) {
                $id = $row['component_product_id'];
                $recipes[$row['product_id']][$id] = ($recipes[$row['product_id']][$id] ?? 0) + (float) $row['quantity'];
            }
        }
        foreach ($products as &$product) {
            $recipe = ($product['product_type'] ?? '') === 'kit' ? ($recipes[$product['id']] ?? []) : [];
            $product['stock_requirements'] = $recipe ?: [$product['id'] => 1.0];
            $product['component_stocks'] = [];
            foreach ($product['stock_requirements'] as $id => $quantity) {
                $product['component_stocks'][$id] = $balances[$id] ?? [];
            }
            $product['pack_units'] = $recipe ? array_sum($recipe) : null;
            $product['stocks'] = $balances[$product['id']] ?? [];
            if (!$recipe) {
                continue;
            }
            $product['stocks'] = [];
            foreach ($warehouses as $warehouse => $_) {
                $available = $gross = INF;
                foreach ($recipe as $id => $quantity) {
                    $balance = $balances[$id][$warehouse] ?? [];
                    $available = min($available, floor(max(0, $balance['available'] ?? 0) / $quantity));
                    $gross = min($gross, floor(max(0, $balance['stock'] ?? 0) / $quantity));
                }
                $product['stocks'][$warehouse] = ['stock' => $gross, 'available' => $available, 'reserved' => $gross - $available];
            }
        }
        unset($product);
        return $products;
    }
}
