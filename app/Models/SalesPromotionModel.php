<?php

namespace App\Models;

class SalesPromotionModel extends BaseUuidModel
{
    protected $table = 'sales_promotions';
    protected $allowedFields = [
        'id', 'company_id', 'name', 'description', 'promotion_type', 'scope', 'value',
        'trigger_quantity', 'bonus_quantity', 'bonus_product_id', 'payment_method', 'bundle_price',
        'start_date', 'end_date', 'active', 'created_at', 'updated_at',
    ];
}
