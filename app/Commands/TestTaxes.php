<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class TestTaxes extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:taxes';
    protected $description = 'Test taxes logic.';

    public function run(array $params)
    {
        $companyId = \Config\Database::connect()->table('companies')->select('id')->limit(1)->get()->getRowArray()['id'] ?? null;

        if (!$companyId) {
            CLI::error("No company found");
            return;
        }

        $libroIva = new \App\Libraries\LibroIvaDigitalService();
        try {
            $report = $libroIva->ventasReport($companyId, '2026-05-01', '2026-05-31');
            CLI::write("Ventas report OK. Count: " . count($report['records']), 'green');
        } catch (\Throwable $e) {
            CLI::error("Error in ventasReport: " . $e->getMessage());
        }

        try {
            $report2 = $libroIva->comprasReport($companyId, '2026-05-01', '2026-05-31');
            CLI::write("Compras report OK. Count: " . count($report2['records']), 'green');
        } catch (\Throwable $e) {
            CLI::error("Error in comprasReport: " . $e->getMessage());
        }

        $sicore = new \App\Libraries\SicoreService();
        try {
            $report3 = $sicore->periodSummary($companyId, '2026-05-01', '2026-05-31');
            CLI::write("Sicore OK.", 'green');
        } catch (\Throwable $e) {
            CLI::error("Error in Sicore: " . $e->getMessage());
        }

        CLI::write("Test done.");
    }
}
