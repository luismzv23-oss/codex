<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateParityGapTables extends Migration
{
    public function up()
    {
        $this->createCashPaymentGateways();
        $this->createCashChecks();
        $this->createCashReconciliations();
        $this->alterCashAndPaymentTables();

        $this->createSalesDiscountPolicies();
        $this->createSalesAuthorizations();
        $this->createSalesCreditFlags();
        $this->alterSalesTables();

        $this->createPurchaseInvoices();
        $this->createPurchaseInvoiceItems();
        $this->createPurchaseCreditNotes();
        $this->createSupplierCostHistory();
        $this->createSupplierExchangeDifferences();
        $this->alterPurchasePayments();

        $this->createInventoryAssemblies();
        $this->createInventoryAssemblyItems();
        $this->createInventoryPeriodClosures();
        $this->createInventoryRevaluations();
        $this->alterInventorySettings();

        $this->createPosDeviceSettings();
        $this->createHardwareLogs();
        $this->createQaRuns();
    }

    public function down()
    {
        $this->forge->dropTable('qa_runs', true);
        $this->forge->dropTable('hardware_logs', true);
        $this->forge->dropTable('pos_device_settings', true);
        $this->forge->dropTable('inventory_revaluations', true);
        $this->forge->dropTable('inventory_period_closures', true);
        $this->forge->dropTable('inventory_assembly_items', true);
        $this->forge->dropTable('inventory_assemblies', true);
        $this->forge->dropTable('supplier_exchange_differences', true);
        $this->forge->dropTable('supplier_cost_history', true);
        $this->forge->dropTable('purchase_credit_notes', true);
        $this->forge->dropTable('purchase_invoice_items', true);
        $this->forge->dropTable('purchase_invoices', true);
        $this->forge->dropTable('sales_credit_flags', true);
        $this->forge->dropTable('sales_authorizations', true);
        $this->forge->dropTable('sales_discount_policies', true);
        $this->forge->dropTable('cash_reconciliations', true);
        $this->forge->dropTable('cash_checks', true);
        $this->forge->dropTable('cash_payment_gateways', true);
    }

    private function createCashPaymentGateways(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'code' => ['type' => 'VARCHAR', 'constraint' => 40],
            'gateway_type' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'gateway'],
            'provider' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'settings_json' => ['type' => 'TEXT', 'null' => true],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'code']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cash_payment_gateways', true);
    }

    private function createCashChecks(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'supplier_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'customer_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'check_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'received'],
            'check_number' => ['type' => 'VARCHAR', 'constraint' => 60],
            'bank_name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'issuer_name' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'due_date' => ['type' => 'DATE', 'null' => true],
            'amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'portfolio'],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'check_number', 'bank_name']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('supplier_id', 'suppliers', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('customer_id', 'customers', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('cash_checks', true);
    }

    private function createCashReconciliations(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'cash_session_id' => ['type' => 'CHAR', 'constraint' => 36],
            'payment_method' => ['type' => 'VARCHAR', 'constraint' => 30],
            'expected_amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'actual_amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'difference_amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('cash_session_id', 'cash_sessions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cash_reconciliations', true);
    }

    private function alterCashAndPaymentTables(): void
    {
        if (! $this->db->fieldExists('gateway_id', 'cash_movements')) {
            $this->forge->addColumn('cash_movements', [
                'gateway_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true, 'after' => 'payment_method'],
                'cash_check_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true, 'after' => 'gateway_id'],
                'external_reference' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true, 'after' => 'reference_number'],
                'reconciliation_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending', 'after' => 'external_reference'],
            ]);
        }

        if (! $this->db->fieldExists('gateway_id', 'sales_receipts')) {
            $this->forge->addColumn('sales_receipts', [
                'gateway_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true, 'after' => 'payment_method'],
                'cash_check_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true, 'after' => 'gateway_id'],
                'external_reference' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true, 'after' => 'reference'],
            ]);
        }
    }

    private function createSalesDiscountPolicies(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'policy_type' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'payment_method_discount'],
            'payment_method' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'min_quantity' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'buy_quantity' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'pay_quantity' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'discount_rate' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'default' => '0.00'],
            'fixed_discount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('sales_discount_policies', true);
    }

    private function createSalesAuthorizations(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'sale_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'authorization_type' => ['type' => 'VARCHAR', 'constraint' => 30],
            'reason' => ['type' => 'TEXT', 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
            'requested_by' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'resolved_by' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'resolved_at' => ['type' => 'DATETIME', 'null' => true],
            'resolution_notes' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('sale_id', 'sales', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('sales_authorizations', true);
    }

    private function createSalesCreditFlags(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'customer_id' => ['type' => 'CHAR', 'constraint' => 36],
            'flag_type' => ['type' => 'VARCHAR', 'constraint' => 30],
            'score_value' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'default' => '0.00'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('customer_id', 'customers', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('sales_credit_flags', true);
    }

    private function alterSalesTables(): void
    {
        if (! $this->db->fieldExists('trigger_quantity', 'sales_promotions')) {
            $this->forge->addColumn('sales_promotions', [
                'trigger_quantity' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00', 'after' => 'value'],
                'bonus_quantity' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00', 'after' => 'trigger_quantity'],
                'bonus_product_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true, 'after' => 'bonus_quantity'],
                'payment_method' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'after' => 'bonus_product_id'],
                'bundle_price' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00', 'after' => 'payment_method'],
            ]);
        }

        if (! $this->db->fieldExists('authorization_status', 'sales')) {
            $this->forge->addColumn('sales', [
                'authorization_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'not_required', 'after' => 'status'],
                'authorization_reason' => ['type' => 'TEXT', 'null' => true, 'after' => 'authorization_status'],
                'credit_score_snapshot' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'default' => '0.00', 'after' => 'authorization_reason'],
                'external_transaction_reference' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true, 'after' => 'credit_score_snapshot'],
            ]);
        }
    }

    private function createPurchaseInvoices(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'supplier_id' => ['type' => 'CHAR', 'constraint' => 36],
            'purchase_receipt_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'invoice_number' => ['type' => 'VARCHAR', 'constraint' => 60],
            'currency_code' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'ARS'],
            'exchange_rate' => ['type' => 'DECIMAL', 'constraint' => '14,6', 'default' => '1.000000'],
            'subtotal' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'tax_total' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'total' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'issue_date' => ['type' => 'DATETIME', 'null' => true],
            'due_date' => ['type' => 'DATETIME', 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'draft'],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'invoice_number']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('supplier_id', 'suppliers', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('purchase_receipt_id', 'purchase_receipts', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('purchase_invoices', true);
    }

    private function createPurchaseInvoiceItems(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'purchase_invoice_id' => ['type' => 'CHAR', 'constraint' => 36],
            'product_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255],
            'quantity' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'unit_cost' => ['type' => 'DECIMAL', 'constraint' => '14,4', 'default' => '0.0000'],
            'tax_rate' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'default' => '0.00'],
            'tax_amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'line_total' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('purchase_invoice_id', 'purchase_invoices', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('product_id', 'inventory_products', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('purchase_invoice_items', true);
    }

    private function createPurchaseCreditNotes(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'supplier_id' => ['type' => 'CHAR', 'constraint' => 36],
            'purchase_invoice_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'credit_note_number' => ['type' => 'VARCHAR', 'constraint' => 60],
            'amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'issue_date' => ['type' => 'DATETIME', 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'issued'],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'credit_note_number']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('supplier_id', 'suppliers', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('purchase_invoice_id', 'purchase_invoices', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('purchase_credit_notes', true);
    }

    private function createSupplierCostHistory(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'supplier_id' => ['type' => 'CHAR', 'constraint' => 36],
            'product_id' => ['type' => 'CHAR', 'constraint' => 36],
            'purchase_receipt_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'purchase_invoice_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'currency_code' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'ARS'],
            'exchange_rate' => ['type' => 'DECIMAL', 'constraint' => '14,6', 'default' => '1.000000'],
            'unit_cost' => ['type' => 'DECIMAL', 'constraint' => '14,4', 'default' => '0.0000'],
            'observed_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('supplier_id', 'suppliers', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('product_id', 'inventory_products', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('purchase_receipt_id', 'purchase_receipts', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('purchase_invoice_id', 'purchase_invoices', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('supplier_cost_history', true);
    }

    private function createSupplierExchangeDifferences(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'purchase_invoice_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'purchase_payment_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'currency_code' => ['type' => 'VARCHAR', 'constraint' => 10],
            'base_rate' => ['type' => 'DECIMAL', 'constraint' => '14,6', 'default' => '1.000000'],
            'settlement_rate' => ['type' => 'DECIMAL', 'constraint' => '14,6', 'default' => '1.000000'],
            'difference_amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('purchase_invoice_id', 'purchase_invoices', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('purchase_payment_id', 'purchase_payments', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('supplier_exchange_differences', true);
    }

    private function alterPurchasePayments(): void
    {
        if (! $this->db->fieldExists('gateway_id', 'purchase_payments')) {
            $this->forge->addColumn('purchase_payments', [
                'gateway_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true, 'after' => 'payment_method'],
                'cash_check_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true, 'after' => 'gateway_id'],
                'currency_code' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'ARS', 'after' => 'cash_check_id'],
                'exchange_rate' => ['type' => 'DECIMAL', 'constraint' => '14,6', 'default' => '1.000000', 'after' => 'currency_code'],
                'external_reference' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true, 'after' => 'reference'],
            ]);
        }
    }

    private function createInventoryAssemblies(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'warehouse_id' => ['type' => 'CHAR', 'constraint' => 36],
            'product_id' => ['type' => 'CHAR', 'constraint' => 36],
            'assembly_number' => ['type' => 'VARCHAR', 'constraint' => 60],
            'assembly_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'assemble'],
            'quantity' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'unit_cost' => ['type' => 'DECIMAL', 'constraint' => '14,4', 'default' => '0.0000'],
            'total_cost' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'issued_at' => ['type' => 'DATETIME', 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'assembly_number']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('warehouse_id', 'inventory_warehouses', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('product_id', 'inventory_products', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('inventory_assemblies', true);
    }

    private function createInventoryAssemblyItems(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'inventory_assembly_id' => ['type' => 'CHAR', 'constraint' => 36],
            'component_product_id' => ['type' => 'CHAR', 'constraint' => 36],
            'quantity' => ['type' => 'DECIMAL', 'constraint' => '14,4', 'default' => '0.0000'],
            'unit_cost' => ['type' => 'DECIMAL', 'constraint' => '14,4', 'default' => '0.0000'],
            'total_cost' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('inventory_assembly_id', 'inventory_assemblies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('component_product_id', 'inventory_products', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('inventory_assembly_items', true);
    }

    private function createInventoryPeriodClosures(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'warehouse_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'period_code' => ['type' => 'VARCHAR', 'constraint' => 20],
            'start_date' => ['type' => 'DATE'],
            'end_date' => ['type' => 'DATE'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'closed'],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'warehouse_id', 'period_code']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('warehouse_id', 'inventory_warehouses', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('inventory_period_closures', true);
    }

    private function createInventoryRevaluations(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'product_id' => ['type' => 'CHAR', 'constraint' => 36],
            'warehouse_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'previous_unit_cost' => ['type' => 'DECIMAL', 'constraint' => '14,4', 'default' => '0.0000'],
            'new_unit_cost' => ['type' => 'DECIMAL', 'constraint' => '14,4', 'default' => '0.0000'],
            'quantity_snapshot' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'difference_amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'issued_at' => ['type' => 'DATETIME', 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('product_id', 'inventory_products', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('warehouse_id', 'inventory_warehouses', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('inventory_revaluations', true);
    }

    private function alterInventorySettings(): void
    {
        if (! $this->db->fieldExists('negative_stock_scope', 'inventory_settings')) {
            $this->forge->addColumn('inventory_settings', [
                'negative_stock_scope' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'company', 'after' => 'allow_negative_stock'],
                'allow_negative_on_sales' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'negative_stock_scope'],
                'allow_negative_on_transfers' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'allow_negative_on_sales'],
                'allow_negative_on_adjustments' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'after' => 'allow_negative_on_transfers'],
            ]);
        }
    }

    private function createPosDeviceSettings(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'channel' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pos'],
            'device_type' => ['type' => 'VARCHAR', 'constraint' => 30],
            'device_name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'device_code' => ['type' => 'VARCHAR', 'constraint' => 60],
            'settings_json' => ['type' => 'TEXT', 'null' => true],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'channel', 'device_code']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('pos_device_settings', true);
    }

    private function createHardwareLogs(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'channel' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pos'],
            'device_type' => ['type' => 'VARCHAR', 'constraint' => 30],
            'event_type' => ['type' => 'VARCHAR', 'constraint' => 30],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
            'reference_type' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'reference_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'payload_json' => ['type' => 'TEXT', 'null' => true],
            'message' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('hardware_logs', true);
    }

    private function createQaRuns(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'module_name' => ['type' => 'VARCHAR', 'constraint' => 40],
            'scenario_code' => ['type' => 'VARCHAR', 'constraint' => 80],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'executed_by' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'executed_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('qa_runs', true);
    }
}
