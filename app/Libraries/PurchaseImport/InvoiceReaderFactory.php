<?php
namespace App\Libraries\PurchaseImport;
use RuntimeException;
class InvoiceReaderFactory
{
    public static function make(?string $provider=null): InvoiceExtractor|LocalInvoiceExtractor
    {
        $provider=$provider ?? config('PurchaseImport')->provider;
        if ($provider==='local') return new LocalInvoiceExtractor();
        if ($provider==='azure') return new AzureInvoiceExtractor();
        throw new RuntimeException('El motor de lectura configurado no está soportado.');
    }
}
