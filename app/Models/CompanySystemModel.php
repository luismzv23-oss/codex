<?php

namespace App\Models;

class CompanySystemModel extends BaseUuidModel
{
    protected $table         = 'company_systems';
    protected $allowedFields = [
        'id',
        'company_id',
        'system_id',
        'active',
        'created_at',
        'updated_at',
    ];
}
