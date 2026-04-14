# Fase 02: Cuentas a Pagar y Pagos a Proveedor

## Objetivo
Extender el circuito de Compras para registrar deuda con proveedores y pagos operativos por empresa.

## Alcance funcional
- Generacion automatica de cuenta a pagar al recepcionar compras.
- Visualizacion de estados:
  - `pending`
  - `partial`
  - `paid`
  - `cancelled`
- Registro de pagos asociados a una cuenta a pagar.
- Actualizacion automatica de saldo.
- Integracion con devoluciones para reducir deuda del proveedor cuando corresponda.

## Reglas de negocio
- Una recepcion genera deuda si su total es mayor a cero.
- El pago no puede superar el saldo pendiente.
- Una devolucion asociada a una recepcion reduce el total adeudado de la cuenta vinculada.
- Si el saldo llega a cero, el estado pasa a `paid`.

## Entidades principales
- `purchase_payables`
- `purchase_payments`

## Pantallas
- Resumen de deuda en `Compras`
- Popup de pago a proveedor

## Integraciones
- `Compras`: la recepcion alimenta la deuda
- `Dashboard`: disponible para futuras metricas financieras

## Criterios de aceptacion
- La recepcion crea una cuenta a pagar.
- El pago reduce saldo y cambia estado.
- La devolucion ajusta la deuda remanente.
