<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class ExtendTreasuryPaymentMethods extends Migration
{
    public function up()
    {
        foreach (['allow_collections'=>1, 'allow_supplier_payments'=>0] as $field=>$default) {
            if (!$this->db->fieldExists($field,'company_payment_methods')) $this->forge->addColumn('company_payment_methods',[$field=>['type'=>'TINYINT','default'=>$default]]);
        }
        $metadata = [
            'payment_method_id'=>['type'=>'CHAR','constraint'=>36,'null'=>true],
            'payment_method_code'=>['type'=>'VARCHAR','constraint'=>30,'null'=>true],
            'payment_method_name'=>['type'=>'VARCHAR','constraint'=>120,'null'=>true],
            'payment_method_snapshot'=>['type'=>'TEXT','null'=>true],
            'payment_data'=>['type'=>'TEXT','null'=>true],
        ];
        foreach (['cash_movements','cash_reconciliations','purchase_payments'] as $table) {
            $fields = $metadata + ['currency_code'=>['type'=>'VARCHAR','constraint'=>10,'null'=>true]];
            if ($table !== 'cash_reconciliations') $fields += [
                'status'=>['type'=>'VARCHAR','constraint'=>20,'default'=>'posted'],
                'confirmed_by'=>['type'=>'CHAR','constraint'=>36,'null'=>true],
                'confirmed_at'=>['type'=>'DATETIME','null'=>true],
                'confirmation_evidence'=>['type'=>'TEXT','null'=>true],
                'request_key'=>['type'=>'VARCHAR','constraint'=>80,'null'=>true],
                'request_hash'=>['type'=>'CHAR','constraint'=>64,'null'=>true],
                'branch_id'=>['type'=>'CHAR','constraint'=>36,'null'=>true],
                'point_of_sale_id'=>['type'=>'CHAR','constraint'=>36,'null'=>true],
                'exchange_rate'=>['type'=>'DECIMAL','constraint'=>'18,6','default'=>1],
            ];
            foreach ($fields as $field=>$definition) {
                if (!$this->db->fieldExists($field,$table)) $this->forge->addColumn($table,[$field=>$definition]);
            }
        }
    }
    public function down()
    {
        throw new \RuntimeException('Esta migración conserva trazabilidad financiera y no admite reversión automática.');
    }
}
