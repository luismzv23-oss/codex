# Fase 11 - Consolidacion ERP y Dashboard Ejecutivo

## Objetivo
Unificar en el dashboard principal el estado operativo del ERP para que `Codex` se perciba como un sistema integrado y no como modulos aislados.

## Alcance funcional
- Consolidar indicadores de:
  - ventas
  - compras
  - inventario
  - caja
  - cobranzas
  - cuentas por pagar
  - auditoria
  - integraciones
- Mostrar alertas ejecutivas:
  - stock critico
  - cobranzas pendientes
  - pagos pendientes
  - cajas abiertas
  - comprobantes fiscales pendientes/error
  - errores de integracion
- Exponer la misma informacion en web y API.

## Requerimientos funcionales
- El `superadmin` visualiza consolidado global.
- El `admin` visualiza consolidado de su empresa.
- El dashboard debe mostrar:
  - KPIs globales
  - salud operativa
  - rendimiento comercial
  - actividad de sucursales/empresas
  - alertas ejecutivas
  - auditoria reciente
  - integraciones recientes

## Requerimientos tecnicos
- Extender `DashboardController` web y API.
- Reutilizar tablas existentes:
  - `sales`
  - `purchase_orders`
  - `purchase_payables`
  - `sales_receivables`
  - `inventory_stock_levels`
  - `inventory_products`
  - `cash_sessions`
  - `audit_logs`
  - `integration_logs`
  - `sales_arca_events`
- No crear nuevas tablas para esta fase.

## Criterios de aceptacion
- El dashboard resume todo el ERP en una sola pantalla.
- El API dashboard devuelve los mismos ejes operativos.
- Las alertas reflejan datos reales de operacion.

## Comparacion contra Tango
- Esta fase acerca a `Codex` a la experiencia de supervision ejecutiva de un ERP maduro.
- Sigue faltando mas profundidad analitica y BI historico avanzado, pero el nivel de integracion visible ya queda mucho mas cercano a Tango.
