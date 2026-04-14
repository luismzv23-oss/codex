<?php

namespace App\Models;

class UserSystemModel extends BaseUuidModel
{
    protected $table         = 'user_systems';
    protected $allowedFields = [
        'id',
        'company_id',
        'user_id',
        'system_id',
        'access_level',
        'active',
        'created_at',
        'updated_at',
    ];
}
