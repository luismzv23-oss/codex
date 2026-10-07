<?php
namespace App\Libraries;
use App\Models\PurchaseInvoiceModel;
use App\Models\PurchaseInvoiceItemModel;
use App\Models\SupplierCostHistoryModel;

/** Called within the existing company transaction for manual and imported invoices. */
class PurchaseInvoiceRegistration
{
    public function totals(array $rows): array
    {
        $subtotal=0; $tax=0; $total=0;
        foreach($rows as $row) { $subtotal += (float)$row['line_total']-(float)$row['tax_amount']; $tax += $row['tax_amount']; $total += $row['line_total']; }
        return ['subtotal'=>round($subtotal,2),'tax_total'=>round($tax,2),'total'=>round($total,2)];
    }
    public function register(string $companyId, string $userId, array $data, array $rows): string
    {
        $supplierId=(string)$data['supplier_id']; $invoiceNumber=trim((string)$data['invoice_number']);
        $purchaseReceiptId=trim((string)($data['purchase_receipt_id']??'')) ?: null;
        $currencyCode=trim((string)($data['currency_code']??'')) ?: 'ARS'; $exchangeRate=(float)(($data['exchange_rate']??1) ?: 1);
        (new PurchaseIntegrityService())->validateInvoice($companyId,$supplierId,$purchaseReceiptId,$currencyCode,$rows);
        $totals = $this->totals($rows);
        $invoiceId = (new PurchaseInvoiceModel())->insert([
            'company_id' => $companyId,
            'supplier_id' => $supplierId,
            'purchase_receipt_id' => $purchaseReceiptId,
            'invoice_number' => $invoiceNumber,
            'currency_code' => $currencyCode,
            'exchange_rate' => $exchangeRate,
            'subtotal' => $totals['subtotal'],
            'tax_total' => $totals['tax_total'],
            'total' => $totals['total'],
            'issue_date' => trim((string) ($data['issue_date'] ?? '')) ?: date('Y-m-d H:i:s'),
            'due_date' => trim((string) ($data['due_date'] ?? '')) ?: null,
            'status' => 'registered',
            'notes' => trim((string) ($data['notes'] ?? '')),
            'created_by' => $userId,
        ], true);

        if (!$invoiceId) throw new \RuntimeException('No se pudo registrar la factura.');
        $itemModel = new PurchaseInvoiceItemModel();
        foreach ($rows as $row) {
            if (!$itemModel->insert(array_merge($row, ['purchase_invoice_id' => $invoiceId]))) throw new \RuntimeException('No se pudo guardar un renglon de la factura.');
            if (! empty($row['product_id'])) {
                $costId=(new SupplierCostHistoryModel())->insert([
                    'company_id' => $companyId,
                    'supplier_id' => $supplierId,
                    'product_id' => $row['product_id'],
                    'purchase_invoice_id' => $invoiceId,
                    'currency_code' => $currencyCode,
                    'exchange_rate' => $exchangeRate,
                    'unit_cost' => (float) $row['unit_cost'],
                    'observed_at' => trim((string) ($data['issue_date'] ?? '')) ?: date('Y-m-d H:i:s'),
                ]);
                if (!$costId) throw new \RuntimeException('No se pudo registrar el costo del proveedor.');
            }
        }

        (new PurchaseIntegrityService())->syncInvoice($companyId, (string) $invoiceId);

        if (!$invoiceId) throw new \RuntimeException('No se pudo registrar la factura.');
        return (string)$invoiceId;
    }
}
