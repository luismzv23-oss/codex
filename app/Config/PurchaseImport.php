<?php
namespace Config;
use CodeIgniter\Config\BaseConfig;
class PurchaseImport extends BaseConfig
{
    public int $maxBytes=10485760;
    public int $maxPages=20;
    public string $storageRoot=WRITEPATH.'private/purchase_documents';
    public string $provider='local';
    public string $tesseractPath='';
    public string $pdftoppmPath='';
    public string $pdftotextPath='';
    public string $tessdataPath='';
    public string $ocrLanguage='spa+eng';
    public int $processTimeout=25;
    public int $documentTimeout=120;
    public string $azureEndpoint='';
    public string $azureKey='';
    public string $azureApiVersion='2024-11-30';
    public string $azureTier='S0';
    public int $maxAttempts=3;
    public int $leaseSeconds=180;
    public function __construct()
    {
        parent::__construct();
        $this->provider=(string)env('PURCHASE_OCR_PROVIDER','local');
        $this->tesseractPath=(string)env('PURCHASE_OCR_TESSERACT_PATH',PHP_OS_FAMILY==='Windows'?WRITEPATH.'tools/tesseract/tesseract.exe':'/usr/bin/tesseract');
        $this->pdftoppmPath=(string)env('PURCHASE_OCR_PDFTOPPM_PATH',PHP_OS_FAMILY==='Windows'?WRITEPATH.'tools/poppler/poppler-26.09.0/Library/bin/pdftoppm.exe':'/usr/bin/pdftoppm');
        $this->pdftotextPath=(string)env('PURCHASE_OCR_PDFTOTEXT_PATH',dirname($this->pdftoppmPath).(PHP_OS_FAMILY==='Windows'?'/pdftotext.exe':'/pdftotext'));
        $this->tessdataPath=(string)env('PURCHASE_OCR_TESSDATA_PATH',PHP_OS_FAMILY==='Windows'?dirname($this->tesseractPath).'/tessdata':'/usr/share/tesseract-ocr/5/tessdata');
        $this->ocrLanguage=(string)env('PURCHASE_OCR_LANGUAGE','spa+eng');
        $this->azureEndpoint=rtrim((string)env('PURCHASE_OCR_AZURE_ENDPOINT',''),'/');
        $this->azureKey=(string)env('PURCHASE_OCR_AZURE_KEY','');
        $this->azureTier=(string)env('PURCHASE_OCR_AZURE_TIER','S0');
    }
}
