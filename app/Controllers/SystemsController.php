<?php

namespace App\Controllers;

use App\Models\CompanyModel;
use App\Models\CompanySystemModel;
use App\Models\RoleModel;
use App\Models\SystemModel;
use App\Models\UserModel;
use App\Models\UserSystemModel;

class SystemsController extends BaseController
{
    public function index()
    {
        $companyModel = new CompanyModel();
        $selectedCompanyId = $this->resolveSystemsCompanyId();
        $selectedCompany = $selectedCompanyId ? $companyModel->find($selectedCompanyId) : null;

        return view('systems/index', [
            'pageTitle' => 'Sistemas',
            'user' => $this->currentUser(),
            'isSuperadmin' => $this->isSuperadmin(),
            'canManageSystems' => $this->canManageSystems(),
            'companies' => $this->isSuperadmin() ? $companyModel->orderBy('name', 'ASC')->findAll() : [],
            'selectedCompanyId' => $selectedCompanyId,
            'selectedCompany' => $selectedCompany,
            'catalogSystems' => $this->catalogSystems(),
            'accessibleSystems' => $this->accessibleSystems($selectedCompanyId),
            'companyAssignments' => $selectedCompanyId ? $this->companyAssignments($selectedCompanyId) : [],
            'operatorAssignments' => $selectedCompanyId ? $this->operatorAssignments($selectedCompanyId) : [],
        ]);
    }

    public function userDetail(string $userId)
    {
        if (! $this->canManageSystems()) {
            return redirect()->to('/sistemas')->with('error', 'No tienes permisos para ver el detalle del usuario.');
        }

        $companyId = $this->resolveSystemsCompanyId();
        $user = (new UserModel())
            ->select('users.*, roles.slug AS role_slug, roles.name AS role_name')
            ->join('roles', 'roles.id = users.role_id')
            ->where('users.id', $userId)
            ->where('users.company_id', $companyId)
            ->first();

        if (! $user) {
            return redirect()->to('/sistemas')->with('error', 'Usuario no disponible.');
        }

        $assignments = (new UserSystemModel())
            ->select('user_systems.*, systems.name AS system_name, systems.slug AS system_slug, systems.description')
            ->join('systems', 'systems.id = user_systems.system_id')
            ->where('user_systems.company_id', $companyId)
            ->where('user_systems.user_id', $userId)
            ->orderBy('systems.name', 'ASC')
            ->findAll();

        return view('systems/forms/user_detail', [
            'pageTitle' => 'Detalle de permisos',
            'userDetail' => $user,
            'assignments' => $assignments,
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function toggleUserPermissions(string $userId)
    {
        if (! $this->canManageSystems()) {
            return redirect()->to('/sistemas')->with('error', 'No tienes permisos para cambiar permisos del usuario.');
        }

        $companyId = $this->resolveSystemsCompanyId();
        if (! $companyId || ! $this->canManageCompanyAssignments($companyId)) {
            return redirect()->to('/sistemas')->with('error', 'Empresa no disponible.');
        }

        $user = (new UserModel())->where('id', $userId)->where('company_id', $companyId)->first();
        if (! $user) {
            return redirect()->to('/sistemas')->with('error', 'Usuario no disponible.');
        }

        $assignments = (new UserSystemModel())
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->findAll();

        if ($assignments === []) {
            return redirect()->to('/sistemas?company_id=' . $companyId)->with('error', 'El usuario no tiene sistemas asignados.');
        }

        $hasActive = count(array_filter($assignments, static fn(array $row): bool => (int) ($row['active'] ?? 0) === 1)) > 0;
        $nextActive = $hasActive ? 0 : 1;

        $model = new UserSystemModel();
        foreach ($assignments as $assignment) {
            $model->update($assignment['id'], ['active' => $nextActive]);
        }

        return redirect()->to('/sistemas?company_id=' . $companyId)->with('message', $nextActive === 1 ? 'Permisos del usuario habilitados.' : 'Permisos del usuario deshabilitados.');
    }

    public function create()
    {
        if (! $this->isSuperadmin()) {
            return redirect()->to('/sistemas')->with('error', 'Solo superadmin puede crear sistemas globales.');
        }

        return view('systems/forms/system', [
            'pageTitle' => 'Nuevo sistema',
            'system' => null,
            'formAction' => site_url('sistemas'),
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function store()
    {
        if (! $this->isSuperadmin()) {
            return redirect()->to('/sistemas')->with('error', 'Solo superadmin puede crear sistemas globales.');
        }

        if (! $this->validate($this->systemRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        (new SystemModel())->insert($this->systemPayload());

        return $this->popupOrRedirect('/sistemas', 'Sistema creado correctamente.');
    }

    public function edit(string $id)
    {
        if (! $this->isSuperadmin()) {
            return redirect()->to('/sistemas')->with('error', 'Solo superadmin puede editar sistemas globales.');
        }

        $system = (new SystemModel())->find($id);

        if (! $system) {
            return redirect()->to('/sistemas')->with('error', 'Sistema no disponible.');
        }

        return view('systems/forms/system', [
            'pageTitle' => 'Editar sistema',
            'system' => $system,
            'formAction' => site_url('sistemas/' . $id . '/actualizar'),
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function update(string $id)
    {
        if (! $this->isSuperadmin()) {
            return redirect()->to('/sistemas')->with('error', 'Solo superadmin puede editar sistemas globales.');
        }

        $system = (new SystemModel())->find($id);

        if (! $system) {
            return redirect()->to('/sistemas')->with('error', 'Sistema no disponible.');
        }

        if (! $this->validate($this->systemRules($id))) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        (new SystemModel())->update($id, $this->systemPayload());

        return $this->popupOrRedirect('/sistemas', 'Sistema actualizado correctamente.');
    }

    public function delete(string $id)
    {
        if (! $this->isSuperadmin()) {
            return redirect()->to('/sistemas')->with('error', 'Solo superadmin puede eliminar sistemas globales.');
        }

        $system = (new SystemModel())->find($id);

        if (! $system) {
            return redirect()->to('/sistemas')->with('error', 'Sistema no disponible.');
        }

        (new SystemModel())->delete($id);

        return redirect()->to('/sistemas')->with('message', 'Sistema eliminado correctamente.');
    }

    public function toggle(string $id)
    {
        if (! $this->isSuperadmin()) {
            return redirect()->to('/sistemas')->with('error', 'Solo superadmin puede habilitar o deshabilitar sistemas globales.');
        }

        $system = (new SystemModel())->find($id);

        if (! $system) {
            return redirect()->to('/sistemas')->with('error', 'Sistema no disponible.');
        }

        (new SystemModel())->update($id, [
            'active' => (int) $system['active'] === 1 ? 0 : 1,
        ]);

        return redirect()->to('/sistemas')->with('message', (int) $system['active'] === 1 ? 'Sistema deshabilitado correctamente.' : 'Sistema habilitado correctamente.');
    }

    public function createCompanyAssignmentForm()
    {
        if (! $this->isSuperadmin()) {
            return redirect()->to('/sistemas')->with('error', 'Solo superadmin puede asignar sistemas a empresas.');
        }

        return view('systems/forms/company_assignment', [
            'pageTitle' => 'Asignar sistema a empresa',
            'companies' => (new CompanyModel())->where('active', 1)->orderBy('name', 'ASC')->findAll(),
            'systems' => (new SystemModel())->where('active', 1)->orderBy('name', 'ASC')->findAll(),
            'selectedCompanyId' => $this->resolveSystemsCompanyId(),
            'formAction' => site_url('sistemas/asignaciones-empresa'),
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function storeCompanyAssignment()
    {
        if (! $this->isSuperadmin()) {
            return redirect()->to('/sistemas')->with('error', 'Solo superadmin puede asignar sistemas a empresas.');
        }

        $companyId = trim((string) $this->request->getPost('company_id'));
        $systemId = trim((string) $this->request->getPost('system_id'));

        if (! $this->isValidCompany($companyId) || ! $this->isValidSystem($systemId)) {
            return redirect()->back()->withInput()->with('error', 'Debes seleccionar una empresa y un sistema validos.');
        }

        $model = new CompanySystemModel();
        $existing = $model->where('company_id', $companyId)->where('system_id', $systemId)->first();

        if ($existing) {
            $active = $this->request->getPost('active') === '0' ? 0 : 1;
            $model->update($existing['id'], [
                'active' => $active,
            ]);
            $this->syncAdminAssignment($companyId, $systemId, $active);
        } else {
            $active = $this->request->getPost('active') === '0' ? 0 : 1;
            $model->insert([
                'company_id' => $companyId,
                'system_id' => $systemId,
                'active' => $active,
            ]);
            $this->syncAdminAssignment($companyId, $systemId, $active);
        }

        return $this->popupOrRedirect('/sistemas?company_id=' . $companyId, 'Sistema asignado a la empresa correctamente.');
    }

    public function toggleCompanyAssignment(string $id)
    {
        if (! $this->isSuperadmin()) {
            return redirect()->to('/sistemas')->with('error', 'Solo superadmin puede cambiar asignaciones de empresa.');
        }

        $assignment = (new CompanySystemModel())->find($id);

        if (! $assignment) {
            return redirect()->to('/sistemas')->with('error', 'Asignacion no disponible.');
        }

        (new CompanySystemModel())->update($id, [
            'active' => (int) $assignment['active'] === 1 ? 0 : 1,
        ]);
        $this->syncAdminAssignment((string) $assignment['company_id'], (string) $assignment['system_id'], (int) $assignment['active'] === 1 ? 0 : 1);

        return redirect()->to('/sistemas?company_id=' . $assignment['company_id'])->with('message', 'Estado de asignacion actualizado correctamente.');
    }

    public function createUserAssignmentForm()
    {
        if (! $this->canManageSystems()) {
            return redirect()->to('/sistemas')->with('error', 'No tienes permisos para asignar sistemas a usuarios.');
        }

        $companyId = $this->resolveSystemsCompanyId();

        return view('systems/forms/user_assignment', [
            'pageTitle' => 'Asignar sistema a operador',
            'users' => $this->assignableUsersForCompany($companyId),
            'userLabel' => $this->isSuperadmin() ? 'Usuario' : 'Operador',
            'systems' => $this->activeCompanySystems($companyId),
            'selectedCompanyId' => $companyId,
            'selectedCompany' => $companyId ? (new CompanyModel())->find($companyId) : null,
            'formAction' => site_url('sistemas/asignaciones-usuario'),
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function storeUserAssignment()
    {
        if (! $this->canManageSystems()) {
            return redirect()->to('/sistemas')->with('error', 'No tienes permisos para asignar sistemas a usuarios.');
        }

        $companyId = $this->resolvePostedCompanyId();
        $userId = trim((string) $this->request->getPost('user_id'));
        $systemId = trim((string) $this->request->getPost('system_id'));
        $accessLevel = trim((string) $this->request->getPost('access_level'));

        if (! in_array($accessLevel, ['view', 'manage'], true)) {
            return redirect()->back()->withInput()->with('error', 'Nivel de acceso no valido.');
        }

        if (! $this->isValidAssignableUser($userId, $companyId)) {
            return redirect()->back()->withInput()->with('error', $this->isSuperadmin()
                ? 'Debes seleccionar un usuario valido de la empresa.'
                : 'Solo puedes asignar sistemas a usuarios operadores de la empresa seleccionada.');
        }

        if (! $this->isActiveCompanySystem($companyId, $systemId)) {
            return redirect()->back()->withInput()->with('error', 'El sistema debe estar asignado a la empresa antes de asignarlo al operador.');
        }

        $model = new UserSystemModel();
        $existing = $model->where('user_id', $userId)->where('system_id', $systemId)->first();

        if ($existing) {
            $model->update($existing['id'], [
                'company_id' => $companyId,
                'access_level' => $accessLevel,
                'active' => $this->request->getPost('active') === '0' ? 0 : 1,
            ]);
        } else {
            $model->insert([
                'company_id' => $companyId,
                'user_id' => $userId,
                'system_id' => $systemId,
                'access_level' => $accessLevel,
                'active' => $this->request->getPost('active') === '0' ? 0 : 1,
            ]);
        }

        return $this->popupOrRedirect('/sistemas?company_id=' . $companyId, 'Permiso del operador actualizado correctamente.');
    }

    public function toggleUserAssignment(string $id)
    {
        if (! $this->canManageSystems()) {
            return redirect()->to('/sistemas')->with('error', 'No tienes permisos para cambiar asignaciones de usuario.');
        }

        $assignment = (new UserSystemModel())->find($id);

        if (! $assignment || ! $this->canManageCompanyAssignments((string) $assignment['company_id'])) {
            return redirect()->to('/sistemas')->with('error', 'Asignacion no disponible.');
        }

        (new UserSystemModel())->update($id, [
            'active' => (int) $assignment['active'] === 1 ? 0 : 1,
        ]);

        return redirect()->to('/sistemas?company_id=' . $assignment['company_id'])->with('message', 'Estado del permiso actualizado correctamente.');
    }

    private function catalogSystems(): array
    {
        return (new SystemModel())->orderBy('name', 'ASC')->findAll();
    }

    private function accessibleSystems(?string $companyId): array
    {
        if ($this->isSuperadmin()) {
            $rows = (new SystemModel())->orderBy('name', 'ASC')->findAll();

            return array_map(fn(array $system): array => [
                'id' => $system['id'],
                'name' => $system['name'],
                'slug' => $system['slug'],
                'description' => $system['description'],
                'entry_url' => $this->systemEntryUrl($system['entry_url']),
                'icon' => $system['icon'] ?: 'bi-grid',
                'access_level' => 'manage',
                'active' => (int) ($system['active'] ?? 1),
            ], $rows);
        }

        $rows = (new UserSystemModel())
            ->select('systems.id, systems.name, systems.slug, systems.description, systems.entry_url, systems.icon, user_systems.access_level')
            ->join('systems', 'systems.id = user_systems.system_id')
            ->join('company_systems', 'company_systems.system_id = systems.id AND company_systems.company_id = user_systems.company_id')
            ->where('user_systems.user_id', $this->currentUser()['id'] ?? '')
            ->where('user_systems.company_id', $companyId)
            ->where('user_systems.active', 1)
            ->where('company_systems.active', 1)
            ->where('systems.active', 1)
            ->orderBy('systems.name', 'ASC')
            ->findAll();

        return array_map(fn(array $row): array => [
            'id' => $row['id'],
            'name' => $row['name'],
            'slug' => $row['slug'],
            'description' => $row['description'],
            'entry_url' => $this->systemEntryUrl($row['entry_url']),
            'icon' => $row['icon'] ?: 'bi-grid',
            'access_level' => $row['access_level'],
            'active' => 1,
        ], $rows);
    }

    private function companyAssignments(string $companyId): array
    {
        return (new CompanySystemModel())
            ->select('company_systems.*, systems.name AS system_name, systems.slug AS system_slug, systems.entry_url, systems.icon, systems.description')
            ->join('systems', 'systems.id = company_systems.system_id')
            ->where('company_systems.company_id', $companyId)
            ->orderBy('systems.name', 'ASC')
            ->findAll();
    }

    private function operatorAssignments(string $companyId): array
    {
        $rows = (new UserModel())
            ->select('users.id AS user_id, users.name AS user_name, users.username, roles.slug AS role_slug, user_systems.id, user_systems.access_level, user_systems.active, systems.name AS system_name, systems.slug AS system_slug')
            ->join('roles', 'roles.id = users.role_id')
            ->join('user_systems', 'user_systems.user_id = users.id AND user_systems.company_id = users.company_id', 'left')
            ->join('systems', 'systems.id = user_systems.system_id', 'left')
            ->where('users.company_id', $companyId)
            ->whereIn('roles.slug', ['admin', 'operador'])
            ->orderBy('users.name', 'ASC')
            ->orderBy('systems.name', 'ASC', false)
            ->findAll();

        $grouped = [];
        foreach ($rows as $row) {
            $userId = $row['user_id'];
            if (! isset($grouped[$userId])) {
                $grouped[$userId] = [
                    'user_id' => $userId,
                    'user_name' => $row['user_name'],
                    'username' => $row['username'],
                    'role_slug' => $row['role_slug'],
                    'systems_count' => 0,
                    'active_systems_count' => 0,
                    'access_summary' => '-',
                    'status_label' => '-',
                    'toggle_assignment_id' => null,
                    'has_assignments' => false,
                ];
            }

            if (! empty($row['system_name'])) {
                $grouped[$userId]['systems_count']++;
                $grouped[$userId]['has_assignments'] = true;
                $grouped[$userId]['toggle_assignment_id'] = $grouped[$userId]['toggle_assignment_id'] ?? $row['id'];
                if ((int) ($row['active'] ?? 0) === 1) {
                    $grouped[$userId]['active_systems_count']++;
                }
                if (($row['access_level'] ?? 'view') === 'manage') {
                    $grouped[$userId]['access_summary'] = 'Gestion';
                } elseif ($grouped[$userId]['access_summary'] !== 'Gestion') {
                    $grouped[$userId]['access_summary'] = 'Consulta';
                }
            }
        }

        foreach ($grouped as &$item) {
            if ($item['systems_count'] > 0) {
                $item['status_label'] = $item['active_systems_count'] > 0 ? 'Activo' : 'Inactivo';
            }
        }
        unset($item);

        return array_values($grouped);
    }

    private function activeCompanySystems(?string $companyId): array
    {
        if (! $companyId) {
            return [];
        }

        return (new CompanySystemModel())
            ->select('systems.id, systems.name, systems.slug, systems.description, systems.entry_url, systems.icon')
            ->join('systems', 'systems.id = company_systems.system_id')
            ->where('company_systems.company_id', $companyId)
            ->where('company_systems.active', 1)
            ->where('systems.active', 1)
            ->orderBy('systems.name', 'ASC')
            ->findAll();
    }

    private function assignableUsersForCompany(?string $companyId): array
    {
        if (! $companyId) {
            return [];
        }

        $query = (new UserModel())
            ->where('company_id', $companyId)
            ->where('active', 1)
            ->orderBy('name', 'ASC');

        if ($this->isSuperadmin()) {
            return $query->findAll();
        }

        $operatorRole = (new RoleModel())->findBySlug('operador');

        if (! $operatorRole) {
            return [];
        }

        return $query
            ->where('role_id', $operatorRole['id'])
            ->findAll();
    }

    private function resolveSystemsCompanyId(): ?string
    {
        if ($this->isSuperadmin()) {
            $companyId = trim((string) $this->request->getGet('company_id'));

            if ($companyId !== '') {
                return $companyId;
            }

            $company = (new CompanyModel())->orderBy('name', 'ASC')->first();

            return $company['id'] ?? null;
        }

        return $this->companyId();
    }

    private function resolvePostedCompanyId(): ?string
    {
        if ($this->isSuperadmin()) {
            $companyId = trim((string) $this->request->getPost('company_id'));

            return $companyId !== '' ? $companyId : null;
        }

        return $this->companyId();
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
        if ($userId === '' || ! $companyId) {
            return false;
        }

        $query = (new UserModel())
            ->where('id', $userId)
            ->where('company_id', $companyId)
            ->where('active', 1);

        if ($this->isSuperadmin()) {
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

    private function canManageCompanyAssignments(string $companyId): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }

        return $this->companyId() === $companyId && $this->canManageSystems();
    }

    private function systemRules(?string $ignoreId = null): array
    {
        $slugRule = 'required|min_length[3]|max_length[80]|alpha_dash|is_unique[systems.slug]';

        if ($ignoreId) {
            $slugRule = 'required|min_length[3]|max_length[80]|alpha_dash|is_unique[systems.slug,id,' . $ignoreId . ']';
        }

        return [
            'name' => 'required|min_length[3]|max_length[120]',
            'slug' => $slugRule,
            'entry_url' => 'permit_empty|max_length[255]',
            'icon' => 'permit_empty|max_length[60]',
            'active' => 'permit_empty|in_list[0,1]',
        ];
    }

    private function systemPayload(): array
    {
        return [
            'name' => trim((string) $this->request->getPost('name')),
            'slug' => trim((string) $this->request->getPost('slug')),
            'description' => trim((string) $this->request->getPost('description')),
            'entry_url' => trim((string) $this->request->getPost('entry_url')),
            'icon' => trim((string) $this->request->getPost('icon')),
            'active' => $this->request->getPost('active') === '0' ? 0 : 1,
        ];
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
