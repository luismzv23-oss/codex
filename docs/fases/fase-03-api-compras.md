# Fase 03: API REST de Compras

## Objetivo
Exponer el modulo `Compras` mediante API REST interna para integraciones, frontend futuro y automatizaciones sin duplicar la logica documental.

## Alcance funcional
- Consulta de resumen de compras por empresa.
- Consulta y alta de proveedores.
- Consulta y alta de ordenes de compra.
- Confirmacion de ordenes.
- Recepcion de compras con impacto en Inventario.
- Devoluciones a proveedor con reversa de stock.
- Consulta de cuentas a pagar.
- Registro de pagos imputados.

## Endpoints previstos
- `GET /api/v1/purchases`
- `GET /api/v1/purchases/suppliers`
- `POST /api/v1/purchases/suppliers`
- `GET /api/v1/purchases/orders`
- `POST /api/v1/purchases/orders`
- `POST /api/v1/purchases/orders/{id}/confirm`
- `GET /api/v1/purchases/receipts`
- `POST /api/v1/purchases/receipts`
- `POST /api/v1/purchases/returns`
- `GET /api/v1/purchases/payables`
- `POST /api/v1/purchases/payments`

## Reglas tecnicas
- La API respeta permisos del sistema `Compras`.
- La empresa debe tener el sistema asignado.
- El usuario debe tener asignacion activa al sistema.
- Las recepciones y devoluciones reutilizan la misma logica de stock del modulo web.

## Criterios de aceptacion
- La API devuelve resumen, catalogos y documentos reales.
- Crear una recepcion via API incrementa stock.
- Registrar una devolucion via API disminuye stock.
- Registrar un pago via API actualiza el saldo pendiente.
