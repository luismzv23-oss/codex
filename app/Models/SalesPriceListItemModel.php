<?php

namespace App\Models;

class SalesPriceListItemModel extends BaseUuidModel
{
    protected $table         = 'sales_price_list_items';
    protected $allowedFields = ['id', 'price_list_id', 'product_id', 'price', 'created_at', 'updated_at'];
}
