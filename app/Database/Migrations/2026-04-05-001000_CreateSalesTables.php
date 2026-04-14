<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSalesTables extends Migration
{
    public function up()
    {
        $this->forge->addColumn('inventory_products', [
            'sale_price' => [
                'type' => 'DECIMAL',
                'constraint' => '14,4',
                'default' => '0.0000',
                'after' => 'max_stock',
            ],
            'cost_price' => [
                'type' => 'DECIMAL',
                'constraint' => '14,4',
                'default' => '0.0000',
                'after' => 'sale_price',
            ],
        ]);

        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'company_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'branch_id' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 160],
            'document_number' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'email' => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'phone' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'address' => ['type' => 'TEXT', 'null' => true],
            'price_list_name' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'credit_limit' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'custom_discount_rate' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => '0.00'],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addUniqueKey(['company_id', 'document_number']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('customers');

        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'company_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'branch_id' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'customer_id' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'warehouse_id' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'sale_number' => ['type' => 'VARCHAR', 'constraint' => 60],
            'issue_date' => ['type' => 'DATETIME'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'draft'],
            'payment_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
            'currency_code' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ARS'],
            'price_list_name' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'subtotal' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'item_discount_total' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'global_discount_total' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'tax_total' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'total' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'paid_total' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'VARCHAR', 'constraint' => 36],
            'confirmed_by' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'confirmed_at' => ['type' => 'DATETIME', 'null' => true],
            'cancelled_by' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'cancelled_at' => ['type' => 'DATETIME', 'null' => true],
            'cancellation_reason' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addKey('customer_id');
        $this->forge->addKey('warehouse_id');
        $this->forge->addUniqueKey(['company_id', 'sale_number']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('customer_id', 'customers', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('warehouse_id', 'inventory_warehouses', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('confirmed_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('cancelled_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('sales');

        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'sale_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'product_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'tax_id' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'line_number' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
            'sku' => ['type' => 'VARCHAR', 'constraint' => 60],
            'product_name' => ['type' => 'VARCHAR', 'constraint' => 160],
            'product_type' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'simple'],
            'unit' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'unidad'],
            'quantity' => ['type' => 'DECIMAL', 'constraint' => '14,2'],
            'returned_quantity' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'available_stock_snapshot' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'unit_price' => ['type' => 'DECIMAL', 'constraint' => '14,4'],
            'unit_cost' => ['type' => 'DECIMAL', 'constraint' => '14,4', 'default' => '0.0000'],
            'discount_rate' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => '0.00'],
            'discount_amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'tax_rate' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => '0.00'],
            'subtotal' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'tax_total' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'line_total' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('sale_id');
        $this->forge->addKey('product_id');
        $this->forge->addForeignKey('sale_id', 'sales', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('product_id', 'inventory_products', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('tax_id', 'taxes', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('sale_items');

        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'sale_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'payment_method' => ['type' => 'VARCHAR', 'constraint' => 30],
            'amount' => ['type' => 'DECIMAL', 'constraint' => '14,2'],
            'reference' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'registered'],
            'paid_at' => ['type' => 'DATETIME', 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('sale_id');
        $this->forge->addForeignKey('sale_id', 'sales', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('sale_payments');

        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'sale_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'warehouse_id' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'return_number' => ['type' => 'VARCHAR', 'constraint' => 60],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'confirmed'],
            'credit_note_number' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'total' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'reason' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_by' => ['type' => 'VARCHAR', 'constraint' => 36],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('sale_id');
        $this->forge->addForeignKey('sale_id', 'sales', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('warehouse_id', 'inventory_warehouses', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('sale_returns');

        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'sale_return_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'sale_item_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'product_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'quantity' => ['type' => 'DECIMAL', 'constraint' => '14,2'],
            'unit_price' => ['type' => 'DECIMAL', 'constraint' => '14,4'],
            'line_total' => ['type' => 'DECIMAL', 'constraint' => '14,2'],
            'reason' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('sale_return_id');
        $this->forge->addForeignKey('sale_return_id', 'sale_returns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('sale_item_id', 'sale_items', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('product_id', 'inventory_products', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('sale_return_items');

        $this->db->table('systems')
            ->where('slug', 'ventas')
            ->update([
                'entry_url' => 'ventas',
                'description' => 'Ventas, clientes, pagos y comprobantes integrados con inventario.',
                'icon' => 'bi-cart-check',
            ]);
    }

    public function down()
    {
        $this->forge->dropTable('sale_return_items', true);
        $this->forge->dropTable('sale_returns', true);
        $this->forge->dropTable('sale_payments', true);
        $this->forge->dropTable('sale_items', true);
        $this->forge->dropTable('sales', true);
        $this->forge->dropTable('customers', true);
        $this->forge->dropColumn('inventory_products', ['sale_price', 'cost_price']);
    }
}
