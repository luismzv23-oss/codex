<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnhanceInventoryModule extends Migration
{
    public function up()
    {
        $this->forge->addColumn('inventory_settings', [
            'valuation_method' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'weighted_average',
                'after' => 'email_notifications',
            ],
        ]);

        $this->forge->addColumn('inventory_products', [
            'category' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
                'after' => 'name',
            ],
            'brand' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
                'after' => 'category',
            ],
            'barcode' => [
                'type' => 'VARCHAR',
                'constraint' => 80,
                'null' => true,
                'after' => 'brand',
            ],
            'product_type' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'simple',
                'after' => 'barcode',
            ],
            'max_stock' => [
                'type' => 'DECIMAL',
                'constraint' => '14,2',
                'default' => '0.00',
                'after' => 'min_stock',
            ],
        ]);

        $this->forge->addColumn('inventory_stock_levels', [
            'reserved_quantity' => [
                'type' => 'DECIMAL',
                'constraint' => '14,2',
                'default' => '0.00',
                'after' => 'quantity',
            ],
            'location_label' => [
                'type' => 'VARCHAR',
                'constraint' => 120,
                'null' => true,
                'after' => 'min_stock',
            ],
        ]);

        $this->forge->addColumn('inventory_movements', [
            'unit_cost' => [
                'type' => 'DECIMAL',
                'constraint' => '14,4',
                'null' => true,
                'after' => 'quantity',
            ],
            'total_cost' => [
                'type' => 'DECIMAL',
                'constraint' => '14,4',
                'null' => true,
                'after' => 'unit_cost',
            ],
            'source_document' => [
                'type' => 'VARCHAR',
                'constraint' => 120,
                'null' => true,
                'after' => 'reason',
            ],
            'lot_number' => [
                'type' => 'VARCHAR',
                'constraint' => 80,
                'null' => true,
                'after' => 'source_document',
            ],
            'serial_number' => [
                'type' => 'VARCHAR',
                'constraint' => 80,
                'null' => true,
                'after' => 'lot_number',
            ],
            'expiration_date' => [
                'type' => 'DATE',
                'null' => true,
                'after' => 'serial_number',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('inventory_movements', ['unit_cost', 'total_cost', 'source_document', 'lot_number', 'serial_number', 'expiration_date']);
        $this->forge->dropColumn('inventory_stock_levels', ['reserved_quantity', 'location_label']);
        $this->forge->dropColumn('inventory_products', ['category', 'brand', 'barcode', 'product_type', 'max_stock']);
        $this->forge->dropColumn('inventory_settings', ['valuation_method']);
    }
}
