<?php

namespace App\Models;

class InventoryProductModel extends BaseUuidModel
{
    protected $table         = 'inventory_products';
    protected $allowedFields = [
        'id',
        'company_id',
        'sku',
        'name',
        'category',
        'brand',
        'barcode',
        'product_type',
        'description',
        'unit',
        'min_stock',
        'max_stock',
        'sale_price',
        'cost_price',
        'lot_control',
        'serial_control',
        'expiration_control',
        'active',
        'created_at',
        'updated_at',
    ];
}
