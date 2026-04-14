<?php

namespace App\Models;

class InventoryWarehouseModel extends BaseUuidModel
{
    protected $table         = 'inventory_warehouses';
    protected $allowedFields = [
        'id',
        'company_id',
        'branch_id',
        'name',
        'code',
        'type',
        'description',
        'is_default',
        'active',
        'created_at',
        'updated_at',
    ];
}
