<?php

namespace App\Models;

class SalesPriceListModel extends BaseUuidModel
{
    protected $table         = 'sales_price_lists';
    protected $allowedFields = ['id', 'company_id', 'name', 'description', 'is_default', 'active', 'created_at', 'updated_at'];
}
