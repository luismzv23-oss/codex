<?php

namespace App\Models;

class InventoryPeriodClosureModel extends BaseUuidModel
{
    protected $table = 'inventory_period_closures';
    protected $allowedFields = [
        'id', 'company_id', 'warehouse_id', 'period_code', 'start_date', 'end_date', 'status', 'notes',
        'created_by', 'created_at', 'updated_at',
    ];

    public static function isPeriodClosed(string $companyId, string $date, ?string $warehouseId = null): bool
    {
        $db = db_connect();
        $builder = $db->table('inventory_period_closures')
            ->where('company_id', $companyId)
            ->where('status', 'closed')
            ->where('start_date <=', $date)
            ->where('end_date >=', $date);

        if ($warehouseId !== null) {
            $builder->groupStart()
                ->where('warehouse_id', $warehouseId)
                ->orWhere('warehouse_id', null)
                ->groupEnd();
        }

        return $builder->countAllResults() > 0;
    }
}
