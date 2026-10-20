<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class CreatePurchaseInvoiceImports extends Migration
{
    public function up()
    {
        $uuid=['type'=>'CHAR','constraint'=>36];
        $text=['type'=>'TEXT','null'=>true];
        $time=['type'=>'DATETIME','null'=>true];
        $this->create('purchase_documents',[
            'company_id'=>$uuid,'sha256'=>['type'=>'CHAR','constraint'=>64],
            'storage_path'=>['type'=>'VARCHAR','constraint'=>255],'original_name'=>['type'=>'VARCHAR','constraint'=>180],
            'mime_type'=>['type'=>'VARCHAR','constraint'=>50],'byte_size'=>['type'=>'INT'],'page_count'=>['type'=>'INT'],
            'created_by'=>$uuid,
        ],[['company_id','sha256']]);
        $this->create('purchase_imports',[
            'company_id'=>$uuid,'document_id'=>$uuid,'processing_status'=>['type'=>'VARCHAR','constraint'=>20,'default'=>'idle'],
            'review_status'=>['type'=>'VARCHAR','constraint'=>20,'default'=>'draft'],'version'=>['type'=>'INT','default'=>1],
            'generation'=>['type'=>'INT','default'=>0],'data_json'=>$text,'extraction_json'=>$text,
            'invoice_id'=>$uuid+['null'=>true],'confirmation_key'=>['type'=>'VARCHAR','constraint'=>80,'null'=>true],
            'created_by'=>$uuid,'confirmed_by'=>$uuid+['null'=>true],'confirmed_at'=>$time,
        ],[['company_id','document_id']]);
        $this->create('purchase_import_items',['import_id'=>$uuid,'line_number'=>['type'=>'INT'],'data_json'=>$text],[]);
        $this->create('purchase_import_jobs',[
            'company_id'=>$uuid,'import_id'=>$uuid,'generation'=>['type'=>'INT'],
            'status'=>['type'=>'VARCHAR','constraint'=>20,'default'=>'queued'],'attempts'=>['type'=>'INT','default'=>0],
            'lease_token'=>$uuid+['null'=>true],'lease_until'=>$time,'available_at'=>$time,
            'operation_url'=>$text,'error_message'=>$text,
        ],[]);
        $this->create('purchase_import_audit',[
            'company_id'=>$uuid,'import_id'=>$uuid,'user_id'=>$uuid+['null'=>true],
            'action'=>['type'=>'VARCHAR','constraint'=>40],'details_json'=>$text,
        ],[]);
        $this->create('supplier_product_links',[
            'company_id'=>$uuid,'supplier_id'=>$uuid,'supplier_code'=>['type'=>'VARCHAR','constraint'=>120],
            'product_id'=>$uuid,'confirmed_by'=>$uuid,
        ],[['company_id','supplier_id','supplier_code']]);
        foreach ([
            'document_type'=>['type'=>'VARCHAR','constraint'=>20,'null'=>true],
            'point_of_sale'=>['type'=>'VARCHAR','constraint'=>5,'null'=>true],
            'invoice_sequence'=>['type'=>'VARCHAR','constraint'=>8,'null'=>true],
            'supplier_tax_id'=>['type'=>'VARCHAR','constraint'=>30,'null'=>true],
            'identity_hash'=>['type'=>'CHAR','constraint'=>64,'null'=>true],
            'import_id'=>$uuid+['null'=>true],'request_key'=>['type'=>'VARCHAR','constraint'=>80,'null'=>true],
            'request_hash'=>['type'=>'CHAR','constraint'=>64,'null'=>true],
            'purchase_kind'=>['type'=>'VARCHAR','constraint'=>20,'default'=>'goods'],
            'adjustments_json'=>$text,'adjustment_total'=>['type'=>'DECIMAL','constraint'=>'18,2','default'=>0],
            'reviewed_total'=>['type'=>'DECIMAL','constraint'=>'18,2','null'=>true],
        ] as $field=>$definition) if (!$this->db->fieldExists($field,'purchase_invoices')) $this->forge->addColumn('purchase_invoices',[$field=>$definition]);
        // NULL historical identities remain historical; no inferred backfill.
        $this->db->query('CREATE UNIQUE INDEX purchase_invoice_identity ON '.$this->db->prefixTable('purchase_invoices').' (company_id,identity_hash)');
    }
    private function create(string $table,array $fields,array $unique): void
    {
        $this->forge->addField(['id'=>['type'=>'CHAR','constraint'=>36]]+$fields+[
            'created_at'=>['type'=>'DATETIME','null'=>true],'updated_at'=>['type'=>'DATETIME','null'=>true]]);
        $this->forge->addKey('id',true);
        foreach ($unique as $columns) $this->forge->addUniqueKey($columns);
        if (isset($fields['company_id'])) $this->forge->addKey('company_id');
        $this->forge->createTable($table,true);
    }
    public function down()
    {
        throw new \RuntimeException('La trazabilidad de documentos de compra requiere una reversión supervisada.');
    }
}
