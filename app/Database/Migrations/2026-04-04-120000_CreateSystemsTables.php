<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSystemsTables extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'slug' => ['type' => 'VARCHAR', 'constraint' => 80],
            'description' => ['type' => 'TEXT', 'null' => true],
            'entry_url' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'icon' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('slug');
        $this->forge->createTable('systems');

        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'company_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'system_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addKey('system_id');
        $this->forge->addUniqueKey(['company_id', 'system_id']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('system_id', 'systems', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('company_systems');

        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'company_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'user_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'system_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'access_level' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'view'],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addKey('user_id');
        $this->forge->addKey('system_id');
        $this->forge->addUniqueKey(['user_id', 'system_id']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('system_id', 'systems', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('user_systems');
    }

    public function down()
    {
        $this->forge->dropTable('user_systems', true);
        $this->forge->dropTable('company_systems', true);
        $this->forge->dropTable('systems', true);
    }
}
