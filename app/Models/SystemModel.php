<?php

namespace App\Models;

class SystemModel extends BaseUuidModel
{
    protected $table         = 'systems';
    protected $allowedFields = [
        'id',
        'name',
        'slug',
        'description',
        'entry_url',
        'icon',
        'active',
        'created_at',
        'updated_at',
    ];
}
