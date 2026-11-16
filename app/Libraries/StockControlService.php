<?php

namespace App\Libraries;

use App\Models\InventoryMovementModel;
use RuntimeException;

/**
 * Weekly stock control: cyclic blind count by priority (alerts, movement, longest without counting),
 * difference review with approval that posts real adjustments (CTRL-…), and a weekly alert report.
 */
class StockControlService
{
    public const MANUAL_REASONS = ['Ajuste manual de stock', 'Ajuste de stock desde edición de producto', 'Stock inicial al registrar producto', 'INGRESO'];
    public const CONTROL_REASON = 'Control semanal';

    public static function week(?int $time = null): string
    {
        return date('o-\WW', $time ?? time());
    }

    // ---- Selection ---------------------------------------------------------------------------------

    /** Countable products ranked: alerts first, then most movement in 7 days, then longest without counting. */
    public function select(string $companyId, string $warehouseId, ?int $limit = null): array
    {
        $db = db_connect();
        $products = $db->table('inventory_products')->select('id, sku, name, cost_price')->where('company_id', $companyId)->where('active', 1)
            ->groupStart()->where('product_type', 'simple')->orWhere('product_type', null)->groupEnd()->get()->getResultArray();
        if (! $products) {
            return [];
        }
        $since = date('Y-m-d H:i:s', strtotime('-7 days'));
        $alerts = array_fill_keys(array_keys($this->alertProducts($companyId, $warehouseId)), true);
        $moves = array_column($db->table('inventory_movements')->select('product_id, COUNT(*) n')->where('company_id', $companyId)
            ->where('occurred_at >=', $since)->groupBy('product_id')->get()->getResultArray(), 'n', 'product_id');
        $lastCounted = array_column($db->table('inventory_count_items i')->select('i.product_id, MAX(i.counted_at) last')
            ->join('inventory_counts c', 'c.id = i.count_id')->where('c.company_id', $companyId)->where('c.status', 'approved')
            ->groupBy('i.product_id')->get()->getResultArray(), 'last', 'product_id');
        foreach ($products as &$product) {
            $product['alert'] = isset($alerts[$product['id']]);
            $product['moves'] = (int) ($moves[$product['id']] ?? 0);
            $product['last_counted'] = $lastCounted[$product['id']] ?? null;
            $product['reason'] = $product['alert'] ? 'alerta' : ($product['moves'] > 0 ? 'movimiento' : 'rotacion');
        }
        unset($product);
        usort($products, static fn($a, $b) => [$b['alert'], $b['moves'], $a['last_counted'] ?? '0000'] <=> [$a['alert'], $a['moves'], $b['last_counted'] ?? '0000']);
        $limit ??= $this->size($companyId, count($products));

        return array_slice($products, 0, max(1, $limit));
    }

    /** Weekly list size: configured, or a quarter of the countable catalog so everything is counted monthly. */
    public function size(string $companyId, int $catalog): int
    {
        $db = db_connect();
        $configured = $db->fieldExists('cycle_count_size', 'inventory_settings')
            ? (int) ($db->table('inventory_settings')->select('cycle_count_size')->where('company_id', $companyId)->get()->getRowArray()['cycle_count_size'] ?? 0) : 0;

        return $configured > 0 ? $configured : (int) max(1, ceil($catalog / 4));
    }

    /** Products with a stock alert (id => labels): manual entries without voucher, unpaid-out drafts, negative stock. */
    public function alertProducts(string $companyId, ?string $warehouseId = null, int $days = 7): array
    {
        $db = db_connect();
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $alerts = [];
        foreach ($this->manualEntries($companyId, $days) as $row) {
            $alerts[$row['product_id']][] = 'entrada sin comprobante';
        }
        foreach ($db->table('sale_items si')->select('si.product_id')->join('sales s', 's.id = si.sale_id')->join('sale_payments p', 'p.sale_id = s.id')
            ->where('s.company_id', $companyId)->where('s.status', 'draft')->where('p.status', 'registered')->where('s.created_at >=', $since)
            ->groupBy('si.product_id')->get()->getResultArray() as $row) {
            $alerts[$row['product_id']][] = 'venta en borrador cobrada';
        }
        $negative = $db->table('inventory_stock_levels')->select('product_id')->where('company_id', $companyId);
        if ($warehouseId) {
            $negative->where('warehouse_id', $warehouseId);
        }
        foreach ($negative->groupBy('product_id')->having('SUM(quantity) <', 0)->get()->getResultArray() as $row) {
            $alerts[$row['product_id']][] = 'stock negativo';
        }

        return $alerts;
    }

    // ---- Count lifecycle -----------------------------------------------------------------------------

    /** Starts (or returns) this week's control for a warehouse with the prioritized product list. */
    public function start(string $companyId, string $warehouseId, string $userId): array
    {
        return (new InventoryIntegrityService())->transaction($companyId, function () use ($companyId, $warehouseId, $userId) {
            $db = db_connect();
            if (! $db->table('inventory_warehouses')->where('company_id', $companyId)->where('id', $warehouseId)->where('active', 1)->countAllResults()) {
                throw new RuntimeException('Depósito no disponible.');
            }
            $week = self::week();
            $existing = $db->table('inventory_counts')->where('company_id', $companyId)->where('warehouse_id', $warehouseId)->where('week', $week)
                ->whereIn('status', ['open', 'counted'])->get()->getRowArray();
            if ($existing) {
                return $this->get($companyId, $existing['id']);
            }
            $selection = $this->select($companyId, $warehouseId);
            if (! $selection) {
                throw new RuntimeException('No hay productos para contar en esta empresa.');
            }
            $id = app_uuid();
            $now = date('Y-m-d H:i:s');
            $db->table('inventory_counts')->insert(['id' => $id, 'company_id' => $companyId, 'warehouse_id' => $warehouseId,
                'count_number' => (new VoucherSequenceService())->next($companyId, 'CTRLINV', 'CTRL', null, false), 'week' => $week, 'status' => 'open',
                'items_count' => count($selection), 'difference_value' => 0, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now]);
            foreach ($selection as $product) {
                $db->table('inventory_count_items')->insert(['id' => app_uuid(), 'count_id' => $id, 'product_id' => $product['id'], 'selection_reason' => $product['reason'],
                    'approved' => 0, 'moved_after_count' => 0, 'created_at' => $now, 'updated_at' => $now]);
            }

            return $this->get($companyId, $id);
        });
    }

    public function get(string $companyId, string $countId): array
    {
        $db = db_connect();
        $count = $db->table('inventory_counts c')->select('c.*, w.name AS warehouse_name')->join('inventory_warehouses w', 'w.id = c.warehouse_id', 'left')
            ->where('c.company_id', $companyId)->where('c.id', $countId)->get()->getRowArray();
        if (! $count) {
            throw new RuntimeException('Control no disponible.');
        }
        $count['items'] = $db->table('inventory_count_items i')->select('i.*, p.sku, p.name, p.unit, p.lot_control, p.serial_control')
            ->join('inventory_products p', 'p.id = i.product_id')->where('i.count_id', $countId)->orderBy('p.name')->get()->getResultArray();

        return $count;
    }

    /** Records the counted quantity; snapshots the system stock at that moment. Products outside the list can be added. */
    public function record(string $companyId, string $countId, string $productId, $counted): array
    {
        if (! is_numeric($counted) || (float) $counted < 0) {
            throw new RuntimeException('La cantidad contada debe ser un número mayor o igual a cero.');
        }
        return (new InventoryIntegrityService())->transaction($companyId, function () use ($companyId, $countId, $productId, $counted) {
            $db = db_connect();
            $count = $this->get($companyId, $countId);
            if ($count['status'] !== 'open') {
                throw new RuntimeException('El control ya no admite conteos.');
            }
            if (! $db->table('inventory_products')->where('company_id', $companyId)->where('id', $productId)->countAllResults()) {
                throw new RuntimeException('Producto no disponible.');
            }
            $system = $this->stock($companyId, $productId, $count['warehouse_id']);
            $cost = $this->unitCost($companyId, $productId, $count['warehouse_id']);
            $difference = round((float) $counted - $system, 2);
            $data = ['system_quantity' => $system, 'counted_quantity' => round((float) $counted, 2), 'difference' => $difference, 'unit_cost' => $cost,
                'difference_value' => round($difference * $cost, 2), 'counted_at' => date('Y-m-d H:i:s'), 'moved_after_count' => 0, 'updated_at' => date('Y-m-d H:i:s')];
            $item = $db->table('inventory_count_items')->where('count_id', $countId)->where('product_id', $productId)->get()->getRowArray();
            if ($item) {
                $db->table('inventory_count_items')->where('id', $item['id'])->update($data);
            } else {
                $db->table('inventory_count_items')->insert($data + ['id' => app_uuid(), 'count_id' => $countId, 'product_id' => $productId, 'selection_reason' => 'agregado',
                    'approved' => 0, 'created_at' => date('Y-m-d H:i:s')]);
                $db->table('inventory_counts')->where('id', $countId)->update(['items_count' => $count['items_count'] + 1]);
            }

            return $this->get($companyId, $countId);
        });
    }

    /** Closes the count: flags products that moved after being counted and totals the differences. */
    public function close(string $companyId, string $countId): array
    {
        return (new InventoryIntegrityService())->transaction($companyId, function () use ($companyId, $countId) {
            $db = db_connect();
            $count = $this->get($companyId, $countId);
            if ($count['status'] !== 'open') {
                throw new RuntimeException('El control ya está cerrado.');
            }
            $counted = array_filter($count['items'], static fn($i) => $i['counted_at'] !== null);
            if (! $counted) {
                throw new RuntimeException('Carga al menos un conteo antes de cerrar.');
            }
            $total = 0.0;
            foreach ($counted as $item) {
                $moved = $db->table('inventory_movements')->where('company_id', $companyId)->where('product_id', $item['product_id'])->where('occurred_at >', $item['counted_at'])
                    ->groupStart()->where('source_warehouse_id', $count['warehouse_id'])->orWhere('destination_warehouse_id', $count['warehouse_id'])->groupEnd()->countAllResults();
                $db->table('inventory_count_items')->where('id', $item['id'])->update(['moved_after_count' => $moved > 0 ? 1 : 0]);
                $total += (float) $item['difference_value'];
            }
            $db->table('inventory_counts')->where('id', $countId)->update(['status' => 'counted', 'difference_value' => round($total, 2), 'updated_at' => date('Y-m-d H:i:s')]);

            return $this->get($companyId, $countId);
        });
    }

    /**
     * Approves differences and posts real adjustments (source document = count number).
     * $lines: itemId => ['approved' => bool, 'reason' => text]. Unapproved lines do not move stock.
     */
    public function approve(string $companyId, string $countId, array $lines, string $userId): array
    {
        return (new InventoryIntegrityService())->transaction($companyId, function () use ($companyId, $countId, $lines, $userId) {
            $db = db_connect();
            $count = $this->get($companyId, $countId);
            if ($count['status'] !== 'counted') {
                throw new RuntimeException('Cierra el conteo antes de aprobar las diferencias.');
            }
            $integrity = new InventoryIntegrityService();
            $total = 0.0;
            foreach ($count['items'] as $item) {
                if ($item['counted_at'] === null) {
                    continue;
                }
                $line = $lines[$item['id']] ?? [];
                $approved = ! empty($line['approved']);
                $reason = mb_substr(trim((string) ($line['reason'] ?? '')), 0, 120);
                $update = ['approved' => $approved ? 1 : 0, 'reason' => $reason ?: null, 'updated_at' => date('Y-m-d H:i:s')];
                $difference = (float) $item['difference'];
                if ($approved && abs($difference) >= 0.005) {
                    if ((int) $item['lot_control'] === 1 || (int) $item['serial_control'] === 1) {
                        throw new RuntimeException($item['sku'] . ' controla lote o serie: ajusta esa diferencia desde Movimiento de stock indicando el lote o la serie.');
                    }
                    if ($reason === '') {
                        throw new RuntimeException('Indica el motivo de la diferencia de ' . $item['sku'] . '.');
                    }
                    $integrity->changeStock($companyId, $item['product_id'], $count['warehouse_id'], $difference);
                    $movement = ['company_id' => $companyId, 'product_id' => $item['product_id'], 'movement_type' => 'ajuste',
                        'adjustment_mode' => $difference > 0 ? 'increase' : 'decrease', 'quantity' => abs($difference), 'unit_cost' => $item['unit_cost'],
                        'total_cost' => round(abs($difference) * (float) $item['unit_cost'], 2), 'source_warehouse_id' => $count['warehouse_id'],
                        'performed_by' => $userId, 'occurred_at' => date('Y-m-d H:i:s'), 'reason' => self::CONTROL_REASON,
                        'source_document' => $count['count_number'], 'notes' => $reason];
                    $movementId = (new InventoryMovementModel())->insert($movement, true);
                    if (! $movementId) {
                        throw new RuntimeException('No se pudo registrar el ajuste de ' . $item['sku'] . '.');
                    }
                    (new InventoryArtifactService())->sync($companyId, $item['product_id'], (string) $movementId, $movement);
                    $update['movement_id'] = $movementId;
                    $total += (float) $item['difference_value'];
                }
                $db->table('inventory_count_items')->where('id', $item['id'])->update($update);
            }
            $db->table('inventory_counts')->where('id', $countId)->update(['status' => 'approved', 'approved_by' => $userId, 'approved_at' => date('Y-m-d H:i:s'),
                'difference_value' => round($total, 2), 'updated_at' => date('Y-m-d H:i:s')]);

            return $this->get($companyId, $countId);
        });
    }

    public function cancel(string $companyId, string $countId): void
    {
        $count = $this->get($companyId, $countId);
        if ($count['status'] === 'approved') {
            throw new RuntimeException('Un control aprobado no se puede anular.');
        }
        db_connect()->table('inventory_counts')->where('id', $countId)->update(['status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')]);
    }

    public function history(string $companyId, int $limit = 20): array
    {
        return db_connect()->table('inventory_counts c')->select('c.*, w.name AS warehouse_name')->join('inventory_warehouses w', 'w.id = c.warehouse_id', 'left')
            ->where('c.company_id', $companyId)->orderBy('c.created_at', 'DESC')->limit($limit)->get()->getResultArray();
    }

    // ---- Weekly report -------------------------------------------------------------------------------

    public function report(string $companyId, int $days = 7, ?string $userId = null): array
    {
        $db = db_connect();
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $settings = $db->table('inventory_settings')->where('company_id', $companyId)->get()->getRowArray() ?? [];
        $noRotation = max(1, (int) ($settings['no_rotation_days'] ?? 30));
        $catalog = $db->table('inventory_products')->where('company_id', $companyId)->where('active', 1)->groupStart()->where('product_type', 'simple')->orWhere('product_type', null)->groupEnd()->countAllResults();
        $countedMonth = $db->table('inventory_count_items i')->select('i.product_id')->distinct()->join('inventory_counts c', 'c.id = i.count_id')
            ->where('c.company_id', $companyId)->where('c.status', 'approved')->where('i.counted_at >=', date('Y-m-d H:i:s', strtotime('-28 days')))->countAllResults();
        $stock = $db->table('inventory_stock_levels')->select('product_id, SUM(quantity) q')->where('company_id', $companyId)->groupBy('product_id')->getCompiledSelect();
        $report = [
            'week' => self::week(), 'generated_at' => date('Y-m-d H:i:s'), 'days' => $days,
            'counts' => $db->table('inventory_counts')->select('count_number, week, status, items_count, difference_value, approved_at')->where('company_id', $companyId)
                ->where('status !=', 'cancelled')->orderBy('created_at', 'DESC')->limit(8)->get()->getResultArray(),
            'manual_entries' => $this->manualEntries($companyId, $days),
            'paid_drafts' => $db->table('sales s')->select('s.sale_number, s.issue_date, SUM(p.amount) AS paid, u.name AS user_name')->join('sale_payments p', 'p.sale_id = s.id')
                ->join('users u', 'u.id = s.created_by', 'left')->where('s.company_id', $companyId)->where('s.status', 'draft')->where('p.status', 'registered')
                ->groupBy('s.id, s.sale_number, s.issue_date, u.name')->orderBy('s.issue_date', 'DESC')->get()->getResultArray(),
            'no_rotation' => $db->table('inventory_products p')->select('p.sku, p.name, st.q AS stock')->join("($stock) st", 'st.product_id = p.id')
                ->where('p.company_id', $companyId)->where('p.active', 1)->where('st.q >', 0)
                ->where("NOT EXISTS (SELECT 1 FROM {$db->prefixTable('inventory_movements')} m WHERE m.product_id = p.id AND m.movement_type = 'egreso' AND m.occurred_at >= " . $db->escape(date('Y-m-d H:i:s', strtotime("-{$noRotation} days"))) . ')', null, false)
                ->orderBy('st.q', 'DESC')->limit(50)->get()->getResultArray(),
            'negative_stock' => $db->table('inventory_products p')->select('p.sku, p.name, st.q AS stock')->join("($stock) st", 'st.product_id = p.id')
                ->where('p.company_id', $companyId)->where('st.q <', 0)->orderBy('st.q')->get()->getResultArray(),
            'coverage' => ['catalog' => $catalog, 'counted_28_days' => $countedMonth, 'percent' => $catalog ? (int) round($countedMonth / $catalog * 100) : 0],
            'no_rotation_days' => $noRotation,
        ];
        $report['since'] = $since;
        $existing = $db->table('inventory_count_reports')->where('company_id', $companyId)->where('week', $report['week'])->get()->getRowArray();
        $row = ['report_json' => json_encode($report, JSON_UNESCAPED_UNICODE), 'created_by' => $userId, 'updated_at' => date('Y-m-d H:i:s')];
        if ($existing) {
            $db->table('inventory_count_reports')->where('id', $existing['id'])->update($row);
        } else {
            $db->table('inventory_count_reports')->insert($row + ['id' => app_uuid(), 'company_id' => $companyId, 'week' => $report['week'], 'created_at' => date('Y-m-d H:i:s')]);
        }

        return $report;
    }

    /** Stock entries without a voucher (manual adjustments, product edits) in the last $days. */
    public function manualEntries(string $companyId, int $days = 7): array
    {
        $db = db_connect();

        return $db->table('inventory_movements m')->select('m.product_id, p.sku, p.name, m.quantity, m.reason, m.occurred_at, u.name AS user_name')
            ->join('inventory_products p', 'p.id = m.product_id')->join('users u', 'u.id = m.performed_by', 'left')
            ->where('m.company_id', $companyId)->where('m.occurred_at >=', date('Y-m-d H:i:s', strtotime("-{$days} days")))
            ->groupStart()->where('m.movement_type', 'ingreso')->orGroupStart()->where('m.movement_type', 'ajuste')->where('m.adjustment_mode', 'increase')->groupEnd()->groupEnd()
            ->whereIn('m.reason', self::MANUAL_REASONS)->orderBy('m.occurred_at', 'DESC')->get()->getResultArray();
    }

    private function stock(string $companyId, string $productId, string $warehouseId): float
    {
        return round((float) (db_connect()->table('inventory_stock_levels')->selectSum('quantity', 'q')->where('company_id', $companyId)
            ->where('product_id', $productId)->where('warehouse_id', $warehouseId)->get()->getRowArray()['q'] ?? 0), 2);
    }

    /** Average cost of open cost layers in the warehouse, falling back to the product cost price. */
    private function unitCost(string $companyId, string $productId, string $warehouseId): float
    {
        $db = db_connect();
        $layers = $db->table('inventory_cost_layers')->select('SUM(remaining_quantity) q, SUM(remaining_quantity * unit_cost) v', false)
            ->where('company_id', $companyId)->where('product_id', $productId)->where('warehouse_id', $warehouseId)->where('remaining_quantity >', 0)->get()->getRowArray();
        if ((float) ($layers['q'] ?? 0) > 0) {
            return round((float) $layers['v'] / (float) $layers['q'], 4);
        }

        return round((float) ($db->table('inventory_products')->select('cost_price')->where('id', $productId)->get()->getRowArray()['cost_price'] ?? 0), 4);
    }
}
