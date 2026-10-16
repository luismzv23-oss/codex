<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class AddPaymentMethodSnapshots extends Migration
{
    public function up()
    {
        foreach (['payment_method_name' => ['type'=>'VARCHAR','constraint'=>120,'null'=>true],
            'payment_method_snapshot' => ['type'=>'TEXT','null'=>true], 'payment_data' => ['type'=>'TEXT','null'=>true]] as $field => $definition) {
            if (!$this->db->fieldExists($field, 'sale_payments')) $this->forge->addColumn('sale_payments', [$field=>$definition]);
        }
    }
    public function down()
    {
        if ($this->db->table('sale_payments')->where('payment_method_snapshot !=', null)->countAllResults()) throw new \RuntimeException('Existen pagos con condiciones históricas; no se puede retirar su trazabilidad.');
        $this->forge->dropColumn('sale_payments', ['payment_method_name','payment_method_snapshot','payment_data']);
    }
}
