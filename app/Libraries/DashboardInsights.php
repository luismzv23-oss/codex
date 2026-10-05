<?php

namespace App\Libraries;

use DateTimeImmutable;
use InvalidArgumentException;

class DashboardInsights
{
    private \CodeIgniter\Database\BaseConnection $db;

    public function __construct(?\CodeIgniter\Database\BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    public function filters(array $input, bool $superadmin, ?string $companyId): array
    {
        if (!$superadmin && !$companyId) {
            throw new InvalidArgumentException('Tu usuario no tiene una empresa asignada.');
        }
        $from = (string) ($input['from'] ?? date('Y-m-01'));
        $to = (string) ($input['to'] ?? date('Y-m-d'));
        foreach ([$from, $to] as $date) {
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$parsed || $parsed->format('Y-m-d') !== $date) {
                throw new InvalidArgumentException('Selecciona fechas válidas.');
            }
        }
        $days = (int) (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->format('%r%a') + 1;
        if ($days < 1 || $days > 92) {
            throw new InvalidArgumentException('Selecciona un período de entre 1 y 92 días.');
        }
        $company = $superadmin ? trim((string) ($input['company_id'] ?? '')) : $companyId;
        if ($company && !$this->db->table('companies')->where('id', $company)->countAllResults()) {
            throw new InvalidArgumentException('La empresa seleccionada no está disponible.');
        }
        $currency = strtoupper(trim((string) ($input['currency'] ?? 'ARS')));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Selecciona una moneda válida.');
        }
        return ['from' => $from, 'to' => $to, 'days' => $days, 'company_id' => $company ?: null, 'currency' => $currency];
    }

    public function load(array $f, bool $superadmin): array
    {
        $db = $this->db;
        $scope = static function ($builder, string $column) use ($f) {
            if ($f['company_id']) $builder->where($column, $f['company_id']);
            return $builder;
        };
        // Sales volume, not collections: returns are retained and explicitly labelled in the UI.
        $sales = static fn() => $scope($db->table('sales s'), 's.company_id')
            ->whereIn('s.status', ['confirmed', 'returned_partial', 'returned_total'])
            ->whereIn('s.document_code', ['FACTURA_A', 'FACTURA_B', 'FACTURA_C', 'FACTURA_M', 'TICKET'])
            ->where('s.currency_code', $f['currency']);
        $end = (new DateTimeImmutable($f['to']))->modify('+1 day')->format('Y-m-d');
        $period = static fn() => $sales()->where('s.issue_date >=', $f['from'])->where('s.issue_date <', $end);
        $summary = $period()->select('COUNT(*) AS documents, COALESCE(SUM(s.total), 0) AS amount', false)->get()->getRowArray();
        $previousFrom = (new DateTimeImmutable($f['from']))->modify('-' . $f['days'] . ' days')->format('Y-m-d');
        $previous = (float) ($sales()->select('SUM(s.total) AS amount', false)->where('s.issue_date >=', $previousFrom)->where('s.issue_date <', $f['from'])->get()->getRowArray()['amount'] ?? 0);
        $daily = $period()->select('DATE(s.issue_date) AS day, SUM(s.total) AS amount', false)->groupBy('DATE(s.issue_date)', false)->orderBy('day')->get()->getResultArray();
        $map = array_column($daily, 'amount', 'day');
        $trend = [];
        for ($day = new DateTimeImmutable($f['from']); $day->format('Y-m-d') < $end; $day = $day->modify('+1 day')) {
            $key = $day->format('Y-m-d');
            $trend[] = ['date' => $key, 'label' => $day->format('d/m'), 'amount' => (float) ($map[$key] ?? 0)];
        }
        $receivables = static fn() => $scope($db->table('sales_receivables r'), 'r.company_id')
            ->join('sales s', 's.id = r.sale_id AND s.company_id = r.company_id')
            ->whereIn('r.status', ['pending', 'partial'])->where('s.currency_code', $f['currency']);
        $balance = (float) ($receivables()->select('SUM(r.balance_amount) AS amount', false)->get()->getRowArray()['amount'] ?? 0);
        $overdue = (float) ($receivables()->where('r.due_date <', date('Y-m-d'))->select('SUM(r.balance_amount) AS amount', false)->get()->getRowArray()['amount'] ?? 0);
        $stock = $scope($db->table('inventory_stock_levels l'), 'l.company_id')
            ->join('inventory_products p', 'p.id=l.product_id AND p.company_id=l.company_id')
            ->join('inventory_warehouses w', 'w.id=l.warehouse_id AND w.company_id=l.company_id')
            ->join('companies c', 'c.id=l.company_id')
            ->where('p.active', 1)->where('w.active', 1)
            ->select('p.id, p.name, p.sku, c.name AS company, w.name AS warehouse, SUM(l.quantity-l.reserved_quantity) AS available, MAX(COALESCE(l.min_stock,p.min_stock,0)) AS minimum', false)
            ->groupBy('p.id,p.name,p.sku,w.id,w.name,c.name')
            ->having('SUM(l.quantity-l.reserved_quantity) <= MAX(COALESCE(l.min_stock,p.min_stock,0))', null, false);
        $critical = $stock->get()->getResultArray();
        usort($critical, static fn($a, $b) => (float) $a['available'] <=> (float) $b['available']);
        $fiscal = $sales()->groupStart()->where('s.cae', null)->orWhere('s.cae', '')->groupEnd()->groupStart()
            ->whereIn('s.arca_status', ['pending', 'queued', 'error', 'rejected', 'Pendiente', 'Rechazado'])
            ->groupEnd()->countAllResults();
        $ranking = $period();
        if ($superadmin) {
            $ranking->join('companies c', 'c.id=s.company_id')->select('c.id, c.name, SUM(s.total) AS amount', false)->groupBy('c.id,c.name');
        } else {
            $ranking->join('branches b', 'b.id=s.branch_id AND b.company_id=s.company_id', 'left')->select("COALESCE(b.name, 'Sin sucursal') AS name, SUM(s.total) AS amount", false)->groupBy('b.id,b.name');
        }
        $ranking = $ranking->orderBy('amount', 'DESC')->limit(6)->get()->getResultArray();
        $recent = $period()->join('companies c', 'c.id=s.company_id')
            ->select('s.sale_number,s.issue_date,s.total,s.status,s.arca_status,c.name AS company')
            ->orderBy('s.issue_date', 'DESC')->limit(8)->get()->getResultArray();
        return compact('summary', 'previous', 'trend', 'balance', 'overdue', 'critical', 'fiscal', 'ranking', 'recent') + ['updated' => date('H:i:s'), 'previousFrom' => $previousFrom];
    }
}
