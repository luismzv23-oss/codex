<?php

namespace App\Libraries;

use App\Models\{BranchModel, CompanyModel, CompanyPaymentMethodModel, CurrencyModel, InventoryWarehouseModel, RoleModel, SalesPointOfSaleModel,
    SalesSettingModel, SystemModel, TaxModel, UserModel};
use RuntimeException;

/**
 * Company registration wizard. Each step validates with the existing services, saves its part and is marked
 * done (or skipped) in company_onboarding, so the wizard can be resumed. Steps 1-3 (company, modules, admin)
 * belong to the superadmin; steps 4-9 can be completed by the company admin.
 */
class CompanyOnboardingService
{
    public const STEPS = [1 => 'Empresa', 2 => 'Módulos', 3 => 'Administrador', 4 => 'Sucursales y depósitos', 5 => 'Impuestos',
        6 => 'Medios de pago', 7 => 'Facturación electrónica', 8 => 'Contabilidad', 9 => 'Revisión'];
    public const SUPERADMIN_STEPS = [1, 2, 3];
    public const OPTIONAL_STEPS = [7, 8];
    public const IVA_DEFAULTS = [
        ['code' => 'IVA21', 'name' => 'IVA 21%', 'rate' => '21.00', 'afip_code' => 5, 'is_default' => 1],
        ['code' => 'IVA10.5', 'name' => 'IVA 10.5%', 'rate' => '10.50', 'afip_code' => 4, 'is_default' => 0],
        ['code' => 'IVA27', 'name' => 'IVA 27%', 'rate' => '27.00', 'afip_code' => 6, 'is_default' => 0],
        ['code' => 'IVA5', 'name' => 'IVA 5%', 'rate' => '5.00', 'afip_code' => 8, 'is_default' => 0],
        ['code' => 'IVA2.5', 'name' => 'IVA 2.5%', 'rate' => '2.50', 'afip_code' => 9, 'is_default' => 0],
        ['code' => 'IVA0', 'name' => 'No Gravado', 'rate' => '0.00', 'afip_code' => 3, 'is_default' => 0],
        ['code' => 'EXENTO', 'name' => 'Exento', 'rate' => '0.00', 'afip_code' => 2, 'is_default' => 0],
    ];

    // ---- State and access -------------------------------------------------------------------------

    /** Companies without an onboarding row were created before the wizard and are considered configured. */
    public function isCompleted(string $companyId): bool
    {
        $row = $this->row($companyId);

        return ! $row || $row['status'] === 'completed';
    }

    public function state(string $companyId): array
    {
        $row = $this->row($companyId);
        $steps = $row ? (json_decode($row['steps_json'] ?: '{}', true) ?: []) : [];
        $current = 9;
        foreach (array_keys(self::STEPS) as $step) {
            if ($step < 9 && empty($steps[$step])) {
                $current = $step;
                break;
            }
        }

        return ['status' => $row['status'] ?? 'completed', 'tracked' => (bool) $row, 'steps' => $steps, 'current' => $current,
            'completed_at' => $row['completed_at'] ?? null, 'company' => (new CompanyModel())->find($companyId)];
    }

    /** Superadmin: any company and step. Company admin: own company, steps 4-9. */
    public function authorize(array $actor, ?string $companyId, int $step): void
    {
        if (! isset(self::STEPS[$step])) {
            throw new RuntimeException('Paso inexistente.');
        }
        $role = $actor['role_slug'] ?? '';
        if ($role === 'superadmin') {
            return;
        }
        if ($role !== 'admin' || ! $companyId || ($actor['company_id'] ?? null) !== $companyId) {
            throw new RuntimeException('No tienes acceso a la configuración de esta empresa.');
        }
        if (in_array($step, self::SUPERADMIN_STEPS, true)) {
            throw new RuntimeException('Los datos de la empresa, sus módulos y su administrador los define el superadministrador.');
        }
    }

    /** Starts tracking an existing company (e.g. to complete one created before the wizard). */
    public function start(string $companyId, string $userId): array
    {
        if (! (new CompanyModel())->find($companyId)) {
            throw new RuntimeException('Empresa no disponible.');
        }
        if (! $this->row($companyId)) {
            $now = date('Y-m-d H:i:s');
            db_connect()->table('company_onboarding')->insert(['id' => app_uuid(), 'company_id' => $companyId, 'status' => 'in_progress',
                'steps_json' => '{}', 'started_by' => $userId, 'created_at' => $now, 'updated_at' => $now]);
        }

        return $this->state($companyId);
    }

    // ---- Step 1: company --------------------------------------------------------------------------

    /** Creates (no $companyId) or updates the company with its fiscal data, base currency, main branch and accounting. */
    public function saveCompany(?string $companyId, array $input, string $userId): string
    {
        $name = $this->text($input['name'] ?? '', 'Nombre comercial', 150, true, 3);
        $legal = $this->text($input['legal_name'] ?? '', 'Razón social', 180, true, 3);
        $cuit = self::cuit((string) ($input['tax_id'] ?? ''));
        $condition = (string) ($input['iva_condition'] ?? '');
        if (! array_key_exists($condition, ArcaService::ISSUER_VAT_CONDITIONS)) {
            throw new RuntimeException('Selecciona la condición frente al IVA.');
        }
        $iibb = $this->text($input['iibb'] ?? '', 'Ingresos brutos', 40);
        $start = $this->date($input['start_activities'] ?? '', 'Inicio de actividades');
        $email = $this->text($input['email'] ?? '', 'Correo', 150);
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Correo inválido.');
        }
        $currency = strtoupper($this->text($input['currency_code'] ?? '', 'Moneda base', 10, true));
        if (! (new CurrencyModel())->where('code', $currency)->first()) {
            throw new RuntimeException('La moneda base seleccionada no está disponible.');
        }
        $duplicate = (new CompanyModel())->where('tax_id', $cuit);
        if ($companyId) {
            $duplicate->where('id !=', $companyId);
        }
        if ($duplicate->first()) {
            throw new RuntimeException('Ya existe una empresa con ese CUIT.');
        }
        $data = ['name' => $name, 'legal_name' => $legal, 'tax_id' => $cuit, 'email' => $email, 'phone' => $this->text($input['phone'] ?? '', 'Teléfono', 40),
            'address' => $this->text($input['address'] ?? '', 'Dirección', 500), 'currency_code' => $currency];
        $db = db_connect();
        $db->transBegin();
        try {
            $model = new CompanyModel();
            if ($companyId) {
                if (! $model->find($companyId) || ! $model->update($companyId, $data)) {
                    throw new RuntimeException('No se pudo actualizar la empresa.');
                }
            } else {
                $companyId = (string) $model->insert($data + ['active' => 1], true);
                if ($companyId === '') {
                    throw new RuntimeException('No se pudo crear la empresa.');
                }
            }
            $this->ensureBaseCurrency($companyId, $currency);
            (new BranchModel())->ensureMainBranch($companyId);
            (new AccountingService())->setupCompanyAccounting($companyId);
            $settings = (new CompanyProvisioningService())->salesSettings($companyId);
            (new SalesSettingModel())->update($settings['id'], ['arca_cuit' => $cuit, 'arca_iva_condition' => $condition, 'arca_iibb' => $iibb,
                'arca_start_activities' => $start, 'default_currency_code' => $currency]);
            $this->start($companyId, $userId);
            $this->mark($companyId, 1, 'done');
            if (! $db->transStatus() || ! $db->transCommit()) {
                throw new RuntimeException('No se pudo guardar la empresa.');
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        return $companyId;
    }

    // ---- Step 2: modules --------------------------------------------------------------------------

    /** Module catalog with dependencies declared in app/Modules/*\/module.php. */
    public function moduleCatalog(): array
    {
        $dependencies = [];
        foreach (glob(APPPATH . 'Modules/*/module.php') ?: [] as $file) {
            $manifest = include $file;
            if (is_array($manifest) && ! empty($manifest['slug'])) {
                $dependencies[$manifest['slug']] = array_keys($manifest['dependencies'] ?? []);
            }
        }

        return array_map(static fn($system) => $system + ['dependencies' => $dependencies[$system['slug']] ?? []],
            (new SystemModel())->where('active', 1)->orderBy('name')->findAll());
    }

    public function saveModules(string $companyId, array $systemIds): void
    {
        $catalog = array_column($this->moduleCatalog(), null, 'id');
        $selected = array_values(array_intersect(array_map('strval', $systemIds), array_keys($catalog)));
        if (! $selected) {
            throw new RuntimeException('Selecciona al menos un módulo.');
        }
        $slugs = array_column(array_intersect_key($catalog, array_flip($selected)), 'slug');
        foreach ($selected as $id) {
            foreach ($catalog[$id]['dependencies'] as $dependency) {
                if (! in_array($dependency, $slugs, true)) {
                    throw new RuntimeException('El módulo ' . $catalog[$id]['name'] . ' requiere ' . ucfirst($dependency) . '.');
                }
            }
        }
        (new SettingsService())->transaction($companyId, function () use ($companyId, $selected) {
            (new CompanySystemsService())->setActive($companyId, $selected);
            $this->mark($companyId, 2, 'done');
        });
    }

    // ---- Step 3: administrator --------------------------------------------------------------------

    public function admin(string $companyId): ?array
    {
        $role = (new RoleModel())->findBySlug('admin');

        return $role ? (new UserModel())->where('company_id', $companyId)->where('role_id', $role['id'])->first() : null;
    }

    /** Creates the company admin on branch MAIN (or updates name/email) and grants every company module. */
    public function saveAdmin(string $companyId, array $input): array
    {
        $existing = $this->admin($companyId);
        $branch = (new BranchModel())->where('company_id', $companyId)->where('code', 'MAIN')->first();
        if (! $branch) {
            throw new RuntimeException('La empresa no tiene sucursal Casa Matriz.');
        }
        $rules = ['name' => 'required|min_length[3]|max_length[150]',
            'username' => 'required|min_length[3]|max_length[50]|is_unique[users.username' . ($existing ? ',id,' . $existing['id'] : '') . ']',
            'email' => 'required|valid_email|max_length[150]|is_unique[users.email' . ($existing ? ',id,' . $existing['id'] : '') . ']'];
        if (! $existing || (string) ($input['password'] ?? '') !== '') {
            $rules['password'] = 'required|min_length[8]|max_length[255]|strong_password|not_common_password';
        }
        $validation = \Config\Services::validation(null, false);
        $validation->setRules($rules);
        if (! $validation->run(array_intersect_key($input, $rules))) {
            throw new RuntimeException(implode(' ', $validation->getErrors()));
        }
        $data = ['name' => trim((string) $input['name']), 'username' => trim((string) $input['username']), 'email' => trim((string) $input['email'])];
        if (isset($rules['password'])) {
            $data['password_hash'] = password_hash((string) $input['password'], PASSWORD_DEFAULT);
        }

        return (new SettingsService())->transaction($companyId, function () use ($companyId, $existing, $branch, $data) {
            $model = new UserModel();
            if ($existing) {
                $model->update($existing['id'], $data);
                $id = $existing['id'];
            } else {
                $role = (new RoleModel())->findBySlug('admin');
                if (! $role) {
                    throw new RuntimeException('No se encontró el rol admin.');
                }
                $id = $model->insert($data + ['company_id' => $companyId, 'branch_id' => $branch['id'], 'role_id' => $role['id'], 'must_change_password' => 1, 'active' => 1], true);
                if (! $id) {
                    throw new RuntimeException('No se pudo crear el administrador.');
                }
            }
            (new CompanySystemsService())->syncAdmin($companyId);
            $this->mark($companyId, 3, 'done');

            return $model->find($id);
        });
    }

    // ---- Step 4: branches, warehouses and module defaults -----------------------------------------

    /** $input: branches[{name, code, address}], warehouses[{name, code, branch_id}] to add. Then creates every module default. */
    public function saveStructure(string $companyId, array $input): void
    {
        (new SettingsService())->transaction($companyId, function () use ($companyId, $input) {
            foreach ((array) ($input['branches'] ?? []) as $branch) {
                if (trim((string) ($branch['name'] ?? '')) !== '') {
                    (new SettingsService())->branch($companyId, $branch + ['active' => 1]);
                }
            }
            $provisioning = new CompanyProvisioningService();
            $provisioning->inventory($companyId);
            foreach ((array) ($input['warehouses'] ?? []) as $warehouse) {
                $name = $this->text($warehouse['name'] ?? '', 'Nombre del depósito', 150);
                if ($name === '') {
                    continue;
                }
                $code = strtoupper($this->text($warehouse['code'] ?? '', 'Código del depósito', 30, true));
                $branchId = (string) ($warehouse['branch_id'] ?? '');
                if ($branchId !== '' && ! (new BranchModel())->where('company_id', $companyId)->find($branchId)) {
                    throw new RuntimeException('La sucursal del depósito no pertenece a la empresa.');
                }
                $model = new InventoryWarehouseModel();
                if ($model->where('company_id', $companyId)->where('code', $code)->first()) {
                    throw new RuntimeException('Ya existe un depósito con el código ' . $code . '.');
                }
                $model->insert(['company_id' => $companyId, 'branch_id' => $branchId ?: null, 'name' => $name, 'code' => $code, 'type' => 'secondary', 'is_default' => 0, 'active' => 1]);
            }
            $provisioning->all($companyId);
            $this->mark($companyId, 4, 'done');
        });
    }

    // ---- Step 5: taxes -----------------------------------------------------------------------------

    /** Current IVA rates, or the standard proposal when the company has none. */
    public function taxes(string $companyId): array
    {
        $rows = (new TaxModel())->where('company_id', $companyId)->orderBy('rate', 'DESC')->findAll();

        return $rows ?: array_map(static fn($row) => $row + ['active' => 1, 'proposed' => true], self::IVA_DEFAULTS);
    }

    /** $rows: [{id?, code, name, rate, afip_code, is_default, active}] saved through SettingsService::tax. */
    public function saveTaxes(string $companyId, array $rows): void
    {
        (new SettingsService())->transaction($companyId, function () use ($companyId, $rows) {
            $service = new SettingsService();
            $default = null;
            foreach ($rows as $row) {
                $id = trim((string) ($row['id'] ?? '')) ?: null;
                $saved = $service->tax($companyId, ['code' => $row['code'] ?? '', 'name' => $row['name'] ?? '', 'rate' => $row['rate'] ?? '',
                    'afip_code' => $row['afip_code'] ?? '', 'active' => (int) ($row['active'] ?? 1), 'is_default' => 0], $id);
                if ((int) ($row['is_default'] ?? 0) === 1) {
                    $default = $saved['id'];
                }
            }
            if (! $default) {
                throw new RuntimeException('Marca una alícuota como predeterminada.');
            }
            $service->taxAction($companyId, $default, 'default');
            $this->mark($companyId, 5, 'done');
        });
    }

    // ---- Step 6: payment methods ------------------------------------------------------------------

    public function paymentMethods(string $companyId): array
    {
        $rows = (new CompanyPaymentMethodModel())->where('company_id', $companyId)->orderBy('code')->findAll();
        if ($rows) {
            return $rows;
        }
        $base = ['active' => 1, 'allows_installments' => 0, 'requires_confirmation' => 0, 'percentage' => '0.00', 'allow_collections' => 1, 'proposed' => true];

        return [
            $base + ['code' => 'EFECTIVO', 'name' => 'Efectivo', 'type' => 'cash', 'funds_destination' => 'cash', 'allow_supplier_payments' => 1],
            $base + ['code' => 'TRANSFERENCIA', 'name' => 'Transferencia bancaria', 'type' => 'transfer', 'funds_destination' => 'bank', 'allow_supplier_payments' => 1, 'requires_confirmation' => 1],
            $base + ['code' => 'DEBITO', 'name' => 'Tarjeta de débito', 'type' => 'card', 'funds_destination' => 'clearing', 'allow_supplier_payments' => 0],
            $base + ['code' => 'CREDITO', 'name' => 'Tarjeta de crédito', 'type' => 'card', 'funds_destination' => 'clearing', 'allow_supplier_payments' => 0, 'allows_installments' => 1],
        ];
    }

    /** $rows: payment methods for CompanyPaymentMethodService::save (currency defaults to the base currency). */
    public function savePaymentMethods(string $companyId, array $rows): void
    {
        $currency = (new CurrencyModel())->where('company_id', $companyId)->where('is_default', 1)->first()
            ?? (new CurrencyModel())->where('company_id', $companyId)->where('active', 1)->first();
        if (! $currency) {
            throw new RuntimeException('La empresa no tiene moneda activa.');
        }
        (new SettingsService())->transaction($companyId, function () use ($companyId, $rows, $currency) {
            $service = new CompanyPaymentMethodService();
            $active = 0;
            foreach ($rows as $row) {
                $id = trim((string) ($row['id'] ?? '')) ?: null;
                $input = array_intersect_key($row, array_flip(['code', 'name', 'type', 'funds_destination', 'active', 'allows_installments', 'requires_confirmation',
                    'percentage', 'allow_collections', 'allow_supplier_payments', 'branch_ids', 'point_of_sale_ids', 'required_fields']));
                foreach (['active', 'allows_installments', 'requires_confirmation', 'allow_collections', 'allow_supplier_payments'] as $flag) {
                    $input[$flag] = (string) (int) ($input[$flag] ?? 0);
                }
                $input['percentage'] = number_format((float) ($input['percentage'] ?? 0), 2, '.', '');
                $input['currency_ids'] = (array) ($row['currency_ids'] ?? []) ?: [$currency['id']];
                $service->save($companyId, $input, $id);
                $active += $input['active'] === '1' ? 1 : 0;
            }
            if ($active === 0) {
                throw new RuntimeException('Deja al menos un medio de pago activo.');
            }
            $this->mark($companyId, 6, 'done');
        });
    }

    // ---- Step 7: ARCA ------------------------------------------------------------------------------

    /**
     * Saves electronic invoicing data, stores uploaded certificate/key (temp file paths) and returns diagnostics.
     * The step can be skipped and completed later from Ventas → Configuración.
     */
    public function saveArca(string $companyId, array $input, ?string $certificateFile = null, ?string $keyFile = null): array
    {
        $provisioning = new CompanyProvisioningService();
        $provisioning->sales($companyId);
        $settings = $provisioning->salesSettings($companyId);
        $enabled = (int) ($input['arca_enabled'] ?? 0) === 1;
        $environment = (string) ($input['arca_environment'] ?? 'homologacion');
        if (! in_array($environment, ['homologacion', 'produccion'], true)) {
            throw new RuntimeException('Ambiente de ARCA inválido.');
        }
        $condition = (string) ($input['arca_iva_condition'] ?? $settings['arca_iva_condition'] ?? '');
        if (! array_key_exists($condition, ArcaService::ISSUER_VAT_CONDITIONS)) {
            throw new RuntimeException('Selecciona la condición frente al IVA.');
        }
        $points = [];
        foreach (['point_of_sale_standard' => 'estándar', 'point_of_sale_kiosk' => 'kiosco'] as $field => $label) {
            $points[$field] = (int) ($input[$field] ?? 0);
            if ($points[$field] < 1 || $points[$field] > 99998) {
                throw new RuntimeException('Indica el número de punto de venta AFIP del canal ' . $label . ' (1 a 99998).');
            }
        }
        $arca = new ArcaService();
        $paths = ['certificate_path' => $settings['certificate_path'] ?? '', 'private_key_path' => $settings['private_key_path'] ?? ''];
        if ($certificateFile || $keyFile) {
            if (! $certificateFile || ! $keyFile) {
                throw new RuntimeException('Sube el certificado y su clave privada juntos.');
            }
            $paths = (new ArcaCertificateStorage())->store($companyId, $certificateFile, $keyFile);
        }
        $payload = ['arca_enabled' => $enabled ? 1 : 0, 'arca_cuit' => self::cuit((string) ($input['arca_cuit'] ?? $settings['arca_cuit'] ?? '')),
            'certificate_path' => $paths['certificate_path'], 'private_key_path' => $paths['private_key_path'], 'token_cache_path' => $settings['token_cache_path'] ?? ''];
        $validation = $arca->validateSettings($payload, $companyId);
        if (! $validation['valid']) {
            throw new RuntimeException(implode(' ', $validation['errors']));
        }
        $portable = $arca->toPortableSettings($validation['settings'], $companyId);
        (new SettingsService())->transaction($companyId, function () use ($companyId, $settings, $payload, $portable, $environment, $condition, $input, $points, $enabled) {
            (new SalesSettingModel())->update($settings['id'], [
                'arca_enabled' => $payload['arca_enabled'], 'arca_environment' => $environment, 'arca_cuit' => $payload['arca_cuit'],
                'arca_iva_condition' => $condition, 'arca_iibb' => $this->text($input['arca_iibb'] ?? $settings['arca_iibb'] ?? '', 'Ingresos brutos', 40),
                'arca_start_activities' => $this->date($input['arca_start_activities'] ?? $settings['arca_start_activities'] ?? '', 'Inicio de actividades'),
                'point_of_sale_standard' => $points['point_of_sale_standard'], 'point_of_sale_kiosk' => $points['point_of_sale_kiosk'],
                'wsaa_enabled' => 1, 'wsfev1_enabled' => 1,
                'certificate_path' => $portable['certificate_path'] ?? '', 'private_key_path' => $portable['private_key_path'] ?? '', 'token_cache_path' => $portable['token_cache_path'] ?? '',
            ]);
            $pos = new SalesPointOfSaleModel();
            $pos->where('company_id', $companyId)->where('channel', 'standard')->set('afip_pos_number', $points['point_of_sale_standard'])->update();
            $pos->where('company_id', $companyId)->where('channel', 'kiosk')->set('afip_pos_number', $points['point_of_sale_kiosk'])->update();
            $this->mark($companyId, 7, $enabled ? 'done' : 'skipped');
        });

        return $this->arcaDiagnostics($companyId);
    }

    /** Readiness and certificate checks without exposing file paths. */
    public function arcaDiagnostics(string $companyId): array
    {
        $settings = (new CompanyProvisioningService())->salesSettings($companyId);
        $arca = new ArcaService();
        $normalized = $arca->sanitizeSettings($settings, $companyId);
        $readiness = $arca->readiness($normalized);
        $certificate = $readiness['diagnostics'] ?? $arca->certificateDiagnostics($normalized);
        $pathKeys = ['certificate_path', 'private_key_path', 'token_cache_path'];

        // Only check results leave the server: no file paths, cache locations or environment internals.
        return [
            'readiness' => ['ready' => (bool) ($readiness['ready'] ?? false), 'progress' => (int) ($readiness['progress'] ?? 0), 'summary' => (string) ($readiness['summary'] ?? ''),
                'checks' => array_values(array_map(static fn($c) => ['label' => $c['label'], 'ok' => (bool) $c['ok']],
                    array_filter($readiness['checks'] ?? [], static fn($c) => ! in_array($c['key'] ?? '', $pathKeys, true))))],
            'certificate' => ['bundle_valid' => (bool) ($certificate['bundle_valid'] ?? false), 'summary' => (string) ($certificate['summary'] ?? ''),
                'valid_to' => $certificate['metadata']['valid_to'] ?? null, 'days_remaining' => $certificate['metadata']['days_remaining'] ?? null,
                'checks' => array_values(array_map(static fn($c) => ['label' => $c['label'], 'ok' => (bool) $c['ok']],
                    array_filter($certificate['checks'] ?? [], static fn($c) => ! str_starts_with((string) ($c['label'] ?? ''), 'Ruta')))) ],
            'has_certificate' => trim((string) ($settings['certificate_path'] ?? '')) !== '',
        ];
    }

    public function skip(string $companyId, int $step): void
    {
        if (! in_array($step, self::OPTIONAL_STEPS, true)) {
            throw new RuntimeException('Este paso es obligatorio.');
        }
        $this->mark($companyId, $step, 'skipped');
    }

    // ---- Step 8: accounting ------------------------------------------------------------------------

    public function accounting(string $companyId): array
    {
        $service = new AccountingService();
        $db = db_connect();

        return ['keys' => AccountingService::mappingKeys(), 'mapping' => $service->accountMapping($companyId),
            'accounts' => $db->table('accounts')->select('id, code, name')->where('company_id', $companyId)->where('active', 1)->where('is_group', 0)->where('accepts_entries', 1)->orderBy('code')->get()->getResultArray(),
            'periods' => $db->table('fiscal_periods')->select('name, start_date, end_date, status')->where('company_id', $companyId)->orderBy('start_date')->get()->getResultArray()];
    }

    /** $mapping: key => account id (empty keeps it unmapped). $openYears: calendar years to open. */
    public function saveAccounting(string $companyId, array $mapping, array $openYears = []): void
    {
        (new SettingsService())->transaction($companyId, function () use ($companyId, $mapping, $openYears) {
            $service = new AccountingService();
            if (! db_connect()->table('accounts')->where('company_id', $companyId)->countAllResults()) {
                $service->setupCompanyAccounting($companyId);
            }
            foreach ($mapping as $key => $accountId) {
                if (trim((string) $accountId) !== '') {
                    $service->saveMapping($companyId, (string) $key, (string) $accountId);
                }
            }
            $service->openFiscalPeriod($companyId, (int) date('Y'));
            foreach ($openYears as $year) {
                $service->openFiscalPeriod($companyId, (int) $year);
            }
            $this->mark($companyId, 8, 'done');
        });
    }

    // ---- Step 9: review ----------------------------------------------------------------------------

    public function review(string $companyId): array
    {
        $db = db_connect();
        $state = $this->state($companyId);
        $mapping = (new AccountingService())->accountMapping($companyId);
        $missing = array_diff(array_keys(AccountingService::mappingKeys()), array_keys($mapping));
        $checks = (new ReadinessService())->evaluate($companyId)['checks'];
        $checks[] = ['label' => 'Administrador de la empresa', 'ok' => (bool) $this->admin($companyId), 'critical' => true];
        $checks[] = ['label' => 'Alícuotas de IVA', 'ok' => $db->table('taxes')->where('company_id', $companyId)->where('active', 1)->countAllResults() > 0, 'critical' => true];
        $checks[] = ['label' => 'Medios de pago activos', 'ok' => $db->table('company_payment_methods')->where('company_id', $companyId)->where('active', 1)->where('deleted_at', null)->countAllResults() > 0, 'critical' => true];
        $checks[] = ['label' => 'Plan de cuentas y ejercicio ' . date('Y'), 'ok' => $db->table('fiscal_periods')->where('company_id', $companyId)->where('start_date', date('Y') . '-01-01')->countAllResults() > 0, 'critical' => true];
        $checks[] = ['label' => $missing ? 'Cuentas sin asignar: ' . implode(', ', array_map(static fn($k) => AccountingService::mappingKeys()[$k], $missing)) : 'Todas las funciones contables asignadas', 'ok' => ! $missing, 'critical' => false];
        foreach ([1, 2, 3, 4, 5, 6] as $step) {
            if (empty($state['steps'][$step])) {
                $checks[] = ['label' => 'Paso pendiente: ' . self::STEPS[$step], 'ok' => false, 'critical' => true];
            }
        }

        return ReadinessService::summarize($checks) + ['state' => $state];
    }

    public function finish(string $companyId, string $userId): array
    {
        $review = $this->review($companyId);
        if ($review['blocking']) {
            throw new RuntimeException('Antes de finalizar resuelve: ' . implode('; ', $review['blocking']) . '.');
        }
        $this->start($companyId, $userId);
        $this->mark($companyId, 9, 'done');
        db_connect()->table('company_onboarding')->where('company_id', $companyId)
            ->update(['status' => 'completed', 'completed_by' => $userId, 'completed_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);

        return $this->state($companyId);
    }

    // ---- Helpers -----------------------------------------------------------------------------------

    /** Normalizes and validates an Argentine CUIT (11 digits with check digit); returns digits only. */
    public static function cuit(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value);
        if (strlen($digits) !== 11) {
            throw new RuntimeException('El CUIT debe tener 11 dígitos.');
        }
        $sum = 0;
        foreach ([5, 4, 3, 2, 7, 6, 5, 4, 3, 2] as $i => $weight) {
            $sum += (int) $digits[$i] * $weight;
        }
        $check = 11 - ($sum % 11);
        $check = $check === 11 ? 0 : $check;
        if ($check === 10 || $check !== (int) $digits[10]) {
            throw new RuntimeException('El CUIT ' . $digits . ' no es válido (dígito verificador incorrecto).');
        }

        return $digits;
    }

    private function ensureBaseCurrency(string $companyId, string $code): void
    {
        $model = new CurrencyModel();
        $existing = $model->where('company_id', $companyId)->where('code', $code)->first();
        $model->where('company_id', $companyId)->set(['is_default' => 0])->update();
        if ($existing) {
            $model->update($existing['id'], ['is_default' => 1, 'active' => 1]);

            return;
        }
        $template = $model->select('code, name, symbol, exchange_rate')->where('code', $code)->orderBy('active', 'DESC')->first();
        $model->insert(['company_id' => $companyId, 'code' => $code, 'name' => $template['name'] ?? $code, 'symbol' => $template['symbol'] ?? '',
            'exchange_rate' => $template['exchange_rate'] ?? '1.0000', 'is_default' => 1, 'active' => 1]);
    }

    private function mark(string $companyId, int $step, string $status): void
    {
        $row = $this->row($companyId);
        if (! $row) {
            return;
        }
        $steps = json_decode($row['steps_json'] ?: '{}', true) ?: [];
        $steps[$step] = $status;
        ksort($steps);
        db_connect()->table('company_onboarding')->where('id', $row['id'])->update(['steps_json' => json_encode($steps), 'updated_at' => date('Y-m-d H:i:s')]);
    }

    private function row(string $companyId): ?array
    {
        $db = db_connect();
        if (! $db->tableExists('company_onboarding')) {
            return null;
        }

        return $db->table('company_onboarding')->where('company_id', $companyId)->get()->getRowArray();
    }

    private function text($value, string $label, int $max, bool $required = false, int $min = 0): string
    {
        if (! is_scalar($value) && $value !== null) {
            throw new RuntimeException('Valor inválido: ' . $label . '.');
        }
        $value = trim((string) $value);
        if ($required && $value === '') {
            throw new RuntimeException('Completa ' . $label . '.');
        }
        if (mb_strlen($value) > $max || ($value !== '' && mb_strlen($value) < $min)) {
            throw new RuntimeException($label . ': longitud no admitida.');
        }

        return $value;
    }

    private function date($value, string $label): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (! $date || $date->format('Y-m-d') !== $value) {
            throw new RuntimeException($label . ': fecha inválida.');
        }

        return $value;
    }
}
