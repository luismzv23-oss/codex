# Revision de cobranzas

Alcance: revision de codigo del circuito web/API, recibos, pagos, caja y contabilidad. Sin operaciones reales ni cambios en datos productivos.

## Correcciones

- Los recibos desglosan sus debitos contables: efectivo en Caja y otros medios en Banco. Requieren los mapeos correspondientes; si faltan, la transaccion se revierte.
- Las transferencias pendientes vinculadas directamente a ventas generan un asiento de cobro al confirmarse. El identificador del pago evita asientos duplicados en reintentos.
- La confirmacion de ventas contabiliza sus pagos inmediatos por separado del asiento de venta, dentro de la misma transaccion y sin duplicarlos al reintentar.
- El saldo y el estado de pago se calculan considerando devoluciones, conservando el historial de pagos.
- Se impide cancelar una venta con cobros aplicados o recibos pendientes. Debe resolverse previamente la cobranza o utilizar el circuito de devoluciones.
- La confirmacion de transferencias directas respeta la restriccion de moneda base del circuito de recibos.

## Cinco casos aislados

1. Recibo mixto, distribucion Caja/Banco, bloqueo de cancelacion y reverso idempotente.
2. Transferencia pendiente sin movimientos antes de confirmacion y reintento sin duplicaciones.
3. Fallo contable: rollback de pagos y movimientos.
4. Cobranza parcial de varias facturas y rechazo de exceso, moneda diferente, documento inexistente y anticipo sin aplicacion.
5. Transferencia directa sobre venta parcialmente devuelta: saldo, estado pagado, caja y asiento una sola vez. Incluye contabilizacion idempotente de venta con pago inmediato en efectivo.

Resultado: 5 pruebas, 37 aserciones correctas. No se ejecuto la suite completa por el limite solicitado.

## Limites y puntos no certificados

- No existe un circuito de anticipos sin factura, saldo a favor ni aplicacion posterior. Actualmente se rechazan; implementarlo requiere definir su tratamiento comercial y contable.
- Las cobranzas estan limitadas a moneda base, sin conversion automatica.
- Un recibo mixto que contiene una transferencia queda pendiente completo hasta su confirmacion; no aplica por separado la parte de efectivo.
- Los medios no efectivos usan Banco. No se implementaron cuentas puente independientes para tarjetas, cheques o billeteras ni conciliacion bancaria externa.
- No se certificaron concurrencia real en MySQL, integraciones bancarias ni todas las variantes de ventas/devoluciones con estas cinco pruebas.
- No se ejecutaron regularizaciones historicas de asientos ni se modificaron operaciones reales.

Por estas limitaciones no corresponde declarar una integracion del 100% de todo el sistema.
