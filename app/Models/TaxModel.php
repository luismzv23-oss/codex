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
        'active',
        'created_at',
        'updated_at',
    ];
}
