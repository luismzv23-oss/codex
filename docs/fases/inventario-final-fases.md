# Inventario Final de Fases

## Estado general

Las fases del roadmap principal de `Codex` quedaron desarrolladas a nivel de:

- base de datos
- controladores web
- API REST
- vistas
- documentación funcional/técnica

La brecha remanente ya no está en "fases grandes sin desarrollar", sino en:

- QA manual integral end-to-end
- endurecimiento de errores de operación real
- homologación/producción viva de ARCA con credenciales reales

## Fase 1 - Compras y Proveedores

- Estado: `Desarrollada`
- Nivel de cierre: `Técnico alto / QA funcional parcial`
- Componentes:
  - proveedores
  - órdenes de compra
  - recepciones
  - devoluciones
  - cuentas a pagar
  - pagos a proveedor
- Documentación:
  - `docs/fases/fase-01-compras-proveedores.md`
  - `docs/fases/fase-02-cuentas-a-pagar.md`
  - `docs/fases/fase-03-api-compras.md`

## Fase 2 - Cuenta Corriente, Cobranzas y Pagos

- Estado: `Desarrollada`
- Nivel de cierre: `Técnico alto / QA funcional parcial`
- Componentes:
  - cobranzas
  - recibos
  - saldos por cobrar
  - pagos a proveedor
  - integración con caja
- Documentación:
  - `docs/fases/fase-05-cuenta-corriente-cobranzas.md`

## Fase 3 - Caja y Tesorería

- Estado: `Desarrollada`
- Nivel de cierre: `Técnico alto / QA funcional parcial`
- Componentes:
  - cajas
  - sesiones
  - apertura/cierre
  - movimientos
  - conciliaciones
  - cheques
- Documentación:
  - `docs/fases/fase-04-caja-tesoreria.md`
  - `docs/fases/fase-15-tesoreria-avanzada-paridad.md`

## Fase 4 - Inventario Avanzado

- Estado: `Desarrollada`
- Nivel de cierre: `Técnico alto / QA funcional parcial`
- Componentes:
  - depósitos
  - ubicaciones internas
  - lotes
  - series
  - vencimientos
  - kits
  - trazabilidad
  - costeo
  - kardex valorizado
  - ensambles
  - cierres
  - revalorizaciones
- Documentación:
  - `docs/fases/fase-06-inventario-avanzado.md`
  - `docs/fases/fase-06b-costeo-real-kardex-valorizado.md`
  - `docs/fases/fase-18-inventario-operativo-paridad.md`

## Fase 5 - Fiscal Argentina y ARCA

- Estado: `Desarrollada estructuralmente`
- Nivel de cierre: `Técnico alto / Operación real pendiente`
- Componentes:
  - configuración fiscal por empresa
  - comprobantes A/B/C/M
  - readiness ARCA
  - eventos fiscales
  - autorización y consulta
  - diagnóstico de certificado/clave/CUIT
- Pendiente real:
  - homologación viva
  - producción viva
  - CAE con credenciales reales
- Documentación:
  - `docs/fases/fase-07-fiscal-argentina-arca.md`
  - `docs/fases/fase-13-arca-operativo-qa-final.md`

## Fase 6 - Ventas Profesionalizadas

- Estado: `Desarrollada`
- Nivel de cierre: `Técnico alto / QA funcional parcial`
- Componentes:
  - workflow documental
  - presupuesto, pedido, remito, factura, ticket
  - listas de precio
  - promociones
  - clientes
  - vendedores
  - zonas
  - condiciones comerciales
  - comisiones
  - POS
  - kiosco
- Documentación:
  - `docs/fases/fase-08-ventas-profesionalizadas.md`
  - `docs/fases/fase-08b-comisiones-export-robustez.md`
  - `docs/fases/fase-16-ventas-avanzadas-paridad.md`

## Fase 7 - Reportes ERP y BI

- Estado: `Desarrollada`
- Nivel de cierre: `Técnico alto / QA funcional parcial`
- Componentes:
  - reportes de ventas
  - dashboard ejecutivo
  - readiness
  - QA dashboard
  - exportación y métricas base
- Documentación:
  - `docs/fases/fase-09-reportes-erp-bi.md`
  - `docs/fases/fase-11-consolidacion-erp-dashboard.md`

## Fase 8 - Auditoría, Seguridad y Robustez

- Estado: `Desarrollada`
- Nivel de cierre: `Técnico alto / QA funcional parcial`
- Componentes:
  - audit logs
  - document events
  - integration logs
  - readiness productiva
  - QA checklist
  - endurecimiento transaccional inicial
- Documentación:
  - `docs/fases/fase-10-auditoria-robustez.md`
  - `docs/fases/fase-12-qa-readiness-productiva.md`
  - `docs/fases/fase-20-qa-hardening-paridad.md`

## Fases de Paridad con Tango

- Estado: `Documentadas y desarrolladas en gran parte`
- Documentación:
  - `docs/roadmap-paridad-tango/README.md`
  - `docs/roadmap-paridad-tango/00-matriz-brechas-codex-vs-tango.md`
  - `docs/roadmap-paridad-tango/fase-A-fiscal-arca-productivo.md`
  - `docs/roadmap-paridad-tango/fase-B-contabilidad-iva.md`
  - `docs/roadmap-paridad-tango/fase-C-tesoreria-avanzada.md`
  - `docs/roadmap-paridad-tango/fase-D-ventas-avanzadas.md`
  - `docs/roadmap-paridad-tango/fase-E-compras-avanzadas.md`
  - `docs/roadmap-paridad-tango/fase-F-inventario-operativo.md`
  - `docs/roadmap-paridad-tango/fase-G-hardware-mostrador.md`
  - `docs/roadmap-paridad-tango/fase-H-qa-hardening.md`

## Pendiente real después del desarrollo por fases

No queda una fase grande sin desarrollar. Lo pendiente real es:

- QA funcional completa de todos los POST críticos
- estabilización por errores de uso real
- homologación/producción ARCA real
- validación operativa prolongada bajo uso diario

## Resultado actual

`Codex` quedó con todas las fases del roadmap desarrolladas. El trabajo que sigue ya no es expansión funcional mayor, sino estabilización operativa y validación final de campo.
