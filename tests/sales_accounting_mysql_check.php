<?php
// Uses a disposable database; never modifies the application's business records.
require dirname(__DIR__) . '/vendor/codeigniter4/framework/system/Test/bootstrap.php';
helper('app');
$worker = ($argv[1] ?? '') === '--worker';
$name = $worker ? ($argv[2] ?? '') : 'codex_sale_accounting_check_' . bin2hex(random_bytes(5));
if (!preg_match('/\Acodex_sale_accounting_check_[a-f0-9]{10}\z/', $name)) { throw new RuntimeException('Invalid test database'); }
$settings = config('Database')->default;
$admin = $worker ? null : \Config\Database::connect($settings, false);
if ($admin) { $admin->query('CREATE DATABASE `' . $name . '`'); }
$settings['database'] = $name; $settings['DBPrefix'] = '';
config('Database')->tests = $settings; $db = db_connect('tests');
if ($worker) {
    $service = new class extends \App\Libraries\AccountingService {
        public function createJournalEntry(string $companyId, array $data, array $lines): array {
            usleep(300000);
            return parent::createJournalEntry($companyId, $data, $lines);
        }
    };
    $sale = $db->table('sales')->where('id', 'sale')->get()->getRowArray();
    $result = $service->journalFromSale('demo', $sale, ['receivable'=>'receivable','revenue'=>'revenue','iva_debito'=>'iva']);
    if (!$result['ok']) { throw new RuntimeException($result['error']); }
    echo !empty($result['already_synced']) ? 'existing' : 'created';
    exit;
}
function checkSaleAccounting(bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } }
try {
    $db->query('CREATE TABLE sales (id VARCHAR(36) PRIMARY KEY, company_id VARCHAR(36), sale_number VARCHAR(40), issue_date DATETIME, subtotal DECIMAL(15,2), tax_total DECIMAL(15,2), total DECIMAL(15,2), payment_surcharge_amount DECIMAL(15,2), created_by VARCHAR(36)) ENGINE=InnoDB');
    $db->query('CREATE TABLE sale_items (id VARCHAR(36) PRIMARY KEY, sale_id VARCHAR(36), line_number INT, quantity DECIMAL(15,2), tax_rate DECIMAL(7,2), subtotal DECIMAL(15,2), tax_total DECIMAL(15,2), line_total DECIMAL(15,2)) ENGINE=InnoDB');
    $db->query('CREATE TABLE journal_entries (id VARCHAR(36) PRIMARY KEY, company_id VARCHAR(36), entry_number INT, entry_date DATE, description VARCHAR(255), reference_type VARCHAR(40), reference_id VARCHAR(36), status VARCHAR(20), total_debit DECIMAL(15,2), total_credit DECIMAL(15,2), user_id VARCHAR(36), posted_at DATETIME, created_at DATETIME) ENGINE=InnoDB');
    $db->query('CREATE TABLE journal_entry_lines (id VARCHAR(36) PRIMARY KEY, journal_entry_id VARCHAR(36), account_id VARCHAR(36), description VARCHAR(255), debit DECIMAL(15,2), credit DECIMAL(15,2), created_at DATETIME) ENGINE=InnoDB');
    $db->table('sales')->insert(['id'=>'sale','company_id'=>'demo','sale_number'=>'TEST','issue_date'=>'2026-10-03 10:00:00','subtotal'=>727.27,'tax_total'=>152.73,'total'=>885.76,'payment_surcharge_amount'=>5.76,'created_by'=>'seller']);
    $db->table('sale_items')->insert(['id'=>'item','sale_id'=>'sale','line_number'=>1,'quantity'=>1,'tax_rate'=>21,'subtotal'=>727.27,'tax_total'=>152.73,'line_total'=>880]);
    $workers=[];
    for($i=0;$i<2;$i++) {
        $process=proc_open([PHP_BINARY,__FILE__,'--worker',$name],[1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
        if(!is_resource($process)){throw new RuntimeException('Cannot start worker');}
        $workers[]=[$process,$pipes];
    }
    $results=[];
    foreach($workers as [$process,$pipes]){
        $results[]=trim(stream_get_contents($pipes[1]));$error=stream_get_contents($pipes[2]);
        fclose($pipes[1]);fclose($pipes[2]);checkSaleAccounting(proc_close($process)===0,$error);
    }
    sort($results);checkSaleAccounting($results===['created','existing'],'Concurrent confirmations duplicated or failed');
    checkSaleAccounting($db->table('journal_entries')->countAllResults()===1,'Duplicate journal');
    $lines=$db->table('journal_entry_lines')->get()->getResultArray();$credits=array_column($lines,'credit','account_id');
    checkSaleAccounting(count($lines)===3 && (float)$credits['revenue']===732.03 && (float)$credits['iva']===153.73,'Fiscal amounts differ');
    echo "OK: concurrent sale accounting creates one balanced journal with the fiscal net and IVA.\n";
} finally {
    $db->close();$admin->query('DROP DATABASE `' . $name . '`');$admin->close();
}
