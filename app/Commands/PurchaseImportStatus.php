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
        $config=config('PurchaseImport');$reader=\App\Libraries\PurchaseImport\InvoiceReaderFactory::make();
        CLI::write('Motor: '.$config->provider);
        CLI::write('Lectura: '.($reader->configured()?'disponible (la precisión requiere revisión con documentos representativos)':'sin configurar; carga manual disponible'));
        if ($config->provider==='local') {
            CLI::write('Tesseract: '.(is_file($config->tesseractPath)?'disponible':'falta instalar'));
            CLI::write('Poppler: '.(is_file($config->pdftoppmPath)?'disponible':'falta instalar'));
            CLI::write('Idiomas: '.$config->ocrLanguage);
        }
        CLI::write('Trabajos en espera: '.db_connect()->table('purchase_import_jobs')->where('status','queued')->countAllResults());
        CLI::write('Trabajos ejecutándose: '.db_connect()->table('purchase_import_jobs')->where('status','running')->countAllResults());
        CLI::write('Límite de archivo: '.config('PurchaseImport')->maxBytes.' bytes / '.config('PurchaseImport')->maxPages.' páginas.');
    }
}
