<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class CreatePurchaseDocuments extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'=>['type'=>'CHAR','constraint'=>36], 'company_id'=>['type'=>'CHAR','constraint'=>36],
            'sha256'=>['type'=>'CHAR','constraint'=>64], 'original_name'=>['type'=>'VARCHAR','constraint'=>180],
            'size_bytes'=>['type'=>'INT','unsigned'=>true], 'status'=>['type'=>'VARCHAR','constraint'=>20,'default'=>'draft'],
            'purchase_invoice_id'=>['type'=>'CHAR','constraint'=>36,'null'=>true],
            'draft_cipher'=>['type'=>'MEDIUMTEXT','null'=>true], 'revision'=>['type'=>'INT','default'=>0],
            'created_by'=>['type'=>'CHAR','constraint'=>36], 'updated_by'=>['type'=>'CHAR','constraint'=>36],
            'created_at'=>['type'=>'DATETIME'], 'updated_at'=>['type'=>'DATETIME'],
        ]);
        $this->forge->addKey('id',true);
        $this->forge->addUniqueKey(['company_id','sha256']);
        $this->forge->addKey(['company_id','status']);
        $this->forge->createTable('purchase_documents');
        $this->forge->addField([
            'id'=>['type'=>'CHAR','constraint'=>36], 'document_id'=>['type'=>'CHAR','constraint'=>36],
            'company_id'=>['type'=>'CHAR','constraint'=>36], 'user_id'=>['type'=>'CHAR','constraint'=>36],
            'action'=>['type'=>'VARCHAR','constraint'=>32], 'created_at'=>['type'=>'DATETIME'],
        ]);
        $this->forge->addKey('id',true); $this->forge->addKey(['company_id','document_id']);
        $this->forge->createTable('purchase_document_events');
    }
    public function down()
    {
        if ($this->db->table('purchase_documents')->countAllResults()) throw new \RuntimeException('Existen documentos archivados; no se puede eliminar su registro.');
        $this->forge->dropTable('purchase_document_events'); $this->forge->dropTable('purchase_documents');
    }
}
