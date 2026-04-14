<?php

namespace App\Models;

class InventoryAssemblyItemModel extends BaseUuidModel
{
    protected $table = 'inventory_assembly_items';
    protected $allowedFields = [
        'id', 'inventory_assembly_id', 'component_product_id', 'quantity', 'unit_cost', 'total_cost',
        'created_at', 'updated_at',
    ];
}
