<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/dashboard-insights.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/inventory-dashboard.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/kardex-dashboard.css') ?>">
<?php
$q = '?company_id=' . urlencode($companyId);
$base = site_url('inventario/control-semanal/' . $count['id']);
$status = $count['status'];
$items = $count['items'];
$num = static fn($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
$money = static fn($v) => number_format((float) $v, 2, ',', '.');
$counted = array_filter($items, static fn($i) => $i['counted_at'] !== null);
$done = count($counted);
$total = count($items);
$progress = $total ? (int) round($done / $total * 100) : 0;
$reasons = ['Rotura', 'Faltante / robo', 'Vencimiento', 'Error de carga', 'Venta sin registrar', 'Compra sin registrar', 'Consumo interno', 'Otro'];
$selectionLabels = ['alerta' => 'Alerta', 'movimiento' => 'Movimiento', 'rotacion' => 'Ciclo', 'agregado' => 'Agregado'];
$statusLabels = ['open' => ['Contando', 'info'], 'counted' => ['Por aprobar', 'warning'], 'approved' => ['Aprobado', 'success'], 'cancelled' => ['Anulado', 'secondary']];
[$statusLabel, $statusColor] = $statusLabels[$status] ?? [$status, 'secondary'];
$pct = static fn($i) => (float) $i['system_quantity'] != 0 ? abs((float) $i['difference'] / (float) $i['system_quantity'] * 100) : ((float) $i['difference'] != 0 ? 100 : 0);
$short = array_filter($counted, static fn($i) => (float) $i['difference'] < 0);
$excess = array_filter($counted, static fn($i) => (float) $i['difference'] > 0);
$high = array_filter($counted, static fn($i) => (float) $i['difference'] != 0 && $pct($i) >= $threshold);
$value = static fn($rows) => array_sum(array_map(static fn($i) => (float) $i['difference_value'], $rows));
$steps = [['open', 'Contar', 'Carga a ciegas de lo que hay en el depósito'], ['counted', 'Revisar', 'Diferencias contra el sistema y motivos'], ['approved', 'Ajustar', 'El stock queda igual a lo contado']];
$stepIndex = ['open' => 0, 'counted' => 1, 'approved' => 2][$status] ?? -1;
?>
<style>
.count-steps{display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem;margin-bottom:1.25rem}
.count-step{background:#fff;border:1px solid #e8ebf2;border-radius:14px;padding:.75rem 1rem;display:flex;gap:.75rem;align-items:center}
.count-step b{width:28px;height:28px;border-radius:50%;display:grid;place-items:center;background:#f1f3f8;color:#77839a;font-size:12px;flex-shrink:0}
.count-step.is-done b{background:#d9f2e3;color:#1f7a45}.count-step.is-current{border-color:#1f2d3d}.count-step.is-current b{background:#1f2d3d;color:#fff}
.count-step span{display:block;font-size:11px;color:#77839a}
.count-toolbar{display:flex;flex-wrap:wrap;gap:.5rem;justify-content:space-between;align-items:center;margin-bottom:1rem}
.count-toolbar .input-group{max-width:300px}
.count-sticky{position:sticky;bottom:0;background:rgba(255,255,255,.96);border-top:1px solid #e8ebf2;padding:.75rem 0;margin-top:1rem;display:flex;flex-wrap:wrap;gap:.75rem;align-items:center;justify-content:space-between;z-index:2}
.count-entry{background:#f7f9fc;border-radius:14px;padding:1rem;margin-bottom:1rem}
</style>
<div class="insight-shell inventory-shell kardex-shell">
<header class="insight-hero">
    <div>
        <div class="insight-eyebrow"><span class="insight-orbit"></span> CODEX / CONTROL DE STOCK</div>
        <h1>Control <?= esc($count['count_number']) ?> <span class="badge text-bg-<?= $statusColor ?> align-middle fs-6"><?= esc($statusLabel) ?></span></h1>
        <p><?= match ($status) {
            'open' => 'Cuenta físicamente cada producto y cárgalo. El stock del sistema se muestra recién al cerrar el conteo.',
            'counted' => 'Revisa las diferencias, indica el motivo y aprueba los ajustes que correspondan.',
            'approved' => 'Control aprobado: los ajustes figuran en el Kardex con documento ' . esc($count['count_number']) . '.',
            default => 'Control anulado: no movió stock.',
        } ?></p>
        <div class="insight-identity"><i class="bi bi-building" aria-hidden="true"></i><span><?= esc($company['name'] ?? '') ?> · <?= esc($count['warehouse_name'] ?? '') ?> · Semana <?= esc($count['week']) ?></span></div>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="<?= $base . '/productos' . $q ?>" class="btn btn-outline-dark icon-btn" data-popup="true" data-popup-title="Productos del control" data-popup-subtitle="<?= esc($count['count_number']) ?>" title="Ver productos" aria-label="Ver productos"><i class="bi bi-list-check"></i></a>
        <a href="<?= $base . '/planilla' . $q ?>" target="_blank" class="btn btn-outline-dark icon-btn" title="Planilla de conteo PDF" aria-label="Planilla de conteo PDF"><i class="bi bi-printer"></i></a>
        <a href="<?= site_url('inventario/control-semanal' . $q) ?>" class="btn btn-outline-dark icon-btn" title="Volver al control semanal" aria-label="Volver al control semanal"><i class="bi bi-arrow-left"></i></a>
        <?php if (in_array($status, ['open', 'counted'], true)): ?>
            <form method="post" action="<?= $base . '/anular' . $q ?>" onsubmit="return confirm('¿Anular este control? No se moverá stock.')" class="m-0"><?= csrf_field() ?>
                <button class="btn btn-outline-danger icon-btn" title="Anular control" aria-label="Anular control"><i class="bi bi-x-circle"></i></button></form>
        <?php endif; ?>
    </div>
</header>

<?php if ($status !== 'cancelled'): ?>
<div class="count-steps">
    <?php foreach ($steps as $n => [$key, $label, $hint]): ?>
        <div class="count-step <?= $n < $stepIndex || $status === 'approved' ? 'is-done' : ($n === $stepIndex ? 'is-current' : '') ?>"><b><?= $n < $stepIndex || $status === 'approved' ? '<i class="bi bi-check-lg"></i>' : $n + 1 ?></b><div><strong><?= $label ?></strong><span><?= $hint ?></span></div></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="insight-kpis">
<?php if ($status === 'open'): ?>
    <div class="insight-kpi insight-kpi-primary"><div class="insight-kpi-label">Avance del conteo</div><strong id="kpi-progress"><?= $progress ?>%</strong><span><span id="count-progress"><?= $done ?>/<?= $total ?></span> productos contados</span></div>
    <div class="insight-kpi <?= $total - $done > 0 ? 'insight-kpi-alert' : '' ?>"><div class="insight-kpi-label">Pendientes</div><strong id="kpi-pending"><?= $total - $done ?></strong><span>Sin cantidad cargada</span></div>
    <div class="insight-kpi"><div class="insight-kpi-label">Con alerta</div><strong><?= count(array_filter($items, static fn($i) => $i['selection_reason'] === 'alerta')) ?></strong><span>Entradas sin comprobante, borradores o negativos</span></div>
    <div class="insight-kpi"><div class="insight-kpi-label">Agregados</div><strong id="kpi-added"><?= count(array_filter($items, static fn($i) => $i['selection_reason'] === 'agregado')) ?></strong><span>Encontrados fuera de la lista</span></div>
<?php else: ?>
    <div class="insight-kpi insight-kpi-primary"><div class="insight-kpi-label">Diferencia total</div><strong>$ <?= $money($count['difference_value']) ?></strong><span><?= $done ?> de <?= $total ?> productos contados</span></div>
    <div class="insight-kpi <?= $short ? 'insight-kpi-alert' : '' ?>"><div class="insight-kpi-label">Con faltante</div><strong><?= count($short) ?></strong><span>$ <?= $money($value($short)) ?></span></div>
    <div class="insight-kpi"><div class="insight-kpi-label">Con sobrante</div><strong><?= count($excess) ?></strong><span>$ <?= $money($value($excess)) ?></span></div>
    <div class="insight-kpi <?= $high ? 'insight-kpi-alert' : '' ?>"><div class="insight-kpi-label">Desvío alto</div><strong><?= count($high) ?></strong><span>Diferencia ≥ <?= $num($threshold) ?>% del stock</span></div>
    <div class="insight-kpi"><div class="insight-kpi-label">Ajustados</div><strong><?= count(array_filter($items, static fn($i) => ! empty($i['movement_id']))) ?></strong><span><?= $status === 'approved' ? 'Aprobado el ' . esc(date('d/m/Y H:i', strtotime((string) $count['approved_at']))) : 'Pendiente de aprobación' ?></span></div>
<?php endif; ?>
</div>

<?php if ($status === 'open'): ?>
<section class="insight-panel mb-4">
    <div class="insight-panel-heading"><div><span class="insight-overline">CONTEO A CIEGAS</span><h2>¿Qué hay en el depósito?</h2><p>Carga con el buscador (código, nombre o lector) o directamente en la columna Contado. Se guarda sola.</p></div><span class="insight-tag"><?= $total ?> productos</span></div>
    <div class="progress mb-3" style="height:8px"><div class="progress-bar bg-success" id="progress-bar" style="width:<?= $progress ?>%"></div></div>
    <div class="count-entry row g-2 align-items-end">
        <div class="col-md-7"><label class="form-label small text-secondary mb-1">Producto</label>
            <select id="count-product"><option value="">Buscar por código, nombre o escáner…</option>
                <?php foreach ($products as $p): ?><option value="<?= esc($p['id']) ?>"><?= esc($p['sku'] . ' — ' . $p['name']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-3"><label class="form-label small text-secondary mb-1">Cantidad contada</label><input id="count-qty" type="number" step="0.01" min="0" class="form-control"></div>
        <div class="col-md-2"><button id="count-save" class="btn btn-dark w-100" type="button"><i class="bi bi-plus-circle"></i> Cargar</button></div>
    </div>
    <div class="count-toolbar">
        <div class="input-group input-group-sm"><span class="input-group-text bg-white"><i class="bi bi-search"></i></span><input type="search" class="form-control count-search" placeholder="Filtrar la lista por SKU o nombre"></div>
        <div class="btn-group btn-group-sm count-filters" role="group">
            <button type="button" class="btn btn-dark" data-filter="all">Todos</button>
            <button type="button" class="btn btn-outline-dark" data-filter="pending">Pendientes</button>
            <button type="button" class="btn btn-outline-dark" data-filter="done">Contados</button>
        </div>
    </div>
    <div class="table-responsive"><table class="table align-middle mb-0 count-paged" id="count-table">
        <thead><tr><th>Producto</th><th>Unidad</th><th>Motivo</th><th style="width:170px">Contado</th></tr></thead>
        <tbody>
        <?php foreach ($items as $i): ?>
            <tr data-search="<?= esc(mb_strtolower($i['sku'] . ' ' . $i['name'])) ?>" data-state="<?= $i['counted_at'] ? 'done' : 'pending' ?>">
                <td><?= esc($i['name']) ?><div class="small text-secondary"><?= esc($i['sku']) ?></div></td>
                <td><?= esc($i['unit'] ?? '') ?></td>
                <td><span class="badge text-bg-light"><?= esc($selectionLabels[$i['selection_reason']] ?? $i['selection_reason']) ?></span></td>
                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm count-line <?= $i['counted_at'] ? 'is-valid' : '' ?>" data-product="<?= esc($i['product_id']) ?>" value="<?= $i['counted_at'] ? esc($i['counted_quantity']) : '' ?>" aria-label="Cantidad contada de <?= esc($i['name']) ?>"></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <div class="count-sticky">
        <span class="small text-secondary">Al cerrar se calculan las diferencias. Los productos sin contar no se ajustan.</span>
        <form method="post" action="<?= $base . '/cerrar' . $q ?>" class="m-0" onsubmit="return confirm('¿Cerrar el conteo? Se calcularán las diferencias.')"><?= csrf_field() ?>
            <button class="btn btn-success"><i class="bi bi-check2-square"></i> Cerrar conteo y ver diferencias</button></form>
    </div>
</section>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const url = <?= json_encode($base . '/contar' . $q) ?>;
    const header = <?= json_encode($csrfHeader) ?>;
    let token = document.querySelector('meta[name="csrf-token"]').content;
    const select = new TomSelect('#count-product', {create: false, maxOptions: 50});
    const pending = document.getElementById('kpi-pending');
    const send = async (productId, counted, el) => {
        if (!productId || counted === '') return;
        const res = await fetch(url, {method: 'POST', headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', [header]: token},
            body: JSON.stringify({product_id: productId, counted})});
        const data = await res.json().catch(() => ({}));
        if (data.csrf_token) token = data.csrf_token;
        if (!res.ok) { window.showCodexToast ? showCodexToast(data.error || 'No se pudo guardar', 'error') : alert(data.error); return false; }
        if (el) { el.classList.add('is-valid'); el.closest('tr').dataset.state = 'done'; }
        document.getElementById('count-progress').textContent = data.counted + '/' + data.total;
        const pct = data.total ? Math.round(data.counted / data.total * 100) : 0;
        document.getElementById('kpi-progress').textContent = pct + '%';
        document.getElementById('progress-bar').style.width = pct + '%';
        pending.textContent = data.total - data.counted;
        return data;
    };
    const bind = input => input.addEventListener('change', () => send(input.dataset.product, input.value, input));
    document.querySelectorAll('.count-line').forEach(bind);
    document.getElementById('count-save').addEventListener('click', async () => {
        const qty = document.getElementById('count-qty');
        const id = select.getValue();
        if (!id || qty.value === '') { window.showCodexToast?.('Elegí un producto e ingresá la cantidad.', 'error'); return; }
        const data = await send(id, qty.value, null);
        if (!data) return;
        const line = document.querySelector(`.count-line[data-product="${id}"]`);
        if (line) {
            line.value = qty.value; line.classList.add('is-valid'); line.closest('tr').dataset.state = 'done';
        } else {
            // Product outside the list: add its row without reloading and keep the current page.
            const [sku, ...rest] = (select.options[id]?.text || '').split(' — ');
            const row = document.createElement('tr');
            row.dataset.state = 'done'; row.dataset.search = (sku + ' ' + rest.join(' ')).toLowerCase();
            row.innerHTML = '<td><span></span><div class="small text-secondary"></div></td><td></td><td><span class="badge text-bg-light">Agregado</span></td><td><input type="number" step="0.01" min="0" class="form-control form-control-sm count-line is-valid"></td>';
            row.querySelector('td span').textContent = rest.join(' — '); row.querySelector('td div').textContent = sku;
            const input = row.querySelector('input'); input.dataset.product = id; input.value = qty.value; bind(input);
            document.querySelector('#count-table tbody').appendChild(row);
            const added = document.getElementById('kpi-added'); added.textContent = Number(added.textContent) + 1;
            window.showCodexToast?.('Producto agregado al control.', 'success');
        }
        window.codexCountPagers?.forEach(p => p.render());
        qty.value = ''; select.clear(); select.focus();
    });
    document.getElementById('count-qty').addEventListener('keydown', e => { if (e.key === 'Enter') document.getElementById('count-save').click(); });
});
</script>
<?php else: ?>
<?php $editable = $status === 'counted'; ?>
<section class="insight-panel mb-4">
    <div class="insight-panel-heading"><div><span class="insight-overline">DIFERENCIAS</span><h2><?= $editable ? '¿Qué diferencias aprobamos?' : '¿Qué diferencias tuvo el control?' ?></h2>
        <p><?= $editable ? 'Las líneas marcadas generan un ajuste de stock con documento ' . esc($count['count_number']) . ' y requieren motivo. Las no marcadas no mueven stock.' : 'Sistema = stock al momento de cargar cada conteo. Valor al costo promedio.' ?></p></div>
        <span class="insight-tag"><?= count($short) + count($excess) ?> con diferencia</span></div>
    <div class="count-toolbar">
        <div class="input-group input-group-sm"><span class="input-group-text bg-white"><i class="bi bi-search"></i></span><input type="search" class="form-control count-search" placeholder="Filtrar por SKU o nombre"></div>
        <div class="btn-group btn-group-sm count-filters" role="group">
            <button type="button" class="btn btn-dark" data-filter="all">Todos</button>
            <button type="button" class="btn btn-outline-dark" data-filter="diff">Con diferencia</button>
            <button type="button" class="btn btn-outline-dark" data-filter="high">Desvío alto</button>
            <button type="button" class="btn btn-outline-dark" data-filter="pending">Sin contar</button>
        </div>
    </div>
    <form method="post" action="<?= $base . '/aprobar' . $q ?>" id="approve-form">
        <?= csrf_field() ?>
        <div class="table-responsive"><table class="table align-middle mb-0 count-paged">
            <thead><tr><th>Producto</th><th>Sistema</th><th>Contado</th><th>Diferencia</th><th>%</th><th>Valor $</th><th>Aprobar</th><th>Motivo</th></tr></thead>
            <tbody>
            <?php foreach ($items as $i):
                $isCounted = $i['counted_at'] !== null; $diff = (float) $i['difference'];
                $p = $isCounted ? $pct($i) : 0;
                $big = $isCounted && $diff != 0 && $p >= $threshold;
                $traced = (int) $i['lot_control'] || (int) $i['serial_control'];
                $state = ! $isCounted ? 'pending' : ($diff == 0 ? 'ok' : ($big ? 'diff high' : 'diff')); ?>
                <tr data-search="<?= esc(mb_strtolower($i['sku'] . ' ' . $i['name'])) ?>" data-state="<?= $state ?>" class="<?= $big ? 'table-danger' : '' ?>">
                    <td><?= esc($i['name']) ?><div class="small text-secondary"><?= esc($i['sku']) ?><?php if ((int) $i['moved_after_count']): ?> · <span class="badge text-bg-warning" title="El producto tuvo movimientos después de contarse">movido luego</span><?php endif; ?></div></td>
                    <?php if (! $isCounted): ?><td colspan="7" class="text-secondary">Sin contar — no se ajusta</td>
                    <?php else: ?>
                        <td><?= $num($i['system_quantity']) ?></td><td><?= $num($i['counted_quantity']) ?></td>
                        <td class="<?= $diff < 0 ? 'insight-danger' : ($diff > 0 ? 'text-success fw-semibold' : '') ?>"><?= $diff > 0 ? '+' : '' ?><?= $num($diff) ?></td>
                        <td><?= number_format($p, 0) ?>%</td>
                        <td><?= $money($i['difference_value']) ?></td>
                        <?php if ($diff == 0): ?><td colspan="2"><span class="badge text-bg-light">Sin diferencia</span></td>
                        <?php else: ?>
                            <td><?php if ($editable): ?><input type="checkbox" class="form-check-input count-approve" name="items[<?= esc($i['id']) ?>][approved]" value="1" checked>
                                <?php else: ?><span class="badge text-bg-<?= ! empty($i['movement_id']) ? 'success' : 'secondary' ?>"><?= ! empty($i['movement_id']) ? 'Ajustado' : 'No aprobado' ?></span><?php endif; ?></td>
                            <td><?php if ($editable): ?><select name="items[<?= esc($i['id']) ?>][reason]" class="form-select form-select-sm count-reason"><option value="">Motivo…</option><?php foreach ($reasons as $r): ?><option><?= esc($r) ?></option><?php endforeach; ?></select>
                                <?php if ($traced): ?><?= view('inventory/control/_trace_inputs', ['item' => $i, 'trace' => ($traces ?? [])[$i['product_id']] ?? ['lots' => [], 'serials' => [], 'serials_required' => false], 'count' => $count, 'num' => $num]) ?><?php endif; ?>
                                <?php else: ?><?= esc($i['reason'] ?? '') ?: '—' ?><?php endif; ?></td>
                        <?php endif; ?>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php if ($editable): ?>
        <div class="count-sticky">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="small text-secondary">Motivo para las líneas marcadas sin motivo:</span>
                <select class="form-select form-select-sm w-auto" id="bulk-reason"><option value="">Elegir…</option><?php foreach ($reasons as $r): ?><option><?= esc($r) ?></option><?php endforeach; ?></select>
                <button type="button" class="btn btn-sm btn-outline-dark" id="bulk-apply">Aplicar</button>
            </div>
            <button class="btn btn-success"><i class="bi bi-shield-check"></i> Aprobar diferencias y ajustar stock</button>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('bulk-apply').addEventListener('click', () => {
            const reason = document.getElementById('bulk-reason').value; if (!reason) return;
            let n = 0;
            document.querySelectorAll('.count-approve:checked').forEach(box => { const sel = box.closest('tr').querySelector('.count-reason'); if (sel && !sel.value) { sel.value = reason; n++; } });
            window.showCodexToast?.(`Motivo aplicado a ${n} línea(s).`, 'success');
        });
        // Series: contador de elegidas por línea.
        document.querySelectorAll('.trace-serials').forEach(sel => {
            const counter = sel.closest('.trace-box').querySelector('.trace-serials-count');
            const ts = new TomSelect(sel, {plugins: ['remove_button'], maxItems: Number(sel.dataset.need), placeholder: 'Buscar serie…'});
            ts.on('change', () => { counter.textContent = `${ts.items.length} de ${sel.dataset.need} elegidas`; });
        });
        // Antes de aprobar: avisar qué diferencias quedan sin ajustar.
        document.getElementById('approve-form').addEventListener('submit', e => {
            const rows = Array.from(document.querySelectorAll('.count-approve')).map(box => box.closest('tr'));
            const unchecked = rows.filter(r => !r.querySelector('.count-approve').checked).length;
            const marked = rows.length - unchecked;
            const msg = `Se ajustará el stock de ${marked} producto(s).` + (unchecked ? `\n${unchecked} diferencia(s) quedarán SIN ajustar y el inventario no cambiará para esos productos.` : '') + '\n¿Aprobar el control?';
            if (!confirm(msg)) e.preventDefault();
        });
        });
        </script>
        <?php endif; ?>
    </form>
</section>
<?php endif; ?>
</div>

<script>
// 15 rows per page with the system's pagination look; search + filter aware; render() keeps the current page.
document.addEventListener('DOMContentLoaded', () => {
    window.codexCountPagers = Array.from(document.querySelectorAll('table.count-paged')).map(table => {
        const panel = table.closest('.insight-panel');
        const search = panel.querySelector('.count-search');
        const filters = panel.querySelector('.count-filters');
        const size = 15; let page = 1; let filter = 'all';
        const pager = document.createElement('div'); pager.className = 'codex-pagination mt-3';
        table.closest('.table-responsive').after(pager);
        const render = () => {
            const term = (search?.value || '').toLowerCase().trim();
            const all = Array.from(table.tBodies[0].rows);
            const rows = all.filter(r => (!term || (r.dataset.search || '').includes(term)) && (filter === 'all' || (r.dataset.state || '').split(' ').includes(filter)));
            all.forEach(r => { r.style.display = 'none'; });
            const pages = Math.max(1, Math.ceil(rows.length / size));
            page = Math.min(Math.max(1, page), pages);
            rows.slice((page - 1) * size, page * size).forEach(r => { r.style.display = ''; });
            const from = rows.length ? (page - 1) * size + 1 : 0, to = Math.min(page * size, rows.length);
            const btn = (html, target, disabled, active, title) => `<button type="button" class="codex-pagination__btn${active ? ' is-active' : ''}" data-page="${target}" ${disabled ? 'disabled' : ''} title="${title}">${html}</button>`;
            let nums = '';
            for (let p = Math.max(1, page - 2); p <= Math.min(pages, page + 2); p++) nums += btn(p, p, false, p === page, 'Página ' + p);
            pager.innerHTML = `<div class="codex-pagination__summary">${rows.length ? `Mostrando ${from}-${to} de ${rows.length} registros` : 'Sin productos para este filtro'}</div>` + (pages > 1
                ? `<div class="codex-pagination__controls">${btn('<i class="bi bi-chevron-double-left"></i>', 1, page === 1, false, 'Primera página')}${btn('<i class="bi bi-chevron-left"></i>', page - 1, page === 1, false, 'Página anterior')}<div class="codex-pagination__pages">${nums}</div>${btn('<i class="bi bi-chevron-right"></i>', page + 1, page === pages, false, 'Página siguiente')}${btn('<i class="bi bi-chevron-double-right"></i>', pages, page === pages, false, 'Última página')}</div>` : '');
        };
        pager.addEventListener('click', e => { const b = e.target.closest('[data-page]'); if (b && !b.disabled) { page = Number(b.dataset.page); render(); } });
        search?.addEventListener('input', () => { page = 1; render(); });
        filters?.addEventListener('click', e => {
            const b = e.target.closest('[data-filter]'); if (!b) return;
            filter = b.dataset.filter; page = 1;
            filters.querySelectorAll('[data-filter]').forEach(x => { x.classList.toggle('btn-dark', x === b); x.classList.toggle('btn-outline-dark', x !== b); });
            render();
        });
        render();
        return {render};
    });
});
</script>
<?= $this->endSection() ?>
