<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPaymentMethodReceiptVisibility extends Migration
{
    public function up()
    {
        $this->forge->addColumn('company_payment_methods', [
            'show_on_receipt' => ['type'=>'TINYINT', 'constraint'=>1, 'default'=>1],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('company_payment_methods', 'show_on_receipt');
    }
}
