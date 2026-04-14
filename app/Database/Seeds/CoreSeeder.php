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
        $roleModel = new RoleModel();
        $permissionModel = new PermissionModel();
        $rolePermissionModel = new RolePermissionModel();
        $companyModel = new CompanyModel();
        $branchModel = new BranchModel();
        $companySystemModel = new CompanySystemModel();
        $currencyModel = new CurrencyModel();
        $taxModel = new TaxModel();
        $systemModel = new SystemModel();
        $salesAgentModel = new SalesAgentModel();
        $salesZoneModel = new SalesZoneModel();
        $salesConditionModel = new SalesConditionModel();
        $voucherSequenceModel = new VoucherSequenceModel();
        $userModel = new UserModel();
        $userSystemModel = new UserSystemModel();

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

        if (! $taxModel->where('company_id', $companyId)->where('code', 'IVA')->first()) {
            $taxModel->insert([
                'company_id' => $companyId,
                'name' => 'IVA General',
                'code' => 'IVA',
                'rate' => '16.00',
                'active' => 1,
            ]);
        }

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

        if (! $userModel->where('username', 'superadmin')->first()) {
            $userModel->insert([
                'company_id' => null,
                'branch_id' => null,
                'role_id' => $roleIds['superadmin'],
                'name' => 'Super Admin',
                'username' => 'superadmin',
                'email' => 'superadmin@codex.local',
                'password_hash' => password_hash('SuperAdmin123*', PASSWORD_DEFAULT),
                'must_change_password' => 0,
                'active' => 1,
            ]);
        }

        if (! $userModel->where('username', 'admin')->first()) {
            $userModel->insert([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'role_id' => $roleIds['admin'],
                'name' => 'Admin Demo',
                'username' => 'admin',
                'email' => 'admin@codex.local',
                'password_hash' => password_hash('Admin123*', PASSWORD_DEFAULT),
                'must_change_password' => 0,
                'active' => 1,
            ]);
        }

        if (! $userModel->where('username', 'operador')->first()) {
            $userModel->insert([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'role_id' => $roleIds['operador'],
                'name' => 'Operador Demo',
                'username' => 'operador',
                'email' => 'operador@codex.local',
                'password_hash' => password_hash('Operador123*', PASSWORD_DEFAULT),
                'must_change_password' => 0,
                'active' => 1,
            ]);
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
