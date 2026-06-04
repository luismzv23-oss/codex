# Checklist QA integral final

## Proposito

Validar que `Codex` puede operar un circuito pyme completo sin inconsistencias entre documentos, stock, caja, cuenta corriente, contabilidad, IVA, fiscalidad y auditoria.

Esta checklist complementa `docs/qa/certificacion-operativa-pyme.md`. No reemplaza la corrida controlada: sirve para marcar controles, evidencias y bloqueantes.

## Regla general

Un control solo se considera aprobado si:

- el resultado coincide con el esperado;
- existe evidencia verificable;
- no requiere ajuste manual posterior;
- conserva trazabilidad hacia comprobantes, movimientos, asientos, libros o logs;
- no deja saldos, stock, numeracion o estados intermedios inconsistentes.

Estados permitidos:

- `pendiente`
- `aprobado`
- `aprobado con observacion`
- `rechazado`

## Datos de corrida

| Campo | Valor |
|---|---|
| Corrida |  |
| Fecha/hora |  |
| Ambiente |  |
| Empresa |  |
| Sucursal |  |
| Usuario operador |  |
| Usuario revisor |  |
| Version/commit |  |
| Base/dataset usado |  |
| Resultado final |  |
| Observaciones |  |

## Criterios de rechazo global

- ARCA opera en `desarrollo` para una prueba de certificacion productiva.
- CAE simulado o inexistente para comprobante fiscalizable.
- Consulta fiscal que no valida contra ARCA.
- Asiento omitido por falta de mapeo contable.
- Libro IVA no reconcilia contra comprobantes.
- Stock no coincide con kardex valorizado.
- Movimiento de caja sin comprobante, referencia o motivo.
- Cuenta corriente requiere ajuste manual para cerrar.
- Numeracion duplicada.
- Error critico sin log, evento o camino de recuperacion.

## Orden recomendado

1. Fiscal ARCA.
2. Contabilidad e IVA.
3. Compras.
4. Inventario.
5. Ventas.
6. Cobranzas.
7. Caja.
8. Tesoreria.
9. Concurrencia.
10. Hardware y mostrador.
11. Cierre diario/mensual.

## Fiscal ARCA

Objetivo: validar emision fiscal real, consulta y trazabilidad.

Precondiciones:

- empresa con CUIT valido;
- punto de venta configurado;
- certificado y clave disponibles;
- ambiente `homologacion` para la primera certificacion;
- tipos de comprobante fiscalizables activos.

| Control | Estado | Evidencia | Observaciones |
|---|---|---|---|
| Completar settings ARCA por empresa | pendiente |  |  |
| Validar certificado, clave, CUIT, ambiente y punto de venta | pendiente |  |  |
| Ejecutar WSAA en homologacion | pendiente |  |  |
| Autorizar comprobante fiscalizable con CAE real | pendiente |  |  |
| Consultar comprobante contra ARCA | pendiente |  |  |
| Registrar error fiscal controlado con credencial o dato invalido | pendiente |  |  |
| Revisar `sales_arca_events` | pendiente |  |  |
| Revisar `integration_logs` | pendiente |  |  |
| Verificar PDF/ticket con CAE y vencimiento | pendiente |  |  |
| Confirmar que el CAE no es simulado | pendiente |  |  |

Bloqueantes:

- CAE simulado;
- consulta ARCA basada solo en datos locales;
- error fiscal sin evento o log;
- PDF fiscal sin CAE o vencimiento cuando corresponde.

## Contabilidad e IVA

Objetivo: validar que los comprobantes generan asientos balanceados y libros IVA reconciliables.

Precondiciones:

- plan de cuentas cargado;
- mapeo contable completo por empresa;
- impuestos y alicuotas configuradas;
- comprobantes de venta y compra disponibles.

| Control | Estado | Evidencia | Observaciones |
|---|---|---|---|
| Validar mapeo de cuentas contables por empresa | pendiente |  |  |
| Confirmar venta y verificar asiento balanceado | pendiente |  |  |
| Registrar cobranza y verificar asiento balanceado | pendiente |  |  |
| Registrar compra/recepcion/factura y verificar asiento balanceado | pendiente |  |  |
| Registrar pago a proveedor y verificar asiento balanceado | pendiente |  |  |
| Cerrar caja con diferencia y verificar asiento | pendiente |  |  |
| Generar Libro IVA ventas | pendiente |  |  |
| Generar Libro IVA compras | pendiente |  |  |
| Reconciliar neto, IVA y total contra comprobantes | pendiente |  |  |
| Verificar trazabilidad comprobante -> asiento | pendiente |  |  |
| Verificar ausencia de `skipped: no_account_mapping` | pendiente |  |  |

Bloqueantes:

- asiento desbalanceado;
- asiento omitido por mapping incompleto;
- Libro IVA con totales distintos al comprobante;
- comprobante contable sin referencia al origen.

## Compras

Objetivo: validar el circuito proveedor -> compra -> recepcion -> stock -> cuenta a pagar -> pago -> contabilidad/IVA.

| Control | Estado | Evidencia | Observaciones |
|---|---|---|---|
| Crear proveedor con condicion fiscal y plazo de pago | pendiente |  |  |
| Crear orden de compra con productos gravados | pendiente |  |  |
| Confirmar orden | pendiente |  |  |
| Recepcionar parcial y validar pendiente | pendiente |  |  |
| Recepcionar total y validar impacto en stock | pendiente |  |  |
| Verificar costo, lote/serie si aplica y kardex | pendiente |  |  |
| Registrar factura de proveedor si corresponde | pendiente |  |  |
| Verificar cuenta a pagar, vencimiento y saldo | pendiente |  |  |
| Registrar pago con medio identificable | pendiente |  |  |
| Verificar movimiento de caja/tesoreria cuando aplique | pendiente |  |  |
| Verificar asiento contable y Libro IVA compras | pendiente |  |  |
| Probar devolucion a proveedor y ajuste de cuenta a pagar | pendiente |  |  |

Bloqueantes:

- recepcion sin impacto de stock cuando corresponde;
- cuenta a pagar distinta al total del comprobante;
- pago sin baja de saldo;
- devolucion sin ajuste financiero o de stock.

## Inventario

Objetivo: validar existencia, trazabilidad y valorizacion.

| Control | Estado | Evidencia | Observaciones |
|---|---|---|---|
| Crear deposito y ubicacion | pendiente |  |  |
| Crear producto con stock minimo, costo e impuestos | pendiente |  |  |
| Registrar ingreso con costo | pendiente |  |  |
| Registrar egreso y validar stock disponible | pendiente |  |  |
| Registrar transferencia entre depositos | pendiente |  |  |
| Registrar ajuste positivo y negativo | pendiente |  |  |
| Verificar lotes, series y vencimientos si aplica | pendiente |  |  |
| Verificar trazabilidad por producto | pendiente |  |  |
| Verificar kardex valorizado | pendiente |  |  |
| Verificar costo promedio/FIFO segun configuracion | pendiente |  |  |
| Verificar ensamble/desensamble | pendiente |  |  |
| Verificar cierre de periodo y bloqueo operativo esperado | pendiente |  |  |

Bloqueantes:

- stock negativo no permitido por configuracion;
- kardex no coincide con stock;
- costo valorizado inconsistente;
- movimiento posterior a periodo cerrado cuando deberia bloquearse.

## Ventas

Objetivo: validar presupuesto -> pedido -> remito -> factura/ticket -> fiscal -> stock -> cuenta corriente -> contabilidad.

| Control | Estado | Evidencia | Observaciones |
|---|---|---|---|
| Crear cliente con condicion fiscal | pendiente |  |  |
| Crear presupuesto | pendiente |  |  |
| Convertir a pedido | pendiente |  |  |
| Generar remito | pendiente |  |  |
| Convertir a factura o ticket | pendiente |  |  |
| Confirmar venta | pendiente |  |  |
| Verificar stock descontado una sola vez | pendiente |  |  |
| Autorizar fiscalmente si corresponde | pendiente |  |  |
| Verificar receivable, comision y evento documental | pendiente |  |  |
| Cancelar una venta permitida y validar reversas | pendiente |  |  |
| Registrar devolucion parcial y total | pendiente |  |  |
| Verificar nota de credito, stock, cuenta corriente y asiento | pendiente |  |  |

Bloqueantes:

- descuento de stock duplicado;
- factura fiscalizable sin CAE;
- devolucion sin reversa de stock/cuenta corriente;
- venta confirmada sin evento o auditoria.

## Cobranzas

Objetivo: validar recibos, imputaciones, saldos, caja y contabilidad.

| Control | Estado | Evidencia | Observaciones |
|---|---|---|---|
| Abrir caja antes de cobrar cuando el canal lo requiere | pendiente |  |  |
| Registrar recibo total | pendiente |  |  |
| Registrar recibo parcial | pendiente |  |  |
| Aplicar a comprobante pendiente | pendiente |  |  |
| Probar pago mixto: efectivo + transferencia/tarjeta/QR/cheque | pendiente |  |  |
| Verificar baja de saldo | pendiente |  |  |
| Verificar movimiento de caja con referencia | pendiente |  |  |
| Verificar asiento de cobranza | pendiente |  |  |
| Anular recibo y validar reversa de saldos y caja | pendiente |  |  |

Bloqueantes:

- recibo sin imputacion correcta;
- doble imputacion al mismo saldo;
- cobranza sin caja cuando el canal la requiere;
- anulacion sin reversa financiera.

## Caja

Objetivo: validar apertura, movimientos, conciliacion y cierre.

| Control | Estado | Evidencia | Observaciones |
|---|---|---|---|
| Abrir caja por usuario y puesto | pendiente |  |  |
| Impedir apertura duplicada cuando corresponda | pendiente |  |  |
| Vender en POS/Kiosco | pendiente |  |  |
| Registrar ingreso manual | pendiente |  |  |
| Registrar egreso manual | pendiente |  |  |
| Registrar cobranza de venta | pendiente |  |  |
| Registrar pago a proveedor si usa caja | pendiente |  |  |
| Conciliar por medio de pago | pendiente |  |  |
| Cerrar caja | pendiente |  |  |
| Validar arqueo esperado vs real | pendiente |  |  |
| Verificar asiento de diferencia de caja | pendiente |  |  |
| Verificar que una caja cerrada no permita nuevos movimientos | pendiente |  |  |

Bloqueantes:

- caja duplicada abierta para el mismo puesto;
- movimientos posteriores al cierre;
- diferencia no justificada;
- cierre sin conciliacion por medio.

## Tesoreria

Objetivo: validar medios de pago, cheques, referencias externas y conciliacion.

| Control | Estado | Evidencia | Observaciones |
|---|---|---|---|
| Registrar medio efectivo | pendiente |  |  |
| Registrar transferencia con referencia | pendiente |  |  |
| Registrar tarjeta/QR con gateway y referencia externa | pendiente |  |  |
| Registrar cheque recibido | pendiente |  |  |
| Registrar cheque emitido o aplicado a pago | pendiente |  |  |
| Verificar cartera de cheques por estado | pendiente |  |  |
| Conciliar movimientos por medio | pendiente |  |  |
| Validar diferencia de cambio en pago moneda extranjera | pendiente |  |  |
| Verificar que cada movimiento tenga comprobante o motivo | pendiente |  |  |

Bloqueantes:

- medio electronico sin referencia;
- cheque sin estado operativo;
- conciliacion sin trazabilidad;
- diferencia de cambio no registrada.

## Concurrencia

Objetivo: validar que operaciones simultaneas no generen duplicados ni inconsistencias.

| Control | Estado | Evidencia | Observaciones |
|---|---|---|---|
| Confirmar dos ventas simultaneas contra el mismo stock | pendiente |  |  |
| Imputar dos cobranzas simultaneas al mismo saldo | pendiente |  |  |
| Abrir dos sesiones sobre la misma caja | pendiente |  |  |
| Emitir dos comprobantes con la misma secuencia | pendiente |  |  |
| Simular timeout de ARCA durante autorizacion | pendiente |  |  |
| Simular falla de impresion despues de venta confirmada | pendiente |  |  |
| Verificar que no haya duplicados ni estados intermedios irrecuperables | pendiente |  |  |

Bloqueantes:

- numeracion duplicada;
- stock vendido dos veces;
- saldo imputado dos veces;
- transaccion confirmada a medias sin recuperacion.

## Hardware y mostrador

Objetivo: validar perifericos, logs y fallback operativo.

| Control | Estado | Evidencia | Observaciones |
|---|---|---|---|
| Configurar puesto/dispositivo | pendiente |  |  |
| Registrar venta POS con log de impresion | pendiente |  |  |
| Registrar error de impresora controlado | pendiente |  |  |
| Registrar referencia de adquirente en tarjeta/QR | pendiente |  |  |
| Validar ticket/PDF contra comprobante | pendiente |  |  |
| Validar operacion con lector de codigo si esta disponible | pendiente |  |  |

Bloqueantes:

- venta confirmada sin comprobante imprimible o PDF alternativo;
- error de hardware sin log;
- pago electronico sin referencia externa;
- ticket distinto al comprobante.

## Cierre diario y mensual

Objetivo: validar que el dia y el periodo cierran sin diferencias.

| Control | Estado | Evidencia | Observaciones |
|---|---|---|---|
| Total ventas del dia contra comprobantes | pendiente |  |  |
| Total cobranzas contra movimientos de caja/tesoreria | pendiente |  |  |
| Total pagos contra cuentas a pagar | pendiente |  |  |
| Stock final contra kardex | pendiente |  |  |
| Libro IVA ventas contra facturas/tickets/NC | pendiente |  |  |
| Libro IVA compras contra facturas/NC proveedor | pendiente |  |  |
| Balance de comprobacion sin diferencias | pendiente |  |  |
| Eventos fiscales sin errores abiertos | pendiente |  |  |
| Logs criticos revisados | pendiente |  |  |

Bloqueantes:

- diferencias no explicadas;
- comprobantes fiscales pendientes sin accion;
- asientos faltantes;
- saldos manualmente corregidos para cerrar.

## Dictamen final

| Resultado | Valor |
|---|---|
| Controles aprobados |  |
| Controles con observacion |  |
| Controles rechazados |  |
| Bloqueantes abiertos |  |
| Decision final |  |
| Responsable QA |  |
| Responsable funcional |  |
| Fecha de cierre |  |

Decision posible:

- `aprobado para siguiente fase`
- `aprobado con observaciones no bloqueantes`
- `rechazado por bloqueantes`
