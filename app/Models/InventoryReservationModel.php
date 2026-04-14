<?php

namespace App\Models;

class InventoryReservationModel extends BaseUuidModel
{
    protected $table         = 'inventory_reservations';
    protected $allowedFields = [
        'id',
        'company_id',
        'product_id',
        'warehouse_id',
        'sale_id',
        'quantity',
        'reference',
        'notes',
        'status',
        'reserved_by',
        'reserved_at',
        'released_by',
        'released_at',
        'created_at',
        'updated_at',
    ];
}
