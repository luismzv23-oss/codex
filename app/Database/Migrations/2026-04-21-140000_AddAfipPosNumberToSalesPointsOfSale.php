<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAfipPosNumberToSalesPointsOfSale extends Migration
{
    public function up()
    {
        $this->forge->addColumn('sales_points_of_sale', [
            'afip_pos_number' => [
                'type'       => 'INT',
                'constraint' => 5,
                'null'       => true,
                'after'      => 'code',
                'comment'    => 'Punto de Venta AFIP (1 - 99998)'
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('sales_points_of_sale', 'afip_pos_number');
    }
}
