<?php

namespace App\Models;

class SalesPromotionItemModel extends BaseUuidModel
{
    protected $table         = 'sales_promotion_items';
    protected $allowedFields = ['id', 'promotion_id', 'product_id', 'created_at', 'updated_at'];
}
