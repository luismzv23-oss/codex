<?php

namespace App\Controllers;

use App\Models\BranchModel;
use App\Models\CompanyModel;
use App\Models\QaRunModel;
use App\Models\UserModel;
use App\Models\SalesSettingModel;

class DashboardController extends BaseController
{
    public function index()
    {
        $companyId = $this->companyId();
        $isSuperadmin = $this->isSuperadmin();

        $companyModel = new CompanyModel();
        $userModel = new UserModel();
        $branchModel = new BranchModel();

        $companyCount = $isSuperadmin
            ? $companyModel->countAllResults()
            : ($companyId ? 1 : 0);

        $userCount = $isSuperadmin
            ? $userModel->countAllResults()
            : $userModel->where('company_id', $companyId)->countAllResults();

        $branchCount = $isSuperadmin
            ? $branchModel->countAllResults()
            : $branchModel->where('company_id', $companyId)->countAllResults();

        $activeUserBuilder = db_connect()->table('users')->where('active', 1);
        $customerBuilder = db_connect()->table('customers');
        $salesBase = db_connect()->table('sales')->whereIn('status', ['confirmed', 'returned_partial', 'returned_total']);
        $purchaseBase = db_connect()->table('purchase_orders')->whereIn('status', ['approved', 'received_partial', 'received_total']);
        $receivableBase = db_connect()->table('sales_receivables')->whereIn('status', ['pending', 'partial']);
        $payableBase = db_connect()->table('purchase_payables')->whereIn('status', ['pending', 'partial']);
        $cashBase = db_connect()->table('cash_sessions')->where('status', 'open');
        $inventoryBase = db_connect()->table('inventory_stock_levels isl')
            ->select('isl.quantity, isl.reserved_quantity, isl.min_stock AS stock_min_stock, ip.min_stock AS product_min_stock')
            ->join('inventory_products ip', 'ip.id = isl.product_id');
        $auditBase = db_connect()->table('audit_logs')->where('DATE(created_at)', date('Y-m-d'));
        $integrationBase = db_connect()->table('integration_logs');
        $arcaBase = db_connect()->table('sales')->whereIn('document_code', ['FACTURA_A', 'FACTURA_B', 'FACTURA_C', 'FACTURA_M', 'TICKET']);

        if (! $isSuperadmin && $companyId) {
            $activeUserBuilder->where('company_id', $companyId);
            $customerBuilder->where('company_id', $companyId);
            $salesBase->where('company_id', $companyId);
            $purchaseBase->where('company_id', $companyId);
            $receivableBase->where('company_id', $companyId);
            $payableBase->where('company_id', $companyId);
            $cashBase->where('company_id', $companyId);
            $inventoryBase->where('isl.company_id', $companyId);
            $auditBase->where('company_id', $companyId);
            $integrationBase->where('company_id', $companyId);
            $arcaBase->where('company_id', $companyId);
        }

        $salesTotal = (float) (((clone $salesBase)->selectSum('total', 'amount')->get()->getRowArray()['amount'] ?? 0));
        $salesToday = (float) (((clone $salesBase)->selectSum('total', 'amount')->where('DATE(issue_date)', date('Y-m-d'))->get()->getRowArray()['amount'] ?? 0));
        $salesCurrentMonth = (int) ((clone $salesBase)->where('issue_date >=', date('Y-m-01 00:00:00'))->countAllResults());
        $salesMargin = (float) (((clone $salesBase)->selectSum('margin_total', 'amount')->get()->getRowArray()['amount'] ?? 0));

        $purchaseTotal = (float) (((clone $purchaseBase)->selectSum('total', 'amount')->get()->getRowArray()['amount'] ?? 0));
        $purchaseCurrentMonth = (int) ((clone $purchaseBase)->where('issued_at >=', date('Y-m-01 00:00:00'))->countAllResults());

        $receivableBalance = (float) (((clone $receivableBase)->selectSum('balance_amount', 'amount')->get()->getRowArray()['amount'] ?? 0));
        $payableBalance = (float) (((clone $payableBase)->selectSum('balance_amount', 'amount')->get()->getRowArray()['amount'] ?? 0));

        $criticalStock = (int) ((clone $inventoryBase)
            ->groupStart()
                ->where('isl.quantity <=', 0)
                ->orWhere('isl.quantity - isl.reserved_quantity <= COALESCE(isl.min_stock, ip.min_stock)', null, false)
            ->groupEnd()
            ->countAllResults());
        $openCashSessions = (int) ((clone $cashBase)->countAllResults());
        $auditToday = (int) ((clone $auditBase)->countAllResults());
        $integrationErrors = (int) ((clone $integrationBase)->whereIn('status', ['error', 'failed'])->countAllResults());
        $arcaPending = (int) ((clone $arcaBase)->whereNotIn('arca_status', ['Authorizado', 'No Aplica'])->countAllResults());

        $customerCount = (int) $customerBuilder->countAllResults();
        $activeUsers = (int) $activeUserBuilder->countAllResults();

        $branchPerformanceRows = $this->branchPerformance($companyId, $isSuperadmin);
        $marketingSeries = $this->marketingSeries($companyId, $isSuperadmin);
        $readiness = $this->erpReadiness($companyId, $isSuperadmin);

        $alerts = [
            ['label' => 'Stock critico', 'value' => $criticalStock, 'tone' => $criticalStock > 0 ? 'danger' : 'success'],
            ['label' => 'Saldo por cobrar', 'value' => number_format($receivableBalance, 2, ',', '.'), 'tone' => $receivableBalance > 0 ? 'warning' : 'success'],
            ['label' => 'Saldo por pagar', 'value' => number_format($payableBalance, 2, ',', '.'), 'tone' => $payableBalance > 0 ? 'warning' : 'success'],
            ['label' => 'Cajas abiertas', 'value' => $openCashSessions, 'tone' => $openCashSessions > 0 ? 'info' : 'secondary'],
            ['label' => 'ARCA pendiente/error', 'value' => $arcaPending, 'tone' => $arcaPending > 0 ? 'warning' : 'success'],
            ['label' => 'Errores de integracion', 'value' => $integrationErrors, 'tone' => $integrationErrors > 0 ? 'danger' : 'success'],
        ];

        $auditBuilder = db_connect()->table('audit_logs al')
            ->select('al.*, u.name AS user_name')
            ->join('users u', 'u.id = al.user_id', 'left');
        if (! $isSuperadmin && $companyId) {
            $auditBuilder->where('al.company_id', $companyId);
        }
        $recentAudit = $auditBuilder
            ->orderBy('al.created_at', 'DESC')
            ->limit(8)
            ->get()
            ->getResultArray();

        $integrationBuilder = db_connect()->table('integration_logs il')->select('il.*');
        if (! $isSuperadmin && $companyId) {
            $integrationBuilder->where('il.company_id', $companyId);
        }
        $recentIntegrations = $integrationBuilder
            ->orderBy('il.created_at', 'DESC')
            ->limit(8)
            ->get()
            ->getResultArray();

        return view('dashboard/index', [
            'pageTitle' => 'Dashboard',
            'stats' => [
                'companies' => $companyCount,
                'users' => $userCount,
                'branches' => $branchCount,
                'active_users' => $activeUsers,
                'customers' => $customerCount,
                'sales_total' => $salesTotal,
                'sales_today' => $salesToday,
                'sales_current_month' => $salesCurrentMonth,
                'sales_margin' => $salesMargin,
                'purchase_total' => $purchaseTotal,
                'purchase_current_month' => $purchaseCurrentMonth,
                'receivable_balance' => $receivableBalance,
                'payable_balance' => $payableBalance,
                'critical_stock' => $criticalStock,
                'open_cash_sessions' => $openCashSessions,
                'audit_today' => $auditToday,
                'integration_errors' => $integrationErrors,
                'arca_pending' => $arcaPending,
            ],
            'alerts' => $alerts,
            'branchPerformance' => $branchPerformanceRows,
            'marketingSeries' => $marketingSeries,
            'recentAudit' => $recentAudit,
            'recentIntegrations' => $recentIntegrations,
            'readiness' => $readiness,
            'user' => $this->currentUser(),
        ]);
    }

    public function readiness()
    {
        $companyId = $this->companyId();
        $isSuperadmin = $this->isSuperadmin();

        return view('dashboard/readiness', [
            'pageTitle' => 'Diagnostico ERP',
            'user' => $this->currentUser(),
            'readiness' => $this->erpReadiness($companyId, $isSuperadmin),
        ]);
    }

    public function qa()
    {
        $companyId = $this->companyId();
        $isSuperadmin = $this->isSuperadmin();

        return view('dashboard/qa', [
            'pageTitle' => 'QA integral',
            'user' => $this->currentUser(),
            'qa' => $this->qaChecklist($companyId, $isSuperadmin),
            'recentRuns' => $this->qaRuns($companyId, $isSuperadmin),
        ]);
    }

    public function storeQaRun()
    {
        $companyId = $this->companyId();
        if (! $companyId && ! $this->isSuperadmin()) {
            return redirect()->to('/dashboard/qa')->with('error', 'No hay empresa activa para registrar QA.');
        }

        $moduleName = trim((string) $this->request->getPost('module_name'));
        $scenarioCode = trim((string) $this->request->getPost('scenario_code'));
        if ($moduleName === '' || $scenarioCode === '') {
            return redirect()->to('/dashboard/qa')->with('error', 'Debes indicar modulo y escenario.');
        }

        (new QaRunModel())->insert([
            'company_id' => $companyId,
            'module_name' => $moduleName,
            'scenario_code' => $scenarioCode,
            'status' => trim((string) $this->request->getPost('status')) ?: 'passed',
            'notes' => trim((string) $this->request->getPost('notes')),
            'executed_by' => $this->currentUser()['id'] ?? null,
            'executed_at' => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('/dashboard/qa')->with('message', 'Resultado QA registrado correctamente.');
    }

    private function branchPerformance(?string $companyId, bool $isSuperadmin): array
    {
        if (! $isSuperadmin && $companyId) {
            return db_connect()->table('sales s')
                ->select('COALESCE(b.name, "Casa Matriz") AS branch_name, COUNT(s.id) AS sales_count, SUM(s.total) AS total_amount, SUM(s.margin_total) AS margin_total', false)
                ->join('branches b', 'b.id = s.branch_id', 'left')
                ->where('s.company_id', $companyId)
                ->whereIn('s.status', ['confirmed', 'returned_partial', 'returned_total'])
                ->groupBy('s.branch_id')
                ->orderBy('total_amount', 'DESC')
                ->limit(6)
                ->get()
                ->getResultArray();
        }

        return db_connect()->table('sales s')
            ->select('COALESCE(c.name, "Sin empresa") AS branch_name, COUNT(s.id) AS sales_count, SUM(s.total) AS total_amount, SUM(s.margin_total) AS margin_total', false)
            ->join('companies c', 'c.id = s.company_id', 'left')
            ->whereIn('s.status', ['confirmed', 'returned_partial', 'returned_total'])
            ->groupBy('s.company_id')
            ->orderBy('total_amount', 'DESC')
            ->limit(6)
            ->get()
            ->getResultArray();
    }

    private function marketingSeries(?string $companyId, bool $isSuperadmin): array
    {
        $series = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-{$i} months"));
            $start = $month . '-01 00:00:00';
            $end = date('Y-m-t 23:59:59', strtotime($start));

            $builder = db_connect()->table('sales')
                ->select('SUM(total) AS amount, SUM(margin_total) AS margin')
                ->whereIn('status', ['confirmed', 'returned_partial', 'returned_total'])
                ->where('issue_date >=', $start)
                ->where('issue_date <=', $end);

            if (! $isSuperadmin && $companyId) {
                $builder->where('company_id', $companyId);
            }

            $row = $builder->get()->getRowArray() ?? [];
            $series[] = [
                'label' => date('M', strtotime($start)),
                'amount' => (float) ($row['amount'] ?? 0),
                'margin' => (float) ($row['margin'] ?? 0),
            ];
        }

        return $series;
    }

    private function erpReadiness(?string $companyId, bool $isSuperadmin): array
    {
        if ($isSuperadmin && ! $companyId) {
            $companyId = $this->resolveDashboardCompanyId();
        }

        $checks = [];
        if (! $companyId) {
            return [
                'status' => 'blocked',
                'score' => 0,
                'blocking' => ['No hay empresa seleccionada para evaluar readiness.'],
                'warnings' => [],
                'ok' => [],
                'checks' => [],
            ];
        }

        $company = (new CompanyModel())->find($companyId);
        $salesSettings = (new SalesSettingModel())->where('company_id', $companyId)->first();
        $currencyCount = db_connect()->table('currencies')->where('company_id', $companyId)->where('active', 1)->countAllResults();
        $branchCount = db_connect()->table('branches')->where('company_id', $companyId)->where('active', 1)->countAllResults();
        $userCount = db_connect()->table('users')->where('company_id', $companyId)->where('active', 1)->countAllResults();
        $warehouseCount = db_connect()->table('inventory_warehouses')->where('company_id', $companyId)->where('active', 1)->countAllResults();
        $productCount = db_connect()->table('inventory_products')->where('company_id', $companyId)->where('active', 1)->countAllResults();
        $documentCount = db_connect()->table('sales_document_types')->where('company_id', $companyId)->where('active', 1)->countAllResults();
        $pointOfSaleCount = db_connect()->table('sales_points_of_sale')->where('company_id', $companyId)->where('active', 1)->countAllResults();
        $systemCount = db_connect()->table('company_systems')->where('company_id', $companyId)->where('active', 1)->countAllResults();
        $cashRegisterCount = db_connect()->table('cash_registers')->where('company_id', $companyId)->where('active', 1)->countAllResults();
        $openCashCount = db_connect()->table('cash_sessions')->where('company_id', $companyId)->where('status', 'open')->countAllResults();
        $arcaEvents = db_connect()->table('sales_arca_events')->where('company_id', $companyId)->countAllResults();

        $checks[] = ['label' => 'Empresa activa', 'ok' => (bool) ($company && (int) ($company['active'] ?? 0) === 1), 'critical' => true];
        $checks[] = ['label' => 'Monedas activas configuradas', 'ok' => $currencyCount > 0, 'critical' => true];
        $checks[] = ['label' => 'Sucursales activas', 'ok' => $branchCount > 0, 'critical' => true];
        $checks[] = ['label' => 'Usuarios activos', 'ok' => $userCount > 0, 'critical' => true];
        $checks[] = ['label' => 'Sistemas asignados', 'ok' => $systemCount > 0, 'critical' => true];
        $checks[] = ['label' => 'Depositos activos', 'ok' => $warehouseCount > 0, 'critical' => true];
        $checks[] = ['label' => 'Productos activos', 'ok' => $productCount > 0, 'critical' => false];
        $checks[] = ['label' => 'Comprobantes activos', 'ok' => $documentCount > 0, 'critical' => true];
        $checks[] = ['label' => 'Puntos de venta activos', 'ok' => $pointOfSaleCount > 0, 'critical' => true];
        $checks[] = ['label' => 'Cajas definidas', 'ok' => $cashRegisterCount > 0, 'critical' => false];
        $checks[] = ['label' => 'Caja operativa abierta', 'ok' => $openCashCount > 0, 'critical' => false];
        $checks[] = ['label' => 'Configuracion ARCA completa', 'ok' => $this->arcaReady($salesSettings), 'critical' => false];
        $checks[] = ['label' => 'Bitacora ARCA inicial', 'ok' => $arcaEvents > 0, 'critical' => false];

        $blocking = [];
        $warnings = [];
        $ok = [];

        foreach ($checks as $check) {
            if ($check['ok']) {
                $ok[] = $check['label'];
                continue;
            }

            if ($check['critical']) {
                $blocking[] = $check['label'];
            } else {
                $warnings[] = $check['label'];
            }
        }

        $score = count($checks) > 0 ? (int) round((count($ok) / count($checks)) * 100) : 0;
        $status = $blocking !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'ready');

        return [
            'status' => $status,
            'score' => $score,
            'blocking' => $blocking,
            'warnings' => $warnings,
            'ok' => $ok,
            'checks' => $checks,
            'company' => $company,
        ];
    }

    private function arcaReady(?array $settings): bool
    {
        if (! $settings || (int) ($settings['arca_enabled'] ?? 0) !== 1) {
            return false;
        }

        $certificatePath = trim((string) ($settings['certificate_path'] ?? ''));
        $privateKeyPath = trim((string) ($settings['private_key_path'] ?? ''));
        $cuit = trim((string) ($settings['arca_cuit'] ?? ''));

        return $cuit !== ''
            && $certificatePath !== ''
            && $privateKeyPath !== ''
            && is_file($certificatePath)
            && is_file($privateKeyPath);
    }

    private function qaChecklist(?string $companyId, bool $isSuperadmin): array
    {
        if ($isSuperadmin && ! $companyId) {
            $companyId = $this->resolveDashboardCompanyId();
        }

        if (! $companyId) {
            return ['status' => 'blocked', 'score' => 0, 'modules' => []];
        }

        $salesSettings = (new SalesSettingModel())->where('company_id', $companyId)->first();
        $modules = [
            'Compras' => [
                ['label' => 'Proveedores creados', 'ok' => db_connect()->table('suppliers')->where('company_id', $companyId)->countAllResults() > 0],
                ['label' => 'Ordenes registradas', 'ok' => db_connect()->table('purchase_orders')->where('company_id', $companyId)->countAllResults() > 0],
                ['label' => 'Recepciones registradas', 'ok' => db_connect()->table('purchase_receipts')->where('company_id', $companyId)->countAllResults() > 0],
                ['label' => 'Pagos a proveedor', 'ok' => db_connect()->table('purchase_payments')->where('company_id', $companyId)->countAllResults() > 0],
            ],
            'Inventario' => [
                ['label' => 'Depositos activos', 'ok' => db_connect()->table('inventory_warehouses')->where('company_id', $companyId)->where('active', 1)->countAllResults() > 0],
                ['label' => 'Productos activos', 'ok' => db_connect()->table('inventory_products')->where('company_id', $companyId)->where('active', 1)->countAllResults() > 0],
                ['label' => 'Movimientos registrados', 'ok' => db_connect()->table('inventory_movements')->where('company_id', $companyId)->countAllResults() > 0],
                ['label' => 'Stock consolidado', 'ok' => db_connect()->table('inventory_stock_levels')->where('company_id', $companyId)->countAllResults() > 0],
            ],
            'Ventas' => [
                ['label' => 'Clientes creados', 'ok' => db_connect()->table('customers')->where('company_id', $companyId)->countAllResults() > 0],
                ['label' => 'Comprobantes configurados', 'ok' => db_connect()->table('sales_document_types')->where('company_id', $companyId)->where('active', 1)->countAllResults() > 0],
                ['label' => 'Puntos de venta activos', 'ok' => db_connect()->table('sales_points_of_sale')->where('company_id', $companyId)->where('active', 1)->countAllResults() > 0],
                ['label' => 'Ventas confirmadas', 'ok' => db_connect()->table('sales')->where('company_id', $companyId)->whereIn('status', ['confirmed', 'returned_partial', 'returned_total'])->countAllResults() > 0],
            ],
            'Cobranzas' => [
                ['label' => 'Cuenta corriente generada', 'ok' => db_connect()->table('sales_receivables')->where('company_id', $companyId)->countAllResults() > 0],
                ['label' => 'Recibos registrados', 'ok' => db_connect()->table('sales_receipts')->where('company_id', $companyId)->countAllResults() > 0],
            ],
            'Caja' => [
                ['label' => 'Cajas activas', 'ok' => db_connect()->table('cash_registers')->where('company_id', $companyId)->where('active', 1)->countAllResults() > 0],
                ['label' => 'Sesiones abiertas o cerradas', 'ok' => db_connect()->table('cash_sessions')->where('company_id', $companyId)->countAllResults() > 0],
                ['label' => 'Movimientos de caja', 'ok' => db_connect()->table('cash_movements')->where('company_id', $companyId)->countAllResults() > 0],
            ],
            'Fiscal' => [
                ['label' => 'CUIT configurado', 'ok' => trim((string) ($salesSettings['arca_cuit'] ?? '')) !== ''],
                ['label' => 'Bundle fiscal valido', 'ok' => $this->arcaReady($salesSettings)],
                ['label' => 'Eventos ARCA registrados', 'ok' => db_connect()->table('sales_arca_events')->where('company_id', $companyId)->countAllResults() > 0],
            ],
        ];

        $normalized = [];
        $totalChecks = 0;
        $okChecks = 0;
        foreach ($modules as $name => $checks) {
            $moduleOk = count(array_filter($checks, static fn(array $check): bool => (bool) $check['ok']));
            $moduleTotal = count($checks);
            $totalChecks += $moduleTotal;
            $okChecks += $moduleOk;
            $normalized[] = [
                'name' => $name,
                'score' => $moduleTotal > 0 ? (int) round(($moduleOk / $moduleTotal) * 100) : 0,
                'status' => $moduleOk === $moduleTotal ? 'ready' : ($moduleOk > 0 ? 'warning' : 'blocked'),
                'checks' => $checks,
            ];
        }

        return [
            'status' => $okChecks === $totalChecks ? 'ready' : ($okChecks > 0 ? 'warning' : 'blocked'),
            'score' => $totalChecks > 0 ? (int) round(($okChecks / $totalChecks) * 100) : 0,
            'modules' => $normalized,
        ];
    }

    private function qaRuns(?string $companyId, bool $isSuperadmin): array
    {
        $builder = db_connect()->table('qa_runs q')
            ->select('q.*, u.name AS user_name')
            ->join('users u', 'u.id = q.executed_by', 'left')
            ->orderBy('q.executed_at', 'DESC')
            ->limit(20);
        if (! $isSuperadmin && $companyId) {
            $builder->where('q.company_id', $companyId);
        }

        return $builder->get()->getResultArray();
    }

    private function resolveDashboardCompanyId(): ?string
    {
        $requestedCompanyId = trim((string) $this->request->getGet('company_id'));
        if ($requestedCompanyId !== '') {
            return $requestedCompanyId;
        }

        $currentCompanyId = $this->companyId();
        if ($currentCompanyId) {
            return $currentCompanyId;
        }

        $company = (new CompanyModel())
            ->where('active', 1)
            ->orderBy('name', 'ASC')
            ->first();

        return $company['id'] ?? null;
    }
}
