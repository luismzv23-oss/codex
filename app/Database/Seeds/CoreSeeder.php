<?php

namespace App\Database\Seeds;

use App\Models\BranchModel;
use App\Models\CompanyModel;
use App\Models\CompanySystemModel;
use App\Models\CurrencyModel;
use App\Models\PermissionModel;
use App\Models\RoleModel;
use App\Models\RolePermissionModel;
use App\Models\SalesAgentModel;
use App\Models\SalesConditionModel;
use App\Models\SalesZoneModel;
use App\Models\SystemModel;
use App\Models\TaxModel;
use App\Models\UserModel;
use App\Models\UserSystemModel;
use App\Models\VoucherSequenceModel;
use CodeIgniter\Database\Seeder;

class CoreSeeder extends Seeder
{
    public function run()
    {
        $permissionIds = $this->seedPermissions();
        $roleIds = $this->seedRoles($permissionIds);
        [$companyId, $branchId] = $this->seedCompanyAndBranch();
        $this->seedCurrencyAndTaxes($companyId);
        $this->seedSalesDefaults($companyId, $branchId);
        $systemIds = $this->seedSystems($companyId);
        $this->seedDemoUsers($companyId, $branchId, $roleIds, $systemIds);
    }

    private function seedPermissions(): array
    {
        $permissionModel = new PermissionModel();

        $permissions = [
            ['module' => 'dashboard', 'name' => 'Ver dashboard', 'slug' => 'dashboard.view'],
            ['module' => 'users', 'name' => 'Ver usuarios', 'slug' => 'users.view'],
            ['module' => 'users', 'name' => 'Gestionar usuarios', 'slug' => 'users.manage'],
            ['module' => 'companies', 'name' => 'Ver empresas', 'slug' => 'companies.view'],
            ['module' => 'companies', 'name' => 'Gestionar empresas', 'slug' => 'companies.manage'],
            ['module' => 'systems', 'name' => 'Ver sistemas', 'slug' => 'systems.view'],
            ['module' => 'systems', 'name' => 'Gestionar sistemas', 'slug' => 'systems.manage'],
            ['module' => 'settings', 'name' => 'Ver configuracion', 'slug' => 'settings.view'],
            ['module' => 'settings', 'name' => 'Gestionar configuracion', 'slug' => 'settings.manage'],
            ['module' => 'branches', 'name' => 'Gestionar sucursales', 'slug' => 'branches.manage'],
            ['module' => 'taxes', 'name' => 'Gestionar impuestos', 'slug' => 'taxes.manage'],
            ['module' => 'currencies', 'name' => 'Gestionar monedas', 'slug' => 'currencies.manage'],
            ['module' => 'voucher_sequences', 'name' => 'Gestionar numeraciones', 'slug' => 'voucher_sequences.manage'],
        ];

        $permissionIds = [];

        foreach ($permissions as $permission) {
            $existing = $permissionModel->where('slug', $permission['slug'])->first();

            if ($existing) {
                $permissionIds[$permission['slug']] = $existing['id'];
                continue;
            }

            $permissionIds[$permission['slug']] = $permissionModel->insert($permission, true);
        }

        return $permissionIds;
    }

    private function seedRoles(array $permissionIds): array
    {
        $roleModel = new RoleModel();
        $rolePermissionModel = new RolePermissionModel();

        $roles = [
            'superadmin' => [
                'name' => 'Superadministrador',
                'description' => 'Administra todo el sistema y todas las empresas.',
                'permissions' => array_keys($permissionIds),
            ],
            'admin' => [
                'name' => 'Administrador',
                'description' => 'Administra la empresa asignada.',
                'permissions' => [
                    'dashboard.view',
                    'systems.view',
                    'systems.manage',
                    'users.view',
                    'users.manage',
                    'companies.view',
                    'settings.view',
                    'settings.manage',
                    'branches.manage',
                    'taxes.manage',
                    'currencies.manage',
                    'voucher_sequences.manage',
                ],
            ],
            'operador' => [
                'name' => 'Operador',
                'description' => 'Opera dentro de la empresa asignada.',
                'permissions' => [
                    'dashboard.view',
                    'systems.view',
                    'settings.view',
                ],
            ],
        ];

        $roleIds = [];

        foreach ($roles as $slug => $role) {
            $existing = $roleModel->where('slug', $slug)->first();

            if ($existing) {
                $roleIds[$slug] = $existing['id'];
            } else {
                $roleIds[$slug] = $roleModel->insert([
                    'name' => $role['name'],
                    'slug' => $slug,
                    'description' => $role['description'],
                    'is_system' => 1,
                ], true);
            }

            foreach ($role['permissions'] as $permissionSlug) {
                $exists = $rolePermissionModel
                    ->where('role_id', $roleIds[$slug])
                    ->where('permission_id', $permissionIds[$permissionSlug])
                    ->first();

                if (! $exists) {
                    $rolePermissionModel->insert([
                        'role_id' => $roleIds[$slug],
                        'permission_id' => $permissionIds[$permissionSlug],
                    ]);
                }
            }
        }

        return $roleIds;
    }

    /**
     * @return array{0: string, 1: string} [$companyId, $branchId]
     */
    private function seedCompanyAndBranch(): array
    {
        $companyModel = new CompanyModel();
        $branchModel = new BranchModel();

        $company = $companyModel->where('name', 'Empresa Demo')->first();
        $companyId = $company['id'] ?? $companyModel->insert([
            'name' => 'Empresa Demo',
            'legal_name' => 'Empresa Demo S.A.',
            'tax_id' => 'J-00000000-0',
            'email' => 'demo@codex.local',
            'phone' => '+58 000 0000000',
            'address' => 'Direccion principal demo',
            'currency_code' => 'ARS',
            'active' => 1,
        ], true);

        $branch = $branchModel->where('company_id', $companyId)->where('code', 'MAIN')->first();
        $branchId = $branch['id'] ?? $branchModel->insert([
            'company_id' => $companyId,
            'name' => 'Casa Matriz',
            'code' => 'MAIN',
            'address' => 'Sucursal principal',
            'phone' => '+58 000 0000000',
            'active' => 1,
        ], true);

        return [$companyId, $branchId];
    }

    private function seedCurrencyAndTaxes(string $companyId): void
    {
        $currencyModel = new CurrencyModel();
        $taxModel = new TaxModel();

        if (! $currencyModel->where('company_id', $companyId)->where('code', 'ARS')->first()) {
            $currencyModel->insert([
                'company_id' => $companyId,
                'code' => 'ARS',
                'name' => 'Pesos Argentinos',
                'symbol' => 'ARS',
                'exchange_rate' => '1.0000',
                'is_default' => 1,
                'active' => 1,
            ]);
        }

        // ── Argentine IVA aliquots (AFIP codes) ──
        $ivaAliquots = [
            ['code' => 'IVA21',    'name' => 'IVA 21%',         'rate' => '21.00', 'afip_code' => 5,  'is_default' => 1],
            ['code' => 'IVA10.5',  'name' => 'IVA 10.5%',       'rate' => '10.50', 'afip_code' => 4,  'is_default' => 0],
            ['code' => 'IVA27',    'name' => 'IVA 27%',         'rate' => '27.00', 'afip_code' => 6,  'is_default' => 0],
            ['code' => 'IVA5',     'name' => 'IVA 5%',          'rate' => '5.00',  'afip_code' => 8,  'is_default' => 0],
            ['code' => 'IVA2.5',   'name' => 'IVA 2.5%',        'rate' => '2.50',  'afip_code' => 9,  'is_default' => 0],
            ['code' => 'IVA0',     'name' => 'No Gravado',      'rate' => '0.00',  'afip_code' => 3,  'is_default' => 0],
            ['code' => 'EXENTO',   'name' => 'Exento',          'rate' => '0.00',  'afip_code' => 2,  'is_default' => 0],
        ];

        foreach ($ivaAliquots as $aliquot) {
            if (! $taxModel->where('company_id', $companyId)->where('code', $aliquot['code'])->first()) {
                $taxModel->insert(array_merge(['company_id' => $companyId, 'active' => 1], $aliquot));
            }
        }

        // Remove legacy IVA 16% if it still exists
        $legacyIva = $taxModel->where('company_id', $companyId)->where('code', 'IVA')->where('rate', '16.00')->first();
        if ($legacyIva) {
            $taxModel->update($legacyIva['id'], ['code' => 'IVA21', 'name' => 'IVA 21%', 'rate' => '21.00', 'afip_code' => 5, 'is_default' => 1]);
        }
    }

    private function seedSalesDefaults(string $companyId, string $branchId): void
    {
        $salesAgentModel = new SalesAgentModel();
        $salesZoneModel = new SalesZoneModel();
        $salesConditionModel = new SalesConditionModel();
        $voucherSequenceModel = new VoucherSequenceModel();

        if (! $salesAgentModel->where('company_id', $companyId)->where('code', 'VEN-GRAL')->first()) {
            $salesAgentModel->insert([
                'company_id' => $companyId,
                'name' => 'Vendedor General',
                'code' => 'VEN-GRAL',
                'email' => 'ventas@codex.local',
                'phone' => '+54 11 0000 0000',
                'commission_rate' => 0,
                'notes' => 'Vendedor base para pruebas.',
                'active' => 1,
            ]);
        }

        if (! $salesZoneModel->where('company_id', $companyId)->where('code', 'ZONA-CENTRO')->first()) {
            $salesZoneModel->insert([
                'company_id' => $companyId,
                'name' => 'Zona Centro',
                'code' => 'ZONA-CENTRO',
                'region' => 'Buenos Aires',
                'description' => 'Zona comercial general de prueba.',
                'active' => 1,
            ]);
        }

        if (! $salesConditionModel->where('company_id', $companyId)->where('code', 'CONTADO')->first()) {
            $salesConditionModel->insert([
                'company_id' => $companyId,
                'name' => 'Contado',
                'code' => 'CONTADO',
                'credit_limit' => 0,
                'payment_terms_days' => 0,
                'discount_rate' => 0,
                'requires_authorization' => 0,
                'notes' => 'Condicion comercial base.',
                'active' => 1,
            ]);
        }

        if (! $voucherSequenceModel->where('company_id', $companyId)->where('document_type', 'FACTURA')->first()) {
            $voucherSequenceModel->insert([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'document_type' => 'FACTURA',
                'prefix' => 'FAC',
                'current_number' => 1,
                'active' => 1,
            ]);
        }
    }

    private function seedSystems(string $companyId): array
    {
        $systemModel = new SystemModel();
        $companySystemModel = new CompanySystemModel();

        $systems = [
            [
                'name' => 'Ventas',
                'slug' => 'ventas',
                'description' => 'Gestion comercial y facturacion.',
                'entry_url' => 'ventas',
                'icon' => 'bi-cart-check',
                'active' => 1,
            ],
            [
                'name' => 'Inventario',
                'slug' => 'inventario',
                'description' => 'Control de existencias y movimientos.',
                'entry_url' => 'inventario',
                'icon' => 'bi-box-seam',
                'active' => 1,
            ],
            [
                'name' => 'Compras',
                'slug' => 'compras',
                'description' => 'Gestion de compras y proveedores.',
                'entry_url' => 'compras',
                'icon' => 'bi-bag-check',
                'active' => 1,
            ],
            [
                'name' => 'Caja',
                'slug' => 'caja',
                'description' => 'Apertura, cierre y tesoreria diaria.',
                'entry_url' => 'caja',
                'icon' => 'bi-cash-stack',
                'active' => 1,
            ],
        ];
        
        $systemIds = [];

        foreach ($systems as $system) {
            $existingSystem = $systemModel->where('slug', $system['slug'])->first();

            if ($existingSystem) {
                $systemIds[$system['slug']] = $existingSystem['id'];
                $systemModel->update($existingSystem['id'], [
                    'name' => $system['name'],
                    'description' => $system['description'],
                    'entry_url' => in_array($system['slug'], ['inventario', 'ventas'], true) ? $system['entry_url'] : $system['entry_url'],
                    'icon' => $system['icon'],
                    'active' => $system['active'],
                ]);
                continue;
            }

            $systemIds[$system['slug']] = $systemModel->insert($system, true);
        }

        foreach (['ventas', 'inventario', 'compras', 'caja'] as $systemSlug) {
            if (! $companySystemModel->where('company_id', $companyId)->where('system_id', $systemIds[$systemSlug])->first()) {
                $companySystemModel->insert([
                    'company_id' => $companyId,
                    'system_id' => $systemIds[$systemSlug],
                    'active' => 1,
                ]);
            }
        }

        return $systemIds;
    }

    private function seedDemoUsers(string $companyId, string $branchId, array $roleIds, array $systemIds): void
    {
        $userModel = new UserModel();
        $userSystemModel = new UserSystemModel();

        // ── Demo users: only use known passwords in development ──
        $isDev = ENVIRONMENT === 'development' || ENVIRONMENT === 'testing';

        $demoUsers = [
            [
                'username'    => 'superadmin',
                'email'       => 'superadmin@codex.local',
                'name'        => 'Super Admin',
                'company_id'  => null,
                'branch_id'   => null,
                'role_id'     => $roleIds['superadmin'],
                'dev_password' => 'SuperAdmin123*',
            ],
            [
                'username'    => 'admin',
                'email'       => 'admin@codex.local',
                'name'        => 'Admin Demo',
                'company_id'  => $companyId,
                'branch_id'   => $branchId,
                'role_id'     => $roleIds['admin'],
                'dev_password' => 'Admin123*',
            ],
            [
                'username'    => 'operador',
                'email'       => 'operador@codex.local',
                'name'        => 'Operador Demo',
                'company_id'  => $companyId,
                'branch_id'   => $branchId,
                'role_id'     => $roleIds['operador'],
                'dev_password' => 'Operador123*',
            ],
        ];

        foreach ($demoUsers as $demoUser) {
            if (! $userModel->where('username', $demoUser['username'])->first()) {
                $password = $isDev
                    ? $demoUser['dev_password']
                    : bin2hex(random_bytes(16)) . 'A1!'; // Random secure password in production

                $userModel->insert([
                    'company_id'          => $demoUser['company_id'],
                    'branch_id'           => $demoUser['branch_id'],
                    'role_id'             => $demoUser['role_id'],
                    'name'                => $demoUser['name'],
                    'username'            => $demoUser['username'],
                    'email'               => $demoUser['email'],
                    'password_hash'       => password_hash($password, PASSWORD_DEFAULT),
                    'must_change_password' => $isDev ? 0 : 1,
                    'active'              => 1,
                ]);
            }
        }

        $admin = $userModel->where('username', 'admin')->first();
        if ($admin) {
            foreach (['ventas', 'inventario', 'compras', 'caja'] as $systemSlug) {
                if (! $userSystemModel->where('user_id', $admin['id'])->where('system_id', $systemIds[$systemSlug])->first()) {
                    $userSystemModel->insert([
                        'company_id' => $companyId,
                        'user_id' => $admin['id'],
                        'system_id' => $systemIds[$systemSlug],
                        'access_level' => 'manage',
                        'active' => 1,
                    ]);
                }
            }
        }
    }
}
