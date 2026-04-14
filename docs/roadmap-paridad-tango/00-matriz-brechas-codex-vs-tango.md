# Matriz de brechas Codex vs Tango

| Area | Codex actual | Tango tipico | Brecha | Prioridad |
|---|---|---|---|---|
| Fiscal ARCA | Estructura, readiness, eventos, simulacion y endpoints | Emision viva, CAE real, consulta real, homologacion y produccion | Alta | Critica |
| IVA y contabilidad | Impuestos basicos y trazabilidad comercial | Libro IVA, asientos automaticos, impacto contable | Muy alta | Critica |
| Tesoreria | Caja, apertura, cierre, movimientos, cobranzas y pagos base | Cheques, cartera, conciliacion, medios profundos, circuitos financieros | Alta | Alta |
| Ventas | Workflow documental, POS, kiosco, listas, promociones base, cobranzas, comisiones | Promociones avanzadas, riesgo crediticio, descuentos complejos, dispositivos | Media/Alta | Alta |
| Compras | Proveedores, ordenes, recepciones, devoluciones, cuentas a pagar | Factura proveedor fuerte, pagos a cuenta, FX, costos por proveedor, deuda compleja | Media/Alta | Alta |
| Inventario | Depositos, ubicaciones, lotes, series, vencimientos, costeo por capas, kardex valorizado | Ensamble/desensamble operativo, cierres, revalorizacion, logistica madura | Media | Media |
| Hardware | No integrado | Impresora fiscal, POSNet/Lapos, lectores, cajon | Alta | Media |
| QA y robustez | Hardening parcial, auditoria, logs y some locking | Madurez operativa probada en uso intensivo | Alta | Critica |

## Lectura ejecutiva
- `Codex` ya tiene una base ERP avanzada.
- La mayor distancia con `Tango` no esta en pantallas sino en operacion fiscal, contable, tesoreria y hardening real.
- Las fases A, B y H son las que mas aumentan la paridad operativa.
