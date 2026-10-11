# Revision de cobranzas - 2026-10-10

Estado: migracion verificada y cuentas de cobranzas configuradas. Cinco escenarios HTTP y concurrencia aprobados en MySQL/MariaDB aislado. Este resultado acredita el alcance probado, no una garantia universal de ausencia de errores.

## Reglas confirmadas por el usuario

- Anticipos por cliente, empresa y moneda, aplicables posteriormente a una o varias facturas sin generar una venta al recibirlos.
- Aplicacion inmediata de cada medio recibido; transferencias solo al verificarlas.
- Cuentas separadas para tarjetas, cheques y billeteras, con acreditacion manual al banco.
- Pruebas aisladas, sin operaciones reales; maximo cinco casos.

## Implementado

- Recibos normales y mixtos con aplicaciones parciales y confirmacion individual de transferencias.
- Anticipos y su aplicacion posterior sin duplicar fondos, con identificador de operacion para reintentos.
- Saldos a favor originados en devoluciones de ventas pagadas; aplicacion o reintegro del saldo disponible.
- Contabilidad de cobros directos y recibos; cuentas separadas por medio y acreditacion manual del importe completo.
- Reversos de recibos y de ventas canceladas sin cobros pendientes de resolver. Reintegros conservan su historial.
- Validacion de empresa, cliente, moneda, cuentas activas y propiedad de la caja; rollback ante fallos.
- Formularios web, rutas API y configuracion contable para administradores. Aplicaciones con cinco filas por pagina sin recargar.
- Migracion ExtendCollections con compatibilidad para recibos anteriores y bloqueo de downgrade cuando existen operaciones nuevas.

## Validacion previa de servicios

1. Cobro mixto efectivo/tarjeta/cheque/billetera, acreditaciones, reverso idempotente y cancelacion contable de venta sin deuda.
2. Efectivo inmediato y dos transferencias verificadas por separado; reintentos y anulacion de recibo parcial.
3. Rollback contable y de fondos, restricciones de empresa/cliente/caja, moneda y referencias; configuracion de cuentas, migracion historica y renderizado del formulario.
4. Anticipo, reintento, aplicaciones parciales a varias facturas, rechazo de excesos y reintegro del remanente.
5. Transferencia sobre venta parcialmente devuelta, cobro directo, acreditacion, contabilizacion de devolucion y saldo a favor reintegrable.

Resultado final: 5 pruebas, 87 aserciones correctas, SQLite en memoria. Sintaxis PHP y JavaScript comprobada. No se ejecuto la suite completa.

## Habilitacion realizada

- `2026-10-10-120000_ExtendCollections` ya figuraba aplicada el 10/10/2026 a las 14:01:59, lote 11. Se verificaron las columnas nuevas y `collection_requests`; no se repitio la migracion sobre datos reales.
- Se ejecuto `php spark collections:configure --apply` el 10/10/2026 a las 14:39. Quedaron siete asignaciones por empresa (21 en total), conservando cuentas existentes y creando 13 cuentas faltantes, sin saldos iniciales ni asientos.
- Distribuidora Duck y Empresa Demo: Caja `1.1.01`, Banco `1.1.02`, Clientes `1.1.04`, Anticipos `2.1.09`, Tarjetas `1.1.90`, Cheques `1.1.91`, Billeteras `1.1.92`.
- Empresa Ejemplo: Caja `1.1.90`, Banco `1.1.91`, Clientes `1.1.92`, Anticipos `2.1.90`, Tarjetas `1.1.93`, Cheques `1.1.94`, Billeteras `1.1.95`.
- Comparacion SHA-256 de todas las filas de ventas, cuentas por cobrar, recibos, aplicaciones, pagos, movimientos de caja y asientos: sin cambios durante la configuracion.

## Cinco escenarios HTTP/MySQL

Comando reproducible: `php tests/collections_mysql_http_check.php`.

1. Migracion en esquema aislado, configuracion reversible e idempotente, login web y JWT, permisos vigentes, bloqueo de tokens invalidos/usuarios inactivos/acceso a otra empresa, formulario, alta, detalle y listado web de recibos.
2. Cobro mixto por API, transferencia individual, acreditacion de tarjeta/cheque/billetera, reintentos, anulacion de recibo, transferencia directa y cancelacion de venta impaga. Venta cobrada no admite cancelacion.
3. Anticipo, aplicacion a varias facturas, reintegro e idempotencia; rechazo de excesos, cliente ajeno y falta de cuenta con rollback completo. Devolucion por API con saldo a favor y reintegro desde el formulario web.
4. Dos solicitudes HTTP simultaneas con igual clave devuelven el mismo recibo; dos confirmaciones simultaneas generan un solo movimiento y pago.
5. Dos cobros compiten por la misma factura y dos aplicaciones compiten por el mismo anticipo: solo uno prospera. Todos los asientos balancean y la cuenta contable Clientes concilia con la suma de cuentas por cobrar.

Resultado: **5 escenarios, 76 verificaciones correctas**, MySQLi sobre **MariaDB 10.4.32**, con modo estricto y esquema real clonado sin datos de negocio. Dos servidores HTTP independientes y sesiones distintas; se comprobo que ambas transacciones esperaban el bloqueo de MySQL antes de liberarlo. Se eliminaron la base temporal y los procesos al terminar.

Se detecto y corrigio un defecto de integracion JWT: las rutas con `apipermission` consultaban exclusivamente la sesion web y no autenticaban el token. Ahora autentican primero, resuelven usuario activo y permisos actuales desde la base, y comparten esa identidad con los controladores sin crear una sesion web autenticada.

La validacion HTTP usa rutas, filtros, CSRF, controladores y formularios reales; no simula clics ni evalua el aspecto visual en un navegador. No se invocaron servicios fiscales o bancarios externos.

## Limites explicitos

- Se conserva la restriccion de moneda base; no se implemento conversion de divisas.
- La acreditacion manual registra el importe completo, sin conciliacion bancaria externa ni calculo automatico de comisiones.
- No se regularizaron asientos historicos ni se generaron ventas, cobros o reintegros reales.
- Un saldo originado en devoluciones o con reintegros no admite anulacion destructiva: conserva historial y permite gestionar el remanente.
