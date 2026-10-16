<?php

namespace App\Controllers\Api\V1;

use App\Libraries\CashService;
use App\Models\CashRegisterModel;
use App\Models\CompanyModel;
use App\Models\CompanySystemModel;
use App\Models\SystemModel;
use App\Models\UserSystemModel;

class CashController extends BaseApiController
{
    public function index()
    {
        $context = $this->cashContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $service = $this->cashService();

        return $this->success([
            'company' => $context['company'],
            'access_level' => $context['access_level'],
            'summary' => $service->summary($context['company']['id']),
            'registers' => $service->registerRows($context['company']['id']),
            'sessions' => $service->activeSessions($context['company']['id']),
            'movements' => $service->recentMovements($context['company']['id']),
            'payment_methods' => $service->paymentMethodBreakdown($context['company']['id']),
            'gateways' => $service->gatewayRows($context['company']['id']),
            'checks' => $service->checkRows($context['company']['id']),
            'reconciliations' => $service->reconciliationRows($context['company']['id']),
        ]);
    }

    public function registers()
    {
        $context = $this->cashContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        return $this->success($this->cashService()->registerRows($context['company']['id']));
    }

    public function sessions()
    {
        $context = $this->cashContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        return $this->success($this->cashService()->activeSessions($context['company']['id']));
    }

    public function openSession()
    {
        $context = $this->cashContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $payload = (array) ($this->request->getJSON(true) ?: $this->request->getPost());
        $registerId = trim((string) ($payload['cash_register_id'] ?? ''));
        $register = (new CashRegisterModel())->where('company_id', $context['company']['id'])->where('id', $registerId)->where('active', 1)->first();
        if (! $register) {
            return $this->fail('Debes seleccionar una caja valida.', 422);
        }

        $sessionId = $this->cashService()->openSession(
            $context['company']['id'],
            $registerId,
            $this->apiUser()['id'],
            (float) ($payload['opening_amount'] ?? 0),
            trim((string) ($payload['notes'] ?? ''))
        );

        if (! $sessionId) {
            return $this->fail('La caja seleccionada ya tiene una sesion abierta.', 422);
        }

        return $this->success($this->cashService()->ownedSession($context['company']['id'], $sessionId), 201);
    }

    public function closeSession(string $id)
    {
        $context = $this->cashContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $payload = (array) ($this->request->getJSON(true) ?: $this->request->getPost());
        try {
            $closed = $this->cashService()->closeSession(
                $context['company']['id'],
                $id,
                $this->apiUser()['id'],
                (float) ($payload['actual_closing_amount'] ?? 0),
                trim((string) ($payload['notes'] ?? '')),
                true
            );

            return $closed
                ? $this->success(['session_id' => $id, 'status' => 'closed'])
                : $this->fail('No se pudo cerrar la sesion seleccionada.', 422);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    public function storeMovement()
    {
        $context=$this->cashContext('manage');
        if (isset($context['error'])) return $this->fail($context['error'],$context['status']);
        try {
            $id=(new \App\Libraries\TreasuryPaymentService())->manual($this->cashService(),$context['company']['id'],$this->payload(),$this->apiUser()['id']);
            return $this->success(['movement_id'=>$id],201);
        } catch (\Throwable $e) { return $this->fail($e->getMessage(),422); }
    }

    public function confirmMovement(string $id)
    {
        $context=$this->cashContext('manage');
        if (isset($context['error'])) return $this->fail($context['error'],$context['status']);
        try {
            (new \App\Libraries\TreasuryPaymentService())->confirmManual($this->cashService(),$context['company']['id'],$id,$this->apiUser()['id'],(string)($this->payload()['evidence'] ?? ''));
            return $this->success(['movement_id'=>$id,'status'=>'posted']);
        } catch (\Throwable $e) { return $this->fail($e->getMessage(),422); }
    }

    public function paymentMethods()
    {
        $context=$this->cashContext('view');
        if (isset($context['error'])) return $this->fail($context['error'],$context['status']);
        return $this->success((new \App\Libraries\SalesPaymentService())->catalog($context['company']['id']));
    }

    public function gateways()
    {
        $context = $this->cashContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        return $this->success($this->cashService()->gatewayRows($context['company']['id']));
    }

    public function checks()
    {
        $context = $this->cashContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        return $this->success($this->cashService()->checkRows($context['company']['id']));
    }

    public function storeCheck()
    {
        $context = $this->cashContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $payload = $this->payload();
        $checkNumber = trim((string) ($payload['check_number'] ?? ''));
        $bankName = trim((string) ($payload['bank_name'] ?? ''));
        $amount = (float) ($payload['amount'] ?? 0);
        if ($checkNumber === '' || $bankName === '' || $amount <= 0) {
            return $this->fail('Debes completar numero, banco y monto del cheque.', 422);
        }

        $id = $this->cashService()->createCheck([
            'company_id' => $context['company']['id'],
            'supplier_id' => trim((string) ($payload['supplier_id'] ?? '')) ?: null,
            'customer_id' => trim((string) ($payload['customer_id'] ?? '')) ?: null,
            'check_type' => trim((string) ($payload['check_type'] ?? 'received')) ?: 'received',
            'check_number' => $checkNumber,
            'bank_name' => $bankName,
            'issuer_name' => trim((string) ($payload['issuer_name'] ?? '')),
            'due_date' => trim((string) ($payload['due_date'] ?? '')) ?: null,
            'amount' => $amount,
            'status' => trim((string) ($payload['status'] ?? 'portfolio')) ?: 'portfolio',
            'notes' => trim((string) ($payload['notes'] ?? '')),
            'created_by' => $this->apiUser()['id'] ?? null,
        ]);

        return $id ? $this->success(['check_id' => $id], 201) : $this->fail('No se pudo registrar el cheque.', 500);
    }

    public function reconciliations()
    {
        $context = $this->cashContext('view');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        return $this->success($this->cashService()->reconciliationRows($context['company']['id']));
    }

    public function storeReconciliation()
    {
        $context=$this->cashContext('manage');
        if (isset($context['error'])) return $this->fail($context['error'],$context['status']);
        try {
            $id=$this->cashService()->createReconciliation(array_merge($this->payload(),['company_id'=>$context['company']['id'],'created_by'=>$this->apiUser()['id']]));
            return $this->success(['reconciliation_id'=>$id],201);
        } catch (\Throwable $e) { return $this->fail($e->getMessage(),422); }
    }

    public function reconciliationPreview()
    {
        $context=$this->cashContext('manage');
        if (isset($context['error'])) return $this->fail($context['error'],$context['status']);
        try {
            return $this->success($this->cashService()->reconciliationBalances($context['company']['id'],(string)$this->request->getGet('cash_session_id')));
        } catch (\Throwable $e) { return $this->fail($e->getMessage(),422); }
    }

    private function cashContext(string $requiredAccess = 'view'): array
    {
        $companyId = $this->apiIsSuperadmin()
            ? (trim((string) ($this->request->getGet('company_id') ?: $this->request->getPost('company_id'))) ?: ((new CompanyModel())->orderBy('name', 'ASC')->first()['id'] ?? null))
            : $this->apiCompanyId();

        if (! $companyId) {
            return ['error' => 'Debes seleccionar una empresa para operar Caja.', 'status' => 422];
        }

        $company = (new CompanyModel())->find($companyId);
        if (! $company) {
            return ['error' => 'La empresa seleccionada no existe.', 'status' => 404];
        }

        $system = (new SystemModel())->where('slug', 'caja')->first();
        if (! $system || (int) ($system['active'] ?? 0) !== 1) {
            return ['error' => 'El sistema Caja no esta disponible.', 'status' => 404];
        }

        $accessLevel = 'view';
        if (! $this->apiIsSuperadmin()) {
            $companyAssignment = (new CompanySystemModel())->where('company_id', $companyId)->where('system_id', $system['id'])->where('active', 1)->first();
            if (! $companyAssignment) {
                return ['error' => 'La empresa no tiene Caja asignado.', 'status' => 403];
            }

            $userAssignment = (new UserSystemModel())->where('company_id', $companyId)->where('user_id', $this->apiUser()['id'] ?? '')->where('system_id', $system['id'])->where('active', 1)->first();
            if (! $userAssignment) {
                return ['error' => 'Tu usuario no tiene acceso activo a Caja.', 'status' => 403];
            }

            $accessLevel = $userAssignment['access_level'] ?? 'view';
        }

        if ($requiredAccess === 'manage' && ! $this->apiIsSuperadmin() && $accessLevel !== 'manage') {
            return ['error' => 'Tu usuario solo tiene acceso de consulta en Caja.', 'status' => 403];
        }

        $this->cashService()->ensureDefaults($companyId, $this->apiUser()['branch_id'] ?? null);

        return ['company' => $company, 'system' => $system, 'access_level' => $accessLevel];
    }

    private function cashService(): CashService
    {
        return new CashService(($this->apiUser()['role_slug'] ?? '') === 'vendedor' ? (string) ($this->apiUser()['id'] ?? '') : null);
    }

    public function endorseCheck(string $id)
    {
        $context = $this->cashContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $payload = $this->payload();
        $supplierId = trim((string) ($payload['supplier_id'] ?? ''));
        $sessionId = trim((string) ($payload['cash_session_id'] ?? ''));
        $notes = trim((string) ($payload['notes'] ?? ''));

        if ($supplierId === '' || $sessionId === '') {
            return $this->fail('Debes especificar supplier_id y cash_session_id.', 422);
        }

        $success = $this->cashService()->endorseCheck(
            $context['company']['id'],
            $id,
            $supplierId,
            $sessionId,
            $this->apiUser()['id'] ?? 'system',
            $notes
        );

        return $success
            ? $this->success(['check_id' => $id, 'status' => 'endorsed'])
            : $this->fail('No se pudo endosar el cheque. Verifica el ID y el estado actual del cheque.', 422);
    }

    public function depositCheck(string $id)
    {
        $context = $this->cashContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $payload = $this->payload();
        $sessionId = trim((string) ($payload['cash_session_id'] ?? ''));
        $notes = trim((string) ($payload['notes'] ?? ''));

        if ($sessionId === '') {
            return $this->fail('Debes especificar cash_session_id.', 422);
        }

        $success = $this->cashService()->depositCheck(
            $context['company']['id'],
            $id,
            $sessionId,
            $this->apiUser()['id'] ?? 'system',
            $notes
        );

        return $success
            ? $this->success(['check_id' => $id, 'status' => 'deposited'])
            : $this->fail('No se pudo depositar el cheque. Verifica el ID y el estado actual del cheque.', 422);
    }

    public function rejectCheck(string $id)
    {
        $context = $this->cashContext('manage');
        if (isset($context['error'])) {
            return $this->fail($context['error'], $context['status']);
        }

        $payload = $this->payload();
        $sessionId = trim((string) ($payload['cash_session_id'] ?? ''));
        $notes = trim((string) ($payload['notes'] ?? ''));

        if ($sessionId === '') {
            return $this->fail('Debes especificar cash_session_id.', 422);
        }

        $success = $this->cashService()->rejectCheck(
            $context['company']['id'],
            $id,
            $sessionId,
            $this->apiUser()['id'] ?? 'system',
            $notes
        );

        return $success
            ? $this->success(['check_id' => $id, 'status' => 'rejected'])
            : $this->fail('No se pudo registrar el rechazo del cheque. Verifica el ID y el estado del cheque.', 422);
    }
}
