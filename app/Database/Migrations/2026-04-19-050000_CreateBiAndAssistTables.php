<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBiAndAssistTables extends Migration
{
    public function up()
    {
        // ── Codex Assist interaction log ──────────────────
        $this->forge->addField([
            'id'          => ['type' => 'CHAR', 'constraint' => 36],
            'user_id'     => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'company_id'  => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'question'    => ['type' => 'TEXT'],
            'answer'      => ['type' => 'LONGTEXT'],
            'provider'    => ['type' => 'VARCHAR', 'constraint' => 20],
            'model'       => ['type' => 'VARCHAR', 'constraint' => 50],
            'duration_ms' => ['type' => 'INT', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('user_id');
        $this->forge->createTable('codex_assist_log', true);

        // ── Saved KPI dashboards ─────────────────────────
        $this->forge->addField([
            'id'          => ['type' => 'CHAR', 'constraint' => 36],
            'company_id'  => ['type' => 'CHAR', 'constraint' => 36],
            'user_id'     => ['type' => 'CHAR', 'constraint' => 36],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'config'      => ['type' => 'LONGTEXT', 'comment' => 'JSON dashboard configuration'],
            'is_default'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->createTable('saved_dashboards', true);

        // ── Scheduled reports ────────────────────────────
        $this->forge->addField([
            'id'          => ['type' => 'CHAR', 'constraint' => 36],
            'company_id'  => ['type' => 'CHAR', 'constraint' => 36],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'report_type' => ['type' => 'VARCHAR', 'constraint' => 50, 'comment' => 'sales_summary|inventory|balance_sheet|libro_iva'],
            'schedule'    => ['type' => 'VARCHAR', 'constraint' => 20, 'comment' => 'daily|weekly|monthly'],
            'recipients'  => ['type' => 'TEXT', 'comment' => 'JSON array of email addresses'],
            'last_sent_at'=> ['type' => 'DATETIME', 'null' => true],
            'active'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->createTable('scheduled_reports', true);
    }

    public function down()
    {
        $this->forge->dropTable('scheduled_reports', true);
        $this->forge->dropTable('saved_dashboards', true);
        $this->forge->dropTable('codex_assist_log', true);
    }
}
