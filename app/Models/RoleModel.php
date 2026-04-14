<?php

namespace App\Models;

class RoleModel extends BaseUuidModel
{
    protected $table         = 'roles';
    protected $allowedFields = [
        'id',
        'name',
        'slug',
        'description',
        'is_system',
        'created_at',
        'updated_at',
    ];

    public function findBySlug(string $slug): ?array
    {
        $role = $this->where('slug', $slug)->first();

        return $role ?: null;
    }
}
