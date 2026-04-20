<?php

namespace App\Libraries;

/**
 * AccountingService — Core accounting engine.
 * Handles chart of accounts, journal entries, and financial statements.
 */
class AccountingService
{
    /**
     * Create a journal entry with balanced debit/credit lines.
     */
    public function createJournalEntry(string $companyId, array $data, array $lines): array
    {
        $db = db_connect();
        $db->transBegin();

        try {
            $totalDebit  = array_sum(array_column($lines, 'debit'));
            $totalCredit = array_sum(array_column($lines, 'credit'));

            if (round($totalDebit, 2) !== round($totalCredit, 2)) {
                throw new \RuntimeException("Asiento desbalanceado: Debe={$totalDebit}, Haber={$totalCredit}");
            }

            $lastEntry = $db->table('journal_entries')
                ->where('company_id', $companyId)
                ->selectMax('entry_number')
                ->get()->getRowArray();
            $nextNumber = ((int) ($lastEntry['entry_number'] ?? 0)) + 1;

            $entryId = app_uuid();
            $entry = [
                'id'             => $entryId,
                'company_id'     => $companyId,
                'entry_number'   => $nextNumber,
                'entry_date'     => $data['entry_date'] ?? date('Y-m-d'),
                'description'    => $data['description'] ?? '',
                'reference_type' => $data['reference_type'] ?? 'manual',
                'reference_id'   => $data['reference_id'] ?? null,
                'status'         => $data['status'] ?? 'draft',
                'total_debit'    => round($totalDebit, 2),
                'total_credit'   => round($totalCredit, 2),
                'user_id'        => $data['user_id'] ?? (auth_user()['id'] ?? null),
                'posted_at'      => ($data['status'] ?? 'draft') === 'posted' ? date('Y-m-d H:i:s') : null,
                'created_at'     => date('Y-m-d H:i:s'),
            ];

            $db->table('journal_entries')->insert($entry);

            foreach ($lines as $line) {
                $db->table('journal_entry_lines')->insert([
                    'id'               => app_uuid(),
                    'journal_entry_id' => $entryId,
                    'account_id'       => $line['account_id'],
                    'description'      => $line['description'] ?? null,
                    'debit'            => (float) ($line['debit'] ?? 0),
                    'credit'           => (float) ($line['credit'] ?? 0),
                    'created_at'       => date('Y-m-d H:i:s'),
                ]);
            }

            $db->transCommit();
            return ['ok' => true, 'entry_id' => $entryId, 'entry_number' => $nextNumber];
        } catch (\Throwable $e) {
            $db->transRollback();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function postEntry(string $entryId): bool
    {
        return db_connect()->table('journal_entries')
            ->where('id', $entryId)->where('status', 'draft')
            ->update(['status' => 'posted', 'posted_at' => date('Y-m-d H:i:s')]);
    }

    public function journalFromSale(string $companyId, array $sale, array $map): array
    {
        $lines = [];
        $total = (float) ($sale['total'] ?? 0);
        $sub   = (float) ($sale['subtotal'] ?? 0);
        $tax   = (float) ($sale['tax_total'] ?? 0);

        if (!empty($map['receivable'])) $lines[] = ['account_id' => $map['receivable'], 'debit' => $total, 'credit' => 0, 'description' => 'Venta #' . ($sale['sale_number'] ?? '')];
        if (!empty($map['revenue']))    $lines[] = ['account_id' => $map['revenue'], 'debit' => 0, 'credit' => $sub, 'description' => 'Ingreso por venta'];
        if (!empty($map['iva_debito']) && $tax > 0) $lines[] = ['account_id' => $map['iva_debito'], 'debit' => 0, 'credit' => $tax, 'description' => 'IVA Debito Fiscal'];

        return $this->createJournalEntry($companyId, [
            'description' => 'Venta #' . ($sale['sale_number'] ?? ''), 'entry_date' => $sale['sale_date'] ?? date('Y-m-d'),
            'reference_type' => 'sale', 'reference_id' => $sale['id'] ?? null, 'status' => 'posted',
        ], $lines);
    }

    public function journalFromPurchase(string $companyId, array $inv, array $map): array
    {
        $lines = [];
        $total = (float) ($inv['total'] ?? 0);
        $sub   = (float) ($inv['subtotal'] ?? 0);
        $tax   = (float) ($inv['tax_total'] ?? 0);

        if (!empty($map['expense']))     $lines[] = ['account_id' => $map['expense'], 'debit' => $sub, 'credit' => 0, 'description' => 'Compra FC #' . ($inv['invoice_number'] ?? '')];
        if (!empty($map['iva_credito']) && $tax > 0) $lines[] = ['account_id' => $map['iva_credito'], 'debit' => $tax, 'credit' => 0, 'description' => 'IVA Credito Fiscal'];
        if (!empty($map['payable']))     $lines[] = ['account_id' => $map['payable'], 'debit' => 0, 'credit' => $total, 'description' => 'Proveedor'];

        return $this->createJournalEntry($companyId, [
            'description' => 'Compra FC #' . ($inv['invoice_number'] ?? ''), 'entry_date' => $inv['invoice_date'] ?? date('Y-m-d'),
            'reference_type' => 'purchase', 'reference_id' => $inv['id'] ?? null, 'status' => 'posted',
        ], $lines);
    }

    public function trialBalance(string $companyId, string $asOfDate): array
    {
        $rows = db_connect()->query("
            SELECT a.id, a.code, a.name, a.account_type, a.is_group, a.level,
                COALESCE(SUM(jl.debit),0) AS total_debit, COALESCE(SUM(jl.credit),0) AS total_credit,
                COALESCE(SUM(jl.debit),0) - COALESCE(SUM(jl.credit),0) AS balance
            FROM accounts a
            LEFT JOIN journal_entry_lines jl ON jl.account_id = a.id
            LEFT JOIN journal_entries je ON je.id = jl.journal_entry_id AND je.status='posted' AND je.entry_date <= ?
            WHERE a.company_id = ? AND a.active = 1
            GROUP BY a.id, a.code, a.name, a.account_type, a.is_group, a.level ORDER BY a.code
        ", [$asOfDate, $companyId])->getResultArray();

        $td = array_sum(array_column($rows, 'total_debit'));
        $tc = array_sum(array_column($rows, 'total_credit'));
        return ['as_of_date' => $asOfDate, 'accounts' => $rows, 'total_debit' => $td, 'total_credit' => $tc, 'balanced' => round($td,2) === round($tc,2)];
    }

    public function balanceSheet(string $companyId, string $asOfDate): array
    {
        $trial = $this->trialBalance($companyId, $asOfDate);
        $assets = array_filter($trial['accounts'], fn($a) => $a['account_type'] === 'asset');
        $liabilities = array_filter($trial['accounts'], fn($a) => $a['account_type'] === 'liability');
        $equity = array_filter($trial['accounts'], fn($a) => $a['account_type'] === 'equity');
        $ta = array_sum(array_column($assets, 'balance'));
        $tl = abs(array_sum(array_column($liabilities, 'balance')));
        $te = abs(array_sum(array_column($equity, 'balance')));
        $pl = $this->incomeStatement($companyId, date('Y-01-01'), $asOfDate);

        return [
            'as_of_date' => $asOfDate,
            'assets' => ['accounts' => array_values($assets), 'total' => $ta],
            'liabilities' => ['accounts' => array_values($liabilities), 'total' => $tl],
            'equity' => ['accounts' => array_values($equity), 'total' => $te, 'net_income' => $pl['net_income'], 'total_with_income' => $te + $pl['net_income']],
            'balanced' => round($ta, 2) === round($tl + $te + $pl['net_income'], 2),
        ];
    }

    public function incomeStatement(string $companyId, string $from, string $to): array
    {
        $rows = db_connect()->query("
            SELECT a.id, a.code, a.name, a.account_type,
                COALESCE(SUM(jl.credit),0) - COALESCE(SUM(jl.debit),0) AS balance
            FROM accounts a
            LEFT JOIN journal_entry_lines jl ON jl.account_id = a.id
            LEFT JOIN journal_entries je ON je.id = jl.journal_entry_id AND je.status='posted' AND je.entry_date BETWEEN ? AND ?
            WHERE a.company_id = ? AND a.active = 1 AND a.account_type IN ('revenue','expense')
            GROUP BY a.id, a.code, a.name, a.account_type ORDER BY a.account_type DESC, a.code
        ", [$from, $to, $companyId])->getResultArray();

        $rev = array_filter($rows, fn($a) => $a['account_type'] === 'revenue');
        $exp = array_filter($rows, fn($a) => $a['account_type'] === 'expense');
        $tr = array_sum(array_column($rev, 'balance'));
        $te = abs(array_sum(array_column($exp, 'balance')));

        return ['from_date' => $from, 'to_date' => $to, 'revenue' => ['accounts' => array_values($rev), 'total' => $tr],
            'expenses' => ['accounts' => array_values($exp), 'total' => $te], 'net_income' => $tr - $te,
            'profit_margin' => $tr > 0 ? round(($tr - $te) / $tr * 100, 2) : 0];
    }

    public function accountLedger(string $accountId, string $from, string $to): array
    {
        $rows = db_connect()->table('journal_entry_lines jl')
            ->select('jl.*, je.entry_number, je.entry_date, je.description AS entry_description')
            ->join('journal_entries je', 'je.id = jl.journal_entry_id')
            ->where('jl.account_id', $accountId)->where('je.status', 'posted')
            ->where('je.entry_date >=', $from)->where('je.entry_date <=', $to)
            ->orderBy('je.entry_date')->orderBy('je.entry_number')->get()->getResultArray();

        $bal = 0;
        foreach ($rows as &$r) { $bal += (float)$r['debit'] - (float)$r['credit']; $r['running_balance'] = round($bal, 2); }
        return ['account_id' => $accountId, 'from' => $from, 'to' => $to, 'entries' => $rows, 'final_balance' => $bal];
    }

    public function chartOfAccounts(string $companyId): array
    {
        $accounts = db_connect()->table('accounts')->where('company_id', $companyId)->where('active', 1)->orderBy('code')->get()->getResultArray();
        return $this->buildTree($accounts);
    }

    private function buildTree(array $items, ?string $parentId = null): array
    {
        $result = [];
        foreach ($items as $item) { if ($item['parent_id'] === $parentId) { $item['children'] = $this->buildTree($items, $item['id']); $result[] = $item; } }
        return $result;
    }

    // ── Sync methods called by Sales/Purchases controllers ──

    /**
     * Sync accounting for a confirmed sale.
     */
    public function syncSale(string $companyId, string $saleId, string $userId): array
    {
        $db = db_connect();
        $sale = $db->table('sales')->where('id', $saleId)->get()->getRowArray();
        if (!$sale || ($sale['status'] ?? '') !== 'confirmed') {
            return ['ok' => false, 'error' => 'Sale not found or not confirmed'];
        }

        // Check if already synced
        $existing = $db->table('journal_entries')
            ->where('company_id', $companyId)->where('reference_type', 'sale')->where('reference_id', $saleId)
            ->countAllResults();
        if ($existing > 0) return ['ok' => true, 'already_synced' => true];

        $map = $this->getAccountMapping($companyId);
        if (empty($map)) return ['ok' => true, 'skipped' => 'no_account_mapping'];

        $sale['user_id'] = $userId;
        return $this->journalFromSale($companyId, $sale, $map);
    }

    /**
     * Sync accounting for a sales receipt (cobro).
     */
    public function syncSalesReceipt(string $companyId, string $receiptId, string $userId): array
    {
        $db = db_connect();
        $receipt = $db->table('sales_receipts')->where('id', $receiptId)->get()->getRowArray();
        if (!$receipt) return ['ok' => false, 'error' => 'Receipt not found'];

        $map = $this->getAccountMapping($companyId);
        if (empty($map)) return ['ok' => true, 'skipped' => 'no_account_mapping'];

        $lines = [];
        $amount = (float)($receipt['total'] ?? 0);

        // Debit: Cash or Bank
        $cashAccount = $map['cash'] ?? $map['bank'] ?? null;
        if ($cashAccount) $lines[] = ['account_id' => $cashAccount, 'debit' => $amount, 'credit' => 0, 'description' => 'Cobro recibo #' . ($receipt['receipt_number'] ?? '')];

        // Credit: Accounts Receivable
        if (!empty($map['receivable'])) $lines[] = ['account_id' => $map['receivable'], 'debit' => 0, 'credit' => $amount, 'description' => 'Cobranza cliente'];

        if (empty($lines)) return ['ok' => true, 'skipped' => 'no_accounts'];

        return $this->createJournalEntry($companyId, [
            'description' => 'Cobro recibo #' . ($receipt['receipt_number'] ?? ''),
            'entry_date' => $receipt['receipt_date'] ?? date('Y-m-d'),
            'reference_type' => 'sales_receipt', 'reference_id' => $receiptId,
            'status' => 'posted', 'user_id' => $userId,
        ], $lines);
    }

    /**
     * Sync accounting for a sale return (NC).
     */
    public function syncSaleReturn(string $companyId, string $returnId, string $userId): array
    {
        $db = db_connect();
        $ret = $db->table('sale_returns')->where('id', $returnId)->get()->getRowArray();
        if (!$ret) return ['ok' => false, 'error' => 'Return not found'];

        $map = $this->getAccountMapping($companyId);
        if (empty($map)) return ['ok' => true, 'skipped' => 'no_account_mapping'];

        $lines = [];
        $total = (float)($ret['total'] ?? 0);
        $tax   = (float)($ret['tax_total'] ?? 0);
        $sub   = $total - $tax;

        // Reverse the sale entry
        if (!empty($map['revenue']))    $lines[] = ['account_id' => $map['revenue'], 'debit' => $sub, 'credit' => 0, 'description' => 'NC - Devolucion venta'];
        if (!empty($map['iva_debito']) && $tax > 0) $lines[] = ['account_id' => $map['iva_debito'], 'debit' => $tax, 'credit' => 0, 'description' => 'NC - IVA Debito Fiscal'];
        if (!empty($map['receivable'])) $lines[] = ['account_id' => $map['receivable'], 'debit' => 0, 'credit' => $total, 'description' => 'NC - Cuenta cliente'];

        if (empty($lines)) return ['ok' => true, 'skipped' => 'no_accounts'];

        return $this->createJournalEntry($companyId, [
            'description' => 'NC Devolucion #' . ($ret['return_number'] ?? ''),
            'entry_date' => $ret['return_date'] ?? date('Y-m-d'),
            'reference_type' => 'sale_return', 'reference_id' => $returnId,
            'status' => 'posted', 'user_id' => $userId,
        ], $lines);
    }

    /**
     * Sync accounting for a purchase receipt (remito ingreso).
     */
    public function syncPurchaseReceipt(string $companyId, string $receiptId, string $userId): array
    {
        $db = db_connect();
        $receipt = $db->table('purchase_receipts')->where('id', $receiptId)->get()->getRowArray();
        if (!$receipt) return ['ok' => false, 'error' => 'Purchase receipt not found'];

        $map = $this->getAccountMapping($companyId);
        if (empty($map)) return ['ok' => true, 'skipped' => 'no_account_mapping'];

        // Purchase receipts don't always generate journal entries (only invoices do)
        // But if there's an inventory account, we can track the stock value
        $invAccount = $map['inventory'] ?? null;
        if (!$invAccount) return ['ok' => true, 'skipped' => 'no_inventory_account'];

        $total = (float)($receipt['total'] ?? 0);
        if ($total <= 0) return ['ok' => true, 'skipped' => 'zero_amount'];

        $lines = [
            ['account_id' => $invAccount, 'debit' => $total, 'credit' => 0, 'description' => 'Ingreso mercaderia'],
            ['account_id' => $map['goods_received'] ?? $map['payable'] ?? $invAccount, 'debit' => 0, 'credit' => $total, 'description' => 'Mercaderia recibida'],
        ];

        return $this->createJournalEntry($companyId, [
            'description' => 'Remito ingreso #' . ($receipt['receipt_number'] ?? ''),
            'entry_date' => $receipt['receipt_date'] ?? date('Y-m-d'),
            'reference_type' => 'purchase_receipt', 'reference_id' => $receiptId,
            'status' => 'posted', 'user_id' => $userId,
        ], $lines);
    }

    /**
     * Sync accounting for a purchase return.
     */
    public function syncPurchaseReturn(string $companyId, string $returnId, string $userId): array
    {
        $db = db_connect();
        $ret = $db->table('purchase_returns')->where('id', $returnId)->get()->getRowArray();
        if (!$ret) return ['ok' => false, 'error' => 'Purchase return not found'];

        $map = $this->getAccountMapping($companyId);
        if (empty($map)) return ['ok' => true, 'skipped' => 'no_account_mapping'];

        $total = (float)($ret['total'] ?? 0);
        $tax   = (float)($ret['tax_total'] ?? 0);
        $sub   = $total - $tax;
        $lines = [];

        if (!empty($map['payable']))     $lines[] = ['account_id' => $map['payable'], 'debit' => $total, 'credit' => 0, 'description' => 'NC Proveedor - Devolucion'];
        if (!empty($map['expense']))     $lines[] = ['account_id' => $map['expense'], 'debit' => 0, 'credit' => $sub, 'description' => 'Reversa gasto compra'];
        if (!empty($map['iva_credito']) && $tax > 0) $lines[] = ['account_id' => $map['iva_credito'], 'debit' => 0, 'credit' => $tax, 'description' => 'Reversa IVA Credito'];

        if (empty($lines)) return ['ok' => true, 'skipped' => 'no_accounts'];

        return $this->createJournalEntry($companyId, [
            'description' => 'NC Proveedor Dev. #' . ($ret['return_number'] ?? ''),
            'entry_date' => $ret['return_date'] ?? date('Y-m-d'),
            'reference_type' => 'purchase_return', 'reference_id' => $returnId,
            'status' => 'posted', 'user_id' => $userId,
        ], $lines);
    }

    /**
     * Sync accounting for a purchase payment.
     */
    public function syncPurchasePayment(string $companyId, string $paymentId, string $userId): array
    {
        $db = db_connect();
        $payment = $db->table('purchase_payments')->where('id', $paymentId)->get()->getRowArray();
        if (!$payment) return ['ok' => false, 'error' => 'Payment not found'];

        $map = $this->getAccountMapping($companyId);
        if (empty($map)) return ['ok' => true, 'skipped' => 'no_account_mapping'];

        $amount = (float)($payment['amount'] ?? 0);
        $lines = [];

        // Debit: Accounts Payable
        if (!empty($map['payable'])) $lines[] = ['account_id' => $map['payable'], 'debit' => $amount, 'credit' => 0, 'description' => 'Pago proveedor'];
        // Credit: Cash or Bank
        $cashAccount = $map['cash'] ?? $map['bank'] ?? null;
        if ($cashAccount) $lines[] = ['account_id' => $cashAccount, 'debit' => 0, 'credit' => $amount, 'description' => 'Egreso por pago'];

        if (empty($lines)) return ['ok' => true, 'skipped' => 'no_accounts'];

        return $this->createJournalEntry($companyId, [
            'description' => 'Pago proveedor #' . ($payment['payment_number'] ?? $paymentId),
            'entry_date' => $payment['payment_date'] ?? date('Y-m-d'),
            'reference_type' => 'purchase_payment', 'reference_id' => $paymentId,
            'status' => 'posted', 'user_id' => $userId,
        ], $lines);
    }

    /**
     * Get accounting mapping from company_settings.
     */
    private function getAccountMapping(string $companyId): array
    {
        $settings = db_connect()->table('company_settings')
            ->where('company_id', $companyId)
            ->like('key', 'account_', 'after')
            ->get()->getResultArray();

        $map = [];
        foreach ($settings as $s) {
            $key = str_replace('account_', '', $s['key'] ?? '');
            if ($key !== '' && !empty($s['value'])) {
                $map[$key] = $s['value'];
            }
        }
        return $map;
    }
}

