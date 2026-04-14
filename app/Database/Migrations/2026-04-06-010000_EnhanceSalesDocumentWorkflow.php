<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnhanceSalesDocumentWorkflow extends Migration
{
    public function up()
    {
        $this->forge->addColumn('sales', [
            'source_sale_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true, 'after' => 'point_of_sale_id'],
            'reservation_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'none', 'after' => 'status'],
            'reserved_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'confirmed_at'],
            'reservation_released_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'reserved_at'],
            'delivered_by' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true, 'after' => 'reservation_released_at'],
            'delivered_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'delivered_by'],
        ]);

        $this->forge->addColumn('inventory_reservations', [
            'sale_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true, 'after' => 'warehouse_id'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('inventory_reservations', ['sale_id']);
        $this->forge->dropColumn('sales', [
            'source_sale_id',
            'reservation_status',
            'reserved_at',
            'reservation_released_at',
            'delivered_by',
            'delivered_at',
        ]);
    }
}
