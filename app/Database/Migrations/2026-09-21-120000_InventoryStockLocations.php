<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class InventoryStockLocations extends Migration
{
    public function up()
    {
        $table = $this->db->prefixTable('inventory_stock_levels');
        $quoted = $this->db->protectIdentifiers($table);
        $oldIndex = null;
        foreach ($this->db->getIndexData('inventory_stock_levels') as $index) {
            if ($index->fields === ['product_id', 'warehouse_id'] && strtoupper($index->type) === 'UNIQUE') {
                $oldIndex = $index->name;
            }
        }
        if ($this->db->DBDriver !== 'MySQLi') {
            throw new \RuntimeException('Esta migracion requiere MySQL/MariaDB.');
        }
        $clauses = [
            "ADD COLUMN location_key VARCHAR(36) GENERATED ALWAYS AS (COALESCE(location_id, '')) STORED",
            'ADD UNIQUE KEY uq_inventory_stock_location (company_id, product_id, warehouse_id, location_key)',
        ];
        if ($oldIndex !== null) {
            $clauses[] = 'DROP INDEX ' . $this->db->protectIdentifiers($oldIndex);
        }
        $this->db->query('ALTER TABLE ' . $quoted . ' ' . implode(', ', $clauses));
    }

    public function down()
    {
        $table = $this->db->protectIdentifiers($this->db->prefixTable('inventory_stock_levels'));
        if ($this->db->query('SELECT product_id FROM ' . $table . ' GROUP BY product_id, warehouse_id HAVING COUNT(*) > 1 LIMIT 1')->getRowArray()) {
            throw new \RuntimeException('No se puede revertir: existen saldos en multiples ubicaciones. No se fusionaran automaticamente.');
        }
        $this->db->query('ALTER TABLE ' . $table . ' DROP INDEX uq_inventory_stock_location, DROP COLUMN location_key, ADD UNIQUE KEY product_id_warehouse_id (product_id, warehouse_id)');
    }
}
