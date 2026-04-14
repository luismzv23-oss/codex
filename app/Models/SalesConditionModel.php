<?php

namespace App\Models;

class SalesConditionModel extends BaseUuidModel
{
    protected $table = 'sales_conditions';

    protected $allowedFields = [
        'id', 'company_id', 'name', 'code', 'payment_terms_days', 'credit_limit', 'discount_rate', 'requires_invoice', 'requires_authorization', 'notes', 'active', 'created_at', 'updated_at',
    ];
}
