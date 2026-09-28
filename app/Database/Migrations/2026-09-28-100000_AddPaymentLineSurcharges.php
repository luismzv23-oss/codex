<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class AddPaymentLineSurcharges extends Migration
{
    public function up()
    {
        $fields = [
            'payment_method_id' => ['type'=>'VARCHAR','constraint'=>36,'null'=>true],
            'payment_method_code' => ['type'=>'VARCHAR','constraint'=>30,'null'=>true],
        ];
        foreach (['base_amount','surcharge_amount','received_amount','change_amount'] as $key) {
            $fields[$key] = ['type'=>'DECIMAL','constraint'=>'18,2','default'=>0];
        }
        $fields['surcharge_rate'] = ['type'=>'DECIMAL','constraint'=>'5,2','default'=>0];
        $this->forge->addColumn('sale_payments', $fields);
    }
    public function down()
    {
        $this->forge->dropColumn('sale_payments', ['payment_method_id','payment_method_code','base_amount','surcharge_amount','received_amount','change_amount','surcharge_rate']);
    }
}
