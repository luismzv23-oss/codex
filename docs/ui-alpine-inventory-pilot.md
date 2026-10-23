# Identidad clara y piloto Alpine de Inventario

## Alcance entregado

La identidad turquesa, celeste, violeta y azul profundo se conserva en una variante clara para Inventario y el formulario compartido de productos. Portada y login mantienen su presentación oscura. Activación progresiva en layouts/app.php para /inventario, alta/edición de productos y las rutas POST que pueden devolver errores del formulario.

- codex-tokens.css: valores visuales compartidos.
- codex-components.css: campos, botones, tablas, foco, paginación y popups.
- codex-workspace.css: estructura clara y ajustes de escritorio/móvil.
- inventory-workspace.js: componente Alpine CSP local para inicialización, búsqueda, filtros y actualización; conserva paginadores y contratos de eventos existentes.
- codex-ui.js: componente Alpine para el contenedor de popups del piloto, manteniendo iframe, mensajes de guardado y redimensionado. Añade foco inicial, devolución del foco, Escape y fondo inerte.
- components/form_actions.php: acciones compartidas con SVG local.
- Formulario de productos: conserva Alpine CSP existente y campos enviados; etiquetas vinculadas a sus controles.

No hay cambios de esquema, controladores, autorizaciones, cálculos de stock, reglas de kits ni precios. No se ejecutaron operaciones sobre productos reales. La búsqueda sigue siendo sobre las filas que el servidor entrega; el tamaño de página sigue siendo cinco.

Se corrigió una referencia visual obsoleta: al refrescar tbody, el estado vacío de la búsqueda debe consultar los nuevos elementos.

## Verificación

- InventoryProductFormTest: 7 pruebas, 78 aserciones.
- InventoryDashboardTest: 5 pruebas, 31 aserciones.
- Chrome con Alpine CSP real y HTML renderizado por PHP, datos sintéticos: 16 comprobaciones a 1440 px y 16 a 390 px (viewport DevTools real).
- Formulario: 20 comprobaciones de comportamiento de simple/kit; vista clara sin desbordamiento de página a 1280 y 390 px.
- Revisión de capturas de escritorio y móvil; sintaxis PHP/JS y git diff --check.

Los ensayos de navegador usan una respuesta de actualización simulada. No constituyen una prueba de sesión autenticada ni un envío multipart contra el servidor real. Bootstrap CSS y fuentes conservan sus cargas existentes.

## Reproducción (PowerShell)

```powershell
$env:INVENTORY_FORM_PREVIEW='1'
php -d extension=sqlite3 vendor/bin/phpunit --no-logging --do-not-cache-result tests/database/InventoryDashboardTest.php
php -d extension=sqlite3 vendor/bin/phpunit --no-logging --do-not-cache-result tests/database/InventoryProductFormTest.php
node tests/frontend/inventory-product-form.cjs
node tests/frontend/inventory-workspace.cjs
```

Los HTML de prueba y capturas se generan en writable/private/ui-pilot/ (excluido de Git). No se inicia un servidor web.

## Pendiente de la siguiente fase

Validación visual del usuario antes de extender la identidad a los otros módulos. La navegación global y el selector especializado Tom Select mantienen sus mecanismos actuales; este bloque no declara migrado todo Core ni eliminadas todas las dependencias de Bootstrap JS. Los módulos fuera del piloto siguen con su presentación y runtime anteriores. Las tablas anchas conservan desplazamiento dentro de su contenedor en móvil.
