<?php
namespace App\Libraries\PurchaseImport;
interface InvoiceExtractor
{
    public function configured(): bool;
    public function start(string $path,array $document): string;
    /** Returns null while processing; otherwise an AnalyzeResult array. */
    public function poll(string $operationUrl): ?array;
}
