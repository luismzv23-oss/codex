<?php

namespace App\Controllers;

use App\Libraries\StockControlService;
use CodeIgniter\HTTP\RedirectResponse;
use Dompdf\{Dompdf, Options};
use RuntimeException;

/** Weekly stock control screens: list, blind count sheet, differences/approval and the weekly report. */
class InventoryControlController extends InventoryController
{
    private function companyOf($context): string
    {
        return (string) $context['company']['id'];
    }

    private function back(string $path, string $companyId): string
    {
        return site_url($path) . ($this->isSuperadmin() ? '?company_id=' . $companyId : '');
    }

    public function controlIndex()
    {
        $context = $this->inventoryContext('view');
        if ($context instanceof RedirectResponse) {
            return $context;
        }
        $companyId = $this->companyOf($context);
        $service = new StockControlService();

        return view('inventory/control/index', [
            'pageTitle' => 'Control semanal de stock', 'companyId' => $companyId, 'week' => StockControlService::week(),
            'counts' => $service->history($companyId),
            'warehouses' => db_connect()->table('inventory_warehouses')->select('id, name, is_default')->where('company_id', $companyId)->where('active', 1)->orderBy('is_default', 'DESC')->orderBy('name')->get()->getResultArray(),
            'alerts' => count($service->alertProducts($companyId)),
            'companies' => $this->inventoryCompanies(), 'company' => $context['company'],
        ]);
    }

    public function controlStart()
    {
        $context = $this->inventoryContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }
        $companyId = $this->companyOf($context);
        try {
            $count = (new StockControlService())->start($companyId, (string) $this->request->getPost('warehouse_id'), (string) $this->currentUser()['id']);
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to($this->back('inventario/control-semanal/' . $count['id'], $companyId));
    }

    public function controlShow(string $id)
    {
        $context = $this->inventoryContext('view');
        if ($context instanceof RedirectResponse) {
            return $context;
        }
        $companyId = $this->companyOf($context);
        try {
            $count = (new StockControlService())->get($companyId, $id);
        } catch (RuntimeException $e) {
            return redirect()->to($this->back('inventario/control-semanal', $companyId))->with('error', $e->getMessage());
        }
        $threshold = (float) (db_connect()->fieldExists('cycle_count_threshold_pct', 'inventory_settings')
            ? (db_connect()->table('inventory_settings')->select('cycle_count_threshold_pct')->where('company_id', $companyId)->get()->getRowArray()['cycle_count_threshold_pct'] ?? 10) : 10);

        return view('inventory/control/show', [
            'pageTitle' => 'Control ' . $count['count_number'], 'count' => $count, 'companyId' => $companyId, 'threshold' => $threshold ?: 10,
            'products' => db_connect()->table('inventory_products')->select('id, sku, name')->where('company_id', $companyId)->where('active', 1)->orderBy('name')->get()->getResultArray(),
            'csrfHeader' => config('Security')->headerName, 'company' => $context['company'],
            'traces' => $count['status'] === 'counted' ? (new StockControlService())->traceOptions($companyId, $count) : [],
        ]);
    }

    /** JSON: records one counted quantity. */
    public function controlRecord(string $id)
    {
        $context = $this->inventoryContext('manage');
        if ($context instanceof RedirectResponse) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Sin acceso.', 'csrf_token' => csrf_hash()]);
        }
        $input = (array) ($this->request->getJSON(true) ?: $this->request->getPost());
        try {
            $count = (new StockControlService())->record($this->companyOf($context), $id, (string) ($input['product_id'] ?? ''), $input['counted'] ?? null);
            $counted = count(array_filter($count['items'], static fn($i) => $i['counted_at'] !== null));

            return $this->response->setJSON(['ok' => true, 'counted' => $counted, 'total' => count($count['items']), 'csrf_token' => csrf_hash()]);
        } catch (RuntimeException $e) {
            return $this->response->setStatusCode(422)->setJSON(['error' => $e->getMessage(), 'csrf_token' => csrf_hash()]);
        }
    }

    public function controlClose(string $id)
    {
        return $this->controlAction($id, fn(StockControlService $s, string $c) => $s->close($c, $id), 'Conteo cerrado. Revisa y aprueba las diferencias.');
    }

    public function controlApprove(string $id)
    {
        $lines = [];
        foreach ((array) $this->request->getPost('items') as $itemId => $line) {
            $line = (array) $line;
            $lines[(string) $itemId] = ['approved' => ! empty($line['approved']), 'reason' => (string) ($line['reason'] ?? ''),
                'lots' => (array) ($line['lots'] ?? []), 'serials' => (array) ($line['serials'] ?? []),
                'lot_number' => (string) ($line['lot_number'] ?? ''), 'expiration_date' => (string) ($line['expiration_date'] ?? '')];
        }

        return $this->controlAction($id, fn(StockControlService $s, string $c) => $s->approve($c, $id, $lines, (string) $this->currentUser()['id']), 'Diferencias aprobadas: los ajustes se registraron en el stock.');
    }

    public function controlCancel(string $id)
    {
        return $this->controlAction($id, function (StockControlService $s, string $c) use ($id) { $s->cancel($c, $id); }, 'Control anulado.', 'inventario/control-semanal');
    }

    private function controlAction(string $id, callable $operation, string $message, ?string $redirect = null)
    {
        $context = $this->inventoryContext('manage');
        if ($context instanceof RedirectResponse) {
            return $context;
        }
        $companyId = $this->companyOf($context);
        try {
            $operation(new StockControlService(), $companyId);
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to($this->back($redirect ?? 'inventario/control-semanal/' . $id, $companyId))->with('message', $message);
    }

    /** Popup: products of a control. While it is open the count stays blind (no system stock). */
    public function controlItems(string $id)
    {
        $context = $this->inventoryContext('view');
        if ($context instanceof RedirectResponse) {
            return $context;
        }
        $companyId = $this->companyOf($context);
        try {
            $count = (new StockControlService())->get($companyId, $id);
        } catch (RuntimeException $e) {
            return redirect()->to($this->back('inventario/control-semanal', $companyId))->with('error', $e->getMessage());
        }

        return view('inventory/control/items', ['count' => $count, 'companyId' => $companyId, 'pageTitle' => 'Productos ' . $count['count_number']]);
    }

    /** Blind count sheet (PDF): products and empty boxes, never the system stock. */
    public function controlSheet(string $id)
    {
        $context = $this->inventoryContext('view');
        if ($context instanceof RedirectResponse) {
            return $context;
        }
        try {
            $count = (new StockControlService())->get($this->companyOf($context), $id);
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return $this->pdf('inventory/control/pdf_sheet', ['count' => $count, 'company' => $context['company']], 'planilla-' . $count['count_number'] . '.pdf');
    }

    public function controlReport(string $format = 'html')
    {
        $context = $this->inventoryContext('view');
        if ($context instanceof RedirectResponse) {
            return $context;
        }
        $format = in_array($format, ['pdf', 'xlsx'], true) ? $format : 'html';
        $companyId = $this->companyOf($context);
        $days =max(1, min(90, (int) ($this->request->getGet('dias') ?: 7)));
        $report = (new StockControlService())->report($companyId, $days, (string) $this->currentUser()['id']);
        $data = ['report' => $report, 'company' => $context['company'], 'companyId' => $companyId, 'pageTitle' => 'Informe semanal de stock', 'canManage' => (bool) ($context['canManage'] ?? false), 'companies' => $this->inventoryCompanies()];
        if ($format === 'pdf') {
            return $this->pdf('inventory/control/pdf_report', $data, 'informe-stock-' . $report['week'] . '.pdf');
        }
        if ($format === 'xlsx') {
            return $this->reportXlsx($report);
        }

        return view('inventory/control/report', $data);
    }

    private function pdf(string $view, array $data, string $filename)
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view($view, $data));
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $this->response->setHeader('Content-Type', 'application/pdf')->setHeader('Content-Disposition', 'inline; filename="' . $filename . '"')->setBody($dompdf->output());
    }

    private function reportXlsx(array $report)
    {
        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $book->removeSheetByIndex(0);
        $sheets = [
            'Controles' => [['Control', 'Semana', 'Deposito', 'Estado', 'Productos', 'Diferencia $'], array_map(static fn($r) => [$r['count_number'], $r['week'], $r['warehouse_name'] ?? '', $r['status'], (int) $r['items_count'], (float) $r['difference_value']], $report['counts'])],
            'Entradas sin comprobante' => [['Fecha', 'SKU', 'Producto', 'Cantidad', 'Motivo', 'Usuario'], array_map(static fn($r) => [$r['occurred_at'], $r['sku'], $r['name'], (float) $r['quantity'], $r['reason'], $r['user_name'] ?? ''], $report['manual_entries'])],
            'Borradores cobrados' => [['Venta', 'Fecha', 'Cliente', 'Cobrado', 'Usuario'], array_map(static fn($r) => [$r['sale_number'], $r['issue_date'], $r['customer'] ?? '', (float) $r['paid'], $r['user_name'] ?? ''], $report['paid_drafts'])],
            'Sin rotacion' => [['SKU', 'Producto', 'Stock', 'Valor $'], array_map(static fn($r) => [$r['sku'], $r['name'], (float) $r['stock'], (float) $r['value']], $report['no_rotation'])],
            'Stock negativo' => [['SKU', 'Producto', 'Stock', 'Valor $'], array_map(static fn($r) => [$r['sku'], $r['name'], (float) $r['stock'], (float) $r['value']], $report['negative_stock'])],
        ];
        foreach ($sheets as $title => [$headers, $rows]) {
            $sheet = $book->createSheet();
            $sheet->setTitle(mb_substr($title, 0, 31));
            foreach ($headers as $c => $h) {
                $sheet->setCellValueExplicit(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c + 1) . '1', $h, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            foreach (array_values($rows) as $r => $row) {
                foreach (array_values($row) as $c => $value) {
                    $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c + 1) . ($r + 2);
                    is_int($value) || is_float($value)
                        ? $sheet->setCellValueExplicit($cell, $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC)
                        : $sheet->setCellValueExplicit($cell, (string) $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
            }
            $sheet->getStyle('A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . '1')->getFont()->setBold(true);
            for ($i = 1; $i <= count($headers); $i++) {
                $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
            }
        }
        $book->setActiveSheetIndex(0);
        $stream = fopen('php://memory', 'w+b');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($stream);
        rewind($stream);
        $body = stream_get_contents($stream);
        fclose($stream);

        return $this->response->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="informe-stock-' . $report['week'] . '.xlsx"')->setBody($body);
    }
}
