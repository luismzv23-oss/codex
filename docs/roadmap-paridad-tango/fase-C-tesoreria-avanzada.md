# Fase C - Tesoreria avanzada

## Objetivo
Expandir `Caja` a una tesoreria operativa mas cercana a `Tango`.

## Brecha con Tango
`Codex` cubre apertura, cierre y movimientos base. Falta profundidad financiera.

## Alcance funcional
- cheques recibidos y emitidos
- cartera de cheques
- conciliacion de caja
- conciliacion de medios electronicos
- pagos mixtos avanzados
- resumen financiero por medio

## Requerimientos funcionales
- Alta de cheques con:
  - numero
  - banco
  - emisor
  - vencimiento
  - estado
- Aplicacion de cheque en cobranzas y pagos.
- Diferenciar efectivo, tarjeta, transferencia, cheque, QR y mixto.
- Conciliar cierres de caja contra medios.

## Requerimientos tecnicos
- nuevas tablas:
  - `cash_checks`
  - `cash_reconciliations`
  - `cash_payment_gateways`
- ampliacion de `cash_movements`, `sales_receipts`, `purchase_payments`.

## Criterios de aceptacion
- Una cobranza puede registrar cheque.
- Un pago a proveedor puede usar cheque o mixto.
- Un cierre de caja informa diferencia por medio.
