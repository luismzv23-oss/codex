# Verificaci?n de venta ? Empresa Demo

Fecha: 2026-10-03. Usuario: vendedor (Vendedor Demo). Se registr? una sola venta mediante el formulario HTTP autenticado de Kiosco. No se modific? c?digo ni se corrigieron registros contables.

## Comprobante

- Venta: `54afb42c-201e-4fa0-ad45-198dd5b60131`.
- N?mero interno: FCB-00000005. Factura B: 00001-00000054.
- CAE: 86400944643938. Vencimiento: 2026-10-13.
- ARCA: autorizada en homologaci?n, sin validez fiscal de producci?n.
- Producto: 1 MIRINDA NARANJA, precio base $880,00.
- Cobros: efectivo $400,00; T NARANJA $485,76 (base $480,00 y recargo $5,76).
- Total $885,76; neto $732,03; IVA $153,73.

## Resultados contrastados

- Inventario: 159 ? 158 unidades; movimiento de egreso de 1 unidad asociado al comprobante y vendedor.
- Caja: dos ingresos, $400,00 y $485,76; incremento de saldo $885,76.
- Cuenta por cobrar: pagada, saldo $0,00.
- Solicitud efectivamente enviada a ARCA: ImpTotal 885.76, ImpNeto 732.03, ImpIVA 153.73; al?cuota IVA con iguales base e impuesto.
- Ticket generado: producto y total $885,76, subtotal $732,03, IVA e IVA contenido $153,73.
- QR: generado como SVG; el contenido decodificado informa importe 885.76 y el CAE autorizado. No contiene un campo separado de IVA.
- Visibilidad por medio: efectivo visible; T NARANJA oculto; sin rengl?n de recargo en el ticket.

## Incidencias detectadas antes de la correcci?n

1. **Duplicaci?n contable:** asientos 47 y 48, ambos publicados, para esta misma venta y con iguales l?neas. Cada uno debita $885,76 y acredita ingreso $727,27, recargo $5,76 e IVA $152,73. El controlador llama a AccountingService::syncSale y luego el evento sale.confirmed vuelve a invocar journalFromSale mediante AutomationService, sin comprobar existencia en esa segunda ruta.
2. **IVA contable inconsistente:** cada asiento usa $152,73, mientras ticket y ARCA usan $153,73. AccountingService toma los importes base almacenados, sin la redistribuci?n fiscal del recargo.
3. **Libro IVA persistido:** no se encontr? registro en vat_sales_books por sale_id, source_id ni n?meros de comprobante. En app solo se encontraron la migraci?n y el modelo de esa tabla, sin integraci?n de escritura. Esto no prueba ausencia en informes que calculen IVA directamente desde ventas.
4. **Identificaci?n del emisor:** el ticket muestra CUIT J-2710106858-4; el QR autorizado contiene CUIT 27191068594. La configuraci?n de homologaci?n y los datos visibles de empresa requieren revisi?n.

## Alcance y evidencia

Se comprobaron datos antes/despu?s mediante consultas de solo lectura, respuesta de registro, solicitud ARCA persistida y generaci?n del HTML del ticket con el JavaScript real del proyecto. No se pudo verificar visualmente en navegador ni realizar impresi?n f?sica porque la herramienta de navegador no inici?. No se reenvi? ni anul? la venta.

Evidencia local: tmp/demo-sale-qa/{before.json,after.json,ledger-check.json,sale-response.json,ticket-data.json,receipt-check.json,ticket-rendered.html}.

El circuito de venta, stock y caja funcion?; la integraci?n contable no queda aprobada por las incidencias anteriores.


## Correcci?n aplicada y verificaci?n final

Por autorizaci?n del usuario, se corrigi? el c?digo y se regulariz? la venta de prueba.

- La contabilizaci?n de ventas es idempotente y bloquea la fila de venta durante la creaci?n del asiento en MySQL. Las llamadas del controlador y la automatizaci?n ya no duplican asientos.
- Contabilidad y libro IVA usan PaymentFiscalPolicy, igual que ticket y ARCA, con importes neto 732,03, IVA 153,73 y total 885,76. Se evita descontar dos veces un descuento global en la alternativa sin detalle.
- El libro IVA incluye todo el ?ltimo d?a y toma la numeraci?n y documento del receptor del pedido autorizado cuando est? disponible.
- El ticket Kiosco y el PDF POS muestran el CUIT del emisor del comprobante autorizado, coincidente con su QR.
- Los balances excluyen borradores y asientos fuera del per?odo.
- La confirmaci?n de venta propaga fallos de contabilizaci?n, evitando una confirmaci?n silenciosa con error contable.
- Asiento 49: reverso compensatorio del duplicado 48. Asiento 50: reclasificaci?n de $1,00 de ingreso a IVA. Se conservaron los asientos originales 47 y 48. No se modific? el CAE ni se reenvi? la factura.
- Resultado contable neto de esta venta: d?bito 885,76; ingreso 732,03; IVA 153,73. Repetir la sincronizaci?n no agreg? asientos.
- Verificaci?n final de solo lectura: ticket correcto; CAE 86400944643938 autorizado; stock 158; balance contable equilibrado.
- Suite completa: **132 pruebas, 550 aserciones, sin errores ni omisiones**. Ejecuci?n con extensi?n SQLite habilitada solo para CLI y OPENSSL_CONF apuntando a C:\xampp\php\extras\ssl\openssl.cnf.
- Evidencia adicional: accounting-before-repair.json, accounting-after-repair.json y final-verification.json en tmp/demo-sale-qa.

La validaci?n cubre el circuito probado y la suite disponible; no certifica la totalidad de m?dulos, impresi?n f?sica ni operaci?n fiscal en producci?n. No se crearon ventas adicionales.


## Ampliaci?n de pruebas solicitada

- Suite completa: **135 pruebas y 569 aserciones**, sin fallos ni omisiones.
- Nuevos casos: IVA mixto 21%/10,5% coincidente entre contabilidad, libro IVA y ambos formatos ARCA; fallo contable sin asientos parciales; rollback de la venta que tambi?n revierte el asiento y permite reintentar.
- MySQL aislado: dos procesos contabilizan simult?neamente la misma venta; se crea exactamente un asiento con neto 732,03 e IVA 153,73. Script reproducible: tests/sales_accounting_mysql_check.php.
- MySQL inventario: migraciones, ubicaciones m?ltiples, reservas e ingresos concurrentes aprobados.
- MySQL configuraci?n: numeraci?n concurrente y escritura de par?metros aprobadas.
- MySQL compras: recepci?n y pago concurrentes, preservaci?n de relaciones y migraciones aprobados.
- Las cuatro bases temporales se eliminaron al finalizar. No se registraron nuevas ventas ni se enviaron comprobantes a ARCA.
- Se aisl? la prueba de autenticaci?n en una carpeta ?nica, eliminada al finalizar, para no sobrescribir los certificados y cach? de prueba existentes. Verificaci?n de ese ajuste: 4 pruebas, 11 aserciones, aprobadas.
