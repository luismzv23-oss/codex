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
        'is_default',
        'active',
        'account_id',
        'sales_point_of_sale_id',
        'created_at',
        'updated_at',
    ];
}
