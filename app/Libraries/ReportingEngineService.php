<?php

namespace App\Libraries;

use App\Models\SaleModel;
use App\Models\SaleItemModel;
use App\Models\SalesReceivableModel;
use App\Models\CustomerModel;
use App\Models\InventoryProductModel;
use App\Models\InventoryStockLevelModel;
use App\Models\InventoryCostLayerModel;
use App\Models\CashCheckModel;
use App\Models\CashClosureModel;
use App\Models\AccountingAccountModel;
use App\Models\JournalEntryLineModel;

/**
 * ReportingEngineService — Centralized Data Aggregation Engine for ERP Reporting
 */
class ReportingEngineService
{
    protected $db;

    public function __construct()
    {
        $this->db = db_connect();
    }

    /**
     * Report 1: Sales Pareto Ranking 80/20 (Top Products / Customers)
     */
    public function getSalesParetoRanking(string $companyId, string $startDate, string $endDate, int $limit = 50): array
    {
        $builder = $this->db->table('sale_items si')
            ->select('si.product_id, p.name as product_name, p.sku, SUM(si.quantity) as total_qty, SUM(si.subtotal) as total_amount')
            ->join('sales s', 's.id = si.sale_id')
            ->join('inventory_products p', 'p.id = si.product_id')
            ->where('s.company_id', $companyId)
            ->where('s.created_at >=', $startDate)
            ->where('s.created_at <=', $endDate)
            ->groupBy('si.product_id, p.name, p.sku')
            ->orderBy('total_amount', 'DESC')
            ->limit($limit);

        return $builder->get()->getResultArray();
    }

    /**
     * Report 2: Aging of Receivables (Cuentas Corrientes 30-60-90-120 días)
     */
    public function getReceivablesAging(string $companyId): array
    {
        $sql = "
            SELECT 
                c.id as customer_id,
                c.name as customer_name,
                c.cuit,
                SUM(r.balance) as total_due,
                SUM(CASE WHEN DATEDIFF(NOW(), r.due_date) <= 0 THEN r.balance ELSE 0 END) as current_amount,
                SUM(CASE WHEN DATEDIFF(NOW(), r.due_date) BETWEEN 1 AND 30 THEN r.balance ELSE 0 END) as aging_1_30,
                SUM(CASE WHEN DATEDIFF(NOW(), r.due_date) BETWEEN 31 AND 60 THEN r.balance ELSE 0 END) as aging_31_60,
                SUM(CASE WHEN DATEDIFF(NOW(), r.due_date) BETWEEN 61 AND 90 THEN r.balance ELSE 0 END) as aging_61_90,
                SUM(CASE WHEN DATEDIFF(NOW(), r.due_date) > 90 THEN r.balance ELSE 0 END) as aging_over_90
            FROM sales_receivables r
            JOIN customers c ON c.id = r.customer_id
            WHERE r.company_id = ? AND r.status != 'paid' AND r.balance > 0
            GROUP BY c.id, c.name, c.cuit
            ORDER BY total_due DESC
        ";

        return $this->db->query($sql, [$companyId])->getResultArray();
    }

    /**
     * Report 3: Valued Inventory Kardex & Cost Layers
     */
    public function getValuedKardex(string $companyId): array
    {
        $sql = "
            SELECT 
                p.id as product_id,
                p.sku,
                p.name as product_name,
                SUM(sl.quantity) as physical_stock,
                cl.cost_price,
                (SUM(sl.quantity) * cl.cost_price) as total_value
            FROM inventory_products p
            JOIN inventory_stock_levels sl ON sl.product_id = p.id
            LEFT JOIN inventory_cost_layers cl ON cl.product_id = p.id AND cl.remaining_qty > 0
            WHERE p.company_id = ?
            GROUP BY p.id, p.sku, p.name, cl.cost_price
            ORDER BY total_value DESC
        ";

        return $this->db->query($sql, [$companyId])->getResultArray();
    }

    /**
     * Report 4: Cash Flow Forecast (30-60-90 Days Rolling Projection)
     */
    public function getCashFlowForecast(string $companyId): array
    {
        $receivables = $this->db->table('sales_receivables')
            ->selectSum('balance', 'total_inflow')
            ->where('company_id', $companyId)
            ->where('status !=', 'paid')
            ->get()->getRowArray();

        $checks = $this->db->table('cash_checks')
            ->selectSum('amount', 'checks_inflow')
            ->where('company_id', $companyId)
            ->where('status', 'cartera')
            ->get()->getRowArray();

        return [
            'projected_inflows'  => (float)($receivables['total_inflow'] ?? 0) + (float)($checks['checks_inflow'] ?? 0),
            'receivables_due'    => (float)($receivables['total_inflow'] ?? 0),
            'checks_in_portfolio'=> (float)($checks['checks_inflow'] ?? 0),
            'projected_outflows' => 0.00, // Extensible for supplier payables
        ];
    }
}
