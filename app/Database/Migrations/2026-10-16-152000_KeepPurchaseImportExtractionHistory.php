<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class KeepPurchaseImportExtractionHistory extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('result_json','purchase_import_jobs')) $this->forge->addColumn('purchase_import_jobs',['result_json'=>['type'=>'LONGTEXT','null'=>true]]);
        $this->db->query('CREATE INDEX purchase_import_queue ON '.$this->db->prefixTable('purchase_import_jobs').' (status,available_at)');
    }
    public function down() {throw new \RuntimeException('El historial de extracción requiere una reversión supervisada.');}
}
