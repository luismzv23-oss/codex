<?php

namespace App\Models;

class InventoryCostLayerModel extends BaseUuidModel
{
    protected $table = 'inventory_cost_layers';
    protected $allowedFields = [
        'id',
        'company_id',
        'product_id',
        'warehouse_id',
        'location_id',
        'movement_id',
        'layer_type',
        'quantity',
        'remaining_quantity',
        'unit_cost',
        'total_cost',
        'occurred_at',
        'created_at',
        'updated_at',
    ];
}
