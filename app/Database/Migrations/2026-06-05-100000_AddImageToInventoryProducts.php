<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddImageToInventoryProducts extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('inventory_products', [
            'image' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'description',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('inventory_products', 'image');
    }
}
