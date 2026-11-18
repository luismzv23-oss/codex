<?php

namespace App\Libraries;

use RuntimeException;

/**
 * Análisis inteligente de inventario para optimizar las compras.
 *
 * Por cada producto simple activo:
 *  - Demanda real: egresos de stock por ventas/remitos (incluye componentes de kits) menos devoluciones y anulaciones,
 *    agrupada en semanas cerradas hacia atrás desde hoy.
 *  - Pronóstico: Holt amortiguado (nivel + tendencia) para demanda regular; Croston-SBA para demanda intermitente.
 *  - Plazo de entrega: el observado entre emisión de la OC y recepción; si no hay historia, el del producto, el del
 *    proveedor o el valor por defecto.
 *  - Política de revisión periódica (R, S): stock de seguridad por nivel de servicio, punto de pedido y nivel objetivo.
 *  - Clasificación ABC (valor anual consumido) y XYZ (variabilidad), sobrestock y stock inmovilizado.
 */
class ReplenishmentAnalysisService
{
    public const DEFAULTS = ['history_days' => 90, 'service_level' => 95, 'review_days' => 7, 'default_lead_time' => 7, 'overstock_days' => 90];
    public const HISTORY_OPTIONS = [30, 60, 90, 180, 365];
    public const SERVICE_LEVELS = [90, 95, 97.5, 99];
    private const OPEN_ORDER_STATUSES = ['draft', 'approved', 'received_partial'];
    private const RETURN_REASONS = ['DEVOLUCION VENTA', 'ANULACION VENTA'];

    // ───────────────────────────── Núcleo matemático (puro) ─────────────────────────────

    public static function normalizeParams(array $input): array
    {
        $history = (int) ($input['history_days'] ?? self::DEFAULTS['history_days']);
        $service = (float) ($input['service_level'] ?? self::DEFAULTS['service_level']);
        $review = (int) ($input['review_days'] ?? self::DEFAULTS['review_days']);
        $leadTime = (int) ($input['default_lead_time'] ?? self::DEFAULTS['default_lead_time']);

        return [
            'history_days' => in_array($history, self::HISTORY_OPTIONS, true) ? $history : self::DEFAULTS['history_days'],
            'service_level' => in_array($service, self::SERVICE_LEVELS, false) ? $service : self::DEFAULTS['service_level'],
            'review_days' => max(1, min(60, $review)),
            'default_lead_time' => max(1, min(120, $leadTime)),
            'overstock_days' => self::DEFAULTS['overstock_days'],
            'warehouse_id' => trim((string) ($input['warehouse_id'] ?? '')) ?: null,
        ];
    }

    /** Factor z de la normal estándar para el nivel de servicio (ciclo) indicado. */
    public static function zScore(float $serviceLevel): float
    {
        $table = ['90.0' => 1.2816, '95.0' => 1.6449, '97.5' => 1.96, '99.0' => 2.3263];

        return $table[number_format($serviceLevel, 1, '.', '')] ?? 1.6449;
    }

    /**
     * Pronóstico de demanda semanal a partir de una serie cronológica (más antigua primero).
     * Devuelve weekly (unidades/semana), sigma (desvío semanal), method, mean, cv y trend_pct.
     */
    public static function forecast(array $series): array
    {
        $series = array_values(array_map(static fn($v): float => max(0.0, (float) $v), $series));
        $n = count($series);
        $nonZero = count(array_filter($series, static fn(float $v): bool => $v > 0));
        if ($n === 0 || $nonZero === 0) {
            return ['weekly' => 0.0, 'sigma' => 0.0, 'method' => 'sin_demanda', 'mean' => 0.0, 'cv' => 0.0, 'trend_pct' => 0.0];
        }

        $mean = array_sum($series) / $n;
        $variance = $n > 1 ? array_sum(array_map(static fn(float $v): float => ($v - $mean) ** 2, $series)) / ($n - 1) : 0.0;
        $sigma = sqrt($variance);
        $adi = $n / $nonZero; // intervalo medio entre demandas

        if ($nonZero < 3) {
            $weekly = $mean;
            $method = 'promedio';
        } elseif ($adi > 1.32) {
            $weekly = self::crostonSba($series);
            $method = 'croston';
        } else {
            $weekly = self::holtDamped($series);
            $method = 'holt';
        }

        $recentWindow = min(4, intdiv($n, 2));
        $trend = 0.0;
        if ($recentWindow >= 2) {
            $recent = array_sum(array_slice($series, -$recentWindow)) / $recentWindow;
            $before = array_sum(array_slice($series, 0, $n - $recentWindow)) / ($n - $recentWindow);
            $trend = $before > 0 ? ($recent - $before) / $before * 100 : ($recent > 0 ? 100.0 : 0.0);
        }

        return [
            'weekly' => round(max(0.0, $weekly), 4),
            'sigma' => round($sigma, 4),
            'method' => $method,
            'mean' => round($mean, 4),
            'cv' => $mean > 0 ? round($sigma / $mean, 4) : 0.0,
            'trend_pct' => round($trend, 1),
        ];
    }

    /** Holt con tendencia amortiguada: pronóstico de la próxima semana. */
    public static function holtDamped(array $series, float $alpha = 0.3, float $beta = 0.1, float $phi = 0.9): float
    {
        $n = count($series);
        $init = min(4, $n);
        $level = array_sum(array_slice($series, 0, $init)) / $init;
        $trend = $n > $init ? ((array_sum(array_slice($series, $init, $init)) / max(1, count(array_slice($series, $init, $init)))) - $level) / $init : 0.0;
        for ($t = $init; $t < $n; $t++) {
            $previousLevel = $level;
            $level = $alpha * $series[$t] + (1 - $alpha) * ($previousLevel + $phi * $trend);
            $trend = $beta * ($level - $previousLevel) + (1 - $beta) * $phi * $trend;
        }

        return max(0.0, $level + $phi * $trend);
    }

    /** Croston con corrección Syntetos-Boylan para demanda intermitente. */
    public static function crostonSba(array $series, float $alpha = 0.15): float
    {
        $size = null;
        $interval = null;
        $gap = 1;
        foreach ($series as $value) {
            if ($value > 0) {
                $size = $size === null ? $value : $alpha * $value + (1 - $alpha) * $size;
                $interval = $interval === null ? $gap : $alpha * $gap + (1 - $alpha) * $interval;
                $gap = 1;
            } else {
                $gap++;
            }
        }
        if ($size === null || ! $interval) {
            return 0.0;
        }

        return (1 - $alpha / 2) * $size / $interval;
    }

    /** Redondea la cantidad sugerida a entero, lote mínimo y múltiplo de compra. */
    public static function roundOrderQuantity(float $quantity, float $minimum = 0.0, float $multiple = 0.0): float
    {
        if ($quantity <= 0) {
            return 0.0;
        }
        $quantity = max($quantity, $minimum);
        if ($multiple > 0) {
            $quantity = ceil(round($quantity / $multiple, 6)) * $multiple;
        }

        return (float) ceil(round($quantity, 6));
    }

    /** Clasificación ABC por valor: A hasta 80 % acumulado, B hasta 95 %, resto C. */
    public static function abcClasses(array $valuesById): array
    {
        arsort($valuesById);
        $total = array_sum($valuesById);
        $classes = [];
        $accumulated = 0.0;
        foreach ($valuesById as $id => $value) {
            if ($total <= 0 || $value <= 0) {
                $classes[$id] = 'C';
                continue;
            }
            $share = $accumulated / $total; // participación acumulada antes de este ítem
            $classes[$id] = $share < 0.80 ? 'A' : ($share < 0.95 ? 'B' : 'C');
            $accumulated += $value;
        }

        return $classes;
    }

    public static function xyzClass(float $cv, bool $hasDemand): string
    {
        if (! $hasDemand) {
            return 'Z';
        }

        return $cv <= 0.5 ? 'X' : ($cv <= 1.0 ? 'Y' : 'Z');
    }

    /**
     * Política (R, S) para un producto. $p: available, on_order, daily, sigma_daily, lead_time, review_days, z,
     * min_stock, max_stock, min_order_qty, order_multiple.
     */
    public static function policy(array $p): array
    {
        $daily = max(0.0, (float) $p['daily']);
        $sigma = max(0.0, (float) $p['sigma_daily']);
        $leadTime = max(1, (int) $p['lead_time']);
        $review = max(1, (int) $p['review_days']);
        $z = (float) $p['z'];
        $minStock = max(0.0, (float) ($p['min_stock'] ?? 0));
        $maxStock = max(0.0, (float) ($p['max_stock'] ?? 0));
        $position = (float) $p['available'] + (float) $p['on_order'];

        $safety = max($minStock, $z * $sigma * sqrt($leadTime));
        $reorderPoint = $daily * $leadTime + $safety;
        $target = max($reorderPoint, $daily * ($leadTime + $review) + max($minStock, $z * $sigma * sqrt($leadTime + $review)));
        if ($maxStock > 0) {
            $target = min($target, max($maxStock, $minStock));
        }

        $trigger = $daily > 0 ? $position < $reorderPoint + $daily * $review : $position < $minStock;
        $quantity = $trigger ? self::roundOrderQuantity($target - $position, (float) ($p['min_order_qty'] ?? 0), (float) ($p['order_multiple'] ?? 0)) : 0.0;

        $coverage = $daily > 0 ? max(0.0, (float) $p['available']) / $daily : null;
        $coverageWithOrders = $daily > 0 ? max(0.0, $position) / $daily : null;
        if ($quantity <= 0) {
            $priority = null;
        } elseif (($daily > 0 && (float) $p['available'] <= 0) || ($coverageWithOrders !== null && $coverageWithOrders < $leadTime)) {
            $priority = 'critica';
        } elseif ($position < $reorderPoint) {
            $priority = 'alta';
        } else {
            $priority = 'media';
        }

        return [
            'position' => round($position, 2),
            'safety_stock' => round($safety, 2),
            'reorder_point' => round($reorderPoint, 2),
            'target_level' => round($target, 2),
            'suggested_qty' => $quantity,
            'coverage_days' => $coverage !== null ? round($coverage, 1) : null,
            'coverage_with_orders' => $coverageWithOrders !== null ? round($coverageWithOrders, 1) : null,
            'priority' => $priority,
            'shortage_units' => $coverageWithOrders !== null && $coverageWithOrders < $leadTime ? round(($leadTime - $coverageWithOrders) * $daily, 2) : 0.0,
        ];
    }

    // ───────────────────────────── Análisis sobre la base ─────────────────────────────

    public function analyze(string $companyId, array $input = []): array
    {
        $params = self::normalizeParams($input);
        $db = db_connect();
        if ($params['warehouse_id'] && ! $db->table('inventory_warehouses')->where('company_id', $companyId)->where('id', $params['warehouse_id'])->countAllResults()) {
            throw new RuntimeException('El depósito seleccionado no pertenece a la empresa.');
        }

        $weeks = (int) ceil($params['history_days'] / 7);
        $today = date('Y-m-d');
        $windowStart = date('Y-m-d', strtotime($today . ' -' . ($weeks * 7 - 1) . ' days'));
        $z = self::zScore($params['service_level']);

        $products = $db->table('inventory_products')->where('company_id', $companyId)->where('active', 1)->where('product_type !=', 'kit')->get()->getResultArray();
        $stock = $this->stockByProduct($companyId, $params['warehouse_id']);
        $openOrders = $this->openOrdersByProduct($companyId, $params['warehouse_id']);
        $demand = $this->weeklyDemand($companyId, $params['warehouse_id'], $windowStart, $today);
        $lastOut = $this->lastOutflow($companyId);
        $purchases = $this->lastPurchaseByProduct($companyId);
        $costs = $this->lastSupplierCost($companyId);
        [$leadByProduct, $leadBySupplier] = $this->observedLeadTimes($companyId);
        $suppliers = array_column($db->table('suppliers')->select('id, name, active')->where('company_id', $companyId)->get()->getResultArray(), null, 'id');

        $rows = [];
        $annualValue = [];
        foreach ($products as $product) {
            $id = $product['id'];
            $createdDays = ! empty($product['created_at']) ? (int) floor((strtotime($today) - strtotime(substr($product['created_at'], 0, 10))) / 86400) + 1 : $weeks * 7;
            $activeWeeks = max(2, min($weeks, (int) ceil($createdDays / 7)));
            $series = [];
            for ($w = $activeWeeks - 1; $w >= 0; $w--) {
                $series[] = (float) ($demand[$id][$w] ?? 0);
            }
            $forecast = self::forecast($series);
            $daily = $forecast['weekly'] / 7;
            $sigmaDaily = $forecast['sigma'] / sqrt(7);

            $onHand = (float) ($stock[$id]['quantity'] ?? 0);
            $reserved = (float) ($stock[$id]['reserved'] ?? 0);
            $available = $onHand - $reserved;
            $onOrder = (float) ($openOrders[$id]['pending'] ?? 0);

            $supplierId = $product['preferred_supplier_id'] ?? null;
            $supplierSource = 'preferido';
            if (! $supplierId || ! isset($suppliers[$supplierId])) {
                $supplierId = $purchases[$id]['supplier_id'] ?? null;
                $supplierSource = $supplierId ? 'ultima_compra' : null;
            }
            if ($supplierId && (int) ($suppliers[$supplierId]['active'] ?? 0) !== 1) {
                $supplierId = null;
                $supplierSource = null;
            }

            [$leadTime, $leadSource] = $this->resolveLeadTime($product, $leadByProduct[$id] ?? null, $supplierId ? ($leadBySupplier[$supplierId] ?? null) : null, $params['default_lead_time']);
            $unitCost = (float) ($costs[$id] ?? 0) ?: (float) ($purchases[$id]['unit_cost'] ?? 0) ?: (float) ($product['cost_price'] ?? 0);
            $taxRate = isset($purchases[$id]['tax_rate']) ? (float) $purchases[$id]['tax_rate'] : 21.0;

            $policy = self::policy([
                'available' => $available, 'on_order' => $onOrder, 'daily' => $daily, 'sigma_daily' => $sigmaDaily,
                'lead_time' => $leadTime, 'review_days' => $params['review_days'], 'z' => $z,
                'min_stock' => $product['min_stock'] ?? 0, 'max_stock' => $product['max_stock'] ?? 0,
                'min_order_qty' => $product['min_order_qty'] ?? 0, 'order_multiple' => $product['order_multiple'] ?? 0,
            ]);

            $windowDemand = array_sum($series);
            $excessQty = 0.0;
            if ($onHand > 0 && $windowDemand > 0) {
                $limit = (float) ($product['max_stock'] ?? 0) > 0 ? (float) $product['max_stock'] : $policy['target_level'] + $daily * $params['overstock_days'];
                $excessQty = max(0.0, $onHand - $limit);
            }

            $status = 'ok';
            if ($policy['suggested_qty'] > 0) {
                $status = 'reponer';
            } elseif ($onHand > 0 && $windowDemand <= 0) {
                $status = 'inmovilizado';
            } elseif ($excessQty > 0) {
                $status = 'sobrestock';
            } elseif ($windowDemand <= 0 && $onHand <= 0) {
                $status = 'sin_movimiento';
            }

            $annualValue[$id] = $daily * 365 * ($unitCost > 0 ? $unitCost : (float) ($product['sale_price'] ?? 0));
            $lastOutAt = $lastOut[$id] ?? null;
            $row = [
                'product_id' => $id,
                'sku' => (string) ($product['sku'] ?? ''),
                'name' => (string) $product['name'],
                'category' => (string) ($product['category'] ?? ''),
                'unit' => (string) ($product['unit'] ?? ''),
                'status' => $status,
                'on_hand' => round($onHand, 2),
                'reserved' => round($reserved, 2),
                'available' => round($available, 2),
                'on_order' => round($onOrder, 2),
                'on_order_draft' => round((float) ($openOrders[$id]['draft'] ?? 0), 2),
                'window_demand' => round($windowDemand, 2),
                'daily_demand' => round($daily, 3),
                'weekly_forecast' => $forecast['weekly'],
                'forecast_method' => $forecast['method'],
                'trend_pct' => $forecast['trend_pct'],
                'cv' => $forecast['cv'],
                'xyz' => self::xyzClass($forecast['cv'], $windowDemand > 0),
                'series' => array_map(static fn(float $v): float => round($v, 2), array_slice($series, -12)),
                'lead_time' => $leadTime,
                'lead_time_source' => $leadSource,
                'min_stock' => (float) ($product['min_stock'] ?? 0),
                'max_stock' => (float) ($product['max_stock'] ?? 0),
                'supplier_id' => $supplierId,
                'supplier_name' => $supplierId ? (string) ($suppliers[$supplierId]['name'] ?? '') : null,
                'supplier_source' => $supplierSource,
                'unit_cost' => round($unitCost, 4),
                'missing_cost' => $unitCost <= 0,
                'tax_rate' => $taxRate,
                'sale_price' => (float) ($product['sale_price'] ?? 0),
                'stock_value' => round(max(0.0, $onHand) * $unitCost, 2),
                'excess_qty' => round($excessQty, 2),
                'excess_value' => round($excessQty * $unitCost, 2),
                'last_outflow_at' => $lastOutAt,
                'days_without_outflow' => $lastOutAt ? (int) floor((strtotime($today) - strtotime(substr($lastOutAt, 0, 10))) / 86400) : null,
                'estimated_cost' => round($policy['suggested_qty'] * $unitCost, 2),
                'sales_at_risk' => round($policy['shortage_units'] * (float) ($product['sale_price'] ?? 0), 2),
            ] + $policy;
            $row['stockout_date'] = $row['coverage_days'] !== null && $row['coverage_days'] < 365 ? date('Y-m-d', strtotime($today . ' +' . (int) floor($row['coverage_days']) . ' days')) : null;
            $row['reasons'] = self::reasons($row, $params);
            $rows[$id] = $row;
        }

        foreach (self::abcClasses($annualValue) as $id => $class) {
            $rows[$id]['abc'] = $class;
        }
        $rows = array_values($rows);
        usort($rows, static function (array $a, array $b): int {
            $rank = ['critica' => 0, 'alta' => 1, 'media' => 2];
            $ra = $rank[$a['priority'] ?? ''] ?? 9;
            $rb = $rank[$b['priority'] ?? ''] ?? 9;
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }
            $abc = strcmp($a['abc'] ?? 'C', $b['abc'] ?? 'C');
            if ($abc !== 0) {
                return $abc;
            }

            return ($a['coverage_days'] ?? INF) <=> ($b['coverage_days'] ?? INF);
        });

        $summary = self::summarize($rows);

        return [
            'generated_at' => date('Y-m-d H:i:s'),
            'params' => $params + ['z' => $z, 'weeks' => $weeks, 'window_start' => $windowStart, 'window_end' => $today],
            'summary' => $summary,
            'rows' => $rows,
            'insights' => self::ruleInsights($rows, $summary, $params),
        ];
    }

    /** Ejecuta el análisis y guarda la corrida. */
    public function run(string $companyId, array $input, ?string $userId): array
    {
        $result = $this->analyze($companyId, $input);
        $id = app_uuid();
        $now = date('Y-m-d H:i:s');
        db_connect()->table('purchase_replenishment_runs')->insert([
            'id' => $id, 'company_id' => $companyId, 'warehouse_id' => $result['params']['warehouse_id'],
            'params_json' => json_encode($result['params']), 'summary_json' => json_encode($result['summary']),
            'result_json' => json_encode(['rows' => $result['rows'], 'insights' => $result['insights']]),
            'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return $this->find($companyId, $id);
    }

    public function find(string $companyId, string $runId): ?array
    {
        $row = db_connect()->table('purchase_replenishment_runs')->where('company_id', $companyId)->where('id', $runId)->get()->getRowArray();

        return $row ? $this->hydrate($row) : null;
    }

    public function latest(string $companyId): ?array
    {
        $row = db_connect()->table('purchase_replenishment_runs')->where('company_id', $companyId)->orderBy('created_at', 'DESC')->limit(1)->get()->getRowArray();

        return $row ? $this->hydrate($row) : null;
    }

    public function history(string $companyId, int $limit = 10): array
    {
        $rows = db_connect()->table('purchase_replenishment_runs r')->select('r.id, r.created_at, r.summary_json, r.params_json, r.orders_json, r.ai_generated_at, u.name AS user_name, w.name AS warehouse_name')
            ->join('users u', 'u.id = r.created_by', 'left')->join('inventory_warehouses w', 'w.id = r.warehouse_id', 'left')
            ->where('r.company_id', $companyId)->orderBy('r.created_at', 'DESC')->limit($limit)->get()->getResultArray();

        return array_map(static fn(array $r): array => $r + [
            'summary' => json_decode((string) $r['summary_json'], true) ?: [],
            'params' => json_decode((string) $r['params_json'], true) ?: [],
            'orders' => json_decode((string) $r['orders_json'], true) ?: [],
        ], $rows);
    }

    /** Genera (o regenera) el resumen ejecutivo con IA; si el modelo no responde usa el resumen por reglas. */
    public function aiSummary(string $companyId, string $runId, array $context = [], bool $refresh = false): array
    {
        $run = $this->find($companyId, $runId);
        if (! $run) {
            throw new RuntimeException('El análisis solicitado no existe.');
        }
        if (! $refresh && ! empty($run['ai_summary'])) {
            return ['summary' => $run['ai_summary'], 'provider' => $run['ai_provider'], 'generated_at' => $run['ai_generated_at'], 'cached' => true];
        }

        $assist = new CodexAssistService();
        $system = 'Sos un analista senior de abastecimiento y control de inventario de una pyme argentina. '
            . 'Recibís el resultado de un análisis cuantitativo (pronóstico de demanda, stock de seguridad, ABC/XYZ). '
            . 'Respondé en español rioplatense, en texto plano con títulos cortos y viñetas "- ", máximo 300 palabras. '
            . 'No inventes datos: usá solo las cifras recibidas. Montos en pesos con separador de miles.';
        $prompt = "Empresa: " . ($context['company'] ?? 'N/D') . "\n"
            . "Redactá un informe ejecutivo para el responsable de compras con estas secciones:\n"
            . "1. Diagnóstico del inventario (2-3 líneas).\n2. Compras prioritarias de esta semana (qué, a quién y por qué).\n"
            . "3. Riesgos de quiebre y venta en riesgo.\n4. Capital inmovilizado y sobrestock: acciones concretas.\n"
            . "5. Recomendaciones para negociar con proveedores (agrupar pedidos, plazos, lotes).\n\nDatos del análisis (JSON):\n"
            . json_encode($this->aiPayload($run), JSON_UNESCAPED_UNICODE);

        try {
            $answer = trim($assist->complete($system, $prompt, 1200));
            if ($answer === '') {
                throw new RuntimeException('Respuesta vacía del modelo.');
            }
            $provider = $assist->label();
        } catch (\Throwable $e) {
            log_message('warning', 'Reposición IA no disponible: ' . $e->getMessage());
            $answer = implode("\n", array_map(static fn(array $i): string => '- ' . $i['text'], $run['insights']));
            $provider = 'reglas';
        }

        $now = date('Y-m-d H:i:s');
        db_connect()->table('purchase_replenishment_runs')->where('id', $runId)->update(['ai_summary' => $answer, 'ai_provider' => $provider, 'ai_generated_at' => $now, 'updated_at' => $now]);

        return ['summary' => $answer, 'provider' => $provider, 'generated_at' => $now, 'cached' => false];
    }

    /**
     * Crea órdenes de compra en borrador agrupadas por proveedor.
     * $lines: [product_id => ['quantity' => x, 'supplier_id' => y, 'unit_cost' => z]] (solo las seleccionadas).
     */
    public function createOrders(string $companyId, string $runId, array $lines, ?string $warehouseId, string $userId, ?string $branchId = null): array
    {
        $run = $this->find($companyId, $runId);
        if (! $run) {
            throw new RuntimeException('El análisis solicitado no existe.');
        }
        $warehouseId = $warehouseId ?: ($run['warehouse_id'] ?: $this->defaultWarehouse($companyId));
        if (! $warehouseId) {
            throw new RuntimeException('Configurá al menos un depósito activo para recibir la mercadería.');
        }
        $byProduct = array_column($run['rows'], null, 'product_id');
        $groups = [];
        $skipped = 0;
        foreach ($lines as $productId => $line) {
            $row = $byProduct[$productId] ?? null;
            $quantity = (float) ($line['quantity'] ?? 0);
            $supplierId = trim((string) ($line['supplier_id'] ?? ''));
            if (! $row || $quantity <= 0 || $supplierId === '') {
                $skipped++;
                continue;
            }
            $unitCost = ($line['unit_cost'] ?? '') !== '' ? (float) $line['unit_cost'] : (float) $row['unit_cost'];
            $groups[$supplierId][] = ['product_id' => $productId, 'description' => $row['name'], 'quantity' => ceil($quantity), 'unit_cost' => max(0.0, $unitCost), 'tax_rate' => (float) $row['tax_rate']];
        }
        if ($groups === []) {
            throw new RuntimeException('Seleccioná al menos un producto con proveedor y cantidad mayor a cero.');
        }

        $flow = new PurchaseFlowService();
        $db = db_connect();
        $orders = [];
        $db->transException(true)->transStart();
        try {
            foreach ($groups as $supplierId => $items) {
                $order = $flow->createOrder($companyId, [
                    'supplier_id' => $supplierId, 'warehouse_id' => $warehouseId, 'currency_code' => 'ARS', 'branch_id' => $branchId,
                    'expected_at' => date('Y-m-d H:i:s', strtotime('+' . max(array_map(fn(array $i): int => (int) ($byProduct[$i['product_id']]['lead_time'] ?? 7), $items)) . ' days')),
                    'notes' => 'Generada por el análisis inteligente de reposición del ' . date('d/m/Y H:i', strtotime($run['created_at'])) . '.',
                ], $flow->orderLines($companyId, $items), $userId);
                $orders[] = ['id' => $order['id'], 'order_number' => $order['order_number'], 'supplier_id' => $supplierId, 'total' => (float) $order['total'], 'lines' => count($items)];
            }
            $previous = $run['orders'] ?: [];
            $db->table('purchase_replenishment_runs')->where('id', $runId)->update(['orders_json' => json_encode(array_merge($previous, $orders)), 'updated_at' => date('Y-m-d H:i:s')]);
            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e instanceof RuntimeException ? $e : new RuntimeException('No se pudieron generar las órdenes: ' . $e->getMessage());
        } finally {
            $db->transException(false);
        }

        return ['orders' => $orders, 'skipped' => $skipped];
    }

    // ───────────────────────────── Consultas ─────────────────────────────

    private function stockByProduct(string $companyId, ?string $warehouseId): array
    {
        $builder = db_connect()->table('inventory_stock_levels')->select('product_id, SUM(quantity) AS quantity, SUM(COALESCE(reserved_quantity, 0)) AS reserved', false)
            ->where('company_id', $companyId)->groupBy('product_id');
        if ($warehouseId) {
            $builder->where('warehouse_id', $warehouseId);
        }

        return array_column($builder->get()->getResultArray(), null, 'product_id');
    }

    private function openOrdersByProduct(string $companyId, ?string $warehouseId): array
    {
        $builder = db_connect()->table('purchase_order_items poi')
            ->select("poi.product_id, SUM(GREATEST(poi.quantity - COALESCE(poi.received_quantity, 0), 0)) AS pending, SUM(CASE WHEN po.status = 'draft' THEN GREATEST(poi.quantity - COALESCE(poi.received_quantity, 0), 0) ELSE 0 END) AS draft", false)
            ->join('purchase_orders po', 'po.id = poi.purchase_order_id')
            ->where('po.company_id', $companyId)->whereIn('po.status', self::OPEN_ORDER_STATUSES)->groupBy('poi.product_id');
        if ($warehouseId) {
            $builder->where('po.warehouse_id', $warehouseId);
        }

        return array_column($builder->get()->getResultArray(), null, 'product_id');
    }

    /** [product_id => [semana_atras => unidades]]; semana 0 = últimos 7 días. */
    private function weeklyDemand(string $companyId, ?string $warehouseId, string $from, string $to): array
    {
        $db = db_connect();
        $returns = implode(',', array_map([$db, 'escape'], self::RETURN_REASONS));
        $warehouseFilter = '';
        $binds = [$to, $companyId, $from . ' 00:00:00', $to . ' 23:59:59'];
        if ($warehouseId) {
            $warehouseFilter = " AND ((m.movement_type = 'egreso' AND m.source_warehouse_id = ?) OR (m.movement_type = 'ingreso' AND m.destination_warehouse_id = ?))";
            $binds[] = $warehouseId;
            $binds[] = $warehouseId;
        }
        $sql = "SELECT m.product_id, FLOOR(DATEDIFF(?, DATE(m.occurred_at)) / 7) AS wk,
                    SUM(CASE WHEN m.movement_type = 'egreso' THEN m.quantity ELSE -m.quantity END) AS qty
                FROM inventory_movements m
                WHERE m.company_id = ? AND m.occurred_at BETWEEN ? AND ?
                  AND ((m.movement_type = 'egreso' AND (m.reason IS NULL OR m.reason NOT LIKE 'Ajuste%'))
                    OR (m.movement_type = 'ingreso' AND UPPER(m.reason) IN ($returns)))
                  $warehouseFilter
                GROUP BY m.product_id, wk";
        $result = [];
        foreach ($db->query($sql, $binds)->getResultArray() as $row) {
            $result[$row['product_id']][(int) $row['wk']] = max(0.0, (float) $row['qty']);
        }

        return $result;
    }

    private function lastOutflow(string $companyId): array
    {
        $rows = db_connect()->table('inventory_movements')->select('product_id, MAX(occurred_at) AS last_at', false)
            ->where('company_id', $companyId)->where('movement_type', 'egreso')->groupBy('product_id')->get()->getResultArray();

        return array_column($rows, 'last_at', 'product_id');
    }

    /** Última compra (no cancelada) por producto: proveedor, costo y alícuota. */
    private function lastPurchaseByProduct(string $companyId): array
    {
        $rows = db_connect()->table('purchase_order_items poi')->select('poi.product_id, po.supplier_id, poi.unit_cost, poi.tax_rate')
            ->join('purchase_orders po', 'po.id = poi.purchase_order_id')
            ->where('po.company_id', $companyId)->where('po.status !=', 'cancelled')
            ->orderBy('po.issued_at', 'DESC')->get()->getResultArray();
        $result = [];
        foreach ($rows as $row) {
            $result[$row['product_id']] ??= $row;
        }

        return $result;
    }

    private function lastSupplierCost(string $companyId): array
    {
        $rows = db_connect()->table('supplier_cost_history')->select('product_id, unit_cost, exchange_rate')
            ->where('company_id', $companyId)->orderBy('observed_at', 'DESC')->get()->getResultArray();
        $result = [];
        foreach ($rows as $row) {
            $result[$row['product_id']] ??= (float) $row['unit_cost'] * ((float) ($row['exchange_rate'] ?? 0) ?: 1.0);
        }

        return $result;
    }

    /** Plazos observados (días entre emisión de la OC y recepción), ignorando cargas del mismo día. */
    private function observedLeadTimes(string $companyId): array
    {
        $rows = db_connect()->table('purchase_receipts pr')
            ->select('pri.product_id, po.supplier_id, DATEDIFF(pr.received_at, po.issued_at) AS days', false)
            ->join('purchase_orders po', 'po.id = pr.purchase_order_id')
            ->join('purchase_receipt_items pri', 'pri.purchase_receipt_id = pr.id')
            ->where('pr.company_id', $companyId)->where('pr.received_at IS NOT NULL', null, false)->where('po.issued_at IS NOT NULL', null, false)
            ->get()->getResultArray();
        $byProduct = [];
        $bySupplier = [];
        foreach ($rows as $row) {
            $days = (int) $row['days'];
            if ($days < 1 || $days > 180) {
                continue;
            }
            $byProduct[$row['product_id']][] = $days;
            $bySupplier[$row['supplier_id']][] = $days;
        }
        $average = static fn(array $values): array => ['days' => (int) ceil(array_sum($values) / count($values)), 'samples' => count($values)];

        return [array_map($average, $byProduct), array_map($average, $bySupplier)];
    }

    private function resolveLeadTime(array $product, ?array $observed, ?array $supplierObserved, int $default): array
    {
        if ($observed && $observed['samples'] >= 2) {
            return [$observed['days'], 'observado'];
        }
        if ((int) ($product['lead_time_days'] ?? 0) > 0) {
            return [(int) $product['lead_time_days'], 'producto'];
        }
        if ($observed) {
            return [$observed['days'], 'observado'];
        }
        if ($supplierObserved) {
            return [$supplierObserved['days'], 'proveedor'];
        }

        return [$default, 'defecto'];
    }

    private function defaultWarehouse(string $companyId): ?string
    {
        $row = db_connect()->table('inventory_warehouses')->select('id')->where('company_id', $companyId)->where('active', 1)
            ->orderBy('is_default', 'DESC')->orderBy('name', 'ASC')->limit(1)->get()->getRowArray();

        return $row['id'] ?? null;
    }

    private function hydrate(array $row): array
    {
        $result = json_decode((string) $row['result_json'], true) ?: [];
        unset($row['result_json']);

        return $row + [
            'params' => json_decode((string) $row['params_json'], true) ?: [],
            'summary' => json_decode((string) $row['summary_json'], true) ?: [],
            'rows' => $result['rows'] ?? [],
            'insights' => $result['insights'] ?? [],
            'orders' => json_decode((string) ($row['orders_json'] ?? ''), true) ?: [],
        ];
    }

    // ───────────────────────────── Resúmenes ─────────────────────────────

    public static function summarize(array $rows): array
    {
        $summary = ['analyzed' => count($rows), 'to_reorder' => 0, 'critical' => 0, 'high' => 0, 'investment' => 0.0, 'investment_with_tax' => 0.0,
            'sales_at_risk' => 0.0, 'stock_value' => 0.0, 'overstock_value' => 0.0, 'overstock_count' => 0, 'immobile_value' => 0.0, 'immobile_count' => 0,
            'without_supplier' => 0, 'without_cost' => 0, 'abc' => ['A' => 0, 'B' => 0, 'C' => 0], 'suppliers' => []];
        foreach ($rows as $row) {
            $summary['stock_value'] += $row['stock_value'];
            $summary['abc'][$row['abc'] ?? 'C']++;
            if ($row['status'] === 'reponer') {
                $summary['to_reorder']++;
                $summary['critical'] += $row['priority'] === 'critica' ? 1 : 0;
                $summary['high'] += $row['priority'] === 'alta' ? 1 : 0;
                $summary['investment'] += $row['estimated_cost'];
                $summary['investment_with_tax'] += $row['estimated_cost'] * (1 + $row['tax_rate'] / 100);
                $summary['sales_at_risk'] += $row['sales_at_risk'];
                $summary['without_cost'] += ! empty($row['missing_cost']) ? 1 : 0;
                if (! $row['supplier_id']) {
                    $summary['without_supplier']++;
                } else {
                    $supplier = &$summary['suppliers'][$row['supplier_id']];
                    $supplier ??= ['name' => $row['supplier_name'], 'lines' => 0, 'amount' => 0.0, 'critical' => 0];
                    $supplier['lines']++;
                    $supplier['amount'] = round($supplier['amount'] + $row['estimated_cost'], 2);
                    $supplier['critical'] += $row['priority'] === 'critica' ? 1 : 0;
                    unset($supplier);
                }
            }
            if ($row['status'] === 'inmovilizado') {
                $summary['immobile_count']++;
                $summary['immobile_value'] += $row['stock_value'];
            }
            if ($row['excess_value'] > 0) {
                $summary['overstock_count']++;
                $summary['overstock_value'] += $row['excess_value'];
            }
        }
        uasort($summary['suppliers'], static fn(array $a, array $b): int => $b['amount'] <=> $a['amount']);
        foreach (['investment', 'investment_with_tax', 'sales_at_risk', 'stock_value', 'overstock_value', 'immobile_value'] as $key) {
            $summary[$key] = round($summary[$key], 2);
        }

        return $summary;
    }

    /** Explicación legible de por qué se recomienda (o no) comprar. */
    public static function reasons(array $row, array $params): array
    {
        $reasons = [];
        $units = static fn(float $v): string => number_format($v, $v == floor($v) ? 0 : 1, ',', '.');
        if ($row['daily_demand'] > 0) {
            $reasons[] = 'Demanda pronosticada de ' . $units($row['weekly_forecast']) . ' u/semana (' . ['holt' => 'tendencia Holt', 'croston' => 'demanda intermitente, Croston', 'promedio' => 'promedio simple'][$row['forecast_method']] . ')'
                . (abs($row['trend_pct']) >= 15 ? ', tendencia ' . ($row['trend_pct'] > 0 ? 'en alza' : 'en baja') . ' ' . $units(abs($row['trend_pct'])) . ' %' : '') . '.';
        }
        if ($row['coverage_days'] !== null) {
            $reasons[] = 'El stock disponible cubre ' . $units($row['coverage_days']) . ' días; el proveedor tarda ' . $row['lead_time'] . ' días (' . ['observado' => 'plazo observado', 'producto' => 'plazo del producto', 'proveedor' => 'promedio del proveedor', 'defecto' => 'plazo por defecto'][$row['lead_time_source']] . ').';
        }
        if ($row['on_order'] > 0) {
            $reasons[] = 'Ya hay ' . $units($row['on_order']) . ' u en órdenes abiertas' . ($row['on_order_draft'] > 0 ? ' (' . $units($row['on_order_draft']) . ' en borrador)' : '') . '.';
        }
        if ($row['status'] === 'reponer') {
            $reasons[] = 'Posición ' . $units($row['position']) . ' u vs. punto de pedido ' . $units($row['reorder_point']) . ' u (stock de seguridad ' . $units($row['safety_stock']) . ' u, servicio ' . $params['service_level'] . ' %). Se lleva a ' . $units($row['target_level']) . ' u.';
        }
        if ($row['status'] === 'inmovilizado') {
            $reasons[] = 'Sin ventas en los últimos ' . $params['history_days'] . ' días' . ($row['days_without_outflow'] !== null ? ' (último egreso hace ' . $row['days_without_outflow'] . ' días)' : '') . '.';
        }
        if ($row['excess_qty'] > 0) {
            $reasons[] = 'Excedente de ' . $units($row['excess_qty']) . ' u sobre el nivel razonable.';
        }

        return $reasons;
    }

    /** Hallazgos por reglas: base del informe cuando el modelo de IA no está disponible. */
    public static function ruleInsights(array $rows, array $summary, array $params): array
    {
        $money = static fn(float $v): string => '$ ' . number_format($v, 0, ',', '.');
        $n = static fn(int $count, string $one, string $many): string => $count . ' ' . ($count === 1 ? $one : $many);
        $insights = [];
        if ($summary['to_reorder'] === 0) {
            $insights[] = ['level' => 'success', 'text' => 'No hay productos que requieran compra con un nivel de servicio del ' . $params['service_level'] . ' %.'];
        } else {
            $insights[] = ['level' => $summary['critical'] > 0 ? 'danger' : 'warning', 'text' => sprintf('%s compra (%s). Inversión estimada %s + IVA.', $n($summary['to_reorder'], 'producto requiere', 'productos requieren'), $n($summary['critical'], 'crítico', 'críticos'), $money($summary['investment']))];
        }
        $critical = array_values(array_filter($rows, static fn(array $r): bool => $r['priority'] === 'critica'));
        if ($critical) {
            $names = implode(', ', array_map(static fn(array $r): string => $r['name'], array_slice($critical, 0, 5)));
            $insights[] = ['level' => 'danger', 'text' => 'Riesgo de quiebre antes de que llegue la mercadería: ' . $names . (count($critical) > 5 ? ' y ' . (count($critical) - 5) . ' más' : '') . '.'];
        }
        if ($summary['sales_at_risk'] > 0) {
            $insights[] = ['level' => 'danger', 'text' => 'Venta en riesgo por quiebre durante el plazo de entrega: ' . $money($summary['sales_at_risk']) . '.'];
        }
        $aCritical = count(array_filter($critical, static fn(array $r): bool => ($r['abc'] ?? '') === 'A'));
        if ($aCritical > 0) {
            $insights[] = ['level' => 'danger', 'text' => $n($aCritical, 'producto crítico es', 'productos críticos son') . ' clase A (los de mayor peso en el consumo): priorizarlos.'];
        }
        if ($summary['suppliers']) {
            $top = array_slice($summary['suppliers'], 0, 3, true);
            $insights[] = ['level' => 'info', 'text' => 'Agrupar pedidos por proveedor: ' . implode('; ', array_map(static fn(array $s): string => $s['name'] . ' (' . ($s['lines'] === 1 ? '1 ítem' : $s['lines'] . ' ítems') . ', $ ' . number_format($s['amount'], 0, ',', '.') . ')', $top)) . '.'];
        }
        if ($summary['without_supplier'] > 0) {
            $insights[] = ['level' => 'warning', 'text' => $n($summary['without_supplier'], 'producto a reponer no tiene', 'productos a reponer no tienen') . ' proveedor habitual: asignalo en la ficha del producto o en la tabla.'];
        }
        if (($summary['without_cost'] ?? 0) > 0) {
            $insights[] = ['level' => 'warning', 'text' => $n($summary['without_cost'], 'producto a reponer no tiene', 'productos a reponer no tienen') . ' costo de compra cargado: la inversión estimada está subestimada.'];
        }
        $rising = array_filter($rows, static fn(array $r): bool => $r['trend_pct'] >= 30 && $r['window_demand'] > 0 && ($r['abc'] ?? 'C') !== 'C');
        if ($rising) {
            $insights[] = ['level' => 'info', 'text' => $n(count($rising), 'producto A/B muestra', 'productos A/B muestran') . ' demanda creciente (+30 % o más en las últimas semanas): revisar mínimos y lotes.'];
        }
        if ($summary['immobile_count'] > 0) {
            $insights[] = ['level' => 'warning', 'text' => sprintf('%s sin ventas en %d días: %s inmovilizados. Evaluar promociones, devolución al proveedor o liquidación.', $n($summary['immobile_count'], 'producto', 'productos'), $params['history_days'], $money($summary['immobile_value']))];
        }
        if ($summary['overstock_value'] > 0) {
            $insights[] = ['level' => 'warning', 'text' => sprintf('Sobrestock en %s por %s: suspender compras de esos ítems hasta normalizar la cobertura.', $n($summary['overstock_count'], 'producto', 'productos'), $money($summary['overstock_value']))];
        }
        $zItems = count(array_filter($rows, static fn(array $r): bool => $r['status'] === 'reponer' && $r['xyz'] === 'Z'));
        if ($zItems > 0) {
            $insights[] = ['level' => 'info', 'text' => $n($zItems, 'producto a reponer tiene', 'productos a reponer tienen') . ' demanda muy irregular (clase Z): conviene comprar lotes chicos y más frecuentes.'];
        }

        return $insights;
    }

    /** Datos compactos para el modelo de lenguaje (sin identificadores internos). */
    private function aiPayload(array $run): array
    {
        $reorder = array_values(array_filter($run['rows'], static fn(array $r): bool => $r['status'] === 'reponer'));
        $excess = array_filter($run['rows'], static fn(array $r): bool => $r['status'] === 'inmovilizado' || $r['excess_value'] > 0);
        usort($excess, static fn(array $a, array $b): int => max($b['excess_value'], $b['status'] === 'inmovilizado' ? $b['stock_value'] : 0) <=> max($a['excess_value'], $a['status'] === 'inmovilizado' ? $a['stock_value'] : 0));
        $summary = $run['summary'];
        $summary['suppliers'] = array_values(array_slice($summary['suppliers'] ?? [], 0, 8));

        return [
            'parametros' => array_intersect_key($run['params'], array_flip(['history_days', 'service_level', 'review_days', 'window_start', 'window_end'])),
            'resumen' => $summary,
            'reponer' => array_map(static fn(array $r): array => [
                'producto' => $r['name'], 'sku' => $r['sku'], 'prioridad' => $r['priority'], 'abc' => $r['abc'] ?? 'C', 'xyz' => $r['xyz'],
                'disponible' => $r['available'], 'en_camino' => $r['on_order'], 'demanda_semanal' => $r['weekly_forecast'], 'tendencia_pct' => $r['trend_pct'],
                'cobertura_dias' => $r['coverage_days'], 'plazo_dias' => $r['lead_time'], 'sugerido' => $r['suggested_qty'], 'costo' => $r['estimated_cost'],
                'proveedor' => $r['supplier_name'] ?: 'sin proveedor', 'venta_en_riesgo' => $r['sales_at_risk'],
            ], array_slice($reorder, 0, 30)),
            'exceso' => array_map(static fn(array $r): array => [
                'producto' => $r['name'], 'estado' => $r['status'], 'stock' => $r['on_hand'], 'valor' => $r['status'] === 'inmovilizado' ? $r['stock_value'] : $r['excess_value'],
                'dias_sin_egreso' => $r['days_without_outflow'],
            ], array_slice($excess, 0, 12)),
            'hallazgos_reglas' => array_column($run['insights'], 'text'),
        ];
    }
}
