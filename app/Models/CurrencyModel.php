<?php

namespace App\Models;

class CurrencyModel extends BaseUuidModel
{
    protected $table         = 'currencies';
    protected $allowedFields = [
        'id',
        'company_id',
        'code',
        'name',
        'symbol',
        'exchange_rate',
        'is_default',
        'active',
        'created_at',
        'updated_at',
    ];
}
