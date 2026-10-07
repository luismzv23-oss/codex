<?php
use App\Libraries\PurchaseDocumentService;
use App\Libraries\PurchaseDocumentVault;
use App\Libraries\PurchasePdfExtractor;
use CodeIgniter\Test\CIUnitTestCase;

final class PurchaseDocumentTest extends CIUnitTestCase
{
    private array $tables=[];
    private string $directory;
    private PurchaseDocumentVault $vault;
    private PurchaseDocumentService $documents;
    protected function setUp(): void
    {
        parent::setUp(); helper(['app','url','auth']);
        $this->directory=WRITEPATH.'tests-documents/'.bin2hex(random_bytes(8)).'/';
        $this->vault=new PurchaseDocumentVault($this->directory.'master.key',$this->directory.'archive/');$this->vault->initialize();
        $this->documents=new PurchaseDocumentService($this->vault);
        $db=db_connect();
        foreach(['Company','Supplier','InventoryProduct','PurchaseInvoice','PurchaseInvoiceItem','PurchasePayable','PurchaseCreditNote','SupplierCostHistory','PurchaseReceipt','PurchaseReceiptItem'] as $name) {
            $class='App\\Models\\'.$name.'Model';$model=new $class();$ref=new ReflectionClass($model);
            $fields=$ref->getProperty('allowedFields');$fields->setAccessible(true);
            $table=$ref->getProperty('table');$table->setAccessible(true);$table=$table->getValue($model);
            $columns=[];
            foreach(array_unique(array_merge(['id','created_at','updated_at'],$fields->getValue($model))) as $field) $columns[]='"'.$field.'" '.($field==='id'?'TEXT PRIMARY KEY':'TEXT');
            $db->query('CREATE TABLE '.$db->prefixTable($table).' ('.implode(',',$columns).')');$this->tables[]=$table;
        }
        foreach(['purchase_documents'=>'id TEXT PRIMARY KEY, company_id TEXT, sha256 TEXT, original_name TEXT, size_bytes INTEGER, status TEXT, purchase_invoice_id TEXT, draft_cipher TEXT, revision INTEGER DEFAULT 0, created_by TEXT, updated_by TEXT, created_at TEXT, updated_at TEXT',
            'purchase_document_events'=>'id TEXT PRIMARY KEY, document_id TEXT, company_id TEXT, user_id TEXT, action TEXT, created_at TEXT'] as $table=>$schema) {
            $db->query('CREATE TABLE '.$db->prefixTable($table).' ('.$schema.')');$this->tables[]=$table;
        }
        $db->table('companies')->insert(['id'=>'a']);$db->table('companies')->insert(['id'=>'b']);
        $db->table('suppliers')->insert(['id'=>'s','company_id'=>'a','active'=>1]);
        $db->table('inventory_products')->insert(['id'=>'p','company_id'=>'a','active'=>1]);
    }
    protected function tearDown(): void
    {
        foreach(array_reverse($this->tables) as $table) db_connect()->query('DROP TABLE '.db_connect()->prefixTable($table));
        $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach($iterator as $file) { $file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname()); }rmdir($this->directory);
        parent::tearDown();
    }
    private function image(): string
    {
        $image=imagecreatetruecolor(8,8);ob_start();imagepng($image);$bytes=ob_get_clean();imagedestroy($image);return $bytes;
    }
    private function upload(): array { return $this->documents->upload('a','user','factura.png',$this->image()); }
    private function draft(): array
    {
        return ['supplier_id'=>'s','invoice_number'=>'A 00001-00000007','issue_date'=>'2026-10-05','due_date'=>'','currency_code'=>'ARS','exchange_rate'=>1,'expected_subtotal'=>180,'expected_tax'=>37.8,'expected_total'=>217.8,'reviewed'=>true,
            'items'=>[['product_id'=>'p','description'=>'Producto comprado','quantity'=>2,'pack'=>3,'unit_cost'=>100,'discount'=>10,'tax_rate'=>21]]];
    }
    public function testEncryptedArchiveDetectsTamperingAndKeepsOriginalBytes(): void
    {
        $doc=$this->upload();$path=$this->directory.'archive/a/'.$doc['id'].'.enc';
        $this->assertSame($this->image(),$this->documents->pdf('a',$doc['id']));
        $this->assertStringNotContainsString($this->image(),file_get_contents($path));
        $cipher=json_decode(file_get_contents($path),true);$cipher['tag']=base64_encode(str_repeat('x',16));file_put_contents($path,json_encode($cipher));
        $this->expectException(RuntimeException::class);$this->documents->pdf('a',$doc['id']);
    }
    public function testDuplicateUploadResumesAndTenantCannotReadIt(): void
    {
        $doc=$this->upload();$again=$this->upload();$this->assertSame($doc['id'],$again['id']);$this->assertTrue($again['duplicate']);
        $this->expectException(RuntimeException::class);$this->documents->pdf('b',$doc['id']);
    }
    public function testDraftIsEncryptedAndStaleSaveDoesNotOverwriteIt(): void
    {
        $doc=$this->upload();$saved=$this->documents->save('a',$doc['id'],'user',$this->draft(),0);
        $this->assertSame(1,$saved['revision']);
        $row=$this->documents->owned('a',$doc['id']);$this->assertStringNotContainsString('Producto comprado',$row['draft_cipher']);
        $this->assertSame('Producto comprado',$saved['draft']['items'][0]['description']);
        $this->expectException(RuntimeException::class);$this->documents->save('a',$doc['id'],'user',['items'=>[]],0);
    }
    public function testConfirmationReusesPurchaseLedgerAndIsIdempotent(): void
    {
        $doc=$this->upload();$this->documents->save('a',$doc['id'],'user',$this->draft(),0);
        $done=$this->documents->confirm('a',$doc['id'],'user',1,['ARS']);
        $again=$this->documents->confirm('a',$doc['id'],'user',1,['ARS']);
        $this->assertSame('registered',$done['status']);$this->assertSame($done['invoice_id'],$again['invoice_id']);
        $db=db_connect();$this->assertSame(1,$db->table('purchase_invoices')->countAllResults());
        $invoice=$db->table('purchase_invoices')->get()->getRowArray();$this->assertEquals(217.8,$invoice['total']);
        $item=$db->table('purchase_invoice_items')->get()->getRowArray();$this->assertEquals(6,$item['quantity']);$this->assertEquals(30,$item['unit_cost']);
        $payable=$db->table('purchase_payables')->get()->getRowArray();$this->assertEquals(217.8,$payable['balance_amount']);
        $this->assertSame(0,$db->table('purchase_receipts')->countAllResults());
        $this->assertSame(1,$db->table('supplier_cost_history')->countAllResults());
    }
    public function testMismatchedTotalsDoNotCreateInvoice(): void
    {
        $doc=$this->upload();$draft=$this->draft();$draft['expected_total']=999;$this->documents->save('a',$doc['id'],'user',$draft,0);
        try { $this->documents->confirm('a',$doc['id'],'user',1,['ARS']);$this->fail('Must reject mismatch'); }
        catch(RuntimeException $e) { $this->assertStringContainsString('no coinciden',$e->getMessage()); }
        $this->assertSame(0,db_connect()->table('purchase_invoices')->countAllResults());
        $this->assertSame('draft',$this->documents->owned('a',$doc['id'])['status']);
    }
    public function testForeignProductAndUnreviewedDraftAreRejected(): void
    {
        $doc=$this->upload();$draft=$this->draft();$draft['reviewed']=false;$this->documents->save('a',$doc['id'],'user',$draft,0);
        try {$this->documents->confirm('a',$doc['id'],'user',1,['ARS']);$this->fail('Must require review');} catch(RuntimeException $e) {$this->assertStringContainsString('revisaste',$e->getMessage());}
        $this->expectException(RuntimeException::class);$this->documents->rows('b',$draft['items']);
    }
    public function testParserSuggestsOnlyBalancedRowsAndUniqueSupplier(): void
    {
        $text="CUIT 30-12345678-9\nFactura 00001-00000007\nFecha: 05/10/2026\nSKU1 Producto  2 100,00 21 242,00\nTOTAL 242,00";
        $result=(new PurchasePdfExtractor())->suggest($text,[['id'=>'s','tax_id'=>'30123456789']],[['id'=>'p','sku'=>'SKU1']]);
        $this->assertSame('s',$result['supplier_id']);$this->assertSame('2026-10-05',$result['issue_date']);$this->assertCount(1,$result['items']);$this->assertSame('p',$result['items'][0]['product_id']);
        $bad=(new PurchasePdfExtractor())->suggest(str_replace('242,00','999,00',$text),[],[]);$this->assertSame([],$bad['items']);
    }
    public function testUnrecognizedFileIsRejected(): void
    {
        $this->expectException(RuntimeException::class);$this->documents->upload('a','user','invoice.pdf','<script>not a PDF</script>');
    }
}
