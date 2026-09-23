# Análisis de medios de pago en facturación

Alcance: definición y utilización de medios en ventas, kiosco y cobranzas. Revisión de código; no se modificaron operaciones ni datos. No incluye el rediseño de apertura y cierre de caja.

## Base existente

- `sale_payments`: varias líneas de pago por venta.
- `sales_receipts`: cabecera de recibo con cliente, moneda y un solo medio de pago.
- `sales_receipt_items`: aplicaciones de un recibo a varias cuentas por cobrar/facturas.
- `sales_receivables`: saldo, importe pagado y vencimiento por comprobante.
- Condiciones comerciales y plazos de pago existentes (`SalesConditionModel`, `payment_terms_days`).
- Pasarelas y cheques existentes; no equivalen a un catálogo completo de medios.

## Hallazgos

1. **Alta: anulación de cobro incompleta.** `SalesController::voidReceipt()` <!-- restaura saldos, elimina pagos asociados y marca el recibo anulado. No genera contramovimiento de fondos ni reverso del asiento de `syncSalesReceipt()`. Conserva recibo y auditoría, pero elimina el detalle de pago en lugar de registrar su reverso. Definir separadamente desaplicación, reverso y devolución de dinero. -->

2. **Alta: sobreaplicación por líneas repetidas o concurrencia.** `receiptApplicationsPayload()` verifica cada línea contra el saldo previo, sin agrupar IDs repetidos ni bloquear la cuenta por cobrar. Dos líneas de 80 contra el mismo saldo de 100 pasan individualmente. `storeReceipt()` calcula el total con ambas; el uso de `min()` al actualizar saldos no invalida los pagos de 160 registrados. La comprobación ocurre antes de la transacción. Agrupar/rechazar duplicados y comprobar saldos bajo bloqueo, tanto web como API.

3. **Alta: medios sin catálogo ni validación central.** Los formularios usan opciones fijas (`cash`, `card`, `transfer`, `check`, `qr`, `mixed`, según pantalla). `parseSalePayments()` y `storeReceipt()` aceptan cadenas recibidas sin consultar un catálogo de medios activos por empresa, moneda o sucursal. No existe aquí la configuración propuesta de destino y datos obligatorios por medio. Las referencias de pasarela y cheque del recibo necesitan validación explícita de pertenencia, estado y compatibilidad.

4. **Alta: metadatos de pago descartados en ventas.** El parser web incluye `gateway_id`, `cash_check_id` y `external_reference`, pero `SalePaymentModel::$allowedFields` no los incluye. `insertSaleChildren()` inserta mediante ese modelo y la sincronización de fondos intenta leer después esos campos. La API además devuelve menos campos en su parser. Unificar contrato de datos y verificar persistencia con pruebas de ida y vuelta.

5. **Alta: moneda de aplicaciones no validada.** `storeReceipt()` toma la moneda base de la empresa; el armado de aplicaciones valida empresa, cliente y saldo, pero no moneda de cada deuda ni conversión. No basta con sumar importes nominales de comprobantes potencialmente en monedas distintas. Registrar moneda original, conversión y aplicado; rechazar combinaciones no admitidas.

6. **Alta: confirmación y acreditación no separadas.** Los pagos se insertan como `registered`; el recibo tiene estado predeterminado `applied`. `refreshSalePaymentStatus()` suma todas las líneas sin filtrar por confirmación o reverso. Informar una transferencia no tiene un paso propio de verificación antes de cancelar deuda. Faltan estados por línea y distribución de importes confirmados disponibles.

7. **Alta: integración de fondos puede omitir fallos.** `storeReceipt()` exige sesión de caja incluso para medios bancarios, registra todos los medios por ese camino e ignora el retorno de `CashService::registerMovement()`. Si devuelve `null`, ese resultado no provoca por sí mismo rollback. Debe existir destino explícito por línea y comprobarse que cada operación requerida se complete.

8. **Funcional: pago combinado desigual según canal.** El formulario de venta admite varias líneas, pero el recibo solo tiene un medio en cabecera. Kiosco ofrece `mixed` como opción de una única línea. `mixed` no permite saber cuánto se pagó en cada medio; debe ser una característica derivada de las líneas, no un medio de catálogo.

9. **Funcional: anticipos, excedentes y vuelto sin modelo completo.** El recibo exige al menos una aplicación y fija su total como la suma aplicada: no conserva dinero disponible sin aplicar. El saldo de la cuenta por cobrar se limita a cero. Kiosco muestra vuelto, pero `SalePaymentModel` no distingue campos de entregado, vuelto y aplicado. No equivale al modelo propuesto de saldo a favor y devolución explícita.

## Correspondencia con la propuesta

| Capacidad | Situación |
|---|---|
| Condiciones de venta | Base existente; revisar su vinculación uniforme con comprobantes |
| Catálogo configurable de medios | Falta |
| Varios pagos por factura | Existe mediante líneas de venta y cobros posteriores |
| Cobro aplicado a varias facturas | Existe, limitado al mismo cliente; faltan controles robustos |
| Varios medios en un cobro agrupado | Falta detalle de medios del recibo |
| Confirmación y acreditación por línea | Falta |
| Anticipos y excedentes disponibles | Falta modelo explícito |
| Reversos preservando historial y fondos | Incompleto |
| Cuentas destino y liquidaciones | Los mecanismos de caja/pasarelas no cubren el modelo propuesto |

## Recomendación

Conservar condiciones de venta y aplicaciones aprovechables. Introducir catálogo de medios por empresa, detalle de medios del cobro y cuentas destino; definir confirmación, acreditación y reversos desde el contrato de datos. Antes de conectar nuevos medios, resolver sobreaplicación, moneda, pérdida de metadatos y anulación incompleta. Migrar los códigos históricos sin reinterpretar ni duplicar movimientos existentes.

Validación pendiente: pruebas aisladas de recibo duplicado, dos cobros simultáneos, moneda incompatible, transferencia pendiente, persistencia de referencias, fallo de movimiento, reverso completo y parcial. Los hallazgos de esta revisión proceden de lectura de código; no se ejecutaron estos escenarios contra la base instalada.
