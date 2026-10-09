<?php
 define('FCPATH',getcwd().'/public/');
 require getcwd().'/app/Config/Paths.php';
 $paths=new Config\Paths();require rtrim($paths->systemDirectory,'\\/ ').'/bootstrap.php';
 require_once SYSTEMPATH.'Config/DotEnv.php';(new CodeIgniter\Config\DotEnv(ROOTPATH))->load();
 define('ENVIRONMENT',env('CI_ENVIRONMENT','production'));
 Config\Services::codeigniter()->initialize();
 $db=db_connect();echo 'Database: '.$db->database.PHP_EOL;
 $controller=new App\Controllers\PurchasesController();
 foreach(['supplierRows','orderRows','receiptRows','payableRows','invoiceRows','creditNoteRows','supplierCostRows'] as $method){
 $r=new ReflectionMethod($controller,$method);$r->setAccessible(true);
 $rows=$r->invoke($controller,'077f3cfc-e966-4c50-b8e8-ef954e58a44f');echo $method.': OK ('.count($rows).')'.PHP_EOL;
 }
 foreach(['purchase_documents','purchase_document_events'] as $table) echo $table.': '.($db->tableExists($table)?'OK':'MISSING').PHP_EOL;
