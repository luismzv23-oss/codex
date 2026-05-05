<?php

namespace App\Models;

class SalesQuoteItemModel extends BaseUuidModel
{
    protected $table = 'sales_quote_items';
    protected $allowedFields = [
        'sales_quote_id', 'product_id', 'sku', 'product_name', 'quantity',
        'unit_price', 'discount_pct', 'tax_rate', 'line_subtotal', 'line_tax',
        'line_total', 'sort_order',
    ];
}
