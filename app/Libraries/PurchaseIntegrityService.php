<?php

namespace App\Libraries;

use App\Models\PurchasePayableModel;
use RuntimeException;

/** All mutations must run inside InventoryIntegrityService::transaction. */
class PurchaseIntegrityService
{
    public function owned(string $table, string $companyId, string $id, ?string $supplierId = null): array
    {
        $builder = db_connect()->table($table)->where('company_id', $companyId)->where('id', $id);
        if ($supplierId !== null) {
            $builder->where('supplier_id', $supplierId);
        }
        $row = $builder->get()->getRowArray();
        if (! $row || in_array($row['status'] ?? '', ['cancelled', 'void'], true)) {
            throw new RuntimeException('El documento no pertenece a la empresa/proveedor o esta anulado.');
        }
        return $row;
    }

    public function validateReceipt(string $companyId, array $order, array $rows): void
    {
        if (! in_array($order['status'], ['approved', 'received_partial'], true)) {
            throw new RuntimeException('Solo se pueden recibir ordenes aprobadas con cantidades pendientes.');
        }
        (new InventoryIntegrityService())->validatePlace($companyId, $order['warehouse_id'], null);
        $requested = [];
        foreach ($rows as $row) {
            $id = $row['purchase_order_item_id'];
            $requested[$id] = ($requested[$id] ?? 0) + $row['quantity'];
            $item = db_connect()->table('purchase_order_items')->where('id', $id)->where('purchase_order_id', $order['id'])->get()->getRowArray();
            if (! $item || $requested[$id] > (float) $item['quantity'] - (float) $item['received_quantity'] + 0.000001) {
                throw new RuntimeException('La cantidad acumulada supera el pendiente de la orden.');
            }
        }
    }

    public function validateReturn(string $companyId, array $receipt, array $rows): void
    {
        $this->owned('purchase_receipts', $companyId, $receipt['id']);
        $requested = [];
        foreach ($rows as $row) {
            $id = $row['purchase_receipt_item_id'];
            $requested[$id] = ($requested[$id] ?? 0) + $row['quantity'];
            $item = db_connect()->table('purchase_receipt_items')->where('id', $id)->where('purchase_receipt_id', $receipt['id'])->get()->getRowArray();
            $returned = db_connect()->table('purchase_return_items ri')->selectSum('ri.quantity', 'qty')
                ->join('purchase_returns r', 'r.id = ri.purchase_return_id')->where('r.company_id', $companyId)
                ->where('r.status !=', 'cancelled')->where('ri.purchase_receipt_item_id', $id)->get()->getRowArray();
            if (! $item || $requested[$id] + (float) ($returned['qty'] ?? 0) > (float) $item['quantity'] + 0.000001) {
                throw new RuntimeException('La devolucion acumulada supera la cantidad recibida.');
            }
        }
    }

    public function validateInvoice(string $companyId, string $supplierId, ?string $receiptId, string $currency, array $rows): void
    {
        if (! $receiptId) {
            return;
        }
        $receipt = $this->owned('purchase_receipts', $companyId, $receiptId, $supplierId);
        if ($receipt['currency_code'] !== $currency) {
            throw new RuntimeException('La factura y la recepcion deben usar la misma moneda.');
        }
        $received = [];
        foreach (db_connect()->table('purchase_receipt_items')->where('purchase_receipt_id', $receiptId)->get()->getResultArray() as $item) {
            $received[$item['product_id']] = ($received[$item['product_id']] ?? 0) + $item['quantity'];
        }
        $invoiced = [];
        foreach ($this->invoiceItems($companyId, $receiptId) as $item) {
            $invoiced[$item['product_id']] = ($invoiced[$item['product_id']] ?? 0) + $item['quantity'];
        }
        foreach ($rows as $row) {
            $id = $row['product_id'] ?? '';
            $invoiced[$id] = ($invoiced[$id] ?? 0) + $row['quantity'];
            if (! isset($received[$id]) || $invoiced[$id] > $received[$id] + 0.000001) {
                throw new RuntimeException('La cantidad facturada acumulada supera la cantidad recibida del producto.');
            }
        }
    }

    private function invoiceItems(string $companyId, string $receiptId): array
    {
        return db_connect()->table('purchase_invoice_items ii')->select('ii.*')
            ->join('purchase_invoices i', 'i.id = ii.purchase_invoice_id')->where('i.company_id', $companyId)
            ->where('i.purchase_receipt_id', $receiptId)->where('i.status !=', 'cancelled')->get()->getResultArray();
    }

    public function validateCredit(string $companyId, string $supplierId, ?string $invoiceId, float $amount, ?string $returnId): void
    {
        $invoice = $this->owned('purchase_invoices', $companyId, (string) $invoiceId, $supplierId);
        $credited = db_connect()->table('purchase_credit_notes')->selectSum('amount', 'total')->where('company_id', $companyId)
            ->where('purchase_invoice_id', $invoiceId)->where('status !=', 'cancelled')->get()->getRowArray();
        if (! is_finite($amount) || $amount <= 0 || $amount + (float) ($credited['total'] ?? 0) > (float) $invoice['total'] + 0.001) {
            throw new RuntimeException('Las notas de credito no pueden superar el total de la factura.');
        }
        if ($returnId) {
            $return = $this->owned('purchase_returns', $companyId, $returnId, $supplierId);
            if ($return['purchase_receipt_id'] !== $invoice['purchase_receipt_id'] || abs((float) $return['total'] - $amount) > 0.001
                || db_connect()->table('purchase_credit_notes')->where('purchase_return_id', $returnId)->where('status !=', 'cancelled')->countAllResults()) {
                throw new RuntimeException('La nota debe coincidir con una devolucion de la misma recepcion, sin nota previa.');
            }
        }
    }

    public function syncInvoice(string $companyId, string $invoiceId): void
    {
        $invoice = $this->owned('purchase_invoices', $companyId, $invoiceId);
        if ($invoice['purchase_receipt_id']) {
            $this->syncReceipt($companyId, $invoice['purchase_receipt_id']);
            return;
        }
        $model = new PurchasePayableModel();
        $payable = $model->where('company_id', $companyId)->where('purchase_invoice_id', $invoiceId)->first();
        if (! $payable) {
            $id = $model->insert(['company_id' => $companyId, 'supplier_id' => $invoice['supplier_id'],
                'purchase_invoice_id' => $invoiceId, 'purchase_receipt_id' => null, 'payable_number' => 'FAC-' . $invoiceId,
                'currency_code' => $invoice['currency_code'], 'total_amount' => $invoice['total'], 'paid_amount' => 0,
                'balance_amount' => $invoice['total'], 'status' => 'pending', 'due_date' => $invoice['due_date']], true);
            $payable = $model->find($id);
        }
        $credits = db_connect()->table('purchase_credit_notes')->selectSum('amount', 'total')->where('company_id', $companyId)
            ->where('purchase_invoice_id', $invoiceId)->where('status !=', 'cancelled')->get()->getRowArray();
        $this->setTotal($payable, (float) $invoice['total'] - (float) ($credits['total'] ?? 0));
    }

    public function syncReceipt(string $companyId, string $receiptId): void
    {
        $receipt = $this->owned('purchase_receipts', $companyId, $receiptId);
        $payable = (new PurchasePayableModel())->where('company_id', $companyId)->where('purchase_receipt_id', $receiptId)->first();
        if (! $payable) {
            throw new RuntimeException('La recepcion no tiene cuenta a pagar; requiere conciliacion.');
        }
        $products = [];
        $total = (float) $receipt['total'];
        $received = 0;
        foreach (db_connect()->table('purchase_receipt_items')->where('purchase_receipt_id', $receiptId)->get()->getResultArray() as $item) {
            $id = $item['product_id'];
            $products[$id]['qty'] = ($products[$id]['qty'] ?? 0) + $item['quantity'];
            $products[$id]['total'] = ($products[$id]['total'] ?? 0) + $item['line_total'];
            $received += $item['quantity'];
        }
        $invoiced = 0;
        foreach ($this->invoiceItems($companyId, $receiptId) as $item) {
            $p = $products[$item['product_id']] ?? null;
            if (! $p || $p['qty'] <= 0) {
                throw new RuntimeException('La factura historica no coincide con la recepcion; requiere conciliacion.');
            }
            $total += $item['line_total'] - $item['quantity'] * $p['total'] / $p['qty'];
            $invoiced += $item['quantity'];
        }
        $returns = db_connect()->table('purchase_returns')->selectSum('total', 'amount')->where('company_id', $companyId)
            ->where('purchase_receipt_id', $receiptId)->where('status !=', 'cancelled')->get()->getRowArray();
        // A credit documenting an existing return must not reduce the liability twice.
        $credits = db_connect()->table('purchase_credit_notes n')->selectSum('n.amount', 'amount')
            ->join('purchase_invoices i', 'i.id = n.purchase_invoice_id')->where('n.company_id', $companyId)
            ->where('i.purchase_receipt_id', $receiptId)->where('n.status !=', 'cancelled')->where('n.purchase_return_id', null)->get()->getRowArray();
        $this->setTotal($payable, $total - (float) ($returns['amount'] ?? 0) - (float) ($credits['amount'] ?? 0));
        db_connect()->table('purchase_receipts')->where('company_id', $companyId)->where('id', $receiptId)->update([
            'status' => $invoiced <= 0 ? 'registered' : ($invoiced >= $received ? 'invoiced_total' : 'invoiced_partial'),
        ]);
    }

    public function setTotal(array $payable, float $total): void
    {
        $total = round($total, 2);
        $paid = (float) $payable['paid_amount'];
        $balance = round($total - $paid, 2);
        // A negative balance is a supplier credit. Historical payments stay intact.
        (new PurchasePayableModel())->update($payable['id'], ['total_amount' => $total, 'balance_amount' => $balance,
            'status' => $balance < 0 ? 'credit' : ($balance == 0 ? 'paid' : ($paid > 0 ? 'partial' : 'pending'))]);
    }

    public function paymentSession(string $companyId, array $payable, string $currency, ?string $gatewayId, ?string $checkId): array
    {
        if ($currency !== $payable['currency_code']) {
            throw new RuntimeException('El pago debe expresarse en la moneda de la cuenta a pagar.');
        }
        foreach (['cash_payment_gateways' => $gatewayId, 'cash_checks' => $checkId] as $table => $id) {
            if ($id) {
                $row = $this->owned($table, $companyId, $id);
                if (isset($row['active']) && ! $row['active']) {
                    throw new RuntimeException('La pasarela seleccionada esta inactiva.');
                }
            }
        }
        $session = (new CashService())->activeSessionForChannel($companyId, 'general');
        if (! $session) {
            throw new RuntimeException('Debes abrir una caja antes de registrar el pago.');
        }
        $db = db_connect();
        $sql = 'SELECT * FROM ' . $db->protectIdentifiers($db->prefixTable('cash_sessions')) . ' WHERE id = ? AND company_id = ?';
        $locked = $db->query($sql . ($db->DBDriver === 'SQLite3' ? '' : ' FOR UPDATE'), [$session['id'], $companyId])->getRowArray();
        if (! $locked || $locked['status'] !== 'open') {
            throw new RuntimeException('La caja ya no esta abierta.');
        }
        return $locked;
    }
}
