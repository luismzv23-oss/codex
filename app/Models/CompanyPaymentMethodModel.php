<?php
namespace App\Models;

class CompanyPaymentMethodModel extends BaseUuidModel
{
    protected $table = 'company_payment_methods';
    protected $useSoftDeletes = true;
    protected $allowedFields = ['company_id', 'code', 'name', 'type', 'active', 'currency_ids',
        'funds_destination', 'required_fields', 'allows_installments', 'requires_confirmation',
        'branch_ids', 'point_of_sale_ids', 'percentage'];
}
