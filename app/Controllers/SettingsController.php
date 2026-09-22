<?php

namespace App\Controllers;

use App\Models\BranchModel;
use App\Models\CompanyModel;
use App\Models\CurrencyModel;
use App\Models\TaxModel;
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
        try {
            $companyId = (string) $this->resolveCompanyId();
            $service = new \App\Libraries\SettingsService();
            $result = $service->company($companyId, (array) $this->request->getPost());
            return $this->popupOrRedirect('/configuracion?company_id=' . $companyId, 'Configuracion guardada correctamente.');
        } catch (\Throwable $e) {
            log_message('error', 'Configuracion: {message}', ['message' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function storeBranch()
    {
        try {
            $companyId = (string) $this->resolveCompanyId();
            $service = new \App\Libraries\SettingsService();
            $result = $service->branch($companyId, (array) $this->request->getPost());
            return $this->popupOrRedirect('/configuracion?company_id=' . $companyId, 'Configuracion guardada correctamente.');
        } catch (\Throwable $e) {
            log_message('error', 'Configuracion: {message}', ['message' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function storeTax()
    {
        try {
            $companyId = (string) $this->resolveCompanyId();
            $service = new \App\Libraries\SettingsService();
            $result = $service->tax($companyId, (array) $this->request->getPost());
            return $this->popupOrRedirect('/configuracion?company_id=' . $companyId, 'Configuracion guardada correctamente.');
        } catch (\Throwable $e) {
            log_message('error', 'Configuracion: {message}', ['message' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function editTaxForm(string $id)
    {
        $companyId = $this->resolveCompanyId();
        $taxModel  = new TaxModel();
        $tax       = $taxModel->where('company_id', $companyId)->find($id);

        if (! $tax) {
            return redirect()->to('/configuracion?company_id=' . $companyId)->with('error', 'Impuesto no encontrado.');
        }

        return view('settings/forms/tax', [
            'pageTitle'  => 'Editar impuesto',
            'companyId'  => $companyId,
            'tax'        => $tax,
            'formAction' => site_url('configuracion/impuestos/' . $id . '/actualizar'),
            'isPopup'    => $this->isPopupRequest(),
        ]);
    }

    public function updateTax(string $id)
    {
        try {
            $companyId = (string) $this->resolveCompanyId();
            $service = new \App\Libraries\SettingsService();
            $result = $service->tax($companyId, (array) $this->request->getPost(), $id);
            return $this->popupOrRedirect('/configuracion?company_id=' . $companyId, 'Configuracion guardada correctamente.');
        } catch (\Throwable $e) {
            log_message('error', 'Configuracion: {message}', ['message' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function setDefaultTax(string $id)
    {
        try {
            $companyId = (string) $this->resolveCompanyId();
            $service = new \App\Libraries\SettingsService();
            $service->taxAction($companyId, $id, 'default');
            return $this->popupOrRedirect('/configuracion?company_id=' . $companyId, 'Configuracion guardada correctamente.');
        } catch (\Throwable $e) {
            log_message('error', 'Configuracion: {message}', ['message' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function deleteTax(string $id)
    {
        try {
            $companyId = (string) $this->resolveCompanyId();
            $service = new \App\Libraries\SettingsService();
            $service->taxAction($companyId, $id, 'delete');
            return $this->popupOrRedirect('/configuracion?company_id=' . $companyId, 'Configuracion guardada correctamente.');
        } catch (\Throwable $e) {
            log_message('error', 'Configuracion: {message}', ['message' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function toggleTax(string $id)
    {
        try {
            $companyId = (string) $this->resolveCompanyId();
            $service = new \App\Libraries\SettingsService();
            $service->taxAction($companyId, $id, 'toggle');
            return $this->popupOrRedirect('/configuracion?company_id=' . $companyId, 'Configuracion guardada correctamente.');
        } catch (\Throwable $e) {
            log_message('error', 'Configuracion: {message}', ['message' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function storeCurrency()
    {
        try {
            $companyId = (string) $this->resolveCompanyId();
            $service = new \App\Libraries\SettingsService();
            $result = $service->currency($companyId, (array) $this->request->getPost());
            return $this->popupOrRedirect('/configuracion?company_id=' . $companyId, 'Configuracion guardada correctamente.');
        } catch (\Throwable $e) {
            log_message('error', 'Configuracion: {message}', ['message' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function storeVoucherSequence()
    {
        try {
            $companyId = (string) $this->resolveCompanyId();
            $service = new \App\Libraries\SettingsService();
            $result = $service->sequence($companyId, (array) $this->request->getPost());
            return $this->popupOrRedirect('/configuracion?company_id=' . $companyId, 'Configuracion guardada correctamente.');
        } catch (\Throwable $e) {
            log_message('error', 'Configuracion: {message}', ['message' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
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
        $settings = [
            'max_cash_registers' => $row['value'] ?? '0'
        ];

        return view('settings/forms/company', [
            'pageTitle' => 'Editar datos de empresa',
            'company' => $company,
            'currencyOptions' => $this->companyCurrencyOptions($companyId, $company['currency_code'] ?? null),
            'formAction' => site_url('configuracion/empresa'),
            'isPopup' => $this->isPopupRequest(),
            'settings' => $settings,
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
        try {
            $companyId = (string) $this->resolveCompanyId();
            $service = new \App\Libraries\SettingsService();
            $service->tickets($companyId, (array) $this->request->getPost(), in_array($this->currentUser()['role_slug'] ?? '', ['admin', 'superadmin'], true));
            return $this->popupOrRedirect('/configuracion?company_id=' . $companyId, 'Configuracion guardada correctamente.');
        } catch (\Throwable $e) {
            log_message('error', 'Configuracion: {message}', ['message' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

}
