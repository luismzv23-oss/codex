<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UniqueVoucherSequences extends Migration
{
    public function up()
    {
        $table = $this->db->protectIdentifiers($this->db->prefixTable('voucher_sequences'));
        if ($this->db->query("SELECT company_id FROM $table GROUP BY company_id, document_type, COALESCE(branch_id, '') HAVING COUNT(*) > 1 LIMIT 1")->getRowArray()) {
            throw new \RuntimeException('Existen numeraciones duplicadas. Conciliarlas antes de aplicar la migracion; no se alteraran correlativos automaticamente.');
        }
        $this->db->query("ALTER TABLE $table ADD COLUMN branch_key VARCHAR(36) GENERATED ALWAYS AS (COALESCE(branch_id, '')) STORED, ADD UNIQUE KEY uq_voucher_scope (company_id, document_type, branch_key)");
    }

    public function down()
    {
        $table = $this->db->protectIdentifiers($this->db->prefixTable('voucher_sequences'));
        $this->db->query("ALTER TABLE $table DROP INDEX uq_voucher_scope, DROP COLUMN branch_key");
    }
}
