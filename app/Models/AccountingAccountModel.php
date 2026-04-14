<?php

namespace App\Models;

class AccountingAccountModel extends BaseUuidModel
{
    protected $table = 'accounting_accounts';

    protected $allowedFields = [
        'id',
        'company_id',
        'code',
        'name',
        'category',
        'nature',
        'system_key',
        'active',
        'created_at',
        'updated_at',
    ];
}
