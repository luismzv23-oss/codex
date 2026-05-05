<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="mb-3"><h2 class="h5 mb-0">Nuevo Asiento Contable</h2></div>
<form method="post" action="<?= esc($formAction) ?>" id="entryForm">
    <?= csrf_field() ?>
    <?php if ($isPopup ?? false): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
    <div class="row g-3 mb-3">
        <div class="col-md-3"><label class="form-label">Fecha</label><input type="date" name="entry_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
        <div class="col-md-6"><label class="form-label">Descripcion</label><input type="text" name="description" class="form-control" required placeholder="Descripcion del asiento"></div>
        <div class="col-md-3"><label class="form-label">Contabilizar</label><select name="auto_post" class="form-select"><option value="0">Guardar como borrador</option><option value="1">Contabilizar al guardar</option></select></div>
    </div>
    <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-header bg-light rounded-top-4 d-flex justify-content-between align-items-center">
            <span class="fw-semibold">Lineas del asiento</span>
            <button type="button" class="btn btn-outline-dark btn-sm" onclick="addLine()"><i class="bi bi-plus-lg"></i> Linea</button>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm align-middle mb-0" id="linesTable">
                <thead class="table-light"><tr><th>Cuenta</th><th>Descripcion</th><th>Debe</th><th>Haber</th><th></th></tr></thead>
                <tbody id="linesBody">
                    <tr data-line="0">
                        <td><select name="lines[0][account_id]" class="form-select form-select-sm" required><?php foreach ($accounts ?? [] as $a): ?><option value="<?= esc($a['id']) ?>"><?= esc($a['code']) ?> — <?= esc($a['name']) ?></option><?php endforeach; ?></select></td>
                        <td><input type="text" name="lines[0][description]" class="form-control form-control-sm"></td>
                        <td><input type="number" step="0.01" name="lines[0][debit]" class="form-control form-control-sm debit-input" value="0" oninput="updateTotals()"></td>
                        <td><input type="number" step="0.01" name="lines[0][credit]" class="form-control form-control-sm credit-input" value="0" oninput="updateTotals()"></td>
                        <td><button type="button" class="btn btn-outline-danger btn-sm icon-btn" onclick="this.closest('tr').remove();updateTotals()"><i class="bi bi-trash"></i></button></td>
                    </tr>
                    <tr data-line="1">
                        <td><select name="lines[1][account_id]" class="form-select form-select-sm" required><?php foreach ($accounts ?? [] as $a): ?><option value="<?= esc($a['id']) ?>"><?= esc($a['code']) ?> — <?= esc($a['name']) ?></option><?php endforeach; ?></select></td>
                        <td><input type="text" name="lines[1][description]" class="form-control form-control-sm"></td>
                        <td><input type="number" step="0.01" name="lines[1][debit]" class="form-control form-control-sm debit-input" value="0" oninput="updateTotals()"></td>
                        <td><input type="number" step="0.01" name="lines[1][credit]" class="form-control form-control-sm credit-input" value="0" oninput="updateTotals()"></td>
                        <td><button type="button" class="btn btn-outline-danger btn-sm icon-btn" onclick="this.closest('tr').remove();updateTotals()"><i class="bi bi-trash"></i></button></td>
                    </tr>
                </tbody>
                <tfoot class="table-dark"><tr>
                    <td colspan="2" class="fw-bold">Totales</td>
                    <td class="fw-bold" id="totalDebit">0,00</td>
                    <td class="fw-bold" id="totalCredit">0,00</td>
                    <td id="balanceIndicator"></td>
                </tr></tfoot>
            </table>
        </div>
    </div>
    <div class="text-end"><button class="btn btn-dark"><i class="bi bi-save"></i> Guardar asiento</button></div>
</form>
<script>
let lineIdx = 2;
const accountOptions = `<?php foreach ($accounts ?? [] as $a): ?><option value="<?= esc($a['id']) ?>"><?= esc($a['code']) ?> — <?= esc($a['name']) ?></option><?php endforeach; ?>`;
function addLine() {
    const row = document.createElement('tr');
    row.dataset.line = lineIdx;
    row.innerHTML = `<td><select name="lines[${lineIdx}][account_id]" class="form-select form-select-sm" required>${accountOptions}</select></td>
        <td><input type="text" name="lines[${lineIdx}][description]" class="form-control form-control-sm"></td>
        <td><input type="number" step="0.01" name="lines[${lineIdx}][debit]" class="form-control form-control-sm debit-input" value="0" oninput="updateTotals()"></td>
        <td><input type="number" step="0.01" name="lines[${lineIdx}][credit]" class="form-control form-control-sm credit-input" value="0" oninput="updateTotals()"></td>
        <td><button type="button" class="btn btn-outline-danger btn-sm icon-btn" onclick="this.closest('tr').remove();updateTotals()"><i class="bi bi-trash"></i></button></td>`;
    document.getElementById('linesBody').appendChild(row);
    lineIdx++;
}
function updateTotals() {
    let d = 0, c = 0;
    document.querySelectorAll('.debit-input').forEach(i => d += parseFloat(i.value || 0));
    document.querySelectorAll('.credit-input').forEach(i => c += parseFloat(i.value || 0));
    document.getElementById('totalDebit').textContent = d.toLocaleString('es-AR', {minimumFractionDigits:2});
    document.getElementById('totalCredit').textContent = c.toLocaleString('es-AR', {minimumFractionDigits:2});
    const diff = Math.abs(d - c);
    document.getElementById('balanceIndicator').innerHTML = diff < 0.01 ? '<span class="text-success">✓</span>' : '<span class="text-danger">Dif: ' + diff.toFixed(2) + '</span>';
}
</script>
<?= $this->endSection() ?>
