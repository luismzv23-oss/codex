# Medios de pago en caja, conciliaciones y proveedores

Esta segunda etapa comparte el catálogo de la empresa con Ventas. Los registros nuevos guardan GUID, código, nombre, configuración y datos obligatorios del medio. Los históricos conservan el tipo genérico; no se les asigna una tarjeta o billetera por inferencia.

## Configuración y uso

1. En Configuración, abrir Medios de Pagos y editar el medio.
2. Habilitar Cobros para ventas, cobranzas e ingresos manuales.
3. Habilitar Pagos a proveedores para pagos y egresos manuales (incluidos retiros y ajustes negativos). Los medios existentes mantienen Cobros habilitado y Proveedores deshabilitado hasta configurarlos explícitamente.
4. Revisar monedas, sucursales, puntos y datos obligatorios.
5. Registrar el movimiento desde Caja o el pago desde la cuenta a pagar de Compras.

Los selectores muestran código y nombre. Las opciones incompatibles quedan deshabilitadas y el servidor vuelve a validar la operación. La sucursal efectiva de caja se obtiene de la sesión seleccionada. Los popups no incluyen navegación superior y las acciones usan iconos con título y etiqueta accesible.

Los importes de esta etapa son fondos efectivamente ingresados o egresados: no se agregan los recargos de venta. Un ajuste manual permite indicar ingreso o egreso. La cotización expresa unidades de moneda base por unidad de la moneda del movimiento; para moneda base el servidor utiliza 1.

## Pendientes y confirmación

Las transferencias y los medios con Confirmación requerida quedan pendientes. En Caja aparecen con indicador Pendiente y un icono para abrir la confirmación; no forman parte del saldo esperado hasta confirmar con evidencia. Una sesión con movimientos manuales pendientes no se puede cerrar.

En Compras, Pagos a proveedores muestra todas las instrucciones pendientes y los últimos 50 pagos registrados (estos últimos se filtran por el período del tablero). El filtro de proveedor también se aplica a esta sección. El icono de confirmación abre un formulario que solicita evidencia.

Un pago pendiente a proveedor reserva el importe para impedir comprometer el mismo saldo en varios pagos. La deuda contable y su saldo no disminuyen hasta confirmar. Si el saldo cambió, se vuelve a validar al confirmar. El medio conserva las condiciones guardadas aunque posteriormente se renombre o desactive en el catálogo.

La confirmación registra el egreso, actualiza la deuda y genera el asiento dentro de la misma transacción. Un fallo revierte todo. Los reintentos de creación con la misma clave y contenido, y los de confirmación, no duplican efectos. El cierre y las nuevas operaciones financieras se serializan mediante el bloqueo de empresa.

Los cheques de cartera deben pertenecer a la empresa, estar disponibles y cubrir exactamente el importe. No pueden comprometerse en dos pagos. Al aplicarlos pasan a endosados, sin generar un segundo egreso independiente. Este circuito de cheques admite moneda base.

## Conciliaciones y reportes

La conciliación muestra saldos por GUID de medio y moneda, más los históricos separados por tipo. El efectivo inicial aparece como una línea independiente y se incluye una sola vez.

El saldo esperado se consulta con una petición sin caché; puede actualizarse con el icono correspondiente y se recalcula al guardar. No se utiliza el importe esperado enviado por el cliente. Se registra el real verificado y la diferencia, sin crear ajustes de caja automáticos.

Los resúmenes y cierres excluyen los movimientos pendientes. Los importes por medio conservan su moneda; los totales agregados de caja se expresan en moneda base usando la cotización guardada. Los históricos sin moneda explícita se presentan como saldo histórico en moneda base para conciliación; no se reescribe su origen.

Los movimientos nuevos generados desde POS, Kiosco y cobranzas conservan el medio específico. La contabilización del pago al proveedor utiliza el destino guardado del medio (Caja, Banco o la cuenta transitoria correspondiente), con cuentas activas de movimiento y una cuenta de proveedores diferente.

## API

Nuevas consultas y acciones, relativas al prefijo API existente:

- GET cash/payment-methods y GET purchases/payment-methods.
- GET purchases/payments: pendientes y últimos pagos registrados.
- POST cash/movements/{id}/confirm y POST purchases/payments/{id}/confirm con evidence.
- GET cash/reconciliations/balances?cash_session_id=... .
- POST cash/reconciliations con cash_session_id, reconciliation_key, currency_code y actual_amount.

La consulta de saldos devuelve la clave reconciliation_key que debe enviarse sin reconstruirla. Puede ser method:GUID, legacy:tipo u opening.

Ejemplo de ingreso manual:

~~~json
{
  "request_key": "UUID_UNICO_DE_LA_OPERACION",
  "cash_session_id": "UUID_SESION",
  "movement_type": "manual_income",
  "payment_method_id": "UUID_MEDIO",
  "currency_code": "ARS",
  "amount": "100.00",
  "reference": "Comprobante 123",
  "fields": {}
}
~~~

Para proveedores, POST purchases/payments usa purchase_payable_id y los mismos datos de medio, importe, moneda y clave. La moneda debe ser la de la deuda. point_of_sale_id, gateway_id, cash_check_id, exchange_rate, paid_at, external_reference y notes son opcionales según la configuración. La clave identifica un intento lógico: reutilizarla con contenido diferente devuelve error.

## Instalación y validación

Aplicar php spark migrate. ExtendTreasuryPaymentMethods agrega las habilitaciones y la trazabilidad; no modifica importes ni asigna medios específicos a registros históricos. No admite reversión automática que elimine trazabilidad financiera.

Las pruebas de TreasuryPaymentTest cubren saldos, restricciones, reservas, reintentos, confirmaciones, rollback contable, asientos por banco y renderizado de popups. Las pruebas JavaScript cubren filtros por operación y sucursal, datos obligatorios y avisos de pendientes. Las regresiones incluyen compras, cobranzas, caja y ventas.

La validación automatizada no sustituye la revisión visual en un navegador conectado. No se registran pagos reales ni se emiten comprobantes fiscales durante las pruebas.
