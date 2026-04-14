<?php

namespace App\Controllers\Api\V1;

use App\Models\CompanyModel;
use App\Models\CompanySystemModel;
use App\Models\RoleModel;
use App\Models\SystemModel;
use App\Models\UserModel;
use App\Models\UserSystemModel;

class SystemsController extends BaseApiController
{
    public function index()
    {
        $companyId = $this->resolveCompanyId();

        return $this->success([
            'company_id' => $companyId,
            'catalog' => $this->apiIsSuperadmin() ? (new SystemModel())->orderBy('name', 'ASC')->findAll() : [],
            'assigned_systems' => $this->assignedSystems($companyId),
            'operator_assignments' => $this->canManageSystems() && $companyId ? $this->operatorAssignments($companyId) : [],
        ]);
    }

    public function store()
    {
        if (! $this->apiIsSuperadmin()) {
            return $this->fail('Solo superadmin puede crear sistemas globales.', 403);
        }

        $payload = $this->payload();
        $model = new SystemModel();
        $id = $model->insert($this->systemPayload($payload), true);

        return $this->success($model->find($id), 201);
    }

    public function update(string $id)
    {
        if (! $this->apiIsSuperadmin()) {
            return $this->fail('Solo superadmin puede editar sistemas globales.', 403);
        }

        $model = new SystemModel();
        $row = $model->find($id);

        if (! $row) {
            return $this->fail('Sistema no disponible.', 404);
        }

        $payload = $this->payload();
        $model->update($id, $this->systemPayload($payload, $row));

        return $this->success($model->find($id));
    }

    public function delete(string $id)
    {
        if (! $this->apiIsSuperadmin()) {
            return $this->fail('Solo superadmin puede eliminar sistemas globales.', 403);
        }

        $model = new SystemModel();
        $row = $model->find($id);

        if (! $row) {
            return $this->fail('Sistema no disponible.', 404);
        }

        $model->delete($id);

        return $this->success(['id' => $id, 'deleted' => true]);
    }

    public function toggle(string $id)
    {
        if (! $this->apiIsSuperadmin()) {
            return $this->fail('Solo superadmin puede habilitar o deshabilitar sistemas globales.', 403);
        }

        $model = new SystemModel();
        $row = $model->find($id);

        if (! $row) {
            return $this->fail('Sistema no disponible.', 404);
        }

        $model->update($id, [
            'active' => (int) $row['active'] === 1 ? 0 : 1,
        ]);

        return $this->success($model->find($id));
    }

    public function storeCompanyAssignment()
    {
        if (! $this->apiIsSuperadmin()) {
            return $this->fail('Solo superadmin puede asignar sistemas a empresas.', 403);
        }

        $payload = $this->payload();
        $companyId = trim((string) ($payload['company_id'] ?? ''));
        $systemId = trim((string) ($payload['system_id'] ?? ''));

        if (! $this->isValidCompany($companyId) || ! $this->isValidSystem($systemId)) {
            return $this->fail('Empresa o sistema no valido.', 422);
        }

        $model = new CompanySystemModel();
        $existing = $model->where('company_id', $companyId)->where('system_id', $systemId)->first();

        if ($existing) {
            $active = array_key_exists('active', $payload) ? (int) $payload['active'] : $existing['active'];
            $model->update($existing['id'], [
                'active' => $active,
            ]);
            $this->syncAdminAssignment($companyId, $systemId, $active);

            return $this->success($model->find($existing['id']));
        }

        $active = array_key_exists('active', $payload) ? (int) $payload['active'] : 1;
        $id = $model->insert([
            'company_id' => $companyId,
            'system_id' => $systemId,
            'active' => $active,
        ], true);
        $this->syncAdminAssignment($companyId, $systemId, $active);

        return $this->success($model->find($id), 201);
    }

    public function toggleCompanyAssignment(string $id)
    {
        if (! $this->apiIsSuperadmin()) {
            return $this->fail('Solo superadmin puede cambiar asignaciones de empresa.', 403);
        }

        $model = new CompanySystemModel();
        $row = $model->find($id);

        if (! $row) {
            return $this->fail('Asignacion no disponible.', 404);
        }

        $model->update($id, [
            'active' => (int) $row['active'] === 1 ? 0 : 1,
        ]);
        $this->syncAdminAssignment((string) $row['company_id'], (string) $row['system_id'], (int) $row['active'] === 1 ? 0 : 1);

        return $this->success($model->find($id));
    }

    public function storeUserAssignment()
    {
        if (! $this->canManageSystems()) {
            return $this->fail('No tienes permisos para asignar sistemas a usuarios.', 403);
        }

        $payload = $this->payload();
        $companyId = $this->resolveCompanyIdFromPayload($payload);
        $userId = trim((string) ($payload['user_id'] ?? ''));
        $systemId = trim((string) ($payload['system_id'] ?? ''));
        $accessLevel = trim((string) ($payload['access_level'] ?? 'view'));

        if (! in_array($accessLevel, ['view', 'manage'], true)) {
            return $this->fail('Nivel de acceso no valido.', 422);
        }

        if (! $this->isValidAssignableUser($userId, $companyId)) {
            return $this->fail($this->apiIsSuperadmin()
                ? 'Debes seleccionar un usuario valido de la empresa.'
                : 'Solo puedes asignar sistemas a operadores validos de la empresa.', 422);
        }

        if (! $this->isActiveCompanySystem($companyId, $systemId)) {
            return $this->fail('El sistema debe estar asignado a la empresa antes de asignarlo al operador.', 422);
        }

        $model = new UserSystemModel();
        $existing = $model->where('user_id', $userId)->where('system_id', $systemId)->first();

        if ($existing) {
            $model->update($existing['id'], [
                'company_id' => $companyId,
                'access_level' => $accessLevel,
                'active' => array_key_exists('active', $payload) ? (int) $payload['active'] : $existing['active'],
            ]);

            return $this->success($model->find($existing['id']));
        }

        $id = $model->insert([
            'company_id' => $companyId,
            'user_id' => $userId,
            'system_id' => $systemId,
            'access_level' => $accessLevel,
            'active' => array_key_exists('active', $payload) ? (int) $payload['active'] : 1,
        ], true);

        return $this->success($model->find($id), 201);
    }

    public function toggleUserAssignment(string $id)
    {
        if (! $this->canManageSystems()) {
            return $this->fail('No tienes permisos para cambiar asignaciones de usuario.', 403);
        }

        $model = new UserSystemModel();
        $row = $model->find($id);

        if (! $row || ! $this->canManageCompany((string) $row['company_id'])) {
            return $this->fail('Asignacion no disponible.', 404);
        }

        $model->update($id, [
            'active' => (int) $row['active'] === 1 ? 0 : 1,
        ]);

        return $this->success($model->find($id));
    }

    private function assignedSystems(?string $companyId): array
    {
        if ($this->apiIsSuperadmin()) {
            if (! $companyId) {
                return [];
            }

            return (new CompanySystemModel())
                ->select('company_systems.*, systems.name AS system_name, systems.slug AS system_slug, systems.entry_url, systems.icon')
                ->join('systems', 'systems.id = company_systems.system_id')
                ->where('company_systems.company_id', $companyId)
                ->orderBy('systems.name', 'ASC')
                ->findAll();
        }

        return (new UserSystemModel())
            ->select('user_systems.*, systems.name AS system_name, systems.slug AS system_slug, systems.entry_url, systems.icon')
            ->join('systems', 'systems.id = user_systems.system_id')
            ->join('company_systems', 'company_systems.system_id = systems.id AND company_systems.company_id = user_systems.company_id')
            ->where('user_systems.user_id', $this->apiUser()['id'] ?? '')
            ->where('user_systems.company_id', $companyId)
            ->where('user_systems.active', 1)
            ->where('company_systems.active', 1)
            ->where('systems.active', 1)
            ->orderBy('systems.name', 'ASC')
            ->findAll();
    }

    private function operatorAssignments(string $companyId): array
    {
        return (new UserSystemModel())
            ->select('user_systems.*, users.name AS user_name, users.username, systems.name AS system_name, systems.slug AS system_slug')
            ->join('users', 'users.id = user_systems.user_id')
            ->join('systems', 'systems.id = user_systems.system_id')
            ->join('roles', 'roles.id = users.role_id')
            ->where('user_systems.company_id', $companyId)
            ->where('roles.slug', 'operador')
            ->orderBy('users.name', 'ASC')
            ->orderBy('systems.name', 'ASC')
            ->findAll();
    }

    private function resolveCompanyId(): ?string
    {
        if ($this->apiIsSuperadmin()) {
            $companyId = trim((string) ($this->request->getGet('company_id') ?? ''));

            if ($companyId !== '') {
                return $companyId;
            }

            $company = (new CompanyModel())->orderBy('name', 'ASC')->first();

            return $company['id'] ?? null;
        }

        return $this->apiCompanyId();
    }

    private function resolveCompanyIdFromPayload(array $payload): ?string
    {
        if ($this->apiIsSuperadmin()) {
            $companyId = trim((string) ($payload['company_id'] ?? ''));

            return $companyId !== '' ? $companyId : null;
        }

        return $this->apiCompanyId();
    }

    private function systemPayload(array $payload, array $row = []): array
    {
        return [
            'name' => trim((string) ($payload['name'] ?? $row['name'] ?? '')),
            'slug' => trim((string) ($payload['slug'] ?? $row['slug'] ?? '')),
            'description' => trim((string) ($payload['description'] ?? $row['description'] ?? '')),
            'entry_url' => trim((string) ($payload['entry_url'] ?? $row['entry_url'] ?? '')),
            'icon' => trim((string) ($payload['icon'] ?? $row['icon'] ?? '')),
            'active' => array_key_exists('active', $payload) ? (int) $payload['active'] : ($row['active'] ?? 1),
        ];
    }

    private function canManageSystems(): bool
    {
        if ($this->apiIsSuperadmin()) {
            return true;
        }

        return ($this->apiUser()['role_slug'] ?? null) === 'admin' && ($this->apiUser()['branch_code'] ?? null) === 'MAIN';
    }

    private function canManageCompany(string $companyId): bool
    {
        if ($this->apiIsSuperadmin()) {
            return true;
        }

        return $this->apiCompanyId() === $companyId && $this->canManageSystems();
    }

    private function isValidCompany(string $companyId): bool
    {
        return $companyId !== '' && (new CompanyModel())->find($companyId) !== null;
    }

    private function isValidSystem(string $systemId): bool
    {
        return $systemId !== '' && (new SystemModel())->find($systemId) !== null;
    }

    private function isActiveCompanySystem(?string $companyId, string $systemId): bool
    {
        if (! $companyId || $systemId === '') {
            return false;
        }

        return (new CompanySystemModel())
            ->where('company_id', $companyId)
            ->where('system_id', $systemId)
            ->where('active', 1)
            ->first() !== null;
    }

    private function isValidAssignableUser(string $userId, ?string $companyId): bool
    {
        if (! $companyId || $userId === '') {
            return false;
        }

        $query = (new UserModel())
            ->where('id', $userId)
            ->where('company_id', $companyId)
            ->where('active', 1);

        if ($this->apiIsSuperadmin()) {
            return $query->first() !== null;
        }

        $operatorRole = (new RoleModel())->findBySlug('operador');

        if (! $operatorRole) {
            return false;
        }

        return $query
            ->where('role_id', $operatorRole['id'])
            ->first() !== null;
    }

    private function syncAdminAssignment(string $companyId, string $systemId, int $active): void
    {
        $adminRole = (new RoleModel())->findBySlug('admin');

        if (! $adminRole) {
            return;
        }

        $adminUser = (new UserModel())
            ->where('company_id', $companyId)
            ->where('role_id', $adminRole['id'])
            ->first();

        if (! $adminUser) {
            return;
        }

        $userSystemModel = new UserSystemModel();
        $existing = $userSystemModel
            ->where('user_id', $adminUser['id'])
            ->where('system_id', $systemId)
            ->first();

        if ($existing) {
            $userSystemModel->update($existing['id'], [
                'company_id' => $companyId,
                'access_level' => 'manage',
                'active' => $active,
            ]);

            return;
        }

        $userSystemModel->insert([
            'company_id' => $companyId,
            'user_id' => $adminUser['id'],
            'system_id' => $systemId,
            'access_level' => 'manage',
            'active' => $active,
        ]);
    }
}
