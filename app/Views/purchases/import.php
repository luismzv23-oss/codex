<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $purchaseEditing=true; ?>
<?= view('purchases/shell',get_defined_vars()) ?>
<link rel="stylesheet" href="<?= base_url('assets/css/purchase-import.css') ?>">
<div id="purchase-import" class="import-wizard" data-base="<?= site_url('compras/documentos') ?>" data-company="<?= esc($companyId) ?>" data-csrf="<?= csrf_hash() ?>" data-csrf-header="<?= csrf_header() ?>">
    <div class="import-heading"><div><span class="import-eyebrow">ASISTENTE DE COMPRAS</span><h2>Del comprobante a tu compra</h2><p>Sube la factura, revisa los datos y conserva el original en tu archivo seguro.</p></div><span class="import-private"><i class="bi bi-shield-lock"></i> Archivo privado</span></div>
    <ol class="import-steps" aria-label="Pasos de la importación"><li data-step-label="0">1 <span>Documento</span></li><li data-step-label="1">2 <span>Datos</span></li><li data-step-label="2">3 <span>Productos</span></li><li data-step-label="3">4 <span>Revisión</span></li></ol>
    <div id="import-status" role="status" aria-live="polite"></div>
    <div class="import-layout">
    <div class="import-editor">
        <section data-step="0">
            <label class="import-drop" id="import-drop" for="import-file"><i class="bi bi-cloud-arrow-up"></i><strong>Arrastra tu factura o selecciona un archivo</strong><span>PDF, JPG, JPEG o PNG · Hasta 10 MB · Lectura de hasta 20 páginas o 20 megapíxeles</span><input id="import-file" type="file" accept="application/pdf,image/jpeg,image/png,.pdf,.jpg,.jpeg,.png"></label>
            <p class="small text-secondary mt-3">La lectura se realiza localmente. Siempre podrás corregir los datos antes de confirmar.</p>
            <div class="import-list-heading"><h3>Archivo de facturas</h3><input id="import-search" class="form-control" placeholder="Buscar documento" aria-label="Buscar documento archivado"></div>
            <div id="import-documents"></div><div class="import-pager" id="import-documents-pager"></div>
        </section>
        <section data-step="1" hidden>
            <h3>Datos del comprobante</h3><p class="text-secondary">Contrasta los campos sugeridos con el documento original.</p>
            <div class="import-fields">
                <label class="wide">Proveedor<select class="form-select" data-field="supplier_id" required></select></label>
                <div class="wide"><a href="<?= site_url('compras/proveedores/nuevo?company_id='.rawurlencode($companyId)) ?>" data-popup="true" data-popup-title="Nuevo proveedor" class="btn btn-sm btn-outline-dark">Nuevo proveedor</a></div>
                <label class="wide">Número completo de factura<input class="form-control" data-field="invoice_number" maxlength="60" placeholder="Ej.: A 00001-00001234" required><small>Incluye la letra del comprobante cuando corresponda.</small></label>
                <label>Emisión<input class="form-control" type="date" data-field="issue_date" required></label>
                <label>Vencimiento<input class="form-control" type="date" data-field="due_date"></label>
                <label>Moneda<select class="form-select" data-field="currency_code"></select></label>
                <label>Cotización<input class="form-control" type="number" min="0.000001" step="0.000001" data-field="exchange_rate"></label>
                <label class="wide">Recepción asociada<select class="form-select" data-field="purchase_receipt_id"></select><small>Vincula la recepción existente. Esta importación registra la factura; la entrada de stock se mantiene en Recepciones.</small></label>
                <label class="wide">Notas<textarea class="form-control" data-field="notes" rows="2" maxlength="3000"></textarea></label>
            </div>
        </section>
        <section data-step="2" hidden>
            <div class="import-list-heading"><div><h3>Productos y cantidades</h3><p>Costos netos, antes de IVA. Revisa cada coincidencia sugerida.</p></div><button type="button" class="btn btn-outline-dark" id="import-add"><i class="bi bi-plus-lg"></i> Agregar</button></div>
            <p><a href="<?= site_url('inventario/productos/nuevo?company_id='.rawurlencode($companyId)) ?>" data-popup="true" data-popup-title="Nuevo producto" class="btn btn-sm btn-outline-dark">Nuevo producto</a></p><div id="import-items"></div><div class="import-pager" id="import-items-pager"></div>
            <div class="import-totals" id="import-item-totals"></div>
        </section>
        <section data-step="3" hidden>
            <h3>Revisar y registrar</h3><p class="text-secondary">Escribe los importes del original. Deben coincidir con la suma de los renglones.</p>
            <div class="import-fields">
                <label>Neto del documento<input class="form-control" type="number" min="0" step="0.01" data-field="expected_subtotal" required></label>
                <label>IVA del documento<input class="form-control" type="number" min="0" step="0.01" data-field="expected_tax" required></label>
                <label class="wide">Total del documento<input class="form-control" type="number" min="0" step="0.01" data-field="expected_total" required></label>
            </div>
            <div class="import-totals" id="import-review-totals"></div><div id="import-difference" role="status"></div>
            <p class="small text-secondary">Si el documento incluye otros impuestos o conceptos no representados, conserva el borrador y resuelve su tratamiento antes de registrar. No ajustes el IVA para forzar una coincidencia.</p>
            <label class="import-reviewed"><input type="checkbox" id="import-reviewed"> Revisé el proveedor, las fechas, cada producto y los importes contra el original.</label>
            <div class="import-effect"><i class="bi bi-info-circle"></i> Se registrará la factura usando el circuito de Compras y se vinculará su original cifrado. No se registrará un pago ni una recepción adicional.</div>
        </section>
        <section id="import-success" hidden><i class="bi bi-check-circle import-success-icon"></i><h3>Factura registrada</h3><p>El comprobante y su original quedaron vinculados. Puedes consultarlo desde Compras.</p><a class="btn btn-outline-dark" id="import-download" download>Descargar original</a><button type="button" class="btn btn-dark" id="import-another">Importar otra</button></section>
    </div>
    <aside class="import-preview" id="import-preview" hidden><div><strong id="import-filename"></strong><a id="import-original" download title="Descargar original" aria-label="Descargar original"><i class="bi bi-download"></i></a></div><div class="import-image-wrap"><img id="import-frame" alt="Vista previa del comprobante original"></div><div id="import-preview-pages" class="import-pager"></div><details><summary>Texto reconocido · requiere revisión</summary><pre id="import-text"></pre></details></aside>
    </div>
    <footer class="import-footer" id="import-footer" hidden><button type="button" class="btn btn-outline-dark" id="import-back">Anterior</button><span id="import-save-status" class="small text-secondary"></span><div><button type="button" class="btn btn-outline-dark" id="import-save">Guardar borrador</button><button type="button" class="btn btn-dark" id="import-next">Continuar <i class="bi bi-arrow-right"></i></button></div></footer>
</div>
<script id="import-options" type="application/json"><?= json_encode($options,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?></script>
<script src="<?= base_url('assets/js/purchase-import.js') ?>" defer></script>
<?= view('purchases/end') ?>
<?= $this->endSection() ?>
