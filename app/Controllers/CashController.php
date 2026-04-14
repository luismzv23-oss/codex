<?php

namespace App\Controllers;

use App\Libraries\CashService;
use App\Models\CashRegisterModel;
use App\Models\CompanyModel;
use App\Models\CompanySystemModel;
use App\Models\CustomerModel;
use App\Models\SupplierModel;
use App\Models\SystemModel;
use App\Models\UserSystemModel;
use CodeIgniter\HTTP\RedirectResponse;

class CashController extends BaseController
{
    public function index()
    {
        $context = $this->cashContext('view');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $service = $this->cashService();

        return view('cash/index', [
            'pageTitle' => 'Caja y Tesoreria',
            'context' => $context,
            'companies' => $this->cashCompanies(),
            'selectedCompanyId' => $context['company']['id'],
            'summary' => $service->summary($context['company']['id']),
            'registers' => $service->registerRows($context['company']['id']),
            'sessions' => $service->activeSessions($context['company']['id']),
            'movements' => $service->recentMovements($context['company']['id']),
            'paymentMethods' => $service->paymentMethodBreakdown($context['company']['id']),
            'gateways' => $service->gatewayRows($context['company']['id']),
            'checks' => $service->checkRows($context['company']['id']),
            'reconciliations' => $service->reconciliationRows($context['company']['id']),
        ]);
    }

    public function openSessionForm()
    {
        $context = $this->cashContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        return view('cash/forms/open_session', [
            'pageTitle' => 'Apertura de caja',
            'companyId' => $context['company']['id'],
            'registers' => $this->cashService()->registerRows($context['company']['id']),
            'formAction' => site_url('caja/sesiones/apertura'),
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function storeOpenSession()
    {
        $context = $this->cashContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $companyId = $context['company']['id'];
        $registerId = trim((string) $this->request->getPost('cash_register_id'));
        $register = (new CashRegisterModel())->where('company_id', $companyId)->where('id', $registerId)->where('active', 1)->first();
        if (! $register) {
            return redirect()->back()->withInput()->with('error', 'Debes seleccionar una caja valida.');
        }

        $sessionId = $this->cashService()->openSession(
            $companyId,
            $registerId,
            $this->currentUser()['id'],
            (float) $this->request->getPost('opening_amount'),
            trim((string) $this->request->getPost('notes'))
        );

        if (! $sessionId) {
            return redirect()->back()->withInput()->with('error', 'La caja seleccionada ya tiene una sesion abierta.');
        }

        return $this->popupOrRedirect($this->cashRoute('caja', $companyId), 'Caja abierta correctamente.');
    }

    public function closeSessionForm(string $id)
    {
        $context = $this->cashContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $session = $this->cashService()->ownedSession($context['company']['id'], $id);
        if (! $session) {
            return redirect()->to($this->cashRoute('caja', $context['company']['id']))->with('error', 'La sesion de caja no existe.');
        }

        return view('cash/forms/close_session', [
            'pageTitle' => 'Cierre de caja',
            'companyId' => $context['company']['id'],
            'session' => $session,
            'expectedAmount' => $this->cashService()->expectedBalance($id, (float) ($session['opening_amount'] ?? 0)),
            'formAction' => site_url('caja/sesiones/' . $id . '/cierre'),
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function storeCloseSession(string $id)
    {
        $context = $this->cashContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $closed = $this->cashService()->closeSession(
            $context['company']['id'],
            $id,
            $this->currentUser()['id'],
            (float) $this->request->getPost('actual_closing_amount'),
            trim((string) $this->request->getPost('notes'))
        );

        if (! $closed) {
            return redirect()->back()->withInput()->with('error', 'No se pudo cerrar la sesion seleccionada.');
        }

        return $this->popupOrRedirect($this->cashRoute('caja', $context['company']['id']), 'Caja cerrada correctamente.');
    }

    public function createMovementForm()
    {
        $context = $this->cashContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        return view('cash/forms/movement', [
            'pageTitle' => 'Movimiento de caja',
            'companyId' => $context['company']['id'],
            'sessions' => $this->cashService()->activeSessions($context['company']['id']),
            'formAction' => site_url('caja/movimientos'),
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function createCheckForm()
    {
        $context = $this->cashContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        return view('cash/forms/check', [
            'pageTitle' => 'Cheque',
            'companyId' => $context['company']['id'],
            'suppliers' => (new SupplierModel())->where('company_id', $context['company']['id'])->where('active', 1)->orderBy('name', 'ASC')->findAll(),
            'customers' => (new CustomerModel())->where('company_id', $context['company']['id'])->where('active', 1)->orderBy('name', 'ASC')->findAll(),
            'formAction' => site_url('caja/cheques'),
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function storeCheck()
    {
        $context = $this->cashContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $checkNumber = trim((string) $this->request->getPost('check_number'));
        $bankName = trim((string) $this->request->getPost('bank_name'));
        $amount = (float) $this->request->getPost('amount');
        if ($checkNumber === '' || $bankName === '' || $amount <= 0) {
            return redirect()->back()->withInput()->with('error', 'Debes completar numero, banco y monto del cheque.');
        }

        $this->cashService()->createCheck([
            'company_id' => $context['company']['id'],
            'supplier_id' => trim((string) $this->request->getPost('supplier_id')) ?: null,
            'customer_id' => trim((string) $this->request->getPost('customer_id')) ?: null,
            'check_type' => trim((string) $this->request->getPost('check_type')) ?: 'received',
            'check_number' => $checkNumber,
            'bank_name' => $bankName,
            'issuer_name' => trim((string) $this->request->getPost('issuer_name')),
            'due_date' => trim((string) $this->request->getPost('due_date')) ?: null,
            'amount' => $amount,
            'status' => trim((string) $this->request->getPost('status')) ?: 'portfolio',
            'notes' => trim((string) $this->request->getPost('notes')),
            'created_by' => $this->currentUser()['id'],
        ]);

        return $this->popupOrRedirect($this->cashRoute('caja', $context['company']['id']), 'Cheque registrado correctamente.');
    }

    public function createReconciliationForm()
    {
        $context = $this->cashContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        return view('cash/forms/reconciliation', [
            'pageTitle' => 'Conciliacion de caja',
            'companyId' => $context['company']['id'],
            'sessions' => $this->cashService()->activeSessions($context['company']['id']),
            'formAction' => site_url('caja/conciliaciones'),
            'isPopup' => $this->isPopupRequest(),
        ]);
    }

    public function storeReconciliation()
    {
        $context = $this->cashContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $sessionId = trim((string) $this->request->getPost('cash_session_id'));
        $paymentMethod = trim((string) $this->request->getPost('payment_method'));
        $expected = (float) $this->request->getPost('expected_amount');
        $actual = (float) $this->request->getPost('actual_amount');
        if ($sessionId === '' || $paymentMethod === '') {
            return redirect()->back()->withInput()->with('error', 'Debes seleccionar una sesion y un medio.');
        }

        $this->cashService()->createReconciliation([
            'company_id' => $context['company']['id'],
            'cash_session_id' => $sessionId,
            'payment_method' => $paymentMethod,
            'expected_amount' => $expected,
            'actual_amount' => $actual,
            'notes' => trim((string) $this->request->getPost('notes')),
            'created_by' => $this->currentUser()['id'],
        ]);

        return $this->popupOrRedirect($this->cashRoute('caja', $context['company']['id']), 'Conciliacion registrada correctamente.');
    }

    public function storeMovement()
    {
        $context = $this->cashContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $sessionId = trim((string) $this->request->getPost('cash_session_id'));
        $session = $this->cashService()->ownedSession($context['company']['id'], $sessionId);
        if (! $session) {
            return redirect()->back()->withInput()->with('error', 'Debes seleccionar una sesion abierta valida.');
        }

        $movementType = trim((string) $this->request->getPost('movement_type')) ?: 'manual_income';
        $amount = (float) $this->request->getPost('amount');
        if ($amount <= 0) {
            return redirect()->back()->withInput()->with('error', 'El monto debe ser mayor a cero.');
        }

        $signedAmount = in_array($movementType, ['manual_expense', 'withdrawal'], true) ? -1 * $amount : $amount;
        $movementId = $this->cashService()->registerMovement([
            'company_id' => $context['company']['id'],
            'cash_register_id' => $session['cash_register_id'],
            'cash_session_id' => $session['id'],
            'movement_type' => $movementType,
            'payment_method' => trim((string) $this->request->getPost('payment_method')) ?: null,
            'amount' => $signedAmount,
            'reference_type' => 'cash_manual',
            'reference_number' => trim((string) $this->request->getPost('reference_number')),
            'occurred_at' => trim((string) $this->request->getPost('occurred_at')) ?: date('Y-m-d H:i:s'),
            'notes' => trim((string) $this->request->getPost('notes')),
            'created_by' => $this->currentUser()['id'],
        ]);

        if (! $movementId) {
            return redirect()->back()->withInput()->with('error', 'No se pudo registrar el movimiento.');
        }

        return $this->popupOrRedirect($this->cashRoute('caja', $context['company']['id']), 'Movimiento de caja registrado.');
    }

    private function cashContext(string $requiredAccess = 'view')
    {
        $companyId = $this->resolveCashCompanyId();
        if (! $companyId) {
            return redirect()->to('/sistemas')->with('error', 'Debes seleccionar una empresa para operar Caja.');
        }

        $company = (new CompanyModel())->find($companyId);
        if (! $company) {
            return redirect()->to('/sistemas')->with('error', 'La empresa seleccionada no existe.');
        }

        $system = (new SystemModel())->where('slug', 'caja')->first();
        if (! $system || (int) ($system['active'] ?? 0) !== 1) {
            return redirect()->to('/sistemas')->with('error', 'El sistema Caja no esta disponible.');
        }

        $accessLevel = 'view';
        if (! $this->isSuperadmin()) {
            $companyAssignment = (new CompanySystemModel())->where('company_id', $companyId)->where('system_id', $system['id'])->where('active', 1)->first();
            if (! $companyAssignment) {
                return redirect()->to('/sistemas')->with('error', 'La empresa no tiene Caja asignado.');
            }

            $userAssignment = (new UserSystemModel())
                ->where('company_id', $companyId)
                ->where('user_id', $this->currentUser()['id'] ?? '')
                ->where('system_id', $system['id'])
                ->where('active', 1)
                ->first();
            if (! $userAssignment) {
                return redirect()->to('/sistemas')->with('error', 'Tu usuario no tiene acceso activo a Caja.');
            }

            $accessLevel = $userAssignment['access_level'] ?? 'view';
        }

        if ($requiredAccess === 'manage' && ! $this->isSuperadmin() && $accessLevel !== 'manage') {
            return redirect()->to($this->cashRoute('caja', $companyId))->with('error', 'Tu usuario solo tiene acceso de consulta en Caja.');
        }

        $this->cashService()->ensureDefaults($companyId, $this->currentUser()['branch_id'] ?? null);

        return [
            'company' => $company,
            'system' => $system,
            'access_level' => $accessLevel,
            'canManage' => $this->isSuperadmin() || $accessLevel === 'manage',
        ];
    }

    private function resolveCashCompanyId(): ?string
    {
        if ($this->isSuperadmin()) {
            $companyId = trim((string) ($this->request->getGet('company_id') ?: $this->request->getPost('company_id')));
            if ($companyId !== '') {
                return $companyId;
            }
            $company = (new CompanyModel())->orderBy('name', 'ASC')->first();
            return $company['id'] ?? null;
        }

        return $this->companyId();
    }

    private function cashCompanies(): array
    {
        if (! $this->isSuperadmin()) {
            $company = (new CompanyModel())->find($this->companyId());
            return $company ? [$company] : [];
        }

        return (new CompanyModel())->where('active', 1)->orderBy('name', 'ASC')->findAll();
    }

    private function cashRoute(string $path, string $companyId): string
    {
        if ($this->isSuperadmin()) {
            return site_url($path . '?company_id=' . $companyId);
        }

        return site_url($path);
    }

    private function cashService(): CashService
    {
        return new CashService();
    }
}
