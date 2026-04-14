<?php

namespace App\Controllers\Api\V1;

use App\Models\BranchModel;
use App\Models\RoleModel;
use App\Models\UserModel;

class UsersController extends BaseApiController
{
    public function index()
    {
        $builder = (new UserModel())
            ->select('users.*, companies.name AS company_name, branches.name AS branch_name, roles.name AS role_name, roles.slug AS role_slug')
            ->join('companies', 'companies.id = users.company_id', 'left')
            ->join('branches', 'branches.id = users.branch_id', 'left')
            ->join('roles', 'roles.id = users.role_id', 'left')
            ->orderBy('users.name', 'ASC');

        if (! $this->apiIsSuperadmin()) {
            $builder->where('users.company_id', $this->apiCompanyId());
        }

        return $this->success($builder->findAll());
    }

    public function store()
    {
        $payload = $this->payload();
        $model = new UserModel();
        $companyId = $this->apiIsSuperadmin() ? ($payload['company_id'] ?? null) : $this->apiCompanyId();
        $branchId = $this->validBranchId($companyId, (string) ($payload['branch_id'] ?? ''));
        $roleSlug = (string) ($payload['role_slug'] ?? 'operador');

        if (! $companyId || ! $branchId) {
            return $this->fail('El usuario debe estar asignado a una empresa y a una sucursal valida.', 422);
        }

        if ($error = $this->validateAdminAssignment($companyId, $branchId, $roleSlug)) {
            return $this->fail($error, 422);
        }

        $id = $model->insert([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'role_id' => $this->roleId($roleSlug),
            'name' => trim((string) ($payload['name'] ?? '')),
            'username' => trim((string) ($payload['username'] ?? '')),
            'email' => trim((string) ($payload['email'] ?? '')),
            'password_hash' => password_hash((string) ($payload['password'] ?? ''), PASSWORD_DEFAULT),
            'must_change_password' => ! empty($payload['must_change_password']) ? 1 : 0,
            'active' => array_key_exists('active', $payload) ? (int) $payload['active'] : 1,
        ], true);

        return $this->success($model->find($id), 201);
    }

    public function update(string $id)
    {
        $model = new UserModel();
        $row = $model->find($id);

        if (! $row || (! $this->apiIsSuperadmin() && $row['company_id'] !== $this->apiCompanyId())) {
            return $this->fail('Usuario no disponible.', 404);
        }

        $payload = $this->payload();
        $companyId = $this->apiIsSuperadmin() ? ($payload['company_id'] ?? $row['company_id']) : $this->apiCompanyId();
        $branchId = $this->validBranchId($companyId, (string) ($payload['branch_id'] ?? $row['branch_id']));
        $roleSlug = (string) ($payload['role_slug'] ?? ($this->roleSlugById((string) $row['role_id']) ?? 'operador'));

        if (! $companyId || ! $branchId) {
            return $this->fail('El usuario debe estar asignado a una empresa y a una sucursal valida.', 422);
        }

        if ($error = $this->validateAdminAssignment($companyId, $branchId, $roleSlug, $id)) {
            return $this->fail($error, 422);
        }

        $data = [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'role_id' => isset($payload['role_slug']) ? $this->roleId($roleSlug) : $row['role_id'],
            'name' => trim((string) ($payload['name'] ?? $row['name'])),
            'username' => trim((string) ($payload['username'] ?? $row['username'])),
            'email' => trim((string) ($payload['email'] ?? $row['email'])),
            'must_change_password' => array_key_exists('must_change_password', $payload) ? (int) $payload['must_change_password'] : $row['must_change_password'],
            'active' => array_key_exists('active', $payload) ? (int) $payload['active'] : $row['active'],
        ];

        if (! empty($payload['password'])) {
            $data['password_hash'] = password_hash((string) $payload['password'], PASSWORD_DEFAULT);
        }

        $model->update($id, $data);

        return $this->success($model->find($id));
    }

    private function roleId(string $roleSlug): string
    {
        $allowed = $this->apiIsSuperadmin() ? ['superadmin', 'admin', 'operador'] : ['operador'];
        $slug = in_array($roleSlug, $allowed, true) ? $roleSlug : 'operador';
        $role = (new RoleModel())->findBySlug($slug);

        return (string) $role['id'];
    }

    private function roleSlugById(string $roleId): ?string
    {
        $role = (new RoleModel())->find($roleId);

        return $role['slug'] ?? null;
    }

    private function validBranchId(?string $companyId, string $branchId): ?string
    {
        if (! $companyId || trim($branchId) === '') {
            return null;
        }

        $branch = (new BranchModel())
            ->where('company_id', $companyId)
            ->where('id', trim($branchId))
            ->first();

        return $branch['id'] ?? null;
    }

    private function validateAdminAssignment(?string $companyId, ?string $branchId, string $roleSlug, ?string $ignoreUserId = null): ?string
    {
        if ($roleSlug !== 'admin') {
            return null;
        }

        if (! $companyId) {
            return 'El usuario administrador debe estar asignado a una empresa.';
        }

        $branch = $branchId ? (new BranchModel())->find($branchId) : null;

        if (! $branch || $branch['company_id'] !== $companyId || strtoupper((string) $branch['code']) !== 'MAIN') {
            return 'El unico usuario admin de la empresa debe estar asignado a CASA MATRIZ.';
        }

        $adminRole = (new RoleModel())->findBySlug('admin');

        if (! $adminRole) {
            return 'No se encontro el rol admin en el sistema.';
        }

        $existingAdmin = (new UserModel())
            ->where('company_id', $companyId)
            ->where('role_id', $adminRole['id']);

        if ($ignoreUserId) {
            $existingAdmin->where('id !=', $ignoreUserId);
        }

        if ($existingAdmin->first()) {
            return 'Solo puede existir un usuario admin por empresa.';
        }

        return null;
    }
}
