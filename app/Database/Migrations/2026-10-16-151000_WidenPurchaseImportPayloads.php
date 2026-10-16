<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class WidenPurchaseImportPayloads extends Migration
{
    public function up()
    {
        foreach (['purchase_imports'=>['data_json','extraction_json'],'purchase_import_items'=>['data_json'],'purchase_import_audit'=>['details_json'],'purchase_invoices'=>['adjustments_json']] as $table=>$fields)
            foreach ($fields as $field) $this->forge->modifyColumn($table,[$field=>['type'=>'LONGTEXT','null'=>true]]);
    }
    public function down() {throw new \RuntimeException('No se reduce automáticamente el tamaño de los documentos financieros.');}
}
