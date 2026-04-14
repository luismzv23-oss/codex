# Fase 05 - Cuenta Corriente, Cobranzas y Pagos

## Objetivo
Completar el circuito financiero operativo de clientes y proveedores para que Ventas, Compras y Caja trabajen sobre saldos reales.

## Alcance funcional
- Visualizar cuentas por cobrar de clientes.
- Registrar recibos de cobranza.
- Imputar una cobranza a una o varias ventas pendientes.
- Actualizar saldo pendiente, parcial o cancelado por comprobante.
- Generar pagos de venta aplicados sobre `sale_payments`.
- Impactar la cobranza en caja cuando exista sesion abierta.
- Exponer la misma operativa por API REST.

## Reglas de negocio
- Solo pueden cobrarse comprobantes con saldo pendiente.
- El importe aplicado por linea no puede superar el saldo del comprobante.
- Un recibo puede cobrar una o varias ventas del mismo cliente.
- El estado del comprobante debe pasar a:
  - `pending`
  - `partial`
  - `paid`
- Si existe caja activa, el recibo genera ingreso de tesoreria.
- La cuenta corriente se mantiene sincronizada con `sales_receivables`.

## Entidades tecnicas
- `sales_receipts`
- `sales_receipt_items`

## Integraciones
- `Ventas`: actualiza `sale_payments`, `paid_total` y `payment_status`.
- `Caja`: registra movimientos `customer_receipt`.
- `Dashboard / Reportes`: reutiliza saldos reales ya sincronizados.
- `API`: alta de recibos e index de cuentas por cobrar.

## Web
- Pantalla `Ventas > Cobranzas`
- Popup `Nuevo recibo`

## API
- `GET /api/v1/sales/receivables`
- `GET /api/v1/sales/receipts`
- `POST /api/v1/sales/receipts`

## Criterios de aceptacion
- Un recibo disminuye el saldo de una o varias ventas.
- La venta pasa a `Parcial` o `Pagado` segun corresponda.
- Se genera `sale_payment` por cada aplicacion.
- Si hay caja activa, la cobranza impacta tesoreria.
- Web y API muestran saldos coherentes con `sales_receivables`.
