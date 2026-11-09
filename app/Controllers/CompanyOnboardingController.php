<?php

namespace App\Controllers;

use App\Libraries\{AccountingService, ArcaService, CompanyOnboardingService, CompanyPaymentMethodService};
use App\Models\{BranchModel, CompanyModel, CompanySystemModel, CurrencyModel, InventoryWarehouseModel, SalesPointOfSaleModel, SalesSettingModel};
use RuntimeException;

/** Company registration wizard: page, step data (JSON) and one save endpoint per step. */
class CompanyOnboardingController extends BaseController
{
    private function actor(): array
    {
        $user = $this->currentUser() ?? [];

        return ['id' => (string) ($user['id'] ?? ''), 'role_slug' => (string) ($user['role_slug'] ?? ''), 'company_id' => $user['company_id'] ?? null];
    }

    private function reply(array $data, int $status = 200)
    {
        return $this->response->setHeader('Cache-Control', 'no-store')->setStatusCode($status)->setJSON($data + ['csrf_token' => csrf_hash()]);
    }

    private function input(): array
    {
        return (array) ($this->request->getJSON(true) ?: $this->request->getPost());
    }

    /** Superadmin: new company (no id) or any company. */
    public function index(?string $companyId = null)
    {
        if (! $this->isSuperadmin()) {
            return redirect()->to('/empresas')->with('error', 'Solo el superadministrador puede dar de alta empresas.');
        }
        if ($companyId && ! (new CompanyModel())->find($companyId)) {
            return redirect()->to('/empresas')->with('error', 'Empresa no disponible.');
        }

        return $this->page($companyId);
    }

    /** Company admin: own company, steps 4-9. */
    public function own()
    {
        $companyId = $this->companyId();
        if (! $companyId) {
            return redirect()->to('/empresas');
        }
        try {
            (new CompanyOnboardingService())->authorize($this->actor(), $companyId, 4);
        } catch (RuntimeException $e) {
            return redirect()->to('/configuracion')->with('error', $e->getMessage());
        }

        return $this->page($companyId);
    }

    private function page(?string $companyId)
    {
        return view('companies/onboarding', [
            'pageTitle' => 'Asistente de alta de empresa',
            'config' => [
                'companyId' => $companyId, 'role' => $this->roleSlug(), 'baseUrl' => site_url('asistente-empresa'),
                'csrfHeader' => config('Security')->headerName, 'csrfToken' => csrf_hash(), 'steps' => CompanyOnboardingService::STEPS,
                'superadminSteps' => CompanyOnboardingService::SUPERADMIN_STEPS, 'optionalSteps' => CompanyOnboardingService::OPTIONAL_STEPS,
                'companiesUrl' => site_url('empresas'), 'dashboardUrl' => site_url('dashboard'),
            ],
        ]);
    }

    /** All the data the wizard needs for one company (or the empty form for a new one). Never returns file paths. */
    public function data(?string $companyId = null)
    {
        try {
            $service = new CompanyOnboardingService();
            $actor = $this->actor();
            $catalogCurrencies = $this->companyCurrencyOptions();
            if (! $companyId) {
                $service->authorize($actor, null, 1);

                return $this->reply(['state' => null, 'company' => null, 'currencies' => $catalogCurrencies, 'ivaConditions' => ArcaService::ISSUER_VAT_CONDITIONS]);
            }
            $service->authorize($actor, $companyId, $actor['role_slug'] === 'superadmin' ? 1 : 4);
            $company = (new CompanyModel())->find($companyId);
            $sales = (new SalesSettingModel())->where('company_id', $companyId)->first() ?? [];
            $active = array_column((new CompanySystemModel())->where('company_id', $companyId)->where('active', 1)->findAll(), 'system_id');
            $admin = $service->admin($companyId);

            return $this->reply([
                'state' => $service->state($companyId),
                'company' => $company + ['iva_condition' => $sales['arca_iva_condition'] ?? '', 'iibb' => $sales['arca_iibb'] ?? '', 'start_activities' => $sales['arca_start_activities'] ?? ''],
                'currencies' => $this->companyCurrencyOptions($companyId, $company['currency_code'] ?? null) + $catalogCurrencies,
                'ivaConditions' => ArcaService::ISSUER_VAT_CONDITIONS,
                'modules' => array_map(static fn($m) => ['id' => $m['id'], 'slug' => $m['slug'], 'name' => $m['name'], 'dependencies' => $m['dependencies'], 'active' => in_array($m['id'], $active, true)], $service->moduleCatalog()),
                'admin' => $admin ? ['name' => $admin['name'], 'username' => $admin['username'], 'email' => $admin['email']] : null,
                'branches' => (new BranchModel())->select('id, name, code, address, active')->where('company_id', $companyId)->orderBy('code')->findAll(),
                'warehouses' => (new InventoryWarehouseModel())->select('id, name, code, branch_id, is_default, active')->where('company_id', $companyId)->orderBy('name')->findAll(),
                'taxes' => $service->taxes($companyId),
                'paymentMethods' => array_map(static fn($row) => $row + ['currency_ids' => json_decode($row['currency_ids'] ?? '[]', true) ?: [],
                    'branch_ids' => json_decode($row['branch_ids'] ?? '[]', true) ?: [], 'point_of_sale_ids' => json_decode($row['point_of_sale_ids'] ?? '[]', true) ?: []], $service->paymentMethods($companyId)),
                'paymentTypes' => CompanyPaymentMethodService::TYPES, 'paymentDestinations' => CompanyPaymentMethodService::DESTINATIONS,
                'currenciesActive' => (new CurrencyModel())->select('id, code')->where('company_id', $companyId)->where('active', 1)->findAll(),
                'pointsOfSale' => (new SalesPointOfSaleModel())->select('id, name, code, channel, afip_pos_number')->where('company_id', $companyId)->findAll(),
                'arca' => array_intersect_key($sales, array_flip(['arca_enabled', 'arca_environment', 'arca_cuit', 'arca_iva_condition', 'arca_iibb', 'arca_start_activities', 'point_of_sale_standard', 'point_of_sale_kiosk']))
                    + ['diagnostics' => $sales ? $service->arcaDiagnostics($companyId) : null],
                'accounting' => $service->accounting($companyId),
                'review' => $service->review($companyId),
                'nextYear' => (int) date('Y') + 1,
            ]);
        } catch (RuntimeException $e) {
            return $this->reply(['error' => $e->getMessage()], 403);
        }
    }

    /** Step 1 for a new company (superadmin). */
    public function create()
    {
        try {
            $service = new CompanyOnboardingService();
            $service->authorize($this->actor(), null, 1);
            $companyId = $service->saveCompany(null, $this->input(), $this->actor()['id']);

            return $this->reply(['companyId' => $companyId, 'message' => 'Empresa creada. Continúa con los módulos.'], 201);
        } catch (RuntimeException $e) {
            return $this->reply(['error' => $e->getMessage()], 422);
        }
    }

    public function save(string $companyId, int $step)
    {
        try {
            $service = new CompanyOnboardingService();
            $actor = $this->actor();
            $service->authorize($actor, $companyId, $step);
            if (! (new CompanyModel())->find($companyId)) {
                throw new RuntimeException('Empresa no disponible.');
            }
            $input = $this->input();
            $extra = [];
            switch ($step) {
                case 1: $service->saveCompany($companyId, $input, $actor['id']); break;
                case 2: $service->saveModules($companyId, (array) ($input['systems'] ?? [])); break;
                case 3: $service->saveAdmin($companyId, $input); break;
                case 4: $service->saveStructure($companyId, $input); break;
                case 5: $service->saveTaxes($companyId, (array) ($input['taxes'] ?? [])); break;
                case 6: $service->savePaymentMethods($companyId, (array) ($input['methods'] ?? [])); break;
                case 7:
                    $files = [];
                    foreach (['certificate', 'private_key'] as $name) {
                        $file = $this->request->getFile($name);
                        $files[$name] = $file && $file->isValid() && ! $file->hasMoved() ? $file->getTempName() : null;
                    }
                    $extra['diagnostics'] = $service->saveArca($companyId, $input, $files['certificate'], $files['private_key']);
                    break;
                case 8: $service->saveAccounting($companyId, (array) ($input['mapping'] ?? []), (array) ($input['open_years'] ?? [])); break;
                default: throw new RuntimeException('Este paso se completa con Finalizar.');
            }

            return $this->reply(['state' => $service->state($companyId), 'message' => 'Paso guardado: ' . CompanyOnboardingService::STEPS[$step] . '.'] + $extra);
        } catch (RuntimeException $e) {
            return $this->reply(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            log_message('error', 'Asistente de empresa: {message}', ['message' => $e->getMessage()]);

            return $this->reply(['error' => 'No se pudo guardar el paso. Revisa los datos e intenta de nuevo.'], 500);
        }
    }

    public function skip(string $companyId, int $step)
    {
        try {
            $service = new CompanyOnboardingService();
            $service->authorize($this->actor(), $companyId, $step);
            $service->skip($companyId, $step);

            return $this->reply(['state' => $service->state($companyId), 'message' => 'Paso omitido; podrás completarlo más tarde.']);
        } catch (RuntimeException $e) {
            return $this->reply(['error' => $e->getMessage()], 422);
        }
    }

    public function finish(string $companyId)
    {
        try {
            $service = new CompanyOnboardingService();
            $service->authorize($this->actor(), $companyId, 9);

            return $this->reply(['state' => $service->finish($companyId, $this->actor()['id']), 'message' => 'Empresa configurada correctamente.']);
        } catch (RuntimeException $e) {
            return $this->reply(['error' => $e->getMessage()], 422);
        }
    }
}
