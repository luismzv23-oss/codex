<?php
namespace App\Libraries\PurchaseImport;
use RuntimeException;

/** No document URLs or credentials are exposed to browsers or accepted from request input. */
class AzureInvoiceExtractor implements InvoiceExtractor
{
    private $config;
    private $http;
    public function __construct(?\Config\PurchaseImport $config=null,$http=null)
    {
        $this->config=$config ?? config('PurchaseImport');$this->http=$http ?? \Config\Services::curlrequest();
    }
    public function configured(): bool {return $this->config->azureEndpoint!=='' && $this->config->azureKey!=='';}
    private function validateUrl(string $url): void
    {
        $base=parse_url($this->config->azureEndpoint);$parsed=parse_url($url);
        if (!$this->configured() || ($base['scheme'] ?? '')!=='https' || !preg_match('/\.(cognitiveservices\.azure\.com|api\.cognitive\.microsoft\.com|services\.ai\.azure\.com)$/D',$base['host'] ?? '')
            || ($parsed['scheme'] ?? '')!=='https' || ($parsed['host'] ?? '')!==$base['host'] || isset($parsed['user']) || isset($parsed['pass']) || isset($parsed['port']) || !str_starts_with($parsed['path'] ?? '','/documentintelligence/')) throw new RuntimeException('La configuración del servicio de lectura no es válida.');
    }
    private function request(string $method,string $url,array $extra=[])
    {
        $this->validateUrl($url);
        try {
            $response=$this->http->request($method,$url,$extra+[
                'headers'=>['Ocp-Apim-Subscription-Key'=>$this->config->azureKey],
                'timeout'=>35,'connect_timeout'=>10,'allow_redirects'=>false,'http_errors'=>false,
            ]);
        } catch (\Throwable $e) {throw new RuntimeException('No se pudo conectar al servicio de lectura. El borrador sigue disponible.');}
        $status=$response->getStatusCode();
        if ($status===429 || $status>=500) throw new RuntimeException('El servicio está ocupado. Se reintentará la lectura.');
        if ($status<200 || $status>=300) throw new RuntimeException('El servicio rechazó la lectura. Revisa su configuración y el formato del documento.');
        return $response;
    }
    public function start(string $path,array $document): string
    {
        if ($this->config->azureTier==='F0' && ((int)$document['page_count']>2 || (int)$document['byte_size']>4194304)) throw new RuntimeException('El nivel F0 admite hasta 2 páginas y 4 MB. Utiliza S0 para este documento o completa el borrador manualmente.');
        $url=$this->config->azureEndpoint.'/documentintelligence/documentModels/prebuilt-invoice:analyze?api-version='.rawurlencode($this->config->azureApiVersion).'&pages=1-'.(int)$document['page_count'];
        $response=$this->request('POST',$url,['headers'=>['Ocp-Apim-Subscription-Key'=>$this->config->azureKey,'Content-Type'=>'application/octet-stream'],'body'=>file_get_contents($path)]);
        $operation=$response->getHeaderLine('Operation-Location');
        $this->validateUrl($operation);
        return $operation;
    }
    public function poll(string $operationUrl): ?array
    {
        $response=$this->request('GET',$operationUrl);
        $body=json_decode((string)$response->getBody(),true,512,JSON_THROW_ON_ERROR);
        if (in_array($body['status'] ?? '',['notStarted','running'],true)) return null;
        if (($body['status'] ?? '')!=='succeeded' || !isset($body['analyzeResult'])) throw new RuntimeException('No se pudo leer el documento. Puedes corregir el borrador manualmente o reintentar.');
        return $body['analyzeResult'];
    }
}
