<?php

namespace App\Controllers\Api\V1;

use App\Libraries\AccountingService;

class AccountingController extends BaseApiController
{
    private AccountingService $accounting;

    public function __construct()
    {
        $this->accounting = new AccountingService();
    }

    public function chartOfAccounts()
    {
        return $this->success($this->accounting->chartOfAccounts($this->apiCompanyId()));
    }

    public function storeAccount()
    {
        $p = $this->payload();
        $id = app_uuid();
        db_connect()->table('accounts')->insert([
            'id' => $id, 'company_id' => $this->apiCompanyId(),
            'parent_id' => $p['parent_id'] ?? null, 'code' => $p['code'] ?? '', 'name' => $p['name'] ?? '',
            'account_type' => $p['account_type'] ?? 'expense', 'is_group' => (int)($p['is_group'] ?? 0),
            'level' => (int)($p['level'] ?? 1), 'accepts_entries' => (int)($p['accepts_entries'] ?? 1),
            'currency_code' => $p['currency_code'] ?? 'ARS', 'created_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->success(['id' => $id], 201);
    }

    public function storeJournalEntry()
    {
        $p = $this->payload();
        $result = $this->accounting->createJournalEntry($this->apiCompanyId(), $p, $p['lines'] ?? []);
        return $result['ok'] ? $this->success($result, 201) : $this->fail($result['error'] ?? 'Error', 422);
    }

    public function postEntry(string $id)
    {
        $this->accounting->postEntry($id);
        return $this->success(['posted' => true]);
    }

    public function trialBalance()
    {
        $date = $this->request->getGet('date') ?? date('Y-m-d');
        return $this->success($this->accounting->trialBalance($this->apiCompanyId(), $date));
    }

    public function balanceSheet()
    {
        $date = $this->request->getGet('date') ?? date('Y-m-d');
        return $this->success($this->accounting->balanceSheet($this->apiCompanyId(), $date));
    }

    public function incomeStatement()
    {
        $from = $this->request->getGet('from') ?? date('Y-01-01');
        $to   = $this->request->getGet('to') ?? date('Y-m-d');
        return $this->success($this->accounting->incomeStatement($this->apiCompanyId(), $from, $to));
    }

    public function accountLedger(string $accountId)
    {
        $from = $this->request->getGet('from') ?? date('Y-m-01');
        $to   = $this->request->getGet('to') ?? date('Y-m-d');
        return $this->success($this->accounting->accountLedger($accountId, $from, $to));
    }
}
