<?php

namespace App\Models;

class InventoryKitItemModel extends BaseUuidModel
{
    protected $table = 'inventory_kit_items';
    protected $allowedFields = [
        'id',
        'product_id',
        'component_product_id',
        'quantity',
        'created_at',
        'updated_at',
    ];
}
