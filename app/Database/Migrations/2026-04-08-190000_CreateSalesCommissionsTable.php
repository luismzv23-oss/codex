<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSalesCommissionsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'sale_id' => ['type' => 'CHAR', 'constraint' => 36],
            'sales_agent_id' => ['type' => 'CHAR', 'constraint' => 36],
            'base_amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'rate' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'default' => '0.00'],
            'commission_amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'liquidated_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['sale_id', 'sales_agent_id']);
        $this->forge->addKey('company_id');
        $this->forge->addKey('sales_agent_id');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('sale_id', 'sales', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('sales_agent_id', 'sales_agents', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('sales_commissions');
    }

    public function down()
    {
        $this->forge->dropTable('sales_commissions', true);
    }
}
