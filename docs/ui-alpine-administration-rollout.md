# Tercera etapa: administración

## Entrega

Identidad clara compartida en Usuarios, Empresas, Sistemas y Configuración, incluidas sus rutas de formularios que usan layouts/app. Se conservan turquesa, celeste y violeta, con superficies claras y texto azul profundo.

- usersDirectory: inicialización Alpine CSP del directorio existente; búsqueda, filtros, tamaño de página y navegación conservados.
- systemsWorkspace: controlador del catálogo y asignaciones extraído de la vista; refresco y contratos de operaciones existentes conservados.
- settingsWorkspace: paginación y actualización por eventos conservadas, incluida la comprobación de empresa al actualizar una card.
- codexTablePages: paginador común trasladado al componente Alpine del contenedor principal. Empresas utiliza esta implementación; se evita la doble inicialización del script anterior.
- Popups: contenedor Alpine compartido de las etapas anteriores.

No se modificaron servicios, controladores, permisos, registros del catálogo, asignaciones ni datos de empresa. No se ejecutaron altas, bajas o cambios sobre datos reales.

## Validación

AdministrationViewTest: 23 aserciones sobre las cuatro vistas, una sola carga de Alpine y conservación del modo de solo lectura de Usuarios.

Chrome con HTML renderizado por PHP y datos sintéticos: 84 comprobaciones a 1440 y 390 px. Incluyen búsqueda, paginación, apertura/cierre de popups y devolución del foco, refresco del catálogo sin pérdida de filas, rechazo de eventos de otra empresa en Configuración y selección de la página del registro guardado.

Capturas revisadas. Los listados anchos conservan desplazamiento dentro de su contenedor en móvil; la página no presenta desbordamiento horizontal. Las respuestas de actualización se simulan: no acredita circuitos autenticados ni envíos reales de formularios.

Se repite la regresión de paneles operativos tras mover el paginador común. Bootstrap JS, Tom Select y controles especializados pendientes siguen presentes: no se declara una migración completa de todo el sistema a Alpine.

## Reproducción

```powershell
$env:CODEX_UI_PREVIEW='1'
php -d extension=sqlite3 vendor/bin/phpunit --no-logging --do-not-cache-result tests/database/AdministrationViewTest.php
node tests/frontend/administration-workspaces.cjs
```

Las capturas y HTML de prueba están en writable/private/ui-operational, excluido de Git. No es necesario iniciar un servidor.
