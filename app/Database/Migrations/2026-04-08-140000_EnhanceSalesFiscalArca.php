<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnhanceSalesFiscalArca extends Migration
{
    public function up()
    {
        $this->forge->addColumn('sales_settings', [
            'arca_alias' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true, 'after' => 'arca_start_activities'],
            'arca_auto_authorize' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'arca_alias'],
            'arca_certificate_expires_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'arca_auto_authorize'],
            'arca_last_wsaa_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'arca_certificate_expires_at'],
            'arca_last_ticket_expires_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'arca_last_wsaa_at'],
            'arca_last_sync_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'arca_last_ticket_expires_at'],
            'arca_last_error' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'arca_last_sync_at'],
        ]);

        $this->forge->addColumn('sales', [
            'arca_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'No Aplica', 'after' => 'payment_status'],
            'arca_service' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'after' => 'arca_status'],
            'arca_operation_mode' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'manual', 'after' => 'arca_service'],
            'cae' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'after' => 'arca_operation_mode'],
            'cae_due_date' => ['type' => 'DATETIME', 'null' => true, 'after' => 'cae'],
            'arca_result_code' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true, 'after' => 'cae_due_date'],
            'arca_result_message' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'arca_result_code'],
            'arca_authorized_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'arca_result_message'],
            'arca_last_checked_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'arca_authorized_at'],
            'arca_request_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true, 'after' => 'arca_last_checked_at'],
        ]);

        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'sale_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'service_slug' => ['type' => 'VARCHAR', 'constraint' => 30],
            'event_type' => ['type' => 'VARCHAR', 'constraint' => 30],
            'environment' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'homologacion'],
            'request_payload' => ['type' => 'LONGTEXT', 'null' => true],
            'response_payload' => ['type' => 'LONGTEXT', 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'pending'],
            'result_code' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'message' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'performed_by' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'performed_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addKey('sale_id');
        $this->forge->addKey('service_slug');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('sale_id', 'sales', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('performed_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('sales_arca_events');
    }

    public function down()
    {
        $this->forge->dropTable('sales_arca_events', true);
        $this->forge->dropColumn('sales', [
            'arca_status',
            'arca_service',
            'arca_operation_mode',
            'cae',
            'cae_due_date',
            'arca_result_code',
            'arca_result_message',
            'arca_authorized_at',
            'arca_last_checked_at',
            'arca_request_id',
        ]);
        $this->forge->dropColumn('sales_settings', [
            'arca_alias',
            'arca_auto_authorize',
            'arca_certificate_expires_at',
            'arca_last_wsaa_at',
            'arca_last_ticket_expires_at',
            'arca_last_sync_at',
            'arca_last_error',
        ]);
    }
}
