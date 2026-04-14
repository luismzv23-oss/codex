<?php

namespace App\Models;

class InventoryLotModel extends BaseUuidModel
{
    protected $table = 'inventory_lots';
    protected $allowedFields = [
        'id',
        'company_id',
        'product_id',
        'warehouse_id',
        'location_id',
        'lot_number',
        'expiration_date',
        'quantity_balance',
        'status',
        'created_at',
        'updated_at',
    ];
}
