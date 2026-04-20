<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAutomationAndNotificationTables extends Migration
{
    public function up()
    {
        // ── Event Log ────────────────────────────────────
        $this->forge->addField([
            'id'              => ['type' => 'CHAR', 'constraint' => 36],
            'event'           => ['type' => 'VARCHAR', 'constraint' => 100],
            'payload'         => ['type' => 'LONGTEXT', 'null' => true],
            'listeners_count' => ['type' => 'INT', 'default' => 0],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('event');
        $this->forge->createTable('event_log', true);

        // ── Automation Log ───────────────────────────────
        $this->forge->addField([
            'id'            => ['type' => 'CHAR', 'constraint' => 36],
            'company_id'    => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'event'         => ['type' => 'VARCHAR', 'constraint' => 100],
            'actions'       => ['type' => 'LONGTEXT', 'null' => true],
            'actions_count' => ['type' => 'INT', 'default' => 0],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->createTable('automation_log', true);

        // ── Notifications ────────────────────────────────
        $this->forge->addField([
            'id'         => ['type' => 'CHAR', 'constraint' => 36],
            'company_id' => ['type' => 'CHAR', 'constraint' => 36],
            'user_id'    => ['type' => 'CHAR', 'constraint' => 36],
            'type'       => ['type' => 'VARCHAR', 'constraint' => 50],
            'title'      => ['type' => 'VARCHAR', 'constraint' => 200],
            'message'    => ['type' => 'TEXT'],
            'url'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'priority'   => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'normal'],
            'read_at'    => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['user_id', 'read_at']);
        $this->forge->addKey('company_id');
        $this->forge->createTable('notifications', true);

        // ── Workflow Templates ───────────────────────────
        $this->forge->addField([
            'id'           => ['type' => 'CHAR', 'constraint' => 36],
            'company_id'   => ['type' => 'CHAR', 'constraint' => 36],
            'name'         => ['type' => 'VARCHAR', 'constraint' => 100],
            'trigger_event'=> ['type' => 'VARCHAR', 'constraint' => 100],
            'conditions'   => ['type' => 'LONGTEXT', 'null' => true, 'comment' => 'JSON conditions'],
            'actions'      => ['type' => 'LONGTEXT', 'null' => true, 'comment' => 'JSON action definitions'],
            'active'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->createTable('workflow_templates', true);
    }

    public function down()
    {
        $this->forge->dropTable('workflow_templates', true);
        $this->forge->dropTable('notifications', true);
        $this->forge->dropTable('automation_log', true);
        $this->forge->dropTable('event_log', true);
    }
}
