<?php

namespace App\Models;

class TaxModel extends BaseUuidModel
{
    protected $table         = 'taxes';
    protected $allowedFields = [
        'id',
        'company_id',
        'name',
        'code',
        'rate',
        'afip_code',
        'is_default',
        'active',
        'created_at',
        'updated_at',
    ];
}
