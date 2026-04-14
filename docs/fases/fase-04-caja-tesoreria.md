# Fase 04 - Caja y Tesoreria

## Objetivo
Cerrar la operacion diaria de ventas, kiosco y pagos vinculando cada movimiento monetario a una caja activa por empresa.

## Alcance funcional
- Crear cajas por empresa y sucursal.
- Apertura de caja con monto inicial.
- Cierre de caja con saldo esperado, saldo real y diferencia.
- Registrar movimientos manuales:
  - ingreso
  - egreso
  - retiro
  - ajuste
- Asociar ventas POS y Kiosco a una sesion abierta.
- Asociar pagos a proveedores a una sesion abierta.
- Ver resumen diario:
  - cajas abiertas
  - ingresos
  - egresos
  - saldo esperado
  - diferencia de cierre

## Reglas de negocio
- Una venta POS o Kiosco no puede confirmarse si no existe una caja activa compatible.
- Las cajas pueden ser de tipo:
  - general
  - pos
  - kiosco
- Si no existe caja del canal exacto, puede usarse una caja `general`.
- Cada caja solo puede tener una sesion abierta a la vez.
- El cierre calcula automaticamente el saldo esperado a partir del monto de apertura y los movimientos registrados.
- Los pagos a proveedores con caja activa impactan como egreso en tesoreria.

## Integraciones
- `Ventas POS`: genera ingresos de caja.
- `Ventas Kiosco`: genera ingresos de caja.
- `Compras / Pagos`: genera egresos de caja.
- `Sistemas`: `Caja` aparece como sistema propio dentro del ecosistema.

## Entidades tecnicas
- `cash_registers`
- `cash_sessions`
- `cash_movements`
- `cash_closures`

## Web
- Dashboard de caja.
- Popup de apertura.
- Popup de cierre.
- Popup de movimiento manual.

## API
- `GET /api/v1/cash`
- `GET /api/v1/cash/registers`
- `GET /api/v1/cash/sessions`
- `POST /api/v1/cash/sessions/open`
- `POST /api/v1/cash/sessions/{id}/close`
- `POST /api/v1/cash/movements`

## Criterios de aceptacion
- Una venta POS con pagos genera movimientos en caja.
- Una venta Kiosco con pagos genera movimientos en caja.
- Un pago a proveedor reduce el saldo esperado de la sesion.
- El cierre deja historial y diferencia de arqueo.
- `admin` puede operar la caja de su empresa.
- `superadmin` puede ver y operar cualquier caja.
