# Controles compartidos con Alpine

## Alcance

El layout `layouts/app` usa `codex-controls.js` antes del runtime local Alpine CSP. Ya no carga Bootstrap JavaScript ni Tom Select. Bootstrap CSS e iconos se conservan. El layout antiguo `layouts/main.php` conserva sus dependencias; no se afirma una migración completa de todo el código JavaScript del repositorio.

- Navegación: apertura móvil, menús excluyentes, cierre exterior/Escape y navegación con teclado. Se mantienen condiciones PHP de permisos y enlaces.
- Pestañas: ventas diarias, clientes y diseño de tickets. El evento `codex:tab-shown` mantiene la actualización de la vista previa de tickets.
- Diálogos: búsquedas de ventas y kiosco, exportación de clientes y aviso de caja abierta. `CodexDialogs` conserva los métodos `show`/`hide`; el diálogo nativo contiene el foco. Eventos `codex:dialog-shown` y `codex:dialog-hidden` sustituyen los eventos Bootstrap en sus consumidores.
- Búsquedas en selectores: componentes del kit, facturas y órdenes de compra. Un filtro Alpine acompaña al select nativo; las opciones originales, nombres, metadatos, validación y eventos siguen siendo los del formulario. Buscar no cambia la selección ni envía el formulario. Las filas agregadas se inicializan por Alpine.
- Cierre de sesión: consulta de caja en cada acción, sin caché, con timeout y recuperación ante errores conservados.

No se modificaron rutas, controladores, base de datos ni permisos en este bloque.

## Verificación local

- `node tests/frontend/shared-controls.cjs`: 68 comprobaciones (1440 y 390 px): menús, teclado, pestañas/eventos, diálogos/foco/cierre, asociación del formulario, estado de caja y errores, búsqueda con acentos, metadatos, selección, reset, deshabilitado y filas dinámicas.
- Regresiones de navegador: `administration-workspaces.cjs`, `operational-workspaces.cjs`, `inventory-workspace.cjs`, `inventory-product-form.cjs` aprobadas.
- Nueve suites PHP de vistas/dashboard/inventario: 31 pruebas, 318 aserciones aprobadas.
- `PosTicketDesignTest` y `KioskPaymentsTest`: 15 pruebas, 46 aserciones aprobadas.
- Sintaxis PHP de las vistas intervenidas y sintaxis del controlador JS verificadas.

Los ensayos de navegador usan fixtures y respuestas simuladas para el estado de caja. No acreditan ventas, compras, exportación ni cierre de sesión completos con usuarios reales. La validación operativa debe recorrer dichos formularios en la sesión habitual, además de abrir/cerrar los buscadores y probar las pestañas POS/Kiosco.
