<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSalesCommercialTables extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'description' => ['type' => 'TEXT', 'null' => true],
            'is_default' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('sales_price_lists');

        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'price_list_id' => ['type' => 'CHAR', 'constraint' => 36],
            'product_id' => ['type' => 'CHAR', 'constraint' => 36],
            'price' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('price_list_id', 'sales_price_lists', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('product_id', 'inventory_products', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('sales_price_list_items');

        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'description' => ['type' => 'TEXT', 'null' => true],
            'promotion_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'percent'],
            'scope' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'selected'],
            'value' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'start_date' => ['type' => 'DATETIME', 'null' => true],
            'end_date' => ['type' => 'DATETIME', 'null' => true],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('sales_promotions');

        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'promotion_id' => ['type' => 'CHAR', 'constraint' => 36],
            'product_id' => ['type' => 'CHAR', 'constraint' => 36],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('promotion_id', 'sales_promotions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('product_id', 'inventory_products', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('sales_promotion_items');

        $this->forge->addColumn('customers', [
            'price_list_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true, 'after' => 'price_list_name'],
        ]);
        $this->forge->addColumn('sales', [
            'price_list_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true, 'after' => 'price_list_name'],
            'promotion_snapshot' => ['type' => 'TEXT', 'null' => true, 'after' => 'price_list_id'],
            'pos_mode' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'promotion_snapshot'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('sales', ['price_list_id', 'promotion_snapshot', 'pos_mode']);
        $this->forge->dropColumn('customers', 'price_list_id');
        $this->forge->dropTable('sales_promotion_items', true);
        $this->forge->dropTable('sales_promotions', true);
        $this->forge->dropTable('sales_price_list_items', true);
        $this->forge->dropTable('sales_price_lists', true);
    }
}
