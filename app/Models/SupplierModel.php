<?php

namespace App\Models;

class SupplierModel extends BaseUuidModel
{
    protected $table         = 'suppliers';
    protected $allowedFields = ['id', 'company_id', 'branch_id', 'name', 'legal_name', 'tax_id', 'email', 'phone', 'address', 'vat_condition', 'payment_terms_days', 'active', 'created_at', 'updated_at'];
}
