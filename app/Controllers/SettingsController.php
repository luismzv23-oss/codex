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

        $db = db_connect();
        $row = $db->table('company_settings')
            ->where('company_id', $companyId)
            ->where('key', 'max_cash_registers')
            ->get()->getRowArray();
        $maxCashRegisters = $row ? (int) $row['value'] : 10;

        return view('settings/index', [
            'pageTitle' => 'Configuracion',
            'company' => $companyModel->find($companyId),
            'companies' => $this->isSuperadmin() ? $companyModel->orderBy('name', 'ASC')->findAll() : [],
            'branches' => $branchModel->where('company_id', $companyId)->orderBy('name', 'ASC')->findAll(),
            'currencies' => $currencyModel->where('company_id', $companyId)->orderBy('code', 'ASC')->findAll(),
            'taxes' => $taxModel->where('company_id', $companyId)->orderBy('name', 'ASC')->findAll(),
            'voucherSequences' => $voucherModel->where('company_id', $companyId)->orderBy('document_type', 'ASC')->findAll(),
            'maxCashRegisters' => $maxCashRegisters,
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

        $maxCashRegisters = (int) $this->request->getPost('max_cash_registers');
        if ($maxCashRegisters <= 0) {
            $maxCashRegisters = 10;
        }

        $db = db_connect();
        $existingSetting = $db->table('company_settings')
            ->where('company_id', $companyId)
            ->where('key', 'max_cash_registers')
            ->get()->getRowArray();

        if ($existingSetting) {
            $db->table('company_settings')
                ->where('id', $existingSetting['id'])
                ->update([
                    'value' => (string) $maxCashRegisters,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        } else {
            $db->table('company_settings')->insert([
                'id' => app_uuid(),
                'company_id' => $companyId,
                'key' => 'max_cash_registers',
                'value' => (string) $maxCashRegisters,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

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

        $db = db_connect();
        $row = $db->table('company_settings')
            ->where('company_id', $companyId)
            ->where('key', 'max_cash_registers')
            ->get()->getRowArray();
        $maxCashRegisters = $row ? (int) $row['value'] : 10;

        return view('settings/forms/company', [
            'pageTitle' => 'Editar datos de empresa',
            'company' => $company,
            'currencyOptions' => $this->companyCurrencyOptions($companyId, $company['currency_code'] ?? null),
            'maxCashRegisters' => $maxCashRegisters,
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

    public function ticketSettingsForm()
    {
        $companyId = $this->resolveCompanyId();
        $company = $companyId ? (new CompanyModel())->find($companyId) : null;

        if (! $companyId || ! $company) {
            return redirect()->to('/configuracion')->with('error', 'Empresa no disponible.');
        }

        $db = db_connect();
        $rawSettings = $db->table('company_settings')
            ->where('company_id', $companyId)
            ->like('key', 'ticket_', 'after')
            ->get()->getResultArray();

        $settings = [];
        foreach ($rawSettings as $s) {
            $settings[$s['key']] = $s['value'];
        }

        $defaults = [
            'header_title' => '',
            'company_subtitle' => '',
            'company_address' => '',
            'company_phone' => '',
            'footer_notes' => '',
            'paper_width' => '80mm',
            'font_size' => 'medium',
            'font_family' => 'DejaVu Sans',
            'bold_top_left' => 1,
            'bold_top_right' => 0,
            'custom_text_top_left' => 'IVA: Responsable Inscripto',
            'custom_text_top_right' => "Ing. Brutos: CM. 901-111111-0\nInicio de Actividades: 01/04/1994",
            'custom_text_bottom_left' => "Imprenta Su Imprenta CUIT: 30-12345678-9 Habil. 22222",
            'custom_text_bottom_right' => "Fecha Impresión: " . date('d/m/Y') . " Numeración: 0001-00001601 al 0001-00001700",
            'show_sku' => 1,
            'show_brand' => 1,
            'show_item_breakdown' => 1,
            'show_customer' => 1,
            'show_user' => 1,
        ];

        $posSettings = [];
        $kioskSettings = [];

        foreach ($defaults as $subKey => $defaultVal) {
            $posKey = 'ticket_pos_' . $subKey;
            $kioskKey = 'ticket_kiosk_' . $subKey;
            $legacyKey = 'ticket_' . $subKey;

            // POS
            if (array_key_exists($posKey, $settings)) {
                $posSettings[$subKey] = $settings[$posKey];
            } elseif (array_key_exists($legacyKey, $settings)) {
                $val = $settings[$legacyKey];
                if ($subKey === 'paper_width') {
                    $posSettings[$subKey] = (strtolower($val) === 'letter' || strtolower($val) === 'carta') ? 'letter' : 'A4';
                } else {
                    $posSettings[$subKey] = $val;
                }
            } else {
                $posSettings[$subKey] = ($subKey === 'paper_width') ? 'A4' : $defaultVal;
            }

            // Kiosk
            if (array_key_exists($kioskKey, $settings)) {
                $kioskSettings[$subKey] = $settings[$kioskKey];
            } elseif (array_key_exists($legacyKey, $settings)) {
                $val = $settings[$legacyKey];
                if ($subKey === 'paper_width') {
                    $kioskSettings[$subKey] = (strtolower($val) === '58mm' || strtolower($val) === '80mm') ? $val : '80mm';
                } else {
                    $kioskSettings[$subKey] = $val;
                }
            } else {
                $kioskSettings[$subKey] = ($subKey === 'paper_width') ? '80mm' : $defaultVal;
            }
        }

        return view('settings/forms/tickets', [
            'pageTitle' => 'Configuracion de Impresion y Tickets',
            'posSettings' => $posSettings,
            'kioskSettings' => $kioskSettings,
            'companyId' => $companyId,
            'companyName' => $company['name'],
            'companyLegalName' => $company['legal_name'] ?? $company['name'],
            'companyAddress' => $company['address'] ?? '',
            'companyPhone' => $company['phone'] ?? '',
            'formAction' => site_url('configuracion/tickets'),
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function updateTicketSettings()
    {
        $companyId = $this->resolveCompanyId();
        $company = $companyId ? (new CompanyModel())->find($companyId) : null;

        if (! $companyId || ! $company) {
            return redirect()->to('/configuracion')->with('error', 'Empresa no disponible.');
        }

        $currentUser = auth_user();
        $isAdminOrSuperadmin = in_array($currentUser['role_slug'] ?? null, ['admin', 'superadmin'], true);

        $db = db_connect();
        $subKeys = [
            'header_title',
            'company_subtitle',
            'company_address',
            'company_phone',
            'footer_notes',
            'paper_width',
            'font_size',
            'font_family',
            'bold_top_left',
            'bold_top_right',
            'custom_text_top_left',
            'custom_text_top_right',
            'custom_text_bottom_left',
            'custom_text_bottom_right',
            'show_sku',
            'show_brand',
            'show_item_breakdown',
            'show_customer',
            'show_user',
        ];

        $prefixes = ['ticket_pos_', 'ticket_kiosk_'];

        foreach ($prefixes as $prefix) {
            foreach ($subKeys as $subKey) {
                $key = $prefix . $subKey;

                // Restrict custom fields and font family modification to admin and superadmin roles
                if (!$isAdminOrSuperadmin && in_array($subKey, ['custom_text_top_left', 'custom_text_top_right', 'bold_top_left', 'bold_top_right', 'font_family'], true)) {
                    continue;
                }

                if (in_array($subKey, ['show_sku', 'show_brand', 'show_item_breakdown', 'show_customer', 'show_user', 'bold_top_left', 'bold_top_right'], true)) {
                    $value = $this->request->getPost($key) === '1' ? '1' : '0';
                } else {
                    $rawVal = $this->request->getPost($key);
                    if ($rawVal === null) {
                        continue;
                    }
                    $value = trim((string) $rawVal);
                }

                $existing = $db->table('company_settings')
                    ->where('company_id', $companyId)
                    ->where('key', $key)
                    ->get()->getRowArray();

                if ($existing) {
                    $db->table('company_settings')
                        ->where('id', $existing['id'])
                        ->update([
                            'value' => $value,
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                } else {
                    $db->table('company_settings')->insert([
                        'id' => app_uuid(),
                        'company_id' => $companyId,
                        'key' => $key,
                        'value' => $value,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }

        return $this->popupOrRedirect('/configuracion?company_id=' . $companyId, 'Configuracion de impresion de tickets actualizada correctamente.');
    }
}
