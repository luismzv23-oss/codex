<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnhanceSalesErpFoundation extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'code' => ['type' => 'VARCHAR', 'constraint' => 30],
            'name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'category' => ['type' => 'VARCHAR', 'constraint' => 30],
            'letter' => ['type' => 'VARCHAR', 'constraint' => 5, 'null' => true],
            'sequence_key' => ['type' => 'VARCHAR', 'constraint' => 60],
            'default_prefix' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'channel' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'standard'],
            'impacts_stock' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'impacts_receivable' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'requires_customer' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'sort_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addUniqueKey(['company_id', 'code']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('sales_document_types');

        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'branch_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'warehouse_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'document_type_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'code' => ['type' => 'VARCHAR', 'constraint' => 30],
            'channel' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'standard'],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addUniqueKey(['company_id', 'code']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('warehouse_id', 'inventory_warehouses', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('document_type_id', 'sales_document_types', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('sales_points_of_sale');

        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'sale_id' => ['type' => 'CHAR', 'constraint' => 36],
            'customer_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'document_type_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'document_number' => ['type' => 'VARCHAR', 'constraint' => 60],
            'issue_date' => ['type' => 'DATETIME'],
            'due_date' => ['type' => 'DATETIME', 'null' => true],
            'total_amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'paid_amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'balance_amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addKey('customer_id');
        $this->forge->addKey('sale_id');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('sale_id', 'sales', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('customer_id', 'customers', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('document_type_id', 'sales_document_types', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('sales_receivables');

        $this->forge->addColumn('customers', [
            'billing_name' => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true, 'after' => 'name'],
            'document_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'after' => 'document_number'],
            'tax_profile' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'consumidor_final', 'after' => 'document_type'],
            'vat_condition' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true, 'after' => 'tax_profile'],
            'payment_terms_days' => ['type' => 'INT', 'constraint' => 11, 'default' => 0, 'after' => 'custom_discount_rate'],
        ]);

        $this->forge->addColumn('sales', [
            'document_type_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true, 'after' => 'warehouse_id'],
            'point_of_sale_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true, 'after' => 'document_type_id'],
            'document_code' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'after' => 'sale_number'],
            'due_date' => ['type' => 'DATETIME', 'null' => true, 'after' => 'issue_date'],
            'fiscal_profile' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true, 'after' => 'currency_code'],
            'customer_name_snapshot' => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true, 'after' => 'fiscal_profile'],
            'customer_document_snapshot' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true, 'after' => 'customer_name_snapshot'],
            'customer_tax_profile' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true, 'after' => 'customer_document_snapshot'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('sales', [
            'document_type_id',
            'point_of_sale_id',
            'document_code',
            'due_date',
            'fiscal_profile',
            'customer_name_snapshot',
            'customer_document_snapshot',
            'customer_tax_profile',
        ]);

        $this->forge->dropColumn('customers', [
            'billing_name',
            'document_type',
            'tax_profile',
            'vat_condition',
            'payment_terms_days',
        ]);

        $this->forge->dropTable('sales_receivables', true);
        $this->forge->dropTable('sales_points_of_sale', true);
        $this->forge->dropTable('sales_document_types', true);
    }
}
