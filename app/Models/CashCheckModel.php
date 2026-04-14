<?php

namespace App\Models;

class CashCheckModel extends BaseUuidModel
{
    protected $table = 'cash_checks';
    protected $allowedFields = [
        'id', 'company_id', 'supplier_id', 'customer_id', 'check_type', 'check_number', 'bank_name',
        'issuer_name', 'due_date', 'amount', 'status', 'notes', 'created_by', 'created_at', 'updated_at',
    ];
}
