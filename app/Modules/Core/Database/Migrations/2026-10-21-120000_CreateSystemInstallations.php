<?php

namespace CodexCore\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateSystemInstallations extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'system_id'=>['type'=>'VARCHAR','constraint'=>36],
            'installed_version'=>['type'=>'VARCHAR','constraint'=>32],
            'manifest_hash'=>['type'=>'VARCHAR','constraint'=>64],
            'status'=>['type'=>'VARCHAR','constraint'=>24],
            'checked_at'=>['type'=>'DATETIME'],
        ]);
        $this->forge->addKey('system_id', true);
        $this->forge->createTable('system_installations');
    }

    public function down()
    {
        $this->forge->dropTable('system_installations');
    }
}
