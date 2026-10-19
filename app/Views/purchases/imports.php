<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/purchase-imports.css').'?v='.filemtime(FCPATH.'assets/css/purchase-imports.css') ?>">
<?php
$configuration=['companyId'=>$companyId,'baseUrl'=>site_url('compras/importaciones'),'csrfHeader'=>config('Security')->headerName,
    'csrfToken'=>csrf_hash(),'ocrConfigured'=>$ocrConfigured,'autoRead'=>$ocrConfigured && config('PurchaseImport')->provider==='local','maxBytes'=>config('PurchaseImport')->maxBytes,
    'suppliers'=>array_map(static fn($s)=>array_intersect_key($s,array_flip(['id','name','tax_id'])),$suppliers),
    'products'=>$products,'receipts'=>$receipts,'currencies'=>$currencies,'pdfModule'=>base_url('assets/lib/pdfjs/pdf.mjs'),
    'pdfWorker'=>base_url('assets/lib/pdfjs/pdf.worker.mjs'),'pdfAssets'=>rtrim(base_url('assets/lib/pdfjs/'),'/').'/'];
?>
<div class="invoice-assistant" id="invoice-assistant">
<header class="invoice-assistant-head">
    <div><span class="invoice-eyebrow">COMPRAS / DOCUMENTOS</span><h1>Asistente de facturas</h1><p>Del documento a tu registro de compras, con una revisión clara en cada paso.</p></div>
    <a href="<?= site_url('compras?company_id='.$companyId) ?>" class="btn btn-outline-dark icon-btn" title="Volver a Compras" aria-label="Volver a Compras"><i class="bi bi-arrow-left"></i></a>
</header>
<ol class="invoice-steps" aria-label="Etapas de carga">
<?php foreach (['Subir','Leer','Revisar','Vincular','Registrar'] as $index=>$label): ?><li data-import-step="<?= $index+1 ?>"><span><?= $index+1 ?></span><?= esc($label) ?></li><?php endforeach; ?>
</ol>
<div id="import-message" class="invoice-message" role="status" aria-live="polite" hidden></div>
<section id="import-upload-panel" class="invoice-upload-layout">
    <div class="invoice-dropzone" id="invoice-dropzone" tabindex="0" role="button" aria-label="Seleccionar factura PDF o imagen">
        <div class="invoice-upload-orbit"><i class="bi bi-cloud-arrow-up" aria-hidden="true"></i></div>
        <h2>Arrastra tu factura aquí</h2><p>También puedes seleccionar el archivo desde tu equipo.</p>
        <span class="invoice-chip">PDF · JPG · PNG</span><span class="invoice-chip">Hasta 10 MB · 20 páginas</span>
        <input id="invoice-file" type="file" accept=".pdf,.jpg,.jpeg,.png" class="visually-hidden" tabindex="-1">
        <p class="small mt-3">Una factura por archivo. El original queda asociado al registro.</p>
    </div>
    <aside class="invoice-recent"><h2 class="h5">Continuar una carga</h2><p class="text-secondary small">Retoma un borrador o consulta una factura registrada.</p>
        <ul class="list-unstyled invoice-draft-list">
        <?php foreach ($drafts as $draft): ?><li><div><strong><?= esc(json_decode($draft['data_json'] ?: '{}',true)['invoice_number'] ?? 'Factura en revisión') ?></strong>
            <small><?= esc($draft['created_at']) ?> · <?= $draft['review_status']==='registered'?'Registrada':'Borrador' ?></small></div>
            <a class="btn btn-outline-dark icon-btn" href="<?= site_url('compras/importaciones?'.http_build_query(['company_id'=>$companyId,'import'=>$draft['id']])) ?>" title="Abrir carga" aria-label="Abrir carga"><i class="bi bi-folder2-open"></i></a></li><?php endforeach; ?>
        <?php if (!$drafts): ?><li class="text-secondary">Tus próximas cargas aparecerán aquí.</li><?php endif; ?>
        </ul>
    </aside>
</section>
<section id="import-workspace" hidden>
    <div class="invoice-workbar">
        <div><strong id="import-filename"></strong><small id="import-processing" role="status"></small></div>
        <div class="invoice-actions">
            <button type="button" id="import-analyze" class="btn btn-outline-dark icon-btn" title="Leer documento automáticamente" aria-label="Leer documento automáticamente"><i class="bi bi-stars"></i></button>
            <button type="button" id="import-apply" class="btn btn-outline-dark icon-btn" title="Aplicar sugerencias a campos vacíos" aria-label="Aplicar sugerencias a campos vacíos" hidden><i class="bi bi-magic"></i></button>
            <button type="button" id="import-save" class="btn btn-outline-dark icon-btn" title="Guardar borrador" aria-label="Guardar borrador"><i class="bi bi-cloud-check"></i></button>
            <button type="button" id="import-recovery" class="btn btn-outline-dark icon-btn" title="Descargar copia de los datos en edición" aria-label="Descargar copia de los datos en edición"><i class="bi bi-download"></i></button>
            <button type="button" id="import-reload" class="btn btn-outline-dark icon-btn" title="Actualizar borrador" aria-label="Actualizar borrador"><i class="bi bi-arrow-clockwise"></i></button>
        </div>
    </div>
    <div class="invoice-review-layout">
        <aside class="invoice-preview">
            <div class="invoice-preview-tools"><span>Documento original</span><div class="invoice-actions">
                <button id="import-page-prev" type="button" class="btn btn-outline-dark icon-btn" title="Página anterior" aria-label="Página anterior"><i class="bi bi-chevron-left"></i></button>
                <span id="import-page-label">1 / 1</span>
                <button id="import-page-next" type="button" class="btn btn-outline-dark icon-btn" title="Página siguiente" aria-label="Página siguiente"><i class="bi bi-chevron-right"></i></button>
                <a id="import-original" target="_blank" rel="noopener" class="btn btn-outline-dark icon-btn" title="Abrir original" aria-label="Abrir original"><i class="bi bi-box-arrow-up-right"></i></a>
            </div></div>
            <div id="import-preview-stage"><div id="import-preview-sheet"><canvas id="import-pdf-canvas" hidden></canvas><img id="import-preview-image" alt="Factura original" hidden><div id="import-highlight" hidden></div></div></div>
            <p id="import-source-note" class="small text-secondary p-3 mb-0">Selecciona un dato para consultar su ubicación cuando esté disponible.</p>
        </aside>
        <div class="invoice-editor">
            <div class="invoice-editor-title"><div><span class="invoice-eyebrow" id="import-stage-caption">REVISIÓN ASISTIDA</span><h2 id="import-stage-title">Datos de la factura</h2></div><span id="import-save-status" class="invoice-chip" role="status">Guardado</span></div>
            <div id="import-inline-message" class="invoice-message m-3" hidden></div>
            <div id="import-reading-panel" class="invoice-section" hidden>
                <div class="invoice-reading-symbol"><i class="bi bi-file-earmark-richtext"></i></div><h3>Tu documento está listo para revisar</h3>
                <p>La lectura local se solicita al cargar el archivo. Cuando termine, se completarán los campos vacíos para que los revises. Puedes continuar manualmente mientras se procesa.</p>
                <?php if (!$ocrConfigured): ?><p class="invoice-help">La lectura automática todavía no está configurada. Puedes continuar con la carga manual y conservar el original.</p><?php elseif (config('PurchaseImport')->provider==='local'): ?><p class="invoice-help">La factura se lee en este servidor. Revisa los datos propuestos antes de registrarla.</p><?php else: ?><p class="invoice-help">La lectura utiliza el servicio documental externo configurado para esta aplicación.</p><?php endif; ?>
            </div>
            <form id="import-form" novalidate>
            <div id="import-header-panel" class="invoice-section">
                <div class="invoice-field-grid">
                <?php
                $fields=['supplier_id'=>['Proveedor','supplier'],'supplier_tax_id'=>['CUIT del emisor','text'],'receiver_tax_id'=>['CUIT del destinatario','text'],
                    'document_type'=>['Tipo de comprobante','document_type'],'point_of_sale'=>['Punto de venta del proveedor','text'],'invoice_sequence'=>['Número de comprobante','text'],
                    'issue_date'=>['Fecha de emisión','date'],'due_date'=>['Vencimiento','date'],'currency_code'=>['Moneda','currency'],'exchange_rate'=>['Cotización','number']];
                foreach ($fields as $key=>[$label,$type]): ?>
                <label class="invoice-field"><span><?= esc($label) ?><small data-confidence="<?= esc($key) ?>"></small></span>
                    <?php if (in_array($type,['supplier','document_type','currency'],true)): ?><select class="form-select" data-field="<?= esc($key) ?>" aria-label="<?= esc($label) ?>">
                        <option value="">Selecciona una opción</option>
                        <?php if ($type==='supplier'): foreach ($suppliers as $supplier): ?><option value="<?= esc($supplier['id']) ?>"><?= esc($supplier['name']) ?></option><?php endforeach; ?>
                        <?php elseif ($type==='currency'): foreach ($currencies as $currency): ?><option value="<?= esc($currency['code']) ?>"><?= esc($currency['code'].' · '.$currency['name']) ?></option><?php endforeach; ?>
                        <?php else: foreach (['FACTURA_A'=>'Factura A','FACTURA_B'=>'Factura B','FACTURA_C'=>'Factura C','FACTURA_M'=>'Factura M','FACTURA_E'=>'Factura E','OTRO'=>'Otro comprobante'] as $value=>$text): ?><option value="<?= esc($value) ?>"><?= esc($text) ?></option><?php endforeach; endif; ?>
                    </select><?php else: ?><input class="form-control" data-field="<?= esc($key) ?>" type="<?= esc($type) ?>" <?= $type==='number'?'min="0.000001" step="0.000001"':'' ?> maxlength="180"><?php endif; ?>
                </label><?php endforeach; ?>
                </div>
                <p class="invoice-help mt-3">Los campos detectados son sugerencias. Comprueba el emisor y que el documento corresponda a tu empresa.</p>
            </div>
            <div id="import-link-panel" class="invoice-section" hidden>
                <div class="invoice-field-grid">
                    <label class="invoice-field"><span>Destino de la compra</span><select data-field="purchase_kind" class="form-select"><option value="goods">Mercadería: vincular recepción existente</option><option value="pending_receipt">Mercadería pendiente de recibir</option><option value="service">Servicios o gastos</option></select></label>
                    <label class="invoice-field"><span>Recepción asociada</span><select data-field="purchase_receipt_id" class="form-select"><option value="">Sin recepción</option><?php foreach ($receipts as $receipt): ?><option value="<?= esc($receipt['id']) ?>" data-supplier="<?= esc($receipt['supplier_id']) ?>" data-currency="<?= esc($receipt['currency_code']) ?>"><?= esc($receipt['receipt_number']) ?></option><?php endforeach; ?></select></label>
                </div>
                <p class="invoice-help mt-3">Vincula productos usando su código o nombre. Registrar esta factura no ingresa mercadería al stock.</p>
                <datalist id="import-products"><?php foreach ($products as $product): ?><option value="<?= esc($product['sku'].' · '.$product['name']) ?>"></option><?php endforeach; ?></datalist>
                <div class="invoice-subheading"><h3 class="h6">Conceptos del documento</h3><button id="import-add-item" class="btn btn-outline-dark icon-btn" type="button" title="Agregar concepto" aria-label="Agregar concepto"><i class="bi bi-plus-lg"></i></button></div>
                <div id="import-items"></div>
                <nav id="import-items-pagination" class="d-flex align-items-center justify-content-between gap-2 my-3" aria-label="Páginas de conceptos">
                    <span id="import-items-page-status" class="small text-secondary" aria-live="polite"></span>
                    <div class="d-flex align-items-center gap-2">
                        <button id="import-items-prev" type="button" class="btn btn-outline-dark icon-btn" title="Conceptos anteriores" aria-label="Conceptos anteriores"><i class="bi bi-chevron-left"></i></button>
                        <span id="import-items-page-number" class="small"></span>
                        <button id="import-items-next" type="button" class="btn btn-outline-dark icon-btn" title="Conceptos siguientes" aria-label="Conceptos siguientes"><i class="bi bi-chevron-right"></i></button>
                    </div>
                </nav>
                <div class="invoice-subheading"><h3 class="h6">Impuestos adicionales, cargos y descuentos</h3><button id="import-add-adjustment" class="btn btn-outline-dark icon-btn" type="button" title="Agregar ajuste" aria-label="Agregar ajuste"><i class="bi bi-plus-lg"></i></button></div>
                <p class="invoice-help">Agrega únicamente importes que no estén incluidos en los conceptos. Revisa la clasificación de los impuestos detectados.</p>
                <div id="import-adjustments"></div>
            </div>
            <div id="import-confirm-panel" class="invoice-section" hidden>
                <div id="import-review-summary"></div>
                <label class="invoice-field mt-3"><span>Total que figura en el documento</span><input data-field="reviewed_total" type="number" min="0.01" step="0.01" class="form-control"></label>
                <label class="invoice-field mt-3"><span>Observaciones</span><textarea data-field="notes" rows="2" class="form-control" maxlength="2000"></textarea></label>
                <label class="invoice-final-review"><input type="checkbox" data-field="identity_reviewed"> Revisé emisor, destinatario, numeración, moneda e importes contra el documento original.</label>
                <button id="import-confirm" class="btn btn-dark icon-btn" type="button" title="Registrar factura revisada" aria-label="Registrar factura revisada"><i class="bi bi-check2-all"></i></button>
                <div id="import-success" class="invoice-success" role="status" hidden></div>
            </div>
            </form>
            <div class="invoice-totals" aria-live="polite"><div><small>Neto de conceptos</small><strong id="import-net">0,00</strong></div><div><small>IVA de conceptos</small><strong id="import-tax">0,00</strong></div><div><small>Ajustes</small><strong id="import-extra">0,00</strong></div><div><small>Total calculado</small><strong id="import-total">0,00</strong></div><p id="import-difference"></p></div>
            <div class="invoice-stage-actions"><button id="import-prev" type="button" class="btn btn-outline-dark icon-btn" title="Paso anterior" aria-label="Paso anterior"><i class="bi bi-arrow-left"></i></button><span id="import-stage-help">Revisa los datos antes de continuar.</span><button id="import-next" type="button" class="btn btn-dark icon-btn" title="Continuar" aria-label="Continuar"><i class="bi bi-arrow-right"></i></button></div>
        </div>
    </div>
</section>
</div>
<script id="import-config" type="application/json"><?= json_encode($configuration,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
<script type="module" src="<?= base_url('assets/js/purchase-imports.js').'?v='.filemtime(FCPATH.'assets/js/purchase-imports.js') ?>"></script>
<?= $this->endSection() ?>
