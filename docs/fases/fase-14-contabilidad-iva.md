# Fase 14 - Contabilidad e IVA

## Objetivo
Agregar una base contable e impositiva operativa sobre Compras, Ventas, Cobranzas, Pagos a proveedor y Caja.

## Alcance implementado
- Plan de cuentas base por empresa.
- Asientos contables automáticos.
- Libro IVA Ventas.
- Libro IVA Compras.
- Sincronización automática desde:
  - venta confirmada
  - devolución de venta
  - recepción de compra
  - devolución a proveedor
  - recibo de cobranza
  - pago a proveedor
  - cierre de caja con diferencia

## Componentes técnicos
- Migración:
  - `accounting_accounts`
  - `accounting_entries`
  - `accounting_entry_items`
  - `vat_sales_books`
  - `vat_purchase_books`
- Modelos:
  - `AccountingAccountModel`
  - `AccountingEntryModel`
  - `AccountingEntryItemModel`
  - `VatSalesBookModel`
  - `VatPurchaseBookModel`
- Servicio:
  - `AccountingService`

## Criterios de aceptación
- Cada documento operativo deja un asiento balanceado.
- IVA ventas y compras quedan registrados en tablas dedicadas.
- Cancelaciones o ausencia de documento fuente limpian el asiento asociado.
- El cierre de caja registra sobrante o faltante cuando exista diferencia.

## Validación prevista
- Migración completa.
- Validación de sintaxis PHP.
- Revisión de hooks de sincronización en controladores y caja.
