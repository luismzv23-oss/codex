# Fase B - Contabilidad e IVA

## Objetivo
Incorporar la capa contable-fiscal faltante para acercar `Codex` a la explotacion real que resuelve `Tango`.

## Brecha con Tango
Hoy `Codex` tiene impuestos y trazabilidad comercial, pero no un libro IVA ni asientos contables automaticos.

## Alcance funcional
- Libro IVA ventas
- Libro IVA compras
- parametrizacion contable por comprobante
- generacion de asientos automáticos
- consulta de movimientos contables relacionados

## Requerimientos funcionales
- Definir cuentas contables por:
  - ventas
  - compras
  - caja
  - cobranzas
  - pagos
  - impuestos
- Generar asiento al confirmar:
  - factura de venta
  - factura/recepcion de compra
  - recibo
  - pago a proveedor
  - cierre de caja
- Emitir reportes de libro IVA compras y ventas.

## Requerimientos tecnicos
- nuevas tablas:
  - `accounting_accounts`
  - `accounting_entries`
  - `accounting_entry_items`
  - `vat_books`
- motores de mapeo por tipo de comprobante.
- integracion con `sales`, `purchase_*`, `cash_*`.

## Criterios de aceptacion
- Cada comprobante contable genera un asiento balanceado.
- Libro IVA ventas y compras refleja operaciones reales.
- Existe trazabilidad comprobante -> asiento.

## Prioridad
Critica
