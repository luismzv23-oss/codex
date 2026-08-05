<?php

namespace App\Controllers\Api\V1;

use CodeIgniter\RESTful\ResourceController;
use App\Libraries\ArcaAsyncWorker;
use App\Libraries\CreditRiskService;
use App\Libraries\WithholdingEngineService;
use App\Libraries\MrpService;
use App\Libraries\BankReconciliationService;
use App\Libraries\CheckLifecycleService;
use App\Libraries\AccountDeterminationService;
use App\Libraries\FieldAuditService;

/**
 * ErpAdvancedController — API Endpoints exposing advanced ERP Services.
 */
class ErpAdvancedController extends ResourceController
{
    protected $format = 'json';

    protected function getCompanyId(): string
    {
        $user = auth_user();
        if ($user && !empty($user['company_id'])) {
            return $user['company_id'];
        }
        return (string) ($this->request->getHeaderLine('X-Company-Id') ?: $this->request->getVar('company_id') ?: '');
    }

    /**
     * POST /api/v1/mrp/calculate — Calculate Material Requirements Planning.
     */
    public function calculateMrp()
    {
        $companyId = $this->getCompanyId();
        if (empty($companyId)) {
            return $this->failUnauthorized('Empresa no identificada.');
        }

        $warehouseId = $this->request->getVar('warehouse_id') ?: null;
        $mrpService = new MrpService();
        $results = $mrpService->calculateRequirements($companyId, $warehouseId);

        return $this->respond([
            'status'  => 200,
            'message' => 'Cálculo de MRP I ejecutado correctamente.',
            'data'    => $results,
        ]);
    }

    /**
     * POST /api/v1/sales/check-credit-risk — Evaluate customer credit risk and commercial policies.
     */
    public function checkCreditRisk()
    {
        $companyId = $this->getCompanyId();
        if (empty($companyId)) {
            return $this->failUnauthorized('Empresa no identificada.');
        }

        $customerId = (string) $this->request->getVar('customer_id');
        $proposedTotal = (float) ($this->request->getVar('proposed_total') ?: 0);
        $discountPct = (float) ($this->request->getVar('discount_pct') ?: 0);
        $salesAgentId = (string) ($this->request->getVar('sales_agent_id') ?: '');

        if (empty($customerId)) {
            return $this->failValidationError('El parámetro customer_id es obligatorio.');
        }

        $creditService = new CreditRiskService();
        $evaluation = $creditService->evaluateSaleRisk($companyId, $customerId, $proposedTotal, $discountPct, $salesAgentId);

        return $this->respond([
            'status'  => 200,
            'message' => 'Evaluación de riesgo crediticio completada.',
            'data'    => $evaluation,
        ]);
    }

    /**
     * POST /api/v1/sales/arca/queue — Queue invoice for async ARCA authorization.
     */
    public function queueArcaInvoice()
    {
        $companyId = $this->getCompanyId();
        if (empty($companyId)) {
            return $this->failUnauthorized('Empresa no identificada.');
        }

        $saleId = (string) $this->request->getVar('sale_id');
        if (empty($saleId)) {
            return $this->failValidationError('El parámetro sale_id es obligatorio.');
        }

        $worker = new ArcaAsyncWorker();
        $ok = $worker->queueInvoice($companyId, $saleId, ['sale_number' => $this->request->getVar('sale_number') ?? '']);

        return $this->respond([
            'status'  => $ok ? 200 : 500,
            'message' => $ok ? 'Comprobante encolado para autorización ARCA asíncrona.' : 'Error al encolar comprobante.',
            'data'    => ['sale_id' => $saleId, 'queued' => $ok],
        ]);
    }

    /**
     * POST /api/v1/sales/arca/process-batch — Run background batch worker for queued ARCA invoices.
     */
    public function processArcaBatch()
    {
        $limit = (int) ($this->request->getVar('limit') ?: 20);
        $worker = new ArcaAsyncWorker();
        $summary = $worker->processBatch($limit);

        return $this->respond([
            'status'  => 200,
            'message' => 'Lote de comprobantes ARCA procesado.',
            'data'    => $summary,
        ]);
    }

    /**
     * POST /api/v1/purchases/withholdings/calculate — Calculate tax withholdings for a payment.
     */
    public function calculateWithholdings()
    {
        $companyId = $this->getCompanyId();
        if (empty($companyId)) {
            return $this->failUnauthorized('Empresa no identificada.');
        }

        $supplierId = (string) $this->request->getVar('supplier_id');
        $paymentAmount = (float) ($this->request->getVar('payment_amount') ?: 0);
        $paymentDate = (string) ($this->request->getVar('payment_date') ?: date('Y-m-d'));

        if (empty($supplierId) || $paymentAmount <= 0) {
            return $this->failValidationError('Parámetros supplier_id y payment_amount son obligatorios.');
        }

        $engine = new WithholdingEngineService();
        $result = $engine->processPaymentWithholdings($companyId, $supplierId, $paymentAmount, [], $paymentDate);

        return $this->respond([
            'status'  => $result['ok'] ? 200 : 400,
            'message' => 'Cálculo automático de retenciones completado.',
            'data'    => $result,
        ]);
    }

    /**
     * POST /api/v1/cash/reconcile-statement — Smart automated bank statement reconciliation.
     */
    public function reconcileBankStatement()
    {
        $companyId = $this->getCompanyId();
        if (empty($companyId)) {
            return $this->failUnauthorized('Empresa no identificada.');
        }

        $bankAccountId = (string) ($this->request->getVar('bank_account_id') ?: '');
        $statementLines = $this->request->getVar('statement_lines');

        if (!is_array($statementLines) || empty($statementLines)) {
            return $this->failValidationError('Se requiere una lista de líneas de extracto bancario en statement_lines.');
        }

        $service = new BankReconciliationService();
        $summary = $service->reconcileStatement($companyId, $bankAccountId, $statementLines);

        return $this->respond([
            'status'  => 200,
            'message' => 'Conciliación bancaria procesada correctamente.',
            'data'    => $summary,
        ]);
    }

    /**
     * POST /api/v1/cash/checks/transition — Transition state for physical checks & eCheqs.
     */
    public function transitionCheckState()
    {
        $companyId = $this->getCompanyId();
        if (empty($companyId)) {
            return $this->failUnauthorized('Empresa no identificada.');
        }

        $checkId = (string) $this->request->getVar('check_id');
        $newStatus = (string) $this->request->getVar('new_status');
        $params = (array) ($this->request->getVar('params') ?: []);

        if (empty($checkId) || empty($newStatus)) {
            return $this->failValidationError('Los parámetros check_id y new_status son obligatorios.');
        }

        $service = new CheckLifecycleService();
        $result = $service->transitionCheckState($companyId, $checkId, $newStatus, $params);

        return $this->respond([
            'status'  => $result['ok'] ? 200 : 400,
            'message' => $result['ok'] ? 'Estado del cheque actualizado correctamente.' : $result['error'],
            'data'    => $result,
        ]);
    }
}
