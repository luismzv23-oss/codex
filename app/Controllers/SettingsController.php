<?php

namespace App\Controllers;

use App\Models\BranchModel;
use App\Models\CompanySystemModel;
use App\Models\CompanyModel;
use App\Models\CurrencyModel;
use App\Models\RoleModel;
use App\Models\TaxModel;
use App\Models\UserModel;
use App\Models\UserSystemModel;
use App\Models\VoucherSequenceModel;

class SettingsController extends BaseController
{
    public function index()
    {
        $companyId = $this->resolveCompanyId();
        $companyModel = new CompanyModel();

        if (! $companyId && $this->isSuperadmin()) {
            $firstCompany = $companyModel->orderBy('name', 'ASC')->first();
            $companyId = $firstCompany['id'] ?? null;
        }

        if (! $companyId) {
            return redirect()->to('/dashboard')->with('error', 'No tienes una empresa asignada para configurar.');
        }

        $branchModel = new BranchModel();
        $branchModel->ensureMainBranch($companyId);
        $currencyModel = new CurrencyModel();
        $taxModel = new TaxModel();
        $voucherModel = new VoucherSequenceModel();

        return view('settings/index', [
            'pageTitle' => 'Configuracion',
            'company' => $companyModel->find($companyId),
            'companies' => $this->isSuperadmin() ? $companyModel->orderBy('name', 'ASC')->findAll() : [],
            'branches' => $branchModel->where('company_id', $companyId)->orderBy('name', 'ASC')->findAll(),
            'currencies' => $currencyModel->where('company_id', $companyId)->orderBy('code', 'ASC')->findAll(),
            'taxes' => $taxModel->where('company_id', $companyId)->orderBy('name', 'ASC')->findAll(),
            'voucherSequences' => $voucherModel->where('company_id', $companyId)->orderBy('document_type', 'ASC')->findAll(),
        ]);
    }

    public function updateCompany()
    {
        $companyId = $this->resolveCompanyId();
        $company = $companyId ? (new CompanyModel())->find($companyId) : null;

        if (! $companyId || ! $company) {
            return redirect()->to('/configuracion')->with('error', 'Empresa no disponible.');
        }

        $currencyCode = trim((string) $this->request->getPost('currency_code'));

        if (! $this->isAllowedCurrencyCode($currencyCode, $companyId, $company['currency_code'] ?? null)) {
            return redirect()->back()->withInput()->with('error', 'La moneda base seleccionada no pertenece a las monedas activas de la empresa.');
        }

        (new CompanyModel())->update($companyId, [
            'name' => trim((string) $this->request->getPost('name')),
            'legal_name' => trim((string) $this->request->getPost('legal_name')),
            'tax_id' => trim((string) $this->request->getPost('tax_id')),
            'email' => trim((string) $this->request->getPost('email')),
            'phone' => trim((string) $this->request->getPost('phone')),
            'address' => trim((string) $this->request->getPost('address')),
            'currency_code' => trim((string) $this->request->getPost('currency_code')),
        ]);

        return $this->popupOrRedirect('/configuracion?company_id=' . $companyId, 'Datos de la empresa actualizados.');
    }

    public function storeBranch()
    {
        $companyId = $this->resolveCompanyId();
        (new BranchModel())->insert([
            'company_id' => $companyId,
            'name' => trim((string) $this->request->getPost('name')),
            'code' => trim((string) $this->request->getPost('code')),
            'address' => trim((string) $this->request->getPost('address')),
            'phone' => trim((string) $this->request->getPost('phone')),
            'active' => $this->request->getPost('active') === '0' ? 0 : 1,
        ]);

        $this->syncAdminSystemAssignments($companyId);

        return $this->popupOrRedirect('/configuracion?company_id=' . $companyId, 'Sucursal registrada.');
    }

    public function storeTax()
    {
        (new TaxModel())->insert([
            'company_id' => $this->resolveCompanyId(),
            'name' => trim((string) $this->request->getPost('name')),
            'code' => trim((string) $this->request->getPost('code')),
            'rate' => (float) $this->request->getPost('rate'),
            'active' => $this->request->getPost('active') === '0' ? 0 : 1,
        ]);

        return $this->popupOrRedirect('/configuracion?company_id=' . $this->resolveCompanyId(), 'Impuesto registrado.');
    }

    public function storeCurrency()
    {
        (new CurrencyModel())->insert([
            'company_id' => $this->resolveCompanyId(),
            'code' => strtoupper(trim((string) $this->request->getPost('code'))),
            'name' => trim((string) $this->request->getPost('name')),
            'symbol' => trim((string) $this->request->getPost('symbol')),
            'exchange_rate' => (float) $this->request->getPost('exchange_rate'),
            'is_default' => $this->request->getPost('is_default') === '1' ? 1 : 0,
            'active' => $this->request->getPost('active') === '0' ? 0 : 1,
        ]);

        return $this->popupOrRedirect('/configuracion?company_id=' . $this->resolveCompanyId(), 'Moneda registrada.');
    }

    public function storeVoucherSequence()
    {
        (new VoucherSequenceModel())->insert([
            'company_id' => $this->resolveCompanyId(),
            'branch_id' => $this->request->getPost('branch_id') ?: null,
            'document_type' => trim((string) $this->request->getPost('document_type')),
            'prefix' => trim((string) $this->request->getPost('prefix')),
            'current_number' => (int) $this->request->getPost('current_number'),
            'active' => $this->request->getPost('active') === '0' ? 0 : 1,
        ]);

        return $this->popupOrRedirect('/configuracion?company_id=' . $this->resolveCompanyId(), 'Numeracion registrada.');
    }

    public function editCompanyForm()
    {
        $companyId = $this->resolveCompanyId();
        $company = (new CompanyModel())->find($companyId);

        return view('settings/forms/company', [
            'pageTitle' => 'Editar datos de empresa',
            'company' => $company,
            'currencyOptions' => $this->companyCurrencyOptions($companyId, $company['currency_code'] ?? null),
            'formAction' => site_url('configuracion/empresa'),
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function createBranchForm()
    {
        return view('settings/forms/branch', [
            'pageTitle' => 'Nueva sucursal',
            'companyId' => $this->resolveCompanyId(),
            'formAction' => site_url('configuracion/sucursales'),
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function createTaxForm()
    {
        return view('settings/forms/tax', [
            'pageTitle' => 'Nuevo impuesto',
            'companyId' => $this->resolveCompanyId(),
            'formAction' => site_url('configuracion/impuestos'),
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function createCurrencyForm()
    {
        return view('settings/forms/currency', [
            'pageTitle' => 'Nueva moneda',
            'companyId' => $this->resolveCompanyId(),
            'formAction' => site_url('configuracion/monedas'),
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function createVoucherSequenceForm()
    {
        return view('settings/forms/voucher_sequence', [
            'pageTitle' => 'Nueva numeracion',
            'companyId' => $this->resolveCompanyId(),
            'branches' => (new BranchModel())->where('company_id', $this->resolveCompanyId())->orderBy('name', 'ASC')->findAll(),
            'formAction' => site_url('configuracion/numeraciones'),
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    private function resolveCompanyId(): ?string
    {
        if ($this->isSuperadmin()) {
            $fromPost = trim((string) $this->request->getPost('company_id'));
            $fromQuery = trim((string) $this->request->getGet('company_id'));

            if ($fromPost !== '') {
                return $fromPost;
            }

            if ($fromQuery !== '') {
                return $fromQuery;
            }
        }

        return $this->companyId();
    }

    private function syncAdminSystemAssignments(?string $companyId): void
    {
        if (! $companyId) {
            return;
        }

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

        $assignments = (new CompanySystemModel())
            ->where('company_id', $companyId)
            ->where('active', 1)
            ->findAll();

        $userSystemModel = new UserSystemModel();
        foreach ($assignments as $assignment) {
            $existing = $userSystemModel
                ->where('company_id', $companyId)
                ->where('user_id', $adminUser['id'])
                ->where('system_id', $assignment['system_id'])
                ->first();

            if ($existing) {
                $userSystemModel->update($existing['id'], [
                    'access_level' => 'manage',
                    'active' => 1,
                ]);
                continue;
            }

            $userSystemModel->insert([
                'company_id' => $companyId,
                'user_id' => $adminUser['id'],
                'system_id' => $assignment['system_id'],
                'access_level' => 'manage',
                'active' => 1,
            ]);
        }
    }
}
