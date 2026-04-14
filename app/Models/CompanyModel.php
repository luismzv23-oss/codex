<?php

namespace App\Models;

class CompanyModel extends BaseUuidModel
{
    protected $table         = 'companies';
    protected $allowedFields = [
        'id',
        'name',
        'legal_name',
        'tax_id',
        'email',
        'phone',
        'address',
        'currency_code',
        'active',
        'created_at',
        'updated_at',
    ];
}
