<?php

namespace App\Models;

class PermissionModel extends BaseUuidModel
{
    protected $table         = 'permissions';
    protected $allowedFields = [
        'id',
        'module',
        'name',
        'slug',
        'description',
        'created_at',
        'updated_at',
    ];
}
