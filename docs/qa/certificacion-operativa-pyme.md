# Certificacion operativa pyme

## Objetivo

Certificar que `Codex` puede operar como sistema principal de una pyme argentina en un circuito diario completo, sin inconsistencias entre facturacion, stock, caja, tesoreria, contabilidad, IVA y auditoria.

Este plan no requiere cambios de base de datos. Define revision, pruebas funcionales, evidencias y criterios de salida.

## Orden de trabajo

1. ARCA + IVA/Contabilidad.
2. Tesoreria avanzada.
3. QA integral y concurrencia.
4. Hardware y refinamiento comercial.

## Modo de ejecucion

Cada fase se ejecuta como una corrida controlada. Una corrida no modifica estructura de base de datos: usa la aplicacion, datos existentes o datos cargados manualmente desde pantallas/API y deja evidencia verificable.

### Preparacion de una corrida

- Definir empresa, sucursal, usuario operador y usuario administrador.
- Definir ambiente: `desarrollo`, `homologacion` o `produccion`.
- Registrar fecha y hora de inicio.
- Registrar version o commit probado.
- Confirmar que no hay migraciones pendientes para esta prueba.
- Confirmar que existe backup operativo antes de probar con datos reales.
- Crear una hoja de evidencias con IDs de operaciones y capturas/exportaciones.

### Resultado posible

- `aprobado`: cumple todos los criterios y no quedan diferencias.
- `aprobado con observacion`: cumple el circuito, pero deja una mejora no bloqueante.
- `rechazado`: falla un bloqueante o requiere correccion manual para cuadrar.

### Plantilla de evidencia

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
| Circuito |  |
| Comprobantes |  |
| Movimientos de stock |  |
| Movimientos de caja/tesoreria |  |
| Asientos contables |  |
| Registros IVA |  |
| Eventos ARCA |  |
| Logs hardware |  |
| Diferencias encontradas |  |
| Decision |  |
| Observaciones |  |

## Criterio de salida

Codex queda en nivel operativo comparable a Tango para gestion comercial cuando:

- una operacion completa compra -> stock -> venta -> cobranza -> caja -> contabilidad -> IVA -> ARCA puede ejecutarse y auditarse de punta a punta;
- cada comprobante critico deja trazabilidad documental, contable, fiscal y financiera;
- no existen diferencias no explicadas entre stock fisico/logico, caja, cuentas corrientes, IVA y asientos;
- las fallas de ARCA, caja, stock, numeracion, pagos y hardware son recuperables y quedan registradas;
- los cierres diarios y mensuales pueden repetirse con los mismos resultados.

## Bloqueantes antes de produccion

- ARCA en `desarrollo` no es valido para salida productiva.
- Una factura fiscalizable sin CAE real en homologacion no certifica el flujo fiscal.
- `ArcaService::consultSale()` debe validarse contra el servicio real; hoy la revision indica que devuelve estado local del comprobante.
- La contabilidad no puede depender de mapeos ausentes. Si `AccountingService` devuelve `skipped: no_account_mapping`, el circuito debe quedar marcado como no certificado.
- El Libro IVA compras/ventas debe reconciliar contra comprobantes reales, no solo contra reportes agregados.
- La suite automatizada actual no alcanza para certificar paridad: debe cubrir flujos end-to-end y concurrencia.
- Ningun circuito critico debe depender de correcciones manuales posteriores para cuadrar saldos.

## Fase 1 - ARCA + IVA/Contabilidad

### Alcance minimo

- Configuracion fiscal por empresa validada: CUIT, certificado, clave, ambiente, punto de venta y tipo de comprobante.
- WSAA en homologacion con ticket cacheado por empresa/ambiente.
- Autorizacion real de factura o ticket fiscalizable con CAE.
- Consulta posterior real del comprobante autorizado.
- Registro de evento en `sales_arca_events` e `integration_logs`.
- Asiento automatico para venta, cobranza, compra, pago y cierre de caja.
- Libro IVA ventas y compras conciliado contra comprobantes.

### Precondiciones

- Empresa con CUIT valido y condicion fiscal cargada.
- Punto de venta fiscal configurado.
- Tipos de comprobante fiscalizables activos.
- Certificado y clave disponibles para homologacion.
- Ambiente ARCA distinto de `desarrollo` para certificar.
- Mapeo contable completo para:
  - ventas
  - IVA debito fiscal
  - clientes / cuentas a cobrar
  - caja / bancos / medios de cobro
  - compras
  - IVA credito fiscal
  - proveedores / cuentas a pagar
  - pagos
  - diferencias de caja
- Libro IVA ventas y compras accesible por pantalla o API.

### Evidencias requeridas

- Captura o export del diagnostico fiscal listo.
- ID de venta, tipo de comprobante, punto de venta, numero, CAE y vencimiento CAE.
- Evento ARCA de autorizacion y consulta.
- Asiento contable balanceado por cada comprobante.
- Libro IVA con la operacion incluida.
- Diferencia cero entre total comprobante, total contable e IVA declarado.

### Runbook Fase 1

#### 1. Revision fiscal previa

- Abrir configuracion de ventas/fiscal de la empresa.
- Verificar CUIT, ambiente, certificado, clave, punto de venta y servicio.
- Ejecutar diagnostico de certificado/clave.
- Ejecutar prueba WSAA.
- Registrar resultado, ambiente y ruta/cache de ticket sin exponer secretos.

Resultado esperado:

- WSAA responde correctamente en homologacion.
- El sistema distingue homologacion de produccion.
- No quedan errores de permisos, CUIT, certificado o clave.

#### 2. Venta fiscalizable con CAE

- Crear cliente con condicion fiscal valida.
- Crear venta con producto gravado.
- Confirmar venta.
- Autorizar ARCA.
- Verificar CAE, vencimiento CAE, servicio, punto de venta y numero.
- Consultar comprobante contra ARCA.
- Revisar eventos fiscales e integration logs.

Resultado esperado:

- La venta queda confirmada y autorizada.
- El CAE no es simulado.
- La consulta no depende solo de campos locales.
- El PDF/ticket muestra datos fiscales coherentes.

#### 3. Impacto contable de venta

- Buscar asiento de la venta.
- Verificar debitos y creditos.
- Verificar cuenta de cliente/cobranza, ventas e IVA debito fiscal.
- Confirmar que el asiento referencia el comprobante.

Resultado esperado:

- El asiento esta balanceado.
- No existe `skipped: no_account_mapping`.
- La suma del asiento coincide con total, neto e IVA de la venta.

#### 4. Cobranza y caja

- Abrir caja si el canal lo requiere.
- Registrar cobranza total o parcial.
- Aplicar la cobranza al comprobante.
- Verificar saldo del cliente.
- Verificar movimiento de caja o tesoreria.
- Verificar asiento de cobranza.

Resultado esperado:

- El saldo baja correctamente.
- El movimiento financiero tiene medio y referencia si corresponde.
- El asiento de cobranza esta balanceado.

#### 5. Compra con IVA

- Crear proveedor con condicion fiscal valida.
- Registrar factura/recepcion de compra con producto gravado.
- Verificar cuenta a pagar.
- Verificar asiento de compra.
- Verificar IVA credito fiscal.

Resultado esperado:

- La deuda con proveedor coincide con el total del comprobante.
- El asiento esta balanceado.
- El IVA credito aparece en Libro IVA compras.

#### 6. Libros IVA

- Generar Libro IVA ventas del periodo.
- Generar Libro IVA compras del periodo.
- Cruzar ventas contra comprobantes autorizados.
- Cruzar compras contra facturas/proveedores.
- Comparar neto, IVA y total.

Resultado esperado:

- Libro IVA ventas incluye la venta fiscalizada.
- Libro IVA compras incluye la compra.
- No hay diferencias entre comprobantes, asientos y libros.

### Reglas de rechazo

- CAE simulado en ambiente `desarrollo`.
- Consulta ARCA que solo lee campos locales.
- Asiento omitido por falta de mapping.
- IVA neto/tasa/total distinto al comprobante.
- Comprobante confirmado sin trazabilidad fiscal o contable.

### Checklist de aprobacion Fase 1

| Control | Estado | Evidencia |
|---|---|---|
| WSAA homologacion OK |  |  |
| Venta fiscalizada con CAE real |  |  |
| Consulta ARCA real OK |  |  |
| Evento en `sales_arca_events` |  |  |
| Evento en `integration_logs` |  |  |
| PDF/ticket fiscal coherente |  |  |
| Asiento de venta balanceado |  |  |
| Asiento de cobranza balanceado |  |  |
| Asiento de compra balanceado |  |  |
| Libro IVA ventas reconciliado |  |  |
| Libro IVA compras reconciliado |  |  |
| Sin `skipped: no_account_mapping` |  |  |
| Sin diferencias manuales |  |  |

## Fase 2 - Tesoreria avanzada

### Alcance minimo

- Caja abierta por usuario/puesto.
- Cobranza con efectivo, transferencia, tarjeta/QR y cheque.
- Pago a proveedor con medio identificable.
- Referencia externa para medios electronicos.
- Cheques con estado y vencimiento.
- Conciliacion por medio y por sesion.
- Cierre de caja con diferencia justificada.
- Asiento contable de cobro, pago y diferencia de caja.

### Evidencias requeridas

- ID de sesion de caja.
- Movimiento de caja por cada cobro/pago.
- Medio de pago, gateway o referencia externa.
- Conciliacion por medio.
- Cierre de caja con esperado, real y diferencia.
- Asientos asociados.

### Reglas de rechazo

- Cobranza registrada sin caja abierta cuando el canal requiere caja.
- Movimiento duplicado para el mismo comprobante.
- Pago/cobro sin impacto en cuenta corriente.
- Cierre de caja con diferencia sin motivo.
- Medio electronico sin referencia cuando corresponde.

## Fase 3 - QA integral y concurrencia

### Circuitos obligatorios

- Compra: proveedor -> orden -> recepcion parcial -> recepcion total -> stock -> cuenta a pagar -> pago -> asiento.
- Venta estandar: cliente -> presupuesto -> pedido -> remito -> factura -> CAE -> cobranza -> caja -> asiento -> Libro IVA.
- POS/Kiosco: caja abierta -> venta -> pago mixto -> impresion/log hardware -> CAE si corresponde -> cierre.
- Devoluciones: venta confirmada -> devolucion parcial -> ajuste stock -> nota de credito -> cuenta corriente -> asiento.
- Inventario: ingreso -> egreso -> transferencia -> ajuste -> kardex valorizado -> trazabilidad lote/serie.
- Mes fiscal: ventas/compras del periodo -> Libro IVA -> balance de comprobacion -> cierre operativo.

### Pruebas de concurrencia

- Dos usuarios intentan confirmar ventas sobre el mismo stock.
- Dos usuarios intentan usar la misma secuencia de comprobante.
- Dos cobranzas intentan imputarse al mismo saldo.
- Dos sesiones intentan abrir la misma caja.
- ARCA responde lento o falla durante autorizacion.
- Hardware no responde despues de confirmar una venta.

### Reglas de rechazo

- Stock negativo no permitido por configuracion.
- Numeracion duplicada.
- Saldo de cuenta corriente negativo por doble imputacion.
- Caja duplicada abierta para el mismo puesto cuando no corresponde.
- Transaccion confirmada a medias sin evento recuperable.

## Fase 4 - Hardware y refinamiento comercial

### Alcance minimo

- Registro por puesto de impresora/ticketera y dispositivo de cobro.
- Log de intento de impresion.
- Referencia del adquirente en tarjeta/QR.
- Manejo funcional cuando el dispositivo falla.
- Promociones, listas de precio y descuentos con autorizacion si exceden politica.
- Reporte diario de ventas por canal, medio, vendedor y caja.

### Evidencias requeridas

- `hardware_logs` con evento OK y evento de error controlado.
- Venta POS con referencia de pago.
- Ticket/PDF fiscal coherente con comprobante.
- Descuento aplicado dentro de politica o con autorizacion.
- Reporte diario conciliado contra caja y ventas.

## Matriz de decision

| Area | Estado para certificar | Evidencia minima |
|---|---|---|
| ARCA | Bloqueante | CAE real + consulta real |
| IVA | Bloqueante | Libro IVA cuadrado contra comprobantes |
| Contabilidad | Bloqueante | Asientos balanceados sin `skipped` |
| Caja | Bloqueante | Apertura, movimientos y cierre conciliado |
| Stock | Bloqueante | Kardex y stock sin diferencias |
| Tesoreria | Alta | Medios, cheques y conciliacion por referencia |
| QA | Bloqueante | Circuitos end-to-end reproducibles |
| Hardware | Alta | Logs, referencias y fallback operativo |
| Comercial | Media | Promos/descuentos auditables |

## Resultado esperado

Al finalizar, cada prueba debe generar una carpeta o registro de evidencias con:

- empresa, sucursal, usuario y fecha;
- IDs de comprobantes involucrados;
- capturas o exports de fiscal, caja, stock, IVA y contabilidad;
- diferencias encontradas;
- decision: `aprobado`, `aprobado con observacion` o `rechazado`.

## Registro de corridas

| Corrida | Fecha | Ambiente | Circuito | Resultado | Responsable | Observaciones |
|---|---|---|---|---|---|---|
| 001 |  | homologacion | ARCA + IVA/Contabilidad | pendiente |  |  |
| 002 |  | homologacion | Tesoreria avanzada | pendiente |  |  |
| 003 |  | homologacion | QA integral y concurrencia | pendiente |  |  |
| 004 |  | homologacion | Hardware y refinamiento comercial | pendiente |  |  |

## Hallazgos tecnicos conocidos

Estos puntos deben revisarse antes de aprobar una salida productiva:

- `ArcaService::consultSale()` debe confirmar consulta real contra ARCA. Si solo devuelve el estado persistido localmente, no certifica el criterio fiscal.
- `AccountingService` permite omitir asientos cuando no existe mapeo contable. Para certificacion, ese caso debe tratarse como bloqueante operativo.
- La suite automatizada existente no cubre todavia los circuitos end-to-end requeridos por este documento.
- Las pruebas de concurrencia deben ejecutarse sobre stock, numeracion, caja y saldos antes de operar con usuarios reales.

## Primer trabajo recomendado

La primera corrida debe ser `001 - ARCA + IVA/Contabilidad` en homologacion.

Secuencia corta:

1. Validar bundle fiscal y WSAA.
2. Emitir venta gravada con CAE real.
3. Consultar el comprobante.
4. Registrar cobranza.
5. Registrar compra con IVA.
6. Verificar asientos balanceados.
7. Generar Libro IVA ventas/compras.
8. Reconciliar comprobantes, IVA, contabilidad y caja.

Decision esperada:

- `aprobado` si todo cuadra sin intervencion manual.
- `rechazado` si ARCA no consulta real, falta mapeo contable o el IVA no reconcilia.
