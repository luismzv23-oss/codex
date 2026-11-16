<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Weekly cycle counts (blind physical counts with approval) and the weekly stock alert report. */
class StockCycleCounts extends Migration
{
    public function up()
    {
        $id = ['type' => 'CHAR', 'constraint' => 36];
        $nullId = ['type' => 'CHAR', 'constraint' => 36, 'null' => true];
        $qty = ['type' => 'DECIMAL', 'constraint' => '14,2', 'null' => true];
        $stamps = ['created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true]];
        if (! $this->db->tableExists('inventory_counts')) {
            $this->forge->addField(['id' => $id, 'company_id' => $id, 'warehouse_id' => $id, 'count_number' => ['type' => 'VARCHAR', 'constraint' => 30],
                'week' => ['type' => 'VARCHAR', 'constraint' => 10], 'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'open'],
                'items_count' => ['type' => 'INT', 'default' => 0], 'difference_value' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'default' => 0],
                'created_by' => $nullId, 'approved_by' => $nullId, 'approved_at' => ['type' => 'DATETIME', 'null' => true], 'notes' => ['type' => 'TEXT', 'null' => true]] + $stamps);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'week']);
            $this->forge->createTable('inventory_counts');
        }
        if (! $this->db->tableExists('inventory_count_items')) {
            $this->forge->addField(['id' => $id, 'count_id' => $id, 'product_id' => $id, 'selection_reason' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'system_quantity' => $qty, 'counted_quantity' => $qty, 'difference' => $qty, 'unit_cost' => ['type' => 'DECIMAL', 'constraint' => '14,4', 'null' => true],
                'difference_value' => ['type' => 'DECIMAL', 'constraint' => '16,2', 'null' => true], 'counted_at' => ['type' => 'DATETIME', 'null' => true],
                'moved_after_count' => ['type' => 'TINYINT', 'default' => 0], 'reason' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'approved' => ['type' => 'TINYINT', 'default' => 0], 'movement_id' => $nullId] + $stamps);
            $this->forge->addKey('id', true);
            $this->forge->addKey('count_id');
            $this->forge->createTable('inventory_count_items');
        }
        if (! $this->db->tableExists('inventory_count_reports')) {
            $this->forge->addField(['id' => $id, 'company_id' => $id, 'week' => ['type' => 'VARCHAR', 'constraint' => 10], 'report_json' => ['type' => 'LONGTEXT', 'null' => true], 'created_by' => $nullId] + $stamps);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'week']);
            $this->forge->createTable('inventory_count_reports');
        }
        if ($this->db->tableExists('inventory_settings')) {
            foreach (['cycle_count_size' => ['type' => 'INT', 'null' => true], 'cycle_count_threshold_pct' => ['type' => 'DECIMAL', 'constraint' => '6,2', 'default' => 10]] as $field => $def) {
                if (! $this->db->fieldExists($field, 'inventory_settings')) {
                    $this->forge->addColumn('inventory_settings', [$field => $def]);
                }
            }
        }
    }

    public function down()
    {
        foreach (['inventory_count_reports', 'inventory_count_items', 'inventory_counts'] as $table) {
            $this->forge->dropTable($table, true);
        }
        foreach (['cycle_count_size', 'cycle_count_threshold_pct'] as $field) {
            if ($this->db->fieldExists($field, 'inventory_settings')) {
                $this->forge->dropColumn('inventory_settings', $field);
            }
        }
    }
}
