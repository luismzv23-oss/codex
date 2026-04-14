<?php

namespace App\Controllers;

use App\Models\BranchModel;
use App\Models\CompanyModel;
use App\Models\CurrencyModel;
use App\Models\UserModel;

class CompaniesController extends BaseController
{
    public function index()
    {
        $builder = (new CompanyModel())->orderBy('name', 'ASC');

        if (! $this->isSuperadmin()) {
            $builder->where('id', $this->companyId());
        }

        return view('companies/index', [
            'pageTitle' => 'Empresas',
            'companies' => $builder->findAll(),
            'canCreate' => $this->isSuperadmin(),
            'canDisable' => $this->isSuperadmin(),
        ]);
    }

    public function create()
    {
        if (! $this->isSuperadmin()) {
            return redirect()->to('/empresas')->with('error', 'Solo superadmin puede crear empresas.');
        }

        return view('companies/form', [
            'pageTitle' => 'Nueva empresa',
            'company' => null,
            'formAction' => site_url('empresas'),
            'currencyOptions' => $this->companyCurrencyOptions(),
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function store()
    {
        if (! $this->isSuperadmin()) {
            return redirect()->to('/empresas')->with('error', 'Solo superadmin puede crear empresas.');
        }

        if (! $this->validate($this->rules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $currencyCode = trim((string) $this->request->getPost('currency_code'));

        if (! $this->isAllowedCurrencyCode($currencyCode)) {
            return redirect()->back()->withInput()->with('error', 'La moneda base seleccionada no esta disponible.');
        }

        $companyModel = new CompanyModel();
        $companyId = $companyModel->insert($this->payload(), true);
        $this->ensureBaseCurrency((string) $companyId, $currencyCode);
        $this->ensureMainBranch((string) $companyId);

        return $this->popupOrRedirect('/empresas', 'Empresa creada correctamente.');
    }

    public function edit(string $id)
    {
        $company = $this->findManagedCompany($id);

        if (! $company) {
            return redirect()->to('/empresas')->with('error', 'Empresa no disponible.');
        }

        $this->ensureBaseCurrency($company['id'], (string) ($company['currency_code'] ?? 'ARS'));

        return view('companies/form', [
            'pageTitle' => 'Editar empresa',
            'company' => $company,
            'formAction' => site_url('empresas/' . $company['id'] . '/actualizar'),
            'currencyOptions' => $this->companyCurrencyOptions($company['id'], $company['currency_code'] ?? null),
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function update(string $id)
    {
        $company = $this->findManagedCompany($id);

        if (! $company) {
            return redirect()->to('/empresas')->with('error', 'Empresa no disponible.');
        }

        if (! $this->validate($this->rules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $currencyCode = trim((string) $this->request->getPost('currency_code'));

        if (! $this->isAllowedCurrencyCode($currencyCode, $company['id'], $company['currency_code'] ?? null)) {
            return redirect()->back()->withInput()->with('error', 'La moneda base seleccionada no pertenece a las monedas activas de la empresa.');
        }

        (new CompanyModel())->update($company['id'], $this->payload());
        $this->ensureBaseCurrency($company['id'], $currencyCode);
        $this->ensureMainBranch($company['id']);

        return $this->popupOrRedirect('/empresas', 'Empresa actualizada correctamente.');
    }

    public function delete(string $id)
    {
        if (! $this->isSuperadmin()) {
            return redirect()->to('/empresas')->with('error', 'Solo superadmin puede eliminar empresas.');
        }

        $companyModel = new CompanyModel();
        $company = $companyModel->find($id);

        if (! $company) {
            return redirect()->to('/empresas')->with('error', 'Empresa no disponible.');
        }

        $db = db_connect();
        $db->transStart();

        (new UserModel())->where('company_id', $id)->delete();
        $companyModel->delete($id);

        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->to('/empresas')->with('error', 'No se pudo eliminar la empresa seleccionada.');
        }

        return redirect()->to('/empresas')->with('message', 'Empresa eliminada correctamente junto con sus datos relacionados.');
    }

    public function toggle(string $id)
    {
        if (! $this->isSuperadmin()) {
            return redirect()->to('/empresas')->with('error', 'Solo superadmin puede deshabilitar empresas.');
        }

        $company = (new CompanyModel())->find($id);

        if (! $company) {
            return redirect()->to('/empresas')->with('error', 'Empresa no disponible.');
        }

        $newStatus = (int) $company['active'] === 1 ? 0 : 1;

        (new CompanyModel())->update($id, ['active' => $newStatus]);
        $this->syncCompanyUsersStatus($id, $newStatus);

        return redirect()->to('/empresas')->with('message', $newStatus === 0 ? 'Empresa deshabilitada y usuarios desactivados.' : 'Empresa habilitada correctamente.');
    }

    private function rules(): array
    {
        return [
            'name' => 'required|min_length[3]|max_length[150]',
            'legal_name' => 'permit_empty|max_length[180]',
            'tax_id' => 'permit_empty|max_length[30]',
            'email' => 'permit_empty|valid_email|max_length[150]',
            'phone' => 'permit_empty|max_length[40]',
            'currency_code' => 'required|max_length[10]',
            'active' => 'permit_empty|in_list[0,1]',
        ];
    }

    private function payload(): array
    {
        return [
            'name' => trim((string) $this->request->getPost('name')),
            'legal_name' => trim((string) $this->request->getPost('legal_name')),
            'tax_id' => trim((string) $this->request->getPost('tax_id')),
            'email' => trim((string) $this->request->getPost('email')),
            'phone' => trim((string) $this->request->getPost('phone')),
            'address' => trim((string) $this->request->getPost('address')),
            'currency_code' => trim((string) $this->request->getPost('currency_code')),
            'active' => $this->request->getPost('active') === '0' ? 0 : 1,
        ];
    }

    private function findManagedCompany(string $id): ?array
    {
        $company = (new CompanyModel())->find($id);

        if (! $company) {
            return null;
        }

        if ($this->isSuperadmin()) {
            return $company;
        }

        return $company['id'] === $this->companyId() ? $company : null;
    }

    private function ensureMainBranch(string $companyId): void
    {
        (new BranchModel())->ensureMainBranch($companyId);
    }

    private function ensureBaseCurrency(string $companyId, string $currencyCode): void
    {
        $currencyModel = new CurrencyModel();
        $existing = $currencyModel
            ->where('company_id', $companyId)
            ->where('code', $currencyCode)
            ->first();

        if ($existing) {
            $currencyModel
                ->where('company_id', $companyId)
                ->set(['is_default' => 0])
                ->update();

            $currencyModel->update($existing['id'], [
                'is_default' => 1,
                'active' => 1,
            ]);

            return;
        }

        $template = $currencyModel
            ->select('code, name, symbol, exchange_rate')
            ->where('code', $currencyCode)
            ->orderBy('active', 'DESC')
            ->first();

        $currencyModel
            ->where('company_id', $companyId)
            ->set(['is_default' => 0])
            ->update();

        $currencyModel->insert([
            'company_id' => $companyId,
            'code' => $currencyCode,
            'name' => $template['name'] ?? $currencyCode,
            'symbol' => $template['symbol'] ?? '',
            'exchange_rate' => $template['exchange_rate'] ?? '1.0000',
            'is_default' => 1,
            'active' => 1,
        ]);
    }

    private function syncCompanyUsersStatus(string $companyId, int $status): void
    {
        (new UserModel())
            ->where('company_id', $companyId)
            ->set(['active' => $status])
            ->update();
    }
}
