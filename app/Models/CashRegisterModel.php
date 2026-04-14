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
        'created_at',
        'updated_at',
    ];
}
