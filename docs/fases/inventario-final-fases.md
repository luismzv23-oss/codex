# Inventario final de fases

## Estado general

Las fases del roadmap principal de `Codex` quedaron desarrolladas a nivel técnico en:

- base de datos;
- controladores web;
- API REST;
- vistas;
- documentación funcional y técnica.

La brecha remanente ya no está en fases grandes sin desarrollar, sino en validación operativa:

- QA manual integral end-to-end;
- endurecimiento ante errores de operación real;
- homologación y producción viva de ARCA con credenciales reales;
- reconciliación de IVA, contabilidad, caja, stock y cuentas corrientes;
- pruebas de concurrencia sobre operaciones críticas.

## Semáforo ejecutivo

| Área | Estado técnico | Estado operativo | Riesgo principal |
|---|---|---|---|
| Compras y proveedores | Desarrollado | QA funcional parcial | Conciliación compra -> stock -> CxP -> asiento |
| Cuentas corrientes y cobranzas | Desarrollado | QA funcional parcial | Imputación, reversas y caja |
| Caja y tesorería | Desarrollado | QA funcional parcial | Conciliación por medio y cierre diario |
| Inventario avanzado | Desarrollado | QA funcional parcial | Costeo, cierres, trazabilidad y concurrencia |
| Fiscal ARCA | Desarrollado estructuralmente | Operación real pendiente | CAE real, consulta real y contingencias |
| Ventas profesionalizadas | Desarrollado | QA funcional parcial | Stock, fiscal, cobranza y promociones |
| Reportes ERP y BI | Desarrollado | QA funcional parcial | Reconciliación contra libros y comprobantes |
| Auditoría y robustez | Desarrollado | Hardening pendiente | Pruebas end-to-end y concurrencia |
| Paridad Tango | Documentada y avanzada | Certificación pendiente | Madurez operativa real |

## Fase 1 - Compras y proveedores

- Estado: `Desarrollada`
- Nivel de cierre: `Técnico alto / QA funcional parcial`
- Componentes:
  - proveedores;
  - órdenes de compra;
  - recepciones;
  - devoluciones;
  - cuentas a pagar;
  - pagos a proveedor.
- Pendiente de certificación:
  - recepción parcial y total;
  - impacto de stock;
  - cuenta a pagar;
  - pago con medio identificable;
  - asiento contable;
  - Libro IVA compras.
- Documentación:
  - `docs/fases/fase-01-compras-proveedores.md`
  - `docs/fases/fase-02-cuentas-a-pagar.md`
  - `docs/fases/fase-03-api-compras.md`

## Fase 2 - Cuenta corriente, cobranzas y pagos

- Estado: `Desarrollada`
- Nivel de cierre: `Técnico alto / QA funcional parcial`
- Componentes:
  - cobranzas;
  - recibos;
  - saldos por cobrar;
  - pagos a proveedor;
  - integración con caja.
- Pendiente de certificación:
  - imputación parcial y total;
  - reversa/anulación;
  - pago mixto;
  - impacto en caja;
  - asiento de cobranza/pago;
  - saldo final de cliente/proveedor.
- Documentación:
  - `docs/fases/fase-05-cuenta-corriente-cobranzas.md`

## Fase 3 - Caja y tesorería

- Estado: `Desarrollada`
- Nivel de cierre: `Técnico alto / QA funcional parcial`
- Componentes:
  - cajas;
  - sesiones;
  - apertura/cierre;
  - movimientos;
  - conciliaciones;
  - cheques;
  - medios de pago.
- Pendiente de certificación:
  - apertura única por puesto/caja;
  - movimientos automáticos desde venta/cobranza/pago;
  - conciliación por medio;
  - cierre con diferencia justificada;
  - asiento de cierre o diferencia;
  - trazabilidad de referencia externa.
- Documentación:
  - `docs/fases/fase-04-caja-tesoreria.md`
  - `docs/fases/fase-15-tesoreria-avanzada-paridad.md`

## Fase 4 - Inventario avanzado

- Estado: `Desarrollada`
- Nivel de cierre: `Técnico alto / QA funcional parcial`
- Componentes:
  - depósitos;
  - ubicaciones internas;
  - lotes;
  - series;
  - vencimientos;
  - kits;
  - trazabilidad;
  - costeo;
  - kardex valorizado;
  - ensambles;
  - cierres;
  - revalorizaciones.
- Pendiente de certificación:
  - compra -> recepción -> stock;
  - venta/remito -> egreso de stock;
  - transferencia entre depósitos;
  - ensamble/desensamble;
  - kardex valorizado;
  - cierre de periodo;
  - concurrencia contra stock disponible.
- Documentación:
  - `docs/fases/fase-06-inventario-avanzado.md`
  - `docs/fases/fase-06b-costeo-real-kardex-valorizado.md`
  - `docs/fases/fase-18-inventario-operativo-paridad.md`

## Fase 5 - Fiscal Argentina y ARCA

- Estado: `Desarrollada estructuralmente`
- Nivel de cierre: `Técnico alto / Operación real pendiente`
- Componentes:
  - configuración fiscal por empresa;
  - comprobantes A/B/C/M;
  - readiness ARCA;
  - eventos fiscales;
  - autorización y consulta;
  - diagnóstico de certificado, clave y CUIT.
- Pendiente real:
  - homologación viva;
  - producción viva;
  - CAE con credenciales reales;
  - consulta real contra ARCA;
  - contingencias y reintentos;
  - bitácora fiscal auditable.
- Bloqueante:
  - una autorización en ambiente `desarrollo` o con CAE simulado no certifica operación productiva.
- Documentación:
  - `docs/fases/fase-07-fiscal-argentina-arca.md`
  - `docs/fases/fase-13-arca-operativo-qa-final.md`

## Fase 6 - Ventas profesionalizadas

- Estado: `Desarrollada`
- Nivel de cierre: `Técnico alto / QA funcional parcial`
- Componentes:
  - workflow documental;
  - presupuesto, pedido, remito, factura y ticket;
  - listas de precio;
  - promociones;
  - clientes;
  - vendedores;
  - zonas;
  - condiciones comerciales;
  - comisiones;
  - POS;
  - kiosco.
- Pendiente de certificación:
  - presupuesto -> pedido -> remito -> factura;
  - descuento/promoción con autorización si corresponde;
  - descuento de stock una sola vez;
  - CAE real para comprobante fiscalizable;
  - cuenta corriente;
  - cobranza y caja;
  - asiento y Libro IVA ventas.
- Documentación:
  - `docs/fases/fase-08-ventas-profesionalizadas.md`
  - `docs/fases/fase-08b-comisiones-export-robustez.md`
  - `docs/fases/fase-16-ventas-avanzadas-paridad.md`

## Fase 7 - Reportes ERP y BI

- Estado: `Desarrollada`
- Nivel de cierre: `Técnico alto / QA funcional parcial`
- Componentes:
  - reportes de ventas;
  - dashboard ejecutivo;
  - readiness;
  - QA dashboard;
  - exportación y métricas base.
- Pendiente de certificación:
  - conciliación de reportes contra comprobantes;
  - conciliación de ventas contra caja;
  - conciliación de IVA contra libros;
  - consistencia de métricas por periodo.
- Documentación:
  - `docs/fases/fase-09-reportes-erp-bi.md`
  - `docs/fases/fase-11-consolidacion-erp-dashboard.md`

## Fase 8 - Auditoría, seguridad y robustez

- Estado: `Desarrollada`
- Nivel de cierre: `Técnico alto / QA funcional parcial`
- Componentes:
  - audit logs;
  - document events;
  - integration logs;
  - readiness productiva;
  - QA checklist;
  - endurecimiento transaccional inicial.
- Pendiente de certificación:
  - trazabilidad por cada comprobante crítico;
  - errores recuperables;
  - pruebas de concurrencia;
  - regresión de POST críticos;
  - checklist firmado de salida operativa.
- Documentación:
  - `docs/fases/fase-10-auditoria-robustez.md`
  - `docs/fases/fase-12-qa-readiness-productiva.md`
  - `docs/fases/fase-20-qa-hardening-paridad.md`

## Fases de paridad con Tango

- Estado: `Documentadas y desarrolladas en gran parte`
- Enfoque actual: certificación operativa, no expansión funcional.
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

- QA funcional completa de todos los POST críticos;
- estabilización por errores de uso real;
- homologación/producción ARCA real;
- validación operativa prolongada bajo uso diario;
- bloqueo operativo de configuraciones incompletas;
- evidencias firmes de conciliación.

## Plan inmediato para paridad operativa

El orden de trabajo para quedar a la altura operativa de `Tango Gestión` no debe priorizar nuevas pantallas, sino certificación de circuitos completos:

1. `ARCA + IVA/Contabilidad`
   - CAE real en homologación y luego producción.
   - Consulta real del comprobante fiscal.
   - Libro IVA ventas y compras reconciliado.
   - Asientos automáticos balanceados para ventas, cobranzas, compras, pagos y cierre de caja.
   - Bloqueo de salida productiva si falta mapeo contable o si la autorización fiscal es simulada.

2. `Tesorería avanzada`
   - Caja, medios de pago, cheques, transferencias, tarjetas/QR y conciliación por referencia.
   - Movimiento financiero trazable desde cada cobro o pago.
   - Cierre de caja con diferencias justificadas y asiento asociado.

3. `QA integral y concurrencia`
   - Pruebas reproducibles de compra -> stock -> venta -> cobranza -> caja -> contabilidad -> IVA -> ARCA.
   - Pruebas de concurrencia sobre stock, numeración, caja y saldos.
   - Evidencia documentada por cada circuito.

4. `Hardware y refinamiento comercial`
   - Logs reales de impresora, ticketera, medios electrónicos y errores recuperables.
   - Promociones, descuentos y autorizaciones auditables.
   - Reporte diario conciliado contra ventas, caja y medios.

Documento operativo asociado:

- `docs/qa/certificacion-operativa-pyme.md`
- `docs/qa/checklist-integral-final.md`

## Bloqueantes de salida productiva

- ARCA sin CAE real en homologación.
- Consulta fiscal que no valide contra ARCA.
- Asientos omitidos por falta de mapeo contable.
- Libro IVA no conciliado contra comprobantes.
- Caja sin conciliación por medio.
- Stock sin kardex valorizado consistente.
- Numeración duplicada o no protegida ante concurrencia.
- Cuentas corrientes que requieren ajuste manual para cerrar.
- Tests end-to-end inexistentes para circuitos críticos.

## Próxima acción recomendada

Ejecutar la corrida `001 - ARCA + IVA/Contabilidad` definida en `docs/qa/certificacion-operativa-pyme.md`.

Resultado esperado de esa primera corrida:

- venta gravada con CAE real;
- consulta real del comprobante;
- cobranza aplicada;
- asiento de venta balanceado;
- asiento de cobranza balanceado;
- compra con IVA;
- asiento de compra balanceado;
- Libro IVA ventas/compras reconciliado;
- cero diferencias manuales.

## Resultado actual

`Codex` quedó con todas las fases del roadmap desarrolladas a nivel técnico. El trabajo que sigue ya no es expansión funcional mayor, sino estabilización operativa, certificación de circuitos completos y validación final de campo.
