<?php

namespace App\Models;

class SaleReturnItemModel extends BaseUuidModel
{
    protected $table         = 'sale_return_items';
    protected $allowedFields = [
        'id',
        'sale_return_id',
        'sale_item_id',
        'product_id',
        'quantity',
        'unit_price',
        'line_total',
        'reason',
        'created_at',
        'updated_at',
    ];
}
