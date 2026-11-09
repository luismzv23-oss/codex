<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Invoice assistant drives the whole purchase circuit:
 * - units per package on supplier links and invoice lines (15 packs x 6 = 90 stock units);
 * - 4-decimal unit cost on order/receipt lines, like cost layers and movements (7149.58 / 6 = 1191.5967);
 * - order and receipt generated from each import.
 */
class PurchaseAssistantCircuit extends Migration
{
    public function up()
    {
        $package = ['units_per_package' => ['type' => 'DECIMAL', 'constraint' => '12,4', 'null' => false, 'default' => 1]];
        foreach (['supplier_product_links', 'purchase_invoice_items'] as $table) {
            if (! $this->db->fieldExists('units_per_package', $table)) {
                $this->forge->addColumn($table, $package);
            }
        }
        foreach (['purchase_order_id', 'purchase_receipt_id'] as $field) {
            if (! $this->db->fieldExists($field, 'purchase_imports')) {
                $this->forge->addColumn('purchase_imports', [$field => ['type' => 'CHAR', 'constraint' => 36, 'null' => true]]);
            }
        }
        if ($this->db->DBDriver !== 'SQLite3') {
            foreach (['purchase_order_items', 'purchase_receipt_items'] as $table) {
                $this->forge->modifyColumn($table, ['unit_cost' => ['name' => 'unit_cost', 'type' => 'DECIMAL', 'constraint' => '15,4', 'null' => false, 'default' => 0]]);
            }
        }
    }

    public function down()
    {
        if ($this->db->DBDriver !== 'SQLite3') {
            foreach (['purchase_order_items', 'purchase_receipt_items'] as $table) {
                $this->forge->modifyColumn($table, ['unit_cost' => ['name' => 'unit_cost', 'type' => 'DECIMAL', 'constraint' => '15,2', 'null' => false, 'default' => 0]]);
            }
        }
        foreach (['purchase_order_id', 'purchase_receipt_id'] as $field) {
            if ($this->db->fieldExists($field, 'purchase_imports')) {
                $this->forge->dropColumn('purchase_imports', $field);
            }
        }
        foreach (['supplier_product_links', 'purchase_invoice_items'] as $table) {
            if ($this->db->fieldExists('units_per_package', $table)) {
                $this->forge->dropColumn($table, 'units_per_package');
            }
        }
    }
}
