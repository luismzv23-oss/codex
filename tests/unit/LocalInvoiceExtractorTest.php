<?php
use App\Libraries\PurchaseImport\{LocalInvoiceExtractor,LocalOcrProcess};
use CodeIgniter\Test\CIUnitTestCase;
final class LocalInvoiceExtractorTest extends CIUnitTestCase
{
    private string $directory;
    private \Config\PurchaseImport $settings;
    protected function setUp(): void
    {
        parent::setUp();
        $this->directory=ROOTPATH.'tmp/local-ocr-'.bin2hex(random_bytes(8));mkdir($this->directory,0700,true);
        $this->settings=new \Config\PurchaseImport();$this->settings->storageRoot=$this->directory.'/private';
    }
    protected function tearDown(): void
    {
        $root=realpath(ROOTPATH.'tmp');
        if (isset($this->directory) && is_dir($this->directory) && str_starts_with(str_replace('\\','/',realpath($this->directory)),str_replace('\\','/',$root).'/local-ocr-')) {
            $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {if ($file->isDir()) rmdir($file->getPathname());else unlink($file->getPathname());}rmdir($this->directory);
        }
        parent::tearDown();
    }
    private function reader(): LocalInvoiceExtractor
    {
        $reader=new LocalInvoiceExtractor($this->settings);
        if (!$reader->configured()) $this->markTestSkipped('Requiere Tesseract/Poppler e idiomas spa+eng instalados.');
        return $reader;
    }
    private function image(): string
    {
        if (!function_exists('imagettftext')) $this->markTestSkipped('Requiere GD.');
        $font=PHP_OS_FAMILY==='Windows'?'C:/Windows/Fonts/arial.ttf':'/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
        if (!is_file($font)) $this->markTestSkipped('Requiere una fuente de prueba.');
        $image=imagecreatetruecolor(1400,900);$white=imagecolorallocate($image,255,255,255);$black=imagecolorallocate($image,0,0,0);imagefill($image,0,0,$white);
        foreach (['FACTURA A 00001-00000123','Proveedor: Ejemplo SRL','CUIT: 30-12345678-9','Fecha: 16/10/2026','TOTAL 242,00'] as $i=>$line) imagettftext($image,30,0,60,70+$i*100,$black,$font,$line);
        $path=$this->directory.'/invoice.png';imagepng($image,$path);imagejpeg($image,$this->directory.'/invoice.jpg',95);imagedestroy($image);return $path;
    }
    public function testPngAndJpegAreReadLocallyWithRealOcrAndCoordinates(): void
    {
        $reader=$this->reader();$this->image();
        foreach (['png'=>'image/png','jpg'=>'image/jpeg'] as $extension=>$mime) {
            $result=$reader->extract($this->directory.'/invoice.'.$extension,['page_count'=>1,'mime_type'=>$mime]);
            $this->assertSame(['tesseract'],$result['methods']);
            $this->assertSame('242.00',$result['documents'][0]['fields']['InvoiceTotal']['valueString']);
            $this->assertNotEmpty($result['documents'][0]['fields']['InvoiceTotal']['boundingRegions']);
        }
        $this->assertSame([],glob($this->settings->storageRoot.'/work/*'));
    }
    public function testNativeAndScannedPagesUseDifferentReadersInTheSamePdf(): void
    {
        $reader=$this->reader();$image=$this->image();
        $pdf=new \Dompdf\Dompdf();$pdf->loadHtml('<p>FACTURA A 00001-00000123</p><p>Proveedor: Ejemplo SRL</p><p>CUIT: 30-12345678-9</p><p>TOTAL 242,00</p>'.
            '<div style="page-break-before:always"><img style="width:550px" src="data:image/png;base64,'.base64_encode(file_get_contents($image)).'"></div>');$pdf->render();
        $path=$this->directory.'/mixed.pdf';file_put_contents($path,$pdf->output());
        $result=$reader->extract($path,['page_count'=>2,'mime_type'=>'application/pdf']);
        $this->assertSame(['pdf-text','tesseract'],$result['methods']);
        $this->assertCount(2,$result['pages']);$this->assertSame('242.00',$result['documents'][0]['fields']['InvoiceTotal']['valueString']);
    }
    public function testProcessArgumentsArePassedLiterallyWithoutShellInterpretation(): void
    {
        $this->settings->tesseractPath=PHP_BINARY;
        $result=(new LocalOcrProcess($this->settings))->run('tesseract',['-r','echo $argv[1];','hello & echo unsafe'],$this->directory,microtime(true)+10);
        $this->assertSame('hello & echo unsafe',$result);
    }
    public function testLongRunningProcessIsTerminatedAndTemporaryOutputRemoved(): void
    {
        $this->settings->tesseractPath=PHP_BINARY;$this->settings->processTimeout=1;
        try {(new LocalOcrProcess($this->settings))->run('tesseract',['-r','sleep(5);'],$this->directory,microtime(true)+10);$this->fail('Timeout required');}
        catch (RuntimeException $e) {$this->assertStringContainsString('tiempo',$e->getMessage());}
        $this->assertSame([],glob($this->directory.'/stdout-*'));
    }
    public function testExcessiveProcessOutputIsRejected(): void
    {
        $this->settings->tesseractPath=PHP_BINARY;
        $this->expectException(RuntimeException::class);
        (new LocalOcrProcess($this->settings))->run('tesseract',['-r','echo str_repeat("x",4000001);'],$this->directory,microtime(true)+10);
    }
}
