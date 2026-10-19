<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class RecordPurchaseImportProvider extends Migration
{
    public function up()
    {
        // Existing jobs retain their cloud engine; only new jobs select local.
        if (!$this->db->fieldExists('provider','purchase_import_jobs')) {
            $this->forge->addColumn('purchase_import_jobs',['provider'=>['type'=>'VARCHAR','constraint'=>20,'default'=>'azure']]);
        }
    }
    public function down() {throw new \RuntimeException('No se elimina el origen del historial de lecturas.');}
}
