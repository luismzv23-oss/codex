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
        'image',
        'unit',
        'min_stock',
        'max_stock',
        'sale_price',
        'cost_price',
        'lot_control',
        'serial_control',
        'expiration_control',
        'active',
        'preferred_supplier_id',
        'lead_time_days',
        'min_order_qty',
        'order_multiple',
        'created_at',
        'updated_at',
    ];
}
