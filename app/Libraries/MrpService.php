<?php

namespace App\Libraries;

/**
 * MrpService — Material Requirements Planning (MRP I) & Dynamic Reorder Engine.
 * Evaluates current stock, pending sales orders, active stock reservations, minimum safety stock,
 * supplier lead times, and daily consumption rates to generate automated purchase requisitions.
 */
class MrpService
{
    /**
     * Run MRP I calculation across all products for a given company and warehouse.
     */
    public function calculateRequirements(string $companyId, ?string $warehouseId = null): array
    {
        $db = db_connect();

        // 1. Fetch active products
        $builder = $db->table('inventory_products')
            ->where('company_id', $companyId)
            ->where('active', 1);

        $products = $builder->get()->getResultArray();
        $requirements = [];
        $purchaseDrafts = [];

        foreach ($products as $product) {
            $productId = $product['id'];
            $sku = $product['sku'] ?? $product['code'] ?? 'SKU-'.$productId;
            $minStock = (float) ($product['min_stock'] ?? 0);
            $reorderPoint = (float) ($product['reorder_point'] ?? $minStock);
            $leadTimeDays = (int) ($product['lead_time_days'] ?? 7);

            // 2. Fetch current physical stock
            $stockQuery = $db->table('inventory_stock_levels')
                ->where('company_id', $companyId)
                ->where('product_id', $productId);
            if ($warehouseId) {
                $stockQuery->where('warehouse_id', $warehouseId);
            }
            $stockRow = $stockQuery->selectSum('quantity')->get()->getRowArray();
            $physicalStock = (float) ($stockRow['quantity'] ?? 0);

            // 3. Fetch active stock reservations (committed stock)
            $resQuery = $db->table('inventory_reservations')
                ->where('company_id', $companyId)
                ->where('product_id', $productId)
                ->where('status', 'active');
            if ($warehouseId) {
                $resQuery->where('warehouse_id', $warehouseId);
            }
            $resRow = $resQuery->selectSum('quantity')->get()->getRowArray();
            $reservedStock = (float) ($resRow['quantity'] ?? 0);

            // 4. Fetch pending approved sales orders (committed demand)
            $orderDemand = 0.0;
            $orderItems = $db->table('sales_order_items soi')
                ->join('sales_orders so', 'so.id = soi.sales_order_id')
                ->where('so.company_id', $companyId)
                ->where('soi.product_id', $productId)
                ->whereIn('so.status', ['approved', 'processing'])
                ->selectSum('soi.quantity')
                ->get()->getRowArray();
            $orderDemand = (float) ($orderItems['quantity'] ?? 0);

            // Available stock calculation
            $availableStock = $physicalStock - $reservedStock;
            $netAvailable = $availableStock - $orderDemand;

            // 5. Calculate average daily consumption (past 30 days)
            $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
            $salesPastMonth = $db->table('sale_items si')
                ->join('sales s', 's.id = si.sale_id')
                ->where('s.company_id', $companyId)
                ->where('si.product_id', $productId)
                ->where('s.sale_date >=', $thirtyDaysAgo)
                ->selectSum('si.quantity')
                ->get()->getRowArray();
            
            $unitsSoldMonth = (float) ($salesPastMonth['quantity'] ?? 0);
            $dailyConsumption = round($unitsSoldMonth / 30.0, 2);

            // Demand expected during supplier lead time
            $leadTimeDemand = round($dailyConsumption * $leadTimeDays, 2);
            $dynamicReorderPoint = max($reorderPoint, $minStock + $leadTimeDemand);

            // Check if reorder is triggered
            if ($netAvailable < $dynamicReorderPoint) {
                $deficit = $dynamicReorderPoint - $netAvailable;
                $suggestedOrderQty = ceil(max($deficit, (float) ($product['min_order_qty'] ?? 1)));

                $reqItem = [
                    'product_id'            => $productId,
                    'sku'                   => $sku,
                    'product_name'          => $product['name'],
                    'physical_stock'        => $physicalStock,
                    'reserved_stock'        => $reservedStock,
                    'pending_sales_demand'  => $orderDemand,
                    'available_net'         => $netAvailable,
                    'min_stock'             => $minStock,
                    'dynamic_reorder_point' => $dynamicReorderPoint,
                    'daily_consumption'     => $dailyConsumption,
                    'lead_time_days'        => $leadTimeDays,
                    'suggested_purchase_qty' => $suggestedOrderQty,
                    'supplier_id'           => $product['preferred_supplier_id'] ?? null,
                    'unit_cost'             => (float) ($product['cost_price'] ?? 0),
                    'total_estimated_cost'  => round($suggestedOrderQty * (float) ($product['cost_price'] ?? 0), 2),
                ];

                $requirements[] = $reqItem;
            }
        }

        return [
            'calculation_date'   => date('Y-m-d H:i:s'),
            'total_products_checked' => count($products),
            'reorder_triggered_count' => count($requirements),
            'requirements'       => $requirements,
        ];
    }
}
