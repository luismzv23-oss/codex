<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMaxDiscountToSalesSettings extends Migration
{
    public function up()
    {
        $this->forge->addColumn('sales_settings', [
            'max_discount_vendedor' => [
                'type' => 'DECIMAL',
                'constraint' => '5,2',
                'default' => '10.00',
                'after' => 'allow_negative_stock_sales',
                'null' => false,
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('sales_settings', 'max_discount_vendedor');
    }
}
