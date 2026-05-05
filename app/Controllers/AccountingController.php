<?php

namespace App\Controllers;

use App\Libraries\AccountingService;
use CodeIgniter\HTTP\RedirectResponse;

class AccountingController extends BaseController
{
    private AccountingService $accounting;

    public function __construct()
    {
        $this->accounting = new AccountingService();
    }

    private function accountingContext(): array|RedirectResponse
    {
        $user = $this->currentUser();
        if (!$user) {
            return redirect()->to(site_url('login'));
        }
        $companyId = session('active_company_id') ?? ($user['company_id'] ?? null);
        if (!$companyId) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Seleccione una empresa.');
        }
        $company = db_connect()->table('companies')->where('id', $companyId)->get()->getRowArray();
        if (!$company) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Empresa no encontrada.');
        }
        return ['user' => $user, 'company' => $company];
    }

    // ── Plan de Cuentas ──────────────────────────────────
    public function index()
    {
        $ctx = $this->accountingContext();
        if ($ctx instanceof RedirectResponse) return $ctx;

        $companyId = $ctx['company']['id'];
        $accounts = db_connect()->table('accounts')
            ->where('company_id', $companyId)->where('active', 1)
            ->orderBy('code')->get()->getResultArray();
        $tree = $this->accounting->chartOfAccounts($companyId);

        return view('accounting/index', [
            'pageTitle'   => 'Contabilidad',
            'context'     => $ctx,
            'accounts'    => $accounts,
            'tree'        => $tree,
            'companyId'   => $companyId,
        ]);
    }

    // ── Formulario nueva cuenta ──────────────────────────
    public function createAccountForm()
    {
        $ctx = $this->accountingContext();
        if ($ctx instanceof RedirectResponse) return $ctx;

        $companyId = $ctx['company']['id'];
        $parents = db_connect()->table('accounts')
            ->where('company_id', $companyId)->where('active', 1)->where('is_group', 1)
            ->orderBy('code')->get()->getResultArray();

        return view('accounting/forms/account', [
            'pageTitle'  => 'Nueva cuenta',
            'context'    => $ctx,
            'parents'    => $parents,
            'companyId'  => $companyId,
            'formAction' => site_url('contabilidad/cuentas'),
            'isPopup'    => $this->isPopupRequest(),
        ]);
    }

    public function storeAccount()
    {
        $ctx = $this->accountingContext();
        if ($ctx instanceof RedirectResponse) return $ctx;

        $companyId = $ctx['company']['id'];
        $code = trim((string) $this->request->getPost('code'));
        $name = trim((string) $this->request->getPost('name'));
        if ($code === '' || $name === '') {
            return redirect()->back()->withInput()->with('error', 'Codigo y nombre son obligatorios.');
        }

        $parentId = trim((string) $this->request->getPost('parent_id')) ?: null;
        $level = 1;
        if ($parentId) {
            $parent = db_connect()->table('accounts')->where('id', $parentId)->get()->getRowArray();
            $level = $parent ? ((int)($parent['level'] ?? 1)) + 1 : 1;
        }

        $id = app_uuid();
        db_connect()->table('accounts')->insert([
            'id' => $id, 'company_id' => $companyId, 'parent_id' => $parentId,
            'code' => $code, 'name' => $name,
            'account_type' => $this->request->getPost('account_type') ?: 'expense',
            'is_group' => (int)($this->request->getPost('is_group') ?? 0),
            'level' => $level,
            'accepts_entries' => (int)($this->request->getPost('accepts_entries') ?? 1),
            'currency_code' => $this->request->getPost('currency_code') ?: 'ARS',
            'opening_balance' => (float)$this->request->getPost('opening_balance'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->popupOrRedirect(site_url('contabilidad'), 'Cuenta creada correctamente.');
    }

    // ── Libro Diario (Asientos) ──────────────────────────
    public function journal()
    {
        $ctx = $this->accountingContext();
        if ($ctx instanceof RedirectResponse) return $ctx;

        $companyId = $ctx['company']['id'];
        $from = $this->request->getGet('from') ?: date('Y-m-01');
        $to   = $this->request->getGet('to')   ?: date('Y-m-d');
        $status = $this->request->getGet('status') ?: '';

        $builder = db_connect()->table('journal_entries')
            ->where('company_id', $companyId)
            ->where('entry_date >=', $from)->where('entry_date <=', $to);
        if ($status !== '') $builder->where('status', $status);

        $entries = $builder->orderBy('entry_number', 'DESC')->get()->getResultArray();

        return view('accounting/journal', [
            'pageTitle' => 'Libro Diario',
            'context'   => $ctx,
            'entries'   => $entries,
            'filters'   => ['from' => $from, 'to' => $to, 'status' => $status],
            'companyId' => $companyId,
        ]);
    }

    // ── Formulario nuevo asiento ─────────────────────────
    public function createEntryForm()
    {
        $ctx = $this->accountingContext();
        if ($ctx instanceof RedirectResponse) return $ctx;

        $companyId = $ctx['company']['id'];
        $accounts = db_connect()->table('accounts')
            ->where('company_id', $companyId)->where('active', 1)->where('accepts_entries', 1)
            ->orderBy('code')->get()->getResultArray();

        return view('accounting/forms/entry', [
            'pageTitle'  => 'Nuevo asiento',
            'context'    => $ctx,
            'accounts'   => $accounts,
            'companyId'  => $companyId,
            'formAction' => site_url('contabilidad/asientos'),
            'isPopup'    => $this->isPopupRequest(),
        ]);
    }

    public function storeEntry()
    {
        $ctx = $this->accountingContext();
        if ($ctx instanceof RedirectResponse) return $ctx;

        $companyId = $ctx['company']['id'];
        $data = [
            'entry_date'     => $this->request->getPost('entry_date') ?: date('Y-m-d'),
            'description'    => trim((string) $this->request->getPost('description')),
            'reference_type' => 'manual',
            'status'         => $this->request->getPost('auto_post') === '1' ? 'posted' : 'draft',
            'user_id'        => $ctx['user']['id'],
        ];

        $lines = [];
        foreach ((array) $this->request->getPost('lines') as $line) {
            $accountId = trim((string) ($line['account_id'] ?? ''));
            if ($accountId === '') continue;
            $lines[] = [
                'account_id'  => $accountId,
                'description' => trim((string) ($line['description'] ?? '')),
                'debit'       => (float)($line['debit'] ?? 0),
                'credit'      => (float)($line['credit'] ?? 0),
            ];
        }

        if (count($lines) < 2) {
            return redirect()->back()->withInput()->with('error', 'Un asiento necesita al menos 2 lineas.');
        }

        $result = $this->accounting->createJournalEntry($companyId, $data, $lines);
        if (!$result['ok']) {
            return redirect()->back()->withInput()->with('error', $result['error'] ?? 'Error al crear asiento.');
        }

        return $this->popupOrRedirect(site_url('contabilidad/diario'), 'Asiento #' . $result['entry_number'] . ' creado correctamente.');
    }

    public function postEntry(string $id)
    {
        $ctx = $this->accountingContext();
        if ($ctx instanceof RedirectResponse) return $ctx;

        $this->accounting->postEntry($id);
        return redirect()->to(site_url('contabilidad/diario'))->with('message', 'Asiento contabilizado.');
    }

    // ── Libro Mayor ──────────────────────────────────────
    public function ledger(string $accountId)
    {
        $ctx = $this->accountingContext();
        if ($ctx instanceof RedirectResponse) return $ctx;

        $from = $this->request->getGet('from') ?: date('Y-m-01');
        $to   = $this->request->getGet('to')   ?: date('Y-m-d');
        $account = db_connect()->table('accounts')->where('id', $accountId)->get()->getRowArray();
        $ledger = $this->accounting->accountLedger($accountId, $from, $to);

        return view('accounting/ledger', [
            'pageTitle' => 'Mayor: ' . ($account['code'] ?? '') . ' ' . ($account['name'] ?? ''),
            'context'   => $ctx,
            'account'   => $account,
            'ledger'    => $ledger,
            'filters'   => ['from' => $from, 'to' => $to],
        ]);
    }

    // ── Balance de Comprobación ──────────────────────────
    public function trialBalance()
    {
        $ctx = $this->accountingContext();
        if ($ctx instanceof RedirectResponse) return $ctx;

        $date = $this->request->getGet('date') ?: date('Y-m-d');
        $trial = $this->accounting->trialBalance($ctx['company']['id'], $date);

        return view('accounting/trial_balance', [
            'pageTitle' => 'Balance de Comprobacion',
            'context'   => $ctx,
            'trial'     => $trial,
            'filters'   => ['date' => $date],
        ]);
    }

    // ── Balance General ─────────────────────────────────
    public function balanceSheet()
    {
        $ctx = $this->accountingContext();
        if ($ctx instanceof RedirectResponse) return $ctx;

        $date = $this->request->getGet('date') ?: date('Y-m-d');
        $balance = $this->accounting->balanceSheet($ctx['company']['id'], $date);

        return view('accounting/balance_sheet', [
            'pageTitle' => 'Balance General',
            'context'   => $ctx,
            'balance'   => $balance,
            'filters'   => ['date' => $date],
        ]);
    }

    // ── Estado de Resultados ────────────────────────────
    public function incomeStatement()
    {
        $ctx = $this->accountingContext();
        if ($ctx instanceof RedirectResponse) return $ctx;

        $from = $this->request->getGet('from') ?: date('Y-01-01');
        $to   = $this->request->getGet('to')   ?: date('Y-m-d');
        $statement = $this->accounting->incomeStatement($ctx['company']['id'], $from, $to);

        return view('accounting/income_statement', [
            'pageTitle' => 'Estado de Resultados',
            'context'   => $ctx,
            'statement' => $statement,
            'filters'   => ['from' => $from, 'to' => $to],
        ]);
    }
}

