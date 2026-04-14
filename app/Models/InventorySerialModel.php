<?php

namespace App\Models;

class InventorySerialModel extends BaseUuidModel
{
    protected $table = 'inventory_serials';
    protected $allowedFields = [
        'id',
        'company_id',
        'product_id',
        'warehouse_id',
        'location_id',
        'serial_number',
        'lot_number',
        'expiration_date',
        'status',
        'last_movement_id',
        'created_at',
        'updated_at',
    ];
}
