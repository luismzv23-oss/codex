<?php

namespace App\Models;

class SalesCreditFlagModel extends BaseUuidModel
{
    protected $table = 'sales_credit_flags';
    protected $allowedFields = [
        'id', 'company_id', 'customer_id', 'flag_type', 'score_value', 'status', 'notes', 'created_at', 'updated_at',
    ];
}
