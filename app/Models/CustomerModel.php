<?php

namespace App\Models;

class CustomerModel extends BaseUuidModel
{
    protected $table         = 'customers';
    protected $allowedFields = [
        'id',
        'company_id',
        'branch_id',
        'name',
        'billing_name',
        'document_number',
        'document_type',
        'tax_profile',
        'vat_condition',
        'email',
        'phone',
        'address',
        'price_list_name',
        'price_list_id',
        'sales_agent_id',
        'sales_zone_id',
        'sales_condition_id',
        'credit_limit',
        'custom_discount_rate',
        'payment_terms_days',
        'active',
        'created_at',
        'updated_at',
    ];
}
