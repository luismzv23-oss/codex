# Medios de pago empresariales: primera etapa

## Alcance

POS, Kiosco, nueva venta, edición de borradores, cobranzas, anticipos y API de ventas usan el catálogo de medios activos de la empresa. Los botones mantienen iconos y etiquetas accesibles. Los popups siguen mostrando únicamente el formulario.

El servidor vuelve a comprobar empresa, moneda activa, sucursal y punto de venta. El cliente deshabilita medios fuera de su alcance, pero no sustituye la validación del servidor. Los campos obligatorios configurados se presentan en el formulario y se validan en el guardado. El tipo Otro no se usa para mover fondos sin un circuito definido. No se simulan planes de cuotas: solo se admite una cuota.

Cada pago nuevo conserva identificador, código, nombre y una copia de la configuración del medio, sus datos obligatorios, importe base, recargo y condición de confirmación. Los porcentajes enviados por el navegador nunca reemplazan los configurados en el servidor.

## Borradores

Nueva venta y edición admiten cero pagos o pagos parciales, dejando saldo a cuenta corriente. POS inmediato y Kiosco mantienen la cobertura completa. Guardar un borrador no confirma la venta, abre un comprobante ni crea movimientos de cobro.

Los pagos existentes se recuperan con sus identificadores, importes, referencias, fechas y recargos. Una línea sin cambios conserva sus datos históricos aunque cambie el catálogo. Un pago genérico antiguo permanece identificado como histórico; nunca se convierte en una tarjeta específica por coincidencia de tipo. Para modificar un pago se debe seleccionar un medio vigente. Antes de confirmar la venta se comprueba nuevamente que sus medios configurados estén habilitados.

La actualización del borrador usa una transacción con bloqueo de empresa y venta. Los pagos que se conservan no se eliminan y recrean. Los borradores con cobranzas aplicadas requieren revisión.

## Cobranzas y anticipos

Los recibos usan medios específicos de la empresa, con un punto de venta opcional para aplicar sus restricciones. Los importes del recibo representan dinero efectivamente recibido: no agregan recargos nuevos a facturas ya emitidas ni a anticipos. El porcentaje vigente se conserva en la copia de configuración para auditoría.

Las transferencias y cualquier otro medio con Confirmación requerida quedan pendientes. No disminuyen la deuda ni generan un movimiento de cobro hasta su verificación explícita con evidencia. El detalle del recibo y Cobranzas permiten confirmarlos. Los reintentos no vuelven a aplicar el pago. Los anticipos conservan su circuito de saldo a favor, sin crear una venta.

El destino contable y la acreditación siguen los circuitos actuales por tipo de fondo. Esta etapa no modifica movimientos manuales de caja, conciliaciones ni pagos a proveedores.

## API

El catálogo de POS incluye `payment_methods`. Los pagos nuevos se envían con identificadores del catálogo, por ejemplo:

```json
{
  "currency_code": "ARS",
  "point_of_sale_id": "UUID_DEL_PUNTO",
  "payments": [{
    "payment_method_id": "UUID_DEL_MEDIO",
    "base_amount": "100.00",
    "reference": "Comprobante 123",
    "fields": {"entidad": "Banco indicado"}
  }]
}
```

`base_amount` es el importe antes del recargo para una venta; en un recibo es el importe que se cobra. Campos opcionales: `received_amount`, `external_reference`, `gateway_id`, `cash_check_id`, `paid_at`, `notes`. Los campos personalizados obligatorios se envían en `fields`. En una venta genérica pueden omitirse los pagos; POS inmediato y Kiosco exigen distribuir el total. Se conservan los nombres `kiosk_payments` y `company_payments` como formatos de entrada del componente compartido.

Enviar únicamente un tipo genérico como card ya no crea un pago nuevo: debe elegirse un identificador de la empresa. Las operaciones antiguas conservan sus datos y estados.

## Instalación y pruebas

Aplicar `php spark migrate`. La migración AddPaymentMethodSnapshots agrega tres campos a sale_payments; no asigna medios a pagos históricos.

Pruebas de esta etapa:

```powershell
php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --no-coverage --filter 'SalesPaymentServiceTest|SalesCollectionTest|KioskPaymentsTest|SalesDashboardViewTest|SavedKioskTicketTest|DeliveryNoteInvoicingTest|CompanyPaymentMethodTest|SaleAccountingTest|PaymentFiscalPolicyTest|SalesDeliveryTest|CashSessionSalesTest|CommercialDocumentViewTest|CashRegisterVisibilityTest|PaymentSurchargeTest|KioskTicketFiscalTest' tests
node --test tests/javascript/company-payments.test.cjs
```

SQLite se habilita solo para el proceso de pruebas. No se emiten comprobantes fiscales reales ni se registran cobros de producción durante las pruebas.

La interacción visual en navegador sigue pendiente si no hay un navegador conectado. Las pruebas verifican el renderizado del formulario y la sintaxis de su JavaScript, además de las reglas del componente y los contratos de guardado web y API.

La integración posterior con caja, conciliaciones y proveedores se documenta en [Medios de pago de tesorería](medios-de-pago-tesoreria.md).

## Presentación compacta de POS y Kiosco

Ambos formularios utilizan la misma fila de medio, importe, total/recargo y acciones con iconos. El contenedor no usa desplazamiento horizontal ni filas con márgenes negativos. Referencia, Fecha y Nota no aparecen en la fila principal. Los datos obligatorios del catálogo se completan mediante un popup, accesible desde el icono de datos del medio. La validación abre el popup si falta información; los botones de aceptar y cerrar son iconos. Las notas y demás datos de pagos guardados se conservan en el envío. Las cobranzas mantienen su presentación existente.
