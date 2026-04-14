# Fase E - Compras avanzadas nivel Tango

## Objetivo
Completar la profundidad financiera y documental de `Compras`.

## Brecha con Tango
Codex cubre bien orden, recepcion, devolucion y deuda base. Falta la capa fuerte de factura proveedor y deuda compleja.

## Alcance funcional
- factura de compra / proveedor
- notas de credito proveedor
- pagos a cuenta
- moneda extranjera
- diferencia de cambio
- historial de costos por proveedor

## Requerimientos funcionales
- Registrar factura proveedor asociada o no a recepcion.
- Imputar notas de credito a documentos origen.
- Mantener vencimientos multiples.
- Manejar pagos parciales y pagos anticipados.
- Consultar costo historico por proveedor y producto.

## Requerimientos tecnicos
- nuevas tablas:
  - `purchase_invoices`
  - `purchase_invoice_items`
  - `purchase_credit_notes`
  - `supplier_cost_history`
  - `supplier_exchange_differences`

## Criterios de aceptacion
- La factura proveedor puede generar deuda sin romper stock.
- Una nota de credito ajusta deuda y pendientes.
- El sistema informa costo historico por proveedor.
