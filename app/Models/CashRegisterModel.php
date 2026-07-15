<?php

namespace App\Models;

class CashRegisterModel extends BaseUuidModel
{
    protected $table = 'cash_registers';
    protected $allowedFields = [
        'id',
        'company_id',
        'branch_id',
        'name',
        'code',
        'register_type',
        'account_id',
        'sales_point_of_sale_id',
        'is_default',
        'active',
        'created_at',
        'updated_at',
    ];
}
