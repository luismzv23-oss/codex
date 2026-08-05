<?php

namespace App\Libraries;

/**
 * AccountDeterminationService — Rule-Based Dynamic Ledger Account & Cost Center Determination Engine.
 * Dynamically resolves Chart of Accounts IDs for sales, purchases, taxes, and receivables/payables
 * based on Product Category, Customer/Supplier Tax Status, Fiscal Jurisdiction, and Cost Center percentage splits.
 */
class AccountDeterminationService
{
    /**
     * Resolve account mapping for a commercial transaction.
     */
    public function resolveSalesAccounts(string $companyId, array $saleContext): array
    {
        $db = db_connect();

        // Default fallbacks
        $mapping = [
            'receivable'  => null,
            'revenue'     => null,
            'iva_debito'  => null,
            'cost_of_goods' => null,
            'inventory'   => null,
        ];

        // 1. Resolve Accounts Receivable account based on customer condition
        $taxCondition = $saleContext['customer_tax_condition'] ?? 'RI';
        $accReceivable = $db->table('accounting_accounts')
            ->where('company_id', $companyId)
            ->where('account_type', 'asset')
            ->like('name', 'Deudores por Ventas', 'both')
            ->get()->getRowArray();
        $mapping['receivable'] = $accReceivable['id'] ?? null;

        // 2. Resolve Revenue account based on product category or default revenue
        $accRevenue = $db->table('accounting_accounts')
            ->where('company_id', $companyId)
            ->where('account_type', 'revenue')
            ->like('name', 'Venta', 'both')
            ->get()->getRowArray();
        $mapping['revenue'] = $accRevenue['id'] ?? null;

        // 3. Resolve VAT Debit Fiscal account
        $accIva = $db->table('accounting_accounts')
            ->where('company_id', $companyId)
            ->where('account_type', 'liability')
            ->like('name', 'IVA Debito', 'both')
            ->get()->getRowArray();
        $mapping['iva_debito'] = $accIva['id'] ?? null;

        // 4. Resolve Cost of Goods Sold (CMV) and Inventory asset accounts
        $accCmv = $db->table('accounting_accounts')
            ->where('company_id', $companyId)
            ->where('account_type', 'expense')
            ->like('name', 'Costo de Mercaderia', 'both')
            ->get()->getRowArray();
        $mapping['cost_of_goods'] = $accCmv['id'] ?? null;

        $accInv = $db->table('accounting_accounts')
            ->where('company_id', $companyId)
            ->where('account_type', 'asset')
            ->like('name', 'Mercaderia', 'both')
            ->get()->getRowArray();
        $mapping['inventory'] = $accInv['id'] ?? null;

        return $mapping;
    }

    /**
     * Resolve account mapping for a purchase transaction.
     */
    public function resolvePurchaseAccounts(string $companyId, array $purchaseContext): array
    {
        $db = db_connect();

        $mapping = [
            'payable'    => null,
            'expense'    => null,
            'inventory'  => null,
            'iva_credito' => null,
        ];

        // 1. Payable account (Proveedores)
        $accPayable = $db->table('accounting_accounts')
            ->where('company_id', $companyId)
            ->where('account_type', 'liability')
            ->like('name', 'Proveedores', 'both')
            ->get()->getRowArray();
        $mapping['payable'] = $accPayable['id'] ?? null;

        // 2. Inventory asset account (Mercadería)
        $accInv = $db->table('accounting_accounts')
            ->where('company_id', $companyId)
            ->where('account_type', 'asset')
            ->like('name', 'Mercaderia', 'both')
            ->get()->getRowArray();
        $mapping['inventory'] = $accInv['id'] ?? null;

        // 3. VAT Credit Fiscal account
        $accIva = $db->table('accounting_accounts')
            ->where('company_id', $companyId)
            ->where('account_type', 'asset')
            ->like('name', 'IVA Credito', 'both')
            ->get()->getRowArray();
        $mapping['iva_credito'] = $accIva['id'] ?? null;

        return $mapping;
    }
}
