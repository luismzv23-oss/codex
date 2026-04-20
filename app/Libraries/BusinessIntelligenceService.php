<?php

namespace App\Libraries;

/**
 * BusinessIntelligenceService — KPIs, forecasting, and executive dashboards.
 */
class BusinessIntelligenceService
{
    public function executiveDashboard(string $companyId, string $period = 'month'): array
    {
        $db = db_connect();
        $now = date('Y-m-d');
        [$from, $to] = $this->periodRange($period);

        // Sales KPIs
        $salesQuery = $db->table('sales')->where('company_id', $companyId)->where('status', 'confirmed')
            ->where('sale_date >=', $from)->where('sale_date <=', $to);
        $salesCount = $salesQuery->countAllResults(false);
        $salesTotal = (float)($salesQuery->selectSum('total')->get()->getRowArray()['total'] ?? 0);

        // Previous period for comparison
        [$prevFrom, $prevTo] = $this->previousPeriodRange($period);
        $prevTotal = (float)(db_connect()->table('sales')->where('company_id', $companyId)->where('status', 'confirmed')
            ->where('sale_date >=', $prevFrom)->where('sale_date <=', $prevTo)->selectSum('total')->get()->getRowArray()['total'] ?? 0);
        $salesGrowth = $prevTotal > 0 ? round(($salesTotal - $prevTotal) / $prevTotal * 100, 1) : 0;

        // Purchase KPIs
        $purchaseTotal = (float)(db_connect()->table('purchase_invoices')->where('company_id', $companyId)
            ->where('status !=', 'cancelled')->where('invoice_date >=', $from)->where('invoice_date <=', $to)
            ->selectSum('total')->get()->getRowArray()['total'] ?? 0);

        // Receivables
        $receivables = (float)(db_connect()->table('sales')->where('company_id', $companyId)->where('status', 'confirmed')
            ->where('payment_status !=', 'paid')->selectSum('total')->get()->getRowArray()['total'] ?? 0);

        // Payables
        $payables = (float)(db_connect()->table('purchase_invoices')->where('company_id', $companyId)
            ->where('status !=', 'cancelled')->where('payment_status !=', 'paid')
            ->selectSum('total')->get()->getRowArray()['total'] ?? 0);

        // Inventory value
        $inventoryValue = (float)(db_connect()->table('products')->where('company_id', $companyId)->where('active', 1)
            ->select('SUM(stock * cost_price) AS val')->get()->getRowArray()['val'] ?? 0);

        // Top products
        $topProducts = db_connect()->query("
            SELECT si.product_name, SUM(si.quantity) AS qty, SUM(si.line_total) AS revenue
            FROM sale_items si JOIN sales s ON s.id = si.sale_id
            WHERE s.company_id = ? AND s.status = 'confirmed' AND s.sale_date BETWEEN ? AND ?
            GROUP BY si.product_name ORDER BY revenue DESC LIMIT 5
        ", [$companyId, $from, $to])->getResultArray();

        // Top customers
        $topCustomers = db_connect()->query("
            SELECT customer_name_snapshot AS name, COUNT(*) AS orders, SUM(total) AS revenue
            FROM sales WHERE company_id = ? AND status = 'confirmed' AND sale_date BETWEEN ? AND ?
            GROUP BY customer_name_snapshot ORDER BY revenue DESC LIMIT 5
        ", [$companyId, $from, $to])->getResultArray();

        // Daily trend
        $dailySeries = db_connect()->query("
            SELECT sale_date AS date, COUNT(*) AS count, SUM(total) AS total
            FROM sales WHERE company_id = ? AND status = 'confirmed' AND sale_date BETWEEN ? AND ?
            GROUP BY sale_date ORDER BY sale_date
        ", [$companyId, $from, $to])->getResultArray();

        return [
            'period' => ['from' => $from, 'to' => $to, 'label' => $period],
            'kpis' => [
                'sales_total'    => $salesTotal,
                'sales_count'    => $salesCount,
                'sales_growth'   => $salesGrowth,
                'avg_ticket'     => $salesCount > 0 ? round($salesTotal / $salesCount, 2) : 0,
                'purchase_total' => $purchaseTotal,
                'gross_margin'   => $salesTotal > 0 ? round(($salesTotal - $purchaseTotal) / $salesTotal * 100, 1) : 0,
                'receivables'    => $receivables,
                'payables'       => $payables,
                'net_position'   => $receivables - $payables,
                'inventory_value'=> $inventoryValue,
            ],
            'top_products'  => $topProducts,
            'top_customers' => $topCustomers,
            'daily_series'  => $dailySeries,
        ];
    }

    public function salesForecast(string $companyId, int $daysAhead = 30): array
    {
        $db = db_connect();
        $history = $db->query("
            SELECT sale_date AS date, SUM(total) AS total
            FROM sales WHERE company_id = ? AND status = 'confirmed' AND sale_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
            GROUP BY sale_date ORDER BY sale_date
        ", [$companyId])->getResultArray();

        if (count($history) < 7) {
            return ['forecast' => [], 'confidence' => 'low', 'message' => 'Datos insuficientes (menos de 7 dias)'];
        }

        // Simple moving average forecast
        $values = array_column($history, 'total');
        $avg7  = array_sum(array_slice($values, -7)) / 7;
        $avg30 = count($values) >= 30 ? array_sum(array_slice($values, -30)) / 30 : $avg7;
        $trend = $avg7 - $avg30; // positive = growing

        $forecast = [];
        for ($i = 1; $i <= $daysAhead; $i++) {
            $date = date('Y-m-d', strtotime("+{$i} days"));
            $dayOfWeek = (int)date('N', strtotime($date));
            $seasonality = $dayOfWeek >= 6 ? 0.6 : 1.0; // weekends lower
            $projected = max(0, ($avg7 + ($trend * $i / 30)) * $seasonality);
            $forecast[] = ['date' => $date, 'projected' => round($projected, 2), 'low' => round($projected * 0.7, 2), 'high' => round($projected * 1.3, 2)];
        }

        return ['forecast' => $forecast, 'confidence' => count($values) >= 60 ? 'high' : 'medium',
            'avg_7d' => round($avg7, 2), 'avg_30d' => round($avg30, 2), 'trend' => round($trend, 2)];
    }

    public function cashFlowProjection(string $companyId, int $daysAhead = 30): array
    {
        $receivables = db_connect()->query("
            SELECT due_date, SUM(total - COALESCE(paid_total, 0)) AS pending
            FROM sales WHERE company_id = ? AND status = 'confirmed' AND payment_status != 'paid'
            AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
            GROUP BY due_date ORDER BY due_date
        ", [$companyId, $daysAhead])->getResultArray();

        $payables = db_connect()->query("
            SELECT due_date, SUM(total - COALESCE(paid_total, 0)) AS pending
            FROM purchase_invoices WHERE company_id = ? AND status != 'cancelled' AND payment_status != 'paid'
            AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
            GROUP BY due_date ORDER BY due_date
        ", [$companyId, $daysAhead])->getResultArray();

        return ['receivables_schedule' => $receivables, 'payables_schedule' => $payables,
            'total_expected_inflow' => array_sum(array_column($receivables, 'pending')),
            'total_expected_outflow' => array_sum(array_column($payables, 'pending'))];
    }

    private function periodRange(string $period): array
    {
        return match($period) {
            'week' => [date('Y-m-d', strtotime('monday this week')), date('Y-m-d')],
            'month' => [date('Y-m-01'), date('Y-m-d')],
            'quarter' => [date('Y-m-01', strtotime('first day of -2 months')), date('Y-m-d')],
            'year' => [date('Y-01-01'), date('Y-m-d')],
            default => [date('Y-m-01'), date('Y-m-d')],
        };
    }

    private function previousPeriodRange(string $period): array
    {
        return match($period) {
            'week' => [date('Y-m-d', strtotime('monday last week')), date('Y-m-d', strtotime('sunday last week'))],
            'month' => [date('Y-m-01', strtotime('-1 month')), date('Y-m-t', strtotime('-1 month'))],
            'quarter' => [date('Y-m-01', strtotime('-5 months')), date('Y-m-t', strtotime('-3 months'))],
            'year' => [date('Y-01-01', strtotime('-1 year')), date('Y-12-31', strtotime('-1 year'))],
            default => [date('Y-m-01', strtotime('-1 month')), date('Y-m-t', strtotime('-1 month'))],
        };
    }
}
