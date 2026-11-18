<?php
// Datos de lote / número de serie para aprobar la diferencia de un producto con trazabilidad.
$name = 'items[' . esc($item['id']) . ']';
$diff = (float) $item['difference'];
$lotControl = (int) $item['lot_control'] === 1;
$serialControl = (int) $item['serial_control'] === 1;
$need = abs($diff);
?>
<div class="trace-box mt-2 p-2 rounded-3 border bg-light small" data-need="<?= esc((string) $need) ?>">
<?php if ($diff > 0): ?>
    <div class="fw-semibold mb-1"><i class="bi bi-box-arrow-in-down"></i> Sobrante de <?= $num($need) ?></div>
    <?php if ($lotControl): ?>
        <div class="row g-1">
            <div class="col-7"><input type="text" name="<?= $name ?>[lot_number]" class="form-control form-control-sm" maxlength="60" placeholder="Lote (por defecto <?= esc($count['count_number']) ?>)" aria-label="Lote del sobrante"></div>
            <div class="col-5"><input type="date" name="<?= $name ?>[expiration_date]" class="form-control form-control-sm" title="Vencimiento (opcional)" aria-label="Vencimiento del sobrante"></div>
        </div>
    <?php endif; ?>
    <?php if ($serialControl): ?><div class="text-secondary mt-1">Las unidades sobrantes entran sin número de serie.</div><?php endif; ?>
<?php elseif ($trace['serials_required']): ?>
    <div class="fw-semibold mb-1"><i class="bi bi-upc-scan"></i> Elegí las <?= (int) $need ?> series que faltan</div>
    <select name="<?= $name ?>[serials][]" class="trace-serials" multiple data-need="<?= (int) $need ?>" aria-label="Números de serie faltantes">
        <?php foreach ($trace['serials'] as $s): ?><option value="<?= esc($s['serial_number']) ?>"><?= esc($s['serial_number'] . ($s['lot_number'] ? ' · lote ' . $s['lot_number'] : '')) ?></option><?php endforeach; ?>
    </select>
    <div class="text-secondary mt-1 trace-serials-count">0 de <?= (int) $need ?> elegidas</div>
<?php else: ?>
    <div class="fw-semibold mb-1"><i class="bi bi-box-arrow-up"></i> Faltante de <?= $num($need) ?></div>
    <?php if ($lotControl && $trace['lots']): ?>
        <div class="text-secondary mb-1">Reparto por lote (primero el que vence antes, podés cambiarlo):</div>
        <?php $left = $need; foreach ($trace['lots'] as $lot): $take = min($left, (float) $lot['quantity_balance']); $left -= $take; ?>
            <div class="input-group input-group-sm mb-1">
                <span class="input-group-text flex-grow-1"><?= esc($lot['lot_number']) ?> · saldo <?= $num($lot['quantity_balance']) ?><?= $lot['expiration_date'] ? ' · vence ' . esc(date('d/m/Y', strtotime($lot['expiration_date']))) : '' ?></span>
                <input type="number" step="0.01" min="0" max="<?= esc((string) $lot['quantity_balance']) ?>" name="<?= $name ?>[lots][<?= esc($lot['id']) ?>]" value="<?= $take > 0 ? esc((string) $take) : '' ?>" class="form-control trace-lot" style="max-width:90px" aria-label="Cantidad del lote <?= esc($lot['lot_number']) ?>">
            </div>
        <?php endforeach; ?>
        <?php if ($left > 0.004): ?><div class="text-warning-emphasis">Los lotes no cubren <?= $num($left) ?>: esa parte se ajusta sin lote.</div><?php endif; ?>
    <?php elseif ($lotControl): ?>
        <div class="text-warning-emphasis">No hay lotes con saldo en el depósito: se ajusta sin lote.</div>
    <?php endif; ?>
    <?php if ($serialControl): ?><div class="text-secondary mt-1">Las series no están cargadas por unidad (hay <?= count($trace['serials']) ?> disponibles): se ajusta sin número de serie y queda anotado.</div><?php endif; ?>
<?php endif; ?>
</div>
