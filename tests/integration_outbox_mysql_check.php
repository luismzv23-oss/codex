<?php
/** Isolated real-MySQL concurrency check. Creates only random-prefixed test tables. */
define('FCPATH',dirname(__DIR__).DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR);
require FCPATH.'../app/Config/Paths.php'; $paths=new Config\Paths();
require rtrim($paths->systemDirectory,'\\/ ').DIRECTORY_SEPARATOR.'bootstrap.php';
require_once SYSTEMPATH.'Config/DotEnv.php'; (new CodeIgniter\Config\DotEnv(ROOTPATH))->load();
define('ENVIRONMENT',env('CI_ENVIRONMENT','production')); Config\Services::codeigniter()->initialize();
helper('app');

use App\Libraries\Core\Events\{ConsumerRegistry,DeliveryWorker,OutboxPublisher};

$child=($argv[1] ?? '')==='child';
$prefix=$child ? ($argv[2] ?? '') : 'vo_'.bin2hex(random_bytes(4)).'_';
if (!preg_match('/^vo_[a-f0-9]{8}_$/D',$prefix)) throw new RuntimeException('Unsafe test prefix');
$cfg=config('Database')->default; $cfg['DBPrefix']=$prefix; $cfg['pConnect']=false;
$db=Config\Database::connect($cfg,false);
if ($db->DBDriver!=='MySQLi') throw new RuntimeException('This check requires MySQLi');
$registry=new ConsumerRegistry(['test.changed'=>['effect'=>['version'=>1,'handler'=>static function(array $event) use ($db) {
    if (!$db->table('effects')->insert(['id'=>$event['id'],'company_id'=>$event['company_id']])) throw new RuntimeException('Test effect failed');
    usleep(400000); // Hold the effect transaction while the competing worker runs.
}]]]);
$dir=WRITEPATH.'private/core-integration/'.$prefix;
if ($child) {
    $phase=$argv[3] ?? ''; $slot=$argv[4] ?? '';
    if (!in_array($phase,['publish','work'],true) || !in_array($slot,['1','2'],true)) exit(2);
    file_put_contents($dir.'/'.$phase.$slot.'.ready','ready');
    $deadline=microtime(true)+15;
    while (!is_file($dir.'/'.$phase.'.go')) {
        if (microtime(true)>$deadline) exit(3);
        usleep(10000);
    }
    if ($phase==='publish') {
        $db->transBegin();
        (new OutboxPublisher($db,$registry))->publish('test-company','test.changed','same-operation','test','source',['value'=>'same']);
        if (!$db->transStatus() || !$db->transCommit()) exit(4);
    } else (new DeliveryWorker($db,$registry))->processBatch('test-company',1);
    exit(0);
}

function verifyOutbox(bool $condition,string $message): void { if (!$condition) throw new RuntimeException($message); }
$created=[]; $processes=[];
try {
    if (!is_dir($dir)) mkdir($dir,0700,true);
    foreach (['companies','effects'] as $table) {
        $db->query('CREATE TABLE '.$db->protectIdentifiers($db->prefixTable($table)).' (id VARCHAR(36) PRIMARY KEY'.($table==='effects' ? ', company_id VARCHAR(36)' : '').') ENGINE=InnoDB');
        $created[]=$table;
    }
    require_once APPPATH.'Modules/Core/Database/Migrations/2026-10-21-130000_CreateIntegrationOutbox.php';
    $created=array_merge($created,['integration_outbox','integration_deliveries','integration_delivery_attempts']);
    (new CodexCore\Database\Migrations\CreateIntegrationOutbox(Config\Database::forge($db)))->up();
    $db->table('companies')->insert(['id'=>'test-company']);
    foreach (['publish','work'] as $phase) {
        foreach (['1','2'] as $slot) {
            $pipes=[];
            $process=proc_open([PHP_BINARY,__FILE__,'child',$prefix,$phase,$slot],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,ROOTPATH);
            if (!is_resource($process)) throw new RuntimeException('Could not start competing process');
            fclose($pipes[0]);
            $processes[]=['process'=>$process,'pipes'=>[$pipes[1],$pipes[2]]];
        }
        $deadline=microtime(true)+15;
        while (!is_file($dir.'/'.$phase.'1.ready') || !is_file($dir.'/'.$phase.'2.ready')) {
            if (microtime(true)>$deadline) throw new RuntimeException('Competing process startup timed out');
            usleep(10000);
        }
        file_put_contents($dir.'/'.$phase.'.go','go');
        foreach ($processes as $entry) {
            foreach ($entry['pipes'] as $pipe) { $output=stream_get_contents($pipe); fclose($pipe); verifyOutbox($output==='','Unexpected child output'); }
            verifyOutbox(proc_close($entry['process'])===0,'Competing process failed');
        }
        $processes=[];
        verifyOutbox($db->table('integration_outbox')->countAllResults()===1,'Duplicate event');
        verifyOutbox($db->table('integration_deliveries')->countAllResults()===1,'Duplicate delivery');
    }
    verifyOutbox($db->table('effects')->countAllResults()===1,'Effect did not execute exactly once locally');
    verifyOutbox($db->table('integration_deliveries')->where('status','succeeded')->countAllResults()===1,'Delivery not complete');
    verifyOutbox($db->table('integration_delivery_attempts')->countAllResults()===1,'Competing worker acquired reserved delivery');
    echo "MySQL: competing publishers and workers passed; one event, one delivery, one local effect.\n";
} finally {
    foreach ($processes as $entry) {
        if (is_resource($entry['process'])) proc_terminate($entry['process']);
        foreach ($entry['pipes'] as $pipe) if (is_resource($pipe)) fclose($pipe);
        if (is_resource($entry['process'])) proc_close($entry['process']);
    }
    foreach (array_reverse($created) as $table) {
        $physical=$db->prefixTable($table);
        if (!str_starts_with($physical,$prefix)) throw new RuntimeException('Unsafe cleanup target');
        if ($db->tableExists($table,false)) $db->query('DROP TABLE '.$db->protectIdentifiers($physical));
    }
    foreach (glob($dir.'/*') ?: [] as $file) unlink($file);
    if (is_dir($dir)) rmdir($dir);
}
