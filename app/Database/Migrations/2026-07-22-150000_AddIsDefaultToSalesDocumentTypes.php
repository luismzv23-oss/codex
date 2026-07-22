<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddIsDefaultToSalesDocumentTypes extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('is_default', 'sales_document_types')) {
            $this->forge->addColumn('sales_document_types', [
                'is_default' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                    'after'      => 'sort_order',
                ],
            ]);
        }

        // Set default document type (FACTURA_B or FACTURA or first active) for existing companies
        $companies = $this->db->table('companies')->select('id')->get()->getResultArray();
        foreach ($companies as $comp) {
            $companyId = $comp['id'];

            // Check if company already has a default document type
            $hasDefault = $this->db->table('sales_document_types')
                ->where('company_id', $companyId)
                ->where('is_default', 1)
                ->countAllResults();

            if ($hasDefault === 0) {
                // Try FACTURA_B first
                $target = $this->db->table('sales_document_types')
                    ->where('company_id', $companyId)
                    ->where('code', 'FACTURA_B')
                    ->get()->getRowArray();

                if (! $target) {
                    // Try FACTURA
                    $target = $this->db->table('sales_document_types')
                        ->where('company_id', $companyId)
                        ->where('code', 'FACTURA')
                        ->get()->getRowArray();
                }

                if (! $target) {
                    // Try any active document type
                    $target = $this->db->table('sales_document_types')
                        ->where('company_id', $companyId)
                        ->where('active', 1)
                        ->get()->getRowArray();
                }

                if ($target) {
                    $this->db->table('sales_document_types')
                        ->where('id', $target['id'])
                        ->update(['is_default' => 1]);
                }
            }
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('is_default', 'sales_document_types')) {
            $this->forge->dropColumn('sales_document_types', 'is_default');
        }
    }
}
