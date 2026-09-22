<?php
$profile = $user['role_slug'] ?? 'operador';
$intro = $isSuperadmin ? 'Selecciona una empresa para acceder a sus módulos y administrar sus asignaciones.'
    : ($canManageSystems ? 'Accede a tus módulos y organiza los permisos del equipo.'
    : ($profile === 'vendedor' ? 'Tus herramientas de venta y caja, listas para tu jornada.' : 'Encuentra tus herramientas de trabajo y consulta el nivel de acceso asignado.'));
$accessLabels = ['manage' => 'Gestión', 'view' => 'Consulta', 'vendedor' => 'Vendedor'];
?>
<header class="sw-heading">
    <div><h1>Sistemas</h1><p><?= esc($intro) ?></p></div>
    <?php if ($isSuperadmin): ?><a href="<?= site_url('sistemas/nuevo') ?>" class="btn btn-dark sw-primary" data-popup="true" data-popup-title="Nuevo sistema" data-popup-subtitle="Registrar un nuevo sistema del ecosistema." aria-label="Nuevo sistema"><i class="bi bi-window-plus" aria-hidden="true"></i></a><?php endif; ?>
</header>
<section class="sw-context" aria-label="Contexto de trabajo">
    <div class="sw-company"><i class="bi bi-building" aria-hidden="true"></i><div><small>EMPRESA DE TRABAJO</small><strong><?= esc($selectedCompany['name'] ?? 'Sin empresa seleccionada') ?></strong></div></div>
    <?php if ($isSuperadmin && ! empty($companies)): ?>
    <form method="get" action="<?= site_url('sistemas') ?>"><label for="sw-company">Cambiar empresa</label><div><select name="company_id" id="sw-company"><?php foreach ($companies as $company): ?><option value="<?= esc($company['id']) ?>" <?= $selectedCompanyId === $company['id'] ? 'selected' : '' ?>><?= esc($company['name']) ?></option><?php endforeach; ?></select><button type="submit" class="btn btn-dark" aria-label="Aplicar" title="Aplicar"><i class="bi bi-check2" aria-hidden="true"></i></button></div></form>
    <?php else: ?><span class="sw-context-note">Acceso según tus asignaciones</span><?php endif; ?>
</section>
<section class="sw-launcher" aria-label="Sistemas disponibles">
    <?php if ($accessibleSystems): ?>
    <div class="sw-grid">
    <?php foreach ($accessibleSystems as $system): ?>
        <?php
        $level = $system['access_level'] ?? 'view';
        $canEnter = ! empty($system['entry_url']) && $system['entry_url'] !== '#' && (int) ($system['active'] ?? 1) === 1;
        $entryHref = $system['entry_url'];
        if ($canEnter && ! empty($selectedCompanyId) && in_array($system['slug'], ['inventario', 'ventas', 'compras', 'caja', 'contabilidad', 'impuestos', 'comercial'], true)) {
            $entryHref .= (strpos($entryHref, '?') === false ? '?' : '&') . 'company_id=' . rawurlencode($selectedCompanyId);
        }
        ?>
        <article class="sw-module" data-sw-card data-access="<?= esc($level) ?>">
            <div class="sw-module-top"><span class="sw-icon"><i class="bi <?= esc($system['icon'] ?: 'bi-grid') ?>" aria-hidden="true"></i></span><span class="sw-access"><?= esc($accessLabels[$level] ?? 'Consulta') ?></span></div>
            <h3><?= esc($system['name']) ?></h3>
            <p><?= esc($system['description'] ?: 'Herramientas para tu operación diaria.') ?></p>
            <footer><span class="sw-availability"><?= $canEnter ? 'Disponible' : 'Sin acceso disponible' ?></span>
            <?php if ($canEnter): ?><a href="<?= esc($entryHref) ?>" aria-label="<?= esc('Abrir ' . $system['name']) ?>" title="Abrir módulo"><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a><?php else: ?><span class="sw-unavailable">No disponible</span><?php endif; ?></footer>
        </article>
    <?php endforeach; ?>
    </div>
    <?php else: ?><div class="sw-empty"><i class="bi bi-grid" aria-hidden="true"></i><h3>Aún no hay módulos disponibles</h3><p><?= $isSuperadmin ? 'Revisa las asignaciones de la empresa o selecciona otra empresa.' : 'Solicita al administrador que revise tus asignaciones.' ?></p></div><?php endif; ?>
</section>
<?php if ($selectedCompanyId && ($isSuperadmin || $canManageSystems)): ?><div class="sw-section-heading sw-admin-heading"><div><h2>Administración de accesos</h2><p>Revisa los sistemas de la empresa y los permisos de su equipo.</p></div></div><?php endif; ?>
