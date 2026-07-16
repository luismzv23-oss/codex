<?php

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;

/**
 * AccountingSeeder — Standard Argentine chart of accounts.
 * Seeds a complete hierarchical plan de cuentas for a company.
 */
class AccountingSeeder extends Seeder
{
    public function run()
    {
        $companyId = $this->getCompanyId();
        if (!$companyId) {
            CLI::write('No company found. Skipping accounting seeder.', 'yellow');
            return;
        }

        $service = new \App\Libraries\AccountingService();
        $service->setupCompanyAccounting($companyId);

        CLI::write('Accounting setup completed for company: ' . $companyId, 'green');
    }

    private function getCompanyId(): ?string
    {
        $company = $this->db->table('companies')->orderBy('name', 'ASC')->limit(1)->get()->getRowArray();
        return $company['id'] ?? null;
    }
}
