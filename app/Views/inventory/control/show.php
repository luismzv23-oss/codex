<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<?php
$q = '?company_id=' . urlencode($companyId);
$base = site_url('inventario/control-semanal/' . $count['id']);
$status = $count['status'];
$num = static fn($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
$done = count(array_filter($count['items'], static fn($i) => $i['counted_at'] !== null));
$reasons = ['Rotura', 'Faltante / robo', 'Vencimiento', 'Error de carga', 'Venta sin registrar', 'Compra sin registrar', 'Consumo interno', 'Otro'];
$selectionLabels = ['alerta' => 'Alerta', 'movimiento' => 'Movimiento', 'rotacion' => 'Ciclo', 'agregado' => 'Agregado'];
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1">Control <?= esc($count['count_number']) ?></h1>
        <p class="text-muted mb-0">Semana <?= esc($count['week']) ?> · <?= esc($count['warehouse_name'] ?? '') ?> · <?= $done ?>/<?= count($count['items']) ?> contados</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-dark" href="<?= $base . '/planilla' . $q ?>" target="_blank"><i class="bi bi-printer"></i> Planilla PDF</a>
        <a class="btn btn-outline-dark" href="<?= site_url('inventario/control-semanal' . $q) ?>"><i class="bi bi-arrow-left"></i> Volver</a>
        <?php if (in_array($status, ['open', 'counted'], true)): ?>
            <form method="post" action="<?= $base . '/anular' . $q ?>" onsubmit="return confirm('¿Anular este control? No se moverá stock.')"><?= csrf_field() ?><button class="btn btn-outline-danger">Anular</button></form>
        <?php endif; ?>
    </div>
</div>

<?php if ($status === 'open'): ?>
    <div class="alert alert-info">Conteo <strong>a ciegas</strong>: cuenta lo que hay físicamente y cárgalo. El stock del sistema no se muestra hasta cerrar el conteo.</div>
    <div class="card mb-3"><div class="card-body row g-2 align-items-end">
        <div class="col-md-7"><label class="form-label">Producto (código, nombre o escáner)</label>
            <select id="count-product"><option value="">Buscar…</option>
                <?php foreach ($products as $p): ?><option value="<?= esc($p['id']) ?>"><?= esc($p['sku'] . ' — ' . $p['name']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-3"><label class="form-label">Cantidad contada</label><input id="count-qty" type="number" step="0.01" min="0" class="form-control"></div>
        <div class="col-md-2"><button id="count-save" class="btn btn-primary w-100" type="button">Cargar</button></div>
    </div></div>
    <table class="table table-sm align-middle">
        <thead><tr><th>SKU</th><th>Producto</th><th>Unidad</th><th>Motivo</th><th style="width:160px">Contado</th></tr></thead>
        <tbody>
        <?php foreach ($count['items'] as $i): ?>
            <tr>
                <td><?= esc($i['sku']) ?></td><td><?= esc($i['name']) ?></td><td><?= esc($i['unit'] ?? '') ?></td>
                <td><span class="badge bg-light text-dark"><?= esc($selectionLabels[$i['selection_reason']] ?? $i['selection_reason']) ?></span></td>
                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm count-line" data-product="<?= esc($i['product_id']) ?>" value="<?= $i['counted_at'] ? esc($i['counted_quantity']) : '' ?>"></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <form method="post" action="<?= $base . '/cerrar' . $q ?>" onsubmit="return confirm('¿Cerrar el conteo? Se calcularán las diferencias.')"><?= csrf_field() ?>
        <button class="btn btn-success"><i class="bi bi-check2-square"></i> Cerrar conteo y ver diferencias</button>
    </form>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const url = <?= json_encode($base . '/contar' . $q) ?>;
        const header = <?= json_encode($csrfHeader) ?>;
        let token = document.querySelector('meta[name="csrf-token"]').content;
        const select = new TomSelect('#count-product', {create: false, maxOptions: 50});
        const send = async (productId, counted, el) => {
            if (!productId || counted === '') return;
            const res = await fetch(url, {method: 'POST', headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', [header]: token},
                body: JSON.stringify({product_id: productId, counted})});
            const data = await res.json().catch(() => ({}));
            if (data.csrf_token) token = data.csrf_token;
            if (!res.ok) { window.showCodexToast ? showCodexToast(data.error || 'No se pudo guardar', 'error') : alert(data.error); return false; }
            if (el) el.classList.add('is-valid');
            return data;
        };
        document.querySelectorAll('.count-line').forEach(input => input.addEventListener('change', () => send(input.dataset.product, input.value, input)));
        document.getElementById('count-save').addEventListener('click', async () => {
            const qty = document.getElementById('count-qty');
            const id = select.getValue();
            const data = await send(id, qty.value, null);
            if (!data) return;
            const line = document.querySelector(`.count-line[data-product="${id}"]`);
            if (line) { line.value = qty.value; line.classList.add('is-valid'); } else { location.reload(); return; }
            qty.value = ''; select.clear(); select.focus();
        });
        document.getElementById('count-qty').addEventListener('keydown', e => { if (e.key === 'Enter') document.getElementById('count-save').click(); });
    });
    </script>
<?php else: ?>
    <?php $editable = $status === 'counted'; ?>
    <?php if ($editable): ?><div class="alert alert-warning">Revisa las diferencias. Las líneas aprobadas generan un ajuste de stock (<?= esc($count['count_number']) ?>) y requieren motivo. Las no aprobadas no mueven stock.</div><?php endif; ?>
    <form method="post" action="<?= $base . '/aprobar' . $q ?>" onsubmit="return confirm('¿Aprobar? Se ajustará el stock de las líneas marcadas.')">
        <?= csrf_field() ?>
        <div class="table-responsive"><table class="table table-sm align-middle">
            <thead><tr><th>SKU</th><th>Producto</th><th class="text-end">Sistema</th><th class="text-end">Contado</th><th class="text-end">Diferencia</th><th class="text-end">%</th><th class="text-end">Valor $</th><th>Aprobar</th><th>Motivo</th></tr></thead>
            <tbody>
            <?php foreach ($count['items'] as $i):
                $counted = $i['counted_at'] !== null; $diff = (float) $i['difference'];
                $pct = $counted && (float) $i['system_quantity'] != 0 ? abs($diff / (float) $i['system_quantity'] * 100) : ($diff != 0 ? 100 : 0);
                $big = $counted && $pct >= $threshold && $diff != 0;
                $traced = (int) $i['lot_control'] || (int) $i['serial_control']; ?>
                <tr class="<?= $big ? 'table-danger' : '' ?>">
                    <td><?= esc($i['sku']) ?></td>
                    <td><?= esc($i['name']) ?><?php if ((int) $i['moved_after_count']): ?> <span class="badge bg-warning text-dark" title="El producto tuvo movimientos después de contarse">movido luego</span><?php endif; ?></td>
                    <?php if (! $counted): ?><td colspan="7" class="text-muted">Sin contar — no se ajusta</td>
                    <?php else: ?>
                        <td class="text-end"><?= $num($i['system_quantity']) ?></td><td class="text-end"><?= $num($i['counted_quantity']) ?></td>
                        <td class="text-end <?= $diff < 0 ? 'text-danger' : ($diff > 0 ? 'text-success' : '') ?>"><?= $num($diff) ?></td>
                        <td class="text-end"><?= number_format($pct, 0) ?>%</td>
                        <td class="text-end"><?= number_format((float) $i['difference_value'], 2, ',', '.') ?></td>
                        <?php if ($diff == 0): ?><td colspan="2" class="text-muted">OK</td>
                        <?php elseif ($traced): ?><td colspan="2" class="text-muted">Con lote/serie: ajustar desde Movimientos</td>
                        <?php else: ?>
                            <td><input type="checkbox" class="form-check-input" name="items[<?= esc($i['id']) ?>][approved]" value="1" <?= ($editable || (int) $i['approved']) ? 'checked' : '' ?> <?= $editable ? '' : 'disabled' ?>></td>
                            <td><?php if ($editable): ?><select name="items[<?= esc($i['id']) ?>][reason]" class="form-select form-select-sm"><option value="">Motivo…</option><?php foreach ($reasons as $r): ?><option><?= esc($r) ?></option><?php endforeach; ?></select>
                                <?php else: ?><?= esc($i['reason'] ?? '') ?><?php endif; ?></td>
                        <?php endif; ?>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><th colspan="6" class="text-end">Diferencia total $</th><th class="text-end"><?= number_format((float) $count['difference_value'], 2, ',', '.') ?></th><th colspan="2"></th></tr></tfoot>
        </table></div>
        <?php if ($editable): ?><button class="btn btn-success"><i class="bi bi-shield-check"></i> Aprobar diferencias y ajustar stock</button><?php endif; ?>
    </form>
    <?php if ($status === 'approved'): ?><p class="text-muted mt-2">Aprobado el <?= esc($count['approved_at']) ?>. Los ajustes figuran en el Kardex con documento <?= esc($count['count_number']) ?>.</p><?php endif; ?>
<?php endif; ?>
<?= $this->endSection() ?>
