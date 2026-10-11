<?php
declare(strict_types=1);

/** Five aggregate scenarios against real routes/filters and an empty schema clone. */
require dirname(__DIR__) . '/vendor/codeigniter4/framework/system/Test/bootstrap.php';
helper('app');
$root = dirname(__DIR__);
$name = 'codex_collections_check_' . bin2hex(random_bytes(5));
$settings = config('Database')->default;
$source = \Config\Database::connect($settings, false);
$source->initialize();
$servers = [];
$db = null;
$created = false;
$assertions = 0;
$passed = [];
$qaDir = $root . '/tmp/' . $name;

function verify(bool $condition, string $message): void
{
    global $assertions;
    ++$assertions;
    if (!$condition) throw new RuntimeException($message);
}
function row(string $table, string $id): array
{
    return db_connect()->table($table)->where('id', $id)->get()->getRowArray() ?? [];
}
function countRows(string $table): int { return db_connect()->table($table)->countAllResults(); }
function financialFingerprint($connection): array
{
    $result = [];
    foreach (['sales','sales_receivables','sales_receipts','sales_receipt_items','sale_payments','cash_movements','journal_entries','journal_entry_lines'] as $table) {
        $hash = hash_init('sha256');
        foreach ($connection->table($table)->orderBy('id')->get()->getResultArray() as $record) hash_update($hash, json_encode($record, JSON_THROW_ON_ERROR));
        $result[$table] = hash_final($hash);
    }
    return $result;
}
function invoice(string $id, float $amount = 100): void
{
    $db = db_connect();
    $db->table('sales')->insert(['id'=>$id,'company_id'=>'qa-company','customer_id'=>'qa-customer','sale_number'=>$id,'issue_date'=>date('Y-m-d H:i:s'),'status'=>'confirmed','currency_code'=>'ARS','subtotal'=>$amount,'total'=>$amount,'created_by'=>'qa-user']);
    $db->table('sales_receivables')->insert(['id'=>$id,'company_id'=>'qa-company','customer_id'=>'qa-customer','sale_id'=>$id,'document_number'=>$id,'issue_date'=>date('Y-m-d H:i:s'),'total_amount'=>$amount,'balance_amount'=>$amount]);
    (new \App\Libraries\AccountingService())->syncSale('qa-company',$id,'qa-user');
}
function payload(string $invoice, array $payments = []): array
{
    return ['company_id'=>'qa-company','request_key'=>app_uuid(),'items'=>[['receivable_id'=>$invoice,'applied_amount'=>100]],'payments'=>$payments ?: [['payment_method'=>'cash','amount'=>100]]];
}
final class QaHttp
{
    public string $base;
    public string $token = '';
    private array $cookies = [];
    private string $csrf = '';
    public function __construct(string $base) { $this->base = $base; }
    public function handle(string $path, ?array $data = null, bool $json = true)
    {
        $handle = curl_init($this->base . $path);
        $headers = ['Accept: ' . ($json ? 'application/json' : 'text/html')];
        if ($this->token !== '') $headers[] = 'Authorization: Bearer ' . $this->token;
        if ($this->csrf !== '') $headers[] = 'X-CSRF-TOKEN: ' . $this->csrf;
        if ($data !== null) {
            $headers[] = 'Content-Type: ' . ($json ? 'application/json' : 'application/x-www-form-urlencoded');
            curl_setopt($handle, CURLOPT_POST, true);
            curl_setopt($handle, CURLOPT_POSTFIELDS, $json ? json_encode($data, JSON_THROW_ON_ERROR) : http_build_query($data));
        }
        curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_HTTPHEADER=>$headers,
            CURLOPT_COOKIE=>implode('; ', array_map(static fn($k,$v)=>$k.'='.$v, array_keys($this->cookies),$this->cookies)),
            CURLOPT_HEADERFUNCTION=>function ($handle, string $header): int {
                if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $header, $m)) $this->cookies[$m[1]] = $m[2];
                return strlen($header);
            }]);
        return $handle;
    }
    public function result($handle, string $body): array
    {
        $status = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if ($error) throw new RuntimeException('HTTP transport: '.$error);
        if (preg_match('/name="csrf_test_name"\s+value="([^"]+)"/', $body, $m)) $this->csrf=$m[1];
        return ['status'=>$status,'body'=>$body,'json'=>json_decode($body,true)];
    }
    public function request(string $path, ?array $data = null, bool $json = true): array
    {
        if ($data !== null) {
            $this->request('/forgot-password', null, false);
            $this->request('/ventas/cobranzas/nuevo?company_id=qa-company&popup=1', null, false);
        }
        $h=$this->handle($path,$data,$json);
        return $this->result($h,(string)curl_exec($h));
    }
    public function api(string $path, array $data, int $expected = 200): array
    {
        $response=$this->request('/api/v1/sales/'.$path,$data);
        verify($response['status']===$expected, $path.' HTTP '.$response['status'].': '.substr($response['body'],0,500));
        return $response['json']['data'] ?? [];
    }
}
function parallel(QaHttp $one, QaHttp $two, string $path, array $first, ?array $second = null): array
{
    // Separate sessions and two PHP servers: neither PHP session locks nor the
    // single-threaded development server can serialize these requests.
    $multi=curl_multi_init(); $handles=[];
    foreach ([[$one,$first],[$two,$second ?? $first]] as [$client,$data]) {
        $client->request('/forgot-password',null,false);
        $client->request('/ventas/cobranzas/nuevo?company_id=qa-company&popup=1',null,false);
        $handle=$client->handle('/api/v1/sales/'.$path,$data);
        curl_multi_add_handle($multi,$handle); $handles[]=[$client,$handle];
    }
    $locker=\Config\Database::connect(config('Database')->tests,false);
    $locker->transBegin();
    $locker->query("SELECT id FROM companies WHERE id='qa-company' FOR UPDATE");
    $waiting=0;
    try {
        $deadline=microtime(true)+8;
        do {
            curl_multi_exec($multi,$running);curl_multi_select($multi,0.05);
            $waiting=(int)$locker->query('SELECT COUNT(DISTINCT t.trx_id) AS n FROM information_schema.INNODB_TRX t JOIN information_schema.PROCESSLIST p ON p.ID=t.trx_mysql_thread_id WHERE p.DB=? AND t.trx_state=?',[$locker->database,'LOCK WAIT'])->getRowArray()['n'];
        } while($waiting<2 && $running && microtime(true)<$deadline);
    } finally {$locker->transRollback();$locker->close();}
    do { $code=curl_multi_exec($multi,$running); if ($running) curl_multi_select($multi,0.1); } while($running && $code===CURLM_OK);
    $results=[];
    foreach($handles as [$client,$handle]) {
        $body=(string)curl_multi_getcontent($handle);curl_multi_remove_handle($multi,$handle);
        $results[]=$client->result($handle,$body);
    }
    curl_multi_close($multi);
    verify($waiting>=2,'Both concurrent HTTP transactions must contend on the MySQL lock');
    return $results;
}

try {
    $before=financialFingerprint($source);
    $source->query('CREATE DATABASE `'.$name.'`'); $created=true;
    $settings['database']=$name;$settings['DBPrefix']='';$settings['strictOn']=true;$settings['DSN']='';
    config('Database')->tests=$settings;
    config('Database')->defaultGroup='tests';
    $db=db_connect('tests');
    verify($db->query('SELECT DATABASE() AS name')->getRowArray()['name']===$name && db_connect()===$db,'Database isolation');
    $db->query('SET FOREIGN_KEY_CHECKS=0');
    foreach($source->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->getResultArray() as $table) {
        $tableName=(string)array_values($table)[0];
        $ddl=$source->query('SHOW CREATE TABLE `'.str_replace('`','``',$tableName).'`')->getRowArray()['Create Table'];
        $db->query($ddl);
    }
    $db->query('SET FOREIGN_KEY_CHECKS=1');
    $db->table('companies')->insert(['id'=>'qa-company','name'=>'QA collections only','currency_code'=>'ARS']);
    $db->table('companies')->insert(['id'=>'qa-foreign','name'=>'QA other tenant','currency_code'=>'ARS']);
    $db->table('roles')->insert(['id'=>'qa-role','name'=>'QA superadmin','slug'=>'superadmin']);
    $password=bin2hex(random_bytes(20));
    $db->table('users')->insert(['id'=>'qa-user','company_id'=>'qa-company','role_id'=>'qa-role','name'=>'QA User','username'=>'qa-collections','email'=>'qa@example.invalid','password_hash'=>password_hash($password,PASSWORD_DEFAULT)]);
    $db->table('systems')->insert(['id'=>'qa-sales','name'=>'Ventas','slug'=>'ventas','active'=>1]);
    $db->table('customers')->insert(['id'=>'qa-customer','company_id'=>'qa-company','name'=>'QA Customer']);
    $db->table('customers')->insert(['id'=>'qa-other-customer','company_id'=>'qa-foreign','name'=>'QA Other Customer']);
    $db->table('inventory_products')->insert(['id'=>'qa-product','company_id'=>'qa-company','sku'=>'QA-1','name'=>'QA Product']);
    $db->table('inventory_warehouses')->insert(['id'=>'qa-warehouse','company_id'=>'qa-company','name'=>'QA Warehouse','code'=>'QA']);
    $db->table('cash_registers')->insert(['id'=>'qa-register','company_id'=>'qa-company','name'=>'QA Register','code'=>'QA','register_type'=>'general']);
    $db->table('cash_sessions')->insert(['id'=>'qa-session','company_id'=>'qa-company','cash_register_id'=>'qa-register','opened_by'=>'qa-user','opened_at'=>date('Y-m-d H:i:s')]);
    // Exercise migration compatibility on the actual engine, including historical receipts.
    require_once APPPATH.'Database/Migrations/2026-10-10-120000_ExtendCollections.php';
    $migration=new \App\Database\Migrations\ExtendCollections(\Config\Database::forge('tests'));
    $migration->down();$db->resetDataCache();$migration->up();$db->resetDataCache();
    $setup=new \App\Libraries\CollectionAccountSetup();
    $setup->configure('qa-company',false);verify(countRows('accounts')===0,'Dry run must roll back');
    $setup->configure('qa-company',true);$setup->configure('qa-company',true);
    verify(countRows('accounts')===7,'Seven distinct accounts, idempotent setup');
    $db->table('accounts')->insert(['id'=>'qa-revenue','company_id'=>'qa-company','code'=>'4.1','name'=>'QA Revenue','account_type'=>'revenue']);
    $db->table('company_settings')->insert(['id'=>app_uuid(),'company_id'=>'qa-company','key'=>'account_revenue','value'=>'qa-revenue']);
    foreach(['session','cache','logs','debugbar','uploads'] as $dir) mkdir($qaDir.'/'.$dir,0700,true);
    $env=getenv();$env['COLLECTIONS_QA_DATABASE']=$name;$env['COLLECTIONS_QA_JWT']=bin2hex(random_bytes(32));
    $clients=[];
    for($i=0;$i<2;$i++) {
        $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error); if(!$socket) throw new RuntimeException($error);
        $address=stream_socket_get_name($socket,false);fclose($socket);
        $process=proc_open([PHP_BINARY,'-S',$address,'-t',$root.'/public',$root.'/tests/support/collections_http_router.php'],[0=>['pipe','r'],1=>['file',$qaDir.'/server-'.$i.'.log','a'],2=>['file',$qaDir.'/server-'.$i.'.log','a']],$pipes,$root,$env,['bypass_shell'=>true,'create_new_console'=>false]);
        if(!is_resource($process)) throw new RuntimeException('Cannot start isolated HTTP server');
        fclose($pipes[0]);$servers[]=$process;
        $client=new QaHttp('http://'.$address);$ready=false;
        for($attempt=0;$attempt<50;$attempt++) {
            $socket=@stream_socket_client('tcp://'.$address,$errno,$error,0.1);
            if($socket){fclose($socket);$ready=true;break;} usleep(100000);
        }
        verify($ready,'Isolated server ready');$clients[]=$client;
    }
    [$web,$api]=$clients;
    $login=$web->request('/login',['login'=>'qa-collections','password'=>$password],false);
    verify(in_array($login['status'],[302,303],true),'Web login HTTP '.$login['status']);
    $login=$api->request('/api/v1/auth/login',['login'=>'qa-collections','password'=>$password]);
    verify($login['status']===200 && !empty($login['json']['data']['access_token']),'JWT login: '.substr($login['body'],0,200));
    $api->token=$login['json']['data']['access_token'];
    $unauthenticated=new QaHttp($api->base);
    verify($unauthenticated->request('/api/v1/sales/receipts?company_id=qa-company')['status']===401,'Anonymous denied');
    $unauthenticated->token=$api->token.'invalid';
    verify($unauthenticated->request('/api/v1/sales/receipts?company_id=qa-company')['status']===401,'Invalid token denied');
    $db->table('users')->where('id','qa-user')->update(['active'=>0]);
    verify($api->request('/api/v1/sales/receipts?company_id=qa-company')['status']===401,'Disabled token user denied');
    $db->table('users')->where('id','qa-user')->update(['active'=>1]);
    // Role changes must be read from MySQL, not stale claims or an unrelated web session.
    $db->table('roles')->insert(['id'=>'qa-viewer','name'=>'QA viewer','slug'=>'operador']);
    $db->table('users')->where('id','qa-user')->update(['role_id'=>'qa-viewer']);
    verify($api->request('/api/v1/sales/receipts?company_id=qa-company')['status']===403,'Current permissions enforced');
    $db->table('permissions')->insert(['id'=>'qa-permission','module'=>'systems','name'=>'View systems','slug'=>'systems.view']);
    $db->table('role_permission')->insert(['role_id'=>'qa-viewer','permission_id'=>'qa-permission']);
    $db->table('company_systems')->insert(['id'=>'qa-cs','company_id'=>'qa-company','system_id'=>'qa-sales','active'=>1]);
    $db->table('user_systems')->insert(['id'=>'qa-us','company_id'=>'qa-company','system_id'=>'qa-sales','user_id'=>'qa-user','active'=>1,'access_level'=>'view']);
    verify($api->request('/api/v1/sales/receipts?company_id=qa-company')['status']===200,'Read-only assigned user may list');
    $api->api('receipts',['company_id'=>'qa-company'],403);
    verify($api->request('/api/v1/sales/receipts?company_id=qa-foreign')['status']===403,'Foreign company denied');
    $db->table('users')->where('id','qa-user')->update(['role_id'=>'qa-role']);
    foreach(['web-one','api-one','advance-one','advance-two','race-one','race-two','race-three'] as $id) invoice($id);

    // 1. Web login, forms, actual form submission, detail and account setup.
    $form=$web->request('/ventas/cobranzas/nuevo?company_id=qa-company&popup=1',null,false);
    verify($form['status']===200 && str_contains($form['body'],'data-collection-items'),'Web form: '.substr(strip_tags($form['body']),0,200));
    $post=['company_id'=>'qa-company','request_key'=>app_uuid(),'items_receivable_id'=>['web-one'],'items_applied_amount'=>[100],'payments'=>[['payment_method'=>'cash','amount'=>100]]];
    $response=$web->request('/ventas/cobranzas',$post,false);
    verify(in_array($response['status'],[302,303],true),'Web store redirect HTTP '.$response['status']);
    verify((float)row('sales','web-one')['paid_total']===100.0,'Web receipt applied');
    $receipt=$db->table('sales_receipts')->get()->getRowArray();
    $detail=$web->request('/ventas/cobranzas/'.$receipt['id'].'/detalle?company_id=qa-company&popup=1',null,false);
    verify($detail['status']===200 && str_contains($detail['body'],$receipt['receipt_number']),'Receipt detail');
    $listing=$web->request('/ventas/cobranzas?company_id=qa-company',null,false);
    verify($listing['status']===200 && str_contains($listing['body'],$receipt['receipt_number']),'Web listing updated');
    $accountsPage=$web->request('/ventas/cobranzas/cuentas?company_id=qa-company&popup=1',null,false);
    verify($accountsPage['status']===200 && str_contains($accountsPage['body'],'card_clearing'),'Account configuration page');
    $passed[]='1. Migration/accounts and web receipt/form/detail/list';echo end($passed)," PASS\n";

    // 2. API mixed methods, individual transfer confirmation, clearing and reversal.
    $receipt=$api->api('receipts',payload('api-one',[['payment_method'=>'cash','amount'=>40],['payment_method'=>'transfer','amount'=>30,'reference'=>'QA-BANK'],['payment_method'=>'card','amount'=>10],['payment_method'=>'check','amount'=>10],['payment_method'=>'qr','amount'=>10]]),201);
    verify($receipt['status']==='partial' && (float)row('sales','api-one')['paid_total']===70.0,'Only confirmed methods apply');
    $lines=json_decode($receipt['payment_details'],true,512,JSON_THROW_ON_ERROR);
    $data=['company_id'=>'qa-company','confirmation_note'=>'QA bank verified','payment_id'=>$lines[1]['id']];
    $api->api('receipts/'.$receipt['id'].'/confirm',$data);$api->api('receipts/'.$receipt['id'].'/confirm',$data);
    verify((float)row('sales','api-one')['paid_total']===100.0,'Transfer exactly once');
    $movements=countRows('cash_movements');
    foreach(array_slice($lines,2) as $line) {
        $path='receipts/'.$receipt['id'].'/payments/'.$line['id'].'/settle';
        $api->api($path,['company_id'=>'qa-company','evidence'=>'QA accredited']);
        $api->api($path,['company_id'=>'qa-company','evidence'=>'QA retry']);
    }
    verify(countRows('cash_movements')===$movements,'Settlement does not collect twice');
    $api->api('receipts/'.$receipt['id'].'/void',['company_id'=>'qa-company','reason'=>'QA reversal']);
    $api->api('receipts/'.$receipt['id'].'/void',['company_id'=>'qa-company','reason'=>'QA retry']);
    verify((float)row('sales_receivables','api-one')['balance_amount']===100.0,'Reversal restores debt');
    invoice('direct-transfer');
    $db->table('sale_payments')->insert(['id'=>'qa-direct','sale_id'=>'direct-transfer','payment_method'=>'transfer','amount'=>100,'status'=>'pending','reference'=>'QA direct bank']);
    $api->api('direct-transfer/payments/qa-direct/confirm',['company_id'=>'qa-company','confirmation_note'=>'QA direct transfer verified']);
    $api->api('direct-transfer/payments/qa-direct/confirm',['company_id'=>'qa-company','confirmation_note'=>'QA retry']);
    verify((float)row('sales','direct-transfer')['paid_total']===100.0,'Direct transfer exactly once');
    $api->api('direct-transfer/cancel',['company_id'=>'qa-company'],422);
    invoice('cancel-one');
    $api->api('cancel-one/cancel',['company_id'=>'qa-company','cancellation_reason'=>'QA cancelled unpaid invoice']);
    $cancelledReceivable=row('sales_receivables','cancel-one');
    verify(row('sales','cancel-one')['status']==='cancelled' && ($cancelledReceivable===[] || (float)$cancelledReceivable['balance_amount']===0.0),'Cancellation closes receivable');
    $passed[]='2. API mixed collection, transfer, bank settlement and reversal';echo end($passed)," PASS\n";

    // 3. Credit, several invoices, refund, validation failures and rollback.
    $advance=['company_id'=>'qa-company','request_key'=>app_uuid(),'receipt_kind'=>'advance','customer_id'=>'qa-customer','payments'=>[['payment_method'=>'cash','amount'=>100]]];
    $receipt=$api->api('receipts',$advance,201);
    $again=$api->api('receipts',$advance,201);verify($again['id']===$receipt['id'],'Advance replay');
    $application=['company_id'=>'qa-company','request_key'=>app_uuid(),'items'=>[['receivable_id'=>'advance-one','applied_amount'=>20],['receivable_id'=>'advance-two','applied_amount'=>40]]];
    $api->api('receipts/'.$receipt['id'].'/apply',$application);$api->api('receipts/'.$receipt['id'].'/apply',$application);
    verify((float)row('sales','advance-one')['paid_total']===20.0 && (float)row('sales','advance-two')['paid_total']===40.0,'Applied to multiple invoices');
    $refund=['company_id'=>'qa-company','request_key'=>app_uuid(),'amount'=>40,'payment_method'=>'cash','reason'=>'QA refund'];
    $api->api('receipts/'.$receipt['id'].'/refund',$refund);$api->api('receipts/'.$receipt['id'].'/refund',$refund);
    verify((float)row('sales_receipts',$receipt['id'])['refunded_amount']===40.0,'Refund once');
    $snapshot=financialFingerprint($db);
    $refund['request_key']=app_uuid();$api->api('receipts/'.$receipt['id'].'/refund',$refund,422);
    $bad=$advance;$bad['request_key']=app_uuid();$bad['customer_id']='qa-other-customer';$api->api('receipts',$bad,422);
    $api->api('receipts/'.$receipt['id'].'/void',['company_id'=>'qa-foreign','reason'=>'QA wrong tenant'],422);
    $mapping=$db->table('company_settings')->where('company_id','qa-company')->where('key','account_receivable')->get()->getRowArray();
    $db->table('company_settings')->where('id',$mapping['id'])->delete();
    $api->api('receipts',payload('api-one'),422);
    $db->table('company_settings')->insert($mapping);
    verify(financialFingerprint($db)===$snapshot,'Failed operations leave no financial writes');
    // A real return endpoint restores stock and transfers excess payment to customer credit.
    invoice('return-one');
    $db->table('sales')->where('id','return-one')->update(['warehouse_id'=>'qa-warehouse']);
    $db->table('sale_items')->insert(['id'=>'qa-item','sale_id'=>'return-one','product_id'=>'qa-product','sku'=>'QA-1','product_name'=>'QA Product','quantity'=>10,'unit_price'=>10,'subtotal'=>100,'line_total'=>100,'tax_rate'=>0]);
    (new \App\Libraries\AccountingService())->syncSale('qa-company','return-one','qa-user');
    $api->api('receipts',payload('return-one'),201);
    $returned=$api->api('return-one/return',['company_id'=>'qa-company','warehouse_id'=>'qa-warehouse','reason'=>'QA return','items'=>[['sale_item_id'=>'qa-item','quantity'=>4]]],201);
    verify((float)$returned['total']===40.0 && (float)row('sales','return-one')['paid_total']===60.0,'Return credits excess paid');
    $credit=$db->table('sales_receipts')->where('receipt_kind','return_credit')->where('reference',$returned['id'])->get()->getRowArray();
    verify($credit!==null && (float)$credit['received_amount']===40.0,'Customer credit generated');
    $response=$web->request('/ventas/cobranzas/'.$credit['id'].'/reintegrar',['company_id'=>'qa-company','request_key'=>app_uuid(),'payment_method'=>'cash','amount'=>40,'reason'=>'QA returned goods refund'],false);
    verify(in_array($response['status'],[302,303],true) && (float)row('sales_receipts',$credit['id'])['refunded_amount']===40.0,'Web refund of return credit');
    $passed[]='3. Advances, applications, refund, isolation and atomic rollback';echo end($passed)," PASS\n";

    // Use a second JWT with an independent session for concurrent HTTP requests.
    $web->token=$api->token;
    // 4. Duplicate requests really overlap a held company lock in MySQL.
    $race=payload('race-one',[['payment_method'=>'transfer','amount'=>100,'reference'=>'QA-RACE']]);
    $results=parallel($web,$api,'receipts',$race);
    verify(array_column($results,'status')===[201,201],'Concurrent creation: '.json_encode(array_map(static fn($r)=>[$r['status'],$r['json']['message']??null],$results)));
    $id=$results[0]['json']['data']['id'];verify($id===$results[1]['json']['data']['id'],'Same idempotency key gives same receipt');
    $count=countRows('cash_movements');
    $results=parallel($web,$api,'receipts/'.$id.'/confirm',['company_id'=>'qa-company','confirmation_note'=>'QA simultaneous confirmation']);
    verify(array_column($results,'status')===[200,200],'Concurrent confirmation');
    verify(countRows('cash_movements')===$count+1 && (float)row('sales','race-one')['paid_total']===100.0,'No duplicate movement or payment');
    $passed[]='4. Concurrent HTTP creation and confirmation are idempotent';echo end($passed)," PASS\n";

    // 5. Competing receipts may not overpay the invoice; all journals balance.
    $results=parallel($web,$api,'receipts',payload('race-two'),payload('race-two'));
    $statuses=array_column($results,'status');sort($statuses);
    verify($statuses===[201,422],'One competing invoice payment rejected');
    verify((float)row('sales','race-two')['paid_total']===100.0 && (float)row('sales_receivables','race-two')['balance_amount']===0.0,'No concurrent overpayment');
    $advance['request_key']=app_uuid();$receipt=$api->api('receipts',$advance,201);
    $first=['company_id'=>'qa-company','request_key'=>app_uuid(),'items'=>[['receivable_id'=>'race-three','applied_amount'=>80]]];
    $second=$first;$second['request_key']=app_uuid();$second['items'][0]['receivable_id']='api-one';
    $results=parallel($web,$api,'receipts/'.$receipt['id'].'/apply',$first,$second);
    $statuses=array_column($results,'status');sort($statuses);verify($statuses===[200,422],'Concurrent advance cannot be spent twice');
    verify((new \App\Libraries\SalesCollectionService())->availableAdvance(row('sales_receipts',$receipt['id']))===20.0,'Advance remainder');
    $unbalanced=$db->query('SELECT journal_entry_id FROM journal_entry_lines GROUP BY journal_entry_id HAVING ABS(SUM(debit)-SUM(credit)) > 0.001')->getResultArray();
    verify($unbalanced===[],'Every posted journal balances');
    $receivableAccount=$db->table('company_settings')->where('company_id','qa-company')->where('key','account_receivable')->get()->getRowArray()['value'];
    $ledger=(float)$db->query('SELECT SUM(debit-credit) AS balance FROM journal_entry_lines WHERE account_id=?',[$receivableAccount])->getRowArray()['balance'];
    $debt=(float)$db->table('sales_receivables')->selectSum('balance_amount')->get()->getRowArray()['balance_amount'];
    verify(round($ledger,2)===round($debt,2),'Receivable ledger reconciles with customer balances');
    verify(financialFingerprint($source)===$before,'Real business data untouched');
    $passed[]='5. Concurrent overpayment/advance protection and ledger consistency';echo end($passed)," PASS\n";
    echo json_encode(['cases'=>count($passed),'assertions'=>$assertions,'engine'=>$db->getVersion(),'isolated_database'=>$name,'real_financial_data_unchanged'=>true],JSON_PRETTY_PRINT),"\n";
} finally {
    foreach($servers as $server) {proc_terminate($server);proc_close($server);}
    if($db) $db->close();
    if($created && preg_match('/\Acodex_collections_check_[a-f0-9]{10}\z/',$name)) $source->query('DROP DATABASE `'.$name.'`');
    $source->close();
    // Remove only this run's verified directory, including synthetic credentials
    // that development debug output may have recorded. Never follow symlinks.
    $actual=realpath($qaDir);
    $expected=realpath($root.'/tmp').DIRECTORY_SEPARATOR.$name;
    if($actual!==false && $actual===$expected) {
        $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($actual,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach($files as $file) {
            if($file->isLink() || $file->isFile()) unlink($file->getPathname());
            elseif($file->isDir()) rmdir($file->getPathname());
        }
        rmdir($actual);
    }
}
