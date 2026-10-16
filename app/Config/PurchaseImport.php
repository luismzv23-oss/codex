<?php
namespace Config;
use CodeIgniter\Config\BaseConfig;
class PurchaseImport extends BaseConfig
{
    public int $maxBytes=10485760;
    public int $maxPages=20;
    public string $storageRoot=WRITEPATH.'private/purchase_documents';
    public string $azureEndpoint='';
    public string $azureKey='';
    public string $azureApiVersion='2024-11-30';
    public string $azureTier='S0';
    public int $maxAttempts=3;
    public int $leaseSeconds=180;
    public function __construct()
    {
        parent::__construct();
        $this->azureEndpoint=rtrim((string)env('PURCHASE_OCR_AZURE_ENDPOINT',''),'/');
        $this->azureKey=(string)env('PURCHASE_OCR_AZURE_KEY','');
        $this->azureTier=(string)env('PURCHASE_OCR_AZURE_TIER','S0');
    }
}
