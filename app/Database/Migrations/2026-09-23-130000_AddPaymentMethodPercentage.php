<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPaymentMethodPercentage extends Migration
{
    public function up()
    {
        $this->forge->addColumn('company_payment_methods', [
            'percentage' => ['type' => 'FLOAT', 'default' => 0],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('company_payment_methods', 'percentage');
    }
}
