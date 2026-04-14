<?php

namespace App\Libraries;

use App\Models\AccountingAccountModel;
use App\Models\AccountingEntryItemModel;
use App\Models\AccountingEntryModel;
use App\Models\BranchModel;
use App\Models\CashClosureModel;
use App\Models\CustomerModel;
use App\Models\PurchasePaymentModel;
use App\Models\PurchaseReceiptModel;
use App\Models\PurchaseReturnModel;
use App\Models\SaleItemModel;
use App\Models\SaleModel;
use App\Models\SaleReturnItemModel;
use App\Models\SaleReturnModel;
use App\Models\SalesDocumentTypeModel;
use App\Models\SalesReceiptModel;
use App\Models\SupplierModel;
use App\Models\VatPurchaseBookModel;
use App\Models\VatSalesBookModel;
use App\Models\VoucherSequenceModel;

class AccountingService
{
    private const DEFAULT_ACCOUNTS = [
        ['code' => '1.1.01', 'name' => 'Caja y bancos', 'category' => 'asset', 'nature' => 'debit', 'system_key' => 'cash'],
        ['code' => '1.1.02', 'name' => 'Creditos por ventas', 'category' => 'asset', 'nature' => 'debit', 'system_key' => 'accounts_receivable'],
        ['code' => '1.1.03', 'name' => 'IVA credito fiscal', 'category' => 'asset', 'nature' => 'debit', 'system_key' => 'vat_credit'],
        ['code' => '1.1.04', 'name' => 'Inventario de mercaderias', 'category' => 'asset', 'nature' => 'debit', 'system_key' => 'inventory'],
        ['code' => '2.1.01', 'name' => 'Proveedores', 'category' => 'liability', 'nature' => 'credit', 'system_key' => 'accounts_payable'],
        ['code' => '2.1.02', 'name' => 'IVA debito fiscal', 'category' => 'liability', 'nature' => 'credit', 'system_key' => 'vat_debit'],
        ['code' => '4.1.01', 'name' => 'Ventas', 'category' => 'revenue', 'nature' => 'credit', 'system_key' => 'sales_revenue'],
        ['code' => '4.1.02', 'name' => 'Devoluciones sobre compras', 'category' => 'revenue', 'nature' => 'credit', 'system_key' => 'purchase_returns'],
        ['code' => '4.1.03', 'name' => 'Sobrantes de caja', 'category' => 'revenue', 'nature' => 'credit', 'system_key' => 'cash_overage'],
        ['code' => '5.1.01', 'name' => 'Costo de ventas', 'category' => 'expense', 'nature' => 'debit', 'system_key' => 'cost_of_goods_sold'],
        ['code' => '5.1.02', 'name' => 'Devoluciones sobre ventas', 'category' => 'expense', 'nature' => 'debit', 'system_key' => 'sales_returns'],
        ['code' => '5.1.03', 'name' => 'Faltantes de caja', 'category' => 'expense', 'nature' => 'debit', 'system_key' => 'cash_shortage'],
    ];

    public function ensureDefaults(string $companyId): void
    {
        $accountModel = new AccountingAccountModel();
        foreach (self::DEFAULT_ACCOUNTS as $row) {
            if (! $accountModel->where('company_id', $companyId)->where('system_key', $row['system_key'])->first()) {
                $accountModel->insert(array_merge($row, ['company_id' => $companyId, 'active' => 1]));
            }
        }

        $branch = (new BranchModel())->where('company_id', $companyId)->where('code', 'MAIN')->first();
        $sequenceModel = new VoucherSequenceModel();
        if (! $sequenceModel->where('company_id', $companyId)->where('document_type', 'ASIENTO')->first()) {
            $sequenceModel->insert([
                'company_id' => $companyId,
                'branch_id' => $branch['id'] ?? null,
                'document_type' => 'ASIENTO',
                'prefix' => 'AST',
                'current_number' => 1,
                'active' => 1,
            ]);
        }
    }

    public function syncSale(string $companyId, string $saleId, ?string $userId = null): void
    {
        $this->ensureDefaults($companyId);
        $sale = (new SaleModel())->find($saleId);
        if (! $sale) {
            $this->removeSource('sale', $saleId);
            return;
        }

        $documentType = ! empty($sale['document_type_id']) ? (new SalesDocumentTypeModel())->find($sale['document_type_id']) : null;
        $category = (string) ($documentType['category'] ?? '');
        $accountable = in_array($category, ['invoice', 'ticket', 'debit_note'], true) && in_array(($sale['status'] ?? ''), ['confirmed', 'returned_partial', 'returned_total'], true);

        if (! $accountable) {
            $this->removeSource('sale', $saleId);
            if (in_array(($sale['status'] ?? ''), ['cancelled', 'draft'], true)) {
                $this->removeVatSalesBook('sale', $saleId);
            }
            return;
        }

        $total = round((float) ($sale['total'] ?? 0), 2);
        $tax = round((float) ($sale['tax_total'] ?? 0), 2);
        $net = max(0.0, round($total - $tax, 2));
        $paid = min($total, round((float) ($sale['paid_total'] ?? 0), 2));
        $balance = max(0.0, round($total - $paid, 2));
        $costTotal = 0.0;
        foreach ((new SaleItemModel())->where('sale_id', $saleId)->findAll() as $item) {
            $costTotal += ((float) ($item['unit_cost'] ?? 0)) * ((float) ($item['quantity'] ?? 0));
        }
        $costTotal = round($costTotal, 2);

        $lines = [];
        if ($paid > 0) {
            $lines[] = $this->line($companyId, 'cash', 'Cobro registrado en venta', $paid, 0, 'sale', $saleId);
        }
        if ($balance > 0) {
            $lines[] = $this->line($companyId, 'accounts_receivable', 'Saldo pendiente de cobro', $balance, 0, 'sale', $saleId);
        }
        if ($net > 0) {
            $lines[] = $this->line($companyId, 'sales_revenue', 'Ventas netas', 0, $net, 'sale', $saleId);
        }
        if ($tax > 0) {
            $lines[] = $this->line($companyId, 'vat_debit', 'IVA debito fiscal', 0, $tax, 'sale', $saleId);
        }
        if ($costTotal > 0 && (int) ($documentType['impacts_stock'] ?? 0) === 1) {
            $lines[] = $this->line($companyId, 'cost_of_goods_sold', 'Costo de ventas', $costTotal, 0, 'sale', $saleId);
            $lines[] = $this->line($companyId, 'inventory', 'Salida de inventario', 0, $costTotal, 'sale', $saleId);
        }

        if ($lines === []) {
            $this->removeSource('sale', $saleId);
            return;
        }

        $this->replaceEntry($companyId, 'sale', $saleId, [
            'entry_date' => $sale['confirmed_at'] ?? $sale['issue_date'] ?? date('Y-m-d H:i:s'),
            'source_number' => $sale['sale_number'] ?? null,
            'description' => 'Asiento de venta ' . ($sale['sale_number'] ?? $saleId),
            'posted_by' => $userId ?? ($sale['confirmed_by'] ?? $sale['created_by'] ?? null),
        ], $lines);

        $customer = ! empty($sale['customer_id']) ? (new CustomerModel())->find($sale['customer_id']) : null;
        $this->replaceVatSalesBook('sale', $saleId, [
            'company_id' => $companyId,
            'source_type' => 'sale',
            'source_id' => $saleId,
            'sale_id' => $saleId,
            'document_type_id' => $sale['document_type_id'] ?? null,
            'document_number' => $sale['sale_number'] ?? '',
            'issue_date' => $sale['issue_date'] ?? date('Y-m-d H:i:s'),
            'customer_name' => (string) ($sale['customer_name_snapshot'] ?? ($customer['name'] ?? 'Consumidor Final')),
            'customer_document' => $sale['customer_document_snapshot'] ?? ($customer['document_number'] ?? null),
            'customer_tax_profile' => $sale['customer_tax_profile'] ?? ($customer['tax_profile'] ?? null),
            'net_amount' => $net,
            'tax_amount' => $tax,
            'total_amount' => $total,
            'currency_code' => $sale['currency_code'] ?? 'ARS',
            'status' => (string) ($sale['status'] ?? 'confirmed'),
        ]);
    }

    public function syncSaleReturn(string $companyId, string $returnId, ?string $userId = null): void
    {
        $this->ensureDefaults($companyId);
        $return = (new SaleReturnModel())->find($returnId);
        if (! $return || ($return['status'] ?? '') !== 'confirmed') {
            $this->removeSource('sale_return', $returnId);
            $this->removeVatSalesBook('sale_return', $returnId);
            return;
        }

        $sale = (new SaleModel())->find((string) $return['sale_id']);
        if (! $sale) {
            return;
        }

        $total = round((float) ($return['total'] ?? 0), 2);
        $saleTotal = max(0.01, (float) ($sale['total'] ?? 0));
        $tax = round($total * (((float) ($sale['tax_total'] ?? 0)) / $saleTotal), 2);
        $net = max(0.0, round($total - $tax, 2));
        $costTotal = 0.0;
        foreach ((new SaleReturnItemModel())->where('sale_return_id', $returnId)->findAll() as $item) {
            $saleItem = (new SaleItemModel())->find($item['sale_item_id']);
            $costTotal += ((float) ($saleItem['unit_cost'] ?? 0)) * ((float) ($item['quantity'] ?? 0));
        }
        $costTotal = round($costTotal, 2);

        $lines = [];
        if ($net > 0) {
            $lines[] = $this->line($companyId, 'sales_returns', 'Nota de credito / devolucion de venta', $net, 0, 'sale_return', $returnId);
        }
        if ($tax > 0) {
            $lines[] = $this->line($companyId, 'vat_debit', 'Reversion IVA debito fiscal', $tax, 0, 'sale_return', $returnId);
        }
        if ($total > 0) {
            $lines[] = $this->line($companyId, 'accounts_receivable', 'Credito al cliente', 0, $total, 'sale_return', $returnId);
        }
        if ($costTotal > 0) {
            $lines[] = $this->line($companyId, 'inventory', 'Reingreso por devolucion', $costTotal, 0, 'sale_return', $returnId);
            $lines[] = $this->line($companyId, 'cost_of_goods_sold', 'Reversion costo de ventas', 0, $costTotal, 'sale_return', $returnId);
        }

        if ($lines !== []) {
            $this->replaceEntry($companyId, 'sale_return', $returnId, [
                'entry_date' => $return['created_at'] ?? date('Y-m-d H:i:s'),
                'source_number' => $return['return_number'] ?? $return['credit_note_number'] ?? null,
                'description' => 'Asiento de devolucion de venta ' . ($return['return_number'] ?? $returnId),
                'posted_by' => $userId ?? ($return['created_by'] ?? null),
            ], $lines);
        }

        $customer = ! empty($sale['customer_id']) ? (new CustomerModel())->find($sale['customer_id']) : null;
        $this->replaceVatSalesBook('sale_return', $returnId, [
            'company_id' => $companyId,
            'source_type' => 'sale_return',
            'source_id' => $returnId,
            'sale_id' => $sale['id'],
            'document_type_id' => $sale['document_type_id'] ?? null,
            'document_number' => $return['credit_note_number'] ?? $return['return_number'] ?? '',
            'issue_date' => $return['created_at'] ?? date('Y-m-d H:i:s'),
            'customer_name' => (string) ($sale['customer_name_snapshot'] ?? ($customer['name'] ?? 'Consumidor Final')),
            'customer_document' => $sale['customer_document_snapshot'] ?? ($customer['document_number'] ?? null),
            'customer_tax_profile' => $sale['customer_tax_profile'] ?? ($customer['tax_profile'] ?? null),
            'net_amount' => -1 * $net,
            'tax_amount' => -1 * $tax,
            'total_amount' => -1 * $total,
            'currency_code' => $sale['currency_code'] ?? 'ARS',
            'status' => 'credit_note',
        ]);
    }

    public function syncPurchaseReceipt(string $companyId, string $receiptId, ?string $userId = null): void
    {
        $this->ensureDefaults($companyId);
        $receipt = (new PurchaseReceiptModel())->find($receiptId);
        if (! $receipt) {
            $this->removeSource('purchase_receipt', $receiptId);
            $this->removeVatPurchaseBook('purchase_receipt', $receiptId);
            return;
        }

        $total = round((float) ($receipt['total'] ?? 0), 2);
        $tax = round((float) ($receipt['tax_total'] ?? 0), 2);
        $net = max(0.0, round($total - $tax, 2));

        $lines = [];
        if ($net > 0) {
            $lines[] = $this->line($companyId, 'inventory', 'Ingreso de inventario por compra', $net, 0, 'purchase_receipt', $receiptId);
        }
        if ($tax > 0) {
            $lines[] = $this->line($companyId, 'vat_credit', 'IVA credito fiscal', $tax, 0, 'purchase_receipt', $receiptId);
        }
        if ($total > 0) {
            $lines[] = $this->line($companyId, 'accounts_payable', 'Cuenta a pagar proveedor', 0, $total, 'purchase_receipt', $receiptId);
        }

        if ($lines !== []) {
            $this->replaceEntry($companyId, 'purchase_receipt', $receiptId, [
                'entry_date' => $receipt['received_at'] ?? $receipt['issued_at'] ?? date('Y-m-d H:i:s'),
                'source_number' => $receipt['receipt_number'] ?? null,
                'description' => 'Asiento de compra ' . ($receipt['receipt_number'] ?? $receiptId),
                'posted_by' => $userId ?? ($receipt['created_by'] ?? null),
            ], $lines);
        }

        $supplier = (new SupplierModel())->find((string) ($receipt['supplier_id'] ?? ''));
        $this->replaceVatPurchaseBook('purchase_receipt', $receiptId, [
            'company_id' => $companyId,
            'source_type' => 'purchase_receipt',
            'source_id' => $receiptId,
            'purchase_receipt_id' => $receiptId,
            'supplier_id' => $receipt['supplier_id'] ?? null,
            'document_number' => $receipt['receipt_number'] ?? '',
            'supplier_document' => $receipt['supplier_document'] ?? null,
            'issue_date' => $receipt['issued_at'] ?? date('Y-m-d H:i:s'),
            'supplier_name' => $supplier['name'] ?? 'Proveedor',
            'supplier_tax_id' => $supplier['tax_id'] ?? null,
            'supplier_vat_condition' => $supplier['vat_condition'] ?? null,
            'net_amount' => $net,
            'tax_amount' => $tax,
            'total_amount' => $total,
            'currency_code' => $receipt['currency_code'] ?? 'ARS',
            'status' => (string) ($receipt['status'] ?? 'registered'),
        ]);
    }

    public function syncPurchaseReturn(string $companyId, string $returnId, ?string $userId = null): void
    {
        $this->ensureDefaults($companyId);
        $return = (new PurchaseReturnModel())->find($returnId);
        if (! $return) {
            $this->removeSource('purchase_return', $returnId);
            $this->removeVatPurchaseBook('purchase_return', $returnId);
            return;
        }

        $total = round((float) ($return['total'] ?? 0), 2);
        $tax = round((float) ($return['tax_total'] ?? 0), 2);
        $net = max(0.0, round($total - $tax, 2));

        $lines = [];
        if ($total > 0) {
            $lines[] = $this->line($companyId, 'accounts_payable', 'Reversion cuenta a pagar por devolucion', $total, 0, 'purchase_return', $returnId);
        }
        if ($net > 0) {
            $lines[] = $this->line($companyId, 'purchase_returns', 'Devolucion a proveedor', 0, $net, 'purchase_return', $returnId);
        }
        if ($tax > 0) {
            $lines[] = $this->line($companyId, 'vat_credit', 'Reversion IVA credito fiscal', 0, $tax, 'purchase_return', $returnId);
        }

        if ($lines !== []) {
            $this->replaceEntry($companyId, 'purchase_return', $returnId, [
                'entry_date' => $return['issued_at'] ?? date('Y-m-d H:i:s'),
                'source_number' => $return['return_number'] ?? null,
                'description' => 'Asiento de devolucion a proveedor ' . ($return['return_number'] ?? $returnId),
                'posted_by' => $userId ?? ($return['created_by'] ?? null),
            ], $lines);
        }

        $supplier = (new SupplierModel())->find((string) ($return['supplier_id'] ?? ''));
        $receipt = ! empty($return['purchase_receipt_id']) ? (new PurchaseReceiptModel())->find($return['purchase_receipt_id']) : null;
        $this->replaceVatPurchaseBook('purchase_return', $returnId, [
            'company_id' => $companyId,
            'source_type' => 'purchase_return',
            'source_id' => $returnId,
            'purchase_receipt_id' => $return['purchase_receipt_id'] ?? null,
            'supplier_id' => $return['supplier_id'] ?? null,
            'document_number' => $return['return_number'] ?? '',
            'supplier_document' => null,
            'issue_date' => $return['issued_at'] ?? date('Y-m-d H:i:s'),
            'supplier_name' => $supplier['name'] ?? 'Proveedor',
            'supplier_tax_id' => $supplier['tax_id'] ?? null,
            'supplier_vat_condition' => $supplier['vat_condition'] ?? null,
            'net_amount' => -1 * $net,
            'tax_amount' => -1 * $tax,
            'total_amount' => -1 * $total,
            'currency_code' => $receipt['currency_code'] ?? 'ARS',
            'status' => (string) ($return['status'] ?? 'issued'),
        ]);
    }

    public function syncSalesReceipt(string $companyId, string $receiptId, ?string $userId = null): void
    {
        $this->ensureDefaults($companyId);
        $receipt = (new SalesReceiptModel())->find($receiptId);
        if (! $receipt) {
            $this->removeSource('sales_receipt', $receiptId);
            return;
        }

        $total = round((float) ($receipt['total_amount'] ?? 0), 2);
        if ($total <= 0) {
            $this->removeSource('sales_receipt', $receiptId);
            return;
        }

        $lines = [
            $this->line($companyId, 'cash', 'Ingreso de cobranza', $total, 0, 'sales_receipt', $receiptId),
            $this->line($companyId, 'accounts_receivable', 'Aplicacion sobre cuentas a cobrar', 0, $total, 'sales_receipt', $receiptId),
        ];

        $this->replaceEntry($companyId, 'sales_receipt', $receiptId, [
            'entry_date' => $receipt['issue_date'] ?? date('Y-m-d H:i:s'),
            'source_number' => $receipt['receipt_number'] ?? null,
            'description' => 'Asiento de cobranza ' . ($receipt['receipt_number'] ?? $receiptId),
            'posted_by' => $userId ?? ($receipt['created_by'] ?? null),
        ], $lines);
    }

    public function syncPurchasePayment(string $companyId, string $paymentId, ?string $userId = null): void
    {
        $this->ensureDefaults($companyId);
        $payment = (new PurchasePaymentModel())->find($paymentId);
        if (! $payment) {
            $this->removeSource('purchase_payment', $paymentId);
            return;
        }

        $amount = round((float) ($payment['amount'] ?? 0), 2);
        if ($amount <= 0) {
            $this->removeSource('purchase_payment', $paymentId);
            return;
        }

        $lines = [
            $this->line($companyId, 'accounts_payable', 'Cancelacion de cuenta a pagar', $amount, 0, 'purchase_payment', $paymentId),
            $this->line($companyId, 'cash', 'Egreso de caja por pago a proveedor', 0, $amount, 'purchase_payment', $paymentId),
        ];

        $this->replaceEntry($companyId, 'purchase_payment', $paymentId, [
            'entry_date' => $payment['paid_at'] ?? date('Y-m-d H:i:s'),
            'source_number' => $payment['payment_number'] ?? null,
            'description' => 'Asiento de pago a proveedor ' . ($payment['payment_number'] ?? $paymentId),
            'posted_by' => $userId ?? ($payment['created_by'] ?? null),
        ], $lines);
    }

    public function syncCashClosure(string $companyId, string $closureId, ?string $userId = null): void
    {
        $this->ensureDefaults($companyId);
        $closure = (new CashClosureModel())->find($closureId);
        if (! $closure) {
            $this->removeSource('cash_closure', $closureId);
            return;
        }

        $difference = round((float) ($closure['difference_amount'] ?? 0), 2);
        if (abs($difference) < 0.01) {
            $this->removeSource('cash_closure', $closureId);
            return;
        }

        if ($difference > 0) {
            $lines = [
                $this->line($companyId, 'cash', 'Sobrante de caja', $difference, 0, 'cash_closure', $closureId),
                $this->line($companyId, 'cash_overage', 'Sobrante detectado en arqueo', 0, $difference, 'cash_closure', $closureId),
            ];
        } else {
            $amount = abs($difference);
            $lines = [
                $this->line($companyId, 'cash_shortage', 'Faltante de caja', $amount, 0, 'cash_closure', $closureId),
                $this->line($companyId, 'cash', 'Faltante detectado en arqueo', 0, $amount, 'cash_closure', $closureId),
            ];
        }

        $this->replaceEntry($companyId, 'cash_closure', $closureId, [
            'entry_date' => $closure['closed_at'] ?? date('Y-m-d H:i:s'),
            'source_number' => $closure['id'],
            'description' => 'Asiento por cierre de caja',
            'posted_by' => $userId ?? ($closure['closed_by'] ?? null),
        ], $lines);
    }

    private function replaceEntry(string $companyId, string $sourceType, string $sourceId, array $meta, array $lines): void
    {
        $entryModel = new AccountingEntryModel();
        $itemModel = new AccountingEntryItemModel();
        $existing = $entryModel->where('company_id', $companyId)->where('source_type', $sourceType)->where('source_id', $sourceId)->first();

        $entryNumber = $existing['entry_number'] ?? $this->nextEntryNumber($companyId);
        if ($existing) {
            $itemModel->where('accounting_entry_id', $existing['id'])->delete();
            $entryModel->delete($existing['id']);
        }

        $totals = $this->totals($lines);
        if (round($totals['debit'], 2) !== round($totals['credit'], 2)) {
            throw new \RuntimeException('El asiento contable no esta balanceado para ' . $sourceType . ':' . $sourceId);
        }

        $entryId = $entryModel->insert([
            'company_id' => $companyId,
            'entry_number' => $entryNumber,
            'entry_date' => $meta['entry_date'] ?? date('Y-m-d H:i:s'),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'source_number' => $meta['source_number'] ?? null,
            'description' => $meta['description'] ?? $sourceType,
            'status' => 'posted',
            'total_debit' => $totals['debit'],
            'total_credit' => $totals['credit'],
            'posted_by' => $meta['posted_by'] ?? null,
            'posted_at' => date('Y-m-d H:i:s'),
        ], true);

        foreach (array_values($lines) as $index => $line) {
            $itemModel->insert([
                'accounting_entry_id' => $entryId,
                'account_id' => $line['account_id'],
                'line_number' => $index + 1,
                'description' => $line['description'],
                'debit' => $line['debit'],
                'credit' => $line['credit'],
                'reference_type' => $line['reference_type'] ?? null,
                'reference_id' => $line['reference_id'] ?? null,
            ]);
        }
    }

    private function removeSource(string $sourceType, string $sourceId): void
    {
        $entryModel = new AccountingEntryModel();
        $itemModel = new AccountingEntryItemModel();
        $existing = $entryModel->where('source_type', $sourceType)->where('source_id', $sourceId)->first();
        if (! $existing) {
            return;
        }

        $itemModel->where('accounting_entry_id', $existing['id'])->delete();
        $entryModel->delete($existing['id']);
    }

    private function replaceVatSalesBook(string $sourceType, string $sourceId, array $payload): void
    {
        $model = new VatSalesBookModel();
        $existing = $model->where('company_id', $payload['company_id'])->where('source_type', $sourceType)->where('source_id', $sourceId)->first();
        if ($existing) {
            $model->update($existing['id'], $payload);
            return;
        }

        $model->insert($payload);
    }

    private function replaceVatPurchaseBook(string $sourceType, string $sourceId, array $payload): void
    {
        $model = new VatPurchaseBookModel();
        $existing = $model->where('company_id', $payload['company_id'])->where('source_type', $sourceType)->where('source_id', $sourceId)->first();
        if ($existing) {
            $model->update($existing['id'], $payload);
            return;
        }

        $model->insert($payload);
    }

    private function removeVatSalesBook(string $sourceType, string $sourceId): void
    {
        (new VatSalesBookModel())->where('source_type', $sourceType)->where('source_id', $sourceId)->delete();
    }

    private function removeVatPurchaseBook(string $sourceType, string $sourceId): void
    {
        (new VatPurchaseBookModel())->where('source_type', $sourceType)->where('source_id', $sourceId)->delete();
    }

    private function nextEntryNumber(string $companyId): string
    {
        $model = new VoucherSequenceModel();
        $sequence = $model->where('company_id', $companyId)->where('document_type', 'ASIENTO')->first();
        if (! $sequence) {
            $this->ensureDefaults($companyId);
            $sequence = $model->where('company_id', $companyId)->where('document_type', 'ASIENTO')->first();
        }

        $number = (int) ($sequence['current_number'] ?? 1);
        $formatted = strtoupper(trim((string) ($sequence['prefix'] ?? 'AST'))) . '-' . str_pad((string) $number, 8, '0', STR_PAD_LEFT);
        $model->update($sequence['id'], ['current_number' => $number + 1]);

        return $formatted;
    }

    private function line(string $companyId, string $systemKey, string $description, float $debit, float $credit, ?string $referenceType = null, ?string $referenceId = null): array
    {
        $account = (new AccountingAccountModel())->where('company_id', $companyId)->where('system_key', $systemKey)->first();
        if (! $account) {
            throw new \RuntimeException('Cuenta contable no disponible para system_key=' . $systemKey);
        }

        return [
            'account_id' => $account['id'],
            'description' => $description,
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ];
    }

    private function totals(array $lines): array
    {
        return [
            'debit' => round(array_sum(array_map(static fn(array $line): float => (float) ($line['debit'] ?? 0), $lines)), 2),
            'credit' => round(array_sum(array_map(static fn(array $line): float => (float) ($line['credit'] ?? 0), $lines)), 2),
        ];
    }
}
