# Recargo por medio de pago en kiosco

El selector envía el GUID del medio configurado. El servidor obtiene su tipo y porcentaje desde el catálogo activo de la empresa; no acepta un porcentaje enviado por el navegador.

El recargo se calcula sobre el total con impuestos, después de descuentos. Se redondea a centavos y se agrega al total a cobrar. No genera productos ni movimientos de stock adicionales. El cálculo del vuelto usa el total final.

La venta conserva `payment_method_id`, `payment_method_code`, `payment_surcharge_rate` y `payment_surcharge_amount`, independientemente de cambios posteriores en el catálogo. Las ventas anteriores quedan con recargo cero. Migración local aplicada: `2026-09-23-140000_AddSalePaymentSurcharge`.

Pantalla y vista de impresión del kiosco muestran neto, impuestos por alícuota, descuentos generales cuando existen, total con impuestos, recargo y total a pagar. El PDF usa los importes guardados. El asiento de venta discrimina el recargo en una línea de ingreso separada.

## Límite de emisión fiscal

El conector ARCA actual compone el total con neto e IVA de productos. No tiene una definición del tratamiento fiscal del recargo. Los comprobantes con recargo no se envían con importes inconsistentes: devuelven `SURCHARGE_FISCAL_MAPPING_REQUIRED`. La venta local, el cobro y el PDF conservan el recargo; esto no equivale a autorización fiscal. Es necesario definir e implementar el tratamiento fiscal antes de habilitar CAE para estos comprobantes.

## Validación

Pruebas del cálculo: base 12.100,00 y 2,50 % produce recargo 302,50 y total 12.402,50; descuentos, cero y redondeo a centavos. Verificación adicional de los helpers JavaScript reales del kiosco con cambio de medio, múltiples alícuotas y ticket vacío. La autorización real ante ARCA no se probó.
