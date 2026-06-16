<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCustomerSnapshotFieldsToSales extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('sales', [
            'customer_address_snapshot' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'default'    => null,
                'after'      => 'customer_tax_profile',
            ],
            'customer_phone_snapshot' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
                'default'    => null,
                'after'      => 'customer_address_snapshot',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('sales', ['customer_address_snapshot', 'customer_phone_snapshot']);
    }
}
