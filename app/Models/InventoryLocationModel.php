<?php

namespace App\Models;

class InventoryLocationModel extends BaseUuidModel
{
    protected $table = 'inventory_locations';
    protected $allowedFields = [
        'id',
        'company_id',
        'warehouse_id',
        'name',
        'code',
        'zone',
        'rack',
        'level',
        'description',
        'active',
        'created_at',
        'updated_at',
    ];
}
