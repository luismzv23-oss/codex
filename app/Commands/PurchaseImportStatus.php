<?php
namespace App\Commands;
use CodeIgniter\CLI\{BaseCommand,CLI};
class PurchaseImportStatus extends BaseCommand
{
    protected $group='Compras';
    protected $name='compras:facturas-estado';
    protected $description='Muestra disponibilidad de lectura y trabajos pendientes sin exponer credenciales.';
    public function run(array $params)
    {
        $configured=(new \App\Libraries\PurchaseImport\AzureInvoiceExtractor())->configured();
        CLI::write('Azure: '.($configured?'configurado (conectividad y precisión requieren piloto)':'sin configurar; carga manual disponible'));
        CLI::write('Trabajos en espera: '.db_connect()->table('purchase_import_jobs')->where('status','queued')->countAllResults());
        CLI::write('Trabajos ejecutándose: '.db_connect()->table('purchase_import_jobs')->where('status','running')->countAllResults());
        CLI::write('Límite de archivo: '.config('PurchaseImport')->maxBytes.' bytes / '.config('PurchaseImport')->maxPages.' páginas.');
    }
}
