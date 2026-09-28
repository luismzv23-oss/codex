# Recargo por medio de pago en kiosco

## Pagos combinados (28/09/2026)

Kiosco admite hasta 20 líneas de pago. Cada una envía el GUID, importe asignado antes del recargo, efectivo entregado cuando corresponde y referencia. El servidor resuelve tipo, código y porcentaje desde el catálogo activo de la empresa y rechaza que la suma asignada sea diferente del total con impuestos y descuentos. Los porcentajes e importes de recargo enviados por el cliente no se utilizan.

Ejemplo: base 10.000; efectivo 6.000 sin recargo y tarjeta 4.000 al 2,50 % generan 100 de recargo y 10.100 a pagar. El efectivo entregado en exceso produce vuelto, sin aumentar la base del recargo. Las transferencias mantienen su estado pendiente hasta la confirmación existente en Cobranzas.

La migración `2026-09-28-100000_AddPaymentLineSurcharges` agrega a `sale_payments` el GUID y código del medio, base asignada, porcentaje, recargo, importe entregado y vuelto. Los campos agregados a la cabecera de venta conservan el total del recargo; porcentaje y medio de cabecera solo se informan si hay un único pago. Ticket y PDF muestran las líneas y sus recargos. Los comprobantes anteriores conservan su representación mediante los datos de cabecera.

La limitación fiscal descrita abajo permanece vigente. Este cambio no define un nuevo tratamiento del recargo ante ARCA.

El selector envía el GUID del medio configurado. El servidor obtiene su tipo y porcentaje desde el catálogo activo de la empresa; no acepta un porcentaje enviado por el navegador.

El recargo se calcula sobre el total con impuestos, después de descuentos. Se redondea a centavos y se agrega al total a cobrar. No genera productos ni movimientos de stock adicionales. El cálculo del vuelto usa el total final.

La venta conserva `payment_method_id`, `payment_method_code`, `payment_surcharge_rate` y `payment_surcharge_amount`, independientemente de cambios posteriores en el catálogo. Las ventas anteriores quedan con recargo cero. Migración local aplicada: `2026-09-23-140000_AddSalePaymentSurcharge`.

Pantalla y vista de impresión del kiosco muestran neto, impuestos por alícuota, descuentos generales cuando existen, total con impuestos, recargo y total a pagar. El PDF usa los importes guardados. El asiento de venta discrimina el recargo en una línea de ingreso separada.

## Límite de emisión fiscal

El conector ARCA actual compone el total con neto e IVA de productos. No tiene una definición del tratamiento fiscal del recargo. Los comprobantes con recargo no se envían con importes inconsistentes: devuelven `SURCHARGE_FISCAL_MAPPING_REQUIRED`. La venta local, el cobro y el PDF conservan el recargo; esto no equivale a autorización fiscal. Es necesario definir e implementar el tratamiento fiscal antes de habilitar CAE para estos comprobantes.

## Validación

Pruebas del cálculo: base 12.100,00 y 2,50 % produce recargo 302,50 y total 12.402,50; descuentos, cero y redondeo a centavos. Verificación adicional de los helpers JavaScript reales del kiosco con cambio de medio, múltiples alícuotas y ticket vacío. La autorización real ante ARCA no se probó.
