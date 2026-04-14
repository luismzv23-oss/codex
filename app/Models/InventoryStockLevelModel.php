<?php

namespace App\Models;

class InventoryStockLevelModel extends BaseUuidModel
{
    protected $table         = 'inventory_stock_levels';
    protected $allowedFields = [
        'id',
        'company_id',
        'product_id',
        'warehouse_id',
        'location_id',
        'quantity',
        'reserved_quantity',
        'min_stock',
        'location_label',
        'created_at',
        'updated_at',
    ];
}
