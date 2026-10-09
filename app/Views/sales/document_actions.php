<?php
$documentUrl = site_url('ventas/documentos/'.$kind.'/'.(($row['source_type'] ?? '') === 'sale' ? 'sale' : 'cycle').'/'.(($row['sale_id'] ?? null) ?: $row['id'])).'?'.http_build_query(['company_id'=>$companyId]);
?>
<a href="<?= esc($documentUrl) ?>" class="btn btn-sm btn-outline-danger icon-btn" data-popup="true" data-popup-pdf="true" data-popup-title="Visualizar PDF" data-popup-subtitle="Consulta, imprime o descarga el documento." title="Visualizar PDF" aria-label="Visualizar PDF"><i class="bi bi-file-earmark-pdf"></i></a>
<a href="<?= esc($documentUrl.'&download=1') ?>" class="btn btn-sm btn-outline-dark icon-btn" download title="Descargar PDF" aria-label="Descargar PDF"><i class="bi bi-file-earmark-arrow-down"></i></a>
