<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSalesReceiptsTables extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'customer_id' => ['type' => 'CHAR', 'constraint' => 36],
            'cash_register_id' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'cash_session_id' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'receipt_number' => ['type' => 'VARCHAR', 'constraint' => 60],
            'issue_date' => ['type' => 'DATETIME'],
            'currency_code' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ARS'],
            'payment_method' => ['type' => 'VARCHAR', 'constraint' => 30],
            'total_amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'reference' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'CHAR', 'constraint' => 36],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addKey('customer_id');
        $this->forge->addUniqueKey(['company_id', 'receipt_number']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('customer_id', 'customers', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('cash_register_id', 'cash_registers', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('cash_session_id', 'cash_sessions', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('sales_receipts');

        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'sales_receipt_id' => ['type' => 'CHAR', 'constraint' => 36],
            'sales_receivable_id' => ['type' => 'CHAR', 'constraint' => 36],
            'sale_id' => ['type' => 'CHAR', 'constraint' => 36],
            'document_number' => ['type' => 'VARCHAR', 'constraint' => 60],
            'applied_amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('sales_receipt_id');
        $this->forge->addKey('sales_receivable_id');
        $this->forge->addKey('sale_id');
        $this->forge->addForeignKey('sales_receipt_id', 'sales_receipts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('sales_receivable_id', 'sales_receivables', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('sale_id', 'sales', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('sales_receipt_items');
    }

    public function down()
    {
        $this->forge->dropTable('sales_receipt_items', true);
        $this->forge->dropTable('sales_receipts', true);
    }
}
