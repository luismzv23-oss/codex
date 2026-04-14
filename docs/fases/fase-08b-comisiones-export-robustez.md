# Fase 08B: Comisiones, exportacion y robustez de stock

## Objetivo
Cerrar brechas reales de `Ventas profesionalizadas`, `Reportes ERP y BI` y `Auditoria/robustez` incorporando comisiones comerciales, exportacion operativa y validaciones mas firmes contra sobreventa.

## Alcance funcional
- Generacion automatica de comisiones por venta confirmada.
- Ajuste automatico de comision cuando la venta:
  - se cancela
  - queda devuelta total
  - cambia de estado operativo
- Reportes de ventas exportables en CSV.
- Visualizacion de resumen de comisiones dentro de reportes.
- Revalidacion de stock con bloqueo transaccional antes de reservar o entregar.

## Alcance tecnico
- Nueva tabla:
  - `sales_commissions`
- Nuevos artefactos:
  - `app/Models/SalesCommissionModel.php`
- Controladores extendidos:
  - `SalesController`
  - `Api/V1/SalesController`
- Rutas nuevas:
  - exportacion CSV web
  - exportacion/consulta API de comisiones

## Reglas implementadas
1. Si una venta confirmada tiene vendedor asignado, se genera o sincroniza una comision.
2. La comision toma como base el total de la venta y la tasa del vendedor.
3. Si la venta se cancela o queda devuelta total, la comision pasa a `cancelled`.
4. Los reportes comerciales incluyen comision total y top de comisiones.
5. La confirmacion de venta revalida stock dentro de transaccion, bloqueando filas de stock por producto/deposito antes de reservar o descargar.

## Criterios de aceptacion cubiertos
- La venta profesional ya genera comision operativa.
- Los reportes pueden exportarse.
- La confirmacion es mas robusta contra inconsistencias por stock concurrente.

## Comparacion con Tango
- Acerca a `Codex` a Tango en gestion de vendedores/comisiones.
- Mejora el uso gerencial con exportacion directa.
- Refuerza una de las diferencias criticas frente a un sistema basico: consistencia transaccional en stock.
