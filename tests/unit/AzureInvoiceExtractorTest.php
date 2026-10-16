<?php
use App\Libraries\PurchaseImport\AzureInvoiceExtractor;
use CodeIgniter\Test\CIUnitTestCase;

final class AzureInvoiceExtractorTest extends CIUnitTestCase
{
    private function configuration(): \Config\PurchaseImport
    {
        $config=new \Config\PurchaseImport();$config->azureEndpoint='https://fixture.cognitiveservices.azure.com';$config->azureKey='test-key-not-a-real-secret';$config->azureTier='S0';return $config;
    }
    public function testAzureAdapterUsesPrivateBytesAndResumesOperationWithoutNewUpload(): void
    {
        $config=$this->configuration();
        $http=new class {
            public array $requests=[];
            public function request($method,$url,$options) {
                $this->requests[]=[$method,$url,$options];
                $response=new \CodeIgniter\HTTP\Response(config('App'));
                if($method==='POST') return $response->setStatusCode(202)->setHeader('Operation-Location','https://fixture.cognitiveservices.azure.com/documentintelligence/documentModels/prebuilt-invoice/analyzeResults/123');
                return $response->setStatusCode(200)->setJSON(['status'=>'succeeded','analyzeResult'=>['documents'=>[]]]);
            }
        };
        $path=ROOTPATH.'tmp/azure-fixture-'.bin2hex(random_bytes(5));file_put_contents($path,'document fixture');
        try {
            $adapter=new AzureInvoiceExtractor($config,$http);$url=$adapter->start($path,['page_count'=>3,'byte_size'=>16]);$result=$adapter->poll($url);
            $this->assertSame('document fixture',$http->requests[0][2]['body']);$this->assertStringContainsString('pages=1-3',$http->requests[0][1]);
            $this->assertSame('GET',$http->requests[1][0]);$this->assertArrayHasKey('documents',$result);
            $this->assertFalse($http->requests[0][2]['allow_redirects']);
        } finally {unlink($path);}
    }
    public function testOperationHostCannotRedirectCredentialsToAnotherServer(): void
    {
        $http=new class {public int $calls=0;public function request($method,$url,$options){$this->calls++;return (new \CodeIgniter\HTTP\Response(config('App')))->setStatusCode(202)->setHeader('Operation-Location','https://attacker.invalid/documentintelligence/result');}};
        $path=ROOTPATH.'tmp/azure-fixture-'.bin2hex(random_bytes(5));file_put_contents($path,'fixture');
        try {(new AzureInvoiceExtractor($this->configuration(),$http))->start($path,['page_count'=>1,'byte_size'=>7]);$this->fail('Must reject a foreign operation URL');}
        catch(RuntimeException $e){$this->assertSame(1,$http->calls);$this->assertStringNotContainsString('test-key',$e->getMessage());}finally{unlink($path);}
    }
    public function testFreeTierLimitIsCheckedBeforeSendingTheDocument(): void
    {
        $config=$this->configuration();$config->azureTier='F0';
        $http=new class {public function request($method,$url,$options){throw new LogicException('Must not call network');}};
        $this->expectException(RuntimeException::class);(new AzureInvoiceExtractor($config,$http))->start('not-read',['page_count'=>3,'byte_size'=>10]);
    }
    public function testBusyServiceDoesNotExposeResponseBodyOrApiKey(): void
    {
        $http=new class {public function request($method,$url,$options){return (new \CodeIgniter\HTTP\Response(config('App')))->setStatusCode(429)->setBody('sensitive diagnostic body');}};
        try {(new AzureInvoiceExtractor($this->configuration(),$http))->poll('https://fixture.cognitiveservices.azure.com/documentintelligence/result');$this->fail('Must signal retry');}
        catch(RuntimeException $e){$this->assertStringContainsString('reintentará',$e->getMessage());$this->assertStringNotContainsString('sensitive',$e->getMessage());}
    }
}
