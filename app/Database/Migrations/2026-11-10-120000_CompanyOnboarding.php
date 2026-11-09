<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Company registration wizard state, and the AFIP voucher code of each sales document type
 * (until now every invoice was sent to ARCA as type 6 / Factura B).
 */
class CompanyOnboarding extends Migration
{
    /** AFIP "CbteTipo" per document code. TICKET is resolved from the issuer VAT condition. */
    public const AFIP_CODES = ['FACTURA_A' => 1, 'ND_A' => 2, 'NC_A' => 3, 'FACTURA_B' => 6, 'ND_B' => 7, 'NC_B' => 8,
        'FACTURA_C' => 11, 'ND_C' => 12, 'NC_C' => 13, 'FACTURA_M' => 51];

    public function up()
    {
        if (! $this->db->tableExists('company_onboarding')) {
            $this->forge->addField([
                'id' => ['type' => 'CHAR', 'constraint' => 36],
                'company_id' => ['type' => 'CHAR', 'constraint' => 36],
                'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'in_progress'],
                'steps_json' => ['type' => 'TEXT', 'null' => true],
                'started_by' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
                'completed_by' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
                'completed_at' => ['type' => 'DATETIME', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('company_id');
            $this->forge->createTable('company_onboarding');
        }
        if ($this->db->tableExists('sales_document_types') && ! $this->db->fieldExists('afip_code', 'sales_document_types')) {
            $this->forge->addColumn('sales_document_types', ['afip_code' => ['type' => 'SMALLINT', 'null' => true]]);
        }
        if ($this->db->tableExists('sales_document_types')) {
            foreach (self::AFIP_CODES as $code => $afip) {
                $this->db->table('sales_document_types')->where('code', $code)->where('afip_code', null)->update(['afip_code' => $afip]);
            }
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('afip_code', 'sales_document_types')) {
            $this->forge->dropColumn('sales_document_types', 'afip_code');
        }
        $this->forge->dropTable('company_onboarding', true);
    }
}
