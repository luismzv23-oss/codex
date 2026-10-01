<?php
$d = $dashboard;
$money = static fn($n) => number_format((float)$n, 2, ',', '.');
$names = ['cash'=>'Efectivo','card'=>'Tarjeta','transfer'=>'Transferencia','qr'=>'QR','check'=>'Cheque','wallet'=>'Billetera'];
$max = max(1, ...array_map(static fn($row) => max($row['income'],$row['expense']), array_values($d['days'])));
$points = static function($key) use ($d, $max) {
    $out=[]; $count=count($d['days']); $i=0;
    foreach($d['days'] as $row) { $out[] = round(30 + $i++ * 740 / max(1,$count-1), 1).','.round(165-135*$row[$key]/$max, 1); }
    if ($count === 1) $out[] = '770,'.round(165-135*reset($d['days'])[$key]/$max, 1);
    return implode(' ', $out);
};
$totalIncome = max(0.01, (float) ($d['income'] ?? 0));
?>
<section class="cash-dash" aria-label="Resumen operativo de caja">
    <div class="insight-kpis">
        <a class="insight-kpi insight-kpi-primary" href="#movements-table">
            <div class="insight-kpi-label">Flujo neto <i class="bi bi-arrow-up-right" aria-hidden="true"></i></div>
            <strong class="<?= (float)$d['net'] < 0 ? 'insight-danger' : '' ?>">$<?= $money($d['net']) ?></strong>
            <span>Ingresos menos egresos</span>
        </a>
        <a class="insight-kpi" href="#movements-table">
            <div class="insight-kpi-label">Ingresos <i class="bi bi-arrow-down-left text-success" aria-hidden="true"></i></div>
            <strong class="text-success">$<?= $money($d['income']) ?></strong>
            <span>Movimientos positivos del período</span>
        </a>
        <a class="insight-kpi" href="#movements-table">
            <div class="insight-kpi-label">Egresos <i class="bi bi-arrow-up-right text-danger" aria-hidden="true"></i></div>
            <strong class="<?= (float)$d['expense'] > 0 ? 'text-danger' : '' ?>">$<?= $money($d['expense']) ?></strong>
            <span>Movimientos negativos del período</span>
        </a>
        <a class="insight-kpi" href="#movements-table">
            <div class="insight-kpi-label">Movimientos <i class="bi bi-receipt" aria-hidden="true"></i></div>
            <strong><?= number_format((int)$d['count'], 0, ',', '.') ?></strong>
            <span>Operaciones registradas</span>
        </a>
        <a class="insight-kpi <?= (int)$d['pending'] > 0 ? 'insight-kpi-alert' : '' ?>" href="#reconciliations-table">
            <div class="insight-kpi-label">Por conciliar <i class="bi bi-clock-history" aria-hidden="true"></i></div>
            <strong><?= number_format((int)$d['pending'], 0, ',', '.') ?></strong>
            <span>Arqueos y diferencias pendientes</span>
        </a>
        <a class="insight-kpi" href="#sessions-table">
            <div class="insight-kpi-label">Sesiones abiertas <i class="bi bi-box-arrow-in-up" aria-hidden="true"></i></div>
            <strong><?= number_format((int)$d['open'], 0, ',', '.') ?></strong>
            <span>Cajas con turno activo</span>
        </a>
    </div>

    <div class="insight-analysis-grid">
        <section class="insight-panel cash-chart">
            <div class="insight-panel-heading">
                <div>
                    <span class="insight-overline">EVOLUCIÓN DIARIA</span>
                    <h2>¿Cómo se mueve el dinero?</h2>
                    <p>Ingresos (violeta) y egresos (naranja discontinuo)</p>
                </div>
                <span class="insight-tag"><span class="insight-series-dot"></span> <?= esc($d['from']) ?> — <?= esc($d['to']) ?></span>
            </div>
            <?php if (!$d['count']): ?>
                <div class="insight-empty">
                    <i class="bi bi-graph-up" aria-hidden="true"></i>
                    <strong>Sin movimientos en este período</strong>
                    <span>Prueba otro rango de fechas o selecciona otra caja para consultar la actividad.</span>
                </div>
            <?php else: ?>
                <svg class="insight-chart" viewBox="0 0 800 185" role="img" aria-label="Evolución diaria. Los importes exactos están en el detalle inferior.">
                    <line x1="30" x2="770" y1="30" y2="30" stroke="#e9ecf4" stroke-dasharray="4 5"/>
                    <line x1="30" x2="770" y1="95" y2="95" stroke="#e9ecf4" stroke-dasharray="4 5"/>
                    <line x1="30" x2="770" y1="165" y2="165" stroke="#e9ecf4" />
                    <polyline points="<?= esc($points('income')) ?>" fill="none" stroke="#6959e4" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    <polyline points="<?= esc($points('expense')) ?>" fill="none" stroke="#f97316" stroke-width="2.5" stroke-dasharray="6 4" stroke-linecap="round"/>
                </svg>
                <div class="d-flex justify-content-between small text-secondary mt-2">
                    <span><?= esc($d['from']) ?></span>
                    <span>Escala máxima: $<?= $money($max) ?></span>
                    <span><?= esc($d['to']) ?></span>
                </div>
            <?php endif; ?>
            <details>
                <summary>Ver importes por día <i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
                <div class="insight-table-scroll">
                    <table>
                        <thead><tr><th>Día</th><th class="text-end">Ingresos</th><th class="text-end">Egresos</th></tr></thead>
                        <tbody>
                        <?php foreach($d['days'] as $day => $row): ?>
                            <tr>
                                <td><?= esc($day) ?></td>
                                <td class="text-end text-success font-monospace">$<?= $money($row['income']) ?></td>
                                <td class="text-end text-danger font-monospace">$<?= $money($row['expense']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        </section>

        <section class="insight-panel">
            <div class="insight-panel-heading">
                <div>
                    <span class="insight-overline">DISTRIBUCIÓN</span>
                    <h2>¿Por dónde ingresó?</h2>
                    <p>Participación por canal de cobro</p>
                </div>
                <span class="insight-tag">Total: $<?= $money($d['income']) ?></span>
            </div>
            <?php $hasIncome = false; foreach($d['methods'] as $method => $amount): if($amount <= 0) continue; $hasIncome = true; $pct = round(($amount / $totalIncome) * 100, 1); ?>
                <div class="cash-method-rank">
                    <div>
                        <span><strong><?= esc($names[$method] ?? ucfirst($method)) ?></strong> <small class="text-muted">(<?= $pct ?>%)</small></span>
                        <strong>$<?= $money($amount) ?></strong>
                    </div>
                    <div class="cash-method-bar">
                        <span style="width: <?= min(100, max(2, $pct)) ?>%;"></span>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if(!$hasIncome): ?>
                <div class="insight-empty">
                    <i class="bi bi-cash-stack" aria-hidden="true"></i>
                    <strong>Sin ingresos registrados</strong>
                    <span>No hay cobros en este rango de fechas.</span>
                </div>
            <?php endif; ?>
        </section>
    </div>
</section>
