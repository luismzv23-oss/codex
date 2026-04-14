# Fase 06B: Costeo real y Kardex valorizado

## Objetivo
Completar la brecha operativa de `Inventario avanzado` incorporando costeo real sobre salidas y reflejo valorizado del stock en `Kardex`, manteniendo coherencia con la configuracion de valuacion definida por empresa.

## Alcance funcional
- Consumo real de capas de costo al registrar:
  - egresos
  - transferencias desde deposito origen
  - ajustes negativos
- Soporte de valuacion:
  - FIFO
  - LIFO
  - Promedio ponderado
- Actualizacion automatica de:
  - `unit_cost`
  - `total_cost`
  en movimientos de salida
- Visualizacion de:
  - valor de stock
  - costo promedio
  en el resumen de `Kardex`
- Exposicion del mismo resultado en API REST

## Alcance tecnico
- Controladores afectados:
  - `app/Controllers/InventoryController.php`
  - `app/Controllers/Api/V1/InventoryController.php`
- Vistas afectadas:
  - `app/Views/inventory/kardex.php`
  - `app/Views/inventory/pdf/kardex.php`
- Reutilizacion de tabla existente:
  - `inventory_cost_layers`

## Reglas implementadas
1. Las entradas crean capas de costo abiertas.
2. Las salidas consumen capas segun la valuacion configurada.
3. Las transferencias consumen costo en origen y recrean costo en destino.
4. Los ajustes negativos consumen costo como una salida operativa.
5. Cuando no hay capas suficientes, el sistema toma costo de respaldo desde:
   - promedio actual de capas abiertas
   - costo base del producto
6. El `Kardex` muestra stock valorizado y costo promedio por producto.

## Criterios de aceptacion cubiertos
- El movimiento de salida queda valorizado.
- El stock remanente refleja valor acumulado.
- La empresa puede consultar `Kardex` con costo y valor de stock.
- Web y API responden con la misma logica de costeo.

## Validacion esperada
- Registrar un ingreso con costo.
- Registrar un egreso del mismo producto.
- Confirmar que el egreso actualiza `unit_cost` y `total_cost`.
- Confirmar que `Kardex` muestra:
  - stock actual
  - stock disponible
  - valor stock
  - costo promedio

## Brecha que sigue despues de esta subfase
- Costeo contable avanzado cruzado con compras/ventas.
- Revaluaciones masivas.
- Cierre de inventario por periodo.
