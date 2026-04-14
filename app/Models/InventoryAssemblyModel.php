<?php

namespace App\Models;

class InventoryAssemblyModel extends BaseUuidModel
{
    protected $table = 'inventory_assemblies';
    protected $allowedFields = [
        'id', 'company_id', 'warehouse_id', 'product_id', 'assembly_number', 'assembly_type', 'quantity',
        'unit_cost', 'total_cost', 'issued_at', 'notes', 'created_by', 'created_at', 'updated_at',
    ];
}
