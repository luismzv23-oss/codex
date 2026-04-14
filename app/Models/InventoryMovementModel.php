<?php

namespace App\Models;

class InventoryMovementModel extends BaseUuidModel
{
    protected $table         = 'inventory_movements';
    protected $allowedFields = [
        'id',
        'company_id',
        'product_id',
        'movement_type',
        'quantity',
        'unit_cost',
        'total_cost',
        'adjustment_mode',
        'source_warehouse_id',
        'source_location_id',
        'destination_warehouse_id',
        'destination_location_id',
        'performed_by',
        'occurred_at',
        'reason',
        'source_document',
        'lot_number',
        'serial_number',
        'expiration_date',
        'notes',
        'created_at',
        'updated_at',
    ];
}
