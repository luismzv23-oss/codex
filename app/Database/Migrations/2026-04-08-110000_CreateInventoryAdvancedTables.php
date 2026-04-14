<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInventoryAdvancedTables extends Migration
{
    public function up()
    {
        $this->forge->addColumn('inventory_products', [
            'lot_control' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'sale_price'],
            'serial_control' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'lot_control'],
            'expiration_control' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'serial_control'],
        ]);

        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'warehouse_id' => ['type' => 'CHAR', 'constraint' => 36],
            'name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'code' => ['type' => 'VARCHAR', 'constraint' => 40],
            'zone' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'rack' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'level' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'description' => ['type' => 'TEXT', 'null' => true],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addKey('warehouse_id');
        $this->forge->addUniqueKey(['warehouse_id', 'code']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('warehouse_id', 'inventory_warehouses', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('inventory_locations');

        $this->forge->addColumn('inventory_stock_levels', [
            'location_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true, 'after' => 'warehouse_id'],
        ]);
        $this->db->query('ALTER TABLE `inventory_stock_levels` ADD INDEX `idx_inventory_stock_levels_location_id` (`location_id`)');
        $this->db->query('ALTER TABLE `inventory_stock_levels` ADD CONSTRAINT `fk_inventory_stock_levels_location_id` FOREIGN KEY (`location_id`) REFERENCES `inventory_locations`(`id`) ON DELETE SET NULL ON UPDATE CASCADE');

        $this->forge->addColumn('inventory_movements', [
            'source_location_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true, 'after' => 'source_warehouse_id'],
            'destination_location_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true, 'after' => 'destination_warehouse_id'],
        ]);
        $this->db->query('ALTER TABLE `inventory_movements` ADD INDEX `idx_inventory_movements_source_location_id` (`source_location_id`)');
        $this->db->query('ALTER TABLE `inventory_movements` ADD INDEX `idx_inventory_movements_destination_location_id` (`destination_location_id`)');
        $this->db->query('ALTER TABLE `inventory_movements` ADD CONSTRAINT `fk_inventory_movements_source_location_id` FOREIGN KEY (`source_location_id`) REFERENCES `inventory_locations`(`id`) ON DELETE SET NULL ON UPDATE CASCADE');
        $this->db->query('ALTER TABLE `inventory_movements` ADD CONSTRAINT `fk_inventory_movements_destination_location_id` FOREIGN KEY (`destination_location_id`) REFERENCES `inventory_locations`(`id`) ON DELETE SET NULL ON UPDATE CASCADE');

        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'product_id' => ['type' => 'CHAR', 'constraint' => 36],
            'warehouse_id' => ['type' => 'CHAR', 'constraint' => 36],
            'location_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'lot_number' => ['type' => 'VARCHAR', 'constraint' => 80],
            'expiration_date' => ['type' => 'DATE', 'null' => true],
            'quantity_balance' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'product_id', 'warehouse_id', 'location_id', 'lot_number']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('product_id', 'inventory_products', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('warehouse_id', 'inventory_warehouses', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('location_id', 'inventory_locations', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('inventory_lots');

        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'product_id' => ['type' => 'CHAR', 'constraint' => 36],
            'warehouse_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'location_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'serial_number' => ['type' => 'VARCHAR', 'constraint' => 100],
            'lot_number' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'expiration_date' => ['type' => 'DATE', 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'available'],
            'last_movement_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'product_id', 'serial_number']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('product_id', 'inventory_products', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('warehouse_id', 'inventory_warehouses', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('location_id', 'inventory_locations', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('inventory_serials');

        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'product_id' => ['type' => 'CHAR', 'constraint' => 36],
            'component_product_id' => ['type' => 'CHAR', 'constraint' => 36],
            'quantity' => ['type' => 'DECIMAL', 'constraint' => '14,4', 'default' => '1.0000'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['product_id', 'component_product_id']);
        $this->forge->addForeignKey('product_id', 'inventory_products', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('component_product_id', 'inventory_products', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('inventory_kit_items');

        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'product_id' => ['type' => 'CHAR', 'constraint' => 36],
            'warehouse_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'location_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'movement_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'layer_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'entry'],
            'quantity' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'remaining_quantity' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'unit_cost' => ['type' => 'DECIMAL', 'constraint' => '14,4', 'default' => '0.0000'],
            'total_cost' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'occurred_at' => ['type' => 'DATETIME'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addKey('product_id');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('product_id', 'inventory_products', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('warehouse_id', 'inventory_warehouses', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('location_id', 'inventory_locations', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('movement_id', 'inventory_movements', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('inventory_cost_layers');
    }

    public function down()
    {
        $this->forge->dropTable('inventory_cost_layers', true);
        $this->forge->dropTable('inventory_kit_items', true);
        $this->forge->dropTable('inventory_serials', true);
        $this->forge->dropTable('inventory_lots', true);
        $this->db->query('ALTER TABLE `inventory_movements` DROP FOREIGN KEY `fk_inventory_movements_source_location_id`');
        $this->db->query('ALTER TABLE `inventory_movements` DROP FOREIGN KEY `fk_inventory_movements_destination_location_id`');
        $this->db->query('ALTER TABLE `inventory_movements` DROP INDEX `idx_inventory_movements_source_location_id`');
        $this->db->query('ALTER TABLE `inventory_movements` DROP INDEX `idx_inventory_movements_destination_location_id`');
        $this->db->query('ALTER TABLE `inventory_stock_levels` DROP FOREIGN KEY `fk_inventory_stock_levels_location_id`');
        $this->db->query('ALTER TABLE `inventory_stock_levels` DROP INDEX `idx_inventory_stock_levels_location_id`');
        $this->forge->dropColumn('inventory_movements', ['source_location_id', 'destination_location_id']);
        $this->forge->dropColumn('inventory_stock_levels', ['location_id']);
        $this->forge->dropTable('inventory_locations', true);
        $this->forge->dropColumn('inventory_products', ['lot_control', 'serial_control', 'expiration_control']);
    }
}
