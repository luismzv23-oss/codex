<?php
namespace App\Commands;
use CodeIgniter\CLI\{BaseCommand,CLI};
class ProcessPurchaseImports extends BaseCommand
{
    protected $group='Compras';
    protected $name='compras:procesar-facturas';
    protected $description='Procesa lecturas documentales pendientes sin registrar facturas.';
    protected $options=['--limit'=>'Máximo de trabajos por ejecución (1-100).','--company'=>'UUID opcional de empresa.'];
    public function run(array $params)
    {
        $count=(new \App\Libraries\PurchaseImportWorker())->run((int)(CLI::getOption('limit') ?: 10),CLI::getOption('company') ?: null);
        CLI::write('Trabajos procesados: '.$count.'. La confirmación de facturas se realiza desde Compras.');
    }
}
