# Fase 01: Compras y Proveedores

## Objetivo
Incorporar el circuito de abastecimiento para registrar proveedores, emitir ordenes de compra, recepcionar mercaderia y devolver productos al proveedor con impacto directo en Inventario.

## Alcance funcional
- Alta, edicion y administracion de proveedores por empresa.
- Ordenes de compra en estado `draft`, `approved`, `received_partial`, `received_total` y `cancelled`.
- Recepcion de compras total o parcial contra una orden.
- Generacion automatica de ingresos de stock al recepcionar.
- Devoluciones a proveedor contra una recepcion.
- Trazabilidad documental basica:
  - proveedor
  - orden de compra
  - recepcion
  - devolucion
  - movimiento de inventario

## Reglas de negocio
- Solo usuarios con acceso `manage` al sistema `Compras` pueden crear, aprobar, recepcionar, devolver y pagar.
- Una recepcion solo puede registrar cantidades pendientes de la orden.
- La devolucion solo puede salir de cantidades ya recepcionadas.
- Toda recepcion debe impactar `inventory_stock_levels` e `inventory_movements`.
- Toda devolucion debe revertir stock y dejar trazabilidad en inventario.

## Entidades principales
- `suppliers`
- `purchase_orders`
- `purchase_order_items`
- `purchase_receipts`
- `purchase_receipt_items`
- `purchase_returns`
- `purchase_return_items`

## Pantallas
- `Compras`:
  - resumen general
  - listado de proveedores
  - listado de ordenes
  - listado de recepciones
  - listado de cuentas a pagar
- Popups:
  - nuevo proveedor
  - nueva orden de compra
  - nueva recepcion
  - nueva devolucion

## Integraciones
- `Inventario`:
  - ingreso por recepcion
  - egreso por devolucion a proveedor
- `Configuracion`:
  - usa sucursales, monedas y numeracion por empresa

## Criterios de aceptacion
- Una orden aprobada puede recepcionarse.
- Una recepcion incrementa stock.
- Una devolucion disminuye stock.
- El historial documental queda visible en el modulo.
