<?php

namespace App\Controllers;

use App\Libraries\ReplenishmentAnalysisService;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;

/**
 * Análisis inteligente de reposición (Compras > Reposición).
 * Consultar y analizar requiere acceso a Compras; generar órdenes requiere acceso de gestión.
 */
class PurchaseReplenishmentController extends PurchasesController
{
    public function index()
    {
        $context = $this->purchaseContext('view');
        if ($context instanceof RedirectResponse) {
            return $context;
        }
        $companyId = $context['company']['id'];
        $service = new ReplenishmentAnalysisService();
        $runId = trim((string) $this->request->getGet('run'));
        $run = $runId !== '' ? $service->find($companyId, $runId) : $service->latest($companyId);
        $db = db_connect();

        return view('purchases/replenishment', [
            'pageTitle' => 'Reposición inteligente',
            'context' => $context,
            'companyId' => $companyId,
            'selectedCompanyId' => $companyId,
            'companies' => $this->isSuperadmin() ? $db->table('companies')->select('id, name')->orderBy('name')->get()->getResultArray() : [],
            'run' => $run,
            'history' => $service->history($companyId),
            'params' => $run['params'] ?? ReplenishmentAnalysisService::normalizeParams([]),
            'warehouses' => $db->table('inventory_warehouses')->select('id, name')->where('company_id', $companyId)->where('active', 1)->orderBy('is_default', 'DESC')->orderBy('name')->get()->getResultArray(),
            'suppliers' => $db->table('suppliers')->select('id, name')->where('company_id', $companyId)->where('active', 1)->orderBy('name')->get()->getResultArray(),
            'aiLabel' => (new \App\Libraries\CodexAssistService())->label(),
        ]);
    }

    public function analyze()
    {
        $context = $this->purchaseContext('view');
        if ($context instanceof RedirectResponse) {
            return $context;
        }
        $companyId = $context['company']['id'];
        try {
            $run = (new ReplenishmentAnalysisService())->run($companyId, (array) $this->request->getPost(), $this->currentUser()['id'] ?? null);
        } catch (RuntimeException $e) {
            return redirect()->to($this->replenishmentUrl($companyId))->with('error', $e->getMessage());
        }

        return redirect()->to($this->replenishmentUrl($companyId, $run['id']))->with('message', 'Análisis completado: ' . $run['summary']['to_reorder'] . ' productos para reponer.');
    }

    public function ai(string $runId)
    {
        $context = $this->purchaseContext('view');
        if ($context instanceof RedirectResponse) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Sin acceso a Compras.', 'csrfHash' => csrf_hash()]);
        }
        try {
            $result = (new ReplenishmentAnalysisService())->aiSummary($context['company']['id'], $runId, ['company' => $context['company']['name']], (string) $this->request->getPost('refresh') === '1');
        } catch (RuntimeException $e) {
            return $this->response->setStatusCode(422)->setJSON(['error' => $e->getMessage(), 'csrfHash' => csrf_hash()]);
        }

        return $this->response->setHeader('Cache-Control', 'no-store')->setJSON($result + ['csrfHash' => csrf_hash()]);
    }

    public function orders(string $runId)
    {
        $context = $this->purchaseContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }
        $companyId = $context['company']['id'];
        $selected = array_map('strval', (array) $this->request->getPost('selected'));
        $quantities = (array) $this->request->getPost('quantity');
        $suppliers = (array) $this->request->getPost('supplier_id');
        $costs = (array) $this->request->getPost('unit_cost');
        $lines = [];
        foreach ($selected as $productId) {
            $lines[$productId] = ['quantity' => $quantities[$productId] ?? 0, 'supplier_id' => $suppliers[$productId] ?? '', 'unit_cost' => $costs[$productId] ?? ''];
        }

        try {
            $result = (new ReplenishmentAnalysisService())->createOrders($companyId, $runId, $lines, trim((string) $this->request->getPost('warehouse_id')) ?: null, $this->currentUser()['id'], $this->flowBranchId($companyId));
        } catch (RuntimeException $e) {
            return redirect()->to($this->replenishmentUrl($companyId, $runId))->with('error', $e->getMessage());
        }

        $numbers = implode(', ', array_column($result['orders'], 'order_number'));
        $message = count($result['orders']) . ' órdenes de compra creadas en borrador (' . $numbers . ').';
        if ($result['skipped'] > 0) {
            $message .= ' ' . $result['skipped'] . ' líneas omitidas por no tener proveedor o cantidad.';
        }

        return redirect()->to($this->replenishmentUrl($companyId, $runId))->with('message', $message);
    }

    private function replenishmentUrl(string $companyId, ?string $runId = null): string
    {
        $query = array_filter(['company_id' => $this->isSuperadmin() ? $companyId : null, 'run' => $runId]);

        return site_url('compras/reposicion' . ($query ? '?' . http_build_query($query) : ''));
    }
}
