<?php

namespace App\Models;

class InventoryPeriodClosureModel extends BaseUuidModel
{
    protected $table = 'inventory_period_closures';
    protected $allowedFields = [
        'id', 'company_id', 'warehouse_id', 'period_code', 'start_date', 'end_date', 'status', 'notes',
        'created_by', 'created_at', 'updated_at',
    ];
}
