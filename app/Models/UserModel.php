<?php

namespace App\Models;

class UserModel extends BaseUuidModel
{
    protected $table         = 'users';
    protected $allowedFields = [
        'id',
        'company_id',
        'branch_id',
        'role_id',
        'name',
        'username',
        'email',
        'password_hash',
        'two_factor_secret',
        'two_factor_enabled',
        'two_factor_backup_codes',
        'must_change_password',
        'active',
        'last_login_at',
        'created_at',
        'updated_at',
    ];

    public function findForAuth(string $login): ?array
    {
        $user = $this
            ->select('users.*, companies.name AS company_name, roles.name AS role_name, roles.slug AS role_slug, branches.name AS branch_name, branches.code AS branch_code')
            ->join('companies', 'companies.id = users.company_id', 'left')
            ->join('branches', 'branches.id = users.branch_id', 'left')
            ->join('roles', 'roles.id = users.role_id', 'left')
            ->groupStart()
            ->where('users.username', $login)
            ->orWhere('users.email', $login)
            ->groupEnd()
            ->first();

        return $user ?: null;
    }

    public function findForAuthById(string $id): ?array
    {
        $user = $this
            ->select('users.*, companies.name AS company_name, roles.name AS role_name, roles.slug AS role_slug, branches.name AS branch_name, branches.code AS branch_code')
            ->join('companies', 'companies.id = users.company_id', 'left')
            ->join('branches', 'branches.id = users.branch_id', 'left')
            ->join('roles', 'roles.id = users.role_id', 'left')
            ->where('users.id', $id)
            ->first();

        return $user ?: null;
    }
}
