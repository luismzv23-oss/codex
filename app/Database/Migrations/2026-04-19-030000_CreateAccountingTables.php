<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAccountingTables extends Migration
{
    public function up()
    {
        // ── 1. Chart of Accounts (Plan de Cuentas) ───────
        $this->forge->addField([
            'id'            => ['type' => 'CHAR', 'constraint' => 36],
            'company_id'    => ['type' => 'CHAR', 'constraint' => 36],
            'parent_id'     => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'code'          => ['type' => 'VARCHAR', 'constraint' => 30],
            'name'          => ['type' => 'VARCHAR', 'constraint' => 150],
            'account_type'  => ['type' => 'VARCHAR', 'constraint' => 30, 'comment' => 'asset|liability|equity|revenue|expense'],
            'is_group'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'level'         => ['type' => 'TINYINT', 'constraint' => 3, 'default' => 1],
            'accepts_entries' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'currency_code' => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'ARS'],
            'opening_balance' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
            'active'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['company_id', 'code']);
        $this->forge->addKey('parent_id');
        $this->forge->createTable('accounts', true);

        // ── 2. Journal Entries (Asientos Contables) ──────
        $this->forge->addField([
            'id'             => ['type' => 'CHAR', 'constraint' => 36],
            'company_id'     => ['type' => 'CHAR', 'constraint' => 36],
            'entry_number'   => ['type' => 'INT', 'unsigned' => true],
            'entry_date'     => ['type' => 'DATE'],
            'description'    => ['type' => 'VARCHAR', 'constraint' => 255],
            'reference_type' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'comment' => 'sale|purchase|payment|manual'],
            'reference_id'   => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'status'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'draft', 'comment' => 'draft|posted|cancelled'],
            'total_debit'    => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
            'total_credit'   => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
            'user_id'        => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'posted_at'      => ['type' => 'DATETIME', 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['company_id', 'entry_number']);
        $this->forge->addKey('entry_date');
        $this->forge->createTable('journal_entries', true);

        // ── 3. Journal Entry Lines ───────────────────────
        $this->forge->addField([
            'id'             => ['type' => 'CHAR', 'constraint' => 36],
            'journal_entry_id' => ['type' => 'CHAR', 'constraint' => 36],
            'account_id'     => ['type' => 'CHAR', 'constraint' => 36],
            'description'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'debit'          => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
            'credit'         => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('journal_entry_id');
        $this->forge->addKey('account_id');
        $this->forge->addForeignKey('journal_entry_id', 'journal_entries', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('journal_entry_lines', true);

        // ── 4. Fiscal Periods (Ejercicios) ───────────────
        $this->forge->addField([
            'id'           => ['type' => 'CHAR', 'constraint' => 36],
            'company_id'   => ['type' => 'CHAR', 'constraint' => 36],
            'name'         => ['type' => 'VARCHAR', 'constraint' => 50],
            'start_date'   => ['type' => 'DATE'],
            'end_date'     => ['type' => 'DATE'],
            'status'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'open', 'comment' => 'open|closed|locked'],
            'closed_at'    => ['type' => 'DATETIME', 'null' => true],
            'closed_by'    => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->createTable('fiscal_periods', true);

        // ── 5. Cost Centers ──────────────────────────────
        $this->forge->addField([
            'id'          => ['type' => 'CHAR', 'constraint' => 36],
            'company_id'  => ['type' => 'CHAR', 'constraint' => 36],
            'code'        => ['type' => 'VARCHAR', 'constraint' => 20],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'active'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->createTable('cost_centers', true);
    }

    public function down()
    {
        $this->forge->dropTable('cost_centers', true);
        $this->forge->dropTable('fiscal_periods', true);
        $this->forge->dropTable('journal_entry_lines', true);
        $this->forge->dropTable('journal_entries', true);
        $this->forge->dropTable('accounts', true);
    }
}
