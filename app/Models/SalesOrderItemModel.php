<?php

namespace App\Models;

class SalesOrderItemModel extends BaseUuidModel
{
    protected $table = 'sales_order_items';
    protected $allowedFields = [
        'sales_order_id', 'product_id', 'sku', 'product_name', 'quantity',
        'quantity_delivered', 'quantity_invoiced', 'unit_price', 'discount_pct',
        'tax_rate', 'line_subtotal', 'line_tax', 'line_total', 'sort_order',
    ];
}
