<?php

namespace App\Controllers;

use App\Libraries\ReportingEngineService;

/**
 * ReportController — Executive & Administrative Reporting Dashboard Controller
 * Guarded by 'reports_auth' middleware filter for 'admin' and 'superadmin' roles.
 */
class ReportController extends BaseController
{
    protected $reportingEngine;

    public function __construct()
    {
        $this->reportingEngine = new ReportingEngineService();
    }

    /**
     * Reporting Center Dashboard (Main View)
     */
    public function index()
    {
        $companyId = auth_company_id();
        $startDate = $this->request->getGet('start_date') ?? date('Y-m-01');
        $endDate   = $this->request->getGet('end_date') ?? date('Y-m-t');

        $data = [
            'title'            => 'Centro de Reportes BI & Analítica ERP',
            'startDate'        => $startDate,
            'endDate'          => $endDate,
            'paretoSales'      => $this->reportingEngine->getSalesParetoRanking($companyId, $startDate, $endDate, 10),
            'receivablesAging' => $this->reportingEngine->getReceivablesAging($companyId),
            'valuedKardex'     => $this->reportingEngine->getValuedKardex($companyId),
            'cashFlowForecast' => $this->reportingEngine->getCashFlowForecast($companyId),
        ];

        return view('reports/index', $data);
    }

    /**
     * Generic Export Handler (CSV / PDF)
     */
    public function export(string $reportKey, string $format = 'csv')
    {
        $companyId = auth_company_id();

        if ($reportKey === 'aging') {
            $data = $this->reportingEngine->getReceivablesAging($companyId);
            $filename = "Aging_Deudores_" . date('Y-m-d') . ".csv";

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $filename);

            $output = fopen('php://output', 'w');
            fputcsv($output, ['Cliente', 'CUIT', 'Total Deuda', 'A Vencer', '1-30 Dias', '31-60 Dias', '61-90 Dias', '+90 Dias']);
            foreach ($data as $row) {
                fputcsv($output, [
                    $row['customer_name'], $row['cuit'], $row['total_due'], 
                    $row['current_amount'], $row['aging_1_30'], $row['aging_31_60'], 
                    $row['aging_61_90'], $row['aging_over_90']
                ]);
            }
            fclose($output);
            exit;
        }

        return redirect()->to(base_url('reports'))->with('error', 'Formato o reporte no soportado.');
    }
}
