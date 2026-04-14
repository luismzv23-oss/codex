<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAccountingAndVatTables extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'code' => ['type' => 'VARCHAR', 'constraint' => 30],
            'name' => ['type' => 'VARCHAR', 'constraint' => 160],
            'category' => ['type' => 'VARCHAR', 'constraint' => 30],
            'nature' => ['type' => 'VARCHAR', 'constraint' => 10],
            'system_key' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addUniqueKey(['company_id', 'code']);
        $this->forge->addUniqueKey(['company_id', 'system_key']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('accounting_accounts');

        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'entry_number' => ['type' => 'VARCHAR', 'constraint' => 60],
            'entry_date' => ['type' => 'DATETIME'],
            'source_type' => ['type' => 'VARCHAR', 'constraint' => 40],
            'source_id' => ['type' => 'CHAR', 'constraint' => 36],
            'source_number' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'posted'],
            'total_debit' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'total_credit' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'posted_by' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'posted_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addUniqueKey(['company_id', 'entry_number']);
        $this->forge->addUniqueKey(['company_id', 'source_type', 'source_id']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('posted_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('accounting_entries');

        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'accounting_entry_id' => ['type' => 'CHAR', 'constraint' => 36],
            'account_id' => ['type' => 'CHAR', 'constraint' => 36],
            'line_number' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'debit' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'credit' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'reference_type' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'reference_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('accounting_entry_id');
        $this->forge->addKey('account_id');
        $this->forge->addForeignKey('accounting_entry_id', 'accounting_entries', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('account_id', 'accounting_accounts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('accounting_entry_items');

        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'source_type' => ['type' => 'VARCHAR', 'constraint' => 40],
            'source_id' => ['type' => 'CHAR', 'constraint' => 36],
            'sale_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'document_type_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'document_number' => ['type' => 'VARCHAR', 'constraint' => 80],
            'issue_date' => ['type' => 'DATETIME'],
            'customer_name' => ['type' => 'VARCHAR', 'constraint' => 160],
            'customer_document' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'customer_tax_profile' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'net_amount' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'tax_amount' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'total_amount' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'currency_code' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ARS'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'posted'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addUniqueKey(['company_id', 'source_type', 'source_id']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('sale_id', 'sales', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('document_type_id', 'sales_document_types', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('vat_sales_books');

        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'source_type' => ['type' => 'VARCHAR', 'constraint' => 40],
            'source_id' => ['type' => 'CHAR', 'constraint' => 36],
            'purchase_receipt_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'supplier_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'document_number' => ['type' => 'VARCHAR', 'constraint' => 80],
            'supplier_document' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'issue_date' => ['type' => 'DATETIME'],
            'supplier_name' => ['type' => 'VARCHAR', 'constraint' => 160],
            'supplier_tax_id' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'supplier_vat_condition' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'net_amount' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'tax_amount' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'total_amount' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'currency_code' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ARS'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'posted'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addUniqueKey(['company_id', 'source_type', 'source_id']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('purchase_receipt_id', 'purchase_receipts', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('supplier_id', 'suppliers', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('vat_purchase_books');
    }

    public function down()
    {
        $this->forge->dropTable('vat_purchase_books', true);
        $this->forge->dropTable('vat_sales_books', true);
        $this->forge->dropTable('accounting_entry_items', true);
        $this->forge->dropTable('accounting_entries', true);
        $this->forge->dropTable('accounting_accounts', true);
    }
}
