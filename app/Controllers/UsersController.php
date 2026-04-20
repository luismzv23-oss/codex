<?php

namespace App\Controllers;

use App\Models\BranchModel;
use App\Models\CompanyModel;
use App\Models\RoleModel;
use App\Models\UserModel;

class UsersController extends BaseController
{
    public function index()
    {
        $model = new UserModel();
        $builder = $model
            ->select('users.*, companies.name AS company_name, branches.name AS branch_name, roles.name AS role_name, roles.slug AS role_slug')
            ->join('companies', 'companies.id = users.company_id', 'left')
            ->join('branches', 'branches.id = users.branch_id', 'left')
            ->join('roles', 'roles.id = users.role_id', 'left')
            ->orderBy('users.name', 'ASC');

        if (! $this->isSuperadmin()) {
            $builder->where('users.company_id', $this->companyId());
        }

        return view('users/index', [
            'pageTitle' => 'Usuarios',
            'users' => $builder->findAll(),
            'canManageUsers' => $this->canMutateUsers(),
            'canDisableOrDeleteUsers' => $this->canDisableOrDeleteUsers(),
        ]);
    }

    public function create()
    {
        if (! $this->canMutateUsers()) {
            return redirect()->to('/usuarios')->with('error', 'No tienes permisos para crear usuarios.');
        }

        return view('users/form', $this->formData([
            'pageTitle' => 'Nuevo usuario',
            'userRow' => null,
            'formAction' => site_url('usuarios'),
            'isPopup' => $this->isPopupRequest(),
        ]));
    }

    public function store()
    {
        if (! $this->canMutateUsers()) {
            return redirect()->to('/usuarios')->with('error', 'No tienes permisos para crear usuarios.');
        }

        $rules = $this->rules();
        $rules['password'] = 'required|min_length[8]|max_length[255]|strong_password|not_common_password';

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $companyId = $this->resolveCompanyId((string) $this->request->getPost('company_id'));
        $branchId = $this->validateBranchForCompany($companyId, (string) $this->request->getPost('branch_id'));
        $roleSlug = (string) $this->request->getPost('role_slug');

        if ($branchId === false) {
            return redirect()->back()->withInput()->with('error', 'Debes seleccionar una sucursal valida para la empresa elegida.');
        }

        if ($error = $this->validateAdminAssignment($companyId, $branchId, $roleSlug)) {
            return redirect()->back()->withInput()->with('error', $error);
        }

        $model = new UserModel();
        $model->insert([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'role_id' => $this->resolveRoleId($roleSlug),
            'name' => trim((string) $this->request->getPost('name')),
            'username' => trim((string) $this->request->getPost('username')),
            'email' => trim((string) $this->request->getPost('email')),
            'password_hash' => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
            'must_change_password' => $this->request->getPost('must_change_password') === '1' ? 1 : 0,
            'active' => $this->request->getPost('active') === '0' ? 0 : 1,
        ]);

        return $this->popupOrRedirect('/usuarios', 'Usuario creado correctamente.');
    }

    public function edit(string $id)
    {
        if (! $this->canMutateUsers()) {
            return redirect()->to('/usuarios')->with('error', 'No tienes permisos para editar usuarios.');
        }

        $userRow = $this->findManagedUser($id);

        if (! $userRow) {
            return redirect()->to('/usuarios')->with('error', 'Usuario no disponible.');
        }

        return view('users/form', $this->formData([
            'pageTitle' => 'Editar usuario',
            'userRow' => $userRow,
            'formAction' => site_url('usuarios/' . $id . '/actualizar'),
            'isPopup' => $this->isPopupRequest(),
        ]));
    }

    public function update(string $id)
    {
        if (! $this->canMutateUsers()) {
            return redirect()->to('/usuarios')->with('error', 'No tienes permisos para editar usuarios.');
        }

        $userRow = $this->findManagedUser($id);

        if (! $userRow) {
            return redirect()->to('/usuarios')->with('error', 'Usuario no disponible.');
        }

        $rules = $this->rules($id);

        if ((string) $this->request->getPost('password') !== '') {
            $rules['password'] = 'min_length[8]|max_length[255]|strong_password|not_common_password';
        }

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $companyId = $this->resolveCompanyId((string) $this->request->getPost('company_id'));
        $branchId = $this->validateBranchForCompany($companyId, (string) $this->request->getPost('branch_id'));
        $roleSlug = (string) $this->request->getPost('role_slug');

        if ($branchId === false) {
            return redirect()->back()->withInput()->with('error', 'Debes seleccionar una sucursal valida para la empresa elegida.');
        }

        if ($error = $this->validateAdminAssignment($companyId, $branchId, $roleSlug, $id)) {
            return redirect()->back()->withInput()->with('error', $error);
        }

        $data = [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'role_id' => $this->resolveRoleId($roleSlug),
            'name' => trim((string) $this->request->getPost('name')),
            'username' => trim((string) $this->request->getPost('username')),
            'email' => trim((string) $this->request->getPost('email')),
            'must_change_password' => $this->request->getPost('must_change_password') === '1' ? 1 : 0,
            'active' => $this->request->getPost('active') === '0' ? 0 : 1,
        ];

        if ((string) $this->request->getPost('password') !== '') {
            $data['password_hash'] = password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT);
        }

        (new UserModel())->update($id, $data);

        if ($id === ($this->currentUser()['id'] ?? null)) {
            auth()->storeUser($id);
        }

        return $this->popupOrRedirect('/usuarios', 'Usuario actualizado correctamente.');
    }

    public function toggle(string $id)
    {
        if (! $this->canDisableOrDeleteUsers()) {
            return redirect()->to('/usuarios')->with('error', 'No tienes permisos para deshabilitar usuarios.');
        }

        $userRow = $this->findManagedUser($id);

        if (! $userRow || $userRow['id'] === ($this->currentUser()['id'] ?? null)) {
            return redirect()->to('/usuarios')->with('error', 'No se puede modificar el usuario seleccionado.');
        }

        (new UserModel())->update($id, [
            'active' => (int) $userRow['active'] === 1 ? 0 : 1,
        ]);

        return redirect()->to('/usuarios')->with('message', 'Estado del usuario actualizado correctamente.');
    }

    public function delete(string $id)
    {
        if (! $this->canDisableOrDeleteUsers()) {
            return redirect()->to('/usuarios')->with('error', 'No tienes permisos para eliminar usuarios.');
        }

        $userRow = $this->findManagedUser($id);

        if (! $userRow || $userRow['id'] === ($this->currentUser()['id'] ?? null)) {
            return redirect()->to('/usuarios')->with('error', 'No se puede eliminar el usuario seleccionado.');
        }

        (new UserModel())->delete($id);

        return redirect()->to('/usuarios')->with('message', 'Usuario eliminado correctamente.');
    }

    private function formData(array $overrides): array
    {
        $companyModel = new CompanyModel();
        $roleModel = new RoleModel();
        $branchModel = new BranchModel();
        $userRow = $overrides['userRow'] ?? null;

        $companies = $this->isSuperadmin()
            ? $companyModel->orderBy('name', 'ASC')->findAll()
            : $companyModel->where('id', $this->companyId())->findAll();

        foreach ($companies as $company) {
            $branchModel->ensureMainBranch($company['id']);
        }

        $roleSlugs = $this->isSuperadmin() ? ['superadmin', 'admin', 'operador'] : ['operador'];

        if (! $this->isSuperadmin() && ($userRow['role_slug'] ?? null) === 'admin') {
            $roleSlugs = ['admin', 'operador'];
        }

        return array_merge([
            'companies' => $companies,
            'branches' => $branchModel->orderBy('name', 'ASC')->findAll(),
            'branchesByCompany' => $this->groupBranchesByCompany($branchModel->orderBy('name', 'ASC')->findAll()),
            'roles' => $roleModel->whereIn('slug', $roleSlugs)->findAll(),
        ], $overrides);
    }

    private function rules(?string $ignoreId = null): array
    {
        $usernameUnique = 'required|min_length[3]|max_length[50]|is_unique[users.username]';
        $emailUnique = 'required|valid_email|max_length[150]|is_unique[users.email]';

        if ($ignoreId) {
            $usernameUnique = 'required|min_length[3]|max_length[50]|is_unique[users.username,id,' . $ignoreId . ']';
            $emailUnique = 'required|valid_email|max_length[150]|is_unique[users.email,id,' . $ignoreId . ']';
        }

        return [
            'name' => 'required|min_length[3]|max_length[150]',
            'username' => $usernameUnique,
            'email' => $emailUnique,
            'company_id' => 'required|max_length[36]',
            'branch_id' => 'required|max_length[36]',
            'role_slug' => 'required|in_list[superadmin,admin,operador]',
            'active' => 'permit_empty|in_list[0,1]',
            'must_change_password' => 'permit_empty|in_list[0,1]',
        ];
    }

    private function resolveCompanyId(string $companyId): ?string
    {
        return $this->isSuperadmin() ? $this->nullIfEmpty($companyId) : $this->companyId();
    }

    private function resolveRoleId(string $roleSlug): string
    {
        $allowed = $this->isSuperadmin() ? ['superadmin', 'admin', 'operador'] : ['admin', 'operador'];
        $slug = in_array($roleSlug, $allowed, true) ? $roleSlug : end($allowed);
        $role = (new RoleModel())->findBySlug($slug);

        return (string) $role['id'];
    }

    private function findManagedUser(string $id): ?array
    {
        $row = (new UserModel())->find($id);

        if (! $row) {
            return null;
        }

        if ($this->isSuperadmin()) {
            return $row;
        }

        return $row['company_id'] === $this->companyId() ? $row : null;
    }

    private function nullIfEmpty(string $value): ?string
    {
        return trim($value) !== '' ? trim($value) : null;
    }

    private function validateBranchForCompany(?string $companyId, string $branchId)
    {
        if ($companyId === null || trim($branchId) === '') {
            return false;
        }

        $branch = (new BranchModel())
            ->where('id', trim($branchId))
            ->where('company_id', $companyId)
            ->first();

        return $branch ? $branch['id'] : false;
    }

    private function validateAdminAssignment(?string $companyId, string $branchId, string $roleSlug, ?string $ignoreUserId = null): ?string
    {
        if ($roleSlug !== 'admin') {
            return null;
        }

        if (! $companyId) {
            return 'El usuario administrador debe estar asignado a una empresa.';
        }

        $branch = (new BranchModel())->find($branchId);

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

    private function groupBranchesByCompany(array $branches): array
    {
        $grouped = [];

        foreach ($branches as $branch) {
            $grouped[$branch['company_id']][] = [
                'id' => $branch['id'],
                'name' => $branch['name'],
                'code' => $branch['code'],
            ];
        }

        return $grouped;
    }
}
