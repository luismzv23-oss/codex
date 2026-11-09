<?php

namespace App\Libraries;

use App\Models\{CompanySystemModel, RoleModel, SystemModel, UserModel, UserSystemModel};
use RuntimeException;

/**
 * Module (system) enablement per company and the matching access of the company administrator.
 * The admin always gets 'manage' on every system enabled for the company, whatever the order in
 * which systems and the admin user were created.
 */
class CompanySystemsService
{
    public function assign(string $companyId, string $systemId, bool $active = true): void
    {
        if (! (new SystemModel())->find($systemId)) {
            throw new RuntimeException('Sistema no disponible.');
        }
        $model = new CompanySystemModel();
        $existing = $model->where('company_id', $companyId)->where('system_id', $systemId)->first();
        if ($existing) {
            $model->update($existing['id'], ['active' => $active ? 1 : 0]);
        } elseif (! $model->insert(['company_id' => $companyId, 'system_id' => $systemId, 'active' => $active ? 1 : 0])) {
            throw new RuntimeException('No se pudo habilitar el sistema.');
        }
        $this->syncAdminSystem($companyId, $systemId, $active ? 1 : 0);
    }

    /** Leaves exactly the given systems active for the company (others are deactivated, never deleted). */
    public function setActive(string $companyId, array $systemIds): void
    {
        $wanted = array_values(array_unique(array_filter(array_map('strval', $systemIds))));
        foreach ($wanted as $systemId) {
            $this->assign($companyId, $systemId, true);
        }
        foreach ((new CompanySystemModel())->where('company_id', $companyId)->findAll() as $row) {
            if (! in_array((string) $row['system_id'], $wanted, true) && (int) $row['active'] === 1) {
                $this->assign($companyId, (string) $row['system_id'], false);
            }
        }
    }

    /** Gives the company admin the access of every system currently assigned to the company. */
    public function syncAdmin(string $companyId): void
    {
        foreach ((new CompanySystemModel())->where('company_id', $companyId)->findAll() as $row) {
            $this->syncAdminSystem($companyId, (string) $row['system_id'], (int) $row['active']);
        }
    }

    public function syncAdminSystem(string $companyId, string $systemId, int $active): void
    {
        $adminRole = (new RoleModel())->findBySlug('admin');
        if (! $adminRole) {
            return;
        }
        $adminUser = (new UserModel())->where('company_id', $companyId)->where('role_id', $adminRole['id'])->first();
        if (! $adminUser) {
            return;
        }
        $userSystemModel = new UserSystemModel();
        $existing = $userSystemModel->where('user_id', $adminUser['id'])->where('system_id', $systemId)->first();
        if ($existing) {
            $userSystemModel->update($existing['id'], ['company_id' => $companyId, 'access_level' => 'manage', 'active' => $active]);

            return;
        }
        $userSystemModel->insert(['company_id' => $companyId, 'user_id' => $adminUser['id'], 'system_id' => $systemId, 'access_level' => 'manage', 'active' => $active]);
    }
}
