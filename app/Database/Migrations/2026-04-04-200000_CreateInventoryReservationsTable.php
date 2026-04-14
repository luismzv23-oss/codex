<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInventoryReservationsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'company_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'product_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'warehouse_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'quantity' => ['type' => 'DECIMAL', 'constraint' => '14,2'],
            'reference' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
            'reserved_by' => ['type' => 'VARCHAR', 'constraint' => 36],
            'reserved_at' => ['type' => 'DATETIME'],
            'released_by' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'released_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addKey('product_id');
        $this->forge->addKey('warehouse_id');
        $this->forge->addKey('status');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('product_id', 'inventory_products', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('warehouse_id', 'inventory_warehouses', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('reserved_by', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('released_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('inventory_reservations');
    }

    public function down()
    {
        $this->forge->dropTable('inventory_reservations', true);
    }
}
