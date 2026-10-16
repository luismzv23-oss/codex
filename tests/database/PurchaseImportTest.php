<?php
use App\Libraries\{PurchaseImportService,PurchaseDocumentStorage,PurchaseInvoiceService,PurchaseImportWorker};
use App\Libraries\PurchaseImport\{InvoiceExtractor,InvoiceExtractionNormalizer};
use CodeIgniter\Test\CIUnitTestCase;

final class PurchaseImportTest extends CIUnitTestCase
{
    private array $tables=[];private string $directory;private string $image;private PurchaseDocumentStorage $storage;private PurchaseImportService $service;
    protected function setUp(): void
    {
        parent::setUp();helper(['app','auth','url']);$this->db=db_connect();$this->db->resetDataCache();
        foreach (['Company','Supplier','Currency','InventoryProduct','InventoryStockLevel','InventoryMovement','PurchaseInvoice','PurchaseInvoiceItem','PurchasePayable','PurchaseReceipt','PurchaseReceiptItem','PurchaseReturn','PurchaseReturnItem','PurchaseCreditNote','SupplierCostHistory'] as $name) {
            $class='App\\Models\\'.$name.'Model';$model=new $class();$ref=new ReflectionClass($model);
            $property=$ref->getProperty('allowedFields');$property->setAccessible(true);
            $fields=array_unique(array_merge(['id','created_at','updated_at'],$property->getValue($model)));
            $table=$ref->getProperty('table');$table->setAccessible(true);$table=$table->getValue($model);
            $columns=array_map(static fn($field)=>'"'.$field.'" '.($field==='id'?'TEXT PRIMARY KEY':'TEXT'),$fields);
            $this->db->query('CREATE TABLE '.$this->db->prefixTable($table).' ('.implode(',',$columns).')');$this->tables[]=$table;
        }
        require_once APPPATH.'Database/Migrations/2026-10-16-150000_CreatePurchaseInvoiceImports.php';
        (new \App\Database\Migrations\CreatePurchaseInvoiceImports(\Config\Database::forge('tests')))->up();
        require_once APPPATH.'Database/Migrations/2026-10-16-152000_KeepPurchaseImportExtractionHistory.php';
        (new \App\Database\Migrations\KeepPurchaseImportExtractionHistory(\Config\Database::forge('tests')))->up();
        $this->tables=array_merge($this->tables,['purchase_documents','purchase_imports','purchase_import_items','purchase_import_jobs','purchase_import_audit','supplier_product_links']);
        foreach (['a','b'] as $company) {
            $this->put('companies',['id'=>$company,'name'=>$company,'currency_code'=>'ARS','tax_id'=>'30712345678']);
            $this->put('suppliers',['id'=>$company.'-supplier','company_id'=>$company,'name'=>'Proveedor '.$company,'tax_id'=>'30123456789','active'=>1]);
            $this->put('currencies',['id'=>$company.'-ars','company_id'=>$company,'code'=>'ARS','name'=>'Pesos','active'=>1]);
            $this->put('inventory_products',['id'=>$company.'-product','company_id'=>$company,'sku'=>'P-1','name'=>'Producto','active'=>1]);
        }
        $this->directory=ROOTPATH.'tmp/import-tests-'.app_uuid();mkdir($this->directory,0700,true);
        $config=new \Config\PurchaseImport();$config->storageRoot=$this->directory.'/private';
        $this->storage=new PurchaseDocumentStorage($config);$this->service=new PurchaseImportService($this->storage);
        $this->image=$this->directory.'/invoice.png';
        $chunk=static fn($type,$data)=>pack('N',strlen($data)).$type.$data.pack('N',crc32($type.$data));
        file_put_contents($this->image,"\x89PNG\r\n\x1a\n".$chunk('IHDR',pack('NNCCCCC',100,100,8,2,0,0,0)).$chunk('IDAT',gzcompress(str_repeat("\x00".str_repeat("\xff",300),100))).$chunk('IEND',''));
    }
    protected function tearDown(): void
    {
        foreach(array_reverse($this->tables) as $table) $this->db->query('DROP TABLE '.$this->db->prefixTable($table));
        if (isset($this->directory) && is_dir($this->directory) && str_starts_with(str_replace('\\','/',realpath($this->directory)),str_replace('\\','/',realpath(ROOTPATH.'tmp')).'/import-tests-')) {
            $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {if($file->isDir()) rmdir($file->getPathname());else unlink($file->getPathname());}rmdir($this->directory);
        }
        $this->db->resetDataCache();parent::tearDown();
    }
    private function put(string $table,array $row): void {$this->db->table($table)->insert($row);}
    private function draft(): array {return $this->service->upload('a',$this->image,'Factura.png','admin');}
    private function input(array $replace=[]): array
    {
        return array_replace(['supplier_id'=>'a-supplier','supplier_tax_id'=>'30123456789','receiver_tax_id'=>'30712345678','document_type'=>'FACTURA_A',
            'point_of_sale'=>'1','invoice_sequence'=>'123','currency_code'=>'ARS','exchange_rate'=>'1','purchase_kind'=>'pending_receipt','issue_date'=>'2026-10-16',
            'due_date'=>'2026-10-30','reviewed_total'=>'242.00','identity_reviewed'=>true,'items'=>[['description'=>'Producto','quantity'=>'2','unit_cost'=>'100','tax_rate'=>'21','tax_amount'=>'42.00','product_id'=>'a-product','supplier_code'=>'PROV-1']],'adjustments'=>[]],$replace);
    }
    private function saveReady(array $replace=[]): array {$draft=$this->draft();return $this->service->save('a',$draft['id'],['version'=>$draft['version'],'data'=>$this->input($replace)],'admin');}
    public function testUploadKeepsPrivateOriginalAndDoesNotCreateFinancialOrStockEntries(): void
    {
        $draft=$this->draft();$this->assertSame('draft',$draft['review_status']);$this->assertEquals(1,$draft['document']['page_count']);
        foreach (['purchase_invoices','purchase_payables','supplier_cost_history','inventory_movements'] as $table) $this->assertSame(0,$this->db->table($table)->countAllResults());
        $document=$this->db->table('purchase_documents')->get()->getRowArray();$this->assertSame(hash_file('sha256',$this->image),hash_file('sha256',$this->storage->path($document)));
        $again=$this->draft();$this->assertSame($draft['id'],$again['id']);$this->assertTrue($again['reused']);
        $other=$this->service->upload('b',$this->image,'Factura.png','other-admin');$this->assertNotSame($draft['id'],$other['id']);
    }
    public function testMismatchedFileExtensionAndMalformedPdfAreRejected(): void
    {
        try {$this->storage->inspect($this->image,'fake.pdf');$this->fail('Must reject forged extension');}catch(RuntimeException $e){$this->assertStringContainsString('formato',$e->getMessage());}
        $path=$this->directory.'/bad.pdf';file_put_contents($path,"%PDF-1.4\nBroken document");
        $this->expectException(RuntimeException::class);$this->storage->inspect($path,'bad.pdf');
    }
    public function testPdfParserChecksPagesWithoutExecutingDocumentActions(): void
    {
        $pdf=new \Dompdf\Dompdf();$pdf->loadHtml('<h1>Factura</h1><div style="page-break-before:always">Segunda página</div>');$pdf->render();
        $path=$this->directory.'/invoice.pdf';file_put_contents($path,$pdf->output());$info=$this->storage->inspect($path,'invoice.pdf');
        $this->assertSame(2,$info['page_count']);
        $config=new \Config\PurchaseImport();$config->maxPages=1;$storage=new PurchaseDocumentStorage($config);
        $this->expectException(RuntimeException::class);$storage->inspect($path,'invoice.pdf');
    }
    public function testOptimisticVersionPreventsOverwritingAnotherReview(): void
    {
        $draft=$this->draft();$saved=$this->service->save('a',$draft['id'],['version'=>1,'data'=>['supplier_name'=>'Corrección manual','items'=>[]]],'admin');
        $this->assertSame(2,$saved['version']);$this->assertSame('needs_review',$saved['review_status']);
        $this->expectException(RuntimeException::class);$this->service->save('a',$draft['id'],['version'=>1,'data'=>$this->input()],'other-admin');
    }
    public function testConfirmationRegistersExactlyOnceAndKeepsOriginalAndProductMapping(): void
    {
        $draft=$this->saveReady();$this->assertSame('ready',$draft['review_status']);
        $request=['version'=>$draft['version'],'request_key'=>app_uuid()];
        $confirmed=$this->service->confirm('a',$draft['id'],$request,'admin');
        $again=$this->service->confirm('a',$draft['id'],$request,'admin');
        $this->assertSame($confirmed['invoice_id'],$again['invoice_id']);$this->assertSame('registered',$confirmed['review_status']);
        $this->assertSame(1,$this->db->table('purchase_invoices')->countAllResults());$this->assertSame(1,$this->db->table('purchase_payables')->countAllResults());
        $invoice=$this->db->table('purchase_invoices')->get()->getRowArray();$this->assertSame('00001-00000123',$invoice['invoice_number']);$this->assertEquals(242,$invoice['total']);
        $this->assertSame('a-product',$this->db->table('supplier_product_links')->get()->getRowArray()['product_id']);
        $this->assertSame(0,$this->db->table('inventory_movements')->countAllResults());
        $this->expectException(RuntimeException::class);$this->service->save('a',$draft['id'],['version'=>$confirmed['version'],'data'=>$this->input()],'admin');
    }
    public function testWrongTotalOrSupplierTaxIdCannotCreateDebt(): void
    {
        foreach ([['reviewed_total'=>'200'],['supplier_tax_id'=>'30999999999'],['receiver_tax_id'=>'30999999999'],['identity_reviewed'=>false]] as $replace) {
            $draft=$this->saveReady($replace);
            try {$this->service->confirm('a',$draft['id'],['version'=>$draft['version'],'request_key'=>app_uuid()],'admin');$this->fail('Invalid invoice');}catch(RuntimeException $e){$this->assertNotEmpty($e->getMessage());}
            $this->assertSame(0,$this->db->table('purchase_payables')->countAllResults());
        }
    }
    public function testManualRegistrationAndImportShareDuplicateProtection(): void
    {
        $manual=$this->input();$manual['request_key']=app_uuid();
        (new PurchaseInvoiceService())->register('a',$manual,'admin',true);
        $draft=$this->saveReady();
        try {$this->service->confirm('a',$draft['id'],['version'=>$draft['version'],'request_key'=>app_uuid()],'admin');$this->fail('Duplicate invoice');}catch(RuntimeException $e){$this->assertStringContainsString('ya está registrada',$e->getMessage());}
        $this->assertSame(1,$this->db->table('purchase_invoices')->countAllResults());
        $this->assertSame('ready',$this->service->get('a',$draft['id'])['review_status']);
    }
    public function testHistoricalUnpaddedNumberIsNotSilentlyDuplicated(): void
    {
        $this->put('purchase_invoices',['id'=>'old','company_id'=>'a','supplier_id'=>'a-supplier','invoice_number'=>'1-123','status'=>'registered']);
        $this->expectException(RuntimeException::class);(new PurchaseInvoiceService())->register('a',$this->input(),'admin',true);
    }
    public function testPartialInvoiceReplacesProvisionalDebtWithoutReceivingStockTwice(): void
    {
        $this->put('purchase_receipts',['id'=>'receipt','company_id'=>'a','supplier_id'=>'a-supplier','currency_code'=>'ARS','total'=>100,'status'=>'registered']);
        $this->put('purchase_receipt_items',['id'=>'received','purchase_receipt_id'=>'receipt','product_id'=>'a-product','quantity'=>10,'unit_cost'=>10,'line_total'=>100]);
        $this->put('purchase_payables',['id'=>'payable','company_id'=>'a','supplier_id'=>'a-supplier','purchase_receipt_id'=>'receipt','currency_code'=>'ARS','total_amount'=>100,'paid_amount'=>30,'balance_amount'=>70,'status'=>'partial']);
        $input=$this->input(['purchase_kind'=>'goods','purchase_receipt_id'=>'receipt','reviewed_total'=>'70','items'=>[['product_id'=>'a-product','description'=>'Producto','quantity'=>'4','unit_cost'=>'15','tax_rate'=>'0']],'adjustments'=>[['kind'=>'charge','label'=>'Flete','amount'=>'10']]]);
        (new PurchaseInvoiceService())->register('a',$input,'admin',true);
        $debt=$this->db->table('purchase_payables')->where('id','payable')->get()->getRowArray();
        $this->assertEquals(130,$debt['total_amount']);$this->assertEquals(100,$debt['balance_amount']);$this->assertEquals(30,$debt['paid_amount']);
        $this->assertSame(1,$this->db->table('purchase_payables')->countAllResults());$this->assertSame(0,$this->db->table('inventory_movements')->countAllResults());
    }
    public function testAdjustmentsAreExplicitAndMustReconcileToDocumentTotal(): void
    {
        $input=$this->input(['reviewed_total'=>'250','adjustments'=>[
            ['kind'=>'charge','label'=>'Flete','amount'=>'10'],['kind'=>'discount','label'=>'Descuento','amount'=>'5'],
            ['kind'=>'tax','label'=>'Otro impuesto','amount'=>'2'],['kind'=>'vat','label'=>'IVA adicional','amount'=>'1'],
        ]]);
        $invoice=(new PurchaseInvoiceService())->register('a',$input,'admin',true);
        $this->assertEquals(207,$invoice['subtotal']);$this->assertEquals(43,$invoice['tax_total']);$this->assertEquals(250,$invoice['total']);
    }
    public function testOtherCompanyCannotReadOrConfirmTheDraft(): void
    {
        $draft=$this->draft();$this->expectException(RuntimeException::class);$this->service->get('b',$draft['id']);
    }
    public function testFileControllerRejectsSellerAndCrossCompanyAccess(): void
    {
        $draft=$this->draft();
        foreach ([['vendedor','a'],['admin','b']] as [$role,$company]) {
            $controller=new class($role,$company) extends \App\Controllers\PurchaseImportsController {
                private string $role;private string $company;
                public function __construct($role,$company){$this->role=$role;$this->company=$company;}
                protected function roleSlug(): ?string{return $this->role;}
                protected function purchaseContext(string $requiredAccess='view'){return ['company'=>['id'=>$this->company]];}
            };
            $controller->initController(service('request'),service('response'),service('logger'));
            $this->assertSame(404,$controller->file($draft['id'])->getStatusCode());
        }
    }
    private function extracted(): array
    {
        return ['documents'=>[['fields'=>[
            'VendorTaxId'=>['valueString'=>'30-12345678-9','confidence'=>.99],
            'InvoiceId'=>['valueString'=>'00001-00000123','confidence'=>.98,'boundingRegions'=>[['pageNumber'=>1,'polygon'=>[1,1,2,1,2,2,1,2]]]],
            'InvoiceTotal'=>['valueCurrency'=>['amount'=>242,'currencyCode'=>'ARS'],'confidence'=>.99],
            'Items'=>['valueArray'=>[['valueObject'=>['Description'=>['valueString'=>'Producto'],'Quantity'=>['valueNumber'=>2],'UnitPrice'=>['valueCurrency'=>['amount'=>100]],'Tax'=>['valueCurrency'=>['amount'=>42]]]]]],
        ]]],'content'=>'FACTURA A','pages'=>[['pageNumber'=>1,'width'=>8.5,'height'=>11,'unit'=>'inch']]];
    }
    public function testExtractionNormalizesSuggestionsAndKeepsSourceCoordinates(): void
    {
        $result=(new InvoiceExtractionNormalizer())->normalize('a',$this->extracted(),['page_count'=>1]);
        $this->assertSame('a-supplier',$result['suggestions']['supplier_id']);$this->assertSame('FACTURA_A',$result['suggestions']['document_type']);
        $this->assertSame('242.00',$result['suggestions']['reviewed_total']);$this->assertSame(1,$result['locations']['invoice_number']['regions'][0]['page']);
        $this->assertSame('100.000000',$result['suggestions']['items'][0]['unit_cost']);
    }
    public function testTruncatedCloudResultIsRejected(): void
    {
        $this->expectException(RuntimeException::class);(new InvoiceExtractionNormalizer())->normalize('a',$this->extracted(),['page_count'=>2]);
    }
    public function testWorkerPreservesManualCorrectionsAndNeverRegistersInvoice(): void
    {
        $draft=$this->saveReady(['notes'=>'Corrección que debe conservarse']);
        $this->put('purchase_import_jobs',['id'=>'job','company_id'=>'a','import_id'=>$draft['id'],'generation'=>1,'status'=>'queued','attempts'=>0,'available_at'=>'2000-01-01 00:00:00']);
        $this->db->table('purchase_imports')->where('id',$draft['id'])->update(['generation'=>1,'processing_status'=>'queued']);
        $result=$this->extracted();
        $extractor=new class($result) implements InvoiceExtractor {
            private array $result;public function __construct($result){$this->result=$result;}
            public function configured(): bool{return true;}
            public function start(string $path,array $document): string{return 'https://fixture.invalid/operation';}
            public function poll(string $url): ?array{return $this->result;}
        };
        $worker=new PurchaseImportWorker($extractor,$this->storage);$this->assertSame(1,$worker->run(1,'a'));
        $this->db->table('purchase_import_jobs')->where('id','job')->update(['available_at'=>'2000-01-01 00:00:00']);
        $worker->run(1,'a');$loaded=$this->service->get('a',$draft['id']);
        $this->assertSame('done',$loaded['processing_status']);$this->assertSame('Corrección que debe conservarse',$loaded['data']['notes']);
        $this->assertSame($draft['version'],$loaded['version']);$this->assertArrayHasKey('suggestions',$loaded['extraction']);
        $this->assertSame(0,$this->db->table('purchase_invoices')->countAllResults());
    }
    public function testWorkerRetriesAreBoundedAndManualDraftSurvives(): void
    {
        $draft=$this->draft();
        $this->put('purchase_import_jobs',['id'=>'job','company_id'=>'a','import_id'=>$draft['id'],'generation'=>1,'status'=>'queued','attempts'=>0,'available_at'=>'2000-01-01 00:00:00']);
        $this->db->table('purchase_imports')->where('id',$draft['id'])->update(['generation'=>1,'processing_status'=>'queued']);
        $extractor=new class implements InvoiceExtractor {
            public function configured(): bool{return true;}
            public function start(string $path,array $document): string{throw new RuntimeException('Servicio temporalmente no disponible.');}
            public function poll(string $url): ?array{return null;}
        };
        $worker=new PurchaseImportWorker($extractor,$this->storage);
        for($i=0;$i<3;$i++) {$this->db->table('purchase_import_jobs')->where('id','job')->update(['available_at'=>'2000-01-01 00:00:00']);$worker->run(1,'a');}
        $this->assertSame('failed',$this->db->table('purchase_import_jobs')->get()->getRowArray()['status']);
        $this->assertSame('error',$this->service->get('a',$draft['id'])['processing_status']);$this->assertSame(0,$this->db->table('purchase_invoices')->countAllResults());
    }
    public function testAssistantRendersOnlyFormInPopupAndEscapesProviderNames(): void
    {
        $html=view('purchases/imports',['companyId'=>'a','pageTitle'=>'Asistente','isPopup'=>true,'context'=>['company'=>['id'=>'a']],
            'drafts'=>[],'suppliers'=>[['id'=>'a-supplier','name'=>'<script>bad</script>','tax_id'=>'30123456789']],
            'products'=>[],'receipts'=>[],'currencies'=>[['code'=>'ARS','name'=>'Pesos']],'ocrConfigured'=>false],['saveData'=>false]);
        $this->assertStringContainsString('Arrastra tu factura',$html);$this->assertStringContainsString('todavía no está configurada',$html);
        $this->assertStringNotContainsString('<nav class="navbar',$html);$this->assertStringNotContainsString('<script>bad</script>',$html);
        $this->assertStringContainsString('purchase-imports.js',$html);
    }
    public function testConfirmingManuallyCancelsPendingReadingWithoutLosingTheReview(): void
    {
        $draft=$this->saveReady();
        $this->put('purchase_import_jobs',['id'=>'late','company_id'=>'a','import_id'=>$draft['id'],'generation'=>1,'status'=>'running','lease_token'=>'old-worker','lease_until'=>'2099-01-01 00:00:00']);
        $this->db->table('purchase_imports')->where('id',$draft['id'])->update(['generation'=>1,'processing_status'=>'reading']);
        $confirmed=$this->service->confirm('a',$draft['id'],['version'=>$draft['version'],'request_key'=>app_uuid()],'admin');
        $this->assertSame('registered',$confirmed['review_status']);$this->assertSame('idle',$confirmed['processing_status']);
        $this->assertSame('cancelled',$this->db->table('purchase_import_jobs')->where('id','late')->get()->getRowArray()['status']);
    }
    public function testExpiredWorkerLeaseCanBeRecoveredWithoutReplacingCorrectedData(): void
    {
        $draft=$this->saveReady(['notes'=>'Revisión propia']);
        $this->put('purchase_import_jobs',['id'=>'expired','company_id'=>'a','import_id'=>$draft['id'],'generation'=>1,'status'=>'running','attempts'=>0,
            'lease_token'=>'expired-token','lease_until'=>'2000-01-01 00:00:00','operation_url'=>'fixture-operation']);
        $this->db->table('purchase_imports')->where('id',$draft['id'])->update(['generation'=>1,'processing_status'=>'reading']);
        $result=$this->extracted();
        $extractor=new class($result) implements InvoiceExtractor {
            private array $result;public function __construct($result){$this->result=$result;}
            public function configured(): bool{return true;}public function start(string $path,array $document): string{throw new LogicException('Must resume');}
            public function poll(string $url): ?array{return $this->result;}
        };
        (new PurchaseImportWorker($extractor,$this->storage))->run(1,'a');
        $job=$this->db->table('purchase_import_jobs')->where('id','expired')->get()->getRowArray();
        $this->assertSame('done',$job['status']);$this->assertNotEmpty($job['result_json']);
        $this->assertSame('Revisión propia',$this->service->get('a',$draft['id'])['data']['notes']);
    }

}
