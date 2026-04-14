<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnhanceSalesCommercialMasters extends Migration
{
    public function up()
    {
        $this->forge->addColumn('sales_agents', [
            'code' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true, 'after' => 'name'],
            'notes' => ['type' => 'TEXT', 'null' => true, 'after' => 'commission_rate'],
        ]);

        $this->forge->addColumn('sales_zones', [
            'code' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true, 'after' => 'name'],
            'region' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true, 'after' => 'code'],
        ]);

        $this->forge->addColumn('sales_conditions', [
            'discount_rate' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'default' => '0.00', 'after' => 'credit_limit'],
            'requires_authorization' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'requires_invoice'],
            'notes' => ['type' => 'TEXT', 'null' => true, 'after' => 'requires_authorization'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('sales_conditions', ['discount_rate', 'requires_authorization', 'notes']);
        $this->forge->dropColumn('sales_zones', ['code', 'region']);
        $this->forge->dropColumn('sales_agents', ['code', 'notes']);
    }
}
