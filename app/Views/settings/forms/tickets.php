<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$currentUser = auth_user();
$isAdminOrSuperadmin = in_array($currentUser['role_slug'] ?? null, ['admin', 'superadmin'], true);
$disabledAttr = !$isAdminOrSuperadmin ? 'disabled' : '';
?>
<div class="row g-4">
    <!-- Left Column: Settings Form -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <div class="mb-4">
                    <h2 class="h5 mb-1">Configuración de Impresión y Tickets</h2>
                    <p class="text-secondary mb-0">Personaliza la cabecera, pie de página, dimensiones y visualización de datos de tus comprobantes de venta.</p>
                </div>

                <!-- Navigation Tabs -->
                <ul class="nav nav-pills mb-4 gap-2 bg-light p-1 rounded-3" id="ticketConfigTab" role="tablist" style="width: fit-content;">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active py-2 px-3 fw-medium" id="pos-tab" data-bs-toggle="tab" data-bs-target="#pos-tab-pane" type="button" role="tab" aria-controls="pos-tab-pane" aria-selected="true">
                            <i class="bi bi-display me-1"></i> Punto de Venta (POS)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-2 px-3 fw-medium" id="kiosk-tab" data-bs-toggle="tab" data-bs-target="#kiosk-tab-pane" type="button" role="tab" aria-controls="kiosk-tab-pane" aria-selected="false">
                            <i class="bi bi-tablet me-1"></i> Kiosco
                        </button>
                    </li>
                </ul>

                <form method="post" action="<?= esc($formAction) . ($isPopup ? '?popup=1' : '') ?>" id="ticket-settings-form">
                    <?= csrf_field() ?>
                    <?php if ($isPopup ?? false): ?><input type="hidden" name="popup" value="1"><?php endif; ?>
                    <?php if (! empty($companyId)): ?><input type="hidden" name="company_id" value="<?= esc($companyId) ?>"><?php endif; ?>

                    <div class="tab-content" id="ticketConfigTabContent">
                        <!-- POS Settings Tab -->
                        <div class="tab-pane fade show active" id="pos-tab-pane" role="tabpanel" aria-labelledby="pos-tab" tabindex="0">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nombre de fantasía / Cabecera (POS)</label>
                                    <input type="text" name="ticket_pos_header_title" id="pos_header_title" class="form-control ticket-input" value="<?= esc($posSettings['header_title'] ?? '') ?>" placeholder="Ej: Distribuidora Duck POS">
                                    <div class="form-text small text-secondary">Si se deja vacío, se usará el nombre legal de la empresa.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Subtítulo / Rubro (POS)</label>
                                    <input type="text" name="ticket_pos_company_subtitle" id="pos_company_subtitle" class="form-control ticket-input" value="<?= esc($posSettings['company_subtitle'] ?? '') ?>" placeholder="Ej: IMPRENTA Y LIBRERIA">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Dirección comercial (POS)</label>
                                    <input type="text" name="ticket_pos_company_address" id="pos_company_address" class="form-control ticket-input" value="<?= esc($posSettings['company_address'] ?? '') ?>" placeholder="Ej: El Salvador 689 - (1406) Capital Federal">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Teléfono (POS)</label>
                                    <input type="text" name="ticket_pos_company_phone" id="pos_company_phone" class="form-control ticket-input" value="<?= esc($posSettings['company_phone'] ?? '') ?>" placeholder="Ej: Tel. 4616-1112 / 4639-0048">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Ancho de papel (POS)</label>
                                    <select name="ticket_pos_paper_width" id="pos_paper_width" class="form-select ticket-input">
                                        <option value="A4" <?= ($posSettings['paper_width'] ?? 'A4') === 'A4' ? 'selected' : '' ?>>A4 Estándar</option>
                                        <option value="letter" <?= ($posSettings['paper_width'] ?? 'A4') === 'letter' ? 'selected' : '' ?>>Carta / Letter</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Tamaño de letra (POS)</label>
                                    <select name="ticket_pos_font_size" id="pos_font_size" class="form-select ticket-input">
                                        <option value="small" <?= ($posSettings['font_size'] ?? 'medium') === 'small' ? 'selected' : '' ?>>Chico (10px)</option>
                                        <option value="medium" <?= ($posSettings['font_size'] ?? 'medium') === 'medium' ? 'selected' : '' ?>>Mediano (12px)</option>
                                        <option value="large" <?= ($posSettings['font_size'] ?? 'medium') === 'large' ? 'selected' : '' ?>>Grande (14px)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Tipografía / Fuente (POS)</label>
                                    <select name="ticket_pos_font_family" id="pos_font_family" class="form-select ticket-input" <?= $disabledAttr ?>>
                                        <option value="DejaVu Sans" <?= ($posSettings['font_family'] ?? 'DejaVu Sans') === 'DejaVu Sans' ? 'selected' : '' ?>>DejaVu Sans (Sans-serif)</option>
                                        <option value="DejaVu Serif" <?= ($posSettings['font_family'] ?? 'DejaVu Sans') === 'DejaVu Serif' ? 'selected' : '' ?>>DejaVu Serif (Serif)</option>
                                        <option value="Courier" <?= ($posSettings['font_family'] ?? 'DejaVu Sans') === 'Courier' ? 'selected' : '' ?>>Courier (Monoespaciada)</option>
                                        <option value="Helvetica" <?= ($posSettings['font_family'] ?? 'DejaVu Sans') === 'Helvetica' ? 'selected' : '' ?>>Helvetica</option>
                                        <option value="Times-Roman" <?= ($posSettings['font_family'] ?? 'DejaVu Sans') === 'Times-Roman' ? 'selected' : '' ?>>Times New Roman</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Leyenda al pie de página (POS)</label>
                                    <textarea name="ticket_pos_footer_notes" id="pos_footer_notes" class="form-control ticket-input" rows="3" placeholder="Ej: Gracias por elegirnos."><?= esc($posSettings['footer_notes'] ?? '') ?></textarea>
                                </div>
                                
                                <!-- Custom Position Texts for POS -->
                                <div class="col-12 mt-3">
                                    <label class="form-label fw-semibold text-dark">Ubicación de textos personalizados en los márgenes de página (A4/Carta)</label>
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label class="form-label small text-secondary mb-1">Cabecera Izquierda (Superior Izq.)</label>
                                            <input type="text" name="ticket_pos_custom_text_top_left" id="pos_custom_text_top_left" class="form-control form-control-sm ticket-input" value="<?= esc($posSettings['custom_text_top_left'] ?? '') ?>" placeholder="Ej: IVA Responsable Inscripto" <?= $disabledAttr ?>>
                                            <div class="form-check form-switch mt-1">
                                                <input class="form-check-input ticket-input" type="checkbox" name="ticket_pos_bold_top_left" id="pos_bold_top_left" value="1" <?= (int) ($posSettings['bold_top_left'] ?? 1) === 1 ? 'checked' : '' ?> <?= $disabledAttr ?>>
                                                <label class="form-check-label small text-secondary" for="pos_bold_top_left">Texto en negrita</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small text-secondary mb-1">Cabecera Derecha (Superior Der.)</label>
                                            <input type="text" name="ticket_pos_custom_text_top_right" id="pos_custom_text_top_right" class="form-control form-control-sm ticket-input" value="<?= esc($posSettings['custom_text_top_right'] ?? '') ?>" placeholder="Ej: CUIT: 30-11223344-5" <?= $disabledAttr ?>>
                                            <div class="form-check form-switch mt-1">
                                                <input class="form-check-input ticket-input" type="checkbox" name="ticket_pos_bold_top_right" id="pos_bold_top_right" value="1" <?= (int) ($posSettings['bold_top_right'] ?? 0) === 1 ? 'checked' : '' ?> <?= $disabledAttr ?>>
                                                <label class="form-check-label small text-secondary" for="pos_bold_top_right">Texto en negrita</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small text-secondary mb-1">Pie Izquierda (Inferior Izq.)</label>
                                            <input type="text" name="ticket_pos_custom_text_bottom_left" id="pos_custom_text_bottom_left" class="form-control form-control-sm ticket-input" value="<?= esc($posSettings['custom_text_bottom_left'] ?? '') ?>" placeholder="Ej: CAI/CAE: 12345678901234" <?= $disabledAttr ?>>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small text-secondary mb-1">Pie Derecha (Inferior Der.)</label>
                                            <input type="text" name="ticket_pos_custom_text_bottom_right" id="pos_custom_text_bottom_right" class="form-control form-control-sm ticket-input" value="<?= esc($posSettings['custom_text_bottom_right'] ?? '') ?>" placeholder="Ej: Vto. CAE: 31/12/2026" <?= $disabledAttr ?>>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-semibold text-dark">Opciones de visualización (POS)</label>
                                    <div class="row g-2 mt-1">
                                        <div class="col-md-6">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input ticket-input" type="checkbox" name="ticket_pos_show_sku" id="pos_show_sku" value="1" <?= (int) ($posSettings['show_sku'] ?? 1) === 1 ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="pos_show_sku">Mostrar SKU del producto</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input ticket-input" type="checkbox" name="ticket_pos_show_brand" id="pos_show_brand" value="1" <?= (int) ($posSettings['show_brand'] ?? 1) === 1 ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="pos_show_brand">Mostrar Marca del producto</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input ticket-input" type="checkbox" name="ticket_pos_show_item_breakdown" id="pos_show_item_breakdown" value="1" <?= (int) ($posSettings['show_item_breakdown'] ?? 1) === 1 ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="pos_show_item_breakdown">Mostrar desglose de cantidades (2 x $100)</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input ticket-input" type="checkbox" name="ticket_pos_show_customer" id="pos_show_customer" value="1" <?= (int) ($posSettings['show_customer'] ?? 1) === 1 ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="pos_show_customer">Mostrar datos del cliente</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input ticket-input" type="checkbox" name="ticket_pos_show_user" id="pos_show_user" value="1" <?= (int) ($posSettings['show_user'] ?? 1) === 1 ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="pos_show_user">Mostrar cajero/vendedor emisor</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Kiosk Settings Tab -->
                        <div class="tab-pane fade" id="kiosk-tab-pane" role="tabpanel" aria-labelledby="kiosk-tab" tabindex="0">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nombre de fantasía / Cabecera (Kiosco)</label>
                                    <input type="text" name="ticket_kiosk_header_title" id="kiosk_header_title" class="form-control ticket-input" value="<?= esc($kioskSettings['header_title'] ?? '') ?>" placeholder="Ej: Distribuidora Duck Kiosco">
                                    <div class="form-text small text-secondary">Si se deja vacío, se usará el nombre legal de la empresa.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Subtítulo / Rubro (Kiosco)</label>
                                    <input type="text" name="ticket_kiosk_company_subtitle" id="kiosk_company_subtitle" class="form-control ticket-input" value="<?= esc($kioskSettings['company_subtitle'] ?? '') ?>" placeholder="Ej: IMPRENTA Y LIBRERIA">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Dirección comercial (Kiosco)</label>
                                    <input type="text" name="ticket_kiosk_company_address" id="kiosk_company_address" class="form-control ticket-input" value="<?= esc($kioskSettings['company_address'] ?? '') ?>" placeholder="Ej: El Salvador 689 - (1406) Capital Federal">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Teléfono (Kiosco)</label>
                                    <input type="text" name="ticket_kiosk_company_phone" id="kiosk_company_phone" class="form-control ticket-input" value="<?= esc($kioskSettings['company_phone'] ?? '') ?>" placeholder="Ej: Tel. 4616-1112 / 4639-0048">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Ancho de papel (Kiosco)</label>
                                    <select name="ticket_kiosk_paper_width" id="kiosk_paper_width" class="form-select ticket-input">
                                        <option value="80mm" <?= ($kioskSettings['paper_width'] ?? '80mm') === '80mm' ? 'selected' : '' ?>>80mm (Térmico Estándar)</option>
                                        <option value="58mm" <?= ($kioskSettings['paper_width'] ?? '80mm') === '58mm' ? 'selected' : '' ?>>58mm (Térmico Angosto)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Tipografía / Fuente (Kiosco)</label>
                                    <select name="ticket_kiosk_font_family" id="kiosk_font_family" class="form-select ticket-input" <?= $disabledAttr ?>>
                                        <option value="Courier" <?= ($kioskSettings['font_family'] ?? 'Courier') === 'Courier' ? 'selected' : '' ?>>Courier (Monoespaciada)</option>
                                        <option value="Helvetica 75 Bold" <?= ($kioskSettings['font_family'] ?? 'Courier') === 'Helvetica 75 Bold' ? 'selected' : '' ?>>Helvetica 75 Bold</option>
                                        <option value="DejaVu Sans" <?= ($kioskSettings['font_family'] ?? 'Courier') === 'DejaVu Sans' ? 'selected' : '' ?>>DejaVu Sans (Sans-serif)</option>
                                        <option value="DejaVu Serif" <?= ($kioskSettings['font_family'] ?? 'Courier') === 'DejaVu Serif' ? 'selected' : '' ?>>DejaVu Serif (Serif)</option>
                                        <option value="Helvetica" <?= ($kioskSettings['font_family'] ?? 'Courier') === 'Helvetica' ? 'selected' : '' ?>>Helvetica</option>
                                        <option value="Times-Roman" <?= ($kioskSettings['font_family'] ?? 'Courier') === 'Times-Roman' ? 'selected' : '' ?>>Times New Roman</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Leyenda al pie de página (Kiosco)</label>
                                    <textarea name="ticket_kiosk_footer_notes" id="kiosk_footer_notes" class="form-control ticket-input" rows="3" placeholder="Ej: Retire su pedido por caja. Gracias por su compra."><?= esc($kioskSettings['footer_notes'] ?? '') ?></textarea>
                                </div>

                                <!-- Custom Position Texts for Kiosco -->
                                <div class="col-12 mt-3">
                                    <label class="form-label fw-semibold text-dark">Ubicación de textos personalizados en las cabeceras (Kiosco)</label>
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label class="form-label small text-secondary mb-1">Cabecera Izquierda (Superior Izq.)</label>
                                            <input type="text" name="ticket_kiosk_custom_text_top_left" id="kiosk_custom_text_top_left" class="form-control form-control-sm ticket-input" value="<?= esc($kioskSettings['custom_text_top_left'] ?? '') ?>" placeholder="Ej: IVA Responsable Inscripto" <?= $disabledAttr ?>>
                                            <div class="form-check form-switch mt-1">
                                                <input class="form-check-input ticket-input" type="checkbox" name="ticket_kiosk_bold_top_left" id="kiosk_bold_top_left" value="1" <?= (int) ($kioskSettings['bold_top_left'] ?? 1) === 1 ? 'checked' : '' ?> <?= $disabledAttr ?>>
                                                <label class="form-check-label small text-secondary" for="kiosk_bold_top_left">Texto en negrita</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small text-secondary mb-1">Cabecera Derecha (Superior Der.)</label>
                                            <input type="text" name="ticket_kiosk_custom_text_top_right" id="kiosk_custom_text_top_right" class="form-control form-control-sm ticket-input" value="<?= esc($kioskSettings['custom_text_top_right'] ?? '') ?>" placeholder="Ej: CUIT: 30-11223344-5" <?= $disabledAttr ?>>
                                            <div class="form-check form-switch mt-1">
                                                <input class="form-check-input ticket-input" type="checkbox" name="ticket_kiosk_bold_top_right" id="kiosk_bold_top_right" value="1" <?= (int) ($kioskSettings['bold_top_right'] ?? 0) === 1 ? 'checked' : '' ?> <?= $disabledAttr ?>>
                                                <label class="form-check-label small text-secondary" for="kiosk_bold_top_right">Texto en negrita</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold text-dark">Opciones de visualización (Kiosco)</label>
                                    <div class="row g-2 mt-1">
                                        <div class="col-md-6">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input ticket-input" type="checkbox" name="ticket_kiosk_show_sku" id="kiosk_show_sku" value="1" <?= (int) ($kioskSettings['show_sku'] ?? 1) === 1 ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="kiosk_show_sku">Mostrar SKU del producto</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input ticket-input" type="checkbox" name="ticket_kiosk_show_brand" id="kiosk_show_brand" value="1" <?= (int) ($kioskSettings['show_brand'] ?? 1) === 1 ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="kiosk_show_brand">Mostrar Marca del producto</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input ticket-input" type="checkbox" name="ticket_kiosk_show_item_breakdown" id="kiosk_show_item_breakdown" value="1" <?= (int) ($kioskSettings['show_item_breakdown'] ?? 1) === 1 ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="kiosk_show_item_breakdown">Mostrar desglose de cantidades (2 x $100)</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input ticket-input" type="checkbox" name="ticket_kiosk_show_customer" id="kiosk_show_customer" value="1" <?= (int) ($kioskSettings['show_customer'] ?? 1) === 1 ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="kiosk_show_customer">Mostrar datos del cliente</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input ticket-input" type="checkbox" name="ticket_kiosk_show_user" id="kiosk_show_user" value="1" <?= (int) ($kioskSettings['show_user'] ?? 1) === 1 ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="kiosk_show_user">Mostrar cajero/vendedor emisor</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Icon-only Buttons to prevent clipping in popups -->
                    <div class="col-12 d-flex gap-2 pt-4 border-top mt-4">
                        <button class="btn btn-dark icon-btn" title="Guardar todo" aria-label="Guardar todo"><i class="bi bi-check-lg"></i></button>
                        <button type="button" class="btn btn-outline-secondary icon-btn" title="Cancelar" aria-label="Cancelar" onclick="window.parent.postMessage({type:'codex-popup-close',redirectUrl:<?= json_encode(site_url('configuracion?company_id=' . ($companyId ?? ''))) ?>}, window.location.origin)"><i class="bi bi-x-lg"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: Interactive Live Preview -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 h-100 bg-light">
            <div class="card-body p-4 d-flex flex-column align-items-center">
                <div class="w-100 mb-3 text-center text-lg-start d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h2 class="h5 mb-1">Vista Previa Interactiva</h2>
                        <p class="text-secondary mb-0" style="font-size: 0.85rem;">Previsualiza en tiempo real cómo lucirá el ticket impreso térmico o documento.</p>
                    </div>
                    <!-- Zoom Controls -->
                    <div class="d-flex align-items-center gap-1 bg-white border rounded-3 p-1 shadow-sm">
                        <button type="button" class="btn btn-sm btn-light border-0 py-1 px-2" id="btn-zoom-out" title="Alejar (Zoom -)" aria-label="Alejar">
                            <i class="bi bi-zoom-out"></i>
                        </button>
                        <span class="small fw-semibold px-2 text-secondary" id="zoom-percentage" style="min-width: 45px; text-align: center;">100%</span>
                        <button type="button" class="btn btn-sm btn-light border-0 py-1 px-2" id="btn-zoom-in" title="Acercar (Zoom +)" aria-label="Acercar">
                            <i class="bi bi-zoom-in"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-light border-0 py-1 px-2 text-danger" id="btn-zoom-reset" title="Restablecer" aria-label="Restablecer">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </button>
                    </div>
                </div>

                <!-- Test Voucher Dropdown for A4/Letter POS -->
                <div id="test-voucher-container" class="w-100 mb-3" style="display: none;">
                    <label class="form-label small fw-semibold text-secondary mb-1">
                        <i class="bi bi-file-earmark-text me-1"></i> Comprobante de prueba (para previsualización)
                    </label>
                    <select id="test_voucher_type" class="form-select form-select-sm border-secondary-subtle rounded-3 shadow-sm py-2">
                        <option value="factura_a" selected>Factura A (Letra A - Cód. 01)</option>
                        <option value="factura_b">Factura B (Letra B - Cód. 06)</option>
                        <option value="factura_c">Factura C (Letra C - Cód. 11)</option>
                        <option value="factura_m">Factura M (Letra M - Cód. 51)</option>
                        <option value="presupuesto">Presupuesto (Letra X - Cód. --)</option>
                        <option value="remito">Remito (Letra R - Cód. --)</option>
                    </select>
                    <div class="form-text small text-secondary" style="font-size: 11px; margin-top: 4px;">
                        La letra y el código AFIP se determinan dinámicamente según el tipo de comprobante seleccionado en la venta POS.
                    </div>
                </div>

                <!-- Ticket Outer Wrapper -->
                <div class="ticket-preview-box w-100 d-flex justify-content-center p-3 border rounded-4 bg-white flex-grow-1 align-items-start" style="min-height: 480px; overflow-y: auto;">
                    <div id="live-ticket" class="ticket-paper shadow-sm">
                        <!-- ======================================================= -->
                        <!-- 1. TICKET FORMAT PREVIEW (80mm / 58mm)                  -->
                        <!-- ======================================================= -->
                        <div id="ticket-format-layout">
                            <!-- Title Header -->
                            <div class="text-center mt-1">
                                <h3 id="preview-ticket-title" class="fw-bold m-0 p-0 fs-6">EMPRESA</h3>
                                <div id="preview-ticket-company-name" class="text-secondary small">Legal Company Name</div>
                                <div class="text-secondary small mt-1">Fecha: <?= date('d/m/Y H:i') ?></div>
                            </div>
                            
                            <div id="preview-ticket-custom-headers" style="display: flex; justify-content: space-between; font-size: 9px; margin-top: 4px; border-bottom: 1px dotted #ccc; padding-bottom: 4px;">
                                <span id="preview-ticket-text-top-left" style="font-weight: bold;">IVA: Responsable Inscripto</span>
                                <span id="preview-ticket-text-top-right" style="text-align: right; white-space: pre-line;">Ing. Brutos: CM. 901-111111-0</span>
                            </div>

                            <div class="ticket-dashed-line" id="preview-top-line"></div>

                            <!-- Items List -->
                            <div id="preview-ticket-items-list" class="my-2"></div>

                            <div class="ticket-dashed-line" id="preview-bottom-line"></div>

                            <!-- Totals -->
                            <div>
                                <div class="d-flex justify-content-between">
                                    <span>Subtotal</span>
                                    <span>$ 3.305,79</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>IVA (21%)</span>
                                    <span>$ 694,21</span>
                                </div>
                                <div class="d-flex justify-content-between fw-bold fs-6 mt-1">
                                    <span>TOTAL</span>
                                    <span>$ 4.000,00</span>
                                </div>
                            </div>

                            <!-- Customer Block -->
                            <div id="preview-ticket-customer-block" class="mt-2 pt-2 border-top border-secondary border-opacity-10 text-start">
                                <strong>Cliente:</strong> Juan Pérez<br>
                                <strong>DNI/CUIT:</strong> CUIT 20-33445566-9
                            </div>

                            <!-- Cashier Block -->
                            <div id="preview-ticket-cashier-block" class="mt-2 text-start">
                                <strong>Cajero:</strong> <?= esc(auth_user()['name'] ?? 'Usuario Demo') ?>
                            </div>

                            <!-- Footer Block -->
                            <div id="preview-ticket-footer-block" class="text-center mt-3 pt-2 border-top border-secondary border-opacity-10 text-muted small" style="white-space: pre-wrap;">
                                Gracias por su compra.
                            </div>
                        </div>

                        <!-- ======================================================= -->
                        <!-- 2. INVOICE FORMAT PREVIEW (A4 / Letter)                 -->
                        <!-- ======================================================= -->
                        <div id="invoice-format-layout" style="display: none;">
                            <!-- Header Box (3 columns layout using CSS Grid or Flex) -->
                            <div style="border: 1px solid #000; display: flex; font-size: 8px; line-height: 1.2;">
                                <!-- Left: Company -->
                                <div style="width: 45%; padding: 6px; border-right: 1px solid #000; text-align: left;">
                                    <h4 id="preview-inv-header-title" style="font-size: 11px; font-weight: bold; margin: 0 0 2px; text-transform: uppercase;">LA IMPRENTA S.A.</h4>
                                    <div id="preview-inv-company-subtitle" style="font-size: 7.5px; font-weight: bold; margin-bottom: 4px;">IMPRENTA Y LIBRERIA</div>
                                    <div style="color: #444;">
                                        <span id="preview-inv-company-address">El Salvador 689 - (1406) Capital Federal</span><br>
                                        <span id="preview-inv-company-phone">Tel. 4616-1112 / 4639-0048</span><br>
                                        <span id="preview-inv-text-top-left" style="color:#000; font-weight: bold;">IVA: Responsable Inscripto</span>
                                    </div>
                                </div>
                                <!-- Center: Badge -->
                                <div style="width: 10%; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 4px; border-right: 1px solid #000; background: #fff;">
                                    <div id="preview-inv-letter-badge" style="border: 2px solid #000; font-size: 18px; font-weight: bold; width: 28px; height: 28px; line-height: 24px; text-align: center; margin-bottom: 2px;">A</div>
                                    <div id="preview-inv-letter-code" style="font-size: 6px; font-weight: bold; text-align: center; white-space: nowrap;">Código Nº 01</div>
                                </div>
                                <!-- Right: Doc Info -->
                                <div style="width: 45%; padding: 6px; text-align: left;">
                                    <h4 id="preview-inv-doc-title" style="font-size: 11px; font-weight: bold; margin: 0 0 2px; text-transform: uppercase;">FACTURA</h4>
                                    <div style="font-size: 8px; font-weight: bold; margin-bottom: 4px;">Nº 0001-00000123</div>
                                    <div style="color: #444;">
                                        <strong>Fecha:</strong> <?= date('d/m/Y') ?><br>
                                        <strong>C.U.I.T.:</strong> 30-68914568-0<br>
                                        <span id="preview-inv-text-top-right" style="white-space: pre-line;">Ing. Brutos: CM. 901-111111-0
Inicio de Actividades: 01/04/1994</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Customer Info Box -->
                            <div style="border: 1px solid #000; border-top: none; padding: 5px 6px; font-size: 8px; text-align: left; line-height: 1.3;">
                                <div style="display: flex; margin-bottom: 2px;">
                                    <div style="width: 15%;"><strong>Señores:</strong></div>
                                    <div style="width: 85%; border-bottom: 1px dotted #ccc;" id="preview-inv-customer-name">Juan Pérez</div>
                                </div>
                                <div style="display: flex; margin-bottom: 2px;">
                                    <div style="width: 15%;"><strong>Dirección:</strong></div>
                                    <div style="width: 85%; border-bottom: 1px dotted #ccc;">Calle Falsa 123</div>
                                </div>
                                <div style="display: flex;">
                                    <div style="width: 15%;"><strong>I.V.A.:</strong></div>
                                    <div style="width: 45%; border-bottom: 1px dotted #ccc;" id="preview-inv-customer-tax">Responsable Inscripto</div>
                                    <div style="width: 10%; text-align: right; padding-right: 4px;"><strong>C.U.I.T.:</strong></div>
                                    <div style="width: 30%; border-bottom: 1px dotted #ccc;">20-33445566-9</div>
                                </div>
                            </div>

                            <!-- Conditions Box -->
                            <div style="border: 1px solid #000; border-top: none; padding: 5px 6px; font-size: 8px; text-align: left; line-height: 1.3;">
                                <div style="display: flex;">
                                    <div style="width: 25%;"><strong>Condiciones de Venta:</strong></div>
                                    <div style="width: 35%; border-bottom: 1px dotted #ccc;">Contado</div>
                                    <div style="width: 15%; text-align: right; padding-right: 4px;"><strong>Remito Nº:</strong></div>
                                    <div style="width: 25%; border-bottom: 1px dotted #ccc;">-</div>
                                </div>
                                <div id="preview-inv-seller-row" style="display: flex; margin-top: 2px;">
                                    <div style="width: 25%;"><strong>Vendedor:</strong></div>
                                    <div style="width: 75%; border-bottom: 1px dotted #ccc;"><?= esc(auth_user()['name'] ?? 'Usuario Demo') ?></div>
                                </div>
                            </div>

                            <!-- Items Table -->
                            <table style="width: 100%; border-collapse: collapse; border: 1px solid #000; border-top: none; font-size: 8px; margin-top: 0;">
                                <thead>
                                    <tr style="background: #f2f2f2; font-weight: bold; border-bottom: 1px solid #000;">
                                        <th style="border-right: 1px solid #000; padding: 3px; width: 15%;">CODIGO</th>
                                        <th style="border-right: 1px solid #000; padding: 3px; width: 10%; text-align: center;">CANT.</th>
                                        <th style="border-right: 1px solid #000; padding: 3px; width: 45%; text-align: left;">DETALLE</th>
                                        <th style="border-right: 1px solid #000; padding: 3px; width: 15%; text-align: right;">P. UNITARIO</th>
                                        <th style="padding: 3px; width: 15%; text-align: right;">TOTAL $</th>
                                    </tr>
                                </thead>
                                <tbody id="preview-inv-items-body"></tbody>
                            </table>

                            <!-- Totals Grid -->
                            <table style="width: 100%; border-collapse: collapse; border: 1px solid #000; border-top: none; font-size: 8px; text-align: center;">
                                <tr style="background: #f2f2f2; font-weight: bold; border-bottom: 1px solid #000;">
                                    <td style="border-right: 1px solid #000; padding: 4px; width: 16.6%;">Subtotal</td>
                                    <td style="border-right: 1px solid #000; padding: 4px; width: 16.6%;">Descuentos</td>
                                    <td style="border-right: 1px solid #000; padding: 4px; width: 16.6%;">Impuestos</td>
                                    <td style="border-right: 1px solid #000; padding: 4px; width: 16.6%;">IVA Insc. 21%</td>
                                    <td style="border-right: 1px solid #000; padding: 4px; width: 16.6%;">IVA No Insc.</td>
                                    <td style="background: #212529; color: #fff; padding: 4px; width: 17%;">TOTAL $</td>
                                </tr>
                                <tr>
                                    <td style="border-right: 1px solid #000; padding: 4px; font-weight: bold;">$ 3.305,79</td>
                                    <td style="border-right: 1px solid #000; padding: 4px;">$ 0,00</td>
                                    <td style="border-right: 1px solid #000; padding: 4px;">$ 694,21</td>
                                    <td style="border-right: 1px solid #000; padding: 4px;">$ 694,21</td>
                                    <td style="border-right: 1px solid #000; padding: 4px;">$ 0,00</td>
                                    <td style="background: #f8fafc; padding: 4px; font-weight: bold; font-size: 10px;">$ 4.000,00</td>
                                </tr>
                            </table>

                            <!-- CAE & Barcode block -->
                            <div style="margin-top: 8px; display: flex; font-size: 8px; justify-content: space-between; align-items: start;">
                                <div style="width: 50%; text-align: left;">
                                    <!-- Barcode representation using thin stripes of repeating-linear-gradient -->
                                    <div style="background: repeating-linear-gradient(90deg, #000, #000 1px, #fff 1px, #fff 3px); width: 120px; height: 20px; margin-bottom: 2px;"></div>
                                    <div style="font-family: monospace; font-size: 7px;">25064106537080</div>
                                </div>
                                <div style="width: 50%; text-align: right; line-height: 1.3;">
                                    <strong>C.A.E. Nº:</strong> 25064106537080<br>
                                    <strong>Fecha de Vto. CAE:</strong> 13/06/2007<br>
                                    <div id="preview-inv-footer-notes" style="font-style: italic; color: #555; margin-top: 2px;"></div>
                                </div>
                            </div>

                            <!-- Bottomest text margins -->
                            <div style="margin-top: 10px; border-top: 1px dotted #ccc; padding-top: 3px; font-size: 6.5px; color: #555; display: flex; justify-content: space-between;">
                                <span id="preview-inv-text-bottom-left"></span>
                                <span id="preview-inv-text-bottom-right"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Custom Styles for Ticket Preview */
.ticket-paper {
    background: #fff;
    border: 1px solid #e2e8f0;
    padding: 18px;
    font-family: 'Courier New', Courier, monospace;
    font-size: 11px;
    color: #1a202c;
    line-height: 1.4;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.ticket-paper.width-58mm {
    width: 230px;
    border-style: solid;
}
.ticket-paper.width-80mm {
    width: 310px;
    border-style: solid;
}
.ticket-paper.width-a4 {
    width: 345px;
    border: 1.5px solid #64748b;
    border-radius: 4px;
    padding: 24px;
}
.ticket-paper.width-letter {
    width: 345px;
    border: 1.5px solid #64748b;
    border-radius: 4px;
    padding: 24px;
}
.ticket-dashed-line {
    border-top: 1px dashed #cbd5e1;
    margin: 8px 0;
}
.ticket-solid-line {
    border-top: 1px solid #94a3b8;
    margin: 8px 0;
}
.ticket-input {
    transition: all 0.2s ease;
}
.ticket-input:focus {
    border-color: #212529;
    box-shadow: 0 0 0 0.25rem rgba(33, 37, 41, 0.1);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const companyName = <?= json_encode($companyName ?? 'Empresa') ?>;
    const companyLegalName = <?= json_encode($companyLegalName ?? 'Legal Name') ?>;
    const companyAddress = <?= json_encode($companyAddress ?? '') ?>;
    const companyPhone = <?= json_encode($companyPhone ?? '') ?>;
    
    // Preview DOM elements
    const liveTicket = document.getElementById('live-ticket');
    
    // Tab buttons
    const posTab = document.getElementById('pos-tab');

    function updatePreview() {
        const isActivePos = posTab.classList.contains('active');
        const prefix = isActivePos ? 'pos' : 'kiosk';

        const headerTitleInput = document.getElementById(`${prefix}_header_title`);
        const companySubtitleInput = document.getElementById(`${prefix}_company_subtitle`);
        const companyAddressInput = document.getElementById(`${prefix}_company_address`);
        const companyPhoneInput = document.getElementById(`${prefix}_company_phone`);
        const paperWidthSelect = document.getElementById(`${prefix}_paper_width`);
        const footerNotesTextarea = document.getElementById(`${prefix}_footer_notes`);
        
        const showSkuCheckbox = document.getElementById(`${prefix}_show_sku`);
        const showBrandCheckbox = document.getElementById(`${prefix}_show_brand`);
        const showBreakdownCheckbox = document.getElementById(`${prefix}_show_item_breakdown`);
        const showCustomerCheckbox = document.getElementById(`${prefix}_show_customer`);
        const showUserCheckbox = document.getElementById(`${prefix}_show_user`);

        // Tab-specific extra controls
        const fontSizeSelect = document.getElementById(`${prefix}_font_size`);
        const fontFamilySelect = document.getElementById(`${prefix}_font_family`);

        const customTitle = headerTitleInput ? headerTitleInput.value.trim() : '';
        const titleText = customTitle !== '' ? customTitle.toUpperCase() : companyName.toUpperCase();
        const subtitleText = companySubtitleInput ? companySubtitleInput.value.trim().toUpperCase() : '';
        const addressText = companyAddressInput ? companyAddressInput.value.trim() : '';
        const phoneText = companyPhoneInput ? companyPhoneInput.value.trim() : '';
        const footerText = footerNotesTextarea ? footerNotesTextarea.value.trim() : '';

        const showSku = showSkuCheckbox ? showSkuCheckbox.checked : true;
        const showBrand = showBrandCheckbox ? showBrandCheckbox.checked : true;
        const showBreakdown = showBreakdownCheckbox ? showBreakdownCheckbox.checked : true;
        const showCustomer = showCustomerCheckbox ? showCustomerCheckbox.checked : true;
        const showUser = showUserCheckbox ? showUserCheckbox.checked : true;

        const paperWidth = paperWidthSelect ? paperWidthSelect.value : '80mm';
        const isPageFormat = isActivePos && (paperWidth === 'A4' || paperWidth === 'letter');

        const testVoucherContainer = document.getElementById('test-voucher-container');
        if (testVoucherContainer) {
            testVoucherContainer.style.display = isPageFormat ? 'block' : 'none';
        }

        if (isPageFormat) {
            document.getElementById('ticket-format-layout').style.display = 'none';
            document.getElementById('invoice-format-layout').style.display = 'block';
        } else {
            document.getElementById('ticket-format-layout').style.display = 'block';
            document.getElementById('invoice-format-layout').style.display = 'none';
        }

        // Apply paper width class
        liveTicket.classList.remove('width-58mm', 'width-80mm', 'width-a4', 'width-letter');
        if (paperWidth === '58mm') {
            liveTicket.classList.add('width-58mm');
        } else if (paperWidth === 'A4') {
            liveTicket.classList.add('width-a4');
        } else if (paperWidth === 'letter') {
            liveTicket.classList.add('width-letter');
        } else {
            liveTicket.classList.add('width-80mm');
        }

        // Apply Font Size (only for POS tab, or if active)
        if (isActivePos && fontSizeSelect) {
            const size = fontSizeSelect.value;
            if (size === 'small') {
                liveTicket.style.fontSize = '9px';
            } else if (size === 'large') {
                liveTicket.style.fontSize = '13px';
            } else {
                liveTicket.style.fontSize = '11px';
            }
        } else {
            liveTicket.style.fontSize = '11px';
        }

        // Apply Font Family
        if (fontFamilySelect) {
            const selectedFont = fontFamilySelect.value;
            if (selectedFont === 'Helvetica 75 Bold') {
                liveTicket.style.fontFamily = '"Helvetica 75 Bold", "Helvetica Neue", Helvetica, Arial, sans-serif';
                liveTicket.style.fontWeight = 'bold';
            } else if (selectedFont === 'DejaVu Sans') {
                liveTicket.style.fontFamily = '"DejaVu Sans", sans-serif';
                liveTicket.style.fontWeight = 'normal';
            } else if (selectedFont === 'DejaVu Serif') {
                liveTicket.style.fontFamily = '"DejaVu Serif", serif';
                liveTicket.style.fontWeight = 'normal';
            } else if (selectedFont === 'Helvetica') {
                liveTicket.style.fontFamily = 'Helvetica, Arial, sans-serif';
                liveTicket.style.fontWeight = 'normal';
            } else if (selectedFont === 'Times-Roman') {
                liveTicket.style.fontFamily = '"Times New Roman", Times, serif';
                liveTicket.style.fontWeight = 'normal';
            } else {
                liveTicket.style.fontFamily = '"Courier New", Courier, monospace';
                liveTicket.style.fontWeight = 'normal';
            }
        } else {
            liveTicket.style.fontFamily = "'Courier New', Courier, monospace";
            liveTicket.style.fontWeight = 'normal';
        }

        if (isPageFormat) {
            // Update A4 layout fields
            document.getElementById('preview-inv-header-title').textContent = titleText;
            document.getElementById('preview-inv-footer-notes').textContent = footerText;

            // Subtitle, Address, Phone
            const previewSubtitle = document.getElementById('preview-inv-company-subtitle');
            if (previewSubtitle) {
                previewSubtitle.textContent = subtitleText;
                previewSubtitle.style.display = subtitleText !== '' ? 'block' : 'none';
            }
            const previewAddress = document.getElementById('preview-inv-company-address');
            if (previewAddress) {
                const finalAddressText = addressText !== '' ? addressText : companyAddress;
                previewAddress.textContent = finalAddressText !== '' ? finalAddressText : 'El Salvador 689 - (1406) Capital Federal';
            }
            const previewPhone = document.getElementById('preview-inv-company-phone');
            if (previewPhone) {
                const finalPhoneText = phoneText !== '' ? phoneText : companyPhone;
                previewPhone.textContent = finalPhoneText !== '' ? finalPhoneText : 'Tel. 4616-1112 / 4639-0048';
            }

            // Update letter badge dynamically based on test voucher selection
            const testVoucherType = document.getElementById('test_voucher_type').value;
            let letter = 'A';
            let code = 'Código Nº 01';
            let docTitle = 'FACTURA';

            if (testVoucherType === 'factura_b') {
                letter = 'B';
                code = 'Código Nº 06';
                docTitle = 'FACTURA';
            } else if (testVoucherType === 'factura_c') {
                letter = 'C';
                code = 'Código Nº 11';
                docTitle = 'FACTURA';
            } else if (testVoucherType === 'factura_m') {
                letter = 'M';
                code = 'Código Nº 51';
                docTitle = 'FACTURA';
            } else if (testVoucherType === 'presupuesto') {
                letter = 'X';
                code = 'Código Nº --';
                docTitle = 'PRESUPUESTO';
            } else if (testVoucherType === 'remito') {
                letter = 'R';
                code = 'Código Nº --';
                docTitle = 'REMITO';
            }

            document.getElementById('preview-inv-letter-badge').textContent = letter;
            document.getElementById('preview-inv-letter-code').textContent = code;
            document.getElementById('preview-inv-doc-title').textContent = docTitle;

            // Margins position texts
            const topLeftVal = document.getElementById('pos_custom_text_top_left').value.trim() || 'IVA: Responsable Inscripto';
            const topRightVal = document.getElementById('pos_custom_text_top_right').value.trim() || 'Ing. Brutos: CM. 901-111111-0\nInicio de Actividades: 01/04/1994';
            const bottomLeftVal = document.getElementById('pos_custom_text_bottom_left').value.trim() || 'Imprenta Su Imprenta CUIT: 30-12345678-9 Habil. 22222';
            const bottomRightVal = document.getElementById('pos_custom_text_bottom_right').value.trim() || 'Fecha Impresión: <?= date("d/m/Y") ?> Numeración: 0001-00001601 al 0001-00001700';

            document.getElementById('preview-inv-text-top-left').textContent = topLeftVal;
            document.getElementById('preview-inv-text-top-right').textContent = topRightVal;
            document.getElementById('preview-inv-text-bottom-left').textContent = bottomLeftVal;
            document.getElementById('preview-inv-text-bottom-right').textContent = bottomRightVal;

            const boldTopLeft = document.getElementById(`${prefix}_bold_top_left`) ? document.getElementById(`${prefix}_bold_top_left`).checked : true;
            const boldTopRight = document.getElementById(`${prefix}_bold_top_right`) ? document.getElementById(`${prefix}_bold_top_right`).checked : false;
            document.getElementById('preview-inv-text-top-left').style.fontWeight = boldTopLeft ? 'bold' : 'normal';
            document.getElementById('preview-inv-text-top-right').style.fontWeight = boldTopRight ? 'bold' : 'normal';

            // Visibility checkboxes
            document.getElementById('preview-inv-customer-name').textContent = showCustomer ? 'Juan Pérez' : 'Consumidor Final';
            document.getElementById('preview-inv-customer-tax').textContent = showCustomer ? 'Responsable Inscripto' : '-';
            document.getElementById('preview-inv-seller-row').style.display = showUser ? 'flex' : 'none';

            // Items list in Table format
            const item1BrandHtml = showBrand ? `<span style="display:block; font-style:italic; font-size:7.5px;">Coca Cola</span>` : '';
            const item1BreakdownHtml = showBreakdown ? `<span style="display:block; font-size:7.5px; color:#555;">2.00 u x $ 1.100,00</span>` : '';

            const item2BrandHtml = showBrand ? `<span style="display:block; font-style:italic; font-size:7.5px;">Pringles</span>` : '';
            const item2BreakdownHtml = showBreakdown ? `<span style="display:block; font-size:7.5px; color:#555;">1.00 u x $ 1.800,00</span>` : '';

            document.getElementById('preview-inv-items-body').innerHTML = `
                <tr style="border-bottom: 1px solid #ccc;">
                    <td style="border-right: 1px solid #000; padding: 4px; font-family: monospace;">${showSku ? 'PROD-004' : '-'}</td>
                    <td style="border-right: 1px solid #000; padding: 4px; text-align: center;">2.00</td>
                    <td style="border-right: 1px solid #000; padding: 4px;">
                        <strong>Coca Cola 1.5L</strong>
                        ${item1BrandHtml}
                        ${item1BreakdownHtml}
                    </td>
                    <td style="border-right: 1px solid #000; padding: 4px; text-align: right;">$ 1.100,00</td>
                    <td style="padding: 4px; text-align: right; font-weight: bold;">$ 2.200,00</td>
                </tr>
                <tr style="border-bottom: 1px solid #ccc;">
                    <td style="border-right: 1px solid #000; padding: 4px; font-family: monospace;">${showSku ? 'PROD-012' : '-'}</td>
                    <td style="border-right: 1px solid #000; padding: 4px; text-align: center;">1.00</td>
                    <td style="border-right: 1px solid #000; padding: 4px;">
                        <strong>Pringles Original 124g</strong>
                        ${item2BrandHtml}
                        ${item2BreakdownHtml}
                    </td>
                    <td style="border-right: 1px solid #000; padding: 4px; text-align: right;">$ 1.800,00</td>
                    <td style="padding: 4px; text-align: right; font-weight: bold;">$ 1.800,00</td>
                </tr>
            `;

        } else {
            // Update Ticket layout fields
            document.getElementById('preview-ticket-title').textContent = titleText;
            document.getElementById('preview-ticket-company-name').textContent = customTitle !== '' ? companyLegalName : '';
            document.getElementById('preview-ticket-company-name').style.display = customTitle !== '' ? 'block' : 'none';
            document.getElementById('preview-ticket-footer-block').textContent = footerText;
            document.getElementById('preview-ticket-footer-block').style.display = footerText !== '' ? 'block' : 'none';

            // Custom headers in ticket preview
            const ticketTopLeft = document.getElementById('preview-ticket-text-top-left');
            const ticketTopRight = document.getElementById('preview-ticket-text-top-right');
            const ticketCustomHeaders = document.getElementById('preview-ticket-custom-headers');
            
            const activeTopLeft = document.getElementById(`${prefix}_custom_text_top_left`) ? document.getElementById(`${prefix}_custom_text_top_left`).value.trim() : '';
            const activeTopRight = document.getElementById(`${prefix}_custom_text_top_right`) ? document.getElementById(`${prefix}_custom_text_top_right`).value.trim() : '';
            const activeBoldTopLeft = document.getElementById(`${prefix}_bold_top_left`) ? document.getElementById(`${prefix}_bold_top_left`).checked : true;
            const activeBoldTopRight = document.getElementById(`${prefix}_bold_top_right`) ? document.getElementById(`${prefix}_bold_top_right`).checked : false;

            if (ticketTopLeft) {
                ticketTopLeft.textContent = activeTopLeft || 'IVA: Responsable Inscripto';
                ticketTopLeft.style.fontWeight = activeBoldTopLeft ? 'bold' : 'normal';
            }
            if (ticketTopRight) {
                ticketTopRight.textContent = activeTopRight || 'Ing. Brutos: CM. 901-111111-0';
                ticketTopRight.style.fontWeight = activeBoldTopRight ? 'bold' : 'normal';
            }
            if (ticketCustomHeaders) {
                ticketCustomHeaders.style.display = (activeTopLeft || activeTopRight) ? 'flex' : 'none';
            }

            // Visibility checkboxes
            document.getElementById('preview-ticket-customer-block').style.display = showCustomer ? 'block' : 'none';
            document.getElementById('preview-ticket-cashier-block').style.display = showUser ? 'block' : 'none';

            // Items list in Ticket format
            const item1SkuHtml = showSku ? `<span style="display:block;">[PROD-004]</span>` : '';
            const item1BrandHtml = showBrand ? `<span style="display:block; font-style:italic;">Marca: Coca Cola</span>` : '';
            const item1BreakdownHtml = showBreakdown ? `<span style="display:block; font-size:10px;">2.00 u x $ 1.100,00</span>` : '';
            
            const item2SkuHtml = showSku ? `<span style="display:block;">[PROD-012]</span>` : '';
            const item2BrandHtml = showBrand ? `<span style="display:block; font-style:italic;">Marca: Pringles</span>` : '';
            const item2BreakdownHtml = showBreakdown ? `<span style="display:block; font-size:10px;">1.00 u x $ 1.800,00</span>` : '';

            document.getElementById('preview-ticket-items-list').innerHTML = `
                <div class="mb-2">
                    <div class="d-flex justify-content-between">
                        <span>Coca Cola 1.5L</span>
                        <span class="fw-bold">$ 2.200,00</span>
                    </div>
                    <div class="text-secondary small ms-1" style="font-size:9.5px;">
                        ${item1SkuHtml}
                        ${item1BrandHtml}
                        ${item1BreakdownHtml}
                    </div>
                </div>
                <div class="mb-2">
                    <div class="d-flex justify-content-between">
                        <span>Pringles Original 124g</span>
                        <span class="fw-bold">$ 1.800,00</span>
                    </div>
                    <div class="text-secondary small ms-1" style="font-size:9.5px;">
                        ${item2SkuHtml}
                        ${item2BrandHtml}
                        ${item2BreakdownHtml}
                    </div>
                </div>
            `;
        }
    }

    document.querySelectorAll('.ticket-input').forEach(input => {
        input.addEventListener('input', updatePreview);
        input.addEventListener('change', updatePreview);
    });

    const testVoucherSelect = document.getElementById('test_voucher_type');
    if (testVoucherSelect) {
        testVoucherSelect.addEventListener('change', updatePreview);
    }

    const configTabElList = document.querySelectorAll('button[data-bs-toggle="tab"]');
    configTabElList.forEach(tabEl => {
        tabEl.addEventListener('shown.bs.tab', () => {
            updatePreview();
        });
    });

    // Zoom control logic
    let zoomLevel = 1.0;
    const zoomPercentageEl = document.getElementById('zoom-percentage');
    const btnZoomIn = document.getElementById('btn-zoom-in');
    const btnZoomOut = document.getElementById('btn-zoom-out');
    const btnZoomReset = document.getElementById('btn-zoom-reset');

    function applyZoom() {
        zoomLevel = Math.round(zoomLevel * 10) / 10;
        zoomPercentageEl.textContent = `${Math.round(zoomLevel * 100)}%`;
        liveTicket.style.zoom = zoomLevel;
    }

    if (btnZoomIn && btnZoomOut && btnZoomReset) {
        btnZoomIn.addEventListener('click', () => {
            if (zoomLevel < 2.0) {
                zoomLevel += 0.1;
                applyZoom();
            }
        });
        btnZoomOut.addEventListener('click', () => {
            if (zoomLevel > 0.5) {
                zoomLevel -= 0.1;
                applyZoom();
            }
        });
        btnZoomReset.addEventListener('click', () => {
            zoomLevel = 1.0;
            applyZoom();
        });
    }

    updatePreview();
});
</script>
<?= $this->endSection() ?>
