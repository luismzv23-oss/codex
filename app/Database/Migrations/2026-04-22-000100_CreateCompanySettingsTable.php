<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCompanySettingsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => false],
            'company_id' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => false],
            'key'        => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'value'      => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addUniqueKey(['company_id', 'key'], 'uq_company_settings_key');
        $this->forge->createTable('company_settings', true);
    }

    public function down()
    {
        $this->forge->dropTable('company_settings', true);
    }
}
