<?php

namespace App\Models;

class SaleItemModel extends BaseUuidModel
{
    protected $table         = 'sale_items';
    protected $allowedFields = [
        'id',
        'sale_id',
        'product_id',
        'tax_id',
        'line_number',
        'sku',
        'product_name',
        'product_type',
        'unit',
        'quantity',
        'returned_quantity',
        'available_stock_snapshot',
        'unit_price',
        'unit_cost',
        'discount_rate',
        'discount_amount',
        'tax_rate',
        'subtotal',
        'tax_total',
        'line_total',
        'created_at',
        'updated_at',
    ];
}
