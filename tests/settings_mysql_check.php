<?php
// Isolated MySQL migration and concurrency checks. No application records are modified.
require dirname(__DIR__) . '/vendor/codeigniter4/framework/system/Test/bootstrap.php';
helper('app');
$worker = ($argv[1] ?? '') === '--worker';
$name = $worker ? ($argv[2] ?? '') : 'codex_settings_check_' . bin2hex(random_bytes(5));
if (! preg_match('/\Acodex_settings_check_[a-f0-9]{10}\z/', $name)) { throw new RuntimeException('Invalid database name'); }
$settings = config('Database')->default;
$admin = $worker ? null : \Config\Database::connect($settings, false);
if ($admin) { $admin->query('CREATE DATABASE `' . $name . '`'); }
$settings['database'] = $name; $settings['DBPrefix'] = '';
config('Database')->tests = $settings; $db = db_connect('tests');
if ($worker) {
    if (($argv[3] ?? '') === 'sequence') { echo (new \App\Libraries\VoucherSequenceService())->next('a', 'TEST', 'T'); }
    else { (new \App\Libraries\SettingsService())->tickets('a', ['ticket_pos_header_title' => 'Saved'], true); echo 'ok'; }
    exit;
}
function settingsCheck(bool $ok, string $message): void { if (! $ok) { throw new RuntimeException($message); } }
function settingsParallel(string $name, string $operation): array
{
    $workers = [];
    for ($i = 0; $i < 2; $i++) {
        $process = proc_open([PHP_BINARY, __FILE__, '--worker', $name, $operation], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
        if (! is_resource($process)) { throw new RuntimeException('Cannot start worker'); }
        $workers[] = [$process, $pipes];
    }
    $results = [];
    foreach ($workers as [$process, $pipes]) {
        $results[] = trim(stream_get_contents($pipes[1])); $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); settingsCheck(proc_close($process) === 0, $errors);
    }
    sort($results); return $results;
}
try {
    $db->query('CREATE TABLE companies (id VARCHAR(36) PRIMARY KEY) ENGINE=InnoDB');
    $db->query('CREATE TABLE branches (id VARCHAR(36) PRIMARY KEY, company_id VARCHAR(36), code VARCHAR(20), active INT) ENGINE=InnoDB');
    $db->query('CREATE TABLE voucher_sequences (id VARCHAR(36) PRIMARY KEY, company_id VARCHAR(36), branch_id VARCHAR(36) NULL, document_type VARCHAR(60), prefix VARCHAR(20), current_number INT, active INT, created_at DATETIME, updated_at DATETIME, CONSTRAINT fk_settings_branch FOREIGN KEY(branch_id) REFERENCES branches(id) ON DELETE SET NULL) ENGINE=InnoDB');
    $db->query('CREATE TABLE company_settings (id VARCHAR(36) PRIMARY KEY, company_id VARCHAR(36), `key` VARCHAR(100), value TEXT, created_at DATETIME, updated_at DATETIME, UNIQUE KEY uq_company_settings (company_id, `key`)) ENGINE=InnoDB');
    $db->table('companies')->insert(['id' => 'a']);
    require APPPATH . 'Database/Migrations/2026-09-21-140000_UniqueVoucherSequences.php';
    $migration = new \App\Database\Migrations\UniqueVoucherSequences(\Config\Database::forge('tests'));
    foreach (['one', 'two'] as $id) { $db->table('voucher_sequences')->insert(['id' => $id, 'company_id' => 'a', 'document_type' => 'DUP', 'current_number' => 42]); }
    try { $migration->up(); throw new LogicException('Duplicate migration accepted'); }
    catch (RuntimeException $e) { settingsCheck(strpos($e->getMessage(), 'Existen numeraciones duplicadas') === 0, $e->getMessage()); }
    settingsCheck($db->table('voucher_sequences')->countAllResults() === 2, 'Existing sequences changed');
    $db->table('voucher_sequences')->where('id', 'two')->delete();
    $before = $db->table('voucher_sequences')->get()->getRowArray();
    $migration->up(); $after = $db->table('voucher_sequences')->get()->getRowArray(); unset($after['branch_key']);
    settingsCheck($before === $after, 'Migration changed correlativo');
    settingsCheck(count($db->getForeignKeyData('voucher_sequences')) === 1, 'FK lost');
    try {
        $inserted = $db->table('voucher_sequences')->insert(['id' => 'duplicate', 'company_id' => 'a', 'document_type' => 'DUP']);
        settingsCheck(! $inserted, 'Null branch uniqueness missing');
    } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
        settingsCheck(strpos($e->getMessage(), 'Duplicate') !== false, $e->getMessage());
    }
    $migration->down(); $migration->up();
    settingsCheck(settingsParallel($name, 'sequence') === ['T-00000001', 'T-00000002'], 'Concurrent numbers overlap');
    settingsCheck($db->table('voucher_sequences')->where('document_type', 'TEST')->countAllResults() === 1, 'Duplicate sequence initialization');
    settingsCheck(settingsParallel($name, 'tickets') === ['ok', 'ok'], 'Concurrent settings failed');
    settingsCheck($db->table('company_settings')->countAllResults() === 1, 'Duplicate setting keys');
    echo "OK: migration/reversal, duplicate preflight, foreign key, concurrent sequence creation/allocation and settings writes.\n";
} finally { $db->close(); $admin->query('DROP DATABASE `' . $name . '`'); $admin->close(); }
