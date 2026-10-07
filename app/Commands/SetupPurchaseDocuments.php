<?php
namespace App\Commands;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
class SetupPurchaseDocuments extends BaseCommand
{
    protected $group='Compras';
    protected $name='purchase-documents:setup';
    protected $description='Prepara la clave del archivo cifrado sin reemplazar claves existentes.';
    public function run(array $params)
    {
        (new \App\Libraries\PurchaseDocumentVault())->initialize();
        CLI::write('Archivo seguro preparado. Respalda la clave por separado de los documentos.','green');
    }
}
