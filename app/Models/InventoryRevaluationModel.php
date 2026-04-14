<?php

namespace App\Models;

class InventoryRevaluationModel extends BaseUuidModel
{
    protected $table = 'inventory_revaluations';
    protected $allowedFields = [
        'id', 'company_id', 'product_id', 'warehouse_id', 'previous_unit_cost', 'new_unit_cost',
        'quantity_snapshot', 'difference_amount', 'issued_at', 'notes', 'created_by', 'created_at', 'updated_at',
    ];
}
