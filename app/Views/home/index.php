<?= $this->extend('layouts/landing') ?>
<?= $this->section('content') ?>
<?php
// Public product descriptions only. The explorer never reads company data.
$modules = [
    'sales' => ['name' => 'Ventas', 'icon' => '↗', 'tag' => 'DEL MOSTRADOR A LA GESTIÓN', 'title' => 'Cada venta, parte de una visión más grande.', 'description' => 'Organizá tus ventas, trabajá desde el punto de venta y consultá comprobantes e información comercial en una misma plataforma.', 'features' => ['Punto de venta', 'Clientes y comprobantes', 'Listas de precios y promociones'], 'steps' => ['Productos y precios', 'Venta y comprobante', 'Seguimiento comercial'], 'benefit' => 'Más contexto para tus decisiones comerciales.'],
    'inventory' => ['name' => 'Inventario', 'icon' => '◇', 'tag' => 'VISIBILIDAD SOBRE TUS PRODUCTOS', 'title' => 'Conocé tu stock. Planificá tu próximo paso.', 'description' => 'Consultá existencias, organizá depósitos y seguí los movimientos de tus productos para planificar ventas y reposiciones con información.', 'features' => ['Productos y depósitos', 'Movimientos y kardex', 'Reservas y trazabilidad'], 'steps' => ['Ingreso de productos', 'Stock y movimientos', 'Disponibilidad para vender'], 'benefit' => 'Información para anticipar necesidades de reposición.'],
    'purchases' => ['name' => 'Compras', 'icon' => '⌑', 'tag' => 'ABASTECIMIENTO CON ORDEN', 'title' => 'Compras organizadas para seguir avanzando.', 'description' => 'Administrá proveedores, órdenes, recepciones y facturas para dar seguimiento al abastecimiento de tu empresa.', 'features' => ['Gestión de proveedores', 'Órdenes y recepciones', 'Facturas y pagos'], 'steps' => ['Orden de compra', 'Recepción de mercadería', 'Factura y pago'], 'benefit' => 'Una visión más clara de tus compromisos con proveedores.'],
    'cash' => ['name' => 'Caja', 'icon' => '▤', 'tag' => 'CADA MOVIMIENTO CUENTA', 'title' => 'Control para el día a día de tu negocio.', 'description' => 'Organizá aperturas y cierres, registrá movimientos y consultá la actividad de caja para acompañar el control de tus fondos.', 'features' => ['Apertura y cierre de caja', 'Movimientos y medios de pago', 'Conciliaciones'], 'steps' => ['Apertura de caja', 'Movimientos del día', 'Cierre y revisión'], 'benefit' => 'Más orden para revisar el movimiento de tu dinero.'],
    'collections' => ['name' => 'Cobranzas', 'icon' => '◎', 'tag' => 'SEGUIMIENTO DE TUS COBROS', 'title' => 'Tus cuentas por cobrar, a la vista.', 'description' => 'Consultá cuentas pendientes, registrá recibos y aplicá anticipos para mantener organizado el seguimiento de los cobros.', 'features' => ['Cuentas por cobrar', 'Recibos y anticipos', 'Seguimiento de pagos'], 'steps' => ['Cuenta pendiente', 'Registro del cobro', 'Aplicación del recibo'], 'benefit' => 'Información para priorizar el seguimiento de tus cobros.'],
];
$accessUrl = $isAuthenticated ? $dashboardUrl : $loginUrl;
$accessLabel = $isAuthenticated ? 'Ir a mi panel' : 'Ingresar al sistema';
?>
<header class="site-header">
    <div class="wrap header-inner">
        <a class="brand" href="#inicio" aria-label="Codex ERP, inicio"><span class="brand-symbol" aria-hidden="true">C<span>↗</span></span><span>codex<span class="brand-erp">ERP</span></span></a>
        <button class="menu-toggle" type="button" x-cloak x-ref="menuToggle" x-on:click="toggleMenu" x-bind:aria-expanded="menuOpen" aria-controls="navigation"><span aria-hidden="true">☰</span> Menú</button>
        <nav id="navigation" class="site-nav" x-bind:class="menuClass" aria-label="Navegación principal">
            <a href="#beneficios" x-on:click="followLink">Por qué Codex</a>
            <a href="#modulos" x-on:click="followLink">Explorá los módulos</a>
            <a class="nav-access" href="<?= esc($accessUrl) ?>"><?= esc($accessLabel) ?> <span aria-hidden="true">↗</span></a>
        </nav>
    </div>
</header>
<main id="contenido">
    <section class="hero wrap" id="inicio" aria-labelledby="hero-title">
        <div class="hero-copy">
            <p class="eyebrow"><span class="tiny-dot" aria-hidden="true"></span> EL PRÓXIMO PASO DE TU EMPRESA</p>
            <h1 id="hero-title">Tu empresa<br>conectada.<br><span class="gradient-text">Tu crecimiento<br>bajo control.</span></h1>
            <p class="hero-description">Las grandes decisiones empiezan con una visión clara. Conectá ventas, inventario, compras y finanzas con <strong>Codex ERP.</strong></p>
            <div class="actions"><a class="button button-primary" href="<?= esc($accessUrl) ?>"><?= esc($accessLabel) ?> <span aria-hidden="true">↗</span></a><a class="button button-quiet" href="#modulos">Descubrí lo que podés hacer <span aria-hidden="true">↓</span></a></div>
            <div class="hero-footnote"><span class="check-icon" aria-hidden="true">✓</span> Información centralizada <span class="footnote-separator">/</span> Una visión de tu negocio</div>
        </div>
        <div class="hero-visual" aria-label="Representación conceptual de los módulos conectados de Codex">
            <div class="visual-topline"><span><span class="tiny-dot" aria-hidden="true"></span> TU NEGOCIO, CONECTADO</span><span class="visual-id">CODEX / 01</span></div>
            <div class="orbit-scene" aria-hidden="true">
                <div class="orbit orbit-outer"></div><div class="orbit orbit-inner"></div><div class="orbit-axis axis-one"></div><div class="orbit-axis axis-two"></div>
                <svg class="orbit-links" viewBox="0 0 520 440" fill="none"><path d="M260 220L100 105M260 220L425 110M260 220L70 270M260 220L440 285M260 220L260 385" stroke="currentColor" stroke-width="1" stroke-dasharray="4 7"/><circle cx="260" cy="220" r="143" stroke="currentColor" stroke-width=".6"/></svg>
                <div class="core-halo"></div><div class="core"><span class="core-glyph">C<span>↗</span></span><strong>codex</strong><small>EL CENTRO DE TU GESTIÓN</small></div>
                <div class="orbit-node node-sales"><span>↗</span><div><small>COMERCIAL</small><strong>Ventas</strong></div><i></i></div>
                <div class="orbit-node node-inventory"><span>◇</span><div><small>PRODUCTOS</small><strong>Inventario</strong></div><i></i></div>
                <div class="orbit-node node-purchases"><span>⌑</span><div><small>PROVEEDORES</small><strong>Compras</strong></div><i></i></div>
                <div class="orbit-node node-cash"><span>▤</span><div><small>FONDOS</small><strong>Caja</strong></div><i></i></div>
                <div class="orbit-node node-collections"><span>◎</span><div><small>CUENTAS</small><strong>Cobranzas</strong></div><i></i></div>
            </div>
            <div class="visual-bottomline"><span class="signal-bars" aria-hidden="true"><i></i><i></i><i></i><i></i></span><span>Más conexiones. Más perspectiva.</span><span class="visual-plus" aria-hidden="true">+</span></div>
            <p class="illustration-note">Visualización conceptual del producto</p>
        </div>
    </section>
    <div class="capability-strip"><div class="wrap strip-inner"><span>UNA PLATAFORMA.<br><strong>MÁS POSIBILIDADES.</strong></span><span>Ventas <i>↗</i></span><span>Inventario <i>◇</i></span><span>Compras <i>⌑</i></span><span>Caja <i>▤</i></span><span>Cobranzas <i>◎</i></span></div></div>
    <!--<section class="section wrap" id="beneficios" aria-labelledby="benefits-title">
        <div class="section-heading"><div><p class="eyebrow">MENOS DISPERSIÓN. MÁS DIRECCIÓN.</p><h2 id="benefits-title">Espacio para enfocarte<br>en <span class="muted-text">lo que viene.</span></h2></div><p>Crecer también es organizarse. Dale a tu equipo información y herramientas para acompañar cada etapa de tu empresa.</p></div>
        <div class="benefits-grid">
            <article class="benefit-card"><span class="card-index">01 / VISIBILIDAD</span><div class="benefit-art art-connect" aria-hidden="true"><span></span><span></span><span></span><b>+</b></div><h3>Tu información,<br>en un mismo lugar.</h3><p>Conectá las áreas de tu negocio y consultá la información de gestión desde una plataforma común.</p></article>
            <article class="benefit-card"><span class="card-index">02 / ORGANIZACIÓN</span><div class="benefit-art art-flow" aria-hidden="true"><span>✓</span><i></i><span>✓</span><i></i><span>↗</span></div><h3>Más orden.<br>Mejor seguimiento.</h3><p>Organizá ventas, movimientos de stock, compras y cobros para dar continuidad a tus procesos diarios.</p></article>
            <article class="benefit-card"><span class="card-index">03 / PERSPECTIVA</span><div class="benefit-art art-bars" aria-hidden="true"><i></i><i></i><i></i><i></i><span>↗</span></div><h3>Decisiones con<br>más contexto.</h3><p>Consultá reportes y actividad de tu negocio para identificar necesidades y planificar tus próximos pasos.</p></article>
        </div>
    </section>
    <section class="section wrap modules-section" id="modulos" aria-labelledby="modules-title">
        <div class="section-heading"><div><p class="eyebrow">EXPLORÁ TU PRÓXIMA FORMA DE TRABAJAR</p><h2 id="modules-title">Todo conectado.<br><span class="muted-text">Cada área con su lugar.</span></h2></div><p>Elegí un módulo y descubrí cómo puede ayudarte en el día a día de tu empresa.</p></div>
        <div class="module-tabs" role="tablist" aria-label="Módulos de Codex" x-cloak x-on:keydown="moveTab">
            <?php foreach ($modules as $key => $module): ?>
                <button type="button" role="tab" id="tab-<?= esc($key) ?>" x-ref="tab_<?= esc($key) ?>" aria-controls="panel-<?= esc($key) ?>" x-bind:aria-selected="selected === '<?= esc($key) ?>'" x-bind:tabindex="selected === '<?= esc($key) ?>' ? 0 : -1" x-on:click="select('<?= esc($key) ?>')"><span aria-hidden="true"><?= esc($module['icon']) ?></span><?= esc($module['name']) ?></button>
            <?php endforeach; ?>
        </div>
        <?php foreach ($modules as $key => $module): ?>
            <div class="module-panel" id="panel-<?= esc($key) ?>" role="tabpanel" aria-labelledby="tab-<?= esc($key) ?>" tabindex="0" x-show="selected === '<?= esc($key) ?>'" <?php if ($key !== 'sales'): ?>x-cloak<?php endif; ?>>
                <div class="module-copy"><p class="eyebrow"><?= esc($module['tag']) ?></p><h3><?= esc($module['title']) ?></h3><p><?= esc($module['description']) ?></p><ul class="feature-list"><?php foreach ($module['features'] as $feature): ?><li><span aria-hidden="true">✓</span><?= esc($feature) ?></li><?php endforeach; ?></ul><a class="text-link" href="<?= esc($accessUrl) ?>"><?= esc($accessLabel) ?> <span aria-hidden="true">↗</span></a></div>
                <div class="module-demo"><div class="demo-toolbar"><span class="demo-dots" aria-hidden="true">● ● ●</span><span>CODEX / <?= esc(mb_strtoupper($module['name'])) ?></span><span class="demo-badge">Vista ilustrativa</span></div><div class="demo-content"><div class="demo-heading"><span class="demo-icon" aria-hidden="true"><?= esc($module['icon']) ?></span><div><small>UN PROCESO CONECTADO</small><h4><?= esc($module['name']) ?></h4></div><span class="demo-spark" aria-hidden="true">✧</span></div><ol class="process-list"><?php foreach ($module['steps'] as $index => $step): ?><li><span class="step-number">0<?= $index + 1 ?></span><span><?= esc($step) ?></span><span class="step-arrow" aria-hidden="true">↗</span></li><?php endforeach; ?></ol><div class="demo-insight"><span aria-hidden="true">✧</span><p><?= esc($module['benefit']) ?></p></div></div></div>
            </div>
        <?php endforeach; ?>
        <noscript><p class="fallback-note">Conocé también los módulos de Inventario, Compras, Caja y Cobranzas en las tarjetas siguientes. Activá JavaScript para usar el explorador interactivo.</p></noscript>
        <div class="module-summary-grid"><?php foreach ($modules as $key => $module): ?><article><span aria-hidden="true"><?= esc($module['icon']) ?></span><h3><?= esc($module['name']) ?></h3><p><?= esc($module['features'][0]) ?> y <?= esc(mb_strtolower($module['features'][1])) ?>.</p></article><?php endforeach; ?></div>
    </section>
    <section class="section wrap closing-section" aria-labelledby="closing-title"><div class="closing-card"><div class="closing-orbit" aria-hidden="true"></div><p class="eyebrow">TU PRÓXIMO CAPÍTULO EMPIEZA CON UNA VISIÓN CLARA</p><h2 id="closing-title">Tu negocio tiene potencial.<br><span class="gradient-text">Dale una gestión a su altura.</span></h2><p>Conectá tu operación con Codex ERP y construí una base más organizada para acompañar el crecimiento de tu empresa.</p><a class="button button-primary" href="<?= esc($accessUrl) ?>"><?= esc($accessLabel) ?> <span aria-hidden="true">↗</span></a><span class="closing-caption">Ventas · Inventario · Compras · Caja · Cobranzas</span></div></section>
    -->
</main>
<!-- <footer class="site-footer wrap"><a class="brand footer-brand" href="#inicio" aria-label="Codex ERP, volver al inicio">codex<span class="brand-erp">ERP</span></a><p>Una visión más clara. Una empresa más conectada.</p><a href="#inicio">Volver arriba <span aria-hidden="true">↑</span></a></footer>-->
<?= $this->endSection() ?>
