<?php
namespace CodexCore\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateIntegrationOutbox extends Migration
{
    public function up()
    {
        $id = ['type'=>'VARCHAR','constraint'=>36];
        $attributes=$this->db->DBDriver==='MySQLi' ? ['ENGINE'=>'InnoDB'] : [];
        $this->forge->addField([
            'id'=>$id,'company_id'=>$id,
            'event'=>['type'=>'VARCHAR','constraint'=>100],
            'schema_version'=>['type'=>'INT','default'=>1],
            'source_type'=>['type'=>'VARCHAR','constraint'=>40],'source_id'=>$id,
            'operation_key'=>['type'=>'VARCHAR','constraint'=>120],
            'payload'=>['type'=>'LONGTEXT'],'payload_hash'=>['type'=>'VARCHAR','constraint'=>64],
            'occurred_at'=>['type'=>'DATETIME'],
        ]);
        $this->forge->addKey('id',true);
        $this->forge->addUniqueKey(['company_id','event','operation_key'],'outbox_operation');
        $this->forge->createTable('integration_outbox',false,$attributes);
        $this->forge->addField([
            'id'=>$id,'company_id'=>$id,'event_id'=>$id,
            'consumer'=>['type'=>'VARCHAR','constraint'=>100],
            'consumer_version'=>['type'=>'INT','default'=>1],
            'status'=>['type'=>'VARCHAR','constraint'=>16,'default'=>'pending'],
            'attempts'=>['type'=>'INT','default'=>0],
            'available_at'=>['type'=>'DATETIME'],
            'lease_token'=>['type'=>'VARCHAR','constraint'=>64,'null'=>true],
            'lease_until'=>['type'=>'DATETIME','null'=>true],
            'completed_at'=>['type'=>'DATETIME','null'=>true],
            'last_error'=>['type'=>'VARCHAR','constraint'=>255,'null'=>true],
        ]);
        $this->forge->addKey('id',true);
        $this->forge->addUniqueKey(['event_id','consumer'],'delivery_consumer');
        $this->forge->addKey(['company_id','status','available_at']);
        $this->forge->addForeignKey('event_id','integration_outbox','id','RESTRICT','RESTRICT');
        $this->forge->createTable('integration_deliveries',false,$attributes);
        $this->forge->addField([
            'id'=>$id,'company_id'=>$id,'delivery_id'=>$id,
            'attempt'=>['type'=>'INT'],'started_at'=>['type'=>'DATETIME'],
            'finished_at'=>['type'=>'DATETIME','null'=>true],
            'result'=>['type'=>'VARCHAR','constraint'=>24],
            'error'=>['type'=>'VARCHAR','constraint'=>255,'null'=>true],
        ]);
        $this->forge->addKey('id',true);
        $this->forge->addKey(['company_id','delivery_id']);
        $this->forge->addForeignKey('delivery_id','integration_deliveries','id','RESTRICT','RESTRICT');
        $this->forge->createTable('integration_delivery_attempts',false,$attributes);
    }

    public function down()
    {
        foreach (['integration_outbox','integration_deliveries','integration_delivery_attempts'] as $table) {
            if ($this->db->tableExists($table) && $this->db->table($table)->countAllResults()>0) {
                throw new \RuntimeException('El registro de integración contiene evidencias; no se permite borrarlo mediante rollback.');
            }
        }
        $this->forge->dropTable('integration_delivery_attempts');
        $this->forge->dropTable('integration_deliveries');
        $this->forge->dropTable('integration_outbox');
    }
}
