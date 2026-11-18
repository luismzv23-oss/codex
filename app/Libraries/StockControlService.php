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
     * Aprueba las diferencias y registra ajustes reales (documento = número del control).
     * $lines: itemId => ['approved' => bool, 'reason' => texto,
     *   'lots' => [loteId => cantidad] (faltante; si no se indica, se descuenta primero del lote que vence antes),
     *   'serials' => [número de serie, ...] (faltante; obligatorio solo si hay series disponibles suficientes),
     *   'lot_number' => texto, 'expiration_date' => Y-m-d (sobrante)].
     * Las líneas no aprobadas no mueven stock.
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
                $difference = round((float) $item['difference'], 2);
                if ($approved && abs($difference) >= 0.005) {
                    if ($reason === '') {
                        throw new RuntimeException('Indica el motivo de la diferencia de ' . $item['sku'] . '.');
                    }
                    $movementIds = [];
                    foreach ($this->adjustmentParts($companyId, $count, $item, $line, $difference) as $part) {
                        $integrity->changeStock($companyId, $item['product_id'], $count['warehouse_id'], $difference > 0 ? $part['quantity'] : -$part['quantity'], $part['location_id']);
                        $movement = ['company_id' => $companyId, 'product_id' => $item['product_id'], 'movement_type' => 'ajuste',
                            'adjustment_mode' => $difference > 0 ? 'increase' : 'decrease', 'quantity' => $part['quantity'], 'unit_cost' => $item['unit_cost'],
                            'total_cost' => round($part['quantity'] * (float) $item['unit_cost'], 2), 'source_warehouse_id' => $count['warehouse_id'],
                            'source_location_id' => $part['location_id'], 'lot_number' => $part['lot_number'], 'serial_number' => $part['serial_number'],
                            'expiration_date' => $part['expiration_date'], 'performed_by' => $userId, 'occurred_at' => date('Y-m-d H:i:s'),
                            'reason' => self::CONTROL_REASON, 'source_document' => $count['count_number'], 'notes' => trim($reason . ' ' . $part['note'])];
                        $movementId = (new InventoryMovementModel())->insert($movement, true);
                        if (! $movementId) {
                            throw new RuntimeException('No se pudo registrar el ajuste de ' . $item['sku'] . '.');
                        }
                        (new InventoryArtifactService())->sync($companyId, $item['product_id'], (string) $movementId, $movement);
                        $movementIds[] = $movementId;
                    }
                    $update['movement_id'] = $movementIds[0] ?? null;
                    $total += (float) $item['difference_value'];
                }
                $db->table('inventory_count_items')->where('id', $item['id'])->update($update);
            }
            $db->table('inventory_counts')->where('id', $countId)->update(['status' => 'approved', 'approved_by' => $userId, 'approved_at' => date('Y-m-d H:i:s'),
                'difference_value' => round($total, 2), 'updated_at' => date('Y-m-d H:i:s')]);

            return $this->get($companyId, $countId);
        });
    }

    /** Lotes con saldo y series disponibles de cada producto con trazabilidad, en el depósito del control. */
    public function traceOptions(string $companyId, array $count): array
    {
        $db = db_connect();
        $options = [];
        foreach ($count['items'] as $item) {
            if ((int) $item['lot_control'] !== 1 && (int) $item['serial_control'] !== 1) {
                continue;
            }
            $lots = (int) $item['lot_control'] === 1 ? $db->table('inventory_lots')->select('id, lot_number, location_id, expiration_date, quantity_balance')
                ->where('company_id', $companyId)->where('product_id', $item['product_id'])->where('warehouse_id', $count['warehouse_id'])->where('quantity_balance >', 0)
                ->orderBy('expiration_date IS NULL', 'ASC', false)->orderBy('expiration_date', 'ASC')->orderBy('lot_number', 'ASC')->get()->getResultArray() : [];
            $serials = (int) $item['serial_control'] === 1 ? $db->table('inventory_serials')->select('serial_number, lot_number, location_id')
                ->where('company_id', $companyId)->where('product_id', $item['product_id'])->where('warehouse_id', $count['warehouse_id'])->where('status', 'available')
                ->orderBy('serial_number')->get()->getResultArray() : [];
            $need = $item['difference'] !== null && (float) $item['difference'] < 0 ? abs((float) $item['difference']) : 0.0;
            $options[$item['product_id']] = ['lots' => $lots, 'serials' => $serials, 'need' => $need,
                'serials_required' => $need > 0 && $serials !== [] && count($serials) >= $need && fmod($need, 1.0) === 0.0];
        }

        return $options;
    }

    /** Parte la diferencia de una línea en movimientos: uno por serie, uno por lote y el resto sin lote. */
    private function adjustmentParts(string $companyId, array $count, array $item, array $line, float $difference): array
    {
        $part = static fn(float $qty, ?string $lot = null, ?string $location = null, ?string $serial = null, ?string $expiration = null, string $note = '') =>
            ['quantity' => round($qty, 2), 'lot_number' => $lot, 'location_id' => $location, 'serial_number' => $serial, 'expiration_date' => $expiration, 'note' => $note];
        $lotControl = (int) $item['lot_control'] === 1;
        $serialControl = (int) $item['serial_control'] === 1;
        if (! $lotControl && ! $serialControl) {
            return [$part(abs($difference))];
        }
        if ($difference > 0) {
            // Sobrante: entra a un lote (por defecto, el número del control); no se generan números de serie.
            $lot = $lotControl ? (mb_substr(trim((string) ($line['lot_number'] ?? '')), 0, 60) ?: $count['count_number']) : null;
            $expiration = trim((string) ($line['expiration_date'] ?? ''));
            $expiration = preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiration) ? $expiration : null;

            return [$part($difference, $lot, null, null, $expiration, $serialControl ? '(sobrante sin números de serie)' : '')];
        }

        $need = abs($difference);
        $options = $this->traceOptions($companyId, ['warehouse_id' => $count['warehouse_id'], 'items' => [$item]])[$item['product_id']];
        $parts = [];
        if ($options['serials_required']) {
            $chosen = array_values(array_unique(array_filter(array_map('trim', (array) ($line['serials'] ?? [])))));
            if (count($chosen) !== (int) $need) {
                throw new RuntimeException('Elige ' . (int) $need . ' números de serie faltantes de ' . $item['sku'] . ' (elegiste ' . count($chosen) . ').');
            }
            $available = array_column($options['serials'], null, 'serial_number');
            $lotBalance = [];
            foreach ($options['lots'] as $lot) {
                $lotBalance[$lot['lot_number'] . '|' . ($lot['location_id'] ?? '')] = (float) $lot['quantity_balance'];
            }
            foreach ($chosen as $serial) {
                if (! isset($available[$serial])) {
                    throw new RuntimeException('La serie ' . $serial . ' de ' . $item['sku'] . ' no está disponible en el depósito.');
                }
                $row = $available[$serial];
                $key = ($row['lot_number'] ?? '') . '|' . ($row['location_id'] ?? '');
                $lot = $lotControl && ($lotBalance[$key] ?? 0) >= 1 ? $row['lot_number'] : null;
                if ($lot !== null) {
                    $lotBalance[$key]--;
                }
                $parts[] = $part(1, $lot, $row['location_id'] ?? null, $serial);
            }

            return $parts;
        }

        $note = $serialControl ? '(series no cargadas por unidad)' : '';
        if (! $lotControl) {
            return [$part($need, null, null, null, null, $note)];
        }
        $lotsById = array_column($options['lots'], null, 'id');
        $requested = array_filter(array_map('floatval', (array) ($line['lots'] ?? [])), static fn($q) => $q > 0);
        if ($requested === []) {
            // Sin reparto indicado: se descuenta primero del lote que vence antes.
            $left = $need;
            foreach ($options['lots'] as $lot) {
                $take = min($left, (float) $lot['quantity_balance']);
                if ($take > 0) {
                    $requested[$lot['id']] = $take;
                    $left -= $take;
                }
            }
        }
        $assigned = 0.0;
        foreach ($requested as $lotId => $qty) {
            $lot = $lotsById[$lotId] ?? null;
            if (! $lot) {
                throw new RuntimeException('El lote indicado para ' . $item['sku'] . ' no tiene saldo en el depósito.');
            }
            if ($qty > (float) $lot['quantity_balance'] + 0.001) {
                throw new RuntimeException('El lote ' . $lot['lot_number'] . ' de ' . $item['sku'] . ' tiene saldo ' . (float) $lot['quantity_balance'] . '.');
            }
            $parts[] = $part($qty, $lot['lot_number'], $lot['location_id'] ?? null, null, null, $note);
            $assigned += $qty;
        }
        if ($assigned > $need + 0.001) {
            throw new RuntimeException('El reparto por lote de ' . $item['sku'] . ' (' . $assigned . ') supera el faltante (' . $need . ').');
        }
        if ($need - $assigned >= 0.005) {
            $parts[] = $part($need - $assigned, null, null, null, null, trim($note . ' (sin lote: los lotes no cubrían el faltante)'));
        }

        return $parts;
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
        $physical = static fn($builder) => $builder->groupStart()->where('p.product_type', 'simple')->orWhere('p.product_type', null)->groupEnd();
        $stockRows = function (string $op, int|float $value, bool $rotation = false) use ($db, $companyId, $stock, $physical, $noRotation) {
            $q = $db->table('inventory_products p')->select('p.id AS product_id, p.sku, p.name, p.unit, st.q AS stock, ROUND(st.q * COALESCE(p.cost_price, 0), 2) AS value', false)
                ->join("($stock) st", 'st.product_id = p.id')->where('p.company_id', $companyId)->where('p.active', 1)->where("st.q {$op}", $value);
            $physical($q);
            if ($rotation) {
                $q->where("NOT EXISTS (SELECT 1 FROM {$db->prefixTable('inventory_movements')} m WHERE m.product_id = p.id AND m.movement_type = 'egreso' AND m.occurred_at >= " . $db->escape(date('Y-m-d H:i:s', strtotime("-{$noRotation} days"))) . ')', null, false);
            }

            return $q->orderBy('st.q', $rotation ? 'DESC' : 'ASC')->get()->getResultArray();
        };
        $customerColumn = $db->fieldExists('customer_name_snapshot', 'sales') ? 's.customer_name_snapshot' : "''";
        $paidDrafts = $db->table('sales s')->select("s.id, s.sale_number, s.issue_date, {$customerColumn} AS customer, SUM(p.amount) AS paid, u.name AS user_name", false)
            ->join('sale_payments p', 'p.sale_id = s.id')->join('users u', 'u.id = s.created_by', 'left')
            ->where('s.company_id', $companyId)->where('s.status', 'draft')->where('p.status', 'registered')->where('s.created_at >=', $since)
            ->groupBy("s.id, s.sale_number, s.issue_date, {$customerColumn}, u.name", false)->orderBy('s.issue_date', 'DESC')->get()->getResultArray();
        $counts = $db->table('inventory_counts c')->select('c.id, c.count_number, c.week, c.status, c.items_count, c.difference_value, c.approved_at, w.name AS warehouse_name')
            ->join('inventory_warehouses w', 'w.id = c.warehouse_id', 'left')->where('c.company_id', $companyId)->where('c.status !=', 'cancelled')
            ->orderBy('c.created_at', 'DESC')->limit(12)->get()->getResultArray();
        $manual = $this->manualEntries($companyId, $days);
        $rotation = $stockRows('>', 0, true);
        $negative = $stockRows('<', 0);
        $lastApproved = array_values(array_filter($counts, static fn($c) => $c['status'] === 'approved'))[0] ?? null;
        $report = [
            'week' => self::week(), 'generated_at' => date('Y-m-d H:i:s'), 'days' => $days, 'since' => $since,
            'counts' => $counts, 'manual_entries' => $manual, 'paid_drafts' => $paidDrafts, 'no_rotation' => $rotation, 'negative_stock' => $negative,
            'coverage' => ['catalog' => $catalog, 'counted_28_days' => $countedMonth, 'percent' => $catalog ? (int) round($countedMonth / $catalog * 100) : 0],
            'no_rotation_days' => $noRotation,
            'summary' => [
                'manual_units' => round(array_sum(array_column($manual, 'quantity')), 2),
                'paid_total' => round(array_sum(array_column($paidDrafts, 'paid')), 2),
                'no_rotation_value' => round(array_sum(array_column($rotation, 'value')), 2),
                'negative_units' => round(array_sum(array_column($negative, 'stock')), 2),
                'last_difference' => $lastApproved ? (float) $lastApproved['difference_value'] : null,
                'last_count' => $lastApproved['count_number'] ?? null,
            ],
        ];
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
