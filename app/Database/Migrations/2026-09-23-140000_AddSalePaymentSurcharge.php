<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class AddSalePaymentSurcharge extends Migration
{
    public function up()
    {
        $this->forge->addColumn('sales', [
            'payment_method_id' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'payment_method_code' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'payment_surcharge_rate' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
            'payment_surcharge_amount' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
        ]);
    }
    public function down()
    {
        $this->forge->dropColumn('sales', ['payment_method_id', 'payment_method_code', 'payment_surcharge_rate', 'payment_surcharge_amount']);
    }
}
