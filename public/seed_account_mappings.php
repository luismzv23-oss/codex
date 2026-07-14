<?php
/**
 * Script to automatically seed account mapping settings in company_settings
 * based on the Argentine chart of accounts.
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

define('ENVIRONMENT', 'development');
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

try {
    $pathsPath = FCPATH . '../app/Config/Paths.php';
    require $pathsPath;
    $paths = new Config\Paths();
    require rtrim($paths->systemDirectory, '\\/ ') . DIRECTORY_SEPARATOR . 'bootstrap.php';

    require_once SYSTEMPATH . 'Config/DotEnv.php';
    (new CodeIgniter\Config\DotEnv(ROOTPATH))->load();

    $app = Config\Services::codeigniter();
    $app->initialize();

    $db = db_connect();

    echo "========================================================\n";
    echo "       SEEDING ACCOUNT MAPPINGS FOR COMPANY SETTINGS    \n";
    echo "========================================================\n\n";

    // 1. Get companies
    $companies = $db->table('companies')->get()->getResultArray();
    if (empty($companies)) {
        die("Error: No se encontraron empresas en la base de datos.\n");
    }

    foreach ($companies as $company) {
        $companyId = $company['id'];
        echo "Procesando empresa: " . $company['name'] . " ({$companyId})\n";

        // 2. Ensure accounting chart is seeded for this company
        $hasAccounts = $db->table('accounts')->where('company_id', $companyId)->countAllResults();
        if ($hasAccounts === 0) {
            echo "-> Plan de cuentas vacío. Ejecutando AccountingSeeder...\n";
            $seeder = \Config\Database::seeder();
            // Simulating Seeder run by calling Seeder class
            $_SERVER['argv'] = []; // mock CLI
            $seeder->call('App\Database\Seeds\AccountingSeeder');
        }

        // Fetch accounts
        $accounts = $db->table('accounts')->where('company_id', $companyId)->get()->getResultArray();
        
        // Define name-to-mapping target map
        $mappingsToFind = [
            'cash'            => 'Caja',
            'bank'            => 'Banco Cuenta Corriente',
            'receivable'      => 'Deudores por Ventas',
            'payable'         => 'Proveedores',
            'revenue'         => 'Ventas',
            'expense'         => 'Costo Mercaderias Vendidas',
            'iva_debito'      => 'IVA Debito Fiscal',
            'iva_credito'     => 'IVA Credito Fiscal',
            'goods_received'  => 'Bienes de Cambio',
            'cash_difference' => 'Diferencia de Cambio'
        ];

        foreach ($mappingsToFind as $mapKey => $accountName) {
            // Find account by name
            $matchedAccount = null;
            foreach ($accounts as $acc) {
                if (strcasecmp($acc['name'], $accountName) === 0) {
                    $matchedAccount = $acc;
                    break;
                }
            }

            if ($matchedAccount) {
                $settingKey = 'account_' . $mapKey;
                $accountId = $matchedAccount['id'];

                // Check if setting already exists
                $existing = $db->table('company_settings')
                    ->where('company_id', $companyId)
                    ->where('key', $settingKey)
                    ->get()->getRowArray();

                if ($existing) {
                    $db->table('company_settings')
                        ->where('id', $existing['id'])
                        ->update([
                            'value' => $accountId,
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                    echo "   - Actualizado: {$settingKey} => {$matchedAccount['name']} ({$accountId})\n";
                } else {
                    $db->table('company_settings')->insert([
                        'id' => app_uuid(),
                        'company_id' => $companyId,
                        'key' => $settingKey,
                        'value' => $accountId,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    echo "   - Creado: {$settingKey} => {$matchedAccount['name']} ({$accountId})\n";
                }
            } else {
                echo "   - ADVERTENCIA: No se encontró cuenta con nombre '{$accountName}' para mapear a '{$mapKey}'.\n";
            }
        }
        echo "\n";
    }

    echo "========================================================\n";
    echo "       MAPPING SEED COMPLETED SUCCESSFULLY             \n";
    echo "========================================================\n";

} catch (\Throwable $e) {
    echo "ERROR DIED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}
