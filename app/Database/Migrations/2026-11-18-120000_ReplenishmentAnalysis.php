<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Análisis inteligente de reposición: parámetros de compra por producto y
 * historial de corridas del análisis (resultado, resumen IA y órdenes generadas).
 */
class ReplenishmentAnalysis extends Migration
{
    private array $productFields = [
        'preferred_supplier_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
        'lead_time_days' => ['type' => 'INT', 'null' => true],
        'min_order_qty' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'null' => true],
        'order_multiple' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'null' => true],
    ];

    public function up()
    {
        foreach ($this->productFields as $field => $definition) {
            if (! $this->db->fieldExists($field, 'inventory_products')) {
                $this->forge->addColumn('inventory_products', [$field => $definition]);
            }
        }

        if (! $this->db->tableExists('purchase_replenishment_runs')) {
            $this->forge->addField([
                'id' => ['type' => 'CHAR', 'constraint' => 36],
                'company_id' => ['type' => 'CHAR', 'constraint' => 36],
                'warehouse_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
                'params_json' => ['type' => 'TEXT', 'null' => true],
                'summary_json' => ['type' => 'TEXT', 'null' => true],
                'result_json' => ['type' => 'LONGTEXT', 'null' => true],
                'ai_summary' => ['type' => 'TEXT', 'null' => true],
                'ai_provider' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'ai_generated_at' => ['type' => 'DATETIME', 'null' => true],
                'orders_json' => ['type' => 'TEXT', 'null' => true],
                'created_by' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'created_at']);
            $this->forge->createTable('purchase_replenishment_runs');
        }
    }

    public function down()
    {
        $this->forge->dropTable('purchase_replenishment_runs', true);
        foreach (array_keys($this->productFields) as $field) {
            if ($this->db->fieldExists($field, 'inventory_products')) {
                $this->forge->dropColumn('inventory_products', $field);
            }
        }
    }
}
