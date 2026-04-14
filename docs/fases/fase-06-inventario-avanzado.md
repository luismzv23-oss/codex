# Fase 06 - Inventario avanzado

## Objetivo
Llevar el sistema de Inventario de Codex a un nivel ERP más cercano a Tango, incorporando control por ubicaciones internas, lotes, series, kits y capas de costo sin romper la operación actual.

## Alcance funcional
- Ubicaciones internas por depósito.
- Trazabilidad por lote y serie.
- Control opcional de vencimiento por producto.
- Productos kit con composición base.
- Capas de costo para movimientos de ingreso.
- Exposición web y API de la información avanzada.

## Requerimientos funcionales
- Cada depósito puede tener múltiples ubicaciones internas activas.
- Un producto puede definir si controla:
  - lotes
  - series
  - vencimiento
- Los movimientos pueden registrar origen y destino por ubicación.
- Los movimientos con lote/serie deben reflejarse en trazabilidad.
- Los productos kit deben poder definir sus componentes y cantidades.
- La trazabilidad del producto debe mostrar:
  - stock por depósito
  - stock por ubicación
  - lotes
  - series
  - movimientos
  - capas de costo

## Requerimientos técnicos
- Migración para:
  - `inventory_locations`
  - `inventory_lots`
  - `inventory_serials`
  - `inventory_kit_items`
  - `inventory_cost_layers`
- Extensión de:
  - `inventory_products`
  - `inventory_movements`
  - `inventory_stock_levels`
- Nuevos modelos para entidades avanzadas.
- Integración en:
  - `InventoryController`
  - `Api/V1/InventoryController`
  - vistas de configuración, producto, movimiento y trazabilidad

## Criterios de aceptación
- Se pueden crear ubicaciones internas por depósito.
- Un movimiento puede registrar ubicación origen/destino.
- Los lotes y series quedan visibles en trazabilidad.
- Los kits guardan composición base.
- Las capas de costo se generan en ingresos y ajustes positivos.
- La API devuelve también la información avanzada del producto.

## Comparación con Tango
Con esta fase, Codex cubre mejor la base operativa que Tango suele manejar en stock avanzado:
- ubicación física
- lote/serie
- vencimiento
- composición de kits
- base de costeo

Todavía quedarán pendientes para igualar o superar completamente a Tango:
- consumo real FIFO/LIFO por capa
- ubicaciones jerárquicas más profundas
- procesos de ensamble/desensamble
- integración automática con compras y ventas sobre lotes/series
