<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePurchasingTables extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                => ['type' => 'CHAR', 'constraint' => 36],
            'company_id'        => ['type' => 'CHAR', 'constraint' => 36],
            'branch_id'         => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'name'              => ['type' => 'VARCHAR', 'constraint' => 150],
            'legal_name'        => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true],
            'tax_id'            => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'email'             => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'phone'             => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'address'           => ['type' => 'TEXT', 'null' => true],
            'vat_condition'     => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'payment_terms_days'=> ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'active'            => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('suppliers');

        $this->forge->addField([
            'id'                 => ['type' => 'CHAR', 'constraint' => 36],
            'company_id'         => ['type' => 'CHAR', 'constraint' => 36],
            'branch_id'          => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'supplier_id'        => ['type' => 'CHAR', 'constraint' => 36],
            'warehouse_id'       => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'order_number'       => ['type' => 'VARCHAR', 'constraint' => 50],
            'status'             => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'draft'],
            'currency_code'      => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'ARS'],
            'subtotal'           => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'tax_total'          => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'total'              => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'issued_at'          => ['type' => 'DATETIME'],
            'expected_at'        => ['type' => 'DATETIME', 'null' => true],
            'approved_at'        => ['type' => 'DATETIME', 'null' => true],
            'approved_by'        => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'notes'              => ['type' => 'TEXT', 'null' => true],
            'created_by'         => ['type' => 'CHAR', 'constraint' => 36],
            'created_at'         => ['type' => 'DATETIME', 'null' => true],
            'updated_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('supplier_id', 'suppliers', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('warehouse_id', 'inventory_warehouses', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('approved_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('purchase_orders');

        $this->forge->addField([
            'id'                    => ['type' => 'CHAR', 'constraint' => 36],
            'purchase_order_id'     => ['type' => 'CHAR', 'constraint' => 36],
            'product_id'            => ['type' => 'CHAR', 'constraint' => 36],
            'description'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'quantity'              => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'received_quantity'     => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'unit_cost'             => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'tax_rate'              => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'tax_amount'            => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'line_total'            => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'created_at'            => ['type' => 'DATETIME', 'null' => true],
            'updated_at'            => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('purchase_order_id', 'purchase_orders', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('product_id', 'inventory_products', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('purchase_order_items');

        $this->forge->addField([
            'id'                  => ['type' => 'CHAR', 'constraint' => 36],
            'company_id'          => ['type' => 'CHAR', 'constraint' => 36],
            'branch_id'           => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'supplier_id'         => ['type' => 'CHAR', 'constraint' => 36],
            'purchase_order_id'   => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'warehouse_id'        => ['type' => 'CHAR', 'constraint' => 36],
            'receipt_number'      => ['type' => 'VARCHAR', 'constraint' => 50],
            'supplier_document'   => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'status'              => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'registered'],
            'currency_code'       => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'ARS'],
            'subtotal'            => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'tax_total'           => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'total'               => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'issued_at'           => ['type' => 'DATETIME'],
            'received_at'         => ['type' => 'DATETIME'],
            'notes'               => ['type' => 'TEXT', 'null' => true],
            'created_by'          => ['type' => 'CHAR', 'constraint' => 36],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('supplier_id', 'suppliers', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('purchase_order_id', 'purchase_orders', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('warehouse_id', 'inventory_warehouses', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('purchase_receipts');

        $this->forge->addField([
            'id'                    => ['type' => 'CHAR', 'constraint' => 36],
            'purchase_receipt_id'   => ['type' => 'CHAR', 'constraint' => 36],
            'purchase_order_item_id'=> ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'product_id'            => ['type' => 'CHAR', 'constraint' => 36],
            'quantity'              => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'unit_cost'             => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'tax_rate'              => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'tax_amount'            => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'line_total'            => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'lot_number'            => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'serial_number'         => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'expiration_date'       => ['type' => 'DATE', 'null' => true],
            'created_at'            => ['type' => 'DATETIME', 'null' => true],
            'updated_at'            => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('purchase_receipt_id', 'purchase_receipts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('purchase_order_item_id', 'purchase_order_items', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('product_id', 'inventory_products', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('purchase_receipt_items');

        $this->forge->addField([
            'id'                 => ['type' => 'CHAR', 'constraint' => 36],
            'company_id'         => ['type' => 'CHAR', 'constraint' => 36],
            'branch_id'          => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'supplier_id'        => ['type' => 'CHAR', 'constraint' => 36],
            'purchase_receipt_id' => ['type' => 'CHAR', 'constraint' => 36],
            'warehouse_id'       => ['type' => 'CHAR', 'constraint' => 36],
            'return_number'      => ['type' => 'VARCHAR', 'constraint' => 50],
            'status'             => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'issued'],
            'subtotal'           => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'tax_total'          => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'total'              => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'issued_at'          => ['type' => 'DATETIME'],
            'reason'             => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true],
            'notes'              => ['type' => 'TEXT', 'null' => true],
            'created_by'         => ['type' => 'CHAR', 'constraint' => 36],
            'created_at'         => ['type' => 'DATETIME', 'null' => true],
            'updated_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('supplier_id', 'suppliers', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('purchase_receipt_id', 'purchase_receipts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('warehouse_id', 'inventory_warehouses', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('purchase_returns');

        $this->forge->addField([
            'id'                     => ['type' => 'CHAR', 'constraint' => 36],
            'purchase_return_id'     => ['type' => 'CHAR', 'constraint' => 36],
            'purchase_receipt_item_id' => ['type' => 'CHAR', 'constraint' => 36],
            'product_id'             => ['type' => 'CHAR', 'constraint' => 36],
            'quantity'               => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'unit_cost'              => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'tax_rate'               => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'tax_amount'             => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'line_total'             => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'created_at'             => ['type' => 'DATETIME', 'null' => true],
            'updated_at'             => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('purchase_return_id', 'purchase_returns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('purchase_receipt_item_id', 'purchase_receipt_items', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('product_id', 'inventory_products', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('purchase_return_items');

        $this->forge->addField([
            'id'                  => ['type' => 'CHAR', 'constraint' => 36],
            'company_id'          => ['type' => 'CHAR', 'constraint' => 36],
            'supplier_id'         => ['type' => 'CHAR', 'constraint' => 36],
            'purchase_receipt_id' => ['type' => 'CHAR', 'constraint' => 36],
            'payable_number'      => ['type' => 'VARCHAR', 'constraint' => 50],
            'status'              => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
            'currency_code'       => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'ARS'],
            'total_amount'        => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'paid_amount'         => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'balance_amount'      => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'due_date'            => ['type' => 'DATETIME', 'null' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('supplier_id', 'suppliers', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('purchase_receipt_id', 'purchase_receipts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('purchase_payables');

        $this->forge->addField([
            'id'                  => ['type' => 'CHAR', 'constraint' => 36],
            'company_id'          => ['type' => 'CHAR', 'constraint' => 36],
            'supplier_id'         => ['type' => 'CHAR', 'constraint' => 36],
            'purchase_payable_id' => ['type' => 'CHAR', 'constraint' => 36],
            'payment_number'      => ['type' => 'VARCHAR', 'constraint' => 50],
            'payment_method'      => ['type' => 'VARCHAR', 'constraint' => 30],
            'amount'              => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'reference'           => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'paid_at'             => ['type' => 'DATETIME'],
            'notes'               => ['type' => 'TEXT', 'null' => true],
            'created_by'          => ['type' => 'CHAR', 'constraint' => 36],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('supplier_id', 'suppliers', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('purchase_payable_id', 'purchase_payables', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('purchase_payments');

        $this->db->table('systems')->where('slug', 'compras')->update(['entry_url' => 'compras']);
    }

    public function down()
    {
        $this->forge->dropTable('purchase_payments', true);
        $this->forge->dropTable('purchase_payables', true);
        $this->forge->dropTable('purchase_return_items', true);
        $this->forge->dropTable('purchase_returns', true);
        $this->forge->dropTable('purchase_receipt_items', true);
        $this->forge->dropTable('purchase_receipts', true);
        $this->forge->dropTable('purchase_order_items', true);
        $this->forge->dropTable('purchase_orders', true);
        $this->forge->dropTable('suppliers', true);
    }
}
