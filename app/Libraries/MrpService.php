<?php

namespace App\Libraries;

/**
 * MrpService — compatibilidad del endpoint POST /api/v1/mrp/calculate.
 * El cálculo vive en ReplenishmentAnalysisService (pronóstico, stock de seguridad, plazos observados);
 * aquí solo se adapta el resultado al formato histórico de la API.
 */
class MrpService
{
    public function calculateRequirements(string $companyId, ?string $warehouseId = null, array $params = []): array
    {
        $analysis = (new ReplenishmentAnalysisService())->analyze($companyId, ['warehouse_id' => $warehouseId] + $params);
        $requirements = [];
        foreach ($analysis['rows'] as $row) {
            if ($row['status'] !== 'reponer') {
                continue;
            }
            $requirements[] = [
                'product_id' => $row['product_id'],
                'sku' => $row['sku'],
                'product_name' => $row['name'],
                'physical_stock' => $row['on_hand'],
                'reserved_stock' => $row['reserved'],
                'pending_purchase_qty' => $row['on_order'],
                'available_net' => $row['available'],
                'min_stock' => $row['min_stock'],
                'safety_stock' => $row['safety_stock'],
                'dynamic_reorder_point' => $row['reorder_point'],
                'target_level' => $row['target_level'],
                'daily_consumption' => $row['daily_demand'],
                'forecast_method' => $row['forecast_method'],
                'lead_time_days' => $row['lead_time'],
                'coverage_days' => $row['coverage_days'],
                'priority' => $row['priority'],
                'abc_class' => $row['abc'],
                'xyz_class' => $row['xyz'],
                'suggested_purchase_qty' => $row['suggested_qty'],
                'supplier_id' => $row['supplier_id'],
                'unit_cost' => $row['unit_cost'],
                'total_estimated_cost' => $row['estimated_cost'],
                'reasons' => $row['reasons'],
            ];
        }

        return [
            'calculation_date' => $analysis['generated_at'],
            'parameters' => $analysis['params'],
            'total_products_checked' => $analysis['summary']['analyzed'],
            'reorder_triggered_count' => count($requirements),
            'summary' => $analysis['summary'],
            'requirements' => $requirements,
        ];
    }
}
