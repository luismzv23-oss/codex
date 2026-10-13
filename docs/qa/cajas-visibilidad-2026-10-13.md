# Visibilidad de cajas configuradas

## Diagnóstico

La consulta de sesiones activas del administrador no filtra por propietario; la del vendedor sí. Las pruebas aisladas confirman que el mapa utilizado por la card incluye el operador y la sesión abierta del vendedor, restringidos a la empresa consultada.

En la consulta de solo lectura realizada durante el diagnóstico no había sesiones abiertas: las 16 estaban cerradas. Por ello no se atribuye de forma concluyente la captura a un fallo de consulta ni se afirma haber reproducido su estado exacto.

Se encontró una brecha de actualización: existían dos manejadores del botón (uno renovaba solo los indicadores y otro también las tablas), y no se refrescaban los datos al volver desde otra pestaña o ventana donde el vendedor hubiera operado.

## Cambio

- Una sola ruta de actualización para indicadores y tablas, incluido estado, operador y acciones de cada caja.
- Actualización al regresar a la pestaña o ventana; no interrumpe formularios modales abiertos.
- El refresco completo excluye el parámetro de respuesta parcial, valida que recibió la tabla esperada y tiene un límite de espera de 15 segundos.
- Sin cambios en permisos, empresas, aperturas, cierres ni importes.

## Verificación

- `php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit tests/database/CashRegisterVisibilityTest.php --no-coverage`: 2 pruebas, 12 aserciones, SQLite en memoria.
- `node tests/js/cash-dashboard-refresh.cjs`: 5 comprobaciones de coordinación del refresco.
- `php -l app/Views/cash/index.php`: sin errores.

No se realizó una prueba integral con sesiones reales de navegador. Las pruebas de JavaScript verifican eventos y delegación, no el renderizado visual completo. Una pantalla que permanezca visible en otro equipo conserva sus datos hasta actualizarla o recuperar el foco; no se incorporó un canal de actualización entre equipos.
