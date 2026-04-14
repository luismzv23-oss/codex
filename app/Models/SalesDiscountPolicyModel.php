<?php

namespace App\Models;

class SalesDiscountPolicyModel extends BaseUuidModel
{
    protected $table = 'sales_discount_policies';
    protected $allowedFields = [
        'id', 'company_id', 'name', 'policy_type', 'payment_method', 'min_quantity', 'buy_quantity',
        'pay_quantity', 'discount_rate', 'fixed_discount', 'active', 'created_at', 'updated_at',
    ];
}
