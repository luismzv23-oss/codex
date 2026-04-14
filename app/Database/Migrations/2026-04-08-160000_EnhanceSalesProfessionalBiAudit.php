<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnhanceSalesProfessionalBiAudit extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'email' => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'phone' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'commission_rate' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'default' => '0.00'],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('sales_agents');

        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'description' => ['type' => 'TEXT', 'null' => true],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('sales_zones');

        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'code' => ['type' => 'VARCHAR', 'constraint' => 40],
            'payment_terms_days' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'credit_limit' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'requires_invoice' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addUniqueKey(['company_id', 'code']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('sales_conditions');

        $this->forge->addColumn('customers', [
            'sales_agent_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true, 'after' => 'price_list_id'],
            'sales_zone_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true, 'after' => 'sales_agent_id'],
            'sales_condition_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true, 'after' => 'sales_zone_id'],
        ]);

        $this->forge->addColumn('sales', [
            'sales_agent_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true, 'after' => 'point_of_sale_id'],
            'sales_zone_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true, 'after' => 'sales_agent_id'],
            'sales_condition_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true, 'after' => 'sales_zone_id'],
            'margin_total' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00', 'after' => 'tax_total'],
        ]);

        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'module' => ['type' => 'VARCHAR', 'constraint' => 50],
            'entity_type' => ['type' => 'VARCHAR', 'constraint' => 50],
            'entity_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'action' => ['type' => 'VARCHAR', 'constraint' => 50],
            'before_payload' => ['type' => 'LONGTEXT', 'null' => true],
            'after_payload' => ['type' => 'LONGTEXT', 'null' => true],
            'user_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addKey('entity_id');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('audit_logs');

        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'module' => ['type' => 'VARCHAR', 'constraint' => 50],
            'document_type' => ['type' => 'VARCHAR', 'constraint' => 50],
            'document_id' => ['type' => 'CHAR', 'constraint' => 36],
            'event_type' => ['type' => 'VARCHAR', 'constraint' => 50],
            'payload' => ['type' => 'LONGTEXT', 'null' => true],
            'user_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addKey('document_id');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('document_events');

        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'provider' => ['type' => 'VARCHAR', 'constraint' => 50],
            'service' => ['type' => 'VARCHAR', 'constraint' => 80],
            'reference_type' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'reference_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'pending'],
            'request_payload' => ['type' => 'LONGTEXT', 'null' => true],
            'response_payload' => ['type' => 'LONGTEXT', 'null' => true],
            'message' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'user_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addKey('reference_id');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('integration_logs');
    }

    public function down()
    {
        $this->forge->dropTable('integration_logs', true);
        $this->forge->dropTable('document_events', true);
        $this->forge->dropTable('audit_logs', true);
        $this->forge->dropColumn('sales', ['sales_agent_id', 'sales_zone_id', 'sales_condition_id', 'margin_total']);
        $this->forge->dropColumn('customers', ['sales_agent_id', 'sales_zone_id', 'sales_condition_id']);
        $this->forge->dropTable('sales_conditions', true);
        $this->forge->dropTable('sales_zones', true);
        $this->forge->dropTable('sales_agents', true);
    }
}
