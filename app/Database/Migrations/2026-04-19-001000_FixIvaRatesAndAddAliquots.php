<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixIvaRatesAndAddAliquots extends Migration
{
    public function up()
    {
        // ── 1. Add AFIP code column to taxes table ──
        if (! $this->db->fieldExists('afip_code', 'taxes')) {
            $this->forge->addColumn('taxes', [
                'afip_code' => [
                    'type'       => 'SMALLINT',
                    'constraint' => 5,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'rate',
                    'comment'    => 'AFIP IVA code: 3=0%, 4=10.5%, 5=21%, 6=27%, 8=5%, 9=2.5%',
                ],
                'is_default' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                    'after'      => 'afip_code',
                ],
            ]);
        }

        // ── 2. Fix existing IVA General from 16% to 21% ──
        $this->db->table('taxes')
            ->where('code', 'IVA')
            ->where('rate', '16.00')
            ->update([
                'name'       => 'IVA 21%',
                'rate'       => '21.00',
                'afip_code'  => 5,
                'is_default' => 1,
            ]);

        // Also fix any that were already at different rates but missing afip_code
        $this->db->table('taxes')
            ->where('code', 'IVA')
            ->where('rate', '21.00')
            ->where('afip_code', null)
            ->update([
                'afip_code'  => 5,
                'is_default' => 1,
            ]);
    }

    public function down()
    {
        // Revert IVA rate
        $this->db->table('taxes')
            ->where('code', 'IVA')
            ->where('rate', '21.00')
            ->update([
                'rate' => '16.00',
                'name' => 'IVA General',
            ]);

        // Remove added columns
        if ($this->db->fieldExists('afip_code', 'taxes')) {
            $this->forge->dropColumn('taxes', ['afip_code', 'is_default']);
        }
    }
}
