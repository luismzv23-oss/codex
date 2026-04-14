<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCashTreasuryTables extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'company_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'branch_id' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'code' => ['type' => 'VARCHAR', 'constraint' => 40],
            'register_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'general'],
            'is_default' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addUniqueKey(['company_id', 'code']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('cash_registers');

        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'company_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'cash_register_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'open'],
            'opened_by' => ['type' => 'VARCHAR', 'constraint' => 36],
            'opened_at' => ['type' => 'DATETIME'],
            'opening_amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'expected_closing_amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'actual_closing_amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'null' => true],
            'difference_amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'closed_by' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'closed_at' => ['type' => 'DATETIME', 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addKey('cash_register_id');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('cash_register_id', 'cash_registers', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('opened_by', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('closed_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('cash_sessions');

        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'company_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'cash_register_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'cash_session_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'movement_type' => ['type' => 'VARCHAR', 'constraint' => 40],
            'payment_method' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'reference_type' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'reference_id' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'reference_number' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'occurred_at' => ['type' => 'DATETIME'],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'VARCHAR', 'constraint' => 36],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addKey('cash_session_id');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('cash_register_id', 'cash_registers', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('cash_session_id', 'cash_sessions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cash_movements');

        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'company_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'cash_session_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'closed_by' => ['type' => 'VARCHAR', 'constraint' => 36],
            'closed_at' => ['type' => 'DATETIME'],
            'opening_amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'expected_amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'actual_amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'difference_amount' => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => '0.00'],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('cash_session_id', 'cash_sessions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('closed_by', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cash_closures');

        $this->forge->addColumn('sales', [
            'cash_register_id' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true, 'after' => 'warehouse_id'],
            'cash_session_id' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true, 'after' => 'cash_register_id'],
        ]);

        $this->db->query('ALTER TABLE sales ADD CONSTRAINT sales_cash_register_id_foreign FOREIGN KEY (cash_register_id) REFERENCES cash_registers(id) ON DELETE SET NULL ON UPDATE CASCADE');
        $this->db->query('ALTER TABLE sales ADD CONSTRAINT sales_cash_session_id_foreign FOREIGN KEY (cash_session_id) REFERENCES cash_sessions(id) ON DELETE SET NULL ON UPDATE CASCADE');

        $this->db->table('systems')->where('slug', 'caja')->update([
            'entry_url' => 'caja',
            'description' => 'Apertura, cierre y control operativo de caja y tesoreria.',
            'icon' => 'bi-cash-stack',
            'active' => 1,
        ]);
    }

    public function down()
    {
        if ($this->db->fieldExists('cash_session_id', 'sales')) {
            $this->db->query('ALTER TABLE sales DROP FOREIGN KEY sales_cash_session_id_foreign');
            $this->forge->dropColumn('sales', 'cash_session_id');
        }
        if ($this->db->fieldExists('cash_register_id', 'sales')) {
            $this->db->query('ALTER TABLE sales DROP FOREIGN KEY sales_cash_register_id_foreign');
            $this->forge->dropColumn('sales', 'cash_register_id');
        }

        $this->forge->dropTable('cash_closures', true);
        $this->forge->dropTable('cash_movements', true);
        $this->forge->dropTable('cash_sessions', true);
        $this->forge->dropTable('cash_registers', true);
    }
}
