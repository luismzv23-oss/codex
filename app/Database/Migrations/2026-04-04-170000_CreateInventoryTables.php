<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInventoryTables extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'company_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'alert_email' => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'unusual_movement_threshold' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '100.00'],
            'no_rotation_days' => ['type' => 'INT', 'constraint' => 11, 'default' => 30],
            'allow_negative_stock' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'low_stock_alerts' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'internal_notifications' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'email_notifications' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('company_id');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('inventory_settings');

        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'company_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'branch_id' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'code' => ['type' => 'VARCHAR', 'constraint' => 40],
            'type' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'general'],
            'description' => ['type' => 'TEXT', 'null' => true],
            'is_default' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addKey('branch_id');
        $this->forge->addUniqueKey(['company_id', 'code']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('inventory_warehouses');

        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'company_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'sku' => ['type' => 'VARCHAR', 'constraint' => 60],
            'name' => ['type' => 'VARCHAR', 'constraint' => 160],
            'description' => ['type' => 'TEXT', 'null' => true],
            'unit' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'unidad'],
            'min_stock' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addUniqueKey(['company_id', 'sku']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('inventory_products');

        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'company_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'product_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'warehouse_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'quantity' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'min_stock' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addKey('product_id');
        $this->forge->addKey('warehouse_id');
        $this->forge->addUniqueKey(['product_id', 'warehouse_id']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('product_id', 'inventory_products', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('warehouse_id', 'inventory_warehouses', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('inventory_stock_levels');

        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'company_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'product_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'movement_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'quantity' => ['type' => 'DECIMAL', 'constraint' => '14,2'],
            'adjustment_mode' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'source_warehouse_id' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'destination_warehouse_id' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'performed_by' => ['type' => 'VARCHAR', 'constraint' => 36],
            'occurred_at' => ['type' => 'DATETIME'],
            'reason' => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addKey('product_id');
        $this->forge->addKey('source_warehouse_id');
        $this->forge->addKey('destination_warehouse_id');
        $this->forge->addKey('performed_by');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('product_id', 'inventory_products', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('source_warehouse_id', 'inventory_warehouses', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('destination_warehouse_id', 'inventory_warehouses', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('performed_by', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('inventory_movements');

        $this->db->table('systems')
            ->where('slug', 'inventario')
            ->update([
                'entry_url' => 'inventario',
                'description' => 'Control de existencias, movimientos, depositos y alertas.',
                'icon' => 'bi-box-seam',
            ]);
    }

    public function down()
    {
        $this->forge->dropTable('inventory_movements', true);
        $this->forge->dropTable('inventory_stock_levels', true);
        $this->forge->dropTable('inventory_products', true);
        $this->forge->dropTable('inventory_warehouses', true);
        $this->forge->dropTable('inventory_settings', true);
    }
}
